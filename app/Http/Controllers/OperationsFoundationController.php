<?php

namespace App\Http\Controllers;

use App\Models\AiMaterial;
use App\Models\Announcement;
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
        return view('pages.foundation.index', [
            'title' => 'Izin & Koreksi Presensi',
            'eyebrow' => 'Absensi Lanjutan',
            'description' => 'Permohonan izin siswa/GTK, status approval, dan dokumen pendukung.',
            'metrics' => [
                ['label' => 'Pending', 'value' => AttendanceRequest::where('school_id', $schoolContext->activeSchoolId())->where('status', 'pending')->count()],
            ],
            'rows' => ApprovalRequest::where('school_id', $schoolContext->activeSchoolId())->where('type', 'attendance')->latest()->get(['id', 'type', 'status', 'note']),
            'columns' => ['type' => 'Jenis', 'status' => 'Status', 'note' => 'Catatan'],
            'approvalActions' => true,
        ]);
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
        return view('pages.foundation.index', [
            'title' => 'Pengumuman',
            'eyebrow' => 'Komunikasi Sekolah',
            'description' => 'Target penerima, lampiran, status baca, dan log pengiriman.',
            'metrics' => [
                ['label' => 'Pengumuman', 'value' => Announcement::where('school_id', $schoolContext->activeSchoolId())->count()],
            ],
            'rows' => Announcement::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['title', 'category', 'target_type', 'published_at']),
            'columns' => ['title' => 'Judul', 'category' => 'Kategori', 'target_type' => 'Target', 'published_at' => 'Publikasi'],
            'form' => [
                'action' => route('announcements.store'),
                'fields' => [
                    ['name' => 'title', 'label' => 'Judul', 'placeholder' => 'Rapat wali murid'],
                    ['name' => 'body', 'label' => 'Isi', 'placeholder' => 'Detail pengumuman'],
                ],
                'button' => 'Terbitkan Draft',
            ],
        ]);
    }

    public function storeAnnouncement(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);

        Announcement::create([
            ...$validated,
            'school_id' => $schoolContext->activeSchoolId(),
            'created_by' => $request->user()->id,
            'target_type' => 'school',
        ]);

        return back()->with('success', 'Pengumuman berhasil disimpan sebagai draft.');
    }

    public function ai(SchoolContext $schoolContext): View
    {
        return view('pages.foundation.index', [
            'title' => 'AI Assistant',
            'eyebrow' => 'Adapter Nonaktif Default',
            'description' => 'Fitur tanya AI dan simpan materi memakai adapter konfigurasi, default log/off tanpa credential.',
            'metrics' => [
                ['label' => 'Materi Tersimpan', 'value' => AiMaterial::where('school_id', $schoolContext->activeSchoolId())->count()],
            ],
            'rows' => AiMaterial::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['title', 'status', 'created_at']),
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
            'prompt' => ['required', 'string'],
        ]);

        $result = $assistant->ask($validated['prompt']);

        AiMaterial::create([
            ...$validated,
            'school_id' => $schoolContext->activeSchoolId(),
            'user_id' => $request->user()->id,
            'response' => $result['answer'],
            'status' => $result['enabled'] ? 'generated' : 'draft',
        ]);

        return back()->with('success', 'Materi AI berhasil disimpan.');
    }
}
