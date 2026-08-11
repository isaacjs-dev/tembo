<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AppearanceAssetService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @return array{storage_disk:string,storage_path:string,mime_type:string,size_bytes:int,sha256:string} */
    public function store(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['asset' => 'A imagem deve ter no máximo 2 MB.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        $image = $bytes === false ? false : @getimagesizefromstring($bytes);
        $mime = is_array($image) ? ($image['mime'] ?? null) : null;
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages(['asset' => 'Envie uma imagem PNG ou JPEG válida.']);
        }
        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);
        if ($width < 16 || $height < 16 || $width > 5000 || $height > 5000 || $width * $height > 12_000_000) {
            throw ValidationException::withMessages(['asset' => 'Dimensões da imagem fora do limite seguro.']);
        }
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $path = 'appearance-assets/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Não foi possível armazenar o asset de aparência.');
        }

        return [
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];
    }

    public function deleteNew(array $asset): void
    {
        if (($asset['storage_disk'] ?? null) === 'local'
            && str_starts_with((string) ($asset['storage_path'] ?? ''), 'appearance-assets/')) {
            Storage::disk('local')->delete($asset['storage_path']);
        }
    }

    public function bytes(array $asset): string
    {
        if (($asset['storage_disk'] ?? null) !== 'local') {
            throw new RuntimeException('Disco de asset não autorizado.');
        }
        $path = (string) ($asset['storage_path'] ?? '');
        if (! str_starts_with($path, 'appearance-assets/') || str_contains($path, '..')) {
            throw new RuntimeException('Caminho de asset não autorizado.');
        }
        $bytes = Storage::disk('local')->get($path);
        if (strlen($bytes) !== (int) ($asset['size_bytes'] ?? -1)
            || ! hash_equals((string) ($asset['sha256'] ?? ''), hash('sha256', $bytes))) {
            throw new RuntimeException('Asset de aparência ausente ou adulterado.');
        }
        $image = @getimagesizefromstring($bytes);
        if (! is_array($image) || ($image['mime'] ?? null) !== ($asset['mime_type'] ?? null)
            || ! in_array($image['mime'], ['image/png', 'image/jpeg'], true)) {
            throw new RuntimeException('Conteúdo do asset não corresponde ao snapshot.');
        }

        return $bytes;
    }

    public function dataUri(array $asset): string
    {
        return 'data:'.$asset['mime_type'].';base64,'.base64_encode($this->bytes($asset));
    }
}
