<?php

namespace FluentSupport\App\Services\Tickets\Importer;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;

class SupportCandyTickets extends BaseImporter
{
	protected $fromId = 0;

	/**
	 * This `supportCandyStats`  method will fetch all available stats of Support Candy.
	 */

	public function supportCandyStats()
	{
		$ticketsCount = $this->countSupportCandyTickets();
        $replyCount = $this->db->table('psmsc_threads')
            ->where('type', 'reply')
            ->count();

        return [
        	'name' => esc_html('Support Candy'),
        	'tickets' => (int) $ticketsCount,
			'replies' => (int) $replyCount,
			'handler' => 'support-candy'
    	];
	}

	/**
	 * This `doMigration` method will migrate ticket from other support system
	 * @param int $page
	 * @param string $maybeDeleteTickets 
	 * @param string $handler
	 * @return array $respone
	 */
	public function doMigration($page, $maybeDeleteTickets, $handler)
	{
		$this->handler = $handler;
		$hasmore = true;
		$allCounts = $this->countSupportCandyTickets();
        $totalBach = ceil($allCounts / $this->limit);

        $tickets = $this->getTickets($this->limit, $page);
        
        $insertIds = [];

        if($tickets) {
        	foreach ($tickets as $ticket) {
	            $insertIds[] = $this->insertTicket($ticket);
	        }
        }

        $checkMore = $this->getTickets(1, end($insertIds));

        if(empty($checkMore)){
        	$hasmore = false;
        }

        $response = [
        	'insertred' => $insertIds,
        	'completed' => (int) count($insertIds),
        	'has_more'	=> $hasmore,
        	'imported_page' => (int) $page,
        	'next_page' => (int) end($insertIds),
        	'total_tickets' => (int) $allCounts
        ];

        if (!$hasmore){
        	$response['message'] = __( $allCounts . ' ' . 'Tickets has been imported successfully.' ,'fluent-support');
        	return $response;
        }

        return $response;
	}

	// This `getTickets` will fetch tickets by limit and id
	private function getTickets($limit, $fromId)
	{
		$tickets = $this->db->table('psmsc_tickets')
						->where('psmsc_tickets.id', '>', $fromId)
						->oldest('psmsc_tickets.id')
						->limit($limit)
						->join('psmsc_threads', 'psmsc_threads.ticket', '=', 'psmsc_tickets.id')
						->where('psmsc_threads.type', '=', 'report')
						->get();

		if ($tickets) {
			foreach ($tickets as $ticket) {
	            $replies = $this->getReplies($ticket->id);
	            $ticket_status = $this->db->table('psmsc_statuses')->where('id', $ticket->status)->select(['name'])->first();
	            $ticketStatus = 'new';
	            
	            if ($ticket_status->name == 'Open' && !$replies) {
	                $ticket->waiting_since = sanitize_text_field($ticket->last_reply_on);
	            }

	            if ($replies && $ticket_status->name != 'Closed') {
	                $ticketStatus = 'active';
	            }

	            $ticket->ticket_status = $ticketStatus;
	            $ticket->replies = $replies;

	            $ticket->customer = $this->getPerson($this->getTicketCustomer($ticket->customer), 'customer');

	            if ($ticket->assigned_agent){
	            	$ticket->agent = $this->getPerson($this->getTicketAgent($ticket->assigned_agent), 'agent');
	            }

	            if ($ticket_status->name == 'Closed') {
	            	$ticketStatus = 'closed';
	                $ticket->resolved_at = $ticket->date_closed;
	            } else {
	                $ticket->resolved_at = false;
	            }

        	}
        	return $tickets;
		}
	}

	/**
     * This `insertTicket` method will insert ticket with associated replies and attachments
     * @param object $ticket
     * return void
     */ 
    public function insertTicket($ticket)
    {
    	$isAlreadyImported = Helper::getTicketMeta($ticket->id, 'as_sc_origin_id');

    	if (!$isAlreadyImported){
    		$ticketData = $this->buildTicketData($ticket);
	        
	        $ticketId = Ticket::insertGetId($ticketData);

	        if ( $ticket->attachments ) {
	        	$this->handleAttachments($ticket->id, $ticketId, $ticket->customer->id);
	        }

	        $firsAgentResponseDate = false;
	        $closingDate = false;

	        $repliesCount = count($ticket->replies);

	        $ticketUpdateData = [];
	        $agent = false;

	        foreach ($ticket->replies as $index => $reply) {
	            if ($reply->customer == $ticket->customer->id) {
	                $person = $this->getPerson($this->getTicketCustomer($reply->customer), 'customer');
	            } else {
	                $person = $this->getPerson($this->getTicketAgent($reply->customer), 'agent');
	                if (!$agent) {
	                    $agent = $person;
	                }
	            }

	            if ($person) {
	                $personType = $person->person_type;
	                if ($personType == 'agent') {
	                    if (!$firsAgentResponseDate) {
	                        $firsAgentResponseDate = $reply->date_created;
	                    }
	                    $ticketUpdateData['last_agent_response'] = $reply->date_created;
	                } else {
	                    $ticketUpdateData['last_customer_response'] = $reply->date_created;
	                    $ticketUpdateData['waiting_since'] = $reply->date_created;
	                }

	                if ($ticketData['status'] == 'closed' && $index == ($repliesCount - 1)) {
	                    // this is the last response
	                    $closingDate = $reply->date_created;
	                    $ticketUpdateData['closed_by'] = $person->id;
	                    $ticketUpdateData['resolved_at'] = $reply->date_created;
	                }
	            }

	            $replyData = [
	                'serial'            => intval($index + 1),
	                'ticket_id'         => intval($ticketId),
	                'person_id'         => ($person) ? intval($person->id) : intval($this->fallbackAgentId()),
	                'conversation_type' => sanitize_text_field('response'),
	                'content'           => sanitize_textarea_field($reply->body),
	                'source'            => sanitize_text_field($this->handler),
	                'created_at'        => sanitize_text_field($reply->date_created),
	                'updated_at'        => sanitize_text_field($reply->date_updated)
	            ];

	            $conversationId = Conversation::insertGetId($replyData);

	            if ($reply->attachments) {
	            	$this->handleAttachments($reply->id, $ticketId, $person->id, $conversationId);
	            }
	        }

	        if ($firsAgentResponseDate) {
	            $ticketUpdateData['first_response_time'] = sanitize_text_field( strtotime($firsAgentResponseDate) - strtotime($ticketData['created_at']) );
	        }

	        if ($closingDate) {
	            $ticketUpdateData['total_close_time'] = sanitize_text_field( strtotime($closingDate) - strtotime($ticketData['created_at']) );
	        }

	        if ($agent) {
	            $ticketUpdateData['agent_id'] = intval($agent->id);
	        }

	        $ticketUpdateData = array_filter($ticketUpdateData);

	        if ($ticketUpdateData) {
	            Ticket::where('id', $ticketId)
	                ->update($ticketUpdateData);
	        }

	        Helper::updateTicketMeta($ticket->id, 'as_sc_origin_id', $ticketId);
	        return $ticketId . ' - ' . $ticket->subject . ' [' . $ticketData['status'] . '] - ' . $ticket->id;
    	}
    }

