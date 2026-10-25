<?php
namespace structure;

/**
 * Dupliquer une ferme membre d'un groupe dans une ferme neuve, geste de l'équipe du groupe.
 *
 * La copie est celle de la ferme école (`SchoolAiLib`) : mêmes modules écartés, mêmes colonnes
 * vidées, même configuration alignée. Elle est appelée avec ses propres arguments, tenus ici : les
 * packages de la famille choisie, toutes les saisons, les espèces bornées quand seule la
 * commercialisation est copiée (`getConditions()`), les ventes agrégées sans seuil. Rien de la
 * mécanique ne s'écrit ici : une règle de décalage ou de clé propre à la duplication aurait ses
 * propres angles morts, que `SchoolAiLib::check()` ne verrait pas.
 *
 * ⚠️ **Les écritures passent par le modèle**, hors des Lib et sans `build()`, comme pour la ferme
 * école : la plus grande structure du parc se copie en 12 s, ce qu'aucune boucle sur `create()` ne
 * tiendrait pendant un geste. Écart validé le 14/09/2026, et borné :
 * toute écriture directe vise l'id de la nouvelle ferme, jamais un autre.
 *
 * ⚠️ **Pas de transaction autour de la copie.** `StructureLib::create()` valide la sienne avant de
 * créer la base de la structure (`CREATE DATABASE` valide implicitement), et un `INSERT … SELECT`
 * sous REPEATABLE-READ verrouille en partage les lignes lues de la source jusqu'au COMMIT : la ferme
 * copiée ne pourrait plus écrire une intervention pendant toute la duplication. Chaque table est une
 * instruction, atomique ; un échec efface ce qui a été copié, et la nouvelle ferme reste fermée.
 */
class DuplicateAiLib {

	use \Notifiable;

	/**
	 * Le droit que chaque famille demande à la ferme, pour l'équipe du groupe.
	 *
	 * La clé est ce que le formulaire poste et ce que le panneau montre : une famille par droit, et
	 * aucune n'attend l'autre.
	 */
	const RIGHTS = [
		'production' => 'shareProductionDuplicate',
		'selling' => 'shareSellingDuplicate',
	];

	/**
	 * Les packages de la production.
	 *
	 * Ceux de la ferme école, moins `gallery` : ses photos ne se copient nulle part (`MODULES_EXCLUDE`)
	 * et le package n'a rien d'autre. `hr` va avec la production parce qu'il porte les présences
	 * des personnes qui pointent, sans lesquelles le temps de travail n'a plus d'équipe.
	 */
	const PACKAGES_PRODUCTION = ['map', 'plant', 'sequence', 'series', 'work', 'hr'];

	/**
	 * Les packages de la commercialisation, à côté des ventes, qui ne se copient pas mais s'agrègent.
	 *
	 * Les catalogues, les offres, les relations et le stock en sont écartés par `MODULES_EXCLUDE`.
	 */
	const PACKAGES_SELLING = ['product', 'analyze'];

	/**
	 * Aucun seuil sur les ventes agrégées : l'agrégat sert à ne pas recopier de clients, et non à
	 * cacher un achat à une équipe à qui la ferme a ouvert sa commercialisation.
	 */
	const CUSTOMERS_MIN = 1;

	/**
	 * Les familles que ce groupe demande à son équipe, dans l'ordre du panneau.
	 *
	 * Un groupe où la duplication de la commercialisation n'est pas ouverte ne la demande jamais :
	 * `CommunityRightAiLib::getRegime()` la rend absente.
	 */
	public static function getAsked(Structure $eStructureCommunity): array {

		$families = [];

		foreach(self::RIGHTS as $family => $right) {

			if(in_array(Community::STAFF, $eStructureCommunity->askedCommunityAudiences($right), TRUE)) {
				$families[] = $family;
			}

		}

		return $families;

	}

