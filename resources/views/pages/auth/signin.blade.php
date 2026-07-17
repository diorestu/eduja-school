@extends('layouts.fullscreen-layout')

@section('content')
<div class="min-h-screen flex flex-col justify-center items-center p-6 bg-gray-50 dark:bg-[#08080c] transition-colors duration-500">
    
    <!-- Top Back Link -->
    <div class="mb-6 w-full max-w-md">
        <a href="/" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors">
            <i class="bx bx-left-arrow-alt text-lg"></i> Kembali ke Halaman Utama
        </a>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-md bg-white dark:bg-[#12121a] border border-gray-200 dark:border-white/5 rounded-2xl shadow-xl p-8 transition-colors duration-500">
        <div class="text-center mb-8">
            <a href="/" class="inline-block mb-4">
                <span class="w-10 h-10 bg-brand-500 rounded-xl flex items-center justify-center text-white text-xl font-bold shadow-xs">E</span>
            </a>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Masuk ke Eduja</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sistem Manajemen Operasional & Keuangan Sekolah</p>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-xl bg-red-500/10 border border-red-500/20 p-4 text-xs text-red-600 dark:text-red-400 flex items-center gap-2">
                <i class="bx bx-error-circle text-lg"></i> {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('signin') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Alamat Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="nama@sekolah.sch.id"
                    class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 px-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all" />
            </div>

            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kata Sandi</label>
                    <a href="#" class="text-xs text-brand-500 hover:text-brand-600">Lupa kata sandi?</a>
                </div>
                <div x-data="{ showPassword: false }" class="relative">
                    <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required placeholder="Masukkan kata sandi"
                        class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 pr-11 pl-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all" />
                    <span @click="showPassword = !showPassword" class="absolute top-1/2 right-4 -translate-y-1/2 cursor-pointer text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i x-show="!showPassword" class="bx bx-show text-lg"></i>
                        <i x-show="showPassword" class="bx bx-hide text-lg" style="display: none;"></i>
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center text-xs text-gray-500 dark:text-gray-400 cursor-pointer select-none">
                    <input type="checkbox" name="remember" class="rounded-md border-gray-300 dark:border-gray-700 bg-transparent text-brand-500 mr-2 focus:ring-0 focus:ring-offset-0" />
                    Ingat saya di perangkat ini
                </label>
            </div>

            <button type="submit" class="w-full rounded-full bg-brand-500 py-3.5 text-center text-sm font-semibold text-white hover:bg-brand-600 transition-all shadow-md mt-6">
                Masuk Dasbor
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-100 dark:border-white/5 text-center text-xs text-gray-500">
            Belum memiliki akun? <a href="/signup" class="text-brand-500 font-semibold hover:text-brand-600">Daftar Akun Baru</a>
        </div>
    </div>

    <!-- Theme Toggler (Floating) -->
    <div class="fixed right-6 bottom-6 z-50">
        <button @click="$store.theme.toggle()" class="w-12 h-12 flex items-center justify-center rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-white/10 shadow-lg hover:scale-105 transition-transform text-gray-700 dark:text-gray-300">
            <i x-show="$store.theme.theme === 'light'" class="bx bx-moon text-xl"></i>
            <i x-show="$store.theme.theme === 'dark'" class="bx bx-sun text-xl" style="display: none;"></i>
        </button>
    </div>
</div>
@endsection
