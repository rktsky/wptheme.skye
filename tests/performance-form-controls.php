<?php
/**
 * Exercise form control adapters with the real WordPress HTML and hook APIs.
 *
 * Backported from completed Volksgarage Website 6fecb117; only fixture names and paths generalized.
 * Run: SKYE_WORDPRESS_CORE=/absolute/wordpress php tests/form-controls-test.php
 * Request/admin and Gravity Forms field contexts are controlled fixtures. No
 * WordPress site, provider submission, database or remote service is loaded.
 *
 * @since 3.0.0
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

use Rocketsky\Skye\Packages\FormControls;

/**
 * Fail immediately so a later case cannot hide an adapter regression.
 *
 * @param bool $condition Actual observed behavior.
 * @param string $message Required contract description.
 * @return void
 * @since 3.0.0
 */
function skye_perf_form_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['skye_perf_form_checks']++;
}

/**
 * Read a synthetic request's AJAX identity through Core's real filter.
 *
 * @param bool $doing_ajax Core's default request flag.
 * @return bool Whether this fixture is a frontend AJAX render.
 * @since 3.0.0
 */
function skye_perf_form_ajax( $doing_ajax ) {
	return $GLOBALS['skye_perf_form_ajax'];
}

/**
 * Supply only the admin-screen boundary read by Core's is_admin().
 *
 * @since 3.0.0
 */
class Skye_Form_Screen {
	/**
	 * Identify the fixture as a native admin request.
	 *
	 * @return bool Always true for this screen fixture.
	 * @since 3.0.0
	 */
	public function in_admin() {
		return true;
	}
}

/**
 * Represent documented provider rendering contexts without copying its HTML.
 *
 * @since 3.0.0
 */
class Skye_Form_Field {
	public $context = '';
	/** @var string Effective native field identity, including structural anti-spam controls. @since 3.3.0 */
	public $type = 'text';

	/**
	 * Identify a Gravity Forms editor render.
	 *
	 * @return bool Whether the fixture is in the form editor.
	 * @since 3.0.0
	 */
	public function is_form_editor() {
		return $this->context === 'editor';
	}

	/**
	 * Identify an entry-detail render.
	 *
	 * @return bool Whether the fixture displays an entry.
	 * @since 3.0.0
	 */
	public function is_entry_detail() {
		return $this->context === 'entry';
	}

	/**
	 * Identify an entry-detail editing render.
	 *
	 * @return bool Whether the fixture edits an entry.
	 * @since 3.0.0
	 */
	public function is_entry_detail_edit() {
		return $this->context === 'entry-edit';
	}
}

/**
 * Inspect one actual parser token without replacing the production adapter.
 *
 * @param string $html Rendered adapter output.
 * @param string $tag Expected native HTML tag.
 * @return WP_HTML_Tag_Processor Processor positioned on the requested tag.
 * @since 3.0.0
 */
function skye_perf_form_tag( $html, $tag = 'INPUT' ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	skye_perf_form_check( $tags->next_tag( $tag ), 'The original ' . $tag . ' tag remains present.' );
	return $tags;
}

