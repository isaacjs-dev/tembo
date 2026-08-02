<?php

namespace Database\Seeders;

use App\Models\ScanMode;
use Illuminate\Database\Seeder;

class ScanModeSeeder extends Seeder
{
    public function run(): void
    {
        ScanMode::updateOrCreate(
            ['slug' => 'hybrid'],
            [
                'name' => 'Híbrido',
                'description' => 'Combina pré-carregamento quando há internet com uso do QR Code para identificação, validação e contingência. Padrão recomendado.',
                'is_default' => true,
                'requires_predownload' => true,
                'requires_qr_data' => true,
                'offline_capable' => true,
                'qr_payload_schema' => [
                    'required' => ['e', 'c', 'h', 'v', 'chk'],
                    'optional' => ['p', 'pt', 'qs', 'qe', 'cols', 'rpp', 'gab'],
                    'description' => 'QR contém identificação + gabarito compacto como fallback. Dados completos preferidos do cache.',
                ],
                'is_active' => true,
            ]
        );

        ScanMode::updateOrCreate(
            ['slug' => 'preloaded'],
            [
                'name' => 'Dados Pré-carregados',
                'description' => 'O professor baixa previamente os dados das provas. O QR Code é usado apenas para identificação e validação.',
                'is_default' => false,
                'requires_predownload' => true,
                'requires_qr_data' => false,
                'offline_capable' => true,
                'qr_payload_schema' => [
                    'required' => ['e', 'c', 'h', 'v', 'chk'],
                    'optional' => ['p', 'pt'],
                    'description' => 'QR contém apenas identificação. Todos os dados vêm do cache local.',
                ],
                'is_active' => true,
            ]
        );

        ScanMode::updateOrCreate(
            ['slug' => 'qr_embedded'],
            [
                'name' => 'Dados no QR Code',
                'description' => 'O QR Code da folha contém dados estruturados suficientes para identificação e correção, sem necessidade de download prévio.',
                'is_default' => false,
                'requires_predownload' => false,
                'requires_qr_data' => true,
                'offline_capable' => true,
                'qr_payload_schema' => [
                    'required' => ['e', 'c', 'h', 'v', 'qs', 'qe', 'cols', 'rpp', 'gab', 'pts', 'chk'],
                    'optional' => ['p', 'pt'],
                    'description' => 'QR contém gabarito completo embutido para correção autônoma.',
                ],
                'is_active' => true,
            ]
        );
    }
}
