<?php

namespace App\Http\Controllers;

use App\Models\BosIncomeRevision;
use App\Models\FinanceIncome;
use App\Services\BosIncomeService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BosIncomeController extends Controller
{
    public function index(Request $request, SchoolContext $context, BosIncomeService $bos)
    {
        $schoolId = $context->activeSchoolIdFor();

        return view('pages.keuangan.bos-income.index', ['title' => 'Pemasukan BOS', 'sources' => $bos->sources($schoolId),
            'years' => $bos->years($schoolId), 'bosAccounts' => $bos->accounts($schoolId)->get(),
            'incomes' => FinanceIncome::where('school_id', $schoolId)->bos()->with(['account', 'revisions'])->latest()->paginate(15)]);
    }

    public function store(Request $request, SchoolContext $context, BosIncomeService $bos)
    {
        $schoolId = $context->activeSchoolIdFor();
        $attributes = $this->validated($request, $schoolId, $bos);
        $path = $request->hasFile('proof') ? $request->file('proof')->storeAs('finance/bos/'.$schoolId, \Illuminate\Support\Str::uuid().'.'.$request->file('proof')->extension(), 'local') : null;
        try {
            $income = $bos->create($schoolId, $request->user()->id, [...$attributes, 'proof_path' => $path, 'proof_hash' => $request->hasFile('proof') ? hash_file('sha256', $request->file('proof')->getRealPath()) : null]);
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

    public function show(FinanceIncome $income, SchoolContext $context, BosIncomeService $bos)
    {
        $schoolId = $context->activeSchoolIdFor();
        $income = FinanceIncome::where('school_id', $schoolId)->bos()->with(['account', 'revisions.requester', 'revisions.reviewer'])->findOrFail($income->id);

        return view('pages.keuangan.bos-income.show', ['title' => 'Revisi Pemasukan BOS', 'income' => $income,
            'sources' => $bos->sources($schoolId)->merge([$income->source_funding])->unique()->values(),
            'years' => $bos->years($schoolId), 'bosAccounts' => $bos->accounts($schoolId)->get(),
            'accountNames' => \App\Models\SchoolAccount::where('school_id', $schoolId)->pluck('name', 'id')]);
    }

    public function requestRevision(Request $request, FinanceIncome $income, SchoolContext $context, BosIncomeService $bos)
    {
        $schoolId = $context->activeSchoolIdFor();
        $income = FinanceIncome::where('school_id', $schoolId)->bos()->findOrFail($income->id);
        $attributes = $this->validated($request, $schoolId, $bos, $income);
        $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Alasan revisi wajib diisi.']);
        $path = $request->hasFile('proof') ? $request->file('proof')->storeAs('finance/bos/'.$schoolId, \Illuminate\Support\Str::uuid().'.'.$request->file('proof')->extension(), 'local') : null;
        try {
            $bos->requestRevision($schoolId, $income, $request->user()->id, [...$attributes, 'reason' => $request->input('reason'), 'proof_path' => $path]);
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('success', 'Revisi diajukan. Data dan saldo belum berubah sampai disetujui kepala sekolah.');
    }

    private function validated(Request $request, int $schoolId, BosIncomeService $bos, ?FinanceIncome $income = null): array
    {
        $amount = $request->input('amount');
        if (is_string($amount) && preg_match('/^(?:\d{1,3}(?:\.\d{3})*|\d+),\d{1,2}$/', $amount)) {
            $request->merge(['amount' => str_replace(',', '.', str_replace('.', '', $amount))]);
        }
        $sources = $bos->sources($schoolId)->when($income, fn ($sources) => $sources->push($income->source_funding));
        $attributes = $request->validate([
            'source_funding' => ['required', 'string', 'max:120', Rule::in($sources->all())],
            'bos_year' => ['required', 'integer', 'between:2020,2099'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99', 'decimal:0,2'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:10240'],
            'account_id' => ['nullable', 'integer'],
            'bos_submission_key' => ['nullable', 'uuid'],
        ], ['source_funding.required' => 'Sumber dana BOS harus dipilih.', 'source_funding.in' => 'Pilih sumber dana BOS dari master sekolah.',
            'bos_year.required' => 'Tahun BOS harus dipilih.', 'bos_year.between' => 'Tahun BOS tidak valid.',
            'amount.required' => 'Nominal tidak boleh kosong.', 'amount.numeric' => 'Nominal harus berupa angka.', 'amount.min' => 'Nominal harus lebih dari nol.', 'amount.decimal' => 'Nominal maksimal dua angka desimal.',
            'received_date.required' => 'Tanggal harus diisi.', 'received_date.date_format' => 'Tanggal terima tidak valid.',
            'proof.mimes' => 'SP2D harus berupa PDF, JPG, PNG, atau HEIC.', 'proof.max' => 'Ukuran SP2D maksimal 10 MB.']);
        unset($attributes['proof']);

        return $attributes;
    }

    public function proof(FinanceIncome $income, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        $income = FinanceIncome::where('school_id', $schoolId)->bos()->findOrFail($income->id);

        return $this->download($income->proof_path, $schoolId, 'SP2D-'.$income->id);
    }

    public function revisionProof(Request $request, BosIncomeRevision $revision, SchoolContext $context)
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $revision->school_id === $schoolId, 404);
        $request->validate(['version' => ['nullable', Rule::in(['old', 'new'])]]);
        $data = $request->input('version') === 'old' ? $revision->old_data : $revision->new_data;

        return $this->download($data['proof_path'] ?? null, $schoolId, 'SP2D-revisi-'.$revision->id);
    }

    private function download(?string $path, int $schoolId, string $name)
    {
        abort_unless($path, 404);
        if (str_starts_with($path, 'finance/bos/'.$schoolId.'/')) {
            $disk = Storage::disk('local');
        } elseif (str_starts_with($path, 'finance/income_proofs/')) {
            $disk = Storage::disk('public');
        } else {
            abort(404);
        }
        abort_unless($disk->exists($path), 404);

        return response()->download($disk->path($path), $name.'.'.pathinfo($path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
