<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use File;
use MediaWiki\Extension\WikiRAG\IAttachmentProvider;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use RepoGroup;

class RepoFile implements IAttachmentProvider {

	/** @var File|null */
	private ?File $file = null;

	/**
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		private readonly RepoGroup $repoGroup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		$this->assertFile( $revision->getPage(), $revision );
		$path = $this->file->getLocalRefPath();
		if ( file_exists( $path ) ) {
			return file_get_contents( $path );
		}
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		$canProvide = $page->getNamespace() === NS_FILE
			&& $this->assertFile( $page ) && $this->file !== null;
		// Reset file in order for next call to be able to retrieve file based on Revision
		$this->file = null;
		return $canProvide;
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		return [ 'file-upload' ];
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @return string
	 */
	public function getAttachmentExtension( RevisionRecord $revisionRecord ): string {
		$this->assertFile( $revisionRecord->getPage(), $revisionRecord );
		if ( $this->file ) {
			return $this->file->getExtension();
		}
		return '';
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|null $revision
	 * @return true
	 */
	private function assertFile( PageIdentity $page, ?RevisionRecord $revision = null ) {
		if ( !$this->file ) {
			$localRepo = $this->repoGroup->getLocalRepo();
			$options = [];
			if ( $revision && !$revision->isCurrent() ) {
				$options['time'] = $revision->getTimestamp();
			}
			$file = $localRepo->findFile( $page, $options );
			if ( $file && $file->exists() ) {
				$this->file = $file;
			}
		}
		return true;
	}
}