	/**
	 * Pour chaque famille, la ferme l'a-t-elle accordée à l'équipe de ce groupe.
	 *
	 * ⚠️ **La demande du groupe se relit, la colonne de la ferme ne suffit pas.** `grants()` ne lit
	 * que ce que la ferme a coché : un groupe qui cesse de demander la duplication laisse la
	 * colonne telle quelle, et la ferme passerait pour l'accorder encore. Une famille que le groupe
	 * ne demande pas vaut donc FALSE, comme une famille refusée ; le panneau les distingue par
	 * `getAsked()`.
	 *
	 * `$eStructureProducer` porte les droits accumulés par `AffiliationAiLib::getStructuresByCommunity()`.
	 */
	public static function getGranted(Structure $eStructureCommunity, Structure $eStructureProducer): array {

		$asked = self::getAsked($eStructureCommunity);

		$granted = [];

		foreach(self::RIGHTS as $family => $right) {

			$granted[$family] = (
				in_array($family, $asked, TRUE) and
				CommunityRightAiLib::grants($eStructureCommunity, $right, $eStructureProducer[$right], Community::STAFF, $eStructureProducer['audiencesBringing'])
			);

		}

		return $granted;

	}

	/**
	 * Si la duplication se propose sur les fermes du groupe : le droit du lecteur, et au moins une
	 * famille demandée. Les roues des cartes et l'entrée « Dupliquer » du bandeau suivent cette seule
	 * règle.
	 */
	public static function isShown(Structure $eStructureCommunity, bool $canDuplicate): bool {
		return ($canDuplicate and self::getAsked($eStructureCommunity) !== []);
	}

	/**
	 * Pose sur chaque ferme ce que la duplication lui propose : NULL quand elle n'est pas proposée
	 * (`isShown()`), sinon si la ferme accorde au moins une famille. La roue d'une carte et l'entrée
	 * « Dupliquer » du bandeau de l'onglet Fermes le lisent.
	 *
	 * `$canDuplicate` est le droit du lecteur, que la page lit sur `Structure::assertDuplicate()` : ce
	 * Lib ne pèse pas le membre connecté.
	 */
	public static function fillStructures(Structure $eStructureCommunity, \Collection $cStructure, bool $canDuplicate): void {

		$show = self::isShown($eStructureCommunity, $canDuplicate);

		foreach($cStructure as $eStructure) {
			$eStructure['duplicate'] = $show ? in_array(TRUE, self::getGranted($eStructureCommunity, $eStructure), TRUE) : NULL;
		}

	}

	/**
	 * Les modules copiés pour ces familles.
	 *
	 * ⚠️ **La commercialisation seule emporte `plant\Plant`, et lui seul de son package** : les produits
	 * et les rapports nomment une espèce, et hors de la copie `getColumns()` refuserait la clé. Les
	 * espèces se bornent à celles qu'ils nomment (`getConditions()`).
	 *
	 * ⚠️ **Et elle n'emporte pas `analyze\Cultivation`**, qui pointe les séries et les cultures : sans
	 * la production, ses lignes pointeraient celles de la source. Les rapports y perdent leur détail
	 * par série, ce que le panneau annonce.
	 */
	public static function getModules(bool $production, bool $selling): array {

		$packages = [];

		if($production) {
			$packages = [...$packages, ...self::PACKAGES_PRODUCTION];
		}

		if($selling) {
			$packages = [...$packages, ...self::PACKAGES_SELLING];
		}

		if($selling and $production === FALSE) {
			$packages[] = 'plant';
		}

		$modules = SchoolAiLib::getModules(array_values(array_unique($packages)));

		if($production === FALSE) {

			$modulesProduction = array_merge(...self::notify('getDuplicateModulesProduction'));

			foreach(array_keys($modules) as $module) {

				if(
					in_array($module, $modulesProduction) or
					($modules[$module]->getPackage() === 'plant' and $module !== 'plant\Plant')
				) {
					unset($modules[$module]);
				}

			}

		}

		return $modules;

	}

