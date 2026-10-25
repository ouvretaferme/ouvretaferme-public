<?php
namespace aid;

/**
 * Les lignes attendues des listes officielles fermées, et la couverture qu'elles donnent du catalogue :
 * par famille et par région, la part des lignes résolues, trouvées ou absentes justifiées.
 */
class ExpectedLib extends ExpectedCrud {

	// Les familles qui se lisent sur une région, dans l'ordre de l'écran ; le national se compte à part.
	const FAMILIES_REGION = [Expected::FEADER, Expected::DEPARTMENT, Expected::GAL, Expected::PARK, Expected::EPCI];

	// Les régions que la colonne de côté nomme comme les moins couvertes (décidé par V-Sub).
	const LOWEST = 8;

	const SEED_CREATED = 'created';
	const SEED_UPDATED = 'updated';
	const SEED_KEPT = 'kept';
	const SEED_UNCHANGED = 'unchanged';

	// `region` après `department`, dont elle se déduit, et `organization` après `aid`, qu'elle exclut.
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['code', 'family', 'department', 'region', 'number', 'name', 'aid', 'organization', 'justification', 'justificationUrl', 'listUrl'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['family', 'department', 'region', 'number', 'name', 'aid', 'organization', 'justification', 'justificationUrl', 'listUrl'];
	}

	public static function getByCode(string $code): Expected {

		$eExpected = new Expected();

		Expected::model()
			->select(Expected::getSelection())
			->whereCode($code)
			->get($eExpected);

		return $eExpected;

	}

	/**
	 * Parmi les Départements du semis, indexés par leur code, ceux qui gardent leur ligne
	 * `department-<code>` : ceux qu'aucune tranche n'a détaillés par une ligne `department-<code>-<objet>`.
	 * Un Département détaillé serait sinon compté une seconde fois comme un seul organisme, et chaque
	 * tranche aurait à retoucher le semis (décidé par V-Sub).
	 */
	public static function getDepartmentsUndetailed(array $departments): array {

		$departmentsDetailed = Expected::model()
			->select('department')
			->whereFamily(Expected::DEPARTMENT)
			->whereCode('LIKE', 'department-%-%')
			->group(['department'])
			->getCollection()
			->getColumn('department');

		// PHP range une clé comme '31' en entier, la colonne la stocke en chaîne.
		return array_filter($departments, fn(int|string $department) => in_array((string)$department, $departmentsDetailed, TRUE) === FALSE, ARRAY_FILTER_USE_KEY);

	}

	/**
	 * Pose une ligne du semis par son code : la crée si elle manque, sinon remet d'aplomb ce qui a dérivé.
	 * Une ligne de Département qui porte une justification ne se touche pas, ni sa justification, ni son
	 * organisme, ni son nom : une tranche l'a relue à la main, et le semis ne connaît pas ce qu'elle a
	 * établi (décidé par V-Sub).
	 */
	public static function seed(string $code, array $input): string {

		$eExpected = self::getByCode($code);

		$fw = new \FailWatch();

		if($eExpected->empty()) {

			$eExpected = new Expected();
			$eExpected->build(self::getPropertiesCreate(), ['code' => $code] + $input);

			$fw->validate();

			self::create($eExpected);

			return self::SEED_CREATED;

		}

		if($eExpected['family'] === Expected::DEPARTMENT and $eExpected['justification'] !== NULL) {
			return self::SEED_KEPT;
		}

		// Deux éléments ne se comparent pas par valeur : une aide ou un organisme se compare par son id.
		$compared = fn($value) => ($value instanceof \Element) ? ($value->empty() ? NULL : $value['id']) : $value;

		$before = array_map($compared, $eExpected->extracts(self::getPropertiesUpdate()));

		$eExpected->build(self::getPropertiesUpdate(), $input);

		$fw->validate();

		// Seul ce qui a dérivé s'écrit, pour qu'un rejeu sans changement laisse `updatedAt` en place.
		$propertiesChanged = array_keys(array_diff_assoc(array_map($compared, $eExpected->extracts(self::getPropertiesUpdate())), $before));

		if($propertiesChanged === []) {
			return self::SEED_UNCHANGED;
		}

		self::update($eExpected, $propertiesChanged);

		return self::SEED_UPDATED;

	}

