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

    Route::get('/payment-approvals/{approval}', [\App\Http\Controllers\PaymentApprovalController::class, 'show'])->name('payment-approvals.show');
    Route::get('/payment-approvals/{approval}/proof', [\App\Http\Controllers\PaymentApprovalController::class, 'proof'])->name('payment-approvals.proof');

    Route::middleware('permission:executive.foundation,yayasan')->group(function () {
        Route::get('/yayasan', [ExecutiveDashboardController::class, 'yayasan'])->name('yayasan.dashboard');
        Route::get('/yayasan/sekolah/{school}', [ExecutiveDashboardController::class, 'yayasanSchool'])->name('yayasan.school');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('permission:executive.district,dinas')->get('/dinas', [ExecutiveDashboardController::class, 'dinas'])->name('dinas.dashboard');

    Route::middleware('school.selected')->group(function () {

        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\SchoolNotificationController::class, 'read'])->name('notifications.read');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('role:kepsek')->group(function () {
            Route::get('/settings/guardian-finance', [\App\Http\Controllers\GuardianFinanceAccessController::class, 'index'])->name('settings.guardian-finance.index');
            Route::put('/settings/guardian-finance', [\App\Http\Controllers\GuardianFinanceAccessController::class, 'update'])->name('settings.guardian-finance.update');
            Route::post('/finance/closing/{closing}/reopen', [FinanceFoundationController::class, 'reopenClosing'])->name('finance.closing.reopen');
            Route::get('/settings/dinas-access', [\App\Http\Controllers\DistrictFinanceAccessController::class, 'index'])->name('settings.dinas-access.index');
            Route::put('/settings/dinas-access', [\App\Http\Controllers\DistrictFinanceAccessController::class, 'update'])->name('settings.dinas-access.update');
        });

        Route::middleware('superadmin')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/permissions', [SchoolRolePermissionController::class, 'index'])->name('permissions.index');
            Route::put('/permissions', [SchoolRolePermissionController::class, 'update'])->name('permissions.update');
        });

        Route::prefix('academic')->name('academic.')->group(function () {
            Route::middleware('permission:academic.departments,super_admin,kepsek,wakasek,tu,staf_tu')->group(function () {
                Route::get('/departments', [AcademicFoundationController::class, 'departments'])->name('departments');
                Route::post('/departments', [AcademicFoundationController::class, 'storeDepartment'])->name('departments.store');
            });
            Route::middleware('permission:academic.promotion,super_admin,kepsek,wakasek,tu,staf_tu,wali_kelas')->group(function () {
                Route::get('/promotion', [AcademicFoundationController::class, 'promotion'])->name('promotion');
                Route::post('/promotion', [AcademicFoundationController::class, 'promote'])->name('promotion.store');
            });
            Route::middleware('permission:academic.graduation,super_admin,kepsek,wakasek,tu,staf_tu,wali_kelas')->group(function () {
                Route::get('/graduation', [AcademicFoundationController::class, 'graduation'])->name('graduation');
                Route::post('/graduation', [AcademicFoundationController::class, 'graduate'])->name('graduation.store');
            });
        });

        Route::middleware('permission:finance.approvals,super_admin,kepsek,wakasek,tu,staf_tu,bendahara,teller,wali_kelas')->prefix('approvals')->name('approvals.')->group(function () {
            Route::post('/{approval}/approve', [ApprovalController::class, 'approve'])->name('approve');
            Route::post('/{approval}/reject', [ApprovalController::class, 'reject'])->name('reject');
        });

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::middleware('permission:finance.accounts,super_admin,bendahara')->group(function () {
                Route::get('/accounts', [FinanceFoundationController::class, 'accounts'])->name('accounts');
                Route::post('/accounts', [FinanceFoundationController::class, 'storeAccount'])->name('accounts.store');
                Route::post('/accounts/transfer', [FinanceFoundationController::class, 'transferAccount'])->name('accounts.transfer');
                Route::put('/accounts/{account}', [FinanceFoundationController::class, 'updateAccount'])->name('accounts.update');
            });
            Route::middleware('permission:finance.virtual_wallets,super_admin,bendahara')->group(function () {
                Route::get('/virtual-wallets', [FinanceFoundationController::class, 'virtualWallets'])->name('virtual-wallets');
                Route::post('/virtual-wallets', [FinanceFoundationController::class, 'storeVirtualWallet'])->name('virtual-wallets.store');
                Route::put('/virtual-wallets/{virtualWallet}', [FinanceFoundationController::class, 'updateVirtualWallet'])->name('virtual-wallets.update');
                Route::delete('/virtual-wallets/{virtualWallet}', [FinanceFoundationController::class, 'destroyVirtualWallet'])->name('virtual-wallets.destroy');
            });
            Route::middleware('permission:finance.income_types,super_admin,bendahara')->group(function () {
                Route::get('/income-types', [FinanceFoundationController::class, 'incomeTypes'])->name('income-types');
                Route::post('/income-types', [FinanceFoundationController::class, 'storeIncomeType'])->name('income-types.store');
                Route::put('/income-types/{incomeType}', [FinanceFoundationController::class, 'updateIncomeType'])->name('income-types.update');
                Route::delete('/income-types/{incomeType}', [FinanceFoundationController::class, 'destroyIncomeType'])->name('income-types.destroy');
            });
            Route::middleware('permission:spp.transactions,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/other-incomes/{income}', [\App\Http\Controllers\OtherIncomeController::class, 'show'])->name('other-income.show');
                Route::post('/other-incomes/{income}/revisions', [\App\Http\Controllers\OtherIncomeController::class, 'requestRevision'])->name('other-income.revisions');
                Route::get('/other-incomes/{income}/proof', [\App\Http\Controllers\OtherIncomeController::class, 'proof'])->name('other-income.proof');
                Route::get('/other-income-revisions/{revision}/proof', [\App\Http\Controllers\OtherIncomeController::class, 'revisionProof'])->name('other-income.revision-proof');
                Route::get('/grant-incomes/{income}', [\App\Http\Controllers\GrantIncomeController::class, 'show'])->name('grant-income.show');
                Route::post('/grant-incomes/{income}/revisions', [\App\Http\Controllers\GrantIncomeController::class, 'requestRevision'])->name('grant-income.revisions');
                Route::get('/grant-incomes/{income}/proof', [\App\Http\Controllers\GrantIncomeController::class, 'proof'])->name('grant-income.proof');
                Route::get('/grant-income-revisions/{revision}/proof', [\App\Http\Controllers\GrantIncomeController::class, 'revisionProof'])->name('grant-income.revision-proof');
                Route::get('/bos-incomes/{income}', [\App\Http\Controllers\BosIncomeController::class, 'show'])->name('bos-income.show');
                Route::post('/bos-incomes/{income}/revisions', [\App\Http\Controllers\BosIncomeController::class, 'requestRevision'])->name('bos-income.revisions');
                Route::get('/bos-incomes/{income}/proof', [\App\Http\Controllers\BosIncomeController::class, 'proof'])->name('bos-income.proof');
                Route::get('/bos-income-revisions/{revision}/proof', [\App\Http\Controllers\BosIncomeController::class, 'revisionProof'])->name('bos-income.revision-proof');
                Route::get('/incomes/committee/{student}', [\App\Http\Controllers\CommitteeIncomeController::class, 'show'])->name('committee-income.show');
                Route::post('/incomes/committee/{student}', [\App\Http\Controllers\CommitteeIncomeController::class, 'store'])->name('committee-income.store');
            });
            Route::middleware('permission:finance.expense_types,super_admin,bendahara')->group(function () {
                Route::get('/expense-types', [FinanceFoundationController::class, 'expenseTypes'])->name('expense-types');
                Route::post('/expense-types', [FinanceFoundationController::class, 'storeExpenseType'])->name('expense-types.store');
                Route::put('/expense-types/{expenseType}', [FinanceFoundationController::class, 'updateExpenseType'])->name('expense-types.update');
                Route::delete('/expense-types/{expenseType}', [FinanceFoundationController::class, 'destroyExpenseType'])->name('expense-types.destroy');
            });
            Route::middleware('permission:finance.allocations,super_admin,bendahara')->group(function () {
                Route::get('/allocations', [FinanceFoundationController::class, 'allocations'])->name('allocations');
                Route::post('/allocations', [FinanceFoundationController::class, 'storeAllocation'])->name('allocations.store');
            });
            Route::middleware('permission:finance.budget_years,super_admin,bendahara')->group(function () {
                Route::get('/budget-years', [FinanceFoundationController::class, 'budgetYears'])->name('budget-years');
                Route::post('/budget-years', [FinanceFoundationController::class, 'storeBudgetYear'])->name('budget-years.store');
                Route::put('/budget-years/{budgetYear}', [FinanceFoundationController::class, 'updateBudgetYear'])->name('budget-years.update');
                Route::delete('/budget-years/{budgetYear}', [FinanceFoundationController::class, 'destroyBudgetYear'])->name('budget-years.destroy');
            });
            Route::middleware('permission:finance.budgets,super_admin,bendahara')->group(function () {
                Route::get('/budgets', [FinanceFoundationController::class, 'budgets'])->name('budgets');
                Route::get('/budgets/revisions', [FinanceFoundationController::class, 'budgetRevisions'])->name('budgets.revisions');
                Route::post('/budgets', [FinanceFoundationController::class, 'storeBudget'])->name('budgets.store');
                Route::put('/budgets/{budgetPlan}', [FinanceFoundationController::class, 'updateBudget'])->name('budgets.update');
                Route::delete('/budgets/{budgetPlan}', [FinanceFoundationController::class, 'destroyBudget'])->name('budgets.destroy');
                Route::post('/budgets/{budgetPlan}/revisions', [FinanceFoundationController::class, 'storeBudgetRevision'])->name('budgets.revisions.store');
            });
            Route::middleware('permission:finance.approvals,super_admin,bendahara,teller,kepsek')->get('/approvals', [FinanceFoundationController::class, 'approvals'])->name('approvals');
            Route::middleware('permission:finance.arrears,super_admin,kepsek,bendahara,teller')->group(function () {
                Route::get('/arrears', [\App\Http\Controllers\StudentArrearsController::class, 'index'])->name('arrears');
                Route::get('/arrears/{student}', [\App\Http\Controllers\StudentArrearsController::class, 'show'])->name('arrears.show');
                Route::get('/arrears/{student}/photo', [\App\Http\Controllers\StudentArrearsController::class, 'photo'])->name('arrears.photo');
                Route::post('/arrears/{student}/pay', [\App\Http\Controllers\StudentArrearsController::class, 'pay'])->name('arrears.pay');
            });
            Route::middleware('permission:finance.billing,super_admin,bendahara')->group(function () {
                Route::get('/billing', [FinanceFoundationController::class, 'billing'])->name('billing');
                Route::post('/billing', [FinanceFoundationController::class, 'storeBilling'])->name('billing.store');
                Route::put('/billing/{billingItem}', [FinanceFoundationController::class, 'updateBilling'])->name('billing.update');
                Route::delete('/billing/{billingItem}', [FinanceFoundationController::class, 'destroyBilling'])->name('billing.destroy');
            });
            Route::middleware('permission:finance.income_types,super_admin,bendahara')->group(function () {
                Route::post('/incomes', [FinanceFoundationController::class, 'storeIncome'])->name('incomes.store');
            });
            Route::middleware('permission:finance.closing,super_admin,bendahara')->group(function () {
                Route::get('/closing', [FinanceFoundationController::class, 'closing'])->name('closing');
                Route::post('/closing', [FinanceFoundationController::class, 'storeClosing'])->name('closing.store');
            });
            Route::middleware('permission:finance.ledger,super_admin,bendahara')->get('/ledger', [FinanceFoundationController::class, 'ledger'])->name('ledger');
            Route::middleware('permission:finance.reports,super_admin,bendahara')->get('/reports', [FinanceFoundationController::class, 'reports'])->name('reports');
        });

        Route::middleware('permission:portal.teacher,super_admin,guru,wali_kelas,kepsek')->group(function () {
            Route::get('/portal/guru', [PortalFoundationController::class, 'guru'])->name('portal.guru');
            Route::post('/portal/guru/attendance', [PortalFoundationController::class, 'storeClassAttendance'])->name('portal.guru.attendance');
            Route::post('/portal/guru/self-attendance', [PortalFoundationController::class, 'storeTeacherAttendance'])->name('portal.guru.self-attendance');
        });
        Route::middleware('permission:portal.student,super_admin,siswa,orang_tua,wali_murid')->post('/portal/payments', [\App\Http\Controllers\PortalPaymentController::class, 'store'])->name('portal.payments.store');
        Route::middleware('permission:portal.student,super_admin,siswa')->group(function () {
            Route::get('/portal/siswa', [PortalFoundationController::class, 'siswa'])->name('portal.siswa');
            Route::post('/portal/siswa/attendance', [PortalFoundationController::class, 'storeAttendance'])->name('portal.siswa.attendance');
        });
        Route::middleware('permission:portal.permission,super_admin,siswa,orang_tua,wali_murid')
            ->post('/portal/siswa/permission', [PortalFoundationController::class, 'storePermission'])->name('portal.siswa.permission');
        Route::middleware('permission:portal.parent,super_admin,orang_tua')->get('/portal/orang-tua', [PortalFoundationController::class, 'orangTua'])->name('portal.orang-tua');

        Route::middleware('role:kepsek,tu,staf_tu')->post('/attendance/leave-entitlements', [AttendanceRequestController::class, 'setLeaveEntitlement'])->name('attendance.leave-entitlements.store');
        Route::middleware('role:orang_tua,wali_murid')->group(function () {
            Route::post('/portal/transfers', [\App\Http\Controllers\StudentTransferRequestController::class, 'store'])->name('portal.transfers.store');
            Route::get('/portal/keuangan-sekolah', [\App\Http\Controllers\GuardianFinanceAccessController::class, 'report'])->name('portal.school-finance');
        });
        Route::middleware('role:kepsek,wakasek,tu,staf_tu')->group(function () {
            Route::get('/student-transfer-requests', [\App\Http\Controllers\StudentTransferRequestController::class, 'index'])->name('student-transfers.index');
            Route::post('/student-transfer-requests/{transfer}/review', [\App\Http\Controllers\StudentTransferRequestController::class, 'review'])->name('student-transfers.review');
        });
        Route::get('/student-transfer-requests/{transfer}/document', [\App\Http\Controllers\StudentTransferRequestController::class, 'document'])->name('student-transfers.document');

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
            Route::put('/announcements/{announcement}', [OperationsFoundationController::class, 'updateAnnouncement'])->name('announcements.update');
            Route::delete('/announcements/{announcement}', [OperationsFoundationController::class, 'destroyAnnouncement'])->name('announcements.destroy');
        });

        Route::middleware('permission:materials.view,super_admin,kepsek,wakasek,tu,staf_tu,guru,wali_kelas,siswa')->group(function () {
            Route::get('/materials', [\App\Http\Controllers\LearningMaterialController::class, 'index'])->name('materials.index');
            Route::post('/materials', [\App\Http\Controllers\LearningMaterialController::class, 'store'])->name('materials.store');
            Route::put('/materials/{material}', [\App\Http\Controllers\LearningMaterialController::class, 'update'])->name('materials.update');
            Route::delete('/materials/{material}', [\App\Http\Controllers\LearningMaterialController::class, 'destroy'])->name('materials.destroy');
            Route::get('/materials/{material}/link', [\App\Http\Controllers\LearningMaterialController::class, 'link'])->name('materials.link');
            Route::get('/materials/{material}/download', [\App\Http\Controllers\LearningMaterialController::class, 'download'])->middleware('signed')->name('materials.download');
        });

        Route::middleware('permission:ai.use,super_admin,kepsek,wakasek,tu,staf_tu,guru,wali_kelas,siswa')->group(function () {
            Route::get('/ai', [OperationsFoundationController::class, 'ai'])->name('ai.index');
            Route::post('/ai', [OperationsFoundationController::class, 'storeAi'])->name('ai.store');
        });
        Route::middleware('permission:academic.alumni,super_admin,kepsek,wakasek,tu,staf_tu,alumni')->group(function () {
            Route::get('/alumni', [AcademicFoundationController::class, 'alumni'])->name('alumni.index');
            Route::put('/alumni/{alumni}', [AcademicFoundationController::class, 'updateAlumni'])->name('alumni.update');
            Route::post('/alumni/{alumni}/link', [AcademicFoundationController::class, 'linkAlumni'])->name('alumni.link');
        });

        // --- KESISWAAN & OPERASIONAL SEKOLAH (FASE 2) ---
        Route::middleware('permission:academic_years.view,super_admin,kepsek,wakasek,tu,staf_tu')->prefix('akademik')->name('akademik.')->group(function () {
            Route::get('/', [AcademicYearController::class, 'index'])->name('index');
            Route::post('/', [AcademicYearController::class, 'store'])->name('store');
            Route::post('/{id}/toggle', [AcademicYearController::class, 'toggleActive'])->name('toggle');
        });

        Route::middleware('permission:classes.view,super_admin,kepsek,wakasek,tu,staf_tu')->prefix('kelas')->name('kelas.')->group(function () {
            Route::get('/', [SchoolClassController::class, 'index'])->name('index');
            Route::post('/', [SchoolClassController::class, 'store'])->name('store');
        });

        Route::middleware('permission:students.view,super_admin,kepsek,wakasek,tu,staf_tu')->prefix('siswa')->name('siswa.')->group(function () {
            Route::get('/', [StudentController::class, 'index'])->name('index');
            Route::post('/', [StudentController::class, 'store'])->name('store');
            Route::get('/{student}/lifecycle/{history}/{kind}', [StudentController::class, 'lifecycleDocument'])->name('lifecycle-document');
        });

        Route::middleware('permission:teachers.view,super_admin,staf_tu')->prefix('guru')->name('guru.')->group(function () {
            Route::get('/', [TeacherController::class, 'index'])->name('index');
            Route::post('/', [TeacherController::class, 'store'])->name('store');
            Route::get('/{teacher}/documents/{record}/{kind}', [TeacherController::class, 'document'])->name('document');
        });

        Route::middleware('permission:organization.view,super_admin,staf_tu')->get('/struktur', function () {
            $schoolId = app(\App\Services\SchoolContext::class)->activeSchoolIdFor();
            $principal = \App\Models\Teacher::where('school_id', $schoolId)->where('role_type', 'Kepala Sekolah')->where('is_active', true)->first();
            $treasurer = \App\Models\Teacher::where('school_id', $schoolId)->where('role_type', 'Bendahara')->where('is_active', true)->first();
            $staff = \App\Models\Teacher::where('school_id', $schoolId)->where('role_type', 'Staf TU')->where('is_active', true)->get();
            $teachers = \App\Models\Teacher::where('school_id', $schoolId)->where('role_type', 'Guru')->where('is_active', true)->get();

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
                Route::post('/transaksi/pemasukan', [FinanceFoundationController::class, 'storeIncome'])->name('transaksi.pemasukan');
            });
            Route::middleware('permission:spp.reports,super_admin,kepsek,bendahara')->get('/laporan', [SppReportController::class, 'index'])->name('laporan.index');
        });

        // --- KEUANGAN BOS & BKU (FASE 4) ---
        Route::prefix('bos')->name('bos.')->group(function () {
            Route::middleware('permission:bos.budgets,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/anggaran', [BosController::class, 'anggaran'])->name('anggaran.index');
                Route::post('/anggaran', [BosController::class, 'storeAnggaran'])->name('anggaran.store');
                Route::put('/anggaran/{category}', [BosController::class, 'updateAnggaran'])->name('anggaran.update');
                Route::delete('/anggaran/{category}', [BosController::class, 'destroyAnggaran'])->name('anggaran.destroy');
            });
            Route::middleware('permission:bos.expenses,super_admin,kepsek,bendahara')->group(function () {
                Route::get('/belanja', [BosController::class, 'belanja'])->name('belanja.index');
                Route::get('/belanja/{expense}/proof', [BosController::class, 'expenseProof'])->name('belanja.proof');
                Route::post('/belanja', [BosController::class, 'storeBelanja'])->name('belanja.store');
                Route::put('/belanja/{expense}', [BosController::class, 'updateBelanja'])->name('belanja.update');
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
            Route::middleware('permission:student_attendance.view,super_admin,kepsek,pic_sekolah,wakasek,tu,staf_tu')->group(function () {
                Route::get('/siswa', [AttendanceController::class, 'siswa'])->name('siswa');
                Route::post('/siswa', [AttendanceController::class, 'storeSiswa'])->name('siswa.store');
            });
            Route::middleware('permission:teacher_attendance.view,super_admin,kepsek,pic_sekolah,wakasek,tu,staf_tu')->group(function () {
                Route::get('/gtk', [AttendanceController::class, 'gtk'])->name('gtk');
                Route::post('/gtk', [AttendanceController::class, 'storeGtk'])->name('gtk.store');
            });
        });

    });
});
