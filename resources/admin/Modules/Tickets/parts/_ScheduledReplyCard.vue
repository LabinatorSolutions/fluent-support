<template>
    <article class="fs_conversation_message fs_scheduled_reply_card" :class="{ fs_scheduled_reply_card_paused: paused }">
        <div class="fs_message_container">
            <div class="fs_message_avatar">
                <img v-if="reply.person" :src="reply.person.photo" :alt="reply.person.full_name"/>
            </div>
            <div class="fs_message_content">
                <div class="fs_message_header">
                    <div class="fs_message_info">
                        <div class="fs_message_name_row">
                            <span v-if="reply.person" class="fs_message_name">{{ reply.person.full_name }}</span>
                            <span class="fs_scheduled_reply_state">
                                <el-icon><VideoPause v-if="paused" /><Clock v-else /></el-icon>
                                {{ paused ? $t('Paused') : $t('Scheduled') }}
                            </span>
                            <span class="fs_message_timestamp">{{ timeLabel }}</span>
                        </div>
                        <div class="fs_scheduled_reply_tags" v-if="reply.schedule?.informational === 'yes' || reply.schedule?.close_ticket === 'yes' || reply.cc_info?.length">
                            <span v-if="reply.schedule?.informational === 'yes'">{{ $t('Informational') }}</span>
                            <span v-if="reply.schedule?.close_ticket === 'yes'">{{ $t('Closes ticket') }}</span>
                            <!-- translators: %s is the list of Cc email addresses -->
                            <span v-if="reply.cc_info?.length">{{ $t('Cc: %s', reply.cc_info.join(', ')) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="paused" class="fs_scheduled_reply_warning">
                    <el-icon><WarningFilled /></el-icon>
                    <span>{{ pauseMessage }}</span>
                </div>

                <div v-html="body" class="fs_message_body"></div>

                <div class="fs_message_attachments" v-if="reply.attachments?.length">
                    <ul>
                        <li v-for="attachment in reply.attachments" :key="attachment.file_hash">
                            <el-icon style="vertical-align: middle;"><paperclip/></el-icon>
                            <a target="_blank" rel="noopener" :href="attachment.secureUrl">{{ attachment.title }}</a>
                        </li>
                    </ul>
                </div>

                <div v-if="canManage" class="fs_scheduled_reply_actions">
                    <el-button v-if="canChange" class="fs_outline_btn" size="default" :disabled="busy" @click="$emit('edit')">
                        <el-icon><EditPen /></el-icon>
                        {{ $t('Edit') }}
                    </el-button>
                    <el-button v-if="canChange && paused" class="fs_outline_btn" size="default" :disabled="busy" @click="run('resume')">
                        <el-icon><Clock /></el-icon>
                        {{ $t('Send as planned') }}
                    </el-button>
                    <el-button v-if="canChange" class="fs_outline_btn" size="default" :disabled="busy" @click="run('send_now')">
                        <el-icon><Promotion /></el-icon>
                        {{ $t('Send now') }}
                    </el-button>
                    <el-button text class="fs_scheduled_reply_cancel" :disabled="busy" @click="confirmCancel">
                        {{ $t('Cancel') }}
                    </el-button>
                </div>
            </div>
        </div>
    </article>
</template>

<script type="text/babel">
import { formatUtcTime } from '@/common/dateTime';

/**
 * The ticket's pending scheduled reply in the thread, with send now, resume and cancel.
 * `body` is the reply's content, already sanitized by the thread.
 */
export default {
    name: 'ScheduledReplyCard',
    props: ['ticketId', 'reply', 'body', 'canManage', 'canChange', 'customerName'],
    emits: ['edit', 'cancelled', 'changed'],
    data() {
        return {
            busy: false
        };
    },
    computed: {
        paused() {
            return this.reply.schedule?.status === 'paused';
        },
        timeLabel() {
            const time = formatUtcTime(this.reply.schedule?.scheduled_at);

            if (this.paused) {
                // translators: %s is the date and time the scheduled reply was due
                return this.$t('Was due %s', time);
            }

            // translators: %s is the date and time the scheduled reply will be sent
            return this.$t('Sends %s', time);
        },
        pauseMessage() {
            const reason = this.reply.schedule?.paused_reason;

            if (reason === 'missed') {
                return this.$t('This reply could not be sent on time. Check it before it sends.');
            }

            if (reason === 'ticket_closed') {
                return this.$t('This ticket was closed after you scheduled this reply. Check it before it sends.');
            }

            if (reason === 'agent_unavailable') {
                return this.$t('The agent who wrote this reply is no longer available, so it was not sent.');
            }

            // translators: %s is the customer's name
            return this.$t('%s replied after you scheduled this. Check it before it sends.', this.customerName || this.$t('The customer'));
        }
    },
    methods: {
        run(action) {
            this.busy = true;
            this.$post(`tickets/${this.ticketId}/scheduled_reply/${this.reply.id}/${action}`)
                .then(response => {
                    this.notify(response.message);
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.busy = false;
                    this.$emit('changed');
                });
        },

        confirmCancel() {
            this.$confirm({
                message: this.$t('The text goes back to the editor as a draft.'),
                title: this.$t('Cancel this scheduled reply?'),
                options: {
                    confirmButtonText: this.$t('Cancel reply'),
                    cancelButtonText: this.$t('Keep scheduled'),
                    type: 'warning',
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'fs_filled_btn',
                }
            }).then(() => {
                this.cancel();
            }).catch(() => {});
        },

        cancel() {
            this.busy = true;
            this.$del(`tickets/${this.ticketId}/scheduled_reply/${this.reply.id}`)
                .then(response => {
                    this.notify(response.message);
                    this.$emit('cancelled', response.restore);
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.busy = false;
                    this.$emit('changed');
                });
        },

        notify(message) {
            this.$notify({
                message,
                type: 'success',
                position: 'bottom-right',
                offset: 50,
            });
        }
    }
};
</script>
