# School Role Permissions Design

## Tujuan

Memberi administrator sekolah kemampuan menambahkan akses menu tertentu kepada setiap role pada sekolah aktif, karena pembagian fungsi role berbeda antar sekolah.

## Prinsip

- Permission bersifat tambahan di atas akses role bawaan yang sudah ada.
- Semua permission selalu terikat pada `school_id`; konfigurasi sekolah lain tidak boleh memengaruhi sekolah aktif.
- `super_admin`/`kepsek` tetap memiliki akses penuh dan menjadi satu-satunya pihak yang dapat mengubah permission.
- Pemeriksaan dilakukan pada sidebar dan route. Menyembunyikan menu saja tidak dianggap sebagai kontrol akses.
- Dashboard tetap tersedia untuk semua pengguna terautentikasi.
- Permission menggunakan key stabil yang tidak bergantung pada label atau URL.

## Model data

Tabel `school_role_permissions` menyimpan `school_id`, `role`, `permission`, `is_allowed`, dan timestamps. Kombinasi `school_id`, `role`, dan `permission` unik. Baris aktif berarti role memperoleh akses tambahan pada sekolah tersebut.

## Katalog permission

`MenuHelper` menjadi katalog tunggal menu. Setiap submenu memiliki `permission` dan `roles` bawaan. Parent ditampilkan jika setidaknya satu submenu dapat diakses. Menu sederhana dapat memiliki permission sendiri. Katalog yang sama dipakai halaman pengaturan untuk menghindari daftar permission yang berbeda antara sidebar dan admin.

## Otorisasi

`PermissionService::can()` mengizinkan akses jika pengguna adalah super admin/kepsek pada sekolah aktif, memiliki salah satu role bawaan menu, atau memiliki grant tambahan untuk salah satu role aktifnya. Middleware `permission:<key>,<default-role>...` memakai service ini untuk route GET maupun mutasi terkait menu yang sama.

Halaman `/settings/permissions` memakai middleware role admin, bukan permission yang dapat diberikan, sehingga admin tidak dapat mendelegasikan pengelolaan permission.

## UI dan penyimpanan

Halaman menampilkan pemilih role dan daftar menu yang dikelompokkan seperti sidebar. Akses bawaan ditandai dan terkunci; akses tambahan berupa checkbox. Simpan melakukan sinkronisasi atomik untuk role terpilih dan sekolah aktif: grant terpilih di-upsert, grant yang tidak lagi terpilih dihapus.

Role yang tersedia berasal dari role sistem dan role yang benar-benar digunakan pada sekolah aktif. `super_admin` tidak perlu dikonfigurasi karena selalu memiliki akses penuh.

## Validasi dan keamanan

- Role harus termasuk role yang tersedia pada sekolah aktif atau role sistem yang didukung.
- Permission harus berasal dari katalog `MenuHelper`.
- Controller tidak menerima `school_id` dari form; selalu memakai session `active_school_id`.
- Operasi disimpan dalam transaksi database.
- Permintaan tanpa sekolah aktif atau tanpa hak admin ditolak.

## Pengujian

Pengujian feature mencakup schema, grant tambahan, isolasi dua sekolah, penolakan route langsung, filter submenu, admin-only configuration, validasi permission, dan sinkronisasi grant tanpa menghapus akses bawaan.
