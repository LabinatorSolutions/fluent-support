<template>
    <div class="fs_attachments_form">
        <el-upload
            class="upload-demo"
            :action="upload_url"
            :on-preview="handlePreview"
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
        >
            <el-button size="small" type="primary">Click to upload</el-button>
            <template #tip>
                <div class="el-upload__tip">Files with a size less than 2MB. Supported Types: images, text, pdf, zip</div>
            </template>
        </el-upload>
        <p style="color: red;" v-if="error_message" @click="error_message = ''">{{ error_message }}</p>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'AttachmentForm',
    props: ['ticket', 'attachments'],
    data() {
        return {
            upload_url: this.appVars.rest.url+'/ticket_file_upload',
            upload_data: {
                ticket_id: this.ticket.id
            },
            file_lists: [],
            requestHeaders: {
                'X-WP-Nonce': this.appVars.rest.nonce
            },
            error_message: ''
        }
    },
    methods: {
        handleRemove(file, fileList) {
            this.error_message = '';
            this.attachments.splice(this.attachments.indexOf(file.response.attachments[0]), 1);
        },
        handlePreview() {

        },
        handleExceed() {

        },
        handleError(err, file, fileList) {
            console.log(file.name);
            let message = this.convertToText(JSON.parse(err.message));
            if(!message) {
                message = 'File failed to upload';
            }

            message = file.name + ': ' + message;
            this.error_message = message;
        },
        handleUploadSuccess(response, file, fileList) {
            this.error_message = '';
            this.attachments.push(...response.attachments);
        }
    }
}
</script>