    private function buildTicketData($ticket)
    {
    	$ticketData = [
            'status'         => sanitize_text_field($ticket->ticket_status),
            'hash'           => sanitize_text_field(md5(time() . wp_generate_uuid4())),
            'title'          => sanitize_text_field($ticket->subject),
            'slug'           => sanitize_title($ticket->subject),
            'source'         => sanitize_text_field($this->handler),
            'content'        => sanitize_textarea_field($ticket->body),
            'response_count' => intval(count($ticket->replies)),
            'mailbox_id'	 => intval($this->mailbox->id),
            'created_at'     => sanitize_text_field($ticket->date_created),
            'updated_at'     => sanitize_text_field($ticket->date_updated)
        ];

        if (isset($ticket->customer)) {
            $ticketData['customer_id'] = intval($ticket->customer->id);
        }

        if (isset($ticket->resolved_at)) {
            $ticketData['resolved_at'] = sanitize_text_field($ticket->resolved_at);
        }

        if (isset($ticket->agent)) {
            $ticketData['agent_id'] = intval($ticket->agent->id);
        }

        if (!isset($ticket->waiting_since)) {
        	$ticketData['waiting_since'] = sanitize_text_field($ticket->date_created);
        }

        return $ticketData;
    }

    private function handleAttachments($id, $createdTicketId, $person, $conversationId = null)
    {
    	$attachments = $this->db->table('psmsc_attachments')
            ->where('source_id', $id)
            ->latest('id')
            ->get();


        if ($attachments){
        	foreach ( $attachments as $attachment ) {
        		$uploadDir = wp_get_upload_dir();
        		$url = explode('uploads/', $attachment->file_path);
        		$fullUrl = $uploadDir['baseurl'] . '/' . $url[1];

	        	Attachment::create([
	        		'ticket_id' => $createdTicketId,
	        		'conversation_id' => $conversationId,
	        		'full_url' => $fullUrl,
	        		'title' => $attachment->name,
	        		'person_id' => intval($person),
	        		'file_path' => $attachment->file_path,
	        	]);
        	}
        }
    }


	protected function getReplies($ticketId)
	{
		return $this->db->table('psmsc_threads')
						->where('type', 'reply')
						->where('ticket', $ticketId)
						->oldest('id')
						->get();
	}

	private function countSupportCandyTickets()
	{
		return $this->db->table('psmsc_tickets')->count();
	}

	private function getTicketCustomer($customerId)
	{
		$customer = $this->db->table('psmsc_customers')
					->where('id', $customerId)
					->select(['user'])
					->first();
					
		return (int) $customer->user;
	}

	private function getTicketAgent($agentId)
	{
		$agent = $this->db->table('psmsc_agents')
					->where('id', $agentId)
					->select(['user'])
					->first();

		if (!$agent) {
			return $this->fallbackAgentId();
		}

		return (int) $agent->user;
	}

	public function deleteTickets($page)
	{
		$hasmore = true;

		$ticketsQuery = $this->db->table('psmsc_tickets')
			->limit($this->limit)
			->select(['id']);

		$tickets = $ticketsQuery->get();

		if (!$tickets){
			$hasmore = false;
		}

		if ($tickets){
			foreach($tickets as $ticket){
				$ticketsQuery->where('id', $ticket->id)->delete();
				$this->db->table('psmsc_threads')->where('ticket', $ticket->id)->delete();
			}
		}

		$response = [
			'has_more' => $hasmore,
			'next_page' => (int) 0
		];

		if (!$hasmore){
			$data['has_more'] = false;
			$data['message'] = __('All tickets has been deleted successfully. Please delete other ticket & reply realated data manually', 'fluent-support');
			return $response;
		}

		return $response;
	}
}