<?php

use App\Http\Controllers\AcademicFoundationController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExecutiveDashboardController;
use App\Http\Controllers\FinanceFoundationController;
use App\Http\Controllers\OperationsFoundationController;
use App\Http\Controllers\PortalFoundationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolRolePermissionController;
use App\Http\Controllers\SchoolSelectionController;
use App\Http\Controllers\SppReportController;
use App\Http\Controllers\SppTariffController;
use App\Http\Controllers\SppTransactionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentSavingController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

// --- PUBLIC LANDING PAGE & INFO ---
Route::get('/', function () {
    return view('pages.landing');
})->name('landing');

Route::get('/layanan', function () {
    return view('pages.layanan');
})->name('layanan');

Route::get('/pricing', function () {
    return view('pages.pricing');
})->name('pricing');

Route::get('/blog', function () {
    return view('pages.blog');
})->name('blog');

Route::get('/contact', function () {
    return view('pages.contact');
})->name('contact');

Route::get('/kebijakan-privasi', function () {
    return view('pages.legal', [
        'document' => 'privacy-policy',
        'activePage' => 'privasi',
        'title' => 'Kebijakan Privasi EDUJA',
        'description' => 'Kebijakan Privasi EDUJA menjelaskan pengelolaan data siswa, guru, orang tua, dan sekolah secara transparan.',
    ]);
})->name('privacy-policy');

Route::get('/syarat-ketentuan', function () {
    return view('pages.legal', [
        'document' => 'terms-of-service',
        'activePage' => 'syarat',
        'title' => 'Syarat & Ketentuan EDUJA',
        'description' => 'Syarat dan Ketentuan penggunaan platform EDUJA untuk sekolah dan seluruh penggunanya.',
    ]);
})->name('terms-of-service');

Route::get('/sitemap.xml', function () {
    $urls = [
        ['loc' => route('landing'), 'priority' => '1.0'],
        ['loc' => route('layanan'), 'priority' => '0.9'],
        ['loc' => route('pricing'), 'priority' => '0.9'],
        ['loc' => route('blog'), 'priority' => '0.8'],
        ['loc' => route('contact'), 'priority' => '0.8'],
        ['loc' => route('privacy-policy'), 'priority' => '0.3'],
        ['loc' => route('terms-of-service'), 'priority' => '0.3'],
    ];

    return response()->view('sitemap', compact('urls'))
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::post('/contact', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name' => 'required|string|max:100',
        'school_name' => 'required|string|max:100',
        'email' => 'required|email|max:100',
        'phone' => 'required|string|max:20',
        'message' => 'required|string',
    ]);

    return back()->with('success', 'Formulir demo Anda berhasil dikirim! Tim kami akan segera menghubungi Anda melalui WhatsApp atau Email.');
})->name('contact.send');

// --- AUTH GUEST ONLY (SIGN IN & SIGN UP) ---
Route::middleware('guest')->group(function () {
    Route::get('/signin', [AuthController::class, 'showSignin'])->name('login'); // Naming as 'login' binds Laravel default redirects
    Route::post('/signin', [AuthController::class, 'signin'])->name('signin');

    Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup.store');
});

