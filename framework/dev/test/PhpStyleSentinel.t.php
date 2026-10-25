<?php
namespace dev;

/**
 * Règles de la section « Style PHP » du CLAUDE.md.
 *
 * Lancement : dev/test package=dev file=PhpStyleSentinel
 */
class PhpStyleSentinelTest extends SentinelTest {

	const BOOLEAN_OPERATOR = [
		'accounting/preaccounting/lib/SuggestionInvoice.l.php' => 5,
		'accounting/thirdParty/lib/ThirdParty.l.php' => 1,
		'base/user/lib/Geolite.l.php' => 3,
		'framework/storage/lib/ColorExtractor/ColorExtractor.php' => 2,
		'framework/storage/lib/ColorExtractor/Palette.php' => 2,
		'framework/util/lib/CrawlerDetect.php' => 1,
		'framework/util/ui/Http.u.php' => 1,
		'internal/securing/lib/Log.l.php' => 1,
	];
	const NEW_PARENTHESIS = [
		'accounting/bank/ui/BankAccount.u.php' => 1,
		'base/media/page/index.p.php' => 1,
		'base/structure/page/member.p.php' => 1,
		'base/user/page/close.p.php' => 1,
		'base/user/page/update.p.php' => 1,
		'commercialisation/shop/ui/Basket.u.php' => 1,
		'framework/core/Action.php' => 3,
		'framework/core/Module.php' => 4,
		'framework/core/Route.php' => 1,
		'framework/core/View.php' => 1,
		'framework/dev/lib/ApiDoc.l.php' => 1,
		'framework/dev/page/dependency.p.php' => 1,
		'framework/dev/page/module.p.php' => 3,
		'framework/language/page/do.p.php' => 1,
		'framework/language/page/prepare.p.php' => 1,
		'framework/language/page/stats.p.php' => 1,
		'ouvretaferme/main/page/cron/backup.p.php' => 1,
		'ouvretaferme/main/page/doc/editor.p.php' => 1,
		'ouvretaferme/main/page/doc/main.p.php' => 1,
		'ouvretaferme/website/page/public.p.php' => 1,
		'production/map/page/bed.p.php' => 1,
		'production/sequence/page/flow.p.php' => 1,
		'production/series/page/comment.p.php' => 1,
		'production/series/page/series.p.php' => 2,
		'production/series/page/task.p.php' => 1,
		'production/series/page/timesheet.p.php' => 3,
		'production/series/ui/Planning.u.php' => 1,
		'production/series/ui/Task.u.php' => 1,
	];
	const MATCH_TRUE = [
		'accounting/cash/lib/Suggestion.l.php' => 1,
		'accounting/cash/lib/Transaction.l.php' => 2,
		'accounting/cash/ui/Cash.u.php' => 3,
		'accounting/cash/ui/Transaction.u.php' => 2,
		'accounting/company/lib/StatisticAccountingAi.l.php' => 1,
		'accounting/invoicing/lib/UblBuilder.l.php' => 1,
		'accounting/overview/ui/DashboardAi.u.php' => 1,
		'accounting/preaccounting/lib/Resume.l.php' => 2,
		'base/structure/lib/SchoolAi.l.php' => 1,
		'base/structure/ui/ApiKey.u.php' => 2,
		'framework/dev/lib/ApiDoc.l.php' => 2,
		'ouvretaferme/admin/lib/StatisticAccountingAi.l.php' => 1,
		'ouvretaferme/admin/ui/StatisticAi.u.php' => 2,
	];
	const HIDDEN_ELEMENT_ID = [
		'accounting/account/ui/Account.u.php' => 1,
		'accounting/asset/ui/Allocation.u.php' => 2,
		'accounting/asset/ui/Depreciation.u.php' => 2,
		'accounting/asset/ui/Recovery.u.php' => 2,
		'accounting/asset/ui/Register.u.php' => 6,
		'accounting/asset/ui/Revision.u.php' => 2,
		'accounting/bank/ui/BankAccount.u.php' => 1,
		'accounting/bank/ui/Cashflow.u.php' => 13,
		'accounting/bank/ui/EnableBanking.u.php' => 2,
		'accounting/bank/ui/Import.u.php' => 1,
		'accounting/bank/ui/Reconciliation.u.php' => 4,
		'accounting/cash/ui/Cash.u.php' => 3,
		'accounting/cash/ui/Register.u.php' => 4,
		'accounting/cash/ui/Transaction.u.php' => 3,
		'accounting/company/ui/AccountingHelp.u.php' => 1,
		'accounting/company/ui/Company.u.php' => 1,
		'accounting/company/ui/OnboardingAi.u.php' => 2,
		'accounting/financialYear/ui/FinancialYear.u.php' => 13,
		'accounting/financialYear/ui/Import.u.php' => 1,
		'accounting/financialYear/ui/Inventory.u.php' => 4,
		'accounting/financialYear/ui/ResultAllocation.u.php' => 4,
		'accounting/invoicing/ui/Event.u.php' => 1,
		'accounting/invoicing/ui/Invoicing.u.php' => 1,
		'accounting/invoicing/ui/Line.u.php' => 1,
		'accounting/journal/ui/JournalCode.u.php' => 1,
		'accounting/journal/ui/OperationWrite.u.php' => 11,
		'accounting/preaccounting/ui/Preaccounting.u.php' => 1,
		'accounting/preaccounting/ui/Purchase.u.php' => 2,
		'accounting/preaccounting/ui/Reconciliate.u.php' => 2,
		'accounting/preaccounting/ui/SaleAccount.u.php' => 1,
		'accounting/preaccounting/view/sale.v.php' => 1,
		'accounting/thirdParty/ui/ThirdParty.u.php' => 3,
		'accounting/vat/ui/RefundAi.u.php' => 1,
		'accounting/vat/ui/Vat.u.php' => 5,
		'base/hr/ui/Presence.u.php' => 2,
		'base/mail/ui/Contact.u.php' => 1,
		'base/structure/ui/ApiKey.u.php' => 1,
		'base/structure/ui/Configuration.u.php' => 2,
		'base/structure/ui/Member.u.php' => 3,
		'base/structure/ui/Production.u.php' => 1,
		'base/structure/ui/Role.u.php' => 1,
		'base/structure/ui/Section.u.php' => 2,
		'base/structure/ui/Structure.u.php' => 4,
		'base/structure/ui/StructureCommunity.u.php' => 1,
		'base/structure/ui/Survey.u.php' => 1,
		'base/user/ui/Admin.u.php' => 1,
		'base/user/ui/Ban.u.php' => 1,
		'commercialisation/amap/ui/Producer.u.php' => 1,
		'commercialisation/payment/ui/Link.u.php' => 2,
		'commercialisation/payment/ui/Method.u.php' => 1,
		'commercialisation/payment/ui/Wallet.u.php' => 1,
		'commercialisation/catalog/ui/Catalog.u.php' => 2,
		'base/product/ui/Category.u.php' => 2,
		'commercialisation/catalog/ui/GroupAi.u.php' => 1,
		'commercialisation/catalog/ui/Offer.u.php' => 1,
		'base/product/ui/Product.u.php' => 3,
		'base/product/ui/Stock.u.php' => 2,
		'base/product/ui/Unit.u.php' => 1,
		'base/product/ui/VariantAi.u.php' => 1,
		'commercialisation/quotation/ui/Community.u.php' => 2,
		'commercialisation/quotation/ui/Sector.u.php' => 1,
		'commercialisation/selling/ui/Customer.u.php' => 4,
		'commercialisation/selling/ui/CustomerGroup.u.php' => 2,
		'commercialisation/selling/ui/CustomerMergeAi.u.php' => 2,
		'commercialisation/selling/ui/Invoice.u.php' => 2,
		'commercialisation/selling/ui/Item.u.php' => 6,
		'commercialisation/selling/ui/Market.u.php' => 2,
		'commercialisation/selling/ui/Merchant.u.php' => 1,
		'commercialisation/selling/ui/Sale.u.php' => 8,
		'commercialisation/shop/ui/Date.u.php' => 1,
		'commercialisation/shop/ui/Department.u.php' => 2,
		'commercialisation/shop/ui/Range.u.php' => 1,
		'commercialisation/shop/ui/Share.u.php' => 1,
		'commercialisation/shop/ui/Shop.u.php' => 7,
		'internal/securing/ui/Admin.u.php' => 1,
		'ouvretaferme/analyze/ui/Report.u.php' => 2,
		'ouvretaferme/association/ui/Admin.u.php' => 1,
		'ouvretaferme/association/ui/Membership.u.php' => 1,
		'ouvretaferme/website/ui/Manage.u.php' => 2,
		'ouvretaferme/website/ui/Menu.u.php' => 1,
		'ouvretaferme/website/ui/News.u.php' => 1,
		'ouvretaferme/website/ui/Webpage.u.php' => 3,
		'production/gallery/ui/Photo.u.php' => 1,
		'production/map/ui/Bed.u.php' => 1,
		'production/map/ui/Onboarding.u.php' => 1,
		'production/map/ui/Plot.u.php' => 4,
		'production/map/ui/Zone.u.php' => 2,
		'production/plant/ui/Forecast.u.php' => 1,
		'production/plant/ui/Plant.u.php' => 3,
		'production/plant/ui/Size.u.php' => 1,
		'production/plant/ui/Variety.u.php' => 1,
		'production/sequence/ui/Crop.u.php' => 2,
		'production/sequence/ui/Flow.u.php' => 3,
		'production/sequence/ui/Sequence.u.php' => 2,
		'production/series/ui/Comment.u.php' => 1,
		'production/series/ui/CopyAi.u.php' => 2,
		'production/series/ui/Cultivation.u.php' => 2,
		'production/series/ui/Place.u.php' => 1,
		'production/series/ui/Planning.u.php' => 2,
		'production/series/ui/Series.u.php' => 12,
		'production/series/ui/Task.u.php' => 5,
		'production/series/ui/Timesheet.u.php' => 1,
		'production/work/ui/Action.u.php' => 2,
		'production/work/ui/Category.u.php' => 2,
		'production/work/ui/Method.u.php' => 2,
		'production/work/ui/Supplier.u.php' => 1,
		'production/work/ui/Tool.u.php' => 2,
	];
	const HANDWRITTEN_INPUT = [
		'accounting/account/ui/Account.u.php' => 2,
		'accounting/articles/ui/Articles.u.php' => 1,
		'accounting/bank/ui/Cashflow.u.php' => 1,
		'accounting/invoicing/ui/Event.u.php' => 1,
		'accounting/journal/ui/Journal.u.php' => 1,
		'accounting/preaccounting/ui/Preaccounting.u.php' => 7,
		'accounting/preaccounting/ui/Reconciliate.u.php' => 2,
		'commercialisation/catalog/ui/Offer.u.php' => 6,
		'base/product/ui/Product.u.php' => 2,
		'commercialisation/selling/ui/Customer.u.php' => 2,
		'commercialisation/selling/ui/Invoice.u.php' => 4,
		'commercialisation/selling/ui/Sale.u.php' => 5,
		'ouvretaferme/analyze/ui/Report.u.php' => 2,
		'production/map/ui/Plan.u.php' => 2,
		'production/series/ui/Cultivation.u.php' => 4,
		'production/series/ui/Task.u.php' => 3,
	];
	const ARIA = [
		'framework/util/ui/Text.u.php' => 2,
	];
	const TRIM_STRING = [
		'accounting/asset/module/Depreciation.e.php' => 1,
		'accounting/financialYear/lib/Import.l.php' => 3,
		'accounting/financialYear/test/ResultAllocationAi.t.php' => 1,
		'accounting/overview/ui/BalanceSheet.u.php' => 2,
		'accounting/preaccounting/lib/FecCheck.l.php' => 1,
		'commercialisation/quotation/lib/Contribute.l.php' => 2,
		'commercialisation/quotation/lib/Result.l.php' => 1,
		'commercialisation/quotation/lib/Suggestion.l.php' => 3,
		'framework/core/Function.php' => 1,
	];
	const DESCRIBER_LAST = [
		'accounting/company/ui/AccountingHelp.u.php' => 1,
		'accounting/financialYear/ui/FinancialYear.u.php' => 1,
		'base/structure/ui/Section.u.php' => 1,
		'base/structure/ui/Structure.u.php' => 1,
		'commercialisation/selling/ui/Item.u.php' => 1,
		'commercialisation/shop/ui/Range.u.php' => 1,
		'internal/communication/ui/WebinaireSubscription.u.php' => 1,
		'internal/data/ui/PdpHistory.u.php' => 1,
		'production/sequence/ui/Flow.u.php' => 1,
	];

