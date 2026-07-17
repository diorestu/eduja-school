@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Rencana Kegiatan & Anggaran Sekolah (RKAS)" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            <i class="bx bx-check-circle text-lg"></i>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- Left: Add Budget Category Form -->
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Tambah Kategori RKAS
                </h4>

                <form action="{{ route('bos.anggaran.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Kode Kegiatan / Akun (ARKAS)
                        </label>
                        <input type="text" name="code" placeholder="Contoh: 03.01" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Nama Akun Kegiatan
                        </label>
                        <input type="text" name="name" placeholder="Contoh: Belanja Bahan Habis Pakai (ATK)" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Sumber Dana
                        </label>
                        <div class="relative bg-transparent">
                            <select name="source_funding" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="BOS">BOS (Bantuan Operasional Sekolah)</option>
                                <option value="Yayasan">Yayasan / Mandiri</option>
                                <option value="Komite">Komite Sekolah</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <i class="bx bx-chevron-down text-lg"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Simpan Kategori
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Budget Category List Table -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Daftar Kategori Anggaran Aktif
                </h4>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[500px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kode Akun</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Akun/Kegiatan</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sumber Dana</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($categories as $cat)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-4">
                                            <span class="font-mono font-semibold text-theme-sm text-gray-800 dark:text-white/90">
                                                {{ $cat->code }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-theme-sm text-gray-800 dark:text-white/90">
                                                {{ $cat->name }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-theme-xs font-semibold {{ $cat->source_funding === 'BOS' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' }}">
                                                {{ $cat->source_funding }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-theme-sm {{ $cat->is_active ? 'text-green-600' : 'text-red-500' }}">
                                                {{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">
                                            Belum ada kategori anggaran RKAS. Silakan tambahkan baru di form samping.
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
