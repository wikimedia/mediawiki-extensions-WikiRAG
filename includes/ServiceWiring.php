<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

return [
	'WikiRAG._Logger' => static function ( MediaWikiServices $services ) {
		return \MediaWiki\Logger\LoggerFactory::getInstance( 'WikiRAG' );
	},
	'WikiRAG._Factory' => static function ( MediaWikiServices $services ) {
		return new \MediaWiki\Extension\WikiRAG\Factory(
			$services->getMainConfig(),
			ExtensionRegistry::getInstance()->getAttribute( 'WikiRAGTargets' ),
			ExtensionRegistry::getInstance()->getAttribute( 'WikiRAGDataProviders' ),
			ExtensionRegistry::getInstance()->getAttribute( 'WikiRAGChangeObservers' ),
			ExtensionRegistry::getInstance()->getAttribute( 'WikiRAGContextProviders' ),
			$services->getObjectFactory()
		);
	},
	'WikiRAG.Runner' => static function ( MediaWikiServices $services ) {
		$runner = new \MediaWiki\Extension\WikiRAG\Runner(
			$services->getService( 'WikiRAG._Factory' ),
			$services->getHookContainer(),
			$services->getRevisionLookup()
		);
		$runner->setLogger( $services->getService( 'WikiRAG._Logger' ) );
		return $runner;
	},
	'WikiRAG.Scheduler' => static function ( MediaWikiServices $services ) {
		return new \MediaWiki\Extension\WikiRAG\Scheduler(
			$services->getDBLoadBalancer(),
			$services->getTitleFactory(),
			$services->getService( 'WikiRAG.Runner' ),
			$services->getService( 'WikiRAG._IndexabilityChecker' ),
			$services->getHookContainer(),
			$services->getService( 'WikiRAG._Logger' ),
		);
	},
	'WikiRAG._IndexabilityChecker' => static function ( MediaWikiServices $services ) {
		return new \MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker(
			$services->getRepoGroup(),
			$services->getNamespaceInfo(),
			$services->getHookContainer()
		);
	},
	'WikiRAG._WikitextRAGOptimizer' => static function ( MediaWikiServices $services ) {
		return new \MediaWiki\Extension\WikiRAG\Util\WikitextRAGOptimizer(
			$services->getMagicWordFactory()
		);
	},
];
