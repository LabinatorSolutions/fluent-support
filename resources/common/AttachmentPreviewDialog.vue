<template>
    <el-dialog
        v-model="dialogVisible"
        :show-close="false"
        width="80%"
        :fullscreen="isFullscreen"
        :z-index="isFullscreen ? 100000 : undefined"
        append-to-body
        align-center
        class="fs_dialog fs_attachment_preview_dialog"
        destroy-on-close
        @closed="resetPreview"
    >
        <template #header="{ titleId, titleClass }">
            <div class="fs_attachment_preview_header">
                <span :id="titleId" :class="titleClass">{{ attachment.title }}</span>
                <el-button
                    text
                    class="fs_attachment_preview_icon_btn"
                    :aria-label="isFullscreen ? $t('Exit fullscreen') : $t('Enter fullscreen')"
                    :title="isFullscreen ? $t('Exit fullscreen') : $t('Enter fullscreen')"
                    :aria-pressed="isFullscreen"
                    @click="isFullscreen = !isFullscreen"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path v-if="isFullscreen" d="M4 9h5V4m6 0v5h5M4 15h5v5m6 0v-5h5"/>
                        <path v-else d="M9 4H4v5m11-5h5v5M4 15v5h5m6 0h5v-5"/>
                    </svg>
                </el-button>
                <el-button
                    text
                    class="fs_attachment_preview_icon_btn"
                    :aria-label="$t('Close')"
                    :title="$t('Close')"
                    @click="dialogVisible = false"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m6 6 12 12M6 18 18 6"/>
                    </svg>
                </el-button>
            </div>
        </template>

        <div class="fs_attachment_preview_body">
            <iframe
                v-if="previewType === 'pdf'"
                :src="previewUrl + '#navpanes=0&view=FitH'"
                :title="attachment.title"
                class="fs_attachment_preview_pdf"
            ></iframe>
            <div v-else class="fs_attachment_preview_markdown">
                <el-skeleton v-if="loading" :rows="8" animated class="fs_attachment_preview_markdown_body"/>
                <p v-else-if="errorMessage" class="fs_attachment_preview_error">{{ errorMessage }}</p>
                <div v-else class="fs_attachment_preview_markdown_body wp-exclude-emoji" v-html="markdownHtml"></div>
            </div>
        </div>

        <template #footer>
            <span class="fs_dialog_footer fs_attachment_preview_footer">
                <el-button class="fs_outline_btn fs_attachment_preview_btn" @click="openInNewTab">
                    {{ $t('Open in new tab') }}
                </el-button>
                <el-button class="fs_filled_btn fs_attachment_preview_btn fs_attachment_preview_btn_primary" @click="download">
                    {{ $t('Download') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script>
import { getAttachmentPreviewType } from './attachmentPreview';
// Static: a dynamic import would split the portal's single-file bundle.
import { parseMarkdownFile } from './markdown';

// Parsing runs on the main thread: ~3 s for 10 MB. Bigger files are download-only.
const MARKDOWN_MAX_CHARS = 2 * 1024 * 1024;

export default {
    name: 'AttachmentPreviewDialog',
    props: {
        modelValue: { type: Boolean, default: false },
        attachment: { type: Object, default: () => ({}) },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            loading: false,
            isFullscreen: false,
            markdownHtml: '',
            errorMessage: '',
            requestId: 0,
        };
    },
    computed: {
        dialogVisible: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            },
        },
        previewType() {
            return getAttachmentPreviewType(this.attachment);
        },
        // fs_preview asks the server to stream remote-storage files instead of redirecting off-site.
        previewUrl() {
            const url = this.attachment.secureUrl || '';
            return url + (url.includes('?') ? '&' : '?') + 'fs_preview=1';
        },
    },
    watch: {
        modelValue(visible) {
            if (visible && this.previewType === 'markdown') {
                this.loadMarkdown();
            }
        },
    },
    methods: {
        async loadMarkdown() {
            const requestId = ++this.requestId;
            this.loading = true;
            this.errorMessage = '';
            this.markdownHtml = '';

            try {
                if (Number(this.attachment.file_size) > MARKDOWN_MAX_CHARS) {
                    throw new Error('Preview too large');
                }
                const response = await fetch(this.previewUrl, { credentials: 'same-origin' });
                // A login page or error page can arrive as a 200 after a redirect; it is not the file.
                if (!response.ok || response.redirected || /text\/html/i.test(response.headers.get('content-type') || '')) {
                    throw new Error('Preview request failed');
                }
                const content = await response.text();
                if (requestId !== this.requestId) {
                    return;
                }
                if (content.length > MARKDOWN_MAX_CHARS) {
                    throw new Error('Preview too large');
                }
                this.markdownHtml = parseMarkdownFile(content);
            } catch (e) {
                if (requestId === this.requestId) {
                    this.errorMessage = this.$t('Attachment preview is not available. Please download the file to view it.');
                }
            } finally {
                if (requestId === this.requestId) {
                    this.loading = false;
                }
            }
        },
        openInNewTab() {
            window.open(this.attachment.secureUrl, '_blank', 'noopener,noreferrer');
        },
        download() {
            const link = document.createElement('a');
            link.href = this.attachment.secureUrl;
            link.download = this.attachment.title || '';
            // Remote files redirect off-site, where `download` is ignored: open them in a tab, not over the app.
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        resetPreview() {
            this.requestId++;
            this.isFullscreen = false;
            this.loading = false;
            this.markdownHtml = '';
            this.errorMessage = '';
        },
    },
};
</script>
