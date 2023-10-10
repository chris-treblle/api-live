<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return null === auth('sanctum')->user()?->getAuthIdentifier();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
        ];
    }
}
