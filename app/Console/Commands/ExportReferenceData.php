<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportReferenceData extends Command
{
    protected $signature = 'app:export-reference-data';
    protected $description = 'Export public reference data used by the database seeders';

    public function handle(): int
    {
        $directory = database_path('seeders/data');
        File::ensureDirectoryExists($directory);

        foreach (['states', 'preference_categories', 'attractions', 'attraction_preferences'] as $table) {
            $firstColumn = DB::getSchemaBuilder()->getColumnListing($table)[0];
            $rows = DB::table($table)->orderBy($firstColumn)->get();
            $path = $directory.DIRECTORY_SEPARATOR.$table.'.json';

            File::put($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->info("Exported {$rows->count()} rows to {$path}");
        }

        return self::SUCCESS;
    }
}
