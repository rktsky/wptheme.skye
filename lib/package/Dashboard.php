<?php

namespace Rocketsky\Skye\Packages;

/**
 * Custom WP welcome dashboard
 *
 * @author  Christoph Ackermann <acki@rocketsky.ch>
 * @version 1.0
 */
class Dashboard {

	/**
	 * Hooks the removeMetaBox action.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action( 'welcome_panel', array( $this, 'welcome_panel' ) );
	}

	public function welcome_panel() {

		$tracker_api = '';
		$amount = 0;

		if( !empty( $tracker_api ) ) {
			$invoices = json_decode( file_get_contents( $tracker_api ) );
			foreach( $invoices->invoices as $i )
				$amount = $amount + $i->amount;
		}
		
	?>

		<script type="text/javascript">
			// Hide default welcome message
			jQuery(document).ready( function($) {
				$('div.welcome-panel-content').hide();
			});
		</script>
	
		<style type="text/css">
			div.welcome-panel .ct-welcome-panel-content .welcome-panel-column { width: 25%; }
		</style>
	
		<div class="ct-welcome-panel-content" style="margin-left: 13px; max-width: 1500px;">
			<h2>Willkommen zu Deiner Website von Rocketsky!</h2>
			<p class="about-description">Nachfolgend findest Du einige Informationen zum loslegen sowie Supportinformationen.</p>
			<div class="welcome-panel-column-container">
				<div class="welcome-panel-column">
					<h3>Support</h3>
					<p>
						Rocketsky GmbH<br>
						Wasserwerkgasse 5a<br>
						3011 Bern (Schweiz)<br><br>
						Supportformular: <a href="https://www.rocketsky.ch" target="_blank">rocketsky.ch</a><br>
						Mail: <a href="mailto:acki@rocketsky.ch">acki@rocketsky.ch</a><br>
						Telefon: n/a
					</p>
					<a class="button button-primary button-hero" href="https://www.visualcraft.ch" target="_blank">Besuche unsere Website</a><br><br>
				</div><!-- .welcome-panel-column -->
				<div class="welcome-panel-column">
					<h3>Erste Schritte</h3>
					<ul>
					<?php if ( 'page' == get_option( 'show_on_front' ) ) : ?>
						<li><?php printf( '<a href="%s" class="welcome-icon welcome-edit-page">Bearbeite Deine Startseite</a>', get_edit_post_link( get_option( 'page_on_front' ) ) ); ?></li>
						<li><?php printf( '<a href="%s" class="welcome-icon welcome-add-page">Füge eine weitere Seite hinzu</a>', admin_url( 'post-new.php?post_type=page' ) ); ?></li>
					<?php endif; if (  get_option( 'page_for_posts' ) ) : ?>
						<li><?php printf( '<a href="%s" class="welcome-icon welcome-write-blog">Erfasse einen Newsbeitrag</a>', admin_url( 'post-new.php' ) ); ?></li>
					<?php endif; ?>
						<li><?php printf( '<a href="%s" class="welcome-icon welcome-view-site">Besuche Deine Website</a>', home_url( '/' ) ); ?></li>
					</ul>
					<h3>Weitere Möglichkeiten</h3>
					<ul>
						<li><?php printf( '<div class="welcome-icon welcome-widgets-menus">' . __( 'Manage <a href="%1$s">widgets</a> or <a href="%2$s">menus</a>' ) . '</div>', admin_url( 'widgets.php' ), admin_url( 'nav-menus.php' ) ); ?></li>
					</ul>
				</div><!-- .welcome-panel-column -->
				<div class="welcome-panel-column">
					<h3>Deine Ansprechperson</h3>
					<p><img src="https://www.rocketsky.ch/media/circle-ct18-ca-t4.png" style="width: 120px;"><br>
						<strong>Christoph Ackermann (Acki)</strong><br>
						Handy: +41 79 289 98 96 (keine Supportanfragen)<br>
						Mail: <a href="mailto:acki@rocketsky.ch">acki@rocketsky.ch</a><br><br>
						<a class="button" href="https://www.linkedin.com/in/acki" target="_blank">Acki auf LinkedIn besuchen</a><br>
				</div><!-- .welcome-panel-column welcome-panel-last -->
				<div class="welcome-panel-column">
					<h3>Deine Verträge</h3>
					<p><strong>Hosting Rocketsky (aktiv)</strong><br>
						Nächste Verlängerung: Ende Juli 2019<br>
						<br>
						<strong>Service Level Agreement (aktiv)</strong><br>
						Nächste Verlängerung: Ende Juli 2019<br>
						Reaktionszeit: 8h 5x8<br>
						Supportkanäle: Supportformular, Mail, Telefon<br>
					</p>
					<?php if( !empty( $tracker_api ) ) : ?>
					<h3>Offene Rechnungen</h3>
					<p><strong>Total CHF <?php echo number_format( $amount, 2, '.', ' ' ); ?> <?php if( $amount > 0 ) echo '😧'; else echo '😎'; ?></strong><br>
					<?php endif; ?>
				</div><!-- .welcome-panel-column welcome-panel-last -->
			</div><!-- .welcome-panel-column-container -->
		</div><!-- .custom-welcome-panel-content -->

	<?php
	}

}
