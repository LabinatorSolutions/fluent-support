<?php
/**
 * Class SampleTest
 *
 * @package Fluent_Support
 */

use FluentSupport\App\Models\Ticket;

class TicketTest extends WP_UnitTestCase {
        public function test_create_ticket() {
            $ticket = new Ticket();

            $ticketData = [
                'title' => 'Test Ticket',
                'content' => 'Test Ticket Content',
                'customer_id' => 2,
                'mailbox_id' => '',
                'client_priority' => 'normal',
                'create_wp_user'  => 'no',
                'create_customer' => 'no',
            ];

            $timeStart = microtime(true);
            $createdTicket = $ticket->createTicket($ticketData);
            $timeEnd = microtime(true);

            //dividing with 60 will give the execution time in minutes otherwise seconds
            $execution_time = ($timeEnd - $timeStart) / 60;

            echo 'Execution Time: ' . number_format($execution_time, 2) . ' minutes';


            if ( $createdTicket ) {
                $this->assertTrue(true);
            }
            else {
                $this->assertTrue(false);
            }
        }
}