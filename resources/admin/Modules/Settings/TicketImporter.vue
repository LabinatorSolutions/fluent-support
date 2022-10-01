<template>
	<el-row style="padding: 45px 25px">
	    <el-col v-for="setting in settings" :span="8">
	    	<div :class="'grid-content fs_'+setting.handler">
	    		<el-card :body-style="{ padding: '0px' }" :header=setting.name>
			        <div style="padding: 14px">
		        		<el-tag class="ml-2" type="info">Tickets: {{setting.tickets}}</el-tag>
		        		<el-tag class="ml-2" type="info">Replies: {{setting.replies}}</el-tag>
		        		<el-progress
		            			v-if="imporing"
							      :text-inside="true"
							      :stroke-width="24"
							      :percentage="percentage"
							      status="success"
							      style="margin: 5px 0"
						    />
			          	<div class="bottom" style="padding: 15px 0">
			          		<el-popconfirm
							    confirm-button-text="Yes"
							    cancel-button-text="No"
							    @confirm="importTickets(setting.handler, 'yes')"
							    @cancel="importTickets(setting.handler, 'no')"
							    title="Delete tickets from Awesome Support after import."
							  >
							    <template #reference>
							      <el-button type="success" :disabled="imporing">Import Tickets</el-button>
							    </template>
							  </el-popconfirm>
			          	</div>
			        </div>
		      	</el-card>
	    	</div>
	    </el-col>
  	</el-row>
</template>

<script type="text/babel">
	export default {
	    name: 'TicketImporter',
	    data() {
	        return {
	        	settings: {},
	        	imporing: false,
	        	import_page: 1,
	        	total_tickets: 0,
	        	completedPercentage: 0,
	        	completed: 0
	    	}
		},

		computed:{
			percentage() {
				let percentage = parseInt((this.completed / this.total_tickets) * 100);
				return percentage  = !isNaN(percentage) ? percentage : 0;
			}
		},

		methods: {
			fetchSettings() {
				this.$get('ticket_importer').then((response) => {
					this.settings = response;
				}).catch((e) => {
					this.$handleError(e);
				});
			},
			importTickets(handler, maybeDeleteTickets) {
				this.imporing = true;

				this.$post('ticket_importer/import', {
					handler: handler,
					page: this.import_page,
					maybeDeleteTickets: maybeDeleteTickets
				})
				.then(response => {
                    if (response.has_more) {
                        this.import_page = response.next_page;
                        this.total_tickets = response.total_tickets;
                        this.completed += response.completed;
                        this.$nextTick(() => {
                            this.importTickets();
                        });
                    } else {
                    	this.imporing = false;
                    	this.$notify.success({
	                        message: response.message,
	                        position: 'bottom-right'
	                    })
                    }
                })
                .catch((error) => {
                    this.$handleError(error);
                });
			}
		},
		mounted() {
        	this.fetchSettings();
    	}
    }
</script>