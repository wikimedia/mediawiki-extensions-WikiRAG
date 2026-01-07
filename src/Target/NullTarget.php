<?php

namespace MediaWiki\Extension\WikiRAG\Target;

use Config;
use MediaWiki\Extension\WikiRAG\ITarget;
use MediaWiki\Extension\WikiRAG\ResourceSpecifier;

class NullTarget implements ITarget {

	/**
	 * @inheritDoc
	 */
	public function setConfig( Config $config ): void {
		// NOOP
	}

	/**
	 * @inheritDoc
	 */
	public function write( ResourceSpecifier $resource ): void {
		// NOOP
	}

	/**
	 * @param ResourceSpecifier $resource
	 * @return void
	 */
	public function remove( ResourceSpecifier $resource ): void {
		// NOOP
	}
}
