<?php

namespace MediaWiki\Extension\WikiRAG\RunJobsTriggerHandler;

use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\Status\Status;
use MWStake\MediaWiki\Component\RunJobsTrigger\IHandler;

final class ExportQueue implements IHandler {

	/**
	 * @param Scheduler $scheduler
	 */
	public function __construct(
		private readonly Scheduler $scheduler
	) {
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return 'wiki-rag-export-queue';
	}

	/**
	 * @return Status
	 */
	public function run() {
		$this->scheduler->runQueued( 200 );
		return Status::newGood();
	}

	/**
	 * @return ExportInterval
	 */
	public function getInterval() {
		return new ExportInterval();
	}
}
