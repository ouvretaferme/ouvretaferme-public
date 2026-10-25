<?php
namespace design;

/**
 * La page du design system montre-t-elle encore tout ce que les feuilles définissent, et le
 * produit ne pose-t-il aucune classe qu'aucune feuille ne dessine ?
 *
 * Deux contrats que le code ne rappelle à personne. Le premier : « un composant absent de la page
 * design system sera réécrit par quelqu'un d'autre » (skill `ui`). Mesuré le 2026-09-11 avant ce
 * test : 275 classes `util-*` définies dans `reboot.css` et `component.css`, 199 nommées sur la
 * page ; `util-table-header` (84 emplois), `util-button` (78), `util-block-search` (27) et
 * `util-block-optional` (24) n'y étaient pas, et deux sessions du même chantier ont réécrit un
 * tableau en `<table>` + `<th>` faute d'avoir trouvé `util-table`. Le second : une classe posée en
 * PHP que rien ne dessine a l'air de gérer un cas et ne le gère pas — `media-logo-default`, posée
 * sur une ferme sans logo, rendait un carré vide (`base/structure/ui/Structure.u.php`, corrigé le
 * 2026-09-08) ; `badge-off` promettait un contour et ne faisait qu'une opacité.
 *
 * Le test ne vérifie aucun rendu : il compare des inventaires, dans les deux sens, et échoue en
 * nommant l'écart. Ce qui est absent de la page l'est avec sa raison, dans `ABSENT` ; ce qui est
 * rendu par un helper plutôt qu'écrit en clair sur la page l'est dans `RENDERED_BY` ; ce qui est
 * posé sans règle l'est dans `UNSTYLED`, avec la date du constat. Une déclaration devenue fausse
 * (la classe est apparue sur la page, la règle a été écrite) fait échouer le test autant qu'un
 * écart nouveau : une exception périmée est une exception qui ment.
 *
 * Deux contrats de plus depuis le 2026-10-01, sur la problématique des retours d'interface (piste 1,
 * retenue le 2026-09-30). « Un composant introuvable sera réécrit par quelqu'un
 * d'autre » : une famille `util-*` montrée sur la page et absente de l'index « Trouver un
 * composant » n'est trouvée que par celui qui connaît déjà son nom, et la dette est dans
 * `NOT_INDEXED`. Et une classe posée en JavaScript que rien ne dessine a le même défaut que posée
 * en PHP, déclarée dans `UNSTYLED_JS`.
 *
 * Six formes fautives de plus depuis le 2026-10-16, sur la même problématique (piste 2) : ce
 * que la passe `ui` ou la recette redressaient se lit dans le source sans juger le
 * sens, donc se refuse avant. Un lien atténué, un menu sans `data-dropdown`, une marque pleine dans
 * le titre de l'écran, une case désactivée écrite à la main, une classe de conteneur sur un champ,
 * un modificateur sans le parent où il agit. Chacune est un cliquet déclaré fichier par fichier, et
 * les mesures sont prises le 2026-10-16 sur `origin/main` à `1b782bb23`, en passant les détecteurs
 * de ce test sur `git show origin/main:<fichier>` de chaque fichier de présentation que l'amont
 * suit. Ce qu'elles ne voient pas : une classe ou un attribut qui arrive par une variable, une
 * balise coupée sur deux lignes du source, et ce que le JavaScript pose. Trois formes de la piste
 * restent hors d'ici : `style="`, tenu par `InlineStyleSentinelAi` ; un contrôle rendu avec une
 * seule option, qui ne se lit qu'à l'exécution ; `<table>` écrit à la main, que l'index range parmi
 * les composants légitimes (« Un tableau classique, à colonnes fixes »), ce qui le sépare d'une
 * `util-table` étant un sens.
 *
 * Lancement : dev/test package=design file=DesignSystemSentinelAi
 */
class DesignSystemSentinelAiTest extends \dev\SentinelTest {

	/**
	 * Les feuilles qui font le design system : tout `util-*` qui y est défini doit se voir sur la page.
	 */
	const SHEETS = [
		'ouvretaferme/main/static/css/reboot.css',
		'ouvretaferme/main/static/css/component.css',
	];

	const PAGE = 'ouvretaferme/design/ui/Design.u.php';

	/**
	 * Sous-éléments que la page montre sans écrire leur nom, parce qu'un helper les rend. La clé est
	 * un préfixe ; la valeur nomme le helper, pour que le lecteur sache où regarder.
	 */
	const RENDERED_BY = [
		'util-start-' => '\main\DesignUi::getStart()',
		'util-omnibox-' => '\util\OmniboxUi',
		'util-quick-' => 'lime.js (util-quick-dropdown) et \util\FormUi (util-quick-form)',
		'util-record-label' => '\main\DesignUi::getRecordItem()',
		'util-record-value' => '\main\DesignUi::getRecordItem()',
		'util-weigh-' => '\main\DesignUi::getWeigh()',
	];

	/**
	 * Nommées sur la page sans qu'aucune feuille ne les dessine : des enveloppes que le JS pose
	 * pour grouper, et qui n'ont rien à dessiner. Une classe qui reçoit une règle doit sortir d'ici.
	 */
	const NAMED_WITHOUT_RULE = [
		'util-omnibox-family' => 'posée par omnibox.js autour des suggestions d\'une famille',
	];

	/**
	 * Définies dans une feuille du design system et volontairement absentes de la page, chacune avec
	 * sa raison. Une classe qui apparaît sur la page doit sortir d'ici.
	 */
	const ABSENT = [
		'util-print-block' => 'impression seulement, rien à montrer à l\'écran',
		'util-print-break-after' => 'impression seulement',
		'util-print-break-inside-avoid' => 'impression seulement',
		'util-print-hide' => 'impression seulement',
		'util-print-invisible' => 'impression seulement',
		'util-animate-turn' => 'animation d\'icône, invisible sur une page statique',
		'util-footer' => 'pied de page du gabarit public',
		'util-summarize-primary' => 'variante de couleur de util-summarize, à présenter avec lui',
		'util-timeline-lock' => 'état de la frise des mois rendu par la comptabilité, à présenter avec la frise',
		'util-campaign-action' => 'bouton de la frise de campagne, rendu dans la section correspondante sous un autre nom',
		'util-table-icon' => 'cellule d\'icône d\'une util-table, à présenter avec la grille',
		'util-action-navigation' => 'barre d\'action avec navigation latérale, à présenter avec util-action',
		'util-action-navigation-list' => 'idem',
		'util-metric-label' => 'famille en retrait, remplacée par util-figure (décidé le 2026-10-01) : la page ne la montre plus, FigureFamilySentinelAi déclare les écrans qui la portent encore',
		'util-metric-value' => 'idem',
		'util-metric-variation' => 'idem, remplacée par util-figure-variation',
		'util-metric-variation-up' => 'idem',
		'util-metric-variation-down' => 'idem',
		'util-block-gradient' => 'en cours de retrait : ses 16 appels ont été remplacés le 2026-09-11, il en reste deux dans shop/ui/Shop.u.php, tenu par une autre session',
		'util-block-side' => 'variante de util-block',
		'util-block-title' => 'variante de util-block',
	];

