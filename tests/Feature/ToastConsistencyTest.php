<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The login page skips the shared JS bundle (bootstrap.bundle.min.js included) for a
 * faster first paint, but its own toasts still called bootstrap.Toast directly with no
 * fallback for that. Every toast on the page fell into a manual classList.add('show') with
 * nothing to ever remove it - toasts appeared and then simply never disappeared, piling up
 * on repeated invalid submits. window.PrismToast (public/js/prism-toast.js) is loaded
 * unconditionally on every layout instead, with its own dismiss timer that does not depend
 * on Bootstrap's JS being present at all.
 */
class ToastConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_loads_the_shared_toast_helper(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('js/prism-toast.js', $html);

        // And not before it loads - the script tag must come before anything that could
        // call window.PrismToast.show().
        $toastScriptAt = strpos($html, 'js/prism-toast.js');
        $bodyOpenAt = strpos($html, '<body');
        $this->assertNotFalse($bodyOpenAt);
        $this->assertGreaterThan($bodyOpenAt, $toastScriptAt);
    }

    public function test_the_login_page_no_longer_defines_its_own_toast_plumbing(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        // The old implementation's fingerprints - if any of these come back, the page has
        // reverted to a second, inconsistent toast system.
        $this->assertStringNotContainsString('data-login-toast-region', $html);
        $this->assertStringNotContainsString('data-login-error-toast', $html);
        $this->assertStringNotContainsString('getToastRegion', $html);
        $this->assertStringNotContainsString("data-bs-delay=\"4000\"", $html);
    }

    public function test_a_validation_failure_is_rendered_through_the_shared_helper(): void
    {
        // A bad login attempt redirects back with an error, which login.blade.php now hands
        // to PrismToast.show() rather than rendering its own toast markup. Two separate
        // calls (not followingRedirects(), which reuses one session Store in-process and
        // does not re-run the marshalling a fresh request would) - each is a real request
        // through the test client's own persisted cookie jar.
        config(['services.recaptcha.enabled' => false]);

        // validate() calls back(), which needs a referer to know where "back" is - exactly
        // what a real browser submit sends, and what's missing without ->from() here.
        $this->from(route('login'))
            ->post(route('login.post'), ['email' => '', 'password' => ''])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors();

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('window.PrismToast', $html);
    }

    public function test_every_layout_loads_the_toast_helper_before_the_gated_bundle(): void
    {
        $layouts = [
            'resources/views/layouts/admin.blade.php',
            'resources/views/layouts/app.blade.php',
            'resources/views/layouts/coordinator.blade.php',
            'resources/views/layouts/guidance.blade.php',
            'resources/views/layouts/reporting-user.blade.php',
            'resources/views/layouts/staff.blade.php',
            'resources/views/layouts/student.blade.php',
        ];

        foreach ($layouts as $layout) {
            $source = file_get_contents(base_path($layout));

            $this->assertStringContainsString('js/prism-toast.js', $source, "{$layout} does not load the shared toast helper.");

            // Must sit before any @hasSection('skip-shared-js') gate, or a page that opts
            // out of the heavier bundle opts out of working toasts too.
            $toastAt = strpos($source, 'js/prism-toast.js');
            $gateAt = strpos($source, "skip-shared-js");

            if ($gateAt !== false) {
                $this->assertLessThan($gateAt, $toastAt, "{$layout} loads the toast helper after its skip-shared-js gate.");
            }
        }
    }

    public function test_no_page_or_script_initializes_bootstrap_toasts_independently(): void
    {
        $forbiddenPatterns = ['bootstrap.Toast', 'data-bs-delay', 'data-bs-autohide'];
        $violations = [];

        $toastSources = array_merge(
            File::allFiles(resource_path('views')),
            File::allFiles(public_path('js')),
        );

        foreach ($toastSources as $sourceFile) {
            $source = $sourceFile->getContents();

            foreach ($forbiddenPatterns as $pattern) {
                if (str_contains($source, $pattern)) {
                    $violations[] = $sourceFile->getPathname().": {$pattern}";
                }
            }
        }

        $this->assertSame([], $violations, 'All Blade toast paths must use window.PrismToast.');
    }

    public function test_shared_and_login_styles_use_the_same_toast_motion_contract(): void
    {
        $shared = file_get_contents(public_path('css/shared.css'));
        $login = file_get_contents(public_path('css/login.css'));

        foreach ([$shared, $login] as $stylesheet) {
            $this->assertStringContainsString('opacity 0.4s cubic-bezier(.22, 1, .36, 1)', $stylesheet);
            $this->assertStringContainsString('transform 0.4s cubic-bezier(.22, 1, .36, 1)', $stylesheet);
            $this->assertStringContainsString('.prism-toast.prism-toast-entering', $stylesheet);
            $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $stylesheet);
        }

        $helper = file_get_contents(public_path('js/prism-toast.js'));
        $this->assertStringContainsString('var FADE_DURATION = 400;', $helper);
        $this->assertStringContainsString('var STACK_DURATION = 320;', $helper);
        $this->assertStringContainsString('mountServerRenderedToasts', $helper);
        $this->assertStringContainsString('toastEl.parentElement !== primaryRegion', $helper);
    }
}