	/**
	 * Les bornes propres à la duplication, au format de `SchoolAiLib::copy()`.
	 *
	 * Sans la production, une espèce ne se copie que si un produit ou un rapport de la source la
	 * nomme : les autres encombreraient le plan de culture d'une ferme qui n'en a pas.
	 */
	public static function getConditions(bool $production, bool $selling, int $structureSource): array {

		if($production or $selling === FALSE) {
			return [];
		}

		$mProduct = new \product\ProductModel()
			->select(['plant'])
			->whereStructure($structureSource);

		// Les rapports nomment aussi une espèce, et vivent dans un package absent de la version publique.
		$plants = [$mProduct->getSql(), ...self::notify('getDuplicatePlants', $structureSource)];

		return [
			'plant\Plant' => ' AND (m1.id IN '.implode(' OR m1.id IN ', $plants).')',
		];

	}

	/**
	 * Crée la nouvelle ferme, puis la remplit.
	 *
	 * ⚠️ L'ordre est imposé par `StructureLib::create()` : elle valide sa transaction et crée la base
	 * de la structure avant de rendre la main, donc rien ne peut l'annuler, et `delete()` lève. La
	 * copie commence après, sur une ferme **fermée** qui ne s'ouvre qu'une fois la copie finie : une
	 * erreur fatale ou un dépassement du temps d'exécution n'atteint aucun `catch`, et laisserait
	 * sinon une ferme active à moitié copiée dans la liste de son propriétaire. `create()` pose
	 * l'adhésion du propriétaire au statut de la structure, donc fermée elle aussi.
	 */
	public static function duplicate(Structure $eStructureSource, Structure $eStructure, bool $production, bool $selling): array {

		$eStructure->expects(['name', 'owner']);
		$eStructureSource->expects(['legalCountry']);

		$eStructure['status'] = Structure::CLOSED;

		$eStructure['producerType'] = Structure::TEST;
		$eStructure['creation'] = Structure::DUPLICATION;
		$eStructure['producerTypeUpdatedAt'] = new \Sql('NOW()');

		// Le pays de la source et non celui du lecteur : les taux de TVA se numérotent par pays, et
		// les produits copiés comme la configuration alignée portent ceux de la source (cf.
		// `SchoolAiLib::placeCountry()`). Posé avant la création, qui en tire `defaultVat`.
		$eStructure['legalCountry'] = $eStructureSource['legalCountry'];

		StructureLib::create($eStructure, withDefaults: FALSE);

		try {

			self::placeDefaults($eStructure, $production, $selling);

			$counts = self::copy($eStructureSource, $eStructure, $production, $selling);

		} catch(\Throwable $e) {

			SchoolAiLib::clear($eStructure['id']);

			throw $e;

		}

		$eStructure['status'] = Structure::ACTIVE;

		StructureLib::update($eStructure, ['status']);

		return $counts;

	}

	/**
	 * Ce qu'une ferme reçoit à sa création dans la famille qu'on ne copie pas.
	 *
	 * ⚠️ **Rien d'autre que ce que `SchoolAiLib::clear()` efface** : une duplication qui échoue ne
	 * laisse ni ligne copiée ni défaut posé à côté, et une ferme vidée par `clear()` repasse par ici
	 * sans doublon. Les moyens de paiement se posent par l'événement `createStructure` que
	 * `StructureLib::create()` émet (`payment\StructureObserverLib`) : `payment` n'est ni copié ni
	 * effacé, et une seconde insertion lèverait sur `(structure, fqn)`.
	 *
	 * Sans la production, pas d'espèces du référentiel : `PlantLib::duplicateForStructure()`
	 * doublonnerait celles que les produits nomment.
	 */
	public static function placeDefaults(Structure $eStructure, bool $production, bool $selling): void {

		if($production === FALSE) {
			\work\ActionLib::duplicateForStructure($eStructure);
		}

		if($selling === FALSE) {
			\product\UnitLib::duplicateForStructure($eStructure);
			\product\ProductLib::createForStructure($eStructure);
		}

	}

