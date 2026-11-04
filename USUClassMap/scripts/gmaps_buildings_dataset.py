"""
USU Buildings Seed Dataset Definition for Google Maps Scraper
Lists all 52 buildings grouped by 16 Faculties and Central University Facilities.
"""

USU_BUILDING_TARGETS = [
    # =========================================================================
    # 1. FASILITAS PUSAT UNIVERSITAS & LAYANAN UMUM
    # =========================================================================
    {
        'faculty': 'Fasilitas Pusat Universitas',
        'category': 'Fasilitas Universitas',
        'buildings': [
            {
                'name': 'Gedung Biro Pusat Administrasi (Rektorat USU)',
                'code': 'REKTORAT',
                'floor_count': 3,
                'search_query': 'Biro Rektor Universitas Sumatera Utara Medan',
                'fallback_lat': 3.56270000,
                'fallback_lng': 98.65780000,
                'description': 'Pusat administrasi utama, kantor Rektor dan Wakil Rektor Universitas Sumatera Utara.',
            },
            {
                'name': 'Perpustakaan Pusat Universitas Sumatera Utara',
                'code' : 'PERPUS-PUSAT',
                'floor_count': 4,
                'search_query': 'Perpustakaan Pusat USU Medan',
                'fallback_lat': 3.56700000,
                'fallback_lng': 98.65800000,
                'description': 'Gedung perpustakaan utama, repositori akademik digital, dan ruang belajar bersama.',
            },
            {
                'name': 'Auditorium Universitas Sumatera Utara',
                'code': 'AUDITORIUM',
                'floor_count': 2,
                'search_query': 'Auditorium Universitas Sumatera Utara Medan',
                'fallback_lat': 3.56380000,
                'fallback_lng': 98.65920000,
                'description': 'Gedung pertemuan akbar, wisuda, kuliah umum perdana, dan acara universitas.',
            },
            {
                'name': 'Pusat Bahasa & Kantor Urusan Internasional (KUI)',
                'code': 'PUSAT-BAHASA',
                'floor_count': 2,
                'search_query': 'Pusat Bahasa USU Medan',
                'fallback_lat': 3.56560000,
                'fallback_lng': 98.65820000,
                'description': 'Pusat pengujian bahasa, kursus bahasa asing, dan layanan mobilitas internasional mahasiswa.',
            },
            {
                'name': 'Gedung Kuliah Bersama 1 (GK1 / MKWK)',
                'code': 'GK1',
                'floor_count': 3,
                'search_query': 'Gedung Kuliah Bersama 1 GK1 USU Medan',
                'fallback_lat': 3.56524500,
                'fallback_lng': 98.65652000,
                'description': 'Gedung perkuliahan umum Mata Kuliah Wajib Kurikulum (MKWK) dan MKWU lintas fakultas.',
            },
            {
                'name': 'Gedung Kuliah Bersama 2 (GK2 / MKWU)',
                'code': 'GK2',
                'floor_count': 4,
                'search_query': 'Gedung Kuliah Bersama 2 GK2 USU Medan',
                'fallback_lat': 3.56612000,
                'fallback_lng': 98.65734000,
                'description': 'Gedung kuliah terpadu, laboratorium multimedia, dan ruang kelas MKWU.',
            },
            {
                'name': 'Pusat Inkubator Bisnis & Kewirausahaan (CIE USU)',
                'code': 'CIE-USU',
                'floor_count': 2,
                'search_query': 'Centre for Innovation and Entrepreneurship CIE USU Medan',
                'fallback_lat': 3.56480000,
                'fallback_lng': 98.65860000,
                'description': 'Gedung pengembangan inovasi bisnis, startup mahasiswa, dan program kewirausahaan.',
            },
            {
                'name': 'Masjid Dakwah USU',
                'code': 'MSJ-DAKWAH',
                'floor_count': 2,
                'search_query': 'Masjid Dakwah USU Medan',
                'fallback_lat': 3.56500000,
                'fallback_lng': 98.66050000,
                'description': 'Masjid utama kampus USU untuk ibadah dan pusat kegiatan keislaman sivitas akademika.',
            },
            {
                'name': 'Asrama Mahasiswa USU (Putra & Putri)',
                'code': 'ASRAMA-USU',
                'floor_count': 4,
                'search_query': 'Asrama Mahasiswa USU Medan',
                'fallback_lat': 3.55950000,
                'fallback_lng': 98.65150000,
                'description': 'Kompleks asrama tempat tinggal mahasiswa penerima beasiswa dan mahasiswa rantau.',
            },
        ],
    },

    # =========================================================================
    # 2. LAYANAN KESEHATAN UNIVERSITAS
    # =========================================================================
    {
        'faculty': 'Layanan Kesehatan Universitas',
        'category': 'Layanan Kesehatan',
        'buildings': [
            {
                'name': 'Rumah Sakit Universitas Sumatera Utara (RS USU)',
                'code': 'RS-USU',
                'floor_count': 5,
                'search_query': 'Rumah Sakit USU Medan',
                'fallback_lat': 3.56020000,
                'fallback_lng': 98.65250000,
                'description': 'Rumah Sakit Pendidikan Utama Tipe B dengan fasilitas rawat inap, IGD, dan laboratorium klinik.',
            },
            {
                'name': 'Poliklinik & Pusat Layanan Kesehatan Mahasiswa USU',
                'code': 'POLIKLINIK',
                'floor_count': 2,
                'search_query': 'Poliklinik USU Medan',
                'fallback_lat': 3.56150000,
                'fallback_lng': 98.65400000,
                'description': 'Klinik rawat jalan dan pelayanan kesehatan primer gratis untuk mahasiswa dan staf USU.',
            },
        ],
    },

    # =========================================================================
    # 3. SARANA OLAHRAGA & KEMAHASISWAAN
    # =========================================================================
    {
        'faculty': 'Sarana Olahraga & Gelanggang',
        'category': 'Sarana Olahraga',
        'buildings': [
            {
                'name': 'Stadion Mini Universitas Sumatera Utara',
                'code': 'STADION-MINI',
                'floor_count': 2,
                'search_query': 'Stadion Mini USU Medan',
                'fallback_lat': 3.56800000,
                'fallback_lng': 98.65950000,
                'description': 'Stadion sepak bola, lintasan atletik, dan tribun penonton kegiatan olahraga universitas.',
            },
            {
                'name': 'Gelanggang Mahasiswa USU (Gelanma)',
                'code': 'GELANMA',
                'floor_count': 2,
                'search_query': 'Gelanggang Mahasiswa USU Medan',
                'fallback_lat': 3.56760000,
                'fallback_lng': 98.66010000,
                'description': 'Gelanggang olahraga indoor serbaguna untuk basket, voli, bulutangkis, dan pameran UKM.',
            },
        ],
    },

    # =========================================================================
    # 4. FAKULTAS ILMU KOMPUTER & TEKNOLOGI INFORMASI (FASILKOM-TI)
    # =========================================================================
    {
        'faculty': 'Fakultas Ilmu Komputer dan Teknologi Informasi',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'FASILKOM-TI Gedung A (Dekanat & Laboratorium)',
                'code': 'FASILKOM-A',
                'floor_count': 3,
                'search_query': 'Fasilkom TI USU Gedung A Medan',
                'fallback_lat': 3.56450000,
                'fallback_lng': 98.65580000,
                'description': 'Gedung Dekanat, Lab Rekayasa Perangkat Lunak, Lab Jaringan Komputer, dan Tata Usaha.',
            },
            {
                'name': 'FASILKOM-TI Gedung B (Ruang Kuliah & Lab AI)',
                'code': 'FASILKOM-B',
                'floor_count': 3,
                'search_query': 'Fasilkom TI USU Gedung B Medan',
                'fallback_lat': 3.56475000,
                'fallback_lng': 98.65550000,
                'description': 'Gedung perkuliahan Ilmu Komputer dan TI, Lab Artificial Intelligence, dan Lab Multimedia.',
            },
        ],
    },

    # =========================================================================
    # 5. FAKULTAS KEDOKTERAN (FK)
    # =========================================================================
    {
        'faculty': 'Fakultas Kedokteran',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Kedokteran',
                'code': 'FK-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Kedokteran USU Medan',
                'fallback_lat': 3.56180000,
                'fallback_lng': 98.65550000,
                'description': 'Gedung administrasi utama Dekanat Fakultas Kedokteran dan aula pertemuan.',
            },
            {
                'name': 'Gedung Kuliah Pendidikan Dokter FK',
                'code': 'FK-KULIAH',
                'floor_count': 4,
                'search_query': 'Gedung Kuliah Kedokteran USU Medan',
                'fallback_lat': 3.56210000,
                'fallback_lng': 98.65520000,
                'description': 'Ruang kuliah tutorial PBL, ruang kuliah bersama, dan amphitheater kedokteran.',
            },
            {
                'name': 'Gedung Laboratorium Terpadu & Anatomi FK',
                'code': 'FK-LAB',
                'floor_count': 3,
                'search_query': 'Laboratorium Anatomi FK USU Medan',
                'fallback_lat': 3.56240000,
                'fallback_lng': 98.65490000,
                'description': 'Laboratorium Anatomi, Histologi, Fisiologi, dan Keterampilan Klinis (Skills Lab).',
            },
        ],
    },

    # =========================================================================
    # 6. FAKULTAS HUKUM (FH)
    # =========================================================================
    {
        'faculty': 'Fakultas Hukum',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Peradilan Semu FH',
                'code': 'FH-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Hukum USU Medan',
                'fallback_lat': 3.56400000,
                'fallback_lng': 98.65720000,
                'description': 'Gedung kantor Dekan FH, ruang sidang peradilan semu (Moot Court), dan perpustakaan hukum.',
            },
            {
                'name': 'Gedung Kuliah Utama Fakultas Hukum',
                'code': 'FH-KULIAH',
                'floor_count': 3,
                'search_query': 'Gedung Kuliah Fakultas Hukum USU Medan',
                'fallback_lat': 3.56430000,
                'fallback_lng': 98.65690000,
                'description': 'Ruang kelas perkuliahan program sarjana ilmu hukum, hukum pidana, dan perdata.',
            },
        ],
    },

    # =========================================================================
    # 7. FAKULTAS PERTANIAN (FP)
    # =========================================================================
    {
        'faculty': 'Fakultas Pertanian',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Pertanian',
                'code': 'FP-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Pertanian USU Medan',
                'fallback_lat': 3.56350000,
                'fallback_lng': 98.65300000,
                'description': 'Gedung Dekanat FP, aula Soemarsono, dan ruang dosen pengajar pertanian.',
            },
            {
                'name': 'Gedung Kuliah Agroteknologi & Agribisnis',
                'code': 'FP-KULIAH',
                'floor_count': 3,
                'search_query': 'Gedung Agroteknologi Pertanian USU Medan',
                'fallback_lat': 3.56380000,
                'fallback_lng': 98.65270000,
                'description': 'Ruang kuliah program studi Agroteknologi, Agribisnis, Ilmu Kelautan, dan Peternakan.',
            },
            {
                'name': 'Laboratorium Riset Terpadu Pertanian',
                'code': 'FP-LAB',
                'floor_count': 2,
                'search_query': 'Laboratorium Pertanian USU Medan',
                'fallback_lat': 3.56320000,
                'fallback_lng': 98.65330000,
                'description': 'Laboratorium Ilmu Tanah, Proteksi Tanaman, Pemuliaan Tanaman, dan Bioteknologi Pertanian.',
            },
        ],
    },

    # =========================================================================
    # 8. FAKULTAS TEKNIK (FT)
    # =========================================================================
    {
        'faculty': 'Fakultas Teknik',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Pusat Administrasi FT',
                'code': 'FT-DEKANAT',
                'floor_count': 3,
                'search_query': 'Dekanat Fakultas Teknik USU Medan',
                'fallback_lat': 3.56680000,
                'fallback_lng': 98.65350000,
                'description': 'Gedung Dekanat Fakultas Teknik, ruang administrasi akademik, dan aula pertemuan FT.',
            },
            {
                'name': 'Gedung Departemen Teknik Mesin & Industri',
                'code': 'FT-MESIN-IND',
                'floor_count': 3,
                'search_query': 'Teknik Mesin USU Medan',
                'fallback_lat': 3.56720000,
                'fallback_lng': 98.65320000,
                'description': 'Ruang kuliah dan laboratorium Teknik Mesin, Ergonomi, dan Sistem Manufaktur Industri.',
            },
            {
                'name': 'Gedung Departemen Teknik Elektro',
                'code': 'FT-ELEKTRO',
                'floor_count': 3,
                'search_query': 'Teknik Elektro USU Medan',
                'fallback_lat': 3.56750000,
                'fallback_lng': 98.65280000,
                'description': 'Ruang kelas kuliah dan Lab Tenaga Listrik, Telekomunikasi, dan Sistem Kendali Elektro.',
            },
            {
                'name': 'Gedung Departemen Teknik Sipil & Lingkungan',
                'code': 'FT-SIPIL',
                'floor_count': 3,
                'search_query': 'Teknik Sipil USU Medan',
                'fallback_lat': 3.56710000,
                'fallback_lng': 98.65410000,
                'description': 'Ruang kuliah Teknik Sipil, Lab Struktur Material, Lab Mekanika Tanah, dan Hidrologi.',
            },
            {
                'name': 'Gedung Departemen Teknik Kimia & Arsitektur',
                'code': 'FT-KIMIA-ARS',
                'floor_count': 3,
                'search_query': 'Teknik Kimia USU Medan',
                'fallback_lat': 3.56650000,
                'fallback_lng': 98.65250000,
                'description': 'Ruang kuliah Teknik Kimia, Studio Gambar Arsitektur, dan Lab Operasi Teknik Kimia.',
            },
        ],
    },

    # =========================================================================
    # 9. FAKULTAS EKONOMI DAN BISNIS (FEB)
    # =========================================================================
    {
        'faculty': 'Fakultas Ekonomi dan Bisnis',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Ekonomi dan Bisnis',
                'code': 'FEB-DEKANAT',
                'floor_count': 4,
                'search_query': 'Fakultas Ekonomi dan Bisnis USU Medan',
                'fallback_lat': 3.56580000,
                'fallback_lng': 98.65420000,
                'description': 'Gedung Dekanat FEB, ruang administrasi, dan galeri investasi Bursa Efek Indonesia.',
            },
            {
                'name': 'Gedung Kuliah Manajemen, Akuntansi & EP',
                'code': 'FEB-KULIAH',
                'floor_count': 4,
                'search_query': 'Gedung Kuliah FEB USU Medan',
                'fallback_lat': 3.56610000,
                'fallback_lng': 98.65390000,
                'description': 'Ruang perkuliahan S1 Manajemen, Akuntansi, dan Ekonomi Pembangunan.',
            },
            {
                'name': 'Gedung Pascasarjana FEB (MM, MAksi, M.E.)',
                'code': 'FEB-PASCA',
                'floor_count': 3,
                'search_query': 'Magister Manajemen USU Medan',
                'fallback_lat': 3.56550000,
                'fallback_lng': 98.65450000,
                'description': 'Gedung perkuliahan program magister dan doktoral ilmu ekonomi dan bisnis.',
            },
        ],
    },

    # =========================================================================
    # 10. FAKULTAS KEDOKTERAN GIGI (FKG)
    # =========================================================================
    {
        'faculty': 'Fakultas Kedokteran Gigi',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Kuliah FKG',
                'code': 'FKG-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Kedokteran Gigi USU Medan',
                'fallback_lat': 3.56080000,
                'fallback_lng': 98.65680000,
                'description': 'Gedung kantor Dekan FKG, ruang kuliah sarjana kedokteran gigi, dan ruang seminar.',
            },
            {
                'name': 'Rumah Sakit Gigi dan Mulut (RSGM USU)',
                'code': 'RSGM-USU',
                'floor_count': 4,
                'search_query': 'Rumah Sakit Gigi dan Mulut USU Medan',
                'fallback_lat': 3.56110000,
                'fallback_lng': 98.65650000,
                'description': 'Rumah sakit pendidikan gigi dan mulut dengan fasilitas dental unit praktek koas.',
            },
        ],
    },

    # =========================================================================
    # 11. FAKULTAS ILMU BUDAYA (FIB)
    # =========================================================================
    {
        'faculty': 'Fakultas Ilmu Budaya',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Ilmu Budaya',
                'code': 'FIB-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Ilmu Budaya USU Medan',
                'fallback_lat': 3.56510000,
                'fallback_lng': 98.65880000,
                'description': 'Gedung kantor Dekan FIB, laboratorium bahasa, dan museum etnomusikologi.',
            },
            {
                'name': 'Gedung Kuliah Sastra & Ilmu Sejarah FIB',
                'code': 'FIB-KULIAH',
                'floor_count': 3,
                'search_query': 'Gedung Kuliah FIB USU Medan',
                'fallback_lat': 3.56540000,
                'fallback_lng': 98.65850000,
                'description': 'Ruang perkuliahan Sastra Indonesia, Sastra Inggris, Sastra Jepang, dan Ilmu Sejarah.',
            },
        ],
    },

    # =========================================================================
    # 12. FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM (FMIPA)
    # =========================================================================
    {
        'faculty': 'Fakultas MIPA',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Aula FMIPA',
                'code': 'FMIPA-DEKANAT',
                'floor_count': 3,
                'search_query': 'FMIPA USU Medan',
                'fallback_lat': 3.56820000,
                'fallback_lng': 98.65520000,
                'description': 'Gedung administrasi Dekanat FMIPA dan auditorium sains.',
            },
            {
                'name': 'Gedung Departemen Fisika & Kimia FMIPA',
                'code': 'FMIPA-FIS-KIM',
                'floor_count': 3,
                'search_query': 'Departemen Fisika FMIPA USU Medan',
                'fallback_lat': 3.56850000,
                'fallback_lng': 98.65480000,
                'description': 'Ruang kuliah dan laboratorium riset Fisika Material, Elektronika, dan Kimia Terpadu.',
            },
            {
                'name': 'Gedung Departemen Biologi & Matematika FMIPA',
                'code': 'FMIPA-BIO-MAT',
                'floor_count': 3,
                'search_query': 'Departemen Biologi FMIPA USU Medan',
                'fallback_lat': 3.56800000,
                'fallback_lng': 98.65580000,
                'description': 'Ruang kuliah Biologi, Matematika, Statistika, serta Herbarium dan Rumah Kaca.',
            },
        ],
    },

    # =========================================================================
    # 13. FAKULTAS ILMU SOSIAL DAN ILMU POLITIK (FISIP)
    # =========================================================================
    {
        'faculty': 'Fakultas ISIP',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Ilmu Sosial dan Ilmu Politik',
                'code': 'FISIP-DEKANAT',
                'floor_count': 3,
                'search_query': 'FISIP USU Medan',
                'fallback_lat': 3.56420000,
                'fallback_lng': 98.65820000,
                'description': 'Gedung Dekanat FISIP, laboratorium radio/televisi kampus, dan ruang seminar.',
            },
            {
                'name': 'Gedung Kuliah Ilmu Komunikasi & Adm. Publik',
                'code': 'FISIP-KULIAH',
                'floor_count': 3,
                'search_query': 'Departemen Ilmu Komunikasi FISIP USU Medan',
                'fallback_lat': 3.56450000,
                'fallback_lng': 98.65790000,
                'description': 'Ruang perkuliahan S1 Ilmu Komunikasi, Ilmu Administrasi Publik, dan Sosiologi.',
            },
        ],
    },

    # =========================================================================
    # 14. FAKULTAS KESEHATAN MASYARAKAT (FKM)
    # =========================================================================
    {
        'faculty': 'Fakultas Kesehatan Masyarakat',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Auditorium FKM',
                'code': 'FKM-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Kesehatan Masyarakat FKM USU Medan',
                'fallback_lat': 3.56290000,
                'fallback_lng': 98.65420000,
                'description': 'Gedung administrasi Dekanat FKM dan auditorium kesehatan masyarakat.',
            },
            {
                'name': 'Gedung Kuliah & Lab Epidemiologi FKM',
                'code': 'FKM-KULIAH',
                'floor_count': 3,
                'search_query': 'Gedung Kuliah FKM USU Medan',
                'fallback_lat': 3.56310000,
                'fallback_lng': 98.65390000,
                'description': 'Ruang kelas kuliah dan Lab Epidemiologi, Biostatistik, Gizi, dan Kesehatan Lingkungan.',
            },
        ],
    },

    # =========================================================================
    # 15. FAKULTAS PSIKOLOGI (FPsi)
    # =========================================================================
    {
        'faculty': 'Fakultas Psikologi',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Kuliah Psikologi',
                'code': 'FPSI-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Psikologi USU Medan',
                'fallback_lat': 3.56360000,
                'fallback_lng': 98.65500000,
                'description': 'Gedung kantor Dekanat dan ruang kuliah sarjana Psikologi.',
            },
            {
                'name': 'Laboratorium Psikodiagnostika & Konseling',
                'code': 'FPSI-LAB',
                'floor_count': 2,
                'search_query': 'Lembaga Konseling Psikologi USU Medan',
                'fallback_lat': 3.56380000,
                'fallback_lng': 98.65470000,
                'description': 'Pusat layanan psikologi terpadu, ruang observasi klinis, dan laboratorium tes psikologi.',
            },
        ],
    },

    # =========================================================================
    # 16. FAKULTAS KEHUTANAN
    # =========================================================================
    {
        'faculty': 'Fakultas Kehutanan',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Kehutanan',
                'code': 'FAHUTAN-DEK',
                'floor_count': 3,
                'search_query': 'Fakultas Kehutanan USU Medan',
                'fallback_lat': 3.56900000,
                'fallback_lng': 98.65350000,
                'description': 'Gedung administrasi Dekanat Fakultas Kehutanan dan ruang pertemuan.',
            },
            {
                'name': 'Gedung Kuliah & Laboratorium Silvikultur Kehutanan',
                'code': 'FAHUTAN-KUL',
                'floor_count': 2,
                'search_query': 'Laboratorium Kehutanan USU Medan',
                'fallback_lat': 3.56930000,
                'fallback_lng': 98.65320000,
                'description': 'Ruang perkuliahan dan lab Konservasi Sumber Daya Hutan, Dendrologi, dan Silvikultur.',
            },
        ],
    },

    # =========================================================================
    # 17. FAKULTAS FARMASI (FF)
    # =========================================================================
    {
        'faculty': 'Fakultas Farmasi',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat Fakultas Farmasi',
                'code': 'FF-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Farmasi USU Medan',
                'fallback_lat': 3.56220000,
                'fallback_lng': 98.65380000,
                'description': 'Gedung administrasi Dekan Farmasi dan ruang program profesi apoteker.',
            },
            {
                'name': 'Gedung Kuliah & Lab Farmakologi Terpadu',
                'code': 'FF-LAB',
                'floor_count': 3,
                'search_query': 'Laboratorium Farmasi USU Medan',
                'fallback_lat': 3.56250000,
                'fallback_lng': 98.65350000,
                'description': 'Ruang perkuliahan dan Lab Kimia Farmasi, Farmakognosi, dan Teknologi Farmasi.',
            },
        ],
    },

    # =========================================================================
    # 18. FAKULTAS KEPERAWATAN (FKEP)
    # =========================================================================
    {
        'faculty': 'Fakultas Keperawatan',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Kuliah Keperawatan',
                'code': 'FKEP-DEKANAT',
                'floor_count': 3,
                'search_query': 'Fakultas Keperawatan USU Medan',
                'fallback_lat': 3.56120000,
                'fallback_lng': 98.65320000,
                'description': 'Gedung kantor Dekan Keperawatan dan ruang perkuliahan S1 Keperawatan.',
            },
            {
                'name': 'Laboratorium Praktik Klinik Keperawatan',
                'code': 'FKEP-LAB',
                'floor_count': 2,
                'search_query': 'Laboratorium Keperawatan USU Medan',
                'fallback_lat': 3.56150000,
                'fallback_lng': 98.65290000,
                'description': 'Laboratorium mini hospital simulasi Keperawatan Medikal Bedah, Maternitas, dan Anak.',
            },
        ],
    },

    # =========================================================================
    # 19. FAKULTAS VOKASI
    # =========================================================================
    {
        'faculty': 'Fakultas Vokasi',
        'category': 'Fakultas',
        'buildings': [
            {
                'name': 'Gedung Dekanat & Kuliah Terpadu Vokasi',
                'code': 'VOKASI-DEK',
                'floor_count': 4,
                'search_query': 'Fakultas Vokasi USU Medan',
                'fallback_lat': 3.56640000,
                'fallback_lng': 98.65900000,
                'description': 'Gedung perkuliahan program diploma dan sarjana terapan Fakultas Vokasi USU.',
            },
            {
                'name': 'Gedung Laboratorium Terapan & Bengkel Kejuruan',
                'code': 'VOKASI-LAB',
                'floor_count': 3,
                'search_query': 'Laboratorium Vokasi USU Medan',
                'fallback_lat': 3.56670000,
                'fallback_lng': 98.65870000,
                'description': 'Pusat pelatihan vokasi terapan, laboratorium perbankan, dan bengkel mekanik.',
            },
        ],
    },
]
