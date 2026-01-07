<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Utils\MWTimestamp;

class RunStatus {
	/** @var array */
	private array $success = [];
	/** @var array */
	private array $fail = [];
	/** @var array */
	private array $skipped = [];
	/** @var MWTimestamp|null */
	private ?MWTimestamp $timestamp = null;
	private array $writtenSpecifiers = [];

	/**
	 * @param PageIdentity $pageIdentity
	 */
	public function __construct(
		private readonly PageIdentity $pageIdentity
	) {
	}

	/**
	 * @return PageIdentity
	 */
	public function getPageIdentity(): PageIdentity {
		return $this->pageIdentity;
	}

	/**
	 * @return array
	 */
	public function getSuccess(): array {
		return $this->success;
	}

	/**
	 * @return array
	 */
	public function getFail(): array {
		return $this->fail;
	}

	/**
	 * @return array
	 */
	public function getSkipped(): array {
		return $this->skipped;
	}

	/**
	 * @return MWTimestamp|null
	 */
	public function getTimestamp(): ?MWTimestamp {
		return $this->timestamp;
	}

	/**
	 * @return array
	 */
	public function getWrittenSpecifiers(): array {
		return $this->writtenSpecifiers;
	}

	/**
	 * @param string $key
	 * @param ResourceSpecifier $specifier
	 * @return void
	 */
	public function successfullProvider( string $key, ResourceSpecifier $specifier ) {
		$this->success[] = $key;
		$this->writtenSpecifiers[] = $specifier;
	}

	/**
	 * @param string $key
	 * @return void
	 */
	public function skippedProvider( string $key ) {
		$this->skipped[] = $key;
	}

	/**
	 * @param string $key
	 * @param string $error
	 * @return void
	 */
	public function failedProvider( string $key, string $error ) {
		$this->fail[$key] = $error;
	}

	/**
	 * @return $this
	 */
	public function finish(): static {
		$this->timestamp = new MWTimestamp();
		return $this;
	}
}
