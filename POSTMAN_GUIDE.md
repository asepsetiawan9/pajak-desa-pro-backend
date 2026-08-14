# 🚀 Panduan Penggunaan Postman API — LENTERA (Layanan Elektronik Terpadu Pajak Daerah) (v2.3.0 - Updated 2026)

Dokumentasi dan koleksi API Postman ini dibuat secara resmi untuk memudahkan seluruh tim pengembang (*backend/frontend/mobile/QA/integrator*) dalam menguji, mengembangkan, dan memverifikasi endpoint REST API **LENTERA (Layanan Elektronik Terpadu Pajak Daerah)**.

---

## 📁 Berkas Postman Terbaru

Seluruh berkas Postman tersimpan pada folder `pajak-backend/postman/`:

1. 📄 **Postman Collection v2.3.0**:  
   `pajak-backend/postman/LENTERA_Pajak_Desa_Pro.postman_collection.json`
2. ⚙️ **Postman Local Environment**:  
   `pajak-backend/postman/LENTERA_Local.postman_environment.json`
3. 🌐 **Postman Production VPS Environment**:  
   `pajak-backend/postman/LENTERA_Production_VPS.postman_environment.json`

---

## 🛠️ Cara Impor ke Postman / Bruno / Insomnia

1. Buka aplikasi **Postman** (atau API Client favorit Anda seperti Insomnia / Bruno).
2. Klik tombol **Import** di pojok kiri atas.
3. Drag & Drop berkas `LENTERA_Pajak_Desa_Pro.postman_collection.json` dan `LENTERA_Production_VPS.postman_environment.json` (atau `LENTERA_Local.postman_environment.json`).
4. Di pojok kanan atas Postman, pilih Environment **"LENTERA (Layanan Elektronik Terpadu Pajak Daerah) - VPS Production"** (atau **Local**).

### 🌐 Variabel URL Backend yang Tersedia:
- `{{baseUrl}}` ➔ `http://backend.barudua.initd.web.id/api/v1` *(Domain Resmi Produksi)*
- `{{baseUrl_direct}}` ➔ `http://36.64.200.242:8827/api/v1` *(Direct Port VPS)*
- Local: `http://127.0.0.1:8000/api/v1` *(Laravel Development Server)*

---

## 🔑 Kredensial Pengujian (Seeded Accounts)

Gunakan kredensial berikut pada folder request **`01. Authentication & Health -> Login`**:

| Role | Username | Password | Akses Dusun / Desa | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| **SUPER_ADMIN_SYSTEM** | `superadmin` | `superadmin123` | `ALL` (Lintas Desa) | Mengelola seluruh Desa, User & Master Multi-Tenant se-Kecamatan |
| **SUPER_ADMIN** | `admin.desa` | `admin123` | `ALL` (Desa Barudua) | Akses penuh ke seluruh fitur, DHKP & dusun di Desa Barudua |
| **KOLEKTOR** | `kolektor.balok` | `password123` | `BALOK, CIDERES` | Data otomatis tersaring ke dusun Balok & Cideres |
| **KOLEKTOR** | `kolektor.cideres` | `password123` | `CIDERES` | Data otomatis tersaring ke dusun Cideres |
| **KOLEKTOR** | `kolektor.puncak` | `password123` | `PUNCAK SARI, CIPEDES` | Data otomatis tersaring ke dusun Puncak Sari & Cipedes |
| **KEPALA_DESA** | `kades.barudua` | `password123` | `ALL` (Desa Barudua) | Mode monitoring executive, ekspor laporan & catatan pengeluaran kas |

---

## ⚡ Otomatisasi Token Sanctum (Bearer Auth)

Koleksi Postman ini sudah dilengkapi dengan **Test Script Otomatis** pada endpoint Login:
- Setiap kali Anda melakukan request `POST /api/v1/auth/login`, token `access_token` yang didapat dari server akan **secara otomatis disimpan** ke environment variable `{{token}}`.
- Seluruh request di folder lainnya secara otomatis menggunakan header:
  ```http
  Authorization: Bearer {{token}}
  Accept: application/json
  Content-Type: application/json
  ```

---

## 📌 Ringkasan Endpoint API (v1) — 50 Endpoints dalam 10 Folder

### 1. Authentication & Health (`/api/v1/auth`, `/api/v1/health`) (7 Requests)
- `GET /api/v1/health` — Cek status server API & waktu realtime
- `POST /api/v1/auth/login` — Autentikasi masuk (Super Admin System, Admin Desa, Kolektor, Kades)
- `GET /api/v1/auth/me` — Mengambil data profil akun yang sedang login & detail Desa
- `POST /api/v1/auth/logout` — Logout dan hapus token aktif

