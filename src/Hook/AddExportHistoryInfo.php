<?php

namespace MediaWiki\Extension\WikiRAG\Hook;

use MediaWiki\Config\Config;
use MediaWiki\Extension\StandardDialogs\Hook\StandardDialogsRegisterPageInfoPanelModules;
use MediaWiki\ResourceLoader\Context as ResourceLoaderContext;

class AddExportHistoryInfo implements StandardDialogsRegisterPageInfoPanelModules {

	/**
	 * @inheritDoc
	 */
	public function onStandardDialogsRegisterPageInfoPanelModules(
		&$modules,
		ResourceLoaderContext $context,
		Config $config
	): void {
		$modules[] = "ext.wikirag.pageInfo";
	}
}
