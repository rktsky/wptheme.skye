<?php

	global $data;

?>

<?php if( !empty( $data->lead ) ): ?>
	<div class="pagelead pt-8 pb-7">
		<div class="container">
			<div class="row">
				<div class="col-md-8 col-12">
					<div class="ct-lead">
						<?php echo $data->lead; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
