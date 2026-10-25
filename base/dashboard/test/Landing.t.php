<?php
namespace dashboard;

/**
 * Le second drapeau de l'accueil, celui qui en fait le **point d'arrivée** du membre, éprouvé dans
 * l'état que la scission ouvre : l'écran ouvert, le point d'arrivée fermé.
 *
 * `Flag.t.php` tient le premier drapeau (`$enabled`), qui dit si l'adresse répond : ses trois routes et
 * le réglage qu'une structure fermée n'accepte pas. Ce fichier tient l'autre moitié, et seulement elle.
 * C'est l'état de la production après la scission, et c'est aussi celui qu'aucun banc ne voyait avant
 * elle, les deux questions n'ayant jusque-là qu'une seule réponse : `/ferme/{id}/accueil` répond, et
 * pourtant rien n'y mène. Les points que le second drapeau commande s'éteignent donc ensemble,
 * l'onglet Bureau compris : son groupe est ce qui mène à l'accueil, et le laisser l'aurait laissé posé
 * sur tous les écrans d'une structure que plus rien n'y conduit.
 *
 * Aucune écriture en base : la structure du décor n'est que lue, et le menu de compte est rendu dans le
 * processus du test.
 *
 * Lancement : dev/test package=dashboard file=Landing
 */
class LandingTest extends \Test {

	/** Le décor et non une structure réelle, pour la raison que `Flag.t.php` écrit. */
	const STRUCTURE = \structure\FixtureAiLib::F1;

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

		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	/**
	 * L'état de la scission pour chaque méthode : l'écran **ouvert**, le point d'arrivée **fermé**. Les
	 * méthodes qui ont besoin de l'autre côté pour prouver qu'elles savent échouer l'ouvrent elles-mêmes.
	 */
	public function setUp(): void {

		$this->enabledInitial = DashboardSetting::$enabled;
		$this->landingInitial = DashboardSetting::$landing;

		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->closeLanding();

	}

	public function tearDown(): void {

		DashboardSetting::$enabled = $this->enabledInitial;
		DashboardSetting::$landing = $this->landingInitial;

	}

	/** Les caches sont des propriétés de processus, que la transaction d'une méthode n'annule pas. */
	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	/**
	 * La valeur déclarée, et non une valeur de test : le second drapeau ne lit que
	 * `FEATURE_DASHBOARD_LANDING`, que le `secret.c.php` d'un poste peut refermer.
	 *
	 * `setUp()` ayant déjà remplacé la closure, c'est celle qu'il a mise de côté qui est appelée.
	 */
	public function testTheDeclaredFlagFollowsTheSecret(): void {

		$this->assertEquals(TRUE, defined('FEATURE_DASHBOARD_LANDING'), "la constante est toujours définie");
		$this->assertEquals(DashboardSetting::accepts($this->eStructure, FEATURE_DASHBOARD_LANDING), ($this->landingInitial)($this->eStructure), "le point d'arrivée suit la constante du poste");

	}

	/**
	 * Le défaut, éprouvé quel que soit le poste : une constante se définit une fois par processus, et
	 * celui du test a déjà lu le `secret.c.php` de la machine. Le fichier de réglages est donc rechargé
	 * dans un processus neuf, où `\Settings` est la seule chose qu'il demande au chargement, les
	 * closures ne résolvant leurs types qu'à l'appel.
	 */
	public function testWithoutTheSecretTheFlagFollowsTheScreen(): void {

		$this->assertEquals(TRUE, self::loadSetting(''), "sans la constante, le drapeau s'ouvre partout où l'écran l'est");
		$this->assertEquals([7], self::loadSetting("define('FEATURE_DASHBOARD_LANDING', [7]);"), "une valeur posée par le secret n'est pas écrasée");

	}

	/**
	 * Les deux drapeaux sont bien deux : l'écran s'ouvre sans le point d'arrivée, et le point
	 * d'arrivée s'ouvre par sa propre closure. L'autre sens, le point d'arrivée sans l'écran, est
	 * borné par `testTheLandingNeverOutgrowsTheScreen()`.
	 */
	public function testTheTwoFlagsAreIndependent(): void {

		$this->assertEquals(TRUE, DashboardSetting::isEnabled($this->eStructure), "l'écran est ouvert");
		$this->assertEquals(FALSE, DashboardSetting::isLanding($this->eStructure), "et le point d'arrivée fermé : c'est la scission");

		$this->openLanding();

		$this->assertEquals(TRUE, DashboardSetting::isLanding($this->eStructure), "le second drapeau s'ouvre par sa closure");

	}

