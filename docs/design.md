# Design System — SoundTix

> Aplikasi pemesanan tiket konser **SoundTix**.
> Dokumen ini jadi acuan desain buat semua tim (design, frontend, QA) di 4 role: **Admin Platform**, **Vendor**, **Gatekeeper**, dan **User**.
> Palet warna diambil dari logo SoundTix (ungu → biru → cyan), tapi **dipecah jadi warna solid**. Gradasi di logo **tidak** dibawa ke UI.

---

## 1. Prinsip Desain

| Prinsip | Artinya di app ini |
| --- | --- |
| **Tenang, bukan ramai** | Warnanya soft dan solid. **Tanpa gradasi, tanpa campuran warna.** Poster konser boleh ramai, UI-nya harus tetap tenang. |
| **Konten yang jadi bintang** | Foto artis/poster event jadi fokus utama. UI cuma jadi "bingkai" yang netral. |
| **Satu layar, satu tujuan** | Tiap layar punya satu aksi utama yang jelas (Beli, Simpan, Scan, Setujui). |
| **Cepat di lapangan** | Gatekeeper kerja di venue yang gelap & ramai, jadi UI-nya harus besar, kontras, dan bisa dipakai satu tangan. |
| **Konsisten antar role** | Token dan komponennya sama. Yang beda cuma navigasi dan kepadatan informasinya. |

---

## 2. Warna

### 2.1 Aturan Dasar
- **Dilarang pakai gradient** (`linear-gradient`, `radial-gradient`, `bg-gradient-*`), termasuk di tombol, header, dan background.
- **Solid color only.** Kedalaman dibangun lewat perbedaan tone permukaan dan border tipis, **bukan** bayangan tebal.
- Palet inti cukup **5 warna**: 1 primary, 1 accent, 3 netral. Warna status (sukses/peringatan/error) cuma dipakai buat feedback.
- Semua kombinasi teks harus lolos **WCAG AA** (≥ 4.5:1 untuk teks normal).

### 2.2 Warna Brand (diambil dari logo)

Logo SoundTix punya gradasi ungu → biru → cyan. Di UI, gradasi itu **dipecah jadi 3 warna solid** yang sudah dilembutkan (desaturasi sedikit) biar nyaman dilihat lama.

| Nama | Hex | Asal di logo | Peran di UI |
| --- | --- | --- | --- |
| **Sound Violet** | `#6B46D1` | Huruf "S" & tulisan "Tix" | `primary`: CTA utama, link, state aktif |
| **Sound Blue** | `#2F6FC8` | Bagian tengah "S" & "T" | `secondary-brand`: info, grafik seri kedua, link sekunder |
| **Sound Cyan** | `#6FD3E6` | Ujung atas huruf "T" | `accent`: highlight hemat (badge "Hampir Habis", promo, rating) |

> Tiga warna ini **tidak boleh disambung** jadi gradasi, dan jangan ditaruh bersebelahan dalam satu elemen (misalnya tombol ungu dengan border cyan). Satu elemen = satu warna.

### 2.3 Palet Inti — Light Mode (default)

| Token | Hex | Kegunaan |
| --- | --- | --- |
| `background` | `#F8F7FC` | Latar utama aplikasi (putih dengan sedikit rona lavender, gak silau) |
| `surface` / `card` | `#FFFFFF` | Kartu, sheet, modal |
| `muted` | `#EFEDF6` | Latar sekunder, input, skeleton, chip non-aktif |
| `border` | `#E4E1EE` | Garis pemisah, outline kartu |
| `foreground` | `#23212E` | Teks utama (kontras ±15:1 di atas background) |
| `muted-foreground` | `#6A6780` | Teks sekunder, caption (kontras ±5.3:1) |
| `primary` | `#6B46D1` | **Sound Violet**. CTA utama, link, state aktif (kontras 6.1:1 dengan putih) |
| `primary-foreground` | `#FFFFFF` | Teks di atas primary |
| `primary-soft` | `#EEE9FB` | Latar highlight halus, badge primary, item nav aktif |
| `secondary-brand` | `#2F6FC8` | **Sound Blue**. Info, grafik, link sekunder (kontras 4.9:1 dengan putih) |
| `secondary-brand-soft` | `#E6EFFA` | Latar badge info, banner info |
| `accent` | `#6FD3E6` | **Sound Cyan**. Highlight hemat: badge "Hampir Habis", promo, rating |
| `accent-foreground` | `#23212E` | Teks di atas accent (**jangan** pakai putih, kontrasnya kurang) |

