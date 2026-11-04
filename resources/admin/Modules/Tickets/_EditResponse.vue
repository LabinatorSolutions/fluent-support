<template>
    <div class="fs_edit_response">
        <wp-editor
            v-if="editor_ready"
            v-model="response.content"
            :ticketId="resolvedTicketId"
            is_agent="yes"
        />

    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
export default {
    name: 'EditResponse',
    props: {
        response: {
            type: Object,
            required: true,
        },
        ticketId: {
            type: [String, Number],
            default() {
                return ''
            }
        },
    },
    components: {
        WpEditor,
    },
    data() {
        return {
            filteredAgents: this.appVars.support_agents,
            popup: false,
            editor_ready: true,
        };
    },

    computed: {
        resolvedTicketId() {
            return this.response.ticket_id || this.ticketId;
        },
    },

    methods: {
        editResponse() {
            const url = this.response.is_ticket_content
                ? `tickets/${this.resolvedTicketId}/content`
                : `tickets/${this.resolvedTicketId}/responses/${this.response.id}`;

            return this.$put(url, { content: this.response.content })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                    });

                    this.$emit("updated", response.response);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        }
    },
};
</script>
