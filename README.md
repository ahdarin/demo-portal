# Demo Penerima Data Mahasiswa dari SPMB Adzkia

Proyek kecil ini **bukan Portal Akademik sungguhan** — ini contoh kerja (reference
implementation) yang menunjukkan persis bagaimana SPMB Adzkia akan mengirim data
mahasiswa yang sudah resmi (punya NIM) ke sistem Portal Akademik, supaya tim
Portal bisa:

1. Memastikan format payload, header autentikasi, dan skema datanya jelas —
   tanpa harus menunggu dokumen tertulis bolak-balik.
2. Menjalankan ini di komputer sendiri dan benar-benar menerima kiriman dari
   SPMB (bukan cuma baca spek di atas kertas), sambil membangun endpoint asli.
3. Melihat langsung contoh penanganan yang benar untuk kasus "kirim ulang
   mahasiswa yang sama" (lihat bagian **Upsert berdasarkan NIM** di bawah).

Build sistem penerima yang sebenarnya **boleh pakai bahasa/framework apa pun**
di sisi Portal — kode di sini hanya referensi pola, bukan sesuatu yang harus
dipakai verbatim.

---

## Cara menjalankan demo ini

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8010
```

Isi `.env` (`SPMB_API_KEY` dan `SPMB_HMAC_SECRET`) dengan nilai yang **sama
persis** dengan `PORTAL_AKADEMIK_API_KEY` / `PORTAL_AKADEMIK_HMAC_SECRET` di
`.env` aplikasi SPMB yang mengirim — dua pihak berbagi rahasia yang sama,
bukan sepasang kunci publik/privat.

---

## Kontrak API

### Endpoint

```
POST /api/spmb/mahasiswa
```

SPMB mengirim **satu kali per hari (dini hari)** secara otomatis untuk semua
mahasiswa baru yang sudah resmi, plus bisa dikirim ulang manual per-mahasiswa
kapan saja dari dashboard admin SPMB (misalnya kalau pengiriman otomatis
gagal).

### Header request

| Header | Isi | Keterangan |
|---|---|---|
| `Authorization` | `Bearer <api_key>` | `api_key` = `SPMB_API_KEY` |
| `X-Signature` | `hash_hmac('sha256', <X-Timestamp> . '.' . <raw body>, <hmac_secret>)` | Yang ditandatangani: nilai header `X-Timestamp`, satu titik, lalu **body mentah** |
| `X-Timestamp` | ISO 8601, mis. `2026-10-05T08:05:50+07:00` | Ikut ditandatangani. Penerima menolak `401` kalau selisihnya lebih dari ±300 detik dari jam server penerima |

Contoh string yang ditandatangani:

```
2026-10-05T08:05:50+07:00.{"schema_version":2,"batch_id":"20261005-080550",...}
```

**Poin paling sering salah saat mengimplementasikan ulang:**
- Signature harus dihitung dari string JSON **persis seperti yang dikirim di
  body**, sebelum di-parse. Kalau implementasi Anda men-decode JSON lalu
  meng-encode ulang untuk verifikasi, urutan key atau spasi bisa berubah dan
  signature akan selalu dianggap tidak valid walau datanya benar.
- Pakai nilai `X-Timestamp` **apa adanya** (string mentah dari header) saat
  menyusun string yang diverifikasi. Jangan di-parse lalu di-format ulang,
  karena format zona waktu bisa berubah (mis. `+07:00` jadi `Z`).
- Timestamp ikut ditandatangani supaya request lama tidak bisa dikirim ulang
  (replay) dengan `X-Timestamp` baru. Pastikan jam server kedua pihak
  sinkron NTP.

### Body (JSON)

```json
{
  "schema_version": 2,
  "batch_id": "20261005-084716",
  "sent_at": "2026-10-05T08:05:50+07:00",
  "mahasiswa": [
    {
      "nim": "26110101100",
      "no_pendaftaran": "202630002",
      "identitas": { "nama_lengkap": "...", "nik": "...", "nisn": "...", "email": "...", "no_whatsapp": "...", "tempat_lahir": "...", "tanggal_lahir": "YYYY-MM-DD", "gender": "...", "agama": "..." },
      "alamat": { "alamat_rumah": "...", "kelurahan_desa": "...", "kecamatan": "...", "kota_kabupaten": "...", "provinsi": "..." },
      "pendidikan_asal": { "sekolah_asal": "...", "npsn_sekolah": "...", "jurusan_sma": "...", "tahun_lulus": "..." },
      "akademik": { "prodi": "...", "fakultas": "...", "jalur": "...", "jalur_rpl": false, "gelombang": "...", "tahun_masuk": 2026, "kode_bidang_nim": 1 },
      "rpl": null,
      "data_ortu": { "ayah": { "...": "..." }, "ibu": { "...": "..." }, "wali": null },
      "size_almamater": "M",
      "keuangan_daftar_ulang": { "skema": "full", "total_kewajiban": 0, "sudah_dibayar": 0, "sisa": 0, "status": "paid" },
      "nim_issued_at": "2026-10-05T08:05:34+07:00"
    }
  ]
}
```

Satu request bisa berisi **banyak mahasiswa sekaligus** (`mahasiswa` adalah
array) — jangan asumsikan selalu 1 per request.

### Response yang diharapkan SPMB

Penerima membalas ringkasan per batch:

```json
{ "batch_id": "20261005-080550", "total": 1, "berhasil": 1, "gagal": 0 }
```

- `2xx` **dan** `gagal` = 0 **dan** `berhasil` = jumlah yang dikirim → SPMB
  menandai seluruh mahasiswa dalam batch itu **berhasil terkirim**, tidak
  akan dikirim ulang otomatis lagi.
- Selain itu (kode non-2xx, timeout/connection error, atau `2xx` tapi ada
  yang gagal) → SPMB menandai **seluruh batch gagal** dan otomatis mengirim
  ulang semuanya pada jadwal berikutnya (atau bisa dikirim ulang manual oleh
  admin). Ringkasan tidak menyebut NIM mana yang gagal, jadi yang sudah
  berhasil ikut terkirim ulang — aman karena penerima meng-upsert per NIM.

---

## Isi demo ini & fungsinya

| Route | Fungsi |
|---|---|
| `POST /api/spmb/mahasiswa` | **Inti yang wajib direplikasi.** Validasi `Authorization` + `X-Signature`, parse body, lalu *upsert* tiap mahasiswa berdasarkan `nim`. |
| `GET /` | Dump mentah payload **terakhir** yang diterima + ringkasan riwayat per-batch. Berguna untuk debug cepat "apa yang baru saja dikirim SPMB barusan". |
| `GET /mahasiswa` | Daftar **semua** mahasiswa yang pernah diterima, satu baris per NIM, dengan kolom "Diterima Ke-" yang menunjukkan berapa kali NIM itu dikirim ulang. Ini pembuktian visual bahwa kirim ulang **tidak** membuat data dobel. |
| `GET /mahasiswa/{nim}` | Payload lengkap terakhir yang tersimpan untuk satu NIM tertentu. |

### Upsert berdasarkan NIM (bagian paling penting untuk ditiru)

```php
$row = MahasiswaDiterima::firstOrNew(['nim' => $m['nim']]);
$baru = !$row->exists;

