@extends('layouts.app')
@section('content')
<div class="work work-stack max-w-3xl mx-auto">
    <a class="work-link" href="{{ route('announcements.index') }}">Kembali ke pengumuman</a>
    <x-work.feedback />
    <article class="work-panel">
        <div class="work-actions mb-4"><span class="work-status">{{ ucfirst($announcement->category) }}</span><time class="work-muted">{{ $announcement->published_at?->translatedFormat('d M Y, H:i') }}</time></div>
        <h1>{{ $announcement->title }}</h1>
        <div class="work-copy mt-6">{{ $announcement->body }}</div>
        @if($announcement->attachment_path)
            <a class="work-link mt-4" href="{{ route('announcements.attachment', $announcement) }}">Unduh {{ $announcement->attachment_name ?: 'lampiran' }}</a>
        @endif
        <div class="work-actions mt-6">
            <form method="POST" action="{{ route('announcements.bookmark', $announcement) }}" x-data="{ saving:false }" @submit="saving=true">
                @csrf<input type="hidden" name="saved" value="{{ $bookmarked ? 0 : 1 }}">
                <x-work.submit :label="$bookmarked ? 'Hapus penanda' : 'Simpan penanda'" />
            </form>
            <div x-data="{ copied:false, failed:false }">
                <button type="button" class="work-btn" @click="navigator.clipboard.writeText(window.location.href).then(() => { copied=true; failed=false; }).catch(() => { failed=true; })" x-text="copied ? 'Tautan disalin' : 'Salin tautan'">Salin tautan</button>
                <p x-show="failed" x-cloak role="alert" class="work-error">Tautan gagal disalin. Salin alamat dari browser.</p>
            </div>
        </div>
        <p class="work-muted mt-3">Tautan hanya dapat dibuka oleh penerima yang memiliki akses.</p>
        <div class="mt-8">
        @if($readAt)
            <p class="work-notice" role="status">Sudah dibaca pada {{ \Carbon\Carbon::parse($readAt)->translatedFormat('d M Y, H:i') }}.</p>
        @else
            <form action="{{ route('announcements.read', $announcement) }}" method="POST" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">@csrf<x-work.submit label="Tandai sudah dibaca" /></form>
        @endif
        </div>
    </article>
</div>
@endsection
