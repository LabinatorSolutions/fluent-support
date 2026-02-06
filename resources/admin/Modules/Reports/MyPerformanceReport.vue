<template>
    <div v-if="!loading" class="fs_personal_performance_box">
        <div class="fs_personal_performance_header">
            <div class="fs_personal_performance_title">
                {{ $t('My Performance') }}
            </div>
        </div>
        <div class="fs_personal_performance_body">
            <div class="fs_personal_performance_list">
                <div class="fs_personal_performance_item">
                    <div class="fs_personal_performance_icon_label">
                        <IconPack 
                            icon-key="interactions" 
                            :width="20" 
                            :height="20"
                            fill="#525866"
                            class="fs_personal_performance_icon"
                        />
                        <span class="fs_personal_performance_label">{{ $t('Interactions') }}</span>
                    </div>
                    <span class="fs_personal_performance_value">{{ stats?.interactions || 0 }}</span>
                </div>
                <div class="fs_personal_performance_item">
                    <div class="fs_personal_performance_icon_label">
                        <IconPack 
                            icon-key="waiting_tickets" 
                            :width="15" 
                            :height="15"
                            fill="#525866"
                            class="fs_personal_performance_icon"
                        />
                        <span class="fs_personal_performance_label">{{ $t('Waiting Tickets') }}</span>
                    </div>
                    <span class="fs_personal_performance_value">{{ stats?.waiting_tickets || 0 }}</span>
                </div>
                <div class="fs_personal_performance_item">
                    <div class="fs_personal_performance_icon_label">
                        <IconPack 
                            icon-key="average_waiting" 
                            :width="12" 
                            :height="12"
                            fill="#525866"
                            class="fs_personal_performance_icon"
                        />
                        <span class="fs_personal_performance_label">{{ $t('Average Waiting') }}</span>
                    </div>
                    <span class="fs_personal_performance_value">{{ stats?.average_waiting || 0 }}</span>
                </div>
                <div class="fs_personal_performance_item">
                    <div class="fs_personal_performance_icon_label">
                        <IconPack 
                            icon-key="max_waiting" 
                            :width="13" 
                            :height="13"
                            fill="#525866"
                            class="fs_personal_performance_icon"
                        />
                        <span class="fs_personal_performance_label">{{ $t('Max Waiting') }}</span>
                    </div>
                    <span class="fs_personal_performance_value">{{ stats?.max_waiting || 0 }}</span>
                </div>
                <div v-if="has_pro && appVars.agent_feedback_rating === 'yes'" class="fs_personal_performance_item">
                    <div class="fs_personal_performance_icon_label">
                        <IconPack 
                            icon-key="like" 
                            :width="20" 
                            :height="20"
                            fill="#525866"
                            class="fs_personal_performance_icon"
                        />
                        <span class="fs_personal_performance_label">{{ $t('Liked') }}</span>
                    </div>
                    <span class="fs_personal_performance_value">{{ stats?.likes || 0 }}</span>
                </div>
                <div v-if="has_pro && appVars.agent_feedback_rating === 'yes'" class="fs_personal_performance_item">
                    <div class="fs_personal_performance_icon_label">
                        <IconPack 
                            icon-key="dislike" 
                            :width="20" 
                            :height="20"
                            fill="#525866"
                            class="fs_personal_performance_icon"
                        />
                        <span class="fs_personal_performance_label">{{ $t('Disliked') }}</span>
                    </div>
                    <span class="fs_personal_performance_value">{{ stats?.dislikes || 0 }}</span>
                </div>
            </div>
        </div>
    </div>
    <div v-else style="padding: 20px; background: white;" class="fs_box_body">
        <el-skeleton :rows="5" animated/>
    </div>
</template>

<script type="text/babel">
import IconPack from "@/admin/Components/IconPack.vue";

export default {
    name: "MyPerformanceReport",
    components: {
        IconPack
    },
    props: {
        reports: {
            type: Array,
            default: () => []
        },
        loading: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            
        };
    },

    computed: {
        stats() {
            if (!this.reports || this.reports.length === 0) {
                return null;
            }
            // For personal view, get the first (and likely only) report
            const report = this.reports[0];
            if (report && report.active_stat) {
                return {
                    interactions: report.stats?.interactions || 0,
                    waiting_tickets: report.active_stat.waiting_tickets || 0,
                    average_waiting: report.active_stat.average_waiting || 0,
                    max_waiting: report.active_stat.max_waiting || 0,
                    likes: report.stats?.likes || 0,
                    dislikes: report.stats?.dislikes || 0,
                };
            }
            return {
                interactions: report?.stats?.interactions || 0,
                waiting_tickets: 0,
                average_waiting: 0,
                max_waiting: 0,
                likes: report?.stats?.likes || 0,
                dislikes: report?.stats?.dislikes || 0,
            };
        },

    }
};
</script>

<style lang="scss" scoped>
// Personal Performance View Styles
.fs_personal_performance_box {
    background: var(--fs-bg-primary, #FFF);
    border-radius: var(--fs-radius-md, 8px);
    overflow: hidden;
    width: 100%;
    
    @media (max-width: 768px) {
        margin-top: 20px;
    }
}

.fs_personal_performance_header {
    background: var(--fs-bg-primary, #FFF);
    border-bottom: 1px solid #e1e4ea;
    padding: 12px 20px;
}

.fs_personal_performance_title {
    font-weight: 500;
    font-size: 16px;
    line-height: 24px;
    color: #0e121b;
    letter-spacing: -0.176px;
}

.fs_personal_performance_body {
    padding: 20px;
}

.fs_personal_performance_list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.fs_personal_performance_item {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.fs_personal_performance_icon_label {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: 1;
    min-width: 0;
}

.fs_personal_performance_icon {
    color: #525866;
    font-size: 20px;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
}

.fs_personal_performance_label {
    font-weight: 400;
    font-size: 14px;
    line-height: 20px;
    color: #525866;
    letter-spacing: -0.084px;
    min-width: 120px;
}

.fs_personal_performance_value {
    font-weight: 400;
    font-size: 14px;
    line-height: 20px;
    color: #0e121b;
    text-align: right;
    white-space: nowrap;
    letter-spacing: -0.084px;
}
</style>

