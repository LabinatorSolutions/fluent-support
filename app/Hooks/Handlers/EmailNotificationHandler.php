<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Emogrifier;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Mailer;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Framework\Support\Arr;

class EmailNotificationHandler
{
    public function ticketCreated($ticket, $customer)
    {
        $mailbox = $ticket->mailbox;

        if (!$mailbox) {
            return;
        }

        // Let's send welcome email to customer if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_created_email_to_customer');

        // Blocked customers get no email; the admin copy below still goes out.
        // An agent's ticket gets the "Logged by Agent" email instead when it is on (ticketCreatedByAgent()).
        if ($customer && $customer->canReceiveNotifications()
            && !($ticket->created_by && $this->isEmailEnabled($mailbox, 'ticket_created_by_agent_email_to_customer'))
            && $this->shouldSendEmail($emailSettings, 'ticket_created_email_to_customer', $ticket, $customer)) {
            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'business' => $mailbox,
                'ticket'   => $ticket
            ]);

            if ($ticket->created_by) {
                $subject = $this->withoutReplyPrefix($subject);
            }

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_created_email_to_customer');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'ticket'     => $ticket,
                'email_type' => 'ticket_created_email_to_customer'
            ]);

            $headers = self::automatedHeaders($mailbox->getMailerHeader());

            $headers = apply_filters('fluent_support/mail_to_customer_header', $headers, [
                'cc_email'  => $ticket->getSettingsValue('cc_email', []),
                'hook_type' => 'ticket_created_email_to_customer',
                'ticket'    => $ticket
            ]);

            $files = $emailSettings['send_attachments'] == 'yes' ? $ticket->attachments : null;

            Mailer::sendWithFiles($customer->email, $subject, $emailBody, $headers, $files);
        }

        // let's send email to admin if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_created_email_to_admin');
        if ($this->shouldSendEmail($emailSettings, 'ticket_created_email_to_admin', $ticket, $customer)
            && is_email($mailbox->settings['admin_email_address'])
        ) {

            $mailTo = Arr::get($mailbox->settings, 'admin_email_address');

            if (!$mailTo || !is_email($mailTo)) {
                return;
            }

            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'business' => $mailbox,
                'ticket'   => $ticket
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_created_email_to_admin');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'ticket'     => $ticket,
                'email_type' => 'ticket_created_email_to_admin'
            ]);

            $headers = self::automatedHeaders($mailbox->getMailerHeader());

            $files = $emailSettings['send_attachments'] == 'yes' ? $ticket->attachments : null;

            Mailer::sendWithFiles($mailTo, $subject, $emailBody, $headers, $files, false);
        }
    }

    public function agentReplied($response, $ticket, $agent)
    {
        $mailbox = $ticket->mailbox;


        if (!$mailbox) {
            return false;
        }

        // Let's send welcome email to customer if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_replied_by_agent_email_to_customer');


        if ($emailSettings && $emailSettings['status'] == 'yes') {

            $ticket->load('customer');
            $customer = $ticket->customer;

            if (!$customer->canReceiveNotifications()) {
                return false;
            }

            if (!$this->shouldSendEmail($emailSettings, 'ticket_replied_by_agent_email_to_customer', $ticket, $customer)) {
                return false;
            }

            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'business' => $mailbox,
                'ticket'   => $ticket,
                'response' => $response,
                'agent'    => $agent
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_replied_by_agent_email_to_customer');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'ticket'     => $ticket,
                'response'   => $response,
                'agent'      => $agent,
                'email_type' => 'ticket_replied_by_agent_email_to_customer'
            ]);


            // Written by the agent, so the From name follows the inbox's From name setting.
            $headers = $mailbox->getMailerHeader($agent);
            $headers = apply_filters('fluent_support/mail_to_customer_header', $headers, [
                'cc_email'  => $response->getSettingsValue('cc_email', []),
                'hook_type' => 'ticket_replied_by_agent_email_to_customer',
                'ticket'    => $ticket
            ]);
            $files = $emailSettings['send_attachments'] == 'yes' ? $response->attachments : null;

            Mailer::sendWithFiles($customer->email, $subject, $emailBody, $headers, $files);
        }
    }

    public function closedByAgent($ticket, $agent)
    {
        $mailbox = $ticket->mailbox;
        if (!$mailbox) {
            return;
        }

        // Let's send welcome email to customer if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_closed_by_agent_email_to_customer');
        if ($emailSettings && $emailSettings['status'] == 'yes') {

            $ticket->load('customer');
            $customer = $ticket->customer;

            if (!$customer->canReceiveNotifications()) {
                return false;
            }

            if (!$this->shouldSendEmail($emailSettings, 'ticket_closed_by_agent_email_to_customer', $ticket, $customer)) {
                return false;
            }

            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'business' => $mailbox,
                'ticket'   => $ticket,
                'agent'    => $agent,
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_closed_by_agent_email_to_customer');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'ticket'     => $ticket,
                'agent'      => $agent,
                'email_type' => 'ticket_closed_by_agent_email_to_customer'
            ]);

            $headers = self::automatedHeaders($mailbox->getMailerHeader());
            $headers = apply_filters('fluent_support/mail_to_customer_header', $headers, [
                'cc_email'  => $ticket->getSettingsValue('cc_email', []),
                'hook_type' => 'ticket_closed_by_agent_email_to_customer',
                'ticket'    => $ticket
            ]);
            Mailer::send($customer->email, $subject, $emailBody, $headers);
        }
    }

    public function customerReplied($response, $ticket, $customer)
    {
        $mailbox = $ticket->mailbox;
        if (!$mailbox) {
            return;
        }

        // Let's send welcome email to customer if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_replied_by_customer_email_to_admin');
        if ($this->shouldSendEmail($emailSettings, 'ticket_replied_by_customer_email_to_admin', $ticket, $customer)) {

            $ticket->load('agent');
            $agent = $ticket->agent;

            $emailTo = $mailbox->settings['admin_email_address'];
            if ($agent) {
                $emailTo = $agent->email;
            }

            if (!$emailTo || !is_email($emailTo)) {
                return;
            }

            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'business' => $mailbox,
                'ticket'   => $ticket,
                'response' => $response,
                'agent'    => $agent
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_replied_by_customer_email_to_admin');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'ticket'     => $ticket,
                'response'   => $response,
                'agent'      => $agent,
                'email_type' => 'ticket_replied_by_customer_email_to_admin'
            ]);

            $headers = self::automatedHeaders($mailbox->getMailerHeader());
            $files = $emailSettings['send_attachments'] == 'yes' ? $response->attachments : null;

            Mailer::sendWithFiles($emailTo, $subject, $emailBody, $headers, $files, false);
        }
    }

    public function onAgentAssign(Agent $agent, Ticket $ticket, ?Agent $assigner = null)
    {
        if ($agent->status !== 'active' || (int) $ticket->agent_id !== (int) $agent->id) {
            return;
        }

        if (!(new AgentTicketAccess())->canAccess($agent, $ticket)) {
            return;
        }

        $currentUser = Helper::getAgentByUserId();

        if ($currentUser && $currentUser->user_id == $agent->user_id) {
            return;
        }

        $mailbox = $ticket->mailbox;

        if (!$mailbox) {
            return;
        }

        // Let's send notification email to agent if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_agent_on_change');

        if ($this->shouldSendEmail($emailSettings, 'ticket_agent_on_change', $ticket, $agent)) {

            if ($agent) {
                $emailTo = $agent->email;
            }

            if (!$emailTo || !is_email($emailTo)) {
                return;
            }

            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'business' => $mailbox,
                'ticket'   => $ticket,
                'agent'    => $agent,
                'assigner' => $assigner
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_agent_on_change');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'business'   => $mailbox,
                'ticket'     => $ticket,
                'agent'      => $agent,
                'assigner'   => $assigner,
                'email_type' => 'ticket_agent_on_change'
            ]);

            $headers = self::automatedHeaders($mailbox->getMailerHeader());

            Mailer::send($emailTo, $subject, $emailBody, $headers);
        }
    }

    public function ticketCreatedByAgent($ticket, $customer, $agent)
    {
        $mailbox = $ticket->mailbox;

        if (!$mailbox || !$customer || !$customer->canReceiveNotifications()) {
            return;
        }

        // Let's send welcome email to customer if enabled
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_created_by_agent_email_to_customer');
        if ($this->shouldSendEmail($emailSettings, 'ticket_created_by_agent_email_to_customer', $ticket, $customer)) {
            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'agent'    => $agent,
                'business' => $mailbox,
                'ticket'   => $ticket
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $this->withoutReplyPrefix($subject), $ticket, 'ticket_created_by_agent_email_to_customer');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'agent'      => $agent,
                'ticket'     => $ticket,
                'email_type' => 'ticket_created_by_agent_email_to_customer'
            ]);

            $headers = apply_filters('fluent_support/mail_to_customer_header', self::automatedHeaders($mailbox->getMailerHeader()), [
                'hook_type' => 'ticket_created_by_agent_email_to_customer',
                'cc_email'  => [],
                'ticket'    => $ticket
            ]);

            $files = $emailSettings['send_attachments'] == 'yes' ? $ticket->attachments : null;

            Mailer::sendWithFiles($customer->email, $subject, $emailBody, $headers, $files);
        }
    }

    public function ticketCreatedByAgentOnBehalf($response, $ticket, $agent)
    {
        $mailbox = $ticket->mailbox;

        if (!$mailbox) {
            return;
        }

        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, 'ticket_created_by_agent_on_behalf_email_to_customer');
        if ($this->shouldSendEmail($emailSettings, 'ticket_created_by_agent_on_behalf_email_to_customer', $ticket)) {

            $ticket->load('customer');
            $customer = $ticket->customer;

            if (!$customer->canReceiveNotifications()) {
                return false;
            }

            $subject = apply_filters('fluent_support/parse_smartcode_data', $emailSettings['email_subject'], [
                'customer' => $customer,
                'agent'    => $agent,
                'business' => $mailbox,
                'ticket'   => $ticket,
                'response' => $response
            ]);

            $subject = apply_filters('fluent_support/ticket_email_subject', $subject, $ticket, 'ticket_created_by_agent_on_behalf_email_to_customer');

            $emailBody = $this->parseEmailBody($emailSettings['email_body'], [
                'customer'   => $customer,
                'business'   => $mailbox,
                'agent'      => $agent,
                'ticket'     => $ticket,
                'response'   => $response,
                'email_type' => 'ticket_created_by_agent_on_behalf_email_to_customer'
            ]);

            $headers = apply_filters('fluent_support/mail_to_customer_header', $mailbox->getMailerHeader($agent), [
                'hook_type' => 'ticket_created_by_agent_on_behalf_email_to_customer',
                'cc_email'  => [],
                'ticket'    => $ticket
            ]);

            $files = null;

            if ($emailSettings['send_attachments'] == 'yes') {
                $files = $response->attachments;

                // Agent-initiated ticket uploads are stored on the ticket, while this email
                // is rendered from the synthetic first response created during ticket creation.
                if ((!$files || $files->isEmpty()) && $ticket->attachments) {
                    $files = $ticket->attachments;
                }
            }

            Mailer::sendWithFiles($customer->email, $subject, $emailBody, $headers, $files);
        }
    }

    private function emailTemplateCss()
    {
        $app = App::getInstance();
        return $app->view->make('emails.styles');
    }

    protected function parseEmailBody($emailBody, $data)
    {
        $data['email_body'] = apply_filters('fluent_support/parse_smartcode_data', $emailBody, $data);

        $app = App::getInstance();

        if (isset($data['business'])) {
            $businessName = $data['business']->name;
        } else {
            $businessName = get_bloginfo('name');
        }

        $footerCreditUrl = Helper::getUpgradeUrl('mail_footer', ['base_url' => 'https://fluentsupport.com/']);
        $footerText = apply_filters('fluent_support/email_footer_credit', 'This email is a service from ' . $businessName . '. Support Plugin is Powered by <a href="' . esc_url($footerCreditUrl) . '" style="color:#9e9e9e;" target="_new">FluentSupport</a>.');

        $data['email_footer'] = $footerText;

        $emailTypeForCustomerFooter = ['ticket_created_email_to_customer', 'ticket_replied_by_agent_email_to_customer', 'ticket_closed_by_agent_email_to_customer', 'ticket_created_by_agent_on_behalf_email_to_customer'];

        if (defined('FLUENTSUPPORTPRO') && in_array($data['email_type'], $emailTypeForCustomerFooter)
            && !is_null($data['business']->email_footer)) {
            $data['email_footer'] = apply_filters('fluent_support/parse_smartcode_data', $data['business']->email_footer, $data);
        }

        $emailBody = $app->view->make('emails.ticket_template', $data);
        $emogrifier = new Emogrifier($emailBody, $this->emailTemplateCss());
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    /**
     * An agent's ticket answers no email from the customer, so its first email is not a reply,
     * even when the mailbox template starts with "Re:" (email mailboxes lock it).
     */
    private function withoutReplyPrefix($subject)
    {
        return preg_replace('/^\s*Re:\s*/i', '', (string) $subject);
    }

    private function isEmailEnabled($mailbox, $emailType)
    {
        $emailSettings = (new Settings())->getBoxEmailSettings($mailbox, $emailType);

        return $emailSettings && Arr::get($emailSettings, 'status') === 'yes';
    }

    protected function shouldSendEmail($emailSettings, $emailType, $ticket, $person = null)
    {
        if (!$emailSettings || $emailSettings['status'] != 'yes') {
            return false;
        }

        return apply_filters('fluent_support/should_send_notification', true, 'email', $emailType, $ticket, $person);
    }

    /**
     * Mark a system-generated email (confirmations, closed notices, agent alerts) so
     * autoresponders don't answer it (RFC 3834 Auto-Submitted, and Outlook's
     * X-Auto-Response-Suppress). An answer would come back through email piping and
     * could start a mail loop. Replies an agent writes are not marked.
     *
     * @param array $headers
     * @return array
     */
    public static function automatedHeaders($headers)
    {
        if (!apply_filters('fluent_support/mark_automated_emails', true)) {
            return $headers;
        }

        $headers[] = 'Auto-Submitted: auto-generated';
        $headers[] = 'X-Auto-Response-Suppress: All';

        return $headers;
    }

    public function getMailerHeaderWithCc($headers, $info)
    {
        if (isset($info['cc_email'])) {
            $CcHeaders = !empty($info['cc_email']) && is_array($info['cc_email']) ? implode(', ', $info['cc_email']) : '';
            $headers[] = $CcHeaders ? "Cc: $CcHeaders" : '';
        }

        return $headers;
    }

}
