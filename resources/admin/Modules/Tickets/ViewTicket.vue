<template>
    <div class="fs_view_ticket">
        <template v-if="ticket && ticket.id">
            <div class="fs_ticket_body">
                <div class="fs_ticket_actions">
                    <ul class="fs_tk_actions">
                        <template v-if="ticket.status != 'closed'">
                            <li :class="(show_response_box == 'response') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'response'">
                                <i class="el-icon-chat-line-square"/>
                            </li>
                            <li :class="(show_response_box == 'note') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'note'">
                                <i class="el-icon-notebook-1"/>
                            </li>
                        </template>
                        <li :title="'Assigned Agent ' + ticket.agent?.full_name">
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
                        <li>
                            <i class="el-icon-price-tag"/>
                        </li>
                        <li>
                            <i class="el-icon-position"/>
                        </li>
                    </ul>
                    <div class="fs_product">
                        <el-button v-loading="loading" @click="fetchTicket()" icon="el-icon-refresh"
                                   size="small"></el-button>
                        <el-button v-loading="updating" :disabled="updating" @click="closeTicket()"
                                   v-if="ticket.status != 'closed'" class="fs_close_btn" type="info" size="small">Close
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
                        <span><i
                            class="el-icon-office-building"></i> {{ ticket.mailbox?.name }}</span>
                    </div>
                </div>
                <div class="fs_th_header">
                    <hgroup>
                        <div class="fs_tk_subject">
                            <h2 title="Click to Edit Subject">
                                <el-popover
                                    placement="bottom"
                                    :width="400"
                                    trigger="click"
                                >
                                    <template #reference>
                                        <span><i class="el-icon-goods"></i> {{ ticket?.title }}</span>
                                    </template>

                                    <el-input @keyup.enter="updateTicketAttr('title')"
                                              v-model="ticket.title"></el-input>
                                    <p>Press enter to save</p>
                                </el-popover>
                            </h2>
                        </div>
                        <div class="fs_tk_badges">
                            <span class="fs_ticket_id">#{{ ticket.id }}</span>
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                    <span :class="'fs_badge_priority_'+ticket.client_priority" class="fs_badge"><i
                                        class="el-icon-s-flag"></i> {{ ticket.client_priority }}</span>
                                </template>

                                <el-select @change="updateTicketAttr('client_priority')" v-model="ticket.client_priority"
                                           size="small">
                                    <el-option

                                        v-for="(priorityLabel, priority) in client_priorities"
                                        :key="priority" :value="priority"
                                        :label="priorityLabel"></el-option>
                                </el-select>
                            </el-popover>
                            <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ ticket.status }}</span>
                        </div>
                    </hgroup>
                </div>
                <create-response
                    v-if="show_response_box && ticket.status != 'closed'"
                    @created="recordNewResponse"
                    :ticket="ticket"
                    @close="show_response_box = ''"
                    :type="show_response_box"
                />
                <div class="fs_create_response text-align-center" v-if="ticket.status == 'closed'">
                    <p>This ticket has been closed at {{ ticket.resolved_at }}
                        <span v-if="ticket.closed_by_person">
                            by <b>{{ getHumanName(ticket.closed_by_person) }}</b>
                        </span></p>
                    <el-button v-loading="updating" :disabled="updating" @click="reOpen()" type="info" size="small">
                        Reopen This ticket
                    </el-button>
                </div>
                <div v-if="ticket && ticket.id" class="fs_threads_container">
                    <article v-for="conversation in conversations"
                             :key="conversation.id"
                             class="fs_thread"
                             :class="getTicketClasses(conversation)">
                        <div class="fs_thread_content">
                            <section class="fs_avatar">
                                <img v-if="conversation.person" :src="conversation.person?.photo"
                                     :alt="conversation.person.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong v-if="conversation.person">
                                                {{ getHumanName(conversation.person) }}</strong>
                                            <span v-if="conversation.conversation_type == 'response'"> replied</span>
                                            <span v-else> added a note</span>
                                        </div>
                                        <div class="fs_thread_actions">
                                            <span style="margin-right: 5px" v-if="conversation.source == 'email'"
                                                  title="Added By Email"><i class="el-icon-message"></i></span>
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
                                                                                     :href="attachment.secureUrl">{{ attachment.title }}</a>
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
                                                  title="Added By Email"><i class="el-icon-message"></i></span>
                                            <span :title="ticket.created_at">{{ $timeDiff(ticket.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div v-html="santizeContent(ticket.content)" class="fs_thread_body"></div>
                                </section>
                            </section>
                        </div>
                    </article>
                </div>
            </div>
            <div class="fs_ticket_sidebar">
                <ticket-sidebar :ticket_id="ticket_id" :ticket="ticket"/>
            </div>
        </template>
        <div style="padding: 20px;" class="fs_ticket_body" v-else>
            <el-skeleton :rows="10" animated/>
        </div>
        <el-dialog
            title="Edit Response"
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
import ActiveAgents from './_active_agents';

export default {
    name: 'ViewTicket',
    props: ['ticket_id'],
    components: {
        CreateResponse,
        TicketSidebar,
        EditResponse,
        ActiveAgents
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
            editing_response: false
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
            this.$get(`tickets/${this.ticket_id}`)
                .then(response => {
                    this.ticket = response.ticket;
                    this.$setTitle(response.ticket.title);
                    this.conversations = response.responses;
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
                    this.$notify.success(response.message);

                    each(response.update_data, (data, key) => {
                        this.ticket[key] = data;
                    });

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
                    this.$notify.success(response.message);
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
                this.$confirm('This will permanently delete response. Continue?', 'Warning', {
                    confirmButtonText: 'Delete Response',
                    cancelButtonText: 'Cancel',
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
                        message: 'You can not edit responses when it is in close state',
                        position: 'bottom-right'
                    });
                    return false;
                }
                this.editing_response = conversation;
                this.edit_response_modal = true;
            }

            console.log(data);
        },
        santizeContent(content) {
            if (!content) {
               return content;
            }
            return content.replace(/\n\s*\n/g, '\n').replace(/\n\s*\n/g, '\n');
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
