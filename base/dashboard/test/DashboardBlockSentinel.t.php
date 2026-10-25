<?php
namespace dashboard;

/**
 * Un bloc du tableau de bord n'est déclaré nulle part dans le moteur : il vit dans la paire
 * `DashboardLib` + `DashboardUi` du package qui l'apporte, que la convention résout depuis le segment
 * précédant le premier tiret de son nom. Cette sentinelle vérifie que la paire est complète, que ce
 * qu'elle annonce existe encore, et que le moteur n'a pas recommencé à nommer un bloc.
 *
 * La même paire déclare, par `getContributions()`, les pièces du poste du haut, qui se rangent dans un
 * emplacement de `DashboardSetting::SLOTS` au lieu d'une section. **Chaque contrôle d'inventaire porte
 * sur les deux formes** : elles partagent la convention de nom, le filtre de visibilité, l'identifiant
 * de nœud et les trois méthodes d'UI, donc elles partagent aussi leurs modes de panne. Un sixième s'y
 * ajoute, qu'aucune des deux formes ne connaît seule : le même nom déclaré des deux côtés.
 *
 * Rien dans le code ne rappellera ce contrat à qui ajoutera un bloc dans six mois, depuis un package
 * qui n'a aucune raison d'ouvrir `base/dashboard`. C'est pourquoi il est écrit une fois
 * (`base/dashboard/CLAUDE.md`) et tenu ici, sous la forme d'un **inventaire** : la liste réelle se
 * dérive de `\Package::getList()`, jamais d'une table, et chaque écart est nommé.
 *
 * Les modes de panne que ces contrôles gardent fermés ne se voient pas au même endroit, et ce sont les
 * silencieux qui justifient le fichier :
 *
 * - un `DashboardLib` sans `DashboardUi` : l'écran lève sur le premier accueil où le bloc paraît,
 *   « Class not found », loin du package qui l'a déclaré ;
 * - une fonctionnalité qui a changé de nom : `Structure::conf()` ne lève pas sur elle, il rend faux, et
 *   le bloc est masqué pour tout le monde sans que personne ne sache pourquoi ;
 * - un bloc `light` déclaré par un package qui vit dans une base de structure : il paraît juste en dev,
 *   sur une structure dont la base est branchée, et `/` ne branche celle de personne ;
 * - un `ReloadAction` dans l'action d'un bloc : l'écran entier repart pour une ligne, et rien ne le
 *   dit, sinon le poids de la réponse.
 *
 * Aucune écriture en base, aucune base ouverte, aucun comportement : cette sentinelle tourne avec
 * toutes les autres (`dev/test package='*' file='*Sentinel*'`) en quelques millisecondes.
 *
 * Lancement : dev/test package=dashboard file=DashboardBlockSentinel
 */
class DashboardBlockSentinelTest extends \Test {

	/**
	 * Les blocs connus au 2026-09-28, et la raison de chaque entrée : c'est elle qu'on relit quand la
	 * sentinelle dit qu'une déclaration est périmée.
	 *
	 * ⚠️ Les blocs s'écrivent en toutes lettres et non par la constante du package. Une déclaration
	 * doit pouvoir devenir fausse et se faire **nommer** : lue dans `\series\DashboardLib::PLANNING`,
	 * elle disparaîtrait avec la classe au lieu d'être signalée, et le fichier ne s'analyserait même
	 * plus.
	 */
	const array BLOCKS = [
		// L15, A. Les interventions de la semaine que le membre suit, faites sur prévues, avec les espèces
		// qu'elles touchent, et « Choisir » pour en changer (`choose`). `light`, parce que `series` vit
		// dans la base commune ; remplace la carte de la semaine et les semis et plantations.
		'series-week' => "les interventions suivies de la semaine",
		// L15, A. Ce qui a été récolté cette semaine, par unité et par espèce, et les cultures que le
		// plan met en récolte. `light`, `seriesHarvest` étant dans la base commune et indexé par semaine.
		'planning-harvest' => "les récoltes de la semaine",
		// L32. Les rappels du pense-bête restés à faire des semaines passées, puis ceux de la semaine,
		// lus par les deux lectures de l'écran de la Semaine. `light`, `planningReminder` étant dans la
		// base commune et indexé par `(structure, status, week)`.
		'planning-reminder' => "les rappels du pense-bête à faire",
		// Lot 13, E. Les espèces qui ont des ventes à livrer, avec leur stock et ce qui en a été récolté
		// cette semaine, lues par `HarvestWeekLib::get()` comme l'écran Récoltes. `heavy` : 14 à 29
		// requêtes selon la structure.
		'planning-sales' => "les ventes à venir face aux récoltes de la semaine",
		// Incrément 2.3. Les quatre prochaines livraisons en ligne des boutiques de la structure, leur
		// date, où en est leur prise de commandes et ce qu'elles ont déjà pris. `light`, parce que
		// `shop` vit dans la base commune.
		'shop-dates' => "les prochaines livraisons en boutique",
		// Incrément 2.1. Les jours de livraison des sept prochains jours et les six plus grosses
		// commandes du premier, avec ce qui en est déjà préparé. `light`, parce que `selling` vit dans
		// la base commune.
		'selling-deliveries' => "les commandes à préparer et à livrer",
		// Incrément 2.2. Le nombre de factures émises qu'il reste à encaisser, leur reste dû, combien ont
		// dépassé leur échéance et depuis quand. Écrit sa requête plutôt que d'appeler
		// `InvoiceLib::countReminder()`, qui ne compte que les factures à relancer.
		'selling-unpaid' => "ce qu'il reste à encaisser sur les factures émises",
		// Incrément 2.4. Les ventes commencées et jamais finies dont la livraison est passée depuis moins
		// de trente jours, la borne du plan (§5.4) : un brouillon de trois ans est un déchet, et
		// l'accueil n'est pas l'endroit où on trie les déchets. Elle avait été retirée le 2026-09-29 par
		// le chef de projet, puis rétablie le même jour, la décision du plan l'emportant.
		'selling-drafts' => "les ventes en brouillon",
		// Lot 4. Les paiements que le rapprochement propose déjà, et les cinq premiers dans l'ordre de
		// son écran. Premier bloc `heavy` : les suggestions vivent dans la base de la structure.
		// `canManageAccounting`, parce que le geste qu'il annonce est une écriture comptable.
		'bank-reconciliation' => "les paiements à rapprocher",
		// Lot 4. Chaque cahier de caisse actif, son solde et où en est sa clôture. `canObserveAccounting`,
		// parce qu'il ne fait que montrer ce que l'écran des cahiers montre à un observateur.
		'cash-registers' => "les cahiers de caisse et leur clôture",
		// 3D, R7. L'image du dernier exercice clos qui porte une écriture, dont le bouton ouvre la scène en
		// plein écran. `heavy`, les exercices vivant dans la base de la structure ; `canAnalyzeAccounting`,
		// le droit de la page du bourg. Absent sans exercice clos.
		'town-year' => "le bourg du dernier exercice clos",
		// G9-33. Les producteurs dont la demande de dates attend le bureau, chacun avec « Choisir les
		// dates ». `light`, parce que `amap` vit dans la base commune ; `canManage`, comme le choix des dates.
		'amap-dates' => "les producteurs qui demandent leurs dates",
		// G9-23. Les contrats ouverts qui ont une liste d'attente et une place libre, ou une place proposée
		// qui attend sa réponse, calculés et non enregistrés. `light`, parce que `amap` vit dans la base
		// commune ; `canManage`, comme les autres gestes du bureau.
		'amap-places' => "les places libérées sur un contrat qui a une liste d'attente",
		// G9-21 T3. Ce qu'un producteur a renvoyé au bureau, une ligne par refus et son `type` : une date
		// proposée qu'il a refusée ou laissée sans réponse, avec « Proposer une autre date » et « Déduire
		// les paniers ». `light`, parce que `amap` vit dans la base commune ; `canManage`, comme les
		// autres gestes du bureau.
		'amap-refusals' => "les refus des producteurs",
		// Lot 5. La prochaine distribution de l'AMAP détaillée, puis les trois suivantes. `light`, parce
		// que `amap` vit dans la base commune ; `canAmapCollective`, les dates étant celles du collectif.
		'amap-distributions' => "les prochaines distributions de l'AMAP",
		// Lot 5. Les postes de permanence non pourvus de la saison à venir, par date. `canManage`, comme
		// l'écran des permanences.
		'amap-duties' => "les postes de permanence à pourvoir",
		// Lot 5. Les adhésions en attente de règlement et celles à relancer, en comptes seuls : aucun
		// montant sur l'accueil. `canManage`, comme l'écran des adhésions.
		'amap-memberships' => "les adhésions qui attendent un geste du bureau",
		// amap-oeil-neuf, lot demandes. Les changements de formule qui attendent les producteurs, chacun
		// avec la souscription qu'il change. En Suivre, parce que c'est le producteur qui répond ; `light`,
		// parce que `amap` vit dans la base commune ; `canManage`, comme la fiche des adhérents.
		'amap-changes' => "les changements de formule qui attendent les producteurs",
		// G11-16. Les invitations de producteurs et d'administrateurs qui attendent leur réponse, en
		// comptes seuls, lus comme la mise en route les lit. En Suivre, parce que c'est l'invité qui
		// répond ; `light`, parce que `amap` vit dans la base commune ; `canManage`, comme les invitations.
		'amap-invites' => "les invitations qui attendent leur réponse",
		// G10-16. Les demandes d'adhésion en attente, cinq nommées avec « Accepter » et « Refuser » et
		// les autres comptées, chacune avec les demandes du même foyer probable. `light`, parce que
		// `amap` vit dans la base commune ; `canManage`, comme l'écran des demandes.
		'amap-requests' => "les demandes d'adhésion en attente",
		// amap-oeil-neuf, lot temps. Les saisons finies que le bureau n'a pas closes, chacune avec « Clore
		// la saison ». `light`, parce que `amap` vit dans la base commune ; `canManage`, comme le panneau
		// de clôture.
		'amap-season' => "les saisons finies à clore",
		// Lot 12, puis amap-oeil-neuf, lot accueil-mise-en-route. La mise en route de l'AMAP, rendue par
		// `StartUi` en tête de la bande, toute la largeur, jusqu'à ce que ses étapes soient faites.
		// `light`, parce que tout se lit dans la base commune ; `canManage`, comme les réglages qu'elle ouvre.
		'amap-start' => "les étapes de la mise en route de l'AMAP qui restent",
		// Lot 12. Les entrées du catalogue de la page d'arrivée que la ferme n'a jamais essayées, en pied
		// d'accueil sous `settings`. `light`, parce que tout se lit dans la base commune ; `canManage`,
		// comme la page d'arrivée.
		'structure-start' => "les fonctionnalités que la ferme n'a jamais essayées"
	];

