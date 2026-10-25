<?php
Lime::setUrls([
	'dev' => 'http://www.dev-ouvretaferme.org',
	'prod' => 'https://www.ouvretaferme.org'
]);

Lime::setApps(['framework', 'agora', 'ouvretaferme', 'base', 'internal', 'production', 'association']);

L::setLang('fr_FR');
L::setVariables([
	'siteName' => 'Ouvretaferme',
]);

Lime::setName('Ouvretaferme');

if(LIME_HOST !== NULL) {
	define('OTF_DEMO', str_starts_with(LIME_HOST, 'demo.'));
	define('OTF_BLANK', FALSE);
} else {
	define('OTF_DEMO', FALSE);
	// Un import à blanc rejoue une reprise sur la copie de la base d'une structure. Lu seulement hors
	// d'une requête HTTP, qu'aucune variable d'environnement ne peut donc faire passer à blanc.
	define('OTF_BLANK', getenv('OTF_BLANK') === '1');
}

if(OTF_DEMO) {
	echo 'La démo est parcourue chaque jour par des centaines de milliers de bots IA.<br/>Elle reviendra quand nous aurons trouvé une solution contre ces vampires.';
	exit;
}

// L'avatar téléversé, un modèle 3D montré à la place de l'avatar composé : ouvert à l'utilisateur
// qui appartient à une structure de la liste. Seules les pages de l'avatar le lisent, par
// `UserSetting::$avatarUpload`, mais un test doit pouvoir le fermer : d'où la closure de lecture.
define('FEATURE_UPLOAD_MODEL', LIME_ENV === 'dev' ? TRUE : [7]);

// Pierre tombale des groupes d'offres (catalog\Relation), que les variantes remplacent. Le
// drapeau n'est pas là pour pouvoir rallumer le mécanisme : il est là pour qu'on ne l'oublie pas.
// Il ferme les deux seules portes qui font naître un groupe qui n'existait pas — le menu de lot
// d'un catalogue et product/relation:create. Il ne ferme rien d'autre, et surtout pas ce qui touche
// un groupe déjà là : product/relation:query sert l'autocomplétion du panneau qui en modifie la
// composition, CatalogLib::duplicate() recopie un groupe avec son catalogue (sans quoi la
// duplication le perdrait en silence), et OfferLib::update() réécrit ses lignes. Modifier ou
// recopier un groupe existant n'en fait pas un neuf. Il se lit hors d'une page (UI, test d'inventaire),
// donc hors de Page::construct(). catalog/test/OfferGroupSentinelAi.t.php tient l'inventaire de ce qui reste
// à retirer.
define('FEATURE_OFFER_GROUP', FALSE);

// L'émission des notifications les lit en cron et en test, hors d'une page : donc hors de Page::construct().
define('FEATURE_NOTIFICATION', TRUE);
define('FEATURE_PILOTAGE', TRUE);

// La sentinelle des blocs et le moteur l'interrogent hors d'une page : donc hors de Page::construct().
define('FEATURE_DASHBOARD', LIME_ENV === 'dev' ? TRUE : [7]);

// Hors de Page::construct() : common() peut lever une action (sélection de structure d'une
// adresse en /x/, maintenance) avant la fin du constructeur, et le gabarit global en lit certains.
define('FEATURE_FREE', TRUE);
define('FEATURE_AMAP', LIME_ENV === 'dev' ? TRUE : [7]);
define('FEATURE_ACCOUNTING_ACCRUAL', FALSE);
define('FEATURE_GAME', FALSE);
define('FEATURE_SYSTEM_PROMO', TRUE);
define('FEATURE_SURVEY', TRUE);
define('FEATURE_ENTRY', LIME_ENV === 'dev' ? TRUE : [7]);
define('FEATURE_QUOTATION_VARIATION', FALSE);
// La date à partir de laquelle la colonne « Écart n-1 » du détail mensuel
// s'affiche. Elle compare un mois à son homologue de l'année précédente : tant
// que les relevés de prix n'ont pas douze mois d'arrêtés derrière eux, la colonne ne
// contient que des tirets et occupe la place d'une information.
define('FEATURE_QUOTATION_PREVIOUS', '2027-02-01');

require_once Lime::getPath().'/secret.c.php';

switch(LIME_ENV) {

	case 'prod' :

		\dev\DevSetting::$minify = TRUE;
		// La version des assets est fournie par asset.c.php, régénéré par .github/workflows/asset.yml

		Database::setPackages([
			'dev' => 'ouvretaferme',
			'shop' => 'ouvretaferme',
			'amap' => 'ouvretaferme',
			'util' => 'ouvretaferme',
			'session' => 'ouvretaferme',
			'mail' => 'ouvretaferme',
			'user' => 'ouvretaferme',
			'media' => 'ouvretaferme',
			'note' => 'ouvretaferme',
			'notification' => 'ouvretaferme',
			'block' => 'ouvretaferme',
			'dashboard' => 'ouvretaferme',
			'queue' => 'ouvretaferme',
			'payment' => 'ouvretaferme',
			'storage' => 'ouvretaferme',
			'plant' => 'ouvretaferme',
			'gallery' => 'ouvretaferme',
			'sequence' => 'ouvretaferme',
			'structure' => 'ouvretaferme',
			'work' => 'ouvretaferme',
			'map' => 'ouvretaferme',
			'series' => 'ouvretaferme',
			'toolbox' => 'ouvretaferme',
			'aid' => 'ouvretaferme',
			'planning' => 'ouvretaferme',
			'analyze' => 'ouvretaferme',
			'hr' => 'ouvretaferme',
			'product' => 'ouvretaferme',
			'catalog' => 'ouvretaferme',
			'quotation' => 'ouvretaferme',
			'selling' => 'ouvretaferme',
			'external' => 'ouvretaferme',
			'game' => 'ouvretaferme',
			'website' => 'ouvretaferme',

			'main' => 'ouvretaferme',
			'association' => 'ouvretaferme',
			'learning' => 'ouvretaferme',
			'subscription' => 'ouvretaferme',
			'admin' => 'ouvretaferme',
			'training' => 'ouvretaferme',

			'securing' => 'ouvretaferme',
			'api' => 'ouvretaferme',
			'communication' => 'ouvretaferme',
			'data' => 'ouvretaferme',
			'aggregate' => 'ouvretaferme',
			'security' => 'ouvretaferme',

			'company' => 'ouvretaferme',
			'account' => 'ouvretaferme',
			'financialYear' => 'ouvretaferme',

		]);

		break;

	case 'dev' :

		\dev\DevSetting::$minify = FALSE;
		Database::setDebug(get_exists('sql'));

		Database::setPackages([
			'dev' => 'dev_ouvretaferme',
			'shop' => 'dev_ouvretaferme',
			'amap' => 'dev_ouvretaferme',
			'util' => 'dev_ouvretaferme',
			'session' => 'dev_ouvretaferme',
			'mail' => 'dev_ouvretaferme',
			'user' => 'dev_ouvretaferme',
			'media' => 'dev_ouvretaferme',
			'note' => 'dev_ouvretaferme',
			'notification' => 'dev_ouvretaferme',
			'block' => 'dev_ouvretaferme',
			'dashboard' => 'dev_ouvretaferme',
			'queue' => 'dev_ouvretaferme',
			'payment' => 'dev_ouvretaferme',
			'storage' => 'dev_ouvretaferme',
			'plant' => 'dev_ouvretaferme',
			'gallery' => 'dev_ouvretaferme',
			'sequence' => 'dev_ouvretaferme',
			'structure' => 'dev_ouvretaferme',
			'work' => 'dev_ouvretaferme',
			'map' => 'dev_ouvretaferme',
			'series' => 'dev_ouvretaferme',
			'toolbox' => 'dev_ouvretaferme',
			'aid' => 'dev_ouvretaferme',
			'planning' => 'dev_ouvretaferme',
			'analyze' => 'dev_ouvretaferme',
			'hr' => 'dev_ouvretaferme',
			'product' => 'dev_ouvretaferme',
			'catalog' => 'dev_ouvretaferme',
			'quotation' => 'dev_ouvretaferme',
			'selling' => 'dev_ouvretaferme',
			'external' => 'dev_ouvretaferme',
			'game' => 'dev_ouvretaferme',
			'website' => 'dev_ouvretaferme',
			'cash' => 'dev_ouvretaferme',

			'main' => 'dev_ouvretaferme',
			'association' => 'dev_ouvretaferme',
			'learning' => 'dev_ouvretaferme',
			'subscription' => 'dev_ouvretaferme',
			'admin' => 'dev_ouvretaferme',
			'training' => 'dev_ouvretaferme',

			'communication' => 'dev_ouvretaferme',
			'data' => 'dev_ouvretaferme',
			'aggregate' => 'dev_ouvretaferme',
			'security' => 'dev_ouvretaferme',
			'api' => 'dev_ouvretaferme',
			'securing' => 'dev_ouvretaferme',

			'company' => 'dev_ouvretaferme',
			'account' => 'dev_ouvretaferme',
			'financialYear' => 'dev_ouvretaferme',

		]);

		// Lu avant toute connexion : StructureLib::connectDatabase() réécrit ces rattachements en place.
		$packages = Database::getPackages();
		$devAccessToken = \api\ApiSetting::$devAccessToken;

		\dev\TestLib::addReset(function() use($devAccessToken) {
			\api\ApiSetting::$devAccessToken = $devAccessToken;
		});
		\dev\TestLib::addReset(fn() => \user\ConnectionLib::setOnline(new \user\User()));
		\dev\TestLib::addReset(fn() => \structure\StructureLib::disconnectDatabase($packages));

		// Avant chaque test : son rollback ne défait pas ce que les tables de réglages ont mémoïsé dans sa
		// transaction, et une écriture oubliée par le Lib se remémoïse à la lecture suivante. Seules les classes
		// déjà chargées peuvent avoir mémoïsé, d'où le parcours des classes déclarées.
		\dev\TestLib::addReset(function() {

			foreach(get_declared_classes() as $class) {
				if(in_array(\structure\ConfLib::class, class_uses($class), TRUE)) {
					$class::forgetAll();
				}
			}

		}, \dev\TestLib::METHOD);

		break;

}

