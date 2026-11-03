<?php
/** Display the native prepared comment list and form after the shared content-access check. */
if ( ! $comments_allowed ) {
	return;
}
?>
<section id="comments" class="comments my-4">
	<?php if ( have_comments() ) : ?>
		<h2><?php echo esc_html( sprintf( _n( '%s response', '%s responses', get_comments_number(), 'skye' ), number_format_i18n( get_comments_number() ) ) ); ?></h2>
		<ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true ) ); ?></ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p><?php esc_html_e( 'Comments are closed.', 'skye' ); ?></p>
	<?php endif; ?>
	<?php comment_form( array( 'class_submit' => 'btn-solid theme-primary' ) ); ?>
</section>
