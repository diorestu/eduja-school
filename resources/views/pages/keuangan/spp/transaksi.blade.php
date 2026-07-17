@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Transaksi Keuangan SPP" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg border border-green-200 bg-green-50 p-4 text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500">
            <span class="font-medium text-sm">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-500">
            <span class="font-medium text-sm">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Alpine Wrapper for Payment Modals -->
    <div x-data="{ 
        openPayModal: false, 
        activeInvoice: { id: '', number: '', name: '', remaining: 0 }
    }">

        <!-- Quick actions: Generate Monthly Invoice & Search -->
        <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-3">
            <!-- Generate Invoices Card -->
            <div class="lg:col-span-1">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h4 class="mb-3 font-semibold text-gray-800 text-theme-base dark:text-white/90">
                        ⚡ Generate Tagihan Bulanan (Massal)
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Membuat tagihan SPP bulanan otomatis untuk seluruh siswa aktif di tahun ajaran saat ini.
                    </p>

                    <form action="{{ route('spp.transaksi.generate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">
                                Pilih Bulan Penagihan
                            </label>
                            <input type="month" name="billing_month" value="{{ date('Y-m') }}" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>
                        <button type="submit"
                            class="w-full rounded-lg bg-brand-500 px-4 py-2 text-center text-xs font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                            Proses Penagihan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Search Card -->
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] h-full flex flex-col justify-between">
                    <div>
                        <h4 class="mb-3 font-semibold text-gray-800 text-theme-base dark:text-white/90">
                            🔍 Cari Tagihan Siswa
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Masukkan Nama atau NIS siswa untuk memfilter data tagihan.
                        </p>
                    </div>

                    <form action="{{ route('spp.transaksi.index') }}" method="GET" class="flex gap-3">
                        <div class="relative w-full">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama siswa atau NIS..."
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                        </div>
                        <button type="submit"
                            class="rounded-lg bg-gray-900 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-gray-800 dark:bg-brand-500 dark:hover:bg-brand-600 focus:outline-hidden">
                            Filter
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                Daftar Tagihan Biaya Sekolah
            </h4>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa / NIS</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Tagihan / Deskripsi</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Total Nominal</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Terbayar</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sisa Tagihan</p>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                                </th>
                                <th class="px-5 py-3 text-right">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($invoices as $invoice)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-4">
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $invoice->student->name }}</span>
                                        <span class="block text-xs text-gray-400">NIS: {{ $invoice->student->nis }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block text-gray-800 text-theme-sm dark:text-white/90 font-medium">{{ $invoice->invoice_number }}</span>
                                        <span class="block text-xs text-gray-400">
                                            {{ $invoice->invoiceItems->first()?->name ?? 'Detail Biaya' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-gray-800 text-theme-sm dark:text-white/90">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-green-600 text-theme-sm dark:text-green-500 font-medium">
                                            Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-red-500 text-theme-sm dark:text-red-400 font-semibold">
                                            Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($invoice->status == 'Lunas')
                                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-500/15 dark:text-green-500">Lunas</span>
                                        @elseif($invoice->status == 'Cicilan')
                                            <span class="inline-flex rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400">Cicilan</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-500">Belum Lunas</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if($invoice->status != 'Lunas')
                                            <button type="button" 
                                                @click="activeInvoice = { id: '{{ $invoice->id }}', number: '{{ $invoice->invoice_number }}', name: '{{ addslashes($invoice->student->name) }}', remaining: {{ $invoice->remaining_amount }} }; openPayModal = true"
                                                class="text-xs font-semibold text-brand-500 hover:text-brand-600">
                                                Bayar
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400 font-medium">Lunas</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">
                                        Tidak ada data tagihan. Buat tagihan menggunakan menu di kiri atas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Alpine Payment Modal -->
        <div x-show="openPayModal" 
            class="fixed inset-0 z-99999 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs"
            x-transition>
            
            <div class="relative w-full max-w-md p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xl"
                @click.away="openPayModal = false">
                
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                    Catat Pembayaran SPP
                </h3>
                
                <!-- Display active invoice detail -->
                <div class="mb-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/40 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div>Siswa: <span class="font-semibold text-gray-900 dark:text-white" x-text="activeInvoice.name"></span></div>
                    <div>Tagihan: <span class="font-mono font-semibold text-gray-900 dark:text-white" x-text="activeInvoice.number"></span></div>
                    <div>Sisa Tagihan: <span class="font-bold text-red-500 dark:text-red-400" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(activeInvoice.remaining)"></span></div>
                </div>

                <form :action="'/spp/transaksi/' + activeInvoice.id + '/bayar'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-400">
                            Jumlah Bayar (Rp)
                        </label>
                        <input type="number" name="amount_paid" :max="activeInvoice.remaining" required :value="activeInvoice.remaining"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-400">
                            Metode Pembayaran
                        </label>
                        <select name="payment_method" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="Tunai">Tunai / Cash</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-Wallet">E-Wallet (Gopay/Ovo)</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-400">
                            Tanggal Pembayaran
                        </label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <div class="flex justify-end gap-3 pt-3">
                        <button type="button" @click="openPayModal = false"
                            class="rounded-lg border border-gray-200 dark:border-gray-800 px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            Batal
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-brand-500 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-600">
                            Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
