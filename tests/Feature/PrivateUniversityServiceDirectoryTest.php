<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivateUniversityServiceDirectoryTest extends TestCase
{
    public function test_private_directory_preserves_existing_contact_keys_and_other_offices(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'prism-contact-fixture-');
        file_put_contents($path, '<?php return '.var_export([
            'contacts' => [
                'Facilities Management Office' => [
                    'existing-private-contact' => ['name' => 'Demo Supervisor', 'email' => 'supervisor@example.test'],
                ],
            ],
        ], true).';');

        try {
            $settings = $this->loadDirectory($path);

            $this->assertSame([
                'existing-private-contact' => ['name' => 'Demo Supervisor', 'email' => 'supervisor@example.test'],
            ], $settings['contacts']['Facilities Management Office']);
            $this->assertArrayHasKey('library-contact', $settings['contacts']['Learning Resource Center']);
        } finally {
            unlink($path);
        }
    }

    public function test_missing_private_file_keeps_the_public_fallback_directory(): void
    {
        $settings = $this->loadDirectory(sys_get_temp_dir().'/prism-no-contact-file-'.bin2hex(random_bytes(8)));

        $this->assertArrayHasKey('facilities-contact', $settings['contacts']['Facilities Management Office']);
    }

    public function test_empty_path_disables_private_directory_loading_for_reproducible_tests(): void
    {
        $settings = $this->loadDirectory('');

        $this->assertArrayHasKey('facilities-contact', $settings['contacts']['Facilities Management Office']);
    }

    private function loadDirectory(string $path): array
    {
        $key = 'PRISM_US_CONTACTS_FILE';
        $previousEnv = $_ENV[$key] ?? null;
        $previousServer = $_SERVER[$key] ?? null;
        $_ENV[$key] = $_SERVER[$key] = $path;

        try {
            return require config_path('university_service.php');
        } finally {
            if ($previousEnv === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $previousEnv;
            }

            if ($previousServer === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $previousServer;
            }
        }
    }
}
