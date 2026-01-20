<?php

namespace MediaWiki\Extension\WikiRAG\ContextProvider;

use MediaWiki\Extension\WikiRAG\IContextProvider;

/**
 * Context provider providing a list of values to create nodes out of
 */
abstract class GenericNodeContextProvider implements IContextProvider {

	/**
	 * Node type as created in graph store - careful!
	 * @return string
	 */
	abstract protected function getNodeType(): string;

	/**
	 * Array of node data - must be data supported by Neo4j nodes
	 * @return array
	 */
	abstract protected function getData(): array;

	/**
	 * @return array
	 */
	abstract protected function getUniqueFields(): array;

	/**
	 * @return string
	 */
	final public function provide(): string {
		$data = $this->getData();
		$context = [
			'isGeneric' => true,
			'keys' => $this->getUniqueFields(),
			'nodeType' => $this->getNodeType(),
			'data' => $data,
		];

		return json_encode( $context, JSON_PRETTY_PRINT );
	}

	/**
	 * @return string
	 */
	public function getExtension(): string {
		return 'json';
	}
}
