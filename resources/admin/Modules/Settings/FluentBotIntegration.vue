<template>
    <div class="fs_bot_container">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h2>Fluent Bot Integration</h2>
                </div>
                <div class="fs_box_actions fs_toggle_container">
                    <span class="fs_toggle_label">Disabled</span>
                    <el-switch
                        v-model="isEnabled"
                        active-value="true"
                        inactive-value="false"
                    />
                    <span class="fs_toggle_label" :class="{ active: isEnabled === 'true' }"> Enabled </span>
                </div>
            </div>
            <div class="fs_box_body">
                <!-- General Bot Configuration -->
                <div class="fs_section">
                    <h3>General Bot</h3>
                    <p class="fs_section_description">
                        This bot will handle general queries for all products
                    </p>

                    <div class="fs_bot_config_card">
                        <div class="fs_input_row">
                            <label>API Key</label>
                            <el-input
                                v-model="config.generalApiKey"
                                show-password
                                placeholder="Enter API Key"
                            />
                        </div>
                        <div class="fs_input_row">
                            <label>Bot ID</label>
                            <el-input
                                v-model="config.generalBotId"
                                placeholder="Enter General Bot ID"
                            />
                        </div>
                    </div>
                </div>

                <!-- Product-specific Configuration -->
                <div class="fs_section">
                    <h3>Product-Specific Bots</h3>
                    <p class="fs_section_description">
                        Configure specialized bots trained for specific products
                    </p>

                    <div class="fs_product_mappings">
                        <div class="fs_card_list">
                            <el-card
                                v-for="(mapping, index) in config.productMappings"
                                :key="index"
                                class="fs_mapping_card"
                                shadow="hover"
                            >
                                <div class="fs_product_header">
                                <span class="fs_product_name">
                                    {{ mapping.productTitle }}
                                </span>
                                    <el-button
                                        type="danger"
                                        circle
                                        size="small"
                                        @click="removeMapping(index)"
                                        class="fs_delete_btn"
                                    >
                                        <el-icon><Delete /></el-icon>
                                    </el-button>
                                </div>

                                <div class="fs_input_row">
                                    <label>API Key</label>
                                    <el-input
                                        v-model="mapping.apiKey"
                                        show-password
                                        placeholder="Enter API Key"
                                    />
                                </div>

                                <div class="fs_input_row">
                                    <label>Bot ID</label>
                                    <el-input
                                        v-model="mapping.botId"
                                        placeholder="Enter Bot ID"
                                    />
                                </div>
                            </el-card>
                        </div>
                        <div class="fs_add_product_section">
                            <el-select
                                v-model="selectedProduct"
                                placeholder="Select a product"
                                class="fs_product_select"
                            >
                                <el-option
                                    v-for="product in availableProducts"
                                    :key="product.id"
                                    :label="product.title"
                                    :value="product.id"
                                />
                            </el-select>
                            <el-button
                                type="success"
                                @click="addProductMapping"
                                :disabled="!selectedProduct"
                            >
                                + Add Product Bot
                            </el-button>
                        </div>
                    </div>
                </div>

                <div class="fs_actions">
                    <el-button type="success" @click="saveConfiguration">Save Configuration</el-button>
                </div>
            </div>
        </div>
    </div>

</template>

<script>
import { Delete } from '@element-plus/icons-vue';
import { ElNotification, ElMessage } from 'element-plus';

export default {
    name: 'FluentBotIntegration',
    components: { Delete },
    data() {
        return {
            isEnabled: 'true',
            isLoading: false,
            isSaving: false,
            config: {
                generalApiKey: '',
                generalBotId: '',
                productMappings: []
            },
            allProducts: [],
            selectedProduct: null,
            originalConfig: null,
            apiEndpoint: '/api/fluent-bot'
        };
    },
    computed: {
        availableProducts() {
            const mappedIds = this.config.productMappings.map(m => m.productId);
            return this.allProducts.filter(p => !mappedIds.includes(p.id));
        }
    },
    methods: {
        fetchData() {
            this.isLoading = true;

            this.$get("settings/fluent-bot-integration")
                .then(data => {
                    this.config = {
                        generalApiKey: data.generalApiKey || '',
                        generalBotId: data.generalBotId || '',
                        productMappings: data.productMappings || []
                    };
                    this.allProducts = data.products || [];
                    this.isEnabled = data.isEnabled || 'false';
                    this.originalConfig = JSON.parse(JSON.stringify(this.config));
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                    this.showError('Failed to load data. Please refresh the page and try again.');
                });
        },

        saveConfiguration() {
            this.isSaving = true;

            this.$post("settings/fluent-bot-integration", {
                ...this.config,
                isEnabled: this.isEnabled
            })
            .then(() => {
                this.originalConfig = JSON.parse(JSON.stringify(this.config));
                this.isSaving = false;
                this.showSuccess('Configuration saved successfully');
            })
            .catch(() => {
                this.isSaving = false;
                this.showError('Failed to save configuration');
            });
        },

        addProductMapping() {
            if (!this.selectedProduct) return;

            const product = this.allProducts.find(p => p.id === this.selectedProduct);
            this.config.productMappings.push({
                productId: product.id,
                productTitle: product.title,
                apiKey: '',
                botId: ''
            });
            this.selectedProduct = null;
        },

        removeMapping(index) {
            this.config.productMappings.splice(index, 1);
        },

        showSuccess(message) {
            ElNotification({
                title: 'Success',
                message,
                type: 'success',
                position: 'bottom-right'
            });
        },

        showError(message) {
            ElNotification({
                title: 'Error',
                message,
                type: 'error',
                position: 'bottom-right'
            });
        }
    },

    watch: {
        isEnabled(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.saveConfiguration();
            }
        }
    },
    mounted() {
        this.fetchData();
    }
};
</script>


