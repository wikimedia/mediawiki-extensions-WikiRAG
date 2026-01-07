<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;

class AddNoRAGMagic implements GetDoubleUnderscoreIDsHook {

	/**
	 * @inheritDoc
	 */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		$doubleUnderscoreIDs[] = 'NO_RAG';
	}
}
