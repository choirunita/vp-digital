# VP Digital PCS

Aplikasi web untuk pengajuan, pemeriksaan, dan persetujuan voucher pembayaran (VP) PT Petrokopindo Cipta Selaras. Aplikasi ini dapat dibuka dari laptop, tablet, maupun HP, menyimpan jejak audit yang tidak bisa diubah, dan menghasilkan PDF siap cetak.

- **Teknologi:** Node.js 20+ dan Express, database SQLite (satu file, tanpa server database), tampilan EJS. Semua komponen open source dan tanpa biaya lisensi.
- **Dikelola oleh:** Tim TI PCS, dipasang di server internal.

## Alur

1. **Registrasi rekening.** Maker mengajukan rekening penerima, lalu Checker menyetujuinya. Perubahan rekening juga harus disetujui Checker.
2. **Input VP.** Maker membuat VP non massal (1 penerima) atau massal (banyak penerima; bisa diunggah dari CSV/Excel), lalu melampirkan bukti dan menekan **Ajukan**. Saat itu terbit nomor `VP/TAHUN/BULAN/URUT` (urutan dimulai ulang setiap bulan).
3. **Pemeriksaan.** Checker menyetujui VP, atau mengembalikannya ke Maker dengan catatan. Maker memperbaiki lalu mengajukan ulang dengan nomor yang sama dan revisi yang bertambah.
4. **Persetujuan.** Approver 1 (Direktur Keuangan) menyetujui satu per satu atau beberapa VP sekaligus. Setelah disetujui, PDF otomatis memuat pindaian tanda tangan Maker, Checker, dan Approver 1, beserta QR verifikasi dan sidik jari SHA-256.
5. **Cetak.** Sesdir mencetak PDF VP yang sudah disetujui Approver 1 (satu per satu atau beberapa sekaligus), dan menyerahkannya ke Approver 2 (Direktur Utama) untuk tanda tangan basah. Setelah itu Sesdir menandai VP sebagai dicetak, lalu (opsional) sebagai sudah ditandatangani.

## Akun uji coba

Akun berikut dibuat otomatis saat database masih kosong.

| Peran | Nama pengguna | Kata sandi |
|---|---|---|
| Maker (Staf) | `maker` | `Maker@2026` |
| Checker (Manager/GM) | `checker` | `Checker@2026` |
| Approver 1 (Direktur Keuangan) | `approver1` | `Approver1@2026` |
| Approver 2 (Direktur Utama, hanya melihat) | `approver2` | `Approver2@2026` |
| Sesdir | `sesdir` | `Sesdir@2026` |
| Admin | `admin` | `Admin@2026` |
| Guest | `guest` | `Guest@2026` |

> **Penting untuk produksi:** akun-akun ini hanya untuk uji coba. Sebelum dipakai sungguhan, buat akun per orang melalui menu Admin (kata sandi sementara akan dibuat otomatis dan wajib diganti saat pertama masuk), lalu nonaktifkan akun contoh. Cara lain: kosongkan database dan atur `SEED_FORCE_CHANGE=1`.

## Instalasi

