<?php

namespace MediaWiki\Extension\WikiRAG\Process;

use MediaWiki\Extension\WikiRAG\Factory;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;

class FullReindexProcessStep implements IProcessStep {

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
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		return $this->scheduler->fullReindex( $this->factory->getPipeline(), $this->factory->getContextProviders() );
	}
}
