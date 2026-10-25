<?php
namespace dev;

/**
 * Une URL de route ->remote() se signe par `Lime::signRemote()`, jamais en collant la clé du paquet
 * derrière un `remoteKey=`.
 *
 * La clé vaut pour tout le parc et ne borne aucun identifiant : collée dans la chaîne de requête,
 * elle part dans les journaux d'accès du serveur, et qui l'y lit ouvre le document de n'importe
 * quelle structure. La signature porte le chemin, les arguments et l'instant, et la clé reste sur
 * le serveur.
 *
 * Le contrat ne se rappelle nulle part sur le chemin de celui qui ajoutera la prochaine route : il
 * écrira son URL dans son Lib, des mois plus tard, sans raison de lire `framework/core/Page.php`.
 * D'où cette sentinelle plutôt qu'une ligne de plus dans CLAUDE.md.
 *
 * Elle lit les chaînes du dépôt : `remoteKey=` écrit à la main, et `getRemoteKey()` appelé hors du
 * cadriciel, qui n'a plus de raison d'être depuis que la signature existe.
 *
 * Lancement : dev/test package=dev file=RemoteKeySentinel
 */
class RemoteKeySentinelTest extends SentinelTest {

	/**
	 * Qui écrit l'argument : `lime.php` parce que c'est lui qui le signe, le test de la signature
	 * parce qu'il doit nommer ce qu'il contrôle, et celui du journal des erreurs parce qu'il pose une
	 * signature dans un message pour vérifier qu'elle en disparaît. Aucun autre fichier n'a de raison
	 * de l'écrire.
	 * @var array<string, int>
	 */
	const WRITTEN_BY_HAND = [
		'framework/lime.php' => 1,
		'framework/dev/test/RemoteSignature.t.php' => 1,
		'framework/dev/test/ErrorSecret.t.php' => 3,
	];

	/**
	 * `getRemoteKey()` ne sert plus qu'à `signRemote()` et `verifyRemote()` : sa déclaration et leurs
	 * deux appels, tous dans le cadriciel. Le test des documents de vente la lit pour la remettre :
	 * il la retire le temps de faire échouer un rendu, et aucune autre voie ne la rend à la fin du cas.
	 * @var array<string, int>
	 */
	const KEY_READ = [
		'framework/lime.php' => 3,
		'commercialisation/selling/test/PdfAi.t.php' => 1,
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {
			$this->addFound('writtenByHand', $file, $this->findWrittenByHand($tokens));
			$this->addFound('keyRead', $file, $this->findKeyRead($tokens));
		}

	}

	/**
	 * Une chaîne qui porte `remoteKey=`, quelle que soit la forme de la concaténation autour.
	 *
	 * @return int[]
	 */
	private function findWrittenByHand(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			if(str_contains(self::getString($token) ?? '', 'remoteKey=')) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * Un appel à `getRemoteKey()`, qui rend le secret en clair à son appelant.
	 *
	 * @return int[]
	 */
	private function findKeyRead(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			if($token[0] === T_STRING and $token[1] === 'getRemoteKey') {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	public function testNoRemoteKeyIsWrittenByHand(): void {

		$this->assertRatchet('writtenByHand', self::WRITTEN_BY_HAND, 'remoteKey= écrit à la main, passer par Lime::signRemote()');

	}

	public function testTheKeyIsNotReadOutsideTheFramework(): void {

		$this->assertRatchet('keyRead', self::KEY_READ, 'Lime::getRemoteKey() appelé hors du cadriciel, passer par Lime::signRemote()');

	}

}
?>
