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

    $dtColumns = [];
    foreach ($columns as $k => $v) {
        if (is_array($v)) {
            $dtColumns[] = $v;
        } else {
            $dtColumns[] = [
                'key' => (string) $k,
                'label' => (string) $v,
                'bold' => empty($dtColumns),
            ];
        }
    }

    $dtRows = collect($rows)->map(function ($row) use ($dtColumns, $renderCell) {
        $rowArr = [];
        foreach ($dtColumns as $col) {
            $key = $col['key'];
            $val = data_get($row, $key);
            $rowArr[$key] = $renderCell($val);
        }
        if (is_object($row) && isset($row->id)) {
            $rowArr['id'] = $row->id;
        } elseif (is_array($row) && isset($row['id'])) {
            $rowArr['id'] = $row['id'];
        }
        return $rowArr;
    });
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" :label="$eyebrow ?? 'EDUJA'" />

    @if($foundationSchools ?? false)
        <section class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-500">Cakupan yayasan</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Dashboard agregat seluruh sekolah yang terafiliasi.</p>
            </div>
            <label class="sr-only" for="foundation-school-select">Buka detail sekolah</label>
            <select id="foundation-school-select" onchange="if(this.value) window.location.href=this.value" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 sm:w-72">
                <option value="{{ route('yayasan.dashboard') }}">Semua sekolah · Agregat yayasan</option>
                @foreach($foundationSchools as $foundationSchool)
                    <option value="{{ route('yayasan.school', $foundationSchool) }}" @selected(($selectedSchool?->id ?? null) === $foundationSchool->id)>{{ $foundationSchool->name }} · Detail sekolah</option>
                @endforeach
            </select>
        </section>
    @endif

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    @if(!empty($description) || session('active_school_id'))
        <section class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-xs dark:border-gray-800 dark:bg-gray-900 md:flex-row md:items-center md:justify-between">
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
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 {{ $form ? 'xl:grid-cols-[minmax(0,1fr)_340px]' : '' }}">
        <div class="space-y-4">
            @if(count($columns))
                <x-common.data-table
                    :rows="$dtRows"
                    :columns="$dtColumns"
                    :caption="$title"
                    search-label="Cari data..."
                    row-label="data"
                    :subtitle="count($dtRows) . ' item terdaftar pada modul ini'"
                    :show-actions="false"
                    :show-avatar="false"
                    :exportable="true"
                    export-label="Export Data"
                    empty-message="Belum ada data pada modul ini."
                />
            @endif

            @foreach($sections as $section)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $section['title'] }}</h2>
                    <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                        @foreach($section['items'] as $item)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-5 text-gray-700 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300">
                                {{ $item }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if($form)
            <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900 h-fit">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Input Cepat</h2>
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
                                <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
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
