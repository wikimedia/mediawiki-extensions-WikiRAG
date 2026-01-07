<?php

namespace MediaWiki\Extension\WikiRAG;

use Config;

interface ITarget {

	/**
	 * Set configured target-specific configuration
	 *
	 * @param Config $config
	 * @return void
	 */
	public function setConfig( Config $config ): void;

	/**
	 * @param ResourceSpecifier $resource
	 * @return void
	 */
	public function write( ResourceSpecifier $resource ): void;

	/**
	 * @param ResourceSpecifier $resource
	 * @return void
	 */
	public function remove( ResourceSpecifier $resource ): void;
}
