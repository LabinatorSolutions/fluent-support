<?php

namespace FluentSupport\App\Hooks\CLI;

use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\StatModule;

class FluentCli
{
    public function stats($args, $assoc_args)
    {
        $overallStats = StatModule::getOverAllStats();
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        \WP_CLI\Utils\format_items(
            $format,
            $overallStats,
            ['title', 'count']
        );
    }

    public function fix_timestamps($args, $assoc_args)
    {
        $targetTickets = Ticket::whereIn('status', ['active', 'new'])
            ->get();
        \WP_CLI::confirm(count($targetTickets) . " tickets will be affected. Continue?");

        $counter = 0;

        foreach ($targetTickets as $ticket) {
            // get last customer response
            $lastCustomerResponse = Response::where('ticket_id', $ticket->id)
                ->where('conversation_type', 'response')
                ->where('person_id', $ticket->customer_id)
                ->orderBy('id', 'DESC')
                ->first();
            $isDirty = false;
            if($lastCustomerResponse && $ticket->last_customer_response != $lastCustomerResponse->created_at) {
                $ticket->last_customer_response = $lastCustomerResponse->created_at;
                $isDirty = true;
            }

            if($ticket->status != 'new') {
                $lastAgentResponse = Response::where('ticket_id', $ticket->id)
                    ->where('conversation_type', 'response')
                    ->where('person_id', '!=', $ticket->customer_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                if($lastAgentResponse && $ticket->last_agent_response != $lastAgentResponse->created_at) {
                    $ticket->last_agent_response = $lastAgentResponse->created_at;
                    $isDirty = true;
                }
            }

            if($isDirty) {
                $ticket->save();
                \WP_CLI::line( 'Ticket Done at '. $ticket->id);
                $counter++;
            } else {
                \WP_CLI::line( 'Ticket Skipped at '. $ticket->id);
            }
        }

        \WP_CLI::line( 'All Done '. $counter);


    }

    public function fix_resolve_null()
    {
        $targetTickets = Ticket::where('resolved_at', '0000-00-00 00:00:00')
            ->where('status', 'closed')
            ->get();

        if ($targetTickets->isEmpty()) {
            \WP_CLI::error('No tickets found!', true);
        }

        \WP_CLI::confirm(count($targetTickets) . " tickets will be affected.");


        $total = count($targetTickets);
        $progress = \WP_CLI\Utils\make_progress_bar('Replacing: ', $total , 1);

        foreach ($targetTickets as $index => $ticket) {
            $ticket->resolved_at = $ticket->updated_at;
            $ticket->save();
            $completed = $index + 1;
            $progress->tick(1, "$completed / $total : Completed - ".$completed);
        }

        $progress->finish();
        \WP_CLI::line( 'All Done! Cheers!!');
    }

    public function replace_null_column_value($args, $assoc_args)
    {
        $column = \WP_CLI\Utils\get_flag_value($assoc_args, 'column', '');
        $replaceColumn = \WP_CLI\Utils\get_flag_value($assoc_args, 'replace_column', '');

        if (empty($column) || empty($replaceColumn)) {
            \WP_CLI::error('--column and --replace_column parameter is required', true);
        }

        $targetTickets = Ticket::whereNull($column)->get();

        if ($targetTickets->isEmpty()) {
            \WP_CLI::error('No tickets found!', true);
        }

        \WP_CLI::confirm(count($targetTickets) . " tickets will be affected. $column value will be replaced with $replaceColumn value of each row. Please confirm", $assoc_args);


        $firstTicket = $targetTickets[0];

        $fillables = $firstTicket->getFillable();

        if (!in_array($column, $fillables) || !in_array($column, $fillables)) {
            \WP_CLI::error('Properties could not be found', true);
        }

        $total = count($targetTickets);
        $progress = \WP_CLI\Utils\make_progress_bar('Replacing: ', $total , 1);

        foreach ($targetTickets as $index => $ticket) {
            $ticket->{$column} = $ticket->{$replaceColumn};
            $ticket->save();
            $completed = $index + 1;
            $progress->tick(1, "$completed / $total : Completed - ".$completed);
        }

        $progress->finish();
        \WP_CLI::line( 'All Done! Cheers!!');
    }

    public function match_old()
    {
        $hasMore = true;
        $itteration = 0;

        while ($hasMore) {
            \WP_CLI::line( 'Itteration: '.$itteration);
            $oldTickets = wpFluent()->table('fs_tickets_old')
                ->select(['id', 'customer_id'])
                ->offset($itteration * 100)
                ->limit(100)
                ->get();

            $itteration++;

            if(count($oldTickets) == 0) {
                $hasMore = false;
            } else {
                foreach ($oldTickets as $oldTicket) {
                    wpFluent()->table('fs_tickets')
                        ->where('id', $oldTicket->id)
                        ->update([
                            'customer_id' => $oldTicket->customer_id
                        ]);
                }
            }
        }

        \WP_CLI::line( 'All Done');
    }

    public function adre_match_smtp()
    {
        $nullTickets = Ticket::whereNull('customer_id')
            ->get();

        foreach ($nullTickets as $nullTicket)
        {
            $emailLog = wpFluent()->table('fsmpt_email_logs')
                ->where('subject', 'Request Received: '.$nullTicket->title)
                ->first();

            if($emailLog) {
                $to = maybe_unserialize($emailLog->to);
                $email = $to[0]['email'];
                $person = Person::where('email', $email)->first();
                if($person) {
                    $nullTicket->customer_id = $person->id;
                    $nullTicket->save();
                } else {
                    \WP_CLI::error('Could not be found '.$nullTicket->id, true);
                }
            } else {
                \WP_CLI::line( $nullTicket->title);
                \WP_CLI::line('Could not be found', false);
            }
        }
    }

    public function fix_waiting_since()
    {
        $tickets = Ticket::where('status', '!=', 'closed')
                        ->whereNull('waiting_since')
                        ->get();
        foreach ($tickets as $ticket) {
            $responses = Response::where('ticket_id', $ticket->id)
                            ->orderBy('id', 'ASC')
                            ->get();
            $lastResponseFrom = 'customer';
            $waitingSince = $ticket->created_at;

            foreach ($responses as $response) {
                if($response->person_id == $ticket->customer_id && $lastResponseFrom == 'customer') {
                    // this is customer response and repeat response
                } else {
                    // this is agent response
                    $waitingSince = $response->created_at;
                    $lastResponseFrom = ($response->person_id == $ticket->customer_id) ? 'customer' : 'agent';
                }
            }

            $ticket->waiting_since = $waitingSince;
            $ticket->save();
            \WP_CLI::line($ticket->waiting_since .' : '. $ticket->id.' : '. $ticket->title);
        }
    }
}
