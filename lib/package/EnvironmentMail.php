<?php
/** Prevent staging mail through native provider boundaries without examining message contents. */

namespace Rocketsky\Skye\Packages;

/** Keep staging transport policy outside project content and third-party package files. */
final class EnvironmentMail {
	/**
	 * Observe native mail preemption and the optional Gravity Forms transport hook.
	 *
	 * Neither hook receives content for inspection or logging. Registering the
	 * provider filter is harmless when Gravity Forms is not installed.
	 *
	 * @since 3.1.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'pre_wp_mail', array( $this, 'preempt' ), PHP_INT_MAX );
		add_filter( 'gform_pre_send_email', array( $this, 'gravity' ), PHP_INT_MAX );
	}

	/**
	 * Evaluate the public opt-out only in WordPress's actual staging environment.
	 *
	 * Production, local and development retain their native transport behavior
	 * even when an extension returns true for the staging-only policy filter.
	 *
	 * @since 3.1.0
	 * @return bool Whether staging mail should be blocked for this invocation.
	 */
	private function blocking() {
		return wp_get_environment_type() === 'staging' && (bool) apply_filters( 'skye_block_staging_mail', true );
	}

	/**
	 * Report a blocked staging delivery accurately without replacing earlier decisions.
	 *
	 * A non-null result already belongs to an earlier native preemption provider.
	 * No mail arguments, recipients, headers or attachments enter this method.
	 *
	 * @since 3.1.0
	 * @param mixed $result Native previous preemption value, normally null or bool.
	 * @return mixed Previous non-null result, false for blocked staging, otherwise null.
	 */
	public function preempt( $result ) {
		return $result !== null ? $result : ( $this->blocking() ? false : null );
	}

	/**
	 * Add only the native abort flag before a staging Gravity Forms mail provider runs.
	 *
	 * Existing abort flags and every other property survive unchanged outside
	 * blocking staging. Unknown argument shapes retain their native value.
	 *
	 * @since 3.1.0
	 * @param mixed $email Native provider transport array, intentionally never inspected.
	 * @return mixed Native transport arguments with only an optional true abort flag.
	 */
	public function gravity( $email ) {
		if ( is_array( $email ) && $this->blocking() ) {
			$email['abort_email'] = true;
		}
		return $email;
	}
}