### 2. Multi-Desa Management (Super Admin System) (`/api/v1/desas`) (5 Requests)
- `GET /api/v1/desas` — Daftar seluruh Desa (Dukungan query `search`)
- `POST /api/v1/desas` — Tambah Desa baru & **Otomatis Generate 3 Akun Default** (Admin Desa, Kolektor, Kades)
- `GET /api/v1/desas/{id}` — Detail 1 data Desa
- `PUT /api/v1/desas/{id}` — Update profil Desa (Kades, NIP, Subdomain, Kode Desa)
- `PATCH /api/v1/desas/{id}/toggle-status` — Toggle status aktif/non-aktif Desa

### 3. User Management & RBAC (`/api/v1/users`) (5 Requests)
- `GET /api/v1/users` — Daftar seluruh user (Filter `role`, `desa_id`, `search`)
- `POST /api/v1/users` — Tambah user baru
- `PUT /api/v1/users/{id}` — Update data user & penugasan multi-dusun
- `PATCH /api/v1/users/{id}/toggle-status` — Toggle status aktif/non-aktif user
- `DELETE /api/v1/users/{id}` — Hapus user dari sistem

### 4. DHKP & Master SPPT (`/api/v1/dhkp`) (7 Requests)
- `GET /api/v1/dhkp/summary` — Ringkasan Kartu KPI & Capaian Dusun (Filter `desa_id`, `tahun`)
- `GET /api/v1/dhkp` — Data SPPT terhalaman (Filter `desa_id`, `status_bayar`, `dusun`, `search`, `tahun`)
- `GET /api/v1/dhkp/{id}` — Detail 1 record SPPT DHKP
- `POST /api/v1/dhkp` — Tambah SPPT manual
- `PUT /api/v1/dhkp/{id}` — Update data SPPT
- `POST /api/v1/dhkp/import` — Import masal data SPPT (Bulk Import Excel/CSV)
- `DELETE /api/v1/dhkp/{id}` — Hapus record SPPT

### 5. Transactions & Kasir STTS (`/api/v1/transactions`) (7 Requests)
- `GET /api/v1/transactions` — Riwayat transaksi pembayaran STTS terhalaman (Filter `desa_id`, `dusun`, `tahun`)
- `GET /api/v1/transactions/{id}` — Detail transaksi STTS & rincian SPPT DHKP
- `POST /api/v1/transactions/pay` — Proses pembayaran Single NOP maupun Multi-NOP (Kolektif 1 KK / Luar Desa)
- `POST /api/v1/transactions/{id}/void` — Pembatalan (Void) transaksi STTS & auto-rollback status DHKP ke `BELUM_BAYAR`
- `POST /api/v1/transactions/group` — Mengelompokkan transaksi 1 KK (Keluarga)
- `POST /api/v1/transactions/ungroup` — Membubarkan kelompok 1 KK

### 6. Reports (`/api/v1/reports`) (2 Requests)
- `GET /api/v1/reports/21-column` — Laporan Rekapitulasi 21-Kolom PBB-P2 per RW/Dusun (Filter `desa_id`, `tahun`, `buku`)

### 7. System Settings (`/api/v1/settings`) (2 Requests)
- `GET /api/v1/settings` — Mengambil data konfigurasi sistem per Desa (`desa_id`)
- `POST /api/v1/settings` — Memperbarui pengaturan sistem & Pejabat Penandatangan Dinamis (Camat/Kades/Bendahara)

### 8. Pengeluaran Kas & Setoran ke Kecamatan (`/api/v1/setoran-kecamatan`) (8 Requests)
- `GET /api/v1/setoran-kecamatan/summary` — Summary KPI Realisasi, Total Disetor, Pengeluaran Internal, Sisa Kas & Rekap Per Desa
- `GET /api/v1/setoran-kecamatan` — Daftar Riwayat Setoran & Pengeluaran Kas (Filter `desa_id`, `status`, `kategori`, `tahun`, `search`)
- `GET /api/v1/setoran-kecamatan/{id}` — Detail Record Pengeluaran Kas / Setoran
- `POST /api/v1/setoran-kecamatan` *(Setor Kecamatan)* — Input Setoran Kas PBB-P2 ke Kas Kecamatan (`kategori: SETOR_KECAMATAN`, butuh verifikasi)
- `POST /api/v1/setoran-kecamatan` *(Pengeluaran Internal)* — Catat Pengeluaran Internal Desa (`kategori: OPERASIONAL_DESA` / `KEGIATAN_DESA`, auto-diterima)
- `PUT /api/v1/setoran-kecamatan/{id}` — Update / Edit Record Pengeluaran Kas
- `POST /api/v1/setoran-kecamatan/{id}/verify` — Verifikasi Setoran oleh Kecamatan (Status `DITERIMA`, `DITOLAK`, atau `PENDING`)
- `DELETE /api/v1/setoran-kecamatan/{id}` — Hapus Record Pengeluaran Kas / Setoran

