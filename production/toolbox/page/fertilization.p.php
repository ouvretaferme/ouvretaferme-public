<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

		$data->cFertilizationCrop = \toolbox\FertilizationCropLib::getList();
		$data->cFertilizationMaterial = \toolbox\FertilizationMaterialLib::getList();

	})
	->get('/ferme/{id}/outils/fertilisation', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		// Tant que le calculateur n'est pas un outil de la structure, l'accueil le présente, et son
		// bouton l'ouvre, comme celui des densités.
		$data->start = (
			get_exists('open') === FALSE and
			in_array(\toolbox\Favorite::FERTILIZATION, $data->eStructure->conf(\structure\Feature::class)['hasToolbox']) === FALSE
		);

		if($data->start) {
			throw new ViewAction($data);
		}

		// Ouvrir l'outil l'inscrit parmi ceux de la structure : un observateur consulte le calculateur sans rien écrire.
		if($data->eStructure->canPlanning()) {
			\toolbox\FertilizationLib::open($data->eStructure);
		}

		[$data->eSeries, $data->eFertilizationCrop, $data->eFertilizationYield, $data->materials] = \toolbox\FertilizationLib::getNew($data->eStructure);
		$data->cTool = \toolbox\FertilizationLib::getTools($data->eStructure);
		$data->result = NULL;

		$data->tab = GET('tab', ['fertilization-calculate', 'fertilization-crops', 'fertilization-materials']);

		throw new ViewAction($data);

	})
	// Le résultat, relu à chaque saisie. Rien ne s'écrit : une saisie hors des bornes de la série ou de
	// la matière est refusée sur son champ, une saisie incomplète rend le résultat vide.
	->post('calculate', function($data) {

		$fw = new FailWatch();

		[$data->eSeries, $data->eFertilizationCrop, $data->eFertilizationYield, $data->materials] = \toolbox\FertilizationLib::build($_POST, $data->cFertilizationCrop, $data->cFertilizationMaterial);

		$fw->validate();

		$data->result = \toolbox\FertilizationLib::getResult($data->eSeries, $data->eFertilizationCrop, $data->eFertilizationYield, $data->materials);

		throw new ViewAction($data);

	})
	// Une autre espèce ou un autre rendement : la culture et ses valeurs, et pour une autre espèce son
	// premier rendement. Le résultat se relit ensuite par `calculate`, qui juge toute la saisie.
	->post('crop', function($data) {

		$data->eFertilizationCrop = \toolbox\FertilizationLib::getCrop($data->cFertilizationCrop, POST('crop', 'int'));
		$data->eFertilizationYield = \toolbox\FertilizationLib::getYield($data->eFertilizationCrop, POST('yield', 'int'));

		throw new ViewAction($data);

	})
	// Les espèces du référentiel que nomme la saisie du champ de l'espèce, et elles seules : le
	// calculateur n'a de valeurs que pour elles.
	->post('query', function($data) {

		$data->cFertilizationCrop = \toolbox\FertilizationCropLib::getFromQuery($data->cFertilizationCrop, POST('query'));

		throw new ViewAction($data, ':query');

	})
	// La notice se lit par tout membre, observateur compris, comme l'écran : elle ne montre que des
	// morceaux du calculateur, sans geste.
	->get('notice', function($data) {

		$data->notice = \toolbox\FertilizationLib::getNotice($data->eStructure, $data->cFertilizationCrop, $data->cFertilizationMaterial);
		$data->sources = \toolbox\FertilizationLib::getSources(\toolbox\FertilizationSourceLib::getList(), $data->cFertilizationCrop, $data->cFertilizationMaterial);

		throw new ViewAction($data);

	})
	// Une matière choisie ou ajoutée, à son index : son sélecteur et sa composition. Un intrant de la
	// structure revient en composition pré-remplie. Comme pour l'espèce, le résultat se relit ensuite
	// par `calculate`.
	->post('material', function($data) {

		$data->index = POST('index', 'int');
		$data->cTool = \toolbox\FertilizationLib::getTools($data->eStructure);
		$data->material = \toolbox\FertilizationLib::getMaterial([], $data->index, POST('material'), $data->cFertilizationMaterial, $data->cTool);

		throw new ViewAction($data);

	});
?>
