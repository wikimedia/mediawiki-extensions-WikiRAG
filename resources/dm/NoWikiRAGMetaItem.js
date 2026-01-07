bs.util.registerNamespace( 'ext.wikirag.dm' );

/**
 * DataModel for __NO_WIKI_RAG__
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ext.wikirag.dm.NoWikiRagMetaItem = function () {
	// Parent constructor
	ext.wikirag.dm.NoWikiRagMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.wikirag.dm.NoWikiRagMetaItem, ve.dm.MetaItem );

/* Static Properties */

ext.wikirag.dm.NoWikiRagMetaItem.static.name = 'nowikirag';

ext.wikirag.dm.NoWikiRagMetaItem.static.group = 'nowikirag';

ext.wikirag.dm.NoWikiRagMetaItem.static.matchTagNames = [ 'meta' ];

ext.wikirag.dm.NoWikiRagMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/NO_RAG' ];

ext.wikirag.dm.NoWikiRagMetaItem.static.toDomElements = function ( dataElement, doc ) {
	const meta = doc.createElement( 'meta' );
	meta.setAttribute( 'property', 'mw:PageProp/NO_RAG' );
	return [ meta ];
};

/* Registration */

ve.dm.modelRegistry.register( ext.wikirag.dm.NoWikiRagMetaItem );
