<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit;

use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

class DummyDataProvider implements IPageDataProvider {

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		return 'Foo Bar';
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		return $page->getNamespace() !== NS_HELP && $page->exists();
	}

	/**
	 * @return array
	 */
	public function getChangeObservers(): array {
		return [];
	}
}
