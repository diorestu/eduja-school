<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeMonetaryInputs
{
    /**
     * Common monetary field names that may receive thousands-separated input.
     */
    private const MONETARY_FIELDS = [
        'amount',
        'nominal',
        'opening_balance',
        'amount_paid',
        'new_amount',
        'minimum_installment',
        'late_fee_per_day',
        'late_fee_maximum',
        'closing_balance',
        'tax_amount',
        'balance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        if (! empty($input)) {
            $input = $this->cleanMonetaryValues($input);
            $request->merge($input);
        }

        return $next($request);
    }

    /**
     * Recursively strip thousands dot delimiters from monetary values.
     */
    private function cleanMonetaryValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->cleanMonetaryValues($value);
            } elseif (is_string($value) && in_array((string) $key, self::MONETARY_FIELDS, true)) {
                $trimmed = trim($value);
                // Matches standard dot-formatted numbers like 1.000, 250.000, 10.000.000, or with optional currency prefix
                if (preg_match('/^\d{1,3}(\.\d{3})+$/', $trimmed)) {
                    $data[$key] = str_replace('.', '', $trimmed);
                } elseif (preg_match('/^[0-9.]+$/', $trimmed) && substr_count($trimmed, '.') > 1) {
                    $data[$key] = str_replace('.', '', $trimmed);
                }
            }
        }

        return $data;
    }
}
