<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreatePlaylistRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_smart' => ['boolean'],
            'rules' => ['required_if:is_smart,true', 'nullable', 'array'],
            'rules.*.logic' => ['required_with:rules', 'string', 'in:and,or'],
            'rules.*.rules' => ['required_with:rules', 'array'],
            'rules.*.rules.*.field' => ['required', 'string'],
            'rules.*.rules.*.operator' => ['required', 'string'],
            'rules.*.rules.*.value' => ['present'],
            'song_ids' => ['nullable', 'array'],
            'song_ids.*' => ['uuid', 'exists:songs,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rules = $this->input('rules', []);
            if (!is_array($rules)) {
                return;
            }

            foreach ($rules as $groupIndex => $group) {
                if (!is_array($group) || !isset($group['rules']) || !is_array($group['rules'])) {
                    continue;
                }

                foreach ($group['rules'] as $ruleIndex => $rule) {
                    if (!is_array($rule)) {
                        continue;
                    }

                    $field = $rule['field'] ?? null;
                    $value = $rule['value'] ?? null;

                    // Boolean fields (like is_favorite) don't require a value
                    if ($field === 'is_favorite') {
                        continue;
                    }

                    // All other fields require a non-empty value
                    if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                        $validator->errors()->add(
                            "rules.{$groupIndex}.rules.{$ruleIndex}.value",
                            'The value field is required.'
                        );
                    }
                }
            }
        });
    }
}
