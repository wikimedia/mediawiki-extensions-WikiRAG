<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Integration\DataProvider;

use MediaWiki\Extension\WikiRAG\DataProvider\HtmlContent;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionRenderer;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\HtmlContent
 * @group Database
 */
class HtmlContentTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|null $revision
	 * @param RevisionRenderer $revisionRenderer
	 * @param bool $canProvide
	 * @param string|null $text
	 * @return void
	 * @dataProvider provideData
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\HtmlContent::provideForRevision
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\HtmlContent::canProvideForPage
	 */
	public function testCanProvide(
		PageIdentity $page, ?RevisionRecord $revision,
		RevisionRenderer $revisionRenderer, bool $canProvide = false, ?string $text = null
	) {
		$provider = new HtmlContent( $revisionRenderer );
		$this->assertEquals( $canProvide, $provider->canProvideForPage( $page ) );
		if ( $canProvide ) {
			$this->assertEquals( $text, $provider->provideForRevision( $revision ) );
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
				'revision' => $this->createMock( RevisionRecord::class ),
				'revisionRenderer' => $this->makeRevisionRenderer( 'MyContent' ),
				'canProcess' => true,
				'result' => 'MyContent',
			],
			'non-existing-page' => [
				'page' => $nonExistingPage,
				'revision' => $this->createMock( RevisionRecord::class ),
				'revisionRenderer' => $this->createMock( RevisionRenderer::class ),
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
	 * @param string $text
	 * @return RevisionRenderer
	 */
	private function makeRevisionRenderer( string $text ): RevisionRenderer {
		$revisionRenderer = $this->createMock( RevisionRenderer::class );
		$renderedRevision = $this->createMock( RenderedRevision::class );
		$output = $this->createMock( ParserOutput::class );
		$output->method( 'runOutputPipeline' )->willReturn( $output );
		$output->method( 'getRawText' )->willReturn( $text );
		$renderedRevision->method( 'getRevisionParserOutput' )->willReturn( $output );
		$revisionRenderer->method( 'getRenderedRevision' )->willReturn( $renderedRevision );
		return $revisionRenderer;
	}
}
