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
        <p class="fs_stat_helper" v-if="component_data.individual_stat" >
            <span style="color:#FF7C7C">
                {{ component_data.individual_stat.waiting_tickets }}
                {{ $t("tickets") }}
            </span>
            {{ $t("are waiting for reply with") }}
            <span style="color:#88C379">
                {{ $t("average") }}
                {{ component_data.individual_stat.average_waiting }}
                {{ $t("wait time") }}</span
            >
            & {{ $t("max wait time") }}
            <span style="color:#7280FF">{{ component_data.individual_stat.max_waiting }}</span>
        </p>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "TicketStatistics",
    setup() {
        return {};
    },
};
</script>
<style scoped>
    .fs_stat_helper{
        padding: 5px 30px;
    }
</style>
