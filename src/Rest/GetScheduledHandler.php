<?php

namespace MediaWiki\Extension\WikiRAG\Rest;

use Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;

class GetScheduledHandler extends SimpleHandler {
	use IPRangeTrait;

	/**
	 * @param PermissionManager $permissionManager
	 * @param Scheduler $scheduler
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 */
	public function __construct(
		private readonly PermissionManager $permissionManager,
		private readonly Scheduler $scheduler,
		private readonly TitleFactory $titleFactory,
		private readonly Config $config
	) {
	}

	public function execute() {
		$this->assertClientAllowed( $this->config );
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'read' ) ) {
			throw new HttpException( 'permissiondenied', 401 );
		}
		$queue = $this->scheduler->getQueued();
		$exportData = [];
		foreach ( $queue as $item ) {
			$page = $this->titleFactory->makeTitleSafe( $item['namespace'], $item['title'] );
			if ( !$page ) {
				continue;
			}
			$id = ( new ResourceIdGenerator() )->getIdBase( $page );
			$exportData[$id] = [
				'namespace' => $page->getNamespace(),
				'dbkey' => $page->getDBkey(),
				'prefixed' => $page->getPrefixedText(),
				'providers' => $item['providers'] ?? [],
				'wiki_id' => $page->getWikiId(),
				'hash_id' => ( new ResourceIdGenerator() )->generateResourceId( $page ),
				'scheduled_at' => $item['scheduled_at'] ?? '',
			];
		}
		return $this->getResponseFactory()->createJson( $exportData );
	}
}
