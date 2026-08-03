<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        abort_if(
            $path === ''
            || str_contains($path, "\0")
            || collect($segments)->contains(
                static fn (string $segment): bool => $segment === ''
                    || $segment === '.'
                    || $segment === '..'
                    || str_starts_with($segment, '.')
            ),
            404
        );

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
