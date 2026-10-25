<?php
namespace data;

/**
 * Inventaire des blocs `## Vérifier` de `migration/` qui ne répondent pas depuis la base seule.
 *
 * Le contrat qu'elle tient : un bloc `## Vérifier` imprime `A JOUER` ou `DEJA JOUEE` d'après l'état
 * de la base, et d'après rien d'autre. C'est le seul bloc du format dont la réponse décide d'un
 * geste chez quelqu'un qui n'a pas écrit le fichier, et c'est la raison pour laquelle il ne peut
 * dépendre ni d'un script du lot, qui disparaît avec lui (`/enprod`, étape 7), ni du système de
 * fichiers, qui décrit l'arbre et non la base.
 *
 * Les deux façons de s'en écarter se ressemblent et donnent des réponses fausses opposées, ce qui
 * est la raison du test plutôt que d'une phrase de plus dans `migration/README.md` :
 *
 * - **un appel nu à une route de `page/configure/` retirée** ne répond pas du tout. Le `||` de la
 *   commande transforme l'échec de la route en `A JOUER`, donc le fichier envoie jouer un
 *   `## Jouer` qui cite le même script absent ;
 * - **un garde `test -f <script>` qui imprime `DEJA JOUEE` quand le fichier manque** répond sur
 *   l'arbre. Chez celui qui tire sans avoir joué la migration, le script est absent aussi, puisque
 *   la mise en production l'a retiré de l'amont : il lit `DEJA JOUEE` sur une base en retard, et la
 *   migration ne se joue jamais. C'est la plus coûteuse des deux, parce qu'elle dispense.
 *
 * Mesuré le 2026-10-02 sur 199 fichiers suivis : 7 fichiers s'en écartent, 2 par l'appel nu et 5 par
 * le garde, tous les 7 marqués `En production le` et tous les 7 portant leur ligne `Script retiré`,
 * donc le script est récupérable et seule la réponse du bloc est en cause. Le geste est le même dans
 * les deux cas, réécrire le bloc en `-r` autonome qui lit la base, ce que font 105 des 108 fichiers
 * à l'ouverture de ce sujet.
 *
 * Lancement : dev/test package=data file=MigrationVerifySentinelAi
 */
class MigrationVerifySentinelAiTest extends \Test {

	/**
	 * Les blocs `## Vérifier` qui appellent une route de `page/configure/` que git ne porte pas.
	 *
	 * La valeur est la route citée, pour que le message nomme ce qui manque sans qu'on ouvre le
	 * fichier. Les sept portent leur ligne `Script retiré` : ce qui reste à faire est de remplacer
	 * l'appel par la lecture de base que le premier bloc de `2026-09-28-abonnement-releve.md` fait
	 * déjà, et non de remonter le script. Les cinq derniers sont aussi déclarés dans l'inventaire
	 * suivant, et ce n'est pas une double écriture : ils gardent l'appel par un `test -f`, donc ils
	 * portent les deux défauts, et réparer l'un laisse l'autre.
	 *
	 * La comparaison se fait dans les deux sens, un fichier réparé échouant aussi, si bien que la
	 * déclaration ne peut que descendre.
	 */
	const APPELLENT_UN_SCRIPT_RETIRE = [
		'2026-09-25-abonnement-produits.md' => 'subscription/configure/productsAi',
		'2026-09-28-abonnement-releve.md' => 'subscription/configure/statementAi',
		'2026-09-29-comptabilite-documents-vides.md' => 'financialYear/configure/documentEmptyAi',
		'2026-09-29-comptabilite-ouverture-vide.md' => 'financialYear/configure/openingBalanceEmptyAi',
		'2026-10-01-invitations-client-sans-fiche.md' => 'structure/configure/inviteCustomerCleanAi',
		'2026-10-01-paniers-composition-rattachee.md' => 'selling/configure/compositionRelinkAi',
		'2026-10-01-plant-famille-tournesol-myrtille.md' => 'plant/configure/familyFixAi',
	];

