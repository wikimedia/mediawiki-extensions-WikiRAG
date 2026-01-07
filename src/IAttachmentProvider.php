<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\Revision\RevisionRecord;

interface IAttachmentProvider extends IPageDataProvider {

	/**
	 * @param RevisionRecord $revisionRecord
	 * @return string
	 */
	public function getAttachmentExtension( RevisionRecord $revisionRecord ): string;
}
