<template>
    <div class="fcon_triggers_wrap">
        <div v-for="(action,actionIndex) in actions" :key="actionIndex" class="fc_trigger_map">
            <action-map @deleteAction="removeAction(actionIndex)" @update="triggerUpdate()" :action="action"
                        :actions="all_actions"/>
        </div>
        <action-adder v-show="show_adder" @success="appendAction" :all_actions="all_actions"/>
        <el-button style="margin-top: 30px;" type="info" @click="show_adder = true" v-if="!show_adder">
            {{ $t('Add Another Action') }}
        </el-button>
    </div>
</template>

<script type="text/babel">
import ActionMap from './_ActionMap.vue';
import ActionAdder from './_ActionAdder.vue';

export default {
    name: 'ActionMappers',
    props: ['workflow_id', 'actions', 'all_actions'],
    components: {
        ActionMap,
        ActionAdder
    },
    emits: ['update'],
    data() {
        return {
            show_adder: false
        }
    },
    methods: {
        appendAction(action) {
            this.show_adder = false;
            action.workflow_id = this.workflow_id;
            this.actions.push(action);
        },
        triggerUpdate() {
            this.$emit('update');
        },
        removeAction(actionIndex) {
            this.actions.splice(actionIndex, 1);
            this.$emit('update');
        }
    },
    mounted() {
        if (!this.actions.length) {
            this.show_adder = true;
        }
    }
}
</script>
