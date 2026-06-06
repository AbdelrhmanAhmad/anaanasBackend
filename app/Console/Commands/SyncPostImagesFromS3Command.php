<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SyncPostImagesFromS3Command extends Command
{
    protected $signature = 'posts:sync-images-local
                            {--limit=100 : Maximum number of posts per run}
                            {--root= : Local root folder (default: storage/app/s3-mirror)}
                            {--resync : Ignore flag and re-check already synced posts}';

    protected $description = 'Scan post image structure on S3 and mirror images locally without duplicates';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $targetRoot = (string) ($this->option('root') ?: storage_path('app/s3-mirror'));
        $resync = (bool) $this->option('resync');

        File::ensureDirectoryExists($targetRoot);

        $hasSyncFlag = Schema::hasColumn('posts', 'images_local_synced');
        $hasSyncAtFlag = Schema::hasColumn('posts', 'images_local_synced_at');

        $selectColumns = ['id'];
        if ($hasSyncFlag) {
            $selectColumns[] = 'images_local_synced';
        }

        $postsQuery = Post::query()
            ->select($selectColumns)
            ->with(['postImages:id,post_id,image'])
            ->whereHas('postImages', function ($q) {
                $q->whereNotNull('image');
            })->latest() ;

        if (! $resync && $hasSyncFlag) {
            $postsQuery->where('images_local_synced', false);
        }

        $posts = $postsQuery->limit($limit)->get();

        if ($posts->isEmpty()) {
            $this->info('No posts to sync.');
            return self::SUCCESS;
        }

        $this->info("Processing {$posts->count()} post(s).");
        $this->line("Local mirror root: {$targetRoot}");

        $totalDownloaded = 0;
        $totalExisting = 0;
        $totalFailed = 0;
        $scannedDirectories = [];

        foreach ($posts as $post) {
            $hasFailures = false;
            $hasAnyImage = false;

            foreach ($post->postImages as $img) {
                $key = $this->normalizeS3Key((string) $img->image);
                if (! $key) {
                    continue;
                }

                $hasAnyImage = true;

                $dirKey = trim(str_replace('\\', '/', dirname($key)), '/');
                if ($dirKey !== '' && $dirKey !== '.') {
                    $scannedDirectories[$dirKey] = true;
                }

                $localPath = rtrim($targetRoot, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $key);

                if (File::exists($localPath) && File::size($localPath) > 0) {
                    $totalExisting++;
                    continue;
                }

                try {
                    File::ensureDirectoryExists(dirname($localPath));

                    $readStream = Storage::disk('s3')->readStream($key);
                    if (! is_resource($readStream)) {
                        throw new \RuntimeException("Unable to open S3 stream: {$key}");
                    }

                    $writeStream = fopen($localPath, 'wb');
                    if (! is_resource($writeStream)) {
                        fclose($readStream);
                        throw new \RuntimeException("Unable to open local file: {$localPath}");
                    }

                    stream_copy_to_stream($readStream, $writeStream);
                    fclose($readStream);
                    fclose($writeStream);

                    $totalDownloaded++;
                } catch (\Throwable $e) {
                    $hasFailures = true;
                    $totalFailed++;
                    $this->warn("Post {$post->id}: failed {$key} ({$e->getMessage()})");
                }
            }

            if ($hasAnyImage && ! $hasFailures && $hasSyncFlag) {
                $payload = [
                    'images_local_synced' => true,
                ];

                if ($hasSyncAtFlag) {
                    $payload['images_local_synced_at'] = now();
                }

                $post->forceFill($payload)->save();
            }
        }

        $this->info('S3 structure scan complete.');
        $this->line('Scanned folders count: ' . count($scannedDirectories));
        $this->line("Downloaded images: {$totalDownloaded}");
        $this->line("Already existing locally: {$totalExisting}");
        $this->line("Failed downloads: {$totalFailed}");

        return self::SUCCESS;
    }

    private function normalizeS3Key(string $imageValue): ?string
    {
        $value = trim($imageValue);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $value = urldecode(ltrim($value, '/'));
        if ($value === '') {
            return null;
        }

        foreach (['upload/', 'posts/', 'photos/'] as $marker) {
            $pos = strpos($value, $marker);
            if ($pos !== false) {
                return substr($value, $pos);
            }
        }

        return $value;
    }
}
