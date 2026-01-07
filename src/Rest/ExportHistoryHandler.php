<?php

namespace MediaWiki\Extension\WikiRAG\Rest;

use DateTime;
use MediaWiki\Extension\WikiRAG\Scheduler;
use MediaWiki\Extension\WikiRAG\Util\IndexabilityChecker;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ExportHistoryHandler extends SimpleHandler {

	/**
	 * @param IndexabilityChecker $indexabilityChecker
	 * @param TitleFactory $titleFactory
	 * @param Scheduler $scheduler
	 */
	public function __construct(
		private readonly IndexabilityChecker $indexabilityChecker,
		private readonly TitleFactory $titleFactory,
		private readonly Scheduler $scheduler
	) {
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$title = $this->titleFactory->newFromID( $this->getValidatedParams()['page_id'] );
		if ( !$title ) {
			throw new HttpException( 400, 'Invalid page ID' );
		}
		$res = [
			'status' => 'disabled',
			'history' => []
		];
		if ( $this->indexabilityChecker->isIndexable( $title ) ) {
			$history = $this->scheduler->getHistoryForPage( $title );
			if ( !$history ) {
				$res['status'] = 'not-exported';
			} else {
				$this->addFormattedTimestamp( $history );
				$res = [
					'status' => 'exported',
					'history' => $history
				];
			}
		}

		return $this->getResponseFactory()->createJson( $res );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'page_id' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}

	private function addFormattedTimestamp( array &$history ) {
		foreach ( $history as &$item ) {
			$dateTime = DateTime::createFromFormat( 'U', $item['timestamp'] );
			$item['formatted_timestamp'] = $dateTime ? $dateTime->format( 'Y-m-d H:i:s' ) : '';
		}
	}

}
