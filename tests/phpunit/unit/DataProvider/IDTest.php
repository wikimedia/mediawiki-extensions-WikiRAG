<?php

namespace MediaWiki\Extension\WikiRAG\Tests\Unit\DataProvider;

use MediaWiki\Extension\WikiRAG\DataProvider\ID;
use MediaWiki\Extension\WikiRAG\ResourceIdGenerator;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikiRAG\DataProvider\ID
 */
class IDTest extends TestCase {

	/**
	 * @return void
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\ID::provideForRevision
	 * @covers       \MediaWiki\Extension\WikiRAG\DataProvider\ID::canProvideForPage
	 */
	public function testCanProvide() {
		$provider = new ID();

		$pageMock = $this->createMock( PageIdentity::class );
		$pageMock->method( 'getNamespace' )->willReturn( NS_HELP );
		$pageMock->method( 'getDBkey' )->willReturn( 'TestPage' );

		$resourceGenerator = new ResourceIdGenerator();
		$expectedId = $resourceGenerator->getIdBase( $pageMock );

		$this->assertTrue( $provider->canProvideForPage( $pageMock ) );
		$revisionMock = $this->createMock( RevisionRecord::class );
		$revisionMock->method( 'getPage' )->willReturn( $pageMock );
		$this->assertEquals( $expectedId, $provider->provideForRevision( $revisionMock ) );
	}
}
