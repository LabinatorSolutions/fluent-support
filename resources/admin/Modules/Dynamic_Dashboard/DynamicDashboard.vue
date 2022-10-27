<template>
  <el-button
    type="info"
    class="fs_drawer"
    style="margin-left: 16px"
    @click="drawer = true"
  >
    <el-icon>
      <Setting />
    </el-icon>
  </el-button>
  <div class="dashboard fs_box_wrapper">
    <h1>
        {{ $t("Good") }} {{ greetingTime }}
        {{ me.full_name }}
    </h1>
    <div v-html="dashboard_notice"></div>
    <el-row :gutter="20">
      <el-col :span="12">
        <draggable
          v-model="dashboard_settings_data.second_row"
          ghost-class="ghost"
          group="people"
          item-key="id"
        >
          <template #item="{ element }">
            <div class="draggable">
              <component
                v-if="element.show"
                :is="element.component"
                :data="total_data"
                :component_collapse_state="component_collapse_state"
                @component_state="changeComponentState"
              ></component>
            </div>
          </template>
        </draggable>
      </el-col>
      <el-col :span="12">
        <draggable
          v-model="dashboard_settings_data.first_row"
          ghost-class="ghost"
          group="people"
          item-key="id"
        >
          <template #item="{ element }">
            <div class="draggable">
              <component
                v-if="element.show"
                :is="element.component"
                :data="total_data"
                :component_collapse_state="component_collapse_state"
                @component_state="changeComponentState"
              ></component>
            </div>
          </template>
        </draggable>
      </el-col>
    </el-row>

    <el-drawer v-model="drawer" title="I am the title" :with-header="false">
      <div
        class="fs_settings_drawer"
        v-for="data in dashboard_settings_data.first_row"
      >
        <el-skeleton
          :rows="5"
          :count="4"
          style="width: 240px; --el-skeleton-circle-size: 20px"
        >
          <template #template>
            <div
              style="
                display: flex;
                align-items: center;
                justify-items: space-between;
                margin-bottom: 5px;
                height: 100%;
              "
            >
              <el-skeleton-item
                variant="circle"
                style="margin-right: 16px; --el-skeleton-circle-size: 20px"
              />
              <el-skeleton-item variant="text" style="width: 80%" />
            </div>
          </template>
        </el-skeleton>
        <el-checkbox v-model="data.show" :label="data.component" />
      </div>

      <div
        class="fs_settings_drawer"
        v-for="data in dashboard_settings_data.second_row"
      >
        <el-skeleton
          :rows="5"
          :count="4"
          style="width: 240px; --el-skeleton-circle-size: 20px"
        >
          <template #template>
            <div
              style="
                display: flex;
                align-items: center;
                justify-items: space-between;
                margin-bottom: 5px;
                height: 100%;
              "
            >
              <el-skeleton-item
                variant="circle"
                style="margin-right: 16px; --el-skeleton-circle-size: 20px"
              />
              <el-skeleton-item variant="text" style="width: 80%" />
            </div>
          </template>
        </el-skeleton>
        <el-checkbox v-model="data.show" :label="data.component" />
      </div>
    </el-drawer>
  </div>
</template>

