<?php
namespace dev;

/**
 * Un nombre saisi se lit par le cadriciel : `\Filter::normalize()` ramène l'écriture française
 * (« 6,5 », « 1 000 ») à celle que le cast et le contrôle lisent, aux deux portes par lesquelles une
 * saisie entre, les lecteurs typés et `build()` (`framework/dev/test/NumericInput.t.php`). Ce qui lit
 * un nombre lui-même se déclare ici, avec ce qui le contrôle, et ce qu'il accepte de plus ou de moins
 * que le cadriciel.
 *
 * Trois inventaires, bornés à ce qui se lit mécaniquement :
 * - les conversions à la main de la virgule en point (`str_replace` d'une virgule par un point),
 *   déclarées par fonction ;
 * - les étapes de `properties()` qui lisent une propriété numérique avant le cast du cadriciel
 *   (`replaceCast`, `addBeforeCast`), déclarées par propriété ;
 * - les propriétés décimales d'un modèle sans `min` ou sans `max`, qui doivent rester zéro :
 *   `Filter::getRange()` retomberait sur les bornes d'un int32.
 *
 * Les casts nus, `(float)$x` et `(int)$x`, ne sont dans aucun inventaire : un cast ne dit pas d'où
 * vient sa valeur, et aucun motif ne sépare celui qui lit une saisie de celui qui lit une colonne.
 * L'inventaire n'est donc pas complet, et ne le prétend pas.
 *
 * Lancement : dev/test package=dev file=NumericInputSentinel
 */
class NumericInputSentinelTest extends SentinelTest {

	const RULE_CONVERSION = 'conversion';
	const RULE_STEP = 'step';
	const RULE_UNBOUNDED = 'unbounded';

	/**
	 * Les répertoires dont chaque fichier est dispensé de la déclaration des conversions, avec une raison
	 * commune : un outil à usage unique, qui reprend une donnée déjà lue une fois.
	 */
	const DIRECTORIES = [
		'/page/configure/' => 'un script de reprise lit des données déjà en base ou un fichier qu\'il connait, une fois, et sous les yeux de qui le lance',
		'/page/perso/' => 'un script personnel, hors du produit',
	];

	const FEC = 'les montants d\'un FEC, dont ImportLib::checkFormat() n\'admet que des chiffres, une virgule décimale et un signe';
	const OMNIBOX = 'une recherche par montant dans l\'omnibox, qui retire aussi le symbole euro : is_numeric() juge le résultat, et une saisie qui n\'est pas un montant ne cherche aucun montant';

	/**
	 * Les conversions à la main de la virgule en point, par fonction, avec ce qui les contrôle.
	 */
	const CONVERSION = [
		'accounting/asset/lib/Expense.l.php:getOperationMatch' => 'une recherche par montant dans les lignes de charge à imputer à une immobilisation : une expression régulière n\'en garde que deux décimales au plus, sinon la recherche ne porte pas sur un montant',
		'accounting/bank/lib/OfxParser.l.php:extractOperations' => 'le montant TRNAMT d\'un relevé OFX, que la norme écrit sans séparateur de milliers : floatval() le lit sans autre contrôle',
		'accounting/bank/ui/CashflowOmniboxAi.u.php:readNumber' => self::OMNIBOX,
		'accounting/financialYear/lib/ClosingPrivateShareAi.l.php:getPostedRates' => 'les pourcentages postés de la part privée, lus hors de build() : is_numeric() juge chaque valeur, et une valeur illisible est écartée sans refus',
		'accounting/financialYear/lib/ClosingSaleBreakdownAi.l.php:getPostedAmounts' => 'les montants postés de la ventilation des ventes, lus hors de build() : is_numeric() juge chaque valeur, et une valeur illisible est écartée sans refus',
		'accounting/financialYear/lib/FecVerifyAi.l.php:readImport' => self::FEC,
		'accounting/financialYear/lib/Import.l.php:extractLines' => self::FEC,
		'accounting/financialYear/lib/Import.l.php:isInternal' => self::FEC,
		'accounting/financialYear/lib/Import.l.php:treatImport' => self::FEC,
		'accounting/financialYear/lib/RepriseAi.l.php:getFecKeys' => self::FEC,
		'accounting/financialYear/lib/RepriseAi.l.php:tiers' => self::FEC,
		'accounting/journal/ui/Journal.u.php:readOmniboxNumber' => self::OMNIBOX,
		'accounting/proof/lib/Proof.l.php:getSearch' => 'une recherche par montant dans les justificatifs : une expression régulière n\'en garde que deux décimales au plus, sinon la recherche ne porte pas sur un montant',
		'framework/core/Filter.php:normalize' => 'la normalisation elle-même, sur une chaîne dont la forme vient d\'être vérifiée',
		'ouvretaferme/learning/module/Date.e.php:castHours' => 'des heures saisies, converties en minutes sur une colonne entière, où build() ne normalise pas la virgule : is_numeric() juge le résultat',
		'ouvretaferme/main/lib/Csv.l.php:formatFloat' => 'les nombres d\'un CSV importé (produits, ventes, immobilisations) : rien ne les juge, et (float) lit « 1 000,50 » comme 1, à reprendre par \\Filter::normalize() et un contrôle',
		'production/plant/module/Plant.e.php:properties' => 'le poids d\'une caisse, que build() normalise déjà avant cette étape : l\'expression qui suit juge la forme et refuse la notation scientifique',
		'production/series/module/Task.e.php:castDecimal' => 'les quantités récoltées, propriétés hors du modèle que build() ne normalise pas : l\'expression qui suit juge la forme',
		'production/toolbox/lib/Rain.l.php:getImport' => 'les relevés d\'un CSV de pluie : is_numeric() juge la valeur avant CsvLib::formatFloat()',
	];

