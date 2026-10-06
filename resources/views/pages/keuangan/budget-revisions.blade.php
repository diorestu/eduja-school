@extends('layouts.app')

@section('content')
@php
    $groupedPrograms = $budgetPlans->groupBy('program_name')->map(function ($items, $programName) {
        return [
            'name' => $programName,
            'total_amount' => $items->sum('amount'),
            'activities' => $items->map(function ($plan) {
                $latestRev = $plan->revisions->first();
                return [
                    'id' => $plan->id,
                    'program_name' => $plan->program_name,
                    'activity_name' => $plan->activity_name,
                    'source_funding' => $plan->source_funding,
                    'amount' => (float) $plan->amount,
                    'status' => $latestRev ? $latestRev->status : ($plan->status ?? 'active'),
                    'latest_revision' => $latestRev ? [
                        'id' => $latestRev->id,
                        'old_amount' => (float) $latestRev->old_amount,
                        'new_amount' => (float) $latestRev->new_amount,
                        'reason' => $latestRev->reason,
                        'status' => $latestRev->status,
                        'created_at' => $latestRev->created_at?->format('d M Y, H:i'),
                        'reviewed_at' => $latestRev->reviewed_at?->format('d M Y, H:i'),
                        'requester_name' => $latestRev->requestedBy?->name ?? 'Bendahara',
                        'reviewer_name' => $latestRev->reviewedBy?->name ?? 'Kepala Sekolah',
                    ] : null,
                    'revision_url' => route('finance.budgets.revisions.store', $plan),
                ];
            })->values(),
        ];
    })->values();
@endphp

<x-common.page-breadcrumb pageTitle="Revisi Anggaran" label="Perencanaan Anggaran" />

