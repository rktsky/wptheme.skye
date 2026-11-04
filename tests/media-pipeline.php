<?php
/**
 * Exercise the actual generic media pipeline with native Core, GD and isolated SQL.
 *
 * Backported from the completed Website suite at 6fecb117; project layout hints
 * are excluded and test-only native sizes now exercise the generic registration.
 *
 * Run: SKYE_WORDPRESS_CORE=/absolute/wordpress php tests/media-pipeline.php
 * This program never bootstraps a site, contacts a server or reads real uploads.
 * SQLite executes the production predicates; only MySQL dialect is translated.
 * It does not establish MySQL/InnoDB or Redis integration coverage.
 * A separate hosting integration must use disposable native JPEG/PNG uploads,
 * simultaneous worker processes, native edit/regenerate/delete operations and
 * the actual external Cron runner. This local program never performs that work.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_schedule_single_event/
 * @see https://developer.wordpress.org/reference/functions/wp_get_image_editor/
 * @see https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/
 * @see https://www.php.net/manual/en/function.imagedestroy.php GD objects use normal object lifetime.
 * @since 3.0.0
 */

namespace {
	if ( 'cli' !== PHP_SAPI ) {
		http_response_code( 403 );
		exit;
	}

	error_reporting( E_ALL );

	/**
	 * Promote PHP notices to failures so malformed metadata cannot pass silently.
	 *
	 * @param int    $severity PHP error severity.
	 * @param string $message PHP diagnostic.
	 * @param string $file Source file.
	 * @param int    $line Source line.
	 * @return bool Whether the diagnostic was handled.
	 * @throws ErrorException When the diagnostic is enabled.
	 * @since 3.0.0
	 */
	function skye_media_test_error( $severity, $message, $file, $line ) {

		if ( error_reporting() & $severity ) {
			throw new ErrorException( $message, 0, $severity, $file, $line );
		}

		return false;
	}

	set_error_handler( 'skye_media_test_error' );
	$core = getenv( 'SKYE_WORDPRESS_CORE' );

	if ( ! $core || ! is_file( $core . '/wp-includes/post.php' ) ) {
		throw new RuntimeException( 'Set SKYE_WORDPRESS_CORE to a local WordPress source checkout.' );
	}

	if ( ! extension_loaded( 'pdo_sqlite' ) || ! extension_loaded( 'gd' ) || ! function_exists( 'imagewebp' ) ) {
		throw new RuntimeException( 'The native pipeline test requires PDO SQLite and GD with WebP support.' );
	}

	$directory = sys_get_temp_dir() . '/skye-media-pipeline-test-' . bin2hex( random_bytes( 8 ) );
	mkdir( $directory, 0700 );
	mkdir( $directory . '/uploads', 0700 );

	/**
	 * Remove only the synthetic test directory, including failure-path derivatives.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_cleanup() {

		$directory = $GLOBALS['directory'];
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() && ! $file->isLink() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}

		rmdir( $directory );
	}

	register_shutdown_function( 'skye_media_test_cleanup' );
	define( 'ABSPATH', rtrim( $core, '/' ) . '/' );
	define( 'WPINC', 'wp-includes' );
	define( 'WP_CONTENT_DIR', $directory );
	define( 'WP_CONTENT_URL', 'https://example.test/wp-content' );
	define( 'WP_DEBUG', false );
	define( 'WP_MEMORY_LIMIT', '256M' );
	define( 'WP_MAX_MEMORY_LIMIT', '512M' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'KB_IN_BYTES', 1024 );
	define( 'MB_IN_BYTES', 1048576 );
	define( 'GB_IN_BYTES', 1073741824 );
	define( 'TB_IN_BYTES', 1099511627776 );
	define( 'EMPTY_TRASH_DAYS', 30 );
	define( 'MEDIA_TRASH', false );
	$GLOBALS['blog_id'] = 1;

	foreach ( [
		'compat.php', 'plugin.php', 'load.php', 'class-wp-error.php', 'cache.php',
		'functions.php', 'compat-utf8.php', 'utf8.php', 'formatting.php', 'kses.php',
		'class-wpdb.php', 'class-wp-post.php', 'meta.php', 'post.php', 'revision.php', 'shortcodes.php',
		'media.php', 'cron.php', 'http.php', 'class-wp-token-map.php',
		'html-api/html5-named-character-references.php', 'html-api/class-wp-html-attribute-token.php',
		'html-api/class-wp-html-span.php', 'html-api/class-wp-html-text-replacement.php',
		'html-api/class-wp-html-decoder.php', 'html-api/class-wp-html-tag-processor.php',
		'class-wp-image-editor.php', 'class-wp-image-editor-gd.php',
		'pomo/entry.php', 'pomo/translations.php', 'l10n.php', 'version.php',
	] as $file ) {
		if ( is_file( ABSPATH . WPINC . '/' . $file ) ) {
			require_once ABSPATH . WPINC . '/' . $file;
		}
	}

	wp_cache_init();

	/**
	 * Execute production SQL against isolated tables without emulating predicates.
	 *
	 * This adapter changes syntax only: IGNORE, BINARY, MySQL's option upsert and
	 * qualified UPDATE targets. Native wpdb::prepare and all Core metadata hooks
	 * remain real. SQLite is deliberately documented as distinct from InnoDB.
	 *
	 * @since 3.0.0
	 */
	class SKYE_Media_Test_DB extends wpdb {

		public $pdo;
		public $before_query = null;
		public $after_query = null;
		/** @var bool Inject one migration-state SQL write failure for recovery tests. */
		public $fail_scan_write = false;
		/** @var bool Fail one cleanup cursor write to verify repeatable acknowledgement. */
		public $fail_cleanup_scan_write = false;
		/** @var bool Fail one installation-marker write before any event is created. */
		public $fail_version_write = false;
		/** @var bool Fail one ownership-inventory read without pretending no row exists. */
		public $fail_inventory_read = false;
		/** @var int Fail the next ownership-inventory CAS writes. */
		public $fail_inventory_writes = 0;
		/** @var array{id?:int,verb?:string} Fail one real pending-row write for discovery recovery. */
		public $fail_enqueue = array();
		public $queries = [];

		/**
		 * Create empty synthetic WordPress tables with binary value comparison.
		 *
		 * PHP 8.4 adds the dedicated SQLite connection and registration API;
		 * only the minimum PHP 8.3 runtime needs the legacy PDO driver methods.
		 * Both paths register the same callbacks and execute identical predicates.
		 *
		 * @see https://www.php.net/manual/en/class.pdo-sqlite.php
		 * @see https://www.php.net/manual/en/migration85.deprecated.php
		 * @return void
		 * @since 3.0.0
		 */
		public function __construct() {

			$this->pdo = PHP_VERSION_ID >= 80400 ? new \Pdo\Sqlite( 'sqlite::memory:' ) : new PDO( 'sqlite::memory:' );
			$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$this->ready = true;
			$this->prefix = 'wp_';
			$this->base_prefix = 'wp_';
			$this->options = 'wp_options';
			$this->posts = 'wp_posts';
			$this->postmeta = 'wp_postmeta';
			$this->pdo->exec( 'CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT COLLATE BINARY UNIQUE, option_value TEXT COLLATE BINARY, autoload TEXT)' );
			$this->pdo->exec( 'CREATE TABLE wp_postmeta (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT COLLATE BINARY, meta_value TEXT COLLATE BINARY)' );
			$this->pdo->exec( "CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_author INTEGER DEFAULT 1, post_date TEXT DEFAULT '', post_date_gmt TEXT DEFAULT '', post_content TEXT DEFAULT '', post_title TEXT DEFAULT '', post_excerpt TEXT DEFAULT '', post_status TEXT DEFAULT 'inherit', comment_status TEXT DEFAULT 'closed', ping_status TEXT DEFAULT 'closed', post_password TEXT DEFAULT '', post_name TEXT DEFAULT '', to_ping TEXT DEFAULT '', pinged TEXT DEFAULT '', post_modified TEXT DEFAULT '', post_modified_gmt TEXT DEFAULT '', post_content_filtered TEXT DEFAULT '', post_parent INTEGER DEFAULT 0, guid TEXT DEFAULT '', menu_order INTEGER DEFAULT 0, post_type TEXT DEFAULT 'attachment', post_mime_type TEXT DEFAULT 'image/jpeg', comment_count INTEGER DEFAULT 0)" );
			if ( PHP_VERSION_ID >= 80400 ) {
				$this->pdo->createFunction( 'UNIX_TIMESTAMP', 'skye_media_test_db_time', 0 );
				$this->pdo->createFunction( 'CONCAT', 'skye_media_test_concat' );
			} else {
				$this->pdo->sqliteCreateFunction( 'UNIX_TIMESTAMP', 'skye_media_test_db_time', 0 );
				$this->pdo->sqliteCreateFunction( 'CONCAT', 'skye_media_test_concat' );
			}
		}

		/**
		 * Quote values for the actual local SQL engine while retaining native prepare.
		 *
		 * @param string $data Unescaped SQL value.
		 * @return string SQL-escaped contents without surrounding quotes.
		 * @since 3.0.0
		 */
		public function _real_escape( $data ) {

			return substr( $this->pdo->quote( (string) $data ), 1, -1 );
		}

		/**
		 * Translate dialect outside quoted values, then execute every SQL condition.
		 *
		 * @param string $query Prepared production query.
		 * @return int|false Number of selected or affected rows, or an injected native write failure.
		 * @throws RuntimeException For unsupported SQL rather than returning fake data.
		 * @since 3.0.0
		 */
		public function query( $query ) {

			$query = $this->remove_placeholder_escape( $query );
			$this->queries[] = $query;
			$this->last_query = $query;
			$this->num_queries++;

			if ( $this->before_query !== null ) {
				$callback = $this->before_query;
				$this->before_query = null;
				$callback( $query, $this );
			}

			if ( $this->fail_version_write && str_contains( $query, 'skye_webp_pipeline_version' ) && preg_match( '/^\s*(?:INSERT|UPDATE)\b/i', $query ) ) {
				$this->fail_version_write = false;
				$this->last_error = 'Synthetic installation-marker write failure';
				return false;
			}
			if ( $this->fail_scan_write && str_contains( $query, 'skye_webp_pipeline_scan' ) && preg_match( '/^\s*(?:INSERT|UPDATE)\b/i', $query ) ) {
				$this->fail_scan_write = false;
				$this->last_error = 'Synthetic migration-state write failure';
				return false;
			}
			if ( $this->fail_cleanup_scan_write && str_contains( $query, 'skye_webp_cleanup_scan' ) && preg_match( '/^\s*(?:INSERT|UPDATE)\b/i', $query ) ) {
				$this->fail_cleanup_scan_write = false;
				$this->last_error = 'Synthetic cleanup cursor write failure';
				return false;
			}
			$failed_id = (int) ( $this->fail_enqueue['id'] ?? 0 );
			$failed_verb = $this->fail_enqueue['verb'] ?? '';
			if ( $failed_id > 0 && in_array( $failed_verb, array( 'INSERT', 'UPDATE' ), true ) &&
				str_contains( $query, "'_skye_webp_pending'" ) && preg_match( '/^\s*' . $failed_verb . '\b/i', $query ) &&
				preg_match( '/\bp\.ID\s*=\s*' . $failed_id . '\b/', $query ) ) {
				$this->fail_enqueue = array();
				$this->last_error = 'Synthetic durable enqueue write failure';
				return false;
			}
			if ( str_contains( $query, "'_skye_webp_files'" ) ) {
				if ( $this->fail_inventory_read && preg_match( '/^\s*SELECT\s+meta_id,\s*meta_value\b/i', $query ) ) {
					$this->fail_inventory_read = false;
					$this->last_error = 'Synthetic inventory read failure';
					$this->last_result = [];
					return false;
				}
				if ( $this->fail_inventory_writes > 0 && preg_match( '/^\s*UPDATE\s+wp_postmeta\s+s\b/i', $query ) ) {
					$this->fail_inventory_writes--;
					$this->last_error = 'Synthetic inventory write failure';
					$this->last_result = [];
					return false;
				}
			}

			$pieces = preg_split( "/('(?:''|[^'])*')/s", $query, -1, PREG_SPLIT_DELIM_CAPTURE );

			foreach ( $pieces as $index => $piece ) {
				if ( $index % 2 !== 0 ) {
					continue;
				}

				$piece = preg_replace( '/\\bINSERT\\s+IGNORE\\s+INTO\\b/i', 'INSERT OR IGNORE INTO', $piece );
				$piece = preg_replace( '/\\bON\\s+DUPLICATE\\s+KEY\\s+UPDATE\\b/i', 'ON CONFLICT(option_name) DO UPDATE SET', $piece );
				$piece = preg_replace( '/\\bVALUES\\(\\s*(`?[a-zA-Z_][a-zA-Z0-9_]*`?)\\s*\\)/i', 'excluded.$1', $piece );
				$piece = preg_replace( '/\\bBINARY\\s+/i', '', $piece );
				$piece = preg_replace( '/\\bSET\\s+[a-zA-Z_][a-zA-Z0-9_]*\\.(meta_value|option_value)\\s*=/i', 'SET $1 =', $piece );
				$pieces[$index] = $piece;
			}

			$sql = implode( '', $pieces );
			$mask = preg_replace_callback( "/'(?:''|[^'])*'/s", 'skye_media_test_sql_mask', $sql );

			if ( preg_match( '/^\\s*UPDATE\\s+(`?[a-zA-Z_][a-zA-Z0-9_]*`?)\\s+([a-zA-Z_][a-zA-Z0-9_]*)\\s+(?=JOIN|LEFT\\s+JOIN)/i', $mask, $target ) ) {
				preg_match( '/\\bSET\\b/i', $mask, $set_match, PREG_OFFSET_CAPTURE );
				preg_match( '/\\bWHERE\\b/i', $mask, $where_match, PREG_OFFSET_CAPTURE, $set_match[0][1] );
				$joins = substr( $sql, strlen( $target[0] ), $set_match[0][1] - strlen( $target[0] ) );
				$set = substr( $sql, $set_match[0][1] + 3, $where_match[0][1] - $set_match[0][1] - 3 );
				$set = preg_replace( '/^\\s*' . $target[2] . '\\./', '', $set );
				$where = substr( $sql, $where_match[0][1] + 5 );
				$sql = 'UPDATE ' . $target[1] . ' AS ' . $target[2] . ' SET ' . $set . ' WHERE ' . $target[2] . '.rowid IN (' .
					'SELECT ' . $target[2] . '.rowid FROM ' . $target[1] . ' ' . $target[2] . ' ' . $joins . ' WHERE ' . $where . ')';
			} elseif ( preg_match( '/^\\s*DELETE\\s+([a-zA-Z_][a-zA-Z0-9_]*(?:\\s*,\\s*[a-zA-Z_][a-zA-Z0-9_]*)*)\\s+FROM\\s+(`?[a-zA-Z_][a-zA-Z0-9_]*`?)\\s+([a-zA-Z_][a-zA-Z0-9_]*)\\s+/i', $mask, $target ) ) {
				$from = substr( $sql, stripos( $mask, 'FROM' ) + 4 );
				$selects = [];
				foreach ( preg_split( '/\\s*,\\s*/', $target[1] ) as $alias ) {
					$selects[] = 'SELECT ' . $alias . '.rowid FROM ' . $from;
				}
				$sql = 'DELETE FROM ' . $target[2] . ' WHERE rowid IN (' . implode( ' UNION ', $selects ) . ')';
			}
			$this->last_error = '';
			$this->last_result = [];
			$this->num_rows = 0;
			$this->rows_affected = 0;

			try {
				$statement = $this->pdo->query( $sql );
				if ( preg_match( '/^\\s*(SELECT|WITH|PRAGMA)\\b/i', $sql ) ) {
					$this->last_result = $statement->fetchAll( PDO::FETCH_OBJ );
					$this->num_rows = count( $this->last_result );
					return $this->finish_query( $query, $this->num_rows );
				}

				$this->rows_affected = $statement->rowCount();
				$this->insert_id = (int) $this->pdo->lastInsertId();
				return $this->finish_query( $query, $this->rows_affected );
			} catch ( PDOException $error ) {
				$this->last_error = $error->getMessage();
				throw new RuntimeException( 'Unsupported or invalid local SQL: ' . $sql, 0, $error );
			}
		}

		/**
		 * Expose one post-statement race boundary without changing its SQL result.
		 *
		 * @param string $query Actual prepared production query.
		 * @param int    $result Actual row count from SQLite.
		 * @return int Unchanged database result.
		 * @since 3.0.0
		 */
		private function finish_query( $query, $result ) {

			if ( $this->after_query !== null ) {
				$callback = $this->after_query;
				$this->after_query = null;
				$callback( $query, $this );
			}

			return $result;
		}

		/**
		 * Read a native wpdb result row without MySQL collation discovery.
		 *
		 * @param string|null $query Prepared SQL.
		 * @param string      $output Native result format.
		 * @param int         $y Row index.
		 * @return array|object|null Requested result.
		 * @since 3.0.0
		 */
		public function get_row( $query = null, $output = OBJECT, $y = 0 ) {

			$rows = $query !== null ? $this->get_results( $query, $output ) : $this->last_result;
			return $rows[$y] ?? null;
		}

		/**
		 * Read SQL rows using the native WordPress result shapes.
		 *
		 * @param string|null $query Prepared SQL.
		 * @param string      $output OBJECT, ARRAY_A or ARRAY_N.
		 * @return array Result rows.
		 * @since 3.0.0
		 */
		public function get_results( $query = null, $output = OBJECT ) {

			if ( $query !== null ) {
				$this->query( $query );
			}

			if ( $output === OBJECT ) {
				return $this->last_result;
			}

			$rows = [];
			foreach ( $this->last_result as $row ) {
				$rows[] = $output === ARRAY_N ? array_values( (array) $row ) : (array) $row;
			}

			return $rows;
		}

		/**
		 * Read a scalar by executing the real query rather than a fixed fixture reply.
		 *
		 * @param string|null $query Prepared SQL.
		 * @param int         $x Column index.
		 * @param int         $y Row index.
		 * @return mixed Requested scalar or null.
		 * @since 3.0.0
		 */
		public function get_var( $query = null, $x = 0, $y = 0 ) {

			$rows = $this->get_results( $query, ARRAY_N );
			return $rows[$y][$x] ?? null;
		}

		/**
		 * Read one result column for native metadata and bounded keyset queries.
		 *
		 * @param string|null $query Prepared SQL.
		 * @param int         $x Column index.
		 * @return array Column values.
		 * @since 3.0.0
		 */
		public function get_col( $query = null, $x = 0 ) {

			$values = [];
			foreach ( $this->get_results( $query, ARRAY_N ) as $row ) {
				$values[] = $row[$x] ?? null;
			}

			return $values;
		}

		/**
		 * Insert Core fixture metadata through actual local constraints.
		 *
		 * @param string     $table Target table.
		 * @param array      $data Named column values.
		 * @param array|null $format Native unused format hints.
		 * @return int Affected row count.
		 * @since 3.0.0
		 */
		public function insert( $table, $data, $format = null ) {

			$columns = '`' . implode( '`,`', array_keys( $data ) ) . '`';
			$values = [];
			foreach ( $data as $value ) {
				$values[] = $value === null ? 'NULL' : $this->pdo->quote( (string) $value );
			}

			return $this->query( "INSERT INTO `$table` ($columns) VALUES (" . implode( ',', $values ) . ')' );
		}

		/**
		 * Update rows with exact SQL WHERE predicates for native Core metadata APIs.
		 *
		 * @param string     $table Target table.
		 * @param array      $data Named column values.
		 * @param array      $where Exact column predicates.
		 * @param array|null $format Native unused format hints.
		 * @param array|null $where_format Native unused predicate format hints.
		 * @return int Affected row count.
		 * @since 3.0.0
		 */
		public function update( $table, $data, $where, $format = null, $where_format = null ) {

			$set = [];
			foreach ( $data as $column => $value ) {
				$set[] = '`' . $column . '`=' . ( $value === null ? 'NULL' : $this->pdo->quote( (string) $value ) );
			}

			return $this->query( "UPDATE `$table` SET " . implode( ',', $set ) . ' WHERE ' . $this->where_sql( $where ) );
		}

		/**
		 * Delete rows using real predicates so stale ownership cannot be faked away.
		 *
		 * @param string     $table Target table.
		 * @param array      $where Exact column predicates.
		 * @param array|null $where_format Native unused predicate format hints.
		 * @return int Affected row count.
		 * @since 3.0.0
		 */
		public function delete( $table, $where, $where_format = null ) {

			return $this->query( "DELETE FROM `$table` WHERE " . $this->where_sql( $where ) );
		}

		/**
		 * Serialize only adapter-supplied named equality predicates, including NULL.
		 *
		 * @param array $where Trusted test/native named columns and values.
		 * @return string SQL conjunction.
		 * @since 3.0.0
		 */
		private function where_sql( $where ) {

			$parts = [];
			foreach ( $where as $column => $value ) {
				$parts[] = '`' . $column . '`' . ( $value === null ? ' IS NULL' : '=' . $this->pdo->quote( (string) $value ) );
			}

			return implode( ' AND ', $parts );
		}
	}

	/**
	 * Supply MySQL CONCAT semantics only where production queries request it.
	 *
	 * @param mixed ...$parts Scalar SQL arguments.
	 * @return string Concatenated SQL value.
	 * @since 3.0.0
	 */
	function skye_media_test_concat( ...$parts ) {

		return implode( '', $parts );
	}

	/**
	 * Mask literals at their original offsets before translating SQL join syntax.
	 *
	 * @param array $match One quoted SQL literal.
	 * @return string Equal-length whitespace, preserving structural token offsets.
	 * @since 3.0.0
	 */
	function skye_media_test_sql_mask( $match ) {

		return str_repeat( ' ', strlen( $match[0] ) );
	}

	/**
	 * Move the SQL clock independently to test expiry inside the atomic statement.
	 *
	 * @return int Controlled database timestamp or the actual Unix timestamp.
	 * @since 3.0.0
	 */
	function skye_media_test_db_time() {

		return $GLOBALS['skye_media_db_clock'] ?? time();
	}

	$wpdb = new SKYE_Media_Test_DB();
	$checks = 0;

	/**
	 * Record an assertion or stop before a later fixture can conceal the failure.
	 *
	 * @param bool   $condition Expected condition.
	 * @param string $message Behaviour being verified.
	 * @return void
	 * @throws RuntimeException On a failed assertion.
	 * @since 3.0.0
	 */
	function skye_media_check( $condition, $message ) {

		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}

