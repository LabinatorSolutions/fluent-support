<template>
    <div class="fluent_bot_container">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h2>Fluent Bot Integration</h2>
                </div>
                <div class="fs_box_actions">
                    <span class="toggle-label">Disabled</span>
                    <el-switch
                        v-model="isEnabled"
                        active-value="true"
                        inactive-value="false"
                    />
                    <span class="toggle-label" :class="{ active: isEnabled === 'true' }"> Enabled </span>
                </div>
            </div>
            <div class="fs_box_body">
                <!-- General Bot Configuration -->
                <div class="section">
                    <h3>General Bot</h3>
                    <p class="section-description">This bot will handle general queries for all products</p>

                    <div class="bot-config-card">
                        <div class="input-row">
                            <label>API Key</label>
                            <el-input v-model="config.generalApiKey" show-password placeholder="Enter API Key" />
                        </div>
                        <div class="input-row">
                            <label>Bot ID</label>
                            <el-input v-model="config.generalBotId" placeholder="Enter General Bot ID" />
                        </div>
                    </div>
                </div>

                <!-- Product-specific Configuration -->
                <div class="section">
                    <h3>Product-Specific Bots</h3>
                    <p class="section-description">Configure specialized bots trained for specific products</p>

                    <div class="product-mappings">
                        <el-card
                            v-for="(mapping, index) in config.productMappings"
                            :key="index"
                            class="mapping-card"
                            shadow="hover"
                        >
                            <div class="product-header">
                                <span class="product-name">{{ mapping.productTitle }}</span>
                                <el-button
                                    type="danger"
                                    circle
                                    size="small"
                                    @click="removeMapping(index)"
                                    class="delete-btn"
                                >
                                    <el-icon><Delete /></el-icon>
                                </el-button>
                            </div>

                            <div class="input-row">
                                <label>API Key</label>
                                <el-input v-model="mapping.apiKey" show-password placeholder="Enter API Key" />
                            </div>

                            <div class="input-row">
                                <label>Bot ID</label>
                                <el-input v-model="mapping.botId" placeholder="Enter Bot ID" />
                            </div>
                        </el-card>

                        <div class="add-product-section">
                            <el-select
                                v-model="selectedProduct"
                                placeholder="Select a product"
                                class="product-select"
                            >
                                <el-option
                                    v-for="product in availableProducts"
                                    :key="product.id"
                                    :label="product.title"
                                    :value="product.id"
                                />
                            </el-select>
                            <el-button type="primary" @click="addProductMapping" :disabled="!selectedProduct">
                                + Add Product Bot
                            </el-button>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <el-button plain @click="resetForm">Reset</el-button>
                    <el-button type="primary" @click="saveConfiguration">Save Configuration</el-button>
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

        resetForm() {
            this.config = JSON.parse(JSON.stringify(this.originalConfig));
            ElMessage({
                message: 'Form has been reset to last saved state',
                type: 'info'
            });
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
    mounted() {
        this.fetchData();
    }
};
</script>


<style scoped>



.toggle-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.toggle-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    transition: all 0.2s ease-in-out;
}

.toggle-label.active {
    color: #4361ee;
}

.fs_box_body {
    padding: 1.5rem;
}

.section {
    margin-bottom: 2rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
}

.section h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section h3:before {
    content: '';
    display: block;
    width: 4px;
    height: 1.125rem;
    background: #4361ee;
    border-radius: 2px;
}

.section-description {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0 0 1.25rem 0;
    line-height: 1.5;
}

/* General Bot Section Specific */
.bot-config-card {
    background: #ffffff;
    border-radius: 0.375rem;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease-in-out;
}

.bot-config-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Product Mappings Section */
.product-mappings {
    display: grid;
    gap: 1rem;
    margin-top: 1.5rem;
}

.mapping-card {
    background: #ffffff;
    border-radius: 0.375rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.2s ease-in-out;
}

.mapping-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.product-header {
    padding: 0.75rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-name {
    font-weight: 600;
    color: #4361ee;
    font-size: 0.9375rem;
}

.delete-btn {
    border-color: #ef4444;
}

.delete-btn:hover {
    color: #ffffff;
}

.input-row {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    padding: 0 1.25rem;
}

.input-row:last-child {
    margin-bottom: 1.25rem;
}

.input-row label {
    width: 80px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    margin-right: 1rem;
}

.input-row .el-input {
    flex: 1;
}

.add-product-section {
    display: flex;
    align-items: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px dashed #e5e7eb;
}

.product-select {
    flex: 1;
    margin-right: 0.75rem;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .fs_box_header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .input-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .input-row label {
        margin-bottom: 0.5rem;
        margin-right: 0;
    }

    .input-row .el-input {
        width: 100%;
    }

    .add-product-section {
        flex-direction: column;
        gap: 0.75rem;
    }

    .product-select {
        width: 100%;
        margin-right: 0;
    }

    .actions {
        flex-direction: column;
    }

    .actions .el-button {
        width: 100%;
    }
}
</style>

