@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
@if(session('success'))<p role="status" class="mb-4 rounded border border-green-300 p-3">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-4 rounded border border-red-300 p-3">{{ $errors->first() }}</div>@endif
<div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <h1 class="mb-4 text-xl font-semibold">{{ $title }}</h1>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <label class="grid gap-1 text-sm">Tahun ajaran
            <select name="academic_year_id" class="rounded border p-2 dark:bg-gray-900" required>
                @foreach($years as $year)<option value="{{ $year->id }}" @selected($yearId == $year->id)>{{ $year->year }} · {{ $year->semester }}</option>@endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">{{ $workflow === 'graduation' ? 'Kelas akhir' : 'Kelas asal' }}
            <select name="source_class_id" class="rounded border p-2 dark:bg-gray-900">
                <option value="">Pilih kelas</option>
                @foreach($classes as $class)<option value="{{ $class->id }}" @selected($sourceId == $class->id)>{{ $class->name }}</option>@endforeach
            </select>
        </label>
        <button class="rounded bg-brand-500 px-4 py-2 text-white">Tampilkan siswa</button>
    </form>
    <p class="mb-4 text-sm text-gray-500">{{ $workflow === 'graduation' ? 'Tagihan yang belum lunas tetap tercatat. Bendahara dan orang tua menerima pemberitahuan; kelulusan tetap diproses.' : 'Pilih siswa yang akan dipindahkan ke kelas tujuan. Riwayat kelas tetap tersimpan.' }}</p>
    <form action="{{ route('academic.'.$workflow.'.store') }}" method="POST" x-data="{all: false}">
        @csrf
        <input type="hidden" name="academic_year_id" value="{{ $yearId }}">
        <input type="hidden" name="source_class_id" value="{{ $sourceId }}">
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b">
            <th class="p-3"><input type="checkbox" aria-label="Pilih semua siswa" @change="all = $event.target.checked; $el.closest('table').querySelectorAll('[name=\'student_ids[]\']').forEach(box => box.checked = all)"></th><th>NIS</th><th>Nama siswa</th>
        </tr></thead><tbody>
        @forelse($students as $student)<tr class="border-b"><td class="p-3"><input type="checkbox" name="student_ids[]" value="{{ $student->id }}" aria-label="Pilih {{ $student->name }}"></td><td>{{ $student->nis }}</td><td>{{ $student->name }}</td></tr>
        @empty<tr><td colspan="3" class="p-4 text-gray-500">Pilih tahun ajaran dan kelas untuk menampilkan siswa aktif.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-5 flex flex-wrap items-end gap-3">
        @if($workflow === 'graduation')
            <label class="grid gap-1 text-sm">Tanggal lulus<input type="date" name="graduation_date" required class="rounded border p-2 dark:bg-gray-900"></label>
            <label class="grid gap-1 text-sm">Tahun lulus<input type="number" name="graduation_year" value="{{ now()->year }}" min="1900" max="2100" required class="rounded border p-2 dark:bg-gray-900"></label>
        @else
            <label class="grid gap-1 text-sm">Kelas tujuan<select name="target_class_id" required class="rounded border p-2 dark:bg-gray-900"><option value="">Pilih kelas</option>@foreach($targetClasses as $target)<option value="{{ $target->id }}">{{ $target->name }} · {{ $target->academicYear?->year }} {{ $target->academicYear?->semester }}</option>@endforeach</select></label>
        @endif
            <button @disabled($students->isEmpty()) class="rounded bg-brand-500 px-4 py-2 text-white disabled:opacity-50">{{ $workflow === 'graduation' ? 'Proses kelulusan' : 'Proses kenaikan' }}</button>
        </div>
    </form>
</div>
@endsection
