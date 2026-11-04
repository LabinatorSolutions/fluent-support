<template>
    <div class="fs_dashboard_statistics">
        <div v-if="loading && !Object.keys(stats || {}).length" class="fs_stats_grid" aria-busy="true">
            <div v-for="n in 6" :key="n" class="fs_stat_card">
                <el-skeleton animated style="width: 100%;">
                    <template #template>
                        <el-skeleton-item variant="rect" style="width: 32px; height: 32px; border-radius: 8px;" />
                        <el-skeleton-item variant="text" style="width: 60%; margin-top: 16px;" />
                        <el-skeleton-item variant="h3" style="width: 40%; margin-top: 8px;" />
                    </template>
                </el-skeleton>
            </div>
        </div>
        <div v-else class="fs_stats_grid">
            <div v-for="(stat, statKey) in stats" :key="statKey" class="fs_stat_card">
                <div class="fs_stat_icon" :class="getToneClass(statKey)">
                    <IconPack :iconKey="getIconKey(statKey)" :width="20" :height="20" />
                </div>
                <div class="fs_stat_content">
                    <p class="fs_stat_label">{{ stat.title }}</p>
                    <p class="fs_stat_number">{{ stat.count }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import IconPack from "@/admin/Components/IconPack.vue"

// Icon and colour tone per stat key; unknown keys get the default icon and a neutral tile
const STAT_STYLES = {
    'new_tickets': { icon: 'new_tickets', tone: 'blue' },
    'open_tickets': { icon: 'open_tickets', tone: 'orange' },
    'active_tickets': { icon: 'open_tickets', tone: 'orange' },
    'closed_tickets': { icon: 'closed_tickets', tone: 'purple' },
    'active_agents': { icon: 'active_agents' },
    'responses': { icon: 'responses', tone: 'pink' },
    'interactions': { icon: 'interactions', tone: 'sky' },
    'unassigned_tickets': { icon: 'unassigned_tickets', tone: 'red' }
}

export default {
    name: 'DashboardStatistics',
    components: {
        IconPack
    },
    props: {
        stats: {
            type: Object,
            default: () => ({})
        },
        loading: {
            type: Boolean,
            default: false
        }
    },
    methods: {
        getIconKey(statKey) {
            const style = STAT_STYLES[statKey]
            return style ? style.icon : 'new_tickets'
        },
        getToneClass(statKey) {
            const style = STAT_STYLES[statKey]
            return style && style.tone ? 'fs_stat_icon--' + style.tone : ''
        }
    }
}
</script>
