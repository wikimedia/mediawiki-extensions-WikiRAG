<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit;

use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Page\PageIdentity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikiRAG\ResourceIdGenerator
 */
class ResourceIdGeneratorTest extends TestCase {

	/**
	 * @return void
	 */
	public function testGenerate() {
		$pageMock = $this->createMock( PageIdentity::class );
		$pageMock->method( 'getNamespace' )->willReturn( NS_HELP );
		$pageMock->method( 'getDBkey' )->willReturn( 'TestPage' );

		$base = ( new ResourceIdGenerator() )->getIdBase( $pageMock );
		$hash1 = ( new ResourceIdGenerator() )->generateResourceId( $pageMock );
		$hash2 = ( new ResourceIdGenerator() )->generateResourceId( $pageMock );

		// We are testing that hash is consistent for the same input and differs from the base
		$this->assertNotEquals( $base, $hash1 );
		$this->assertEquals( $hash1, $hash2 );

		// Assert hash is a valid for a filename
		$invalidRegex = '/[\\\\\/:*?"<>|\x00]/';
		$this->assertDoesNotMatchRegularExpression( $invalidRegex, $hash1 );
	}
}
