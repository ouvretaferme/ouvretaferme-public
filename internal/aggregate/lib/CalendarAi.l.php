<?php
namespace aggregate;

/**
 * Le calendrier type d'une espèce sous un climat : les semaines où elle se récolte, et celles où
 * elle se livre encore une fois la conservation comptée.
 *
 * Une ligne par couple (climat, espèce de référence), sans année : c'est une saison habituelle, lue
 * sur les plans de culture de trois saisons confondues.
 *
 * La table n'est pas recalculée : ses lignes ont été calculées une fois en dev, validées en
 * recette, et figées dans `calendarDataAi.php`, que chaque environnement sème (`seed()`). « Le
 * calcul du plan de culture par climat est fait 1 fois ici en dev, et stocké indéfiniment en prod,
 * pas de cron » (02/10/2026). `harvestWeeks` porte les semaines de
 * récolte, `weeks` celles où l'espèce est livrable, récolte et conservation confondues ; l'écart
 * entre les deux est ce que les barres d'une saison dessinent en plus court.
 *
 * `structures` compte les structures qui cultivent l'espèce derrière la ligne, et `fallback` dit
 * que ce compte est celui de tous les climats, celui du climat étant trop mince pour dessiner une
 * saison (CalendarProjectionAiLib::STRUCTURES_MIN). Aucun écran ne l'affiche : il sert à lire la
 * table et à comparer deux passages.
 *
 * `online` traduit le seuil d'anonymat, comme sur aggregate\Price : une ligne sous le seuil serait
 * écrite sans jamais être servie. Aucune ne l'est aujourd'hui, la projection exigeant plus de
 * structures pour dessiner une saison que le seuil n'en demande pour la servir ; la colonne garde
 * les deux règles séparées, pour que baisser l'une ne baisse pas l'autre.
 *
 * Le climat d'une structure se lit ici aussi, parce que le calendrier est la seule chose qui le
 * déduise : la projection s'en sert pour ranger chaque structure, le départ de la composition des
 * paniers pour préremplir son choix.
 */
class CalendarAiLib extends CalendarCrud {

	// Même découpage que PriceAiLib, et pour la même raison : la taille d'un INSERT dépend d'un
	// réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/**
	 * L'altitude à partir de laquelle une structure est rangée en climat montagnard, en mètres.
	 */
	private const MOUNTAIN_ALTITUDE = 600;

	/**
	 * Le pays où le climat se déduit, la France.
	 *
	 * Décidé le 02/10/2026 : « Uniquement pour la France, jamais déduit pour les autres
	 * pays ». Le découpage ci-dessous est tracé pour la métropole, et l'appliquer à une structure
	 * belge ou suisse rendrait un climat vraisemblable et faux.
	 */
	private const COUNTRY = 75;

	/**
	 * Le calendrier figé, suivi par git : `[fqn de l'espèce => [climat => ['harvestWeeks' => …,
	 * 'weeks' => …, 'structures' => …, 'fallback' => …]]]`. L'espèce y est désignée par son fqn,
	 * son id n'étant pas le même d'un environnement à l'autre. Écrit par
	 * CalendarProjectionAiLib::freeze().
	 */
	const FILE = __DIR__.'/calendarDataAi.php';

	public static function getFrozen(): array {
		return require self::FILE;
	}

	/**
	 * Sème la table depuis un calendrier figé (`getFrozen()`), et rend le compte des lignes écrites
	 * et les fqn que le référentiel partagé ne porte pas : `['calendars' => int, 'missing' => [fqn]]`.
	 *
	 * Un seul fqn absent et rien n'est écrit : une espèce sautée en silence serait une espèce sans
	 * saison au départ des paniers, que rien ne signalerait.
	 *
	 * Se rejoue sans effet : la table est réécrite en entier sous transaction, comme une projection
	 * (CalendarProjectionAiLib::publish()), et un second semis du même fichier rend les mêmes lignes.
	 * Toute ligne semée est servie, le fichier ne portant que des lignes au seuil.
	 */
	public static function seed(array $frozen): array {

		$cPlant = \plant\Plant::model()
			->select(['id', 'fqn'])
			->whereStructure(NULL)
			->whereFqn('IN', array_keys($frozen))
			->getCollection(index: 'fqn');

		$missing = [];

		foreach(array_keys($frozen) as $fqn) {

			if($cPlant->offsetExists($fqn) === FALSE) {
				$missing[] = $fqn;
			}

		}

		if($missing !== []) {

			return [
				'calendars' => 0,
				'missing' => $missing,
			];

		}

		$cCalendar = new \Collection();

		foreach($frozen as $fqn => $climates) {

			foreach($climates as $climate => $calendar) {

				$cCalendar[] = new Calendar([
					'climate' => $climate,
					'plant' => $cPlant[$fqn],
					'harvestWeeks' => $calendar['harvestWeeks'],
					'weeks' => $calendar['weeks'],
					'structures' => $calendar['structures'],
					'fallback' => $calendar['fallback'],
					'online' => TRUE,
				]);

			}

		}

		Calendar::model()->beginTransaction();

			self::deleteOffline();
			self::deleteOnline();
			self::createCollection($cCalendar);

		Calendar::model()->commit();

		return [
			'calendars' => $cCalendar->count(),
			'missing' => [],
		];

	}

