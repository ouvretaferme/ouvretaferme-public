<?php
namespace toolbox;

/**
 * Les pluviomètres d'une structure : un par lieu, la ferme ou une zone (étape `placed` de
 * `Gauge::properties()`). Un pluviomètre supprimé passe inactif et garde ses relevés en base ;
 * toute lecture part des seuls pluviomètres actifs.
 */
class GaugeLib extends GaugeCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['zone'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['zone', 'status'];
	}

	/**
	 * Le premier pluviomètre fait de la Pluviométrie un outil de la structure (`Feature.hasToolbox`),
	 * qui le reste même quand tous ses pluviomètres ont été supprimés : la Boîte à outils la range
	 * alors sous « Vos outils », sans chiffres.
	 */
	public static function create(Gauge $e): void {

		$e->expects(['structure']);

		Gauge::model()->beginTransaction();

			parent::create($e);

			if(in_array(Favorite::RAIN, $e['structure']->conf(\structure\Feature::class)['hasToolbox']) === FALSE) {
				\structure\FeatureLib::addToolbox($e['structure'], Favorite::RAIN);
			}

		Gauge::model()->commit();

	}

	/**
	 * Les pluviomètres actifs de la structure, toujours dans l'ordre de leur création, la ferme comprise
	 * (retour du 24/09) : un pluviomètre garde sa place sur la page comme dans la Boîte.
	 */
	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		return Gauge::model()
			->select([
				'id', 'structure', 'status', 'createdAt',
				'zone' => ['name']
			])
			->whereStructure($eStructure)
			->whereStatus(Gauge::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Les lieux où poser un nouveau pluviomètre, parmi les zones de la saison `$cZone` : ceux qui
	 * n'ont pas de pluviomètre actif. La ferme vient en tête quand elle est libre ; elle n'a pas d'id
	 * et se range sous 0, qu'aucune zone ne porte. Pour changer de lieu `$eGaugeMoved`, son propre
	 * lieu compte parmi les libres : c'est la valeur que le formulaire présélectionne.
	 */
	public static function getFree(\structure\Structure $eStructure, \Collection $cZone, Gauge $eGaugeMoved = new Gauge()): \Collection {

		$cGauge = Gauge::model()
			->select('zone')
			->whereStructure($eStructure)
			->whereStatus(Gauge::ACTIVE)
			->whereId('!=', $eGaugeMoved, if: $eGaugeMoved->notEmpty())
			->getCollection();

		$hasFarm = FALSE;
		$placed = [];

		foreach($cGauge as $eGauge) {

			if($eGauge['zone']->empty()) {
				$hasFarm = TRUE;
			} else {
				$placed[] = $eGauge['zone']['id'];
			}

		}

		$cZoneFree = new \Collection();

		if($hasFarm === FALSE) {
			$cZoneFree[0] = new \map\Zone(['id' => NULL]);
		}

		foreach($cZone as $eZone) {

			if(in_array($eZone['id'], $placed) === FALSE) {
				$cZoneFree[$eZone['id']] = $eZone;
			}

		}

		return $cZoneFree;

	}

}
?>
