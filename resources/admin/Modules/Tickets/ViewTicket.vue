<template>
    <div class="fs_view_ticket" :class="{ fs_ticket_switching: switchingTicket }" :inert="switchingTicket">
        <!-- Keyed on the loaded ticket, not the route: everything inside remounts when the next ticket's
             data arrives, and children get ticket.id so the previous ticket's parts never act on the next one. -->
        <template v-if="ticket && ticket.id" :key="ticket.id">
            <div class="fs_ticket_body">
                <Teleport to="#fs_top_bar_ticket_chip_slot">
                    <scheduled-reply-badge v-if="scheduledReply" :schedule="scheduledReply.schedule" />
                </Teleport>
                <ticket-top-bar
                    :ticket="ticket"
                    :watchers="watchers"
                    :loading="loading"
                    :updating="updating"
                    :switching="switchingTicket"
                    :can-assign-agents="canAssignAgents"
                    :can-manage-tickets="canManageTickets"
                    :list-position="listPosition"
                    :stepping-list="steppingList"
                    @refresh="fetchTicket()"
                    @bookmark="show_watcher_modal = true"
                    @assign="updateTicketAttr('agent_id', $event)"
                    @close="closeTicket()"
                    @reopen="reOpen()"
                    @command="handleTopBarCommand"
                    @step="goToAdjacentTicket"
                />

                <ticket-header
                    :ticket="ticket"
                    :updating="updating"
                    :response-box="show_response_box"
                    :can-manage-tickets="canManageTickets"
                    :draft-reply-permission="draftReplyPermission"
                    :scheduled-paused="isScheduledReplyPaused"
                    @update-attr="updateTicketAttr"
                    @status="handleStatusChange"
                    @toggle-reply="toggleReplyResponseBox"
                    @toggle-note="toggleNoteResponseBox"
                    @change-mailbox="changeMailbox"
                    @ai="$refs.aiInsights.run($event)"
                />

                <merge-tickets v-if="has_pro && show_merge_modal" :ticket="ticket" @merged="onMerged" @close="show_merge_modal = false"/>

                <el-dialog
                    v-model="show_watcher_modal"
                    v-if="has_pro && show_watcher_modal"
                    :title="$t('Add Bookmark')"
                    :append-to-body="true"
                    class="fs_dialog"
                >
                    <div class="fs_box_body">
                        <el-select class="fs_select_field" filterable :clearable="true" :multiple="true" v-model="watcherIds">
                            <el-option
                                v-for="(agent,agent_key) in agents"
                                :key="agent_key" :value="agent.id"
                                :label="agent.full_name"></el-option>
                        </el-select>

                        <el-button class="fs_filled_btn fs_bookmark_action_btn"
                                    @click="addWatchers">{{ $t('Add') }}
                        </el-button>
                    </div>
                </el-dialog>

                <ai-insights ref="aiInsights" :ticket-id="ticket.id"/>

                <transition name="fs-response-box">
                    <div v-if="show_response_box" class="fs_response_box_transition">
                        <div class="fs_response_box_transition_inner">
                            <!-- Keyed by ticket only: switching Reply/Note keeps the same box, so the text stays. -->
                            <create-response
                                ref="createResponse"
                                :key="ticket_id"
                                @created="recordNewResponse"
                                @scheduled="onReplyScheduled"
                                @scheduleSaved="onScheduleSaved"
                                @cancelEditSchedule="editingScheduleId = null"
                                :scheduledReply="scheduledReply"
                                :canChangeSchedule="canChangeScheduledReply"
                                @editSchedule="editScheduledReply"
                                :editingSchedule="editingScheduledReply"
                                :ticket="ticket"
                                :is_agent="'yes'"
                                @close="show_response_box = ''"
                                :type="show_response_box"
                                :draft="draftData"
                                :attachment_store="composerAttachments"
                                :openAIIntegration="openAIIntegration"
                                :last_conversation_id="latestConversationId"
                                @newerResponses="refreshConversations"
                                @discardDraft="discardDraft"
                                @draftSaved="syncDraft"
                            />
                        </div>
                    </div>
                </transition>
                <div class="fs_create_response text-align-center" v-if="ticket.status == 'closed'" :class="'fs_create_response_'+ticket.status">
                    <span>{{$t('Closed Ticket')}}</span>
                    <!-- Imported tickets can be closed with no close date. -->
                    <template v-if="ticket.resolved_at">
                        <!-- translators: %1$s is the date the ticket was closed, %2$s the name of the person who closed it -->
                        <p v-if="ticket.closed_by_person">{{ $t('This ticket was closed on %1$s by %2$s.', ticket.resolved_at, getHumanName(ticket.closed_by_person)) }}</p>
                        <!-- translators: %s is the date the ticket was closed -->
                        <p v-else>{{ $t('This ticket was closed on %s.', ticket.resolved_at) }}</p>
                    </template>
                    <!-- translators: %s is the name of the person who closed the ticket -->
                    <p v-else-if="ticket.closed_by_person">{{ $t('This ticket was closed by %s.', getHumanName(ticket.closed_by_person)) }}</p>
                    <el-button v-if="canManageTickets" v-loading="updating" :disabled="updating" @click="reOpen()" class="fs_outline_btn">
                        <DynamicIcon name="Refresh"/>
                        {{ $t('Reopen This ticket') }}
                    </el-button>
                </div>

                <ticket-thread
                    ref="thread"
                    :ticket="ticket"
                    :conversations="conversations"
                    :show-saved-draft="showSavedDraft"
                    :draft-data="draftData"
                    :draft-response="draftResponse"
                    :can-manage-tickets="canManageTickets"
                    :draft-reply-permission="draftReplyPermission"
                    :draft-reply-approve-permission="draftReplyApprovePermission"
                    @edit-draft="editDraft"
                    @discard-draft="discardDraft"
                    @response-command="handleResponseActionCommand"
                    @approve-draft="approveDraftResponse"
                    @custom-data="syncCustomData"
                >
                    <template #pending="{ sanitize }">
                        <scheduled-reply-card
                            v-if="scheduledReply && !editingScheduledReply"
                            :ticket-id="ticket.id"
                            :reply="scheduledReply"
                            :body="sanitize(scheduledReply.content)"
                            :can-manage="canManageTickets"
                            :can-change="canChangeScheduledReply"
                            :customer-name="ticket.customer?.full_name"
                            @edit="editScheduledReply"
                            @cancelled="restoreCancelledReply"
                            @changed="fetchTicket()"
                        />
                    </template>
                </ticket-thread>
            </div>

            <div class="fs_ticket_sidebar">
                <ticket-sidebar :fluentcrm_profile="fluentcrm_profile" :ticket_id="ticket.id" :ticket="ticket"
                                :watchers="watchers" :watcher_ids="watcherIds" @refresh="fetchTicket" :fetch_other_tickets="fetch_other_tickets" :can_manage_tickets="canManageTickets"/>
            </div>

        </template>

        <template v-else-if="ticketNotFound.length">
            <div class="fs_ticket_error_message">
                <div class="fs_error_container">
                    <img
                        :src="appVars.asset_url + 'images/ticketNotFound.svg'"
                        :alt="$t('Ticket not found')"
                        class="fs_error_icon"
                    />
                    <h2 class="fs_error_title">{{ ticketNotFound }}</h2>
                    <p class="fs_error_description">
                        {{ $t('Go back to') }}
                        <a class="fs_error_link" @click="$router.push({ name: 'tickets' })">{{ $t('All Tickets') }}</a>
                        {{ $t('or contact support for assistance.') }}
                    </p>
                </div>
            </div>
        </template>

        <ticket-skeleton v-else/>

        <el-dialog
            v-model="edit_response_modal"
            :title="editing_response && editing_response.is_ticket_content ? $t('Edit Opening Message') : $t('Edit Response')"
            :append-to-body="true"
            class="fs_dialog"
            @closed="editing_response = false"
        >
            <edit-response
                v-if="editing_response"
                ref="edit_response"
                @updated="onResponseUpdated"
                :response="editing_response"
                :ticket-id="ticket.id"
            />
            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button class="fs_outline_btn" @click="edit_response_modal = false">
                        {{ $t('Cancel') }}
                    </el-button>
                    <el-button
                        class="fs_filled_btn"
                        v-loading="updating_response"
                        :disabled="updating_response"
                        @click="updateResponse()"
                    >
                        {{ draftReplyApprovePermission && editing_response && editing_response.conversation_type === 'draft_response' ? $t('Update And Approve Response') : $t('Update Response') }}
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <el-dialog
            v-model="show_fbs_add_task_modal"
            v-if="show_fbs_add_task_modal"
            :title="$t('Add Task to Fluent Boards')"
            :append-to-body="true"
            class="fs_dialog"
        >
            <FluentBoardsIntegration @created="afterCreatedTask" :ticket=ticket :fluentcrm_profile="fluentcrm_profile" />
        </el-dialog>

        <el-dialog
            v-model="split_ticket_modal"
            :title="$t('Split Ticket')"
            :append-to-body="true"
            class="fs_dialog"
        >
            <split-ticket
                :ticket_data="split_ticket"
                @split-ticket="splitToNewTicket"
                :spliting="loading"
            />
        </el-dialog>
        <active-agents :ticket="ticket" v-if="ticket && ticket.id" :key="ticket.id"/>
    </div>
