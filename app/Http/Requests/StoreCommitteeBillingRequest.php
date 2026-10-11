<?php

namespace App\Http\Requests;

use App\Models\School;
use App\Services\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitteeBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $truth = fn ($value) => in_array($value, [true, 1, '1', 'ya', 'true', 'on'], true);
        $this->merge(['allow_installment' => $truth($this->input('allow_installment')), 'has_late_fee' => $truth($this->input('has_late_fee'))]);
        if (! $this->boolean('allow_installment')) {
            $this->merge(['minimum_installment' => null]);
        }
        if (! $this->boolean('has_late_fee')) {
            $this->merge(['late_fee_per_day' => 0, 'late_fee_maximum' => 0]);
        }
        if ($this->input('target_type') === 'school') {
            $this->merge(['target_department_id' => null, 'target_generation' => null, 'target_class_id' => null, 'target_student_id' => null]);
        }
        if ($this->input('billing_frequency') === 'Sekali') {
            $this->merge(['billing_day' => null, 'due_day' => null]);
        } elseif ($this->input('billing_frequency') === 'Bulanan') {
            // Older clients submitted full dates for the same monthly schedule.
            foreach (['billing_day' => 'start_date', 'due_day' => 'due_date'] as $day => $field) {
                if (! $this->filled($day) && preg_match('/^\d{4}-\d{2}-(\d{2})$/', $this->input($field, ''), $match)) {
                    $this->merge([$day => (int) $match[1]]);
                }
            }
        }
    }

    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->activeSchoolIdFor();
        $monthly = $this->input('billing_frequency') === 'Bulanan';
        $smk = strtolower(School::find($schoolId)?->level ?? '') === 'smk';

        return [
            'income_type_id' => ['required', 'integer', Rule::exists('income_types', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->whereRaw('LOWER(category) = ?', ['komite']))],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))],
            'name' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'allow_installment' => ['required', 'boolean'],
            'minimum_installment' => [Rule::requiredIf($this->boolean('allow_installment')), 'nullable', 'numeric', 'min:0.01', 'lte:amount', 'decimal:0,2'],
            'has_late_fee' => ['required', 'boolean'],
            'late_fee_per_day' => [Rule::requiredIf($this->boolean('has_late_fee')), 'numeric', $this->boolean('has_late_fee') ? 'min:0.01' : 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'late_fee_maximum' => [Rule::requiredIf($this->boolean('has_late_fee')), 'numeric', 'gte:late_fee_per_day', 'max:9999999999.99', 'decimal:0,2'],
            'billing_frequency' => ['required', Rule::in(['Sekali', 'Bulanan'])],
            'billing_day' => [Rule::requiredIf($monthly), 'nullable', 'integer', 'between:1,31'],
            'due_day' => [Rule::requiredIf($monthly), 'nullable', 'integer', 'between:1,31', ...($monthly ? ['gte:billing_day'] : [])],
            'start_date' => [Rule::requiredIf(! $monthly), 'nullable', 'date'],
            'due_date' => [Rule::requiredIf(! $monthly), 'nullable', 'date', ...(! $monthly ? ['after_or_equal:start_date'] : [])],
            'target_type' => ['required', Rule::in(['school', 'pilihan'])],
            'target_department_id' => ['nullable', Rule::prohibitedIf(! $smk), 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))],
            'target_generation' => ['nullable', 'integer', 'between:1900,2200'],
            'target_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->where('academic_year_id', $this->input('academic_year_id')))],
            'target_student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->where('is_active', true)->where('status', 'active'))],
        ];
    }
}
