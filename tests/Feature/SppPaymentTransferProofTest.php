<?php

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SchoolAccount;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function sppFixture($test): array
{
    Storage::fake('local');
    $school = DB::table('schools')->insertGetId([
        'name' => 'Sekolah SPP Bukti Transfer',
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
    ]);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert([
        'school_id' => $school,
        'user_id' => $user->id,
        'role' => 'bendahara',
        'is_active' => true,
        'membership_status' => 'active',
    ]);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);

    $bank = SchoolAccount::create([
        'school_id' => $school,
        'name' => 'BNI Operasional Sekolah',
        'type' => 'Bank',
        'bank_name' => 'Bank BNI',
        'account_number' => '987654321',
        'is_active' => true,
        'current_balance' => 0,
    ]);

    $year = AcademicYear::create([
        'school_id' => $school,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
    ]);
    $class = SchoolClass::create([
        'school_id' => $school,
        'academic_year_id' => $year->id,
        'name' => 'XII IPA',
        'grade' => 'XII',
    ]);
    $student = Student::create([
        'school_id' => $school,
        'nis' => 'SPP-001',
        'name' => 'Budi Santoso',
        'gender' => 'L',
        'status' => 'active',
        'is_active' => true,
    ]);
    $class->students()->attach($student->id);

    $invoice = Invoice::create([
        'school_id' => $school,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'invoice_number' => 'INV-SPP-001',
        'due_date' => '2026-10-31',
        'total_amount' => 250000,
        'paid_amount' => 0,
        'remaining_amount' => 250000,
        'status' => 'Belum Lunas',
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'SPP Komite Oktober 2026',
        'amount' => 250000,
    ]);

    return [$school, $student, $invoice, $bank];
}

it('allows paying arrears via transfer bank with account selection and payment proof upload', function () {
    [$school, $student, $invoice, $bank] = sppFixture($this);

    // Form di transaksi blade memiliki field bukti transfer dan pilihan rekening bank
    $this->get('/spp/transaksi')->assertOk()
        ->assertSee('name="account_id"', false)
        ->assertSee('name="proof"', false)
        ->assertSee('Pilih Rekening Transfer');

    $file = UploadedFile::fake()->image('bukti_transfer.jpg');

    $response = $this->post("/spp/transaksi/{$invoice->id}/bayar", [
        'amount_paid' => 250000,
        'payment_method' => 'Transfer',
        'account_id' => $bank->id,
        'payment_date' => '2026-10-30',
        'proof' => $file,
    ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::where('invoice_id', $invoice->id)->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->amount_paid)->toBe('250000.00')
        ->and($transaction->account_id)->toBe($bank->id)
        ->and($transaction->proof_path)->not->toBeNull()
        ->and($transaction->proof_path)->toStartWith("payment-proofs/{$school}/");

    Storage::disk('local')->assertExists($transaction->proof_path);

    // Verifikasi bukti transfer dapat diakses via route transaksi.proof
    $this->get(route('spp.transaksi.proof', $transaction))->assertOk();
});

it('accepts payment with payment_method Transfer Bank as well', function () {
    [$school, $student, $invoice, $bank] = sppFixture($this);

    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    $response = $this->post("/spp/transaksi/{$invoice->id}/bayar", [
        'amount_paid' => 150000,
        'payment_method' => 'Transfer Bank',
        'account_id' => $bank->id,
        'payment_date' => '2026-10-30',
        'proof' => $file,
    ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::where('invoice_id', $invoice->id)->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->amount_paid)->toBe('150000.00')
        ->and($transaction->payment_method)->toBe('Transfer Bank')
        ->and($transaction->proof_path)->not->toBeNull();

    Storage::disk('local')->assertExists($transaction->proof_path);
});

it('handles student arrears flow payment with Transfer Bank payment method', function () {
    [$school, $student, $invoice, $bank] = sppFixture($this);

    $file = UploadedFile::fake()->image('transfer_komite.png');

    $response = $this->post("/finance/arrears/{$student->id}/pay", [
        'invoice_id' => $invoice->id,
        'amount_paid' => 250000,
        'payment_method' => 'Transfer Bank',
        'account_id' => $bank->id,
        'payment_date' => '2026-10-30',
        'proof' => $file,
    ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::where('invoice_id', $invoice->id)->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->proof_path)->not->toBeNull();

    Storage::disk('local')->assertExists($transaction->proof_path);
});
