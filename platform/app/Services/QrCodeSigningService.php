<?php

namespace App\Services;

use App\Models\Organization;

/**
 * Assina os metadados do QR com HMAC e cifra o gabarito com AES-256-GCM.
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
        $version = filter_var($payload['v'] ?? null, FILTER_VALIDATE_INT);
        if ($version === false || ! in_array($version, self::SUPPORTED_VERSIONS, true)) {
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
            if (filter_var($payload[$field], FILTER_VALIDATE_INT) === false || (int) $payload[$field] < 1) {
                return false;
            }
        }

        if (! is_string($payload['h']) || $payload['h'] === '') {
            return false;
        }

        if ($version >= 4) {
            $pageFields = ['pt', 'qs', 'qe', 'rpp'];
            $presentPageFields = array_filter($pageFields, fn (string $field): bool => array_key_exists($field, $payload));
            if ($presentPageFields !== [] && count($presentPageFields) !== count($pageFields)) {
                return false;
            }
            foreach ($presentPageFields as $field) {
                if (filter_var($payload[$field], FILTER_VALIDATE_INT) === false || (int) $payload[$field] < 1) {
                    return false;
                }
            }

            if ($presentPageFields !== [] && ((int) $payload['p'] > (int) $payload['pt'] || (int) $payload['qs'] > (int) $payload['qe'])) {
                return false;
            }

            if (! is_array($payload['g']) || count($payload['g']) !== 6) {
                return false;
            }
            foreach ($payload['g'] as $coordinate) {
                if (! is_int($coordinate) || $coordinate < 0) {
                    return false;
                }
            }

            if (array_key_exists('oc', $payload)) {
                if (! is_string($payload['oc']) || ! preg_match('/^[0-9]+$/', $payload['oc'])) {
                    return false;
                }
                if ($presentPageFields !== []) {
                    $questionCount = (int) $payload['qe'] - (int) $payload['qs'] + 1;
                    if (strlen($payload['oc']) !== $questionCount) {
                        return false;
                    }
                }
            }
        }

        return true;
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
     * Monta o QR final. O modo preloaded leva somente identificação; hybrid e
     * qr_embedded levam o gabarito cifrado, nunca o vetor em texto claro.
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
