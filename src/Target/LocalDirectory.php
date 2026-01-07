<?php

namespace MediaWiki\Extension\WikiRAG\Target;

use Config;
use MediaWiki\Extension\WikiRAG\ITarget;
use MediaWiki\Extension\WikiRAG\ResourceSpecifier;

/**
 * Required configuration
 * $wgWikiRAGTarget = [
 *   'type' => 'local-directory',
 *   'configuration' => [
 *     'path' => '/path/to/directory'
 *   ]
 * ]
 */
class LocalDirectory implements ITarget {

	/** @var Config|null */
	private ?Config $config = null;

	/**
	 * @inheritDoc
	 */
	public function setConfig( Config $config ): void {
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function write( ResourceSpecifier $resource ): void {
		$filePath = $this->getTargetPath( $resource );
		if ( file_put_contents( $filePath, $resource->getContent() ) === false ) {
			throw new \RuntimeException( "WikiRAG: Failed to write to file '$filePath'" );
		}
	}

	/**
	 * @param ResourceSpecifier $resource
	 * @return void
	 */
	public function remove( ResourceSpecifier $resource ): void {
		$filePath = $this->getTargetPath( $resource );
		if ( file_exists( $filePath ) ) {
			unlink( $filePath );
		}
	}

	/**
	 * @param ResourceSpecifier $result
	 * @return string
	 */
	private function getTargetPath( ResourceSpecifier $result ): string {
		$path = $this->config->get( 'path' );
		if ( !file_exists( $path ) ) {
			if ( !mkdir( $path, 0777, true ) && !is_dir( $path ) ) {
				throw new \RuntimeException( "WikiRAG: Failed to create directory '$path'" );
			}
		}
		return $path . DIRECTORY_SEPARATOR . $result->getFileName();
	}
}