	/**
	 * Classes posées dans un `.u.php` ou un `.v.php` qu'aucune feuille du dépôt ne définit, avec le
	 * fichier qui les pose. Une classe qui reçoit une règle doit sortir d'ici.
	 *
	 * Elle a été vide du 2026-09-11 au 2026-09-17, sous un plancher de cinq emplois qui la vidait
	 * plus qu'il ne la justifiait (voir `MIN_USAGES`). Les entrées ci-dessous sont ce que la
	 * levée du plancher a découvert, toutes mesurées une par une. Aucune n'est traitée ici : quatre
	 * appartiennent à `framework/`, et sortir l'état du socle pour un détail de feuille n'est pas
	 * le travail de ce test. Une classe qui reçoit une règle doit sortir d'ici.
	 */
	const UNSTYLED = [
		'util-omnibox-token-template' => 'ouvretaferme/main/ui/Omnibox.u.php : marque un <template>, que le navigateur ne peint jamais ; le JS s\'en sert pour retrouver le gabarit du jeton. Une règle ici ne s\'appliquerait à rien.',
		'dropdown-list-bottom' => 'accounting/journal/ui/OperationWrite.u.php (3) et accounting/asset/ui/Register.u.php (1), et posée aussi par framework/util/static/js/lime.js:1696, qui choisit entre elle et dropdown-list-top selon la place disponible. Aucune des deux n\'a de règle dans aucune feuille : le placement qu\'elles annoncent n\'est dessiné nulle part. Non traitée ici, c\'est le socle.',
		'form-range-label' => 'framework/util/ui/Form.u.php:1750. Ses voisines de form.css sont dessinées (form-range l.479), pas celle-ci.',
		'form-dropdown-head' => 'framework/util/ui/Form.u.php:1948. form-dropdown-toggle, -title, -list et -placeholder ont leur règle (form.css l.504 à 544), ces deux-là sont passées au travers.',
		'form-dropdown-content' => 'framework/util/ui/Form.u.php:1966 et 1994, même cas que form-dropdown-head.',
	];

	/**
	 * Les classes retirées du design system, avec celle qui les remplace. Une fusion ne laisse pas de
	 * règle à l'ancien nom, ce qui n'empêche pas de le reposer, recopié d'un écran voisin ou d'un
	 * test : il s'afficherait alors sans aucun style. Retirées le
	 * 2026-10-10, quand les messages et les blocs à teinte sont passés sur fond clair (lot L20 du
	 * chantier dashboard).
	 */
	const RETIRED = [
		'util-warning-outline' => 'util-warning',
		'util-danger-outline' => 'util-danger',
		'util-block-secondary' => 'util-block-info',
		'util-block-outline-warning' => 'util-block-warning',
	];

	/**
	 * Les emplois d'une classe retirée qui restent, par cas `<fichier>:<classe>`, chacun avec sa
	 * raison. Un cas sort d'ici avec le lot qui le renomme.
	 */
	const RETIRED_KEPT = [];

	/**
	 * Classes posées par un `.js` qu'aucune feuille du dépôt ne définit, avec le fichier qui les
	 * pose. Une classe qui reçoit une règle, ou que le JS ne pose plus, doit sortir d'ici.
	 *
	 * La collecte est large à dessein : `classList.add`, `remove` et `toggle`, `className`, et
	 * l'attribut `class` d'un balisage écrit dans une chaîne ou un gabarit. Resserrée aux seules
	 * poses certaines, elle écartait 204 poses dont 195 `classList.toggle` et l'assertion verdissait
	 * sans rien prouver (cas-vus, « Une mesure juste dont la règle dérivée est fausse »). Elle ne
	 * voit pas une classe rangée dans un tableau puis jointe, ni une lecture (`classList.contains`,
	 * un sélecteur), qui ne pose rien.
	 */
	const UNSTYLED_JS = [
		'util-omnibox-family' => 'ouvretaferme/main/static/js/omnibox.js, par className : une enveloppe qui groupe les suggestions d\'une famille et n\'a rien à dessiner (voir NAMED_WITHOUT_RULE).',
		'dropdown-list-top' => 'framework/util/static/js/lime.js, par classList.add selon la place disponible : le placement qu\'elle annonce n\'est dessiné nulle part. Non traitée ici, c\'est le socle.',
		'dropdown-list-bottom' => 'framework/util/static/js/lime.js, même ligne et même cas que dropdown-list-top (voir aussi UNSTYLED, qui la porte pour le PHP).',
	];

