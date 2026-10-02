@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Rekening Sekolah" label="Master Data Keuangan" />
<div class="space-y-6 text-gray-900 dark:text-gray-100">
    @if(session('success'))
        <div role="status" class="rounded-lg border border-success-200 bg-success-50 p-4 text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">{{ session('success') }}</div>
    @endif
    <div>
        <h1 class="text-2xl font-semibold">Rekening Sekolah</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-300">Kelola rekening tunai dan bank untuk sekolah yang sedang aktif.</p>
    </div>
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Rekening Sekolah</h2>
            <div class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($accounts as $account)
                    <article class="py-4 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 break-words">
                                <h3 class="font-semibold">{{ $account->name }}</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $account->type }} · {{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-300">Nominal saat ini</p>
                                <p class="mt-1 break-all text-lg font-semibold tabular-nums">Rp {{ number_format((float) $account->current_balance, 2, ',', '.') }}</p>
                            </div>
                        </div>
                        @if($account->type === 'Bank')
                            <p class="mt-3 break-all text-sm text-gray-600 dark:text-gray-300">{{ $account->bank_name }} · {{ $account->account_number }}</p>
                        @endif
                    </article>
                @empty
                    <p class="py-4 text-gray-600 dark:text-gray-300">Belum ada rekening sekolah. Pilih + Rekening Sekolah untuk mencatat rekening pertama.</p>
                @endforelse
            </div>
        </section>
        <details class="min-w-0 self-start rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900" @if($errors->any()) open @endif>
            <summary class="min-h-11 cursor-pointer rounded-lg py-2 font-semibold text-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300">+ Rekening Sekolah</summary>
            <form method="POST" action="{{ route('finance.accounts.store') }}" class="mt-4 space-y-4" x-data="{ type: @js(old('type', 'Tunai')), saving: false }" @submit="saving = true">
                @csrf
                @if($errors->any())
                    <p role="alert" class="text-error-700 dark:text-error-400">Rekening belum disimpan. Periksa kolom yang ditandai di bawah.</p>
                @endif
                @foreach(['name' => 'Nama', 'type' => 'Jenis', 'bank_name' => 'Nama Bank', 'account_number' => 'Nomor Rekening', 'opening_balance' => 'Nominal saat ini'] as $field => $label)
                    <div @if(in_array($field, ['bank_name', 'account_number'])) x-show="type === 'Bank'" @endif>
                        <label for="account-{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }} <span aria-hidden="true">*</span></label>
                        @if($field === 'type')
                            <select id="account-type" name="type" x-model="type" required class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 focus:outline-2 focus:outline-brand-500 dark:border-gray-600 dark:bg-gray-900">
                                <option value="Tunai" @selected(old('type', 'Tunai') === 'Tunai')>Tunai</option>
                                <option value="Bank" @selected(old('type') === 'Bank')>Bank</option>
                            </select>
                        @else
                            <input id="account-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" type="{{ $field === 'opening_balance' ? 'number' : 'text' }}"
                                @if($field === 'opening_balance') min="0" max="9999999999999.99" step="0.01" inputmode="decimal" aria-describedby="balance-help{{ $errors->has($field) ? ' error-'.$field : '' }}"
                                @else maxlength="{{ $field === 'name' ? 120 : ($field === 'bank_name' ? 80 : 100) }}" @endif
                                @if(in_array($field, ['bank_name', 'account_number'])) :required="type === 'Bank'" :disabled="type !== 'Bank'" @else required @endif
                                @if($errors->has($field)) aria-invalid="true" @if($field !== 'opening_balance') aria-describedby="error-{{ $field }}" @endif @endif
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 focus:outline-2 focus:outline-brand-500 dark:border-gray-600 dark:bg-gray-900">
                        @endif
                        @if($field === 'opening_balance')<p id="balance-help" class="mt-2 text-sm text-gray-600 dark:text-gray-300">Dalam rupiah. Nominal ini menjadi saldo awal rekening.</p>@endif
                        @error($field)<p id="error-{{ $field }}" class="mt-2 text-sm text-error-700 dark:text-error-400">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <button type="submit" :disabled="saving" class="min-h-11 rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50" x-text="saving ? 'Menyimpan…' : 'Simpan'">Simpan</button>
            </form>
        </details>
    </div>
</div>
@endsection
