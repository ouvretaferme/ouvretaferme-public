<?php
namespace structure;

/**
 * La RH et le temps de travail s'allument sous la production, et s'éteignent avec elle sans être réécrits : la
 * page Fonctionnalités, l'état `Structure::acceptHr()`, la journée que la RH ouvre seule, l'astuce de l'équipe, et
 * la période qui garde son contrat quand la RH est éteinte.
 *
 * Lancement : dev/test package=structure file=FeatureHrAi
 */
class FeatureHrAiTest extends \dev\TransactionTest {

	protected Structure $eStructure;

	protected function getTransactionModel(): \ModuleModel {
		return new FeatureModel();
	}

	public function init(): void {

		$this->eStructure = StructureLib::getById(FixtureAiLib::F1);

	}

	/**
	 * Le rollback ne défait pas la mémoïsation de `conf()`, qui garde ce qu'elle a lu dans la transaction :
	 * elle s'oublie après chaque écriture et à la sortie.
	 */
	public function tearDown(): void {

		parent::tearDown();

		FeatureLib::forget($this->eStructure);
		FeatureLib::forget(StructureLib::getById(FixtureAiLib::G1));

	}

	protected function setFeature(Structure $eStructure, string $feature, bool $value): void {

		FeatureLib::updateFeature($eStructure, $feature, $value);
		FeatureLib::forget($eStructure);

	}

	/**
	 * Construit `$property` sur la ligne de `Feature` ou de `Configuration` de la structure relue entière, comme
	 * la page, et rend les refus.
	 */
	protected function build(Structure $eStructure, string $module, string $property, bool $value): array {

		$eStructure = StructureLib::getById($eStructure['id']);

		$e = match($module) {
			'Feature' => FeatureLib::readByStructure($eStructure),
			'Configuration' => ConfigurationLib::readByStructure($eStructure)
		};

		$e['structure'] = $eStructure;

		$fw = new \FailWatch();

		$e->build([$property], [$property => $value ? '1' : '0']);

		return array_column($fw->get(), 1);

	}

	public function testHrAndTimeNeedProduction(): void {

		$this->assertEquals([], $this->build($this->eStructure, 'Feature', 'hasHr', TRUE), 'la RH ne s\'allume pas sous la production');
		$this->assertEquals([], $this->build($this->eStructure, 'Configuration', 'time', TRUE), 'le temps de travail ne s\'allume pas sous la production');

		$this->setFeature($this->eStructure, 'production', FALSE);

		$this->assertEquals(['Feature::hasHr.production'], $this->build($this->eStructure, 'Feature', 'hasHr', TRUE), 'la RH s\'allume production éteinte');
		$this->assertEquals(['Configuration::time.production'], $this->build($this->eStructure, 'Configuration', 'time', TRUE), 'le temps de travail s\'allume production éteinte');

		$this->assertEquals([], $this->build($this->eStructure, 'Feature', 'hasHr', FALSE), 'la RH ne s\'éteint plus production éteinte');
		$this->assertEquals([], $this->build($this->eStructure, 'Configuration', 'time', FALSE), 'le temps de travail ne s\'éteint plus production éteinte');

	}

	/**
	 * Un groupe porte `production` vrai par défaut sans avoir de planning : rien de ce qui en dépend ne s'y allume.
	 */
	public function testOnlyAProducerTurnsProductionOn(): void {

		$eCommunity = StructureLib::getById(FixtureAiLib::G1);

		$this->assertTrue($eCommunity->conf(Feature::class)['production'], 'le décor du groupe ne porte pas la production par défaut');

		$this->assertEquals(['Feature::production.producer'], $this->build($eCommunity, 'Feature', 'production', TRUE), 'un groupe allume la production');
		$this->assertEquals(['Feature::hasHr.production'], $this->build($eCommunity, 'Feature', 'hasHr', TRUE), 'un groupe allume la RH');
		$this->assertEquals([], $this->build($eCommunity, 'Feature', 'production', FALSE), 'un groupe n\'éteint plus la production');

		$this->assertEquals([], $this->build($this->eStructure, 'Feature', 'production', TRUE), 'un producteur n\'allume plus la production');

	}

	public function testAcceptHr(): void {

		$this->setFeature($this->eStructure, 'hasHr', FALSE);
		$this->assertFalse($this->eStructure->acceptHr(), 'acceptHr() accepte une RH éteinte');

		$this->setFeature($this->eStructure, 'hasHr', TRUE);
		$this->assertTrue($this->eStructure->acceptHr(), 'acceptHr() refuse une RH et une production allumées');

		$this->setFeature($this->eStructure, 'production', FALSE);
		$this->assertFalse($this->eStructure->acceptHr(), 'acceptHr() accepte une production éteinte');

		$eCommunity = StructureLib::getById(FixtureAiLib::G1);

		$this->setFeature($eCommunity, 'hasHr', TRUE);
		$this->assertFalse($eCommunity->acceptHr(), 'acceptHr() accepte un groupe qui porte le drapeau');

	}

