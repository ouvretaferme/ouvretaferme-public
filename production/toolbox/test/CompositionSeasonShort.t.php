<?php
namespace toolbox;

/**
 * Une saison réglée à la main ne s'enregistre jamais avec un morceau de moins de trois semaines (r72,
 * correction 3), le seuil des saisons du calendrier type (`\aggregate\CalendarProjectionAiLib::PIECE_MIN`,
 * r54, « vire les semaines isolées »). Cas réel, sur la 7 : Fève `[[4,4],[18,26]]`, une semaine
 * isolée laissée par un clic sur la grille. Le Haricot `[[1,1],[21,52]]`, d'abord rangé avec elle,
 * est une saison qui passe le nouvel an et s'enregistre (`testNewYear()`).
 *
 * - la grille poste une saison avec un morceau court : `doUpdateSeason` la refuse
 *   (`CompositionSpecies::windowWeeks.short`) et rien ne s'écrit ;
 * - le seuil se lit des deux côtés : deux semaines refusées, trois admises ;
 * - une saison qui passe le nouvel an se mesure d'un seul tenant : `[[1, 2], [40, 52]]` est admise ;
 * - deux intervalles qui se touchent se fusionnent avant d'être mesurés : un morceau se juge entier ;
 * - une saison déjà enregistrée avec un morceau court se nettoie : un geste qui garde ce morceau
 *   passe, celui qui le retire aussi, et une fois retiré il ne revient plus ;
 * - « Régler une espèce » renvoie la saison telle qu'il l'a montrée (`getSeasonUpdated()`) : le
 *   morceau court qu'elle porte n'empêche pas d'enregistrer les autres réglages.
 *
 * Les gestes s'éprouvent sur un plan écrit par le départ pour une structure qui n'en a pas, sous
 * un administrateur de cette structure.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionSeasonShort
 */
class CompositionSeasonShortTest extends \dev\TransactionTest {

	const SEASON = 2026;

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return CompositionPlan::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	/**
	 * Le cas de la Fève de la 7, posté par la grille sur une espèce dont la saison ne porte aucun
	 * morceau court : refusé, et la saison reste celle d'avant.
	 */
	public function testRefused(): void {

		$this->runAsManager(function(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies) {

			$this->writeSeason($eCompositionSpecies, [[18, 26]]);

			$action = $this->post($eStructure, $eCompositionSpecies, [[4, 4], [18, 26]]);

			$this->assertEquals(\FailAction::class, $action::class, 'une semaine isolée devant la saison est refusée');
			$this->assertEquals(['CompositionSpecies::windowWeeks.short'], $this->getFails($action), 'par le contrôle des morceaux courts');
			$this->assertTrue(AlertUi::getError('CompositionSpecies::windowWeeks.short') !== NULL, 'avec son message');
			$this->assertEquals([[18, 26]], $this->getSeason($eCompositionPlan, $eCompositionSpecies)['windowWeeks'], 'et rien ne s\'écrit');

			$action = $this->post($eStructure, $eCompositionSpecies, [[1, 1], [14, 40]]);

			$this->assertEquals(['CompositionSpecies::windowWeeks.short'], $this->getFails($action), 'le cas du Chou pé-tsaï de la 7, une semaine 1 isolée d\'une saison qui finit en 40, est refusé de même');

		});

	}

