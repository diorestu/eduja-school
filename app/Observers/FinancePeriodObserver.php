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
        if ($model->exists) {
            $guard->assertOpen((int) ($model->getRawOriginal('school_id') ?: $schoolId), $model->getRawOriginal($field), $model->getRawOriginal('source_funding') ?: 'Komite', $model->getRawOriginal('bos_year'));
        }
        $guard->assertOpen((int) $schoolId, $model->getAttribute($field), $model->source_funding ?? 'Komite', $model->bos_year);
    }
}
