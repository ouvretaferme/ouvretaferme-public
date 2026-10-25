<?php
namespace dev;

/**
 * Base des tests qui tournent dans une transaction annulée après chaque méthode.
 *
 * `tearDown()` vérifie que la transaction ouverte par `setUp()` est encore celle qu'il annule, et
 * nomme ce qu'il trouve à la place. Il ne répare rien : ce qu'un commit implicite a écrit reste écrit.
 *
 * Le contrôle lit la pile du cadriciel et la connexion, parce qu'aucune des deux ne suffit : un
 * `rollBack()` du code éprouvé remet la pile à 0 quel que soit l'étage, et une instruction DDL valide
 * la transaction de la connexion sans toucher à la pile.
 *
 * Abstraite, donc ignorée par `TestLib::run()`.
 */
abstract class TransactionTest extends \Test {

	/**
	 * Un refus est un avertissement tant que la constante vaut FALSE, un échec ensuite.
	 * L'argument strict=1 de dev/test vaut TRUE pour le lancement.
	 */
	const STRICT = FALSE;

	/**
	 * À passer à TRUE dans une classe dont les tests ferment eux-mêmes la transaction, parce que
	 * c'est ce qu'ils éprouvent. Compté par TransactionSentinel.
	 */
	const CLOSED_BY_TEST = FALSE;

	const CLOSED = 'closed';
	const NESTED = 'nested';
	const IMPLICIT_COMMIT = 'implicit-commit';
	const FOREIGN = 'foreign';

	private string $server = '';

	abstract protected function getTransactionModel(): \ModuleModel;

	public function setUp(): void {

		$states = \ModuleModel::getTransactionStates();

		$this->getTransactionModel()->beginTransaction();

		$this->server = self::getOpenedServer($states, \ModuleModel::getTransactionStates());

	}

	public function tearDown(): void {

		$committed = FALSE;

		// tearDown() tourne aussi quand setUp() a levé avant d'ouvrir : il n'y a alors rien à vérifier.
		if($this->server !== '') {

			foreach(self::getRefusals(\ModuleModel::getTransactionStates(), $this->server) as [$server, $refusal]) {

				if($refusal !== self::CLOSED or static::CLOSED_BY_TEST === FALSE) {
					$this->refuse($server, $refusal);
				}

				if($refusal === self::IMPLICIT_COMMIT) {
					$committed = TRUE;
				}

			}

			$this->server = '';

		}

		if($committed) {

			// ModuleModel::rollBack() remet la pile à zéro avant d'annuler sur la connexion, qui lève faute
			// de transaction. Sans cet appel, la pile resterait à 1 et le setUp() suivant n'ouvrirait rien.
			try {
				$this->getTransactionModel()->rollBack();
			} catch(\PDOException) {
			}

		} else {
			$this->getTransactionModel()->rollBack();
		}

	}

	public static function getOpenedServer(array $before, array $after): string {

		foreach($after as $server => $state) {

			if($state['stack'] === ($before[$server]['stack'] ?? 0) + 1) {
				return $server;
			}

		}

		return '';

	}

	/**
	 * @return array<int, array{0: string, 1: string}> Le serveur et le refus
	 */
	public static function getRefusals(array $states, string $opened): array {

		$refusals = [];

		foreach($states as $server => $state) {

			if($server === $opened) {

				if($state['stack'] === 0) {
					$refusals[] = [$server, self::CLOSED];
				} else if($state['stack'] > 1) {
					$refusals[] = [$server, self::NESTED];
				} else if($state['connection'] === FALSE) {
					$refusals[] = [$server, self::IMPLICIT_COMMIT];
				}

			} else if($state['stack'] > 0 or $state['connection']) {
				$refusals[] = [$server, self::FOREIGN];
			}

		}

		return $refusals;

	}

	protected function getLogPath(): string {
		return sys_get_temp_dir().'/lime-test-transaction.log';
	}

	protected function isStrict(): bool {
		return (static::STRICT or GET('strict', 'bool'));
	}

	private function refuse(string $server, string $refusal): void {

		$text = match($refusal) {
			self::CLOSED => 'the transaction opened by setUp() is closed, nothing written after that point is rolled back',
			self::NESTED => 'a transaction opened by the test is still open',
			self::IMPLICIT_COMMIT => 'the connection holds no transaction, an implicit commit has written what came before it',
			self::FOREIGN => 'a transaction is open on a server this class did not open',
		};

		$message = static::class.'::'.$this->getTestMethod().'() on '.$server.', '.$text;

		if($this->isStrict()) {
			$this->assertFail($message);
		} else {

			TestLib::addWarning($message);

			file_put_contents($this->getLogPath(), date('Y-m-d H:i:s').' '.$message.PHP_EOL, FILE_APPEND | LOCK_EX);

		}

	}

}
?>
