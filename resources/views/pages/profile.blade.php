@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="User Profile" />

    @if(session('success'))
        <div class="mb-6 rounded-xl bg-green-500/10 border border-green-500/20 p-4 text-xs sm:text-sm text-green-600 dark:text-green-400 flex items-center gap-2">
            <i class="bx bxs-check-circle text-lg"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-xl bg-red-500/10 border border-red-500/20 p-4 text-xs sm:text-sm text-red-600 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">Profile</h3>
        <x-profile.profile-card />
        <x-profile.personal-info-card />
        <x-profile.address-card />
    </div>
@endsection
