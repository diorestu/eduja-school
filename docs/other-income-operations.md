# Pendapatan Lainnya

Buka Pemasukan, lalu pilih Lainnya. Isi sumber pendapatan, nominal positif maksimal dua angka desimal, tanggal terima, dan metode Tunai/Transfer. Bukti opsional menerima PDF/JPG/PNG/HEIC maksimal 10 MB. Bukti baru disimpan privat dan hanya dapat diunduh oleh pengguna berwenang di sekolah yang sama.

Tunai menambah kas sekolah aktif (tipe Tunai/cash). Jika ada beberapa kas, pilih tujuan. Transfer wajib memilih rekening bank aktif dengan Kategori Dana Komite pada Rekening Sekolah. Bank yang tidak ditandai Komite atau berasal dari sekolah lain ditolak. Sumber BOS/BOP/DAK, Komite, dan Hibah harus menggunakan kategori pemasukan yang sesuai.

Alokasi dompet mengikuti master Jenis Pemasukan kategori Lainnya. Satu jenis dipilih otomatis; beberapa jenis memerlukan pilihan. Alokasi kategori lain tidak digunakan. Total persentase maksimal 100%, dan gabungan nominal/persentase tidak boleh melebihi penerimaan. Pencatatan penerimaan, saldo kas/bank, dan alokasi dompet berada dalam satu transaksi database. Pengiriman ulang form yang sama tidak menggandakan saldo atau bukti.

Ajukan perubahan lewat Revisi / Riwayat beserta alasan. Sumber, nominal, tanggal, metode, rekening, jenis pemasukan, dan bukti dapat direvisi. Pengajuan mempertahankan data/saldo sampai kepala sekolah dengan keanggotaan aktif menyetujui melalui Approval. Persetujuan mengoreksi selisih saldo rekening dan dompet virtual sekaligus; pengurangan ditolak jika saldo tidak cukup. Data yang berubah setelah pengajuan harus ditinjau ulang melalui pengajuan baru.

Jejak revisi menyimpan nilai sebelum/sesudah, pengaju, pemberi keputusan, alasan, waktu, dan bukti lama/pengganti. Perubahan nominal mempertahankan aturan alokasi saat penerimaan dicatat; perubahan jenis pemasukan menggunakan aturan jenis baru saat pengajuan. Pembulatan pecahan rupiah dibagi secara deterministik.

Penerimaan lama tanpa jejak alokasi memerlukan rekonsiliasi sebelum koreksi nominal/jenis jika sekolah pernah memiliki konfigurasi alokasi. Sistem tidak menebak nilai yang sebelumnya masuk ke dompet. Bukti lama di disk publik tetap didukung dan belum dipindahkan; pembatasan penyimpanan privat berlaku pada bukti yang diunggah melalui flow baru.

Migrasi: `2026_10_12_000003_add_other_income_revisions`.
Pengujian: `php artisan test --filter=OtherIncomeFlowTest`.
