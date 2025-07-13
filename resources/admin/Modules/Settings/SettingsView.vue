<template>
    <div class="fs_tickets_view fs_settings_view">
        <div class="inner_sidebar">
            <ul>
                <li v-for="(settings_menu, settings_index) in settings_items">
                    <router-link
                        :to="{
                            name: settings_menu.route_name,
                            query: settings_menu.route_query,
                        }"
                    >
                        <el-icon>
                            <component :is="settings_menu.icon" />
                        </el-icon>
                        {{ settings_menu.title }}
                    </router-link>
                </li>
            </ul>
        </div>
        <div style="padding-bottom: 20px" class="inner_body">
            <router-view key="products_view"></router-view>
        </div>
    </div>
</template>

<script type="text/babel">
import {  onMounted, reactive, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "SettingsView",

    setup() {

        const {
            translate,
            get,
            handleError,
        } = useFluentHelper();

        const state = reactive({
            settings_items: [],
        });

        const fetchSettingsMenu = async () => {
            await get("settings/settings-menu")
                .then((response) => {
                    state.settings_items = response;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {});
        };

        onMounted(() => {
            fetchSettingsMenu();
        });

        return {
            ...toRefs(state),
            translate,
        }
    },
};
</script>
