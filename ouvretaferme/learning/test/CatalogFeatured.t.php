<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * « Premiers pas » (lot catalogue-attractif, planche v3) : la rangée que l'équipe de Ouvretaferme
 * choisit pour ouvrir le catalogue public, tenue dans l'onglet Catalogue public de
 * l'administration.
 *
 * Chaque geste est joué par sa route (`executePage()`), pour que le droit (`canDecide`) et l'état
 * (`acceptFeature`, `acceptUnfeature`) soient éprouvés avec lui. Les formations du décor sont
 * écrites dans la transaction du test : la base de dev a son propre catalogue, que les assertions
 * laissent de côté en ne regardant que les formations du test.
 */
class CatalogFeaturedTest extends LearningNetworkFixtureTest {

	const REASON = 'zzTest catalogue-attractif : retirée pour le test.';

	/**
	 * Une formation de réseau en ligne naît payée par le réseau, comme dans `CatalogCircuit.t.php` :
	 * l'offre n'est pas le sujet de ce fichier. Une formation de réseau demandée ou acceptée porte la
	 * date de sa demande ou de la décision, que le décor commun ne pose pas faute de passer par
	 * `updateCatalog()` : les listes de l'administration l'affichent.
	 */
	protected function createCourse(\structure\Structure $eStructureCommunity, string $status, string $catalog, bool $classified = TRUE, string $type = Course::ONLINE): Course {

		$eCourse = parent::createCourse($eStructureCommunity, $status, $catalog, $classified, $type);

		if(
			$eCourse->isNetwork() and
			$eCourse->isDated() === FALSE
		) {
			$eCourse['offer'] = Course::NETWORK;
			CourseLib::update($eCourse, ['offer']);
		}

		if(
			$eCourse->isNetwork() and
			$eCourse['catalog'] !== Course::PRIVATE
		) {
			$eCourse['catalogAt'] = \Clock::datetime();
			CourseLib::update($eCourse, ['catalogAt']);
		}

		return $eCourse;

	}

	/**
	 * L'équipe ajoute une formation du catalogue, puis la retire ; chaque geste ne se joue qu'une fois.
	 */
	public function testFeature(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);

