<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <div class="fs_row" v-if="ticket.source === 'email' && type !== 'note'">
            <el-form-item :label="$t('Cc')" v-if="selected_cc?.length || show_cc_option">
                <el-select v-model="selected_cc" multiple filterable remote
                           :placeholder="$t('Please enter email')"
                           allow-create
                           class="fs_select_field fs_cc_selector"
                           default-first-option
                           :reserve-keyword="false"
                           @change="handleCcChange"
                           :loading="loading"
                           style="padding-left: 10px"
                >
                    <el-option
                        v-for="item in cc_emails"
                        :key="item"
                        :label="item"
                        :value="item"
                    />
                </el-select>
            </el-form-item>
        </div>
        <wp-editor ref="editor" :autofocus="true" v-if="editor_ready" :class="{ fs_editor_needs_text: highlightEmptyEditor }" v-model="response_body" :height="170" :ticketId="ticket.id" :productID="ticket.product_id" :is_agent="is_agent" :is_direct_paste="true" :editor_shortcodes="shortcodes" :openAIIntegration="openAIIntegration"
                   :show-fluent-booking="type === 'response' && !!appVars.fluent_booking?.active"
                   :show-saved-replies="true" :show-cc-toggle-button="ticket.source === 'email' && type !== 'note'" :add_cc="selected_cc?.length > 0 || show_cc_option" @toggleCcOption="toggleCcOption"/>
        <div class="fs_row fs_attachment_section">
            <div class="fs_full">
                <attachment-form
                    ref="attachmentForm"
                    is_agent="yes"
                    v-if="appVars.has_file_upload"
                    :ticket="ticket"
                    :attachments="attachments"
                    :attachment_info="attachmentInfo"
                    @upload-state-change="updateAttachmentUploadState"
                />
            </div>
        </div>
        <div v-if="editingSchedule" class="fs_schedule_edit_banner">
            <el-icon><EditPen /></el-icon>
            <span class="fs_schedule_edit_banner_text">
                <!-- translators: %s is the date and time the reply will be sent -->
                {{ $t('Editing the reply scheduled for %s.', formatUtcTime(editingSchedule.schedule?.scheduled_at)) }}
                {{ $t("It won't send until you save.") }}
            </span>
            <el-button text @click="discardScheduleEdit">{{ $t('Discard changes') }}</el-button>
        </div>
        <div v-if="editingSchedule && isEditorEmpty" class="fs_schedule_alert fs_schedule_edit_empty" role="status">
            <el-icon><WarningFilled /></el-icon>
            <span>{{ $t('Write your reply in the editor before you save it.') }}</span>
        </div>
        <div class="fs_draft_response_actions fs_row fs_actions_row">
            <div class="fs_half fs_left_section">
                <div v-if="type === 'response' && !editingSchedule" class="fs_informational_reply_container">
                    <el-tooltip effect="dark" :content="$t('informational_reply_info')" placement="bottom" popper-class="fs_tooltip">
                        <el-checkbox
                            v-model="informational_reply"
                            class="fs_informational_reply"
                        >
                            {{ $t('Send as Informational Reply')}}
                        </el-checkbox>
                    </el-tooltip>
                </div>
                <p v-if="type== 'note'" class="fs_note_warning">{{ $t('You are adding internal Note. Only support staff can see this note.') }}</p>
            </div>
            <div class="fs_half fs_right_section">
                <div class="fs_response_actions fs_button_group">
                    <el-button v-if="type != 'note' && type !== 'draft_response' && !editingSchedule" :disabled="creating || attachmentUploadState.uploading" @click="create('yes')" size="large"
                               class="fs_reply_close_btn">
                        {{ $t('Reply and Close Ticket') }}
                    </el-button>
                    <div class="fs_reply_split" :class="{ fs_reply_split_on: scheduleMode }">
                        <el-button class="fs_reply_btn fs_filled_btn" v-loading="creating" :disabled="creating || attachmentUploadState.uploading || (!!editingSchedule && isEditorEmpty)"
                                   @click="editingSchedule ? saveScheduleEdit() : create('no')">
                            <IconPack icon-key="reply" :width="16" :height="16" fill="currentColor" class="fs_reply_icon" />
                            <span v-if="editingSchedule">{{ $t('Save changes') }}</span>
                            <span v-else-if="type === 'draft_response'">{{ $t('Add Draft Reply') }}</span>
                            <span v-else-if="type === 'note'">{{ $t('Add Internal Note') }}</span>
                            <span v-else>{{ $t('Reply') }}</span>
                        </el-button>
                        <schedule-send-menu
                            v-if="scheduleMode"
                            ref="scheduleMenu"
                            :mode="scheduleMode"
                            :pending="editingSchedule"
                            :blocked="editingSchedule ? null : scheduledReply"
                            :can-edit="canChangeSchedule"
                            :needs-text="scheduleNeedsText"
                            :busy="creating"
                            :disabled="creating || attachmentUploadState.uploading"
                            @submit="onScheduleSubmit"
                            @form-open="scheduleFormOpen = $event"
                            @edit-pending="$emit('editSchedule')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import AttachmentForm from './_AttachmentForm';