	// Le parent définit le comportement que la règle interdit de recopier
	const CAN_WRITE_DUPLICATE = [
		'framework/core/Data.php' => 1,
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			$this->addFound('booleanOperator', $file, $this->findBooleanOperator($tokens));
			$this->addFound('coalescePrecedence', $file, $this->findCoalescePrecedence($tokens));
			$this->addFound('newParenthesis', $file, $this->findNewParenthesis($tokens));
			$this->addFound('matchTrue', $file, $this->findMatchTrue($tokens));
			$this->addFound('canWriteDuplicate', $file, $this->findCanWriteDuplicate($tokens));
			$this->addFound('hiddenElementId', $file, $this->findHiddenElementId($tokens));
			$this->addFound('aria', $file, $this->findAria($tokens));
			$this->addFound('trimString', $file, $this->findTrimString($tokens));
			$this->addFound('isObjectProperty', $file, $this->findIsObjectProperty($tokens));

			if(self::isPresentation($file)) {
				$this->addFound('handwrittenInput', $file, $this->findHandwrittenInput($tokens));
			}

			if(str_ends_with($file, '.u.php')) {
				$this->addFound('describerLast', $file, $this->findDescriberNotLast($tokens));
			}

		}

	}

	/** @return int[] */
	private function findBooleanOperator(array $tokens): array {

		$lines = [];

		foreach($tokens as [$type, , $line]) {

			if($type === T_BOOLEAN_AND or $type === T_BOOLEAN_OR) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * `??` suivi, dans la même opérande, d'une comparaison : `$a ?? NULL === NULL` compare `NULL` à
	 * `NULL` et rend `$a ?? TRUE`.
	 *
	 * @return int[]
	 */
	private function findCoalescePrecedence(array $tokens): array {

		$lines = [];
		$count = count($tokens);

		$comparisons = [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL, '<', '>', T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, T_SPACESHIP, T_INSTANCEOF];
		$ends = [';', ',', '?', ':', '=', T_DOUBLE_ARROW, T_LOGICAL_AND, T_LOGICAL_OR, T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE, T_CLOSE_TAG];

		foreach($tokens as $i => [$type, , $line]) {

			if($type !== T_COALESCE) {
				continue;
			}

			for($j = $i + 1; $j < $count; $j++) {

				$next = $tokens[$j][0];

				if(in_array($next, ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE)) {
					$j = SentinelLib::getClosing($tokens, $j);
				} else if(in_array($next, [')', ']', '}'], TRUE) or in_array($next, $ends, TRUE)) {
					break;
				} else if(in_array($next, $comparisons, TRUE)) {
					$lines[] = $line;
					break;
				}

			}

		}

		return $lines;

	}

	/**
	 * `(new Foo())->method()` : la parenthèse ouvrante groupe quand rien ne l'appelle, ni un nom de
	 * fonction ou de méthode, ni une construction du langage.
	 *
	 * @return int[]
	 */
	private function findNewParenthesis(array $tokens): array {

		$lines = [];

		$callers = [
			T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_VARIABLE, ')', ']', '}',
			T_ISSET, T_EMPTY, T_ARRAY, T_LIST, T_UNSET, T_EXIT, T_EVAL, T_STATIC, T_CLASS,
			T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_MATCH, T_CATCH, T_DECLARE, T_FUNCTION, T_FN, T_USE
		];

		foreach($tokens as $i => [$type, , $line]) {

			if(
				$type !== '(' or
				($tokens[$i + 1][0] ?? NULL) !== T_NEW or
				in_array($tokens[$i - 1][0] ?? NULL, $callers, TRUE)
			) {
				continue;
			}

			$closing = SentinelLib::getClosing($tokens, $i);

			if(in_array($tokens[$closing + 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, '['], TRUE)) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/** @return int[] */
	private function findMatchTrue(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, , $line]) {

			if(
				$type === T_MATCH and
				($tokens[$i + 1][0] ?? NULL) === '(' and
				strtoupper($tokens[$i + 2][1] ?? '') === 'TRUE' and
				($tokens[$i + 3][0] ?? NULL) === ')'
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/** @return int[] */
	private function findCanWriteDuplicate(array $tokens): array {

		$lines = [];
		$expected = ['function', 'canWrite', '(', ')', ':', 'bool', '{', 'return', '$this', '->', 'canRead', '(', ')', ';', '}'];

		foreach($tokens as $i => [$type, , $line]) {

			if($type !== T_FUNCTION) {
				continue;
			}

			$texts = array_map(fn(array $token) => $token[1], array_slice($tokens, $i, count($expected)));

			if($texts === $expected) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * `->hidden('plant', $ePlant['id'])` : `hidden()` accepte l'élément.
	 *
	 * @return int[]
	 */
	private function findHiddenElementId(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type !== T_STRING or
				$text !== 'hidden' or
				($tokens[$i - 1][0] ?? NULL) !== T_OBJECT_OPERATOR or
				($tokens[$i + 1][0] ?? NULL) !== '('
			) {
				continue;
			}

			$closing = SentinelLib::getClosing($tokens, $i + 1);

			for($j = $i + 2; $j < $closing; $j++) {

				if(
					in_array($tokens[$j - 1][0], ['(', ','], TRUE) and
					$tokens[$j][0] === T_VARIABLE and
					preg_match('/^\$e[A-Z]/', $tokens[$j][1]) === 1 and
					$tokens[$j + 1][0] === '[' and
					in_array($tokens[$j + 2][1], ["'id'", '"id"'], TRUE) and
					$tokens[$j + 3][0] === ']' and
					in_array($tokens[$j + 4][0], [')', ','], TRUE)
				) {
					$lines[] = $line;
				}

			}

		}

		return $lines;

	}

	/**
	 * Un `<input type="hidden">` ou `type="checkbox"` écrit à la main dans une chaîne.
	 *
	 * @return int[]
	 */
	private function findHandwrittenInput(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			$string = self::getString($token);

			if($string !== NULL and preg_match('/type=\\\\?["\']?(hidden|checkbox)\b/i', $string) === 1) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/** @return int[] */
	private function findAria(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			$string = self::getString($token);

			if($string !== NULL and preg_match('/\baria-[a-z]/', $string) === 1) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/** @return int[] */
	private function findTrimString(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type === T_STRING and
				strtolower($text) === 'trim' and
				($tokens[$i + 1][0] ?? NULL) === '(' and
				($tokens[$i + 2][0] ?? NULL) === T_STRING_CAST
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * `is_object($e['operation'])` : une propriété déléguée est toujours un élément.
	 *
	 * @return int[]
	 */
	private function findIsObjectProperty(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type === T_STRING and
				strtolower($text) === 'is_object' and
				($tokens[$i + 1][0] ?? NULL) === '(' and
				($tokens[$i + 2][0] ?? NULL) === T_VARIABLE and
				($tokens[$i + 3][0] ?? NULL) === '['
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * La ligne de `function p()` quand une méthode nommée la suit.
	 *
	 * @return int[]
	 */
	private function findDescriberNotLast(array $tokens): array {

		$methods = [];

		foreach($tokens as $i => [$type, , $line]) {

			if($type === T_FUNCTION and ($tokens[$i + 1][0] ?? NULL) === T_STRING) {
				$methods[] = [$tokens[$i + 1][1], $line];
			}

		}

		foreach($methods as $position => [$name, $line]) {

			if($name === 'p' and $position !== array_key_last($methods)) {
				return [$line];
			}

		}

		return [];

	}

	public function testDetectors(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			$a = ($b && $c) || $d;
			$e = $f ?? NULL === NULL;
			$g = ($f ?? NULL) === NULL;
			$h = (new Foo())->bar();
			$i = baz(new Foo())->bar();
			if((new Foo)->ok) {}
			$j = new Foo()->bar();
			$k = match(TRUE) { default => 1 };
			$l = match($m) { default => 1 };
			$h .= $form->hidden('plant', $ePlant['id']);
			$h .= $form->hidden('plant', $ePlant);
			$h .= $form->hidden('plant', $ePlant['id'] + 1);
			$h .= '<input type="hidden" name="a">';
			$h .= "<div aria-label=\"x\">{$a}</div>";
			$n = trim((string)$o);
			$n = trim($o);
			$p = is_object($eOperation['operation']);
			PHP);

		$this->assertEquals([2, 2], $this->findBooleanOperator($tokens), 'booleanOperator');
		$this->assertEquals([3], $this->findCoalescePrecedence($tokens), 'coalescePrecedence');
		$this->assertEquals([5, 7], $this->findNewParenthesis($tokens), 'newParenthesis');
		$this->assertEquals([9], $this->findMatchTrue($tokens), 'matchTrue');
		$this->assertEquals([11], $this->findHiddenElementId($tokens), 'hiddenElementId');
		$this->assertEquals([14], $this->findHandwrittenInput($tokens), 'handwrittenInput');
		$this->assertEquals([15], $this->findAria($tokens), 'aria');
		$this->assertEquals([16], $this->findTrimString($tokens), 'trimString');
		$this->assertEquals([18], $this->findIsObjectProperty($tokens), 'isObjectProperty');

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			class A {
				public function canWrite(): bool {
					return $this->canRead();
				}
				public function canRead(): bool {
					return $this->canWrite() and TRUE;
				}
			}
			class AUi {
				public static function p(string $property): \PropertyDescriber {
					return new \PropertyDescriber($property, function() {});
				}
				public function display(): string {
					return '';
				}
			}
			PHP);

		$this->assertEquals([3], $this->findCanWriteDuplicate($tokens), 'canWriteDuplicate');
		$this->assertEquals([11], $this->findDescriberNotLast($tokens), 'describerLast');

	}

	public function testBooleanOperator(): void {
		$this->assertRatchet('booleanOperator', self::BOOLEAN_OPERATOR, '&& ou || au lieu de and ou or');
	}

	public function testCoalescePrecedence(): void {
		$this->assertRatchet('coalescePrecedence', [], '?? suivi d\'une comparaison, à parenthéser');
	}

	public function testNewParenthesis(): void {
		$this->assertRatchet('newParenthesis', self::NEW_PARENTHESIS, '(new Foo())->, à écrire new Foo()->');
	}

	public function testMatchTrue(): void {
		$this->assertRatchet('matchTrue', self::MATCH_TRUE, 'match(TRUE), à écrire en if / else if');
	}

	public function testCanWriteDuplicate(): void {
		$this->assertRatchet('canWriteDuplicate', self::CAN_WRITE_DUPLICATE, 'canWrite() recopie canRead(), déjà le comportement du parent');
	}

	public function testHiddenElementId(): void {
		$this->assertRatchet('hiddenElementId', self::HIDDEN_ELEMENT_ID, '->hidden() reçoit $e[\'id\'], à passer l\'élément');
	}

	public function testHandwrittenInput(): void {
		$this->assertRatchet('handwrittenInput', self::HANDWRITTEN_INPUT, 'input hidden ou checkbox écrit à la main, à produire par FormUi');
	}

	public function testAria(): void {
		$this->assertRatchet('aria', self::ARIA, 'attribut aria');
	}

	public function testTrimString(): void {
		$this->assertRatchet('trimString', self::TRIM_STRING, 'trim((string) …)');
	}

	public function testIsObjectProperty(): void {
		$this->assertRatchet('isObjectProperty', [], 'is_object() sur une propriété, un délégué est toujours un élément');
	}

	public function testDescriberLast(): void {
		$this->assertRatchet('describerLast', self::DESCRIBER_LAST, 'p() avant d\'autres méthodes, à placer en bas du fichier');
	}

}
?>