	/**
	 * L'URL dédiée répond, et c'est tout l'objet de la scission : les deux routes que le premier drapeau
	 * garde passent alors que rien ne mène plus à l'accueil.
	 *
	 * La route d'un bloc n'est pas reprise ici : `Flag.t.php` l'éprouve déjà des deux côtés du premier
	 * drapeau, et elle tombe sur le refus suivant, le 404 d'un nom inventé, ce qui ne dirait rien du
	 * second.
	 */
	public function testTheDedicatedAddressStillAnswers(): void {

		$routes = [
			['dashboard/dashboard', '/ferme/{id}/accueil'],
			['dashboard/preference', '/ferme/{id}/accueil/personnaliser'],
		];

		foreach($routes as [$request, $name]) {

			$action = $this->executeRoute($request, $name, []);

			$this->assertEquals(\ViewAction::class, get_debug_type($action), $name.' répond alors que le point d\'arrivée est fermé');

		}

	}

	/**
	 * La section d'arrivée redevient la section par défaut, qui est ce que le produit faisait avant l'accueil.
	 *
	 * L'étalon n'est pas une chaîne écrite à la main mais `getDefaultSection()`, que le drapeau n'a jamais
	 * lu : poser `'production'` ici ferait rougir le fichier le jour où le décor change de droits, pour
	 * une raison qui n'aurait rien à voir avec le drapeau.
	 */
	public function testTheAccessSectionFallsBackToTheDefault(): void {

		$section = $this->eStructure->getDefaultSection();

		$this->assertEquals($section, $this->eStructure->getAccessSection(), "point d'arrivée fermé, l'entrée est la section par défaut");
		$this->assertEquals($this->eStructure->getUrl($section), $this->eStructure->getAccessUrl(), "et l'accès mène à la section par défaut");

		$this->openLanding();

		$this->assertEquals('dashboard', $this->eStructure->getAccessSection(), "ouvert, l'accueil prend l'entrée");
		$this->assertEquals('/ferme/'.self::STRUCTURE.'/accueil', $this->eStructure->getAccessUrl(), "et l'accès mène à l'accueil de la structure");

	}

	/** Le menu de compte reprend sa cascade, celle que `Root.php` calculait avant l'accueil. */
	public function testTheMenuSectionFallsBackToItsCascade(): void {

		$section = $this->eStructure->getMenuSection(NULL);

		$this->assertEquals(TRUE, $section !== 'dashboard', "point d'arrivée fermé, le menu ne mène pas à l'accueil mais à ".var_export($section, TRUE));
		$this->assertEquals(TRUE, $section !== NULL, "et il mène quelque part : la cascade du menu a repris");

		$this->openLanding();

		$this->assertEquals('dashboard', $this->eStructure->getMenuSection(NULL), "ouvert, le menu mène à l'accueil");

	}

	/**
	 * L'accueil de la structure ne s'offre jamais dans le menu de compte : point d'arrivée fermé il n'est
	 * offert nulle part, ouvert c'est l'entrée « Tableau de bord » du groupe que le jeton Bureau ouvre, dans
	 * la colonne comme dans la barre du téléphone : le jeton ne mène plus nulle part de lui-même (L73).
	 * Il se lit donc sur le balisage que le gabarit rend, et non sur une condition recopiée.
	 *
	 * La marque est l'**adresse** de l'accueil et non son libellé : un libellé traduit cesserait de
	 * correspondre sans que rien ne soit cassé, et aucune autre entrée du menu ne porte cette adresse.
	 */
	public function testTheAccountMenuDropsTheStructureHome(): void {

		$url = DashboardUi::url($this->eStructure);

		$this->assertEquals(FALSE, str_contains($this->getUserMenu(), $url), "point d'arrivée fermé, le menu de compte n'offre pas l'accueil de la structure");

		$this->openLanding();

		$this->assertEquals(FALSE, str_contains($this->getUserMenu(), $url), "ouvert, il ne l'offre pas davantage");
		$this->assertEquals(0, substr_count($this->invokeTemplate($this->eStructure, 'getStructureSections'), 'href="'.$url.'"'), "le jeton Bureau de la colonne ne l'offre pas de lui-même");
		$this->assertEquals(1, substr_count($this->invokeTemplate($this->eStructure, 'getStructureNav'), 'href="'.$url.'"'), "c'est le groupe Bureau qui l'offre, et le contrôle ci-dessus sait donc voir l'adresse");
		$this->assertEquals(FALSE, str_contains($this->getBar($this->eStructure), 'href="'.$url.'"'), "la barre du téléphone ouvre la feuille du Bureau, dont l'entrée « Tableau de bord » mène à l'accueil");

	}

