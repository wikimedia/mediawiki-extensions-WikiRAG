<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

class Deleted implements IPageDataProvider {

	/**
	 * Provides empty file if page is deleted or does not exist.
	 *
	 * @param RevisionRecord $revision
	 * @return string
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		return !$page->exists();
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		// This is an implicit provider, always active
		return [];
	}
}
