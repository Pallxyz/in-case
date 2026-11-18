# InCase

Aplikasi web untuk memastikan **tidak ada barang yang tertinggal**. Barang ditempeli stiker RFID, lalu dipindai ke box sebelum berangkat dan sebelum pulang. Cocok untuk sekolah, traveling, hiking, atau kegiatan apa pun.

Satu jenis pengguna saja: tanpa peran admin atau guru.

## Cara kerja

1. **Daftarkan barang** (menu Barang) dan pasang stiker RFID. Tandai barang berharga.
2. **Buat kegiatan** (menu Kegiatan), misalnya "Hiking Gunung", lalu pilih barang yang harus dibawa. Jadikan kegiatan itu aktif.
3. **Scan berangkat:** pilih mode di menu Pindai, lalu tempelkan barang ke box. Aplikasi menampilkan yang sudah dan yang masih kurang.
4. **Scan pulang:** pindai lagi saat pulang. Barang yang belum kembali dijelaskan: dipinjam/dititipkan atau hilang. Barang berharga yang hilang wajib diberi keterangan kapan dan di mana terakhir terlihat.
5. Riwayat scan bisa dilihat dan diekspor ke CSV. Pengingat harian dikirim jam 06.00 dan 19.00 untuk kegiatan hari ini atau besok.

## Arsitektur

```
Stiker RFID -> ESP32 + pembaca RFID -> broker MQTT -> php artisan mqtt:listen -> database -> web
```

- Backend: Laravel, Blade, Tailwind CSS, Alpine.js
- Database: MySQL (SQLite untuk tes)
- Perangkat: ESP32 mengirim UID stiker lewat MQTT (listener `mqtt:listen`) atau HTTP `POST /api/scan` dengan header `X-API-KEY`
- Tampilan mobile-first: tab navigasi bawah di HP, sidebar di layar lebar

## Menjalankan di komputer sendiri

Butuh PHP 8.3+, Composer, Node.js, dan MySQL.

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

Isi `DB_*` dan `MQTT_*` di `.env`, lalu:

```bash
php artisan migrate
php artisan serve
```

Proses yang berjalan terus (terminal terpisah):

```bash
php artisan mqtt:listen      # menerima scan dari perangkat
php artisan schedule:work    # pengingat harian
```

Variabel MQTT di `.env`: `MQTT_HOST`, `MQTT_PORT`, `MQTT_USER`, `MQTT_PASS`, `MQTT_TOPIC`.

## Tes

```bash
php artisan test
```

Tes memakai SQLite di memori, jadi ekstensi PHP `pdo_sqlite` harus aktif.

## Catatan

- Satu pengguna hanya punya satu kegiatan aktif pada satu waktu.
- Hosting online butuh server yang bisa menjalankan PHP dan proses latar (VPS). Vercel dan Netlify tidak cocok.