	/**
	 * Éteindre la production ne réécrit ni la RH ni le temps de travail : rallumée, chacun reprend sa valeur.
	 */
	public function testProductionOffKeepsHrAndTime(): void {

		$this->setFeature($this->eStructure, 'hasHr', TRUE);

		$time = ConfigurationLib::readByStructure($this->eStructure)['time'];
		$eFeature = FeatureLib::readByStructure($this->eStructure);

		$this->asAuthor(fn() => $this->assertEquals(\RedirectAction::class, $this->executePage('structure/feature:doUpdateProduction', ['id' => $eFeature['id'], 'production' => '0']), 'la production ne s\'éteint pas'));

		FeatureLib::forget($this->eStructure);

		$this->assertFalse($this->eStructure->conf(Feature::class)['production'], 'la production est restée allumée');
		$this->assertTrue($this->eStructure->conf(Feature::class)['hasHr'], 'la RH a été réécrite');
		$this->assertEquals($time, ConfigurationLib::readByStructure($this->eStructure)['time'], 'le temps de travail a été réécrit');
		$this->assertFalse($this->eStructure->acceptHr(), 'la RH reste ouverte production éteinte');

		$this->asAuthor(fn() => $this->assertEquals(\RedirectAction::class, $this->executePage('structure/feature:doUpdateProduction', ['id' => $eFeature['id'], 'production' => '1']), 'la production ne se rallume pas'));

		FeatureLib::forget($this->eStructure);

		$this->assertTrue($this->eStructure->acceptHr(), 'la RH ne revient pas avec la production');

	}

	/**
	 * L'astuce de la RH se propose dès deux personnes dans l'équipe, RH éteinte, et plus une fois allumée.
	 */
	public function testTipFromTwoTeammates(): void {

		$this->setFeature($this->eStructure, 'hasHr', FALSE);

		$this->assertEquals(1, \hr\TeammateLib::getByStructure($this->eStructure)->count(), 'le décor de F1 n\'a pas une seule personne dans l\'équipe');
		$this->assertEquals(NULL, $this->getTeamTip(), 'astuce proposée à une seule personne');

		$this->createGhost('Seconde');

		$this->assertEquals(2, \hr\TeammateLib::getByStructure($this->eStructure)->count(), 'la seconde personne n\'est pas dans l\'équipe');

		$this->assertEquals('hr-start', $this->getTeamTip(), 'astuce absente à deux personnes');

		$this->setFeature($this->eStructure, 'hasHr', TRUE);

		$this->assertEquals(NULL, $this->getTeamTip(), 'astuce proposée RH allumée');

	}

	/**
	 * RH éteinte, la période se modifie sans son contrat, qui reste en base et revient au rallumage.
	 */
	public function testPresenceKeepsItsContractWhenHrIsOff(): void {

		$eTeammate = $this->createGhost('Contrat', new \hr\Contract([
			'type' => \hr\Contract::CDI,
			'weeklyHours' => 35.0,
			'days' => new \Set(\hr\Contract::MONDAY | \hr\Contract::TUESDAY)
		]));

		$ePresence = $eTeammate['ePresence'];

		$this->setFeature($this->eStructure, 'hasHr', FALSE);

		$this->asAuthor(fn() => $this->assertEquals(\BackAction::class, $this->executePage('hr/presence:doUpdate', ['id' => $ePresence['id'], 'from' => '2026-01-12', 'to' => '']), 'la période ne se modifie pas'));

		$this->assertEquals('2026-01-12', \hr\Presence::model()->select(['from'])->whereId($ePresence['id'])->get()['from'], 'la période n\'a pas été écrite');
		$this->assertEquals(\hr\Contract::CDI, \hr\ContractLib::getByPresence($ePresence)['type'] ?? NULL, 'le contrat a été effacé');

	}

