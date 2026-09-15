# PRODUCT REQUIREMENT DOCUMENT (PRD)

## SoundTix — Enterprise Digital Concert Ticketing Platform

**Document Version:** 1.1.0-REVISED  

**Status:** Approved for Development  

**Target Architecture:** Decoupled Single Page Application (React) & RESTful API (Laravel)  

---

## 1. PROJECT OVERVIEW & GOALS

### 1.1. Background & Vision

SoundTix adalah platform *e-ticketing* konser digital modern yang dirancang untuk mengelola seluruh siklus pemesanan tiket: dari penemuan acara, pengelolaan inventaris tiket berkinerja tinggi, simulasi pembayaran instan, hingga validasi tiket *real-time* di lokasi *venue* menggunakan QR Code terenkripsi.

### 1.2. Key Business Metrics (KPIs)

* **Zero Overselling Rate:** 100% pencegahan alokasi tiket ganda saat lonjakan transaksi (*ticket war*).

* **High Transaction Throughput:** Pemrosesan reservasi tiket paralel tanpa menyebabkan kebocoran kuota (*race condition*).

* **Scan Latency:** Proses validasi QR Code di venue kurang dari 1 detik per tiket.

* **Audit Trail Accuracy:** Rekonsiliasi data keuangan 100% presisi antara transaksi pesanan, rincian item, dan status pembayaran.

---

## 2. USER PERSONAS & ROLE ACCESS MATRIX (RBAC)

### 2.1. System Roles

1. **Customer:** Mencari event, memesan tiket, melakukan pembayaran simulasi, dan menggunakan e-ticket QR Code.

2. **Event Organizer:** Membuat event, mengelola kategori dan kuota tiket, serta memantau analisis penjualan.

3. **Gatekeeper:** Petugas lapangan yang memvalidasi QR Code e-ticket saat penonton masuk *gate*.

4. **System Admin:** Pengelola utama platform untuk verifikasi event (*approval*) dan manajemen seluruh user. **(Akun khusus berbasis whitelist email yang ditentukan pemilik sistem)**.

### 2.2. Role Access Control Matrix

| Fitur / Modul System | Customer | Event Organizer | Gatekeeper | System Admin |

| :--- | :---: | :---: | :---: | :---: |

| Self Register (Publik) | Yes | Yes | No | **No (Khusus)** |

| Dedicated Email Login | Yes | Yes | Yes | **Yes (Whitelist)** |

| Browse & Search Published Events | Yes | Yes | Yes | Yes |

| Lock Stock & Checkout Ticket | Yes | No | No | No |

| Simulate Payment Execution | Yes | No | No | No |

| Access Personal E-Ticket & QR | Yes | No | No | No |

| Create & Draft New Event | No | Yes | No | Yes |

| Manage Ticket Categories & Stock | No | Yes | No | Yes |

| View Organizer Sales Analytics | No | Yes | No | Yes |

| Access Gate Scanner Interface | No | No | Yes | Yes |

| Validate QR Code Ticket Status | No | No | Yes | Yes |

| Approve / Reject Submitted Event | No | No | No | Yes |

| Global User & Role Management | No | No | No | Yes |

---

## 3. SYSTEM ARCHITECTURE & TECHNICAL STRATEGY

### 3.1. Tech Stack

* **Backend:** Laravel RESTful API (PHP 8.2+) dengan arsitektur *Service-Repository Pattern*.

* **Frontend:** React (Vite) Single Page Application (SPA) + Tailwind CSS.

* **Authentication:** Stateful/Stateless Bearer Token via Laravel Sanctum.

* **Database:** MySQL 8.0+ (Engine InnoDB - ACID Compliant).

### 3.2. Stock Concurrency & Inventory Locking

Untuk mencegah **Overselling** saat lonjakan transaksi (*ticket war*):

1. Saat Customer menekan tombol *Checkout*, sistem memulai **Database Transaction**.

2. Kuota tiket diperiksa menggunakan **Pessimistic Locking** (`SELECT ... FOR UPDATE`).

