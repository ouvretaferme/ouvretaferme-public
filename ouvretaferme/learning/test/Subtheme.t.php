<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les sous-thématiques des formations (lot T1) : la règle qui les garde dans la thématique de leur
 * formation, ce que la fusion et la suppression d'une thématique en font, leur administration, et le
 * script `learning/configure/theme` qui les sème.
 */
class SubthemeTest extends LearningFixtureTest {

	/**
	 * Une formation ne porte qu'une sous-thématique de sa thématique : le formulaire le vérifie à
	 * l'envoi (`Course::subtheme.consistency`), et le Lib vide la sous-thématique quand seule la
	 * thématique change, comme le fait l'API ou la fusion.
	 */
	public function testConsistency(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eSubtheme, $eThemeOther, $eSubthemeOther] = $this->getSubthemes();

		if($eSubtheme->empty()) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([], $this->buildSubtheme($eCourse, $this->eTheme, $eSubtheme), 'une sous-thématique de la thématique est acceptée');
		CourseLib::update($eCourse, ['theme', 'subtheme']);
		$this->assertEquals($eSubtheme['id'], $this->getCourse($eCourse['id'])['subtheme']['id'], 'et écrite');

		$this->assertEquals(['Course::subtheme.consistency'], $this->buildSubtheme($this->getCourse($eCourse['id']), $this->eTheme, $eSubthemeOther), 'une sous-thématique d\'une autre thématique est refusée');
		$this->assertEquals(['Course::subtheme.consistency'], $this->buildSubtheme($this->getCourse($eCourse['id']), new Theme(), $eSubtheme), 'une sous-thématique sans thématique est refusée');
		$this->assertEquals([], $this->buildSubtheme($this->getCourse($eCourse['id']), $this->eTheme, new Subtheme()), 'aucune sous-thématique est un choix');

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['theme'] = $this->eTheme;
		CourseLib::update($eCourse, ['theme']);
		$this->assertEquals($eSubtheme['id'], $this->getCourse($eCourse['id'])['subtheme']['id'], 'la même thématique, reposée seule, garde la sous-thématique');

		$eCourse['theme'] = $eThemeOther;
		CourseLib::update($eCourse, ['theme']);
		$this->assertTrue($this->getCourse($eCourse['id'])['subtheme']->empty(), 'une autre thématique, posée seule, vide la sous-thématique');

