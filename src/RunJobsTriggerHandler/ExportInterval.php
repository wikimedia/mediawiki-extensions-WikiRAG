<?php

namespace MediaWiki\Extension\WikiRAG\RunJobsTriggerHandler;

use MWStake\MediaWiki\Component\RunJobsTrigger\Interval;

final class ExportInterval implements Interval {

	/**
	 * @inheritDoc
	 */
	public function getNextTimestamp( $currentRunTimestamp, $options ) {
		$nextTS = clone $currentRunTimestamp;
		$nextTS->modify( '+20 minutes' );
		$nextTS->setTime( $nextTS->format( 'H' ), $nextTS->format( 'i' ), 0 );

		return $nextTS;
	}
}
