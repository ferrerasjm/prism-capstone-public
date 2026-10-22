<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private const SHS_UNIT_NAME = 'Senior High School';

    private const SHS_YEAR_LEVELS = ['Grade 11', 'Grade 12'];

    private const COLLEGE_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

    public function showPasswordForm(): View
    {
        return view('onboarding.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) use ($user) {
                    $identifier = $user->role === 'student'
                        ? $user->studentProfile?->student_number
                        : $user->institutional_id;

                    if ($identifier && $value === $identifier) {
                        $fail('Your password cannot be the same as your University ID.');
                    }
                },
            ],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'module' => 'auth',
            'record_id' => $user->id,
            'new_values' => ['password_changed' => true],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        if ($user->role !== 'student') {
            $user->update(['status' => 'active']);

            return redirect($this->dashboardRoute($user->role));
        }

        return redirect()->route('onboarding.profile');
    }

    public function showProfileForm(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return redirect()->route('onboarding.password');
        }

        return view('onboarding.profile', [
            'academicUnits' => AcademicUnit::query()
                ->where('is_active', true)
                ->whereNull('scope_year_level')
                ->catalog()
                ->with(['programs' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name')])
                ->get(),
            'shsUnitName' => self::SHS_UNIT_NAME,
            'collegeYearLevels' => self::COLLEGE_YEAR_LEVELS,
            'shsYearLevels' => self::SHS_YEAR_LEVELS,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $isShsProgram = AcademicProgram::query()
            ->where('name', $request->input('program'))
            ->whereHas('academicUnit', fn ($query) => $query->where('name', self::SHS_UNIT_NAME))
            ->exists();

        $validated = $request->validate([
            'program' => [
                'required',
                'string',
                'max:100',
                Rule::exists('academic_programs', 'name')->where('is_active', true),
            ],
            'year_level' => [
                'required',
                'string',
                'max:20',
                Rule::in($isShsProgram ? self::SHS_YEAR_LEVELS : self::COLLEGE_YEAR_LEVELS),
            ],
            'contact_number' => ['required', 'string', 'max:20'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_contact' => ['required', 'string', 'max:20'],
            'guardian_relationship' => ['required', 'string', 'in:Father,Mother,Guardian,Sibling,Other'],
        ]);

        $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return redirect()->route('onboarding.acknowledge');
    }

    public function showAcknowledgmentForm(): View|RedirectResponse
    {
        $user = Auth::user();
        $profile = $user->studentProfile;

        if ($user->role !== 'student') {
            return redirect()->route('onboarding.password');
        }

        if (! $profile || ! $profile->program) {
            return redirect()->route('onboarding.profile');
        }

        return view('onboarding.acknowledge', compact('profile'));
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $request->validate([
            'acknowledged' => ['required', 'accepted'],
        ]);

        $user = Auth::user();
        $now = now();
        $profile = $user->studentProfile;

        $user->studentProfile()->update(['data_accuracy_acknowledged_at' => $now]);

        $user->update(['status' => 'active']);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'module' => 'student_profiles',
            'record_id' => $profile->id,
            'new_values' => ['data_accuracy_acknowledged_at' => $now],
            'ip_address' => $request->ip(),
            'created_at' => $now,
        ]);

        return redirect()->route('privacy.notice');
    }

    private function dashboardRoute(string $role): string
    {
        return match ($role) {
            'super_admin' => route('admin.dashboard'),
            'discipline_coordinator' => route('coordinator.dashboard'),
            'discipline_officer' => route('officer.dashboard'),
            'reporting_user' => route('reporter.dashboard'),
            'guidance_officer' => route('guidance.dashboard'),
            'student' => route('student.dashboard'),
            default => route('login'),
        };
    }
}
