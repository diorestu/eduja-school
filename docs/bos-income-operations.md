# Pemasukan BOS

- Buka Pemasukan, lalu pilih kategori BOS. Sumber dana menyediakan pilihan BOS bawaan, jenis pemasukan kategori BOS milik sekolah, dan sumber BOS/BOP/DAK dari master anggaran sekolah.
- Tahun, sumber dana, nominal positif maksimal dua angka desimal, dan tanggal terima wajib diisi. SP2D opsional sesuai validasi flow; PDF/JPG/PNG/HEIC maksimal 10 MB disimpan privat.
- Rekening tujuan harus aktif dan ditandai kategori dana BOS pada Rekening Sekolah. Rekening lama bertipe BOS juga dikenali. Jika ada beberapa rekening BOS, bendahara harus memilih rekening tujuan.
- Penerimaan awal langsung tercatat dan menambah saldo rekening BOS. Pengiriman ulang form yang sama tidak menggandakan penerimaan atau saldo.
- Buka Revisi / Riwayat untuk mengajukan perubahan beserta alasannya. Penerimaan dan saldo tetap sampai kepala sekolah dengan keanggotaan aktif menyetujui melalui menu Approval.
- Persetujuan menyesuaikan selisih nominal pada rekening asal, termasuk jika rekening asal telah dinonaktifkan. Penolakan tidak mengubah saldo. Pengurangan ditolak jika saldo tidak cukup; pengajuan yang datanya telah berubah juga tidak bisa diterapkan.
- Jejak revisi menyimpan nilai sebelum/sesudah, pengaju, pemberi keputusan, dan waktunya. SP2D lama dan pengganti tetap tersedia dalam riwayat. Dokumen hanya dapat diunduh oleh pengguna berwenang di sekolah yang sama.
- Penerimaan lama yang belum memiliki rekening dapat dilengkapi lewat pengajuan revisi; nominal baru baru dikreditkan setelah persetujuan. Penerimaan harus sudah berstatus tercatat dengan waktu posting.

Migrasi: `2026_10_12_000001_add_bos_income_revisions`.
Pengujian: `php artisan test --filter=BosIncomeFlowTest`.
