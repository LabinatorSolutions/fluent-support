<template>
    <div v-if="ticket && ticket.id" class="fs_threads_container">

        <article v-if="showSavedDraft" class="fs_saved_draft fs_conversation_message fs_msg_draft">
            <div class="fs_message_container">
                <!-- Avatar -->
                <div class="fs_message_avatar">

                </div>

                <div class="fs_message_content">
                    <div class="fs_message_header">
                        <div class="fs_message_info">
                            <div class="fs_message_name_row">
                                <div class="fs_badge_draft_response">
                                    {{ $t('Saved Draft') }}
                                </div>
                            </div>

                            <div>
                                <!-- translators: %s is the date and time the draft was created -->
                                <span class="fs_message_timestamp" v-if="draftData?.created_at" :title="$t('Draft created at %s', draftData.created_at)">
                                    {{ $timeDiff(draftData.created_at) }}
                                </span>
                                <!-- translators: %s is the date and time the draft was last updated -->
                                <span class="fs_message_timestamp" v-else-if="draftData?.updated_at" :title="$t('Draft updated at %s', draftData.updated_at)">
                                    {{ $timeDiff(draftData.updated_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-html="santizeContent(draftResponse)" class="fs_message_body"></div>

                    <div class="fs_draft_actions">
                        <el-button class="fs_outline_btn" size="default" @click="$emit('edit-draft')">
                            <el-icon><EditPen /></el-icon>
                            {{ $t('Edit') }}
                        </el-button>
                        <el-button class="fs_outline_btn" size="default" @click="$emit('discard-draft', draftData.id)" text>
                            {{ $t('Discard') }}
                        </el-button>
                    </div>
                </div>
            </div>
        </article>

        <slot name="pending" :sanitize="santizeContent"></slot>

        <article v-for="conversation in conversations"
                 :key="conversation.id"
                 :ref="el => setRef(conversation.id, el)"
                 :class="['fs_conversation_message', isActivity(conversation) ? 'fs_conversation_activity' : getConversationClass(conversation)]">

            <!-- System activity (closed, reopened, status changes): one compact line -->
            <div v-if="isActivity(conversation)" class="fs_activity_row">
                <div class="fs_activity_avatar">
                    <!-- Automatic closes, workflows, bookings and bounce or auto-reply notices: an event, not the person on the row. -->
                    <span v-if="isSystemActivity(conversation)" class="fs_activity_system_icon">
                        <DynamicIcon :name="systemActivityIcon(conversation)"/>
                    </span>
                    <img v-else-if="conversation.person" :src="conversation.person.photo" :alt="conversation.person.full_name"/>
                </div>
                <div class="fs_activity_text">
                    <!-- A real space, so copied text and screen readers don't run the name into the line.
                         Older lines name the person themselves ("Sarah assigned ..."), so skip it there. -->
                    <template v-if="!isSystemActivity(conversation) && activityNeedsActor(conversation)"><span class="fs_activity_actor">{{ conversation.person.full_name }}</span>{{ ' ' }}</template>
                    <span class="fs_activity_body" v-html="santizeContent(conversation.content)"></span>
                    <!-- translators: %s is the date and time the activity was recorded -->
                    <span class="fs_activity_time" :title="$t('Conversation created at %s', conversation.created_at)">{{ $timeDiff(conversation.created_at) }}</span>
                </div>
                <lazy-dropdown v-if="canManageTickets" class="fs_activity_actions" :persistent="false" @command="$emit('response-command', $event)" trigger="click">
                    <el-button class="fs_more_btn" :aria-label="$t('More Actions')">
                        <el-icon><MoreFilled /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu class="fs_global_dropdown fs_table_more_actions_dropdown_menu">
                            <el-dropdown-item :command="{ type: 'delete', conversation: conversation }" icon="Delete">
                                {{ $t('Delete') }}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </lazy-dropdown>
            </div>

            <!-- Message Container -->
            <div v-else class="fs_message_container">
                <!-- Avatar -->
                <div class="fs_message_avatar">
                    <img v-if="conversation.person" :src="conversation.person?.photo"
                         :alt="conversation.person.full_name"/>
                </div>

                <!-- Message Content -->
                <div class="fs_message_content">
                    <!-- Header: Name, Role, Badge, Timestamp, More Button -->
                    <div class="fs_message_header">
                        <div class="fs_message_info">
                            <!-- Name and Role -->
                            <div class="fs_message_name_row">
                                <span v-if="conversation.person" class="fs_message_name">{{ conversation.person.full_name }}</span>
                                <span v-if="conversation.person?.person_type === 'customer'" v-tip="$t('Customer')" class="fs_role_badge fs_role_badge_customer">
                                    <IconPack iconKey="user_icon" :width="16" :height="16" />
                                </span>
                                <span v-else-if="conversation.person?.person_type === 'agent' && conversation.conversation_type === 'response'" v-tip="$t('Agent')" class="fs_role_badge fs_role_badge_agent">
                                    <IconPack iconKey="agent_icon" :width="16" :height="16" />
                                </span>

                                <!-- Role Badge for Notes -->
                                <span v-if="['note', 'internal_info'].includes(conversation.conversation_type)" class="fs_message_label fs_label_internal_info">
                                    {{ $t('Added Note') }}
                                </span>

                                <!-- Badge for Draft Response or Reply for Review -->
                                <div v-if="conversation.conversation_type === 'draft_response'" class="fs_badge_draft_response">
                                    {{ $t('DRAFT RESPONSE') }}
                                </div>
                            </div>

                            <!-- Timestamp -->
                            <div>
                                <!-- translators: %s is the date and time the reply was posted -->
                                <span class="fs_message_timestamp" :title="$t('Conversation created at %s', conversation.created_at)">
                                    {{ $timeDiff(conversation.created_at) }}
                                </span>
                            </div>

                            <div class="carrier_info" v-if="conversation.person?.person_type == 'customer' || conversation.cc_info?.length">
                                <div class="from_info" v-if="conversation.person.person_type == 'customer'">
                                    <span><strong>From: </strong>{{ conversation.person?.full_name }}&lt;{{ conversation.person?.email }}&gt;</span>
                                </div>
                                <CcList :emails="conversation.cc_info || []"/>
                            </div>
                        </div>

                        <!-- More Actions Button -->

                        <div class="fs_conversation_right_side">
                            <TicketSourceBadge :source="conversation.source"/>
                            <div v-if="canManageTickets || draftReplyPermission" class="fs_message_actions">
                                <lazy-dropdown :persistent="false" @command="$emit('response-command', $event)" trigger="click">
                                    <el-button class="fs_more_btn">
                                        <el-icon><MoreFilled /></el-icon>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu class="fs_global_dropdown fs_table_more_actions_dropdown_menu">
                                            <el-dropdown-item
                                                v-if="canEditConversation(conversation)"
                                                :command="{ type: 'edit', conversation: conversation }"
                                                icon="EditPen"> {{ $t('Edit') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="has_pro && conversation.conversation_type !== 'draft_response'"
                                                              :command="{ type: 'split_ticket', conversation: conversation }"
                                                              icon="TopLeft">
                                                {{ $t('Split Ticket') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="canManageTickets"
                                                :command="{ type: 'delete', conversation: conversation }"
                                                icon="Delete"> {{ $t('Delete') }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </lazy-dropdown>
                            </div>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div v-html="santizeContent(conversation.content)" class="fs_message_body"></div>

                    <!-- Attachments -->
                    <div class="fs_message_attachments" v-if="conversation.attachments?.length">
                        <ul>
                            <li v-for="attachment in conversation.attachments" :key="attachment.file_hash">
                                <el-icon style="vertical-align: middle;">
                                    <paperclip/>
                                </el-icon>
                                <a target="_blank" rel="noopener" :href="attachment.secureUrl">{{
                                    attachment.title
                                }}</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Liked Indicator -->
                    <div v-if="has_pro && appVars.agent_feedback_rating === 'yes' && conversation.agent_feedback" class="fs_message_liked">
                        <DynamicIcon :name="conversation.agent_feedback === 'like' ? 'LikeButtonFill' : 'DislikeButtonFill'"/>
                        <span>{{ conversation.agent_feedback === 'like' ? $t('Liked') : $t('Disliked') }}</span>
                    </div>

                    <!-- Draft Response Actions -->
                    <div v-if="conversation.conversation_type === 'draft_response'" class="fs_draft_actions">
                        <el-button class="fs_outline_btn" v-if="draftReplyApprovePermission" size="default" @click="$emit('approve-draft', conversation)">
                            <DynamicIcon name="CheckDoubleLine"/>
                            {{ $t('Approve') }}
                        </el-button>
                    </div>
                </div>
            </div>
        </article>
        <article v-if="!isAgentInitiated" class="fs_conversation_message fs_thread_starter">
            <!-- Message Container -->
            <div class="fs_message_container">
                <!-- Avatar -->
                <div class="fs_message_avatar">
                    <img :src="ticket.customer?.photo" :alt="ticket.customer?.full_name"/>
                </div>

                <!-- Message Content -->
                <div class="fs_message_content">
                    <!-- Header: Name, Role, Badge, Timestamp -->
                    <div class="fs_message_header">
                        <div class="fs_message_info">
                            <!-- Name and Role -->
                            <div class="fs_message_name_row">
                                <span class="fs_message_name">{{ ticket.customer?.full_name }}</span>
                                <span v-tip="$t('Customer')" class="fs_role_badge fs_role_badge_customer">
                                    <IconPack iconKey="user_icon" :width="16" :height="16" />
                                </span>

                                <!-- Thread Starter Badge -->
                                <div class="fs_message_badge fs_badge_thread_starter">
                                    {{ $t('THREAD STARTER') }}
                                </div>
                                <span v-if="ticket.created_by_agent && !ticket.created_by_agent.agent_initiated" class="fs_created_by_agent">
                                    {{ $t('Created By') }} <strong>{{ ticket.created_by_agent.full_name }}</strong>
                                </span>
                            </div>

                            <!-- Timestamp and Email Row -->
                            <div class="fs_message_time_row">
                                <!-- translators: %s is the date and time the ticket was created -->
                                <span class="fs_message_timestamp" :title="$t('Ticket created at %s', ticket.created_at)">
                                    {{ $timeDiff(ticket.created_at) }}
                                </span>
                                <span class="fs_message_separator"> • </span>
                                <span class="fs_message_email">{{ ticket.customer?.email }}</span>

                                <div class="carrier_info" v-if="ticket.source == 'email'">
                                    <CcList :emails="ticketCc"/>
                                </div>
                            </div>
                        </div>

                        <!-- Source on the right -->
                        <div class="fs_conversation_right_side">
                            <TicketSourceBadge :source="ticket.source"/>
                            <div v-if="canManageTickets && appVars.can_edit_customer_messages" class="fs_message_actions">
                                <lazy-dropdown :persistent="false" @command="$emit('response-command', $event)" trigger="click">
                                    <el-button class="fs_more_btn" :aria-label="$t('More actions')">
                                        <el-icon><MoreFilled /></el-icon>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu class="fs_global_dropdown fs_table_more_actions_dropdown_menu">
                                            <el-dropdown-item :command="{ type: 'edit_ticket_content' }" icon="EditPen">
                                                {{ $t('Edit') }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </lazy-dropdown>
                            </div>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div v-html="santizeContent(ticket.content)" class="fs_message_body"></div>

                    <!-- Attachments -->
                    <div class="fs_message_attachments" v-if="ticket.attachments && ticket.attachments.length">
                        <ul>
                            <li v-for="attachment in ticket.attachments" :key="attachment.file_hash">
                                <el-icon style="vertical-align: middle;">
                                    <paperclip/>
                                </el-icon>
                                <a target="_blank" rel="noopener" :href="attachment.secureUrl">{{
                                    attachment.title
                                }}</a>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </article>
        <!-- Agent-initiated: the agent's first response above is the opening message; the body is only a placeholder. -->
        <div v-else class="fs_thread_started_by_agent">
            <!-- translators: %s is the agent's name -->
            {{ $t('%s started this conversation', ticket.created_by_agent.full_name) }}
            <span class="fs_message_separator"> • </span>
            <span :title="ticket.created_at">{{ $timeDiff(ticket.created_at) }}</span>
        </div>

        <!-- Custom Field Edit Dialog -->
        <el-dialog
            :title="$t('Updating Custom Field Data')"
            v-model="showCustomDataEditForm"
            v-if="showCustomDataEditForm"
            width="60%"
            :append-to-body="true"
            class="fs_dialog">
            <custom-field-form @syncData="syncCustomData" :ticket_id="ticket.id" type="update_ticket"
                               :custom_data="ticket.custom_fields"/>
        </el-dialog>

        <article class="fs_conversation_message fs_additional_info" v-if="has_pro && !isEmpty(appVars.custom_fields)">
            <!-- Custom Fields Section -->
            <div  class="fs_custom_data_card">
                <div class="fs_custom_data_header">
                    <h3>{{ $t('Additional Info') }}</h3>
                    <el-button @click="showCustomDataEditForm = !showCustomDataEditForm" :text="true"
                                size="small" class="fs_edit_custom_data_btn">
                        <DynamicIcon name="Edit"/>
                    </el-button>
                </div>
                <div class="fs_custom_data_body">
                    <ul v-if="!isEmpty(ticket.custom_fields)" class="fs_custom_data_list">
                        <li v-for="(fieldValue, fieldName) in ticket.custom_fields" :key="fieldName" class="fs_custom_data_item">
                            <span dir="auto" class="fs_custom_data_label">{{ appVars.custom_fields[fieldName].label }}</span>
                            <div dir="auto" class="fs_custom_data_value">
                                        <span v-if="appVars.custom_fields[fieldName].type === 'date-range' && isArray(fieldValue)">
                                            {{ fieldValue.join(' - ') }}
                                        </span>
                                <span v-else-if="isArray(fieldValue)">
                                            <span dir="auto" class="fs_custom_check_value" v-for="value in fieldValue"
                                                  :key="value">{{ value }}</span>
                                        </span>
                                <span v-else v-html="santizeContent(fieldValue)"></span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="fs_no_data">{{ $t('No additional data found') }}</p>
                </div>
            </div>
        </article>

    </div>
</template>

<script type="text/babel">
import { isArray, isEmpty } from 'lodash-es';
import { applyPurifyGuards } from '@/common/domPurifyGuards';
import CustomFieldForm from './_CustomFieldForm';
import TicketSourceBadge from '@/admin/Components/TicketSourceBadge.vue';
import LazyDropdown from '@/admin/Components/LazyDropdown.vue';
import IconPack from '@/admin/Components/IconPack.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import CcList from '@/admin/Components/CcList.vue';

// Activity sources shown as events, with their icon.
const SYSTEM_ACTIVITY_ICONS = {
    automatic: 'AutoClose',
    automation: 'WorkFlow',
    fluent_booking: 'InformationFill',
    email: 'SourceEmail',
};

// santizeContent() runs from the template on every re-render; reuse the result for unchanged content.
const sanitizedCache = new Map();
const SANITIZED_CACHE_LIMIT = 500;

/**
 * The ticket thread, newest first: the saved reply draft, replies, notes and
 * activity lines, the opening message and the custom fields. Every HTML body is
 * sanitized here. Emits the reply actions; ViewTicket.vue does them.
 */
export default {
    name: 'TicketThread',
    components: { CustomFieldForm, TicketSourceBadge, LazyDropdown, IconPack, DynamicIcon, CcList },
    props: ['ticket', 'conversations', 'showSavedDraft', 'draftData', 'draftResponse', 'canManageTickets', 'draftReplyPermission', 'draftReplyApprovePermission'],
    emits: ['edit-draft', 'discard-draft', 'response-command', 'approve-draft', 'custom-data'],
    data() {
        return {
            showCustomDataEditForm: false,
            conversationRefs: {}
        };
    },
    computed: {
        ticketCc() {
            return (this.ticket?.carbon_copy || '').split(',').map(e => e.trim()).filter(Boolean);
        },

        isAgentInitiated() {
            return !!(this.ticket.created_by_agent && this.ticket.created_by_agent.agent_initiated);
        }
    },
    methods: {
        // Scrolls to the reply in the URL's last #fragment, if it is in this thread.
        scrollToConversation(id) {
            if (id && this.conversationRefs[id]) {
                return this.$scrollToRef(this.conversationRefs[id]);
            }
        },

        setRef(id, el) {
            if (el) {
                this.conversationRefs[id] = el;
            }
        },

        // A customer's reply is editable by site admins only (to remove sensitive data).
        canEditConversation(conversation) {
            if (conversation.person?.person_type === 'customer') {
                return !!this.appVars.can_edit_customer_messages;
            }
            return !this.draftReplyPermission || conversation.conversation_type === 'draft_response';
        },

        // Older MCP versions saved real notes as internal_info with source 'mcp'; they render as notes.
        isActivity(conversation) {
            return conversation.conversation_type === 'internal_info' && conversation.source !== 'mcp';
        },

        // Activity with these sources is an event, not something the row's person did: automatic
        // closes, workflow closes, FluentBooking meetings and (Pro) bounce and auto-reply notices.
        isSystemActivity(conversation) {
            return Object.prototype.hasOwnProperty.call(SYSTEM_ACTIVITY_ICONS, conversation.source);
        },

        systemActivityIcon(conversation) {
            return SYSTEM_ACTIVITY_ICONS[conversation.source] || 'InformationFill';
        },

        getConversationClass(conversation) {
            // Determine background color based on conversation type and person type
            if (conversation.conversation_type === 'note') {
                return 'fs_msg_note'; // Yellow background
            } else if (conversation.conversation_type === 'draft_response') {
                return 'fs_msg_draft'; // Light gray background
            } else if (conversation.conversation_type === 'internal_info') {
                return 'fs_msg_note'; // Older MCP notes; system activity renders as a compact row instead
            } else if (conversation.person?.person_type === 'customer') {
                return 'fs_msg_customer'; // White background
            } else if (conversation.person?.person_type === 'agent') {
                return 'fs_msg_agent'; // White background
            }
            return '';
        },

        santizeContent(content) {
            if (!content || typeof content !== 'string') {
                return content;
            }

            const cached = sanitizedCache.get(content);
            if (cached !== undefined) {
                return cached;
            }

            const raw = content;
            content = content.replace(/\n\s*\n/g, '\n');

            // Convert plain-text editor tokens @[id:Name] to styled mention chips.
            if (content.includes('@[')) {
                content = content.replace(
                    /@\[(\d+):([^\]]+)\]/g,
                    (match, id, name) => `<span class="fs_agent_mention">@${name}</span>`
                );
            }

            applyPurifyGuards(window.DOMPurify);

            const sanitized = window.DOMPurify.sanitize(content, {
                FORBID_TAGS: ['style'],
            });

            if (sanitizedCache.size >= SANITIZED_CACHE_LIMIT) {
                sanitizedCache.delete(sanitizedCache.keys().next().value);
            }
            sanitizedCache.set(raw, sanitized);

            return sanitized;
        },

        // Show who did it unless the activity text already names them (older "Sarah assigned ..." lines).
        activityNeedsActor(conversation) {
            const name = conversation.person?.full_name;
            // Compare against the decoded text: names are stored escaped (O'Brien → O&#039;Brien).
            const text = new DOMParser().parseFromString(conversation.content || '', 'text/html').body.textContent;
            return !!name?.trim() && !text.includes(name);
        },

        syncCustomData(data) {
            this.showCustomDataEditForm = false;
            this.$emit('custom-data', data);
        },

        isEmpty(value) {
            return isEmpty(value);
        },

        isArray(value) {
            return isArray(value);
        }
    }
};
</script>