3. Jika kuota mencukupi, `available_quantity` dikurangi secara *atomic* dan pesanan berstatus `PENDING` dibuat.

4. Sistem memasang `expires_at` (Holding Time 15 Menit).

5. Jika pembayaran tidak diselesaikan dalam 15 menit, *Scheduled Task / Cron Job* otomatis mengembalikan kuota tiket (*Release Stock*).

### 3.3. Payment Abstraction Layer (Adapter Pattern)

Menggunakan interface `PaymentGatewayInterface` dengan implementasi default `MockPaymentDriver`. Saat integrasi ke payment gateway asli (Midtrans/Xendit), sistem cukup menambahkan driver baru tanpa mengubah logika bisnis di Service Layer.

---

## 4. FUNCTIONAL REQUIREMENTS

* **EPIC 1: Authentication & Restricted Admin Access**

  * Self-register publik hanya terbuka untuk role `customer` dan `organizer`. Registrasi publik untuk role `admin` ditutup total.

  * Akun Admin dibuat secara khusus via Database Seeder / Environment Configuration (`ADMIN_EMAIL`) menggunakan alamat Gmail khusus dari pemilik sistem.

  * Login Admin memvalidasi kredensial password dan melakukan pengecekan match ke whitelist email Admin.

  * Hashing password aman menggunakan Bcrypt.

  * Reset password via token sementara.

* **EPIC 2: Event Discovery & Showcase**

  * Homepage memiliki **header/navigation bar** berisi: Logo SoundTix, Search bar pencarian manual, tombol Masuk, dan tombol Daftar.

  * Search bar menggunakan pencarian manual berbasis kata kunci (nama event, artis/performer, atau lokasi). Hasil pencarian menampilkan event yang relevan berdasarkan keyword yang dimasukkan.

  * Tidak menggunakan filter berdasarkan kota, tanggal, atau kategori pada halaman discovery.

  * Homepage menampilkan **poster/banner konser** sebagai visual utama event melalui **cover transition / hero carousel** yang menampilkan beberapa event unggulan secara bergantian. Setiap poster menampilkan nama event, artis/performer, tanggal event, lokasi, dan tombol Lihat Detail.

  * Transition berjalan dengan halus dan nyaman digunakan baik di desktop maupun mobile. Poster menggunakan rasio dan ukuran responsif agar tidak terpotong secara tidak proporsional di berbagai ukuran layar.

  * **Footer & Informational Navigation** tersedia di seluruh halaman publik dan terdiri dari:
    * **Jelajahi SoundTix:** Tentang SoundTix, Mencari Event, Panduan Penyelenggara.
    * **Bantuan:** Hubungi SoundTix, Informasi Penyelenggara.
    * Menampilkan logo SoundTix, copyright, serta informasi singkat platform. Struktur footer konsisten pada seluruh halaman publik yang menggunakan layout utama SoundTix.

  * Detail event menampilkan rundown, daftar artis/performer, lokasi, dan daftar kategori tiket.

* **EPIC 3: Event & Ticket Management (Organizer & Admin)**

  * Organizer dapat membuat event berstatus `DRAFT` atau mengajukan `PENDING_APPROVAL`.

  * Relasi Many-to-Many antara Event dan Performer/Artis.

  * Admin berhak menyetujui (`PUBLISHED`) atau menolak (`REJECTED`) event.

* **EPIC 4: Order & Checkout Engine**

  * Batas maksimal 5 tiket per transaksi.

  * Mekanisme Holding Lock kuota tiket selama 15 menit.

  * Generasi kode pesanan unik (Format: `STX-YYYYMMDD-XXXX`).

* **EPIC 5: Mock Payment System**

  * Pilihan metode simulasi: Bank Transfer / QRIS.

  * Akses simulasi status "Bayar Berhasil" atau "Kadaluarsa".

  * Otomatis memicu pembuatan E-Ticket saat status menjadi `PAID`.