### 2.4 Palet Inti — Dark Mode

Dipakai opsional sama User, dan **jadi default buat Gatekeeper** (venue gelap). Logo SoundTix juga paling pas tampil di dark mode karena tulisan "Sound"-nya putih.

| Token | Hex |
| --- | --- |
| `background` | `#16151D` |
| `surface` / `card` | `#1F1D29` |
| `muted` | `#2A2836` |
| `border` | `#363345` |
| `foreground` | `#ECEAF4` |
| `muted-foreground` | `#A4A1B8` |
| `primary` | `#A58BF0` |
| `primary-foreground` | `#16151D` |
| `primary-soft` | `#2C2645` |
| `secondary-brand` | `#7FA9E8` |
| `secondary-brand-soft` | `#1F2B40` |
| `accent` | `#8EDDEB` |
| `accent-foreground` | `#16151D` |

> Dark mode sengaja **tidak pakai hitam pekat** (`#000`), tapi ungu gelap yang sangat redup, biar mata gak capek dan tetap terasa "SoundTix".

### 2.5 Pemakaian Logo

- File logo asli boleh tetap bergradasi. **Itu satu-satunya pengecualian**, karena logo adalah aset brand, bukan elemen UI.
- Tulisan "Sound" di logo berwarna putih, jadi logo penuh **hanya dipakai di atas latar gelap** (`#16151D` / `#1F1D29`): splash screen, header Gatekeeper, footer, e-ticket versi dark.
- Di atas latar terang, pakai **versi flat**: ikon "ST" satu warna `primary` + wordmark "Sound" warna `foreground` dan "Tix" warna `primary`.
- Ukuran minimal: ikon 24px (favicon/nav), logo penuh 120px lebar. Beri ruang kosong minimal setinggi huruf "T" di sekelilingnya.
- Jangan pakai efek glow, shadow, atau outline di logo.

### 2.6 Warna Status (Semantic)

Semua warnanya solid dan desaturasi. Masing-masing punya versi `-soft` buat latar.

| Status | Solid | Soft (latar) | Contoh pemakaian |
| --- | --- | --- | --- |
| `success` | `#4F8A5B` | `#E6F0E8` | Pembayaran berhasil, tiket **VALID** di scanner |
| `warning` | `#B7862F` | `#F6EEDC` | Menunggu pembayaran, event menunggu review |
| `destructive` | `#B5534A` | `#F5E3E1` | Gagal bayar, tiket **DITOLAK / SUDAH DIPAKAI** |
| `info` | `#2F6FC8` | `#E6EFFA` | Info umum, notifikasi sistem (pakai Sound Blue) |

### 2.7 Cara Pakai Warna (Proporsi 70 / 20 / 10)
- **70%** netral (`background`, `surface`, `muted`)
- **20%** teks dan border
- **10%** warna brand: Violet paling dominan, Blue secukupnya, Cyan **maksimal 1–2 elemen per layar**.
- Grafik (Vendor/Admin): seri 1 Violet, seri 2 Blue, seri 3 Cyan, seri 4 `muted-foreground`. Tiap seri solid, tanpa area gradasi.

---

## 3. Tipografi

Cukup **2 font**:

| Peran | Font | Alasan |
| --- | --- | --- |
| Heading | **Plus Jakarta Sans** (600/700) | Buatan lokal Indonesia, ramah, modern |
| Body & UI | **Inter** (400/500/600) | Sangat terbaca di ukuran kecil, angka tabular buat harga & jam |

### Skala Tipografi

| Token | Mobile | Desktop | Line-height | Pemakaian |
| --- | --- | --- | --- | --- |
| `display` | 28px | 40px | 1.15 | Hero judul event |
| `h1` | 24px | 32px | 1.2 | Judul halaman |
| `h2` | 20px | 24px | 1.3 | Judul section |
| `h3` | 17px | 18px | 1.4 | Judul kartu |
| `body` | 15px | 16px | 1.6 | Teks paragraf |
| `small` | 13px | 14px | 1.5 | Caption, meta (tanggal, lokasi) |
| `micro` | 11px | 12px | 1.4 | Label badge (uppercase, tracking +0.04em) |

- Harga, jam, dan kuota pakai `font-variant-numeric: tabular-nums`.
- Panjang baris teks maksimal ~70 karakter (`max-w-prose`).
- Hindari teks di bawah 13px di mobile.

---

## 4. Spacing, Radius, Elevation

