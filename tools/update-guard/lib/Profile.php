<?php
/** Validate finite original-file identities without executing provider source files. */

namespace Rocketsky\Skye\UpdateGuard;

/** Share the exact bounded file contract between the generator and update boundary. */
final class Profile {
	public const MAX_FILES = 4096;
	public const MAX_BYTES = 67108864;
	public const MAX_FILE_BYTES = 16777216;

	/**
	 * Admit a local relative name without links, wrappers or ambiguous components.
	 *
	 * @since 1.0.0
	 * @param mixed $path Untrusted profile or archive member spelling.
	 * @return bool Whether the spelling is a bounded portable relative path.
	 */
	public static function path( $path ) {
		return is_string( $path ) && strlen( $path ) <= 512 && preg_match( '~\A[a-zA-Z0-9_.@ /-]+\z~D', $path ) === 1 &&
			! preg_match( '~(?:\A|/)(?:\.{1,2})?(?:/|\z)~', $path );
	}

	/**
	 * Require one finite directory-based native plugin identity.
	 *
	 * @since 1.0.0
	 * @param mixed $plugin Configured basename rather than an arbitrary local path.
	 * @return bool Whether the basename has one directory and one PHP entry point.
	 */
	public static function plugin( $plugin ) {
		return is_string( $plugin ) && strlen( $plugin ) <= 160 && preg_match( '~\A[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+\.php\z~D', $plugin ) === 1;
	}

	/**
	 * Require one explicit native theme directory identity instead of an arbitrary path.
	 *
	 * @since 1.0.0
	 * @param mixed $name Configured template or stylesheet option value.
	 * @return bool Whether this is a bounded single directory identity.
	 */
	public static function theme( $name ) {
		return is_string( $name ) && strlen( $name ) <= 160 && $name !== '.' && $name !== '..' && preg_match( '~\A[a-zA-Z0-9_.-]+\z~D', $name ) === 1;
	}