// Avant toute connexion, la base commune est encore la seule à laquelle les paquets sont rattachés :
// la copie de la structure, que StructureLib::connectDatabase() rattache ensuite, reste la seule
// base où un import à blanc écrit.
if(OTF_BLANK) {
	\ModuleModel::lockBases(array_values(array_unique(Database::getPackages())));
	RedisCache::setPrefix('blank:');
	\queue\QueueLib::setDiscard(TRUE);
	\util\CurlLib::setLocked(TRUE);
}

\user\UserSetting::$checkTos = TRUE;
\user\UserSetting::$apiUserId = 3;

Page::construct(function($data) {

	\main\PageLib::common($data);

	// Seul drapeau qui lit la session, donc posé après common(). Les constructeurs se rejouent
	// dans un même processus (page d'erreur après une DisabledPage, cron permanent), d'où la garde.
	if(defined('FEATURE_INRAE') === FALSE) {
		define('FEATURE_INRAE', LIME_ENV === 'dev' or (isset($_SESSION) and \user\ConnectionLib::getOnline()->notEmpty() and in_array(\user\ConnectionLib::getOnline()['id'], [1, 7445, 10307])));
	}

});

class ApiData extends ApiInternalData {

	public \structure\Structure $eStructure;

	public function acceptScope(string $scope): bool {
		return $this->eStructure->acceptApiScope($scope);
	}

	public function authorized() {

		$this->eStructure = \structure\StructureLib::getById($this->eAccessToken['reference']);

		if($this->eStructure->acceptFeature(\structure\Structure::FEATURE_API) === FALSE) {
			throw new \ApiException('API requires a subscription for this structure', 'subscription_required', 403);
		}

		if($this->eStructure->acceptApi() === FALSE) {
			throw new \ApiException('API is not available for this structure', 'structure_not_allowed', 403);
		}

		// Une clé vit un an et ne porte que l'identité de qui l'a demandée : sans ce contrôle, elle
		// continue d'agir sur une structure que son porteur a quittée, ou dont on lui a retiré
		// l'administration, et personne d'autre ne peut la révoquer. Le droit se relit donc à chaque
		// appel, et c'est le même que celui exigé pour l'obtenir (`Structure::canManage()`, que la page
		// des clés et l'autorisation OAuth valident toutes deux).
		if(\structure\MemberLib::isAdmin($this->eAccessToken['user'], $this->eStructure) === FALSE) {
			throw new \ApiException('The user of this key no longer administrates this structure', 'user_not_allowed', 403);
		}

		\structure\StructureLib::connectDatabase($this->eStructure);
		\user\ConnectionLib::setOnline(\user\UserLib::getById(\user\UserSetting::$apiUserId));

		// Pendant API de StructureLib::load() : les modules comptables lisent le régime de l'exercice
		// sur la structure connectée, qui n'est pas forcément l'élément rechargé ci-dessus (une
		// structure déjà connectée court-circuite connectDatabase).
		\structure\StructureLib::loadAccountingApi(\structure\Structure::getConnected());

	}

}

\api\ApiSetting::setReferenceContextResolver(function($data) {

	if(
		\Route::getRequestMethod() === 'GET' and
		get_exists('structure') === FALSE
	) {
		\structure\ApiKeyLib::resolveAuthorize($data);
	}

	$data->eStructure = \structure\StructureLib::getById(REQUEST('structure'));

	// Même droit que la page des clés personnelles : les deux portes délivrent le même pouvoir sur
	// la structure, et `ApiData::authorized()` le relit à chaque appel
	$data->eStructure->validate('canManage', 'acceptApi');

	if($data->eStructure->acceptFeature(\structure\Structure::FEATURE_API) === FALSE) {
		throw new \RedirectAction(\subscription\SubscriptionUi::urlClosed($data->eStructure), 'replace');
	}

	\structure\StructureLib::connectDatabase($data->eStructure);

});

// Le décor des tests d'API bâtit un jeton sans passer par OAuth, et `ApiData::authorized()` exige de
// son porteur qu'il administre encore la structure : c'est ici, et non dans le cadriciel, que se sait
// qui remplit cette condition.
\api\ApiSetting::$devAccessTokenUser = function(int $reference): \user\User {

	$eStructure = \structure\StructureLib::getById($reference);

	if($eStructure->empty()) {
		throw new \Exception('Structure #'.$reference.' introuvable : sème le décor par structure/configure/fixtureAi write=1, ou importe la copie de production pour une structure réelle');
	}

	$eMember = \structure\MemberLib::getAdminsByStructure($eStructure)->first();

	if($eMember === NULL) {
		throw new \Exception('Aucune personne n\'administre la structure #'.$reference.' : un jeton pour elle serait refusé');
	}

	return $eMember['user'];

};

\api\ApiSetting::setReferenceResolver(fn() => \structure\Structure::getConnected()['id']);

// Hors de tout scope : c'est la structure que l'utilisateur vient de choisir au consentement.
\api\ApiSetting::setTokenParamsResolver(function(int $reference): array {

	$eStructure = \structure\StructureLib::getById($reference);

	return [
		'structure' => [
			'id' => $eStructure['id'],
			'name' => $eStructure['name']
		]
	];

});

