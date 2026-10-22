<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentHandbookDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_displays_the_handbook_download(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('NU Clark Student Handbook')
            ->assertSee(route('student.handbook.view'))
            ->assertSee(route('student.handbook.download'));
    }

    public function test_student_can_download_the_pdf_handbook(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)
            ->get(route('student.handbook.download'))
            ->assertOk()
            ->assertDownload('NU Clark Student Handbook 2025 Edition.pdf');
    }

    public function test_student_can_view_the_pdf_handbook_inline(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)
            ->get(route('student.handbook.view'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_student_cannot_download_the_student_handbook(): void
    {
        $this->actingAs($this->user('discipline_officer'))
            ->get(route('student.handbook.download'))
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::create([
            'first_name' => 'Handbook',
            'last_name' => 'Tester',
            'email' => $role.'-handbook@example.test',
            'password' => Hash::make('Password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
