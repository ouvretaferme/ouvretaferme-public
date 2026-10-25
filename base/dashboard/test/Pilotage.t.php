<?php
namespace dashboard;

/**
 * Le groupe Bureau du panneau : quand « Du nouveau » y paraît, et la route qui le dit hors des
 * écrans qui lisent les nouvelles.
 *
 * Les nouvelles se lisent sous un membre fictif injecté dans le cache d'appartenance, comme
 * `Preference.t.php`, ce qui éprouve le droit sans dépendre des rôles que le décor donne à ses membres
 * réels. Rien ne s'écrit en base : les nouvelles sont posées dans le cache que le moteur lit.
 *
 * Lancement : dev/test package=dashboard file=Pilotage
 */
class PilotageTest extends \Test {

	/** Le décor, pour la raison que `Preference.t.php` écrit. */
	const STRUCTURE = \structure\FixtureAiLib::F1;

	/** Un utilisateur qui ne désigne personne, pour la raison que `Preference.t.php` écrit. */
	const USER = 4000000001;

	private const TODAY = '2026-09-23 10:00:00';

	protected \structure\Structure $eStructure;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;
	private \Closure $enabledInitial;
	private \Closure $landingInitial;

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
		$this->enabledInitial = DashboardSetting::$enabled;
		$this->landingInitial = DashboardSetting::$landing;

		// L'écran et le point d'arrivée ouverts pour cette structure, quels que soient les drapeaux du
		// poste : la route du groupe se ferme avec le point d'arrivée, et la méthode qui l'éprouve le
		// referme elle-même.
		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function setUp(): void {

		$this->setClock(self::TODAY);
		$this->connectAs();

		// Le blog réel publie chaque semaine : les nouvelles se taisent d'office, et la méthode qui les
		// éprouve les fait parler (`announce()`).
		$this->announce(NULL);

	}

	/** Les caches sont des propriétés de processus, que rien n'annule à la fin d'une méthode. */
	public function finalize(): void {

		\Cache::redis()->delete($this->getNewsKey());

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		DashboardSetting::$enabled = $this->enabledInitial;
		DashboardSetting::$landing = $this->landingInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	/** Hors du point d'arrivée, la route refuse : l'onglet qui la demande n'existe pas. */
	public function testTheRouteFollowsTheLanding(): void {

		$action = $this->executeRoute('/ferme/{id}/accueil/pilotage');

		$this->assertEquals(\ViewAction::class, get_debug_type($action), "ouverte, la route rend sa vue");

		DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

		try {
			$action = $this->executeRoute('/ferme/{id}/accueil/pilotage');
		} finally {
			DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;
		}

		$this->assertEquals(\NotExpectedAction::class, get_debug_type($action), "fermée, elle refuse");

	}

	/**
	 * « Du nouveau » paraît dès que l'emplacement des nouvelles a un occupant qui parle, et se tait avec
	 * lui (L46).
	 */
	public function testTheNewsGiveAnEntryWhenTheyHaveSomethingToSay(): void {

		$this->assertFalse(DashboardLib::hasNews($this->eStructure), "le blog se tait, pas d'entrée");

		$this->announce([['id' => 1, 'title' => 'Une nouvelle', 'publishedAt' => '2026-09-22 10:00:00', 'card' => FALSE]]);

		$this->assertTrue(DashboardLib::hasNews($this->eStructure), "le blog parle, l'entrée paraît");

	}

	/**
	 * La page sert un membre avec ou sans nouvelle, l'adresse se gardant et se rechargeant après que le
	 * blog s'est tu. Elle ne refuse que hors du point d'arrivée.
	 */
	public function testTheNewsPageKeepsItsAddress(): void {

		$this->assertEquals([], DashboardLib::loadNews($this->eStructure), "le blog se tait");
		$this->assertEquals(\ViewAction::class, get_debug_type($this->executeRoute('/ferme/{id}/accueil/nouveau')), "rien de neuf, la page s'affiche vide");
		$this->assertEquals(['website-news' => \website\WebsiteSetting::BLOG_URL], DashboardLib::loadNewsUrls($this->eStructure), "rien de neuf, le chemin vers le blog reste");

		$this->announce([['id' => 1, 'title' => 'Une nouvelle', 'publishedAt' => '2026-09-22 10:00:00', 'card' => FALSE]]);

		$this->assertEquals(\ViewAction::class, get_debug_type($this->executeRoute('/ferme/{id}/accueil/nouveau')), "une nouvelle, la page s'affiche");

		DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

		try {
			$action = $this->executeRoute('/ferme/{id}/accueil/nouveau');
		} finally {
			DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;
		}

		$this->assertEquals(\NotExpectedAction::class, get_debug_type($action), "hors du point d'arrivée, elle refuse");

	}

	/**
	 * Les nouvelles du blog posées au cache que `DashboardLib::loadData()` lit, dans l'enveloppe qu'il y
	 * range, sous la clé du membre fictif que personne d'autre ne lit. `NULL` les fait taire.
	 */
	private function announce(?array $news): void {
		\Cache::redis()->set($this->getNewsKey(), ['data' => $news], 300);
	}

	private function getNewsKey(): string {
		return DashboardLib::getCacheKey('website-news', $this->eStructure, new \user\User(['id' => self::USER]));
	}

	/**
	 * Une route de la page jouée par le routeur, et l'action qu'elle lève rendue à l'appelant. Harnais
	 * de `Landing.t.php`, réduit aux routes sans paramètre de ce fichier.
	 */
	private function executeRoute(string $route): ?\Action {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];

		try {
			\Page::execute([['dashboard/dashboard', $route, ['id' => $this->eStructure['id']], NULL]], $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Le membre fictif, gestionnaire : `isLanding()` et le droit de lire la structure ne lui refusent
	 * rien, et les nouvelles ne dépendent d'aucun rôle.
	 */
	private function connectAs(): void {

		$eUser = new \user\User(['id' => self::USER]);

		\user\ConnectionLib::setOnline($eUser);

		$eMember = new \structure\Member([
			'structure' => $this->eStructure['id'],
			'role' => new \structure\Role([
				'admin' => TRUE
			]),
		]);

		$property = self::getMemberCache();
		$cMember = $property->getValue();
		$cMember[$eUser['id']] = new \Collection([$this->eStructure['id'] => $eMember]);
		$property->setValue(NULL, $cMember);

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');
	}

}
?>