		$this->assertFalse($eCourse['featured'], 'une formation naît hors des premiers pas');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourse['id']]), 'l\'équipe l\'ajoute');

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourse['featured'], 'elle est dans les premiers pas');
		$this->assertEquals(\Clock::datetime(), $eCourse['featuredAt'], 'datée de son entrée');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourse['id']]), 'elle ne s\'ajoute pas deux fois');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doUnfeature', ['id' => $eCourse['id']]), 'l\'équipe la retire');

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourse['featured'], 'elle n\'y est plus');
		$this->assertEquals(NULL, $eCourse['featuredAt'], 'et ne garde pas de date');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doUnfeature', ['id' => $eCourse['id']]), 'rien à retirer');

	}

	/**
	 * Seule une formation qui se lit au catalogue entre dans les premiers pas : ni une demande, ni
	 * une formation retenue mais dépubliée. Une formation de l'équipe y entre comme celle d'un réseau.
	 */
	public function testFeatureInCatalogOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseProposed = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PROPOSED);
		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::ACCEPTED);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseProposed['id']]), 'pas une demande');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseDraft['id']]), 'ni une formation dépubliée');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseTeam['id']]), 'une formation de l\'équipe, oui');

	}

	/** Le geste est celui de l'équipe de Ouvretaferme : l'équipe du réseau ne choisit pas. */
	public function testFeatureAdminOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);

		$this->actAs($this->eUserReader);

		$this->assertAdminOnly(fn() => $this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourse['id']]), 'un compte hors de l\'équipe n\'ajoute pas');
		$this->assertAdminOnly(fn() => $this->executeView('learning/admin/catalog:feature', []), 'ni n\'ouvre le panneau');

		$this->assertFalse($this->getCourse($eCourse['id'])['featured'], 'la formation n\'a pas bougé');

	}

	/**
	 * La rangée suit l'ordre d'ajout, pas celui des noms ni des identifiants : la formation créée en
	 * second, ajoutée en premier, ouvre la rangée. Retirée puis ajoutée de nouveau, elle passe en
	 * dernier. L'administration lit le même ordre.
	 */
	public function testOrder(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseFirst = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eCourseSecond = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);

		$this->setClock('2030-10-05 09:00:00');
		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseSecond['id']]);

		$this->setClock('2030-10-05 10:00:00');
		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseFirst['id']]);

		$this->assertEquals([$eCourseSecond['id'], $eCourseFirst['id']], $this->getFeaturedIds([$eCourseFirst, $eCourseSecond]), 'l\'ordre d\'ajout');

		$data = $this->executeView('learning/admin/catalogue', []);
		$this->assertEquals([$eCourseSecond['id'], $eCourseFirst['id']], array_values(array_intersect($data->cCourseFeatured->getIds(), [$eCourseFirst['id'], $eCourseSecond['id']])), 'le même dans l\'administration');

		$this->setClock('2030-10-05 11:00:00');
		$this->executePage('learning/admin/catalog:doUnfeature', ['id' => $eCourseSecond['id']]);
		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseSecond['id']]);

		$this->assertEquals([$eCourseFirst['id'], $eCourseSecond['id']], $this->getFeaturedIds([$eCourseFirst, $eCourseSecond]), 'ajoutée de nouveau, elle passe en dernier');

	}

	/**
	 * Une formation qui quitte le catalogue quitte les premiers pas, retirée par l'équipe comme par
	 * son réseau : y revenir passe par une nouvelle demande, puis un nouveau choix.
	 */
	public function testLeaveCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseRemoved = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eCourseWithdrawn = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);

		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseRemoved['id']]);
		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseWithdrawn['id']]);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/admin/catalog:doRemove', ['id' => $eCourseRemoved['id'], 'catalogReason' => self::REASON]), 'l\'équipe la retire du catalogue');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doCatalogWithdraw', ['id' => $eCourseWithdrawn['id']]), 'le réseau retire la sienne');

		foreach([$eCourseRemoved, $eCourseWithdrawn] as $eCourse) {
			$eCourse = $this->getCourse($eCourse['id']);
			$this->assertFalse($eCourse['featured'], 'hors du catalogue, hors des premiers pas');
			$this->assertEquals(NULL, $eCourse['featuredAt'], 'sans date');
		}

	}

	/**
	 * Une formation dépubliée quitte les premiers pas, comme une formation qui sort du catalogue
	 * (tranché par V-Learning, diff-2) : republiée, elle n'y revient que par un nouveau choix.
	 * Dépublier passe par `doUpdateStatus` sur le web et par `PUT` dans l'API, tous deux par
	 * `CourseLib::update()`, que le test appelle aussi pour le second chemin.
	 */
	public function testUnpublish(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseWeb = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eCourseLib = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseWeb['id']]);
		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseLib['id']]);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateStatus', ['id' => $eCourseWeb['id'], 'status' => Course::DRAFT]), 'l\'équipe la dépublie');

		$eCourseLib = $this->getCourse($eCourseLib['id']);
		$eCourseLib['status'] = Course::DRAFT;
		CourseLib::update($eCourseLib, ['status']);

		foreach([$eCourseWeb, $eCourseLib] as $eCourse) {
			$eCourse = $this->getCourse($eCourse['id']);
			$this->assertEquals(Course::DRAFT, $eCourse['status'], 'en brouillon');
			$this->assertFalse($eCourse['featured'], 'dépubliée, hors des premiers pas');
			$this->assertEquals(NULL, $eCourse['featuredAt'], 'sans date');
		}

		$this->executePage('learning/course:doUpdateStatus', ['id' => $eCourseWeb['id'], 'status' => Course::PUBLISHED]);

		$this->assertFalse($this->getCourse($eCourseWeb['id'])['featured'], 'republiée, elle attend un nouveau choix');

	}

	/**
	 * Sans filtre, une formation des premiers pas ne reparaît pas dans la rangée de sa thématique,
	 * dont « Voir les N formations » la compte pourtant. Un filtre posé retire les premiers pas et
	 * rend la rangée entière, la formation comprise.
	 */
	public function testRows(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		// Une rangée suit l'ordre des noms et s'arrête à trois cartes : nommée pour passer devant les
		// formations de la base de dev, la formation choisie y serait la première si elle n'en était
		// pas écartée.
		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eCourse['name'] = '0 zzTest formation choisie';
		CourseLib::update($eCourse, ['name']);

		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourse['id']]);

		$this->actAs($this->eUserReader);

		$data = $this->executeView('formations', []);

		$this->assertTrue(in_array($eCourse['id'], $data->cCourseFeatured->getIds()), 'elle ouvre le catalogue');
		$this->assertTrue($data->cThemeCatalog->offsetExists($this->eTheme['id']), 'sa thématique a sa rangée');

		$eThemeRow = $data->cThemeCatalog[$this->eTheme['id']];

		$this->assertFalse(in_array($eCourse['id'], $eThemeRow['cCourse']->getIds()), 'elle ne reparaît pas dans sa rangée');
		$this->assertEquals(CourseLib::getCatalog()->find(fn(Course $e) => $e['theme']->is($this->eTheme))->count(), $eThemeRow['courses'], 'la thématique compte toutes ses formations');
		$this->assertTrue($eThemeRow['cCourse']->count() <= CourseLib::CATALOG_ROW, 'trois cartes au plus');

		$data = $this->executeView('formations', ['theme' => $this->eTheme['id']]);

		$this->assertTrue($data->cCourseFeatured->empty(), 'filtrée, pas de premiers pas');
		$this->assertTrue(in_array($eCourse['id'], $data->cThemeCatalog[$this->eTheme['id']]['cCourse']->getIds()), 'la rangée entière, elle comprise');
		$this->assertFalse($data->cThemeCatalog[$this->eTheme['id']]['more'], 'sans « Voir les N formations »');

	}

	/**
	 * L'administration liste les premiers pas, avec « Ajouter » et la roue « Retirer ». Le panneau
	 * « Ajouter » propose les formations du catalogue qui n'y sont pas encore.
	 */
	public function testAdmin(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseFeatured = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eCourseProposed = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PROPOSED);

		$this->executePage('learning/admin/catalog:doFeature', ['id' => $eCourseFeatured['id']]);

		$data = $this->executeView('learning/admin/catalog:feature', []);

		$this->assertFalse(in_array($eCourseFeatured['id'], $data->cCourse->getIds()), 'le panneau ne propose pas une formation déjà choisie');
		$this->assertTrue(in_array($eCourseOther['id'], $data->cCourse->getIds()), 'il propose une formation du catalogue');

		$panel = new CatalogUi()->feature($data->cCourse)->body;
		$this->assertTrue(str_contains($panel, '<option value="'.$eCourseOther['id'].'">'.encode($eCourseOther['name']).'</option>'), 'chaque formation sous son nom : '.$panel);
		$this->assertFalse(in_array($eCourseProposed['id'], $data->cCourse->getIds()), 'pas une demande');

		$data = $this->executeView('learning/admin/catalogue', []);
		$html = new CatalogUi()->getAdmin($data->cCourseProposed, $data->cCourseAccepted, $data->cCourseFeatured, $data->cCourseRefused);

		$this->assertTrue(str_contains($html, 'href="/learning/admin/catalog:feature"'), '« Ajouter » : '.$html);
		$this->assertTrue(str_contains($html, 'data-ajax="/learning/admin/catalog:doUnfeature" post-id="'.$eCourseFeatured['id'].'"'), 'la roue « Retirer des premiers pas »');

	}

	/**
	 * Une carte sans vignette porte l'ami de sa thématique, et son rang dans la rangée l'alterne avec
	 * le second. Chaque icône de thématique a ses deux amis.
	 */
	public function testFriend(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals(Theme::ICONS, array_keys(ThemeUi::FRIENDS), 'un couple d\'amis par icône de thématique');

		foreach(ThemeUi::FRIENDS as $icon => $friends) {
			foreach($friends as $friend) {
				$this->assertTrue(is_file(\Package::getPath('main').'/static/image/friends/'.$friend.'.svg'), $icon.' : '.$friend.'.svg existe');
			}
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->getCourse($this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED)['id']);

		if($this->assertDecorPresent($eCourse['vignette'] === NULL, 'une formation sans vignette') === FALSE) {
			return;
		}

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);
		CourseLib::fillCounts($cCourse);
		ProgressLib::fillDone($this->eUserAdmin, $cCourse);

		$friends = ThemeUi::FRIENDS[$this->eTheme['icon']];

		$first = new CourseUi()->getCard($eCourse, FALSE, FALSE, position: 0);
		$second = new CourseUi()->getCard($eCourse, FALSE, FALSE, position: 1);

		$this->assertTrue(str_contains($first, \Asset::svg('main', 'friends/'.$friends[0].'.svg')), 'le premier ami au premier rang : '.$first);
		$this->assertTrue(str_contains($second, \Asset::svg('main', 'friends/'.$friends[1].'.svg')), 'le second au rang suivant');

	}

	/**
	 * @param Course[] $courses
	 * @return int[]
	 */
	private function getFeaturedIds(array $courses): array {

		$data = $this->executeView('formations', []);

		$ids = array_map(fn(Course $eCourse) => $eCourse['id'], $courses);

		return array_values(array_intersect($data->cCourseFeatured->getIds(), $ids));

	}

	private function assertAdminOnly(\Closure $execute, string $message): void {

		try {
			$execute();
			$this->assertFail($message.' : la route a répondu');
		} catch(\DisabledPage) {
			$this->assertTrue(TRUE, $message);
		}

	}

}
?>