if(\structure\StructureSetting::hasSubscriptionPackage()) {

	\api\ApiSetting::setDayLimitResolver(function(int $reference): int {

		$date = \subscription\SubscriptionLib::getAccessDate(new \structure\Structure(['id' => $reference]));

		if($date === NULL) {
			return \api\ApiSetting::RATE_LIMIT_DAY;
		}

		return match(\subscription\SubscriptionLib::getSoftware(\structure\StructureLib::getById($reference), $date)) {
			\subscription\Subscription::EXPERT => \subscription\SubscriptionSetting::API_DAY_LIMIT_EXPERT,
			\subscription\Subscription::MANAGEMENT => \subscription\SubscriptionSetting::API_DAY_LIMIT_MANAGEMENT,
			NULL => \api\ApiSetting::RATE_LIMIT_DAY
		};

	});

}

define('OTF_DEMO_HOST', 'demo.'.Lime::getDomain());
define('OTF_DEMO_URL', \Lime::getProtocol().'://'.OTF_DEMO_HOST);

// Les premiers segments d'URL que les vhosts servent eux-mêmes au lieu de les passer à la page d'un
// tenant : la connexion, l'inscription, les fichiers statiques et minifiés. Une adresse choisie par
// un utilisateur qui vaudrait l'un d'eux aurait une page masquée par le vhost, sans erreur nulle
// part. Ici et non dans un paquet : l'adresse d'un réseau est la première à s'en servir, celle
// d'une boutique ou d'un groupe s'y heurtera de la même façon. Tenue égale aux vhosts par
// structure/test/CommunityHostSentinelAi.
define('RESERVED_FQN', ['inscription', 'minify', 'static', 'user']);

define('CATEGORIE_CULTURE', 'culture');
define('CATEGORIE_PRODUCTION', 'production');
define('CATEGORIE_COMMERCIALISATION', 'commercialisation');
define('CATEGORIE_ADMINISTRATION', 'administration');

define('ACTION_FERTILISATION', 'fertilisation');
define('ACTION_RECOLTE', 'recolte');
define('ACTION_SEMIS_PEPINIERE', 'semis-pepiniere');
define('ACTION_SEMIS_DIRECT', 'semis-direct');
define('ACTION_PLANTATION', 'plantation');
define('ACTION_TRAITEMENT', 'traitement');


function vat_from_including(float $amount, float $vatRate, int $precision = 2): float {
	return $amount - round($amount / (1 + $vatRate / 100), $precision);
}

function vat_from_excluding(float $amount, float $vatRate, int $precision = 2): float {
	return round($amount * $vatRate / 100, $precision);
}

function including_from_excluding(float $amount, float $vatRate, int $precision = 2): float {
	return round($amount + vat_from_excluding($amount, $vatRate), $precision);
}

function excluding_from_including(float $amount, float $vatRate, int $precision = 2): float {
	return round($amount - vat_from_including($amount, $vatRate), $precision);
}

class CommunityAction extends \ViewAction {

	public function render(View $view): void {

		$eStructure = $this->data->eStructure ?? throw new UnsupportedException('Missing $data->eStructure for CommunityAction');

		if($eStructure['type'] !== \structure\Structure::COMMUNITY) {
			throw new NotExpectedAction('Expected COMMUNITY structure');
		}

		$this->data->cRole ??= \structure\RoleLib::getByStructure($eStructure);
		$this->data->ccMember ??= \structure\MemberLib::getByStructure($eStructure);

		// Le gabarit peint sa bande avec la couleur du groupe : elle vit sur `Community`,
		// et une vue ne va pas la chercher. Préparée ici comme les cinq lignes voisines,
		// elle vaut pour tous les écrans de groupe d'un coup — sur un seul, elle se lirait
		// comme un défaut plutôt que comme une limite.
		$this->data->eCommunity ??= \structure\CommunityLib::getByStructure($eStructure);

		$this->data->hasStructures ??= $eStructure->acceptGroupStructure();
		$this->data->eMember ??= $eStructure->getMember();

		// Le compte du groupe entier, que la mise en route et l'accueil reprennent :
		// `prepareSection()` borne ensuite `members` au sous-groupe regardé.
		$this->data->membersTotal = \structure\MemberLib::countByStructure($eStructure);
		$this->data->members ??= $this->data->membersTotal;

		$this->data->hasLearningShared ??= (
			$eStructure->acceptLearning() and
			$eStructure->isNetwork() and
			$this->data->eMember->isStaff() === FALSE and
			\learning\ShareLib::hasNetworkByUser($this->data->eUserOnline, $eStructure)
		);

		$ccStructure = $this->data->hasStructures
			? \structure\AffiliationAiLib::groupStructuresByUser($this->getAffiliations())
			: new \Collection();

		// Le périmètre ne se lit plus ici : il vit sur chaque rattachement, donc c'est la
		// préparation qui trie ferme par ferme. Elle ne requête rien quand aucune n'ouvre sa
		// production.
		\structure\ProfileAiLib::prepareRailProductions($ccStructure, $eStructure);

		// Le nom d'une ferme mène à sa fiche partout où le groupe le nomme. L'adresse ne se pose
		// que sous FEATURE_INRAE : hors du drapeau, `profileAi.p.php` refuse la requête, et le
		// lien serait mort. Même règle que celle des cartes de fermes, dans `community.p.php`.
		foreach($ccStructure as $cStructure) {

			foreach($cStructure as $eStructureProducer) {
				$eStructureProducer['url'] = FEATURE_INRAE ? \structure\StructureUi::urlProfile($eStructureProducer) : '';
			}

		}

		foreach($this->data->ccMember as $cMember) {
			foreach($cMember as $eMember) {
				$eMember['cStructure'] = $ccStructure[$eMember['user']['id']] ?? new \Collection();
			}
		}

		$this->prepareSection();
		$this->prepareMe();
		$this->prepareOnboarding();
		$this->prepareCompare();
		$this->prepareVegetable();
		$this->prepareMap();

		$view->render($this->data, new CommunityTemplate());

	}

	/**
	 * Les rattachements du groupe, que le rail et le menu de la personne regroupent chacun à leur
	 * façon. La route qui les a déjà lus les pose sur `$data->cAffiliation`, et ils ne se relisent
	 * pas : une seule lecture par réponse.
	 */
	private function getAffiliations(): \Collection {

		return $this->data->cAffiliation ??= \structure\AffiliationAiLib::getByCommunity($this->data->eStructure);

	}

