<?php
namespace data;

/**
 * La règle de chaque colonne dans une copie anonymisée des sauvegardes de production.
 *
 * Une colonne d'un type que `AnonymizeAiLib::TYPES_DECLARED` désigne (texte, éditeur, json, e-mail,
 * url, adresse IP, binaire, position, adresse de page) porte ici une règle et sa raison, sous la
 * forme `[règle, raison]`, ou `[règle, raison, libellé]` pour les règles qui en prennent un
 * (`RULES_LABELLED`). Les autres colonnes se gardent, sauf celles qu'une règle nomme quand même :
 * une date de naissance est une date, et c'est pourtant une donnée personnelle.
 * `AnonymizeSentinelAi` refuse toute colonne sans règle et toute règle sans colonne.
 *
 * Un package fermé à la publication, hors comptabilité, déclare ses règles et ses modules vidés dans
 * son `lib/Data.o.php` (`getAnonymizeRules()`, `getAnonymizeTruncate()`), sous la même forme : la
 * version publique ne peut pas nommer ses modules. `AnonymizeAiLib::getRules()` et
 * `getModulesTruncate()` rendent la fusion.
 *
 * La structure 7 reste en clair (décidé par Vincent le 2026-09-27) : seules les règles `SECRET`
 * s'appliquent à ses lignes. Les comptes des administrateurs de la production aussi
 * (`ADMIN_ROWS`). Une valeur `NULL` reste `NULL` sous toutes les règles, pour qu'un champ vide le
 * reste et qu'un écran ne montre pas une donnée qui n'existait pas.
 */
class AnonymizeAiSetting extends \Settings {

	/** Ne dit rien d'une personne ni d'une structure : référentiel, code, libellé de produit, réglage. */
	const KEEP = 'keep';

	/** Remplacée par `<libellé> <id>`, pour qu'un écran qui l'affiche reste lisible. */
	const FAKE = 'fake';

	/**
	 * Remplacée par `<libellé>-<id>` : une adresse de page ou un sous-domaine doit rester unique et
	 * ne porte pas d'espace.
	 */
	const FAKE_SLUG = 'fakeSlug';

	/** Remplacée par `<table><id>@example.invalid`, domaine réservé où aucun envoi n'aboutit. */
	const FAKE_EMAIL = 'fakeEmail';

	/** Remplacée par un numéro de mobile fictif dérivé de l'id. */
	const FAKE_PHONE = 'fakePhone';

	/** Vidée : `NULL` si la colonne l'accepte, sinon la valeur vide de son type. */
	const CLEAR = 'clear';

	/** Vidée partout, structure 7 comprise : un jeton, une clé, un mot de passe, une session. */
	const SECRET = 'secret';

	/**
	 * Un numéro ou un libellé de compte : les chiffres de tête restent, la suite est remplacée par une
	 * empreinte salée de la valeur d'origine.
	 *
	 * Une structure nomme ses comptes auxiliaires d'après ses tiers (« 401 » suivi du nom du
	 * fournisseur). L'empreinte est la même pour une même valeur dans toutes les tables et les deux
	 * familles de bases d'un export : les écritures, les tiers, les inventaires et les ventes se
	 * rejoignent encore, et les index uniques sur `accountLabel` tiennent. Un numéro fait de chiffres
	 * seuls ne nomme personne et sort inchangé. Le sel est tiré à chaque export et n'est écrit nulle
	 * part, sans quoi un nom court se retrouverait en essayant tous les noms.
	 *
	 * L'empreinte garde la longueur de la valeur d'origine : les numéros de compte des tiers et les
	 * comptes proposés sur les lignes de facture tiennent en 8 caractères. Elle reste unique dans la
	 * structure, faute de quoi l'index unique des numéros de tiers refuse la copie ; une collision
	 * arrête l'export plutôt que de fusionner deux comptes.
	 */
	const ACCOUNT = 'account';

	/**
	 * Gardée si elle figure au plan comptable de référence (`REFERENCE_SOURCE`), sans égard à la
	 * casse comme la collation de la colonne, remplacée par
	 * `<libellé> <id>` sinon : le libellé d'un compte que la structure a créé peut nommer une
	 * personne (« prélèvement privé » suivi d'un prénom).
	 */
	const REFERENCE = 'reference';

	/** La colonne qui fait foi pour `REFERENCE`. */
	const REFERENCE_SOURCE = ['company\\GenericAccount', 'description'];

	/** Les règles qui prennent un libellé en troisième position. */
	const RULES_LABELLED = [self::FAKE, self::FAKE_SLUG, self::REFERENCE];

	/** Le module part en entier. */
	const TRUNCATE_ALL = 'all';

	/**
	 * Le module part sauf les lignes de la 7. Il faut pouvoir les borner : le module porte une
	 * colonne `structure`, ou vit dans une base `structure_<id>`.
	 */
	const TRUNCATE_EXCEPT_7 = 'except7';

	/**
	 * Les modules dont les lignes partent, sous la forme `[portée, raison]`. Leurs colonnes ne se
	 * déclarent pas.
	 *
	 * La portée se déclare module par module et ne se déduit pas de la présence d'une colonne
	 * `structure` : un journal d'échanges avec un tiers porte des jetons et des factures d'autres
	 * structures jusque dans les lignes de la 7, et il part en entier.
	 */
	const MODULES_TRUNCATE = [
		'api\\AccessToken' => [self::TRUNCATE_ALL, 'jetons d\'accès à l\'API'],
		'api\\AuthCode' => [self::TRUNCATE_ALL, 'codes d\'autorisation OAuth'],
		'api\\Log' => [self::TRUNCATE_ALL, 'journal des appels à l\'API, en-têtes et réponses compris'],
		'api\\RefreshToken' => [self::TRUNCATE_ALL, 'jetons de renouvellement de l\'API'],
		'data\\EnableBankingHistory' => [self::TRUNCATE_ALL, 'journal des échanges avec EnableBanking'],
		'data\\PdpHistory' => [self::TRUNCATE_ALL, 'journal des échanges avec la plateforme de facturation, factures comprises'],
		'dev\\Error' => [self::TRUNCATE_ALL, 'erreurs de production, avec les paramètres des requêtes'],
		'dev\\ErrorParameter' => [self::TRUNCATE_ALL, 'paramètres des requêtes en erreur'],
		'dev\\ErrorTrace' => [self::TRUNCATE_ALL, 'arguments des appels en erreur'],
		'dev\\Query' => [self::TRUNCATE_ALL, 'journal des réponses de la dev, adresses et utilisateurs compris'],
		'dev\\QueryStatement' => [self::TRUNCATE_ALL, 'requêtes en double ou lentes de chaque réponse du journal'],
		'help\\Check' => [self::TRUNCATE_ALL, 'fichiers produits par les contrôles d\'une demande d\'aide : relevé de FEC, liste des tiers et empreinte de la base de la structure'],
		'help\\File' => [self::TRUNCATE_ALL, 'pièces jointes aux demandes d\'aide comptable, dont des FEC complets'],
		'help\\Gap' => [self::TRUNCATE_ALL, 'écarts attendus au verdict d\'un import, dont la raison cite des comptes et des montants de la structure'],
		'help\\Import' => [self::TRUNCATE_ALL, 'imports d\'une reprise, qui portent la configuration, le verdict et l\'empreinte de toute la base de la structure'],
		'help\\Message' => [self::TRUNCATE_ALL, 'échanges entre une structure et l\'équipe, qui citent associés, comptes et montants'],
		'help\\Proposal' => [self::TRUNCATE_ALL, 'propositions des contrôles d\'une demande d\'aide, qui citent noms de fichiers, comptes et montants'],
		'help\\Step' => [self::TRUNCATE_ALL, 'journal de chaque étape d\'un import, qui cite des tiers, des comptes et des montants'],
		'notification\\Notification' => [self::TRUNCATE_EXCEPT_7, 'notifications, qui citent les clients par leur nom'],
		'taxReturn\\Deposit' => [self::TRUNCATE_EXCEPT_7, 'liasses déposées, dont la photo en json porte les associés en entier et ne se réécrit pas valeur par valeur ; sans dépôt, la liasse redevient un brouillon'],
		'map\\DecorUpload' => [self::TRUNCATE_ALL, 'modèles 3D téléversés à la place d\'un bâtiment composé, et leur modération'],
		'map\\Relief' => [self::TRUNCATE_ALL, 'altitudes autour des zones, dont les tuiles situent la structure'],
		'queue\\Task' => [self::TRUNCATE_ALL, 'tâches en attente, dont la charge cite les éléments traités'],
		'security\\AccessEvent' => [self::TRUNCATE_ALL, 'journal des accès, adresses IP comprises'],
		'session\\Session' => [self::TRUNCATE_ALL, 'sessions ouvertes'],
		'storage\\Buffer' => [self::TRUNCATE_ALL, 'fichiers en transit'],
		'user\\AdminLog' => [self::TRUNCATE_ALL, 'journal des actions d\'administration, adresses IP comprises'],
		'user\\Ban' => [self::TRUNCATE_ALL, 'adresses IP bannies'],
		'user\\Challenge' => [self::TRUNCATE_ALL, 'codes de connexion envoyés par e-mail'],
		'user\\Log' => [self::TRUNCATE_ALL, 'journal des connexions, adresses IP comprises'],
		'user\\LoginAttempt' => [self::TRUNCATE_ALL, 'tentatives de connexion, identifiants et adresses IP compris'],
		'user\\LoginLink' => [self::TRUNCATE_ALL, 'liens de connexion envoyés par e-mail, adresses IP comprises'],
		'user\\UserAuto' => [self::TRUNCATE_ALL, 'clés de reconnexion automatique'],
		'user\\UserAvatarRequest' => [self::TRUNCATE_ALL, 'photos de visage envoyées pour faire un avatar, et leur suite'],
		'user\\UserAvatarUpload' => [self::TRUNCATE_ALL, 'modèles 3D téléversés à la place d\'un avatar composé'],
		'util\\Cache' => [self::TRUNCATE_ALL, 'cache, qui recopie des données de toutes les tables'],
		'website\\Contact' => [self::TRUNCATE_EXCEPT_7, 'messages reçus par les sites des structures'],
	];

