<template>
    <div class="fs_box_body">
        <ul class="fs_card_list">
            <li
                style="padding: 15px 0;"
                v-for="(stat, statKey) in component_data"
                :key="statKey"
            >
                <b>{{ stat.title }}: </b> {{ stat.count }}
            </li>
        </ul>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "TicketsByProduct",
    setup() {
        return {};
    },
};
</script>
<style>

</style>
