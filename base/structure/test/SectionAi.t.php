<?php
namespace structure;

/**
 * Les sous-groupes, dont l'appartenance est une liste d'ids d'utilisateur rangée dans une colonne
 * `json`.
 *
 * C'est cette forme qui est éprouvée ici, et rien d'autre : un id dans du `json` n'est ni une clé
 * étrangère ni un index, personne ne le retire à notre place, et le filtre qui le cherche ne peut
 * pas être un `LIKE`. Trois choses s'y prouvent :
 *
 * 1. l'appartenance s'écrit sans doublon et se relit ;
 * 2. la lecture distingue 12 de 120 et de 112 — un `LIKE '%12%'` rendrait les trois ;
 * 3. le retrait borne sa portée : un sous-groupe nommé, ou tous ceux du groupe, jamais ceux d'un
 *    autre groupe, et la suppression d'un sous-groupe libère les invitations qui le visaient.
 *
 * Chaque contrôle qui attend un ensemble vide est précédé de son pendant non vide : un test qui ne
 * trouve rien ne prouve rien tant qu'il n'a pas montré qu'il sait trouver.
 *
 * Décor : les sous-groupes se rangent dans les deux groupes partageants du décor des tests, G4 et
 * G6, le second bornant la portée des retraits. Isolation : une transaction par
 * méthode, annulée en tearDown(). Les sous-groupes sont créés par le test.
 *
 * Lancement : dev/test package=structure file=SectionAi
 */
class SectionAiTest extends CommunityFixtureAiTest {

	/**
	 * Ids d'utilisateur choisis pour le piège du `LIKE` : 12 est une sous-chaîne de 120 et de 112.
	 * Ils n'ont pas à exister en base, la liste ne portant que des entiers.
	 */
	const USER_SUBSTRING = 12;
	const USER_SUFFIX = 120;
	const USER_PREFIX = 112;

	public function setUp(): void {
		Section::model()->beginTransaction();
	}

	public function tearDown(): void {
		Section::model()->rollBack();
	}

	private function getUser(int $id): \user\User {
		return new \user\User(['id' => $id]);
	}

	private function createSection(int $structure, string $name): Section {

		$eSection = new Section([
			'structure' => $this->getStructure($structure),
			'name' => $name,
			'status' => Section::OPEN
		]);

		SectionLib::create($eSection);

		return $eSection;

	}

	private function getSection(Section $eSection): Section {

		return Section::model()
			->select(Section::getSelection())
			->whereId($eSection)
			->get();

	}

	public function testAssociateIsIdempotent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eSection = $this->createSection($this->communitySharing, 'Promotion 2026');
		$eUser = $this->getUser(self::USER_SUBSTRING);

		$this->assertEquals([], $this->getSection($eSection)['users']);

		SectionLib::associateUser($eSection, $eUser);

		$this->assertEquals([self::USER_SUBSTRING], $this->getSection($eSection)['users']);

		// Le second appel ne doit rien ajouter : l'écriture se garde elle-même par un
		// JSON_CONTAINS = 0, jamais par une relecture de la liste.
		SectionLib::associateUser($eSection, $eUser);