		$GLOBALS['checks']++;
	}

	/**
	 * Supply only local site configuration; mutable queue options use native Core.
	 *
	 * @param mixed  $value Existing short-circuit result.
	 * @param string $name Requested option name.
	 * @return mixed Local site option or unchanged filter result.
	 * @since 3.0.0
	 */
	function skye_media_test_option( $value, $name ) {

		$options = [
			'blog_charset' => 'UTF-8',
			'upload_path' => $GLOBALS['directory'] . '/uploads',
			'upload_url_path' => 'https://example.test/uploads',
			'siteurl' => 'https://example.test',
			'home' => 'https://example.test',
			'uploads_use_yearmonth_folders' => 0,
			'timezone_string' => 'UTC',
			'gmt_offset' => 0,
		];

		return array_key_exists( $name, $options ) ? $options[$name] : $value;
	}

	/**
	 * Reject accidental external requests from the pipeline or image editor.
	 *
	 * @return void
	 * @throws RuntimeException Always, because fixtures need no network.
	 * @since 3.0.0
	 */
	function skye_media_test_no_http() {

		throw new RuntimeException( 'The isolated media regression attempted a network request.' );
	}

	add_filter( 'pre_option', 'skye_media_test_option', 10, 2 );
	add_filter( 'pre_http_request', 'skye_media_test_no_http' );

	/**
	 * Resolve the theme library from this test checkout, without bootstrapping themes.
	 *
	 * @return string Absolute current test theme path.
	 * @since 3.0.0
	 */
	function get_template_directory() {

		return dirname( __DIR__ );
	}

	/**
	 * Decode one pixel independently of native truecolor or palette storage.
	 *
	 * imagecolorat() returns a palette index for indexed PNGs, not packed RGBA.
	 * The public color decoder preserves the same alpha assertions for either
	 * legitimate output model chosen by the unchanged native image provider.
	 *
	 * @param GdImage $image Real decoded fixture image.
	 * @param int $x Horizontal pixel position.
	 * @param int $y Vertical pixel position.
	 * @return array{red:int,green:int,blue:int,alpha:int} Decoded native pixel channels.
	 * @throws RuntimeException If the requested pixel cannot be decoded.
	 * @see https://www.php.net/manual/en/function.imagecolorat.php
	 * @see https://www.php.net/manual/en/function.imagecolorsforindex.php
	 * @since 3.0.0
	 */
	function skye_media_test_pixel( GdImage $image, int $x, int $y ): array {
		$index = imagecolorat( $image, $x, $y );
		$pixel = false === $index ? false : imagecolorsforindex( $image, $index );
		if ( ! is_array( $pixel ) ) {
			throw new RuntimeException( 'Cannot decode fixture pixel.' );
		}
		return $pixel;
	}

	/**
	 * Save and reload transparent palette and truecolor PNGs through real GD.
	 *
	 * This proves why an indexed color cannot be bitshifted as packed RGBA while
	 * retaining exact opaque, partial-alpha and transparent checks for both models.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_png_color_models() {
		foreach ( array( false, true ) as $truecolor ) {
			$image = $truecolor ? imagecreatetruecolor( 3, 1 ) : imagecreate( 3, 1 );
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
			foreach ( array( 127, 64, 0 ) as $x => $alpha ) {
				$color = imagecolorallocatealpha( $image, 20, 40, 60, $alpha );
				imagesetpixel( $image, $x, 0, $color );
			}
			$path = $GLOBALS['directory'] . '/png-colors-' . ( $truecolor ? 'truecolor' : 'palette' ) . '.png';
			skye_media_check( imagepng( $image, $path ), 'The color-model fixture is saved as an actual PNG file.' );
			unset( $image );
			$image = imagecreatefrompng( $path );
			skye_media_check( $image instanceof GdImage, 'Native GD decodes the saved PNG fixture.' );
			skye_media_check( imageistruecolor( $image ) === $truecolor, 'PNG decoding preserves the intended palette or truecolor model.' );
			foreach ( array( 127, 64, 0 ) as $x => $alpha ) {
				skye_media_check( skye_media_test_pixel( $image, $x, 0 )['alpha'] === $alpha, 'Both PNG models retain exact transparent, partial-alpha and opaque channels.' );
			}
			$opaque = skye_media_test_pixel( $image, 2, 0 );
			skye_media_check( $opaque['red'] === 20 && $opaque['green'] === 40 && $opaque['blue'] === 60, 'Both PNG models preserve opaque source colors.' );
			if ( ! $truecolor ) {
				skye_media_check( ( imagecolorat( $image, 0, 0 ) >> 24 ) === 0 && skye_media_test_pixel( $image, 0, 0 )['alpha'] === 127, 'The old packed-color shift would falsely reject this fully transparent palette pixel.' );
			}
			unset( $image );
		}
	}

	/**
	 * Create synthetic photographs or transparent PNGs without customer fixtures.
	 *
	 * @param string $path Destination inside the disposable test directory.
	 * @param string $mime JPEG or PNG.
	 * @param int    $width Exact width.
	 * @param int    $height Exact height.
	 * @param bool   $flat Whether to create a compressible flat PNG instead of photo-like noise.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_image( $path, $mime, $width, $height, $flat = false ) {

		$image = imagecreatetruecolor( $width, $height );
		if ( $mime === 'image/png' ) {
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
			$background = imagecolorallocatealpha( $image, 20, 40, 60, 127 );
		} else {
			$background = imagecolorallocate( $image, 20, 40, 60 );
		}

		imagefill( $image, 0, 0, $background );
		$accent = imagecolorallocatealpha( $image, 230, 120, 20, 0 );
		imagefilledrectangle( $image, 3, 3, max( 4, (int) ( $width / 2 ) ), max( 4, (int) ( $height / 2 ) ), $accent );
		if ( $mime === 'image/png' && ! $flat ) {
			// Deterministic photographic entropy makes real PNG-to-WebP gain measurable.
			for ( $y = 0; $y < $height; $y++ ) {
				for ( $x = 0; $x < $width; $x++ ) {
					$value = ( $x * 397 + $y * 613 + $x * $y * 71 ) ^ ( $x << 13 ) ^ ( $y << 7 );
					imagesetpixel( $image, $x, $y, $value & 0xffffff );
				}
			}
			imagefilledrectangle( $image, 0, 0, max( 2, (int) ( $width / 32 ) ), $height - 1, $background );
			imagefilledrectangle( $image, 0, 0, $width - 1, max( 2, (int) ( $height / 32 ) ), $background );
			imagefilledrectangle( $image, $width - 1 - max( 2, (int) ( $width / 32 ) ), 0, $width - 1, $height - 1, $background );
			imagefilledrectangle( $image, 0, $height - 1 - max( 2, (int) ( $height / 32 ) ), $width - 1, $height - 1, $background );
		}

		if ( $mime === 'image/png' ) {
			imagepng( $image, $path );
		} else {
			imagejpeg( $image, $path, 90 );
		}

		unset( $image );
	}

	/**
	 * Add real native attachment records and files without enqueueing fixture setup.
	 *
	 * @param int    $id Synthetic attachment ID.
	 * @param string $mime Original MIME.
	 * @param int    $variants Number of distinct native subsizes.
	 * @return array Exact initial native metadata.
	 * @since 3.0.0
	 */
	function skye_media_test_attachment( $id, $mime = 'image/jpeg', $variants = 1 ) {

		global $wpdb, $directory;

		$folder = $directory . '/uploads/2026/11';
		if ( ! is_dir( $folder ) ) {
			mkdir( $folder, 0700, true );
		}

		$extension = $mime === 'image/png' ? 'png' : 'jpg';
		$base = 'fixture-' . $id;
		$metadata = [
			'file' => '2026/11/' . $base . '.' . $extension,
			'width' => 96,
			'height' => 64,
			'sizes' => [],
			'image_meta' => [ 'caption' => 'Keep original & exact', 'orientation' => '1' ],
		];

		skye_media_test_image( $folder . '/' . $base . '.' . $extension, $mime, 96, 64 );
		$metadata['filesize'] = filesize( $folder . '/' . $base . '.' . $extension );

		for ( $index = 1; $index <= $variants; $index++ ) {
			$width = 16 + 4 * $index;
			$height = 16 + 2 * $index;
			$file = $base . '-' . $width . 'x' . $height . '.' . $extension;
			skye_media_test_image( $folder . '/' . $file, $mime, $width, $height );
			$metadata['sizes']['size-' . $index] = [
				'file' => $file,
				'width' => $width,
				'height' => $height,
				'mime-type' => $mime,
				'filesize' => filesize( $folder . '/' . $file ),
			];
		}

		$wpdb->insert( $wpdb->posts, [ 'ID' => $id, 'post_type' => 'attachment', 'post_mime_type' => $mime, 'post_title' => 'Synthetic fixture' ] );
		$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $id, 'meta_key' => '_wp_attached_file', 'meta_value' => $metadata['file'] ] );
		$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $id, 'meta_key' => '_wp_attachment_metadata', 'meta_value' => maybe_serialize( $metadata ) ] );
		wp_cache_flush();

		return $metadata;
	}

	/**
	 * Reset isolated SQL state and native caches between independent race scenarios.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_reset() {

		global $wpdb;

		if ( class_exists( '\Rocketsky\Skye\Packages\Media\MediaStore', false ) ) {
			\Rocketsky\Skye\Packages\Media\MediaStore::release();
		}

		unset( $GLOBALS['skye_media_clock'], $GLOBALS['skye_media_db_clock'] );
		$wpdb->before_query = null;
		$wpdb->after_query = null;
		$wpdb->fail_inventory_read = false;
		$wpdb->fail_inventory_writes = 0;
		$wpdb->fail_enqueue = array();
		$wpdb->fail_cleanup_scan_write = false;
		$wpdb->query( 'DELETE FROM wp_options' );
		$wpdb->query( 'DELETE FROM wp_postmeta' );
		$wpdb->query( 'DELETE FROM wp_posts' );
		$wpdb->queries = [];
		wp_cache_flush();
	}

	/**
	 * Provide synthetic registered sizes to exercise generic crop-family behavior.
	 *
	 * These values belong only to disposable tests; production reads whichever
	 * sizes WordPress and the selected theme actually register.
	 *
	 * @return array<string,array> Native width, height and crop fixtures.
	 * @since 3.0.0
	 */
	function skye_media_test_sizes() {
		return array(
			'skye-content-sm' => array( 'width' => 320, 'height' => 0, 'crop' => false ),
			'skye-content-md' => array( 'width' => 480, 'height' => 0, 'crop' => false ),
			'skye-content-lg' => array( 'width' => 800, 'height' => 0, 'crop' => false ),
			'skye-card-sm' => array( 'width' => 320, 'height' => 151, 'crop' => true ),
			'skye-card-md' => array( 'width' => 480, 'height' => 226, 'crop' => true ),
			'skye-news-sm' => array( 'width' => 640, 'height' => 360, 'crop' => true ),
			'skye-news-md' => array( 'width' => 960, 'height' => 540, 'crop' => true ),
		);
	}

	/**
	 * Resolve fixed local template paths while exercising Skye's actual loader.
	 *
	 * This is the theme-directory boundary only, not a second renderer; production
	 * get_component() performs context isolation and includes the actual template.
	 *
	 * @param string|string[] $names Ordered native template candidates.
	 * @param bool $load Unused native loading flag; the actual loader includes it.
	 * @param bool $once Unused native include-once flag.
	 * @return string First existing local template or the native missing value.
	 * @since 3.0.0
	 */
	function locate_template( $names, $load = false, $once = true ) {
		if ( ! empty( $GLOBALS['skye_media_missing_component'] ) ) {
			return '';
		}
		foreach ( (array) $names as $name ) {
			$path = dirname( __DIR__ ) . '/' . $name;
			if ( is_file( $path ) ) {
				return $path;
			}
		}
		return '';
	}

	// Establish that hooks and comparisons below operate on real native persistence.
	skye_media_test_reset();
	$native_metadata = skye_media_test_attachment( 410 );
	skye_media_check( wp_get_attachment_metadata( 410, true ) === $native_metadata, 'Native Core reads the synthetic SQL metadata exactly.' );
	$native_metadata['image_meta']['caption'] = 'Changed "caption" \\ keep';
	skye_media_check( wp_update_attachment_metadata( 410, wp_slash( $native_metadata ) ) !== false, 'Native metadata save executes successfully through the SQL adapter.' );
	skye_media_check( wp_get_attachment_metadata( 410, true ) === $native_metadata, 'Native metadata slashing and serialization stay exact.' );
	skye_media_check( wp_schedule_single_event( time() + 10, 'skye_media_test_event', [] ) === true, 'Native Cron persists a real single event.' );
	skye_media_check( wp_next_scheduled( 'skye_media_test_event', [] ) !== false, 'Native Cron reads the stored event back.' );
	skye_media_test_reset();

	// Fail rather than skip missing implementation: the suite is a release check.
	require_once dirname( __DIR__ ) . '/lib/rocketsky/helpers.php';
	require_once dirname( __DIR__ ) . '/lib/package/Images.php';
	require_once dirname( __DIR__ ) . '/lib/package/media/MediaStore.php';
	require_once dirname( __DIR__ ) . '/lib/package/media/WebpGenerator.php';

	use Rocketsky\Skye\Packages\Images;
	use Rocketsky\Skye\Packages\MediaPipeline;
	use Rocketsky\Skye\Packages\Media\MediaStore;
	use Rocketsky\Skye\Packages\Media\WebpGenerator;

	/**
	 * Prepare one current job and acquire its native worker snapshot.
	 *
	 * @param int    $id Synthetic attachment ID.
	 * @param string $mime Original MIME.
	 * @param int    $variants Number of native subsizes.
	 * @return array Valid production MediaStore snapshot.
	 * @since 3.0.0
	 */
	function skye_media_test_snapshot( $id = 410, $mime = 'image/jpeg', $variants = 1 ) {

		skye_media_test_reset();
		skye_media_test_attachment( $id, $mime, $variants );
		$revision = sprintf( '%010d:%s', time(), wp_generate_uuid4() );
		update_post_meta( $id, MediaStore::PENDING_KEY, $revision );
		skye_media_check( MediaStore::acquire(), 'A fresh worker acquires the unique DB lease.' );
		$snapshot = MediaStore::snapshot( $id, $revision );
		skye_media_check( is_array( $snapshot ), 'Worker obtains an existing attachment and exact pending snapshot.' );
		return $snapshot;
	}

	/**
	 * Build structurally valid manifests for publication-boundary tests only.
	 *
	 * Actual encoding, MIME and alpha are independently tested through the real
	 * generator below; publication must not access these files itself.
	 *
	 * @param array $snapshot Existing production snapshot.
	 * @return array Complete skye_webp v1 manifest.
	 * @since 3.0.0
	 */
	function skye_media_test_manifest( $snapshot ) {

		$metadata = $snapshot['metadata'];
		$entry = [
			'file' => 'fixture-' . $snapshot['attachment_id'] . '-skye-webp-' . $snapshot['attachment_id'] . '-q82-0123456789ab.webp',
			'source' => wp_basename( $metadata['file'] ),
			'width' => $metadata['width'],
			'height' => $metadata['height'],
			'mime-type' => 'image/webp',
			'filesize' => 100,
			'source_sha256' => str_repeat( 'a', 64 ),
		];
		$manifest = [ 'version' => 1, 'quality' => 82, 'source_file' => $metadata['file'], 'full' => $entry, 'sizes' => [] ];

		foreach ( $metadata['sizes'] as $name => $size ) {
			$entry['source'] = $size['file'];
			$entry['file'] = pathinfo( $size['file'], PATHINFO_FILENAME ) . '-skye-webp-' . $snapshot['attachment_id'] . '-q82-0123456789ab.webp';
			$entry['width'] = $size['width'];
			$entry['height'] = $size['height'];
			$manifest['sizes'][$name] = $entry;
		}

		return $manifest;
	}

	/**
	 * Read the private lease directly, deliberately bypassing native option caches.
	 *
	 * @return string|null Current stored lease.
	 * @since 3.0.0
	 */
	function skye_media_test_lease() {

		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM wp_options WHERE option_name = %s', MediaStore::LOCK_OPTION ) );
	}

	/**
	 * Replace the process-local owner token to model another independent PHP worker.
	 *
	 * @param string $value Exact worker-local lease token.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_worker( $value ) {

		$property = new ReflectionProperty( MediaStore::class, 'lease' );
		$property->setValue( null, $value );
	}

	/**
	 * Inject a concurrent database change immediately before the guarded publication.
	 *
	 * SQL matching selects the race boundary only; production WHERE predicates
	 * execute unchanged and determine whether publication can actually succeed.
	 *
	 * @param string           $query SQL about to execute.
	 * @param SKYE_Media_Test_DB $db Actual isolated database.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_publish_race( $query, $db ) {

		if ( ! preg_match( '/^\\s*UPDATE\\s+`?wp_postmeta`?\\s+m\\b/i', $query ) ) {
			$db->before_query = 'skye_media_test_publish_race';
			return;
		}

		$id = $GLOBALS['skye_media_race_id'];
		$mode = $GLOBALS['skye_media_race_mode'];

		if ( $mode === 'delete_meta' ) {
			$db->pdo->exec( "DELETE FROM wp_postmeta WHERE post_id = $id AND meta_key = '_wp_attachment_metadata'" );
		} elseif ( $mode === 'delete_post' ) {
			$db->pdo->exec( "DELETE FROM wp_posts WHERE ID = $id" );
		} elseif ( $mode === 'new_revision' ) {
			$statement = $db->pdo->prepare( 'UPDATE wp_postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = ?' );
			$statement->execute( [ sprintf( '%010d:%s', time(), wp_generate_uuid4() ), $id, MediaStore::PENDING_KEY ] );
		} elseif ( $mode === 'new_core_size' ) {
			$value = $GLOBALS['skye_media_race_metadata'];
			$value['sizes']['concurrent-native'] = [ 'file' => 'native-added-8x8.jpg', 'width' => 8, 'height' => 8, 'mime-type' => 'image/jpeg' ];
			$statement = $db->pdo->prepare( "UPDATE wp_postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = '_wp_attachment_metadata'" );
			$statement->execute( [ maybe_serialize( $value ), $id ] );
		} elseif ( $mode === 'new_pointer' ) {
			$db->pdo->exec( "UPDATE wp_postmeta SET meta_value = '2026/11/replaced.jpg' WHERE post_id = $id AND meta_key = '_wp_attached_file'" );
		} elseif ( $mode === 'deleting' ) {
			$db->insert( $db->postmeta, [ 'post_id' => $id, 'meta_key' => MediaStore::DELETING_KEY, 'meta_value' => '1' ] );
		} elseif ( $mode === 'new_owner' ) {
			$statement = $db->pdo->prepare( 'UPDATE wp_options SET option_value = ? WHERE option_name = ?' );
			$statement->execute( [ sprintf( '%010d:%s', time() + MediaStore::LOCK_TTL, wp_generate_uuid4() ), MediaStore::LOCK_OPTION ] );
		} elseif ( $mode === 'db_expiry' ) {
			$GLOBALS['skye_media_db_clock'] = time() + MediaStore::LOCK_TTL + 1;
		} else {
			throw new RuntimeException( 'Unknown controlled race.' );
		}

		wp_cache_delete( $id, 'post_meta' );
	}

	/**
	 * Move only the worker clock beyond its lease after its initial ownership check.
	 *
	 * @param int    $meta_id Native metadata ID.
	 * @param int    $id Native attachment ID.
	 * @param string $key Native metadata key.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_expire_worker( $meta_id, $id, $key ) {

		if ( $key === '_wp_attachment_metadata' ) {
			$GLOBALS['skye_media_clock'] = time() + MediaStore::LOCK_TTL + 1;
		}
	}

	/**
	 * Verify lease ownership, internal checkpoints and atomic Core publication races.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_store() {

		global $wpdb;

		$snapshot = skye_media_test_snapshot();
		$owner_a = skye_media_test_lease();
		skye_media_check( MediaStore::owns_lock(), 'The actual DB lease is owned.' );
		skye_media_check( ! MediaStore::acquire(), 'A second acquisition cannot overwrite an unexpired lease.' );
		skye_media_check( skye_media_test_lease() === $owner_a, 'Losing acquisition preserves the existing token exactly.' );
		wp_cache_set( MediaStore::LOCK_OPTION, 'poisoned-cache-owner', 'options' );
		skye_media_check( MediaStore::owns_lock(), 'Incorrect object-cache lease data does not affect DB ownership.' );
		skye_media_check( $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM wp_options WHERE option_name = %s', MediaStore::LOCK_OPTION ) ) === 'off', 'The worker lease is excluded from autoload.' );

		$expired = sprintf( '%010d:%s', time() - 1, substr( $owner_a, 11 ) );
		$wpdb->update( $wpdb->options, [ 'option_value' => $expired ], [ 'option_name' => MediaStore::LOCK_OPTION ] );
		skye_media_test_worker( '' );
		skye_media_check( MediaStore::acquire(), 'An independent worker can reclaim an expired lease.' );
		$owner_b = skye_media_test_lease();
		skye_media_check( $owner_b !== $owner_a && $owner_b !== $expired, 'Replacement worker receives a fresh identity.' );
		skye_media_test_worker( $owner_a );
		skye_media_check( ! MediaStore::owns_lock() && ! MediaStore::current( $snapshot ), 'A stale worker cannot use a successor lease.' );
		MediaStore::release();
		skye_media_check( skye_media_test_lease() === $owner_b, 'Stale release never deletes the successor lease.' );
		skye_media_test_worker( $owner_b );
		MediaStore::release();
		skye_media_check( skye_media_test_lease() === null, 'The actual owner releases its own lease.' );

		$snapshot = skye_media_test_snapshot();
		skye_media_check( MediaStore::current( $snapshot ), 'Unchanged native state is current.' );
		skye_media_check( MediaStore::progress( $snapshot, [ 'entries' => [], 'checked' => 1 ] ), 'A current worker checkpoints internal progress.' );
		skye_media_check( MediaStore::progress( $snapshot ) === [ 'entries' => [], 'checked' => 1 ], 'Progress round-trips through native post metadata.' );
		$owned = '2026/11/fixture-410-skye-webp-410-q82-0123456789ab.webp';
		skye_media_check( MediaStore::remember_file( $snapshot, $owned ), 'An own-ID generated path is inventoried before encoding.' );
		skye_media_check( isset( get_post_meta( 410, MediaStore::FILES_KEY, true )[$owned] ), 'The output ledger is persistent.' );
		foreach ( [ '../' . $owned, '/' . $owned, 'https://example.test/' . $owned, str_replace( '-410-q82-', '-411-q82-', $owned ), '2026/11/fixture-410.jpg' ] as $path ) {
			skye_media_check( ! MediaStore::remember_file( $snapshot, $path ), 'Traversal, foreign owners and source files cannot enter the deletion ledger.' );
		}
		update_post_meta( 410, MediaStore::PENDING_KEY, sprintf( '%010d:%s', time(), wp_generate_uuid4() ) );
		skye_media_check( ! MediaStore::current( $snapshot ), 'A newer pending revision invalidates the old snapshot.' );
		skye_media_check( ! MediaStore::progress( $snapshot, [ 'entries' => [] ] ), 'Stale progress cannot overwrite the new revision.' );
		skye_media_check( ! MediaStore::remember_file( $snapshot, $owned ), 'A stale worker cannot extend the file ledger.' );

		foreach ( [ 'new_core_size', 'new_revision', 'new_pointer', 'new_owner', 'db_expiry', 'deleting', 'delete_meta', 'delete_post' ] as $mode ) {
			$snapshot = skye_media_test_snapshot();
			$alternatives = skye_media_test_manifest( $snapshot );
			$GLOBALS['skye_media_race_id'] = 410;
			$GLOBALS['skye_media_race_mode'] = $mode;
			$GLOBALS['skye_media_race_metadata'] = $snapshot['metadata'];
			$wpdb->before_query = 'skye_media_test_publish_race';
			skye_media_check( ! MediaStore::publish( $snapshot, $alternatives ), 'Atomic publication rejects concurrent ' . $mode . '.' );
			$wpdb->before_query = null;
			$current = wp_get_attachment_metadata( 410, true );
			skye_media_check( empty( $current['skye_webp'] ), 'Rejected publication never exposes an uncommitted namespace.' );
			if ( $mode === 'new_core_size' ) {
				skye_media_check( isset( $current['sizes']['concurrent-native'] ), 'Concurrent Core subsizes are preserved exactly.' );
			}
			if ( $mode === 'delete_meta' ) {
				skye_media_check( $wpdb->get_var( "SELECT COUNT(*) FROM wp_postmeta WHERE post_id = 410 AND meta_key = '_wp_attachment_metadata'" ) == 0, 'Publication cannot recreate a deleted metadata row.' );
			}
		}

		$snapshot = skye_media_test_snapshot();
		add_action( 'update_post_meta', 'skye_media_test_expire_worker', 10, 3 );
		skye_media_check( ! MediaStore::publish( $snapshot, skye_media_test_manifest( $snapshot ) ), 'Lease expiry between the initial check and SQL publication is fenced.' );
		remove_action( 'update_post_meta', 'skye_media_test_expire_worker', 10 );
		unset( $GLOBALS['skye_media_clock'] );

		$snapshot = skye_media_test_snapshot();
		$alternatives = skye_media_test_manifest( $snapshot );
		skye_media_check( MediaStore::publish( $snapshot, $alternatives ), 'Unchanged current metadata accepts a complete valid manifest.' );
		$stored = wp_get_attachment_metadata( 410, true );
		skye_media_check( ( $stored['skye_webp'] ?? null ) === $alternatives, 'The complete namespace is actually persisted.' );
		unset( $stored['skye_webp'] );
		skye_media_check( $stored === $snapshot['metadata'], 'Every original Core field stays byte-for-byte equivalent after publication.' );
		skye_media_check( ! MediaStore::is_publishing( 410 ), 'Publication guard is reset after success.' );
	}

	/**
	 * Return all own-ID outputs in the disposable upload directory.
	 *
	 * @param int $id Synthetic attachment owner.
	 * @return array Absolute generated paths, sorted by name.
	 * @since 3.0.0
	 */
	function skye_media_test_outputs( $id ) {

		$files = glob( $GLOBALS['directory'] . '/uploads/2026/11/*-skye-webp-' . $id . '-q82-*.webp' );
		sort( $files );
		return $files;
	}

	/**
	 * Record source hashes independently of production planning and fingerprinting.
	 *
	 * @param array $snapshot Current attachment metadata.
	 * @return array Absolute original paths and SHA-256 values.
	 * @since 3.0.0
	 */
	function skye_media_test_hashes( $snapshot ) {

		$base = $GLOBALS['directory'] . '/uploads/';
		$metadata = $snapshot['metadata'];
		$files = [ $base . $metadata['file'] ];

		foreach ( $metadata['sizes'] as $size ) {
			$files[] = $base . dirname( $metadata['file'] ) . '/' . $size['file'];
		}

		$hashes = [];
		foreach ( array_unique( $files ) as $file ) {
			$hashes[$file] = hash_file( 'sha256', $file );
		}

		return $hashes;
	}

	/**
	 * Assert that JPEG/PNG sources survive success and encoder failure unchanged.
	 *
	 * @param array $hashes Absolute original paths and their previous hashes.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_sources_unchanged( $hashes ) {

		foreach ( $hashes as $file => $hash ) {
			skye_media_check( is_file( $file ) && hash_file( 'sha256', $file ) === $hash, 'Every original file remains byte-exact.' );
		}
	}

	/**
	 * Supply a real native GD implementation that fails only its requested save.
	 *
	 * @since 3.0.0
	 */
	class SKYE_Media_Test_Failing_Editor extends WP_Image_Editor_GD {
		/**
		 * Simulate an encoder save error after native loading and file reservation.
		 *
		 * @param string|null $destfilename Reserved native destination.
		 * @param string|null $mime_type Requested output MIME.
		 * @return WP_Error Controlled native failure.
		 * @since 3.0.0
		 */
		public function save( $destfilename = null, $mime_type = null ) {

			return new WP_Error( 'synthetic_encoder_failure', 'Synthetic test encoder failed.' );
		}
	}

	/**
	 * Select controlled failure modes while ordinary cases use actual native GD.
	 *
	 * @param array $editors Existing native editor classes.
	 * @return array Unmodified classes, no editor, or the controlled save failure.
	 * @since 3.0.0
	 */
	function skye_media_test_editors( $editors ) {

		if ( ( $GLOBALS['skye_media_editor_mode'] ?? '' ) === 'none' ) {
			return [];
		}
		if ( ( $GLOBALS['skye_media_editor_mode'] ?? '' ) === 'fail' ) {
			return [ SKYE_Media_Test_Failing_Editor::class ];
		}

		return $editors;
	}

	/**
	 * Change source/job state inside the real native encode, after planning.
	 *
	 * @param int    $quality Current encoder quality.
	 * @param string $mime Current encoder MIME.
	 * @return int Unchanged encoder quality.
	 * @since 3.0.0
	 */
	function skye_media_test_encode_race( $quality, $mime ) {

		global $wpdb;

		$mode = $GLOBALS['skye_media_encode_race'] ?? '';
		if ( $mime !== 'image/webp' || $mode === '' ) {
			return $quality;
		}

		$GLOBALS['skye_media_encode_race'] = '';
		$id = $GLOBALS['skye_media_encode_id'];
		if ( $mode === 'source' ) {
			file_put_contents( get_attached_file( $id, true ), 'changed after source snapshot', FILE_APPEND );
		} elseif ( $mode === 'revision' ) {
			update_post_meta( $id, MediaStore::PENDING_KEY, sprintf( '%010d:%s', time(), wp_generate_uuid4() ) );
		} elseif ( $mode === 'delete' ) {
			do_action( 'delete_attachment', $id, get_post( $id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM wp_postmeta WHERE post_id = %d', $id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM wp_posts WHERE ID = %d', $id ) );
			wp_cache_delete( $id, 'posts' );
			wp_cache_delete( $id, 'post_meta' );
		} else {
			throw new RuntimeException( 'Unknown encode-race fixture.' );
		}

		return $quality;
	}

	/**
	 * Exercise actual Core/GD encoding, resumability, alpha and conservative failures.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_generator() {

		global $wpdb;

		$snapshot = skye_media_test_snapshot( 420, 'image/jpeg', 7 );
		$hashes = skye_media_test_hashes( $snapshot );
		$result = WebpGenerator::process( 420, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'pending', 'A file-limited real encoder checkpoints an incomplete set: ' . json_encode( $result ) );
		skye_media_check( count( skye_media_test_outputs( 420 ) ) === 3, 'The three-file budget creates exactly three sidecars.' );
		skye_media_check( empty( wp_get_attachment_metadata( 420, true )['skye_webp'] ), 'A partial set is never advertised to browsers.' );
		$progress = MediaStore::progress( $snapshot );
		skye_media_check( count( $progress['entries'] ?? [] ) === 3, 'Completed native outputs survive in persistent progress.' );
		$result = WebpGenerator::process( 420, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'pending' && count( skye_media_test_outputs( 420 ) ) === 6, 'The next job resumes and creates only three remaining sidecars.' );
		$result = WebpGenerator::process( 420, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete' && count( skye_media_test_outputs( 420 ) ) === 8, 'The complete native set is published after bounded resumptions.' );
		$stored = wp_get_attachment_metadata( 420, true );
		$entries = array_merge( [ $stored['skye_webp']['full'] ], array_values( $stored['skye_webp']['sizes'] ) );
		foreach ( $entries as $entry ) {
			$file = $GLOBALS['directory'] . '/uploads/2026/11/' . $entry['file'];
			$actual = getimagesize( $file );
			skye_media_check( $actual['mime'] === 'image/webp' && $actual[0] === $entry['width'] && $actual[1] === $entry['height'], 'Published sidecars have actual WebP MIME and exact crop dimensions.' );
		}
		$before = skye_media_test_outputs( 420 );
		$result = WebpGenerator::process( 420, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete' && skye_media_test_outputs( 420 ) === $before, 'Repeated completed work reuses verified files without duplicates.' );
		skye_media_test_sources_unchanged( $hashes );
		delete_post_meta( 420, MediaStore::PROGRESS_KEY );
		$result = WebpGenerator::process( 420, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete' && skye_media_test_outputs( 420 ) === $before, 'A published v1 manifest can be reused without any pending progress.' );

		$snapshot = skye_media_test_snapshot( 421, 'image/jpeg', 1 );
		$native = $snapshot['metadata'];
		$native['sizes']['same-native-file'] = $native['sizes']['size-1'];
		wp_update_attachment_metadata( 421, $native );
		$snapshot = MediaStore::snapshot( 421, $snapshot['revision'] );
		$result = WebpGenerator::process( 421, $snapshot['revision'], 3, 6 );
		$stored = wp_get_attachment_metadata( 421, true );
		skye_media_check( $result['status'] === 'complete' && count( skye_media_test_outputs( 421 ) ) === 2, 'Native size aliases encode one physical file only.' );
		skye_media_check( $stored['skye_webp']['sizes']['size-1'] === $stored['skye_webp']['sizes']['same-native-file'], 'Both native names retain identical exact crop alternatives.' );

		$snapshot = skye_media_test_snapshot( 422, 'image/png', 1 );
		$hashes = skye_media_test_hashes( $snapshot );
		$result = WebpGenerator::process( 422, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete', 'Native transparent PNG sources receive WebP alternatives: ' . json_encode( $result ) );
		$stored = wp_get_attachment_metadata( 422, true );
		$image = imagecreatefromwebp( $GLOBALS['directory'] . '/uploads/2026/11/' . $stored['skye_webp']['full']['file'] );
		$transparent = imagecolorsforindex( $image, imagecolorat( $image, 0, 0 ) );
		$opaque = imagecolorsforindex( $image, imagecolorat( $image, 5, 5 ) );
		skye_media_check( $transparent['alpha'] >= 120 && $opaque['alpha'] <= 5, 'Real encoded WebP retains transparent and opaque PNG pixels.' );
		unset( $image );
		skye_media_test_sources_unchanged( $hashes );

		$snapshot = skye_media_test_snapshot( 423, 'image/png', 0 );
		$path = get_attached_file( 423, true );
		$bytes = file_get_contents( $path );
		$chunk = 'acTL' . pack( 'NN', 2, 0 );
		$animated = substr( $bytes, 0, 33 ) . pack( 'N', 8 ) . $chunk . pack( 'N', crc32( $chunk ) ) . substr( $bytes, 33 );
		file_put_contents( $path, $animated );
		$result = WebpGenerator::process( 423, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'unsupported' && ( $result['error'] ?? '' ) === 'animated_png', 'An actual APNG acTL chunk is rejected before flattening animation.' );
		skye_media_check( file_get_contents( $path ) === $animated && skye_media_test_outputs( 423 ) === [], 'Unsupported animation retains its exact native file and has no sidecar.' );

		$snapshot = skye_media_test_snapshot( 424, 'image/jpeg', 2 );
		$result = WebpGenerator::process( 424, $snapshot['revision'], 0, 6 );
		skye_media_check( $result['status'] === 'pending' && skye_media_test_outputs( 424 ) === [], 'A zero file budget produces no output.' );
		$result = WebpGenerator::process( 424, $snapshot['revision'], 3, 0 );
		skye_media_check( $result['status'] === 'pending' && skye_media_test_outputs( 424 ) === [], 'An exhausted time budget produces no output.' );

		add_filter( 'wp_image_editors', 'skye_media_test_editors' );
		foreach ( [ 'none', 'fail' ] as $mode ) {
			$id = $mode === 'none' ? 425 : 426;
			$snapshot = skye_media_test_snapshot( $id, 'image/jpeg', 1 );
			$hashes = skye_media_test_hashes( $snapshot );
			$GLOBALS['skye_media_editor_mode'] = $mode;
			$result = WebpGenerator::process( $id, $snapshot['revision'], 3, 6 );
			skye_media_check( $result['status'] === 'retry', 'Unavailable/failed native encoding remains recoverable after a hosting repair.' );
			skye_media_check( empty( wp_get_attachment_metadata( $id, true )['skye_webp'] ) && skye_media_test_outputs( $id ) === [], 'Encoder failure leaves the original fallback and removes new uncommitted outputs.' );
			skye_media_test_sources_unchanged( $hashes );
		}
		unset( $GLOBALS['skye_media_editor_mode'] );
		remove_filter( 'wp_image_editors', 'skye_media_test_editors' );
		skye_media_check( ! has_filter( 'wp_editor_set_quality', [ WebpGenerator::class, 'webp_quality' ] ) && ! has_filter( 'image_editor_output_format', [ WebpGenerator::class, 'output_format' ] ), 'Temporary encoder filters are removed after native failures.' );

		add_filter( 'wp_editor_set_quality', 'skye_media_test_encode_race', 20, 2 );
		foreach ( [ 'source', 'revision' ] as $index => $mode ) {
			$id = 427 + $index;
			$snapshot = skye_media_test_snapshot( $id, 'image/jpeg', 0 );
			$GLOBALS['skye_media_encode_id'] = $id;
			$GLOBALS['skye_media_encode_race'] = $mode;
			$result = WebpGenerator::process( $id, $snapshot['revision'], 3, 6 );
			skye_media_check( $result['status'] === 'stale', 'A source/job change inside native encoding invalidates publication.' );
			skye_media_check( empty( wp_get_attachment_metadata( $id, true )['skye_webp'] ), 'A mid-encode change never advertises its stale output.' );
		}
		remove_filter( 'wp_editor_set_quality', 'skye_media_test_encode_race', 20 );

		$snapshot = skye_media_test_snapshot( 429, 'image/jpeg', 1 );
		$result = WebpGenerator::process( 429, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete', 'Legacy-reuse fixture first receives real verified WebP bytes.' );
		$published = wp_get_attachment_metadata( 429, true );
		$manifest = $published['skye_webp'];
		$folder = $GLOBALS['directory'] . '/uploads/2026/11/';
		foreach ( [ 'full', 'size-1' ] as $name ) {
			$entry = $name === 'full' ? $manifest['full'] : $manifest['sizes'][$name];
			$legacy = 'legacy-429-' . $name . '.webp';
			rename( $folder . $entry['file'], $folder . $legacy );
			if ( $name === 'full' ) {
				$manifest['full']['file'] = $legacy;
				$manifest['full']['filesize'] = (string) $entry['filesize'];
			} else {
				$manifest['sizes'][$name]['file'] = $legacy;
				$manifest['sizes'][$name]['filesize'] = (string) $entry['filesize'];
			}
		}
		$current = MediaStore::snapshot( 429, $snapshot['revision'] );
		skye_media_check( MediaStore::publish( $current, $manifest ), 'The actual old v1 naming scheme is accepted without adopting its files.' );
		delete_post_meta( 429, MediaStore::PROGRESS_KEY );
		$result = WebpGenerator::process( 429, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete' && skye_media_test_outputs( 429 ) === [], 'Verified beneficial legacy filenames and numeric-string byte counts are reused without new own-ID duplicates.' );
		file_put_contents( $folder . $manifest['full']['file'], 'corrupt WebP file' );
		$result = WebpGenerator::process( 429, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete' && count( skye_media_test_outputs( 429 ) ) === 1, 'A corrupted legacy output is replaced by one verified owned sidecar.' );
		skye_media_check( file_get_contents( $folder . $manifest['full']['file'] ) === 'corrupt WebP file', 'A corrupted unowned legacy file is never deleted or overwritten.' );

		$snapshot = skye_media_test_snapshot( 430, 'image/jpeg', 1 );
		$result = WebpGenerator::process( 430, $snapshot['revision'], 1, 6 );
		skye_media_check( $result['status'] === 'pending', 'Fingerprint fixture checkpoints a real first file.' );
		file_put_contents( get_attached_file( 430, true ), 'source changed between jobs', FILE_APPEND );
		$result = WebpGenerator::process( 430, $snapshot['revision'], 1, 6 );
		skye_media_check( $result['status'] === 'stale' && ( $result['error'] ?? '' ) === 'source_changed', 'Progress from different source bytes cannot be resumed as the current image.' );
		skye_media_check( empty( wp_get_attachment_metadata( 430, true )['skye_webp'] ), 'Mismatched progress never becomes a frontend source.' );
	}

	/**
	 * Return the actual native Cron events for one dispatcher, including arguments.
	 *
	 * @param string $hook Hook being inspected.
	 * @return array Timestamp and event records.
	 * @since 3.0.0
	 */
	function skye_media_test_events( $hook ) {

		$events = [];
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[$hook] ?? [] as $event ) {
				$events[] = [ 'timestamp' => $timestamp, 'event' => $event ];
			}
		}

		return $events;
	}

	/**
	 * Make a persistent job immediately due without changing its generation token.
	 *
	 * @param int $id Synthetic attachment whose worker should run now.
	 * @return string Exact new due revision.
	 * @since 3.0.0
	 */
	function skye_media_test_due( $id ) {

		$previous = get_post_meta( $id, MediaStore::PENDING_KEY, true );
		$revision = sprintf( '%010d:%s', time() - 1, substr( $previous, 11 ) );
		update_post_meta( $id, MediaStore::PENDING_KEY, $revision );
		return $revision;
	}

	/**
	 * Count durable pending attachments from actual SQL, without fetching contents.
	 *
	 * @return int Number of persistent pending rows.
	 * @since 3.0.0
	 */
	function skye_media_test_pending_count() {

		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM wp_postmeta WHERE meta_key = %s', MediaStore::PENDING_KEY ) );
	}

	/**
	 * Verify native metadata hooks, coalesced Cron, bounded discovery and recovery.
	 *
	 * @param MediaPipeline $pipeline Actual current production pipeline instance.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_pipeline( $pipeline ) {

		global $wpdb;

		skye_media_test_reset();
		skye_media_check( has_action( 'added_post_meta', [ $pipeline, 'metadata_saved' ] ) === 10 && has_action( 'updated_post_meta', [ $pipeline, 'metadata_saved' ] ) === 10, 'Both successful native metadata-write hooks are registered.' );
		skye_media_check( has_action( 'delete_attachment', [ $pipeline, 'deleted' ] ) === 10, 'Permanent deletion uses the native attachment hook.' );
		skye_media_check( has_filter( 'wp_generate_attachment_metadata', [ $pipeline, 'generated' ] ) === 20 && has_filter( 'image_editor_save_pre', [ $pipeline, 'editing' ] ) === 20, 'Regeneration and native editor saves retain a retry opportunity even for unchanged metadata.' );
		$pipeline->install();
		skye_media_check( (int) get_option( MediaPipeline::VERSION_OPTION ) === MediaPipeline::VERSION, 'First installation persists its native version marker.' );
		skye_media_check( count( skye_media_test_events( MediaPipeline::DISCOVER_HOOK ) ) === 1, 'Installation plans one background discovery event.' );
		$recovery = skye_media_test_events( MediaPipeline::RECOVER_HOOK );
		skye_media_check( count( $recovery ) === 1 && $recovery[0]['event']['schedule'] === 'hourly' && $recovery[0]['event']['args'] === [], 'One global hourly recovery event has no attachment arguments.' );
		$before = $wpdb->num_queries;
		for ( $index = 0; $index < 10; $index++ ) {
			$pipeline->install();
		}
		skye_media_check( $wpdb->num_queries === $before, 'Warm frontend installation checks perform no database query or library scan.' );

		skye_media_test_reset();
		$native = skye_media_test_attachment( 440 );
		$revisions = [];
		for ( $index = 0; $index < 8; $index++ ) {
			$native['image_meta']['caption'] = 'Native upload checkpoint ' . $index;
			skye_media_check( wp_update_attachment_metadata( 440, $native ) !== false, 'Native checkpoint persists before queue inspection.' );
			$revisions[] = get_post_meta( 440, MediaStore::PENDING_KEY, true );
		}
		skye_media_check( count( array_unique( $revisions ) ) === 8, 'Every genuine native metadata checkpoint has a fresh pending revision.' );
		skye_media_check( preg_match( '/\\A[0-9]{10}:0-[a-f0-9-]{36}\\z/', end( $revisions ) ) === 1, 'Native jobs have a due timestamp and explicit high-priority UUID generation.' );
		skye_media_test_attachment( 441 );
		update_post_meta( 441, 'unrelated_customer_free_fixture', 'test' );
		skye_media_check( get_post_meta( 441, MediaStore::PENDING_KEY, true ) === '', 'Unrelated metadata never queues an image job.' );
		$pipeline->queue( 441 );
		$pipeline->flush();
		$pipeline->flush();
		$events = skye_media_test_events( MediaPipeline::WORK_HOOK );
		skye_media_check( count( $events ) === 1 && $events[0]['event']['args'] === [], 'Many native checkpoints and attachments share one no-argument dispatcher.' );
		skye_media_check( skye_media_test_pending_count() === 2, 'Coalescing Cron preserves every persistent attachment job.' );

		$native = skye_media_test_attachment( 442, 'image/png', 0 );
		delete_post_meta( 442, '_wp_attachment_metadata' );
		skye_media_check( wp_update_attachment_metadata( 442, $native ) !== false, 'First native metadata save uses Core add_metadata.' );
		skye_media_check( get_post_meta( 442, MediaStore::PENDING_KEY, true ) !== '', 'The added_post_meta path queues a newly uploaded image.' );
		$before = get_post_meta( 442, MediaStore::PENDING_KEY, true );
		skye_media_check( apply_filters( 'wp_generate_attachment_metadata', $native, 442, 'update' ) === $native, 'Regeneration keeps the exact metadata value and context contract.' );
		skye_media_check( get_post_meta( 442, MediaStore::PENDING_KEY, true ) !== $before, 'Regeneration also queues when native metadata could compare equal.' );
		$editor = (object) [ 'synthetic' => true ];
		skye_media_check( apply_filters( 'image_editor_save_pre', $editor, 442 ) === $editor, 'The editor-save hook returns the exact native editor unchanged.' );

		skye_media_test_reset();
		skye_media_test_attachment( 443, 'image/jpeg', 0 );
		$pipeline->queue( 443 );
		$revision = get_post_meta( 443, MediaStore::PENDING_KEY, true );
		skye_media_check( MediaStore::acquire(), 'Own-publication fixture acquires the normal lease.' );
		$result = WebpGenerator::process( 443, $revision, 3, 6 );
		skye_media_check( $result['status'] === 'complete', 'Own-publication fixture performs a real complete encode.' );
		skye_media_check( get_post_meta( 443, MediaStore::PENDING_KEY, true ) === $revision, 'Native updated_post_meta actions from own publication never enqueue another revision.' );
		MediaStore::release();

		skye_media_test_reset();
		foreach ( range( 450, 454 ) as $id ) {
			skye_media_test_attachment( $id, 'image/jpeg', 0 );
			$pipeline->queue( $id );
			skye_media_test_due( $id );
		}
		$pipeline->work();
		$completed = 0;
		foreach ( range( 450, 454 ) as $id ) {
			$completed += ! empty( wp_get_attachment_metadata( $id, true )['skye_webp'] ) ? 1 : 0;
		}
		skye_media_check( $completed === 4 && skye_media_test_pending_count() === 1, 'One worker request processes at most four attachments and retains the remainder.' );
		skye_media_check( skye_media_test_lease() === null, 'Worker releases its global lease after bounded processing.' );
		skye_media_check( count( skye_media_test_events( MediaPipeline::WORK_HOOK ) ) === 1, 'Remaining durable work receives one follow-up dispatcher.' );

		skye_media_test_reset();
		skye_media_test_attachment( 455, 'image/jpeg', 0 );
		$pipeline->queue( 455 );
		$pipeline->flush();
		$revision = get_post_meta( 455, MediaStore::PENDING_KEY, true );
		$when = wp_next_scheduled( MediaPipeline::WORK_HOOK );
		wp_unschedule_event( $when, MediaPipeline::WORK_HOOK, [] );
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) === false, 'The simulated crash consumes the native single event before executing work.' );
		$pipeline->recover();
		skye_media_check( get_post_meta( 455, MediaStore::PENDING_KEY, true ) === $revision && wp_next_scheduled( MediaPipeline::WORK_HOOK ) !== false, 'Hourly recovery restores a consumed event without losing its durable job identity.' );
		skye_media_check( skye_media_test_outputs( 455 ) === [], 'Recovery schedules work without encoding in the discovery request.' );

		skye_media_test_reset();
		skye_media_test_attachment( 456, 'image/jpeg', 0 );
		$pipeline->queue( 456 );
		$revision = skye_media_test_due( 456 );
		unlink( get_attached_file( 456, true ) );
		$pipeline->work();
		$failure = get_post_meta( 456, MediaStore::FAILURE_KEY, true );
		$next = get_post_meta( 456, MediaStore::PENDING_KEY, true );
		skye_media_check( (int) ( $failure['attempts'] ?? 0 ) === 1 && substr( $next, 11 ) === substr( $revision, 11 ), 'A source failure defers the same generation and records the first attempt.' );
		skye_media_check( (int) substr( $next, 0, 10 ) >= time() + 59 && ! str_contains( json_encode( $failure ), $GLOBALS['directory'] ), 'Retry uses bounded backoff and stores no filesystem path.' );
		skye_media_test_due( 456 );
		$pipeline->work();
		$failure = get_post_meta( 456, MediaStore::FAILURE_KEY, true );
		$next = get_post_meta( 456, MediaStore::PENDING_KEY, true );
		skye_media_check( (int) $failure['attempts'] === 2 && (int) substr( $next, 0, 10 ) >= time() + 119, 'Repeated transient failure increases backoff without abandoning the original job.' );

		skye_media_test_reset();
		for ( $index = 0; $index < 45; $index++ ) {
			skye_media_test_attachment( 600 + 2 * $index, $index % 2 === 0 ? 'image/jpeg' : 'image/png', 0 );
		}
		$pipeline->queue( 600 );
		$priority_revision = get_post_meta( 600, MediaStore::PENDING_KEY, true );
		$pipeline->discover();
		$state = get_option( MediaPipeline::STATE_OPTION );
		skye_media_check( skye_media_test_pending_count() === 20 && $state['cursor'] === 638 && ! $state['complete'], 'The first discovery reads exactly twenty IDs with a stable keyset cursor.' );
		skye_media_check( get_post_meta( 600, MediaStore::PENDING_KEY, true ) === $priority_revision, 'Backfill never downgrades an already queued native upload.' );
		$pipeline->discover();
		$state = get_option( MediaPipeline::STATE_OPTION );
		skye_media_check( skye_media_test_pending_count() === 40 && $state['cursor'] === 678, 'The next discovery advances past gaps without offset drift or duplicate jobs.' );
		$pipeline->discover();
		$state = get_option( MediaPipeline::STATE_OPTION );
		skye_media_check( skye_media_test_pending_count() === 45 && $state['cursor'] === 688 && $state['complete'], 'The final partial batch marks a fully discovered library.' );
		$before = $wpdb->num_queries;
		$pipeline->discover();
		skye_media_check( $wpdb->num_queries <= $before + 1 && skye_media_test_pending_count() === 45, 'A completed cursor performs at most one empty ID-after probe without rehydrating the discovered library.' );
		skye_media_check( count( skye_media_test_events( MediaPipeline::WORK_HOOK ) ) === 1, 'A large discovered library still uses a single dispatcher event.' );
	}

	/**
	 * Keep discovery retryable until the current candidate has durable queued work.
	 *
	 * A failed middle write must not move the cursor past an unrecorded image.
	 * Later passes preserve acknowledged identities and bypass native tombstones.
	 *
	 * @param MediaPipeline $pipeline Actual current production scheduler.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_discovery_recovery( $pipeline ) {
		global $wpdb;
		foreach ( array( 'UPDATE', 'INSERT' ) as $verb ) {
			skye_media_test_reset();
			foreach ( range( 690, 693 ) as $id ) {
				skye_media_test_attachment( $id, 'image/jpeg', 0 );
			}
			update_post_meta( 692, MediaStore::DELETING_KEY, 1 );
			$wpdb->fail_enqueue = array( 'id' => 691, 'verb' => $verb );
			$pipeline->discover();
			$state = get_option( MediaPipeline::STATE_OPTION );
			$acknowledged = get_post_meta( 690, MediaStore::PENDING_KEY, true );
			skye_media_check( $wpdb->fail_enqueue === array(), 'The actual discovery path reaches the injected ' . $verb . ' failure.' );
			skye_media_check( $state['cursor'] === 690 && ! $state['complete'], 'A failed enqueue persists only the last acknowledged ID and leaves discovery incomplete.' );
			skye_media_check( $acknowledged !== '' && skye_media_test_pending_count() === 1 && get_post_meta( 693, MediaStore::PENDING_KEY, true ) === '', 'A failed middle candidate stops the batch without dropping earlier durable work.' );
			skye_media_check( wp_next_scheduled( MediaPipeline::DISCOVER_HOOK ) !== false, 'The incomplete batch receives a normal discovery retry event.' );
			$pipeline->discover();
			$state = get_option( MediaPipeline::STATE_OPTION );
			skye_media_check( $state['cursor'] === 693 && $state['complete'] && skye_media_test_pending_count() === 3, 'The next pass recovers the failed image and every later eligible candidate.' );
			skye_media_check( get_post_meta( 690, MediaStore::PENDING_KEY, true ) === $acknowledged && get_post_meta( 692, MediaStore::PENDING_KEY, true ) === '', 'Recovery preserves the earlier identity and excludes native deleting markers.' );
			skye_media_check( skye_media_test_outputs( 691 ) === array(), 'Recovery creates durable work without performing image conversion in discovery.' );
		}
	}

	/**
	 * Verify permanent deletion and the real encode/delete interleaving.
	 *
	 * @param MediaPipeline $pipeline Actual current production pipeline.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_delete( $pipeline ) {

		$snapshot = skye_media_test_snapshot( 460, 'image/jpeg', 1 );
		$hashes = skye_media_test_hashes( $snapshot );
		$result = WebpGenerator::process( 460, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete' && count( skye_media_test_outputs( 460 ) ) === 2, 'Delete fixture has actual published full/crop sidecars.' );
		MediaStore::release();
		do_action( 'delete_attachment', 460, get_post( 460 ) );
		skye_media_check( skye_media_test_outputs( 460 ) === [] && get_post_meta( 460, MediaStore::PENDING_KEY, true ) === '', 'Permanent delete removes own sidecars and invalidates the pending job.' );
		skye_media_check( get_post_meta( 460, MediaStore::DELETING_KEY, true ) == 1, 'Deleting tombstone fences an in-flight worker before Core removes the post.' );
		skye_media_test_sources_unchanged( $hashes );

		$snapshot = skye_media_test_snapshot( 461, 'image/jpeg', 0 );
		$hashes = skye_media_test_hashes( $snapshot );
		$GLOBALS['skye_media_encode_id'] = 461;
		$GLOBALS['skye_media_encode_race'] = 'delete';
		add_filter( 'wp_editor_set_quality', 'skye_media_test_encode_race', 20, 2 );
		$result = WebpGenerator::process( 461, $snapshot['revision'], 3, 6 );
		remove_filter( 'wp_editor_set_quality', 'skye_media_test_encode_race', 20 );
		skye_media_check( $result['status'] === 'stale' && get_post( 461 ) === null, 'Deletion inside the native encode prevents publication or attachment recreation.' );
		skye_media_check( skye_media_test_outputs( 461 ) === [], 'A late encoder save after deletion cleans its newly created sidecar.' );
		skye_media_test_sources_unchanged( $hashes );
	}

	/**
	 * Interleave deletion or a successor worker just after pending settlement.
	 *
	 * @param string           $query SQL which has actually finished.
	 * @param SKYE_Media_Test_DB $db Actual isolated SQL store.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_after_settle( $query, $db ) {

		if ( ! preg_match( '/^\\s*(?:UPDATE\\s+`?wp_postmeta`?\\s+j\\b|DELETE\\s+j(?:\\s*,\\s*s)?\\s+FROM\\s+`?wp_postmeta`?\\s+j\\b)/i', $query ) ) {
			$db->after_query = 'skye_media_test_after_settle';
			return;
		}

		$id = $GLOBALS['skye_media_settle_id'];
		$mode = $GLOBALS['skye_media_settle_mode'];
		if ( $mode === 'delete' ) {
			$db->pdo->exec( "DELETE FROM wp_postmeta WHERE post_id = $id" );
			$db->pdo->exec( "DELETE FROM wp_posts WHERE ID = $id" );
			wp_cache_delete( $id, 'posts' );
		} else {
			$revision = sprintf( '%010d:0-%s', time(), wp_generate_uuid4() );
			$progress = [ '_generation' => substr( $revision, 11 ), 'successor' => true, 'entries' => [] ];
			$failure = [ 'generation' => substr( $revision, 11 ), 'attempts' => 1, 'code' => 'successor_failure' ];
			$GLOBALS['skye_media_successor_progress'] = $progress;
			$GLOBALS['skye_media_successor_failure'] = $failure;
			foreach ( [ MediaStore::PENDING_KEY => $revision, MediaStore::PROGRESS_KEY => maybe_serialize( $progress ), MediaStore::FAILURE_KEY => maybe_serialize( $failure ) ] as $key => $value ) {
				$statement = $db->pdo->prepare( 'DELETE FROM wp_postmeta WHERE post_id = ? AND meta_key = ?' );
				$statement->execute( [ $id, $key ] );
				$statement = $db->pdo->prepare( 'INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES (?,?,?)' );
				$statement->execute( [ $id, $key, $value ] );
			}
			$statement = $db->pdo->prepare( 'UPDATE wp_options SET option_value = ? WHERE option_name = ?' );
			$statement->execute( [ sprintf( '%010d:%s', time() + MediaStore::LOCK_TTL, wp_generate_uuid4() ), MediaStore::LOCK_OPTION ] );
		}

		wp_cache_delete( $id, 'post_meta' );
	}

	/**
	 * Delete or tombstone an attachment immediately before its queue insertion.
	 *
	 * @param string           $query Actual SQL about to execute.
	 * @param SKYE_Media_Test_DB $db Actual isolated SQL store.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_queue_race( $query, $db ) {

		if ( ! preg_match( '/^\\s*INSERT(?:\\s+IGNORE)?\\s+INTO\\s+`?wp_postmeta`?\\b/i', $query ) || ! str_contains( $query, MediaStore::PENDING_KEY ) ) {
			$db->before_query = 'skye_media_test_queue_race';
			return;
		}

		$id = $GLOBALS['skye_media_queue_id'];
		if ( $GLOBALS['skye_media_queue_mode'] === 'delete' ) {
			$db->pdo->exec( "DELETE FROM wp_postmeta WHERE post_id = $id" );
			$db->pdo->exec( "DELETE FROM wp_posts WHERE ID = $id" );
			wp_cache_delete( $id, 'posts' );
		} else {
			$statement = $db->pdo->prepare( 'INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES (?,?,?)' );
			$statement->execute( [ $id, MediaStore::DELETING_KEY, '1' ] );
		}

		wp_cache_delete( $id, 'post_meta' );
	}

	/**
	 * Verify enqueue cannot cross a concurrent delete or native deleting tombstone.
	 *
	 * @param MediaPipeline $pipeline Actual current production pipeline.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_queue_races( $pipeline ) {

		global $wpdb;

		foreach ( [ 'delete', 'tombstone' ] as $index => $mode ) {
			skye_media_test_reset();
			$id = 472 + $index;
			skye_media_test_attachment( $id, 'image/jpeg', 0 );
			$GLOBALS['skye_media_queue_id'] = $id;
			$GLOBALS['skye_media_queue_mode'] = $mode;
			$wpdb->before_query = 'skye_media_test_queue_race';
			$pipeline->queue( $id );
			$wpdb->before_query = null;
			if ( $mode === 'delete' ) {
				skye_media_check( get_post( $id ) === null, 'The queue race actually deletes its attachment before insertion.' );
				skye_media_check( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM wp_postmeta WHERE post_id = %d', $id ) ) === 0, 'Enqueue cannot create orphan metadata after the concurrent delete.' );
			} else {
				skye_media_check( get_post_meta( $id, MediaStore::DELETING_KEY, true ) == 1, 'The queue race actually sets a native deleting tombstone.' );
				skye_media_check( get_post_meta( $id, MediaStore::PENDING_KEY, true ) === '', 'Enqueue respects a tombstone inserted after its initial checks.' );
			}
		}
	}

	/**
	 * Add a concurrently remembered own file before the tested ledger update.
	 *
	 * @param string           $query SQL about to execute.
	 * @param SKYE_Media_Test_DB $db Actual isolated SQL database.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_ledger_race( $query, $db ) {

		if ( ! preg_match( '/^\\s*UPDATE\\s+`?wp_postmeta`?\\b/i', $query ) || ! str_contains( $query, MediaStore::FILES_KEY ) ) {
			$db->before_query = 'skye_media_test_ledger_race';
			return;
		}

		$id = $GLOBALS['skye_media_ledger_id'] ?? 490;
		$statement = $db->pdo->prepare( 'SELECT meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key = ?' );
		$statement->execute( [ $id, MediaStore::FILES_KEY ] );
		$files = maybe_unserialize( $statement->fetchColumn() );
		$files[$GLOBALS['skye_media_concurrent_file']] = time();
		$statement = $db->pdo->prepare( 'UPDATE wp_postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = ?' );
		$statement->execute( [ maybe_serialize( $files ), $id, MediaStore::FILES_KEY ] );
		wp_cache_delete( $id, 'post_meta' );
	}

	/**
	 * Verify the deletion ledger merges concurrent changes instead of losing paths.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_ledger_merge() {

		global $wpdb;

		$snapshot = skye_media_test_snapshot( 490, 'image/jpeg', 0 );
		$GLOBALS['skye_media_ledger_id'] = 490;
		$first = '2026/11/first-skye-webp-490-q82-aaaaaaaaaaaa.webp';
		$second = '2026/11/second-skye-webp-490-q82-bbbbbbbbbbbb.webp';
		$third = '2026/11/third-skye-webp-490-q82-cccccccccccc.webp';
		skye_media_check( MediaStore::remember_file( $snapshot, $first ), 'The ledger merge fixture starts with one owned file.' );
		$GLOBALS['skye_media_concurrent_file'] = $second;
		$wpdb->before_query = 'skye_media_test_ledger_race';
		skye_media_check( MediaStore::remember_file( $snapshot, $third ), 'A concurrent ledger append is retried within the bounded CAS merge.' );
		$wpdb->before_query = null;
		$files = get_post_meta( 490, MediaStore::FILES_KEY, true );
		skye_media_check( isset( $files[$first], $files[$second], $files[$third] ), 'The final ownership ledger retains all three independently remembered paths.' );
	}

	/**
	 * Verify normal cleanup retention, published/progress protection and fresh CAS.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_retention() {

		global $wpdb;

		$snapshot = skye_media_test_snapshot( 491, 'image/jpeg', 1 );
		$result = WebpGenerator::process( 491, $snapshot['revision'], 1, 6 );
		skye_media_check( $result['status'] === 'pending', 'Retention fixture starts with one real resumable output.' );
		$progress = MediaStore::progress( $snapshot );
		$entry = reset( $progress['entries'] );
		$folder = $GLOBALS['directory'] . '/uploads/2026/11/';
		$relative = '2026/11/' . $entry['file'];
		$files = get_post_meta( 491, MediaStore::FILES_KEY, true );
		$files[$relative] = 0;
		update_post_meta( 491, MediaStore::FILES_KEY, $files );
		MediaStore::cleanup( 491 );
		skye_media_check( is_file( $folder . $entry['file'] ), 'Normal cleanup keeps a resumable output even with an old ledger timestamp.' );
		$result = WebpGenerator::process( 491, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete', 'Retention fixture completes its real native output set.' );
		$published = wp_get_attachment_metadata( 491, true );
		$current = MediaStore::snapshot( 491, $snapshot['revision'] );
		$expired = '2026/11/expired-skye-webp-491-q82-dddddddddddd.webp';
		$young = '2026/11/young-skye-webp-491-q82-eeeeeeeeeeee.webp';
		skye_media_check( MediaStore::remember_file( $current, $expired ) && MediaStore::remember_file( $current, $young ), 'Disposable retired outputs are inventoried before their test files are created.' );
		copy( $folder . $entry['file'], $GLOBALS['directory'] . '/uploads/' . $expired );
		copy( $folder . $entry['file'], $GLOBALS['directory'] . '/uploads/' . $young );
		$files = get_post_meta( 491, MediaStore::FILES_KEY, true );
		$files[$expired] = 0;
		$files[$relative] = 0;
		$files[$published['file']] = 0;
		update_post_meta( 491, MediaStore::FILES_KEY, $files );
		$GLOBALS['skye_media_ledger_id'] = 491;
		$GLOBALS['skye_media_concurrent_file'] = '2026/11/new-skye-webp-491-q82-ffffffffffff.webp';
		$wpdb->before_query = 'skye_media_test_ledger_race';
		skye_media_check( MediaStore::cleanup( 491 ), 'A young retired output retains its full retention interval.' );
		$wpdb->before_query = null;
		$files = get_post_meta( 491, MediaStore::FILES_KEY, true );
		skye_media_check( ! is_file( $GLOBALS['directory'] . '/uploads/' . $expired ) && ! isset( $files[$expired] ), 'Expired owned output and only its ledger entry are pruned.' );
		skye_media_check( is_file( $GLOBALS['directory'] . '/uploads/' . $young ), 'A younger retired output remains intact.' );
		skye_media_check( isset( $files[$GLOBALS['skye_media_concurrent_file']] ), 'Cleanup CAS preserves a simultaneously appended ownership entry.' );
		skye_media_check( is_file( $folder . $entry['file'] ) && is_file( $GLOBALS['directory'] . '/uploads/' . $published['file'] ), 'Cleanup never deletes the currently published sidecar or native original.' );

		$files[$relative] = 0;
		update_post_meta( 491, MediaStore::FILES_KEY, $files );
		$GLOBALS['skye_media_concurrent_file'] = '2026/11/retire-new-skye-webp-491-q82-111111111111.webp';
		$wpdb->before_query = 'skye_media_test_ledger_race';
		MediaStore::retire( 491, $published );
		$wpdb->before_query = null;
		$files = get_post_meta( 491, MediaStore::FILES_KEY, true );
		skye_media_check( $files[$relative] >= time() - 1 && isset( $files[$GLOBALS['skye_media_concurrent_file']] ), 'Retirement extends its timestamp and preserves a concurrent ledger addition.' );
		$files[$young] = 0;
		update_post_meta( 491, MediaStore::FILES_KEY, $files );
		MediaStore::release();
		skye_media_check( MediaStore::cleanup( 491 ) && is_file( $GLOBALS['directory'] . '/uploads/' . $young ), 'An unleased normal cleanup cannot delete even an expired output.' );
	}

	/**
	 * Ensure post-settlement cleanup cannot recreate or overwrite successor state.
	 *
	 * @param MediaPipeline $pipeline Actual current production pipeline.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_settle_races( $pipeline ) {

		global $wpdb;

		skye_media_test_reset();
		skye_media_test_attachment( 470, 'image/jpeg', 0 );
		$pipeline->queue( 470 );
		skye_media_test_due( 470 );
		unlink( get_attached_file( 470, true ) );
		$GLOBALS['skye_media_settle_id'] = 470;
		$GLOBALS['skye_media_settle_mode'] = 'delete';
		$wpdb->after_query = 'skye_media_test_after_settle';
		$pipeline->work();
		$wpdb->after_query = null;
		skye_media_check( get_post( 470 ) === null, 'The retry-settlement race actually deletes the post.' );
		skye_media_check( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_postmeta WHERE post_id = 470' ) === 0, 'Failure bookkeeping after a concurrent delete never recreates orphan metadata.' );

		skye_media_test_reset();
		skye_media_test_attachment( 471, 'image/jpeg', 0 );
		$pipeline->queue( 471 );
		skye_media_test_due( 471 );
		$GLOBALS['skye_media_settle_id'] = 471;
		$GLOBALS['skye_media_settle_mode'] = 'successor';
		$wpdb->after_query = 'skye_media_test_after_settle';
		$pipeline->work();
		$wpdb->after_query = null;
		skye_media_check( get_post_meta( 471, MediaStore::PROGRESS_KEY, true ) === $GLOBALS['skye_media_successor_progress'], 'A stale complete callback never deletes successor progress.' );
		skye_media_check( get_post_meta( 471, MediaStore::FAILURE_KEY, true ) === $GLOBALS['skye_media_successor_failure'], 'A stale complete callback never deletes successor failure state.' );
	}

	/**
	 * Keep viewport heuristics outside the isolated native attachment-rendering test.
	 *
	 * @return array Empty native loading override; explicit fixture attrs remain real.
	 * @since 3.0.0
	 */
	function skye_media_test_loading() {

		return [];
	}

	/**
	 * Fail immediately if a presentation request reaches worker filesystem/encoding.
	 *
	 * Retain only the selected native editor class for CI failure diagnostics;
	 * no source paths or customer data are added to this diagnostic record.
	 *
	 * @param string $function Native IO function invoked by an actual theme class.
	 * @param array  $arguments Original native arguments.
	 * @return mixed Unmodified native result outside the frontend-only phase.
	 * @throws RuntimeException On any worker IO while rendering.
	 * @since 3.0.0
	 */
	function skye_media_test_worker_io( $function, $arguments ) {

		if ( ! empty( $GLOBALS['skye_media_frontend'] ) ) {
			throw new RuntimeException( 'Frontend reached media worker IO: ' . $function );
		}

		$result = call_user_func_array( $function, $arguments );
		if ( 'wp_get_image_editor' === $function && is_object( $result ) ) {
			$GLOBALS['skye_media_last_editor'] = get_class( $result );
		}
		return $result;
	}

	/**
	 * Render real native JPEG/PNG attachments with the installed pipeline dormant.
	 *
	 * Namespace spies reject worker filesystem operations. These integration
	 * assertions keep the scheduler/encoder out
	 * of presentation and verify unchanged persistent state and warm query count.
	 *
	 * @param MediaPipeline $pipeline Actual installed background pipeline.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_frontend( $pipeline ) {

		global $wpdb;

		add_filter( 'pre_wp_get_loading_optimization_attributes', 'skye_media_test_loading' );
		foreach ( [ 'image/jpeg', 'image/png' ] as $index => $mime ) {
			$id = 480 + $index;
			$snapshot = skye_media_test_snapshot( $id, $mime, 1 );
			$result = WebpGenerator::process( $id, $snapshot['revision'], 3, 6 );
			skye_media_check( $result['status'] === 'complete', 'Frontend fixture is completely encoded before presentation.' );
			MediaStore::release();
			update_option( MediaPipeline::VERSION_OPTION, MediaPipeline::VERSION, true );
			$native = wp_get_attachment_image( $id, 'full', false, [ 'loading' => 'eager', 'fetchpriority' => 'high' ] );
			skye_media_check( str_contains( $native, '<picture' ) && str_contains( $native, 'type="image/webp"' ), 'Actual native JPEG/PNG rendering receives a verified WebP source.' );
			$before_metadata = wp_get_attachment_metadata( $id, true );
			$before_cron = _get_cron_array();
			$before_queries = $wpdb->num_queries;
			$GLOBALS['skye_media_frontend'] = true;
			try {
				$pipeline->install();
				for ( $repeat = 0; $repeat < 3; $repeat++ ) {
					skye_media_check( wp_get_attachment_image( $id, 'full', false, [ 'loading' => 'eager', 'fetchpriority' => 'high' ] ) === $native, 'Repeated native rendering stays byte-exact with the pipeline registered.' );
				}
			} finally {
				$GLOBALS['skye_media_frontend'] = false;
			}

			skye_media_check( $wpdb->num_queries === $before_queries, 'Warm native presentation performs no query, queue scan or persistent write.' );
			skye_media_check( _get_cron_array() === $before_cron && wp_get_attachment_metadata( $id, true ) === $before_metadata, 'Rendering changes neither Cron nor attachment metadata.' );
		}
		remove_filter( 'pre_wp_get_loading_optimization_attributes', 'skye_media_test_loading' );
	}

	/**
	 * Refuse cleanup scheduling while preserving the real native Cron datastore.
	 *
	 * @param mixed $pre Previous native short-circuit result.
	 * @param object $event Actual native event about to be persisted.
	 * @param bool $wp_error Whether detailed native failures were requested.
	 * @return mixed Previous result or a controlled native cleanup failure.
	 * @since 3.0.0
	 */
	function skye_media_test_cleanup_schedule_failure( $pre, $event, $wp_error ) {
		return $event->hook === MediaPipeline::CLEANUP_HOOK ? new WP_Error( 'fixture_cleanup_schedule', 'Synthetic cleanup scheduling failure.' ) : $pre;
	}

	/**
	 * Recover retired sidecars after native scheduling fails or its event is lost.
	 *
	 * Real inventory/metadata reads prove that published and resumable files need
	 * no new event. The keyset pass remains bounded and never inspects image files.
	 *
	 * @param MediaPipeline $pipeline Actual production scheduler.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_cleanup_recovery( $pipeline ) {
		global $wpdb;
		$snapshot = skye_media_test_snapshot( 906, 'image/png', 0 );
		$hashes = skye_media_test_hashes( $snapshot );
		skye_media_check( WebpGenerator::process( 906, $snapshot['revision'], 3, 6 )['status'] === 'complete', 'Cleanup recovery begins with a real published sidecar.' );
		MediaStore::release();
		$metadata = wp_get_attachment_metadata( 906, true );
		wp_update_attachment_metadata( 906, $metadata );
		$revision = get_post_meta( 906, MediaStore::PENDING_KEY, true );
		$settle = new ReflectionMethod( $pipeline, 'settle' );
		add_filter( 'pre_schedule_event', 'skye_media_test_cleanup_schedule_failure', 10, 3 );
		try {
			MediaStore::acquire();
			$settle->invoke( $pipeline, 906, $revision, array( 'status' => 'complete' ) );
			MediaStore::release();
			skye_media_check( get_post_meta( 906, MediaStore::PENDING_KEY, true ) === '' && wp_next_scheduled( MediaPipeline::CLEANUP_HOOK, array( 906 ) ) === false, 'A native scheduling failure occurs after the actual job is settled.' );
			$pipeline->recover();
			skye_media_check( wp_next_scheduled( MediaPipeline::CLEANUP_DISCOVER_HOOK ) !== false, 'Hourly recovery restores a metadata-only cleanup discovery event.' );
			$GLOBALS['skye_media_frontend'] = true;
			$pipeline->cleanup_discover();
			unset( $GLOBALS['skye_media_frontend'] );
			skye_media_check( (int) get_option( MediaPipeline::CLEANUP_STATE_OPTION ) === 0 && wp_next_scheduled( MediaPipeline::CLEANUP_HOOK, array( 906 ) ) === false, 'A repeated scheduling failure cannot acknowledge the retired attachment.' );
		} finally {
			unset( $GLOBALS['skye_media_frontend'] );
			remove_filter( 'pre_schedule_event', 'skye_media_test_cleanup_schedule_failure' );
			MediaStore::release();
		}
		$GLOBALS['skye_media_frontend'] = true;
		$pipeline->cleanup_discover();
		unset( $GLOBALS['skye_media_frontend'] );
		skye_media_check( wp_next_scheduled( MediaPipeline::CLEANUP_HOOK, array( 906 ) ) !== false && count( skye_media_test_outputs( 906 ) ) === 1, 'Metadata-only recovery repairs the missing event without deleting the retained file.' );
		$when = wp_next_scheduled( MediaPipeline::CLEANUP_HOOK, array( 906 ) );
		$pipeline->cleanup_discover();
		skye_media_check( count( skye_media_test_events( MediaPipeline::CLEANUP_HOOK ) ) === 1 && wp_next_scheduled( MediaPipeline::CLEANUP_HOOK, array( 906 ) ) === $when, 'An acknowledged native cleanup timer is retained without duplication or postponement.' );
		wp_unschedule_hook( MediaPipeline::CLEANUP_HOOK );
		$pipeline->cleanup_discover();
		skye_media_check( wp_next_scheduled( MediaPipeline::CLEANUP_HOOK, array( 906 ) ) !== false, 'A subsequently lost cleanup event is rediscovered from durable ownership alone.' );
		$pipeline->cleanup( 906 );
		skye_media_check( count( skye_media_test_outputs( 906 ) ) === 1, 'Recovered cleanup still honors the full retirement interval.' );
		$GLOBALS['skye_media_clock'] = time() + MediaStore::RETENTION + 2;
		$pipeline->cleanup( 906 );
		unset( $GLOBALS['skye_media_clock'] );
		skye_media_check( skye_media_test_outputs( 906 ) === array(), 'The recovered ordinary cleanup later removes the expired owned sidecar.' );
		skye_media_test_sources_unchanged( $hashes );

		skye_media_test_reset();
		foreach ( range( 9100, 9120 ) as $id ) {
			$metadata = skye_media_test_attachment( $id, 'image/jpeg', 0 );
			$metadata['skye_webp'] = skye_media_test_manifest( array( 'metadata' => $metadata, 'attachment_id' => $id ) );
			update_post_meta( $id, '_wp_attachment_metadata', $metadata );
			$relative = dirname( $metadata['file'] ) . '/' . $metadata['skye_webp']['full']['file'];
			update_post_meta( $id, MediaStore::FILES_KEY, array( $relative => 0 ) );
		}
		$files = get_post_meta( 9100, MediaStore::FILES_KEY, true );
		$files['2026/11/old-skye-native-9100-v1-0123456789ab.jpg'] = 0;
		update_post_meta( 9100, MediaStore::FILES_KEY, $files );
		update_post_meta( 9101, MediaStore::FILES_KEY, 'unproven inventory' );
		foreach ( array( 9102, 9103 ) as $id ) {
			$metadata = wp_get_attachment_metadata( $id, true );
			$entry = $metadata['skye_webp']['full'];
			unset( $metadata['skye_webp'] );
			update_post_meta( $id, '_wp_attachment_metadata', $metadata );
			if ( 9102 === $id ) {
				update_post_meta( $id, MediaStore::PROGRESS_KEY, array( 'entries' => array( 'full' => $entry ) ) );
			} else {
				update_post_meta( $id, '_wp_attachment_backup_sizes', array( 'full-orig' => $entry ) );
			}
		}
		$before = count( $wpdb->queries );
		$GLOBALS['skye_media_frontend'] = true;
		$pipeline->cleanup_discover();
		unset( $GLOBALS['skye_media_frontend'] );
		$queries = array_slice( $wpdb->queries, $before );
		$reads = 0;
		foreach ( $queries as $query ) {
			$reads += str_contains( $query, "meta_key = '_wp_attachment_metadata'" ) ? 1 : 0;
		}
		skye_media_check( (int) get_option( MediaPipeline::CLEANUP_STATE_OPTION ) === 9119 && $reads === 20, 'One recovery pass reads exactly twenty inventories and persists its keyset cursor.' );
		skye_media_check( skye_media_test_events( MediaPipeline::CLEANUP_HOOK ) === array(), 'Published, resumable, native and backup files plus corrupt unproven inventory never create redundant cleanup timers.' );
		wp_unschedule_hook( MediaPipeline::CLEANUP_DISCOVER_HOOK );
		$pipeline->cleanup_discover();
		skye_media_check( (int) get_option( MediaPipeline::CLEANUP_STATE_OPTION ) === 0 && skye_media_test_events( MediaPipeline::CLEANUP_DISCOVER_HOOK ) === array(), 'The final partial batch resets for a future hourly cycle without an endless follow-up loop.' );
		$wpdb->fail_inventory_read = true;
		$pipeline->cleanup_discover();
		skye_media_check( ! $wpdb->fail_inventory_read && (int) get_option( MediaPipeline::CLEANUP_STATE_OPTION ) === 0 && wp_next_scheduled( MediaPipeline::CLEANUP_DISCOVER_HOOK ) !== false, 'A genuine inventory read error retries before acknowledging that ID.' );
		$wpdb->fail_cleanup_scan_write = true;
		$pipeline->cleanup_discover();
		skye_media_check( ! $wpdb->fail_cleanup_scan_write && (int) get_option( MediaPipeline::CLEANUP_STATE_OPTION ) === 0, 'A failed cursor write leaves the previous safe checkpoint available for repetition.' );
		$pipeline->cleanup_discover();
		skye_media_check( (int) get_option( MediaPipeline::CLEANUP_STATE_OPTION ) === 9119 && skye_media_test_events( MediaPipeline::CLEANUP_HOOK ) === array(), 'Repeating acknowledged active inventories recovers cursor persistence without adding cleanup work.' );
		$pipeline->stop();
		skye_media_check( wp_next_scheduled( MediaPipeline::CLEANUP_DISCOVER_HOOK ) === false, 'Theme shutdown also removes the metadata-only recovery dispatcher.' );
	}

	/**
	 * Refuse selected native scheduling calls without replacing Core Cron storage.
	 *
	 * @param mixed  $pre Previous native short-circuit result.
	 * @param object $event Actual event Core is about to persist.
	 * @param bool   $wp_error Whether Core requested detailed errors.
	 * @return mixed Previous result, or a controlled native WP_Error.
	 * @since 3.0.0
	 */
	function skye_media_test_schedule_failure( $pre, $event, $wp_error ) {

		if ( $event->hook !== MediaPipeline::WORK_HOOK ) {
			return $pre;
		}
		$GLOBALS['skye_media_schedule_calls']++;
		if ( $GLOBALS['skye_media_schedule_mode'] === 'all' || $GLOBALS['skye_media_schedule_calls'] === 1 ) {
			return new WP_Error( 'fixture_schedule_failure', 'Synthetic Cron failure.' );
		}
		return $pre;
	}

	/**
	 * Refuse native dispatcher removal to exercise the retained-event failure path.
	 *
	 * @param mixed  $pre Previous native short-circuit result.
	 * @param int    $timestamp Actual scheduled UTC timestamp.
	 * @param string $hook Actual scheduled hook.
	 * @param array  $arguments Actual event arguments.
	 * @param bool   $wp_error Whether Core requested detailed errors.
	 * @return mixed Previous result, or a controlled native WP_Error.
	 * @since 3.0.0
	 */
	function skye_media_test_unschedule_failure( $pre, $timestamp, $hook, $arguments, $wp_error ) {

		return $hook === MediaPipeline::WORK_HOOK ? new WP_Error( 'fixture_unschedule_failure', 'Synthetic Cron failure.' ) : $pre;
	}

	/**
	 * Prepare a real deferred event and a fresh native upload needing earlier work.
	 *
	 * @param MediaPipeline $pipeline Actual pipeline under test.
	 * @param int           $id Synthetic attachment ID.
	 * @param int           $delay Existing dispatcher delay in seconds.
	 * @return array Existing timestamp and exact new upload revision.
	 * @since 3.0.0
	 */
	function skye_media_test_scheduler_case( $pipeline, $id, $delay ) {

		skye_media_test_reset();
		$pipeline->flush();
		wp_clear_scheduled_hook( MediaPipeline::WORK_HOOK );
		$previous = time() + $delay;
		skye_media_check( wp_schedule_single_event( $previous, MediaPipeline::WORK_HOOK ) === true, 'Native Cron stores the existing deferred dispatcher.' );
		skye_media_test_attachment( $id );
		$pipeline->queue( $id );
		return [ $previous, get_post_meta( $id, MediaStore::PENDING_KEY, true ) ];
	}

	/**
	 * Verify earlier native dispatch, failure recovery and exclusion of inert jobs.
	 *
	 * No production scheduler logic is copied: real Core persists each event and
	 * SQLite executes the actual worker/recovery predicates against synthetic posts.
	 *
	 * @param MediaPipeline $pipeline Actual current pipeline.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_scheduler( $pipeline ) {

		global $wpdb;

		foreach ( [ DAY_IN_SECONDS, 300 ] as $index => $delay ) {
			$id = 680 + $index;
			[ $previous, $revision ] = skye_media_test_scheduler_case( $pipeline, $id, $delay );
			$pipeline->flush();
			$events = skye_media_test_events( MediaPipeline::WORK_HOOK );
			skye_media_check( count( $events ) === 1 && $events[0]['event']['args'] === [] && $events[0]['timestamp'] <= time() + 5, 'A fresh upload advances a later dispatcher, including inside Core ten-minute deduplication.' );
			skye_media_check( get_post_meta( $id, MediaStore::PENDING_KEY, true ) === $revision, 'Advancing native Cron does not replace the durable upload identity.' );
			$pipeline->queue( $id );
			$before = _get_cron_array();
			$pipeline->flush();
			skye_media_check( _get_cron_array() === $before, 'An already earlier event remains unchanged when another upload is queued.' );
		}

		[ $previous, $revision ] = skye_media_test_scheduler_case( $pipeline, 682, 300 );
		$GLOBALS['skye_media_schedule_calls'] = 0;
		$GLOBALS['skye_media_schedule_mode'] = 'once';
		add_filter( 'pre_schedule_event', 'skye_media_test_schedule_failure', 10, 3 );
		$pipeline->flush();
		remove_filter( 'pre_schedule_event', 'skye_media_test_schedule_failure' );
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) === $previous && count( skye_media_test_events( MediaPipeline::WORK_HOOK ) ) === 1, 'A rejected replacement restores the old real event without a duplicate.' );
		$pipeline->flush();
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) <= time() + 5 && get_post_meta( 682, MediaStore::PENDING_KEY, true ) === $revision, 'A later flush retries the earlier request after the native scheduling failure clears.' );

		[ $previous, $revision ] = skye_media_test_scheduler_case( $pipeline, 683, 300 );
		add_filter( 'pre_unschedule_event', 'skye_media_test_unschedule_failure', 10, 5 );
		$pipeline->flush();
		remove_filter( 'pre_unschedule_event', 'skye_media_test_unschedule_failure' );
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) === $previous, 'Refused native removal keeps the existing dispatcher.' );
		$pipeline->flush();
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) <= time() + 5 && get_post_meta( 683, MediaStore::PENDING_KEY, true ) === $revision, 'The request retries safely after unscheduling becomes available.' );

		[ $previous, $revision ] = skye_media_test_scheduler_case( $pipeline, 684, 300 );
		$GLOBALS['skye_media_schedule_calls'] = 0;
		$GLOBALS['skye_media_schedule_mode'] = 'all';
		add_filter( 'pre_schedule_event', 'skye_media_test_schedule_failure', 10, 3 );
		$pipeline->flush();
		remove_filter( 'pre_schedule_event', 'skye_media_test_schedule_failure' );
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) === false && get_post_meta( 684, MediaStore::PENDING_KEY, true ) === $revision, 'Failure of both replacement and restoration leaves the durable job intact.' );
		$pipeline->recover();
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) <= time() + 5 && get_post_meta( 684, MediaStore::PENDING_KEY, true ) === $revision, 'Hourly recovery restores dispatch after native Cron writes become available.' );

		skye_media_test_reset();
		$pipeline->flush();
		wp_clear_scheduled_hook( MediaPipeline::WORK_HOOK );
		skye_media_test_attachment( 685 );
		$tombstone_revision = sprintf( '%010d:0-%s', time() - 5, wp_generate_uuid4() );
		update_post_meta( 685, MediaStore::DELETING_KEY, 1 );
		update_post_meta( 685, MediaStore::PENDING_KEY, $tombstone_revision );
		skye_media_test_attachment( 686 );
		$pipeline->queue( 686 );
		$changed_mime_revision = skye_media_test_due( 686 );
		$wpdb->update( $wpdb->posts, [ 'post_mime_type' => 'image/webp' ], [ 'ID' => 686 ] );
		wp_cache_delete( 686, 'posts' );
		$pipeline->flush();
		wp_clear_scheduled_hook( MediaPipeline::WORK_HOOK );
		$pipeline->recover();
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) === false, 'Recovery schedules no worker for only tombstones or attachments changed to an ineligible MIME.' );
		$before = count( $wpdb->queries );
		$pipeline->work();
		$selections = array_filter( array_slice( $wpdb->queries, $before ), 'skye_media_test_worker_selection' );
		skye_media_check( count( $selections ) === 1 && wp_next_scheduled( MediaPipeline::WORK_HOOK ) === false, 'An empty dispatcher makes one selection and does not requeue those inert jobs.' );
		skye_media_check( get_post_meta( 685, MediaStore::PENDING_KEY, true ) === $tombstone_revision && get_post_meta( 686, MediaStore::PENDING_KEY, true ) === $changed_mime_revision, 'Inert-job exclusion leaves native media and its pending metadata unchanged.' );
		skye_media_test_attachment( 687, 'image/png', 0 );
		$pipeline->queue( 687 );
		skye_media_test_due( 687 );
		$pipeline->work();
		skye_media_check( ! empty( wp_get_attachment_metadata( 687, true )['skye_webp'] ) && get_post_meta( 687, MediaStore::PENDING_KEY, true ) === '', 'Inert earlier rows do not block a real eligible PNG job.' );
	}

	/**
	 * Identify actual dispatcher selections for the bounded empty-work assertion.
	 *
	 * @param string $query SQL executed by the real pipeline.
	 * @return bool Whether this selects a pending attachment for processing.
	 * @since 3.0.0
	 */
	function skye_media_test_worker_selection( $query ) {

		return preg_match( '/SELECT\\s+j\\.post_id,\\s*j\\.meta_value\\s+FROM/i', $query ) === 1;
	}

	$images = new Images();
	/**
	 * Create a real portrait master while preserving the normal SQL fixture setup.
	 *
	 * @param int $id Synthetic attachment identifier.
	 * @param string $mime JPEG or PNG master format.
	 * @return array Native metadata for the untouched original fixture.
	 * @since 3.0.0
	 */
	function skye_media_test_large_native( $id, $mime = 'image/jpeg' ) {
		global $wpdb;
		$metadata = skye_media_test_attachment( $id, $mime, 0 );
		$path = get_attached_file( $id, true );
		skye_media_test_image( $path, $mime, 1200, 1800 );
		$metadata['width'] = 1200;
		$metadata['height'] = 1800;
		$metadata['filesize'] = filesize( $path );
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $metadata ) ), array( 'post_id' => $id, 'meta_key' => '_wp_attachment_metadata' ) );
		wp_cache_delete( $id, 'post_meta' );
		return $metadata;
	}

	/**
	 * Record shared commit notifications without storing image bytes or paths.
	 *
	 * @param int $meta_id Existing Core metadata row.
	 * @param int $id Attachment identifier.
	 * @param string $key Native metadata key.
	 * @param mixed $value Native action value.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_native_notice( $meta_id, $id, $key, $value ) {
		if ( $key === '_wp_attachment_metadata' ) {
			$GLOBALS['skye_native_notices'][] = array( current_filter(), MediaStore::is_publishing( $id ) );
		}
	}

	/**
	 * Veto a guarded metadata proposal through the real native filter contract.
	 *
	 * @param array $metadata Proposed native metadata.
	 * @return array Deliberately changed proposal that must never be committed.
	 * @since 3.0.0
	 */
	function skye_media_test_native_veto( $metadata ) {
		$metadata['plugin-veto'] = true;
		return $metadata;
	}

	/**
	 * Exercise a supported native output-format mapping without touching other saves.
	 *
	 * @param array $formats Current native mappings.
	 * @return array JPEG-to-PNG mapping used by a real native resize.
	 * @since 3.0.0
	 */
	function skye_media_test_native_format( $formats ) {
		$formats['image/jpeg'] = 'image/png';
		return $formats;
	}

	/**
	 * Verify additive backfill, active WebP preservation, SQL races, and Core restore.
	 *
	 * @param MediaPipeline $pipeline Actual production scheduler and worker.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_native_backfill( $pipeline ) {
		global $wpdb, $_wp_additional_image_sizes;
		$registered = $_wp_additional_image_sizes;
		foreach ( skye_media_test_sizes() as $name => $definition ) {
			add_image_size( $name, $definition['width'], $definition['height'], $definition['crop'] );
		}
		add_filter( 'pre_wp_get_loading_optimization_attributes', 'skye_media_test_loading' );
		try {
			skye_media_test_reset();
			$native = skye_media_test_large_native( 710 );
			$pipeline->queue( 710 );
			$revision = get_post_meta( 710, MediaStore::PENDING_KEY, true );
			skye_media_check( MediaStore::acquire(), 'Native migration fixture owns the normal shared worker lease.' );
			skye_media_check( WebpGenerator::process( 710, $revision, 32, 6 )['status'] === 'complete', 'The migration starts with a fully valid original WebP presentation.' );
			$before = wp_get_attachment_metadata( 710, true );
			$active = $before['skye_webp'];
			$master_hash = hash_file( 'sha256', get_attached_file( 710, true ) );
			$GLOBALS['skye_native_notices'] = array();
			foreach ( array( 'update_post_meta', 'update_postmeta', 'updated_post_meta', 'updated_postmeta' ) as $hook ) {
				add_action( $hook, 'skye_media_test_native_notice', 99, 4 );
			}
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 710, $revision, 99, 6 );
			foreach ( array( 'update_post_meta', 'update_postmeta', 'updated_post_meta', 'updated_postmeta' ) as $hook ) {
				remove_action( $hook, 'skye_media_test_native_notice', 99 );
			}
			$partial = wp_get_attachment_metadata( 710, true );
			skye_media_check( $result['status'] === 'pending' && $result['created'] === 2 && $result['attempted'] === 2 && count( $partial['sizes'] ) === 2, 'One native invocation cannot exceed two new sizes even when a larger limit is requested.' );
			skye_media_check( $partial['skye_webp'] === $active && $partial['file'] === $before['file'] && $partial['image_meta'] === $before['image_meta'], 'An additive commit retains the active namespace and every unrelated native field exactly.' );
			skye_media_check( ! Images::validate_webp_metadata( $partial ) && str_contains( wp_get_attachment_image( 710, 'full' ), '<picture' ), 'A partial native expansion is not a publishable complete replacement but keeps the previously verified full-size WebP presentation.' );
			skye_media_check( count( $GLOBALS['skye_native_notices'] ) === 4 && ! in_array( false, array_column( $GLOBALS['skye_native_notices'], 1 ), true ), 'Both writers share one native before/after action sequence under the own-publication guard.' );
			skye_media_check( get_post_meta( 710, MediaStore::PENDING_KEY, true ) === $revision, 'Own additive metadata publication never queues a replacement revision.' );
			$counts = array( 2 );
			for ( $pass = 0; $pass < 4 && $result['status'] === 'pending'; $pass++ ) {
				$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 710, $revision, 2, 6 );
				$counts[] = $result['created'];
				skye_media_check( wp_get_attachment_metadata( 710, true )['skye_webp'] === $active, 'Every resumable native batch keeps the original active WebP namespace.' );
			}
			$complete = wp_get_attachment_metadata( 710, true );
			skye_media_check( $result['status'] === 'complete' && $counts === array( 2, 2, 2, 1 ) && count( $complete['sizes'] ) === 7, 'Repeated bounded jobs automatically finish the complete seven-size schema.' );
			skye_media_check( $complete['sizes']['skye-content-sm']['width'] === 320 && $complete['sizes']['skye-content-sm']['height'] === 480 && $complete['sizes']['skye-card-sm']['width'] === 320 && $complete['sizes']['skye-card-sm']['height'] === 151, 'Actual native resizing preserves portrait ratio and the existing card crop family.' );
			skye_media_check( hash_file( 'sha256', get_attached_file( 710, true ) ) === $master_hash, 'Native backfill never changes original image bytes.' );
			skye_media_check( WebpGenerator::process( 710, $revision, 32, 6 )['status'] === 'complete' && count( wp_get_attachment_metadata( 710, true )['skye_webp']['sizes'] ) === 7, 'The existing WebP worker publishes the extended set after native backfill completes.' );
			skye_media_check( wp_get_attachment_metadata( 710, true )['skye_webp']['full'] === $active['full'], 'The complete replacement namespace reuses the previously verified original WebP file.' );
			$content = Images::webp_source( 710, 'full' );
			skye_media_check( str_contains( $content['srcset'], ' 320w' ) && str_contains( $content['srcset'], ' 480w' ) && str_contains( $content['srcset'], ' 800w' ) && ! str_contains( $content['srcset'], '-skye-card-' ) && ! str_contains( $content['srcset'], '-skye-news-' ), 'Real Core srcset exposes all three uncropped widths without mixing crop families.' );
			$card = Images::webp_source( 710, 'skye-card-sm' );
			$news = Images::webp_source( 710, 'skye-news-sm' );
			skye_media_check( str_contains( $card['srcset'], ' 320w' ) && str_contains( $card['srcset'], ' 480w' ) && ! str_contains( $card['srcset'], '-skye-content-' ), 'Card sources include the two compatible legacy-ratio crops only.' );
			skye_media_check( str_contains( $news['srcset'], ' 640w' ) && str_contains( $news['srcset'], ' 960w' ) && ! str_contains( $news['srcset'], '-skye-card-' ), 'News sources expose both smaller native 16:9 crops without card contamination.' );

			skye_media_test_reset();
			skye_media_test_large_native( 711, 'image/png' );
			$pipeline->queue( 711 );
			$revision = get_post_meta( 711, MediaStore::PENDING_KEY, true );
			MediaStore::acquire();
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 711, $revision, 2, 0 );
			skye_media_check( $result['status'] === 'pending' && $result['attempted'] === 0 && wp_get_attachment_metadata( 711, true )['sizes'] === array(), 'An exhausted shared budget performs no native encoding or metadata append.' );
			$GLOBALS['skye_native_microtime'] = array( microtime( true ), microtime( true ), microtime( true ), microtime( true ) + 10 );
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 711, $revision, 2, 1 );
			unset( $GLOBALS['skye_native_microtime'] );
			skye_media_check( $result['created'] === 1 && $result['status'] === 'pending', 'The shared soft deadline is checked again before the second native image operation.' );
			$png = wp_get_attachment_metadata( 711, true )['sizes']['skye-content-sm'];
			$image = imagecreatefrompng( dirname( get_attached_file( 711, true ) ) . '/' . $png['file'] );
			$pixel = skye_media_test_pixel( $image, imagesx( $image ) - 2, imagesy( $image ) - 2 );
			skye_media_check( $pixel['alpha'] >= 120, 'Real native PNG resizing preserves transparent source pixels: ' . json_encode( array( 'editor' => $GLOBALS['skye_media_last_editor'] ?? 'unknown', 'truecolor' => imageistruecolor( $image ), 'rgba' => $pixel, 'file' => $png['file'] ) ) );
			unset( $image );

			skye_media_test_reset();
			skye_media_test_large_native( 712, 'image/png' );
			$path = get_attached_file( 712, true );
			$bytes = file_get_contents( $path );
			$chunk = pack( 'N', 8 ) . 'acTL' . pack( 'NN', 2, 0 );
			$chunk .= pack( 'N', crc32( substr( $chunk, 4 ) ) );
			file_put_contents( $path, substr( $bytes, 0, 33 ) . $chunk . substr( $bytes, 33 ) );
			$pipeline->queue( 712 );
			$revision = get_post_meta( 712, MediaStore::PENDING_KEY, true );
			MediaStore::acquire();
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 712, $revision, 2, 6 );
			skye_media_check( $result['status'] === 'unsupported' && $result['attempted'] === 0 && wp_get_attachment_metadata( 712, true )['sizes'] === array(), 'The shared actual acTL parser prevents native backfill from flattening animated PNG sources.' );

			skye_media_test_reset();
			foreach ( array( 713, 714 ) as $id ) {
				skye_media_test_large_native( $id );
				$pipeline->queue( $id );
				$revision = skye_media_test_due( $id );
				MediaStore::acquire();
				WebpGenerator::process( $id, $revision, 32, 6 );
				MediaStore::release();
			}
			$pipeline->work();
			$total = count( wp_get_attachment_metadata( 713, true )['sizes'] ) + count( wp_get_attachment_metadata( 714, true )['sizes'] );
			skye_media_check( $total === 2 && skye_media_test_pending_count() === 2, 'The actual scheduled worker caps native work at two files across all attachments and retains durable continuation jobs.' );
			skye_media_check( ! empty( wp_get_attachment_metadata( 713, true )['skye_webp'] ) && ! empty( wp_get_attachment_metadata( 714, true )['skye_webp'] ), 'The actual worker keeps both previously active WebP presentations while native migration is pending.' );

			foreach ( array( 'delete_meta', 'delete_post', 'new_revision', 'new_core_size', 'new_pointer', 'deleting', 'new_owner', 'db_expiry' ) as $offset => $mode ) {
				skye_media_test_reset();
				$id = 720 + $offset;
				$native = skye_media_test_large_native( $id );
				$pipeline->queue( $id );
				$revision = get_post_meta( $id, MediaStore::PENDING_KEY, true );
				MediaStore::acquire();
				$GLOBALS['skye_media_race_id'] = $id;
				$GLOBALS['skye_media_race_mode'] = $mode;
				$GLOBALS['skye_media_race_metadata'] = $native;
				$wpdb->before_query = 'skye_media_test_publish_race';
				$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( $id, $revision, 2, 6 );
				$wpdb->before_query = null;
				$after = wp_get_attachment_metadata( $id, true );
				skye_media_check( $result['status'] !== 'complete' && empty( $after['sizes']['skye-content-sm'] ), 'Real SQL rejects native append after concurrent ' . $mode . '.' );
				if ( $mode === 'new_core_size' ) {
					skye_media_check( isset( $after['sizes']['concurrent-native'] ), 'A concurrent native Core addition is never overwritten by stale resize metadata.' );
				}
			}

			skye_media_test_reset();
			skye_media_test_large_native( 730 );
			$pipeline->queue( 730 );
			$revision = get_post_meta( 730, MediaStore::PENDING_KEY, true );
			MediaStore::acquire();
			add_filter( 'wp_update_attachment_metadata', 'skye_media_test_native_veto', 99 );
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 730, $revision, 2, 6 );
			remove_filter( 'wp_update_attachment_metadata', 'skye_media_test_native_veto', 99 );
			skye_media_check( $result['status'] === 'retry' && wp_get_attachment_metadata( 730, true )['sizes'] === array(), 'The shared commit honors a native metadata filter veto without persisting its altered payload.' );
			add_filter( 'image_editor_output_format', 'skye_media_test_native_format', 50 );
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 730, $revision, 2, 6 );
			remove_filter( 'image_editor_output_format', 'skye_media_test_native_format', 50 );
			$entry = wp_get_attachment_metadata( 730, true )['sizes']['skye-content-sm'] ?? array();
			skye_media_check( $result['created'] === 2 && ( $entry['mime-type'] ?? '' ) === 'image/png' && str_ends_with( $entry['file'], '.png' ), 'Ordinary JPEG-to-PNG output mapping is honored with verified exclusively reserved PNG paths.' );
			skye_media_check( ! has_filter( 'image_editor_output_format', array( \Rocketsky\Skye\Packages\Media\NativeSizes::class, 'output_format' ) ), 'Scoped native format guards are removed after both vetoes and successful saves.' );

			skye_media_test_reset();
			$original = skye_media_test_large_native( 731 );
			$pipeline->queue( 731 );
			$revision = get_post_meta( 731, MediaStore::PENDING_KEY, true );
			MediaStore::acquire();
			\Rocketsky\Skye\Packages\Media\NativeSizes::process( 731, $revision, 2, 6 );
			$metadata = wp_get_attachment_metadata( 731, true );
			$entry = $metadata['sizes']['skye-content-sm'];
			$folder = dirname( get_attached_file( 731, true ) );
			$backup = array( 'full-orig' => array( 'file' => wp_basename( $original['file'] ), 'width' => 1200, 'height' => 1800, 'filesize' => $original['filesize'] ), 'skye-content-sm-orig' => $entry );
			copy( get_attached_file( 731, true ), $folder . '/edited-native-fixture.jpg' );
			update_attached_file( 731, $folder . '/edited-native-fixture.jpg' );
			$metadata['file'] = '2026/11/edited-native-fixture.jpg';
			$metadata['sizes'] = array();
			$files = get_post_meta( 731, MediaStore::FILES_KEY, true );
			foreach ( $files as &$timestamp ) {
				$timestamp = 0;
			}
			unset( $timestamp );
			update_post_meta( 731, MediaStore::FILES_KEY, $files );
			$GLOBALS['skye_native_gap_path'] = $folder . '/' . $entry['file'];
			$GLOBALS['skye_native_gap_kept'] = false;
			add_action( 'updated_post_meta', 'skye_media_test_native_backup_gap', PHP_INT_MAX, 4 );
			wp_update_attachment_metadata( 731, $metadata );
			remove_action( 'updated_post_meta', 'skye_media_test_native_backup_gap', PHP_INT_MAX );
			skye_media_check( $GLOBALS['skye_native_gap_kept'], 'Cleanup injected after Core metadata replacement and before backup persistence cannot delete the future restore source.' );
			update_post_meta( 731, '_wp_attachment_backup_sizes', $backup );
			MediaStore::cleanup( 731 );
			skye_media_check( is_file( $folder . '/' . $entry['file'] ), 'Expired native ownership cannot delete a generated file referenced only by Core edit backups.' );
			require_once ABSPATH . 'wp-admin/includes/image-edit.php';
			$restored = wp_restore_image( 731 );
			skye_media_check( empty( $restored->error ) && wp_get_attachment_metadata( 731, true )['sizes']['skye-content-sm']['file'] === $entry['file'] && is_file( $folder . '/' . $entry['file'] ), 'Actual Core restore succeeds after cleanup and restores the retained generated native size.' );
			wp_delete_attachment_files( 731, wp_get_attachment_metadata( 731, true ), get_post_meta( 731, '_wp_attachment_backup_sizes', true ), get_attached_file( 731, true ) );
			skye_media_check( ! is_file( $folder . '/' . $entry['file'] ), 'Core permanent attachment-file deletion remains responsible for restored native and backup files.' );

			skye_media_test_reset();
			update_option( MediaPipeline::VERSION_OPTION, 1, true );
			update_option( MediaPipeline::STATE_OPTION, array( 'cursor' => 9999, 'complete' => true ), false );
			$pipeline->install();
			$state = get_option( MediaPipeline::STATE_OPTION );
			skye_media_check( $state['cursor'] === 0 && $state['version'] === MediaPipeline::VERSION, 'The current installation automatically restarts old completed library discovery once.' );
			$state['cursor'] = 42;
			update_option( MediaPipeline::STATE_OPTION, $state, false );
			$pipeline->install();
			skye_media_check( get_option( MediaPipeline::STATE_OPTION )['cursor'] === 42, 'A repeated warm installation preserves resumed migration discovery progress.' );
			skye_media_check( array_intersect_key( \Rocketsky\Skye\Packages\Media\NativeSizes::definitions(), skye_media_test_sizes() ) === skye_media_test_sizes(), 'The generic worker takes every test size from native registration rather than a fixed project crop list.' );
		} finally {
			remove_filter( 'pre_wp_get_loading_optimization_attributes', 'skye_media_test_loading' );
			$_wp_additional_image_sizes = $registered;
			unset( $GLOBALS['skye_native_microtime'] );
		}
	}

	/**
	 * Move the controlled worker clock past its budget during native preflight.
	 *
	 * @param array $missing Native missing-size result, returned unchanged.
	 * @return array Unchanged native missing-size result.
	 * @since 3.0.0
	 */
	function skye_media_test_native_preflight_delay( $missing ) {
		$GLOBALS['skye_native_microtime'] = array( microtime( true ) + 10 );
		return $missing;
	}

	/**
	 * Exercise a legitimate JPEG-to-WebP mapping handled by another image plugin.
	 *
	 * @param array $formats Native output-format mappings.
	 * @return array Mappings including JPEG-to-WebP.
	 * @since 3.0.0
	 */
	function skye_media_test_native_modern_format( $formats ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png'] = 'image/webp';
		return $formats;
	}

	/**
	 * Run cleanup in Core's actual metadata-before-backup editor ordering gap.
	 *
	 * @param int $meta_id Updated native metadata row.
	 * @param int $id Attachment being edited.
	 * @param string $key Native metadata key.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_native_backup_gap( $meta_id, $id, $key ) {
		if ( $id === 731 && $key === '_wp_attachment_metadata' && ! MediaStore::is_publishing( $id ) ) {
			MediaStore::cleanup( $id );
			$GLOBALS['skye_native_gap_kept'] = is_file( $GLOBALS['skye_native_gap_path'] );
		}
	}

	/**
	 * Invoke a real nested native resize from the outer editor quality callback.
	 *
	 * @param int $quality Native JPEG quality, returned unchanged.
	 * @param string $mime Current encoder format.
	 * @return int Unchanged native JPEG quality.
	 * @since 3.0.0
	 */
	function skye_media_test_native_nested( $quality, $mime ) {
		if ( $mime === 'image/jpeg' && empty( $GLOBALS['skye_native_nested_called'] ) ) {
			$GLOBALS['skye_native_nested_called'] = true;
			$GLOBALS['skye_native_nested_result'] = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 755, get_post_meta( 755, MediaStore::PENDING_KEY, true ), 2, 6 );
			$GLOBALS['skye_native_nested_guard'] = has_filter( 'image_editor_output_format', array( \Rocketsky\Skye\Packages\Media\NativeSizes::class, 'output_format' ) ) !== false;
		}
		return $quality;
	}

	/**
	 * Exercise nested metadata publication before the outer native metadata filter.
	 *
	 * @param array $metadata Native metadata proposal, returned unchanged.
	 * @param int $id Attachment being published.
	 * @return array Unchanged native metadata proposal.
	 * @since 3.0.0
	 */
	function skye_media_test_nested_metadata( $metadata, $id ) {
		if ( $id === 758 && empty( $GLOBALS['skye_native_meta_nested_called'] ) ) {
			$GLOBALS['skye_native_meta_nested_called'] = true;
			$GLOBALS['skye_native_meta_nested_result'] = WebpGenerator::process( 759, get_post_meta( 759, MediaStore::PENDING_KEY, true ), 32, 6 );
			$GLOBALS['skye_native_outer_publication_kept'] = MediaStore::is_publishing( 758 );
		}
		return $metadata;
	}

	/**
	 * Verify migration failure recovery, MIME compatibility and long UTF-8 names.
	 *
	 * @param MediaPipeline $pipeline Actual production pipeline.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_native_edges( $pipeline ) {
		global $wpdb, $_wp_additional_image_sizes;
		$registered = $_wp_additional_image_sizes;
		foreach ( skye_media_test_sizes() as $name => $definition ) {
			add_image_size( $name, $definition['width'], $definition['height'], $definition['crop'] );
		}
		try {
			skye_media_test_reset();
			update_option( MediaPipeline::VERSION_OPTION, 1, true );
			update_option( MediaPipeline::STATE_OPTION, array( 'cursor' => 9999, 'complete' => true ), false );
			$wpdb->fail_scan_write = true;
			$pipeline->install();
			skye_media_check( (int) get_option( MediaPipeline::VERSION_OPTION ) === 1 && get_option( MediaPipeline::STATE_OPTION )['cursor'] === 9999, 'A failed migration-state database write cannot publish the new version or abandon old library media.' );
			$pipeline->install();
			skye_media_check( (int) get_option( MediaPipeline::VERSION_OPTION ) === MediaPipeline::VERSION && get_option( MediaPipeline::STATE_OPTION )['cursor'] === 0, 'A later ordinary installation check recovers the failed state reset automatically.' );

			skye_media_test_reset();
			skye_media_test_large_native( 750 );
			$pipeline->queue( 750 );
			$revision = get_post_meta( 750, MediaStore::PENDING_KEY, true );
			MediaStore::acquire();
			add_filter( 'wp_get_missing_image_subsizes', 'skye_media_test_native_preflight_delay' );
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 750, $revision, 2, 1 );
			remove_filter( 'wp_get_missing_image_subsizes', 'skye_media_test_native_preflight_delay' );
			unset( $GLOBALS['skye_native_microtime'] );
			skye_media_check( $result['attempted'] === 0 && $result['status'] === 'pending', 'Native preflight time consumes the original worker budget rather than resetting its deadline.' );

			skye_media_test_reset();
			skye_media_test_large_native( 751 );
			$pipeline->queue( 751 );
			skye_media_test_due( 751 );
			add_filter( 'image_editor_output_format', 'skye_media_test_native_modern_format', 50 );
			$pipeline->work();
			remove_filter( 'image_editor_output_format', 'skye_media_test_native_modern_format', 50 );
			$metadata = wp_get_attachment_metadata( 751, true );
			$failure = get_post_meta( 751, MediaStore::FAILURE_KEY, true );
			skye_media_check( ! empty( $metadata['skye_webp']['full'] ) && $metadata['sizes'] === array(), 'A legitimate modern-format plugin does not block the existing WebP pipeline or silently force its native policy.' );
			skye_media_check( ( $failure['code'] ?? '' ) === 'native_output_format' && get_post_meta( 751, MediaStore::PENDING_KEY, true ) !== '', 'Unsupported native format migration remains explicit and durably retryable after old WebP work succeeds.' );

			skye_media_test_reset();
			$metadata = skye_media_test_large_native( 810, 'image/png' );
			$file = 'fixture-810-flat-20x18.png';
			$path = dirname( get_attached_file( 810, true ) ) . '/' . $file;
			skye_media_test_image( $path, 'image/png', 20, 18, true );
			$metadata['sizes']['flat-native'] = array( 'file' => $file, 'width' => 20, 'height' => 18, 'mime-type' => 'image/png', 'filesize' => filesize( $path ) );
			wp_update_attachment_metadata( 810, $metadata );
			$pipeline->queue( 810 );
			skye_media_test_due( 810 );
			add_filter( 'image_editor_output_format', 'skye_media_test_native_modern_format', 50 );
			try {
				$pipeline->work();
			} finally {
				remove_filter( 'image_editor_output_format', 'skye_media_test_native_modern_format', 50 );
			}
			$failure = get_post_meta( 810, MediaStore::FAILURE_KEY, true );
			skye_media_check( empty( wp_get_attachment_metadata( 810, true )['skye_webp'] ) && ( $failure['code'] ?? '' ) === 'native_output_format' && get_post_meta( 810, MediaStore::PENDING_KEY, true ) !== '', 'External native-format blocking stays recoverable even when WebP correctly declines its larger flat variant.' );
			for ( $attempt = 0; $attempt < 6 && get_post_meta( 810, MediaStore::PENDING_KEY, true ); $attempt++ ) {
				skye_media_test_due( 810 );
				$pipeline->work();
			}
			$restored = wp_get_attachment_metadata( 810, true );
			skye_media_check( count( array_intersect_key( $restored['sizes'], \Rocketsky\Skye\Packages\Media\NativeSizes::definitions() ) ) === 7 && $restored['sizes']['flat-native'] === $metadata['sizes']['flat-native'] && ! get_post_meta( 810, MediaStore::PENDING_KEY, true ), 'Removing the external format mapping resumes all seven native sizes automatically, then settles the ordinary no-gain decision terminally.' );

			skye_media_test_reset();
			foreach ( array( 754, 755 ) as $id ) {
				skye_media_test_large_native( $id );
				$pipeline->queue( $id );
			}
			MediaStore::acquire();
			$GLOBALS['skye_native_nested_called'] = false;
			add_filter( 'wp_editor_set_quality', 'skye_media_test_native_nested', 50, 2 );
			$outer = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 754, get_post_meta( 754, MediaStore::PENDING_KEY, true ), 2, 6 );
			remove_filter( 'wp_editor_set_quality', 'skye_media_test_native_nested', 50 );
			$total = count( wp_get_attachment_metadata( 754, true )['sizes'] ) + count( wp_get_attachment_metadata( 755, true )['sizes'] );
			skye_media_check( $GLOBALS['skye_native_nested_guard'] && $outer['created'] === 1 && $GLOBALS['skye_native_nested_result']['created'] === 1, 'A real nested native editor callback leaves the outer exact-path output guard registered.' );
			skye_media_check( $total === 2 && $outer['attempted'] === 2 && ! has_filter( 'image_editor_output_format', array( \Rocketsky\Skye\Packages\Media\NativeSizes::class, 'output_format' ) ), 'Nested native encoding shares the two-file allowance and removes its format filter only after the outer save finishes.' );

			skye_media_test_reset();
			skye_media_test_large_native( 758 );
			skye_media_test_attachment( 759, 'image/jpeg', 0 );
			$pipeline->queue( 758 );
			$pipeline->queue( 759 );
			MediaStore::acquire();
			$revision = get_post_meta( 758, MediaStore::PENDING_KEY, true );
			WebpGenerator::process( 758, $revision, 32, 6 );
			$active = wp_get_attachment_metadata( 758, true )['skye_webp'];
			$GLOBALS['skye_native_meta_nested_called'] = false;
			add_filter( 'wp_update_attachment_metadata', 'skye_media_test_nested_metadata', 5, 2 );
			$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( 758, $revision, 2, 6 );
			remove_filter( 'wp_update_attachment_metadata', 'skye_media_test_nested_metadata', 5 );
			skye_media_check( $GLOBALS['skye_native_outer_publication_kept'] && $GLOBALS['skye_native_meta_nested_result']['status'] === 'complete' && $result['created'] === 2 && wp_get_attachment_metadata( 758, true )['skye_webp'] === $active, 'Nested metadata filters restore the outer publication guard and preserve its active WebP namespace.' );

			foreach ( array( str_repeat( 'a', 205 ) . '.jpg', str_repeat( '😀', 40 ) . '.jpg' ) as $offset => $basename ) {
				skye_media_test_reset();
				$id = 752 + $offset;
				$metadata = skye_media_test_large_native( $id );
				$old_path = get_attached_file( $id, true );
				$path = dirname( $old_path ) . '/' . $basename;
				rename( $old_path, $path );
				$metadata['file'] = '2026/11/' . $basename;
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $metadata['file'] ), array( 'post_id' => $id, 'meta_key' => '_wp_attached_file' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $metadata ) ), array( 'post_id' => $id, 'meta_key' => '_wp_attachment_metadata' ) );
				wp_cache_delete( $id, 'post_meta' );
				$pipeline->queue( $id );
				$revision = get_post_meta( $id, MediaStore::PENDING_KEY, true );
				MediaStore::acquire();
				skye_media_check( WebpGenerator::process( $id, $revision, 32, 6 )['status'] === 'complete', 'Long original filenames remain encodable through the shared bounded stem.' );
				$before = wp_get_attachment_metadata( $id, true );
				$legacy = str_repeat( 'l', 180 ) . '-legacy.webp';
				$folder = dirname( $path );
				rename( $folder . '/' . $before['skye_webp']['full']['file'], $folder . '/' . $legacy );
				$before['skye_webp']['full']['file'] = $legacy;
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $before ) ), array( 'post_id' => $id, 'meta_key' => '_wp_attachment_metadata' ) );
				wp_cache_delete( $id, 'post_meta' );
				$result = \Rocketsky\Skye\Packages\Media\NativeSizes::process( $id, $revision, 2, 6 );
				$native = wp_get_attachment_metadata( $id, true );
				skye_media_check( $result['created'] === 2 && $native['file'] === $metadata['file'], 'Native backfill bounds generated names without renaming a long original pointer.' );
				foreach ( $native['sizes'] as $entry ) {
					skye_media_check( strlen( $entry['file'] ) < 255 && preg_match( '//u', $entry['file'] ) === 1 && is_file( $folder . '/' . $entry['file'] ), 'Actual native output is below the filesystem basename limit and valid UTF-8.' );
				}
				skye_media_check( WebpGenerator::process( $id, $revision, 32, 6 )['status'] === 'complete', 'The native-to-WebP suffix chain succeeds for long ASCII and four-byte UTF-8 source names.' );
				$webp = wp_get_attachment_metadata( $id, true )['skye_webp'];
				skye_media_check( $webp['full']['file'] === $legacy && $webp['full']['source'] === $basename, 'Valid legacy WebP filenames and exact original source fields remain unchanged.' );
				foreach ( $webp['sizes'] as $entry ) {
					skye_media_check( strlen( $entry['file'] ) < 255 && preg_match( '//u', $entry['file'] ) === 1, 'WebP outputs derived from bounded native names stay below 255 bytes with intact UTF-8.' );
				}
			}
		} finally {
			$_wp_additional_image_sizes = $registered;
			unset( $GLOBALS['skye_native_microtime'] );
		}
	}

	/**
	 * Replace synthetic PNG sources with real compressible flat graphics.
	 *
	 * @param array $snapshot Current PNG fixture and lease snapshot.
	 * @param bool $full Whether the full image also becomes flat, or only subsizes.
	 * @return array Fresh snapshot after the native metadata save.
	 * @since 3.0.0
	 */
	function skye_media_test_flat_png( array $snapshot, $full = true ) {
		$id = $snapshot['attachment_id'];
		$metadata = $snapshot['metadata'];
		$folder = dirname( get_attached_file( $id, true ) );
		if ( $full ) {
			$path = get_attached_file( $id, true );
			skye_media_test_image( $path, 'image/png', $metadata['width'], $metadata['height'], true );
			clearstatcache( true, $path );
			$metadata['filesize'] = filesize( $path );
		}
		foreach ( $metadata['sizes'] as &$size ) {
			$path = $folder . '/' . $size['file'];
			skye_media_test_image( $path, 'image/png', $size['width'], $size['height'], true );
			clearstatcache( true, $path );
			$size['filesize'] = filesize( $path );
		}
		unset( $size );
		wp_update_attachment_metadata( $id, $metadata );
		return MediaStore::snapshot( $id, get_post_meta( $id, MediaStore::PENDING_KEY, true ) );
	}

	/**
	 * Publish historical real WebP files without the newly introduced gain policy.
	 *
	 * Core's public editor creates valid quality-82 files. This fixture recreates
	 * a previously published namespace so migration can verify actual byte costs.
	 *
	 * @param array $snapshot Current synthetic sources and exclusive worker lease.
	 * @return array Fresh snapshot containing the historical namespace.
	 * @since 3.0.0
	 */
	function skye_media_test_historical_webp( array $snapshot ) {
		$id = $snapshot['attachment_id'];
		$metadata = $snapshot['metadata'];
		$folder = dirname( get_attached_file( $id, true ) );
		$manifest = skye_media_test_manifest( $snapshot );
		$entries = [ 'full' => $manifest['full'] ] + $manifest['sizes'];
		foreach ( $entries as $name => $entry ) {
			$source = $folder . '/' . $entry['source'];
			$entry['source_sha256'] = hash_file( 'sha256', $source );
			$entry['file'] = 'historical-' . pathinfo( $entry['source'], PATHINFO_FILENAME ) . '-skye-webp-' . $id . '-q82-' . substr( $entry['source_sha256'], 0, 12 ) . '.webp';
			$relative = dirname( $metadata['file'] ) . '/' . $entry['file'];
			skye_media_check( MediaStore::remember_file( $snapshot, $relative ), 'A historical sidecar is inventoried before its native save.' );
			$editor = wp_get_image_editor( $source );
			skye_media_check( ! is_wp_error( $editor ) && ! is_wp_error( $editor->set_quality( 82 ) ), 'The historical fixture uses the actual native image editor at its declared quality.' );
			$saved = $editor->save( $folder . '/' . $entry['file'], 'image/webp' );
			skye_media_check( ! is_wp_error( $saved ) && getimagesize( $saved['path'] )['mime'] === 'image/webp', 'Historical alternatives contain actual decodable WebP bytes.' );
			$entry['filesize'] = filesize( $saved['path'] );
			if ( $name === 'full' ) {
				$manifest['full'] = $entry;
			} else {
				$manifest['sizes'][$name] = $entry;
			}
		}
		skye_media_check( MediaStore::publish( $snapshot, $manifest ), 'The old complete namespace is stored through the production guarded metadata writer.' );
		return MediaStore::snapshot( $id, $snapshot['revision'] );
	}

	/**
	 * Grow a reused physical WebP during native decoding without changing its row.
	 *
	 * @param int $quality Native quality, returned unchanged.
	 * @param string $mime MIME being loaded by the real Core editor.
	 * @return int Unmodified quality.
	 * @since 3.0.0
	 */
	function skye_media_test_grow_webp( $quality, $mime ) {
		if ( $mime === 'image/webp' && isset( $GLOBALS['skye_media_grow_webp'] ) ) {
			$path = $GLOBALS['skye_media_grow_webp'];
			unset( $GLOBALS['skye_media_grow_webp'] );
			file_put_contents( $path, str_repeat( "\0", 16000 ), FILE_APPEND );
		}
		return $quality;
	}

	/**
	 * Prove complete-set byte gains, terminal native fallback and safe retirement.
	 *
	 * @param MediaPipeline $pipeline Actual production lifecycle and settlement.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_delivery_gain( $pipeline ) {
		global $wpdb;
		$snapshot = skye_media_test_snapshot( 780, 'image/png', 2 );
		$native = $snapshot['metadata'];
		$native['filesize'] = 1;
		foreach ( $native['sizes'] as &$size ) {
			$size['filesize'] = 1;
		}
		unset( $size );
		wp_update_attachment_metadata( 780, $native );
		$snapshot = MediaStore::snapshot( 780, get_post_meta( 780, MediaStore::PENDING_KEY, true ) );
		$result = WebpGenerator::process( 780, $snapshot['revision'], 3, 6 );
		skye_media_check( $result['status'] === 'complete', 'Photo-like native PNGs benefit even when Core filesize metadata is stale.' );
		$metadata = wp_get_attachment_metadata( 780, true );
		$folder = dirname( get_attached_file( 780, true ) );
		$totals = [ 'native' => 0, 'webp' => 0, 'variants' => 0 ];
		foreach ( array_merge( [ $metadata['skye_webp']['full'] ], array_values( $metadata['skye_webp']['sizes'] ) ) as $entry ) {
			$native_bytes = filesize( $folder . '/' . $entry['source'] );
			$webp_bytes = filesize( $folder . '/' . $entry['file'] );
			skye_media_check( $webp_bytes < $native_bytes && $entry['filesize'] === $webp_bytes, 'Every advertised PNG-derived alternative is physically smaller than its exact native source.' );
			$totals['native'] += $native_bytes;
			$totals['webp'] += $webp_bytes;
			$totals['variants']++;
		}
		$GLOBALS['skye_media_gain_evidence']['photo_png'] = $totals;

		$snapshot = skye_media_test_flat_png( skye_media_test_snapshot( 781, 'image/png', 2 ) );
		$native = $snapshot['metadata'];
		$native['filesize'] *= 100;
		foreach ( $native['sizes'] as &$size ) {
			$size['filesize'] *= 100;
		}
		unset( $size );
		wp_update_attachment_metadata( 781, $native );
		$snapshot = MediaStore::snapshot( 781, get_post_meta( 781, MediaStore::PENDING_KEY, true ) );
		$hashes = skye_media_test_hashes( $snapshot );
		$result = WebpGenerator::process( 781, $snapshot['revision'], 3, 6 );
		skye_media_check( $result === [ 'status' => 'unsupported', 'error' => 'not_smaller' ], 'A real flat PNG set terminates with native fallback instead of larger WebP requests.' );
		skye_media_check( wp_get_attachment_metadata( 781, true ) === $snapshot['metadata'] && skye_media_test_outputs( 781 ) === [], 'Declining a new set preserves exact native metadata and immediately cleans new unpublished files.' );
		skye_media_test_sources_unchanged( $hashes );
		$settle = new ReflectionMethod( MediaPipeline::class, 'settle' );
		$settle->invoke( $pipeline, 781, $snapshot['revision'], $result );
		skye_media_check( ! get_post_meta( 781, MediaStore::PENDING_KEY, true ) && ! get_post_meta( 781, MediaStore::PROGRESS_KEY, true ) && ! get_post_meta( 781, MediaStore::FAILURE_KEY, true ), 'Unsupported not_smaller is terminal and cannot leave an endless retry job.' );

		$snapshot = skye_media_test_flat_png( skye_media_test_snapshot( 782, 'image/png', 1 ), false );
		$snapshot = skye_media_test_historical_webp( $snapshot );
		$manifest = $snapshot['metadata']['skye_webp'];
		$folder = dirname( get_attached_file( 782, true ) );
		skye_media_check( filesize( $folder . '/' . $manifest['full']['file'] ) < filesize( get_attached_file( 782, true ) ) && filesize( $folder . '/' . $manifest['sizes']['size-1']['file'] ) > filesize( $folder . '/' . $manifest['sizes']['size-1']['source'] ), 'The mixed-gain fixture has a beneficial full image and one genuinely larger native-crop alternative.' );
		$files = get_post_meta( 782, MediaStore::FILES_KEY, true );
		foreach ( $files as &$timestamp ) {
			$timestamp = 0;
		}
		unset( $timestamp );
		update_post_meta( 782, MediaStore::FILES_KEY, $files );
		MediaStore::progress( $snapshot, [ 'fingerprint' => '', 'entries' => [ wp_basename( $snapshot['metadata']['file'] ) => $manifest['full'], $manifest['sizes']['size-1']['source'] => $manifest['sizes']['size-1'] ] ] );
		$before = $snapshot['metadata'];
		unset( $before['skye_webp'] );
		$result = WebpGenerator::process( 782, $snapshot['revision'], 0, 6 );
		skye_media_check( $result['status'] === 'unsupported' && ( $result['error'] ?? '' ) === 'not_smaller' && wp_get_attachment_metadata( 782, true ) === $before, 'One unfavorable reused entry withdraws the entire historical namespace through the shared writer while preserving every Core field.' );
		$settle->invoke( $pipeline, 782, $snapshot['revision'], $result );
		$files = get_post_meta( 782, MediaStore::FILES_KEY, true );
		foreach ( $files as $relative => $timestamp ) {
			skye_media_check( $timestamp >= time() - 1 && is_file( $GLOBALS['directory'] . '/uploads/' . $relative ), 'Previously published paths receive a fresh full retirement interval after terminal progress removal.' );
		}
		MediaStore::release();
		$GLOBALS['skye_media_clock'] = time() + MediaStore::RETENTION - 2;
		MediaStore::acquire();
		MediaStore::cleanup( 782 );
		skye_media_check( count( skye_media_test_outputs( 782 ) ) === 2, 'In-flight HTML sidecars survive until the complete twenty-four-hour retention period expires.' );
		MediaStore::release();
		$GLOBALS['skye_media_clock'] = time() + MediaStore::RETENTION + 2;
		MediaStore::acquire();
		MediaStore::cleanup( 782 );
		skye_media_check( skye_media_test_outputs( 782 ) === [] && is_file( get_attached_file( 782, true ) ), 'Ordinary later cleanup removes retired own WebP files while retaining the native original.' );

		$snapshot = skye_media_test_historical_webp( skye_media_test_snapshot( 783, 'image/png', 0 ) );
		$manifest = $snapshot['metadata']['skye_webp'];
		$path = dirname( get_attached_file( 783, true ) ) . '/' . $manifest['full']['file'];
		$source_bytes = filesize( get_attached_file( 783, true ) );
		file_put_contents( $path, str_repeat( "\0", $source_bytes - filesize( $path ) ), FILE_APPEND );
		clearstatcache( true, $path );
		$manifest['full']['filesize'] = filesize( $path );
		skye_media_check( $manifest['full']['filesize'] === $source_bytes && ! is_wp_error( wp_get_image_editor( $path ) ), 'The equality fixture is a real decodable WebP with exactly the native physical byte count.' );
		MediaStore::publish( $snapshot, $manifest );
		$result = WebpGenerator::process( 783, $snapshot['revision'], 0, 6 );
		skye_media_check( $result === [ 'status' => 'unsupported', 'error' => 'not_smaller' ] && empty( wp_get_attachment_metadata( 783, true )['skye_webp'] ), 'Equal-byte historical alternatives are rejected by the strict gain rule.' );

		$snapshot = skye_media_test_historical_webp( skye_media_test_snapshot( 788, 'image/png', 1 ) );
		$entry = $snapshot['metadata']['skye_webp']['full'];
		$GLOBALS['skye_media_grow_webp'] = dirname( get_attached_file( 788, true ) ) . '/' . $entry['file'];
		add_filter( 'wp_editor_set_quality', 'skye_media_test_grow_webp', 20, 2 );
		try {
			$result = WebpGenerator::process( 788, $snapshot['revision'], 0, 6 );
		} finally {
			remove_filter( 'wp_editor_set_quality', 'skye_media_test_grow_webp', 20 );
			unset( $GLOBALS['skye_media_grow_webp'] );
		}
		skye_media_check( $result === [ 'status' => 'retry', 'error' => 'output_changed' ] && wp_get_attachment_metadata( 788, true ) === $snapshot['metadata'], 'A reused output growing after its first validation cannot be advertised as a physically smaller new set.' );

		$snapshot = skye_media_test_snapshot( 789, 'image/png', 0 );
		$GLOBALS['skye_media_before_hash'] = realpath( get_attached_file( 789, true ) );
		try {
			$result = WebpGenerator::process( 789, $snapshot['revision'], 3, 6 );
		} finally {
			unset( $GLOBALS['skye_media_before_hash'] );
		}
		skye_media_check( $result === [ 'status' => 'stale', 'error' => 'source_changed' ] && empty( wp_get_attachment_metadata( 789, true )['skye_webp'] ), 'Source growth between its initial filesize and hash cannot validate an obsolete physical native byte count: ' . json_encode( $result ) );

		foreach ( [ 'read', 'write', 'malformed', 'new_core_size', 'veto' ] as $index => $mode ) {
			$id = 800 + $index;
			$snapshot = skye_media_test_historical_webp( skye_media_test_flat_png( skye_media_test_snapshot( $id, 'image/png', 0 ) ) );
			if ( $mode === 'read' ) {
				$entry = $snapshot['metadata']['skye_webp']['full'];
				$GLOBALS['skye_media_gain_evidence']['flat_png'] = [
					'native' => filesize( get_attached_file( $id, true ) ),
					'webp' => filesize( dirname( get_attached_file( $id, true ) ) . '/' . $entry['file'] ),
					'variants' => 1,
				];
			}
			$files = get_post_meta( $id, MediaStore::FILES_KEY, true );
			foreach ( $files as &$timestamp ) {
				$timestamp = $mode === 'malformed' ? null : 0;
			}
			unset( $timestamp );
			update_post_meta( $id, MediaStore::FILES_KEY, $files );
			$wpdb->fail_inventory_read = $mode === 'read';
			$wpdb->fail_inventory_writes = $mode === 'write' ? 3 : 0;
			if ( $mode === 'new_core_size' ) {
				$GLOBALS['skye_media_race_id'] = $id;
				$GLOBALS['skye_media_race_mode'] = $mode;
				$GLOBALS['skye_media_race_metadata'] = $snapshot['metadata'];
				$wpdb->before_query = 'skye_media_test_publish_race';
			}
			if ( $mode === 'veto' ) {
				add_filter( 'wp_update_attachment_metadata', 'skye_media_test_native_veto' );
			}
			try {
				$result = WebpGenerator::process( $id, $snapshot['revision'], 0, 6 );
			} finally {
				remove_filter( 'wp_update_attachment_metadata', 'skye_media_test_native_veto' );
			}
			$wpdb->before_query = null;
			$metadata = wp_get_attachment_metadata( $id, true );
			skye_media_check( in_array( $result['status'], [ 'retry', 'stale' ], true ) && $metadata['skye_webp'] === $snapshot['metadata']['skye_webp'] && count( skye_media_test_outputs( $id ) ) === 1, 'A failed retirement or lost withdrawal CAS preserves the previous namespace and its files: ' . $mode );
			if ( $mode === 'new_core_size' ) {
				skye_media_check( isset( $metadata['sizes']['concurrent-native'] ), 'A native size committed at the final withdrawal SQL boundary cannot be overwritten.' );
			}
		}

		skye_media_test_reset();
		update_option( MediaPipeline::VERSION_OPTION, 2, true );
		update_option( MediaPipeline::STATE_OPTION, [ 'cursor' => 9999, 'complete' => true, 'version' => 2 ], false );
		$pipeline->install();
		$state = get_option( MediaPipeline::STATE_OPTION );
		skye_media_check( MediaPipeline::VERSION === 3 && (int) get_option( MediaPipeline::VERSION_OPTION ) === 3 && $state === [ 'cursor' => 0, 'complete' => false, 'version' => 3 ], 'Version three automatically reevaluates the complete existing library after version two.' );
	}

	/**
	 * Disable the actual media policy without touching native callback behavior.
	 *
	 * @return bool Always false for the explicit disabled-policy phase.
	 * @since 3.0.0
	 */
	function skye_media_test_disabled() {
		return false;
	}

	/**
	 * Inject an actual Core unscheduling refusal to verify retry ownership.
	 *
	 * @param mixed $pre Existing native short-circuit result.
	 * @param string $hook Hook being unscheduled.
	 * @return mixed False for the tested worker hook, unchanged for all other hooks.
	 * @since 3.0.0
	 */
	function skye_media_test_policy_unschedule_failure( $pre, $hook ) {
		return MediaPipeline::WORK_HOOK === $hook ? false : $pre;
	}

	/**
	 * Verify automatic start, safe disable, retry, and reenable through native Cron.
	 *
	 * Disabling optimization must leave originals and durable jobs intact while
	 * avoiding new work. This exercises the live policy rather than a test copy.
	 *
	 * @param MediaPipeline $pipeline Actual production scheduler and worker.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_policy( $pipeline ) {
		global $wpdb;
		skye_media_test_reset();
		skye_media_test_attachment( 901 );
		skye_media_check( MediaPipeline::enabled(), 'The generic media optimization is enabled without an extra activation step.' );
		$pipeline->install();
		$pipeline->queue( 901 );
		$pipeline->flush();
		$pending = get_post_meta( 901, MediaStore::PENDING_KEY, true );
		$state = array( 'version' => MediaPipeline::VERSION, 'cursor' => 23, 'complete' => false );
		update_option( MediaPipeline::STATE_OPTION, $state, false );
		wp_schedule_single_event( time() + 60, MediaPipeline::CLEANUP_HOOK, array( 901 ) );
		skye_media_check( wp_next_scheduled( MediaPipeline::WORK_HOOK ) !== false && wp_next_scheduled( MediaPipeline::RECOVER_HOOK ) !== false, 'Native dispatcher and hourly recovery exist before disabling.' );
		add_filter( 'skye_media_pipeline_enabled', 'skye_media_test_disabled' );
		add_filter( 'pre_unschedule_hook', 'skye_media_test_policy_unschedule_failure', 10, 2 );
		$pipeline->install();
		skye_media_check( (string) get_option( MediaPipeline::VERSION_OPTION ) === '0' && wp_next_scheduled( MediaPipeline::WORK_HOOK ) !== false, 'An unscheduling failure retains a pending-transition marker for an automatic retry.' );
		remove_filter( 'pre_unschedule_hook', 'skye_media_test_policy_unschedule_failure' );
		remove_filter( 'skye_media_pipeline_enabled', 'skye_media_test_disabled' );
		$pipeline->install();
		skye_media_check( wp_next_scheduled( MediaPipeline::RECOVER_HOOK ) !== false && (int) get_option( MediaPipeline::VERSION_OPTION ) === MediaPipeline::VERSION, 'Reenabling during a partial failed shutdown restores the already removed recovery event.' );
		add_filter( 'skye_media_pipeline_enabled', 'skye_media_test_disabled' );
		$pipeline->install();
		skye_media_check( get_option( MediaPipeline::VERSION_OPTION, false ) === false && get_option( MediaPipeline::STATE_OPTION ) === $state, 'Successful disable removes the marker but preserves the discovery cursor.' );
		foreach ( array( MediaPipeline::WORK_HOOK, MediaPipeline::DISCOVER_HOOK, MediaPipeline::RECOVER_HOOK, MediaPipeline::CLEANUP_HOOK ) as $hook ) {
			$args = MediaPipeline::CLEANUP_HOOK === $hook ? array( 901 ) : array();
			skye_media_check( wp_get_scheduled_event( $hook, $args ) === false, 'Disabled optimization leaves no dispatcher or per-attachment event: ' . $hook );
		}
		skye_media_check( get_post_meta( 901, MediaStore::PENDING_KEY, true ) === $pending && is_file( get_attached_file( 901, true ) ), 'Disabling preserves the exact durable job and native original.' );
		$before = count( $wpdb->queries );
		$pipeline->queue( 901 );
		$pipeline->flush();
		$pipeline->work();
		$pipeline->discover();
		$pipeline->recover();
		$pipeline->cleanup( 901 );
		skye_media_check( Images::webp_source( 901 ) === false && count( $wpdb->queries ) === $before, 'Disabled queue, dispatch, discovery, cleanup and presentation do no database or file work.' );
		remove_filter( 'skye_media_pipeline_enabled', 'skye_media_test_disabled' );
		$pipeline->install();
		skye_media_check( (int) get_option( MediaPipeline::VERSION_OPTION ) === MediaPipeline::VERSION && get_option( MediaPipeline::STATE_OPTION ) === $state && get_post_meta( 901, MediaStore::PENDING_KEY, true ) === $pending, 'Reenable restores scheduling without losing or resetting resumable state.' );
		do_action( 'switch_theme' );
		skye_media_check( wp_next_scheduled( MediaPipeline::RECOVER_HOOK ) === false && get_option( MediaPipeline::VERSION_OPTION, false ) === false, 'A native theme switch unschedules the old theme worker.' );
		$wpdb->fail_version_write = true;
		$pipeline->install();
		skye_media_check( get_option( MediaPipeline::VERSION_OPTION, false ) === false && wp_next_scheduled( MediaPipeline::RECOVER_HOOK ) === false && wp_next_scheduled( MediaPipeline::DISCOVER_HOOK ) === false, 'Failed first marker persistence creates no orphan dispatcher or recurring event.' );
		update_option( MediaPipeline::STATE_OPTION, 'corrupt' );
		$pipeline->install();
		skye_media_check( get_option( MediaPipeline::STATE_OPTION ) === array( 'cursor' => 0, 'complete' => false, 'version' => MediaPipeline::VERSION ), 'A corrupt installation state is replaced through the normal checked setup path.' );
	}

	/**
	 * Verify new native crop definitions restart discovery without render work.
	 *
	 * Registration remains a Core/theme responsibility; the generic scheduler
	 * compares dimensions only during explicit admin or recovery callbacks.
	 *
	 * @param MediaPipeline $pipeline Actual production scheduler.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_size_changes( $pipeline ) {
		global $_wp_additional_image_sizes;
		$registered = $_wp_additional_image_sizes;
		try {
			skye_media_test_reset();
			$pipeline->install();
			$state = array( 'cursor' => 42, 'complete' => true, 'version' => MediaPipeline::VERSION );
			update_option( MediaPipeline::STATE_OPTION, $state, false );
			$pipeline->sync_sizes();
			skye_media_check( get_option( MediaPipeline::STATE_OPTION ) === $state, 'Repeated native size configuration preserves completed discovery progress.' );
			add_image_size( 'fixture-configured-landscape', 777, 333, true );
			do_action( 'admin_init' );
			skye_media_check( get_option( MediaPipeline::STATE_OPTION ) === array( 'cursor' => 0, 'complete' => false, 'version' => MediaPipeline::VERSION ) && isset( get_option( MediaPipeline::SIZES_OPTION )['fixture-configured-landscape'] ), 'A new registered crop automatically restarts bounded discovery through the actual admin hook.' );
			update_option( MediaPipeline::STATE_OPTION, $state, false );
			unset( $_wp_additional_image_sizes['fixture-configured-landscape'] );
			$pipeline->sync_sizes();
			skye_media_check( get_option( MediaPipeline::STATE_OPTION ) === $state, 'A temporary missing registration does not erase the known crop or rescan the library.' );
			add_image_size( 'fixture-configured-landscape', 778, 334, true );
			$pipeline->recover();
			skye_media_check( get_option( MediaPipeline::STATE_OPTION )['cursor'] === 0 && get_option( MediaPipeline::SIZES_OPTION )['fixture-configured-landscape']['width'] === 778, 'The normal recovery callback detects a real dimension change without a manual regeneration step.' );
		} finally {
			$_wp_additional_image_sizes = $registered;
		}
	}

	/**
	 * Preserve in-flight URLs when a successful native edit outlives ledger failure.
	 *
	 * The native edit must succeed while its fallback retention deadline becomes
	 * durable in the same metadata row. Real cleanup then honors that deadline,
	 * carries it through later edits and rejects unknown corrupt expiry values.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_retirement_failure() {
		global $wpdb;
		foreach ( array( 'read', 'write' ) as $index => $mode ) {
			$id = 903 + $index;
			$snapshot = skye_media_test_snapshot( $id, 'image/png', 0 );
			skye_media_check( WebpGenerator::process( $id, $snapshot['revision'], 3, 6 )['status'] === 'complete', 'Retirement-failure fixture begins with an actual published WebP.' );
			MediaStore::release();
			$before = wp_get_attachment_metadata( $id, true );
			$relative = dirname( $before['file'] ) . '/' . $before['skye_webp']['full']['file'];
			$path = $GLOBALS['directory'] . '/uploads/' . $relative;
			$files = get_post_meta( $id, MediaStore::FILES_KEY, true );
			$files[$relative] = 0;
			update_post_meta( $id, MediaStore::FILES_KEY, $files );
			$incoming = $before;
			$incoming['image_meta']['caption'] = 'Native edit must succeed';
			if ( 'read' === $mode ) {
				$wpdb->fail_inventory_read = true;
			} else {
				$wpdb->fail_inventory_writes = 3;
			}
			skye_media_check( (bool) wp_update_attachment_metadata( $id, $incoming ), 'The native edit succeeds despite retirement-inventory ' . $mode . ' failure.' );
			$after = wp_get_attachment_metadata( $id, true );
			skye_media_check( empty( $after['skye_webp'] ) && $after['image_meta']['caption'] === 'Native edit must succeed' && ( $after[MediaStore::RETIREMENT_KEY][$relative] ?? 0 ) >= time() - 1, 'The same native metadata write stores the edit and a conservative own-ID deadline.' );
			skye_media_check( get_post_meta( $id, MediaStore::FILES_KEY, true )[$relative] === 0, 'The failure actually leaves an old inventory timestamp; the regression does not fake a successful ledger write.' );
			MediaStore::acquire();
			skye_media_check( MediaStore::cleanup( $id ) && is_file( $path ), 'Immediate real cleanup cannot delete an old sidecar inside its newly protected retention window.' );
			MediaStore::release();
			unset( $incoming['skye_webp'], $incoming[MediaStore::RETIREMENT_KEY] );
			$incoming['image_meta']['caption'] = 'Second native edit';
			wp_update_attachment_metadata( $id, $incoming );
			skye_media_check( wp_get_attachment_metadata( $id, true )[MediaStore::RETIREMENT_KEY] === $after[MediaStore::RETIREMENT_KEY], 'A later native write that omits own fields still carries the durable fallback deadlines.' );
			$GLOBALS['skye_media_clock'] = time() + MediaStore::RETENTION + 2;
			MediaStore::acquire();
			skye_media_check( ! MediaStore::cleanup( $id ) && ! is_file( $path ) && is_file( get_attached_file( $id, true ) ), 'After the full fallback interval cleanup removes only the owned retired output and preserves the native original.' );
			MediaStore::release();
		}
		$snapshot = skye_media_test_snapshot( 905, 'image/png', 0 );
		WebpGenerator::process( 905, $snapshot['revision'], 3, 6 );
		MediaStore::release();
		$metadata = wp_get_attachment_metadata( 905, true );
		$relative = dirname( $metadata['file'] ) . '/' . $metadata['skye_webp']['full']['file'];
		$path = $GLOBALS['directory'] . '/uploads/' . $relative;
		$files = get_post_meta( 905, MediaStore::FILES_KEY, true );
		$files[$relative] = 0;
		update_post_meta( 905, MediaStore::FILES_KEY, $files );
		unset( $metadata['skye_webp'] );
		$metadata[MediaStore::RETIREMENT_KEY] = array( $relative => 'unproven expiry' );
		update_post_meta( 905, '_wp_attachment_metadata', $metadata );
		$GLOBALS['skye_media_clock'] = time() + 3 * MediaStore::RETENTION;
		MediaStore::acquire();
		skye_media_check( MediaStore::cleanup( 905 ) && is_file( $path ), 'An invalid own expiry cannot be interpreted as permission for early deletion.' );
		MediaStore::release();
		unset( $GLOBALS['skye_media_clock'] );
	}

	/**
	 * Observe completed native mutations without manually clearing presentation.
	 *
	 * Positive and negative cached results must follow direct Core APIs in the
	 * same request, including add/delete and attached-file pointer changes.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_presentation_invalidation() {
		$snapshot = skye_media_test_snapshot( 904, 'image/png', 0 );
		skye_media_check( WebpGenerator::process( 904, $snapshot['revision'], 3, 6 )['status'] === 'complete', 'Cache invalidation begins with a real published native alternative.' );
		MediaStore::release();
		$saved = wp_get_attachment_metadata( 904, true );
		skye_media_check( is_array( Images::webp_source( 904 ) ), 'The positive presentation result is warm before direct mutation.' );
		$changed = $saved;
		unset( $changed['skye_webp'] );
		update_post_meta( 904, '_wp_attachment_metadata', $changed );
		skye_media_check( Images::webp_source( 904 ) === false, 'A completed direct metadata update invalidates the previous positive result.' );
		update_post_meta( 904, '_wp_attachment_metadata', $saved );
		skye_media_check( is_array( Images::webp_source( 904 ) ), 'A direct metadata update also invalidates a previous negative result.' );
		delete_post_meta( 904, '_wp_attachment_metadata' );
		skye_media_check( Images::webp_source( 904 ) === false, 'Deleting native metadata cannot reuse a cached alternative.' );
		add_post_meta( 904, '_wp_attachment_metadata', $saved, true );
		skye_media_check( is_array( Images::webp_source( 904 ) ), 'Adding native metadata invalidates a cached missing result.' );
		update_post_meta( 904, '_wp_attached_file', '2026/11/unknown-cache-fixture.png' );
		skye_media_check( Images::webp_source( 904 ) === false, 'Changing the native file pointer cannot reuse the old source URL.' );
		update_post_meta( 904, '_wp_attached_file', $saved['file'] );
		skye_media_check( is_array( Images::webp_source( 904 ) ), 'Restoring the native file pointer makes the valid alternative visible again.' );
		add_filter( 'wp_get_attachment_image_src', '__return_false' );
		do_action( 'delete_attachment', 904, get_post( 904 ) );
		skye_media_check( Images::webp_source( 904 ) === false, 'The native deletion boundary discards a cached source before subsequent image selection.' );
		remove_filter( 'wp_get_attachment_image_src', '__return_false' );
	}

	/**
	 * Inject an unverified native srcset candidate at Core's public output boundary.
	 *
	 * @param array $sources Core-calculated width candidates.
	 * @return array Sources containing an unrelated unverified format candidate.
	 * @since 3.0.0
	 */
	function skye_media_test_poison_srcset( $sources ) {
		$sources[777] = array( 'url' => 'https://example.test/foreign.jpg', 'descriptor' => 'w', 'value' => 777 );
		return $sources;
	}

	/**
	 * Verify exact native fallback and conservative markup/srcset boundaries.
	 *
	 * The actual Core image and HTML tag APIs feed the production presenter, so
	 * wrappers, poisoned filters and missing records cannot hide behind a mock.
	 *
	 * @param Images $images Actual native attachment presentation adapter.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_media_test_presentation( $images ) {
		$snapshot = skye_media_test_snapshot( 902, 'image/png', 1 );
		skye_media_check( WebpGenerator::process( 902, $snapshot['revision'], 3, 6 )['status'] === 'complete', 'Presentation-negative fixture first receives a complete real WebP namespace.' );
		MediaStore::release();
		add_filter( 'pre_wp_get_loading_optimization_attributes', 'skye_media_test_loading' );
		remove_filter( 'wp_get_attachment_image', array( $images, 'picture' ), 10 );
		$native = wp_get_attachment_image( 902, 'full', false, array( 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => '<Keep exact>', 'sizes' => '100vw' ) );
		add_filter( 'wp_get_attachment_image', array( $images, 'picture' ), 10, 5 );
		$wrapped = $images->picture( $native, 902, 'full', false, array() );
		skye_media_check( str_contains( $wrapped, '<source type="image/webp"' ) && substr_count( $wrapped, $native ) === 1 && str_ends_with( rtrim( $wrapped ), $native . '</picture>' ), 'The real component adds one typed source while preserving every native image byte.' );
		$GLOBALS['skye_media_missing_component'] = true;
		skye_media_check( $images->picture( $native, 902, 'full', false, array() ) === $native, 'A missing presentation component retains the exact native image instead of an empty result or diagnostic.' );
		unset( $GLOBALS['skye_media_missing_component'] );
		foreach ( array( '<picture>' . $native . '</picture>', '<a href="/image">' . $native . '</a>', $native . $native, 'prefix' . $native, str_replace( 'fixture-902.png', 'unrelated.png', $native ) ) as $other ) {
			skye_media_check( $images->picture( $other, 902, 'full', false, array() ) === $other, 'Unsupported wrappers, multiple or unrelated images retain exact original markup.' );
		}
		skye_media_check( $images->picture( $native, 902, 'full', true, array() ) === $native && $images->picture( null, 902, 'full', false, array() ) === null, 'Icon and non-string requests retain native fallback.' );
		Images::reset_webp_cache();
		add_filter( 'wp_calculate_image_srcset', 'skye_media_test_poison_srcset' );
		skye_media_check( Images::webp_source( 902 ) === false && $images->picture( $native, 902, 'full', false, array() ) === $native, 'An unrelated native srcset filter cannot label unverified JPEG content as WebP.' );
		remove_filter( 'wp_calculate_image_srcset', 'skye_media_test_poison_srcset' );
		$metadata = wp_get_attachment_metadata( 902, true );
		$metadata['skye_webp']['source_file'] = 'another-image.png';
		update_post_meta( 902, '_wp_attachment_metadata', $metadata );
		Images::reset_webp_cache();
		skye_media_check( Images::webp_source( 902 ) === false, 'A stale complete namespace cannot bind to a different original.' );
		remove_filter( 'pre_wp_get_loading_optimization_attributes', 'skye_media_test_loading' );
	}

	$groups = [];
	$before_checks = $checks;
	skye_media_test_png_color_models();
	$groups['png_color_models'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_store();
	$groups['store'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_generator();
	$groups['native_generator'] = $checks - $before_checks;
	require_once dirname( __DIR__ ) . '/lib/package/MediaPipeline.php';
	$pipeline = new MediaPipeline();
	$before_checks = $checks;
	skye_media_test_delivery_gain( $pipeline );
	$groups['delivery_gain'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_pipeline( $pipeline );
	$groups['native_pipeline'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_discovery_recovery( $pipeline );
	$groups['discovery_recovery'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_native_backfill( $pipeline );
	$groups['additive_native_backfill'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_native_edges( $pipeline );
	$groups['native_backfill_edges'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_delete( $pipeline );
	$groups['delete_races'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_frontend( $pipeline );
	$groups['frontend_no_worker_io'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_queue_races( $pipeline );
	$groups['queue_races'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_settle_races( $pipeline );
	$groups['settle_races'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_ledger_merge();
	$groups['ledger_merge'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_retention();
	$groups['retention_cleanup'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_scheduler( $pipeline );
	$groups['native_scheduler'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_cleanup_recovery( $pipeline );
	$groups['cleanup_recovery'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_policy( $pipeline );
	$groups['policy_disable_resume'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_size_changes( $pipeline );
	$groups['configured_size_changes'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_retirement_failure();
	$groups['native_retirement_failure'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_presentation_invalidation();
	$groups['presentation_invalidation'] = $checks - $before_checks;
	$before_checks = $checks;
	skye_media_test_presentation( $images );
	$groups['presentation_fallback'] = $checks - $before_checks;
	echo json_encode( [ 'status' => 'pass', 'assertions' => $checks, 'groups' => $groups, 'delivery_gain_bytes' => $GLOBALS['skye_media_gain_evidence'] ?? [], 'php' => PHP_VERSION, 'wordpress' => $wp_version, 'database' => 'SQLite predicates; not an InnoDB/Redis integration test', 'native_http' => 'blocked' ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

}

namespace Rocketsky\Skye\Packages\Media {
	/**
	 * Supply a deterministic soft-budget boundary while preserving ordinary time.
	 *
	 * @param bool $as_float Whether a floating-point timestamp is requested.
	 * @return float|string Controlled fixture timestamp or the native timestamp.
	 * @since 3.0.0
	 */
	function microtime( $as_float = false ) {
		if ( ! empty( $GLOBALS['skye_native_microtime'] ) ) {
			return array_shift( $GLOBALS['skye_native_microtime'] );
		}
		return \microtime( $as_float );
	}

	/**
	 * Advance worker time deterministically without waiting ten real minutes.
	 *
	 * @return int Controlled worker clock or the real Unix timestamp.
	 * @since 3.0.0
	 */
	function time() {

		return $GLOBALS['skye_media_clock'] ?? \time();
	}

	/**
	 * Spy the first worker path resolution without replacing its native result.
	 *
	 * @param string $path Native local path.
	 * @return string|false Native canonical path.
	 * @since 3.0.0
	 */
	function realpath( $path ) {

		return \skye_media_test_worker_io( 'realpath', [ $path ] );
	}

	/**
	 * Ensure source hashing never starts while presenting attachment markup.
	 *
	 * @param string $algorithm Native hash algorithm.
	 * @param string $path Native source path.
	 * @param bool   $binary Whether binary output is requested.
	 * @return string|false Native source digest.
	 * @since 3.0.0
	 */
	function hash_file( $algorithm, $path, $binary = false ) {
		if ( ( $GLOBALS['skye_media_before_hash'] ?? null ) === $path ) {
			unset( $GLOBALS['skye_media_before_hash'] );
			file_put_contents( $path, "\0", FILE_APPEND );
		}

		return \skye_media_test_worker_io( 'hash_file', [ $algorithm, $path, $binary ] );
	}

	/**
	 * Ensure native editor loading remains restricted to explicit worker calls.
	 *
	 * @param string $path Native source path.
	 * @param array  $arguments Native editor request arguments.
	 * @return \WP_Image_Editor|\WP_Error Native editor result.
	 * @since 3.0.0
	 */
	function wp_get_image_editor( $path, $arguments = [] ) {

		return \skye_media_test_worker_io( 'wp_get_image_editor', [ $path, $arguments ] );
	}

	/**
	 * Reject worker stream opening during presentation while preserving real IO.
	 *
	 * @param mixed ...$arguments Original native fopen arguments.
	 * @return resource|false Native stream handle.
	 * @since 3.0.0
	 */
	function fopen( ...$arguments ) {

		return \skye_media_test_worker_io( 'fopen', $arguments );
	}
}
