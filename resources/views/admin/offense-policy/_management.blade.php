@php
    $oldCategory = $categoryRows->firstWhere('number', (int) old('number'));
    $categoryFormAction = route('admin.offense-policy.categories.update', $oldCategory ?? $categoryRows->first());
    $oldRecommendationOffense = $majorOffenses->firstWhere('id', (int) old('offense_id')) ?? $majorOffenses->first();
    $recommendationFormAction = route('admin.offense-policy.recommendations.save', $oldRecommendationOffense?->id ?? 0);
@endphp
<div class="policy-management">
    <section class="policy-tab-panel" id="offense-categories" @if($activePolicyTab !== 'categories') hidden @endif>
        <div class="policy-panel-body"><p class="policy-help">Categories 1–5 follow the handbook workflows. Archived categories are unavailable for new recommendations.</p></div>
        <div class="table-responsive"><table class="prism-table policy-table policy-management-table"><thead><tr><th>Category</th><th>Intervention</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @foreach($categoryRows as $category)
                <tr class="{{ $category->is_active ? '' : 'policy-row-archived' }}">
                    <td><span class="policy-title">Category {{ $category->number }}</span></td>
                    <td><div class="policy-title">{{ $category->intervention }}</div>@if($category->description)<div class="policy-code">{{ $category->description }}</div>@endif</td>
                    <td><span class="prism-badge {{ $category->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $category->is_active ? 'Active' : 'Archived' }}</span></td>
                    <td><div class="policy-row-actions">
                        <button type="button" class="sdo-icon-btn sdo-action-view" title="{{ $category->is_active ? 'Edit' : 'Restore' }} Category" aria-label="{{ $category->is_active ? 'Edit' : 'Restore' }} Category {{ $category->number }}" data-bs-toggle="modal" data-bs-target="#categoryModal" data-category-number="{{ $category->number }}" data-category-intervention="{{ $category->intervention }}" data-category-description="{{ $category->description }}" data-category-active="{{ $category->is_active ? '1' : '0' }}" data-category-action="{{ route('admin.offense-policy.categories.update', $category) }}"><i class="bi bi-{{ $category->is_active ? 'pencil' : 'arrow-counterclockwise' }}"></i></button>
                        @if($category->is_active)<button type="button" class="sdo-icon-btn text-danger" title="Archive Category" aria-label="Archive Category {{ $category->number }}" data-bs-toggle="modal" data-bs-target="#categoryArchiveModal" data-category-number="{{ $category->number }}" data-category-action="{{ route('admin.offense-policy.categories.archive', $category) }}"><i class="bi bi-archive"></i></button>@endif
                    </div></td>
                </tr>
            @endforeach
        </tbody></table></div>
    </section>

    <section class="policy-tab-panel" id="sanction-matrix" @if($activePolicyTab !== 'matrix') hidden @endif>
        <div class="policy-panel-body"><p class="policy-help">These rows guide case decisions and the University Service hours shown to officers.</p></div>
        <div class="table-responsive"><table class="prism-table policy-table policy-management-table"><thead><tr><th>Offense</th><th>Occurrence</th><th>Category</th><th>US Hours</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse($matrixRows as $matrix)
                <tr class="{{ $matrix->is_active ? '' : 'policy-row-archived' }}">
                    <td><div class="policy-title">{{ $matrix->offense?->code }}</div><div class="policy-code">{{ $matrix->offense?->title }}</div></td>
                    <td>{{ $matrix->offense_count }}</td>
                    <td>Category {{ $matrix->sanction_category }}<div class="policy-code">{{ $categoryRows->firstWhere('number', $matrix->sanction_category)?->intervention }}</div></td>
                    <td>{{ $matrix->us_hours_min !== null ? number_format($matrix->us_hours_min, 0).'–'.number_format($matrix->us_hours_max, 0) : '—' }}</td>
                    <td><span class="prism-badge {{ $matrix->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $matrix->is_active ? 'Active' : 'Archived' }}</span></td>
                    <td><div class="policy-row-actions">
                        <button type="button" class="sdo-icon-btn sdo-action-view" title="{{ $matrix->is_active ? 'Edit' : 'Restore' }} Recommendation" aria-label="{{ $matrix->is_active ? 'Edit' : 'Restore' }} {{ $matrix->offense?->code }} recommendation" data-bs-toggle="modal" data-bs-target="#recommendationModal" data-recommendation-action="{{ route('admin.offense-policy.recommendations.save', $matrix->offense_catalog_id) }}" data-recommendation-offense="{{ $matrix->offense_catalog_id }}" data-recommendation-count="{{ $matrix->offense_count }}" data-recommendation-category="{{ $matrix->sanction_category }}" data-recommendation-min="{{ $matrix->us_hours_min }}" data-recommendation-max="{{ $matrix->us_hours_max }}" data-recommendation-guidance="{{ $matrix->us_hours_guidance }}" data-recommendation-notes="{{ $matrix->notes }}" data-recommendation-active="{{ $matrix->is_active ? '1' : '0' }}"><i class="bi bi-{{ $matrix->is_active ? 'pencil' : 'arrow-counterclockwise' }}"></i></button>
                        @if($matrix->is_active)<button type="button" class="sdo-icon-btn text-danger" title="Archive Recommendation" aria-label="Archive {{ $matrix->offense?->code }} recommendation" data-bs-toggle="modal" data-bs-target="#recommendationArchiveModal" data-recommendation-name="{{ $matrix->offense?->code }} · Occurrence {{ $matrix->offense_count }}" data-recommendation-action="{{ route('admin.offense-policy.recommendations.archive', [$matrix->offense_catalog_id, $matrix]) }}"><i class="bi bi-archive"></i></button>@endif
                    </div></td>
                </tr>
            @empty<tr><td colspan="6" class="prism-table-empty">No recommendations found.</td></tr>@endforelse
        </tbody></table></div>
        <div class="policy-panel-body"><x-pagination :paginator="$matrixRows" noun="recommendations" /></div>
    </section>
