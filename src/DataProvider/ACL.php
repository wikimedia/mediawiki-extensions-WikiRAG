<?php

namespace MediaWiki\Extension\WikiRAG\DataProvider;

use Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\WikiRAG\IPageDataProvider;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

class ACL implements IPageDataProvider {

	/** @var string[] Roles containing the read permission */
	private const BLUESPICE_READER_ROLES = [ 'reader' ];

	/**
	 * @param Config $mainConfig
	 * @param ConfigFactory $configFactory
	 */
	public function __construct(
		private readonly Config $mainConfig,
		private readonly ConfigFactory $configFactory
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function provideForRevision( RevisionRecord $revision ): string {
		// Note: This might better belong in BlueSpice itself, but it also has its benefits to keep it together
		if ( in_array( 'bsg', $this->configFactory->getConfigNames() ) ) {
			return json_encode( $this->getBlueSpiceReadGroups( $revision ) );
		}
		return json_encode( $this->getNativeReadGroups() );
	}

	/**
	 * @inheritDoc
	 */
	public function canProvideForPage( PageIdentity $page ): bool {
		return true;
	}

	/**
	 * @return string[]
	 */
	public function getChangeObservers(): array {
		// Since we have no good way to check when permissions change, lets
		// update on every page update
		return [ 'page-content' ];
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array
	 */
	private function getBlueSpiceReadGroups( RevisionRecord $revision ): array {
		$config = $this->configFactory->makeConfig( 'bsg' );
		// BlueSpice has two layers: global permissions and per-namespace permissions
		// First check namespace lockdown, as that has priority
		$ns = $revision->getPage()->getNamespace();
		$lockdown = $config->get( 'NamespaceRolesLockdown' );
		$readGroups = [];
		if ( isset( $lockdown[$ns] ) ) {
			foreach ( static::BLUESPICE_READER_ROLES as $readerRole ) {
				if ( isset( $lockdown[$ns][$readerRole] ) && is_array( $lockdown[$ns][$readerRole] ) ) {
					$readGroups = array_merge( $readGroups, $lockdown[$ns][$readerRole] );
				}
			}
			return array_unique( $readGroups );
		}
		// No namespace lockdown, check global permissions
		$globalRoles = $config->get( 'GroupRoles' );

		foreach ( $globalRoles as $group => $roles ) {
			foreach ( static::BLUESPICE_READER_ROLES as $readerRole ) {
				if ( isset( $roles[$readerRole] ) && $roles[$readerRole] === true ) {
					$readGroups[] = $group;
				}
			}
		}
		return $readGroups;
	}

	/**
	 * @return array
	 */
	private function getNativeReadGroups(): array {
		$groupPermissions = $this->mainConfig->get( 'GroupPermissions' );
		$readGroups = [];
		foreach ( $groupPermissions as $group => $permissions ) {
			if ( !empty( $permissions['read'] ) && $permissions['read'] === true ) {
				$readGroups[] = $group;
			}
		}
		return $readGroups;
	}
}
