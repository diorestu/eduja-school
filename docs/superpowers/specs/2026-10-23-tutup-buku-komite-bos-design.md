# Spesifikasi Tutup Buku Komite dan BOS

Tanggal: 23 Oktober 2026.
Status: rancangan tertulis untuk ditinjau; implementasi belum dimulai.

## 1. Tujuan dan keputusan percakapan

Bendahara dapat memeriksa dan menutup buku Komite bulanan/tahunan serta BOS per jenis dan per bulan. Penutupan tahunan BOS dilakukan setelah seluruh bulan pada tahun BOS tersebut selesai. Ini mengikuti rekomendasi terakhir yang dilanjutkan pengguna dengan “ok lanjutkan”.

Keberhasilan berarti saldo akhir periode dapat dibuktikan, saldo awal berikutnya tidak menggandakan dana, seluruh jalur tulis menghormati periode tertutup, dan pengguna memperoleh alasan konkret bila penutupan gagal. WhatsApp tetap ditunda. Notifikasi menggunakan aplikasi EDUJA.

Pembukaan kembali dilakukan kepala sekolah atau superadmin dengan keanggotaan aktif sekolah, alasan wajib, dan jejak audit. Bendahara tetap dapat melakukan pemeriksaan dan penutupan dengan izin `finance.closing` pada sekolah aktif.

## 2. Kondisi kode yang sudah diperiksa

- `FinanceClosingService::validate()` memeriksa beberapa status pending, rekening negatif, dan pajak belum disetor pada seluruh sekolah tanpa pemilahan periode/sumber.
- Pemeriksaan jurnal gagal, transfer belum selesai, dan rekonsiliasi masih bernilai tetap nol. Pemeriksaan tersebut belum menjadi bukti kesiapan tutup buku.
- `close()` menyimpan saldo rekening saat ini dan ringkasan seluruh waktu. Belum ada penguncian jalur transaksi, saldo historis dompet, pembukaan kembali, atau saldo awal periode berikutnya.
- `FinanceLedgerService` menyusun laporan dari transaksi sumber, belum memiliki jurnal persisten dan riwayat transfer antar rekening.
- `transferAccount()` langsung mengubah dua saldo. `transfer_date` dan catatan belum disimpan dalam tabel transfer.
- Master dompet menerima perubahan `nominal` langsung. Riwayat mutasi dompet lama tidak selalu dapat direkonstruksi.
- Pengeluaran memiliki `posted_at`; beberapa transaksi lama hanya memiliki status approved. Status tersebut sendiri tidak membuktikan apakah saldo pernah berubah.
- `BudgetYear` memiliki tanggal awal/akhir yang dapat dipakai untuk tahun anggaran Komite.

## 3. Buku, sumber dana, dan periode

Gunakan buku keuangan eksplisit milik sekolah (`finance_books`) untuk memisahkan buku Komite dan masing-masing jenis BOS. Identitas buku disimpan sebagai foreign key, bukan disimpulkan terus-menerus dari nama dompet/rekening yang dapat berubah.

- Usulan pemetaan untuk ditinjau: buku Komite mencakup penerimaan/pengeluaran Komite serta Hibah dan Lainnya dalam pembukuan umum sekolah. Hibah/Lainnya tetap menjadi subkategori terpisah dan nilainya dibedakan di snapshot. Rekening bank bersama tidak membuat Hibah/Lainnya berubah kategori. Pemetaan ini menjaga seluruh mutasi rekening umum dapat direkonsiliasi, termasuk transaksi yang sudah ada.
- BOS memiliki buku terpisah per jenis/sumber BOS. Tahun BOS menjadi dimensi transaksi, tidak selalu sama dengan tahun tanggal penerimaan. Pilihan sumber mengikuti master dan data sekolah yang digunakan oleh form pemasukan BOS.
- Pengeluaran BOS wajib menyimpan jenis dan tahun BOS secara eksplisit. Data lama dengan jenis/tahun yang ambigu menghalangi penutupan buku terkait sampai dipetakan oleh pengguna berwenang.
- Bulanan: batas tanggal kalender pada zona waktu sekolah/aplikasi yang ditetapkan untuk pembukuan. Simpan batas tanggal transaksi secara eksplisit; waktu audit tetap memakai timestamp.
- Tahunan Komite: pilih record `BudgetYear`, pakai `start_date` sampai `end_date`, termasuk bila melintasi tahun kalender. Tanggal yang belum lengkap harus dilengkapi sebelum tutup buku.
- Tahunan BOS: satu jenis dan tahun BOS, setelah seluruh bulan dalam tahun tersebut ditutup. Bulan tanpa transaksi tetap perlu diperiksa dan ditutup dengan saldo yang dibawa.
- Periode aktif awal buku dicatat saat penerapan. Bulan sebelum tanggal mulai tidak otomatis dianggap kosong atau sudah ditutup.
- Penutupan bulan BOS mengunci bulan yang dipilih untuk jenis/tahun BOS itu; penutupan tahunan mengunci seluruh tahun BOS tersebut.

