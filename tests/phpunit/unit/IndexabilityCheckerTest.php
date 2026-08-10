<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit;

use File;
use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\NamespaceInfo;
use PHPUnit\Framework\TestCase;
use RepoGroup;

/** *
 * @covers \MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker
 */
class IndexabilityCheckerTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker::isIndexable
	 * @dataProvider provideData
	 * @param PageIdentity $page
	 * @param bool $expected
	 * @return void
	 */
	public function testCanIndex( PageIdentity $page, bool $expected ) {
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->expects( $this->once() )
			->method( 'run' )
			->willReturnCallback(
				static function ( string $hook, array $args ) {
					if ( $hook === 'WikiRAGCanBeIndexed' ) {
						// Simulate a hook that disallows indexing for NS_USER
						if ( $args[0]->getNamespace() === NS_USER ) {
							$args[1] = true;
						}
					}
					return true;
				}
			);

		$checker = new IndexabilityChecker(
			$this->getRepoGroup(),
			$this->getNamespaceInfo(),
			$hookContainer
		);

		$this->assertEquals( $expected, $checker->isIndexable( $page ) );
	}

	/**
	 * @return array[]
	 */
	protected function provideData() {
		return [
			'main namespace' => [
				$this->getPage( 'SomeArticle', NS_MAIN ),
				true
			],
			'project namespace' => [
				$this->getPage( 'SomeProject', NS_PROJECT ),
				true
			],
			'help namespace' => [
				$this->getPage( 'SomeHelp', NS_HELP ),
				false
			],
			'file namespace, office file exists' => [
				$this->getPage( 'ExistsOffice', NS_FILE ),
				true
			],
			'file namespace, text file exists' => [
				$this->getPage( 'ExistsText', NS_FILE ),
				true
			],
			'file namespace, image file exists' => [
				$this->getPage( 'ExistsImage', NS_FILE ),
				false
			],
			'file namespace, file does not exist' => [
				$this->getPage( 'NotExists', NS_FILE ),
				false
			],
			'user namespace, allowed by hook' => [
				$this->getPage( 'SomeUser', NS_USER ),
				true
			],
		];
	}

	/**
	 * @param string $title
	 * @param int $ns
	 * @return PageIdentity
	 */
	private function getPage( string $title, int $ns ) {
		$page = $this->createMock( PageIdentity::class );
		$page->method( 'getDBkey' )->willReturn( $title );
		$page->method( 'getNamespace' )->willReturn( $ns );
		$page->method( 'exists' )->willReturn( true );
		return $page;
	}

	/**
	 * @return NamespaceInfo
	 */
	private function getNamespaceInfo() {
		$mock = $this->createMock( NamespaceInfo::class );
		$mock->method( 'isContent' )->willReturnCallback(
			static fn ( int $ns ) => in_array( $ns, [ NS_MAIN, NS_PROJECT ] )
		);
		$mock->method( 'isSubject' )->willReturnCallback(
			static fn ( int $ns ) => $ns % 2 === 0
		);
		return $mock;
	}

	/**
	 * @param string $mediaType
	 * @param bool $exists
	 * @return File
	 */
	public function getFile( string $mediaType, bool $exists ) {
		$file = $this->createMock( File::class );
		$file->method( 'exists' )->willReturn( $exists );
		$file->method( 'getMediaType' )->willReturn( $mediaType );
		return $file;
	}

	/**
	 * @return RepoGroup
	 */
	private function getRepoGroup() {
		$localRepo = $this->createMock( \LocalRepo::class );
		$localRepo->method( 'findFile' )->willReturnCallback(
			static function ( PageIdentity $page ) {
				if ( $page->getDBkey() === 'ExistsOffice' ) {
					return ( new IndexabilityCheckerTest() )->getFile( 'office', true );
				}
				if ( $page->getDBkey() === 'ExistsText' ) {
					return ( new IndexabilityCheckerTest() )->getFile( 'text', true );
				}
				if ( $page->getDBkey() === 'ExistsImage' ) {
					return ( new IndexabilityCheckerTest() )->getFile( 'image', true );
				}
				if ( $page->getDBkey() === 'NotExists' ) {
					return ( new IndexabilityCheckerTest() )->getFile( 'office', false );
				}
				return null;
			}
		);

		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'getLocalRepo' )->willReturn( $localRepo );
		return $repoGroup;
	}
}
