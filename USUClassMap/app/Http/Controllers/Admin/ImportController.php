<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ScheduleTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ScheduleImport;
use App\Jobs\RegenerateRdfJob;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function index()
    {
        return view('features.admin.import.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ], [
            'file.required' => 'File Excel wajib diunggah.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        try {
            $import = new ScheduleImport();
            
            // Nonaktifkan observer sementara untuk mencegah queue flooding (500 baris = 500 job)
            Schedule::withoutEvents(function () use ($import, $request) {
                Excel::import($import, $request->file('file'));
            });

            $successCount = $import->getSuccessCount();
            $failCount = $import->getFailCount();
            $errors = $import->getErrors();

            // Dispatch sinkronisasi RDF ke Fuseki tepat satu kali jika terdapat data yang berhasil diimpor
            if ($successCount > 0) {
                RegenerateRdfJob::dispatch();
            }

            return redirect()->route('admin.import.index')
                ->with('success', "Import selesai: {$successCount} baris berhasil, {$failCount} baris gagal.")
                ->with('import_errors', $errors);
        } catch (\Exception $e) {
            return redirect()->route('admin.import.index')
                ->with('error', 'Terjadi kesalahan saat import: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new ScheduleTemplateExport(), 'template_jadwal_usuclassmap.xlsx');
    }
}
