<?php

namespace App\Services;

use App\Models\Organization;

/**
 * Assina os metadados do QR com HMAC e preserva a leitura de gabaritos históricos
 * cifrados com AES-256-GCM.
 *
 * O formato atual (v5) autentica todos os campos do payload, inclusive geometria,
 * limites de página e contagem de opções. QRs v3 emitidos anteriormente continuam
 * verificáveis pelo contrato legado restrito.
 */
class QrCodeSigningService
{
    /** @var list<int> */
    private const SUPPORTED_VERSIONS = [3, 4, 5];

    /** @var array<int, list<string>> */
    private const ALLOWED_FIELDS = [
        // The v3 signature only covered this exact legacy set. Accepting any
        // additional field would make unsigned metadata look trustworthy.
        3 => ['e', 'c', 'h', 'p', 'v', 'tpl_id', 'tpl_v', 'gab_enc', 'chk'],
        // v4/v5 authenticate the complete canonical payload.
        // Historical v4 emitters also included the redundant `tpl` slug.
        4 => ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'cols', 'tpl', 'tpl_id', 'tpl_v', 'g', 'oc', 'gab_enc', 'pts', 'chk'],
        5 => ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'cols', 'tpl_id', 'tpl_v', 'g', 'oc', 'gab_enc', 'pts', 'chk'],
    ];

    private const HMAC_LENGTH = 32;

    private const LEGACY_HMAC_LENGTH = 16;

    /** Retorna uma chave binária de 32 bytes, segregada por organização. */
    private function rawKey(?int $organizationId): string
    {
        $base = null;
        if ($organizationId) {
            $base = Organization::query()->find($organizationId)?->omr_hmac_secret;
        }

        $base = $base ?: (string) config('app.key');
        if ($base === '') {
            throw new \LogicException('APP_KEY ou segredo OMR da organização precisa estar configurado.');
        }

        return hash('sha256', $base.'|omr|'.($organizationId ?? 'system'), true);
    }

    /**
     * Serialização determinística do payload inteiro, exceto a própria assinatura.
     * Arrays indexados preservam a ordem; objetos associativos têm as chaves ordenadas.
     */
    private function canonical(array $payload): string
    {
        unset($payload['chk']);

        return json_encode(
            $this->canonicalizeValue($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private function canonicalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalizeValue($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeValue($item);
        }

        return $value;
    }

    private function legacyPayloadSignature(array $payload, ?int $organizationId): string
    {
        $canonical = implode('|', [
            $payload['e'] ?? '',
            $payload['c'] ?? '',
            $payload['h'] ?? '',
            $payload['p'] ?? 1,
            $payload['tpl_id'] ?? '',
            $payload['tpl_v'] ?? '',
            $payload['gab_enc'] ?? '',
        ]);

        return substr(
            hash_hmac('sha256', $canonical, $this->rawKey($organizationId)),
            0,
            self::LEGACY_HMAC_LENGTH
        );
    }

    public function signPayload(array $payload, ?int $organizationId): string
    {
        return substr(
            hash_hmac('sha256', $this->canonical($payload), $this->rawKey($organizationId)),
            0,
            self::HMAC_LENGTH
        );
    }

    public function verifyPayload(array $payload, ?int $organizationId): bool
    {
        if (! $this->hasSupportedContract($payload)) {
            return false;
        }

        $actual = $payload['chk'] ?? '';
        if (! is_string($actual) || $actual === '') {
            return false;
        }

        if (hash_equals($this->signPayload($payload, $organizationId), $actual)) {
            return true;
        }

        // QR v5 usa a mesma assinatura truncada de 128 bits do v4, codificada em
        // base64url. Isso reduz 10 caracteres em relação ao hexadecimal e melhora
        // materialmente a leitura em um QR impresso, sem reduzir a entropia. QRs v4
        // continuam usando o formato hexadecimal acima.
        if ((int) ($payload['v'] ?? 0) >= 5) {
            $expected = $this->compactSignature($payload, $organizationId);
            if (hash_equals($expected, $actual)) {
                return true;
            }
        }

        // Compatibilidade somente para QRs antigos. O emissor atual usa v5.
        return (int) $payload['v'] === 3
            && hash_equals($this->legacyPayloadSignature($payload, $organizationId), $actual);
    }

    /**
     * Reject unknown versions, unknown fields and structurally incomplete
     * payloads before signature verification. This prevents a modern QR from
     * being downgraded into the permissive legacy path.
     */
    public function hasSupportedContract(array $payload): bool
    {
        $version = $payload['v'] ?? null;
        if (! is_int($version) || ! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return false;
        }

        if (array_diff(array_keys($payload), self::ALLOWED_FIELDS[$version]) !== []) {
            return false;
        }

        $required = match ($version) {
            3 => ['e', 'c', 'h', 'p', 'v', 'tpl_id', 'tpl_v', 'chk'],
            // Early v4 cards did not always carry page ranges/option counts.
            4 => ['e', 'c', 'h', 'p', 'v', 'tpl_id', 'tpl_v', 'g', 'chk'],
            5 => ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'tpl_id', 'tpl_v', 'g', 'oc', 'chk'],
        };

        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                return false;
            }
        }

        foreach (['e', 'c', 'p', 'tpl_id', 'tpl_v'] as $field) {
            if (! is_int($payload[$field]) || $payload[$field] < 1) {
                return false;
            }
        }

        if (! is_string($payload['h']) || $payload['h'] === '' || strlen($payload['h']) > 128) {
            return false;
        }

        if ($version >= 4) {
            $pageFields = ['pt', 'qs', 'qe', 'rpp'];
            $presentPageFields = array_filter($pageFields, fn (string $field): bool => array_key_exists($field, $payload));
            if ($presentPageFields !== [] && count($presentPageFields) !== count($pageFields)) {
                return false;
            }
            foreach ($presentPageFields as $field) {
                if (! is_int($payload[$field]) || $payload[$field] < 1) {
                    return false;
                }
            }

            if ($presentPageFields !== [] && ((int) $payload['p'] > (int) $payload['pt'] || (int) $payload['qs'] > (int) $payload['qe'])) {
                return false;
            }

            if (! $this->validGeometryVector($payload['g'])) {
                return false;
            }

            if (array_key_exists('oc', $payload)) {
                if (! is_string($payload['oc']) || ! preg_match('/^(?:0|[2-9])+$/', $payload['oc'])) {
                    return false;
                }
                if ($presentPageFields !== []) {
                    $questionCount = (int) $payload['qe'] - (int) $payload['qs'] + 1;
                    if (strlen($payload['oc']) !== $questionCount) {
                        return false;
                    }
                }
            }

            if ($presentPageFields !== [] && isset($payload['oc']) && ! $this->geometryFitsPage($payload)) {
                return false;
            }
        }

        if (isset($payload['cols']) && (! is_int($payload['cols']) || $payload['cols'] < 1)) {
            return false;
        }
        if (isset($payload['tpl']) && (! is_string($payload['tpl']) || $payload['tpl'] === '' || strlen($payload['tpl']) > 100)) {
            return false;
        }
        if (isset($payload['gab_enc']) && (! is_string($payload['gab_enc']) || $payload['gab_enc'] === '')) {
            return false;
        }
        if (isset($payload['pts']) && ! is_array($payload['pts']) && ! is_string($payload['pts'])) {
            return false;
        }
        if (! $this->validSignatureShape($version, (string) $payload['chk'])) {
            return false;
        }

        return true;
    }

    private function validGeometryVector(mixed $geometry): bool
    {
        if (! is_array($geometry) || count($geometry) !== 6) {
            return false;
        }
        foreach ($geometry as $coordinate) {
            if (! is_int($coordinate) || $coordinate < 0 || $coordinate > 10000) {
                return false;
            }
        }

        return $geometry[2] > 0
            && $geometry[3] > 0
            && $geometry[4] > 0
            && $geometry[5] > 0
            && $geometry[0] + $geometry[4] <= 10000
            && $geometry[1] + $geometry[4] <= 10000;
    }

    private function geometryFitsPage(array $payload): bool
    {
        $geometry = $payload['g'];
        $questionCount = (int) $payload['qe'] - (int) $payload['qs'] + 1;
        $rows = min($questionCount, (int) $payload['rpp']);
        $columns = (int) ceil($questionCount / (int) $payload['rpp']);
        $maxOptions = max(array_map('intval', str_split((string) $payload['oc'])));

        return $geometry[0] + (($columns - 1) * $geometry[2]) + (max(0, $maxOptions - 1) * $geometry[5]) + $geometry[4] <= 10000
            && $geometry[1] + (($rows - 1) * $geometry[3]) + $geometry[4] <= 10000;
    }

    private function validSignatureShape(int $version, string $signature): bool
    {
        return match ($version) {
            3 => preg_match('/^(?:[a-f0-9]{16}|[a-f0-9]{32})$/', $signature) === 1,
            4 => preg_match('/^[a-f0-9]{32}$/', $signature) === 1,
            5 => preg_match('/^(?:[A-Za-z0-9_-]{22}|[a-f0-9]{32})$/', $signature) === 1,
        };
    }

    /** Cifra um vetor de índices em base64(iv|tag|ciphertext). */
    public function encryptGabarito(array $answers, ?int $organizationId): string
    {
        $plaintext = json_encode(array_values($answers), JSON_THROW_ON_ERROR);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->rawKey($organizationId),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new \RuntimeException('Não foi possível cifrar o gabarito OMR.');
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    /** Decifra o gabarito; retorna null para conteúdo inválido ou adulterado. */
    public function decryptGabarito(?string $encryptedAnswers, ?int $organizationId): ?array
    {
        if (empty($encryptedAnswers)) {
            return null;
        }

        $raw = base64_decode($encryptedAnswers, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $plaintext = openssl_decrypt(
            substr($raw, 28),
            'aes-256-gcm',
            $this->rawKey($organizationId),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16)
        );
        if ($plaintext === false) {
            return null;
        }

        try {
            $answers = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($answers) || ! array_is_list($answers)) {
            return null;
        }
        foreach ($answers as $answer) {
            if (! is_int($answer) || $answer < -1 || $answer > 8) {
                return null;
            }
        }

        return $answers;
    }

    /**
     * Monta o QR final. Novos emissores enviam somente identidade e geometria;
     * quando um consumidor histórico fornecer `gab`, hybrid/qr_embedded ainda o
     * cifram para manter compatibilidade, nunca expondo o vetor em texto claro.
     */
    public function buildPayload(array $basePayload, string $mode, ?int $organizationId): array
    {
        if (! in_array($mode, ['preloaded', 'qr_embedded', 'hybrid'], true)) {
            throw new \InvalidArgumentException("Modo de leitura OMR inválido: {$mode}.");
        }

        $payload = $basePayload;
        if ($mode === 'preloaded') {
            unset($payload['gab'], $payload['gab_enc'], $payload['pts']);
        } else {
            if (isset($payload['gab'])) {
                if (! is_array($payload['gab'])) {
                    throw new \InvalidArgumentException('O gabarito do QR precisa ser um vetor.');
                }
                $payload['gab_enc'] = $this->encryptGabarito($payload['gab'], $organizationId);
            }
            unset($payload['gab']);
        }

        $payload['chk'] = (int) ($payload['v'] ?? 0) >= 5
            ? $this->compactSignature($payload, $organizationId)
            : $this->signPayload($payload, $organizationId);

        return $payload;
    }

    /**
     * HMAC de 128 bits (mesmo truncamento legado), com codificação URL-safe sem
     * padding para caber melhor no QR. Não deve ser usado como segredo fora do QR.
     */
    private function compactSignature(array $payload, ?int $organizationId): string
    {
        $raw = substr(
            hash_hmac('sha256', $this->canonical($payload), $this->rawKey($organizationId), true),
            0,
            16
        );

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** @deprecated Assinatura de identificação para QRs anteriores ao formato v3. */
    public function sign(array $qrPayload, ?string $hmacKey = null): string
    {
        $key = $hmacKey ?? (string) config('app.key');
        $data = implode('|', [
            $qrPayload['e'] ?? '',
            $qrPayload['c'] ?? '',
            $qrPayload['h'] ?? '',
            $qrPayload['p'] ?? 1,
        ]);

        return substr(hash_hmac('sha256', $data, $key), 0, 12);
    }

    /** @deprecated Verificação de identificação para QRs anteriores ao formato v3. */
    public function verify(array $qrPayload, ?string $hmacKey = null): bool
    {
        $actual = $qrPayload['chk'] ?? '';

        return is_string($actual)
            && $actual !== ''
            && hash_equals($this->sign($qrPayload, $hmacKey), $actual);
    }
}
