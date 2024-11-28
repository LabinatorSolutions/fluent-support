<template>
    <el-popover ref="estimated_time_pop" popper-class="fs_timer_log_popover" placement="left" :width="400" trigger="click">
        <template #reference>
            <div style="cursor: pointer;">{{ timeSheetTotal }}</div>
        </template>
        <el-table :stripe="true" :border="true" :data="timeItems" style="width: 100%">
            <el-table-column type="expand" width="30">
                <template #default="{row}">
                    <div class="fs_detail_log">
                        <p><b>{{translate('Logged at:')}}</b> {{ smartDate(row.created_at, true) }}</p>
                        <p><b>{{translate('Work Description:')}}</b> {{ row.message }}</p>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="user" label="Agent">
                <template #default="{ row }">
                    <div class="fs_time_sheet_person">
                        <el-avatar :src="row.agent?.photo" size="small"></el-avatar>
                        <a
                            :href="`/wp-admin/user-edit.php?user_id=${row.agent?.user_id}`"
                            target="_blank"
                            style="text-decoration: none; margin-left: 8px;">
                            <strong> {{ row.agent?.full_name }} </strong>
                        </a>

                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="billable_minutes" label="Time" width="80">
                <template #default="{ row }">
                    <span>{{ formatMinutes(row.billable_minutes) }}</span>
                </template>
            </el-table-column>
        </el-table>
    </el-popover>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import each from 'lodash/each';
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import {timesheetUtils} from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils";

export default {
    name: 'TicketDateSheetPop',
    props: ['ticket_id','date','timeSheets'],
    setup(props) {
        const { smartDate, translate } = useFluentHelper();
        const timeItems = ref([]);

        const timeSheetTotal = computed(() => {
            return timesheetUtils.calculateTimeSheetTotal(props.timeSheets, props.date, props.ticket_id);
        });

        const initItems = () => {
            const sheets = props.timeSheets?.[props.date]?.[props.ticket_id] ?? [];
            each(sheets, sheet => {
                timeItems.value.push(sheet);
            });
        };

        const formatMinutes = (minutes) =>
            timesheetUtils.formatMinutes(minutes);

        onMounted(initItems);

        return {
            timeItems,
            timeSheetTotal,
            formatMinutes,
            smartDate,
            translate
        };
    }
};
</script>
