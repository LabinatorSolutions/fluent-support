<template>
    <div class="fs_edit_response">
        <wp-editor  @showSuggestion=showSuggestion v-if="editor_ready"  v-model="response.content" />
        <hr />
        <el-button size="large" type="success" @click="editResponse()">{{$t('Update Response')}}</el-button>

        <el-dialog v-model="popup" title="Support Stuff" width="18%" center top="45%">
          <el-form-item>
            <el-input v-model="agent_id" placeholder="Type to search"
                      @keyup="searchAgent" />
          </el-form-item>

          <div class="text item"
               v-for="agent in filteredAgents"
               :key="agent.id" @click="tagMe(agent)">{{ agent.full_name }}</div>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'EditResponse',
    props: ['response', 'type', 'conversation_type'],
    data() {
        return {
            saving: false,
            filteredAgents: this.appVars.support_agents,
            popup: false,
            editor_ready: true,
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
            if(this.type != 'response' && this.conversation_type == 'note') {
                this.replaceText = replaceText;
                this.popup = true;
            }
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
            this.response.content = this.response.content.replace(RegX, newText + '$1');
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
