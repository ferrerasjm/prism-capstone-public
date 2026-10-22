<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Models\DisciplinaryCase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The notice PDF's <title> is what a browser shows as the tab name, offers as the
 * saved-file name, and — in Chrome's built-in PDF viewer — repeats as a floating
 * document-title label above the toolbar. It used to lead with the full control
 * number ("CRK – ACD – SDO – F – 002 — NOTICE TO EXPLAIN — DC-…"), which was
 * unwieldy, then "F-002: Notice to Explain", which Chrome's viewer showed twice
 * over (once as that floating label, once truncated in the toolbar) right next
 * to the same heading already printed in the document's own banner. It now reads
 * just "F-002" — still a unique, meaningful identifier — while the full code and
 * the descriptive title still print in the form banner.
 */
class NoticeDocumentTitleTest extends TestCase
{
    private function render(string $formNumber, string $documentTitle): string
    {
        return View::make('documents.notices._layout', [
            'formNumber' => $formNumber,
            'documentTitle' => $documentTitle,
            'case' => new DisciplinaryCase(['case_number' => 'DC-2026-0001']),
            'isPreview' => false,
        ])->render();
    }

    public function test_the_pdf_title_uses_the_short_form_code(): void
    {
        $html = $this->render('CRK – ACD – SDO – F – 002', 'Notice to Explain');

        $this->assertStringContainsString('<title>F-002</title>', $html);
        $this->assertStringNotContainsString('<title>CRK – ACD – SDO – F – 002', $html);
        $this->assertStringNotContainsString('DC-2026-0001</title>', $html);
    }

    public function test_the_full_control_number_still_prints_in_the_form_banner(): void
    {
        $html = $this->render('CRK – ACD – SDO – F – 003', 'Notice of Formative Intervention');

        $this->assertStringContainsString('CRK – ACD – SDO – F – 003', $html);
        $this->assertStringContainsString('<title>F-003</title>', $html);
    }

    public function test_hearing_notice_short_code(): void
    {
        $html = $this->render('CRK – ACD – SDO – F – 016', 'Notice of Hearing');

        $this->assertStringContainsString('<title>F-016</title>', $html);
    }
}
