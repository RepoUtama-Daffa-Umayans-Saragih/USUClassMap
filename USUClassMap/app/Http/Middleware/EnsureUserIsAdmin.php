<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     * Only allow users with admin or superadmin role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            session()->flash('error', 'Akses ditolak. Anda tidak memiliki izin.');
            return redirect('/login');
        }

        return $next($request);
    }
}