	const ARRAY_REFUSED = 'refuse un tableau sous son propre nom, et laisse le nombre au cast du cadriciel';
	const VARIANT_SKIPPED = 'saute la propriété d\'une variante, qui la reçoit du principal, sans lire le nombre';
	const SHARED_SKIPPED = 'saute le prix d\'une variante en prix commun, sans lire le nombre';
	const FREE_COURSE = 'pose 0 sur une formation gratuite, sans lire le nombre';
	const HOURS = 'lit des heures et les convertit en minutes : accepte la virgule (« 7,5 ») sur une colonne entière, que le cadriciel y refuse, et la notation scientifique, qu\'admet is_numeric()';
	const METER = 'refuse ce qui n\'est pas un entier écrit en chiffres, pour qu\'« abc » ne pose pas un compteur à zéro ; « 1 000 », normalisé avant elle, passe';
	const ONCE = 'vide la fréquence d\'un entretien ponctuel, sans lire le nombre';
	const WATER = 'refuse ce que is_numeric() refuse, avant le cast, et accepte comme lui la notation scientifique';

	/**
	 * Les étapes qui lisent une propriété numérique avant le cast du cadriciel, avec ce qu'elles acceptent
	 * de plus ou de moins que lui. Elles reçoivent la saisie déjà normalisée par `build()`.
	 */
	const STEP = [
		'accounting/taxReturn/module/Entry.e.php:value' => 'compose une surface en ares depuis les hectares et les ares postés, puis refuse un montant décimal, que le cast en entier tronquerait',
		'commercialisation/amap/module/Contract.e.php:absenceDelay' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:changeDelay' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:distributionsMax' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:distributionsMin' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:orderDelay' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:places' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:saleDaysBefore' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Contract.e.php:subscriptionDelay' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/ContractProduct.e.php:number' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/ContractProduct.e.php:orderMax' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/ContractProduct.e.php:orderMin' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Producer.e.php:contentAnnouncedDays' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/Subscription.e.php:number' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/SubscriptionDistribution.e.php:number' => self::ARRAY_REFUSED,
		'commercialisation/amap/module/SubscriptionDistributionProduct.e.php:number' => self::ARRAY_REFUSED,
		'base/product/module/Product.e.php:preparationDays' => self::VARIANT_SKIPPED,
		'base/product/module/Product.e.php:privatePrice' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:privatePriceInitial' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:privateStep' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:proPackaging' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:proPrice' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:proPriceInitial' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:proStep' => self::SHARED_SKIPPED,
		'base/product/module/Product.e.php:vat' => self::VARIANT_SKIPPED,
		'ouvretaferme/learning/module/Course.e.php:price' => self::FREE_COURSE,
		'ouvretaferme/learning/module/Course.e.php:priceFunded' => self::FREE_COURSE,
		'ouvretaferme/learning/module/Date.e.php:minutes' => self::HOURS,
		'ouvretaferme/learning/module/DateEnrollment.e.php:attendedMinutes' => self::HOURS,
		'production/plant/module/Plant.e.php:crateWeight' => 'juge la forme d\'un décimal écrit en chiffres et refuse la notation scientifique, que le cast du cadriciel admet',
		'production/plant/module/Variety.e.php:stock' => 'remplace le cast : convertit des grammes en graines par le poids de mille graines ; la colonne étant entière, la virgule n\'y est pas normalisée, et « 1,5 » g se refuse quand « 1.5 » g passe',
		'production/series/module/Timesheet.e.php:time' => 'refuse une durée absente d\'une saisie par jour, sans lire le nombre',
		'production/toolbox/module/EquipmentReading.e.php:value' => self::METER,
		'production/toolbox/module/Maintenance.e.php:everyMonths' => self::ONCE,
		'production/toolbox/module/Maintenance.e.php:everyValue' => self::ONCE,
		'production/toolbox/module/MaintenanceDone.e.php:value' => 'oublie la valeur postée quand le compteur n\'est pas mis à jour, puis la juge comme un relevé : '.self::METER,
		'production/toolbox/module/WaterReading.e.php:changedValue' => self::WATER,
		'production/toolbox/module/WaterReading.e.php:value' => self::WATER,
	];

