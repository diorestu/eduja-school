# Pembaruan Flow Tagihan Komite

Tanggal: 11 Oktober 2026
Status: disetujui dan diimplementasikan pada 11 Oktober 2026. Pengguna kemudian meminta integrasi provider WhatsApp dilewati; antrean pengingat tetap tersedia tanpa pengiriman eksternal.

## Tujuan dan cakupan

Bendahara membuat tagihan komite melalui menu Tagihan, menentukan aturan pembayaran dan target siswa, lalu sistem membuat invoice dan menjaga piutang sampai lunas. Periode bulanan berjalan otomatis selama tahun ajaran yang dipilih. Semua pembacaan, perubahan, pembayaran, dan pengingat harus mengikuti sekolah pemilik tagihan.

Gunakan halaman `/finance/billing`, route `finance.billing.*`, DataTable, modal pembuatan, invoice, dan transaksi yang sudah tersedia. Pertahankan alur tagihan/SPP lama dan format status database yang digunakan konsumen lain. Jangan mengganti keseluruhan halaman keuangan.

## Temuan pada kode saat ini

- `FinanceFoundationController::storeBilling` membuat konfigurasi dan satu invoice awal per siswa, tanpa transaksi database yang meliputi keseluruhan proses.
- Hari penagihan dan deadline bulanan diubah menjadi tanggal bulan berjalan lalu tidak disimpan sebagai aturan berulang.
- Filter target menggunakan prioritas `elseif`, sehingga jurusan, angkatan, rombel, dan siswa tidak diterapkan bersamaan.
- Jenis komite masih mencakup pencarian nama SPP dan fallback semua jenis pemasukan.
- `Student` belum memiliki kolom angkatan. Jurusan tersedia melalui rombel, bukan kolom `students.department_id`.
- Ringkasan target memakai perkiraan, termasuk angka 32 dan 64, sehingga tidak selalu sesuai invoice yang dibuat.
- Scheduler `spp:generate-billing` memakai tarif SPP lama; belum membaca konfigurasi Tagihan Komite.
- `NotificationService::send` hanya mencatat log. Belum ada konfigurasi provider WhatsApp pada `config/services.php`.
- `Invoice.status` menggunakan `Belum Lunas`, `Cicilan`, `Lunas`; `Invoice.total_amount` dan transaksi dipakai dalam pembayaran dan laporan.

## Form dan validasi

Urutan field: Jenis Tagihan, Tahun Ajaran, Nominal, Bisa Cicil, Minimal Cicilan, Ada Denda, Denda per Hari, Denda Maksimal, Jenis Penagihan, Tanggal Mulai, Deadline, Target.

- Jenis tagihan wajib dipilih dari pemasukan berkategori komite milik sekolah aktif. Nama tagihan dapat diturunkan dari jenis dan tahun ajaran; tidak perlu menjadi field wajib tambahan dalam flow baru.
- Tahun ajaran wajib berasal dari sekolah aktif atau data tahun ajaran bersama yang memang diizinkan aplikasi. Bulanan memerlukan batas awal/akhir tahun ajaran yang valid; jika belum diisi, tampilkan kesalahan yang mengarahkan pengguna melengkapi master tahun ajaran.
- Nominal harus positif. Gunakan perhitungan uang dalam satuan terkecil atau decimal yang terkendali; jangan mengandalkan operasi float untuk keputusan lunas.
- Minimal cicilan wajib positif dan tidak melebihi nominal saat cicilan diaktifkan. Jika cicilan dimatikan, kosongkan nilainya.
- Denda harian dan maksimum wajib positif saat denda diaktifkan. Maksimum harus sekurangnya denda harian. Jika dimatikan, kedua nilai menjadi nol.
- Sekali memerlukan tanggal mulai dan deadline lengkap dengan deadline tidak lebih awal dari mulai.
- Bulanan memerlukan hari 1–31 untuk mulai dan deadline; deadline tidak lebih awal dari hari mulai pada bulan yang sama. Hari yang tidak tersedia dipotong ke hari terakhir bulan. Tampilkan aturan penyesuaian ini di form.
- Awal pengulangan: bulan berjalan jika masuk rentang tahun ajaran, atau bulan pertama tahun ajaran jika belum dimulai. Tidak otomatis membuat utang untuk bulan sebelum konfigurasi dibuat.
- Kesalahan validasi mempertahankan input, membuka kembali modal, dan menampilkan pesan pada field terkait.

