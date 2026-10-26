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
        @if((int) $announcement->created_by === auth()->id() || auth()->user()->hasRole(['kepsek','tu','staf_tu']))
        <details class="mt-6"><summary>Ubah pengumuman</summary><form method="POST" action="{{ route('announcements.update',$announcement) }}" class="work-fields">@csrf @method('PUT')
            <x-work.field name="title" label="Judul" :value="$announcement->title" required maxlength="160" />
            <x-work.field name="category" label="Kategori" :value="$announcement->category" maxlength="40" />
            <label>Isi pengumuman<textarea name="body" required>{{ $announcement->body }}</textarea></label><button class="work-btn">Simpan perubahan</button>
        </form></details>
        <form method="POST" action="{{ route('announcements.destroy',$announcement) }}" class="mt-3">@csrf @method('DELETE')<button class="work-btn">Hapus pengumuman</button></form>
        @endif
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
