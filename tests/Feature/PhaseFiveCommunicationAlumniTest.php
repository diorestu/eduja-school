<?php

use App\Models\Alumni;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function phaseFiveSchool(array $attributes = []): int
{
    return DB::table('schools')->insertGetId(array_merge([
        'name' => 'Sekolah Fase Lima',
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function phaseFiveMember(int $schoolId, string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['role' => $role], $attributes));

    DB::table('school_user_roles')->insert([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'role' => $role,
        'is_active' => true,
        'membership_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

it('stores a targeted announcement and tracks a recipient read receipt', function () {
    $school = phaseFiveSchool();
    $teacher = phaseFiveMember($school, 'guru');

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school])
        ->post('/announcements', [
            'title' => 'Rapat guru',
            'body' => 'Rapat koordinasi hari Jumat.',
            'category' => 'akademik',
            'target_type' => 'teacher',
        ])
        ->assertRedirect();

    $announcement = Announcement::query()->firstOrFail();
    expect($announcement->target_type)->toBe('teacher');

    $student = phaseFiveMember($school, 'siswa');
    $this->actingAs($student)
        ->withSession(['active_school_id' => $school])
        ->get('/announcements/'.$announcement->id)
        ->assertForbidden();

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school])
        ->post('/announcements', ['title' => 'Tidak boleh', 'body' => 'Tidak boleh', 'target_type' => 'school'])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school])
        ->get('/announcements/'.$announcement->id)
        ->assertOk()
        ->assertSee('Rapat guru');

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school])
        ->post('/announcements/'.$announcement->id.'/read')
        ->assertRedirect();

    expect(DB::table('announcement_reads')->where('announcement_id', $announcement->id)->where('user_id', $teacher->id)->exists())->toBeTrue();
});

it('allows an alumni account to update only its own profile', function () {
    $school = phaseFiveSchool();
    $alumniUser = phaseFiveMember($school, 'alumni');
    $otherUser = phaseFiveMember($school, 'alumni');
    $alumni = Alumni::create([
        'school_id' => $school,
        'user_id' => $alumniUser->id,
        'name' => 'Alumni Satu',
        'graduation_year' => 2024,
        'current_status' => 'Lulus',
    ]);

    $this->actingAs($alumniUser)
        ->withSession(['active_school_id' => $school])
        ->put('/alumni/'.$alumni->id, [
            'phone' => '08123456789',
            'email' => 'alumni@example.test',
            'address' => 'Jl. Pendidikan',
            'current_status' => 'Kuliah',
            'education_history' => 'SMA 2024; Universitas 2025',
            'current_job' => '',
        ])
        ->assertRedirect();

    expect($alumni->refresh()->current_status)->toBe('Kuliah');
    expect($alumni->email)->toBe('alumni@example.test');

    $this->actingAs($otherUser)
        ->withSession(['active_school_id' => $school])
        ->put('/alumni/'.$alumni->id, ['current_status' => 'Bekerja'])
        ->assertForbidden();
});

it('saves the AI response without treating the adapter result as a string', function () {
    $school = phaseFiveSchool();
    $teacher = phaseFiveMember($school, 'guru');

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school])
        ->post('/ai', ['title' => 'Materi kelas', 'prompt' => 'Buat ringkasan energi'])
        ->assertRedirect();

    expect(DB::table('ai_materials')->where('title', 'Materi kelas')->value('response'))->toContain('AI belum diaktifkan');
});

it('renders operational empty states and does not show other users AI materials', function () {
    $school = phaseFiveSchool();
    $teacher = phaseFiveMember($school, 'kepsek');
    $other = phaseFiveMember($school, 'guru');
    \App\Models\AiMaterial::create(['school_id'=>$school, 'user_id'=>$other->id, 'title'=>'Materi pribadi lain', 'prompt'=>'Rahasia', 'status'=>'draft']);
    $this->actingAs($teacher)->withSession(['active_school_id'=>$school]);
    $this->get('/ai')->assertOk()->assertSee('Belum ada materi tersimpan')->assertDontSee('Materi pribadi lain');
    $this->get('/presensi/siswa')->assertOk()->assertSee('Pilih kelas untuk melihat daftar siswa');
    $this->get('/presensi/gtk')->assertOk()->assertSee('Belum ada peserta aktif');
    $this->get('/attendance/requests')->assertOk()->assertSee('Belum ada permohonan');
    $this->get('/alumni')->assertOk()->assertSee('Belum ada alumni');
});

