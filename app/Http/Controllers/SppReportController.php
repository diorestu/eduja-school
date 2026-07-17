<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class SppReportController extends Controller
{
    public function index()
    {
        // 1. Recents payments log
        $payments = Transaction::with(['invoice.student.schoolClasses'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get();

        // 2. Outstanding invoices
        $unpaidInvoices = Invoice::with(['student.schoolClasses', 'transactions'])
            ->whereIn('status', ['Belum Lunas', 'Cicilan'])
            ->orderBy('due_date', 'asc')
            ->get();

        // 3. Stats calculations
        $totalCollected = Transaction::sum('amount_paid');
        
        $totalInvoiced = Invoice::sum('total_amount');
        $totalOutstanding = $totalInvoiced - $totalCollected;

        // 4. Class-wise SPP report
        $classReports = SchoolClass::all()->map(function($class) {
            $classId = $class->id;
            
            $invoiced = Invoice::whereHas('student.schoolClasses', function($q) use ($classId) {
                $q->where('school_classes.id', $classId);
            })->sum('total_amount');

            $collected = Transaction::whereHas('invoice.student.schoolClasses', function($q) use ($classId) {
                $q->where('school_classes.id', $classId);
            })->sum('amount_paid');

            return [
                'name' => $class->name,
                'invoiced' => (float) $invoiced,
                'collected' => (float) $collected,
                'outstanding' => (float) ($invoiced - $collected)
            ];
        });

        return view('pages.keuangan.spp.laporan', [
            'title' => 'Laporan & Tunggakan SPP',
            'payments' => $payments,
            'unpaidInvoices' => $unpaidInvoices,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'classReports' => $classReports
        ]);
    }
}
