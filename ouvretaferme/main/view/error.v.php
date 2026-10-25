<?php
new AdaptativeView('403', function($data, BaseTemplate $t) {

	$t->title = s("La page n'est pas accessible");

	$h = '<div class="text-center">';
		$h .= '<br/><br/>';
		$h .= '<h1>';
			$h .= s("Vous n'êtes pas autorisé à afficher cette page !");
		$h .= '</h1>';
		$h .= '<h2>'.s("Essayez de revenir à la page précédente pour continuer votre navigation.").'</h2>';
			$h .= '<div class="util-signature-mascot">'.\Asset::svg('main', 'friends/oiseau.svg').'</div>';
		$h .= '<br/><br/>';
	$h .= '</div>';

	$t->header = $h;

});

new AdaptativeView('404', function($data, BaseTemplate $t) {

	$t->title = s("La page n'existe pas");

	$h = '<div class="text-center">';
		$h .= '<br/><br/>';
		$h .= '<h1>';
			$h .= s("La page que vous avez demandée n'existe pas !");
		$h .= '</h1>';
		$h .= '<h2>'.s("Essayez de revenir à la page précédente pour prendre un autre chemin.").'</h2>';
			$h .= '<div class="util-signature-mascot">'.\Asset::svg('main', 'friends/abeille.svg').'</div>';
		$h .= '<br/><br/>';
	$h .= '</div>';

	$t->header = $h;

	echo $data->error ?? '';

});
?>
