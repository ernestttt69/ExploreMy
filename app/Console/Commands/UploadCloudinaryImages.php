<?php

namespace App\Console\Commands;

use App\Services\CloudinaryImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UploadCloudinaryImages extends Command
{
    protected $signature = 'images:cloudinary {--upload : Upload files; otherwise only preview} {--limit=5 : Maximum new uploads per run}';

    protected $description = 'Upload public images with a resumable manifest and generate Aiven URL-update SQL';

    public function handle(CloudinaryImageService $cloudinary): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) {
            $this->error('Use a positive --limit.');
            return self::FAILURE;
        }
        $cloud = (string) config('services.cloudinary.cloud_name');
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $cloud)) {
            $this->error('Set CLOUDINARY_CLOUD_NAME first.');
            return self::FAILURE;
        }
        $directory = storage_path('app/cloudinary/'.$cloud);
        File::ensureDirectoryExists($directory);
        $lock = fopen($directory.'/upload.lock', 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $this->error('Another upload is already running.');
            return self::FAILURE;
        }
        try {
            $manifestPath = $directory.'/manifest.json';
            $manifest = File::exists($manifestPath)
                ? json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
            $count = 0;
            foreach (['images', 'attraction_images'] as $folder) {
                if (! File::isDirectory(public_path($folder))) {
                    continue;
                }
                foreach (File::allFiles(public_path($folder)) as $file) {
                    if (! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                        continue;
                    }
                    $relative = $folder.'/'.str_replace('\\', '/', $file->getRelativePathname());
                    $hash = hash_file('sha256', $file->getPathname());
                    if (($manifest[$relative]['sha256'] ?? null) === $hash) {
                        continue;
                    }
                    if ($count >= $limit) {
                        break 2;
                    }
                    $this->line($relative);
                    if ($this->option('upload')) {
                        $manifest[$relative] = [
                            'sha256' => $hash,
                            'url' => $cloudinary->upload($file->getPathname()),
                        ];
                        $this->saveProgressFile($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                    }
                    $count++;
                }
            }
            if ($this->option('upload')) {
                $sql = "-- Review and back up Aiven before running. Updates matching local paths only.\nSTART TRANSACTION;\n";
                foreach ($manifest as $path => $entry) {
                    $url = "CONVERT(0x".bin2hex($entry['url'])." USING utf8mb4)";
                    $paths = '0x'.bin2hex($path).', 0x'.bin2hex('/'.$path);
                    foreach (['attraction_image', 'attractions'] as $table) {
                        $sql .= "UPDATE `{$table}` SET `image_path` = {$url} WHERE BINARY `image_path` IN ({$paths});\n";
                    }
                }
                $this->saveProgressFile($directory.'/update-aiven-images.sql', $sql."COMMIT;\n");
                $this->info("Uploaded {$count} files. SQL: {$directory}/update-aiven-images.sql");
            } else {
                $this->info("Preview: {$count} files. Add --upload to upload this batch. No database changes were made.");
            }
            return self::SUCCESS;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function saveProgressFile(string $path, string $contents): void
    {
        // The command's upload.lock serializes writers. Avoid rename-over-existing,
        // which can fail for OneDrive files on Windows. Preserve the previous copy.
        if (File::exists($path)) {
            $previous = File::get($path);
            if (File::put($path.'.backup', $previous, true) !== strlen($previous)) {
                throw new \RuntimeException('Could not back up the upload progress file.');
            }
        }
        if (File::put($path, $contents, true) !== strlen($contents)) {
            throw new \RuntimeException('Could not save upload progress. The previous version is in '.$path.'.backup');
        }
    }
}
