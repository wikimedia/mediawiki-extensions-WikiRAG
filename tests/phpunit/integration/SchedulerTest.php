<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Integration;

use MediaWiki\Extension\WikiRAG\DataProvider\Deleted;
use MediaWiki\Extension\WikiRAG\DataProvider\ID;
use MediaWiki\Extension\WikiRAG\ResourceSpecifier;
use MediaWiki\Extension\WikiRAG\RunStatus;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\Extension\WikiRAG\Tests\Unit\DummyDataProvider;
use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * Basic full-feature test
 *
 * @covers \MediaWiki\Extension\WikiRAG\Scheduler
 *
 * @group Database
 */
class SchedulerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return void
	 */
	public function testIntegration() {
		$indexabilityChecker = $this->createMock( IndexabilityChecker::class );
		$indexabilityChecker->method( 'isIndexable' )->willReturnCallback(
			static function ( $page ) {
				// Disallow talk pages
				return !$page->isTalkPage();
			}
		);

		$scheduler = new Scheduler(
			$this->getServiceContainer()->getDBLoadBalancer(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getRunner(),
			$indexabilityChecker,
			$this->getServiceContainer()->getHookContainer(),
			new NullLogger()
		);
		$scheduler->schedule( $this->getNonexistingTestPage( 'SomePageNonExisting' )->getTitle(), [
			'id' => new ID(),
			'deleted' => new Deleted(),
		] );
		sleep( 1 );
		$scheduler->schedule( $this->getExistingTestPage( 'SomePage' )->getTitle(), [
			'id' => new ID(),
			'provider.foo' => new DummyDataProvider(),
		] );
		sleep( 1 );
		$scheduler->schedule( $this->getExistingTestPage( 'AnotherPage' )->getTitle(), [
			'id' => new ID(),
		] );
		$scheduler->schedule( $this->getExistingTestPage( 'Talk:SomePage' )->getTitle(), [
			'id' => new ID(),
			'provider.foo' => new DummyDataProvider(),
		] );

		$queued = $scheduler->getQueued();
		array_walk( $queued, static function ( &$item ) {
			$item['scheduled_at'] = null;
		} );

		$this->assertCount( 3, $queued );
		$this->assertArrayContains( [
			[
				'namespace' => '0',
				'title' => 'SomePageNonExisting',
				'providers' => [ 'deleted', 'id' ],
				'scheduled_at' => null
			],
			[
				'namespace' => '0',
				'title' => 'SomePage',
				'providers' => [ 'id', 'provider.foo' ],
				'scheduled_at' => null
			],
			[
				'namespace' => '0',
				'title' => 'AnotherPage',
				'providers' => [ 'id' ],
				'scheduled_at' => null
			]
		], $queued );

		$scheduler->runQueued();
		$historyForAPage = $scheduler->getHistoryForPage( $this->getExistingTestPage( 'SomePage' )->getTitle() );
		$expectedHistory = [
			[
				'pipeline' => 'id',
				'status' => 'success',
				'error_message' => '',
				'timestamp' => null,
			],
			[
				'pipeline' => 'provider.foo',
				'status' => 'success',
				'error_message' => '',
				'timestamp' => null,
			],
		];
		// Ignore timestamps
		foreach ( $historyForAPage as &$entry ) {
			$entry['timestamp'] = null;
		}
		$this->assertEquals( $expectedHistory, $historyForAPage );
	}

	/**
	 * @return \MediaWiki\Extension\WikiRAG\Runner
	 */
	private function getRunner() {
		$mock = $this->createMock( \MediaWiki\Extension\WikiRAG\Runner::class );
		$mock->method( 'runForPage' )->willReturnCallback(
			function ( $page, $providers ) {
				$status = new RunStatus( $page );
				foreach ( $providers as $provider ) {
					$specifier = $this->createMock( ResourceSpecifier::class );
					$status->successfullProvider( $provider, $specifier );
				}
				$status->finish();
				return $status;
			}
		);
		return $mock;
	}

}
