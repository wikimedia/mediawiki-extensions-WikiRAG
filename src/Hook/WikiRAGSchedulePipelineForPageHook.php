<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Page\PageIdentity;

interface WikiRAGSchedulePipelineForPageHook {

	/**
	 * Called when a pipeline is about to be executed for a page
	 * Allows modification of the pipeline
	 *
	 * @param PageIdentity $page
	 * @param array &$pipeline Assoc array of pipeline key => instance
	 * @return void
	 */
	public function onWikiRAGSchedulePipelineForPage( PageIdentity $page, array &$pipeline ): void;
}
