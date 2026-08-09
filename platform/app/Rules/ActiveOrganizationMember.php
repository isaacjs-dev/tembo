<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveOrganizationMember implements ValidationRule
{
    public function __construct(
        private readonly int $organizationId,
        private readonly string $role,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isAllowed = filter_var($value, FILTER_VALIDATE_INT) !== false
            && User::query()
                ->whereKey((int) $value)
                ->where('status', 'active')
                ->memberOfOrganization($this->organizationId, $this->role)
                ->exists();

        if (! $isAllowed) {
            $fail('O usuário selecionado não possui um vínculo ativo e compatível com esta instituição.');
        }
    }
}
