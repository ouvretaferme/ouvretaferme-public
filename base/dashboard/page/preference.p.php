<?php
/**
 * L'accueil en réglage, et les trois gestes qu'il porte (L43, piste « Réglé sur place, depuis la
 * roue », tranchée le 2026-10-12).
 *
 * Le réglage est une page et non un état de l'accueil : l'accueil ordinaire ne rend ni poignée ni
 * interrupteur et ne lit rien de plus, et le réglage se recharge par son adresse. Il est l'accueil
 * lui-même, à ceci près que les pièces éteintes y gardent leur place, réduites à leur titre.
 *
 * Deux objets de page, parce que deux modules sont en jeu et que ce ne sont pas les mêmes droits. La
 * lecture du réglage est une lecture de la **structure** : `StructurePage` et son `canRead` par
 * défaut. Les trois gestes écrivent une **préférence**, et portent donc la paire de `Preference`,
 * dont `canWrite()` pèse à la fois la propriété de la ligne et l'accès à la structure postée.
 *
 * Aucune de ces trois actions ne lit l'identité dans le formulaire : `PreferenceLib` borne chacune de
 * ses requêtes par `whereUser(\user\ConnectionLib::getOnline())`, donc un `user` posté ne désignerait
 * rien. C'est la structure qui vient du formulaire, et c'est `canWrite()` qui la pèse.
 *
 * Chaque geste ne renvoie que ce qui change : le bloc touché, la carte d'un univers basculé d'un coup
 * avec chacun de ses blocs, ou les colonnes quand les univers changent de rang.
 */
new \structure\StructurePage()
	->read('/ferme/{id}/accueil/personnaliser', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isEnabled($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Dashboard is closed for this structure');
		}

		$data->today = \dashboard\DashboardLib::getToday();
		$data->blocks = \dashboard\DashboardLib::load($data->eStructure, setting: TRUE);
		// La météo reste au menu, que le réglage lit plus bas comme l'accueil.
		$data->contributions = \dashboard\DashboardLib::loadContributions($data->eStructure, slots: [\dashboard\DashboardSetting::FIGURE, \dashboard\DashboardSetting::BILAN, \dashboard\DashboardSetting::QUEUE]);
		$data->sectionsChosen = \dashboard\DashboardLib::getSectionsChosen($data->blocks);

		// Le réglage montre l'accueil tel qu'il est, groupe Bureau du menu compris.
		$data->hasNews = \dashboard\DashboardLib::hasNews($data->eStructure);
		$data->home = \dashboard\DashboardLib::loadHome($data->eStructure);

		throw new ViewAction($data);

	});