## Pemilihan target

Satu Sekolah memilih seluruh siswa aktif di sekolah tersebut. Pilihan menyediakan Semua atau satu pilihan pada setiap filter Jurusan, Angkatan, Rombel, dan Siswa. Pilihan satu per filter mengikuti bentuk dropdown pada flow pengguna; multi-select tidak ditambahkan.

- Jurusan tampil hanya ketika `School.level` menunjukkan SMK. Ambil jurusan melalui rombel pada tahun ajaran terpilih.
- Rombel mengikuti sekolah, tahun ajaran, dan jurusan terpilih. Siswa mengikuti seluruh filter sebelumnya.
- Filter diterapkan bersama sebagai irisan, bukan prioritas salah satu filter. Jurusan dan rombel harus cocok dalam relasi pendaftaran yang sama.
- Tambahkan data angkatan siswa sebagai tahun masuk nullable (`generation`) beserta input pada pengelolaan/import siswa yang relevan. Jangan menyimpulkan angkatan dari `created_at` atau tingkat kelas. Data lama tetap kosong sampai dilengkapi; pilihan Semua tetap menyertakannya, sedangkan angkatan tertentu hanya mengambil siswa dengan data angkatan tersebut.
- Mengubah filter induk mereset pilihan turunan yang tidak lagi valid. Backend menolak ID milik sekolah lain, kombinasi tidak cocok, dan target tanpa siswa.
- Ringkasan dan jumlah siswa harus memakai hasil query yang sama dengan generator. Hilangkan angka perkiraan dan fallback jumlah fiktif.
- Target diselesaikan kembali setiap periode: siswa aktif baru yang memenuhi filter masuk periode berikutnya; siswa yang sudah tidak aktif tidak diberi tagihan baru. Piutang lama tetap ada.

## Model data dan status

Pisahkan konfigurasi (`billing_items`), periode (`billing_periods` baru), dan invoice siswa (`invoices`).

- Konfigurasi menyimpan sekolah, tahun ajaran, jenis komite, aturan pembayaran, filter target, hari mulai/deadline bulanan, dan status aktif/nonaktif konfigurasi. Field tanggal lama dipertahankan untuk kompatibilitas.
- Periode menyimpan `billing_item_id`, sekolah, kunci periode, tanggal mulai, deadline, dan status `active`/`deactive`. Sekali memiliki satu kunci; bulanan memakai `YYYY-MM`. Kombinasi konfigurasi dan kunci periode unik.
- Invoice baru memiliki hubungan eksplisit ke konfigurasi dan periode; kombinasi periode dan siswa unik. Invoice lama tetap valid dengan hubungan nullable.
- Snapshot nominal, cicilan, dan aturan denda berada pada invoice, sehingga perubahan konfigurasi tidak mengubah kewajiban yang sudah diterbitkan.
- Periode Active sampai akhir tanggal deadline; mulai hari berikutnya menjadi Deactive. Semua keputusan tanggal memakai zona waktu aplikasi/sekolah yang konsisten.
- Konfigurasi bulanan tetap aktif setelah periode terdahulu menjadi Deactive. Dinonaktifkan manual berarti berhenti membuat periode baru; invoice dan pengingat utang lama tetap berlaku.
- Paid berarti sisa kewajiban nol; selain itu Unpaid. Database tetap menggunakan status lama: `Lunas` dipresentasikan Paid, `Belum Lunas` dan `Cicilan` sebagai Unpaid dengan rincian pembayaran sebagian.
- Tidak ada penghapusan invoice atau penghapusan piutang saat periode berakhir. Menonaktifkan bukan membatalkan utang.

