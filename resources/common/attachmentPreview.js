// Larger remote files are redirected, not streamed (ExternalPages::isPreviewRequest), so they cannot be previewed.
const REMOTE_PREVIEW_MAX_BYTES = 10 * 1024 * 1024;

/**
 * 'pdf', 'markdown' or '' for an attachment the preview dialog can show inline.
 */
export function getAttachmentPreviewType(attachment) {
    if (!attachment || !attachment.secureUrl) {
        return '';
    }

    const isRemote = attachment.driver && attachment.driver !== 'local';
    if (isRemote && Number(attachment.file_size) > REMOTE_PREVIEW_MAX_BYTES) {
        return '';
    }

    const fileType = (attachment.file_type || '').toLowerCase();
    const extension = ((attachment.title || '').match(/\.([a-z0-9]+)$/i) || [])[1]?.toLowerCase() || '';

    if (fileType === 'application/pdf') {
        return 'pdf';
    }

    if (['text/markdown', 'text/x-markdown'].includes(fileType)
        || (fileType === 'text/plain' && ['md', 'markdown'].includes(extension))) {
        return 'markdown';
    }

    return '';
}

/**
 * Whether an attachment link click should open the preview: a plain left click on a
 * previewable file. Modified clicks keep the browser's own behavior (new tab, save).
 */
export function shouldPreviewAttachment(event, attachment) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    return !!getAttachmentPreviewType(attachment);
}
