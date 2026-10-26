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

## Tunggakan Siswa

Menu `/finance/arrears` tersedia untuk Bendahara dan Teller di sekolah aktif. Pencarian mencakup nama, NIS, NISN; filter kelas, bulan, tahun serta urutan nominal diterapkan sebelum daftar dipaginasi. Bulan dan tahun memakai tanggal mulai periode tagihan; invoice lama tanpa periode memakai deadline. Siswa tidak aktif yang masih memiliki utang tetap tampil. Tagihan lunas tetap tersedia pada rincian siswa, tetapi tidak menambah saldo tunggakan.

Pembayaran Tunai masuk ke kas aktif berkategori Komite atau kas umum yang belum dikategorikan; kas BOS tidak dipilih. Transfer wajib memilih rekening bank aktif bertanda Komite. Atur **Kategori Dana → Komite** pada Rekening Sekolah terlebih dahulu; rekening lama tidak diklasifikasikan otomatis berdasarkan namanya. Rekening bank berkategori kosong belum dianggap sebagai rekening Komite.

Saldo rekening, transaksi, status invoice, dan alokasi dompet diperbarui atomik. Alokasi hanya memakai jenis pemasukan tagihan yang dibayar dan dompet aktif milik sekolah yang sama. Jika tidak ada aturan yang cocok, tidak ada alokasi; aturan dari jenis pemasukan lain tidak digunakan sebagai fallback. Total alokasi tidak boleh melebihi pembayaran. Pembayaran melalui approval menggunakan service alokasi yang sama.

Foto siswa dapat diunggah melalui Data Murid (JPG/PNG/WebP, maksimal 2 MB). File disimpan privat dan disajikan melalui route yang memeriksa sekolah serta akses ke tunggakan; tanpa foto, daftar memakai inisial. Melihat daftar/rincian menghitung tampilan denda terkini tanpa menulis invoice; penyimpanan denda tetap dilakukan scheduler atau saat pembayaran di bawah penguncian invoice.

## Pemasukan Komite Manual

Pada menu Pemasukan, pilih kategori Komite. Daftar memakai pencarian Nama/NIS/NISN, kelas, bulan, tahun, dan urutan nominal yang sama dengan Tunggakan Siswa, tetapi hanya mencakup kewajiban yang dapat dibuktikan sebagai Komite. Untuk invoice lama tanpa relasi langsung, pencocokan nama hanya diterima jika item pertama menunjuk satu konfigurasi unik di sekolah yang sama dan jenis pemasukan konfigurasi itu Komite; nama ambigu tidak dipakai.

Klik siswa, pilih satu atau beberapa tagihan, lalu isi metode pembayaran dan tanggal. Transfer memerlukan bank Komite aktif. Nominal awal adalah seluruh sisa setiap tagihan; nominal cicilan tetap mengikuti aturan invoice. Form menerima pemisah ribuan titik dan desimal koma, tanpa mengubah nilai pecahan.

Satu penerimaan mempunyai nomor utama `PK...` dan rincian transaksi untuk setiap invoice. Saldo rekening bertambah sekali sebesar total penerimaan; dompet dialokasikan berdasarkan jenis pemasukan masing-masing invoice. Record `committee_receipts` adalah metadata penerimaan, bukan pemasukan tambahan dalam ledger. Seluruh baris, status, saldo, dan alokasi disimpan atomik: jika satu baris atau alokasi gagal, semua perubahan dibatalkan.

Kunci form dan sidik data mencegah pengiriman ulang identik menggandakan pembayaran. Pengiriman dengan kunci sama tetapi data berbeda ditolak; buka form baru untuk penerimaan baru. Notifikasi pembayaran kepada orang tua disiapkan setelah commit melalui adapter notifikasi yang masih berupa log; pengiriman WhatsApp nyata tetap ditunda.
