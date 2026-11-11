<?php
/**
 * Per-user product filter presets.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores presets in the current site's private table.
 */
class DKBCE_Preset_Store {
	/**
	 * List the authenticated user's presets.
	 *
	 * @return array
	 * @throws RuntimeException When preset rows cannot be read.
	 */
	public function all() {
		global $wpdb;
		DKBCE_Database_Installer::maybe_upgrade();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, rules, created_at, updated_at, revision FROM %i WHERE user_id = %d ORDER BY created_at DESC, id DESC', $wpdb->prefix . 'dkbce_presets', get_current_user_id() ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			throw new RuntimeException( esc_html__( 'Could not load presets.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		foreach ( $rows as &$row ) {
			$row['id']      = (int) $row['id'];
			$row['filters'] = json_decode( $row['rules'], true );
			unset( $row['rules'] );
		}
		return $rows;
	}

	/**
	 * Save a new preset or update an owned preset with a matching revision.
	 *
	 * @param string $name Unique name.
	 * @param array  $filters Validated product rules.
	 * @param int    $id Existing preset ID, or zero.
	 * @param string $revision Expected revision for edits.
	 * @return true|WP_Error
	 */
	public function save( $name, $filters, $id = 0, $revision = '' ) {
		global $wpdb;
		DKBCE_Database_Installer::maybe_upgrade();
		$table     = $wpdb->prefix . 'dkbce_presets';
		$owner     = get_current_user_id();
		$duplicate = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND name = %s AND id <> %d', $table, $owner, $name, $id ) );
		if ( $duplicate ) {
			return new WP_Error( 'duplicate_preset', __( 'You already have a preset with this name. Choose a unique name.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$data = array(
			'name'       => $name,
			'rules'      => wp_json_encode( $filters ),
			'updated_at' => current_time( 'mysql', true ),
			'revision'   => wp_generate_uuid4(),
		);
		if ( $id ) {
			$result = $wpdb->update(
				$table,
				$data,
				array(
					'id'       => $id,
					'user_id'  => $owner,
					'revision' => $revision,
				)
			);
		} else {
			$data['user_id']    = $owner;
			$data['created_at'] = $data['updated_at'];
			$result             = $wpdb->insert( $table, $data );
		}
		if ( ! $result ) {
			return new WP_Error( 'preset_save_failed', __( 'Could not save the preset. Its name may already exist or it may have changed. Reload Presets and try again.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return true;
	}

	/**
	 * Delete selected presets belonging to the authenticated user.
	 *
	 * @param array $ids Preset IDs.
	 * @return true|WP_Error
	 */
	public function delete( $ids ) {
		global $wpdb;
		foreach ( $ids as $id ) {
			if ( false === $wpdb->delete(
				$wpdb->prefix . 'dkbce_presets',
				array(
					'id'      => absint( $id ),
					'user_id' => get_current_user_id(),
				),
				array( '%d', '%d' )
			) ) {
				return new WP_Error( 'preset_delete_failed', __( 'Could not delete presets. Reload the list and try again.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}
		return true;
	}
}
