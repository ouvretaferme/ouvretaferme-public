<?php
namespace structure;

class StructureSetting extends \Settings {

	const INVITE_CUSTOMER_DELAY = 7;

	const CALENDAR_LIMIT = 20;

	const NEW_SEASON = 10;

	const PRODUCER_TYPES_COLLECTIVE = [Structure::PROJECT, Structure::SCHOOL];

	/**
	 * Les entrées de `StructureUi::getStartedFeatures()` que l'accueil peut désigner comme premier pas,
	 * dans l'ordre où les structures neuves commencent. Mesuré en dev le 19/10/2026 sur les 2 543
	 * structures productrices créées du 01/04 au 30/09 : la première ligne de 1 134 d'entre elles est
	 * une série pour 477, une vente pour 348, une boutique pour 208, un site pour 58, une caisse pour 43.
	 * La facturation électronique, la comptabilité et les relevés de prix ne sont pas mesurés, donc
	 * jamais désignés (lot L68 du chantier dashboard).
	 */
	const START_STEPS = ['planning', 'invoice', 'shop', 'website', 'market'];

	/**
	 * Les identifiants réservés au décor des tests, que rien ne distingue en base d'une structure réelle.
	 * Jamais vide : un NOT IN sur une liste vide écarte toutes les lignes.
	 */
	const FIXTURE_STRUCTURES = [4, 5, 6, 8, 22, 50, 54, 84, 87, 122, 164, 170, 173, 177, 208, 229, 265, 269, 283, 292, 315];

	const ROLE_FQNS_STAFF = ['advisor'];
	const ROLE_FQNS = ['admin', ...self::ROLE_FQNS_STAFF];

	const CATEGORIE_JURIDIQUE_ENTREPRENEUR_INDIVIDUEL = 1000;
	const CATEGORIE_JURIDIQUE_SOCIETE_ANONYME = ['from' => 5410, 'to' => 5710];
	const CATEGORIE_GAEC = 6533;
	const CATEGORIE_JURIDIQUE_AUTRE = 9999;
	const CATEGORIES_JURIDIQUES = [1000, 6598, 6533, 5410, 5500, 5710, 6597, 6317, 2200, 2300, 6318, 9999];
	const CATEGORIES_JURIDIQUES_FAMILLES = [54 => 5410, 55 => 5500, 57 => 5710];

	const WALLET_CUSTOMER_CLASS = '41';
	const WALLET_GIFT_VOUCHER_CLASS = '6';

	const SURVEY_MEMBERSHIP = TRUE;

	public static string $communityDomain;

	public static ?string $openMeteoKey = NULL;

	// Versions des CGV et leur date d'entrée en vigueur, publiées avec les CGU (UserSetting::$tos) : la version 1 est
	// servie sous `main/document/v1/cgv.pdf`, la version 2 sous `main/document/cgv.pdf` depuis le 02/10/2026.
	public static array $terms = [
		1 => '2026-06-12',
		2 => '2026-11-01',
	];

	// La RH entre dans les offres Gestion et Pilotage à cette date (21/10/2026). La page Fonctionnalités
	// l'annonce jusqu'à la veille : passé ce jour, l'annonce au futur deviendrait fausse.
	const HR_SUBSCRIPTION_FROM = '2026-11-01';

	const API_STRUCTURE_BETA = [
		7, 17, 2093, 6491,
	];

