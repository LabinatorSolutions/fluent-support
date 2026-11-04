<template>
    <template v-if="app_ready && !first_time_loading">
        <!-- List View -->
        <div v-if="ticketLayout !== 'grid'" v-loading="loading" element-loading-text="Loading..." class="fs_tickets_list" :class="{ 'fs_tickets_list_compact': ticketLayout === 'compact' }">
            <div class="fs_ticket_item" v-for="ticket in tickets" :key="ticket.id" @click="gotToTicket(ticket, $event)">
                <div class="fs_ticket_main_content">
                    <!-- Left Side: Checkbox, Title, Author, Description -->
                    <div class="fs_ticket_left_section">
                        <div class="fs_ticket_title_row">
                            <el-checkbox
                                v-if="show_bulk_actions"
                                @click.stop
                                :model-value="ticket_selections.includes(ticket.id)"
                                @change="handleTicketSelection(ticket.id)"
                            />
                            <div class="fs_ticket_title_group">
                                <template v-if="fieldVisibility.title">
                                    <a :href="$router.resolve({ name: 'view_ticket', params: { ticket_id: ticket.id } }).href"
                                       @click="goToTicketView(ticket, $event)"
                                       class="fs_ticket_title"
                                    >
                                        <span>{{ ticket.title }}</span>
                                    </a>
                                </template>
                                <span v-if="fieldVisibility.description && ticketLayout === 'compact'" class="fs_ticket_inline_description">
                                    <span class="fs_inline_separator">–</span>
                                    <span class="fs_inline_desc_text">{{ getExcerpt(ticket) }}</span>
                                </span>
                                <!-- translators: %s is the customer's first name -->
                                <span v-if="fieldVisibility.author" class="fs_ticket_author">{{ $t('by %s', ticket.customer?.first_name || '') }} </span>
                                <!-- translators: %s is the internal ticket ID number -->
                                <span v-tip="$t('Internal Ticket ID: #%s', ticket.id)" class="fs_tk_number">
                                    #{{ ticket.display_ticket_number || ticket.serial_number || ticket.id }}
                                </span>
                            </div>

                            <ticket-tags
                                v-if="fieldVisibility.tags && ticket.tags.length"
                                @click.stop
                                :tags="ticket.tags"
                                :ticket_id="ticket.id"
                                mode="ticket_list"
                            ></ticket-tags>

                            <span v-if="ticket.source && fieldVisibility.source" v-tip="$sourceLabel(ticket.source)" class="fs_ticket_source_wrap">
                                <DynamicIcon
                                    v-if="$sourceIconName(ticket.source)"
                                    :name="$sourceIconName(ticket.source)"
                                    :label="$sourceLabel(ticket.source)"
                                    class="fs_ticket_source_img"
                                />
                                <img
                                    v-else-if="!imageErrors[ticket.id + '_' + ticket.source]"
                                    :src="$getSourceIcon(ticket.source)"
                                    :alt="$sourceLabel(ticket.source)"
                                    @error="imageErrors[ticket.id + '_' + ticket.source] = true"
                                    class="fs_ticket_source_img"
                                />
                                <span v-else class="fs_source_initial_badge">{{ ticket.source.charAt(0).toUpperCase() }}</span>
                            </span>
                            <span v-if="fieldVisibility.status && ticket.status === 'new'" class="fs_status_dot fs_status_dot_new"></span>
                        </div>
                        <div v-if="fieldVisibility.description" class="fs_ticket_description">
                            <p class="fs_tk_preview_text">{{ getExcerpt(ticket) }}</p>
                        </div>
                    </div>

                    <!-- Right Side: All Metadata -->
                    <div class="fs_ticket_right_section">
                        <!-- Response Count & Mailbox -->
                        <div class="fs_ticket_meta_info">
                            <span v-if="fieldVisibility.response_count && ticket.response_count" v-tip="$t('Response Count')" class="fs_ticket_conversation_count">
                                <IconPack iconKey="ticketCount" :width="16" :height="16" />
                                {{ ticket.response_count }}
                            </span>
                            <!-- translators: %s is the product name -->
                            <span v-if="fieldVisibility.product && ticket.product && ticket.product.title" v-tip="$t('Product: %s', ticket.product?.title || '')" class="fs_ticket_product">
                                <IconPack iconKey="productIcon" :width="16" :height="16" />
                                {{ ticket.product.title }}
                            </span>
                            <!-- translators: %s is the mailbox name -->
                            <el-tooltip v-if="fieldVisibility.mailbox && ticket.mailbox && ticket.mailbox.name && appVars?.mailboxes?.length > 1" effect="dark" :content="$t('Mailbox: %s', ticket.mailbox?.name || '')" placement="top" trigger="hover">
                                <span class="fs_ticket_mailbox">
                                    <IconPack iconKey="businessBoxIcon" :width="16" :height="16" />
                                    {{ ticket.mailbox.name }}
                                </span>
                            </el-tooltip>
                            <span class="fs_ticket_manage" v-if="false">
                                <DynamicIcon name="Settings"/>
                                Manage Ninja
                            </span>
                        </div>

                        <!-- Avatar, Status & Priority -->
                        <div class="fs_ticket_status_group">
                            <LiveActivityAvatars :activities="ticket.live_activity" />
                            <scheduled-reply-badge v-if="ticket.scheduled_reply" :schedule="ticket.scheduled_reply" compact />
                            <!-- translators: %s is a status label (a ticket status, or Published/Draft for a workflow) -->
                            <span v-if="fieldVisibility.status && ticket.status !== 'new'" v-tip="$t('Status: %s', appVars.ticket_statuses[ticket.status] || ticket.status)" class="fs_status_badge" :class="`fs_status_${ticket.status?.toLowerCase()}`">
                                {{ appVars.ticket_statuses[ticket.status] || ticket.status }}
                            </span>
                            <!-- translators: %s is the priority the customer set -->
                            <span v-if="fieldVisibility.client_priority && ticket.client_priority" v-tip="$t('Client Priority: %s', appVars.client_priorities[ticket.client_priority] || ticket.client_priority)" class="fs_client_priority_badge" :class="`fs_client_priority_${ticket.client_priority?.toLowerCase()}`">
                                <span class="fs_priority_dot" :class="`fs_priority_dot_${ticket.client_priority?.toLowerCase()}`"></span>
                                {{ appVars.client_priorities[ticket.client_priority] || ticket.client_priority }}
                            </span>
                            <!-- translators: %s is the date and time the ticket started waiting for a reply -->
                            <span v-if="fieldVisibility.waiting_time && ticket.waiting_since" v-tip="$t('Waiting Time: %s', ticket.waiting_since)" class="fs_ticket_waiting_time">
                                <IconPack iconKey="clock" :width="16" :height="16" />
                                {{ $timeDiff(ticket.waiting_since)}}
                            </span>
                            <!-- translators: %s is the assigned agent's name -->
                            <span v-if="fieldVisibility.agent && ticket.agent" v-tip="$t('Assigned Agent: %s', ticket.agent?.full_name || '')" class="fs_ticket_agent_badge">
                                <IconPack iconKey="agent" :width="14" :height="14" />
                                <span class="fs_ticket_agent_name">
                                    {{ ticket.agent.full_name || $t('Unassigned') }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fs_tickets_empty_state" v-if="!tickets.length">
                <img :src="appVars.asset_url + 'images/empty.svg'" alt="">
                <p>{{ $t('No Results Found') }}</p>
            </div>
        </div>

        <!-- Grid View -->
        <div v-else-if="ticketLayout === 'grid'" v-loading="loading" element-loading-text="Loading..." class="fs_tickets_grid_view">
            <div class="fs_grid_scroll_wrapper">
                <div class="fs_grid_table" :style="{ '--fs-grid-cols': gridTemplateColumns, minWidth: gridMinWidth + 'px' }">

                    <!-- ── Header Row ─────────────────────────────── -->
                    <div class="fs_grid_header_row">
                        <!-- Checkbox col: select-all -->
                        <div class="fs_grid_th fs_grid_th_checkbox">
                            <el-checkbox
                                v-if="show_bulk_actions"
                                :indeterminate="isIndeterminate"
                                :model-value="selectAllChecked"
                                @change="handleSelectAll"
                                @click.stop
                            />
                        </div>

                        <!-- Title -->
                        <div class="fs_grid_th fs_grid_th_title">
                            <span>{{ $t('Title') }}</span>
                        </div>

                        <!-- Agent -->
                        <div v-if="gridFieldVisibility.agent" class="fs_grid_th fs_grid_th_agent">
                            <span>{{ $t('Agent') }}</span>
                        </div>

                        <!-- Product -->
                        <div v-if="gridFieldVisibility.product" class="fs_grid_th fs_grid_th_product">{{ $t('Product') }}</div>

                        <!-- Responses -->
                        <div v-if="gridFieldVisibility.response_count" class="fs_grid_th fs_grid_th_responses">{{ $t('Responses') }}</div>

                        <!-- Priority -->
                        <div v-if="gridFieldVisibility.client_priority" class="fs_grid_th fs_grid_th_priority">{{ $t('Priority') }}</div>

                        <!-- Date -->
                        <div v-if="gridFieldVisibility.waiting_time" class="fs_grid_th fs_grid_th_date">{{ $t('Date') }}</div>

                        <!-- Tags -->
                        <div v-if="gridFieldVisibility.tags" class="fs_grid_th fs_grid_th_tags">
                            <span>{{ $t('Tags') }}</span>
                        </div>

                        <!-- Status -->
                        <div v-if="gridFieldVisibility.status" class="fs_grid_th fs_grid_th_status">{{ $t('Status') }}</div>

                        <!-- Mailbox -->
                        <div v-if="gridFieldVisibility.mailbox" class="fs_grid_th fs_grid_th_mailbox">{{ $t('Mailbox') }}</div>
                    </div>

                    <!-- ── Data Rows ───────────────────────────────── -->
                    <div
                        class="fs_grid_row"
                        v-for="ticket in tickets"
                        :key="ticket.id"
                        :class="{ 'fs_grid_row_selected': ticket_selections.includes(ticket.id) }"
                        @click="gotToTicket(ticket, $event)"
                    >
                        <!-- Checkbox cell (dedicated column) -->
                        <div class="fs_grid_td fs_grid_td_checkbox">
                            <el-checkbox
                                v-if="show_bulk_actions"
                                @click.stop
                                :model-value="ticket_selections.includes(ticket.id)"
                                @change="handleTicketSelection(ticket.id)"
                            />
                        </div>

                        <!-- Title Cell -->
                        <div class="fs_grid_td fs_grid_td_title">
                            <div class="fs_grid_avatar">
                                <img v-if="ticket.customer?.photo" :src="ticket.customer.photo" :alt="ticket.customer.first_name" class="fs_grid_avatar_img" />
                                <span v-else class="fs_grid_avatar_initials">{{ getInitials(ticket.customer) }}</span>
                            </div>
                            <div class="fs_grid_title_content">
                                <div class="fs_grid_title_row">
                                    <a
                                        :href="$router.resolve({ name: 'view_ticket', params: { ticket_id: ticket.id } }).href"
                                        @click="goToTicketView(ticket, $event)"
                                        class="fs_grid_title_text"
                                    >{{ ticket.title }}</a>
                                    <span v-if="gridFieldVisibility.author" class="fs_grid_author">
                                        <!-- translators: %s is the customer's first name -->
                                        {{ $t('by %s', ticket.customer?.first_name || '') }}
                                        <!-- translators: %s is the internal ticket ID number -->
                                        <em v-tip="$t('Internal Ticket ID: #%s', ticket.id)">#{{ ticket.display_ticket_number || ticket.serial_number || ticket.id }}</em>
                                    </span>
                                    <span v-if="ticket.source && gridFieldVisibility.source" class="fs_grid_source_icon" @click.stop>
                                        <span v-tip="$sourceLabel(ticket.source)" class="fs_ticket_source_wrap">
                                            <DynamicIcon
                                                v-if="$sourceIconName(ticket.source)"
                                                :name="$sourceIconName(ticket.source)"
                                                :label="$sourceLabel(ticket.source)"
                                                class="fs_grid_source_img"
                                            />
                                            <img
                                                v-else-if="!imageErrors[ticket.id + '_' + ticket.source]"
                                                :src="$getSourceIcon(ticket.source)"
                                                :alt="$sourceLabel(ticket.source)"
                                                @error="imageErrors[ticket.id + '_' + ticket.source] = true"
                                                class="fs_grid_source_img"
                                            />
                                            <span v-else class="fs_source_initial_badge">{{ ticket.source.charAt(0).toUpperCase() }}</span>
                                        </span>
                                    </span>
                                    <span v-if="ticket.status === 'new'" class="fs_status_dot fs_status_dot_new"></span>
                                    <LiveActivityAvatars :activities="ticket.live_activity" @click.stop />
                                    <scheduled-reply-badge v-if="ticket.scheduled_reply" :schedule="ticket.scheduled_reply" compact />
                                </div>
                                <p v-if="gridFieldVisibility.description" class="fs_grid_description">{{ getExcerpt(ticket) }}</p>
                            </div>
                        </div>

                        <!-- Agent Cell -->
                        <div v-if="gridFieldVisibility.agent" class="fs_grid_td fs_grid_td_agent">
                            <span v-if="ticket.agent" v-tip="ticket.agent.full_name" class="fs_grid_cell_text">{{ ticket.agent.first_name }}</span>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                        <!-- Product Cell -->
                        <div v-if="gridFieldVisibility.product" class="fs_grid_td fs_grid_td_product">
                            <span v-if="ticket.product?.title" v-tip="ticket.product.title" class="fs_grid_cell_text">{{ ticket.product.title }}</span>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                        <!-- Responses Cell -->
                        <div v-if="gridFieldVisibility.response_count" class="fs_grid_td fs_grid_td_responses">
                            <span v-if="ticket.response_count" class="fs_grid_response_count">
                                <IconPack iconKey="ticketCount" :width="16" :height="16" />
                                {{ ticket.response_count }}
                            </span>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                        <!-- Priority Cell -->
                        <div v-if="gridFieldVisibility.client_priority" class="fs_grid_td fs_grid_td_priority">
                            <span
                                v-if="ticket.client_priority"
                                class="fs_client_priority_badge"
                                :class="`fs_client_priority_${ticket.client_priority?.toLowerCase()}`"
                            >
                                <span class="fs_priority_dot" :class="`fs_priority_dot_${ticket.client_priority?.toLowerCase()}`"></span>
                                {{ appVars.client_priorities[ticket.client_priority] || ticket.client_priority }}
                            </span>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                        <!-- Date Cell -->
                        <div v-if="gridFieldVisibility.waiting_time" class="fs_grid_td fs_grid_td_date">
                            <div class="fs_grid_date_stack">
                                <span v-if="ticket.created_at" v-tip="$t('Ticket Created')" class="fs_grid_date fs_grid_date_muted">
                                    <IconPack iconKey="clock" :width="14" :height="14" />
                                    {{ $timeDiff(ticket.created_at) }}
                                </span>
                                <span v-if="ticket.waiting_since" v-tip="$t('Waiting Since')" class="fs_grid_date">
                                    <IconPack iconKey="clock" :width="14" :height="14" />
                                    {{ $timeDiff(ticket.waiting_since) }}
                                </span>
                            </div>
                        </div>

                        <!-- Tags Cell -->
                        <div v-if="gridFieldVisibility.tags" class="fs_grid_td fs_grid_td_tags">
                            <ticket-tags
                                v-if="ticket.tags && ticket.tags.length"
                                @click.stop
                                :tags="ticket.tags"
                                :ticket_id="ticket.id"
                                mode="ticket_list"
                            ></ticket-tags>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                        <!-- Status Cell -->
                        <div v-if="gridFieldVisibility.status" class="fs_grid_td fs_grid_td_status">
                            <span
                                v-if="ticket.status"
                                class="fs_status_badge"
                                :class="`fs_status_${ticket.status?.toLowerCase()}`"
                            >{{ appVars.ticket_statuses[ticket.status] || ticket.status }}</span>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                        <!-- Mailbox Cell -->
                        <div v-if="gridFieldVisibility.mailbox" class="fs_grid_td fs_grid_td_mailbox">
                            <span v-if="ticket.mailbox?.name" class="fs_grid_cell_text">{{ ticket.mailbox.name }}</span>
                            <span v-else class="fs_grid_cell_muted">—</span>
                        </div>

                    </div>
                </div>
            </div>

            <div class="fs_tickets_empty_state" v-if="!tickets.length">
                <img :src="appVars.asset_url + 'images/empty.svg'" alt="">
                <p>{{ $t('No Results Found') }}</p>
            </div>
        </div>
    </template>

    <!-- First load only: later fetches keep the rows and show the v-loading overlay. -->
    <TableSkeleton v-else-if="ticketLayout === 'grid'" :rows="10" :columns="gridSkeletonColumns" />
    <div v-else class="fs_tickets_list fs_tickets_list_skeleton" :class="{ 'fs_tickets_list_compact': ticketLayout === 'compact' }" aria-busy="true">
        <el-skeleton animated :loading="true">
            <template #template>
                <div v-for="n in 10" :key="n" class="fs_ticket_item">
                    <div class="fs_ticket_main_content">
                        <div class="fs_ticket_left_section">
                            <el-skeleton-item variant="text" :style="{ width: [55, 40, 62, 48, 35][n % 5] + '%', height: '16px' }" />
                            <el-skeleton-item v-if="ticketLayout !== 'compact'" variant="text" :style="{ width: [80, 70, 90, 65, 75][n % 5] + '%', marginTop: '6px' }" />
                        </div>
                        <div class="fs_ticket_right_section">
                            <el-skeleton-item v-if="ticketLayout !== 'compact'" variant="text" style="width: 110px;" />
                            <div class="fs_ticket_status_group">
                                <el-skeleton-item variant="button" style="width: 56px; height: 20px;" />
                                <el-skeleton-item variant="text" style="width: 64px;" />
                                <el-skeleton-item variant="text" style="width: 80px;" />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </el-skeleton>
    </div>
</template>

<script type="text/babel">
import TicketTags from './_Tags';
import IconPack from '@/admin/Components/IconPack.vue';
import LiveActivityAvatars from '@/admin/Components/LiveActivityAvatars.vue';
import ScheduledReplyBadge from '../_ScheduledReplyBadge.vue';
import TableSkeleton from '@/admin/Components/TableSkeleton.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import { mapState, mapActions } from 'pinia';
import { useTicketListStore } from '@/admin/stores/ticketList.store';

const GRID_COLUMNS = [
    { width: '52px'  },                                        // checkbox  (always visible)
    { width: '1fr', minWidth: 240 },                           // title     (takes remaining space)
    { key: 'agent',           width: '110px' },
    { key: 'product',         width: '130px' },
    { key: 'response_count',  width: '100px' },
    { key: 'client_priority', width: '110px' },
    { key: 'waiting_time',    width: '120px' },
    { key: 'tags',            width: '140px' },
    { key: 'status',          width: '90px'  },
    { key: 'mailbox',         width: '130px' },
];

/**
 * The ticket rows of the list, in the default, compact or grid layout, with
 * the first-load placeholders and the empty state. Reads the list store; a
 * click opens the ticket.
 */
export default {
    name: 'TicketListRows',
    components: { TicketTags, IconPack, LiveActivityAvatars, ScheduledReplyBadge, TableSkeleton, DynamicIcon },
    props: ['show_bulk_actions'],
    data() {
        return {
            imageErrors: {}
        };
    },
    computed: {
        ...mapState(useTicketListStore, [
            'tickets',
            'loading',
            'first_time_loading',
            'app_ready',
            'ticket_selections',
            'fieldVisibility',
            'gridFieldVisibility',
            'ticketLayout',
            'selectAllChecked',
            'isIndeterminate',
        ]),
        gridColumns() {
            const V = this.gridFieldVisibility;
            return GRID_COLUMNS.filter(col => !col.key || V[col.key]);
        },
        // Same widths as the grid header, in the shape TableSkeleton takes.
        gridSkeletonColumns() {
            return this.gridColumns.map(col => col.minWidth ? 1 : col.width);
        },
        gridTemplateColumns() {
            return this.gridColumns
                .map(col => col.minWidth ? `minmax(${col.minWidth}px, ${col.width})` : col.width)
                .join(' ');
        },
        // Below this width the table scrolls sideways instead of squeezing the title to nothing.
        gridMinWidth() {
            return this.gridColumns.reduce((total, col) => total + (col.minWidth || parseInt(col.width, 10)), 0);
        }
    },
    methods: {
        ...mapActions(useTicketListStore, {
            storeHandleSelectAll: 'handleSelectAll',
            storeHandleTicketSelection: 'handleTicketSelection',
        }),

        gotToTicket(row, event) {
            if (event.target && event.target.tagName.toLowerCase() === 'a') {
                return;
            }

            this.$router.push({
                name: "view_ticket",
                params: { ticket_id: row.id },
            });
        },

        goToTicketView(ticket, event) {
            if (event.ctrlKey || event.metaKey) {
                const routeData = this.$router.resolve({
                    name: "view_ticket",
                    params: { ticket_id: ticket.id },
                });
                window.open(routeData.href, "_blank");
                event.preventDefault();
                event.stopPropagation();
            }
        },

        getExcerpt(row) {
            let text = row.preview_response
                ? row.preview_response.content
                : row.content;

            if (!text) {
                return "";
            }

            // Decode entities first, then strip tags: some piped emails store their markup
            // escaped (&lt;p&gt;), which a single pass would show as a literal <p>.
            const parse = (html) => new DOMParser().parseFromString(html, "text/html").body.textContent;
            return parse(parse(text)).substring(0, 300);
        },

        getInitials(customer) {
            if (!customer) return '?';
            const first = (customer.first_name || '')[0] || '';
            const last = (customer.last_name || '')[0] || '';
            return (first + last).toUpperCase() || '?';
        },

        handleTicketSelection(ticketId) {
            this.storeHandleTicketSelection(ticketId);
        },

        handleSelectAll(checked) {
            this.storeHandleSelectAll(checked);
        }
    }
};
</script>
