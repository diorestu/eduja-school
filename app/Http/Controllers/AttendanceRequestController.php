<?php

namespace App\Http\Controllers;

use App\Services\AbsenceRequestService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AttendanceRequestController extends Controller
{
    public function storeStudent(Request $request, SchoolContext $schoolContext, AbsenceRequestService $absenceRequests): RedirectResponse
    {
        $validated = $request->validate($this->rules(['sakit', 'izin', 'dispensasi']));

        try {
            $absenceRequests->submitStudent(
                $schoolContext->activeSchoolIdFor($request->user()),
                $request->user(),
                $validated,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pengajuan absensi berhasil dikirim dan menunggu persetujuan.');
    }

    public function storeTeacher(Request $request, SchoolContext $schoolContext, AbsenceRequestService $absenceRequests): RedirectResponse
    {
        $validated = $request->validate($this->rules(['sakit', 'cuti']));

        try {
            $absenceRequests->submitTeacher(
                $schoolContext->activeSchoolIdFor($request->user()),
                $request->user(),
                $validated,
            );
        } catch (InvalidArgumentException $exception) {
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
        ];
    }
}
