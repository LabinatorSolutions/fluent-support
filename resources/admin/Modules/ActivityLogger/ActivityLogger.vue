<template>
    <div class="activities fs_box fs_box_wrapper">
        <div v-if="!loading" class="fs_box_header" style="font-size: 16px;">
            <div class="fs_box_head">
                Overall Activities
            </div>
            <div class="fs_box_actions">
                <el-button @click="showSettingsModal = true" size="small" type="default" icon="el-icon-setting"></el-button>
            </div>
        </div>
        <div class="fs_box fs_activity_box" v-if="activities">
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul class="fs_activities">
                        <li v-for="activity in activities" @click.prevent="handleClick(activity, $event)" :key="activity.id"
                            :class="activity.person_type=='agent' ? 'fs_agent_activity' : 'fs_customer_activity'">
                            <div class="fs_activity">
                                <div class="fs_activity_avatar">
                                    <img v-if="activity.person" :src="activity.person?.photo"
                                         :alt="activity.person.full_name"/>
                                </div>
                                <div class="fs_activity_info">
                                    <span v-html="activity.description"></span> <br/>
                                    <samp>{{ $timeDiff(activity.created_at) }}</samp>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div style="padding-bottom: 20px" class="fframe_pagination_wrapper">
                        <pagination @fetch="fetchActivities()" :pagination="pagination"/>
                    </div>
                </template>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated/>
                </div>
            </div>
        </div>

        <el-dialog
            v-model="showSettingsModal"
            title="Activity Log Settings"
            width="60%"
            :append-to-body="true"
        >
            <activity-settings @updated="showSettingsModal = false" />
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import ActivitySettings from './_ActivitySettings';

export default {
    name: "ActivityLogger",
    components: {
        ActivitySettings,
        Pagination
    },
    data() {
        return {
            activities: [],
            loading: false,
            me: this.appVars.me,
            showSettingsModal: false,
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            }
        }
    },
    methods: {
        fetchActivities() {
            this.loading = true;
            this.$get('activity-logger',  {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page
            })
                .then(response => {
                    this.activities = response.activities.data;
                    this.pagination.total = response.activities.total;
                })
                .catch(error => {
                    this.$handleError(error)
                })
                .always(() => {
                    this.loading = false;
                })
        },
        handleClick(activity, $event) {
            const route = $event.target.getAttribute('href');

            if(!route) {
                return false;
            }

            if(route == '#view_ticket') {
                this.$router.push({ name: 'view_ticket', params: {ticket_id: activity.object_id }});
            }

        }
    },
    mounted() {
        this.fetchActivities();
        this.$setTitle('Activities');
    }
}
</script>