Rekening fisik dan dompet adalah dua pandangan dana. Jumlah dompet tidak ditambahkan ke saldo kas/bank sebagai uang tambahan. Satu rekening fisik yang dipakai beberapa sumber mempunyai sub-saldo buku; total sub-saldo direkonsiliasi terhadap rekening fisik.

## 4. Pencatatan jurnal dan mutasi

Tambahkan pencatatan persisten yang cukup untuk pemeriksaan dan saldo berdasarkan tanggal:

- `finance_journals`: sekolah, buku, tanggal efektif, sumber/model/id/versi, status `pending`, `posted`, atau `failed`, kode kegagalan, waktu posting, dan pelaku. Kunci unik sumber-versi-aksi mencegah pencatatan ulang.
- `finance_journal_lines`: debit/kredit desimal dan tujuan akun. Jurnal penerimaan/pengeluaran memakai pasangan kas/bank dan akun lawan pendapatan/beban; saldo awal memakai akun lawan saldo pembukaan. Transfer mempunyai dua sisi yang dapat ditelusuri.
- `finance_wallet_movements`: jurnal sumber, dompet, nominal bertanda, tanggal efektif, dan buku. Mutasi alokasi bersifat pencatatan pembagian dana, tidak dihitung kembali sebagai pendapatan/pengeluaran kas.
- `finance_transfers`: asal/tujuan, buku asal/tujuan, nominal, tanggal, status, pelaku, dan referensi jurnal. Kedua sisi transfer internal diposting atomik. Transfer antarbuku memakai referensi berpasangan dan akun penghubung yang dapat direkonsiliasi.
- `finance_reconciliations`: rekening fisik, tanggal batas, saldo sistem, saldo pembanding/hasil hitung kas, selisih, referensi bukti, pelaku, dan waktu konfirmasi. Rekonsiliasi mengikat versi/fingerprint data yang diperiksa.

Saldo dihitung memakai integer satuan terkecil/desimal dua digit melalui pola `BillingPaymentService::cents()` dan `decimal()`. Hindari float untuk validasi dan posting.

Seluruh jalur yang mengubah saldo wajib memakai satu mekanisme posting atau adapter yang menjamin jurnal, mutasi dompet, dan perubahan saldo aktual berada dalam satu transaksi database. Pencatatan jurnal atas transaksi yang sudah memengaruhi saldo menggunakan mode impor tanpa mengubah saldo lagi.

Perubahan nominal dompet yang sudah ada menjadi penyesuaian tercatat dengan tanggal dan alasan. Nominal awal dompet baru menghasilkan entri saldo pembukaan. Penghapusan rekening/dompet yang sudah memiliki mutasi atau snapshot ditolak; penonaktifan tetap tersedia. Ini menutup jalur perubahan saldo yang selama ini melewati riwayat transaksi.

## 5. Pemeriksaan kesiapan

Pemeriksaan menghasilkan daftar masalah berstruktur: kode, jumlah, uraian, referensi transaksi, dan tautan perbaikan. Hasil dihitung ulang pada server saat menyimpan, walaupun pratinjau sebelumnya lulus.

