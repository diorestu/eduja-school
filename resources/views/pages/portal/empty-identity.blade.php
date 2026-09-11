@extends('layouts.app')

@section('content')
    <div class="mx-auto flex min-h-[60vh] max-w-xl items-center justify-center px-4 py-12 text-center">
        <div>
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400"><i class="bx bx-link-alt text-2xl" aria-hidden="true"></i></div>
            <h1 class="mt-5 text-xl font-semibold text-gray-900 dark:text-white">{{ $title ?? 'Data belum terhubung' }}</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $message ?? 'Sistem belum menemukan data yang terhubung ke akun ini.' }}</p>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $action ?? 'Hubungi administrator untuk melanjutkan.' }}</p>
            <a href="{{ route('profile') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-600 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20">Buka profil akun</a>
        </div>
    </div>
@endsection
