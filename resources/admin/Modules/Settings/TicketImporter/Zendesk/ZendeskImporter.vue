<template>
    <div class="fs_zendesk_importer">
        <el-form :data="settings" label-position="top">
            <el-form-item :label="$t('Zendesk Domain')" class="fs_form_item">
                <el-input class="fs_text_input" v-model="settings.domain" :placeholder="$t('Zendesk Domain')"/>
            </el-form-item>
            <el-form-item :label="$t('Email Address')" class="fs_form_item">
                <el-input class="fs_text_input" type="email" v-model="settings.email" :placeholder="$t('Email Address')"/>
            </el-form-item>
            <el-form-item :label="$t('API Key')" class="fs_form_item">
                <el-input class="fs_text_input" v-model="settings.access_token" :placeholder="$t('API Key')"/>
            </el-form-item>
        </el-form>

        <!-- Footer actions -->
        <div class="fs_importer_footer">
            <div class="fs_importer_actions">
                <el-button class="fs_outline_btn" @click="$emit('close')">{{ $t('Cancel') }}</el-button>
                <el-button
                    class="fs_filled_btn"
                    @click="$emit('import')"
                    :disabled="!settings.domain || !settings.email || !settings.access_token">
                    {{ $t('Import Tickets') }}
                </el-button>
            </div>
        </div>
    </div>
</template>

<script>

export default {
    name: 'ZendeskImporter',
    props: ['show', 'settings'],
    emits: ['import', 'close'],
    data() {
        return {
            mailboxes: {}
        };
    }
}
</script>