	/**
	 * Les noms de classe qu'un `.u.php` ou un `.v.php` assemble en collant une variable devant un
	 * fragment littéral (`'.$cssPrefix.'-header-date'`), avec chaque classe que l'assemblage produit.
	 * Aucun de ces noms n'existe en entier dans le code : renommer ou retirer la règle d'un de ses
	 * produits ne trouve pas l'emploi, et la classe sans règle rend du texte nu sans que rien ne lève.
	 * Mesuré le 2026-09-12 : la migration des compteurs vers `util-counter` a retiré `.tab-item-count`,
	 * et six pastilles construites par `'.$class.'-count'` ont disparu de trois écrans d'onglets.
	 *
	 * La clé est le fichier et le gabarit, chaque variable remplacée par `*` ; la déclaration écrit en
	 * entier les classes produites, ce qui les rend trouvables à qui cherche la règle avant de la
	 * retirer. Le préfixe seul (`'badge-'.$tint`) n'est pas relevé : celui-là se trouve en cherchant
	 * le préfixe. Ne sont pas vus non plus un nom assemblé hors d'un attribut `class="…"` (tableau
	 * d'attributs, `\Asset::image()`), ni par une constante (`'.Invoice::GENERATED.'`), ni par une
	 * expression plus complexe qu'une variable, un index ou une propriété.
	 *
	 * Les classes produites sont recopiées des valeurs possibles, pas dérivées : une valeur ajoutée à
	 * une énumération (`Invoice::status`) produit une classe que la déclaration ne nomme pas, et que
	 * rien ne relève. Les dériver d'ici nommerait du code fermé depuis un fichier publié.
	 */
	const ASSEMBLED = [
		'accounting/preaccounting/ui/Preaccounting.u.php:invoice-status-*-button' => [
			'reason' => 'l\'état de la facture, Invoice::DRAFT, CONFIRMED, GENERATED ou CANCELED',
			'classes' => ['invoice-status-draft-button', 'invoice-status-confirmed-button', 'invoice-status-generated-button', 'invoice-status-canceled-button'],
		],
		'commercialisation/selling/ui/Invoice.u.php:invoice-status-*-button' => [
			'reason' => 'l\'état de la facture, Invoice::DRAFT, CONFIRMED, GENERATED ou CANCELED',
			'classes' => ['invoice-status-draft-button', 'invoice-status-confirmed-button', 'invoice-status-generated-button', 'invoice-status-canceled-button'],
		],
		'commercialisation/selling/ui/Sale.u.php:invoice-status-*-button' => [
			'reason' => 'l\'état de la facture, Invoice::DRAFT, CONFIRMED, GENERATED ou CANCELED',
			'classes' => ['invoice-status-draft-button', 'invoice-status-confirmed-button', 'invoice-status-generated-button', 'invoice-status-canceled-button'],
		],
		'commercialisation/selling/ui/Sale.u.php:sale-preparation-status-*-button' => [
			'reason' => 'les quatre états que le menu d\'actions groupées propose, Sale::CONFIRMED, PREPARED, DELIVERED et CANCELED',
			'classes' => ['sale-preparation-status-confirmed-button', 'sale-preparation-status-prepared-button', 'sale-preparation-status-delivered-button', 'sale-preparation-status-canceled-button'],
		],
		'commercialisation/shop/ui/Date.u.php:*-header-date-content' => [
			'reason' => 'getEmptyPeriod(), que getDeliveryPeriod() appelle sans lui passer son préfixe : la boutique seulement',
			'classes' => ['shop-header-date-content'],
		],
		'commercialisation/shop/ui/Date.u.php:*-header-date-day' => [
			'reason' => 'le préfixe de getDeliveryPeriod(), shop pour la boutique, website-widget pour le widget du site (Widget.u.php)',
			'classes' => ['shop-header-date-day', 'website-widget-header-date-day'],
		],
		'commercialisation/shop/ui/Shop.u.php:*-header-block' => [
			'reason' => 'le préfixe de getDateHeader(), shop pour la boutique, website-widget pour le widget du site (Widget.u.php)',
			'classes' => ['shop-header-block', 'website-widget-header-block'],
		],
		'commercialisation/shop/ui/Shop.u.php:*-header-date' => [
			'reason' => 'le préfixe de getDateHeader(), comme *-header-block',
			'classes' => ['shop-header-date', 'website-widget-header-date'],
		],
		'commercialisation/shop/ui/Shop.u.php:*-header-period' => [
			'reason' => 'le préfixe de getDateHeader(), comme *-header-block',
			'classes' => ['shop-header-period', 'website-widget-header-period'],
		],
		'ouvretaferme/presentation/ui/Feature.u.php:feature-block-screenshot-*-*' => [
			'reason' => 'le côté de la capture, left ou right, et l\'un des cinq fonds tirés par mt_rand(1, 5)',
			'classes' => [
				'feature-block-screenshot-left-1', 'feature-block-screenshot-left-2', 'feature-block-screenshot-left-3', 'feature-block-screenshot-left-4', 'feature-block-screenshot-left-5',
				'feature-block-screenshot-right-1', 'feature-block-screenshot-right-2', 'feature-block-screenshot-right-3', 'feature-block-screenshot-right-4', 'feature-block-screenshot-right-5',
			],
		],
	];

	/**
	 * Familles `util-<segment>` montrées sur la page et que l'index « Trouver un composant » ne cite
	 * pas, avec la section qui les montre : une session qui cherche par ce qu'elle veut obtenir ne
	 * les trouve pas. Mesuré le 2026-09-26 par shisow puis le 2026-10-01 sur `origin/main` à
	 * `d7f3203fd` : 84 familles définies, 80 montrées, 58 citées dans l'index.
	 *
	 * Vidée le 2026-10-16, chacune des seize familles restantes ayant reçu sa ligne. Elle ne se
	 * remplit plus (`NOT_INDEXED_MAX`) : une famille entre dans l'index par une ligne de `finder()`
	 * écrite dans les mots de celui qui cherche, jamais par le nom du composant seul.
	 */
	const NOT_INDEXED = [];

	/**
	 * Le plafond de `NOT_INDEXED`, qui ne se relève pas : une famille nouvelle entre sur la page avec
	 * sa ligne d'index. Il se baisse avec la liste.
	 */
	const NOT_INDEXED_MAX = 0;

	/**
	 * `color-muted` sur un `<a>` : le gris atténué se lit comme éteint, et le lien n'est pas cliqué
	 * (`#convention-gris-inactif`). Sur `origin/main` à `1b782bb23`, 8 liens dans 4 fichiers.
	 */
	const LINK_MUTED = [
		// Les actions d'une immobilisation dans le registre, atténuées à côté de son libellé
		'accounting/asset/ui/Register.u.php' => 3,
		// Le lien d'une opération sans écriture, sur un bouton en lecture seule
		'accounting/bank/ui/Cashflow.u.php' => 1,
		// Le compte d'un tiers dans la balance, cité par la recette de la liasse
		'accounting/journal/ui/ThirdPartyBalanceAi.u.php' => 1,
		// L'adresse de livraison d'une vente, vers la carte, atténuée sous le nom du client
		'commercialisation/selling/ui/Sale.u.php' => 3,
		// Le contre-exemple de #convention-gris-inactif, montré pour être écarté
		'ouvretaferme/design/ui/Design.u.php' => 1,
	];

	/**
	 * `dropdown-toggle` sans `data-dropdown` : le bouton porte la flèche d'un menu et n'en ouvre
	 * aucun. Sur `origin/main` à `1b782bb23`, 5 boutons dans 2 fichiers.
	 */
	const TOGGLE_WITHOUT_DROPDOWN = [
		'accounting/articles/view/doc.v.php' => 4,
		// La roue dessinée dans le texte de l'aide, qu'on montre et qui ne s'ouvre pas
		// L'outil d'un onglet dans la démonstration des onglets, réduit à sa forme
		'ouvretaferme/design/ui/Design.u.php' => 1,
	];

	/**
	 * `badge-solid` dans un `<h1>` ou sur la ligne d'un `getPageHeader()` : un état dans le titre de
	 * l'écran a sa forme, qui n'est pas une marque pleine. Aucun sur `origin/main` à `1b782bb23`.
	 */
	const TITLE_SOLID = [];

	/**
	 * Une case à cocher ou un bouton radio `disabled` écrit à la main, hors de `FormUi` : une valeur
	 * figée est du texte (`#convention-valeur-figee`), et un réglage verrouillé passe par le
	 * formulaire avec sa raison (`#convention-reglage-verrouille`). Aucun sur `origin/main` à
	 * `1b782bb23` : `production/series/ui/Cultivation.u.php` le pose par un ternaire, que la règle
	 * laisse à la relecture.
	 */
	const CONTROL_DISABLED = [];

