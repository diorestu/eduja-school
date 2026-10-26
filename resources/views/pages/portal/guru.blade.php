@extends('layouts.app')
@section('content')
<div class="work work-stack"><header class="work-head"><div><h1>Portal guru & wali kelas</h1><p class="work-muted">Kelas aktif yang terhubung kepada akun Anda.</p></div><a class="work-btn" href="{{ route('attendance.requests') }}">Permohonan izin</a><a class="work-btn" href="{{ route('materials.index') }}">Materi belajar</a></header><x-work.feedback />
@forelse($classes as $class)<section class="work-panel"><h2>{{ $class->name }}</h2>
@forelse($class->students as $student)<article class="work-row"><h3>{{ $student->name }}</h3><p class="work-muted">NIS {{ $student->nis }}</p>
    @if(auth()->user()->hasRole('wali_kelas'))<p>Tunggakan: Rp {{ number_format(($arrears->get($student->id) ?? collect())->sum('remaining_amount'),0,',','.') }}</p>
    <form method="POST" action="{{ route('portal.guru.attendance') }}" class="work-actions">@csrf<input type="hidden" name="student_id" value="{{ $student->id }}"><input type="hidden" name="school_class_id" value="{{ $class->id }}"><label>Tanggal<input type="date" name="attendance_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label><label>Status<select name="status" required><option value="">Pilih status</option>@foreach(['H'=>'Hadir','S'=>'Sakit','I'=>'Izin','D'=>'Dispensasi','A'=>'Alpha'] as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label><label>Catatan<input name="note" maxlength="255"></label><button class="work-btn">Simpan</button></form>@endif
</article>@empty<p class="work-muted">Belum ada siswa aktif.</p>@endforelse
</section>@empty<div class="work-empty">Belum ada kelas aktif yang ditugaskan kepada Anda.</div>@endforelse
@include('pages.portal.history',['attendanceHistory'=>$history,'periodStart'=>$start,'periodEnd'=>$end])
</div>
@endsection