	/**
	 * Les blocs `## Vérifier` qui lisent le système de fichiers.
	 *
	 * Les cinq appartiennent à quatre chantiers et emploient la même forme, `test -f <script> ||
	 * echo "DEJA JOUEE"`, écrite de bonne foi pour que le fichier continue de répondre après le
	 * retrait du script. Elle répond, et elle répond faux chez celui qui tire : son arbre n'a pas le
	 * script non plus. Ils sont déclarés, non réparés, le geste revenant au lot qui a écrit le
	 * fichier, et la déclaration descend avec lui.
	 *
	 * ⚠️ L'inventaire ne retient qu'un chemin **du dépôt**. Un `test -d /var/www/mysql-backup/backup`
	 * lit l'état de la machine, ce qu'une migration dont l'objet est une archive doit faire, et la
	 * première rédaction de ce test l'attrapait : la règle tirée des cinq cas en écartait un sixième
	 * qui est juste (`2026-09-30-tech-sauvegarde-du-decor.md`).
	 */
	const LISENT_LE_SYSTEME_DE_FICHIERS = [
		'2026-09-29-comptabilite-documents-vides.md',
		'2026-09-29-comptabilite-ouverture-vide.md',
		'2026-10-01-invitations-client-sans-fiche.md',
		'2026-10-01-paniers-composition-rattachee.md',
		'2026-10-01-plant-famille-tournesol-myrtille.md',
	];

	/**
	 * Le bloc `## Vérifier` d'un fichier suivi, ou NULL quand il n'en a pas.
	 *
	 * Le bloc court jusqu'au titre suivant : `## Jouer` cite légitimement un script, c'est son
	 * objet, et `## En production` porte les mêmes commandes.
	 */
	protected function getVerifyBlock(string $path): ?string {

		if(preg_match('/^## Vérifier\s*$(.*?)(?=^## |\z)/ms', file_get_contents($path), $match) === 0) {
			return NULL;
		}

		return $match[1];

	}

	/**
	 * Les fichiers de `migration/` que git porte, hors README.
	 *
	 * Le nom ne reprend pas celui d'un helper de `SentinelTest` : `getFiles()` y est défini et une
	 * redéclaration n'héritera jamais de ses corrections (`framework/dev/test/SentinelHelperSentinel.t.php`).
	 *
	 * Un fichier non suivi appartient à la session qui l'écrit, avec un lot qui n'est pas poussé :
	 * son bloc peut citer un script que l'amont n'a pas encore, et ce n'est pas un écart.
	 */
	protected function getMigrationFiles(): array {

		$files = [];

		// `getFiles()` rend les chemins relatifs en clés et les chemins absolus en valeurs.
		foreach(\dev\SentinelLib::getFiles('.md') as $relative => $absolute) {

			if(str_starts_with($relative, 'migration/') === FALSE or $relative === 'migration/README.md') {
				continue;
			}

			$files[basename($relative)] = $absolute;

		}

		return $files;

	}

