<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FotocopyController extends Controller
{
    /**
     * Tampilan publik: Daftar Mitra Toko Fotocopy USU (Unified).
     */
    public function index(Request $request)
    {
        return app(PhotocopyController::class)->index($request);
    }

    /**
     * Tampilan publik: Detail Toko & Katalog Harga (Unified).
     */
    public function show($id)
    {
        return app(PhotocopyController::class)->show($id);
    }

    /**
     * Tampilan publik: Halaman Pemesanan Interaktif (Unified).
     */
    public function order($id)
    {
        return app(PhotocopyController::class)->order($id);
    }

    /**
     * Simpan pemesanan layanan dari mahasiswa ke database (Unified).
     */
    public function storeOrder(Request $request, $id)
    {
        return app(PhotocopyController::class)->storeOrder($request, $id);
    }
}
