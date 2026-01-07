<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

class WikiTextContent implements IPageDataProvider {

	/**
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		$content = $revision->getContent( SlotRecord::MAIN );
		if ( $this->isWikitextContent( $content ) ) {
			return $content->getText();
		}
		throw new InvalidArgumentException(
			'Revision does not contain wikitext content.'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		if ( !$page->exists() ) {
			return false;
		}
		$content = $this->revisionLookup->getRevisionByTitle( $page )?->getContent( SlotRecord::MAIN );
		return $this->isWikitextContent( $content );
	}

	/**
	 * @param Content|null $content
	 * @return bool
	 */
	private function isWikitextContent( ?Content $content ): bool {
		if ( !$content ) {
			return false;
		}
		return $content instanceof \MediaWiki\Content\WikitextContent;
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		return [ 'page-content' ];
	}
}
