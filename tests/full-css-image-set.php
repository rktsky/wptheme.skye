<?php
/** Verify the strict image-set URL subset and optionally read an original local provider stylesheet. */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
require __DIR__ . '/performance-fixture.php';
require dirname( __DIR__ ) . '/lib/package/assets/FullCss.php';

use Rocketsky\Skye\Packages\Assets\FullCss;

$source = 'https://example.test/assets/css/site.css';
$valid = array(
	'image-set(url(a.webp) 1x,url(b.webp) 2x)' => 'image-set(url("https://example.test/assets/css/a.webp") 1x,url("https://example.test/assets/css/b.webp") 2x)',
	'-webkit-image-set(url(../a.webp) 1x,url(../b.webp) 2x)' => '-webkit-image-set(url("https://example.test/assets/a.webp") 1x,url("https://example.test/assets/b.webp") 2x)',
	'image-set(url("../a.webp?size=1#icon") 1x)' => 'image-set(url("https://example.test/assets/a.webp?size=1#icon") 1x)',
	"image-set(url('../space name.webp') .5x)" => 'image-set(url("https://example.test/assets/space name.webp") .5x)',
	'image-set(url(/images/a.webp) 1.5dppx)' => 'image-set(url("https://example.test/images/a.webp") 1.5dppx)',
	'image-set(url(//cdn.example.test/a.webp) 192dpi)' => 'image-set(url("https://cdn.example.test/a.webp") 192dpi)',
	'image-set(url(https://cdn.example.test/a.webp) 37.8dpcm)' => 'image-set(url("https://cdn.example.test/a.webp") 37.8dpcm)',
	'image-set(url("data:image/png;base64,YQ==") 1x,url(#local) 2x)' => 'image-set(url("data:image/png;base64,YQ==") 1x,url("#local") 2x)',
	"IMAGE-SET(\n URL( a.webp ) 1X,\turl(b.webp) 2DPPX\n)" => "IMAGE-SET(\n url(\"https://example.test/assets/css/a.webp\") 1X,\turl(\"https://example.test/assets/css/b.webp\") 2DPPX\n)",
);
foreach ( $valid as $input => $expected ) {
	$css = '/* complete rules */.icon{background:' . $input . ';color:red}@media(print){.icon{display:none}}';
	$wanted = '/* complete rules */.icon{background:' . $expected . ';color:red}@media(print){.icon{display:none}}';
	perf_check( FullCss::rebase( $css, $source ) === $wanted, 'Only URL tokens change; function, density, order and complete surrounding rules survive.' );
}
$literal = '/* image-set(url(comment.webp) 1x) */.x{content:"image-set(url(literal.webp) 1x)"}';
perf_check( FullCss::rebase( $literal, $source ) === $literal, 'Strings and comments remain literal without invented image requests.' );
$invalid = array(
	"image-set(url(\x0ba.webp) 1x)",
	"image-set(url(a.webp\x0b) 1x)",
	"image-set(url(\"a.webp\"\x0b) 1x)",
	'image-set("relative.webp" 1x)',
	'image-set()',
	'image-set(url(a.webp))',
	'image-set(url(a.webp) 0x)',
	'image-set(url(a.webp) -1x)',
	'image-set(url(a.webp) NaNx)',
	'image-set(url(a.webp) INFx)',
	'image-set(url(a.webp) 1e309x)',
	'image-set(url(a.webp) 1w)',
	'image-set(url(a.webp) 1)',
	'image-set(url(a.webp) 1x type("image/webp"))',
	'image-set(url(a.webp) 1x type("invalid"))',
	'image-set(linear-gradient(red,blue) 1x)',
	'image-set(image(url(a.webp),red) 1x)',
	'image-set(image-set(url(a.webp) 1x) 1x)',
	'image-set(url(a.webp)1x)',
	'image-set(url (a.webp) 1x)',
	'image-set(url(a.webp) 1x/* comment */)',
	'image-set(url(a.webp) /* comment */1x)',
	'image-set(/* comment */url(a.webp) 1x)',
	'image-set(url(a.webp) 1x,)',
	'image-set(url(a.webp) 1x url(b.webp) 2x)',
	'image-set(url(a.webp) 1x',
	'image-set(url(a.webp 1x)',
	'image-set(url("unclosed.webp) 1x)',
	'image-set(url(unquoted space.webp) 1x)',
	'image-set(url("bad\\ path.webp") 1x)',
	'image-set(u\\72l(a.webp) 1x)',
	'image\\2d set(url(a.webp) 1x)',
	'image-set(url(javascript:alert) 1x)',
	'image-set(url(blob:unknown) 1x)',
	'image-set(url(file:///private.png) 1x)',
	'image-set(url(https://user:pass@example.test/a.webp) 1x)',
	'image-set(url(a.webp) ' . str_repeat( '9', 400 ) . 'x)',
	'unknown-image-set(url(a.webp) 1x)',
	'éimage-set(url(a.webp) 1x)',
);
foreach ( $invalid as $input ) {
	perf_check( FullCss::rebase( '.icon{background:' . $input . '}', $source ) === false, 'Unsupported or malformed image syntax keeps the complete native stylesheet.' );
}
$candidates = array_fill( 0, 16, 'url(a.webp) 1x' );
perf_check( FullCss::rebase( '.x{background:image-set(' . implode( ',', $candidates ) . ')}', $source ) !== false, 'The explicit candidate budget accepts a bounded list.' );
$candidates[] = 'url(a.webp) 2x';
perf_check( FullCss::rebase( '.x{background:image-set(' . implode( ',', $candidates ) . ')}', $source ) === false, 'Oversized lists retain native delivery.' );
perf_check( FullCss::rebase( '.x{background:image-set(url(a.webp) 1x)}', 'not-a-canonical-source' ) === false, 'Relative candidates require a canonical stylesheet base.' );

$provider = getenv( 'SKYE_PROVIDER_CSS_FIXTURE' );
if ( $provider ) {
	if ( ! is_file( $provider ) || ! is_readable( $provider ) || filesize( $provider ) > 524288 ) {
		throw new RuntimeException( 'The optional original provider fixture must be a readable bounded local file.' );
	}
	$hash = hash_file( 'sha256', $provider );
	$css = file_get_contents( $provider );
	$rebased = FullCss::rebase( $css, 'https://example.test/wp-content/plugins/provider/assets/css/dist/basic.min.css' );
	perf_check( is_string( $rebased ), 'The unmodified real provider source now passes complete-CSS URL rebasing.' );
	$url_tokens = '~url\((?:"[^"]*"|\'[^\']*\'|[^()]*)\)~i';
	perf_check( preg_split( $url_tokens, $css ) === preg_split( $url_tokens, $rebased ), 'Every provider byte outside URL tokens remains identical; no rule, candidate, density or selector is removed.' );
	perf_check( substr_count( $rebased, 'https://example.test/wp-content/plugins/provider/images/theme/international-phone/flags-sprite' ) === 6, 'All real ordinary and image-set phone sprite candidates resolve against the provider stylesheet.' );
	perf_check( hash_file( 'sha256', $provider ) === $hash, 'The optional licensed source is read only and remains byte-identical.' );
}
echo 'Full CSS image-set: ' . $GLOBALS['perf_checks'] . " assertions passed against native Core.\n";
