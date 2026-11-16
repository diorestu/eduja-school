<?php

namespace App\Http\Controllers;

use App\Models\BookClosing;
use App\Models\BudgetYear;
use App\Services\CommitteeClosingService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitteeClosingController extends Controller
{
    public function index(Request $request, SchoolContext $context, CommitteeClosingService $service)
    {
        $schoolId = $context->activeSchoolIdFor();
        $years = BudgetYear::where('school_id', $schoolId)->orderByDesc('start_date')->get();
        $input = $request->validate(['type' => ['nullable', Rule::in(['monthly', 'yearly'])], 'period' => ['nullable', 'string', 'max:25'], 'check' => ['nullable', 'in:1']]);
        $type = $input['type'] ?? 'monthly';
        $period = $input['period'] ?? ($type === 'yearly' ? (string) ($years->first()?->id ?? '') : now()->format('Y-m'));
        $preview = ! empty($input['check']) ? $service->preview($schoolId, $type, $period) : null;
        $closings = BookClosing::with('closedBy')->where('school_id', $schoolId)->whereIn('type', ['monthly', 'yearly'])->latest('closed_at')->get();

        return view('pages.keuangan.committee-closing', [
            'title' => 'Tutup Buku Komite', 'description' => 'Menutup periode transaksi dan menghasilkan laporan keuangan Komite (Non-BOS).',
            'years' => $years, 'type' => $type, 'period' => $period, 'preview' => $preview, 'closings' => $closings,
            'reports' => CommitteeClosingService::REPORTS,
        ]);
    }

    public function store(Request $request, SchoolContext $context, CommitteeClosingService $service)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['monthly', 'yearly'])], 'period' => ['required', 'string', 'max:25'],
            'fingerprint' => ['required', 'string', 'size:64'], 'confirmed' => ['accepted'],
        ]);
        $service->close($context->activeSchoolIdFor(), $data['type'], $data['period'], $request->user()->id, $data['fingerprint']);

        return redirect()->route('finance.closing', ['tab' => 'komite', 'type' => $data['type'], 'period' => $data['period']])
            ->with('success', 'Periode berhasil ditutup. Transaksi Komite dikunci dan saldo awal periode berikutnya tersimpan.');
    }

    public function report(Request $request, SchoolContext $context, CommitteeClosingService $service)
    {
        $data = $request->validate([
            'report' => ['required', Rule::in(array_keys(CommitteeClosingService::REPORTS))],
            'closing_id' => ['nullable', 'integer'], 'type' => ['required_without:closing_id', Rule::in(['monthly', 'yearly'])],
            'period' => ['required_without:closing_id', 'string', 'max:25'],
        ]);
        $closing = null;
        if (! empty($data['closing_id'])) {
            $closing = BookClosing::with('closedBy')->where('school_id', $context->activeSchoolIdFor())->whereIn('type', ['monthly', 'yearly'])->findOrFail($data['closing_id']);
            abort_unless(($closing->snapshot['book'] ?? null) === 'komite', 404, 'Paket laporan tidak tersedia untuk snapshot lama.');
            $package = $closing->snapshot;
        } else {
            $package = $service->preview($context->activeSchoolIdFor(), $data['type'], $data['period']);
        }

        return view('pages.keuangan.committee-closing-report', [
            'package' => $package, 'closing' => $closing, 'report' => $data['report'],
            'reportTitle' => CommitteeClosingService::REPORTS[$data['report']],
            'schoolName' => \Illuminate\Support\Facades\DB::table('schools')->where('id', $context->activeSchoolIdFor())->value('name'),
        ]);
    }
}
