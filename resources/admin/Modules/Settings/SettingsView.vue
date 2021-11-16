<template>
    <div class="fs_tickets_view fs_settings_view">
        <div class="inner_sidebar">
            <ul>
                <li v-for="(settings_menu, settings_index) in settings_items">
                    <router-link :to="{ name: settings_menu.route_name, query: settings_menu.route_query }">
                        <i :class="settings_menu.icon"/>
                        {{ settings_menu.title }}
                    </router-link>
                </li>
            </ul>
        </div>
        <div style="padding-bottom: 20px;" class="inner_body">
            <router-view key="products_view"></router-view>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'SettingsView',
    data() {
        return {
            settings_items: [
                {
                    title: this.$t('Global Settings'),
                    route_name: 'global_settings',
                    icon: 'el-icon-document'
                },
                {
                    title: this.$t('Ticket Tags'),
                    route_name: 'tags',
                    icon: 'el-icon-collection-tag',
                    route_query: {}
                },
                {
                    title: this.$t('Ticket Form Config'),
                    route_name: 'ticket-form-config',
                    icon: 'el-icon-setting',
                    route_query: {}
                },
                {
                    title: this.$t('Custom Fields'),
                    route_name: 'custom_fields',
                    route_query: {},
                    icon: 'el-icon-tickets'
                },
                {
                    title: this.$t('Products'),
                    route_name: 'products',
                    route_query: {},
                    icon: 'el-icon-goods'
                },
                {
                    title: this.$t('Support Staff'),
                    route_name: 'support-staffs',
                    route_query: {},
                    icon: 'el-icon-user'
                },
                {
                    title: this.$t('FluentCRM Integration'),
                    route_name: 'fluentcrm_integration',
                    route_query: {},
                    icon: 'el-icon-cpu'
                },
                {
                    title: this.$t('Notification Integrations'),
                    route_name: 'integration',
                    icon: 'el-icon-alarm-clock'
                }
            ]
        }
    },
    mounted() {
        if (this.has_pro) {
            this.settings_items.push({
                title: this.$t('License Management'),
                route_name: 'license',
                icon: 'el-icon-lock'
            });
        }
    }
}
</script>
