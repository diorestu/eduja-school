@extends('layouts.app')
@section('content')
<div class="work work-stack">
    <header class="work-head"><div><h1>{{ $isAlumni ? 'Profil alumni saya' : 'Data alumni' }}</h1><p class="work-muted">{{ app(\App\Services\SchoolContext::class)->activeSchool()?->name }}</p></div></header>
    <x-work.feedback />
    @if($editableAlumni)
        <div class="work-split">
        <section class="work-panel">
            <h2>Perbarui kabar Anda</h2><p class="work-muted mt-2">Lengkapi kontak, pendidikan, dan kegiatan Anda setelah lulus.</p>
            <form action="{{ route('alumni.update', $editableAlumni) }}" method="POST" class="work-fields mt-6" x-data="{ saving:false, status: @js(old('current_status', in_array($editableAlumni->current_status, \App\Models\Alumni::CURRENT_STATUSES, true) ? $editableAlumni->current_status : '')), entries: @js(old('education_entries', $editableAlumni->educationEntries->where('source', 'manual')->map(fn ($row) => $row->only(['institution_name','department','entry_year','graduation_year']))->values()->all())) }" @submit="saving=true" @pageshow.window="saving=false">
                @csrf @method('PUT')
                <div class="work-fields work-fields-pair">
                    <x-work.field name="phone" label="Nomor HP" type="tel" :value="$editableAlumni->phone" maxlength="30" />
                    <x-work.field name="email" label="Email" type="email" :value="$editableAlumni->email" maxlength="160" />
                </div>
                <div class="work-field"><label for="current_status">Kegiatan saat ini (wajib)</label><select id="current_status" name="current_status" x-model="status" required>
                    <option value="">Pilih kegiatan</option>
                    @foreach(\App\Models\Alumni::CURRENT_STATUSES as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select></div>
                <x-work.field name="address" label="Alamat domisili" type="textarea" :value="$editableAlumni->address" maxlength="1000" />
                <div class="work-fields work-fields-pair">
                    <x-work.field name="village" label="Desa/Kelurahan" :value="$editableAlumni->village" maxlength="150" />
                    <x-work.field name="city" label="Kabupaten/Kota" :value="$editableAlumni->city" maxlength="150" />
                    <x-work.field name="province" label="Provinsi" :value="$editableAlumni->province" maxlength="150" />
                </div>
                <section class="work-fields">
                    <h3>Riwayat pendidikan</h3>
                    <p class="work-muted">Data sekolah yang tercatat di EDUJA diisi otomatis. Tambahkan pendidikan di sekolah atau kampus lain di bawahnya.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm"><caption class="sr-only">Pendidikan dari data sekolah</caption><thead><tr><th class="p-2">Sekolah/kampus</th><th class="p-2">Jurusan</th><th class="p-2">Masuk</th><th class="p-2">Lulus</th></tr></thead><tbody>
                        @foreach($editableAlumni->educationEntries->where('source', 'school') as $education)
                            <tr><td class="p-2">{{ $education->institution_name }} <span class="work-muted">(otomatis)</span></td><td class="p-2">{{ $education->department ?: '—' }}</td><td class="p-2">{{ $education->entry_year ?: 'Belum tercatat' }}</td><td class="p-2">{{ $education->graduation_year ?: 'Belum tercatat' }}</td></tr>
                        @endforeach
                        </tbody></table>
                    </div>
                    <input type="hidden" name="education_entries_present" value="1">
                    <template x-for="(entry, index) in entries" :key="index">
                        <fieldset class="work-fields border-t border-gray-200 py-4 dark:border-gray-700">
                            <legend class="text-sm font-medium" x-text="'Pendidikan tambahan ' + (index + 1)"></legend>
                            <div class="work-fields work-fields-pair">
                                <div class="work-field"><label :for="'education-name-' + index">Nama sekolah/kampus (wajib)</label><input :id="'education-name-' + index" :name="'education_entries[' + index + '][institution_name]'" x-model="entry.institution_name" required maxlength="255"></div>
                                <div class="work-field"><label :for="'education-department-' + index">Jurusan</label><input :id="'education-department-' + index" :name="'education_entries[' + index + '][department]'" x-model="entry.department" maxlength="255"></div>
                                <div class="work-field"><label :for="'education-entry-' + index">Tahun masuk (wajib)</label><input :id="'education-entry-' + index" type="number" min="1900" max="9999" :name="'education_entries[' + index + '][entry_year]'" x-model="entry.entry_year" required></div>
                                <div class="work-field"><label :for="'education-graduation-' + index">Tahun lulus (jika sudah)</label><input :id="'education-graduation-' + index" type="number" :min="entry.entry_year || 1900" max="9999" :name="'education_entries[' + index + '][graduation_year]'" x-model="entry.graduation_year"></div>
                            </div>
                            <button type="button" class="work-btn justify-self-start" @click="entries.splice(index, 1)" :aria-label="'Hapus pendidikan tambahan ' + (index + 1)">Hapus pendidikan</button>
                        </fieldset>
                    </template>
                    <button type="button" class="work-btn justify-self-start" @click="entries.push({institution_name:'', department:'', entry_year:'', graduation_year:''})">Tambah riwayat pendidikan</button>
                    @if($editableAlumni->education_history)<p class="work-muted">Catatan pendidikan sebelumnya: {{ $editableAlumni->education_history }}</p>@endif
                </section>
                <div x-cloak x-show="['Bekerja','Kuliah sambil bekerja','Wirausaha'].includes(status)">
                    <x-work.field name="current_job" label="Pekerjaan saat ini" :value="$editableAlumni->current_job" maxlength="160" x-bind:required="['Bekerja','Kuliah sambil bekerja','Wirausaha'].includes(status)" />
                </div>
                <p class="work-muted" x-show="status && !['Bekerja','Kuliah sambil bekerja','Wirausaha'].includes(status)">Pekerjaan saat ini: <span x-text="status === 'Melanjutkan Pendidikan' ? 'Pelajar' : (status === 'Kuliah' ? 'Mahasiswa' : '—')"></span></p>
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
                    @foreach(['phone'=>'WhatsApp','email'=>'Email','address'=>'Alamat','village'=>'Desa/Kelurahan','city'=>'Kabupaten/Kota','province'=>'Provinsi','education_history'=>'Catatan pendidikan','current_job'=>'Pekerjaan'] as $field=>$label)
                        <div><dt class="work-muted">{{ $label }}</dt><dd class="work-copy">{{ $person->$field ?: 'Belum diisi' }}</dd></div>
                    @endforeach
                    </dl>
                    @if($person->educationEntries->isNotEmpty())
                        <div class="overflow-x-auto mt-4"><table class="w-full text-left text-sm"><caption class="text-left font-medium">Riwayat pendidikan</caption><thead><tr><th class="p-2">Sekolah/kampus</th><th class="p-2">Jurusan</th><th class="p-2">Masuk</th><th class="p-2">Lulus</th></tr></thead><tbody>
                        @foreach($person->educationEntries as $entry)<tr><td class="p-2">{{ $entry->institution_name }}</td><td class="p-2">{{ $entry->department ?: '—' }}</td><td class="p-2">{{ $entry->entry_year ?: '—' }}</td><td class="p-2">{{ $entry->graduation_year ?: '—' }}</td></tr>@endforeach
                        </tbody></table></div>
                    @endif
                    @if(! $person->user_id && $linkableUsers->isNotEmpty())
                        <form method="POST" action="{{ route('alumni.link', $person) }}" class="work-fields mt-4" x-data="{ saving:false }" @submit="saving=true">
                            @csrf
                            <div class="work-field"><label for="link-{{ $person->id }}">Hubungkan dengan akun alumni</label><select id="link-{{ $person->id }}" name="user_id" required><option value="">Pilih akun yang identitasnya telah diperiksa</option>@foreach($linkableUsers as $account)<option value="{{ $account->id }}">{{ $account->name }} ({{ $account->email }})</option>@endforeach</select></div>
                            <p class="work-muted">Pastikan nama dan email sesuai pemilik profil. Akun akan dapat memperbarui profil ini.</p>
                            <x-work.submit label="Hubungkan akun" />
                        </form>
                    @endif
                    </details>
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
