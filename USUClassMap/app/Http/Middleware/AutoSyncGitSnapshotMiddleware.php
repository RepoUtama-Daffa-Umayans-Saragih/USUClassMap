<?php

namespace App\Http\Middleware;

use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AutoSyncGitSnapshotMiddleware
{
    /**
     * Periksa apakah snapshot Git dari `git pull` lebih baru dibanding state lokal.
     * Jika berbeda, lakukan auto-import atomik sebelum request diproses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->runningInConsole() && !GitSnapshotObserver::$isMuted) {
            try {
                /** @var GitSnapshotSyncService $syncService */
                $syncService = app(GitSnapshotSyncService::class);

                if ($syncService->hasPendingImport()) {
                    $syncService->importAll();
                }
            } catch (\Throwable $e) {
                Log::error('AutoSyncGitSnapshotMiddleware: Gagal melakukan auto-sync snapshot Git: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