	/**
	 * Copie les familles choisies de la source dans une ferme déjà créée.
	 *
	 * Rend ce qui a été écrit : les lignes copiées et les ventes agrégées.
	 */
	public static function copy(Structure $eStructureSource, Structure $eStructure, bool $production, bool $selling): array {

		$source = $eStructureSource['id'];
		$target = $eStructure['id'];

		$modules = self::getModules($production, $selling);

		$offset = SchoolAiLib::getOffset($modules, $source);

		// Les personnes fictives existent avant la copie : chaque colonne de compte des lignes copiées
		// en vise une (arbitré le 14/09/2026).
		$team = SchoolAiLib::placeTeam($modules, $source, $target);

		// La ligne de map posée à la création cède la place à celle de la source, que la copie emporte
		// avec son terrain : l'unicité par structure refuserait les deux.
		if($production) {
			\map\ConfigurationLib::deleteByStructure($eStructure);
		}

		// Toutes les saisons : la duplication emporte l'histoire de la ferme, et non un intervalle.
		$copied = SchoolAiLib::copy($modules, $source, $target, $team, $offset, NULL, self::getConditions($production, $selling, $source));

		self::placeConfiguration($source, $target);

		$written = ['lignes' => 0, 'ventes' => 0];

		if($selling) {
			$written = SchoolAiLib::aggregateSelling($source, $target, $offset, NULL, self::CUSTOMERS_MIN);
		}

		self::placeFeature($target, $production, $written['ventes'] > 0);

		return [
			'copied' => $copied,
			'sales' => $written['ventes'],
			'items' => $written['lignes'],
		];

	}

	/**
	 * Aligne la configuration sur la source : ce qui décrit le système.
	 *
	 * La liste est celle de la ferme école (`CONFIGURATION_ALIGN`), sans son contrôle à l'exécution :
	 * il lit les colonnes de la base, où une colonne morte arrêterait un geste du produit. La
	 * complétude se tient dans `DuplicateSentinelAiTest`, sur le modèle.
	 *
	 * Les saisons et les autres réglages de culture arrivent avec la ligne de `map\Configuration`
	 * que la copie emporte : une ferme neuve déclarerait l'année en cours et la suivante, et les
	 * séries copiées de 2021 resteraient hors de tous les onglets.
	 */
	public static function placeConfiguration(int $structureSource, int $structure): void {

		$properties = SchoolAiLib::CONFIGURATION_ALIGN;

		$eConfigurationSource = Configuration::model()
			->select($properties)
			->whereStructure($structureSource)
			->get();

		// Les valeurs et non l'élément : il porte l'id de la configuration de la source.
		ConfigurationLib::updateProperties(new Structure(['id' => $structure]), $eConfigurationSource->extracts($properties));

		SchoolAiLib::alignConfiguration($structureSource, $structure);

	}

	/**
	 * Allume les fonctionnalités que la copie remplit.
	 *
	 * Une fonctionnalité de ferme s'allume au premier geste, et la copie n'en fait aucun : sans elles,
	 * l'analyse des ventes s'ouvre sans rubrique (mesuré sur la ferme école le 05/09/2026). Rien ne
	 * s'éteint, une ferme neuve les a toutes à faux.
	 */
	public static function placeFeature(int $structure, bool $production, bool $sales): void {

		$values = [];

		if($production and \series\Series::model()->whereStructure($structure)->exists()) {
			$values['hasCultivations'] = TRUE;
		}

		if($sales) {
			$values['hasSales'] = TRUE;
		}

		if($values === []) {
			return;
		}

		Feature::model()
			->whereStructure($structure)
			->update($values);

		FeatureLib::forget(new Structure(['id' => $structure]));

	}

