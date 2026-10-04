# Rekening Sekolah: DataTable dan modal

Tanggal: 4 Oktober 2026. Scope: halaman bendahara `/finance/accounts`, komponen tabel reusable, dan modal tambah rekening. Pemeriksaan memakai database SQLite uji di `/tmp/eduja-accounts-qa`, bukan data operasional.

## Hasil dan batas

- Daftar rekening memakai `x-common.data-table`. Pencarian, sort dan pagination berjalan di browser; data tetap dibatasi controller ke sekolah aktif.
- Data yang dikirim hanya nama, jenis, bank, nomor rekening, saldo dan status. Saldo diurutkan sebagai angka, bukan teks rupiah. Nomor rekening tetap string agar nol awal tidak hilang.
- Native dialog mengisolasi fokus dan mendukung Tutup, Batal, Escape. Kesalahan validasi membuka kembali dialog dan mempertahankan input.
- Komponen menerima skema kolom berbeda. Contoh pemakaian dan batas client-side ada di `docs/components/data-table.md`; preview delapan state ada di `docs/components/data-table.preview.blade.php`.
- Avatar, ekspor CSV dan aksi generik dari perubahan bersamaan dipertahankan sebagai opsi komponen. Halaman rekening tidak menampilkannya. Pemakai yang mengaktifkan aksi wajib memasang handler.
- Halaman lain belum dikonversi ke komponen ini.

## Arah visual

Hallmark component-scope: tidak mengganti macrostructure, navigasi, footer, font atau identitas aplikasi. Inter dan token teal EDUJA dipertahankan. ENERGY 1 / RHYTHM 2 / MOTION 1. Satu permukaan tabel memudahkan perbandingan saldo; form berada dalam modal agar daftar tetap menjadi fokus. Warna aksen untuk aksi utama dan fokus. Tidak membuat kartu ringkasan, ilustrasi, ikon dekoratif, atau identitas orang.

Self-critique: P5 H4 E4 S5 R5 V4. Ini pemeriksaan komponen dan alur yang diubah, bukan klaim 69/69 untuk seluruh EDUJA.

## Bukti interaksi

1. Data uji 26 rekening: pagination berikutnya menampilkan rekening 11-20; batas sebelumnya nonaktif pada halaman pertama.
2. Jumlah baris bisa diubah menjadi 25. Search dan sort mengembalikan halaman ke awal.
3. Pencarian `nonaktif` menampilkan 8 rekening; pencarian tanpa hasil menampilkan petunjuk dan tombol Hapus pencarian.
4. Sort saldo ascending/descending memperbarui aria-sort; pengurutan numerik juga diuji lewat Node.
5. Tombol + Rekening Sekolah membuka dialog; fokus pertama ke Nama.
6. Jenis Tunai menyembunyikan dan menonaktifkan field bank. Bank menampilkan Nama Bank dan Nomor Rekening.
7. Pengiriman nama berisi spasi mencapai validasi server: dialog kembali terbuka, pesan dekat Nama, fokus ke Nama, data bank dan saldo dipertahankan.
8. Nama diperbaiki lalu simpan: rekening Bank QA muncul di tabel dengan nomor `000123456789` dan saldo `Rp 123.456,78`, status sukses muncul, dialog tertutup.
9. Batal dan Escape menutup dialog dan mengembalikan fokus ke tombol tambah. Tutup juga berfungsi.
10. Tabel dan modal diperiksa pada 320, 375, 414, 768 px. Lebar dialog 288, 343, 382, 576 px; scrollWidth halaman sama dengan viewport. Tabel memiliki scroll internal agar semua kolom tetap bisa dibaca.
11. Dialog Bank dan tabel diperiksa dalam tema gelap. Mode terang kembali dipulihkan setelah QA.
12. Preview reusable benar-benar merender status Loading serta Error dari prop komponen. Disabled pagination teramati pada satu halaman; preview Success memakai data uji yang ditandai.
13. Console yang tertangkap tidak memiliki error/warning JavaScript.
14. Ring fokus native/komponen tidak dianimasikan; field dan kontrol sort/pagination minimal 44 px. Motion baru tidak ditambahkan; reduced-motion mematikan transisi tabel.

Screenshot berada di folder `eduja-accounts` pada visualizations chat; seluruh rekening adalah fixture uji.

## Antislop delivery gate

Status berlaku pada alur rekening dan style operasional bertoken yang dipakai halaman ini. Varian opsional yang tidak dirender pada halaman rekening tidak diaudit sebagai alur baru.

