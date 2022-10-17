<?php

namespace FluentSupport\App\Services\Tickets\Importer;

use FluentSupport\App\Services\Tickets\Importer\BaseImporter;

class Importer extends BaseImporter
{
	/**
     * This `getStats` method will fetch available other systems data 
     */
	public function getStats()
	{
		if (defined('WPAS_VERSION')) {
			$this->stats[] = $this->mapClassWithHandler('awesome-support')->awesomeSupportStats();
		}

		if (defined('WPSC_VERSION')) {
			$this->stats[] = $this->mapClassWithHandler('support-candy')->supportCandyStats();
		}

		return $this->stats;
	}

	/**
     * This `handleImport` method will handle the ticket import
     * @param int $page // It can be page number or ticket id for inserting ticket
     * @param string $maybeDeleteTickets // Value is yes or no
     * @param string $handler
     */
	public function handleImport($page, $maybeDeleteTickets, $handler)
	{
		return $this->mapClassWithHandler($handler)->doMigration($page, $maybeDeleteTickets, $handler);
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
			'awesome-support'	=> 'AwesomeSupportTickets',
			'support-candy'		=> 'SupportCandyTickets'
		];

		$class = $namespace . $classMapper[$handler];
		
		return new $class();
	}
}