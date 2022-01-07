<template>
    <div class="fcon_add_trigger">
        <h3>{{$t('Select Action')}}</h3>
        <ul class="fcon_provider_selectors">
            <el-select @change="actionSuccess()" v-model="action.action_name">
                <el-option v-for="(action, actionName) in all_actions" :value="actionName"
                           :label="action.title"></el-option>
            </el-select>
        </ul>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ActionAdder',
    props: ['all_actions'],
    emits: ['success'],
    computed: {
        selectedProvider() {
            const selectedProviderKey = this.action.action_provider;
            if (!selectedProviderKey) {
                return false;
            }

            return this.all_actions[selectedProviderKey];
        }
    },
    data() {
        return {
            action: {
                title: '',
                action_name: '',
                settings: {}
            }
        }
    },
    methods: {
        actionSuccess() {
            if (this.action.action_name) {
                this.action.is_open = true;
                this.$emit('success', this.action);
                this.action = {
                    title: '',
                    action_name: '',
                    settings: {}
                };
            }
        }
    },
    mounted() {
    }
}
</script>