	/**
	 * Ce que ce groupe sait de son lecteur, pour le menu de la personne.
	 *
	 * ⚠️ Ses sous-groupes se filtrent sur `cSection`, déjà chargée par `prepareSection()`, et ne se
	 * relisent pas : les ids vivent dans une colonne json, la collection les porte déjà.
	 */
	private function prepareMe(): void {

		$eStructure = $this->data->eStructure;

		$this->data->cStructureMe = new \Collection();
		$this->data->cSectionMe = new \Collection();

		// Un groupe ne se lit pas déconnecté, mais la propriété se pose quand même : le menu se
		// rend sur chaque écran hors mise en route, et c'est lui qui répondrait de l'absence.
		if($this->data->eUserOnline->empty()) {
			return;
		}

		// ⚠️ `getSharedByUser()` et non les rattachements dont je suis l'auteur :
		// `Affiliation.user` est un fait historique — qui a **présenté** la ferme —, gelé au
		// rattachement. Mesuré sur 5984, les deux règles rendaient cinq fermes d'un côté et trois
		// de l'autre, dont deux communes : trois ne m'appartiennent plus, et une que j'administre
		// vraiment manquait parce qu'un associé l'avait rattachée. La page « Mes fermes dans ce
		// groupe » applique déjà cette règle, et le menu y renvoie — deux définitions du même
		// mot sur le même écran en feraient un lien mort.
		$this->data->cStructureMe = \structure\StructureLib::findSharedByUser($this->data->eUserOnline, \structure\AffiliationAiLib::groupByStructure($this->getAffiliations(), $this->data->eUserOnline));

		$user = $this->data->eUserOnline['id'];

		// ⚠️ Les clos comptent : un sous-groupe clos range toujours ses membres, et l'écran de
		// gestion les sépare pour les administrer, pas pour les oublier. Les lire sur la seule
		// `cSection` aurait fait dépendre le menu de ce que la route en cours a mis dedans.
		$cSection = new \Collection();

		foreach([$this->data->cSection, $this->data->cSectionClosed] as $cSectionPart) {
			foreach($cSectionPart as $eSection) {
				$cSection[$eSection['id']] = $eSection;
			}
		}

		// ⚠️ **Un clos ne se montre plus à un membre**, ici comme dans le déroulant de portée : ce
		// qui est fini n'a pas à figurer dans ce qu'on lui présente de son appartenance. Le critère
		// est `Role::isStaff()`, celui du produit pour ceux qui encadrent, et jamais la portée.
		//
		// ⚠️ Aucune exception ici, contrairement à la barre de portée : le menu **dit** une
		// appartenance, il ne borne pas un écran. Un membre borné à une promotion close continue
		// de le lire dans le sur-titre, où l'étiquette est nécessaire pour comprendre le filtre.
		$staff = $this->data->eMember->isStaff();

		// ⚠️ `find()` et non `filter()` : le second mute la collection reçue, et celle-ci est
		// celle que les écrans du groupe affichent.
		$this->data->cSectionMe = $cSection
			->find(fn(\structure\Section $eSection) => (
				in_array($user, $eSection['users'], TRUE) and
				($staff or $eSection->isClosed() === FALSE)
			))
			->sort(['name' => SORT_ASC]);

	}

	/**
	 * Ce que l'écran de comparaison met en regard.
	 *
	 * ⚠️ Préparé ici et non dans la route, parce que `prepareSection()` vient de filtrer
	 * `cStructure` : lire les espèces avant lui les prendrait sur toutes les fermes du groupe, et
	 * un étudiant borné à sa promotion se verrait proposer les espèces de l'établissement entier.
	 * L'ordre des deux préparations est donc le sujet, pas un détail d'écriture.
	 */
	private function prepareCompare(): void {

		$this->data->compareApply ??= FALSE;

		if($this->data->compareApply === FALSE) {
			return;
		}

		// ⚠️ Le partage se filtre ici et non dans la route : `prepareSection()` vient de borner
		// `cStructure` au sous-groupe regardé, et le compte des fermes comparées se dit sur cette
		// population-là.
		//
		// ⚠️ Le résultat dépend du lecteur, et c'est assumé : voir `findSharing()`. Le
		// compte ci-dessous est ce qui empêche un producteur de prendre une comparaison partielle
		// pour celle du groupe entier — un chiffre, qui ne nomme aucune ferme et ne dit donc à
		// personne laquelle lui a refusé la lecture.
		$this->data->structures = $this->data->cStructure->count();

		$cStructure = \structure\AffiliationAiLib::findSharing($this->data->eStructure, $this->data->cStructure, 'shareProduction');

		$this->data->hasSharing = $cStructure->notEmpty();

		// ⚠️ La ferme de départ et celle d'en face se lisent ici et non dans la route, pour la même
		// raison que les espèces : elles se valident contre la population déjà bornée au
		// sous-groupe **et** filtrée par le partage. Une ferme hors de cette population rend l'écran
		// sans ferme choisie, quelle que soit l'adresse demandée.
		$this->data->cStructureFarm = $cStructure;

		$this->data->eStructureFarm = $cStructure[GET('farm', 'int')] ?? new \structure\Structure();
		$this->data->eStructureAgainst = $this->data->eStructureFarm->notEmpty()
			? ($cStructure[GET('against', 'int')] ?? new \structure\Structure())
			: new \structure\Structure();

		if($this->data->eStructureAgainst->is($this->data->eStructureFarm)) {
			$this->data->eStructureAgainst = new \structure\Structure();
		}

		if($this->data->eStructureFarm->notEmpty()) {

			$cStructureCompared = new \Collection([$this->data->eStructureFarm['id'] => $this->data->eStructureFarm]);

			if($this->data->eStructureAgainst->notEmpty()) {
				$cStructureCompared[$this->data->eStructureAgainst['id']] = $this->data->eStructureAgainst;
			} else {
				foreach($cStructure as $eStructureSharing) {
					$cStructureCompared[$eStructureSharing['id']] = $eStructureSharing;
				}
			}

			$cStructure = $cStructureCompared;

		}

		$this->data->cStructure = $cStructure;

		$this->data->seasons = \series\CompareAiLib::getSeasons($cStructure);

		// ⚠️ La saison a un défaut, l'espèce n'en a pas. La saison n'est qu'un cadre, et l'année en
		// cours est celle qu'on regarde quand on ne demande rien ; l'espèce, elle, est l'objet même
		// de la comparaison, et proposée d'office le lecteur ne peut plus savoir si ce qu'il lit
		// est son choix ou celui du logiciel.
		//
		// ⚠️ La saison la plus fournie a été le défaut un temps : elle ouvrait FRAB Aura sur 2027,
		// mesuré le 07/09/2026. Un écran qui s'ouvre sur l'an prochain sans le dire fait lire des
		// prévisions pour des faits.
		//
		// À défaut, la plus récente que les fermes du groupe portent — même repli que
		// `MemberLib::getDynamicSeason()`, qui retombe sur `seasonLast` pour une ferme.
		$seasonDefault = $this->getCompareSeasonDefault($this->data->seasons);

		$this->data->season = GET('season', 'int', $seasonDefault);

		// Une saison qu'aucune ferme du groupe ne porte n'a pas de ligne dans le sélecteur : elle
		// rendait un écran vide sous un titre qui la nomme, et rien pour en sortir.
		if(in_array($this->data->season, $this->data->seasons, TRUE) === FALSE) {
			$this->data->season = $seasonDefault;
		}

		// ⚠️ La fenêtre de la frise est **fixe et la même pour toutes les espèces** : du 1er
		// septembre N-1 au 30 avril N+1. C'est une configuration de calendrier, pas un axe à
		// écrire — 9 et 4 sont dans les plages du schéma —, donc l'écran de comparaison reçoit la
		// même barre de saison que le plan de culture.
		//
		// ⚠️ Jamais dérivée des dates réelles, et c'est ce qui rend deux lignes comparables :
		// mesurée sur la donnée, la fenêtre valait 20 mois sur la carotte, 9 sur l'aubergine et 6
		// sur la courgette, donc deux frises du même groupe ne se superposaient pas. Le prix est
		// qu'une courgette n'occupe qu'un tiers de la largeur.
		// `calendarMonths` est calculée en SQL par `Configuration::getSelection()` et la barre de
		// saison la lit : un élément construit à la main la porte donc lui-même, sous la même
		// définition — les quatre mois de N-1, les douze de N, les quatre de N+1.
		$this->data->eConfiguration = new \map\Configuration([
			'calendarMonthStart' => 9,
			'calendarMonthStop' => 4,
			'calendarMonths' => 20
		]);

		$this->data->cSpecies = ($this->data->eStructureFarm->empty() or get_exists('plant'))
			? \series\CompareAiLib::getSpecies($cStructure, $this->data->season)
			: new \Collection();

		$this->data->nothingToCompare = ($this->data->eStructureFarm->empty() and $this->data->cSpecies->empty());
		$this->data->hasCompareCommunity = $this->hasCompareCommunity();

		$this->data->ePlant = $this->data->cSpecies[GET('plant', 'int')] ?? new \plant\Plant();

		if($this->data->ePlant->empty()) {

			$this->data->cByStructure = new \Collection();

			$this->data->cPlantTimeline = $this->data->eStructureFarm->notEmpty()
				? \series\CompareAiLib::getSpeciesTimeline($cStructure, $this->data->eStructureFarm, $this->data->season)
				: new \Collection();

			return;

		}

		$this->data->cPlantTimeline = new \Collection();

		$this->data->cByStructure = \series\CompareAiLib::getCultivations($cStructure, $this->data->ePlant, $this->data->season);

		// ⚠️ Le droit d'ouvrir une série se pose ici et non dans le tableau : le laisser à la
		// cellule le ferait relire ligne à ligne. Le tableau ne rend un lien que là où il mène
		// quelque part.
		//
		// ⚠️ Les rattachements se chargent d'abord, en une requête pour toutes les fermes : la
		// mémoïsation de `getCommunitiesByStructure()` est par couple (ferme, lecteur), donc rien
		// ne se transmet d'un `canRead()` au suivant et chaque ferme que le lecteur n'atteint que
		// par un partage coûte sa propre lecture.
		\structure\AffiliationAiLib::fillCommunitiesByStructures($cStructure, $this->data->eUserOnline);

		foreach($cStructure as $eStructureCompared) {
			$eStructureCompared['canReadSeries'] = $eStructureCompared->canRead();
		}

	}

