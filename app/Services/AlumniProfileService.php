<?php

namespace App\Services;

use App\Models\Alumni;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlumniProfileService
{
    public function synchronizeSchoolHistory(Alumni $alumni): void
    {
        $alumni->loadMissing(['school', 'student']);
        if (! $alumni->school) {
            return;
        }
        $entryYear = $alumni->student?->generation;
        $alumni->educationEntries()->updateOrCreate([
            'source' => 'school', 'source_school_id' => $alumni->school_id,
        ], [
            'institution_name' => $alumni->school->name,
            'department' => $alumni->department_name,
            'entry_year' => $entryYear && $entryYear >= 1900 ? $entryYear : null,
            'graduation_year' => $alumni->graduation_year,
        ]);
    }

    public function update(Alumni $alumni, Request $request): void
    {
        if ($request->boolean('education_entries_present') && ! $request->has('education_entries')) {
            $request->merge(['education_entries' => []]);
        }
        $working = in_array($request->input('current_status'), ['Bekerja', 'Kuliah sambil bekerja', 'Wirausaha'], true);
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'current_status' => ['required', Rule::in(Alumni::CURRENT_STATUSES)],
            'current_job' => [Rule::requiredIf($working), 'nullable', 'string', 'max:160'],
            'education_history' => ['nullable', 'string', 'max:2000'],
            'education_entries' => ['sometimes', 'array', 'max:50'],
            'education_entries.*.institution_name' => ['required', 'string', 'max:255'],
            'education_entries.*.department' => ['nullable', 'string', 'max:255'],
            'education_entries.*.entry_year' => ['required', 'integer', 'min:1900', 'max:9999'],
            'education_entries.*.graduation_year' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        ]);
        foreach ($validated['education_entries'] ?? [] as $index => $entry) {
            if (isset($entry['graduation_year']) && $entry['graduation_year'] < $entry['entry_year']) {
                throw ValidationException::withMessages(["education_entries.$index.graduation_year" => 'Tahun lulus harus sama atau setelah tahun masuk.']);
            }
        }
        $validated['current_job'] = match ($validated['current_status']) {
            'Melanjutkan Pendidikan' => 'Pelajar',
            'Kuliah' => 'Mahasiswa',
            'Gap year' => null,
            default => $validated['current_job'],
        };
        DB::transaction(function () use ($alumni, $validated): void {
            $alumni->update(Arr::except($validated, ['education_entries']));
            $this->synchronizeSchoolHistory($alumni);
            if (array_key_exists('education_entries', $validated)) {
                $alumni->educationEntries()->where('source', 'manual')->delete();
                foreach ($validated['education_entries'] as $entry) {
                    $alumni->educationEntries()->create(Arr::only($entry, ['institution_name', 'department', 'entry_year', 'graduation_year']) + ['source' => 'manual']);
                }
            }
        });
    }
}
