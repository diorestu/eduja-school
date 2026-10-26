<?php

namespace App\Services;

use App\Models\OtherIncomeRevision;
use Illuminate\Validation\ValidationException;

class OtherIncomeService extends GrantIncomeService
{
    protected string $category = 'lainnya';

    protected string $label = 'Lainnya';

    protected string $identityField = 'source_funding';

    protected string $allocationColumn = 'other_allocations';

    protected string $submissionColumn = 'other_submission_key';

    protected string $fingerprintColumn = 'other_fingerprint';

    protected string $incomeScope = 'other';

    protected string $revisionRelation = 'otherRevisions';

    protected string $revisionModel = OtherIncomeRevision::class;

    protected string $approvalType = 'other_income_revision';

    public function accounts(int $schoolId, string $method)
    {
        return parent::accounts($schoolId, $method)->when($method === 'Transfer', fn ($query) => $query->where('source_funding', 'komite'));
    }

    protected function validateIdentity(array $attributes): void
    {
        $source = strtolower(trim($attributes['source_funding']));
        if (in_array($source, ['hibah', 'komite'], true) || preg_match('/^(bos|bop|dak)/', $source)) {
            throw ValidationException::withMessages(['source_funding' => 'Gunakan kategori Komite, BOS, atau Hibah yang sesuai untuk sumber pendapatan ini.']);
        }
    }
}
