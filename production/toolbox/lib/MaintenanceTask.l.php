<?php
namespace toolbox;

/**
 * Du carnet d'entretien au planning (planche production-finir-carnet-planning) : un entretien noté
 * fait crée au planning une intervention déjà faite, à la date de l'entretien, sur l'intervention
 * que la ferme a réglée (`MaintenanceConfiguration`), avec le matériel. Quand la structure suit le
 * temps, le panneau porte le bloc du temps d'une équipe (`planning\TimeTeamLib`, planche
 * production-finir-temps-bloc) : une durée, les personnes qui l'ont passée, au jour de l'entretien.
 * Des durées différentes par personne ou plusieurs jours se reprennent au planning, sur la tâche créée.
 *
 * Rien ne relie ensuite la tâche à la ligne de l'historique : modifier ou supprimer l'une ne touche
 * pas l'autre, et `series\Task` ne porte aucune colonne du carnet. Seul l'écran crée une tâche :
 * l'API ne construit pas `task` (`MaintenanceDoneLib::getPropertiesCreate()`), et « Ignorer ce
 * retard » ne note aucun entretien.
 */
class MaintenanceTaskLib {

	/**
	 * L'entretien fait `$e` écrit-il au planning ? Seul « Fait » sur un entretien planifié le fait :
	 * « Consigner un entretien », qui n'en nomme aucun, n'entre qu'à l'historique (arbitrage B de
	 * M-Prod du 02/10/2026). Le panneau, `build()` et l'écriture posent tous cette question ici : y
	 * faire entrer « Consigner un entretien » se fait en changeant cette seule condition.
	 */
	public static function isExpected(MaintenanceDone $e): bool {

		$e->expects(['maintenance']);

		return $e['maintenance']->notEmpty();

	}

	/**
	 * Ce que le panneau « Entretien fait » demande pour la tâche : l'intervention tant que la ferme
	 * ne l'a pas réglée, préremplie quand la ferme a celle du modèle
	 * (`MaintenanceConfigurationLib::DEFAULT_NAME`).
	 */
	public static function fillPanel(MaintenanceDone $e): void {

		$e->expects(['structure']);

		$e['hasTask'] = self::isExpected($e);
		$e['eMaintenanceConfiguration'] = new MaintenanceConfiguration();
		$e['cUserTime'] = new \Collection();

		if($e['hasTask'] === FALSE) {
			return;
		}

		// Toute l'équipe, et non les personnes présentes dans une semaine comme au planning (retour 254) : la
		// date de l'entretien se choisit librement dans le passé, et le bloc n'est pas redessiné quand elle change.
		$e['cUserTime'] = \planning\TimeTeamLib::getUsers($e['structure']);

		$eMaintenanceConfiguration = new MaintenanceConfiguration(['structure' => $e['structure']]);
		MaintenanceConfigurationLib::fillPanel($eMaintenanceConfiguration);

		if($eMaintenanceConfiguration['isConfigured'] === FALSE) {
			$e['eMaintenanceConfiguration'] = $eMaintenanceConfiguration;
		}

	}

	/**
	 * Construit la tâche depuis ce que le panneau poste, par les `build()` de chaque module : leurs
	 * refus remontent sur les champs du panneau, l'intervention sur le sien, et la date de la tâche
	 * sur celle de l'entretien, qu'elle reprend.
	 *
	 * Le réglage de la ferme n'est construit qu'au premier « Fait », quand il n'existe pas encore :
	 * il est alors enregistré avec l'entretien (`create()`). Ensuite l'intervention est la sienne, et
	 * ce qu'un POST porterait n'est pas lu.
	 *
	 * Le matériel entre dans `tools` quand le planning l'accepte sur cette intervention : un matériel
	 * lié à une autre intervention en est écarté (`\work\ToolLib::filterForWork()`), et rien ne le
	 * contourne. La description nomme donc toujours l'entretien et le matériel.
	 */
	public static function prepare(MaintenanceDone $e, array $input): bool {

		$e->expects([
			'structure', 'date',
			'maintenance' => ['name'],
			'equipment' => ['tool' => ['name']]
		]);

		$eStructure = $e['structure'];

		$fw = new \FailWatch();

		$cAction = MaintenanceConfigurationLib::getActions($eStructure);
		$eMaintenanceConfiguration = MaintenanceConfigurationLib::getByStructure($eStructure, $cAction);

		if($eMaintenanceConfiguration->empty()) {

			// Le panneau « Fait » ne demande que l'intervention : le rythme du rappel des compteurs reste
			// celui de la ferme, relu ici parce que l'écriture remplace les deux (`create()`).
			$eMaintenanceConfiguration = new MaintenanceConfiguration([
				'structure' => $eStructure,
				'readingEveryMonths' => MaintenanceConfigurationLib::getReadingEveryMonths($eStructure)
			]);

			$lConfiguration = new \PropertyList('create');

			$eMaintenanceConfiguration->build(['action'], $input, $lConfiguration);

			// Sans intervention, la tâche n'a rien à quoi se rattacher : le champ porte déjà l'erreur.
			if($lConfiguration->isInvalid('action')) {
				return FALSE;
			}

			$e['eMaintenanceConfiguration'] = $eMaintenanceConfiguration;

		} else {
			$e['eMaintenanceConfiguration'] = new MaintenanceConfiguration();
		}

		$eTask = new \series\Task([
			'structure' => $eStructure,
			'category' => MaintenanceConfigurationLib::getCategory($eStructure),
			'series' => new \series\Series(),
			'season' => NULL,
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'status' => \series\Task::DONE,
		]);

		$eTask->validate('canCreate');

		// La date de la tâche est celle de l'entretien : ce que le planning en refuse (`Task::done.interval`,
		// plus de cinq ans) s'affiche sur le champ de la date de l'entretien.
		$l = new \PropertyList('create');
		$l->setWrapper(fn(string $property) => ($property === 'done') ? 'date' : $property);

		$eTask->build(\series\TaskLib::getPropertiesCreate()($eTask), [
			'action' => $eMaintenanceConfiguration['action']['id'],
			'tools' => [$e['equipment']['tool']['id']],
			'description' => $e['maintenance']['name'].', '.$e['equipment']['tool']['name'],
			'done' => ['date' => $e['date']],
		], $l);

		$e['eTask'] = $eTask;
		$e['cTimesheet'] = \planning\TimeTeamLib::prepare($eStructure, \planning\TimeTeamLib::getUsers($eStructure), $e['date'], $input);

		return $fw->ok();

	}

	/**
	 * Écrit ce que `prepare()` a construit, dans la transaction de l'entretien fait : le réglage au
	 * premier « Fait », puis la tâche faite et le temps de chaque personne.
	 */
	public static function create(MaintenanceDone $e): void {

		if($e['eTask']->empty()) {
			return;
		}

		if($e['eMaintenanceConfiguration']->notEmpty()) {
			MaintenanceConfigurationLib::create($e['eMaintenanceConfiguration']);
		}

		\series\TaskLib::create($e['eTask']);

		\planning\TimeTeamLib::create($e['eTask'], $e['cTimesheet']);

	}

}
?>