	/**
	 * La barre du téléphone est posée pour toute structure, point d'arrivée ouvert ou non, AMAP ou
	 * producteur sans Pilotage (décidé le 2026-10-11, « ouvre le nouveau menu mobile pour
	 * tout le monde »). Seul son onglet Bureau suit le point d'arrivée, parce qu'il est ce qui y mène.
	 *
	 * Aucune structure n'est sans Pilotage aujourd'hui, `hasPilotage()` rendant une constante : le cas
	 * se construit par une classe anonyme, comme dans `Flag.t.php`.
	 */
	public function testTheBarIsForEveryStructure(): void {

		$this->assertEquals(TRUE, str_contains($this->getNav($this->eStructure), '<div class="structure-bar">'), "point d'arrivée fermé, la barre est posée");
		$this->assertEquals(FALSE, str_contains($this->getBar($this->eStructure), 'structure-nav-section-home'), "point d'arrivée fermé, la barre n'a pas d'onglet Bureau");

		$this->openLanding();

		$this->assertEquals(TRUE, str_contains($this->getBar($this->eStructure), 'structure-nav-section-home'), "ouvert, la barre a l'onglet Bureau : le contrôle ci-dessus sait le voir");

		$eStructureWithoutPilotage = new class($this->eStructure->getArrayCopy()) extends \structure\Structure {

			public function hasPilotage(): bool {
				return FALSE;
			}

		};

		$eStructureAmap = new \structure\Structure(['type' => \structure\Structure::AMAP] + $this->eStructure->getArrayCopy());

		foreach([
			'sans Pilotage' => $eStructureWithoutPilotage,
			'une AMAP' => $eStructureAmap,
		] as $label => $eStructure) {

			$this->assertEquals(FALSE, DashboardSetting::isLanding($eStructure), $label.", la structure n'arrive pas sur l'accueil");
			$this->assertEquals(TRUE, str_contains($this->getNav($eStructure), '<div class="structure-bar">'), $label.", la barre est posée");
			$this->assertEquals(FALSE, str_contains($this->getBar($eStructure), 'structure-nav-section-home'), $label.", la barre n'a pas d'onglet Bureau");

		}

	}

	/**
	 * `/` rend toujours la liste des structures, point d'arrivée ouvert ou fermé (« laisser la page
	 * d'accueil comme actuellement en prod », décidé le 2026-10-06) : le tableau de bord ne
	 * s'atteint que par son adresse et par l'onglet Bureau. Lu sur la vue que la route choisit, par réflexion,
	 * `ViewAction::$path` n'ayant pas d'accesseur.
	 */
	public function testTheIndexRendersTheUpstreamScreen(): void {

		$this->assertEquals(':logged', $this->getIndexView(), "point d'arrivée fermé, / rend la liste des structures");

		$this->openLanding();

		$this->assertEquals(':logged', $this->getIndexView(), "ouvert, / rend toujours la liste des structures");

	}

	/**
	 * Le second drapeau ne s'ouvre jamais plus largement que le premier, et c'est `isLanding()` qui le
	 * tient, pas les valeurs qu'on donne aux closures : la constante du point d'arrivée se pose dans le
	 * `secret.c.php` d'un poste, celle de l'écran dans `ouvretaferme/Lime.s.php`, et rien n'oblige à
	 * les faire voyager ensemble. Sans la borne, la structure mènerait vers une adresse que la route
	 * refuse, et le membre n'aurait aucun écran où atterrir.
	 */
	public function testTheLandingNeverOutgrowsTheScreen(): void {

		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => FALSE;

		$this->openLanding();

		$this->assertEquals(FALSE, DashboardSetting::isLanding($this->eStructure), "le point d'arrivée ouvert sur un écran fermé reste fermé");
		$this->assertTrue($this->eStructure->getAccessUrl() !== '/ferme/'.self::STRUCTURE.'/accueil', "et la structure ne mène pas à l'accueil");

		$action = $this->executeRoute('dashboard/dashboard', '/ferme/{id}/accueil', []);

		$this->assertEquals(\NotExpectedAction::class, get_debug_type($action), "l'adresse qu'elle aurait ouverte se refuse : c'est ce que la borne évite");

	}

	private function closeLanding(): void {

		DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

	}