### Spacing (basis 4px)
`4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64`

- Padding kartu: **16px** di mobile, **24px** di desktop
- Jarak antar section: **32px** di mobile, **48–64px** di desktop
- Semua layout pakai `gap`, bukan margin antar elemen

### Radius
| Token | Nilai | Pemakaian |
| --- | --- | --- |
| `radius-sm` | 8px | Input, chip, badge |
| `radius-md` | 12px | Tombol, kartu kecil |
| `radius-lg` | 16px | Kartu event, modal |
| `radius-xl` | 24px | Bottom sheet (sudut atas), e-ticket |
| `radius-full` | 9999px | Avatar, pill filter |

### Elevation
Kedalaman diutamakan lewat **tone permukaan dan border**, bayangan cuma boleh yang sangat tipis:

| Level | Style |
| --- | --- |
| 0 | `border: 1px solid border` (default kartu) |
| 1 | `0 1px 2px rgba(35,33,46,0.06)` (kartu hover) |
| 2 | `0 8px 24px rgba(35,33,46,0.08)` (modal, dropdown, bottom sheet) |

---

## 5. Layout & Breakpoint

### Breakpoint (mobile-first)

| Nama | Min-width | Target |
| --- | --- | --- |
| `base` | 0 | HP (360–430px) |
| `sm` | 640px | HP landscape |
| `md` | 768px | Tablet |
| `lg` | 1024px | Laptop |
| `xl` | 1280px | Desktop |

### Grid
- **Mobile:** 4 kolom, margin 16px, gutter 12px
- **Tablet:** 8 kolom, margin 24px, gutter 16px
- **Desktop:** 12 kolom, max-width konten **1200px** (dashboard: fluid dengan sidebar 256px)

### Pola Navigasi per Role

| Role | Mobile | Desktop |
| --- | --- | --- |
| **User** | Bottom tab bar (5 item) + top bar ringan | Top navbar horizontal + search di tengah |
| **Vendor** | Bottom tab bar (4 item) + FAB "Buat Event" | Sidebar kiri (256px, bisa collapse ke 72px) |
| **Admin Platform** | Top bar + drawer menu (hamburger) | Sidebar kiri + top bar berisi search global |
| **Gatekeeper** | Full-screen scanner, tanpa tab bar, cuma 3 tombol besar di bawah | Tablet/desktop: layout split (scanner kiri, log kanan) |

**Aturan umum:**
- Tap target minimal **44×44px** (Gatekeeper: **56px**)
- Bottom tab bar tingginya 64px + `safe-area-inset-bottom`
- Tombol CTA utama di mobile dibuat **sticky di bawah** (misalnya "Beli Tiket", "Bayar Sekarang")

---

## 6. Komponen Inti

### 6.1 Tombol
| Varian | Style |
| --- | --- |
| Primary | bg `primary`, teks putih, radius 12px, tinggi 48px (mobile) / 44px (desktop) |
| Secondary | bg `muted`, teks `foreground` |
| Outline | border `border`, bg transparan |
| Ghost | tanpa bg, teks `primary`. Buat aksi tersier |
| Destructive | bg `destructive`, teks putih. **Selalu minta konfirmasi dulu** |

State: hover pakai tone 6% lebih gelap (solid, bukan gradient); focus pakai ring 2px `primary` + offset 2px; disabled pakai opacity 50%.

### 6.2 Kartu Event (komponen paling penting)
```
┌──────────────────────────┐
│  [Poster 16:9 / 4:5]     │  ← radius-lg, gambar full-bleed
│  [Badge: Hampir Habis]   │  ← accent, pojok kiri atas
├──────────────────────────┤
│  SAB, 14 DES · 19:00     │  ← micro, primary
│  Nama Artis / Event      │  ← h3, max 2 baris
│  📍 Istora Senayan       │  ← small, muted-foreground (pakai ikon, bukan emoji)
│  Mulai Rp350.000     ♡   │  ← body semibold + tombol simpan
└───────────���──────────────┘
```
- Mobile: 1 kolom (list) atau carousel horizontal di home
- Tablet: 2 kolom · Desktop: 3–4 kolom

### 6.3 E-Ticket
- Kartu `radius-xl`, bg `surface`, garis putus-putus di tengah (efek sobekan tiket, pakai `border-dashed`)
- QR code minimal **240×240px** dengan quiet zone putih, **selalu di atas latar putih** walaupun sedang dark mode
- Brightness layar otomatis naik waktu tiket dibuka (kalau didukung)
- Info: nama event, kategori/section, nomor kursi, nama pemilik, status

