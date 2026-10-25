<?php
namespace dashboard;

/**
 * Le sélecteur de structure (lot 7 N3, arbitrage S3 du 2026-10-01) : depuis L23 (décision du
 * 2026-10-11, « tu peux garder ce bloc et le répercuter sur la nav actuelle pour changer de ferme »),
 * c'est le nom de la structure dans la barre du haut qui ouvre la liste des structures du compte, dans
 * toute structure depuis L28. Le menu de compte d'une structure ne porte donc plus cette liste.
 *
 * Cinq promesses, chacune sur un compte du décor dont le nombre de structures la met en jeu :
 * - la page ne porte qu'une coquille, et le menu de compte ne porte pas la liste ;
 * - un compte d'une seule structure n'a rien à ouvrir ;
 * - avec plusieurs structures, le nom ouvre une seule liste, rendue une fois ;
 * - hors du point d'arrivée, le nom ouvre la même liste ;
 * - depuis 7A (B2, décision du 2026-10-03), la page ne rend plus qu'une coquille vide, que
 *   `structure/selectorAi` remplit à l'ouverture : son contenu, recherche comprise, est éprouvé par
 *   `structure/test/SelectorAi.t.php`.
 *
 * Aucune écriture : les comptes et leurs structures viennent du décor (`FixtureAiLib`), le compte en
 * ligne et le point d'arrivée, ouvert ici pour que le menu de compte soit celui de l'accueil, sont
 * rendus à la fin.
 *
 * Lancement : dev/test package=dashboard file=Selector
 */
class SelectorTest extends \Test {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	/** Une seule structure. */
	const SINGLE = 'decor.f1.planning@example.org';

	/** Quelques structures : le sélecteur sans recherche. */
	const FEW = 'decor.f1.admin@example.org';

	/** Treize structures : le sélecteur avec recherche. */
	const MANY = 'decor.c.admin@example.org';

	private \user\User $eUserInitial;
	private \Closure $enabledInitial;
	private \Closure $landingInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->enabledInitial = DashboardSetting::$enabled;
		$this->landingInitial = DashboardSetting::$landing;

		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		DashboardSetting::$landing = DashboardSetting::$enabled;

	}

	public function finalize(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);
		DashboardSetting::$enabled = $this->enabledInitial;
		DashboardSetting::$landing = $this->landingInitial;

	}

	/**
	 * Le sélecteur n'est plus qu'une coquille vide, quel que soit le nombre de structures, et le menu de
	 * compte ne porte pas la liste.
	 */
	public function testTheSelectorIsAnEmptyShell(): void {

		foreach([self::FEW, self::MANY] as $email) {

			$template = $this->getTemplate($email);
			$links = $this->invoke($template, 'getStructureLinks');
			$selector = $this->invoke($template, 'getSelector');
			$menu = $this->invoke($template, 'getUserMenu', $template->getData());

			$this->assertEquals(1, preg_match('/^<div [^>]*data-dropdown-id="structure-selector-list"><\/div>$/', $selector), $email." : la page ne rend que la coquille");
			$this->assertEquals(FALSE, str_contains($links, 'selected'), $email." : aucune structure n'est marquée dans la liste");
			$this->assertEquals(FALSE, str_contains($menu, $links), $email." : le menu de compte ne porte pas la liste");

		}

	}

	/** Une seule structure : ni liste ni déclencheur. */
	public function testASingleStructureOpensNothing(): void {

		$template = $this->getTemplate(self::SINGLE);

		$this->assertEquals(1, $template->getData()->nStructureUser, "le compte n'a qu'une structure");

		$nav = $this->invoke($template, 'getNav');

		$this->assertEquals(0, substr_count($nav, 'structure-selector'), "rien ne s'ouvre");

	}

	/** Plusieurs structures : le nom ouvre la liste de la structure ouverte, rendue une seule fois dans la page. */
	public function testTheNameOpensTheList(): void {

		$template = $this->getTemplate(self::FEW);

		$this->assertTrue($template->getData()->nStructureUser > 1, "le compte a plusieurs structures");

		$nav = $this->invoke($template, 'getNav');
		$trigger = 'data-structure-selector="'.\structure\SelectorAiUi::url($template->getData()->eStructure).'" data-structure-sections="'.implode(' ', DashboardSetting::SECTIONS).'" data-dropdown-id="structure-selector"';

		$this->assertEquals(1, substr_count($nav, $trigger), "le nom ouvre la liste");
		$this->assertEquals(1, substr_count($nav, 'data-dropdown-id="structure-selector"'), "un seul déclencheur");
		$this->assertEquals(1, substr_count($nav, 'data-dropdown-id="structure-selector-list"'), "une seule liste");

	}

	/** Hors du point d'arrivée, le nom ouvre la même liste : la navigation est celle de toute structure. */
	public function testOutsideTheLandingTheNameOpensTheList(): void {

		DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

		$nav = $this->invoke($this->getTemplate(self::FEW), 'getNav');

		DashboardSetting::$landing = DashboardSetting::$enabled;

		$this->assertEquals(1, substr_count($nav, 'data-dropdown-id="structure-selector"'), "le nom ouvre la liste");
		$this->assertEquals(1, substr_count($nav, 'data-dropdown-id="structure-selector-list"'), "une seule liste");

	}

	/**
	 * Un gabarit garni de ce que `Page.l.php` charge pour le compte, sur la structure F1, ou sur la
	 * première structure du compte quand il n'est pas membre de F1 : le sélecteur ne marque que la
	 * structure ouverte, qui doit donc être l'une des siennes.
	 */
	private function getTemplate(string $email): \StructureTemplate {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

		if($eUser->empty()) {
			throw new \Exception('Compte '.$email.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		\user\ConnectionLib::setOnline($eUser);

		$data = new \stdClass();
		$data->isLogged = TRUE;
		$data->eUserOnline = $eUser;
		$data->eUserOnline['eUserAvatar'] = \user\UserAvatarLib::getByUser($eUser);
		$data->ccStructureUser = \structure\StructureLib::getByUser($eUser, withFeature: TRUE, index: ['type', 'id']);
		$data->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId($this->getOpenStructure($data->ccStructureUser))
			->get();
		$data->nStructureUser = $data->ccStructureUser->countByDepth();
		$data->hasLearning = FALSE;
		$data->notificationsUnseen = [];

		$template = new \StructureTemplate();
		$template->setData($data);

		return $template;

	}

	private function getOpenStructure(\Collection $ccStructureUser): int {

		$structures = [];

		foreach($ccStructureUser as $cStructure) {
			foreach($cStructure as $eStructure) {
				if($eStructure->isCommunity() === FALSE) {
					$structures[] = $eStructure['id'];
				}
			}
		}

		if(in_array(self::STRUCTURE, $structures, TRUE)) {
			return self::STRUCTURE;
		} else if($structures !== []) {
			return $structures[0];
		} else {
			throw new \Exception('Le compte n\'a aucune structure hors groupe à ouvrir : monte le décor par dev/test package=structure file=FixtureAi');
		}

	}

	/** Les méthodes du gabarit sont `protected`, comme `getUserMenu()` que `Landing.t.php` lit de la même façon. */
	private function invoke(\StructureTemplate $template, string $method, mixed ...$arguments): string {
		return new \ReflectionMethod($template, $method)->invoke($template, ...$arguments);
	}

}
?>
