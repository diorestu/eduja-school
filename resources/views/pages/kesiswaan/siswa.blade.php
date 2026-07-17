@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Data Siswa" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg border border-green-200 bg-green-50 p-4 text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500">
            <span class="font-medium text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
        <!-- Form Tambah -->
        <div class="xl:col-span-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Registrasi Siswa Baru
                </h4>

                <form action="{{ route('siswa.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            NIS (Nomor Induk Siswa)
                        </label>
                        <input type="text" name="nis" placeholder="Contoh: 10024" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            NISN (Nasional)
                        </label>
                        <input type="text" name="nisn" placeholder="Contoh: 0098765432"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Nama Lengkap
                        </label>
                        <input type="text" name="name" placeholder="Nama lengkap siswa" required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Jenis Kelamin
                        </label>
                        <div class="relative bg-transparent">
                            <select name="gender" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="L">Laki-laki (L)</option>
                                <option value="P">Perempuan (P)</option>
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
                            Kelas (Rombel Aktif)
                        </label>
                        <div class="relative bg-transparent">
                            <select name="school_class_id" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="">Pilih Kelas</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}">
                                        {{ $class->name }} ({{ $class->academicYear->year }} - {{ $class->academicYear->semester }})
                                    </option>
                                @endforeach
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
                            Nama Orang Tua / Wali
                        </label>
                        <input type="text" name="parent_name" placeholder="Nama ayah/ibu/wali"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            No. Telepon Orang Tua
                        </label>
                        <input type="text" name="parent_phone" placeholder="Contoh: 0812345678"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Daftarkan Siswa
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar -->
        <div class="xl:col-span-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-5 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Daftar Siswa Aktif
                </h4>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[800px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">NIS / NISN</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kelas (Tahun Ajaran)</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jenis Kelamin</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Orang Tua / Wali</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($students as $student)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-4">
                                            <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $student->name }}</span>
                                            <span class="block text-xs text-gray-400">{{ $student->phone ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="block text-gray-800 text-theme-sm dark:text-white/90">NIS: {{ $student->nis }}</span>
                                            <span class="block text-xs text-gray-400">NISN: {{ $student->nisn ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($student->schoolClasses->isNotEmpty())
                                                @php $activeClass = $student->schoolClasses->first(); @endphp
                                                <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                    {{ $activeClass->name }}
                                                </span>
                                                <span class="block text-xs text-gray-400">
                                                    {{ $activeClass->academicYear->year }} - {{ $activeClass->academicYear->semester }}
                                                </span>
                                            @else
                                                <span class="text-xs text-red-500 font-medium">Belum Masuk Kelas</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                {{ $student->gender == 'L' ? 'Laki-laki' : 'Perempuan' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="block text-gray-800 text-theme-sm dark:text-white/90">{{ $student->parent_name ?? '-' }}</span>
                                            <span class="block text-xs text-gray-400">{{ $student->parent_phone ?? '-' }}</span>
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
