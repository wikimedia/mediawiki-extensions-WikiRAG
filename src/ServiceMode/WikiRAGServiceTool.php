<?php

namespace MediaWiki\Extension\WikiRAG\ServiceMode;

use BlueSpice\DistributionConnector\ServiceMode\IServiceModeProcessTool;
use BlueSpice\DistributionConnector\ServiceMode\IServiceModeReportTool;
use BlueSpice\DistributionConnector\ServiceMode\ProcessDefinition;
use MediaWiki\Extension\WikiRAG\Factory;
use MediaWiki\Extension\WikiRAG\Process\FullReindexProcessStep;
use MediaWiki\Extension\WikiRAG\Process\ScheduleAllProcessStep;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;

class WikiRAGServiceTool implements IServiceModeReportTool, IServiceModeProcessTool {

	/**
	 * @param Scheduler $scheduler
	 * @param Factory $factory
	 */
	public function __construct(
		private readonly Scheduler $scheduler,
		private readonly Factory $factory
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'wikirag';
	}

	/**
	 * @inheritDoc
	 */
	public function getLabel(): Message {
		return new RawMessage( 'WikRAG' );
	}

	/**
	 * @inheritDoc
	 */
	public function getReportData( bool $isSuperUser = false ): array {
		return [
			'Scheduled pages' => count( $this->scheduler->getQueued() ),
			'Pipeline' => json_encode( array_keys( $this->factory->getPipeline() ) ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getProcesses( bool $isSuperUser = false ): array {
		return [
			new ProcessDefinition(
				'full-reindex',
				Message::newFromKey( 'wikirag-servicemode-full-reindex' ),
				null,
				new ManagedProcess( [
					'full-reindex' => [
						'class' => FullReindexProcessStep::class,
						'services' => [ 'WikiRAG._Factory', 'WikiRAG.Scheduler' ]
					]
				] )
			),
			new ProcessDefinition(
				'schedule-reindex',
				Message::newFromKey( 'wikirag-servicemode-schedule-reindex' ),
				null,
				new ManagedProcess( [
					'full-reindex' => [
						'class' => ScheduleAllProcessStep::class,
						'services' => [ 'WikiRAG._Factory', 'WikiRAG.Scheduler' ]
					]
				] )
			),
		];
	}
}
