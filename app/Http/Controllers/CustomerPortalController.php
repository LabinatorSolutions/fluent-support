<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class CustomerPortalController extends Controller
{
    public function getTickets(Request $request)
    {
        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message'    => 'No Customer Found',
                'error_type' => 'no_customer'
            ]);
        }

        $statuses = Arr::get([
            'open'   => ['new', 'active', 'on-hold'],
            'all'    => [],
            'closed' => ['closed']
        ], $request->get('filter_type', ''));

        $ticketsQuery = Ticket::with([
            'customer' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }, 'agent' => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            }
        ])
            ->where('customer_id', $customer->id)
            ->orderBy('id', 'DESC');

        $ticketsQuery->where('customer_id', $customer->id);

        if ($statuses) {
            $ticketsQuery->whereIn('status', $statuses);
        }

        $tickets = $ticketsQuery->paginate();

        return [
            'tickets' => $tickets
        ];
    }

    public function createTicket(Request $request)
    {
        $data = $request->all();

        $this->validate($data, [
            'title'   => 'required',
            'content' => 'required'
        ]);

        $data['title'] = sanitize_text_field(wp_unslash($data['title']));

        $data['content'] = wp_unslash(wp_kses_post($data['content']));

        $customer = $this->resolveCustomer($request, true);

        if (!$customer) {
            return $this->sendError([
                'message'    => 'No Customer Found',
                'error_type' => 'no_customer'
            ]);
        }

        $data['customer_id'] = $customer->id;
        $data['product_source'] = 'local';
        $data['mailbox_id'] = $this->resolveMailboxId($request);

        $data = apply_filters('fluent_support/create_ticket_data', $data, $customer);
        do_action('fluent_support/before_ticket_create', $data, $customer);

        $createdTicket = Ticket::create($data);
        $ticket = Ticket::find($createdTicket->id);

        do_action('fluent_support/ticket_created', $ticket, $customer);

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $ticket
        ];
    }

    public function getTicket(Request $request, $ticketId)
    {
        $ticket = Ticket::where('id', $ticketId)
            ->with([
                'customer' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id']);
                }, 'agent' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id']);
                },
                'product'
            ])
            ->first();

        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message'    => 'Sorry, You do not have permission to this support ticket',
                'error_type' => 'no_customer'
            ]);
        }

        if ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message'    => 'You do not have permission to view this support ticket',
                'error_type' => 'permission_error'
            ]);
        }

        $responses = Conversation::where('ticket_id', $ticketId)
            ->with([
                'person' => function ($query) {
                    $query->select(['first_name', 'email', 'person_type', 'last_name', 'id']);
                },
                'attachments'
            ])
            ->filterByType('response')
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($responses as $response) {
            $response->content = make_clickable($response->content);
            if ($response->person) {
                $response->person->setHidden(['email']);
            }
        }

        $ticket->content = make_clickable($ticket->content);

        if ($ticket->customer) {
            $ticket->customer->setHidden(['email']);
        }

        if ($ticket->agent) {
            $ticket->agent->setHidden(['email']);
        }

        if ($ticket->status == 'closed') {
            $ticket->load('closed_by_person');
            if ($ticket->closed_by_person) {
                $ticket->closed_by_person->setVisible(['first_name', 'last_name', 'id', 'full_name', 'photo']);
            }
        }

        return [
            'ticket'     => $ticket,
            'responses'  => $responses,
            'sign_on_id' => $customer->id
        ];
    }

    public function createResponse(Request $request, $ticketId)
    {
        $data = $request->all();
        $this->validate($data, [
            'content' => 'required'
        ]);

        $data['content'] = wp_unslash(wp_kses_post($data['content']));

        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if ($ticket->privacy == 'private' && $customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not reply to this ticket', 'fluent-support')
            ]);
        }

        $data['conversation_type'] = 'response';
        $responseData = (new ResponseService())->createResponse($data, $customer, $ticket);

        return [
            'message'  => __('Reply has been added', 'fluent-support'),
            'response' => $responseData['response'],
            'ticket'   => $responseData['ticket']
        ];
    }

    public function closeTicket(Request $request, $ticketId)
    {
        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if ($customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not close this ticket', 'fluent-support')
            ]);
        }

        return [
            'message' => __('Ticket has been closed', 'fluent_support'),
            'ticket'  => (new TicketService())->close($ticket, $customer)
        ];
    }

    public function reOpenTicket(Request $request, $ticketId)
    {
        $customer = $this->resolveCustomer($request);

        if (!$customer) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if ($customer->id != $ticket->customer_id) {
            return $this->sendError([
                'message' => __('Sorry! You can not close this ticket', 'fluent-support')
            ]);
        }


        return [
            'message' => __('Ticket has been opened again', 'fluent_support'),
            'ticket'  => (new TicketService())->reopen($ticket, $customer)
        ];


    }

    private function resolveCustomer($request, $forceCreate = false)
    {
        $onBehalf = $request->get('on_behalf');

        if (!$onBehalf) {
            $user = get_user_by('ID', get_current_user_id());
            if (!$user) {
                return false;
            }
            $onBehalf = [
                'user_id'         => $user->id,
                'email'           => $user->user_email,
                'last_ip_address' => $request->getIp()
            ];
        }

        if ($forceCreate) {
            return Customer::maybeCreateCustomer($onBehalf);
        }

        return Customer::getCustomerFromData($onBehalf);
    }

    public function getPublicOptions()
    {
        $products = Product::select(['id', 'title'])->get();

        return [
            'support_products'           => $products,
            'customer_ticket_priorities' => Helper::customerTicketPriorities()
        ];
    }

    public function uploadTicketFiles(Request $request)
    {
        $files = $this->validate($this->request->files(), [
            'file' => 'mimetypes:' . implode(',', Helper::ticketAcceptedFileMiles())
        ], ['file.mimetypes' => 'Only image and documents are allowed']);


        $ticketId = $request->get('ticket_id');
        $ticket = Ticket::findOrFail($ticketId);

        $person = $this->resolveCustomer($request);

        if (!$person) {
            return $this->sendError([
                'message' => __('Sorry! No customer found', 'fluent-support')
            ]);
        }

        $uploadedFiles = FileSystem::setSubDir('ticket_' . $ticketId)->put($files);

        $attachments = [];
        foreach ($uploadedFiles as $file) {
            $fileData = [
                'ticket_id' => $ticketId,
                'person_id' => $person->id,
                'file_type' => $file['type'],
                'file_path' => $file['file_path'],
                'full_url'  => $file['url'],
                'title'     => $file['name'],
                'driver'    => 'local'
            ];

            $attachment = Attachment::create($fileData);
            $attachments[] = $attachment->file_hash;
        }

        return [
            'attachments' => $attachments
        ];
    }

    private function resolveMailboxId($request)
    {
        if($mailboxId = $request->get('mailbox_id')) {
            $mailbox = MailBox::find($mailboxId);
            if($mailbox) {
                return $mailbox->id;
            }
        }

        $mailbox = Helper::getDefaultMailBox();

        if($mailbox) {
            return $mailbox->id;
        }
        return null;
    }
}