| Aturan | Status | Bukti |
|---|---|---|
| R-01 | PASS | Token paper/ink/teal yang sudah ada dipakai. |
| R-02 | PASS | Copy baru tidak memakai em dash. |
| R-03 | PASS | Empat lebar layar diperiksa; halaman tidak overflow, kontrol 44 px. |
| R-04 | PASS | Ikon pencarian/sort/pagination memiliki fungsi; tanpa emoji baru. |
| R-05 | PASS | Struktur dibangun dari daftar rekening dan aksi tambah. |
| R-06 | PASS | Inter dipertahankan untuk konsistensi aplikasi dan angka tabel. |
| R-07 | PASS | Tidak menambah pola background. |
| R-08 | PASS | Panah menunjukkan arah sort dan pagination, bukan dekorasi CTA. |
| R-09 | PASS | Status rekening ditampilkan sebagai teks; avatar/badge tambahan dimatikan. |
| R-10 | PASS | Tidak menambah blur/glass. |
| R-11 | PASS | Radius input/modal mengikuti skala yang sudah ada. |
| R-12 | PASS | Permukaan tabel operasional tidak memakai shadow dekoratif. |
| R-13 | PASS | Tidak memakai glow dekoratif. |
| R-14 | PASS | Tidak menambah feature card. |
| R-15 | PASS | Aksi tambah, simpan, tutup, batal dan reset pencarian konkret. |
| R-16 | PASS | Copy spesifik rekening dan sekolah; tanpa buzzword. |
| R-17 | PASS | Jumlah baris dan saldo berasal dari data controller. |
| R-18 | PASS | Tidak membuat testimonial atau avatar orang baru. |
| R-19 | PASS | Motion baru tidak ditambahkan; reduced-motion tersedia. |
| R-20 | PASS | Identitas EDUJA dipertahankan, tabel berisi metadata rekening sekolah. |
| R-21 | PASS | Tema aplikasi dipertahankan dan digunakan pada QA. |
| R-22 | PASS | Tidak membuat ilustrasi. |
| R-23 | PASS | Tidak membuat aset atau navigasi baru. |
| R-24 | PASS | Route finance yang sudah ada tetap dipakai. |
| R-25 | PASS | Rasio token: light ink/paper 15.91, muted/paper 7.81; dark ink/paper 15.00, muted/paper 9.36; teks CTA light 6.95, dark 10.00. |
| R-26 | PASS | Interaksi 1-9 dan 12 dijalankan; aksi generik yang belum ada handler tidak ditampilkan pada rekening. |
| R-27 | PASS | Empty/search tanpa hasil, loading, error dan sukses diuji. |
| R-28 | PASS | Tidak menambah FAQ. |
| R-29 | PASS | Palet netral dan satu aksen teal dipakai. |
| R-30 | PASS | Mengikuti sistem EDUJA, tanpa membangun ulang desain produk lain. |
| R-31 | PASS | Alasan tabel utama, modal, warna, font dan kontrol ditulis di arah visual. |
| R-32 | PASS | Label search unik per instance, aria-sort, dialog native dan pengembalian fokus diuji. |
| R-33 | PASS | Implementasi ditulis langsung pada Blade/JS/CSS, bukan script penggantian sumber. |
| R-34 | PASS | Mode terang/gelap diperiksa lewat browser. |
| R-35 | PASS | Build dan tes dijalankan; klik kontrol tercatat di bukti interaksi. |
| R-36 | PASS | Tidak menambah klaim keamanan/kecepatan/kepatuhan. |
| R-37 | PASS | Existing tokens.css dan workspace.css menjadi arah, bukan tema baru. |
| R-38 | PASS | Preview dan screenshot diberi konteks data uji; halaman operasional memakai data nyata controller. |
| Liveliness | PASS | Fokus pada daftar rekening, pengelompokan toolbar dan aksen pada tindakan utama. |
| C-1 sampai C-5 | PASS | Pilihan visual punya alasan, kontrol diuji, struktur sesuai tugas, responsive dan state diperiksa. |

## Verifikasi

- `php artisan test --compact`: 123 tests, 590 assertions, semuanya lolos.
- `node --test tests/js/data-table.test.mjs`: 7 tests, semuanya lolos, termasuk dua tes tambahan dari perubahan bersamaan.
- `npm run build`: berhasil. Warning ukuran chunk aplikasi >500 kB tetap ada; tidak mengubah pemisahan bundle di luar scope ini.
- `git diff --check`: berhasil.
- Tidak mengubah controller, service finance, role atau query sekolah aktif.
