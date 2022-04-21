<template>
    <div v-loading="loading" class="fcon_trigger_view">
        <div @click="action.is_open = !action.is_open" class="fcon_provider_info">
            <div class="fc_trigger_titles">
                <span class="fc_trigger_name">{{ actions[action.action_name].title }}</span>
            </div>
            <div class="fc_trigger_actions">
                <span class="fc_item_open"><el-icon><ArrowDown /></el-icon></span>
            </div>
        </div>
        <div class="fcon_trigger_details">
            <div v-if="action.is_open" class="fcon_trigger_editor">
                <h3>{{ $t('Action') }}</h3>
                <el-select @change="triggerEventChanged()" :placeholder="$t('Select Integration Event')"
                           v-model="action.action_name">
                    <el-option
                        v-for="(actionItem, triggerName) in actions"
                        :key="triggerName"
                        :value="triggerName"
                        :label="actionItem.title"
                    />
                </el-select>
                <div v-if="action.action_name && builder_ready">
                    <form-builder :fields="actions[action.action_name].fields" :form-data="action.settings">
                        <template v-slot:form_end>
                            <el-form-item label="Action Title">
                                <el-input type="text" placeholder="Action Title" v-model="action.title"></el-input>
                            </el-form-item>
                        </template>
                    </form-builder>


                    <div style="display: block; margin-top: 10px;">
                        <el-button @click="emitSave()" size="small" type="success">{{ $t('Save') }}</el-button>
                        <div style="text-align: right; display: inline-block; float: right;">
                            <el-popconfirm
                                :confirm-button-text="$t('Yes, Delete this Action')"
                                :cancel-button-text="$t('No')"
                                icon-color="red"
                                @confirm="deleteAction()"
                                :title="$t('delete_action_warning')"
                            >
                                <template #reference>
                                    <el-button type="danger" plain size="small" icon="Delete"></el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';

export default {
    name: 'ActionMap',
    props: ['action', 'actions'],
    components: {
        FormBuilder
    },
    data() {
        return {
            settings_fields: {},
            loading: false,
            builder_ready: true
        }
    },
    emits: ['update', 'deleteAction'],
    methods: {
        triggerEventChanged() {
            if (!this.action.action_name) {
                this.action.settings = {};
                return false;
            }
            this.builder_ready = false;
            const selectedAction = JSON.parse(JSON.stringify(this.actions[this.action.action_name]));
            this.action.settings = selectedAction.settings_defaults;
            this.action.title = selectedAction.title;
            setTimeout(() => {
                this.builder_ready = true;
            }, 100);
        },
        emitSave() {
            this.action.is_open = !this.action.is_open;
            this.$emit('update');
        },
        deleteAction() {
            this.action.is_open = !this.action.is_open;
            this.$emit('deleteAction');
        }
    },
    mounted() {
        if (!this.action.settings) {
            this.action.settings = {};
        }
    }
}
</script>