* **EPIC 6: E-Ticket Generation & QR Security**

  * Generasi unit tiket unik berformat 12 karakter alfanumerik.

  * QR Code berisi payload terenkripsi HMAC SHA-256 (`ticket_code` + `order_id` + `salt`).

* **EPIC 7: Gatekeeper Scanner & Ticket Validation**

  * Interface scanner berbasis web kamera *mobile-friendly*.

  * Validasi status tiket: Ubah `ACTIVE` menjadi `USED`, simpan waktu pindaian dan ID Gatekeeper.

  * Penolakan otomatis jika tiket berstatus `USED`, `EXPIRED`, atau `CANCELLED`.

* **EPIC 8: Analytics & Financial Reporting**

  * Dashboard Organizer: Grafik tren penjualan, total pendapatan, dan persentase kuota terisi.

  * Export laporan transaksi ke CSV/PDF.

  * Dashboard Admin: Statistik makro platform dan pendapatan global.

* **EPIC 9: Responsive UI/UX**

  * Seluruh halaman publik SoundTix wajib responsive dan dapat digunakan dengan baik pada desktop, laptop, tablet, dan smartphone.

  * Header menyesuaikan ukuran layar. Search bar tetap mudah digunakan pada perangkat mobile.

  * Poster / hero event menyesuaikan ukuran viewport tanpa merusak rasio gambar.

  * Footer menggunakan layout responsive — beberapa kolom pada layar lebar, berubah menjadi layout vertikal pada layar kecil.

  * Button, typography, card, spacing, dan navigation harus memiliki ukuran nyaman untuk perangkat touchscreen.

  * Tidak boleh terdapat horizontal scrolling pada viewport normal. Seluruh komponen utama menggunakan breakpoint responsive yang konsisten.

---

## 5. DATABASE ARCHITECTURE

### 5.1. Data Dictionary

#### 1. `users`

* `id` (BigInt, PK, Auto-Increment)

* `name` (VarChar 255)

* `email` (VarChar 255, Unique) — *Admin diset menggunakan email khusus*

* `password` (VarChar 255)

* `phone` (VarChar 20, Nullable)

* `role` (Enum: `'admin'`, `'organizer'`, `'customer'`, `'gatekeeper'`)

* `is_active` (Boolean, Default: `true`)

* `created_at`, `updated_at` (Timestamps)

#### 2. `events`

* `id` (BigInt, PK, Auto-Increment)

* `organizer_id` (BigInt, FK -> `users.id`)

* `title` (VarChar 255)

* `slug` (VarChar 255, Unique)

* `description` (Text)

* `banner_path` (VarChar 255, Nullable)

* `venue_name` (VarChar 255)

* `city` (VarChar 100)

* `address` (Text)

* `event_date` (Date)

* `start_time` (Time)

* `end_time` (Time, Nullable)

* `status` (Enum: `'draft'`, `'pending'`, `'published'`, `'rejected'`, `'cancelled'`)

* `rejection_reason` (Text, Nullable)

* `created_at`, `updated_at` (Timestamps)

#### 3. `performers`

* `id` (BigInt, PK, Auto-Increment)

* `name` (VarChar 255)

* `genre` (VarChar 100, Nullable)

* `image_path` (VarChar 255, Nullable)

* `created_at`, `updated_at` (Timestamps)

#### 4. `event_performers` (Pivot Table)

* `event_id` (BigInt, FK -> `events.id`, On Delete Cascade)

* `performer_id` (BigInt, FK -> `performers.id`, On Delete Cascade)

* **PRIMARY KEY:** (`event_id`, `performer_id`)

#### 5. `ticket_categories`

* `id` (BigInt, PK, Auto-Increment)

* `event_id` (BigInt, FK -> `events.id`, On Delete Cascade)

* `name` (VarChar 100)

* `description` (Text, Nullable)

* `price` (Decimal 12, 2)

* `total_quantity` (Int Unsigned)

* `available_quantity` (Int Unsigned)

* `max_per_order` (Int Unsigned, Default: 5)

