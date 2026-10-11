# Operasional Tagihan Komite

Konfigurasi baru dibuat melalui Tagihan → Buat Tagihan Baru. Angkatan berarti tahun masuk yang dicatat di Data Murid; data lama yang belum diisi tetap termasuk dalam Semua Angkatan.

## Penjadwalan

Jalankan migration pada lingkungan deployment, lalu pastikan Laravel scheduler berjalan melalui cron setiap menit:

```sh
php artisan migrate
php artisan schedule:run
```

`schedule:run` merupakan perintah yang dipanggil cron, bukan sekali saja untuk mengaktifkan penjadwalan. Scheduler menjalankan `komite:process-billing --prepare-only` setiap hari pukul 00:00 untuk pergantian periode dan deadline, kemudian `komite:process-billing` pukul 07:00 untuk pemrosesan pengingat, dalam zona waktu aplikasi. Command membuat periode yang waktunya sudah tiba, mengejar periode terlewat sejak konfigurasi dibuat, menonaktifkan periode lewat deadline, menghitung denda, dan menyiapkan pengingat. Untuk pemrosesan manual tanpa percobaan pengiriman:

```sh
php artisan komite:process-billing --prepare-only
```

Konfigurasi bulanan tetap berjalan setelah periode terdahulu Deactive. Nonaktifkan konfigurasi melalui Ubah Tagihan untuk menghentikan periode berikutnya. Piutang yang sudah terbit tetap bisa dilunasi dan tidak dapat dihapus bersama konfigurasi.

## Kompatibilitas data lama

Konfigurasi lama tanpa `recurrence_start` tidak otomatis diproses generator baru, sehingga invoice lama tidak digandakan. Buat konfigurasi baru untuk periode berikutnya; pastikan kewajiban pada bulan yang sama belum diterbitkan lewat alur lama. Invoice lama tanpa `school_id` hanya dapat diakses jika siswa serta tahun ajaran membuktikan kepemilikan sekolah. Saat dibayar, sekolah pemilik dicatat pada invoice.

Pembayaran baru mencatat rekening tujuan yang sebenarnya pada transaksi, termasuk ketika dipilih otomatis. Pembayaran komite baru ditolak jika tidak ada rekening aktif yang dapat menerima pembayaran. Aturan cicilan dan denda menggunakan snapshot invoice, sehingga perubahan konfigurasi tidak mengubah tagihan yang sudah terbit.

## WhatsApp ditunda

Sesuai instruksi pengguna, tidak ada provider WhatsApp yang diintegrasikan atau diaktifkan. Pengingat disimpan dalam `billing_reminders`; `unconfigured` berarti belum dapat dikirim, `missing_recipient` berarti nomor wali belum valid, dan `cancelled` berarti kewajiban sudah lunas. `sent` hanya boleh digunakan adapter mendatang ketika provider mengonfirmasi penerimaan pengiriman; ini bukan bukti pesan dibaca atau diterima di perangkat.

Kontrak `BillingWhatsAppGateway` tersedia untuk integrasi mendatang. Default `DeferredBillingWhatsAppGateway` selalu tidak terkonfigurasi. Pengiriman harus memakai nomor wali yang sah dan identitas idempotensi per pengingat; hasil pengiriman tidak pasti perlu direkonsiliasi sebelum dicoba lagi. UI tidak mengklaim notifikasi terkirim selama integrasi ditunda.