### 6.4 Komponen Lain
- **Input:** tinggi 48px, bg `surface`, border `border`, focus ring `primary`, label selalu di atas (bukan placeholder)
- **Badge status:** bg `*-soft` + teks warna solid, radius-sm, micro uppercase
- **Chip filter:** pill, non-aktif `muted`, aktif `primary-soft` + teks `primary`
- **Bottom sheet (mobile) ↔ Dialog (desktop):** konten sama, container menyesuaikan breakpoint
- **Tabel (Admin/Vendor):** zebra row pakai `muted` 50%, header sticky; di mobile berubah jadi list kartu
- **Stat card:** angka besar (h1, tabular), label small, delta (+12%) pakai warna success/destructive
- **Empty state:** ilustrasi line sederhana monokrom `muted-foreground`, 1 kalimat, 1 CTA
- **Skeleton:** blok `muted`, animasi pulse pelan (opacity 1 → 0.6)

### 6.5 Ikon
- Set: **Lucide**, stroke 1.75px, ukuran 20px (UI) / 24px (nav)
- **Tidak pakai emoji sebagai ikon**

---

## 7. Layar per Role

### 7.1 USER (Pembeli Tiket)

**Navigasi mobile (bottom tab):** Beranda · Jelajah · Tiket Saya · Tersimpan · Profil

| Layar | Isi Utama | Catatan Layout |
| --- | --- | --- |
| **Beranda** | Search bar, carousel "Segera Tampil", kategori genre (chip), "Dekat Kamu", "Populer Minggu Ini" | Mobile: section stacked, carousel horizontal. Desktop: hero event unggulan 2/3 + list samping 1/3 |
| **Jelajah / Search** | Filter (tanggal, kota, genre, harga), hasil grid | Mobile: filter di bottom sheet. Desktop: sidebar filter kiri 280px + grid kanan |
| **Detail Event** | Poster, info artis, tanggal & venue, peta venue, kategori tiket, S&K | Mobile: poster full-width → info → **CTA sticky bawah** "Pilih Tiket · mulai Rp…". Desktop: 2 kolom (konten 8 col, kartu pembelian sticky 4 col) |
| **Pilih Tiket / Kursi** | Kategori tiket (Festival, CAT 1, VIP) + stepper jumlah, seat map opsional | Seat map: warna tersedia = `primary-soft`, dipilih = `primary`, terjual = `muted` + pattern garis (bukan warna saja, demi aksesibilitas) |
| **Checkout** | Timer reservasi (mis. 10:00), data pemesan, metode bayar, ringkasan | Stepper 3 langkah: Data → Bayar → Selesai. Timer pakai `warning-soft` banner |
| **Pembayaran Berhasil** | Ilustrasi sukses, ringkasan, tombol "Lihat Tiket" | Warna `success` hanya di ikon centang, sisanya netral |
| **Tiket Saya** | Tab: Akan Datang · Selesai · Dibatalkan | List kartu tiket, tap untuk buka e-ticket full-screen |
| **Profil** | Data diri, metode bayar tersimpan, preferensi tema (Light/Dark/System), bantuan | List group dengan divider |

### 7.2 VENDOR (Promotor / Penyelenggara)

**Navigasi:** Dashboard · Event · Penjualan · Tim Gatekeeper · Pengaturan

| Layar | Isi Utama | Catatan Layout |
| --- | --- | --- |
| **Dashboard** | Stat card (tiket terjual, pendapatan, check-in hari ini, event aktif), grafik penjualan, event terdekat | Desktop: 4 stat card sebaris → grafik 8 col + list 4 col. Mobile: stat card 2×2, grafik full-width |
| **Daftar Event** | Tabel event + status (Draft, Menunggu Review, Tayang, Selesai) | Status pakai badge semantic. Mobile: kartu list |
| **Buat / Edit Event** | Form multi-step: Info Dasar → Jadwal & Venue → Kategori Tiket & Harga → Media → Review | Desktop: stepper vertikal kiri + form kanan + preview kartu event live. Mobile: stepper horizontal di atas, tombol Lanjut sticky |
| **Detail Penjualan** | Grafik per kategori, daftar transaksi, export CSV | Filter tanggal di atas, tabel di bawah |
| **Tim Gatekeeper** | Undang gatekeeper, assign ke event/gate, lihat aktivitas | List anggota + badge gate |
| **Pencairan Dana** | Saldo, riwayat payout, rekening | Angka saldo besar di kartu `primary-soft` |

