<?php
namespace structure;

/**
 * Le décor des tests : vingt et une structures posées sur les identifiants de
 * `StructureSetting::FIXTURE_STRUCTURES`, et les dix-sept comptes qui les font vivre.
 *
 * Le décor est **toute ligne qui pointe un identifiant de `FIXTURE_STRUCTURES`, plus les comptes des
 * courriels de `USERS`**. Les plantes, produits, unités, moyens de paiement, présences et CGU que
 * `StructureLib::create()` sème à la création en font partie au même titre que ce qui est déclaré
 * ici : ils ne se déclarent pas parce qu'ils se déduisent de la création.
 *
 * Il existe en production comme en dev, invisible du produit par la constante (lot 6.2a), et il ne
 * change que par ce fichier : `check()` compare la base à `DECOR` et nomme chaque écart, `apply()`
 * amène la base à `DECOR` depuis n'importe quel état et se rejoue sans rien écrire quand tout est en
 * place. Le script `structure/configure/fixtureAi` porte les deux.
 *
 * Tout passe par les Lib, comme depuis l'écran : le décor doit se comporter comme une structure
 * réelle, et une écriture directe poserait un état que le produit ne sait pas fabriquer. Ce que
 * l'utilisateur saisit (comptes, structures, nom, consentement, visibilité de la fiche, lieu,
 * exercices, données légales, tiers, comptes du plan, pièces) passe aussi par `build()` ;
 * le reste est posé par les Lib qui le posent depuis l'écran, qui ne construisent rien :
 * `ConfigurationLib::updateProperty()`, `CommunityLib::update()`, `MemberLib`, `FeatureLib`,
 * `AffiliationAiLib`, et `StructureLib::close()` pour une structure fermée.
 *
 * Une seule écriture n'a pas de geste du produit derrière elle : le passage d'une ferme en ferme
 * projet (`applyProject()`), qu'aucun écran ne fait.
 *
 * Ce qu'`apply()` ne redresse pas, faute de geste du produit pour le faire : le type d'une structure
 * hors du passage en ferme projet, sa catégorie, son pays une fois vérifié, des données légales sur
 * une structure qui n'en déclare pas, une structure fermée à rouvrir, une comptabilité ou un
 * exercice en trop, une pièce ou un compte du plan qui s'écarte de sa déclaration, un compte qui a
 * reçu un moyen de connexion. `check()` les nomme, et le script refuse de conclure tant qu'ils
 * restent.
 *
 * Ajouter une structure au décor : `base/structure/CLAUDE.md`.
 */
class FixtureAiLib {

	use \Notifiable;

