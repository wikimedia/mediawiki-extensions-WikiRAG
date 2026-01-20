<?php

namespace MediaWiki\Extension\WikiRAG;

/**
 * Target that can retrieve context data that was previously stored
 */
interface ContentRetrievableTarget extends ITarget {

	/**
	 * @param ResourceSpecifier $resource
	 * @return array|null on no data
	 */
	public function retrieve( ResourceSpecifier $resource ): ?string;
}
