<?php
namespace FluentSupport\App\Http\Controllers;

use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Services\Tickets\TicketImportServices;

class TicketImportController
{
	public function getStats ( TicketImportServices $importService )
	{
		return $importService->getStats();
	}

	public function importTickets ( TicketImportServices $importService, Request $request )
	{
		return $importService->doMigration( $request->getSafe('page', '', 'intval'), $request->getSafe('maybeDeleteTickets') );
	}
}