<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule restricting an officer to selecting students within
 * their own academic unit.
 */
class StudentInOfficerDepartment implements ValidationRule
{
    public function __construct(private readonly User $officer) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isEligible = User::query()
            ->whereKey($value)
            ->where('role', 'student')
            ->where('status', '!=', 'inactive')
            ->whereHas('studentProfile', fn ($profile) => $profile
                ->forOfficerDepartment($this->officer))
            ->exists();

        if (! $isEligible) {
            $fail('The selected student is not part of your assigned department.');
        }
    }
}
