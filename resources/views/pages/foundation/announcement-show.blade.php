@extends('layouts.app')
@section('content')
<div class="work work-stack max-w-3xl mx-auto">
    <a class="work-link" href="{{ route('announcements.index') }}">Kembali ke pengumuman</a>
    <x-work.feedback />
    <article class="work-panel">
        <div class="work-actions mb-4"><span class="work-status">{{ ucfirst($announcement->category) }}</span><time class="work-muted">{{ $announcement->published_at?->translatedFormat('d M Y, H:i') }}</time></div>
        <h1>{{ $announcement->title }}</h1>
        <div class="work-copy mt-6">{{ $announcement->body }}</div>
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
