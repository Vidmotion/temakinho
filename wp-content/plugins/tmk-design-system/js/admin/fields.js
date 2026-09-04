/**
 * TMK editorial fields — the three interactions ACF free cannot provide.
 *
 * 1. media   — single attachment picker (logotipo)
 * 2. gallery — multi attachment picker, drag to reorder, × to remove; the order IS the value
 * 3. rows    — repeatable label/value pairs (orari), drag to reorder
 *
 * All three keep their value in one hidden input, so the metabox posts through the normal
 * WordPress form and needs no REST call, no page-specific endpoint and no build step.
 * jQuery and jquery-ui-sortable both ship with WordPress; nothing else is loaded.
 */
( function ( $ ) {
	'use strict';

	/* ---------------------------------------------------------------- 1. single media picker */
	$( document ).on( 'click', '[data-tmk-media-pick]', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '[data-tmk-media]' );
		var frame = wp.media( {
			title: 'Scegli un’immagine',
			button: { text: 'Usa questa immagine' },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
			$box.find( '[data-tmk-media-value]' ).val( a.id );
			$box.find( '[data-tmk-media-preview]' ).html( '<img src="' + url + '" alt="" />' );
			$box.find( '[data-tmk-media-clear]' ).prop( 'hidden', false );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '[data-tmk-media-clear]', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '[data-tmk-media]' );
		$box.find( '[data-tmk-media-value]' ).val( '' );
		$box.find( '[data-tmk-media-preview]' ).empty();
		$( this ).prop( 'hidden', true );
	} );

	/* --------------------------------------------------------------------------- 2. gallery */
	function syncGallery( $box ) {
		var ids = $box.find( '[data-tmk-gallery-list] > li' ).map( function () {
			return $( this ).data( 'id' );
		} ).get();
		$box.find( '[data-tmk-gallery-value]' ).val( ids.join( ',' ) );
		$box.find( '[data-tmk-gallery-count]' ).text( ids.length + ( 1 === ids.length ? ' foto' : ' foto' ) );
	}

	$( document ).on( 'click', '[data-tmk-gallery-add]', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '[data-tmk-gallery]' );
		var frame = wp.media( {
			title: 'Aggiungi foto alla galleria',
			button: { text: 'Aggiungi alla galleria' },
			library: { type: 'image' },
			multiple: 'add'
		} );
		frame.on( 'select', function () {
			var $list = $box.find( '[data-tmk-gallery-list]' );
			frame.state().get( 'selection' ).map( function ( att ) {
				var a = att.toJSON();
				// Never add the same attachment twice: the value is an ordered set.
				if ( $list.find( '[data-id="' + a.id + '"]' ).length ) { return; }
				var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
				$list.append(
					'<li data-id="' + a.id + '"><img src="' + url + '" alt="" />' +
					'<button type="button" class="tmk-af__x" data-tmk-gallery-remove aria-label="Togli questa foto">&times;</button></li>'
				);
			} );
			syncGallery( $box );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '[data-tmk-gallery-remove]', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '[data-tmk-gallery]' );
		$( this ).closest( 'li' ).remove();
		syncGallery( $box );
	} );

	/* ------------------------------------------------------------------------------ 3. rows */
	$( document ).on( 'click', '[data-tmk-rows-add]', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '[data-tmk-rows]' );
		var name = $box.data( 'name' );
		$box.find( '[data-tmk-rows-list]' ).append(
			'<li class="tmk-af__rowitem"><span class="tmk-af__grip" aria-hidden="true">⋮⋮</span>' +
			'<input type="text" name="' + name + '[label][]" value="" />' +
			'<input type="text" name="' + name + '[value][]" value="" />' +
			'<button type="button" class="tmk-af__x" data-tmk-rows-remove aria-label="Togli questa riga">&times;</button></li>'
		);
		$box.find( '[data-tmk-rows-list] > li:last-child input:first' ).trigger( 'focus' );
	} );

	$( document ).on( 'click', '[data-tmk-rows-remove]', function ( e ) {
		e.preventDefault();
		$( this ).closest( 'li' ).remove();
	} );

	/* ------------------------------------------------------------ sortables, once per screen */
	$( function () {
		$( '[data-tmk-gallery-list]' ).sortable( {
			items: '> li',
			cursor: 'move',
			tolerance: 'pointer',
			update: function () { syncGallery( $( this ).closest( '[data-tmk-gallery]' ) ); }
		} );
		$( '[data-tmk-rows-list]' ).sortable( {
			items: '> li',
			handle: '.tmk-af__grip',
			axis: 'y',
			cursor: 'move',
			tolerance: 'pointer'
		} );
	} );

}( jQuery ) );
