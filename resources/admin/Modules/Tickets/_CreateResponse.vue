<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <div class="fc_template_box">
            <el-dropdown type="primary" trigger="click">
                <el-button size="small" type="primary" style="margin-right: .3em;">
                    {{$t('Shortcodes')}} <el-icon><ArrowDown /></el-icon>
                </el-button>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item v-for="(value ,key) in shortcodes" :value="key" @click="insertShortcode">
                            {{value}}
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>
            <template-inserter v-if="appVars.has_pro" @insert="insertTemplate"/>
        </div>
        <wp-editor :autofocus="true" @showSuggestion=showSuggestion v-if="editor_ready" v-model="response_body"/>
        <div class="fs_row">
            <div class="fs_half">
                <div style="text-align: left" class="fs_response_actions">
                    <el-button v-loading="creating" :disabled="creating" @click="create('no')" size="large" type="success">
                        <span v-if="type== 'note'">{{ $t('Add Internal Note') }}</span>
                        <span v-else>{{ $t('Add Reply') }}</span>
                    </el-button>
                    <el-button v-if="type != 'note'" :disabled="creating" @click="create('yes')" size="large" type="danger">
                        {{ $t('Reply and Close') }}
                    </el-button>
                    <p v-if="type== 'note'">{{ $t('internal_note_warning') }}</p>
                </div>
            </div>
            <div class="fs_half">
                <attachment-form v-if="appVars.has_file_upload" :ticket="ticket" :attachments="attachments"/>
            </div>
        </div>
    </div>

    <el-dialog v-model="popup" :title="$t('Support Staff')" width="18%" center top="45%">
        <el-form-item>
            <el-input v-model="agent_id" placeholder="Type to search"
                      @keyup="searchAgent" />
        </el-form-item>

        <div class="text item"
             v-for="agent in filteredAgents"
             :key="agent.id" @click="tagMe(agent)">{{ agent.full_name }}</div>
    </el-dialog>
</template>

<script type="text/babel">

import WpEditor from '../../Pieces/_wp_editor';
import AttachmentForm from './_AttachmentForm';
import TemplateInserter from './_templateInserter';

export default {
    name: 'CreateResponse',
    props: ['ticket', 'type'],
    components: {
        WpEditor,
        AttachmentForm,
        TemplateInserter
    },
    data() {
        return {
            agents: this.appVars.support_agents,
            filteredAgents: this.appVars.support_agents,
            replaceText: '',
            agent_id: '',
            popup: false,
            response_body: '',
            creating: false,
            close_ticket: 'no',
            attachments: [],
            editor_ready: true,
            shortcodes: {
                '{{customer.first_name}}' : 'Customer First Name',
                '{{customer.last_name}}' : 'Customer Last Name',
                '{{customer.full_name}}' : 'Customer Full Name',
                '{{customer.email}}' : 'Customer Email',
                '{{agent.first_name}}' : 'Agent First Name',
                '{{agent.last_name}}' : 'Agent Last Name',
                '{{agent.full_name}}' : 'Agent Full Name',
                '{{agent.email}}' : 'Agent Email',
            }
        }
    },
    methods: {
        create(closed = 'no') {

            const data = {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: closed,
                attachments: this.attachments
            };

            let action = `tickets/${this.ticket.id}/responses`;
            if (Array.isArray(this.ticket)) {
                data.ticket_ids = this.ticket;
                data.bulk_action = 'reply_tickets';
                action = 'tickets/bulk-reply';
            }

            this.creating = true;
            this.$post(action, data)
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.response_body = '';
                    this.$emit('created', response.response, response);
                    this.attachments = [];
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },
        insertTemplate(content) {
            this.editor_ready = false;
            this.response_body = this.response_body + content;
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        },
        insertShortcode(content) {
            this.editor_ready = false;
            this.response_body = this.response_body.concat(content.target._value);
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        },
        showSuggestion(replaceText){
            this.replaceText = replaceText;
            this.popup = true;
        },
        searchAgent(){
            this.filteredAgents = this.agents.filter(
                (data) => !this.agent_id || (this.agent_id && (data.full_name.toLowerCase().includes(this.agent_id.toLowerCase())))
            )
        },
        tagMe(agent){
            this.editor_ready = false;
            let newText = "@<a href='#"+agent.id+"'>"+agent.first_name+'-'+agent.last_name+"</a>";
            let RegX = new RegExp(this.replaceText+"([^"+this.replaceText+"]*)$");
            this.response_body = this.response_body.replace(RegX, newText + '$1');
            this.popup = false;
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        }
    }
}
</script>

<style scoped>
    .text {
        font-size: 14px;
    }

    .item {
        padding: 18px 0;
        cursor: pointer;
    }
</style>
