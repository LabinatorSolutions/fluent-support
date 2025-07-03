<template>
    <el-card class="fluent-bot-container">
        <div class="header">
            <div class="title-area">
                <h2>Fluent Bot Integration</h2>
                <p class="subtitle">Connect your products with AI-powered support</p>
            </div>
            <div class="toggle-container">
                <span class="toggle-label">Disabled</span>
                <el-switch v-model="isEnabled" />
                <span class="toggle-label" :class="{ 'active': isEnabled }">Enabled</span>
            </div>
        </div>

        <el-divider />

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
                        <span class="product-name">{{ mapping.productName }}</span>
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
                            :label="product.name"
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
    </el-card>
</template>

<script>
import { Delete } from '@element-plus/icons-vue';
import { ElNotification } from 'element-plus';

export default {
    name: 'FluentBotIntegration',
    components: {
        Delete
    },
    data() {
        return {
            isEnabled: true,
            config: {
                generalApiKey: 'sk_fluent_bot_5823a9f71c2e4b8d',
                generalBotId: 'bot_general_9283',
                productMappings: [
                    {
                        productId: 1,
                        productName: 'Email Marketing Suite',
                        apiKey: 'sk_fluent_email_8472e0a31b5d',
                        botId: 'bot_email_4582'
                    },
                    {
                        productId: 2,
                        productName: 'CRM Platform',
                        apiKey: 'sk_fluent_crm_9472c1f59e7a',
                        botId: 'bot_crm_7391'
                    }
                ]
            },
            allProducts: [
                { id: 1, name: 'Email Marketing Suite' },
                { id: 2, name: 'CRM Platform' },
                { id: 3, name: 'Analytics Dashboard' },
                { id: 4, name: 'Social Media Manager' },
                { id: 5, name: 'E-commerce Platform' },
            ],
            selectedProduct: null,
            originalConfig: null
        }
    },
    computed: {
        availableProducts() {
            const mappedProductIds = this.config.productMappings.map(m => m.productId);
            return this.allProducts.filter(p => !mappedProductIds.includes(p.id));
        }
    },
    mounted() {
        // Store original config for reset functionality
        this.originalConfig = JSON.parse(JSON.stringify(this.config));
    },
    methods: {
        addProductMapping() {
            if (!this.selectedProduct) return;

            const product = this.allProducts.find(p => p.id === this.selectedProduct);

            this.config.productMappings.push({
                productId: product.id,
                productName: product.name,
                apiKey: '',
                botId: ''
            });

            this.selectedProduct = null;
        },

        removeMapping(index) {
            this.config.productMappings.splice(index, 1);
        },

        saveConfiguration() {
            // In a real app, this would make an API call
            ElNotification({
                title: 'Success',
                message: 'Fluent Bot configuration has been saved',
                type: 'success',
                position: 'bottom-right'
            });

            // Update original config after saving
            this.originalConfig = JSON.parse(JSON.stringify(this.config));
        },

        resetForm() {
            this.config = JSON.parse(JSON.stringify(this.originalConfig));
            ElNotification({
                title: 'Reset Complete',
                message: 'Form has been reset to last saved state',
                type: 'info',
                position: 'bottom-right'
            });
        }
    }
}
</script>


