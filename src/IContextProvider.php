<?php

namespace MediaWiki\Extension\WikiRAG;

interface IContextProvider {

	/**
	 * @return string
	 */
	public function provide(): string;

	/**
	 * @return bool
	 */
	public function canProvide(): bool;

	/**
	 * @return string
	 */
	public function getExtension(): string;
}
