# Pengeluaran Operasional Komite

Bendahara membuka Pengeluaran, lalu memilih Operasional. Tanggal, jenis pengeluaran Komite, deskripsi, nominal positif, penerima dana, metode pembayaran, dan dompet sumber wajib diisi. Pilih kas/rekening sesuai metode Tunai/Transfer; kas tunggal juga dapat ditentukan otomatis pada backend. Pilihan kas/bank mengecualikan rekening berlabel BOS. Dompet sumber harus aktif dan milik sekolah yang sama. Bukti transaksi opsional, PDF/JPG/PNG/HEIC maksimal 10 MB.

Penyimpanan menghasilkan nomor `EXP{YYYYMMDD}{id delapan digit}`, pengeluaran pending, dan antrian approval kepala sekolah. Tidak ada saldo yang dipotong saat pengajuan. Kepala sekolah mendapat notifikasi dalam aplikasi yang membuka detail pengajuan, nominal, penerima, metode, rekening, dompet, dan bukti. WhatsApp tetap ditunda.

Approval mengunci pengajuan dan pengeluaran, lalu memeriksa ulang saldo kas/bank serta dompet sumber. Kedua saldo dikurangi dengan nominal pengeluaran dalam satu transaksi database. Jika salah satu sumber tidak aktif, tidak sesuai sekolah/metode, atau saldonya kurang, keputusan dibatalkan dan saldo tidak berubah. Tidak ada pemotongan pajak tambahan dalam perubahan ini. Reject tidak mengubah saldo.

Pengeluaran pending dapat diedit dengan validasi sumber dana yang sama. Pengeluaran yang sudah disetujui, ditolak, atau dibukukan tidak dapat diubah melalui endpoint edit. Pemrosesan ulang approval yang sama atau approval kedua atas pengeluaran yang sudah diproses ditolak.

Lampiran baru disimpan privat pada disk local dalam `finance/expenses/{school_id}/...`; unduhan memeriksa akses pengguna dan sekolah aktif. Bukti lama pada disk public masih didukung tanpa memindahkan berkas lama. Nomor transaksi hanya dihasilkan untuk pengeluaran baru; data lama tetap memakai pengenal pengeluaran yang sudah ada.

Jalankan migrasi `2026_10_23_000001_add_expense_transaction_number` pada database tujuan sebelum memakai perubahan. Pengujian browser menggunakan SQLite sementara dan tidak memigrasikan database aplikasi utama.

Tes: `php artisan test tests/Feature/CommitteeExpenseFlowTest.php`.
