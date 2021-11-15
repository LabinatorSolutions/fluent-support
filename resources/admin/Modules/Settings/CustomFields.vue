<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('Custom Ticket Fields')}}</h3>
                </div>
                <div v-if="has_pro" class="fs_box_actions">
                    <el-button @click="addFieldVisible = true" type="primary" icon="el-icon-plus" size="small">
                        {{$t('Add New Field')}}
                    </el-button>
                </div>
            </div>
            <template v-if="has_pro">
                <div v-if="!loading" class="fs_box_body">
                    <el-table :empty-text="$t('No Custom Fields Found')" border stripe :data="fields">
                        <el-table-column :label="$t('Label')" prop="label">
                            <template #default="scope">
                                {{scope.row.label || scope.row.admin_label}}
                                <i title="Agent Only Field" v-if="scope.row.admin_only == 'yes'" class="el-icon-lock" />
                                <i title="Conditional Field" v-if="scope.row.has_logics == 'yes'" class="el-icon-connection" />
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('Slug')" prop="slug"></el-table-column>
                        <el-table-column :label="$t('Type')" prop="type"></el-table-column>
                        <el-table-column width="160" :label="$t('Actions')">
                            <template #default="scope">
                                <el-button type="text" @click="updateFieldModal(scope.$index)" size="medium"
                                           icon="el-icon-edit"></el-button>
                                <el-popconfirm title="Are you sure to delete this field?"
                                               @confirm="deleteField(scope.$index)">
                                    <template #reference>
                                        <el-button type="text" size="medium" icon="el-icon-delete" style="color: red; margin-right: .3em;"></el-button>
                                    </template>
                                </el-popconfirm>

                                <el-button-group>
                                    <el-button @click="movePosition(scope.$index, 'up')" :disabled="scope.$index == 0"
                                               size="mini" icon="el-icon-arrow-up"></el-button>
                                    <el-button @click="movePosition(scope.$index, 'down')"
                                               :disabled="scope.$index == (fields.length - 1)" size="mini"
                                               icon="el-icon-arrow-down"></el-button>
                                </el-button-group>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
                <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                    <el-skeleton :rows="5" animated/>
                </div>
            </template>
            <div class="fs_narrow_promo" v-else>
                <h3>{{$t('tk_data_collect_using_customfield')}}</h3>
                <p>{{$t('pro_promo')}}</p>
                <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{$t('Upgrade To Pro')}}</a>
            </div>
        </div>
        <el-dialog
            :title="$t('Add New Custom Field')"
            v-model="addFieldVisible"
            :append-to-body=true
            width="60%">
            <custom-field-form form_type="new" :field_types="field_types" :item="new_item"></custom-field-form>
            <div slot="footer" class="dialog-footer">
                <el-button @click="createField()" type="success" size="small">Add</el-button>
            </div>
        </el-dialog>

        <el-dialog
            :title="$t('Update Custom Field')"
            v-model="updateFieldVisible"
            :append-to-body=true
            width="60%">
            <custom-field-form v-if="updateFieldVisible" :fields="fields" form_type="update" :field_types="field_types" :item="update_field"></custom-field-form>
            <div slot="footer" class="dialog-footer">
                <el-button @click="updateField()" type="success" size="small">Update Field</el-button>
            </div>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import CustomFieldForm from './_CustomFieldForm';

export default {
    name: 'CustomFields',
    components: {
        CustomFieldForm
    },
    data() {
        return {
            fields: [],
            loading: false,
            new_item: {},
            field_types: {},
            addFieldVisible: false,
            updateFieldVisible: false,
            update_field: {}
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('ticket-custom-fields', {
                with: ['field_types']
            })
                .then((response) => {
                    this.fields = response.fields;
                    this.field_types = response.field_types;
                })
                .catch((error) => {
                    this.handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        saveFields() {
            this.loading = true;
            this.$post('ticket-custom-fields', {
                fields: JSON.stringify(this.fields)
            })
                .then((response) => {
                    this.fields = response.fields;
                    this.$notify.success(response.message);
                })
                .catch((error) => {
                    this.handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        createField() {
            if (!this.validateField(this.new_item)) {
                return false;
            }
            this.fields.push(this.new_item);
            this.addFieldVisible = false;
            this.new_item = {};
            this.saveFields();
        },
        validateField(item) {
            if (!item.label) {
                this.$notify.error('Please Provide label');
                return false;
            }
            if (item.options && !item.options.length) {
                this.$notify.error('Please Field Option Values');
                return false;
            }
            return true;
        },
        updateFieldModal(fieldIndex) {
            this.update_field = this.fields[fieldIndex];
            this.updateFieldVisible = true;
        },
        updateField() {
            if (!this.validateField(this.update_field)) {
                return false;
            }
            this.updateFieldVisible = false;
            this.update_field = {};
            this.saveFields();
        },
        deleteField(fieldIndex) {
            this.fields.splice(fieldIndex, 1);
            this.saveFields();
        },
        movePosition(fromIndex, type) {
            let toIndex = fromIndex - 1;
            if (type === 'down') {
                toIndex = fromIndex + 1;
            }
            const fields = this.fields;
            const element = fields[fromIndex];
            fields.splice(fromIndex, 1);
            fields.splice(toIndex, 0, element);
            this.fields = fields;
            this.saveFields();
        }
    },
    mounted() {
        this.$setTitle('Custom Ticket Fields');
        if (this.has_pro) {
            this.fetch();
        }
    }
}
</script>
