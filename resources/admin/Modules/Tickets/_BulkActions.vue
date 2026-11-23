<template>
    <div class="fs_bb_root">
        <div
            v-if="visible"
            ref="bar"
            class="fs_bb_bar"
            :class="{ 'is-compact': compact, 'is-narrow': narrow, 'is-busy': working }"
            :style="barStyle"
            role="toolbar"
            :aria-label="$t('Bulk actions')"
        >
            <div v-if="working" class="fs_bb_run" aria-live="polite">
                <span class="fs_bb_spin" aria-hidden="true"></span>
                <span class="fs_bb_run_label">{{ runLabel }}</span>
            </div>

            <template v-else>
                <div class="fs_bb_left">
                    <button type="button" class="fs_bb_btn is-icon" :aria-label="$t('Clear selection')" :title="$t('Clear selection (Esc)')" @click="deselect">
                        <DynamicIcon name="CloseLine"/>
                    </button>
                    <span class="fs_bb_sep" aria-hidden="true"></span>
                    <span class="fs_bb_count">{{ countLabel.before }}<strong>{{ countLabel.count }}</strong>{{ countLabel.after }}</span>
                </div>

                <template v-if="!compact">
                    <span class="fs_bb_sep" aria-hidden="true"></span>
                    <div class="fs_bb_actions">
                        <button
                            v-for="action in catalog.primary"
                            :key="action.key"
                            type="button"
                            class="fs_bb_btn"
                            :data-panel="action.key"
                            :aria-haspopup="action.input === 'reply' ? 'dialog' : 'true'"
                            :aria-expanded="rootPanel === action.key ? 'true' : 'false'"
                            @click="onPrimary(action, $event.currentTarget)"
                        >
                            <DynamicIcon :name="primaryIcons[action.key]"/>
                            <span class="fs_bb_btn_text">{{ action.label }}</span>
                        </button>
                        <button
                            type="button"
                            class="fs_bb_btn"
                            data-panel="more"
                            aria-haspopup="true"
                            :aria-expanded="rootPanel === 'more' ? 'true' : 'false'"
                            @click="openPanel('more', $event.currentTarget)"
                        >
                            <span class="fs_bb_btn_text">{{ $t('More') }}</span>
                            <DynamicIcon name="ArrowDownSLine"/>
                        </button>
                    </div>
                    <template v-if="catalog.destructive">
                        <span class="fs_bb_sep" aria-hidden="true"></span>
                        <button
                            type="button"
                            class="fs_bb_btn is-icon is-danger"
                            data-panel="delete"
                            aria-haspopup="true"
                            :aria-expanded="rootPanel === 'delete' ? 'true' : 'false'"
                            :aria-label="$t('Delete tickets')"
                            :title="$t('Delete tickets')"
                            @click="openPanel('delete', $event.currentTarget)"
                        >
                            <DynamicIcon name="DeleteBinLine"/>
                        </button>
                    </template>
                </template>
                <button
                    v-else
                    type="button"
                    class="fs_bb_btn is-menu"
                    data-panel="menu"
                    aria-haspopup="true"
                    :aria-expanded="rootPanel === 'menu' ? 'true' : 'false'"
                    @click="openPanel('menu', $event.currentTarget)"
                >
                    <span class="fs_bb_btn_text">{{ $t('Actions') }}</span>
                    <DynamicIcon name="ArrowDownSLine"/>
                </button>
            </template>
        </div>

        <div v-if="activePanel && compact" class="fs_bb_scrim" @click="closePanel()"></div>

        <div
            v-if="activePanel"
            ref="panel"
            class="fs_bb_panel"
            :class="{ 'is-sheet': compact }"
            :style="panelStyle"
            role="dialog"
            :aria-label="panelTitle"
            tabindex="-1"
        >
            <div class="fs_bb_panel_head">
                <div class="fs_bb_panel_title">
                    <button v-if="panelStack.length > 1" type="button" class="fs_bb_back" :aria-label="$t('Back')" @click="popPanel">
                        <DynamicIcon name="ArrowLeftSLine"/>
                    </button>
                    <span>{{ panelTitle }}</span>
                </div>
                <div v-if="currentAction && currentAction.input === 'terms'" class="fs_bb_seg" role="group" :aria-label="$t('Add or remove')">
                    <button type="button" :aria-pressed="termsMode === 'add' ? 'true' : 'false'" @click="setTermsMode('add')">{{ $t('Add') }}</button>
                    <button type="button" :aria-pressed="termsMode === 'remove' ? 'true' : 'false'" @click="setTermsMode('remove')">{{ $t('Remove') }}</button>
                </div>
            </div>

            <div class="fs_bb_panel_body">
                <!-- Menu lists: "More" on desktop, everything in compact mode -->
                <template v-if="!currentAction">
                    <template v-if="activePanel === 'menu'">
                        <button v-for="action in catalog.primary" :key="action.key" type="button" class="fs_bb_menu_item" @click="onMenuPrimary(action)">
                            <DynamicIcon :name="primaryIcons[action.key]"/>
                            <span>{{ action.label }}</span>
                        </button>
                    </template>
                    <template v-for="group in catalog.groups" :key="group.label">
                        <div class="fs_bb_group_label">{{ group.label }}</div>
                        <button v-for="item in group.items" :key="item.key" type="button" class="fs_bb_menu_item" @click="pushPanel(item.key)">
                            <span>{{ item.label }}</span>
                        </button>
                    </template>
                    <template v-if="activePanel === 'menu' && catalog.destructive">
                        <div class="fs_bb_group_label">{{ $t('Danger zone') }}</div>
                        <button type="button" class="fs_bb_menu_item is-danger" @click="pushPanel('delete')">
                            <DynamicIcon name="DeleteBinLine"/>
                            <span>{{ $t('Delete tickets') }}</span>
                        </button>
                    </template>
                </template>

                <bulk-option-list
                    v-else-if="currentAction.input === 'terms'"
                    :key="termsMode"
                    ref="optionList"
                    v-model="termsIds"
                    multiple
                    :items="tagItems"
                    :placeholder="$t('Search tags')"
                    :empty-text="termsMode === 'remove' ? $t('None of the selected tickets have a tag.') : $t('No tags yet')"
                />

                <bulk-option-list
                    v-else-if="currentAction.input === 'agent'"
                    ref="optionList"
                    v-model="choiceValue"
                    :items="agentItems"
                    :placeholder="$t('Search agents')"
                />

                <template v-else-if="currentAction.input === 'choice' || currentAction.input === 'workflow'">
                    <bulk-option-list
                        ref="optionList"
                        v-model="choiceValue"
                        :items="choiceItems"
                        :placeholder="$t('Search')"
                    />
                    <div v-if="currentAction.input === 'workflow' && choiceValue" class="fs_bb_info">
                        <template v-if="workflowLoading">{{ $t('Loading...') }}</template>
                        <template v-else-if="workflowActions.length">
                            <div>{{ $t('This workflow will:') }}</div>
                            <ul class="fs_bb_info_list">
                                <li v-for="task in workflowActions" :key="task.id">{{ task.title || task.action_name }}</li>
                            </ul>
                        </template>
                    </div>
                </template>

                <div v-else-if="currentAction.input === 'confirm'" class="fs_bb_info">{{ confirmNote }}</div>

                <div v-else-if="currentAction.input === 'delete'" class="fs_bb_info is-danger">
                    {{ $t('This removes the tickets with their replies, notes and attachments. It cannot be undone.') }}
                </div>
            </div>

            <div v-if="currentAction" class="fs_bb_panel_foot">
                <button type="button" class="fs_bb_apply" :class="{ 'is-danger': currentAction.input === 'delete' }" :disabled="applyDisabled" @click="apply">
                    {{ applyLabel }}
                </button>
            </div>
        </div>

        <el-dialog
            :title="$t('Reply To Selected Tickets')"
            v-model="add_response_modal"
            width="60%"
            :append-to-body="true"
            class="fs_dialog fs_bulk_action_modal">
            <div class="fs_bulk_action_create_response">
                <create-response @created="afterChange()" v-if="add_response_modal" :ticket="ticket_selections"/>
            </div>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import { mapState, mapWritableState } from 'pinia';
