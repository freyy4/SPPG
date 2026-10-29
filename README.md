# SPPG

Aplikasi pengelolaan purchase order dan operasional SPPG, termasuk perhitungan pembelian, kuantitas gudang, biaya, pembayaran, dan analisa keuntungan.

<!-- APP-VERSION:START -->
**Versi pencatatan:** `v0.1.14`  
**Terakhir diperbarui:** 2026-10-29 18:00:05 WIB
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
| 2026-10-29 18:00:05 WIB | v0.1.14 | Update tampilan, pengujian, dashboard (8 file; 2 ditambah, 6 diubah). <!-- snapshot:b6db7cd37d71e89777c48c2b39e5587d7ce7d7a197fa2a52242e443dd7d649fc --> |
| 2026-10-29 17:55:05 WIB | v0.1.13 | Update tampilan, pengujian, dashboard dan 1 bagian lainnya (14 file; 7 ditambah, 7 diubah). <!-- snapshot:d1ef40f9b5fd22fdebb865de84b7fe9c02ffe6e681a1829f35e6c2f2d3a6f000 --> |
| 2026-10-29 17:50:04 WIB | v0.1.12 | Update pengujian, tampilan, dashboard dan 1 bagian lainnya (6 file; 4 ditambah, 2 diubah). <!-- snapshot:7df6948af754f338e5a7e863af73db8165ff1833f908ed629134bcd6da7f2edf --> |
| 2026-10-29 04:40:05 WIB | v0.1.11 | Update tampilan, master data, dashboard dan 3 bagian lainnya (15 file; 11 ditambah, 4 diubah). <!-- snapshot:d06fc5500a468d1da7a4d01b7eddafc26c189a58dfd98211cacde062112bce76 --> |
| 2026-10-29 02:05:04 WIB | v0.1.10 | Update tampilan, dashboard, pengujian dan 2 bagian lainnya (17 file; 11 ditambah, 6 diubah). <!-- snapshot:b47e9037b15beee1d2cf35377458181c193acbc8e429da1035d4b46cd6b68788 --> |
| 2026-10-28 20:50:04 WIB | v0.1.9 | Update tampilan, dokumentasi, pengujian (4 file; 1 ditambah, 3 diubah). <!-- snapshot:b4f4623e47ef866e8201c08433585f82e5a2b0415b12008b3f861c0f29eae663 --> |
| 2026-10-28 20:45:04 WIB | v0.1.8 | Update pembelian dan gudang, pengujian, tampilan (3 file; 3 diubah). <!-- snapshot:7a2719878894ebe3f8b6f56c0bf2d7e9fd18aac7c8b1d7ec723324b4c5521fd6 --> |
| 2026-10-28 20:40:05 WIB | v0.1.7 | Update tampilan, dokumentasi, pengujian (4 file; 1 ditambah, 3 diubah). <!-- snapshot:adc30b52e44919486a1dd35e01568c3e9aae275f56b4e15aa3e8e8165227ef84 --> |
| 2026-10-28 20:35:04 WIB | v0.1.6 | Update pembelian dan gudang, pengujian, tampilan (3 file; 3 diubah). <!-- snapshot:9f4356119be14d9fbdd89a57958d91eeef091aefbd8a230e3b9d33d45c3173dd --> |
| 2026-10-28 20:30:04 WIB | v0.1.5 | Update tampilan, dashboard, pengujian (7 file; 7 diubah). <!-- snapshot:ef1e47f8c5d5dbd8acd6c47e1f2dbfeab5bcd9d6858f6483e930357c50149674 --> |
| 2026-10-28 20:25:04 WIB | v0.1.4 | Update tampilan, dashboard (4 file; 4 diubah). <!-- snapshot:ca6ac6251c42f75a0597fc4d4173226d5187d684ec6386b096fe6c8eb0281d10 --> |
| 2026-10-28 20:15:04 WIB | v0.1.3 | Update tampilan, dashboard, pembelian dan gudang dan 1 bagian lainnya (14 file; 13 ditambah, 1 diubah). <!-- snapshot:eb434e2474a93e19965a2d687b5ba90034306ab6484a5e24ff6a1195ee7dc9be --> |
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
