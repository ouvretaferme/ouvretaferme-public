<?php
namespace dev;

/**
 * Règles de la section « Rédaction » du CLAUDE.md.
 *
 * « ferme » n'a pas de sentinelle : ses exceptions (la production, la mercuriale) se lisent dans
 * le sens du texte, pas dans son emplacement.
 *
 * Les règles sur les mots ne lisent que le texte d'interface : les arguments de `s()` et `p()` en
 * PHP, les chaînes à guillemets doubles et les gabarits des JS de package. Jamais les commentaires,
 * ni l'apostrophe en JS, qui y délimite des sélecteurs et des clés, ni les tests, dont les chaînes
 * plantent la forme fautive pour l'éprouver.
 *
 * Il n'y a aucune liste transverse de mots interdits : « porter » reste juste dans « la livraison
 * porte deux modes ». Ce qui se refuse est un verbe du modèle dont le sujet est un objet, une forme
 * redressée en relecture (la liste ne s'allonge qu'à un redressement, jamais d'avance), dont
 * « Veuillez », redressé le 2026-10-16 au profit de l'impératif (« Choisissez une date »), et un mot
 * refusé par le lexique de son domaine (`.claude/lexique/*.md`), borné à la portée de sa ligne.
 *
 * Lancement : dev/test package=dev file=WritingSentinel
 */
class WritingSentinelTest extends SentinelTest {

	/**
	 * Un verbe du modèle employé pour un objet. Les objets nommés sont ceux que les retours ont vus
	 * sujets de « porter » ; « naît », « désigner » et « doit porter » sont relevés tels qu'ils
	 * ont été renvoyés.
	 */
	const OBJECT_VERB_PATTERNS = [
		'/(?<!\p{L})(compte|fiche|écriture|ligne|case)s?(?!\p{L})[^.!?;:]{0,40}?(?<!\p{L})(ne |n\')?port(e|es|ent|ait|aient|era|eront)(?!\p{L})/iu',
		'/(?<!\p{L})naît(?!\p{L})/iu',
		'/(?<!\p{L})désigner(?!\p{L})/iu',
		'/(?<!\p{L})doi(t|vent) porter(?!\p{L})/iu',
	];

	/**
	 * Les formes redressées en relecture : « de même rang » pour une planche (2026-09-27), « passer
	 * sur » une offre.
	 */
	const REDRESSED_PATTERNS = [
		'/(?<!\p{L})de même rang(?!\p{L})/iu',
		'/(?<!\p{L})pass(e|es|er|ent|é|ée|és|ées)\s+sur\s+(une|l\'|la|les|cette|chaque)\s*offres?(?!\p{L})/iu',
	];

	/**
	 * Une forme redressée, tenue hors de REDRESSED parce que sa dette se compte par
	 * centaines quand l'autre se compte à l'unité : dans un même cliquet, un « de même rang » corrigé
	 * s'échangerait contre un « Veuillez » neuf sans que le compte d'un fichier bouge. La minuscule
	 * compte : « Aucun fichier n'a été reçu, veuillez sélectionner un fichier » se redresse comme la
	 * phrase qui l'ouvre.
	 */
	const VEUILLEZ_PATTERNS = [
		'/(?<!\p{L})veuillez(?!\p{L})/iu',
	];

