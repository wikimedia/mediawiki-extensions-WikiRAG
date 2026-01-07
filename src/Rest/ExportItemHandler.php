<?php

namespace MediaWiki\Extension\WikiRAG\Rest;

use Config;
use GuzzleHttp\Psr7\Utils;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Extension\WikiRAG\ResourceSpecifier;
use MediaWiki\Extension\WikiRAG\Runner;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ExportItemHandler extends SimpleHandler {

	use IPRangeTrait;

	public function __construct(
		private readonly PermissionManager $permissionManager,
		private readonly Runner $runner,
		private readonly TitleFactory $titleFactory,
		private readonly Config $config
	) {
	}

	public function execute() {
		$this->assertClientAllowed( $this->config );
		$params = $this->getValidatedParams();
		$id = $params['id'];
		$page = ( new ResourceIdGenerator() )->pageFromIdBase( $id, $this->titleFactory );
		if ( !$page ) {
			throw new HttpException( 'Invalid id' );
		}

		if ( !$this->permissionManager->userCan( 'read', RequestContext::getMain()->getUser(), $page ) ) {
			throw new HttpException( 'permissiondenied', 401 );
		}
		$status = $this->runner->runForPage( $page, [ $params['provider'] ] );
		if ( !$status ) {
			throw new HttpException( 'Failed to run export' );
		}
		$written = $status->getWrittenSpecifiers();
		if ( empty( $written ) ) {
			throw new HttpException( 'No export data written' );
		}
		/** @var ResourceSpecifier $specifier */
		$specifier = $written[0];
		$response = $this->getResponseFactory()->create();
		$response->setHeader( 'Content-Disposition', 'attachment; filename="' . $specifier->getFileName() . '"' );
		$mime = MediaWikiServices::getInstance()->getMimeAnalyzer()->guessMimeType( $specifier->getFileName() );
		$response->setHeader( 'Content-Type', $mime );
		$response->setBody( Utils::streamFor( $specifier->getContent() ) );

		return $response;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'id' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'provider' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}
}