### 9. Master Dusun Management (`/api/v1/dusuns`) (7 Requests)
- `GET /api/v1/dusuns` — Daftar Master Dusun per Desa (Filter `desa_id`, `search`, `status_aktif`)
- `GET /api/v1/dusuns?format=names` — Ambil Array String Nama Dusun untuk Dropdown/Selector Form
- `POST /api/v1/dusuns` — Tambah Master Dusun Baru terisolasi per Desa
- `GET /api/v1/dusuns/{id}` — Detail Data Master Dusun
- `PUT /api/v1/dusuns/{id}` — Update Nama Dusun, Kode Dusun, dan RT/RW
- `PATCH /api/v1/dusuns/{id}/toggle-status` — Toggle Status Aktif/Nonaktif Dusun
- `DELETE /api/v1/dusuns/{id}` — Hapus Master Dusun

### 10. Audit Logs & Activity Trail (`/api/v1/audit-logs`) (1 Request)
- `GET /api/v1/audit-logs` — Riwayat Log Aktivitas Sistem (Filter `desa_id`, `action`, `module`, `per_page`)

---

## 🧪 Contoh Payload Request & Response Lengkap

### Contoh 1: Tambah Master Dusun Baru (`POST /api/v1/dusuns`)
**Body Request:**
```json
{
  "nama_dusun": "Dusun Sindangsari",
  "desa_id": 1,
  "kode_dusun": "SDG01",
  "rt_rw": "RT 01-04 / RW 02",
  "status_aktif": true
}
```
**Response 201 Created:**
```json
{
  "success": true,
  "message": "Master Dusun 'Dusun Sindangsari' berhasil ditambahkan.",
  "data": {
    "id": 4,
    "desa_id": 1,
    "nama_dusun": "Dusun Sindangsari",
    "kode_dusun": "SDG01",
    "rt_rw": "RT 01-04 / RW 02",
    "status_aktif": true
  }
}
```

---

### Contoh 2: Catat Pengeluaran Internal Desa (`POST /api/v1/setoran-kecamatan`)
**Body Request:**
```json
{
  "kategori": "OPERASIONAL_DESA",
  "tanggal_setor": "2026-08-14",
  "tahun": 2026,
  "nominal": 1500000,
  "metode_setoran": "TUNAI",
  "penyetor_nama": "Asep Setiawan",
  "penyetor_jabatan": "Bendahara Desa",
  "catatan_desa": "Biaya transport kolektor dan pengadaan form cetak STTS"
}
```
**Response 201 Created (Auto Diterima Tanpa Verifikasi Kecamatan):**
```json
{
  "success": true,
  "status": "success",
  "message": "Data pengeluaran / setoran kas berhasil disimpan.",
  "data": {
    "id": 4,
    "desa_id": 1,
    "kategori": "OPERASIONAL_DESA",
    "nomor_bukti": "STR/3205042002/20260814/OP99",
    "tanggal_setor": "2026-08-14",
    "tahun": 2026,
    "nominal": 1500000,
    "metode_setoran": "TUNAI",
    "penyetor_nama": "Asep Setiawan",
    "penyetor_jabatan": "Bendahara Desa",
    "catatan_desa": "Biaya transport kolektor dan pengadaan form cetak STTS",
    "perlu_verifikasi_kecamatan": false,
    "status": "DITERIMA",
    "tanggal_diterima": "2026-08-14 19:00:00"
  }
}
```

---

### Contoh 3: Ambil Daftar Nama Dusun Dropdown (`GET /api/v1/dusuns?format=names&desa_id=1`)
**Response 200 OK:**
```json
{
  "success": true,
  "data": [
    "BALOK",
    "CIDERES",
    "CIPEDES",
    "PUNCAK SARI",
    "Dusun Sindangsari"
  ]
}
```

---

### Contoh 4: Monitoring Audit Log Sistem (`GET /api/v1/audit-logs?per_page=15&desa_id=1`)
**Response 200 OK:**
```json
{
  "success": true,
  "message": "Audit logs berhasil dimuat",
  "data": [
    {
      "id": 101,
      "user_id": 2,
      "action": "CREATE_SETORAN",
      "module": "SETORAN_KECAMATAN",
      "payload": {
        "setoran_id": 3,
        "nomor_bukti": "STR/3205042002/20260814/K9L1",
        "nominal": 5000000,
        "desa_id": 1
      },
      "ip_address": "127.0.0.1",
      "created_at": "2026-08-14T19:05:00.000000Z",
      "user": {
        "id": 2,
        "name": "Asep Setiawan, S.Kom",
        "username": "admin.desa",
        "role": "SUPER_ADMIN"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```