</div>

<div class="modal fade prism-modal policy-modal policy-management-modal" id="thresholdModal" tabindex="-1" aria-labelledby="thresholdModalTitle" aria-hidden="true" data-auto-open="{{ old('_form') === 'threshold' || request()->query('tab') === 'thresholds' ? 'true' : 'false' }}">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('admin.offense-policy.thresholds.update') }}">@csrf @method('PATCH')<input type="hidden" name="_form" value="threshold">
        <div class="modal-header"><h2 class="modal-title" id="thresholdModalTitle">Edit Minor Escalation Threshold</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="policy-grid">
            <div><label class="prism-label" for="sameTypeThreshold">Same Minor Offense Type <span class="text-danger">*</span></label><input class="prism-input" id="sameTypeThreshold" type="number" name="same_type_minor_threshold" min="2" max="20" placeholder="e.g., 3" value="{{ old('same_type_minor_threshold', $settings->same_type_minor_threshold) }}" required></div>
            <div><label class="prism-label" for="differentTypeThreshold">Different Minor Offense Types <span class="text-danger">*</span></label><input class="prism-input" id="differentTypeThreshold" type="number" name="different_type_minor_threshold" min="2" max="20" placeholder="e.g., 4" value="{{ old('different_type_minor_threshold', $settings->different_type_minor_threshold) }}" required></div>
            <div class="wide"><label class="prism-label" for="thresholdReason">Reason for Change <span class="text-danger">*</span></label><textarea class="prism-input" id="thresholdReason" name="reason" minlength="10" maxlength="1000" placeholder="Explain the approved change to these thresholds." required>{{ old('_form') === 'threshold' ? old('reason') : '' }}</textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary">Save Thresholds</button></div>
    </form></div></div>
</div>

