<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les thématiques et les niveaux du catalogue : fusion, suppression d'une valeur encore portée,
 * ordre.
 */
class ThemeTest extends LearningFixtureTest {

	public function testMerge(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eTheme = $this->createTheme('zzTest thématique fusionnée');
		$eThemeTarget = $this->createTheme('zzTest thématique qui reçoit');

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eCourse['theme'] = $eTheme;
		CourseLib::update($eCourse, ['theme']);

		$fw = new \FailWatch();
		ThemeLib::merge($eTheme, $eTheme);
		$this->assertEquals(['Theme::mergeSelf'], array_column($fw->get(), 1), 'fusion dans elle-même');
		$this->assertTrue(Theme::model()->whereId($eTheme)->exists(), 'la fusion refusée ne supprime rien');

		$fw = new \FailWatch();
		ThemeLib::merge($eTheme, $eThemeTarget);
		$this->assertFalse($fw->ko());

		$this->assertEquals($eThemeTarget['id'], $this->getCourse($eCourse['id'])['theme']['id'], 'la formation passe à la thématique qui reçoit');
		$this->assertFalse(Theme::model()->whereId($eTheme)->exists(), 'la thématique fusionnée disparaît');

	}

	/**
	 * La fusion d'un niveau, miroir de celle d'une thématique, et la sortie qu'elle ouvre à
	 * l'écran : le geste de suppression que `assertDelete()` retire est remplacé par la fusion, il
	 * ne disparaît pas.
	 */
	public function testMergeLevel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLevel = $this->createLevel('zzTest niveau fusionné');
		$eLevelTarget = $this->createLevel('zzTest niveau qui reçoit');

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eCourse['level'] = $eLevel;
		CourseLib::update($eCourse, ['level']);

		$cLevel = new \Collection([$eLevel['id'] => $eLevel, $eLevelTarget['id'] => $eLevelTarget]);
		LevelLib::fillCourses($cLevel);

		$levels = new ThemeUi()->getLevels($cLevel);

		$rowUsed = $this->getLevelRow($levels, $eLevel);
		$this->assertTrue(str_contains($rowUsed, 'theme:mergeLevel'), 'le niveau porté propose la fusion');
		$this->assertFalse(str_contains($rowUsed, 'theme:doDeleteLevel'), 'et ne propose plus la suppression');

		$rowFree = $this->getLevelRow($levels, $eLevelTarget);
		$this->assertTrue(str_contains($rowFree, 'theme:doDeleteLevel'), 'le niveau libre propose la suppression');
		$this->assertFalse(str_contains($rowFree, 'theme:mergeLevel'), 'et ne propose pas la fusion');

		$fw = new \FailWatch();
		LevelLib::merge($eLevel, $eLevel);
		$this->assertEquals(['Level::mergeSelf'], array_column($fw->get(), 1), 'fusion dans lui-même');
		$this->assertTrue(Level::model()->whereId($eLevel)->exists(), 'la fusion refusée ne supprime rien');

		$fw = new \FailWatch();
		LevelLib::merge($eLevel, $eLevelTarget);
		$this->assertFalse($fw->ko());

