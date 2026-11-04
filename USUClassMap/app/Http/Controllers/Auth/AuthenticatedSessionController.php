<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view (SATU USU clone).
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * Implements "Seamless Sync" flow:
     * 1. Try local authentication first.
     * 2. If local auth fails and identity looks like a NIM, attempt
     *    background scraping against satu.usu.ac.id (placeholder).
     * 3. On successful remote auth, create/update local user and sync schedules.
     * 4. Login locally and redirect.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $nim = $request->identity();
        $password = $request->input('password');

        // ─────────────────────────────────────────────────────────────
        // STEP 1: Attempt standard local authentication
        // ─────────────────────────────────────────────────────────────
        try {
            $request->authenticate();

            $request->session()->regenerate();

            return $this->redirectAfterLogin($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Local auth failed — continue to remote sync if identity is a NIM
        }

        // ─────────────────────────────────────────────────────────────
        // STEP 2: Seamless Background Sync (SATU USU Scraping)
        // Only attempt if the identity looks like a NIM/NIP (not email)
        // ─────────────────────────────────────────────────────────────
        if (! filter_var($nim, FILTER_VALIDATE_EMAIL) && preg_match('/^\d{6,}$/', $nim)) {

            try {
                $syncResult = $this->attemptSatuUsuSync($nim, $password);

                if ($syncResult['success']) {
                    // Create or update local user from remote data
                    $user = $this->createOrUpdateLocalUser($nim, $password, $syncResult);

                    // Sync schedules into local database
                    $this->syncSchedulesToLocal($user, $syncResult['schedules'] ?? []);

                    // Login the user locally
                    Auth::login($user, $request->boolean('remember'));
                    $request->session()->regenerate();

                    return redirect()->intended(route('map', absolute: false))
                        ->with('success', 'Jadwal berhasil disinkronkan otomatis dari SATU USU!');
                }
            } catch (\Exception $ex) {
                Log::warning('[SATU USU Sync] Remote sync failed: ' . $ex->getMessage());
            }
        }

        // ─────────────────────────────────────────────────────────────
        // STEP 3: All methods failed — throw validation error
        // ─────────────────────────────────────────────────────────────
        $errorField = $request->has('nim') ? 'nim' : 'email';

        throw \Illuminate\Validation\ValidationException::withMessages([
            $errorField => trans('auth.failed'),
        ]);
    }

    /**
     * Attempt to authenticate and scrape schedule data from SATU USU portal.
     *
     * This is a PLACEHOLDER implementation. In production, this would:
     * 1. POST credentials to https://satu.usu.ac.id/auth/login
     * 2. Follow redirects to capture session cookies
     * 3. GET https://satu.usu.ac.id/mahasiswa/home to scrape schedule HTML
     * 4. Parse the HTML DOM to extract schedule data
     *
     * @param string $nim  The student's NIM
     * @param string $password  The student's password
     * @return array{success: bool, name: string, email: string, semester: string, schedules: array}
     */
    protected function attemptSatuUsuSync(string $nim, string $password): array
    {
        Log::info("[SATU USU Sync] Attempting remote authentication for NIM: {$nim}");

        // ┌─────────────────────────────────────────────────────────┐
        // │  PRODUCTION IMPLEMENTATION (uncomment when ready):      │
        // │                                                         │
        // │  // Step 1: POST login credentials to SATU USU          │
        // │  $loginResponse = Http::asForm()                        │
        // │      ->withOptions(['allow_redirects' => true])         │
        // │      ->post('https://satu.usu.ac.id/auth/login', [      │
        // │          'username' => $nim,                             │
        // │          'password' => $password,                        │
        // │      ]);                                                 │
        // │                                                         │
        // │  if ($loginResponse->failed()) {                        │
        // │      return ['success' => false];                       │
        // │  }                                                      │
        // │                                                         │
        // │  // Step 2: Extract session cookies                     │
        // │  $cookies = $loginResponse->cookies();                  │
        // │                                                         │
        // │  // Step 3: GET the mahasiswa dashboard page             │
        // │  $dashboardResponse = Http::withCookies($cookies, ...)  │
        // │      ->get('https://satu.usu.ac.id/mahasiswa/home');    │
        // │                                                         │
        // │  // Step 4: Parse schedule HTML from the response body  │
        // │  $html = $dashboardResponse->body();                    │
        // │  $schedules = $this->parseScheduleHtml($html);          │
        // │                                                         │
        // │  return [                                                │
        // │      'success' => true,                                  │
        // │      'name' => $parsedName,                              │
        // │      'email' => "{$nim}@students.usu.ac.id",            │
        // │      'semester' => $parsedSemester,                      │
        // │      'schedules' => $schedules,                          │
        // │  ];                                                      │
        // └─────────────────────────────────────────────────────────┘

        // DEVELOPMENT PLACEHOLDER: Always return false
        // Remove this and uncomment the block above for production
        return ['success' => false];
    }

    /**
     * Create or update a local user record from SATU USU remote data.
     *
     * @param string $nim
     * @param string $password
     * @param array  $syncResult
     * @return User
     */
    protected function createOrUpdateLocalUser(string $nim, string $password, array $syncResult): User
    {
        $user = User::updateOrCreate(
            ['nim' => $nim],
            [
                'name' => $syncResult['name'] ?? "Mahasiswa {$nim}",
                'email' => $syncResult['email'] ?? "{$nim}@students.usu.ac.id",
                'semester' => $syncResult['semester'] ?? '1',
                'password' => Hash::make($password),
                'role' => 'user',
            ]
        );

        Log::info("[SATU USU Sync] Local user created/updated: {$user->email} (NIM: {$nim})");

        return $user;
    }

    /**
     * Sync scraped schedule data into the local database.
     *
     * @param User  $user
     * @param array $schedules  Array of schedule objects from SATU USU
     */
    protected function syncSchedulesToLocal(User $user, array $schedules): void
    {
        if (empty($schedules)) {
            Log::info("[SATU USU Sync] No schedules to sync for user: {$user->nim}");
            return;
        }

        // ┌───────────────────────────────────────────────────────────┐
        // │  PRODUCTION: Insert scraped schedules into database       │
        // │                                                           │
        // │  foreach ($schedules as $schedule) {                      │
        // │      Schedule::updateOrCreate(                             │
        // │          [                                                 │
        // │              'course_code' => $schedule['kode_mk'],       │
        // │              'day' => $schedule['hari'],                   │
        // │              'semester' => $schedule['semester'],          │
        // │          ],                                                │
        // │          [                                                 │
        // │              'course_name' => $schedule['mata_kuliah'],    │
        // │              'start_time' => $schedule['jam_mulai'],       │
        // │              'end_time' => $schedule['jam_selesai'],       │
        // │              'room_name' => $schedule['ruangan'],          │
        // │              'lecturer_name' => $schedule['dosen'],        │
        // │          ]                                                 │
        // │      );                                                    │
        // │  }                                                         │
        // └───────────────────────────────────────────────────────────┘

        Log::info("[SATU USU Sync] Synced " . count($schedules) . " schedules for NIM: {$user->nim}");
    }

    /**
     * Parse schedule HTML from SATU USU dashboard page.
     *
     * @param string $html  Raw HTML body from satu.usu.ac.id/mahasiswa/home
     * @return array  Parsed schedule data
     */
    protected function parseScheduleHtml(string $html): array
    {
        $schedules = [];

        // ┌───────────────────────────────────────────────────────────┐
        // │  PRODUCTION: Use DOMDocument or regex to parse the HTML   │
        // │                                                           │
        // │  $dom = new \DOMDocument();                                │
        // │  @$dom->loadHTML($html);                                   │
        // │  $xpath = new \DOMXPath($dom);                             │
        // │                                                           │
        // │  // Query for schedule table rows                         │
        // │  $rows = $xpath->query('//table[contains(@class,         │
        // │          "jadwal")]//tr');                                  │
        // │                                                           │
        // │  foreach ($rows as $row) {                                │
        // │      $cells = $row->getElementsByTagName('td');            │
        // │      if ($cells->length >= 4) {                            │
        // │          $schedules[] = [                                   │
        // │              'mata_kuliah' => trim($cells[0]->textContent),│
        // │              'hari' => trim($cells[1]->textContent),       │
        // │              'jam_mulai' => ...,                            │
        // │              'jam_selesai' => ...,                          │
        // │              'ruangan' => ...,                              │
        // │              'dosen' => ...,                                │
        // │          ];                                                 │
        // │      }                                                     │
        // │  }                                                         │
        // └───────────────────────────────────────────────────────────┘

        return $schedules;
    }

    /**
     * Determine the correct redirect destination after login.
     */
    protected function redirectAfterLogin(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Superadmin or admin@usu.ac.id → Admin Dashboard
        if ($user && ($user->email === 'admin@usu.ac.id' || $user->isSuperAdmin())) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        // Regular users → Map page (or dashboard for Breeze compatibility)
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
