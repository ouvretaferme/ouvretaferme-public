<?php
namespace structure;

/**
 * Les can* d'écriture des modules exposés à l'API qui lisent une colonne de leur élément.
 *
 * `ModuleApi` ne pèse que les accept* (`framework/core/Api.php`) : une règle d'état rangée dans un
 * can* ne joue que sur le web, et l'API écrit ce que le web refuse. Chaque can* qui lit autre chose
 * que `structure` ou `id` se déclare donc ici avec sa raison, qui dit pourquoi ce qu'il lit est un
 * droit et non un état. Un état se déplace dans le accept* correspondant, et l'entrée disparaît.
 */
class WriteStateSentinelTest extends \dev\SentinelTest {

	const DECLARED = [
		'base/block/module/Block.e.php:canWrite' => 'droit par le parent : celui d\'écrire l\'objet qui porte le bloc, que dit le BlockOwnerLib de son usage ; l\'état est dans acceptWrite()',
		'base/hr/module/Absence.e.php:canCreate' => 'droit de l\'auteur : une personne présente au planning ne demande une absence que pour elle',
		'base/hr/module/Absence.e.php:canWrite' => 'droit de l\'auteur : une personne au planning ne retouche que sa propre demande tant qu\'elle attend une réponse',
		'base/hr/module/WorkingTime.e.php:canWrite' => 'droit de l\'auteur : un membre au planning limité n\'écrit que son propre temps',
		'base/structure/module/Invite.e.php:canDelete' => 'droit de l\'auteur : l\'invité refuse l\'invitation adressée à son courriel',
		'base/structure/module/Member.e.php:canUpdateRole' => 'droit de l\'auteur : personne ne change son propre rôle, et un rôle fantôme se règle par son invitation (laissé en can* au plan 4h)',
		'commercialisation/selling/module/Sale.e.php:canUpdateByCustomer' => 'droit de l\'auteur : le client connecté ne modifie que sa propre commande',
		'commercialisation/shop/module/Date.e.php:canCreateSale' => 'droit par le parent : la boutique, lue en personnel ou partagée selon sa nature ; l\'état est dans acceptSaleBy()',
		'commercialisation/shop/module/Range.e.php:canCreate' => 'droit par le parent : la boutique partagée avec la structure du rattachement',
		'commercialisation/shop/module/Range.e.php:canWrite' => 'droit par le parent : la boutique ou la structure du rattachement',
		'commercialisation/shop/module/Share.e.php:canWrite' => 'droit par le parent : l\'administrateur de la boutique, ou le producteur sur sa propre part',
		'ouvretaferme/learning/module/Chapter.e.php:canWrite' => 'droit par le parent : celui d\'écrire la formation',
		'ouvretaferme/learning/module/Course.e.php:canWrite' => 'droit par le parent : être de l\'équipe du réseau qui porte la formation',
		'ouvretaferme/learning/module/Date.e.php:canWrite' => 'droit par le parent : celui d\'écrire la formation',
		'ouvretaferme/learning/module/DateEnrollment.e.php:canCreate' => 'droit de l\'auteur : on ne s\'inscrit que soi-même, à une formation qu\'on peut lire',
		'ouvretaferme/learning/module/Faq.e.php:canWrite' => 'droit de l\'équipe : être de l\'équipe de la structure qui porte la FAQ ; l\'état est dans acceptWrite()',
		'ouvretaferme/learning/module/Lesson.e.php:canWrite' => 'droit par le parent : celui d\'écrire la formation',
		'ouvretaferme/learning/module/Phase.e.php:canWrite' => 'droit par le parent : celui d\'écrire la formation',
		'production/series/module/Timesheet.e.php:canWrite' => 'droit de l\'auteur : un membre au planning limité n\'écrit que son propre temps',
	];

	const IGNORED = ['structure', 'farm', 'id'];

	/**
	 * @var string[] les API dont le module exposé ne se retrouve pas
	 */
	private array $unresolved = [];

	public function init() {

		// Un module se cherche par son espace de noms et son nom, le dossier d'un package ne disant pas
		// son espace de noms à coup sûr : `production/series` comme `base/hr`.
		$modules = [];

		foreach(\dev\SentinelLib::getFiles('.e.php') as $file => $path) {
			if(preg_match('#([^/]+)/module/(\w+)\.e\.php$#', $file, $match) === 1) {
				$modules[$match[1].'\\'.$match[2]] = $file;
			}
		}

		foreach(\dev\SentinelLib::getFiles('.a.php') as $file => $path) {

			if(preg_match('#^(.+)/api/([A-Z]\w*?)_\d#', $file, $match) === 0) {
				continue;
			}

			$module = self::getExposedModule(file_get_contents($path), $modules) ?? $match[1].'/module/'.$match[2].'.e.php';

			if(is_file(\dev\SentinelLib::getRoot().'/'.$module) === FALSE) {
				$this->unresolved[] = $file;
				continue;
			}

			foreach(self::findStateReads(\dev\SentinelLib::getTokens(file_get_contents(\dev\SentinelLib::getRoot().'/'.$module))) as $method => $line) {
				$this->addFoundKey('writeState', $module, $method, $line);
			}

		}

	}

