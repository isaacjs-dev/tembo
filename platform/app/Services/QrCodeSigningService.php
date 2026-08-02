<?php

namespace App\Services;

use App\Models\Organization;

/**
 * Assina os metadados do QR com HMAC e cifra o gabarito com AES-256-GCM.
 *
 * O formato atual (v4) autentica todos os campos do payload, inclusive geometria,
 * limites de página e contagem de opções. QRs v3 emitidos anteriormente continuam
 * verificáveis pelo contrato legado restrito.
 */
class QrCodeSigningService
{
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
        $actual = $payload['chk'] ?? '';
        if (! is_string($actual) || $actual === '') {
            return false;
        }

        if (hash_equals($this->signPayload($payload, $organizationId), $actual)) {
            return true;
        }

        // Compatibilidade somente para QRs antigos. O emissor atual usa v4.
        return (int) ($payload['v'] ?? 0) <= 3
            && hash_equals($this->legacyPayloadSignature($payload, $organizationId), $actual);
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

        $payload['chk'] = $this->signPayload($payload, $organizationId);

        return $payload;
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
