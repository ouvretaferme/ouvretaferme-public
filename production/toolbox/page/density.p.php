<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

	})
	->get('/ferme/{id}/outils/densites', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		// Tant que le calculateur n'est pas un outil de la structure, l'accueil le présente, et son
		// bouton l'ouvre (« il manque la page de démarrage sur Densités », le 29/09).
		$data->start = (
			get_exists('open') === FALSE and
			in_array(\toolbox\Favorite::DENSITY, $data->eStructure->conf(\structure\Feature::class)['hasToolbox']) === FALSE
		);

		if($data->start) {
			throw new ViewAction($data);
		}

		// Ouvrir l'outil l'inscrit parmi ceux de la structure : un observateur consulte le calculateur sans rien écrire.
		if($data->eStructure->canPlanning()) {
			\toolbox\DensityLib::open($data->eStructure);
		}

		[$data->eSeries, $data->eCultivation] = \toolbox\DensityLib::getNew($data->eStructure);
		$data->plan = \toolbox\DensityLib::getPlan($data->eSeries, $data->eCultivation);

		throw new ViewAction($data);

	})
	// Le résultat, relu à chaque saisie. Rien ne s'écrit : une saisie hors des bornes de la série est
	// refusée sur son champ, une saisie incomplète rend le résultat vide.
	->post('calculate', function($data) {

		$fw = new FailWatch();

		[$data->eSeries, $data->eCultivation] = \toolbox\DensityLib::build($_POST);

		$fw->validate();

		$data->plan = \toolbox\DensityLib::getPlan($data->eSeries, $data->eCultivation);

		throw new ViewAction($data);

	});
?>