	private function getCompareSeasonDefault(array $seasons): int {

		return in_array(\Clock::year(), $seasons, TRUE)
			? \Clock::year()
			: ($seasons[0] ?? \Clock::year());

	}

	/**
	 * Ce que le tableau de bord maraîchage agrège.
	 *
	 * ⚠️ Préparé ici et non dans la route, pour la même raison que `prepareCompare()` :
	 * `prepareSection()` vient de borner `cStructure` au sous-groupe regardé, et lire les agrégats
	 * avant lui les prendrait sur tout l'établissement.
	 *
	 * ⚠️ **`cStructure` n'est pas réaffectée**, contrairement à `prepareCompare()`. La population
	 * du tableau de bord est faite des fermes rattachées, partageuses ou non (le Lib n'en retire, dans un
	 * réseau, que les fermes qui ne sont pas en production) : le partage du
	 * plan de culture décide de ce qu'un lecteur peut ouvrir ferme par ferme, pas de ce qu'un
	 * agrégat masqué sous un plancher peut dire du réseau. `findSharing()` ne sert donc
	 * ici qu'à savoir si les liens vers la comparaison mènent quelque part.
	 */
	private function prepareVegetable(): void {

		$this->data->vegetableApply ??= FALSE;

		if($this->data->vegetableApply === FALSE) {
			return;
		}

		$cStructureSharing = \structure\AffiliationAiLib::findSharing($this->data->eStructure, $this->data->cStructure, 'shareProduction');

		$this->data->hasSharing = $cStructureSharing->notEmpty();

		// ⚠️ Les saisons sont déjà bornées par le plancher : une saison qu'une seule ferme porte
		// n'ouvrirait qu'un écran entièrement masqué.
		$this->data->seasons = \series\VegetableDashboardAiLib::getSeasons($this->data->eStructure, $this->data->cStructure);

		$seasonDefault = $this->getVegetableSeasonDefault($this->data->seasons);

		$this->data->season = GET('season', 'int', $seasonDefault);

		if(in_array($this->data->season, $this->data->seasons, TRUE) === FALSE) {
			$this->data->season = $seasonDefault;
		}

		$this->data->dashboard = \series\VegetableDashboardAiLib::get($this->data->eStructure, $this->data->cStructure, $this->data->season);

		// L'écran entier bascule sur cette seule condition : aucune valeur n'atteint le plancher,
		// et le tableau de bord cède la place au bloc qui dit combien de fermes contribuent.
		$this->data->hasAboveFloor = \series\VegetableDashboardAiLib::hasAboveFloor($this->data->dashboard);

		// Les fermes rattachées dans la portée, le nombre que l'écran des fermes affiche : le bloc
		// vide le dit tel quel, quand `dashboard['farms']` ne compte que celles que le tableau de
		// bord retient.
		$this->data->structures = $this->data->cStructure->count();

		$this->data->hasCompare = $this->hasVegetableCompare($cStructureSharing);
		$this->data->hasFiguresCommunity = $this->hasVegetableFiguresCommunity();

	}

	private function getVegetableSeasonDefault(array $seasons): int {
		return $seasons[0] ?? \Clock::year();
	}

	/**
	 * Le bloc « Rien à comparer pour l'instant » d'un sous-groupe renvoie à la comparaison de tout le
	 * groupe, et ne doit jamais mener au même bloc : la réponse est celle de l'adresse sans
	 * sous-groupe, sans ferme, sans espèce ni saison, calculée comme elle.
	 *
	 * Les lectures sont celles que cette adresse fait à chaque affichage, et ne sont faites ici que
	 * sur l'écran déjà vide. Un lecteur borné à ses sous-groupes n'a pas de groupe entier où aller.
	 */
	private function hasCompareCommunity(): bool {

		if(
			$this->data->nothingToCompare === FALSE or
			$this->data->eSection->empty() or
			$this->data->sectionScope !== NULL
		) {
			return FALSE;
		}

		$cStructureSharing = \structure\AffiliationAiLib::findSharing($this->data->eStructure, $this->data->cStructureCommunity, 'shareProduction');

		return $this->hasCompareSpecies($cStructureSharing);

	}

	/**
	 * La comparaison de ces fermes a-t-elle une espèce à proposer, à l'adresse sans ferme, sans
	 * espèce ni saison ? C'est le contraire de son bloc « Rien à comparer pour l'instant »
	 * (`prepareCompare()`, `nothingToCompare`).
	 */
	private function hasCompareSpecies(\Collection $cStructureSharing): bool {

		if($cStructureSharing->empty()) {
			return FALSE;
		}

		$seasons = \series\CompareAiLib::getSeasons($cStructureSharing);

		return \series\CompareAiLib::getSpecies($cStructureSharing, $this->getCompareSeasonDefault($seasons))->notEmpty();

	}

	/**
	 * Le bloc vide des statistiques et l'écran sans plan de culture renvoient à la comparaison des
	 * cultures, et ne doivent jamais mener à son bloc « Rien à comparer pour l'instant »
	 * (`hasCompareSpecies()`).
	 *
	 * Les deux lectures de la comparaison ne sont faites que là où le bouton peut paraître : dès que
	 * `FLOOR` fermes ont un plan de culture, l'écran rend ses chiffres et n'y renvoie pas
	 * (`VegetableDashboardAiUi::get()`).
	 */
	private function hasVegetableCompare(\Collection $cStructureSharing): bool {

		if($this->data->dashboard['plan']['farms'] >= \series\VegetableDashboardAiLib::FLOOR) {
			return FALSE;
		}

		return $this->hasCompareSpecies($cStructureSharing);

	}

	/**
	 * Le bloc vide d'un sous-groupe renvoie aux statistiques de tout le groupe, et ne doit jamais
	 * mener à un second bloc vide : la réponse est celle de l'adresse sans sous-groupe ni saison,
	 * calculée comme elle.
	 *
	 * ⚠️ Un second tableau de bord coûte 35 à 200 ms : il n'est lu que sur l'écran déjà vide, quand
	 * aucun bouton ne passe devant lui (la comparaison, si elle a quelque chose à montrer). Un lecteur
	 * borné à ses sous-groupes n'a pas de groupe entier où aller.
	 */
	private function hasVegetableFiguresCommunity(): bool {

		if(
			$this->data->hasAboveFloor or
			$this->data->hasCompare or
			$this->data->eSection->empty() or
			$this->data->sectionScope !== NULL
		) {
			return FALSE;
		}

		$cStructure = $this->data->cStructureCommunity;

		$seasons = \series\VegetableDashboardAiLib::getSeasons($this->data->eStructure, $cStructure);
		$dashboard = \series\VegetableDashboardAiLib::get($this->data->eStructure, $cStructure, $this->getVegetableSeasonDefault($seasons));

		return \series\VegetableDashboardAiLib::hasAboveFloor($dashboard);

	}

