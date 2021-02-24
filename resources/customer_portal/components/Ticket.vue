<template>
    <div v-loading="fetching" class="fs_ticket">

        <div class="fs_tk_header">
            <div v-if="ticket" class="fs_th_header">
                <hgroup>
                    <div class="fs_tk_subject">
                        <h2 title="fs_tk_edit_subject">
                            <span class="fs_ticket_id">#{{ ticket.id }}</span>
                            {{ ticket.title }}
                        </h2>
                        <div class="fs_tk_tags">
                            <span v-if="ticket.product" class="fs_badge">{{ticket.product.title}}</span>
                            <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ ticket.status }}</span>
                        </div>
                    </div>
                    <div class="fs_tk_actions">
                        <el-button v-loading="loading" @click="fetchTicket()" icon="el-icon-refresh"
                                   size="small"></el-button>
                        <el-button @click="$router.push({ name: 'dashboard' })" size="small">All</el-button>
                        <el-button v-if="ticket.status != 'closed'" :disabled="updating" v-loading="updating" @click="closeTicket()" size="small"
                                   type="danger">Close Ticket
                        </el-button>
                    </div>
                </hgroup>
            </div>
        </div>
        <div class="fs_tk_body">
            <template v-if="ticket">
                <div style="text-align: center;" class="fst_reply_box" v-if="ticket.status == 'closed'">
                    <p>This ticket has been closed at {{ticket.resolved_at }}
                        <span v-if="ticket.closed_by_person">
                            by <b>{{getHumanName(ticket.closed_by_person)}}</b>
                        </span>
                    <br />If you still have related issues. Please reopen this ticket and reply</p>
                    <el-button :disabled="updating" v-loading="updating" @click="reOpen()" type="info" size="small">Reopen This ticket</el-button>
                </div>
                <inline-reply v-else @created="recordNewResponse" :ticket="ticket"/>
            </template>

            <div v-if="ticket && ticket.id" class="fs_threads_container">
                <article v-for="conversation in conversations"
                         :key="conversation.id"
                         class="fs_thread"
                         :class="getTicketClasses(conversation)">
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
                                            }}</strong> replied
                                    </div>
                                    <div class="fs_thread_actions">
                                        {{ conversation.created_at }}
                                    </div>
                                </div>
                                <div v-html="conversation.content" class="fs_thread_body"></div>

                                <div class="fst_file_lists" v-if="conversation.attachments.length">
                                    <ul>
                                        <li
                                            v-for="attachment in conversation.attachments"
                                            :key="attachment.file_hash"
                                        >
                                            <i class="el-icon-paperclip"></i> <a target="_blank" rel="noopener" :href="attachment.secureUrl">{{attachment.title}}</a>
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
                                        <strong>{{ getHumanName(ticket.customer) }}</strong> started the conversation
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

    </div>
</template>

<script type="text/babel">
import InlineReply from "./InlineReply";

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
            updating: false
        }
    },
    methods: {
        fetchTicket() {
            this.fetching = true;
            this.$get(`tickets/${this.ticket_id}`)
                .then(response => {
                    this.ticket = response.ticket;
                    this.conversations = response.responses;
                    this.signon_id = response.sign_on_id;
                })
                .catch((errors) => {
                    console.log(errors);
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
                return 'You';
            }

            return person.full_name;
        },
        recordNewResponse(response, ticket) {
            this.conversations.unshift(response);
            this.ticket.status = ticket.status;
        },
        closeTicket() {
            this.updating = true;
            this.$post(`tickets/${this.ticket_id}/close`)
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
            this.$post(`tickets/${this.ticket_id}/re-open`)
                .then(response => {
                    this.ticket.status = response.ticket.status;
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        }
    },
    mounted() {
        this.fetchTicket();
    }
}
</script>
