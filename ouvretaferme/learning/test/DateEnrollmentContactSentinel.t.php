<?php
namespace learning;

/**
 * Une inscription à une date n'a pas toujours de compte : faite sur la page publique d'un réseau
 * (lot L10c), elle porte le prénom, le nom et l'adresse saisis, et `user` reste vide jusqu'à ce qu'un
 * compte se connecte à la même adresse. Lire l'identité par le compte (`['user']['email']`,
 * `['user']->getName()`) rend alors une valeur vide, ou lève `ElementException` sur une propriété que
 * la sélection n'a pas chargée : c'est ce qui cassait l'accusé de réception d'une ligne sans compte.
 *
 * Les accesseurs de `DateEnrollment.e.php` (`getName()`, `getFirstName()`, `getLastName()`,
 * `getEmail()`) choisissent entre le compte et la saisie. Ce contrôle vérifie qu'ils sont seuls à lire
 * l'identité d'une inscription par son compte. Tester `['user']->empty()` reste permis : c'est ce qui
 * distingue les deux lignes.
 *
 * Une inscription se reconnaît à son nom, `$eDateEnrollment…`, et à `$e`, `$this` ou `$data->e` dans
 * les fichiers dont elle est l'élément (`SCOPE`). `Enrollment`, l'inscription à une formation en
 * ligne, a toujours un compte et n'est pas concernée.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentContactSentinel
 */
class DateEnrollmentContactSentinelTest extends \dev\SentinelTest {

	const PACKAGE = 'ouvretaferme/learning';

	const RULE = 'identité d\'une inscription lue par son compte';

	/**
	 * Les fichiers où `$e`, `$this` et `$data->e` désignent une inscription à une date.
	 */
	const SCOPE = [
		'api/DateEnrollment_0_1beta.a.php',
		'module/DateEnrollment.e.php',
		'lib/DateEnrollment.l.php',
		'ui/DateEnrollment.u.php',
		'ui/DatePublic.u.php',
		'page/dateEnrollment.p.php',
		'page/public.p.php',
		'view/dateEnrollment.v.php',
		'view/public.v.php',
	];

	/**
	 * Les lectures permises, par fichier, et pourquoi.
	 */
	const DECLARED = [
		// Les quatre accesseurs, qui prennent le compte quand il existe et la saisie sinon.
		'module/DateEnrollment.e.php' => 4,
	];

	const PATTERN = '/(\$eDateEnrollment\w*|\$data->e\b|\$e\b|\$this)\[\'user\'\](?:\[\'(?:email|firstName|lastName)\'\]|->get(?:Name|FirstName|LastName|Email)\(\))/';

	/**
	 * Le code sans ses commentaires, lignes gardées : un commentaire qui cite la forme fautive pour
	 * l'expliquer n'est pas une lecture.
	 */
	public static function stripComments(string $source): string {

		$code = '';

		foreach(\PhpToken::tokenize($source) as $token) {

			$code .= $token->is([T_COMMENT, T_DOC_COMMENT]) ?
				str_repeat("\n", substr_count($token->text, "\n")) :
				$token->text;

		}

		return $code;

	}

	/**
	 * Les lignes d'une lecture de l'identité par le compte. Hors `SCOPE`, seul un nom en
	 * `$eDateEnrollment` désigne une inscription.
	 */
	public static function find(string $source, bool $inScope): array {

		$lines = [];

		foreach(explode("\n", self::stripComments($source)) as $index => $line) {

			if(preg_match_all(self::PATTERN, $line, $matches) === 0) {
				continue;
			}

			foreach($matches[1] as $variable) {

				if(
					$inScope or
					str_starts_with($variable, '$eDateEnrollment')
				) {
					$lines[] = $index + 1;
				}

			}

		}

		return $lines;

	}

	public function testDetector(): void {

		$source = <<<'PHP'
			<?php
			$a = $eDateEnrollment['user']['email'];
			$b = $eDateEnrollmentOnline['user']->getName();
			$c = $e['user']['firstName'].$this['user']['lastName'];
			$d = $data->e['user']->getEmail();
			// $eDateEnrollment['user']['email'] dans un commentaire
			$f = $eDateEnrollment['user']->empty() ? $eDateEnrollment->getEmail() : '';
			$g = $eEnrollment['user']['email'];
			$h = $eDateEnrollment['user']['id'];
			PHP;

		$this->assertEquals([2, 3, 4, 4, 5], self::find($source, TRUE));
		$this->assertEquals([2, 3], self::find($source, FALSE));

	}

	public function testContact(): void {

		$directory = LIME_DIRECTORY.'/'.self::PACKAGE;

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

		foreach($iterator as $path => $info) {

			$file = substr($path, strlen($directory) + 1);

			if(
				str_ends_with($file, '.php') === FALSE or
				str_starts_with($file, 'test/')
			) {
				continue;
			}

			$this->addFound(self::RULE, $file, self::find(file_get_contents($path), in_array($file, self::SCOPE, TRUE)));

		}

		$this->assertRatchet(self::RULE, self::DECLARED, 'identité d\'une inscription lue par son compte, passer par getName(), getFirstName(), getLastName() ou getEmail()');

	}

}
?>
