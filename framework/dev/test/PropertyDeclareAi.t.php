<?php
namespace dev;

/**
 * Lot L2 de la refonte en Property builder : la déclaration statique d'un pipeline par
 * `static function properties(\PropertyList $l)` et le builder `$l->property('x')->…`.
 *
 * Les sondes héritent d'un vrai élément, `structure\Community`, qui ne surcharge pas build() : la
 * propriété `quotationMinimum` est bornée `int8(5, 50)` par le .yml, ce qui permet de voir si le
 * contrôle du schéma tourne encore (une valeur hors bornes refusée) ou a été remplacé.
 *
 * Aucun de ces tests ne touche la base : build() ne fait que valider et poser la valeur sur l'élément.
 */

class ProbeDeclareAddAfterCheck extends \structure\Community {

	public static int $declaredTimes = 0;

	public static function properties(\PropertyList $l): void {

		self::$declaredTimes++;

		$l->property('quotationMinimum')
			->addAfterCheck('even', function(int $value): bool {
				return $value % 2 === 0;
			});

	}

}

class ProbeDeclareReplaceCheck extends \structure\Community {

	public static function properties(\PropertyList $l): void {

		$l->property('quotationMinimum')
			->replaceCheck(fn($value): bool => TRUE);

	}

}

class ProbeDeclareThis extends \structure\Community {

	public static function properties(\PropertyList $l): void {

		// Déclarée dans un static, cette closure n'a pas de $this : build() doit le lui donner.
		$l->property('quotationMinimum')
			->addAfterCheck('atLeastSections', function(int $value): bool {
				return $value >= $this['sections'];
			});

	}

}

class ProbeDeclareArgument extends \structure\Community {

	public static int $argumentCalls = 0;

	public static function properties(\PropertyList $l): void {

		$l->property('quotationMinimum')
			->replaceArgument(function(string $property): string {
				self::$argumentCalls++;
				return 'minimum';
			});

	}

}

class ProbeDeclareSkip extends \dev\ErrorTrace {

	public static function properties(\PropertyList $l): void {

		$l->property('line')
			->addAfterCheck('file', function(int $value, \PropertyList $l): bool {
				$l->skipIfNotBuilt('file');
				return TRUE;
			});

	}

}

class ProbeDeclareRequired extends \structure\Community {

	public static function properties(\PropertyList $l): void {

		// sections est retenue parce que son cast ramène un champ absent à 0, ce qu'une étape posée après
		// lui ne distinguerait pas d'une saisie ; testRequiredJudgesTheRawInput le vérifie avant de s'y fier
		$l->property('customFont')->required();
		$l->property('sections')->required(fn(\PropertyList $l): bool => $l->for === 'create');
		$l->property('customTitleFont')->required(fn(\PropertyList $l): bool => $this->exists() === FALSE);

	}

}

class PropertyDeclareAiTest extends \Test {

	private function accepts(\Element $e, int $value, string $key = 'quotationMinimum'): bool {

		$fw = new \FailWatch();
		$e->build(['quotationMinimum'], [$key => $value], new \PropertyList('update'));

		return $fw->ok();

	}

	/**
	 * addAfterCheck s'ajoute : le schéma refuse 51 même si la règle ajoutée l'accepterait, la
	 * règle ajoutée refuse 49 que le schéma accepterait, et 50 passe les deux.
	 */
	public function testAddAfterCheckKeepsTheSchema(): void {

		$e = new ProbeDeclareAddAfterCheck();

		$this->assertTrue($this->accepts($e, 50), '50 : dans les bornes et pair');
		$this->assertEquals(50, $e['quotationMinimum'], 'la valeur acceptée est posée sur l\'élément');
		$this->assertFalse($this->accepts($e, 52), '52 : pair mais hors bornes, le schéma refuse');
		$this->assertFalse($this->accepts($e, 49), '49 : dans les bornes mais impair, la règle ajoutée refuse');

	}

	/**
	 * replaceCheck remplace, délibérément : 52 passe.
	 */
	public function testReplaceCheckReplacesTheSchema(): void {

		$e = new ProbeDeclareReplaceCheck();

		$this->assertTrue($this->accepts($e, 52), '52 : hors bornes, mais le contrôle du schéma est remplacé');

	}

	/**
	 * Le nom d'une étape ajoutée est la clé de son échec, telle qu'Alert.u.php la lira.
	 */
	public function testAddedStepNamesItsFailure(): void {

		$fw = new \FailWatch();
		new ProbeDeclareAddAfterCheck()->build(['quotationMinimum'], ['quotationMinimum' => 49], new \PropertyList('update'));

		$names = array_map(fn($fail) => $fail[1], $fw->get());

		$this->assertEquals(['Community::quotationMinimum.even'], $names);

	}

