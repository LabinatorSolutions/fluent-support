<?php

namespace FluentSupport\App\Services\Integrations\FluentForm;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Support\Arr;

class FeedIntegration extends IntegrationManager
{

    public $hasGlobalMenu = false;

    public $disableGlobalSettings = 'yes';

    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'FluentSupport',
            'fluent_support',
            '_fluentsupport_settings',
            'fluentform_fluentsupport_feed',
            16
        );

        $this->logo = $this->app->url('public/img/integrations/drip.png');

        $this->description = 'Create Support Ticket From Your Form Submission in FluentSUpport';

        $this->registerAdminHooks();

        add_filter('fluentform_notifying_async_fluent_support', '__return_false');

    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title'                 => $this->title . ' Integration',
            'logo'                  => $this->logo,
            'is_active'             => $this->isConfigured(),
            'configure_title'       => 'Configuration required!',
            'global_configure_url'  => '#',
            'configure_message'     => 'FluentSupport is not configured yet! Please configure your FluentSupport api first',
            'configure_button_text' => 'Set FluentSupport'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name'                      => '',
            'first_name'                => '',
            'last_name'                 => '',
            'email'                     => '',
            'list_id'                   => '', // this is the business ID
            'ticket_title'              => '',
            'ticket_body'               => '',
            'product_id'                => '',
            'product_id_selection_type' => 'simple',
            'product_routers'           => [],
            'conditionals'              => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'                   => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields'              => [
                [
                    'key'         => 'name',
                    'label'       => 'Feed Name',
                    'required'    => true,
                    'placeholder' => 'Your Feed Name',
                    'component'   => 'text'
                ],
                [
                    'key'         => 'list_id',
                    'label'       => 'Business',
                    'placeholder' => 'Select Business',
                    'tips'        => 'Select the Business you would like to add your Support Ticket to.',
                    'component'   => 'select',
                    'required'    => true,
                    'options'     => $this->geMailBoxes(),
                ],
                [
                    'key'                => 'product_id',
                    'require_list'       => false,
                    'label'              => 'Product',
                    'placeholder'        => 'Select Support Product',
                    'component'          => 'selection_routing',
                    'simple_component'   => 'select',
                    'routing_input_type' => 'select',
                    'routing_key'        => 'product_id_selection_type',
                    'settings_key'       => 'product_routers',
                    'is_multiple'        => false,
                    'labels'             => [
                        'choice_label'      => 'Enable Dynamic Product Selection',
                        'input_label'       => '',
                        'input_placeholder' => 'Set Product'
                    ],
                    'options'            => $this->getProducts()
                ],
                [
                    'key'          => 'ticket_title',
                    'require_list' => false,
                    'label'        => 'Ticket Title',
                    'placeholder'  => 'Ticket Title',
                    'component'    => 'value_text'
                ],
                [
                    'key'          => 'ticket_content',
                    'require_list' => false,
                    'label'        => 'Ticket Content',
                    'placeholder'  => 'Ticket Content',
                    'component'    => 'value_textarea'
                ],
                [
                    'component' => 'html_info',
                    'html_info' => '<h4>Please provide the ticket provider info. If user is logged in then it will use that info. For Public users you can set your customer info</h4>'
                ],
                [
                    'key'                => 'CustomFields',
                    'require_list'       => false,
                    'label'              => 'Customer Data',
                    'tips'               => 'Please Map Your Customer Data for this form. If your customer already logged in you can leave this',
                    'component'          => 'map_fields',
                    'field_label_remote' => 'Support Customer Field',
                    'field_label_local'  => 'Form Field',
                    'primary_fileds'     => [
                        [
                            'key'           => 'email',
                            'label'         => 'Email Address',
                            'required'      => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key'   => 'first_name',
                            'label' => 'First Name'
                        ],
                        [
                            'key'   => 'last_name',
                            'label' => 'Last Name'
                        ]
                    ]
                ]
            ],
            'button_require_list' => false,
            'integration_title'   => $this->title
        ];
    }

    private function geMailBoxes()
    {
        $items = MailBox::all();
        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[strval($item->id)] = $item->name;
        }
        return $formattedItems;
    }

    private function getProducts()
    {
        $products = Product::all();
        $formattedProducts = [];
        foreach ($products as $product) {
            $formattedProducts[strval($product->id)] = $product->title;
        }
        return $formattedProducts;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $data = $feed['processedValues'];

        if (!empty($data['email']) && !is_email($data['email'])) {
            $data['email'] = Arr::get($data, $data['email']);
        }

        $ticketData = [
            'product_source' => 'local',
            'mailbox_id' => Arr::get($data, 'list_id'),
            'title' => sanitize_text_field(wp_unslash(Arr::get($data, 'ticket_title'))),
            'content' => wp_unslash(wp_kses_post(Arr::get($data, 'ticket_content'))),
            'source' => 'web'
        ];

        $selectedProductArray = (array) $this->getSelectedTagIds($data, $formData, 'product_id', 'product_id_selection_type', 'product_routers');

        if($selectedProductArray) {
            $selectedProduct = $selectedProductArray[0];
            $ticketData['product_id'] = $selectedProduct;
        }

        $customerData = Arr::only($data, ['first_name', 'last_name', 'email']);
        $user = wp_get_current_user();

        if(!$user) {
            $user = get_user_by('user_email', $customerData['email']);
        }

        if($user) {
            $customerData['email'] = $user->user_email;
            $customerData['user_id'] = $user->ID;
        }

        if(empty($customerData['email'])) {
            do_action('ff_log_data', [
                'title'            => $feed['settings']['name'],
                'status'           => 'failed',
                'description'      => 'Support ticket creation failed, because no valid customer email found',
                'parent_source_id' => $form->id,
                'source_id'        => $entry->id,
                'component'        => $this->integrationKey,
                'source_type'      => 'submission_item'
            ]);
            return false;
        }

        $customerData['last_ip_address'] = $entry->ip;

        $customer = Customer::maybeCreateCustomer($customerData);

        $ticketData['customer_id'] = $customer->id;

        $ticketData = apply_filters('fluent_support/create_ticket_data', $ticketData, $customer);
        do_action('fluent_support/before_ticket_create', $ticketData, $customer);

        $createdTicket = Ticket::create($ticketData);
        $ticket = Ticket::find($createdTicket->id);

        do_action('fluent_support/ticket_created', $ticket, $customer);

        do_action('ff_log_data', [
            'title'            => $feed['settings']['name'],
            'status'           => 'success',
            'description'      => 'Support ticket has been created at Fluent Support. Ticket ID: '.$ticket->id,
            'parent_source_id' => $form->id,
            'source_id'        => $entry->id,
            'component'        => $this->integrationKey,
            'source_type'      => 'submission_item'
        ]);

        return true;
    }

    public function isConfigured()
    {
        return true;
    }

    public function isEnabled()
    {
        return true;
    }

}
