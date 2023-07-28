<template>
    <div class="fs_box">
        <div class="fs_box_header" style="margin-top: 1.25em">
            <div class="fs_header_title">
                {{ translate("Quick Stats") }}
            </div>
        </div>
        <div class="fs_box_body">
            <ul v-if="!loading" class="fs_card_list">
                <li
                    style="padding: 15px"
                    v-for="(stat, stat_type) in overall_reports"
                    :key="stat_type"
                >
                    <b>{{ stat.title }}:</b> {{ stat.count }}
                </li>
            </ul>
            <div class="fs_padded_20" v-else>
                <el-skeleton :rows="3" animated />
            </div>
        </div>

        <div
            class="fs_box fs_today_stats"
            style="margin-top: 1.25em"
        >
            <div class="fs_box_header">
                <div class="fs_header_title">
                    {{ translate("Today Stats") }}
                </div>
            </div>
            <div class="fs_box_body">
                <ul v-if="!loading" class="fs_card_list">
                    <li
                        style="padding: 15px"
                        v-for="(stat, stat_type) in today_reports"
                        :key="stat_type"
                    >
                        <b>{{ stat.title }}:</b>
                        {{ stat.count }}
                    </li>
                </ul>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import {reactive, toRefs, onMounted, nextTick} from "vue";

export default {
    name: "SideBar",
    setup(){
        const { get, translate, handleError, setTitle } =
            useFluentHelper();

        const state = reactive({
            overall_reports: {},
            today_reports: {},
        });
        const fetchReports = () => {
            state.loading = true;
            get("reports")
                .then((response) => {
                    state.overall_reports = response.overall_reports;
                    state.today_reports = response.today_reports;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        onMounted(() => {
            fetchReports();
        });

        return {
            ...toRefs(state),
            translate
        }
    }
}
</script>

<style scoped>

</style>
