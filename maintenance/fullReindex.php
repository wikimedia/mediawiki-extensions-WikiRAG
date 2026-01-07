<?php

use MediaWiki\Extension\WikiRAG\Factory;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class FullReindex extends \MediaWiki\Maintenance\Maintenance {

	public function execute() {
		/** @var Factory $factory */
		$factory = $this->getServiceContainer()->getService( 'WikiRAG._Factory' );

		/** @var \MediaWiki\Extension\WikiRAG\Scheduler $scheduler */
		$scheduler = $this->getServiceContainer()->getService( 'WikiRAG.Scheduler' );
		$this->output( "Starting full reindex...\n" );
		$res = $scheduler->fullReindex( $factory->getPipeline(), $factory->getContextProviders() );
		$count = count( $res );
		$this->output( "Done: {$count} page(s) re-indexed\n" );
	}
}

$maintClass = FullReindex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
