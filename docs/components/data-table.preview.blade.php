@extends('layouts.app')
@section('content')
@php
    $columns = [['key' => 'name', 'label' => 'Nama'], ['key' => 'balance', 'label' => 'Saldo', 'type' => 'currency']];
    $rows = [['name' => 'Data Uji Komponen', 'balance' => 125000]];
@endphp
<div class="work work-stack">
    <h1>Preview DataTable</h1>
    <p class="work-muted">Data uji, bukan rekening sekolah. Delapan state untuk pemeriksaan komponen.</p>
    @foreach(['Default', 'Hover', 'Focus', 'Active', 'Disabled', 'Loading', 'Error', 'Success'] as $state)
        <div class="work-stack preview-{{ strtolower($state) }}">
            <h2>{{ $state }}</h2>
            @if($state === 'Success')<p role="status" class="work-notice">Data uji berhasil disimpan.</p>@endif
            <x-common.data-table :columns="$columns" :rows="$rows" :caption="'Preview '.$state" :loading="$state === 'Loading'"
                :show-actions="false" :show-avatar="false" :exportable="false" subtitle="Data uji komponen"
                :error="$state === 'Error' ? 'Data belum dapat dimuat. Muat ulang halaman untuk mencoba kembali.' : ''" />
            @if($state === 'Disabled')<p class="work-muted">Pagination nonaktif karena data hanya memiliki satu halaman.</p>@endif
        </div>
    @endforeach
</div>
<style>
    .preview-hover .data-table tbody tr { background: var(--color-work-soft); }
    .preview-focus .data-table-scroll { outline: 3px solid var(--color-work-accent); outline-offset: -3px; }
    .preview-active .data-table-sort-btn { color: var(--color-work-accent); }
</style>
@endsection