it('counts unseen announcements and preserves the first read time', function () {
    $school = phaseFiveSchool();
    $teacher = phaseFiveMember($school, 'guru');
    $announcement = Announcement::create(['school_id'=>$school, 'title'=>'Informasi sekolah', 'body'=>'Isi', 'target_type'=>'school', 'published_at'=>now()]);
    Announcement::create(['school_id'=>$school, 'title'=>'Draft tersembunyi', 'body'=>'Isi', 'target_type'=>'school']);
    $this->actingAs($teacher)->withSession(['active_school_id'=>$school]);
    $this->get('/announcements')->assertOk()->assertViewHas('unreadCount', 1)->assertDontSee('Draft tersembunyi');
    $this->post('/announcements/'.$announcement->id.'/read')->assertRedirect();
    $first = DB::table('announcement_reads')->value('read_at');
    $this->travel(5)->minutes();
    $this->post('/announcements/'.$announcement->id.'/read')->assertRedirect();
    expect(DB::table('announcement_reads')->value('read_at'))->toBe($first);
    $this->get('/announcements')->assertViewHas('unreadCount', 0);
});

it('uses the active school alumni role for list and update ownership', function () {
    $school = phaseFiveSchool();
    $user = phaseFiveMember($school, 'alumni', ['role'=>'guru']);
    $own = Alumni::create(['school_id'=>$school,'user_id'=>$user->id,'name'=>'Profil sendiri']);
    $other = Alumni::create(['school_id'=>$school,'name'=>'Profil orang lain']);
    $this->actingAs($user)->withSession(['active_school_id'=>$school]);
    $this->get('/alumni')->assertOk()->assertSee('Profil sendiri')->assertDontSee('Profil orang lain');
    $this->put('/alumni/'.$other->id, ['current_status'=>'Kuliah'])->assertForbidden();
});