	/**
	 * Les familles qui ont au moins une ligne quelque part. Une famille qui n'en a aucune n'a pas encore
	 * été recherchée, ce qui n'est pas la même chose qu'une région où rien n'est attendu (décidé par V-Sub).
	 */
	public static function getFamiliesSearched(): array {

		return Expected::model()
			->select('family')
			->group(['family'])
			->getCollection()
			->getColumn('family');

	}

	/**
	 * La couverture de tout le catalogue : le total de la tête, le national à part, puis chaque région
	 * pour la famille choisie, toutes les familles régionales quand il n'y en a pas, et chaque
	 * département pour les lignes de département. Une région sans ligne dans la famille n'a pas de taux
	 * (`rate` NULL) : elle sort de l'échelle et des moins couvertes, qui sont les plus bas taux parmi
	 * les autres de métropole. L'échelle est celle des départements quand la carte se teinte par
	 * département, et `scale` NULL quand rien de la métropole n'a de taux, ce qui n'empêche pas
	 * l'outre-mer d'en avoir (`isEmpty` seul dit qu'il n'y a aucune ligne).
	 */
	public static function getCoverage(?string $family): array {

		$cExpected = Expected::model()
			->select(['id', 'family', 'region', 'department', 'aid', 'organization', 'justification'])
			->getCollection();

		self::fillAids($cExpected);

		$total = self::getTally();
		$national = self::getTally();
		$regions = array_fill_keys(array_keys(AidSetting::REGIONS), self::getTally());
		$departments = array_fill_keys(array_keys(AidSetting::DEPARTMENTS), self::getTally());

		foreach($cExpected as $eExpected) {

			$state = $eExpected->getState();

			self::count($total, $state);

			if($eExpected['family'] === Expected::NATIONAL) {
				self::count($national, $state);
			} else if($family === NULL or $eExpected['family'] === $family) {
				self::count($regions[$eExpected['region']], $state);
			}

			if($eExpected['family'] === Expected::DEPARTMENT) {
				self::count($departments[$eExpected['department']], $state);
			}

		}

		$total = self::rate($total);
		$national = self::rate($national);
		$regions = array_map(self::rate(...), $regions);
		$departments = array_map(self::rate(...), $departments);

		$rated = array_filter($regions, fn(array $tally) => $tally['rate'] !== NULL);
		uasort($rated, fn(array $tally1, array $tally2) => $tally1['rate'] <=> $tally2['rate']);

		// L'outre-mer a son propre bloc : il n'entre jamais dans les moins couvertes, ni dans l'échelle,
		// qui ne compte que ce que la carte colore (décidé par V-Sub).
		$ratedMainland = array_filter($rated, fn(int|string $region) => in_array((string)$region, AidSetting::REGIONS_OVERSEAS, TRUE) === FALSE, ARRAY_FILTER_USE_KEY);

		$scaled = ($family === Expected::DEPARTMENT) ? array_filter($departments, fn(array $tally, int|string $department) => (
			$tally['rate'] !== NULL and
			in_array(AidSetting::DEPARTMENTS[(string)$department][1], AidSetting::REGIONS_OVERSEAS, TRUE) === FALSE
		), ARRAY_FILTER_USE_BOTH) : $ratedMainland;

		return [
			'total' => $total,
			'national' => $national,
			'regions' => $regions,
			'departments' => $departments,
			'isEmpty' => ($rated === []),
			'lowest' => array_slice(array_keys($ratedMainland), 0, self::LOWEST),
			'scale' => ($scaled === []) ? NULL : [min(array_column($scaled, 'rate')), max(array_column($scaled, 'rate'))],
		];

	}

