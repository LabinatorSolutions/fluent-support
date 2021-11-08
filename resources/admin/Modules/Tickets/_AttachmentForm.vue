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
            <el-button size="small" type="primary">{{$t('Click to upload')}}</el-button>
            <template #tip>
                <div class="el-upload__tip">{{$t('allowed_files_and_size')}}</div>
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
                ticket_id: this.ticket.id,
                intended_ticket_hash: this.appVars.intended_ticket_hash
            },
            file_lists: [],
            requestHeaders: {
                'X-WP-Nonce': this.appVars.rest.nonce
            },
            error_message: '',
            dialogImageUrl: '',
            dialogImageTitle: '',
            dialogVisible: false,
        }
    },
    methods: {
        handleRemove(file, fileList) {
            this.error_message = '';
            this.attachments.splice(this.attachments.indexOf(file.response.attachments[0]), 1);
        },
        handleExceed(files,fileList) {
            this.error_message = `You can upload maximum ${fileList.length} files`;
        },
        handleError(err, file, fileList) {
            let message = this.convertToText(JSON.parse(err.message));
            if(!message) {
                message = this.$t('File failed to upload');
            }

            message = file.name + ': ' + message;
            this.error_message = message;
        },
        handleUploadSuccess(response, file, fileList) {
            this.error_message = '';
            if(!file.raw.type.includes('image')) {
                if(this.appVars.asset_url) {
                    return file.url = this.appVars.asset_url+'images/file.png';
                }
                return file.url = this.appVars.fallback_image;
            }
            this.attachments.push(...response.attachments);
        }
    }
}
</script>

<style scoped>
.el-dialog__body img{
  width: 50%;
  margin-left: 12em;
}
</style>
