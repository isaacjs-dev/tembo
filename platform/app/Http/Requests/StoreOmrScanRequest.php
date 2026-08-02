<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOmrScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Middleware handles auth
    }

    public function rules(): array
    {
        return [
            'exam_id' => 'required|exists:exams,id',
            'image' => 'required|image|max:10240', // Max 10MB
        ];
    }
}
