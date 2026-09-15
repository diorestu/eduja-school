@extends('layouts.app')
@section('content')
<div class="work work-stack">
    <header class="work-head"><div><h1>Materi belajar saya</h1><p class="work-muted">Catat pertanyaan dan baca kembali materi yang tersimpan.</p></div></header>
    <x-work.feedback />
    <div class="work-split">
        <section class="work-panel">
            <h2>Materi tersimpan</h2>
            <div class="mt-6">
            @forelse($rows as $material)
                <details class="work-row">
                    <summary>{{ $material->title }}</summary>
                    <div class="work-actions"><span class="work-status">{{ $material->status === 'generated' ? 'Jawaban tersedia' : 'Draft pertanyaan' }}</span><time class="work-muted">{{ $material->created_at->format('d M Y, H:i') }}</time></div>
                    <h3 class="mt-4">Pertanyaan</h3><p class="work-copy mt-2">{{ $material->prompt }}</p>
                    @if($material->status === 'generated' && filled($material->response))
                        <h3 class="mt-4">Jawaban</h3><div class="work-copy mt-2">{{ $material->response }}</div>
                    @else
                        <p class="work-notice mt-4">Pertanyaan sudah tersimpan. Jawaban AI belum tersedia.</p>
                    @endif
                </details>
            @empty
                <div class="work-empty"><h3>Belum ada materi tersimpan</h3><p class="work-muted">Simpan pertanyaan pertama Anda melalui formulir di halaman ini.</p></div>
            @endforelse
            </div>
            {{ $rows->links() }}
        </section>
        <aside class="work-panel">
            <h2>Simpan pertanyaan</h2><p class="work-notice mt-4">Tanya AI belum tersedia. Anda tetap dapat menyimpan pertanyaan sebagai draft.</p>
            <form action="{{ route('ai.store') }}" method="POST" class="work-fields mt-6" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">
                @csrf
                <x-work.field name="title" label="Judul materi" required maxlength="160" />
                <x-work.field name="prompt" type="textarea" label="Pertanyaan atau instruksi belajar" required maxlength="10000" rows="8" />
                <x-work.submit label="Simpan draft pertanyaan" />
            </form>
        </aside>
    </div>
</div>
@endsection
