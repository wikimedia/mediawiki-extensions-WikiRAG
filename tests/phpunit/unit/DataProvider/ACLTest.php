<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit\DataProvider;

use Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\WikiRAG\DataProvider\ACL;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\ACL
 */
class ACLTest extends TestCase {

	/**
	 * @param PageIdentity $page
	 * @param Config $mainConfig
	 * @param ConfigFactory $configFactory
	 * @param string $res
	 * @return void
	 * @dataProvider provideData
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\ACL::provideForRevision
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\ACL::canProvideForPage
	 */
	public function testCanProvide(
		PageIdentity $page, Config $mainConfig, ConfigFactory $configFactory, string $res
	) {
		$provider = new ACL( $mainConfig, $configFactory );
		$this->assertTrue( $provider->canProvideForPage( $page ) );
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPage' )->willReturn( $page );
		$this->assertEquals( $res, $provider->provideForRevision( $revision ) );
	}

	/**
	 * @return array[]
	 */
	private function provideData() {
		return [
			'native' => [
				$this->makePage( NS_MAIN ),
				$this->makeMainConfig(),
				$this->makeConfigFactory( allowBlueSpice: false ),
				'result' => json_encode( [ 'sysop', 'bureaucrat' ] ),
			],
			'native-another-ns' => [
				$this->makePage( NS_HELP ),
				$this->makeMainConfig(),
				$this->makeConfigFactory( allowBlueSpice: false ),
				'result' => json_encode( [ 'sysop', 'bureaucrat' ] ),
			],
			'bluespice-ns-lockdown' => [
				$this->makePage( NS_MAIN ),
				$this->makeMainConfig(),
				$this->makeConfigFactory( allowBlueSpice: true ),
				'result' => json_encode( [ 'bureaucrat' ] ),
			],
			'bluespice-no-ns-lockdown' => [
				$this->makePage( NS_HELP ),
				$this->makeMainConfig(),
				$this->makeConfigFactory( allowBlueSpice: true ),
				'result' => json_encode( [ 'sysop', 'bureaucrat' ] ),
			],
		];
	}

	/**
	 * @return Config
	 */
	private function makeMainConfig(): Config {
		$config = $this->createMock( Config::class );
		$config->method( 'get' )->willReturnCallback(
			static function ( $name ) {
				$map = [
					'GroupPermissions' => [
						'*' => [
							'read' => false,
							'edit' => false,
						],
						'user' => [
							'edit' => false,
						],
						'sysop' => [
							'read' => true,
							'edit' => true,
						],
						'bureaucrat' => [
							'read' => true,
							'edit' => true,
						],
					],
				];
				return $map[$name] ?? null;
			}
		);
		return $config;
	}

	/**
	 * @param bool $allowBlueSpice
	 * @return ConfigFactory
	 */
	private function makeConfigFactory( bool $allowBlueSpice ): ConfigFactory {
		$configFactory = $this->createMock( ConfigFactory::class );
		$configFactory->method( 'getConfigNames' )->willReturnCallback(
			static function () use ( $allowBlueSpice ) {
				$names = [ 'main' ];
				if ( $allowBlueSpice ) {
					$names[] = 'bsg';
				}
				return $names;
			}
		);
		$configFactory->method( 'makeConfig' )->willReturnCallback(
			function ( $name ) {
				$config = $this->createMock( Config::class );
				if ( $name === 'bsg' ) {
					$config->method( 'get' )->willReturnCallback(
						static function ( $name ) {
							$map = [
								'NamespaceRolesLockdown' => [
									0 => [ 'reader' => [ 'bureaucrat' ] ],
									1 => [ 'reader' => [ 'sysop' ] ],
								],
								'GroupRoles' => [
									'*' => [
										'reader' => false,
										'editor' => false,
									],
									'user' => [
										'editor' => false,
									],
									'sysop' => [
										'reader' => true,
										'editor' => true,
									],
									'bureaucrat' => [
										'reader' => true,
										'editor' => true,
									]
								],
							];
							return $map[$name] ?? null;
						}
					);
				}
				return $config;
			}
		);
		return $configFactory;
	}

	/**
	 * @param int $namespace
	 * @return PageIdentity
	 */
	private function makePage( int $namespace ) {
		$page = $this->createMock( PageIdentity::class );
		$page->method( 'getNamespace' )->willReturn( $namespace );
		return $page;
	}

}
