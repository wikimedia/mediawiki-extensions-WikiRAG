<?php

namespace MediaWiki\Extension\WikiRAG\Util;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\NamespaceInfo;
use RepoGroup;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

class IndexabilityChecker {

	/**
	 * @param RepoGroup $repoGroup
	 * @param NamespaceInfo $namespaceInfo
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		private readonly RepoGroup $repoGroup,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly HookContainer $hookContainer
	) {
	}

	/**
	 * @param PageIdentity $page
	 * @return bool
	 */
	public function isIndexable( PageIdentity $page ): bool {
		$res = $this->namespaceInfo->isContent( $page->getNamespace() ) &&
			$this->namespaceInfo->isSubject( $page->getNamespace() );
		if ( !$page->exists() ) {
			// Allow deletions to be indexed
			return true;
		}
		$res = $this->isIndexableFile( $page ) || $res;

		$this->hookContainer->run( 'WikiRAGCanBeIndexed', [ $page, &$res ] );

		return $res;
	}

	/**
	 * @param PageIdentity $page
	 * @return bool
	 */
	public function isIndexableFile( PageIdentity $page ): bool {
		if ( $page->getNamespace() !== NS_FILE ) {
			return false;
		}
		$file = $this->repoGroup->getLocalRepo()->findFile( $page );
		if ( !$file || !$file->exists() ) {
			return false;
		}
		$type = strtoupper( $file->getMediaType() );
		return in_array( $type, [ 'OFFICE', 'TEXT' ] );
	}

	/**
	 * @param ILoadBalancer $lb
	 * @return IResultWrapper
	 */
	public function selectIndexablePagesFromDB( ILoadBalancer $lb ): IResultWrapper {
		// Downside of this is that hook `WikiRAGCanBeIndexed` is not called, so pages
		// added by it will not be a part of this result
		$contentNamespaces = $this->namespaceInfo->getContentNamespaces();
		$subjectNamespaces = $this->namespaceInfo->getSubjectNamespaces();
		$namespaces = array_intersect( $contentNamespaces, $subjectNamespaces );
		$namespaces[] = NS_FILE;

		$db = $lb->getConnection( DB_REPLICA );
		return $db->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => $namespaces,
				$db->makeList( [ 'img_media_type IS NULL', 'img_media_type' => [ 'OFFICE', 'TEXT' ] ], LIST_OR )
			] )
			->leftJoin( 'image', null, [ 'img_name = page_title' ] )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

}
