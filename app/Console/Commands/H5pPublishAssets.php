<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Publishes the H5P core + editor JS/CSS so the player and editor views can
 * load them over HTTP. By default copies vendor/h5p/* into public/vendor/* as
 * real files (portable — works regardless of whether the web server follows
 * symlinks); pass --link to symlink instead.
 *
 * Run on deploy after `composer install`.
 */
class H5pPublishAssets extends Command
{
    protected $signature = 'h5p:publish {--link : Symlink instead of copying real files}';

    protected $description = 'Publish H5P core/editor assets into public/vendor';

    public function handle(): int
    {
        $targets = [
            'h5p-core'   => base_path('vendor/h5p/h5p-core'),
            'h5p-editor' => base_path('vendor/h5p/h5p-editor'),
        ];

        $publicVendor = public_path('vendor');
        File::ensureDirectoryExists($publicVendor);

        foreach ($targets as $name => $source) {
            if (! is_dir($source)) {
                $this->error("Missing {$source} — run `composer install` first.");
                return self::FAILURE;
            }

            $link = $publicVendor . DIRECTORY_SEPARATOR . $name;

            // Remove any existing link/dir so the command is idempotent.
            if (is_link($link)) {
                @unlink($link);
            } elseif (is_dir($link)) {
                File::deleteDirectory($link);
            }

            if ($this->option('link')) {
                @symlink($source, $link);
                if (! is_link($link)) {
                    // Filesystem may not support symlinks — fall back to copy.
                    File::copyDirectory($source, $link);
                    $this->info("Symlink unsupported; copied vendor/h5p/{$name} → public/vendor/{$name}");
                } else {
                    $this->info("Linked public/vendor/{$name} → vendor/h5p/{$name}");
                }
            } else {
                File::copyDirectory($source, $link);
                $this->info("Copied vendor/h5p/{$name} → public/vendor/{$name}");
            }
        }

        $this->info('H5P assets published.');
        return self::SUCCESS;
    }
}
