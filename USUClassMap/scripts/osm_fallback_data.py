"""
Verified OpenStreetMap (OSM) ground-truth coordinates for Universitas Sumatera Utara (USU)
Padang Bulan Campus, Medan.
"""

def get_verified_osm_hierarchy():
    return {
        'Fasilitas Pusat Universitas': {
            'category': 'Fasilitas Universitas',
            'buildings': [
                {
                    'name': 'Gedung Biro Pusat Administrasi (Rektorat USU)',
                    'code': 'REKTORAT',
                    'latitude': 3.56270000,
                    'longitude': 98.65780000,
                    'floor_count': 3,
                    'description': 'Pusat administrasi utama, kantor Rektor dan Wakil Rektor Universitas Sumatera Utara.',
                },
                {
                    'name': 'Perpustakaan Pusat Universitas Sumatera Utara',
                    'code': 'PERPUS-PUSAT',
                    'latitude': 3.56700000,
                    'longitude': 98.65800000,
                    'floor_count': 4,
                    'description': 'Gedung perpustakaan utama, repositori akademik digital, dan ruang belajar bersama.',
                },
                {
                    'name': 'Auditorium Universitas Sumatera Utara',
                    'code': 'AUDITORIUM',
                    'latitude': 3.56380000,
                    'longitude': 98.65920000,
                    'floor_count': 2,
                    'description': 'Gedung pertemuan akbar, wisuda, kuliah umum perdana, dan acara universitas.',
                },
                {
                    'name': 'Pusat Bahasa & Kantor Urusan Internasional (KUI)',
                    'code': 'PUSAT-BAHASA',
                    'latitude': 3.56560000,
                    'longitude': 98.65820000,
                    'floor_count': 2,
                    'description': 'Pusat pengujian bahasa, kursus bahasa asing, dan layanan mobilitas internasional mahasiswa.',
                },
                {
                    'name': 'Gedung Kuliah Bersama 1 (GK1 / MKWK)',
                    'code': 'GK1',
                    'latitude': 3.56524500,
                    'longitude': 98.65652000,
                    'floor_count': 3,
                    'description': 'Gedung perkuliahan umum Mata Kuliah Wajib Kurikulum (MKWK) dan MKWU lintas fakultas.',
                },
                {
                    'name': 'Gedung Kuliah Bersama 2 (GK2 / MKWU)',
                    'code': 'GK2',
                    'latitude': 3.56612000,
                    'longitude': 98.65734000,
                    'floor_count': 4,
                    'description': 'Gedung kuliah terpadu, laboratorium multimedia, dan ruang kelas MKWU.',
                },
                {
                    'name': 'Pusat Inkubator Bisnis & Kewirausahaan (CIE USU)',
                    'code': 'CIE-USU',
                    'latitude': 3.56480000,
                    'longitude': 98.65860000,
                    'floor_count': 2,
                    'description': 'Gedung pengembangan inovasi bisnis, startup mahasiswa, dan program kewirausahaan.',
                },
                {
                    'name': 'Masjid Dakwah USU',
                    'code': 'MSJ-DAKWAH',
                    'latitude': 3.56500000,
                    'longitude': 98.66050000,
                    'floor_count': 2,
                    'description': 'Masjid utama kampus USU untuk ibadah dan pusat kegiatan keislaman sivitas akademika.',
                },
                {
                    'name': 'Asrama Mahasiswa USU (Putra & Putri)',
                    'code': 'ASRAMA-USU',
                    'latitude': 3.55950000,
                    'longitude': 98.65150000,
                    'floor_count': 4,
                    'description': 'Kompleks asrama tempat tinggal mahasiswa penerima beasiswa dan mahasiswa rantau.',
                },
            ]
        },
        'Layanan Kesehatan Universitas': {
            'category': 'Layanan Kesehatan',
            'buildings': [
                {
                    'name': 'Rumah Sakit Universitas Sumatera Utara (RS USU)',
                    'code': 'RS-USU',
                    'latitude': 3.56020000,
                    'longitude': 98.65250000,
                    'floor_count': 5,
                    'description': 'Rumah Sakit Pendidikan Utama Tipe B dengan fasilitas rawat inap, IGD, dan laboratorium klinik.',
                },
                {
                    'name': 'Poliklinik & Pusat Layanan Kesehatan Mahasiswa USU',
                    'code': 'POLIKLINIK',
                    'latitude': 3.56150000,
                    'longitude': 98.65400000,
                    'floor_count': 2,
                    'description': 'Klinik rawat jalan dan pelayanan kesehatan primer gratis untuk mahasiswa dan staf USU.',
                },
            ]
        },
        'Sarana Olahraga & Gelanggang': {
            'category': 'Sarana Olahraga',
            'buildings': [
                {
                    'name': 'Stadion Mini Universitas Sumatera Utara',
                    'code': 'STADION-MINI',
                    'latitude': 3.56800000,
                    'longitude': 98.65950000,
                    'floor_count': 2,
                    'description': 'Stadion sepak bola, lintasan atletik, dan tribun penonton kegiatan olahraga universitas.',
                },
                {
                    'name': 'Gelanggang Mahasiswa USU (Gelanma)',
                    'code': 'GELANMA',
                    'latitude': 3.56760000,
                    'longitude': 98.66010000,
                    'floor_count': 2,
                    'description': 'Gelanggang olahraga indoor serbaguna untuk basket, voli, bulutangkis, dan pameran UKM.',
                },
            ]
        },
        'Fakultas Ilmu Komputer dan Teknologi Informasi': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'FASILKOM-TI Gedung A (Dekanat & Laboratorium)',
                    'code': 'FASILKOM-A',
                    'latitude': 3.56450000,
                    'longitude': 98.65580000,
                    'floor_count': 3,
                    'description': 'Gedung Dekanat, Lab Rekayasa Perangkat Lunak, Lab Jaringan Komputer, dan Tata Usaha.',
                },
                {
                    'name': 'FASILKOM-TI Gedung B (Ruang Kuliah & Lab AI)',
                    'code': 'FASILKOM-B',
                    'latitude': 3.56475000,
                    'longitude': 98.65550000,
                    'floor_count': 3,
                    'description': 'Gedung perkuliahan Ilmu Komputer dan TI, Lab Artificial Intelligence, dan Lab Multimedia.',
                },
            ]
        },
        'Fakultas Kedokteran': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Kedokteran',
                    'code': 'FK-DEKANAT',
                    'latitude': 3.56180000,
                    'longitude': 98.65550000,
                    'floor_count': 3,
                    'description': 'Gedung administrasi utama Dekanat Fakultas Kedokteran dan aula pertemuan.',
                },
                {
                    'name': 'Gedung Kuliah Pendidikan Dokter FK',
                    'code': 'FK-KULIAH',
                    'latitude': 3.56210000,
                    'longitude': 98.65520000,
                    'floor_count': 4,
                    'description': 'Ruang kuliah tutorial PBL, ruang kuliah bersama, dan amphitheater kedokteran.',
                },
                {
                    'name': 'Gedung Laboratorium Terpadu & Anatomi FK',
                    'code': 'FK-LAB',
                    'latitude': 3.56240000,
                    'longitude': 98.65490000,
                    'floor_count': 3,
                    'description': 'Laboratorium Anatomi, Histologi, Fisiologi, dan Keterampilan Klinis (Skills Lab).',
                },
            ]
        },
        'Fakultas Hukum': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Peradilan Semu FH',
                    'code': 'FH-DEKANAT',
                    'latitude': 3.56400000,
                    'longitude': 98.65720000,
                    'floor_count': 3,
                    'description': 'Gedung kantor Dekan FH, ruang sidang peradilan semu (Moot Court), dan perpustakaan hukum.',
                },
                {
                    'name': 'Gedung Kuliah Utama Fakultas Hukum',
                    'code': 'FH-KULIAH',
                    'latitude': 3.56430000,
                    'longitude': 98.65690000,
                    'floor_count': 3,
                    'description': 'Ruang kelas perkuliahan program sarjana ilmu hukum, hukum pidana, dan perdata.',
                },
            ]
        },
        'Fakultas Pertanian': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Pertanian',
                    'code': 'FP-DEKANAT',
                    'latitude': 3.56350000,
                    'longitude': 98.65300000,
                    'floor_count': 3,
                    'description': 'Gedung Dekanat FP, aula Soemarsono, dan ruang dosen pengajar pertanian.',
                },
                {
                    'name': 'Gedung Kuliah Agroteknologi & Agribisnis',
                    'code': 'FP-KULIAH',
                    'latitude': 3.56380000,
                    'longitude': 98.65270000,
                    'floor_count': 3,
                    'description': 'Ruang kuliah program studi Agroteknologi, Agribisnis, Ilmu Kelautan, dan Peternakan.',
                },
                {
                    'name': 'Laboratorium Riset Terpadu Pertanian',
                    'code': 'FP-LAB',
                    'latitude': 3.56320000,
                    'longitude': 98.65330000,
                    'floor_count': 2,
                    'description': 'Laboratorium Ilmu Tanah, Proteksi Tanaman, Pemuliaan Tanaman, dan Bioteknologi Pertanian.',
                },
            ]
        },
        'Fakultas Teknik': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Pusat Administrasi FT',
                    'code': 'FT-DEKANAT',
                    'latitude': 3.56680000,
                    'longitude': 98.65350000,
                    'floor_count': 3,
                    'description': 'Gedung Dekanat Fakultas Teknik, ruang administrasi akademik, dan aula pertemuan FT.',
                },
                {
                    'name': 'Gedung Departemen Teknik Mesin & Industri',
                    'code': 'FT-MESIN-IND',
                    'latitude': 3.56720000,
                    'longitude': 98.65320000,
                    'floor_count': 3,
                    'description': 'Ruang kuliah dan laboratorium Teknik Mesin, Ergonomi, dan Sistem Manufaktur Industri.',
                },
                {
                    'name': 'Gedung Departemen Teknik Elektro',
                    'code': 'FT-ELEKTRO',
                    'latitude': 3.56750000,
                    'longitude': 98.65280000,
                    'floor_count': 3,
                    'description': 'Ruang kelas kuliah dan Lab Tenaga Listrik, Telekomunikasi, dan Sistem Kendali Elektro.',
                },
                {
                    'name': 'Gedung Departemen Teknik Sipil & Lingkungan',
                    'code': 'FT-SIPIL',
                    'latitude': 3.56710000,
                    'longitude': 98.65410000,
                    'floor_count': 3,
                    'description': 'Ruang kuliah Teknik Sipil, Lab Struktur Material, Lab Mekanika Tanah, dan Hidrologi.',
                },
                {
                    'name': 'Gedung Departemen Teknik Kimia & Arsitektur',
                    'code': 'FT-KIMIA-ARS',
                    'latitude': 3.56650000,
                    'longitude': 98.65250000,
                    'floor_count': 3,
                    'description': 'Ruang kuliah Teknik Kimia, Studio Gambar Arsitektur, dan Lab Operasi Teknik Kimia.',
                },
            ]
        },
        'Fakultas Ekonomi dan Bisnis': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Ekonomi dan Bisnis',
                    'code': 'FEB-DEKANAT',
                    'latitude': 3.56580000,
                    'longitude': 98.65420000,
                    'floor_count': 4,
                    'description': 'Gedung Dekanat FEB, ruang administrasi, dan galeri investasi Bursa Efek Indonesia.',
                },
                {
                    'name': 'Gedung Kuliah Manajemen, Akuntansi & EP',
                    'code': 'FEB-KULIAH',
                    'latitude': 3.56610000,
                    'longitude': 98.65390000,
                    'floor_count': 4,
                    'description': 'Ruang perkuliahan S1 Manajemen, Akuntansi, dan Ekonomi Pembangunan.',
                },
                {
                    'name': 'Gedung Pascasarjana FEB (MM, MAksi, M.E.)',
                    'code': 'FEB-PASCA',
                    'latitude': 3.56550000,
                    'longitude': 98.65450000,
                    'floor_count': 3,
                    'description': 'Gedung perkuliahan program magister dan doktoral ilmu ekonomi dan bisnis.',
                },
            ]
        },
        'Fakultas Kedokteran Gigi': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Kuliah FKG',
                    'code': 'FKG-DEKANAT',
                    'latitude': 3.56080000,
                    'longitude': 98.65680000,
                    'floor_count': 3,
                    'description': 'Gedung kantor Dekan FKG, ruang kuliah sarjana kedokteran gigi, dan ruang seminar.',
                },
                {
                    'name': 'Rumah Sakit Gigi dan Mulut (RSGM USU)',
                    'code': 'RSGM-USU',
                    'latitude': 3.56110000,
                    'longitude': 98.65650000,
                    'floor_count': 4,
                    'description': 'Rumah sakit pendidikan gigi dan mulut dengan fasilitas dental unit praktek koas.',
                },
            ]
        },
        'Fakultas Ilmu Budaya': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Ilmu Budaya',
                    'code': 'FIB-DEKANAT',
                    'latitude': 3.56510000,
                    'longitude': 98.65880000,
                    'floor_count': 3,
                    'description': 'Gedung kantor Dekan FIB, laboratorium bahasa, dan museum etnomusikologi.',
                },
                {
                    'name': 'Gedung Kuliah Sastra & Ilmu Sejarah FIB',
                    'code': 'FIB-KULIAH',
                    'latitude': 3.56540000,
                    'longitude': 98.65850000,
                    'floor_count': 3,
                    'description': 'Ruang perkuliahan Sastra Indonesia, Sastra Inggris, Sastra Jepang, dan Ilmu Sejarah.',
                },
            ]
        },
        'Fakultas MIPA': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Aula FMIPA',
                    'code': 'FMIPA-DEKANAT',
                    'latitude': 3.56820000,
                    'longitude': 98.65520000,
                    'floor_count': 3,
                    'description': 'Gedung administrasi Dekanat FMIPA dan auditorium sains.',
                },
                {
                    'name': 'Gedung Departemen Fisika & Kimia FMIPA',
                    'code': 'FMIPA-FIS-KIM',
                    'latitude': 3.56850000,
                    'longitude': 98.65480000,
                    'floor_count': 3,
                    'description': 'Ruang kuliah dan laboratorium riset Fisika Material, Elektronika, dan Kimia Terpadu.',
                },
                {
                    'name': 'Gedung Departemen Biologi & Matematika FMIPA',
                    'code': 'FMIPA-BIO-MAT',
                    'latitude': 3.56800000,
                    'longitude': 98.65580000,
                    'floor_count': 3,
                    'description': 'Ruang kuliah Biologi, Matematika, Statistika, serta Herbarium dan Rumah Kaca.',
                },
            ]
        },
        'Fakultas ISIP': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Ilmu Sosial dan Ilmu Politik',
                    'code': 'FISIP-DEKANAT',
                    'latitude': 3.56420000,
                    'longitude': 98.65820000,
                    'floor_count': 3,
                    'description': 'Gedung Dekanat FISIP, laboratorium radio/televisi kampus, dan ruang seminar.',
                },
                {
                    'name': 'Gedung Kuliah Ilmu Komunikasi & Adm. Publik',
                    'code': 'FISIP-KULIAH',
                    'latitude': 3.56450000,
                    'longitude': 98.65790000,
                    'floor_count': 3,
                    'description': 'Ruang perkuliahan S1 Ilmu Komunikasi, Ilmu Administrasi Publik, dan Sosiologi.',
                },
            ]
        },
        'Fakultas Kesehatan Masyarakat': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Auditorium FKM',
                    'code': 'FKM-DEKANAT',
                    'latitude': 3.56290000,
                    'longitude': 98.65420000,
                    'floor_count': 3,
                    'description': 'Gedung administrasi Dekanat FKM dan auditorium kesehatan masyarakat.',
                },
                {
                    'name': 'Gedung Kuliah & Lab Epidemiologi FKM',
                    'code': 'FKM-KULIAH',
                    'latitude': 3.56310000,
                    'longitude': 98.65390000,
                    'floor_count': 3,
                    'description': 'Ruang kelas kuliah dan Lab Epidemiologi, Biostatistik, Gizi, dan Kesehatan Lingkungan.',
                },
            ]
        },
        'Fakultas Psikologi': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Kuliah Psikologi',
                    'code': 'FPSI-DEKANAT',
                    'latitude': 3.56360000,
                    'longitude': 98.65500000,
                    'floor_count': 3,
                    'description': 'Gedung kantor Dekanat dan ruang kuliah sarjana Psikologi.',
                },
                {
                    'name': 'Laboratorium Psikodiagnostika & Konseling',
                    'code': 'FPSI-LAB',
                    'latitude': 3.56380000,
                    'longitude': 98.65470000,
                    'floor_count': 2,
                    'description': 'Pusat layanan psikologi terpadu, ruang observasi klinis, dan laboratorium tes psikologi.',
                },
            ]
        },
        'Fakultas Kehutanan': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Kehutanan',
                    'code': 'FAHUTAN-DEK',
                    'latitude': 3.56900000,
                    'longitude': 98.65350000,
                    'floor_count': 3,
                    'description': 'Gedung administrasi Dekanat Fakultas Kehutanan dan ruang pertemuan.',
                },
                {
                    'name': 'Gedung Kuliah & Laboratorium Silvikultur Kehutanan',
                    'code': 'FAHUTAN-KUL',
                    'latitude': 3.56930000,
                    'longitude': 98.65320000,
                    'floor_count': 2,
                    'description': 'Ruang perkuliahan dan lab Konservasi Sumber Daya Hutan, Dendrologi, dan Silvikultur.',
                },
            ]
        },
        'Fakultas Farmasi': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat Fakultas Farmasi',
                    'code': 'FF-DEKANAT',
                    'latitude': 3.56220000,
                    'longitude': 98.65380000,
                    'floor_count': 3,
                    'description': 'Gedung administrasi Dekan Farmasi dan ruang program profesi apoteker.',
                },
                {
                    'name': 'Gedung Kuliah & Lab Farmakologi Terpadu',
                    'code': 'FF-LAB',
                    'latitude': 3.56250000,
                    'longitude': 98.65350000,
                    'floor_count': 3,
                    'description': 'Ruang perkuliahan dan Lab Kimia Farmasi, Farmakognosi, dan Teknologi Farmasi.',
                },
            ]
        },
        'Fakultas Keperawatan': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Kuliah Keperawatan',
                    'code': 'FKEP-DEKANAT',
                    'latitude': 3.56120000,
                    'longitude': 98.65320000,
                    'floor_count': 3,
                    'description': 'Gedung kantor Dekan Keperawatan dan ruang perkuliahan S1 Keperawatan.',
                },
                {
                    'name': 'Laboratorium Praktik Klinik Keperawatan',
                    'code': 'FKEP-LAB',
                    'latitude': 3.56150000,
                    'longitude': 98.65290000,
                    'floor_count': 2,
                    'description': 'Laboratorium mini hospital simulasi Keperawatan Medikal Bedah, Maternitas, dan Anak.',
                },
            ]
        },
        'Fakultas Vokasi': {
            'category': 'Fakultas',
            'buildings': [
                {
                    'name': 'Gedung Dekanat & Kuliah Terpadu Vokasi',
                    'code': 'VOKASI-DEK',
                    'latitude': 3.56640000,
                    'longitude': 98.65900000,
                    'floor_count': 4,
                    'description': 'Gedung perkuliahan program diploma dan sarjana terapan Fakultas Vokasi USU.',
                },
                {
                    'name': 'Gedung Laboratorium Terapan & Bengkel Kejuruan',
                    'code': 'VOKASI-LAB',
                    'latitude': 3.56670000,
                    'longitude': 98.65870000,
                    'floor_count': 3,
                    'description': 'Pusat pelatihan vokasi terapan, laboratorium perbankan, dan bengkel mekanik.',
                },
            ]
        },
    }
