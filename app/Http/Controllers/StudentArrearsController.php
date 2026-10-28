<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SchoolAccount;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\BillingPaymentService;
use App\Services\SchoolContext;
use App\Services\StudentArrearsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class StudentArrearsController extends Controller
{
    public function photo(Student $student, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $student->school_id === $schoolId && str_starts_with($student->photo_path ?? '', 'student-photos/'.$schoolId.'/'), 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($student->photo_path), 404);

        return response()->file($disk->path($student->photo_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function index(Request $request, SchoolContext $context, StudentArrearsService $arrears)
    {
        $schoolId = $context->activeSchoolIdFor();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'], 'sort' => ['nullable', Rule::in(['asc', 'desc'])],
            'month' => ['nullable', 'integer', 'between:1,12'], 'year' => ['nullable', 'integer', 'between:1900,2200'],
            'class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $committeeIncome = (bool) $request->attributes->get('committee_income');
        $rows = $arrears->rows($schoolId, [...$filters, 'committee_only' => $committeeIncome]);
        $page = (int) ($filters['page'] ?? 1);
        $students = new LengthAwarePaginator($rows->forPage($page, 15), $rows->count(), 15, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('pages.keuangan.arrears.index', ['title' => $committeeIncome ? 'Pemasukan Komite' : 'Tunggakan Siswa', 'committeeIncome' => $committeeIncome, 'students' => $students, 'filters' => $filters,
            'classes' => SchoolClass::where('school_id', $schoolId)->with('academicYear')->orderBy('name')->get()]);
    }

    public function show(Request $request, Student $student, SchoolContext $context, StudentArrearsService $arrears)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $student->school_id === $schoolId, 404);
        $student->load('schoolClasses.academicYear');
        $invoices = $arrears->invoices($schoolId, [], $student->id);
        $invoices->load('transactions');

        return view('pages.keuangan.arrears.show', [
            'title' => 'Tagihan '.$student->name, 'row' => $arrears->studentRow($student, $invoices), 'arrears' => $arrears,
            'bankAccounts' => SchoolAccount::where('school_id', $schoolId)->where('is_active', true)->whereRaw('LOWER(type) = ?', ['bank'])->where(fn ($q) => $q->whereNull('source_funding')->orWhere('source_funding', 'komite'))->orderBy('name')->get(),
        ]);
    }

    public function pay(Request $request, Student $student, SchoolContext $context, BillingPaymentService $payments)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $student->school_id === $schoolId, 404);
        if ($request->input('payment_method') === 'Tunai') {
            $request->merge(['account_id' => null]);
        }
        $request->validate([
            'invoice_id' => ['required', 'integer'], 'payment_method' => ['required', Rule::in(['Tunai', 'Transfer'])],
            'account_id' => [Rule::requiredIf($request->input('payment_method') === 'Transfer'), 'nullable', 'integer',
                Rule::exists('school_accounts', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->where('is_active', true)->whereRaw('LOWER(type) = ?', ['bank'])->where(fn ($source) => $source->whereNull('source_funding')->orWhere('source_funding', 'komite')))],
        ]);
        $invoice = Invoice::ownedBySchool($schoolId)->where('student_id', $student->id)->findOrFail($request->input('invoice_id'));

        return app(SppTransactionController::class)->pay($request, $invoice->id, $context, $payments);
    }

    public function proof(Student $student, \App\Models\Transaction $transaction, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $student->school_id === $schoolId && (int) $transaction->school_id === $schoolId, 404);
        abort_unless($transaction->invoice()->where('student_id', $student->id)->exists(), 404);
        $path = $transaction->proof_path ?? '';
        abort_unless(str_starts_with($path, 'payment-proofs/'.$schoolId.'/') && ! str_contains($path, '..'), 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
