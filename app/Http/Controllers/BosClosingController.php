<?php

namespace App\Http\Controllers;

use App\Models\BookClosing;
use App\Services\BosClosingService;
use App\Services\BosIncomeService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BosClosingController extends Controller
{
    private function selection(Request $request, int $schoolId, bool $required = false): array
    {
        $bos = app(BosIncomeService::class);

        return $request->validate([
            'bos_type' => [$required ? 'required' : 'nullable', Rule::in(app(BosClosingService::class)->sources($schoolId)->all())],
            'period' => [$required ? 'required' : 'nullable', 'integer', Rule::in($bos->years($schoolId)->all())],
        ]);
    }

    public function index(Request $request, SchoolContext $context, BosClosingService $service, BosIncomeService $bos)
    {
        $schoolId = $context->activeSchoolIdFor();
        $selection = $this->selection($request, $schoolId);
        $request->validate(['check' => ['nullable', 'in:1'], 'closing_id' => ['nullable', 'integer']]);
        $source = $selection['bos_type'] ?? 'BOS Reguler';
        $year = (int) ($selection['period'] ?? now()->year);
        $preview = $request->boolean('check') ? $service->preview($schoolId, $source, $year) : null;
        $completed = $request->filled('closing_id') ? BookClosing::with('closedBy')->where('school_id', $schoolId)->where('type', 'bos')->findOrFail($request->integer('closing_id')) : null;
        $closings = BookClosing::with('closedBy')->where('school_id', $schoolId)->where('type', 'bos')->latest('closed_at')->get();

        return view('pages.keuangan.bos-closing', [
            'title' => 'Tutup Buku BOS', 'description' => 'Menutup tahun anggaran BOS setelah transaksi diperiksa dan laporan ditinjau.',
            'sources' => $service->sources($schoolId), 'years' => $bos->years($schoolId), 'source' => $source, 'year' => $year,
            'preview' => $preview, 'completed' => $completed, 'closings' => $closings, 'checks' => BosClosingService::CHECKS,
        ]);
    }

    public function store(Request $request, SchoolContext $context, BosClosingService $service)
    {
        $selection = $this->selection($request, $context->activeSchoolIdFor(), true);
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64'], 'confirmed' => ['accepted'], 'notes' => ['nullable', 'string', 'max:200']]);
        $closing = $service->close($context->activeSchoolIdFor(), $selection['bos_type'], (int) $selection['period'], $request->user()->id, $data['fingerprint'], $data['notes'] ?? null);

        return redirect()->route('finance.closing', ['tab' => 'bos', 'bos_type' => $selection['bos_type'], 'period' => $selection['period'], 'closing_id' => $closing->id])
            ->with('success', 'Tahun BOS berhasil ditutup. Snapshot dan saldo awal berikutnya telah disimpan.');
    }

    public function report(Request $request, SchoolContext $context, BosClosingService $service)
    {
        $data = $request->validate(['closing_id' => ['nullable', 'integer'], 'report' => ['required', Rule::in(['financial', 'receipt'])]]);
        $closing = null;
        if ($request->filled('closing_id')) {
            $closing = BookClosing::with('closedBy')->where('school_id', $context->activeSchoolIdFor())->where('type', 'bos')->findOrFail($data['closing_id']);
            abort_unless(($closing->snapshot['book'] ?? null) === 'bos', 404, 'Paket laporan belum tersedia untuk snapshot lama.');
            $package = $closing->snapshot;
        } else {
            $selection = $this->selection($request, $context->activeSchoolIdFor(), true);
            $package = $service->preview($context->activeSchoolIdFor(), $selection['bos_type'], (int) $selection['period']);
        }

        return view('pages.keuangan.bos-closing-report', [
            'package' => $package, 'closing' => $closing, 'report' => $data['report'], 'checks' => BosClosingService::CHECKS,
            'schoolName' => \Illuminate\Support\Facades\DB::table('schools')->where('id', $context->activeSchoolIdFor())->value('name'),
        ]);
    }
}
