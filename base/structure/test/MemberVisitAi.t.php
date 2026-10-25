<?php
namespace structure;

/**
 * `visitedAt`, que `MemberLib::visit()` écrit et qui range la liste des structures de « / » par la
 * dernière visite. La contrainte que `visit()` tient seule est de n'écrire **qu'une fois par jour** :
 * elle pose la date à la première visite, ne la réécrit pas le même jour, et la repose le lendemain.
 * L'horloge est figée par `setClock()`, sans quoi le contrôle dépendrait de l'heure à laquelle il
 * tourne.
 *
 * Chaque contrôle qui attend « rien n'a été écrit » est précédé de son pendant qui écrit : un test
 * qui ne trouve rien ne prouve rien tant qu'il n'a pas montré qu'il sait trouver.
 *
 * Décor : le compte `decor.c.admin@example.org` du décor des tests (`FixtureAiLib`), qui adhère à
 * plusieurs structures, et `decor.sans.structure@example.org`, qui n'adhère à aucune. Isolation : la
 * transaction de `\dev\TransactionTest`, annulée après chaque méthode. Le point de départ est
 * **remis** par `resetDecor()` et non supposé : une visite posée par un usage réel du compte est un
 * état légitime, et la base de dev est partagée.
 *
 * Lancement : dev/test package=structure file=MemberVisitAi
 */
class MemberVisitAiTest extends \dev\TransactionTest {

	/** Le compte du décor qui adhère à plusieurs structures */
	const EMAIL_USER = 'decor.c.admin@example.org';

	/** Le compte du décor qui n'adhère à aucune structure */
	const EMAIL_USER_WITHOUT = 'decor.sans.structure@example.org';

	/** Deux structures du décor auxquelles EMAIL_USER adhère */
	const STRUCTURE_FIRST = FixtureAiLib::F6;
	const STRUCTURE_SECOND = FixtureAiLib::F8;

	private \user\User $eUser;
	private \user\User $eUserWithout;

	/** L'état à remettre : le compte connecté vit dans un cache de processus, que la transaction n'annule pas. */
	private \user\User $eUserInitial;

	/** Les écarts du décor, quand il n'est pas en place */
	private ?string $decorFailure = NULL;

