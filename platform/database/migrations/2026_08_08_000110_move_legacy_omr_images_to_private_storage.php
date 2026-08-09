<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $checksum = static function ($disk, string $path): ?string {
            $stream = $disk->readStream($path);
            if ($stream === false) {
                return null;
            }

            try {
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);

                return hash_final($context);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        };

        $moveToPrivate = static function (?string $path) use ($checksum): void {
            $normalized = str_replace('\\', '/', (string) $path);
            if ($normalized === '' || ! str_starts_with($normalized, 'omr-scans/')) {
                return;
            }

            $private = Storage::disk('local');
            $public = Storage::disk('public');
            if (! $public->exists($normalized)) {
                return;
            }

            if (! $private->exists($normalized)) {
                $stream = $public->readStream($normalized);
                if ($stream === false) {
                    return;
                }

                try {
                    if (! $private->writeStream($normalized, $stream)) {
                        return;
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            // Delete only after proving that the private object is byte-for-byte
            // equivalent. A pre-existing collision must preserve both files for
            // manual recovery instead of destroying the historical source.
            $publicChecksum = $checksum($public, $normalized);
            $privateChecksum = $checksum($private, $normalized);
            if ($publicChecksum !== null
                && $privateChecksum !== null
                && hash_equals($publicChecksum, $privateChecksum)) {
                $public->delete($normalized);
            }
        };

        DB::table('omr_scans')->orderBy('id')->chunkById(100, function ($scans) use ($moveToPrivate): void {
            foreach ($scans as $scan) {
                $moveToPrivate($scan->image_path);
                $moveToPrivate($scan->warped_path);
                $moveToPrivate($scan->debug_path);
            }
        });

        DB::table('omr_scan_pages')->orderBy('id')->chunkById(100, function ($pages) use ($moveToPrivate): void {
            foreach ($pages as $page) {
                $moveToPrivate($page->image_path);
            }
        });
    }

    public function down(): void
    {
        // Privacy migrations are intentionally not reversed to a public disk.
        // Database paths remain unchanged and the authenticated readers support
        // the private location before and after rollback.
    }
};
