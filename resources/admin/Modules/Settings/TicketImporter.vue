<template>
	<div style="background: white;" class="fs_box_body" v-if="loading">
        <el-skeleton class="fs_box_wrapper" :rows="5" animated/>
    </div>
	<el-row style="padding: 45px 25px" v-if="!loading && settings.length" :gutter="20">
	    <el-col v-for="setting in settings" :span="8">
	    	<div :class="'grid-content fs_'+setting.handler">
	    		<el-card :body-style="{ padding: '0px' }" :header=setting.name>
			        <div style="padding: 14px">
		        		<el-tag class="ml-2" type="info">Tickets: {{setting.tickets}}</el-tag>
		        		<el-tag class="ml-2" type="info">Replies: {{setting.replies}}</el-tag>
		        		<el-progress
		            			v-if="imporing && currently_importing == setting.handler"
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
							    width="30"
							    @confirm="importTickets(setting.handler,'yes')"
							    @cancel="importTickets(setting.handler,'no')"
							    title="Delete tickets from previous system after import."
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
  	<div class="fs_no_active_support_system" v-if="!loading && !settings.length">
  		<h3> {{$t('no_other_support_system_is_activate')}} </h3>
  	</div>

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
	        	completed: 0,
	        	loading: false,
	        	currently_importing: ''
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
				this.loading=true;
				this.$get('ticket_importer').then((response) => {
					this.settings = response;
					this.loading=false;
				}).catch((e) => {
					this.$handleError(e);
				});
			},
			importTickets(handler, maybeDeleteTickets) {
				this.imporing = true;
				this.currently_importing = handler;
				if (handler == 'support-candy' && !this.total_tickets){
					this.import_page = 0;
				}
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
                            this.importTickets( handler, maybeDeleteTickets );
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
        	this.$setTitle('Ticket Importer');
    	}
    }
</script>

<style lang="scss" scoped>
	.fs_no_active_support_system {
	    display: flex;
	    flex-direction: column;
	    align-items: center;
	    background: #ffffff;
	    margin: 35px;
	    & h3 {
		    text-align: center;
	        line-height: 32px;
		}
	}
</style>