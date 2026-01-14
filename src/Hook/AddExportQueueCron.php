<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Extension\WikiRAG\Process\ExportQueue;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\WikiCron\WikiCronManager;

class AddExportQueueCron implements MediaWikiServicesHook {

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public function onMediaWikiServices( $services ) {
		if ( defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}
		/** @var WikiCronManager $cronManager */
		$cronManager = $services->getService( 'MWStake.WikiCronManager' );
		$cronManager->registerCron( 'wiki-rag-export', '0 1 * * *', new ManagedProcess( [
			'export' => [
				'class' => ExportQueue::class,
				'services' => [ 'WikiRAG.Scheduler' ],
			]
		] ) );
	}
}
