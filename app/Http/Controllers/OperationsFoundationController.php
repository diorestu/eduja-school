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
        ]);
    }

    public function showAnnouncement(Announcement $announcement, SchoolContext $schoolContext): View
    {
        abort_unless($announcement->school_id === $schoolContext->activeSchoolIdFor(), 404);
        abort_unless($this->canReceiveAnnouncement($announcement, request()->user()), 403);

        $readAt = $announcement->reads()->where('user_id', request()->user()->id)->value('read_at');
        return view('pages.foundation.announcement-show', compact('announcement', 'readAt'));
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
            'target_type' => ['required', 'in:school,teacher,staff,student,parent'],
            'target_id' => ['prohibited'],
        ]);

        Announcement::create([
            ...$validated,
            'school_id' => $schoolContext->activeSchoolIdFor(),
            'created_by' => $request->user()->id,
            'category' => $validated['category'] ?? 'umum',
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'] ?? null,
            'published_at' => now(),
        ]);

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
        if (! $announcement->published_at || $announcement->published_at->isFuture()) {
            return false;
        }
        if ($announcement->target_type === 'school' || $user->isSuperAdmin()) {
            return true;
        }

        $roles = [
            'teacher' => ['guru', 'wali_kelas'],
            'staff' => ['tendik', 'tu', 'staf_tu', 'bendahara'],
            'student' => ['siswa'],
            'parent' => ['orang_tua', 'wali_murid'],
        ];

        return $user->hasRole($roles[$announcement->target_type] ?? []);
    }
}
