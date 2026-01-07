window.ext = window.ext || {};

ext.wikirag = ext.wikirag || {};

ext.wikirag.PageInfo = function ( name, config ) {
	ext.wikirag.PageInfo.super.call( this, name, config );
};

OO.inheritClass( ext.wikirag.PageInfo, StandardDialogs.ui.BasePage );

ext.wikirag.PageInfo.prototype.setupOutlineItem = function () {
	ext.wikirag.PageInfo.super.prototype.setupOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem.setLabel( mw.message( 'wikirag-pageinfo-title' ).plain() );
	}
};

ext.wikirag.PageInfo.prototype.setup = function () {
	this.panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.$element.append( this.panel.$element );
};

ext.wikirag.PageInfo.prototype.onInfoPanelSelect = async function () {
	const pageId = mw.config.get( 'wgArticleId' );
	if ( !pageId ) {
		return;
	}
	const res = await fetch( mw.util.wikiScript( 'rest' ) + `/wikirag/v1/export/history/${ pageId }` );
	try {
		const data = await res.json();
		this.panel.$element.empty();
		this.panel.$element.append(
			new OO.ui.FieldLayout( new OO.ui.LabelWidget( {
				// * wikirag-pageinfo-export-status-exported
				// * wikirag-pageinfo-export-status-disabled
				// * wikirag-pageinfo-export-status-not-exported
				label: mw.msg( 'wikirag-pageinfo-export-status-' + data.status )
			} ), {
				align: 'left',
				label: mw.msg( 'wikirag-pageinfo-export-status-label' )
			} ).$element
		);
		if ( data.history ) {
			this.panel.$element.append( this.makeTable( data.history ) );
		}
	} catch ( e ) {
		console.error( 'Error fetching page history data:', e ); // eslint-disable-line no-console
	}
};

ext.wikirag.PageInfo.prototype.makeTable = function ( history ) {
	const $table = $( '<table>' ).addClass( 'wikitable' );
	history.forEach( ( entry ) => {
		const $row = $( '<tr>' );
		$row.append( $( '<td>' ).text( entry.pipeline ) );
		$row.append( $( '<td>' ).text( entry.status ) );
		$row.append( $( '<td>' ).text( entry.formatted_timestamp ) );
		$table.append( $row );
	} );
	return $table;
};

registryPageInformation.register( 'wikirag_history', ext.wikirag.PageInfo );
