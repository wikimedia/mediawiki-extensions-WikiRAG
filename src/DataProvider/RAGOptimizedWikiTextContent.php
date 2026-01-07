<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use MediaWiki\Extension\WikiRAG\Util\WikitextRAGOptimizer;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;

class RAGOptimizedWikiTextContent extends WikiTextContent {

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param WikitextRAGOptimizer $optimizer
	 */
	public function __construct( RevisionLookup $revisionLookup, private readonly WikitextRAGOptimizer $optimizer ) {
		parent::__construct( $revisionLookup );
	}

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		$content = parent::provideForRevision( $revision );
		return $this->optimizer->process( $content );
	}
}
