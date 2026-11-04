<?php
/** Present distinct native desktop/modal/fallback menus with an optional stable brand-node slot. */

defined( 'ABSPATH' ) || exit;
?>
<div class="skye-mobile-navigation<?php if ( $navigation['mobile'] === '' || $navigation['fallback'] === '' ) : ?> skye-navigation-static<?php endif; ?>" data-skye-navigation data-skye-navigation-breakpoint="<?php echo esc_attr( $mobile_navigation['breakpoint'] ); ?>">
	<nav class="skye-navigation-desktop" aria-label="<?php echo esc_attr( $mobile_navigation['label'] ); ?>">
		<?php echo $navigation['html']; // Core/provider serialized menu with its own ID prefix.?>
	</nav>
	<?php if ( $navigation['mobile'] !== '' && $navigation['fallback'] !== '' ) : ?>
		<noscript><span class="skye-navigation-nojs" hidden></span></noscript>
		<details class="skye-navigation-disclosure" data-skye-navigation-fallback>
			<summary class="skye-navigation-toggle">
				<span><?php echo esc_html( $mobile_navigation['open_text'] ); ?></span>
				<span class="skye-navigation-icon" aria-hidden="true"><span></span><span></span><span></span></span>
			</summary>
			<nav class="skye-navigation-fallback-links" aria-label="<?php echo esc_attr( $mobile_navigation['label'] ); ?>">
				<?php echo $navigation['fallback']; // Separate native identity namespace; no script is required.?>
			</nav>
		</details>
		<button class="skye-navigation-toggle" type="button" hidden data-skye-navigation-open aria-controls="<?php echo esc_attr( $mobile_navigation['id'] ); ?>" aria-expanded="false" aria-label="<?php echo esc_attr( $mobile_navigation['open_label'] ); ?>">
			<span><?php echo esc_html( $mobile_navigation['open_text'] ); ?></span>
			<span class="skye-navigation-icon" aria-hidden="true"><span></span><span></span><span></span></span>
		</button>
		<dialog class="skye-navigation-dialog" id="<?php echo esc_attr( $mobile_navigation['id'] ); ?>" tabindex="-1" data-skye-navigation-dialog aria-labelledby="<?php echo esc_attr( $mobile_navigation['id'] ); ?>-title">
			<div class="skye-navigation-panel-header">
				<div class="skye-navigation-brand-slot" data-skye-navigation-brand-slot></div>
				<button class="skye-navigation-toggle skye-navigation-close" type="button" data-skye-navigation-close aria-label="<?php echo esc_attr( $mobile_navigation['close_label'] ); ?>">
					<span><?php echo esc_html( $mobile_navigation['close_text'] ); ?></span>
					<span class="skye-navigation-icon" aria-hidden="true"><span></span><span></span><span></span></span>
				</button>
			</div>
			<nav class="skye-navigation-dialog-links" aria-label="<?php echo esc_attr( $mobile_navigation['label'] ); ?>">
				<p class="skye-navigation-title" id="<?php echo esc_attr( $mobile_navigation['id'] ); ?>-title"><?php echo esc_html( $mobile_navigation['title'] ); ?></p>
				<?php echo $navigation['mobile']; // Complete native link tree without popup-only submenu controls.?>
			</nav>
		</dialog>
	<?php endif; ?>
</div>
