<?php

namespace MediaWiki\Extension\WikiRAG\ChangeObserver;

use ManualLogEntry;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\TitleFactory;

class PageContentObserver extends HookObserver implements
	PageSaveCompleteHook,
	PageMoveCompleteHook,
	PageUndeleteCompleteHook
{

	/**
	 * @param HookContainer $hookContainer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly TitleFactory $titleFactory
	) {
		parent::__construct( $hookContainer );
		$this->hookContainer->register( 'PageSaveComplete', [ $this, 'onPageSaveComplete' ] );
		$this->hookContainer->register( 'PageMoveComplete', [ $this, 'onPageMoveComplete' ] );
		$this->hookContainer->register( 'PageUndeleteComplete', [ $this, 'onPageUndeleteComplete' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		$this->scheduler?->schedule(
			$revisionRecord->getPage(),
			$this->pipeline
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		if ( $redirid ) {
			$oldTitle = $this->titleFactory->castFromLinkTarget( $old );
			if ( $oldTitle ) {
				// If redirect is left, update old page
				$this->scheduler?->schedule(
					$oldTitle,
					$this->pipeline
				);
			}
		}
		// Index new page
		$this->scheduler?->schedule(
			$revision->getPage(),
			$this->pipeline
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page, Authority $restorer, string $reason, RevisionRecord $restoredRev,
		ManualLogEntry $logEntry, int $restoredRevisionCount, bool $created, array $restoredPageIds
	): void {
		$this->scheduler?->schedule( $page, $this->pipeline );
	}
}