## Generator dan penjadwalan

Pindahkan logika pembuatan dari controller ke `BillingService`. Simpan konfigurasi dan pembuatan awal dalam transaksi database; kegagalan tidak meninggalkan konfigurasi atau sebagian invoice.

- Sekali menerbitkan satu periode dan invoice saat disimpan, dengan tanggal mulai sebagai awal masa penagihan.
- Bulanan hanya menerbitkan periode yang tanggal mulainya sudah tiba. Konfigurasi yang dibuat sebelum hari mulai belum menerbitkan invoice; UI menjelaskan tanggal penerbitannya.
- Command komite berjalan harian untuk membuat periode yang sudah waktunya, menutup periode lewat deadline, menghitung denda, dan menyiapkan pengingat.
- Setelah scheduler sempat berhenti, pemrosesan mengejar periode yang terlewat sejak awal pengulangan konfigurasi sampai hari berjalan, dalam batas tahun ajaran.
- Constraint unik, transaksi, dan penguncian memastikan pemrosesan berulang atau bersamaan tidak menggandakan invoice.
- Command komite tidak menggantikan generator tarif SPP lama. Verifikasi bahwa konfigurasi komite tidak diproses dua kali oleh kedua generator.
- Penerbitan masa depan sebelum hari mulai tidak dilakukan. H-7 yang jatuh sebelum invoice terbit tidak dikirim karena belum ada invoice; beri keterangan ini jika jarak mulai-deadline kurang dari tujuh hari.

## Pembayaran, denda, dan piutang

- Validasi pembayaran komite memakai snapshot invoice: tanpa cicilan wajib melunasi sisa; dengan cicilan nominal minimal berlaku kecuali sisa lebih kecil daripada minimal, ketika seluruh sisa boleh dilunasi.
- Denda mulai hari setelah deadline: `min(jumlah hari terlambat × denda harian, denda maksimum)`, hanya selama invoice belum lunas. Maksimum berlaku per invoice, bukan per pembayaran atau seluruh siswa.
- Pokok dan denda tampil terpisah; total kewajiban adalah pokok ditambah denda yang telah dihitung. Update `total_amount` secara konsisten dengan rincian, sehingga pembayaran dan laporan membaca nilai sama.
- Pembayaran langsung dan pembayaran yang disetujui melalui alur approval harus memakai perhitungan saldo dan validasi yang sama. Pending/rejected tidak mengurangi piutang.
- Hitung denda dan saldo terbaru serta simpan pembayaran dalam transaksi dengan penguncian invoice. Tolak pembayaran berlebih dan pembayaran ke invoice sekolah lain.
- Ketika lunas, hentikan pertumbuhan denda dan pengingat. Piutang tetap bisa dilunasi setelah periode Deactive atau tahun ajaran berakhir.
- Perubahan nominal/aturan/target konfigurasi berlaku untuk periode baru. Hubungan konfigurasi tidak dicari berdasarkan kesamaan nama untuk invoice baru. Invoice lama tidak dihubungkan otomatis hanya karena nama sama.

## Pengingat WhatsApp

Simpan pengingat sebagai record persisten dengan sekolah, siswa, tujuan, jenis pemicu, tanggal, status pengiriman, percobaan, dan identitas deduplikasi. Worker membaca kembali saldo sebelum mengirim, sehingga invoice yang sudah lunas tidak dikirimkan.

