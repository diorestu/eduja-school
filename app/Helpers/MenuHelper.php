<?php

namespace App\Helpers;

class MenuHelper
{
    public static function menuDefinitions(): array
    {
        return [
            [
                'icon' => 'dashboard',
                'name' => 'Dashboard',
                'path' => '/dashboard',
            ],
            [
                'icon' => 'user-profile',
                'name' => 'Kesiswaan',
                'subItems' => [
                    ['name' => 'Tahun Akademik', 'path' => '/akademik', 'permission' => 'academic_years.view', 'roles' => ['super_admin', 'kepsek', 'staf_tu'], 'pro' => false],
                    ['name' => 'Data Kelas', 'path' => '/kelas', 'permission' => 'classes.view', 'roles' => ['super_admin', 'kepsek', 'staf_tu'], 'pro' => false],
                    ['name' => 'Data Siswa', 'path' => '/siswa', 'permission' => 'students.view', 'roles' => ['super_admin', 'staf_tu'], 'pro' => false],
                    ['name' => 'Data GTK (Guru/Staf)', 'path' => '/guru', 'permission' => 'teachers.view', 'roles' => ['super_admin', 'staf_tu'], 'pro' => false],
                    ['name' => 'Struktur Organisasi', 'path' => '/struktur', 'permission' => 'organization.view', 'roles' => ['super_admin', 'staf_tu'], 'pro' => false],
                    ['name' => 'Presensi Siswa', 'path' => '/presensi/siswa', 'permission' => 'student_attendance.view', 'roles' => ['super_admin', 'staf_tu'], 'pro' => false],
                    ['name' => 'Presensi GTK', 'path' => '/presensi/gtk', 'permission' => 'teacher_attendance.view', 'roles' => ['super_admin', 'staf_tu'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'ecommerce',
                'name' => 'Keuangan SPP',
                'subItems' => [
                    ['name' => 'Tarif Biaya', 'path' => '/spp/tarif', 'permission' => 'spp.tariffs', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                    ['name' => 'Transaksi SPP', 'path' => '/spp/transaksi', 'permission' => 'spp.transactions', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                    ['name' => 'Laporan SPP', 'path' => '/spp/laporan', 'permission' => 'spp.reports', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                    ['name' => 'Tabungan Siswa', 'path' => '/tabungan', 'permission' => 'student_savings.view', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'forms',
                'name' => 'Keuangan BOS & BKU',
                'subItems' => [
                    ['name' => 'Anggaran RKAS', 'path' => '/bos/anggaran', 'permission' => 'bos.budgets', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                    ['name' => 'Pencatatan Belanja', 'path' => '/bos/belanja', 'permission' => 'bos.expenses', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                    ['name' => 'Buku Kas Umum', 'path' => '/bos/bku', 'permission' => 'bos.ledger', 'roles' => ['super_admin', 'kepsek', 'bendahara'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'charts',
                'name' => 'Eksekutif',
                'subItems' => [
                    ['name' => 'Dashboard Dinas', 'path' => '/dinas', 'permission' => 'executive.district', 'roles' => ['dinas'], 'pro' => false],
                    ['name' => 'Dashboard Yayasan', 'path' => '/yayasan', 'permission' => 'executive.foundation', 'roles' => ['yayasan'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'tables',
                'name' => 'Akademik Lanjutan',
                'subItems' => [
                    ['name' => 'Jurusan', 'path' => '/academic/departments', 'permission' => 'academic.departments', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu'], 'pro' => false],
                    ['name' => 'Kenaikan Kelas', 'path' => '/academic/promotion', 'permission' => 'academic.promotion', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu'], 'pro' => false],
                    ['name' => 'Kelulusan', 'path' => '/academic/graduation', 'permission' => 'academic.graduation', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu'], 'pro' => false],
                    ['name' => 'Alumni', 'path' => '/alumni', 'permission' => 'academic.alumni', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu', 'alumni'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'ecommerce',
                'name' => 'Finance Foundation',
                'subItems' => [
                    ['name' => 'Rekening & Wallet', 'path' => '/finance/accounts', 'permission' => 'finance.accounts', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Jenis Pemasukan', 'path' => '/finance/income-types', 'permission' => 'finance.income_types', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Jenis Pengeluaran', 'path' => '/finance/expense-types', 'permission' => 'finance.expense_types', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Alokasi Dana', 'path' => '/finance/allocations', 'permission' => 'finance.allocations', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Tahun Anggaran', 'path' => '/finance/budget-years', 'permission' => 'finance.budget_years', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Anggaran', 'path' => '/finance/budgets', 'permission' => 'finance.budgets', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Approval', 'path' => '/finance/approvals', 'permission' => 'finance.approvals', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Tagihan Komite', 'path' => '/finance/billing', 'permission' => 'finance.billing', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Tutup Buku', 'path' => '/finance/closing', 'permission' => 'finance.closing', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Ledger Finance', 'path' => '/finance/ledger', 'permission' => 'finance.ledger', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                    ['name' => 'Laporan Finance', 'path' => '/finance/reports', 'permission' => 'finance.reports', 'roles' => ['super_admin', 'bendahara'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'calendar',
                'name' => 'Absensi & Komunikasi',
                'subItems' => [
                    ['name' => 'Permohonan Izin', 'path' => '/attendance/requests', 'permission' => 'attendance.requests', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas'], 'pro' => false],
                    ['name' => 'RFID Sync', 'path' => '/attendance/rfid-sync', 'permission' => 'attendance.rfid', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas'], 'pro' => false],
                    ['name' => 'Pengumuman', 'path' => '/announcements', 'permission' => 'announcements.view', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas'], 'pro' => false],
                    ['name' => 'AI Assistant', 'path' => '/ai', 'permission' => 'ai.use', 'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'pages',
                'name' => 'Portal',
                'subItems' => [
                    ['name' => 'Portal Guru', 'path' => '/portal/guru', 'permission' => 'portal.teacher', 'roles' => ['super_admin', 'guru', 'wali_kelas', 'kepsek'], 'pro' => false],
                    ['name' => 'Portal Siswa', 'path' => '/portal/siswa', 'permission' => 'portal.student', 'roles' => ['super_admin', 'siswa'], 'pro' => false],
                    ['name' => 'Portal Orang Tua', 'path' => '/portal/orang-tua', 'permission' => 'portal.parent', 'roles' => ['super_admin', 'orang_tua'], 'pro' => false],
                ],
            ],
            [
                'icon' => 'authentication',
                'name' => 'Permission',
                'path' => '/settings/permissions',
                'roles' => ['super_admin', 'kepsek'],
            ],
        ];
    }

    public static function getMainNavItems(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $permissionService = app(\App\Services\PermissionService::class);
        $visibleItems = [];

        foreach (self::menuDefinitions() as $item) {
            if ($item['name'] === 'Dashboard') {
                $visibleItems[] = $item;

                continue;
            }

            if (isset($item['subItems'])) {
                $item['subItems'] = array_values(array_filter(
                    $item['subItems'],
                    fn (array $subItem) => $permissionService->can(
                        $user,
                        $subItem['permission'],
                        $subItem['roles'] ?? [],
                    ),
                ));

                if (! empty($item['subItems'])) {
                    $visibleItems[] = $item;
                }

                continue;
            }

            if ($user->hasRole($item['roles'] ?? [])) {
                $visibleItems[] = $item;
            }
        }

        return $visibleItems;
    }

    public static function permissionCatalog(): array
    {
        return array_values(array_filter(array_map(function (array $item) {
            if (empty($item['subItems'])) {
                return null;
            }

            return [
                'name' => $item['name'],
                'icon' => $item['icon'],
                'items' => array_map(fn (array $subItem) => [
                    'name' => $subItem['name'],
                    'permission' => $subItem['permission'],
                    'roles' => $subItem['roles'] ?? [],
                ], $item['subItems']),
            ];
        }, self::menuDefinitions())));
    }

    public static function getOthersItems()
    {
        return [];
    }

    public static function getMenuGroups()
    {
        return [
            [
                'title' => 'Menu',
                'items' => self::getMainNavItems(),
            ],
        ];
    }

    public static function isActive($path)
    {
        return request()->is(ltrim($path, '/'));
    }

    public static function getIconSvg($iconName)
    {
        $icons = [
            'dashboard' => '<i class="bx bxs-grid-alt text-xl"></i>',
            'ai-assistant' => '<i class="bx bx-bot text-xl"></i>',
            'ecommerce' => '<i class="bx bxs-wallet text-xl"></i>',
            'calendar' => '<i class="bx bxs-calendar text-xl"></i>',
            'user-profile' => '<i class="bx bxs-group text-xl"></i>',
            'task' => '<i class="bx bx-list-check text-xl"></i>',
            'forms' => '<i class="bx bxs-file-doc text-xl"></i>',
            'tables' => '<i class="bx bx-table text-xl"></i>',
            'pages' => '<i class="bx bxs-copy text-xl"></i>',
            'charts' => '<i class="bx bxs-chart text-xl"></i>',
            'ui-elements' => '<i class="bx bxs-component text-xl"></i>',
            'authentication' => '<i class="bx bxs-lock-alt text-xl"></i>',
            'chat' => '<i class="bx bxs-chat text-xl"></i>',
            'support-ticket' => '<i class="bx bxs-help-circle text-xl"></i>',
            'email' => '<i class="bx bxs-envelope text-xl"></i>',
        ];

        return $icons[$iconName] ?? '<i class="bx bx-star text-xl"></i>';
    }
}