<div class="modal fade prism-modal policy-modal policy-management-modal" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true" data-auto-open="{{ old('_form') === 'category' ? 'true' : 'false' }}">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="categoryForm" method="POST" action="{{ $categoryFormAction }}">@csrf @method('PATCH')<input type="hidden" name="_form" value="category">
        <div class="modal-header"><h2 class="modal-title" id="categoryModalTitle">{{ $oldCategory && ! $oldCategory->is_active ? 'Restore Category' : 'Edit Category' }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="policy-grid">
            <div><label class="prism-label" for="categoryNumberDisplay">Category Number</label><input class="prism-input" id="categoryNumberDisplay" value="{{ $oldCategory ? 'Category '.$oldCategory->number : '' }}" readonly><input type="hidden" id="categoryNumber" name="number" value="{{ old('_form') === 'category' ? old('number') : '' }}"></div>
            <div><label class="prism-label" for="categoryIntervention">Intervention <span class="text-danger">*</span></label><input class="prism-input" id="categoryIntervention" name="intervention" maxlength="100" placeholder="e.g., Written warning" value="{{ old('_form') === 'category' ? old('intervention') : '' }}" required></div>
            <div class="wide"><label class="prism-label" for="categoryDescription">Description <span class="policy-optional">(Optional)</span></label><textarea class="prism-input" id="categoryDescription" name="description" maxlength="2000" placeholder="Describe when this intervention applies.">{{ old('_form') === 'category' ? old('description') : '' }}</textarea></div>
            <div class="wide"><label class="prism-label" for="categoryReason">Reason and Policy Source <span class="text-danger">*</span></label><textarea class="prism-input" id="categoryReason" name="reason" minlength="10" maxlength="1000" placeholder="Cite the handbook section and reason for this change." required>{{ old('_form') === 'category' ? old('reason') : '' }}</textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary" id="categorySubmit">{{ $oldCategory && ! $oldCategory->is_active ? 'Restore Category' : 'Save Changes' }}</button></div>
    </form></div></div>
</div>

<div class="modal fade prism-modal policy-modal policy-management-modal" id="categoryArchiveModal" tabindex="-1" aria-labelledby="categoryArchiveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="categoryArchiveForm" method="POST">@csrf @method('PATCH')
        <div class="modal-header"><h2 class="modal-title" id="categoryArchiveTitle">Archive Category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p id="categoryArchiveMessage"></p><label class="prism-label" for="categoryArchiveReason">Reason and Policy Source <span class="text-danger">*</span></label><textarea class="prism-input" id="categoryArchiveReason" name="reason" minlength="10" maxlength="1000" placeholder="Cite the policy change that retires this category." required></textarea></div>
        <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-danger">Archive Category</button></div>
    </form></div></div>
</div>

<div class="modal fade prism-modal policy-modal policy-management-modal policy-management-modal--wide" id="recommendationModal" tabindex="-1" aria-labelledby="recommendationModalTitle" aria-hidden="true" data-auto-open="{{ old('_form') === 'recommendation' ? 'true' : 'false' }}">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form id="recommendationForm" method="POST" action="{{ $recommendationFormAction }}" data-store-action="{{ route('admin.offense-policy.recommendations.save', $majorOffenses->first()?->id ?? 0) }}">@csrf<input type="hidden" name="_form" value="recommendation"><input type="hidden" name="_is_new" id="recommendationIsNew" value="{{ old('_form') === 'recommendation' ? old('_is_new', '1') : '1' }}">
        <div class="modal-header"><h2 class="modal-title" id="recommendationModalTitle">{{ old('_form') === 'recommendation' && old('_is_new') === '0' ? 'Edit Recommendation' : 'Add Recommendation' }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="policy-grid">
            <div class="wide"><label class="prism-label" for="recommendationOffense">Major Offense <span class="text-danger">*</span></label><select class="prism-select" id="recommendationOffense" name="offense_id" required><option value="">Select Offense</option>@foreach($majorOffenses as $offense)<option value="{{ $offense->id }}" data-action="{{ route('admin.offense-policy.recommendations.save', $offense) }}" @selected(old('_form') === 'recommendation' && (int) old('offense_id') === $offense->id)>{{ $offense->code }} — {{ $offense->title }}</option>@endforeach</select></div>
            <div><label class="prism-label" for="recommendationCount">Offense Occurrence <span class="text-danger">*</span></label><input class="prism-input" id="recommendationCount" name="offense_count" type="number" min="1" max="10" placeholder="e.g., 1" value="{{ old('_form') === 'recommendation' ? old('offense_count', 1) : 1 }}" @readonly(old('_form') === 'recommendation' && old('_is_new') === '0') required></div>
            <div><label class="prism-label" for="recommendationCategory">Offense Category <span class="text-danger">*</span></label><select class="prism-select" id="recommendationCategory" name="sanction_category" required><option value="">Select Category</option>@foreach($categories as $number => $intervention)<option value="{{ $number }}" @selected(old('_form') === 'recommendation' && (int) old('sanction_category') === $number)>Category {{ $number }} — {{ $intervention }}</option>@endforeach</select></div>
            <div><label class="prism-label" for="recommendationMin">Minimum US Hours <span class="policy-optional">(Optional)</span></label><input class="prism-input" id="recommendationMin" name="us_hours_min" type="number" min="0" max="9999.99" step="0.01" placeholder="e.g., 10" value="{{ old('_form') === 'recommendation' ? old('us_hours_min') : '' }}"></div>
            <div><label class="prism-label" for="recommendationMax">Maximum US Hours <span class="policy-optional">(Optional)</span></label><input class="prism-input" id="recommendationMax" name="us_hours_max" type="number" min="0" max="9999.99" step="0.01" placeholder="e.g., 20" value="{{ old('_form') === 'recommendation' ? old('us_hours_max') : '' }}"></div>
            <p class="policy-help wide">Enter both hour values, or leave both blank. Use the same value for a single recommendation.</p>
            <div class="wide"><label class="prism-label" for="recommendationGuidance">US Hours Guidance <span class="policy-optional">(Optional)</span></label><textarea class="prism-input" id="recommendationGuidance" name="us_hours_guidance" maxlength="2000" placeholder="Explain how to choose hours within this range.">{{ old('_form') === 'recommendation' ? old('us_hours_guidance') : '' }}</textarea></div>
            <div class="wide"><label class="prism-label" for="recommendationNotes">Notes <span class="policy-optional">(Optional)</span></label><input class="prism-input" id="recommendationNotes" name="notes" maxlength="255" placeholder="Add any supporting notes." value="{{ old('_form') === 'recommendation' ? old('notes') : '' }}"></div>
            <div class="wide"><label class="prism-label" for="recommendationReason">Reason and Policy Source <span class="text-danger">*</span></label><textarea class="prism-input" id="recommendationReason" name="reason" minlength="10" maxlength="1000" placeholder="Cite the handbook section supporting this recommendation." required>{{ old('_form') === 'recommendation' ? old('reason') : '' }}</textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary" id="recommendationSubmit">{{ old('_form') === 'recommendation' && old('_is_new') === '0' ? 'Save Changes' : 'Save Recommendation' }}</button></div>
    </form></div></div>
</div>

<div class="modal fade prism-modal policy-modal policy-management-modal" id="recommendationArchiveModal" tabindex="-1" aria-labelledby="recommendationArchiveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="recommendationArchiveForm" method="POST">@csrf @method('PATCH')
        <div class="modal-header"><h2 class="modal-title" id="recommendationArchiveTitle">Archive Recommendation</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p id="recommendationArchiveMessage"></p><label class="prism-label" for="recommendationArchiveReason">Reason and Policy Source <span class="text-danger">*</span></label><textarea class="prism-input" id="recommendationArchiveReason" name="reason" minlength="10" maxlength="1000" placeholder="Cite the policy change that retires this recommendation." required></textarea></div>
        <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-danger">Archive Recommendation</button></div>
    </form></div></div>
</div>
