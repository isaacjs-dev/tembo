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
            'answers_json' => 'required|string', // JSON string from frontend
            'quality_json' => 'required|string', // JSON string from frontend
            'warped_image' => 'nullable|string', // Base64 string
        ];
    }
}
