<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\User\User;
use RuntimeException;

class HtmlContent implements IPageDataProvider {

	/**
	 * @param RevisionRenderer $revisionRenderer
	 */
	public function __construct(
		private readonly RevisionRenderer $revisionRenderer
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		$output = $this->getRenderedOutput( $revision );
		return $output->getRawText() ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		return $page->exists();
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		return [ 'page-content' ];
	}

	/**
	 * @param RevisionRecord $revision
	 * @return ParserOutput
	 */
	protected function getRenderedOutput( RevisionRecord $revision ): ParserOutput {
		$rendered = $this->revisionRenderer->getRenderedRevision( $revision );
		if ( !$rendered ) {
			throw new RuntimeException(
				"RevisionRenderer could not render revision for page with ID: " . $revision->getPage()->getId()
			);
		}
		$output = $rendered->getRevisionParserOutput();
		if ( !$output ) {
			throw new RuntimeException(
				"RevisionParserOutput is not available for revision with ID: " . $revision->getId()
			);
		}
		return $output->runOutputPipeline( ParserOptions::newFromUser(
			User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] )
		), [
			'allowTOC' => false,
			'enableSectionEditLinks' => false
		] );
	}
}
