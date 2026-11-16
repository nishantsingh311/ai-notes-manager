<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteSearchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->merge(['q' => trim($this->query('q'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['bail', 'required', 'string', 'min:2', 'max:500'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