new \dashboard\PreferencePage()
	/**
	 * L'élément que les trois gestes font valider, et non la ligne que l'un d'eux écrira : au premier
	 * geste d'un membre, aucune ligne n'existe encore, et deux des trois gestes en touchent plusieurs.
	 * Ce que la paire de droits a besoin de peser est le couple membre-structure, que celui-ci porte.
	 *
	 * `getElement()` rendant un élément, `ModulePage::write()` ne cherche plus `POST('id')` comme un
	 * identifiant de préférence (`framework/core/Module.php`) : il vaut ici la structure, ce que
	 * l'écran poste sur chaque interrupteur.
	 */
	->getElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('id', '?int'));

		if($data->eStructure->empty()) {
			throw new NotExistsAction('structure #'.POST('id', '?int'));
		}

		return new \dashboard\Preference([
			'user' => \user\ConnectionLib::getOnline(),
			'structure' => $data->eStructure
		]);

	})
	->write('doUpdateHidden', function($data) {

		$fw = new FailWatch();

		$data->section = POST('scope', 'string');
		$name = POST('name', 'string');

		$choices = \dashboard\DashboardLib::loadChoices($data->eStructure);

		// Les deux index postés se vérifient **avant** l'écriture, et l'un contre l'autre : l'univers
		// doit être un de ceux que ce membre voit, et le bloc doit appartenir à cet univers. Un `name`
		// seul ne suffit pas, son `build()` l'accepterait sous n'importe quel `scope`, et la vue
		// indexerait alors un univers absent. Le POST étant contournable, le formulaire ne prouve rien.
		//
		// L'ordre est la correction : écrire d'abord puis lever laissait le réglage appliqué derrière
		// une erreur 500 (mesuré le 2026-10-01, le `hidden` d'un bloc passé de 0 à 1 avant le plantage).
		// Le membre voyait une erreur crue alors que son geste avait pris effet.
		if(
			array_key_exists($data->section, $choices['sections']) === FALSE
			or array_key_exists($name, $choices['sections'][$data->section]) === FALSE
		) {
			throw new NotExpectedAction('Unknown block');
		}

		\dashboard\PreferenceLib::updateHidden($data->eStructure, $name, POST('hidden', 'bool'));

		$fw->validate();

		$data->name = $name;
		$data->entry = \dashboard\DashboardLib::loadSetting($data->eStructure, $name);

		// Relu après l'écriture : c'est l'état que la carte de son univers doit dire.
		$data->sectionOn = \dashboard\DashboardLib::isSectionChosen(\dashboard\DashboardLib::loadChoices($data->eStructure)['sections'][$data->section]);

		throw new ViewAction($data);

	})
	->write('doUpdateSection', function($data) {

		$fw = new FailWatch();

		$data->section = POST('scope', 'string');

		$choices = \dashboard\DashboardLib::loadChoices($data->eStructure);

		// La section postée n'ouvre que ce que ce membre voit déjà : un nom qu'il ne porte pas n'a
		// aucun bloc dans sa carte, donc le geste n'écrit rien.
		if(array_key_exists($data->section, $choices['sections']) === FALSE) {
			throw new NotExpectedAction('Unknown section');
		}

		\dashboard\PreferenceLib::updateSection($data->eStructure, array_keys($choices['sections'][$data->section]), POST('hidden', 'bool'));

		$fw->validate();

		$data->blocks = \dashboard\DashboardLib::load($data->eStructure, setting: TRUE);
		$data->sectionOn = \dashboard\DashboardLib::getSectionsChosen($data->blocks)[$data->section];

		throw new ViewAction($data);

	})
	->write('doUpdatePositions', function($data) {

		$fw = new FailWatch();

		$section = POST('scope', 'string');

		$choices = \dashboard\DashboardLib::loadChoices($data->eStructure);

		// Sans `scope`, l'étage est celui des univers (L39). Tous portent une poignée, éteints compris, et
		// sont donc tous postés : la liste attendue est celle de tous les univers que le réglage montre.
		if($section === '') {

			$chosen = array_keys($choices['sections']);

		} else {

			if(array_key_exists($section, $choices['sections']) === FALSE) {
				throw new NotExpectedAction('Unknown section');
			}

			// Seuls les blocs allumés portent une poignée, un bloc éteint gardant sa place sans se ranger
			// (L43), donc seuls eux sont postés : comparer la liste à tous les blocs de la section ferait
			// refuser chaque glisser-déposer d'un univers dont un bloc est éteint.
			$chosen = [];

			foreach($choices['sections'][$section] as $name => $choice) {
				if($choice['chosen']) {
					$chosen[] = $name;
				}
			}

		}

		// Une chaîne JSON et non `names[]` : c'est la forme du seul glisser-déposer du dépôt
		// (`base/block/page/block.p.php`, `doUpdatePositions`), et `Ajax.Query.body()` ne sait pas
		// poster un tableau autrement.
		$names = json_decode(POST('names'), TRUE);

		if(is_array($names) === FALSE) {
			throw new NotExpectedAction('Names');
		}

		\dashboard\PreferenceLib::updatePositions($data->eStructure, $chosen, $names);

		$fw->validate();

		// Un bloc rangé est déjà à sa place sous le doigt. Un univers rangé déplace ses blocs dans les deux
		// colonnes, qui se relisent dans le nouvel ordre : le réglage montre ce que l'accueil montrera.
		if($section !== '') {
			throw new VoidAction();
		}

		$data->blocks = \dashboard\DashboardLib::load($data->eStructure, setting: TRUE);
		$data->contributions = \dashboard\DashboardLib::loadContributions($data->eStructure, slots: [\dashboard\DashboardSetting::FIGURE, \dashboard\DashboardSetting::BILAN, \dashboard\DashboardSetting::QUEUE]);

		throw new ViewAction($data);

	});
?>
