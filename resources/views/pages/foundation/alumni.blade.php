@extends('layouts.app')
@section('content')
<div class="work work-stack">
    <header class="work-head"><div><h1>{{ $isAlumni ? 'Profil alumni saya' : 'Data alumni' }}</h1><p class="work-muted">{{ app(\App\Services\SchoolContext::class)->activeSchool()?->name }}</p></div></header>
    <x-work.feedback />
    @if($editableAlumni)
        <div class="work-split">
        <section class="work-panel">
            <h2>Perbarui kabar Anda</h2><p class="work-muted mt-2">Lengkapi kontak, pendidikan, dan kegiatan Anda setelah lulus.</p>
            <form action="{{ route('alumni.update', $editableAlumni) }}" method="POST" class="work-fields mt-6" x-data="{ saving:false }" @submit="saving=true" @pageshow.window="saving=false">
                @csrf @method('PUT')
                <div class="work-fields work-fields-pair">
                    <x-work.field name="phone" label="Nomor WhatsApp" type="tel" :value="$editableAlumni->phone" maxlength="30" />
                    <x-work.field name="email" label="Email" type="email" :value="$editableAlumni->email" maxlength="160" />
                </div>
                <div class="work-field"><label for="current_status">Kegiatan saat ini (wajib)</label><select id="current_status" name="current_status" required>
                    <option value="">Pilih kegiatan</option>
                    @foreach(array_unique(array_filter(['Lulus','Kuliah','Bekerja','Wirausaha','Mencari kerja','Lainnya', $editableAlumni->current_status])) as $status)
                        <option @selected(old('current_status', $editableAlumni->current_status) === $status)>{{ $status }}</option>
                    @endforeach
                </select></div>
                <x-work.field name="address" label="Alamat domisili" type="textarea" :value="$editableAlumni->address" maxlength="1000" />
                <x-work.field name="education_history" label="Riwayat pendidikan" type="textarea" :value="$editableAlumni->education_history" maxlength="2000" placeholder="Nama institusi, program studi, dan tahun pendidikan" />
                <x-work.field name="current_job" label="Pekerjaan saat ini (jika ada)" :value="$editableAlumni->current_job" maxlength="160" />
                <div><x-work.submit label="Simpan profil" /></div>
            </form>
        </section>
        <aside class="work-panel"><h2>Identitas kelulusan</h2><dl class="work-fields mt-6">
            @foreach(['name'=>'Nama','nisn'=>'NISN','graduation_year'=>'Tahun lulus','department_name'=>'Jurusan'] as $field=>$label)
            <div><dt class="work-muted">{{ $label }}</dt><dd>{{ $editableAlumni->$field ?: 'Belum tercatat' }}</dd></div>
            @endforeach
        </dl><p class="work-muted mt-6">Hubungi sekolah jika data kelulusan perlu diperbaiki.</p></aside>
        </div>
    @elseif($isAlumni)
        <section class="work-panel work-empty"><h2>Profil belum terhubung</h2><p class="work-muted">Hubungi admin sekolah untuk menghubungkan akun Anda dengan data kelulusan.</p></section>
    @else
        <section class="work-panel" x-data="{ q:'' }">
            <div class="work-field"><label for="alumni-search">Cari alumni</label><input type="search" id="alumni-search" x-model="q" placeholder="Nama, angkatan, atau kegiatan"></div>
            <div class="mt-6">
            @forelse($alumni as $person)
                <article class="work-row" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())"><h2>{{ $person->name }}</h2><p class="work-muted">Lulus {{ $person->graduation_year ?: 'belum tercatat' }} · {{ $person->current_status ?: 'Kegiatan belum diisi' }}</p>
                    <details><summary>Detail alumni</summary><dl class="work-fields work-fields-pair">
                    @foreach(['phone'=>'WhatsApp','email'=>'Email','address'=>'Domisili','education_history'=>'Pendidikan','current_job'=>'Pekerjaan'] as $field=>$label)
                        <div><dt class="work-muted">{{ $label }}</dt><dd class="work-copy">{{ $person->$field ?: 'Belum diisi' }}</dd></div>
                    @endforeach
                    </dl></details>
                </article>
            @empty
                <div class="work-empty"><h2>Belum ada alumni</h2><p class="work-muted">Profil akan tersedia setelah sekolah memproses kelulusan siswa.</p></div>
            @endforelse
            </div>
            <p x-cloak x-show="q && !Array.from($el.previousElementSibling.querySelectorAll('article')).some(row => row.textContent.toLowerCase().includes(q.toLowerCase()))" class="work-notice">Alumni tidak ditemukan. Coba nama atau angkatan lain.</p>
        </section>
    @endif
</div>
@endsection
