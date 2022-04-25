<template>
    <div class="fs_edit_response">
        <wp-editor v-if="editor_ready"  v-model="response.content" />
        <hr />
        <el-button size="large" type="success" @click="editResponse()">{{$t('Update Response')}}</el-button>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'EditResponse',
    props: ['response'],
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
        }
    }
}
</script>
