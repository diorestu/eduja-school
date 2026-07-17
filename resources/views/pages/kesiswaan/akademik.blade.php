@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Tahun Akademik" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg border border-green-200 bg-green-50 p-4 text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500">
            <span class="font-medium text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Form Tambah -->
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Tambah Tahun Akademik
                </h4>

                <form action="{{ route('akademik.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Tahun Ajaran
                        </label>
                        <input type="text" name="year" placeholder="Contoh: 2026/2027" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Semester
                        </label>
                        <div class="relative bg-transparent">
                            <select name="semester" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                            class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-900" />
                        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-400">
                            Aktifkan Sekarang
                        </label>
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Simpan
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Daftar Tahun Akademik
                </h4>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[500px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tahun Ajaran</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Semester</p>
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
                                @foreach($academicYears as $year)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-4">
                                            <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $year->year }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $year->semester }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($year->is_active)
                                                <span class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-500/15 dark:text-green-500">
                                                    Aktif
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                    Tidak Aktif
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            @if(!$year->is_active)
                                                <form action="{{ route('akademik.toggle', $year->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-brand-500 hover:text-brand-600">
                                                        Aktifkan
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400">Sedang Aktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
