<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;

class ResourceIdGenerator {

	/**
	 * @param PageIdentity $page
	 * @return string
	 */
	public function getIdBase( PageIdentity $page ) {
		return implode( '|', [
			WikiMap::getCurrentWikiId(),
			$page->getNamespace(),
			$page->getDBkey()
		] );
	}

	/**
	 * @param string $idBase
	 * @param TitleFactory $titleFactory
	 * @return PageIdentity|null
	 */
	public function pageFromIdBase( string $idBase, TitleFactory $titleFactory ): ?PageIdentity {
		$bits = explode( '|', $idBase );
		if ( count( $bits ) !== 3 ) {
			return null;
		}
		[ $wikiId, $ns, $dbKey ] = $bits;
		if ( $wikiId !== WikiMap::getCurrentWikiId() ) {
			return null;
		}
		return $titleFactory->makeTitleSafe( (int)$ns, $dbKey );
	}

	/**
	 * Generate a unique resource ID based on provided page
	 *
	 * @param PageIdentity $page
	 * @return string
	 */
	public function generateResourceId( PageIdentity $page ): string {
		return sha1( $this->getIdBase( $page ) );
	}

}
