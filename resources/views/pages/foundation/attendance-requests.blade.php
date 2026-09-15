@extends('layouts.app')
@section('content')
@php($statusLabels = ['pending'=>'Menunggu persetujuan','approved'=>'Disetujui','rejected'=>'Ditolak'])
<div class="work work-stack">
    <header class="work-head"><div><h1>Permohonan izin</h1><p class="work-muted">{{ app(\App\Services\SchoolContext::class)->activeSchool()?->name }}</p></div></header>
    <x-work.feedback />
    <section class="work-panel">
        <dl class="work-summary">@foreach($statusLabels as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $counts[$key] ?? 0 }}</dd></div>@endforeach</dl>
        <form method="GET" action="{{ route('attendance.requests') }}" class="work-actions" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">
            <div class="work-field"><label for="status">Status permohonan</label><select id="status" name="status"><option value="">Semua status</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div>
            <x-work.submit label="Tampilkan" busy="Memuat…" />
        </form>
    </section>
    <div @class(['work-split' => $students->isNotEmpty() || $teachers->isNotEmpty()])>
        <section class="work-panel">
            <h2>Daftar permohonan</h2><p class="work-muted mt-2">Buka detail untuk membaca alasan dan keputusan permohonan.</p>
            <div class="mt-6">
            @forelse($requests as $item)
                <details class="work-row">
                    <summary>{{ $item->subject?->name ?? 'Nama tidak tersedia' }} · {{ ucfirst($item->request_type) }}</summary>
                    <div class="work-actions"><span class="work-status">{{ $statusLabels[$item->status] ?? $item->status }}</span><span class="work-muted">{{ $item->start_date->format('d M Y') }} hingga {{ ($item->end_date ?? $item->start_date)->format('d M Y') }}</span></div>
                    <p class="work-muted">Diajukan oleh {{ $item->requester?->name ?? $item->submittedBy?->name ?? 'Tidak tercatat' }}</p>
                    <h3>Alasan permohonan</h3><p class="work-copy">{{ $item->reason ?: 'Tidak ada alasan tercatat.' }}</p>
                    <p class="work-muted">Dokumen pendukung: {{ $item->document_name ?: ($item->document_path ? 'Dokumen tercatat' : 'Tidak dilampirkan') }}</p>
                    @if($item->document_path && str_starts_with($item->document_path, 'attendance-documents/'.$item->school_id.'/'))
                        <a class="work-link" href="{{ route('attendance.requests.document', $item) }}">Unduh dokumen</a>
                    @endif
                    @if($item->reviewed_at)
                        <p class="work-muted">Diproses {{ $item->reviewed_at->format('d M Y, H:i') }} oleh {{ $item->reviewer?->name ?? 'reviewer sekolah' }}.</p>
                        <p class="work-copy">{{ $item->review_note ?: 'Tidak ada catatan keputusan.' }}</p>
                    @endif
                    @if($reviewableIds->contains($item->id))
                        <form action="{{ route('approvals.approve', $item->approvalRequest) }}" method="POST" class="work-fields" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">
                            @csrf
                            <div class="work-field"><label for="review-note-{{ $item->id }}">Catatan keputusan (opsional)</label><textarea id="review-note-{{ $item->id }}" name="note" maxlength="1000"></textarea></div>
                            <div class="work-actions">
                                <x-work.submit label="Setujui" busy="Memproses…" />
                                <button class="work-btn" type="submit" formaction="{{ route('approvals.reject', $item->approvalRequest) }}" :disabled="saving">Tolak</button>
                            </div>
                        </form>
                    @elseif($item->status === 'pending')
                        <p class="work-notice">Menunggu keputusan petugas yang berwenang.</p>
                    @endif
                </details>
            @empty
                <div class="work-empty"><h3>Belum ada permohonan{{ $status ? ' dengan status ini' : '' }}</h3><p class="work-muted">Permohonan yang dapat Anda akses di sekolah ini akan tampil di sini.</p></div>
            @endforelse
            </div>
            {{ $requests->links() }}
        </section>
        @if($students->isNotEmpty() || $teachers->isNotEmpty())
        <aside class="work-panel">
            <h2>Ajukan izin</h2><p class="work-muted mt-2">Permohonan diteruskan kepada petugas sekolah untuk ditinjau.</p>
            @foreach(['student'=>$students, 'teacher'=>$teachers] as $kind=>$subjects)
                @if($subjects->isNotEmpty())
                <details class="mt-4" @if($students->isEmpty() || $teachers->isEmpty()) open @endif><summary>{{ $kind === 'student' ? 'Izin siswa / anak' : 'Sakit atau cuti saya' }}</summary>
                    <form action="{{ route('attendance.requests.'.$kind.'.store') }}" method="POST" enctype="multipart/form-data" class="work-fields" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">
                        @csrf
                        <div class="work-field"><label for="subject-{{ $kind }}">{{ $kind === 'student' ? 'Siswa' : 'Guru / tendik' }}</label><select id="subject-{{ $kind }}" name="subject_id" required>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>@endforeach</select></div>
                        <div class="work-field"><label for="type-{{ $kind }}">Jenis permohonan</label><select id="type-{{ $kind }}" name="request_type" required>@foreach(($kind === 'student' ? ['sakit','izin','dispensasi'] : ['sakit','cuti']) as $type)<option value="{{ $type }}" @selected(old('request_type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
                        <div class="work-field"><label for="start-{{ $kind }}">Tanggal mulai (wajib)</label><input id="start-{{ $kind }}" type="date" name="start_date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}" required></div>
                        <div class="work-field"><label for="end-{{ $kind }}">Tanggal selesai (wajib)</label><input id="end-{{ $kind }}" type="date" name="end_date" min="{{ today()->toDateString() }}" value="{{ old('end_date') }}" required></div>
                        <div class="work-field"><label for="reason-{{ $kind }}">Alasan (wajib)</label><textarea id="reason-{{ $kind }}" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea></div>
                        <div class="work-field"><label for="document-{{ $kind }}">Dokumen pendukung (opsional)</label><input id="document-{{ $kind }}" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="document-help-{{ $kind }}"><p id="document-help-{{ $kind }}" class="work-muted">PDF, JPG, atau PNG, maksimal 5 MB. Pilih ulang berkas jika formulir perlu diperbaiki.</p></div>
                        <x-work.submit label="Kirim permohonan" busy="Mengirim…" />
                    </form>
                </details>
                @endif
            @endforeach
        </aside>
        @endif
    </div>
</div>
@endsection
