<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminFotokopi
{
    /**
     * Handle an incoming request.
     * Membatasi akses route untuk Admin Fotokopi dan Super Admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || (!auth()->user()->isAdminFotokopi() && !auth()->user()->isSuperAdmin())) {
            session()->flash('error', 'Akses ditolak. Halaman ini hanya untuk Admin Fotokopi Kampus USU.');
            return redirect('/login');
        }

        return $next($request);
    }
}
