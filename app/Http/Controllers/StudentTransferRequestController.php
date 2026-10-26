<?php
namespace App\Http\Controllers;
use App\Models\{Student,StudentTransferRequest};
use App\Services\{AcademicLifecycleService,SchoolContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
class StudentTransferRequestController extends Controller {
    public function index(Request $request,SchoolContext $context) {
        abort_unless($request->user()->hasRole(['kepsek','wakasek','tu','staf_tu']),403);
        $transfers=StudentTransferRequest::where('school_id',$context->activeSchoolIdFor())->with('student')->latest()->paginate(20);
        return view('pages.foundation.transfer-requests',compact('transfers'));
    }
    public function store(Request $request,SchoolContext $context) {
        $schoolId=$context->activeSchoolIdFor();
        $data=$request->validate(['student_id'=>['required','integer'],'destination'=>['required','string','max:255'],'transfer_date'=>['required','date_format:Y-m-d','after_or_equal:today'],'reason'=>['required','string','max:1000'],'document'=>['required','file','mimes:pdf,jpg,jpeg,png','max:5120']]);
        $student=Student::active()->where('school_id',$schoolId)->whereKey($data['student_id'])->where('guardian_user_id',$request->user()->id)->firstOrFail();
        $path=null;
        try { DB::transaction(function () use ($data,$schoolId,$student,$request,&$path) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if (StudentTransferRequest::where('school_id',$schoolId)->where('student_id',$student->id)->where('status','pending')->exists()) throw ValidationException::withMessages(['transfer'=>'Permohonan pindah masih menunggu keputusan.']);
            $path=$request->file('document')->store('transfer-documents/'.$schoolId,'local');
            unset($data['document']);
            StudentTransferRequest::create([...$data,'school_id'=>$schoolId,'requested_by'=>$request->user()->id,'document_path'=>$path,'document_name'=>$request->file('document')->getClientOriginalName(),'status'=>'pending']);
        }); } catch (\Throwable $e) { if($path)Storage::disk('local')->delete($path); throw $e; }
        return back()->with('success','Permohonan pindah dikirim untuk ditinjau sekolah.');
    }
    public function review(Request $request,StudentTransferRequest $transfer,SchoolContext $context,AcademicLifecycleService $lifecycle) {
        abort_unless((int)$transfer->school_id===$context->activeSchoolIdFor(),404);
        abort_unless($request->user()->hasRole(['kepsek','wakasek','tu','staf_tu']),403);
        $data=$request->validate(['decision'=>['required','in:approved,rejected'],'note'=>['nullable','string','max:1000']]);
        DB::transaction(function()use($transfer,$request,$data,$lifecycle){
            $transfer=StudentTransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->status==='pending',422,'Permohonan sudah diproses.');
            if($data['decision']==='approved')$lifecycle->transitionStudent($transfer->school_id,$transfer->student_id,'transferred',$transfer->transfer_date->toDateString(),$transfer->reason,['transfer_destination'=>$transfer->destination,'transfer_document_path'=>$transfer->document_path],$request->user()->id);
            $transfer->update(['status'=>$data['decision'],'review_note'=>$data['note']??null,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);
        });
        return back()->with('success','Keputusan permohonan pindah disimpan.');
    }
    public function document(Request $request,StudentTransferRequest $transfer,SchoolContext $context){
        abort_unless((int)$transfer->school_id===$context->activeSchoolIdFor(),404);
        abort_unless((int)$transfer->requested_by===$request->user()->id||$request->user()->hasRole(['kepsek','wakasek','tu','staf_tu']),403);
        abort_unless(str_starts_with($transfer->document_path,'transfer-documents/'.$transfer->school_id.'/')&&!str_contains($transfer->document_path,'..')&&Storage::disk('local')->exists($transfer->document_path),404);
        return Storage::disk('local')->download($transfer->document_path,basename($transfer->document_name));
    }
}