	/**
	 * ⚠️ Lue après `prepareSection()`, qui a borné les fermes au sous-groupe regardé : la carte montre
	 * ce que la liste montre dessous. Les fermes projet n'y sont pas.
	 */
	private function prepareMap(): void {

		$this->data->mapApply ??= FALSE;

		if($this->data->mapApply === FALSE) {
			return;
		}

		$cStructure = $this->getStructuresMembers();
		$cSystem = \structure\SystemLib::getByStructures($cStructure);

		$this->data->cStructureLocated = new \Collection();

		foreach($cStructure as $eStructure) {

			$eStructure['eSystem'] = $cSystem[$eStructure['id']];

			if($eStructure['eSystem']['placeLngLat'] !== NULL) {
				$this->data->cStructureLocated[$eStructure['id']] = $eStructure;
			}

		}

		$this->data->mapTotal = $cStructure->count();

	}

	private function getStructuresMembers(): \Collection {

		$cStructure = new \Collection();

		foreach([$this->data->cStructureMine, $this->data->cStructureMember] as $cStructureList) {
			foreach($cStructureList as $eStructure) {
				$cStructure[$eStructure['id']] = $eStructure;
			}
		}

		return $cStructure;

	}

	/**
	 * Ce que le groupe propose de faire ensuite, rendu en tête du contenu par le gabarit.
	 *
	 * ⚠️ L'état se calcule dans le Lib du type : le gabarit ne sait pas quel enchaînement
	 * s'applique, il demande. Chaque route qui montre l'appel à l'action le déclare par
	 * `$data->onboardingApply`, et le défaut est FAUX — les écrans qui ne le portent pas ne
	 * paient pas les lectures qu'il demande.
	 */
	private function prepareOnboarding(): void {

		$this->prepareStart();

		$this->data->onboardingApply ??= FALSE;
		$this->data->onboardingHidden ??= [];

		if($this->data->onboardingApply === FALSE) {
			return;
		}

		$class = \structure\StructureLib::getCommunityClass($this->data->eStructure);

		$this->data->onboarding = $class::getOnboarding($this->data->eStructure, $this->data->eUserOnline, $this->data->membersTotal);

		// ⚠️ Pendant la mise en route, l'appel à l'action se tait aussi hors de l'accueil : il propose
		// les gestes du parcours, et deux guides du même geste ne se mettraient pas à jour ensemble.
		if(
			$this->data->startPending or
			in_array($this->data->onboarding['state'], $this->data->onboardingHidden)
		) {
			$this->data->onboarding['state'] = \structure\StructureCommunityLib::NOTHING;
		}

	}

	/**
	 * La mise en route d'un groupe : le parcours sur son accueil, et rien sur ses autres écrans.
	 *
	 * ⚠️ **Le parcours ne tient que l'accueil**, que sa route déclare par `$data->startApply`, dont
	 * le défaut est FAUX (décidé le 12/10, question 1 du cadrage de la mise en route).
	 * Il a remplacé tous les écrans du groupe : une formatrice qui ouvrait Formations retombait
	 * sur lui, et la fin la ramenait à l'accueil. Les autres écrans s'ouvrent donc sans en parler :
	 * le rappel qu'ils portaient en tête a été retiré (19/10).
	 *
	 * ⚠️ Le bandeau de fin paraît sur tout écran : c'est celui où la fin ramène, qui n'est pas
	 * toujours l'accueil.
	 *
	 * ⚠️ Rien n'est compté tant que le parcours n'a pas lieu d'être : ces lectures ne coûtent
	 * qu'aux groupes en cours de démarrage, et à l'écran qui affiche leur bandeau de fin.
	 *
	 * `prepareSection()` a déjà chargé `cSection`, c'est ce qui permet de ne pas la relire.
	 */
	private function prepareStart(): void {

		$this->data->startApply ??= FALSE;

		$this->data->start = NULL;
		$this->data->startWelcome = FALSE;
		$this->data->startPending = FALSE;
		$this->data->startOrigin = \structure\CommunityStartAiUi::ORIGIN_HOME;

		$eStructure = $this->data->eStructure;

		// ⚠️ Le parcours demande à la classe du type des mots qu'elle doit savoir dire : la garde nomme
		// les types qui les disent (`acceptCommunityStart()`), et un type ajouté depuis n'a rien qui le
		// rappelle à qui l'ajoute, d'où le test d'appariement.
		if(
			$eStructure->acceptCommunityStart() === FALSE or
			$eStructure->canManage() === FALSE
		) {
			return;
		}

		// Le bandeau d'arrivée : demandé par l'adresse au retour de la fermeture, et par elle
		// seule. Il efface son paramètre en paraissant, donc il ne revient pas.
		$welcome = (GET('demarrage') === 'fini');

		if(
			$welcome === FALSE and
			$eStructure->conf(\structure\Feature::class)['hasStart'] === FALSE
		) {
			return;
		}

		if(
			$welcome === FALSE and
			$this->data->startApply === FALSE
		) {
			$this->data->startPending = TRUE;
			return;
		}

		// ⚠️ Le rôle de l'équipe se lit dans la table des publics et ne s'écrit pas ici : un
		// organisme de formation appelle la sienne `admin`, un réseau `advisor`, et une
		// communauté de producteurs n'en a pas. Écrit en dur, le compte valait zéro sur un réseau
		// pourtant pourvu, et le bandeau d'arrivée proposait d'inviter une équipe déjà là.
		$roles = $eStructure->getAudienceRoles();
		$staff = $roles['staff'] ?? NULL;

		if($staff === NULL) {
			$staffCount = 0;
		} else {
			$staffCount = \structure\MemberLib::countByRoleFqn($eStructure, $staff) + \structure\InviteLib::countMembersByRoleFqn($eStructure, $staff);
		}

		// Le créateur, administrateur, ferait passer une équipe d'administrateurs pour pourvue.
		if($staff === 'admin') {
			$staffCount--;
		}

		// Une invitation épuisée reste listée jusqu'à son nettoyage (`InviteLib::clean()`), mais n'attend plus personne.
		$cInvite = \structure\InviteLib::getMembersByStructure($eStructure)->find(fn(\structure\Invite $eInvite) => $eInvite->acceptLimit());

		$this->data->startCounts = [
			// L'étape des invitations est faite dès que le lien du groupe existe, donc il compte comme les autres.
			'invites' => $cInvite->count(),
			// Une invitation sans adresse est un lien, qui n'est parti chez personne, et le bandeau de fin ne compte que les invitations parties.
			'invitesSent' => $cInvite->find(fn(\structure\Invite $eInvite) => $eInvite['email'] !== NULL)->count(),
			'members' => $this->data->membersTotal,
			'staff' => $staffCount
		];

		if($welcome) {

			// Lu sur le seul écran du bandeau, qui propose la première formation tant qu'il n'y en a aucune.
			$this->data->startCounts['courses'] = \learning\CourseLib::countByCommunity($eStructure);

			$this->data->startWelcome = TRUE;
			return;

		}


		$this->data->startOrigin = GET('origin', \structure\CommunityStartAiUi::ORIGINS, \structure\CommunityStartAiUi::ORIGIN_HOME);

		$this->data->eInviteLink = array_key_exists('member', $roles) ? \structure\InviteLib::getLinkByStructure($eStructure) : new \structure\Invite();

		// L'étape du partage réemploie le formulaire du réglage, qui attend ces comptes : préparés
		// ici comme l'écran de réglage les prépare, et non lus avec un défaut dans l'UI, qui ne va
		// pas chercher sa donnée.
		$eStructure['shareCounts'] = \structure\AffiliationAiLib::countSharesByCommunity($eStructure);

		// ⚠️ L'étape ouverte vient de l'adresse quand un enregistrement vient de la poser, sinon
		// c'est la première qui manque. Sans elle, passer une étape puis enregistrer la suivante
		// ramènerait sur celle qu'on vient de passer : rien ne mémorise qu'on l'a passée.
		$this->data->start = \structure\CommunityStartAiUi::getOpenStep($eStructure, GET('demarrage', '?int'), $this->data->cSection, $this->data->startCounts);

	}

