<?php
namespace design;

/**
 * Test-sentinelle : un en-tête d'écran se pose par `\main\DesignUi::getPageHeader()`, et par rien
 * d'autre.
 *
 * Le socle de l'en-tête de page (chantier dashboard, lot 7 navigation, B3, validé le
 * 03/10/2026) tient en neuf règles. Celles qui ont une forme se lisent ici ; les autres restent à la
 * relecture et à Chrome (la règle 3 est tenue par la forme de la méthode, la règle 5 au-delà de
 * l'ordre des gestes, la règle 6 qui sépare un onglet de page d'un onglet de bloc par le sens, la
 * règle 9 qui reconnaîtrait une date dans un titre).
 *
 * Six règles, chacune en cliquet par fichier (`assertRatchet()`), qui ne peut que baisser :
 * - `h1`, règle 1 : un `<h1` écrit dans une chaîne d'un `.u.php` ou d'un `.v.php`. Le titre d'un
 *   écran est émis par la méthode, qui seule porte la bande, le retour et l'ordre des gestes.
 * - `stylesheet`, règle 2 : une règle d'une feuille autre que `component.css` dont le sélecteur
 *   vise `util-page-band` ou `util-page-header*`. La bande ne prend de l'écran ni teinte, ni
 *   taille, ni marge.
 * - `viewMenu`, règle 4 : `h-menu-wrapper`, le menu de vues glissé dans le `h1`, qui devient des
 *   onglets en tête du contenu.
 * - `year`, règle 7 : une affectation `->mainYear =`. La saison passe en jeton sur la ligne de
 *   contexte, et l'emplacement du gabarit disparaît avec la dernière.
 * - `back`, règle 8 : `class="h-back`, la flèche écrite à la main. Le retour passe par `back:`.
 * - `titleBack` : une flèche `h-back` dans le premier argument d'un appel. Le titre et le disque
 *   coexisteraient dans le même composant. Vide, et le reste. Un titre passé par une variable est
 *   suivi jusqu'à ses affectations (`=` et `.=`) dans la fonction qui appelle ; une variable reçue
 *   en paramètre, ou remplie par une autre méthode, reste invisible.
 *
 * Le PHP se lit par l'analyseur lexical : seules les chaînes et le HTML en ligne comptent, donc une
 * docbloc qui cite `<h1>` ou `h-back` ne compte pas, et un appel se découpe en arguments sans se
 * laisser tromper par une virgule dans une chaîne. Les feuilles se lisent sans leurs commentaires.
 *
 * L'existant est déclaré sur `35acc240a` (le 03/10/2026, identique à `origin/main` `1c960626b`
 * pour chaque fichier compté), moins les écrans repris depuis par `getPageHeader()` : l'éditeur
 * de formation, la composition d'un produit, l'aide comptable et la fiche contrat AMAP côté producteur (`back:`), le détail d'une série, une facture, la Banque sans
 * compte, les paramètres de la production et l'export des commandes d'une AMAP, puis les paramètres
 * de structure et la communication (L3a : paramètres de commercialisation et leurs sous-pages, TVA,
 * cultures, productions, profil, paramétrage, exports, imports, intégrations, enquête AMAP, équipe,
 * rôles, fiche d'un membre, contacts, campagnes, notifications et historique), puis les rapports
 * de production (L3b : la liste et son jeton de saison, un rapport, sa création et son
 * actualisation), puis les groupes (L3c : membres, tableau de bord, maraîchage, comparer, fermes
 * école, mes fermes, sous-groupes, gestion des fermes et fermes), puis les ventes (L4a : ventes,
 * achats, étiquettes, archivage, caisse, clients, cagnottes, groupes de clients et un groupe,
 * stocks, factures, analyse des ventes et son jeton d'année, une vente, une fiche client, les
 * imports de clients et de prix), puis les produits et boutiques (L4b : produits et catégories,
 * catalogues, groupes de produits, une fiche produit, unités de vente, imports de produits,
 * boutiques en ligne, une boutique, sa création, son paramétrage et son intégration, modes de
 * livraison, paiement par carte, reversements et modes de règlement), puis les relevés de prix
 * (L4c : renseigner mes prix, les prix du moment, une cotation, comparer les régions, une région,
 * la méthodologie, les relevés d'un groupe et une collecte), puis la banque, la caisse et la
 * précomptabilité (L5a : opérations bancaires, imports bancaires, cahiers de caisse et leur
 * archivage, précomptabilité, imports des ventes et des achats, règlements sans trace,
 * rapprochement), puis le journal, le plan de comptes et les tiers (L5b : livre journal, grand
 * livre, balance, comptes de tiers et la vue d'un tiers, journaux, une écriture, trames, une trame
 * et la trame tirée d'une écriture, plan comptable, tiers et fiche d'un tiers), puis la synthèse,
 * les exercices et les déclarations (L5c : synthèse, bilans, soldes intermédiaires de gestion,
 * compte de résultat, analyses financières et leur jeton d'exercice, clôture, réouverture,
 * ouverture, inventaire, archivage, import FEC, liasse fiscale et déclaration de TVA), puis les
 * immobilisations, la facturation électronique et le démarrage (L5d : immobilisations et
 * acquisitions, une immobilisation, les deux têtes de l'import d'immobilisations, factures
 * électroniques d'achat et de vente, une facture électronique, rapport de facturation électronique,
 * plateforme de facturation électronique, aide à l'initialisation, paramètres de la comptabilité et
 * les trois écrans d'entrée de la gestion), puis la production (L6a à L6d : paramètres de la
 * production, bibliothèque et Boîte à outils, écrans saisonniers et leur jeton de saison, analyses
 * du temps de travail et des cultures, itinéraires techniques, liste des interventions, copie et
 * import d'un plan de culture, têtes de semaine et d'année du planning), puis l'AMAP côté
 * collectif (L7a : adhérents et fiche d'un adhérent, distributions, permanences, adhésions,
 * demandes, producteurs, tableau de bord, sous-pages de configuration et mise en route), puis
 * l'AMAP côté producteur (L7b : la liste des AMAP, la page d'une AMAP et son menu, la liste des
 * contrats des deux côtés, les marches du tunnel d'ouverture, l'inscription d'un adhérent et une
 * souscription). Mesure de contrôle : `git grep -n "<h1" origin/main -- '*.u.php' '*.v.php'`,
 * `git grep -n h-menu-wrapper origin/main -- '*.u.php' '*.v.php'`,
 * `git grep -n -- '->mainYear *=' origin/main -- '*.php'`,
 * `git grep -n 'class="h-back' origin/main -- '*.u.php' '*.v.php'`, chacune hors `/test/`. Les
 * comptes de ce fichier sont par occurrence et non par ligne : une ligne qui porte deux `<h1`
 * compte deux. Seuls les fichiers que git suit sont lus (`\dev\SentinelLib::getFiles()`).
 *
 * Chaque lot de reprise (L3 à L7, un univers chacun) baisse les déclarations de ses fichiers dans
 * le même commit. Les six retours par l'historique de la comptabilité passent par
 * `back: NULL`, sans adresse (décision 5 du 03/10, question 4 du plan B3) : leur
 * donner une adresse changerait leur destination. Restent déclarés au plancher, avec leur raison
 * sur la ligne : le `<h1>` que le composant émet lui-même, les écrans hors du gabarit de structure
 * (formation, site, hors chantier), et la neutralisation de la bande sur l'Accueil.
 *
 * Lancement : dev/test package=design file=PageHeaderSentinelAi
 */
