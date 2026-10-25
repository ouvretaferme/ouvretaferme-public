<?php
new HtmlView('index', function($data, HtmlTemplate $t) {

	echo '<html>';

		echo '<title>'.s("Maintenance").'</title>';

		echo '<body>';

			echo '<h1>'.s("{siteName} est en maintenance !").'</h1>';
			echo '<h2>'.s("Une maintenance est en cours pour améliorer le site, nous vous remercions de votre patience !").'</h2>';

		echo '</body>';

	echo '</html>';

});

new AdaptativeView('demo', function($data, BaseTemplate $t) {

	$t->title = s("Maintenance");

	$t->header = '<h1 class="text-center">'.s("La démonstration est en cours de rechargement").'</h1>';

	echo '<p class="text-center font-lg mb-4">'.s("Ses données sont remises à neuf chaque nuit. Nous vous remercions de réessayer d'ici quelques minutes !").'</p>';

	echo \Asset::image('main', 'producers.webp', ['style' => 'width: 50rem; display: block; margin: auto;']);

});
?>
