<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateSongsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'song_ids' => ['required', 'array', 'min:1'],
            'song_ids.*' => ['required', 'uuid', 'exists:songs,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'artist_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'album_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'track' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'disc' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'genre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'add_tag_ids' => ['sometimes', 'array'],
            'add_tag_ids.*' => ['integer', 'exists:tags,id'],
            'remove_tag_ids' => ['sometimes', 'array'],
            'remove_tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
