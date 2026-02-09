<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlaylistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'rules.*.logic' => ['required_with:rules', 'string', 'in:and,or'],
            'rules.*.rules' => ['required_with:rules', 'array'],
            'rules.*.rules.*.field' => ['required', 'string'],
            'rules.*.rules.*.operator' => ['required', 'string'],
            'rules.*.rules.*.value' => ['required'],
        ];
    }
}
