<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

class ID implements IPageDataProvider {

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		return ( new ResourceIdGenerator() )->getIdBase( $revision->getPage() );
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		return true;
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		// This is an implicit provider, always active
		return [];
	}
}
