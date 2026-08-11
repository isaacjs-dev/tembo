<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Single physical profile for every newly generated OMR QR Code. */
class OmrQrRendererService
{
    public const ERROR_CORRECTION = 'M';

    public const QUIET_ZONE_MODULES = 4;

    public const SOURCE_SIZE_PX = 300;

    public const MIN_PRINT_SIZE_MM = 30;

    public const MAX_PRINT_SIZE_MM = 30;

    public const MIN_MODULE_PITCH_MM = 0.35;

    public function render(string $encodedPayload): string
    {
        return $this->renderWithProfile($encodedPayload)['svg'];
    }

    /**
     * @return array{svg:string,profile:array{size_mm:float,quiet_zone_modules:int,error_correction:string,source_size_px:int,symbol_version:int,data_modules:int,total_modules:int,module_pitch_mm:float,payload_hash:string}}
     */
    public function renderWithProfile(string $encodedPayload): array
    {
        $svg = (string) QrCode::format('svg')
            ->errorCorrection(self::ERROR_CORRECTION)
            ->size(self::SOURCE_SIZE_PX)
            ->margin(self::QUIET_ZONE_MODULES)
            ->color(0, 0, 0)
            ->backgroundColor(255, 255, 255)
            ->generate($encodedPayload);

        if (! preg_match('/transform="scale\(([0-9.]+)\)"/', $svg, $matches)) {
            throw new \RuntimeException('O gerador QR não informou a escala modular do SVG.');
        }

        $scale = (float) $matches[1];
        $totalModules = (int) round(self::SOURCE_SIZE_PX / $scale);
        $dataModules = $totalModules - (2 * self::QUIET_ZONE_MODULES);
        $version = (int) ((($dataModules - 21) / 4) + 1);
        if ($scale <= 0 || $dataModules < 21 || $version < 1 || $version > 40) {
            throw new \RuntimeException('A malha modular gerada para o QR é inválida.');
        }

        $requiredSize = ceil($totalModules * self::MIN_MODULE_PITCH_MM * 2) / 2;
        $sizeMm = max((float) self::MIN_PRINT_SIZE_MM, $requiredSize);
        if ($sizeMm > self::MAX_PRINT_SIZE_MM) {
            throw new \RuntimeException(sprintf(
                'O payload QR exige %.1f mm e excede a área física suportada de %d mm.',
                $sizeMm,
                self::MAX_PRINT_SIZE_MM,
            ));
        }

        return [
            'svg' => $svg,
            'profile' => [
                'size_mm' => $sizeMm,
                'quiet_zone_modules' => self::QUIET_ZONE_MODULES,
                'error_correction' => self::ERROR_CORRECTION,
                'source_size_px' => self::SOURCE_SIZE_PX,
                'symbol_version' => $version,
                'data_modules' => $dataModules,
                'total_modules' => $totalModules,
                'module_pitch_mm' => round($sizeMm / $totalModules, 4),
                'payload_hash' => hash('sha256', $encodedPayload),
            ],
        ];
    }

    /** @return array{min_size_mm:int,max_size_mm:int,min_module_pitch_mm:float,quiet_zone_modules:int,error_correction:string,source_size_px:int} */
    public function constraints(): array
    {
        return [
            'min_size_mm' => self::MIN_PRINT_SIZE_MM,
            'max_size_mm' => self::MAX_PRINT_SIZE_MM,
            'min_module_pitch_mm' => self::MIN_MODULE_PITCH_MM,
            'quiet_zone_modules' => self::QUIET_ZONE_MODULES,
            'error_correction' => self::ERROR_CORRECTION,
            'source_size_px' => self::SOURCE_SIZE_PX,
        ];
    }
}
