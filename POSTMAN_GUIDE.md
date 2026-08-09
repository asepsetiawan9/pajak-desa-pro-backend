# 🚀 Panduan Penggunaan Postman API — LENTERA (Layanan Elektronik Terpadu Pajak Daerah) (v1)

Dokumentasi dan koleksi API Postman ini dibuat secara resmi untuk memudahkan seluruh tim pengembang (*backend/frontend/QA/integrator*) dalam menguji, mengembangkan, dan memverifikasi endpoint REST API **LENTERA (Layanan Elektronik Terpadu Pajak Daerah)**.

---

## 📁 Berkas yang Disediakan

Semua berkas Postman tersimpan pada folder `pajak-backend/postman/`:

1. 📄 **Postman Collection v2.1.0**:  
   `pajak-backend/postman/LENTERA_Pajak_Desa_Pro.postman_collection.json`
2. ⚙️ **Postman Environment File**:  
   `pajak-backend/postman/LENTERA_Local.postman_environment.json`

---

## 🛠️ Cara Impor ke Postman / Bruno / Insomnia

1. Buka aplikasi **Postman** (atau API Client favorit Anda seperti Insomnia / Bruno).
2. Klik tombol **Import** di pojok kiri atas.
3. Drag & Drop atau pilih berkas `LENTERA_Pajak_Desa_Pro.postman_collection.json` dan `LENTERA_Local.postman_environment.json`.
4. Di pojok kanan atas Postman, pilih Environment **"LENTERA (Layanan Elektronik Terpadu Pajak Daerah) - Local"**.

---

## 🔑 Kredensial Pengujian (Seeded Accounts)

Gunakan kredensial berikut pada request **`01. Authentication -> 1. Login`**:

| Role | Username | Password | Akses Dusun | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| **SUPER_ADMIN** | `admin.desa` | `admin123` | `ALL` | Akses penuh ke seluruh fitur & dusun |
| **BENDAHARA** | `bendahara.pbb` | `password123` | `ALL` | Mengelola transaksi, kasir STTS & laporan |
| **KOLEKTOR** | `kolektor.balok` | `password123` | `BALOK, CIDERES` | Data otomatis tersaring ke dusun penugasan |
| **KOLEKTOR** | `kolektor.cideres` | `password123` | `CIDERES` | Data otomatis tersaring hanya dusun Cideres |
| **KEPALA_DESA** | `kades.barudua` | `password123` | `ALL` | Mode monitoring & rekapitulasi |

---

## ⚡ Otomatisasi Token Sanctum (Bearer Auth)

Koleksi Postman ini sudah dilengkapi dengan **Test Script Otomatis** pada endpoint Login:
- Setiap kali Anda melakukan request `POST /api/v1/auth/login`, token `access_token` yang didapat dari server akan **secara otomatis disimpan** ke environment variable `{{token}}`.
- Seluruh request di folder lainnya (User Management, DHKP, Transactions, Reports, Settings) secara otomatis menggunakan header:
  ```http
  Authorization: Bearer {{token}}
  Accept: application/json
  Content-Type: application/json
  ```

---

## 📌 Ringkasan Endpoint API (v1)

### 1. Authentication (`/api/v1/auth`)
- `POST /api/v1/auth/login` — Autentikasi masuk pengguna
- `GET /api/v1/auth/me` — Mengambil data akun yang sedang login
- `POST /api/v1/auth/logout` — Logout dan hapus token aktif

### 2. User Management & RBAC (`/api/v1/users`)
- `GET /api/v1/users` — Daftar seluruh user (bisa filter `role` & `search`)
- `POST /api/v1/users` — Tambah user baru (Super Admin, Bendahara, Kolektor, Kades)
- `PUT /api/v1/users/{id}` — Update data user & penugasan multi-dusun
- `PATCH /api/v1/users/{id}/toggle-status` — Toggle status aktif/non-aktif user
- `DELETE /api/v1/users/{id}` — Hapus user dari sistem

