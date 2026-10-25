<?php
namespace dashboard;

/**
 * Le drapeau de l'accueil **du côté éteint** : la règle qui le lit, les trois routes qu'il ferme, et le
 * réglage qu'une structure fermée n'accepte pas.
 *
 * C'est le cas majoritaire du parc et le seul qu'aucun banc ne voyait. En production
 * `FEATURE_DASHBOARD` vaut `[7]`, donc toute structure sauf la 7 est du côté éteint ; en dev la
 * constante vaut `TRUE`, donc un test qui ne repose pas la closure éprouve toujours l'autre côté.
 * `Preference.t.php` force d'ailleurs `$enabled` à `TRUE` pour tout son fichier, ce qui est juste pour
 * ce qu'il éprouve et laisse ce côté-ci entier.
 *
 * Précédent suivi : `base/entry/test/Entry.t.php::testFlag()`, qui éteint `EntrySetting::$enabled` et
 * joue chaque route du champ par le routeur pour lire l'action qu'elle lève. Le harnais qui joue une
 * route vit dans les fixtures de deux packages (`base/entry/test/EntryFixture.t.php:49`,
 * `commercialisation/amap/test/AmapFixture.t.php:196`) et non dans le cadriciel : `executeRoute()`
 * ci-dessous en est la troisième écriture, réduite à ce qu'une route `read()` demande, et nomme sa
 * route au lieu de la chercher, le routeur n'ayant pas de table en CLI.
 *
 * Aucune écriture en base : la structure de référence n'est que lue, et l'élément de préférence
 * fabriqué ici ne sort pas du processus.
 *
 * Lancement : dev/test package=dashboard file=Flag
 */
class FlagTest extends \Test {

	/**
	 * Le décor et non la structure 7, pour la raison que `Preference.t.php` écrit : un test neuf ne se
	 * monte pas sur une structure réelle (`structure/test/RealStructureSentinelAi.t.php`). F1 porte en
	 * plus toutes les fonctionnalités sauf deux, ce dont `testAcceptsReadsEachFormOfTheFlag()` a besoin
	 * pour éprouver la forme « nom de fonctionnalité » dans les deux sens.
	 */
	const STRUCTURE = \structure\FixtureAiLib::F1;

	protected \structure\Structure $eStructure;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;
	private \Closure $enabledInitial;

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	/**
	 * Le drapeau est **fermé** pour chaque méthode, parce que c'est le sujet du fichier : les méthodes
	 * qui ont besoin de l'autre côté pour prouver qu'elles savent échouer l'ouvrent elles-mêmes.
	 * Forme de `structure/test/AccessSectionAi.t.php`.
	 */
	public function setUp(): void {

		$this->enabledInitial = DashboardSetting::$enabled;

		$this->close();

	}

	public function tearDown(): void {

		DashboardSetting::$enabled = $this->enabledInitial;

	}

	/** Les caches sont des propriétés de processus, que la transaction d'une méthode n'annule pas. */
	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	/**
	 * La règle du drapeau sur chacune des formes que `accepts()` accepte, et sur celle de la production.
	 *
	 * La quatrième assertion est le cas du parc : une liste qui ne contient pas la structure. Les deux
	 * dernières tiennent deux pièges que la signature ne dit pas. La comparaison est **stricte**
	 * (`in_array(…, TRUE)`), donc une liste écrite en chaînes n'ouvre rien, et c'est voulu : un
	 * identifiant de structure est un entier. Et la forme « nom de fonctionnalité » lit une colonne de
	 * `structureFeature`, donc elle répond non sur une fonctionnalité que la structure n'a pas, sans
	 * jamais lever.
	 */
	public function testAcceptsReadsEachFormOfTheFlag(): void {

		$id = $this->eStructure['id'];

		$this->assertEquals(TRUE, DashboardSetting::accepts($this->eStructure, TRUE), "TRUE ouvre tout le parc");
		$this->assertEquals(FALSE, DashboardSetting::accepts($this->eStructure, FALSE), "FALSE ne laisse rien passer");
		$this->assertEquals(FALSE, DashboardSetting::accepts($this->eStructure, []), "une liste vide n'ouvre personne");
		$this->assertEquals(FALSE, DashboardSetting::accepts($this->eStructure, [$id + 1]), "une liste sans la structure ne l'ouvre pas, le cas de tout le parc hors la 7");
		$this->assertEquals(TRUE, DashboardSetting::accepts($this->eStructure, [$id]), "une liste qui la nomme l'ouvre");
		$this->assertEquals(FALSE, DashboardSetting::accepts($this->eStructure, [(string)$id]), "la comparaison est stricte : une liste de chaînes n'ouvre rien");
		$this->assertEquals(TRUE, DashboardSetting::accepts($this->eStructure, 'hasShops'), "une fonctionnalité que la structure a l'ouvre");
		$this->assertEquals(FALSE, DashboardSetting::accepts($this->eStructure, 'hasPdp'), "une fonctionnalité qu'elle n'a pas ne l'ouvre pas");

	}

