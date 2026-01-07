<?php

namespace MediaWiki\Extension\WikiRAG;

/**
 * Normally, the file extension of the created file is derived from the registry key of the provider.
 * With this interface, a provider can force a specific file extension.
 */
interface IForcedExtensionProvider extends IPageDataProvider {

	/**
	 * @return string
	 */
	public function getFileExtension(): string;
}
