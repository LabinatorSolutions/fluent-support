<template>
    <div class="fs_edit_response">
        <wp-editor  @showSuggestion=showSuggestion v-model="response.content" />
        <hr />
        <el-button size="small" type="success" @click="editResponse()">{{$t('Update Response')}}</el-button>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'EditResponse',
    props: ['response'],
    data() {
        return {
            saving: false
        }
    },
    components: {
        WpEditor
    },
    emits: ['updated'],
    methods: {
        editResponse() {
            this.saving = true;
            this.$put(`tickets/${this.response.ticket_id}/responses/${this.response.id}`, {
                content: this.response.content
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right',
                        type: 'success'
                    });
                    this.$emit('updated', response.response);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
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
