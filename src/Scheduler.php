<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Wikimedia\Rdbms\ILoadBalancer;

class Scheduler {

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Runner $runner
	 * @param IndexabilityChecker $indexabilityChecker
	 * @param HookContainer $hookContainer
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly TitleFactory $titleFactory,
		private readonly Runner $runner,
		private readonly IndexabilityChecker $indexabilityChecker,
		private readonly HookContainer $hookContainer,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * @param PageIdentity $page
	 * @return bool
	 */
	public function canPageBeScheduled( PageIdentity $page ): bool {
		return $this->indexabilityChecker->isIndexable( $page );
	}

	/**
	 * Clear whole queue
	 * @return void
	 */
	public function clearQueue() {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->delete( 'wikirag_queue' )
			->where( '1=1' )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param PageIdentity $page
	 * @param IPageDataProvider[] $pipeline
	 * @return bool
	 */
	public function schedule( PageIdentity $page, array $pipeline ): bool {
		try {
			if ( !$this->canPageBeScheduled( $page ) ) {
				return false;
			}
			$db = $this->lb->getConnection( DB_PRIMARY );
			$this->clearQueueForPage( $page, array_keys( $pipeline ) );
			$this->hookContainer->run( 'WikiRAGSchedulePipelineForPage', [ $page, &$pipeline ] );

			$rows = [];
			foreach ( array_keys( $pipeline ) as $providerKey ) {
				$rows[] = [
					'wrq_namespace' => $page->getNamespace(),
					'wrq_title' => $page->getDBkey(),
					'wrq_pipeline' => $providerKey,
					'wrq_scheduled_at' => $db->timestamp(),
				];
			}
			$db->newInsertQueryBuilder()
				->insertInto( 'wikirag_queue' )
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
			return true;
		} catch ( Throwable $ex ) {
			$this->logger->error(
				'Scheduling page {namespace}:{title} failed: {message}',
				[
					'namespace' => $page->getNamespace(),
					'title' => $page->getDBkey(),
					'message' => $ex->getMessage(),
				]
			);
		}
		return false;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function scheduleContextProvider( string $key ): bool {
		try {
			$this->clearQueueForContextProviders( [ $key ] );
			$db = $this->lb->getConnection( DB_PRIMARY );
			$db->newInsertQueryBuilder()
				->insertInto( 'wikirag_queue' )
				->row( [
					'wrq_namespace' => 9999,
					'wrq_title' => '__CONTEXT__',
					'wrq_pipeline' => $key,
					'wrq_scheduled_at' => $db->timestamp(),
				] )
				->caller( __METHOD__ )
				->execute();
			return true;
		} catch ( Throwable $ex ) {
			$this->logger->error(
				'Scheduling context provider {provider} failed: {message}',
				[
					'provider' => $key,
					'message' => $ex->getMessage(),
				]
			);
		}
		return false;
	}

	/**
	 * Schedule all pages for indexing
	 *
	 * @param array $pipeline
	 * @param array $contextProviders
	 * @return int[]
	 */
	public function scheduleFullReindex( array $pipeline, array $contextProviders ): array {
		$pages = $this->getAllIndexablePages();
		$this->clearQueue();
		$this->runner->requestPurge();

		$pageCount = 0;
		foreach ( $pages as $page ) {
			$validPipeline = [];
			foreach ( $pipeline as $key => $provider ) {
				if ( $provider->canProvideForPage( $page ) ) {
					$validPipeline[$key] = $provider;
				}
			}
			if ( $this->schedule( $page, $validPipeline ) ) {
				$pageCount++;
			}
		}

		$contextProviderCount = 0;
		foreach ( $contextProviders as $key => $provider ) {
			$this->scheduleContextProvider( $key );
			$contextProviderCount++;
		}

		return [
			'pages' => $pageCount,
			'contextProviders' => $contextProviderCount
		];
	}

	/**
	 * Execute full reindex right away - expensive!
	 *
	 * @param array $pipeline
	 * @param array $contextProviders
	 * @return int[]
	 */
	public function fullReindex( array $pipeline, array $contextProviders ): array {
		$this->scheduleFullReindex( $pipeline, $contextProviders );
		return $this->runQueued();
	}

	/**
	 * @param int $limit
	 * @return array
	 */
	public function runQueued( int $limit = -1 ): array {
		$res = [];
		$pages = $this->getQueued();
		$contentPages = [];
		foreach ( $pages as $page ) {
			if ( $page['title'] === '__CONTEXT__' ) {
				$this->runner->runForContextProviders( $page['providers'] );
				$this->clearQueueForContextProviders( $page['providers'] );
			} else {
				$contentPages[] = $page;
			}
		}
		$cnt = 0;
		foreach ( $contentPages as $page ) {
			if ( $limit >= 0 && $cnt >= $limit ) {
				break;
			}
			$pageObject = $this->titleFactory->makeTitleSafe( $page['namespace'], $page['title'] );
			if ( !$pageObject ) {
				$this->logger->error( 'Cannot create title for queued page', [
					'namespace' => $page['namespace'],
					'title' => $page['title']
				] );
				continue;
			}
			$this->logger->debug( 'Exporting page', [
				'namespace' => $pageObject->getNamespace(),
				'title' => $pageObject->getDBkey(),
				'providers' => implode( ', ', $page['providers'] )
			] );
			$runStatus = $this->runner->runForPage( $pageObject, $page['providers'] );
			if ( $runStatus ) {
				$this->storeRunStatus( $runStatus );
				$res["{$page['namespace']}:{$page['title']}"] = $runStatus;
			}
			$cnt++;
		}

		return $res;
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	public function getHistoryForPage( PageIdentity $page ) {
		$db = $this->lb->getConnection( DB_REPLICA );
		$res = $db->newSelectQueryBuilder()
			->select( [ 'wrh_pipeline', 'wrh_status', 'wrh_error_message', 'wrh_timestamp' ] )
			->from( 'wikirag_history' )
			->where( [
				'wrh_namespace' => $page->getNamespace(),
				'wrh_title' => $page->getDBkey(),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$history = [];
		foreach ( $res as $row ) {
			$history[] = [
				'pipeline' => $row->wrh_pipeline,
				'status' => $row->wrh_status,
				'error_message' => $row->wrh_error_message ?? null,
				'timestamp' => str_replace( "\0", '', $row->wrh_timestamp ),
			];
		}
		return $history;
	}

	/**
	 * @return array
	 */
	public function getQueued(): array {
		$db = $this->lb->getConnection( DB_REPLICA );
		$res = $db->newSelectQueryBuilder()
			->select( [ 'wrq_namespace', 'wrq_title', 'wrq_pipeline', 'wrq_scheduled_at' ] )
			->from( 'wikirag_queue' )
			->orderBy( [ 'wrq_scheduled_at' ], 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$pages = [];
		foreach ( $res as $row ) {
			$key = $row->wrq_namespace . ':' . $row->wrq_title;
			if ( !isset( $pages[$key] ) ) {
				$pages[$key] = [
					'namespace' => $row->wrq_namespace,
					'title' => $row->wrq_title,
					'providers' => [],
					'scheduled_at' => $row->wrq_scheduled_at,
				];
			}
			$pages[$key]['providers'][] = $row->wrq_pipeline;
		}

		return array_values( $pages );
	}

	/**
	 * @return PageIdentity[]
	 */
	private function getAllIndexablePages(): array {
		$res = $this->indexabilityChecker->selectIndexablePagesFromDB( $this->lb );
		$pages = [];
		foreach ( $res as $row ) {
			$page = $this->titleFactory->newFromRow( $row );
			if ( $page && $this->indexabilityChecker->isIndexable( $page ) ) {
				$pages[] = $page;
			}

		}
		return $pages;
	}

	/**
	 * @param PageIdentity $page
	 * @param array $providers
	 * @return void
	 */
	private function clearQueueForPage( PageIdentity $page, array $providers ): void {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$qb = $db->newDeleteQueryBuilder()
			->deleteFrom( 'wikirag_queue' )
			->where( [
				'wrq_namespace' => $page->getNamespace(),
				'wrq_title' => $page->getDBkey(),
			] );
		if ( !empty( $providers ) ) {
			$qb->where( [ 'wrq_pipeline' => $providers ] );
		}
		$qb->caller( __METHOD__ )->execute();
	}

	/**
	 * @param array $providers
	 * @return void
	 */
	private function clearQueueForContextProviders( array $providers ): void {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'wikirag_queue' )
			->where( [
				'wrq_namespace' => 9999,
				'wrq_title' => '__CONTEXT__',
				'wrq_pipeline' => $providers
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param PageIdentity $page
	 * @param array $providers
	 * @return void
	 */
	private function clearHistoryForPage( PageIdentity $page, array $providers = [] ) {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$qb = $db->newDeleteQueryBuilder()
			->deleteFrom( 'wikirag_history' )
			->where( [
				'wrh_namespace' => $page->getNamespace(),
				'wrh_title' => $page->getDBkey(),
			] );
		if ( !empty( $providers ) ) {
			$qb->where( [ 'wrh_pipeline' => $providers ] );
		}
		$qb->caller( __METHOD__ )->execute();
	}

	/**
	 * @param RunStatus $runStatus
	 * @return void
	 */
	private function storeRunStatus( RunStatus $runStatus ) {
		$page = $runStatus->getPageIdentity();
		$providers = array_merge(
			$runStatus->getSuccess(), $runStatus->getSkipped(), array_keys( $runStatus->getFail() )
		);
		$this->clearHistoryForPage( $page, $providers );
		$this->clearQueueForPage( $page, $providers );

		$rows = [];
		foreach ( $runStatus->getSuccess() as $providerKey ) {
			$rows[] = [
				'wrh_namespace' => $page->getNamespace(),
				'wrh_title' => $page->getDBkey(),
				'wrh_pipeline' => $providerKey,
				'wrh_status' => 'success',
				'wrh_error_message' => '',
				'wrh_timestamp' => $runStatus->getTimestamp(),
			];
		}
		foreach ( $runStatus->getFail() as $providerKey => $errorMessage ) {
			$rows[] = [
				'wrh_namespace' => $page->getNamespace(),
				'wrh_title' => $page->getDBkey(),
				'wrh_pipeline' => $providerKey,
				'wrh_status' => 'fail',
				'wrh_error_message' => $errorMessage,
				'wrh_timestamp' => $runStatus->getTimestamp(),
			];
		}
		if ( empty( $rows ) ) {
			return;
		}
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newInsertQueryBuilder()
			->insertInto( 'wikirag_history' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

}