</template>

<script type="text/babel">
import { each } from 'lodash-es';
import { mapState } from 'pinia';
import ticketService from '@/admin/services/ticketService';
import { useTicketListStore } from '@/admin/stores/ticketList.store';
import { shortcutModifiers, isTypingTarget, adjacentTicket } from '@/admin/Bits/shortcuts';
import CreateResponse from './_CreateResponse';
import EditResponse from './_EditResponse';
import TicketSidebar from './_TicketSidebar';
import FluentBoardsIntegration from './_FluentBoardsIntegration';
import ActiveAgents from './_active_agents';
import SplitTicket from './_SplitTicket';
import TicketTopBar from './parts/_TicketTopBar';
import TicketHeader from './parts/_TicketHeader';
import TicketThread from './parts/_TicketThread';
import AiInsights from './parts/_AiInsights';
import MergeTickets from './parts/_MergeTickets';
import TicketSkeleton from './parts/_TicketSkeleton';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import ScheduledReplyBadge from './_ScheduledReplyBadge.vue';
import ScheduledReplyCard from './parts/_ScheduledReplyCard.vue';
import { formatUtcTime } from '@/common/dateTime';

/**
 * One ticket: loads it with its thread and draft, and does what its parts ask
 * (top bar, header, thread, composer, dialogs). Also the keyboard shortcuts and
 * stepping through the list the agent came from.
 */