| Pemeriksaan | Aturan lulus |
| --- | --- |
| Approval transaksi | Tidak ada transaksi/revisi keuangan relevan dalam periode dan buku yang masih menunggu keputusan. Pengajuan akademik/presensi dan rencana anggaran yang tidak mengubah transaksi periode tidak menghalangi penutupan. |
| Status transaksi | Tidak ada penerimaan, pembayaran, pengeluaran, atau transfer relevan yang pending/tidak konsisten. Tagihan siswa belum dibayar bukan transaksi pembayaran pending. |
| Saldo | Saldo akhir seluruh rekening, sub-saldo buku, dan dompet terkait tidak negatif. |
| Jurnal | Jurnal posted seimbang, tidak ada jurnal gagal, dan tidak ada jurnal approved yang tertinggal setelah tahap posting. |
| Transfer | Semua transfer relevan memiliki kedua sisi posted dan identitas sumber/tujuan yang sesuai. |
| BOS | Saldo awal + penerimaan + transfer masuk - belanja - transfer keluar = saldo akhir untuk jenis/tahun BOS terkait; subledger dan rekening pendukung dapat direkonsiliasi. Realisasi belanja tidak diwajibkan sama dengan anggaran atau menghabiskan seluruh saldo. |
| Pajak | Pajak yang wajib disetor atas belanja periode memiliki pencatatan penyelesaian dan bukti terkait; perubahan status pajak dalam periode terkunci mengikuti aturan penguncian. |
| Rekening | Rekening milik sekolah yang benar, metode dan pemetaan sumber valid, serta rekonsiliasi kas/bank pada batas periode tidak berselisih. Flag waktu rekonsiliasi lama saja tidak cukup. |
| Bukti | Bukti transaksi pengeluaran wajib tersedia sebelum tutup buku, walaupun pengajuan awal boleh tanpa nota. SP2D/bukti penerimaan mengikuti kewajiban kategori yang ditetapkan; tidak dibuat wajib secara diam-diam untuk semua penerimaan. Bukti harus benar-benar tersedia pada penyimpanan. |
| Kelengkapan historis | Tidak ada data, sumber BOS, mutasi saldo, atau status posting yang masih ambigu untuk periode tersebut. |

Contoh pesan: “2 pengeluaran belum approval kepala sekolah”, “1 nota pengeluaran belum diunggah”, “Saldo Dompet Operasional kurang Rp125.000”, atau “Jenis BOS belum ditentukan pada 3 transaksi”. Masalah dapat ditampilkan bersama agar bendahara tidak memperbaiki satu per satu melalui percobaan berulang.

Saat preflight gagal, simpan riwayat percobaan dan tampilkan notifikasi dalam aplikasi kepada bendahara yang menjalankannya. Tidak ada snapshot penutupan atau perubahan saldo dari percobaan yang gagal.

## 6. Urutan tutup buku atomik

1. Autentikasi, keanggotaan sekolah aktif, izin, buku, dan format periode diperiksa pada server.
2. Mulai transaksi database. Ambil pengunci bersama sekolah dan buku/periodenya sesuai urutan penguncian yang sama dengan jalur posting.
3. Periksa duplikasi, penutupan yang bertumpang tindih, urutan periode, dan perubahan versi sejak pratinjau.
4. Periksa persetujuan, pemetaan, bukti, transfer, dan prasyarat yang tidak memerlukan posting.
5. Posting tepat sekali transaksi yang sudah approved, memiliki jejak belum diposting yang dapat dibuktikan, dan belum memiliki perubahan saldo. Jangan otomatis menyetujui transaksi pending.
6. Hitung ulang pemeriksaan jurnal, saldo, pajak, BOS, dan rekonsiliasi. Jika gagal, batalkan seluruh tahap penutupan beserta posting yang dilakukan di dalamnya. Simpan catatan kegagalan di luar transaksi yang dibatalkan.
7. Hitung saldo akhir bertanggal batas dari saldo awal dan mutasi, termasuk bila sudah ada transaksi pada bulan berikutnya.
8. Simpan snapshot immutable berisi rekening, sub-saldo buku, dompet, arus dana, pajak, transfer, jurnal sumber, hasil pemeriksaan, checksum/versi, waktu, dan pelaku.
9. Simpan saldo awal periode berikutnya dengan referensi snapshot asal, lalu tandai periode closed dan commit.

Nominal saldo awal berikutnya adalah carryforward akuntansi. Tahap tersebut tidak menambah lagi `current_balance` rekening atau `nominal` dompet.

Tahunan merangkum snapshot bulanan dan melakukan pemeriksaan silang. Carryforward Desember ke Januari yang sudah ada dipakai kembali; penutupan tahunan tidak membuat saldo awal kedua. Kunci unik buku-entitas-tanggal awal mencegah duplikasi.

## 7. Penguncian semua jalur tulis

Tambahkan layanan penjaga periode bersama (`FinancePeriodGuard`). Penguncian dijalankan dalam transaksi database yang sama dengan penutupan/posting dan berlaku terhadap tanggal efektif transaksi, bukan tanggal tombol diklik.

Integrasikan pada:

- penerimaan Komite, pembayaran invoice, dan approval pembayaran siswa;
- pemasukan BOS, Hibah, Lainnya, serta pengajuan/penerapan revisinya;
- create/update/approve/reject pengeluaran, bukti transaksi, dan status pajaknya;
- transfer rekening dan transfer/alokasi dompet;
- penyesuaian saldo awal, nominal dompet, klasifikasi sumber, serta penghapusan data yang dapat memengaruhi saldo/snapshot historis;
- semua endpoint lama yang mengubah status atau saldo pada controller, bukan hanya service baru.