	/**
	 * Les fermes école, désignées par constante de déploiement et non découvertes par requête.
	 *
	 * ⚠️ Une structure `SCHOOL` absente de cette liste **n'existe pour personne** : ni les écrans
	 * qui la montrent, ni les droits qui l'ouvrent, ni le script qui la met à jour. C'est voulu —
	 * une ferme école se pose par l'équipe, pas par un geste du produit — mais il faut que ce soit
	 * vrai des deux côtés, sinon on fabrique une ferme copiée et invisible.
	 *
	 * Elle est lue depuis `canRead()` et `canWrite()`, donc des centaines de fois par page : une
	 * requête à cet endroit, fût-elle mémoïsée, n'a pas sa place dans un chemin de droits.
	 *
	 * Les identifiants sont les plus bas au-dessus de 1, **l'id 1 étant réservé à la démo**.
	 * Vérifié en production le 2026-09-04 : 2 à 6 y sont libres, comme en dev où la première
	 * structure porte l'id 7.
	 *
	 * ⚠️ La vérification est à refaire avant d'ajouter un identifiant à cette liste : un id déjà
	 * pris désignerait une ferme réelle et ouvrirait ses données à tout organisme de formation.
	 *
	 * Chaque entrée porte les deux bornes de la copie, et elles sont ici plutôt qu'en paramètre du
	 * script parce qu'elles décrivent la ferme et non l'exécution : un rejeu doit rendre le même
	 * contenu que le précédent, ce qu'un argument de ligne de commande ne garantit pas.
	 *
	 * - `salesUntil` : dernière date de livraison agrégée. Elle existe parce que les ventes d'une
	 *   ferme réelle continuent d'arriver, et qu'une ferme école dont la dernière semaine bouge
	 *   sans prévenir n'est pas un support d'enseignement stable ;
	 * - `source` : la ferme réelle recopiée. Elle est aussi posée sur `structure.source` au premier
	 *   passage, mais elle est déclarée ici pour que la création d'une ferme école ne demande rien
	 *   d'autre que cette entrée ;
	 * - `name` et `logo` : l'identité de la ferme école. Elle ne se copie pas — ce serait celle de
	 *   la ferme réelle — et elle ne se laisse pas au défaut, une ferme sans nom ni logo n'ayant
	 *   pas l'air d'une ferme. Le chemin du logo part de `Lime::getPath()`, soit `ouvretaferme/` ;
	 * - `seasonFirst` / `seasonLast` : l'intervalle de saisons copié. ⚠️ Il s'arrête à la dernière
	 *   saison qui porte vraiment quelque chose, et non à celle que la source déclare : sans
	 *   adhésion, `MemberLib::getDynamicSeason()` retombe sur `seasonLast`
	 *   (`structure/lib/Member.l.php:401`), si bien qu'un visiteur atterrissait sur une saison
	 *   vide. Mesuré le 2026-09-05 : 2027 ne portait que 3 séries et aucun rapport sur la ferme 7,
	 *   5 séries et aucune vente sur la 368. Ce que la borne coûte est donc ces quelques séries,
	 *   et ce qu'elle évite est un écran vide à l'ouverture ; Sept tables des paquets
	 *   copiés portent une colonne `season` — `series`, `seriesCultivation`, `seriesPlace`,
	 *   `seriesRepeat`, `seriesTask`, `mapDraw`, `plantForecast` —
	 *   et la borne s'applique à toutes ensemble, sinon une culture survivrait à sa série.
	 */
	const SCHOOL_STRUCTURES = [
		2 => [
			'source' => 7,
			'name' => "La ferme du petit marron",
			'logo' => 'main/static/image/bubble/courge-potimarron.png',
			'salesUntil' => '2026-08-31',
			'seasonFirst' => 2021,
			'seasonLast' => 2026,
		],
		3 => [
			'source' => 368,
			'name' => "La ferme du champ rouge",
			'logo' => 'main/static/image/bubble/betterave.png',
			'salesUntil' => '2026-12-31',
			'seasonFirst' => 2024,
			'seasonLast' => 2026,
		],
	];

	public static function getDatabaseName(\structure\Structure $eStructure, bool $isDemo = FALSE, bool $isBlank = FALSE): string {

		if(OTF_BLANK or $isBlank) {
			return (LIME_ENV === 'dev' ? 'dev_' : '').'blank_structure_'.$eStructure['id'];
		}

		if(OTF_DEMO or $isDemo) {
			return 'demo_structure_'.$eStructure['id'];
		}

		return (LIME_ENV === 'dev' ? 'dev_' : '').'structure_'.$eStructure['id'];
	}

	// L'export public retire l'application accounting de Lime::setApps().
	public static function hasAccountingApp(): bool {
		return in_array('accounting', \Lime::getApps(), TRUE);
	}

	// L'export public retire l'application commercialisation de Lime::setApps().
	public static function hasCommercialisationApp(): bool {
		return in_array('commercialisation', \Lime::getApps(), TRUE);
	}

	// L'export public retire le package analyze, qui croise les cultures et les ventes.
	public static function hasAnalyzePackage(): bool {
		return \Package::exists('analyze');
	}

	// L'export public retire le package subscription : le logiciel s'y installe sans abonnement.
	public static function hasSubscriptionPackage(): bool {
		return \Package::exists('subscription');
	}

	// L'export public retire le package presentation : le logiciel s'y installe sans site de présentation.
	public static function hasPresentationPackage(): bool {
		return \Package::exists('presentation');
	}

	// L'export public retire le package association : ni adhésion ni don, chaque structure a les avantages d'un adhérent.
	public static function hasAssociationPackage(): bool {
		return \Package::exists('association');
	}

	// L'export public retire le package admin : ni statistiques du parc ni instantané des structures comptables.
	public static function hasAdminPackage(): bool {
		return \Package::exists('admin');
	}

	// L'export public retire le package mail : les e-mails partent sans être enregistrés, ni contacts ni suivi de remise.
	public static function hasMailPackage(): bool {
		return \Package::exists('mail');
	}

	public static function getStartPackages(): array {

		$packages = ['securing', 'invoicing', 'notification', 'pdp', 'thirdParty', 'help', 'external', 'projection', 'loan'];

		if(self::hasAccountingApp() === FALSE) {
			$packages = array_values(array_diff($packages, ['invoicing', 'pdp', 'thirdParty', 'help', 'projection', 'loan']));
		}

		if(self::hasCommercialisationApp() === FALSE) {
			$packages = array_values(array_diff($packages, ['external']));
		}

		return $packages;

	}

	public static function getAccountingPackages(): array {

		if(self::hasAccountingApp()) {
			return ['account', 'asset', 'bank', 'financialYear', 'journal', 'overview', 'preaccounting', 'proof', 'taxReturn', 'vat', 'cash', 'edi'];
		} else {
			return [];
		}

	}

	public static function getPackages(): array {
		return array_merge(self::getStartPackages(), self::getAccountingPackages());
	}

}

StructureSetting::$communityDomain = 'reseau.'.\Lime::getDomain();

?>