<div class="work work-stack"
    x-data="{
        programs: @js($groupedPrograms),
        searchQuery: '',
        collapsedPrograms: {},
        activePlan: null,
        activeDetail: null,
        revisionAmount: '',
        revisionReason: '',
        formErrors: {
            amount: '',
            reason: ''
        },
        saving: false,
        showSuccessModal: {{ session('success_modal') ? 'true' : 'false' }},

        toggleProgram(index) {
            this.collapsedPrograms[index] = !this.collapsedPrograms[index];
        },

        openRevisionModal(activity) {
            this.activePlan = activity;
            const rawAmt = activity.latest_revision ? activity.latest_revision.new_amount : activity.amount;
            this.revisionAmount = window.formatCurrencyMask ? window.formatCurrencyMask(rawAmt) : (rawAmt || '');
            this.revisionReason = '';
            this.formErrors = { amount: '', reason: '' };
            this.$nextTick(() => {
                this.$refs.revisionModal.showModal();
            });
        },

        closeRevisionModal() {
            this.$refs.revisionModal.close();
            this.activePlan = null;
        },

        openDetailModal(activity) {
            this.activeDetail = activity;
            this.$nextTick(() => {
                this.$refs.detailModal.showModal();
            });
        },

        closeDetailModal() {
            this.$refs.detailModal.close();
            this.activeDetail = null;
        },

        validateAndSubmit(e) {
            this.formErrors = { amount: '', reason: '' };
            let hasError = false;
            const cleanNum = Number(String(this.revisionAmount || '').replace(/\D/g, ''));

            if (!this.revisionAmount || cleanNum <= 0) {
                this.formErrors.amount = 'Masukkan nominal baru / Nominal baru wajib diisi.';
                hasError = true;
            }

            if (!this.revisionReason || this.revisionReason.trim().length === 0) {
                this.formErrors.reason = 'Alasan revisi wajib diisi.';
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
                return false;
            }

            this.saving = true;
        },

        formatRupiah(val) {
            if (!val && val !== 0) return 'Rp 0';
            const clean = Number(String(val).replace(/\D/g, '')) || 0;
            return 'Rp ' + clean.toLocaleString('id-ID');
        },

        filteredPrograms() {
            if (!this.searchQuery.trim()) return this.programs;
            const q = this.searchQuery.toLowerCase();
            return this.programs.map(prog => {
                const matchProg = prog.name.toLowerCase().includes(q);
                const matchedActivities = prog.activities.filter(act => 
                    act.activity_name.toLowerCase().includes(q) ||
                    act.source_funding.toLowerCase().includes(q) ||
                    matchProg
                );
                return {
                    ...prog,
                    activities: matchedActivities
                };
            }).filter(prog => prog.activities.length > 0);
        }
    }">

    {{-- TOP BANNER / TIPS SESUAI FLOW REVISI ANGGARAN --}}
    <div class="rounded-xl border border-sky-100 bg-sky-50/70 p-4 dark:border-sky-900/40 dark:bg-sky-950/20 flex items-start gap-3.5 shadow-2xs">
        <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0 mt-0.5">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
        </div>
        <div class="text-xs text-sky-900 dark:text-sky-200 leading-relaxed">
            <strong class="font-semibold text-sky-950 dark:text-sky-100">Revisi anggaran diperlukan jika ada perubahan kebutuhan di tengah tahun.</strong>
            <span> Semua perubahan akan disetujui oleh Kepala Sekolah sebelum digunakan.</span>
        </div>
    </div>

    {{-- STEP 3: PILIH TAHUN ANGGARAN --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Revisi Anggaran</h2>
                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    <span>Perencanaan Anggaran</span>
                    <span>&rsaquo;</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Revisi Anggaran</span>
                </div>
            </div>

            <form method="GET" action="{{ route('finance.budgets.revisions') }}" class="w-full sm:w-80">
                <label for="budget_year_select" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Tahun Anggaran <span class="text-red-500">*</span>
                </label>
                <select id="budget_year_select" name="budget_year_id" onchange="this.form.submit()"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-800 shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    @foreach($budgetYears as $year)
                        <option value="{{ $year->id }}" {{ (int) $selectedYearId === (int) $year->id ? 'selected' : '' }}>
                            {{ $year->name }} {{ $year->status === 'active' ? '(Aktif)' : '' }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="mt-3.5 flex items-center gap-2 text-xs text-sky-700 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40 px-3 py-2 rounded-lg border border-sky-100 dark:border-sky-900/40">
            <svg class="w-4 h-4 shrink-0 text-sky-600 dark:text-sky-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <span>Hanya tahun anggaran yang berstatus aktif yang dapat direvisi.</span>
        </div>
    </div>

    {{-- STEP 4: TAMPILKAN PROGRAM DAN KEGIATAN (CONTAINER WITH CLASS DATA-TABLE) --}}
    <section class="data-table rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden" aria-label="Daftar Program dan Kegiatan">
        <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Daftar Program dan Kegiatan</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Sistem menampilkan daftar program dan kegiatan sesuai tahun anggaran yang dipilih.</p>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative w-full sm:w-64">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text" x-model="searchQuery" placeholder="Cari program atau kegiatan..."
                        class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-hidden focus:ring-1 focus:ring-brand-500" />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
                <thead class="bg-gray-50/75 dark:bg-gray-800/60 text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="py-3 px-4 w-14 text-center">No</th>
                        <th class="py-3 px-4">Nama Program / Kegiatan</th>
                        <th class="py-3 px-4 w-36">Sumber Dana</th>
                        <th class="py-3 px-4 w-44">Anggaran Saat Ini</th>
                        <th class="py-3 px-4 w-36 text-center">Status</th>
                        <th class="py-3 px-4 w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    <template x-for="(prog, pIndex) in filteredPrograms()" :key="prog.name">
                        <template x-data="{ expanded: !collapsedPrograms[pIndex] }">
                            <template x-teleport-skip>
                            </template>
                        </template>
                    </template>

                    @forelse($groupedPrograms as $pIndex => $prog)
                        {{-- PROGRAM PARENT ROW --}}
                        <tr class="bg-gray-50/50 dark:bg-gray-800/40 font-semibold text-gray-900 dark:text-white"
                            x-show="filteredPrograms().some(p => p.name === '{{ addslashes($prog['name']) }}')">
                            <td class="py-3 px-4 text-center text-gray-500 dark:text-gray-400">{{ $pIndex + 1 }}</td>
                            <td class="py-3 px-4">
                                <button type="button" @click="toggleProgram({{ $pIndex }})" class="flex items-center gap-2 hover:text-brand-600 text-left font-semibold">
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-150"
                                        :class="collapsedPrograms[{{ $pIndex }}] ? '-rotate-90' : 'rotate-0'"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <span>{{ $prog['name'] }}</span>
                                </button>
                            </td>
                            <td class="py-3 px-4 text-gray-400">-</td>
                            <td class="py-3 px-4 font-bold text-gray-900 dark:text-white tabular-nums">
                                Rp {{ number_format($prog['total_amount'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @php
                                    $hasPending = collect($prog['activities'])->contains(fn($a) => ($a['status'] ?? '') === 'pending');
                                    $hasRejected = collect($prog['activities'])->contains(fn($a) => ($a['status'] ?? '') === 'rejected');
                                @endphp
                                @if($hasPending)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        Ada Revisi Pending
                                    </span>
                                @elseif($hasRejected)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                        Perlu Revisi Ulang
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center text-gray-400">-</td>
                        </tr>

                        {{-- KEGIATAN CHILD ROWS --}}
                        @foreach($prog['activities'] as $aIndex => $act)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/30 transition-colors"
                                x-show="!collapsedPrograms[{{ $pIndex }}] && filteredPrograms().some(p => p.name === '{{ addslashes($prog['name']) }}' && p.activities.some(a => a.id === {{ $act['id'] }}))">
                                <td class="py-2.5 px-4 text-center text-gray-400 pl-6">{{ $pIndex + 1 }}.{{ $aIndex + 1 }}</td>
                                <td class="py-2.5 px-4 pl-8">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                        <span class="text-gray-800 dark:text-gray-200 font-medium">{{ $act['activity_name'] }}</span>
                                    </div>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                        {{ $act['source_funding'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 font-semibold text-gray-900 dark:text-white tabular-nums">
                                    Rp {{ number_format($act['amount'], 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    @if(($act['status'] ?? '') === 'pending')
                                        <button type="button" @click="openDetailModal(@js($act))"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 hover:bg-amber-200 transition-colors"
                                            title="Klik untuk melihat status persetujuan">
                                            <svg class="w-3 h-3 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span>Menunggu Persetujuan</span>
                                        </button>
                                    @elseif(($act['status'] ?? '') === 'rejected')
                                        <button type="button" @click="openDetailModal(@js($act))"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 hover:bg-red-200 transition-colors"
                                            title="Klik untuk melihat alasan penolakan">
                                            <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            <span>Ditolak</span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                            Aktif
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" @click="openRevisionModal(@js($act))"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium text-brand-700 bg-brand-50 hover:bg-brand-100 dark:bg-brand-950/60 dark:text-brand-300 dark:hover:bg-brand-900/50 border border-brand-200 dark:border-brand-800 transition-colors"
                                            aria-label="Revisi {{ $act['activity_name'] }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <span>Revisi</span>
                                        </button>

                                        @if($act['latest_revision'])
                                            <button type="button" @click="openDetailModal(@js($act))"
                                                class="p-1 rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800 transition-colors"
                                                title="Lihat status timeline revisi">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                                Belum ada rencana anggaran pada tahun anggaran ini. Silakan buat rencana di menu Susun Anggaran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3.5 bg-gray-50/60 dark:bg-gray-800/40 border-t border-gray-200 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400">
            Sistem menampilkan daftar program dan kegiatan sesuai tahun anggaran yang dipilih. Bendahara klik tombol <strong>"Revisi"</strong> pada kegiatan yang ingin diubah.
        </div>
    </section>

    {{-- STEP 5 & 6: MODAL FORM REVISI ANGGARAN --}}
    <dialog x-ref="revisionModal" class="account-dialog work max-w-lg w-full" aria-labelledby="rev-title"
        @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) closeRevisionModal()">
        
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="rev-title" class="text-base font-semibold text-gray-900 dark:text-white">Revisi Anggaran</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Sistem menampilkan form revisi anggaran dengan nominal lama, nominal baru, dan alasan revisi.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="closeRevisionModal()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="activePlan ? activePlan.revision_url : ''" @submit="validateAndSubmit($event)">
            @csrf

            <div class="space-y-3.5 my-4">
                {{-- Nama Program --}}
                <div class="work-field">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Program</label>
                    <input type="text" :value="activePlan?.program_name" readonly disabled
                        class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 cursor-not-allowed">
                </div>

                {{-- Nama Kegiatan --}}
                <div class="work-field">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Kegiatan</label>
                    <input type="text" :value="activePlan?.activity_name" readonly disabled
                        class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 cursor-not-allowed">
                </div>

                {{-- Sumber Dana --}}
                <div class="work-field">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sumber Dana</label>
                    <input type="text" :value="activePlan?.source_funding" readonly disabled
                        class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 cursor-not-allowed">
                </div>

                {{-- Nominal Lama --}}
                <div class="work-field">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal Lama</label>
                    <input type="text" :value="formatRupiah(activePlan?.amount)" readonly disabled
                        class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 cursor-not-allowed tabular-nums">
                </div>

                {{-- Nominal Baru * --}}
                <div class="work-field">
                    <label for="input_new_amount" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nominal Baru <span class="text-red-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 dark:text-gray-400 pointer-events-none select-none z-10">Rp</span>
                        <input id="input_new_amount" name="new_amount" type="text" inputmode="numeric" data-mask="currency" x-model="revisionAmount"
                            :class="formErrors.amount ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 dark:border-gray-700 focus:ring-brand-500 focus:border-brand-500'"
                            class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums focus:outline-hidden focus:ring-1 mask-currency"
                            style="padding-left: 2.75rem !important;"
                            placeholder="Masukkan nominal baru (contoh: 70.000.000)">
                    </div>
                    <template x-if="formErrors.amount">
                        <p class="mt-1 text-[11px] text-red-600 font-medium" x-text="formErrors.amount"></p>
                    </template>
                    <template x-if="revisionAmount && (Number(String(revisionAmount).replace(/\D/g, '')) > 0)">
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400" x-text="'Preview: ' + formatRupiah(revisionAmount)"></p>
                    </template>
                </div>

                {{-- Alasan Revisi * --}}
                <div class="work-field">
                    <div class="flex items-center justify-between mb-1">
                        <label for="input_reason" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                            Alasan Revisi <span class="text-red-500">*</span>
                        </label>
                        <span class="text-[11px] text-gray-400 tabular-nums">
                            <span x-text="revisionReason.length"></span>/300
                        </span>
                    </div>
                    <textarea id="input_reason" name="reason" rows="3" maxlength="300" x-model="revisionReason"
                        :class="formErrors.reason ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 dark:border-gray-700 focus:ring-brand-500 focus:border-brand-500'"
                        class="w-full rounded-lg border bg-white dark:bg-gray-800 px-3 py-2 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:outline-hidden focus:ring-1"
                        placeholder="Tuliskan alasan revisi"></textarea>
                    <template x-if="formErrors.reason">
                        <p class="mt-1 text-[11px] text-red-600 font-medium" x-text="formErrors.reason"></p>
                    </template>
                </div>
            </div>

            <div class="account-dialog-actions mt-5 flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="closeRevisionModal()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- STEP 7: MODAL DATA BERHASIL DISIMPAN (SUCCESS MODAL) --}}
    <dialog x-ref="successModal" class="account-dialog work max-w-sm w-full text-center"
        x-init="if (showSuccessModal) { $nextTick(() => { $refs.successModal.showModal(); }); }">
        <div class="py-3 px-2 flex flex-col items-center">
            <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-3.5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Revisi anggaran disimpan</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-5">
                Data revisi anggaran berhasil disimpan dan dikirim ke Kepala Sekolah untuk persetujuan.
            </p>
            <button type="button" @click="$refs.successModal.close()" class="w-full work-btn work-btn-primary py-2 text-xs font-semibold justify-center">
                OK
            </button>
        </div>
    </dialog>

    {{-- STEP 8 & 9: MODAL STATUS REVISI ANGGARAN & APPROVAL DETAIL --}}
    <dialog x-ref="detailModal" class="account-dialog work max-w-lg w-full" aria-labelledby="status-rev-title"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) closeDetailModal()">
        
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="status-rev-title" class="text-base font-semibold text-gray-900 dark:text-white">Status Revisi Anggaran</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Alur persetujuan Kepala Sekolah untuk usulan revisi anggaran.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="closeDetailModal()" aria-label="Tutup detail">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="my-4 space-y-4 text-xs">
            {{-- SUMMARY CARD --}}
            <div class="p-3 bg-gray-50 dark:bg-gray-800/60 rounded-lg border border-gray-200 dark:border-gray-700/60 space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-gray-500">Program:</span>
                    <span class="font-medium text-gray-900 dark:text-white" x-text="activeDetail?.program_name"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Kegiatan:</span>
                    <span class="font-medium text-gray-900 dark:text-white" x-text="activeDetail?.activity_name"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Sumber Dana:</span>
                    <span class="font-medium text-gray-900 dark:text-white" x-text="activeDetail?.source_funding"></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-1.5 mt-1.5">
                    <span class="text-gray-500">Nominal Lama &rarr; Baru:</span>
                    <span class="font-bold text-gray-900 dark:text-white tabular-nums">
                        <span x-text="formatRupiah(activeDetail?.latest_revision?.old_amount || activeDetail?.amount)"></span>
                        <span class="text-gray-400 mx-1">&rarr;</span>
                        <span class="text-brand-600 dark:text-brand-400" x-text="formatRupiah(activeDetail?.latest_revision?.new_amount)"></span>
                    </span>
                </div>
                <template x-if="activeDetail?.latest_revision?.reason">
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-1.5 mt-1.5">
                        <span class="text-gray-500 block mb-0.5">Alasan Revisi:</span>
                        <p class="text-gray-800 dark:text-gray-200 italic" x-text="activeDetail?.latest_revision?.reason"></p>
                    </div>
                </template>
            </div>

            {{-- STEP 8: TIMELINE STATUS REVISI ANGGARAN --}}
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                <h4 class="text-xs font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-3">Timeline Persetujuan</h4>
                
                <div class="space-y-4">
                    {{-- 1. Revisi diajukan --}}
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">Revisi diajukan</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400" x-text="(activeDetail?.latest_revision?.created_at || 'Baru saja') + ' oleh ' + (activeDetail?.latest_revision?.requester_name || 'Bendahara')"></p>
                            <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5">Revisi anggaran telah disimpan dan menunggu persetujuan Kepala Sekolah.</p>
                        </div>
                    </div>

                    {{-- 2. Menunggu persetujuan Kepala Sekolah --}}
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                            :class="activeDetail?.status === 'pending' ? 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400'">
                            <template x-if="activeDetail?.status === 'pending'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>
                            </template>
                            <template x-if="activeDetail?.status !== 'pending'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </template>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">Menunggu persetujuan Kepala Sekolah</p>
                            <p class="text-[11px] text-gray-500" x-text="activeDetail?.status === 'pending' ? 'Sedang dalam antrian review pimpinan' : 'Telah ditelaah oleh Kepala Sekolah'"></p>
                        </div>
                    </div>

                    {{-- 3. Disetujui / Ditolak --}}
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                            :class="{
                                'bg-gray-100 text-gray-400 dark:bg-gray-800': activeDetail?.status === 'pending',
                                'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400': activeDetail?.status === 'approved',
                                'bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400': activeDetail?.status === 'rejected'
                            }">
                            <template x-if="activeDetail?.status === 'pending'">
                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            </template>
                            <template x-if="activeDetail?.status === 'approved'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="activeDetail?.status === 'rejected'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </template>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">Disetujui / Ditolak (Oleh Kepala Sekolah)</p>
                            <template x-if="activeDetail?.latest_revision?.reviewed_at">
                                <p class="text-[11px] text-gray-500" x-text="activeDetail.latest_revision.reviewed_at + ' oleh ' + (activeDetail.latest_revision.reviewer_name || 'Kepala Sekolah')"></p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 9: HASIL SETELAH DISETUJUI / DITOLAK --}}
            <template x-if="activeDetail?.status === 'approved'">
                <div class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/40 dark:bg-emerald-950/20 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h5 class="font-semibold text-emerald-900 dark:text-emerald-200">Revisi Anggaran Disetujui</h5>
                        <p class="text-[11px] text-emerald-800 dark:text-emerald-300 mt-0.5">
                            Revisi anggaran telah disetujui oleh Kepala Sekolah <span x-text="activeDetail?.latest_revision?.reviewed_at ? 'pada ' + activeDetail.latest_revision.reviewed_at : ''"></span>. Anggaran kini aktif dan dapat digunakan.
                        </p>
                    </div>
                </div>
            </template>

            <template x-if="activeDetail?.status === 'rejected'">
                <div class="p-3.5 rounded-xl border border-red-200 bg-red-50/80 dark:border-red-900/40 dark:bg-red-950/20 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 dark:bg-red-900/50 dark:text-red-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div class="flex-1">
                        <h5 class="font-semibold text-red-900 dark:text-red-200">Revisi Anggaran Ditolak</h5>
                        <p class="text-[11px] text-red-800 dark:text-red-300 mt-0.5">
                            Revisi anggaran ditolak oleh Kepala Sekolah <span x-text="activeDetail?.latest_revision?.reviewed_at ? 'pada ' + activeDetail.latest_revision.reviewed_at : ''"></span>. Bendahara dapat melakukan revisi kembali sesuai catatan dari Kepala Sekolah.
                        </p>
                        <div class="mt-2.5">
                            <button type="button" @click="closeDetailModal(); openRevisionModal(activeDetail);"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-semibold bg-red-600 hover:bg-red-700 text-white transition-colors">
                                <span>Ajukan Revisi Kembali</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="account-dialog-actions mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex justify-end">
            <button type="button" class="work-btn" @click="closeDetailModal()">Tutup</button>
        </div>
    </dialog>
</div>
@endsection
