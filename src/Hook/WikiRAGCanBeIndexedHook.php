<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Page\PageIdentity;

interface WikiRAGCanBeIndexedHook {
	/**
	 * @param PageIdentity $page
	 * @param bool &$canBeIndexed
	 */
	public function onWikiRAGCanBeIndexed( PageIdentity $page, bool &$canBeIndexed ): void;
}
