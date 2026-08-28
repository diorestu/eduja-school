@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Permission Role" />

    @if (session('success'))
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-400">
            <i class="bx bxs-check-circle text-lg"></i>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-500">Akses per sekolah</p>
                <h1 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">Permission Tambahan</h1>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Berikan akses menu tambahan sesuai pembagian tugas di sekolah aktif. Akses bawaan role tetap aktif dan tidak dapat dinonaktifkan dari halaman ini.
                </p>
            </div>

            <form method="GET" action="{{ route('settings.permissions.index') }}" class="w-full lg:w-72">
                <label for="permission-role" class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih role</label>
                <select id="permission-role" name="role" onchange="this.form.submit()"
                    class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-800 outline-none transition focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    @foreach ($roles as $role => $label)
                        <option value="{{ $role }}" @selected($selectedRole === $role)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.permissions.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="role" value="{{ $selectedRole }}">

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach ($catalog as $group)
                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500/10 text-brand-600 dark:text-brand-400">
                            {!! \App\Helpers\MenuHelper::getIconSvg($group['icon']) !!}
                        </span>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $group['name'] }}</h2>
                    </div>

                    <div class="space-y-2">
                        @foreach ($group['items'] as $item)
                            @php
                                $isDefault = in_array($item['permission'], $defaultPermissions, true);
                                $isGranted = in_array($item['permission'], $grantedPermissions, true);
                            @endphp
                            <label class="flex items-center justify-between gap-4 rounded-xl border px-3 py-3 transition {{ $isDefault ? 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60' : 'cursor-pointer border-gray-200 hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-800 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5' }}">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $item['name'] }}</span>
                                    <span class="mt-0.5 block truncate text-[11px] text-gray-400">{{ $item['permission'] }}</span>
                                </span>

                                @if ($isDefault)
                                    <span class="shrink-0 rounded-full bg-gray-200 px-2.5 py-1 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">Bawaan</span>
                                @else
                                    <input type="checkbox" name="permissions[]" value="{{ $item['permission'] }}" @checked($isGranted)
                                        class="h-5 w-5 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                                @endif
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="sticky bottom-4 z-20 mt-5 flex justify-end rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 text-sm font-semibold text-white transition hover:bg-brand-600 focus:outline-none focus:ring-3 focus:ring-brand-500/20">
                <i class="bx bx-save text-lg"></i>
                Simpan Permission
            </button>
        </div>
    </form>
@endsection
