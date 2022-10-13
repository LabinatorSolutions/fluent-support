<?php 

namespace FluentSupport\App\Services\Tickets\Importer;

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;

class AwesomeSupportTickets extends BaseImporter
{
	public function awesomeSupportStats() : array
	{
		$ticketsCount = $this->countAwesomeSupportTickets();
        $replyCount = $this->db->table('posts')
            ->where('post_type', 'ticket_reply')
            ->count();

        return [
        	'name' => esc_html('Awesome Support'),
        	'tickets' => (int) $ticketsCount,
			'replies' => (int) $replyCount,
			'handler' => 'awesome-support'
    	];
	}
   
	/**
	 * This `doMigration` method will migrate ticket from other support system
	 * @param int $page
	 * @param string $maybeDeleteTickets 
	 * @param string $handler
	 * @return array $respone
	 */
    public function doMigration( $page, $maybeDeleteTickets, $handler )
    {
    	$this->handler = $handler;
        $allCounts = $this->countAwesomeSupportTickets();
        $totalBach = ceil($allCounts / $this->limit);

        $tickets = $this->getTickets($this->limit, $page);
        $insertIds = [];

        foreach ($tickets as $ticket) {
            $insertIds[] = $this->insertTicket($ticket);
        }

        $hasmore = $totalBach > $page ? true : false;

        $respone = [
        	'insertred' => $insertIds,
        	'completed' => (int) count($insertIds),
        	'has_more'	=> $hasmore,
        	'imported_page' => (int) $page,
        	'next_page' => (int) $page+1,
        	'total_tickets' => (int) $allCounts
        ];

        if ( !$hasmore ){
        	$respone['message'] = __( $allCounts . ' ' . 'Tickets has been imported successfully.' ,'fluent-support');
        	return $respone;
        }

        return $respone;
    }

