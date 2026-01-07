<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class Runner implements LoggerAwareInterface {

	/** @var ITarget|null */
	private ?ITarget $target = null;
	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/**
	 * @param Factory $factory
	 * @param HookContainer $hookContainer
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly Factory $factory,
		private readonly HookContainer $hookContainer,
		private readonly RevisionLookup $revisionLookup
	) {
		$this->logger = new NullLogger();
	}

	/**
	 * Request target to delete all resources for the current wiki
	 * @return void
	 */
	public function requestPurge() {
		$this->assertTarget();
		$this->target->write( new ResourceSpecifier(
			WikiMap::getCurrentWikiId(),
			'purge'
		) );
	}

	/**
	 * @param PageIdentity $page
	 * @param array $pipeline
	 * @return RunStatus|null
	 */
	public function runForPage( PageIdentity $page, array $pipeline ): ?RunStatus {
		$this->assertTarget();

		if ( $page->exists() ) {
			$revision = $this->revisionLookup->getRevisionByTitle( $page );
			$this->hookContainer->run( 'WikiRAGRunForPage', [ $page, &$revision ] );
			if ( !$revision ) {
				$this->logger->warning(
					"Page {page} has no valid page for export",
					[
						'page' => $page->getNamespace() . ':' . $page->getDBkey(),
					]
				);
				return null;
			}
		} else {
			$revision = new MutableRevisionRecord( $page );
		}

		$pipeline = array_unique( $pipeline );
		$configuredPipeline = $this->factory->getPipeline();
		$configuredPipeline['deleted'] = true;

		$resourceId = ( new ResourceIdGenerator() )->generateResourceId( $page );

		$result = new RunStatus( $page );
		foreach ( $pipeline as $providerKey ) {
			try {
				if ( !isset( $configuredPipeline[$providerKey] ) ) {
					continue;
				}
				$provider = $this->factory->getDataProvider( $providerKey );
				$ragFileExtension = $provider instanceof IForcedExtensionProvider ?
					$provider->getFileExtension() : $providerKey;
				if ( $provider instanceof IAttachmentProvider ) {
					$attachmentExtension = $provider->getAttachmentExtension( $revision );
					$ragFileExtension = "attachment.$attachmentExtension";
				}
				if ( !$provider->canProvideForPage( $page ) ) {
					if ( !( $provider instanceof IForcedExtensionProvider ) ) {
						$this->target->remove( new ResourceSpecifier( $resourceId, $ragFileExtension ) );
					}
					$result->skippedProvider( $providerKey );
					$this->logger->debug(
						"Skipping provider {provider} for page {page} as it cannot provide content.",
						[
							'provider' => $providerKey,
							'page' => $page->getNamespace() . ':' . $page->getDBkey(),
						]
					);
					continue;
				}
				$content = $provider->provideForRevision( $revision );

				$resource = new ResourceSpecifier( $resourceId, $ragFileExtension, $content );
				$this->target->write( $resource );
				$result->successfullProvider( $providerKey, $resource );
			} catch ( Throwable $ex ) {
				$this->logger->error(
					"Error in provider {provider} for page {page}: {message}",
					[
						'provider' => $providerKey,
						'page' => $page->getNamespace() . ':' . $page->getDBkey(),
						'message' => $ex->getMessage()
					]
				);
				$result->failedProvider( $providerKey, $ex->getMessage() );
			}
		}

		return $result->finish();
	}

	/**
	 * @param array $providers
	 * @return void
	 */
	public function runForContextProviders( array $providers ) {
		$this->assertTarget();

		foreach ( $providers as $providerKey ) {
			$provider = $this->factory->getContextProvider( $providerKey );
			if ( !$provider ) {
				$this->logger->error( "Context provider {provider} not found.", [ 'provider' => $providerKey ] );
				continue;
			}
			try {
				$resourceKey = WikiMap::getCurrentWikiId() . '.context.' . $providerKey;
				if ( !$provider->canProvide() ) {
					$this->logger->debug( "Skipping context provider {provider} as it cannot provide content.", [
						'provider' => $providerKey
					] );
					$this->target->remove( new ResourceSpecifier( $resourceKey, $provider->getExtension() ) );
					continue;
				}
				$content = $provider->provide();
				if ( $content ) {
					$this->target->write( new ResourceSpecifier( $resourceKey, $provider->getExtension(), $content ) );
					$this->logger->info( "Context provider {provider} ran successfully.", [
						'provider' => $providerKey
					] );
				}
			} catch ( Throwable $ex ) {
				$this->logger->error(
					"Error in context provider {provider}: {message}",
					[
						'provider' => $providerKey,
						'message' => $ex->getMessage()
					]
				);
			}

		}
	}

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @return void
	 */
	private function assertTarget(): void {
		if ( $this->target === null ) {
			$this->target = $this->factory->getTarget();
		}
	}
}