	/**
	 * Le calendrier d'un climat, par espèce de référence.
	 */
	public static function getByClimate(string $climate): \Collection {

		return Calendar::model()
			->select(['id', 'plant', 'harvestWeeks', 'weeks'])
			->whereClimate($climate)
			->whereOnline(TRUE)
			->getCollection(index: 'plant');

	}

	/**
	 * Les espèces de référence que le calendrier sait dater, tous climats confondus : celles qu'un
	 * plan de paniers peut ajouter à ses espèces livrées.
	 */
	public static function getPlantIds(): array {

		return Calendar::model()
			->whereOnline(TRUE)
			->group('plant')
			->getColumn('plant')
			->getIds();

	}

	/**
	 * Le climat d'une structure : celui qu'elle a déclaré, sinon celui que sa position donne, sinon
	 * aucun.
	 *
	 * L'adresse se lit ici, sur le seul id : l'appelant tient la structure de la page, dont la
	 * sélection n'a pas à suivre ce que le calendrier regarde.
	 */
	public static function getClimate(\structure\Structure $eStructure): ?string {

		$eStructure->expects(['id']);

		$eStructureLocated = \structure\Structure::model()
			->select(['id', 'legalCountry', 'legalPostcode'])
			->whereId($eStructure['id'])
			->get();

		$eSystem = \structure\System::model()
			->select(['id', 'climate', 'placeLngLat', 'altitude'])
			->whereStructure($eStructure)
			->get();

		return self::getClimateBySystem($eStructureLocated, $eSystem);

	}

	/**
	 * Même lecture, sur un système déjà chargé : la projection en lit des milliers d'une traite.
	 *
	 * Une structure qui n'a pas placé son site est rangée par le centre du département de son
	 * adresse. L'altitude, elle, ne se devine pas : sans système renseigné, aucune structure n'est
	 * montagnarde.
	 */
	public static function getClimateBySystem(\structure\Structure $eStructure, \structure\System $eSystem): ?string {

		$altitude = NULL;
		$lngLat = NULL;

		if($eSystem->notEmpty()) {

			if($eSystem['climate'] !== NULL) {
				return $eSystem['climate'];
			}

			$altitude = $eSystem['altitude'];
			$lngLat = $eSystem['placeLngLat'];

		}

		if($eStructure['legalCountry']->empty() or $eStructure['legalCountry']['id'] !== self::COUNTRY) {
			return NULL;
		}

		if($lngLat === NULL) {

			$department = \structure\SchoolAiLib::getDepartment($eStructure['legalPostcode']);

			if($department === NULL) {
				return NULL;
			}

			[, , $lng, $lat] = \structure\SchoolAiLib::DEPARTMENTS[$department];

		} else {
			[$lng, $lat] = $lngLat;
		}

		return self::deduceClimate($lat, $lng, $altitude);

	}

	/**
	 * Le climat d'un point de la métropole, par un découpage à grands traits.
	 *
	 * Quatre zones et non une carte : l'altitude d'abord, puis le pourtour méditerranéen (le sud
	 * du Massif central et la vallée du Rhône jusqu'à Valence, le Roussillon), puis l'est du
	 * méridien de Clermont sous la latitude de Lille, et l'océanique pour le reste. Un point hors
	 * du cadre de la métropole n'est rangé nulle part, ce qui écarte l'outre-mer.
	 */
	public static function deduceClimate(float $lat, float $lng, ?int $altitude): ?string {

		if($lat < 41 or $lat > 51.6 or $lng < -5.6 or $lng > 10) {
			return NULL;
		}

		if($altitude !== NULL and $altitude >= self::MOUNTAIN_ALTITUDE) {
			return Calendar::MOUNTAIN;
		}

		if($lat <= 44.6 and ($lng >= 2.6 or ($lng >= 2.0 and $lat <= 43.4))) {
			return Calendar::MEDITERRANEAN;
		}

		if($lng >= 3.0 and $lat < 50.0) {
			return Calendar::CONTINENTAL;
		}

		return Calendar::OCEANIC;

	}

	/**
	 * Dépose les lignes d'un passage.
	 *
	 * Écriture directe et par lot, à rebours de la règle du dépôt, pour la raison écrite sur
	 * CultivationAiLib::createByPlant() : cette table n'est la source de rien et ne porte aucune
	 * règle de module, et create() ligne à ligne ne coûterait que des requêtes.
	 */
	public static function createCollection(\Collection $cCalendar): void {

		foreach(array_chunk($cCalendar->getArrayCopy(), self::CHUNK) as $chunk) {

			$cCalendarChunk = new \Collection($chunk);

			Calendar::model()->insert($cCalendarChunk);

		}

	}

	/**
	 * Jette les lignes qu'aucun lecteur ne sert.
	 */
	public static function deleteOffline(): void {

		Calendar::model()
			->whereOnline(FALSE)
			->delete();

	}

	/**
	 * Jette les lignes servies.
	 *
	 * C'est l'appelant qui enferme ce mouvement et l'écriture de la version neuve dans une
	 * transaction : entre les deux, la table n'aurait rien à servir. L'UNIQUE (climate, plant)
	 * interdit de garder les deux versions côte à côte.
	 */
	public static function deleteOnline(): void {

		Calendar::model()
			->whereOnline(TRUE)
			->delete();

	}

}
?>