	/**
	 * Une classe de conteneur sur un `<input>`, un `<select>` ou un `<textarea>` : elle dessine le
	 * bloc autour du champ, pas le champ. Aucune sur `origin/main` à `1b782bb23`.
	 */
	const CONTAINER_ON_CONTROL = [];

	const CONTAINERS = ['input-group', 'form-group', 'form-control-group', 'util-block', 'util-card', 'util-cards', 'util-table'];

	/**
	 * Un modificateur `util-*` posé sans le parent où il agit (`util-search-2` sans `util-search`) :
	 * il n'a aucun effet. Le parent se lit dans les feuilles, il n'est déclaré nulle part. Aucun sur
	 * `origin/main` à `1b782bb23`.
	 */
	const MODIFIER_WITHOUT_PARENT = [];

	/**
	 * Les préfixes de classes du cadriciel et du design system que le contrôle des règles regarde.
	 * Une classe de package (`shop-product`, `series-item`) n'est pas concernée : elle vit dans la
	 * feuille de son package et le test ne peut pas savoir laquelle.
	 */
	const PREFIXES = ['util-', 'badge', 'btn-', 'tab-', 'dropdown-', 'form-', 'td-', 'tr-', 'table-', 'nav-'];

	/**
	 * Une classe posée une seule fois compte. Il y a eu un plancher à 5 ici, justifié par « le bruit
	 * d'un écran d'administration ou d'un essai ne vaut pas une exception déclarée » ; il a été
	 * retiré le 2026-09-17, sur la mesure de C-Commercialisation, parce qu'il n'écartait plus aucun
	 * bruit. Le relevé qui l'avait fixé comptait quinze cas dont quatorze étaient des faux positifs
	 * de bornage (`customer-form-category` lu comme `form-category`), et ceux-là ont été corrigés
	 * depuis par le `(?<![a-zA-Z0-9_-])` du motif : la raison d'être du plancher avait disparu, pas
	 * le plancher. Descendu à 1, il ne fait apparaître que cinq classes, toutes préfixées cadriciel
	 * ou design system, toutes réelles, déclarées ci-dessus.
	 *
	 * ⚠️ Ce qu'il cachait était exactement ce que ce test existe pour attraper : `util-variation-flat`,
	 * posée une fois par `shop/ui/Date.u.php` et attendue par un test, nommée dans un commentaire de
	 * `component.css` et définie nulle part. La pastille « = 0 % » sortait sans sa couleur et la
	 * suite rendait 76/76. Un plancher est un critère : il se mesure sur ce qu'il écarte, pas sur ce
	 * qu'il laisse passer.
	 */
	const MIN_USAGES = 1;

	private function read(string $file): string {

		$path = LIME_DIRECTORY.'/'.$file;

		$this->assertTrue(is_file($path), "le fichier ".$file." existe encore");

		return (string)file_get_contents($path);

	}

	/**
	 * Les fichiers du dépôt d'une extension donnée, hors dépendances node et scripts personnels.
	 * @return array<string, string> chemin depuis la racine => chemin absolu
	 */
	private function getFiles(string $suffix): array {

		$files = [];

		foreach(\Package::getList() as $package => $app) {

			$path = \Package::getPath($package);

			if(is_dir($path) === FALSE) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

			foreach($iterator as $file) {

				if(
					str_ends_with($file->getFilename(), $suffix) === FALSE or
					str_contains($file->getPathname(), '/nodejs/') or
					str_contains($file->getPathname(), '/node_modules/') or
					str_contains($file->getPathname(), '/perso/')
				) {
					continue;
				}

				$files[substr($file->getPathname(), strlen(LIME_DIRECTORY) + 1)] = $file->getPathname();

			}

		}

		ksort($files);

		return $files;

	}

	/**
	 * Les classes définies par les feuilles du design system. Deux familles seulement : `util-*`, et
	 * les marques, qui portent un nom nu depuis le 2026-09-12 — `badge` est au design system ce que
	 * `btn` est déjà aux boutons.
	 * @return string[]
	 */
	private function getDefined(): array {

		$classes = [];

		foreach(self::SHEETS as $sheet) {
			preg_match_all('/\.(util-[a-z0-9-]+|badge[a-z0-9-]*)/', $this->read($sheet), $matches);
			$classes = array_merge($classes, $matches[1]);
		}

		$classes = array_unique($classes);
		sort($classes);

		return $classes;

	}

	/**
	 * Les classes du design system écrites en clair sur la page, les préfixes qu'elle complète par
	 * concaténation (`'util-card-accent-'.$accent`, `'badge-'.$tint`), et les valeurs qu'elle
	 * énumère en clair, qui sont ce que ces préfixes reçoivent.
	 * @return array{classes: string[], prefixes: string[], values: string[]}
	 */
	private function getPresented(): array {

		$page = $this->read(self::PAGE);

		preg_match_all('/(?<![a-zA-Z0-9_-])(?:util-[a-z0-9-]*|badge[a-z0-9-]*)/', $page, $matches);

		$classes = [];
		$prefixes = [];

		foreach(array_unique($matches[0]) as $token) {
			if(str_ends_with($token, '-')) {
				$prefixes[] = $token;
			} else {
				$classes[] = $token;
			}
		}

		preg_match_all('/\'([a-z][a-z0-9-]*)\'/', $page, $values);

		return ['classes' => $classes, 'prefixes' => $prefixes, 'values' => array_unique($values[1])];

	}

	/**
	 * Les familles `util-<segment>` qu'un texte nomme, variantes et sous-éléments ramenés à leur
	 * premier segment : `util-deck-aside` et `util-deck` sont la famille `util-deck`.
	 * @return string[]
	 */
	private static function getFamilies(string $text): array {

		preg_match_all('/(?<![a-zA-Z0-9_-])util-[a-z0-9]+/', $text, $matches);

		$families = array_unique($matches[0]);
		sort($families);

		return $families;

	}

	/**
	 * Le corps de `finder()`, de sa déclaration à l'accolade qui ferme la méthode. Borné par la
	 * méthode et non par des numéros de ligne, qui bougent au premier ajout sur la page.
	 */
	private function getFinder(): string {

		preg_match('/\n\tpublic function finder\(.*?\n\t\}\n/s', $this->read(self::PAGE), $matches);

		return $matches[0] ?? '';

	}

	/**
	 * Les fragments d'un source JavaScript qui posent une classe : arguments de `classList.add`,
	 * `remove` et `toggle`, membre droit d'une affectation à `className`, attribut `class` d'un
	 * balisage écrit dans une chaîne ou un gabarit.
	 * @return string[]
	 */
	private static function findPostedInJs(string $source): array {

		preg_match_all('/classList\.(?:add|remove|toggle)\(([^)]*)\)/', $source, $calls);
		preg_match_all('/className\s*\+?=(?!=)\s*([^;\n]*)/', $source, $assignments);
		preg_match_all('/class=\\\\?"([^"]*)"/', $source, $attributes);

		return array_merge($calls[1], $assignments[1], $attributes[1]);

	}