    /**
	 * This `getTickets` method will get tickets from other support system
	 * @param int $limit
	 * @param int $page 
	 * @return object $tickets
	 */
	public function getTickets( $limit, $page )
    {
    	$args = [ 
    		'posts_per_page' => $limit,
    		'paged' => $page
    	];


        $tickets = \wpas_get_tickets('any', $args);
		
        if ($tickets) {
        	foreach ($tickets as $ticket) {
	            $replies = $this->getReplies($ticket->ID);
	            $ticketStatus = $this->getMeta('_wpas_status', $ticket->ID);
	            if ($ticketStatus == 'open' && !$replies) {
	                $ticketStatus = 'new';
	                $ticket->waiting_since = sanitize_text_field($ticket->post_date);
	            }

	            if ($replies && $ticketStatus != 'closed') {
	                $ticketStatus = 'active';
	            }

	            $ticket->ticket_status = $ticketStatus;

	            $ticket->replies = $replies;
	            $ticket->customer = $this->getPerson($ticket->post_author, 'customer');
	            if ($ticketStatus == 'closed') {
	                $ticket->resolved_at = $this->getMeta('_ticket_closed_on', $ticket->ID);
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
        $isAlreadyImported = Helper::getTicketMeta($ticket->ID, 'as_origin_id');

        if (!$isAlreadyImported){
            $ticketData = $this->buildTicketData($ticket);
            $ticketId = Ticket::insertGetId($ticketData);
            $this->handleAttachments($ticket->ID, $ticketId);

            $firsAgentResponseDate = false;
            $closingDate = false;

            $repliesCount = count($ticket->replies);

            $ticketUpdateData = [];
            $agent = false;

            foreach ($ticket->replies as $index => $reply) {
                if ($reply->post_author == $ticket->post_author) {
                    $person = $this->getPerson($reply->post_author, 'customer');
                } else {
                    $person = $this->getPerson($reply->post_author, 'agent');
                    if (!$agent) {
                        $agent = $person;
                    }
                }

                if ($person) {
                    $personType = $person->person_type;
                    if ($personType == 'agent') {
                        if (!$firsAgentResponseDate) {
                            $firsAgentResponseDate = $reply->post_date;
                        }
                        $ticketUpdateData['last_agent_response'] = $reply->post_date;
                    } else {
                        $ticketUpdateData['last_customer_response'] = $reply->post_date;
                        $ticketUpdateData['waiting_since'] = $reply->post_date;
                    }

                    if ($ticketData['status'] == 'closed' && $index == ($repliesCount - 1)) {
                        // this is the last response
                        $closingDate = $reply->post_date;
                        $ticketUpdateData['closed_by'] = $person->id;
                        $ticketUpdateData['resolved_at'] = $reply->post_date;
                    }
                }

                $replyData = [
                    'serial'            => intval($index + 1),
                    'ticket_id'         => intval($ticketId),
                    'person_id'         => ($person) ? intval($person->id) : intval($this->fallbackAgentId()),
                    'conversation_type' => sanitize_text_field('response'),
                    'content'           => sanitize_textarea_field($reply->post_content),
                    'source'            => sanitize_text_field($this->handler),
                    'created_at'        => sanitize_text_field($reply->post_date),
                    'updated_at'        => sanitize_text_field($reply->post_date)
                ];

                $conversationId = Conversation::insertGetId($replyData);

                $this->handleAttachments($reply->ID, $ticketId, $conversationId);
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

            Helper::updateTicketMeta($ticket->ID, 'as_origin_id', $ticketId);

            return $ticketId . ' - ' . $ticket->post_title . ' [' . $ticketData['status'] . '] - ' . $ticket->ID;
        }

    }

    /**
     * This `getMeta` method will get associated meta data of a ticket
     * @param string $metaKey
     * @param int $postId
     * @return array
     */ 
    public function getMeta($metaKey, $postId)
    {
        return get_post_meta($postId, $metaKey, true);
    }

    /**
     * This `getReplies` method will get associated replies with a ticket by it's id
     * @param int $ticketId
     * @return object $replies
     */
    public function getReplies($ticketId)
    {
        $replies = $this->db->table('posts')
            ->where('post_type', 'ticket_reply')
            ->where('post_parent', $ticketId)
            ->oldest('ID')
            ->get();

        return $replies;
    }

    // This method will handle attach attachments with a ticket and conversation
    private function handleAttachments($id, $createdTicketId, $conversationId = null)
    {
    	$attachments = $this->db->table('posts')
            ->where('post_type', 'attachment')
            ->where('post_parent', $id)
            ->orderBy('ID', 'DESC')
            ->get();


        if ($attachments){
        	foreach ( $attachments as $attachment ) {
	        	Attachment::create([
	        		'ticket_id' => $createdTicketId,
	        		'conversation_id' => $conversationId,
	        		'full_url' => sanitize_url($attachment->guid),
	        		'title' => $attachment->post_title,
	        		'person_id' => $attachment->post_author,
	        		'file_path' => get_attached_file($attachment->ID),
	        		'file_type' => $attachment->post_mime_type
	        	]);
        	}
        }
    }

    // This method will delete all old data from db after ticket importing
    // Param: $type
    private function deleteOldData($types)
    {
        $this->db->table('posts')
            ->select(['ID'])
            ->whereIn('post_type', $types)
            ->delete();
    }

    private function buildTicketData($ticket)
    {
    	$ticketData = [
            'status'         => sanitize_text_field($ticket->ticket_status),
            'hash'           => sanitize_text_field(md5(time() . wp_generate_uuid4())),
            'title'          => sanitize_text_field($ticket->post_title),
            'slug'           => sanitize_title($ticket->post_name),
            'source'         => sanitize_text_field($this->handler),
            'content'        => sanitize_textarea_field($ticket->post_content),
            'response_count' => intval(count($ticket->replies)),
            'mailbox_id'	 => intval($this->mailbox->id),
            'created_at'     => sanitize_text_field($ticket->post_date),
            'updated_at'     => sanitize_text_field($ticket->post_date)
        ];

        if ($ticket->customer) {
            $ticketData['customer_id'] = intval($ticket->customer->id);
        }

        if ($ticket->resolved_at) {
            $ticketData['resolved_at'] = sanitize_text_field($ticket->resolved_at);
        }

        if ($ticket->agent) {
            $ticketData['agent_id'] = intval($ticket->agent->id);
        }

        if ($ticket->waiting_since) {
        	$ticketData['waiting_since'] = sanitize_text_field($ticket->post_date);
        }

        return $ticketData;
    }

    protected function countAwesomeSupportTickets()
	{
		return count( \wpas_get_tickets( 'any' ) );
	}

    /**
     * This `deleteTickets` method will delete tickets with all available data
     * @param int $page //In some scenario it can be id of ticket
     * @return array $response
     */
    public function deleteTickets($page)
    {
        $hasmore = true;

        $args = [ 
            'posts_per_page' => $this->limit,
            'paged' => $page
        ];

        $tickets = \wpas_get_tickets('any', $args);

        if (!$tickets) {
            $hasmore = false;
        }

        if ($tickets) {
            foreach($tickets as $ticket){
                wp_delete_post($ticket->ID, true);

                $this->db->table('posts')
                    ->whereIn('post_type', ['ticket_reply', 'ticket_history'])
                    ->where('post_parent', $ticket->ID)
                    ->oldest('ID')
                    ->delete();
            }
        }

        $response = [
            'has_more' => $hasmore,
            'next_page' => (int) $page + 1
        ];

        if (!$hasmore) {
            $response['message'] = __('All tickets has been deleted successfully', 'fluent-support');
        }

        return $response;
    }
    
}


