<?php
require 'vendor/autoload.php';

$generator = new \OpenApi\Generator();

$sources = new \OpenApi\SourceFinder([
    __DIR__ . '/app/Http/Controllers',
    __DIR__ . '/app/Swagger'
]);

$openapi = $generator->generate($sources);

echo "Paths found: " . count((array)$openapi->paths) . "\n\n";

if ($openapi->paths) {
    foreach ($openapi->paths as $path) {
        echo " - " . $path->path . "\n";
    }
} else {
    echo "No paths found!\n";
}
