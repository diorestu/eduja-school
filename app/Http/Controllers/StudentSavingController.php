<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentSaving;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StudentSavingController extends Controller
{
    /**
     * Display list of students with balances or detailed student saving profile.
     */
    public function index(Request $request)
    {
        $selectedStudentId = $request->query('student_id');
        $activeYear = AcademicYear::where('is_active', true)->first();

        if ($selectedStudentId) {
            $student = Student::with(['schoolClasses'])->findOrFail($selectedStudentId);
            
            // Fetch detailed savings ledger for this student
            $ledger = StudentSaving::where('student_id', $student->id)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate current savings balance
            $totalSetoran = (float) StudentSaving::where('student_id', $student->id)->where('type', 'Setoran')->sum('amount');
            $totalPenarikan = (float) StudentSaving::where('student_id', $student->id)->where('type', 'Penarikan')->sum('amount');
            $balance = $totalSetoran - $totalPenarikan;

            return view('pages.keuangan.tabungan.detail', [
                'title' => 'Buku Tabungan - ' . $student->name,
                'student' => $student,
                'ledger' => $ledger,
                'balance' => $balance,
                'activeYear' => $activeYear
            ]);
        }

        // Otherwise show all students with their savings balances
        $query = Student::where('is_active', true)->with(['schoolClasses']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $students = $query->get()->map(function($student) {
            $setoran = (float) StudentSaving::where('student_id', $student->id)->where('type', 'Setoran')->sum('amount');
            $penarikan = (float) StudentSaving::where('student_id', $student->id)->where('type', 'Penarikan')->sum('amount');
            $student->savings_balance = $setoran - $penarikan;
            return $student;
        });

        return view('pages.keuangan.tabungan.index', [
            'title' => 'Simpanan / Tabungan Siswa',
            'students' => $students,
            'activeYear' => $activeYear
        ]);
    }

    /**
     * Store new savings transaction (Setoran / Penarikan).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'type' => 'required|in:Setoran,Penarikan',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:255'
        ]);

        $studentId = $validated['student_id'];
        
        // If penarikan, check if balance is sufficient
        if ($validated['type'] === 'Penarikan') {
            $totalSetoran = (float) StudentSaving::where('student_id', $studentId)->where('type', 'Setoran')->sum('amount');
            $totalPenarikan = (float) StudentSaving::where('student_id', $studentId)->where('type', 'Penarikan')->sum('amount');
            $currentBalance = $totalSetoran - $totalPenarikan;

            if ($validated['amount'] > $currentBalance) {
                return redirect()->back()->withErrors(['amount' => 'Saldo tabungan tidak mencukupi untuk melakukan penarikan. Saldo saat ini: Rp ' . number_format($currentBalance, 0, ',', '.')]);
            }
        }

        // Generate reference number
        $dateStr = Carbon::parse($validated['transaction_date'])->format('Ymd');
        $countToday = StudentSaving::whereDate('created_at', Carbon::today())->count() + 1;
        $refNo = 'SAV/' . $dateStr . '/' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

        $validated['reference_number'] = $refNo;
        $validated['recipient_name'] = auth()->user()->name ?? 'Administrator';

        StudentSaving::create($validated);

        return redirect()->back()->with('success', 'Transaksi tabungan berhasil dicatat! No. Ref: ' . $refNo);
    }
}
