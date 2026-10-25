<?php
namespace dev;

/**
 * Règle du skill `api`, « La forme d'une ressource » : `createdAt` et `createdBy` ne sortent dans
 * l'API que lorsqu'ils portent un sens pour le client.
 *
 * Lancement : dev/test package=dev file=ApiFieldSentinel
 */
class ApiFieldSentinelTest extends SentinelTest {

	const CREATED = [
		// La date d'un événement du cycle de la facture électronique est l'événement lui-même
		'accounting/invoicing/api/Event_0_1beta.a.php' => 1,
		// La date d'un mouvement de stock, publiée aussi dans le dernier mouvement d'un produit
		'base/product/api/Stock_0_1beta.a.php' => 1,
		'base/product/api/Product_0_1beta.a.php' => 1,
		// L'heure de création d'un appareil de caisse est celle de sa prise de service, publiée en takenAt
		'commercialisation/selling/api/MarketDevice_0_1beta.a.php' => 1,
		// Qui a écrit une annotation d'un mouvement bancaire, et quand : le nom de l'auteur, sa date, sa modification
		'accounting/bank/api/Cashflow_0_1beta.a.php' => 4,
		// L'auteur et la date d'un commentaire
		'production/series/api/Comment_0_1beta.a.php' => 2,
		// La date d'une demande d'absence, que le gérant lit pour y répondre dans l'ordre
		'base/hr/api/Absence_0_1beta.a.php' => 1,
		// Publié avant la règle, le retirer change le contrat de l'API
		'production/series/api/Task_0_1beta.a.php' => 1,
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			if(preg_match('#/api/[^/]+\.a\.php$#', $file) === 1) {
				$this->addFound('created', $file, $this->findCreated($tokens));
			}

		}

	}

	/** @return int[] */
	private function findCreated(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			if(in_array(self::getString($token), ['createdAt', 'createdBy'], TRUE) and $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	public function testDetectors(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			$this->element('series\Task', fn() => ['id', 'createdAt', "createdBy"]);
			$note = 'createdAt est exclu';
			PHP);

		$this->assertEquals([2, 2], $this->findCreated($tokens), 'created');

	}

	public function testCreated(): void {
		$this->assertRatchet('created', self::CREATED, 'createdAt ou createdBy exposé dans l\'API, à déclarer avec sa raison');
	}

}
?>
