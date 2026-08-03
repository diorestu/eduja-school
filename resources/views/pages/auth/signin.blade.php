@extends('layouts.fullscreen-layout')

@section('content')
<style>
/* Hallmark · macrostructure: Split Auth Desk · tone: playful-professional · anchor hue: EDUJA teal */
.auth-page{--auth-paper:#fbf6ed;--auth-ink:#173b3d;--auth-teal:#087f7a;--auth-mint:#d8f1e7;--auth-line:#d8e2da;min-height:100vh;background:var(--auth-paper);color:var(--auth-ink);overflow-x:clip}
.auth-display{font-family:Georgia,'Times New Roman',serif;letter-spacing:-.055em}
.auth-side{background:var(--auth-ink);position:relative;overflow:hidden}
.auth-side:after{content:"";position:absolute;width:320px;height:320px;right:-110px;bottom:-120px;border:1px solid rgba(216,241,231,.22);border-radius:48% 52% 40% 60%;transform:rotate(28deg)}
.auth-input{background:rgba(255,255,255,.55);border:1px solid var(--auth-line);color:var(--auth-ink);transition:border-color 160ms ease,box-shadow 160ms ease,background 160ms ease}
.auth-input:hover{background:#fff;border-color:#b7cbc0}.auth-input:focus{background:#fff;border-color:var(--auth-teal);box-shadow:0 0 0 4px rgba(8,127,122,.12);outline:0}.auth-input:disabled{opacity:.55;cursor:not-allowed}.auth-input[aria-invalid="true"]{border-color:#c85656;box-shadow:0 0 0 4px rgba(200,86,86,.1)}
.auth-button{background:var(--auth-teal);transition:transform 160ms ease,background 160ms ease,box-shadow 160ms ease}.auth-button:hover{background:#06645f;transform:translateY(-1px);box-shadow:0 10px 20px rgba(8,127,122,.18)}.auth-button:active{transform:translateY(1px)}.auth-button:focus-visible,.auth-link:focus-visible{outline:3px solid #f1c85b;outline-offset:3px}.auth-button:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none}
@media(max-width:767px){.auth-side{min-height:260px}.auth-content{padding-top:2rem;padding-bottom:2rem}}@media(prefers-reduced-motion:reduce){.auth-input,.auth-button{transition:none}}
</style>
<div class="auth-page grid min-h-screen md:grid-cols-[minmax(300px,0.78fr)_minmax(420px,1.22fr)]">
    <aside class="auth-side hidden flex-col justify-between px-7 py-8 text-white sm:px-12 md:flex md:px-14 md:py-12">
        <a href="/" aria-label="Kembali ke beranda"><img src="/images/logo/logo-wide-dark.png" alt="EDUJA — Sekolah Jadi Seru" class="h-10 w-auto object-contain brightness-0 invert"></a>
        <div class="relative z-10 max-w-md py-12 md:py-0"><p class="text-sm font-bold tracking-wide text-[#f6d873]">Sekolah Jadi Seru</p><h1 class="auth-display mt-5 text-5xl leading-[.96] sm:text-6xl">Semua urusan sekolah, satu ruang kerja.</h1><p class="mt-6 max-w-sm text-sm leading-7 text-white/65">Masuk untuk melanjutkan pekerjaan akademik, keuangan, presensi, dan koordinasi sekolah Anda.</p></div>
        <p class="relative z-10 text-xs text-white/45">EDUJA · Platform operasional sekolah</p>
    </aside>
    <main class="auth-content flex items-center justify-center px-6 py-10 sm:px-10 md:px-16"><div class="w-full max-w-[430px]">
        <a href="/" class="auth-link mb-10 inline-flex items-center gap-2 text-sm font-semibold text-[var(--auth-ink)]/55 hover:text-[var(--auth-teal)]"><i class="bx bx-arrow-back"></i> Kembali ke beranda</a>
        <div class="mb-8"><p class="text-sm font-bold text-[var(--auth-teal)]">Selamat datang kembali</p><h2 class="auth-display mt-2 text-5xl leading-none">Masuk ke EDUJA</h2><p class="mt-4 text-sm leading-6 text-[var(--auth-ink)]/60">Lanjutkan mengelola sekolah dengan informasi yang lebih terarah.</p></div>
        @if($errors->any())<div class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert"><i class="bx bx-error-circle mt-0.5 text-lg"></i><span>{{ $errors->first() }}</span></div>@endif
        <form action="{{ route('signin') }}" method="POST" class="space-y-5">@csrf
            <div><label for="email" class="mb-2 block text-sm font-bold">Alamat email</label><input class="auth-input w-full rounded-2xl px-4 py-3.5 text-sm" type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="nama@sekolah.sch.id" autocomplete="email"></div>
            <div><div class="mb-2 flex items-center justify-between gap-4"><label for="password" class="block text-sm font-bold">Kata sandi</label><a href="#" class="auth-link whitespace-nowrap text-xs font-bold text-[var(--auth-teal)]">Lupa kata sandi?</a></div><div x-data="{ showPassword: false }" class="relative"><input class="auth-input w-full rounded-2xl px-4 py-3.5 pr-12 text-sm" :type="showPassword ? 'text' : 'password'" id="password" name="password" required placeholder="Masukkan kata sandi" autocomplete="current-password"><button type="button" @click="showPassword = !showPassword" class="auth-link absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-xl text-slate-400 hover:text-[var(--auth-teal)]" :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"><i x-show="!showPassword" class="bx bx-show text-lg"></i><i x-show="showPassword" class="bx bx-hide text-lg" style="display:none"></i></button></div></div>
            <label class="flex select-none items-center gap-2 pt-1 text-sm text-[var(--auth-ink)]/60"><input type="checkbox" name="remember" class="rounded border-slate-300 text-[var(--auth-teal)] focus:ring-0"> Ingat saya di perangkat ini</label>
            <button type="submit" class="auth-button w-full rounded-2xl py-4 text-sm font-bold text-white">Masuk ke dasbor <i class="bx bx-right-arrow-alt ml-1 align-middle text-lg"></i></button>
        </form>
        <p class="mt-8 border-t border-[var(--auth-line)] pt-6 text-center text-sm text-[var(--auth-ink)]/55">Belum memiliki akun? <a href="/signup" class="auth-link font-bold text-[var(--auth-teal)] hover:underline">Daftar akun baru</a></p>
        <button @click="$store.theme.toggle()" class="auth-link fixed bottom-6 right-6 flex h-11 w-11 items-center justify-center rounded-full border border-[var(--auth-line)] bg-white/75 text-[var(--auth-ink)] shadow-sm" aria-label="Ganti tema"><i x-show="$store.theme.theme === 'light'" class="bx bx-moon text-lg"></i><i x-show="$store.theme.theme === 'dark'" class="bx bx-sun text-lg" style="display:none"></i></button>
    </div></main>
</div>
@endsection