	const OBJECT_VERB = [
		'accounting/articles/ui/ArticleAutoInvoicing.u.php' => 1,
		'accounting/articles/view/doc.v.php' => 15,
		'accounting/asset/ui/Alert.u.php' => 1,
		'accounting/asset/ui/Csv.u.php' => 1,
		'accounting/asset/ui/Register.u.php' => 1,
		'accounting/financialYear/ui/Dev.o.php' => 1,
		'accounting/financialYear/ui/FinancialYear.u.php' => 5,
		'accounting/financialYear/ui/HasVatAi.u.php' => 3,
		'accounting/invoicing/ui/BusinessRuleAi.u.php' => 1,
		'accounting/journal/ui/Alert.u.php' => 6,
		'accounting/journal/ui/Dev.o.php' => 1,
		'accounting/journal/ui/PatternLine.u.php' => 2,
		'accounting/journal/ui/Pattern.u.php' => 3,
		'accounting/taxReturn/ui/Control.u.php' => 15,
		'accounting/taxReturn/ui/FixedAsset.u.php' => 4,
		'accounting/taxReturn/ui/Result.u.php' => 2,
		'accounting/vat/ui/Dev.o.php' => 1,
		'commercialisation/amap/ui/Subscription.u.php' => 1,
		'commercialisation/catalog/ui/Alert.u.php' => 1,
		'base/product/ui/Alert.u.php' => 2,
		'commercialisation/selling/ui/Alert.u.php' => 1,
		'commercialisation/selling/ui/Customer.u.php' => 1,
		'ouvretaferme/admin/ui/StatisticAi.u.php' => 2,
		'internal/securing/ui/Log.u.php' => 2,
		'ouvretaferme/design/ui/Design.u.php' => 2,
		'ouvretaferme/design/ui/Pdf.u.php' => 1,
		'production/sequence/ui/Dev.o.php' => 1,
		'production/toolbox/ui/Alert.u.php' => 2,
	];

	const REDRESSED = [
		'ouvretaferme/subscription/ui/MailAi.u.php' => 1,
	];

	const LEXICON = [
		'commercialisation/amap/ui/Subscription.u.php' => 15,
	];

	const VEUILLEZ = [
		'accounting/asset/ui/Alert.u.php' => 1,
		'accounting/bank/ui/Alert.u.php' => 25,
		'accounting/cash/ui/Alert.u.php' => 2,
		'accounting/cash/ui/Cash.u.php' => 1,
		'accounting/company/ui/Alert.u.php' => 1,
		'accounting/company/ui/Company.u.php' => 1,
		'accounting/financialYear/ui/Alert.u.php' => 6,
		'accounting/help/ui/Alert.u.php' => 1,
		'accounting/invoicing/ui/Alert.u.php' => 3,
		'accounting/invoicing/ui/Event.u.php' => 1,
		'accounting/journal/ui/Alert.u.php' => 15,
		'accounting/pdp/ui/Address.u.php' => 1,
		'accounting/pdp/ui/Alert.u.php' => 8,
		'accounting/thirdParty/ui/Alert.u.php' => 2,
		'accounting/vat/ui/Vat.u.php' => 2,
		'base/mail/ui/Contact.u.php' => 1,
		// Généré au démarrage depuis AlertUi::getJavascript() : se redresse dans base/media/ui/Alert.u.php.
		'base/media/static/js/conf.js' => 6,
		'base/media/ui/Alert.u.php' => 6,
		'base/structure/ui/Alert.u.php' => 9,
		'base/structure/ui/Invite.u.php' => 7,
		'base/structure/ui/StructureAccounting.u.php' => 1,
		'base/structure/ui/StructureCommunity.u.php' => 1,
		'base/structure/ui/StructureEducation.u.php' => 1,
		'base/structure/ui/StructureNetwork.u.php' => 1,
		'base/user/ui/Alert.u.php' => 15,
		'base/user/ui/User.u.php' => 1,
		'commercialisation/payment/ui/Alert.u.php' => 5,
		'commercialisation/payment/ui/Stripe.u.php' => 1,
		'commercialisation/payment/ui/SumupReaderCheckout.u.php' => 4,
		'base/product/ui/Alert.u.php' => 10,
		'commercialisation/quotation/ui/Contribute.u.php' => 2,
		'commercialisation/quotation/ui/Suggestion.u.php' => 2,
		'commercialisation/selling/ui/Alert.u.php' => 17,
		'commercialisation/selling/ui/Customer.u.php' => 1,
		'commercialisation/shop/ui/Alert.u.php' => 6,
		'commercialisation/shop/ui/Basket.u.php' => 8,
		'commercialisation/shop/ui/Date.u.php' => 1,
		'commercialisation/shop/view/public.v.php' => 1,
		'framework/api/ui/Alert.u.php' => 4,
		'framework/dev/ui/Alert.u.php' => 1,
		'framework/editor/ui/Editor.u.php' => 2,
		'ouvretaferme/analyze/ui/Alert.u.php' => 3,
		'ouvretaferme/association/ui/Alert.u.php' => 2,
		'ouvretaferme/main/ui/Alert.u.php' => 1,
		'ouvretaferme/main/view/index.v.php' => 1,
		'ouvretaferme/subscription/ui/Alert.u.php' => 1,
		'ouvretaferme/website/ui/Alert.u.php' => 1,
		'ouvretaferme/website/ui/Manage.u.php' => 2,
		'production/map/ui/Alert.u.php' => 10,
		'production/sequence/ui/Alert.u.php' => 1,
		'production/series/ui/Alert.u.php' => 3,
		'production/series/ui/Csv.u.php' => 1,
		'production/work/ui/Alert.u.php' => 1,
	];

