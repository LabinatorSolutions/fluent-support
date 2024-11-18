<template>
    <el-popover ref="estimated_time_pop" popper-class="fs_timer_log_popover" placement="left" :width="400" trigger="click">
        <template #reference>
            <div style="cursor: pointer;">{{ timeSheetTotal }}</div>
        </template>
        <el-table :stripe="true" :border="true" :data="timeItems" style="width: 100%">
            <el-table-column type="expand" width="30">
                <template #default="{row}">
                    <div class="fs_detail_log">
                        <p><b>Logged at:</b> {{ smartDate(row.created_at, true) }}</p>
                        <p><b>Work Description:</b> {{ row.message }}</p>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="user" label="Agent">
                <template #default="{ row }">
                    <div class="fs_time_sheet_person">
                        <el-avatar :src="row.agent?.photo" size="small"></el-avatar>
                        <span>{{ row.agent?.full_name }}</span>
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

export default {
    name: 'TicketDateSheetPop',
    props: ['ticket_id','date','timeSheets'],
    setup(props) {
        const { smartDate } = useFluentHelper();
        const timeItems = ref([]);

        const timeSheetTotal = computed(() => {
            const sheets = props.timeSheets?.[props.date]?.[props.ticket_id] ?? [];
            const minutes = sheets.reduce((acc, sheet) => acc + sheet.billable_minutes, 0);
            return formatMinutes(minutes);
        });

        const initItems = () => {
            const sheets = props.timeSheets?.[props.date]?.[props.ticket_id] ?? [];
            each(sheets, sheet => {
                timeItems.value.push(sheet);
            });
        };

        const formatMinutes = (minutes) => {
            const intMinutes = parseInt(minutes);
            if (!intMinutes) return '--';

            const hours = Math.floor(intMinutes / 60);
            const remainingMinutes = intMinutes % 60;

            return hours ? `${hours}h ${remainingMinutes}m` : `${remainingMinutes}m`;
        };

        onMounted(initItems);

        return {
            timeItems,
            timeSheetTotal,
            formatMinutes,
            smartDate
        };
    }
};
</script>
