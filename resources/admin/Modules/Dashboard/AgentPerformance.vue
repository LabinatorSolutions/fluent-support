<template>
    <div class="fs_box_body" v-if="fetching && !todayStats.length" aria-busy="true">
        <div class="fs_agent_performance_list">
            <el-skeleton v-for="n in 3" :key="n" animated>
                <template #template>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <el-skeleton-item variant="circle" style="width: 32px; height: 32px; flex-shrink: 0;" />
                        <div style="flex: 1;">
                            <el-skeleton-item variant="text" style="width: 50%;" />
                            <el-skeleton-item variant="text" style="width: 35%; margin-top: 6px;" />
                        </div>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: flex-end;">
                            <el-skeleton-item variant="button" style="width: 120px; height: 22px;" />
                            <el-skeleton-item variant="text" style="width: 70%; margin-top: 6px;" />
                        </div>
                    </div>
                </template>
            </el-skeleton>
        </div>
    </div>
    <div class="fs_box_body" v-else-if="todayStats.length">
        <div class="fs_agent_performance_list">
            <div
                v-for="(agent, index) in visibleStats"
                :key="agent.agent_id"
                class="fs_agent_performance_item"
            >
                <!-- Agent Info Row -->
                <div class="fs_agent_info_row">
                    <!-- Left: Avatar + Name/Email -->
                    <div class="fs_agent_info">
                        <div class="fs_agent_avatar">
                            <img
                                :src="agent.avatar || getDefaultAvatar(agent.agent_name)"
                                :alt="agent.agent_name"
                                class="fs_avatar_image"
                            />
                        </div>
                        <div class="fs_agent_details">
                            <p class="fs_agent_name">{{ agent.agent_name.trim().length > 0 ? agent.agent_name : "No name" }}</p>
                            <p class="fs_agent_email">{{ agent.agent_email || 'agent@example.com' }}</p>
                        </div>
                    </div>

                    <!-- Right: Stats Column -->
                    <div class="fs_agent_stats_column">
                        <!-- Closed Tickets Badge -->
                        <div class="fs_closed_badge">
                            <IconPack icon-key="closed_tickets"/>
                            <!-- translators: %d is the number of tickets the agent closed today -->
                            <span>{{ $_n('Closed %d ticket', 'Closed %d tickets', agent.stats.closed_tickets.count) }}</span>
                        </div>

                        <!-- Stats Row -->
                        <div class="fs_agent_stats_row">
                            <!-- Waiting -->
                            <div class="fs_agent_stat">
                                <IconPack iconKey="clock" :width="16" :height="16" class="fs_meta_icon" />
                                <!-- translators: %d is the number of the agent's tickets waiting for a reply -->
                                <span>{{ $_n('%d ticket waiting', '%d tickets waiting', agent.stats.waiting_today.count) }}</span>
                            </div>

                            <!-- Responses -->
                            <div class="fs_agent_stat">
                                <IconPack iconKey="responses" :width="16" :height="16" class="fs_meta_icon" />
                                <!-- translators: %d is the number of replies the agent wrote today -->
                                <span>{{ $_n('%d response', '%d responses', agent.stats.responses.count) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Divider (except for last item) -->
                <div v-if="index < visibleStats.length - 1" class="fs_agent_divider"></div>
            </div>
        </div>
        <div v-if="todayStats.length > pageSize" class="fs_agent_performance_footer">
            <el-button link class="fs_agent_performance_more" :aria-expanded="allShown ? 'true' : 'false'" @click="toggleMore">
                {{ allShown ? $t('Show less') : $t('Load more') }}
            </el-button>
        </div>
    </div>

    <div v-else class="fs_no_agents_data">
        <span>
            {{ $t("No agent performance data available") }}
        </span>
    </div>
</template>

<script type="text/babel">
import IconPack from "../../Components/IconPack.vue";

export default {
    name: 'AgentPerformance',
    components: {
        IconPack
    },
    data() {
        return {
            fetching: true,
            todayStats: [],
            pageSize: 6,
            visibleCount: 6
        };
    },
    computed: {
        visibleStats() {
            return this.todayStats.slice(0, this.visibleCount);
        },
        allShown() {
            return this.visibleCount >= this.todayStats.length;
        }
    },
    methods: {
        async fetchAgentPerformance() {
            this.fetching = true;
            try {
                const response = await this.$get('tickets/agent_performance');
                this.todayStats = response.agent_today_stats;
            } catch (errors) {
                this.$handleError(errors);
            } finally {
                this.fetching = false;
            }
        },
        getDefaultAvatar(name) {
            // Generate a simple avatar based on initials or use a default
            return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&size=40&background=f0f0f0&color=525866&font-size=0.33`;
        },
        toggleMore() {
            this.visibleCount = this.allShown ? this.pageSize : this.visibleCount + this.pageSize;
        }
    },
    mounted() {
        this.fetchAgentPerformance();
    }
}
</script>
