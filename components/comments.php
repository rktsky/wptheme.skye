<?php
if (post_password_required()) {
	return;
}
?>

<section id="comments" class="comments mt-8 mb-10">
	<?php if (have_comments()) : ?>
		<h4><?php printf(_nx('One response to &ldquo;%2$s&rdquo;', '%1$s responses to &ldquo;%2$s&rdquo;', get_comments_number(), 'comments title', 'skye'), number_format_i18n(get_comments_number()), '<span>' . get_the_title() . '</span>'); ?></h4>

		<ol class="comment-list">
			<?php wp_list_comments(['style' => 'ol', 'short_ping' => true]); ?>
		</ol>

		<?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : ?>
			<nav>
				<ul class="pager">
					<?php if (get_previous_comments_link()) : ?>
						<li class="previous"><?php previous_comments_link(__('&larr; Older comments', 'skye')); ?></li>
					<?php endif; ?>
					<?php if (get_next_comments_link()) : ?>
						<li class="next"><?php next_comments_link(__('Newer comments &rarr;', 'skye')); ?></li>
					<?php endif; ?>
				</ul>
			</nav>
		<?php endif; ?>
	<?php endif; // have_comments() ?>

	<?php if (!comments_open() && get_comments_number() != '0' && post_type_supports(get_post_type(), 'comments')) : ?>
		<div class="alert alert-warning">
			<?php _e('Comments are closed.', 'skye'); ?>
		</div>
	<?php endif; ?>

	<?php
		comment_form([
			'title_reply_before' => '<h4>',
			'title_reply_after' => '</h4>',
			'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="%3$s btn-ct btn-outline" value="%4$s"><span>%4$s</span></button>',
		]);
	?>
</section>



	