	/**
	 * Inventory every regular original file without following symlinks or executing PHP.
	 *
	 * Limits cover both generated and received packages. Empty directories have no
	 * execution identity; all regular files, including hidden files, do. Failures
	 * throw a bounded message without disclosing source bytes or local paths.
	 *
	 * @since 1.0.0
	 * @param string $root Existing original or privately extracted plugin directory.
	 * @return array<string,string> Sorted complete relative SHA-256 file identities.
	 * @throws \RuntimeException For links, invalid names or exceeded budgets.
	 */
	public static function inventory( $root ) {
		if ( ! is_string( $root ) || is_link( $root ) || ! is_dir( $root ) ) {
			throw new \RuntimeException( 'Invalid original package directory.' );
		}
		$root = rtrim( $root, '/\\' );
		$files = array();
		$bytes = 0;
		$entries = 0;
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $entry ) {
			$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $entry->getPathname(), strlen( $root ) + 1 ) );
			if ( ++$entries > self::MAX_FILES * 2 || ! self::path( $relative ) || $entry->isLink() || ( ! $entry->isDir() && ! $entry->isFile() ) ) {
				throw new \RuntimeException( 'Unsupported original package member.' );
			}
			if ( $entry->isDir() ) {
				continue;
			}
			$size = $entry->getSize();
			$bytes += $size;
			if ( $size > self::MAX_FILE_BYTES || $bytes > self::MAX_BYTES || count( $files ) >= self::MAX_FILES ) {
				throw new \RuntimeException( 'Original package exceeds its finite budget.' );
			}
			$hash = hash_file( 'sha256', $entry->getPathname() );
			if ( ! is_string( $hash ) ) {
				throw new \RuntimeException( 'Original package member cannot be read.' );
			}
			$files[$relative] = $hash;
		}
		ksort( $files, SORT_STRING );
		if ( ! $files ) {
			throw new \RuntimeException( 'Original package is empty.' );
		}
		return $files;
	}

	/**
	 * Parse a bounded local profile with no executable configuration or URL fields.
	 *
	 * Protected basenames are configured separately so a broken profile still
	 * denies only those updates instead of silently disabling the guard.
	 *
	 * @since 1.0.0
	 * @param string $path Private/local reviewed JSON profile path.
	 * @param string[] $protected Independently configured protected basenames.
	 * @return array Validated plugin/version/file map.
	 * @throws \RuntimeException For malformed, missing or oversized profiles.
	 */
	public static function load( $path, $protected ) {
		clearstatcache( true );
		if ( ! is_string( $path ) || strlen( $path ) > 4096 || ! str_starts_with( $path, DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			throw new \RuntimeException( 'Reviewed update profile is unavailable.' );
		}
		$json = file_get_contents( $path, false, null, 0, 4194305 );
		if ( ! is_string( $json ) || strlen( $json ) > 4194304 ) {
			throw new \RuntimeException( 'Reviewed update profile exceeds its byte budget.' );
		}
		$data = json_decode( $json, true, 12, JSON_THROW_ON_ERROR );
		if ( ! self::keys( $data, array( 'schema', 'plugins' ) ) || $data['schema'] !== 1 || ! is_array( $data['plugins'] ) || count( $data['plugins'] ) !== count( $protected ) ) {
			throw new \RuntimeException( 'Invalid reviewed update profile.' );
		}
		foreach ( $protected as $plugin ) {
			$versions = $data['plugins'][$plugin] ?? null;
			if ( ! self::plugin( $plugin ) || ! is_array( $versions ) || ! $versions || count( $versions ) > 16 ) {
				throw new \RuntimeException( 'Invalid protected plugin profile.' );
			}
			foreach ( $versions as $version => $record ) {
				$files = $record['files'] ?? null;
				$prerequisites = $record['prerequisites'] ?? null;
				$theme = $record['theme'] ?? null;
				if ( ! is_string( $version ) || strlen( $version ) > 64 || preg_match( '~\A[0-9]+(?:\.[0-9]+){1,3}(?:[-.][a-zA-Z0-9]+)*\z~D', $version ) !== 1 || ! self::keys( $record, array( 'files', 'prerequisites', 'theme' ) ) ||
					! self::keys( $theme, array( 'template', 'stylesheet' ) ) || ! self::theme( $theme['template'] ) || ! self::theme( $theme['stylesheet'] ) ||
					! is_array( $files ) || ! $files || count( $files ) > self::MAX_FILES || ! isset( $files[basename( $plugin )] ) || ! is_array( $prerequisites ) || ! $prerequisites || count( $prerequisites ) > 32 ) {
					throw new \RuntimeException( 'Invalid original version profile.' );
				}
				foreach ( array( $files, $prerequisites ) as $identities ) {
					foreach ( $identities as $file => $hash ) {
						if ( ! self::path( $file ) || ! is_string( $hash ) || preg_match( '/\A[a-f0-9]{64}\z/D', $hash ) !== 1 ) {
							throw new \RuntimeException( 'Invalid original file identity.' );
						}
					}
				}
				foreach ( $prerequisites as $file => $hash ) {
					if ( ! self::integration_path( $file ) ) {
						throw new \RuntimeException( 'Invalid integration prerequisite path.' );
					}
				}
				ksort( $data['plugins'][$plugin][$version]['files'], SORT_STRING );
				ksort( $data['plugins'][$plugin][$version]['prerequisites'], SORT_STRING );
			}
		}
		return $data['plugins'];
	}

	/**
	 * Compare JSON object fields as a set while still rejecting missing or unknown keys.
	 *
	 * @since 1.0.0
	 * @param mixed $value Decoded JSON object represented by an associative array.
	 * @param string[] $expected Complete schema field names, independent of ordering.
	 * @return bool Whether precisely the expected object fields are present.
	 */
	private static function keys( $value, $expected ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		$keys = array_keys( $value );
		sort( $keys, SORT_STRING );
		sort( $expected, SORT_STRING );
		return $keys === $expected;
	}

	/**
	 * Limit integration evidence to explicitly named first-party theme or MU files.
	 *
	 * @since 1.0.0
	 * @param mixed $path Relative path inside the configured native content root.
	 * @return bool Whether the spelling belongs to a bounded own integration file.
	 */
	public static function integration_path( $path ) {
		return self::path( $path ) && preg_match( '~\A(?:themes/[a-zA-Z0-9_.-]+/.+|mu-plugins/.+)\z~D', $path ) === 1;
	}

	/**
	 * Hash bounded integration files without executing a theme or accepting arbitrary roots.
	 *
	 * This runs only during administrative update/guard checks. A single native
	 * realpath-cache refresh avoids stale parent aliases across external deployment
	 * swaps; it does not flush WordPress, Redis or application caches.
	 *
	 * @since 1.0.0
	 * @param string $content Native content root, also used by the offline generator.
	 * @param string[] $paths Explicit theme/MU-relative integration files, at most 32.
	 * @return array<string,string> Sorted exact integration identities.
	 * @throws \RuntimeException For escaped, missing or excessive prerequisites.
	 */
	public static function prerequisites( $content, $paths ) {
		if ( ! is_array( $paths ) || ! $paths || count( $paths ) > 32 ) {
			throw new \RuntimeException( 'Integration prerequisites are required.' );
		}
		clearstatcache( true );
		$root = realpath( $content );
		$files = array();
		$total = 0;
		foreach ( $paths as $path ) {
			if ( ! self::integration_path( $path ) || ! $root ) {
				throw new \RuntimeException( 'Invalid integration prerequisite.' );
			}
			$canonical = realpath( $root . '/' . $path );
			if ( ! $canonical || ( ! str_starts_with( $canonical, $root . '/themes/' ) && ! str_starts_with( $canonical, $root . '/mu-plugins/' ) ) || ! is_file( $canonical ) || is_link( $canonical ) ) {
				throw new \RuntimeException( 'Integration prerequisite escaped or disappeared.' );
			}
			$size = filesize( $canonical );
			$total += $size;
			if ( $size > 2097152 || $total > 16777216 ) {
				throw new \RuntimeException( 'Integration prerequisites exceed their byte budget.' );
			}
			$hash = hash_file( 'sha256', $canonical );
			if ( ! is_string( $hash ) ) {
				throw new \RuntimeException( 'Integration prerequisite is unreadable.' );
			}
			$files[$path] = $hash;
		}
		ksort( $files, SORT_STRING );
		return $files;
	}
}