	/**
	 * RH seule, temps de travail éteint : la journée se saisit, le temps d'une intervention non. Sans l'un ni
	 * l'autre, la journée se refuse, par la page comme par l'étape `untracked` qui garde aussi l'API.
	 */
	public function testWorkingTimeUnderHrOnly(): void {

		$this->setClock('2026-03-10 10:00:00');

		$eTeammate = $this->createGhost('Journée');
		$eUser = $eTeammate['user'];

		$this->setFeature($this->eStructure, 'hasHr', TRUE);

		$working = fn() => $this->executePage('planning/timeDay:doWorkingTime', ['id' => $this->eStructure['id'], 'user' => $eUser['id'], 'week' => '2026-W11', 'date' => '2026-03-10', 'time' => '7']);

		$this->withConfiguration(['time' => FALSE, 'timeRange' => FALSE], function() use($working, $eUser) {

			$this->asAuthor(function() use($working, $eUser) {

				$this->assertEquals(\ViewAction::class, $working(), 'la journée ne se saisit pas sous la RH seule');
				$eWorkingTime = \hr\WorkingTimeLib::getByDate($this->eStructure, $eUser, '2026-03-10');

				$this->assertTrue($eWorkingTime->notEmpty(), 'la journée n\'a pas été écrite');
				$this->assertEquals(7.0, $eWorkingTime['time']);

				$this->assertEquals(\NotExpectedAction::class, $this->executePage('planning/timeDay:doTime', ['id' => $this->eStructure['id'], 'user' => $eUser['id'], 'week' => '2026-W11', 'date' => '2026-03-10', 'task' => '0']), 'le temps d\'une intervention se saisit sous la RH seule');

				$this->setFeature($this->eStructure, 'hasHr', FALSE);

				$this->assertEquals(\NotExpectedAction::class, $working(), 'la journée se saisit sans temps de travail ni RH');

			});

			$eWorkingTime = new \hr\WorkingTime([
				'structure' => StructureLib::getById($this->eStructure['id']),
				'user' => $eUser
			]);

			$fw = new \FailWatch();

			$eWorkingTime->build(['date'], ['date' => '2026-03-09']);

			$this->assertEquals(['WorkingTime::date.untracked'], array_column($fw->get(), 1), 'la journée s\'écrit sans temps de travail ni RH');

		});

	}

	/**
	 * La configuration est gardée en cache pour le processus (`Structure::conf()`) : le test la change sur place
	 * et la rend telle quelle, même quand le cas échoue.
	 */
	protected function withConfiguration(array $values, \Closure $test): void {

		$eConfiguration = $this->eStructure->conf();
		$valuesInitial = $eConfiguration->extracts(array_keys($values));

		$eConfiguration->merge($values);

		try {
			$test();
		} finally {
			$eConfiguration->merge($valuesInitial);
		}

	}

	protected function createGhost(string $name, \hr\Contract $eContract = new \hr\Contract()): \hr\Teammate {

		$eUser = MemberLib::getNewGhostUser()->merge([
			'firstName' => 'RH',
			'lastName' => $name
		]);

		// Hors build() : une période ouverte depuis janvier, que le panneau accepterait aussi.
		$eTeammate = new \hr\Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'ePresence' => new \hr\Presence([
				'from' => '2026-01-05',
				'to' => NULL,
				'eContract' => $eContract
			])
		]);

		\hr\TeammateLib::create($eTeammate);

		return $eTeammate;

	}

	protected function getTeamTip(): ?string {

		$data = new \stdClass();

		// Par référence : une fonction fléchée recevrait une copie de `$data`, que la page remplirait en vain.
		$this->asAuthor(function() use(&$data) {
			$this->assertEquals(\ViewAction::class, $this->executePage('ferme/'.$this->eStructure['id'].'/equipe', ['id' => $this->eStructure['id']], 'GET', $data), 'la page de l\'équipe ne s\'affiche pas');

			// L'astuce est choisie une fois par utilisateur : la relire se fait sur un état vierge. Hors de
			// `TipLib`, qui ne sait que changer un statut, jamais l'oublier.
			Tip::model()->whereUser(\user\ConnectionLib::getOnline())->delete();

		});

		return $data->tip ?? NULL;

	}

	protected function asAuthor(\Closure $test): void {

		$eUserOnline = \user\ConnectionLib::getOnline();

		\user\ConnectionLib::setOnline(\user\User::model()->select(\user\User::getSelection())->whereEmail(FixtureAiLib::AUTHOR)->get());
		MemberLib::clearCache();

		try {
			$test();
		} finally {
			\user\ConnectionLib::setOnline($eUserOnline);
			MemberLib::clearCache();
		}

	}

	/**
	 * Exécute la route comme le navigateur et rend la classe de ce qu'elle lève ; sur une `ViewAction`, ce que la
	 * page passe à la vue est rendu dans `$data`.
	 */
	private function executePage(string $path, array $input, string $method = 'POST', ?\stdClass &$data = NULL): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		$input = array_map('strval', $input);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		$_REQUEST = $input;

		if($method === 'GET') {
			$_GET = $input;
		} else {
			$_POST = $input;
		}

		try {
			// Ce que l'application pose sur chaque requête et que les pages lisent.
			$dataPage = new \stdClass();
			$dataPage->eUserOnline = \user\ConnectionLib::getOnline();

			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), $dataPage);
			return 'aucune action';
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return \FailAction::class.' '.(is_string($fail) ? $fail : implode(', ', array_column($fail->format(), 0)));
		} catch(\ViewAction $action) {
			$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
			return \ViewAction::class;
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

}
?>
