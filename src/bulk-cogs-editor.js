/* global DKBCE */
( function () {
	'use strict';

	const $ = ( selector ) => document.querySelector( selector );
	const state = {
		mode: 'bulk',
		editorMode: 'bulk',
		importPreparing: false,
		importId: DKBCE.importId || '',
		importCancel: false,
		count: 0,
		hasQuery: false,
		previewId: '',
		previewNeedsRefresh: false,
		rows: [],
		pageData: null,
		page: 1,
		loadingPage: false,
		pageRequestId: 0,
		excludedIds: new Set(),
		selectedIds: new Set(),
		explicitSelection: false,
		selectionPreviewId: '',
		selectedCount: 0,
		selectionFiltersChanged: false,
		selectionRange: null,
		busy: false,
		previewTimer: null,
		operationId: DKBCE.operationId || '',
		operationStatus: '',
		startingOperation: false,
		operationStartedAt: null,
		operationTimer: null,
		pollTimer: null,
		progressRequestId: 0,
		progressError: false,
		reviewedIds: new Set(),
	};

	const notice = $( '#dkbce-alert' );
	const noticeParent = notice.parentNode;
	const noticeNextSibling = notice.nextSibling;
	const getButton = $( '#dkbce-get-products' );
	const productsSpinner = $( '#dkbce-products-spinner' );
	const previewSpinner = $( '#dkbce-preview-spinner' );
	const pageSizeSelect = $( '#dkbce-page-size' );
	const pageSizeLabel = $( 'label[for="dkbce-page-size"]' );
	const applyButton = $( '#dkbce-apply' );
	const applySpinner = $( '#dkbce-apply-spinner' );
	const previewContent = $( '#dkbce-preview-content' );
	const previewState = $( '#dkbce-preview-state' );
	const refreshPreviewButton = $( '#dkbce-refresh-preview' );
	const operationPanel = $( '#dkbce-operation' );
	const modal = $( '#dkbce-confirm' );
	const rangeControls = document.createElement( 'div' );
	rangeControls.className = 'dkbce-range-selection';
	const rangeLabel = document.createElement( 'label' );
	rangeLabel.htmlFor = 'dkbce-range-direction';
	rangeLabel.textContent = DKBCE.i18n.selectRange;
	const rangeDirection = document.createElement( 'select' );
	rangeDirection.id = 'dkbce-range-direction';
	[ [ 'all', DKBCE.i18n.allResults ], [ 'first', DKBCE.i18n.selectFirst ], [ 'last', DKBCE.i18n.selectLast ] ].forEach( ( [ value, label ] ) => {
		rangeDirection.add( new Option( label, value ) );
	} );
	const rangeCount = document.createElement( 'input' );
	rangeCount.type = 'number';
	rangeCount.min = '1';
	rangeCount.step = '1';
	rangeCount.required = true;
	rangeCount.value = '';
	rangeCount.setAttribute( 'aria-label', DKBCE.i18n.selectCount );
	const rangeButton = document.createElement( 'button' );
	rangeButton.type = 'button';
	rangeButton.className = 'button';
	rangeButton.textContent = DKBCE.i18n.selectRows;
	rangeControls.append( rangeLabel, rangeDirection, rangeCount, rangeButton );
	$( '#dkbce-range-menu' ).append( rangeControls );
	rangeDirection.addEventListener( 'change', updateOperationControls );
	rangeButton.addEventListener( 'click', () => {
		if ( isOperationRunning() || state.busy || state.loadingPage || ! state.previewId ) {
			return;
		}
		if ( rangeDirection.value === 'all' ) {
			state.selectionRange = null;
			state.explicitSelection = false;
			state.selectedIds.clear();
			state.excludedIds.clear();
			state.selectedCount = state.count;
			renderPreview( state.pageData );
			setNotice( DKBCE.i18n.rangeSelected.replace( '%d', state.count ), 'info' );
			return;
		}
		rangeCount.max = String( state.count );
		if ( ! rangeCount.reportValidity() ) {
			return;
		}
		const previewId = state.previewId;
		setBusy( true, rangeButton, DKBCE.i18n.loading );
		request( 'select_range', { preview_id: previewId, direction: rangeDirection.value, count: rangeCount.value } )
			.then( ( data ) => {
				if ( state.previewId !== previewId ) {
					return;
				}
				state.explicitSelection = false;
				state.selectedIds.clear();
				state.selectionRange = data.range;
				$( '#dkbce-range-menu' ).open = false;
				state.selectedCount = data.range.count;
				state.excludedIds.clear();
				$( '#dkbce-selected-only' ).checked = true;
				renderPreview( state.pageData );
				setNotice( DKBCE.i18n.rangeSelected.replace( '%d', data.range.count ), 'info' );
			} )
			.catch( ( error ) => setNotice( error.message, 'error' ) )
			.finally( () => setBusy( false, rangeButton ) );
	} );
	const actionSection = $( '.dkbce-action-section' );
	$( '.dkbce-action-fields' ).appendChild( $( '#dkbce-value-wrap' ) );
	$( '.dkbce-filters-section' ).appendChild( $( '.dkbce-filter-actions' ) );

	const historyText = DKBCE.history;
	const historyState = { page: 1, size: 10, operationId: '', productPage: 1, productSize: 10, review: false, token: '', requestId: 0, detailRequestId: 0, loading: false, pollTimer: null };
	const historyRoot = $( '#dkbce-history' );
	const settingsRoot = $( '#dkbce-settings' );
	const operationAnchor = document.createComment( 'operation panel' );
	operationPanel.before( operationAnchor );

	function historyElement( tag, text = '', className = '' ) {
		const element = document.createElement( tag );
		element.textContent = text;
		element.className = className;
		return element;
	}

	function historyButton( text, callback, primary = false ) {
		const button = historyElement( 'button', text, 'button' + ( primary ? ' button-primary' : '' ) );
		button.type = 'button';
		button.addEventListener( 'click', callback );
		return button;
	}

	function historySection( title, number, help = '' ) {
		const section = historyElement( 'section', '', 'dkbce-section' );
		const heading = historyElement( 'div', '', 'dkbce-section-heading' );
		const text = historyElement( 'div' );
		text.appendChild( historyElement( 'h2', title ) );
		if ( help ) { text.appendChild( historyElement( 'p', help ) ); }
		heading.append( historyElement( 'span', String( number ) ), text );
		section.appendChild( heading );
		return section;
	}

	function historyField( label, input ) {
		const wrapper = historyElement( 'label', '', 'dkbce-history-field' );
		wrapper.append( historyElement( 'span', label ), input );
		return wrapper;
	}

	function historySelect( entries, initial = '' ) {
		const select = document.createElement( 'select' );
		entries.forEach( ( [ value, text ] ) => select.add( new Option( text, value ) ) );
		select.value = initial;
		return select;
	}

	function historyTable( headers ) {
		const wrap = historyElement( 'div', '', 'dkbce-table-wrap' );
		const table = historyElement( 'table', '', 'widefat striped dkbce-preview-table dkbce-history-table' );
		const head = document.createElement( 'thead' );
		const row = document.createElement( 'tr' );
		headers.forEach( ( title ) => {
			const cell = historyElement( 'th', title );
			cell.scope = 'col';
			row.appendChild( cell );
		} );
		head.appendChild( row );
		const body = document.createElement( 'tbody' );
		table.append( head, body );
		wrap.appendChild( table );
		return { wrap, body };
	}

	function createPagination( currentPage, totalPages, onPage, jumpId ) {
		const pagination = document.createElement( 'nav' );
		pagination.className = 'dkbce-pagination';
		pagination.setAttribute( 'aria-label', DKBCE.i18n.paginationLabel );
		const addPageButton = ( label, page, disabled = false ) => {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button';
			button.textContent = label;
			button.disabled = disabled;
			button.setAttribute( 'aria-label', DKBCE.i18n.pageNumber
				.replace( '%1$d', String( page ) )
				.replace( '%2$d', String( totalPages ) ) );
			if ( String( page ) === label && page === currentPage ) {
				button.setAttribute( 'aria-current', 'page' );
			}
			button.addEventListener( 'click', () => onPage( page ) );
			pagination.appendChild( button );
		};
		addPageButton( '«', 1, currentPage === 1 );
		addPageButton( '‹', Math.max( 1, currentPage - 1 ), currentPage === 1 );
		const pages = new Set( [ 1, totalPages ] );
		const start = Math.max( 1, Math.min( currentPage - 2, totalPages - 4 ) );
		for ( let page = start; page <= Math.min( totalPages, start + 4 ); page++ ) {
			pages.add( page );
		}
		let previousPage = 0;
		Array.from( pages ).sort( ( a, b ) => a - b ).forEach( ( page ) => {
			if ( previousPage && page > previousPage + 1 ) {
				const gap = document.createElement( 'span' );
				gap.textContent = '…';
				pagination.appendChild( gap );
			}
			addPageButton( String( page ), page );
			previousPage = page;
		} );
		addPageButton( '›', Math.min( totalPages, currentPage + 1 ), currentPage === totalPages );
		addPageButton( '»', totalPages, currentPage === totalPages );
		const jump = document.createElement( 'form' );
		jump.className = 'dkbce-page-jump';
		const label = document.createElement( 'label' );
		label.htmlFor = jumpId;
		label.textContent = DKBCE.ui.goToPage;
		const input = document.createElement( 'input' );
		input.id = jumpId;
		input.type = 'number';
		input.min = '1';
		input.max = String( totalPages );
		input.step = '1';
		input.required = true;
		input.value = String( currentPage );
		const go = document.createElement( 'button' );
		go.type = 'submit';
		go.className = 'button';
		go.textContent = DKBCE.ui.go;
		jump.append( label, input, go );
		jump.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			if ( input.reportValidity() ) { onPage( Number( input.value ) ); }
		} );
		return { pagination, jump };
	}

	function historyPagination( total, page, size, onPage, onSize, jumpId = 'dkbce-history-jump-page' ) {
		const footer = historyElement( 'div', '', 'dkbce-table-footer' );
		const sizes = historySelect( [ 10, 20, 50, 100 ].map( ( value ) => [ String( value ), String( value ) ] ), String( size ) );
		sizes.addEventListener( 'change', () => onSize( Number( sizes.value ) ) );
		const pages = Math.max( 1, Math.ceil( total / size ) );
		footer.appendChild( historyField( historyText.show, sizes ) );
		if ( pages > 1 ) {
			const { pagination, jump } = createPagination( page, pages, onPage, jumpId );
			footer.append( pagination, jump );
		}
		footer.appendChild( historyElement( 'p', historyText.page.replace( '%1$d', page ).replace( '%2$d', pages ).replace( '%3$d', total ), 'description dkbce-page-summary' ) );
		return footer;
	}

	function historyAction( operation ) {
		const action = historyText.actions[ operation.action ] || operation.action;
		if ( operation.value === '' || operation.value == null ) { return action; }
		return action + ' · ' + ( operation.action.includes( 'percent' ) ? operation.value + '%' : money( operation.value ) );
	}

	function historyStatus( status ) {
		return historyElement( 'span', historyText.statuses[ status ] || status, 'dkbce-history-status is-' + status );
	}

	const historyList = historySection( historyText.history, 1, historyText.historyHelp );
	const historyFilters = historyElement( 'form', '', 'dkbce-history-filters' );
	const historyInputs = {};
	[ [ 'status', historyText.allStatuses, historyText.statuses ], [ 'action_type', historyText.allActions, historyText.actions ] ].forEach( ( [ name, label, entries ] ) => {
		const select = historySelect( [ [ '', label ], ...Object.entries( entries ) ] );
		historyInputs[ name ] = select;
		historyFilters.appendChild( historyField( label, select ) );
	} );
	historyInputs.user_id = historySelect( [ [ '', historyText.allUsers ] ] );
	historyFilters.appendChild( historyField( historyText.user, historyInputs.user_id ) );
	[ [ 'from', 'date', 'from' ], [ 'to', 'date', 'to' ], [ 'search', 'search', 'search' ] ].forEach( ( [ name, type, label ] ) => {
		const input = document.createElement( 'input' );
		input.type = type;
		if ( type === 'number' ) { input.min = '1'; input.step = '1'; }
		if ( type === 'search' ) { input.placeholder = historyText.search; }
		historyInputs[ name ] = input;
		historyFilters.appendChild( historyField( historyText[ label ], input ) );
	} );
	const historyFilterButton = historyButton( historyText.filter, () => {} , true );
	historyFilterButton.type = 'submit';
	historyFilters.appendChild( historyFilterButton );
	historyFilters.addEventListener( 'submit', ( event ) => { event.preventDefault(); historyState.page = 1; loadHistory(); } );
	const historyRows = historyElement( 'div' );
	const historyDetails = historyElement( 'div' );
	const historyProgress = historySection( historyText.progress, 5 );
	historyProgress.hidden = true;
	historyList.append( historyFilters, historyRows );
	historyRoot.append( historyList, historyDetails, historyProgress );
	function updateHistoryProgressVisibility() {
		historyProgress.hidden = operationPanel.hidden || historyDetails.childElementCount > 0;
	}
	const noteInput = document.createElement( 'input' );
	noteInput.type = 'text';
	noteInput.id = 'dkbce-operation-note';
	noteInput.maxLength = 500;
	$( '#dkbce-action-summary' ).before( historyField( historyText.noteHelp, noteInput ) );

	async function loadHistory() {
		const requestId = ++historyState.requestId;
		historyFilterButton.disabled = true;
		historyRows.setAttribute( 'aria-busy', 'true' );
		try {
			const values = Object.fromEntries( Object.entries( historyInputs ).map( ( [ name, input ] ) => [ name, input.value ] ) );
			const data = await request( 'history', { ...values, page: historyState.page, size: historyState.size } );
			if ( requestId !== historyState.requestId ) { return; }
			const selectedUser = historyInputs.user_id.value;
			historyInputs.user_id.replaceChildren( new Option( historyText.allUsers, '' ) );
			data.users.forEach( ( user ) => historyInputs.user_id.add( new Option( user.display_name || historyText.userId + ' ' + user.user_id, user.user_id ) ) );
			historyInputs.user_id.value = selectedUser;
			historyRows.replaceChildren();
			if ( ! data.indexed ) {
				historyRows.appendChild( historyElement( 'p', historyText.indexing ) );
				window.setTimeout( () => { if ( state.mode === 'history' ) { loadHistory(); } }, 500 );
			}
			const { wrap, body } = historyTable( [ historyText.id, historyText.date, historyText.action, historyText.products, historyText.status, historyText.user, historyText.note, historyText.view ] );
			data.rows.forEach( ( operation ) => {
				const row = document.createElement( 'tr' );
				appendCell( row, operation.operation_id );
				// UUIDs remain copyable without stretching the table.
				row.firstChild.classList.add( 'dkbce-history-id' );
				appendCell( row, new Date( operation.created_at * 1000 ).toLocaleString() );
				appendCell( row, historyAction( operation ) );
				appendCell( row, Number( operation.total ).toLocaleString() );
				const status = document.createElement( 'td' ); status.appendChild( historyStatus( operation.status ) ); row.appendChild( status );
				appendCell( row, operation.user ); appendCell( row, operation.note || '—' );
				const action = document.createElement( 'td' );
				action.appendChild( historyButton( historyText.view, () => { historyState.operationId = operation.operation_id; historyState.productPage = 1; historyState.review = false; loadHistoryDetails( true ); } ) );
				row.appendChild( action ); body.appendChild( row );
			} );
			historyRows.appendChild( wrap );
			if ( ! data.total ) { historyRows.appendChild( historyElement( 'p', historyText.empty ) ); }
			historyRows.appendChild( historyPagination( data.total, historyState.page, historyState.size, ( page ) => { historyState.page = page; loadHistory(); }, ( size ) => { historyState.size = size; historyState.page = 1; loadHistory(); } ) );
		} catch ( error ) { setNotice( error.message, 'error', historyList ); }
		finally { historyFilterButton.disabled = false; historyRows.removeAttribute( 'aria-busy' ); }
	}

	async function loadHistoryDetails( scrollToDetails = false ) {
		window.clearTimeout( historyState.pollTimer );
		const requestId = ++historyState.detailRequestId;
		historyState.loading = true;
		historyDetails.setAttribute( 'aria-busy', 'true' );
		historyState.token = '';
		try {
			const operationId = historyState.operationId;
			const data = await request( 'history_detail', { operation_id: operationId, page: historyState.productPage, size: historyState.productSize, review: historyState.review ? '1' : '' } );
			if ( requestId !== historyState.detailRequestId || operationId !== historyState.operationId ) { return; }
			historyState.token = data.review_token;
			historyDetails.replaceChildren();
			const details = historySection( historyText.details + ' · ' + data.state.operation_id, 2 );
			details.appendChild( historyButton( historyText.back, () => { window.clearTimeout( historyState.pollTimer ); historyState.detailRequestId++; historyState.operationId = ''; historyState.token = ''; historyState.loading = false; historyDetails.removeAttribute( 'aria-busy' ); historyDetails.replaceChildren(); updateHistoryProgressVisibility(); } ) );
			const metadata = historyElement( 'dl', '', 'dkbce-history-metadata' );
			[ [ historyText.action, historyAction( data.state ) ], [ historyText.status, historyText.statuses[ data.state.status ] ], [ historyText.user, data.state.user ], [ historyText.updated, data.state.succeeded + ' / ' + data.state.total ], [ historyText.note, data.state.note || '—' ], [ historyText.started, data.state.started_at ? new Date( data.state.started_at * 1000 ).toLocaleString() : '—' ], [ historyText.completed, data.state.completed_at ? new Date( data.state.completed_at * 1000 ).toLocaleString() : '—' ], [ historyText.duration, formatDuration( data.state.elapsed_seconds ) ], [ historyText.filters, Object.entries( data.state.filters ).filter( ( [ , value ] ) => value !== '' && value != null && ( ! Array.isArray( value ) || value.length ) ).map( ( [ key, value ] ) => key + ': ' + ( Array.isArray( value ) ? value.join( ', ' ) : typeof value === 'object' ? JSON.stringify( value ) : value ) ).join( ' · ' ) || historyText.none ] ].forEach( ( [ label, value ] ) => metadata.append( historyElement( 'dt', label ), historyElement( 'dd', value ) ) );
			details.appendChild( metadata );
			const products = historySection( historyState.review ? historyText.review : historyText.changes, historyState.review ? 4 : 3 );
			const { wrap, body } = historyTable( [ historyText.product, historyText.sku, historyState.review ? historyText.current : historyText.old, historyState.review ? historyText.restore : historyText.new, historyText.change ] );
			data.rows.forEach( ( item ) => {
				const row = document.createElement( 'tr' );
				appendProductCell( row, item ); appendCell( row, item.sku || '—' ); appendCell( row, money( item.current ) ); appendCell( row, money( item.new ) ); appendChangeCell( row, item ); body.appendChild( row );
			} );
			products.appendChild( wrap );
			if ( ! historyState.review && data.total < data.state.succeeded ) { products.appendChild( historyElement( 'p', historyText.partialHistory.replace( '%1$d', data.total ).replace( '%2$d', data.state.succeeded ), 'description' ) ); }
			if ( ! data.total ) { products.appendChild( historyElement( 'p', historyText.noChanges ) ); }
			products.appendChild( historyPagination( data.total, historyState.productPage, historyState.productSize, ( page ) => { historyState.productPage = page; loadHistoryDetails(); }, ( size ) => { historyState.productSize = size; historyState.productPage = 1; loadHistoryDetails(); }, 'dkbce-history-product-jump-page' ) );
			if ( historyState.review ) {
				products.appendChild( historyElement( 'p', data.rollback_policy === 'skip_changed' ? historyText.skipWarning : historyText.warning, 'notice notice-warning inline dkbce-history-warning' ) );
				const checked = document.createElement( 'input' ); checked.type = 'checkbox';
				const confirm = historyButton( historyText.confirm, () => startRollback( confirm ), true );
				confirm.classList.add( 'dkbce-rollback-confirm' ); confirm.disabled = true;
				checked.addEventListener( 'change', () => { confirm.disabled = ! checked.checked || ! data.can_rollback || isOperationRunning(); } );
				const confirmation = historyElement( 'label', '', 'dkbce-rollback-confirmation' );
				confirmation.append( checked, document.createTextNode( data.rollback_policy === 'skip_changed' ? historyText.skipConfirm : historyText.confirmCheck ) );
				const rollbackActions = historyElement( 'div', '', 'dkbce-rollback-actions' );
				const back = historyButton( historyText.backChanges, () => { historyState.review = false; loadHistoryDetails(); } );
				rollbackActions.append( confirmation, confirm, back );
				products.appendChild( rollbackActions );
			} else if ( data.can_rollback ) {
				products.appendChild( historyButton( historyText.review, () => { historyState.review = true; historyState.productPage = 1; loadHistoryDetails(); } ) );
			} else {
				products.appendChild( historyElement( 'p', historyText.unavailable, 'description' ) );
			}
			if ( data.rollback_id ) { products.appendChild( historyButton( historyText.rollbackExists, () => { historyState.operationId = data.rollback_id; historyState.review = false; historyState.productPage = 1; loadHistoryDetails(); } ) ); }
			historyDetails.append( details, products );
			const result = historySection( historyText.progress, 5 );
			const panel = historyElement( 'div', '', 'dkbce-operation' ); result.appendChild( panel );
			renderOperation( data.state, panel, true );
			historyDetails.appendChild( result );
			updateHistoryProgressVisibility();
			if ( scrollToDetails ) {
				historyDetails.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
			if ( ! [ 'completed', 'completed_with_errors', 'failed', 'cancelled' ].includes( data.state.status ) ) {
				result.appendChild( historyButton( DKBCE.i18n.refreshStatus, loadHistoryDetails ) );
				if ( state.mode === 'history' && historyState.operationId === operationId ) { historyState.pollTimer = window.setTimeout( loadHistoryDetails, 2500 ); }
			}
		} catch ( error ) { if ( requestId === historyState.detailRequestId ) { setNotice( error.message, 'error', historyList ); } }
		finally { if ( requestId === historyState.detailRequestId ) { historyState.loading = false; historyDetails.removeAttribute( 'aria-busy' ); } }
	}

	async function startRollback( button ) {
		if ( isOperationRunning() || ! historyState.token || historyState.loading ) { return; }
		button.disabled = true;
		state.startingOperation = true;
		try {
			const data = await request( 'rollback', { operation_id: historyState.operationId, review_token: historyState.token, confirmed: '1' } );
			historyState.token = '';
			state.operationId = data.operation_id; state.operationStartedAt = null;
			historyProgress.hidden = false; historyProgress.appendChild( operationPanel ); operationPanel.hidden = false;
			renderOperation( data.state ); pollProgress();
			invalidatePreview( false, true );
			historyDetails.replaceChildren();
			updateHistoryProgressVisibility();
			historyProgress.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			loadHistory();
		} catch ( error ) { setNotice( error.message, 'error', historyList ); button.disabled = false; }
		finally { state.startingOperation = false; updateOperationControls(); }
	}

	const settingsSection = historySection( historyText.settings, 1 );
	const settingsForm = historyElement( 'form', '', 'dkbce-settings-form' );
	const retentionChoice = historySelect( [ [ '30', historyText.days30 ], [ '90', historyText.days90 ], [ 'custom', historyText.custom ] ], '30' );
	const retentionDays = document.createElement( 'input' ); retentionDays.type = 'number'; retentionDays.min = '1'; retentionDays.max = '36500'; retentionDays.step = '1'; retentionDays.required = true; retentionDays.value = '30';
	const customRetention = historyField( historyText.days, retentionDays ); customRetention.hidden = true;
	retentionChoice.addEventListener( 'change', () => { customRetention.hidden = retentionChoice.value !== 'custom'; if ( retentionChoice.value !== 'custom' ) { retentionDays.value = retentionChoice.value; } } );
	const rollbackPolicy = historySelect( [ [ 'overwrite', historyText.overwrite ], [ 'skip_changed', historyText.skipChanged ] ], 'overwrite' );
	const saveSettings = historyButton( historyText.save, () => {}, true ); saveSettings.type = 'submit';
	settingsForm.append( historyField( historyText.retention, retentionChoice ), customRetention, historyElement( 'p', historyText.retentionHelp, 'description' ), historyField( historyText.rollbackBehavior, rollbackPolicy ), historyElement( 'p', historyText.policyHelp, 'description' ), saveSettings );
	settingsSection.appendChild( settingsForm ); settingsRoot.appendChild( settingsSection );
	async function loadSettings() {
		saveSettings.disabled = true;
		try {
			const data = await request( 'settings' );
			rollbackPolicy.value = data.rollback_policy;
			retentionDays.value = String( data.days ); retentionChoice.value = [ 30, 90 ].includes( Number( data.days ) ) ? String( data.days ) : 'custom'; customRetention.hidden = retentionChoice.value !== 'custom';
		} catch ( error ) { setNotice( error.message, 'error', settingsSection ); }
		finally { saveSettings.disabled = false; }
	}
	settingsForm.addEventListener( 'submit', async ( event ) => {
		event.preventDefault(); if ( ! settingsForm.reportValidity() ) { return; }
		saveSettings.disabled = true;
		try { await request( 'settings', { save: '1', days: retentionDays.value, rollback_policy: rollbackPolicy.value } ); setNotice( historyText.saved, 'success', settingsSection ); }
		catch ( error ) { setNotice( error.message, 'error', settingsSection ); }
		finally { saveSettings.disabled = false; }
	} );

	function csvMode() {
		return state.editorMode === 'csv';
	}

	function downloadCSV( contents, filename ) {
		const url = URL.createObjectURL( new Blob( [ '\uFEFF', ...contents ], { type: 'text/csv;charset=utf-8' } ) );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = filename;
		document.body.appendChild( link );
		link.click();
		link.remove();
		window.setTimeout( () => URL.revokeObjectURL( url ), 1000 );
	}

	function updateCSVMode() {
		const active = state.mode === 'csv';
		setNotice( '', 'info' );
		$( '#dkbce-range-menu' ).open = false;
		const importing = active && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'import';
		const helpMode = active ? ( importing ? 'import' : 'export' ) : 'bulk';
		document.querySelectorAll( '[data-dkbce-help]' ).forEach( ( section ) => {
			section.hidden = section.dataset.dkbceHelp !== helpMode;
		} );
		$( '#dkbce-csv' ).hidden = ! active;
		actionSection.hidden = active;
		$( '.dkbce-filters-section' ).hidden = importing;
		$( '.dkbce-filter-actions' ).hidden = importing;
		$( '#dkbce-csv-export-options' ).hidden = importing;
		$( '#dkbce-csv-import-options' ).hidden = ! importing;
		rangeControls.hidden = importing;
		applyButton.textContent = active && ! importing ? DKBCE.ui.createCSV : DKBCE.ui.review;
		$( '#dkbce-preview-title' ).textContent = importing ? DKBCE.ui.importTitle : active ? DKBCE.ui.exportTitle : DKBCE.ui.bulkTitle;
		$( '.dkbce-preview-empty h3' ).textContent = DKBCE.ui.emptyTitle;
		$( '.dkbce-preview-empty p' ).textContent = importing ? DKBCE.ui.importEmpty : active ? DKBCE.ui.exportEmpty : DKBCE.ui.bulkEmpty;
		$( '.dkbce-preview-controls' ).hidden = ! state.rows.length;
		$( '#dkbce-csv-title' ).textContent = importing ? DKBCE.ui.importTask : DKBCE.ui.exportTask;
		$( '#dkbce-apply-title' ).textContent = applyButton.textContent;
		const help = $( '.dkbce-apply-section .dkbce-section-heading p' );
		if ( ! help.dataset.originalText ) { help.dataset.originalText = help.textContent; }
		help.textContent = active && ! importing ? DKBCE.csv.exportHelp : help.dataset.originalText;
		const selection = $( '.dkbce-selection-option label' ).lastChild;
		if ( ! state.selectionLabel ) { state.selectionLabel = selection.textContent; }
		selection.textContent = active ? ' ' + DKBCE.csv.selection : state.selectionLabel;
		$( '.dkbce-preview-section .dkbce-section-heading > span' ).textContent = active && importing ? '2' : '3';
		$( '.dkbce-csv-section .dkbce-section-heading > span' ).textContent = '1';
		$( '.dkbce-csv-section .dkbce-step-icon' ).hidden = ! active;
		$( '.dkbce-csv-section .dkbce-section-heading > span' ).hidden = ! active;
		$( '.dkbce-workspace' ).classList.toggle( 'dkbce-csv-workflow', active );
		$( '#dkbce-csv-preview-wrap' ).hidden = active && ! importing;
		$( '#dkbce-csv-preview' ).textContent = DKBCE.csv.previewImport;
		const previewHelp = $( '#dkbce-preview-help' );
		if ( ! previewHelp.dataset.originalText ) { previewHelp.dataset.originalText = previewHelp.textContent; }
		previewHelp.textContent = active && ! importing ? DKBCE.csv.previewHelp : previewHelp.dataset.originalText;
		$( '.dkbce-filters-section .dkbce-step-number' ).textContent = active ? '2' : '1';
		const filterHelp = $( '#dkbce-filter-help' );
		if ( ! filterHelp.dataset.originalText ) { filterHelp.dataset.originalText = filterHelp.textContent; }
		filterHelp.textContent = active && ! importing ? DKBCE.csv.filterHelp : filterHelp.dataset.originalText;
		updateColumnVisibility();
		$( '.dkbce-apply-section .dkbce-section-heading > span' ).textContent = active && importing ? '3' : '4';
		document.querySelectorAll( '[data-dkbce-mode]' ).forEach( ( button ) => {
			const selected = button.dataset.dkbceMode === state.mode;
			button.setAttribute( 'aria-pressed', String( selected ) );
			button.classList.toggle( 'button-primary', selected );
		} );
		const extra = [ 'history', 'settings' ].includes( state.mode );
		$( '#dkbce-history' ).hidden = state.mode !== 'history';
		$( '#dkbce-settings' ).hidden = state.mode !== 'settings';
		if ( extra ) {
			document.querySelectorAll( '.dkbce-workspace > section' ).forEach( ( section ) => { section.hidden = true; } );
		}
		if ( state.mode === 'history' ) {
			historyProgress.appendChild( operationPanel ); updateHistoryProgressVisibility();
		} else { operationAnchor.after( operationPanel ); historyProgress.hidden = true; }
		$( '.dkbce-preview-section' ).hidden = extra;
		$( '.dkbce-apply-section' ).hidden = extra;
		if ( state.mode === 'settings' ) { historyProgress.appendChild( operationPanel ); }
		updateOperationControls();
	}

	async function previewCSV() {
		if ( state.busy || state.loadingPage || isOperationRunning() || ! csvMode() ) {
			return;
		}
		const button = $( '#dkbce-csv-preview' );
		const mode = $( 'input[name="dkbce-csv-action"]:checked' ).value;
		const file = $( '#dkbce-csv-file' ).files[ 0 ];
		if ( mode === 'import' && ( ! file || file.size > DKBCE.maxUploadSize ) ) {
			setNotice( DKBCE.csv.file, 'error', $( '#dkbce-csv' ) );
			return;
		}
		invalidatePreview( 'import' === mode, 'import' === mode );
		$( '#dkbce-csv-errors' ).hidden = true;
		setNotice( '', 'info' );
		setBusy( true, button, DKBCE.i18n.previewLoading );
		try {
			const data = await request( 'csv_preview', {
				mode,
				filters: mode === 'export' ? filters() : {},
				import_file: mode === 'import' ? file : '',
			} );
			if ( data.import_id ) {
				state.importId = data.import_id;
				state.importCancel = false;
				state.importPreparing = true;
				pollImport();
			} else {
				showImportPreview( data );
			}
		} catch ( error ) {
			setNotice( error.message, 'error', $( '#dkbce-csv' ) );
		} finally {
			setBusy( false, button );
		}
	}

	async function downloadImportReport( source, results, button ) {
		button.disabled = true;
		try {
			const job = await request( 'import_report', { import_id: source, results: results ? '1' : '0' } );
			const download = await waitForDownload( job.download_id );
			window.location.href = download.url;
		} catch ( error ) {
			setNotice( error.message, 'error' );
		} finally {
			button.disabled = false;
		}
	}

	async function waitForDownload( downloadId, onStatus = () => {} ) {
		while ( true ) {
			const status = await request( 'download_status', { download_id: downloadId } );
			onStatus( status );
			if ( 'completed' === status.status && status.url ) {
				return status;
			}
			if ( 'failed' === status.status || 'cancelled' === status.status ) {
				throw new Error( status.message || DKBCE.csv.downloadFailed );
			}
			await new Promise( ( resolve ) => window.setTimeout( resolve, 1500 ) );
		}
	}

	function showImportPreview( data ) {
		pageSizeSelect.value = String( data.page_size );
		state.hasQuery = true;
		renderPreview( data );
		const errors = $( '#dkbce-csv-errors' );
		errors.replaceChildren();
		errors.hidden = ! data.errors.length;
		if ( ! data.errors.length ) { return; }
		const explanation = document.createElement( 'p' );
		explanation.textContent = DKBCE.csv.errors + ' ' + DKBCE.csv.invalidCount.replace( '%d', data.invalid_count || data.errors.length );
		const report = document.createElement( 'button' );
		report.type = 'button';
		report.className = 'button';
		report.textContent = DKBCE.csv.report;
		report.addEventListener( 'click', () => downloadImportReport( data.report_source, false, report ) );
		const list = document.createElement( 'ul' );
		data.errors.forEach( ( error ) => {
			const item = document.createElement( 'li' );
			item.textContent = DKBCE.csv.row + ' ' + error.row_no + ': ' + error.error;
			list.appendChild( item );
		} );
		errors.append( explanation, report, list );
	}

	async function pollImport() {
		const panel = $( '#dkbce-import-progress' );
		panel.hidden = false;
		const status = $( '#dkbce-import-status' );
		const count = $( '#dkbce-import-count' );
		const progressTrack = $( '#dkbce-import-progress-track' );
		const progressBar = $( '#dkbce-import-progress-bar' );
		const percentage = $( '#dkbce-import-percentage' );
		const time = $( '#dkbce-import-time' );
		const cancel = panel.querySelector( 'button' );
		cancel.hidden = false;
		cancel.disabled = state.importCancel;
		status.textContent = DKBCE.csv.preparing;
		updateOperationControls();
		try {
			const data = await request( 'import_status', { import_id: state.importId, cancel: state.importCancel ? '1' : '0' } );
			const processed = Number( data.processed ) || 0;
			const ratio = null === data.progress_ratio || undefined === data.progress_ratio ? null : Math.max( 0, Math.min( 1, Number( data.progress_ratio ) ) );
			const total = Number( data.total_rows ) || 0;
			count.textContent = total > 0
				? DKBCE.csv.progressRows.replace( '%1$s', processed.toLocaleString() ).replace( '%2$s', total.toLocaleString() )
				: DKBCE.csv.progressUnknown.replace( '%s', processed.toLocaleString() );
			if ( null === ratio ) {
				progressBar.removeAttribute( 'value' );
				percentage.textContent = '';
			} else {
				const percent = Math.floor( ratio * 100 );
				progressBar.value = percent;
				percentage.textContent = percent + '%';
			}
			progressTrack.classList.toggle( 'is-active', [ 'pending', 'processing' ].includes( data.status ) );
			const elapsed = Number( data.elapsed_seconds ) || 0;
			const remaining = null !== ratio && ratio > 0 && ratio < 1
				? formatDuration( elapsed * ( 1 - ratio ) / ratio )
				: ratio >= 1 ? formatDuration( 0 ) : DKBCE.i18n.estimatingTime;
			time.replaceChildren();
			[ [ DKBCE.i18n.elapsedTime, formatDuration( elapsed ) ], [ DKBCE.i18n.remainingTime, remaining ] ].forEach( ( [ template, duration ] ) => {
				const parts = template.split( '%s' );
				const value = document.createElement( 'strong' );
				value.className = 'dkbce-duration';
				value.textContent = duration;
				time.append( document.createTextNode( parts[ 0 ] ), value, document.createTextNode( parts.slice( 1 ).join( '%s' ) ) );
			} );
			if ( [ 'pending', 'processing' ].includes( data.status ) ) {
				status.textContent = state.importCancel
					? DKBCE.csv.cancelling
					: data.stage === 'import_strings' ? DKBCE.csv.readingStrings : DKBCE.csv.preparing;
				status.textContent += data.sheet ? ' — ' + data.sheet : '';
				window.setTimeout( pollImport, 2000 );
				return;
			}
			state.importPreparing = false;
			cancel.hidden = true;
			if ( data.status === 'ready' ) {
				panel.hidden = true;
				setNotice( '', 'info' );
				showImportPreview( data.preview );
			} else {
				const message = data.message || DKBCE.csv.importStopped;
				status.textContent = message;
				if ( [ 'failed', 'expired' ].includes( data.status ) ) {
					setNotice( message, 'error', panel );
				}
			}
			updateOperationControls();
		} catch ( error ) {
			if ( error.status >= 400 && error.status < 500 ) {
				state.importPreparing = false;
				cancel.hidden = true;
				status.textContent = error.message;
				setNotice( error.message, 'error', panel );
				updateOperationControls();
				return;
			}
			status.textContent = error.message + ' ' + DKBCE.csv.retrying;
			window.setTimeout( pollImport, 5000 );
		}
	}

	$( '#dkbce-import-progress button' ).addEventListener( 'click', () => {
		state.importCancel = true;
		$( '#dkbce-import-progress button' ).disabled = true;
	} );

	async function exportCSV() {
		if ( state.busy || isOperationRunning() || ! state.previewId ) {
			return;
		}
		const columns = Array.from( document.querySelectorAll( '[name="dkbce-csv-column"]:checked' ), ( input ) => input.value );
		if ( ! columns.length ) {
			setNotice( DKBCE.csv.columns, 'error', $( '#dkbce-csv' ) );
			return;
		}
		window.clearTimeout( state.pollTimer );
		let count = 0;
		let cancelled = false;
		let downloadId = '';
		const total = selectionCount();
		const startedAt = window.performance.now();
		const heading = document.createElement( 'h3' );
		heading.textContent = DKBCE.csv.exporting;
		const status = document.createElement( 'p' );
		status.className = 'dkbce-progress-count';
		const track = document.createElement( 'div' );
		track.className = 'dkbce-progress-track is-active';
		const progress = document.createElement( 'progress' );
		progress.max = Math.max( 1, total );
		progress.setAttribute( 'aria-label', DKBCE.i18n.progressLabel );
		const highlight = document.createElement( 'span' );
		highlight.className = 'dkbce-progress-highlight';
		highlight.setAttribute( 'aria-hidden', 'true' );
		const percentage = document.createElement( 'span' );
		percentage.className = 'dkbce-progress-percentage';
		percentage.setAttribute( 'aria-hidden', 'true' );
		track.append( progress, highlight, percentage );
		const time = document.createElement( 'p' );
		time.className = 'dkbce-operation-time dkbce-export-time';
		time.setAttribute( 'aria-live', 'off' );
		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = DKBCE.csv.cancel;
		cancel.addEventListener( 'click', () => {
			cancelled = true;
			cancel.disabled = true;
			if ( downloadId ) {
				request( 'download_cancel', { download_id: downloadId } ).catch( ( error ) => setNotice( error.message, 'error' ) );
			}
		} );
		operationPanel.classList.remove( 'is-history' );
		operationPanel.replaceChildren( heading, status, track, time, cancel );
		operationPanel.hidden = false;
		const renderProgress = ( done = false ) => {
			const elapsed = Math.floor( ( window.performance.now() - startedAt ) / 1000 );
			const percent = done ? 100 : Math.floor( Math.min( 100, ( count / Math.max( 1, total ) ) * 100 ) );
			status.textContent = DKBCE.i18n.progressSummary
				.replace( '%1$s', count.toLocaleString() )
				.replace( '%2$s', total.toLocaleString() );
			progress.value = done ? progress.max : Math.min( count, progress.max );
			highlight.style.width = percent + '%';
			percentage.textContent = percent + '%';
			const remaining = done ? formatDuration( 0 ) : count > 0 ? formatDuration( ( elapsed / count ) * Math.max( 0, total - count ) ) : DKBCE.i18n.estimatingTime;
			time.replaceChildren();
			[ [ DKBCE.i18n.elapsedTime, formatDuration( elapsed ) ], [ DKBCE.i18n.remainingTime, remaining ] ].forEach( ( [ template, duration ] ) => {
				const parts = template.split( '%s' );
				const metric = document.createElement( 'span' );
				metric.appendChild( document.createTextNode( parts[ 0 ] ) );
				const value = document.createElement( 'strong' );
				value.className = 'dkbce-duration';
				value.textContent = duration;
				metric.appendChild( value );
				if ( parts.length > 1 ) {
					metric.appendChild( document.createTextNode( parts.slice( 1 ).join( '%s' ) ) );
				}
				time.appendChild( metric );
			} );
		};
		renderProgress();
		const excluded = $( '#dkbce-selected-only' ).checked ? Array.from( state.excludedIds ) : [];
		const rangeToken = state.selectionRange ? state.selectionRange.token : '';
		setBusy( true, applyButton, DKBCE.csv.exporting );
		const timer = window.setInterval( () => renderProgress(), 1000 );
		try {
			const job = await request( 'csv_export', { preview_id: state.previewId, columns, excluded_ids: JSON.stringify( excluded ), range_token: rangeToken, selection_mode: state.explicitSelection ? 'explicit' : 'all_except', selected_ids: state.explicitSelection ? Array.from( state.selectedIds ) : [] } );
			downloadId = job.download_id;
			if ( cancelled ) {
				await request( 'download_cancel', { download_id: downloadId } );
				heading.textContent = DKBCE.csv.cancelled;
			} else {
				const download = await waitForDownload( downloadId, ( status ) => {
					count = status.rows;
					renderProgress( 'completed' === status.status );
				} );
				heading.textContent = DKBCE.ui.downloadReady;
				const link = document.createElement( 'a' );
				link.href = download.url;
				link.className = 'button button-primary';
				link.textContent = DKBCE.ui.downloadCSV;
				operationPanel.appendChild( link );
			}
		} catch ( error ) {
			heading.textContent = error.message;
			setNotice( error.message, 'error' );
		} finally {
			window.clearInterval( timer );
			cancel.remove();
			setBusy( false, applyButton );
		}
	}

	document.querySelectorAll( '[data-dkbce-mode]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			if ( state.busy || state.loadingPage || ( isOperationRunning() && ! [ 'history', 'settings' ].includes( button.dataset.dkbceMode ) ) ) {
				return;
			}
			const nextMode = button.dataset.dkbceMode;
			window.clearTimeout( historyState.pollTimer );
			window.clearTimeout( state.previewTimer );
			state.mode = nextMode;
			if ( [ 'bulk', 'csv' ].includes( nextMode ) && nextMode !== state.editorMode ) {
				state.editorMode = nextMode;
				resetSelection();
				invalidatePreview( true, true );
			}
			updateCSVMode();
			if ( state.mode === 'history' ) { loadHistory(); if ( historyState.operationId ) { loadHistoryDetails(); } }
			if ( state.mode === 'settings' ) { loadSettings(); }
		} );
	} );
	document.querySelectorAll( 'input[name="dkbce-csv-action"]' ).forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			invalidatePreview( true, true );
			$( '#dkbce-csv-errors' ).hidden = true;
			updateCSVMode();
		} );
	} );
	function updateFileDetails() {
		const file = $( '#dkbce-csv-file' ).files[ 0 ];
		$( '#dkbce-file-details' ).hidden = ! file;
		$( '#dkbce-file-details span' ).textContent = file ? file.name + ' · ' + Math.ceil( file.size / 1024 ) + ' KB' : '';
	}
	$( '#dkbce-file-replace' ).addEventListener( 'click', () => $( '#dkbce-csv-file' ).click() );
	$( '#dkbce-file-remove' ).addEventListener( 'click', () => { $( '#dkbce-csv-file' ).value = ''; updateFileDetails(); invalidatePreview( true, true ); } );
	$( '#dkbce-csv-file' ).addEventListener( 'change', () => { updateFileDetails(); invalidatePreview( true, true ); } );
	$( '#dkbce-csv-preview' ).addEventListener( 'click', previewCSV );
	$( '#dkbce-csv-template' ).addEventListener( 'click', () => downloadCSV( [ 'id,sku,cogs,new_cogs\r\n,REPLACE-WITH-YOUR-SKU,10.00,12.50\r\n' ], 'cogs-template.csv' ) );
	$( '#dkbce-csv-drop' ).addEventListener( 'dragover', ( event ) => event.preventDefault() );
	$( '#dkbce-csv-drop' ).addEventListener( 'drop', ( event ) => {
		event.preventDefault();
		if ( ! state.busy && ! isOperationRunning() && event.dataTransfer.files.length === 1 ) {
			$( '#dkbce-csv-file' ).files = event.dataTransfer.files;
			updateFileDetails();
			invalidatePreview( true, true );
		}
	} );

	const preferences = DKBCE.preferences || {};
	pageSizeSelect.value = String( preferences.page_size || pageSizeSelect.value );
	const hiddenColumns = new Set( preferences.hidden_columns || [] );
	let preferenceQueue = Promise.resolve();

	function savePreferences() {
		const values = { page_size: Number( pageSizeSelect.value ), hidden_columns: Array.from( hiddenColumns ) };
		preferenceQueue = preferenceQueue.then( () => request( 'preferences', values ) )
			.catch( ( error ) => setNotice( error.message, 'error' ) );
	}
	const columnControls = $( '.dkbce-columns' );
	$( '#dkbce-selection-toolbar' ).appendChild( columnControls );

	function updateColumnVisibility() {
		previewContent.querySelectorAll( 'tr' ).forEach( ( row ) => {
			Array.from( row.children ).forEach( ( cell, index ) => {
				const exporting = csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export';
				cell.hidden = hiddenColumns.has( Number( cell.dataset.column ?? index ) ) || ( exporting && [ 6, 7 ].includes( Number( cell.dataset.column ?? index ) ) );
			} );
		} );
		previewContent.querySelectorAll( 'table' ).forEach( ( table ) => { table.classList.toggle( 'dkbce-selection-hidden', hiddenColumns.has( 0 ) ); table.classList.toggle( 'dkbce-sku-visible', ! hiddenColumns.has( 3 ) ); } );
		const options = $( '#dkbce-column-options' ).querySelectorAll( 'input' );
		const exporting = csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export';
		options.forEach( ( input, index ) => {
			const forceHidden = exporting && [ 6, 7 ].includes( index );
			input.parentElement.hidden = forceHidden;
			input.checked = forceHidden ? false : ! hiddenColumns.has( index );
			input.disabled = forceHidden || ( input.checked && hiddenColumns.size === options.length - 1 );
		} );
	}

	DKBCE.i18n.columns.forEach( ( name, index ) => {
		const label = document.createElement( 'label' );
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.checked = ! hiddenColumns.has( index );
		input.addEventListener( 'change', () => {
			if ( input.checked ) {
				hiddenColumns.delete( index );
			} else {
				hiddenColumns.add( index );
			}
			updateColumnVisibility();
			savePreferences();
		} );
		label.append( input, document.createTextNode( name ) );
		$( '#dkbce-column-options' ).appendChild( label );
	} );
	updateColumnVisibility();
	columnControls.querySelector( 'button' ).addEventListener( 'click', () => {
		columnControls.open = false;
		columnControls.querySelector( 'summary' ).focus();
	} );
	document.addEventListener( 'click', ( event ) => {
		document.querySelectorAll( '.dkbce-columns[open], .dkbce-help[open], .dkbce-filter-dropdown[open]' ).forEach( ( details ) => {
			if ( ! details.contains( event.target ) ) {
				details.open = false;
			}
		} );
	} );
	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			document.querySelectorAll( '.dkbce-columns[open], .dkbce-help[open], .dkbce-filter-dropdown[open]' ).forEach( ( details ) => {
				details.open = false;
				details.querySelector( 'summary' ).focus();
			} );
		}
	} );

	function setNotice( message, type, target ) {
		if ( target ) { target.appendChild( notice ); }
		else { noticeParent.insertBefore( notice, noticeNextSibling ); }
		notice.className =
			'notice ' + ( type === 'error' ? 'notice-error' : 'notice-info' );
		notice.querySelector( 'p' ).textContent = message;
		notice.hidden = ! message;
	}

	function setApplySpinner( active ) {
		applySpinner.classList.toggle( 'is-active', active );
		applySpinner.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
	}

	function toggleSpinner( spinner, active ) {
		spinner.classList.toggle( 'is-active', active );
		spinner.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
	}

	function request( action, values ) {
		const data = new FormData();
		data.append( 'action', 'dkbce_' + action );
		data.append( 'nonce', DKBCE.nonce );
		Object.keys( values || {} ).forEach( ( key ) => {
			const value = values[ key ];
			if ( key === 'filters' ) {
				Object.keys( value ).forEach( ( filter ) => {
					if ( Array.isArray( value[ filter ] ) ) {
						value[ filter ].forEach( ( item ) => data.append( 'filters[' + filter + '][]', item ) );
					} else {
						data.append( 'filters[' + filter + ']', value[ filter ] );
					}
				} );
			} else if ( Array.isArray( value ) ) {
				value.forEach( ( item ) => data.append( key + '[]', item ) );
			} else {
				data.append( key, value );
			}
		} );
		return fetch( DKBCE.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: action === 'preferences',
			body: data,
		} )
			.then( async ( response ) => {
				const payload = await response.json();
				if ( ! response.ok ) {
					const error = new Error( payload.data && payload.data.message ? payload.data.message : DKBCE.csv.requestFailed );
					error.status = response.status;
					error.refreshPreview = Boolean( payload.data && payload.data.refresh_preview );
					throw error;
				}
				return payload;
			} )
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

	function selectedFilterValues( selector ) {
		const select = $( selector );
		return select ? Array.from( select.selectedOptions, ( option ) => option.value )
			.filter( ( value ) => value !== '0' && value !== 'any' ) : [];
	}

	// Keep native selects as the filter source; checkboxes provide a compact multi-select UI.
	document.querySelectorAll( '.dkbce-multi-select' ).forEach( ( select ) => {
		const details = document.createElement( 'details' );
		details.className = 'dkbce-filter-dropdown';
		const summary = document.createElement( 'summary' );
		summary.id = select.id + '-summary';
		const fieldLabel = document.querySelector( 'label[for="' + select.id + '"]' );
		fieldLabel.id = select.id + '-label';
		summary.setAttribute( 'aria-labelledby', fieldLabel.id + ' ' + summary.id );
		const panel = document.createElement( 'div' );
		panel.className = 'dkbce-popover';
		const search = document.createElement( 'input' );
		search.type = 'search';
		search.placeholder = DKBCE.ui.searchOptions;
		search.setAttribute( 'aria-label', DKBCE.ui.searchOptions + ': ' + fieldLabel.textContent );
		panel.appendChild( search );
		const allOption = select.options[ 0 ];
		const clear = document.createElement( 'button' );
		clear.type = 'button';
		clear.className = 'button-link';
		clear.textContent = allOption.textContent;
		panel.appendChild( clear );
		const selectAllLabel = document.createElement( 'label' );
		const selectAll = document.createElement( 'input' );
		selectAll.type = 'checkbox';
		selectAllLabel.append( selectAll, document.createTextNode( DKBCE.i18n.selectAll ) );
		panel.appendChild( selectAllLabel );
		const inputs = [];
		Array.from( select.options ).slice( 1 ).forEach( ( option ) => {
			const label = document.createElement( 'label' );
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.addEventListener( 'change', () => {
				allOption.selected = false;
				option.selected = input.checked;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			inputs.push( { input, option, label } );
			label.append( input, document.createTextNode( option.textContent ) );
			panel.appendChild( label );
		} );
		const sync = () => {
			const selected = inputs.filter( ( { option } ) => option.selected );
			selectAll.checked = inputs.length > 0 && selected.length === inputs.length;
			selectAll.indeterminate = selected.length > 0 && selected.length < inputs.length;
			selectAll.disabled = inputs.length === 0;
			summary.textContent = selected.length ? DKBCE.ui.optionsSelected.replace( '%d', selected.length ) : allOption.textContent;
			summary.title = selected.map( ( { option } ) => option.textContent ).join( ', ' ) || allOption.textContent;
			inputs.forEach( ( { input, option } ) => { input.checked = option.selected; } );
		};
		selectAll.addEventListener( 'change', () => {
			allOption.selected = false;
			inputs.forEach( ( { option } ) => { option.selected = selectAll.checked; } );
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		clear.addEventListener( 'click', () => {
			Array.from( select.options ).forEach( ( option ) => { option.selected = false; } );
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		search.addEventListener( 'input', () => {
			const query = search.value.trim().toLocaleLowerCase();
			inputs.forEach( ( { option, label } ) => { label.hidden = ! option.textContent.toLocaleLowerCase().includes( query ); } );
		} );
		select.addEventListener( 'change', sync );
		fieldLabel.addEventListener( 'click', ( event ) => { event.preventDefault(); summary.focus(); } );
		details.append( summary, panel );
		select.after( details );
		select.hidden = true;
		sync();
	} );

	function renderFilterChips() {
		const chips = $( '#dkbce-filter-chips' );
		chips.replaceChildren();
		document.querySelectorAll( '.dkbce-filter-grid > p > input, .dkbce-filter-grid > p > select, .dkbce-range input' ).forEach( ( input ) => {
			const values = input.tagName === 'SELECT' ? Array.from( input.selectedOptions ).filter( ( option ) => ! [ '0', 'any' ].includes( option.value ) ) : input.value ? [ null ] : [];
			values.forEach( ( option ) => {
				const label = document.querySelector( 'label[for="' + input.id + '"]' ).textContent;
				const text = label + ': ' + ( option ? option.textContent : input.value );
				const chip = document.createElement( 'button' );
				chip.type = 'button';
				chip.className = 'button dkbce-filter-chip';
				chip.textContent = text + ' ×';
				chip.setAttribute( 'aria-label', DKBCE.ui.removeFilter.replace( '%s', text ) );
				chip.disabled = state.busy || isOperationRunning();
				chip.addEventListener( 'click', () => {
					if ( option ) { option.selected = false; } else { input.value = ''; }
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					$( '#dkbce-clear-filters' ).focus();
				} );
				chips.appendChild( chip );
			} );
		} );
	}

	function filters() {
		return {
			search: $( '#dkbce-search' ).value,
			category: selectedFilterValues( '#dkbce-category' ),
			type: selectedFilterValues( '#dkbce-type' ),
			stock_status: selectedFilterValues( '#dkbce-stock' ),
			brand: selectedFilterValues( '#dkbce-brand' ),
			price_min: $( '#dkbce-price-min' ).value,
			price_max: $( '#dkbce-price-max' ).value,
			cogs_min: $( '#dkbce-cogs-min' ).value,
			cogs_max: $( '#dkbce-cogs-max' ).value,
		};
	}

	function selectedAction() {
		const kind = $( '#dkbce-action-kind' ).value;
		return kind === 'adjust' ? $( '#dkbce-action-direction' ).value + '_' + $( '#dkbce-action-unit' ).value : kind;
	}

	function invalidatePreview( filtersChanged, discard = false ) {
		const retainPreview = ! discard && ! previewContent.hidden && Boolean( previewContent.querySelector( 'table' ) );
		if ( ! csvMode() && state.previewId ) {
			state.selectionPreviewId = state.previewId;
		}
		state.previewId = '';
		state.reviewedIds.clear();
		state.rows = [];
		state.pageData = null;
		state.page = 1;
		state.loadingPage = false;
		state.pageRequestId++;
		if ( csvMode() ) {
			resetSelection();
		}
		state.previewNeedsRefresh = retainPreview;
		toggleSpinner( previewSpinner, false );
		if ( filtersChanged ) {
			state.selectionFiltersChanged = true;
			state.hasQuery = false;
			state.count = 0;
			$( '#dkbce-product-count' ).textContent = DKBCE.i18n.filtersChanged;
		}
		applyButton.disabled = true;
		previewContent.hidden = ! retainPreview;
		if ( ! retainPreview ) { previewContent.replaceChildren(); }
		previewContent.removeAttribute( 'aria-busy' );
		pageSizeSelect.disabled = state.busy;
		updateOperationControls();
	}

	function resetSelection() {
		state.previewNeedsRefresh = false;
		state.previewId = '';
		state.selectionPreviewId = '';
		state.reviewedIds.clear();
		state.excludedIds.clear();
		state.selectedIds.clear();
		state.explicitSelection = false;
		state.selectionRange = null;
		state.selectedCount = 0;
		state.selectionFiltersChanged = false;
		state.selectionNarrowed = false;
		rangeDirection.value = 'all';
		rangeCount.value = '';
	}

	function isOperationRunning() {
		return state.importPreparing || state.startingOperation || Boolean( state.operationId &&
			! [ 'completed', 'completed_with_errors', 'failed', 'cancelled' ]
				.includes( state.operationStatus ) );
	}

	function updateOperationControls() {
		renderFilterChips();
		const running = isOperationRunning();
		updateSelectionSummary();
		previewContent.inert = state.previewNeedsRefresh || state.loadingPage;
		previewContent.classList.toggle( 'is-stale', state.previewNeedsRefresh );
		previewState.hidden = ! state.previewNeedsRefresh && ! state.loadingPage;
		previewState.querySelector( '[role="status"]' ).textContent = state.loadingPage ? DKBCE.i18n.pageLoading
			: state.previewNeedsRefresh ? ( state.busy ? DKBCE.i18n.previewLoading : DKBCE.ui.stale + '. ' + DKBCE.ui.staleHelp ) : '';
		refreshPreviewButton.hidden = state.loadingPage || ( state.busy && ! state.previewNeedsRefresh );
		refreshPreviewButton.disabled = running || state.busy || state.loadingPage;
		document.querySelectorAll( '#dkbce-selection-toolbar button' ).forEach( ( input ) => { input.disabled = running || state.busy || state.loadingPage || state.previewNeedsRefresh; } );
		document.querySelectorAll( '[data-dkbce-mode], #dkbce-csv input, #dkbce-csv select, #dkbce-csv-preview, #dkbce-file-details button' ).forEach( ( input ) => {
			input.disabled = [ 'history', 'settings' ].includes( input.dataset.dkbceMode ) ? state.busy || state.loadingPage : running || state.busy || state.loadingPage || ! DKBCE.cogsAvailable;
		} );
		$( '#dkbce-action-prerequisite' ).hidden = state.hasQuery && state.count > 0;
		document.querySelectorAll( '.dkbce-action-fields input, .dkbce-action-fields select' ).forEach( ( input ) => {
			input.disabled = running || state.busy || ! state.hasQuery || state.count === 0;
		} );
		[ rangeDirection, rangeCount, rangeButton ].forEach( ( input ) => {
			input.disabled = running || state.busy || state.loadingPage || ! state.previewId;
		} );
		rangeCount.disabled = rangeCount.disabled || rangeDirection.value === 'all';
		rangeCount.max = String( Math.max( 1, state.count ) );
		document.querySelectorAll( '.dkbce-filter-grid input, .dkbce-filter-grid select, .dkbce-filter-grid button, #dkbce-reset, #dkbce-clear-filters' )
			.forEach( ( input ) => {
				if ( running || ( csvMode() && state.busy ) ) {
					if ( ! ( 'disabledBeforeOperation' in input.dataset ) ) {
						input.dataset.disabledBeforeOperation = String( input.disabled );
					}
					input.disabled = true;
				} else if ( 'disabledBeforeOperation' in input.dataset ) {
					input.disabled = input.dataset.disabledBeforeOperation === 'true';
					delete input.dataset.disabledBeforeOperation;
				}
			} );
		getButton.disabled = state.busy || running;
		applyButton.disabled = running || state.busy || state.loadingPage ||
			! state.previewId || state.previewNeedsRefresh || ! selectionCount() || ! state.count || ! state.rows.length;
		document.querySelectorAll( '#dkbce-selected-only, .dkbce-product-select, .dkbce-select-page' )
			.forEach( ( input ) => { input.disabled = running || state.loadingPage || state.previewNeedsRefresh || ( csvMode() && state.busy ); } );
		if ( state.selectionRange ) {
			document.querySelectorAll( '#dkbce-selected-only, .dkbce-product-select, .dkbce-select-page' )
				.forEach( ( input ) => { input.disabled = true; } );
		}
	}

	function setBusy( busy, button, label ) {
		state.busy = busy;
		pageSizeSelect.disabled = busy;
		if ( button === applyButton ) {
			const terminal = [ 'completed', 'completed_with_errors', 'failed', 'cancelled' ];
			setApplySpinner( busy || ( state.operationId && ! terminal.includes( state.operationStatus ) ) );
		}
		updateOperationControls();
		button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		if ( button === getButton ) {
			$( '#dkbce-products-loading' ).textContent = busy ? label : '';
			toggleSpinner( productsSpinner, busy );
			return;
		}
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

	function hasValidActionValue() {
		const action = selectedAction();
		const value = numericActionValue();
		return (
			'clear' === action ||
			( '' !== value && Number.isFinite( Number( value ) ) && Number( value ) >= 0 )
		);
	}

	function actionLabel() {
		const labels = {
			set: [ DKBCE.i18n.cogsLabel, DKBCE.currencySymbol ],
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
		const adjusting = $( '#dkbce-action-kind' ).value === 'adjust';
		$( '#dkbce-action-direction' ).parentElement.hidden = ! adjusting;
		$( '#dkbce-action-unit' ).parentElement.hidden = ! adjusting;
		const amount = Number( numericActionValue() );
		let result = action === 'set' ? amount : 10;
		if ( adjusting ) {
			const delta = action.endsWith( '_percent' ) ? 10 * amount / 100 : amount;
			result = Math.max( 0, 10 + ( action.startsWith( 'decrease' ) ? -delta : delta ) );
		}
		$( '#dkbce-action-example' ).textContent = hasValidActionValue() ? DKBCE.ui.example
			.replace( '%1$s', money( 10 ) ).replace( '%2$s', action === 'clear' || result === 0 ? DKBCE.i18n.emptyValue : money( result ) ) + ( action === 'clear' ? ' ' + DKBCE.ui.clearWarning : '' ) : '';
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
		const identity = document.createElement( 'span' );
		const name = item.name || DKBCE.i18n.noName;
		if ( item.edit_url ) {
			const link = document.createElement( 'a' );
			link.href = item.edit_url;
			link.textContent = name;
			identity.appendChild( link );
		} else {
			identity.appendChild( document.createTextNode( name ) );
		}
		if ( item.sku ) {
			const sku = document.createElement( 'small' );
			sku.textContent = item.sku;
			identity.appendChild( sku );
		}
		product.appendChild( identity );
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
			( 'up' === item.change_direction ? 'is-increase' : 'is-decrease' );
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

	function updatePageSelectionControls() {
		const checkboxes = Array.from(
			previewContent.querySelectorAll( '.dkbce-product-select' )
		);
		const selectedCount = checkboxes.filter( ( checkbox ) => checkbox.checked ).length;
		previewContent.querySelectorAll( '.dkbce-select-page' ).forEach( ( checkbox ) => {
			checkbox.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
			checkbox.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
		} );
	}

	function selectionCount() {
		return state.explicitSelection ? state.selectedIds.size : state.selectionRange ? state.selectionRange.count : state.selectedCount;
	}

	function productSelected( id ) {
		if ( state.selectionRange ) { return Number( id ) >= state.selectionRange.min && Number( id ) <= state.selectionRange.max; }
		return state.explicitSelection ? state.selectedIds.has( String( id ) ) : ! state.excludedIds.has( String( id ) );
	}

	function setProductSelected( id, selected ) {
		id = String( id );
		if ( state.explicitSelection ) {
			if ( selected && ! state.selectedIds.has( id ) && state.selectedIds.size >= 500 ) { setNotice( DKBCE.ui.manualLimit, 'error' ); return; }
			if ( selected ) { state.selectedIds.add( id ); } else { state.selectedIds.delete( id ); }
		} else {
			if ( productSelected( id ) !== selected ) { state.selectedCount += selected ? 1 : -1; }
			if ( selected ) { state.excludedIds.delete( id ); } else { state.excludedIds.add( id ); }
		}
	}

	function updateSelectionSummary() {
		const count = Math.max( 0, selectionCount() );
		$( '#dkbce-selection-toolbar' ).hidden = ! state.rows.length;
		$( '#dkbce-range-menu' ).hidden = csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'import';
		$( '#dkbce-select-all' ).textContent = DKBCE.ui.all.replace( '%d', state.count.toLocaleString() );
		$( '#dkbce-selection-count' ).textContent = DKBCE.ui.selected.replace( '%d', count.toLocaleString() );
		$( '#dkbce-page-selection-count' ).textContent = DKBCE.ui.pageSelected.replace( '%d', state.rows.filter( ( item ) => productSelected( item.id ) ).length );
		$( '#dkbce-action-summary' ).textContent = state.previewNeedsRefresh ? DKBCE.ui.stale : state.previewId ? count.toLocaleString() + ' · ' + ( csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export' ? DKBCE.ui.format : operationDescription() ) : csvMode() ? ( $( 'input[name="dkbce-csv-action"]:checked' ).value === 'import' ? DKBCE.ui.importEmpty : DKBCE.ui.exportEmpty ) : DKBCE.ui.bulkEmpty;
		if ( ! state.busy ) {
			applyButton.textContent = csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export' ? DKBCE.ui.createCSV : count ? DKBCE.ui.reviewCount.replace( '%d', count.toLocaleString() ) : DKBCE.ui.review;
		}
		$( '#dkbce-selection-context' ).hidden = ! state.selectionNarrowed;
		$( '#dkbce-selection-context' ).textContent = state.selectionNarrowed ? DKBCE.ui.narrowed : '';
	}

	function setPageSelection( selected ) {
		state.rows.forEach( ( item ) => setProductSelected( item.id, selected ) );
		previewContent.querySelectorAll( '.dkbce-product-select' ).forEach( ( checkbox ) => { checkbox.checked = productSelected( checkbox.value ); } );
		updatePageSelectionControls();
		updateOperationControls();
	}

	$( '#dkbce-select-all' ).addEventListener( 'click', () => {
		state.explicitSelection = false;
		state.selectedIds.clear();
		state.selectionRange = null;
		state.excludedIds.clear();
		state.selectedCount = state.count;
		renderPreview( state.pageData );
	} );
	$( '#dkbce-select-none' ).addEventListener( 'click', () => {
		state.explicitSelection = true;
		state.selectedIds.clear();
		state.selectionRange = null;
		state.excludedIds.clear();
		renderPreview( state.pageData );
	} );

	function renderPreview( data, restoreSelection = false ) {
		if ( data !== state.pageData ) {
			state.previewNeedsRefresh = false;
		}
		state.previewId = data.preview_id;
		if ( restoreSelection && data.selected_count !== undefined ) {
			state.selectedCount = data.selected_count;
			state.selectionPreviewId = data.preview_id;
			state.excludedIds = new Set( data.excluded_ids.map( String ) );
			state.selectionRange = data.selection_range || null;
		}
		if ( restoreSelection && data.selected_ids !== null && data.selected_ids !== undefined ) {
			state.explicitSelection = true;
			state.selectedIds = new Set( data.selected_ids.map( String ) );
		}
		if ( ! state.pageData && data.selected_count === undefined ) { state.selectedCount = data.count; }
		state.count = data.count;
		state.rows = data.rows;
		state.pageData = data;
		state.page = data.page;
		data.rows.forEach( ( item ) => state.reviewedIds.add( String( item.id ) ) );
		$( '.dkbce-preview-controls' ).hidden = ! data.rows.length;
		previewContent.replaceChildren();
		previewContent.removeAttribute( 'aria-busy' );
		const summary = document.createElement( 'p' );
		summary.className = 'dkbce-preview-summary';
		summary.textContent =
			data.count +
			' ' +
			( data.count === 1
				? DKBCE.i18n.productOne
				: DKBCE.i18n.productMany );
		previewContent.appendChild( summary );
		if ( ! csvMode() ) {
			const freshness = document.createElement( 'p' );
			freshness.textContent = DKBCE.i18n.previewFreshness;
			previewContent.appendChild( freshness );
		}
		if ( data.rows.some( ( item ) => item.status === 'success' && item.new === null ) ) {
			const clearing = document.createElement( 'p' );
			clearing.textContent = DKBCE.i18n.zeroClears;
			previewContent.appendChild( clearing );
		}

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
		DKBCE.i18n.columns.forEach( ( label, index ) => {
			const cell = document.createElement( 'th' );
			cell.scope = 'col';
			if ( 0 === index ) {
				const selectAll = document.createElement( 'input' );
				selectAll.type = 'checkbox';
				selectAll.className = 'dkbce-select-page';
				selectAll.setAttribute( 'aria-label', DKBCE.i18n.selectPageProducts );
				cell.append( selectAll, document.createTextNode( ' ' + label ) );
			} else {
				cell.textContent = label;
			}
			header.appendChild( cell );
		} );
		if ( csvMode() ) {
			const status = document.createElement( 'th' );
			status.scope = 'col';
			status.textContent = DKBCE.csv.status;
			header.appendChild( status );
		}
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
			checkbox.checked = productSelected( item.id );
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
			if ( csvMode() ) { appendCell( row, item.reason || DKBCE.csv.valid ); }
			tbody.appendChild( row );
		} );
		[ header, ...tbody.children ].forEach( ( row ) => {
			const cells = Array.from( row.children );
			cells.forEach( ( cell, index ) => { cell.dataset.column = String( index ); } );
			[ 0, 2, 5, 6, 7, 1, 3, 4, 8 ].forEach( ( index ) => { if ( cells[ index ] ) { row.appendChild( cells[ index ] ); } } );
		} );
		table.appendChild( tbody );
		wrapper.appendChild( table );
		previewContent.appendChild( wrapper );
		const footer = document.createElement( 'div' );
		footer.className = 'dkbce-table-footer';
		const bottomSelectAllLabel = document.createElement( 'label' );
		bottomSelectAllLabel.className = 'dkbce-select-all-bottom';
		const bottomSelectAll = document.createElement( 'input' );
		bottomSelectAll.type = 'checkbox';
		bottomSelectAll.className = 'dkbce-select-page';
		bottomSelectAll.setAttribute( 'aria-label', DKBCE.i18n.selectPageProducts );
		bottomSelectAllLabel.append( bottomSelectAll, document.createTextNode( DKBCE.i18n.selectPageProducts ) );
		footer.appendChild( bottomSelectAllLabel );
		const pageSummary = document.createElement( 'p' );
		pageSummary.className = 'description dkbce-page-summary';
		const firstProduct = ( data.page - 1 ) * data.page_size + 1;
		const lastProduct = Math.min( data.page * data.page_size, data.count );
		pageSummary.textContent = DKBCE.i18n.pageSummary
			.replace( '%1$d', String( firstProduct ) )
			.replace( '%2$d', String( lastProduct ) )
			.replace( '%3$d', String( data.count ) );
		footer.append( pageSizeLabel, pageSizeSelect, pageSummary );
		previewContent.appendChild( footer );
		updatePageSelectionControls();

		if ( data.total_pages > 1 ) {
			const { pagination, jump } = createPagination( data.page, data.total_pages, fetchPreviewPage, 'dkbce-jump-page' );
			footer.insertBefore( pagination, pageSummary );
			footer.insertBefore( jump, pageSummary );
		}
		updateColumnVisibility();
		previewContent.hidden = false;
		updateOperationControls();
	}

	function fetchPreviewPage( page ) {
		if ( state.busy || state.loadingPage || state.previewNeedsRefresh || ! state.previewId ) {
			return;
		}
		const previousPage = state.pageData;
		const requestId = ++state.pageRequestId;
		state.loadingPage = true;
		updateOperationControls();
		toggleSpinner( previewSpinner, true );
		applyButton.disabled = true;
		previewContent.setAttribute( 'aria-busy', 'true' );
		pageSizeSelect.disabled = true;
		request( 'preview_page', {
			preview_id: state.previewId,
			operation_id: isOperationRunning() ? state.operationId : '',
			page,
			page_size: Number( pageSizeSelect.value ),
		} )
			.then( ( data ) => {
				if ( requestId !== state.pageRequestId ) {
					return;
				}
				state.loadingPage = false;
				renderPreview( data );
			} )
			.catch( ( error ) => {
				if ( requestId !== state.pageRequestId ) {
					return;
				}
				state.loadingPage = false;
				setNotice( error.message, 'error', $( '.dkbce-preview-section' ) );
				if ( previousPage ) {
					pageSizeSelect.value = String( previousPage.page_size );
					renderPreview( previousPage );
				}
			} )
			.finally( () => {
				if ( requestId !== state.pageRequestId ) {
					return;
				}
				state.loadingPage = false;
				toggleSpinner( previewSpinner, false );
				previewContent.removeAttribute( 'aria-busy' );
				pageSizeSelect.disabled = state.busy;
				updateOperationControls();
			} );
	}

	function getProducts() {
		if ( isOperationRunning() || state.busy || state.loadingPage ) {
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
					? DKBCE.i18n.matchCount
						.replace( '%1$d', String( data.count ) )
						.replace( '%2$d', String( data.product_count ) )
						.replace( '%3$d', String( data.variation_count ) )
					: DKBCE.i18n.noProducts;
				if ( 0 === data.count ) {
					invalidatePreview( false, true );
					setNotice( DKBCE.i18n.noProducts, 'info', $( '.dkbce-filters-section' ) );
				}
			} )
			.catch( ( error ) => setNotice( error.message, 'error', $( '.dkbce-filters-section' ) ) )
			.finally( () => {
				setBusy( false, getButton );
				if ( state.hasQuery && state.count > 0 ) {
					if ( csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export' ) {
						previewCSV();
					} else if ( ! csvMode() && hasValidActionValue() ) {
						preview();
					}
				}
			} );
	}

	function preview() {
		if ( csvMode() ) { return; }
		if ( isOperationRunning() || state.busy || state.loadingPage || 0 === state.count ) {
			return;
		}
		const action = selectedAction();
		const value = numericActionValue();
		const requestedPageSize = Number( pageSizeSelect.value );
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
		setBusy( true, getButton, DKBCE.i18n.previewLoading );
		toggleSpinner( previewSpinner, true );
		previewContent.setAttribute( 'aria-busy', 'true' );
		const requestedFilters = filters();
		const previousPreviewId = state.selectionPreviewId;
		request( 'preview', {
			previous_preview_id: previousPreviewId,
			selection_mode: state.explicitSelection ? 'explicit' : 'all_except',
			selected_ids: state.explicitSelection ? Array.from( state.selectedIds ) : [],
			excluded_ids: JSON.stringify( Array.from( state.excludedIds ) ),
			range_token: state.selectionRange ? state.selectionRange.token : '',
			filters: requestedFilters,
			action_type: action,
			action_value: value,
			page_size: requestedPageSize,
		} )
			.then( ( data ) => {
				if (
					JSON.stringify( requestedFilters ) !==
						JSON.stringify( filters() ) ||
					action !== selectedAction() ||
					value !== numericActionValue() ||
					requestedPageSize !== Number( pageSizeSelect.value )
				) {
					return;
				}
				renderPreview( data, true );
				if ( state.selectionFiltersChanged && previousPreviewId ) {
					setNotice( DKBCE.i18n.selectionPreserved.replace( '%d', String( state.selectedCount ) ), 'info' );
				}
				state.selectionNarrowed = Boolean( previousPreviewId && state.selectionFiltersChanged ) || state.selectionNarrowed;
				state.selectionFiltersChanged = false;
				updateSelectionSummary();
			} )
			.catch( ( error ) => setNotice( error.message, 'error', $( '.dkbce-preview-section' ) ) )
			.finally( () => {
				toggleSpinner( previewSpinner, false );
				previewContent.removeAttribute( 'aria-busy' );
				setBusy( false, getButton );
				updateOperationControls();
			} );
	}

	function operationDescription() {
		if ( csvMode() ) { return DKBCE.ui.importTask; }
		const action = selectedAction();
		if ( action === 'clear' ) { return DKBCE.ui.clearCost; }
		const direction = action.startsWith( 'increase' ) ? DKBCE.ui.increase : DKBCE.ui.decrease;
		const amount = action.includes( 'percent' ) ? numericActionValue() + '%' : money( numericActionValue() );
		return ( action === 'set' ? DKBCE.ui.set : direction ) + ' · ' + amount;
	}

	function openConfirm() {
		const exporting = csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export';
		if ( isOperationRunning() || state.busy || state.loadingPage || ! state.previewId || state.previewNeedsRefresh || ! state.count ) {
			return;
		}
		const selectedOnly = $( '#dkbce-selected-only' ).checked;
		const selectedCount = selectionCount();
		const count = selectedOnly ? selectedCount : state.count;
		if ( selectedOnly && ! count ) {
			setNotice( DKBCE.i18n.noSelection, 'error' );
			return;
		}
		const confirmButton = $( '#dkbce-confirm-apply' );
		$( '#dkbce-confirm-title' ).textContent = exporting ? DKBCE.i18n.confirmExportTitle : DKBCE.i18n.confirmTitle;
		confirmButton.disabled = false;
		confirmButton.textContent = exporting ? DKBCE.ui.createCSV : DKBCE.ui.updateCount.replace( '%d', count );
		const confirmText = exporting
			? ( count === 1 ? DKBCE.i18n.confirmExportOne : DKBCE.i18n.confirmExportText )
			: ( count === 1 ? DKBCE.i18n.confirmTextOne : DKBCE.i18n.confirmText );
		$( '#dkbce-confirm-text' ).textContent = confirmText.replace( '%d', String( count ) );
		const summary = $( '#dkbce-confirm-summary' );
		summary.replaceChildren();
		const reviewed = Array.from( state.reviewedIds ).filter( productSelected ).length;
		const entries = [ [ DKBCE.ui.operation, exporting ? DKBCE.ui.format : operationDescription() ], [ DKBCE.ui.scope, String( count ) ] ];
		if ( exporting ) {
			entries.push( [ DKBCE.ui.fileColumns, Array.from( document.querySelectorAll( '[name="dkbce-csv-column"]:checked' ), ( input ) => input.parentElement.textContent.trim() ).join( ', ' ) + ', new_cogs' ] );
		} else {
			entries.push( [ DKBCE.ui.coverage, reviewed >= count ? DKBCE.ui.allReviewed : DKBCE.ui.reviewed.replace( '%d', reviewed ) ] );
		}
		entries.forEach( ( [ label, value ] ) => {
			const term = document.createElement( 'dt' );
			const description = document.createElement( 'dd' );
			term.textContent = label;
			description.textContent = value;
			summary.append( term, description );
		} );
		$( '#dkbce-confirm-warning' ).textContent = exporting ? '' : DKBCE.ui.persistence + ( selectedAction() === 'clear' || ( selectedAction() === 'set' && numericActionValue() !== '' && Number( numericActionValue() ) === 0 ) || state.rows.some( ( item ) => productSelected( item.id ) && item.status === 'success' && item.new === null ) ? ' ' + DKBCE.i18n.zeroClears : '' );
		modal.hidden = false;
		$( '#dkbce-confirm-cancel' ).focus();
	}

	function confirmAction() {
		if ( csvMode() && $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export' ) {
			modal.hidden = true;
			exportCSV();
			return;
		}
		applyChanges();
	}

	function schedulePreviewRefresh() {
		if ( csvMode() ) { return; }
		if ( state.previewTimer ) {
			window.clearTimeout( state.previewTimer );
		}
		if ( isOperationRunning() || ! state.hasQuery || ! state.count ) {
			return;
		}
		const action = selectedAction();
		const value = numericActionValue();
		if (
			'clear' !== action &&
			( '' === value || ! Number.isFinite( Number( value ) ) || Number( value ) < 0 )
		) {
			return;
		}
		state.previewTimer = window.setTimeout( () => {
			if ( state.busy ) {
				schedulePreviewRefresh();
				return;
			}
			preview();
		}, 350 );
	}

	refreshPreviewButton.addEventListener( 'click', async () => {
		if ( state.busy || state.loadingPage || isOperationRunning() ) { return; }
		if ( ! csvMode() ) {
			if ( ! state.hasQuery ) { getProducts(); }
			else { preview(); }
			return;
		}
		if ( $( 'input[name="dkbce-csv-action"]:checked' ).value === 'export' ) {
			getProducts();
			return;
		}
		setBusy( true, refreshPreviewButton, DKBCE.i18n.previewLoading );
		try {
			const result = await request( 'import_status', { import_id: state.importId } );
			if ( result.preview ) { showImportPreview( result.preview ); }
		} catch ( error ) {
			setNotice( error.message, 'error', $( '.dkbce-preview-section' ) );
		} finally {
			setBusy( false, refreshPreviewButton );
		}
	} );

	function applyChanges() {
		if ( isOperationRunning() || ! state.previewId || state.previewNeedsRefresh || state.busy || state.loadingPage ) {
			return;
		}
		const selectedOnly = $( '#dkbce-selected-only' ).checked;
		const excludedIds = Array.from( state.excludedIds );
		modal.hidden = true;
		applyButton.disabled = true;
		setNotice( '', 'info' );
		state.operationStartedAt = null;
		if ( state.operationTimer ) {
			window.clearInterval( state.operationTimer );
			state.operationTimer = null;
		}
		state.startingOperation = true;
		window.clearTimeout( state.previewTimer );
		setBusy( true, applyButton, DKBCE.i18n.starting );
		request( 'apply', {
			note: noteInput.value,
			confirmed_count: selectionCount(),
			selected_ids: state.explicitSelection ? Array.from( state.selectedIds ) : [],
			preview_id: state.previewId,
			selected_only: selectedOnly ? '1' : '',
			selection_mode: state.explicitSelection ? 'explicit' : state.selectionRange ? 'range' : 'all_except',
			range_token: state.selectionRange ? state.selectionRange.token : '',
			excluded_ids: JSON.stringify( selectedOnly ? excludedIds : [] ),
		} )
			.then( ( data ) => {
				state.operationId = data.operation_id;
				operationPanel.hidden = false;
				renderOperation( data.state );
				window.scrollTo( {
					top: document.documentElement.scrollHeight,
					behavior: 'smooth',
				} );
				pollProgress();
			} )
			.catch( ( error ) => {
				setNotice( error.message, 'error', $( '.dkbce-preview-section' ) );
				if ( error.refreshPreview ) {
					state.previewNeedsRefresh = true;
				}
			} )
			.finally( () => {
				state.startingOperation = false;
				setBusy( false, applyButton );
			} );
	}

	function formatDuration( seconds ) {
		const duration = Math.max( 0, Math.floor( seconds ) );
		const hours = Math.floor( duration / 3600 );
		const minutes = Math.floor( ( duration % 3600 ) / 60 );
		const remainingSeconds = duration % 60;
		return [ hours, minutes, remainingSeconds ]
			.map( ( value ) => String( value ).padStart( 2, '0' ) )
			.join( ':' );
	}

	function updateOperationTime( operation, timeElement ) {
		const terminalStatuses = [ 'completed', 'completed_with_errors', 'failed', 'cancelled' ];
		const isTerminal = terminalStatuses.includes( operation.status );
		const elapsedSeconds = isTerminal
			? operation.elapsed_seconds
			: Math.floor( ( window.performance.now() - state.operationStartedAt ) / 1000 );
		let remaining = DKBCE.i18n.estimatingTime;

		if ( isTerminal ) {
			remaining = [ 'completed', 'completed_with_errors' ].includes(
				operation.status
			)
				? formatDuration( 0 )
				: '—';
		} else if ( 'snapshot' !== operation.stage && operation.processed > 0 ) {
			remaining = formatDuration(
				( elapsedSeconds / operation.processed ) *
					Math.max( 0, operation.total - operation.processed )
			);
		}

		timeElement.replaceChildren();
		[
			[ DKBCE.i18n.elapsedTime, formatDuration( elapsedSeconds ) ],
			...( isTerminal ? [] : [ [ DKBCE.i18n.remainingTime, remaining ] ] ),
		].forEach( ( [ template, duration ] ) => {
			const card = document.createElement( 'span' );
			const parts = template.split( '%s' );
			const value = document.createElement( 'strong' );
			value.className = 'dkbce-duration';
			value.textContent = duration;
			card.append( document.createTextNode( parts[ 0 ] ), value,
				document.createTextNode( parts.slice( 1 ).join( '%s' ) ) );
			timeElement.appendChild( card );
		} );
	}

	function renderOperation( operation, operationPanel = $( '#dkbce-operation' ), historyView = false ) {
		const completionMessage = {
			completed: DKBCE.i18n.completedStatus,
			completed_with_errors: DKBCE.i18n.withErrorsStatus,
			failed: DKBCE.i18n.failedStatus,
			cancelled: DKBCE.i18n.cancelledStatus,
		};
		const focusedControl = operationPanel.contains( document.activeElement ) ? document.activeElement.dataset.operationControl : '';
		if ( ! historyView ) {
			state.operationStatus = operation.status; updateOperationControls();
			if ( state.mode === 'history' ) { updateHistoryProgressVisibility(); }
		}
		if ( ! historyView && null === state.operationStartedAt ) {
			state.operationStartedAt = window.performance.now() - operation.elapsed_seconds * 1000;
		}
		if ( ! historyView && state.operationTimer ) {
			window.clearInterval( state.operationTimer );
		}
		const terminalStatuses = [ 'completed', 'completed_with_errors', 'failed', 'cancelled' ];
		operationPanel.replaceChildren();
		const heading = document.createElement( 'h3' );
		const terminal = terminalStatuses.includes( operation.status );
		operationPanel.classList.toggle( 'is-history', terminal );
		operationPanel.dataset.task = operation.action === 'rollback' ? historyText.rollback : operation.action === 'csv_import' ? DKBCE.ui.importTask : DKBCE.ui.bulkTask;
		const delayed = ! terminal && operation.progress_age >= 120;
		const unavailable = ! terminal && ! operation.queue_available;
		if ( terminal ) {
			heading.textContent = completionMessage[ operation.status ];
		} else if ( unavailable || delayed || operation.status === 'pending' ) {
			heading.textContent = DKBCE.i18n.queued;
		} else if ( 'snapshot' === operation.stage ) {
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
		if ( ! terminal ) { heading.textContent = operationPanel.dataset.task + ' · ' + heading.textContent; }
		operationPanel.appendChild( heading );
		heading.setAttribute( 'role', 'status' );
		const checkpoint = document.createElement( 'p' );
		checkpoint.textContent = DKBCE.i18n.lastProgress.replace( '%s', new Date( operation.last_progress_at * 1000 ).toLocaleString() );
		operationPanel.appendChild( checkpoint );
		if ( ! terminal ) {
			if ( unavailable || delayed ) {
				const recovery = document.createElement( 'p' );
				recovery.textContent = unavailable ? DKBCE.i18n.queueUnavailable : DKBCE.i18n.queueDelayed;
				operationPanel.appendChild( recovery );
			}
			const refresh = document.createElement( 'button' );
			refresh.type = 'button';
			refresh.className = 'button';
			refresh.dataset.operationControl = 'refresh';
			refresh.textContent = DKBCE.i18n.refreshStatus;
			refresh.addEventListener( 'click', historyView ? loadHistoryDetails : pollProgress );
			operationPanel.appendChild( refresh );
		}
		if ( ! historyView ) { setApplySpinner( ! terminal && ! delayed && ! unavailable ); }

		const progress = document.createElement( 'progress' );
		progress.max = Math.max( 1, operation.total );
		progress.value = Math.min( operation.processed, progress.max );
		if ( operation.stage === 'snapshot' ) { progress.removeAttribute( 'value' ); }
		progress.setAttribute( 'aria-label', DKBCE.i18n.progressLabel );

		const text = document.createElement( 'p' );
		let percent = 100;
		if ( 'snapshot' === operation.stage ) {
			percent = 0;
		} else if ( operation.total ) {
			percent = Math.floor(
				( operation.processed / operation.total ) * 100
			);
		}
		text.textContent = DKBCE.i18n.progressSummary
			.replace( '%1$s', Number( operation.processed ).toLocaleString() )
			.replace( '%2$s', Number( operation.total ).toLocaleString() );
		if ( operation.stage === 'snapshot' ) {
			text.textContent = DKBCE.i18n.snapshotCount.replace( '%d', Number( operation.total ).toLocaleString() );
		}
		text.className = 'dkbce-progress-count';
		operationPanel.appendChild( text );
		const track = document.createElement( 'div' );
		track.className = 'dkbce-progress-track';
		track.classList.toggle( 'is-active', ! terminal && ! delayed && ! unavailable );
		const highlight = document.createElement( 'span' );
		highlight.className = 'dkbce-progress-highlight';
		highlight.style.width = Math.max( 0, Math.min( 100, percent ) ) + '%';
		highlight.setAttribute( 'aria-hidden', 'true' );
		const percentage = document.createElement( 'span' );
		percentage.className = 'dkbce-progress-percentage';
		percentage.textContent = percent + '%';
		percentage.hidden = operation.stage === 'snapshot';
		percentage.setAttribute( 'aria-hidden', 'true' );
		track.append( progress, highlight, percentage );
		operationPanel.appendChild( track );
		if (
			! delayed && ! unavailable &&
			'processing' === operation.stage &&
			[ 'pending', 'processing' ].includes( operation.status ) &&
			! operation.cancel_requested
		) {
			const backgroundMessage = document.createElement( 'p' );
			backgroundMessage.className = 'dkbce-background-message';
			backgroundMessage.textContent = DKBCE.i18n.backgroundMessage;
			operationPanel.appendChild( backgroundMessage );
		}
		const stats = document.createElement( 'div' );
		stats.className = 'dkbce-operation-stats';
		[
			[ 'succeeded', DKBCE.i18n.updatedLabel ],
			[ 'skipped', DKBCE.i18n.skippedLabel ],
			[ 'failed', DKBCE.i18n.failedLabel ],
		].forEach( ( [ key, label ] ) => {
			const card = document.createElement( 'div' );
			card.className = 'dkbce-stat is-' + key;
			const value = document.createElement( 'strong' );
			value.textContent = Number( operation[ key ] ).toLocaleString();
			const caption = document.createElement( 'span' );
			caption.textContent = label;
			card.append( value, caption );
			stats.appendChild( card );
		} );
		const metrics = document.createElement( 'div' );
		metrics.className = 'dkbce-operation-metrics';
		metrics.appendChild( stats );
		operationPanel.appendChild( metrics );
		const time = document.createElement( 'p' );
		time.className = 'dkbce-operation-time';
		time.setAttribute( 'aria-live', 'off' );
		metrics.appendChild( time );
		if ( historyView ) { time.textContent = DKBCE.i18n.elapsedTime.replace( '%s', formatDuration( operation.elapsed_seconds ) ); } else { updateOperationTime( operation, time ); }
		if ( ! historyView && ! terminalStatuses.includes( operation.status ) ) {
			state.operationTimer = window.setInterval(
				() => updateOperationTime( operation, time ),
				1000
			);
		}
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
		if ( ! historyView && 'cancelled' === operation.status ) {
			const clearButton = document.createElement( 'button' );
			clearButton.type = 'button';
			clearButton.className = 'button-link';
			clearButton.textContent = DKBCE.i18n.clearCancelled;
			clearButton.addEventListener( 'click', () => {
				clearButton.disabled = true;
				request( 'clear_cancelled', { operation_id: operation.operation_id } )
					.then( () => window.location.reload() )
					.catch( ( error ) => {
						clearButton.disabled = false;
						setNotice( error.message, 'error' );
					} );
			} );
			operationPanel.appendChild( clearButton );
		}
		if ( operation.import_source && [ 'completed', 'completed_with_errors', 'failed', 'cancelled' ].includes( operation.status ) ) {
			const report = document.createElement( 'button' );
			report.type = 'button';
			report.className = 'button';
			report.textContent = DKBCE.csv.report;
			report.addEventListener( 'click', () => downloadImportReport( operation.import_source, true, report ) );
			operationPanel.appendChild( report );
		}

		if ( operation.csv_report ) {
			const report = document.createElement( 'button' );
			report.type = 'button';
			report.className = 'button';
			report.textContent = DKBCE.csv.report;
			report.addEventListener( 'click', () => downloadCSV( [ operation.csv_report ], 'cogs-import-results.csv' ) );
			operationPanel.appendChild( report );
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
			cancelButton.dataset.operationControl = 'cancel';
			cancelButton.textContent = operation.cancel_requested
				? DKBCE.i18n.cancelling
				: operation.action === 'rollback' ? historyText.cancelRollback : DKBCE.i18n.cancelOperation;
			cancelButton.disabled = operation.cancel_requested;
			cancelButton.addEventListener( 'click', historyView ? async () => { cancelButton.disabled = true; try { await request( 'cancel', { operation_id: operation.operation_id } ); loadHistoryDetails(); } catch ( error ) { setNotice( error.message, 'error', operationPanel ); cancelButton.disabled = false; } } : cancelOperation );
			operationPanel.appendChild( cancelButton );
			const persistence = document.createElement( 'p' );
			persistence.textContent = DKBCE.ui.persistence;
			operationPanel.appendChild( persistence );
		} else if ( ! historyView ) {
			if ( ! historyView && 'completed' === operation.status ) {
				const refreshNotice = document.createElement( 'div' );
				refreshNotice.className = 'notice notice-success inline dkbce-refresh-notice';
				refreshNotice.setAttribute( 'role', 'status' );
				const refreshMessage = document.createElement( 'p' );
				const refreshLink = document.createElement( 'a' );
				refreshLink.href = window.location.href;
				refreshLink.textContent = DKBCE.i18n.refreshNow;
				refreshLink.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					if ( refreshLink.getAttribute( 'aria-busy' ) === 'true' ) {
						return;
					}
					refreshLink.setAttribute( 'aria-busy', 'true' );
					request( 'clear_operation', { operation_id: operation.operation_id } )
						.then( () => window.location.reload() )
						.catch( ( error ) => {
							refreshLink.removeAttribute( 'aria-busy' );
							setNotice( error.message, 'error' );
						} );
				} );
				refreshMessage.append( document.createTextNode( DKBCE.i18n.refreshing + ' ' ), refreshLink );
				refreshNotice.appendChild( refreshMessage );
				operationPanel.appendChild( refreshNotice );
			} else {
				setNotice(
					completionMessage[ operation.status ] ||
						DKBCE.i18n.operationStatus.replace(
							'%s',
							operation.status.replace( /_/g, ' ' )
						),
					operation.failed || 'failed' === operation.status
						? 'error'
						: 'info', operationPanel
				);
			}
		}
		if ( terminal ) {
			const details = document.createElement( 'details' );
			details.open = historyView;
			const summary = document.createElement( 'summary' );
			summary.textContent = DKBCE.ui.details;
			details.appendChild( summary );
			while ( operationPanel.firstChild ) { details.appendChild( operationPanel.firstChild ); }
			const title = document.createElement( 'h3' );
			title.textContent = DKBCE.ui.recent + ' · ' + operationPanel.dataset.task;
			const result = document.createElement( 'p' );
			result.textContent = DKBCE.ui.completed.replace( '%d', Number( operation.succeeded ).toLocaleString() ) + ' · ' + new Date( ( operation.completed_at || operation.last_progress_at ) * 1000 ).toLocaleString()
				+ ' · ' + DKBCE.i18n.elapsedTime.replace( '%s', formatDuration( operation.elapsed_seconds ) );
			const outcome = document.createElement( 'p' );
			outcome.className = 'dkbce-operation-outcome';
			outcome.classList.toggle( 'has-errors', operation.failed > 0 || operation.status === 'failed' );
			outcome.textContent = [
				operation.status === 'completed' ? '' : completionMessage[ operation.status ],
				operation.skipped > 0 ? Number( operation.skipped ).toLocaleString() + ' ' + DKBCE.i18n.skippedLabel : '',
				operation.failed > 0 ? Number( operation.failed ).toLocaleString() + ' ' + DKBCE.i18n.failedLabel : '',
			].filter( Boolean ).join( ' · ' );
			outcome.hidden = ! outcome.textContent;
			operationPanel.append( title, result, outcome, details );
		}
		if ( focusedControl ) { operationPanel.querySelector( '[data-operation-control="' + focusedControl + '"]' )?.focus(); }

	}

	function pollProgress() {
		if ( ! state.operationId ) {
			return;
		}
		if ( state.pollTimer ) {
			window.clearTimeout( state.pollTimer );
		}
		const requestId = ++state.progressRequestId;
		request( 'progress', { operation_id: state.operationId } )
			.then( ( data ) => {
				if ( requestId !== state.progressRequestId ) { return; }
				if ( state.progressError ) { setNotice( '', 'info' ); }
				state.progressError = false;
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
			.catch( () => {
				if ( requestId !== state.progressRequestId ) { return; }
				state.progressError = true;
				setApplySpinner( false );
				const heading = operationPanel.querySelector( 'h3' );
				if ( heading ) { heading.textContent = DKBCE.i18n.progressUnavailable; }
				if ( ! heading ) {
					const message = document.createElement( 'p' );
					message.textContent = DKBCE.i18n.progressUnavailable;
					operationPanel.replaceChildren( message );
				}
				const track = operationPanel.querySelector( '.dkbce-progress-track' );
				if ( track ) { track.classList.remove( 'is-active' ); }
				const background = operationPanel.querySelector( '.dkbce-background-message' );
				if ( background ) { background.remove(); }
				setNotice( DKBCE.i18n.progressUnavailable, 'error', operationPanel );
				if ( ! operationPanel.querySelector( '[data-operation-control="refresh"]' ) ) {
					const refresh = document.createElement( 'button' );
					refresh.type = 'button';
					refresh.className = 'button';
					refresh.dataset.operationControl = 'refresh';
					refresh.textContent = DKBCE.i18n.refreshStatus;
					refresh.addEventListener( 'click', pollProgress );
					operationPanel.appendChild( refresh );
				}
				state.pollTimer = window.setTimeout( pollProgress, 10000 );
			} );
	}

	function cancelOperation() {
		if ( ! state.operationId ) {
			return;
		}
		request( 'cancel', { operation_id: state.operationId } )
			.then( ( data ) => {
				state.progressRequestId++;
				renderOperation( data.state );
				if ( state.pollTimer ) {
					window.clearTimeout( state.pollTimer );
				}
				state.pollTimer = window.setTimeout( pollProgress, 2500 );
			} )
			.catch( ( error ) => setNotice( error.message, 'error', operationPanel ) );
	}

	getButton.addEventListener( 'click', getProducts );
	applyButton.addEventListener( 'click', openConfirm );
	previewContent.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( '.dkbce-product-select, .dkbce-select-page' ) ) {
			$( '#dkbce-selected-only' ).checked = true;
		}
		if ( event.target.matches( '.dkbce-product-select' ) ) {
			setProductSelected( event.target.value, event.target.checked );
			event.target.checked = productSelected( event.target.value );
			updatePageSelectionControls();
			updateOperationControls();
		} else if ( event.target.matches( '.dkbce-select-page' ) ) {
			setPageSelection( event.target.checked );
		}
	} );
	pageSizeSelect.addEventListener( 'change', () => {
		savePreferences();
		if ( state.previewId && ! state.busy && ! state.loadingPage ) {
			fetchPreviewPage( 1 );
		}
	} );
	$( '#dkbce-confirm-cancel' ).addEventListener( 'click', () => {
		modal.hidden = true;
		applyButton.focus();
	} );
	$( '#dkbce-confirm-apply' ).addEventListener( 'click', confirmAction );
	modal.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key ) {
			modal.hidden = true;
			applyButton.focus();
		}
		if ( 'Tab' === event.key ) {
			const focusable = Array.from( modal.querySelectorAll( 'button:not([disabled]), input:not([disabled])' ) )
				.filter( ( element ) => ! element.closest( '[hidden]' ) );
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
	function clearFilters() {
		document
			.querySelectorAll(
				'#dkbce-search, #dkbce-price-min, #dkbce-price-max, #dkbce-cogs-min, #dkbce-cogs-max'
			)
			.forEach( ( input ) => {
				input.value = '';
			} );
		document.querySelectorAll( '.dkbce-multi-select' ).forEach( ( select ) => {
			Array.from( select.options ).forEach( ( option ) => { option.selected = false; } );
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		$( '#dkbce-product-count' ).textContent = '';
		state.count = 0;
		invalidatePreview( true );
	}
	$( '#dkbce-clear-filters' ).addEventListener( 'click', clearFilters );
	$( '#dkbce-reset' ).addEventListener( 'click', () => { resetSelection(); clearFilters(); invalidatePreview( true, true ); } );
	document.querySelectorAll( '.dkbce-action-fields select' ).forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			actionLabel();
			invalidatePreview( false );
			updateOperationControls();
			schedulePreviewRefresh();
		} );
	} );
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
	$( '#dkbce-value' ).addEventListener( 'input', () => {
		actionLabel();
		invalidatePreview( false );
		schedulePreviewRefresh();
	} );
	$( '#dkbce-value' ).addEventListener( 'keyup', schedulePreviewRefresh );
	actionLabel();
	updateCSVMode();
	updateOperationControls();
	if ( DKBCE.operationMode === 'rollback' ) { state.mode = 'history'; updateCSVMode(); loadHistory(); }
	if ( state.importId || DKBCE.operationMode === 'csv_import' ) {
		state.mode = 'csv';
		state.editorMode = 'csv';
		$( 'input[name="dkbce-csv-action"][value="import"]' ).checked = true;
		updateCSVMode();
	}
	if ( state.importId ) {
		state.operationId = '';
		state.importPreparing = true;
		pollImport();
	} else if ( state.operationId ) {
		operationPanel.hidden = false;
		pollProgress();
	}
} )();
