<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit\DataProvider;

use MediaWiki\Extension\WikiRAG\DataProvider\Deleted;
use MediaWiki\Page\PageIdentity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\Deleted
 */
class DeletedTest extends TestCase {

	/**
	 * @param PageIdentity $page
	 * @param bool $canProvide
	 * @return void
	 * @dataProvider provideData
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\Deleted::provideForRevision
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\Deleted::canProvideForPage
	 */
	public function testCanProvide( PageIdentity $page, bool $canProvide ) {
		$provider = new Deleted();
		$this->assertEquals( $canProvide, $provider->canProvideForPage( $page ) );
	}

	/**
	 * @return array[]
	 */
	private function provideData() {
		return [
			'existing' => [
				$this->makePage( true ),
				false,
			],
			'non-existing-page' => [
				$this->makePage( false ),
				true,
			],
		];
	}

	/**
	 * @param bool $exists
	 * @return PageIdentity
	 */
	private function makePage( bool $exists ): PageIdentity {
		$page = $this->createMock( PageIdentity::class );
		$page->method( 'exists' )->willReturn( $exists );
		return $page;
	}
}