	/**
	 * Les contributions connues au 2026-09-29, avec la raison de chaque entrée et la même règle que
	 * `BLOCKS` : le nom en toutes lettres, jamais par une constante de package.
	 */
	const array CONTRIBUTIONS = [
		// Incrément 2.6, en phrase sous la date depuis L62. Le montant hors taxes livré la semaine en
		// cours, avec celui de la semaine précédente. `figure`, qui ne se règle pas, sans cache : une
		// requête groupée sur deux semaines, et la semaine en cours change à chaque livraison.
		'selling-revenue' => "le chiffre d'affaires de la semaine",
		// L9, ludique B. Le lundi seulement, le bilan de la semaine passée comparé à la même semaine de
		// l'an dernier, le cumul depuis janvier et les dix semaines en grand. `bilan`, en dernière bande
		// de l'accueil (L9, planche C), qui ne se règle pas ; `NULL` les autres jours.
		'selling-bilan' => "le bilan du lundi",
		// L71, piste C. Le lundi seulement, les récoltes de la semaine passée : le kilo en grand comparé à
		// la même semaine de l'an dernier, les autres unités sans comparaison, les trois premières espèces.
		// `bilan`, après celui des ventes ; `NULL` les autres jours et sans récolte.
		'planning-bilan' => "le bilan des récoltes du lundi",
		// Incrément 2.6. Ce qu'il reste à préparer pour le prochain jour de livraison, à l'échéance de ce
		// jour. `queue`. Rien à préparer, aucune ligne.
		'selling-preparing' => "les commandes à préparer",
		// L67, B8. Les commandes confirmées ou préparées des sept jours passés, jamais marquées livrées,
		// leur montant et la plus ancienne. `queue`, pour une structure qui a marqué une livraison dans
		// les trente jours seulement. Aucune, aucune ligne.
		'selling-late' => "les commandes en retard de livraison",
		// Incrément 2.6, lot L60, dédoublée en deux files au lot L66 (planche C). Les factures qui attendent
		// leur première relance, puis celles qui attendent la deuxième au délai que la structure a réglé, et
		// leur reste dû, rangées par la plus ancienne échéance : une échéance passée est la plus proche qui
		// soit, donc la ligne passe devant ce qui échoit plus tard. `queue`. Aucune dans la file, aucune ligne.
		'selling-reminder-first' => "les factures pour une 1re relance",
		'selling-reminder-second' => "les factures pour une 2e relance",
		// L70, piste C. Les factures échues qu'aucune des deux files ne prend, déjà relancées sans deuxième
		// relance à venir ou réglées en partie, qui attendent un règlement ou une relance à la main. `queue`,
		// après les deux relances. Aucune, aucune ligne.
		'selling-overdue' => "les factures échues à régler ou relancer",
		// AMAP, lot producteur-legal. Les ventes d'AMAP d'un producteur que la tâche de nuit ne passe pas
		// livrées faute d'informations légales : celles des dates passées en retard, sinon celles de la
		// prochaine date. `queue`, sans `feature` ni `accept` : la ligne suit les ventes bloquées, pas
		// l'appartenance à une AMAP, et un producteur sorti qui en garde la voit encore. Aucune requête
		// quand les informations sont remplies.
		'amap-legal' => "les ventes d'AMAP bloquées par les informations légales",
		// L15, M2. La météo de la position de la structure : `weather`, lue dans le cache que remplit un
		// cron, son lien cite la source. Dans la tête de l'accueil jusqu'à L52, qui l'a portée au groupe
		// Bureau du menu sous le nom `structure-weather`.
		'structure-weather' => "la météo du groupe Bureau du menu",
		// L40, piste D. Les trois dernières nouvelles du blog, en pied d'accueil après le bilan : `news`,
		// lues en base commune, `cache` d'une heure, réglable. La dernière garde sa carte quatorze jours.
		'website-news' => "les nouvelles du blog"
	];

	/** Les méthodes que le `DashboardLib` d'un package doit porter, dans l'ordre où le moteur les appelle. */
	const array METHODS_LIB = ['getBlocks', 'getContributions', 'load', 'getUrl'];

