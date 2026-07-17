@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pencatatan Belanja & Operasional Sekolah" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            <i class="bx bx-check-circle text-lg"></i>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- Left: Form Tambah Pengeluaran -->
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Catat Pengeluaran Baru
                </h4>

                <form action="{{ route('bos.belanja.store') }}" method="POST" class="space-y-4" x-data="{ 
                    amount: 0, 
                    taxType: '', 
                    taxAmount: 0,
                    calculateTax() {
                        let amt = parseFloat(this.amount) || 0;
                        if (this.taxType === 'PPN') {
                            this.taxAmount = Math.round(amt * 0.11);
                        } else if (this.taxType === 'PPh 22') {
                            this.taxAmount = Math.round(amt * 0.015);
                        } else if (this.taxType === 'PPh 23') {
                            this.taxAmount = Math.round(amt * 0.02);
                        } else {
                            this.taxAmount = 0;
                        }
                    }
                }">
                    @csrf
                    
                    <input type="hidden" name="academic_year_id" value="{{ $activeYear?->id }}" />

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Akun Anggaran RKAS
                        </label>
                        <div class="relative bg-transparent">
                            <select name="budget_category_id" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="">-- Pilih Akun --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->code }} - {{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <i class="bx bx-chevron-down text-lg"></i>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Uraian Belanja / Kegiatan
                        </label>
                        <input type="text" name="expense_name" placeholder="Contoh: Pembelian ATK Ujian Ganjil" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Jumlah Belanja (Rp)
                            </label>
                            <input type="number" name="amount" x-model="amount" @input="calculateTax()" placeholder="Contoh: 1500000" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Tanggal Belanja
                            </label>
                            <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Sumber Dana
                            </label>
                            <div class="relative bg-transparent">
                                <select name="source_funding" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                    <option value="BOS">BOS (Negeri)</option>
                                    <option value="Yayasan">Yayasan (Swasta)</option>
                                    <option value="Komite">Komite / Iuran</option>
                                </select>
                                <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <i class="bx bx-chevron-down text-lg"></i>
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Cara Pembayaran
                            </label>
                            <div class="relative bg-transparent">
                                <select name="payment_method" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                    <option value="Tunai">Tunai</option>
                                    <option value="Transfer">Transfer Bank</option>
                                </select>
                                <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <i class="bx bx-chevron-down text-lg"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                No. Bukti / Invoice
                            </label>
                            <input type="text" name="reference_invoice" placeholder="Contoh: NOTA-0922"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Penerima Dana / Toko
                            </label>
                            <input type="text" name="recipient_name" placeholder="Contoh: CV. Restu Agung"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                        <span class="block mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Perhitungan Potongan Pajak</span>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Jenis Pajak
                                </label>
                                <div class="relative bg-transparent">
                                    <select name="tax_type" x-model="taxType" @change="calculateTax()"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="">Tidak Ada Pajak</option>
                                        <option value="PPN">PPN (11%)</option>
                                        <option value="PPh 21">PPh 21 (Honor/Gaji)</option>
                                        <option value="PPh 22">PPh 22 (Barang 1.5%)</option>
                                        <option value="PPh 23">PPh 23 (Jasa 2%)</option>
                                    </select>
                                    <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                        <i class="bx bx-chevron-down text-lg"></i>
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Nilai Pajak (Rp)
                                </label>
                                <input type="number" name="tax_amount" x-model="taxAmount" placeholder="0" readonly
                                    class="dark:bg-dark-900 bg-gray-50 dark:border-gray-700 dark:text-white/50 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-400 focus:outline-hidden" />
                            </div>
                        </div>

                        <!-- Checkbox Pajak Disetor -->
                        <div class="mt-3 flex items-center gap-2">
                            <input type="checkbox" name="is_tax_paid" id="is_tax_paid" value="1"
                                class="h-4 w-4 rounded-sm border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" />
                            <label for="is_tax_paid" class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                Pajak sudah langsung disetor ke kas negara (SSP)
                            </label>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Simpan Transaksi Belanja
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Daftar Pengeluaran Table -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Buku Pembantu Pengeluaran Belanja
                </h4>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[700px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal / Bukti</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Akun & Uraian Belanja</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sumber / Metode</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jumlah Belanja</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Pajak (Status)</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($expenses as $exp)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-4">
                                            <span class="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">
                                                {{ $exp->transaction_date->translatedFormat('d M Y') }}
                                            </span>
                                            <span class="block font-mono text-xs text-gray-400">
                                                {{ $exp->reference_invoice ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="block font-mono text-xs text-brand-600 dark:text-brand-400">
                                                {{ $exp->budgetCategory->code ?? 'Umum' }}
                                            </span>
                                            <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                {{ $exp->expense_name }}
                                            </span>
                                            <span class="block text-xs text-gray-400">
                                                Penerima: {{ $exp->recipient_name ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="block text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                {{ $exp->source_funding }}
                                            </span>
                                            <span class="block text-xs text-gray-400">
                                                {{ $exp->payment_method }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="font-semibold text-theme-sm text-red-500">
                                                Rp {{ number_format($exp->amount, 0, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($exp->tax_amount > 0)
                                                <span class="block text-theme-sm text-gray-800 dark:text-white/90 font-medium">
                                                    {{ $exp->tax_type }}: Rp {{ number_format($exp->tax_amount, 0, ',', '.') }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $exp->is_tax_paid ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400' }}">
                                                    <i class="bx {{ $exp->is_tax_paid ? 'bx-check' : 'bx-time' }}"></i>
                                                    {{ $exp->is_tax_paid ? 'Disetor' : 'Belum Setor' }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">Nihil</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">
                                            Belum ada pencatatan transaksi belanja operasional.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
