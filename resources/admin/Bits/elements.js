import { createApp } from 'vue';

import lang from 'element-plus/lib/locale/lang/en'
import locale from 'element-plus/lib/locale'

import {
    ElMenu,
    ElMenuItem,
    ElMain,
    ElRow,
    ElCol,
    ElTable,
    ElTableColumn,
    ElPagination,
    ElButtonGroup,
    ElButton,
    ElIcon,

    ElForm,
    ElFormItem,
    ElColorPicker,
    ElInput,
    ElInputNumber,
    ElOption,
    ElOptionGroup,
    ElRadio,
    ElRadioButton,
    ElRadioGroup,
    ElRate,
    ElSelect,
    ElCheckbox,
    ElCheckboxGroup,

    ElSlider,
    ElSwitch,
    ElTimePicker,
    ElDatePicker,
    ElTimeSelect,
    ElDialog,
    ElSkeleton,
    ElUpload,
    ElTooltip,

    ElTabs,
    ElTabPane,

    ElPopover,
    ElPopconfirm,
    ElDropdown,
    ElDropdownMenu,
    ElDropdownItem,

    ElLoading,
    ElMessage,
    ElMessageBox,
    ElNotification
} from 'element-plus';


const app = createApp({});
locale.use(lang);

const components = [
    ElMenu,
    ElMenuItem,
    ElMain,
    ElRow,
    ElCol,
    ElTable,
    ElTableColumn,
    ElPagination,
    ElButtonGroup,
    ElButton,
    ElIcon,
    ElForm,
    ElFormItem,
    ElColorPicker,
    ElInput,
    ElInputNumber,
    ElOption,
    ElOptionGroup,
    ElRadio,
    ElRadioButton,
    ElRadioGroup,
    ElRate,
    ElSelect,
    ElSlider,
    ElSwitch,
    ElTimePicker,
    ElUpload,
    ElTimeSelect,
    ElDialog,
    ElPopover,
    ElSkeleton,
    ElTabs,
    ElTabPane,
    ElTooltip,
    ElCheckbox,
    ElCheckboxGroup,
    ElPopconfirm,
    ElDropdown,
    ElDropdownMenu,
    ElDropdownItem,
    ElDatePicker
];

components.forEach(component => {
    app.component(component.name, component)
})

const plugins = [
    ElLoading,
    ElMessage,
    ElMessageBox,
    ElNotification
];

plugins.forEach(plugin => {
    app.use(plugin)
});


export default app;
