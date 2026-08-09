<?php

namespace App\Http\Requests;

use App\Rules\ActiveOrganizationMember;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmOmrScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = (int) $this->user()->organization_id;

        return [
            'student_id' => [
                'required',
                'integer',
                new ActiveOrganizationMember($organizationId, 'student'),
            ],
            'answers' => 'required|array',
        ];
    }
}
