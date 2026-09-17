@extends('layouts.app')
@section('content')
<div class="work work-stack">
    <header class="work-head">
        <div><h1>Pengumuman sekolah</h1><p class="work-muted">{{ app(\App\Services\SchoolContext::class)->activeSchool()?->name }}</p></div>
        <span class="work-status">{{ $unreadCount }} belum dibaca</span>
    </header>
    <x-work.feedback />
    <div @class(['work-split' => $canManage])>
        <section class="work-panel" aria-label="Daftar pengumuman" x-data="{ q: '' }">
            <div class="work-field"><label for="announcement-search">Cari pengumuman</label><input id="announcement-search" type="search" x-model="q" placeholder="Judul atau isi pengumuman"></div>
            <div class="mt-6">
            @forelse($announcements as $announcement)
                <article class="work-row" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())">
                    <div class="work-actions"><span class="work-status">{{ ucfirst($announcement->category) }}</span><span class="work-muted">{{ $announcement->published_at?->translatedFormat('d M Y') }}</span>
                    @unless($announcement->reads->contains(fn ($read) => $read->read_at !== null))<span class="work-status">Belum dibaca</span>@endunless</div>
                    <h2><a class="work-link" href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a></h2>
                    <p class="work-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 180) }}</p>
                    <p class="work-muted">Untuk {{ ['school'=>'seluruh sekolah','teacher'=>'guru','staff'=>'tendik','student'=>'siswa','parent'=>'wali murid'][$announcement->target_type] ?? 'penerima terpilih' }}</p>
                </article>
            @empty
                <div class="work-empty"><h2>Belum ada pengumuman</h2><p class="work-muted">Informasi untuk role Anda di sekolah ini akan tampil di sini.</p></div>
            @endforelse
            </div>
            <p x-cloak x-show="q && !Array.from($el.previousElementSibling.querySelectorAll('article')).some(row => row.textContent.toLowerCase().includes(q.toLowerCase()))" class="work-notice">Tidak ada pengumuman yang cocok. Coba kata lain.</p>
        </section>
        @if($canManage)
        <aside class="work-panel">
            <h2>Terbitkan pengumuman</h2><p class="work-muted mt-2">Pilih penerima dan tulis informasi yang perlu mereka ketahui.</p>
            <form action="{{ route('announcements.store') }}" method="POST" enctype="multipart/form-data" class="work-fields mt-6" x-data="{ saving:false, target: @js(old('target_type', 'school')) }" @submit="if(saving) { $event.preventDefault(); } else { saving=true; }" @pageshow.window="saving=false">
                @csrf
                <x-work.field name="title" label="Judul pengumuman" required maxlength="160" />
                <div class="work-field"><label for="category">Kategori</label><select id="category" name="category">@foreach(['umum'=>'Umum','akademik'=>'Akademik','keuangan'=>'Keuangan','kegiatan'=>'Kegiatan'] as $value=>$label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="work-field"><label for="target_type">Penerima</label><select id="target_type" name="target_type" x-model="target" required>@foreach(['school'=>'Seluruh sekolah','teacher'=>'Guru','staff'=>'Tendik','student'=>'Siswa','parent'=>'Wali murid','person'=>'Satu akun','class'=>'Siswa & wali per kelas','department'=>'Siswa & wali per jurusan','grade'=>'Siswa & wali per tingkat'] as $value=>$label)<option value="{{ $value }}" @selected(old('target_type') === $value)>{{ $label }}</option>@endforeach</select></div>
                @foreach($targetOptions as $kind => $options)
                    <div class="work-field" x-show="target === '{{ $kind }}'" x-cloak>
                        <label for="target-{{ $kind }}">Pilih {{ ['person'=>'akun','class'=>'kelas','department'=>'jurusan','grade'=>'tingkat'][$kind] }}</label>
                        <select id="target-{{ $kind }}" name="target_id" :disabled="target !== '{{ $kind }}'" :required="target === '{{ $kind }}'">
                            <option value="">Pilih penerima</option>
                            @foreach($options as $id=>$name)<option value="{{ $id }}" @selected(old('target_id') == $id)>{{ $name }}</option>@endforeach
                        </select>
                        @error('target_id')<p class="work-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <x-work.field name="body" label="Isi pengumuman" type="textarea" required rows="8" />
                <div class="work-field"><label for="attachment">Lampiran (opsional)</label><input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"><p class="work-muted">PDF, JPG, atau PNG. Maksimal 5 MB.</p>@error('attachment')<p class="work-error">{{ $message }}</p>@enderror</div>
                <p class="work-muted">Pengumuman langsung tersedia di EDUJA setelah diterbitkan.</p>
                <x-work.submit label="Terbitkan pengumuman" busy="Menerbitkan…" />
            </form>
        </aside>
        @endif
    </div>
</div>
@endsection