	private function openLanding(): void {

		DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	/**
	 * Le menu de compte rendu par le gabarit d'une structure, seul cas où il change avec le point
	 * d'arrivée (`$this instanceof StructureTemplate`).
	 *
	 * `nStructureUser` vaut 1 pour que le menu s'arrête aux entrées du haut : la liste des structures du
	 * membre rendrait un logo par structure, qui ne dit rien de ce qu'on mesure ici.
	 */
	private function getUserMenu(): string {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->eUserOnline['eUserAvatar'] = \user\UserAvatarLib::getByUser($data->eUserOnline);
		$data->eStructure = $this->eStructure;
		$data->ccStructureUser = new \Collection();
		$data->nStructureUser = 1;
		$data->hasLearning = FALSE;

		$template = new \StructureTemplate();
		$template->setData($data);

		$method = new \ReflectionMethod(\RootTemplate::class, 'getUserMenu');

		return $method->invoke($template, $data);

	}

	/** La barre rendue sur la même donnée que le menu de compte. */
	private function getBar(\structure\Structure $eStructure): string {
		return $this->invokeTemplate($eStructure, 'getBar');
	}

	/** La barre du haut du gabarit, qui pose la barre du téléphone à sa suite. */
	private function getNav(\structure\Structure $eStructure): string {
		return $this->invokeTemplate($eStructure, 'getNav');
	}

	private function invokeTemplate(\structure\Structure $eStructure, string $method): string {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->eUserOnline['eUserAvatar'] = \user\UserAvatarLib::getByUser($data->eUserOnline);
		$data->eStructure = $eStructure;
		$data->ccStructureUser = new \Collection();
		$data->nStructureUser = 1;
		$data->hasLearning = FALSE;
		$data->notificationsUnseen = [];
		$data->isLogged = TRUE;

		$template = new \StructureTemplate();
		$template->setData($data);

		return new \ReflectionMethod(\StructureTemplate::class, $method)->invoke($template);

	}

	/** La vue que la route de `/` choisit, `ViewAction::$path` n'ayant pas d'accesseur. */
	private function getIndexView(): ?string {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->ccStructureUser = \structure\StructureLib::getOnline();
		$data->nStructureUser = $data->ccStructureUser->countByDepth();

		$action = $this->executeRoute('main/index', 'index', [], withStructure: FALSE, data: $data);

		return new \ReflectionProperty(\ViewAction::class, 'path')->getValue($action);

	}

	/**
	 * Une route jouée par le routeur, et l'action qu'elle lève rendue à l'appelant. Copie du harnais de
	 * `Flag.t.php`, à deux paramètres près : la route de `/` ne porte pas d'identifiant de structure et
	 * demande un `$data` garni.
	 *
	 * La route est **nommée** (son chemin et le fichier de page qui la porte, tels que
	 * `ouvretaferme/route.c.php` les apparie) au lieu d'être cherchée depuis une URL : `Route::$routes`
	 * est garni par la configuration d'une requête HTTP, que le lanceur de tests ne joue pas.
	 */
	private function executeRoute(string $request, string $name, array $arguments, bool $withStructure = TRUE, ?\stdClass $data = NULL): ?\Action {

		if($data === NULL) {
			$data = new \stdClass();
			$data->eUserOnline = \user\ConnectionLib::getOnline();
			$data->hasAvatar = \map\SceneLib::isOpenByStructures(\structure\StructureLib::getOnline());
		}

		if($withStructure) {
			$arguments += ['id' => $this->eStructure['id']];
		}

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];

		try {
			\Page::execute([[$request, $name, $arguments, NULL]], $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * L'administrateur du décor, un membre réel de la structure de référence : les deux routes valident
	 * `canRead()` sur la structure, et la section d'arrivée se calcule sur un rôle. Un visiteur rendrait
	 * le fichier vert sans rien prouver.
	 *
	 * Il est rechargé sur `User::getSelection()`, qui est ce que le produit pose en ligne
	 * (`ConnectionLib::getFromSession()`) : la route de `/` lit `invoiceCountry` sur l'utilisateur, que la
	 * sélection d'un membre ne porte pas.
	 */
	private function getAdmin(): \user\User {

		foreach(\structure\MemberLib::getByStructure($this->eStructure, index: NULL) as $eMember) {

			if($eMember['role']->notEmpty() and $eMember['role']['admin']) {

				return \user\User::model()
					->select(\user\User::getSelection())
					->whereId($eMember['user'])
					->get();

			}

		}

		throw new \Exception('La structure #'.self::STRUCTURE.' n\'a pas de membre administrateur : monte le décor par dev/test package=structure file=FixtureAi');

	}

	private static function getMemberCache(): \ReflectionProperty {

		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');

	}


	/** La valeur de `FEATURE_DASHBOARD_LANDING` après le chargement de `Dashboard.s.php` derrière `$secret`. */
	private static function loadSetting(string $secret): mixed {

		$code = '<?php class Settings {} '.$secret.' require '.var_export(\Package::getPath('dashboard').'/Dashboard.s.php', TRUE).'; echo json_encode(FEATURE_DASHBOARD_LANDING);';

		// Le script part sur l'entrée standard, comme dans `base/block/test/BlockReach.t.php` : la
		// commande reste faite de constantes.
		$process = proc_open([PHP_BINARY], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		fwrite($pipes[0], $code);
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		proc_close($process);

		if($error !== '') {
			throw new \Exception($error);
		}

		return json_decode($output, TRUE);

	}

}
?>
