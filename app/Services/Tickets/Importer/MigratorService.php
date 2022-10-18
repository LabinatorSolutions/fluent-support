<?php

namespace FluentSupport\App\Services\Tickets\Importer;

class MigratorService
{
    /**
     * This `getStats` method will fetch available other systems data
     */
    public function getStats()
    {
        $stats = [];

        if (defined('WPAS_VERSION')) {
            $stats[] = $this->mapClassWithHandler('awesome-support')->stats();
        }

        if (defined('WPSC_VERSION')) {
            $stats[] = $this->mapClassWithHandler('support-candy')->stats();
        }

        return [
            'stats' => $stats
        ];
    }

    /**
     * This `handleImport` method will handle the ticket import
     * @param int $page // It can be page number or ticket id for inserting ticket
     * @param string $handler
     */
    public function handleImport($page, $handler)
    {
        return $this->mapClassWithHandler($handler)->doMigration($page, $handler);
    }

    public function deleteTickets($page, $handler)
    {
        return $this->mapClassWithHandler($handler)->deleteTickets($page);
    }

    // This method is a helper method of `handleImport` method it's responsible for calling a class by $handler
    private function mapClassWithHandler($handler)
    {
        $namespace = "FluentSupport\App\Services\Tickets\Importer\\";

        $classMapper = [
            'awesome-support' => 'AwesomeSupportTickets',
            'support-candy'   => 'SupportCandyTickets'
        ];

        $class = $namespace . $classMapper[$handler];

        return new $class();
    }
}
