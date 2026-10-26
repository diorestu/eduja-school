<?php

namespace App\Http\Controllers;

use App\Models\SchoolAccount;
use App\Models\Student;
use App\Services\CommitteeReceiptService;
use App\Services\SchoolContext;
use App\Services\StudentArrearsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitteeIncomeController extends Controller
{
    public function index(Request $request, SchoolContext $context, StudentArrearsService $arrears)
    {
        abort_unless(app(\App\Services\PermissionService::class)->can($request->user(), 'spp.transactions', ['bendahara', 'super_admin', 'kepsek']), 403);
        $request->attributes->set('committee_income', true);

        return app(StudentArrearsController::class)->index($request, $context, $arrears);
    }

    public function show(Request $request, Student $student, SchoolContext $context, StudentArrearsService $arrears)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $student->school_id === $schoolId, 404);
        $student->load('schoolClasses.academicYear');
        $invoices = $arrears->invoices($schoolId, ['committee_only' => true], $student->id);

        return view('pages.keuangan.income-committee.show', ['title' => 'Pemasukan Komite', 'row' => $arrears->studentRow($student, $invoices), 'arrears' => $arrears,
            'bankAccounts' => SchoolAccount::where('school_id', $schoolId)->where('is_active', true)->whereRaw('LOWER(type) = ?', ['bank'])->where('source_funding', 'komite')->orderBy('name')->get()]);
    }

    public function store(Request $request, Student $student, SchoolContext $context, CommitteeReceiptService $receipts)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $student->school_id === $schoolId, 404);
        if ($request->input('payment_method') === 'Tunai') {
            $request->merge(['account_id' => null]);
        }
        $lines = $request->input('payments');
        if (is_array($lines)) {
            foreach ($lines as &$line) {
                $value = is_array($line) ? ($line['amount_paid'] ?? null) : null;
                if (is_string($value) && str_contains($value, ',') && preg_match('/^(?:\d{1,3}(?:\.\d{3})*|\d+),\d{1,2}$/', $value)) {
                    $line['amount_paid'] = str_replace(',', '.', str_replace('.', '', $value));
                }
            }
            unset($line);
            $request->merge(['payments' => $lines]);
        }
        $attributes = $request->validate([
            'payments' => ['required', 'array', 'min:1'], 'payments.*.invoice_id' => ['required', 'integer', 'distinct'],
            'payments.*.amount_paid' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(['Tunai', 'Transfer'])], 'payment_date' => ['required', 'date_format:Y-m-d'],
            'submission_key' => ['required', 'uuid'],
            'account_id' => [Rule::requiredIf($request->input('payment_method') === 'Transfer'), 'nullable', 'integer',
                Rule::exists('school_accounts', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->where('is_active', true)->whereRaw('LOWER(type) = ?', ['bank'])->where('source_funding', 'komite'))],
        ], [
            'payments.required' => 'Pilih minimal satu tagihan.',
            'payments.array' => 'Daftar tagihan tidak valid.',
            'payments.*.invoice_id.required' => 'Pilih tagihan yang akan dibayar.',
            'payments.*.invoice_id.distinct' => 'Tagihan yang sama tidak boleh dipilih dua kali.',
            'payments.*.amount_paid.required' => 'Nominal pembayaran setiap tagihan wajib diisi.',
            'payments.*.amount_paid.numeric' => 'Nominal pembayaran harus berupa angka.',
            'payments.*.amount_paid.min' => 'Nominal pembayaran harus lebih dari nol.',
            'payments.*.amount_paid.decimal' => 'Nominal pembayaran maksimal dua angka desimal.',
            'payment_method.in' => 'Pilih metode Tunai atau Transfer.',
            'payment_date.required' => 'Tanggal transaksi wajib diisi.',
            'payment_date.date_format' => 'Tanggal transaksi tidak valid.',
            'account_id.required' => 'Pilih rekening transfer Komite.',
            'account_id.exists' => 'Rekening transfer harus aktif, berkategori Komite, dan milik sekolah yang sedang aktif.',
            'submission_key.required' => 'Form penerimaan tidak valid. Muat ulang halaman.',
            'submission_key.uuid' => 'Form penerimaan tidak valid. Muat ulang halaman.',
        ]);
        $receipt = $receipts->receive($schoolId, $student, $request->user()->id, $attributes);

        return back()->with('success', 'Transaksi berhasil disimpan')->with('committee_receipt_number', $receipt->receipt_number)->with('committee_receipt_amount', $receipt->amount_paid);
    }
}
