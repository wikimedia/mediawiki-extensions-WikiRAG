<?php

namespace MediaWiki\Extension\WikiRAG\Tests\DataProvider;

use MediaWiki\Content\JsonContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\WikiTextContent
 */
class WikitextContentTest extends TestCase {

	/**
	 * @param PageIdentity $page
	 * @param RevisionLookup $revisionLookup
	 * @param bool $canProvide
	 * @param string|null $text
	 * @return void
	 * @dataProvider provideData
	 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\WikiTextContent::provideForRevision
	 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\WikiTextContent::canProvideForPage
	 */
	public function testCanProvide(
		PageIdentity $page, RevisionLookup $revisionLookup, bool $canProvide, ?string $text = null
	) {
		$provider = new \MediaWiki\Extension\WikiRAG\DataProvider\WikiTextContent( $revisionLookup );
		$this->assertEquals( $canProvide, $provider->canProvideForPage( $page ) );
		if ( $canProvide ) {
			$this->assertEquals( $text, $provider->provideForRevision( $revisionLookup->getRevisionByTitle( $page ) ) );
		}
	}

	/**
	 * @return array[]
	 */
	private function provideData() {
		$exitingPage = $this->makePage( true );
		$nonExistingPage = $this->makePage( false );

		return [
			'existing-page-wikitext' => [
				'page' => $exitingPage,
				'revisionLookup' => $this->getRevisionLookupMock(
					$exitingPage, WikitextContent::class, 'MyContent'
				),
				'canProcess' => true,
				'result' => 'MyContent',
			],
			'existing-page-non-wikitext' => [
				'page' => $exitingPage,
				'revisionLookup' => $this->getRevisionLookupMock(
					$exitingPage, JsonContent::class, '{"key":"value"}'
				),
				'canProcess' => false,
				'result' => null,
			],
			'non-existing-page' => [
				'page' => $nonExistingPage,
				'revisionLookup' => $this->getRevisionLookupMock(
					$nonExistingPage, WikitextContent::class, 'MyContent'
				),
				'canProcess' => false,
				'result' => null,
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

	/**
	 * @param PageIdentity $page
	 * @param string $contentClass
	 * @param string $text
	 * @return RevisionLookup
	 */
	private function getRevisionLookupMock( PageIdentity $page, string $contentClass, string $text ) {
		$revision = $this->createMock( RevisionRecord::class );
		$content = $this->createMock( $contentClass );
		$content->method( 'getText' )->willReturn( $text );
		$revision->method( 'getContent' )->willReturn( $content );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionByTitle' )
			->with( $page )
			->willReturn( $page->exists() ? $revision : null );

		return $revisionLookup;
	}
}
