<?php

use MediaWiki\Extension\WikiRAG\Factory;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class ShowQueued extends \MediaWiki\Maintenance\Maintenance {

	public function execute() {
		/** @var \MediaWiki\Extension\WikiRAG\Scheduler $scheduler */
		$scheduler = $this->getServiceContainer()->getService( 'WikiRAG.Scheduler' );
		$queued = $scheduler->getQueued();

		// Output table
		$data = [
			[ 'Page', 'Provider', 'Scheduled at' ],
		];
		foreach ( $queued as $page ) {
			if ( $page['title'] === '__CONTEXT__' ) {
				$data[] = [ '(context provider)', implode( ', ', $page['providers'] ), $page['scheduled_at'] ];
				continue;
			}
			$title = $this->getServiceContainer()->getTitleFactory()->makeTitleSafe(
				$page['namespace'], $page['title']
			);
			if ( !$title ) {
				$this->output( "Invalid title for namespace {$page['namespace']} and title {$page['title']}\n" );
				continue;
			}
			$data[] = [
				$title->getPrefixedText(),
				implode( ', ', $page['providers'] ) ?: '(none)',
				$page['scheduled_at'],
			];
		}

		/** @var Factory $factory */
		$factory = $this->getServiceContainer()->getService( 'WikiRAG._Factory' );

		try {
			$target = $factory->getTarget();
			$this->output( "WikiRAG Target: " . get_class( $target ) . "\n" );
		} catch ( Throwable $e ) {
			$this->error( 'RAG configuration invalid!' );
		}

		$this->printTable( $data );
		if ( count( $data ) > 1 ) {
			$this->output( "\nPages scheduled: " . count( $data ) - 1 . "\n" );
		}
	}

	/**
	 * @param array $rows
	 * @return void
	 */
	private function printTable( array $rows ) {
		if ( count( $rows ) === 1 ) {
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

$maintClass = ShowQueued::class;
require_once RUN_MAINTENANCE_IF_MAIN;
