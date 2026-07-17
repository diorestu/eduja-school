@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <x-common.page-breadcrumb pageTitle="Laporan & Tunggakan SPP" />
        
        <button onclick="window.print()" class="flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
            <i class="bx bx-printer"></i> Cetak Laporan
        </button>
    </div>

    <!-- Metrics Section -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 mb-6">
        <!-- Card: Total Collected -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-green-50 rounded-xl dark:bg-green-500/10 mb-4 text-green-600 dark:text-green-500 text-lg">
                <i class="bx bx-check-circle text-xl"></i>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Dana SPP Masuk</span>
            <h4 class="mt-2 font-bold text-gray-800 text-2xl dark:text-white/90">
                Rp {{ number_format($totalCollected, 0, ',', '.') }}
            </h4>
        </div>

        <!-- Card: Total Outstanding -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-red-50 rounded-xl dark:bg-red-500/10 mb-4 text-red-500 text-lg">
                <i class="bx bx-error-circle text-xl"></i>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Tunggakan SPP</span>
            <h4 class="mt-2 font-bold text-red-500 text-2xl dark:text-red-400">
                Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
            </h4>
        </div>
    </div>

    <!-- Alpine.js Tabs for Reports -->
    <div x-data="{ activeTab: 'tunggakan' }" class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        
        <!-- Tab Navigation Header -->
        <div class="flex border-b border-gray-200 dark:border-gray-800 mb-6">
            <button type="button" 
                @click="activeTab = 'tunggakan'"
                :class="activeTab === 'tunggakan' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Daftar Tunggakan
            </button>
            <button type="button" 
                @click="activeTab = 'kelas'"
                :class="activeTab === 'kelas' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Rekap per Kelas
            </button>
            <button type="button" 
                @click="activeTab = 'riwayat'"
                :class="activeTab === 'riwayat' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Riwayat Transaksi Masuk
            </button>
        </div>

        <!-- Tab Content 1: Tunggakan -->
        <div x-show="activeTab === 'tunggakan'" x-transition>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[700px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa / NIS</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kelas</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Tagihan</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jatuh Tempo</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sisa Tunggakan</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($unpaidInvoices as $invoice)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-4">
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $invoice->student->name }}</span>
                                        <span class="block text-xs text-gray-400">NIS: {{ $invoice->student->nis }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-gray-800 text-theme-sm dark:text-white/90 font-medium">
                                            {{ $invoice->student->schoolClasses->first()?->name ?? 'Belum Ada Kelas' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block text-gray-800 text-theme-sm dark:text-white/90 font-mono">{{ $invoice->invoice_number }}</span>
                                        <span class="block text-xs text-gray-400">{{ $invoice->invoiceItems->first()?->name ?? 'SPP' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                            {{ $invoice->due_date->translatedFormat('d M Y') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-red-500 font-semibold text-theme-sm dark:text-red-400">
                                            Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($invoice->status == 'Cicilan')
                                            <span class="inline-flex rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400">Cicilan</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-500">Belum Lunas</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">
                                        Luar biasa! Tidak ada siswa yang menunggak tagihan saat ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab Content 2: Rekap per Kelas -->
        <div x-show="activeTab === 'kelas'" x-transition style="display: none;">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[700px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Kelas</p>
                                </th>
                                <th class="px-5 py-3 text-right">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Total Ditagihkan</p>
                                </th>
                                <th class="px-5 py-3 text-right">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Total Diterima</p>
                                </th>
                                <th class="px-5 py-3 text-right">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sisa Tunggakan</p>
                                </th>
                                <th class="px-5 py-3 text-center w-56">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Persentase Pelunasan</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($classReports as $report)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-4 font-semibold text-gray-800 dark:text-white/90">
                                        {{ $report['name'] }}
                                    </td>
                                    <td class="px-5 py-4 text-right text-gray-700 dark:text-gray-300">
                                        Rp {{ number_format($report['invoiced'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4 text-right text-green-600 dark:text-green-500 font-semibold">
                                        Rp {{ number_format($report['collected'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4 text-right text-red-500 dark:text-red-400 font-semibold">
                                        Rp {{ number_format($report['outstanding'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @php
                                            $percent = $report['invoiced'] > 0 ? round(($report['collected'] / $report['invoiced']) * 100, 1) : 0;
                                        @endphp
                                        <div class="flex items-center gap-3">
                                            <div class="w-full bg-gray-100 rounded-full h-2.5 dark:bg-gray-800">
                                                <div class="bg-green-500 h-2.5 rounded-full" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <span class="text-theme-xs font-semibold text-gray-700 dark:text-gray-300 min-w-10 text-right">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">
                                        Belum ada data transaksi per kelas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab Content 3: Riwayat Transaksi -->
        <div x-show="activeTab === 'riwayat'" x-transition style="display: none;">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[700px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Kuitansi</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa / NIS</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal Bayar</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Metode</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jumlah Bayar</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Penerima</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($payments as $payment)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-4">
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 font-mono">{{ $payment->receipt_number }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $payment->invoice->student->name }}</span>
                                        <span class="block text-xs text-gray-400">Kelas: {{ $payment->invoice->student->schoolClasses->first()?->name ?? '-' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                            {{ $payment->payment_date->translatedFormat('d M Y') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $payment->payment_method }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-green-600 font-semibold text-theme-sm dark:text-green-500">
                                            Rp {{ number_format($payment->amount_paid, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-gray-800 text-theme-sm dark:text-white/90">
                                            {{ $payment->recipient_name ?? 'Sistem' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">
                                        Belum ada transaksi pembayaran masuk.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