	/**
	 * Le filtre par sous-groupe, préparé pour les écrans que ce gabarit sert.
	 *
	 * ⚠️ Il ne s'applique **que là où la page liste des objets rangés dans les sous-groupes** :
	 * les fermes des membres et les membres eux-mêmes. Chaque route concernée le déclare par
	 * `$data->sectionApply`, et le défaut est FAUX — un écran neuf ne se met donc pas à filtrer
	 * tout seul, il le demande.
	 *
	 * Les autres écrans du groupe ne montrent pas le sélecteur et ne filtrent rien, pas même leur
	 * rail : les fermes école n'appartiennent à personne, « mes fermes » sont celles de la
	 * personne connectée et non du groupe, les relevés sont ceux du groupe, et la liste des
	 * sous-groupes filtrée par un sous-groupe ne rendrait qu'elle-même. Un contrôle qui n'agit pas
	 * sur ce qu'on regarde ment sur son effet ; un rail restreint sans le contrôle qui l'explique
	 * ment dans l'autre sens.
	 *
	 * Le paramètre continue pourtant de voyager sur ces écrans-là, sinon chaque aller-retour par
	 * un onglet voisin effacerait le choix.
	 */
	private function prepareSection(): void {

		$this->data->cSection ??= \structure\SectionLib::getByStructure($this->data->eStructure);

		// `??=` : l'écran de gestion sépare lui-même ouverts et clos, et n'applique pas le filtre.
		// Une affectation sèche écraserait sa liste avant qu'il l'affiche.
		$this->data->cSectionClosed ??= new \Collection();
		$this->data->eSection = new \structure\Section();
		$this->data->sectionScope = \structure\SectionLib::getScope($this->data->eStructure);
		$this->data->sectionChoice = TRUE;
		$this->data->sectionApply ??= FALSE;

		// Le rôle borne ce qu'on peut choisir : un étudiant ne se voit proposer que ses promotions,
		// et « toutes » veut alors dire « toutes les miennes ». Borné à aucune, il n'a pas de
		// sélecteur du tout — c'est le même écran qu'un groupe sans sous-groupe.
		if($this->data->sectionScope !== NULL) {

			$scope = $this->data->sectionScope;

			$this->data->cSection = $this->data->cSection->find(
				fn(\structure\Section $eSection) => in_array($eSection['id'], $scope, TRUE)
			);

		}

		// ⚠️ Un choix ne s'affiche que s'il existe plus d'une option qui change le résultat, et
		// cela se mesure sur la borne et non sur le groupe. Borné à un seul sous-groupe, un membre
		// se voyait proposer « toutes les promotions » et « sa promotion », qui rendaient le même
		// écran : un menu dont toutes les options mènent au même endroit n'est pas un choix, et
		// « toutes » lui mentait puisqu'il en voit une sur deux.
		$this->data->sectionChoice = (
			$this->data->sectionScope === NULL or
			$this->data->cSection->count() > 1
		);

		// Un groupe sans sous-groupe rend exactement les mêmes écrans qu'avant : pas de sélecteur,
		// pas de propriété posée, pas une requête de plus.
		if($this->data->cSection->empty()) {

			$this->data->sectionApply = FALSE;

			return;

		}

		$section = GET('section');

		// Un sous-groupe clos reste sélectionnable : le choix se cherche donc avant la séparation.
		$this->data->eSection = \structure\SectionLib::getSelected($this->data->cSection, $section);

		$filtered = $this->data->eSection->notEmpty();

		// Le choix voyage dans l'adresse et jamais en session : un lien envoyé montre ce qu'on
		// regarde, et non ce que la dernière visite du destinataire avait laissé. Il y voyage
		// maintenant en **segment** — `/groupe/12/membres/101` —, porté par les url helpers et non
		// concaténé à la fin : c'est la forme du dépôt pour une portée, comme la saison du plan de
		// culture ou la région des relevés.
		//
		// ⚠️ Les écrans qui ne filtrent pas — « Mes fermes », les relevés — n'ont pas de segment,
		// donc la portée s'oublie en passant par eux. C'est assumé : leur en donner un serait une
		// commande qui n'agit pas sur ce qu'on regarde.

		if($this->data->sectionApply === FALSE) {
			return;
		}

		\structure\SectionLib::fillSections($this->data->ccMember, $this->data->cSection);

		// Les effectifs se comptent avant de filtrer : le sélecteur annonce toujours ce que
		// chaque entrée contient, y compris celles qu'on ne regarde pas.
		$counts = [];
		$membersScope = 0;

		foreach($this->data->ccMember as $cMember) {
			foreach($cMember as $eMember) {

				foreach($eMember['cSection'] as $eSection) {

					$counts[$eSection['id']] = ($counts[$eSection['id']] ?? 0) + 1;

					// L'union de la borne, comptée une fois par personne : deux promotions qui se
					// partagent quelqu'un ne le comptent pas deux fois.
					if(
						$this->data->sectionScope !== NULL and
						in_array($eSection['id'], $this->data->sectionScope, TRUE)
					) {

						$membersScope++;

						break;

					}

				}

			}
		}

		foreach($this->data->cSection as $eSection) {
			$eSection['countMember'] = $counts[$eSection['id']] ?? 0;
		}

		// Chaque chiffre est borné par qui le calcule : celui des fermes l'est dans la route du
		// tableau de bord, qui tient les rattachements. Les deux disent ce que le filtre laisse
		// voir, sinon le tableau qui les ventile contredirait ce qu'ils annoncent.
		if($this->data->eSection->notEmpty()) {
			$this->data->members = $this->data->eSection['countMember'];
		} else if($this->data->sectionScope !== NULL) {
			$this->data->members = $membersScope;
		}

		// Les clos ferment la liste du sélecteur : la séparation se décide ici et non par un tri
		// sur la colonne `status`, qui marcherait par accident de l'ordre alphabétique de ses deux
		// valeurs. `cSection` garde les ouverts, qui sont ce que la liste propose d'abord.
		$cSection = $this->data->cSection;

		// ⚠️ **Un clos ne se propose plus à un membre**, il ne reste que pour l'encadrement. Ce qui
		// est fini n'a pas à figurer dans une liste où l'on choisit, et le critère est
		// `Role::isStaff()` et jamais la portée : c'est le mot du produit pour ceux qui encadrent.
		//
		// ⚠️ Sauf ceux qui **bornent** ce lecteur. Clore ne retire aucun accès — le `.yml` le dit —
		// et un rôle borné à la promotion de l'an dernier continue d'y être borné : sans sa ligne,
		// l'étudiant regarderait un écran filtré sans étiquette, sans rien qui dise à quoi. La
		// visibilité change, les droits non.
		$scope = $this->data->sectionScope ?? [];

		if($this->data->eMember->isStaff() === FALSE) {

			$cSection = $cSection->find(fn(\structure\Section $eSection) => (
				$eSection->isClosed() === FALSE or
				in_array($eSection['id'], $scope, TRUE)
			));

		}

		// ⚠️ Un clos qui **borne** le lecteur reste dans le corps et non dans la queue de la liste :
		// la queue est ce qu'on propose en dernier, et sa promotion n'est pas une option qu'on lui
		// propose, c'est l'écran qu'il regarde. Sans ça, `cSection` se vidait et le sur-titre
		// perdait le nom du filtre qui agissait quand même.

		$this->data->cSection = $cSection->find(fn(\structure\Section $eSection) => (
			$eSection->isClosed() === FALSE or
			in_array($eSection['id'], $scope, TRUE)
		));

		$this->data->cSectionClosed = $cSection->find(fn(\structure\Section $eSection) => (
			$eSection->isClosed() and
			in_array($eSection['id'], $scope, TRUE) === FALSE
		));

		// Le sous-groupe qu'on regarde a toujours sa ligne, ouvert ou clos : marquer suppose que la
		// ligne existe, et un tableau qui montre les deux sous-groupes où l'on n'est pas mais pas
		// celui qu'on regarde ne se lit plus. Le clos choisi rejoint donc le corps.
		if($this->data->eSection->notEmpty() and $this->data->eSection->isClosed()) {

			$this->data->cSectionClosed->offsetUnset($this->data->eSection['id']);
			$this->data->cSection[$this->data->eSection['id']] = $this->data->eSection;

		}

		// Borné, on filtre même sans choix : la vue par défaut est l'union de ses sous-groupes, et
		// non le groupe entier. C'est la différence entre un filtre de confort et une portée.
		if($filtered === FALSE and $this->data->sectionScope === NULL) {
			return;
		}

		$users = $this->filterMembers();

		$this->filterStructures($users);

	}

