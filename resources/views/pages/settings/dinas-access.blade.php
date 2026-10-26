@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Izin Keuangan Dinas" />

    @if(session('success'))
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</p>
    @endif

    <section class="max-w-2xl rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Izin keuangan untuk Dinas Pendidikan</h1>
        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $school->name }}</p>
        <p id="dinas-access-description" class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">Kepala sekolah dapat mengizinkan Dinas Pendidikan melihat ringkasan keuangan BOS sekolah ini. Izin hanya untuk melihat dan dapat dicabut kapan saja.</p>

        <form method="POST" action="{{ route('settings.dinas-access.update') }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="dinas_finance_allowed" value="0">
            <label for="dinas-finance-allowed" class="flex min-h-11 cursor-pointer items-center gap-3 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                <input id="dinas-finance-allowed" type="checkbox" name="dinas_finance_allowed" value="1" @checked(old('dinas_finance_allowed', $school->dinas_finance_allowed)) aria-describedby="dinas-access-description"
                    class="h-5 w-5 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                Izinkan Dinas Pendidikan melihat keuangan sekolah ini
            </label>
            @error('dinas_finance_allowed')<p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Simpan izin</button>
        </form>
    </section>
@endsection
