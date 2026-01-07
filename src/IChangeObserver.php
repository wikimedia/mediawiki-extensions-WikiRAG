<?php

namespace MediaWiki\Extension\WikiRAG;

interface IChangeObserver {

	/**
	 * @param Scheduler $scheduler
	 * @return void
	 */
	public function setScheduler( Scheduler $scheduler ): void;

	/**
	 * @param array $pipeline
	 * @return void
	 */
	public function setPipeline( array $pipeline ): void;
}