	/**
	 * Les lignes des administrateurs de la production (`\user\UserSetting::$admin`) restent en
	 * clair, comme la structure 7, pour que chaque membre de l'équipe retrouve son compte et s'y
	 * connecte (décidé par Émilie le 2026-09-27). Par module, la propriété qui rattache une ligne à
	 * l'administrateur, et la raison.
	 *
	 * La liste se lit dans la configuration de la machine qui exporte (`secret.c.php`, hors git) :
	 * elle doit être celle de la production, et le manifeste de l'export nomme les comptes gardés.
	 */
	const ADMIN_ROWS = [
		'user\\User' => ['id', 'identité et adresse de connexion du membre de l\'équipe'],
		'user\\UserAuth' => ['user', 'identifiant et mot de passe du membre de l\'équipe'],
	];

	/**
	 * Les secrets que les lignes des administrateurs gardent : sans l'empreinte du mot de passe,
	 * personne ne se connecte à la copie. Le jeton de réinitialisation reste vidé.
	 */
	const ADMIN_SECRETS_KEPT = [
		'user\\UserAuth' => ['password'],
	];

	/**
	 * La règle de chaque colonne, par module puis par propriété.
	 */
	const RULES = [
		'aggregate\\Calendar' => [
			'harvestWeeks' => [self::KEEP, 'table agrégée anonyme par construction (AnonymitySentinelAi)'],
			'weeks' => [self::KEEP, 'table agrégée anonyme par construction (AnonymitySentinelAi)'],
		],
		'aggregate\\Cultivation' => [
			'harvestWeeks' => [self::KEEP, 'table agrégée anonyme par construction (AnonymitySentinelAi)'],
		],
		'aggregate\\CultivationVariety' => [
			'name' => [self::KEEP, 'nom de variété du référentiel partagé'],
			'searchKey' => [self::KEEP, 'clé de recherche dérivée du nom de variété'],
		],
		'aggregate\\VegetableHours' => [
			'fqn' => [self::KEEP, 'code du référentiel'],
		],
		'aggregate\\VegetableSpecies' => [
			'months' => [self::KEEP, 'mois de culture agrégés'],
		],
		'aid\\Aid' => [
			'name' => [self::KEEP, 'intitulé d\'aide du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'summary' => [self::KEEP, 'résumé d\'aide du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'conditions' => [self::KEEP, 'conditions d\'aide du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'rate' => [self::KEEP, 'taux d\'aide du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'productions' => [self::KEEP, 'productions du référentiel'],
			'keywords' => [self::KEEP, 'mots de recherche du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'notes' => [self::KEEP, 'relevé de recherche du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'seedDifferences' => [self::KEEP, 'écarts de reprise du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'seedKept' => [self::KEEP, 'empreinte d\'écart de reprise du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'aliases' => [self::KEEP, 'identifiants de recherche du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'depositUrl' => [self::KEEP, 'site public de dépôt d\'un organisme'],
			'commitmentRule' => [self::KEEP, 'règle de dépôt du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
		],
		'aid\\AidPiece' => [
			'name' => [self::KEEP, 'intitulé de pièce du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'note' => [self::KEEP, 'condition de pièce du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'url' => [self::KEEP, 'modèle public d\'un organisme'],
		],
		'aid\\Area' => [
			'department' => [self::KEEP, 'code INSEE de département'],
			'epci' => [self::KEEP, 'SIREN d\'un EPCI, une collectivité'],
			'commune' => [self::KEEP, 'code INSEE de commune'],
			'note' => [self::KEEP, 'précision de périmètre du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
		],
		'aid\\Edition' => [
			'name' => [self::KEEP, 'intitulé d\'appel du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'rate' => [self::KEEP, 'taux d\'appel du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'ceiling' => [self::KEEP, 'plafond d\'appel du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'url' => [self::KEEP, 'page publique d\'un organisme'],
		],
		'aid\\Expected' => [
			'code' => [self::KEEP, 'code de ligne attendue du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'department' => [self::KEEP, 'code INSEE de département'],
			'region' => [self::KEEP, 'code INSEE de région'],
			'number' => [self::KEEP, 'numéro de mesure d\'une liste officielle'],
			'name' => [self::KEEP, 'intitulé de mesure d\'une liste officielle'],
			'justification' => [self::KEEP, 'justification du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'justificationUrl' => [self::KEEP, 'page publique d\'un organisme'],
			'listUrl' => [self::KEEP, 'liste officielle publiée par un organisme'],
		],
		'aid\\Organization' => [
			'name' => [self::KEEP, 'nom d\'organisme public ou associatif'],
			'url' => [self::KEEP, 'site public d\'un organisme'],
		],
		'aid\\Piece' => [
			'fqn' => [self::KEEP, 'code du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'name' => [self::KEEP, 'intitulé de pièce du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
		],
		'aid\\Source' => [
			'url' => [self::KEEP, 'page publique d\'un organisme'],
			'movedUrl' => [self::KEEP, 'page publique d\'un organisme'],
			'fingerprint' => [self::KEEP, 'empreinte d\'une page publique'],
		],
		'aid\\Tag' => [
			'fqn' => [self::KEEP, 'code du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
			'name' => [self::KEEP, 'intitulé de thème du catalogue des aides, commun à toutes les structures et tenu par l\'équipe'],
		],
		'api\\Client' => [
			'clientId' => [self::KEEP, 'identifiant public du client OAuth'],
			'name' => [self::KEEP, 'nom de l\'application cliente'],
			'secret' => [self::SECRET, 'secret du client OAuth'],
			'redirectUris' => [self::KEEP, 'adresses de retour de l\'application'],
		],
		'api\\Scope' => [
			'name' => [self::KEEP, 'nom de portée du référentiel de l\'API'],
		],
		'block\\Block' => [
			'owner' => [self::KEEP, 'usage du bloc, contenu éditorial de la formation'],
			'type' => [self::KEEP, 'type de bloc'],
			'variant' => [self::KEEP, 'variante de bloc'],
			'title' => [self::KEEP, 'contenu éditorial de la formation, écrit par OTF'],
			'content' => [self::KEEP, 'contenu éditorial de la formation, écrit par OTF'],
			'media' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'url' => [self::KEEP, 'adresse publique d\'une vidéo ou d\'un bouton, contenu éditorial de la formation'],
			'settings' => [self::KEEP, 'grille d\'un tableau, surtitre d\'une section ou étiquettes, contenu éditorial de la formation'],
		],
		'communication\\DiscordAccess' => [
			'discordId' => [self::CLEAR, 'identifiant d\'une personne sur Discord'],
			'refreshToken' => [self::SECRET, 'jeton Discord'],
		],
		'communication\\DiscordInvitation' => [
			'key' => [self::SECRET, 'clé d\'invitation'],
		],
		'communication\\Note' => [
			'title' => [self::KEEP, 'note éditoriale écrite par OTF'],
			'content' => [self::KEEP, 'note éditoriale écrite par OTF'],
			'buttonLabel' => [self::KEEP, 'note éditoriale écrite par OTF'],
			'buttonPath' => [self::KEEP, 'chemin dans le produit'],
			'structureTypes' => [self::KEEP, 'ciblage de la note'],
			'features' => [self::KEEP, 'ciblage de la note'],
		],
		'communication\\WebinaireSubscription' => [
			'firstName' => [self::FAKE, 'prénom d\'un inscrit', 'Prénom'],
			'lastName' => [self::FAKE, 'nom d\'un inscrit', 'Nom'],
			'email' => [self::FAKE_EMAIL, 'adresse d\'un inscrit'],
		],
		'company\\AccountingHelp' => [
			'softwareOther' => [self::KEEP, 'nom d\'un logiciel comptable'],
		],
		'company\\AmortizationDuration' => [
			'class' => [self::KEEP, 'numéro de compte du référentiel'],
		],
		'company\\DictionaryItem' => [
			'siren' => [self::CLEAR, 'SIREN d\'un fournisseur, qui désigne une personne en entreprise individuelle'],
			'itemKey' => [self::KEEP, 'clé dérivée du libellé de ligne'],
			'itemName' => [self::KEEP, 'libellé de ligne de facture'],
			'sellerName' => [self::FAKE, 'nom d\'un fournisseur', 'Fournisseur'],
			'natureMistral' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'natureAnthropic' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'natureShared' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'natureDecided' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'natureRemoved' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
		],
		'company\\DictionaryItemHistory' => [
			'natureBefore' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'natureAfter' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'accountNumberProposed' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
		],
		'company\\DictionaryItemStructure' => [
			'nature' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
		],
		'company\\GenericAccount' => [
			'class' => [self::KEEP, 'plan comptable de référence'],
			'description' => [self::KEEP, 'plan comptable de référence'],
			'vatClass' => [self::KEEP, 'plan comptable de référence'],
		],
		'company\\JournalCode' => [
			'name' => [self::KEEP, 'journal de référence'],
			'code' => [self::KEEP, 'journal de référence'],
		],
		'company\\NatureAccount' => [
			'nature' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
		],
		'company\\ProofRegister' => [
			'readCurrency' => [self::KEEP, 'code de devise'],
			'readNumber' => [self::KEEP, 'numéro de facture'],
			'readSiren' => [self::CLEAR, 'SIREN lu, qui désigne une personne en entreprise individuelle'],
			'currency' => [self::KEEP, 'code de devise'],
			'number' => [self::KEEP, 'numéro de facture'],
			'siren' => [self::CLEAR, 'SIREN lu, qui désigne une personne en entreprise individuelle'],
			'thirdPartyName' => [self::CLEAR, 'nom d\'un tiers lu sur la pièce'],
		],
		'company\\TransmissionRegister' => [
			'rejectCode' => [self::KEEP, 'code de rejet des impôts, pris dans leur table des codes'],
			'paymentRejectCode' => [self::KEEP, 'code de rejet du prélèvement, pris dans la table des codes des impôts'],
		],
		'dashboard\\Preference' => [
			'name' => [self::KEEP, 'nom d\'une pièce de l\'accueil, pris dans l\'inventaire des packages (Preference::properties()) : un code du logiciel, qui ne dit rien du membre qui l\'a réglée'],
		],
		'gallery\\Photo' => [
			'hash' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'title' => [self::CLEAR, 'légende libre'],
		],
		'game\\Growing' => [
			'fqn' => [self::KEEP, 'code de référentiel écrit par OTF'],
			'name' => [self::KEEP, 'intitulé du jeu'],
		],
		'game\\History' => [
			'message' => [self::CLEAR, 'message qui nomme un joueur'],
		],
		'game\\Player' => [
			'name' => [self::FAKE, 'nom d\'un joueur', 'Joueur'],
			'code' => [self::CLEAR, 'code personnel d\'un joueur'],
		],
		'learning\\Attachment' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'hash' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'learning\\Chapter' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'summary' => [self::KEEP, 'contenu de formation écrit par OTF'],
		],
		'learning\\Choice' => [
			'label' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'image' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'learning\\Comment' => [
			'text' => [self::CLEAR, 'commentaire d\'un apprenant'],
		],
		'learning\\Course' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'summary' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'description' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'audience' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'prerequisites' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'objectives' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'access' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'accessibility' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'recognition' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'externalUrl' => [self::KEEP, 'page publique de la formation sur le site de l\'organisme'],
			'catalogReason' => [self::KEEP, 'motif d\'une décision sur le catalogue public, écrit par l\'équipe OTF'],
			'fundingNote' => [self::KEEP, 'conditions de financement d\'une formation, publiées par le réseau'],
			'vignette' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'learning\\Date' => [
			'place' => [self::KEEP, 'lieu de session de formation publié par OTF'],
			'link' => [self::KEEP, 'lien de session de formation publié par OTF'],
			'externalUrl' => [self::KEEP, 'page publique de la date sur le site de l\'organisme'],
			'fundingNote' => [self::KEEP, 'conditions de financement d\'une date, publiées par le réseau'],
		],
		'learning\\DateSequence' => [
			'place' => [self::KEEP, 'lieu d\'une séance de formation, publié par le réseau comme celui de sa date'],
		],
		'learning\\DateSheet' => [
			'name' => [self::CLEAR, 'nom du fichier d\'une feuille signée, qui peut nommer une personne'],
			'hash' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'learning\\DateEnrollment' => [
			'structureName' => [self::FAKE, 'nom de la structure saisi sur la page publique', 'Structure'],
			'firstName' => [self::FAKE, 'prénom saisi sur la page publique', 'Prénom'],
			'lastName' => [self::FAKE, 'nom saisi sur la page publique', 'Nom'],
			'email' => [self::FAKE_EMAIL, 'adresse saisie sur la page publique'],
			'token' => [self::SECRET, 'jeton qui ouvre l\'inscription depuis l\'e-mail, pour la relire et l\'annuler'],
		],
		'learning\\Faq' => [
			'question' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'answer' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'symptoms' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'steps' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'notes' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'demoPage' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'demoPath' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'keywords' => [self::KEEP, 'contenu de FAQ écrit par la structure pour être publié, sans donnée personnelle'],
			'discordUrl' => [self::KEEP, 'adresse d\'une discussion publique, publiée avec la question'],
		],
		'learning\\Lesson' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'objective' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'deliverable' => [self::KEEP, 'contenu de formation écrit par OTF'],
		],
		'learning\\Level' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
		],
		'learning\\Phase' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'summary' => [self::KEEP, 'contenu de formation écrit par OTF'],
		],
		'learning\\Question' => [
			'statement' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'image' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'explanation' => [self::KEEP, 'contenu de formation écrit par OTF'],
		],
		'learning\\Review' => [
			'text' => [self::CLEAR, 'avis d\'un stagiaire, qui peut nommer une personne'],
			'reportReason' => [self::CLEAR, 'motif de signalement d\'un avis, qui peut nommer son auteur'],
			'hideReason' => [self::CLEAR, 'motif de masquage d\'un avis, adressé à son auteur'],
			'answer' => [self::CLEAR, 'réponse d\'un réseau à un avis, qui peut nommer son auteur'],
		],
		'learning\\Staff' => [
			'role' => [self::KEEP, 'rôle d\'un formateur dans une formation, présenté publiquement sur sa fiche'],
		],
		'learning\\Subtheme' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
		],
		'learning\\Supervisor' => [
			'name' => [self::KEEP, 'formateur présenté publiquement par OTF'],
			'role' => [self::KEEP, 'formateur présenté publiquement par OTF'],
			'description' => [self::KEEP, 'formateur présenté publiquement par OTF'],
			'email' => [self::FAKE_EMAIL, 'adresse personnelle d\'un formateur, publiée sur la fiche quand il est contact'],
		],
		'learning\\Theme' => [
			'name' => [self::KEEP, 'contenu de formation écrit par OTF'],
			'icon' => [self::KEEP, 'nom d\'icône'],
		],
		'loan\\Loan' => [
			'name' => [self::CLEAR, 'libellé libre d\'un emprunt, qui peut nommer un lieu ou une personne'],
			'accountLabel' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
		],
		'loan\\Reading' => [
			'filename' => [self::CLEAR, 'nom du fichier déposé, qui peut nommer la structure'],
			'hash' => [self::CLEAR, 'hash du contrat ou du tableau déposé, qui suffit à lire le fichier à l\'adresse publique du stockage'],
			'values' => [self::CLEAR, 'ce qui a été lu sur le document de la banque, nom de la banque et IBAN compris'],
		],
		'map\\Bed' => [
			'name' => [self::KEEP, 'nom de planche'],
		],
		'map\\Configuration' => [
			'rotationExclude' => [self::KEEP, 'identifiants d\'espèces'],
		],
		'map\\Decor' => [
			'kit' => [self::KEEP, 'clé du catalogue de décor'],
			'coordinates' => [self::CLEAR, 'position GPS, qui situe la structure'],
			'trace' => [self::CLEAR, 'points GPS d\'un tracé, qui situent la structure'],
		],
		'map\\DecorBuilding' => [
			'name' => [self::FAKE, 'nom saisi du bâtiment, qui peut nommer une personne (« Maison de Paul »)', 'Bâtiment'],
			'settings' => [self::KEEP, 'choix du catalogue de bâtiments (choices.json), sans texte libre'],
			'upload' => [self::CLEAR, 'modèle téléversé montré, dont la table est vidée : le bâtiment revient à sa composition'],
		],
		'map\\DecorLayer' => [
			'name' => [self::KEEP, 'nom de calque'],
		],
		'map\\Draw' => [
			'corner' => [self::CLEAR, 'position GPS, qui situe la structure'],
		],
		'map\\Plot' => [
			'name' => [self::KEEP, 'nom de parcelle'],
			'coordinates' => [self::CLEAR, 'position GPS, qui situe la structure'],
		],
		'map\\Zone' => [
			'name' => [self::KEEP, 'nom d\'îlot'],
			'coordinates' => [self::CLEAR, 'position GPS, qui situe la structure'],
		],
		'media\\Media' => [
			'hash' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'note\\Note' => [
			'title' => [self::FAKE, 'titre libre d\'une note de structure, qui peut nommer un salarié ou un client', 'Note'],
			'content' => [self::CLEAR, 'texte libre d\'une note, qui porte des prénoms et des téléphones comme les commentaires de série'],
		],
		'note\\Notebook' => [
			'name' => [self::FAKE, 'nom de carnet choisi par la structure, qui peut nommer une personne ou un client', 'Carnet'],
		],
		'note\\Target' => [
			'targetType' => [self::KEEP, 'code du type d\'objet rattaché'],
		],
		'planning\\Due' => [
			'source' => [self::KEEP, 'nom de package'],
			'type' => [self::KEEP, 'type écrit par OTF'],
		],
		'planning\\Reminder' => [
			'text' => [self::CLEAR, 'texte libre du pense-bête, qui porte des téléphones et des prénoms comme les interventions « 📌 » qu\'il remplace'],
		],
		'plant\\Criterion' => [
			'name' => [self::KEEP, 'référentiel'],
		],
		'plant\\Family' => [
			'fqn' => [self::KEEP, 'code de référentiel écrit par OTF'],
			'name' => [self::KEEP, 'référentiel'],
		],
		'plant\\Plant' => [
			'name' => [self::KEEP, 'nom d\'espèce'],
			'fqn' => [self::KEEP, 'code du référentiel'],
			'aliases' => [self::KEEP, 'noms d\'espèce'],
			'tagsAccepted' => [self::KEEP, 'étiquettes'],
			'vignette' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'criteria' => [self::KEEP, 'critères de variété'],
		],
		'plant\\Size' => [
			'name' => [self::KEEP, 'calibre'],
			'comment' => [self::KEEP, 'description de calibre'],
		],
		'plant\\Tag' => [
			'fqn' => [self::KEEP, 'code de référentiel écrit par OTF'],
			'name' => [self::KEEP, 'référentiel'],
		],
		'plant\\Variety' => [
			'fqn' => [self::KEEP, 'code formé sur le nom de la variété'],
			'name' => [self::KEEP, 'nom de variété'],
			'tags' => [self::KEEP, 'étiquettes'],
			'ratingComment' => [self::KEEP, 'appréciation d\'une variété'],
		],
		'plant\\VarietyName' => [
			'name' => [self::KEEP, 'référentiel'],
			'searchKey' => [self::KEEP, 'référentiel'],
			'sortKey' => [self::KEEP, 'référentiel'],
		],
		'product\\Category' => [
			'name' => [self::KEEP, 'catégorie de produit'],
			'icon' => [self::KEEP, 'nom d\'icône'],
		],
		'product\\Configuration' => [
			'stockNotes' => [self::CLEAR, 'texte libre'],
		],
		'product\\Product' => [
			'name' => [self::KEEP, 'nom de produit'],
			'additional' => [self::CLEAR, 'complément écrit par la structure, qui peut la nommer'],
			'description' => [self::CLEAR, 'description écrite par la structure, qui peut la nommer'],
			'vignette' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'reference' => [self::KEEP, 'référence de produit'],
			'variety' => [self::KEEP, 'variété'],
			'packaging' => [self::KEEP, 'conditionnement'],
			'allergens' => [self::KEEP, 'allergènes'],
			'ingredients' => [self::KEEP, 'ingrédients'],
			'origin' => [self::KEEP, 'origine'],
			'privatePriceSteps' => [self::KEEP, 'paliers de prix'],
			'proPriceSteps' => [self::KEEP, 'paliers de prix'],
			'privateAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'proAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
		],
		'product\\Stock' => [
			'comment' => [self::CLEAR, 'texte libre'],
		],
		'product\\Unit' => [
			'fqn' => [self::KEEP, 'code d\'unité, 20 valeurs distinctes sur les unités des structures'],
			'singular' => [self::KEEP, 'nom d\'unité'],
			'plural' => [self::KEEP, 'nom d\'unité'],
			'short' => [self::KEEP, 'nom d\'unité'],
		],
		'sequence\\Flow' => [
			'methods' => [self::KEEP, 'identifiants de méthodes'],
			'tools' => [self::KEEP, 'identifiants d\'outils'],
			'description' => [self::CLEAR, 'texte libre, recopié dans les interventions de la série'],
			'fertilizer' => [self::KEEP, 'fertilisation'],
			'products' => [self::KEEP, 'identifiants de produits de traitement et d\'intrants, avec leur dose'],
		],
		'sequence\\Sequence' => [
			'name' => [self::KEEP, 'itinéraire de culture'],
			'description' => [self::KEEP, 'itinéraire de culture'],
			'comment' => [self::KEEP, 'itinéraire de culture'],
		],
		'series\\Comment' => [
			'text' => [self::CLEAR, 'note libre, qui peut nommer un salarié'],
		],
		'series\\Cultivation' => [
			'harvestedByUnit' => [self::KEEP, 'quantités récoltées'],
			'harvestMonths' => [self::KEEP, 'calendrier de récolte'],
			'harvestWeeks' => [self::KEEP, 'calendrier de récolte'],
			'harvestMonthsExpected' => [self::KEEP, 'calendrier de récolte'],
			'harvestWeeksExpected' => [self::KEEP, 'calendrier de récolte'],
		],
		'series\\Repeat' => [
			'methods' => [self::KEEP, 'identifiants de méthodes'],
			'tools' => [self::KEEP, 'identifiants d\'outils'],
			'description' => [self::CLEAR, 'texte libre, recopié dans les interventions qu\'il répète'],
			'fertilizer' => [self::KEEP, 'fertilisation'],
			'products' => [self::KEEP, 'identifiants de produits de traitement et d\'intrants, avec leur dose'],
			'discrete' => [self::KEEP, 'réglage de répétition'],
		],
		'series\\Series' => [
			'name' => [self::KEEP, 'nom de série'],
			'comment' => [self::CLEAR, 'note libre, qui peut nommer un salarié'],
		],
		'series\\Task' => [
			'methods' => [self::KEEP, 'identifiants de méthodes'],
			'tools' => [self::KEEP, 'identifiants d\'outils'],
			'description' => [self::CLEAR, 'texte libre, qui porte des téléphones, des adresses et des prénoms de salariés'],
			'fertilizer' => [self::KEEP, 'fertilisation'],
			'products' => [self::KEEP, 'identifiants de produits de traitement et d\'intrants, avec leur dose'],
			'treatment' => [self::KEEP, 'dose et volume de bouillie'],
			'plannedUsers' => [self::KEEP, 'identifiants d\'utilisateurs'],
		],
		'structure\\Community' => [
			'fqn' => [self::FAKE_SLUG, 'adresse de la page du groupe, formée sur son nom', 'groupe'],
			'rules' => [self::KEEP, 'règles d\'une communauté, publiées à ses membres'],
			'quotationHelp' => [self::KEEP, 'aide de la mercuriale'],
			'quotationRegion' => [self::KEEP, 'région de la mercuriale'],
			'learningConditions' => [self::KEEP, 'conditions de formation'],
			'customTitleFont' => [self::KEEP, 'nom de police'],
			'customFont' => [self::KEEP, 'nom de police'],
			'customDesign' => [self::KEEP, 'nom de gabarit, liste fermée'],
		],
		'structure\\CommunityRedirect' => [
			'fqn' => [self::FAKE_SLUG, 'ancienne adresse d\'un groupe, formée sur son nom ; libellé distinct de celui des groupes, qui partagent les mêmes numéros', 'ancien-groupe'],
		],
		'structure\\Configuration' => [
			'vatNumber' => [self::CLEAR, 'numéro de TVA de la structure'],
			'organicCertifier' => [self::KEEP, 'organisme certificateur'],
			'emailBanner' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'emailFooter' => [self::CLEAR, 'signature d\'e-mail de la structure'],
		],
		'structure\\ConfigurationHistory' => [
			'field' => [self::KEEP, 'nom du réglage modifié'],
			'value' => [self::CLEAR, 'valeur d\'un réglage, qui peut être une mention avec IBAN'],
		],
		'structure\\Feature' => [
			'hasToolbox' => [self::KEEP, 'réglage'],
		],
		'structure\\Invite' => [
			'email' => [self::FAKE_EMAIL, 'adresse d\'un invité'],
			'key' => [self::SECRET, 'clé d\'invitation'],
		],
		'structure\\Member' => [
			'inviteKey' => [self::SECRET, 'clé d\'invitation'],
			'viewSellingPreparing' => [self::KEEP, 'réglage d\'affichage'],
			'viewPlanningOrder' => [self::KEEP, 'réglage d\'affichage, liste d\'ids d\'action de la structure'],
			'viewDashboardActions' => [self::KEEP, 'réglage d\'affichage, liste d\'ids d\'action de la structure'],
			'viewQuotationRegion' => [self::KEEP, 'réglage d\'affichage'],
			'helpers' => [self::KEEP, 'réglage d\'affichage'],
			'notifications' => [self::KEEP, 'réglage de notification'],
		],
		'structure\\Production' => [
			'fqn' => [self::KEEP, 'code de production'],
			'description' => [self::CLEAR, 'texte libre'],
		],
		'structure\\Role' => [
			'fqn' => [self::KEEP, 'code de rôle, 8 valeurs distinctes sur les rôles des structures'],
			'name' => [self::KEEP, 'intitulé de rôle'],
		],
		'structure\\Section' => [
			'name' => [self::KEEP, 'intitulé de section'],
			'users' => [self::KEEP, 'identifiants d\'utilisateurs'],
		],
		'structure\\Structure' => [
			'name' => [self::FAKE, 'nom de la structure', 'Structure'],
			'legalName' => [self::CLEAR, 'raison sociale'],
			'legalEmail' => [self::FAKE_EMAIL, 'adresse de la structure'],
			'siret' => [self::CLEAR, 'SIRET'],
			'legalStreet1' => [self::CLEAR, 'adresse de la structure'],
			'legalStreet2' => [self::CLEAR, 'adresse de la structure'],
			'legalPostcode' => [self::CLEAR, 'adresse de la structure'],
			'legalCity' => [self::CLEAR, 'adresse de la structure'],
			'url' => [self::CLEAR, 'site de la structure'],
			'description' => [self::CLEAR, 'présentation de la structure'],
			'logo' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'banner' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'interstitials' => [self::KEEP, 'écrans déjà vus'],
		],
		'structure\\Survey' => [
			'productionMissing' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
			'commercialisationMissing' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
			'accountingMissing' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
			'useful' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
			'missing' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
			'otherSoftware' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
			'comment' => [self::CLEAR, 'réponse libre à l\'enquête des adhérents'],
		],
		'structure\\System' => [
			'place' => [self::CLEAR, 'lieu de la structure'],
			'placeLngLat' => [self::CLEAR, 'position GPS de la structure'],
			'placeCity' => [self::CLEAR, 'commune du lieu de production, qui situe la structure comme sa position'],
			'placeDepartment' => [self::KEEP, 'département du lieu de production, le flou que la ferme école publie déjà'],
			'placeEpci' => [self::CLEAR, 'intercommunalité du lieu de production, qui situe la structure à quelques communes près'],
		],
		'structure\\Terms' => [
			'ip' => [self::CLEAR, 'adresse IP de la personne qui a accepté les CGV'],
		],
		'structure\\Tip' => [
			'list' => [self::KEEP, 'astuces déjà vues'],
		],
		'subscription\\Statement' => [
			'stripePayoutId' => [self::CLEAR, 'virement de production depuis un compte de reversement'],
		],
		'subscription\\StatementLine' => [
			'stripeDebitId' => [self::CLEAR, 'débit de production sur un compte de reversement'],
		],
		'toolbox\\CompositionFormula' => [
			'name' => [self::KEEP, 'intitulé de formule'],
		],
		'toolbox\\CompositionPlan' => [
			'weeks' => [self::KEEP, 'calendrier'],
		],
		'toolbox\\CompositionSpecies' => [
			'name' => [self::KEEP, 'intitulé d\'une variante de panier, comme celui d\'une formule'],
			'windowWeeks' => [self::KEEP, 'calendrier'],
		],
		'toolbox\\Equipment' => [
			'registration' => [self::CLEAR, 'immatriculation d\'un véhicule réel'],
		],
		'toolbox\\FertilizationMaterial' => [
			'name' => [self::KEEP, 'matière du référentiel de fertilisation écrit par OTF'],
		],
		'toolbox\\FertilizationSource' => [
			'name' => [self::KEEP, 'libellé d\'un document publié, cité par le référentiel de fertilisation écrit par OTF'],
			'url' => [self::KEEP, 'adresse d\'un document publié, cité par le référentiel de fertilisation écrit par OTF'],
		],
		'toolbox\\FertilizationYield' => [
			'name' => [self::KEEP, 'rendement de référence du référentiel de fertilisation écrit par OTF'],
		],
		'toolbox\\Maintenance' => [
			'name' => [self::KEEP, 'intitulé d\'entretien'],
		],
		'toolbox\\MaintenanceDone' => [
			'name' => [self::KEEP, 'libellé d\'une intervention sans entretien, qui entre dans l\'historique sous ce nom'],
			'comment' => [self::CLEAR, 'note libre sur un entretien fait'],
			'photo' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'toolbox\\WaterMeter' => [
			'name' => [self::KEEP, 'nom de compteur d\'eau'],
		],
		'training\\Farm' => [
			'content' => [self::KEEP, 'nom d\'un exercice de formation écrit par OTF'],
		],
		'user\\Country' => [
			'code' => [self::KEEP, 'référentiel des pays'],
			'name' => [self::KEEP, 'référentiel des pays'],
			'center' => [self::KEEP, 'référentiel des pays'],
		],
		'user\\Tos' => [
			'ip' => [self::CLEAR, 'adresse IP d\'une personne'],
		],
		'user\\User' => [
			'firstName' => [self::FAKE, 'prénom', 'Prénom'],
			'lastName' => [self::FAKE, 'nom', 'Nom'],
			'legalName' => [self::CLEAR, 'raison sociale'],
			'email' => [self::FAKE_EMAIL, 'adresse de connexion'],
			'phone' => [self::FAKE_PHONE, 'téléphone'],
			'siret' => [self::CLEAR, 'SIRET'],
			'invoiceStreet1' => [self::CLEAR, 'adresse'],
			'invoiceStreet2' => [self::CLEAR, 'adresse'],
			'invoicePostcode' => [self::CLEAR, 'adresse'],
			'invoiceCity' => [self::CLEAR, 'adresse'],
			'deliveryStreet1' => [self::CLEAR, 'adresse'],
			'deliveryStreet2' => [self::CLEAR, 'adresse'],
			'deliveryPostcode' => [self::CLEAR, 'adresse'],
			'deliveryCity' => [self::CLEAR, 'adresse'],
			'vignette' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'user\\UserAuth' => [
			'login' => [self::FAKE_EMAIL, 'identifiant de connexion, une adresse e-mail'],
			'loginArchive' => [self::CLEAR, 'ancien identifiant de connexion'],
			'password' => [self::SECRET, 'mot de passe'],
			'passwordHash' => [self::SECRET, 'jeton de réinitialisation du mot de passe'],
		],
		'user\\UserAvatar' => [
			'composition' => [self::KEEP, 'choix du catalogue fermé de l\'éditeur d\'avatar'],
			'accessory' => [self::KEEP, 'clé du catalogue des accessoires'],
			'companion' => [self::KEEP, 'clé d\'un animal des ambiances'],
			'photo' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
		],
		'website\\Design' => [
			'fqn' => [self::KEEP, 'code de thème écrit par OTF'],
			'name' => [self::KEEP, 'gabarit de site'],
		],
		'website\\Menu' => [
			'url' => [self::KEEP, 'chemin de page du site'],
			'label' => [self::KEEP, 'intitulé de menu'],
		],
		'website\\News' => [
			'title' => [self::KEEP, 'titre d\'actualité'],
			'content' => [self::CLEAR, 'actualité écrite par la structure, qui la nomme'],
		],
		'website\\Template' => [
			'fqn' => [self::KEEP, 'code de gabarit écrit par OTF'],
			'name' => [self::KEEP, 'gabarit écrit par OTF'],
			'description' => [self::KEEP, 'gabarit écrit par OTF'],
			'defaultUrl' => [self::KEEP, 'gabarit écrit par OTF'],
			'defaultLabel' => [self::KEEP, 'gabarit écrit par OTF'],
			'defaultTitle' => [self::KEEP, 'gabarit écrit par OTF'],
			'defaultDescription' => [self::KEEP, 'gabarit écrit par OTF'],
			'defaultContent' => [self::KEEP, 'gabarit écrit par OTF'],
		],
		'website\\Webpage' => [
			'title' => [self::KEEP, 'titre de page'],
			'banner' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'url' => [self::KEEP, 'chemin de page'],
			'description' => [self::CLEAR, 'présentation écrite par la structure'],
			'content' => [self::CLEAR, 'page écrite par la structure, qui la nomme'],
		],
		'website\\Website' => [
			'internalDomain' => [self::FAKE_SLUG, 'sous-domaine, formé sur le nom de la structure', 'site'],
			'domain' => [self::CLEAR, 'domaine de la structure'],
			'logo' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'favicon' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'banner' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'name' => [self::FAKE, 'nom du site, qui nomme la structure', 'Site'],
			'footer' => [self::CLEAR, 'pied de page, qui porte l\'adresse'],
			'description' => [self::CLEAR, 'présentation de la structure'],
			'customTitleFont' => [self::KEEP, 'nom de police'],
			'customFont' => [self::KEEP, 'nom de police'],
		],
		'work\\Action' => [
			'name' => [self::KEEP, 'intitulé d\'intervention'],
			'fqn' => [self::KEEP, 'code d\'intervention'],
			'short' => [self::KEEP, 'abréviation'],
			'categories' => [self::KEEP, 'identifiants de catégories'],
		],
		'work\\Category' => [
			'name' => [self::KEEP, 'intitulé de catégorie'],
			'fqn' => [self::KEEP, 'code de catégorie'],
		],
		'work\\Method' => [
			'name' => [self::KEEP, 'intitulé de méthode'],
		],
		'work\\PhytoProduct' => [
			'amm' => [self::KEEP, 'numéro d\'autorisation du catalogue E-Phy de l\'ANSES'],
			'name' => [self::KEEP, 'nom commercial du catalogue E-Phy'],
			'otherNames' => [self::KEEP, 'noms commerciaux du catalogue E-Phy'],
			'functions' => [self::KEEP, 'fonctions du catalogue E-Phy'],
			'formulation' => [self::KEEP, 'formulation du catalogue E-Phy'],
			'substances' => [self::KEEP, 'substances actives du catalogue E-Phy'],
		],
		'work\\PhytoUsage' => [
			'crop' => [self::KEEP, 'culture du catalogue E-Phy'],
			'treatment' => [self::KEEP, 'type de traitement du catalogue E-Phy'],
			'target' => [self::KEEP, 'cible du catalogue E-Phy'],
			'doseUnit' => [self::KEEP, 'unité de dose du catalogue E-Phy'],
			'plants' => [self::KEEP, 'identifiants des espèces du référentiel partagé'],
		],
		'work\\Supplier' => [
			'name' => [self::KEEP, 'nom de fournisseur de semences ou de matériel'],
		],
		'work\\Tool' => [
			'name' => [self::KEEP, 'intitulé d\'outil'],
			'vignette' => [self::KEEP, 'empreinte d\'un fichier que l\'export n\'emporte pas'],
			'routineName' => [self::KEEP, 'code de routine'],
			'routineValue' => [self::KEEP, 'réglage de routine'],
			'comment' => [self::CLEAR, 'texte libre'],
		],
		'account\\Account' => [
			'class' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'description' => [self::REFERENCE, 'libellé du compte : celui du plan de référence, ou celui que la structure a écrit, qui peut nommer une personne', 'Compte'],
			'vatClass' => [self::KEEP, 'code de classe de TVA'],
		],
		'account\\AncillaryMove' => [
			'parentClass' => [self::KEEP, 'numéro de compte, chiffres seuls'],
			'operations' => [self::KEEP, 'identifiants seuls, en json, qui ne se réécrit pas valeur par valeur'],
			'cashes' => [self::KEEP, 'identifiants et empreintes seuls, en json, qui ne se réécrit pas valeur par valeur'],
			'products' => [self::KEEP, 'identifiants et noms de propriétés seuls, en json, qui ne se réécrit pas valeur par valeur'],
		],
		'account\\Configuration' => [
			'profileAccountNumber' => [self::KEEP, 'racines de compte de vente par profil de produit, en json, chiffres seuls sur les lignes de la dev ; le json ne se réécrit pas valeur par valeur'],
			'vatRof' => [self::KEEP, 'code d\'obligation de TVA (TVA1 à TVA999), sans rien d\'une personne'],
		],
		'asset\\Asset' => [
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'accountLabel' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'description' => [self::KEEP, 'désigne un bien (« Tunnel pépi », « Clôture »), jamais une personne'],
		],
		'asset\\Depreciation' => [
			'reason' => [self::CLEAR, 'texte libre'],
		],
		'asset\\Import' => [
			'name' => [self::CLEAR, 'nom du fichier déposé, qui peut nommer la structure'],
			'content' => [self::CLEAR, 'document du cabinet comptable'],
		],
		'asset\\Revision' => [
			'reason' => [self::CLEAR, 'texte libre'],
		],
		'bank\\Annotation' => [
			'text' => [self::CLEAR, 'note libre sur une opération bancaire'],
		],
		'bank\\BankAccount' => [
			'bankId' => [self::KEEP, 'code banque'],
			'accountId' => [self::CLEAR, 'numéro de compte bancaire'],
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'iban' => [self::CLEAR, 'IBAN de la structure'],
			'bic' => [self::KEEP, 'code de la banque, qui n\'identifie pas le titulaire'],
			'description' => [self::KEEP, 'libellé du compte (« Compte courant »)'],
			'enableBankingSessionId' => [self::SECRET, 'session bancaire vivante'],
			'enableBankingAccountId' => [self::SECRET, 'compte bancaire connecté'],
			'enableBankingIban' => [self::SECRET, 'IBAN de la connexion bancaire'],
			'aspspName' => [self::KEEP, 'nom de la banque'],
			'enableBankingBalanceType' => [self::KEEP, 'code de type de solde'],
		],
		'bank\\Cashflow' => [
			'fitid' => [self::KEEP, 'identifiant technique de l\'opération dans le relevé'],
			'name' => [self::FAKE, 'libellé bancaire, qui nomme le payeur ou le bénéficiaire', 'Opération'],
			'memo' => [self::CLEAR, 'complément de libellé bancaire'],
			'thirdPartyIban' => [self::CLEAR, 'IBAN du tiers'],
			'transactionCode' => [self::KEEP, 'code d\'opération bancaire'],
			'fingerprint' => [self::KEEP, 'empreinte de dédoublonnage'],
			'proofReason' => [self::KEEP, 'motif d\'un justificatif manquant, choisi dans une liste'],
			'proofReasonText' => [self::CLEAR, 'raison tapée d\'un justificatif manquant, qui peut nommer un tiers'],
		],
		'bank\\Import' => [
			'filename' => [self::CLEAR, 'nom du fichier de relevé'],
			'result' => [self::KEEP, 'compteurs de l\'import'],
			'bankId' => [self::KEEP, 'code banque'],
			'accountId' => [self::CLEAR, 'numéro de compte bancaire'],
			'ofx' => [self::CLEAR, 'relevé bancaire complet'],
			'raw' => [self::CLEAR, 'relevé bancaire complet'],
			'refusal' => [self::KEEP, 'motif du refus d\'un relevé PDF'],
			'hash' => [self::CLEAR, 'hash du relevé PDF déposé, qui suffit à lire le fichier à l\'adresse publique du stockage'],
		],
		'bank\\Reconciliation' => [
			'accountingHash' => [self::KEEP, 'empreinte technique'],
		],
		'cash\\Archive' => [
			'sha256' => [self::KEEP, 'empreinte technique'],
			'csv' => [self::CLEAR, 'export complet d\'un cahier de caisse'],
		],
		'cash\\Cash' => [
			'description' => [self::CLEAR, 'libellé saisi, qui peut nommer un client'],
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'accountingHash' => [self::KEEP, 'empreinte technique'],
			'proofReason' => [self::KEEP, 'motif d\'un justificatif manquant, choisi dans une liste'],
			'proofReasonText' => [self::CLEAR, 'raison tapée d\'un justificatif manquant, qui peut nommer un tiers'],
		],
		'cash\\Import' => [
			'result' => [self::KEEP, 'compteurs de l\'import'],
			'raw' => [self::CLEAR, 'réponse complète du prestataire de paiement'],
			'rawPayouts' => [self::CLEAR, 'réponse complète du prestataire de paiement'],
		],
		'cash\\Register' => [
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'bankAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'feeAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'selectedMonth' => [self::KEEP, 'mois affiché'],
		],
		'cash\\Transaction' => [
			'paymentExternalId' => [self::KEEP, 'identifiant de transaction du prestataire, sans usage hors de son compte'],
			'externalId' => [self::KEEP, 'identifiant de transaction du prestataire, sans usage hors de son compte'],
			'balanceId' => [self::KEEP, 'identifiant de mouvement du prestataire'],
			'description' => [self::CLEAR, 'libellé du prestataire, qui peut nommer un client'],
			'dispute' => [self::KEEP, 'identifiant de litige du prestataire, sans usage hors de son compte'],
		],
		'edi\\Mandate' => [
			'structureName' => [self::FAKE, 'nom de la structure au moment du mandat, qui peut nommer une personne', 'Structure'],
			'siren' => [self::CLEAR, 'SIREN de la structure, qui désigne une personne en entreprise individuelle'],
			'ip' => [self::CLEAR, 'adresse IP de la personne qui a donné le mandat'],
		],
		'edi\\Transmission' => [
			'rof' => [self::KEEP, 'code d\'obligation de TVA, sans rien d\'une personne'],
			'reference' => [self::KEEP, 'référence numérique de l\'interchange, faite de l\'année et des identifiants'],
			'message' => [self::CLEAR, 'interchange envoyé, qui porte le SIREN, l\'IBAN et le nom du demandeur'],
			'values' => [self::KEEP, 'montants par case, copie de vat\\Declaration.data'],
			'payments' => [self::CLEAR, 'IBAN et BIC des comptes qui paient'],
			'operations' => [self::KEEP, 'identifiants des écritures rattachées par l\'envoi'],
			'report' => [self::CLEAR, 'compte rendu reçu, qui recopie les références de l\'envoi'],
			'rejectCode' => [self::KEEP, 'code de rejet de la DGFiP'],
			'paymentRejectCode' => [self::KEEP, 'code de rejet du paiement'],
			'notifiedEmail' => [self::FAKE_EMAIL, 'adresse à laquelle le compte rendu a été envoyé'],
		],
		'financialYear\\ArchiveDocument' => [
			'sha256' => [self::KEEP, 'empreinte technique'],
			'type' => [self::KEEP, 'type de document'],
			'csv' => [self::CLEAR, 'export comptable complet'],
		],
		'financialYear\\Check' => [
			'checkKey' => [self::KEEP, 'clé du point de clôture'],
			'signature' => [self::KEEP, 'empreinte des montants contrôlés'],
		],
		'financialYear\\Document' => [
			'type' => [self::KEEP, 'type de document'],
		],
		'financialYear\\File' => [
			'type' => [self::KEEP, 'type de document'],
			'reference' => [self::KEEP, 'référence technique'],
			'name' => [self::CLEAR, 'nom du fichier déposé'],
			'content' => [self::CLEAR, 'document comptable de la structure'],
		],
		'financialYear\\Import' => [
			'filename' => [self::CLEAR, 'nom du fichier déposé'],
			'delimiter' => [self::KEEP, 'séparateur de colonnes'],
			'content' => [self::CLEAR, 'FEC complet'],
			'rules' => [self::KEEP, 'règles d\'import'],
		],
		'financialYear\\Inventory' => [
			'accountLabel' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'variationAccountLabel' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'financialYear\\Pdf' => [
			'name' => [self::CLEAR, 'nom de fichier, qui porte le nom de la structure'],
			'type' => [self::KEEP, 'type de document'],
		],
		'financialYear\\PdfContent' => [
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'financialYear\\Reconciliation' => [
			'checkKey' => [self::KEEP, 'clé du point de clôture'],
			'accountLabel' => [self::ACCOUNT, 'numéro de compte auxiliaire, formé sur le nom du tiers (« 401 » suivi du fournisseur)'],
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'financialYear\\ResultAllocation' => [
			'accountLabel' => [self::ACCOUNT, 'numéro de compte auxiliaire, formé sur le nom du tiers (« 401 » suivi du fournisseur)'],
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'financialYear\\Review' => [
			'questionKey' => [self::KEEP, 'clé de la question de revue'],
			'comment' => [self::CLEAR, 'texte libre, qui peut nommer un tiers ou un associé'],
		],
		'invoicing\\Event' => [
			'statusCode' => [self::KEEP, 'code de statut de la plateforme'],
			'statusText' => [self::KEEP, 'libellé de statut de la plateforme'],
			'reason' => [self::CLEAR, 'texte libre de l\'émetteur'],
			'details' => [self::CLEAR, 'réponse complète de la plateforme'],
			'attachmentHash' => [self::KEEP, 'empreinte technique'],
			'attachmentFilename' => [self::CLEAR, 'nom de fichier'],
		],
		'invoicing\\Invoicing' => [
			'processingRule' => [self::KEEP, 'code de traitement'],
			'number' => [self::KEEP, 'numéro de facture'],
			'comment' => [self::CLEAR, 'texte libre'],
			'deposits' => [self::KEEP, 'références d\'acomptes'],
			'depositsReference' => [self::KEEP, 'références d\'acomptes'],
			'purchaseOrderReference' => [self::KEEP, 'référence de commande'],
			'notes' => [self::CLEAR, 'mentions libres de la facture'],
			'buyerElectronicAddress' => [self::CLEAR, 'adresse électronique, dérivée du SIREN de l\'acheteur'],
			'buyerReference' => [self::KEEP, 'référence acheteur'],
			'sellerElectronicAddress' => [self::CLEAR, 'adresse électronique, dérivée du SIREN du vendeur'],
			'depositOfReference' => [self::KEEP, 'référence de facture'],
			'allowances' => [self::KEEP, 'remises'],
			'charges' => [self::KEEP, 'frais'],
			'vatByRate' => [self::KEEP, 'ventilation de TVA'],
			'vatDueDateCode' => [self::KEEP, 'code de TVA'],
			'pdpStatus' => [self::KEEP, 'statut'],
			'pdpPendingStatus' => [self::KEEP, 'statut'],
			'accountingHash' => [self::KEEP, 'empreinte technique'],
			'raw' => [self::CLEAR, 'facture électronique complète, avec les coordonnées des parties'],
			'rawUbl' => [self::CLEAR, 'facture électronique complète, avec les coordonnées des parties'],
		],
		'invoicing\\Line' => [
			'identifier' => [self::KEEP, 'identifiant de ligne'],
			'name' => [self::KEEP, 'libellé de ligne de facture'],
			'description' => [self::CLEAR, 'description libre de ligne'],
			'sellerIdentifier' => [self::KEEP, 'référence produit du vendeur'],
			'buyerIdentifier' => [self::KEEP, 'référence produit de l\'acheteur'],
			'buyerOrderReference' => [self::KEEP, 'référence de commande'],
			'buyerAccountingReference' => [self::KEEP, 'référence comptable de l\'acheteur'],
			'unitCodeQuantity' => [self::KEEP, 'code d\'unité'],
			'allowances' => [self::KEEP, 'remises'],
			'attributes' => [self::KEEP, 'attributs de produit'],
			'vatCode' => [self::KEEP, 'code de TVA'],
			'vatDueDateCode' => [self::KEEP, 'code de TVA'],
			'vatExemptionReason' => [self::KEEP, 'mention d\'exonération normalisée'],
			'numberCode' => [self::KEEP, 'code'],
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'proposedAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'dictionaryNature' => [self::KEEP, 'code de nature, pris dans la liste de NatureLib'],
		],
		'invoicing\\OutgoingEvent' => [
			'statusCode' => [self::KEEP, 'code de statut'],
			'options' => [self::KEEP, 'options d\'envoi'],
			'attachmentHash' => [self::KEEP, 'empreinte technique'],
			'attachmentFilename' => [self::CLEAR, 'nom de fichier'],
			'lastError' => [self::CLEAR, 'réponse de la plateforme, qui cite les parties'],
		],
		'invoicing\\Pdf' => [
			'name' => [self::CLEAR, 'nom de fichier, qui porte le nom de la structure'],
		],
		'invoicing\\PdfContent' => [
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'journal\\JournalCode' => [
			'name' => [self::KEEP, 'intitulé de journal'],
			'code' => [self::KEEP, 'code de journal'],
		],
		'journal\\Operation' => [
			'hash' => [self::KEEP, 'empreinte technique'],
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'accountLabel' => [self::ACCOUNT, 'numéro de compte auxiliaire, formé sur le nom du tiers (« 401 » suivi du fournisseur)'],
			'description' => [self::FAKE, 'libellé d\'écriture, souvent recopié du relevé bancaire', 'Écriture'],
			'document' => [self::KEEP, 'numéro de pièce'],
			'vatAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'lettering' => [self::KEEP, 'code de lettrage'],
		],
		'journal\\OperationCashflow' => [
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'journal\\Pattern' => [
			'name' => [self::KEEP, 'intitulé de trame'],
			'document' => [self::KEEP, 'numéro de pièce'],
			'privateAccountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'privateAccountLabel' => [self::ACCOUNT, 'numéro de compte auxiliaire, formé sur le nom du tiers (« 401 » suivi du fournisseur)'],
		],
		'journal\\PatternLine' => [
			'accountNumber' => [self::ACCOUNT, 'numéro de compte, que la structure peut former sur le nom d\'un tiers'],
			'accountLabel' => [self::ACCOUNT, 'numéro de compte auxiliaire, formé sur le nom du tiers (« 401 » suivi du fournisseur)'],
			'description' => [self::KEEP, 'libellé de trame'],
		],
		'taxReturn\\Associate' => [
			'lastName' => [self::FAKE, 'nom d\'un associé', 'Nom'],
			'usageName' => [self::CLEAR, 'nom d\'usage d\'un associé'],
			'firstNames' => [self::FAKE, 'prénoms d\'un associé', 'Prénom'],
			'birthDate' => [self::CLEAR, 'date de naissance d\'un associé'],
			'birthCity' => [self::CLEAR, 'lieu de naissance'],
			'birthDepartment' => [self::CLEAR, 'lieu de naissance'],
			'legalName' => [self::CLEAR, 'raison sociale d\'un associé'],
			'siren' => [self::CLEAR, 'SIREN d\'un associé'],
			'street1' => [self::CLEAR, 'adresse d\'un associé'],
			'street2' => [self::CLEAR, 'adresse d\'un associé'],
			'postcode' => [self::CLEAR, 'adresse d\'un associé'],
			'city' => [self::CLEAR, 'adresse d\'un associé'],
		],
		'taxReturn\\Entry' => [
			'code' => [self::KEEP, 'code de case de la liasse'],
			'key' => [self::KEEP, 'clé de ligne pré-calculée'],
			'label' => [self::KEEP, 'libellé de ligne'],
			'text' => [self::CLEAR, 'valeur texte d\'une case, qui peut nommer une personne'],
			'computedText' => [self::CLEAR, 'valeur texte d\'une case, qui peut nommer une personne'],
		],
		'taxReturn\\Subsidiary' => [
			'legalName' => [self::FAKE, 'raison sociale d\'une filiale', 'Filiale'],
			'siren' => [self::CLEAR, 'SIREN d\'une filiale'],
			'street1' => [self::CLEAR, 'adresse d\'une filiale'],
			'street2' => [self::CLEAR, 'adresse d\'une filiale'],
			'postcode' => [self::CLEAR, 'adresse d\'une filiale'],
			'city' => [self::CLEAR, 'adresse d\'une filiale'],
		],
		'pdp\\Address' => [
			'name' => [self::KEEP, 'intitulé de l\'adresse'],
			'identifier' => [self::CLEAR, 'adresse électronique, dérivée du SIREN'],
			'statusMessage' => [self::KEEP, 'message de statut de la plateforme'],
		],
		'pdp\\Partner' => [
			'number' => [self::SECRET, 'compte de la structure sur la plateforme'],
			'accessToken' => [self::SECRET, 'jeton de la plateforme'],
			'refreshToken' => [self::SECRET, 'jeton de la plateforme'],
			'params' => [self::SECRET, 'réglages de connexion à la plateforme'],
		],
		'projection\\ProjectionLine' => [
			'source' => [self::KEEP, 'source d\'une ligne du prévisionnel, choisie dans une liste'],
			'comment' => [self::CLEAR, 'texte libre'],
		],
		'proof\\AccountGroup' => [
			'name' => [self::CLEAR, 'nom libre d\'un regroupement de comptes, donné par la structure'],
		],
		'proof\\AccountGroupAccount' => [
			'accountNumber' => [self::KEEP, 'numéro du plan comptable'],
		],
		'proof\\File' => [
			'sha256' => [self::KEEP, 'empreinte de dédoublonnage'],
			'hash' => [self::CLEAR, 'clé du fichier de la pièce sur l\'adresse publique du stockage'],
		],
		'proof\\Folder' => [
			'name' => [self::CLEAR, 'nom libre d\'un dossier de justificatifs, donné par la structure'],
		],
		'proof\\Operation' => [
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'proof\\OperationMissing' => [
			'hash' => [self::KEEP, 'empreinte technique'],
			'reasonText' => [self::CLEAR, 'raison tapée d\'un justificatif manquant, qui peut nommer un tiers'],
		],
		'proof\\Proof' => [
			'fileName' => [self::CLEAR, 'nom du fichier déposé, qui peut nommer un client ou un fournisseur'],
			'name' => [self::CLEAR, 'nom donné au justificatif par la structure, qui peut nommer un tiers'],
			'readHash' => [self::KEEP, 'empreinte technique des fichiers lus'],
			'thirdPartyName' => [self::CLEAR, 'nom d\'un tiers lu sur la pièce'],
			'siren' => [self::CLEAR, 'SIREN lu, qui désigne une personne en entreprise individuelle'],
			'vatNumber' => [self::CLEAR, 'numéro de TVA lu sur la pièce'],
			'number' => [self::KEEP, 'numéro de facture'],
			'numberKey' => [self::KEEP, 'numéro de facture normalisé'],
			'vatByRate' => [self::KEEP, 'montants de TVA par taux'],
			'currency' => [self::KEEP, 'code de devise'],
			'text' => [self::CLEAR, 'texte de la pièce, qui nomme tiers et personnes'],
		],
		'proof\\Sample' => [
			'accountLabel' => [self::KEEP, 'préfixe d\'un numéro du plan comptable'],
			'name' => [self::CLEAR, 'nom du périmètre tiré, qui recopie celui d\'un tiers ou d\'un regroupement'],
		],
		'proof\\SampleOperation' => [
			'hash' => [self::KEEP, 'empreinte technique'],
			'accountLabel' => [self::KEEP, 'numéro du plan comptable'],
			'description' => [self::CLEAR, 'libellé d\'écriture recopié, qui peut nommer un tiers'],
			'comment' => [self::CLEAR, 'remarque libre posée avec « Vu », qui peut nommer un tiers ou une personne'],
		],
		'proof\\Suggestion' => [
			'hash' => [self::KEEP, 'empreinte technique'],
		],
		'securing\\Log' => [
			'event' => [self::KEEP, 'type d\'événement'],
			'data' => [self::CLEAR, 'contenu de l\'événement, qui cite les pièces et les tiers'],
			'comment' => [self::CLEAR, 'texte libre'],
		],
		'securing\\Signature' => [
			'hmac' => [self::KEEP, 'empreinte de la chaîne d\'inaltérabilité'],
			'hmacChained' => [self::KEEP, 'empreinte de la chaîne d\'inaltérabilité'],
			'data' => [self::CLEAR, 'contenu signé, qui cite les pièces et les tiers'],
		],
		'securing\\SignatureTail' => [
			'hmac' => [self::KEEP, 'empreinte du dernier maillon de la chaîne d\'inaltérabilité'],
		],
		'thirdParty\\ThirdParty' => [
			'legalName' => [self::CLEAR, 'raison sociale d\'un tiers'],
			'name' => [self::FAKE, 'nom d\'un tiers', 'Tiers'],
			'siret' => [self::CLEAR, 'SIRET d\'un tiers'],
			'siren' => [self::CLEAR, 'SIREN d\'un tiers'],
			'vatNumber' => [self::CLEAR, 'numéro de TVA d\'un tiers'],
			'supplierAccountNumber' => [self::ACCOUNT, 'numéro de compte auxiliaire du fournisseur, formé sur son nom'],
			'customerAccountNumber' => [self::ACCOUNT, 'numéro de compte auxiliaire du client, formé sur son nom'],
			'electronicAddresses' => [self::CLEAR, 'adresses électroniques, dérivées du SIREN'],
			'memos' => [self::CLEAR, 'libellés bancaires appris, qui nomment le tiers'],
			'fingerprints' => [self::CLEAR, 'empreintes dérivées du nom du tiers'],
			'roles' => [self::KEEP, 'rôles du tiers'],
			'normalizedName' => [self::CLEAR, 'nom du tiers normalisé'],
			'legalStreet1' => [self::CLEAR, 'adresse d\'un tiers'],
			'legalStreet2' => [self::CLEAR, 'adresse d\'un tiers'],
			'legalPostcode' => [self::CLEAR, 'adresse d\'un tiers'],
			'legalCity' => [self::CLEAR, 'adresse d\'un tiers'],
			'contactName' => [self::CLEAR, 'nom d\'un contact'],
			'contactEmail' => [self::CLEAR, 'adresse d\'un contact'],
			'contactPhone' => [self::CLEAR, 'téléphone d\'un contact'],
		],
		'vat\\Declaration' => [
			'data' => [self::KEEP, 'montants déclarés'],
			'collectedData' => [self::KEEP, 'montants collectés'],
			'refundApplicant' => [self::CLEAR, 'nom du demandeur de remboursement'],
			'refundQuality' => [self::KEEP, 'qualité du demandeur'],
			'refundIban' => [self::CLEAR, 'IBAN déclaré'],
			'refundBic' => [self::KEEP, 'code de la banque, qui n\'identifie pas le titulaire'],
			'refundPlace' => [self::CLEAR, 'lieu de signature'],
		],
		'vat\\DeclarationProof' => [
			'thirdPartyName' => [self::CLEAR, 'nom d\'un tiers recopié du justificatif'],
			'number' => [self::KEEP, 'numéro de facture'],
		],
	];

}
?>
