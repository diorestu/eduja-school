@props([
    'rows' => [],
    'columns' => [],
    'caption' => null,
    'title' => null,
    'subtitle' => null,
    'searchLabel' => 'Cari data...',
    'searchable' => true,
    'emptyMessage' => 'Belum ada data.',
    'emptyHint' => '',
    'rowLabel' => 'karyawan',
    'exportable' => true,
    'exportLabel' => 'Export Data',
    'showActions' => true,
    'showAvatar' => true,
])
@php
    $tableTitle = $title ?? $caption ?? 'Daftar Karyawan';
    $tableSubtitle = $subtitle ?? ($rowLabel ? "Filter aktif: Semua {$rowLabel}" : 'Filter aktif: Semua data');
    $hasActionColumn = collect($columns)->contains(fn ($c) => in_array($c['key'] ?? '', ['actions', 'action', 'aksi']));
    $displayActions = $showActions || $hasActionColumn;
    $totalColCount = count($columns) + ($displayActions && !$hasActionColumn ? 1 : 0);
@endphp

<section {{ $attributes->class(['data-table']) }} x-data="dataTable(@js(collect($rows)->values()), @js($columns))" aria-label="{{ $tableTitle }}" :aria-busy="loading">
    <div class="data-table-top" x-data="{ searchOpen: false }">
        <div>
            <h2 class="data-table-title">{{ $tableTitle }}</h2>
            <p class="data-table-subtitle">{{ $tableSubtitle }}</p>
        </div>
        <div class="data-table-controls">
            @if($searchable)
                <div class="data-table-search-box">
                    <template x-if="searchOpen || query">
                        <div class="relative flex items-center">
                            <span class="data-table-search-icon" aria-hidden="true">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="search" :value="query" @input.debounce.200ms="search($event.target.value)" @keydown.escape="searchOpen = false; if(!query) search('')" placeholder="{{ $searchLabel }}" class="data-table-search-input" autocomplete="off" :disabled="loading" aria-label="{{ $searchLabel }}" autofocus />
                        </div>
                    </template>
                    <template x-if="!searchOpen && !query">
                        <button type="button" @click="searchOpen = true" class="data-table-nav-btn" title="{{ $searchLabel }}" aria-label="{{ $searchLabel }}">
                            <svg class="w-4 h-4 text-gray-500 hover:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                    </template>
                </div>
            @endif

            <span class="data-table-pagination-text" x-text="`${startRow}–${endRow} of ${filteredRows.length}`"></span>

            <div class="data-table-nav-group">
                <button type="button" @click="goTo(currentPage - 1)" :disabled="loading || currentPage === 1" class="data-table-nav-btn" aria-label="Halaman sebelumnya">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" @click="goTo(currentPage + 1)" :disabled="loading || currentPage === pageCount" class="data-table-nav-btn" aria-label="Halaman berikutnya">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            @if($exportable)
                <button type="button" @click="exportData('{{ \Illuminate\Support\Str::slug($tableTitle) }}.csv')" :disabled="loading || filteredRows.length === 0" class="data-table-export-btn" aria-label="{{ $exportLabel }}">
                    <svg class="w-4 h-4 shrink-0 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>{{ $exportLabel }}</span>
                </button>
            @endif
        </div>
    </div>

    <p x-cloak x-show="loading" role="status" class="px-8 py-2 text-xs text-gray-500">Memuat {{ $rowLabel }}…</p>
    <p x-cloak x-show="error" role="alert" class="px-8 py-2 text-xs text-red-600" x-text="error"></p>

    <div class="data-table-scroll" tabindex="0" aria-label="{{ $tableTitle }}. Geser untuk melihat kolom lainnya.">
        <table>
            <caption class="sr-only">{{ $tableTitle }}</caption>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        @php
                            $isActionCol = in_array($column['key'] ?? '', ['actions', 'action', 'aksi']);
                            $sortable = ($column['sortable'] ?? true) && !$isActionCol;
                        @endphp
                        <th scope="col" :aria-sort="sortKey === @js($column['key']) ? (sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'">
                            @if($sortable)
                                <button type="button" @click="sort(@js($column['key']))" :disabled="loading" class="data-table-sort-btn" aria-label="Urutkan {{ $column['label'] }}">
                                    <span>{{ $column['label'] }}</span>
                                    <span class="data-table-sort-active" x-show="sortKey === @js($column['key'])" x-text="sortDirection === 'asc' ? '↑' : '↓'"></span>
                                    <span class="data-table-sort-arrows" x-show="sortKey !== @js($column['key'])">↑↓</span>
                                </button>
                            @else
                                <span>{{ $column['label'] }}</span>
                            @endif
                        </th>
                    @endforeach
                    @if($displayActions && !$hasActionColumn)
                        <th scope="col">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                <template x-for="row in pageRows" :key="row.__tableKey">
                    <tr>
                        @foreach($columns as $column)
                            @php
                                $isActionCol = in_array($column['key'] ?? '', ['actions', 'action', 'aksi']);
                                $useAvatar = $showAvatar && (($column['avatar'] ?? false) || ($loop->first && ($column['avatar'] ?? true) && !in_array($column['type'] ?? '', ['number', 'currency', 'boolean', 'date'])));
                                $subKey = $column['subKey'] ?? null;
                            @endphp
                            @if($isActionCol)
                                <td>
                                    <div class="data-table-actions">
                                        <template x-if="row.only_edit || @js(!empty($column['onlyEdit']))">
                                            <button type="button" @click="$dispatch('table-edit', row)" class="data-table-action-icon text-gray-500 hover:text-primary-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-primary-400 dark:hover:bg-gray-800 transition-colors" title="Ubah data" aria-label="Ubah data">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        </template>
                                        <template x-if="!row.only_edit && !@js(!empty($column['onlyEdit']))">
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="$dispatch('table-view', row)" class="data-table-action-icon" title="Lihat detail" aria-label="Lihat detail">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </button>
                                                <button type="button" @click="$dispatch('table-edit', row)" class="data-table-action-icon" title="Ubah data" aria-label="Ubah data">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                <button type="button" @click="$dispatch('table-more', row)" class="data-table-action-icon" title="Menu lainnya" aria-label="Menu lainnya">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            @elseif($useAvatar)
                                <td>
                                    <div class="data-table-cell-name">
                                        <div class="data-table-avatar" x-text="getInitials(row[@js($column['key'])])"></div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="data-table-name-title truncate" x-text="format(row[@js($column['key'])], @js($column))"></span>
                                                <template x-if="row[@js($column['badgeKey'] ?? 'badge')] || row.work_mode">
                                                    <span class="data-table-badge" x-text="row[@js($column['badgeKey'] ?? 'badge')] || row.work_mode"></span>
                                                </template>
                                            </div>
                                            <template x-if="row[@js($column['subKey'] ?? '')] || row.sub_title || row.subtitle || row.role || row.jabatan">
                                                <p class="data-table-subtext truncate" x-text="row[@js($column['subKey'] ?? '')] || row.sub_title || row.subtitle || row.role || row.jabatan"></p>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                            @elseif($subKey)
                                <td>
                                    <div>
                                        <span :class="{'font-semibold text-gray-900 dark:text-white': @js(!empty($column['bold']) || in_array($column['key'], ['bank_name', 'rek'])), 'text-gray-700 dark:text-gray-300': !@js(!empty($column['bold']) || in_array($column['key'], ['bank_name', 'rek']))}" class="text-[13px] block leading-tight truncate" x-text="format(row[@js($column['key'])], @js($column))"></span>
                                        <span class="data-table-subtext block truncate mt-1" x-text="row[@js($subKey)]"></span>
                                    </div>
                                </td>
                            @elseif(($column['type'] ?? '') === 'badge')
                                <td>
                                    <template x-if="format(row[@js($column['key'])], @js($column)) === '-'">
                                        <span class="data-table-empty-dash">-</span>
                                    </template>
                                    <template x-if="format(row[@js($column['key'])], @js($column)) !== '-'">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                            :class="{
                                                'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60': ['aktif', 'active', 'approved', 'disetujui', 'sukses', 'success', 'lunas'].includes(String(row[@js($column['key'])]).toLowerCase()),
                                                'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60': ['pending', 'menunggu', 'komite', 'cicilan'].includes(String(row[@js($column['key'])]).toLowerCase()),
                                                'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/60': String(row[@js($column['key'])]).toLowerCase() === 'bos',
                                                'bg-indigo-50 text-indigo-800 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800/60': String(row[@js($column['key'])]).toLowerCase() === 'yayasan',
                                                'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60': ['hibah', 'rejected', 'ditolak', 'batal', 'belum lunas'].includes(String(row[@js($column['key'])]).toLowerCase()),
                                                'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700': !['aktif', 'active', 'approved', 'disetujui', 'sukses', 'success', 'lunas', 'pending', 'menunggu', 'komite', 'cicilan', 'bos', 'yayasan', 'hibah', 'rejected', 'ditolak', 'batal', 'belum lunas'].includes(String(row[@js($column['key'])]).toLowerCase())
                                            }"
                                            x-text="format(row[@js($column['key'])], @js($column))">
                                        </span>
                                    </template>
                                </td>
                            @else
                                <td>
                                    <template x-if="format(row[@js($column['key'])], @js($column)) === '-'">
                                        <span class="data-table-empty-dash">-</span>
                                    </template>
                                    <template x-if="format(row[@js($column['key'])], @js($column)) !== '-'">
                                        <span :class="{'font-semibold text-gray-900 dark:text-white': @js(!empty($column['bold'])), 'text-gray-700 dark:text-gray-300': !@js(!empty($column['bold']))}" class="text-[13px]" x-text="format(row[@js($column['key'])], @js($column))"></span>
                                    </template>
                                </td>
                            @endif
                        @endforeach

                        @if($displayActions && !$hasActionColumn)
                            <td>
                                <div class="data-table-actions">
                                    <button type="button" @click="$dispatch('table-view', row)" class="data-table-action-icon" title="Lihat detail" aria-label="Lihat detail">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button type="button" @click="$dispatch('table-edit', row)" class="data-table-action-icon" title="Ubah data" aria-label="Ubah data">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button type="button" @click="$dispatch('table-more', row)" class="data-table-action-icon" title="Menu lainnya" aria-label="Menu lainnya">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                                    </button>
                                </div>
                            </td>
                        @endif
                    </tr>
                </template>
                <tr x-show="!loading && !error && filteredRows.length === 0">
                    <td colspan="{{ $totalColCount }}" class="data-table-empty">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="rows.length ? 'Tidak ada hasil untuk pencarian ini.' : @js($emptyMessage)"></p>
                        <p class="text-xs text-gray-400 mt-1" x-text="rows.length ? 'Ubah kata pencarian untuk melihat data lainnya.' : @js($emptyHint)"></p>
                        <button x-cloak x-show="query" type="button" @click="search('')" class="mt-3 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition">Hapus pencarian</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
