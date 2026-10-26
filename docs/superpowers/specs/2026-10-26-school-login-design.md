# Login dan konteks sekolah

Alur yang disetujui: autentikasi email/HP dan password, ambil sekolah unik dengan afiliasi aktif, otomatis pilih bila satu sekolah, wajib pilih bila lebih dari satu, kemudian dashboard berdasarkan role. Dinas/Yayasan langsung ke dashboard masing-masing.

Untuk beberapa role dalam satu sekolah, gunakan gabungan permission dan prioritas dashboard yang sudah ada (alumni, siswa, orang tua, kemudian operasional). Tidak menambahkan pemilih role.

Tanpa afiliasi aktif, tampilkan keadaan belum memiliki akses di halaman sekolah, termasuk nama pengajuan berstatus pending bila ada. Jangan izinkan akses data sekolah atau mengembalikan hak dari users.role. Sekolah dan afiliasi aktif wajib divalidasi kembali setiap permintaan sekolah; konteks yang sudah tidak valid dibersihkan dan user kembali ke pemilihan sekolah.

## Pendekatan

Perluas SchoolContext, middleware, dan layanan permission yang ada; gunakan halaman pemilihan sekolah yang sudah tersedia untuk keadaan tanpa akses. Alternatif halaman baru dan pemilih role menambah langkah serta tidak diperlukan untuk alur dasar ini.

Tujuan login selalu dashboard atau pemilihan sekolah, sehingga url.intended tidak melewati langkah tersebut. Pengguna boleh mengganti sekolah hanya melalui afiliasi aktif miliknya. Perubahan dibatasi pada alur login; pekerjaan keuangan yang sudah ada tetap dipertahankan.
