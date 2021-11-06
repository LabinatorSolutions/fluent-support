<template>
    <div class="activities fs_box_wrapper">
        <div v-if="!loading" class="fs_activity_header">
            {{$t('Good')}} {{greetingTime}} {{me.full_name}},
            <span style="font-weight: 300; color: #3C434A;">
                     Here are your activity logger
            </span>
        </div>
        <div class="fs_box fs_activity_box" v-if="activities">
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul>
                        <li v-for="activitie in activities" :key="activitie.id" :class="activitie.person_type=='agent' ? 'fs_agent_activity' : 'fs_customer_activity'">
                            <div class="fs_activity">
                                <div class="fs_activity_avatar">
                                    <img :src="activitie.person?.photo"
                                         :alt="activitie.person.full_name"/>
                                </div>
                                <div class="fs_activity_info">
                                    <span v-html="activitie.description"></span> <br/>
                                    <samp>{{$timeDiff(activitie.created_at)}}</samp>
                                </div>
                            </div>
                        </li>
                    </ul>
                </template>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated />
                </div>
            </div>
        </div>
    </div>
</template>

<script>

export default {
    name: "ActivityLogger",
    data() {
        return {
            activities: [],
            loading: false,
            me: this.appVars.me
        }
    },

    computed: {
        greetingTime() {
            const m = this.moment();
            let g = null; //return g

            if (!m || !m.isValid()) {
                return;
            } //if we can't find a valid or filled moment, we return.

            const split_afternoon = 12 //24hr time to split the afternoon
            const split_evening = 17 //24hr time to split the evening
            const currentHour = parseFloat(m.format("HH"));

            if (currentHour >= split_afternoon && currentHour <= split_evening) {
                g = this.$t("afternoon");
            } else if (currentHour >= split_evening) {
                g = this.$t("evening");
            } else {
                g = this.$t("morning");
            }

            return g;
        }
    },
    methods: {
      fetchActivities() {
          this.loading = !this.loading;
          this.$get('activity-logger')
          .then(response => {
              this.activities = response.activities;
          })
          .catch(error => {
              this.$handleError(error)
          })
          .always(() => {
              this.loading = !this.loading;
          })
      },
    },
    mounted() {
        this.fetchActivities();
        this.$setTitle('Activity Logger');
    }
}
</script>