import IconPack from '@/admin/Components/IconPack.vue';
import ScheduleSendMenu from './parts/_ScheduleSendMenu.vue';
import { formatUtcTime } from '@/common/dateTime';
import { ElMessageBox } from 'element-plus';

export default {
    name: 'CreateResponse',
    props: {
        ticket: {},
        type: {},
        draft: {},
        openAIIntegration: {},
        is_agent: {},
        scheduledReply: { type: Object, default: null },
        canChangeSchedule: { type: Boolean, default: false },
        editingSchedule: { type: Object, default: null },
        // Newest conversation the agent has loaded. Sent with replies so the server
        // can hold one written against an outdated thread.
        last_conversation_id: {},
        // Optional { hashes, info } owned by the parent, so uploads survive a remount.
        attachment_store: {}
    },
    components: {
        WpEditor,
        AttachmentForm,
        IconPack,
        ScheduleSendMenu
    },
    emits: ['discardDraft', 'draftSaved', 'created', 'newerResponses', 'scheduled', 'scheduleSaved', 'cancelEditSchedule', 'editSchedule'],
    data() {
        return {
            informational_reply: false,
            response_body: '',
            show_cc_option: false,
            selected_cc: [],
            cc_emails: [],
            creating: false,
            close_ticket: 'no',
            attachments: this.attachment_store ? this.attachment_store.hashes : [],
            attachmentInfo: this.attachment_store ? this.attachment_store.info : {},
            editor_ready: true,
            shortcodes: {
                '{{customer.first_name}}': 'Customer First Name',
                '{{customer.last_name}}': 'Customer Last Name',
                '{{customer.full_name}}': 'Customer Full Name',
                '{{customer.email}}': 'Customer Email',
                '{{agent.first_name}}': 'Agent First Name',
                '{{agent.last_name}}': 'Agent Last Name',
                '{{agent.full_name}}': 'Agent Full Name',
                '{{agent.email}}': 'Agent Email',
            },
            draftID:'',
            loading: false,
            saveResponseDraftTimer: null,
            isCreatingResponse: false,
            attachmentUploadState: {
                uploading: false,
                count: 0
            },
            scheduleFormOpen: false,
            stashedComposer: null,
            isMounted: false
        };
    },
    computed: {
        scheduleMode() {
            if (this.type !== 'response' || Array.isArray(this.ticket)) {
                return '';
            }

            if (this.appVars.scheduled_replies) {
                return 'pro';
            }

            return this.appVars.has_pro ? '' : 'upsell';
        },
        isEditorEmpty() {
            const body = String(this.response_body || '');
            return !/<(img|video|audio|iframe|embed|object)\b/i.test(body)
                && body.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() === '';
        },
        scheduleNeedsText() {
            return this.scheduleMode === 'pro' && this.isEditorEmpty;
        },
        highlightEmptyEditor() {
            return (this.scheduleNeedsText && this.scheduleFormOpen)
                || (!!this.editingSchedule && this.isEditorEmpty);
        }
    },
    watch: {
        response_body() {
            if (this.editingSchedule) {
                return;
            }

            if (this.appVars.enable_draft_mode === 'yes' && !Array.isArray(this.ticket)) {
                if (this.response_body === '' && this.draftID) {
                    this.removeDraft();
                }

                // Notes are never saved as a draft.
                if (this.type !== 'note') {
                    this.saveResponseDraft();
                }
            }
        },
        selected_cc() {
            if (this.editingSchedule) {
                return;
            }

            if (this.appVars.enable_draft_mode === 'yes' && !Array.isArray(this.ticket)) {
                if (this.response_body === '' && this.draftID) {
                    this.removeDraft();
                }

                // Notes are never saved as a draft.
                if (this.type !== 'note') {
                    this.saveResponseDraft();
                }
            }
        },
        // The parent learns a new draft's ID after this box loaded it; take it so sending removes the draft.
        'draft.id'(id) {
            if (id && !this.draftID) {
                this.draftID = id;
            }
        },
        editingSchedule: {
            handler(conversation, previous) {
                if (conversation && !previous) {
                    if (this.saveResponseDraftTimer) {
                        clearTimeout(this.saveResponseDraftTimer);
                        this.saveResponseDraftTimer = null;
                        this.flushResponseDraft();
                    }

                    this.stashedComposer = {
                        body: this.response_body,
                        cc: this.selected_cc,
                        attachments: this.attachments
                    };
                    this.response_body = conversation.content;
                    this.selected_cc = conversation.cc_info || [];
                    this.attachments = [];
                    this.$nextTick(() => this.addUploadedFiles(conversation.attachments || []));
                    this.refreshEditor();
                } else if (!conversation && previous && this.stashedComposer) {
                    this.response_body = this.stashedComposer.body;
                    this.selected_cc = this.stashedComposer.cc;
                    this.attachments = this.stashedComposer.attachments || [];
                    this.stashedComposer = null;
                    this.refreshEditor();
                }
            },
            immediate: true
        },
        // Reply and Note share one box: the text, Cc and attachments stay when the agent switches.
        type(newType) {
            if (this.appVars.enable_draft_mode === 'yes' && !Array.isArray(this.ticket) && !this.editingSchedule) {
                if (newType === 'note') {
                    // Keep the reply edits made before the switch; nothing typed as a note is saved.
                    if (this.saveResponseDraftTimer) {
                        clearTimeout(this.saveResponseDraftTimer);
                        this.saveResponseDraftTimer = null;
                        this.flushResponseDraft();
                    }
                } else if (this.response_body !== '') {
                    // Back on Reply, the text in the box is the reply draft again.
                    this.saveResponseDraft();
                }
            }

            this.$nextTick(() => this.focusEditor());
        }
    },
    methods: {
        saveResponseDraft() {
            if (this.saveResponseDraftTimer) {
                clearTimeout(this.saveResponseDraftTimer);
            }

            this.saveResponseDraftTimer = setTimeout(() => {
                this.saveResponseDraftTimer = null;
                this.flushResponseDraft();
            }, 5000);
        },

        flushResponseDraft() {
            const data = {
                content: this.response_body,
                draftID: this.draftID,
                selected_cc: this.selected_cc,
                conversation_type: this.type,
            };

            if (this.response_body !== '' && this.isCreatingResponse === false) {
                let action = `tickets/${this.ticket.id}/draft`;

                const request = this.$post(action, data);
                // Kept off data(): a reactive proxy of the request would never === the request.
                this._pendingDraftSave = request;
                request.always(() => {
                    if (this._pendingDraftSave === request) {
                        this._pendingDraftSave = null;
                    }
                });

                // Sync the parent's copy now and hand it the request: the box may unmount before it returns,
                // and an unmounted component's emits are dropped, so the parent records the new draft ID itself.
                // The ticket ID lets the parent ignore the save beforeUnmount flushes after the agent moved on.
                this.$emit('draftSaved', data, request, this.ticket.id);

                request
                    .then((response) => {
                        this.draftID = response.draftID;
                    })
                    .catch((errors) => {
                        this.$handleError(errors);
                    });
            }
        },

        removeDraft() {
            this.$emit('discardDraft', this.draftID);
            this.draftID = '';
        },

        toggleCcOption(command) {
            if (command === 'show') {
                this.show_cc_option = true;
            } else {
                this.selected_cc = [];
                this.show_cc_option = false;
            }
        },

        updateAttachmentUploadState(state) {
            this.attachmentUploadState = state;
        },

        notifyAttachmentUploadPending() {
            const message = this.attachmentUploadState.count > 1
                ? this.$t('Please wait for all attachments to finish uploading before submitting the reply.')
                : this.$t('Please wait for the attachment to finish uploading before submitting the reply.');

            this.$notify({
                message,
                type: 'warning',
                position: 'bottom-right',
                offset: 50,
            });
        },

        // The mode is fixed at the click: Reply and Notes share this box, so it can change while a send waits.
        create(closed = 'no', forceSend = false, scheduledAt = null, type = this.type) {
            if (this.attachmentUploadState.uploading) {
                this.notifyAttachmentUploadPending();
                return;
            }

            // A first draft save still in flight has no ID yet, so the draft couldn't be removed after sending.
            // Send once it settles; done/always run synchronously, before the request's own .then().
            if (this._pendingDraftSave) {
                this.creating = true;
                this._pendingDraftSave.always((response) => {
                    if (response && response.draftID) {
                        this.draftID = response.draftID;
                    }
                    this._pendingDraftSave = null;
                    this.creating = false;
                    this.create(closed, forceSend, scheduledAt, type);
                });
                return;
            }

            this.isCreatingResponse = true;
            const data = {
                content: this.response_body,
                conversation_type: type,
                close_ticket: closed,
                attachments: this.attachments,
                // Cc and informational are kept for when the agent switches back to Reply; a note sends neither.
                cc_emails: type === 'note' ? [] : this.selected_cc,
                informational_reply: type === 'response' && this.informational_reply,
            };

            if (scheduledAt) {
                data.scheduled_at = scheduledAt;
            }

            // 0 means the thread had no replies yet; still checked.
            if (!Array.isArray(this.ticket) && type === 'response' && Number.isInteger(this.last_conversation_id)) {
                data.last_conversation_id = this.last_conversation_id;
                if (forceSend) {
                    data.force_send = 'yes';
                }
            }

            let action = `tickets/${this.ticket.id}/responses`;
            if (Array.isArray(this.ticket)) {
                data.ticket_ids = this.ticket;
                data.bulk_action = 'reply_tickets';
                action = 'tickets/bulk-reply';
            }
            this.creating = true;
            this.$post(action, data)
                .then(response => {
                    if (!scheduledAt) {
                        this.$notify({
                            message: response.message,
                            type: 'success',
                            position: 'bottom-right',
                            offset: 50,
                        });
                    }
                    this.isCreatingResponse = false;
                    if (this.draftID) {
                        this.removeDraft();
                    }

                    this.response_body = '';
                    this.selected_cc = [];
                    // Empty in place: the array may be the parent's attachment store.
                    this.attachments.splice(0);

                    if (scheduledAt) {
                        this.$refs.scheduleMenu?.close();
                        this.$emit('scheduled', response.response, scheduledAt);
                    } else {
                        this.$emit('created', response.response, response);
                    }
                })
                .catch((errors) => {
                    this.isCreatingResponse = false;
                    const body = errors && errors.responseJSON;
                    if (body && body.code === 'fs_newer_responses') {
                        this.confirmDespiteNewerResponses(body.new_responses || [], closed, scheduledAt, type);
                        return;
                    }
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },

        formatUtcTime,

        onScheduleSubmit(timestamp, closeTicket) {
            if (this.editingSchedule) {
                this.updateSchedule({
                    scheduled_at: timestamp,
                    close_ticket: closeTicket ? 'yes' : 'no'
                });
                return;
            }

            this.create(closeTicket ? 'yes' : 'no', false, timestamp);
        },

        saveScheduleEdit() {
            if (this.isEditorEmpty) {
                return;
            }

            this.updateSchedule({});
        },

        updateSchedule(data) {
            if (this.attachmentUploadState.uploading) {
                this.notifyAttachmentUploadPending();
                return;
            }

            const conversation = this.editingSchedule;

            data.content = this.response_body;
            // jQuery drops an empty array, so send '' to clear the Cc
            data.cc_emails = this.selected_cc?.length ? this.selected_cc : '';
            // The full list the reply keeps, so removed files come off; '' for none
            data.attachments = this.attachments.length ? this.attachments : '';

            this.creating = true;
            this.$put(`tickets/${this.ticket.id}/scheduled_reply/${conversation.id}`, data)
                .then(() => {
                    this.$refs.scheduleMenu?.close();
                    this.$emit('scheduleSaved', data.scheduled_at || conversation.schedule?.scheduled_at, !!data.scheduled_at);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },

        discardScheduleEdit() {
            this.$emit('cancelEditSchedule');
        },

        restoreText(restore) {
            const content = restore?.content || '';
            const cc = Array.isArray(restore?.cc_emails) ? restore.cc_emails : [];

            this.response_body = this.isEditorEmpty ? content : this.response_body + content;
            this.selected_cc = [...new Set([...(this.selected_cc || []), ...cc])];
            this.addUploadedFiles(restore?.attachments || []);
            this.refreshEditor();
        },

        addUploadedFiles(files) {
            files.forEach(file => {
                this.$refs.attachmentForm?.handleUploadSuccess(
                    { attachments: [file.file_hash] },
                    { name: file.title, size: file.file_size, type: file.file_type }
                );
            });
        },

        focusEditor() {
            const id = this.$refs.editor?.editor_id;
            const tiny = id && window.tinyMCE?.get(id);
            if (tiny) {
                tiny.focus();
            } else if (id) {
                document.getElementById(id)?.focus();
            }
        },

        // The editor reads its content only when it mounts, so remount it after replacing the text
        refreshEditor() {
            if (!this.isMounted) {
                return;
            }

            this.editor_ready = false;
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        },

        /**
         * Someone replied while the agent was writing. Show the new replies
         * (the draft stays in the editor) or send anyway.
         */
        confirmDespiteNewerResponses(newResponses, closed, scheduledAt = null, type = this.type) {
            const names = [...new Set(newResponses.map(item => item.person_name).filter(Boolean))];
            let message;
            if (newResponses.length === 1 && names.length === 1) {
                // translators: %s is the name of the customer or agent who replied
                message = this.$t('%s replied while you were writing.', names[0]);
            } else {
                // translators: %s is the number of replies added while the agent was writing
                message = this.$_n('%s new reply arrived while you were writing.', '%s new replies arrived while you were writing.', newResponses.length);
            }

            ElMessageBox.confirm(message, this.$t('New reply on this ticket'), {
                confirmButtonText: this.$t('Send anyway'),
                cancelButtonText: this.$t('Show new replies'),
                confirmButtonClass: 'fs_filled_btn',
                cancelButtonClass: 'fs_outline_btn',
                distinguishCancelAndClose: true,
                type: 'warning'
            })
                .then(() => {
                    this.create(closed, true, scheduledAt, type);
                })
                .catch((action) => {
                    if (action === 'cancel') {
                        this.$emit('newerResponses');
                    }
                });
        },

        loadDraft() {
            if (this.appVars.enable_draft_mode !== 'yes' || Array.isArray(this.ticket) || !this.draft) {
                return;
            }

            this.draftID = this.draft.id || '';

            if (this.editingSchedule && this.stashedComposer) {
                this.stashedComposer.body = this.draft.value.content;
                this.stashedComposer.cc = this.draft.value.selected_cc || [];
                return;
            }

            this.response_body = this.draft.value.content;
            this.selected_cc = this.draft.value.selected_cc;
        },

        handleCcChange(value) {
            if (!value.length) {
                this.selected_cc = [];
                this.show_cc_option = false;
            }
        }
    },
    created() {
        // Load before the child editor mounts: a warm TinyMCE reads the textarea synchronously on init.
        this.loadDraft();
    },
    mounted() {
        this.isMounted = true;

        const composer = this.editingSchedule && this.stashedComposer ? this.stashedComposer : null;
        let cc = composer ? composer.cc : this.selected_cc;

        if (!Array.isArray(this.ticket)) {
            if (this.ticket.responses.length === 0) {
                if (this.ticket.carbon_copy && this.ticket.carbon_copy !== '') {
                    cc = this.ticket.carbon_copy?.split(',') || [];
                }
            } else if (this.ticket.responses[0].cc_info && !cc?.length) {
                cc = this.ticket.responses[0].cc_info;
            }
        } else {
            cc = [];
        }

        if (composer) {
            composer.cc = cc;
        } else {
            this.selected_cc = cc;
        }
    },
    beforeUnmount() {
        // Leaving the ticket inside the 5s autosave window would drop the last edits; save them now.
        if (this.saveResponseDraftTimer) {
            clearTimeout(this.saveResponseDraftTimer);
            this.saveResponseDraftTimer = null;
            this.flushResponseDraft();
        }
    }
}
</script>
