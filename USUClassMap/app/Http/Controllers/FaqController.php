<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Display the FAQ page with categorized questions and answers & Contact Persons.
     */
    public function index()
    {
        $faqs = [
            [
                'category' => 'Tentang Aplikasi Web',
                'question' => 'Apa itu aplikasi USUStarterMap?',
                'answer' => 'USUStarterMap adalah platform peta interaktif kampus dan direktori akademik berbasis Web Semantik (Semantic Web). Aplikasi ini membantu mahasiswa Universitas Sumatera Utara menemukan lokasi ruang kelas perkuliahan MKWK/MKWU, gedung fakultas, navigasi 360°, jadwal kuliah, serta layanan terintegrasi kampus seperti jasa fotocopy & percetakan di sekitar kampus.'
            ],
            [
                'category' => 'Pencarian & Jadwal',
                'question' => 'Bagaimana cara mencari jadwal kuliah saya?',
                'answer' => 'Buka halaman "Peta", lalu gunakan kolom pencarian di sidebar kiri untuk mengetik nama mata kuliah (misal: "Pendidikan Agama", "Kewarganegaraan"), kode mata kuliah, nama gedung, atau nama dosen. Anda juga dapat memfilter jadwal berdasarkan hari kuliah (Senin hingga Sabtu).'
            ],
            [
                'category' => 'Navigasi & Tur 360°',
                'question' => 'Apa itu Tur Visual 360°',
                'answer' => 'Tur Visual 360° adalah fitur foto panorama interaktif yang memungkinkan mahasiswa melihat kondisi nyata di dalam ruang kelas perkuliahan sebelum datang langsung.'
            ],
            [
                'category' => 'Navigasi & Tur 360°',
                'question' => 'Bagaimana cara mendapatkan rute petunjuk arah',
                'answer' => 'Pada peta atau kartu informasi gedung/ruangan, klik tombol "Petunjuk Arah (Google Maps)" atau ikon rute jalan kaki untuk panduan rute GPS akurat langsung ke gedung yang dituju.'
            ],
            [
                'category' => 'Fitur Bookmark',
                'question' => 'Bagaimana cara menyimpan jadwal favorit saya ke Bookmark?',
                'answer' => 'Klik tombol bookmark pada kartu jadwal yang ingin Anda simpan. Seluruh jadwal yang dibookmark akan otomatis tersimpan di peramban Anda dan dapat diakses dengan cepat melalui menu "Bookmark" di navigasi atas.'
            ],
            [
                'category' => 'Teknologi & Semantic Web',
                'question' => 'Bagaimana data jadwal diproses di USUStarterMap?',
                'answer' => 'Data jadwal dikonversi ke format RDF/OWL ontologi Semantic Web dan di-query menggunakan SPARQL Protocol and RDF Query Language melalui Apache Jena Fuseki untuk relasi data yang presisi.'
            ],
            [
                'category' => 'Akses & Akun',
                'question' => 'Apakah mahasiswa perlu login untuk menggunakan fitur peta?',
                'answer' => 'Tidak wajib. Peta interaktif, pencarian jadwal, tur 360°, bookmark, dan layanan fotocopy dapat diakses secara publik dan gratis oleh seluruh mahasiswa tanpa perlu login.'
            ],
            [
                'category' => 'Bantuan & Kendala',
                'question' => 'Jadwal kuliah saya tidak ditemukan, apa yang harus dilakukan?',
                'answer' => 'Pastikan penulisan kata kunci nama mata kuliah atau dosen sudah sesuai. Jika jadwal tetap tidak muncul, silakan hubungi tim helpdesk melalui nomor WhatsApp atau email yang tersedia di bawah.'
            ],
            [
                'category' => 'Layanan Fotocopy',
                'question' => 'Apakah USUStarterMap terhubung dengan layanan fotocopy di kampus?',
                'answer' => 'Ya! USUStarterMap kini memiliki fitur terintegrasi "Fotocopy" di mana mahasiswa dapat mencari tempat fotocopy terdekat dari ruang kelas, melihat status buka/tutup toko, mengecek harga layanan (print, jilid, laminating, fotocopy), serta memesan langsung via WhatsApp dengan pesan otomatis atau menuju lokasi via Google Maps.'
            ],
        ];

        $contacts = [
            [
                'name' => 'Helpdesk WhatsApp Resmi',
                'type' => 'whatsapp',
                'contact' => '0812-6000-8787',
                'url' => 'https://wa.me/6281260008787?text=' . rawurlencode('Halo Tim USUStarterMap, saya mahasiswa USU ingin bertanya atau menyampaikan masukan terkait aplikasi web USUStarterMap:'),
                'desc' => 'Layanan bantuan cepat via WhatsApp aktif Senin - Jumat (08:00 - 17:00 WIB)',
                'fa_icon' => 'fa-brands fa-whatsapp',
                'badge' => 'Respon Cepat',
                'color' => '#25D366',
                'action_label' => 'Hubungi via WhatsApp'
            ],
            [
                'name' => 'Email Pusat Bantuan & Informasi',
                'type' => 'email',
                'contact' => 'support@usuclassmap.usu.ac.id',
                'url' => 'mailto:support@usuclassmap.usu.ac.id?subject=' . rawurlencode('Bantuan Mahasiswa USUStarterMap'),
                'desc' => 'Kirim pertanyaan rinci, laporan bug antarmuka, atau permintaan data ruangan',
                'fa_icon' => 'fa-solid fa-envelope',
                'badge' => 'Tiket Dukungan',
                'color' => '#10A760',
                'action_label' => 'Kirim Email'
            ],
            [
                'name' => 'Lokasi Sekretariat PSI USU',
                'type' => 'location',
                'contact' => 'Gedung Pusat Sistem Informasi (PSI) USU',
                'url' => 'https://maps.google.com/?q=Pusat+Sistem+Informasi+USU+Medan',
                'desc' => 'Jl. Universitas No. 9, Kampus Padang Bulan, Medan 20155',
                'fa_icon' => 'fa-solid fa-location-dot',
                'badge' => 'Kunjungan Langsung',
                'color' => '#D4A017',
                'action_label' => 'Buka di Google Maps'
            ],
        ];

        return view('features.faq.index', compact('faqs', 'contacts'));
    }
}
