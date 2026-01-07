<?php

namespace MediaWiki\Extension\WikiRAG\ChangeObserver;

use ManualLogEntry;
use MediaWiki\Extension\WikiRAG\DataProvider\Deleted;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;

class PageDeletionObserver extends HookObserver implements PageDeleteCompleteHook, PageMoveCompleteHook {

	/**
	 * @param HookContainer $hookContainer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly TitleFactory $titleFactory
	) {
		parent::__construct( $hookContainer );
		$hookContainer->register( 'PageDeleteComplete', [ $this, 'onPageDeleteComplete' ] );
		$hookContainer->register( 'PageMoveComplete', [ $this, 'onPageMoveComplete' ] );
	}

	public function setPipeline( array $pipeline ): void {
		parent::setPipeline( $pipeline );
		$this->pipeline['deleted'] = new Deleted();
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$this->scheduler?->schedule( $page, $this->pipeline );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		if ( $redirid !== 0 ) {
			// Do not delete redirect pages
			return;
		}
		$deletedPage = $this->titleFactory->makeTitle( $old->getNamespace(), $old->getDBkey() );
		$this->scheduler?->schedule( $deletedPage, $this->pipeline );
	}
}
