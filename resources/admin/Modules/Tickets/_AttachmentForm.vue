<template>
    <div class="fs_attachments_form">
        <el-upload
            class="upload-demo"
            :action="upload_url"
            :on-remove="handleRemove"
            :on-success="handleUploadSuccess"
            :with-credentials="true"
            :on-error="handleError"
            multiple
            :headers="requestHeaders"
            :data="upload_data"
            :limit="3"
            :on-exceed="handleExceed"
            :file-list="file_lists"
            list-type="picture"
        >
            <el-button size="default" type="primary">{{ translate('Click to upload') }}</el-button>
            <template #tip>
                <div class="el-upload__tip">{{ translate('allowed_files_and_size') }}</div>
            </template>
        </el-upload>
        <p style="color: red;" v-if="error_message" @click="error_message = ''">{{ error_message }}</p>

    </div>
</template>

<script type="text/babel">
import {reactive, toRefs} from "vue";
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'AttachmentForm',
    props: ['ticket', 'attachments'],

    setup(props) {
        const {
            translate,
            appVars,
            convertToText
        } = useFluentHelper();

        const state = reactive({
            upload_url: appVars.rest.url + '/ticket_file_upload',
            upload_data: {
                ticket_id: props.ticket.id,
                intended_ticket_hash: appVars.intended_ticket_hash
            },
            file_lists: [],
            requestHeaders: {
                'X-WP-Nonce': appVars.rest.nonce
            },
            error_message: '',
            dialogImageUrl: '',
            dialogImageTitle: '',
            dialogVisible: false,
        });

        const handleRemove = (file, fileList) => {
            state.error_message = '';
            props.attachments.splice(props.attachments.indexOf(file.response.attachments[0]), 1);
        };

        const handleExceed = (files, fileList) => {
            state.error_message = `${translate('You can upload maximum')} ${fileList.length} ${translate('files')}`;
        };

        const handleError = (err, file, fileList) => {
            let message = convertToText(JSON.parse(err.message));
            if (!message) {
                message = translate('File failed to upload');
            }

            message = file.name + ': ' + message;
            state.error_message = message;
        };

        const handleUploadSuccess = (response, file, fileList) => {
            state.error_message = '';
            props.attachments.push(...response.attachments);

            if (file.raw.type.includes('application') || file.raw.type.includes('text')) {
                if (appVars.asset_url) {
                    return file.url = appVars.asset_url + 'images/icons/file.svg';
                }
                return file.url = appVars.fallback_image;
            }
        }

        return {
            ...toRefs(state),
            handleRemove,
            handleExceed,
            handleError,
            handleUploadSuccess,
            translate,
        }
    }
}
</script>

<style scoped>
.el-dialog__body img {
    width: 50%;
    margin-left: 12em;
}
</style>
