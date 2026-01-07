bs.util.registerNamespace( 'ext.wikirag.ui.plugin' );

ext.wikirag.ui.plugin.MWMetaDialog = function BsHideTitleUiPluginMWMetaDialog( component ) { // eslint-disable-line no-unused-vars
	ext.wikirag.ui.plugin.MWMetaDialog.super.apply( this, arguments );
};

OO.inheritClass( ext.wikirag.ui.plugin.MWMetaDialog, bs.vec.ui.plugin.MWMetaDialog );

ext.wikirag.ui.plugin.MWMetaDialog.prototype.initialize = function () {
	this.component.advancedSettingsPage.nowikirag = new OO.ui.FieldLayout(
		new OO.ui.ButtonSelectWidget()
			.addItems( [
				new OO.ui.ButtonOptionWidget( {
					data: 'default',
					label: mw.msg( 'wikirag-ve-magic-yes' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'mw:PageProp/NO_RAG',
					label: mw.msg( 'wikirag-ve-magic-no' )
				} )
			] )
			.connect( this, { select: 'onNoWikiRagChange' } ),
		{
			$overlay: this.component.$overlay,
			align: 'top',
			label: mw.msg( 'wikirag-ve-magic-label' ),
			help: mw.msg( 'wikirag-ve-magic-help' )
		}
	);

	this.component.advancedSettingsPage.advancedSettingsFieldset.$element.append( this.component.advancedSettingsPage.nowikirag.$element );
};

ext.wikirag.ui.plugin.MWMetaDialog.prototype.getSetupProcess = function ( parentProcess, data ) {
	const advancedSettingsPage = this.component.advancedSettingsPage;
	this.component.advancedSettingsPage.setup( data.fragment, data );
	const metaList = data.fragment.getSurface().metaList;

	const field = advancedSettingsPage.nowikirag.getField();
	advancedSettingsPage.metaList = metaList;
	const option = advancedSettingsPage.getMetaItem( 'nowikirag' );
	const metaData = option ? 'mw:PageProp/NO_RAG' : 'default';

	field.selectItemByData( metaData );

	return parentProcess;
};

ext.wikirag.ui.plugin.MWMetaDialog.prototype.getTeardownProcess = function ( parentProcess, data ) { // eslint-disable-line no-unused-vars
	const advancedSettingsPage = this.component.advancedSettingsPage;

	const option = advancedSettingsPage.getMetaItem( 'nowikirag' );
	const metaData = advancedSettingsPage.nowikirag.getField().findSelectedItem();

	if ( option ) {
		advancedSettingsPage.fragment.removeMeta( option );
	}
	if ( metaData.data !== 'default' ) {
		const item = { type: 'nowikirag' };
		this.component.getFragment().insertMeta( item, 0 );
	}

	return parentProcess;
};

/**
 * Handle option state change events.
 */
ext.wikirag.ui.plugin.MWMetaDialog.prototype.onNoWikiRagChange = function () {
	this.component.actions.setAbilities( {
		done: true
	} );
};