	/**
	 * Une closure déclarée dans un static reçoit l'élément en $this au moment de l'appel.
	 */
	public function testThisIsBoundToTheElement(): void {

		$e = new ProbeDeclareThis(['sections' => 10]);

		$this->assertTrue($this->accepts($e, 10), '10 >= sections (10)');
		$this->assertFalse($this->accepts($e, 9), '9 < sections (10) : la closure a bien lu $this');

	}

	/**
	 * Une étape qui lève PropertySkip abandonne la propriété avant son `set` : la propriété n'a pas reçu
	 * sa valeur, elle ne doit pas se dire construite. Sinon une étape suivante qui lit `isBuilt()` puis
	 * la valeur trouve une clé absente (journal\Operation, accountLabel qui lisait accountNumber sur
	 * une écriture sans date, structure 3395 le 26/09/2026).
	 */
	public function testSkippedPropertyIsNotBuilt(): void {

		$e = new ProbeDeclareSkip();
		$l = new \PropertyList('update');

		$e->build(['line'], ['line' => 10], $l);

		$this->assertFalse($l->isBuilt('line'), 'une propriété abandonnée n\'est pas construite');
		$this->assertFalse($e->offsetExists('line'), 'contrôle du contrôle : son set n\'a pas tourné');

		$e = new ProbeDeclareSkip();
		$l = new \PropertyList('update');

		$e->build(['file', 'line'], ['file' => 'Data.php', 'line' => 10], $l);

		$this->assertTrue($l->isBuilt('line'), 'sans abandon, la propriété est construite');
		$this->assertEquals(10, $e['line']);

	}

	/**
	 * replaceArgument choisit la clé lue dans l'entrée, une fois, et ne tourne pas dans la chaîne.
	 */
	public function testReplaceArgumentReadsAnotherKeyOnce(): void {

		ProbeDeclareArgument::$argumentCalls = 0;

		$e = new ProbeDeclareArgument();

		$this->assertTrue($this->accepts($e, 20, 'minimum'), 'la valeur est lue sous la clé choisie');
		$this->assertEquals(20, $e['quotationMinimum']);
		$this->assertEquals(1, ProbeDeclareArgument::$argumentCalls, 'l\'argument ne tourne qu\'une fois, à l\'étape 0');

	}

	/**
	 * Le pipeline d'une classe se déclare une fois : plusieurs build() ne rappellent pas properties().
	 */
	public function testPropertiesIsDeclaredOncePerClass(): void {

		$before = ProbeDeclareAddAfterCheck::$declaredTimes;

		$e = new ProbeDeclareAddAfterCheck();
		$this->accepts($e, 10);
		$this->accepts($e, 12);
		$this->accepts(new ProbeDeclareAddAfterCheck(), 14);

		$this->assertTrue(ProbeDeclareAddAfterCheck::$declaredTimes <= max(1, $before), 'properties() n\'est appelée qu\'une fois par classe');

	}

	/**
	 * Un nom d'étape réservé ou déjà pris est refusé à la déclaration, pas à l'exécution.
	 */
	public function testStepNamesAreGuarded(): void {

		$this->assertThrows(fn() => new \Property('x')->addAfterCheck('check', fn() => TRUE), 'Property \'x\': \'check\' is reserved or invalid as a step name');
		$this->assertThrows(fn() => new \Property('x')->addAfterCheck('a', fn() => TRUE)->addAfterCast('a', fn() => TRUE), 'Property \'x\': step \'a\' is declared twice');
		$this->assertThrows(fn() => new \Property('x')->replaceCheck(fn() => TRUE)->replaceCheck(fn() => TRUE), 'Property \'x\': check is replaced twice');

	}

	/**
	 * required() juge l'entrée avant le cast : absente, NULL, vide ou faite d'espaces, elle est refusée
	 * sous `propriete.required`, même là où le cast l'aurait ramenée à une valeur acceptée.
	 */
	public function testRequiredJudgesTheRawInput(): void {

		$refused = function(array $input, string $for = 'update'): array {

			$fw = new \FailWatch();
			new ProbeDeclareRequired()->build(['customFont'], $input, new \PropertyList($for));

			return array_map(fn($fail) => $fail[1], $fw->get());

		};

		$this->assertEquals(['Community::customFont.required'], $refused([]), 'absente');
		$this->assertEquals(['Community::customFont.required'], $refused(['customFont' => NULL]), 'NULL');
		$this->assertEquals(['Community::customFont.required'], $refused(['customFont' => '']), 'vide');
		$this->assertEquals(['Community::customFont.required'], $refused(['customFont' => '  ']), 'des espaces, que le cast aurait ramenés à NULL');
		$this->assertEquals([], $refused(['customFont' => 'Lora']), 'une valeur passe');

		$e = new ProbeDeclareRequired();
		$fw = new \FailWatch();
		$e->build(['sections'], [], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), 'hors de la condition, un champ absent passe comme avant');
		$this->assertEquals(0, $e['sections'], 'contrôle du contrôle : le cast l\'a ramené à 0, qu\'une étape après lui ne distinguerait pas d\'une saisie');