	/**
	 * Les trois routes gardées par le drapeau, jouées par le routeur, dans les deux sens.
	 *
	 * Drapeau fermé, chacune lève `NotExpectedAction` avant toute lecture. Drapeau ouvert, aucune n'y
	 * tombe : l'accueil et le panneau rendent leur vue, et la route d'un bloc tombe sur le refus
	 * **suivant**, le 404 d'un nom qu'aucun package ne déclare (`DashboardLib::hasBlock()`). C'est ce qui
	 * permet d'éprouver l'autre sens de cette route sans nommer de bloc, qu'aucun fichier de ce package
	 * ne fait, et sans payer le `load()` d'un package tiers.
	 */
	public function testTheClosedStructureRefusesTheThreeRoutes(): void {

		$routes = [
			['dashboard/dashboard', '/ferme/{id}/accueil', [], \ViewAction::class],
			['dashboard/dashboard', '/ferme/{id}/accueil/bloc/{name}', ['name' => 'bloc-invente'], \NotExistsAction::class],
			['dashboard/preference', '/ferme/{id}/accueil/personnaliser', [], \ViewAction::class],
		];

		foreach($routes as [$request, $name, $arguments]) {

			$action = $this->executeRoute($request, $name, $arguments);

			$this->assertEquals(TRUE, $action instanceof \NotExpectedAction, $name.' est refusée drapeau fermé, et non '.get_debug_type($action));

		}

		$this->open();

		foreach($routes as [$request, $name, $arguments, $opened]) {

			$action = $this->executeRoute($request, $name, $arguments);

			$this->assertEquals($opened, get_debug_type($action), $name.' passe le drapeau quand il est ouvert');

		}

	}

	/**
	 * Une structure dont l'accueil n'est pas ouvert n'accepte aucun réglage, et le refus porte où
	 * `ModuleApi` le lit : sur `acceptWrite()` et non sur un `can*`.
	 *
	 * La paire `['canWrite', 'acceptWrite']` est celle que les quatre gestes du panneau font poser par
	 * `ModulePage`, donc `validate()` est ici exactement ce que la route fait. Elle lève
	 * `NotExpectedAction` parce que le test qui tombe ne commence pas par `can` (`framework/core/Data.php`).
	 */
	public function testTheClosedStructureAcceptsNoSetting(): void {

		// Élément construit à la main et non par `build()` : c'est un fichier de test, et ce qu'on éprouve
		// est le couple membre-structure que les quatre gestes font valider, qui n'a ni nom de pièce ni
		// `$_POST` (CLAUDE.md, « Validation et build() »).
		$ePreference = new Preference([
			'user' => \user\ConnectionLib::getOnline(),
			'structure' => $this->eStructure
		]);

		$this->assertEquals(FALSE, $ePreference->acceptWrite(), "drapeau fermé, la structure n'accepte aucun réglage");
		$this->assertEquals(TRUE, $ePreference->canWrite(), "et ce n'est pas une question de droit : le membre a bien le droit d'écrire");

		$refused = FALSE;

		try {
			$ePreference->validate('canWrite', 'acceptWrite');
		} catch(\NotExpectedAction) {
			$refused = TRUE;
		}

		$this->assertEquals(TRUE, $refused, "la paire que ModulePage pose sur les quatre gestes refuse l'écriture");

		$this->open();

		$this->assertEquals(TRUE, $ePreference->acceptWrite(), "drapeau ouvert, la même structure l'accepte");

	}

