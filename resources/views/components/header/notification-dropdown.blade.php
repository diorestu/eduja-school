<div class="relative" x-data="{ open:false }" @click.outside="open=false" @keydown.escape.window="open=false">
    <button type="button" class="work-btn relative" aria-label="Notifikasi sekolah" :aria-expanded="open.toString()" aria-controls="school-notifications" @click="open=!open">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        @if($schoolNotifications->isNotEmpty())<span>{{ $schoolNotifications->count() }}</span>@endif
    </button>
    <section id="school-notifications" x-show="open" x-cloak class="work work-panel absolute right-0 top-full mt-2 z-50 w-[min(20rem,calc(100vw-2rem))]" aria-label="Notifikasi sekolah">
        <h2>Notifikasi sekolah</h2>
        <div class="max-h-80 overflow-y-auto">
        @forelse($schoolNotifications as $item)
            <a class="work-row work-link" href="{{ $item['url'] }}">{{ $item['title'] }}</a>
        @empty
            <p class="work-muted mt-4">Tidak ada pengumuman baru atau permohonan yang perlu Anda tinjau.</p>
        @endforelse
        </div>
        <button type="button" class="work-btn mt-4" @click="open=false">Tutup</button>
    </section>
</div>
