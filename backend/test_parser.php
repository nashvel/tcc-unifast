<?php

use App\Services\MasterlistSpreadsheetParser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$parser = app(MasterlistSpreadsheetParser::class);
$file = new UploadedFile(storage_path('app/dummy_ched.csv'), 'dummy_ched.csv', 'text/csv', null, true);
$rows = $parser->parse($file);
print_r($rows);