// --- AUTH PROTECTED ROUTES ---
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/school/select', [SchoolSelectionController::class, 'index'])->name('school.select');
    Route::post('/school/switch', [SchoolSelectionController::class, 'switch'])->name('school.switch');

    Route::middleware('permission:executive.foundation,yayasan')->group(function () {
        Route::get('/yayasan', [ExecutiveDashboardController::class, 'yayasan'])->name('yayasan.dashboard');
        Route::get('/yayasan/sekolah/{school}', [ExecutiveDashboardController::class, 'yayasanSchool'])->name('yayasan.school');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('school.selected')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('superadmin')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/permissions', [SchoolRolePermissionController::class, 'index'])->name('permissions.index');
            Route::put('/permissions', [SchoolRolePermissionController::class, 'update'])->name('permissions.update');
        });

        Route::middleware('permission:executive.district,dinas')->get('/dinas', [ExecutiveDashboardController::class, 'dinas'])->name('dinas.dashboard');

        Route::prefix('academic')->name('academic.')->group(function () {
            Route::middleware('permission:academic.departments,super_admin,kepsek,wakasek,tu,staf_tu')->group(function () {
                Route::get('/departments', [AcademicFoundationController::class, 'departments'])->name('departments');
                Route::post('/departments', [AcademicFoundationController::class, 'storeDepartment'])->name('departments.store');
            });
            Route::middleware('permission:academic.promotion,super_admin,kepsek,wakasek,tu,staf_tu')->group(function () {
                Route::get('/promotion', [AcademicFoundationController::class, 'promotion'])->name('promotion');
                Route::post('/promotion', [AcademicFoundationController::class, 'promote'])->name('promotion.store');
            });
            Route::middleware('permission:academic.graduation,super_admin,kepsek,wakasek,tu,staf_tu')->group(function () {
                Route::get('/graduation', [AcademicFoundationController::class, 'graduation'])->name('graduation');
                Route::post('/graduation', [AcademicFoundationController::class, 'graduate'])->name('graduation.store');
            });
        });

        Route::middleware('permission:finance.approvals,super_admin,kepsek,wakasek,tu,staf_tu,bendahara,wali_kelas')->prefix('approvals')->name('approvals.')->group(function () {
            Route::post('/{approval}/approve', [ApprovalController::class, 'approve'])->name('approve');
            Route::post('/{approval}/reject', [ApprovalController::class, 'reject'])->name('reject');
        });

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::middleware('permission:finance.accounts,super_admin,bendahara')->group(function () {
                Route::get('/accounts', [FinanceFoundationController::class, 'accounts'])->name('accounts');
                Route::post('/accounts', [FinanceFoundationController::class, 'storeAccount'])->name('accounts.store');
            });
            Route::middleware('permission:finance.income_types,super_admin,bendahara')->group(function () {
                Route::get('/income-types', [FinanceFoundationController::class, 'incomeTypes'])->name('income-types');
                Route::post('/income-types', [FinanceFoundationController::class, 'storeIncomeType'])->name('income-types.store');
            });
            Route::middleware('permission:finance.expense_types,super_admin,bendahara')->group(function () {
                Route::get('/expense-types', [FinanceFoundationController::class, 'expenseTypes'])->name('expense-types');
                Route::post('/expense-types', [FinanceFoundationController::class, 'storeExpenseType'])->name('expense-types.store');
            });
            Route::middleware('permission:finance.allocations,super_admin,bendahara')->group(function () {
                Route::get('/allocations', [FinanceFoundationController::class, 'allocations'])->name('allocations');
                Route::post('/allocations', [FinanceFoundationController::class, 'storeAllocation'])->name('allocations.store');
            });
            Route::middleware('permission:finance.budget_years,super_admin,bendahara')->group(function () {
                Route::get('/budget-years', [FinanceFoundationController::class, 'budgetYears'])->name('budget-years');
                Route::post('/budget-years', [FinanceFoundationController::class, 'storeBudgetYear'])->name('budget-years.store');
            });
            Route::middleware('permission:finance.budgets,super_admin,bendahara')->group(function () {
                Route::get('/budgets', [FinanceFoundationController::class, 'budgets'])->name('budgets');
                Route::post('/budgets', [FinanceFoundationController::class, 'storeBudget'])->name('budgets.store');
                Route::post('/budgets/{budgetPlan}/revisions', [FinanceFoundationController::class, 'storeBudgetRevision'])->name('budgets.revisions.store');
            });
            Route::middleware('permission:finance.approvals,super_admin,bendahara')->get('/approvals', [FinanceFoundationController::class, 'approvals'])->name('approvals');
            Route::middleware('permission:finance.billing,super_admin,bendahara')->group(function () {
                Route::get('/billing', [FinanceFoundationController::class, 'billing'])->name('billing');
                Route::post('/billing', [FinanceFoundationController::class, 'storeBilling'])->name('billing.store');
            });
            Route::middleware('permission:finance.closing,super_admin,bendahara')->group(function () {
                Route::get('/closing', [FinanceFoundationController::class, 'closing'])->name('closing');
                Route::post('/closing', [FinanceFoundationController::class, 'storeClosing'])->name('closing.store');
            });
            Route::middleware('permission:finance.ledger,super_admin,bendahara')->get('/ledger', [FinanceFoundationController::class, 'ledger'])->name('ledger');
            Route::middleware('permission:finance.reports,super_admin,bendahara')->get('/reports', [FinanceFoundationController::class, 'reports'])->name('reports');
        });

        Route::middleware('permission:portal.teacher,super_admin,guru,wali_kelas,kepsek')->get('/portal/guru', [PortalFoundationController::class, 'guru'])->name('portal.guru');
        Route::middleware('permission:portal.student,super_admin,siswa')->group(function () {
            Route::get('/portal/siswa', [PortalFoundationController::class, 'siswa'])->name('portal.siswa');
            Route::post('/portal/siswa/attendance', [PortalFoundationController::class, 'storeAttendance'])->name('portal.siswa.attendance');
            Route::post('/portal/siswa/permission', [PortalFoundationController::class, 'storePermission'])->name('portal.siswa.permission');
        });
        Route::middleware('permission:portal.parent,super_admin,orang_tua')->get('/portal/orang-tua', [PortalFoundationController::class, 'orangTua'])->name('portal.orang-tua');

        Route::middleware('permission:attendance.requests,super_admin,kepsek,wakasek,tu,staf_tu,guru,wali_kelas,tendik,siswa,orang_tua,wali_murid')
            ->get('/attendance/requests', [OperationsFoundationController::class, 'attendanceRequests'])->name('attendance.requests');
        Route::middleware('permission:attendance.requests,super_admin,siswa,orang_tua,wali_murid')
            ->post('/attendance/requests/student', [AttendanceRequestController::class, 'storeStudent'])->name('attendance.requests.student.store');
        Route::get('/attendance/requests/{absence}/document', [AttendanceRequestController::class, 'document'])->name('attendance.requests.document');
        Route::middleware('permission:attendance.requests,super_admin,guru,tendik,staf_tu,tu,wali_kelas')
            ->post('/attendance/requests/teacher', [AttendanceRequestController::class, 'storeTeacher'])->name('attendance.requests.teacher.store');
        Route::middleware('permission:attendance.rfid,super_admin,kepsek,wakasek,tu,staf_tu,guru,wali_kelas')
            ->get('/attendance/rfid-sync', [OperationsFoundationController::class, 'rfidSync'])->name('attendance.rfid-sync');
        Route::middleware('permission:announcements.view,super_admin,kepsek,wakasek,tu,staf_tu,guru,wali_kelas,tendik,siswa,orang_tua,wali_murid,alumni')->group(function () {
            Route::get('/announcements', [OperationsFoundationController::class, 'announcements'])->name('announcements.index');
            Route::get('/announcements/{announcement}', [OperationsFoundationController::class, 'showAnnouncement'])->name('announcements.show');
            Route::post('/announcements/{announcement}/read', [OperationsFoundationController::class, 'markAnnouncementRead'])->name('announcements.read');
            Route::post('/announcements/{announcement}/bookmark', [OperationsFoundationController::class, 'bookmarkAnnouncement'])->name('announcements.bookmark');
            Route::get('/announcements/{announcement}/attachment', [OperationsFoundationController::class, 'announcementAttachment'])->name('announcements.attachment');
            Route::post('/announcements', [OperationsFoundationController::class, 'storeAnnouncement'])->name('announcements.store');
        });

        Route::middleware('permission:ai.use,super_admin,kepsek,wakasek,tu,staf_tu,guru,wali_kelas')->group(function () {
            Route::get('/ai', [OperationsFoundationController::class, 'ai'])->name('ai.index');
            Route::post('/ai', [OperationsFoundationController::class, 'storeAi'])->name('ai.store');
        });
        Route::middleware('permission:academic.alumni,super_admin,kepsek,wakasek,tu,staf_tu,alumni')->group(function () {
            Route::get('/alumni', [AcademicFoundationController::class, 'alumni'])->name('alumni.index');
            Route::put('/alumni/{alumni}', [AcademicFoundationController::class, 'updateAlumni'])->name('alumni.update');
            Route::post('/alumni/{alumni}/link', [AcademicFoundationController::class, 'linkAlumni'])->name('alumni.link');
        });

        // --- KESISWAAN & OPERASIONAL SEKOLAH (FASE 2) ---
        Route::middleware('permission:academic_years.view,super_admin,kepsek,staf_tu')->prefix('akademik')->name('akademik.')->group(function () {
            Route::get('/', [AcademicYearController::class, 'index'])->name('index');
            Route::post('/', [AcademicYearController::class, 'store'])->name('store');
            Route::post('/{id}/toggle', [AcademicYearController::class, 'toggleActive'])->name('toggle');
        });

        Route::middleware('permission:classes.view,super_admin,kepsek,staf_tu')->prefix('kelas')->name('kelas.')->group(function () {
            Route::get('/', [SchoolClassController::class, 'index'])->name('index');
            Route::post('/', [SchoolClassController::class, 'store'])->name('store');
        });

        Route::middleware('permission:students.view,super_admin,staf_tu')->prefix('siswa')->name('siswa.')->group(function () {
            Route::get('/', [StudentController::class, 'index'])->name('index');
            Route::post('/', [StudentController::class, 'store'])->name('store');
        });

        Route::middleware('permission:teachers.view,super_admin,staf_tu')->prefix('guru')->name('guru.')->group(function () {
            Route::get('/', [TeacherController::class, 'index'])->name('index');
            Route::post('/', [TeacherController::class, 'store'])->name('store');
        });

        Route::middleware('permission:organization.view,super_admin,staf_tu')->get('/struktur', function () {
            $principal = \App\Models\Teacher::where('role_type', 'Kepala Sekolah')->where('is_active', true)->first();
            $treasurer = \App\Models\Teacher::where('role_type', 'Bendahara')->where('is_active', true)->first();
            $staff = \App\Models\Teacher::where('role_type', 'Staf TU')->where('is_active', true)->get();
            $teachers = \App\Models\Teacher::where('role_type', 'Guru')->where('is_active', true)->get();

            return view('pages.kesiswaan.struktur', [
                'title' => 'Struktur Organisasi Sekolah',
                'principal' => $principal,
                'treasurer' => $treasurer,
                'staff' => $staff,
                'teachers' => $teachers,
            ]);
        })->name('struktur.index');

        // --- KEUANGAN SPP (FASE 3) ---
        Route::prefix('spp')->name('spp.')->group(function () {
            Route::middleware('permission:spp.tariffs,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/tarif', [SppTariffController::class, 'index'])->name('tarif.index');
                Route::post('/tarif', [SppTariffController::class, 'store'])->name('tarif.store');
            });
            Route::middleware('permission:spp.transactions,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/transaksi', [SppTransactionController::class, 'index'])->name('transaksi.index');
                Route::post('/transaksi/generate', [SppTransactionController::class, 'generateInvoices'])->name('transaksi.generate');
                Route::post('/transaksi/{id}/bayar', [SppTransactionController::class, 'pay'])->name('transaksi.pay');
            });
            Route::middleware('permission:spp.reports,super_admin,kepsek,bendahara')->get('/laporan', [SppReportController::class, 'index'])->name('laporan.index');
        });

        // --- KEUANGAN BOS & BKU (FASE 4) ---
        Route::prefix('bos')->name('bos.')->group(function () {
            Route::middleware('permission:bos.budgets,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/anggaran', [BosController::class, 'anggaran'])->name('anggaran.index');
                Route::post('/anggaran', [BosController::class, 'storeAnggaran'])->name('anggaran.store');
            });
            Route::middleware('permission:bos.expenses,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/belanja', [BosController::class, 'belanja'])->name('belanja.index');
                Route::post('/belanja', [BosController::class, 'storeBelanja'])->name('belanja.store');
            });
            Route::middleware('permission:bos.ledger,super_admin,kepsek,bendahara')->get('/bku', [BosController::class, 'bku'])->name('bku.index');
        });

        // --- SIMPANAN / TABUNGAN ---
        Route::middleware('permission:student_savings.view,super_admin,kepsek,bendahara')->prefix('tabungan')->name('tabungan.')->group(function () {
            Route::get('/', [StudentSavingController::class, 'index'])->name('index');
            Route::post('/', [StudentSavingController::class, 'store'])->name('store');
        });

        // --- ABSENSI / PRESENSI ---
        Route::prefix('presensi')->name('presensi.')->group(function () {
            Route::middleware('permission:student_attendance.view,super_admin,kepsek,pic_sekolah,wakasek,tu,staf_tu,guru,wali_kelas')->group(function () {
                Route::get('/siswa', [AttendanceController::class, 'siswa'])->name('siswa');
                Route::post('/siswa', [AttendanceController::class, 'storeSiswa'])->name('siswa.store');
            });
            Route::middleware('permission:teacher_attendance.view,super_admin,kepsek,pic_sekolah,wakasek,tu,staf_tu,guru,wali_kelas')->group(function () {
                Route::get('/gtk', [AttendanceController::class, 'gtk'])->name('gtk');
                Route::post('/gtk', [AttendanceController::class, 'storeGtk'])->name('gtk.store');
            });
        });

    });
});