	/**
	 * Les propriétés décimales sans `min` ou sans `max`. Le générateur pose les deux : la déclaration
	 * reste vide.
	 */
	const UNBOUNDED = [];

	public function init(): void {

		foreach($this->getPhpTokens() as $file => $tokens) {

			// Un test ne lit pas de saisie : il relit au plus ce qu'un écran affiche, pour le comparer
			if(str_ends_with($file, '.t.php') or self::isDispensed($file)) {
				continue;
			}

			foreach(self::findConversions($tokens) as [$function, $line]) {
				$this->addFoundKey(self::RULE_CONVERSION, $file, $function, $line);
			}

		}

		foreach(SentinelLib::getFiles('.e.php') as $file => $path) {

			if(str_contains($file, '/module/') === FALSE) {
				continue;
			}

			$class = SentinelLib::getNamespace(SentinelLib::getTokens(file_get_contents($path))).'\\'.basename($file, '.e.php');

			if(class_exists($class) === FALSE or is_subclass_of($class, \Element::class) === FALSE) {
				continue;
			}

			$m = $class::model();

			foreach($m->getProperties() as $property) {

				$mask = $m->getProperty($property);

				if($mask[0] === 'decimal' and (($mask['min'] ?? NULL) === NULL or ($mask['max'] ?? NULL) === NULL)) {
					$this->addFoundKey(self::RULE_UNBOUNDED, $file, $property, 0);
				}

			}

			if(method_exists($class, 'properties') === FALSE) {
				continue;
			}

			$l = new \PropertyList();
			$class::properties($l);

			foreach($l->getDeclared() as $property => $eProperty) {

				if($m->hasProperty($property) === FALSE or self::isNumeric($m->getPropertyType($property)) === FALSE) {
					continue;
				}

				$line = self::getStepLine($eProperty);

				if($line !== NULL) {
					$this->addFoundKey(self::RULE_STEP, $file, $property, $line);
				}

			}

		}

	}

	public function testConversionsAreDeclared(): void {
		$this->assertDeclared(self::RULE_CONVERSION, self::CONVERSION, 'une virgule convertie en point à la main se déclare avec ce qui la contrôle ; quand elle est juste, la déclarer avec sa raison avant de penser à la retirer, et sur une saisie de formulaire ou d\'API, \Filter::normalize() la remplace');
	}

	public function testStepsAreDeclared(): void {
		$this->assertDeclared(self::RULE_STEP, self::STEP, 'une étape qui lit une propriété numérique avant le cast du cadriciel se déclare avec ce qu\'elle accepte de plus ou de moins que lui');
	}

	public function testDecimalsAreBounded(): void {
		$this->assertDeclared(self::RULE_UNBOUNDED, self::UNBOUNDED, 'une propriété décimale déclare min et max dans son .yml, sans quoi Filter::getRange() la borne comme un int32');
	}

