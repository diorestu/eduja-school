<?php

namespace App\Helpers;

class MenuHelper
{
    public static function getMainNavItems()
    {
        $user = auth()->user();
        $items = [
            [
                'icon' => 'dashboard',
                'name' => 'Dashboard',
                'path' => '/dashboard',
            ],
            [
                'icon' => 'user-profile',
                'name' => 'Kesiswaan',
                'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu'],
                'subItems' => [
                    ['name' => 'Tahun Akademik', 'path' => '/akademik', 'pro' => false],
                    ['name' => 'Data Kelas', 'path' => '/kelas', 'pro' => false],
                    ['name' => 'Data Siswa', 'path' => '/siswa', 'pro' => false],
                    ['name' => 'Data GTK (Guru/Staf)', 'path' => '/guru', 'pro' => false],
                    ['name' => 'Struktur Organisasi', 'path' => '/struktur', 'pro' => false],
                    ['name' => 'Presensi Siswa', 'path' => '/presensi/siswa', 'pro' => false],
                    ['name' => 'Presensi GTK', 'path' => '/presensi/gtk', 'pro' => false],
                ],
            ],
            [
                'icon' => 'ecommerce',
                'name' => 'Keuangan SPP',
                'roles' => ['super_admin', 'kepsek', 'bendahara'],
                'subItems' => [
                    ['name' => 'Tarif Biaya', 'path' => '/spp/tarif', 'pro' => false],
                    ['name' => 'Transaksi SPP', 'path' => '/spp/transaksi', 'pro' => false],
                    ['name' => 'Laporan SPP', 'path' => '/spp/laporan', 'pro' => false],
                    ['name' => 'Tabungan Siswa', 'path' => '/tabungan', 'pro' => false],
                ],
            ],
            [
                'icon' => 'forms',
                'name' => 'Keuangan BOS & BKU',
                'roles' => ['super_admin', 'kepsek', 'bendahara'],
                'subItems' => [
                    ['name' => 'Anggaran RKAS', 'path' => '/bos/anggaran', 'pro' => false],
                    ['name' => 'Pencatatan Belanja', 'path' => '/bos/belanja', 'pro' => false],
                    ['name' => 'Buku Kas Umum', 'path' => '/bos/bku', 'pro' => false],
                ],
            ],
            [
                'icon' => 'charts',
                'name' => 'Eksekutif',
                'roles' => ['super_admin', 'dinas', 'yayasan', 'kepsek'],
                'subItems' => [
                    ['name' => 'Dashboard Dinas', 'path' => '/dinas', 'pro' => false],
                    ['name' => 'Dashboard Yayasan', 'path' => '/yayasan', 'pro' => false],
                ],
            ],
            [
                'icon' => 'tables',
                'name' => 'Akademik Lanjutan',
                'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu'],
                'subItems' => [
                    ['name' => 'Jurusan', 'path' => '/academic/departments', 'pro' => false],
                    ['name' => 'Kenaikan Kelas', 'path' => '/academic/promotion', 'pro' => false],
                    ['name' => 'Kelulusan', 'path' => '/academic/graduation', 'pro' => false],
                    ['name' => 'Alumni', 'path' => '/alumni', 'pro' => false],
                ],
            ],
            [
                'icon' => 'ecommerce',
                'name' => 'Finance Foundation',
                'roles' => ['super_admin', 'bendahara'],
                'subItems' => [
                    ['name' => 'Rekening & Wallet', 'path' => '/finance/accounts', 'pro' => false],
                    ['name' => 'Jenis Pemasukan', 'path' => '/finance/income-types', 'pro' => false],
                    ['name' => 'Jenis Pengeluaran', 'path' => '/finance/expense-types', 'pro' => false],
                    ['name' => 'Anggaran', 'path' => '/finance/budgets', 'pro' => false],
                    ['name' => 'Approval', 'path' => '/finance/approvals', 'pro' => false],
                    ['name' => 'Tagihan Komite', 'path' => '/finance/billing', 'pro' => false],
                    ['name' => 'Tutup Buku', 'path' => '/finance/closing', 'pro' => false],
                ],
            ],
            [
                'icon' => 'calendar',
                'name' => 'Absensi & Komunikasi',
                'roles' => ['super_admin', 'kepsek', 'wakasek', 'tu', 'staf_tu', 'guru', 'wali_kelas'],
                'subItems' => [
                    ['name' => 'Permohonan Izin', 'path' => '/attendance/requests', 'pro' => false],
                    ['name' => 'RFID Sync', 'path' => '/attendance/rfid-sync', 'pro' => false],
                    ['name' => 'Pengumuman', 'path' => '/announcements', 'pro' => false],
                    ['name' => 'AI Assistant', 'path' => '/ai', 'pro' => false],
                ],
            ],
            [
                'icon' => 'pages',
                'name' => 'Portal',
                'roles' => ['super_admin', 'guru', 'wali_kelas', 'siswa', 'orang_tua'],
                'subItems' => [
                    ['name' => 'Portal Guru', 'path' => '/portal/guru', 'pro' => false],
                    ['name' => 'Portal Siswa', 'path' => '/portal/siswa', 'pro' => false],
                    ['name' => 'Portal Orang Tua', 'path' => '/portal/orang-tua', 'pro' => false],
                ],
            ],
        ];

        return array_values(array_filter($items, function ($item) use ($user) {
            if ($item['name'] === 'Dashboard') {
                return true;
            }

            if (! $user) {
                return false;
            }

            return $user->hasRole($item['roles'] ?? []);
        }));
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
