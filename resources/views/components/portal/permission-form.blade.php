@props(['student'])
@if(session('success'))<p class="portal-status mt-4" role="status">{{ session('success') }}</p>@endif
@error('permission')<p class="portal-feedback is-visible" role="alert">{{ $message }}</p>@enderror
<form action="{{ route('portal.siswa.permission') }}" method="POST" enctype="multipart/form-data" class="portal-form" x-data="{ saving:false }" @submit="saving=true">
    @csrf<input type="hidden" name="student_id" value="{{ $student->id }}">
    <div class="portal-field"><label for="request_type">Jenis pengajuan</label><select id="request_type" name="request_type" required><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="dispensasi">Dispensasi</option></select></div>
    <div class="portal-grid portal-grid--two"><div class="portal-field"><label for="start_date">Tanggal mulai</label><input id="start_date" name="start_date" type="date" min="{{ today()->toDateString() }}" required value="{{ old('start_date', today()->toDateString()) }}"></div><div class="portal-field"><label for="end_date">Tanggal selesai</label><input id="end_date" name="end_date" type="date" min="{{ today()->toDateString() }}" required value="{{ old('end_date', today()->toDateString()) }}"></div></div>
    <div class="portal-field"><label for="reason">Alasan</label><textarea id="reason" name="reason" required maxlength="1000">{{ old('reason') }}</textarea></div>
    <div class="portal-field"><label for="attachment">Dokumen pendukung (opsional)</label><input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"><p class="portal-muted">PDF, JPG, atau PNG. Maksimal 5 MB.</p></div>
    <button class="portal-action portal-action--primary" type="submit" :disabled="saving" x-text="saving ? 'Mengirim…' : 'Kirim pengajuan'">Kirim pengajuan</button>
</form>