	public function testDetectorFindsConversions(): void {

		$code = '<?php
			function a($v) { return str_replace(\',\', \'.\', $v); }
			function b($v) { return str_replace([\' \', \',\'], [\'\', \'.\'], $v); }
			class C {
				public function d($v) {
					$f = function($x) { return str_replace(\',\', \'.\', $x); };
					return "{$v}";
				}
				abstract public function e();
				public function f($v) { return str_replace(\'.\', \',\', $v); }
				public function g($v) { return $this->str_replace(\',\', \'.\'); }
			}
			str_replace(",", ".", $top);
		';

		$this->assertEquals(
			[['a', 2], ['b', 3], ['d', 6], ['{main}', 13]],
			self::findConversions(SentinelLib::getTokens($code)),
			'une virgule remplacée par un point est trouvée, sous sa fonction nommée et non sous la closure qui la porte ; l\'inverse et une méthode homonyme ne le sont pas'
		);

	}

	public function testDetectorFindsSteps(): void {

		$this->assertEquals(NULL, self::getStepLine(new \Property('x')), 'une propriété sans étape avant le cast n\'est pas relevée');
		$this->assertEquals(NULL, self::getStepLine(new \Property('x')->addAfterCheck('positive', fn() => TRUE)), 'une étape après le contrôle a déjà le nombre du cadriciel');
		$this->assertEquals(__LINE__, self::getStepLine(new \Property('x')->replaceCast(fn() => TRUE)), 'un cast remplacé est relevé à sa ligne');
		$this->assertEquals(__LINE__, self::getStepLine(new \Property('x')->addBeforeCast('number', fn() => TRUE)), 'une étape avant le cast est relevée à sa ligne');

	}

	/**
	 * Les appels à `str_replace()` qui remplacent une virgule par un point, chacun sous la fonction
	 * nommée qui le contient, `{main}` hors de toute fonction. Une closure n'a pas de nom stable : son
	 * appel se range sous la fonction qui la déclare.
	 *
	 * @return array<int, array{0: string, 1: int}>
	 */
	private static function findConversions(array $tokens): array {

		$found = [];
		$functions = [];
		$depth = 0;
		$pending = NULL;

		foreach($tokens as $i => [$type, $text, $line]) {

			if($type === T_FUNCTION) {

				$next = ($tokens[$i + 1][0] === '&') ? $i + 2 : $i + 1;

				if($tokens[$next][0] === T_STRING) {
					$pending = $tokens[$next][1];
				}

			} else if($type === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {

				$depth++;

				if($pending !== NULL) {
					$functions[] = [$pending, $depth];
					$pending = NULL;
				}

			} else if($type === '}') {

				if($functions !== [] and end($functions)[1] === $depth) {
					array_pop($functions);
				}

				$depth--;

			} else if($type === ';') {
				// Une méthode abstraite ou d'interface n'a pas de corps
				$pending = NULL;
			} else if(
				$type === T_STRING and
				$text === 'str_replace' and
				$tokens[$i + 1][0] === '(' and
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], TRUE) === FALSE
			) {

				[$search, $replace] = self::getFirstArguments($tokens, $i + 1);

				foreach($search as $position => $string) {

					// Un remplacement unique vaut pour toutes les chaînes cherchées, comme dans str_replace()
					$replacement = (count($replace) === 1) ? $replace[0] : ($replace[$position] ?? NULL);

					if($string === ',' and $replacement === '.') {
						$found[] = [$functions === [] ? '{main}' : end($functions)[0], $line];
						break;
					}

				}

			}

		}

		return $found;

	}

	/**
	 * Les chaînes littérales des deux premiers arguments de l'appel ouvert en `$open`, dans l'ordre où
	 * elles s'écrivent : `','` rend `[',']`, `[' ', ',']` rend `[' ', ',']`.
	 *
	 * @return array{0: string[], 1: string[]}
	 */
	private static function getFirstArguments(array $tokens, int $open): array {

		$arguments = [[], []];
		$argument = 0;
		$depth = 0;

		for($j = $open + 1, $closing = SentinelLib::getClosing($tokens, $open); $j < $closing and $argument < 2; $j++) {

			$type = $tokens[$j][0];

			if($type === '(' or $type === '[') {
				$depth++;
			} else if($type === ')' or $type === ']') {
				$depth--;
			} else if($type === ',' and $depth === 0) {
				$argument++;
			} else if($type === T_CONSTANT_ENCAPSED_STRING) {
				$arguments[$argument][] = substr($tokens[$j][1], 1, -1);
			}

		}

		return $arguments;

	}

	/**
	 * La ligne de la première étape qui lit la saisie avant le cast du cadriciel, NULL s'il n'y en a pas.
	 */
	private static function getStepLine(\Property $eProperty): ?int {

		$callback = $eProperty->cast ?? (array_values($eProperty->beforeCast)[0] ?? NULL);

		return ($callback === NULL) ? NULL : new \ReflectionFunction($callback)->getStartLine();

	}

	private static function isNumeric(string $type): bool {

		return (
			str_starts_with($type, 'int') or
			str_starts_with($type, 'float') or
			$type === 'decimal'
		);

	}

	private static function isDispensed(string $file): bool {

		foreach(self::DIRECTORIES as $directory => $reason) {

			if(str_contains('/'.$file, $directory)) {
				return TRUE;
			}

		}

		return FALSE;

	}

}
?>
