<template>
    <el-tabs v-model="activeName">
        <el-tab-pane :label="translate('Personal Reports')" name="my-reports" :lazy="true">
            <personal-reports :url="'my-reports'" />
        </el-tab-pane>
        <el-tab-pane v-if="me.permissions.indexOf('fst_sensitive_data') != -1" :label="translate('Agents Reports')" name="reports" :lazy="true">
            <reports/>
        </el-tab-pane>
        <el-tab-pane v-if="me.permissions.indexOf('fst_sensitive_data') != -1" :label="translate('Products Reports')" name="product-reports" :lazy="true">
            <product-reports :url="'product-reports'"/>
        </el-tab-pane>
        <el-tab-pane v-if="me.permissions.indexOf('fst_sensitive_data') != -1" :label="translate('Business Boxes Reports')" name="mailbox-reports" :lazy="true">
            <business-box-reports :url="'mailbox-reports'"/>
        </el-tab-pane>
        <el-tab-pane v-if="me.permissions.indexOf('fst_sensitive_data') != -1" :label="translate('Feedback')" name="feedback-by-time-of-day" :lazy="true">
            <feedback-by-time-of-day :url="'feedback-by-time-of-day'"/>
        </el-tab-pane>
    </el-tabs>
</template>

<script type="text/babel">
import Reports from "./Reports";
import PersonalReports from "./PersonalReports";
import ProductReports from "./ProductReports";
import BusinessBoxReports from "./BusinessBoxReports";
import FeedbackByTimeOfDay from "./FeedbackByTimeOfDay";
import {
    useFluentHelper,
} from "@/admin/Composable/FluentFrameworkHelper";
import { reactive, toRefs } from "vue";

export default {
    name: 'Report',
    components:{
        Reports,
        PersonalReports,
        ProductReports,
        BusinessBoxReports,
        FeedbackByTimeOfDay
    },

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

