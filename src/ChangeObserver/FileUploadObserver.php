<?php

namespace MediaWiki\Extension\WikiRAG\ChangeObserver;

use ManualLogEntry;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;

class FileUploadObserver extends HookObserver implements
	PageMoveCompleteHook,
	PageUndeleteCompleteHook,
	UploadCompleteHook
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
		$hookContainer->register( 'UploadComplete', [ $this, 'onUploadComplete' ] );
		$hookContainer->register( 'PageUndeleteComplete', [ $this, 'onPageUndeleteComplete' ] );
		$hookContainer->register( 'PageMoveComplete', [ $this, 'onPageMoveComplete' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		if ( $new->getNamespace() !== NS_FILE ) {
			return;
		}
		// Has there be a new upload
		$deletedPage = $this->titleFactory->castFromLinkTarget( $old );
		if ( $deletedPage ) {
			$this->scheduler?->schedule( $deletedPage, $this->pipeline );
		}
		$newPage = $this->titleFactory->castFromLinkTarget( $new );
		if ( $newPage ) {
			$this->scheduler?->schedule( $newPage, $this->pipeline );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onUploadComplete( $uploadBase ) {
		if ( !$uploadBase->getTitle() ) {
			return;
		}
		$title = $this->titleFactory->makeTitle( NS_FILE, $uploadBase->getTitle()->getDBkey() );
		$this->scheduler?->schedule( $title, $this->pipeline );
	}

	/**
	 * @param ProperPageIdentity $page
	 * @param Authority $restorer
	 * @param string $reason
	 * @param RevisionRecord $restoredRev
	 * @param ManualLogEntry $logEntry
	 * @param int $restoredRevisionCount
	 * @param bool $created
	 * @param array $restoredPageIds
	 * @return void
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page, Authority $restorer, string $reason, RevisionRecord $restoredRev,
		ManualLogEntry $logEntry, int $restoredRevisionCount, bool $created, array $restoredPageIds
	): void {
		if ( $page->getNamespace() !== NS_FILE ) {
			return;
		}
		$this->scheduler?->schedule(
			$this->titleFactory->makeTitle( $page->getNamespace(), $page->getDBkey() ),
			$this->pipeline
		);
	}
}
