<template>
    <div class="fs_tickets_view fs_settings_view">
        <div class="inner_sidebar">
            <ul>
                <li v-for="(settings_menu, settings_index) in settings_items">
                    <router-link :to="{ name: settings_menu.route_name, query: settings_menu.route_query }">
                        {{settings_menu.title}}
                    </router-link>
                </li>
            </ul>
        </div>
        <div style="padding-bottom: 20px; background: white;" class="inner_body">
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
                    title: 'Global Settings',
                    route_name: 'global_settings'
                },
                {
                    title: 'Tags',
                    route_name: 'tags',
                    route_query: {}
                },
                {
                    title: 'Products',
                    route_name: 'products',
                    route_query: {}
                },
                {
                    title: 'Support Staff',
                    route_name: 'support-staffs',
                    route_query: {}
                },
                {
                    title: 'Telegram Notifications',
                    route_name: 'integration',
                    route_query: {
                        item_key: 'telegram_settings'
                    }
                }
            ]
        }
    }
}
</script>
