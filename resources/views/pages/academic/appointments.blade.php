<details class="mt-3 border-t pt-3 text-xs">
    <summary class="cursor-pointer font-medium">Jabatan dan riwayat SK</summary>
    @php($currentAppointment = $history->firstWhere('ends_on', null))
    @foreach($history as $appointment)
    <p class="my-2">{{ $appointmentRoles[$appointment->role] ?? $appointment->role }} · {{ $appointment->appointment_number }}<br>{{ $appointment->starts_on }} — {{ $appointment->ends_on ?? 'berjalan' }}<br>{{ $appointment->appointing_institution }}
        @if(Route::has('guru.document'))<a class="underline" href="{{ route('guru.document', ['teacher' => $teacher->id, 'record' => $appointment->id, 'kind' => 'appointment']) }}">Unduh SK</a>@endif
    </p>
    @endforeach
    @if($currentAppointment)
    <form action="{{ route('guru.store') }}" method="POST" class="mt-2 space-y-2">@csrf
        <input type="hidden" name="action" value="end_appointment"><input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
        <label class="block">TMT akhir jabatan<input type="date" name="ends_on" min="{{ $currentAppointment->starts_on }}" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        <button class="rounded border px-2 py-1">Akhiri jabatan</button>
    </form>
    @elseif($staffRoleMemberships->get($teacher->user_id, collect())->isNotEmpty())
    <form action="{{ route('guru.store') }}" method="POST" class="mt-2 space-y-2">@csrf
        <input type="hidden" name="action" value="end_appointment"><input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
        <p>Role yang sudah ada harus diakhiri sebelum pengangkatan baru.</p>
        <label>Role<select name="role" required class="block w-full rounded border p-1 dark:bg-gray-900">@foreach($staffRoleMemberships->get($teacher->user_id) as $membership)<option value="{{ $membership->role }}">{{ $appointmentRoles[$membership->role] }}</option>@endforeach</select></label>
        <label>TMT akhir jabatan<input type="date" name="ends_on" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        <button class="rounded border px-2 py-1">Akhiri role lama</button>
    </form>
    @elseif($teacher->is_active)
    <form action="{{ route('guru.store') }}" method="POST" enctype="multipart/form-data" class="mt-2 space-y-2">@csrf
        <input type="hidden" name="action" value="appoint"><input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
        <label class="block">Jabatan baru<select name="role" required class="block w-full rounded border p-1 dark:bg-gray-900">@foreach($appointmentRoles as $role => $label)<option value="{{ $role }}">{{ $label }}</option>@endforeach</select></label>
        <label class="block">Nomor SK<input name="appointment_number" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        <label class="block">TMT pengangkatan<input type="date" name="starts_on" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        <label class="block">Lembaga pengangkat<input name="appointing_institution" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        <label class="block">Scan SK (PDF, maks. 5 MB)<input type="file" name="appointment_document" accept="application/pdf" required class="block w-full"></label>
        <button class="rounded border px-2 py-1">Simpan pengangkatan</button>
    </form>
    @endif
</details>