	protected function getTransactionModel(): \ModuleModel {
		return Member::model();
	}

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		// ⚠️ Une exception qui sort de `init()` interrompt le fichier sans bilan : le refus se garde
		// pour que chaque contrôle le rende en échec nommé par `assertFixtures()`.
		try {
			$this->readDecor();
		} catch(\Throwable $e) {
			$this->decorFailure = $e->getMessage();
		}

	}

	public function setUp(): void {

		parent::setUp();

		$this->resetDecor();

		MemberLib::clearCache();

	}

	/**
	 * Le point de départ, remis dans la transaction au lieu d'être supposé. Une visite est un état
	 * légitime que tout usage réel du compte du décor laisse derrière lui, et la base de dev est
	 * partagée par les sessions et par les exercices dans le navigateur : un contrôle qui exige une
	 * table vierge est faux par construction, et il l'est chez tous ceux qui tirent.
	 */
	private function resetDecor(): void {

		if($this->decorFailure !== NULL) {
			return;
		}

		$cMember = Member::model()
			->select(['id', 'visitedAt'])
			->whereUser($this->eUser)
			->getCollection();

		foreach($cMember as $eMember) {

			if($eMember['visitedAt'] === NULL) {
				continue;
			}

			$eMember['visitedAt'] = NULL;

			// Sans `build()` : `visitedAt` n'est pas une propriété postée, seule `visit()` l'écrit dans
			// le produit (dispense du CLAUDE.md pour un `.t.php`).
			MemberLib::update($eMember, ['visitedAt']);

		}

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);
		MemberLib::clearCache();

		parent::tearDown();

	}

	public function finalize(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);
		MemberLib::clearCache();

	}

	/** La première visite pose la date, à l'horloge figée et non à celle de la machine. */
	public function testVisitWritesTheFirstTime(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-06-15 10:00:00');

		$this->connect($this->eUser);

		$this->assertEquals(NULL, $this->getMember(self::STRUCTURE_FIRST)['visitedAt'], 'le décor part sans visite');

		MemberLib::visit($this->getStructure(self::STRUCTURE_FIRST));

		$this->assertEquals('2026-06-15 10:00:00', $this->getMember(self::STRUCTURE_FIRST)['visitedAt']);
		$this->assertEquals(NULL, $this->getMember(self::STRUCTURE_SECOND)['visitedAt'], 'et les autres structures ne sont pas datées');

	}

	/**
	 * Une fois par jour : la seconde visite du même jour ne réécrit rien, celle du lendemain réécrit.
	 * Les deux sont dans la même méthode, pour qu'on ne puisse pas lire l'une sans l'autre.
	 */
	public function testVisitWritesOncePerDay(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->connect($this->eUser);

		$this->setClock('2026-06-15 10:00:00');
		MemberLib::visit($this->getStructure(self::STRUCTURE_FIRST));

		$this->assertEquals('2026-06-15 10:00:00', $this->getMember(self::STRUCTURE_FIRST)['visitedAt'], 'première visite');

		$this->setClock('2026-06-15 23:30:00');
		MemberLib::visit($this->getStructure(self::STRUCTURE_FIRST));

		$this->assertEquals('2026-06-15 10:00:00', $this->getMember(self::STRUCTURE_FIRST)['visitedAt'], 'même jour, treize heures plus tard : rien n\'est réécrit');

		$this->setClock('2026-06-16 08:00:00');
		MemberLib::visit($this->getStructure(self::STRUCTURE_FIRST));

		$this->assertEquals('2026-06-16 08:00:00', $this->getMember(self::STRUCTURE_FIRST)['visitedAt'], 'le lendemain, la date est reposée');

	}

	/**
	 * Qui n'adhère pas ne date rien. Le contrôle est précédé d'une visite qui écrit, sans quoi un
	 * `NULL` ne dirait pas si la garde a joué ou si la méthode ne sait rien écrire.
	 */
	public function testVisitIgnoresANonMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-06-15 10:00:00');

		$this->connect($this->eUser);
		MemberLib::visit($this->getStructure(self::STRUCTURE_FIRST));

		$this->assertEquals('2026-06-15 10:00:00', $this->getMember(self::STRUCTURE_FIRST)['visitedAt'], 'un membre date sa visite');

		$this->connect($this->eUserWithout);
		MemberLib::visit($this->getStructure(self::STRUCTURE_SECOND));

		$this->assertEquals(0, Member::model()->whereUser($this->eUserWithout)->count(), 'le compte sans structure n\'a aucune adhésion');
		$this->assertEquals(NULL, $this->getMember(self::STRUCTURE_SECOND)['visitedAt'], 'et sa visite n\'a daté l\'adhésion de personne');

	}

	private function assertFixtures(): bool {

		if($this->decorFailure !== NULL) {
			$this->assertFail('décor des tests absent ou dérivé : '.$this->decorFailure.'. Le décor se monte par structure/configure/fixtureAi write=1');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Les comptes du décor et leurs adhésions. Les identifiants d'utilisateur changent d'une base à
	 * l'autre : ils se lisent par le courriel.
	 */
	private function readDecor(): void {

		$structures = [self::STRUCTURE_FIRST, self::STRUCTURE_SECOND];

		$gaps = FixtureAiLib::check($structures);

		if($gaps !== []) {
			throw new \Exception(implode(' ; ', $gaps));
		}

		$cUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('IN', [self::EMAIL_USER, self::EMAIL_USER_WITHOUT])
			->getCollection(index: 'email');

		foreach([self::EMAIL_USER, self::EMAIL_USER_WITHOUT] as $email) {

			if($cUser->offsetExists($email) === FALSE) {
				throw new \Exception('le compte '.$email.' est absent');
			}

		}

		$this->eUser = $cUser[self::EMAIL_USER];
		$this->eUserWithout = $cUser[self::EMAIL_USER_WITHOUT];

		foreach($structures as $structure) {

			if(MemberLib::getByUserAndStructure($this->eUser, new Structure(['id' => $structure]))->empty()) {
				throw new \Exception(self::EMAIL_USER.' n\'adhère pas à la structure '.$structure);
			}

		}

	}

	/** Le compte connecté vit dans deux caches de processus : celui de la session et celui des adhésions. */
	private function connect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		MemberLib::clearCache();

	}

	private function getStructure(int $structure): Structure {

		return Structure::model()
			->select(Structure::getSelection())
			->whereId($structure)
			->get();

	}

	private function getMember(int $structure): Member {

		return Member::model()
			->select(['id', 'structure', 'visitedAt'])
			->whereUser($this->eUser)
			->whereStructure($structure)
			->get();

	}

}
?>