	public function testAucunBlocVerifierNAppelleUnScriptRetire(): void {

		// `getTracked()` rend NULL quand git est indisponible, ce qu'il annonce lui-même : le repli
		// est alors l'existence sur disque, qui répond à la même question pour un script de
		// `page/configure/` cité par un fichier de `migration/` déjà suivi.
		$tracked = \dev\SentinelLib::getTracked();
		$found = [];

		foreach($this->getMigrationFiles() as $file => $path) {

			$block = $this->getVerifyBlock($path);

			if($block === NULL) {
				continue;
			}

			// Une route de `page/configure/` s'écrit `<paquet>/configure/<nom>` : le préfixe `page`
			// qu'on lit aussi dans ces blocs est un chemin de fichier, traité par l'autre méthode.
			preg_match_all('#\b([a-zA-Z][a-zA-Z0-9]*)/configure/([a-zA-Z0-9_]+)#', $block, $matches, PREG_SET_ORDER);

			foreach($matches as $match) {

				if($match[1] === 'page') {
					continue;
				}

				try {
					$root = \Package::getPath($match[1]);
				} catch(\Exception) {
					continue;
				}

				$relative = substr($root, strlen(\dev\SentinelLib::getRoot()) + 1).'/page/configure/'.$match[2].'.p.php';

				$present = $tracked === NULL ?
					file_exists(\dev\SentinelLib::getRoot().'/'.$relative) :
					isset($tracked[$relative]);

				if($present === FALSE) {
					$found[$file] = $match[1].'/configure/'.$match[2];
					break;
				}

			}

		}

		$new = [];
		$repaired = [];

		foreach($found as $file => $route) {
			if(isset(self::APPELLENT_UN_SCRIPT_RETIRE[$file]) === FALSE) {
				$new[] = $file.' → '.$route;
			}
		}

		foreach(self::APPELLENT_UN_SCRIPT_RETIRE as $file => $route) {
			if(isset($found[$file]) === FALSE) {
				$repaired[] = $file;
			}
		}

		// Le côté qui se corrige est le fichier de `migration/`, jamais le dépôt : le script a été
		// retiré exprès par `/enprod`, le remonter pour faire répondre un contrôle rétablirait un
		// outil à usage unique que la production a déjà joué.
		$this->assertEquals([], $new, 'un bloc `## Vérifier` appelle une route de `page/configure/` que git ne porte plus, donc il imprime `A JOUER` sur une migration jouée ; réécrire le bloc en `php framework/lime.php … -r` qui lit la base, sans remonter le script : '.implode(' · ', $new));
		$this->assertEquals([], $repaired, 'blocs réparés, les retirer de APPELLENT_UN_SCRIPT_RETIRE : '.implode(' · ', $repaired));

	}

	public function testAucunBlocVerifierNeLitLeSystemeDeFichiers(): void {

		$found = [];

		foreach($this->getMigrationFiles() as $file => $path) {

			$block = $this->getVerifyBlock($path);

			if($block === NULL) {
				continue;
			}

			foreach(explode("\n", $block) as $line) {

				// La prose autour du bloc parle librement d'un fichier retiré : seule une commande
				// décide de ce qui s'imprime.
				if(preg_match('/^(php |test |\[ |ls )/', trim($line)) === 0) {
					continue;
				}

				// Le test porte sur un chemin **du dépôt**, écrit en relatif. Un chemin absolu
				// (`/var/www/mysql-backup/backup`) lit l'état de la machine, ce qui est légitime et
				// parfois l'objet même de la migration : `2026-09-30-tech-sauvegarde-du-decor.md`
				// vérifie qu'une archive existe, et aucune ligne de base ne peut le dire.
				if(preg_match('/(?:\btest\s+|\[\s+)!?\s*-[efd]\s+(?![\/~$])[a-zA-Z]/', $line) === 1) {
					$found[] = $file;
					break;
				}

			}

		}

		$found = array_values(array_unique($found));

		$new = array_values(array_diff($found, self::LISENT_LE_SYSTEME_DE_FICHIERS));
		$repaired = array_values(array_diff(self::LISENT_LE_SYSTEME_DE_FICHIERS, $found));

		// ⚠️ Le message dit la direction, parce que les deux lectures de l'écart mènent à des gestes
		// opposés : le lecteur qui croit que l'arbre fait foi ajoute un garde de plus, celui qui
		// sait que la base fait foi retire le garde. C'est la base.
		$this->assertEquals([], $new, 'un bloc `## Vérifier` lit le système de fichiers, donc il répond sur l\'arbre et non sur la base ; chez celui qui tire, le script est absent aussi et le bloc imprime `DEJA JOUEE` sur une base en retard : '.implode(' · ', $new));
		$this->assertEquals([], $repaired, 'blocs réparés, les retirer de LISENT_LE_SYSTEME_DE_FICHIERS : '.implode(' · ', $repaired));

	}

}
