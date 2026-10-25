<?php
new AdaptativeView('check', function($data, BaseTemplate $t) {

	$t->header = '<h1 class="text-center">'.\Asset::icon('check').' '.s("Votre adresse e-mail est validée !").'</h1>';

	echo \Asset::image('main', 'producers.webp', ['style' => 'width: 50rem; display: block; margin: auto;']);

});
?>
