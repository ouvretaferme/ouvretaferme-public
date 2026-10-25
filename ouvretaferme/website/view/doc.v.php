<?php
new AdaptativeView('/doc/communication:website', function($data, DocTemplate $t) {

	$t->title = s("Créer le site internet de sa ferme");
	$t->subTitle = s("Une vidéo vous est proposée pour vous présenter la création d'un site internet pour votre ferme avec {siteName}.");

	$t->menuSelected = 'communicationWebsite';

	echo '<div class="text-center">';

		echo '<iframe style="width: 100%; aspect-ratio: 1.777" src="https://www.youtube.com/embed/sRenofLF0W0?si=97pSzDMfIq-oU7Co" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';

	echo '</div>';

});
?>