export default {
    name: 'ViewTicket',
    props: ['ticket_id'],
    components: {
        DynamicIcon,
        CreateResponse,
        TicketSidebar,
        EditResponse,
        ActiveAgents,
        SplitTicket,
        FluentBoardsIntegration,
        TicketTopBar,
        TicketHeader,
        TicketThread,
        AiInsights,
        MergeTickets,
        TicketSkeleton,
        ScheduledReplyBadge,
        ScheduledReplyCard,
    },
    data() {
        return {
            loading: false,
            // True while the route moved to another ticket and its data is loading. The previous
            // ticket stays on screen, dimmed and inert, so the layout doesn't collapse and refill.
            switchingTicket: false,
            ticket: {},
            conversations: [],
            show_response_box: '',
            scheduledReply: null,
            editingScheduleId: null,
            agents: this.appVars.support_agents,
            updating: false,
            edit_response_modal: false,
            editing_response: false,
            editing_conversation: null,
            updating_response: false,
            fluentcrm_profile: false,
            show_merge_modal: false,
            show_watcher_modal: false,
            watchers: [],
            split_ticket_modal: false,
            split_ticket: {},
            close_ticket_silently: 'no',
            fetch_other_tickets: false,
            canManageTickets: false,
            canAssignAgents: false,
            draftReplyPermission: false,
            draftReplyApprovePermission: false,
            draftResponse: {},
            showDraft: false,
            draftData: {},
            // Composer uploads, kept here so they survive closing and reopening the box.
            composerAttachments: { hashes: [], info: {} },
            ticketNotFound: '',
            show_fbs_add_task_modal: false,
            watcherIds: [],
            steppingList: false
        };
    },
    computed: {
        ...mapState(useTicketListStore, { listTickets: 'tickets', listPagination: 'pagination' }),
        // Where this ticket sits in the list the agent came from, across pages.
        listPosition() {
            const index = (this.listTickets || []).findIndex(item => String(item.id) === String(this.ticket_id));
            if (index === -1) {
                return null;
            }

            const { current_page: page, per_page: perPage, total } = this.listPagination;
            const number = (page - 1) * perPage + index + 1;

            return {
                number,
                total: Math.max(total, number),
                hasPrev: number > 1,
                hasNext: number < total
            };
        },

        editingScheduledReply() {
            if (!this.editingScheduleId || !this.scheduledReply || this.scheduledReply.id !== this.editingScheduleId) {
                return null;
            }

            return this.scheduledReply;
        },

        canChangeScheduledReply() {
            if (!this.scheduledReply || !this.canManageTickets) {
                return false;
            }

            return parseInt(this.appVars.me?.id) === parseInt(this.scheduledReply.person_id)
                || (this.appVars.me?.permissions || []).includes('fst_manage_other_tickets');
        },

        isScheduledReplyPaused() {
            return this.scheduledReply?.schedule?.status === 'paused';
        },

        openAIIntegration() {
            return !!this.appVars.open_ai_integration;
        },

        // The saved reply draft shows in the thread while no composer is open.
        showSavedDraft() {
            return this.appVars.enable_draft_mode === 'yes' && this.showDraft && !this.show_response_box;
        },

        latestConversationId() {
            return (this.conversations || []).reduce((max, conversation) => Math.max(max, parseInt(conversation.id) || 0), 0);
        }
    },
    watch: {
        show_response_box(type) {
            if (type !== 'response') {
                this.editingScheduleId = null;
            }
        },
        '$route.params.ticket_id'(ticketId) {
            if (ticketId) {
                this.doAction('ticket_view_exit', this.ticket.id);
                this.switchingTicket = !!this.ticket.id;
                // The reply box, draft and AI panels belong to the ticket we left.
                this._draftGeneration = (this._draftGeneration || 0) + 1;
                this.draftData = null;
                this.draftResponse = null;
                this.showDraft = false;
                this.show_response_box = '';
                this.composerAttachments = { hashes: [], info: {} };
                this.$refs.aiInsights?.close();
                this.$nextTick(() => {
                    this.doAction('ticket_view_entered', ticketId);
                    this.fetchTicket();
                });
            }
        }
    },

    methods: {
        toggleReplyResponseBox() {
            const responseType = this.draftReplyPermission ? 'draft_response' : 'response';
            this.show_response_box = (this.show_response_box === responseType) ? '' : responseType;
        },

        toggleNoteResponseBox() {
            this.show_response_box = this.show_response_box === 'note' ? '' : 'note';
        },

        /** Reload the thread only, leaving the reply box and its draft alone. */
        refreshConversations() {
            const requestedTicketId = this.ticket_id;
            ticketService.get(requestedTicketId)
                .then(response => {
                    // Ignore a response for a ticket the user already switched away from.
                    if (requestedTicketId !== this.ticket_id) {
                        return;
                    }
                    this.conversations = response.responses;
                    this.scheduledReply = response.scheduled_reply || null;
                    this.applyTicketStatus(response.ticket);
                    this.$notify({
                        type: 'info',
                        message: this.$t('New replies loaded. Your reply is still in the editor.'),
                        position: 'bottom-right'
                    });
                })
                .catch(errors => {
                    this.$handleError(errors);
                });
        },

        async fetchTicket() {
            const requestedTicketId = this.ticket_id;
            this.loading = true;

            // Run in parallel — fetchDraft() guards its own stale-ticket check.
            const ticketRequest = ticketService.get(requestedTicketId, {
                customer_id: this.ticket.customer_id,
                with_data: ['fluentcrm_profile']
            });
            const draftRequest = this.appVars.enable_draft_mode === 'yes'
                ? this.fetchDraft()
                : Promise.resolve();

            await Promise.all([ticketRequest, draftRequest]).then(([response]) => {
                // Ignore a response for a ticket the user already switched away from.
                if (requestedTicketId !== this.ticket_id) {
                    return;
                }
                this.loading = false;
                if (this.switchingTicket) {
                    this.switchingTicket = false;
                    window.scrollTo(0, 0);
                }
                this.ticket = response.ticket;
                this.ticket.product_id = (this.ticket.product_id && this.ticket.product_id !== '0')
                    ? Number(this.ticket.product_id)
                    : null;
                this.$setTitle(response.ticket.title);
                this.conversations = response.responses;
                this.scheduledReply = response.scheduled_reply || null;
                const permissions = this.appVars.me.permissions;
                this.canManageTickets = permissions.includes('fst_manage_own_tickets')
                    || permissions.includes('fst_manage_unassigned_tickets')
                    || permissions.includes('fst_manage_other_tickets');
                // Reassigning is its own capability, checked server-side on the
                // agent_id property branch — managing a ticket is not enough.
                this.canAssignAgents = this.canManageTickets
                    && permissions.includes('fst_assign_agents');
                this.draftReplyPermission = permissions.includes('fst_draft_reply');
                this.draftReplyApprovePermission = permissions.includes('fst_approve_draft_reply');

                // sent the response to body event
                document.body.dispatchEvent(new CustomEvent('fs_ticket_viewed', {
                    detail: {
                        response: response
                    }
                }));

                if (this.appVars.fluentcrm_config) {
                    this.fluentcrm_profile = response.fluentcrm_profile;
                }

                if (this.has_pro) {
                    this.watchers = response.watchers;
                }
            }).catch(error => {
                // Ignore a late error for a ticket the user already left.
                if (requestedTicketId !== this.ticket_id) {
                    return;
                }
                this.loading = false;
                // Don't leave the previous ticket on screen in place of one that failed to load.
                if (this.switchingTicket) {
                    this.switchingTicket = false;
                    this.ticket = {};
                }
                this.$handleError(error);
            })
            .finally(() => {
                // Don't clear loading for a ticket the user has since switched to.
                if (requestedTicketId !== this.ticket_id) {
                    return;
                }
                this.loading = false;
            });
        },

        async fetchDraft() {
            const requestedTicketId = this.ticket_id;
            try {
                const response = await ticketService.draft(requestedTicketId);
                // Ignore a late response for the ticket we already left.
                if (requestedTicketId !== this.ticket_id) {
                    return;
                }
                this.draftData = response.draft;

                if (this.draftData && this.draftData.value.content) {
                    this.draftResponse = this.draftData.value.content;
                    this.showDraft = true;
                } else {
                    this.draftResponse = null;
                    this.showDraft = false;
                }
            } catch (error) {
                this.$handleError(error);
            }
        },

        // The "Closed Ticket" bar reads when and by whom, so they change with the status.
        applyTicketStatus(ticket) {
            this.ticket.status = ticket.status;
            this.ticket.resolved_at = ticket.resolved_at || null;
            this.ticket.closed_by_person = ticket.closed_by_person || null;
        },

        onReplyScheduled(response, scheduledAt) {
            this.show_response_box = '';
            this.fetchTicket();

            this.$notify({
                // translators: %s is the date and time the reply will be sent
                message: this.$t('Reply scheduled for %s', formatUtcTime(scheduledAt)),
                type: 'success',
                position: 'bottom-right',
                offset: 50,
            });
        },

        onScheduleSaved(scheduledAt, rescheduled) {
            this.editingScheduleId = null;

            // translators: %s is the date and time the reply will be sent
            let message = this.$t('Changes saved. Still sends %s', formatUtcTime(scheduledAt, true));
            if (rescheduled) {
                // translators: %s is the date and time the reply will be sent
                message = this.$t('Reply rescheduled for %s', formatUtcTime(scheduledAt));
            }

            this.$notify({
                type: 'success',
                position: 'bottom-right',
                offset: 50,
                message
            });
            this.fetchTicket();
        },

        editScheduledReply() {
            this.show_response_box = 'response';
            this.editingScheduleId = this.scheduledReply.id;
        },

        restoreCancelledReply(restore) {
            this.editingScheduleId = null;
            this.show_response_box = 'response';
            this.$nextTick(() => {
                this.$refs.createResponse?.restoreText(restore);
            });
        },

        recordNewResponse(response, data) {
            this.conversations.unshift(response);
            this.applyTicketStatus(data.ticket);
            this.show_response_box = '';

            if (response?.conversation_type === 'response' && response?.content?.includes('fs_ticket_id=')) {
                window.dispatchEvent(new CustomEvent('fluent-support/fluent-booking-link-sent', {
                    detail: {
                        ticket_id: this.ticket_id,
                        response_id: response.id
                    }
                }));
            }

            each(data.update_data, (data, key) => {
                this.ticket[key] = data;
            });
        },

        // Shows the new value right away, saves it, then reloads the ticket.
        updateTicketAttr(propName, propValue) {
            this.ticket[propName] = propValue;

            return ticketService.updateProperty(this.ticket.id, propName, propValue)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });

                    each(response.update_data, (data, key) => {
                        this.ticket[key] = data;
                    });
                    this.fetchTicket();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        },

        handleStatusChange(newStatus) {
            // Prevent overlapping status requests from the same dropdown while a
            // status transition (close/re-open/property update) is still in flight.
            if (this.updating) {
                return;
            }

            const prevStatus = this.ticket.status;

            if (newStatus === prevStatus) {
                return;
            }

            // Closing from the status control should behave exactly like the
            // Close Ticket button (runs the dedicated /close flow + internal note).
            if (newStatus === 'closed') {
                this.closeTicket();
                return;
            }

            // Reopening a closed ticket should go through the dedicated re-open
            // flow so it logs the same internal note as the Re-open button.
            if (prevStatus === 'closed') {
                this.reOpen();
                return;
            }

            // active <-> new transitions: property update logs an internal note backend-side.
            this.updating = true;
            this.updateTicketAttr('status', newStatus)
                .always(() => {
                    this.updating = false;
                });
        },

        getHumanName(person) {
            if (parseInt(this.appVars.me?.id) === parseInt(person.id)) {
                return this.$t('You');
            }

            return person.full_name;
        },

        closeTicket() {
            this.updating = true;
            ticketService.close(this.ticket.id, this.close_ticket_silently)
                .then(response => {
                    this.applyTicketStatus(response.ticket);
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    if (window.history.state?.back) {
                        this.$router.go(-1);
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        },

        reOpen() {
            this.updating = true;
            ticketService.reopen(this.ticket.id)
                .then(response => {
                    this.applyTicketStatus(response.ticket);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        },

        deleteTicket() {
            this.$confirm({
                message: this.$t('Are you sure to delete this Ticket?'),
                title: this.$t('Warning'),
                options: {
                    confirmButtonText: this.$t('Delete Ticket'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning',
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'el-button--danger fs_filled_btn',
                }
            }).then(() => {
                ticketService.remove(this.ticket.id)
                    .then(response => {
                        this.$notify({
                            message: response.message,
                            type: "success",
                            position: "bottom-right",
                        });
                        this.$router.push({name: 'tickets'});
                    });
            });
        },

        onResponseUpdated(updated) {
            this.editing_conversation.content = updated.content;
            // Editing what the customer wrote adds an activity line; reload to show it.
            const editedCustomerText = this.editing_response.is_ticket_content
                || this.editing_conversation.person?.person_type === 'customer';
            this.edit_response_modal = false;
            this.editing_response = false;
            if (editedCustomerText) {
                this.fetchTicket();
            }
        },

        updateResponse() {
            this.updating_response = true;
            this.$refs.edit_response.editResponse().always(() => {
                this.updating_response = false;
            });
        },

        approveDraftResponse(conversation) {
            ticketService.approveDraftResponse(conversation.ticket_id, conversation.id, conversation.content)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success'
                    });
                    this.fetchTicket();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        },

        // A reply's menu in the thread: delete, edit or split it off.
        handleResponseActionCommand({ type, conversation }) {
            if (type === 'delete') {
                this.$confirm({
                    message: this.$t('response_delete_warning'),
                    title: this.$t('Warning'),
                    options: {
                        confirmButtonText: this.$t('Delete Response'),
                        cancelButtonText: this.$t('Cancel'),
                        type: 'warning',
                        cancelButtonClass: 'el-button--default fs_outline_btn',
                        confirmButtonClass: 'el-button--danger fs_filled_btn',
                    }
                }).then(() => {
                    ticketService.deleteResponse(this.ticket.id, conversation.id)
                        .then(response => {
                            this.$notify({
                                message: response.message,
                                type: "success",
                                position: "bottom-right",
                            });
                            this.fetchTicket();
                        })
                        .catch((error) => {
                            this.$handleError(error);
                        });
                });
            } else if (type === 'edit') {
                // Edit a copy so Cancel leaves the thread untouched
                this.editing_conversation = conversation;
                this.editing_response = { ...conversation };
                this.edit_response_modal = true;
            } else if (type === 'edit_ticket_content') {
                // The opening message lives on the ticket; _EditResponse saves it to tickets/{id}/content.
                this.editing_conversation = this.ticket;
                this.editing_response = { ticket_id: this.ticket.id, content: this.ticket.content, is_ticket_content: true };
                this.edit_response_modal = true;
            } else if (type === 'split_ticket') {
                this.split_ticket = {
                    conversation_id: conversation.id,
                    content: conversation.content,
                    customer_id: parseInt(this.ticket.customer_id),
                    mailbox_id: parseInt(this.ticket.mailbox_id),
                    product_id: this.ticket.product?.id,
                    client_priority: this.ticket.priority,
                    create_wp_user: 'no',
                    create_customer: 'no',
                };
                this.split_ticket_modal = true;
            }
        },

        afterCreatedTask() {
            this.show_fbs_add_task_modal = false;
            this.fetchTicket();
        },

        changeMailbox(mailboxId) {
            this.loading = true;
            ticketService.moveToMailbox(this.ticket.mailbox_id, mailboxId, this.ticket_id)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.fetchTicket();
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },

        onMerged() {
            this.fetchTicket();
            this.fetch_other_tickets = !this.fetch_other_tickets;
        },

        syncCustomData(data) {
            this.ticket.custom_fields = data;
        },

        addWatchers() {
            ticketService.addWatchers(this.ticket.id, this.watcherIds)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.fetchTicket();
                    this.show_watcher_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        },

        splitToNewTicket() {
            this.loading = true;
            ticketService.split(this.ticket.id, this.split_ticket)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.fetchTicket();
                    this.split_ticket_modal = false;
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },

        /** Keep the local draft current so a reopened reply box never loads stale text. */
        syncDraft(data, request, ticketId) {
            // A box closing as the agent leaves a ticket flushes its last edits after the route changed.
            if (ticketId && String(ticketId) !== String(this.$route.params.ticket_id)) {
                return;
            }

            this.draftData = {
                ...(this.draftData || {}),
                id: data.draftID || this.draftData?.id,
                value: data
            };
            this.draftResponse = data.content;

            // A first save has no ID yet; record the one the server assigns, unless the agent moved to another
            // ticket or discarded the draft meanwhile (either bumps the generation).
            if (request) {
                const generation = this._draftGeneration;
                request.done((response) => {
                    if (generation === this._draftGeneration && this.draftData && !this.draftData.id && response?.draftID) {
                        this.draftData = { ...this.draftData, id: response.draftID };
                    }
                });
            }
        },

        discardDraft(draftID) {
            this._draftGeneration = (this._draftGeneration || 0) + 1;
            return ticketService.discardDraft(draftID)
                .then(() => {
                    this.draftData = null;
                    this.draftResponse = null;
                    this.showDraft = false;
                })
                .catch((error) => {
                    this.$handleError(error);
                });
        },

        // Edit on the saved draft opens the composer with it.
        editDraft() {
            this.show_response_box = this.draftReplyPermission ? 'draft_response' : 'response';
        },

        openMerge() {
            if (this.has_pro) {
                this.show_merge_modal = !this.show_merge_modal;
            }
        },

        handleKeydown(event) {
            const { code } = event;
            const { primary, alt } = shortcutModifiers(event);

            if (!primary || !alt || code === 'KeyI' || isTypingTarget(event.target)) {
                return;
            }

            const keyActions = {
                KeyR: () => {
                    if (!this.canManageTickets && !this.draftReplyPermission) return;
                    this.toggleReplyResponseBox();
                },
                KeyN: () => {
                    if (!this.canManageTickets) return;
                    this.toggleNoteResponseBox();
                },
                KeyQ: () => this.fetchTicket(),
                KeyM: () => this.openMerge(),
                KeyB: () => {
                    if (this.has_pro) {
                        this.show_watcher_modal = !this.show_watcher_modal;
                    }
                },
                KeyX: () => this.closeTicketFromShortcut(),
                ArrowDown: () => this.goToAdjacentTicket(1),
                ArrowUp: () => this.goToAdjacentTicket(-1),
            };

            // While the next ticket loads, the screen still shows the previous one: only allow stepping.
            if (this.switchingTicket && code !== 'ArrowDown' && code !== 'ArrowUp') {
                return;
            }

            const action = keyActions[code];
            if (action) {
                event.preventDefault();
                action();
            }
        },

        /** Step through the ticket list the agent came from, loading the next or previous page at its ends. */
        goToAdjacentTicket(step) {
            if (this.steppingList) {
                return;
            }
            const target = adjacentTicket(this.listTickets, this.ticket_id, step);
            const position = this.listPosition;
            const crossesPage = !target && position && (step > 0 ? position.hasNext : position.hasPrev);
            if (!target && !crossesPage) {
                this.$notify({
                    type: 'info',
                    position: 'bottom-right',
                    message: step > 0 ? this.$t('This is the last ticket in this list.') : this.$t('This is the first ticket in this list.')
                });
                return;
            }
            const open = (ticket) => this.$router.push({ name: 'view_ticket', params: { ticket_id: ticket.id } });
            const go = () => (target ? open(target) : this.loadAdjacentListPage(step).then(ticket => ticket && open(ticket)));
            if (!this.hasUnsentReply()) {
                go();
                return;
            }
            this.$confirm({
                message: this.$t('Your reply has not been sent. Leave this ticket?'),
                title: this.$t('Unsent reply'),
                options: {
                    confirmButtonText: this.$t('Leave'),
                    cancelButtonText: this.$t('Stay'),
                    type: 'warning'
                }
            }).then(go).catch(() => {});
        },

        /** Loads the next (step 1) or previous (step -1) list page and returns the ticket to open from it. */
        loadAdjacentListPage(step) {
            const store = useTicketListStore();
            const fromPage = store.pagination.current_page;
            store.pagination.current_page = fromPage + step;
            this.steppingList = true;
            const fetchId = store._fetchId + 1;

            return store.fetchTickets(true)
                .then(() => {
                    // A newer list load replaced this one, so the list may not hold the page asked for.
                    if (store._fetchId !== fetchId) {
                        return null;
                    }

                    // The list page restores its page from here, so it stays on the page the agent reached.
                    const pref = this.$getData('tickets_pref', false);
                    if (pref) {
                        this.$saveData('tickets_pref', { ...pref, current_page: store.pagination.current_page });
                    }

                    const tickets = store.tickets || [];
                    return step > 0 ? tickets[0] : tickets[tickets.length - 1];
                })
                .catch((error) => {
                    // The list still holds the old page, so the page number goes back with it.
                    store.pagination.current_page = fromPage;
                    this.$handleError(error);
                    return null;
                })
                .finally(() => {
                    this.steppingList = false;
                });
        },

        hasUnsentReply() {
            const box = this.show_response_box && this.$refs.createResponse;
            if (!box) {
                return false;
            }
            if (box.attachments && box.attachments.length) {
                return true;
            }
            const body = String(box.response_body || '');
            // A pasted screenshot or embed is a reply too, even with no text.
            if (/<(img|video|audio|iframe|embed|object)\b/i.test(body)) {
                return true;
            }
            const text = body.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
            return text !== '';
        },

        closeTicketFromShortcut() {
            if (!this.canManageTickets || !this.ticket.id || this.ticket.status === 'closed') {
                return;
            }
            this.$confirm({
                message: this.$t('Close this ticket?'),
                title: this.$t('Close Ticket'),
                options: {
                    confirmButtonText: this.$t('Close Ticket'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning'
                }
            }).then(() => {
                this.closeTicket();
            }).catch(() => {});
        },

        scrollToHash() {
            return this.$refs.thread?.scrollToConversation(window.location.hash.split('#').pop());
        },

        // A wheel over the top bars would scroll the window (only the WP menu moves), so scroll the thread.
        // The stacked layout and the front-end portal scroll the page, so the thread is not a scroller there.
        forwardWheelToThread(event) {
            const thread = this.$el?.querySelector?.('.fs_ticket_body');
            if (event.ctrlKey || !event.deltaY || !thread || getComputedStyle(thread).overflowY !== 'auto') {
                return;
            }
            event.preventDefault();
            thread.scrollBy({ top: event.deltaMode === 1 ? event.deltaY * 16 : event.deltaY });
        },

        toggleTopBarWheel(on) {
            document.querySelectorAll('.fs_main_navbar, .fs_combined_top_bar').forEach(bar => {
                if (on) {
                    bar.addEventListener('wheel', this.forwardWheelToThread, { passive: false });
                } else {
                    bar.removeEventListener('wheel', this.forwardWheelToThread);
                }
            });
        },

        handleTopBarCommand(command) {
            switch (command) {
                case 'merge':
                    this.openMerge();
                    break;
                case 'close_silently':
                    this.close_ticket_silently = 'yes';
                    this.closeTicket();
                    this.close_ticket_silently = 'no';
                    break;
                case 'fluent_boards':
                    this.show_fbs_add_task_modal = !this.show_fbs_add_task_modal;
                    break;
                case 'delete':
                    this.deleteTicket();
                    break;
            }
        }
    },
    async mounted() {
        // Before the awaits, so leaving during the first load still removes them.
        window.addEventListener("hashchange", this.scrollToHash);
        if (this.appVars.keyboard_shortcuts === 'yes') {
            window.addEventListener("keydown", this.handleKeydown);
        }
        this.toggleTopBarWheel(true);

        const initialTicketId = this.ticket_id;
        await this.fetchTicket();
        // Skip if the user switched tickets during the initial load — the
        // route watcher already fired this for the ticket now being viewed.
        if (this.ticket_id === initialTicketId) {
            this.doAction('ticket_view_entered', this.ticket_id);
        }

        await this.scrollToHash();
    },
    beforeUnmount() {
        this.doAction('ticket_view_exit', this.ticket_id);
        document.body.dispatchEvent(new CustomEvent('fs_ticket_view_exit', {
            detail: {
                ticket_id: this.ticket_id
            }
        }));
        window.removeEventListener("hashchange", this.scrollToHash);
        if (this.appVars.keyboard_shortcuts === 'yes') {
            window.removeEventListener("keydown", this.handleKeydown);
        }
        this.toggleTopBarWheel(false);
    }
};
</script>
