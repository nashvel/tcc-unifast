<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$parser = app(\App\Services\MasterlistSpreadsheetParser::class);
$file = new \Illuminate\Http\UploadedFile(storage_path('app/dummy_ched.csv'), 'dummy_ched.csv', 'text/csv', null, true);
$rows = $parser->parse($file);
print_r($rows);
