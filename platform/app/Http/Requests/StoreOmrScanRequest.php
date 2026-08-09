<?php

namespace App\Http\Requests;

use App\Rules\ActiveOrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOmrScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Middleware handles auth
    }

    public function rules(): array
    {
        $organizationId = (int) $this->user()->organization_id;

        return [
            'exam_id' => [
                'required',
                'integer',
                Rule::exists('exams', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)->whereNull('deleted_at')
                ),
            ],
            'copy_id' => [
                'nullable',
                'integer',
                Rule::exists('exam_copies', 'id')->where(
                    fn ($query) => $query->where('exam_id', $this->input('exam_id'))
                ),
            ],
            'student_id' => [
                'nullable',
                'integer',
                new ActiveOrganizationMember($organizationId, 'student'),
            ],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'omr_payload' => ['nullable', 'json', 'max:200000'],
            'layout_meta' => ['nullable', 'json', 'max:100000'],
        ];
    }
}
