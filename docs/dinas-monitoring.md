# Monitoring Dinas Pendidikan

Dinas masuk ke `/dinas` dan melihat agregat semua sekolah aktif. Pilih sekolah dan klik **Tampilkan** untuk memfilter ringkasan jumlah sekolah, siswa, guru, tendik, dan grafik kehadiran enam bulan. **Kembali ke semua sekolah** menghapus filter. Filter monitoring tidak mengganti konteks sekolah operasional.

## Izin keuangan

Kepala sekolah membuka **Izin Keuangan Dinas** (`/settings/dinas-access`) pada sekolah aktif. Centang **Izinkan Dinas Pendidikan melihat keuangan sekolah ini**, lalu **Simpan izin**. Hilangkan centang dan simpan untuk mencabut izin.

- Pengaturan awal setiap sekolah: tidak diizinkan.
- Hanya keanggotaan kepala sekolah aktif yang dapat mengubahnya, termasuk alias kepala sekolah `super_admin` yang sudah digunakan sistem.
- Izin berlaku untuk peran Dinas Pendidikan dan hanya memberikan akses baca pada ringkasan keuangan BOS di monitoring.
- Agregat BOS hanya menjumlahkan belanja BOS yang disetujui dari sekolah aktif yang memberi izin. Belanja pending/ditolak dan dana non-BOS dikecualikan.
- Label cakupan menunjukkan berapa sekolah yang memberi izin; angka agregat bukan total semua sekolah bila hanya sebagian memberi izin.
- Tanpa izin, seri nominal BOS tidak dikirim ke halaman. Pencabutan berlaku pada permintaan berikutnya.

## Profil Dinas

Gunakan `/profile` untuk memperbarui nama, email, dan password sendiri. Tidak ada penghapusan akun atau pengelolaan akun orang lain pada alur ini.

## Instalasi

Migrasi tambahan: `2026_10_26_000001_add_dinas_finance_consent_to_schools.php`, menambahkan boolean `schools.dinas_finance_allowed` dengan nilai awal false. Migrasi ini sudah diterapkan pada database lokal saat implementasi. Jalankan migrasi pada lingkungan deployment sebelum menggunakan fitur.

## Verifikasi

- Suite awal: 247 tes lulus. Suite setelah perubahan: 263 tes lulus (1773 assertions).
- 16 tes baru mencakup izin, tenant/role, pencabutan, BOS, filter sekolah, bulan kalender, keadaan kosong, dan profil sendiri.
- Build Vite berhasil; ada peringatan bundle JavaScript lebih dari 500 kB.
- Browser QA menggunakan Chromium/Playwright pada SQLite fixture terpisah, bukan data operasional: desktop 1440×1100 dan mobile 390×844, filter/reset, grant/revoke, data BOS tersembunyi, console tanpa error, dan overflow horizontal.
- Screenshot QA tersimpan di `/tmp/eduja-dinas-qa.V8Nsus`; data pada screenshot adalah fixture QA.
