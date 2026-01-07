<?php

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class ShowExportHistory extends \MediaWiki\Maintenance\Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'page', 'Page to show history for (format: Namespace:Title)', false, true );
	}

	public function execute() {
		$arg = $this->getOption( 'page' );
		$title = $this->getServiceContainer()->getTitleFactory()->newFromText( $arg );

		/** @var \MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker $indexabilityChecker */
		$indexabilityChecker = $this->getServiceContainer()->getService( 'WikiRAG._IndexabilityChecker' );
		if ( !$title || !$indexabilityChecker->isIndexable( $title ) ) {
			$this->error( "Invalid or non-indexable page: '$arg'" );
			return;
		}

		/** @var \MediaWiki\Extension\WikiRAG\Scheduler $scheduler */
		$scheduler = $this->getServiceContainer()->getService( 'WikiRAG.Scheduler' );
		$history = $scheduler->getHistoryForPage( $title );

		// Output table
		$data = [
			[ 'Provider', 'Status', 'Timestamp', 'Error (if failed)' ],
		];
		foreach ( $history as $item ) {
			$dateTime = DateTime::createFromFormat( 'U', $item['timestamp'] );
			$data[] = [
				$item['pipeline'],
				$item['status'],
				$dateTime ? $dateTime->format( 'Y-m-d H:i:s' ) : '',
				$item['error_message'] ?? ''
			];
		}

		$this->printTable( $data );
	}

	/**
	 * @param array $rows
	 * @return void
	 */
	private function printTable( array $rows ) {
		if ( empty( $rows ) ) {
			$this->output( "(no scheduled pages)\n" );
			return;
		}

		// Determine max width for each column
		$widths = [];
		foreach ( $rows as $row ) {
			foreach ( $row as $i => $cell ) {
				$len = mb_strlen( (string)$cell );
				$widths[$i] = max( $widths[$i] ?? 0, $len );
			}
		}

		// Build a horizontal border
		$border = '+';
		foreach ( $widths as $w ) {
			$border .= str_repeat( '-', $w + 2 ) . '+';
		}
		$border .= "\n";

		// Print table
		$this->output( $border );
		foreach ( $rows as $rIndex => $row ) {
			$line = '|';
			foreach ( $row as $i => $cell ) {
				$line .= ' ' . str_pad( (string)$cell, $widths[$i] ) . ' |';
			}
			$this->output( $line . "\n" );

			// Draw border after header row
			if ( $rIndex === 0 ) {
				$this->output( $border );
			}
		}
		$this->output( $border );
	}

}

$maintClass = ShowExportHistory::class;
require_once RUN_MAINTENANCE_IF_MAIN;
