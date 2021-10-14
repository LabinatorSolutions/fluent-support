<template>
    <el-tabs v-model="activeName">
        <el-tab-pane label="Personal Reports" name="my-reports" :lazy="true">
            <personal-reports :url="'my-reports'" />
        </el-tab-pane>
        <el-tab-pane v-if="me.permissions.includes('fst_sensitive_data')" label="Agents Reports" name="reports" :lazy="true">
            <reports/>
        </el-tab-pane>
    </el-tabs>
</template>

<script type="text/babel">
import Reports from "./Reports";
import PersonalReports from "./PersonalReports";

export default {
    name: 'Report',
    components:{ Reports, PersonalReports },
    data() {
        return {
            activeName: 'my-reports',
            me: this.appVars.me
        }
    }
}
</script>

