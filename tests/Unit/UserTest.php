<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_full_name_includes_the_middle_name_or_initial_when_present(): void
    {
        $user = new User([
            'first_name' => 'Jamie',
            'middle_name' => 'Example',
            'last_name' => 'Sample',
        ]);

        $this->assertSame('Jamie Example Sample', $user->full_name);

        $user->middle_name = 'E.';

        $this->assertSame('Jamie E. Sample', $user->full_name);
    }

    public function test_full_name_has_no_extra_space_when_middle_name_is_missing(): void
    {
        $user = new User([
            'first_name' => 'Jamie',
            'middle_name' => null,
            'last_name' => 'Sample',
        ]);

        $this->assertSame('Jamie Sample', $user->full_name);
    }
}
