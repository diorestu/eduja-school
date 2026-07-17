@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pilih Sekolah" />

    <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-2xl">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-500">Multi-School Context</p>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white/90">Pilih sekolah aktif</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                Semua menu operasional akan membaca role dan data berdasarkan sekolah yang dipilih.
            </p>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($schools as $school)
                <form action="{{ route('school.switch') }}" method="POST" class="rounded-lg border border-gray-200 bg-gray-50 p-4 transition hover:border-brand-300 hover:bg-white dark:border-gray-800 dark:bg-gray-900/40 dark:hover:border-brand-700">
                    @csrf
                    <input type="hidden" name="school_id" value="{{ $school->id }}">
                    <div class="min-h-24">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white/90">{{ $school->name }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $school->npsn ?? 'NPSN belum diisi' }}</p>
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $school->level }} · {{ $school->city ?? 'Kota belum diisi' }}
                        </p>
                    </div>
                    <button type="submit"
                        class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 active:bg-brand-700">
                        Aktifkan
                    </button>
                </form>
            @empty
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    Akun ini belum punya afiliasi sekolah. Hubungi admin untuk mengisi school_user_roles.
                </div>
            @endforelse
        </div>
    </section>
@endsection
