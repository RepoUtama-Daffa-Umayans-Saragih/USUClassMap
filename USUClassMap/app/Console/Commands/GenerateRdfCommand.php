<?php

namespace App\Console\Commands;

use App\Services\RdfGeneratorService;
use Illuminate\Console\Command;

class GenerateRdfCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rdf:generate {--upload : Upload generated RDF to Fuseki endpoint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate RDF/XML file from MySQL schedules data and optionally sync to Apache Jena Fuseki';

    /**
     * Execute the console command.
     */
    public function handle(RdfGeneratorService $rdfService): int
    {
        $this->info('Generating RDF graph from MySQL database...');

        $filePath = $rdfService->generate();
        $this->info("RDF successfully generated at: {$filePath}");

        if ($this->option('upload') || config('sparql.fuseki_url')) {
            $this->info('Uploading RDF graph to Apache Jena Fuseki dataset...');
            $uploaded = $rdfService->uploadToFuseki();

            if ($uploaded) {
                $this->info('Fuseki upload successful!');
            } else {
                $this->warn('Fuseki upload could not be completed (Fuseki server might be offline). File saved locally.');
            }
        }

        return Command::SUCCESS;
    }
}
