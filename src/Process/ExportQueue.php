<?php

namespace MediaWiki\Extension\WikiRAG\Process;

use MediaWiki\Extension\WikiRAG\Scheduler;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;

class ExportQueue implements IProcessStep {

	/**
	 * @param Scheduler $scheduler
	 */
	public function __construct(
		private readonly Scheduler $scheduler
	) {
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		return $this->scheduler->runQueued();
	}
}
