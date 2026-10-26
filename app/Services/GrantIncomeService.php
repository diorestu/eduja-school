<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\FinanceIncome;
use App\Models\FundAllocation;
use App\Models\GrantIncomeRevision;
use App\Models\IncomeType;
use App\Models\SchoolAccount;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrantIncomeService
{
    protected string $category = 'hibah';

    protected string $label = 'Hibah';

    protected string $identityField = 'donor_name';

    protected string $allocationColumn = 'grant_allocations';

    protected string $submissionColumn = 'grant_submission_key';

    protected string $fingerprintColumn = 'grant_fingerprint';

    protected string $incomeScope = 'grant';

    protected string $revisionRelation = 'grantRevisions';

    protected string $revisionModel = GrantIncomeRevision::class;

    protected string $approvalType = 'grant_income_revision';

    protected function validateIdentity(array $attributes): void {}

    public function types(int $schoolId)
    {
        return IncomeType::where('school_id', $schoolId)->whereRaw('LOWER(category) = ?', [$this->category])->orderBy('name');
    }

    public function accounts(int $schoolId, string $method)
    {
        return SchoolAccount::where('school_id', $schoolId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('source_funding')->orWhere('source_funding', '!=', 'bos'))
            ->whereIn(DB::raw('LOWER(type)'), $method === 'Tunai' ? ['cash', 'tunai'] : ['bank'])->orderBy('id');
    }

    private function account(int $schoolId, string $method, ?int $id): SchoolAccount
    {
        if ($method === 'Transfer' && ! $id) {
            throw ValidationException::withMessages(['account_id' => 'Pilih rekening bank tujuan pembayaran.']);
        }
        $accounts = $this->accounts($schoolId, $method)->when($id, fn ($q) => $q->whereKey($id))->lockForUpdate()->get();
        if ($accounts->count() !== 1) {
            throw ValidationException::withMessages(['account_id' => 'Pilih rekening aktif milik sekolah sesuai metode pembayaran. Jika terdapat beberapa kas, pilih kas tujuan.']);
        }

        return $accounts->first();
    }

    private function typeId(int $schoolId, ?int $id): ?int
    {
        $types = $this->types($schoolId)->get();
        if ($id && ! $types->contains('id', $id)) {
            throw ValidationException::withMessages(['income_type_id' => "Jenis pemasukan harus berkategori {$this->label} dan milik sekolah aktif."]);
        }
        if (! $id && $types->count() > 1) {
            throw ValidationException::withMessages(['income_type_id' => "Pilih jenis pemasukan {$this->label} agar alokasi dana sesuai."]);
        }

        return $id ?? $types->first()?->id;
    }

    private function allocations(int $schoolId, ?int $typeId, int $amount): array
    {
        if (! $typeId) {
            return [];
        }
        $rules = [];
        foreach (FundAllocation::where('school_id', $schoolId)->where('income_type_id', $typeId)->where('status', 'active')->whereNotNull('virtual_wallet_id')->orderBy('virtual_wallet_id')->get() as $allocation) {
            if (! VirtualWallet::where('school_id', $schoolId)->whereKey($allocation->virtual_wallet_id)->whereIn(DB::raw('LOWER(status)'), ['active', 'aktif'])->exists()) {
                throw ValidationException::withMessages(['amount' => "Dompet pada konfigurasi alokasi {$this->label} harus aktif dan milik sekolah."]);
            }
            $percentage = in_array(strtolower($allocation->method), ['percentage', 'persentase', 'percent', '%'], true);
            if (! $percentage && ! in_array(strtolower($allocation->method), ['nominal', 'fixed'], true)) {
                throw ValidationException::withMessages(['amount' => "Metode alokasi {$this->label} tidak valid."]);
            }
            if (str_starts_with($allocation->amount, '-')) {
                throw ValidationException::withMessages(['amount' => "Besaran alokasi {$this->label} tidak boleh negatif."]);
            }
            $value = BillingPaymentService::cents($allocation->amount);
            if ($value < 0 || ($percentage && $value > 10000)) {
                throw ValidationException::withMessages(['amount' => "Besaran alokasi {$this->label} tidak valid."]);
            }
            $rules[] = ['wallet_id' => (int) $allocation->virtual_wallet_id, 'percentage' => $percentage, 'value' => $value];
        }

        return $this->calculate($rules, $amount);
    }

    private function calculate(array $rules, int $amount): array
    {
        $result = [];
        $remainders = [];
        $percentageValue = 0;
        $percentageCents = 0;
        $fixedCents = 0;
        foreach ($rules as $index => $rule) {
            if ($rule['percentage']) {
                $percentageValue += $rule['value'];
                $cents = intdiv($amount, 10000) * $rule['value'] + intdiv(($amount % 10000) * $rule['value'], 10000);
                $remainders[] = ['index' => $index, 'wallet_id' => $rule['wallet_id'], 'remainder' => (($amount % 10000) * $rule['value']) % 10000];
                $percentageCents += $cents;
            } else {
                $cents = min($amount, $rule['value']);
                $fixedCents += $cents;
            }
            $result[$index] = [...$rule, 'cents' => $cents];
        }
        if ($percentageValue > 10000) {
            throw ValidationException::withMessages(['amount' => "Total persentase alokasi {$this->label} melebihi 100%."]);
        }
        $target = intdiv($amount, 10000) * $percentageValue + intdiv(($amount % 10000) * $percentageValue + 5000, 10000);
        if ($target + $fixedCents > $amount) {
            throw ValidationException::withMessages(['amount' => "Total alokasi {$this->label} melebihi nominal. Perbaiki konfigurasi alokasi dana."]);
        }
        usort($remainders, fn ($a, $b) => ($b['remainder'] <=> $a['remainder']) ?: ($a['wallet_id'] <=> $b['wallet_id']) ?: ($a['index'] <=> $b['index']));
        foreach (array_slice($remainders, 0, $target - $percentageCents) as $remainder) {
            $result[$remainder['index']]['cents']++;
        }

        return $result;
    }

    public function create(int $schoolId, int $actorId, array $attributes): FinanceIncome
    {
        return DB::transaction(function () use ($schoolId, $actorId, $attributes) {
            $this->validateIdentity($attributes);
            // Serialize retries even when a resubmission changes its destination account.
            DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
            $account = $this->account($schoolId, $attributes['payment_method'], ! empty($attributes['account_id']) ? (int) $attributes['account_id'] : null);
            $typeId = $this->typeId($schoolId, ! empty($attributes['income_type_id']) ? (int) $attributes['income_type_id'] : null);
            $fingerprint = hash('sha256', json_encode([$actorId, $account->id, $typeId, $attributes[$this->identityField], BillingPaymentService::cents($attributes['amount']), $attributes['received_date'], $attributes['payment_method'], $attributes['proof_hash'] ?? null]));
            if (! empty($attributes[$this->submissionColumn]) && ($existing = FinanceIncome::where('school_id', $schoolId)->where($this->submissionColumn, $attributes[$this->submissionColumn])->first())) {
                if (! hash_equals($existing->{$this->fingerprintColumn} ?? '', $fingerprint)) {
                    throw ValidationException::withMessages([$this->submissionColumn => 'Form telah digunakan dengan data berbeda. Muat ulang halaman untuk penerimaan baru.']);
                }

                return $existing;
            }
            $amount = BillingPaymentService::cents($attributes['amount']);
            $allocations = $this->allocations($schoolId, $typeId, $amount);
            $income = FinanceIncome::create(['school_id' => $schoolId, 'income_category' => $this->category, 'source_funding' => "{$this->label}", 'income_type_id' => $typeId, 'account_id' => $account->id,
                $this->identityField => $attributes[$this->identityField], 'amount' => BillingPaymentService::decimal($amount), 'received_date' => $attributes['received_date'],
                'payment_method' => $attributes['payment_method'], 'proof_path' => $attributes['proof_path'] ?? null, 'status' => 'approved', 'posted_at' => now(),
                'received_by' => $actorId, $this->allocationColumn => $allocations, $this->submissionColumn => $attributes[$this->submissionColumn] ?? null, $this->fingerprintColumn => $fingerprint]);
            $account->increment('current_balance', $income->amount);
            $this->walletDeltas($schoolId, [], $allocations, $income);

            return $income;
        });
    }

    public function snapshot(FinanceIncome $income): array
    {
        return [$this->identityField => $income->{$this->identityField}, 'amount' => $income->amount, 'received_date' => $income->received_date->toDateString(),
            'payment_method' => $income->payment_method, 'account_id' => $income->account_id === null ? null : (int) $income->account_id,
            'income_type_id' => $income->income_type_id === null ? null : (int) $income->income_type_id, 'proof_path' => $income->proof_path,
            $this->allocationColumn => $income->{$this->allocationColumn}, 'status' => $income->status];
    }

    public function requestRevision(int $schoolId, FinanceIncome $income, int $actorId, array $attributes): GrantIncomeRevision
    {
        return DB::transaction(function () use ($schoolId, $income, $actorId, $attributes) {
            $this->validateIdentity($attributes);
            $income = FinanceIncome::where('school_id', $schoolId)->{$this->incomeScope}()->whereKey($income->id)->lockForUpdate()->firstOrFail();
            if (! in_array($income->status, ['approved', 'confirmed'], true) || ! $income->posted_at) {
                throw ValidationException::withMessages(['reason' => "Revisi hanya untuk penerimaan {$this->label} yang sudah tercatat."]);
            }
            if ($income->{$this->revisionRelation}()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => 'Masih ada revisi menunggu keputusan kepala sekolah.']);
            }
            app(FinancePeriodGuard::class)->assertOpen($schoolId, $income->received_date, $income->source_funding);
            app(FinancePeriodGuard::class)->assertOpen($schoolId, $attributes['received_date'], $income->source_funding);
            $old = $this->snapshot($income);
            $method = $attributes['payment_method'];
            $id = ! empty($attributes['account_id']) ? (int) $attributes['account_id'] : ($method === $income->payment_method ? $income->account_id : null);
            $account = $this->account($schoolId, $method, $id);
            $typeId = $this->typeId($schoolId, ! empty($attributes['income_type_id']) ? (int) $attributes['income_type_id'] : null);
            $amount = BillingPaymentService::cents($attributes['amount']);
            $moneyChanged = $amount !== BillingPaymentService::cents($income->amount) || $typeId !== $income->income_type_id;
            if ($income->{$this->allocationColumn} === null && $moneyChanged && FundAllocation::where('school_id', $schoolId)->exists()) {
                throw ValidationException::withMessages(['reason' => "Alokasi penerimaan lama belum memiliki jejak. Rekonsiliasi alokasi lama sebelum mengubah nominal atau jenis {$this->label}."]);
            }
            $allocations = $typeId === $income->income_type_id ? ($income->{$this->allocationColumn} === null ? null : $this->calculate($income->{$this->allocationColumn}, $amount)) : $this->allocations($schoolId, $typeId, $amount);
            $new = [...$old, $this->identityField => $attributes[$this->identityField], 'amount' => BillingPaymentService::decimal($amount), 'received_date' => $attributes['received_date'],
                'payment_method' => $method, 'account_id' => (int) $account->id, 'income_type_id' => $typeId, 'proof_path' => $attributes['proof_path'] ?? $income->proof_path, $this->allocationColumn => $allocations];
            if ($old === $new) {
                throw ValidationException::withMessages(['reason' => 'Tidak ada data penerimaan yang berubah.']);
            }
            $revision = $this->revisionModel::create(['school_id' => $schoolId, 'finance_income_id' => $income->id, 'old_data' => $old, 'new_data' => $new,
                'fingerprint' => hash('sha256', json_encode($old)), 'reason' => $attributes['reason'], 'requested_by' => $actorId, 'status' => 'pending']);
            ApprovalRequest::create(['school_id' => $schoolId, 'requested_by' => $actorId, 'approvable_type' => $this->revisionModel, 'approvable_id' => $revision->id, 'type' => $this->approvalType, 'status' => 'pending', 'note' => $attributes['reason']]);

            return $revision;
        });
    }

    public function apply(GrantIncomeRevision $revision, string $status, int $reviewerId): void
    {
        DB::transaction(function () use ($revision, $status, $reviewerId) {
            $revision = $this->revisionModel::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->applied_at) {
                return;
            }
            if ($status !== 'approved') {
                $revision->update(['status' => $status, 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);

                return;
            }
            $income = FinanceIncome::where('school_id', $revision->school_id)->{$this->incomeScope}()->whereKey($revision->finance_income_id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($revision->fingerprint, hash('sha256', json_encode($this->snapshot($income))))) {
                throw ValidationException::withMessages(['revision' => 'Data penerimaan telah berubah. Tolak pengajuan dan ajukan revisi dari data terkini.']);
            }
            $old = $revision->old_data;
            $new = $revision->new_data;
            app(FinancePeriodGuard::class)->assertOpen($revision->school_id, $old['received_date'], $income->source_funding);
            app(FinancePeriodGuard::class)->assertOpen($revision->school_id, $new['received_date'], $income->source_funding);
            $target = $this->account($revision->school_id, $new['payment_method'], $new['account_id']);
            $deltas = [];
            if ($old['account_id']) {
                $deltas[$old['account_id']] = -BillingPaymentService::cents($old['amount']);
            }
            $deltas[$target->id] = ($deltas[$target->id] ?? 0) + BillingPaymentService::cents($new['amount']);
            ksort($deltas);
            foreach ($deltas as $id => $delta) {
                $account = SchoolAccount::where('school_id', $revision->school_id)->whereKey($id)->lockForUpdate()->firstOrFail();
                if ($delta < 0 && BillingPaymentService::cents($account->current_balance) < -$delta) {
                    throw ValidationException::withMessages(['revision' => "Saldo rekening asal tidak cukup untuk koreksi {$this->label}."]);
                }
                if ($delta > 0) {
                    $account->increment('current_balance', BillingPaymentService::decimal($delta));
                }
                if ($delta < 0) {
                    $account->decrement('current_balance', BillingPaymentService::decimal(-$delta));
                }
            }
$this->walletDeltas($revision->school_id, $old[$this->allocationColumn] ?? [], $new[$this->allocationColumn] ?? [], $income, $old['received_date'], $new['received_date']);
            $income->update([...$new, 'revised_by' => $revision->requested_by, 'revised_at' => now()]);
            $revision->update(['status' => 'approved', 'reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'applied_at' => now()]);
        });
    }

    private function walletDeltas(int $schoolId, array $old, array $new, FinanceIncome $income, mixed $oldDate = null, mixed $newDate = null): void
    {
        $deltas = [];
        foreach ($old as $rule) {
            $deltas[$rule['wallet_id']] = ($deltas[$rule['wallet_id']] ?? 0) - $rule['cents'];
        }
        foreach ($new as $rule) {
            $deltas[$rule['wallet_id']] = ($deltas[$rule['wallet_id']] ?? 0) + $rule['cents'];
        }
        ksort($deltas);
        foreach ($deltas as $id => $delta) {
            $wallet = VirtualWallet::where('school_id', $schoolId)->whereKey($id)->lockForUpdate()->first();
            if (! $wallet || ($delta > 0 && ! in_array(strtolower($wallet->status), ['active', 'aktif'], true))) {
                throw ValidationException::withMessages(['revision' => "Dompet alokasi {$this->label} tidak tersedia atau tidak aktif."]);
            }
            if ($delta < 0 && BillingPaymentService::cents($wallet->nominal) < -$delta) {
                throw ValidationException::withMessages(['revision' => "Saldo dompet virtual tidak cukup untuk koreksi alokasi {$this->label}."]);
            }
            foreach ([[$old, -1, $oldDate ?? $income->received_date], [$new, 1, $newDate ?? $income->received_date]] as [$rules, $sign, $date]) {
                $cents = array_sum(array_map(fn ($rule) => (int) $rule['wallet_id'] === (int) $id ? $rule['cents'] : 0, $rules));
                if ($cents) {
                    \App\Models\FinanceWalletMovement::create(['school_id' => $schoolId, 'virtual_wallet_id' => $wallet->id,
                        'amount' => ($sign < 0 ? '-' : '').BillingPaymentService::decimal($cents), 'effective_date' => $date,
                        'source_type' => $income->getMorphClass(), 'source_id' => $income->id]);
                }
            }
            if ($delta > 0) { $wallet->increment('nominal', BillingPaymentService::decimal($delta)); }
            if ($delta < 0) { $wallet->decrement('nominal', BillingPaymentService::decimal(-$delta)); }
        }
    }
}
