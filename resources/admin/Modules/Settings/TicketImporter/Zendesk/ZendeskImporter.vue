<template>
    <Teleport to="body">
        <modal :show="show" title="Import Tickets From Zendesk in Fluent Support" @close="$emit('close')">
            <template #body>
                <el-form :data="settings" label-position="top">
                    <el-form-item :label="translate('Zendesk Domain')">
                        <el-input v-model="settings.domain" :placeholder="translate('Zendesk Domain')"/>
                    </el-form-item>
                    <el-form-item :label="translate('Email Address')">
                        <el-input type="email" v-model="settings.email" :placeholder="translate('Email Address')"/>
                    </el-form-item>
                    <el-form-item :label="translate('API Key')">
                        <el-input v-model="settings.access_token" :placeholder="translate('API Key')"/>
                    </el-form-item>
                </el-form>
            </template>
            <template #footer>
                <span class="dialog-footer">
            <el-button type="primary" @click="$emit('close')">{{ translate('Cancel') }}</el-button>
            <el-button type="success" @click="$emit('import')">
                {{ translate('Import Tickets') }}
            </el-button>
          </span>
            </template>
        </modal>
    </Teleport>
</template>

<script>
import Modal from '../../../../Pieces/Modal';
import {onMounted, reactive, toRefs} from "vue";
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'ZendeskImporter',
    props: ['show', 'settings'],
    emits: ['import', 'close'],
    components: {
        Modal
    },
    setup() {
        const {
            translate,
            has_pro,
        } = useFluentHelper();

        const state = reactive({
            mailboxes: {},
        });

        return {
            translate,
            ...toRefs(state),
        }
    }
}
</script>
