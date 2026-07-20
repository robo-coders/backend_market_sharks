<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('message'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:4000']];
    }

    protected function prepareForValidation(): void
    {
        $body = (string) $this->input('body', '');
        $body = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $body);
        $body = str_replace("\r\n", "\n", $body);
        $body = preg_replace("/\n{3,}/", "\n\n", $body);
        $this->merge(['body' => trim($body)]);
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Message cannot be empty.',
            'body.max'      => 'Message is too long (max 4000 characters).',
        ];
    }
}
