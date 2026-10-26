# Pemasukan Hibah

Buka Pemasukan, lalu pilih Hibah. Isi nama donatur, nominal positif maksimal dua angka desimal, tanggal terima, dan metode Tunai/Transfer. Bukti opsional menerima PDF/JPG/PNG/HEIC maksimal 10 MB. Lampiran baru disimpan privat dan diunduh melalui pemeriksaan sekolah serta hak akses pengguna.

Tunai menambah kas sekolah aktif (tipe Tunai/cash). Jika ada beberapa kas, pilih tujuan. Transfer wajib memilih bank sekolah aktif. Rekening yang ditandai BOS tidak digunakan untuk Hibah. Penerimaan dan saldo tersimpan dalam satu transaksi database; kegagalan alokasi membatalkan keduanya. Pengiriman ulang form yang sama tidak menggandakan saldo atau lampiran.

Alokasi dompet virtual mengikuti master Jenis Pemasukan kategori Hibah. Satu jenis dipilih otomatis; jika ada beberapa jenis, form meminta pilihan. Aturan kategori lain tidak digunakan. Total persentase harus maksimal 100%, dan gabungan alokasi nominal/persentase tidak boleh melebihi penerimaan. Pecahan rupiah dibagi secara deterministik berdasarkan sisa pembulatan. Aturan serta nilai yang benar-benar dibukukan disimpan pada penerimaan.

Ajukan perubahan melalui Revisi / Riwayat dengan alasan. Nominal, donatur, tanggal, metode, rekening, jenis Hibah, dan bukti dapat dikoreksi. Pengajuan tidak mengubah penerimaan atau saldo. Kepala sekolah dengan keanggotaan aktif memutuskan melalui Approval. Persetujuan mengoreksi selisih rekening dan dompet virtual sekaligus; jika rekening asal atau dompet tidak memiliki cukup saldo untuk pengurangan, persetujuan dibatalkan. Pengajuan yang penerimaannya sudah berubah juga harus ditolak dan diajukan ulang.

Riwayat menyimpan data sebelum/sesudah, alasan, nama pengaju/pemberi keputusan, waktu, dan dokumen lama/pengganti. Perubahan nominal mempertahankan aturan alokasi saat penerimaan dicatat; perubahan jenis Hibah menggunakan aturan jenis baru saat pengajuan. Konfigurasi alokasi yang berubah setelah pengajuan tidak mengubah pengajuan yang sudah ditinjau.

Penerimaan lama belum memiliki jejak alokasi. Jika sekolah pernah memiliki aturan alokasi, koreksi nominal/jenis pada data lama membutuhkan rekonsiliasi jejak alokasi dahulu agar sistem tidak menebak dana yang harus dikembalikan. Penerimaan lama tanpa rekening dapat dilengkapi melalui revisi; pengkreditan rekening terjadi setelah persetujuan. Bukti lama pada disk publik tetap didukung, tanpa memindahkan dokumen yang mungkin masih direferensikan fitur lama.

Migrasi: `2026_10_12_000002_add_grant_income_revisions`.
Pengujian: `php artisan test --filter=GrantIncomeFlowTest`.
