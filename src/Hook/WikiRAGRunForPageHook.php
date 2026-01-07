<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

interface WikiRAGRunForPageHook {
	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|null &$revision
	 */
	public function onWikiRAGRunForPage( PageIdentity $page, ?RevisionRecord &$revision ): void;
}