	/**
	 * Les textes d'interface de chaque fichier, relevés une fois pour les règles sur les mots.
	 * @var array<string, array<int, array{0: int, 1: string}>>
	 */
	private array $texts = [];

	private int $lexiconRows = 0;

	const EM_DASH = [
		'accounting/articles/ui/ArticleVatRfa.u.php' => 1,
		'accounting/articles/ui/ArticleVatWording.u.php' => 2,
		'accounting/vat/ui/Alert.u.php' => 1,
		'accounting/vat/ui/Ca3_2026.u.php' => 1,
		'commercialisation/quotation/ui/Table.u.php' => 1,
		'ouvretaferme/admin/ui/StatisticAi.u.php' => 5,
		'ouvretaferme/design/ui/Design.u.php' => 2,
		'ouvretaferme/main/ui/Csv.u.php' => 1,
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			$this->addFound('brandElision', $file, $this->findBrandElision($tokens));
			$this->addFound('emDash', $file, $this->findEmDash($tokens));

			if(str_ends_with($file, '.t.php') === FALSE) {
				$this->texts[$file] = self::findTexts($tokens);
			}

		}

		foreach(SentinelLib::getFiles('.js') as $file => $path) {

			$code = file_get_contents($path);

			foreach(explode("\n", $code) as $position => $line) {

				if(preg_match('/d\\\\?[\'’]Ouvretaferme/iu', $line) === 1) {
					$this->addFound('brandElision', $file, [$position + 1]);
				}

			}

			if(str_starts_with($file, 'framework/') === FALSE) {
				$this->texts[$file] = $this->findJsTexts($code);
			}

		}

		$lexicon = $this->getLexicon();

