<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\WikiRAG\DataProvider\Deleted;
use MediaWiki\Extension\WikiRAG\DataProvider\ID;
use MediaWiki\Extension\WikiRAG\Factory;
use MediaWiki\Extension\WikiRAG\IContextProvider;
use MediaWiki\Extension\WikiRAG\ITarget;
use MediaWiki\Extension\WikiRAG\ResourceSpecifier;
use MediaWiki\Extension\WikiRAG\Runner;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;
use PHPUnit\Framework\TestCase;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 *
 * @covers \MediaWiki\Extension\WikiRAG\ResourceIdGenerator
 */
class RunnerTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\WikiRAG\Runner::runForPage
	 * @dataProvider provideData
	 * @param PageIdentity $page
	 * @param array|null $writeExpectation
	 * @param bool $shouldGetRevision
	 * @param int $success
	 * @param int $fail
	 * @param array $pipeline
	 * @return void
	 */
	public function testRunForPage(
		PageIdentity $page, ?array $writeExpectation, bool $shouldGetRevision,
		int $success = 0, int $fail = 0, array $pipeline = [ 'provider.foo', 'deleted' ]
	): void {
		$factory = $this->getFactory( $writeExpectation );
		$revision = $this->createMock( RevisionRecord::class );
		$hookContainerMock = $this->createMock( HookContainer::class );
		$hookContainerMock->expects( $shouldGetRevision ? $this->once() : $this->never() )
			->method( 'run' )
			->with(
				'WikiRAGRunForPage',
				[ $page, $revision ]
			);
		$revisionLookupMock = $this->createMock( RevisionLookup::class );
		$revisionLookupMock->expects( $shouldGetRevision ? $this->once() : $this->never() )
			->method( 'getRevisionByTitle' )
			->with( $page )->willReturn( $revision );

		$runner = new Runner( $factory, $hookContainerMock, $revisionLookupMock );
		$runStatus = $runner->runForPage( $page, $pipeline );
		$this->assertInstanceOf( MWTimestamp::class, $runStatus->getTimestamp() );
		$this->assertEquals( $page, $runStatus->getPageIdentity() );
		$this->assertCount( $success, $runStatus->getSuccess() );
		$this->assertCount( $fail, $runStatus->getFail() );
	}

	public function testRunForContextProvider() {
		$factory = $this->getFactory( null, true );

		$runner = new Runner(
			$factory,
			$this->createMock( HookContainer::class ),
			$this->createMock( RevisionLookup::class )
		);

		$runner->runForContextProviders( [ 'context.foo' ] );
	}

	/**
	 * @return array[]
	 */
	public function provideData(): array {
		return [
			'existing-page' => [
				'page' => $this->makePage( shouldExist: true ),
				'writeExpectation' => [ 'key' => 'provider.foo', 'content' => 'Foo Bar' ],
				'shouldCallHook' => true,
				'success' => 1,
			],
			'non-existing-page' => [
				'page' => $this->makePage( shouldExist: false ),
				'writeExpectation' => [ 'key' => 'deleted', 'content' => '' ],
				'shouldCallHook' => false,
				'success' => 1,
			],
			'no-providers' => [
				// Dummy provider wont provide for NS_HELP
				'page' => $this->makePage( ns: NS_HELP ),
				'writeExpectation' => null,
				'shouldCallHook' => true,
				'success' => 0,
				'fail' => 0
			],
			'provider-not-in-pipeline' => [
				'page' => $this->makePage(),
				'writeExpectation' => null,
				'shouldCallHook' => true,
				'success' => 1,
				'fail' => 0,
				'pipeline' => [ 'provider.foo', 'provider.invalid' ]
			],
		];
	}

	/**
	 * @param bool $shouldExist
	 * @param int $ns
	 * @return PageIdentity
	 */
	public function makePage( bool $shouldExist = true, int $ns = NS_MAIN ): PageIdentity {
		$mock = $this->createMock( PageIdentity::class );
		$mock->method( 'getNamespace' )->willReturn( $ns );
		$mock->method( 'getDBkey' )->willReturn( 'TestPage' );
		$mock->method( 'exists' )->willReturn( $shouldExist );
		return $mock;
	}

	/**
	 * @param array|null $writeExpectation
	 * @param bool $expectContextProvider
	 * @return Factory
	 */
	private function getFactory( ?array $writeExpectation = null, bool $expectContextProvider = false ): Factory {
		$dummyDataProvider = new DummyDataProvider();
		$targetMock = $this->createMock( ITarget::class );
		if ( $writeExpectation ) {
			$targetMock->expects( $this->once() )->method( 'write' )->with( $this->callback(
				static function ( ResourceSpecifier $result ) use ( $writeExpectation ) {
					return str_ends_with( $result->getFileName(), $writeExpectation['key'] ) &&
						$result->getContent() === $writeExpectation['content'];
				}
			) );
		}
		$contextProvider = $this->createMock( IContextProvider::class );
		if ( $expectContextProvider ) {
			$contextProvider->expects( $this->once() )->method( 'canProvide' )->willReturn( true );
			$contextProvider->expects( $this->once() )->method( 'provide' )->willReturn( 'FooContext' );
			$contextProvider->expects( $this->once() )->method( 'getExtension' )->willReturn( 'bar' );
			$targetMock->expects( $this->once() )->method( 'write' )->with( new ResourceSpecifier(
				WikiMap::getCurrentWikiId() . '.context.context.foo', 'bar', 'FooContext'
			) );
		}
		$ofMock = $this->createMock( ObjectFactory::class );
		$ofMock->method( 'createObject' )->willReturnCallback(
			static function ( array $spec ) use ( $targetMock, $dummyDataProvider, $contextProvider ) {
				if ( $spec['class'] === 'Foo' ) {
					return $targetMock;
				}
				if ( $spec['class'] === 'FooProvider' ) {
					return $dummyDataProvider;
				}
				if ( $spec['class'] === 'FooContextProvider' ) {
					return $contextProvider;
				}
				if ( $spec['class'] === Deleted::class ) {
					return new Deleted();
				}
				if ( $spec['class'] === ID::class ) {
					return new ID();
				}
				return null;
			}
		);
		return new Factory(
			new HashConfig( [
				'WikiRAGTarget' => [
					'type' => 'foo',
				],
				'WikiRAGPipeline' => [ 'provider.foo' ]
			] ),
			[
				'foo' => [
					'class' => 'Foo'
				]
			],
			[
				'provider.foo' => [
					'class' => 'FooProvider'
				]
			],
			[],
			[
				'context.foo' => [
					'class' => 'FooContextProvider'
				]
			],
			$ofMock
		);
	}
}
