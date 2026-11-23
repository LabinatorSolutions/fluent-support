<template>
    <!-- New Title Container Design -->
    <div class="fs_title_container">
        <div class="fs_title_content">
            <!-- Editable Title -->
            <div class="fs_title_row">
                <h2 class="fs_ticket_title">
                    <el-popover :persistent="false"
                        v-if="canManageTickets"
                        @show="titleDraft = ticket.title"
                        placement="bottom-start"
                        :width="popoverWidth"
                        trigger="click"
                    >
                        <template #reference>
                            <span :title="$t('Click to Edit Subject')">{{ ticket?.title }}</span>
                        </template>
                        <el-input @keyup.enter="$emit('update-attr', 'title', titleDraft)"
                                  v-model="titleDraft" class="fs_text_input"></el-input>
                        <p style="margin-top: 8px; font-size: 12px; color: var(--fs-text-secondary, #525866);">{{ $t('Press enter to save') }}</p>
                    </el-popover>
                    <span v-else>{{ ticket?.title }}</span>
                </h2>
            </div>

            <!-- Info Container: Timer + Status Badge -->
            <div class="fs_info_container">
                <!-- Timer Info -->
                <!-- translators: %s is the date and time the ticket started waiting for a reply -->
                <div v-if="ticket.waiting_since" v-tip="$t('Waiting Time: %s', ticket.waiting_since)" class="fs_timer_info">
                <el-icon class="fs_timer_icon"><Timer /></el-icon>
                <!-- translators: %s is how long the ticket has been waiting, e.g. "2 hours ago" -->
                <span class="fs_timer_text">{{ $t('Waiting: %s', $timeDiff(ticket.waiting_since)) }}</span>
                </div>
                <!-- Separator Dot -->
                <span v-if="ticket.waiting_since" class="fs_info_separator">∙</span>

                <!-- Status Badge -->
                <el-popover :persistent="false"
                    placement="bottom"
                    :width="300"
                    trigger="click"
                    v-if="canManageTickets && has_pro && !isEmpty(ticket_statuses)"
                >
                    <template #reference>
                        <div class="fs_ticket_status">
                            <span class="fs_status_badge" :class="`fs_status_${ticket.status?.toLowerCase()}`">
                                {{ appVars.ticket_statuses[ticket.status] || ticket.status }}
                            </span>
                        </div>
                    </template>
                    <el-select class="fs_select_field" :teleported="false" :disabled="updating" @change="$emit('status', $event)" :model-value="ticket.status" size="default">
                        <el-option
                            v-for="(status, statusKey) in getTicketStatus"
                            :key="statusKey"
                            :value="statusKey"
                            :label="appVars.ticket_statuses[statusKey] || statusKey">{{ appVars.ticket_statuses[statusKey] || statusKey }}
                        </el-option>
                    </el-select>
                </el-popover>
                <div v-else class="fs_ticket_status">
                    <span class="fs_status_badge" :class="`fs_status_${ticket.status?.toLowerCase()}`">
                        {{ appVars.ticket_statuses[ticket.status] || ticket.status }}
                    </span>
                </div>
            </div>
            <span v-if="scheduledPaused" class="fs_needs_review_badge">{{ $t('Needs review') }}</span>
        </div>
    </div>

    <!-- New Ticket Header Design -->
    <div class="fs_ticket_header">
        <div class="fs_header_container">
            <!-- Left: Action Group (Reply & Notes Buttons) -->
            <!-- With no composer open, one bar starts a reply or a note. -->
            <div v-if="showComposerPrompt" class="fs_composer_prompt">
                <el-button text class="fs_composer_prompt_reply" @click="$emit('toggle-reply')">
                    <el-icon class="fs_composer_prompt_icon"><DynamicIcon name="ChatLineIcon" /></el-icon>
                    <span class="fs_composer_prompt_text">{{ replyPromptParts[0] }}<strong>{{ replyPromptParts[1] }}</strong>{{ replyPromptParts[2] }}</span>
                </el-button>
                <el-button v-if="canManageTickets" text class="fs_composer_prompt_note" @click="$emit('toggle-note')">
                    <el-icon class="fs_composer_prompt_icon"><DynamicIcon name="DraftLineIcon" /></el-icon>
                    <span>{{ $t('Note') }}</span>
                </el-button>
            </div>
            <div v-else class="fs_action_group">
                <el-button
                    v-if="ticket.status != 'closed' && (canManageTickets || draftReplyPermission)"
                    class="fs_outline_btn"
                    :class="{ 'fs_header_btn_active': isReplyResponseBoxOpen }"
                    @click="$emit('toggle-reply')"
                >
                    <IconPack :iconKey="isReplyResponseBoxOpen ? 'cancel' : 'total_replies'" :width="20" :height="20" />

                    {{ isReplyResponseBoxOpen ? $t('Cancel Reply') : $t('Reply') }}
                </el-button>
                <el-button
                    v-if="canManageTickets"
                    class="fs_outline_btn"
                    :class="{ 'fs_header_btn_active': isNoteResponseBoxOpen }"
                    @click="$emit('toggle-note')"
                >
                    <IconPack v-if="isNoteResponseBoxOpen" iconKey="cancel" :width="20" :height="20" />
                    <DynamicIcon name="DraftLineIcon" v-else class="fs_header_icon"/>

                    {{ isNoteResponseBoxOpen ? $t('Cancel Notes') : $t('Notes') }}
                </el-button>
            </div>

            <!-- Right: Business Inbox, Product, Workflow -->
            <div class="fs_header_right_group">
                <!-- Business Inbox Dropdown -->
                <span v-tip="$t('Business Inbox')" >
                    <el-dropdown :persistent="false" v-if="appVars.me.permissions.includes('fst_manage_settings') && ticket.mailbox_id && mailboxes && mailboxes.length > 1" trigger="click" @command="$emit('change-mailbox', $event)">
                                <el-button class="fs_outline_btn fs_header_dropdown">
                                    <el-icon class="fs_header_icon fs_header_lead_icon"><OfficeBuilding /></el-icon>
                                    <span class="fs_header_dropdown_text">{{ getMailboxName(ticket.mailbox_id) }}</span>
                                    <el-icon class="fs_header_icon"><ArrowDown /></el-icon>
                                </el-button>
                        <template #dropdown>
                            <el-dropdown-menu class="fs_global_dropdown">
                                <el-dropdown-item
                                    v-for="mailbox in mailboxes"
                                    :key="mailbox.id"
                                    :command="mailbox.id"
                                    :disabled="ticket.mailbox_id == mailbox.id"
                                >
                                    {{ mailbox.name }}
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </span>

                <!-- Product Button -->
                <span v-tip="$t('Product')">
                    <el-dropdown :persistent="false" v-if="products && products.length" trigger="click" @command="$emit('update-attr', 'product_id', $event)" @visible-change="onProductDropdownToggle">
                        <el-button class="fs_outline_btn" :class="{ 'fs_header_dropdown': ticket.product_id }">
                            <DynamicIcon name="ProductIcon" class="fs_header_icon"/>
                            <span v-if="ticket.product_id" class="fs_header_dropdown_text">{{ getProductName(ticket.product_id) }}</span>
                            <el-icon v-if="ticket.product_id" class="fs_header_icon"><ArrowDown /></el-icon>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu class="fs_global_dropdown">
                                <div v-if="products.length > 6" class="fs_dropdown_search" @click.stop>
                                    <el-input
                                        v-model="productSearchQuery"
                                        :placeholder="$t('Search...')"
                                        clearable
                                        size="small"
                                        @click.stop
                                    />
                                </div>
                                <el-dropdown-item
                                    v-for="product in filteredHeaderProducts"
                                    :key="product.id"
                                    :command="product.id"
                                    :disabled="ticket.product_id == product.id"
                                >
                                    {{ product.title }}
                                </el-dropdown-item>
                                <div v-if="!filteredHeaderProducts.length" class="fs_dropdown_no_results">
                                    {{ $t('No products found') }}
                                </div>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </span>


                <span v-tip="$t('AI Intelligence')">
                    <el-dropdown :persistent="false" v-if="openAIIntegration" trigger="click">
                        <el-button class="fs_filled_btn fs_ai_btn">
                            <DynamicIcon name="AiIcon" class="fs_header_icon"/>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu class="fs_global_dropdown">
                                <el-dropdown-item @click="$emit('ai', 'summary')">{{ $t('Ticket Summary') }}</el-dropdown-item>
                                <el-dropdown-item @click="$emit('ai', 'sentiment')">{{ $t('Customer Sentiment') }}</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </span>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import { isEmpty } from 'lodash-es';