		foreach($this->texts as $file => $texts) {

			$this->addFound('objectVerb', $file, $this->match($texts, self::OBJECT_VERB_PATTERNS));
			$this->addFound('redressed', $file, $this->match($texts, self::REDRESSED_PATTERNS));
			$this->addFound('veuillez', $file, $this->match($texts, self::VEUILLEZ_PATTERNS));

			$patterns = [];

			foreach($lexicon as [$word, $scopes]) {

				foreach($scopes as $scope) {

					if(str_starts_with($file, $scope.'/')) {
						$patterns[] = '/(?<!\p{L})'.preg_quote($word, '/').'(?!\p{L})/iu';
						break;
					}

				}

			}

			$this->addFound('lexicon', $file, $this->match($texts, $patterns));

		}

	}

	/**
	 * Les mots refusés des lignes « confirmé » de `.claude/lexique/*.md`, chacun avec sa portée : la
	 * colonne Portée, ou la ligne « Portée par défaut » du fichier quand la colonne est vide.
	 *
	 * @return array<int, array{0: string, 1: string[]}>
	 */
	private function getLexicon(): array {

		$files = glob(SentinelLib::getRoot().'/.claude/lexique/*.md') ?: [];

		$lexicon = [];

		foreach($files as $path) {

			$content = file_get_contents($path);

			$scopeDefault = [];

			if(preg_match('/^Portée par défaut : `([^`]+)`/mu', $content, $match) === 1) {
				$scopeDefault = [$match[1]];
			}

			$columns = NULL;

			foreach(explode("\n", $content) as $line) {

				if(str_starts_with($line, '|') === FALSE) {
					continue;
				}

				$cells = array_map('trim', explode('|', trim($line, " \t|")));

				if($columns === NULL) {
					$columns = array_flip($cells);
					continue;
				}

				if(preg_match('/^[-: ]+$/', implode('', $cells)) === 1) {
					continue;
				}

				$this->lexiconRows++;

				if($cells[$columns['État']] !== 'confirmé') {
					continue;
				}

				$scopes = array_filter(array_map(fn($scope) => trim($scope, " `"), explode(',', $cells[$columns['Portée']])));

				foreach(explode(',', $cells[$columns['Mots refusés']]) as $word) {

					$word = preg_replace('/^[\s«»`"]+|[\s«»`"]+$/u', '', $word);

					if($word !== '') {
						$lexicon[] = [$word, $scopes ?: $scopeDefault];
					}

				}

			}

		}

		return $lexicon;

	}

	/**
	 * @param array<int, array{0: int, 1: string}> $texts
	 * @return int[]
	 */
	private function match(array $texts, array $patterns): array {

		$lines = [];

		foreach($texts as [$line, $text]) {

			foreach($patterns as $pattern) {

				if(preg_match($pattern, $text) === 1) {
					$lines[] = $line;
					break;
				}

			}

		}

		return $lines;

	}

	/**
	 * Les chaînes à guillemets doubles et les gabarits d'un JS, avec la ligne où ils s'ouvrent. Une
	 * barre oblique de littéral d'expression régulière n'est pas reconnue : un guillemet dans `/"/`
	 * ouvre une chaîne qui se ferme au guillemet suivant ou à la fin de la ligne.
	 *
	 * @return array<int, array{0: int, 1: string}>
	 */
	private function findJsTexts(string $code): array {

		$texts = [];
		$line = 1;
		$length = strlen($code);

		for($i = 0; $i < $length; $i++) {

			$char = $code[$i];

			if($char === "\n") {
				$line++;
				continue;
			}

			if($char === '/' and ($code[$i + 1] ?? '') === '/') {
				$end = strpos($code, "\n", $i);
				$i = ($end === FALSE ? $length : $end) - 1;
				continue;
			}

			if($char === '/' and ($code[$i + 1] ?? '') === '*') {
				$end = strpos($code, '*/', $i + 2);
				$end = ($end === FALSE) ? $length : $end + 2;
				$line += substr_count($code, "\n", $i, $end - $i);
				$i = $end - 1;
				continue;
			}

			if(in_array($char, ['\'', '"', '`'], TRUE) === FALSE) {
				continue;
			}

			$start = $line;
			$text = '';

			for($i++; $i < $length and $code[$i] !== $char; $i++) {

				if($code[$i] === '\\') {

					$i++;

					if(($code[$i] ?? '') === "\n") {
						$line++;
					}

					$text .= $code[$i] ?? '';
					continue;

				}

				if($code[$i] === "\n") {

					$line++;

					if($char !== '`') {
						break;
					}

				}

				$text .= $code[$i];

			}

			if($char !== '\'') {
				$texts[] = [$start, $text];
			}

		}

		return $texts;

	}

	/** @return int[] */
	private function findBrandElision(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			if(preg_match('/d\\\\?[\'’]Ouvretaferme/iu', self::getString($token) ?? '') === 1) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * Un tiret cadratin dans le texte à traduire, premier argument de `s()` ou `p()`.
	 *
	 * @return int[]
	 */
	private function findEmDash(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type !== T_STRING or
				($text !== 's' and $text !== 'p') or
				($tokens[$i + 1][0] ?? NULL) !== '(' or
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)
			) {
				continue;
			}

			$closing = SentinelLib::getClosing($tokens, $i + 1);

			for($j = $i + 2; $j < $closing and $tokens[$j][0] !== ','; $j++) {

				if(str_contains(self::getString($tokens[$j]) ?? '', '—')) {
					$lines[] = $tokens[$j][2];
					break;
				}

			}

		}

		return $lines;

	}

	public function testDetectors(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			$a = s("Le compte d'Ouvretaferme");
			$b = s('Le compte d\'Ouvretaferme').s("Le compte de Ouvretaferme");
			$c = s("Une valeur — une autre", ['x' => '—']);
			$d = '<span>—</span>'.p("Un — deux", "Des — deux", 2);
			PHP);

		$this->assertEquals([2, 3], $this->findBrandElision($tokens), 'brandElision');
		$this->assertEquals([4, 5], $this->findEmDash($tokens), 'emDash');

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			// La fiche ne porte pas de prix de cession
			$a = s("La fiche de ce bien ne porte pas de prix de cession");
			$b = s('Chacune passe sur la planche '.'de même rang', ['x' => 'de même rang']);
			$c = p("Une ligne", "{value} lignes portent une remise", 2);
			$d = $e->s("La fiche ne porte rien").'Le compte porte';
			$f = s("La livraison porte deux modes, la ligne est validée");
			PHP);

		$texts = self::findTexts($tokens);

		$this->assertEquals([3, 4, 5, 5, 7], array_column($texts, 0), 'findTexts');
		$this->assertEquals([3, 5], $this->match($texts, self::OBJECT_VERB_PATTERNS), 'objectVerb');
		$this->assertEquals([4], $this->match($texts, self::REDRESSED_PATTERNS), 'redressed');

		$texts = $this->findJsTexts(<<<'JS'
			// Une ligne porte "de même rang"
			const a = 'La fiche porte "rien"';
			/* "de même rang"
			   sur deux lignes */
			const b = "Veuillez choisir";
			const c = `Le produit passe
			sur une offre ${"de même rang"}`;
			const d = "Comptabilité : " + reference;
			JS);

		$this->assertEquals([[5, 'Veuillez choisir'], [6, "Le produit passe\nsur une offre \${\"de même rang\"}"], [8, 'Comptabilité : ']], $texts, 'findJsTexts');
		$this->assertEquals([6], $this->match($texts, self::REDRESSED_PATTERNS), 'redressed js');
		$this->assertEquals([5], $this->match($texts, self::VEUILLEZ_PATTERNS), 'veuillez js');

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			// Veuillez noter que ce commentaire n'est pas lu
			$a = s("Aucun fichier n'a été reçu, veuillez en choisir un");
			$b = s('Veuillez choisir une date').p("Un produit", "Veuillez retirer {value} produits", 2);
			$c = '<p>Veuillez patienter</p>'.s("Choisissez une date");
			PHP);

		$this->assertEquals([3, 4, 4], $this->match(self::findTexts($tokens), self::VEUILLEZ_PATTERNS), 'veuillez');

	}

	public function testBrandElision(): void {
		$this->assertRatchet('brandElision', [], 'élision sur la marque, écrire « de Ouvretaferme »');
	}

	public function testEmDash(): void {
		$this->assertRatchet('emDash', self::EM_DASH, 'tiret cadratin dans un texte');
	}

	public function testObjectVerb(): void {
		$this->assertRatchet('objectVerb', self::OBJECT_VERB, 'un objet sujet d\'un verbe du modèle (« la fiche ne porte pas », « naît », « désigner ») : dire ce que l\'utilisateur voit ou fait, « aucun prix de cession n\'est saisi pour ce bien »');
	}

	public function testRedressed(): void {
		$this->assertRatchet('redressed', self::REDRESSED, 'forme redressée (« de même rang », « passer sur une offre ») : écrire le rang de la planche, ou ce que fait l\'offre');
	}

	public function testVeuillez(): void {
		$this->assertRatchet('veuillez', self::VEUILLEZ, 'formule « Veuillez », refusée le 2026-10-16 : écrire à l\'impératif, « Choisissez une date »');
	}

	public function testLexicon(): void {

		$this->assertTrue($this->lexiconRows > 0, 'aucune ligne lue dans les tableaux de .claude/lexique/*.md, la règle des mots refusés ne voit plus rien : rétablir les fichiers ou leur en-tête « | Objet ou geste (code) | … | Mots refusés | Portée | Fondement | État | »');
		$this->assertRatchet('lexicon', self::LEXICON, 'mot refusé par une ligne « confirmé » de .claude/lexique/ pour ce package : employer le mot de l\'utilisateur que donne la même ligne');

	}

}
?>
