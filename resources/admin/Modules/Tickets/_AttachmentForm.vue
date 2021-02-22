<template>
    <div class="fs_attachments_form">
        <el-upload
            class="upload-demo"
            :action="upload_url"
            :on-preview="handlePreview"
            :on-remove="handleRemove"
            :on-success="handleUploadSuccess"
            :with-credentials="true"
            multiple
            :headers="requestHeaders"
            :data="upload_data"
            :limit="3"
            :on-exceed="handleExceed"
            :file-list="file_lists"
        >
            <el-button size="small" type="primary">Click to upload</el-button>
            <template #tip>
                <div class="el-upload__tip">Files with a size less than 2MB</div>
            </template>
        </el-upload>
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
            }
        }
    },
    methods: {
        handleRemove() {

        },
        handlePreview() {

        },
        handleExceed() {

        },
        handleUploadSuccess(response, file, fileList) {
            this.attachments.push(...response.attachments);
        }
    }
}
</script>