class PageHeaderSentinelAiTest extends \dev\SentinelTest {

	const COMPONENT = 'ouvretaferme/main/static/css/component.css';

	/**
	 * Les motifs cherchés dans les chaînes des fichiers de présentation, par règle. `h-back` suivi
	 * d'un blanc ou du guillemet, pour ne pas prendre une classe qui commencerait pareil.
	 */
	const PATTERNS = [
		'h1' => '/<h1(?![0-9a-z])/',
		'viewMenu' => '/h-menu-wrapper/',
		'back' => '/class="h-back(?=[\s"])/',
	];

	const H1 = [
		'accounting/articles/ui/AbstractArticle.u.php' => 1,
		'accounting/articles/ui/ArticleAssetWording.u.php' => 1,
		'accounting/articles/ui/ArticleAutoInvoicing.u.php' => 1,
		'accounting/articles/ui/ArticleClosingAsset.u.php' => 1,
		'accounting/articles/ui/ArticleClosingClients.u.php' => 1,
		'accounting/articles/ui/ArticleClosingEquityAi.u.php' => 1,
		'accounting/articles/ui/ArticleClosingSocial.u.php' => 1,
		'accounting/articles/ui/ArticleClosingSuppliers.u.php' => 1,
		'accounting/articles/ui/ArticleClosingTreasurery.u.php' => 1,
		'accounting/articles/ui/ArticleDja.u.php' => 1,
		'accounting/articles/ui/ArticleOrganicCertification.u.php' => 1,
		'accounting/articles/ui/ArticleOtherProducts.u.php' => 1,
		'accounting/articles/ui/ArticleStripe.u.php' => 1,
		'accounting/articles/ui/ArticleVatCars.u.php' => 1,
		'accounting/articles/ui/Articles.u.php' => 1,
		'accounting/bank/ui/Reconciliation.u.php' => 1,
		'accounting/cash/ui/Transaction.u.php' => 2,
		'accounting/journal/ui/OperationWrite.u.php' => 1,
		// La tête H2 de l'Accueil, la date en titre sans bande ni composant (chantier dashboard, L10,
		// go de V-Dashboard du 05/10) : l'Accueil ressort de `getPageHeader()`.
		'base/dashboard/ui/Dashboard.u.php' => 1,
		'base/mail/view/contact.v.php' => 2,
		'base/structure/ui/ProfileAi.u.php' => 1,
		// La vue `start`, hors du gabarit de structure, hors chantier B3 (question 9).
		'base/structure/view/structure.v.php' => 1,
		'base/user/view/signUp.v.php' => 1,
		'base/user/view/update.v.php' => 1,
		'base/user/view/verify.v.php' => 1,
		// Le formulaire public d'adhésion, hors du gabarit de structure, hors chantier B3 (question 9).
		'commercialisation/amap/ui/MembershipRequest.u.php' => 1,
		'commercialisation/payment/view/wallet.v.php' => 1,
		'commercialisation/quotation/ui/Contribute.u.php' => 1,
		'commercialisation/quotation/ui/Response.u.php' => 2,
		'commercialisation/quotation/view/suggestion.v.php' => 1,
		'commercialisation/selling/ui/Order.u.php' => 1,
		'commercialisation/shop/ui/Basket.u.php' => 1,
		'commercialisation/shop/ui/Shop.u.php' => 1,
		'commercialisation/shop/view/auditAi.v.php' => 1,
		'commercialisation/shop/view/public.v.php' => 1,
		'internal/communication/ui/Note.u.php' => 2,
		'internal/communication/view/webinaire.v.php' => 2,
		'ouvretaferme/association/view/membership.v.php' => 1,
		// La démonstration d'util-action-fold, que la page présente encore, dans ses deux états : gestes
		// à droite d'un titre long, gestes passés sous un titre court.
		'ouvretaferme/design/ui/Design.u.php' => 2,
		'ouvretaferme/design/ui/Navigation.u.php' => 2,
		'ouvretaferme/game/view/index.v.php' => 2,
		'ouvretaferme/learning/ui/Catalog.u.php' => 3,
		'ouvretaferme/learning/ui/Course.u.php' => 3,
		'ouvretaferme/learning/ui/DatePublic.u.php' => 1,
		'ouvretaferme/learning/ui/Review.u.php' => 1,
		'ouvretaferme/learning/view/admin/supervisor.v.php' => 1,
		'ouvretaferme/learning/view/admin/theme.v.php' => 1,
		// Le h1 que getPageHeader() émet : c'est le composant lui-même, au plancher.
		'ouvretaferme/main/ui/Design.u.php' => 1,
		'ouvretaferme/main/view/account.v.php' => 1,
		'ouvretaferme/main/view/error.v.php' => 2,
		'ouvretaferme/main/view/index.v.php' => 1,
		'ouvretaferme/main/view/maintenance.v.php' => 2,
		'ouvretaferme/presentation/ui/Feature.u.php' => 1,
		'ouvretaferme/presentation/ui/Landing.u.php' => 1,
		'ouvretaferme/presentation/ui/Pricing.u.php' => 1,
		'ouvretaferme/presentation/view/index.v.php' => 5,
		'ouvretaferme/subscription/view/closed.v.php' => 1,
		'ouvretaferme/subscription/view/indexAi.v.php' => 2,
		'ouvretaferme/website/ui/Manage.u.php' => 1,
		'ouvretaferme/website/ui/News.u.php' => 2,
		'ouvretaferme/website/ui/Webpage.u.php' => 2,
		'ouvretaferme/website/view/manage.v.php' => 1,
		'production/plant/ui/Plant.u.php' => 1,
	];