### 7.3 GATEKEEPER (Petugas Pintu Masuk)

> **Mode default: Dark.** UI dirancang untuk venue yang gelap, dipakai satu tangan, dan harus cepat.

| Layar | Isi Utama | Catatan Layout |
| --- | --- | --- |
| **Pilih Event & Gate** | List event yang di-assign hari ini | Kartu besar, tap sekali langsung masuk |
| **Scanner (layar utama)** | Viewfinder kamera full-screen, frame scan di tengah, counter check-in di atas ("1.204 / 5.000") | Bawah: 3 tombol 56px: **Senter · Input Manual · Riwayat** |
| **Hasil Scan: VALID** | Layar penuh `success` solid, ikon centang besar, nama pemilik + kategori | Otomatis kembali ke scanner dalam 1.5 detik + getar pendek |
| **Hasil Scan: DITOLAK** | Layar penuh `destructive` solid, alasan besar ("Sudah dipakai 19:42 di Gate B") | **Tidak** kembali otomatis, harus tap "Scan Lagi". Getar panjang |
| **Hasil Scan: PERLU CEK** | Layar `warning`, misalnya kategori tidak sesuai gate | Tombol "Izinkan" / "Tolak" |
| **Riwayat Scan** | Log real-time, filter status | List ringkas dengan badge status |
| **Mode Offline** | Banner `warning` di atas: "Offline, scan tersimpan lokal" | Sinkron otomatis saat online |

**Aturan khusus Gatekeeper:**
- Status **tidak boleh cuma dibedakan pakai warna**, harus ada ikon + teks besar (≥ 24px) + pola getar berbeda.
- Tidak ada animasi dekoratif, semua transisi ≤ 150ms.
- Tablet/desktop: layout split, scanner kiri 60% + log live kanan 40%.

### 7.4 ADMIN PLATFORM

**Navigasi sidebar:** Ringkasan · Moderasi Event · Vendor · Pengguna · Transaksi · Payout · Laporan · Pengaturan Sistem

| Layar | Isi Utama | Catatan Layout |
| --- | --- | --- |
| **Ringkasan** | GMV, total transaksi, vendor aktif, event menunggu review, grafik tren, alert | Grid 12 kolom, stat card 4×, grafik + antrian review |
| **Moderasi Event** | Antrian event dari vendor, preview, tombol Setujui / Minta Revisi / Tolak | Desktop: split-view (list kiri 5 col, preview kanan 7 col). Mobile: list → halaman detail |
| **Manajemen Vendor** | Verifikasi KYC/dokumen, status, komisi | Tabel + drawer detail dari kanan |
| **Manajemen Pengguna** | Cari user, riwayat pembelian, suspend | Tabel dengan search global |
| **Transaksi & Refund** | Semua transaksi, filter status, proses refund | Aksi refund pakai dialog konfirmasi `destructive` |
| **Payout** | Persetujuan pencairan dana vendor | Bulk action + checkbox |
| **Pengaturan Sistem** | Biaya platform, kategori genre, banner beranda, role & izin | Form dikelompokkan dalam kartu per section |

**Aturan khusus Admin:**
- Kepadatannya lebih tinggi: tinggi baris tabel 44px, body 14px.
- Semua aksi berisiko (suspend, tolak, refund) **wajib** konfirmasi + kolom alasan.
- Tampilkan audit trail ("Disetujui oleh Rina · 12 Des 14:03").

---

## 8. Pembeda Visual Antar Role

Warnanya **tetap sama** di semua role. Pembedanya lewat:
1. **Label role** di header/sidebar: badge kecil `primary-soft` bertuliskan "Vendor", "Admin", atau "Gatekeeper".
2. **Kepadatan:** User (lega) → Vendor (sedang) → Admin (padat) → Gatekeeper (sangat besar & minimal).
3. **Tema default:** User/Vendor/Admin pakai Light, Gatekeeper pakai Dark.

---

## 9. Motion

| Jenis | Durasi | Easing |
| --- | --- | --- |
| Hover / press | 120ms | `ease-out` |
| Buka dropdown / tooltip | 150ms | `ease-out` |
| Bottom sheet / dialog | 220ms | `cubic-bezier(0.2, 0.8, 0.2, 1)` |
| Pindah halaman | 200ms fade | `ease-in-out` |

