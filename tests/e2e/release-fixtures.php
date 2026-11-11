<?php
/**
 * Inspect and exercise disposable release fixtures through WP-CLI.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

$fixture_path = getenv( 'DKBCE_E2E_STATE_FILE' );
$fixture_data = json_decode( file_get_contents( $fixture_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Private test state.
$input        = json_decode( getenv( 'DKBCE_E2E_RELEASE_INPUT' ), true );
$actor        = get_userdata( $fixture_data['manager_id'] ?? 0 );
if ( ! $actor || 'dkbce_e2e_manager_' . ( $fixture_data['token'] ?? '' ) !== $actor->user_login ) {
	WP_CLI::error( 'Disposable release actor is unavailable.' );
}
wp_set_current_user( $actor->ID );
$store        = new DKBCE_Operation_Store();
$service      = new DKBCE_COGS_Service();
$processor    = new DKBCE_Bulk_Processor( $store, $service );
$release_mode = $input['mode'] ?? '';
$output       = array();

if ( 'seed' === $release_mode ) {
	$release_id = wp_generate_uuid4();
	$state      = array(
		'operation_id'       => $release_id,
		'user_id'            => $actor->ID,
		'created_at'         => time(),
		'updated_at'         => time(),
		'started_at'         => time(),
		'completed_at'       => time(),
		'action'             => 'set',
		'value'              => '15.00',
		'operation'          => array(
			'action' => 'set',
			'value'  => '15.00',
		),
		'filters'            => $service->validate_filters( array( 'search' => 'DKBCE Release ' . $fixture_data['token'] ) ),
		'status'             => 'completed',
		'stage'              => 'processing',
		'total'              => 3,
		'processed'          => 3,
		'succeeded'          => 3,
		'skipped'            => 0,
		'failed'             => 0,
		'snapshot_chunks'    => 0,
		'processed_chunks'   => 0,
		'batch_size'         => 50,
		'reusable_selection' => true,
		'cancel_requested'   => false,
		'errors'             => array(),
		'error_summary'      => '',
	);
	if ( 'active' === ( $input['kind'] ?? '' ) || 'import' === ( $input['kind'] ?? '' ) ) {
		$state['status']             = 'pending';
		$state['completed_at']       = 0;
		$state['processed']          = 0;
		$state['succeeded']          = 0;
		$state['reusable_selection'] = false;
		if ( 'import' === $input['kind'] ) {
			$state['action']    = 'import_preview';
			$state['operation'] = array(
				'action' => 'import_preview',
				'value'  => '',
			);
			$state['stage']     = 'import_rows';
			$state['format']    = 'csv';
			$state['file']      = wp_tempnam( 'dkbce-release-import' );
			$state['file_size'] = 100;
			$state['offset']    = 0;
			$state['cursor']    = 0;
			$state['header']    = array();
			update_user_meta( $actor->ID, 'dkbce_latest_import', $release_id );
		} else {
			update_user_meta( $actor->ID, 'dkbce_latest_operation', $release_id );
		}
	} else {
		$real_ids = array_slice( $fixture_data['release_ids'], 0, 3 );
		foreach ( $real_ids as $pid ) {
			$store->save_outcome(
				$release_id,
				$pid,
				array(
					'status'  => 'success',
					'message' => '',
				)
			);
		}
		for ( $index = 3; $index < (int) ( $input['outcomes'] ?? 3 ); ++$index ) {
			// Metadata-only IDs exercise bounded cleanup without touching products.
			$store->save_outcome(
				$release_id,
				900000000 + $index,
				array(
					'status'  => 'success',
					'message' => '',
				)
			);
		}
	}
	$store->save( $state );
	$output = array( 'id' => $release_id );
} elseif ( 'costs' === $release_mode || 'rounding' === $release_mode ) {
	$release_ids = $input['ids'] ?? array( $fixture_data['release_ids'][0] );
	foreach ( $release_ids as $pid ) {
		if ( ! in_array( (int) $pid, $fixture_data['product_ids'], true ) ) {
			WP_CLI::error( 'Only disposable product IDs may be inspected.' );
		}
		$product        = wc_get_product( $pid );
		$output[ $pid ] = array(
			'cogs'  => $product->get_cogs_value(),
			'price' => $product->get_regular_price(),
		);
	}
	if ( 'rounding' === $release_mode ) {
		$csv    = new DKBCE_CSV_Service();
		$output = array();
		foreach ( array( 0, 2, 3 ) as $precision ) {
			$filter = static function () use ( $precision ) {
				return $precision;
			};
			add_filter( 'wc_get_price_decimals', $filter );
			$output[ $precision ] = $csv->validate_row( array( (string) $release_ids[0], '12.345678901234567' ), array( 'id', 'new_cogs' ), 2, $actor->ID, $service );
			remove_filter( 'wc_get_price_decimals', $filter );
		}
	}
} elseif ( 'current' === $release_mode ) {
	$current_id = get_user_meta( $actor->ID, 'dkbce_latest_operation', true );
	$current    = $current_id ? $store->get( $current_id, true ) : false;
	$output     = $current && (int) $actor->ID === (int) $current['user_id'] ? array(
		'id'     => $current_id,
		'status' => $current['status'],
	) : array();
} elseif ( 'clear_current' === $release_mode ) {
	delete_user_meta( $actor->ID, 'dkbce_latest_operation' );
	delete_user_meta( $actor->ID, 'dkbce_latest_import' );
	delete_user_meta( $actor->ID, 'dkbce_display_preferences' );
	$output = array( 'cleared' => true );
} elseif ( 'xlsx' === $release_mode ) {
	$file = wp_tempnam( 'dkbce-release-xlsx' );
	$zip  = new ZipArchive();
	$zip->open( $file, ZipArchive::OVERWRITE );
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/></Types>' );
	$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Release" sheetId="1" r:id="rId1"/></sheets></workbook>' );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>' );
	$strings = '<sst xmlns="' . $ns . '"><si><t>id</t></si><si><t>new_cogs</t></si>';
	for ( $i = 0; $i < ( ! empty( $input['formula'] ) ? 0 : 3000 ); ++$i ) {
		$strings .= '<si><t>Release string ' . $i . '</t></si>';
	}
	$zip->addFromString( 'xl/sharedStrings.xml', $strings . '</sst>' );
	$sheet = '<worksheet xmlns="' . $ns . '"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>';
	for ( $i = 2; $i <= ( ! empty( $input['formula'] ) ? 1 : 1080 ); ++$i ) {
		$sheet .= '<row r="' . $i . '"/>';
	}
	foreach ( $fixture_data['release_ids'] as $index => $pid ) {
		$row    = 1081 + $index;
		$value  = ! empty( $input['formula'] ) && 0 === $index ? '<f>1+1</f><v>2</v>' : '<v>12.3456789</v>';
		$sheet .= '<row r="' . $row . '"><c r="A' . $row . '"><v>' . $pid . '</v></c><c r="B' . $row . '">' . $value . '</c></row>';
	}
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet . '</sheetData></worksheet>' );
	$zip->close();
	$output = array( 'base64' => base64_encode( file_get_contents( $file ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport a generated disposable workbook to the browser test.
	wp_delete_file( $file );
} else {
	$release_id = $input['id'] ?? '';
	$state      = $store->get( $release_id, true );
	if ( ! $state || (int) $state['user_id'] !== (int) $actor->ID ) {
		WP_CLI::error( 'Only operations owned by the disposable release actor may be exercised.' );
	}
	if ( 'patch' === $release_mode ) {
		foreach ( $input['fields'] as $key => $value ) {
			if ( ! in_array( $key, array( 'history_cleanup_pending', 'history_cleanup_started', 'cleanup_pending', 'completed_at' ), true ) ) {
				WP_CLI::error( 'Unsupported test state field.' );
			}
			$state[ $key ] = $value;
		}
		$store->save( $state );
	} elseif ( 'drive' === $release_mode ) {
		$processor->process_operation( $release_id );
	} elseif ( 'cleanup_probe' === $release_mode ) {
		$checks = array();
		$probe  = static function ( $query ) use ( &$checks, $release_id, $store ) {
			if ( 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, 'dkbce_operation_outcomes' ) && false !== strpos( $query, $release_id ) ) {
				$checks[] = ! empty( $store->get( $release_id, true )['history_cleanup_started'] );
			}
			return $query;
		};
		add_filter( 'query', $probe );
		$processor->cleanup_operation( $release_id );
		remove_filter( 'query', $probe );
		$output['marker_checks'] = $checks;
	} elseif ( 'race_preview' === $release_mode ) {
		add_filter(
			'posts_request',
			static function ( $sql, $query ) use ( $release_id, $store ) {
				if ( $query->get( 'dkbce_selection_operation' ) === $release_id ) {
					$source                            = $store->get( $release_id, true );
					$source['history_cleanup_pending'] = true;
					$source['history_cleanup_started'] = true;
					$store->save( $source );
				}
				return $sql;
			},
			10,
			2
		);
		$_POST    = array(
			'nonce'                 => wp_create_nonce( DKBCE_Admin_Functions::NONCE ),
			'previous_operation_id' => $release_id,
			'filters'               => array( 'search' => 'DKBCE Release ' . $fixture_data['token'] ),
			'action_type'           => 'set',
			'action_value'          => '15',
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Supply the nonce-bearing request to the real AJAX handler under test.
		DKBCE_Admin_Functions::get_instance()->ajax_preview();
	}
	$state             = $store->get( $release_id, true );
	$output['state']   = $state;
	$output['summary'] = $store->summarize( $release_id, $state['snapshot_chunks'] ?? 0 );
}
WP_CLI::line( wp_json_encode( $output ) );
