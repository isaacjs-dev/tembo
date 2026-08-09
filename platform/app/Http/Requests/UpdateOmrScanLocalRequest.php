<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOmrScanLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers_json' => ['required', 'string', 'json', 'max:200000'],
            'quality_json' => ['required', 'string', 'json', 'max:100000'],
            'warped_image' => [
                'nullable',
                'string',
                'max:15000000',
                'regex:/^data:image\/(?:png|jpe?g|webp);base64,[A-Za-z0-9+\/=\r\n]+$/',
            ],
        ];
    }
}
