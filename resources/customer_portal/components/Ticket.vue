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
                                <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ $t(ticket.status) }}</span>
                            </div>
                        </div>
                        <div class="fs_tk_actions">
                            <el-button class="fs_refresh_button" v-loading="fetching" @click="fetchTicket()" icon="Refresh"
                                       size="small"></el-button>
                            <a class="el-button el-button--default el-button--small fs_all_button" :href="appVars.view_tickets_url">{{$t('All')}}</a>
                            <el-button class="fs_close_button" v-if="ticket.status != 'closed'" :disabled="updating" v-loading="updating"
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
                             :class="getTicketClasses(conversation, ticket) ">
                        <span :class="getRibbonClass(conversation, ticket)" >{{getTextByPerson(conversation, ticket)}}</span>

                        <div class="fs_thread_content">
                            <section class="fs_avatar" v-if="!['ticket_split_activity', 'ticket_merge_activity'].includes(conversation.conversation_type)">
                                <img v-if="conversation.person" :src="conversation.person.photo"
                                     :alt="conversation.person.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head" v-if="!['ticket_split_activity', 'ticket_merge_activity'].includes(conversation.conversation_type)">
                                        <div class="fs_thread_title">
                                            <strong v-if="conversation.person">{{
                                                    getHumanName(conversation.person)
                                                }}</strong> {{$t('replied')}}
                                        </div>
                                        <div class="fs_actions_container">
                                            <div class="fs_thread_actions">
                                                {{ conversation.created_at }}
                                            </div>

                                            <div v-if = "conversation.person.person_type === 'agent' && appVars.agent_feedback_rating == 'yes'" class="fs_agent_feedback_actions">
                                                <el-button-group>
                                                    <el-button
                                                        @click="submitAgentFeedback('like', conversation.id)"
                                                        size="small"
                                                        class="fs_refresh_button"
                                                    >
                                                        <svg :fill="conversation.agent_feedback === 'like' ? '#0000FF' : '#000000'" width="15px" height="15px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M19.017 31.992c-9.088 0-9.158-0.377-10.284-1.224-0.597-0.449-1.723-0.76-5.838-1.028-0.298-0.020-0.583-0.134-0.773-0.365-0.087-0.107-2.143-3.105-2.143-7.907 0-4.732 1.472-6.89 1.534-6.99 0.182-0.293 0.503-0.47 0.847-0.47 3.378 0 8.062-4.313 11.21-11.841 0.544-1.302 0.657-2.159 2.657-2.159 1.137 0 2.413 0.815 3.042 1.86 1.291 2.135 0.636 6.721 0.029 9.171 2.063-0.017 5.796-0.045 7.572-0.045 2.471 0 4.107 1.473 4.156 3.627 0.017 0.711-0.077 1.619-0.282 2.089 0.544 0.543 1.245 1.36 1.276 2.414 0.038 1.36-0.852 2.395-1.421 2.989 0.131 0.395 0.391 0.92 0.366 1.547-0.063 1.542-1.253 2.535-1.994 3.054 0.061 0.422 0.11 1.218-0.026 1.834-0.535 2.457-4.137 3.443-9.928 3.443zM3.426 27.712c3.584 0.297 5.5 0.698 6.51 1.459 0.782 0.589 0.662 0.822 9.081 0.822 2.568 0 7.59-0.107 7.976-1.87 0.153-0.705-0.59-1.398-0.593-1.403-0.203-0.501 0.023-1.089 0.518-1.305 0.008-0.004 2.005-0.719 2.050-1.835 0.030-0.713-0.46-1.142-0.471-1.16-0.291-0.452-0.185-1.072 0.257-1.38 0.005-0.004 1.299-0.788 1.267-1.857-0.024-0.849-1.143-1.447-1.177-1.466-0.25-0.143-0.432-0.39-0.489-0.674-0.056-0.282 0.007-0.579 0.183-0.808 0 0 0.509-0.808 0.49-1.566-0.037-1.623-1.782-1.674-2.156-1.674-2.523 0-9.001 0.025-9.001 0.025-0.349 0.002-0.652-0.164-0.84-0.443s-0.201-0.627-0.092-0.944c0.977-2.813 1.523-7.228 0.616-8.736-0.267-0.445-0.328-0.889-1.328-0.889-0.139 0-0.468 0.11-0.812 0.929-3.341 7.995-8.332 12.62-12.421 13.037-0.353 0.804-1.016 2.47-1.016 5.493 0 3.085 0.977 5.473 1.447 6.245z"></path>
                                                        </svg>
                                                    </el-button>
                                                    <el-button
                                                        @click="submitAgentFeedback('dislike', conversation.id)"
                                                        size="small"
                                                        class="fs_refresh_button"
                                                    >
                                                        <svg :fill="conversation.agent_feedback === 'dislike' ? '#0000FF' : '#000000'" width="15px" height="15px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M12.982 0.007c9.088 0 9.159 0.377 10.284 1.225 0.597 0.449 1.723 0.76 5.838 1.028 0.299 0.019 0.583 0.134 0.773 0.365 0.087 0.107 2.143 3.105 2.143 7.907 0 4.732-1.471 6.89-1.534 6.991-0.183 0.292-0.503 0.469-0.848 0.469-3.378 0-8.062 4.313-11.211 11.841-0.544 1.302-0.657 2.158-2.657 2.158-1.137 0-2.412-0.814-3.043-1.86-1.291-2.135-0.636-6.721-0.028-9.171-2.063 0.017-5.796 0.045-7.572 0.045-2.471 0-4.106-1.474-4.157-3.628-0.016-0.711 0.077-1.62 0.283-2.088-0.543-0.543-1.245-1.361-1.276-2.415-0.038-1.36 0.852-2.395 1.42-2.989-0.13-0.396-0.391-0.92-0.366-1.547 0.063-1.542 1.253-2.536 1.995-3.054-0.061-0.42-0.109-1.217 0.026-1.832 0.535-2.457 4.138-3.445 9.928-3.445zM28.575 4.289c-3.584-0.296-5.5-0.698-6.51-1.459-0.782-0.588-0.661-0.822-9.082-0.822-2.568 0-7.59 0.107-7.976 1.869-0.154 0.705 0.59 1.398 0.593 1.403 0.203 0.502-0.024 1.089-0.518 1.305-0.008 0.004-2.004 0.72-2.050 1.836-0.030 0.713 0.46 1.142 0.471 1.159 0.291 0.452 0.184 1.072-0.257 1.38-0.005 0.004-1.299 0.788-1.267 1.857 0.025 0.848 1.143 1.447 1.177 1.466 0.25 0.143 0.432 0.39 0.489 0.674 0.057 0.282-0.007 0.579-0.182 0.807 0 0-0.509 0.808-0.49 1.566 0.037 1.623 1.782 1.674 2.156 1.674 2.522 0 9.001-0.026 9.001-0.026 0.35-0.001 0.652 0.164 0.839 0.444s0.202 0.627 0.091 0.945c-0.976 2.814-1.522 7.227-0.616 8.735 0.267 0.445 0.328 0.889 1.328 0.889 0.139 0 0.468-0.11 0.812-0.93 3.343-7.994 8.334-12.619 12.423-13.036 0.352-0.804 1.015-2.47 1.015-5.493-0.001-3.085-0.979-5.472-1.449-6.245z"></path>
                                                        </svg>
                                                    </el-button>
                                                </el-button-group>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-html="purify(conversation.content)" class="fs_thread_body"></div>

                                    <div class="fst_file_lists"
                                         v-if="conversation.attachments && conversation.attachments.length">
                                        <ul>
                                            <li v-if="conversation.attachments.length"
                                                v-for="attachment in conversation.attachments"
                                                :key="attachment.file_hash"
                                            >
                                                <el-icon> <Paperclip /> </el-icon> <a target="_blank" rel="noopener"
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
                                            <strong>{{ getHumanName(ticket.customer) }}</strong> {{$t('conversation_started')}}
                                        </div>
                                        <div class="fs_thread_actions">
                                            {{ ticket.created_at }}
                                        </div>
                                    </div>
                                    <div v-html="purify(ticket.content)" class="fs_thread_body"></div>

                                    <div class="fst_file_lists" v-if="ticket.attachments && ticket.attachments.length">
                                        <ul>
                                            <li v-if="ticket.attachments.length"
                                                v-for="attachment in ticket.attachments"
                                                :key="attachment.file_hash"
                                            >
                                                <el-icon> <Paperclip /> </el-icon> <a target="_blank" rel="noopener"
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
                                    <span v-else v-html="purify(fieldValue)"></span>
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
        getTicketClasses(conversation, ticket) {
            const classes = [
                'fs_thread'
            ];

            if (conversation.person) {
                if (conversation.person.person_type === 'agent') {
                    classes.push('fs_person_agent');
                    classes.push('fs_agent');
                }
                else {
                    if (ticket.customer_id == conversation.person_id) {
                        classes.push('fs_person_customer');
                        classes.push('fs_customer');
                    } else {
                        classes.push('fs_person_customer_cc');
                        classes.push('fs_cc_customer');
                    }
                }
            }

            classes.push('fs_conv_type_' + conversation.conversation_type);
            return classes;
        },
        submitAgentFeedback(approvalStatus,conversationID){
            this.$post(`tickets/${conversationID}/agent-feedback`, {
                approvalStatus: approvalStatus,
            })
                .then(response => {
                    this.fetchTicket();
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.updating = false;
                });
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
        getRibbonClass(conversation, ticket){
            const classes = [
                'fs_thread_ribbon'
            ];

            if (conversation.person) {
                if (conversation.person.person_type === 'agent') {
                    classes.push('fs_thread_ribbon_agent');
                } else {
                    if (ticket.customer_id == conversation.person_id) {
                        classes.push('fs_thread_ribbon_customer');
                    } else {
                        classes.push('fs_thread_ribbon_customer_cc');
                    }
                }
            }
            return classes;
        },
        getTextByPerson(conversation, ticket){
            if (conversation?.person.person_type === 'agent') {
                return this.$t('Support Staff')
            }else{
                if (ticket.customer_id == conversation.person_id) {
                    return this.$t('Thread Starter')
                } else {
                    return this.$t('Thread Follower')
                }
            }
        },
        isArray,
        isEmpty,
        purify(string) {
            // check if this is type of string
            if (typeof string !== 'string') {
                return string;
            }

            const tagRegex = /<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>|<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi;
            string = string.replace(tagRegex, '');

            return window.DOMPurify.sanitize(string);
        }
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
    background: #1785EB;
    color: #fff;
    padding: 5px 10px;
    font-size: 11px;
}

.fs_agent {
    border-left: 4px solid #1785EB;
}

.fs_customer{
    border-left: 4px solid #15BE7C;
}

.fs_thread_ribbon{
    position: relative;
    left: 0;
    top: -6px;
    color: #fff;
    font-size: 10px;
    padding: 5px 10px;
}
.fs_thread_ribbon_agent{
    background: #1785EB;
}
.fs_thread_ribbon_customer{
    background: #15BE7C;
}
.fs_thread_ribbon_customer_cc{
    background: #EC5c03;
}
</style>