	/**
	 * Toutes les classes qu'une feuille du dépôt nomme dans un sélecteur, en clés.
	 * @return array<string, int>
	 */
	private function getRules(): array {

		$defined = [];

		foreach($this->getFiles('.css') as $path) {
			preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]+)/', (string)file_get_contents($path), $matches);
			$defined = array_merge($defined, $matches[1]);
		}

		return array_flip(array_unique($defined));

	}

	/**
	 * Les gabarits des noms assemblés d'un attribut `class="…"` lu dans le source : chaque
	 * `'.$variable.'` devient `*`, et seul compte un nom où une variable précède un tiret littéral.
	 * @return string[]
	 */
	private static function findAssembled(string $attribute): array {

		$template = preg_replace('/\'\s*\.\s*\$[A-Za-z_][A-Za-z0-9_]*(?:\[[^\]]*\]|->[A-Za-z_][A-Za-z0-9_]*)*\s*\.\s*\'/', '*', $attribute);

		return array_values(array_filter(preg_split('/\s+/', $template, -1, PREG_SPLIT_NO_EMPTY), fn(string $name) => str_contains($name, '*-')));

	}

	private static function getClassPattern(): string {
		return '/(?<![a-zA-Z0-9_-])((?:'.join('|', array_map(fn($prefix) => preg_quote($prefix, '/'), self::PREFIXES)).')[a-z0-9-]*[a-z0-9])(?![a-z0-9-])/';
	}

	private function startsWithOne(string $class, array $prefixes): bool {

		foreach($prefixes as $prefix) {
			if(str_starts_with($class, $prefix)) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Une classe que la page construit par concaténation n'est présentée que si la page énumère
	 * aussi la valeur qui la complète. Le préfixe seul ne prouve rien : `'badge-'.$tint` laissait
	 * passer toute classe `badge-*`, et l'amont définissait ainsi `.badge-expired` sans la montrer
	 * nulle part (mesuré le 2026-09-24, la seule des 373 classes définies que ce resserrement
	 * ajoute).
	 */
	private function isConcatenated(string $class, array $presented): bool {

		foreach($presented['prefixes'] as $prefix) {

			if(str_starts_with($class, $prefix) and in_array(substr($class, strlen($prefix)), $presented['values'], TRUE)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Toute classe définie par le design system se voit sur la page, ou dit pourquoi elle ne s'y
	 * voit pas.
	 */
	public function testEveryDefinedClassIsPresented(): void {

		$presented = $this->getPresented();
		$absent = array_keys(self::ABSENT);
		$rendered = array_keys(self::RENDERED_BY);

		$missing = [];

		foreach($this->getDefined() as $class) {

			if(
				in_array($class, $presented['classes'], TRUE) or
				in_array($class, $absent, TRUE) or
				$this->isConcatenated($class, $presented) or
				$this->startsWithOne($class, $rendered)
			) {
				continue;
			}

			$missing[] = $class;

		}

		$this->assertEquals([], $missing, "classes définies dans le design system et absentes de la page, sans raison déclarée : ".join(', ', $missing));

	}

	/**
	 * Une classe déclarée absente qui apparaît sur la page, ou qui n'est plus définie, est une
	 * déclaration périmée.
	 */
	public function testEveryAbsentDeclarationIsStillTrue(): void {

		$presented = $this->getPresented()['classes'];
		$defined = $this->getDefined();

		foreach(self::ABSENT as $class => $reason) {
			$this->assertFalse(in_array($class, $presented, TRUE), $class." est déclarée absente de la page et s'y trouve pourtant : retirer la déclaration");
			$this->assertTrue(in_array($class, $defined, TRUE), $class." est déclarée absente de la page mais n'est plus définie : retirer la déclaration");
		}

		foreach(self::RENDERED_BY as $prefix => $helper) {
			$covered = array_filter($defined, fn($class) => str_starts_with($class, $prefix));
			$this->assertTrue($covered !== [], "le préfixe ".$prefix." (rendu par ".$helper.") ne couvre plus aucune classe définie : retirer la déclaration");
		}

	}

	/**
	 * Tout ce que la page nomme existe dans une feuille du dépôt : une classe montrée sans règle est
	 * une promesse sur laquelle un codeur va s'appuyer.
	 */
	public function testEveryPresentedClassIsDefined(): void {

		$defined = [];

		foreach($this->getFiles('.css') as $path) {
			preg_match_all('/\.(util-[a-z0-9-]+|badge[a-z0-9-]*)/', (string)file_get_contents($path), $matches);
			$defined = array_merge($defined, $matches[1]);
		}

		$defined = array_unique($defined);

		$undefined = array_values(array_diff($this->getPresented()['classes'], $defined, array_keys(self::NAMED_WITHOUT_RULE)));

		$this->assertEquals([], $undefined, "classes nommées sur la page du design system qu'aucune feuille ne définit : ".join(', ', $undefined));

		foreach(self::NAMED_WITHOUT_RULE as $class => $reason) {
			$this->assertFalse(in_array($class, $defined, TRUE), $class." est déclarée sans règle et une feuille la définit maintenant : retirer la déclaration");
		}

	}

	/**
	 * Une classe du cadriciel ou du design system posée en PHP a une règle quelque part, ou est
	 * déclarée sans règle avec le fichier qui la pose.
	 */
	public function testEveryClassPostedInUiHasARule(): void {

		$defined = $this->getRules();

		$usages = [];
		$pattern = self::getClassPattern();

		foreach(array_merge($this->getFiles('.u.php'), $this->getFiles('.v.php')) as $path) {

			preg_match_all('/class="([^"]*)"/', (string)file_get_contents($path), $attributes);

			foreach($attributes[1] as $attribute) {
				preg_match_all($pattern, $attribute, $matches);
				foreach($matches[1] as $class) {
					$usages[$class] = ($usages[$class] ?? 0) + 1;
				}
			}

		}

		$unstyled = [];

		foreach($usages as $class => $count) {

			if($count < self::MIN_USAGES or isset($defined[$class]) or isset(self::UNSTYLED[$class])) {
				continue;
			}

			$unstyled[] = $class.' ('.$count.')';

		}

		$this->assertEquals([], $unstyled, "classes posées en PHP qu'aucune feuille ne définit, sans déclaration : ".join(', ', $unstyled));

		foreach(self::UNSTYLED as $class => $where) {
			$this->assertFalse(isset($defined[$class]), $class." est déclarée sans règle et une feuille la définit maintenant : retirer la déclaration");
			$this->assertTrue(($usages[$class] ?? 0) >= self::MIN_USAGES, $class." est déclarée sans règle mais n'est plus posée ".self::MIN_USAGES." fois (".($usages[$class] ?? 0)."), retirer la déclaration");
		}

	}

	/**
	 * Le relevé des noms assemblés trouve une variable suivie d'un tiret, nue, indexée ou en
	 * propriété, et laisse passer le préfixe seul et les noms écrits en entier.
	 */
	public function testAssembledDetector(): void {

		$attributes = explode("\n", <<<'PHP'
			'.$cssPrefix.'-header-block '.$cssPrefix.'-header-period
			btn invoice-status-'.$eInvoice['status'].'-button
			feature-block-screenshot-'.$align.' feature-block-screenshot-'.$align.'-'.$background.'
			x-' . $e->color . '-y badge-'.$tint.' util-card '.($open ? 'open' : '').'
			PHP);

		$this->assertEquals(
			['*-header-block', '*-header-period', 'invoice-status-*-button', 'feature-block-screenshot-*-*', 'x-*-y'],
			array_merge(...array_map(fn(string $attribute) => self::findAssembled($attribute), $attributes))
		);

	}

	/**
	 * Un nom de classe assemblé est déclaré avec toutes les classes qu'il produit, et chacune a sa
	 * règle dans une feuille du dépôt.
	 */
	public function testEveryAssembledClassHasARule(): void {

		$found = [];

		foreach(array_merge($this->getFiles('.u.php'), $this->getFiles('.v.php')) as $file => $path) {

			preg_match_all('/class="([^"]*)"/', (string)file_get_contents($path), $attributes);

			foreach($attributes[1] as $attribute) {
				foreach(self::findAssembled($attribute) as $template) {
					$found[$file.':'.$template] = TRUE;
				}
			}

		}

		$this->assertTrue(count($found) > 5, "le relevé ne lit plus les noms de classe assemblés (".count($found)." relevés, 10 sur 0b2621a56) : il rendrait un vert qui ne prouve rien");

		$new = array_keys(array_diff_key($found, self::ASSEMBLED));

		$this->assertEquals([], $new, "noms de classe assemblés sans déclaration, introuvables à qui retire la règle d'une des classes produites (le code fait foi : écrire le nom en entier, ou déclarer dans ASSEMBLED chaque classe qu'il produit, avec la raison) : ".join(' · ', $new));

		$stale = array_keys(array_diff_key(self::ASSEMBLED, $found));

		$this->assertEquals([], $stale, "noms de classe déclarés dans ASSEMBLED que le code n'assemble plus, retirer la déclaration : ".join(' · ', $stale));

		$defined = $this->getRules();

		$unreasoned = [];
		$foreign = [];
		$unstyled = [];

		foreach(self::ASSEMBLED as $key => ['reason' => $reason, 'classes' => $classes]) {

			if($reason === '' or $classes === []) {
				$unreasoned[] = $key;
			}

			$template = substr($key, strpos($key, ':') + 1);
			$pattern = '/^'.str_replace('\\*', '[a-z0-9-]+', preg_quote($template, '/')).'$/';

			foreach($classes as $class) {

				if(preg_match($pattern, $class) !== 1) {
					$foreign[] = $class.' ('.$key.')';
				} else if(isset($defined[$class]) === FALSE) {
					$unstyled[] = $class.' ('.$key.')';
				}

			}

		}

		$this->assertEquals([], $unreasoned, "nom assemblé déclaré dans ASSEMBLED sans raison ou sans classe produite : ".join(' · ', $unreasoned));
		$this->assertEquals([], $foreign, "classe déclarée dans ASSEMBLED que son gabarit ne peut pas produire, corriger la déclaration : ".join(' · ', $foreign));
		$this->assertEquals([], $unstyled, "classe produite par un nom assemblé qu'aucune feuille ne définit plus (le code fait foi : remettre la règle, ou retirer la valeur du code puis de la déclaration) : ".join(' · ', $unstyled));

	}

	/**
	 * La collecte des classes posées en JavaScript trouve chaque forme de pose et laisse passer les
	 * lectures : sans cette preuve, une liste vide se lirait comme « tout a une règle ».
	 */
	public function testJsDetector(): void {

		$source = <<<'JS'
			node.classList.add(isTop ? 'dropdown-a' : 'dropdown-b');
			node.classList.toggle('util-c', isOpen);
			node.classList.remove('util-d');
			family.className = 'util-e';
			h += `<div class="util-f ${state}">`;
			h += "<a class=\"btn-g\">";
			if(node.classList.contains('btn-read')) {}
			if(node.className === 'util-compared') {}
			node.qs('.util-selected');
			JS;

		preg_match_all(self::getClassPattern(), join("\n", self::findPostedInJs($source)), $matches);

		$this->assertEquals(['dropdown-a', 'dropdown-b', 'util-c', 'util-d', 'util-e', 'util-f', 'btn-g'], $matches[1]);

	}

	/**
	 * Une classe du cadriciel ou du design system posée en JavaScript a une règle quelque part, ou
	 * est déclarée sans règle avec le fichier qui la pose.
	 */
	public function testEveryClassPostedInJsHasARule(): void {

		$defined = $this->getRules();

		$usages = [];
		$pattern = self::getClassPattern();

		foreach($this->getFiles('.js') as $file => $path) {

			preg_match_all($pattern, join("\n", self::findPostedInJs((string)file_get_contents($path))), $matches);

			foreach($matches[1] as $class) {
				$usages[$class][$file] = TRUE;
			}

		}

		$this->assertTrue(count($usages) > 50, "la collecte ne lit plus les poses de classe des .js (".count($usages)." classes relevées, 70 sur origin/main à d7f3203fd) : elle rendrait un vert qui ne prouve rien");

		$unstyled = [];

		foreach($usages as $class => $files) {

			if(isset($defined[$class]) or isset(self::UNSTYLED_JS[$class])) {
				continue;
			}

			$unstyled[] = $class.' ('.join(', ', array_keys($files)).')';

		}

		$this->assertEquals([], $unstyled, "classes posées en JavaScript qu'aucune feuille ne définit (le code fait foi : écrire la règle ou retirer la pose, et ne déclarer dans UNSTYLED_JS qu'une classe qui n'a rien à dessiner) : ".join(' · ', $unstyled));

		foreach(self::UNSTYLED_JS as $class => $where) {
			$this->assertFalse(isset($defined[$class]), $class." est déclarée sans règle dans UNSTYLED_JS et une feuille la définit maintenant : retirer la déclaration");
			$this->assertTrue(isset($usages[$class]), $class." est déclarée sans règle dans UNSTYLED_JS mais aucun .js ne la pose plus : retirer la déclaration");
		}

	}

	/**
	 * Aucun fichier du dépôt ne nomme une classe retirée, hors des cas déclarés dans `RETIRED_KEPT`.
	 * Le relevé lit le PHP, le JavaScript et les feuilles, tests compris, et ce fichier excepté, qui
	 * nomme les classes pour les refuser.
	 */
	public function testNoRetiredClassIsPosted(): void {

		$found = [];

		foreach(array_merge($this->getFiles('.php'), $this->getFiles('.js'), $this->getFiles('.css')) as $file => $path) {

			if($file === substr(__FILE__, strlen(LIME_DIRECTORY) + 1)) {
				continue;
			}

			foreach(file($path) as $index => $line) {
				foreach(self::RETIRED as $class => $replacement) {
					if(preg_match('/(?<![\w-])'.preg_quote($class, '/').'(?![\w-])/', $line)) {
						$found[$file.':'.$class] ??= $file.':'.($index + 1).' → '.$replacement;
					}
				}
			}

		}

		$new = array_values(array_diff_key($found, self::RETIRED_KEPT));

		$this->assertEquals([], $new, "classes retirées du design system, à remplacer par la classe nommée après la flèche : ".join(' · ', $new)." · un emploi qui ne peut pas être renommé dans son lot, parce qu'une autre session tient le fichier, se déclare dans RETIRED_KEPT avec sa raison.");

		$stale = array_keys(array_diff_key(self::RETIRED_KEPT, $found));

		$this->assertEquals([], $stale, "cas déclarés dans RETIRED_KEPT que leur fichier ne porte plus, retirer la déclaration : ".join(' · ', $stale));

	}

	/**
	 * Toute famille `util-*` montrée sur la page a une ligne dans l'index « Trouver un composant »,
	 * ou est déclarée dans `NOT_INDEXED`, qui ne peut que descendre.
	 */
	public function testEveryPresentedFamilyIsIndexed(): void {

		$finder = $this->getFinder();

		$this->assertTrue(str_contains($finder, "'util-table'"), "le corps de finder() ne se lit plus dans ".self::PAGE." : la borne de getFinder() est à revoir, sans quoi aucune famille ne paraît indexée");

		$defined = self::getFamilies(join(' ', $this->getDefined()));
		$presented = array_intersect($defined, self::getFamilies($this->read(self::PAGE)));
		$indexed = self::getFamilies($finder);

		$missing = array_values(array_diff($presented, $indexed, array_keys(self::NOT_INDEXED)));

		$this->assertEquals([], $missing, "familles montrées sur la page du design system sans ligne dans l'index « Trouver un composant » (le code fait foi : écrire dans finder() la ligne qui y mène, par ce qu'on veut obtenir, et ne pas l'ajouter à NOT_INDEXED) : ".join(', ', $missing));

		$stale = array_values(array_diff(array_keys(self::NOT_INDEXED), array_diff($presented, $indexed)));

		$this->assertEquals([], $stale, "familles déclarées dans NOT_INDEXED que l'index cite maintenant, ou que la page ne montre plus : retirer la déclaration et baisser NOT_INDEXED_MAX : ".join(', ', $stale));

		$this->assertEquals(self::NOT_INDEXED_MAX, count(self::NOT_INDEXED), "NOT_INDEXED porte ".count(self::NOT_INDEXED)." familles et NOT_INDEXED_MAX vaut ".self::NOT_INDEXED_MAX." : le plafond suit la liste à la baisse et ne se relève jamais, une famille nouvelle prend sa ligne dans finder()");

	}

	public function init() {

		$parents = self::getParents(join("\n", array_map(fn(string $sheet) => file_get_contents(LIME_DIRECTORY.'/'.$sheet), self::SHEETS)));

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(self::isPresentation($file) === FALSE) {
				continue;
			}

			$lines = explode("\n", file_get_contents($path));

			$this->addFound('linkMuted', $file, self::findLinkMuted($lines));
			$this->addFound('toggleWithoutDropdown', $file, self::findToggleWithoutDropdown($lines));
			$this->addFound('titleSolid', $file, self::findTitleSolid($lines));
			$this->addFound('controlDisabled', $file, self::findControlDisabled($lines));
			$this->addFound('containerOnControl', $file, self::findContainerOnControl($lines));
			$this->addFound('modifierWithoutParent', $file, self::findModifierWithoutParent($lines, $parents));

		}

	}

	/**
	 * Les balises d'un nom donné sur une ligne. Un `->` ou un `=>` de la concaténation PHP qui la
	 * construit ne la ferme pas.
	 * @return string[]
	 */
	private static function findTags(string $line, string $names): array {

		preg_match_all('/<(?:'.$names.')\b(?:->|=>|[^<>])*>/', $line, $matches);

		return $matches[0];

	}

	/**
	 * Le contenu des attributs `class` d'une ligne ou d'une balise, en mots. Les bornes d'une
	 * concaténation PHP séparent les mots comme une espace : dans
	 * `class="util-comment'.($editable ? ' util-comment-editable' : '').'"`, le parent est entier.
	 * @return string[][]
	 */
	private static function findClasses(string $text): array {

		preg_match_all('/\bclass="([^"]*)"/', $text, $matches);

		return array_map(fn(string $value) => preg_split('/[\s\'.()?:]+/', $value, flags: PREG_SPLIT_NO_EMPTY), $matches[1]);

	}

	/**
	 * Une ligne est relevée une fois par balise fautive.
	 * @param string[] $lines
	 * @param \Closure(string): bool $isFaulty
	 * @return int[]
	 */
	private static function findInTags(array $lines, string $names, \Closure $isFaulty): array {

		$found = [];

		foreach($lines as $position => $line) {

			foreach(self::findTags($line, $names) as $tag) {

				if($isFaulty($tag)) {
					$found[] = $position + 1;
				}

			}

		}

		return $found;

	}

	private static function findLinkMuted(array $lines): array {
		return self::findInTags($lines, 'a', fn(string $tag) => array_filter(self::findClasses($tag), fn(array $classes) => in_array('color-muted', $classes, TRUE)) !== []);
	}

	private static function findToggleWithoutDropdown(array $lines): array {
		return self::findInTags($lines, 'a|button', fn(string $tag) => (
			array_filter(self::findClasses($tag), fn(array $classes) => in_array('dropdown-toggle', $classes, TRUE)) !== [] and
			str_contains($tag, 'data-dropdown') === FALSE
		));
	}

	/**
	 * Un `disabled` précédé d'une apostrophe est la fin d'un ternaire (`' disabled'`) : la règle ne
	 * juge pas une condition.
	 */
	private static function findControlDisabled(array $lines): array {
		return self::findInTags($lines, 'input', fn(string $tag) => (
			preg_match('/\btype="(?:checkbox|radio)"/', $tag) === 1 and
			preg_match('/(?<!\')\sdisabled\b/', $tag) === 1
		));
	}

	private static function findContainerOnControl(array $lines): array {
		return self::findInTags($lines, 'input|select|textarea', fn(string $tag) => array_filter(self::findClasses($tag), fn(array $classes) => array_intersect(self::CONTAINERS, $classes) !== []) !== []);
	}

	/**
	 * Le titre de l'écran se lit dans un `<h1>`, ou sur la ligne qui appelle `getPageHeader()`, dont
	 * le titre est le premier argument.
	 * @return int[]
	 */
	private static function findTitleSolid(array $lines): array {

		$found = [];

		foreach($lines as $position => $line) {

			if(
				str_contains($line, 'badge-solid') and
				(preg_match('/<h1\b.*\bbadge-solid\b/', $line) === 1 or str_contains($line, 'getPageHeader('))
			) {
				$found[] = $position + 1;
			}

		}

		return $found;

	}

	/**
	 * Les parents de chaque modificateur `util-*`, lus dans les feuilles : une classe qui n'est jamais
	 * seule dans un sélecteur composé (`.util-search.util-search-2`) n'agit qu'avec l'une des classes
	 * qui l'y accompagnent. Une classe posée seule une fois au moins n'est le modificateur de rien.
	 * @return array<string, string[]> modificateur => parents possibles
	 */
	private static function getParents(string $css): array {

		$css = preg_replace('#/\*.*?\*/#s', '', $css);

		preg_match_all('/([^{}]+)\{/', $css, $matches);

		$companions = [];

		foreach($matches[1] as $selectors) {

			if(str_contains($selectors, '@')) {
				continue;
			}

			foreach(explode(',', $selectors) as $selector) {

				foreach(preg_split('/\s*[\s>+~]\s*/', trim($selector)) as $compound) {

					preg_match_all('/\.([a-zA-Z0-9_-]+)/', preg_replace('/:(?:not|is|where|has)\([^)]*\)/', '', $compound), $classes);
					$classes = array_unique($classes[1]);

					foreach($classes as $class) {

						if(str_starts_with($class, 'util-')) {
							$companions[$class][] = array_values(array_diff($classes, [$class]));
						}

					}

				}

			}

		}

		$parents = [];

		foreach($companions as $class => $list) {

			if(in_array([], $list, TRUE) === FALSE) {
				$parents[$class] = array_values(array_unique(array_merge(...$list)));
			}

		}

		return $parents;

	}

	/**
	 * @param array<string, string[]> $parents
	 * @return int[]
	 */
	private static function findModifierWithoutParent(array $lines, array $parents): array {

		$found = [];

		foreach($lines as $position => $line) {

			foreach(self::findClasses($line) as $classes) {

				foreach(array_intersect(array_keys($parents), $classes) as $modifier) {

					if(array_intersect($parents[$modifier], $classes) === []) {
						$found[] = $position + 1;
					}

				}

			}

		}

		return $found;

	}

	public function testFormDetectors(): void {

		$lines = explode("\n", <<<'HTML'
			<a href="#" class="color-muted">Voir</a> <a class="color-muted-light">
			<a href="'.$url.'" class="btn color-muted"> <span class="color-muted">
			<a class="btn dropdown-toggle"> <a class="btn dropdown-toggle" data-dropdown="bottom-end">
			<button class="dropdown-toggle" onclick="'.$this->open().'"> <a class="dropdown-toggle-icon">
			<h1>Vente <span class="badge badge-solid">Payée</span></h1> <h2><span class="badge badge-solid">
			getPageHeader(s("Vente").'<span class="badge badge-solid">'
			<input type="checkbox" disabled> <input type="checkbox"'.($locked ? ' disabled' : '').'>
			<input type="radio" name="x" disabled="disabled"> <input type="text" disabled>
			<input class="form-control input-group"> <select class="form-control"> <div class="input-group">
			<textarea class="util-block">
			HTML);

		$this->assertEquals([1, 2], self::findLinkMuted($lines));
		$this->assertEquals([3, 4], self::findToggleWithoutDropdown($lines));
		$this->assertEquals([5, 6], self::findTitleSolid($lines));
		$this->assertEquals([7, 8], self::findControlDisabled($lines));
		$this->assertEquals([9, 10], self::findContainerOnControl($lines));

		$parents = self::getParents('.util-search { } .util-search.util-search-2 { } .util-circle { } .util-circle-lg.util-circle, .x .util-alone { } /* .util-gone.util-circle { } */');

		$this->assertEquals(['util-search-2' => ['util-search'], 'util-circle-lg' => ['util-circle']], $parents);

		$lines = explode("\n", <<<'HTML'
			<div class="util-search-2"> <div class="util-search util-search-2">
			<div class="util-circle util-circle-lg"> <div class="util-alone util-circle-lg">
			<div class="util-search'.($open ? ' util-search-2' : '').'">
			HTML);

		$this->assertEquals([1, 2], self::findModifierWithoutParent($lines, $parents));

	}

	public function testLinkMuted(): void {
		$this->assertRatchet('linkMuted', self::LINK_MUTED, 'color-muted sur un lien (le code fait foi : un lien garde sa couleur, le gris atténué reste à ce qui ne réagit pas, cf. #convention-gris-inactif sur la page du design system, et un fichier neuf ne se déclare pas)');
	}

	public function testToggleWithoutDropdown(): void {
		$this->assertRatchet('toggleWithoutDropdown', self::TOGGLE_WITHOUT_DROPDOWN, 'dropdown-toggle sans data-dropdown (le code fait foi : poser data-dropdown="bottom-end" et la dropdown-list qui le suit, ou retirer dropdown-toggle d\'un bouton qui n\'ouvre aucun menu)');
	}

	public function testTitleSolid(): void {
		$this->assertRatchet('titleSolid', self::TITLE_SOLID, 'badge-solid dans le titre de l\'écran (le code fait foi : l\'état d\'un objet se pose hors du titre, dans la forme montrée par #design-marks sur la page du design system)');
	}

	public function testControlDisabled(): void {
		$this->assertRatchet('controlDisabled', self::CONTROL_DISABLED, 'case à cocher ou bouton radio disabled écrit à la main (le code fait foi : une valeur figée s\'écrit en texte, cf. #convention-valeur-figee, et un réglage verrouillé passe par FormUi avec sa raison, cf. #convention-reglage-verrouille)');
	}

	public function testContainerOnControl(): void {
		$this->assertRatchet('containerOnControl', self::CONTAINER_ON_CONTROL, 'classe de conteneur sur un champ (le code fait foi : la classe va sur l\'élément qui entoure le champ, et le champ garde form-control)');
	}

	public function testModifierWithoutParent(): void {
		$this->assertRatchet('modifierWithoutParent', self::MODIFIER_WITHOUT_PARENT, 'modificateur util-* posé sans le parent où il agit (le code fait foi : poser la classe parente sur le même élément, celle que nomme le sélecteur du modificateur dans component.css ou reboot.css)');
	}

}
?>
