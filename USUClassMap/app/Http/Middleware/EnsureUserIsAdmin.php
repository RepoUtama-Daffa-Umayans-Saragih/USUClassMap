<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     * Membatasi akses route untuk Admin USUStarterMap dan Super Admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin())) {
            session()->flash('error', 'Akses ditolak. Halaman ini hanya untuk Admin USUStarterMap.');
            return redirect('/login');
        }

        return $next($request);
    }
}
