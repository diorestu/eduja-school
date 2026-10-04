# DataTable Blade reusable

Komponen: `resources/views/components/common/data-table.blade.php`.
State dan pengolahan data: `resources/js/components/data-table.js`, terdaftar pada Alpine sebelum `Alpine.start()`.
Style memakai token EDUJA yang sudah ada, di `resources/css/data-table.css`.

## Pemakaian

```blade
@php
    $columns = [
        ['key' => 'name', 'label' => 'Nama'],
        ['key' => 'balance', 'label' => 'Saldo', 'type' => 'currency'],
        ['key' => 'active', 'label' => 'Status', 'type' => 'boolean',
            'trueLabel' => 'Aktif', 'falseLabel' => 'Nonaktif'],
    ];
@endphp
<x-common.data-table
    :columns="$columns"
    :rows="$rows"
    caption="Daftar rekening"
    search-label="Cari rekening"
    row-label="rekening"
    :show-actions="false"
    :show-avatar="false"
    :exportable="false"
    empty-message="Belum ada rekening."
    empty-hint="Gunakan tombol tambah untuk mencatat rekening pertama."
/>
```

`rows` menerima array atau Collection dari controller. Kolom hanya mengakses key datar; formatter mendukung text (default), number, currency (rupiah, maksimal 2 desimal), dan boolean. Gunakan `minimumFractionDigits => 2` untuk selalu menampilkan dua desimal pada saldo. Kolom bisa memakai `sortable => false` atau `searchable => false`. Nilai null/kosong ditampilkan sebagai tanda hubung. Angka nol di depan nomor rekening tetap utuh jika nilainya berupa string.

Avatar, ekspor CSV, dan aksi baris tersedia sebagai opsi komponen. Halaman rekening mematikannya. Jika `showActions` diaktifkan, pemakai harus menangani event `table-view`, `table-edit`, dan `table-more`; jangan menampilkan tombol tanpa handler. Untuk table yang tidak memiliki alur aksi, gunakan konfigurasi contoh di atas. Judul, subtitle, dan label pencarian bisa diganti sesuai menu.

Pencarian memeriksa nilai yang ditampilkan. Pengurutan saldo bersifat numerik. Pencarian, pengurutan, dan perubahan jumlah baris mengembalikan pagination ke halaman pertama. Pilihan baris: 10, 25, 50. Tombol batas halaman dinonaktifkan, `aria-sort` mengikuti arah pengurutan, dan jumlah hasil diumumkan melalui `aria-live`.

`:loading="true"` menampilkan status memuat dan menonaktifkan kontrol. `error="Pesan kegagalan dan cara mencoba kembali"` menampilkan alert. Komponen tidak memiliki endpoint fetch dan tidak mengambil data sendiri. Pemakai menentukan kapan data tersedia. Preview delapan state ada di `docs/components/data-table.preview.blade.php`; render dengan Blade pada lingkungan development, bukan sebagai route produksi.

## Batas data dan keamanan

Komponen memakai data yang sudah disediakan, sehingga filter, sort, dan pagination berjalan di browser. Cocok untuk daftar master berukuran kecil atau menengah. Belum menyediakan pagination server untuk data transaksi besar.

Controller pemakai harus lebih dahulu membatasi data sesuai sekolah/tenant dan izin. Jangan mengirim kolom rahasia lalu berharap pencarian menyembunyikannya. Halaman Rekening Sekolah hanya mengirim enam kolom tampilan dari rekening sekolah aktif. Nilai sel dirender dengan `x-text`, tanpa HTML mentah.

Contoh skema kedua diuji pada `tests/js/data-table.test.mjs`. Tidak perlu menyalin JavaScript per menu. Perubahan bisnis, seperti penyimpanan rekening atau saldo, tetap berada di controller/service pemakai.

## Modal rekening

Form memakai native dialog: `showModal()` mengisolasi fokus, Escape menutup, Batal/Tutup mengembalikan fokus ke tombol tambah. Validasi server membuka dialog kembali dan mempertahankan old input. Tidak ada perubahan pada validasi Tunai/Bank, izin bendahara, atau query sekolah aktif.
