# Manuscript Scholar Plugins (tema OJS 3.3)

Tema **Manuscript Scholar** menggabungkan tampilan **Manuscript** dari PKP ([pkp/defaultManuscript](https://github.com/pkp/defaultManuscript), cabang `stable-3_3_0`): halaman putih berbingkai di atas latar abu-abu seperti selembar kertas, judul Montserrat, teks Noto Serif, dan garis aksen tipis di bawah judul, dengan **semua fitur tema Lite Scholar**. Tema ini child theme dari **Default Theme** OJS 3.3.x, seperti Manuscript sendiri: tidak ada template inti OJS yang disalin, jadi pembaruan OJS 3.3.x tetap aman.

- **Tampilan**: berkas LESS Manuscript (`styles/*.less`) dikompilasi bersama stylesheet Default Theme, lalu satu berkas CSS kecil (`styles/manuscriptScholar.css`) memberi gaya pada elemen-elemen fitur dengan gaya Manuscript (kotak abu-abu muda bergaris tipis, label Montserrat huruf kapital, garis aksen).
- **Fitur**: kode PHP dan JavaScript Lite Scholar, diganti nama menjadi Manuscript Scholar (kelas CSS berawalan `ms-`).

Tema ini mandiri: plugin Manuscript (`defaultManuscript`) tidak perlu dipasang; font Montserrat ikut di folder `fonts/`.

## Fitur

### Dari Manuscript

- Tata letak berbingkai (halaman putih dengan bayangan tipis di atas latar abu-abu), header berwarna dasar, menu utama putih dengan huruf kapital Montserrat dan garis bawah warna dasar saat disorot.
- Judul blok sidebar, judul bagian daftar isi, dan label artikel bergaris aksen 3 px.
- Detail artikel (kolom samping) dalam kotak abu-abu muda bergaris tipis; tombol galeri dan "Make a Submission" bergaya tombol Manuscript.
- Dua warna yang bisa dipilih per jurnal di **Website → Appearance → Theme**: **Base colour** (warna header, tombol, dan tautan; opsi bawaan Default Theme) dan **Accent colour** (warna garis aksen dan tombol saat disorot; bawaan kuning `#F7BC4A`). Lihat [Warna](#warna).
- Font Montserrat (lokal) dan Noto Serif (dari Default Theme); pilihan *Typography* dan *Use homepage image as header* dari Default Theme disembunyikan, seperti di Manuscript.
- Dukungan bahasa kanan-ke-kiri (Arab) dan terjemahan Manuscript (Arab, Ceko, Dansk, Jerman, Spanyol, Italia, Portugis, Portugis Brasil, Turki), ditambah Indonesia dan Inggris untuk semua teks fitur.

### Dari Lite Scholar

- **Afiliasi dan negara di bawah nama penulis** pada halaman artikel. Negara diambil dari kolom *Country* pada metadata penulis dan selalu ditambahkan di belakang afiliasi dengan tanda koma pada baris yang sama, mis. "Universitas Indonesia, Indonesia", juga ketika afiliasi sudah menyebut negara. Penulis tanpa afiliasi mendapat baris negara sendiri di bawah nama. Tema menyisipkan negara pada halaman yang sudah dirender (penulis dicocokkan lewat nama yang dicetak template), sehingga data penulis dan template Default Theme tidak diubah. Jarak nama, afiliasi, dan negara dirapatkan, dengan jarak jelas antar penulis.
- **Badge kutipan Dimensions** di halaman artikel, tepat di atas baris DOI (hanya untuk artikel yang punya DOI). Tema membaca DOI dari halaman yang dirender dan menyisipkan badge resmi Dimensions (`badge.dimensions.ai`, gratis, tanpa API key); angka kutipan diambil browser pengunjung dari Dimensions, bukan oleh server jurnal, dan badge tetap tampil walau kutipannya 0. Efek samping: halaman artikel memuat satu skrip dari domain pihak ketiga itu. Untuk mematikan, hapus injeksi `dimensions badge` di `handleDisplay` (`ManuscriptScholarThemePlugin.inc.php`).
- **Cakupan terbitan** (*Issue Coverage*) pada halaman terbitan dan beranda: total artikel dan **total halaman** (jumlah halaman semua artikel dari kolom *Pages*; "863-875" dihitung 13 halaman, angka tunggal 1, huruf diabaikan, kolom kosong tidak dihitung), jumlah penulis dan negara, serta daftar negara dengan bendera dan jumlah penulis, mis. "Issue Coverage: 45 Author(s) from 2 Country(s)". Kotak ini ditempatkan di sebelah kanan cover terbitan (layar 768 px ke atas), di atas deskripsi dan tanggal terbit. Setiap penulis dihitung satu kali per terbitan (nama yang sama dengan email yang sama; dua penulis berbeda yang memakai satu alamat email tetap dihitung sendiri-sendiri). Bendera berupa SVG lokal yang hanya dimuat untuk negara yang tampil.
- **Penulis asing (persentase)** di Issue Coverage ("Foreign authors: 9 (12%)") dan di kartu Archives ("12% foreign"). Pilih sekali di **Website → Appearance → Theme → Journal home country / Negara asal jurnal** (daftar nama negara lengkap seperti pada formulir penulis; nama mengikuti bahasa antarmuka, daftar disimpan sehari). Belum dipilih = angka tidak tampil. Bila daftar negara tidak bisa dimuat, kolomnya berubah menjadi isian teks untuk kode dua huruf (mis. `ID`). Persentase dihitung dari penulis yang negaranya dikenal, tidak pernah 0% bila ada penulis asing, dan ikut cache.
- **Negara dari afiliasi bila kolom *Country* kosong** (untuk hitungan Issue Coverage dan kartu Archives): bagian terakhir afiliasi setelah koma dicocokkan dengan nama negara (bahasa antarmuka dan Inggris, serta singkatan umum seperti USA, UK, UAE). "Universitas Indonesia, Depok, Indonesia" dibaca Indonesia; "Universitas Indonesia" saja tidak ditebak. Kolom *Country* yang terisi selalu diutamakan.
- **Kartu statistik di halaman Archives**: di bawah judul tiap terbitan tampil jumlah **penulis** (unik per terbitan), jumlah **negara** beserta sampai 6 bendera (selebihnya "+N"), dan jumlah **artikel** (dengan **total halaman** di bawahnya). Kartu hanya dipasang bila jumlah blok terbitan di halaman sama dengan jumlah terbitan (template bawaan); jika plugin *Author Origins* masih aktif, tema tidak menambah kartu lagi supaya tidak dobel.
- **Baris aksi di bawah nama penulis** pada daftar artikel (daftar isi terbitan, beranda, hasil pencarian): *Show Abstract | Download PDF | DOI: 10.xxxx/… | Check in Google Scholar*, sebagai tombol bergaris tipis warna dasar dengan huruf Montserrat (terisi saat disorot atau saat abstrak terbuka). *Show Abstract* membuka dan menutup abstrak di tempat (tanpa JavaScript tautannya membuka halaman artikel). Tombol "PDF" bawaan artikel dihapus dan tautannya dipindahkan ke *Download PDF* (tombol galeri lain, mis. HTML, tetap ada). *Check in Google Scholar* mencari judul tepat di Google Scholar; tombol **DOI** membuka doi.org di tab baru. Tautan yang datanya tidak ada tidak ditampilkan. Antar artikel diberi garis pembatas tipis (tidak setelah artikel terakhir).
- **Terbitan terkini di beranda dilipat** menjadi 5 artikel pertama (urutan menurut nomor halaman). Tombol **Read more** (*Selengkapnya*) membuka seluruh artikel terbitan itu di halaman yang sama dan berubah menjadi **Show less** (*Tampilkan lebih sedikit*) untuk melipatnya kembali. Semua artikel sudah ada di halaman (yang tersembunyi diberi atribut `hidden`), jadi tidak ada pemuatan tambahan. Tanpa JavaScript tombol menjadi tautan ke halaman terbitan. Jumlahnya diatur lewat konstanta `HOME_ARTICLE_LIMIT` di `ManuscriptScholarThemePlugin.inc.php`.
- **Artikel diurutkan menurut nomor halaman awal** di beranda dan di halaman terbitan, per bagian. Angka pertama dari rentang halaman dipakai (`1029-1038` → 1029, `e12` → 12). Artikel tanpa nomor halaman diletakkan di akhir bagiannya dengan urutan semula.
- **Kartu kategori di beranda**, tepat di atas terbitan terkini: satu kartu per Kategori OJS yang punya artikel di **Current Issue**, dengan nama kategori dan jumlah artikel; klik kartu untuk membuka daftar artikelnya (maks. 20, konstanta `CATEGORY_ARTICLE_LIMIT`).
- **Tiga kartu di beranda** (di dalam halaman putih, sebelum footer): *Latest articles* (5 artikel terbaru, dengan tanggal terbit), *Most read* (dengan jumlah dilihat) dan *Most downloaded* (dengan jumlah unduhan), dari tabel `metrics` OJS 3.3. Kartu tanpa data disembunyikan. Statistik OJS harus aktif agar dua kartu terakhir terisi.
- **Tombol Pilih Semua / Batal Pilih di sisi admin**: di atas daftar artikel halaman ekspor **Crossref** dan di atas tabel **Tasks/Notifikasi**. Skrip terpisah `js/manuscriptScholarAdmin.js` hanya dimuat di halaman admin untuk pengguna yang sudah login. Jika skrip lama masih terpasang (tombol `#btnSelectAllCrossref` / `#btnSelectAllTasks`), tema tidak menambah tombol kedua. Tema ini harus menjadi tema aktif jurnal agar tombolnya muncul.
- **Ukuran file di daftar file admin**: di halaman *Workflow*, *Author Dashboard*, dan halaman **reviewer**, ukuran tiap file tampil kecil di bawah jenisnya; file 5 MB ke atas ditandai merah tebal. Ukuran dibaca langsung dari disk server dengan satu kueri, tanpa mengunduh file; reviewer hanya mendapat ukuran file ulasannya sendiri.
- **Pemuatan gambar**: gambar header diberi `fetchpriority="high"`, sampul terbitan di Archives (kecuali yang pertama) serta gambar dan iframe di sidebar dan footer diberi `loading="lazy"`, kecuali penghitung pengunjung (Flag Counter, StatCounter, Histats, ClustrMaps, dan sejenisnya). Atribut yang sudah ada tidak ditimpa.
- **Deskripsi meta otomatis** (`<meta name="description">`) untuk halaman yang belum punya: abstrak di halaman artikel, judul dan deskripsi terbitan di halaman terbitan, selain itu nama dan deskripsi jurnal (maks. 160 karakter).
- **Menu sidebar bertile**, **lebar halaman 90%**, **header gambar penuh**, dan **footer rapi**: lihat bagian di bawah.
- Fitur-fitur di atas selalu aktif (tidak ada sakelar di Appearance → Theme).

## Instalasi

1. Unduh `manuscriptScholar-vX.Y.Z.tar.gz` dari halaman **Releases** repo ini (dilampirkan otomatis saat rilis dipublikasikan), atau buat sendiri dengan `scripts/package-theme.sh` (dari akar repo).
2. Masuk sebagai **Site Administrator** → **Administration → Hosted Journals → Manage Plugins → Upload a New Plugin**.
3. Unggah paket tersebut. Atau salin isi repo ke folder `plugins/themes/manuscriptScholar` lalu bersihkan cache OJS.
4. Buka **Website → Appearance → Theme**, pilih **Manuscript Scholar Plugins**, simpan.

Tema ini membutuhkan Default Theme (bawaan OJS) tetap terpasang.

### Instalasi manual via cPanel File Manager

1. Download `manuscriptScholar-vX.Y.Z.tar.gz` dari halaman **Releases**, atau download ZIP repo ini.
2. Di cPanel File Manager, buka `public_html/plugins/themes/`.
3. Upload file lalu klik kanan → **Extract** di folder `themes/`.
4. Pastikan hasil extract membentuk folder bernama **`manuscriptScholar`** (bukan `Manuscript-Scholar-Plugins` atau nama lain) dengan struktur:
   ```
   plugins/themes/manuscriptScholar/
   ├── index.php
   ├── ManuscriptScholarThemePlugin.inc.php
   ├── version.xml
   ├── classes/
   ├── fonts/
   ├── locale/
   └── styles/
   ```
   > **Penting:** nama folder harus tepat `manuscriptScholar`. Font Montserrat dimuat dari `plugins/themes/manuscriptScholar/fonts/`, dan nama folder yang salah juga dapat menyebabkan HTTP 500 karena OJS tidak menemukan class plugin.
5. Di OJS admin: **Settings → Website → Plugins** → aktifkan **Manuscript Scholar Plugins**.
6. **Settings → Website → Appearance → Theme** → pilih **Manuscript Scholar Plugins** → simpan.
7. Jalankan **Administration → Clear Template Cache** dan **Clear Data Caches**.

### Penyebab umum HTTP 500 setelah instalasi

| Gejala | Penyebab | Solusi |
|--------|----------|--------|
| HTTP 500 setelah aktifkan tema | Nama folder bukan `manuscriptScholar` | Rename folder di `plugins/themes/` menjadi `manuscriptScholar` |
| HTTP 500 setelah aktifkan tema | File tidak lengkap (folder `classes/`, `styles/` atau `locale/` tidak ada) | Upload ulang dengan paket lengkap dari Releases |
| Tema tidak muncul di daftar plugin | Default Theme tidak terpasang | Pastikan `plugins/themes/default/` ada dan aktif |
| Judul tampil dengan font cadangan | Nama folder salah sehingga `fonts/` tidak ditemukan | Rename folder menjadi `manuscriptScholar`, lalu Clear Template Cache |
| Tema muncul tapi tidak bisa dipilih | Cache lama | Jalankan Clear Template Cache + Clear Data Caches |

### Pindah dari Lite Scholar atau Manuscript

Pengaturan tema disimpan per plugin, jadi setelah berganti tema pilih ulang **Base colour**, **Accent colour**, dan **Negara asal jurnal** di Appearance → Theme. Daftar menu sidebar lama dengan `class="ls-menu"` perlu diganti menjadi `class="ms-menu"`. Lite Scholar atau Manuscript boleh tetap terpasang; cukup pilih Manuscript Scholar sebagai tema aktif.

## Warna

Dua warna diatur per jurnal di **Website → Appearance → Theme**:

- **Base colour** (opsi bawaan Default Theme, dipakai seperti di Manuscript): latar header, tombol, garis bawah footer, tile menu sidebar yang aktif, ikon kartu Archives. Nilai bawaan Default Theme (`#1E6292`) dibaca sebagai warna Manuscript `#4B7D92`. Tautan dan tombol bergaris ikut memakai warna ini bila kontrasnya terhadap putih cukup (minimal 4,5:1); warna dasar yang terang (mis. kuning) tetap memberi tautan berwarna Manuscript agar terbaca, dan teks header otomatis menjadi gelap.
- **Accent colour**: garis tipis di bawah judul, di kiri kotak Issue Coverage, kartu Archives, dan panel abstrak, serta tombol Manuscript saat disorot. Warna terang paling cocok; jangan dipakai untuk teks.

Tema mengisi variabel LESS Manuscript (`@bg-base`, `@text-bg-base`, `@primary`, `@accent`) dan memasang variabel CSS (`--ms-base`, `--ms-on-base`, `--ms-primary`, `--ms-primary-dark`, `--ms-primary-rgb`, `--ms-accent`) untuk elemen buatan tema. Isian yang bukan kode warna hex tidak disimpan. Setelah mengganti warna, jalankan **Clear Template Cache** dan **Clear Data Caches** lalu Ctrl+F5, karena OJS menyimpan CSS hasil kompilasi.

## Lebar halaman 90%

Di layar 992 px ke atas, halaman putih Manuscript selebar **90% layar** (Manuscript sendiri berhenti di 1160 px) dengan jarak 30 px di atas dan bawah. Header, konten, dan footer mengisi halaman putih itu; isi berjarak 2,143rem dari tepi kiri dan kanan halaman putih, sama dengan header Manuscript. Kolom utama mengisi sisa lebar; sidebar selebar 26% (minimum 300 px, maksimum 520 px) dengan garis pembatas tipis di kirinya. Di bawah 992 px tata letak responsif bawaan tetap dipakai. Ubah angka `90%` dan `26%` pada bagian "Page width" di `styles/manuscriptScholar.css` untuk mengaturnya.

## Header gambar penuh

Unggah gambar header yang lebar, misalnya **1800 × 293 px** (rasio sekitar 6:1; PNG/JPG/WebP, usahakan di bawah 300 KB) lewat **Website → Appearance → Setup → Logo**. Di layar 992 px ke atas gambar memenuhi lebar halaman putih dari tepi ke tepi (seperti kop surat) dengan proporsi tetap, dan Register/Login berada di sudut kanan atas gambar. Jurnal tanpa logo tetap memakai header teks Manuscript.

Bila sisi kiri gambar kosong (transparan) lebih lebar dari jarak isi halaman, tema membaca gambar itu (lewat canvas) dan menggesernya ke kiri sehingga logo yang terlihat dimulai tepat di tepi kiri teks halaman; bagian yang keluar dari halaman putih dipotong. Jika browser menolak membaca gambar, gambar dibiarkan apa adanya. Cara paling andal tetap memotong ruang kosong di file gambarnya.

## Menu sidebar otomatis (custom block)

Di **Settings → Website → Setup → Sidebar → Custom Block Manager**, buat blok dan isi dengan daftar bullet biasa yang diberi `class="ms-menu"` (mode **Source code**). Tema menampilkannya sebagai deretan tile abu-abu muda dengan huruf Montserrat; tile yang disorot dan halaman aktif berwarna dasar dengan garis aksen di kiri. Ikon Font Awesome dipilih otomatis berdasarkan kata pada URL tautan (mis. `editorialTeam`, `peerReviewers`, `authorFees`). Beri `class="home"` pada `<li>` untuk ikon rumah. Contoh siap tempel: [`examples/sidebar-menu.html`](examples/sidebar-menu.html). Daftar biasa tanpa `class="ms-menu"` tidak diubah.

## Footer

Footer tetap bergaya Manuscript (latar abu-abu sangat muda dengan garis warna dasar di bawahnya). Di layar 768 px ke atas teks footer berada di kiri dan logo "Platform & workflow by OJS / PKP" (lebar 180 px) di kanan, keduanya di tengah secara vertikal. Teks footer dan kartu beranda dimulai tepat di tempat teks konten utama dimulai dan berakhir tepat di tempat sidebar berakhir; posisinya diukur di browser oleh `js/manuscriptScholar.js`.

## Ukuran huruf

Secara bawaan tema memakai ukuran huruf Manuscript apa adanya di semua halaman. Jika Anda ingin menaikkan semua ukuran huruf, ubah `--ms-font-bump` (mis. `2px`) dan `--ms-title-bump` (tambahan untuk judul daftar artikel) di bagian atas `styles/manuscriptScholar.css`; `js/manuscriptScholar.js` akan menambahkannya ke setiap ukuran teks (hanya di halaman yang menjalankan skrip).

## Diagnosis jika fitur tidak muncul

Setiap halaman publik diakhiri komentar HTML, misalnya `<!-- manuscriptScholar X.Y.Z -->`. Untuk **Site Administrator** yang sedang login, komentar itu memuat status tiap fitur (tidak ada data, penanda HTML tidak ditemukan, atau pesan error). Buka halaman, tekan Ctrl+U, lalu cari `manuscriptScholar` di bagian paling bawah.

Baris `issue coverage: issue N, A published articles, P authors, C countries (ID, MY, …), K author bylines without a valid country` menunjukkan angka yang dipakai kotak Issue Coverage. Jika kartu kategori tidak muncul, lihat baris `categories with articles in current issue`.

## Cache

- **CSS dan JavaScript**: URL `manuscriptScholar.css` dan `js/manuscriptScholar.js` diberi `?v=<versi>`, sehingga browser, Cloudflare, dan cache server mengambil berkas baru setiap kali versi tema naik. Stylesheet Manuscript dikompilasi OJS dan disimpan di cache OJS: jalankan **Clear Template Cache** dan **Clear Data Caches** setelah memasang atau memperbarui tema.
- **Data beranda dan daftar artikel**: kartu beranda, kartu kategori, kotak Issue Coverage, kartu Archives, dan data tombol aksi artikel disimpan sementara selama **30 menit** (konstanta `CACHE_TTL`; `0` mematikan cache) di folder cache OJS (`cache/manuscriptScholar`), terpisah per jurnal, bahasa, dan versi tema. **Site Administrator yang sedang login selalu mendapat data terbaru** dan sekaligus memperbarui salinan tersimpan. Kunci cache memuat sidik jari artikel terbit, jadi saat artikel diterbitkan, ditarik, atau diedit, data langsung dibangun ulang.
- Bila server menjalankan PHP-FPM atau LiteSpeed, data yang sudah lewat 30 menit tetap langsung ditampilkan dan dibangun ulang **setelah** halaman terkirim ke pengunjung. Salinan yang lebih tua dari 7 hari (`STALE_TTL`) dibangun ulang langsung.

## Aksesibilitas

Tombol *Show Abstract* memakai `role="button"`, `aria-expanded` dan `aria-controls` dan bereaksi pada tombol spasi. Tombol, tile menu, dan kartu kategori punya cincin fokus keyboard yang jelas. Border tombol bergaris sengaja lembut; untuk kontras 3:1 (WCAG 1.4.11) naikkan alfa `--ms-pill-border` di CSS menjadi `0.6`.

## Kompatibilitas

Dibuat untuk **OJS 3.3.x** dengan Default Theme (berkas LESS Manuscript dari cabang `stable-3_3_0`). Statistik "Most read/downloaded" membaca tabel `metrics` yang dihapus di OJS 3.4, dan penyisipan halaman bergantung pada markup Default Theme 3.3. Di OJS 3.4 atau lebih baru tema ini perlu penyesuaian.

## Pengembangan

```bash
php tests/themeTest.php                 # logika PHP dan berkas tema (tanpa instalasi OJS)
node --check js/manuscriptScholar.js    # sintaks JavaScript
scripts/check-version.sh                # semua nomor versi sama dengan version.xml
scripts/check-locales.sh                # semua locale punya kunci yang sama dengan en_US
```

CI (`.github/workflows/quality.yml`) menjalankan semuanya di PHP 7.4, 8.1 dan 8.2. `scripts/package-theme.sh` membuat paket dari salinan repo dan memperkecil `styles/manuscriptScholar.css` di dalam paket.

### Merilis versi baru

1. `scripts/bump-version.sh X.Y.Z` (mengubah `version.xml` beserta tanggalnya, konstanta `VERSION`, dan header CSS), lalu commit dan merge.
2. Di GitHub: **Releases → Draft a new release**, tag `vX.Y.Z` (target `main`), **Publish**.
3. Workflow `release.yml` memeriksa tag sama dengan `version.xml`, menjalankan tes, membuat `manuscriptScholar-vX.Y.Z.tar.gz`, dan melampirkannya ke Release.

## Kredit dan lisensi

- Tampilan: **Manuscript** oleh Public Knowledge Project (Nate Wright, Stefan Weil, dan kontributor), lisensi GNU GPL.
- Fitur: **Lite Scholar Theme** ([fauziddin-tech/daffa-theme-ojs33](https://github.com/fauziddin-tech/daffa-theme-ojs33)).
- Font Montserrat: SIL Open Font License. Bendera: flag-icons (MIT). Lihat `THIRD_PARTY_NOTICES.md`.

GPL-3.0-or-later. Lihat `LICENSE`.
