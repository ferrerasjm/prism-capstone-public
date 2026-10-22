<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Renders the three disciplinary notices as real PDFs.
 *
 * The notices used to be HTML pages the reader had to print themselves, which meant
 * the "document" differed by browser and no file existed to attach or archive. One
 * renderer here keeps the officer's preview, the student's copy and any future
 * attachment byte-for-byte identical.
 */
class NoticeDocumentService
{
    private const DOCUMENTS = [
        'notice_to_explain' => [
            'view' => 'documents.notices.notice-to-explain',
            'slug' => 'notice-to-explain',
        ],
        'notice_of_hearing' => [
            'view' => 'documents.notices.notice-of-hearing',
            'slug' => 'notice-of-hearing',
        ],
        'notice_of_formative_intervention' => [
            'view' => 'documents.notices.nfi',
            'slug' => 'notice-of-formative-intervention',
        ],
    ];

    public function render(
        CaseNotice $notice,
        DisciplinaryCase $case,
        bool $isPreview = false,
        ?UpccReferral $referral = null,
    ): string {
        $document = self::DOCUMENTS[$notice->notice_type]
            ?? throw new InvalidArgumentException("No PDF document for notice type [{$notice->notice_type}].");

        return Pdf::loadView($document['view'], [
            'notice' => $notice,
            'case' => $case,
            'referral' => $referral,
            'isPreview' => $isPreview,
            // "Noted by" on the printed forms is the Student Discipline Coordinator, so
            // the document names whoever currently holds that role rather than a
            // hardcoded signatory that goes stale when the office changes hands.
            'notedBy' => User::where('role', 'discipline_coordinator')
                ->where('status', 'active')
                ->orderBy('id')
                ->first(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            // The layout embeds Poppins and the PRISM logo from public/, so dompdf is
            // allowed to read there and nowhere else.
            ->setOption('chroot', public_path())
            ->output();
    }

    /**
     * Streamed inline so it opens in the browser's PDF viewer - and so it can be
     * embedded in an iframe for the on-page preview - rather than downloading.
     */
    public function stream(
        CaseNotice $notice,
        DisciplinaryCase $case,
        bool $isPreview = false,
        ?UpccReferral $referral = null,
    ): Response {
        return response($this->render($notice, $case, $isPreview, $referral), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($notice, $case, $isPreview).'"',
            // A preview reflects unsaved state, so it must never be served from cache.
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function filename(CaseNotice $notice, DisciplinaryCase $case, bool $isPreview = false): string
    {
        $slug = self::DOCUMENTS[$notice->notice_type]['slug'] ?? 'notice';

        return ($isPreview ? 'preview-' : '').$slug.'-'.$case->case_number.'.pdf';
    }
}
