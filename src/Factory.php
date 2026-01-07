<?php

namespace MediaWiki\Extension\WikiRAG;

use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\WikiRAG\DataProvider\Deleted;
use MediaWiki\Extension\WikiRAG\DataProvider\ID;
use RuntimeException;
use Wikimedia\ObjectFactory\ObjectFactory;

class Factory {

	/** @var ITarget|null */
	private ?ITarget $target = null;
	/** @var array */
	private array $dataProviders = [];
	/** @var array|null */
	private ?array $changeObservers = null;
	/** @var array|null */
	private ?array $contextProviders = null;

	/**
	 * @param Config $config
	 * @param array $targetRegistry
	 * @param array $dataProviderRegistry
	 * @param array $changeObserverRegistry
	 * @param array $contextProvidersRegistry
	 * @param ObjectFactory $objectFactory
	 */
	public function __construct(
		private readonly Config $config,
		private readonly array $targetRegistry,
		private array $dataProviderRegistry,
		private readonly array $changeObserverRegistry,
		private readonly array $contextProvidersRegistry,
		private readonly ObjectFactory $objectFactory
	) {
		// Implicit providers
		$this->dataProviderRegistry['deleted'] = [
			'class' => Deleted::class,
		];
		$this->dataProviderRegistry['id'] = [
			'class' => ID::class,
		];
	}

	/**
	 * @return ITarget
	 */
	public function getTarget(): ITarget {
		if ( $this->target === null ) {
			$targetConfig = $this->config->get( 'WikiRAGTarget' );
			if ( !is_array( $targetConfig ) || !isset( $targetConfig['type'] ) ) {
				throw new RuntimeException( 'WikiRAG: Invalid target configuration' );
			}

			$type = $targetConfig['type'];
			if ( !$type || !isset( $this->targetRegistry[$type] ) ) {
				throw new RuntimeException( "WikiRAG: Target type '$type' not found in registry" );
			}
			$this->target = $this->createObject( $this->targetRegistry[$type], ITarget::class );
			$config = $this->parseTargetConfig( $targetConfig['configuration'] ?? [] );
			$this->target->setConfig( $config );
		}

		return $this->target;
	}

	/**
	 * @return bool
	 */
	public function isConfigured(): bool {
		$pipelineConfig = $this->config->get( 'WikiRAGPipeline' );
		if ( !is_array( $pipelineConfig ) || empty( $pipelineConfig ) ) {
			return false;
		}
		if ( !$this->getTarget() ) {
			return false;
		}
		return true;
	}

	/**
	 * @return IPageDataProvider[]
	 */
	public function getPipeline( ?string $forChangeObserver = null ): array {
		$pipelineItems = $this->config->get( 'WikiRAGPipeline' );
		if ( !is_array( $pipelineItems ) ) {
			throw new RuntimeException( 'WikiRAG: Invalid pipeline configuration' );
		}
		$pipeline = [];
		foreach ( $pipelineItems as $item ) {
			$provider = $this->getDataProvider( $item );
			if ( $forChangeObserver ) {
				$supportedObservers = $provider->getChangeObservers();
				if ( in_array( $forChangeObserver, $supportedObservers ) ) {
					$pipeline[$item] = $provider;
				}
			} else {
				$pipeline[$item] = $provider;
			}
		}
		if ( !isset( $pipeline['id'] ) ) {
			// Ensure implicit ID provider is always set
			$pipeline['id'] = $this->getDataProvider( 'id' );
		}

		return $pipeline;
	}

	/**
	 * @return IChangeObserver[]
	 */
	public function getChangeObservers(): array {
		if ( $this->changeObservers === null ) {
			$this->changeObservers = [];
			foreach ( $this->changeObserverRegistry as $key => $spec ) {
				if ( !$this->isDisabled( $key ) ) {
					continue;
				}
				$object = $this->createObject( $spec, IChangeObserver::class );
				$this->changeObservers[$key] = $object;
				$pipeline = $this->getPipeline( $key );
				$object->setPipeline( $pipeline );
			}
		}

		return array_values( $this->changeObservers );
	}

	/**
	 * @return array|null
	 */
	public function getContextProviders() {
		if ( $this->contextProviders === null ) {
			$this->contextProviders = [];
			foreach ( $this->contextProvidersRegistry as $key => $spec ) {
				if ( !$this->isDisabled( $key ) ) {
					continue;
				}
				$object = $this->createObject( $spec, IContextProvider::class );
				$this->contextProviders[$key] = $object;
			}
		}

		return $this->contextProviders;
	}

	/**
	 * @param string $key
	 * @return IContextProvider|null
	 */
	public function getContextProvider( string $key ): ?IContextProvider {
		return $this->getContextProviders()[$key] ?? null;
	}

	/**
	 * @param string $type
	 * @return IPageDataProvider
	 */
	public function getDataProvider( string $type ): IPageDataProvider {
		if ( isset( $this->dataProviders[$type] ) ) {
			return $this->dataProviders[$type];
		}
		if ( !isset( $this->dataProviderRegistry[$type] ) ) {
			throw new RuntimeException( "WikiRAG: Data provider type '$type' not found in registry" );
		}
		return $this->createObject( $this->dataProviderRegistry[$type], IPageDataProvider::class );
	}

	/**
	 * @param array $spec
	 * @param string $expectedClass
	 * @return \stdClass
	 */
	private function createObject( array $spec, string $expectedClass ): object {
		$instance = $this->objectFactory->createObject( $spec );
		if ( $instance === null ) {
			throw new RuntimeException(
				"WikiRAG: Failed to create object of type " . $spec['class']
			);
		}
		if ( !$instance instanceof $expectedClass ) {
			throw new RuntimeException(
				"WikiRAG: Object of type " . get_class( $instance ) . " does not implement $expectedClass"
			);
		}
		return $instance;
	}

	/**
	 * @param mixed $data
	 * @return Config
	 */
	private function parseTargetConfig( mixed $data ): Config {
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new RuntimeException( 'WikiRAG: Invalid JSON in target configuration' );
			}
			return new HashConfig( $data );
		}
		if ( is_array( $data ) ) {
			return new HashConfig( $data );
		}

		throw new RuntimeException( 'WikiRAG: Target configuration must be an array or JSON string' );
	}

	/**
	 * @param mixed $key
	 * @return bool
	 */
	private function isDisabled( mixed $key ): bool {
		if ( is_string( $key ) && str_starts_with( $key, "@" ) ) {
			return false;
		}
		return true;
	}
}