### Kebutuhan
- Node.js 20 LTS atau 22 LTS (https://nodejs.org)
- Server Linux atau Windows dengan RAM ±1 GB dan ruang disk ±2 GB (untuk lampiran)
- Untuk Linux: paket `build-essential` dan `python3`, hanya diperlukan bila `better-sqlite3` tidak menemukan biner siap pakai

### Langkah
```bash
# 1. Salin folder aplikasi ke server, misalnya /opt/vp-digital
cd /opt/vp-digital
npm ci --omit=dev            # atau: npm install --omit=dev
cp .env.example .env         # lalu sunting .env (lihat Konfigurasi)
npm start                    # buka http://alamat-server:3000
```
Pada Windows, jalankan perintah yang sama di PowerShell atau Command Prompt.

### Menjalankan sebagai layanan
**Linux (systemd)** — buat file `/etc/systemd/system/vp-digital.service`:
```ini
[Unit]
Description=VP Digital PCS
After=network.target

[Service]
WorkingDirectory=/opt/vp-digital
ExecStart=/usr/bin/node src/server.js
Restart=always
User=vpdigital
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```
Lalu jalankan `systemctl enable --now vp-digital`.

**Windows:** gunakan NSSM (https://nssm.cc) untuk menjalankan `node.exe src\server.js` sebagai Windows Service. Alternatifnya, gunakan `pm2` beserta `pm2-windows-startup`.

### HTTPS (wajib bila diakses dari luar jaringan kantor)
Letakkan aplikasi di belakang reverse proxy (Nginx, IIS ARR, atau Caddy) yang memakai sertifikat HTTPS, lalu atur `COOKIE_SECURE=1` dan `BASE_URL=https://vp.domain-pcs`. Contoh konfigurasi Nginx:
```nginx
server {
  listen 443 ssl;
  server_name vp.pcs.co.id;
  # ssl_certificate ... ; ssl_certificate_key ... ;
  client_max_body_size 25m;
  location / { proxy_pass http://127.0.0.1:3000; proxy_set_header Host $host; proxy_set_header X-Forwarded-For $remote_addr; proxy_set_header X-Forwarded-Proto https; }
}
```

## Konfigurasi (`.env`)
| Variabel | Fungsi | Bawaan |
|---|---|---|
| `PORT` | Port aplikasi | `3000` |
| `BASE_URL` | Alamat yang dibuka pengguna; dipakai untuk QR verifikasi di PDF | `http://localhost:3000` |
| `SESSION_SECRET` | **Wajib diganti** dengan teks acak panjang | — |
| `COOKIE_SECURE` | `1` bila memakai HTTPS | `0` |
| `DATA_DIR` | Folder database dan lampiran | `data` |
| `SESSION_HOURS` | Lama sesi login (jam) | `8` |
| `REQUIRE_ATTACHMENT` | `1` = VP wajib berlampiran sebelum diajukan | `1` |
| `SEED_FORCE_CHANGE` | `1` = akun awal wajib ganti kata sandi | `0` |

## Cadangan data
Semua data tersimpan di folder `data/`:
- `data/vp-digital.db` — database (termasuk jejak audit)
- `data/uploads/lampiran/` — berkas lampiran VP
- `data/uploads/signatures/` — pindaian tanda tangan

Cadangkan seluruh folder `data/` setiap hari. Untuk salinan database yang konsisten saat aplikasi berjalan, gunakan:
`sqlite3 data/vp-digital.db ".backup '/backup/vp-$(date +%F).db'"`
Lakukan uji pemulihan secara berkala.

## Perintah lain
- `npm test` — uji otomatis alur lengkap (9 skenario, memakai database sementara)
- `npm run reset-demo -- --ya` — **menghapus semua data** lalu mengisi ulang data uji coba (hanya untuk server uji)

## Kontrol yang dijaga aplikasi
- Pemisahan tugas: pembuat VP tidak bisa memeriksa atau menyetujui VP yang sama. Pengaju rekening tidak bisa menyetujui rekeningnya sendiri. Admin tidak bisa menyetujui VP.
- VP terkunci setelah diajukan. Perubahan hanya bisa dilakukan Maker setelah VP dikembalikan, dan persetujuan sebelumnya otomatis gugur.
- Rekening penerima tidak bisa diketik bebas di VP: harus berasal dari daftar rekening yang sudah disetujui. Data rekening disalin ke VP saat diajukan, sehingga perubahan rekening setelahnya tidak mengubah VP yang sedang berjalan.
- Jejak audit (`audit_log`) dan riwayat VP (`vp_history`) dilindungi trigger database yang menolak UPDATE dan DELETE. Waktu dicatat oleh server.
- PDF memuat SHA-256 isi VP. Halaman `/v/<kode>` (dari QR) memberi tahu apakah data masih cocok.
- Reset kata sandi hanya oleh Admin (permintaan disampaikan di luar aplikasi); setiap reset tercatat di jejak audit.
- Kata sandi disimpan dengan hash scrypt. Login gagal 5 kali dalam 10 menit akan diblokir sementara. Setiap formulir dilindungi token CSRF, dan unggahan dibatasi jenis serta ukurannya.

## Struktur kode
```
src/server.js       pengaturan aplikasi, keamanan header, sesi
src/routes.js       semua halaman dan aksi per peran
src/vp-service.js   aturan alur VP, penomoran, sidik jari dokumen
src/db.js           skema database dan trigger audit
src/pdf.js          PDF VP
src/importer.js     baca CSV/Excel penerima massal
src/security.js     kata sandi, sesi, CSRF, batas login
src/seed.js         akun dan data uji coba
views/              tampilan (EJS)
public/             CSS, JS, logo, font
test/flow.test.js   uji alur end-to-end
```

## Riwayat versi
- 1.0.4 — Rincian penerima VP massal di daftar persetujuan dibuka sebagai baris selebar tabel. Pada HP, teks panjang di kartu (nomor referensi, uraian) turun ke baris sendiri dan tidak lagi terpotong di tepi layar.
- 1.0.3 — Unggah massal ditolak bila bank, nama, atau atas nama tidak cocok dengan rekening terdaftar. Kotak cari rekening pada isian penerima. Checker dan Approver 1 dapat melihat rincian penerima VP massal langsung di daftar persetujuan; Checker dapat menyetujui per baris atau beberapa VP sekaligus. Lampiran PDF dan gambar dapat dipratinjau tanpa diunduh.
- 1.0.2 — List pembayaran dihapus. Sesdir hanya mencetak PDF VP yang sudah disetujui Approver 1. Akun contoh approver1 memakai nama jabatan.
- 1.0.1 — Tampilan tablet dan HP: menu pindah ke atas di layar ≤1100 px, tabel menjadi kartu di layar ≤900 px, kotak persetujuan tampil sebelum rincian, nama lampiran panjang tidak lagi melebarkan halaman.

## Catatan dan batasan versi 1.0
- Persetujuan online adalah persetujuan internal yang tercatat, bukan tanda tangan elektronik tersertifikasi (PSrE).
- Notifikasi email belum ada. Saat ini setiap pengguna melihat tugasnya di Beranda dan pada angka di menu. Rencana tahap 2: notifikasi email lewat SMTP kantor.
- Login memakai nama pengguna dan kata sandi lokal. Login dengan Google Workspace (SSO) bisa ditambahkan di tahap 2.
- `npm audit` melaporkan satu temuan tingkat *moderate* pada pustaka `uuid` di dalam `exceljs`. Fungsi yang terdampak tidak dipakai aplikasi ini (aplikasi hanya membaca Excel). Pantau pembaruan `exceljs`.
- Logo PCS di `public/img/` adalah milik PT Petrokopindo Cipta Selaras. Font Signika memakai lisensi SIL OFL (lihat `public/fonts/`).