		$eCourse = $this->getCourse($eCourse['id']);
		$this->assertEquals([], $this->buildSubtheme($eCourse, $eThemeOther, $eSubthemeOther), 'la thématique et sa sous-thématique changent ensemble');
		CourseLib::update($eCourse, ['theme', 'subtheme']);
		$this->assertEquals($eSubthemeOther['id'], $this->getCourse($eCourse['id'])['subtheme']['id'], 'et la nouvelle sous-thématique est écrite, et non vidée');

	}

	/**
	 * Le formulaire de la formation recharge le groupe de la sous-thématique quand la thématique
	 * change : la route ne propose que les sous-thématiques de la thématique postée, et garde celle
	 * de la formation quand on revient à sa thématique.
	 */
	public function testSubthemeField(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eSubtheme, $eThemeOther] = $this->getSubthemes();

		if($eSubtheme->empty()) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eCourse['subtheme'] = $eSubtheme;
		CourseLib::update($eCourse, ['subtheme']);

		$data = $this->executeView('learning/course:getSubthemeField', ['id' => (string)$eCourse['id'], 'theme' => (string)$this->eTheme['id'], 'columns' => '1'], 'POST');
		$this->assertEquals(SubthemeLib::getByTheme($this->eTheme)->getIds(), $data->eCourse['cSubtheme']->getIds(), 'les sous-thématiques de la thématique postée');
		$this->assertEquals($eSubtheme['id'], $data->eCourse['subtheme']['id'], 'la formation garde la sienne sous sa thématique');
		$this->assertEquals(1, $data->columns, 'le groupe se rend comme ses voisins');

		$field = CourseUi::getSubthemeField(new \util\FormUi(), $data->eCourse);
		$this->assertTrue(str_contains($field, 'id="learning-course-subtheme-field"'), 'le conteneur que la réponse remplace');
		$this->assertTrue(str_contains($field, '<option value="'.$eSubtheme['id'].'" selected'), 'la sous-thématique de la formation est choisie : '.$field);

		$data = $this->executeView('learning/course:getSubthemeField', ['id' => (string)$eCourse['id'], 'theme' => (string)$eThemeOther['id']], 'POST');
		$this->assertEquals(SubthemeLib::getByTheme($eThemeOther)->getIds(), $data->eCourse['cSubtheme']->getIds(), 'celles de l\'autre thématique');
		$this->assertTrue($data->eCourse['subtheme']->empty(), 'sous une autre thématique, le choix repart de zéro');
		$this->assertEquals(2, $data->columns, 'sans colonnes postées, le formulaire à deux colonnes');

		$data = $this->executeView('learning/course:getSubthemeField', ['theme' => ''], 'POST');
		$this->assertTrue($data->eCourse['cSubtheme']->empty(), 'sans thématique, rien à choisir');
		$field = CourseUi::getSubthemeField(new \util\FormUi(), $data->eCourse);
		$this->assertTrue(str_contains($field, 'id="learning-course-subtheme-field"') and str_contains($field, '<option') === FALSE, 'le conteneur reste, vide : '.$field);

	}

	/**
	 * Fusionnée ou supprimée, une thématique emporte ses sous-thématiques. Les formations déplacées
	 * par la fusion perdent la leur, qui n'appartenait pas à la thématique qui les reçoit.
	 */
	public function testMergeAndDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eTheme = $this->createTheme('zzTest thématique fusionnée');
		$eSubtheme = $this->createSubtheme($eTheme, 'zzTest sous-thématique fusionnée');

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$this->assertEquals([], $this->buildSubtheme($eCourse, $eTheme, $eSubtheme));
		CourseLib::update($eCourse, ['theme', 'subtheme']);

		$fw = new \FailWatch();
		ThemeLib::merge($eTheme, $this->eTheme);
		$this->assertFalse($fw->ko());

		$eCourse = $this->getCourse($eCourse['id']);
		$this->assertEquals($this->eTheme['id'], $eCourse['theme']['id'], 'la formation passe à la thématique qui reçoit');
		$this->assertTrue($eCourse['subtheme']->empty(), 'sans sa sous-thématique');
		$this->assertFalse(Subtheme::model()->whereId($eSubtheme)->exists(), 'la sous-thématique part avec sa thématique');

		$eTheme = $this->createTheme('zzTest thématique supprimée');
		$eSubtheme = $this->createSubtheme($eTheme, 'zzTest sous-thématique supprimée');

		$fw = new \FailWatch();
		ThemeLib::delete($eTheme);
		$this->assertFalse($fw->ko());
		$this->assertFalse(Subtheme::model()->whereId($eSubtheme)->exists(), 'supprimée, la thématique emporte ses sous-thématiques');

	}

	/**
	 * L'administration : une sous-thématique neuve prend la place suivante, l'ordre posté doit les
	 * nommer toutes, une sous-thématique portée ne se supprime pas, et un retrait renumérote.
	 */
	public function testAdmin(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eTheme = $this->createTheme('zzTest thématique administrée');

		$eSubthemeFirst = $this->createSubtheme($eTheme, 'zzTest première');
		$eSubthemeSecond = $this->createSubtheme($eTheme, 'zzTest deuxième');
		$eSubthemeThird = $this->createSubtheme($eTheme, 'zzTest troisième');

		$this->assertEquals([0, 1, 2], SubthemeLib::getByTheme($eTheme)->getColumn('position'), 'chacune prend la place suivante');

		$ids = [$eSubthemeThird['id'], $eSubthemeSecond['id'], $eSubthemeFirst['id']];

		$fw = new \FailWatch();
		SubthemeLib::updatePositions($eTheme, array_slice($ids, 1));
		$this->assertEquals(['Subtheme::positionsIncomplete'], array_column($fw->get(), 1), 'une sous-thématique manque');

		$fw = new \FailWatch();
		SubthemeLib::updatePositions($eTheme, [$ids[0], ...$ids]);
		$this->assertEquals(['Subtheme::positionsIncomplete'], array_column($fw->get(), 1), 'une sous-thématique en double');

		$fw = new \FailWatch();
		SubthemeLib::updatePositions($eTheme, [$ids[0], $ids[1], $this->getSubthemes()[0]['id']]);
		$this->assertEquals(['Subtheme::positionsIncomplete'], array_column($fw->get(), 1), 'une sous-thématique d\'une autre thématique');

		$fw = new \FailWatch();
		SubthemeLib::updatePositions($eTheme, $ids);
		$this->assertFalse($fw->ko());
		$this->assertEquals($ids, SubthemeLib::getByTheme($eTheme)->getIds(), 'ordre inversé');

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$this->assertEquals([], $this->buildSubtheme($eCourse, $eTheme, $eSubthemeSecond));
		CourseLib::update($eCourse, ['theme', 'subtheme']);

		$cSubtheme = SubthemeLib::getByTheme($eTheme);
		SubthemeLib::fillCourses($cSubtheme);

		$this->assertEquals([0, 1, 0], $cSubtheme->getColumn('courses'), 'le nombre de formations de chaque ligne');
		$this->assertFalse($cSubtheme[$eSubthemeSecond['id']]->assertDelete(), 'le menu n\'offre pas de supprimer une sous-thématique portée');
		$this->assertTrue($cSubtheme[$eSubthemeThird['id']]->assertDelete(), 'il l\'offre pour une sous-thématique libre');

		$panel = new ThemeUi()->getSubthemes($eTheme, $cSubtheme)->body;
		$this->assertTrue(str_contains($panel, 'post-id="'.$eTheme['id'].'"'), 'l\'ordre se poste avec sa thématique');
		$this->assertTrue(str_contains($panel, 'href="/learning/admin/theme:createSubtheme?theme='.$eTheme['id'].'"'), 'l\'ajout ouvre le panneau de la thématique');

		$this->assertEquals([\FailAction::class, 'Subtheme::deleteUsed'], $this->executePage('learning/admin/theme:doDeleteSubtheme', ['id' => (string)$eSubthemeSecond['id']]), 'la route refuse une sous-thématique portée, sous le nom du refus du Lib');
		$this->assertTrue(Subtheme::model()->whereId($eSubthemeSecond)->exists(), 'et ne supprime rien');

		$this->assertEquals([\ReloadLayerAction::class, NULL], $this->executePage('learning/admin/theme:doDeleteSubtheme', ['id' => (string)$eSubthemeThird['id']]), 'une sous-thématique libre se supprime, et le panneau se recharge');
		$this->assertFalse(Subtheme::model()->whereId($eSubthemeThird)->exists());
		$this->assertEquals([0, 1], SubthemeLib::getByTheme($eTheme)->getColumn('position'), 'le retrait renumérote');

		$this->assertEquals([\BackAction::class, NULL], $this->executePage('learning/admin/theme:doCreateSubtheme', ['theme' => (string)$eTheme['id'], 'name' => 'zzTest ajoutée par la route']), 'l\'ajout par la route referme son panneau');
		$this->assertEquals(['zzTest deuxième', 'zzTest première', 'zzTest ajoutée par la route'], SubthemeLib::getByTheme($eTheme)->getColumn('name'), 'à la suite des autres');

		$this->assertEquals(\NotExistsAction::class, $this->executeView('learning/admin/theme:createSubtheme', ['theme' => '0']), 'pas d\'ajout sans thématique');

		$data = $this->executeView('learning/admin/theme:subthemes', ['id' => (string)$eTheme['id']]);
		$this->assertEquals(SubthemeLib::getByTheme($eTheme)->getIds(), $data->cSubtheme->getIds(), 'le panneau de la thématique');

		$this->actAs($this->eUserReader);
		$this->assertFalse($cSubtheme[$eSubthemeFirst['id']]->canDelete(), 'un lecteur n\'administre pas les sous-thématiques');

	}

	/**
	 * Le script `learning/configure/theme` sur une base en retard : une thématique sous son ancien
	 * nom, une thématique manquante avec ses sous-thématiques, les places mélangées. Le premier
	 * passage rend les douze dans l'ordre du référentiel, la thématique ajoutée par l'équipe après
	 * elles ; le second ne change rien.
	 */
	public function testConfigure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$cTheme = ThemeLib::getAll();
		$eThemeVegetal = $cTheme->find(fn(Theme $eTheme) => $eTheme['name'] === 'Production végétale')->first();
		$eThemeAnimal = $cTheme->find(fn(Theme $eTheme) => $eTheme['name'] === 'Production animale')->first();

		if($eThemeVegetal === NULL or $eThemeAnimal === NULL) {
			$this->assertFail('décor absent : learning/configure/theme à jouer');
			return;
		}

		$eThemeTeam = $this->createTheme('zzTest thématique de l\'équipe');

		// Le décor d'une base d'avant T1 : sans build(), le nom ancien n'est plus une valeur que le
		// produit écrirait, et les places mélangées sont celles d'un ordre retouché à la main.
		$eThemeVegetal['name'] = 'Production et maraîchage';
		Theme::model()->select('name')->update($eThemeVegetal);

		ThemeLib::delete($eThemeAnimal);

		ThemeLib::updatePositions(array_reverse(ThemeLib::getAll()->getIds()));

		$output = $this->runConfigure();

		$this->assertTrue(str_contains($output, 'renommée : Production et maraîchage → Production végétale'), 'l\'ancien nom est reconnu : '.$output);
		$this->assertTrue(str_contains($output, 'créée : Production animale'), 'la thématique manquante est créée');
		$this->assertTrue(str_contains($output, 'sous-thématiques créées : 6'), 'avec ses six sous-thématiques, et aucune autre');

		$cTheme = ThemeLib::getAll();
		$names = $cTheme->getColumn('name');

		$this->assertEquals(self::THEMES, array_slice($names, 0, 12), 'les douze dans l\'ordre du référentiel');
		$this->assertEquals(range(0, $cTheme->count() - 1), $cTheme->getColumn('position'), 'des places contiguës');
		$this->assertEquals($eThemeVegetal['id'], $cTheme->first()['id'], 'la thématique renommée garde sa ligne');
		$this->assertTrue(array_search('zzTest thématique de l\'équipe', $names) >= 12, 'la thématique de l\'équipe passe après les douze');

		$eThemeAnimal = $cTheme->find(fn(Theme $eTheme) => $eTheme['name'] === 'Production animale')->first();
		$this->assertEquals(['Bovins', 'Ovins et caprins', 'Porcins', 'Volailles', 'Apiculture', 'Santé et bien-être animal'], $eThemeAnimal['cSubtheme']->getColumn('name'), 'les sous-thématiques dans l\'ordre du référentiel');
		$this->assertEquals(range(0, 5), $eThemeAnimal['cSubtheme']->getColumn('position'));

		$snapshot = $this->getSnapshot();

		$output = $this->runConfigure();

		$this->assertFalse(str_contains($output, 'créée :'), 'rejoué, il ne crée aucune thématique : '.$output);
		$this->assertTrue(str_contains($output, 'sous-thématiques créées : 0'), 'ni aucune sous-thématique');
		$this->assertEquals($snapshot, $this->getSnapshot(), 'ni ne touche une place');

		$this->assertTrue(Theme::model()->whereId($eThemeTeam)->exists(), 'la thématique de l\'équipe est conservée');

	}

	const THEMES = [
		'Production végétale',
		'Production animale',
		'Agronomie et environnement',
		'Transformation et qualité',
		'Vente et commercialisation',
		'Comptabilité et gestion',
		'Stratégie de la ferme',
		'Installation et transmission',
		'Accueil et territoire',
		'Emploi et collectifs',
		'Santé et sécurité au travail',
		'Logiciel Ouvretaferme',
	];

	/** Chaque thématique avec sa place et ses sous-thématiques, rangées. */
	private function getSnapshot(): array {

		$snapshot = [];

		foreach(ThemeLib::getAll() as $eTheme) {
			$snapshot[$eTheme['id']] = [$eTheme['position'], $eTheme['cSubtheme']->getIds(), $eTheme['cSubtheme']->getColumn('position')];
		}

		return $snapshot;

	}

	/** Le script joué comme la ligne de commande le joue. Rend sa sortie. */
	private function runConfigure(): string {

		$path = \Package::getPath('learning').'/page/configure/theme.p.php';

		\Page::includeFile($path);

		ob_start();

		try {
			foreach(\Page::all()[$path] as $page) {
				$page['create'](new \stdClass());
			}
		} catch(\Throwable $e) {
			ob_end_clean();
			throw $e;
		}

		return ob_get_clean();

	}

	/**
	 * La première sous-thématique de la thématique du décor, une autre thématique qui en porte, et
	 * la première de celle-ci. Rend des éléments vides et signale le décor si le script n'a pas été
	 * joué.
	 */
	private function getSubthemes(): array {

		$cSubtheme = SubthemeLib::getByTheme($this->eTheme);

		$eThemeOther = ThemeLib::getAll()->find(fn(Theme $eTheme) => (
			$eTheme['id'] !== $this->eTheme['id'] and
			$eTheme['cSubtheme']->notEmpty()
		))->first();

		if($cSubtheme->empty() or $eThemeOther === NULL) {
			$this->assertFail('décor absent : des thématiques avec leurs sous-thématiques, learning/configure/theme à jouer');
			return [new Subtheme(), new Theme(), new Subtheme()];
		}

		return [$cSubtheme->first(), $eThemeOther, $eThemeOther['cSubtheme']->first()];

	}

	/** La thématique et la sous-thématique passées par `build()`, comme le formulaire les poste. Rend les refus. */
	private function buildSubtheme(Course $eCourse, Theme $eTheme, Subtheme $eSubtheme): array {

		$fw = new \FailWatch();

		$eCourse->build(['theme', 'subtheme'], [
			'theme' => $eTheme->empty() ? '' : (string)$eTheme['id'],
			'subtheme' => $eSubtheme->empty() ? '' : (string)$eSubtheme['id'],
		]);

		return array_column($fw->get(), 1);

	}

	private function createSubtheme(Theme $eTheme, string $name): Subtheme {

		$eSubtheme = new Subtheme();

		$fw = new \FailWatch();

		$eSubtheme->build(SubthemeLib::getPropertiesCreate(), [
			'theme' => (string)$eTheme['id'],
			'name' => $name,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de la sous-thématique refusée : '.json_encode($fw->get())));

		SubthemeLib::create($eSubtheme);

		return $eSubtheme;

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
