<template>
<el-form :data="code" label-position="top">
    <div class="fs_resend_email" v-if="!resendText.length">
        <span class="fs_resend_email_text">Haven't received the verification email then request for a resend. </span>
        <el-button type="text" @click="resendEmail">Resend Email</el-button>
    </div>
    <div class="fs_resend_email" v-else>
        <span class="fs_resend_email_text">{{resendText}}</span>
    </div>
    <span class="fst_verify_email_text">Please enter the verification code sent to your email address.</span>
    <el-form-item>
        <el-input v-model="code" placeholder="Verification Code"></el-input>
    </el-form-item>
    <el-form-item>
        <el-button type="primary" @click="verifyEmail">Verify Email</el-button>
    </el-form-item>
</el-form>
</template>

<script>
export default {
    name: "VerifyEmail",
    data() {
        return {
            code: '',
            resendText: ''
        }
    },
    methods: {
        verifyEmail() {
            this.$post('verify_email', {
                code: this.code,
            })
                .then(response => {
                    this.$router.go(0);
                })
                .catch(error => {
                    this.$message.error('Something went wrong. Please try again.')
                })
        },
        resendEmail() {
            this.$post('resend_verify_email')
                .then(response => {
                    this.resendText = response.message;
                })
                .catch(error => {
                    this.$message.error('Something went wrong. Please try again.')
                })
        }
    }
}
</script>

<style scoped>
.fs_resend_email{
    background: #faebd7;
    padding: 12px 5px;
    margin-bottom: 13px;
}
</style>
