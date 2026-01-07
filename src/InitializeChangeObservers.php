<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\MediaWikiServices;

class InitializeChangeObservers {

	public static function init() {
		$handler = new self(
			MediaWikiServices::getInstance()->getService( 'WikiRAG._Factory' ),
			MediaWikiServices::getInstance()->getService( 'WikiRAG.Scheduler' ),
		);
		$handler->setup();
	}

	/**
	 * @param Factory $factory
	 * @param Scheduler $scheduler
	 */
	public function __construct(
		private readonly Factory $factory,
		private readonly Scheduler $scheduler
	) {
	}

	/**
	 * @return void
	 */
	public function setup() {
		if ( !$this->factory->isConfigured() ) {
			return;
		}
		// This will initialize all observers with the scheduler and let them observe
		foreach ( $this->factory->getChangeObservers() as $observer ) {
			$observer->setScheduler( $this->scheduler );
		}
	}
}
