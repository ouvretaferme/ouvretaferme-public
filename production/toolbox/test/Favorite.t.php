<?php
namespace toolbox;

/**
 * Les favoris de la Boîte à outils : personnels (arbitrage du 22/09, « par personne »),
 * un par outil, et ce que le menu en lit.
 *
 * Le plafond de trois (`Favorite::tool.tooMany`) n'est pas éprouvé ici : l'enum ne compte encore
 * qu'un outil et l'unicité (structure, user, tool) interdit d'en poser un second, il n'y a donc
 * aucun moyen honnête d'atteindre trois favoris. Le test viendra avec le deuxième outil.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=Favorite
 */
class FavoriteTest extends \Test {

	public function setUp(): void {
		Favorite::model()->beginTransaction();
	}

	public function tearDown(): void {
		Favorite::model()->rollBack();
	}

	private function builds(Favorite $eFavorite, array $input): array {

		$fw = new \FailWatch();
		$eFavorite->build(['tool'], $input);

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	private function getMember(): array {

		$users = Favorite::model()
			->select('user')
			->getCollection()
			->getColumnCollection('user')
			->getIds();

		$eMember = \structure\Member::model()
			->select(['structure', 'user'])
			->whereUser('!=', NULL)
			->whereUser('NOT IN', $users, if: $users)
			->sort(['id' => SORT_ASC])
			->get();

		$this->assertTrue($eMember->notEmpty(), 'un membre');

		// La structure entière : lire les favoris demande si chaque outil lui est ouvert
		// (`Favorite::isOpen()`), ce qui lit son type.
		return [\structure\StructureLib::getById($eMember['structure']['id']), $eMember['user']];

	}

	public function testToolMustBeInTheEnum(): void {

		[$eStructure, $eUser] = $this->getMember();

		$eFavorite = new Favorite(['structure' => $eStructure, 'user' => $eUser]);

		$this->assertEquals(['Favorite::tool.check'], $this->builds($eFavorite, ['tool' => 'compost']), 'un outil hors de l\'enum est refusé');
		$this->assertEquals([], $this->builds($eFavorite, ['tool' => Favorite::RAIN]), 'la pluviométrie passe');

	}

	public function testFavoritesArePersonal(): void {

		[$eStructure, $eUser] = $this->getMember();

		FavoriteLib::create(new Favorite(['structure' => $eStructure, 'user' => $eUser, 'tool' => Favorite::RAIN]));

		$this->assertEquals(1, FavoriteLib::countByStructure($eStructure, $eUser), 'le favori de la personne');

		$eUserOther = \user\User::model()
			->select('id')
			->whereId('!=', $eUser['id'])
			->get();

		$this->assertEquals(0, FavoriteLib::countByStructure($eStructure, $eUserOther), 'une autre personne de la même structure ne le voit pas');

		\user\ConnectionLib::setOnline($eUser);

		$this->assertEquals([Favorite::RAIN], FavoriteLib::getByStructure($eStructure)->getKeys(), 'le menu le lit, indexé par outil');

		\user\ConnectionLib::setOnline($eUserOther);

		$this->assertEquals([], FavoriteLib::getByStructure($eStructure)->getKeys(), 'et seulement pour elle');

	}

	/**
	 * Le favori orphelin : une personne a mis les paniers à son menu quand ils lui étaient ouverts,
	 * puis l'outil se ferme à elle. Le favori reste en base, le menu ne le lit plus, il ne prend pas de
	 * place dans le plafond, et le sous-menu de la Boîte ne mène à aucune page refusée.
	 *
	 * Les paniers s'ouvrent à toute structure productrice (`Structure::acceptComposition()`) : la
	 * fermeture se monte sur une structure qui ne l'est pas.
	 */
	public function testClosedToolLeavesTheMenu(): void {

		$users = Favorite::model()
			->select('user')
			->getCollection()
			->getColumnCollection('user')
			->getIds();

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(\structure\Structure::model(), 'm1.structure = m3.id')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.type', '!=', \structure\Structure::PRODUCER)
			->where('m3.status', \structure\Structure::ACTIVE)
			->where('m1.user', 'NOT IN', $users, if: $users)
			->sort(['m1.structure' => SORT_ASC])
			->get();

		$this->assertTrue($eMember->notEmpty(), 'un administrateur d\'une structure qui n\'est pas productrice');

		$eUser = $eMember['user'];
		\user\ConnectionLib::setOnline($eUser);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$this->assertEquals([FALSE, TRUE], [$eStructure->acceptComposition(), $eStructure->canManage()], 'le cas est monté : un gestionnaire, dans une structure à qui les paniers sont fermés');
		$this->assertEquals([FALSE, TRUE], [Favorite::isOpen($eStructure, Favorite::BASKET), Favorite::isOpen($eStructure, Favorite::RAIN)], 'les paniers lui sont fermés, la pluviométrie ouverte');

		$this->assertEquals(['Favorite::tool.closed'], $this->builds(new Favorite(['structure' => $eStructure, 'user' => $eUser]), ['tool' => Favorite::BASKET]), 'les paniers ne se mettent plus au menu');

		// Sans build() : il refuserait ce favori (`tool.closed`, au-dessus), et c'est l'état qu'on
		// éprouve, celui d'un favori écrit quand l'outil était ouvert.
		FavoriteLib::create(new Favorite(['structure' => $eStructure, 'user' => $eUser, 'tool' => Favorite::BASKET]));
		FavoriteLib::create(new Favorite(['structure' => $eStructure, 'user' => $eUser, 'tool' => Favorite::RAIN]));

		$this->assertEquals(2, Favorite::model()->whereStructure($eStructure)->whereUser($eUser)->count(), 'les deux favoris sont en base');
		$this->assertEquals([Favorite::RAIN], FavoriteLib::getByStructure($eStructure)->getKeys(), 'le menu ne lit que l\'outil ouvert');
		$this->assertEquals(1, FavoriteLib::countByStructure($eStructure, $eUser), 'et le favori fermé ne compte pas dans le plafond');

		$tab = new \structure\MenuUi()->getToolboxTab($eStructure, 'toolbox', NULL);

		$this->assertEquals(
			[TRUE, FALSE],
			[str_contains($tab, 'href="'.RainUi::url($eStructure).'"'), str_contains($tab, CompositionUi::url($eStructure))],
			'le sous-menu de la Boîte mène à la pluviométrie, pas aux paniers'
		);

		$this->assertTrue(FavoriteLib::getOne($eStructure, Favorite::BASKET)->notEmpty(), 'le favori fermé se lit encore un par un, pour que l\'étoile puisse le retirer');

	}

}
?>