		$this->assertEquals([self::USER_SUBSTRING], $this->getSection($eSection)['users']);

	}

	/** Le point qui interdit un LIKE : 12 se retrouve dans 120 et dans 112. */
	public function testReadDoesNotMatchSubstring(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$eSectionSuffix = $this->createSection($this->communitySharing, 'Promotion 120');
		$eSectionPrefix = $this->createSection($this->communitySharing, 'Promotion 112');

		SectionLib::associateUser($eSectionSuffix, $this->getUser(self::USER_SUFFIX));
		SectionLib::associateUser($eSectionPrefix, $this->getUser(self::USER_PREFIX));

		// Le contrôle sait trouver : chacun des deux se retrouve depuis son propre id.
		$this->assertEquals([$eSectionSuffix['id']], SectionLib::getByUser($this->getUser(self::USER_SUFFIX), $eStructure)->getIds());
		$this->assertEquals([$eSectionPrefix['id']], SectionLib::getByUser($this->getUser(self::USER_PREFIX), $eStructure)->getIds());

		// Et il ne trouve pas 12, qui n'a été rangé nulle part.
		$this->assertTrue(SectionLib::getByUser($this->getUser(self::USER_SUBSTRING), $eStructure)->empty());

	}

	public function testReadFindsEverySection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$eSectionFirst = $this->createSection($this->communitySharing, 'Promotion A');
		$eSectionSecond = $this->createSection($this->communitySharing, 'Groupe de travail B');
		$eSectionOther = $this->createSection($this->communitySharing, 'Promotion C');

		$eUser = $this->getUser(self::USER_SUBSTRING);

		SectionLib::associateUser($eSectionFirst, $eUser);
		SectionLib::associateUser($eSectionSecond, $eUser);

		// Une personne appartient à autant de sous-groupes qu'il le faut : le rangement ne ferme rien.
		$cSection = SectionLib::getByUser($eUser, $eStructure);

		$this->assertEquals(2, $cSection->count());
		$this->assertTrue($cSection->offsetExists($eSectionFirst['id']));
		$this->assertTrue($cSection->offsetExists($eSectionSecond['id']));
		$this->assertFalse($cSection->offsetExists($eSectionOther['id']));

	}

	public function testDissociateFromOneSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$eSectionFirst = $this->createSection($this->communitySharing, 'Promotion A');
		$eSectionSecond = $this->createSection($this->communitySharing, 'Promotion B');

		$eUser = $this->getUser(self::USER_SUBSTRING);

		SectionLib::associateUser($eSectionFirst, $eUser);
		SectionLib::associateUser($eSectionSecond, $eUser);

		SectionLib::dissociateUser($eUser, $eStructure, $eSectionFirst);

		$this->assertEquals([], $this->getSection($eSectionFirst)['users']);
		$this->assertEquals([self::USER_SUBSTRING], $this->getSection($eSectionSecond)['users']);

	}

	/**
	 * Le retrait sans sous-groupe nommé vide tous ceux du groupe : c'est ce qu'appelle
	 * MemberLib::delete(). Il doit écrire sur plusieurs lignes, là où un Element passé à update()
	 * y ajouterait un AND id = <id> et n'en toucherait qu'une.
	 */
	public function testDissociateFromEverySection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$eSectionFirst = $this->createSection($this->communitySharing, 'Promotion A');
		$eSectionSecond = $this->createSection($this->communitySharing, 'Promotion B');

		$eUser = $this->getUser(self::USER_SUBSTRING);

		SectionLib::associateUser($eSectionFirst, $eUser);
		SectionLib::associateUser($eSectionSecond, $eUser);

		$this->assertEquals(2, SectionLib::getByUser($eUser, $eStructure)->count());

		SectionLib::dissociateUser($eUser, $eStructure);

		$this->assertTrue(SectionLib::getByUser($eUser, $eStructure)->empty());
		$this->assertEquals([], $this->getSection($eSectionFirst)['users']);
		$this->assertEquals([], $this->getSection($eSectionSecond)['users']);

	}

	/** Deux groupes peuvent ranger la même personne : le retrait ne déborde pas sur l'autre. */
	public function testDissociateStaysInStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eSection = $this->createSection($this->communitySharing, 'Promotion A');
		$eSectionBis = $this->createSection($this->communitySharingBis, 'Promotion A');

		$eUser = $this->getUser(self::USER_SUBSTRING);

		SectionLib::associateUser($eSection, $eUser);
		SectionLib::associateUser($eSectionBis, $eUser);

		SectionLib::dissociateUser($eUser, $this->getStructure($this->communitySharing));

		$this->assertEquals([], $this->getSection($eSection)['users']);
		$this->assertEquals([self::USER_SUBSTRING], $this->getSection($eSectionBis)['users']);

	}

	/** Le même nom se réutilise d'un groupe à l'autre, jamais deux fois dans le même. */
	public function testNameIsUniquePerStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->createSection($this->communitySharing, 'Promotion A');
		$this->createSection($this->communitySharingBis, 'Promotion A');

		$fw = new \FailWatch();

		$this->createSection($this->communitySharing, 'Promotion A');

		$this->assertTrue($fw->has('Section::name.duplicate'), json_encode($fw->get()));

	}

	/**
	 * L'invitation en attente perd sa destination et reste valable : elle fait entrer dans le
	 * groupe, le sous-groupe n'en était que le rangement. Aucune clé étrangère réelle dans ce
	 * schéma, donc rien ne le ferait à la place de SectionLib::delete().
	 */
	public function testDeleteReleasesInvites(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eSection = $this->createSection($this->communitySharing, 'Promotion A');
		$eSectionOther = $this->createSection($this->communitySharing, 'Promotion B');

		// Fixture posée par le modèle : InviteLib::create() envoie un courriel, que ce contrôle
		// n'a pas à déclencher pour observer une colonne.
		$eInvite = new Invite([
			'structure' => $this->getStructure($this->communitySharing),
			'type' => Invite::MEMBER,
			'memberSection' => $eSection
		]);

		Invite::model()->insert($eInvite);

		$eInviteOther = new Invite([
			'structure' => $this->getStructure($this->communitySharing),
			'type' => Invite::MEMBER,
			'memberSection' => $eSectionOther
		]);

		Invite::model()->insert($eInviteOther);

		SectionLib::delete($eSection);

		$cInvite = Invite::model()
			->select(['id', 'memberSection', 'status'])
			->whereId('IN', [$eInvite['id'], $eInviteOther['id']])
			->getCollection(index: 'id');

		$this->assertTrue($cInvite[$eInvite['id']]['memberSection']->empty());
		$this->assertEquals(Invite::PENDING, $cInvite[$eInvite['id']]['status']);

		// Le contrôle sait trouver une destination : celle de l'autre invitation n'a pas bougé.
		$this->assertEquals($eSectionOther['id'], $cInvite[$eInviteOther['id']]['memberSection']['id']);

	}

	/**
	 * Le POST est contournable : build() n'accepte que des utilisateurs du groupe, et le contrôle
	 * vit sur l'élément pour que l'API l'emprunte aussi.
	 */
	public function testBuildKeepsOnlyStructureUsers(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$users = Member::model()
			->whereStructure($eStructure)
			->whereStatus(Member::IN)
			->getColumn('user')
			->getIds();

		$this->assertTrue($users !== []);

		$eSection = $this->createSection($this->communitySharing, 'Promotion A');
		$eSection['structure'] = $eStructure;

		$fw = new \FailWatch();

		// Le contrôle sait accepter : un utilisateur du groupe passe, mêlé à un intrus.
		$eSection->build(['users'], ['users' => [(string)$users[0], '999999999']], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals([$users[0]], $eSection['users']);

	}

}
?>
