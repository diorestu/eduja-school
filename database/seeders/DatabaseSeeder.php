<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\BillingItem;
use App\Models\BudgetCategory;
use App\Models\BudgetPlan;
use App\Models\BudgetYear;
use App\Models\ClassStudent;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\IncomeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\School;
use App\Models\SchoolAccount;
use App\Models\SchoolClass;
use App\Models\SchoolUserRole;
use App\Models\SppTariff;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentSaving;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = School::updateOrCreate(
            ['npsn' => '50100199'],
            [
                'name' => 'SMA EDUJA Nusantara',
                'level' => 'sma',
                'ownership' => 'swasta',
                'foundation_name' => 'Yayasan EDUJA',
                'city' => 'Makassar',
                'province' => 'Sulawesi Selatan',
                'is_active' => true,
            ],
        );

        // 1. Seed Users (Roles)
        $admin = User::updateOrCreate(
            ['email' => 'admin@eduja.sch.id'],
            [
                'name' => 'Dr. H. Ahmad Dahlan, M.Pd.',
                'password' => Hash::make('password'),
                'role' => 'super_admin', // Kepala Sekolah
            ]
        );

        $bendahara = User::updateOrCreate(
            ['email' => 'bendahara@eduja.sch.id'],
            [
                'name' => 'Siti Khadijah, S.E.',
                'password' => Hash::make('password'),
                'role' => 'bendahara', // Bendahara Sekolah
            ]
        );

        $tu = User::updateOrCreate(
            ['email' => 'tu@eduja.sch.id'],
            [
                'name' => 'Bambang Triyono, A.Md.',
                'password' => Hash::make('password'),
                'role' => 'staf_tu', // Tata Usaha
            ]
        );

        foreach ([[$admin, 'kepsek'], [$admin, 'yayasan'], [$bendahara, 'bendahara'], [$tu, 'tu']] as [$user, $role]) {
            SchoolUserRole::updateOrCreate(
                ['school_id' => $school->id, 'user_id' => $user->id, 'role' => $role],
                ['is_active' => true],
            );
        }

        // 2. Seed Academic Years
        $prevYear = AcademicYear::updateOrCreate(
            ['year' => '2025/2026', 'semester' => 'Genap'],
            ['school_id' => $school->id, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_active' => false]
        );

        $activeYear = AcademicYear::updateOrCreate(
            ['year' => '2026/2027', 'semester' => 'Ganjil'],
            ['school_id' => $school->id, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]
        );

        $department = Department::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'IPA'],
            ['name' => 'Ilmu Pengetahuan Alam', 'is_active' => true],
        );

        // 3. Seed Teachers & Staff
        $teachersData = [
            [
                'nuptk' => '1234567890123000',
                'nip' => '197001011995031001',
                'name' => 'Dr. H. Ahmad Dahlan, M.Pd.',
                'role_type' => 'Kepala Sekolah',
                'staff_type' => 'PNS',
                'phone' => '081111111111',
            ],
            [
                'nuptk' => '1234567890123005',
                'nip' => '198203042008012002',
                'name' => 'Siti Khadijah, S.E.',
                'role_type' => 'Bendahara',
                'staff_type' => 'PNS',
                'phone' => '082222222222',
            ],
            [
                'nuptk' => '1234567890123001',
                'nip' => '198001012010011001',
                'name' => 'Budi Santoso, S.Pd.',
                'role_type' => 'Guru',
                'staff_type' => 'PNS',
                'phone' => '081234567890',
            ],
            [
                'nuptk' => '1234567890123002',
                'nip' => '198505122015022002',
                'name' => 'Sri Wahyuni, S.Si.',
                'role_type' => 'Guru',
                'staff_type' => 'PPPK',
                'phone' => '081345678901',
            ],
            [
                'nuptk' => '1234567890123003',
                'nip' => '197809182005011003',
                'name' => 'Ahmad Hidayat, S.Pd.',
                'role_type' => 'Guru',
                'staff_type' => 'Honorer',
                'phone' => '081456789012',
            ],
            [
                'nuptk' => null,
                'nip' => null,
                'name' => 'Rina Herawati, A.Md.',
                'role_type' => 'Staf TU',
                'staff_type' => 'Honorer',
                'phone' => '081567890123',
            ],
        ];

        $teachers = [];
        foreach ($teachersData as $t) {
            $t['school_id'] = $school->id;
            $t['status'] = 'active';
            $teachers[] = Teacher::updateOrCreate(['name' => $t['name']], $t);
        }

        // 4. Seed Classes
        $classA = SchoolClass::updateOrCreate(
            ['name' => 'Kelas X-A', 'academic_year_id' => $activeYear->id],
            ['school_id' => $school->id, 'grade' => 10, 'department_id' => $department->id, 'teacher_id' => $teachers[0]->id]
        );

        $classB = SchoolClass::updateOrCreate(
            ['name' => 'Kelas XI-IPA', 'academic_year_id' => $activeYear->id],
            ['school_id' => $school->id, 'grade' => 11, 'department_id' => $department->id, 'teacher_id' => $teachers[1]->id]
        );

        $classC = SchoolClass::updateOrCreate(
            ['name' => 'Kelas XII-IPS', 'academic_year_id' => $activeYear->id],
            ['school_id' => $school->id, 'grade' => 12, 'department_id' => $department->id, 'teacher_id' => $teachers[2]->id]
        );

        // 5. Seed Students
        $studentsData = [
            // Kelas X-A
            ['nis' => '10001', 'nisn' => '0091234501', 'name' => 'Aditya Pratama', 'gender' => 'L', 'parent_name' => 'Slamet Pratama'],
            ['nis' => '10002', 'nisn' => '0091234502', 'name' => 'Siti Aminah', 'gender' => 'P', 'parent_name' => 'Rahman Hakim'],
            ['nis' => '10003', 'nisn' => '0091234503', 'name' => 'Rizky Ramadan', 'gender' => 'L', 'parent_name' => 'Yusuf Ramadan'],
            ['nis' => '10004', 'nisn' => '0091234504', 'name' => 'Dewi Lestari', 'gender' => 'P', 'parent_name' => 'Wibowo'],
            ['nis' => '10005', 'nisn' => '0091234505', 'name' => 'Eko Prasetyo', 'gender' => 'L', 'parent_name' => 'Joko Prasetyo'],

            // Kelas XI-IPA
            ['nis' => '11001', 'nisn' => '0081234506', 'name' => 'Fajar Sidik', 'gender' => 'L', 'parent_name' => 'Hasan Sidik'],
            ['nis' => '11002', 'nisn' => '0081234507', 'name' => 'Gita Gutawa', 'gender' => 'P', 'parent_name' => 'Lutfi Gutawa'],
            ['nis' => '11003', 'nisn' => '0081234508', 'name' => 'Hendra Wijaya', 'gender' => 'L', 'parent_name' => 'Agus Wijaya'],
            ['nis' => '11004', 'nisn' => '0081234509', 'name' => 'Indah Permatasari', 'gender' => 'P', 'parent_name' => 'Suradi'],
            ['nis' => '11005', 'nisn' => '0081234510', 'name' => 'Joni Iskandar', 'gender' => 'L', 'parent_name' => 'Kurniawan'],

            // Kelas XII-IPS
            ['nis' => '12001', 'nisn' => '0071234511', 'name' => 'Kartika Sari', 'gender' => 'P', 'parent_name' => 'Mulyadi'],
            ['nis' => '12002', 'nisn' => '0071234512', 'name' => 'Lucky Perdana', 'gender' => 'L', 'parent_name' => 'Sujatmiko'],
            ['nis' => '12003', 'nisn' => '0071234513', 'name' => 'Mega Utami', 'gender' => 'P', 'parent_name' => 'Supriyadi'],
            ['nis' => '12004', 'nisn' => '0071234514', 'name' => 'Naufal Azhar', 'gender' => 'L', 'parent_name' => 'Sofyan Azhar'],
            ['nis' => '12005', 'nisn' => '0071234515', 'name' => 'Olivia Zalianty', 'gender' => 'P', 'parent_name' => 'Zulkifli'],
        ];

        $studentsList = [];
        foreach ($studentsData as $index => $s) {
            $student = Student::updateOrCreate(
                ['nis' => $s['nis']],
                [
                    'nisn' => $s['nisn'],
                    'name' => $s['name'],
                    'gender' => $s['gender'],
                    'phone' => '0899'.rand(100000, 999999),
                    'parent_name' => $s['parent_name'],
                    'parent_phone' => '0812'.rand(100000, 999999),
                    'school_id' => $school->id,
                    'is_active' => true,
                    'status' => 'active',
                ]
            );

            $studentsList[] = $student;

            // Assign to class based on NIS prefix
            if ($index < 5) {
                ClassStudent::updateOrCreate([
                    'school_class_id' => $classA->id,
                    'student_id' => $student->id,
                ]);
            } elseif ($index < 10) {
                ClassStudent::updateOrCreate([
                    'school_class_id' => $classB->id,
                    'student_id' => $student->id,
                ]);
            } else {
                ClassStudent::updateOrCreate([
                    'school_class_id' => $classC->id,
                    'student_id' => $student->id,
                ]);
            }
        }

        // 6. Seed SPP Tariffs
        $tariffA = SppTariff::updateOrCreate(
            ['name' => 'SPP Bulanan Kelas X', 'academic_year_id' => $activeYear->id, 'school_class_id' => $classA->id],
            ['school_id' => $school->id, 'amount' => 200000, 'type' => 'Bulanan']
        );

        $tariffB = SppTariff::updateOrCreate(
            ['name' => 'SPP Bulanan Kelas XI', 'academic_year_id' => $activeYear->id, 'school_class_id' => $classB->id],
            ['school_id' => $school->id, 'amount' => 250000, 'type' => 'Bulanan']
        );

        $tariffC = SppTariff::updateOrCreate(
            ['name' => 'SPP Bulanan Kelas XII', 'academic_year_id' => $activeYear->id, 'school_class_id' => $classC->id],
            ['school_id' => $school->id, 'amount' => 300000, 'type' => 'Bulanan']
        );

        // 7. Seed Invoices & Transactions
        // Invoice 1: Aditya Pratama - Lunas
        $inv1 = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[0]->id,
            'academic_year_id' => $activeYear->id,
            'invoice_number' => 'INV/202607/0001',
            'due_date' => '2026-07-31',
            'total_amount' => 200000,
            'status' => 'Lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv1->id,
            'spp_tariff_id' => $tariffA->id,
            'name' => 'SPP Bulanan - Juli 2026',
            'amount' => 200000,
        ]);

        Transaction::create([
            'invoice_id' => $inv1->id,
            'amount_paid' => 200000,
            'payment_date' => '2026-07-01',
            'payment_method' => 'Tunai',
            'receipt_number' => 'RCP/20260701/0001',
            'recipient_name' => 'Siti Khadijah, S.E.',
        ]);

        // Invoice 2: Siti Aminah - Cicilan
        $inv2 = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[1]->id,
            'academic_year_id' => $activeYear->id,
            'invoice_number' => 'INV/202607/0002',
            'due_date' => '2026-07-31',
            'total_amount' => 200000,
            'status' => 'Cicilan',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv2->id,
            'spp_tariff_id' => $tariffA->id,
            'name' => 'SPP Bulanan - Juli 2026',
            'amount' => 200000,
        ]);

        Transaction::create([
            'invoice_id' => $inv2->id,
            'amount_paid' => 120000,
            'payment_date' => '2026-07-03',
            'payment_method' => 'Tunai',
            'receipt_number' => 'RCP/20260703/0002',
            'recipient_name' => 'Siti Khadijah, S.E.',
        ]);

        // Invoice 3: Fajar Sidik - Belum Lunas
        $inv3 = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[5]->id,
            'academic_year_id' => $activeYear->id,
            'invoice_number' => 'INV/202607/0003',
            'due_date' => '2026-07-31',
            'total_amount' => 250000,
            'status' => 'Belum Lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv3->id,
            'spp_tariff_id' => $tariffB->id,
            'name' => 'SPP Bulanan - Juli 2026',
            'amount' => 250000,
        ]);

        // Invoice 4: Kartika Sari - Lunas
        $inv4 = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[10]->id,
            'academic_year_id' => $activeYear->id,
            'invoice_number' => 'INV/202607/0004',
            'due_date' => '2026-07-31',
            'total_amount' => 300000,
            'status' => 'Lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv4->id,
            'spp_tariff_id' => $tariffC->id,
            'name' => 'SPP Bulanan - Juli 2026',
            'amount' => 300000,
        ]);

        Transaction::create([
            'invoice_id' => $inv4->id,
            'amount_paid' => 300000,
            'payment_date' => '2026-07-01',
            'payment_method' => 'Transfer Bank',
            'receipt_number' => 'RCP/20260701/0003',
            'recipient_name' => 'Siti Khadijah, S.E.',
        ]);

        // 6. Seed Budget Categories (BOS RKAS)
        $categories = [
            ['code' => '03.01', 'name' => 'Alat Tulis Kantor & Bahan Habis Pakai', 'source_funding' => 'BOS'],
            ['code' => '03.02', 'name' => 'Penggandaan Materi Pembelajaran & Ujian', 'source_funding' => 'BOS'],
            ['code' => '04.01', 'name' => 'Honorarium Guru Honorer & GTK', 'source_funding' => 'BOS'],
            ['code' => '05.01', 'name' => 'Perawatan Sarana & Prasarana Sekolah', 'source_funding' => 'BOS'],
            ['code' => '06.01', 'name' => 'Daya dan Jasa (Listrik, Air, Internet)', 'source_funding' => 'BOS'],
            ['code' => '07.01', 'name' => 'Penyelenggaraan Rapat & Konsumsi Sekolah', 'source_funding' => 'BOS'],
            ['code' => '08.01', 'name' => 'Pembelian Alat Peraga & Media Belajar', 'source_funding' => 'BOS'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $cat['school_id'] = $school->id;
            $categoryModels[] = BudgetCategory::create($cat);
        }

        // 7. Seed Mock Expenses (Dana BOS)
        Expense::create([
            'school_id' => $school->id,
            'budget_category_id' => $categoryModels[0]->id, // ATK
            'academic_year_id' => $activeYear->id,
            'expense_name' => 'Pembelian Kertas A4 & ATK Ujian Ganjil',
            'amount' => 1500000.00,
            'transaction_date' => '2026-07-02',
            'source_funding' => 'BOS',
            'payment_method' => 'Tunai',
            'reference_invoice' => 'NOTA-202607-001',
            'recipient_name' => 'Toko Buku Restu Ibu',
            'tax_type' => 'PPN',
            'tax_amount' => 165000.00, // 11% PPN
            'is_tax_paid' => true,
        ]);

        Expense::create([
            'school_id' => $school->id,
            'budget_category_id' => $categoryModels[2]->id, // Honor GTT
            'academic_year_id' => $activeYear->id,
            'expense_name' => 'Pembayaran Honor Guru Honorer Bulan Juli 2026',
            'amount' => 4500000.00,
            'transaction_date' => '2026-07-05',
            'source_funding' => 'BOS',
            'payment_method' => 'Transfer',
            'reference_invoice' => 'SPH-202607-001',
            'recipient_name' => 'Ahmad Hidayat, S.Pd.',
            'tax_type' => 'PPh 21',
            'tax_amount' => 0.00,
            'is_tax_paid' => false,
        ]);

        Expense::create([
            'school_id' => $school->id,
            'budget_category_id' => $categoryModels[4]->id, // Daya & Jasa
            'academic_year_id' => $activeYear->id,
            'expense_name' => 'Pembayaran Tagihan Listrik & Internet Sekolah',
            'amount' => 850000.00,
            'transaction_date' => '2026-07-06',
            'source_funding' => 'BOS',
            'payment_method' => 'Transfer',
            'reference_invoice' => 'PLN-202607-01',
            'recipient_name' => 'PLN & IndiHome',
            'tax_type' => null,
            'tax_amount' => 0.00,
            'is_tax_paid' => false,
        ]);

        // 8. Seed Student Savings (Tabungan Siswa)
        StudentSaving::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[0]->id, // Ahmad
            'academic_year_id' => $activeYear->id,
            'type' => 'Setoran',
            'amount' => 50000.00,
            'transaction_date' => '2026-07-01',
            'reference_number' => 'SAV/20260701/0001',
            'note' => 'Setoran awal',
            'recipient_name' => 'Rina Herawati, A.Md.',
        ]);

        StudentSaving::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[0]->id, // Ahmad
            'academic_year_id' => $activeYear->id,
            'type' => 'Setoran',
            'amount' => 20000.00,
            'transaction_date' => '2026-07-03',
            'reference_number' => 'SAV/20260703/0001',
            'note' => 'Titipan uang saku',
            'recipient_name' => 'Rina Herawati, A.Md.',
        ]);

        StudentSaving::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[0]->id, // Ahmad
            'academic_year_id' => $activeYear->id,
            'type' => 'Penarikan',
            'amount' => 10000.00,
            'transaction_date' => '2026-07-05',
            'reference_number' => 'SAV/20260705/0001',
            'note' => 'Beli alat tulis',
            'recipient_name' => 'Rina Herawati, A.Md.',
        ]);

        StudentSaving::create([
            'school_id' => $school->id,
            'student_id' => $studentsList[1]->id, // Siti
            'academic_year_id' => $activeYear->id,
            'type' => 'Setoran',
            'amount' => 100000.00,
            'transaction_date' => '2026-07-02',
            'reference_number' => 'SAV/20260702/0001',
            'note' => 'Tabungan bulanan',
            'recipient_name' => 'Rina Herawati, A.Md.',
        ]);

        // 9. Seed Student Attendance (Presensi Siswa)
        $today = \Illuminate\Support\Carbon::today();
        foreach ($studentsList as $student) {
            $classStudent = ClassStudent::where('student_id', $student->id)->first();
            if ($classStudent) {
                $rand = rand(1, 100);
                $status = 'H';
                if ($rand > 95) {
                    $status = 'A';
                } elseif ($rand > 90) {
                    $status = 'I';
                } elseif ($rand > 85) {
                    $status = 'S';
                }

                StudentAttendance::create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'school_class_id' => $classStudent->school_class_id,
                    'attendance_date' => $today->toDateString(),
                    'status' => $status,
                    'note' => $status !== 'H' ? 'Absensi seeder' : null,
                ]);
            }
        }

        // 10. Seed Teacher Attendance (Presensi GTK)
        $activeTeachers = Teacher::where('is_active', true)->get();
        foreach ($activeTeachers as $teacher) {
            $rand = rand(1, 100);
            $status = 'H';
            if ($rand > 98) {
                $status = 'A';
            } elseif ($rand > 95) {
                $status = 'DL';
            } elseif ($rand > 92) {
                $status = 'I';
            } elseif ($rand > 89) {
                $status = 'S';
            }

            TeacherAttendance::create([
                'school_id' => $school->id,
                'teacher_id' => $teacher->id,
                'attendance_date' => $today->toDateString(),
                'status' => $status,
                'note' => $status !== 'H' ? 'Absensi seeder' : null,
            ]);
        }

        SchoolAccount::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Kas Bendahara'],
            ['type' => 'Tunai', 'current_balance' => 2500000, 'is_active' => true],
        );

        $incomeType = IncomeType::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'KOMITE'],
            ['name' => 'Iuran Komite', 'category' => 'komite', 'uses_allocation' => true],
        );

        ExpenseType::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'BOS-ATK'],
            ['name' => 'ATK dan Bahan Habis Pakai', 'source_funding' => 'BOS', 'bos_component' => 'Operasional Sekolah', 'requires_approval' => true],
        );

        $budgetYear = BudgetYear::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'TA 2026'],
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active'],
        );

        BudgetPlan::updateOrCreate(
            ['school_id' => $school->id, 'budget_year_id' => $budgetYear->id, 'program_name' => 'Operasional Sekolah'],
            ['source_funding' => 'BOS', 'activity_name' => 'Pengadaan ATK', 'amount' => 7500000, 'status' => 'approved'],
        );

        BillingItem::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Iuran Komite Juli 2026'],
            [
                'income_type_id' => $incomeType->id,
                'amount' => 200000,
                'allow_installment' => true,
                'minimum_installment' => 50000,
                'has_late_fee' => true,
                'late_fee_per_day' => 1000,
                'late_fee_maximum' => 25000,
                'billing_frequency' => 'Bulanan',
                'due_date' => '2026-07-31',
                'target_type' => 'school',
                'status' => 'active',
            ],
        );

        Announcement::updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Awal Tahun Ajaran 2026/2027'],
            ['created_by' => $admin->id, 'category' => 'akademik', 'body' => 'Kegiatan belajar mengajar dimulai sesuai kalender akademik.', 'target_type' => 'school', 'published_at' => now()],
        );
    }
}
