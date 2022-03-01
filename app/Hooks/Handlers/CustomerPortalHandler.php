<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class CustomerPortalHandler
{
    public function renderPortal()
    {
        $invalidPermissionMessage = __('You don\'t have permission to access customer support portal', 'fluent-support');

        $person = Helper::getCurrentPerson();
        if ($person && $person->status === 'inactive') {
            return '<div id="fluent_support_client_app" style="text-align: center;"><h3 class="fs_customer_restriction">' . $invalidPermissionMessage . '</h3></div>';
        } else if (PermissionManager::currentUserPermissions()) {
            $adminPortalUrl = Helper::getPortalAdminBaseUrl();
            return '<div style="text-align: center;"><h3>' . __('Customer Portal is only accessible by Customers. Looks like you are a support staff', 'fluent-support') . '</h3><a href="' . $adminPortalUrl . '">' . __('Go to Support Admin Page', 'fluent-support') . '</a></div>';
        } else if ($this->hasCustomerPortalAccess()) {

            /*
            * Filter customer portal access settings
            *
            * @since v1.0.0
            *
            * @param array $canAccess
            */
            $canAccess = apply_filters('fluent_support/user_portal_access_config', [
                'status'  => true,
                'message' => $invalidPermissionMessage
            ]);

            if (empty($canAccess['status'])) {
                $invalidPermissionMessage = Arr::get($canAccess, 'message', $invalidPermissionMessage);
                return '<div id="fluent_support_client_app" style="text-align: center;"><h3 class="fs_customer_restriction">' . $invalidPermissionMessage . '</h3></div>';
            }

            if (!$person) {
                $this->maybeCreateCustomer();
            }

            $this->enqueueScripts();

            return '<div id="fluent_support_client_app"><h3 class="fs_loading_text">' . __('Loading Customer Portal. Please wait...', 'fluent-support') . '</h3></div>';
        } else {

            $businessSettings = Helper::getBusinessSettings();
            $loggedInMessage = Arr::get($businessSettings, 'login_message', '');

            $loggedInMessage = str_replace('[fluent_support_portal]', '', $loggedInMessage);

            return do_shortcode($loggedInMessage);
        }
    }

    public function enqueueScripts()
    {
        $app = App::getInstance();

        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');
        $slug = $app->config->get('app.slug');

        $restInfo = [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $v . '/customer-portal'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v,
        ];

        $assets = $app['url.assets'];

        wp_enqueue_script('fs_tk_customer_portal', $assets . 'portal/js/app.js', ['jquery'], FLUENT_SUPPORT_VERSION);
        wp_enqueue_style('fs_tk_customer_portal', $assets . 'portal/css/app.css', [], FLUENT_SUPPORT_VERSION);

        $i18ns = [
            'Conversation'                => __('Conversation', 'fluent-support'),
            'Click Here to Write a reply' => __('Click Here to Write a reply', 'fluent-support'),
            'All'                         => __('All', 'fluent-support'),
            'Open'                        => __('Open', 'fluent-support'),
            'Closed'                      => __('Closed', 'fluent-support'),
            'Date'                        => __('Date', 'fluent-support'),
            'Status'                      => __('Status', 'fluent-support'),
            'Next'                        => __('Next', 'fluent-support'),
            'Prev'                        => __('Prev', 'fluent-support'),
            'agent_and_officials_can_see' => __('Only you and official support agents can view this conversation', 'fluent-support'),
            'reopen_ticket_instruction'   => __('If you still have related issues. Please reopen this ticket and reply', 'fluent-support'),
            'ticket_closed'               => __('This ticket has been closed at', 'fluent-support'),
            'Close Ticket'                => __('Close Ticket', 'fluent-support'),
            'replied'                     => __('replied', 'fluent-support'),
            'conversation_started'        => __('started the conversation', 'fluent-support'),
            'Click to upload'             => __('Click to upload', 'fluent-support'),
            'This ticket is'              => __('This ticket is', 'fluent-support'),
            'Private'                     => __('Private', 'fluent-support'),
            'Write a reply'               => __('Write a reply', 'fluent-support'),
            'Reply'                       => __('Reply', 'fluent-support'),
            'You'                         => __('You', 'fluent-support'),
            'no_open_support_tickets'     => __('Looks like you did not open any support tickets yet', 'fluent-support'),
            'no_support_ticket_found'     => __('No support tickets found for this filter', 'fluent-support'),
            'Delete Customer'             => __('Delete Customer', 'fluent-support'),
            'Update Customer'             => __('Update Customer', 'fluent-support'),
            'Create Customer'             => __('Create Customer', 'fluent-support'),

            'subject_placeholder'  => __('What\'s this support ticket about?', 'fluent-support'),
            'service_placeholder'  => __('Select related Product/Service', 'fluent-support'),
            'suggestion_loading'   => __('Looking for similar articles...', 'fluent-support'),
            'articles_heading'     => __('Suggested articles', 'fluent-support'),
            'priority_placeholder' => __('Select Priority', 'fluent-support'),

            'subject'           => __('Subject', 'fluent-support-pro'),
            'ticket_details'    => __('Ticket Details', 'fluent-support-pro'),
            'details_help'      => __('Please provide details about your problem', 'fluent-support-pro'),
            'product_services'  => __('Related Product/Service', 'fluent-support-pro'),
            'priority'          => __('Priority', 'fluent-support-pro'),
            'btn_text'          => __('Create Ticket', 'fluent-support-pro'),
            'submit_heading'    => __('Submit a Support Ticket', 'fluent-support-pro'),
            'create_ticket_cta' => __('Create a New Ticket', 'fluent-support'),
            'Reopen This ticket' => __('Reopen This ticket', 'fluent-support'),
            'by'                => __('by', 'fluent-support')
        ];

        $i18ns['allowed_files_and_size'] = Helper::getFileUploadMessage();

        $data = [
            'rest'                       => $restInfo,
            'nonce'                      => wp_create_nonce($slug),
            'support_products'           => Product::select(['id', 'title'])->get(),
            'customer_ticket_priorities' => Helper::customerTicketPriorities(),
            'view_tickets_url'           => '#/',
            'i18n'                       => $i18ns,
            'fallback_image'             => $assets . 'images/file.png',
            'has_file_upload'            => !!Helper::ticketAcceptedFileMiles(),
            'has_rich_text_editor'       => true
        ];

        if ($this->isSignedTicketView()) {
            $data['intended_ticket_hash'] = sanitize_text_field($_REQUEST['support_hash']);
            $data['view_tickets_url'] = Helper::getPortalBaseUrl() . '/#';
        } else {
            add_filter('user_can_richedit', '__return_true');
        }
        /*
         * Filter customer portal localize javascript data
         *
         *  @since v1.0.0
         *
         * @param array $data
         */
        $data = apply_filters('fluent_support/customer_portal_vars', $data);

        if (!empty($data['has_rich_text_editor'])) {
            wp_tinymce_inline_scripts();
            wp_enqueue_editor();
        }

        wp_localize_script('fs_tk_customer_portal', 'fs_customer_portal', $data);
    }

    public function hasCustomerPortalAccess()
    {
        $userId = get_current_user_id();

        if ($userId) {
            return true;
        }

        return $this->isSignedTicketView();
    }

    protected static function customerStatus()
    {
        $user = get_current_user_id();
        return Customer::where('user_id', $user)->select(['status'])->first();
    }

    protected function isSignedTicketView()
    {
        if (!Helper::isPublicSignedTicketEnabled()) {
            return false;
        }

        return isset($_REQUEST['fs_view']) && $_REQUEST['fs_view'] == 'ticket' && isset($_REQUEST['support_hash']) && isset($_REQUEST['ticket_id']);
    }

    private function maybeCreateCustomer()
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        $person = Helper::getCurrentPerson();
        if ($person) {
            return $person;
        }

        $user = get_user_by('ID', $userId);

        $request = App::request();

        $onBehalf = [
            'user_id'         => $user->id,
            'email'           => $user->user_email,
            'last_ip_address' => $request->getIp()
        ];

        return Customer::maybeCreateCustomer($onBehalf);
    }
}
