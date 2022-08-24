import { createApp } from 'vue';
import {
    ElLoading,
    ElMessage,
    ElMessageBox,
    ElNotification
} from 'element-plus';

import {
    Notebook,
    ChatLineSquare,
    User,
    Lightning,
    Refresh,
    Goods,
    OfficeBuilding,
    Service,
    Message,
    ArrowDown,
    ArrowUp,
    EditPen,
    Delete,
    Paperclip,
    Stopwatch,
    Tickets,
    View,
    Plus,
    Flag,
    CaretBottom,
    CaretTop,
    Search,
    Setting,
    Lock,
    CollectionTag,
    Document,
    Cpu,
    AlarmClock,
    Connection,
    InfoFilled,
    CircleCheck,
    UploadFilled,
    Folder,
    ChatLineRound,
    PriceTag,
    Finished,
    Box,
    More,
    Close,
    Aim,
    CircleClose,
    Camera,
    TopLeft,
    Timer
} from '@element-plus/icons-vue/dist';

const icons = [
    Notebook,
    ChatLineSquare,
    User,
    Lightning,
    Refresh,
    Goods,
    OfficeBuilding,
    Service,
    Message,
    ArrowDown,
    ArrowUp,
    EditPen,
    Delete,
    Paperclip,
    Stopwatch,
    Tickets,
    View,
    Plus,
    Flag,
    CaretBottom,
    CaretTop,
    Search,
    Setting,
    Lock,
    CollectionTag,
    Document,
    Cpu,
    AlarmClock,
    Connection,
    InfoFilled,
    CircleCheck,
    UploadFilled,
    Folder,
    ChatLineRound,
    PriceTag,
    Finished,
    Box,
    More,
    Close,
    Aim,
    CircleClose,
    Camera,
    TopLeft,
    Timer
];

const app = createApp({});

const plugins = [
    ElLoading,
    ElMessage,
    ElMessageBox,
    ElNotification
];

plugins.forEach(plugin => {
    app.use(plugin)
});

icons.forEach(icon => {
    app.component(icon.name, icon);
})

export default app;
