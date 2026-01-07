<?php

namespace MediaWiki\Extension\WikiRAG\Integration;

use BS\ExtendedSearch\ISearchSource;
use BS\ExtendedSearch\Lookup;
use BS\ExtendedSearch\Plugin\IFormattingModifier;
use BS\ExtendedSearch\Plugin\ISearchPlugin;
use BS\ExtendedSearch\SearchResult;
use BS\ExtendedSearch\Source\WikiPages;
use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Title\Title;

class SearchRAGIdentifierPlugin implements ISearchPlugin, IFormattingModifier {
	/** @var ResourceIdGenerator */
	private readonly ResourceIdGenerator $idGenerator;

	public function __construct() {
		$this->idGenerator = new ResourceIdGenerator();
	}

	/**
	 * @inheritDoc
	 */
	public function formatFulltextResult(
		array &$result,
		SearchResult $resultObject,
		ISearchSource $source,
		Lookup $lookup
	): void {
		if ( !( $source instanceof WikiPages ) ) {
			return;
		}
		$title = Title::newFromRow( (object)[
			'page_id' => $result['page_id'],
			'page_namespace' => $result['namespace'],
			'page_title' => $result['basename']
		] );
		if ( $title ) {
			$result['rag_id'] = $this->idGenerator->getIdBase( $title );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function formatAutocompleteResults( array &$results, array $searchData ): void {
		// NOOP
	}

	/**
	 * @inheritDoc
	 */
	public function modifyResultStructure( array &$resultStructure, ISearchSource $source ): void {
		// NOOP
	}
}
