@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <x-common.page-breadcrumb pageTitle="Buku Kas Umum & Buku Pembantu" />
        
        <button onclick="window.print()" class="flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
            <i class="bx bx-printer"></i> Cetak Dokumen
        </button>
    </div>

    <!-- Ledger Summary Metrics -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6 mb-6">
        
        <!-- Total Debet (Kas Masuk) -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-green-50 rounded-lg dark:bg-green-500/10 mb-4 text-green-600 dark:text-green-500 text-lg">
                <i class="bx bx-plus-circle"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Penerimaan (Debet BKU)</span>
            <h4 class="mt-2 font-bold text-green-600 text-xl dark:text-green-500">
                Rp {{ number_format($totalDebet, 0, ',', '.') }}
            </h4>
        </div>

        <!-- Total Kredit (Kas Keluar) -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-red-50 rounded-lg dark:bg-red-500/10 mb-4 text-red-500 text-lg">
                <i class="bx bx-minus-circle"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Pengeluaran (Kredit BKU)</span>
            <h4 class="mt-2 font-bold text-red-500 text-xl dark:text-red-400">
                Rp {{ number_format($totalKredit, 0, ',', '.') }}
            </h4>
        </div>

        <!-- Final Balance (Saldo Akhir) -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-brand-50 rounded-lg dark:bg-brand-500/10 mb-4 text-brand-600 dark:text-brand-500 text-lg">
                <i class="bx bx-wallet"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Saldo Kas Buku Kas Umum (BKU)</span>
            <h4 class="mt-2 font-bold text-brand-600 text-xl dark:text-brand-500">
                Rp {{ number_format($finalBalance, 0, ',', '.') }}
            </h4>
        </div>

    </div>

    <!-- Alpine.js Tabs for BKU & Buku Pembantu -->
    <div x-data="{ activeTab: 'bku' }" class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        
        <!-- Tab Navigation Header -->
        <div class="flex flex-wrap border-b border-gray-200 dark:border-gray-800 mb-6 gap-2 sm:gap-0">
            <button type="button" 
                @click="activeTab = 'bku'"
                :class="activeTab === 'bku' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Buku Kas Umum (BKU)
            </button>
            <button type="button" 
                @click="activeTab = 'kas'"
                :class="activeTab === 'kas' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Buku Pembantu Kas (Tunai)
            </button>
            <button type="button" 
                @click="activeTab = 'bank'"
                :class="activeTab === 'bank' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Buku Pembantu Bank
            </button>
            <button type="button" 
                @click="activeTab = 'pajak'"
                :class="activeTab === 'pajak' ? 'border-brand-500 text-brand-600 dark:text-brand-500 border-b-2 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="pb-3 px-4 text-sm focus:outline-hidden">
                Buku Pembantu Pajak
            </button>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: BUKU KAS UMUM (BKU) -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'bku'" x-transition>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-5 gap-2">
                <div>
                    <h5 class="font-bold text-gray-800 dark:text-white/90">Lembar Buku Kas Umum</h5>
                    <p class="text-xs text-gray-400">Gabungan seluruh kas masuk, keluar, dan perpajakan sekolah secara kronologis.</p>
                </div>
                <span class="inline-flex rounded-lg bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                    Saldo Akhir: Rp {{ number_format($finalBalance, 0, ',', '.') }}
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left w-28"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal</p></th>
                                <th class="px-5 py-3 text-left w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Bukti/Ref</p></th>
                                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Uraian Transaksi</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Penerimaan (Debet)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Pengeluaran (Kredit)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Saldo Buku</p></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-theme-sm">
                            <tr class="bg-gray-50/30 dark:bg-gray-800/5">
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 font-sans italic font-semibold">SALDO AWAL BUKU KAS</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-900 dark:text-white font-bold">Rp 0</td>
                            </tr>
                            @forelse($ledger as $entry)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400 font-sans">{{ \Carbon\Carbon::parse($entry['date'])->translatedFormat('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-gray-800 dark:text-white/95">{{ $entry['ref'] }}</td>
                                    <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300 font-sans">{{ $entry['description'] }}</td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['debet'] > 0 ? 'text-green-600 dark:text-green-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['debet'] > 0 ? 'Rp '.number_format($entry['debet'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['kredit'] > 0 ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['kredit'] > 0 ? 'Rp '.number_format($entry['kredit'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-gray-900 dark:text-white font-bold">Rp {{ number_format($entry['saldo'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400 font-sans text-sm">Belum ada transaksi BKU.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: BUKU PEMBANTU KAS (TUNAI) -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'kas'" x-transition style="display: none;">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-5 gap-2">
                <div>
                    <h5 class="font-bold text-gray-800 dark:text-white/90">Buku Pembantu Kas (Tunai)</h5>
                    <p class="text-xs text-gray-400">Hanya menampilkan arus transaksi tunai (uang kertas/logam) di brankas sekolah.</p>
                </div>
                <span class="inline-flex rounded-lg bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-500/10 dark:text-green-400">
                    Saldo Brankas Tunai: Rp {{ number_format($balanceKas, 0, ',', '.') }}
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left w-28"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal</p></th>
                                <th class="px-5 py-3 text-left w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Bukti/Ref</p></th>
                                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Uraian Transaksi</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Penerimaan (Tunai)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Pengeluaran (Tunai)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Saldo Kas</p></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-theme-sm">
                            <tr class="bg-gray-50/30 dark:bg-gray-800/5">
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 font-sans italic font-semibold">SALDO AWAL KAS TUNAI</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-900 dark:text-white font-bold">Rp 0</td>
                            </tr>
                            @forelse($bukuKas as $entry)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400 font-sans">{{ \Carbon\Carbon::parse($entry['date'])->translatedFormat('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-gray-800 dark:text-white/95">{{ $entry['ref'] }}</td>
                                    <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300 font-sans">{{ $entry['description'] }}</td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['debet'] > 0 ? 'text-green-600 dark:text-green-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['debet'] > 0 ? 'Rp '.number_format($entry['debet'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['kredit'] > 0 ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['kredit'] > 0 ? 'Rp '.number_format($entry['kredit'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-gray-900 dark:text-white font-bold">Rp {{ number_format($entry['saldo'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400 font-sans text-sm">Belum ada transaksi kas tunai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: BUKU PEMBANTU BANK -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'bank'" x-transition style="display: none;">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-5 gap-2">
                <div>
                    <h5 class="font-bold text-gray-800 dark:text-white/90">Buku Pembantu Bank</h5>
                    <p class="text-xs text-gray-400">Hanya menampilkan arus transaksi non-tunai (transfer rekening bank sekolah).</p>
                </div>
                <span class="inline-flex rounded-lg bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                    Saldo Rekening Bank: Rp {{ number_format($balanceBank, 0, ',', '.') }}
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left w-28"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal</p></th>
                                <th class="px-5 py-3 text-left w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Bukti/Ref</p></th>
                                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Uraian Transaksi</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Penerimaan (Bank)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Pengeluaran (Bank)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Saldo Bank</p></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-theme-sm">
                            <tr class="bg-gray-50/30 dark:bg-gray-800/5">
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 font-sans italic font-semibold">SALDO AWAL REKENING BANK</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-900 dark:text-white font-bold">Rp 0</td>
                            </tr>
                            @forelse($bukuBank as $entry)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400 font-sans">{{ \Carbon\Carbon::parse($entry['date'])->translatedFormat('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-gray-800 dark:text-white/95">{{ $entry['ref'] }}</td>
                                    <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300 font-sans">{{ $entry['description'] }}</td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['debet'] > 0 ? 'text-green-600 dark:text-green-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['debet'] > 0 ? 'Rp '.number_format($entry['debet'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['kredit'] > 0 ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['kredit'] > 0 ? 'Rp '.number_format($entry['kredit'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-gray-900 dark:text-white font-bold">Rp {{ number_format($entry['saldo'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400 font-sans text-sm">Belum ada transaksi rekening bank.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: BUKU PEMBANTU PAJAK -->
        <!-- ========================================== -->
        <div x-show="activeTab === 'pajak'" x-transition style="display: none;">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-5 gap-2">
                <div>
                    <h5 class="font-bold text-gray-800 dark:text-white/90">Buku Pembantu Pajak</h5>
                    <p class="text-xs text-gray-400">Memantau transaksi pemungutan (Debet) dan penyetoran (Kredit) pajak PPN/PPh BOS.</p>
                </div>
                <span class="inline-flex rounded-lg bg-yellow-50 px-3 py-1 text-xs font-semibold text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400">
                    Kewajiban Pajak Belum Disetor: Rp {{ number_format($balancePajak, 0, ',', '.') }}
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left w-28"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal</p></th>
                                <th class="px-5 py-3 text-left w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Bukti/Ref</p></th>
                                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Uraian Transaksi Pajak</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Pemungutan (Debet)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Penyetoran (Kredit)</p></th>
                                <th class="px-5 py-3 text-right w-36"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Saldo Hutang Pajak</p></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-theme-sm">
                            <tr class="bg-gray-50/30 dark:bg-gray-800/5">
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-400">-</td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 font-sans italic font-semibold">SALDO AWAL HUTANG PAJAK</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-400">Rp 0</td>
                                <td class="px-5 py-3 text-right text-gray-900 dark:text-white font-bold">Rp 0</td>
                            </tr>
                            @forelse($bukuPajak as $entry)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400 font-sans">{{ \Carbon\Carbon::parse($entry['date'])->translatedFormat('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-gray-800 dark:text-white/95">{{ $entry['ref'] }}</td>
                                    <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300 font-sans">{{ $entry['description'] }}</td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['debet'] > 0 ? 'text-green-600 dark:text-green-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['debet'] > 0 ? 'Rp '.number_format($entry['debet'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right {{ $entry['kredit'] > 0 ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                        {{ $entry['kredit'] > 0 ? 'Rp '.number_format($entry['kredit'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-gray-900 dark:text-white font-bold">Rp {{ number_format($entry['saldo'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400 font-sans text-sm">Belum ada transaksi pajak.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
