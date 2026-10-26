@extends('layouts.app')
@section('content')
<div class="work work-stack">
    <header class="work-head"><div><h1>Materi belajar</h1><p class="work-muted">Materi sekolah dan kelas yang dapat Anda akses.</p></div></header>
    <x-work.feedback />
    <section class="work-panel"><form method="GET" class="work-actions"><label for="teacher-filter">Guru</label><select id="teacher-filter" name="teacher_id"><option value="">Semua guru</option>@foreach($authors as $author)<option value="{{ $author->id }}" @selected(request('teacher_id') == $author->id)>{{ $author->name }}</option>@endforeach</select><button class="work-btn">Tampilkan</button></form></section>
    @foreach($materials as $material)
    <article class="work-panel"><h2>{{ $material->title }}</h2><p class="work-muted">{{ $material->author?->name }} · {{ $material->schoolClass?->name ?: 'Seluruh sekolah' }}</p><p class="work-copy mt-3">{{ $material->description }}</p><a class="work-link" href="{{ route('materials.link',$material) }}">Unduh {{ $material->file_name }}</a>
        @if(auth()->id() === (int) $material->user_id || auth()->user()->hasRole(['kepsek','tu','staf_tu']))
        <details class="mt-4"><summary>Ubah materi</summary><form method="POST" action="{{ route('materials.update',$material) }}" enctype="multipart/form-data" class="work-fields">@csrf @method('PUT')
            <x-work.field name="title" label="Judul" :value="$material->title" required maxlength="160" /><label>Deskripsi<textarea name="description" maxlength="10000">{{ $material->description }}</textarea></label>
            <label>Kelas<select name="school_class_id"><option value="">Seluruh sekolah</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected($class->id === $material->school_class_id)>{{ $class->name }}</option>@endforeach</select></label>
            <label>Ganti berkas (opsional)<input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"></label><button class="work-btn">Simpan perubahan</button>
        </form></details>
        <form method="POST" action="{{ route('materials.destroy',$material) }}" class="mt-3">@csrf @method('DELETE')<button class="work-btn">Hapus materi</button></form>
        @endif
    </article>
    @endforeach
    @if($materials->isEmpty())<div class="work-empty">Belum ada materi yang dapat diakses.</div>@endif
    @if(auth()->user()->hasRole(['guru','wali_kelas','kepsek','tu','staf_tu']))
    <section class="work-panel"><h2>Simpan materi</h2><form method="POST" action="{{ route('materials.store') }}" enctype="multipart/form-data" class="work-fields">@csrf
        <x-work.field name="title" label="Judul materi" required maxlength="160" /><label>Deskripsi<textarea name="description" maxlength="10000">{{ old('description') }}</textarea></label>
        <label>Kelas penerima<select name="school_class_id"><option value="">Seluruh sekolah</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
        <label>Berkas materi<input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" required></label><p class="work-muted">Maksimal 10 MB. Tautan unduh berlaku lima menit dan tetap memerlukan akses penerima.</p><button class="work-btn">Simpan materi</button>
    </form></section>
    @endif
</div>
@endsection
