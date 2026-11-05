<?php
/** Initialize owned theme services; optional infrastructure remains outside Skye. */

$skye_packages = array(
	'ClassicEditing', 'Images', 'MenuCache', 'NotFound', 'Performance',
	'FormControls', 'CriticalStyles', 'StaticDelivery', 'DeliverySettings', 'EnvironmentMail', 'MobileNavigation', 'ExternalLinks', 'Branding', 'Icons', 'Register', 'Templates',
);
foreach ( $skye_packages as $skye_package ) {
	require_once __DIR__ . '/' . $skye_package . '.php';
}
unset( $skye_package, $skye_packages );

new Rocketsky\Skye\Packages\ClassicEditing();
new Rocketsky\Skye\Packages\Images();
new Rocketsky\Skye\Packages\MediaPipeline();
new Rocketsky\Skye\Packages\Performance();
new Rocketsky\Skye\Packages\FormControls();
new Rocketsky\Skye\Packages\CriticalStyles();
new Rocketsky\Skye\Packages\StaticDelivery();
new Rocketsky\Skye\Packages\DeliverySettings();
new Rocketsky\Skye\Packages\EnvironmentMail();
new Rocketsky\Skye\Packages\MobileNavigation();
new Rocketsky\Skye\Packages\ExternalLinks();
new Rocketsky\Skye\Packages\Branding();
new Rocketsky\Skye\Packages\Register();
