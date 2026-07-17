@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Data GTK (Guru & Tenaga Kependidikan)" />

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
                    Tambah GTK Baru
                </h4>

                <form action="{{ route('guru.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Nama Lengkap & Gelar
                        </label>
                        <input type="text" name="name" placeholder="Contoh: Budi Santoso, S.Pd." required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            NIP (Nomor Induk Pegawai)
                        </label>
                        <input type="text" name="nip" placeholder="Contoh: 198001012010011001"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            NUPTK
                        </label>
                        <input type="text" name="nuptk" placeholder="Contoh: 1234567890123001"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Jabatan / Peran
                        </label>
                        <div class="relative bg-transparent">
                            <select name="role_type" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="Guru">Guru</option>
                                <option value="Staf TU">Staf TU</option>
                                <option value="Kepala Sekolah">Kepala Sekolah</option>
                                <option value="Bendahara">Bendahara</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Tipe Kepegawaian
                        </label>
                        <div class="relative bg-transparent">
                            <select name="staff_type" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="PNS">PNS</option>
                                <option value="PPPK">PPPK</option>
                                <option value="Honorer">Honorer</option>
                                <option value="Tetap Yayasan">Tetap Yayasan</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            No. Telepon
                        </label>
                        <input type="text" name="phone" placeholder="Contoh: 0812345678"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Simpan GTK
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Daftar GTK Aktif
                </h4>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[600px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Pegawai</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">NIP / NUPTK</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jabatan</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tipe Kepegawaian</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($teachers as $teacher)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-4">
                                            <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $teacher->name }}</span>
                                            <span class="block text-xs text-gray-400">{{ $teacher->phone ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="block text-gray-800 text-theme-sm dark:text-white/90">NIP: {{ $teacher->nip ?? '-' }}</span>
                                            <span class="block text-xs text-gray-400">NUPTK: {{ $teacher->nuptk ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                {{ $teacher->role_type }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                {{ $teacher->staff_type }}
                                            </span>
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
