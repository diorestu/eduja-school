<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\SchoolAccount;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpensePostingService
{
    public function sources(int $schoolId, array $attributes): array
    {
        $amount = BillingPaymentService::cents($attributes['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran harus lebih dari nol.']);
        }
        $committee = strtolower($attributes['source_funding'] ?? '') === 'komite';
        $method = $attributes['payment_method'] ?? '';
        if ($committee && ! in_array($method, ['Tunai', 'Transfer', 'Transfer Bank'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Pilih metode Tunai atau Transfer.']);
        }
        $accountId = $attributes['account_id'] ?? null;
        if ($committee && ! $accountId) {
            $cash = SchoolAccount::where('school_id', $schoolId)->where('is_active', true)
                ->whereIn(DB::raw('LOWER(type)'), ['cash', 'tunai'])
                ->where(fn ($q) => $q->whereNull('source_funding')->orWhereRaw('LOWER(source_funding) != ?', ['bos']))->orderBy('id')->lockForUpdate()->get();
            if ($method !== 'Tunai' || $cash->count() !== 1) {
                throw ValidationException::withMessages(['account_id' => 'Pilih kas atau rekening bank pembayaran sesuai metode.']);
            }
            $accountId = $cash->first()->id;
        }
        $account = null;
        if ($accountId) {
            $account = SchoolAccount::where('school_id', $schoolId)->where('is_active', true)->whereKey($accountId)->lockForUpdate()->first();
            $types = $method === 'Tunai' ? ['cash', 'tunai'] : ['bank'];
            if (! $account || ($committee && (! in_array(strtolower($account->type), $types, true) || strtolower($account->source_funding ?? '') === 'bos'))) {
                throw ValidationException::withMessages(['account_id' => 'Kas/rekening harus aktif, milik sekolah, dan sesuai metode pembayaran Komite.']);
            }
            if ($amount > BillingPaymentService::cents($account->current_balance)) {
                throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran melebihi saldo kas/rekening pembayaran.']);
            }
        }
        $wallet = null;
        if ($committee && ! empty($attributes['virtual_wallet_id'])) {
            $wallet = VirtualWallet::where('school_id', $schoolId)->whereKey($attributes['virtual_wallet_id'])->where('status', 'active')->lockForUpdate()->first();
            if (! $wallet) {
                throw ValidationException::withMessages(['virtual_wallet_id' => 'Dompet sumber harus aktif dan milik sekolah yang sedang aktif.']);
            }
            if ($amount > BillingPaymentService::cents($wallet->nominal)) {
                throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran melebihi saldo dompet virtual sumber dana.']);
            }
        }
        if (! empty($attributes['expense_type_id']) && ! ExpenseType::where('school_id', $schoolId)->whereKey($attributes['expense_type_id'])
            ->when($committee, fn ($q) => $q->whereRaw('LOWER(source_funding) = ?', ['komite']))->exists()) {
            throw ValidationException::withMessages(['expense_type_id' => 'Pilih jenis pengeluaran yang sesuai dan milik sekolah aktif.']);
        }

        return [$account, $wallet];
    }

    public function post(Expense $expense, int $reviewerId): void
    {
        DB::transaction(function () use ($expense, $reviewerId) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->posted_at) {
                return;
            }
            app(FinancePeriodGuard::class)->assertOpen($expense->school_id, $expense->transaction_date, $expense->source_funding, $expense->bos_year);
            if (preg_match('/^(bos|bop|dak)/i', $expense->source_funding) && $expense->budget_plan_id) {
                $plan = \App\Models\BudgetPlan::where('school_id', $expense->school_id)->whereKey($expense->budget_plan_id)->lockForUpdate()->first();
                $year = $plan?->budgetYear;
                if (! $plan || $plan->status !== 'approved' || $year?->status !== 'active'
                    || $expense->transaction_date->lt($year->start_date) || $expense->transaction_date->gt($year->end_date)
                    || (strcasecmp($plan->source_funding, $expense->source_funding) !== 0 && strcasecmp($expense->source_funding, 'BOS') !== 0)) {
                    throw ValidationException::withMessages(['budget_plan_id' => 'Pilih kegiatan BOS yang sudah disetujui pada tahun anggaran aktif sesuai sumber dana dan tanggal transaksi.']);
                }
                $spent = \App\Models\Expense::where('school_id', $expense->school_id)->where('budget_plan_id', $plan->id)->whereNotNull('posted_at')->sum('amount');
                if (BillingPaymentService::cents($spent) + BillingPaymentService::cents($expense->amount) > BillingPaymentService::cents($plan->amount)) {
                    throw ValidationException::withMessages(['amount' => 'Nominal belanja melebihi sisa anggaran kegiatan BOS.']);
                }
                $bosAccount = SchoolAccount::where('school_id', $expense->school_id)->whereKey($expense->account_id)->first();
                if ($bosAccount && (strtolower($bosAccount->source_funding ?? '') !== 'bos' && strtolower($bosAccount->type) !== 'bos')) {
                    throw ValidationException::withMessages(['account_id' => 'Pilih rekening BOS milik sekolah.']);
                }
                if ($expense->budget_category_id && (int) $expense->budget_category_id !== (int) $plan->budget_category_id) {
                    throw ValidationException::withMessages(['budget_category_id' => 'Komponen belanja harus sesuai kegiatan BOS.']);
                }
            }
            [$account, $wallet] = $this->sources($expense->school_id, $expense->getAttributes());
            $account?->decrement('current_balance', $expense->amount);
            if ($wallet) {
                app(FinanceWalletService::class)->adjust($wallet, -BillingPaymentService::cents($expense->amount), $expense->transaction_date, $expense);
            }
            $expense->forceFill(['account_id' => $account?->id, 'virtual_wallet_id' => $wallet?->id, 'posted_at' => now(), 'posted_by' => $reviewerId])->save();
        });
    }
}