	/**
	 * Les comptes, par courriel. Aucun ne porte de `UserAuth` : en production ils n'ont aucun moyen
	 * de connexion, et un mot de passe connu du dépôt y serait une porte. Le domaine `example.org`
	 * est réservé, rien n'y est jamais délivré.
	 */
	const USERS = [
		'decor.f1.admin@example.org' => ['firstName' => 'Alice', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.f1.planning@example.org' => ['firstName' => 'Bruno', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.f1.comptable@example.org' => ['firstName' => 'Claire', 'lastName' => 'Décor', 'role' => 'roleConsultant'],
		'decor.f1.observateur@example.org' => ['firstName' => 'David', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.f2.admin@example.org' => ['firstName' => 'Émile', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.g3.conseiller@example.org' => ['firstName' => 'Fanny', 'lastName' => 'Décor', 'role' => 'roleConsultant'],
		'decor.g2.enseignant@example.org' => ['firstName' => 'Gaspard', 'lastName' => 'Décor', 'role' => 'roleTeacher'],
		'decor.g2.eleve@example.org' => ['firstName' => 'Hélène', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.a1.referent@example.org' => ['firstName' => 'Inès', 'lastName' => 'Décor', 'role' => 'roleCustomer'],
		'decor.sans.structure@example.org' => ['firstName' => 'Jules', 'lastName' => 'Décor', 'role' => 'roleCustomer'],
		'decor.f4.admin@example.org' => ['firstName' => 'Karim', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.f4.membre@example.org' => ['firstName' => 'Léa', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.f4.sorti@example.org' => ['firstName' => 'Marc', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.f5.admin@example.org' => ['firstName' => 'Nadia', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.c.admin@example.org' => ['firstName' => 'Olivier', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.c.lecteur@example.org' => ['firstName' => 'Pauline', 'lastName' => 'Décor', 'role' => 'roleProducer'],
		'decor.g7.admin@example.org' => ['firstName' => 'Quentin', 'lastName' => 'Décor', 'role' => 'roleProducer'],
	];

	/**
	 * Le compte connecté pendant `apply()`. Plusieurs créations lisent l'auteur sur la session et le
	 * refusent vide (la boutique qu'une AMAP reçoit à sa création porte un `createdBy` obligatoire) :
	 * le décor se monte comme depuis l'écran, par quelqu'un.
	 */
	const AUTHOR = 'decor.f1.admin@example.org';

	/**
	 * Les rôles sans `fqn`, que `RoleLib::createForStructure()` pose sur une ferme et qui ne se
	 * distinguent que par leurs droits : leur nom est traduit, il ne se compare pas.
	 */
	const ROLE_PLANNING = 'planning';
	const ROLE_ACCOUNTANT = 'accountant';
	const ROLE_OBSERVER = 'observer';

	/**
	 * Les propriétés que porte une entrée `legal` de `DECOR`, celles de l'écran des données légales
	 * (`StructureLib::getPropertiesLegal()`). Le SIRET n'est demandé qu'en France et vaut NULL ailleurs :
	 * ceux du décor ont une clé de Luhn juste, parce que la liasse contrôle la clé, et un SIREN que
	 * l'annuaire des entreprises ignore, parce que le décor monte en production et n'y porte
	 * l'identité de personne. L'annuaire ne se lit pas depuis un test : un SIRET ajouté s'y vérifie à la main.
	 * `FixtureSentinelAi` tient chaque entrée face à cette liste.
	 */
	const LEGAL = ['legalName', 'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'legalCategory', 'legalCountry', 'siret'];

	/**
	 * Les identifiants du décor, sous le code de leur entrée dans `DECOR`. Un test cite une structure
	 * du décor par ces constantes et jamais par son identifiant : le code dit ce qu'il lit, et
	 * `StructureSweepSentinelAi` refuse une constante `STRUCTURE_*` de test écrite en littéral.
	 * `FixtureSentinelAi` tient chaque constante face à son entrée.
	 */
	const F1 = 4;
	const F2 = 5;
	const G3 = 6;
	const F3 = 8;
	const B1 = 22;
	const A1 = 50;
	const G1 = 54;
	const G2 = 84;
	const F4 = 87;
	const F5 = 122;
	const F6 = 164;
	const F7 = 170;
	const F8 = 173;
	const G4 = 177;
	const G5 = 208;
	const G6 = 229;
	const P1 = 265;
	const F9 = 269;
	const F10 = 283;
	const F11 = 292;
	const G7 = 315;

	/**
	 * Une entrée par identifiant réservé.
	 *
	 * - `legalEmail` est l'identité de la structure : `apply()` refuse d'écrire sur un identifiant
	 *   occupé par une structure qui ne la porte pas, qui serait alors une structure réelle ;
	 * - `owner` reçoit l'administration à la création, par `StructureLib::create()`. Une structure dont
	 *   aucun membre n'est administrateur naît sans lui, `owner` étant facultatif ;
	 * - `members` liste tous les membres présents, et un membre présent hors de la liste est un écart ;
	 * - `ghosts` : les comptes que l'équipe garde en fantômes, rôle `ghost` et statut `OUT`, comme les
	 *   pose `MemberLib::createGhostUser()`. C'est la seule ligne sortie que le produit fabrique : le
	 *   retrait d'un membre efface sa ligne ;
	 * - `affiliations` : groupe => la ferme partage-t-elle sa production. Le compte qui rattache est
	 *   l'administrateur de la ferme, qui doit être membre du groupe (`AffiliationAiLib::create()`) ;
	 * - `asks` : ce que le groupe demande à lire de ses fermes. Sans demande, une ferme rattachée ne
	 *   partage rien, quel que soit son choix ;
	 * - `features` : les fonctionnalités vérifiées. `hasEnableBanking` y est à FALSE partout, et
	 *   `hasPdp` partout sauf sur F3, parce que les crons d'e-facturation et de banque visitent toute
	 *   structure qui les porte, décor compris. F3 tient le rôle de la structure sur la PA sans
	 *   comptabilité, que demandent les tests de la file (`pdp/test/QueueAi.t.php`,
	 *   `invoicing/test/QueueAi.t.php`) ; elle n'a aucun partenaire, et ces crons sautent une
	 *   structure sans partenaire valide ;
	 * - `legal` : les données légales saisies sur l'écran de la structure, ou NULL pour les laisser
	 *   vides. Le pays y figure parce que l'écran le demande avec elles, et qu'il ne se change plus une
	 *   fois vérifié ;
	 * - `financialYears` : les exercices de la base `structure_<id>`, triés par date de début, parce
	 *   que le relevé les lit dans cet ordre. `status` dit l'exercice clos par le geste de clôture, et
	 *   `opened` l'à-nouveau posé par le geste d'ouverture, date et drapeau, que le livre des recettes n'a pas.
	 *   `accounts` : les comptes que l'exercice ajoute à son plan, avec le tiers qu'ils portent, par son
	 *   nom. `operations` : les pièces saisies sur l'exercice, chacune reconnue à son numéro de pièce
	 *   (`document`), ses lignes en [sens, compte, montant]. Les comptes se posent avant l'à-nouveau, qui
	 *   les reprend, et les pièces entre l'à-nouveau et la clôture, qui les fige ;
	 * - `database` : la base `structure_<id>`, que `StructureLib::create()` crée pour tout type ;
	 * - `place` : le lieu de la structure, nom et point en [longitude, latitude], ou NULL pour n'en
	 *   porter aucun. Il se pose par le geste de l'écran du lieu (`system.p.php`, `doUpdatePlace`) ;
	 * - `profileVisibility` : qui lit la fiche publique ;
	 * - `parent` : le groupe propriétaire d'une ferme projet, NULL pour toute autre structure ;
	 * - `status` : une structure `CLOSED` est fermée après ses rattachements, qu'une structure
	 *   fermée ne peut plus recevoir.
	 *
	 * F6 à F11, G4 à G6 et P1 sont le décor des groupes (`test/CommunityFixtureAi.t.php`) : trois
	 * groupes de producteurs, dont un qui ne demande rien, et les fermes que deux comptes y apportent.
	 * F6 et F7 sont à 53,6 km l'une de l'autre, la distance qu'éprouve `ProfileAi`.
	 *
	 * G7 tient le rôle du réseau des tutoriels de Ouvretaferme (`learning/test/LearningTutorialFixture.t.php`).
	 * Il est distinct de G3, que se partagent les tests de l'ouverture aux membres : ce qu'un tutoriel
	 * ouvre à tout visiteur ne se mêle pas à ce qu'un réseau ouvre à ses fermes. Son administrateur
	 * porte la clé d'API qui écrit les tutoriels.
	 *
	 * F7 et F8 tiennent aussi la comptabilité, sans aucun document : `financialYear/test/QueueAi.t.php`
	 * génère les siens sur leur premier exercice et exige qu'aucun ne l'y attende. Leurs exercices
	 * restent ouverts, sans à-nouveau ni clôture, qui en poseraient.
	 */
	const DECOR = [

		self::F1 => [
			'code' => 'F1',
			'name' => 'Ferme des Quatre Saisons',
			'legalEmail' => 'decor.f1@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => TRUE,
			'hasVat' => TRUE,
			'legal' => [
				'legalName' => 'EARL des Quatre Saisons',
				'legalStreet1' => '1 rue du Décor',
				'legalStreet2' => NULL,
				'legalPostcode' => '63450',
				'legalCity' => 'Saint-Amant-Tallende',
				'legalCategory' => 6598,
				'legalCountry' => 75,
				'siret' => '00000003400017',
			],
			'owner' => 'decor.f1.admin@example.org',
			'members' => [
				'decor.f1.admin@example.org' => 'admin',
				'decor.f1.planning@example.org' => self::ROLE_PLANNING,
				'decor.f1.comptable@example.org' => self::ROLE_ACCOUNTANT,
				'decor.f1.observateur@example.org' => self::ROLE_OBSERVER,
			],
			'ghosts' => [],
			'affiliations' => [self::G1 => TRUE, self::G3 => TRUE],
			'asks' => FALSE,
			'features' => [
				'hasShops' => TRUE, 'hasSales' => TRUE, 'hasInvoices' => TRUE, 'hasPurchases' => TRUE,
				'hasCultivations' => TRUE, 'hasStock' => TRUE, 'hasWallet' => TRUE,
				'hasAccounting' => TRUE, 'hasFinancialYears' => TRUE,
				'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [
				[
					'startDate' => '2024-01-01', 'endDate' => '2024-12-31', 'status' => \financialYear\FinancialYear::CLOSE,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => TRUE,
					'accounts' => [],
					'operations' => [],
				],
				[
					'startDate' => '2025-01-01', 'endDate' => '2025-12-31', 'status' => \financialYear\FinancialYear::CLOSE,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => TRUE,
					'accounts' => [],
					'operations' => [],
				],
				[
					'startDate' => '2026-01-01', 'endDate' => '2026-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => TRUE,
					'accounts' => [
						['class' => \account\AccountSetting::VAT_SELL_PENDING_CLASS, 'description' => 'TVA collectée en attente d\'encaissement', 'thirdParty' => NULL],
						['class' => \account\AccountSetting::VAT_BUY_PENDING_CLASS, 'description' => 'TVA déductible en attente de paiement', 'thirdParty' => NULL],
					],
					'operations' => [],
				],
				// Créé, sans à-nouveau : l'ouverture exige le précédent clos, et la clôture refuse 2026 avant
				// sa date de fin. Son plan est la copie de celui de 2026, comptes de TVA en attente compris.
				[
					'startDate' => '2027-01-01', 'endDate' => '2027-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => FALSE,
					'accounts' => [
						['class' => \account\AccountSetting::VAT_SELL_PENDING_CLASS, 'description' => 'TVA collectée en attente d\'encaissement', 'thirdParty' => NULL],
						['class' => \account\AccountSetting::VAT_BUY_PENDING_CLASS, 'description' => 'TVA déductible en attente de paiement', 'thirdParty' => NULL],
					],
					'operations' => [],
				],
			],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F2 => [
			'code' => 'F2',
			'name' => 'Le Potager du Marché',
			'legalEmail' => 'decor.f2@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => FALSE,
			'hasVat' => FALSE,
			'legal' => NULL,
			'owner' => 'decor.f2.admin@example.org',
			'members' => [
				'decor.f2.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [self::G1 => FALSE, self::G3 => FALSE],
			'asks' => FALSE,
			'features' => [
				'hasSales' => TRUE, 'hasInvoices' => TRUE,
				'hasAccounting' => TRUE, 'hasFinancialYears' => TRUE,
				'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [
				[
					'startDate' => '2026-01-01', 'endDate' => '2026-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::MICRO_BA,
					'accountingMode' => \financialYear\FinancialYear::CASH_RECEIPTS,
					'accountingType' => \financialYear\FinancialYear::CASH,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 1000,
					'opened' => FALSE,
					'accounts' => [],
					'operations' => [],
				],
			],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G3 => [
			'code' => 'G3',
			'name' => 'Réseau Terres Voisines',
			'legalEmail' => 'decor.g3@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::NETWORK,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => NULL,
			'members' => [
				'decor.g3.conseiller@example.org' => 'advisor',
				'decor.f1.admin@example.org' => 'member',
				'decor.f2.admin@example.org' => 'member',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => TRUE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F3 => [
			'code' => 'F3',
			'name' => 'Les Jeunes Pousses',
			'legalEmail' => 'decor.f3@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => NULL,
			'members' => [],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => TRUE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::B1 => [
			'code' => 'B1',
			'name' => 'Le Panier des Producteurs',
			'legalEmail' => 'decor.b1@example.org',
			'type' => Structure::SHOP,
			'producerType' => NULL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => TRUE,
			'legal' => NULL,
			'owner' => NULL,
			'members' => [],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::A1 => [
			'code' => 'A1',
			'name' => 'AMAP du Pré Commun',
			'legalEmail' => 'decor.a1@example.org',
			'type' => Structure::AMAP,
			'producerType' => NULL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => FALSE,
			'legal' => NULL,
			'owner' => NULL,
			'members' => [
				'decor.a1.referent@example.org' => 'referent',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G1 => [
			'code' => 'G1',
			'name' => 'Les Maraîchers Associés',
			'legalEmail' => 'decor.g1@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::PRODUCER,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => NULL,
			'members' => [
				'decor.f1.admin@example.org' => 'member',
				'decor.f2.admin@example.org' => 'member',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => TRUE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G2 => [
			'code' => 'G2',
			'name' => 'Lycée agricole des Coteaux',
			'legalEmail' => 'decor.g2@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::EDUCATION,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.g2.enseignant@example.org',
			'members' => [
				'decor.g2.enseignant@example.org' => 'admin',
				'decor.g2.eleve@example.org' => 'student',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F4 => [
			'code' => 'F4',
			'name' => 'La Ferme du Haut Pré',
			'legalEmail' => 'decor.f4@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			// Hors de France, sans SIRET : la France seule l'exige, et l'écran le vide ailleurs.
			'legal' => [
				'legalName' => 'Ferme du Haut Pré',
				'legalStreet1' => '1 rue du Décor',
				'legalStreet2' => NULL,
				'legalPostcode' => '9999',
				'legalCity' => 'Décorville',
				'legalCategory' => 6598,
				'legalCountry' => 20,
				'siret' => NULL,
			],
			'owner' => 'decor.f4.admin@example.org',
			'members' => [
				'decor.f4.admin@example.org' => 'admin',
				'decor.f4.membre@example.org' => self::ROLE_PLANNING,
			],
			'ghosts' => ['decor.f4.sorti@example.org'],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F5 => [
			'code' => 'F5',
			'name' => 'Les Vergers du Val',
			'legalEmail' => 'decor.f5@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => FALSE,
			'legal' => [
				'legalName' => 'EARL des Vergers du Val',
				'legalStreet1' => '2 rue du Décor',
				'legalStreet2' => NULL,
				'legalPostcode' => '63450',
				'legalCity' => 'Saint-Amant-Tallende',
				'legalCategory' => 6598,
				'legalCountry' => 75,
				'siret' => '00000004200010',
			],
			'owner' => 'decor.f5.admin@example.org',
			'members' => [
				'decor.f5.admin@example.org' => 'admin',
				'decor.f4.admin@example.org' => self::ROLE_PLANNING,
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => TRUE, 'hasFinancialYears' => TRUE,
				'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			// Un exercice écrit puis clos, et le suivant ouvert sur son à-nouveau. Le résultat de 2025,
			// 3 000 €, reste au 120 de 2026 : une société ne l'affecte qu'à la décision de l'assemblée,
			// que le décor ne prend pas, pour que l'affectation reste un geste à éprouver.
			'financialYears' => [
				[
					'startDate' => '2025-01-01', 'endDate' => '2025-12-31', 'status' => \financialYear\FinancialYear::CLOSE,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::CASH,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => TRUE,
					'accounts' => [
						['class' => \account\AccountSetting::ASSOCIATE_ACCOUNT_PRINCIPAL_CLASS.'1', 'description' => 'Compte courant de l\'associé du décor', 'thirdParty' => 'Associé du décor'],
					],
					'operations' => [
						['document' => 'DECOR-2025-1', 'date' => '2025-01-02', 'description' => 'Apport en capital', 'lines' => [
							[\journal\Operation::DEBIT, '512', 20000.0], [\journal\Operation::CREDIT, '101', 20000.0],
						]],
						['document' => 'DECOR-2025-2', 'date' => '2025-03-01', 'description' => 'Acquisition d\'un tracteur', 'lines' => [
							[\journal\Operation::DEBIT, '2154', 12000.0], [\journal\Operation::CREDIT, '512', 12000.0],
						]],
						['document' => 'DECOR-2025-3', 'date' => '2025-04-01', 'description' => 'Apport en compte courant d\'associé', 'lines' => [
							[\journal\Operation::DEBIT, '512', 3000.0], [\journal\Operation::CREDIT, \account\AccountSetting::ASSOCIATE_ACCOUNT_PRINCIPAL_CLASS.'1', 3000.0],
						]],
						['document' => 'DECOR-2025-4', 'date' => '2025-05-01', 'description' => 'Achat de semences', 'lines' => [
							[\journal\Operation::DEBIT, '60112', 4000.0], [\journal\Operation::CREDIT, '512', 4000.0],
						]],
						['document' => 'DECOR-2025-5', 'date' => '2025-09-15', 'description' => 'Ventes', 'lines' => [
							[\journal\Operation::DEBIT, '512', 9000.0], [\journal\Operation::CREDIT, '7011', 9000.0],
						]],
						['document' => 'DECOR-2025-6', 'date' => '2025-12-31', 'description' => 'Dotation aux amortissements', 'lines' => [
							[\journal\Operation::DEBIT, '6811', 2000.0], [\journal\Operation::CREDIT, '28154', 2000.0],
						]],
					],
				],
				[
					'startDate' => '2026-01-01', 'endDate' => '2026-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::CASH,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => TRUE,
					// Le plan d'un exercice se copie de celui du précédent à sa création, qui précède ici les
					// comptes de 2025 : l'à-nouveau du compte courant demande le compte dans ce plan aussi.
					'accounts' => [
						['class' => \account\AccountSetting::ASSOCIATE_ACCOUNT_PRINCIPAL_CLASS.'1', 'description' => 'Compte courant de l\'associé du décor', 'thirdParty' => 'Associé du décor'],
					],
					'operations' => [],
				],
				// Créé, sans à-nouveau, comme celui de F1 : le résultat de 2025 reste au 120 de 2026.
				[
					'startDate' => '2027-01-01', 'endDate' => '2027-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::CASH,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => FALSE,
					'accounts' => [
						['class' => \account\AccountSetting::ASSOCIATE_ACCOUNT_PRINCIPAL_CLASS.'1', 'description' => 'Compte courant de l\'associé du décor', 'thirdParty' => 'Associé du décor'],
					],
					'operations' => [],
				],
			],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G4 => [
			'code' => 'G4',
			'name' => 'Les Producteurs des Volcans',
			'legalEmail' => 'decor.g4@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::PRODUCER,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
				'decor.c.lecteur@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => TRUE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G5 => [
			'code' => 'G5',
			'name' => 'Le Cercle des Semences',
			'legalEmail' => 'decor.g5@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::PRODUCER,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G6 => [
			'code' => 'G6',
			'name' => 'Le Réseau des Monts Dore',
			'legalEmail' => 'decor.g6@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::PRODUCER,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
				'decor.c.lecteur@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => TRUE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::G7 => [
			'code' => 'G7',
			'name' => 'Tutoriels du décor',
			'legalEmail' => 'decor.g7@example.org',
			'type' => Structure::COMMUNITY,
			'producerType' => NULL,
			'communityType' => Structure::NETWORK,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.g7.admin@example.org',
			'members' => [
				'decor.g7.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE, 'hasCommunityLearning' => TRUE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F6 => [
			'code' => 'F6',
			'name' => 'Les Jardins du Puy',
			'legalEmail' => 'decor.f6@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [self::G4 => TRUE, self::G6 => TRUE],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => ['place' => 'Tallende', 'placeLngLat' => [3.13, 45.67], 'placeCity' => '63425', 'placeDepartment' => '63', 'placeEpci' => '200069177'],
			'profileVisibility' => Structure::MEMBERS,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F7 => [
			'code' => 'F7',
			'name' => 'Les Champs de Beaulieu',
			'legalEmail' => 'decor.f7@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => FALSE,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [self::G4 => TRUE],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => TRUE, 'hasFinancialYears' => TRUE,
				'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [
				[
					'startDate' => '2026-01-01', 'endDate' => '2026-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => FALSE,
					'accounts' => [],
					'operations' => [],
				],
				[
					'startDate' => '2027-01-01', 'endDate' => '2027-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => FALSE,
					'accounts' => [],
					'operations' => [],
				],
			],
			'database' => TRUE,
			'place' => ['place' => 'Beaulieu', 'placeLngLat' => [2.51, 45.46], 'placeCity' => '15020', 'placeDepartment' => '15', 'placeEpci' => '241501055'],
			'profileVisibility' => Structure::MEMBERS,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F8 => [
			'code' => 'F8',
			'name' => 'Le Clos des Monts',
			'legalEmail' => 'decor.f8@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => FALSE,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [self::G5 => FALSE],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => TRUE, 'hasFinancialYears' => TRUE,
				'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [
				[
					'startDate' => '2026-01-01', 'endDate' => '2026-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => FALSE,
					'accounts' => [],
					'operations' => [],
				],
				[
					'startDate' => '2027-01-01', 'endDate' => '2027-12-31', 'status' => \financialYear\FinancialYear::OPEN,
					'taxSystem' => \financialYear\FinancialYear::BA_REEL_SIMPLIFIE,
					'accountingMode' => \financialYear\FinancialYear::ACCOUNTING,
					'accountingType' => \financialYear\FinancialYear::ACCRUAL,
					'plan' => \financialYear\FinancialYear::AGRICULTURAL,
					'legalCategory' => 6598,
					'opened' => FALSE,
					'accounts' => [],
					'operations' => [],
				],
			],
			'database' => TRUE,
			'place' => ['place' => 'Saint-Amant-Tallende', 'placeLngLat' => [3.11, 45.67], 'placeCity' => '63315', 'placeDepartment' => '63', 'placeEpci' => '200069177'],
			'profileVisibility' => Structure::MEMBERS,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::P1 => [
			'code' => 'P1',
			'name' => 'Le Jardin commun des Volcans',
			'legalEmail' => 'decor.p1@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::PROJECT,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => self::G4,
			'status' => Structure::ACTIVE,
		],

		self::F9 => [
			'code' => 'F9',
			'name' => 'La Ferme de la Sioule',
			'legalEmail' => 'decor.f9@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [self::G4 => FALSE],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

		self::F10 => [
			'code' => 'F10',
			'name' => 'La Ferme des Anciens Prés',
			'legalEmail' => 'decor.f10@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.admin@example.org',
			'members' => [
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			'affiliations' => [self::G4 => TRUE],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::CLOSED,
		],

		self::F11 => [
			'code' => 'F11',
			'name' => 'Les Jardins du Bec',
			'legalEmail' => 'decor.f11@example.org',
			'type' => Structure::PRODUCER,
			'producerType' => Structure::REAL,
			'communityType' => NULL,
			'consentData' => NULL,
			'hasVat' => NULL,
			'legal' => NULL,
			'owner' => 'decor.c.lecteur@example.org',
			'members' => [
				'decor.c.lecteur@example.org' => 'admin',
				'decor.c.admin@example.org' => 'admin',
			],
			'ghosts' => [],
			// Rattachée par le lecteur, que F6 n'a pas : c'est le couple que reprend un second associé.
			'affiliations' => [self::G4 => TRUE],
			'asks' => FALSE,
			'features' => [
				'hasAccounting' => FALSE, 'hasPdp' => FALSE, 'hasEnableBanking' => FALSE,
			],
			'financialYears' => [],
			'database' => TRUE,
			'place' => NULL,
			'profileVisibility' => Structure::NONE,
			'parent' => NULL,
			'status' => Structure::ACTIVE,
		],

	];

	/** L'ordre de création : les groupes avant les fermes qui s'y rattachent. */
	const ORDER = [
		self::G1, self::G2, self::G3, self::G4, self::G5, self::G6, self::G7,
		self::F1, self::F2, self::F3, self::F4, self::F5, self::F6, self::F7, self::F8, self::P1, self::F9, self::F10, self::F11,
		self::B1, self::A1,
	];

	/** Ce que la création demande en plus du nom et du type, identique pour toutes. */
	const INPUT = [
		'isLegal' => 0,
		// L'identifiant du pays et non son code : la colonne est un `?user\Country`.
		'legalCountry' => 75,
		// Une année fixe : une date lue à l'horloge ferait dériver le décor d'une année à l'autre.
		'startedAt' => 2020,
		'organic' => Structure::NO,
		'qualities' => [],
	];

	private static int $writes = 0;

	/**
	 * La garde de toute écriture : un identifiant hors de la constante désigne une structure réelle,
	 * en production comme dans une copie de production.
	 */
	public static function assertFixture(int $structure): void {

		if(in_array($structure, StructureSetting::FIXTURE_STRUCTURES, TRUE) === FALSE) {
			throw new \Exception('La structure '.$structure.' n\'est pas réservée au décor, rien n\'est écrit.');
		}

	}

	/**
	 * La connexion à une base `structure_<id>` passe par la garde : c'est elle qui décide de la base
	 * où `FinancialYearLib::create()` écrit, puisqu'il lit la structure connectée.
	 */
	public static function connect(Structure $eStructure, bool $migrate): void {

		self::assertFixture($eStructure['id']);

		StructureLib::connectDatabase($eStructure, $migrate);

	}

	/**
	 * Les écarts entre la base et le décor, nommés un par un. Un tableau vide veut dire que le décor
	 * est en place.
	 *
	 * Les comptes sont toujours relevés ; les structures peuvent se borner à celles qu'un lecteur
	 * emprunte, pour qu'un écart ailleurs dans le décor ne fasse pas échouer ce qui ne le lit pas.
	 *
	 * @param int[] $structures
	 * @return string[]
	 */
	public static function check(array $structures = self::ORDER): array {

		return StructureLib::keepConnection(function() use($structures) {

			$gaps = [];

			$cUser = self::getUsers();

			foreach(self::USERS as $email => $user) {

				if($cUser->offsetExists($email) === FALSE) {
					$gaps[] = 'le compte '.$email.' n\'existe pas';
				} else if(\user\UserAuth::model()->whereUser($cUser[$email])->exists()) {
					$gaps[] = 'le compte '.$email.' porte un moyen de connexion';
				}

			}

			$cStructure = self::getStructures();

			foreach($structures as $id) {

				$decor = self::DECOR[$id];
				$label = 'la structure '.$id.' ('.$decor['code'].')';

				if($cStructure->offsetExists($id) === FALSE) {
					$gaps[] = $label.' n\'existe pas';
					continue;
				}

				array_push($gaps, ...self::checkStructure($label, $cStructure[$id], $decor));

			}

			return $gaps;

		});

	}

	/**
	 * Amène la base au décor et rend le nombre d'écritures faites, 0 quand tout est déjà en place.
	 *
	 * ⚠️ Hors de toute transaction : `StructureLib::create()` émet un `CREATE DATABASE`, qui
	 * validerait la transaction ouverte. Rejouée dans une transaction sur un décor en place, elle ne
	 * crée rien et n'émet donc aucun DDL.
	 */
	public static function apply(): int {

		foreach(array_keys(self::DECOR) as $id) {
			self::assertFixture($id);
		}

		self::assertOccupants();

		self::$writes = 0;

		StructureLib::keepConnection(function() {

			$cUser = self::applyUsers();

			\user\ConnectionLib::setOnline($cUser[self::AUTHOR]);

			foreach(self::ORDER as $id) {
				self::applyStructure($id, self::DECOR[$id], $cUser);
			}

			$cStructure = self::getStructures();

			foreach(self::ORDER as $id) {

				$eStructure = $cStructure[$id];
				$decor = self::DECOR[$id];

				self::applyProfile($eStructure, $decor);
				self::applyMembers($eStructure, $decor, $cUser);
				self::applyAccounting($eStructure, $decor);
				self::applyFeatures($eStructure, $decor);

			}

			// Après tous les membres : le compte qui rattache une ferme doit déjà être membre du groupe.
			foreach(self::ORDER as $id) {
				self::applyAffiliations($cStructure[$id], self::DECOR[$id], $cUser, $cStructure);
			}

			foreach(self::ORDER as $id) {
				self::applyStatus($cStructure[$id], self::DECOR[$id]);
			}

		});

		return self::$writes;

	}

	/**
	 * Refuse avant toute écriture ce que le décor adopterait sans l'avoir créé. En production, c'est
	 * ce qui le sépare de données réelles :
	 * - une structure réservée qui n'est pas celle du décor, reconnue à son `legalEmail` ;
	 * - une base `structure_<id>` sans sa structure, où `createDatabase()`, qui n'échoue pas sur une
	 *   base existante, poserait le décor sur les tables d'un autre ;
	 * - un compte déclaré qui a un moyen de connexion, que le décor ferait membre de ses structures.
	 */
	public static function assertOccupants(): void {

		$cStructure = self::getStructures();

		foreach($cStructure as $id => $eStructure) {

			if($eStructure['legalEmail'] !== self::DECOR[$id]['legalEmail']) {
				throw new \Exception('La structure '.$id.' existe sans être celle du décor ('.$eStructure['legalEmail'].' au lieu de '.self::DECOR[$id]['legalEmail'].'), rien n\'est écrit.');
			}

		}

		$orphans = array_filter(
			array_keys(self::DECOR),
			fn(int $id) => $cStructure->offsetExists($id) === FALSE
		);

		if($orphans !== []) {

			$databases = \data\CleanTestStructureAiLib::getDatabases($orphans);

			if($databases !== []) {
				throw new \Exception('Des bases de structure existent sans leur structure ('.implode(', ', $databases).'), rien n\'est écrit.');
			}

		}

		foreach(self::getUsers() as $email => $eUser) {

			if(\user\UserAuth::model()->whereUser($eUser)->exists()) {
				throw new \Exception('Le compte '.$email.' a un moyen de connexion, rien n\'est écrit.');
			}

		}

	}

	private static function write(): void {
		self::$writes++;
	}

	/**
	 * @return \Collection<\user\User> Indexée par courriel
	 */
	private static function getUsers(): \Collection {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('IN', array_keys(self::USERS))
			->getCollection(index: 'email');

	}

	private static function getStructures(): \Collection {

		return Structure::model()
			->select(Structure::getSelection())
			->whereId('IN', array_keys(self::DECOR))
			->getCollection(index: 'id');

	}

	private static function getMembers(Structure $eStructure): \Collection {

		return Member::model()
			->select([
				'id', 'status', 'structure',
				'user' => ['email'],
				'role' => Role::getSelection(),
			])
			->whereStructure($eStructure)
			->getCollection();

	}

	/**
	 * Le rôle que désigne une clé de `DECOR` : son `fqn` quand il en a un, ses droits sinon.
	 */
	private static function getRole(Structure $eStructure, string $key): Role {

		$matcher = match($key) {
			self::ROLE_PLANNING => fn(Role $eRole) => ($eRole['admin'] === FALSE and $eRole['planning'] === Role::LIMITED),
			self::ROLE_ACCOUNTANT => fn(Role $eRole) => ($eRole['admin'] === FALSE and $eRole['accounting'] === Role::FULL),
			self::ROLE_OBSERVER => fn(Role $eRole) => ($eRole['admin'] === FALSE and $eRole['productionAnalyze'] === TRUE and $eRole['planning'] === Role::DENIED),
			default => fn(Role $eRole) => $eRole['fqn'] === $key,
		};

		return Role::model()
			->select(Role::getSelection())
			->whereStructure($eStructure)
			->sort(['id' => SORT_ASC])
			->getCollection()
			->find($matcher)
			->first() ?? new Role();

	}

	/**
	 * @return string[]
	 */
	private static function checkStructure(string $label, Structure $eStructure, array $decor): array {

		$gaps = [];

		foreach(['name', 'legalEmail', 'type', 'producerType', 'communityType', 'consentData', 'profileVisibility', 'status'] as $property) {

			if($eStructure[$property] !== $decor[$property]) {
				$gaps[] = $label.' porte '.$property.' = '.var_export($eStructure[$property], TRUE).', attendu '.var_export($decor[$property], TRUE);
			}

		}

		foreach(self::getLegalGaps($eStructure, $decor) as $property => $expected) {
			$gaps[] = $label.' porte '.$property.' = '.var_export(self::getLegalValue($eStructure, $property), TRUE).', attendu '.var_export($expected, TRUE);
		}

		$parent = self::getParent($eStructure);

		if($parent !== $decor['parent']) {
			$gaps[] = $label.' porte parent = '.var_export($parent, TRUE).', attendu '.var_export($decor['parent'], TRUE);
		}

		$place = self::getPlace(SystemLib::getByStructure($eStructure));

		if($place !== $decor['place']) {
			$gaps[] = $label.' porte le lieu '.json_encode($place, JSON_UNESCAPED_UNICODE).', attendu '.json_encode($decor['place'], JSON_UNESCAPED_UNICODE);
		}

		if($eStructure['membership'] === TRUE) {
			$gaps[] = $label.' est adhérente';
		}

		$hasVat = Configuration::model()
			->whereStructure($eStructure)
			->getValue('hasVat');

		if($hasVat !== $decor['hasVat']) {
			$gaps[] = $label.' porte hasVat = '.var_export($hasVat, TRUE).', attendu '.var_export($decor['hasVat'], TRUE);
		}

		$eFeature = Feature::model()
			->select(array_keys($decor['features']))
			->whereStructure($eStructure)
			->get();

		foreach($decor['features'] as $feature => $value) {

			if($eFeature[$feature] !== $value) {
				$gaps[] = $label.' porte '.$feature.' = '.var_export($eFeature[$feature], TRUE).', attendu '.var_export($value, TRUE);
			}

		}

		if(self::hasVatHistory($eStructure, $decor) === FALSE) {
			$gaps[] = $label.' n\'a pas hasVat = '.var_export($decor['hasVat'], TRUE).' dans son historique au début de son premier exercice';
		}

		if($eStructure['type'] === Structure::COMMUNITY) {

			$asked = (CommunityLib::getByStructure($eStructure)['shareProduction']->get() !== 0);

			if($asked !== $decor['asks']) {
				$gaps[] = $label.' '.($asked ? 'demande' : 'ne demande pas').' la lecture de la production de ses fermes';
			}

		}

		array_push($gaps, ...self::checkMembers($label, $eStructure, $decor));
		array_push($gaps, ...self::checkAffiliations($label, $eStructure, $decor));

		if(\communication\DiscordAccess::model()->whereStructure($eStructure)->exists()) {
			$gaps[] = $label.' porte un accès Discord';
		}

		if(in_array(TRUE, self::notify('hasMembershipHistory', $eStructure), TRUE)) {
			$gaps[] = $label.' porte une adhésion ou un don';
		}

		$database = (\data\CleanTestStructureAiLib::getDatabases([$eStructure['id']]) !== []);

		if($database !== $decor['database']) {
			$gaps[] = $label.' '.($database ? 'a une base de structure qu\'elle ne devrait pas avoir' : 'n\'a pas de base de structure');
		}

		// Les tables comptables n'existent qu'une fois la comptabilité activée : sans elle, l'écart est
		// déjà nommé par `hasAccounting` plus haut, et les exercices ne se lisent pas.
		$hasAccounting = Feature::model()
			->whereStructure($eStructure)
			->getValue('hasAccounting');

		if($database and $hasAccounting and $decor['financialYears'] !== []) {
			array_push($gaps, ...self::checkFinancialYears($label, $eStructure, $decor));
			array_push($gaps, ...self::checkDocuments($label, $eStructure));
		}

		return $gaps;

	}

	/**
	 * Les propriétés légales qui s'écartent du décor, avec la valeur attendue. Une structure qui ne
	 * déclare pas de données légales doit les avoir vides, pays excepté : la création pose celui de
	 * `INPUT` sur une ferme et le vide sur un groupe.
	 *
	 * @return array<string, mixed>
	 */
	private static function getLegalGaps(Structure $eStructure, array $decor): array {

		if($decor['legal'] === NULL) {
			$expected = array_fill_keys(array_diff(self::LEGAL, ['legalCountry']), NULL);
		} else {
			$expected = $decor['legal'];
		}

		$gaps = [];

		foreach($expected as $property => $value) {

			if(self::getLegalValue($eStructure, $property) !== $value) {
				$gaps[$property] = $value;
			}

		}

		return $gaps;

	}

	/**
	 * Le pays est un élément, que `DECOR` déclare par son identifiant comme `INPUT`.
	 */
	private static function getLegalValue(Structure $eStructure, string $property): mixed {

		if($property !== 'legalCountry') {
			return $eStructure[$property];
		}

		return $eStructure['legalCountry']->empty() ? NULL : $eStructure['legalCountry']['id'];

	}

	/**
	 * Le groupe d'une ferme projet par son identifiant, comme `DECOR` le déclare.
	 */
	private static function getParent(Structure $eStructure): ?int {

		return $eStructure['parent']->empty() ? NULL : $eStructure['parent']['id'];

	}

	/**
	 * Le lieu du système sous la forme de `DECOR`, NULL sans nom : `System::properties()` pose ou
	 * efface le nom et le point ensemble, et refuse un nom sans point. Les codes en font partie : un
	 * décor posé avant eux, codes à NULL, doit se voir et se redresser.
	 */
	private static function getPlace(System $eSystem): ?array {

		if($eSystem['place'] === NULL) {
			return NULL;
		}

		return [
			'place' => $eSystem['place'],
			'placeLngLat' => $eSystem['placeLngLat'],
			'placeCity' => $eSystem['placeCity'],
			'placeDepartment' => $eSystem['placeDepartment'],
			'placeEpci' => $eSystem['placeEpci'],
		];

	}

	/**
	 * L'assujettissement à la TVA au début du premier exercice, que l'écran pose en créant cet
	 * exercice et que la liasse lit par exercice. Une structure sans exercice n'en porte pas.
	 */
	private static function hasVatHistory(Structure $eStructure, array $decor): bool {

		if($decor['financialYears'] === [] or $decor['hasVat'] === NULL) {
			return TRUE;
		}

		$eConfigurationHistory = ConfigurationHistoryLib::getForDate($eStructure, 'hasVat', $decor['financialYears'][0]['startDate']);

		return (
			$eConfigurationHistory->notEmpty() and
			$eConfigurationHistory['value']['hasVat'] === $decor['hasVat']
		);

	}

	/**
	 * @return string[]
	 */
	private static function checkMembers(string $label, Structure $eStructure, array $decor): array {

		$gaps = [];
		$found = [];

		$cMember = self::getMembers($eStructure);

		foreach($cMember as $eMember) {

			if($eMember['status'] !== Member::IN) {
				continue;
			}

			$email = $eMember['user']['email'];
			$found[] = $email;

			if(array_key_exists($email, $decor['members']) === FALSE) {
				$gaps[] = $label.' compte un membre en trop, '.$email;
			} else if($eMember['role']->is(self::getRole($eStructure, $decor['members'][$email])) === FALSE) {
				$gaps[] = $label.' donne à '.$email.' un autre rôle que '.$decor['members'][$email];
			}

		}

		foreach(array_diff(array_keys($decor['members']), $found) as $email) {
			$gaps[] = $label.' ne compte pas '.$email.' parmi ses membres';
		}

		foreach($decor['ghosts'] as $email) {

			$eMember = $cMember->find(fn(Member $eMember) => $eMember['user']['email'] === $email)->first() ?? new Member();

			if($eMember->empty()) {
				$gaps[] = $label.' ne garde pas '.$email.' en fantôme';
			} else if($eMember['status'] !== Member::OUT or $eMember['role']->is(self::getRole($eStructure, 'ghost')) === FALSE) {
				$gaps[] = $label.' garde '.$email.' autrement qu\'en fantôme sorti de l\'équipe';
			}

		}

		return $gaps;

	}

	/**
	 * @return string[]
	 */
	private static function checkAffiliations(string $label, Structure $eStructure, array $decor): array {

		$gaps = [];

		$cAffiliation = Affiliation::model()
			->select(['structureCommunity', 'user' => ['email'], 'shareProduction'])
			->whereStructureProducer($eStructure)
			->getCollection(index: 'structureCommunity');

		foreach($decor['affiliations'] as $community => $share) {

			if($cAffiliation->offsetExists($community) === FALSE) {
				$gaps[] = $label.' n\'est pas rattachée au groupe '.$community;
				continue;
			}

			$eAffiliation = $cAffiliation[$community];

			if($eAffiliation['user']['email'] !== $decor['owner']) {
				$gaps[] = $label.' est rattachée au groupe '.$community.' par un autre compte que '.$decor['owner'];
			}

			if(($eAffiliation['shareProduction']->get() !== 0) !== $share) {
				$gaps[] = $label.' '.($share ? 'ne partage pas' : 'partage').' sa production avec le groupe '.$community;
			}

		}

		foreach($cAffiliation as $community => $eAffiliation) {

			if(array_key_exists($community, $decor['affiliations']) === FALSE) {
				$gaps[] = $label.' est rattachée en trop au groupe '.$community;
			}

		}

		return $gaps;

	}

	/**
	 * @return string[]
	 */
	private static function checkFinancialYears(string $label, Structure $eStructure, array $decor): array {

		// Sans migration : un relevé ne doit rien écrire, pas même la version d'une base en retard.
		self::connect($eStructure, migrate: FALSE);

		$properties = ['startDate', 'endDate', 'status', 'taxSystem', 'accountingMode', 'accountingType', 'plan', 'legalCategory'];

		$cFinancialYear = \financialYear\FinancialYear::model()
			->select(array_merge(['id'], $properties, ['openDate', 'closeDate', 'hasOpeningBalance']))
			->sort(['startDate' => SORT_ASC])
			->getCollection(index: 'startDate');

		$found = [];

		// Le statut se lit par isClosed(), qui exige aussi la date de clôture : un exercice rouvert
		// garde sa date (FinancialYearLib::reopen()), et son seul statut ne dirait pas qu'il est clos.
		foreach($cFinancialYear as $eFinancialYear) {

			$financialYear = $eFinancialYear->extracts($properties);
			$financialYear['status'] = $eFinancialYear->isClosed() ? \financialYear\FinancialYear::CLOSE : \financialYear\FinancialYear::OPEN;
			$financialYear['opened'] = self::isOpened($eFinancialYear);

			$found[] = $financialYear;

		}

		// Les comptes et les pièces se relèvent à part, exercice par exercice, pour nommer chacun.
		$expected = array_map(fn(array $financialYear) => array_diff_key($financialYear, ['accounts' => TRUE, 'operations' => TRUE]), $decor['financialYears']);

		if($found !== $expected) {
			return [$label.' porte les exercices '.json_encode($found).', attendus '.json_encode($expected)];
		}

		$gaps = [];

		foreach($decor['financialYears'] as $financialYear) {

			$eFinancialYear = $cFinancialYear[$financialYear['startDate']];
			$labelFinancialYear = $label.', exercice '.$financialYear['startDate'].',';

			array_push($gaps, ...self::checkAccounts($labelFinancialYear, $eFinancialYear, $financialYear['accounts']));
			array_push($gaps, ...self::checkOperations($labelFinancialYear, $eFinancialYear, $financialYear['operations']));

		}

		return $gaps;

	}

	/**
	 * Les documents qui ne sont pas générés, et les tâches de FEC ou de document que la file garde
	 * pour la structure. Relevés à part des exercices, dont un écart rend la main avant les comptes :
	 * une tâche restée bloque les tests de la file quel que soit l'état des exercices.
	 *
	 * @return string[]
	 */
	private static function checkDocuments(string $label, Structure $eStructure): array {

		self::connect($eStructure, migrate: FALSE);

		$gaps = [];

		$cDocument = \financialYear\Document::model()
			->select(['financialYear', 'type', 'generation'])
			->whereGeneration('!=', \financialYear\Document::SUCCESS)
			->getCollection();

		foreach($cDocument as $eDocument) {
			$gaps[] = $label.' porte le document '.$eDocument['type'].' de l\'exercice '.$eDocument['financialYear']['id'].' en '.$eDocument['generation'].', attendu '.\financialYear\Document::SUCCESS;
		}

		$cTask = \queue\Task::model()
			->select(['id', 'action', 'status'])
			->whereReference($eStructure['id'])
			->whereAction('IN', [\financialYear\ImportLib::TREAT, \financialYear\DocumentLib::GENERATE])
			->getCollection();

		foreach($cTask as $eTask) {
			$gaps[] = $label.' porte la tâche '.$eTask['id'].' ('.$eTask['action'].', '.$eTask['status'].') que la file servirait pour de bon';
		}

		return $gaps;

	}

	/**
	 * @return string[]
	 */
	private static function checkAccounts(string $label, \financialYear\FinancialYear $eFinancialYear, array $accounts): array {

		$gaps = [];

		foreach($accounts as $account) {

			$eAccount = self::getAccount($eFinancialYear, $account['class']);

			if($eAccount->empty()) {
				$gaps[] = $label.' n\'a pas le compte '.$account['class'];
				continue;
			}

			$found = [
				'class' => $eAccount['class'],
				'description' => $eAccount['description'],
				'thirdParty' => $eAccount['thirdParty']->empty() ? NULL : $eAccount['thirdParty']['name'],
			];

			if($found !== $account) {
				$gaps[] = $label.' porte le compte '.json_encode($found, JSON_UNESCAPED_UNICODE).', attendu '.json_encode($account, JSON_UNESCAPED_UNICODE);
			}

		}

		return $gaps;

	}

	/**
	 * Chaque pièce déclarée, relue par son numéro, ligne pour ligne. Une pièce saisie hors du décor
	 * n'est pas cherchée : le relevé dit ce qui manque au décor, pas ce qu'un test aurait laissé, que
	 * le relevé de base des tests contrôle de son côté.
	 *
	 * @return string[]
	 */
	private static function checkOperations(string $label, \financialYear\FinancialYear $eFinancialYear, array $operations): array {

		$gaps = [];

		foreach($operations as $operation) {

			$found = self::getOperationLines($eFinancialYear, $operation['document']);
			$expected = self::getExpectedLines($operation);

			if($found === []) {
				$gaps[] = $label.' n\'a pas la pièce '.$operation['document'];
			} else if($found !== $expected) {
				$gaps[] = $label.' porte la pièce '.$operation['document'].' en '.json_encode($found, JSON_UNESCAPED_UNICODE).', attendue en '.json_encode($expected, JSON_UNESCAPED_UNICODE);
			}

		}

		return $gaps;

	}

	private static function getAccount(\financialYear\FinancialYear $eFinancialYear, string $class): \account\Account {

		return \account\Account::model()
			->select(['id', 'class', 'description', 'thirdParty' => ['name']])
			->whereFinancialYear($eFinancialYear)
			->whereClass($class)
			->get();

	}

	/**
	 * Les lignes d'une pièce sous la forme de `DECOR`, date et libellé compris, triées pour que l'ordre
	 * d'écriture n'y compte pas.
	 */
	private static function getOperationLines(\financialYear\FinancialYear $eFinancialYear, string $document): array {

		$cOperation = \journal\Operation::model()
			->select(['date', 'description', 'type', 'accountLabel', 'amount'])
			->whereFinancialYear($eFinancialYear)
			->whereDocument($document)
			->getCollection();

		$lines = [];

		foreach($cOperation as $eOperation) {
			$lines[] = [$eOperation['date'], $eOperation['description'], $eOperation['type'], $eOperation['accountLabel'], (float)$eOperation['amount']];
		}

		sort($lines);

		return $lines;

	}

	private static function getExpectedLines(array $operation): array {

		$lines = [];

		foreach($operation['lines'] as [$type, $class, $amount]) {
			$lines[] = [$operation['date'], $operation['description'], $type, \account\AccountLabelLib::pad($class), $amount];
		}

		sort($lines);

		return $lines;

	}

	/**
	 * @return \Collection<\user\User> Indexée par courriel
	 */
	private static function applyUsers(): \Collection {

		$cUser = self::getUsers();

		foreach(self::USERS as $email => $user) {

			if($cUser->offsetExists($email)) {
				continue;
			}

			$eUser = new \user\User([
				'visibility' => \user\User::PRIVATE,
				'roleCustomer' => FALSE,
				'roleProducer' => FALSE,
				'roleConsultant' => FALSE,
				'roleTeacher' => FALSE,
			]);

			$eUser[$user['role']] = TRUE;

			$fw = new \FailWatch();

			$eUser->build(['email', 'firstName', 'lastName'], [
				'email' => $email,
				'firstName' => $user['firstName'],
				'lastName' => $user['lastName'],
			]);

			$fw->validate();

			\user\UserLib::create($eUser);

			$fw->validate();

			self::write();

		}

		return self::getUsers();

	}

	private static function applyStructure(int $id, array $decor, \Collection $cUser): void {

		$eStructure = Structure::model()
			->select(Structure::getSelection())
			->whereId($id)
			->get();

		if($eStructure->notEmpty()) {

			if($eStructure['name'] !== $decor['name']) {

				$fw = new \FailWatch();

				$eStructure->build(['name'], ['name' => $decor['name']]);

				$fw->validate();

				StructureLib::update($eStructure, ['name']);

				self::write();

			}

			return;

		}

		$eStructure = new Structure(['type' => $decor['type']]);

		if($decor['owner'] !== NULL) {
			$eStructure['owner'] = $cUser[$decor['owner']];
		}

		$fw = new \FailWatch();

		$eStructure->build(StructureLib::getPropertiesCreate()($eStructure), [
			'name' => $decor['name'],
			'legalEmail' => $decor['legalEmail'],
			// Une ferme projet naît ferme : l'étape `producerType.type` refuse PROJECT à la
			// construction, et `applyProject()` la pose ensuite sous son groupe.
			'producerType' => match($decor['producerType']) {
				Structure::PROJECT => Structure::REAL,
				default => $decor['producerType'],
			},
			'communityType' => $decor['communityType'],
		] + self::INPUT, new \PropertyList('create'));

		$fw->validate();

		// Posé après le build, qui ne le construit pas : `create()` garde un identifiant posé.
		$eStructure['id'] = $id;

		StructureLib::create($eStructure);

		self::write();

	}

	private static function applyProfile(Structure $eStructure, array $decor): void {

		if($eStructure['consentData'] !== $decor['consentData']) {

			$fw = new \FailWatch();

			$eStructure->build(['consentData'], ['consentData' => $decor['consentData']]);

			$fw->validate();

			StructureLib::update($eStructure, ['consentData']);

			self::write();

		}

		$hasVat = Configuration::model()
			->whereStructure($eStructure)
			->getValue('hasVat');

		if($hasVat !== $decor['hasVat']) {

			ConfigurationLib::updateProperty($eStructure, 'hasVat', $decor['hasVat']);

			self::write();

		}

		self::applyLegal($eStructure, $decor);

		if($eStructure['profileVisibility'] !== $decor['profileVisibility']) {

			$fw = new \FailWatch();

			$eStructure->build(['profileVisibility'], ['profileVisibility' => $decor['profileVisibility']]);

			$fw->validate();

			StructureLib::update($eStructure, ['profileVisibility']);

			self::write();

		}

		self::applyPlace($eStructure, $decor);
		self::applyProject($eStructure, $decor);

		if($eStructure['type'] === Structure::COMMUNITY) {

			$eCommunity = CommunityLib::getByStructure($eStructure);

			if(($eCommunity['shareProduction']->get() !== 0) !== $decor['asks']) {

				$eCommunity['shareProduction'] = \Set::fromArray($decor['asks'] ? [Community::STAFF, Community::MEMBER] : []);

				CommunityLib::update($eCommunity, ['shareProduction']);

				self::write();

			}

		}

	}

	/**
	 * Par le geste de l'écran des données légales, qui vérifie le pays en même temps
	 * (`structure.p.php`, `doUpdateLegal`).
	 */
	private static function applyLegal(Structure $eStructure, array $decor): void {

		if($decor['legal'] === NULL) {
			return;
		}

		$properties = StructureLib::getPropertiesLegal($eStructure);

		// Un pays vérifié sort du geste : un écart qui ne porte que sur lui écrirait à chaque passage
		// sans rien changer, et reste nommé par `check()`.
		if(array_intersect(array_keys(self::getLegalGaps($eStructure, $decor)), $properties) === []) {
			return;
		}

		$fw = new \FailWatch();

		$eStructure->build($properties, $decor['legal'] + ['verifiedCountry' => TRUE]);

		$fw->validate();

		StructureLib::update($eStructure, $properties);

		self::write();

	}

	/**
	 * Par le geste de l'écran du lieu (`system.p.php`, `doUpdatePlace`), qui poste le point en JSON.
	 * Les codes du point viennent du décor et non de geo.api.gouv.fr : un test ne dépend pas du
	 * réseau, et `SystemLib::update()` ne l'appelle pas quand `placeDepartment` est écrit.
	 */
	private static function applyPlace(Structure $eStructure, array $decor): void {

		$eSystem = SystemLib::getByStructure($eStructure);

		if(self::getPlace($eSystem) === $decor['place']) {
			return;
		}

		$input = ($decor['place'] === NULL)
			? ['place' => NULL, 'placeLngLat' => NULL]
			: ['place' => $decor['place']['place'], 'placeLngLat' => json_encode($decor['place']['placeLngLat'])];

		$fw = new \FailWatch();

		$eSystem->build(['place', 'placeLngLat'], $input);

		$fw->validate();

		foreach(['placeCity', 'placeDepartment', 'placeEpci'] as $property) {
			$eSystem[$property] = ($decor['place'] === NULL) ? NULL : $decor['place'][$property];
		}

		SystemLib::update($eSystem, ['place', 'placeLngLat', 'placeCity', 'placeDepartment', 'placeEpci']);

		self::write();

	}

	/**
	 * La ferme projet sous son groupe. Aucun geste du produit ne fait d'une ferme une ferme projet :
	 * l'étape `producerType.type` refuse PROJECT à la construction, puisque c'est le groupe qui la
	 * pose.
	 */
	private static function applyProject(Structure $eStructure, array $decor): void {

		if(
			$decor['parent'] === NULL or
			($eStructure['producerType'] === Structure::PROJECT and self::getParent($eStructure) === $decor['parent'])
		) {
			return;
		}

		$eStructure['producerType'] = Structure::PROJECT;
		$eStructure['parent'] = new Structure(['id' => $decor['parent']]);

		// Sans `build()`, qui refuse PROJECT : c'est la seule écriture du décor qu'aucun geste du
		// produit ne fait, et le groupe qu'elle nomme est une structure du décor.
		StructureLib::update($eStructure, ['producerType', 'parent']);

		self::write();

	}

	private static function applyMembers(Structure $eStructure, array $decor, \Collection $cUser): void {

		$cMember = self::getMembers($eStructure);

		foreach($cMember as $eMember) {

			// Un fantôme redevenu présent se remet plus bas : `delete()` refuse une ligne de rôle `ghost`.
			if(
				$eMember['status'] === Member::IN and
				array_key_exists($eMember['user']['email'], $decor['members']) === FALSE and
				in_array($eMember['user']['email'], $decor['ghosts'], TRUE) === FALSE
			) {

				$fw = new \FailWatch();

				MemberLib::delete($eMember);

				$fw->validate();

				self::write();

			}

		}

		foreach($decor['members'] as $email => $role) {

			$eRole = self::getRole($eStructure, $role);

			if($eRole->empty()) {
				throw new \Exception('La structure '.$eStructure['id'].' n\'a pas de rôle '.$role.'.');
			}

			$eMember = $cMember->find(fn(Member $eMember) => $eMember['user']['email'] === $email)->first() ?? new Member();

			if($eMember->empty()) {

				$fw = new \FailWatch();

				MemberLib::create(new Member([
					'user' => $cUser[$email],
					'structure' => $eStructure,
					'role' => $eRole,
					'status' => Member::IN,
				]));

				$fw->validate();

				self::write();

			} else if($eMember['status'] !== Member::IN or $eMember['role']->is($eRole) === FALSE) {

				$eMember['status'] = Member::IN;
				$eMember['role'] = $eRole;

				MemberLib::update($eMember, ['status', 'role']);

				self::write();

			}

		}

		foreach($decor['ghosts'] as $email) {

			$eRole = self::getRole($eStructure, 'ghost');

			if($eRole->empty()) {
				throw new \Exception('La structure '.$eStructure['id'].' n\'a pas de rôle ghost.');
			}

			$eMember = $cMember->find(fn(Member $eMember) => $eMember['user']['email'] === $email)->first() ?? new Member();

			if($eMember->empty()) {

				$fw = new \FailWatch();

				MemberLib::create(new Member([
					'user' => $cUser[$email],
					'structure' => $eStructure,
					'role' => $eRole,
					'status' => Member::OUT,
				]));

				$fw->validate();

				self::write();

			} else if($eMember['status'] !== Member::OUT or $eMember['role']->is($eRole) === FALSE) {

				$eMember['status'] = Member::OUT;
				$eMember['role'] = $eRole;

				MemberLib::update($eMember, ['status', 'role']);

				self::write();

			}

		}

	}

	/**
	 * La comptabilité et les exercices, par les deux gestes de l'écran d'accueil de la comptabilité :
	 * l'activation, puis la création du premier exercice et de sa ligne d'historique de TVA
	 * (`company/page/public.p.php`). Les exercices sont ensuite ouverts et clos par
	 * applyFinancialYearStates(), une fois l'historique de TVA posé.
	 */
	private static function applyAccounting(Structure $eStructure, array $decor): void {

		if($decor['financialYears'] === []) {
			return;
		}

		if(Feature::model()->whereStructure($eStructure)->getValue('hasAccounting') === FALSE) {

			$fw = new \FailWatch();

			\company\CompanyLib::enableAccounting($eStructure);

			$fw->validate();

			self::write();

		}

		self::connect($eStructure, migrate: TRUE);

		foreach($decor['financialYears'] as $financialYear) {

			if(\financialYear\FinancialYear::model()->whereStartDate($financialYear['startDate'])->exists()) {
				continue;
			}

			if($financialYear['accountingMode'] === \financialYear\FinancialYear::CASH_RECEIPTS) {
				\financialYear\FinancialYearLib::createCashReceiptsYear($eStructure, $financialYear['legalCategory'], $financialYear['startDate'], $financialYear['taxSystem']);
			} else {
				\financialYear\FinancialYearLib::initializeFirst($eStructure, array_diff_key($financialYear, ['status' => TRUE, 'opened' => TRUE, 'accounts' => TRUE, 'operations' => TRUE]), origin: 'onboarding');
			}

			self::write();

		}

		// L'historique de TVA précède les clôtures : la clôture le lit à la date de début de l'exercice,
		// et l'écran refuse de clore un exercice qui ne l'a pas (HasVatAiLib::check()).
		if(self::hasVatHistory($eStructure, $decor) === FALSE) {

			$eFinancialYear = \financialYear\FinancialYear::model()
				->select(\financialYear\FinancialYear::getSelection())
				->whereStartDate($decor['financialYears'][0]['startDate'])
				->get();

			\company\OnboardingAiLib::createHasVatHistoryForFirstFinancialYear($eStructure, $eFinancialYear);

			self::write();

		}

		self::applyFinancialYearStates($eStructure, $decor);

	}

	/**
	 * L'à-nouveau et la clôture, par les gestes entiers de l'écran (doOpen, doClose, doReclose de
	 * financialYear/page/index.p.php), dans l'ordre chronologique que le produit impose : un exercice
	 * ne se clôt pas tant que le précédent est ouvert, et ne s'ouvre qu'une fois le précédent clos.
	 * Chaque exercice est relu avant son tour, puisque le geste sur le précédent change ce qu'il
	 * accepte. Chaque garde de route est rejouée : un refus lève avant le geste qu'il
	 * garde, plutôt que de poser un état que l'écran ne sait pas fabriquer.
	 */
	private static function applyFinancialYearStates(Structure $eStructure, array $decor): void {

		foreach($decor['financialYears'] as $financialYear) {

			$eFinancialYear = \financialYear\FinancialYear::model()
				->select(\financialYear\FinancialYear::getSelection())
				->whereStartDate($financialYear['startDate'])
				->get();

			self::applyAccounts($eFinancialYear, $financialYear['accounts']);

			// isOpened() dit s'il reste à ouvrir, acceptOpen() si l'écran l'accepterait : un exercice déjà
			// ouvert se laisse tel quel au lieu de lever sur le refus que l'écran lui opposerait.
			if($financialYear['opened'] and self::isOpened($eFinancialYear) === FALSE) {

				self::assertAccepted($eStructure, $eFinancialYear, $eFinancialYear->acceptOpen(), 'acceptOpen');

				\financialYear\FinancialYearLib::openFinancialYear($eFinancialYear, []);

				\financialYear\DocumentLib::regenerateAll($eStructure, $eFinancialYear, [\financialYear\DocumentLib::OPENING, \financialYear\DocumentLib::OPENING_DETAILED]);

				self::write();

			}

			self::applyOperations($eFinancialYear, $financialYear);

			if($financialYear['status'] === \financialYear\FinancialYear::CLOSE and $eFinancialYear->isClosed() === FALSE) {

				$fw = new \FailWatch();

				// Un exercice rouvert garde sa date de clôture, et le produit le referme par reclose(),
				// sans refaire les documents de la première clôture ni leur archive. Il produit en revanche
				// une nouvelle archive de caisse et de ventes, quand l'exercice en porte.
				if($eFinancialYear['closeDate'] !== NULL) {

					self::assertAccepted($eStructure, $eFinancialYear, $eFinancialYear->acceptReClose(), 'acceptReClose');

					\financialYear\ClosingLib::reclose($eFinancialYear);

				} else {

					self::assertAccepted($eStructure, $eFinancialYear, $eFinancialYear->acceptClose(), 'acceptClose');

					// Les deux temps de la route doClose : la TVA renseignée refuse avant d'écrire, puis
					// closeFinancialYear() journalise lui-même la cause de chacun de ses refus.
					\financialYear\HasVatAiLib::check($eStructure, $eFinancialYear);

					$fw->validate();

					\financialYear\ClosingLib::closeFinancialYear($eStructure, $eFinancialYear);

				}

				$fw->validate();

				self::write();

			}

		}

		self::applyDocuments($eStructure);

	}

	/**
	 * Les documents que la clôture et l'ouverture demandent (`DocumentLib::regenerateAll()`), générés
	 * ici plutôt que laissés à la file : aucun runner ne la sert en dev, et une tâche restée sur une
	 * structure du décor arrête les tests qui la lisent (`financialYear/test/QueueAi.t.php`). Même
	 * borné à la structure, `QueueLib::run()` ne ferait de ce passage qu'un essai parmi cinq : un
	 * document en échec y laisserait sa tâche reportée, que le montage ne saurait pas conclure.
	 *
	 * La génération passe d'abord, et ce passage est son dernier essai : un document qui échoue
	 * passe en échec, d'où l'écran le régénère, et l'exception arrête le montage avant que la tâche
	 * ne soit retirée. Une tâche restée sans document à générer se retire aussi.
	 */
	private static function applyDocuments(Structure $eStructure): void {

		$hasWaiting = \financialYear\Document::model()
			->whereGeneration(\financialYear\Document::WAITING)
			->exists();

		$cTask = \queue\TaskLib::getByElements($eStructure['id'], \financialYear\DocumentLib::GENERATE, [$eStructure['id']]);

		if($hasWaiting === FALSE and $cTask->empty()) {
			return;
		}

		// generateWaiting() annule la transaction ouverte quand un document échoue : celle d'un
		// appelant partirait avec, et tout ce qu'il a écrit avant.
		if(\financialYear\Document::model()->inTransaction()) {
			throw new \Exception('La structure '.$eStructure['id'].' a des documents à générer, ce qui ne se fait pas dans une transaction ouverte, rien n\'est écrit.');
		}

		\financialYear\DocumentLib::generateWaiting($eStructure, lastTry: TRUE);

		if($cTask->notEmpty()) {

			$eTask = $cTask->first();

			// reserve() ne prend qu'une tâche en attente et échue : une tâche reportée, en cours ou en
			// échec ne se retire pas d'ici sans décider à la place de la file.
			$eTaskReserved = \queue\TaskLib::reserve($eTask);

			if($eTaskReserved->empty()) {
				throw new \Exception('La tâche '.$eTask['id'].' ('.$eTask['action'].', '.$eTask['status'].') de la structure '.$eStructure['id'].' n\'est pas en attente et échue, elle ne se retire pas.');
			}

			\queue\TaskLib::complete($eTaskReserved);

		}

		self::write();

	}

	/**
	 * Les comptes que l'exercice ajoute à son plan, par le geste de création d'un compte
	 * (`account/page/account.p.php`), et le tiers que porte un compte courant, par celui de création
	 * d'un tiers. Un tiers se retrouve par son nom, un compte à sa classe : ce qui est en place n'est
	 * pas réécrit, et un écart sur l'un ou l'autre reste nommé par `check()`.
	 */
	private static function applyAccounts(\financialYear\FinancialYear $eFinancialYear, array $accounts): void {

		foreach($accounts as $account) {

			if(self::getAccount($eFinancialYear, $account['class'])->notEmpty()) {
				continue;
			}

			$fw = new \FailWatch();

			$eAccount = new \account\Account();
			$eAccount->build(\account\AccountLib::getPropertiesCreate()($eAccount), [
				'financialYear' => $eFinancialYear['id'],
				'class' => $account['class'],
				'description' => $account['description'],
				'thirdParty' => ($account['thirdParty'] === NULL) ? NULL : self::applyThirdParty($account['thirdParty'])['id'],
			]);

			$fw->validate();

			\account\AccountLib::create($eAccount);

			self::write();

		}

	}

	private static function applyThirdParty(string $name): \thirdParty\ThirdParty {

		$eThirdParty = \thirdParty\ThirdParty::model()
			->select(['id'])
			->whereName($name)
			->get();

		if($eThirdParty->notEmpty()) {
			return $eThirdParty;
		}

		$fw = new \FailWatch();

		$eThirdParty->build(['name'], ['name' => $name]);

		$fw->validate();

		\thirdParty\ThirdPartyLib::create($eThirdParty);

		self::write();

		return $eThirdParty;

	}

	/**
	 * Les pièces de l'exercice, chacune d'un seul hash comme une saisie au journal, par
	 * `OperationLib::createFromValues()`, qui construit chaque ligne. Une ligne sur un compte déclaré
	 * avec un tiers porte ce tiers, comme la saisie le propose. Une pièce dont le numéro existe déjà
	 * sur l'exercice est en place ; un exercice clos n'accepte plus d'écriture, et ses pièces
	 * manquantes ne se rattrapent pas ici.
	 */
	private static function applyOperations(\financialYear\FinancialYear $eFinancialYear, array $financialYear): void {

		if($financialYear['operations'] === [] or $eFinancialYear->isClosed()) {
			return;
		}

		$eJournalCode = \journal\JournalCodeLib::askByCode(\journal\JournalSetting::JOURNAL_CODE_OD);
		$thirdParties = array_column($financialYear['accounts'], 'thirdParty', 'class');

		foreach($financialYear['operations'] as $operation) {

			if(self::getOperationLines($eFinancialYear, $operation['document']) !== []) {
				continue;
			}

			$hash = \journal\OperationLib::generateHash(\journal\JournalSetting::HASH_LETTER_WRITE);

			// Une pièce s'écrit entière ou pas du tout : à moitié posée, elle serait en place pour le
			// passage suivant, qui la retrouve par son numéro, et déséquilibrerait l'exercice.
			\journal\Operation::model()->beginTransaction();

			try {

				foreach($operation['lines'] as [$type, $class, $amount]) {

					\journal\OperationLib::createFromValues([
						'financialYear' => $eFinancialYear['id'],
						'date' => $operation['date'],
						'accountNumber' => $class,
						'accountLabel' => \account\AccountLabelLib::pad($class),
						'journalCode' => $eJournalCode['id'],
						'hash' => $hash,
						'thirdParty' => isset($thirdParties[$class]) ? self::applyThirdParty($thirdParties[$class])['id'] : NULL,
						'description' => $operation['description'],
						'type' => $type,
						'amount' => $amount,
						'document' => $operation['document'],
						'documentDate' => $operation['date'],
					]);

				}

			} catch(\Throwable $e) {
				\journal\Operation::model()->rollBack();
				throw $e;
			}

			\journal\Operation::model()->commit();

			self::write();

		}

	}

	/**
	 * L'à-nouveau posé, pour le relevé comme pour le montage. La date seule ne suffit pas : l'ouverture
	 * la pose aussi quand elle n'enregistre pas l'à-nouveau, et c'est le drapeau que lisent
	 * acceptCloseView() et isWaitingOpeningBalance().
	 */
	private static function isOpened(\financialYear\FinancialYear $eFinancialYear): bool {

		return ($eFinancialYear['openDate'] !== NULL and $eFinancialYear->hasOpeningBalance());

	}

	/**
	 * La garde que la route pose par addValidate. Elle rend un booléen sans journaliser sa cause :
	 * le refus se nomme donc ici, par la garde et l'exercice, pour ne pas lever plus loin sans raison.
	 * La boucle des exercices ne tient pas de transaction : un refus n'écrit rien pour le geste refusé,
	 * et laisse posés les gestes qui le précèdent, l'ouverture du même exercice comprise.
	 */
	private static function assertAccepted(Structure $eStructure, \financialYear\FinancialYear $eFinancialYear, bool $accepted, string $accept): void {

		if($accepted === FALSE) {
			throw new \Exception('L\'exercice '.$eFinancialYear['startDate'].' de la structure '.$eStructure['id'].' refuse '.$accept.'(), comme l\'écran le refuserait : ce geste n\'écrit rien, ceux qui le précèdent restent posés.');
		}

	}

	/**
	 * Ce qui reste des fonctionnalités une fois la comptabilité posée, qui allume les siennes.
	 */
	private static function applyFeatures(Structure $eStructure, array $decor): void {

		$eFeature = Feature::model()
			->select(array_keys($decor['features']))
			->whereStructure($eStructure)
			->get();

		foreach($decor['features'] as $feature => $value) {

			if(in_array($feature, ['hasAccounting', 'hasFinancialYears']) or $eFeature[$feature] === $value) {
				continue;
			}

			FeatureLib::updateFeature($eStructure, $feature, $value);

			self::write();

		}

	}

	/**
	 * Par le geste de l'écran (`structure.p.php`, `doClose`), une fois les rattachements posés :
	 * `isMember()` ne compte que les structures actives, et une structure fermée ne se rattache plus.
	 */
	private static function applyStatus(Structure $eStructure, array $decor): void {

		if($decor['status'] === Structure::ACTIVE or $eStructure['status'] === Structure::CLOSED) {
			return;
		}

		$eStructure['status'] = Structure::CLOSED;

		StructureLib::close($eStructure);

		self::write();

	}

	private static function applyAffiliations(Structure $eStructure, array $decor, \Collection $cUser, \Collection $cStructure): void {

		foreach($decor['affiliations'] as $community => $share) {

			$eStructureCommunity = $cStructure[$community];

			// Rien de coché, pour une ferme silencieuse : `create()` borne ce choix à ce que le groupe
			// demande, et sans choix il accorde toute la demande.
			$rights = $share ? NULL : array_fill_keys(AffiliationAiLib::SHARES, []);

			$eAffiliation = Affiliation::model()
				->select(['id', 'shareProduction'])
				->whereStructureProducer($eStructure)
				->whereStructureCommunity($eStructureCommunity)
				->get();

			if($eAffiliation->empty()) {

				$fw = new \FailWatch();

				AffiliationAiLib::create(new Affiliation([
					'user' => $cUser[$decor['owner']],
					'structureProducer' => $eStructure,
					'structureCommunity' => $eStructureCommunity,
				]), $rights);

				$fw->validate();

				self::write();

			} else if(($eAffiliation['shareProduction']->get() !== 0) !== $share) {

				$rightsGranted = [];

				foreach(AffiliationAiLib::SHARES as $right) {
					$rightsGranted[$right] = $share ? $eStructureCommunity->askedCommunityAudiences($right) : [];
				}

				AffiliationAiLib::setRights($eStructure, $eStructureCommunity, $rightsGranted);

				self::write();

			}

		}

	}

}
?>
