<?php

namespace App\Services;

use App\Models\FinanceWalletMovement;
use App\Models\VirtualWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FinanceWalletService
{
    public function adjust(VirtualWallet $wallet, int $cents, mixed $date, Model $source): void
    {
        if ($cents === 0) { return; }
        DB::transaction(function () use ($wallet, $cents, $date, $source) {
            app(FinancePeriodGuard::class)->assertOpen($wallet->school_id, $date, $source->source_funding ?? 'Komite', $source->bos_year);
            FinanceWalletMovement::create(['school_id' => $wallet->school_id, 'virtual_wallet_id' => $wallet->id, 'amount' => ($cents < 0 ? '-' : '').BillingPaymentService::decimal(abs($cents)),
                'effective_date' => $date, 'source_type' => $source->getMorphClass(), 'source_id' => $source->id]);
            $wallet->increment('nominal', ($cents < 0 ? '-' : '').BillingPaymentService::decimal(abs($cents)));
        });
    }
}
