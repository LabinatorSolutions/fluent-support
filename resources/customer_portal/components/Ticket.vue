<template>
    <div class="fs_ticket_wrapper fs_ticket">
        <!-- Back Button -->
        <div class="fs_back_nav">
            <el-button link class="fs_back_button" @click="$router.push({ name: 'dashboard' })">
                <svg class="fs_svg_back" width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.2045 4.99907L5.917 8.71157L4.8565 9.77207L0.0834961 4.99907L4.8565 0.226074L5.917 1.28657L2.2045 4.99907Z" fill="#0E121B"/>
                </svg>
                <span class="fs_back_btn">Back to All Tickets</span>
            </el-button>
        </div>
        <div class="fs_tickets_container">
            <template v-if="ticket">
                <div class="fs_ticket_heroarea">
                    <!-- <div class="fs_ticket_header"> -->
                        <div class="fs_tk_subject">
                            <h2 class="fs_ticket_subject">
                                #{{ ticket.id }} {{ ticket.title }}
                            </h2>
                            <div class="fs_status_badge" :class="['fs_status_badge_' + (ticket.status === 'closed' ? 'closed' : 'active')]">
                                <span class="fs_status_dot" :class="['fs_status_dot_' + (ticket.status === 'closed' ? 'closed' : 'active')]"></span> 
                                <span v-if="ticket.status === 'closed'">Closed</span>
                                <span v-else>Active</span>
                            </div>
                        </div>
                        <div class="fs_ticket_actions">
                            <div class="fs_product_name">
                                <svg v-if="ticket.product" width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.9001 16.2995V4.32953C1.90004 4.14478 1.95684 3.96449 2.06279 3.81314C2.16873 3.66179 2.31869 3.54671 2.4923 3.48353L11.1962 0.319127C11.2642 0.294383 11.3371 0.286397 11.4089 0.295847C11.4806 0.305298 11.549 0.331906 11.6083 0.373415C11.6675 0.414925 11.7159 0.470112 11.7493 0.534297C11.7827 0.598483 11.8001 0.669774 11.8001 0.742127V5.19983L17.4845 7.09433C17.6638 7.15404 17.8197 7.26867 17.9302 7.42197C18.0407 7.57527 18.1001 7.75946 18.1001 7.94843V16.2995H19.9001V18.0995H0.100098V16.2995H1.9001ZM3.7001 16.2995H10.0001V2.66903L3.7001 4.96043V16.2995ZM16.3001 16.2995V8.59733L11.8001 7.09703V16.2995H16.3001Z" fill="#525866"/>
                                </svg>
                                <p v-if="ticket.product">{{ ticket.product.title }}</p>
                            </div>
                            <div v-if="ticket.status != 'closed'" class="fs_ticket_actions_btn">
                                <div v-loading="fetching" class="fs_ticket_refresh_btn" @click="fetchTicket()">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3.09725 2.32476C4.45817 1.1455 6.19924 0.497495 8 0.500007C12.1423 0.500007 15.5 3.85776 15.5 8.00001C15.5 9.60201 14.9975 11.087 14.1425 12.305L11.75 8.00001H14C14.0001 6.82373 13.6544 5.67336 13.006 4.69195C12.3576 3.71054 11.4349 2.94138 10.3529 2.4801C9.27082 2.01882 8.07704 1.88578 6.91997 2.09752C5.7629 2.30926 4.69359 2.85643 3.845 3.67101L3.09725 2.32476ZM12.9028 13.6753C11.5418 14.8545 9.80076 15.5025 8 15.5C3.85775 15.5 0.5 12.1423 0.5 8.00001C0.5 6.39801 1.0025 4.91301 1.8575 3.69501L4.25 8.00001H2C1.9999 9.17629 2.34556 10.3267 2.994 11.3081C3.64244 12.2895 4.56505 13.0586 5.64712 13.5199C6.72918 13.9812 7.92296 14.1142 9.08003 13.9025C10.2371 13.6908 11.3064 13.1436 12.155 12.329L12.9028 13.6753Z" fill="#525866"/>
                                    </svg>
                                    <span>Refresh</span>
                                </div>

                                <div class="fs_close_ticket" :disabled="updating" v-loading="updating" @click="closeTicket()">
                                    <span class="fs_close_ticket_title">{{$t('Close Ticket')}}</span>
                                </div>
                            </div>
                        </div>
                    <!-- </div> -->
                    <div class="fs_ticket_response">
                        <div class="fs_ticket_alert">
                            <el-alert
                                v-if="ticket.privacy === 'private' && ticket.status !== 'closed'"
                                show-icon
                                :title="`${$t('This ticket is')} ${$t('Private')}. ${$t('agent_and_officials_can_see')}`"
                                type="info"
                            />
                            <el-alert
                                v-if="ticket.privacy === 'public' && ticket.status !== 'closed'"
                                show-icon
                                :title="`${$t('This ticket is')} ${$t('Public')}. ${$t('not_to_share_private_info')}`"
                                type="info"
                            />

                            <div  v-if="ticket.status === 'closed'" class="fs_ticket_closed_alert">
                                <span class="fs_ticket_closed_content">
                                    <svg width="22" height="22" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6 12C2.6862 12 0 9.3138 0 6C0 2.6862 2.6862 0 6 0C9.3138 0 12 2.6862 12 6C12 9.3138 9.3138 12 6 12ZM5.4 5.4V9H6.6V5.4H5.4ZM5.4 3V4.2H6.6V3H5.4Z" fill="#335CFF"/>
                                    </svg>

                                    <p>{{$t('ticket_closed')}} {{ ticket.resolved_at }}
                                        <span v-if="ticket.closed_by_person">
                                            {{$t('by')}} {{ getHumanName(ticket.closed_by_person) }}
                                        </span>
                                        {{$t('reopen_ticket_instruction')}}
                                    </p>
                                </span>
                                
                                <a class="fs_ticket_reopen" :disabled="updating" v-loading="updating" @click="reOpen()">
                                    {{$t('Reopen This ticket')}}
                                </a>
                            </div>

                        </div>

                        <inline-reply v-if="ticket.status !== 'closed'" @created="recordNewResponse" :ticket="ticket"/>
                    </div>
                </div>
                
                <div class="fs_ticket_body">
                    <div class="fs_ticket_threads_container" id="fs_ticket_threads_container">
                        <article v-for="conversation in conversations"
                                :key="conversation.id"
                                class="fs_customer_conversation"
                                :class="getTicketClasses(conversation, ticket) ">
                            
                            <div class="fs_ticket_thread_content">
                                <section class="fs_ticket_avatar" v-if="!['ticket_split_activity', 'ticket_merge_activity'].includes(conversation.conversation_type)">
                                    <img v-if="conversation.person" :src="conversation.person.photo"
                                        :alt="conversation.person.full_name"/>
                                </section>
                                <section class="fs_ticket_thread_wrap">
                                    <div class="fs_thread_message">
                                        <div class="fs_thread_head" v-if="!['ticket_split_activity', 'ticket_merge_activity'].includes(conversation.conversation_type)">
                                            <div class="fs_thread_name">
                                                <strong v-if="conversation.person">{{
                                                        getHumanName(conversation.person)
                                                    }}</strong> <span :class="getRibbonClass(conversation, ticket)" >{{getTextByPerson(conversation, ticket)}}</span>
                                            </div>
                                        </div>
                                        <div v-html="purify(conversation.content)" class="fs_thread_body"></div>

                                        <div class="fs_actions_head">
                                                <div class="fs_thread_actions">
                                                    {{ conversation.human_date }}
                                                </div>
                                                <div v-if = "appVars.has_pro && conversation.person.person_type === 'agent' && appVars.agent_feedback_rating == 'yes'" class="fs_agent_feedback_actions">
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
                                    </div>
                                </section>
                            </div>
                        </article>
                        <article class="fs_ticket_thread fs_conversion_starter" ref="conversionStarter">
                            <div class="fs_conversion_starter_section">
                                <div class="fs_ticket_thread_content fs_starter_thread_content">
                                    <section class="fs_ticket_avatar">
                                        <img :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                                    </section>
                                    <section class="fs_ticket_thread_wrap">
                                        <section class="fs_thread_message">
                                            <div class="fs_thread_head">
                                                <div class="fs_thread_name">
                                                    <strong>{{ getHumanName(ticket.customer) }}</strong> {{$t('conversation_started')}}
                                                </div>
                                            </div>
                                            <div v-html="purify(ticket.content)" class="fs_thread_body"></div>
                                                
                                            <div class="fs_actions_head">
                                                <div class="fs_thread_actions">
                                                    {{ ticket.human_date }}
                                                </div>
                                            </div>

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
                                <div v-if="!isEmpty(ticket.custom_fields)" class="fs_custom_data_wrap">
                                    <h3>{{ $t('Additional info') }}</h3>
                                    <ul>
                                        <li v-for="(fieldValue, fieldName) in ticket.custom_fields" :key="fieldName">
                                            <b class="fs_custom_info_label">{{ appVars.custom_fields[fieldName].label }}</b> :
                                            <span v-if="isArray(fieldValue)">
                                                    <span class="fs_custom_check_value" v-for="value in fieldValue"
                                                        :key="value">{{ value }}</span>
                                                </span>
                                            <span v-else v-html="purify(fieldValue)"></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </template>

            <div style="padding: 20px; background: white; " class="fs_ticket_body" v-else-if="!error_message">
                <el-skeleton :rows="5" animated/>
            </div>
            <div style="padding: 20px; text-align: center;" class="fs_ticket_body" v-else-if="error_message">
                <p v-html="error_message"></p>
                <el-button type="primary" @click="$router.push({ name: 'dashboard' })" size="small">{{$t('View Your Tickets')}}
                </el-button>
            </div>
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
            error_message: '',
            isSticky: false
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
                'fs_ticket_thread'
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
            const config = {
                ADD_ATTR: ['target'],
            };

            return window.DOMPurify.sanitize(string, config);
        }
    },
    mounted() {
        this.fetchTicket();
    }
}
</script>
<style lang="scss">
#fluent_support_client_app {
    * {
        box-sizing: border-box;
    }
}
.fs_back_nav{
    margin-bottom: 20px;
}
.fs_svg_back{
    margin: 16px;
    top: 5.23px;
    left: 7.08px;
    color: #0E121B;
}
.fs_back_btn {
    font-weight: 500;
    font-size: 14px;
    line-height: 20px;
    letter-spacing: -0.6%;
    color: #0E121B;
}
.fs_ticket_wrapper {
    padding: 20px;
    background: #f8fafc;
    min-height: 100vh;

    .fs_tickets_container {
        border-radius: 16px;
        border: 1px solid var(--stroke-soft-200, #E1E4EA);
        box-shadow: none;
        // max-width: 1200px;
        margin: 0 auto;
        .fs_ticket_heroarea{
            &.sticky-header {
                position: sticky;
                top: 0;
                z-index: 10; 
                background: #fff;
            }
            .fs_ticket_header {
                // padding: 16px 20px;
                border-bottom: 1px solid #E5E7EB;
                position: relative;
                background: white; /* Ensure the header has a background */
                transition: top 0.3s ease; /* Smooth transition for sticky behavior */
                @media (max-width: 665px) {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                }
            }
            .fs_ticket_actions {
                    display: flex; 
                    align-items: center;
                    justify-content: space-between;
                    margin: 0;
                    right: 0;
                    top: 1px;
                    gap: 8px;
                    padding: 20px;
                    flex: none;
                    border-top: 1px solid #E1E4EA;
                    border-bottom: 1px solid #E1E4EA;
                    @media (max-width: 665px) {
                        flex-wrap: wrap;
                    }
                    .fs_product_name {
                        display: flex;
                        align-items: center;
                        gap: 4px;
                        p {
                            display: inline;
                            margin: 0;
                            font-weight: 400;
                            font-size: 14px;
                            line-height: 20px;
                            color: var(--text-sub-600, #525866);
                        } 
                    }
                    .fs_ticket_actions_btn {
                        display: flex;
                        gap: 8px;
                    }
                    .fs_tk_thread{
                        border-radius: 8px;
                        padding: 8px;
                        flex: none;
                        gap: 4px;
                        border: 1px solid #E1E4EA;
                        align-items: center;
                        display: flex;
                        justify-content: center;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        font-size: 14px;
                        line-height: 20px;
                        color: var(--text-sub-600, #525866);
                        cursor: pointer;
                    }
                    .fs_ticket_refresh_btn{
                        border: 1px solid #E1E4EA;
                        align-items: center;
                        display: flex;
                        justify-content: center;
                        border-radius: 8px;
                        padding: 8px;
                        height: 36px;
                        gap: 4px;
                        cursor: pointer;
                        span {
                            font-weight: 500;
                            font-size: 14px;
                            line-height: 20px;
                            letter-spacing: -0.6%;
                            color: #525866;
                        }
                    }
                    .fs_close_ticket{
                        background: var(--bg-weak-50, #F5F7FA);
                        color: var(--text-sub-600, #525866);
                        gap: 8px;
                        padding: 8px;
                        flex: none;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 14px;
                        line-height: 20px;
                        border-radius: 8px;
                        cursor: pointer;
                    //    .fs_close_btn_checkbox .el-checkbox__inner{
                    //        width: 16px;
                    //        height: 16px;
                    //        left: 2px;
                    //        border-radius: 4px;
                    //        border: 2px solid #E1E4EA;
                    //    }
                    .fs_close_ticket_title{
                        font-weight: 500;
                        size: 14px;
                        line-height: 20px;
                    }
                    }
                }
                .fs_ticket_number {
                    font-size: 20px;
                    color: #000000;
                    font-weight: 500;
                    line-height: 28px;
                    display: block;
                    margin-right: 4px;
                }
                .fs_ticket_subject{
                    word-wrap: break-word;
                    color: #314351;
                    display: inline-block;
                    font-size: 20px;
                    font-weight: 500;
                    line-height: 28px;
                    margin: 0;
                    color: #000000;
                    font-weight: 500;
                    font-size: 18px;
                    line-height: 24px;
                }
                .fs_tk_subject {
                    padding: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 2px;
                    .fs_status_badge {
                        &.fs_status_badge_active {
                            background-color: #EEFBF6;
                            color: #23A682;
                        }
                        &.fs_status_badge_closed {
                            background: #F2F5F8;
                            color: #717784;
                        }
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 4px 8px;
                        border-radius: 15px;
                        font-size: 12px;
                        font-weight: 500;
                        line-height: 16px;
                        text-align: left;
                        color: rgb(82, 88, 102);
                        margin-left: 5px;
                        
                        .fs_status_dot {
                            &.fs_status_dot_active {
                                background-color: #1FC16B;
                            }
                            &.fs_status_dot_closed {
                                background: #717784;
                            }
                            color: rgb(31, 193, 107);
                            width: 6px;
                            height: 6px;
                            border-radius: 50%;
                            background: currentColor;
                        }
                    }
                }
            
                label {
                    font-size: 20px;
                    font-weight: 500;
                    line-height: 28px;
                    text-align: left;
                    text-underline-position: from-font;
                    text-decoration-skip-ink: none;
                    margin: 0;
                    color: #111827;
                }
        }

        
    }
    
}
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
.fs_thread_name{
    color: #0E121B;
    font-weight: 500;
    font-size: 14px;
    line-height: 20px;
    display: inline-block;
}

// .fs_agent {
//     border-left: 4px solid #1785EB;
// }

// .fs_customer{
//     border-left: 4px solid #15BE7C;
// }

.fs_thread_ribbon_agent{
    background: #C0D5FF;
    color: #122368;
    border-radius: 2rem;
}
.fs_thread_ribbon_customer_cc{
    background: #EC5c03;
}
.fs_thread_body p{
    margin: 0;
}
.fs_ticket_threads_container {
    border-top: 1px solid var(--stroke-soft-200, #E1E4EA);
    .fs_customer_conversation{
        padding: 20px 16px;
    }
    .fs_ticket_thread {
        border-bottom: 1px solid #E1E4EA;
        // padding: 12px 0;
        position: relative;
        &:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }
        &.fs_conversion_starter {
            .fs_conversion_starter_section{
                padding: 8px;
                background: #F9FAFB;
            }
            .fs_ticket_thread_content {
                padding-bottom: 12px;

            }
            .fs_starter_thread_content{
                margin: 16px;
            }
        }
        .fs_ticket_thread_content {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            .fs_ticket_avatar {
                overflow: hidden;
                border-radius: 50%;
                width: 32px;
                height: 32px;
                flex: none;
                img {
                    display: block;
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
            }
            .fs_ticket_thread_wrap {
                .fs_thread_head {
                    .fs_thread_name {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        font-weight: 500;
                        font-size: 14px;
                        line-height: 20px;
                        color: var(--text-strong-950, #0E121B);
                        strong {
                            font-weight: 500;
                        }
                        .fs_thread_ribbon {
                            display: block;
                            position: relative;
                            left: 0;
                            color: var(--state-information-dark, #122368);
                            padding: 2px 8px;
                            font-weight: 500;
                            font-size: 12px;
                            line-height: 16px;
                            border-radius: 30px;
                            &.fs_thread_ribbon_customer {
                                color: var(--state-faded-dark, #222530);
                                background: #E1E4EA;
                            }
                        }
                    }
                }
                .fs_thread_body {
                    margin: 8px 0 12px 0;
                    p {
                        font-weight: 400;
                        font-size: 14px;
                        line-height: 20px;
                        color: var(--text-strong-950, #0E121B);
                        margin: 0;
                    }
                }
                .fs_actions_head {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                    .fs_thread_actions {
                        font-weight: 400;
                        font-size: 12px;
                        line-height: 16px;
                        display: block;
                        color: var(--text-sub-600, #525866);
                    }
                    .fs_agent_feedback_actions {
                        color: #a5b2bd;
                        font-size: 12px;
                        .el-button-group {
                            display: flex;
                            align-items: center;
                            .el-button {
                                margin: 0;
                                padding: 0;
                                border: none;
                                height: auto;
                                border-right: 1px solid #E1E4EA;
                                padding-right: 8px;
                                margin-right: 8px;
                                &:last-child {
                                    border-right: none;
                                }
                                &:hover {
                                    background: none;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
.fs_ticket_alert {
    padding: 20px 16px;
    .fs_ticket_closed_alert{
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        background: #EBF1FF;
        border-radius: 8px;
        .fs_ticket_closed_content{
            display: flex;
            align-items: center;
            width: 400px;
            gap: 8px;
        }
        .fs_ticket_reopen{
            font-weight: 500;
            font-size: 12px;
            line-height: 16px;
            text-decoration: underline;
            color: #0E121B;
            cursor: pointer;
        }
        p {
            font-weight: 400;
            font-size: 12px;
            line-height: 16px;
            margin: 0;
        }
    }
    .el-alert {
        background: var(--state-information-lighter, #EBF1FF);
        font-weight: 400;
        font-size: 12px;
        line-height: 16px;
        color: var(--text-strong-950, #0E121B);
        padding: 8px 10px;
        border-radius: 8px;
        .el-icon {
            color: var(--state-information-base, #335CFF);
        }
        .el-alert__close-btn {
            color: var(--icon-strong-950, #0E121B);
            opacity: .4;
        }
    }
}
.fs_custom_data_wrap {
    h3 {
        font-weight: 500;
        font-size: 14px;
        line-height: 20px;
        letter-spacing: -0.6%;
        margin-top: 0;
        color: var(--text-strong-950, #0E121B);
    }
    margin: 8px;
    padding: 16px;
    border-top: 1px solid #e5e9ec;
    background: #F2F5F8;
    border-radius: 12px;
    .fs_custom_info_label {
        font-weight: 400;
        font-size: 14px;
        line-height: 20px;
        letter-spacing: -0.6%;
        color: #525866;
    }

    > ul {
        display: block;
        margin: 0;
        padding: 0;

        li {
            font-size: 14px;
            color: #0E121B;
            list-style: none;
            margin-bottom: 10px;
        }
    }

    span.fs_custom_check_value {
        font-weight: 400;
        font-size: 14px;
        line-height: 20px;
        letter-spacing: -0.6%;
        color: #0E121B;
    }
}
</style>