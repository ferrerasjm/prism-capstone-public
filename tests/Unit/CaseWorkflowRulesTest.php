<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CaseResolution;
use App\Models\Sanction;
use App\Models\UpccReferral;
use App\Models\UsAssignment;
use App\Services\MajorOffenseWorkflow;
use App\Services\RiskScoringService;
use App\Services\SanctionRecommendationService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaseWorkflowRulesTest extends TestCase
{
    public function test_nte_deadline_is_five_calendar_days(): void
    {
        $deadline = MajorOffenseWorkflow::nteResponseDeadline(Carbon::parse('2026-07-03 15:30'));

        $this->assertSame('2026-07-08', $deadline->toDateString());
    }

    public function test_nte_days_remaining_are_whole_numbers(): void
    {
        $days = MajorOffenseWorkflow::wholeDaysRemaining(
            Carbon::parse('2026-07-08 00:00'),
            Carbon::parse('2026-07-03 17:30')
        );

        $this->assertSame(5, $days);
    }

    public function test_major_offense_policy_deadlines_use_the_defined_turnaround_times(): void
    {
        $receivedAt = Carbon::parse('2026-07-03 09:00');
        $conferenceWindow = MajorOffenseWorkflow::conferenceWindow($receivedAt);

        $this->assertSame('2026-07-05', MajorOffenseWorkflow::explanationEvaluationDeadline($receivedAt)->toDateString());
        $this->assertSame('2026-07-05', MajorOffenseWorkflow::upccConveningDeadline($receivedAt)->toDateString());
        $this->assertSame('2026-07-08', $conferenceWindow['earliest']->toDateString());
        $this->assertSame('2026-07-10', $conferenceWindow['latest']->toDateString());
        $this->assertSame('2026-07-13 09:00:00', MajorOffenseWorkflow::proceedingsDeadline($receivedAt)->toDateTimeString());
        $this->assertSame('2026-07-06 09:00:00', MajorOffenseWorkflow::decisionDeadline($receivedAt)->toDateTimeString());
        $this->assertSame('2026-07-06 09:00:00', MajorOffenseWorkflow::finalReportDeadline($receivedAt)->toDateTimeString());
        $this->assertSame('2026-07-06 09:00:00', MajorOffenseWorkflow::councilActionDeadline($receivedAt)->toDateTimeString());
        // Student Handbook VI.G.4 counts this one in working days, so the Friday
        // start lands on the following Friday rather than the Wednesday.
        $this->assertSame('2026-07-10', MajorOffenseWorkflow::appealDeadline($receivedAt)->toDateString());
        $this->assertSame('2026-07-03 13:00:00', MajorOffenseWorkflow::recordsFilingDeadline($receivedAt)->toDateTimeString());
    }

    #[DataProvider('pathRecommendationProvider')]
    public function test_path_recommendation_rules(
        string $admissionType,
        int $sanctionCategory,
        string $expectedPath
    ): void {
        $recommendation = (new SanctionRecommendationService)
            ->recommendPath($admissionType, $sanctionCategory);

        $this->assertSame($expectedPath, $recommendation['path']);
        $this->assertNotSame('', $recommendation['reason']);
    }

    public static function pathRecommendationProvider(): array
    {
        return [
            'full admission category 1' => ['full_admission', 1, 'direct_resolution'],
            'full admission category 2' => ['full_admission', 2, 'direct_resolution'],
            'full admission category 3' => ['full_admission', 3, 'upcc_referral'],
            'partial admission' => ['partial_admission', 1, 'upcc_referral'],
            'full denial' => ['full_denial', 2, 'upcc_referral'],
        ];
    }

    #[DataProvider('riskBandProvider')]
    public function test_risk_bands_match_the_system_logic(float $score, string $expectedBand): void
    {
        $method = new \ReflectionMethod(RiskScoringService::class, 'resolveBand');

        $this->assertSame($expectedBand, $method->invoke(new RiskScoringService, $score));
    }

    public static function riskBandProvider(): array
    {
        return [
            'low ceiling' => [25, 'low'],
            'moderate floor' => [26, 'moderate'],
            'moderate ceiling' => [50, 'moderate'],
            'high floor' => [51, 'high'],
            'high ceiling' => [75, 'high'],
            'critical floor' => [76, 'critical'],
        ];
    }

    public function test_appeal_windows_remain_open_through_the_deadline_date(): void
    {
        Carbon::setTestNow('2026-07-08 15:00:00');

        $upcc = new UpccReferral([
            'appeal_window_end' => '2026-07-08',
            'appeal_filed' => false,
        ]);
        $resolution = new CaseResolution([
            'appeal_deadline' => '2026-07-08',
            'appeal_filed' => false,
        ]);

        $this->assertTrue($upcc->isAppealWindowOpen());
        $this->assertTrue($resolution->isAppealWindowOpen());

        Carbon::setTestNow();
    }

    public function test_sanctions_become_overdue_after_the_due_date_ends(): void
    {
        $sanction = new Sanction(['status' => 'pending', 'due_date' => '2026-07-08']);
        $assignment = new UsAssignment([
            'status' => 'in_progress',
            'target_completion_date' => '2026-07-08',
        ]);

        Carbon::setTestNow('2026-07-08 15:00:00');
        $this->assertFalse($sanction->isOverdue());
        $this->assertFalse($assignment->isOverdue());

        Carbon::setTestNow('2026-07-09 00:00:00');
        $this->assertTrue($sanction->isOverdue());
        $this->assertTrue($assignment->isOverdue());

        Carbon::setTestNow();
    }
}