	/** Les méthodes que le `DashboardUi` d'un package doit porter. Le cadre est au socle : il n'en demande pas d'autre. */
	const array METHODS_UI = ['getTitle', 'render', 'getUrlLabel'];

	/**
	 * Les méthodes qu'un bloc qui déclare `choose` porte des deux côtés de sa paire, et lui seul : le Lib
	 * rend ce qu'on peut choisir, l'UI le contenu du panneau. Une paire sans bloc `choose` ne les porte
	 * pas, ce serait une méthode que personne n'appelle.
	 */
	const array METHODS_CHOOSE = ['getChoice'];

	/**
	 * Le nom d'un bloc comme d'une contribution : le package, un tiret, ce qu'il montre. Les minuscules
	 * et le tiret sont ce que `DashboardSetting::getPackage()` sait découper, et ce que l'identifiant
	 * d'un nœud sait porter.
	 */
	const string PATTERN_NAME = '/^[a-z]+-[a-z-]+$/';

	/**
	 * La paire d'un package, sous les deux seules formes où le nom d'une classe s'écrit : `\<package>\`
	 * dans le corps, ou `use <package>\` en tête. Le motif exige un nom de package **littéral** entre
	 * deux antislashs, ce qui le sépare de la classe que le moteur construit à l'exécution
	 * (`'\\'.DashboardSetting::getPackage($block).'\\DashboardUi'`), qui ne nomme aucun package.
	 */
	const string PATTERN_PAIR = '/(?:\\\\|use\s+)([a-z][a-zA-Z0-9_]*)\\\\Dashboard(?:Lib|Ui)\b/';

	/** Un univers du menu : `$sections['x'] = [\\Asset::icon('y'), s("Z")];` dans `getSections()`. */
	const string PATTERN_MENU = '/\\$sections\\[\'([a-z]+)\'\\] = \\[\\\\Asset::icon\\(\'([a-z0-9-]+)\'\\), s\\("([^"]+)"\\)\\]/';

	/** Une branche de `getSectionName()` : `'x' => s("Z")`. */
	const string PATTERN_BOARD_NAME = '/\'([a-z]+)\' => s\\("([^"]+)"\\)/';

	/** Une branche de `getSectionIcon()` : `'x' => \\Asset::icon('y')`. */
	const string PATTERN_BOARD_ICON = '/\'([a-z]+)\' => \\\\Asset::icon\\(\'([a-z0-9-]+)\'\\)/';

	/**
	 * Les contrôles savent-ils encore trouver ?
	 *
	 * Tant qu'aucun package n'apporte de bloc, les inventaires ci-dessous portent sur des listes vides
	 * et passeraient au vert quoi qu'il arrive. Les témoins prouvent que la comparaison nomme l'écart
	 * dans les deux sens et que les motifs reconnaissent ce qu'ils cherchent, avant qu'un vert veuille
	 * dire quelque chose.
	 */
	public function testDetectors(): void {

		$found = ['series-week', 'structure-start'];
		$declared = ['structure-start', 'shop-sales'];

		$this->assertEquals(['series-week'], array_values(array_diff($found, $declared)), 'un bloc apparu sans déclaration se nomme');
		$this->assertEquals(['shop-sales'], array_values(array_diff($declared, $found)), 'une déclaration devenue fausse se nomme');

		$this->assertEquals(1, preg_match(self::PATTERN_NAME, 'series-week'), 'le motif du nom accepte un nom bien formé');
		$this->assertEquals(0, preg_match(self::PATTERN_NAME, 'seriesPlanning'), 'le motif du nom refuse un nom sans tiret');
		$this->assertEquals(0, preg_match(self::PATTERN_NAME, 'series-Planning'), 'le motif du nom refuse une majuscule');

		foreach(['\series\DashboardLib::PLANNING => $eTask' => 'series', 'use series\DashboardUi;' => 'series'] as $temoin => $package) {
			$this->assertEquals($package, preg_match(self::PATTERN_PAIR, $temoin, $match) === 1 ? $match[1] : NULL, "le motif de la paire sait lire la forme par constante : ".$temoin);
		}

		$this->assertEquals(NULL, preg_match(self::PATTERN_PAIR, '$class = \'\\\\\'.DashboardSetting::getPackage($block).\'\\\\DashboardUi\';', $match) === 1 ? $match[1] : NULL, 'le motif de la paire ne voit pas la classe construite à l\'exécution');

		// Les deux inventaires de contributions sont vides à l'incrément 2.0 : sans ces témoins, les
		// contrôles qui les comparent passeraient au vert sans rien comparer.
		$this->assertEquals(['series-week'], array_values(array_intersect(['series-week', 'shop-sales'], ['series-week', 'shop-orders'])), 'un nom déclaré comme bloc et comme contribution se nomme');

		$known = ['slot', 'section', 'feature', 'right', 'default', 'cost', 'cache', 'position'];

		$this->assertEquals(['max'], array_values(array_diff(['slot', 'max'], $known)), 'une clé de déclaration que le moteur ne lit pas se nomme');

		$this->assertEquals(['planning-board déclare la colonne review', 'planning-week déclare la colonne side'], self::getUndeclarableColumns(['planning' => ['planning-board' => ['column' => DashboardSetting::REVIEW], 'planning-week' => ['column' => 'side'], 'planning-day' => ['column' => DashboardSetting::TODO], 'planning-note' => ['column' => DashboardSetting::FOLLOW], 'planning-month' => []]]), 'une colonne réservée ou inconnue se nomme, une colonne de bloc ou absente non');

		$this->assertEquals(['planning-board (band)'], self::getUnknownShapes(['planning' => ['planning-board' => ['shape' => 'band'], 'planning-week' => ['shape' => DashboardSetting::NOTE], 'planning-day' => []]]), 'une forme que le socle ne sait pas dessiner se nomme, une forme connue ou absente non');

	}

	/**
	 * Les sections que le moteur range sont-elles encore celles que le produit sait ouvrir ?
	 *
	 * `DashboardSetting::SECTIONS` en tient l'ordre, que le code ne peut pas dériver : c'est celui du
	 * menu. Le jeu, lui, se dérive des cas de `Structure::canSection()`, qui fait foi. Une section
	 * qu'elle ne connaît pas n'est ouvrable par personne, et un bloc rangé dessous ne paraîtrait sur
	 * aucun écran. Les deux sens ne disent pas la même chose : une section apparue n'est rangée nulle
	 * part, une section déclarée en trop range des blocs dans le vide.
	 */
	public function testSectionsSayWhatTheCodeCarries(): void {

		$found = self::getSections();
		$declared = DashboardSetting::SECTIONS;

		$this->assertTrue($found !== [], '`\structure\Structure::canSection()` est introuvable, ou son motif ne trouve plus aucun cas : la sentinelle lit le corps de cette méthode, et un renommage se reporte ici');

		$this->assertEquals([], array_values(array_diff($found, $declared)), "sections que canSection() ouvre et que le moteur ne range pas : ".implode(', ', array_diff($found, $declared)));
		$this->assertEquals([], array_values(array_diff($declared, $found)), "sections que le moteur range et que canSection() ne connaît plus : ".implode(', ', array_diff($declared, $found)));

	}

