<?php

	global $data;

	if( empty( $data ) )
		$data = (object) [];

	// yeah false need to be a string - fuckdumb ACF
	if( empty( $data->override ) || $data->override === "false" )
		$data->title = Cubetech\Skye\Titles\title();

?>

<div class="pageheader simple pb-8">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="page-header">
					<h1><?php echo $data->title; ?></h1>
				</div>
				<?php if( !empty( $data->breadcrumbs ) && $data->breadcrumbs === 'show' ): ?>
					<div class="breadcrumbs">
						<?php get_component( 'breadcrumbs' ); ?>
					</div>
				<?php endif; ?>
				<?php if( !empty( $data->lead ) ): ?>
					<div class="col-md-8 mt-5">
						<?php echo $data->lead; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>