		$fw = new \FailWatch();
		new ProbeDeclareRequired()->build(['sections'], [], new \PropertyList('create'));

		$this->assertEquals(['Community::sections.required'], array_map(fn($fail) => $fail[1], $fw->get()), 'sous la condition, le même champ absent est refusé');

		$e = new ProbeDeclareRequired();
		$fw = new \FailWatch();
		$e->build(['sections'], ['sections' => '0'], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), 'sous la condition, un zéro saisi passe');
		$this->assertEquals(0, $e['sections']);

	}

	/**
	 * La condition de required(), déclarée dans un properties() statique, lit l'élément construit comme
	 * toute autre étape.
	 */
	public function testRequiredConditionReadsTheElement(): void {

		$fw = new \FailWatch();
		new ProbeDeclareRequired()->build(['customTitleFont'], [], new \PropertyList('update'));

		$this->assertEquals(['Community::customTitleFont.required'], array_map(fn($fail) => $fail[1], $fw->get()), 'un élément neuf doit la porter');

		$fw = new \FailWatch();
		new ProbeDeclareRequired(['id' => 1])->build(['customTitleFont'], [], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), 'un élément enregistré peut s\'en passer');

	}

	public function testRequiredIsDeclaredOnce(): void {

		$this->assertThrows(fn() => new \Property('x')->required()->required(), 'Property \'x\': step \'required\' is declared twice');
		$this->assertThrows(fn() => new \Property('x')->required()->addAfterCheck('required', fn() => TRUE), 'Property \'x\': step \'required\' is declared twice');
		$this->assertThrows(fn() => new \Property('x')->addBeforeCast('required', fn() => TRUE)->required(), 'Property \'x\': step \'required\' is declared twice');

		$this->assertEquals(
			['q.required' => 'declared', 'q.trim' => 'declared', 'q.cast' => 'default', 'q.check' => 'default', 'q.set' => 'default', 'q.end' => 'default'],
			new \Property('q')->addBeforeCast('trim', fn() => TRUE)->required()->chain(TRUE),
			'required passe avant toute étape, quel que soit l\'ordre de déclaration'
		);

	}

	/**
	 * Un second ->property() sur le même nom complète la même description (une boucle sur une
	 * constante, puis la description propre) : c'est l'étape, pas la propriété, qui ne se pose qu'une fois.
	 */
	public function testPropertyCanBeReopened(): void {

		$l = new \PropertyList();
		$l->property('q')->addBeforeCast('variant', fn() => TRUE);
		$l->property('q')->replaceCheck(fn() => TRUE);

		$this->assertEquals(['q.variant' => 'declared', 'q.cast' => 'default', 'q.check' => 'declared', 'q.set' => 'default', 'q.end' => 'default'], $l->getDeclared('q')->chain(TRUE));
		$this->assertThrows(fn() => $l->property('q')->replaceCheck(fn() => TRUE), 'Property \'q\': check is replaced twice');

	}

	/**
	 * \Test::assertException() attend une exception déjà attrapée ; ici on veut prouver qu'un
	 * geste lève, avec ce message.
	 */
	private function assertThrows(\Closure $act, string $message): void {

		try {
			$act();
		} catch(\Exception $e) {
			$this->assertEquals($message, $e->getMessage());
			return;
		}

		$this->assertFail('Aucune exception levée, attendu : '.$message);

	}

	/**
	 * La chaîne compilée dit, clé par clé, ce qui est défaut et ce qui est déclaré : un check
	 * remplacé devient « declared », un ajouté laisse le schéma en « default ».
	 */
	public function testChainTellsDefaultFromDeclared(): void {

		$added = new \Property('q');
		$added->addAfterCheck('even', fn() => TRUE);

		$this->assertEquals(
			['q.cast' => 'default', 'q.check' => 'default', 'q.even' => 'declared', 'q.set' => 'default', 'q.end' => 'default'],
			$added->chain(TRUE)
		);

		$replaced = new \Property('q');
		$replaced->replaceCheck(fn() => TRUE)->addBeforeCast('trim', fn() => TRUE);

		$this->assertEquals(
			['q.trim' => 'declared', 'q.cast' => 'default', 'q.check' => 'declared', 'q.set' => 'default', 'q.end' => 'default'],
			$replaced->chain(TRUE)
		);

		$pseudo = new \Property('virtual');
		$pseudo->addAfterCheck('present', fn() => TRUE);

		$this->assertEquals(['virtual.present' => 'declared'], $pseudo->chain(FALSE), 'une pseudo-propriété n\'a pas de défauts');

	}

}
?>