	/**
	 * Un package qui apporte des blocs apporte les deux côtés, ou aucun.
	 *
	 * Le moteur demande le sens d'un bloc à son `DashboardLib` et ses mots à son `DashboardUi`, sans
	 * jamais vérifier que le second existe : un package qui n'apporterait que le premier passerait tous
	 * les autres contrôles et tomberait sur l'accueil du premier membre.
	 */
	public function testEveryPackageCarriesBothSides(): void {

		$packagesLib = [];
		$packagesUi = [];

		foreach(\Package::getList() as $package => $app) {

			// Le package du moteur porte les deux classes, qui sont le moteur lui-même et n'apportent
			// aucun bloc.
			if($package === __NAMESPACE__) {
				continue;
			}

			if(class_exists('\\'.$package.'\\DashboardLib')) {
				$packagesLib[] = $package;
			}

			if(class_exists('\\'.$package.'\\DashboardUi')) {
				$packagesUi[] = $package;
			}

		}

		$this->assertEquals([], array_values(array_diff($packagesLib, $packagesUi)), "packages qui portent un DashboardLib sans DashboardUi : ".implode(', ', array_diff($packagesLib, $packagesUi)));
		$this->assertEquals([], array_values(array_diff($packagesUi, $packagesLib)), "packages qui portent un DashboardUi sans DashboardLib : ".implode(', ', array_diff($packagesUi, $packagesLib)));

	}

	/**
	 * La déclaration ci-dessus dit-elle encore ce que les packages apportent ?
	 *
	 * Les deux sens ne signalent pas la même chose : un bloc apparu sans déclaration est un bloc qu'on
	 * n'a pas relu, une déclaration sans bloc est une entrée devenue fausse.
	 */
	public function testDeclarationSaysWhatTheCodeCarries(): void {

		$found = array_keys(DashboardLib::getBlocks());
		$declared = array_keys(self::BLOCKS);

		$this->assertEquals([], array_values(array_diff($found, $declared)), "blocs apparus sans déclaration ici : ".implode(', ', array_diff($found, $declared)));
		$this->assertEquals([], array_values(array_diff($declared, $found)), "blocs déclarés ici qu'aucun package n'apporte plus : ".implode(', ', array_diff($declared, $found)));

		$found = array_keys(DashboardLib::getContributions());
		$declared = array_keys(self::CONTRIBUTIONS);

		$this->assertEquals([], array_values(array_diff($found, $declared)), "contributions apparues sans déclaration ici : ".implode(', ', array_diff($found, $declared)));
		$this->assertEquals([], array_values(array_diff($declared, $found)), "contributions déclarées ici qu'aucun package n'apporte plus : ".implode(', ', array_diff($declared, $found)));

	}

	/**
	 * Ce que chaque bloc annonce existe-t-il encore là où le moteur le lit ?
	 *
	 * Les fonctionnalités se lisent par un nom de propriété, qui ne lève pas quand il est inconnu : il rend
	 * faux, et le bloc disparaît pour tout le monde. Le droit, lui, s'appelle par son nom sur l'élément :
	 * un droit renommé lèverait, sur l'accueil de chaque membre.
	 */
	public function testEveryBindingStillExists(): void {

		$features = \structure\Feature::model()->getProperties();
		$sections = self::getSections();

		foreach(DashboardLib::getBlocks() + DashboardLib::getContributions() as $name => $declaration) {

			if($declaration['feature'] !== NULL) {
				$this->assertTrue(in_array($declaration['feature'], $features, TRUE), $name." : la fonctionnalité ".$declaration['feature']." est encore une colonne de structure\Feature");
			}

			foreach((array)$declaration['right'] as $right) {
				$this->assertTrue(method_exists(\structure\Structure::class, $right), $name." : le droit ".$right." est encore une méthode de structure\Structure");
			}

			$this->assertTrue(is_array($declaration['accept']), $name." : son accept est une liste de méthodes");

			// Un `can*` posé ici passerait sous silence : la clé se lirait comme un second `right`, et le
			// droit se trouverait dit à deux endroits.
			foreach($declaration['accept'] as $accept) {
				$this->assertTrue(str_starts_with($accept, 'accept') and method_exists(\structure\Structure::class, $accept), $name." : ".$accept." est une méthode accept* de structure\Structure");
			}

			$this->assertTrue(is_bool($declaration['default']) or $declaration['default'] instanceof \Closure, $name." : son défaut est un booléen ou une closure");
			$this->assertTrue(is_int($declaration['cache']), $name." : son cache est un nombre de secondes");

		}

		foreach(DashboardLib::getBlocks() as $block => $declaration) {

			$this->assertTrue(in_array($declaration['section'], $sections, TRUE), $block." : la section ".$declaration['section']." est encore une section de Structure::canSection()");

			$this->assertTrue(in_array($declaration['cost'], [DashboardSetting::LIGHT, DashboardSetting::HEAVY], TRUE), $block." : son coût ".$declaration['cost']." est light ou heavy");
			$this->assertTrue($declaration['max'] === NULL or is_int($declaration['max']), $block." : son plafond d'items est NULL ou un nombre");
			$this->assertTrue(is_int($declaration['position']), $block." : son rang dans sa section est un nombre");

		}

		foreach(DashboardLib::getContributions() as $contribution => $declaration) {

			$this->assertTrue(in_array($declaration['slot'], DashboardSetting::SLOTS, TRUE), $contribution." : l'emplacement ".$declaration['slot']." est encore un emplacement de DashboardSetting::SLOTS");
			$this->assertTrue(is_int($declaration['position']), $contribution." : son rang dans l'emplacement est un nombre");

			// Une contribution lourde n'a aucun abri : rien ne remplit son emplacement après coup, le
			// socle la chargeant dans la page (`loadContributions()` passe `deferred: FALSE`). La
			// première qui s'imposera viendra avec sa façon de ne pas faire sauter le poste, et lèvera
			// ce contrôle en même temps.
			$this->assertEquals(DashboardSetting::LIGHT, $declaration['cost'], $contribution." : une contribution est légère, le poste étant rendu dans la page");

		}

	}

	/**
	 * Une déclaration porte-t-elle une clé que le moteur ne lit pas ?
	 *
	 * `$declaration += self::getDefaults()` complète sans rien refuser : `'positon' => 2` ou un `max` posé
	 * sur une contribution, qui ne rend pas de liste, sont gardés tels quels et n'ont aucun effet. Le
	 * package croit avoir réglé quelque chose, l'écran ne bouge pas, et rien ne le dit.
	 */
	public function testNoDeclarationCarriesAnUnknownKey(): void {

		$found = [];

		$keys = [
			'getBlocks' => ['section', 'column', 'shape', 'feature', 'right', 'accept', 'default', 'cost', 'cache', 'max', 'position', 'choose'],
			'getContributions' => ['slot', 'section', 'feature', 'right', 'accept', 'default', 'cost', 'cache', 'position']
		];

		foreach($keys as $method => $known) {

			foreach(self::getDeclarationsByPackage($method) as $package => $declarations) {

				foreach($declarations as $name => $declaration) {

					foreach(array_diff(array_keys($declaration), $known) as $key) {
						$found[] = $name.' ('.$key.')';
					}

				}

			}

		}

		sort($found);

		$this->assertEquals([], $found, "déclarations qui portent une clé que le moteur ne lit pas : ".implode(', ', $found));

	}

	/**
	 * Le nom d'un bloc désigne-t-il le package qui le déclare ?
	 *
	 * C'est le nom, et rien d'autre, qui résout la paire : un bloc que `hr` déclarerait sous
	 * `selling-absences` ferait chercher ses mots dans `\selling\DashboardUi`, qui ne les a pas.
	 */
	public function testEveryNameResolvesToItsPackage(): void {

		foreach(['getBlocks', 'getContributions'] as $method) {

			foreach(self::getDeclarationsByPackage($method) as $package => $declarations) {

				foreach($declarations as $name => $declaration) {

					$this->assertEquals(1, preg_match(self::PATTERN_NAME, $name), $name." : son nom s'écrit <package>-<quoi>, en minuscules et tirets");
					$this->assertEquals($package, DashboardSetting::getPackage($name), $name." : son préfixe désigne le package qui le déclare, ".$package);

				}

			}

		}

	}