<script type="text/babel">
import draggable from "vuedraggable";
import DashboardBox from "./DashboardBox.vue";
import SuggestedTicket from "./SuggestedTicket.vue";
import MentionedTticket from "./MentionedTticket.vue";
import Component1 from "./Component1.vue";
import Component2 from "./Component2.vue";
export default {
  name: "DynamicDashboard",
  components: {
    draggable,
    DashboardBox,
    SuggestedTicket,
    Component1,
    Component2,
    MentionedTticket,
  },
  data() {
    return {
      drawer: false,
      me: this.appVars.me,
      can_access_unassigned_tickets: false,
      loading: false,
      stats: {},
      suggested_tickets: [],
      ticket_to_watch: [],
      overall_stats: false,
      individual_stat: false,
      dashboard_notice: "",
      total_data: [],
      dashboard_settings_data: {
        first_row: [
          {
            id: 3,
            component: "MentionedTticket",
            show: true,
          },
          {
            id: 1,
            component: "Component2",
            show: true,
          },
        ],

        second_row: [
          {
            id: 2,
            component: "DashboardBox",
            show: true,
          },
          {
            id: 4,
            component: "SuggestedTicket",
            show: true,
          },
        ],
      },
      settings_data: {},
      default_component_collapse_state: {
        dashboardBox: true,
        mentionedTicket: true,
        suggestedTicket: true,
      },
      component_collapse_state: {},
    };
  },
  watch: {
    dashboard_settings_data: {
      handler(newValue, oldValue) {
        this.$saveData("dashboard_settings_data", newValue);
      },
      deep: true,
    },
  },
  computed: {
    greetingTime() {
      const m = this.moment();
      let g = null; //return g

      if (!m || !m.isValid()) {
        return;
      } //if we can't find a valid or filled moment, we return.

      const split_afternoon = 12; //24hr time to split the afternoon
      const split_evening = 17; //24hr time to split the evening
      const currentHour = parseFloat(m.format("HH"));

      if (currentHour >= split_afternoon && currentHour <= split_evening) {
        g = this.$t("afternoon");
      } else if (currentHour >= split_evening) {
        g = this.$t("evening");
      } else {
        g = this.$t("morning");
      }

      return g;
    },
  },
  methods: {
    checkMove: function (e) {
      window.console.log("Future index: " + e.draggedContext.futureIndex);
    },
    getDashboardSettings() {
      this.settings_data = this.$getData("dashboard_settings_data");
      if (this.settings_data) {
        this.dashboard_settings_data = this.settings_data;
      }
    },
    saveDashboardSettings() {
      this.$saveData("dashboard_settings_data", this.dashboard_settings_data);
    },
    changeComponentState() {
      this.$saveData("component_state", this.component_collapse_state);
      this.component_collapse_state = this.$getData("component_state");
      if (!this.component_collapse_state) {
        this.component_collapse_state = this.default_component_collapse_state;
      }
    },
    getComponentState() {
      this.component_collapse_state = this.$getData("component_state");
      if (!this.component_collapse_state) {
        this.component_collapse_state = this.default_component_collapse_state;
      }
    },
    fetchStat() {
      this.loading = true;
      this.$get("tickets/my_stats", {
        with: [
          "suggested_tickets",
          "overall_stats",
          "individual_stat",
          "ticket_to_watch",
        ],
      })
        .then((response) => {
          this.total_data = response;
          this.stats = response.stats;
          this.suggested_tickets = response.suggested_tickets;
          this.overall_stats = response.overall_stats;
          this.individual_stat = response.individual_stat;
          this.dashboard_notice = response.dashboard_notice;
          this.ticket_to_watch = response.ticket_to_watch;
        })
        .catch((errors) => {
          this.$handleError(errors);
        })
        .always(() => {
          this.loading = false;
        });
    },
  },
  mounted() {
    if (!this.appVars.mailboxes.length) {
      this.$router.push({ name: "setup", query: { t: Date.now() } });
    }
    this.can_access_unassigned_tickets =
      this.appVars.me.permissions.indexOf("fst_manage_unassigned_tickets") > -1;
    this.fetchStat();
    this.getDashboardSettings();
    this.getComponentState();
    jQuery(
      ".update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error"
    ).remove();
  },
};
</script>

<style scoped>
.ghost {
  opacity: 0.2;
  background: gray;
  color: white;
  width: 100%;
  margin: auto;
  display: block;
  overflow: hidden;
  text-align: center;
}
.fs_settings_drawer {
  width: 70%;
  border-radius: 10px;
  padding: 10px;
  margin-bottom: 10px;
  background: #fff;
  display: block;
  overflow: hidden;
  border: 1px solid #e3e8ee;
}
.fs_drawer {
  float: right;
}
</style>
