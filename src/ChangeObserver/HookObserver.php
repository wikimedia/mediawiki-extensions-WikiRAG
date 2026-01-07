<?php

namespace MediaWiki\Extension\WikiRAG\ChangeObserver;

use MediaWiki\Extension\WikiRAG\IChangeObserver;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\HookContainer\HookContainer;

abstract class HookObserver implements IChangeObserver {

	/**
	 * @var Scheduler|null
	 */
	protected ?Scheduler $scheduler = null;

	/**
	 * @var array|null
	 */
	protected ?array $pipeline = null;

	/**
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		protected readonly HookContainer $hookContainer
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function setScheduler( Scheduler $scheduler ): void {
		$this->scheduler = $scheduler;
	}

	/**
	 * @param array $pipeline
	 * @return void
	 */
	public function setPipeline( array $pipeline ): void {
		$this->pipeline = $pipeline;
	}
}