	/**
	 * Deux packages déclarent-ils le même nom ?
	 *
	 * Le moteur lève déjà à la lecture, et c'est le bon endroit. Ce contrôle-ci le voit **avant** :
	 * `getBlocks()` levant, tous les autres contrôles tomberaient d'un coup sans dire lequel des deux
	 * packages est en cause.
	 */
	public function testNoBlockIsDeclaredTwice(): void {

		foreach(['getBlocks' => 'blocs', 'getContributions' => 'contributions'] as $method => $what) {

			$packagesByName = [];

			foreach(self::getDeclarationsByPackage($method) as $package => $declarations) {

				foreach($declarations as $name => $declaration) {
					$packagesByName[$name][] = $package;
				}

			}

			$found = [];

			foreach($packagesByName as $name => $packages) {

				if(count($packages) > 1) {
					$found[] = $name.' ('.implode(', ', $packages).')';
				}

			}

			$this->assertEquals([], $found, $what." déclarés par deux packages : ".implode(', ', $found));

		}

	}

	/**
	 * Un nom est-il déclaré à la fois comme bloc et comme contribution ?
	 *
	 * Les deux formes partagent l'identifiant de nœud que le socle pose sur l'écran
	 * (`DashboardUi::getBlockId()`) : le même nom des deux côtés le poserait deux fois sur la même page,
	 * et le second nœud serait celui qu'aucune réponse ajax ne retrouve. Le moteur lève déjà à la
	 * lecture, et c'est le bon endroit ; ce contrôle-ci le voit **avant**, sans quoi tous les autres
	 * tomberaient d'un coup sans nommer le nom en cause.
	 */
	public function testNoNameIsBothABlockAndAContribution(): void {

		$blocks = [];
		$contributions = [];

		foreach(self::getDeclarationsByPackage('getBlocks') as $package => $declarations) {
			$blocks = array_merge($blocks, array_keys($declarations));
		}

		foreach(self::getDeclarationsByPackage('getContributions') as $package => $declarations) {
			$contributions = array_merge($contributions, array_keys($declarations));
		}

		$found = array_values(array_intersect($blocks, $contributions));

		sort($found);

		$this->assertEquals([], $found, "noms déclarés à la fois comme bloc et comme contribution : ".implode(', ', $found));

	}

	/**
	 * Le moteur a-t-il recommencé à nommer un bloc ?
	 *
	 * Deux façons de le faire, qui ne se voient pas au même endroit : le nom littéral d'un bloc écrit
	 * dans un fichier d'ici, et la paire d'un package citée par son nom de classe, ce qu'un auteur
	 * écrira plutôt puisque le `CLAUDE.md` racine demande d'employer la constante. Tous les fichiers du
	 * package sont lus, et non les seuls `.php` : un bloc nommé dans une feuille ou dans la
	 * documentation rouvre la même table.
	 */
	public function testEngineNamesNoBlock(): void {

		$names = array_merge(array_keys(DashboardLib::getBlocks()), array_keys(DashboardLib::getContributions()));

		$foundName = [];
		$foundPair = [];

		foreach(self::getEngineLines() as $place => $line) {

			foreach($names as $name) {

				if(str_contains($line, $name)) {
					$foundName[] = $place.' ('.$name.')';
				}

			}

			if(preg_match(self::PATTERN_PAIR, $line, $match) === 1 and $match[1] !== __NAMESPACE__) {
				$foundPair[] = $place.' ('.$match[1].')';
			}

		}

		sort($foundName);
		sort($foundPair);

		$this->assertEquals([], $foundName, "fichiers du moteur qui nomment un bloc ou une contribution : ".implode(', ', $foundName));
		$this->assertEquals([], $foundPair, "fichiers du moteur qui nomment la paire d'un package : ".implode(', ', $foundPair));

	}

	/**
	 * La paire porte-t-elle les méthodes que le moteur appelle ?
	 *
	 * Le moteur les appelle sans les vérifier : une méthode manquante lève sur l'accueil du premier
	 * membre qui voit le bloc, et non chez celui qui l'a écrite.
	 */
	public function testPairImplementsEveryMethod(): void {

		$found = [];

		foreach(\Package::getList() as $package => $app) {

			if($package === __NAMESPACE__) {
				continue;
			}

			$classLib = '\\'.$package.'\\DashboardLib';

			if(class_exists($classLib) === FALSE) {
				continue;
			}

			foreach(self::METHODS_LIB as $method) {

				if(method_exists($classLib, $method) === FALSE) {
					$found[] = $package.'\DashboardLib::'.$method.'()';
				}

			}

			foreach(self::METHODS_UI as $method) {

				if(method_exists('\\'.$package.'\\DashboardUi', $method) === FALSE) {
					$found[] = $package.'\DashboardUi::'.$method.'()';
				}

			}

		}

		$this->assertEquals([], $found, "méthodes que le moteur appelle et qu'une paire ne porte pas : ".implode(', ', $found));

	}

	/**
	 * Un bloc `choose` porte-t-il son panneau, et seul un bloc `choose` le porte-t-il ?
	 *
	 * Le bouton « Choisir » est posé par le socle sur la seule déclaration : une paire sans
	 * `getChoice()` lèverait au clic, chez le premier membre qui veut choisir. Dans l'autre sens, une
	 * paire qui porte `getChoice()` sans bloc qui le déclare garde du code que plus rien n'atteint.
	 */
	public function testChooseCarriesItsChoice(): void {

		$missing = [];
		$orphans = [];

		foreach(\Package::getList() as $package => $app) {

			$classLib = '\\'.$package.'\\DashboardLib';

			if($package === __NAMESPACE__ or class_exists($classLib) === FALSE) {
				continue;
			}

			$choose = array_filter(DashboardLib::getBlocks(), fn(array $declaration, string $block) => (DashboardSetting::getPackage($block) === $package and $declaration['choose']), ARRAY_FILTER_USE_BOTH) !== [];

			foreach([$classLib, '\\'.$package.'\\DashboardUi'] as $class) {

				foreach(self::METHODS_CHOOSE as $method) {

					$has = method_exists($class, $method);

					if($choose and $has === FALSE) {
						$missing[] = ltrim($class, '\\').'::'.$method.'()';
					} else if($choose === FALSE and $has) {
						$orphans[] = ltrim($class, '\\').'::'.$method.'()';
					}

				}

			}

		}

		$this->assertEquals([], $missing, "blocs qui déclarent choose sans leur panneau : ".implode(', ', $missing));
		$this->assertEquals([], $orphans, "panneaux de choix qu'aucun bloc ne déclare : ".implode(', ', $orphans));

	}

