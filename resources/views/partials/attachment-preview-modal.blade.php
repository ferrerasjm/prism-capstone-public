<div class="modal fade prism-modal" id="attachmentPreviewModal" tabindex="-1" style="display:none;"
     aria-labelledby="attachmentPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="attachmentPreviewTitle" style="font-size:1rem;">Attachment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attachmentPreviewBody"
                 style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:0;background:#f8fafc;">
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modalElement = document.getElementById('attachmentPreviewModal');
    if (!modalElement || modalElement.dataset.wired) return;
    modalElement.dataset.wired = 'true';

    const titleEl = document.getElementById('attachmentPreviewTitle');
    const bodyEl = document.getElementById('attachmentPreviewBody');
    const imageTypes = ['jpg', 'jpeg', 'png'];

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-attachment-preview]');
        if (!trigger) return;
        event.preventDefault();

        const url = trigger.dataset.attachmentUrl;
        const name = trigger.dataset.attachmentName || 'Attachment';
        const type = (trigger.dataset.attachmentType || '').toLowerCase();

        titleEl.textContent = name;
        bodyEl.innerHTML = '';

        // Property assignment (not innerHTML) so an untrusted file name/url from an
        // uploaded attachment is never parsed as markup.
        let media;
        if (imageTypes.includes(type)) {
            media = document.createElement('img');
            media.alt = name;
            media.style.cssText = 'max-width:100%;max-height:75vh;object-fit:contain;';
        } else {
            // Everything else accepted by uploads is a PDF; the browser's built-in
            // viewer inside the iframe already offers zoom/print/download controls.
            // No aria-label here — the modal itself is already labelled via
            // aria-labelledby="attachmentPreviewTitle" above, and an aria-label on
            // the iframe surfaces as a native hover tooltip that just repeats that
            // same name right over the PDF viewer's own toolbar.
            media = document.createElement('iframe');
            media.style.cssText = 'width:100%;height:75vh;border:0;';
        }
        media.src = url;
        bodyEl.appendChild(media);

        window.bootstrap?.Modal.getOrCreateInstance(modalElement).show();
    });

    // Stop the file from continuing to load/play in the background once dismissed.
    modalElement.addEventListener('hidden.bs.modal', () => {
        bodyEl.innerHTML = '';
    });
})();
</script>