Edit/revisi memeriksa tanggal dan buku sebelum serta sesudah perubahan. Memindahkan transaksi dari bulan/buku tertutup ke bulan/buku terbuka juga ditolak. Posting jurnal menelusuri sumber yang sama agar tidak menjadi jalur bypass.

Semua jalur posting mengambil pengunci yang sama sebelum mengubah saldo. Urutan global: sekolah, buku menurut ID, periode, approval/sumber transaksi, rekening menurut ID, lalu dompet menurut ID. Jalur yang sudah mengunci approval terlebih dahulu harus disesuaikan dengan urutan ini. Penutupan bersamaan dengan pembayaran, approval, transfer, revisi, atau penutupan ulang harus menghasilkan satu keadaan yang konsisten. Deadlock yang aman dapat dicoba ulang secara terbatas.

## 8. Pembukaan kembali dan versi laporan

- Kepala sekolah/superadmin dengan keanggotaan aktif dan izin khusus `finance.closing.reopen` dapat membuka kembali dengan alasan wajib. Bendahara tidak memperoleh hak ini secara default.
- Buka kembali tahunan dahulu jika bulan berada di bawah tahun tertutup. Periode bulanan sesudahnya yang sudah ditutup harus dibuka mundur terlebih dahulu. Sistem menjelaskan dependensi; tidak membuka periode lain diam-diam.
- Pembukaan kembali hanya melepas penguncian. Transaksi yang sudah dibukukan tidak dibalik dan saldo aktual tidak direset.
- Snapshot lama tetap tersimpan sebagai versi historis dengan pelaku/waktu/alasan reopening. Penutupan ulang membuat versi snapshot baru dan memperbarui carryforward periode terbuka berikutnya dengan audit perubahan.
- Pratinjau rekonsiliasi, hasil cek, dan saldo awal yang bergantung pada versi lama ditandai perlu dihitung/dikonfirmasi ulang.

## 9. Skema dan kompatibilitas

Perluas `book_closings` dengan `book_id`, tanggal awal/akhir, tahun anggaran/tahun BOS, versi aktif, dan metadata reopening. Pisahkan tabel versi snapshot/kejadian audit agar snapshot lama tidak ditimpa.

Tambahkan `finance_period_balances` untuk saldo awal/akhir per buku, rekening/subrekening atau dompet, tanggal batas, dan snapshot asal. Pisahkan pratinjau serta percobaan penutupan gagal dari record closed.

Tambahkan pemetaan BOS pada pengeluaran dan transaksi relevan. Hubungan rekening/dompet dengan buku dipertahankan sebagai identitas stabil; mengganti nama master tidak mengubah klasifikasi histori.

Pertahankan endpoint `GET/POST /finance/closing` dan jenis lama `monthly`, `yearly`, `bos` melalui adapter. BOS tahunan memakai pilihan eksplisit tambahan. Field periode disimpan sebagai kolom terstruktur; hanya input aktif yang dikirim form. `bos_type` tidak lagi digabung ke string periode sebagai satu-satunya identitas.

Metode lama `validate(schoolId)` dapat tetap menjadi ringkasan kesiapan sekolah untuk kompatibilitas. Keputusan penutupan wajib memakai validator dengan konteks buku dan periode lengkap. Ringkasan tanpa periode tidak boleh menyatakan suatu periode siap ditutup.

## 10. Data lama dan penerapan

Migrasi skema bersifat tambahan dan dapat diperiksa sebelum dijalankan. Sediakan perintah audit/pratinjau tanpa perubahan untuk memetakan transaksi, saldo awal, dan sumber BOS lama.

- Transaksi yang terbukti telah diposting diimpor ke jurnal tanpa mengubah saldo aktual.
- Riwayat transfer yang tidak pernah disimpan tidak direkayasa. Penyesuaian baseline memerlukan tanggal, bukti rekonsiliasi, alasan, dan pelaku berwenang.
- Status approved dengan status posting atau alokasi yang ambigu menjadi masalah rekonsiliasi, bukan alasan untuk menambah/mengurangi saldo lagi.
- Record tutup buku lama tetap tersedia sebagai snapshot lama. Aktivasi penguncian dan carryforward memerlukan pemetaan/perhitungan yang lulus; tidak melabeli snapshot lama sebagai penutupan tervalidasi tanpa bukti.
- Penutupan periode historis sebelum baseline hanya diizinkan bila seluruh mutasinya dapat direkonstruksi serta direkonsiliasi. Bila tidak, sistem memberi alasan dan kebutuhan penyelesaian yang jelas.
- Rekening/dompet campuran atau belum dipetakan harus diselesaikan sebelum penutupan buku terkait. Data sekolah lain tidak boleh muncul dalam audit/pratinjau.

