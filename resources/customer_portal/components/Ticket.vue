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
                                                    >
                                                        <svg v-if="conversation.agent_feedback === 'like'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30 30" fill="none">
                                                            <path d="M20.2694 16.265L20.9749 12.1852C21.1511 11.1662 20.3675 10.2342 19.3345 10.2342H14.1534C13.6399 10.2342 13.2489 9.77328 13.332 9.26598L13.9947 5.22142C14.1024 4.56435 14.0716 3.892 13.9044 3.24752C13.7659 2.71364 13.354 2.28495 12.8123 2.11093L12.6673 2.06435C12.3399 1.95918 11.9826 1.98365 11.6739 2.13239C11.3342 2.29611 11.0856 2.59473 10.9935 2.94989L10.5178 4.78374C10.3664 5.36723 10.146 5.93045 9.8617 6.46262C9.44634 7.24017 8.80416 7.86246 8.13663 8.43769L6.69789 9.67749C6.29223 10.0271 6.07919 10.5506 6.12535 11.0844L6.93752 20.4771C7.01201 21.3386 7.73231 22 8.59609 22H13.2447C16.726 22 19.697 19.5744 20.2694 16.265Z" fill="#1C274C"/>
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.96767 9.48508C3.36893 9.46777 3.71261 9.76963 3.74721 10.1698L4.71881 21.4063C4.78122 22.1281 4.21268 22.7502 3.48671 22.7502C2.80289 22.7502 2.25 22.1954 2.25 21.5129V10.2344C2.25 9.83275 2.5664 9.5024 2.96767 9.48508Z" fill="#1C274C"/>
                                                        </svg>
                                                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30 30" fill="none">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12.4382 2.77841C12.2931 2.73181 12.1345 2.74311 11.9998 2.80804C11.8523 2.87913 11.7548 3.0032 11.7197 3.13821L11.244 4.97206C11.0777 5.61339 10.8354 6.23198 10.5235 6.81599C10.0392 7.72267 9.30632 8.42 8.62647 9.00585L7.18773 10.2456C6.96475 10.4378 6.8474 10.7258 6.87282 11.0198L7.68498 20.4125C7.72601 20.887 8.12244 21.25 8.59635 21.25H13.245C16.3813 21.25 19.0238 19.0677 19.5306 16.1371L20.2361 12.0574C20.3332 11.4959 19.9014 10.9842 19.3348 10.9842H14.1537C13.1766 10.9842 12.4344 10.1076 12.5921 9.14471L13.2548 5.10015C13.3456 4.54613 13.3197 3.97923 13.1787 3.43584C13.1072 3.16009 12.8896 2.92342 12.5832 2.82498L12.4382 2.77841L12.6676 2.06435L12.4382 2.77841ZM11.3486 1.45674C11.8312 1.2242 12.3873 1.18654 12.897 1.35029L13.042 1.39686L12.8126 2.11092L13.042 1.39686C13.819 1.64648 14.4252 2.26719 14.6307 3.0592C14.8241 3.80477 14.8596 4.58256 14.7351 5.34268L14.0724 9.38724C14.0639 9.439 14.1038 9.4842 14.1537 9.4842H19.3348C20.8341 9.4842 21.9695 10.8365 21.7142 12.313L21.0087 16.3928C20.3708 20.081 17.0712 22.75 13.245 22.75H8.59635C7.3427 22.75 6.29852 21.7902 6.19056 20.5417L5.3784 11.149C5.31149 10.3753 5.62022 9.61631 6.20855 9.10933L7.64729 7.86954C8.3025 7.30492 8.85404 6.75767 9.20042 6.10924C9.45699 5.62892 9.65573 5.12107 9.79208 4.59542L10.2678 2.76157C10.417 2.18627 10.8166 1.71309 11.3486 1.45674ZM2.96767 9.4849C3.36893 9.46758 3.71261 9.76945 3.74721 10.1696L4.71881 21.4061C4.78122 22.1279 4.21268 22.75 3.48671 22.75C2.80289 22.75 2.25 22.1953 2.25 21.5127V10.2342C2.25 9.83256 2.5664 9.50221 2.96767 9.4849Z" fill="#1C274C"/>
                                                        </svg>
                                                    </el-button>
                                                    <el-button
                                                        @click="submitAgentFeedback('dislike', conversation.id)"
                                                        size="small"
                                                        class="fs_refresh_button"
                                                    >
                                                        <svg v-if="conversation.agent_feedback === 'dislike'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30 30" fill="none">
                                                            <path d="M20.2694 8.48505L20.9749 12.5648C21.1511 13.5838 20.3675 14.5158 19.3345 14.5158H14.1534C13.6399 14.5158 13.2489 14.9767 13.332 15.484L13.9947 19.5286C14.1024 20.1857 14.0716 20.858 13.9044 21.5025C13.7659 22.0364 13.354 22.465 12.8123 22.6391L12.6673 22.6856C12.3399 22.7908 11.9826 22.7663 11.6739 22.6176C11.3342 22.4539 11.0856 22.1553 10.9935 21.8001L10.5178 19.9663C10.3664 19.3828 10.146 18.8195 9.8617 18.2874C9.44634 17.5098 8.80416 16.8875 8.13663 16.3123L6.69789 15.0725C6.29223 14.7229 6.07919 14.1994 6.12535 13.6656L6.93752 4.27293C7.01201 3.41139 7.73231 2.75 8.59609 2.75H13.2447C16.726 2.75 19.697 5.17561 20.2694 8.48505Z" fill="#1C274C"/>
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.96767 15.2651C3.36893 15.2824 3.71261 14.9806 3.74721 14.5804L4.71881 3.34389C4.78122 2.6221 4.21268 2 3.48671 2C2.80289 2 2.25 2.55474 2.25 3.23726V14.5158C2.25 14.9174 2.5664 15.2478 2.96767 15.2651Z" fill="#1C274C"/>
                                                        </svg>
                                                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30 30" fill="none">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12.4382 21.2216C12.2931 21.2682 12.1345 21.2569 11.9998 21.192C11.8523 21.1209 11.7548 20.9968 11.7197 20.8618L11.244 19.0279C11.0777 18.3866 10.8354 17.768 10.5235 17.184C10.0392 16.2773 9.30632 15.58 8.62647 14.9942L7.18773 13.7544C6.96475 13.5622 6.8474 13.2742 6.87282 12.9802L7.68498 3.58754C7.72601 3.11303 8.12244 2.75 8.59635 2.75H13.245C16.3813 2.75 19.0238 4.93226 19.5306 7.86285L20.2361 11.9426C20.3332 12.5041 19.9014 13.0158 19.3348 13.0158H14.1537C13.1766 13.0158 12.4344 13.8924 12.5921 14.8553L13.2548 18.8998C13.3456 19.4539 13.3197 20.0208 13.1787 20.5642C13.1072 20.8399 12.8896 21.0766 12.5832 21.175L12.4382 21.2216L12.6676 21.9356L12.4382 21.2216ZM11.3486 22.5433C11.8312 22.7758 12.3873 22.8135 12.897 22.6497L13.042 22.6031L12.8126 21.8891L13.042 22.6031C13.819 22.3535 14.4252 21.7328 14.6307 20.9408C14.8241 20.1952 14.8596 19.4174 14.7351 18.6573L14.0724 14.6128C14.0639 14.561 14.1038 14.5158 14.1537 14.5158H19.3348C20.8341 14.5158 21.9695 13.1635 21.7142 11.687L21.0087 7.60725C20.3708 3.91896 17.0712 1.25 13.245 1.25H8.59635C7.3427 1.25 6.29852 2.20975 6.19056 3.45832L5.3784 12.851C5.31149 13.6247 5.62022 14.3837 6.20855 14.8907L7.64729 16.1305C8.3025 16.6951 8.85404 17.2423 9.20042 17.8908C9.45699 18.3711 9.65573 18.8789 9.79208 19.4046L10.2678 21.2384C10.417 21.8137 10.8166 22.2869 11.3486 22.5433ZM2.96767 14.5151C3.36893 14.5324 3.71261 14.2306 3.74721 13.8304L4.71881 2.59389C4.78122 1.8721 4.21268 1.25 3.48671 1.25C2.80289 1.25 2.25 1.80474 2.25 2.48726V13.7658C2.25 14.1674 2.5664 14.4978 2.96767 14.5151Z" fill="#1C274C"/>
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
