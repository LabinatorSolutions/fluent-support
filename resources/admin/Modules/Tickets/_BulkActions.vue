<template>
    <div v-loading="working" class="fs_bulk_actions_wrap">
        <div v-if="ticket_selections.length" class="fs_bulk_actions_bar">
            <ul v-loading="working" class="fs_tk_actions">
                <li :title="$t('Add Reply')"
                    @click="add_response_modal = true">
                    <el-icon> <ChatLineSquare /> </el-icon>
                </li>
                <li :title="$t('Assign Agent ')">
                    <el-popover
                        placement="bottom"
                        :width="400"
                        trigger="manual"
                        v-model:visible="assignAgentPop"
                    >
                        <template #reference>
                            <el-icon :class="{'fs_pop_active': assignAgentPop}" @click="togglePop('assignAgentPop')">
                                <User />
                            </el-icon>
                        </template>

                        <div class="fs_extended_pop">
                            <el-select filterable v-model="agent_id">
                                <el-option
                                    v-for="agent in appVars.support_agents"
                                    :key="agent.id"
                                    :value="agent.id"
                                    :label="agent.full_name"></el-option>
                            </el-select>
                            <el-button @click="assignAgent()" style="margin-top: 10px;" :disabled="!agent_id"
                                       size="small"
                                       type="danger">
                                {{$t('Assign Agent')}}
                            </el-button>
                            <el-button @click="assignAgentPop = false" size="small" type="default" style="margin-top: 10px;">{{$t('Close')}}</el-button>
                        </div>
                    </el-popover>
                </li>
                <li title="Run Workflow">
                    <work-flow-selector @reloadTickets="fetchTickets()" :ticket_ids="ticket_selections"/>
                </li>
                <li title="Add Tag(s)">
                    <el-popover
                        placement="bottom"
                        :width="400"
                        trigger="manual"
                        v-model:visible="addTagPop"
                    >
                        <template #reference>
                            <el-icon @click="togglePop('addTagPop')" :class="{fs_pop_active: assignAgentPop}">
                                <PriceTag />
                            </el-icon>
                        </template>
                        <div class="fs_extended_pop">
                            <el-select :multiple="true" filterable v-model="tag_ids">
                                <el-option
                                    v-for="agent in appVars.ticket_tags"
                                    :key="agent.id"
                                    :value="agent.id"
                                    :label="agent.title"></el-option>
                            </el-select>
                            <el-button style="margin-top: 10px;" size="small" type="success"
                                       @click="assignTags()">Apply Tags
                            </el-button>
                            <el-button style="margin-top: 10px;" @click="addTagPop = false" size="small" type="default">{{$t('Close')}}</el-button>
                        </div>
                    </el-popover>
                </li>
                <li title="Close Tickets">
                    <el-popover
                        placement="bottom"
                        :width="400"
                        trigger="click"
                    >
                        <template #reference>
                            <el-icon @click="togglePop()"> <Finished /> </el-icon>
                        </template>
                        <p style="margin: 0 0 1em 0;">{{$t('close_selected_ticket_warning')}}</p>
                        <el-button style="margin-top: 0;" size="small" type="success"
                                   @click="closeTickets()">{{$t('Close Selected Tickets')}}
                        </el-button>
                    </el-popover>
                </li>
                <li title="Delete Tickets">
                    <el-popover
                        placement="bottom"
                        :width="400"
                        trigger="click"
                    >
                        <template #reference>
                            <el-icon @click="togglePop()"> <Delete /> </el-icon>
                        </template>
                        <p style="margin: 0 0 1em 0;">{{$t('delete_selected_ticket_warning')}}</p>
                        <el-button style="margin-top: 0;" size="small" type="danger"
                                   @click="deleteTickets()">{{$t('Yes, Delete Selected Tickets')}}
                        </el-button>
                    </el-popover>
                </li>
            </ul>
        </div>
        <el-dialog
            :title="$t('Reply To Selected Tickets')"
            v-model="add_response_modal"
            width="60%">
            <create-response @created="fetchTickets()" v-if="add_response_modal" :ticket="ticket_selections"/>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import WorkFlowSelector from './parts/_WorkFlowSelector';
import CreateResponse from "./_CreateResponse";

export default {
    name: 'TicketBulkActions',
    props: ['ticket_selections'],
    components: {
        WorkFlowSelector,
        CreateResponse
    },
    data() {
        return {
            agent_id: '',
            tag_ids: [],
            workflow_id: '',
            add_response_modal: false,
            working: false,
            assignAgentPop: false,
            addTagPop: false
        }
    },
    methods: {
        fetchTickets() {
            this.agent_id = '';
            this.tag_ids = '';
            this.workflow_id = '';
            this.addTagPop = false;
            this.assignAgentPop = false;
            this.add_response_modal = false;
            this.$emit('fetchTickets');
        },
        doBulkActions(data) {
            this.working = true;
            data.ticket_ids = this.ticket_selections;
            this.$post('tickets/bulk-actions', data)
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchTickets();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.working = false;
                });
        },
        assignAgent() {
            if (!this.agent_id) {
                this.$notify.error({
                    message: this.$t('Please select an agent first'),
                    position: 'bottom-right'
                });
                return false;
            }

            this.doBulkActions({
                bulk_action: 'assign_agent',
                agent_id: this.agent_id
            });
        },
        assignTags() {
            if (!this.tag_ids.length) {
                this.$notify.error({
                    message: this.$t('Please select tag first'),
                    position: 'bottom-right'
                });
                return false;
            }

            this.doBulkActions({
                bulk_action: 'assign_tags',
                tag_ids: this.tag_ids
            });
        },
        closeTickets() {
            this.doBulkActions({
                bulk_action: 'close_tickets'
            });
        },
        deleteTickets() {
            this.doBulkActions({
                bulk_action: 'delete_tickets'
            });
        },
        togglePop(pop) {
            if (pop && this[pop]) {
                this[pop] = false;
                return;
            }
            this.assignAgentPop = false;
            this.addTagPop = false;
            if (pop) {
                this[pop] = true;
            }
        }
    }
}
</script>