import CreateResponse from './_CreateResponse';
import BulkOptionList from './BulkActions/BulkOptionList.vue';
import { buildTicketBulkCatalog, buildTicketBulkPayload, countTagCoverage } from './BulkActions/bulkActionCatalog';
import { getGroupedAgentOptions } from '@/admin/Composable/agentGroupHelper';
import { useTicketListStore } from '@/admin/stores/ticketList.store';

// Bar widths (px) below which the bar collapses into one "Actions" menu.
const NARROW_WIDTH = 860;
const COMPACT_WIDTH = 640;
const MAX_BAR_WIDTH = 860;

/**
 * Floating bulk-action bar for the ticket list, laid out like FluentCRM's
 * contact bulk bar. The list store owns the selection.
 */
export default {
    name: 'TicketBulkActions',
    components: {
        CreateResponse,
        BulkOptionList,
        DynamicIcon
    },
    emits: ['fetchTickets'],
    data() {
        return {
            working: false,
            runLabel: '',
            add_response_modal: false,
            // Panel state.
            panelStack: [],
            anchorEl: null,
            termsMode: 'add',
            termsIds: [],
            choiceValue: null,
            workflowActions: [],
            workflowLoading: false,
            // Layout.
            barStyle: {},
            panelStyle: {},
            compact: false,
            narrow: false
        };
    },
    computed: {
        ...mapState(useTicketListStore, ['tickets']),
        ...mapWritableState(useTicketListStore, ['ticket_selections']),
        catalog() {
            return buildTicketBulkCatalog({ $t: this.$t, appVars: this.appVars });
        },
        primaryIcons() {
            return { reply: 'Message2Line', assign: 'CustomerServiceLine', tags: 'PriceTag3Line', close: 'CheckDoubleLine' };
        },
        visible() {
            return this.ticket_selections.length > 0 || this.working;
        },
        // The count is bold inside one translatable sentence, so split the translation around it.
        countLabel() {
            const count = String(this.ticket_selections.length);
            // Wrap the count in a marker so the bold part is the placeholder, never another digit.
            const marker = '\u0001';
            const parts = this.selectedLabel(marker + count + marker).split(marker);
            if (parts.length !== 3) {
                return { before: parts.join(''), count: '', after: '' };
            }
            return { before: parts[0], count: parts[1], after: parts[2] };
        },
        selectedTickets() {
            const ids = this.ticket_selections.map(String);
            return (this.tickets || []).filter(ticket => ids.includes(String(ticket.id)));
        },
        scopeLabel() {
            // translators: %s is the number of selected tickets. Used inside button labels such as "Close 3 tickets"
            return this.$_n('%s ticket', '%s tickets', this.ticket_selections.length);
        },
        closedSelectedCount() {
            return this.selectedTickets.filter(ticket => ticket.status === 'closed').length;
        },
        rootPanel() {
            return this.panelStack[0] || null;
        },
        activePanel() {
            return this.panelStack[this.panelStack.length - 1] || null;
        },
        currentAction() {
            return this.findAction(this.activePanel);
        },
        panelTitle() {
            if (this.currentAction) {
                if (this.currentAction.input === 'delete') {
                    // translators: %s is the number of selected tickets
                    return this.$_n('Delete %s ticket?', 'Delete %s tickets?', this.ticket_selections.length);
                }
                return this.currentAction.label;
            }
            if (this.activePanel === 'menu') {
                return this.selectedLabel(this.ticket_selections.length);
            }
            return this.$t('More actions');
        },
        tagItems() {
            const coverage = countTagCoverage(this.selectedTickets);
            const total = this.selectedTickets.length;
            let items = (this.appVars.ticket_tags || []).map(tag => {
                const have = total ? (coverage[String(tag.id)] || 0) : null;
                // Adding a tag every selected ticket already has would do nothing.
                const noop = this.termsMode === 'add' && have !== null && have === total;
                let note = '';
                if (have) {
                    // translators: Short badge next to a tag name. %1$s is how many selected tickets have it, %2$s is the number of selected tickets
                    note = have === total ? (noop ? this.$t('Already on all') : this.$t('On all')) : this.$t('On %1$s of %2$s', have, total);
                }
                return { id: tag.id, title: tag.title, note, disabled: noop, have };
            });
            // Removing only makes sense for tags at least one selected ticket has.
            if (this.termsMode === 'remove' && total) {
                items = items.filter(item => item.have > 0);
            }
            return items;
        },
        agentItems() {
            const agents = this.appVars.support_agents || [];
            const groups = this.appVars.agent_groups || [];
            if (!groups.length) {
                return agents.map(agent => ({ id: agent.id, title: agent.full_name }));
            }
            const items = [];
            getGroupedAgentOptions(agents, groups, this.$t('Other')).forEach(group => {
                group.agents.forEach(agent => items.push({ id: agent.id, title: agent.full_name, group: group.label }));
            });
            return items;
        },
        choiceItems() {
            const action = this.currentAction;
            if (!action || !action.items) {
                return [];
            }
            if (action.key !== 'priority' && action.key !== 'product') {
                return action.items;
            }
            // Mark the value every selected ticket already has.
            const field = action.key === 'priority' ? 'priority' : 'product_id';
            const total = this.selectedTickets.length;
            return action.items.map(item => {
                const noop = total > 0 && this.selectedTickets.every(ticket => String(ticket[field]) === String(item.id));
                return { ...item, disabled: noop, note: noop ? this.$t('Already set') : '' };
            });
        },
        confirmNote() {
            const action = this.currentAction;
            if (action && action.key === 'reopen') {
                // translators: %1$s is the number of closed tickets in the selection, %2$s is the number of selected tickets
                return this.$t('%1$s of %2$s selected tickets are closed. Only they reopen.', this.closedSelectedCount, this.ticket_selections.length);
            }
            return this.$t('Tickets that are already closed are skipped. Customers get the ticket closed email if it is turned on for the inbox.');
        },
        choiceTitle() {
            const item = this.choiceItems.concat(this.agentItems).find(option => String(option.id) === String(this.choiceValue));
            return item ? item.title : '';
        },
        applyDisabled() {
            const action = this.currentAction;
            if (!action || this.working) {
                return true;
            }
            switch (action.input) {
                case 'terms':
                    return !this.termsIds.length;
                case 'agent':
                case 'choice':
                    return this.choiceValue === null || this.choiceValue === '';
                case 'workflow':
                    return this.choiceValue === null || this.workflowLoading;
                case 'confirm':
                    return action.key === 'reopen' && this.selectedTickets.length > 0 && !this.closedSelectedCount;
            }
            return false;
        },
        applyLabel() {
            const action = this.currentAction;
            if (!action) {
                return '';
            }
            const scope = this.scopeLabel;
            const empty = this.choiceValue === null || this.choiceValue === '';
            switch (action.key) {
                case 'tags': {
                    const count = this.termsIds.length;
                    if (!count) {
                        return this.$t('Choose tags');
                    }
                    // translators: %s is the number of chosen tags. Used inside "Add %1$s to %2$s"
                    const what = this.$_n('%s tag', '%s tags', count);
                    // translators: %1$s is a number of tags such as "2 tags", %2$s is the tickets such as "3 tickets"
                    return this.termsMode === 'add' ? this.$t('Add %1$s to %2$s', what, scope) : this.$t('Remove %1$s from %2$s', what, scope);
                }
                case 'assign':
                    // translators: %1$s is the tickets such as "3 tickets", %2$s is an agent name
                    return empty ? this.$t('Choose an agent') : this.$t('Assign %1$s to %2$s', scope, this.choiceTitle);
                case 'agent_group':
                    // translators: %1$s is the tickets such as "3 tickets", %2$s is an agent group name
                    return empty ? this.$t('Choose a group') : this.$t('Assign %1$s via %2$s', scope, this.choiceTitle);
                case 'priority':
                    // translators: %1$s is the tickets such as "3 tickets", %2$s is a priority such as Critical
                    return empty ? this.$t('Choose a priority') : this.$t('Set %1$s to %2$s priority', scope, this.choiceTitle);
                case 'product':
                    // translators: %1$s is the tickets such as "3 tickets", %2$s is a product name
                    return empty ? this.$t('Choose a product') : this.$t('Move %1$s to %2$s', scope, this.choiceTitle);
                case 'workflow':
                    // translators: %1$s is a workflow name, %2$s is the tickets such as "3 tickets"
                    return empty ? this.$t('Choose a workflow') : this.$t('Run %1$s on %2$s', this.choiceTitle, scope);
                case 'close':
                    // translators: %s is the tickets such as "3 tickets"
                    return this.$t('Close %s', scope);
                case 'reopen':
                    // translators: %s is the number of closed tickets to reopen
                    return this.$_n('Reopen %s ticket', 'Reopen %s tickets', this.closedSelectedCount || this.ticket_selections.length);
                case 'delete':
                    // translators: %s is the tickets such as "3 tickets"
                    return this.$t('Delete %s', scope);
            }
            return '';
        }
    },
    watch: {
        visible(isVisible) {
            if (isVisible) {
                this.$nextTick(this.layout);
            } else {
                this.closePanel(false);
            }
        },
        'ticket_selections.length'() {
            this.$nextTick(this.layout);
        },
        choiceValue(value) {
            if (this.currentAction && this.currentAction.input === 'workflow' && value) {
                this.loadWorkflowActions(value);
            }
        }
    },
    methods: {
        // One wording for the bar and the compact menu title.
        selectedLabel(count) {
            // translators: %s is the number of selected tickets
            return this.$_n('%s item Selected', '%s items Selected', count);
        },
        findAction(key) {
            if (!key || key === 'menu' || key === 'more') {
                return null;
            }
            if (key === 'delete') {
                return this.catalog.destructive;
            }
            const primary = this.catalog.primary.find(action => action.key === key);
            if (primary) {
                return primary;
            }
            for (const group of this.catalog.groups) {
                const item = group.items.find(action => action.key === key);
                if (item) {
                    return item;
                }
            }
            return null;
        },
        deselect() {
            this.closePanel(false);
            this.ticket_selections = [];
        },
        onPrimary(action, anchorEl) {
            if (action.input === 'reply') {
                this.closePanel(false);
                this.add_response_modal = true;
                return;
            }
            this.openPanel(action.key, anchorEl);
        },
        onMenuPrimary(action) {
            if (action.input === 'reply') {
                this.closePanel(false);
                this.add_response_modal = true;
                return;
            }
            this.pushPanel(action.key);
        },
        resetPanelInputs() {
            this.termsMode = 'add';
            this.termsIds = [];
            this.choiceValue = null;
            this.workflowActions = [];
        },
        /** Open a root panel, or close it if it is already open. */
        openPanel(key, anchorEl) {
            if (this.rootPanel === key) {
                this.closePanel();
                return;
            }
            this.anchorEl = anchorEl || null;
            this.panelStack = [key];
            this.afterPanelChange();
        },
        /** Drill into an action from a menu; Back returns to the menu. */
        pushPanel(key) {
            this.panelStack = [...this.panelStack, key];
            this.afterPanelChange();
        },
        popPanel() {
            this.panelStack = this.panelStack.slice(0, -1);
            this.afterPanelChange();
        },
        afterPanelChange() {
            this.resetPanelInputs();
            this.$nextTick(() => {
                this.positionPanel();
                this.focusPanel();
            });
        },
        closePanel(restoreFocus = true) {
            if (!this.panelStack.length) {
                return;
            }
            const anchor = this.anchorEl;
            this.panelStack = [];
            this.anchorEl = null;
            if (restoreFocus && anchor && document.body.contains(anchor)) {
                anchor.focus();
            }
        },
        focusPanel() {
            const panel = this.$refs.panel;
            if (!panel) {
                return;
            }
            if (this.$refs.optionList) {
                this.$refs.optionList.focus();
                return;
            }
            // Never focus Apply: on panels without a field the panel is the only confirmation.
            const first = panel.querySelector('input:not([disabled]), button:not([disabled]):not(.fs_bb_apply)');
            (first || panel).focus();
        },
        setTermsMode(mode) {
            this.termsMode = mode;
            this.termsIds = [];
        },
        loadWorkflowActions(workflowId) {
            this.workflowActions = [];
            this.workflowLoading = true;
            this.$get('workflows/' + workflowId + '/actions')
                .then(response => {
                    if (String(this.choiceValue) === String(workflowId)) {
                        this.workflowActions = response.actions || [];
                    }
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.workflowLoading = false;
                });
        },
        apply() {
            const action = this.currentAction;
            if (!action || this.applyDisabled) {
                return;
            }

            const ticketIds = [...this.ticket_selections];
            let request;
            if (action.input === 'workflow') {
                request = this.$post('workflows/' + this.choiceValue + '/run', { ticket_ids: ticketIds });
            } else {
                const value = action.input === 'terms' ? { mode: this.termsMode, ids: this.termsIds } : this.choiceValue;
                request = this.$post('tickets/bulk-actions', { ...buildTicketBulkPayload(action, value), ticket_ids: ticketIds });
            }

            // translators: %s is the name of the bulk action being applied
            this.runLabel = action.input === 'delete' ? this.$t('Deleting tickets') : this.$t('Applying: %s', action.label);
            this.working = true;
            this.closePanel(false);

            request
                .then(response => {
                    this.$notify({ type: 'success', message: response.message, position: 'bottom-right' });
                    this.afterChange();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.working = false;
                });
        },
        afterChange() {
            this.add_response_modal = false;
            this.$emit('fetchTickets');
        },
        /** Center the bar on the list and pick the layout tier from its width. */
        layout() {
            const host = this.$el && this.$el.parentElement;
            if (!host) {
                return;
            }
            const rect = host.getBoundingClientRect();
            const viewport = document.documentElement.clientWidth;
            const left = Math.max(rect.left, 0);
            const right = Math.min(rect.right, viewport);
            const width = Math.max(0, Math.min(MAX_BAR_WIDTH, right - left - 32));
            this.barStyle = { left: (left + (right - left) / 2) + 'px', maxWidth: width + 'px' };
            this.compact = width < COMPACT_WIDTH;
            this.narrow = width < NARROW_WIDTH;
            this.$nextTick(this.fitContent);
        },
        /** Collapse into the "Actions" menu when the buttons do not fit. */
        fitContent() {
            const bar = this.$refs.bar;
            if (bar && !this.compact && bar.scrollWidth > bar.clientWidth + 1) {
                this.compact = true;
            }
            this.positionPanel();
        },
        positionPanel() {
            const panel = this.$refs.panel;
            const bar = this.$refs.bar;
            if (!panel || !bar || this.compact) {
                this.panelStyle = {};
                return;
            }
            const barRect = bar.getBoundingClientRect();
            const anchorRect = this.anchorEl && document.body.contains(this.anchorEl) ? this.anchorEl.getBoundingClientRect() : barRect;
            const viewport = document.documentElement.clientWidth;
            const width = panel.offsetWidth;
            const left = Math.min(Math.max(16, anchorRect.left), viewport - width - 16);
            this.panelStyle = {
                left: left + 'px',
                bottom: (window.innerHeight - barRect.top + 8) + 'px',
                maxHeight: Math.max(200, Math.min(480, barRect.top - 24)) + 'px'
            };
        },
        onResize() {
            this.layout();
        },
        /** Another dialog or message box is open; leave its keys alone. */
        overlayOpen() {
            return Array.from(document.querySelectorAll('.el-overlay')).some(el => el.getClientRects().length > 0);
        },
        onKeydown(e) {
            if (!this.visible || e.key !== 'Escape') {
                return;
            }
            if (this.activePanel) {
                e.preventDefault();
                this.closePanel();
                return;
            }
            const target = e.target;
            const editable = target && target.closest && target.closest('input, textarea, select, [contenteditable="true"]');
            if (!editable && !this.working && !this.overlayOpen()) {
                this.deselect();
            }
        },
        /** Close the panel on clicks outside it, the bar, and teleported dropdowns. */
        onPointerDown(e) {
            if (!this.activePanel) {
                return;
            }
            const target = e.target;
            if (!target || !target.closest) {
                return;
            }
            if (target.closest('.fs_bb_panel, .fs_bb_bar, .el-popper, .el-overlay')) {
                return;
            }
            this.closePanel(false);
        }
    },
    mounted() {
        this.layout();
        window.addEventListener('resize', this.onResize);
        document.addEventListener('keydown', this.onKeydown);
        document.addEventListener('pointerdown', this.onPointerDown, true);
        if (typeof ResizeObserver !== 'undefined' && this.$el.parentElement) {
            this.resizeObserver = new ResizeObserver(() => this.layout());
            this.resizeObserver.observe(this.$el.parentElement);
        }
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.onResize);
        document.removeEventListener('keydown', this.onKeydown);
        document.removeEventListener('pointerdown', this.onPointerDown, true);
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
    }
};
</script>
