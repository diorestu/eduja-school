<?php

namespace App\Observers;

use App\Services\FinancePeriodGuard;
use Illuminate\Database\Eloquent\Model;

class FinancePeriodObserver
{
    public function saving(Model $model): void
    {
        $this->check($model);
    }

    public function deleting(Model $model): void
    {
        $this->check($model);
    }

    private function check(Model $model): void
    {
        $field = match ($model->getTable()) {
            'finance_incomes' => 'received_date',
            'expenses' => 'transaction_date',
            'finance_account_transfers' => 'transfer_date',
            'finance_wallet_movements' => 'effective_date',
            default => 'payment_date',
        };
        $schoolId = $model->school_id ?? $model->invoice?->school_id ?? $model->invoice?->student?->school_id;
        if (! $schoolId) {
            return;
        }
        $guard = app(FinancePeriodGuard::class);
        if ($model instanceof \App\Models\FinanceAccountTransfer) {
            foreach ($model->exists ? [true, false] : [false] as $original) {
                $school = $original ? $model->getRawOriginal('school_id') : $schoolId;
                $date = $original ? $model->getRawOriginal($field) : $model->getAttribute($field);
                $ids = $original ? [$model->getRawOriginal('from_account_id'), $model->getRawOriginal('to_account_id')] : [$model->from_account_id, $model->to_account_id];
                foreach (\App\Models\SchoolAccount::where('school_id', $school)->whereIn('id', $ids)->get() as $account) {
                    $isBos = preg_match('/^(bos|bop|dak)/i', $account->source_funding ?? '') || strcasecmp($account->type, 'bos') === 0;
                    if ($isBos) {
                        $guard->assertBosPhysicalOpen((int) $school, $date);
                    }
                    $guard->assertOpen((int) $school, $date, $isBos ? ($account->source_funding ?: 'bos') : 'Komite');
                }
            }

            return;
        }
        if ($model instanceof \App\Models\FinanceWalletMovement) {
            foreach ($model->exists ? [true, false] : [false] as $original) {
                $school = $original ? $model->getRawOriginal('school_id') : $schoolId;
                $date = $original ? $model->getRawOriginal($field) : $model->getAttribute($field);
                $sourceType = $original ? $model->getRawOriginal('source_type') : $model->source_type;
                $sourceId = $original ? $model->getRawOriginal('source_id') : $model->source_id;
                $sourceClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($sourceType) ?? $sourceType;
                $record = in_array($sourceClass, [\App\Models\Expense::class, \App\Models\FinanceIncome::class], true)
                    ? $sourceClass::where('school_id', $school)->find($sourceId) : null;
                $wallet = \App\Models\VirtualWallet::where('school_id', $school)->find($original ? $model->getRawOriginal('virtual_wallet_id') : $model->virtual_wallet_id);
                if (! $record && preg_match('/^(bos|bop|dak)/i', $wallet?->source ?? '')) {
                    $guard->assertBosPhysicalOpen((int) $school, $date);
                }
                $guard->assertOpen((int) $school, $date, $record?->source_funding ?? $wallet?->source ?? 'Komite', $record?->bos_year);
            }

            return;
        }
        if ($model->exists) {
            $guard->assertOpen((int) ($model->getRawOriginal('school_id') ?: $schoolId), $model->getRawOriginal($field), $model->getRawOriginal('source_funding') ?: 'Komite', $model->getRawOriginal('bos_year'));
        }
        $guard->assertOpen((int) $schoolId, $model->getAttribute($field), $model->source_funding ?? 'Komite', $model->bos_year);
    }
}
