<?php

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class ExportQueued extends \MediaWiki\Maintenance\Maintenance {

	public function execute() {
		/** @var \MediaWiki\Extension\WikiRAG\Scheduler $scheduler */
		$scheduler = $this->getServiceContainer()->getService( 'WikiRAG.Scheduler' );
		$scheduler->runQueued();
		$this->output( "WikiRAG export queued tasks executed.\n" );
	}
}

$maintClass = ExportQueued::class;
require_once RUN_MAINTENANCE_IF_MAIN;
