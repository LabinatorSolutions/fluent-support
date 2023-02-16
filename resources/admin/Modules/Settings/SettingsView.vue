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
            has_pro,
        } = useFluentHelper();

        const state = reactive({
            settings_items: [
                {
                    title: translate("Global Settings"),
                    route_name: "global_settings",
                    icon: "Document",
                },
                {
                    title: translate("Ticket Tags"),
                    route_name: "tags",
                    icon: "CollectionTag",
                    route_query: {},
                },
                {
                    title: translate("Recaptcha"),
                    route_name: "reCaptcha",
                    icon: "Setting",
                },
                {
                    title: translate("Ticket Form Config"),
                    route_name: "ticket-form-config",
                    icon: "Setting",
                    route_query: {},
                },
                {
                    title: translate("Custom Fields"),
                    route_name: "custom_fields",
                    route_query: {},
                    icon: "Tickets",
                },
                {
                    title: translate("Products"),
                    route_name: "products",
                    route_query: {},
                    icon: "Goods",
                },
                {
                    title: translate("Support Staff"),
                    route_name: "support-staffs",
                    route_query: {},
                    icon: "User",
                },
                {
                    title: translate("FluentCRM Integration"),
                    route_name: "fluentcrm_integration",
                    route_query: {},
                    icon: "Cpu",
                },
                {
                    title: translate("Incoming Webhook"),
                    route_name: "incoming-webhook",
                    route_query: {},
                    icon: "Connection",
                },
                {
                    title: translate("Notification Integrations"),
                    route_name: "integration",
                    icon: "AlarmClock",
                },
                {
                    title: translate("Auto Close Settings"),
                    route_name: "auto_close",
                    icon: "Timer",
                },
                {
                    title: translate("Ticket Importer"),
                    route_name: "ticket_importer",
                    icon: "Download",
                },
            ],
        });

        onMounted(() => {
            if (has_pro) {
                state.settings_items.push({
                    title: translate("License Management"),
                    route_name: "license",
                    icon: "Lock",
                });
            }
        });

        return {
            ...toRefs(state),
            translate,

        }
    },
};
</script>
