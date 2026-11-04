<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Apache Jena Fuseki SPARQL Endpoint Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for connecting to the Apache Jena Fuseki server.
    |
    */

    'fuseki_url' => env('FUSEKI_URL', 'http://localhost:3030'),

    'dataset' => env('FUSEKI_DATASET', 'usuclassmap'),

    'query_endpoint' => env('FUSEKI_QUERY_ENDPOINT', env('FUSEKI_URL', 'http://localhost:3030') . '/' . env('FUSEKI_DATASET', 'usuclassmap') . '/query'),

    'data_endpoint' => env('FUSEKI_DATA_ENDPOINT', env('FUSEKI_URL', 'http://localhost:3030') . '/' . env('FUSEKI_DATASET', 'usuclassmap') . '/data'),

    'timeout' => env('SPARQL_TIMEOUT', 5), // in seconds

    'namespaces' => [
        'usu' => 'http://usuclassmap.usu.ac.id/ontology#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ],

    'rdf_storage_path' => storage_path('app/rdf/schedules.rdf'),
    'ontology_storage_path' => storage_path('app/ontology/usuclassmap.owl'),
];
