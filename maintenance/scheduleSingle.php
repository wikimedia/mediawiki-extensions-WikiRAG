<?php

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class ScheduleSingle extends \MediaWiki\Maintenance\Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'page', 'Title of the page to schedule', false, true );
		$this->addOption( 'context-provider', 'Context provider to schedule', false, true );
		$this->addOption(
			'pipeline', 'Comma-separated list of pipelines to run, omit to run configured pipeline', false, true
		);
	}

	public function execute() {
		/** @var \MediaWiki\Extension\WikiRAG\Scheduler $scheduler */
		$scheduler = $this->getServiceContainer()->getService( 'WikiRAG.Scheduler' );

		$page = $this->getOption( 'page' );
		$contextProvider = $this->getOption( 'context-provider' );
		if ( $page && $contextProvider ) {
			$this->fatalError( "Cannot specify both --page and --context-provider options.\n" );
		}
		if ( $page ) {
			/** @var \MediaWiki\Extension\WikiRAG\Factory $factory */
			$factory = $this->getServiceContainer()->getService( 'WikiRAG._Factory' );

			$pipelineArg = $this->getOption( 'pipeline' );
			$pipeline = $factory->getPipeline();
			if ( $pipelineArg ) {
				$pipeline = array_map( 'trim', explode( ',', $pipelineArg ) );
			}
			$title = $this->getServiceContainer()->getTitleFactory()->newFromText( $page );
			if ( !$title || !$title->exists() ) {
				$this->fatalError( "The specified page '$page' does not exist.\n" );
			}
			if ( !$scheduler->schedule( $title, $pipeline ) ) {
				$this->fatalError( "Failed to schedule page '$page'.\n" );
			}
		} elseif ( $contextProvider ) {
			if ( !$scheduler->scheduleContextProvider( $contextProvider ) ) {
				$this->fatalError( "The specified context provider '$contextProvider' does not exist.\n" );
			}
		} else {
			$this->fatalError( "You must specify either --page or --context-provider option.\n" );
		}
		$this->output( "Scheduling completed.\n" );
	}
}

$maintClass = ScheduleSingle::class;
require_once RUN_MAINTENANCE_IF_MAIN;
