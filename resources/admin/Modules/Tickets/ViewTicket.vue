<template>
    <div class="fs_view_ticket">
        <template v-if="ticket && ticket.id">
            <div class="fs_ticket_body">
                <div class="fs_ticket_actions">
                    <ul class="fs_tk_actions">
                        <template v-if="ticket.status != 'closed'">
                            <li :title="$t('Add Reply')"
                                class="fs_add_reply"
                                :class="(show_response_box == 'response') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'response'">
                                <el-icon style="vertical-align: middle;"><chat-line-square /></el-icon>
                            </li>
                            <li :title="$t('Add Internal Note')"
                                class="fs_add_note"
                                :class="(show_response_box == 'note') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'note'">
                                <el-icon style="vertical-align: middle;"><notebook /></el-icon>
                            </li>
                        </template>

                        <li title="Run Workflow" class="fs_add_workflow"
                            v-if="appVars.manual_workflows && appVars.manual_workflows.length">
                            <work-flow-selector @reloadTickets="fetchTicket()" :ticket_ids="[ticket_id]"/>
                        </li>

                        <li :title="$t('Assigned Agent ') + ticket.agent?.full_name">
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                <span class="fs_agent_photo_icon" v-if="ticket.agent">
                                    <img :alt="ticket.agent?.full_name" :src="ticket.agent?.photo"/>
                                </span>
                                    <el-icon style="vertical-align: middle;" v-else><user /></el-icon>
                                </template>

                                <el-select filterable @change="updateTicketAttr('agent_id')" v-model="ticket.agent_id">
                                    <el-option
                                        v-for="agent in agents"
                                        :key="agent.id"
                                        :value="agent.id"
                                        :label="agent.full_name"></el-option>
                                </el-select>
                            </el-popover>
                        </li>

                    </ul>
                    <div class="fs_product">
                        <el-button v-loading="loading" @click="fetchTicket()"
                                   icon="Refresh"
                                   class="fs_refresh_tk_page"
                                   size="small"></el-button>
                        <el-button v-loading="updating" :disabled="updating" @click="closeTicket()"
                                   v-if="ticket.status != 'closed'" class="fs_close_btn" type="info" size="small">
                            {{ $t('Close') }}
                        </el-button>
                        <el-popover
                            placement="bottom"
                            :width="400"
                            trigger="click"
                        >
                            <template #reference>
                                <span style="margin-right: 10px;"><el-icon style="vertical-align: middle;"><goods /></el-icon> {{
                                        ticket.product?.title
                                    }}</span>
                            </template>

                            <el-select @change="updateTicketAttr('product_id')" v-model="ticket.product_id">
                                <el-option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="product.id"
                                    :label="product.title"></el-option>
                            </el-select>

                        </el-popover>

                        <el-popover
                            placement="bottom"
                            :width="400"
                            trigger="click"
                            v-if="appVars.me.permissions.includes('fst_manage_settings')"
                        >
                            <template #reference>
                                <span class="fs_business_name" style="margin-right: 10px;">
                                    <el-icon style="vertical-align: middle;"><office-building /></el-icon> {{ticket.mailbox?.name}}
                                </span>
                            </template>
                            <el-select @change="changeMailbox" v-model="ticket.mailbox.name">
                                <el-option
                                    v-for="mailbox in mailboxes"
                                    :key="mailbox.id"
                                    :value="mailbox.id"
                                    :label="mailbox.name"></el-option>
                            </el-select>
                        </el-popover>

                        <span v-else class="fs_business_name" style="margin-right: 10px;">
                            <el-icon style="vertical-align: middle;"><office-building /></el-icon> {{ticket.mailbox?.name}}
                        </span>

                        <el-dropdown style="vertical-align: middle;">
                            <span class="el-dropdown-link">
                              <el-icon style="transform: rotate(90deg)"><More /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item @click='(show_merge_modal=true) && (customerTickets())'>
                                        <i class="dashicons dashicons-randomize"></i> Merge Ticket
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>

                        <el-dialog
                            v-model="show_merge_modal"
                            v-if="show_merge_modal"
                            title="Merge Tickets"
                        >
                            <div class="fs_box_body">
                                <el-table :data="customer_tickets" style="width: 100%">
                                    <el-table-column prop="id" label="ID" width="130"></el-table-column>
                                    <el-table-column prop="title" label="Title" width="250"></el-table-column>
                                    <el-table-column prop="status" label="Status" width="130"></el-table-column>
                                    <el-table-column label="Action">
                                        <template #default="scope">
                                            <el-button size="small" type="primary" @click="mergeTickets(scope.row.id)">
                                                Merge
                                            </el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <div style="padding-bottom: 20px;" class="fframe_pagination_wrapper">
                                    <pagination @fetch="customerTickets" :pagination="pagination" />
                                </div>
                            </div>
                        </el-dialog>
                    </div>
                </div>
                <div class="fs_th_header">
                    <div class="fs_header_group">
                        <div class="fs_tk_subject">
                            <h2 :title="$t('Click to Edit Subject')">
                                <el-popover
                                    placement="bottom"
                                    :width="400"
                                    trigger="click"
                                >
                                    <template #reference>
                                        <span> {{ ticket?.title }}</span>
                                    </template>

                                    <el-input @keyup.enter="updateTicketAttr('title')"
                                              v-model="ticket.title"></el-input>
                                    <p>{{ $t('Press enter to save') }}</p>
                                </el-popover>
                            </h2>
                            <ticket-tags :creatable="true" :ticket_id="ticket.id" :tags.sync="ticket.tags"/>
                        </div>
                        <div class="fs_tk_badges">
                            <span class="fs_ticket_id">#{{ ticket.id }} </span>
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                    <span :title="$t('Client Priority: ') + ticket.client_priority "
                                          :class="'fs_badge_priority_'+ticket.client_priority" class="fs_badge">
                                        <el-icon style="vertical-align: middle;"><user /></el-icon> {{ ticket.client_priority }}</span>
                                </template>

                                <el-select @change="updateTicketAttr('client_priority')"
                                           v-model="ticket.client_priority"
                                           size="small">
                                    <el-option

                                        v-for="(priorityLabel, priority) in client_priorities"
                                        :key="priority" :value="priority"
                                        :label="priorityLabel"></el-option>
                                </el-select>
                            </el-popover>
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                    <span :title="$t('Admin Priority:') + ticket.priority "
                                          :class="'fs_badge_priority_'+ticket.priority" class="fs_badge">
                                        <el-icon style="vertical-align: middle;"><service /></el-icon> {{ ticket.priority }}</span>
                                </template>

                                <el-select @change="updateTicketAttr('priority')" v-model="ticket.priority"
                                           size="small">
                                    <el-option

                                        v-for="(priorityLabel, priority) in admin_priorities"
                                        :key="priority" :value="priority"
                                        :label="priorityLabel"></el-option>
                                </el-select>
                            </el-popover>
                            <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ ticket.status }}</span>
                        </div>
                    </div>
                </div>
                <create-response
                    v-if="show_response_box && ticket.status != 'closed'"
                    @created="recordNewResponse"
                    :ticket="ticket"
                    @close="show_response_box = ''"
                    :type="show_response_box"
                />
                <div class="fs_create_response text-align-center" v-if="ticket.status == 'closed'">
                    <p>{{ $t('ticket_closed') }} {{ ticket.resolved_at }}
                        <span v-if="ticket.closed_by_person">
                            by <b>{{ getHumanName(ticket.closed_by_person) }}</b>
                        </span></p>
                    <el-button v-loading="updating" :disabled="updating" @click="reOpen()" type="info" size="small">
                        {{ $t('Reopen This ticket') }}
                    </el-button>
                </div>
                <div v-if="ticket && ticket.id" class="fs_threads_container">
                    <article v-for="conversation in conversations"
                             :key="conversation.id"
                             class="fs_thread"
                             :class="(conversation.person.title && conversation.person.person_type != 'customer' ) ? 'fs_agent fs_conv_type_'+conversation.conversation_type : getTicketClasses(conversation) ">

                        <span class="agent_title"
                              v-if="conversation.person.title"> {{ conversation.person.title }} </span>

                        <div class="fs_thread_content">
                            <section class="fs_avatar">
                                <img v-if="conversation.person" :src="conversation.person?.photo"
                                     :alt="conversation.person.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong v-if="conversation.person">{{
                                                    getHumanName(conversation.person)
                                                }}</strong>&nbsp;
                                            <span v-if="conversation.conversation_type == 'response'"> {{
                                                    $t('replied')
                                                }}</span>
                                            <span v-else-if="conversation.conversation_type == 'note'"> {{
                                                    $t('added a note')
                                                }}</span>
                                        </div>
                                        <div class="fs_thread_actions">
                                            <span style="margin-right: 5px" v-if="conversation.source == 'email'"
                                                  :title="$t('Added By Email')"><el-icon style="vertical-align: middle;"><message /></el-icon></span>
                                            <span :title="conversation.created_at">{{
                                                    $timeDiff(conversation.created_at)
                                                }}</span>
                                            <el-dropdown @command="handleResponseActionCommand" trigger="click">
                                                <span class="el-dropdown-link">
                                                    <el-icon><arrow-down /></el-icon>
                                                </span>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item
                                                            :command="{ type: 'edit', conversation: conversation }"
                                                            icon="EditPen"> {{$t('Edit')}}
                                                        </el-dropdown-item>
                                                        <el-dropdown-item
                                                            :command="{ type: 'delete', conversation: conversation }"
                                                            icon="Delete"> {{$t('Delete')}}
                                                        </el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </div>
                                    </div>
                                    <div v-html="santizeContent(conversation.content)" class="fs_thread_body"></div>

                                    <div class="fst_file_lists" v-if="conversation.attachments?.length">
                                        <hr/>
                                        <ul>
                                            <li
                                                v-for="attachment in conversation.attachments"
                                                :key="attachment.file_hash"
                                            >
                                                <el-icon style="vertical-align: middle;"><paperclip /></el-icon> <a target="_blank" rel="noopener"
                                                                                     :href="attachment.secureUrl">{{
                                                    attachment.title
                                                }}</a>
                                            </li>
                                        </ul>
                                    </div>
                                </section>
                            </section>
                        </div>
                    </article>
                    <article class="fs_thread conversion_starter">
                        <div class="fs_thread_content">
                            <section class="fs_avatar">
                                <img :src="ticket.customer?.photo" :alt="ticket.customer?.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong>{{ ticket.customer?.full_name }}</strong> {{$t('started the conversation')}}
                                        </div>
                                        <div class="fs_thread_actions">
                                            <span style="margin-right: 5px" v-if="ticket.source == 'email'"
                                                  :title="$t('Added By Email')"><el-icon style="vertical-align: middle;"><message /></el-icon></span>
                                            <span :title="ticket.created_at">{{ $timeDiff(ticket.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div v-html="santizeContent(ticket.content)" class="fs_thread_body"></div>

                                    <div class="fst_file_lists" v-if="ticket.attachments && ticket.attachments.length">
                                        <ul>
                                            <li v-if="ticket.attachments.length"
                                                v-for="attachment in ticket.attachments"
                                                :key="attachment.file_hash"
                                            >
                                                <el-icon style="vertical-align: middle;"><paperclip /></el-icon> <a target="_blank" rel="noopener"
                                                                                     :href="attachment.secureUrl">{{
                                                    attachment.title
                                                }}</a>
                                            </li>
                                        </ul>
                                    </div>
                                </section>
                            </section>
                            <div v-if="has_pro && !isEmpty(appVars.custom_fields)" class="fc_custom_data_wrap">
                                <h3>{{ $t('Additional Data') }}
                                    <el-button @click="showCustomDataEditForm = !showCustomDataEditForm" type="text"
                                               icon="EditPen" size="small"></el-button>
                                </h3>
                                <ul v-if="!isEmpty(ticket.custom_fields)">
                                    <li v-for="(fieldValue, fieldName) in ticket.custom_fields" :key="fieldName">
                                        <b>{{ appVars.custom_fields[fieldName].label }}</b> :
                                        <span v-if="isArray(fieldValue)">
                                            <span class="fs_custom_check_value" v-for="value in fieldValue"
                                                  :key="value">{{ value }}</span>
                                        </span>
                                        <span v-else v-html="fieldValue"></span>
                                    </li>
                                </ul>
                                <p v-else>{{$t('No additional data found')}}</p>
                            </div>
                        </div>
                        <el-dialog
                            :title="$t('Updating Custom Field Data')"
                            v-model="showCustomDataEditForm"
                            v-if="showCustomDataEditForm"
                            width="60%">
                            <custom-field-form @syncData="syncCustomData" :ticket_id="ticket_id"
                                               :custom_data="ticket.custom_fields"/>
                        </el-dialog>

                    </article>
                </div>
            </div>
            <div class="fs_ticket_sidebar">
                <ticket-sidebar :fluentcrm_profile="fluentcrm_profile" :ticket_id="ticket_id" :ticket="ticket"/>
            </div>
        </template>
        <template v-else>
            <div class="fs_ticket_body">
                <div style="padding: 15px;">
                    <el-skeleton :rows="10" animated/>
                </div>
            </div>
            <div style="margin-left: 20px;" class="fs_ticket_sidebar">
                <el-skeleton style="background: white;padding: 20px; margin-bottom: 20px; box-sizing: border-box;"
                             :rows="3" animated/>
                <el-skeleton style="background: white;padding: 20px; margin-bottom: 20px; box-sizing: border-box;"
                             :rows="3" animated/>
                <el-skeleton style="background: white;padding: 20px; margin-bottom: 20px; box-sizing: border-box;"
                             :rows="3" animated/>
            </div>
        </template>

        <el-dialog
            :title="$t('Edit Response')"
            v-model="edit_response_modal"
            width="60%">
            <edit-response @updated="edit_response_modal = false; editing_response = false" v-if="editing_response"
                           :response="editing_response"/>
        </el-dialog>
        <active-agents :ticket="ticket" v-if="ticket && ticket.id"/>
    </div>
</template>

<script type="text/babel">
import CreateResponse from './_CreateResponse';
import EditResponse from './_EditResponse';
import TicketSidebar from './_TicketSidebar';
import each from 'lodash/each';
import isEmpty from 'lodash/isEmpty';
import isArray from 'lodash/isArray';
import ActiveAgents from './_active_agents';
import TicketTags from './parts/_Tags';
import CustomFieldForm from './parts/_CustomFieldForm';
import WorkFlowSelector from './parts/_WorkFlowSelector';
import Pagination from "../../Pieces/Pagination";

export default {
    name: 'ViewTicket',
    props: ['ticket_id'],
    components: {
        Pagination,
        CreateResponse,
        TicketSidebar,
        EditResponse,
        ActiveAgents,
        TicketTags,
        CustomFieldForm,
        WorkFlowSelector
    },
    data() {
        return {
            loading: false,
            ticket: false,
            conversations: [],
            show_response_box: '',
            products: this.appVars.support_products,
            agents: this.appVars.support_agents,
            admin_priorities: this.appVars.admin_priorities,
            client_priorities: this.appVars.client_priorities,
            updating: false,
            active_agents: [],
            edit_response_modal: false,
            editing_response: false,
            showCustomDataEditForm: false,
            fluentcrm_profile: false,
            mailboxes: this.appVars.mailboxes,
            customer_tickets: [],
            show_merge_modal: false,
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 10
            },
        }
    },
    watch: {
        '$route.params.ticket_id'(ticketId) {
            if (ticketId) {
                this.doAction('ticket_view_exit', this.ticket.id);
                this.ticket = false;
                this.$nextTick(() => {
                    this.doAction('ticket_view_entered', ticketId);
                    this.fetchTicket();
                });
            }
        }
    },
    methods: {
        fetchTicket() {
            this.loading = true;
            this.$get(`tickets/${this.ticket_id}`, {
                with_data: ['fluentcrm_profile']
            })
                .then(response => {
                    this.ticket = response.ticket;
                    this.$setTitle(response.ticket.title);
                    this.conversations = response.responses;
                    if (this.appVars.fluentcrm_config) {
                        this.fluentcrm_profile = response.fluentcrm_profile;
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        getTicketClasses(conversation) {
            const classes = [
                'fs_thread'
            ];

            if (conversation.person) {
                classes.push('fs_person_' + conversation.person.person_type);
            }

            classes.push('fs_conv_type_' + conversation.conversation_type);
            return classes;
        },
        recordNewResponse(response, data) {
            this.conversations.unshift(response);
            this.ticket.status = data.ticket.status;
            this.show_response_box = false;

            each(data.update_data, (data, key) => {
                this.ticket[key] = data;
            });

            if (this.appVars.pref.go_back_after_reply == 'yes') {
                if (window.history.state.back) {
                    this.$router.go(-1);
                }
            }
        },
        updateTicketAttr(propName) {
            this.$put(`tickets/${this.ticket.id}/property`, {
                prop_name: propName,
                prop_value: this.ticket[propName]
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
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
        getHumanName(person) {
            if (this.appVars.me?.id == person.id) {
                return this.$t('You');
            }

            return person.full_name;
        },
        closeTicket() {
            this.updating = true;
            this.$post(`tickets/${this.ticket.id}/close`)
                .then(response => {
                    this.ticket.status = response.ticket.status;
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    if (window.history.state.back) {
                        this.$router.go(-1);
                    }
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        },
        reOpen() {
            this.updating = true;
            this.$post(`tickets/${this.ticket.id}/re-open`)
                .then(response => {
                    this.ticket.status = response.ticket.status;
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        },
        onActivityChange(items) {
            const personIds = [];
            items.forEach((item) => {
                personIds.push(item.val());
            });
            this.active_agents = personIds;
        },
        onTicketDataChange(item) {
            let data = item.val();
            this.ticket[data.type] = data.data;
        },
        handleResponseActionCommand(data) {
            const actionType = data.type;
            const conversation = data.conversation;

            if (actionType == 'delete') {
                this.$confirm(this.$t('response_delete_warning'), 'Warning', {
                    confirmButtonText: this.$t('Delete Response'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning'
                }).then(() => {
                    this.$del(`tickets/${this.ticket.id}/responses/${conversation.id}`)
                        .then(response => {
                            this.$notify.success({
                                message: response.message,
                                position: 'bottom-right',
                                type: 'success'
                            });
                            this.fetchTicket();
                        })
                });
            } else if (actionType == 'edit') {
                if (this.ticket.status == 'closed') {
                    this.$notify({
                        type: 'error',
                        message: this.$t('error_msg_on_closed_ticket_edit'),
                        position: 'bottom-right'
                    });
                    return false;
                }
                this.editing_response = conversation;
                this.edit_response_modal = true;
            }
        },
        changeMailbox(mailbox) {
            this.loading != this.loading;
            this.$put(`mailboxes/${this.ticket.mailbox_id}/move_tickets`, {
                new_box_id: mailbox,
                ticket_ids: [this.ticket_id],
                move_type: 'Custom',
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
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
        customerTickets(){
            this.$get('tickets', {
                search: 'customer_id:'+this.ticket.customer_id,
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
            })
                .then(response => {
                    this.customer_tickets = response.tickets.data;
                    this.pagination.total = response.tickets.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                })
        },
        mergeTickets(ticketToMerge){
            this.$confirm('Are you sure you want to merge these tickets?', 'Merge Tickets', {
                confirmButtonText: 'Merge',
                cancelButtonText: 'Cancel',
                type: 'warning'
            }).then(() => {
                this.loading = true;
                this.$post('tickets/' + this.ticket_id +'/merge_tickets', {
                    ticket_to_merge: ticketToMerge,
                })
                    .then(response => {
                        this.$notify.success({
                            message: response.message,
                            position: 'bottom-right'
                        });
                        this.customerTickets();
                        this.fetchTicket();
                    })
                    .catch((error) => {
                        this.$handleError(error);
                    })
                    .always(() => {
                        this.loading = false;
                    });
            });
        },
        santizeContent(content) {
            if (!content) {
                return content;
            }
            return content.replace(/\n\s*\n/g, '\n').replace(/\n\s*\n/g, '\n');
        },
        isEmpty,
        isArray,
        syncCustomData(data) {
            this.ticket.custom_fields = data;
            this.showCustomDataEditForm = false;
        }
    },
    mounted() {
        this.fetchTicket();
        this.doAction('ticket_view_entered', this.ticket_id, this);
    },
    beforeUnmount() {
        this.doAction('ticket_view_exit', this.ticket_id);
    }
}
</script>

<style scoped>
.agent_title {
    content: '';
    position: relative;
    left: 0;
    top: 0;
    background: #1785EB;
    color: #fff;
    padding: 5px 10px;
    font-size: 11px;
}

.fs_agent {
    border-left: 4px solid #1785EB;
}

.fs_conv_type_note {
    border-left: 0px solid #e6a23c;
}
i.dashicons.dashicons-randomize {
    transform: rotate(90deg);
}
</style>
