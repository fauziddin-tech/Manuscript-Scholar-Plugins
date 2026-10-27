# Individualize Scholar (tema OJS 3.3)

Tema **Individualize Scholar** memadukan tampilan dan pengaturan **Individualize Theme** dari Publia ([NateWr/individualizeTheme](https://github.com/NateWr/individualizeTheme), cabang `stable-3_3_0`) dengan **fitur-fitur Manuscript Scholar**. Tema ini *child theme* dari Individualize, seperti contoh resmi [exampleIndividualizeChild](https://github.com/NateWr/exampleIndividualizeChild): semua template, stylesheet, dan opsi Individualize tetap dipakai apa adanya, lalu tema ini menambahkan fitur ilmiah di atasnya.

## Kebutuhan

- OJS **3.3.0-21** atau lebih baru (syarat Individualize), PHP 8.0 ke atas.
- Plugin **Individualize Theme** terpasang dan aktif (**Settings → Website → Plugins → Plugin Gallery → Individualize Theme → Install**). Individualize tidak perlu dipilih sebagai tema; cukup terpasang dan aktif.
- Tema ini boleh dipasang berdampingan dengan Manuscript Scholar (nama kelas PHP berawalan `Is`, tidak bentrok dengan `Ms`).

## Instalasi

1. Pasang **Individualize Theme** dari Plugin Gallery (lihat di atas).
2. Unduh `individualizeScholar-vX.Y.Z.tar.gz` dari halaman **Releases** repo ini (tag `individualizeScholar-vX.Y.Z`), atau buat sendiri dengan `scripts/package-individualize-scholar.sh`.
3. **Settings → Website → Plugins → Upload a New Plugin**, unggah paket tersebut. Atau ekstrak di `plugins/themes/` sehingga terbentuk folder **`individualizeScholar`**.
4. **Settings → Website → Appearance → Theme**, pilih **Individualize Scholar**, simpan.
5. Jalankan **Administration → Clear Template Cache** dan **Clear Data Caches**.

> Pakai paket dari Releases. Folder `individualizeScholar/` di repo tidak memuat bendera negara (`flags/`); paket menambahkannya dari akar repo. Tanpa folder itu kode negara tampil sebagai teks.

## Pengaturan

Semua opsi Individualize ada di **Appearance → Theme**: tata letak header, tagline, posisi gambar beranda, **Homepage Blocks**, cara menampilkan penulis, metadata yang disorot, teks lengkap HTML, tampilan arsip, font (plugin Google Fonts), dan warna (mode sederhana: *Primary* dan *Secondary*; mode lanjut: warna tiap bagian). Lihat [panduan Individualize](https://github.com/NateWr/individualizeTheme/blob/stable-3_3_0/docs/usage.md).

Tema ini menambah:

- **Journal home country / Negara asal jurnal**: untuk persentase penulis asing di Issue Coverage dan kartu Archives (belum dipilih = angka tidak tampil).
- **Homepage cards / Kartu beranda** (kotak centang, bawaan keduanya aktif), sebagai tambahan **Homepage Blocks** Individualize:
  - **Kategori pada terbitan terkini**: satu kartu per kategori yang punya artikel di terbitan terkini, dengan jumlah artikel; klik untuk membuka daftar artikelnya (maks. 20). Ditempatkan tepat di atas blok terbitan terkini (*Current Issue* daftar isi atau ringkasan), atau di akhir bila blok itu tidak dipilih.
  - **Artikel terbaru, terbanyak dibaca, dan terbanyak diunduh**: tiga kartu di akhir beranda, dari tabel `metrics` OJS 3.3; kartu tanpa data disembunyikan.

Kedua kartu disisipkan saat beranda dirender dan tidak pernah disimpan ke opsi Homepage Blocks milik Individualize, jadi berganti kembali ke Individualize biasa (atau menghapus tema ini) tidak membuat beranda error. Untuk menampilkan daftar isi terbitan terkini (dengan Issue Coverage dan tombol *Read more*), centang **Current Issue (Table of Contents)** di Homepage Blocks.

## Fitur dari Manuscript Scholar

| Fitur | Tempat |
| --- | --- |
| Negara penulis di belakang afiliasi ("Universitas Indonesia, Indonesia"); penulis tanpa afiliasi mendapat baris negara sendiri | Detail penulis di halaman artikel |
| Badge kutipan **Dimensions** (artikel ber-DOI) | Halaman artikel, di atas tabel *Article details* (tepat di atas baris DOI) |
| **Issue Coverage**: total artikel dan halaman, jumlah penulis dan negara, bendera per negara, persentase penulis asing | Halaman terbitan dan blok daftar isi terbitan terkini di beranda, di atas deskripsi terbitan |
| Baris aksi: *Show Abstract \| Download PDF \| DOI \| Check in Google Scholar*; tombol galeri PDF dipindahkan ke *Download PDF*, galeri lain tetap | Setiap ringkasan artikel (daftar isi, beranda, pencarian, kategori) |
| Artikel diurutkan menurut nomor halaman awal per bagian | Halaman terbitan dan beranda |
| Terbitan terkini di beranda dilipat menjadi 5 artikel dengan tombol **Read more / Show less** (tanpa JavaScript tombol membuka halaman terbitan) | Blok daftar isi terbitan terkini |
| Kartu statistik terbitan: penulis (dan % asing), negara dengan bendera, artikel dan halaman | Halaman Archives, tampilan daftar (bukan tampilan galeri sampul) |
| Tiga kartu beranda dan kartu kategori | Beranda (opsi *Homepage cards*, lihat di atas) |
| Tombol **Select all / Clear selection** (ekspor Crossref, Tasks) dan ukuran file di daftar file (Workflow, Author Dashboard, reviewer) | Sisi admin |
| Negara dari afiliasi bila kolom *Country* kosong, cache 30 menit dengan sidik jari konten, deskripsi meta otomatis, `fetchpriority` logo header, `loading="lazy"` sampul arsip dan gambar footer | Otomatis |

Fitur khusus tata letak Manuscript (lebar halaman 90%, header gambar penuh, footer rapi, menu sidebar bertile `ms-menu`, `--ms-font-bump`) tidak dibawa, karena Individualize punya tata letak dan opsi header sendiri dan menaruh blok sidebar di footer.

Warna dan font fitur mengikuti variabel CSS Individualize (`--color-page-links`, `--color-secondary`, `--color-block-background`, `--font-titles`, `--font-actions`, dan seterusnya), jadi otomatis menyesuaikan pilihan warna/font jurnal.

## Template yang ditimpa

Fitur ditempatkan lewat template, bukan dengan mengubah HTML yang sudah jadi. Lima template Individualize disalin ke `templates/frontend/components/` dan diberi tambahan (dijelaskan di kepala tiap berkas):

- `article-summary.tpl`: baris aksi, tombol PDF dipindah.
- `author-details.tpl`: negara penulis.
- `issue-toc.tpl`: Issue Coverage dan lipatan "Read more".
- `homepage-blocks/issue-toc.tpl`: mengaktifkan lipatan di beranda.
- `issue-summary.tpl`: kartu statistik Archives.

Jika Individualize memperbarui salah satu template itu, salin versi barunya dan pasang kembali baris bertanda `individualize_scholar_*` / `ms-`.

## Diagnosis

Setiap halaman publik diakhiri komentar `<!-- individualizeScholar X.Y.Z -->`. Untuk Site Administrator yang sedang login, komentar itu memuat status fitur (mis. `article actions: printed 12 time(s)`, `issue coverage: …`, `parent theme: Individualize is not installed or not enabled`). Tekan Ctrl+U dan cari `individualizeScholar` di bagian bawah.

## Pengembangan

Kelas pembantu yang sama dengan Manuscript Scholar (`classes/Is*.inc.php` kecuali `IsArticleOrder` dan `IsPageHints`) serta `js/individualizeScholarAdmin.js` **dihasilkan** dari berkas `Ms*` dengan `scripts/sync-individualize-scholar.sh`. Ubah berkas aslinya di `classes/` atau `js/`, lalu jalankan skrip itu; CI memeriksa salinannya selalu sama.

```bash
php tests/individualizeScholarTest.php                     # logika PHP dan berkas tema
scripts/sync-individualize-scholar.sh --check              # salinan kelas bersama mutakhir
scripts/check-version-individualize-scholar.sh             # nomor versi sama
scripts/package-individualize-scholar.sh                   # paket rilis
```

Rilis: naikkan versi di `individualizeScholar/version.xml`, konstanta `VERSION` di `IndividualizeScholarThemePlugin.inc.php`, dan baris 2 `styles/individualizeScholar.css`, lalu terbitkan Release dengan tag `individualizeScholar-vX.Y.Z`; workflow `release.yml` melampirkan paketnya.

## Lisensi

GPL-3.0-or-later. Template salinan dari Individualize Theme (GPL v3) dan bendera flag-icons (MIT): lihat `THIRD_PARTY_NOTICES.md`.