	const STYLESHEET = [
		// L'écart sous l'en-tête dans le gabarit des groupes, que la bande porte depuis B3 L1.
		'ouvretaferme/main/static/css/template-community.css' => 1,
	];

	const VIEW_MENU = [];

	const YEAR = [];

	const BACK = [
		'commercialisation/payment/view/wallet.v.php' => 1,
		// Hors du gabarit de structure, hors chantier B3 (question 9).
		// Hors du gabarit de structure, hors chantier B3 (question 9).
		// Hors du gabarit de structure, hors chantier B3 (question 9).
		'ouvretaferme/learning/ui/DatePublic.u.php' => 1,
		// Hors du gabarit de structure, hors chantier B3 (question 9).
		'ouvretaferme/learning/ui/Review.u.php' => 1,
		// Hors du gabarit de structure, hors chantier B3 (question 9).
		'ouvretaferme/website/ui/News.u.php' => 2,
		// Hors du gabarit de structure, hors chantier B3 (question 9).
		'ouvretaferme/website/ui/Webpage.u.php' => 1,
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			if(self::isPresentation($file)) {
				foreach(self::PATTERNS as $rule => $pattern) {
					$this->addFound($rule, $file, self::findInStrings($tokens, $pattern));
				}

				$this->addFound('year', $file, self::findYear($tokens));
			}

			foreach(self::findCalls($tokens) as $rule => $lines) {
				$this->addFound($rule, $file, $lines);
			}

		}

