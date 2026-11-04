<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
     * Accepts 'nim' (NIP/NIM/Email) field from the SATU USU clone form,
     * or 'email' field for backward compatibility with existing admin/test flows.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Support both 'nim' (from new SATU USU form) and 'email' (from tests/admin)
        if ($this->has('nim')) {
            return [
                'nim' => ['required', 'string'],
                'password' => ['required', 'string'],
            ];
        }

        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Resolve the identity field value.
     * The user may login with NIM, NIP, or Email via the 'nim' field.
     */
    public function identity(): string
    {
        return $this->input('nim') ?? $this->input('email') ?? '';
    }

    /**
     * Determine the credential column to use for authentication.
     * Auto-detects whether the input is an email or a NIM/NIP.
     */
    public function identityColumn(): string
    {
        $identity = $this->identity();

        if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        return 'nim';
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Supports login via NIM, NIP, or Email address.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            $this->identityColumn() => $this->identity(),
            'password' => $this->input('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $errorField = $this->has('nim') ? 'nim' : 'email';

            throw ValidationException::withMessages([
                $errorField => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $errorField = $this->has('nim') ? 'nim' : 'email';

        throw ValidationException::withMessages([
            $errorField => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identity()).'|'.$this->ip());
    }
}
