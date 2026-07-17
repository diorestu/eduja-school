@extends('layouts.app')

@php
    $metrics = $metrics ?? [];
    $rows = $rows ?? collect();
    $columns = $columns ?? [];
    $sections = $sections ?? [];
    $form = $form ?? null;
    $approvalActions = $approvalActions ?? false;

    $renderCell = function ($value) {
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('d M Y');
        }

        if (is_numeric($value) && (float) $value >= 1000) {
            return number_format((float) $value, 0, ',', '.');
        }

        return filled($value) ? $value : '-';
    };
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" :label="$eyebrow ?? 'EDUJA'" />

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-3 py-2 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
            {{ session('error') }}
        </div>
    @endif

    @if(!empty($description) || session('active_school_id'))
        <section class="mb-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03] md:flex-row md:items-center md:justify-between">
            @if(!empty($description))
                <p class="max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $description }}</p>
            @endif
            @if(session('active_school_id'))
                <a href="{{ route('school.select') }}"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-600 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-gray-300">
                    Ganti Sekolah
                </a>
            @endif
        </section>
    @endif

    @if(count($metrics))
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach($metrics as $metric)
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold leading-7 text-gray-900 dark:text-white/90">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 {{ $form ? 'xl:grid-cols-[minmax(0,1fr)_340px]' : '' }}">
        <div class="space-y-4">
            @if(count($columns))
                <div x-data="{ q: '' }" class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white/90">Data Utama</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $rows->count() }} item terdaftar</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <label class="sr-only" for="foundation-table-search">Filter data</label>
                            <div class="relative">
                                <input id="foundation-table-search" x-model="q" type="search" placeholder="Filter data..."
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 pl-9 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:w-64">
                                <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                            </div>
                            <span class="hidden rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400 sm:inline-flex">
                                Datatable
                            </span>
                        </div>
                    </div>
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[560px]">
                            <thead class="bg-gray-50 dark:bg-gray-800/40">
                                <tr>
                                    @foreach($columns as $label)
                                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</th>
                                    @endforeach
                                    @if($approvalActions)
                                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($rows as $row)
                                    <tr x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="hover:bg-gray-50/70 dark:hover:bg-gray-800/20">
                                        @foreach($columns as $key => $label)
                                            <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $renderCell(data_get($row, $key)) }}
                                            </td>
                                        @endforeach
                                        @if($approvalActions)
                                            <td class="px-4 py-2.5">
                                                <div class="flex justify-end gap-2">
                                                    @if(data_get($row, 'status') === 'pending')
                                                        <form action="{{ route('approvals.approve', data_get($row, 'id')) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg bg-success-500 px-3 text-xs font-semibold text-white hover:bg-success-600">
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('approvals.reject', data_get($row, 'id')) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg bg-error-500 px-3 text-xs font-semibold text-white hover:bg-error-600">
                                                                Reject
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Selesai</span>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($columns) + ($approvalActions ? 1 : 0) }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Belum ada data pada modul ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @foreach($sections as $section)
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white/90">{{ $section['title'] }}</h2>
                    <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                        @foreach($section['items'] as $item)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-5 text-gray-700 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300">
                                {{ $item }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if($form)
            <aside class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white/90">Input Cepat</h2>
                <form action="{{ $form['action'] }}" method="POST" class="mt-4 space-y-3">
                    @csrf
                    @foreach($form['fields'] as $field)
                        <div>
                            @php
                                $fieldName = str_replace('[]', '', $field['name']);
                                $fieldType = $field['type'] ?? 'text';
                            @endphp
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-400" for="{{ $fieldName }}">
                                {{ $field['label'] }}
                            </label>
                            @if($fieldType === 'select')
                                <select id="{{ $fieldName }}" name="{{ $field['name'] }}" @if(!empty($field['multiple'])) multiple size="6" @endif
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 min-h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                    required>
                                    @foreach(($field['options'] ?? []) as $value => $label)
                                        <option value="{{ $value }}" @selected(collect(old($fieldName, []))->contains((string) $value))>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input id="{{ $fieldName }}" name="{{ $field['name'] }}" type="{{ $fieldType }}" value="{{ old($fieldName) }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                    placeholder="{{ $field['placeholder'] ?? '' }}" required>
                            @endif
                            @error($fieldName)
                                <p class="mt-1 text-xs font-medium text-error-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                    <button type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 active:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ $form['button'] ?? 'Simpan' }}
                    </button>
                </form>
            </aside>
        @endif
    </div>
@endsection
