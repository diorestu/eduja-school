<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BosIncomeRevision;
use App\Models\BudgetCategory;
use App\Models\FinanceIncome;
use App\Models\IncomeType;
use App\Models\SchoolAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BosIncomeService
{
    public function sources(int $schoolId): Collection
    {
        return collect(['BOS Reguler', 'BOS Kinerja', 'BOS Afirmasi', 'BOS Daerah', 'BOP', 'DAK'])
            ->merge(IncomeType::where('school_id', $schoolId)->whereRaw('LOWER(category) = ?', ['bos'])->pluck('name'))
            ->merge(BudgetCategory::where('school_id', $schoolId)->where(fn ($q) => $q->where('source_funding', 'like', '%BOS%')->orWhere('source_funding', 'like', '%BOP%')->orWhere('source_funding', 'like', '%DAK%'))->pluck('source_funding'))
            ->filter()->unique()->sort()->values();
    }

    public function years(int $schoolId): Collection
    {
        return collect(range(2020, max(2020, now()->year + 1)))
            ->merge(FinanceIncome::where('school_id', $schoolId)->whereNotNull('bos_year')->pluck('bos_year'))
            ->merge(\App\Models\BudgetYear::where('school_id', $schoolId)->pluck('year'))
            ->map(fn ($year) => (int) $year)->filter(fn ($year) => $year >= 2020 && $year <= 2099)->unique()->sortDesc()->values();
    }

    public function accounts(int $schoolId)
    {
        return SchoolAccount::where('school_id', $schoolId)->where('is_active', true)
            ->where(fn ($q) => $q->where('source_funding', 'bos')->orWhereRaw('LOWER(type) = ?', ['bos']))->orderBy('id');
    }

    private function account(int $schoolId, ?int $id): SchoolAccount
    {
        $accounts = $this->accounts($schoolId)->when($id, fn ($q) => $q->whereKey($id))->lockForUpdate()->get();
        if ($accounts->count() !== 1) {
            throw ValidationException::withMessages(['account_id' => $id ? 'Rekening tujuan harus aktif, ditandai BOS, dan milik sekolah yang sedang aktif.' : ($accounts->isEmpty() ? 'Belum ada rekening BOS aktif. Tandai kategori dana rekening sebagai BOS di Rekening Sekolah.' : 'Pilih rekening tujuan karena terdapat beberapa rekening BOS aktif.')]);
        }

        return $accounts->first();
    }

    public function create(int $schoolId, int $actorId, array $attributes): FinanceIncome
    {
        return DB::transaction(function () use ($schoolId, $actorId, $attributes) {
            $account = $this->account($schoolId, ! empty($attributes['account_id']) ? (int) $attributes['account_id'] : null);
            $fingerprint = hash('sha256', json_encode([$actorId, $account->id, $attributes['source_funding'], (int) $attributes['bos_year'], BillingPaymentService::cents($attributes['amount']), $attributes['received_date'], $attributes['proof_hash'] ?? null]));
            if (! empty($attributes['bos_submission_key'])) {
                $existing = FinanceIncome::where('school_id', $schoolId)->where('bos_submission_key', $attributes['bos_submission_key'])->first();
                if ($existing) {
                    if (! hash_equals($existing->bos_fingerprint ?? '', $fingerprint)) {
                        throw ValidationException::withMessages(['bos_submission_key' => 'Form ini sudah digunakan dengan data berbeda. Muat ulang halaman untuk penerimaan baru.']);
                    }

                    return $existing;
                }
            }
            $type = IncomeType::where('school_id', $schoolId)->whereRaw('LOWER(category) = ?', ['bos'])->where('name', $attributes['source_funding'])->first();
            $income = app(FinanceService::class)->recordBosIncome($schoolId, [...$attributes, 'income_type_id' => $type?->id, 'account_id' => $account->id, 'payment_method' => 'Transfer Bank']);
            $income->update(['received_by' => $actorId, 'bos_submission_key' => $attributes['bos_submission_key'] ?? null, 'bos_fingerprint' => $fingerprint]);

            return $income;
        });
    }

    public function snapshot(FinanceIncome $income): array
    {
        return ['source_funding' => $income->source_funding, 'bos_year' => $income->bos_year === null ? null : (int) $income->bos_year,
            'amount' => $income->amount, 'received_date' => $income->received_date->toDateString(),
            'proof_path' => $income->proof_path, 'account_id' => $income->account_id === null ? null : (int) $income->account_id,
            'status' => $income->status];
    }

    public function requestRevision(int $schoolId, FinanceIncome $income, int $actorId, array $attributes): BosIncomeRevision
    {
        return DB::transaction(function () use ($schoolId, $income, $actorId, $attributes) {
            $income = FinanceIncome::where('school_id', $schoolId)->bos()->whereKey($income->id)->lockForUpdate()->firstOrFail();
            if (! in_array($income->status, ['approved', 'confirmed'], true) || ! $income->posted_at) {
                throw ValidationException::withMessages(['reason' => 'Revisi hanya dapat diajukan untuk penerimaan BOS yang sudah tercatat.']);
            }
            if ($income->revisions()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => 'Masih ada revisi menunggu persetujuan kepala sekolah.']);
            }
            app(FinancePeriodGuard::class)->assertOpen($schoolId, $income->received_date, $income->source_funding, $income->bos_year);
            app(FinancePeriodGuard::class)->assertOpen($schoolId, $attributes['received_date'], $attributes['source_funding'], (int) $attributes['bos_year']);
            $old = $this->snapshot($income);
            $accountId = $income->account_id ?? $this->account($schoolId, ! empty($attributes['account_id']) ? (int) $attributes['account_id'] : null)->id;
            $new = [...$old, 'source_funding' => $attributes['source_funding'], 'bos_year' => (int) $attributes['bos_year'],
                'amount' => BillingPaymentService::decimal(BillingPaymentService::cents($attributes['amount'])),
                'received_date' => $attributes['received_date'], 'proof_path' => $attributes['proof_path'] ?? $income->proof_path,
                'account_id' => (int) $accountId, 'status' => 'approved'];
            if (collect($old)->except('status')->all() === collect($new)->except('status')->all()) {
                throw ValidationException::withMessages(['reason' => 'Tidak ada data penerimaan yang berubah.']);
            }
            $revision = BosIncomeRevision::create(['school_id' => $schoolId, 'finance_income_id' => $income->id, 'old_data' => $old, 'new_data' => $new,
                'fingerprint' => hash('sha256', json_encode($old)), 'reason' => $attributes['reason'], 'requested_by' => $actorId, 'status' => 'pending']);
            ApprovalRequest::create(['school_id' => $schoolId, 'requested_by' => $actorId, 'approvable_type' => BosIncomeRevision::class,
                'approvable_id' => $revision->id, 'type' => 'bos_income_revision', 'status' => 'pending', 'note' => $attributes['reason']]);

            return $revision;
        });
    }

    public function apply(BosIncomeRevision $revision, string $status, int $reviewerId): void
    {
        DB::transaction(function () use ($revision, $status, $reviewerId) {
            $revision = BosIncomeRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->applied_at) {
                return;
            }
            if ($status !== 'approved') {
                $revision->update(['status' => $status, 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);

                return;
            }
            $income = FinanceIncome::where('school_id', $revision->school_id)->bos()->whereKey($revision->finance_income_id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($revision->fingerprint, hash('sha256', json_encode($this->snapshot($income))))) {
                throw ValidationException::withMessages(['revision' => 'Data penerimaan telah berubah. Tolak pengajuan ini dan ajukan revisi baru berdasarkan data terkini.']);
            }
            $new = $revision->new_data;
            app(FinancePeriodGuard::class)->assertOpen($revision->school_id, $income->received_date, $income->source_funding, $income->bos_year);
            app(FinancePeriodGuard::class)->assertOpen($revision->school_id, $new['received_date'], $new['source_funding'], (int) $new['bos_year']);
            $account = $income->account_id
                ? SchoolAccount::where('school_id', $revision->school_id)->whereKey($new['account_id'])->lockForUpdate()->firstOrFail()
                : $this->account($revision->school_id, (int) $new['account_id']);
            $oldCents = $income->account_id ? BillingPaymentService::cents($income->amount) : 0;
            $delta = BillingPaymentService::cents($new['amount']) - $oldCents;
            if ($delta < 0 && BillingPaymentService::cents($account->current_balance) < -$delta) {
                throw ValidationException::withMessages(['revision' => 'Saldo rekening tidak cukup untuk koreksi nominal. Rekonsiliasi saldo sebelum menyetujui revisi.']);
            }
            if ($delta > 0) {
                $account->increment('current_balance', BillingPaymentService::decimal($delta));
            } elseif ($delta < 0) {
                $account->decrement('current_balance', BillingPaymentService::decimal(-$delta));
            }
            $type = IncomeType::where('school_id', $revision->school_id)->whereRaw('LOWER(category) = ?', ['bos'])->where('name', $new['source_funding'])->first();
            $income->update([...$new, 'income_type_id' => $type?->id, 'revised_by' => $revision->requested_by, 'revised_at' => now()]);
            $revision->update(['status' => 'approved', 'reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'applied_at' => now()]);
        });
    }
}
