<?php

namespace App\Services;

use App\Models\BookClosing;
use App\Models\BudgetYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancePeriodGuard
{
    public function bounds(int $schoolId, string $type, string $period): array
    {
        if ($type === 'monthly' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $start = Carbon::createFromFormat('!Y-m', $period);

            return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString(), null, null];
        }
        if ($type === 'yearly') {
            $year = BudgetYear::where('school_id', $schoolId)->whereKey($period)->first();
            if ($year) {
                return [$year->start_date->toDateString(), $year->end_date->toDateString(), null, $year->id];
            }
            if (preg_match('/^20\d{2}$/', $period)) {
                return [$period.'-01-01', $period.'-12-31', null, null];
            }
        }
        if ($type === 'bos' && preg_match('/^(?:(.+) )?(20\d{2})(?:-(0[1-9]|1[0-2]))?$/', $period, $matches)) {
            $source = trim($matches[1] ?? '') ?: null;
            $date = $matches[2].(! empty($matches[3]) ? '-'.$matches[3] : '-01');
            $start = Carbon::createFromFormat('!Y-m', $date);

            return [$start->toDateString(), (! empty($matches[3]) ? $start->copy()->endOfMonth() : $start->copy()->endOfYear())->toDateString(), $source, null];
        }
        throw ValidationException::withMessages(['period' => 'Pilih periode bulanan, tahun anggaran sekolah, atau tahun BOS yang valid.']);
    }

    public function assertOpen(int $schoolId, mixed $date, ?string $source = null, ?int $bosYear = null): void
    {
        if (! $date) {
            return;
        }
        // The same school row serialises closing with every transactional financial mutation.
        DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
        $date = Carbon::parse($date)->toDateString();
        foreach (BookClosing::where('school_id', $schoolId)->where('status', 'closed')->get() as $closing) {
            [$start, $end, $funding] = $closing->period_start
                ? [$closing->period_start, $closing->period_end, $closing->source_funding]
                : $this->bounds($schoolId, $closing->type, $closing->period);
            $matches = $date >= $start && $date <= $end;
            if (($closing->snapshot['book'] ?? null) === 'komite') {
                $matches = $matches && ! ($bosYear || preg_match('/^(bos|bop|dak)/i', $source ?? ''));
            }
            if (($closing->snapshot['book'] ?? null) === 'bos') {
                $isBos = preg_match('/^(bos|bop|dak)/i', $source ?? '') || $bosYear;
                $matches = $isBos && (! $funding || strcasecmp($funding, $source ?? '') === 0)
                    && ($bosYear ? $bosYear === (int) $closing->snapshot['year'] : $matches);
            } elseif ($closing->type === 'bos') {
                $isBos = preg_match('/^(bos|bop|dak)/i', $source ?? '') || $bosYear;
                $matches = $matches || ($bosYear && substr($start, 0, 4) === (string) $bosYear && substr($end, 5) === '12-31');
                $matches = $matches && $isBos && (! $funding || strcasecmp($funding, $source ?? '') === 0);
            }
            if ($matches) {
                throw ValidationException::withMessages(['period' => 'Transaksi berada dalam periode '.$closing->period.' yang sudah ditutup. Pengguna berwenang harus membuka kembali periode sebelum koreksi.']);
            }
        }
    }

    public function assertBosPhysicalOpen(int $schoolId, mixed $date): void
    {
        if (! $date) {
            return;
        }
        DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
        $date = Carbon::parse($date)->toDateString();
        foreach (BookClosing::where('school_id', $schoolId)->where('type', 'bos')->where('status', 'closed')->get() as $closing) {
            [$start, $end] = $closing->period_start ? [$closing->period_start, $closing->period_end] : $this->bounds($schoolId, 'bos', $closing->period);
            if ($date >= $start && $date <= $end) {
                throw ValidationException::withMessages(['period' => 'Mutasi rekening/dompet BOS bersama berada dalam tahun yang sudah ditutup. Buka kembali periode melalui pengguna berwenang sebelum koreksi.']);
            }
        }
    }
}
