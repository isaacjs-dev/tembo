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
                    'schema' => 'contracts/omr/qr-payload.schema.json',
                    'required' => ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'tpl_id', 'tpl_v', 'g', 'oc', 'chk'],
                    'optional' => ['gab_enc'],
                    'description' => 'QR v5 fornece identidade e geometria assinadas. O cache é preferido; autenticidade e nota final são confirmadas no servidor.',
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
                    'schema' => 'contracts/omr/qr-payload.schema.json',
                    'required' => ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'tpl_id', 'tpl_v', 'g', 'oc', 'chk'],
                    'optional' => [],
                    'description' => 'QR v5 identifica a cópia e a página; gabarito e pontos autorizados vêm do cache local.',
                ],
                'is_active' => true,
            ]
        );

        ScanMode::updateOrCreate(
            ['slug' => 'qr_embedded'],
            [
                'name' => 'Dados no QR Code',
                'description' => 'O QR Code contém identidade e geometria suficientes para capturar sem download prévio; a autenticidade e a correção oficial são concluídas pelo servidor.',
                'is_default' => false,
                'requires_predownload' => false,
                'requires_qr_data' => true,
                'offline_capable' => true,
                'qr_payload_schema' => [
                    'schema' => 'contracts/omr/qr-payload.schema.json',
                    'required' => ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'tpl_id', 'tpl_v', 'g', 'oc', 'chk'],
                    'optional' => ['gab_enc'],
                    'description' => 'Nome histórico preservado: o QR permite captura offline, mas não expõe gabarito nem chave e não autoriza correção local autônoma.',
                ],
                'is_active' => true,
            ]
        );
    }
}
