<?php

namespace MediaWiki\Extension\WikiRAG\ChangeObserver;

use ManualLogEntry;
use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

/**
 * Monitors when the page set of the wiki changes
 * This will rebuild whole wiki structure on each change, due to non-page-provider
 * not being part of the original design
 */
class PageSetObserver extends HookObserver implements
	PageSaveCompleteHook,
	PageDeleteCompleteHook,
	PageMoveCompleteHook,
	PageUndeleteCompleteHook
{

	/**
	 * @param HookContainer $hookContainer
	 * @param IndexabilityChecker $indexabilityChecker
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly IndexabilityChecker $indexabilityChecker
	) {
		parent::__construct( $hookContainer );
		$hookContainer->register( 'PageSaveComplete', [ $this, 'onPageSaveComplete' ] );
		$hookContainer->register( 'PageDeleteComplete', [ $this, 'onPageDeleteComplete' ] );
		$hookContainer->register( 'PageUndeleteComplete', [ $this, 'onPageUndeleteComplete' ] );
		$hookContainer->register( 'PageMoveComplete', [ $this, 'onPageMoveComplete' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( !$editResult->isNew() ) {
			return;
		}
		if ( $this->indexabilityChecker->isIndexable( $wikiPage->getTitle() ) ) {
			$this->scheduler?->scheduleContextProvider( 'wiki-structure' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page, Authority $restorer, string $reason, RevisionRecord $restoredRev,
		ManualLogEntry $logEntry, int $restoredRevisionCount, bool $created, array $restoredPageIds
	): void {
		if ( $this->indexabilityChecker->isIndexable( $page ) ) {
			$this->scheduler?->scheduleContextProvider( 'wiki-structure' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		if ( $this->indexabilityChecker->isIndexable( $page ) ) {
			$this->scheduler?->scheduleContextProvider( 'wiki-structure' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$old = new PageIdentityValue( 0, $old->getNamespace(), $old->getDBkey(), '' );
		$new = new PageIdentityValue( 0, $new->getNamespace(), $new->getDBkey(), '' );
		if ( $this->indexabilityChecker->isIndexable( $old ) || $this->indexabilityChecker->isIndexable( $new ) ) {
			$this->scheduler?->scheduleContextProvider( 'wiki-structure' );
		}
	}
}
