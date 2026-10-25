<?php
namespace structure;

/**
 * Une astuce déjà montrée ne défait pas la transaction de qui la demande.
 *
 * `TipLib::changeStatus()` refermait sa transaction par `rollBack()` quand l'astuce avait déjà été
 * vue. `Module::rollBack()` remet toute la pile à zéro : appelée sous une transaction ouverte, elle
 * l'annulait et rendait la main sans rien lever, et tout ce que l'appelant écrivait ensuite partait
 * hors transaction. Relevé le 2026-10-12 sous la page des campagnes (`base/mail/page/index.p.php`,
 * qui demande une astuce), rendue par `amap/test/CampaignAmapAi` dans la transaction de son décor.
 *
 * Lancement : dev/test package=structure file=TipTransactionAi
 */
class TipTransactionAiTest extends \dev\TransactionTest {

	/** Le compte du décor qui n'appartient à aucune structure, dont seule la liste d'astuces change. */
	const USER = 'decor.sans.structure@example.org';

	const TIP = 'sequence-weeks';

	protected function getTransactionModel(): \ModuleModel {
		return Tip::model();
	}

	public function testATipAlreadyShownLeavesTheCallerTransactionOpen(): void {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(self::USER)
			->get();

		if($this->assertDecorPresent($eUser->notEmpty(), 'le décor porte le compte '.self::USER) === FALSE) {
			return;
		}

		TipLib::changeStatus($eUser, self::TIP, 'shown');

		$this->assertEquals(NULL, TipLib::pickOne($eUser, self::TIP, closedByUser: FALSE), 'une astuce déjà montrée n\'est pas proposée une seconde fois');
		$this->assertTrue(Tip::model()->inTransaction(), 'la transaction de l\'appelant est encore ouverte après le refus');

	}

}
?>
