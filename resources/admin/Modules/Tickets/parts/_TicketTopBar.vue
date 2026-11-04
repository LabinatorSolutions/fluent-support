<template>
    <Teleport to="#fs_top_bar_actions_slot">
        <div class="fs_action_buttons" :class="{ fs_ticket_switching: switching }" :inert="switching">
            <!-- Refresh Button -->
            <el-button v-tip="$t('Refresh')"
                class="fs_refresh_btn fs_outline_btn"
                :title="$t('Refresh')"
                @click="$emit('refresh')"
                :disabled="loading"
                v-loading="loading"
            >
                <DynamicIcon name="Refresh"/>
            </el-button>

            <!-- Bookmark/Watcher Button -->
            <el-button v-if="!watchers.length && has_pro" v-tip="$t('Add Bookmark')"
                class="fs_outline_btn"
                :title="$t('Add Bookmark')"
                @click="$emit('bookmark')"
            >
                <DynamicIcon name="BookMark"/>
            </el-button>

            <!-- Support Agent Button -->
            <span v-if="canAssignAgents" v-tip="$t('Assign Agent')" class="fs_agent_selector_tooltip">
                <el-popover :persistent="false"
                    placement="bottom"
                    :width="300"
                    trigger="click"
                    popper-class="fs_popover"
                    @show="onAgentPopoverShow"
                >
                    <template #reference>

                            <el-button
                                class="fs_outline_btn"
                                :class="ticket.agent?.photo ? 'fs_agent_selector_btn' : ''"
                            >
                                <div v-if="ticket.agent?.photo" class="fs_agent_selector_display">
                                    <img
                                        :src="ticket.agent.photo"
                                        :alt="ticket.agent.full_name"
                                        class="fs_agent_avatar_img"
                                    />
                                    <span class="fs_agent_name_display">
                                        {{ ticket.agent?.full_name || $t('Unassigned') }}
                                        <span v-if="assignedAgentGroupName" class="fs_agent_group_label">({{ assignedAgentGroupName }})</span>
                                    </span>
                                    <el-icon class="fs_agent_dropdown_arrow"><ArrowDown /></el-icon>
                                </div>
                                <DynamicIcon name="AgenIconFill" v-else class="fs_agent_icon_placeholder"/>
                            </el-button>
                    </template>
                    <div class="fs_agent_selector">
                        <el-select
                            ref="agentSelect"
                            :model-value="ticket.agent_id"
                            @change="$emit('assign', $event)"
                            filterable
                            class="fs_select_field fs_agent_selector_dropdown"
                            popper-class="fs_agent_group_popper"
                            :placeholder="$t('Select Agent')"
                        >
                            <template v-if="agentGroups.length">
                                <el-option-group
                                    v-for="group in groupedAgentOptions"
                                    :key="group.label"
                                    :label="group.label"
                                >
                                    <el-option
                                        v-for="agent in group.agents"
                                        :key="agent.id"
                                        :value="agent.id"
                                        :label="agent.full_name"
                                    />
                                </el-option-group>
                            </template>
                            <template v-else>
                                <el-option
                                    v-for="agent in agents"
                                    :key="agent.id"
                                    :value="agent.id"
                                    :label="agent.full_name"
                                />
                            </template>
                        </el-select>
                    </div>
                </el-popover>
            </span>
            <!-- Workflow Button -->
            <work-flow-selector v-if="appVars.manual_workflows && appVars.manual_workflows.length" v-tip="$t('Run Workflow')"
                @reloadTickets="$emit('refresh')"
                :ticket_ids="[ticket.id]">
                <el-button
                    class="fs_outline_btn"
                    :title="$t('Run Workflow')"
                >
                    <DynamicIcon name="WorkFlow"/>
                </el-button>
            </work-flow-selector>

            <!-- Close/Reopen Ticket Button -->
            <el-button v-if="canManageTickets" v-tip="$t('Close Ticket')"
                class="fs_outline_btn fs_action_btn_with_text fs_close_ticket_btn"
                @click="$emit(ticket.status === 'closed' ? 'reopen' : 'close')"
                :disabled="updating"
                v-loading="updating"
            >
                <el-icon v-if="ticket.status !== 'closed'"><Check /></el-icon>
                <el-icon v-if="ticket.status === 'closed'"><RefreshRight /></el-icon>
                <span>{{ ticket.status === 'closed' ? $t('Re-open') : $t('Close Ticket') }}</span>
            </el-button>

            <!-- More Actions Button -->
            <el-dropdown :persistent="false" v-if="canManageTickets" v-tip="$t('More Actions')" class="fs_more_action" trigger="click" @command="$emit('command', $event)">
                <el-button class="fs_outline_btn" :title="$t('More Actions')">
                    <el-icon style="transform: rotate(90deg);"><More /></el-icon>
                </el-button>
                <template #dropdown>
                    <el-dropdown-menu class="fs_global_dropdown fs_table_more_actions_dropdown_menu">
                        <div v-if="has_pro">
                            <el-dropdown-item command="merge">
                                <svg width="14" style="margin-right: 4px;" height="14" viewBox="0 0 14 14"
                                    fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M4.14447 5.12748C4.24566 5.4978 4.46577 5.8246 4.77092 6.05754C5.07606 6.29048 5.44932 6.41666 5.83322 6.41665H8.16655C8.85374 6.4167 9.51884 6.65933 10.0446 7.10178C10.5704 7.54423 10.9232 8.15808 11.0406 8.83515C11.4365 8.96431 11.7734 9.23039 11.9908 9.5856C12.2082 9.9408 12.2918 10.3619 12.2267 10.7732C12.1616 11.1845 11.9519 11.5591 11.6355 11.8298C11.319 12.1005 10.9163 12.2495 10.4999 12.25C10.0924 12.2503 9.69767 12.1084 9.38362 11.8489C9.06957 11.5893 8.85594 11.2283 8.77956 10.828C8.70318 10.4278 8.76884 10.0135 8.96522 9.65651C9.1616 9.29952 9.47639 9.02224 9.85531 8.87248C9.75412 8.50216 9.534 8.17537 9.22886 7.94242C8.92371 7.70948 8.55045 7.5833 8.16655 7.58331H5.83322C5.20199 7.58424 4.58765 7.37946 4.08322 6.99998V8.84915C4.47259 8.98676 4.80078 9.2576 5.00976 9.61378C5.21875 9.96997 5.29509 10.3886 5.22528 10.7956C5.15547 11.2026 4.94401 11.5719 4.62827 11.8381C4.31254 12.1043 3.91286 12.2503 3.49989 12.2503C3.08691 12.2503 2.68724 12.1043 2.3715 11.8381C2.05577 11.5719 1.84431 11.2026 1.7745 10.7956C1.70469 10.3886 1.78102 9.96997 1.99001 9.61378C2.199 9.2576 2.52718 8.98676 2.91655 8.84915V5.15081C2.52979 5.01426 2.20325 4.74624 1.99393 4.39351C1.7846 4.04079 1.70577 3.62576 1.7712 3.22085C1.83663 2.81594 2.04216 2.44686 2.35192 2.17801C2.66169 1.90916 3.05602 1.75762 3.46611 1.74983C3.8762 1.74204 4.276 1.87849 4.59576 2.13537C4.91551 2.39226 5.13491 2.75326 5.21568 3.15539C5.29644 3.55753 5.23344 3.97525 5.03766 4.33567C4.84189 4.6961 4.52577 4.97633 4.14447 5.12748V5.12748ZM3.49989 4.08331C3.6546 4.08331 3.80297 4.02186 3.91237 3.91246C4.02176 3.80306 4.08322 3.65469 4.08322 3.49998C4.08322 3.34527 4.02176 3.1969 3.91237 3.0875C3.80297 2.97811 3.6546 2.91665 3.49989 2.91665C3.34518 2.91665 3.1968 2.97811 3.08741 3.0875C2.97801 3.1969 2.91655 3.34527 2.91655 3.49998C2.91655 3.65469 2.97801 3.80306 3.08741 3.91246C3.1968 4.02186 3.34518 4.08331 3.49989 4.08331ZM3.49989 11.0833C3.6546 11.0833 3.80297 11.0219 3.91237 10.9125C4.02176 10.8031 4.08322 10.6547 4.08322 10.5C4.08322 10.3453 4.02176 10.1969 3.91237 10.0875C3.80297 9.9781 3.6546 9.91665 3.49989 9.91665C3.34518 9.91665 3.1968 9.9781 3.08741 10.0875C2.97801 10.1969 2.91655 10.3453 2.91655 10.5C2.91655 10.6547 2.97801 10.8031 3.08741 10.9125C3.1968 11.0219 3.34518 11.0833 3.49989 11.0833ZM10.4999 11.0833C10.6546 11.0833 10.803 11.0219 10.9124 10.9125C11.0218 10.8031 11.0832 10.6547 11.0832 10.5C11.0832 10.3453 11.0218 10.1969 10.9124 10.0875C10.803 9.9781 10.6546 9.91665 10.4999 9.91665C10.3452 9.91665 10.1968 9.9781 10.0874 10.0875C9.97801 10.1969 9.91655 10.3453 9.91655 10.5C9.91655 10.6547 9.97801 10.8031 10.0874 10.9125C10.1968 11.0219 10.3452 11.0833 10.4999 11.0833Z"
                                        fill="currentColor"/>
                                </svg>
                                {{ $t('Merge Ticket') }}
                            </el-dropdown-item>
                            <el-dropdown-item command="close_silently">
                                <el-icon>
                                    <MuteNotification/>
                                </el-icon>
                                {{ $t('Close Ticket Silently') }}
                            </el-dropdown-item>
                        </div>
                        <el-dropdown-item v-if="appVars.fluent_boards" command="fluent_boards">
                            <img :src="appVars.asset_url + '/images/addTask.svg'" class="fs_add_task_logo">
                            {{ $t('Add Task to Fluent Boards') }}
                        </el-dropdown-item>
                        <el-dropdown-item command="delete">
                            <el-icon><Delete /></el-icon>
                            {{ $t('Delete Ticket') }}
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>
        </div>
    </Teleport>

    <!-- Position in the list the agent came from, with previous/next -->
    <Teleport to="#fs_top_bar_position_slot">
        <div v-if="listPosition" class="fs_ticket_position">
            <!-- translators: %1$s is the ticket's position in the list, %2$s is the number of tickets in the list -->
            <span class="fs_ticket_position_text">{{ $t('%1$s of %2$s', listPosition.number, listPosition.total) }}</span>
            <el-button v-tip.bottom="$t('Previous ticket')"
                class="fs_outline_btn fs_ticket_step_btn"
                :aria-label="$t('Previous ticket')"
                :disabled="!listPosition.hasPrev || steppingList"
                @click="$emit('step', -1)"
            >
                <el-icon><ArrowUp /></el-icon>
            </el-button>
            <el-button v-tip.bottom="$t('Next ticket')"
                class="fs_outline_btn fs_ticket_step_btn"
                :aria-label="$t('Next ticket')"
                :disabled="!listPosition.hasNext || steppingList"
                @click="$emit('step', 1)"
            >
                <el-icon><ArrowDown /></el-icon>
            </el-button>
        </div>
    </Teleport>
