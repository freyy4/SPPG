# SPPG

Aplikasi pengelolaan purchase order dan operasional SPPG, termasuk perhitungan pembelian, kuantitas gudang, biaya, pembayaran, dan analisa keuntungan.

<!-- APP-VERSION:START -->
**Versi pencatatan:** `v0.1.2`  
**Terakhir diperbarui:** 2026-10-28 18:55:04 WIB
<!-- APP-VERSION:END -->

## Fitur utama

- Dashboard PO dengan pencarian dan filter tanggal.
- Pencatatan purchase order, kuantitas gudang, dan harga pemasok.
- Analisa PO dan keuntungan, fee mitra, fee KSPPG, serta pembayaran supplier.
- Nota dapur dan tautan verifikasi bertanda tangan.
- Kalkulator buah beserta riwayat keputusan, pengelolaan wilayah, akun, dan profil.



## Teknologi dan menjalankan lokal

PHP 8.3+, Laravel v13.26.1, Blade, dan Vite. Gunakan versi Node yang sesuai `package.json` dan paket Vite yang terkunci pada proyek.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
# Atur koneksi database dan layanan yang dibutuhkan di .env terlebih dahulu.
php artisan migrate
npm run build
php artisan serve
```

Langkah di atas untuk instalasi lokal baru. Jangan menimpa `.env` server yang sudah berjalan. Konfigurasi database, integrasi, dan kredensial berada di `.env` dan tidak diunggah ke GitHub. Worker antrean dan scheduler perlu dijalankan terpisah untuk fitur yang menggunakannya.

## Versi dan riwayat update

`v0.1.0` adalah awal pencatatan versi pada README, bukan klaim versi rilis historis atau kesiapan produksi. Setiap commit melalui auto-sync server berikutnya menaikkan patch (`v0.1.1`, `v0.1.2`, dst.) dan menambahkan catatan bertanggal WIB. Ini versi snapshot sinkronisasi; tidak membuat tag atau GitHub Release.

Ringkasan otomatis dibentuk dari file dan jenis perubahan. Ringkasan menjelaskan area yang tersentuh, bukan jaminan fitur selesai atau pengujian lulus. Update manual di luar auto-sync perlu dicatat sendiri. Bagian otomatis dikelola server; catatan tambahan dapat ditulis di luar penandanya.

<!-- AUTO-UPDATES:START -->
| Tanggal (WIB) | Versi | Update |
| --- | --- | --- |
| 2026-10-28 18:55:04 WIB | v0.1.2 | Update tampilan, akun dan akses, dashboard dan 1 bagian lainnya (5 file; 1 ditambah, 4 diubah). <!-- snapshot:f4a27e28e1c8babd5be9d9d69f8b9356b533bfb18b9bbb604198e361608d8087 --> |
| 2026-10-28 18:50:05 WIB | v0.1.1 | Update tampilan, akun dan akses, dashboard (5 file; 3 ditambah, 2 diubah). <!-- snapshot:1732371aabea0c521a5647c67d75132c9a68ef60de716a034ce3512b51a11dfa --> |
| 20 Oktober 2026 | v0.1.0 | Mulai pencatatan versi; README menjelaskan aplikasi, fitur, cara menjalankan, dan riwayat perubahan. |
<!-- AUTO-UPDATES:END -->

### Riwayat sebelum pencatatan versi

Tanggal mengikuti commit Git yang tersedia; nomor versi lama tidak direka.

| Tanggal | Versi | Perubahan yang tercatat |
| --- | --- | --- |
| 20 Oktober 2026 | Belum diberi versi | Kuantitas gudang, harga supplier B, status pembayaran gudang, dan penyesuaian analisa keuntungan (`f6be090`). |
| 27 September 2026 | Belum diberi versi | Pembaruan filter tanggal dashboard dan kalkulator buah (`d5ffa64`). |
| 22 September 2026 | Belum diberi versi | Pencatatan awal aplikasi dalam Git (`ad237cd`). |
