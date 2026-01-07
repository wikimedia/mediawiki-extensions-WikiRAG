<?php

namespace MediaWiki\Extension\WikiRAG\ContextProvider;

use MediaWiki\Extension\WikiRAG\IContextProvider;
use MediaWiki\Message\Message;

class AnalyzePrompts implements IContextProvider {

	/**
	 * @return string
	 */
	public function provide(): string {
		return Message::newFromKey( 'wikirag-prompt-analyzer' )->parse();
	}

	/**
	 * @return bool
	 */
	public function canProvide(): bool {
		return true;
	}

	/**
	 * @return string
	 */
	public function getExtension(): string {
		return 'prompt';
	}
}
