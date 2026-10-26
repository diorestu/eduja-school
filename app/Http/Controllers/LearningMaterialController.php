<?php

namespace App\Http\Controllers;

use App\Models\LearningMaterial;
use App\Models\Student;
use App\Services\ActiveEnrollment;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class LearningMaterialController extends Controller
{
    private function canManage(LearningMaterial $material, Request $request): bool
    {
        return (int) $material->school_id === app(SchoolContext::class)->activeSchoolIdFor()
            && ($request->user()->id === (int) $material->user_id || $request->user()->hasRole(['kepsek','tu','staf_tu']));
    }

    private function canRead(LearningMaterial $material, Request $request): bool
    {
        $schoolId = app(SchoolContext::class)->activeSchoolIdFor();
        if ((int) $material->school_id !== $schoolId) return false;
        if ($this->canManage($material, $request)) return true;
        if (! $material->school_class_id) return true;
        $class = app(ActiveEnrollment::class)->classes($schoolId)->whereKey($material->school_class_id);
        return (clone $class)->whereHas('teacher', fn ($q) => $q->where('user_id', $request->user()->id))->exists()
            || (clone $class)->whereHas('students', fn ($q) => $q->active()->where('students.school_id',$schoolId)
                ->where(fn ($own) => $own->where('user_id',$request->user()->id)->orWhere('guardian_user_id',$request->user()->id)))->exists();
    }

    public function index(Request $request, SchoolContext $context)
    {
        $data = $request->validate(['teacher_id'=>['nullable','integer']]);
        $schoolId = $context->activeSchoolIdFor();
        $materials = LearningMaterial::where('school_id',$schoolId)->when($data['teacher_id'] ?? null,fn ($q,$id) => $q->where('user_id',$id))
            ->with('author','schoolClass')->latest()->get()->filter(fn ($m)=>$this->canRead($m,$request));
        $classes = app(ActiveEnrollment::class)->classes($schoolId)->orderBy('name')->get();
        $authors = LearningMaterial::where('school_id',$schoolId)->with('author')->get()->pluck('author')->filter()->unique('id');
        return view('pages.foundation.materials',compact('materials','classes','authors'));
    }

    public function store(Request $request, SchoolContext $context)
    {
        abort_unless($request->user()->hasRole(['guru','wali_kelas','kepsek','tu','staf_tu']),403);
        $data = $this->validated($request,$context->activeSchoolIdFor(),true);
        $file = $request->file('file');
        unset($data['file']);
        $path = $file->store('learning-materials/'.$context->activeSchoolIdFor(),'local');
        try {
            LearningMaterial::create([...$data,'school_id'=>$context->activeSchoolIdFor(),'user_id'=>$request->user()->id,'file_path'=>$path,'file_name'=>$file->getClientOriginalName()]);
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return back()->with('success','Materi berhasil disimpan.');
    }

    public function update(Request $request, LearningMaterial $material, SchoolContext $context)
    {
        abort_unless($this->canManage($material,$request),403);
        $data = $this->validated($request,$context->activeSchoolIdFor(),false);
        unset($data['file']);
        $old = $material->file_path;
        if ($file = $request->file('file')) {
            $data['file_path'] = $file->store('learning-materials/'.$context->activeSchoolIdFor(),'local');
            $data['file_name'] = $file->getClientOriginalName();
        }
        try { $material->update($data); } catch (\Throwable $e) { if (isset($data['file_path'])) Storage::disk('local')->delete($data['file_path']); throw $e; }
        if (isset($data['file_path'])) Storage::disk('local')->delete($old);
        return back()->with('success','Materi diperbarui.');
    }

    public function destroy(Request $request, LearningMaterial $material)
    {
        abort_unless($this->canManage($material,$request),403);
        $path = $material->file_path;
        $material->delete();
        Storage::disk('local')->delete($path);
        return back()->with('success','Materi dihapus.');
    }

    public function link(Request $request, LearningMaterial $material)
    {
        abort_unless($this->canRead($material,$request),403);
        return redirect(URL::temporarySignedRoute('materials.download',now()->addMinutes(5),['material'=>$material->id,'recipient'=>$request->user()->id]));
    }

    public function download(Request $request, LearningMaterial $material)
    {
        abort_unless($request->hasValidSignature() && $request->integer('recipient') === $request->user()->id && $this->canRead($material,$request),403);
        abort_unless(str_starts_with($material->file_path,'learning-materials/'.$material->school_id.'/') && ! str_contains($material->file_path,'..') && Storage::disk('local')->exists($material->file_path),404);
        return Storage::disk('local')->download($material->file_path,basename($material->file_name));
    }

    private function validated(Request $request,int $schoolId,bool $required): array
    {
        return $request->validate(['title'=>['required','string','max:160'],'description'=>['nullable','string','max:10000'],
            'school_class_id'=>['nullable','integer',Rule::exists('school_classes','id')->where('school_id',$schoolId)],
            'file'=>[$required ? 'required':'nullable','file','mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png','max:10240']]);
    }
}