	/**
	 * Réduit le rail aux membres du sous-groupe et rend les utilisateurs qu'il laisse passer.
	 *
	 * Les administrateurs restent visibles quel que soit le filtre : un professeur qui regarde une
	 * promotion ne doit pas disparaître de son propre rail. Ils ne comptent pas pour autant parmi
	 * les utilisateurs rendus — une ferme n'entre pas dans la promotion parce que
	 * l'administrateur, lui, y reste affiché. Ce que l'équipe apporte se garde ailleurs, dans
	 * `SectionLib::keepsStructure()`.
	 */
	private function filterMembers(): array {

		$users = [];
		$rolesEmpty = [];

		foreach($this->data->ccMember as $role => $cMember) {

			$cMemberKept = $cMember->find(function(\structure\Member $eMember) use (&$users) {

				$keep = $this->keepMember($eMember);

				if($keep) {
					$users[$eMember['user']['id']] = TRUE;
				}

				// ⚠️ Toujours visible : l'encadrement quand le lecteur choisit librement, et tout
				// rôle de portée ALL quand il est borné. C'est ce qui fait qu'un étudiant voit son
				// enseignant, lequel n'est rangé dans aucune promotion.
				//
				// La seconde règle ne se généralise pas au premier cas : tous les rôles portent
				// ALL par défaut, et le sélecteur cesserait de filtrer quoi que ce soit — mesuré,
				// le rail d'un réseau passerait de 2 membres à 5. L'encadrement, lui, se borne aux
				// trois `fqn` que le produit pose : en réseau il ajoute les conseillers et rien
				// d'autre, `member` restant filtré.
				$visible = ($this->data->sectionScope === NULL)
					? $eMember->isStaff()
					: ($eMember['role']['sections'] === \structure\Role::ALL);

				return ($keep or $visible);

			});

			$this->data->ccMember[$role] = $cMemberKept;

			if($cMemberKept->empty()) {
				$rolesEmpty[] = $role;
			}

		}

		// Un rôle que le filtre a vidé disparaît du rail : le gabarit rend un titre et un compteur
		// par rôle, et laisser la clé afficherait « Étudiants 0 ». Retiré après la boucle, jamais
		// pendant : Collection étend ArrayIterator, dont on ne retire pas une clé en cours de
		// parcours.
		foreach($rolesEmpty as $role) {
			$this->data->ccMember->offsetUnset($role);
		}

		return $users;

	}

	/**
	 * Ce que la vue courante laisse passer : le sous-groupe choisi, ou la borne du rôle quand
	 * aucun choix n'a été fait.
	 */
	private function keepMember(\structure\Member $eMember): bool {

		if($this->data->eSection->notEmpty()) {
			return $eMember['cSection']->offsetExists($this->data->eSection['id']);
		}

		foreach($this->data->sectionScope as $section) {

			if($eMember['cSection']->offsetExists($section)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Réduit les fermes des étudiants à celles qu'un membre du sous-groupe a apportées.
	 *
	 * C'est `cUser` qui répond, posée par la page depuis la table des rattachements : une ferme
	 * appartient au groupe au nom de qui l'y a mise, et c'est cette personne que le sous-groupe
	 * range. Les collections absentes de l'écran courant ne sont pas touchées.
	 */
	private function filterStructures(array $users): void {

		foreach(['cStructureMine', 'cStructureMember', 'cStructure'] as $property) {

			$cStructure = $this->data->$property ?? NULL;

			if($cStructure instanceof \Collection === FALSE or $cStructure->empty()) {
				continue;
			}

			// `cUser` **définit** ce qui se filtre : une ferme qui la porte est entrée dans le
			// groupe au nom de quelqu'un, et c'est cette personne que le sous-groupe range. Une
			// liste qui ne la porte pas n'est pas une liste de fermes de membres — /mes-fermes
			// montre les miennes, qu'aucune promotion ne restreint. Les fermes projet du groupe et
			// les fermes école ne sont celles de personne, elles ne sont donc jamais dans ce tour.
			if($cStructure->first()->offsetExists('cUser') === FALSE) {
				continue;
			}

			$this->data->$property = $cStructure->find(
				fn(\structure\Structure $eStructure) => \structure\SectionLib::keepsStructure($this->data->eStructure, $eStructure, $users, $this->data->sectionScope !== NULL)
			);

		}

	}

}

/**
 * Rend une page de l'AMAP sous `AmapTemplate`, celle du bureau comme celle de l'adhérent.
 *
 * ⚠️ Ne pose que ce que le bandeau montre au compte connecté : son rang au bureau et ses menus. Rien
 * de l'équipe ni des rattachements d'un groupe (`CommunityAction`), qui n'ont pas de sens pour une
 * AMAP et feraient descendre la liste du bureau sur la page d'un adhérent. Les menus se calculent pour
 * le connecté et jamais d'après la fiche que la page affiche, qui peut être celle d'un autre
 * adhérent. Deux pages s'ouvrent sans connexion, la charte et le partage d'un contrat : un anonyme
 * y reçoit un bandeau sans menu.
 */
class AmapAction extends \ViewAction {

	public function render(View $view): void {

		$eStructure = $this->data->eStructure ?? throw new UnsupportedException('Missing $data->eStructure for AmapAction');

		if($eStructure['type'] !== \structure\Structure::AMAP) {
			throw new NotExpectedAction('Expected AMAP structure');
		}

		// Un autre nom que `eMember`, que des routes partagées avec une ferme posent sur le membre
		// qu'elles montrent (`member.p.php`).
		$this->data->eMemberOnline = $eStructure->getMember();
		$this->data->amapMenus = \structure\MenuLib::getAmapGroupMenus($eStructure);

		$view->render($this->data, new AmapTemplate());

	}

}

class DynamicAction extends \ViewAction {

	public function render(View $view): void {

		$eStructure = $this->data->eStructure ?? throw new UnsupportedException('Missing $data->eStructure for DynamicActionF');

		switch($eStructure['type']) {

			case \structure\Structure::COMMUNITY :
				new CommunityAction(
					data: $this->data,
					path: $this->path,
					view: $this->view
				)->render($view);
				break;

			case \structure\Structure::AMAP :
				new AmapAction(
					data: $this->data,
					path: $this->path,
					view: $this->view
				)->render($view);
				break;

			default :
				$view->render($this->data, template: new StructureTemplate());
				break;

		}

	}

}

interface DynamicTemplate {

}
?>