$row->payload = $m;                 // timpa dengan data terbaru
$row->last_received_at = now();
if ($baru) {
    $row->first_received_at = now();
}
$row->receive_count = ($row->receive_count ?? 0) + 1;
$row->save();
```

Kalau NIM yang sama dikirim lagi (baik karena SPMB retry otomatis, atau admin
SPMB pencet "Kirim Ulang" manual), baris yang **sama** di-update — bukan
insert baris baru. Ini penting karena SPMB *tidak menjamin* tiap NIM hanya
dikirim sekali seumur hidup; sistem Portal yang sebenarnya **wajib**
idempoten terhadap NIM yang sama, dengan cara apa pun yang setara (upsert,
`INSERT ... ON CONFLICT`, cek-lalu-update, dll — tidak harus pakai Eloquent
seperti di sini).

---

## Menerapkan pola ini ke sistem Portal yang sebenarnya

Langkah konsepnya, lepas dari bahasa/framework yang dipakai tim Portal:

1. Baca `Authorization` header, cocokkan token Bearer dengan API key yang
   disepakati. Tolak dengan `401` kalau tidak cocok.
2. Baca header `X-Timestamp`. Tolak `401` kalau kosong, tidak bisa dibaca
   sebagai waktu, atau selisihnya lebih dari ±300 detik dari jam server.
3. Baca **body mentah** (raw string, belum di-parse), hitung
   `HMAC-SHA256(x_timestamp + "." + raw_body, hmac_secret)`, bandingkan dengan
   `X-Signature` pakai perbandingan constant-time (`hash_equals` di PHP,
   `hmac.compare_digest` di Python, dll — jangan pakai `==` biasa). Tolak
   `401` kalau tidak cocok.
4. Baru setelah lolos semuanya, parse body sebagai JSON.
5. Loop array `mahasiswa`, untuk tiap elemen: upsert berdasarkan `nim` ke
   tabel mahasiswa Portal yang sebenarnya (bukan tabel demo seperti di sini).
6. Balas `200` dengan ringkasan `{"batch_id","total","berhasil","gagal"}`.
   SPMB menganggap batch berhasil hanya kalau kode `2xx`, `gagal` = 0, dan
   `berhasil` sama dengan jumlah yang dikirim; selain itu seluruh batch
   dikirim ulang nanti (aman karena upsert per NIM).

---

## Mengetes pengiriman selagi kedua sistem masih localhost

Saat ini kedua sistem (SPMB dan demo ini) jalan di komputer yang sama lewat
`localhost`, jadi pengujian langsung tanpa halangan. Begitu tim Portal mulai
mengembangkan di komputer/jaringan **mereka sendiri**, `localhost` SPMB tidak
lagi bisa menjangkau `localhost` Portal begitu saja — ada dua opsi tergantung
tahap pengujiannya:

### Untuk sesi uji coba bersama yang singkat (paling praktis sekarang)

Pakai **tunnel sementara** supaya server lokal salah satu pihak bisa diakses
dari internet selama sesi testing, tanpa perlu deploy apa pun:

```bash
# jalankan di komputer yang menjalankan server penerima (Portal)
ngrok http 8010
# atau alternatif gratis: cloudflared tunnel --url http://localhost:8010
```

Tunnel akan memberi URL publik sementara (mis. `https://abcd1234.ngrok-free.app`)
— pihak SPMB tinggal set `PORTAL_AKADEMIK_URL` ke URL itu + path endpoint
(`/api/spmb/mahasiswa`) untuk sesi itu saja. **Matikan tunnel setelah sesi
selesai** — jangan dibiarkan terbuka, karena itu membuka server dev (yang
biasanya tanpa hardening production) ke internet publik. Autentikasi
Bearer + HMAC yang sudah ada tetap jadi lapisan pelindung utama selama
tunnel aktif.

