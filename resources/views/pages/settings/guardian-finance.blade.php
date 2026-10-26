@extends('layouts.app')
@section('content')
<div class="work work-stack">
    <header class="work-head"><div><h1>Izin keuangan orang tua</h1><p class="work-muted">{{ $school->name }}</p></div></header>
    <x-work.feedback />
    <section class="work-panel max-w-2xl">
        <h2>Bagikan ringkasan keuangan</h2>
        <p class="work-muted mt-2">Kepala sekolah dapat memberi dan mencabut izin orang tua yang memiliki anak aktif di sekolah ini. Akses hanya untuk melihat ringkasan saldo dan pengeluaran sekolah.</p>
        <form class="work-fields mt-6" method="POST" action="{{ route('settings.guardian-finance.update') }}" x-data="{ saving:false }" @submit="saving=true">@csrf @method('PUT')
            <input type="hidden" name="guardian_finance_allowed" value="0">
            <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="guardian_finance_allowed" value="1" @checked(old('guardian_finance_allowed', $school->guardian_finance_allowed))>Izinkan orang tua melihat ringkasan keuangan sekolah</label>
            <div><x-work.submit label="Simpan izin" /></div>
        </form>
    </section>
</div>
@endsection
