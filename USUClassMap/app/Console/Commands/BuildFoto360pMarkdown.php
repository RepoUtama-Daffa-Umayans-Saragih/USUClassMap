<?php

namespace App\Console\Commands;

use App\Services\Foto360p\Photo360pMarkdownBuilder;
use App\Services\Foto360p\Photo360pScanRepository;
use Illuminate\Console\Command;

class BuildFoto360pMarkdown extends Command
{
    protected $signature = 'foto360p:markdown';

    protected $description = 'Regenerate DAFTAR_RUANGAN_DEEP_SCAN_360P.md from scan_results.json';

    public function handle(Photo360pScanRepository $repository, Photo360pMarkdownBuilder $builder): int
    {
        $data = $repository->load();
        $stats = $builder->stats($data);
        $writtenPath = $builder->write($data);

        $this->info("Markdown generated: {$writtenPath}");
        $this->table(
            ['Metric', 'Count'],
            [
                ['total', $stats['total']],
                ['detected', $stats['detected']],
                ['no_text', $stats['no_text']],
                ['blurry', $stats['blurry']],
                ['pending', $stats['pending']],
                ['verified', $stats['verified']],
                ['unique_rooms', $stats['unique_rooms']],
            ]
        );

        return self::SUCCESS;
    }
}
