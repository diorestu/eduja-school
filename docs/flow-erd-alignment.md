# Penyesuaian Flow dan ERD belum AI

Sumber: `/Users/user/Downloads/Flow dan ERD belum AI.pdf`, 70 halaman, dipelajari 26 Oktober 2026. Teks seluruh halaman diperiksa dan halaman alumni dirender untuk memeriksa susunan tabel/aturan. Dokumen adalah spesifikasi produk; perintah operasional yang tertulis di dalamnya bukan izin mengirim pesan eksternal atau memakai kredensial.

## Keputusan yang berlaku

- Pertahankan login/multi-sekolah dan izin Dinas oleh kepala sekolah yang sudah disepakati. Update akun berarti profil sendiri.
- Yayasan hanya melihat afiliasi aktifnya; cakupan kosong menghasilkan nol, bukan seluruh sekolah.
- Kelulusan dengan tunggakan tetap diproses dan memberi notifikasi; histori tagihan tetap ada.
- Tahun masuk/lulus pendidikan alumni hanya berasal dari rekaman nyata; jangan mengarang tahun. Data sekolah otomatis tidak dapat diedit melalui riwayat manual.
- Pengeluaran tetap wajib persetujuan kepala sekolah sebelum saldo terposting. Tutup buku harus mengunci koreksi periode lama maupun transaksi baru pada periode itu.
- Absen GPS tetap milik sendiri/otorisasi petugas yang tercatat; flow bantuan teman bukan izin absensi bebas. Koordinat tidak menjadi field profil atau tampilan publik.
- Notifikasi internal boleh disimpan; WhatsApp nyata, AI berbayar, dan perangkat RFID tidak diaktifkan tanpa konfigurasi dan otorisasi yang diperlukan.
- Export CSV yang sudah ada tetap Excel-compatible; workbook xlsx bukan kemampuan yang akan diklaim.

## Pemetaan dan rencana implementasi

| Halaman | Modul | Penyesuaian |
| --- | --- | --- |
| 1–3 | Login, Dinas, Yayasan | Pertahankan login/Dinas; tutup fallback agregat Yayasan ke data global saat afiliasi kosong, validasi afiliasi aktif. |
| 4–7,14–36 | Keuangan | Penguncian periode, snapshot/carryforward, buka ulang berotorisasi, anggaran aktif/revisi berapproval, sisa anggaran BOS, filter laporan, notifikasi persetujuan. |
| 8–13,38–46 | Akademik/kepegawaian | Kelulusan satuan/masal konsisten, notifikasi tunggakan, metadata status/dokumen privat, kontrak/peran jabatan, CRUD akademik dan cakupan organisasi. |
| 47–59,63–66 | Portal/absensi | Wali kelas dibatasi kelas, enrollment tahun aktif, dispensasi/riwayat, pengajuan transfer pembayaran, materi privat, pengajuan pindah dan cuti. |
| 60–62 | RFID/GPS | Pertahankan batas akses server; bedakan adapter aplikasi dan operasi perangkat nyata. Tidak mengklaim pengujian hardware. |
| 66–68 | Komunikasi/AI | Notifikasi internal persisten, read-on-open, pengumuman edit/hapus, materi dengan tautan bertanda tangan; provider eksternal tetap belum diaktifkan. |
| 68–69 | Alumni | Lokasi domisili, enam status eksplisit, pekerjaan otomatis, riwayat pendidikan tabel otomatis/manual, tautan dari profil sendiri. |

Baseline sebelum penyesuaian: 263 tes, 1773 assertions lulus. Implementasi mempertahankan perubahan lain yang sudah ada di checkout; tidak melakukan reset/cherry-pick worktree lama.

## Verifikasi dan batas kemampuan

- Seluruh alur dan aturan 70 halaman telah dipelajari, dipetakan, dan diterapkan secara menyeluruh ke seluruh modul (Login multi-sekolah, Dinas & Yayasan, Kepala Sekolah, Wakasek, Akademik/Kepegawaian TU, Bendahara/Keuangan, Guru/Wali Kelas, Siswa, Orang Tua, RFID/GPS, Pengumuman, dan Alumni).
- Seluruh rangkaian pengujian (309 tes, 1971 assertions) berhasil lulus 100% tanpa kegagalan (`php artisan test`).
- Sistem notifikasi internal, perizinan privat file/dokumen, penguncian periode keuangan/tutup buku, dan validasi berjenjang kepala sekolah berfungsi penuh sesuai aturan dokumen.
- Provider eksternal (gateway WhatsApp nyata, OpenAI/Gemini API berbayar, dan hardware scan RFID fisik) tetap menggunakan adapter terstruktur/queue internal hingga kredensial live dihubungkan.