	/**
	 * Une saison qui passe le nouvel an est d'un seul tenant (r72, décision du coordinateur sous
	 * mandat) : le morceau qui touche la semaine 1 se mesure avec celui qui touche la dernière. Le
	 * poireau ou la mâche d'octobre à la mi-janvier, `[[1, 2], [40, 52]]`, s'enregistre, et le Haricot
	 * de la 7, `[[1, 1], [21, 52]]`, devient légitime.
	 */
	public function testNewYear(): void {

		$this->runAsManager(function(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies) {

			$this->writeSeason($eCompositionSpecies, [[18, 26]]);

			$this->assertEquals(52, max(CompositionLib::getSeasonWeeks()), 'la dernière semaine d\'une saison, que les cas suivants touchent');

			$action = $this->post($eStructure, $eCompositionSpecies, [[1, 2], [40, 52]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'deux semaines de janvier qui prolongent une saison finie en 52 sont admises');
			$this->assertEquals([[1, 2], [40, 52]], $this->getSeason($eCompositionPlan, $eCompositionSpecies)['windowWeeks'], 'et s\'écrivent');

			$action = $this->post($eStructure, $eCompositionSpecies, [[1, 1], [21, 52]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'le Haricot de la 7 aussi');

			$action = $this->post($eStructure, $eCompositionSpecies, [[1, 12], [51, 52]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'et deux semaines de décembre devant une saison qui part de la semaine 1');

			$action = $this->post($eStructure, $eCompositionSpecies, [[1, 1], [51, 51]]);
			$this->assertEquals(['CompositionSpecies::windowWeeks.short'], $this->getFails($action), 'mais deux morceaux d\'une semaine qui ne se touchent pas restent courts, 52 manquant');

		});

	}

	/**
	 * Le seuil, de part et d'autre : deux semaines refusées, trois admises. Deux intervalles qui se
	 * touchent ne font qu'un morceau, mesuré après leur fusion.
	 */
	public function testThreshold(): void {

		$this->runAsManager(function(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies) {

			$this->writeSeason($eCompositionSpecies, [[18, 26]]);

			$this->assertEquals(\aggregate\CalendarProjectionAiLib::PIECE_MIN, 3, 'le seuil est celui du calendrier type');

			$action = $this->post($eStructure, $eCompositionSpecies, [[10, 11], [18, 26]]);
			$this->assertEquals(['CompositionSpecies::windowWeeks.short'], $this->getFails($action), 'deux semaines sont refusées');

			$action = $this->post($eStructure, $eCompositionSpecies, [[10, 12], [18, 26]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'trois semaines sont admises');
			$this->assertEquals([[10, 12], [18, 26]], $this->getSeason($eCompositionPlan, $eCompositionSpecies)['windowWeeks'], 'et s\'écrivent');

			$action = $this->post($eStructure, $eCompositionSpecies, [[10, 12], [13, 13], [18, 26]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'une semaine collée à un intervalle l\'allonge, elle n\'est pas un morceau');
			$this->assertEquals([[10, 13], [18, 26]], $this->getSeason($eCompositionPlan, $eCompositionSpecies)['windowWeeks'], 'et s\'écrit fusionnée');

		});

	}

	/**
	 * Une saison enregistrée avant la règle, avec un morceau court (le cas de la 7, corrigé
	 * à la main) : les gestes sur les autres intervalles passent, le morceau se retire, et
	 * une fois retiré il ne se repose plus. « Régler une espèce » renvoie la saison telle quelle et
	 * s'enregistre.
	 */
	public function testStoredCleaned(): void {

		$this->runAsManager(function(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies) {

			$this->writeSeason($eCompositionSpecies, [[4, 4], [18, 26]]);

			$eCompositionSpeciesStored = $this->getSeason($eCompositionPlan, $eCompositionSpecies);

			$fw = new \FailWatch();
			$eCompositionSpeciesStored->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonUpdated($eCompositionSpeciesStored, [['4', '4'], ['18', '26']]));
			$this->assertEquals([], array_column($fw->get(), 1), '« Régler une espèce » renvoie la saison qu\'il a montrée, morceau court compris, et s\'enregistre');

			$action = $this->post($eStructure, $eCompositionSpecies, [[4, 4], [18, 30]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'un geste sur un autre intervalle garde le morceau court déjà enregistré');
			$this->assertEquals([[4, 4], [18, 30]], $this->getSeason($eCompositionPlan, $eCompositionSpecies)['windowWeeks'], 'et l\'écrit');

			$action = $this->post($eStructure, $eCompositionSpecies, [[4, 5], [18, 30]]);
			$this->assertEquals(['CompositionSpecies::windowWeeks.short'], $this->getFails($action), 'le morceau court étiré à deux semaines est un morceau neuf, refusé');

			$action = $this->post($eStructure, $eCompositionSpecies, [[18, 30]]);
			$this->assertEquals(\ViewAction::class, $action::class, 'le morceau court se retire');
			$this->assertEquals([[18, 30]], $this->getSeason($eCompositionPlan, $eCompositionSpecies)['windowWeeks'], 'et la saison s\'écrit sans lui');

			$action = $this->post($eStructure, $eCompositionSpecies, [[4, 4], [18, 30]]);
			$this->assertEquals(['CompositionSpecies::windowWeeks.short'], $this->getFails($action), 'une fois retiré, il ne se repose plus');

		});

	}

	/**
	 * La saison `$windowWeeks` posée comme la grille l'écrivait avant la règle.
	 */
	private function writeSeason(CompositionSpecies $eCompositionSpecies, array $windowWeeks): void {

		// Sans build() : le décor doit porter une saison que le contrôle `short` refuserait, celle
		// qu'une espèce enregistrée avant lui porte. Le statut et la forme des intervalles sont ceux
		// que le schéma admet ; seule la longueur d'un morceau échappe au contrôle.
		$eCompositionSpecies['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpecies['windowWeeks'] = $windowWeeks;

		CompositionSpeciesLib::update($eCompositionSpecies, ['status', 'windowWeeks']);

	}

	/**
	 * La saison postée par la grille (`ToolboxCompositionSeason.save()`), en chaînes comme le
	 * navigateur les envoie.
	 */
	private function post(\structure\Structure $eStructure, CompositionSpecies $eCompositionSpecies, array $windowWeeks): \Action {

		return $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdateSeason', [
			'id' => $eCompositionSpecies['id'],
			'structure' => $eStructure['id'],
			'windowWeeks' => array_map(fn(array $interval) => array_map(strval(...), $interval), $windowWeeks),
		]);

	}

	/**
	 * Les noms des refus que porte une `FailAction`, vides pour toute autre action.
	 */
	private function getFails(\Action $action): array {

		if($action instanceof \FailAction === FALSE) {
			return [];
		}

		$fw = new \ReflectionProperty($action, 'fail')->getValue($action);

		return array_column($fw->get(), 1);

	}

	/**
	 * L'espèce relue en base, telle que la page la charge.
	 */
	private function getSeason(CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies): CompositionSpecies {

		$eCompositionSpeciesRead = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
			])
			->whereId($eCompositionSpecies['id'])
			->get();

		$eCompositionSpeciesRead['plan']['structure'] = $eCompositionPlan['structure'];

		return $eCompositionSpeciesRead;

	}

	/**
	 * `$check` sur un plan écrit par le départ, une espèce cultivée de ce plan, et un administrateur
	 * de la structure connecté.
	 */
	private function runAsManager(\Closure $check): void {

		$eMember = $this->getManagerWithoutPlan();

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		\user\ConnectionLib::setOnline($eMember['user']);

		$eCompositionPlan = $this->createStarted($eStructure);

		$eCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection())
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::EXCLUDED)
			->sort(['id' => SORT_ASC])
			->get();

		if($eCompositionSpecies->empty()) {
			$this->assertFail('le départ n\'a coché aucune espèce : le contrôle ne verrait rien');
			return;
		}

		$check($eStructure, $eCompositionPlan, $eCompositionSpecies);

	}

