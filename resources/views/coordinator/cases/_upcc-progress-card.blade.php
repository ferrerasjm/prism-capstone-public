@if($case->forwarded_to_coordinator)
    <div class="prism-card mb-4">
        <div class="prism-card-header">
            <h2 class="prism-card-title">UPCC Progress</h2>
            <span class="prism-badge prism-badge-blue">{{ $headerTitle }}</span>
        </div>
        <div class="prism-card-body">
            <div class="upcc-step-track">
                @foreach($stageSteps as $i => $step)
                    <div class="upcc-step {{ $progress->isStepDone($i) ? 'is-done' : ($progress->isStepActive($i) ? 'is-active' : '') }}">
                        <span class="upcc-step-kicker">Step {{ $i + 1 }}</span>
                        <div class="upcc-step-title">
                            @if($progress->isStepDone($i))
                                <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
                            @endif
                            {{ $progress->stepTitle($i) }}
                        </div>
                        <div class="upcc-step-note">{{ $step['note'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
