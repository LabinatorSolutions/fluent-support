<template>
    <el-tabs v-model="activeName">
        <el-tab-pane :label="translate('Personal Reports')" name="my-reports" :lazy="true">
            <personal-reports :url="'my-reports'" />
        </el-tab-pane>
        <el-tab-pane v-if="me.permissions.indexOf('fst_sensitive_data') != -1" :label="translate('Agents Reports')" name="reports" :lazy="true">
            <reports/>
        </el-tab-pane>
    </el-tabs>
</template>

<script type="text/babel">
import Reports from "./Reports";
import PersonalReports from "./PersonalReports";
import {
    useFluentHelper,
} from "@/admin/Composable/FluentFrameworkHelper";
import { reactive, toRefs } from "vue";

export default {
    name: 'Report',
    components:{ Reports, PersonalReports },

    setup(){
        const {
            appVars,
            translate,
        } = useFluentHelper();

        const state = reactive({
            activeName: 'my-reports',
            me: appVars.me
        });

        return {
            ...toRefs(state),
            translate
        }
    }
}
</script>