### 3. DHKP & Master SPPT (`/api/v1/dhkp`)
- `GET /api/v1/dhkp/summary` — Ringkasan Kartu KPI (Total PBB, Lunas, Piutang, Capaian %)
- `GET /api/v1/dhkp` — Data SPPT terhalaman (Filter `search`, `dusun`, `status_bayar`, `tahun`)
- `GET /api/v1/dhkp/{id}` — Detail 1 record SPPT DHKP
- `POST /api/v1/dhkp` — Tambah SPPT manual
- `PUT /api/v1/dhkp/{id}` — Update data SPPT
- `POST /api/v1/dhkp/import` — Import masal data SPPT (Bulk Import)
- `DELETE /api/v1/dhkp/{id}` — Hapus record SPPT

### 4. Transactions & Kasir STTS (`/api/v1/transactions`)
- `GET /api/v1/transactions` — Riwayat transaksi pembayaran STTS terhalaman
- `GET /api/v1/transactions/{id}` — Detail transaksi STTS
- `POST /api/v1/transactions/pay` — Proses pembayaran STTS & otomatis ubah status DHKP ke `LUNAS`
- `POST /api/v1/transactions/{id}/void` — Pembatalan (Void) transaksi STTS & rollback status DHKP ke `BELUM_BAYAR`
- `POST /api/v1/transactions/group` — Mengelompokkan transaksi 1 KK (Keluarga)
- `POST /api/v1/transactions/ungroup` — Membubarkan kelompok 1 KK

### 5. Reports (`/api/v1/reports`)
- `GET /api/v1/reports/21-column` — Laporan Rekapitulasi 21-Kolom PBB-P2 per RW/Dusun hingga Grand Total Desa

### 6. System Settings (`/api/v1/settings`)
- `GET /api/v1/settings` — Mengambil data konfigurasi sistem
- `POST /api/v1/settings` — Memperbarui pengaturan sistem secara masal

---

## 🧪 Contoh Payload Request & Response

### Contoh 1: Login User (`POST /api/v1/auth/login`)
**Body Request:**
```json
{
  "username": "admin.desa",
  "password": "admin123"
}
```
**Response 200 OK:**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "access_token": "1|laravel_sanctum_token_example_abcdef123456",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Asep Setiawan, S.Kom",
      "username": "admin.desa",
      "email": "admin@barudua.desa.id",
      "role": "SUPER_ADMIN",
      "dusun_akses": "ALL",
      "status_aktif": true
    }
  }
}
```

---

### Contoh 2: Proses Pembayaran STTS (`POST /api/v1/transactions/pay`)
**Body Request:**
```json
{
  "spptId": 1,
  "dhkp_row_id": 1,
  "jumlah_bayar": 175000,
  "denda": 0,
  "fee_kolektor": 3500,
  "metode_pembayaran": "CASH",
  "nama_pembayar": "Sutrisno",
  "catatan": "Pembayaran lunas via Kolektor Balok"
}
```
**Response 201 Created:**
```json
{
  "success": true,
  "message": "Pembayaran Kasir STTS berhasil diproses",
  "data": {
    "id": 102,
    "no_transaksi": "TRX-20260806-0002",
    "dhkp_row_id": 1,
    "jumlah_bayar": 175000,
    "denda": 0,
    "fee_kolektor": 3500,
    "metode_pembayaran": "CASH",
    "nama_pembayar": "Sutrisno",
    "status": "SUCCESS"
  }
}
```

---

## 🔒 Uji Coba Penyaringan Multi-Dusun (Collector Isolation)

1. Lakukan login sebagai **`kolektor.balok`** (dusun penugasan: `BALOK, CIDERES`).
2. Panggil endpoint `GET /api/v1/dhkp`.
3. Perhatikan bahwa seluruh data SPPT yang dikembalikan oleh backend **otomatis disaring** hanya untuk dusun Balok dan Cideres saja!
4. Jika `kolektor.balok` mencoba mengakses `GET /api/v1/dhkp/{id}` untuk SPPT di luar wilayahnya, server akan merespon dengan **`403 Forbidden`**:
```json
{
  "success": false,
  "message": "Akses ditolak: Data SPPT berada di luar wilayah dusun penugasan Anda."
}
```

---

## 🎯 Penutup & Catatan
Seluruh endpoint di atas sudah siap diuji pada lingkungan lokal (`http://127.0.0.1:8000/api/v1`). Jika server backend Anda berjalan di port lain, cukup sesuaikan variabel `baseUrl` pada Postman Environment.