	/**
	 * La liste des méthodes dit-elle encore ce que le moteur délègue ?
	 *
	 * Elle était déclarée sans être confrontée au code : une délégation ajoutée au moteur sans branche
	 * dans une paire passait verte jusqu'à l'écran d'un membre. La liste réelle se lit dans les seuls
	 * endroits qui appellent la paire, le moteur et son UI, et les deux sens ne disent pas la même
	 * chose : une délégation non déclarée n'est éprouvée nulle part, une déclaration sans délégation est
	 * une entrée périmée.
	 */
	public function testDeclaredMethodsMatchEngineDelegations(): void {

		preg_match_all('/\$class::(\w+)\(/', file_get_contents(dirname(__DIR__).'/lib/Dashboard.l.php'), $matchesLib);
		preg_match_all('/\$ui->(\w+)\(/', file_get_contents(dirname(__DIR__).'/ui/Dashboard.u.php'), $matchesUi);

		$delegatedLib = array_values(array_unique($matchesLib[1]));
		$delegatedUi = array_values(array_unique($matchesUi[1]));

		$this->assertTrue($delegatedLib !== [], 'aucune délégation lue dans lib/Dashboard.l.php : le motif ne trouve plus rien');
		$this->assertTrue($delegatedUi !== [], 'aucune délégation lue dans ui/Dashboard.u.php : le motif ne trouve plus rien');

		$declaredLib = array_merge(self::METHODS_LIB, self::METHODS_CHOOSE);
		$declaredUi = array_merge(self::METHODS_UI, self::METHODS_CHOOSE);

		$this->assertEquals([], array_values(array_diff($delegatedLib, $declaredLib)), "méthodes que le moteur demande à un DashboardLib sans être déclarées ici : ".implode(', ', array_diff($delegatedLib, $declaredLib)));
		$this->assertEquals([], array_values(array_diff($declaredLib, $delegatedLib)), "méthodes déclarées ici que le moteur ne demande plus à un DashboardLib : ".implode(', ', array_diff($declaredLib, $delegatedLib)));

		$this->assertEquals([], array_values(array_diff($delegatedUi, $declaredUi)), "méthodes que le moteur demande à un DashboardUi sans être déclarées ici : ".implode(', ', array_diff($delegatedUi, $declaredUi)));
		$this->assertEquals([], array_values(array_diff($declaredUi, $delegatedUi)), "méthodes déclarées ici que le moteur ne demande plus à un DashboardUi : ".implode(', ', array_diff($declaredUi, $delegatedUi)));

	}

	/**
	 * Un bloc léger est-il déclaré par un package qui vit dans une base de structure ?
	 *
	 * Un bloc léger est rendu dans la page, et la page de l'accueil ne branche aucune base
	 * `structure_<id>` : `/` en particulier n'en branche jamais. Un tel bloc paraît juste en dev, sur
	 * une structure dont la base est ouverte par ailleurs, et lève ou se vide en production. La
	 * population se lit dans `StructureSetting::getPackages()`, jamais recopiée : elle évolue.
	 */
	public function testNoLightBlockReadsAStructureBase(): void {

		$packages = \structure\StructureSetting::getPackages();
		$found = [];

		// Les contributions y tombent toutes, étant toutes légères : une pièce du poste ne peut donc
		// jamais venir d'un package qui vit dans une base de structure.
		foreach(DashboardLib::getBlocks() + DashboardLib::getContributions() as $name => $declaration) {

			if(
				in_array(DashboardSetting::getPackage($name), $packages, TRUE)
				and $declaration['cost'] === DashboardSetting::LIGHT
			) {
				$found[] = $name;
			}

		}

		$this->assertEquals([], $found, "pièces légères déclarées par un package de base de structure : ".implode(', ', $found));

	}