### Untuk pengujian berkelanjutan / menjelang go-live

Tunnel tidak cocok dipakai terus-menerus (URL berubah setiap restart,
tidak ada SLA). Untuk tahap ini sebaiknya salah satu atau kedua pihak
menyiapkan **environment staging** yang reachable secara tetap (domain
sendiri + HTTPS). Ini juga sejalan dengan guard yang sudah ada di kode SPMB:
pengiriman otomatis **ditolak secara sengaja** kalau environment production
dan URL tujuannya bukan `https://` (mencegah data sensitif—NIK, data orang
tua—terkirim lewat koneksi tak terenkripsi akibat salah konfigurasi). Jadi
URL staging Portal juga sebaiknya sudah HTTPS sebelum dipakai untuk uji
coba serius, bukan cuma saat sudah production.

---

## Yang BUKAN bagian dari kontrak resmi

- Nilai `SPMB_API_KEY` / `SPMB_HMAC_SECRET` di `.env.example` repo ini cuma
  placeholder demo — wajib diganti dengan rahasia baru sebelum dipakai di
  luar sesi testing lokal, dan tidak boleh dikirim lewat chat/email biasa.
- Tabel `mahasiswa_diterima` di demo ini cuma untuk pembuktian konsep upsert
  — bukan rancangan skema tabel yang harus ditiru persis; Portal bebas
  menyimpan sesuai struktur data mahasiswa mereka sendiri.
- Skema payload di atas mengikuti kondisi SPMB saat dokumen ini ditulis.
  Kalau ada field yang perlu ditambah/diubah, itu perlu dikoordinasikan
  ulang antara kedua tim sebelum go-live.
