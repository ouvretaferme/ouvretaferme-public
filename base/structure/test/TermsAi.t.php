<?php
namespace structure;

/**
 * L'acceptation des CGV par une structure (chantier marketplace, lot A) : la case refusée sans être cochée, une seule
 * ligne par version malgré un double envoi, la dernière version acceptée, et une version nouvelle qui ne retire rien.
 *
 * Décor : la structure F1 et son administratrice, lues dans le décor des tests ; l'acceptation est écrite dans la
 * transaction de la méthode, annulée ensuite. Aucun appel à Stripe.
 *
 * Lancement : dev/test package=structure file=TermsAi
 */
class TermsAiTest extends \dev\TransactionTest {

	private const EMAIL_ADMIN = 'decor.f1.admin@example.org';

	private Structure $eStructure;
	private \user\User $eUser;

	private \user\User $eUserInitial;

	private array $terms;

	public function init(): void {

		$this->eStructure = StructureLib::getById(FixtureAiLib::F1);

		$this->eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(self::EMAIL_ADMIN)
			->get();

		$this->terms = StructureSetting::$terms;

	}

	protected function getTransactionModel(): \ModuleModel {
		return Terms::model();
	}

	public function setUp(): void {

		parent::setUp();

		StructureSetting::$terms = $this->terms;

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		if($this->eUser->notEmpty()) {
			\user\ConnectionLib::setOnline($this->eUser);
		}

		// Chaque cas part d'une F1 sans acceptation. Le produit ne retire jamais une acceptation, qui est une preuve :
		// aucun Lib ne le fait, l'effacement passe donc par le modèle, dans la transaction annulée au tearDown()
		Terms::model()
			->whereStructure($this->eStructure)
			->delete();

	}

	// L'utilisateur connecté est une propriété du processus, que le rollback n'annule pas : il suivrait les fichiers suivants
	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	private function assertDecor(): bool {

		return (
			$this->assertDecorPresent($this->eStructure->notEmpty(), 'la structure F1 du décor') and
			$this->assertDecorPresent($this->eUser->notEmpty(), 'le compte '.self::EMAIL_ADMIN)
		);

	}

	private function countTerms(): int {

		return Terms::model()
			->whereStructure($this->eStructure)
			->count();

	}

	public function testRefusedWithoutCheckbox(): void {

		if($this->assertDecor() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		try {
			TermsAiLib::acceptFromInput($this->eStructure, []);
			$this->assertFail('la case non cochée aurait dû être refusée');
		} catch(\FailAction) {
			$this->assertTrue($fw->has('Terms::terms.check'), 'le refus porte la clé Terms::terms.check : '.$fw);
		}

		$this->assertEquals(0, $this->countTerms(), 'rien n\'est enregistré');

		foreach([['terms' => '0'], ['terms' => ''], ['terms' => ['1']]] as $input) {

			try {
				TermsAiLib::acceptFromInput($this->eStructure, $input);
				$this->assertFail('une case postée à '.json_encode($input['terms']).' aurait dû être refusée');
			} catch(\FailAction) {
				$this->assertEquals(0, $this->countTerms(), 'une case postée à '.json_encode($input['terms']).' ne vaut pas accord');
			}

		}
		$this->assertFalse(TermsAiLib::hasAccepted($this->eStructure), 'la structure n\'a rien accepté');

	}

	public function testRefusedWhenLoggedAsSomeoneElse(): void {

		if($this->assertDecor() === FALSE) {
			return;
		}

		// Une connexion « en tant que » se reconnaît à l'utilisateur d'origine gardé en session
		\session\SessionLib::set('userOld', $this->eUser['id']);

		$fw = new \FailWatch();

		try {
			TermsAiLib::acceptFromInput($this->eStructure, ['terms' => '1']);
			$this->assertFail('une case cochée sous une connexion « en tant que » aurait dû être refusée');
		} catch(\FailAction) {
			$this->assertTrue($fw->has('Terms::terms.external'), 'le refus porte la clé Terms::terms.external : '.$fw);
		} finally {
			\session\SessionLib::delete('userOld');
		}

		$this->assertEquals(0, $this->countTerms(), 'aucune acceptation n\'est prêtée à l\'administrateur');

	}

	public function testAcceptedOnceDespiteDoubleSubmit(): void {

		if($this->assertDecor() === FALSE) {
			return;
		}

		TermsAiLib::acceptFromInput($this->eStructure, ['terms' => '1']);
		TermsAiLib::acceptFromInput($this->eStructure, ['terms' => '1']);

		$this->assertEquals(1, $this->countTerms(), 'un double envoi ne garde qu\'une acceptation');
		$this->assertTrue(TermsAiLib::hasAccepted($this->eStructure), 'la structure a accepté');

		$eTerms = TermsAiLib::getLast($this->eStructure);

		$this->assertEquals(TermsAiLib::getLatestVersion(), $eTerms['version'], 'la version acceptée est la dernière déclarée');
		$this->assertEquals($this->eUser['id'], $eTerms['user']['id'], 'l\'acceptation est au nom de qui a coché');
		$this->assertEquals('Alice', $eTerms['user']['firstName'], 'le nom de qui a coché se lit pour l\'écran');

	}

	public function testNewVersionKeepsAcceptance(): void {

		if($this->assertDecor() === FALSE) {
			return;
		}

		TermsAiLib::accept($this->eStructure);

		$versionAccepted = TermsAiLib::getLatestVersion();

		StructureSetting::$terms[$versionAccepted + 1] = '2027-06-01';

		$this->assertTrue(TermsAiLib::hasAccepted($this->eStructure), 'une version nouvelle ne retire pas l\'acceptation de la précédente (R-08)');
		$this->assertEquals($versionAccepted, TermsAiLib::getLast($this->eStructure)['version'], 'la version acceptée reste lisible');

		TermsAiLib::accept($this->eStructure);

		$this->assertEquals(2, $this->countTerms(), 'la version nouvelle s\'accepte à côté de la précédente');
		$this->assertEquals($versionAccepted + 1, TermsAiLib::getLast($this->eStructure)['version'], 'la dernière acceptée se lit en premier');

	}

	public function testOtherStructureUntouched(): void {

		if($this->assertDecor() === FALSE) {
			return;
		}

		TermsAiLib::accept($this->eStructure);

		$this->assertFalse(TermsAiLib::hasAccepted(StructureLib::getById(FixtureAiLib::F2)), 'l\'acceptation n\'engage que sa structure');
		$this->assertTrue(TermsAiLib::getLast(StructureLib::getById(FixtureAiLib::F2))->empty(), 'une structure sans acceptation rend un élément vide');

	}

}
?>
