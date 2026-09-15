<?php

namespace App\Http\Controllers;

use App\Services\AbsenceRequestService;
use App\Services\SchoolContext;
use App\Models\AttendanceRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AttendanceRequestController extends Controller
{
    public function storeStudent(Request $request, SchoolContext $schoolContext, AbsenceRequestService $absenceRequests): RedirectResponse
    {
        $validated = $request->validate($this->rules(['sakit', 'izin', 'dispensasi']));
        $validated = $this->withDocument($request, $schoolContext, $validated);

        try {
            $absenceRequests->submitStudent(
                $schoolContext->activeSchoolIdFor($request->user()),
                $request->user(),
                $validated,
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->hasFile('document')) {
                Storage::disk('local')->delete($validated['document_path']);
            }
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pengajuan absensi berhasil dikirim dan menunggu persetujuan.');
    }

    public function storeTeacher(Request $request, SchoolContext $schoolContext, AbsenceRequestService $absenceRequests): RedirectResponse
    {
        $validated = $request->validate($this->rules(['sakit', 'cuti']));
        $validated = $this->withDocument($request, $schoolContext, $validated);

        try {
            $absenceRequests->submitTeacher(
                $schoolContext->activeSchoolIdFor($request->user()),
                $request->user(),
                $validated,
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->hasFile('document')) {
                Storage::disk('local')->delete($validated['document_path']);
            }
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pengajuan absensi berhasil dikirim dan menunggu persetujuan.');
    }

    private function rules(array $requestTypes): array
    {
        return [
            'subject_id' => ['required', 'integer', 'min:1'],
            'request_type' => ['required', 'string', 'in:'.implode(',', $requestTypes)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'document_path' => ['nullable', 'string', 'max:255'],
            'document_name' => ['nullable', 'string', 'max:255'],
            'document_mime' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    private function withDocument(Request $request, SchoolContext $context, array $attributes): array
    {
        unset($attributes['document'], $attributes['document_path'], $attributes['document_name'], $attributes['document_mime']);
        if ($file = $request->file('document')) {
            $attributes['document_path'] = $file->store('attendance-documents/'.$context->activeSchoolIdFor(), 'local');
            $attributes['document_name'] = $file->getClientOriginalName();
            $attributes['document_mime'] = $file->getMimeType();
        }
        return $attributes;
    }

    public function document(AttendanceRequest $absence, Request $request, SchoolContext $context)
    {
        abort_unless((int) $absence->school_id === $context->activeSchoolIdFor(), 404);
        abort_unless($request->user()->hasRole(['kepsek', 'wakasek', 'tu', 'staf_tu', 'wali_kelas'])
            || in_array($request->user()->id, [$absence->requester_id, $absence->submitted_by], true), 403);
        abort_unless($absence->document_path
            && str_starts_with($absence->document_path, 'attendance-documents/'.$absence->school_id.'/')
            && ! str_contains($absence->document_path, '..')
            && Storage::disk('local')->exists($absence->document_path), 404);
        return Storage::disk('local')->download($absence->document_path, basename($absence->document_name ?: 'dokumen.pdf'));
    }
}
