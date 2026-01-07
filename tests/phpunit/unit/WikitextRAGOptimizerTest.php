<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 *
 * @covers \MediaWiki\Extension\WikiRAG\Util\WikitextRAGOptimizer
 */
class WikitextRAGOptimizerTest extends TestCase {

	/**
	 * @return void
	 */
	public function testOptimize() {
		$input = file_get_contents( __DIR__ . '/data/raw.wikitext' );
		$optimized = file_get_contents( __DIR__ . '/data/optimized.wikitext' );

		$magicWordFactoryMock = $this->createMock( \MediaWiki\Parser\MagicWordFactory::class );
		$magicWordFactoryMock->method( 'getVariableIDs' )->willReturn( [ 'pagename' ] );
		$optimizer = new \MediaWiki\Extension\WikiRAG\Util\WikitextRAGOptimizer( $magicWordFactoryMock );
		$output = $optimizer->process( $input );

		$this->assertEquals( $optimized, $output );
	}
}