Jalankan audit data lama dan uji browser pada database terisolasi dahulu. Jangan menjalankan impor saldo/perbaikan historis pada database utama tanpa hasil pratinjau yang sudah ditinjau.

## 11. Antarmuka dan laporan

Gunakan halaman Tutup Buku dan komponen form/DataTable yang sudah ada. Pilih buku Komite/BOS, jenis BOS bila relevan, bulanan/tahunan, dan periode/tahun anggaran. Tampilkan rentang tanggal yang akan dikunci.

Tombol “Periksa Periode” menghasilkan checklist dengan jumlah dan tautan transaksi bermasalah. Tampilkan status belum diperiksa, sedang diperiksa, gagal, siap, closed, dan reopened secara jelas. Jangan menampilkan angka nol sebagai pengganti pemeriksaan yang belum tersedia.

Jika lulus, tampilkan saldo awal, mutasi, saldo akhir per rekening/dompet, dan saldo awal periode berikutnya. Tombol “Kunci & Tutup Buku” menjalankan pemeriksaan ulang. Bila data berubah, tampilkan hasil terbaru tanpa menggunakan pratinjau lama.

Rincian snapshot menyediakan laporan periode yang tetap sama meskipun transaksi bulan berikutnya berjalan. Tombol buka kembali hanya muncul bagi pengguna berwenang dan menjelaskan dependensi periode.

Form tetap dapat dipakai dengan keyboard. Pada layar kecil, field menjadi satu kolom, tabel bergulir di dalam wadahnya, dan aksi utama tetap dapat dijangkau. Semua kesalahan diumumkan dan input yang valid dipertahankan.

## 12. Bukti penerimaan implementasi

1. Validasi hanya menyertakan transaksi sekolah, buku, tanggal, dan tahun BOS yang tepat; tagihan siswa belum dibayar tidak disamakan dengan pembayaran pending.
2. Delapan pemeriksaan dan kewajiban bukti di atas menggunakan data nyata. Ada tes untuk setiap alasan gagal dan tautan perbaikannya.
3. Posting approved yang belum posted terjadi sekali; data lama yang sudah mengubah saldo tidak diposting ulang. Kegagalan di tengah proses membatalkan perubahan saldo dan penutupan.
4. Saldo historis benar meskipun rekening/dompet telah menerima mutasi bulan berikutnya. Carryforward tidak mengubah saldo aktual dan tidak mengganda saat penutupan bulanan/tahunan atau retry.
5. Tambah bertanggal mundur, edit lintas periode, hapus, approval, revisi, perubahan bukti/pajak, transfer, dan penyesuaian master ditolak untuk periode closed pada backend.
6. Penutupan bersamaan dengan posting/approval diuji menggunakan database yang mendukung row lock, selain tes SQLite. Bila lingkungan tersebut tidak tersedia, batas verifikasi dilaporkan.
7. BOS bulanan hanya mengunci jenis/bulan/tahun yang dipilih. Tahunan menunggu seluruh periode bulanan yang relevan; satu jenis BOS tidak mengunci sumber lain.
8. Pembukaan kembali memeriksa role, sekolah, alasan, dan dependensi; versi snapshot lama tetap dapat dibaca.
9. Rekonsiliasi rekening fisik bersama mencakup seluruh sub-saldo tanpa menghitung dompet dua kali sebagai uang baru.
10. Suite keuangan/pembayaran yang sudah ada tetap lulus. Browser memverifikasi penutupan berhasil/gagal, snapshot, reopening, serta tata letak desktop dan seluler.

## 13. Batas cakupan

Tidak mengaktifkan WhatsApp, integrasi bank/provider eksternal, atau mengarang riwayat yang hilang. Tidak otomatis menyetujui pengajuan, menghapus transaksi bermasalah, atau mereset saldo rekening/dompet saat carryforward. Pencatatan jurnal dan rekonsiliasi difokuskan pada alur keuangan sekolah yang disebut dalam spesifikasi ini.

Tahap berikutnya setelah spesifikasi tertulis disetujui adalah rencana implementasi dengan urutan migrasi, adapter posting, penjaga periode, validasi, closing/reopening, UI, audit data lama, dan pengujian.
