<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserFinderService
{
    /**
     * Busca um usuário por email ou código de vínculo.
     *
     * @return array{found: bool, user: ?User, suggestion: string}
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if (empty($query)) {
            return ['found' => false, 'user' => null, 'suggestion' => 'create'];
        }

        // Determinar se é email ou código
        if (filter_var($query, FILTER_VALIDATE_EMAIL)) {
            return $this->findByEmail($query);
        }

        if ($this->isValidLinkCode($query)) {
            return $this->findByLinkCode($query);
        }

        throw ValidationException::withMessages([
            'search' => 'Informe um e-mail válido ou código de vínculo (8 caracteres alfanuméricos).',
        ]);
    }

    /**
     * Busca por email.
     */
    public function findByEmail(string $email): array
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            return [
                'found' => true,
                'user' => $user,
                'suggestion' => 'invite',
            ];
        }

        return ['found' => false, 'user' => null, 'suggestion' => 'create'];
    }

    /**
     * Busca por código de vínculo (8 caracteres).
     */
    public function findByLinkCode(string $code): array
    {
        $user = User::where('link_code', strtoupper($code))->first();

        if ($user) {
            return [
                'found' => true,
                'user' => $user,
                'suggestion' => 'invite',
            ];
        }

        throw ValidationException::withMessages([
            'search' => 'Código de vínculo não encontrado.',
        ]);
    }

    /**
     * Valida formato do código de vínculo.
     * 8 caracteres alfanuméricos (without I, O, 0, 1).
     */
    public function isValidLinkCode(string $code): bool
    {
        return (bool) preg_match('/^[A-HJ-NP-Z2-9]{8}$/i', $code);
    }

    /**
     * Verifica se um usuário já está vinculado a uma organização.
     */
    public function isAlreadyLinked(User $user, int $organizationId): bool
    {
        return $user->organizations()
            ->wherePivot('organization_id', $organizationId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Gera um novo código de vínculo único.
     */
    public static function generateLinkCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (User::where('link_code', $code)->exists());

        return $code;
    }
}
