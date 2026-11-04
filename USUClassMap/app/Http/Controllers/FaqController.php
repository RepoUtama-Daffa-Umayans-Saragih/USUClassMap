<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Display the FAQ page with categorized questions and answers.
     */
    public function index()
    {
        $faqs = [
            [
                'category' => 'Tentang USUClassMap',
                'question' => 'Apa itu aplikasi USUClassMap?',
                'answer' => 'USUClassMap adalah platform peta interaktif berbasis Web Semantik (Semantic Web) yang dirancang khusus untuk membantu mahasiswa Universitas Sumatera Utara menemukan lokasi ruang kelas perkuliahan MKWK (Mata Kuliah Wajib Kurikulum) dan MKWU (Mata Kuliah Wajib Universitas) di seluruh kawasan kampus USU.'
            ],
            [
                'category' => 'Pencarian & Filter',
                'question' => 'Bagaimana cara mencari jadwal kuliah saya?',
                'answer' => 'Anda cukup membuka halaman "Peta", lalu masukkan nama mata kuliah (contoh: "Pendidikan Agama", "Pancasila"), kode mata kuliah (contoh: "UNI101"), atau nama dosen pengampu pada kolom pencarian di sidebar kiri. Anda juga dapat memfilter jadwal berdasarkan hari kuliah (Senin hingga Sabtu).'
            ],
            [
                'category' => 'Fitur 360°',
                'question' => 'Apa itu Tur Visual 360° dan bagaimana cara melihatnya?',
                'answer' => 'Tur Visual 360° memungkinkan Anda melihat bagian dalam ruangan kelas secara panorama 360 derajat sebelum mendatangi lokasi. Cukup klik pin gedung di peta atau klik salah satu jadwal, lalu pada pop-up yang muncul, geser viewer panorama untuk melihat kondisi sekeliling ruangan.'
            ],
            [
                'category' => 'Navigasi & Rute',
                'question' => 'Bagaimana cara mendapatkan rute petunjuk arah ke gedung kelas?',
                'answer' => 'Pada setiap pop-up gedung atau kartu jadwal di sidebar, klik tombol "Petunjuk Arah (Google Maps)" atau ikon pejalan kaki (🚶). Anda akan otomatis diarahkan ke Google Maps dengan titik tujuan koordinat gedung yang presisi dan mode berjalan kaki.'
            ],
            [
                'category' => 'Fitur Bookmark',
                'question' => 'Bagaimana cara menyimpan jadwal favorit saya ke Bookmark?',
                'answer' => 'Klik ikon bookmark (🔖) pada kartu jadwal yang ingin Anda simpan. Jadwal akan tersimpan di memori browser Anda (localStorage) dan jumlah bookmark akan langsung tampil di badge Navbar atas. Anda dapat melihat seluruh jadwal tersimpan di menu "Bookmark".'
            ],
            [
                'category' => 'Teknologi & Data',
                'question' => 'Bagaimana data jadwal diproses di USUClassMap?',
                'answer' => 'Aplikasi ini menggabungkan basis data relasional MySQL dengan teknologi Web Semantik (Ontologi OWL & RDF/XML). Data diekspor ke format RDF dan dapat dicari secara cerdas menggunakan protokol SPARQL melalui server Apache Jena Fuseki, dengan failover otomatis ke MySQL.'
            ],
            [
                'category' => 'Akses & Akun',
                'question' => 'Apakah mahasiswa perlu login untuk menggunakan peta dan bookmark?',
                'answer' => 'Tidak perlu. Halaman publik (Peta interaktif, pencarian jadwal, tur 360°, dan bookmark) dapat diakses langsung oleh seluruh sivitas akademika USU secara gratis tanpa perlu melakukan login. Login hanya diperuntukkan bagi Administrator pengelola jadwal.'
            ],
            [
                'category' => 'Bantuan & Kendala',
                'question' => 'Jadwal kuliah saya tidak ditemukan, apa yang harus saya lakukan?',
                'answer' => 'Pastikan penulisan kata kunci pencarian sudah benar atau gunakan kata kunci yang lebih umum. Jika jadwal Anda memang belum tercantum, kemungkinan data untuk kelas/semester tersebut sedang dalam proses input oleh admin program studi.'
            ],
        ];

        return view('public.faq', compact('faqs'));
    }
}