		foreach(\dev\SentinelLib::getFiles('.css') as $file => $path) {

			if($file === self::COMPONENT) {
				continue;
			}

			$this->addFound('stylesheet', $file, self::findStylesheet(file_get_contents($path)));

		}

	}

	/**
	 * Les lignes où un motif paraît dans une chaîne ou dans le HTML en ligne, une fois par
	 * occurrence. Une chaîne sur plusieurs lignes rend la ligne de l'occurrence, pas celle de son
	 * début.
	 *
	 * @return int[]
	 */
	private static function findInStrings(array $tokens, string $pattern): array {

		$found = [];

		foreach($tokens as [$type, $text, $line]) {

			if($type !== T_CONSTANT_ENCAPSED_STRING and $type !== T_ENCAPSED_AND_WHITESPACE and $type !== T_INLINE_HTML) {
				continue;
			}

			preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

			foreach($matches[0] as [, $offset]) {
				$found[] = $line + substr_count(substr($text, 0, $offset), "\n");
			}

		}

		return $found;

	}

	/**
	 * @return int[]
	 */
	private static function findYear(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type === T_STRING and $text === 'mainYear' and
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], TRUE) and
				($tokens[$i + 1][0] ?? NULL) === '='
			) {
				$found[] = $line;
			}

		}

		return $found;

	}

	/**
	 * Les appels de `getPageHeader()`, rangés par règle enfreinte, à la ligne du nom de la méthode.
	 * Sa déclaration (`function getPageHeader(`) n'est pas un appel.
	 *
	 * @return array<string, int[]>
	 */
	private static function findCalls(array $tokens): array {

		$found = ['titleBack' => []];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type !== T_STRING or $text !== 'getPageHeader' or
				($tokens[$i + 1][0] ?? NULL) !== '(' or
				($tokens[$i - 1][0] ?? NULL) === T_FUNCTION
			) {
				continue;
			}

			$arguments = self::getArguments($tokens, $i + 1);

			if($arguments !== [] and $arguments[0]['name'] === NULL and self::hasBack(self::expandTitle($tokens, $i, $arguments[0]['tokens']))) {
				$found['titleBack'][] = $line;
			}

		}

		return $found;

	}

	/**
	 * Les arguments d'un appel dont la parenthèse ouvre en `$open`, découpés aux virgules de premier
	 * niveau : chacun porte son nom quand il est nommé (`back:`), et ses jetons.
	 *
	 * @return array<int, array{name: ?string, tokens: array}>
	 */
	private static function getArguments(array $tokens, int $open): array {

		$close = \dev\SentinelLib::getClosing($tokens, $open);

		$arguments = [];
		$current = [];
		$depth = 0;

		for($i = $open + 1; $i < $close; $i++) {

			$type = $tokens[$i][0];

			if($type === '(' or $type === '[' or $type === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$depth++;
			} else if($type === ')' or $type === ']' or $type === '}') {
				$depth--;
			} else if($type === ',' and $depth === 0) {
				$arguments[] = $current;
				$current = [];
				continue;
			}

			$current[] = $tokens[$i];

		}

		if($current !== []) {
			$arguments[] = $current;
		}

		return array_map(fn(array $argument) => [
			'name' => (($argument[0][0] ?? NULL) === T_STRING and ($argument[1][0] ?? NULL) === ':') ? $argument[0][1] : NULL,
			'tokens' => $argument
		], $arguments);

	}

	/**
	 * Les jetons du titre, augmentés de ceux de chaque affectation (`=`, `.=`) de ses variables entre
	 * le début de la fonction qui appelle et l'appel : `$title = '<a … class="h-back">'` puis
	 * `getPageHeader($title, …)` porte la flèche aussi sûrement qu'une chaîne en ligne. La fonction
	 * commence à l'accolade qui suit le dernier `function` avant l'appel, ce qui couvre les méthodes
	 * et les closures, pas une fonction fléchée (`fn`), qui n'affecte rien ; partir de l'accolade
	 * écarte la valeur par défaut d'un paramètre (`string $title = ''`), qui n'est pas une
	 * affectation. L'affectation se lit jusqu'à son `;`.
	 */
	private static function expandTitle(array $tokens, int $call, array $title): array {

		$variables = [];

		foreach($title as [$type, $text]) {

			if($type === T_VARIABLE) {
				$variables[] = $text;
			}

		}

		if($variables === []) {
			return $title;
		}

		$start = 0;

		for($i = $call; $i >= 0; $i--) {

			if($tokens[$i][0] === T_FUNCTION) {
				$start = $i;
				break;
			}

		}

		while($start < $call and $tokens[$start][0] !== '{') {
			$start++;
		}

		for($i = $start; $i < $call; $i++) {

			if(
				$tokens[$i][0] === T_VARIABLE and
				in_array($tokens[$i][1], $variables, TRUE) and
				in_array($tokens[$i + 1][0], ['=', T_CONCAT_EQUAL], TRUE)
			) {

				for($j = $i + 2; $j < $call and $tokens[$j][0] !== ';'; $j++) {
					$title[] = $tokens[$j];
				}

			}

		}

		return $title;

	}

	private static function hasBack(array $tokens): bool {

		foreach($tokens as [$type, $text]) {

			if(($type === T_CONSTANT_ENCAPSED_STRING or $type === T_ENCAPSED_AND_WHITESPACE) and str_contains($text, 'h-back')) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les lignes des règles dont un sélecteur vise la bande ou l'en-tête. Les commentaires sont
	 * remplacés par autant de retours à la ligne qu'ils en portaient, pour garder les numéros.
	 *
	 * Une classe nommée dans un `:has()` ne compte pas : la règle y teste la présence de la bande
	 * et dessine son conteneur, pas la bande. C'est la forme du gabarit de structure
	 * (`template-structure.css`), qui lâche la hauteur figée de son conteneur de titre et règle
	 * l'écart avant le contenu sur le pas de la bande, sur l'écran qui en porte une.
	 *
	 * @return int[]
	 */
	private static function findStylesheet(string $css): array {

		$css = preg_replace_callback('/\/\*.*?\*\//s', fn(array $matches) => str_repeat("\n", substr_count($matches[0], "\n")), $css);

		preg_match_all('/[^{};]+(?=\{)/', $css, $matches, PREG_OFFSET_CAPTURE);

		$found = [];

		foreach($matches[0] as [$selector, $offset]) {

			$subject = preg_replace('/:has\((?:[^()]|\([^()]*\))*\)/', '', $selector);

			if(preg_match('/\.util-page-(band|header)(?![a-z0-9_])|\.util-page-header-[a-z]/', $subject) === 1) {
				$offset += strlen($selector) - strlen(ltrim($selector));
				$found[] = substr_count(substr($css, 0, $offset), "\n") + 1;
			}

		}

		return $found;

	}

	public function testDetectors(): void {

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			/** Une docbloc qui cite <h1> et class="h-back" ne compte pas. */
			$h = '<h1>'.$title.'</h1><h2>'.$sub.'</h2>';
			$h .= "<h1 class=\"x\">";
			$h .= '<a href="/" class="h-back">'.$icon.'</a><a class="h-menu-wrapper">';
			$h .= '<span class="h-back-label">';
			$t->mainYear = $year;
			$t->mainYearLabel = $year;
			if($t->mainYear === NULL) {}
			$h .= '<div>
				<h1>Sur la seconde ligne</h1>';
			PHP);

		$this->assertEquals([3, 4, 11], self::findInStrings($tokens, self::PATTERNS['h1']), 'les <h1 des chaînes, pas celui de la docbloc ni le <h2');
		$this->assertEquals([5], self::findInStrings($tokens, self::PATTERNS['back']), 'la flèche écrite à la main, pas une classe qui commence pareil sans être un attribut ouvert sur elle');
		$this->assertEquals([5], self::findInStrings($tokens, self::PATTERNS['viewMenu']));
		$this->assertEquals([7], self::findYear($tokens), 'une affectation de mainYear, pas une propriété voisine ni une lecture');

		$calls = self::findCalls(\dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			public static function getPageHeader(string $title = '<a class="h-back">') {}
			DesignUi::getPageHeader($title, context: $context);
			DesignUi::getPageHeader(back: '<a class="h-back">', title: $title);
			DesignUi::getPageHeader($title, context: 'a, b', settings: $gear);
			DesignUi::getPageHeader(
				'<a href="/" class="h-back">'.$icon.'</a>'.$name,
				settings: $gear,
				create: $button
			);
			DesignUi::getPageHeader(s("Titre, long"), back: url(['/', $a]), tools: f($a, $b));
			DesignUi::getPageHeader($cond ? $a : $b, context: $c);
			function producer() {
				$title = '<a href="/" class="h-back">'.$icon.'</a>';
				$title .= $name;
				return DesignUi::getPageHeader($title, context: $c);
			}
			function member(string $title = '<a class="h-back">') {
				$back = '<a href="/" class="h-back">';
				return DesignUi::getPageHeader($title, context: $back);
			}
			PHP));

		$this->assertEquals([6, 16], $calls['titleBack'], 'une flèche dans le titre, en ligne ou affectée à sa variable dans la fonction, pas dans la déclaration, ni dans back même placé en premier, ni dans le contexte, ni dans une autre variable');

		$css = <<<'CSS'
			/* .util-page-band { color: red } */
			.util-page-header-legacy, .community-main > .util-page-band {
				margin: 0;
			}
			#dashboard .util-page-header h1 { color: white; }
			@media (max-width: 575px) {
				.x .util-page-header-aside .btn:hover { color: red; }
			}
			.util-page-headerless, .util-pages { color: red; }
			main .container:has(.util-page-band) + .container { margin-top: 0; }
			main .container:has(> div:not(.x) .util-page-header) .util-page-header-aside { margin: 0; }
			CSS;

		$this->assertEquals([2, 5, 7, 11], self::findStylesheet($css), 'les règles qui visent la bande ou l\'en-tête, pas un commentaire, ni une classe qui commence pareil, ni une classe seulement nommée dans un :has()');

	}

	public function testH1(): void {
		$this->assertRatchet('h1', self::H1, '<h1 écrit dans un .u.php ou un .v.php (le code fait foi : poser le titre par \main\DesignUi::getPageHeader(), qui émet le h1, et un fichier neuf ne se déclare pas)');
	}

	public function testStylesheet(): void {
		$this->assertRatchet('stylesheet', self::STYLESHEET, 'règle de feuille hors de component.css qui vise util-page-band ou util-page-header (le code fait foi : la bande et l\'en-tête se dessinent dans component.css, pour tous les écrans, et l\'écran ne leur pose ni teinte, ni taille, ni marge)');
	}

	public function testViewMenu(): void {
		$this->assertRatchet('viewMenu', self::VIEW_MENU, 'h-menu-wrapper, un menu de vues dans le titre (le code fait foi : les vues deviennent des onglets tabs tabs-h en tête du contenu, sous la bande)');
	}

	public function testYear(): void {
		$this->assertRatchet('year', self::YEAR, 'affectation de ->mainYear (le code fait foi : la saison se passe en jeton util-chip par l\'argument context de getPageHeader())');
	}

	public function testBack(): void {
		$this->assertRatchet('back', self::BACK, 'class="h-back écrit à la main (le code fait foi : le retour se passe par back: avec l\'adresse de la page parente à getPageHeader(), qui rend le disque, à adresse inchangée)');
	}

	public function testTitleBack(): void {
		$this->assertRatchet('titleBack', [], 'flèche h-back dans le titre d\'un appel de getPageHeader() (le code fait foi : la retirer du titre et passer son adresse par back:)');
	}

}
?>