	/**
	 * Ce que la duplication emportera de la source, famille par famille.
	 *
	 * Les comptes sont ceux de ce que la nouvelle ferme montrera : hors supprimés, et hors planches et
	 * jardins de remplissage, que le plan ne présente pas. La copie emporte les lignes supprimées
	 * aussi, comme la ferme école, pour que les clés qui les visent restent justes.
	 */
	public static function getCounts(Structure $eStructureSource, array $families): array {

		$counts = [];

		if(in_array('production', $families, TRUE)) {
			$counts['production'] = self::getCountsProduction($eStructureSource);
		}

		if(in_array('selling', $families, TRUE)) {
			$counts['selling'] = self::getCountsSelling($eStructureSource);
		}

		return $counts;

	}

	private static function getCountsProduction(Structure $eStructure): array {

		$eSeries = \series\Series::model()
			->select([
				'count' => new \Sql('COUNT(*)', 'int'),
				'first' => new \Sql('MIN(season)', 'int'),
				'last' => new \Sql('MAX(season)', 'int'),
			])
			->whereStructure($eStructure)
			->get();

		$eForecast = \plant\Forecast::model()
			->select([
				'count' => new \Sql('COUNT(DISTINCT season)', 'int'),
				'first' => new \Sql('MIN(season)', 'int'),
				'last' => new \Sql('MAX(season)', 'int'),
			])
			->whereStructure($eStructure)
			->get();

		return [
			'zones' => \map\Zone::model()->whereStructure($eStructure)->count(),
			'plots' => \map\Plot::model()->whereStructure($eStructure)->whereZoneFill(FALSE)->count(),
			'beds' => \map\Bed::model()
				->whereStructure($eStructure)
				->whereZoneFill(FALSE)
				->wherePlotFill(FALSE)
				->whereStatus(\map\Bed::ACTIVE)
				->count(),
			'plants' => \plant\Plant::model()->whereStructure($eStructure)->count(),
			'varieties' => \plant\Variety::model()->whereStructure($eStructure)->count(),
			'sizes' => \plant\Size::model()->whereStructure($eStructure)->count(),
			'sequences' => \sequence\Sequence::model()->whereStructure($eStructure)->count(),
			'series' => $eSeries['count'],
			'seriesFirst' => $eSeries['first'],
			'seriesLast' => $eSeries['last'],
			'tasks' => \series\Task::model()->whereStructure($eStructure)->count(),
			'harvests' => \series\Harvest::model()->whereStructure($eStructure)->count(),
			'hours' => \series\Timesheet::model()
				->select(['time' => new \Sql('ROUND(COALESCE(SUM(time), 0))', 'int')])
				->whereStructure($eStructure)
				->get()['time'],
			'actions' => \work\Action::model()->whereStructure($eStructure)->count(),
			'methods' => \work\Method::model()->whereStructure($eStructure)->count(),
			'tools' => \work\Tool::model()->whereStructure($eStructure)->whereStatus('!=', \work\Tool::DELETED)->count(),
			'suppliers' => \work\Supplier::model()->whereStructure($eStructure)->count(),
			'forecasts' => $eForecast['count'],
			'forecastsFirst' => $eForecast['first'],
			'forecastsLast' => $eForecast['last'],
		];

	}

	/**
	 * Les ventes et les rapports se comptent dans leur package, absent de la version publique, où la
	 * ferme n'en a aucun.
	 */
	private static function getCountsSelling(Structure $eStructure): array {

		$counts = [
			'products' => \product\Product::model()->whereStructure($eStructure)->whereStatus('!=', \product\Product::DELETED)->count(),
			'sales' => 0,
			'salesFirst' => NULL,
			'salesLast' => NULL,
			'customers' => [],
			'reports' => 0,
			'reportsFirst' => NULL,
			'reportsLast' => NULL,
		];

		return array_replace($counts, ...self::notify('getDuplicateCounts', $eStructure));

	}

}
?>
