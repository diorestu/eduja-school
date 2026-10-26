<?php

namespace App\Http\Controllers;

use App\Models\FinanceIncome;
use App\Models\OtherIncomeRevision;
use App\Models\SchoolAccount;
use App\Services\OtherIncomeService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OtherIncomeController extends Controller
{
    private function formData(int $schoolId, OtherIncomeService $incomes): array
    {
        return ['incomeTypes' => $incomes->types($schoolId)->get(), 'cashAccounts' => $incomes->accounts($schoolId, 'Tunai')->get(), 'bankAccounts' => $incomes->accounts($schoolId, 'Transfer')->get()];
    }

    public function index(Request $request, SchoolContext $context, OtherIncomeService $incomes)
    {
        $schoolId = $context->activeSchoolIdFor();

        return view('pages.keuangan.other-income.index', [...$this->formData($schoolId, $incomes), 'title' => 'Pendapatan Lainnya',
            'incomes' => FinanceIncome::where('school_id', $schoolId)->other()->with(['account', 'otherRevisions'])->latest()->paginate(15)]);
    }

    public function store(Request $request, SchoolContext $context, OtherIncomeService $incomes)
    {
        $schoolId = $context->activeSchoolIdFor();
        $attributes = $this->validated($request);
        $path = $this->upload($request, $schoolId);
        try {
            $income = $incomes->create($schoolId, $request->user()->id, [...$attributes, 'proof_path' => $path, 'proof_hash' => $request->hasFile('proof') ? hash_file('sha256', $request->file('proof')->getRealPath()) : null]);
            if ($path && $income->proof_path !== $path) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('success', 'Transaksi berhasil disimpan');
    }

    public function show(FinanceIncome $income, SchoolContext $context, OtherIncomeService $incomes)
    {
        $schoolId = $context->activeSchoolIdFor();
        $income = FinanceIncome::where('school_id', $schoolId)->other()->with(['account', 'otherRevisions.requester', 'otherRevisions.reviewer'])->findOrFail($income->id);

        return view('pages.keuangan.other-income.show', [...$this->formData($schoolId, $incomes), 'title' => 'Revisi Pendapatan Lainnya', 'income' => $income,
            'accountNames' => SchoolAccount::where('school_id', $schoolId)->pluck('name', 'id'),
            'typeNames' => $incomes->types($schoolId)->pluck('name', 'id'),
            'walletNames' => \App\Models\VirtualWallet::where('school_id', $schoolId)->pluck('name', 'id')]);
    }

    public function requestRevision(Request $request, FinanceIncome $income, SchoolContext $context, OtherIncomeService $incomes)
    {
        $schoolId = $context->activeSchoolIdFor();
        $income = FinanceIncome::where('school_id', $schoolId)->other()->findOrFail($income->id);
        $attributes = $this->validated($request);
        $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Alasan revisi wajib diisi.']);
        $path = $this->upload($request, $schoolId);
        try {
            $incomes->requestRevision($schoolId, $income, $request->user()->id, [...$attributes, 'reason' => $request->input('reason'), 'proof_path' => $path]);
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('success', 'Revisi diajukan. Data dan saldo belum berubah sampai disetujui kepala sekolah.');
    }

    private function upload(Request $request, int $schoolId): ?string
    {
        return $request->hasFile('proof') ? $request->file('proof')->storeAs('finance/other-incomes/'.$schoolId, Str::uuid().'.'.$request->file('proof')->extension(), 'local') : null;
    }

    private function validated(Request $request): array
    {
        $amount = $request->input('amount');
        if (is_string($amount) && preg_match('/^(?:\d{1,3}(?:\.\d{3})*|\d+),\d{1,2}$/', $amount)) {
            $request->merge(['amount' => str_replace(',', '.', str_replace('.', '', $amount))]);
        }
        $attributes = $request->validate([
            'source_funding' => ['required', 'string', 'max:160'], 'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99', 'decimal:0,2'],
            'received_date' => ['required', 'date_format:Y-m-d'], 'payment_method' => ['required', Rule::in(['Tunai', 'Transfer'])],
            'account_id' => ['nullable', 'integer'], 'income_type_id' => ['nullable', 'integer'], 'other_submission_key' => ['nullable', 'uuid'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:10240'],
        ], ['source_funding.required' => 'Sumber pendapatan harus diisi.', 'amount.required' => 'Nominal tidak boleh kosong.', 'amount.min' => 'Nominal harus lebih dari nol.',
            'amount.numeric' => 'Nominal harus berupa angka.', 'amount.decimal' => 'Nominal maksimal dua angka desimal.', 'received_date.required' => 'Tanggal harus diisi.',
            'received_date.date_format' => 'Tanggal terima tidak valid.', 'payment_method.required' => 'Pilih metode pembayaran.', 'proof.mimes' => 'Bukti harus PDF, JPG, PNG, atau HEIC.', 'proof.max' => 'Ukuran bukti maksimal 10 MB.']);
        unset($attributes['proof']);

        return $attributes;
    }

    public function proof(FinanceIncome $income, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        $income = FinanceIncome::where('school_id', $schoolId)->other()->findOrFail($income->id);

        return $this->download($income->proof_path, $schoolId, 'Bukti-Lainnya-'.$income->id);
    }

    public function revisionProof(Request $request, OtherIncomeRevision $revision, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $revision->school_id === $schoolId, 404);
        $request->validate(['version' => ['nullable', Rule::in(['old', 'new'])]]);
        $data = $request->input('version') === 'old' ? $revision->old_data : $revision->new_data;

        return $this->download($data['proof_path'] ?? null, $schoolId, 'Bukti-Lainnya-revisi-'.$revision->id);
    }

    private function download(?string $path, int $schoolId, string $name)
    {
        abort_unless($path, 404);
        $disk = str_starts_with($path, 'finance/other-incomes/'.$schoolId.'/') ? Storage::disk('local') : (str_starts_with($path, 'finance/income_proofs/') ? Storage::disk('public') : null);
        abort_unless($disk && $disk->exists($path), 404);

        return response()->download($disk->path($path), $name.'.'.pathinfo($path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
