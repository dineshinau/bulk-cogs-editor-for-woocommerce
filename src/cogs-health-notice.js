/* global DKBCEHealth */
document.addEventListener( 'click', ( event ) => {
	if ( ! event.target.closest( '#dkbce-health-notice .notice-dismiss' ) ) { return; }
	const data = new FormData();
	data.append( 'action', 'dkbce_dismiss_health' );
	data.append( 'nonce', DKBCEHealth.nonce );
	fetch( DKBCEHealth.url, { method: 'POST', credentials: 'same-origin', body: data, keepalive: true } ).catch( () => {} );
}, true );

document.addEventListener( 'click', async ( event ) => {
	const button = event.target.closest( '#dkbce-health-notice .dkbce-refresh-health' );
	if ( ! button || button.disabled ) { return; }
	event.preventDefault();
	const notice = button.closest( '#dkbce-health-notice' );
	const status = notice.querySelector( '.dkbce-health-status' );
	const started = Date.now();
	const buttonLabel = button.textContent;
	const spinner = document.createElement( 'span' );
	spinner.className = 'spinner is-active dkbce-health-spinner';
	spinner.setAttribute( 'aria-hidden', 'true' );
	spinner.style.float = 'none';
	spinner.style.margin = '0 4px';
	spinner.style.verticalAlign = 'middle';
	button.disabled = true;
	button.setAttribute( 'aria-busy', 'true' );
	button.textContent = DKBCEHealth.refreshing;
	button.after( spinner );
	status.textContent = DKBCEHealth.refreshing;
	const request = async ( mode, scanId = '' ) => {
		const form = new FormData();
		form.append( 'action', 'dkbce_refresh_health' );
		form.append( 'nonce', DKBCEHealth.refreshNonce );
		form.append( 'mode', mode );
		form.append( 'scan_id', scanId );
		const response = await fetch( DKBCEHealth.url, { method: 'POST', credentials: 'same-origin', body: form } );
		let payload;
		try { payload = await response.json(); } catch { throw new Error( DKBCEHealth.failed ); }
		if ( ! response.ok || ! payload.success ) { throw new Error( payload.data?.message || DKBCEHealth.failed ); }
		return payload.data;
	};
	try {
		const scan = await request( 'refresh' );
		while ( notice.isConnected ) {
			await new Promise( resolve => window.setTimeout( resolve, 3000 ) );
			if ( ! notice.isConnected ) { return; }
			if ( Date.now() - started > 15 * 60 * 1000 ) { throw new Error( DKBCEHealth.failed ); }
			const result = await request( 'status', scan.scan_id );
			if ( ! result.ready ) { continue; }
			if ( result.clear ) { notice.remove(); }
			else { notice.querySelector( 'p' ).innerHTML = result.message; }
			return;
		}
	} catch ( error ) {
		if ( notice.isConnected ) {
			status.textContent = error.message || DKBCEHealth.failed;
			spinner.remove();
			button.textContent = buttonLabel;
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
		}
	}
} );