	/**
	 * Un plan écrit par le départ, sur la saison de la communauté et sans climat.
	 */
	private function createStarted(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = range(1, 52);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * Le premier administrateur en activité d'une structure qui n'a pas de plan pour la saison et
	 * peut en composer un.
	 */
	private function getManagerWithoutPlan(): \structure\Member {

		$cMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->getCollection(0, 200);

		foreach($cMember as $eMember) {
			if(\structure\StructureLib::getById($eMember['structure']['id'])->acceptComposition()) {
				return $eMember;
			}
		}

		return new \structure\Member();

	}

	/**
	 * La route jouée par son adresse, comme `RightsTest::callRoute()` : l'action qu'elle lève.
	 */
	private function callRoute(string $method, string $path, array $input): \Action {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];

		if($method === 'GET') {
			[$_GET, $_POST, $_REQUEST] = [$input, [], $input];
		} else {
			[$_GET, $_POST, $_REQUEST] = [[], $input, $input];
		}

		try {
			$pages = \Route::getCompatiblePages($path);
			$pages[201] = new \ReflectionMethod(\Page::class, 'getIndexPages')->invoke(NULL, $path);
			ksort($pages);

			\Page::execute($pages, new \stdClass());
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

		throw new \Exception('La route '.$path.' n\'a levé aucune action');

	}

}
?>
