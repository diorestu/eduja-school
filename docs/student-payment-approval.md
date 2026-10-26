# Approval pembayaran siswa

Menu **Approval Tunggakan** menampilkan pengajuan pembayaran sekolah aktif. Bendahara dan teller dapat membuka detail siswa, kelas, nomor tagihan, nominal, tanggal pembayaran, rekening tujuan, dan bukti sebelum mengambil keputusan. Menu Approval Keuangan yang sudah ada tetap tersedia untuk jenis pengajuan lainnya; teller hanya memperoleh akses antrian pembayaran.

Alur dimulai dari `PaymentSubmission` berstatus `pending` dengan `ApprovalRequest` bertipe `payment` yang menunjuk pengajuan tersebut. Form pengiriman bukti oleh siswa/orang tua berada di luar perubahan ini.

- Pengajuan pending muncul di notifikasi dalam aplikasi bendahara/teller.
- Approve mencatat transaksi, memperbarui tagihan, saldo rekening, dan alokasi dompet sesuai aturan yang ada. Kegagalan pencatatan membatalkan keputusan dan seluruh perubahan saldo.
- Reject wajib menyertakan alasan; alasan disimpan pada pengajuan pembayaran. Reject tidak mencatat transaksi atau mengubah saldo.
- Pengajuan yang sudah diputuskan tidak bisa diputuskan ulang. Penguncian approval dan pembayaran mencegah pencatatan ganda.
- Hasil keputusan terlihat melalui notifikasi dalam aplikasi siswa (`students.user_id`) dan orang tua (`students.guardian_user_id`) yang terhubung serta memiliki keanggotaan aktif pada sekolah tersebut. Notifikasi dihitung dari pengajuan; belum memiliki status dibaca tersendiri dan mengikuti batas sepuluh item terbaru pada feed sekolah.
- WhatsApp tetap ditunda, tidak ada pengiriman pesan eksternal dalam alur ini.

Detail dan bukti hanya dapat diakses reviewer atau siswa/orang tua terkait pada sekolah aktif. Bukti disimpan pada disk `local` di `payment-proofs/{school_id}/...`, diunduh melalui endpoint berautentikasi, dan tidak menggunakan URL publik. Pengajuan lama tanpa bukti menampilkan keterangan bahwa bukti belum tersedia.

Verifikasi otomatis: `php artisan test tests/Feature/StudentPaymentApprovalFlowTest.php`.
