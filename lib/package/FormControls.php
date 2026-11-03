<?php
/**
 * Supply Bootstrap control classes without expanding its Sass selectors.
 */

namespace Rocketsky\Skye\Packages;

/**
 * Preserve the existing form styling through native rendered-HTML filters.
 *
 * Only classes change; provider values, submission names and accessibility
 * attributes remain under the original form renderer's control.
 *
 * @since 3.0.0
 */
class FormControls {

	private const GF_INPUT_TYPES = array(
		'email', 'date', 'datetime', 'datetime-local', 'month', 'number',
		'password', 'search', 'tel', 'text', 'time', 'week', 'url',
	);

	/**
	 * Register final field and search adapters so provider submission markup remains native.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_filter( 'gform_field_content', array( $this, 'gravityforms' ), 20, 5 );
		add_filter( 'comment_form_fields', array( $this, 'comments' ), 20 );
		add_filter( 'get_search_form', array( $this, 'search' ), 20 );
	}

	/**
	 * Class the actual Gravity Forms controls, including compound field inputs.
	 *
	 * AJAX frontend validation and pagination can run in an admin request. Keep
	 * those renders styled while leaving normal administration and the field
	 * editor/entry-detail contexts unchanged. Match the former Sass type list;
	 * color, file, hidden and choice controls intentionally retain their styles.
	 *
	 * @see https://docs.gravityforms.com/gform_field_content/
	 * @param string $html Rendered field content supplied by Gravity Forms.
	 * @param object|null $field Native field object, when supplied by the provider.
	 * @return string Original content with eligible control classes added.
	 * @since 3.0.0
	 */
	public function gravityforms( $html, $field = null ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		foreach ( array( 'is_form_editor', 'is_entry_detail', 'is_entry_detail_edit' ) as $context ) {
			if ( is_object( $field ) && is_callable( array( $field, $context ) ) && $field->$context() ) {
				return $html;
			}
		}
		return $this->controls( $html, 'gravityforms' );
	}

	/**
	 * Class native and caller-supplied comment fields, including the textarea.
	 *
	 * The array hook retains custom field ordering and excludes checkbox consent,
	 * submit controls and types outside the theme's original comment selectors.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/comment_form_fields/
	 * @param array $fields Field names mapped to their rendered HTML fragments.
	 * @return array The same fields with eligible control classes added.
	 * @since 3.0.0
	 */
	public function comments( $fields ) {
		if ( ! is_array( $fields ) || is_admin() ) {
			return $fields;
		}
		foreach ( $fields as $name => $html ) {
			$fields[$name] = $this->controls( $html, 'comments' );
		}
		return $fields;
	}

	/**
	 * Class the native search field while preserving custom form markup.
	 *
	 * The search-field class is the established theme contract; unrelated search
	 * form inputs, hidden parameters and the submit button remain untouched.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/get_search_form/
	 * @param string $html Rendered search form returned by WordPress.
	 * @return string Original form with search-field control classes added.
	 * @since 3.0.0
	 */
	public function search( $html ) {
		if ( is_admin() ) {
			return $html;
		}
		return $this->controls( $html, 'search' );
	}

	/**
	 * Add a class using the native parser without rebuilding provider markup.
	 *
	 * Explicit input types preserve the old attribute-selector contract. Selects
	 * belong to Gravity Forms; textareas belong to Gravity Forms and comments.
	 * Bootstrap 6 uses the same form-control surface for these native controls.
	 * No stored form definitions, field values or external resources are read.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_html_tag_processor/add_class/
	 * @param string $html Provider HTML fragment, returned unchanged if invalid.
	 * @param string $mode One of gravityforms, comments or search.
	 * @return string HTML with only eligible class attributes updated.
	 * @since 3.0.0
	 */
	private function controls( $html, $mode ) {
		if ( ! is_string( $html ) || $html === '' || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$types = $mode === 'gravityforms' ? self::GF_INPUT_TYPES : array( 'text', 'email', 'url' );
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( $mode === 'search' ) {
				if ( $tag === 'INPUT' && $tags->has_class( 'search-field' ) ) {
					$tags->add_class( 'form-control' );
				}
				continue;
			}
			$type = $tags->get_attribute( 'type' );
			if ( $tag === 'TEXTAREA' || ( $mode === 'gravityforms' && $tag === 'SELECT' ) ||
				( $tag === 'INPUT' && is_string( $type ) && in_array( strtolower( $type ), $types, true ) ) ) {
				$tags->add_class( 'form-control' );
			}
		}
		return $tags->get_updated_html();
	}
}
