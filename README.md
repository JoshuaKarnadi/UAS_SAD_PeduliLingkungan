# PeduliLingkungan

Aplikasi web pelaporan masalah lingkungan dan koordinasi kegiatan *volunteer* berbasis komunitas yang dibangun menggunakan **PHP Native** dan **PostgreSQL** dengan penerapan kontrol keamanan **Secure SDLC** (OWASP Top 10:2025).

## Panduan Instalasi & Menjalankan dari Nol (Windows)

### 1. Prasyarat Sistem

Pastikan perangkat lunak berikut sudah terpasang di Windows:

* **PHP 8.2+** (Standalone, tanpa XAMPP)

* **PostgreSQL 14+** (beserta `pgAdmin 4` atau `psql`)

* **Git**

### 2. Daftarkan PHP ke Environment Variables (PATH) Windows

1. Ekstrak folder instalasi PHP Anda (misalnya ke `C:\php`).

2. Tekan tombol **Windows**, ketik **Edit the system environment variables**, lalu buka.

3. Klik **Environment Variables...** $\rightarrow$ pada bagian **System variables**, pilih **`Path`**, lalu klik **Edit...**.

4. Klik **New**, masukkan lokasi folder PHP Anda (`C:\php`), lalu klik **OK** pada semua jendela.

5. Buka terminal baru dan pastikan PHP sudah terbaca:

   ```
   php -v
   
   ```

### 3. Konfigurasi `php.ini`

1. Masuk ke folder instalasi PHP (`C:\php`), salin berkas `php.ini-development` lalu ubah namanya menjadi **`php.ini`**.

2. Buka `php.ini`, aktifkan ekstensi berikut dengan menghapus tanda titik koma (`;`) di awal baris:

   ```
    extension=fileinfo
    extension=gd
    extension=mbstring
    extension=exif
    extension=pdo_pgsql
   
   ```

3. Sembunyikan versi PHP pada *HTTP response header*:

   ```
   expose_php = Off
   
   ```

4. Simpan perubahan (`Ctrl + S`).

### 4. Clone Repositori Proyek

Buka terminal pada direktori induk tempat Anda ingin menyimpan proyek (misalnya `C:\Projects\`), lalu jalankan:

```
git clone https://github.com/JoshuaKarnadi/SAD-.git

```

### 5. Konfigurasi Basis Data PostgreSQL (*Least Privilege*)

1. Buka **pgAdmin 4** (atau `psql` menggunakan akun `postgres`).

2. Buat database baru bernama **`db_pedulilingkungan`**.

3. Impor skema tabel (`users`, `laporan`, `laporan_participants`, `activity_logs`) dan akun peran aplikasi terbatas (**`app_pedulilingkungan`**) dari berkas `.sql` yang tersedia di folder repositori `SAD-`.
   *(Akun `app_pedulilingkungan` menerapkan prinsip least privilege agar tidak dapat melakukan `UPDATE` maupun `DELETE` pada tabel audit `activity_logs`)*.

### 6. Buat Berkas `.env` di Luar Folder Proyek

Buat satu berkas bernama **`.env`** sejajar di luar folder `SAD-` agar kredensial tidak berada di dalam *web root*:

```
Folder_Induk/
├── SAD-/          <-- Folder repositori proyek
└── .env           <-- Letakkan file .env persis di luar folder SAD-

```

Isi berkas **`.env`** tersebut dan sesuaikan `DB_USER` serta `DB_PASS` Anda:

```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=db_pedulilingkungan
DB_USER=app_pedulilingkungan
DB_PASS=ganti_dengan_password_anda

```

### 7. Menjalankan Server Aplikasi

Masuk ke dalam folder proyek `SAD-`:

```
cd SAD-

```

Pilih salah satu mode berikut (wajib memakai `router.php` agar pemblokiran berkas sensitif dan *HTTP security headers* aktif):

* **Opsi A — Akses dari PC/Laptop Saja (`localhost`):**

  ```
  php -S localhost:8000 router.php
  
  ```

  Buka peramban dan akses: **`http://localhost:8000`**

* **Opsi B — Akses dari HP & Perangkat Lain dalam Satu Jaringan Wi-Fi:**

  ```
  php -S 0.0.0.0:8000 router.php
  
  ```

  Cek IPv4 PC Anda lewat `ipconfig` (misalnya `192.168.1.10`), lalu akses lewat peramban HP: **`http://192.168.1.10:8000`**

  ### 8. Akun Pengujian Admin
Jika berkas `.sql` yang diimpor sudah menyertakan data awal (*seeder*), Anda dapat langsung masuk sebagai **Admin** menggunakan kredensial berikut:
* **Email:** `tes@gmail.com`
* **Password:** `qwerty123`

**Jika akun di atas belum tersedia di basis data:**
1. Lakukan registrasi akun baru terlebih dahulu melalui halaman **Register** di aplikasi (misalnya menggunakan email `tes@gmail.com`).
2. Buka **pgAdmin 4** atau `psql` (menggunakan akun superuser `postgres`), lalu ubah peran pengguna tersebut menjadi `admin` pada tabel `users`:
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'tes@gmail.com';