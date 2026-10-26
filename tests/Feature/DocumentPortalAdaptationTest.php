<?php

use App\Models\{School, User, Student, Teacher, SchoolClass, AttendanceRequest, ApprovalRequest, StudentAttendance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function flowPortalFixture(): array {
    $school = School::create(['name'=>'Sekolah Flow','level'=>'sma','ownership'=>'swasta','status'=>'active','is_active'=>true]);
    $users = collect(['wali_kelas','wali_kelas','siswa'])->map(function ($role) use ($school) {
        $user = User::factory()->create(['role'=>$role]);
        DB::table('school_user_roles')->insert(['school_id'=>$school->id,'user_id'=>$user->id,'role'=>$role,'is_active'=>true,'membership_status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return $user;
    });
    $teacher = Teacher::create(['school_id'=>$school->id,'user_id'=>$users[0]->id,'nuptk'=>'FLOW-T','name'=>'Wali Terdaftar','role_type'=>'Guru','staff_type'=>'Honorer','status'=>'active','is_active'=>true]);
    $year = DB::table('academic_years')->insertGetId(['school_id'=>$school->id,'year'=>'2026/2027','semester'=>'Ganjil','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    $class = SchoolClass::create(['school_id'=>$school->id,'teacher_id'=>$teacher->id,'academic_year_id'=>$year,'name'=>'X Flow','grade'=>10]);
    $student = Student::create(['school_id'=>$school->id,'user_id'=>$users[2]->id,'nis'=>'FLOW-S','name'=>'Anak Kelas Flow','gender'=>'P','status'=>'active','is_active'=>true]);
    $class->students()->attach($student);
    test()->actingAs($users[0])->withSession(['active_school_id'=>$school->id]);
    return [$school,$users,$class,$student];
}

it('keeps absence review and documents within the assigned active class', function () {
    [$school,$users,$class,$student] = flowPortalFixture();
    $absence = app(App\Services\AbsenceRequestService::class)->submitStudent($school->id,$users[2],['subject_id'=>$student->id,'request_type'=>'izin','start_date'=>today()->toDateString(),'reason'=>'Keluarga']);
    expect(app(App\Services\AttendanceApprovalService::class)->canReview($absence->approvalRequest,$users[0]))->toBeTrue();
    $this->actingAs($users[1]);
    expect(app(App\Services\AttendanceApprovalService::class)->canReview($absence->approvalRequest,$users[1]))->toBeFalse();
    $this->get('/attendance/requests')->assertOk()->assertDontSee('Keluarga');
    $this->get('/attendance/requests/'.$absence->id.'/document')->assertForbidden();
});

it('resolves current enrollment and includes dispensasi in portal statistics', function () {
    [$school,$users,$class,$student] = flowPortalFixture();
    $oldYear = DB::table('academic_years')->insertGetId(['school_id'=>$school->id,'year'=>'2025/2026','semester'=>'Ganjil','is_active'=>false,'created_at'=>now(),'updated_at'=>now()]);
    $oldClass = SchoolClass::create(['school_id'=>$school->id,'academic_year_id'=>$oldYear,'name'=>'Kelas Historis','grade'=>9]);
    $oldClass->students()->attach($student);
    StudentAttendance::create(['school_id'=>$school->id,'school_class_id'=>$class->id,'student_id'=>$student->id,'attendance_date'=>today(),'status'=>'D']);
    expect(app(App\Services\ActiveEnrollment::class)->forStudent($school->id,$student->id)->id)->toBe($class->id);
    $this->actingAs($users[2])->get('/portal/siswa')->assertOk()->assertViewHas('stats',fn ($stats)=>$stats['dispensasi'] === 1 && $stats['rate'] === 0.0);
});

it('opens announcements as read and rejects editing another authors announcement', function () {
    [$school,$users] = flowPortalFixture();
    $announcement = App\Models\Announcement::create(['school_id'=>$school->id,'created_by'=>$users[0]->id,'title'=>'Info Flow','body'=>'Isi','target_type'=>'school','published_at'=>now()]);
    $this->actingAs($users[1])->get('/announcements/'.$announcement->id)->assertOk();
    expect($announcement->reads()->where('user_id',$users[1]->id)->whereNotNull('read_at')->exists())->toBeTrue();
    $this->put('/announcements/'.$announcement->id,['title'=>'Changed','body'=>'X','target_type'=>'school'])->assertForbidden();
});

it('restricts private material downloads to enrolled recipients and expires signed links', function () {
    [$school,$users,$class,$student] = flowPortalFixture();
    Illuminate\Support\Facades\Storage::fake('local');
    $this->post('/materials', ['title'=>'Materi Privat','school_class_id'=>$class->id,'file'=>Illuminate\Http\UploadedFile::fake()->create('materi.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
    $material = App\Models\LearningMaterial::query()->sole();
    $this->actingAs($users[2])->get('/materials/'.$material->id.'/link')->assertRedirect();
    $url = $this->get('/materials/'.$material->id.'/link')->headers->get('Location');
    $this->get($url)->assertOk();
    $this->get('/materials/'.$material->id.'/download')->assertForbidden();
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
    $this->travelBack();
    $this->actingAs($users[1])->get($url)->assertForbidden();
    $this->actingAs($users[2]);
    $class->students()->detach($student);
    $this->get($url)->assertForbidden();
    $this->actingAs($users[1])->delete('/materials/'.$material->id)->assertForbidden();
});

it('shows only assigned class learners and allows scoped teacher attendance edits', function () {
    [$school,$users,$class,$student] = flowPortalFixture();
    $this->get('/portal/guru')->assertOk()->assertSee($student->name)->assertViewHas('classes',fn ($classes)=>$classes->count()===1);
    $this->post('/portal/guru/attendance',['school_class_id'=>$class->id,'student_id'=>$student->id,'attendance_date'=>today()->toDateString(),'status'=>'H'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($users[1])->post('/portal/guru/attendance',['school_class_id'=>$class->id,'student_id'=>$student->id,'attendance_date'=>today()->toDateString(),'status'=>'A'])->assertForbidden();
    expect(StudentAttendance::where('student_id',$student->id)->sole()->status)->toBe('H');
});

it('submits owned student transfer evidence pending and posts only after school approval', function () {
    [$school,$users,$class,$student] = flowPortalFixture();
    Illuminate\Support\Facades\Storage::fake('local');
    $account = App\Models\SchoolAccount::create(['school_id'=>$school->id,'name'=>'Rekening Sekolah','type'=>'bank','bank_name'=>'Bank Sekolah','account_number'=>'12345','opening_balance'=>0,'current_balance'=>0,'is_active'=>true]);
    $invoice = App\Models\Invoice::create(['school_id'=>$school->id,'student_id'=>$student->id,'academic_year_id'=>$class->academic_year_id,'invoice_number'=>'FLOW-INVOICE','total_amount'=>100000,'due_date'=>today(),'status'=>'Belum Lunas']);
    $payload=['invoice_id'=>$invoice->id,'account_id'=>$account->id,'amount'=>100000,'payment_date'=>today()->toDateString(),'proof'=>Illuminate\Http\UploadedFile::fake()->create('bukti.pdf',10,'application/pdf')];
    $this->actingAs($users[2])->post('/portal/payments',$payload)->assertRedirect()->assertSessionHasNoErrors();
    $payment=App\Models\PaymentSubmission::query()->sole();
    expect($payment->status)->toBe('pending')->and($account->fresh()->current_balance)->toBe('0.00')->and($invoice->fresh()->status)->toBe('Belum Lunas');
    $this->post('/portal/payments',$payload)->assertSessionHasErrors('invoice_id');
    $reviewer=User::factory()->create(['role'=>'bendahara']);
    DB::table('school_user_roles')->insert(['school_id'=>$school->id,'user_id'=>$reviewer->id,'role'=>'bendahara','is_active'=>true,'membership_status'=>'active','created_at'=>now(),'updated_at'=>now()]);
    $approval=ApprovalRequest::where('approvable_type',App\Models\PaymentSubmission::class)->where('approvable_id',$payment->id)->sole();
    $this->actingAs($reviewer)->post('/approvals/'.$approval->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe('Lunas')->and($account->fresh()->current_balance)->toBe('100000.00');
    DB::table('school_user_roles')->insert(['school_id'=>$school->id,'user_id'=>$users[1]->id,'role'=>'siswa','is_active'=>true,'membership_status'=>'active','created_at'=>now(),'updated_at'=>now()]);
    $this->actingAs($users[1])->post('/portal/payments',$payload)->assertNotFound();
});

it('reserves annual leave against configured allowance and releases rejected days', function () {
    [$school,$users,$class,$student] = flowPortalFixture();
    $teacher=Teacher::where('user_id',$users[0]->id)->sole();
    DB::table('teacher_leave_entitlements')->insert(['school_id'=>$school->id,'teacher_id'=>$teacher->id,'year'=>(int) date('Y'),'days'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $service=app(App\Services\AbsenceRequestService::class);
    $attributes=['subject_id'=>$teacher->id,'request_type'=>'cuti','leave_type'=>'annual','contact_phone'=>'08123456789','start_date'=>today()->toDateString(),'reason'=>'Keluarga'];
    $first=$service->submitTeacher($school->id,$users[0],$attributes);
    expect($first->leave_type)->toBe('annual')->and($first->contact_phone)->toBe('08123456789');
    expect(fn ()=>$service->submitTeacher($school->id,$users[0],[...$attributes,'start_date'=>today()->addDay()->toDateString()]))->toThrow(InvalidArgumentException::class);
    $first->update(['status'=>'rejected']);
    expect($service->submitTeacher($school->id,$users[0],[...$attributes,'start_date'=>today()->addDay()->toDateString()])->status)->toBe('pending');
});

it('keeps guardian transfer requests pending until an authorized school review', function () {
    [$school,$users,$class,$student]=flowPortalFixture();
    Illuminate\Support\Facades\Storage::fake('local');
    $guardian=User::factory()->create(['role'=>'orang_tua']);
    DB::table('school_user_roles')->insert(['school_id'=>$school->id,'user_id'=>$guardian->id,'role'=>'orang_tua','is_active'=>true,'membership_status'=>'active','created_at'=>now(),'updated_at'=>now()]);
    $student->update(['guardian_user_id'=>$guardian->id]);
    $data=['student_id'=>$student->id,'destination'=>'Sekolah Tujuan','transfer_date'=>today()->toDateString(),'reason'=>'Pindah domisili','document'=>Illuminate\Http\UploadedFile::fake()->create('surat.pdf',10,'application/pdf')];
    $this->actingAs($guardian)->post('/portal/transfers',$data)->assertRedirect()->assertSessionHasNoErrors();
    $transfer=App\Models\StudentTransferRequest::sole();
    expect($student->fresh()->status)->toBe('active');
    $this->actingAs($users[0])->post('/student-transfer-requests/'.$transfer->id.'/review',['decision'=>'approved'])->assertForbidden();
    DB::table('school_user_roles')->insert(['school_id'=>$school->id,'user_id'=>$users[1]->id,'role'=>'tu','is_active'=>true,'membership_status'=>'active','created_at'=>now(),'updated_at'=>now()]);
    $this->actingAs($users[1])->post('/student-transfer-requests/'.$transfer->id.'/review',['decision'=>'approved'])->assertRedirect()->assertSessionHasNoErrors();
    expect($student->fresh()->status)->toBe('transferred')->and($transfer->fresh()->status)->toBe('approved');
});

it('records GPS attendance for the linked teacher with a private photo and rejects foreign locations', function () {
    [$school,$users]=flowPortalFixture();
    Illuminate\Support\Facades\Storage::fake('local');
    Illuminate\Support\Facades\Config::set('services.attendance.gps.schools.'.$school->id,['latitude'=>-5.1477,'longitude'=>119.4327,'radius_meters'=>250]);
    $data=['type'=>'masuk','location'=>['latitude'=>0,'longitude'=>0],'photo'=>Illuminate\Http\UploadedFile::fake()->image('selfie.jpg')];
    $this->post('/portal/guru/self-attendance',$data)->assertSessionHasErrors('location');
    $this->post('/portal/guru/self-attendance',[...$data,'location'=>['latitude'=>-5.1477,'longitude'=>119.4327]])->assertRedirect()->assertSessionHasNoErrors();
    $attendance=App\Models\TeacherAttendance::sole();
    expect($attendance->teacher->user_id)->toBe($users[0]->id)->and($attendance->source)->toBe('gps');
    Illuminate\Support\Facades\Storage::disk('local')->assertExists($attendance->photo_path);
});
