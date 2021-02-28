<div class="container">
	<div class="row">
		<div class="col-12">
			<?php get_component('page-header'); ?>
		</div>
	</div>
	
	<div class="row">
		<div class="col-12">
	
			<?php if (!have_posts()) : ?>
			  <div class="alert alert-warning">
			    <?php _e('Sorry, no results were found.', 'sage'); ?>
			  </div>
			  <?php get_search_form(); ?>
			<?php endif; ?>
			
			<?php while (have_posts()) : the_post(); ?>
			  <?php get_component( 'search' ); ?>
			<?php endwhile; ?>
			
			<?php the_posts_navigation(); ?>
	
		</div>
	</div>
</div>