	/**
	 * L'action d'un bloc recharge-t-elle l'écran ?
	 *
	 * Un `ReloadAction` fait repayer au serveur, à l'analyse et à la mise en page tout l'accueil pour
	 * une ligne. Une action ne renvoie que le nœud qu'elle change. Sont lues les pages `dashboard.p.php`
	 * et `dashboardAi.p.php` des packages qui portent un `DashboardLib`, **le socle compris**, dont les
	 * deux routes tombent sous la même règle ; celles des autres packages n'ont rien à voir avec ce
	 * moteur et ne sont pas lues.
	 *
	 * ⚠️ Les deux noms sont lus parce qu'un package hors du socle suffixe sa page neuve, et que l'absence
	 * d'un fichier est ici un `continue` : `selling` et `shop` portent un `DashboardLib` sans page, donc
	 * ce silence est légitime et ne peut pas devenir un échec. Un seul nom lu suffirait donc à retirer un
	 * package de la couverture sans que rien ne le signale, ce qu'a fait le suffixage de
	 * `production/series` le 2026-09-30.
	 */
	public function testNoDashboardActionReloads(): void {

		$found = [];

		foreach(\Package::getList() as $package => $app) {

			if(class_exists('\\'.$package.'\\DashboardLib') === FALSE) {
				continue;
			}

			foreach(['dashboard.p.php', 'dashboardAi.p.php'] as $file) {

				$path = \Package::getPath($package).'/page/'.$file;

				if(is_file($path) === FALSE) {
					continue;
				}

				foreach(file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {

					if(str_contains($line, 'ReloadAction')) {
						$found[] = $package.'/page/'.$file.':'.($number + 1);
					}

				}

			}

		}

		$this->assertEquals([], $found, "actions de bloc qui rechargent tout l'écran : ".implode(', ', $found));

	}

	/**
	 * Chaque univers rangé par le moteur a-t-il encore ses mots, sa pastille, sa teinte et sa décision
	 * de titre ?
	 *
	 * Les quatre `match` de `DashboardUi` n'ont pas de `default`, ce qui est voulu : une valeur inattendue
	 * doit échouer fort. Mais le contrôle des sections ci-dessus se satisfait d'une entrée ajoutée à
	 * `SECTIONS`, et l'accueil lèverait alors `UnhandledMatchError` sur le premier bloc rangé dessous.
	 * Le vert doit coûter les quatre branches, pas une. `hasSectionTitle()` rend un booléen, que le
	 * contrôle de la chaîne vide laisse passer : seule l'absence de branche y compte.
	 */
	public function testEverySectionHasItsWordsAndIcon(): void {

		$found = [];

		// Sans le constructeur : il pose la feuille de style du package, et le fichier d'une sentinelle
		// ne porte que l'inventaire. Les deux méthodes lues sont sans état.
		$ui = new \ReflectionClass(DashboardUi::class)->newInstanceWithoutConstructor();

		foreach(['getSectionName', 'getSectionIcon', 'getSectionButton', 'hasSectionTitle'] as $method) {

			$reflection = new \ReflectionMethod(DashboardUi::class, $method);
			$reflection->setAccessible(TRUE);

			foreach(DashboardSetting::SECTIONS as $section) {

				try {

					if($reflection->invoke($ui, $section) === '') {
						$found[] = $method.'('.$section.') rend une chaîne vide';
					}

				} catch(\UnhandledMatchError) {
					$found[] = $method.'('.$section.') n\'a pas de branche';
				}

			}

		}

		$this->assertEquals([], $found, "univers rangés par le moteur que l'écran ne sait pas rendre : ".implode(', ', $found));

	}

	/**
	 * Chaque colonne de l'accueil a-t-elle son titre, et chaque bloc qui en déclare une nomme-t-il une
	 * colonne que le socle tient ?
	 *
	 * `getColumnName()` n'a pas de `default`, pour la même raison que les `match` d'univers : une colonne
	 * ajoutée à `COLUMNS` sans son titre lèverait `UnhandledMatchError` sur le premier accueil rendu.
	 * Dans l'autre sens, la valeur d'une déclaration est un mot libre : `getBlocks()` la refuse au premier
	 * appel, donc sur l'écran de tout le monde, et ce contrôle la nomme avant. Un bloc ne déclare qu'une
	 * colonne de `COLUMNS_BLOCK` : « Bilan » a son titre, mais elle est réservée au bilan (L69).
	 */
	public function testEveryColumnHasItsTitleAndEveryBlockAKnownColumn(): void {

		$found = [];

		// Sans le constructeur, comme pour les univers : il pose la feuille de style du package.
		$ui = new \ReflectionClass(DashboardUi::class)->newInstanceWithoutConstructor();

		foreach(DashboardSetting::COLUMNS as $column) {

			try {

				if($ui->getColumnName($column) === '') {
					$found[] = 'getColumnName('.$column.') rend une chaîne vide';
				}

			} catch(\UnhandledMatchError) {
				$found[] = 'getColumnName('.$column.') n\'a pas de branche';
			}

		}

		$found = array_merge($found, self::getUndeclarableColumns(self::getDeclarationsByPackage('getBlocks')));

		$this->assertEquals([], $found, "colonnes que l'accueil ne sait pas rendre ou qu'un bloc ne peut pas prendre : un titre manquant s'ajoute dans `DashboardUi::getColumnName()`, une colonne de bloc s'ajoute à `DashboardSetting::COLUMNS` et `COLUMNS_BLOCK`, et « Bilan » (`REVIEW`) ne se déclare pas, elle est réservée au bilan : ".implode(', ', $found));

	}

	/** Les blocs dont la colonne déclarée n'est pas dans `DashboardSetting::COLUMNS_BLOCK`, l'absence valant « À suivre ». */
	private static function getUndeclarableColumns(array $declarationsByPackage): array {

		$found = [];

		foreach($declarationsByPackage as $declarations) {

			foreach($declarations as $block => $declaration) {

				if(array_key_exists('column', $declaration) and in_array($declaration['column'], DashboardSetting::COLUMNS_BLOCK, TRUE) === FALSE) {
					$found[] = $block.' déclare la colonne '.$declaration['column'];
				}

			}

		}

		return $found;

	}

	/**
	 * Chaque bloc qui déclare une forme nomme-t-il une forme que le socle sait dessiner ?
	 *
	 * Même raison que pour la colonne : `getBlocks()` refuse une forme inconnue au premier appel, donc sur
	 * l'écran de tout le monde, et ce contrôle la nomme avant.
	 */
	public function testEveryBlockHasAKnownShape(): void {

		$found = self::getUnknownShapes(self::getDeclarationsByPackage('getBlocks'));

		$this->assertEquals([], $found, "formes que l'accueil ne sait pas rendre, à corriger dans la déclaration du bloc ou à ajouter à `DashboardSetting::SHAPES` avec leur rendu dans `DashboardUi::getBlock()` : ".implode(', ', $found));

	}

	/** Les blocs dont la forme déclarée n'est pas dans `DashboardSetting::SHAPES`, l'absence valant la tuile. */
	private static function getUnknownShapes(array $declarationsByPackage): array {

		$found = [];

		foreach($declarationsByPackage as $declarations) {

			foreach($declarations as $block => $declaration) {

				if(array_key_exists('shape', $declaration) and in_array($declaration['shape'], DashboardSetting::SHAPES, TRUE) === FALSE) {
					$found[] = $block.' ('.$declaration['shape'].')';
				}

			}

		}

		return $found;

	}

	/**
	 * L'accueil nomme-t-il les univers comme le menu ?
	 *
	 * Le `CLAUDE.md` du package s'y engage, et les deux listes sont deux copies : l'ordre se dérive du
	 * menu, les mots et les pastilles y sont recopiés. Le jour où « Vente » change de nom dans le menu,
	 * l'accueil continuerait de dire « Vente » sans qu'aucun contrôle ne bouge, et la promesse écrite
	 * deviendrait fausse en silence. Les deux côtés se lisent dans leur source, les `s()` du menu étant
	 * posés sous des conditions qu'un test ne peut pas toutes réunir sur une même structure.
	 */
	public function testSectionsAreNamedAsTheMenu(): void {

		$menu = self::getMenuSections();
		$board = self::getBoardSections();

		$this->assertTrue($menu !== [], '`\StructureTemplate::getSections()` est introuvable, ou son motif ne trouve plus aucun univers : la sentinelle lit le corps de cette méthode, et un renommage se reporte ici');
		$this->assertTrue($board !== [], '`\dashboard\DashboardUi::getSectionIcon()` ou `getSectionName()` est introuvable, ou son motif ne trouve plus aucun univers : la sentinelle lit le corps de ces méthodes, et un renommage se reporte ici');

		$found = [];

		foreach($menu as $section => $menuWords) {

			if(array_key_exists($section, $board) === FALSE) {
				$found[] = $section.' est dans le menu et pas dans l\'accueil';
			} else if($board[$section] !== $menuWords) {
				$found[] = $section.' : le menu dit '.implode(' / ', $menuWords).', l\'accueil dit '.implode(' / ', $board[$section]);
			}

		}

		// Les univers que l'accueil nomme sans que le menu d'une ferme les porte, chacun avec sa raison.
		$boardOnly = [
			'amap' => "les blocs de l'accueil d'une AMAP gardent la section « amap » (`getUnivers()`), que nomme le bandeau de l'AMAP et non le menu d'une ferme",
		];

		foreach($board as $section => $boardWords) {

			if(
				array_key_exists($section, $menu) === FALSE and
				array_key_exists($section, $boardOnly) === FALSE
			) {
				$found[] = $section.' est dans l\'accueil et pas dans le menu, à nommer au menu ou à déclarer dans `$boardOnly` avec sa raison';
			}

		}

		foreach(array_keys($boardOnly) as $section) {

			if(array_key_exists($section, $menu) or array_key_exists($section, $board) === FALSE) {
				$found[] = $section.' est déclaré hors du menu, à tort : retirer sa déclaration';
			}

		}

		$this->assertEquals([], $found, "univers que l'accueil et le menu ne nomment pas pareil : ".implode(' · ', $found));

	}

	/**
	 * Le panneau du téléphone rend-il un groupe par univers du menu, et aucun autre ?
	 *
	 * Depuis que le groupe se rend sous la condition de son entrée du menu, les deux côtés tirent du
	 * même `getSections()`, et c'est ce qu'il faut tenir : le jour où un univers s'ajoute au menu sans
	 * que `getTabSections()` le consulte, son groupe ne se rendrait **jamais** dans le panneau, et rien
	 * ne le dirait, l'écran de cet univers s'ouvrant sur une sous-navigation vide. Dans l'autre sens,
	 * une clé que le menu ne rend plus laisse un groupe que rien ne peut atteindre.
	 *
	 * Trois listes et non deux, parce que la condition et le rendu sont deux lignes différentes du même
	 * `if` : un groupe ajouté sans sa garde n'apparaîtrait ni dans le menu ni dans les conditions, les
	 * deux premières listes resteraient égales, et le panneau rendrait pourtant un groupe qu'aucune
	 * icône ne peut allumer, soit exactement le défaut annoncé plus haut.
	 *
	 * **Le menu fait foi** : un écart se corrige dans `getTabSections()`, en ajoutant ou en retirant la
	 * condition du groupe et le groupe qu'elle garde. Un groupe rendu pour un univers que le menu ne
	 * porte pas n'a que deux issues, le retirer ou ouvrir l'univers au menu, et la seconde écrit dans
	 * `getSections()` : c'est ce que dit le message de `$unguarded`.
	 */
	public function testPanelGroupsMatchTheMenuSections(): void {

		// Le jeton du Bureau n'est pas dans `getSections()`, qui nomme les univers des écrans : il est
		// posé à part, par `getHome()`, et allume son groupe comme les autres.
		preg_match_all('/data-section="([a-z]+)"/', self::readMethod(\StructureTemplate::class, 'getHome'), $matches);

		$menu = array_merge($matches[1], array_keys(self::getMenuSections()));
		$guarded = self::getPanelGuardedGroups();
		$rendered = self::getPanelRenderedGroups();

		$this->assertTrue($menu !== [], '`\StructureTemplate::getSections()` est introuvable, ou son motif ne trouve plus aucun univers : la sentinelle lit le corps de cette méthode, et un renommage se reporte ici');
		$this->assertTrue($guarded !== [], '`\structure\MenuUi::getTabSections()` est introuvable, ou son motif `$isOnBar` ne trouve plus aucune condition : la sentinelle lit le corps de cette méthode, et un renommage se reporte ici');
		$this->assertTrue($rendered !== [], '`\structure\MenuUi::getTabSections()` est introuvable, ou son motif `$getGroup` ne trouve plus aucun groupe : la sentinelle lit le corps de cette méthode, et un renommage se reporte ici');

		$missing = array_values(array_diff($menu, $guarded));
		$extra = array_values(array_diff($guarded, $menu));

		$this->assertEquals([], $missing, "univers du menu dont `getTabSections()` ne rend aucun groupe, donc une sous-navigation vide sur leurs écrans : ".implode(', ', $missing).' (c\'est `getTabSections()` qui se corrige, le menu fait foi)');
		$this->assertEquals([], $extra, "groupes de `getTabSections()` qu'aucun univers du menu ne peut allumer, donc morts : ".implode(', ', $extra).' (c\'est `getTabSections()` qui se corrige, le menu fait foi)');

		$unguarded = array_values(array_diff($rendered, $guarded));
		$unrendered = array_values(array_diff($guarded, $rendered));

		$this->assertEquals([], $unguarded, 'groupes que `getTabSections()` rend sans les garder par `$isOnBar`, donc rendus dans un panneau dont le menu ne porte pas l\'icône : '.implode(', ', $unguarded).' (ajouter la condition au `if` du groupe, et l\'univers au menu s\'il n\'y est pas, le menu faisant foi)');
		$this->assertEquals([], $unrendered, 'conditions `$isOnBar` de `getTabSections()` dont aucun `$getGroup` ne rend le groupe, donc sans effet : '.implode(', ', $unrendered).' (rendre le groupe par `$getGroup`, ou retirer la condition si l\'univers a quitté le menu)');

	}

	/** Les univers dont `getTabSections()` garde le groupe par une condition. */
	private static function getPanelGuardedGroups(): array {

		return self::readSections(\structure\MenuUi::class, 'getTabSections', '/\\$isOnBar\\(\'([a-z]+)\'\\)/');

	}

	/** Les univers dont `getTabSections()` rend effectivement le groupe. */
	private static function getPanelRenderedGroups(): array {

		return self::readSections(\structure\MenuUi::class, 'getTabSections', '/\\$getGroup\\(\'([a-z]+)\'\\)/');

	}

	/**
	 * Le corps d'une méthode, lu par réflexion.
	 *
	 * Une méthode absente rend une chaîne vide, et non une `ReflectionException` : l'exception
	 * interromprait le fichier entier, donc toutes ses assertions disparaîtraient du compte et
	 * `Failed: 0` se lirait comme un vert. Chaque appelant nomme la méthode dans son message.
	 */
	private static function readMethod(string $class, string $method): string {

		if(method_exists($class, $method) === FALSE) {
			return '';
		}

		$reflection = new \ReflectionMethod($class, $method);

		return implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

	}

	/** Les univers nommés par un motif dans le corps d'une méthode. */
	private static function readSections(string $class, string $method, string $pattern): array {

		preg_match_all($pattern, self::readMethod($class, $method), $matches);

		return array_values(array_unique($matches[1]));

	}

	/** Les univers du menu, lus dans `getSections()`, la source unique dont `getStructureSections()` tire son rendu : section => [icône, libellé]. */
	private static function getMenuSections(): array {

		preg_match_all(self::PATTERN_MENU, self::readMethod(\StructureTemplate::class, 'getSections'), $matches, PREG_SET_ORDER);

		$sections = [];

		foreach($matches as $match) {
			$sections[$match[1]] = [$match[2], $match[3]];
		}

		return $sections;

	}

	/** Les univers de l'accueil, lus dans les deux `match` de `DashboardUi` : section => [icône, libellé]. */
	private static function getBoardSections(): array {

		$read = function(string $method, string $pattern): array {

			preg_match_all($pattern, self::readMethod(DashboardUi::class, $method), $matches, PREG_SET_ORDER);

			$values = [];

			foreach($matches as $match) {
				$values[$match[1]] = $match[2];
			}

			return $values;

		};

		$names = $read('getSectionName', self::PATTERN_BOARD_NAME);
		$icons = $read('getSectionIcon', self::PATTERN_BOARD_ICON);

		$sections = [];

		foreach($icons as $section => $icon) {

			if(array_key_exists($section, $names)) {
				$sections[$section] = [$icon, $names[$section]];
			}

		}

		return $sections;

	}

	/**
	 * Les sections que `Structure::canSection()` sait ouvrir, lues dans les cas de son `switch`.
	 *
	 * Le source et non une liste : c'est cette méthode qui décide, et une section qu'elle perd doit se
	 * faire nommer ici et non se découvrir sur un écran vide.
	 */
	private static function getSections(): array {

		preg_match_all('/case \'([a-z]+)\'/', self::readMethod(\structure\Structure::class, 'canSection'), $matches);

		return $matches[1];

	}

	/**
	 * Ce que chaque package déclare par l'une des deux méthodes : `[package => [nom => déclaration]]`.
	 *
	 * Lu package par package et non par `DashboardLib::getBlocks()` ou `getContributions()`, qui lèvent
	 * sur un doublon : tous les contrôles tomberaient alors d'un coup, sans dire lequel des deux
	 * packages est en cause. Les déclarations sont ici **brutes**, sans les défauts, ce qui est ce qu'il
	 * faut pour juger des clés qu'un package a écrites.
	 *
	 * Une paire qui ne porte pas la méthode est sautée et non fatale : c'est
	 * `testPairImplementsEveryMethod()` qui la nomme, et lui seul, sans quoi une paire incomplète ferait
	 * tomber tous les contrôles avec elle.
	 */
	private static function getDeclarationsByPackage(string $method): array {

		$declarations = [];

		foreach(\Package::getList() as $package => $app) {

			if($package === __NAMESPACE__) {
				continue;
			}

			$class = '\\'.$package.'\\DashboardLib';

			if(class_exists($class) === FALSE or method_exists($class, $method) === FALSE) {
				continue;
			}

			$declarations[$package] = $class::$method();

		}

		return $declarations;

	}

	/**
	 * Toutes les lignes des fichiers du moteur, indexées `chemin:ligne`. `test/` est hors contrôle,
	 * puisque c'est ici que les blocs sont écrits en toutes lettres, par construction.
	 *
	 * Le répertoire se déduit de l'emplacement de ce fichier : un package déplacé ne doit pas rendre les
	 * contrôles silencieusement vides.
	 */
	private static function getEngineLines(): array {

		$root = dirname(__DIR__);
		$lines = [];

		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

		foreach($files as $file) {

			$path = $file->getPathname();

			if(str_starts_with($path, $root.'/test/')) {
				continue;
			}

			foreach(file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {
				$lines[substr($path, strlen($root) + 1).':'.($number + 1)] = $line;
			}

		}

		return $lines;

	}

}
?>
