<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string', 'max:50000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->exists('title') && ! $this->exists('content')) {
                $validator->errors()->add('title', 'Provide at least one field to update.');
                $validator->errors()->add('content', 'Provide at least one field to update.');
            }
        }];
    }
}