		$this->assertEquals($eLevelTarget['id'], $this->getCourse($eCourse['id'])['level']['id'], 'la formation passe au niveau qui reçoit');
		$this->assertFalse(Level::model()->whereId($eLevel)->exists(), 'le niveau fusionné disparaît');

	}

	/**
	 * La cible d'une fusion, quand elle ne convient pas. Les trois cas arrivent au Lib sous la même
	 * forme, un élément vide : la liste ouverte sur son choix vide, un identifiant inconnu, une
	 * cible supprimée entre l'ouverture du panneau et l'envoi. Le refus doit se lire à l'écran,
	 * comme celui du reste du lot.
	 */
	public function testMergeTargetMissing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$eLevel = $eCourse['level'];
		$eTheme = $eCourse['theme'];

		$this->assertEquals(
			['FailAction', ['Level::mergeTargetMissing']],
			$this->runPage('doMergeLevel', $eLevel['id'], ['target' => '']),
			'fusionner un niveau sans avoir choisi la cible'
		);

		$this->assertEquals(
			['FailAction', ['Level::mergeTargetMissing']],
			$this->runPage('doMergeLevel', $eLevel['id'], ['target' => '999999999']),
			'fusionner un niveau dans une cible qui n\'existe pas'
		);

		$this->assertEquals(
			['FailAction', ['Theme::mergeTargetMissing']],
			$this->runPage('doMerge', $eTheme['id'], ['target' => '']),
			'fusionner une thématique sans avoir choisi la cible'
		);

		$this->assertEquals(
			['FailAction', ['Theme::mergeTargetMissing']],
			$this->runPage('doMerge', $eTheme['id'], ['target' => '999999999']),
			'fusionner une thématique dans une cible qui n\'existe pas'
		);

		$this->assertTrue(Level::model()->whereId($eLevel)->exists(), 'le niveau refusé est intact');
		$this->assertTrue(Theme::model()->whereId($eTheme)->exists(), 'la thématique refusée est intacte');
		$this->assertEquals($eLevel['id'], $this->getCourse($eCourse['id'])['level']['id'], 'la formation n\'a pas bougé');

		// L'autre sens : une cible réelle passe, donc la garde refuse le cas qu'elle vise et lui
		// seul. Sans cette moitié, une garde qui refuserait tout rendrait les trois premières
		// assertions vertes.
		$eLevelTarget = $this->createLevel('zzTest niveau qui reçoit la cible');

		$this->assertEquals(
			['ReloadAction', []],
			$this->runPage('doMergeLevel', $eLevel['id'], ['target' => (string)$eLevelTarget['id']]),
			'fusionner un niveau dans une cible réelle'
		);

		$this->assertEquals($eLevelTarget['id'], $this->getCourse($eCourse['id'])['level']['id'], 'la formation passe au niveau qui reçoit');
		$this->assertFalse(Level::model()->whereId($eLevel)->exists(), 'le niveau fusionné disparaît');

	}

	/**
	 * Les deux panneaux de fusion ouvrent sur un choix vide, et non sur la première cible venue.
	 * Deux assertions par panneau : la liste porte l'option vide, et c'est elle qui est retenue.
	 * La seconde ne découle pas de la première, une liste pouvant porter l'option vide et en
	 * retenir une autre.
	 */
	public function testMergeTargetOption(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLevel = $this->createLevel('zzTest niveau à fusionner');
		$eLevelTarget = $this->createLevel('zzTest niveau cible du panneau');
		$eLevel['cLevel'] = new \Collection([$eLevelTarget['id'] => $eLevelTarget]);

		$this->assertTargetOption(new ThemeUi()->mergeLevel($eLevel)->body, 'panneau du niveau');

		$eTheme = $this->createTheme('zzTest thématique à fusionner');
		$eThemeTarget = $this->createTheme('zzTest thématique cible du panneau');
		$eTheme['cTheme'] = new \Collection([$eThemeTarget['id'] => $eThemeTarget]);

		$this->assertTargetOption(new ThemeUi()->merge($eTheme)->body, 'panneau de la thématique');

		// L'autre sens. Il se mesure sur le cadriciel et non sur un panneau, la contrainte n'ayant
		// aucune trace dans le balisage qu'un panneau rend : seule sa conséquence s'y lit. Il
		// établit que c'est bien son absence qui fait naître l'option vide. Sans cette moitié, les
		// quatre assertions précédentes passeraient sans qu'on ait vu l'outil trouver.
		$constrained = new \util\FormUi()->select('target', $eLevel['cLevel'], attributes: ['required' => TRUE]);

		$this->assertFalse(str_contains($constrained, '<option value=""'), 'contrainte posée, le cadriciel n\'émet plus de choix vide');

	}

	/**
	 * Ce qu'un panneau de fusion doit montrer de sa liste de cibles. La contrainte elle-même ne
	 * s'y lit pas : `FormUi::select()` ne l'écrit pas dans la balise, il s'en sert pour décider
	 * d'émettre l'option vide ou non. Seule sa conséquence est observable, et c'est donc elle
	 * qu'on regarde.
	 */
	private function assertTargetOption(string $body, string $panneau): void {

		$this->assertTrue(
			preg_match('/<select[^>]*name="target"[^>]*>\s*<option value=""/', $body) === 1,
			$panneau.' : la liste des cibles ouvre sur un choix vide'
		);

		$this->assertTrue(
			preg_match('/<select[^>]*name="target"[^>]*>\s*<option value="" selected="selected"/', $body) === 1,
			$panneau.' : et c\'est ce choix vide qui est retenu'
		);

	}

	/**
	 * Les positions après un retrait, suppression comme fusion. Elles se relisent en base et non sur
	 * la collection rendue, et le `create()` qui suit se joue vraiment : c'est lui que le trou casse,
	 * puisqu'il pose `position = count()`.
	 */
	public function testReorder(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		// La base de dev est partagée : un trou hérité d'un autre travail rendrait les assertions
		// ci-dessous rouges au nom de ce lot, et `create()`, qui pose `position = count()`, y écrirait
		// un doublon avant que la renumérotation éprouvée ici n'ait tourné une seule fois.
		LevelLib::reorder();
		ThemeLib::reorder();

		$idsLevelBefore = LevelLib::getAll()->getIds();
		$count = count($idsLevelBefore) + 5;

		$eLevelFirst = $this->createLevel('zzTest ordre 1');
		$eLevelSecond = $this->createLevel('zzTest ordre 2');
		$eLevelMiddle = $this->createLevel('zzTest ordre 3');
		$eLevelFourth = $this->createLevel('zzTest ordre 4');
		$eLevelFifth = $this->createLevel('zzTest ordre 5');

		$this->assertEquals(range(0, $count - 1), $this->getLevelPositions(), 'cinq niveaux ajoutés, positions contiguës');

		// L'ordre est imposé à rebours de celui des ids, parce que des positions contiguës ne
		// distinguent pas une renumérotation qui suit l'ordre de celle qui trie par id : les deux
		// rendent la même suite de nombres. Or l'ordre des niveaux est le sens du module.
		$idsLevel = array_merge([
			$eLevelFifth['id'],
			$eLevelFourth['id'],
			$eLevelMiddle['id'],
			$eLevelSecond['id'],
			$eLevelFirst['id'],
		], $idsLevelBefore);

		LevelLib::updatePositions($idsLevel);

		$this->assertEquals($idsLevel, LevelLib::getAll()->getIds(), 'ordre imposé, relu en base');

		LevelLib::delete($eLevelMiddle);

		$idsLevel = array_values(array_diff($idsLevel, [$eLevelMiddle['id']]));

		$this->assertEquals(range(0, $count - 2), $this->getLevelPositions(), 'un niveau retiré du milieu, positions contiguës');
		$this->assertEquals($idsLevel, LevelLib::getAll()->getIds(), 'un niveau retiré du milieu, ordre préservé');

		$eLevelNext = $this->createLevel('zzTest ordre 6');

		$idsLevel[] = $eLevelNext['id'];

		$this->assertEquals($count - 1, $this->getLevelPosition($eLevelNext), 'le niveau suivant prend la place libérée');
		$this->assertEquals(range(0, $count - 1), $this->getLevelPositions(), 'et aucune position en double');
		$this->assertEquals($idsLevel, LevelLib::getAll()->getIds(), 'et il se range en dernier');

		// La fusion supprime par `parent::delete()` et non par `delete()` : elle porte donc sa propre
		// renumérotation. Sans ce contrôle, la poser dans la seule suppression passerait pour fait.
		LevelLib::merge($eLevelSecond, $eLevelFourth);

		$this->assertEquals(range(0, $count - 2), $this->getLevelPositions(), 'un niveau fusionné, positions contiguës');
		$this->assertEquals(array_values(array_diff($idsLevel, [$eLevelSecond['id']])), LevelLib::getAll()->getIds(), 'un niveau fusionné, ordre préservé');

		$idsThemeBefore = ThemeLib::getAll()->getIds();
		$countTheme = count($idsThemeBefore) + 3;

		$eThemeFirst = $this->createTheme('zzTest ordre thématique 1');
		$eThemeSecond = $this->createTheme('zzTest ordre thématique 2');
		$eThemeThird = $this->createTheme('zzTest ordre thématique 3');

		$idsTheme = array_merge([
			$eThemeThird['id'],
			$eThemeSecond['id'],
			$eThemeFirst['id'],
		], $idsThemeBefore);

		ThemeLib::updatePositions($idsTheme);

		ThemeLib::delete($eThemeFirst);

		$idsTheme = array_values(array_diff($idsTheme, [$eThemeFirst['id']]));

		$this->assertEquals(range(0, $countTheme - 2), $this->getThemePositions(), 'une thématique retirée, positions contiguës');
		$this->assertEquals($idsTheme, ThemeLib::getAll()->getIds(), 'une thématique retirée, ordre préservé');

		ThemeLib::merge($eThemeSecond, $this->createTheme('zzTest ordre thématique 4'));

		$this->assertEquals(range(0, $countTheme - 2), $this->getThemePositions(), 'une thématique fusionnée, positions contiguës');

	}

	/** Les positions de tous les niveaux, relues en base. */
	private function getLevelPositions(): array {

		$positions = Level::model()
			->select(['id', 'position'])
			->getCollection()
			->getColumn('position');

		sort($positions);

		return $positions;

	}

	private function getLevelPosition(Level $eLevel): int {

		return Level::model()
			->select(['id', 'position'])
			->whereId($eLevel)
			->get()['position'];

	}

	/** Les positions de toutes les thématiques, relues en base. */
	private function getThemePositions(): array {

		$positions = Theme::model()
			->select(['id', 'position'])
			->getCollection()
			->getColumn('position');

		sort($positions);

		return $positions;

	}

	public function testDeleteUsed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		// L'écran propose la suppression par `assertDelete()`, sur le compte que la page pose.
		$cTheme = new \Collection([$eCourse['theme']]);
		ThemeLib::fillCourses($cTheme);
		$this->assertFalse($cTheme->first()->assertDelete(), 'l\'écran ne propose pas de supprimer une thématique portée');

		$cLevel = new \Collection([$eCourse['level']]);
		LevelLib::fillCourses($cLevel);
		$this->assertFalse($cLevel->first()->assertDelete(), 'l\'écran ne propose pas de supprimer un niveau porté');

		$fw = new \FailWatch();
		ThemeLib::delete($eCourse['theme']);
		$this->assertEquals(['Theme::deleteUsed'], array_column($fw->get(), 1), 'thématique encore portée');

		$fw = new \FailWatch();
		LevelLib::delete($eCourse['level']);
		$this->assertEquals(['Level::deleteUsed'], array_column($fw->get(), 1), 'niveau encore porté');

		$eTheme = $this->createTheme('zzTest thématique libre');

		ThemeLib::fillCourses(new \Collection([$eTheme]));
		$this->assertTrue($eTheme->assertDelete(), 'l\'écran propose de supprimer une thématique libre');

		$fw = new \FailWatch();
		ThemeLib::delete($eTheme);
		$this->assertFalse($fw->ko(), 'thématique libre');
		$this->assertFalse(Theme::model()->whereId($eTheme)->exists());

	}

	/**
	 * La suppression jouée par la route, et non par le Lib seul : c'est le montage que ce lot
	 * change. Le refus qui parle est celui du Lib ; `acceptDelete`, que la route validait avant
	 * d'ouvrir son `FailWatch`, en sortait par une `NotExpectedAction` sans message.
	 */
	public function testDeleteFromRoute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$this->assertEquals(['FailAction', ['Theme::deleteUsed']], $this->runPage('doDelete', $eCourse['theme']['id']), 'supprimer une thématique portée');
		$this->assertTrue(Theme::model()->whereId($eCourse['theme'])->exists(), 'la thématique portée est intacte');

		$this->assertEquals(['FailAction', ['Level::deleteUsed']], $this->runPage('doDeleteLevel', $eCourse['level']['id']), 'supprimer un niveau porté');
		$this->assertTrue(Level::model()->whereId($eCourse['level'])->exists(), 'le niveau porté est intact');

		$eTheme = $this->createTheme('zzTest thématique libre');

		$this->assertEquals(['ReloadAction', []], $this->runPage('doDelete', $eTheme['id']), 'supprimer une thématique libre');
		$this->assertFalse(Theme::model()->whereId($eTheme)->exists(), 'la thématique libre est supprimée');

		$eThemeGuarded = $this->createTheme('zzTest thématique gardée');

		$this->actAs($this->eUserReader);

		// Le droit est le seul conjoint que le Lib ne rejuge pas. La route le pose deux fois,
		// `validateAdmin()` fermant la page entière avant que `canDelete` ne soit atteint : le
		// refus se lit donc sur l'élément, et la route se contrôle sur ce qu'elle rend au lecteur.
		$this->assertFalse($eThemeGuarded->canDelete(), 'un lecteur n\'a pas le droit de supprimer');
		$this->assertEquals(['DisabledPage', []], $this->runPage('doDelete', $eThemeGuarded['id']), 'la route refuse le lecteur');
		$this->assertTrue(Theme::model()->whereId($eThemeGuarded)->exists(), 'et ne supprime rien');

	}

	/**
	 * Sans formation portée, la suppression reste soumise à la règle d'écriture. Aucune thématique
	 * ni aucun niveau n'en pose aujourd'hui : une sous-classe qui ferme l'écriture montre que
	 * `acceptDelete()` la lit. L'autre branche du `and`, « aucune formation portée », est éprouvée
	 * par `testDeleteUsed()`, sur des éléments réels.
	 */
	public function testDeleteReadsWrite(): void {

		$this->assertTrue(new Theme(['courses' => 0])->acceptDelete(), 'thématique libre');
		$this->assertTrue(new Level(['courses' => 0])->acceptDelete(), 'niveau libre');

		$eThemeClosed = new class(['courses' => 0]) extends Theme {
			public function acceptWrite(): bool {
				return FALSE;
			}
		};

		$eLevelClosed = new class(['courses' => 0]) extends Level {
			public function acceptWrite(): bool {
				return FALSE;
			}
		};

		$this->assertFalse($eThemeClosed->acceptDelete(), 'thématique libre, écriture fermée');
		$this->assertFalse($eLevelClosed->acceptDelete(), 'niveau libre, écriture fermée');

	}

	/**
	 * L'ordre posté doit nommer chaque thématique une fois : un envoi d'un onglet resté ouvert
	 * pendant un ajout est refusé plutôt que de laisser deux thématiques à la même place.
	 */
	public function testPositions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->createTheme('zzTest thématique ajoutée');

		$ids = array_reverse(ThemeLib::getAll()->getIds());

		$fw = new \FailWatch();
		ThemeLib::updatePositions(array_slice($ids, 1));
		$this->assertEquals(['Theme::positionsIncomplete'], array_column($fw->get(), 1), 'une thématique manque');

		$fw = new \FailWatch();
		ThemeLib::updatePositions([$ids[0], ...$ids]);
		$this->assertEquals(['Theme::positionsIncomplete'], array_column($fw->get(), 1), 'une thématique en double');

		$fw = new \FailWatch();
		ThemeLib::updatePositions($ids);
		$this->assertFalse($fw->ko());
		$this->assertEquals($ids, ThemeLib::getAll()->getIds(), 'ordre inversé');

		$ids = array_reverse(LevelLib::getAll()->getIds());

		$fw = new \FailWatch();
		LevelLib::updatePositions($ids);
		$this->assertFalse($fw->ko());
		$this->assertEquals($ids, LevelLib::getAll()->getIds(), 'niveaux, ordre inversé');

	}

	/**
	 * Une icône ou une couleur hors de la liste est refusée par le contrôle qui remplace celui du
	 * schéma.
	 */
	public function testBuild(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eTheme = new Theme();

		$fw = new \FailWatch();
		$eTheme->build(ThemeLib::getPropertiesCreate(), ['name' => 'zzTest', 'icon' => 'bomb', 'color' => '#ff0000'], new \PropertyList('create'));
		$this->assertEquals(['Theme::icon.check', 'Theme::color.check'], array_column($fw->get(), 1));

	}

	/**
	 * Une thématique s'ajoute comme un niveau : par le bouton du titre de sa liste, qui ouvre un
	 * panneau, et non par un formulaire posé sous la liste.
	 */
	public function testCreatePanel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cTheme = ThemeLib::getAll();
		ThemeLib::fillCourses($cTheme);

		$cLevel = LevelLib::getAll();
		LevelLib::fillCourses($cLevel);

		$list = new ThemeUi()->getList($cTheme, $cLevel);

		$this->assertTrue(str_contains($list, 'href="/learning/admin/theme:create"'), 'la liste ouvre le panneau d\'ajout d\'une thématique');
		$this->assertTrue(str_contains($list, 'href="/learning/admin/theme:createLevel"'), 'comme celui d\'un niveau');
		$this->assertFalse(str_contains($list, '/learning/admin/theme:doCreate'), 'aucun formulaire d\'ajout n\'est posé sous la liste');

		$body = new ThemeUi()->create(new Theme())->body;

		$this->assertTrue(str_contains($body, 'data-ajax-form="/learning/admin/theme:doCreate"'), 'le panneau envoie à la création');

		$this->assertEquals(
			new ThemeUi()->create(new Theme())->id,
			new ThemeUi()->update(ThemeLib::getAll()->first())->id,
			'un seul panneau pour ajouter et modifier une thématique, comme pour un niveau'
		);

		$this->assertEquals(['ViewAction', []], $this->runPage('create', method: 'GET'), 'la route du panneau répond');

		$this->assertEquals(
			['FailAction', ['Theme::name.check', 'Theme::icon.check', 'Theme::color.check']],
			$this->runPage('doCreate', post: ['name' => '', 'icon' => 'bomb', 'color' => '#ff0000']),
			'une création refusée le dit, champ par champ'
		);

	}

	/**
	 * La route jouée comme le produit la joue : le nom de l'action qui l'a interrompue, et les
	 * refus levés en chemin. `Fail::log()` incrémente tous les guetteurs ouverts, celui-ci voit
	 * donc le refus que la route lève dans le sien.
	 */
	private function runPage(string $page, int $id = 0, array $post = [], string $method = 'POST'): array {

		$fw = new \FailWatch();

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodBefore = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		$input = ($id === 0) ? $post : ['id' => (string)$id] + $post;

		$getBefore = $_GET;
		$postBefore = $_POST;

		if($method === 'GET') {
			$_GET = $input;
		} else {
			$_POST = $input;
		}

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		try {
			\Page::execute([['learning/admin/theme', $page, [], NULL]], $data);
			$name = 'aucune action';
		} catch(\Throwable $e) {
			$name = new \ReflectionClass($e)->getShortName();
		} finally {
			$_GET = $getBefore;
			$_POST = $postBefore;
			$requestMethod->setValue(NULL, $requestMethodBefore);
		}

		return [$name, array_column($fw->get(), 1)];

	}

	/** La ligne d'un niveau dans la liste rendue, coupée avant la suivante. */
	private function getLevelRow(string $levels, Level $eLevel): string {

		$start = strpos($levels, 'data-sort-id="'.$eLevel['id'].'"');

		if($start === FALSE) {
			throw new \Exception('le niveau #'.$eLevel['id'].' n\'a pas de ligne dans la liste rendue');
		}

		$next = strpos($levels, 'data-sort-id="', $start + 1);

		return ($next === FALSE) ? substr($levels, $start) : substr($levels, $start, $next - $start);

	}

	private function createLevel(string $name): Level {

		$eLevel = new Level();

		$fw = new \FailWatch();

		$eLevel->build(LevelLib::getPropertiesCreate(), ['name' => $name], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du niveau refusée : '.json_encode($fw->get())));

		LevelLib::create($eLevel);

		return $eLevel;

	}

	private function createTheme(string $name): Theme {

		$eTheme = new Theme();

		$fw = new \FailWatch();

		$eTheme->build(ThemeLib::getPropertiesCreate(), [
			'name' => $name,
			'icon' => Theme::ICONS[0],
			'color' => Theme::COLORS['production'],
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de la thématique refusée : '.json_encode($fw->get())));

		ThemeLib::create($eTheme);

		return $eTheme;

	}

}
?>
