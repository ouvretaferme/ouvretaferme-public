<?php
namespace planning;

/**
 * La page « Personnaliser le planning » (R-Retours-0410-soir, A) et ses deux réglages, préférences du
 * membre écrites par `taskWeek:doDensity` et `taskWeek:doColor` : chacun n'écrit que la sienne, une
 * densité inconnue est refusée avec son message, et la réponse pose sur la page ce que le rechargement
 * montre. Le liseré de la couleur de l'action se pose aussi sur les blocs de « En retard », dont la
 * sélection est propre (`TaskLateLib`).
 *
 * L'écran après chaque réponse se compare au rechargement dans `FingerprintTest` (`testTaskDensity`,
 * `testTaskColor`).
 *
 * Lancement : dev/test package=planning file=TaskCustomize
 */
class TaskCustomizeTest extends HarvestFixtureTest {

	/**
	 * La structure dont l'utilisateur du décor n'est pas membre : un réglage posté sur elle est refusé.
	 */
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	/**
	 * Chacun écrit sa densité et sa couleur, jamais celles d'un autre membre de la structure : la page
	 * écrit sur le membre connecté, quelle que soit la structure postée, et une structure dont on n'est pas
	 * membre est refusée avant toute écriture.
	 */
	public function testWritesOwnMember(): void {

		$eMemberSelf = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		\user\ConnectionLib::setOnline($this->getMemberPlanning());
		\structure\MemberLib::clearCache();

		$eMemberOther = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		if($this->assertTrue($eMemberOther->notEmpty() and $eMemberOther['id'] !== $eMemberSelf['id'], 'Bruno n\'est pas un autre membre de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[, , $action] = $this->callCustomize('doDensity', ['viewPlanningDensity' => \structure\Member::SPACIOUS]);
		$this->assertTrue($action instanceof \ViewAction, 'Bruno n\'écrit pas sa densité : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callCustomize('doColor', ['viewPlanningColor' => '1']);
		$this->assertTrue($action instanceof \ViewAction, 'Bruno n\'écrit pas sa couleur : '.implode(', ', $this->getFails($action)));

		$this->assertEquals(['viewPlanningDensity' => \structure\Member::SPACIOUS, 'viewPlanningColor' => TRUE], $this->getStored($eMemberOther), 'les réglages de Bruno ne sont pas écrits');
		$this->assertEquals(['viewPlanningDensity' => \structure\Member::NORMAL, 'viewPlanningColor' => FALSE], $this->getStored($eMemberSelf), 'les réglages de Bruno ont changé ceux d\'un autre membre');

		\user\ConnectionLib::setOnline($this->getMember());
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

		foreach(['doDensity' => ['viewPlanningDensity' => \structure\Member::DENSE], 'doColor' => ['viewPlanningColor' => '1']] as $route => $input) {

			$action = $this->executePage('planning/taskWeek:'.$route, $input + [
				'id' => self::STRUCTURE_OTHER,
				'week' => \Clock::week(),
			], 'POST');

			$this->assertTrue($action instanceof \NotAllowedAction, $route.' sur une autre structure n\'est pas refusé par `canPlanningLimited`');

		}

		$this->assertEquals(['viewPlanningDensity' => \structure\Member::NORMAL, 'viewPlanningColor' => FALSE], $this->getStored($eMemberSelf), 'un réglage refusé a écrit');

	}

	/**
	 * Une densité hors de l'enum est refusée par le schéma, sans rien écrire, et le refus a son message.
	 */
	public function testDensityRefused(): void {

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		[, , $action] = $this->callCustomize('doDensity', ['viewPlanningDensity' => 'compact']);

		$this->assertEquals(['Member::viewPlanningDensity.check'], $this->getFails($action), 'la densité « compact » n\'est pas refusée par le schéma');
		$this->assertTrue(\structure\AlertUi::getError('Member::viewPlanningDensity.check', []) !== NULL, 'le refus de la densité n\'a pas de message');
		$this->assertEquals(\structure\Member::NORMAL, $this->getStored($eMember)['viewPlanningDensity'], 'la densité refusée a été écrite');

	}

	/**
	 * Les ventes à venir de l'accueil (R-Retours-1210, V35b) : une fenêtre de `PlanningSetting::SALES_DAYS`
	 * s'écrit, redessine les réglages et oublie le bloc de l'accueil gardé pour ce membre ; une fenêtre
	 * hors de la liste, que le schéma admet, est refusée avec son message sans rien écrire ni oublier.
	 */
	public function testSalesDays(): void {

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();
		$key = \dashboard\DashboardLib::getCacheKey('planning-sales', $this->eStructure, \user\ConnectionLib::getOnline());
		$sales = fn(): int => (int)\structure\Member::model()->select('viewPlanningSalesDays')->whereId($eMember['id'])->getValue('viewPlanningSalesDays');

		// La clé se pose par le chemin de l'accueil, la route différée d'un bloc lourd, et non à la main :
		// c'est la clé qu'il écrit que l'oubli doit atteindre. Le bloc ne paraît que sur une saison
		// planifiée, d'où la culture, et la mémoire de cette question vidée autour d'elle : elle survivrait
		// au retour arrière de la transaction.
		\Cache::redis()->delete($key);
		$this->getCultivation();
		new \ReflectionProperty(\structure\Structure::class, 'seasonPlanned')->setValue(NULL, []);

		if($this->assertDecorPresent($this->eStructure->acceptSeasonPlanned() and $this->eStructure->canSelling(), 'la structure '.static::STRUCTURE.' montre le bloc des ventes à venir') === FALSE) {
			return;
		}

		$this->executePage('ferme/'.$this->eStructure['id'].'/accueil/bloc/planning-sales', [], 'GET');
		new \ReflectionProperty(\structure\Structure::class, 'seasonPlanned')->setValue(NULL, []);
		$this->assertTrue(\Cache::redis()->get($key) !== FALSE, 'l\'accueil ne garde pas le bloc sous la clé que l\'oubli reconstruit');

		[, , $action] = $this->callCustomize('doSalesDays', ['viewPlanningSalesDays' => '6']);

		$this->assertEquals(['Member::viewPlanningSalesDays.days'], $this->getFails($action), 'une fenêtre de 6 jours n\'est pas refusée');
		$this->assertTrue(\structure\AlertUi::getError('Member::viewPlanningSalesDays.days', []) !== NULL, 'le refus de la fenêtre n\'a pas de message');
		$this->assertEquals(7, $sales(), 'la fenêtre refusée a été écrite');
		$this->assertTrue(\Cache::redis()->get($key) !== FALSE, 'la fenêtre refusée a oublié le bloc de l\'accueil');

		\structure\MemberLib::clearCache();

		[, $instructions, $action] = $this->callCustomize('doSalesDays', ['viewPlanningSalesDays' => '3']);

		$this->assertTrue($action instanceof \ViewAction, 'la fenêtre de 3 jours ne s\'écrit pas : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(3, $sales(), 'la fenêtre de 3 jours n\'est pas en base');
		$this->assertTrue($this->hasInstruction($instructions, '#planning-customize-tools', 'outerHtml', []), 'les réglages ne sont pas redessinés : '.json_encode($instructions));
		$this->assertFalse(\Cache::redis()->get($key), 'l\'accueil garde le bloc de l\'ancienne fenêtre');

	}

	/**
	 * La réponse redessine les réglages, et la couleur bascule la classe de la page, qui montre la pastille
	 * des actions. La Semaine rechargée relit la densité dans la largeur de ses piles.
	 */
	public function testResponse(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[, $instructions] = $this->callCustomize('doDensity', ['viewPlanningDensity' => \structure\Member::SPACIOUS]);

		$this->assertTrue($this->hasInstruction($instructions, '#planning-customize-tools', 'outerHtml', []), 'les réglages ne sont pas redessinés : '.json_encode($instructions));
		$this->assertFalse(str_contains(json_encode($instructions), '#planning-week'), 'la réponse vise encore la Semaine, absente de la page : '.json_encode($instructions));

		[, $instructions] = $this->callCustomize('doColor', ['viewPlanningColor' => '1']);

		$this->assertTrue($this->hasInstruction($instructions, '#planning-customize', 'addClass', ['planning-color']), 'la couleur n\'est pas posée sur la page : '.json_encode($instructions));

		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

		[$document] = $this->renderTasks();

		$this->assertEquals((string)TaskWeekUi::PILE_WIDTHS[\structure\Member::SPACIOUS], $document->getElementById('planning-week-board')->getAttribute('data-pile-width'));

	}

	/**
	 * La flèche de retour ramène à la semaine d'où l'on vient (A2), et le libellé de la couleur bascule
	 * l'interrupteur.
	 */
	public function testPage(): void {

		$week = toWeek(\Clock::date().' + 14 DAYS');

		[$document, , $mainTitle] = $this->renderCustomize($week);

		$this->assertTrue(str_contains($mainTitle, '<a href="'.TaskWeekUi::url($this->eStructure, $week).'" class="util-page-header-back"'), 'la flèche de retour ne ramène pas à la semaine d\'où l\'on vient : '.$mainTitle);

		$color = $document->querySelector('#planning-customize-tools a.util-filter-label');
		$switch = $document->querySelector('#planning-customize-tools a.field-switch');
		$this->assertTrue($color !== NULL and $switch !== NULL and $color->getAttribute('data-ajax') === $switch->getAttribute('data-ajax') and $color->getAttribute('post-view-planning-color') === $switch->getAttribute('post-view-planning-color'), 'le libellé de la couleur ne bascule pas l\'interrupteur');

	}

	/**
	 * Ouverte depuis le menu d'un autre écran (retour du 05/10), la page ramène à cet écran, à la même
	 * semaine, par l'origine que porte son adresse. Une origine inconnue
	 * ramène à la Semaine.
	 */
	public function testOrigin(): void {

		$week = toWeek(\Clock::date().' + 14 DAYS');

		foreach(TabUi::SCREENS as $screen) {

			[, $data, $mainTitle] = $this->renderCustomize($week, ['origin' => $screen]);

			$this->assertEquals($screen, $data->origin, 'l\'origine '.$screen.' n\'est pas retenue');
			$this->assertTrue(str_contains($mainTitle, '<a href="'.TabUi::getUrl($this->eStructure, $screen, $week).'" class="util-page-header-back"'), 'la flèche de retour ne ramène pas à l\'écran '.$screen.' : '.$mainTitle);

		}

		$this->assertEquals('/planning-v2/jour/'.$week, substr(TabUi::getUrl($this->eStructure, 'jour', $week), -strlen('/planning-v2/jour/'.$week)), 'le Jour ne ramène pas à sa page');
		$this->assertTrue(str_ends_with(TabUi::getUrl($this->eStructure, 'equipe', $week), '?user='.TimeWeekLib::TEAM), 'la fiche de l\'équipe ne ramène pas à elle-même');

		[, $data, $mainTitle] = $this->renderCustomize($week, ['origin' => 'inconnu']);

		$this->assertEquals('semaine', $data->origin, 'une origine inconnue est retenue');
		$this->assertTrue(str_contains($mainTitle, '<a href="'.TaskWeekUi::url($this->eStructure, $week).'" class="util-page-header-back"'), 'une origine inconnue ne ramène pas à la Semaine : '.$mainTitle);

	}

	/**
	 * Chaque écran choisit son onglet, le Jour et l'Année celui des Interventions, la fiche de l'équipe celui
	 * de l'Équipe ; et chacun propose la personnalisation au membre connecté. L'onglet choisi se reconnaît à
	 * l'écran où il mène, non à son libellé.
	 */
	public function testScreenTabs(): void {

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		if($this->assertDecorPresent($eMember->notEmpty(), 'la personne connectée est membre de la structure du décor') === FALSE) {
			return;
		}

		$time = $this->eStructure->hasTime() ? 'temps' : NULL;

		$expected = [
			'semaine' => 'semaine',
			'jour' => 'semaine',
			'annee' => 'semaine',
			'recoltes' => 'recoltes',
			'temps' => $time,
			'equipe' => $time,
		];

		foreach($expected as $screen => $page) {

			$tabs = new TabUi()->get($this->eStructure, \Clock::week(), $screen, $eMember);

			preg_match_all('/<a href="([^"]+)" class="tab-item selected">/', $tabs, $selected);

			$this->assertEquals($page === NULL ? [] : [TabUi::getUrl($this->eStructure, $page, \Clock::week())], $selected[1], 'l\'onglet choisi sur l\'écran '.$screen);
			$this->assertTrue(str_contains($tabs, 'href="'.TaskWeekUi::urlCustomize($this->eStructure, \Clock::week(), $screen).'"'), 'la personnalisation manque sur l\'écran '.$screen);

		}

	}

	/**
	 * Un bloc de « En retard » porte la couleur de son action, comme un bloc de la semaine : sa sélection
	 * est propre (`TaskLateLib`) et doit charger `color`.
	 */
	public function testLateBlockAccent(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eAction = \work\ActionLib::getById($this->getAction(ACTION_PLANTATION)['id']);

		// Sans `build()`, qui n'aurait vérifié que le format de la couleur : la valeur est écrite en clair
		// et le test ne poste rien.
		$eAction['color'] = '#EF2929';
		\work\ActionLib::update($eAction, ['color']);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderTasks();

		foreach(['planning-week-block-'.$eAction['id'] => 'de la semaine', 'planning-week-'.TaskLateLib::LATE.'-block-'.$eAction['id'] => 'de « En retard »'] as $id => $case) {

			$block = $document->getElementById($id);

			if($this->assertTrue($block !== NULL, 'le bloc '.$case.' n\'est pas à l\'écran') === FALSE) {
				continue;
			}

			$this->assertEquals('--accent: #EF2929', $block->getAttribute('style'), 'le bloc '.$case.' ne porte pas la couleur de son action');

		}

	}

	private function callCustomize(string $route, array $input): array {

		$action = $this->executePage('planning/taskWeek:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView($route, $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Une instruction sur `$selector` qui appelle `$method` avec ces premiers arguments.
	 */
	private function hasInstruction(array $instructions, string $selector, string $method, array $arguments): bool {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$methodCalled, $argumentsCalled]) {

					if($methodCalled === $method and array_slice($argumentsCalled, 0, count($arguments)) === $arguments) {
						return TRUE;
					}

				}

			}

		}

		return FALSE;

	}

	/**
	 * Les deux réglages lus en base, et non sur le membre en cache, que `build()` a déjà touché.
	 */
	private function getStored(\structure\Member $eMember): array {

		return \structure\Member::model()
			->select(['viewPlanningDensity', 'viewPlanningColor'])
			->whereId($eMember['id'])
			->get()
			->extracts(['viewPlanningDensity', 'viewPlanningColor']);

	}

}
?>