<style scoped lang="scss">
.fs_bot_container {
    .fs_box_wrapper {
        .fs_box_header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            .fs_box_head {
                h2 {
                    margin: 0;
                }
            }

            .fs_box_actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;

                .fs_toggle_label {
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: #6b7280;
                    transition: all 0.2s ease-in-out;

                    &.active {
                        color: #4361ee;
                    }
                }
            }
        }

        .fs_box_body {
            padding: 1.5rem;

            .fs_section {
                margin-bottom: 2rem;
                background: #f9fafb;
                border-radius: 0.5rem;
                padding: 1.5rem;
                border: 1px solid #e5e7eb;

                h3 {
                    font-size: 1.125rem;
                    font-weight: 600;
                    color: #1f2937;
                    margin: 0 0 0.75rem 0;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;

                    &::before {
                        content: '';
                        display: block;
                        width: 4px;
                        height: 1.125rem;
                        background: #4361ee;
                        border-radius: 2px;
                    }
                }

                .fs_section_description {
                    color: #6b7280;
                    font-size: 0.875rem;
                    margin: 0 0 1.25rem 0;
                    line-height: 1.5;
                }

                .fs_bot_config_card {
                    background: #ffffff;
                    border-radius: 0.375rem;
                    padding: 1.5rem;
                    border: 1px solid #e5e7eb;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                    transition: all 0.2s ease-in-out;

                    &:hover {
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                        0 2px 4px -1px rgba(0, 0, 0, 0.06);
                    }
                }

                .fs_product_mappings {
                    display: grid;
                    gap: 1rem;
                    margin-top: 1.5rem;
                    .fs_card_list {
                        display: grid;
                        gap: 1rem;
                        margin-top: 1.5rem;

                        .fs_mapping_card {
                            background: #ffffff;
                            border-radius: 0.375rem;
                            border: 1px solid #e5e7eb;
                            overflow: hidden;
                            transition: all 0.2s ease-in-out;

                            &:hover {
                                transform: translateY(-2px);
                                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                                0 2px 4px -1px rgba(0, 0, 0, 0.06);
                            }

                            .fs_product_header {
                                padding: 0.75rem 1.25rem;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;

                                .fs_product_name {
                                    font-weight: 600;
                                    color: #4361ee;
                                    font-size: 0.9375rem;
                                }

                                .fs_delete_btn {
                                    border-color: #ef4444;

                                    &:hover {
                                        color: #ffffff;
                                    }
                                }
                            }
                        }
                    }

                    .fs_add_product_section {
                        display: flex;
                        align-items: center;
                        margin-top: 1.5rem;
                        padding-top: 1.5rem;
                        border-top: 1px dashed #e5e7eb;

                        .fs_product_select {
                            flex: 1;
                            margin-right: 0.75rem;
                        }
                    }
                }
            }

            .fs_actions {
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
                margin-top: 2rem;
                padding-top: 1.5rem;
                border-top: 1px solid #e5e7eb;
            }
        }
    }

    .fs_input_row {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0 1.25rem;

        &:last-child {
            margin-bottom: 1.25rem;
        }

        label {
            width: 80px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            margin-right: 1rem;
        }

        .el-input {
            flex: 1;
        }
    }
}

// Responsive styles
@media (max-width: 768px) {
    .fs_bot_container {
        .fs_box_wrapper {
            .fs_box_header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .fs_box_body {
                .fs_input_row {
                    flex-direction: column;
                    align-items: flex-start;

                    label {
                        margin-bottom: 0.5rem;
                        margin-right: 0;
                    }

                    .el-input {
                        width: 100%;
                    }
                }

                .fs_add_product_section {
                    flex-direction: column;
                    gap: 0.75rem;

                    .fs_product_select {
                        width: 100%;
                        margin-right: 0;
                    }
                }

                .fs_actions {
                    flex-direction: column;

                    .el-button {
                        width: 100%;
                    }
                }
            }
        }
    }
}
</style>



