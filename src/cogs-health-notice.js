/* global DKBCEHealth */
document.addEventListener( 'click', ( event ) => {
	if ( ! event.target.closest( '#dkbce-health-notice .notice-dismiss' ) ) { return; }
	const data = new FormData();
	data.append( 'action', 'dkbce_dismiss_health' );
	data.append( 'nonce', DKBCEHealth.nonce );
	fetch( DKBCEHealth.url, { method: 'POST', credentials: 'same-origin', body: data, keepalive: true } ).catch( () => {} );
}, true );