* `created_at`, `updated_at` (Timestamps)

#### 6. `orders`

* `id` (BigInt, PK, Auto-Increment)

* `order_code` (VarChar 50, Unique)

* `customer_id` (BigInt, FK -> `users.id`)

* `total_amount` (Decimal 12, 2)

* `admin_fee` (Decimal 12, 2, Default: 0)

* `grand_total` (Decimal 12, 2)

* `status` (Enum: `'pending'`, `'paid'`, `'expired'`, `'cancelled'`, `'failed'`)

* `expires_at` (DateTime)

* `paid_at` (DateTime, Nullable)

* `created_at`, `updated_at` (Timestamps)

#### 7. `order_items`

* `id` (BigInt, PK, Auto-Increment)

* `order_id` (BigInt, FK -> `orders.id`, On Delete Cascade)

* `ticket_category_id` (BigInt, FK -> `ticket_categories.id`)

* `quantity` (Int Unsigned)

* `price_per_unit` (Decimal 12, 2) — *Snapshot harga saat transaksi*

* `subtotal` (Decimal 12, 2)

* `created_at`, `updated_at` (Timestamps)

#### 8. `tickets`

* `id` (BigInt, PK, Auto-Increment)

* `order_item_id` (BigInt, FK -> `order_items.id`, On Delete Cascade)

* `ticket_code` (VarChar 100, Unique)

* `qr_payload` (Text)

* `status` (Enum: `'active'`, `'used'`, `'expired'`, `'cancelled'`)

* `used_at` (DateTime, Nullable)

* `validated_by` (BigInt, FK -> `users.id`, Nullable)

* `created_at`, `updated_at` (Timestamps)

#### 9. `payments`

* `id` (BigInt, PK, Auto-Increment)

* `order_id` (BigInt, FK -> `orders.id`, On Delete Cascade)

* `transaction_id` (VarChar 100, Unique)

* `payment_type` (VarChar 50)

* `gross_amount` (Decimal 12, 2)

* `payment_status` (Enum: `'pending'`, `'settlement'`, `'expire'`, `'failure'`)

* `raw_response` (JSON, Nullable)

* `created_at`, `updated_at` (Timestamps)

---

## 6. DEVELOPMENT MILESTONES

* **MILESTONE 1:** Project Planning & System Analysis *(Selesai)*

* **MILESTONE 2:** Project Initialization & Environment Setup

* **MILESTONE 3:** Database Architecture & Sanctum Auth Setup

* **MILESTONE 4:** Role, Permission & User Management (Termasuk Admin Seeder)

* **MILESTONE 5:** Customer Homepage & Event Discovery

* **MILESTONE 6:** Event Organizer Dashboard Shell

* **MILESTONE 7:** Event Management & Approval System

* **MILESTONE 8:** Ticket Category & Inventory Management

* **MILESTONE 9:** Customer Ticket Ordering UI

* **MILESTONE 10:** Holding Lock Checkout Engine

* **MILESTONE 11:** Mock Payment System

* **MILESTONE 12:** E-Ticket & QR Code Generation

* **MILESTONE 13:** QR Code Gatekeeper Validation

* **MILESTONE 14:** Admin Dashboard & Management

* **MILESTONE 15:** Organizer Sales Reports & Export

* **MILESTONE 16:** Notification System

* **MILESTONE 17:** Security, Validation & Concurrency Test

* **MILESTONE 18:** UI/UX Refinement, Responsive Design & Public Layout Polish

  * Scope: Finalisasi Header SoundTix · Search bar pada Header · Penghapusan sistem filter · Hero/cover transition poster event · Footer SoundTix · Responsive desktop/tablet/mobile · Typography, spacing, card, dan button refinement · Cross-device UI testing.

* **MILESTONE 19:** Integration Testing & Bug Fixing

* **MILESTONE 20:** Deployment Preparation

* **MILESTONE 21:** UKOM Documentation & Diagram Portfolio

* **MILESTONE 22:** Final Review & Production Readiness