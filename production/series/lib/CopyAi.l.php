<?php
namespace series;

/**
 * La copie d'un plan de culture d'une ferme vers une autre.
 *
 * ⚠️ Elle ne réécrit rien de l'import : le plan de la source repasse par le format CSV de
 * Ouvretaferme et par son convertisseur, puis par la même analyse et la même écriture
 * (`CsvLib::getImportFromStructure()`, `getCultivationsFromImport()`, `importCultivations()`).
 * Ce Lib ne porte que ce que la copie ajoute — qui a le droit de copier quoi, et vers où.
 */
class CopyAiLib {

	/** Vers une ferme que la personne administre. */
	const MINE = 'mine';

	/** Vers la ferme projet du groupe. */
	const PROJECT = 'project';

	/** Vers un fichier, qui sort du produit. */
	const CSV = 'csv';

	/**
	 * Les fermes qui peuvent recevoir ce plan, selon la destination demandée.
	 *
	 * ⚠️ La source en est écartée : copier un plan sur lui-même doublerait les séries d'une ferme
	 * sans que rien ne le signale, et l'écran de conflits n'a aucun moyen de voir qu'une série
	 * existe déjà.
	 */
	public static function getDestinations(\user\User $eUser, \structure\Structure $eStructureSource, \structure\Structure $eStructureCommunity, string $mode): \Collection {

		$cStructure = match($mode) {

			self::PROJECT => \structure\StructureLib::getProjectByCommunity($eStructureCommunity),

			// Les fermes de la personne, et non celles du groupe : on copie vers chez soi.
			default => \structure\StructureLib::getByUser($eUser, \structure\Structure::PRODUCER)

		};

		// ⚠️ `canManage()` et non `canWrite()` : sur une structure, `canWrite()` répond « est
		// membre », et la liste proposait alors des fermes où l'on n'a rien à écrire. C'est
		// `Series::canWrite()` qui dit la vraie porte — elle délègue à `canManage()` de la ferme,
		// et copier deux cents séries est bien une écriture.
		return $cStructure
			->find(fn(\structure\Structure $eStructure) => (
				$eStructure['id'] !== $eStructureSource['id'] and
				$eStructure->acceptCultivation() and
				$eStructure->canManage()
			))
			->sort(['name' => SORT_ASC]);

	}

	/**
	 * Les saisons que la ferme d'arrivée ouvre.
	 *
	 * ⚠️ Le panneau ne propose que celles-là, mais la route les revérifie : la saison voyage dans
	 * l'URL de l'écran de conflits, et une valeur forgée déposerait des séries dans une saison que
	 * la ferme n'ouvre pas — elles existeraient en base sans qu'aucun de ses écrans les montre.
	 * L'analyse ne peut pas l'attraper : c'est elle qui remplace la saison, elle ne la juge plus.
	 */
	public static function getSeasonsDestination(\structure\Structure $eStructureDestination): array {

		return range(
			(int)$eStructureDestination->conf(\map\Configuration::class)['seasonFirst'],
			(int)$eStructureDestination->conf(\map\Configuration::class)['seasonLast']
		);

	}

	/**
	 * Les séries d'une sélection, ramenées à celles que cette ferme porte vraiment sur cette saison.
	 *
	 * ⚠️ La sélection voyage dans l'URL puis dans un POST, donc elle se forge : elle se relit ici
	 * contre la ferme source **et** la saison, et un identifiant venu d'ailleurs disparaît au lieu
	 * d'entrer dans l'export. C'est la même précaution que la saison d'arrivée, revérifiée par la
	 * route après avoir été proposée par le panneau.
	 *
	 * La chaîne plutôt qu'un tableau : la sélection traverse une URL de redirection, un panneau et
	 * un attribut `post-*` d'un lien ajax, trois endroits où un tableau se transporte mal.
	 */
	public static function getSeriesSelection(\structure\Structure $eStructureSource, int $season, string $selection): \Collection {

		$ids = array_values(array_filter(array_map('intval', explode(',', $selection))));

		if($ids === []) {
			return new \Collection();
		}

		return Series::model()
			->select('id')
			->whereStructure($eStructureSource)
			->whereSeason($season)
			->whereId('IN', $ids)
			->getCollection();

	}

	/**
	 * Les saisons de la ferme source qui portent des séries.
	 *
	 * Proposer une saison vide serait proposer de copier rien : l'analyse rendrait une liste vide
	 * et l'écran de conflits n'aurait rien à montrer.
	 */
	public static function getSeasons(\structure\Structure $eStructureSource): array {

		$seasons = Series::model()
			->select(['season'])
			->whereStructure($eStructureSource)
			->getCollection()
			->getColumn('season');

		$seasons = array_values(array_unique(array_map('intval', $seasons)));

		sort($seasons);

		return $seasons;

	}

}
?>
