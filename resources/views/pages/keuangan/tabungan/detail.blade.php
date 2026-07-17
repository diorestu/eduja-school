@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('tabungan.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
            <i class="bx bx-arrow-back"></i> Kembali ke Buku Induk
        </a>
    </div>

    <x-common.page-breadcrumb pageTitle="Buku Tabungan Siswa" />

    <!-- Error/Success Alerts -->
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            <i class="bx bx-check-circle text-lg"></i>
            <p>{{ session('success') }}</p>
        </div>
    @endif
    
    @if($errors->has('amount'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
            <i class="bx bx-error-circle text-lg"></i>
            <p>{{ $errors->first('amount') }}</p>
        </div>
    @endif

    <!-- Profile Header Banner -->
    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- Student Info -->
        <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] flex items-center gap-4">
            <div class="flex items-center justify-center w-14 h-14 bg-brand-50 rounded-full text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 text-2xl font-bold">
                {{ strtoupper(substr($student->name, 0, 1)) }}
            </div>
            <div>
                <h4 class="font-bold text-gray-800 text-lg dark:text-white/95">{{ $student->name }}</h4>
                <p class="text-xs text-gray-400 mt-1 font-mono">NIS: {{ $student->nis }} | NISN: {{ $student->nisn ?? '-' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Kelas: {{ $student->schoolClasses->first()?->name ?? 'Belum Ada Kelas' }}</p>
            </div>
        </div>

        <!-- Savings Balance Card -->
        <div class="rounded-2xl border border-gray-200 bg-brand-600 p-5 text-white dark:border-brand-800 flex flex-col justify-center">
            <span class="text-xs text-brand-200 font-semibold uppercase tracking-wider">Saldo Tabungan Saat Ini</span>
            <h3 class="mt-2 font-bold text-2xl md:text-3xl text-white">
                Rp {{ number_format($balance, 0, ',', '.') }}
            </h3>
        </div>
    </div>

    <!-- Main Workspace Split -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- Left: Form Transaksi Tabungan -->
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Input Transaksi Tabungan
                </h4>

                <form action="{{ route('tabungan.store') }}" method="POST" class="space-y-4">
                    @csrf
                    
                    <input type="hidden" name="student_id" value="{{ $student->id }}" />
                    <input type="hidden" name="academic_year_id" value="{{ $activeYear?->id }}" />

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Jenis Transaksi
                        </label>
                        <div class="relative bg-transparent">
                            <select name="type" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="Setoran">Setoran (Kredit Saldo)</option>
                                <option value="Penarikan">Penarikan (Debet Saldo)</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <i class="bx bx-chevron-down text-lg"></i>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Jumlah Nominal (Rp)
                        </label>
                        <input type="number" name="amount" placeholder="Contoh: 50000" min="1" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Tanggal Transaksi
                        </label>
                        <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Keterangan / Catatan
                        </label>
                        <textarea name="note" rows="3" placeholder="Contoh: Titipan uang saku mingguan..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Simpan Transaksi
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Ledger Transactions Table -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Mutasi & Riwayat Tabungan
                </h4>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[600px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left w-28">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal</p>
                                    </th>
                                    <th class="px-5 py-3 text-left w-36">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Referensi</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Catatan (Penerima)</p>
                                    </th>
                                    <th class="px-5 py-3 text-right w-28">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Setoran (+)</p>
                                    </th>
                                    <th class="px-5 py-3 text-right w-28">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Penarikan (-)</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-theme-sm">
                                @forelse($ledger as $tx)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400 font-sans">
                                            {{ $tx->transaction_date->translatedFormat('d/m/Y') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-800 dark:text-white/95">
                                            {{ $tx->reference_number }}
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300 font-sans">
                                            <span class="block font-medium text-gray-800 dark:text-white/90">{{ $tx->note ?? 'Setoran/Penarikan' }}</span>
                                            <span class="block text-xs text-gray-400">Petugas: {{ $tx->recipient_name }}</span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-semibold text-green-600 dark:text-green-500">
                                            @if($tx->type === 'Setoran')
                                                Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-semibold text-red-500">
                                            @if($tx->type === 'Penarikan')
                                                Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-gray-400 font-sans text-sm">
                                            Belum ada riwayat transaksi tabungan untuk siswa ini.
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
