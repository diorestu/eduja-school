<?php

namespace App\Http\Controllers;

use App\Models\AiMaterial;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\ApprovalRequest;
use App\Models\AttendanceRequest;
use App\Services\AiAssistantService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsFoundationController extends Controller
{
    public function attendanceRequests(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $user = request()->user();
        $canReview = $user->hasRole(['kepsek', 'wakasek', 'tu', 'staf_tu', 'wali_kelas']);
        $status = request()->validate(['status' => ['nullable', 'in:pending,approved,rejected']])['status'] ?? null;
        $query = AttendanceRequest::where('school_id', $schoolId)
            ->when(! $canReview, fn ($q) => $q->where(fn ($own) => $own->where('requester_id', $user->id)->orWhere('submitted_by', $user->id)));
        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $requests = $query->when($status, fn ($q) => $q->where('status', $status))
            ->with(['subject', 'requester', 'submittedBy', 'reviewer', 'approvalRequest'])->latest()->paginate(20)->withQueryString();
        $reviewableIds = $requests->getCollection()->filter(fn ($item) => $item->approvalRequest
            && app(\App\Services\AttendanceApprovalService::class)->canReview($item->approvalRequest, $user))->pluck('id');
        $students = \App\Models\Student::active()->where('school_id', $schoolId)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('guardian_user_id', $user->id))->get();
        $teachers = \App\Models\Teacher::active()->where('school_id', $schoolId)->where('user_id', $user->id)->get();

        return view('pages.foundation.attendance-requests', compact('requests', 'counts', 'status', 'reviewableIds', 'students', 'teachers'));
    }

    public function rfidSync(): View
    {
        return view('pages.foundation.index', [
            'title' => 'RFID & GPS Sync Queue',
            'eyebrow' => 'Absensi Lanjutan',
            'description' => 'Payload manual, RFID, GPS, foto, dan queue sync berada di kolom presensi lanjutan.',
            'sections' => [
                ['title' => 'Sumber Presensi', 'items' => ['manual', 'rfid', 'gps']],
                ['title' => 'Duplicate Prevention', 'items' => ['Kombinasi siswa/GTK, tanggal, sumber, dan UID dipakai sebelum transaksi final.']],
            ],
        ]);
    }

    public function announcements(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $announcements = Announcement::where('school_id', $schoolId)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->with(['reads' => fn ($q) => $q->where('user_id', request()->user()->id)])
            ->latest('published_at')->get()
            ->filter(fn (Announcement $announcement) => $this->canReceiveAnnouncement($announcement, request()->user()))
            ->values();

        return view('pages.foundation.announcements', [
            'title' => 'Pengumuman',
            'eyebrow' => 'Komunikasi Sekolah',
            'description' => 'Sampaikan informasi ke warga sekolah dan pantau status bacanya.',
            'announcements' => $announcements,
            'unreadCount' => $announcements->filter(fn ($item) => ! $item->reads->contains(fn ($read) => $read->read_at !== null))->count(),
            'canManage' => request()->user()->hasRole(['super_admin', 'kepsek', 'pic_sekolah', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas']),
            'targetOptions' => $this->targetOptions($schoolId),
        ]);
    }

    public function showAnnouncement(Announcement $announcement, SchoolContext $schoolContext): View
    {
        abort_unless($announcement->school_id === $schoolContext->activeSchoolIdFor(), 404);
        abort_unless($this->canReceiveAnnouncement($announcement, request()->user()), 403);

        $readAt = $announcement->reads()->where('user_id', request()->user()->id)->value('read_at');
        $bookmarked = \Illuminate\Support\Facades\DB::table('announcement_bookmarks')->where('announcement_id', $announcement->id)->where('user_id', request()->user()->id)->exists();
        return view('pages.foundation.announcement-show', compact('announcement', 'readAt', 'bookmarked'));
    }

    public function markAnnouncementRead(Announcement $announcement, SchoolContext $schoolContext): RedirectResponse
    {
        abort_unless($announcement->school_id === $schoolContext->activeSchoolIdFor(), 404);
        abort_unless($this->canReceiveAnnouncement($announcement, request()->user()), 403);

        AnnouncementRead::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => request()->user()->id],
            ['read_at' => now()],
        );

        return back()->with('success', 'Pengumuman ditandai sudah dibaca.');
    }

    public function storeAnnouncement(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['super_admin', 'kepsek', 'pic_sekolah', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas']), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:40'],
            'target_type' => ['required', 'in:school,teacher,staff,student,parent,person,class,department,grade'],
            'target_id' => ['nullable', 'integer'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $schoolId = $schoolContext->activeSchoolIdFor();
        $targeted = in_array($validated['target_type'], ['person', 'class', 'department', 'grade'], true);
        if ($targeted && ! array_key_exists($validated['target_id'] ?? 0, $this->targetOptions($schoolId)[$validated['target_type']])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['target_id' => 'Pilih penerima yang terdaftar di sekolah aktif.']);
        }
        unset($validated['attachment']);
        $path = $request->file('attachment')?->store('announcement-documents/'.$schoolId, 'local');

        try {
            Announcement::create([
            ...$validated,
            'school_id' => $schoolId,
            'created_by' => $request->user()->id,
            'category' => $validated['category'] ?? 'umum',
            'target_type' => $validated['target_type'],
            'target_id' => $targeted ? $validated['target_id'] : null,
            'attachment_path' => $path,
            'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
            'published_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($path) \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Pengumuman berhasil diterbitkan.');
    }

    public function ai(SchoolContext $schoolContext): View
    {
        return view('pages.foundation.ai', [
            'title' => 'AI Assistant',
            'eyebrow' => 'Adapter Nonaktif Default',
            'description' => 'Fitur tanya AI dan simpan materi memakai adapter konfigurasi, default log/off tanpa credential.',
            'metrics' => [
                ['label' => 'Materi Tersimpan', 'value' => AiMaterial::where('school_id', $schoolContext->activeSchoolId())->count()],
            ],
            'rows' => AiMaterial::where('school_id', $schoolContext->activeSchoolIdFor())->where('user_id', request()->user()->id)->latest()->paginate(15),
            'columns' => ['title' => 'Judul', 'status' => 'Status', 'created_at' => 'Dibuat'],
            'form' => [
                'action' => route('ai.store'),
                'fields' => [
                    ['name' => 'title', 'label' => 'Judul Materi', 'placeholder' => 'Rangkuman Bab 1'],
                    ['name' => 'prompt', 'label' => 'Prompt', 'placeholder' => 'Buat ringkasan materi...'],
                ],
                'button' => 'Simpan Materi',
            ],
        ]);
    }

    public function storeAi(Request $request, SchoolContext $schoolContext, AiAssistantService $assistant): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'prompt' => ['required', 'string', 'max:10000'],
        ]);

        $result = $assistant->ask($validated['prompt']);

        AiMaterial::create([
            ...$validated,
            'school_id' => $schoolContext->activeSchoolIdFor(),
            'user_id' => $request->user()->id,
            'response' => $result['answer'],
            'status' => $result['enabled'] ? 'generated' : 'draft',
        ]);

        return back()->with('success', 'Materi AI berhasil disimpan.');
    }

    private function canReceiveAnnouncement(Announcement $announcement, \App\Models\User $user): bool
    {
        return app(\App\Services\AnnouncementAudience::class)->allows($announcement, $user);
    }

    private function targetOptions(int $schoolId): array
    {
        if (! request()->user()->hasRole(['super_admin', 'kepsek', 'pic_sekolah', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas'])) {
            return ['person' => [], 'class' => [], 'department' => [], 'grade' => []];
        }
        return [
            'person' => \App\Models\User::whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId)->where('is_active', true)->where('membership_status', 'active'))->orderBy('name')->pluck('name', 'id')->all(),
            'class' => \App\Models\SchoolClass::where('school_id', $schoolId)->orderBy('name')->pluck('name', 'id')->all(),
            'department' => \App\Models\Department::where('school_id', $schoolId)->orderBy('name')->pluck('name', 'id')->all(),
            'grade' => \App\Models\SchoolClass::where('school_id', $schoolId)->orderBy('grade')->distinct()->pluck('grade', 'grade')->all(),
        ];
    }

    public function bookmarkAnnouncement(Announcement $announcement, Request $request, SchoolContext $context): RedirectResponse
    {
        abort_unless((int) $announcement->school_id === $context->activeSchoolIdFor(), 404);
        abort_unless($this->canReceiveAnnouncement($announcement, $request->user()), 403);
        $request->validate(['saved' => ['required', 'boolean']]);
        $key = ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id];
        if ($request->boolean('saved')) {
            \Illuminate\Support\Facades\DB::table('announcement_bookmarks')->insertOrIgnore([...$key, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            \Illuminate\Support\Facades\DB::table('announcement_bookmarks')->where($key)->delete();
        }
        return back()->with('success', $request->boolean('saved') ? 'Pengumuman disimpan ke penanda.' : 'Penanda pengumuman dihapus.');
    }

    public function announcementAttachment(Announcement $announcement, Request $request, SchoolContext $context)
    {
        abort_unless((int) $announcement->school_id === $context->activeSchoolIdFor(), 404);
        abort_unless($this->canReceiveAnnouncement($announcement, $request->user()), 403);
        $path = $announcement->attachment_path;
        abort_unless($path && str_starts_with($path, 'announcement-documents/'.$announcement->school_id.'/')
            && ! str_contains($path, '..') && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($path, basename($announcement->attachment_name ?: 'lampiran.pdf'));
    }
}
