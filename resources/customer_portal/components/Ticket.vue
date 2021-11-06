<template>
    <div class="fs_ticket">
        <template v-if="ticket">
            <div class="fs_tk_header">
                <div class="fs_th_header">
                    <hgroup>
                        <div class="fs_tk_subject">
                            <h2 title="fs_tk_edit_subject">
                                <span class="fs_ticket_id">#{{ ticket.id }}</span>
                                {{ ticket.title }}
                            </h2>
                            <div class="fs_tk_tags">
                                <span v-if="ticket.product" class="fs_badge">{{ ticket.product.title }}</span>
                                <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ ticket.status }}</span>
                            </div>
                        </div>
                        <div class="fs_tk_actions">
                            <el-button v-loading="fetching" @click="fetchTicket()" icon="el-icon-refresh"
                                       size="small"></el-button>                            <a class="el-button el-button--default el-button--small" :href="appVars.view_tickets_url">All</a>
                            <el-button v-if="ticket.status != 'closed'" :disabled="updating" v-loading="updating"
                                       @click="closeTicket()" size="small"
                                       type="danger">{{$t('Close Ticket')}}
                            </el-button>
                        </div>
                    </hgroup>
                </div>
            </div>
            <div class="fs_tk_body">
                <div style="text-align: center;" class="fst_reply_box" v-if="ticket.status == 'closed'">
                    <p>{{$t('ticket_closed')}} {{ ticket.resolved_at }}
                        <span v-if="ticket.closed_by_person">
                            {{$t('by')}} <b>{{ getHumanName(ticket.closed_by_person) }}</b>
                        </span>
                        <br/>{{$t('reopen_ticket_instruction')}}</p>
                    <el-button :disabled="updating" v-loading="updating" @click="reOpen()" type="info" size="small">
                        {{$t('Reopen This ticket')}}
                    </el-button>
                </div>
                <inline-reply v-else @created="recordNewResponse" :ticket="ticket"/>

                <div class="fs_threads_container">
                    <article v-for="conversation in conversations"
                             :key="conversation.id"
                             class="fs_thread"
                             :class="(conversation.person.title!=='' && conversation.person.person_type !== 'customer') ? 'fs_agent' : getTicketClasses(conversation)">

                        <span class="agent_title"
                              v-if="conversation.person.title"> {{ conversation.person.title }} </span>

                        <div class="fs_thread_content">
                            <section class="fs_avatar">
                                <img v-if="conversation.person" :src="conversation.person.photo"
                                     :alt="conversation.person.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong v-if="conversation.person">{{
                                                    getHumanName(conversation.person)
                                                }}</strong> {{$t('replied')}}
                                        </div>
                                        <div class="fs_thread_actions">
                                            {{ conversation.created_at }}
                                        </div>
                                    </div>
                                    <div v-html="conversation.content" class="fs_thread_body"></div>

                                    <div class="fst_file_lists"
                                         v-if="conversation.attachments && conversation.attachments.length">
                                        <ul>
                                            <li v-if="conversation.attachments.length"
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
                                <img :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong>{{ getHumanName(ticket.customer) }}</strong> {{$t('started the conversation')}}
                                        </div>
                                        <div class="fs_thread_actions">
                                            {{ ticket.created_at }}
                                        </div>
                                    </div>
                                    <div v-html="ticket.content" class="fs_thread_body"></div>

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
                        </div>
                        <div v-if="!isEmpty(ticket.custom_fields)" class="fc_custom_data_wrap">
                            <h3>{{ $t('Additional info') }}</h3>
                            <ul>
                                <li v-for="(fieldValue, fieldName) in ticket.custom_fields" :key="fieldName">
                                    <b>{{ appVars.custom_fields[fieldName].label }}</b> :
                                    <span v-if="isArray(fieldValue)">
                                            <span class="fs_custom_check_value" v-for="value in fieldValue"
                                                  :key="value">{{ value }}</span>
                                        </span>
                                    <span v-else v-html="fieldValue"></span>
                                </li>
                            </ul>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <div style="padding: 20px; background: white; " class="fs_tk_body" v-else-if="!error_message">
            <el-skeleton :rows="5" animated/>
        </div>
        <div style="padding: 20px; text-align: center;" class="fs_tk_body" v-else-if="error_message">
            <p v-html="error_message"></p>
            <el-button type="primary" @click="$router.push({ name: 'dashboard' })" size="small">{{$t('View Your Tickets')}}
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
import InlineReply from "./InlineReply";
const isEmpty = require('lodash/isEmpty');
const isArray = require('lodash/isArray');

export default {
    name: 'ticket',
    props: ['ticket_id'],
    components: {
        InlineReply
    },
    data() {
        return {
            ticket: false,
            conversations: [],
            fetching: false,
            signon_id: '',
            updating: false,
            error_message: ''
        }
    },
    methods: {
        fetchTicket() {
            this.fetching = true;
            this.$get(`tickets/${this.ticket_id}`, {
                intended_ticket_hash: this.appVars.intended_ticket_hash
            })
                .then(response => {
                    this.ticket = response.ticket;
                    this.conversations = response.responses;
                    this.signon_id = response.sign_on_id;
                })
                .catch((errors) => {
                    let message = this.$t('Unknown error. Please reload this page');
                    if (errors.responseJSON?.errors?.message) {
                        message = errors.responseJSON?.errors?.message;
                    } else if (errors.responseJSON?.message) {
                        message = errors.responseJSON?.message;
                    }
                    this.error_message = message;
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        getTicketClasses(conversation) {
            const classes = [
                'fs_thread'
            ];

            if (conversation.person) {
                classes.push('fs_person_' + conversation.person.person_type);
            }

            if (conversation.person.id == this.signon_id) {
                classes.push('fs_person_own');
            }

            classes.push('fs_conv_type_' + conversation.conversation_type);

            return classes;
        },
        getHumanName(person) {
            if (this.signon_id == person.id) {
                return this.$t('You');
            }

            return person.full_name;
        },
        recordNewResponse(response, ticket) {
            this.conversations.unshift(response);
            this.ticket.status = ticket.status;
        },
        closeTicket() {
            this.updating = true;
            this.$post(`tickets/${this.ticket_id}/close`, {
                intended_ticket_hash: this.appVars.intended_ticket_hash
            })
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
        reOpen() {
            this.updating = true;
            this.$post(`tickets/${this.ticket_id}/re-open`, {
                intended_ticket_hash: this.appVars.intended_ticket_hash
            })
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
        isArray,
        isEmpty
    },
    mounted() {
        this.fetchTicket();
    }
}
</script>
<style scoped>
.agent_title {
    content: '';
    position: relative;
    left: 0;
    top: 0;
    background: #5d6cc3;
    color: #fff;
    padding: 5px 10px;
    font-size: 11px;
}

.fs_agent {
    border-left: 4px solid #5d6cc3;
}
</style>
