<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

interface IPageDataProvider {

	/**
	 * Provide file content based on this revision
	 *
	 * @param RevisionRecord $revision
	 * @return string
	 */
	public function provideForRevision( RevisionRecord $revision ): string;

	/**
	 * Check if this provider can provide data for the given page
	 *
	 * @param PageIdentity $page
	 * @return bool false to skip it
	 */
	public function canProvideForPage( PageIdentity $page ): bool;

	/**
	 * Array of keys of ChangeObservers (WikiRAGChangeObservers attribute) that are looking for changes
	 * relevant to this data provider. If any of these observers detect a change, this data provider will be called
	 *
	 * @return array
	 */
	public function getChangeObservers(): array;
}
