<?php
/**
 * Class SampleTest
 *
 * @package Fluent_Support
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Services\Tickets\TicketService;

class TicketTest extends WP_UnitTestCase {
        public function test_create_ticket() {
            $ticketData = [
                'title' => 'Test Ticket',
                'content' => 'Test Ticket Content',
                'customer_id' => 2,
                'mailbox_id' => '',
                'client_priority' => 'normal',
            ];

            $customer = Customer::findOrFail($ticketData['customer_id']);

            $timeStart = microtime(true);
            $createdTicket = (new TicketService())->storeTicket($ticketData, $customer);
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