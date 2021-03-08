<?php

namespace FluentSupport\App\Services\MailerInbox;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Support\Arr;

class ByMailHandler
{
    public static function handleEmailData($data)
    {
        $data['subject'] = self::getActualSubject(Arr::get($data, 'subject', 'Subject not defined'));

        $onBehalf = Arr::get($data, 'sender');
        $fullName = $onBehalf['name'];
        unset($onBehalf['name']);

        $nameArray = explode(' ', $fullName);

        if (count($nameArray) >= 2) {
            $onBehalf['last_name'] = array_pop($nameArray);
            $onBehalf['first_name'] = implode(' ', $nameArray);
        } else if ($fullName) {
            $onBehalf['first_name'] = $fullName;
        }

        $customer = Customer::maybeCreateCustomer($onBehalf);

        $existingTicket = false;

        if (!$customer->newly_created) {
            $existingTicket = self::guessTicket($customer, $data);
        }

        $responseOrTicketData = [
            'title'      => $data['subject'],
            'message_id' => $data['message_id'],
            'content'    => wp_unslash(wp_kses_post($data['content']))
        ];

        if (!$existingTicket) {
            $responseOrTicketData['customer_id'] = $customer->id;
            $responseOrTicketData['source'] = 'email';

            // find mailbox
            $mailBox = MailBox::where('mapped_email', $data['mailbox_email'])->first();
            if($mailBox) {
                $mailBox = MailBox::where('is_default', 'yes')->orderBy('id', 'ASC')->first();
            }

            if($mailBox) {
                $responseOrTicketData['mailbox_id'] = $mailBox->id;
            }

            $ticketData = apply_filters('fluent_support/create_ticket_data', $responseOrTicketData, $customer);
            do_action('fluent_support/before_ticket_create', $responseOrTicketData, $customer);

            $createdTicket = Ticket::create($ticketData);

            $createdTicket->load('customer');

            do_action('fluent_support/ticket_created', $createdTicket, $customer);

            return [
                'type'      => 'new_ticket',
                'ticket_id' => $createdTicket->id,
                'ticket'    => $createdTicket
            ];
        }

        if ($existingTicket->extra_content) {
            $responseOrTicketData['content'] = $existingTicket->extra_content . $responseOrTicketData['content'];
        }

        // we have to create a response
        unset($responseOrTicketData['title']);
        $responseOrTicketData['person_id'] = $customer->id;
        $responseOrTicketData['ticket_id'] = $existingTicket->id;
        $responseOrTicketData['conversation_type'] = 'response';
        $responseOrTicketData['source'] = 'email';

        $createdResponse = Response::create($responseOrTicketData);
        if ($existingTicket->status != 'active') {
            $existingTicket->status = 'active';
            $existingTicket->last_customer_response = current_time('mysql');
            $existingTicket->response_count += 1;

            if (!empty($data['message_id'])) {
                $existingTicket->message_id = $data['message_id'];
            }

            $existingTicket->save();
        }

        do_action('fluent_support/response_added_by_customer', $createdResponse, $existingTicket, $customer);

        return [
            'type'        => 'new_response',
            'ticket_id'   => $existingTicket->id,
            'response_id' => $createdResponse->id,
            'response'    => $createdResponse,
            'customer'    => $customer
        ];
    }

    private static function getActualSubject($string)
    {
        $prefix = 'Re: ';
        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($string));
        }

        $prefix = 'RE: ';
        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($string));
        }

        $prefix = 'Fwd: ';
        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($string));
        }

        $prefix = 'Request Received: ';
        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($string));
        }

        return $string;
    }


    protected static function guessTicket($customer, $data)
    {
        $subject = $data['subject'];

        preg_match_all('/#([0-9]*)/', $subject, $matches);

        $ticketId = false;
        if (count($matches[1])) {
            $ticketId = array_pop($matches[1]);
        }

        if ($ticketId) {
            $existingTicket = Ticket::where('customer_id', $customer->id)
                ->where('id', $ticketId)
                ->first();

            if ($existingTicket) {
                return $existingTicket;
            }

            $subject = str_replace('#' . $ticketId, '', $subject);
        }

        $subject = trim($subject);

        $existingTicket = Ticket::where('customer_id', $customer->id)
            ->where('title', 'like', '%%' . $subject . '%%')
            ->orderBy('ID', 'DESC')
            ->first();

        if (!$existingTicket) {
            // check if user has any active ticket
            $existingTicket = Ticket::where('customer_id', $customer->id)
                ->where('status', '!=', 'closed')
                ->orderBy('ID', 'DESC')
                ->first();

            if ($existingTicket) {
                $existingTicket->extra_content = '<h4>---- Email Subject: ' . $data['subject'] . ' -----</h4>';
            }
        }

        return $existingTicket;
    }

}