	/**
	 * Les lignes d'une région, chacune avec les aides qui peuvent la résoudre, groupées par famille dans
	 * l'ordre du détail, une famille sans ligne comprise, puis rangées par département et par numéro. La
	 * famille choisie sur la carte vient en premier (décidé par V-Sub).
	 */
	public static function getByRegion(string $region, ?string $family = NULL): \Collection {

		$cExpected = Expected::model()
			->select([
				'id', 'family', 'department', 'number', 'name', 'justification', 'justificationUrl',
				'aid' => ['name'],
				'organization' => ['name'],
			])
			->whereRegion($region)
			->getCollection();

		self::fillAids($cExpected);

		$families = ($family === NULL) ? self::FAMILIES_REGION : array_values(array_unique([$family, ...self::FAMILIES_REGION]));

		$ccExpected = new \Collection(array_map(fn() => new \Collection(), array_flip($families)))->setDepth(2);

		foreach($cExpected as $eExpected) {
			$ccExpected[$eExpected['family']][$eExpected['id']] = $eExpected;
		}

		// En PHP et non en SQL, pour l'ordre naturel des numéros et des départements : 2 avant 10, 2A avant 21.
		$ccExpected->sort(['department', 'number', 'name'], binary: TRUE, natural: TRUE, depth: 2);

		return $ccExpected;

	}

	// Le décompte de chaque famille d'une région, pour les chiffres de tête de son détail.
	public static function countByFamily(\Collection $ccExpected): array {

		$families = [];

		foreach($ccExpected as $family => $cExpected) {

			$families[$family] = self::getTally();

			foreach($cExpected as $eExpected) {
				self::count($families[$family], $eExpected->getState());
			}

		}

		return array_map(self::rate(...), $families);

	}

	/**
	 * Pose sur chaque ligne les aides qui peuvent la résoudre (`cAid`, lue par Expected::getState()) :
	 * son aide, ou les aides publiées de son organisme. Deux requêtes pour toutes les lignes, chacune
	 * avec ce que Aid::getHiddenReason() lit pour dire si l'aide se montre ou si elle est close.
	 */
	private static function fillAids(\Collection $cExpected): void {

		$aids = [];
		$organizations = [];

		foreach($cExpected as $eExpected) {

			if($eExpected['aid']->notEmpty()) {
				$aids[] = $eExpected['aid']['id'];
			}

			if($eExpected['organization']->notEmpty()) {
				$organizations[] = $eExpected['organization']['id'];
			}

		}

		$cAid = Aid::model()
			->select(self::getSelectionAid())
			->whereId('IN', $aids)
			->getCollection(NULL, NULL, 'id');

		$cAidByOrganization = Aid::model()
			->select(array_merge(self::getSelectionAid(), ['organization']))
			->whereOrganization('IN', $organizations)
			->whereStatus(Aid::PUBLISHED)
			->getCollection(NULL, NULL, ['organization', 'id']);

		foreach($cExpected as $eExpected) {

			if($eExpected['aid']->notEmpty() and $cAid->offsetExists($eExpected['aid']['id'])) {
				$eExpected['cAid'] = new \Collection([$eExpected['aid']['id'] => $cAid[$eExpected['aid']['id']]]);
			} else if($eExpected['organization']->notEmpty() and $cAidByOrganization->offsetExists($eExpected['organization']['id'])) {
				$eExpected['cAid'] = $cAidByOrganization[$eExpected['organization']['id']];
			} else {
				$eExpected['cAid'] = new \Collection();
			}

		}

	}

	private static function getSelectionAid(): array {

		return [
			'id', 'name', 'status', 'calendar',
			'cEdition' => new EditionModel()->select(['id', 'aid', 'state', 'startDate', 'endDate'])->delegateCollection('aid'),
			'cSource' => new SourceModel()->select(['id', 'aid', 'kind', 'status'])->delegateCollection('aid'),
		];

	}

	private static function getTally(): array {
		return [Expected::FOUND => 0, Expected::JUSTIFIED => 0, Expected::OPEN => 0];
	}

	private static function count(array &$tally, string $state): void {
		$tally[$state]++;
	}

	// Le nombre de lignes, celles qui sont résolues et leur part, NULL quand le territoire n'a aucune ligne : 0 % dirait un trou.
	private static function rate(array $tally): array {

		$resolved = $tally[Expected::FOUND] + $tally[Expected::JUSTIFIED];
		$expected = $resolved + $tally[Expected::OPEN];

		return $tally + [
			'resolved' => $resolved,
			'expected' => $expected,
			'rate' => ($expected === 0) ? NULL : fdiv($resolved, $expected),
		];

	}

	public static function update(Expected $e, array $properties): void {

		$e['updatedAt'] = \Clock::datetime();
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

}
?>
