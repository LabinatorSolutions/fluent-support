<template>
    <div class="fs_tickets_view">
        <tickets-menu />
        <div class="inner_body">
            <router-view key="tickets_view"></router-view>
        </div>
    </div>
</template>

<script type="text/babel">
import {onMounted, watch} from "vue";
import {useRouter} from "vue-router";
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";
import TicketsMenu from "./_TicketsMenu.vue";

export default {
    name: 'ticketsView',
    components: {
        TicketsMenu
    },
    setup(props, _){
        const {
            appVars,
            saveData,
            getData
        } = useFluentHelper();
        const router = useRouter();

        const saveRoutDataToLocalStorage = () => {
            const currentRoute = router.currentRoute.value.query;
            if (Object.keys(currentRoute).length > 0) {
                saveData("routesData", JSON.stringify(currentRoute));
            }
        };

        const loadRouteDataFromLocalStorage = () => {
            const routeName = router.currentRoute.value.name;
            const savedRoute = getData('routesData');

            if (routeName !== 'tickets' || Object.keys(savedRoute).length === 0) {
                return;
            }

            const parsedRoute = JSON.parse(savedRoute);

            if ("agent_id" in parsedRoute && parsedRoute.agent_id !== 'unassigned') {
                parsedRoute.agent_id = appVars.me.id;
            }

            router.replace({ name: 'tickets', query: parsedRoute });
        }

        watch(() => router.currentRoute.value.name, (newRouteName) => {
            loadRouteDataFromLocalStorage();
        });

        router.afterEach(() => {
            saveRoutDataToLocalStorage();
        });

        onMounted(() => {
            loadRouteDataFromLocalStorage();
        });

        return {
            appVars
        }
    }
}
</script>
