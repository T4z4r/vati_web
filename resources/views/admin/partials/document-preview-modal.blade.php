<div class="modal fade document-preview-modal" id="{{ $id ?? 'documentPreviewModal' }}" tabindex="-1" aria-labelledby="{{ ($id ?? 'documentPreviewModal') }}Title" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="muted" style="margin:0">{{ __('Document preview') }}</p>
                    <h2 class="modal-title" id="{{ ($id ?? 'documentPreviewModal') }}Title">{{ __('Uploaded document') }}</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <iframe class="document-preview-frame" title="{{ __('Document preview') }}"></iframe>
            </div>
            <div class="modal-footer">
                <a class="btn btn-secondary document-preview-download" href="#">{{ __('Download') }}</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
