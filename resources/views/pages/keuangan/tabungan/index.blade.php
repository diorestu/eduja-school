@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Simpanan / Tabungan Siswa" />

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h4 class="font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Buku Induk Tabungan Siswa
                </h4>
                <p class="text-xs text-gray-400 mt-1">Tahun Ajaran Aktif: {{ $activeYear?->year ?? '-' }}</p>
            </div>
            
            <!-- Search bar -->
            <form action="{{ route('tabungan.index') }}" method="GET" class="w-full sm:w-72">
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari siswa / NIS..."
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent pl-10 pr-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    <span class="absolute top-1/2 left-3.5 -translate-y-1/2 text-gray-400">
                        <i class="bx bx-search text-lg"></i>
                    </span>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[600px]">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                            <th class="px-5 py-3 text-left w-32">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">NIS</p>
                            </th>
                            <th class="px-5 py-3 text-left">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Siswa</p>
                            </th>
                            <th class="px-5 py-3 text-left">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kelas / Rombel</p>
                            </th>
                            <th class="px-5 py-3 text-right">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Saldo Tabungan</p>
                            </th>
                            <th class="px-5 py-3 text-center w-40">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($students as $student)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                <td class="px-5 py-4">
                                    <span class="font-mono text-theme-sm text-gray-800 dark:text-white/90">
                                        {{ $student->nis }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                        {{ $student->name }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                                        {{ $student->schoolClasses->first()?->name ?? 'Belum Ada Kelas' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span class="font-semibold text-theme-sm {{ $student->savings_balance > 0 ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400' }}">
                                        Rp {{ number_format($student->savings_balance, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <a href="{{ route('tabungan.index', ['student_id' => $student->id]) }}"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-brand-500 hover:text-brand-600">
                                        <i class="bx bx-book-open text-base"></i> Buka Buku
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">
                                    Tidak ada data siswa ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
