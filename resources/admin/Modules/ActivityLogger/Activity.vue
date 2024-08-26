<template>
    <el-tabs type="border-card" class="fs_activity_menu" v-model="activeName">
        <el-tab-pane :label="translate('Overall Activities')" name="overall_activities" :lazy="true">
            <activity-logger></activity-logger>
        </el-tab-pane>
        <el-tab-pane v-if="ai_enabled" :label="translate('AI Activities')" name="ai_activities" :lazy="true">
            <AIActivityLogger></AIActivityLogger>
        </el-tab-pane>
    </el-tabs>
</template>

<script type="text/babel">
import ActivityLogger from "./ActivityLogger.vue";
import AIActivityLogger from "./AIActivityLogger.vue";
import {
    useFluentHelper,
} from "@/admin/Composable/FluentFrameworkHelper";
import {onMounted, reactive, toRefs} from "vue";
import dayjs from "dayjs";

export default {
    name: 'Activity',
    components:{
        ActivityLogger,
        AIActivityLogger,
    },

    setup(){
        const {
            appVars,
            translate,
            get
        } = useFluentHelper();

        const state = reactive({
            activeName: 'overall_activities',
            me: appVars.me,
            ai_enabled: appVars.ai_integration,
        });

        return {
            ...toRefs(state),
            translate,
            appVars,
        }
    }
}
</script>

<style scoped>

.fs_activity_menu {
    max-width: 1100px;
    margin: 0 auto;
    width: 100%;
}

</style>

