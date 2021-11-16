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
                                <i class="el-icon-chat-line-square"/>
                            </li>
                            <li :title="$t('Add Internal Note')"
                                class="fs_add_note"
                                :class="(show_response_box == 'note') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'note'">
                                <i class="el-icon-notebook-1"/>
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
                                    <i v-else class="el-icon-user"/>
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
                        <el-button v-loading="loading" @click="fetchTicket()" icon="el-icon-refresh"
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
                                <span style="margin-right: 10px;"><i class="el-icon-goods"></i> {{
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
                        <span class="fs_business_name"><i class="el-icon-office-building"></i> {{
                                ticket.mailbox?.name
                            }}</span>
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
                                        <i class="el-icon-user"></i> {{ ticket.client_priority }}</span>
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
                                          :class="'fs_badge_priority_'+ticket.priority" class="fs_badge"> <i
                                        class="el-icon-service"></i> {{ ticket.priority }}</span>
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
                                                  :title="$t('Added By Email')"><i class="el-icon-message"></i></span>
                                            <span :title="conversation.created_at">{{
                                                    $timeDiff(conversation.created_at)
                                                }}</span>
                                            <el-dropdown @command="handleResponseActionCommand" trigger="click">
                                                <span class="el-dropdown-link">
                                                    <i class="el-icon-arrow-down el-icon--right"></i>
                                                </span>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item
                                                            :command="{ type: 'edit', conversation: conversation }"
                                                            icon="el-icon-edit"> Edit
                                                        </el-dropdown-item>
                                                        <el-dropdown-item
                                                            :command="{ type: 'delete', conversation: conversation }"
                                                            icon="el-icon-delete"> Delete
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
                                                <i class="el-icon-paperclip"></i> <a target="_blank" rel="noopener"
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
                                            <strong>{{ ticket.customer?.full_name }}</strong> started the conversation
                                        </div>
                                        <div class="fs_thread_actions">
                                            <span style="margin-right: 5px" v-if="ticket.source == 'email'"
                                                  :title="$t('Added By Email')"><i class="el-icon-message"></i></span>
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
                                                <i class="el-icon-paperclip"></i> <a target="_blank" rel="noopener"
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
                                               icon="el-icon-edit" size="mini"></el-button>
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

export default {
    name: 'ViewTicket',
    props: ['ticket_id'],
    components: {
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
            fluentcrm_profile: false
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
                return 'You';
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
</style>