</template>

<script type="text/babel">
import WorkFlowSelector from './_WorkFlowSelector';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import { getGroupedAgentOptions } from '@/admin/Composable/agentGroupHelper';

/**
 * The ticket's action buttons, teleported into the top bar of TicketsView.vue:
 * refresh, bookmark, assign agent, run workflow, close or re-open, more actions,
 * and the position in the list with previous/next. Emits what the agent chose;
 * ViewTicket.vue does it.
 */
export default {
    name: 'TicketTopBar',
    components: { WorkFlowSelector, DynamicIcon },
    props: ['ticket', 'watchers', 'loading', 'updating', 'switching', 'canAssignAgents', 'canManageTickets', 'listPosition', 'steppingList'],
    emits: ['refresh', 'bookmark', 'assign', 'close', 'reopen', 'command', 'step'],
    data() {
        return {
            agents: this.appVars.support_agents
        };
    },
    computed: {
        agentGroups() {
            return this.appVars.agent_groups || [];
        },

        groupedAgentOptions() {
            return getGroupedAgentOptions(this.agents || [], this.agentGroups, this.$t('Other'));
        },

        assignedAgentGroupName() {
            if (!this.ticket.agent_id || !this.agentGroups.length) return '';
            const agentId = Number(this.ticket.agent_id);
            for (const group of this.agentGroups) {
                const groupAgentIds = (group.agent_ids || []).map(Number);
                if (groupAgentIds.includes(agentId)) {
                    return group.title;
                }
            }
            return '';
        }
    },
    methods: {
        onAgentPopoverShow() {
            this.$nextTick(() => {
                if (this.$refs.agentSelect) {
                    this.$refs.agentSelect.focus();
                    this.$refs.agentSelect.toggleMenu();
                }
            });
        }
    }
};
</script>
