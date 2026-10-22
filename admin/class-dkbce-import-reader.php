<?php
/**
 * Streaming CSV and XLSX input.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read bounded batches without loading a workbook into memory.
 */
class DKBCE_Import_Reader {
	/**
	 * Bounded shared-string cache for the current XLSX batch request.
	 *
	 * @var array
	 */
	private $shared_string_cache = array();

	/**
	 * Read the next CSV records using a durable byte cursor.
	 *
	 * @param array              $state Import state, updated after durable rows.
	 * @param DKBCE_Import_Store $store Staging storage.
	 * @param DKBCE_COGS_Service $service COGS calculations.
	 * @param float              $deadline Cooperative deadline.
	 * @return bool Whether EOF was reached.
	 * @throws RuntimeException When the CSV is not readable UTF-8.
	 */
	public function csv_batch( &$state, $store, $service, $deadline ) {
		$file = new SplFileObject( $state['file'], 'rb' );
		$file->fseek( $state['offset'] );
		$csv = new DKBCE_CSV_Service();
		for ( $count = 0; $count < $state['batch_size']; ++$count ) {
			if ( microtime( true ) >= $deadline ) {
				return false;
			}
			$start  = $file->ftell();
			$fields = $file->fgetcsv( ',', '"', '' );
			$end    = $file->ftell();
			if ( false === $fields || array( null ) === $fields ) {
				$state['offset'] = $end;
				if ( $file->eof() ) {
					return true;
				}
				continue;
			}
			$file->fseek( $start );
			$raw = $file->fread( $end - $start );
			if ( 0 === $start ) {
				$raw    = preg_replace( '/\A\xEF\xBB\xBF/', '', $raw );
				$fields = str_getcsv( $raw, ',', '"', '' );
			}
			if ( false !== strpos( $raw, "\0" ) || ! preg_match( '//u', $raw ) || ! $csv->valid_quoting( $raw ) ) {
				throw new RuntimeException( esc_html__( 'Use UTF-8 CSV with valid double-quoted fields. Other CSV encodings are not supported.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			$line = $state['cursor'] + 1;
			if ( ! $state['header'] ) {
				$state['header'] = $csv->header( $fields );
			} else {
				$store->put( $state['operation_id'], $csv->validate_row( $fields, $state['header'], $line, $state['user_id'], $service ) );
				++$state['processed'];
			}
			$state['cursor'] = $line;
			$state['offset'] = $end;
		}
		return $file->eof();
	}

	/**
	 * Discover first worksheet and shared strings from workbook relationships.
	 *
	 * @param array $state Import state.
	 * @return void
	 * @throws RuntimeException When XLSX support or workbook structure is missing.
	 */
	public function prepare_xlsx( &$state ) {
		if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'XMLReader' ) || ! class_exists( 'DOMDocument' ) ) {
			throw new RuntimeException( esc_html__( 'XLSX imports require the PHP ZIP, XMLReader and DOM extensions.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $state['file'] ) ) {
			throw new RuntimeException( esc_html__( 'This file is not a readable XLSX workbook.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$zip->close();
		$reader       = $this->xml( $state['file'], 'xl/workbook.xml' );
		$relationship = '';
		try {
			while ( $this->read( $reader ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
				if ( XMLReader::ELEMENT === $reader->nodeType && 'sheet' === $reader->localName ) {
					$state['sheet_name'] = $reader->getAttribute( 'name' );
					if ( $reader->moveToFirstAttribute() ) {
						do {
							// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
							if ( 'id' === $reader->localName ) {
								$relationship = $reader->value;
							}
						} while ( $reader->moveToNextAttribute() );
					}
					break;
				}
			}
		} finally {
			$reader->close();
		}
		$reader = $this->xml( $state['file'], 'xl/_rels/workbook.xml.rels' );
		try {
			while ( $this->read( $reader ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
				if ( XMLReader::ELEMENT !== $reader->nodeType || 'Relationship' !== $reader->localName ) {
					continue;
				}
				$type = $reader->getAttribute( 'Type' );
				$id   = $reader->getAttribute( 'Id' );
				if ( $id !== $relationship && '/sharedStrings' !== substr( $type, -14 ) ) {
					continue;
				}
				if ( 'External' === $reader->getAttribute( 'TargetMode' ) ) {
					throw new RuntimeException( esc_html__( 'External worksheet relationships are not supported.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
				$target = $reader->getAttribute( 'Target' );
				$target = '/' === substr( $target, 0, 1 ) ? ltrim( $target, '/' ) : 'xl/' . $target;
				if ( ! preg_match( '/\A[a-zA-Z0-9_.\/-]+\z/', $target ) || false !== strpos( $target, '..' ) ) {
					throw new RuntimeException( esc_html__( 'Invalid worksheet path in XLSX file.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
				if ( $id === $relationship && '/worksheet' === substr( $type, -10 ) ) {
					$state['sheet'] = $target;
				} elseif ( '/sharedStrings' === substr( $type, -14 ) ) {
					$state['strings'] = $target;
				}
			}
		} finally {
			$reader->close();
		}
		if ( empty( $state['sheet'] ) ) {
			throw new RuntimeException( esc_html__( 'The first XLSX sheet is not a readable worksheet.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$worksheet = $this->xml( $state['file'], $state['sheet'] );
		try {
			while ( $this->read( $worksheet ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native XMLReader properties.
				if ( XMLReader::ELEMENT !== $worksheet->nodeType || 'dimension' !== $worksheet->localName ) {
					continue;
				}
				$dimension = $worksheet->getAttribute( 'ref' );
				if ( preg_match( '/\A[A-Z]+[1-9][0-9]*(?::[A-Z]+([1-9][0-9]*))?\z/', $dimension, $match ) ) {
					$last_row                 = isset( $match[1] ) ? (int) $match[1] : (int) preg_replace( '/\A[A-Z]+/', '', $dimension );
					$state['estimated_total'] = max( 0, $last_row - 1 );
				}
				break;
			}
		} finally {
			$worksheet->close();
		}
		$state['stage'] = empty( $state['strings'] ) ? 'import_rows' : 'import_strings';
	}

	/**
	 * Stream the next XLSX batch; skipped nodes are never materialized.
	 *
	 * @param array              $state Durable import cursor.
	 * @param DKBCE_Import_Store $store Row and shared-string storage.
	 * @param DKBCE_COGS_Service $service COGS calculations.
	 * @param float              $deadline Cooperative deadline.
	 * @return bool Whether the entire worksheet has been validated.
	 * @throws RuntimeException When a worksheet row cannot be decoded.
	 */
	public function xlsx_batch( &$state, $store, $service, $deadline ) {
		$strings = 'import_strings' === $state['stage'];
		if ( ! $strings ) {
			$this->shared_string_cache = array();
		}
		$reader   = $this->xml( $state['file'], $strings ? $state['strings'] : $state['sheet'] );
		$tag      = $strings ? 'si' : 'row';
		$index    = 0;
		$count    = 0;
		$max_rows = max( 1, absint( $state['batch_size'] ) ) * 4;
		$csv      = new DKBCE_CSV_Service();
		try {
			while ( $this->read( $reader ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
				if ( XMLReader::ELEMENT !== $reader->nodeType || $tag !== $reader->localName ) {
					continue;
				}
				++$index;
				if ( $index <= $state['cursor'] ) {
					continue;
				}
				$node = $reader->expand();
				if ( ! $node ) {
					throw new RuntimeException( esc_html__( 'An XLSX row could not be read.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
				if ( $strings ) {
					$store->put(
						$state['operation_id'],
						array(
							'line'  => $index - 1,
							'value' => $this->text( $node ),
						),
						1
					);
				} else {
					$reference = $node->getAttribute( 'r' );
					$line      = '' === $reference ? ( $state['sheet_row'] ?? 0 ) + 1 : (int) $reference;
					if ( ( '' !== $reference && ! preg_match( '/\A[1-9][0-9]*\z/', $reference ) ) || $line <= ( $state['sheet_row'] ?? 0 ) ) {
						throw new RuntimeException( esc_html__( 'Invalid or repeated XLSX row number.', 'bulk-cogs-editor-for-woocommerce' ) );
					}
					$state['sheet_row'] = $line;
					$fields             = $this->cells( $node, $state, $store );
					if ( $fields ) {
						if ( ! $state['header'] ) {
							$state['header'] = $csv->header( $fields );
						} else {
							$fields = array_pad( $fields, count( $state['header'] ), '' );
							$store->put( $state['operation_id'], $csv->validate_row( $fields, $state['header'], $line, $state['user_id'], $service ) );
							++$state['processed'];
						}
					}
				}
				$state['cursor'] = $index;
				++$count;
				unset( $node );
				if ( $count >= $max_rows || microtime( true ) >= $deadline ) {
					return false;
				}
			}
		} finally {
			$reader->close();
		}
		if ( $strings ) {
			$state['cursor'] = 0;
			$state['stage']  = 'import_rows';
			return false;
		}
		return true;
	}

	/**
	 * Open a known ZIP member without extracting arbitrary archive paths.
	 *
	 * @param string $path Private uploaded file.
	 * @param string $part Validated package member.
	 * @return XMLReader
	 * @throws RuntimeException When XML cannot be opened.
	 */
	private function xml( $path, $part ) {
		$reader = new XMLReader();
		if ( ! $reader->open( 'zip://' . $path . '#' . $part, null, LIBXML_NONET | LIBXML_COMPACT ) ) {
			throw new RuntimeException( esc_html__( 'The XLSX XML content could not be opened.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$reader->setParserProperty( XMLReader::LOADDTD, false );
		$reader->setParserProperty( XMLReader::SUBST_ENTITIES, false );
		return $reader;
	}

	/**
	 * Advance an XML cursor while rejecting DTDs and malformed XML.
	 *
	 * @param XMLReader $reader XML cursor.
	 * @return bool
	 * @throws RuntimeException When XML is invalid or contains entity declarations.
	 */
	private function read( $reader ) {
		$next = $reader->read();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
		if ( XMLReader::DOC_TYPE === $reader->nodeType || XMLReader::ENTITY_REF === $reader->nodeType || libxml_get_last_error() ) {
			throw new RuntimeException( esc_html__( 'Invalid XLSX XML. DTDs and external entities are not supported.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $next;
	}

	/**
	 * Combine rich-text runs, excluding phonetic annotations.
	 *
	 * @param DOMElement $node String container.
	 * @return string
	 */
	private function text( $node ) {
		$text = '';
		foreach ( $node->getElementsByTagNameNS( '*', 't' ) as $part ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
			if ( 'rPh' !== $part->parentNode->localName ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
				$text .= $part->textContent;
			}
		}
		return $text;
	}

	/**
	 * Decode sparse worksheet cells using persisted shared strings.
	 *
	 * @param DOMElement         $node Row element.
	 * @param array              $state Import state.
	 * @param DKBCE_Import_Store $store String storage.
	 * @return array
	 * @throws RuntimeException When cells reference missing strings or contain formulas.
	 */
	private function cells( $node, $state, $store ) {
		$fields = array();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
		foreach ( $node->childNodes as $cell ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
			if ( ! ( $cell instanceof DOMElement ) || 'c' !== $cell->localName ) {
				continue;
			}
			$reference = $cell->getAttribute( 'r' );
			$column    = count( $fields );
			if ( preg_match( '/\A([A-Z]+)[1-9][0-9]*\z/', $reference, $match ) ) {
				$column = 0;
				foreach ( str_split( $match[1] ) as $letter ) {
					$column = $column * 26 + ord( $letter ) - 64;
				}
				--$column;
			}
			if ( ( '' !== $reference && ! preg_match( '/\A[A-Z]+[1-9][0-9]*\z/', $reference ) ) || $column < 0 || $column >= 16384 ) {
				throw new RuntimeException( esc_html__( 'Invalid XLSX column reference.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			$type  = $cell->getAttribute( 't' );
			$value = '';
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
			foreach ( $cell->childNodes as $child ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
				if ( 'f' === $child->localName ) {
					throw new RuntimeException( esc_html__( 'Replace spreadsheet formulas with values before importing.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
				if ( 'v' === $child->localName ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM/XMLReader property.
					$value = $child->textContent;
				}
			}
			if ( 's' === $type ) {
				$string_id = ctype_digit( $value ) ? (int) $value : -1;
				if ( array_key_exists( $string_id, $this->shared_string_cache ) ) {
					$value = $this->shared_string_cache[ $string_id ];
				} else {
					$value = -1 < $string_id ? $store->value( $state['operation_id'], $string_id, 1 ) : null;
					if ( null !== $value ) {
						if ( 512 <= count( $this->shared_string_cache ) ) {
							unset( $this->shared_string_cache[ array_key_first( $this->shared_string_cache ) ] );
						}
						$this->shared_string_cache[ $string_id ] = $value;
					}
				}
				if ( null === $value ) {
					throw new RuntimeException( esc_html__( 'The XLSX file references a missing shared string.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
			} elseif ( 'inlineStr' === $type ) {
				$value = $this->text( $cell );
			} elseif ( 'e' === $type || 'b' === $type ) {
				$value = '#INVALID';
			}
			$fields            = array_pad( $fields, $column + 1, '' );
			$fields[ $column ] = $value;
		}
		while ( $fields && '' === end( $fields ) ) {
			array_pop( $fields );
		}
		return $fields;
	}
}
