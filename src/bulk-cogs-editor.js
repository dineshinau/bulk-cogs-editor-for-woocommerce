/* global DKBCE */
( function () {
	'use strict';

	const $ = ( selector ) => document.querySelector( selector );
	const state = {
		count: 0,
		hasQuery: false,
		previewId: '',
		rows: [],
		busy: false,
		operationId: DKBCE.operationId || '',
		pollTimer: null,
	};

	const notice = $( '#dkbce-alert' );
	const getButton = $( '#dkbce-get-products' );
	const previewButton = $( '#dkbce-preview' );
	const applyButton = $( '#dkbce-apply' );
	const previewContent = $( '#dkbce-preview-content' );
	const operationPanel = $( '#dkbce-operation' );
	const modal = $( '#dkbce-confirm' );

	function setNotice( message, type ) {
		notice.className =
			'notice ' + ( type === 'error' ? 'notice-error' : 'notice-info' );
		notice.querySelector( 'p' ).textContent = message;
		notice.hidden = ! message;
	}

	function request( action, values ) {
		const data = new FormData();
		data.append( 'action', 'dkbce_' + action );
		data.append( 'nonce', DKBCE.nonce );
		Object.keys( values || {} ).forEach( ( key ) => {
			const value = values[ key ];
			if ( key === 'filters' ) {
				Object.keys( value ).forEach( ( filter ) =>
					data.append( 'filters[' + filter + ']', value[ filter ] )
				);
			} else if ( Array.isArray( value ) ) {
				value.forEach( ( item ) => data.append( key + '[]', item ) );
			} else {
				data.append( key, value );
			}
		} );
		return fetch( DKBCE.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( ! response.success ) {
					throw new Error(
						response.data && response.data.message
							? response.data.message
							: 'The request failed.'
					);
				}
				return response.data;
			} );
	}

	function filters() {
		return {
			search: $( '#dkbce-search' ).value,
			category: $( '#dkbce-category' ).value,
			type: $( '#dkbce-type' ).value,
			stock_status: $( '#dkbce-stock' ).value,
			brand: $( '#dkbce-brand' ) ? $( '#dkbce-brand' ).value : '0',
			price_min: $( '#dkbce-price-min' ).value,
			price_max: $( '#dkbce-price-max' ).value,
			cogs_min: $( '#dkbce-cogs-min' ).value,
			cogs_max: $( '#dkbce-cogs-max' ).value,
		};
	}

	function selectedAction() {
		const checked = document.querySelector(
			'input[name="dkbce-action"]:checked'
		);
		return checked ? checked.value : '';
	}

	function invalidatePreview( filtersChanged ) {
		state.previewId = '';
		state.rows = [];
		if ( filtersChanged ) {
			state.hasQuery = false;
			state.count = 0;
			$( '#dkbce-product-count' ).textContent = DKBCE.i18n.filtersChanged;
		}
		previewButton.disabled =
			state.busy || ! state.hasQuery || 0 === state.count;
		applyButton.disabled = true;
		previewContent.hidden = true;
		previewContent.replaceChildren();
		if ( state.hasQuery && state.count > 0 ) {
			$( '#dkbce-product-count' ).textContent =
				DKBCE.i18n.previewRequired;
		}
	}

	function setBusy( busy, button, label ) {
		state.busy = busy;
		button.disabled = busy;
		button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		if ( busy ) {
			button.dataset.originalText = button.textContent;
			button.textContent = label;
		} else if ( button.dataset.originalText ) {
			button.textContent = button.dataset.originalText;
			delete button.dataset.originalText;
		}
	}

	function numericActionValue() {
		const action = selectedAction();
		if ( 'clear' === action ) {
			return '';
		}
		return $( '#dkbce-value' ).value;
	}

	function actionLabel() {
		const labels = {
			set: [ DKBCE.i18n.cogsLabel, '' ],
			increase_percent: [ DKBCE.i18n.percentageLabel, '%' ],
			decrease_percent: [ DKBCE.i18n.percentageLabel, '%' ],
			increase_fixed: [ DKBCE.i18n.amountLabel, DKBCE.currencySymbol ],
			decrease_fixed: [ DKBCE.i18n.amountLabel, DKBCE.currencySymbol ],
			clear: [ '', '' ],
		};
		const action = selectedAction();
		$( '#dkbce-value-label' ).textContent = labels[ action ][ 0 ];
		$( '#dkbce-value-suffix' ).textContent = labels[ action ][ 1 ];
		$( '#dkbce-value-wrap' ).hidden = 'clear' === action;
	}

	function money( value ) {
		if ( null === value || undefined === value || '' === value ) {
			return '—';
		}
		const amount = Number( value );
		if ( ! Number.isFinite( amount ) ) {
			return '—';
		}
		return (
			DKBCE.currencySymbol +
			new Intl.NumberFormat( undefined, {
				minimumFractionDigits: DKBCE.decimals,
				maximumFractionDigits: DKBCE.decimals,
			} ).format( amount )
		);
	}

	function appendCell( row, value, className ) {
		const cell = document.createElement( 'td' );
		if ( className ) {
			cell.className = className;
		}
		cell.textContent = value;
		row.appendChild( cell );
		return cell;
	}

	function appendProductLinkCell( row, value, url ) {
		const cell = document.createElement( 'td' );
		if ( url ) {
			const link = document.createElement( 'a' );
			link.href = url;
			link.textContent = value;
			cell.appendChild( link );
		} else {
			cell.textContent = value;
		}
		row.appendChild( cell );
	}

	function appendProductCell( row, item ) {
		const cell = document.createElement( 'td' );
		const product = document.createElement( 'div' );
		product.className = 'dkbce-product-cell';
		if ( item.image_url ) {
			const image = document.createElement( 'img' );
			image.src = item.image_url;
			image.alt = '';
			image.setAttribute( 'aria-hidden', 'true' );
			image.width = 40;
			image.height = 40;
			image.loading = 'lazy';
			product.appendChild( image );
		}
		const name = item.name || DKBCE.i18n.noName;
		if ( item.edit_url ) {
			const link = document.createElement( 'a' );
			link.href = item.edit_url;
			link.textContent = name;
			product.appendChild( link );
		} else {
			product.appendChild( document.createTextNode( name ) );
		}
		cell.appendChild( product );
		row.appendChild( cell );
	}

	function appendChangeCell( row, item ) {
		const cell = document.createElement( 'td' );
		if ( 'skipped' === item.status || 'failed' === item.status ) {
			cell.textContent = item.reason || DKBCE.i18n.skipped;
			row.appendChild( cell );
			return;
		}
		if ( ! item.change_direction ) {
			cell.textContent = '—';
			row.appendChild( cell );
			return;
		}

		const badge = document.createElement( 'span' );
		badge.className =
			'dkbce-change-badge ' +
			( 'up' === item.change_direction
				? 'is-increase'
				: 'is-decrease' );
		const arrow = document.createElement( 'span' );
		arrow.className = 'dkbce-change-arrow';
		arrow.setAttribute( 'aria-hidden', 'true' );
		arrow.textContent = 'up' === item.change_direction ? '↑' : '↓';
		badge.appendChild( arrow );

		if ( null !== item.change ) {
			const amount = document.createElement( 'span' );
			amount.textContent =
				item.change < 0
					? '-' + money( Math.abs( item.change ) )
					: DKBCE.i18n.deltaPositive.replace(
							'%s',
							money( item.change )
						);
			badge.appendChild( amount );

			if ( null !== item.change_percent ) {
				const percentage = document.createElement( 'span' );
				percentage.textContent = DKBCE.i18n.deltaPercent.replace(
					'%s',
					new Intl.NumberFormat( undefined, {
						maximumFractionDigits: 2,
					} ).format( Math.abs( item.change_percent ) )
				);
				badge.appendChild( percentage );
			}
		} else {
			badge.setAttribute( 'aria-label', DKBCE.i18n.clearedChange );
		}

		cell.appendChild( badge );
		row.appendChild( cell );
	}

	function renderPreview( data ) {
		state.previewId = data.preview_id;
		state.count = data.count;
		state.rows = data.rows;
		previewContent.replaceChildren();
		const summary = document.createElement( 'p' );
		summary.className = 'dkbce-preview-summary';
		summary.textContent =
			data.count +
			' ' +
			( data.count === 1
				? DKBCE.i18n.productOne
				: DKBCE.i18n.productMany );
		previewContent.appendChild( summary );

		if ( ! data.rows.length ) {
			const empty = document.createElement( 'p' );
			empty.textContent = DKBCE.i18n.noProducts;
			previewContent.appendChild( empty );
			previewContent.hidden = false;
			return;
		}

		const wrapper = document.createElement( 'div' );
		wrapper.className = 'dkbce-table-wrap';
		const table = document.createElement( 'table' );
		table.className = 'widefat striped dkbce-preview-table';
		const thead = document.createElement( 'thead' );
		const header = document.createElement( 'tr' );
		DKBCE.i18n.columns.forEach( ( label ) => {
			const cell = document.createElement( 'th' );
			cell.scope = 'col';
			cell.textContent = label;
			header.appendChild( cell );
		} );
		thead.appendChild( header );
		table.appendChild( thead );
		const tbody = document.createElement( 'tbody' );
		data.rows.forEach( ( item ) => {
			const row = document.createElement( 'tr' );
			const selectCell = document.createElement( 'td' );
			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.className = 'dkbce-product-select';
			checkbox.value = String( item.id );
			checkbox.checked = true;
			checkbox.setAttribute(
				'aria-label',
				DKBCE.i18n.ariaSelect.replace( '%d', String( item.id ) )
			);
			selectCell.appendChild( checkbox );
			row.appendChild( selectCell );
			appendProductLinkCell( row, String( item.id ), item.edit_url );
			appendProductCell( row, item );
			appendCell( row, item.sku || DKBCE.i18n.noSku );
			appendCell( row, item.type_label || item.type || '—' );
			appendCell(
				row,
				null === item.current
					? DKBCE.i18n.emptyValue
					: money( item.current )
			);
			let newValue = money( item.new );
			if ( null === item.new ) {
				newValue = DKBCE.i18n.emptyValue;
			}
			if ( 'skipped' === item.status || 'failed' === item.status ) {
				newValue = '—';
			}
			appendCell( row, newValue );
			appendChangeCell( row, item );
			tbody.appendChild( row );
		} );
		table.appendChild( tbody );
		wrapper.appendChild( table );
		previewContent.appendChild( wrapper );
		const note = document.createElement( 'p' );
		note.className = 'description';
		note.textContent =
			data.count > data.rows.length
				? DKBCE.i18n.showing
						.replace( '%1$d', String( data.rows.length ) )
						.replace( '%2$d', String( data.count ) )
				: DKBCE.i18n.allShown;
		previewContent.appendChild( note );
		previewContent.hidden = false;
		applyButton.disabled = false;
	}

	function getProducts() {
		if ( state.busy ) {
			return;
		}
		const requestedFilters = filters();
		setNotice( '', 'info' );
		invalidatePreview();
		setBusy( true, getButton, DKBCE.i18n.loading );
		request( 'get_products', { filters: requestedFilters } )
			.then( ( data ) => {
				if (
					JSON.stringify( requestedFilters ) !==
					JSON.stringify( filters() )
				) {
					return;
				}
				state.count = data.count;
				state.hasQuery = true;
				$( '#dkbce-product-count' ).textContent = data.count
					? DKBCE.i18n.matchCount.replace(
							'%d',
							String( data.count )
						)
					: DKBCE.i18n.noProducts;
				previewButton.disabled = 0 === data.count;
				if ( 0 === data.count ) {
					setNotice( DKBCE.i18n.noProducts, 'info' );
				}
			} )
			.catch( ( error ) => setNotice( error.message, 'error' ) )
			.finally( () => setBusy( false, getButton ) );
	}

	function preview() {
		if ( state.busy || 0 === state.count ) {
			return;
		}
		const action = selectedAction();
		const value = numericActionValue();
		if (
			'clear' !== action &&
			( '' === value ||
				! Number.isFinite( Number( value ) ) ||
				Number( value ) < 0 )
		) {
			setNotice( DKBCE.i18n.badValue, 'error' );
			$( '#dkbce-value' ).focus();
			return;
		}
		setNotice( '', 'info' );
		applyButton.disabled = true;
		setBusy( true, previewButton, DKBCE.i18n.previewLoading );
		const requestedFilters = filters();
		request( 'preview', {
			filters: requestedFilters,
			action_type: action,
			action_value: value,
		} )
			.then( ( data ) => {
				if (
					JSON.stringify( requestedFilters ) !==
						JSON.stringify( filters() ) ||
					action !== selectedAction() ||
					value !== numericActionValue()
				) {
					return;
				}
				renderPreview( data );
			} )
			.catch( ( error ) => setNotice( error.message, 'error' ) )
			.finally( () => {
				setBusy( false, previewButton );
				previewButton.disabled = ! state.hasQuery || 0 === state.count;
			} );
	}

	function openConfirm() {
		if ( ! state.previewId || ! state.count ) {
			return;
		}
		const selectedOnly = $( '#dkbce-selected-only' ).checked;
		const selected = Array.from(
			document.querySelectorAll( '.dkbce-product-select:checked' )
		).map( ( checkbox ) => checkbox.value );
		if ( selectedOnly && ! selected.length ) {
			setNotice( DKBCE.i18n.noSelection, 'error' );
			return;
		}
		const count = selectedOnly ? selected.length : state.count;
		$( '#dkbce-confirm-text' ).textContent = DKBCE.i18n.confirmText.replace(
			'%d',
			String( count )
		);
		modal.hidden = false;
		$( '#dkbce-confirm-cancel' ).focus();
	}

	function applyChanges() {
		if ( ! state.previewId || state.busy ) {
			return;
		}
		const selectedOnly = $( '#dkbce-selected-only' ).checked;
		const selectedIds = Array.from(
			document.querySelectorAll( '.dkbce-product-select:checked' )
		).map( ( checkbox ) => checkbox.value );
		modal.hidden = true;
		applyButton.disabled = true;
		setNotice( '', 'info' );
		setBusy( true, applyButton, DKBCE.i18n.starting );
		request( 'apply', {
			preview_id: state.previewId,
			selected_only: selectedOnly ? '1' : '',
			selected_ids: selectedOnly ? selectedIds : [],
		} )
			.then( ( data ) => {
				state.operationId = data.operation_id;
				operationPanel.hidden = false;
				renderOperation( data.state );
				pollProgress();
			} )
			.catch( ( error ) => {
				applyButton.disabled = false;
				setNotice( error.message, 'error' );
			} )
			.finally( () => {
				setBusy( false, applyButton );
				if ( state.operationId ) {
					applyButton.disabled = true;
				}
			} );
	}

	function renderOperation( operation ) {
		operationPanel.replaceChildren();
		const heading = document.createElement( 'h3' );
		if ( 'snapshot' === operation.stage ) {
			heading.textContent = DKBCE.i18n.snapshotting;
		} else if (
			'processing' === operation.status ||
			'pending' === operation.status
		) {
			heading.textContent = DKBCE.i18n.processing;
		} else {
			heading.textContent = DKBCE.i18n.operationStatus.replace(
				'%s',
				operation.status.replace( /_/g, ' ' )
			);
		}
		operationPanel.appendChild( heading );
		const progress = document.createElement( 'progress' );
		progress.max = Math.max( 1, operation.total );
		progress.value = Math.min( operation.processed, progress.max );
		progress.setAttribute( 'aria-label', DKBCE.i18n.progressLabel );
		operationPanel.appendChild( progress );
		const text = document.createElement( 'p' );
		let percent = 100;
		if ( 'snapshot' === operation.stage ) {
			percent = 0;
		} else if ( operation.total ) {
			percent = Math.floor(
				( operation.processed / operation.total ) * 100
			);
		}
		text.textContent = DKBCE.i18n.processed
			.replace( '%1$d', operation.processed )
			.replace( '%2$d', operation.total )
			.replace( '%3$d', percent )
			.replace( '%4$d', operation.succeeded )
			.replace( '%5$d', operation.skipped )
			.replace( '%6$d', operation.failed );
		operationPanel.appendChild( text );
		if ( operation.error_summary ) {
			const errorSummary = document.createElement( 'p' );
			errorSummary.textContent = operation.error_summary;
			operationPanel.appendChild( errorSummary );
		}
		if ( operation.cancelled_note ) {
			const cancelled = document.createElement( 'p' );
			cancelled.textContent = operation.cancelled_note;
			operationPanel.appendChild( cancelled );
		}
		if ( operation.errors && operation.errors.length ) {
			const list = document.createElement( 'ul' );
			operation.errors.forEach( ( error ) => {
				const item = document.createElement( 'li' );
				item.textContent = DKBCE.i18n.productError
					.replace( '%1$d', error.product_id )
					.replace( '%2$s', error.message );
				list.appendChild( item );
			} );
			operationPanel.appendChild( list );
		}
		if (
			! [
				'completed',
				'completed_with_errors',
				'failed',
				'cancelled',
			].includes( operation.status )
		) {
			const cancelButton = document.createElement( 'button' );
			cancelButton.type = 'button';
			cancelButton.className = 'button';
			cancelButton.textContent = operation.cancel_requested
				? DKBCE.i18n.cancelling
				: DKBCE.i18n.cancelOperation;
			cancelButton.disabled = operation.cancel_requested;
			cancelButton.addEventListener( 'click', cancelOperation );
			operationPanel.appendChild( cancelButton );
		} else {
			const completionMessage = {
				completed: DKBCE.i18n.completedStatus,
				completed_with_errors: DKBCE.i18n.withErrorsStatus,
				failed: DKBCE.i18n.failedStatus,
				cancelled: DKBCE.i18n.cancelledStatus,
			};
			setNotice(
				completionMessage[ operation.status ] ||
					DKBCE.i18n.operationStatus.replace(
						'%s',
						operation.status.replace( /_/g, ' ' )
					),
				operation.failed || 'failed' === operation.status
					? 'error'
					: 'info'
			);
		}
	}

	function pollProgress() {
		if ( ! state.operationId ) {
			return;
		}
		if ( state.pollTimer ) {
			window.clearTimeout( state.pollTimer );
		}
		request( 'progress', { operation_id: state.operationId } )
			.then( ( data ) => {
				renderOperation( data.state );
				if (
					! [
						'completed',
						'completed_with_errors',
						'failed',
						'cancelled',
					].includes( data.state.status )
				) {
					state.pollTimer = window.setTimeout( pollProgress, 2500 );
				}
			} )
			.catch( ( error ) => setNotice( error.message, 'error' ) );
	}

	function cancelOperation() {
		if ( ! state.operationId ) {
			return;
		}
		request( 'cancel', { operation_id: state.operationId } )
			.then( ( data ) => {
				renderOperation( data.state );
				if ( state.pollTimer ) {
					window.clearTimeout( state.pollTimer );
				}
				state.pollTimer = window.setTimeout( pollProgress, 2500 );
			} )
			.catch( ( error ) => setNotice( error.message, 'error' ) );
	}

	getButton.addEventListener( 'click', getProducts );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', openConfirm );
	$( '#dkbce-confirm-cancel' ).addEventListener( 'click', () => {
		modal.hidden = true;
		applyButton.focus();
	} );
	$( '#dkbce-confirm-apply' ).addEventListener( 'click', applyChanges );
	modal.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key ) {
			modal.hidden = true;
			applyButton.focus();
		}
		if ( 'Tab' === event.key ) {
			const focusable = modal.querySelectorAll(
				'button:not([disabled])'
			);
			if (
				event.shiftKey &&
				modal.ownerDocument.activeElement === focusable[ 0 ]
			) {
				event.preventDefault();
				focusable[ focusable.length - 1 ].focus();
			} else if (
				! event.shiftKey &&
				modal.ownerDocument.activeElement ===
					focusable[ focusable.length - 1 ]
			) {
				event.preventDefault();
				focusable[ 0 ].focus();
			}
		}
	} );
	$( '#dkbce-reset' ).addEventListener( 'click', () => {
		document
			.querySelectorAll(
				'#dkbce-search, #dkbce-price-min, #dkbce-price-max, #dkbce-cogs-min, #dkbce-cogs-max'
			)
			.forEach( ( input ) => {
				input.value = '';
			} );
		$( '#dkbce-category' ).value = '0';
		$( '#dkbce-type' ).value = 'any';
		$( '#dkbce-stock' ).value = 'any';
		if ( $( '#dkbce-brand' ) ) {
			$( '#dkbce-brand' ).value = '0';
		}
		$( '#dkbce-product-count' ).textContent = '';
		state.count = 0;
		invalidatePreview( true );
	} );
	document
		.querySelectorAll( 'input[name="dkbce-action"]' )
		.forEach( ( input ) =>
			input.addEventListener( 'change', () => {
				document
					.querySelectorAll( '.dkbce-action-card' )
					.forEach( ( card ) =>
						card.classList.toggle(
							'is-selected',
							Boolean( card.querySelector( 'input:checked' ) )
						)
					);
				actionLabel();
				invalidatePreview( false );
			} )
		);
	document
		.querySelectorAll(
			'#dkbce-search, #dkbce-category, #dkbce-type, #dkbce-stock, #dkbce-brand, #dkbce-price-min, #dkbce-price-max, #dkbce-cogs-min, #dkbce-cogs-max'
		)
		.forEach( ( input ) => {
			if ( input && input.addEventListener ) {
				input.addEventListener( 'input', () =>
					invalidatePreview( true )
				);
			}
			if ( input && input.addEventListener ) {
				input.addEventListener( 'change', () =>
					invalidatePreview( true )
				);
			}
		} );
	$( '#dkbce-value' ).addEventListener( 'input', () =>
		invalidatePreview( false )
	);
	actionLabel();
	document
		.querySelectorAll( '.dkbce-action-card' )
		.forEach( ( card ) =>
			card.classList.toggle(
				'is-selected',
				Boolean( card.querySelector( 'input:checked' ) )
			)
		);
	if ( state.operationId ) {
		operationPanel.hidden = false;
		pollProgress();
	}
} )();
