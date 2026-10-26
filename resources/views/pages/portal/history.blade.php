<section class="portal-card mt-4">
    <h2>Riwayat presensi</h2><form method="GET" class="mt-3">@if(isset($student))<input type="hidden" name="student_id" value="{{ $student->id }}">@endif
        <label>Tanggal acuan<input type="date" name="attendance_date" value="{{ request('attendance_date',today()->toDateString()) }}"></label>
        <label>Periode<select name="period">@foreach(['day'=>'Hari','week'=>'Minggu','month'=>'Bulan','semester'=>'Semester kalender'] as $value=>$label)<option value="{{ $value }}" @selected(request('period','month') === $value)>{{ $label }}</option>@endforeach</select></label><button class="portal-action">Tampilkan</button>
    </form>
    <p class="portal-muted mt-2">{{ $periodStart }} hingga {{ $periodEnd }}. Belum dicatat tidak dihitung sebagai Alpha.</p>
    @forelse($attendanceHistory as $entry)<details class="mt-3"><summary>{{ $entry->attendance_date->format('d M Y') }} · {{ ['H'=>'Hadir','S'=>'Sakit','I'=>'Izin','D'=>'Dispensasi','C'=>'Cuti','A'=>'Alpha','DL'=>'Dinas luar'][$entry->status] ?? $entry->status }}</summary><p>Masuk: {{ $entry->clock_in_at ?: 'Belum tercatat' }} · Pulang: {{ $entry->clock_out_at ?: 'Belum tercatat' }}</p><p>Sumber: {{ $entry->source ?: 'Manual' }} · {{ $entry->note }}</p></details>@empty<p class="portal-muted mt-3">Belum ada presensi pada periode ini.</p>@endforelse
    {{ $attendanceHistory->links() }}
</section>
