<?php
namespace FluentSupport\App\Http\Controllers;

use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Services\Tickets\TicketImportServices;
use FluentSupport\App\Services\Tickets\Importer\Importer;
use FluentSupport\App\Services\Tickets\Importer\BaseImporter;


class TicketImportController
{
	public function getStats ( Importer $importService )
	{
		return $importService->getStats();
	}

	public function importTickets ( Importer $importService, Request $request )
	{
		return $importService->handleImport( $request->getSafe('page', '', 'intval'), $request->getSafe('maybeDeleteTickets'), $request->getSafe('handler') );
	}

	public function deleteTickets (Importer $importService, Request $request)
	{
		return $importService->deleteTickets($request->getSafe('page', '', 'intval'), $request->getSafe('handler'));
	}
}