- Tombol ditekan: `scale(0.98)`
- Hormati `prefers-reduced-motion`: animasinya dimatikan, cuma fade.
- **Tidak ada** parallax, efek glow, atau animasi background.

---

## 10. Aksesibilitas

- Kontras minimal AA untuk semua teks, sudah dicek di tabel warna di atas.
- Status selalu **warna + ikon + teks**, jangan cuma warna.
- Focus ring terlihat jelas di semua elemen interaktif.
- Gambar poster wajib ada `alt` (nama event + artis).
- Timer checkout diumumkan ke screen reader pakai `aria-live="polite"`.
- Hasil scan gatekeeper pakai `aria-live="assertive"`.
- Form: label eksplisit, pesan error di bawah field dengan warna `destructive` + ikon.
- Input Enter-submit harus aman untuk IME (cek `isComposing`).

---

## 11. Copywriting & Nada

- Bahasa Indonesia yang santai tapi jelas. Contoh: "Tiketmu siap!", "Yah, tiketnya habis. Mau dikabari kalau ada lagi?"
- Format harga: `Rp350.000` (tanpa spasi, titik sebagai pemisah ribuan)
- Format tanggal: `Sab, 14 Des 2026 · 19:00 WIB`
- Hindari istilah teknis ke User (misalnya "Pembayaran tertunda" lebih baik daripada "Payment pending")

---

## 12. Token Implementasi (Tailwind v4 / shadcn)

```css
@theme inline {
  --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
  --font-heading: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;

  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 24px;
}

:root {
  --background: #F8F7FC;
  --foreground: #23212E;
  --card: #FFFFFF;
  --card-foreground: #23212E;
  --popover: #FFFFFF;
  --popover-foreground: #23212E;
  --primary: #6B46D1;
  --primary-foreground: #FFFFFF;
  --primary-soft: #EEE9FB;
  --secondary: #EFEDF6;
  --secondary-foreground: #23212E;
  --secondary-brand: #2F6FC8;
  --secondary-brand-soft: #E6EFFA;
  --muted: #EFEDF6;
  --muted-foreground: #6A6780;
  --accent: #6FD3E6;
  --accent-foreground: #23212E;
  --destructive: #B5534A;
  --success: #4F8A5B;
  --warning: #B7862F;
  --info: #2F6FC8;
  --border: #E4E1EE;
  --input: #E4E1EE;
  --ring: #6B46D1;
  --chart-1: #6B46D1;
  --chart-2: #2F6FC8;
  --chart-3: #6FD3E6;
  --chart-4: #6A6780;
}

.dark {
  --background: #16151D;
  --foreground: #ECEAF4;
  --card: #1F1D29;
  --card-foreground: #ECEAF4;
  --popover: #1F1D29;
  --popover-foreground: #ECEAF4;
  --primary: #A58BF0;
  --primary-foreground: #16151D;
  --primary-soft: #2C2645;
  --secondary: #2A2836;
  --secondary-foreground: #ECEAF4;
  --secondary-brand: #7FA9E8;
  --secondary-brand-soft: #1F2B40;
  --muted: #2A2836;
  --muted-foreground: #A4A1B8;
  --accent: #8EDDEB;
  --accent-foreground: #16151D;
  --destructive: #D27A70;
  --success: #7DB08A;
  --warning: #D4A85A;
  --info: #7FA9E8;
  --border: #363345;
  --input: #363345;
  --ring: #A58BF0;
  --chart-1: #A58BF0;
  --chart-2: #7FA9E8;
  --chart-3: #8EDDEB;
  --chart-4: #A4A1B8;
}
```

**Lint rule tim:** tolak PR yang mengandung `gradient`, `bg-gradient-`, `from-`, `via-`, atau `to-` di class Tailwind.

---

## 13. Checklist Sebelum Rilis Layar Baru

- [ ] Hanya pakai token warna, tidak ada hex hardcode
- [ ] Tidak ada gradient (kecuali file logo asli)
- [ ] Satu elemen cuma pakai satu warna brand (Violet, Blue, atau Cyan)
- [ ] Accent (Cyan) maksimal 1–2 elemen per layar
- [ ] Logo penuh cuma dipakai di atas latar gelap
- [ ] Sudah dicek di 375px, 768px, dan 1280px
- [ ] Tap target ≥ 44px (Gatekeeper ≥ 56px)
- [ ] Light & dark mode sudah dicek
- [ ] Status tidak cuma dibedakan pakai warna
- [ ] Empty, loading, dan error state sudah ada
- [ ] Kontras teks lolos AA