- H-7 dan hari deadline untuk invoice terbit yang belum lunas. Gabungkan invoice siswa yang jatuh pada pemicu sama beserta piutang komite sebelumnya, dengan rincian pokok, denda, pembayaran, dan sisa.
- Jika tidak ada tagihan baru, pengingat piutang tetap berjalan setiap bulan pada hari deadline asal yang disesuaikan dengan panjang bulan. Gabungkan pemicu yang sama agar tidak mengirim pesan ganda.
- Hanya nomor orang tua/wali yang tercatat dan memiliki hubungan sah ke siswa yang boleh menjadi penerima. Jangan memakai nomor siswa atau nomor contoh sebagai fallback. Tanpa nomor wali, catat sebagai tidak bisa dikirim.
- Status terkirim hanya diberikan setelah provider mengonfirmasi penerimaan. Log lokal bukan bukti terkirim maupun bukti pesan diterima pengguna.
- Retry harus mempertahankan identitas pengiriman. Timeout yang hasilnya tidak diketahui dicatat untuk rekonsiliasi; jangan menjanjikan tepat sekali tanpa dukungan idempotensi provider.
- Provider WhatsApp belum ditentukan pengguna. Tahap aplikasi dapat menyiapkan scheduler, antrean, adapter, dan status belum terkonfigurasi. Pengiriman nyata tetap memerlukan pilihan provider, konfigurasi aman, dan pengujian pada nomor yang diotorisasi; ini merupakan batas penyelesaian end-to-end, bukan fitur yang boleh dinyatakan sudah terkirim.
- UI harus menjelaskan kesiapan provider secara jujur, menggantikan klaim bahwa notifikasi pasti dikirim ketika hanya adapter log tersedia.

## Komponen yang terdampak

- Migration tambahan untuk aturan pengulangan, periode, hubungan dan snapshot invoice, angkatan, dan pengingat.
- `BillingItem`, `Invoice`, model periode dan pengingat, serta `Student` dan pengelolaan data angkatannya.
- `BillingService` untuk query target dan penerbitan, service perhitungan pembayaran/denda bersama, service antrean pengingat.
- `FinanceFoundationController` dan `resources/views/pages/keuangan/billing.blade.php` untuk validasi, data dropdown, ringkasan, dan tampilan status/piutang.
- `SppTransactionController`, jalur posting approval pembayaran, dan pembaca laporan yang relevan untuk keselarasan nominal dan status.
- `routes/console.php` atau command terpisah untuk scheduler komite. Adapter WhatsApp mempertahankan alur notifikasi lain.

## Verifikasi dan penerimaan

Pest feature/service tests mencakup isolasi sekolah dan tahun ajaran, kategori komite, kombinasi target, angkatan kosong, SMK/non-SMK, target kosong, jumlah siswa nyata, tanggal 28–31 dan tahun kabisat, penerbitan awal/berulang, scheduler tertinggal, deduplikasi, rollback, Deactive setelah deadline, piutang tetap dapat dibayar, aturan cicilan, batas denda, pembayaran bersamaan, approval, Paid/Unpaid, dan snapshot ketika konfigurasi berubah.

Tes pengingat memakai adapter palsu untuk H-7/deadline/piutang bulanan, wali tanpa nomor, siswa lunas sebelum worker berjalan, kegagalan provider, serta pengiriman berulang. Tes ini tidak membuktikan pengiriman WhatsApp nyata.

Validasi UI meliputi modal, input kondisional, dropdown berantai, error dan retry, ringkasan, DataTable, keyboard, dan layar kecil. Jalankan tes keuangan lama, tes yang baru, build aset, kompilasi Blade, dan pemeriksaan diff. Catat terpisah hasil lokal, scheduler deployment, serta pengiriman WhatsApp nyata.

## Peninjauan spesifikasi

Rancangan percakapan dan spesifikasi telah disetujui melalui instruksi melanjutkan implementasi. Status Deactive milik periode, angkatan memakai data tahun masuk yang sah, dan pengiriman WhatsApp nyata sengaja ditunda sesuai instruksi lanjutan pengguna. Implementasi dijalankan dalam sesi yang sama pada branch `codex/tagihan-komite-flow`; panduan operasional ada di `docs/committee-billing-operations.md`.
