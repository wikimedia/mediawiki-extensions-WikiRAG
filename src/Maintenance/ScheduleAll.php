<?php

namespace MediaWiki\Extension\WikiRAG\Maintenance;

use MediaWiki\Extension\WikiRAG\Factory;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use RuntimeException;
use Throwable;

require_once dirname( __DIR__, 4 ) . '/maintenance/Maintenance.php';

class ScheduleAll extends LoggedUpdateMaintenance {

	/**
	 * @return false|void
	 */
	protected function doDBUpdates() {
		/** @var Factory $factory */
		$factory = $this->getServiceContainer()->getService( 'WikiRAG._Factory' );

		try {
			$factory->getTarget();
			$pipeline = $factory->getPipeline();
			if ( empty( $pipeline ) ) {
				throw new RuntimeException( 'No pipeline configured' );
			}
		} catch ( Throwable $e ) {
			$this->output( "Error: {$e->getMessage()}\n" );
			return false;
		}

		/** @var Scheduler $scheduler */
		$scheduler = $this->getServiceContainer()->getService( 'WikiRAG.Scheduler' );
		$res = $scheduler->scheduleFullReindex( $pipeline, $factory->getContextProviders() );

		$this->output( "Scheduled {$res['pages']} pages\n" );
		$this->output( "Scheduled {$res['contextProviders']} context providers\n" );
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikirag-schedule-all';
	}
}

$maintClass = ScheduleAll::class;
require_once RUN_MAINTENANCE_IF_MAIN;