$core = getenv( 'SKYE_WORDPRESS_CORE' );
if ( ! $core || ! is_file( $core . '/wp-includes/html-api/class-wp-html-tag-processor.php' ) ) {
	throw new RuntimeException( 'Set SKYE_WORDPRESS_CORE to an official WordPress source checkout.' );
}
define( 'ABSPATH', rtrim( $core, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', false );
foreach ( array(
	'compat.php', 'plugin.php', 'load.php', 'functions.php', 'theme.php', 'compat-utf8.php', 'utf8.php', 'formatting.php', 'kses.php',
	'class-wp-token-map.php', 'html-api/html5-named-character-references.php', 'html-api/class-wp-html-attribute-token.php',
	'html-api/class-wp-html-span.php', 'html-api/class-wp-html-text-replacement.php', 'html-api/class-wp-html-decoder.php',
	'html-api/class-wp-html-tag-processor.php', 'version.php',
) as $file ) {
	if ( in_array( $file, array( 'compat-utf8.php', 'utf8.php', 'class-wp-token-map.php' ), true ) && ! is_file( ABSPATH . WPINC . '/' . $file ) ) {
		continue;
	}
	require_once ABSPATH . WPINC . '/' . $file;
}
/**
 * Supply the public charset used by Core 6.8's native attribute serializer.
 *
 * @since 3.0.0
 * @param mixed $previous Native option short circuit.
 * @param string $name Requested option.
 * @return mixed Controlled charset or the existing value.
 */
function skye_perf_form_option( $previous, $name ) {
	return $name === 'blog_charset' ? 'UTF-8' : $previous;
}
add_filter( 'pre_option', 'skye_perf_form_option', 10, 2 );
require_once dirname( __DIR__ ) . '/lib/package/FormControls.php';
$GLOBALS['skye_perf_form_checks'] = 0;
$GLOBALS['skye_perf_form_ajax'] = false;
add_filter( 'wp_doing_ajax', 'skye_perf_form_ajax' );
$adapter = new FormControls();
$field = new Skye_Form_Field();

skye_perf_form_check( has_filter( 'gform_field_content', array( $adapter, 'gravityforms' ) ) === 20, 'The documented GF field-content hook is registered.' );
$gf_hook = $GLOBALS['wp_filter']['gform_field_content']->callbacks[20];
skye_perf_form_check( reset( $gf_hook )['accepted_args'] === 5, 'The provider hook receives its five documented arguments.' );
skye_perf_form_check( has_filter( 'comment_form_fields', array( $adapter, 'comments' ) ) === 20 && has_filter( 'get_search_form', array( $adapter, 'search' ) ) === 20, 'Both native WordPress form hooks are registered.' );

foreach ( array( 'email', 'date', 'datetime', 'datetime-local', 'month', 'number', 'password', 'search', 'tel', 'text', 'time', 'week', 'url' ) as $type ) {
	$html = '<input type="' . $type . '" name="input_1" value="A&amp;B" aria-describedby="help" required>';
	$output = apply_filters( 'gform_field_content', $html, $field, '', 0, 7 );
	$tag = skye_perf_form_tag( $output );
	skye_perf_form_check( $tag->has_class( 'form-control' ), 'GF ' . $type . ' receives Bootstrap control styling.' );
	skye_perf_form_check( $tag->get_attribute( 'name' ) === 'input_1' && $tag->get_attribute( 'value' ) === 'A&B' && $tag->get_attribute( 'aria-describedby' ) === 'help' && $tag->get_attribute( 'required' ) === true, 'GF submission and accessibility attributes survive for ' . $type . '.' );
	skye_perf_form_check( apply_filters( 'gform_field_content', $output, $field, '', 0, 7 ) === $output, 'GF ' . $type . ' is byte-idempotent.' );
}

foreach ( array( 'color', 'hidden', 'file', 'checkbox', 'radio', 'submit', 'button', 'reset', 'image', 'range', '', 'custom' ) as $type ) {
	$html = '<input type="' . $type . '" class="existing" value="untouched">';
	skye_perf_form_check( apply_filters( 'gform_field_content', $html, $field, '', 0, 7 ) === $html, 'Excluded GF type ' . $type . ' remains byte-identical.' );
}
$missing_type = '<input name="missing_type" value="unchanged">';
skye_perf_form_check( $adapter->gravityforms( $missing_type ) === $missing_type, 'An absent type retains the old explicit-attribute selector contract.' );
$upper = skye_perf_form_tag( $adapter->gravityforms( '<INPUT TYPE="EMAIL" CLASS="existing" VALUE="unchanged">' ) );
skye_perf_form_check( $upper->has_class( 'form-control' ) && $upper->has_class( 'existing' ) && $upper->get_attribute( 'type' ) === 'EMAIL', 'Case-insensitive HTML types are recognized without rewriting their attributes.' );

$compound = <<<'HTML'
<fieldset><legend>Name and address</legend><label for="first">First</label><input id="first" name="input_4.3" type="text" autocomplete="given-name" aria-required="true" value="O&#039;Neil"><input id="last" name="input_4.6" type="text" class="large form-control" value="Doe"><select name="input_5.6" aria-label="Country"><option value="CH" selected>Schweiz</option></select><textarea name="input_6" rows="4">Literal &lt;input type="text"&gt;</textarea><input type="hidden" name="state_7" value="opaque"><script>const input = '<input type="text">';</script><!-- <input type="text"> --></fieldset>
HTML;
$compound_output = apply_filters( 'gform_field_content', $compound, $field, '', 0, 7 );
$tags = new WP_HTML_Tag_Processor( $compound_output );
$classed = 0;
while ( $tags->next_tag() ) {
	if ( $tags->has_class( 'form-control' ) ) {
		$classed++;
	}
}
skye_perf_form_check( $classed === 4, 'All compound name/address controls are classed once, including select and textarea.' );
skye_perf_form_check( str_contains( $compound_output, '<label for="first">First</label>' ) && str_contains( $compound_output, '<option value="CH" selected>Schweiz</option>' ) && str_contains( $compound_output, '<script>const input = \'<input type="text">\';</script><!-- <input type="text"> -->' ), 'Labels, choices, script bodies and HTML comments retain their exact markup.' );
$first = skye_perf_form_tag( $compound_output );
skye_perf_form_check( $first->get_attribute( 'value' ) === "O'Neil" && $first->get_attribute( 'name' ) === 'input_4.3' && $first->get_attribute( 'autocomplete' ) === 'given-name' && $first->get_attribute( 'aria-required' ) === 'true', 'Compound values, subinput names and ARIA/autocomplete contracts survive.' );
skye_perf_form_check( $adapter->gravityforms( $compound_output ) === $compound_output, 'Existing classes and compound fragments remain byte-idempotent.' );

$honeypot = clone $field;
$honeypot->type = 'honeypot';
$protected = '<div class="gform_validation_container" aria-hidden="true"><input type="text" name="input_8" value="" tabindex="-1" autocomplete="new-password"></div>';
skye_perf_form_check( apply_filters( 'gform_field_content', $protected, $honeypot, '', 0, 7 ) === $protected, 'A native structural honeypot remains byte-identical rather than becoming a visible Bootstrap control.' );
$security = '<input type="hidden" name="state_7" value="signed&amp;opaque"><textarea name="ak_hp_textarea" style="display:none"></textarea><input type="hidden" name="ak_js" value="123"><script>document.getElementById("ak_js_fixture").value=Date.now();</script>';
foreach ( array( 'gform_submit_button', 'gform_next_button', 'gform_previous_button', 'comment_form_submit_button' ) as $hook ) {
	$button = '<button type="submit" name="submitter" value="native" aria-label="Continue">Continue</button>';
	$output = apply_filters( $hook, $button . $security, array() );
	skye_perf_form_check( str_ends_with( $output, $security ), 'Adjacent signed state and provider anti-spam controls/scripts remain byte-identical in ' . $hook . '.' );
	$tag = skye_perf_form_tag( $output, 'BUTTON' );
	skye_perf_form_check( $tag->has_class( 'btn' ) && $tag->get_attribute( 'name' ) === 'submitter' && $tag->get_attribute( 'value' ) === 'native' && $tag->get_attribute( 'aria-label' ) === 'Continue', 'Only the actual submitter gains classes in ' . $hook . '.' );
}
foreach ( array( 'text', 'email', 'hidden', 'checkbox', 'radio', 'file', '', 'unknown' ) as $type ) {
	$input = '<input type="' . $type . '" name="provider" value="native">';
	skye_perf_form_check( $adapter->submit_button( $input ) === $input, 'A non-submitter input remains unchanged even inside a button-filter fragment: ' . $type );
}
foreach ( array( 'submit', 'button', 'reset', 'image' ) as $type ) {
	$tag = skye_perf_form_tag( $adapter->submit_button( '<input type="' . $type . '" name="provider" value="native">' ) );
	skye_perf_form_check( $tag->has_class( 'btn' ) && $tag->get_attribute( 'type' ) === $type, 'Native input submitter semantics remain supported: ' . $type );
}
skye_perf_form_check( $adapter->gravityforms( 'Plain provider text.' ) === 'Plain provider text.', 'Plain text retains exact bytes without invoking an HTML parser.' );

$GLOBALS['current_screen'] = new Skye_Form_Screen();
$input = '<input type="text" name="input_1">';
skye_perf_form_check( $adapter->gravityforms( $input, $field ) === $input, 'Ordinary GF administration is untouched.' );
$GLOBALS['skye_perf_form_ajax'] = true;
skye_perf_form_check( skye_perf_form_tag( $adapter->gravityforms( $input, $field ) )->has_class( 'form-control' ), 'Frontend GF AJAX stays styled even when Core reports an admin request.' );
foreach ( array( 'editor', 'entry', 'entry-edit' ) as $context ) {
	$field->context = $context;
	skye_perf_form_check( $adapter->gravityforms( $input, $field ) === $input, 'GF AJAX ' . $context . ' output remains untouched.' );
}
$field->context = '';
$comments = array( 'author' => '<input type="text" name="author">', 'comment' => '<textarea name="comment">Text</textarea>' );
$search = '<form class="search-form" method="get"><input type="search" class="search-field" name="s"></form>';
skye_perf_form_check( $adapter->comments( $comments ) === $comments && $adapter->search( $search ) === $search, 'Other admin form filters remain unchanged, including AJAX.' );
unset( $GLOBALS['current_screen'] );
$GLOBALS['skye_perf_form_ajax'] = false;

$comments = array(
	'comment' => '<p><textarea name="comment" id="comment" required aria-describedby="notes">A&amp;B</textarea></p>',
	'author' => '<p><input type="text" name="author" value="Name"></p>',
	'email' => '<p><input type="email" name="email" value="test@example.org"></p>',
	'url' => '<p><input type="url" name="url" value="https://example.org"></p>',
	'cookies' => '<p><input type="checkbox" name="wp-comment-cookies-consent" value="yes"></p>',
	'custom' => '<select name="custom"><option>A</option></select><input type="tel" name="phone"><input type="hidden" name="nonce" value="opaque">',
);
$comment_output = apply_filters( 'comment_form_fields', $comments );
skye_perf_form_check( array_keys( $comment_output ) === array_keys( $comments ), 'Native comment field names and ordering are retained.' );
foreach ( array( 'author', 'email', 'url' ) as $name ) {
	skye_perf_form_check( skye_perf_form_tag( $comment_output[$name] )->has_class( 'form-control' ), 'Native comment ' . $name . ' is classed.' );
}
skye_perf_form_check( skye_perf_form_tag( $comment_output['comment'], 'TEXTAREA' )->has_class( 'form-control' ), 'The native comment textarea is classed.' );
skye_perf_form_check( $comment_output['cookies'] === $comments['cookies'] && $comment_output['custom'] === $comments['custom'], 'Comment consent and unsupported custom control types remain byte-identical.' );
skye_perf_form_check( apply_filters( 'comment_form_fields', $comment_output ) === $comment_output, 'The entire native comment array is idempotent.' );

$search = <<<'HTML'
<form role="search" method="get" class="search-form" action="https://example.org/" aria-label="Site"><label><span class="screen-reader-text">Search for:</span><input type="search" class="search-field existing" name="s" value="A&amp;B" placeholder="Search"></label><input type="text" name="other" value="unchanged"><input type="hidden" name="lang" value="de"><input type="submit" class="search-submit" value="Search"></form>
HTML;
$search_output = apply_filters( 'get_search_form', $search, array( 'echo' => false ) );
$search_tag = skye_perf_form_tag( $search_output );
skye_perf_form_check( $search_tag->has_class( 'search-field' ) && $search_tag->has_class( 'existing' ) && $search_tag->has_class( 'form-control' ) && $search_tag->get_attribute( 'name' ) === 's' && $search_tag->get_attribute( 'value' ) === 'A&B', 'The native search field retains its query value and existing classes.' );
skye_perf_form_check( str_contains( $search_output, '<form role="search" method="get" class="search-form" action="https://example.org/" aria-label="Site"><label><span class="screen-reader-text">Search for:</span>' ) && str_contains( $search_output, '<input type="text" name="other" value="unchanged"><input type="hidden" name="lang" value="de">' ), 'Search action, method, accessibility labels and unrelated inputs remain byte-identical.' );
$submit = new WP_HTML_Tag_Processor( $search_output );
while ( $submit->next_tag( 'INPUT' ) ) {
	if ( 'submit' === $submit->get_attribute( 'type' ) ) {
		break;
	}
}
skye_perf_form_check( $submit->get_attribute( 'value' ) === 'Search' && $submit->has_class( 'search-submit' ) && $submit->has_class( 'btn' ) && $submit->has_class( 'btn-solid' ) && $submit->has_class( 'theme-primary' ), 'Native search submitter keeps value/type and gains only the Bootstrap 6 presentation classes.' );
skye_perf_form_check( apply_filters( 'get_search_form', $search_output ) === $search_output, 'Native search output is byte-idempotent.' );
skye_perf_form_check( $adapter->gravityforms( '' ) === '' && $adapter->comments( array() ) === array(), 'Empty provider output passes through unchanged.' );

echo 'Skye_FORM_CONTROLS_RESULT=' . json_encode( array( 'status' => 'pass', 'assertions' => $GLOBALS['skye_perf_form_checks'], 'wordpress' => $wp_version, 'php' => PHP_VERSION ) ) . "\n";
