<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCashierFotokopi
{
    /**
     * Handle an incoming request.
     * Membatasi akses route untuk Cashier Fotokopi dan Super Admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || (!auth()->user()->isCashierFotokopi() && !auth()->user()->isSuperAdmin())) {
            session()->flash('error', 'Akses ditolak. Halaman ini hanya untuk Kasir / Operator Toko Fotokopi.');
            return redirect('/login');
        }

        return $next($request);
    }
}
