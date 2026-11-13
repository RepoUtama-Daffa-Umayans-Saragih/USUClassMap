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
                'question' => 'Apa itu aplikasi USUClassMap dan apa fungsinya?',
                'answer' => 'USUClassMap adalah platform peta interaktif kampus dan direktori akademik berbasis Web Semantik (Semantic Web). Aplikasi ini membantu mahasiswa Universitas Sumatera Utara menemukan lokasi ruang kelas perkuliahan MKWK/MKWU, gedung fakultas, navigasi 360°, jadwal kuliah, serta layanan terintegrasi kampus seperti jasa fotocopy & percetakan di sekitar kampus.'
            ],
            [
                'category' => 'Tentang Aplikasi Web',
                'question' => 'Apakah USUClassMap terhubung dengan layanan fotocopy di kampus?',
                'answer' => 'Ya! USUClassMap kini memiliki fitur terintegrasi "Fotocopy" di mana mahasiswa dapat mencari tempat fotocopy terdekat dari ruang kelas, melihat status buka/tutup toko, mengecek harga layanan (print, jilid, laminating, fotocopy), serta memesan langsung via WhatsApp dengan pesan otomatis atau menuju lokasi via Google Maps.'
            ],
            [
                'category' => 'Pencarian & Jadwal',
                'question' => 'Bagaimana cara mencari jadwal kuliah dan ruangan kelas saya?',
                'answer' => 'Buka halaman "Peta", lalu gunakan kolom pencarian di sidebar kiri untuk mengetik nama mata kuliah (misal: "Pendidikan Agama", "Kewarganegaraan"), kode mata kuliah, nama gedung, atau nama dosen. Anda juga dapat memfilter jadwal berdasarkan hari kuliah (Senin hingga Sabtu).'
            ],
            [
                'category' => 'Navigasi & Tur 360°',
                'question' => 'Bagaimana cara menggunakan Tur Visual 360° dan Rute Lokasi?',
                'answer' => 'Pada peta atau kartu informasi gedung/ruangan, klik ikon 360° untuk melihat panorama ruangan secara interaktif. Untuk menuju ke lokasi fisik di kampus, klik tombol "Petunjuk Arah (Google Maps)" atau ikon pejalan kaki untuk panduan rute akurat.'
            ],
            [
                'category' => 'Fitur Bookmark',
                'question' => 'Bagaimana cara menyimpan jadwal favorit saya ke Bookmark?',
                'answer' => 'Klik tombol bookmark pada kartu jadwal yang ingin Anda simpan. Seluruh jadwal yang dibookmark akan otomatis tersimpan di peramban Anda dan dapat diakses dengan cepat melalui menu "Bookmark" di navigasi atas.'
            ],
            [
                'category' => 'Akses & Akun',
                'question' => 'Apakah mahasiswa wajib mendaftar akun atau login untuk menggunakan peta?',
                'answer' => 'Tidak wajib. Peta interaktif, pencarian jadwal, tur 360°, bookmark, dan layanan fotocopy dapat diakses secara publik dan gratis oleh seluruh mahasiswa tanpa perlu login. Login hanya diperuntukkan bagi admin pengelola jadwal dan pengguna yang ingin menyimpan profil.'
            ],
        ];

        $contacts = [
            [
                'name' => 'Helpdesk WhatsApp Resmi',
                'type' => 'whatsapp',
                'contact' => '0812-6000-8787',
                'url' => 'https://wa.me/6281260008787?text=' . rawurlencode('Halo Tim USUClassMap, saya mahasiswa USU ingin bertanya atau menyampaikan masukan terkait aplikasi web USUClassMap:'),
                'desc' => 'Layanan bantuan cepat via WhatsApp aktif Senin - Jumat (08:00 - 17:00 WIB)',
                'fa_icon' => 'fa-brands fa-whatsapp',
                'badge' => 'Respon Cepat',
                'color' => '#25D366',
                'action_label' => 'Hubungi via WhatsApp'
            ],
            [
                'name' => 'Email Pusat Bantuan & Informasi',
                'type' => 'email',
                'contact' => 'support.classmap@usu.ac.id',
                'url' => 'mailto:support.classmap@usu.ac.id?subject=' . rawurlencode('Bantuan / Pertanyaan Aplikasi USUClassMap'),
                'desc' => 'Kirim pertanyaan, laporan bug, atau usulan penambahan data jadwal & mitra fotocopy',
                'fa_icon' => 'fa-solid fa-envelope',
                'badge' => 'Email Resmi',
                'color' => '#10A760',
                'action_label' => 'Kirim Email'
            ],
            [
                'name' => 'Instagram Resmi USUClassMap',
                'type' => 'instagram',
                'contact' => '@usuclassmap',
                'url' => 'https://instagram.com/usuclassmap',
                'desc' => 'Informasi update pembaruan jadwal, panduan penggunaan, dan info seputar kampus USU',
                'fa_icon' => 'fa-brands fa-instagram',
                'badge' => 'Media Sosial',
                'color' => '#E1306C',
                'action_label' => 'Kunjungi Instagram'
            ],
            [
                'name' => 'Facebook Page USUClassMap',
                'type' => 'facebook',
                'contact' => 'USUClassMap Official',
                'url' => 'https://facebook.com/usuclassmap',
                'desc' => 'Komunitas mahasiswa dan informasi seputar denah & peta kampus USU',
                'fa_icon' => 'fa-brands fa-facebook-f',
                'badge' => 'Komunitas',
                'color' => '#1877F2',
                'action_label' => 'Kunjungi Facebook'
            ],
        ];

        return view('public.faq', compact('faqs', 'contacts'));
    }
}
