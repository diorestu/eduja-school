<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolAccount;
use App\Services\FinanceLedgerService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;

class GuardianFinanceAccessController extends Controller
{
    public function index(Request $request, SchoolContext $context)
    {
        $school = School::findOrFail($context->activeSchoolIdFor($request->user()));

        return view('pages.settings.guardian-finance', compact('school'));
    }

    public function update(Request $request, SchoolContext $context)
    {
        $data = $request->validate(['guardian_finance_allowed' => ['required', 'boolean']]);
        $school = School::findOrFail($context->activeSchoolIdFor($request->user()));
        $school->guardian_finance_allowed = $data['guardian_finance_allowed'];
        $school->save();

        return redirect()->route('settings.guardian-finance.index')->with('success', 'Izin keuangan orang tua berhasil diperbarui.');
    }

    public function report(Request $request, SchoolContext $context, FinanceLedgerService $ledger)
    {
        $schoolId = $context->activeSchoolIdFor($request->user());
        $school = School::findOrFail($schoolId);
        abort_unless($school->guardian_finance_allowed && $request->user()->guardianStudents()->active()->where('school_id', $schoolId)->exists(), 403);

        return view('pages.foundation.index', [
            'title' => 'Ringkasan Keuangan Sekolah', 'eyebrow' => 'Transparansi Sekolah',
            'description' => 'Ringkasan yang dibagikan kepala sekolah. Data tagihan atau transaksi individu tidak ditampilkan.',
            'metrics' => [
                ['label' => 'Saldo Kas', 'value' => 'Rp '.number_format(SchoolAccount::where('school_id', $schoolId)->whereRaw('LOWER(type) IN (?, ?)', ['cash', 'tunai'])->sum('current_balance'), 0, ',', '.')],
                ['label' => 'Saldo Rekening', 'value' => 'Rp '.number_format(SchoolAccount::where('school_id', $schoolId)->whereRaw('LOWER(type) IN (?, ?)', ['bank', 'bos'])->sum('current_balance'), 0, ',', '.')],
                ['label' => 'Total Pengeluaran Disetujui', 'value' => 'Rp '.number_format(\App\Models\Expense::where('school_id', $schoolId)->where('status', 'approved')->sum('amount'), 0, ',', '.')],
            ],
        ]);
    }
}