it('shows only owned requests to a guardian and supports private document uploads', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $school = phaseFiveSchool();
    $user = phaseFiveMember($school, 'orang_tua');
    $student = \App\Models\Student::create(['school_id'=>$school,'guardian_user_id'=>$user->id,'nis'=>'UI001','name'=>'Anak Uji','gender'=>'L','status'=>'active','is_active'=>true]);
    $other = \App\Models\AttendanceRequest::create(['school_id'=>$school,'reason'=>'Alasan privat lainnya','request_type'=>'izin','start_date'=>today(),'status'=>'pending']);
    $this->actingAs($user)->withSession(['active_school_id'=>$school]);
    $this->post('/attendance/requests/student', [
        'subject_id'=>$student->id, 'request_type'=>'izin', 'start_date'=>today()->toDateString(), 'end_date'=>today()->toDateString(),
        'reason'=>'Keperluan keluarga', 'document'=>\Illuminate\Http\UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $own = \App\Models\AttendanceRequest::where('requester_id',$user->id)->firstOrFail();
    $this->get('/attendance/requests')->assertOk()->assertSee('Keperluan keluarga')->assertDontSee('Alasan privat lainnya');
    $this->get('/attendance/requests/'.$own->id.'/document')->assertOk();
    $stranger = phaseFiveMember($school, 'orang_tua');
    $this->actingAs($stranger)->get('/attendance/requests/'.$own->id.'/document')->assertForbidden();
});

it('keeps principals read only while TU can write attendance', function () {
    $school = phaseFiveSchool();
    $principal = phaseFiveMember($school, 'kepsek', ['role'=>'super_admin']);
    $this->actingAs($principal)->withSession(['active_school_id'=>$school]);
    $this->get('/presensi/gtk')->assertOk()->assertViewHas('canEdit', false);
    $this->post('/presensi/gtk', [])->assertForbidden();
    $this->post('/presensi/siswa', [])->assertForbidden();
    $tu = phaseFiveMember($school, 'staf_tu');
    $this->actingAs($tu)->get('/presensi/gtk')->assertOk()->assertViewHas('canEdit', true);
});

it('limits targeted announcement attachments and bookmarks to recipients', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $school = phaseFiveSchool();
    $writer = phaseFiveMember($school, 'staf_tu');
    $recipient = phaseFiveMember($school, 'guru');
    $other = phaseFiveMember($school, 'guru');
    $this->actingAs($writer)->withSession(['active_school_id'=>$school]);
    $this->post('/announcements', ['title'=>'Surat khusus', 'body'=>'Isi khusus', 'target_type'=>'person', 'target_id'=>$recipient->id,
        'attachment'=>\Illuminate\Http\UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    $announcement = Announcement::where('title','Surat khusus')->firstOrFail();
    $this->actingAs($recipient)->get('/announcements/'.$announcement->id)->assertOk();
    $this->get('/announcements/'.$announcement->id.'/attachment')->assertOk();
    $this->post('/announcements/'.$announcement->id.'/bookmark', ['saved'=>1])->assertRedirect();
    $this->post('/announcements/'.$announcement->id.'/bookmark', ['saved'=>1])->assertRedirect();
    expect(DB::table('announcement_bookmarks')->count())->toBe(1);
    $this->actingAs($other)->get('/announcements/'.$announcement->id)->assertForbidden();
    $this->get('/announcements/'.$announcement->id.'/attachment')->assertForbidden();
    $this->post('/announcements/'.$announcement->id.'/bookmark',['saved'=>1])->assertForbidden();
});

it('rejects foreign school targets and limits notification feed', function () {
    $school = phaseFiveSchool();
    $otherSchool = phaseFiveSchool();
    $writer = phaseFiveMember($school, 'staf_tu');
    $outsider = phaseFiveMember($otherSchool, 'guru');
    $this->actingAs($writer)->withSession(['active_school_id'=>$school]);
    $this->post('/announcements', ['title'=>'Invalid', 'body'=>'Body', 'target_type'=>'person', 'target_id'=>$outsider->id])->assertSessionHasErrors('target_id');
    Announcement::create(['school_id'=>$otherSchool,'title'=>'Foreign','body'=>'Body','target_type'=>'school','published_at'=>now()]);
    Announcement::create(['school_id'=>$school,'title'=>'Draft','body'=>'Body','target_type'=>'school']);
    expect(app(\App\Services\SchoolNotificationFeed::class)->forUser($writer))->toHaveCount(0);
    Announcement::create(['school_id'=>$school,'title'=>'Local','body'=>'Body','target_type'=>'school','published_at'=>now()]);
    expect(app(\App\Services\SchoolNotificationFeed::class)->forUser($writer)->pluck('title')->all())->toBe(['Local']);
});

it('links old alumni only to an active alumni account in the same school', function () {
    $school = phaseFiveSchool();
    $admin = phaseFiveMember($school, 'staf_tu');
    $account = phaseFiveMember($school, 'alumni');
    $other = phaseFiveMember(phaseFiveSchool(), 'alumni');
    $profile = Alumni::create(['school_id'=>$school,'name'=>'Alumni lama']);
    $this->actingAs($admin)->withSession(['active_school_id'=>$school]);
    $this->post('/alumni/'.$profile->id.'/link',['user_id'=>$other->id])->assertSessionHasErrors('user_id');
    $this->post('/alumni/'.$profile->id.'/link',['user_id'=>$account->id])->assertSessionHasNoErrors();
    expect($profile->fresh()->user_id)->toBe($account->id);
    $this->actingAs($account)->get('/dashboard')->assertRedirect('/alumni');
});