	/**
	 * Le module qu'une API expose, lu dans `getNewElement()` : une API peut exposer le module d'un
	 * autre package, et son propre chemin désignerait alors un fichier absent.
	 */
	private static function getExposedModule(string $code, array $modules): ?string {

		if(preg_match('#function getNewElement\(.*?return new \\\\?([\w\\\\]+)\(#s', $code, $match) === 0) {
			return NULL;
		}

		$class = $match[1];

		if(str_contains($class, '\\') === FALSE) {
			preg_match('#^namespace (\w+);#m', $code, $namespace);
			$class = $namespace[1].'\\'.$class;
		}

		return $modules[$class] ?? NULL;

	}

	public function testExposedModule(): void {

		$modules = ['block\\Block' => 'base/block/module/Block.e.php', 'learning\\Lesson' => 'ouvretaferme/learning/module/Lesson.e.php'];

		$this->assertEquals('base/block/module/Block.e.php', self::getExposedModule("<?php\nnamespace learning;\nclass Block_0_1betaApi {\n\tpublic function getNewElement(\\ApiData \$data): \\block\\Block {\n\t\treturn new \\block\\Block();\n\t}\n}", $modules), 'module d\'un autre package');
		$this->assertEquals('ouvretaferme/learning/module/Lesson.e.php', self::getExposedModule("<?php\nnamespace learning;\nclass Lesson_0_1betaApi {\n\tpublic function getNewElement(\\ApiData \$data): Lesson {\n\t\treturn new Lesson();\n\t}\n}", $modules), 'module du package, nom court');
		$this->assertEquals(NULL, self::getExposedModule("<?php\nnamespace learning;\nclass Course_0_1betaApi {}", $modules), 'sans getNewElement()');

	}

	public function testResolved(): void {
		$this->assertEquals([], $this->unresolved, 'API dont le module exposé ne se retrouve ni par getNewElement() ni par le chemin de l\'API, donc ses can* ne sont pas lus : '.implode(', ', $this->unresolved));
	}

	public function testDeclared(): void {
		$this->assertDeclared('writeState', self::DECLARED, 'can* d\'écriture d\'un module exposé qui lit une colonne de son élément : une règle d\'état passe dans le accept* correspondant, que l\'API interroge seul ; un droit se déclare dans DECLARED avec sa raison');
	}

	public function testDetector(): void {

		$code = <<<'PHP'
<?php
namespace fixture;

class Fixture {

	public function canWrite(): bool {
		return $this['structure']->canManage() and $this['status'] !== 'deleted';
	}

	public function canCreate(): bool {
		return $this['structure']->canManage();
	}

	public function canDeleteOwn(): bool {
		return $this['user']->isOnline();
	}

	public function canRead(): bool {
		return $this['status'] !== 'deleted';
	}

	public function acceptUpdate(): bool {
		return $this['status'] !== 'deleted';
	}

}
PHP;

		$this->assertEquals(['canWrite' => 6, 'canDeleteOwn' => 14], self::findStateReads(\dev\SentinelLib::getTokens($code)));

	}

	/**
	 * Les can* d'écriture dont le corps lit `$this['<colonne>']` hors de `IGNORED`, avec leur ligne.
	 *
	 * @return array<string, int>
	 */
	public static function findStateReads(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type]) {

			if(
				$type !== T_FUNCTION or
				($tokens[$i + 1][0] ?? NULL) !== T_STRING or
				preg_match('/^can(Write|Create|Update|Delete)/', $tokens[$i + 1][1]) === 0
			) {
				continue;
			}

			$open = $i + 1;

			while(isset($tokens[$open]) and $tokens[$open][0] !== '{') {
				$open++;
			}

			$close = \dev\SentinelLib::getClosing($tokens, $open);

			for($j = $open; $j < $close; $j++) {

				if(
					$tokens[$j][0] === T_VARIABLE and
					$tokens[$j][1] === '$this' and
					$tokens[$j + 1][0] === '[' and
					$tokens[$j + 2][0] === T_CONSTANT_ENCAPSED_STRING and
					in_array(trim($tokens[$j + 2][1], '\'"'), self::IGNORED, TRUE) === FALSE
				) {
					$found[$tokens[$i + 1][1]] = $tokens[$i + 1][2];
					break;
				}

			}

		}

		return $found;

	}

}
?>
