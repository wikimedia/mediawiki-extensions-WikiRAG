<?php

namespace MediaWiki\Extension\WikiRAG;

use JsonSerializable;

class ResourceSpecifier implements JsonSerializable {

	/**
	 * @param string $resourceId
	 * @param string $extension
	 * @param string $content
	 */
	public function __construct(
		private readonly string $resourceId,
		private readonly string $extension,
		private readonly string $content = ''
	) {
	}

	/**
	 * @return string
	 */
	public function getFileName(): string {
		return $this->resourceId . '.' . $this->extension;
	}

	/**
	 * @return string
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'extension' => $this->extension,
			'fileName' => $this->getFileName(),
			'content' => $this->getContent(),
		];
	}
}
