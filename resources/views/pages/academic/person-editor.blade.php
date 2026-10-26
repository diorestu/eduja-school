<details class="mt-3 text-xs">
    <summary class="cursor-pointer font-medium">Ubah data dan akun</summary>
    <form method="POST" action="{{ route($personType === 'student' ? 'siswa.store' : 'guru.store') }}" class="mt-2 grid gap-2">
        @csrf<input type="hidden" name="action" value="update"><input type="hidden" name="{{ $personType }}_id" value="{{ $person->id }}">
        <label>Nama<input name="name" value="{{ $person->name }}" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        @if($personType === 'student')
            <label>NIS<input name="nis" value="{{ $person->nis }}" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
            <label>NISN<input name="nisn" value="{{ $person->nisn }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
            <label>Jenis kelamin<select name="gender" required class="block w-full rounded border p-1 dark:bg-gray-900"><option value="L" @selected($person->gender === 'L')>Laki-laki</option><option value="P" @selected($person->gender === 'P')>Perempuan</option></select></label>
            <label>Nama orang tua<input name="parent_name" value="{{ $person->parent_name }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
            <label>Telepon orang tua<input name="parent_phone" value="{{ $person->parent_phone }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        @else
            <label>NIP<input name="nip" value="{{ $person->nip }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
            <label>NUPTK<input name="nuptk" value="{{ $person->nuptk }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
            <label>Jenis GTK<input name="role_type" value="{{ $person->role_type }}" required class="block w-full rounded border p-1 dark:bg-gray-900"></label>
            <label>Status kepegawaian<input name="staff_type" value="{{ $person->staff_type }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        @endif
        <label>Telepon<input name="phone" value="{{ $person->phone }}" class="block w-full rounded border p-1 dark:bg-gray-900"></label>
        @if($person->is_active)
        <label>Akun sekolah<select name="user_id" class="block w-full rounded border p-1 dark:bg-gray-900"><option value="">Belum terhubung</option>@foreach($linkableAccounts as $account)<option value="{{ $account->id }}" @selected($person->user_id == $account->id)>{{ $account->name }} (#{{ $account->id }})</option>@endforeach</select></label>
        @endif
        <button class="rounded border px-2 py-1">Simpan data</button>
    </form>
</details>
