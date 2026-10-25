<?php
/**
 * L'outil « Aides agricoles » de la Boîte à outils (chantier subventions, lot L3, planche B) : les
 * aides du catalogue commun qui valent pour la structure, rangées par projet puis par ce qu'elles
 * financent. Tout membre le consulte, et rien ne s'y écrit, sauf l'inscription de l'outil à sa
 * première ouverture.
 */
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		// Le canWrite() d'une structure dit « membre », quel que soit le rôle (Structure.e.php:1261) :
		// l'observateur et le comptable lisent l'outil. canRead() l'ouvrirait en plus aux écoles et aux
		// communautés qui observent la production, qui ne sont pas membres.
		$data->eStructure->validate('canWrite');

		if(\aid\AidSetting::isActive($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Aid tool is not active for this structure');
		}

	})
	->get('/ferme/{id}/outils/aides', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		// Ouvrir l'outil l'inscrit parmi ceux de la structure : un observateur consulte sans rien écrire.
		if($data->eStructure->canPlanning()) {
			\aid\AidLib::open($data->eStructure);
		}

		$data->filters = \aid\AidLib::getFilters($data->eStructure, $_GET);

		// La carte d'une structure sans lieu mène au panneau « Lieu de production », que seule une
		// personne qui gère la structure peut enregistrer (System::canWrite()). Le bouton suit la
		// paire droit et état que l'action validera, pour ne jamais mener à un refus.
		$data->eStructure['eSystem'] = $data->eStructure->system();
		$data->eStructure['canUpdatePlace'] = $data->eStructure['eSystem']->assertUpdate();

		$data->cAid = \aid\AidLib::getByFilters($data->filters);
		$data->projects = \aid\AidLib::countByProject($data->cAid);

		$data->cTag = ($data->filters['project'] === NULL) ?
			new Collection() :
			\aid\AidLib::countByTag(\aid\AidLib::filterByProject($data->cAid, $data->filters['project'], new \aid\Tag()), $data->filters['project']);

		$cAidSelected = \aid\AidLib::filterByProject($data->cAid, $data->filters['project'], $data->filters['tag']);

		// Une page au-delà de la dernière, venue d'un lien ancien, montre la dernière.
		$data->count = $cAidSelected->count();
		$data->pages = \aid\AidLib::countPages($cAidSelected);
		$data->filters['page'] = min($data->filters['page'], $data->pages - 1);

		$data->cAidShown = \aid\AidLib::getPage($cAidSelected, $data->filters['page']);

		// Une recherche qui ne laisse rien dans le département propose les aides de toute la France,
		// avec leur nombre : le bouton ne mène jamais à une liste vide.
		$data->countFrance = NULL;

		if($cAidSelected->empty() and $data->filters['department'] !== NULL) {
			$data->countFrance = \aid\AidLib::filterByProject(\aid\AidLib::getByFilters(['department' => NULL] + $data->filters), $data->filters['project'], $data->filters['tag'])->count();
		}

		throw new ViewAction($data);

	})
	->get('/ferme/{id}/outils/aides/{aid@int}', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		$data->filters = \aid\AidLib::getFilters($data->eStructure, $_GET);

		$data->eAid = \aid\AidLib::getSheet(GET('aid', 'int'), $data->filters['department']);

		// Une aide publiée qui ne s'affiche pas n'a pas de fiche, mais son adresse dit pourquoi.
		$data->isHidden = $data->eAid->empty();

		if($data->isHidden) {
			$data->eAid = \aid\AidLib::getHidden(GET('aid', 'int'))->validate();
		}

		throw new ViewAction($data);

	})
	// Les suggestions de la barre, rappelées à chaque saisie avec les critères déjà posés.
	->post('query', function($data) {

		$data->filters = \aid\AidLib::getFilters($data->eStructure, $_POST);

		$data->query = POST('query');
		$data->suggestions = \aid\AidLib::getSuggestions($data->filters, $data->query);

		throw new ViewAction($data);

	})
	// Les départements que propose « Un autre département », à côté du titre, rappelés à chaque saisie
	// avec les critères de la liste, que le choix garde.
	->post('departments', function($data) {

		$data->filters = \aid\AidLib::getFilters($data->eStructure, $_POST);

		$data->departments = \aid\AidLib::getDepartments(POST('query'));

		throw new ViewAction($data);

	});
?>
