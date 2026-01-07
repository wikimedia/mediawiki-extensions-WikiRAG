<?php

namespace MediaWiki\Extension\WikiRAG\ChangeObserver;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

/**
 * Monitors when the page set of the wiki changes
 * This will rebuild whole wiki structure on each change, due to non-page-provider
 * not being part of the original design
 */
class PromptObserver extends HookObserver implements PageSaveCompleteHook {

	/**
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		HookContainer $hookContainer,
	) {
		parent::__construct( $hookContainer );
		$hookContainer->register( 'PageSaveComplete', [ $this, 'onPageSaveComplete' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if (
			$wikiPage->getTitle()->getNamespace() !== NS_MEDIAWIKI ||
			strtolower( $wikiPage->getTitle()->getDBkey() ) !== 'wikirag-prompt-analyzer'
		) {
			return;
		}
		$this->scheduler?->scheduleContextProvider( 'analyze' );
	}
}
