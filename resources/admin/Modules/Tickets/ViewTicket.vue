<template>
    <div v-loading="loading" class="fs_view_ticket">
        <div class="fs_ticket_body">
            <div class="fs_ticket_actions">
                <ul class="fs_tk_actions">
                    <li @click="show_response_box = !show_response_box">
                        <i class="el-icon-chat-line-square" />
                    </li>
                    <li>
                        <i class="el-icon-notebook-1" />
                    </li>
                    <li>
                        <i class="el-icon-user" />
                    </li>
                    <li>
                        <i class="el-icon-s-flag" />
                    </li>
                    <li>
                        <i class="el-icon-price-tag" />
                    </li>
                    <li>
                        <i class="el-icon-position" />
                    </li>
                </ul>
            </div>
            <div class="fs_th_header">
                <hgroup>
                    <div class="fs_tk_subject">
                        <h2 title="fs_tk_edit_subject">{{ ticket.title }}</h2>
                        <div class="fs_tk_tags">
                            <span class="fs_badge">samples</span>
                            <span class="fs_badge">vip</span>
                        </div>
                    </div>
                    <div class="fs_tk_badges">
                        <span class="fs_ticket_id">#{{ ticket.id }}</span>
                        <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ ticket.status }}</span>
                    </div>
                </hgroup>
            </div>
            <create-response @created="recordNewResponse" v-if="show_response_box" :ticket="ticket" type="response" />
            <div v-if="ticket.id" class="fs_threads_container">
                <article v-for="conversation in conversations"
                         :key="conversation.id"
                         class="fs_thread"
                         :class="getTicketClasses(conversation)">
                    <div class="fs_thread_content">
                        <section class="fs_avatar">
                            <img v-if="conversation.person" :src="conversation.person.photo" :alt="conversation.person.full_name"/>
                        </section>
                        <section class="fs_thread_wrap">
                            <section class="fs_thread_message">
                                <div class="fs_thread_head">
                                    <div class="fs_thread_title">
                                        <strong v-if="conversation.person">{{ conversation.person.full_name }}</strong> replied
                                    </div>
                                    <div class="fs_thread_actions">
                                        {{ conversation.created_at }}
                                    </div>
                                </div>
                                <div v-html="conversation.content" class="fs_thread_body"></div>
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
                                        <strong>{{ ticket.customer.full_name }}</strong> started the conversation
                                    </div>
                                    <div class="fs_thread_actions">
                                        {{ ticket.created_at }}
                                    </div>
                                </div>
                                <div v-html="ticket.content" class="fs_thread_body"></div>
                            </section>
                        </section>
                    </div>
                </article>
            </div>
        </div>
        <div class="fs_ticket_sidebar">
            <ticket-sidebar :ticket_id="ticket_id" :ticket="ticket" />
        </div>
    </div>
</template>

<script type="text/babel">
import CreateResponse from './_CreateResponse';
import TicketSidebar from './_TicketSidebar';

export default {
    name: 'ViewTicket',
    props: ['ticket_id'],
    components: {
        CreateResponse,
        TicketSidebar
    },
    data() {
        return {
            loading: false,
            ticket: false,
            conversations: [],
            show_response_box: false
        }
    },
    methods: {
        fetchTicket() {
            this.loading = true;
            this.$get(`tickets/${this.ticket_id}`)
                .then(response => {
                    this.ticket = response.ticket;
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
        recordNewResponse(response, ticket) {
            this.conversations.unshift(response);
            this.ticket.status = ticket.status;
            this.show_response_box = false;
        }
    },
    mounted() {
        this.fetchTicket();
    }
}
</script>
