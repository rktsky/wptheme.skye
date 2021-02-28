<?php

	global $data;

	if( empty( $data ) )
		$data = (object) [];

	if( empty( $data->title ) || empty( $data->override ) || $data->override === 'false' )
		$data->title = Roots\Sage\Titles\title();

	if( empty( $data->url ) )
		$data->url = get_home_url();

	if( empty( $data->urltitle ) )
		$data->urltitle = _x( 'Zur Startseite', 'Default URL Titel im Fragment pageheader/image wenn $data->urltitle leer', 'sage' );

?>

<div class="pageheader image pb-10 dark">
	<?php if( !empty( $data->image ) ): ?>
		<?php echo wp_get_attachment_image( $data->image, 'ct-sky', false, [ 'class' => 'background' ] ); ?>
	<?php endif; ?>
	<div class="gradient"></div>
	<div class="container content">
		<div class="row">
			<div class="col">
				<div class="page-header">
					<a href="<?php echo $data->url; ?>"><?php echo $data->urltitle; ?></a>
					<h1><?php echo $data->title; ?></h1>
				</div>
				<?php if( !empty( $data->breadcrumbs ) && $data->breadcrumbs === 'show' ): ?>
					<div class="breadcrumbs">
						<?php get_component( 'breadcrumbs' ); ?>
					</div>
				<?php endif; ?>
				<?php if( !empty( $data->breadcrumbs ) && $data->breadcrumbs === 'categories' ): ?>
					<div class="breadcrumbs">
						<?php get_component( 'news', 'categories' ); ?>
					</div>
				<?php endif; ?>
				<?php if( !empty( $data->headerlead ) ): ?>
					<div class="col-md-8 col-12 mt-5">
						<div class="ct-lead">
							<?php echo $data->headerlead; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