	/**
	 * La barrière de `isEnabled()`, une branche par cas, les deux closures ouvertes pour toutes les
	 * structures : c'est la forme que prennent les tests, et la barrière doit tenir contre elle.
	 *
	 * - un producteur avec Pilotage entre ;
	 * - une AMAP n'entre jamais, l'accueil étant réservé aux producteurs (décidé le
	 *   2026-10-05) ;
	 * - un producteur sans Pilotage n'entre pas. `hasPilotage()` rend une constante, vraie partout
	 *   aujourd'hui : la branche se joue sur une sous-classe qui répond non ;
	 * - drapeaux fermés, plus personne n'entre.
	 */
	public function testTheBarrierKeepsAmapsAndRequiresPilotage(): void {

		$landingInitial = DashboardSetting::$landing;

		try {

			$this->open();
			DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

			$this->assertTrue(DashboardSetting::isEnabled($this->eStructure), 'un producteur avec Pilotage entre');
			$this->assertTrue(DashboardSetting::isLanding($this->eStructure), 'et y arrive');

			$eStructureAmap = new \structure\Structure(['type' => \structure\Structure::AMAP] + $this->eStructure->getArrayCopy());

			$this->assertFalse(DashboardSetting::isEnabled($eStructureAmap), 'une AMAP n\'entre jamais');
			$this->assertFalse(DashboardSetting::isLanding($eStructureAmap), 'ni n\'y arrive');

			$eStructureWithoutPilotage = new class($this->eStructure->getArrayCopy()) extends \structure\Structure {

				public function hasPilotage(): bool {
					return FALSE;
				}

			};

			$this->assertFalse(DashboardSetting::isEnabled($eStructureWithoutPilotage), 'un producteur sans Pilotage n\'entre pas');
			$this->assertFalse(DashboardSetting::isLanding($eStructureWithoutPilotage), 'ni n\'y arrive');

			$this->close();
			DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

			$this->assertFalse(DashboardSetting::isEnabled($this->eStructure), 'drapeaux fermés, le producteur n\'entre plus');

		} finally {
			DashboardSetting::$landing = $landingInitial;
		}

	}

	private function close(): void {

		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => FALSE;

	}

	private function open(): void {

		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	/**
	 * Une route jouée par le routeur, et l'action qu'elle lève rendue à l'appelant.
	 *
	 * La route est **nommée** (son chemin et le fichier de page qui la porte, tels que
	 * `ouvretaferme/route.c.php` les apparie) au lieu d'être cherchée depuis une URL : `Route::$routes`
	 * est garni par la configuration d'une requête HTTP, que le lanceur de tests ne joue pas. Les
	 * paramètres du chemin arrivent donc ici, et `Page::build()` les verse dans `$_GET`, d'où
	 * `ModulePage` lit l'`id` de la structure (`INPUT('id')`).
	 *
	 * `$data->eUserOnline` et `$data->hasAvatar` sont posés à la main comme dans `EntryFixture.t.php` : en HTTP
	 * c'est `main\PageLib::common()` qui les pose, et le lanceur de tests ne passe pas par lui.
	 */
	private function executeRoute(string $request, string $name, array $arguments): ?\Action {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->hasAvatar = \map\SceneLib::isOpenByStructures(\structure\StructureLib::getOnline());

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];

		try {
			\Page::execute([[$request, $name, $arguments + ['id' => $this->eStructure['id']], NULL]], $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * L'administrateur du décor, un membre réel de la structure de référence : les routes valident
	 * `canRead()` sur la structure **avant** de lire le drapeau, donc un visiteur verrait chaque route
	 * refusée pour une autre raison et le test serait vert sans rien prouver.
	 */
	private function getAdmin(): \user\User {

		foreach(\structure\MemberLib::getByStructure($this->eStructure, index: NULL) as $eMember) {

			if($eMember['role']->notEmpty() and $eMember['role']['admin']) {
				return $eMember['user'];
			}

		}

		throw new \Exception('La structure #'.self::STRUCTURE.' n\'a pas de membre administrateur : monte le décor par dev/test package=structure file=FixtureAi');

	}

	private static function getMemberCache(): \ReflectionProperty {

		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');

	}

}
?>
