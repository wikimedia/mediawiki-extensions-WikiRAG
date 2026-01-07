<?php

namespace MediaWiki\Extension\WikiRAG\ContextProvider;

use MediaWiki\Extension\WikiRAG\IContextProvider;
use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWiki\Language\Language;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\ILoadBalancer;

class WikiStructure implements IContextProvider {

	/**
	 * @param ILoadBalancer $lb
	 * @param IndexabilityChecker $indexabilityChecker
	 * @param Language $language
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly IndexabilityChecker $indexabilityChecker,
		private readonly Language $language,
		private readonly NamespaceInfo $namespaceInfo
	) {
	}

	/**
	 * @return string
	 */
	public function provide(): string {
		$res = $this->indexabilityChecker->selectIndexablePagesFromDB( $this->lb );
		$pages = [];
		foreach ( $res as $row ) {
			$nsText = $this->language->getNsText( $row->page_namespace );
			// Yes, for NS_MAIN we still want title to start with a colon
			$pages[] = $nsText . ':' . $row->page_title;
		}
		$tree = $this->makeTree( $pages );
		return json_encode( [
			'wiki_id' => WikiMap::getCurrentWikiId(),
			'pages' => $tree,
			'namespaces' => $this->getNamespaceMap()
		], JSON_PRETTY_PRINT );
	}

	/**
	 * @param array $pages
	 * @return array
	 */
	private function makeTree( array $pages ): array {
		$tree = [];
		foreach ( $pages as $page ) {
			$parts = explode( '/', $page );
			$node =& $tree;

			foreach ( $parts as $part ) {
				if ( !isset( $node[$part] ) ) {
					$node[$part] = [];
				}
				$node =& $node[$part];
			}
		}
		return $tree;
	}

	private function getNamespaceMap(): array {
		$namespaces = array_intersect(
			$this->namespaceInfo->getSubjectNamespaces(), $this->namespaceInfo->getContentNamespaces()
		);
		$namespaces = array_values( $namespaces );
		$namespaces[] = NS_FILE;
		$namespaces = array_values( array_unique( $namespaces ) );

		$names = [];
		foreach ( $namespaces as $nsId ) {
			$names[$nsId] = $this->language->getNsText( $nsId );
		}
		return $names;
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
		return 'json';
	}
}
