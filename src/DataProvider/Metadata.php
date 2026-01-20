<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageProps;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use RepoGroup;

class Metadata extends HtmlContent {

	/** @var ParserOutput|null */
	private ?ParserOutput $renderedOutput = null;

	/**
	 * @param RevisionRenderer $revisionRenderer
	 * @param TitleFactory $titleFactory
	 * @param HookContainer $hookContainer
	 * @param PageProps $pageProps
	 * @param RepoGroup $repoGroup
	 * @param IndexabilityChecker $indexabilityChecker
	 * @param RevisionLookup $revisionLookup
	 * @param RedirectLookup $redirectLookup
	 * @param SpecialPageFactory $specialPageFactory
	 */
	public function __construct(
		RevisionRenderer $revisionRenderer,
		private readonly TitleFactory $titleFactory,
		private readonly HookContainer $hookContainer,
		private readonly PageProps $pageProps,
		private readonly RepoGroup $repoGroup,
		private readonly IndexabilityChecker $indexabilityChecker,
		private readonly RevisionLookup $revisionLookup,
		private readonly RedirectLookup $redirectLookup,
		private readonly SpecialPageFactory $specialPageFactory
	) {
		parent::__construct( $revisionRenderer );
	}

	/**
	 * Provides empty file if page is deleted or does not exist.
	 *
	 * @param RevisionRecord $revision
	 * @return string
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		$title = $this->titleFactory->newFromPageIdentity( $revision->getPage() );
		$firstRevision = $this->revisionLookup->getFirstRevision( $title ) ?? $revision;
		$meta = [
			'wiki_id' => WikiMap::getCurrentWikiId(),
			'title' => $title->getPrefixedText(),
			'namespace_text' => $title->getNsText(),
			'url' => $this->getPermalink( $revision ),
			'modification-date' => $revision->getTimestamp(),
			'creation-date' => $firstRevision->getTimestamp(),
			'categories' => $this->getCategories( $revision ),
			'display-title' => $this->getDisplayTitle( $title ),
			'is-redirect' => $title->isRedirect(),
			'redirect_target' => $this->getRedirectTarget( $title ),
			'incoming-link-count' => count( $title->getLinksTo() ),
			'linked_pages' => $this->getLinkedPages( $title ),
			'subpage-of' => $this->getBaseTitleId( $title ),
			'noindex' => $this->getNoRAG( $revision ),
			'includes' => $this->getInclusions( $revision ),
			'sections' => $this->getSections( $revision ),
		];
		$file = $this->getFileForRevision( $revision );
		if ( $file !== null ) {
			$meta['is_file'] = true;
			$meta['mime_type'] = $file->getMimeType();
			$meta['extension'] = $file->getExtension();
		}

		$this->hookContainer->run( 'WikiRAGMetadata', [ $title, $revision, &$meta ] );
		return json_encode( $meta, JSON_PRETTY_PRINT );
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		return [ 'page-content' ];
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array
	 */
	private function getCategories( RevisionRecord $revision ): array {
		$categories = [];

		$links = $this->getParserOutput( $revision )->getLinkList( ParserOutputLinkTypes::CATEGORY );
		foreach ( $links as $link ) {
			$categories[] = $link['link']->getText();
		}

		return $categories;
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @return bool
	 */
	private function getNoRAG( RevisionRecord $revisionRecord ): bool {
		return $this->getParserOutput( $revisionRecord )->getPageProperty( 'NO_RAG' ) !== null;
	}

	/**
	 * @param Title $title
	 * @return false|string|string[]
	 */
	private function getDisplayTitle( Title $title ) {
		$props = $this->pageProps->getProperties( $title, 'displaytitle' );
		return empty( $props ) ? $title->getText() : reset( $props );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @return ParserOutput|null
	 */
	private function getParserOutput( RevisionRecord $revisionRecord ) {
		if ( $this->renderedOutput === null ) {
			$this->renderedOutput = $this->getRenderedOutput( $revisionRecord );
		}
		return $this->renderedOutput;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return \File|null
	 */
	private function getFileForRevision( RevisionRecord $revision ): ?\File {
		$options = [];
		if ( !$revision->isCurrent() ) {
			$options['time'] = $revision->getTimestamp();
		}
		$file = $this->repoGroup->getLocalRepo()->findFile( $revision->getPage(), $options );
		if ( $file && $file->exists() ) {
			return $file;
		}
		return null;
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	private function getLinkedPages( Title $title ): array {
		$linkedPages = [];
		$links = $title->getLinksFrom( [ 'redirects' => false ] );
		foreach ( $links as $link ) {
			$linkTitle = $this->titleFactory->newFromLinkTarget( $link );
			$link = $this->resolveRedirects( $linkTitle );
			if ( $link && $this->indexabilityChecker->isIndexable( $link ) ) {
				$linkedPages[] = ( new ResourceIdGenerator() )->getIdBase( $link->toPageIdentity() );
			}
		}
		return $linkedPages;
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function getRedirectTarget( Title $title ): string {
		$redirTarget = $this->redirectLookup->getRedirectTarget( $title );
		if ( $redirTarget ) {
			$redirTargetTitle = $this->titleFactory->newFromLinkTarget( $redirTarget );
			$redirTarget = $this->resolveRedirects( $redirTargetTitle );
			return $redirTarget ? ( new ResourceIdGenerator() )->getIdBase( $redirTarget->toPageIdentity() ) : '';
		}
		return '';
	}

	/**
	 * @param Title $title
	 * @return string|null
	 */
	private function getBaseTitleId( Title $title ): ?string {
		if ( !$title->isSubpage() ) {
			return null;
		}
		$base = $title->getBaseTitle();
		if ( !$base ) {
			return null;
		}
		return ( new ResourceIdGenerator() )->getIdBase( $base );
	}

	/**
	 * @param RevisionRecord $revision
	 * @return string|null
	 */
	private function getPermalink( RevisionRecord $revision ): ?string {
		$target = $this->specialPageFactory->getPage( 'PermanentLink' )?->getPageTitle( $revision->getId() );
		if ( !$target ) {
			return null;
		}
		return $target->getFullURL();
	}

	/**
	 * @param Title|null $link
	 * @param int $depth
	 * @return Title|null
	 */
	private function resolveRedirects( ?Title $link, int $depth = 0 ): ?Title {
		if ( $depth > 10 ) {
			return $link;
		}
		if ( !$link ) {
			return null;
		}
		if ( !$link->isRedirect() ) {
			return $link;
		}
		$target = $this->redirectLookup->getRedirectTarget( $link );
		if ( $target ) {
			$targetTitle = $this->titleFactory->castFromLinkTarget( $target );
			if ( $targetTitle ) {
				return $this->resolveRedirects( $targetTitle, $depth + 1 );
			}
		}
		return $link;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array
	 */
	private function getInclusions( RevisionRecord $revision ): array {
		$output = $this->getParserOutput( $revision );
		if ( !$output ) {
			return [];
		}
		$includedPages = [];
		$templates = $output->getLinkList( ParserOutputLinkTypes::TEMPLATE );
		/** @var LinkTarget $template */
		foreach ( $templates as $template ) {
			$includedPages[] = $template['link']->getText();
		}

		return $includedPages;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array
	 */
	private function getSections( RevisionRecord $revision ): array {
		$output = $this->getParserOutput( $revision );
		if ( !$output ) {
			return [];
		}
		return $output->getSections();
	}

}
