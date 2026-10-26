@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
@if(session('success'))<p role="status" class="mb-4 rounded border p-3">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="mb-4 rounded border border-red-300 p-3">{{ $errors->first() }}</p>@endif
<div class="space-y-5 rounded-xl border bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <h1 class="text-xl font-semibold">Jurusan & Program Keahlian</h1>
    <form method="POST" action="{{ route('academic.departments.store') }}" class="flex flex-wrap items-end gap-3">@csrf
        <label class="grid gap-1 text-sm">Kode<input name="code" required maxlength="20" class="rounded border p-2 dark:bg-gray-900"></label>
        <label class="grid gap-1 text-sm">Nama jurusan<input name="name" required maxlength="120" class="rounded border p-2 dark:bg-gray-900"></label>
        <button class="rounded bg-brand-500 px-4 py-2 text-white">Tambah jurusan</button>
    </form>
    @forelse($departments as $department)
    <div class="flex flex-wrap items-end gap-3 border-t pt-4">
        <form method="POST" action="{{ route('academic.departments.store') }}" class="flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="action" value="update"><input type="hidden" name="department_id" value="{{ $department->id }}">
            <label class="grid gap-1 text-sm">Kode<input name="code" value="{{ $department->code }}" required class="rounded border p-2 dark:bg-gray-900"></label>
            <label class="grid gap-1 text-sm">Nama jurusan<input name="name" value="{{ $department->name }}" required class="rounded border p-2 dark:bg-gray-900"></label>
            <button class="rounded border px-3 py-2">Simpan</button>
        </form>
        <form method="POST" action="{{ route('academic.departments.store') }}">@csrf<input type="hidden" name="action" value="{{ $department->is_active ? 'deactivate' : 'activate' }}"><input type="hidden" name="department_id" value="{{ $department->id }}"><button class="rounded border px-3 py-2">{{ $department->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
    </div>
    @empty<p class="text-sm text-gray-500">Belum ada jurusan.</p>@endforelse
    <p class="text-sm text-gray-500">Jurusan nonaktif tetap tersimpan dalam riwayat kelas dan laporan.</p>
</div>
@endsection
