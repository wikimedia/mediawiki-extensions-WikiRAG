<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Extension\WikiRAG\RunJobsTriggerHandler\ExportQueue;

class AddExportQueueTask {

	/**
	 * @param array &$handlers
	 * @return void
	 */
	public function onMWStakeRunJobsTriggerRegisterHandlers( array &$handlers ) {
		$handlers['wiki-rag-export-queue'] = [
			'class' => ExportQueue::class,
			'services' => [ 'WikiRAG.Scheduler' ]
		];
	}
}
