<?php

namespace MediaWiki\Extension\WikiRAG\Rest;

use Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MWException;
use Wikimedia\IPUtils;

trait IPRangeTrait {

	/**
	 * @return void
	 * @throws HttpException
	 * @throws MWException
	 */
	public function assertClientAllowed( Config $config ) {
		$clientIP = RequestContext::getMain()->getRequest()->getIP();
		if (
			$config->get( 'WikiRAGApiAllowedIP' ) &&
			(
				!IPUtils::isValidRange( $config->get( 'WikiRAGApiAllowedIP' ) ) ||
				!IPUtils::isInRange( $clientIP, $config->get( 'WikiRAGApiAllowedIP' ) )
			)
		) {
			throw new HttpException( 'permissiondenied', 401 );
		}
	}
}
