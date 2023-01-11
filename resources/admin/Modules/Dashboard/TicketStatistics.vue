<template>
    <div class="fs_box_body">
        <ul class="fs_card_list">
            <li
                style="padding: 15px"
                v-for="(stat, statKey) in component_data.stats"
                :key="statKey"
            >
                <b>{{ stat.title }}: </b> {{ stat.count }}
            </li>
        </ul>
        <p class="fs_stat_helper" v-if="component_data.individual_stat">
            <span style="color: #ff7c7c">
                {{ component_data.individual_stat.waiting_tickets }}
                {{ translate("tickets") }}
            </span>
            {{ translate("are waiting for reply with") }}
            <span style="color: #88c379">
                {{ translate("average") }}
                {{ component_data.individual_stat.average_waiting }}
                {{ translate("wait time") }}
            </span>
            & {{ translate("max wait time") }}
            <span style="color: #7280ff">{{
                component_data.individual_stat.max_waiting
            }}</span>
        </p>
    </div>
</template>
<script>
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    props: ["component_data"],
    name: "TicketStatistics",
    setup() {
        const { translate } = useFluentHelper();
        return {
            translate,
        };
    },
};
</script>
<style scoped>
.fs_stat_helper {
    padding: 5px 30px;
}
</style>
