<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Extension\WikiRAG\Maintenance\ScheduleAll;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__, 2 );

		$updater->addExtensionTable(
			'wikirag_queue',
			"$dir/db/$dbType/wikirag_queue.sql"
		);
		$updater->addExtensionTable(
			'wikirag_history',
			"$dir/db/$dbType/wikirag_history.sql"
		);

		$updater->addPostDatabaseUpdateMaintenance( ScheduleAll::class );
	}
}
