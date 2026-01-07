<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\WikiRAG\DataProvider\Deleted;
use MediaWiki\Extension\WikiRAG\DataProvider\ID;
use MediaWiki\Extension\WikiRAG\IChangeObserver;
use MediaWiki\Extension\WikiRAG\IContextProvider;
use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Extension\WikiRAG\ITarget;
use PHPUnit\Framework\TestCase;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * Minimal test to verify general functionality
 *
 * @covers \MediaWiki\Extension\WikiRAG\Factory
 */
class FactoryTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\WikiRAG\Factory::getTarget
	 * @covers \MediaWiki\Extension\WikiRAG\Factory::getPipeline
	 * @covers \MediaWiki\Extension\WikiRAG\Factory::getChangeObservers
	 * @covers \MediaWiki\Extension\WikiRAG\Factory::getContextProviders
	 * @return void
	 */
	public function testGetPipeline() {
		$targetConfigData = [
			'path' => 'dummy/path',
			'format' => 'json',
		];
		$targetConfig = new HashConfig( $targetConfigData );

		$targetMock = $this->createMock( ITarget::class );
		$targetMock->expects( $this->once() )->method( 'setConfig' )
			->with( $targetConfig );

		$dp1Mock = $this->createMock( IPageDataProvider::class );
		$dp2Mock = $this->createMock( IPageDataProvider::class );
		$idMock = $this->createMock( ID::class );
		$deletedMock = $this->createMock( Deleted::class );

		$changeObserverMock = $this->createMock( IChangeObserver::class );
		$contextProviderMock = $this->createMock( IContextProvider::class );

		$objectFactoryMock = $this->createMock( ObjectFactory::class );
		$objectFactoryMock->method( 'createObject' )->willReturnCallback(
			static function ( array $spec ) use (
				$targetMock, $dp1Mock, $dp2Mock, $idMock, $deletedMock, $changeObserverMock, $contextProviderMock
			) {
				if ( $spec['class'] === 'Foo' ) {
					return $targetMock;
				} elseif ( $spec['class'] === 'FooProvider' ) {
					return $dp1Mock;
				} elseif ( $spec['class'] === 'BarProvider' ) {
					return $dp2Mock;
				} elseif ( $spec['class'] === 'FooObserver' ) {
					return $changeObserverMock;
				} elseif ( $spec['class'] === 'FooContextProvider' ) {
					return $contextProviderMock;
				} elseif ( $spec['class'] === ID::class ) {
					return $idMock;
				} elseif ( $spec['class'] === Deleted::class ) {
					return $deletedMock;
				}
				return null;
			}
		);

		$targetRegistry = [
			'foo' => [
				'class' => 'Foo'
			]
		];
		$dataProviderRegistry = [
			'provider.foo' => [
				'class' => 'FooProvider'
			],
			'provider.bar' => [
				'class' => 'BarProvider'
			],
		];
		$changeObserverRegistry = [
			'observer.foo' => [
				'class' => 'FooObserver'
			],
		];
		$contextProviderRegistry = [
			'context.foo' => [
				'class' => 'FooContextProvider'
			],
		];

		$factory = new \MediaWiki\Extension\WikiRAG\Factory(
			new HashConfig( [
				'WikiRAGTarget' => [
					'type' => 'foo',
					'configuration' => $targetConfigData,
				],
				'WikiRAGPipeline' => [ 'provider.foo', 'provider.bar' ]
			] ),
			$targetRegistry,
			$dataProviderRegistry,
			$changeObserverRegistry,
			$contextProviderRegistry,
			$objectFactoryMock
		);

		$target = $factory->getTarget();
		$this->assertInstanceOf( ITarget::class, $target );

		$pipeline = $factory->getPipeline();
		$this->assertCount( 3, $pipeline );
		foreach ( $pipeline as $provider ) {
			$this->assertInstanceOf( IPageDataProvider::class, $provider );
		}

		$this->assertEquals( [
			'provider.foo' => $dp1Mock, 'provider.bar' => $dp2Mock, 'id' => $idMock
		], $pipeline );
		$this->assertSame( $dp1Mock, $factory->getDataProvider( 'provider.foo' ) );
		$this->assertSame( $dp2Mock, $factory->getDataProvider( 'provider.bar' ) );

		$this->assertSame( [ $changeObserverMock ], $factory->getChangeObservers() );

		$this->assertSame( [ 'context.foo' => $contextProviderMock ], $factory->getContextProviders() );
	}
}
