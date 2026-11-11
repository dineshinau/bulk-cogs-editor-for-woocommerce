<?php
/**
 * Creates and upgrades plugin database tables.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies database changes when the stored schema version is behind the code.
 */
class DKBCE_Database_Installer {
	/**
	 * Create or update plugin tables when a newer database version is available.
	 *
	 * @return void
	 * @throws RuntimeException When a required table cannot be created or updated.
	 */
	public static function maybe_upgrade() {
		global $wpdb;

		$db_version = get_option( 'dkbce_db_version', '0.0.0' );
		if ( ! version_compare( DKBCE_DB_VERSION, $db_version, '>' ) ) {
			return;
		}

		// Repair the early preset schema without changing any saved names or rules.
		$preset_table = $wpdb->prefix . 'dkbce_presets';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $preset_table ) ) ) === $preset_table ) {
			$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $preset_table, 'owner_name' ), 4 );
			if ( in_array( 'name_key', $columns, true ) ) {
				$result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX owner_name, ADD UNIQUE KEY owner_name (user_id,name), DROP COLUMN name_key', $preset_table ) );
				if ( false === $result ) {
					throw new RuntimeException( esc_html__( 'The preset name index could not be updated.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
			}
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$tables  = array(
			$wpdb->prefix . 'dkbce_presets'            => "CREATE TABLE {$wpdb->prefix}dkbce_presets (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint unsigned NOT NULL,
				name varchar(60) NOT NULL,
				rules longtext NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				revision varchar(36) NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY owner_name (user_id,name),
				KEY owner_created (user_id,created_at)
			) $collate;",

			$wpdb->prefix . 'dkbce_history'            => "CREATE TABLE {$wpdb->prefix}dkbce_history (
				operation_id varchar(36) NOT NULL,
				created_at bigint unsigned NOT NULL,
				user_id bigint unsigned NOT NULL,
				action varchar(32) NOT NULL,
				status varchar(32) NOT NULL,
				note text NOT NULL,
				PRIMARY KEY  (operation_id),
				KEY created (created_at,operation_id),
				KEY owner (user_id,created_at)
			) $collate;",
			$wpdb->prefix . 'dkbce_history_products'   => "CREATE TABLE {$wpdb->prefix}dkbce_history_products (
				operation_id varchar(36) NOT NULL,
				product_id bigint unsigned NOT NULL,
				original_value varchar(64) DEFAULT NULL,
				target_value varchar(64) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'prepared',
				PRIMARY KEY  (operation_id,product_id),
				KEY product (product_id)
			) $collate;",
			$wpdb->prefix . 'dkbce_import_rows'        => "CREATE TABLE {$wpdb->prefix}dkbce_import_rows (
				job_id varchar(36) NOT NULL,
				row_type tinyint unsigned NOT NULL DEFAULT 0,
				row_no bigint unsigned NOT NULL,
				product_id bigint unsigned NOT NULL DEFAULT 0,
				value longtext NOT NULL,
				sku text NOT NULL,
				error text NOT NULL,
				selected tinyint unsigned NOT NULL DEFAULT 1,
				result_status varchar(20) NOT NULL DEFAULT '',
				result_message text NOT NULL,
				PRIMARY KEY  (job_id,row_type,row_no),
				KEY product (job_id,row_type,product_id),
				KEY selection (job_id,row_type,selected,row_no)
			) $collate;",
			$wpdb->prefix . 'dkbce_operation_outcomes' => "CREATE TABLE {$wpdb->prefix}dkbce_operation_outcomes (
				operation_id varchar(36) NOT NULL,
				product_id bigint unsigned NOT NULL,
				status varchar(20) NOT NULL,
				message text NOT NULL,
				PRIMARY KEY  (operation_id,product_id)
			) $collate;",
		);

		foreach ( $tables as $table_name => $schema ) {
			dbDelta( $schema );

			// Verify the private table after dbDelta.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
				throw new RuntimeException( esc_html__( 'Bulk COGS Editor database tables could not be created or updated.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		update_option( 'dkbce_db_version', DKBCE_DB_VERSION, false );
		delete_option( 'dkbce_import_schema' );
		delete_option( 'dkbce_operation_outcomes_schema' );
	}
}