import IconPack from '@/admin/Components/IconPack.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

/**
 * The top of the ticket: the title (edited in place), waiting time and status,
 * then the Reply / Note buttons and the inbox, product and AI dropdowns. Emits
 * what the agent chose; ViewTicket.vue does it.
 */
export default {
    name: 'TicketHeader',
    components: { IconPack, DynamicIcon },
    props: ['ticket', 'updating', 'responseBox', 'canManageTickets', 'draftReplyPermission', 'scheduledPaused'],
    emits: ['update-attr', 'status', 'toggle-reply', 'toggle-note', 'change-mailbox', 'ai'],
    data() {
        return {
            titleDraft: '',
            products: this.appVars.support_products,
            productSearchQuery: '',
            mailboxes: this.appVars.mailboxes,
            ticket_statuses: this.appVars.changeable_ticket_statuses
        };
    },
    computed: {
        openAIIntegration() {
            return !!this.appVars.open_ai_integration;
        },

        filteredHeaderProducts() {
            const products = this.products || [];
            const query = this.productSearchQuery.trim().toLowerCase();
            if (!query) return products;
            return products.filter(product => product.title.toLowerCase().includes(query));
        },

        getTicketStatus() {
            const status = {};
            for (let key in this.ticket_statuses) {
                if (this.ticket_statuses[key].length) {
                    status[key] = this.ticket_statuses[key][0];
                }
            }
            return status;
        },

        popoverWidth() {
            if (typeof window !== 'undefined') {
                return Math.min(600, window.innerWidth - 40);
            }
            return 600;
        },

        isReplyResponseBoxOpen() {
            return ['response', 'draft_response'].includes(this.responseBox);
        },

        isNoteResponseBoxOpen() {
            return this.responseBox === 'note';
        },

        showComposerPrompt() {
            return !this.responseBox && this.ticket.status != 'closed' && (this.canManageTickets || this.draftReplyPermission);
        },

        // "Reply to %s..." split around the recipient, so it can be bold without splitting the sentence.
        replyPromptParts() {
            const customer = this.ticket.customer || {};
            const recipient = (customer.full_name || '').trim() || customer.email || '';
            const marker = '{{recipient}}';
            // translators: %s is the customer's name, or their email address when they have no name
            const sentence = this.$t('Reply to %s...', marker);
            const index = sentence.indexOf(marker);

            if (index === -1) {
                return [sentence, '', ''];
            }

            return [sentence.slice(0, index), recipient, sentence.slice(index + marker.length)];
        }
    },
    methods: {
        isEmpty(value) {
            return isEmpty(value);
        },

        getMailboxName(mailboxId) {
            if (!this.mailboxes || !this.mailboxes.length) {
                return '';
            }
            const mailbox = this.mailboxes.find(m => m.id == mailboxId);
            return mailbox ? mailbox.name : '';
        },

        getProductName(productId) {
            if (!this.products || !this.products.length || !productId) {
                return '';
            }
            const product = this.products.find(p => p.id == productId);
            return product ? product.title : '';
        },

        onProductDropdownToggle(visible) {
            if (!visible) {
                this.productSearchQuery = '';
            }
        }
    }
};
</script>
