@php
    $labels = $isGtk ? ['H'=>'Hadir','S'=>'Sakit','I'=>'Izin','C'=>'Cuti','A'=>'Alpha','DL'=>'Dinas luar'] : ['H'=>'Hadir','S'=>'Sakit','I'=>'Izin','D'=>'Dispensasi','A'=>'Alpha'];
    $people = $isGtk ? $teachers : $students;
    $routeName = $isGtk ? 'presensi.gtk' : 'presensi.siswa';
@endphp
<div class="work work-stack">
    <header class="work-head"><div><h1>{{ $isGtk ? 'Presensi guru & tendik' : 'Presensi siswa' }}</h1><p class="work-muted">{{ app(\App\Services\SchoolContext::class)->activeSchool()?->name }}</p></div>
        <a href="{{ route('attendance.requests') }}" class="work-btn">Permohonan izin</a>
    </header>
    <x-work.feedback />
    <section class="work-panel">
        <form method="GET" action="{{ route($routeName) }}" class="work-fields work-fields-pair" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">
            @unless($isGtk)
            <div class="work-field"><label for="school_class_id">Kelas / rombel</label><select id="school_class_id" name="school_class_id" required><option value="">{{ $classes->isEmpty() ? 'Belum ada kelas' : 'Pilih kelas' }}</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected($selectedClassId == $class->id)>{{ $class->name }}</option>@endforeach</select></div>
            @endunless
            <x-work.field name="attendance_date" type="date" label="Tanggal presensi" :value="$selectedDate" required />
            <div class="work-field"><label for="period">Periode riwayat</label><select id="period" name="period">@foreach(['day'=>'Hari','week'=>'Minggu','month'=>'Bulan','semester'=>'Semester kalender (Jan–Jun / Jul–Des)'] as $value=>$label)<option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="work-actions"><x-work.submit label="Tampilkan presensi" busy="Memuat…" /></div>
        </form>
        @if($isGtk)
            @foreach($groupTotals as $group => $values)
                <h2 class="mt-6">{{ $group }}</h2>
                <dl class="work-summary">@foreach($labels as $code=>$label)<div><dt>{{ $label }}</dt><dd>{{ $values[$code] ?? 0 }}</dd></div>@endforeach</dl>
            @endforeach
        @else
            <dl class="work-summary">@foreach($labels as $code=>$label)<div><dt>{{ $label }}</dt><dd>{{ $totals[$code] ?? 0 }}</dd></div>@endforeach</dl>
        @endif
        <p class="work-muted">Jumlah catatan pada {{ $periodStart->format('d M Y') }} hingga {{ $periodEnd->format('d M Y') }}{{ !$isGtk && !$selectedClassId ? ', seluruh kelas' : '' }}. Belum dicatat tidak dihitung sebagai Alpha.</p>
    </section>
    <section class="work-panel">
        <div class="work-head"><div><h2>Lembar presensi harian</h2><p class="work-muted">{{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</p></div></div>
        @if($people->isEmpty())
            <div class="work-empty"><h3>{{ !$isGtk && !$selectedClassId ? 'Pilih kelas untuk melihat daftar siswa' : 'Belum ada peserta aktif' }}</h3><p class="work-muted">{{ $isGtk ? 'Guru dan tendik aktif di sekolah ini akan tampil di sini.' : 'Periksa pilihan kelas dan data siswa aktif di sekolah ini.' }}</p></div>
        @elseif(! $canEdit)
            <p class="work-notice">Akses lihat presensi. Pencatatan dilakukan oleh petugas administrasi sekolah.</p>
            @foreach($people as $person)
                @php($existing = $existingAttendances->get($person->id))
                <article class="work-row"><h3>{{ $person->name }}</h3><p>{{ $labels[$existing?->status] ?? 'Belum dicatat' }}</p><p class="work-muted">{{ $existing?->note ?: 'Tidak ada catatan' }}</p></article>
            @endforeach
        @else
        <form action="{{ route($routeName.'.store') }}" method="POST" class="work-fields" x-data="{ saving:false }" @submit="if(saving) { $event.preventDefault(); } else { saving=true; }" @pageshow.window="saving=false">
            @csrf<input type="hidden" name="attendance_date" value="{{ $selectedDate }}">
            @unless($isGtk)<input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">@endunless
            <p class="work-muted">Pilih status setiap orang sebelum menyimpan. Isian lama tetap ditampilkan.</p>
            @foreach($people as $person)
                @php($existing = $existingAttendances->get($person->id))
                <div class="work-row work-person">
                    <div><h3>{{ $person->name }}</h3><p class="work-muted">{{ $isGtk ? ($person->role_type === 'Guru' ? 'Guru' : 'Tendik') : 'NIS '.$person->nis }}</p></div>
                    <div class="work-field"><label for="attendance-{{ $person->id }}">Status {{ $person->name }}</label><select id="attendance-{{ $person->id }}" name="attendances[{{ $person->id }}]" required><option value="">Belum dicatat</option>@foreach($labels as $code=>$label)<option value="{{ $code }}" @selected(old('attendances.'.$person->id, $existing?->status) === $code)>{{ $label }}</option>@endforeach</select></div>
                    <div class="work-field"><label for="note-{{ $person->id }}">Catatan {{ $person->name }}</label><input id="note-{{ $person->id }}" name="notes[{{ $person->id }}]" value="{{ old('notes.'.$person->id, $existing?->note) }}" maxlength="255" placeholder="Opsional"></div>
                </div>
            @endforeach
            <div><x-work.submit label="Simpan Lembar Presensi" /></div>
        </form>
        @endif
    </section>
    <section class="work-panel">
        <h2>Riwayat presensi</h2>
        <p class="work-muted mt-2">Gunakan periode di atas untuk melihat catatan terdahulu.</p>
        <div class="mt-6">
        @forelse($history as $record)
            <details class="work-row">
                <summary>{{ $isGtk ? ($record->teacher?->name ?? 'GTK tidak tersedia') : ($record->student?->name ?? 'Siswa tidak tersedia') }} · {{ $record->attendance_date->format('d M Y') }} · {{ $labels[$record->status] ?? $record->status }}</summary>
                <dl class="work-fields work-fields-pair"><div><dt class="work-muted">Catatan</dt><dd>{{ $record->note ?: 'Tidak ada catatan' }}</dd></div><div><dt class="work-muted">Sumber</dt><dd>{{ $record->source ?: 'Tidak tercatat' }}</dd></div>@if($isGtk)<div><dt class="work-muted">Kelompok</dt><dd>{{ $record->teacher?->role_type === 'Guru' ? 'Guru' : 'Tendik' }}</dd></div>@else<div><dt class="work-muted">Kelas</dt><dd>{{ $record->schoolClass?->name ?: 'Tidak tersedia' }}</dd></div>@endif</dl>
            </details>
        @empty
            <div class="work-empty"><h3>Belum ada catatan pada periode ini</h3><p class="work-muted">Pilih periode lain atau isi lembar presensi harian.</p></div>
        @endforelse
        </div>
        {{ $history->links() }}
    </section>
</div>
