<div class="mb-3">
    <label for="deactivationCategory" class="sdo-form-label">Deactivation Category <span class="text-danger">*</span></label>
    <select id="deactivationCategory" name="deactivation_category" class="sdo-form-control" required>
        @foreach(\App\Models\User::DEACTIVATION_CATEGORIES as $value => $label)
            <option value="{{ $value }}" @selected($value === 'other')>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label for="deactivationDate" class="sdo-form-label">Effective Date <span class="text-muted">(Optional)</span></label>
    <input id="deactivationDate" name="deactivation_effective_on" type="date" class="sdo-form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
    <small class="text-muted">Defaults to today if blank. Access is disabled immediately on submission.</small>
</div>
<div id="deactivationReasonWrap">
    <label for="deactivationReason" class="sdo-form-label">Reason <span class="text-danger">*</span></label>
    <textarea id="deactivationReason" name="reason" class="sdo-form-control" rows="3" maxlength="500" required
        placeholder="Explain the other reason for deactivation..."></textarea>
</div>
@push('scripts')
<script>
(() => {
    const modal = document.getElementById('toggleModal');
    const category = document.getElementById('deactivationCategory');
    const date = document.getElementById('deactivationDate');
    const reason = document.getElementById('deactivationReason');
    const wrapper = document.getElementById('deactivationReasonWrap');
    const sync = () => {
        const deactivating = document.getElementById('toggleStatusInput').value === 'inactive';
        const needsReason = deactivating && category.value === 'other';
        category.disabled = !deactivating;
        category.required = deactivating;
        date.disabled = !deactivating;
        wrapper.hidden = !needsReason;
        reason.disabled = !needsReason;
        reason.required = needsReason;
        if (!needsReason) reason.value = '';
    };
    category.addEventListener('change', sync);
    modal.addEventListener('show.bs.modal', sync);
    modal.addEventListener('hidden.bs.modal', () => {
        category.value = 'other';
        date.value = date.defaultValue;
        reason.value = '';
    });
    sync();
})();
</script>
@endpush
