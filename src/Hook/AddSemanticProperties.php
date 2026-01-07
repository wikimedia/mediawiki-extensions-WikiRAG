<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Content\TextContent;
use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use SMW\DIProperty;

class AddSemanticProperties implements WikiRAGMetadataHook {

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onWikiRAGMetadata( PageIdentity $page, RevisionRecord $revision, array &$meta ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
			return;
		}
		$store = \SMW\StoreFactory::getStore();

		// Get semantic data for the page
		$semanticData = $store->getSemanticData( \SMW\DIWikiPage::newFromTitle( $page ) );

		$propertiesMeta = [];
		$properties = [];
		// Iterate properties
		foreach ( $semanticData->getProperties() as $property ) {
			if ( !$property->isUserDefined() ) {
				// Skip system properties
				continue;
			}
			$label = $property->getCanonicalLabel();
			$values = $semanticData->getPropertyValues( $property );
			$finalValue = [];
			$type = 'plain-text';
			foreach ( $values as $value ) {
				switch ( $value->getDIType() ) {
					case DIProperty::TYPE_WIKIPAGE:
						if ( $value->getTitle()->getNamespace() === NS_USER ) {
							$finalValue[] = $value->getTitle()->getText();
							$type = 'user';
						} else {
							$finalValue[] = ( new ResourceIdGenerator() )->getIdBase( $value->getTitle() );
							$type = 'page';
						}
						break;
					case DIProperty::TYPE_NUMBER:
						$finalValue[] = $value->getSerialization();
						$type = 'number';
						break;
					case DIProperty::TYPE_TIME:
						$time = $value->asDateTime()->format( 'YmdHis' );
						$finalValue[] = $time;
						$type = 'date';
						break;
					default:
						$finalValue[] = $value->getSerialization();
						break;
				}
			}
			if ( count( $finalValue ) === 0 ) {
				continue;
			}
			$propertiesMeta[$label] = [
				'type' => $type,
				'description' => $this->getPropertyDescription( $property ),
			];
			$properties[$label] = $finalValue;
		}

		$meta['property_definitions'] = $propertiesMeta;
		$meta['properties'] = $properties;
	}

	/**
	 * @param DIProperty $property
	 * @return string|null
	 */
	private function getPropertyDescription( DIProperty $property ): ?string {
		$page = $property->getDiWikiPage();
		if ( !$page ) {
			return null;
		}
		$wp = $this->wikiPageFactory->newFromTitle( $page->getTitle() );
		$content = $wp->getContent( SlotRecord::MAIN );
		if ( !( $content instanceof TextContent ) ) {
			return null;
		}
		$text = $content->getText();
		// Strip all [[...::...]]
		$text = preg_replace( '/\[\[.*?::.*?\]\]/', '', $text );
		return trim( $text ) ?: null;
	}
}
