@php
    $user = auth()->user();
    $activeNav = '';
    $coordActiveNav = '';
    $studentProfile = $user?->studentProfile;
    $profileLayout = match ($user?->role) {
        'super_admin' => 'layouts.admin',
        'discipline_coordinator' => 'layouts.coordinator',
        'discipline_officer' => 'layouts.staff',
        'reporting_user' => 'layouts.reporting-user',
        'guidance_officer' => 'layouts.guidance',
        'us_supervisor' => 'layouts.staff',
        'student' => 'layouts.student',
        default => 'layouts.app',
    };
    $profileInitials = strtoupper(substr((string) $user?->first_name, 0, 1).substr((string) $user?->last_name, 0, 1));
    $profilePhotoUrl = $user?->profile_photo && Storage::disk('public')->exists($user->profile_photo)
        ? asset('storage/'.$user->profile_photo)
        : null;
    $profileRole = match ($user?->role) {
        'super_admin' => 'Admin',
        'us_supervisor' => 'US Supervisor',
        default => ucwords(str_replace('_', ' ', (string) $user?->role)),
    };
@endphp

@extends($profileLayout)

@section('title', 'My Profile')

@section('css')
<style>
    .account-profile-wrap { width: 100%; max-width: none; margin: 0; }
    .account-profile-layout {
        width: 100%;
        max-width: 1080px;
        margin: 30px auto 0;
    }
    .account-profile-wrap .prism-btn-primary {
        box-shadow: 0 2px 6px rgba(47, 79, 191, 0.14);
    }
    .account-profile-wrap .prism-btn-primary:hover,
    .account-profile-wrap .prism-btn-primary:focus {
        box-shadow: 0 3px 8px rgba(47, 79, 191, 0.18);
    }
    .account-profile-card {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }
    .account-profile-summary {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px 22px;
        border-bottom: 1px solid var(--border);
        background: #fbfcff;
    }
    .account-profile-avatar {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        flex: 0 0 64px;
        overflow: hidden;
        border-radius: 50%;
        background: #e8efff;
        color: #294ab3;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: .03em;
        cursor: pointer;
        outline: 0;
    }
    .account-profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .account-profile-avatar-edit {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        background: rgba(15, 23, 42, .62);
        color: #fff;
        font-size: 1rem;
        opacity: 0;
        transition: opacity .15s ease;
    }
    .account-profile-avatar:hover .account-profile-avatar-edit,
    .account-profile-avatar:focus-visible .account-profile-avatar-edit { opacity: 1; }
    .account-profile-avatar:focus-visible { box-shadow: 0 0 0 3px rgba(49, 86, 199, .24); }
    .account-profile-summary-copy { min-width: 0; }
    .account-profile-name { margin: 0; color: #0f172a; font-size: 1.18rem; font-weight: 750; }
    .account-profile-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; margin-top: 5px; }
    .account-profile-role { color: #64748b; font-size: .78rem; }
    .account-profile-status {
        display: inline-flex;
        padding: 3px 8px;
        border-radius: 999px;
        background: #dcfce7;
        color: #15803d;
        font-size: .68rem;
        font-weight: 700;
    }
    .account-profile-body { flex: 1 1 auto; padding: 22px; }
    .account-profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 18px; }
    .account-profile-field { min-width: 0; }
    .account-profile-field.full { grid-column: 1 / -1; }
    .account-profile-field .prism-input {
        background: #ffffff;
        border-color: #cbd5e1;
    }
    .account-profile-card.was-validated .prism-input:invalid,
    .account-profile-card.was-validated .prism-select:invalid,
    .account-profile-field .is-invalid {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, .08);
    }
    .account-profile-card.was-validated .prism-input:invalid ~ .invalid-feedback,
    .account-profile-card.was-validated .prism-select:invalid ~ .invalid-feedback,
    .account-profile-field .is-invalid ~ .invalid-feedback {
        display: block;
        margin-top: 5px;
        color: #dc2626;
        font-size: .72rem;
        font-weight: 600;
    }
    .account-profile-readonly {
        min-height: 42px;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #eef1f5;
        color: #64748b;
        font-size: .86rem;
        font-weight: 600;
        word-break: break-word;
        cursor: not-allowed;
    }
    .account-profile-note { margin: 5px 0 0; color: #64748b; font-size: .72rem; }
    .account-profile-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 9px;
        padding: 15px 22px;
        border-top: 1px solid var(--border);
        background: #fbfcff;
    }
    .profile-crop-modal .modal-content {
        overflow: hidden;
        border: 1px solid #dfe5ef;
        border-radius: 14px;
    }
    .profile-crop-modal .modal-header,
    .profile-crop-modal .modal-footer { padding: 16px 18px; }
    .profile-crop-modal .modal-title { color: #0f172a; font-size: 1rem; font-weight: 700; }
    .profile-crop-modal .modal-body { padding: 20px; }
    .profile-crop-stage {
        position: relative;
        width: min(100%, 380px);
        margin-inline: auto;
        overflow: hidden;
        border-radius: 12px;
        background: #e2e8f0;
        cursor: grab;
        touch-action: none;
        user-select: none;
    }
    .profile-crop-stage.is-dragging { cursor: grabbing; }
    .profile-crop-stage::after {
        position: absolute;
        inset: 0;
        border: 2px solid rgba(255, 255, 255, .92);
        border-radius: 50%;
        box-shadow: 0 0 0 999px rgba(15, 23, 42, .58);
        content: '';
        pointer-events: none;
    }
    .profile-crop-canvas {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 1;
        outline: 0;
    }
    .profile-crop-canvas:focus-visible { box-shadow: inset 0 0 0 4px rgba(49, 86, 199, .7); }
    .profile-crop-controls { width: min(100%, 380px); margin: 18px auto 0; }
    .profile-crop-zoom-row { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 12px; }
    .profile-crop-zoom-row i { color: #64748b; }
    .profile-crop-zoom { width: 100%; accent-color: #3156c7; }
    .profile-crop-help { margin: 10px 0 0; color: #64748b; font-size: .74rem; text-align: center; }
    @media (max-width: 767.98px) {
        .account-profile-summary { align-items: flex-start; flex-wrap: wrap; }
        .account-profile-grid { grid-template-columns: 1fr; }
        .account-profile-field.full { grid-column: auto; }
        .account-profile-actions { justify-content: stretch; }
        .account-profile-actions .prism-btn { flex: 1 1 auto; }
    }
</style>
@endsection

@section('content')
<div class="prism-content account-profile-wrap">
    <div class="prism-page-header">
        <h1 class="prism-page-title">My Profile</h1>
        <p class="prism-page-sub">Manage your personal information and profile photo.</p>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-3">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="account-profile-layout">
    <form method="POST" action="{{ route('dashboard.profile.update') }}"
          id="accountProfileForm"
          novalidate
          class="prism-card account-profile-card" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="account-profile-summary">
            <label for="profile_photo"
                   class="account-profile-avatar"
                   id="profilePhotoTrigger"
                   role="button"
                   tabindex="0"
                   aria-label="Change profile photo. JPG, PNG, or WebP. Maximum 2 MB.">
                <img id="profilePhotoPreview"
                     src="{{ $profilePhotoUrl ?? '' }}"
                     alt="{{ $user?->full_name }} profile photo"
                     @if(!$profilePhotoUrl) hidden @endif>
                <span id="profilePhotoInitials" @if($profilePhotoUrl) hidden @endif>{{ $profileInitials }}</span>
                <span class="account-profile-avatar-edit" aria-hidden="true">
                    <i class="bi bi-pencil-fill"></i>
                </span>
            </label>
            <input id="profile_photo" type="file" name="profile_photo"
                   class="visually-hidden"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <div class="account-profile-summary-copy">
                <h2 class="account-profile-name">{{ $user?->full_name }}</h2>
                <div class="account-profile-meta">
                    <span class="account-profile-role">{{ $profileRole }}</span>
                    <span class="account-profile-status">{{ ucfirst((string) $user?->status) }}</span>
                </div>
            </div>
        </div>

        <div class="account-profile-body">
            <div class="account-profile-grid">
                @if($user?->role !== 'student')
                    <div class="account-profile-field">
                        <label class="prism-label" for="first_name">First Name</label>
                        <input id="first_name" name="first_name" class="prism-input" value="{{ old('first_name', $user?->first_name) }}" required>
                    </div>
                    <div class="account-profile-field">
                        <label class="prism-label" for="last_name">Last Name</label>
                        <input id="last_name" name="last_name" class="prism-input" value="{{ old('last_name', $user?->last_name) }}" required>
                    </div>
                @else
                    <div class="account-profile-field full">
                        <label class="prism-label">Official Name</label>
                        <div class="account-profile-readonly">{{ $user?->full_name }}</div>
                        <p class="account-profile-note">Contact the university to correct your official name.</p>
                    </div>
                @endif

                <div class="account-profile-field">
                    <label class="prism-label" for="middle_name">Middle Name <span class="prism-label-opt">(optional)</span></label>
                    <input id="middle_name" name="middle_name" class="prism-input" value="{{ old('middle_name', $user?->middle_name) }}">
                </div>
                @if($user?->role !== 'student')
                    <div class="account-profile-field">
                        <label class="prism-label">University ID</label>
                        <div class="account-profile-readonly prism-ref-number">{{ $user?->institutional_id ?? 'Not available' }}</div>
                    </div>
                @endif
                <div class="account-profile-field">
                    <label class="prism-label">Official Email</label>
                    <div class="account-profile-readonly">{{ $user?->email }}</div>
                    <p class="account-profile-note">Contact the university administrator to correct your official email.</p>
                </div>

                @if(in_array($user?->role, ['discipline_officer', 'guidance_officer'], true))
                    <div class="account-profile-field">
                        <label class="prism-label">Assigned Academic Departments</label>
                        <div class="account-profile-readonly">{{ $user->assignedAcademicUnits()->pluck('name')->map(fn ($name) => \App\Models\AcademicUnit::abbreviatedName($name))->join(', ') ?: 'Not assigned' }}</div>
                        <p class="account-profile-note">Your student and case access is limited to these assignments.</p>
                    </div>
                @endif

                @if($user?->role === 'student')
                    <div class="account-profile-field">
                        <label class="prism-label">Student Number</label>
                        <div class="account-profile-readonly prism-ref-number">{{ $studentProfile?->student_number ?? 'Not available' }}</div>
                    </div>
                    <div class="account-profile-field">
                        <label class="prism-label">Program / Year Level</label>
                        <div class="account-profile-readonly">{{ $studentProfile?->program ?? 'Not available' }} &middot; {{ $studentProfile?->year_level ?? 'Not available' }}</div>
                    </div>
                    <div class="account-profile-field">
                        <label class="prism-label" for="contact_number">Contact Number <span class="text-danger">*</span></label>
                        <input id="contact_number" name="contact_number" class="prism-input @error('contact_number') is-invalid @enderror" value="{{ old('contact_number', $studentProfile?->contact_number) }}" required>
                        @error('contact_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Contact number is required.</div>
                        @enderror
                    </div>
                    <div class="account-profile-field">
                        <label class="prism-label" for="guardian_name">Guardian Name <span class="text-danger">*</span></label>
                        <input id="guardian_name" name="guardian_name" class="prism-input @error('guardian_name') is-invalid @enderror" value="{{ old('guardian_name', $studentProfile?->guardian_name) }}" required>
                        @error('guardian_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Guardian name is required.</div>
                        @enderror
                    </div>
                    <div class="account-profile-field">
                        <label class="prism-label" for="guardian_contact">Guardian Contact <span class="text-danger">*</span></label>
                        <input id="guardian_contact" name="guardian_contact" class="prism-input @error('guardian_contact') is-invalid @enderror" value="{{ old('guardian_contact', $studentProfile?->guardian_contact) }}" required>
                        @error('guardian_contact')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Guardian contact is required.</div>
                        @enderror
                    </div>
                    <div class="account-profile-field">
                        <label class="prism-label" for="guardian_relationship">Relationship <span class="text-danger">*</span></label>
                        <select id="guardian_relationship" name="guardian_relationship" class="prism-select @error('guardian_relationship') is-invalid @enderror" required>
                            <option value="" disabled @selected(! old('guardian_relationship', $studentProfile?->guardian_relationship))>Select relationship</option>
                            @foreach(['Father', 'Mother', 'Guardian', 'Sibling', 'Other'] as $relationship)
                                <option value="{{ $relationship }}" @selected(old('guardian_relationship', $studentProfile?->guardian_relationship) === $relationship)>{{ $relationship }}</option>
                            @endforeach
                        </select>
                        @error('guardian_relationship')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Guardian relationship is required.</div>
                        @enderror
                    </div>
                @endif
            </div>
        </div>

        <div class="account-profile-actions">
            <button type="submit" class="prism-btn prism-btn-primary">Save Changes</button>
        </div>
    </form>

    </div>
</div>

<div class="modal fade prism-modal profile-crop-modal" id="profileCropModal" tabindex="-1"
     aria-labelledby="profileCropModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="profileCropModalTitle">Crop Profile Photo</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel photo cropping"></button>
            </div>
            <div class="modal-body">
                <div class="profile-crop-stage" id="profileCropStage">
                    <canvas id="profileCropCanvas" class="profile-crop-canvas" width="512" height="512"
                            tabindex="0"
                            aria-label="Profile photo crop area. Drag the photo or use the arrow keys to reposition it."></canvas>
                </div>
                <div class="profile-crop-controls">
                    <div class="profile-crop-zoom-row">
                        <i class="bi bi-image" aria-hidden="true"></i>
                        <input id="profileCropZoom" class="profile-crop-zoom" type="range"
                               min="1" max="3" step="0.01" value="1" aria-label="Photo zoom">
                        <i class="bi bi-image-fill" aria-hidden="true"></i>
                    </div>
                    <p class="profile-crop-help">Drag to reposition the photo. The circular guide shows what will appear in your avatar.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="prism-btn prism-btn-outline" id="profileCropReset">Reset</button>
                <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="prism-btn prism-btn-primary" id="profileCropApply">Apply Crop</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const input = document.getElementById('profile_photo');
    const form = document.getElementById('accountProfileForm');
    const trigger = document.getElementById('profilePhotoTrigger');
    const preview = document.getElementById('profilePhotoPreview');
    const initials = document.getElementById('profilePhotoInitials');
    const modalElement = document.getElementById('profileCropModal');
    const stage = document.getElementById('profileCropStage');
    const canvas = document.getElementById('profileCropCanvas');
    const zoomInput = document.getElementById('profileCropZoom');
    const resetButton = document.getElementById('profileCropReset');
    const applyButton = document.getElementById('profileCropApply');
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maximumBytes = 2 * 1024 * 1024;
    const outputSize = 512;
    let previewUrl = null;
    let sourceUrl = null;
    let sourceImage = null;
    let offsetX = 0;
    let offsetY = 0;
    let zoom = 1;
    let dragging = false;
    let pointerX = 0;
    let pointerY = 0;
    let cropApplied = false;

    form?.addEventListener('submit', (event) => {
        const invalidFields = [...form.querySelectorAll('input, select, textarea')]
            .filter((field) => !field.disabled && !field.checkValidity());
        const invalidField = invalidFields[0];
        if (!invalidField) return;

        event.preventDefault();
        form.classList.add('was-validated');
        invalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        invalidField.focus({ preventScroll: true });
    });

    if (!input || !trigger || !preview || !initials || !modalElement || !stage || !canvas
        || !zoomInput || !resetButton || !applyButton || !window.bootstrap?.Modal) return;

    const context = canvas.getContext('2d');
    const cropModal = bootstrap.Modal.getOrCreateInstance(modalElement);

    const revokeSourceUrl = () => {
        if (!sourceUrl) return;
        URL.revokeObjectURL(sourceUrl);
        sourceUrl = null;
    };

    const imageScale = () => Math.max(outputSize / sourceImage.naturalWidth, outputSize / sourceImage.naturalHeight) * zoom;

    const clampOffsets = () => {
        if (!sourceImage) return;
        const scale = imageScale();
        const maximumX = Math.max(0, (sourceImage.naturalWidth * scale - outputSize) / 2);
        const maximumY = Math.max(0, (sourceImage.naturalHeight * scale - outputSize) / 2);
        offsetX = Math.max(-maximumX, Math.min(maximumX, offsetX));
        offsetY = Math.max(-maximumY, Math.min(maximumY, offsetY));
    };

    const drawCrop = () => {
        if (!sourceImage) return;
        clampOffsets();
        const scale = imageScale();
        const width = sourceImage.naturalWidth * scale;
        const height = sourceImage.naturalHeight * scale;

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, outputSize, outputSize);
        context.drawImage(
            sourceImage,
            (outputSize - width) / 2 + offsetX,
            (outputSize - height) / 2 + offsetY,
            width,
            height,
        );
    };

    const resetCrop = () => {
        offsetX = 0;
        offsetY = 0;
        zoom = 1;
        zoomInput.value = '1';
        drawCrop();
    };

    const rejectFile = (message) => {
        input.value = '';
        input.setCustomValidity(message);
        input.reportValidity();
        revokeSourceUrl();
        sourceImage = null;
    };

    trigger.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        input.click();
    });

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        input.setCustomValidity('');

        if (!file) return;

        if (!allowedTypes.includes(file.type)) {
            rejectFile('Choose a JPG, PNG, or WebP image.');
            return;
        }

        if (file.size > maximumBytes) {
            rejectFile('The selected photo exceeds the 2 MB limit.');
            return;
        }

        revokeSourceUrl();
        sourceUrl = URL.createObjectURL(file);
        sourceImage = new Image();
        sourceImage.onload = () => {
            cropApplied = false;
            resetCrop();
            cropModal.show();
        };
        sourceImage.onerror = () => rejectFile('The selected photo could not be opened.');
        sourceImage.src = sourceUrl;
    });

    zoomInput.addEventListener('input', () => {
        zoom = Number(zoomInput.value);
        drawCrop();
    });

    resetButton.addEventListener('click', resetCrop);

    stage.addEventListener('pointerdown', (event) => {
        if (!sourceImage) return;
        dragging = true;
        pointerX = event.clientX;
        pointerY = event.clientY;
        stage.classList.add('is-dragging');
        stage.setPointerCapture(event.pointerId);
    });

    stage.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        const ratio = outputSize / canvas.getBoundingClientRect().width;
        offsetX += (event.clientX - pointerX) * ratio;
        offsetY += (event.clientY - pointerY) * ratio;
        pointerX = event.clientX;
        pointerY = event.clientY;
        drawCrop();
    });

    const stopDragging = (event) => {
        if (!dragging) return;
        dragging = false;
        stage.classList.remove('is-dragging');
        if (stage.hasPointerCapture(event.pointerId)) stage.releasePointerCapture(event.pointerId);
    };
    stage.addEventListener('pointerup', stopDragging);
    stage.addEventListener('pointercancel', stopDragging);

    canvas.addEventListener('keydown', (event) => {
        const movement = event.shiftKey ? 10 : 3;
        if (event.key === 'ArrowLeft') offsetX -= movement;
        else if (event.key === 'ArrowRight') offsetX += movement;
        else if (event.key === 'ArrowUp') offsetY -= movement;
        else if (event.key === 'ArrowDown') offsetY += movement;
        else if (event.key === '+' || event.key === '=') zoom = Math.min(3, zoom + .05);
        else if (event.key === '-') zoom = Math.max(1, zoom - .05);
        else return;

        event.preventDefault();
        zoomInput.value = String(zoom);
        drawCrop();
    });

    applyButton.addEventListener('click', () => {
        if (!sourceImage) return;
        applyButton.disabled = true;
        drawCrop();

        canvas.toBlob((blob) => {
            applyButton.disabled = false;
            if (!blob) {
                rejectFile('The cropped photo could not be prepared.');
                cropModal.hide();
                return;
            }

            const croppedFile = new File([blob], 'profile-photo.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now(),
            });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            input.files = transfer.files;

            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            preview.hidden = false;
            initials.hidden = true;
            cropApplied = true;
            cropModal.hide();
        }, 'image/jpeg', .9);
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (!cropApplied) input.value = '';
        dragging = false;
        stage.classList.remove('is-dragging');
        revokeSourceUrl();
        sourceImage = null;
    });

    preview.addEventListener('error', () => {
        preview.hidden = true;
        initials.hidden = false;
    });

    window.addEventListener('beforeunload', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        revokeSourceUrl();
    });
})();
</script>
@endsection
