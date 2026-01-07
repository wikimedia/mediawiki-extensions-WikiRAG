<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

interface WikiRAGMetadataHook {

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord $revision
	 * @param array &$meta
	 */
	public function onWikiRAGMetadata( PageIdentity $page, RevisionRecord $revision, array &$meta ): void;
}
