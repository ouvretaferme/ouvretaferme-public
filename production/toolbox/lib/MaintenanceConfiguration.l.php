<?php
namespace toolbox;

/**
 * Le réglage du carnet d'entretien, un seul par ferme : l'intervention du planning que prend un
 * entretien fait (planche production-finir-carnet-planning, « L'intervention se règle au global
 * pour la ferme »), et le rythme du rappel des compteurs à relever (demande du
 * 02/10/2026, arbitrée par M-Prod : tous les mois, 2 mois, 3 mois ou 6 mois).
 */
class MaintenanceConfigurationLib extends MaintenanceConfigurationCrud {

	// L'intervention que le premier « Fait » propose. Les interventions d'une ferme sont copiées d'un
	// modèle (`\work\ActionLib::duplicateForStructure()`) où celle-ci n'a pas de `fqn` : elle ne se
	// reconnait qu'à son nom, et une ferme qui l'a renommée ou désactivée choisit elle-même.
	const DEFAULT_NAME = 'Matériel';

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['action', 'readingEveryMonths'];
	}

	/**
	 * Le rythme d'une ferme qui n'a rien réglé : le défaut du schéma, qui est aussi celui que prend
	 * la ligne posée par le premier « Fait ».
	 */
	public static function getReadingEveryMonthsDefault(): int {
		return MaintenanceConfiguration::model()->getDefaultValue('readingEveryMonths');
	}

	/**
	 * Le rythme du rappel des compteurs d'une ferme, en mois. Il se lit sur la ligne elle-même, et
	 * non par `getByStructure()` : un réglage dont l'intervention ne vaut plus y est rendu vide, alors
	 * que son rythme, lui, vaut toujours.
	 */
	public static function getReadingEveryMonths(\structure\Structure $eStructure): int {

		return MaintenanceConfiguration::model()
			->whereStructure($eStructure)
			->getValue('readingEveryMonths', self::getReadingEveryMonthsDefault());

	}

	/**
	 * Le rythme de chaque ferme qui a un réglage, par identifiant de structure, pour le cron qui
	 * balaie le parc (`NotificationLib::emitReadings()`). Une ferme absente du tableau suit le défaut.
	 */
	public static function getReadingEveryMonthsByStructure(): array {

		$cMaintenanceConfiguration = MaintenanceConfiguration::model()
			->select(['structure', 'readingEveryMonths'])
			->getCollection();

		$readingEveryMonths = [];

		foreach($cMaintenanceConfiguration as $eMaintenanceConfiguration) {
			$readingEveryMonths[$eMaintenanceConfiguration['structure']['id']] = $eMaintenanceConfiguration['readingEveryMonths'];
		}

		return $readingEveryMonths;

	}

	/**
	 * Les interventions qu'un entretien fait peut prendre au planning : celles, actives, de la
	 * catégorie « Sur l'outil de production », triées par nom.
	 *
	 * La catégorie se lit ici, et non par son `fqn` passé à `ActionLib::getByStructure()` : sur une
	 * ferme qui n'a pas la catégorie, il ne filtrerait rien et rendrait toutes les interventions.
	 */
	public static function getActions(\structure\Structure $eStructure): \Collection {

		$eCategory = self::getCategory($eStructure);

		if($eCategory->empty()) {
			return new \Collection();
		}

		return \work\ActionLib::getByStructure($eStructure, category: $eCategory, index: 'id');

	}

	public static function getCategory(\structure\Structure $eStructure): \work\Category {
		return \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_PRODUCTION);
	}

	/**
	 * Le réglage de la ferme, vide tant qu'il n'est pas posé. Il est aussi rendu vide quand son
	 * intervention n'est plus de celles qu'on peut choisir (désactivée, ou sortie de la catégorie) :
	 * la tâche ne pourrait pas se créer dessus (`Task::action.usable`), donc le panneau « Fait » la
	 * redemande, et le menu du carnet montre un champ vide plutôt qu'une valeur qui ne vaut plus.
	 */
	public static function getByStructure(\structure\Structure $eStructure, \Collection $cAction): MaintenanceConfiguration {

		$eMaintenanceConfiguration = MaintenanceConfiguration::model()
			->select(['id', 'structure', 'action'])
			->whereStructure($eStructure)
			->get();

		if(
			$eMaintenanceConfiguration->notEmpty() and
			$cAction->offsetExists($eMaintenanceConfiguration['action']['id']) === FALSE
		) {
			return new MaintenanceConfiguration();
		}

		return $eMaintenanceConfiguration;

	}

	/**
	 * L'intervention proposée tant que rien n'est réglé, vide chez une ferme qui ne l'a plus.
	 */
	public static function getDefaultAction(\Collection $cAction): \work\Action {
		return $cAction->find(fn(\work\Action $eAction) => $eAction['name'] === self::DEFAULT_NAME, limit: 1, default: new \work\Action());
	}

	/**
	 * Ce que montre le champ « Intervention au planning », dans le panneau du réglage comme au
	 * premier « Fait » : les interventions qu'on peut choisir, et celle sur laquelle il s'ouvre. C'est
	 * celle du réglage quand il vaut (`isConfigured`), sinon celle que `getDefaultAction()` propose,
	 * et le champ reste à choisir chez une ferme qui ne l'a pas.
	 *
	 * Le rythme du rappel s'ouvre sur celui de la ferme, le défaut tant qu'elle n'a rien réglé : avec
	 * l'intervention préremplie, changer le seul rythme reste un geste à un champ.
	 */
	public static function fillPanel(MaintenanceConfiguration $e): void {

		$e->expects(['structure']);

		$e['cAction'] = self::getActions($e['structure']);

		$eMaintenanceConfiguration = self::getByStructure($e['structure'], $e['cAction']);

		$e['isConfigured'] = $eMaintenanceConfiguration->notEmpty();
		$e['action'] = $e['isConfigured'] ? $eMaintenanceConfiguration['action'] : self::getDefaultAction($e['cAction']);
		$e['readingEveryMonths'] = self::getReadingEveryMonths($e['structure']);

	}

	/**
	 * Une ferme n'a qu'un réglage : le poser une seconde fois le remplace, que la ligne en base soit
	 * celle qu'on vient de lire ou une ligne dont l'intervention ne vaut plus (`getByStructure()` la
	 * rend alors vide, donc sans identifiant à modifier).
	 */
	public static function create(MaintenanceConfiguration $e): void {

		$e->expects(['structure', 'action', 'readingEveryMonths']);

		$affected = MaintenanceConfiguration::model()
			->option('add-ignore')
			->insert($e);

		if($affected === 0) {

			// La ligne de la ferme n'est pas `$e`, qui n'a pas d'id : l'écriture se désigne par la
			// structure, et garde donc un tableau de valeurs.
			MaintenanceConfiguration::model()
				->whereStructure($e['structure'])
				->update($e->extracts(['action', 'readingEveryMonths']));

		}

		// Le rythme dit le jour où chaque compteur entre au planning.
		\planning\DueLib::refresh($e['structure'], 'toolbox');

	}

}
?>
