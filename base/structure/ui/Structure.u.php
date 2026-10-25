<?php
namespace structure;

class StructureUi {

	use \Notifiable;

	public function __construct() {
		\Asset::css('structure', 'structure.css');
		\Asset::js('structure', 'structure.js');
	}

	public static function getInstance(Structure $eStructure): StructureCommonUi {

		$class = self::getClass($eStructure);
		return new $class;

	}

	/**
	 * La couleur d'un groupe, en jetons, prête à poser sur sa bande.
	 *
	 * `--community` peint l'aplat, `--community-ink` l'encre qui va dessus. L'encre est
	 * **dérivée** de la couleur et jamais choisie : un groupe qui prend une teinte claire
	 * aurait sinon du blanc sur du jaune, et `reboot.css` ne peut pas le deviner. Une
	 * couleur trop claire pour porter le blanc de la bande du corps est refusée à
	 * l'enregistrement (`Community::isReadableUnderLight()`) : seule une couleur enregistrée
	 * avant cette règle peut encore prendre l'encre noire.
	 *
	 * Rien du tout sur un groupe qui n'a pas choisi : les jetons gardent alors la valeur
	 * de Ouvretaferme, et la bande est celle de tous les autres écrans.
	 *
	 * Une AMAP n'a pas de `Community` : sa bande prend la couleur de l'univers AMAP, celle que le
	 * gabarit d'une structure lui donne déjà.
	 */
	public static function getCommunityColorStyle(Structure $eStructure, Community $eCommunity): string {

		if($eStructure->isEducation()) {
			return ' style="--community: var(--color-brand)"';
		}

		if($eStructure->isAmap()) {
			return ' style="--community: var(--amap)"';
		}

		if($eCommunity->empty() or $eCommunity['color'] === NULL) {
			return '';
		}

		return ' style="--community: '.encode($eCommunity['color']).'; --community-ink: '.encode(Community::getInk($eCommunity['color'])).'"';

	}

	/**
	 * Le libellé du type d'un groupe, ou la chaîne vide.
	 *
	 * Le type est une propriété du groupe et non de l'écran : le laisser poser par chaque vue
	 * le fait disparaître de celles qui l'oublient, et un surtitre vide se lit comme un défaut
	 * plutôt que comme un groupe sans type.
	 *
	 * Le libellé rendu est l'un des `s()` du describer de `communityType`, un enum fermé du
	 * schéma : aucune saisie ne l'atteint, et les bandeaux qui l'affichent le concatènent sans
	 * `encode()`. Une valeur d'une autre origine rendue ici les rendrait tous vulnérables.
	 */
	public static function getCommunityType(Structure $eStructure): string {

		$eStructure->expects(['communityType']);

		if($eStructure['communityType'] === NULL) {
			return '';
		}

		return self::p('communityType')->values[$eStructure['communityType']];

	}

	/**
	 * L'icône d'un onglet du bandeau d'un groupe (`StructureCommunityUi::getTabs()`), que la bande
	 * claire de ses écrans montre en vignette. Un écran qui n'allume aucun onglet, comme les
	 * notifications, n'a pas de vignette.
	 */
	public static function getCommunityTabIcon(string $tab): string {

		return match($tab) {
			'' => '',
			'home' => 'speedometer2',
			'members' => 'people-fill',
			'structures', 'schools' => 'producer-fill',
			'production' => 'flower1',
			'learning' => 'mortarboard-fill',
			'quotation' => 'clipboard-data',
			'settings' => 'gear-fill',
		};

	}

	/**
	 * L'UI qui répond pour cette structure.
	 *
	 * ⚠️ Le type se résout **ici et une seule fois**, y compris celui d'un groupe : chaque
	 * communauté a sa classe, qui porte ses mots et ce qu'elle fait de différent. Un cinquième
	 * type de groupe est une classe de plus et pas un `match` de plus au fond d'un écran.
	 *
	 * `StructureCommunityUi` reste la classe de base **et** le défaut : une communauté de
	 * producteurs, ou un groupe dont le type n'est pas encore choisi, n'ont rien à redéfinir.
	 */
	public static function getClass(Structure $eStructure): string {

		return match($eStructure['type']) {
			Structure::PRODUCER => '\structure\StructureProducerUi',
			Structure::SHOP => '\structure\StructureShopUi',
			Structure::AMAP => '\structure\StructureAmapUi',
			Structure::COMMUNITY => match($eStructure['communityType']) {
				Structure::EDUCATION => '\structure\StructureEducationUi',
				Structure::NETWORK => '\structure\StructureNetworkUi',
				Structure::ACCOUNTING => '\structure\StructureAccountingUi',
				default => '\structure\StructureCommunityUi'
			},
		};

	}

	/**
	 * Le mot que ce type de structure emploie pour cette idée.
	 *
	 * ⚠️ **Une clé inconnue ne fait pas tomber l'écran.** Le `match` de chaque classe n'a pas de
	 * `default` et lève un `UnhandledMatchError` : c'est juste sur un enum, dont toute valeur hors
	 * jeu est un bug qu'il faut voir tomber, mais `$text` est une chaîne libre que l'appelant
	 * choisit. Un écran peut légitimement demander un mot que ce type-là n'a jamais eu à dire.
	 *
	 * Le coût de la disproportion est mesuré : la clé `updateLegal` manquait à `StructureProducerUi`
	 * et emportait le panneau juridique de deux fermes actives (12/09/2026). Un titre vide se voit
	 * et se répare ; une page blanche cache ce qui l'a causée.
	 *
	 * ⚠️ Ce filet n'excuse pas une clé manquante, et il n'efface pas le refus **délibéré** : une
	 * classe qui ne veut pas d'une clé l'écrit `=> throw new \UnsupportedException()`, comme
	 * `StructureAmapUi` pour `updateLegal`, et cette exception-là continue de remonter.
	 */
	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		$class = self::getClass($eStructure);

		try {
			return $class::getText($eStructure, $text, $value);
		} catch(\UnhandledMatchError) {
			return '';
		}

	}

	public static function link(Structure $eStructure, bool $newTab = FALSE): string {
		return '<a href="'.self::urlPlanningWeekly($eStructure).'" '.($newTab ? 'target="_blank"' : '').'>'.encode($eStructure['name']).'</a>';
	}

	public static function getPanelHeader(Structure $eStructure): string {

		$h = '<div class="panel-header-subtitle">';

			// Sans logo, le carré de `getLogo()` resterait vide et décalerait le nom de sa largeur.
			if($eStructure['logo'] !== NULL) {
				$h .= self::getLogo($eStructure, '2rem').'  ';
			}

			$h .= encode($eStructure['name']);
		$h .= '</div>';

		return $h;

	}

	public static function getOrganic(): array {
		return [
			Structure::NO => s("Non"),
			Structure::YES => s("Oui"),
			Structure::CONVERSION => s("En conversion"),
		];
	}

	public static function getOrganicLogo(string $organic, string $size): string {

		if($organic === Structure::CONVERSION) {

			// Du texte là où les autres marques sont des images : il lui faut sa propre
			// pastille pour rester lisible sur la photo d'un produit.
			\Asset::css('structure', 'structure.css');

			return '<span class="structure-organic-conversion">'.s("En conversion").'</span>';

		} else if($organic === Structure::YES) {
			return \Asset::image('structure', 'organic.png', ['style' => 'height: '.$size]);
		} else {
			return '';
		}

	}

	public static function getQualitiesLabels(): array {
		return [
			Structure::DEMETER => s("Demeter"),
			Structure::NATURE_PROGRES => s("Nature & Progrès"),
			Structure::BIO_COHERENCE => s("Bio Cohérence"),
		];
	}

	public static function getQualitiesLogos(?\Set $qualities, string $size): string {

		if($qualities === NULL) {
			return '';
		}

		$list = '';

		foreach(Structure::model()->getPropertySet('qualities') as $quality) {

			if($qualities->value($quality)) {
				$list .= self::getQualityLogo($quality, $size);
			}

		}

		return $list;

	}

	public static function getQualityLogo(int $quality, string $size): string {
		return \Asset::image('structure', Structure::getQualities()[$quality].'.png', ['style' => 'height: '.$size]);
	}

	public static function websiteLink(Structure $eStructure, bool $newTab = TRUE): string {
		if($eStructure['url'] !== NULL) {
			return '<a href="'.encode($eStructure['url']).'" '.($newTab ? 'target="_blank"' : '').'>'.encode($eStructure['name']).'</a>';
		} else {
			return encode($eStructure['name']);
		}
	}

	public static function url(Structure $eStructure): string {

		$eStructure->expects(['type']);

		return match($eStructure['type']) {
			Structure::COMMUNITY => '/groupe/'.$eStructure['id'],
			Structure::PRODUCER, Structure::SHOP => '/ferme/'.$eStructure['id'],
			Structure::AMAP => $eStructure->getAmapUrl(),
		};
	}

	/**
	 * L'adresse de la fiche publique d'une ferme.
	 *
	 * Distincte de url(), qui rend la même chose pour un SHOP : la fiche le refuse, et c'est ici
	 * que la borne se pose plutôt qu'après coup. Une structure qui n'en a pas rend
	 * la chaîne vide, ce que les écrans lisent déjà pour décider entre un lien et un bloc inerte.
	 *
	 * NONE passe par là aussi, et c'est la raison d'être du point unique : « Personne » retire la
	 * page sans effacer le nom, et les trois écrans qui mènent à une fiche le lisent déjà ainsi.
	 */
	public static function urlProfile(Structure $eStructure): string {

		$eStructure->expects(['type', 'profileVisibility']);

		return (
			$eStructure['type'] === Structure::PRODUCER and
			$eStructure['profileVisibility'] !== Structure::NONE
		) ? '/ferme/'.$eStructure['id'] : '';

	}

	public static function urlBase(Structure $eStructure): string {

		if($eStructure->empty()) {
			$eStructure = Structure::getConnected();
		}

		return '/'.$eStructure['id'];
	}

	public static function urlStructure(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'];
	}

	/**
	 * Le contenu d'un interstitiel, chargé par-dessus la page qui le demande.
	 */
	public static function urlInterstitial(Structure $eStructure, string $name): string {
		return self::urlStructure($eStructure).'/interstitiel/'.$name;
	}

	public static function urlCommunity(Structure $eStructure, \structure\Section $eSection = new \structure\Section()): string {

		// ⚠️ Le tableau de bord est le seul écran de portée sans suffixe à lui : il prend
		// `/section/{id}`, la forme que le produit emploie déjà pour les portées — la saison, la
		// région. L'adresse nue reste l'adresse nue, aucune sentinelle du genre `/section/none`.
		return '/groupe/'.$eStructure['id'].($eSection->notEmpty() ? '/section/'.$eSection['id'] : '');

	}

	/**
	 * Le sous-groupe regardé, en segment de l'adresse et non en paramètre.
	 *
	 * ⚠️ Il se pose **avant** la requête : `urlCommunityCompare()` rend déjà `?season=&plant=`, et
	 * une portée concaténée à la fin s'y serait rangée comme une option de plus. Une adresse ne se
	 * compose pas par ajout de chaîne.
	 */
	private static function urlCommunitySection(string $url, \structure\Section $eSection): string {
		return $url.($eSection->notEmpty() ? '/'.$eSection['id'] : '');
	}

	public static function urlCommunitySchools(Structure $eStructure): string {
		return self::urlCommunityFarms($eStructure).'/ecole';
	}

	public static function urlCommunityFarmsManage(Structure $eStructure): string {
		return self::urlCommunityFarms($eStructure).'/gerer';
	}

	public static function urlCommunityFarmsMine(Structure $eStructure): string {
		return self::urlCommunity($eStructure).'/mes-fermes';
	}

	public static function urlCommunityFarms(Structure $eStructure, \structure\Section $eSection = new \structure\Section()): string {
		return self::urlCommunitySection(self::urlCommunity($eStructure).'/fermes', $eSection);
	}

	public static function urlCommunityMembers(Structure $eStructure, \structure\Section $eSection = new \structure\Section()): string {
		return self::urlCommunitySection(self::urlCommunity($eStructure).'/membres', $eSection);
	}

	public static function urlCommunityVegetable(Structure $eStructure, \structure\Section $eSection = new \structure\Section()): string {
		return self::urlCommunitySection(self::urlCommunity($eStructure).'/maraichage', $eSection);
	}

	public static function urlCommunityCompare(Structure $eStructure, \structure\Section $eSection = new \structure\Section()): string {
		return self::urlCommunitySection(self::urlCommunity($eStructure).'/comparer', $eSection);
	}

	public static function urlCommunityLearning(Structure $eStructure): string {
		return self::urlCommunity($eStructure).'/formations';
	}

	// L'adresse ne porte pas le mot de l'écran : le titre vient de getText() et vaut « promotions »,
	// « groupes de travail » ou « antennes » selon le type, quand l'adresse, elle, ne bouge pas.
	public static function urlCommunitySections(Structure $eStructure): string {
		return self::urlCommunity($eStructure).'/sous-groupes';
	}

	/**
	 * `$inviteKey`, la clé de l'invitation d'AMAP qui a fait créer la structure, et que la page d'arrivée
	 * propose d'accepter en premier.
	 */
	public static function urlStart(Structure $eStructure, ?string $inviteKey = NULL): string {
		return '/structure/structure:start?id='.$eStructure['id'].(($inviteKey !== NULL) ? '&inviteKey='.urlencode($inviteKey) : '');
	}

	public static function urlAmapDashboard(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/tableau-de-bord';
	}

	public static function urlAmapCustomers(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/adherents';
	}

	public static function urlAmapWallets(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/cagnottes-adherents';
	}

	public static function urlAmapProducers(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/producteurs';
	}

	public static function urlAmapReferents(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/referents';
	}

	public static function urlAmapDistributions(Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/distributions';
	}

	public static function urlStructureGeneral(Structure $eStructure): string {
		return '/structure/structure:update?id='.$eStructure['id'];
	}

	public static function getSettingsGeneralName(Structure $eStructure): string {
		return $eStructure->isAmap() ? self::getText($eStructure, 'settingsGeneral') : s("Paramétrage");
	}

	public static function urlSharing(Structure $eStructure): string {

		if($eStructure->isCommunity()) {
			return self::urlStructureGeneral($eStructure).'&tab=structure-sharing';
		} else {
			return self::urlStructureGeneral($eStructure).'#structure-sharing';
		}

	}

	/**
	 * Le profil public d'une structure : ce qu'elle montre, et à qui.
	 *
	 * Les cinq premiers champs existaient déjà et se réglaient à l'administratif, où ils
	 * voisinaient le SIRET et l'e-mail légal. Ils vivent ici parce qu'ils font une même chose :
	 * donner un visage. La bascule les gouverne tous.
	 */
	public function updateProfile(Structure $eStructure, \structure\System $eSystem, \Collection $cProduction): string {

		$ui = new \structure\ProfileAiUi();

		// ⚠️ L'aperçu se rend par le **code de la fiche**, jamais par une reproduction : deux
		// rendus d'une même chose divergent au premier changement, et c'est l'aperçu qui devient
		// faux — celui que personne ne recette, puisqu'il a l'air juste.
		//
		// Le voisinage n'y est pas, et ce n'est pas un oubli : il est borné au lecteur, donc il
		// dirait ce que **cette** personne voit et non ce que la fiche montre d'elle-même.
		// Le bandeau dit à qui la fiche est montrée, ce qui n'a de sens que là où l'audience se
		// règle : sous le drapeau baissé, le champ de visibilité n'existe pas et la phrase
		// annoncerait un réglage introuvable.
		$h = FEATURE_INRAE ? '<div class="util-block-info"><p>'.self::getProfileAudience($eStructure).'</p></div>' : '';

		// Sans son propre .container : la page de réglage est déjà dans celui du gabarit, et
		// .container se dimensionne sur l'écran, pas sur son parent.
		$h .= $ui->getIdentity($eStructure, container: FALSE);

		$blocks = $ui->getScene($eSystem, $cProduction);
		$blocks .= $ui->getProductions($cProduction);

		// Même règle que la fiche : une page qui n'a que son titre se lit comme une panne, et le
		// message ne s'écrit qu'une fois qu'on sait qu'aucun bloc n'a de contenu.
		$h .= ($blocks === '') ? $ui->getEmptyNotice($eStructure, TRUE) : $blocks;

		return $h;

	}

	/**
	 * Le formulaire du profil, sur sa propre page.
	 *
	 * Séparé de l'aperçu et non déplié sous lui : un écran qui porte les deux oblige à défiler
	 * entre un champ et son effet pour vérifier ce qu'on vient de changer.
	 */
	public function updateProfileForm(Structure $eStructure): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/structure:doUpdateProfile', ['id' => 'structure-update-profile']);

			$h .= $form->hidden('id', $eStructure['id']);

			$h .= $form->group(
				(self::p('logo')->label)($eStructure),
				new \media\StructureLogoUi()->getCamera($eStructure, size: '10rem')
			);

			$h .= $form->group(
				(self::p('banner')->label)($eStructure),
				new \media\StructureBannerUi()->getCamera($eStructure, width: '32rem', height: 'auto')
			);

			// Même règle que le bandeau : la visibilité ne se propose que sous le drapeau.
			$properties = ['startedAt', 'url', 'description'];

			if(FEATURE_INRAE) {
				$properties[] = 'profileVisibility';
			}

			$h .= $form->dynamicGroups($eStructure, $properties);

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return $h;

	}


	/**
	 * À qui cette fiche est montrée, dit sur la page qui la règle.
	 *
	 * Un aperçu qui ne dit pas son audience ne renseigne qu'à moitié : on voit ce qui s'affiche
	 * sans savoir devant qui. Les trois phrases suivent les trois valeurs de `profileVisibility`.
	 */
	public static function getProfileAudience(Structure $eStructure): string {

		$eStructure->expects(['profileVisibility']);

		return match($eStructure['profileVisibility']) {
			Structure::PUBLIC => s("Cette fiche est visible de tout le monde, y compris sans compte."),
			Structure::NONE => s("Cette fiche n'est visible de personne : seuls les membres de la ferme peuvent l'ouvrir."),
			default => s("Cette fiche est visible des groupes dont vous êtes membre.")
		};

	}

	public static function urlStructureProfile(Structure $eStructure): string {
		return '/structure/structure:updateProfile?id='.$eStructure['id'];
	}

	public static function urlStructureProfileEdit(Structure $eStructure): string {
		return '/structure/structure:updateProfileEdit?id='.$eStructure['id'];
	}

	public static function urlStructureVat(Structure $eStructure): string {
		return '/structure/configuration:updateVat?id='.$eStructure['id'];
	}

	public static function urlStructureApi(Structure $eStructure): string {
		return '/structure/apiKey:manage?structure='.$eStructure['id'];
	}

	public static function urlDataExport(Structure $eStructure): string {
		return '/structure/structure:export?id='.$eStructure['id'];
	}

	public static function urlDataImport(Structure $eStructure): string {
		return '/structure/structure:import?id='.$eStructure['id'];
	}

	public static function urlDataCustomers(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/configuration/adherents';
	}

	public static function urlStructureTeam(Structure $eStructure, ?string $view = NULL): string {

		$view ??= $eStructure->getView('viewTeamCategory');

		return match($view) {
			Member::TEAM => self::urlTeam($eStructure),
			Member::ROLE => self::urlRole($eStructure),
		};

	}

	public static function urlTeam(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/equipe';
	}

	/**
	 * L'écran des membres, qui n'est pas au même endroit selon le type de la structure.
	 *
	 * ⚠️ `route.c.php` ne route `/ferme/{id}/equipe` que pour une ferme : y renvoyer un groupe rend
	 * « La page que vous avez demandée n'existe pas ! » sous un message de succès.
	 */
	public static function urlMembers(Structure $eStructure): string {

		$eStructure->expects(['type']);

		return $eStructure->isCommunity()
			? self::urlCommunityMembers($eStructure)
			: self::urlTeam($eStructure);

	}

	public static function urlRole(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/roles';
	}

	public static function urlInvoicingOnboarding(\structure\Structure $eStructure = new Structure()): string {
		return self::urlConnected('/pdp/:onboarding', $eStructure);
	}

	public static function urlInvoicingSell(\structure\Structure $eStructure = new Structure()): string {
		return self::urlBase($eStructure).'/facturation-electronique/ventes/';
	}

	public static function urlInvoicingBuy(\structure\Structure $eStructure = new Structure()): string {
		return self::urlBase($eStructure).'/facturation-electronique/achats/';
	}

	public static function urlInvoicingReporting(\structure\Structure $eStructure = new Structure()): string {
		return self::urlBase($eStructure).'/facturation-electronique/e-reporting/';
	}

	public static function urlBankOperations(\structure\Structure $eStructure = new Structure()): string {
		return self::urlBase($eStructure).'/banque/operations';
	}

	public static function urlAssets(\structure\Structure $eStructure = new Structure()): string {
		return self::urlFinancialYear(eStructure: $eStructure).'/immobilisations';
	}

	public static function urlOverview(\structure\Structure $eStructure = new Structure(), string $view = '', ?\financialYear\FinancialYear $eFinancialYear = NULL): string {
		return self::urlFinancialYear($eFinancialYear, $eStructure).'/etats-financiers/'.$view;
	}

	public static function urlJournal(\structure\Structure $eStructure = new Structure(), ?\financialYear\FinancialYear $eFinancialYear = NULL): string {
		return \structure\StructureUi::urlFinancialYear($eFinancialYear, $eStructure).'/journal';
	}

	public static function urlBank(\structure\Structure $eStructure = new Structure(), ?\financialYear\FinancialYear $eFinancialYear = NULL): string {
		return \structure\StructureUi::urlFinancialYear($eFinancialYear, $eStructure).'/bank';
	}

	public static function urlPlanning(Structure $eStructure, ?string $view = NULL): string {

		$view ??= $eStructure->getView('viewPlanning');

		return match($view) {
			Member::DAILY => self::urlPlanningDaily($eStructure),
			Member::WEEKLY => self::urlPlanningWeekly($eStructure),
			Member::YEARLY => self::urlPlanningYear($eStructure)
		};

	}

	public static function urlPlanningBase(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/planning';
	}

	public static function urlPlanningDaily(Structure $eStructure, ?string $week = NULL): string {

		$week ??= \Clock::week();

		$url = self::urlPlanningBase($eStructure).'/'.Member::DAILY;

		if($week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		return $url;

	}

	public static function urlPlanningWeekly(Structure $eStructure, ?string $week = NULL): string {

		$week ??= \Clock::week();

		$url = self::urlPlanningBase($eStructure).'/'.Member::WEEKLY;

		if($week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		return $url;

	}

	public static function urlPlanningAction(Structure $eStructure, string $week, \work\Action $eAction): string {
		return self::urlStructure($eStructure).'/intervention/'.$week.'/'.$eAction['id'];
	}

	public static function urlPlanningYear(Structure $eStructure, ?int $year = NULL, ?int $month = NULL): string {

		$year ??= \Clock::year();
		$month ??= (int)\Clock::format('n');

		$url = self::urlPlanningBase($eStructure).'/'.Member::YEARLY;

		if($month !== (int)\Clock::format('n') or $year !== \Clock::year()) {
			$url .= '/'.$year.'/'.$month;
		}

		return $url;

	}

	public static function urlAnalyzeWorkingTime(Structure $eStructure, ?int $year = NULL, ?string $category = NULL): string {
		return self::urlStructure($eStructure).'/analyses/planning'.($year ? '/'.$year : '').($category ? '/'.$category : '');
	}

	public static function urlCultivation(Structure $eStructure, string $view, ?string $subView = NULL): string {

		return match($view) {
			'series' => self::urlCultivationSeries($eStructure, $subView),
			'forecast' => self::urlCultivationForecast($eStructure),
			'seedling' => self::urlCultivationSeedling($eStructure),
			'soil' => self::urlCultivationSoil($eStructure, $subView),
			'sequence' => self::urlCultivationSequences($eStructure),
			'map' => \map\PlanUi::urlView($eStructure)
		};

	}

	public static function urlCultivationSeries(Structure $eStructure, ?string $view = NULL, ?int $season = NULL): string {

		$view ??= $eStructure->getView('viewSeries');

		return self::urlStructure($eStructure).'/series'.($season ? '/'.$season : '').'?view='.$view;

	}

	public static function urlCultivationForecast(Structure $eStructure, ?int $season = NULL): string {

		return self::urlStructure($eStructure).'/previsionnel'.($season ? '/'.$season : '');

	}

	public static function urlCultivationSeedling(Structure $eStructure, ?int $season = NULL): string {

		return self::urlStructure($eStructure).'/semences-plants'.($season ? '/'.$season : '');

	}

	public static function urlCultivationSoil(Structure $eStructure, ?string $view = NULL, ?int $season = NULL): string {

		$view ??= $eStructure->getView('viewSoil');

		return match($view) {
			Member::PLAN => self::urlSoil($eStructure, $season),
			Member::GARDEN => self::urlSoil($eStructure, $season),
			Member::ROTATION => self::urlHistory($eStructure, $season),
		};

	}

	public static function urlCultivationSequences(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/itineraires';
	}


	public static function urlSoil(Structure $eStructure, ?int $season = NULL): string {
		return self::urlStructure($eStructure).'/assolement'.($season ? '/'.$season : '');
	}

	public static function urlHistory(Structure $eStructure, ?int $season = NULL): string {
		return self::urlStructure($eStructure).'/rotation'.($season ? '/'.$season : '');
	}

	public static function urlSelling(Structure $eStructure, string $view): string {

		return match($view) {
			'sale' => self::urlSellingSales($eStructure),
			'invoice' => self::urlSellingInvoices($eStructure),
			'shop' => self::urlSellingShop($eStructure),
			'amap' => self::getObserverEntries('getCategoryUrls', $eStructure)['amap-producer']['list'],
			'product' => self::urlSellingProducts($eStructure),
			'stock' => self::urlSellingStock($eStructure),
			'catalog' => self::urlSellingCatalog($eStructure),
			'wallet' => self::urlSellingCustomersWallet($eStructure),
			'customer' => self::urlSellingCustomers($eStructure),
			'market' => self::urlSellingMarket($eStructure),
		};

	}

	public static function urlSellingStock(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/stocks';
	}

	public static function urlSellingInvoices(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/factures';
	}

	public static function urlSellingProducts(Structure $eStructure, ?string $view = NULL): string {

		$view ??= $eStructure->getView('viewSellingProducts');

		return match($view) {
			Member::PRODUCT => self::urlSellingProductsAll($eStructure),
			Member::CATEGORY => self::urlSellingProductsCategories($eStructure),
		};

	}

	public static function urlSellingProductsAll(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/produits';
	}

	public static function urlSellingProductsCategories(Structure $eStructure): string {
		return '/product/category:manage?structure='.$eStructure['id'];
	}

	public static function urlSellingCustomers(Structure $eStructure, ?string $view = NULL): string {

		$view ??= $eStructure->getView('viewSellingCustomers');

		return match($view) {
			Member::CUSTOMER => self::urlSellingCustomersAll($eStructure),
			Member::CUSTOMER_PRIVATE => self::urlSellingCustomersPrivate($eStructure),
			Member::CUSTOMER_PRO => self::urlSellingCustomersPro($eStructure),
			Member::GROUP => self::getObserverEntries('getCategoryUrls', $eStructure)['selling']['group'],
			Member::WALLET => self::urlSellingCustomersWallet($eStructure),
		};

	}

	public static function urlSellingCustomersAll(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/clients';
	}

	public static function urlSellingCustomersPrivate(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/clients/particuliers';
	}

	public static function urlSellingCustomersPro(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/clients/professionnels';
	}

	public static function urlSellingCustomersWallet(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/cagnottes';
	}

	public static function urlSellingSales(Structure $eStructure, ?string $view = NULL): string {

		$view ??= $eStructure->getView('viewSellingSales');

		return match($view) {
			Member::SALE => self::urlSellingSalesAll($eStructure),
			Member::SALE_PRIVATE => self::urlSellingSalesPrivate($eStructure),
			Member::SALE_PRO => self::urlSellingSalesPro($eStructure),
			Member::PURCHASE => self::urlSellingPurchases($eStructure),
			Member::LABEL => self::urlSellingSalesLabel($eStructure),
			Member::ARCHIVES => self::urlSellingSalesArchives($eStructure),
		};

	}

	public static function urlSellingSalesAll(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/ventes';
	}

	public static function urlSellingSalesPrivate(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/ventes/particuliers';
	}

	public static function urlSellingSalesPro(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/ventes/professionnels';
	}

	public static function urlSellingPurchases(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/achats';
	}

	public static function urlSellingMarket(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/caisse';
	}

	public static function urlSellingSalesInvoice(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/factures';
	}

	public static function urlSellingSalesLabel(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/etiquettes';
	}

	public static function urlSellingSalesArchives(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/archives';
	}

	public static function urlSellingShop(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/boutiques';
	}

	public static function urlSellingCatalog(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/catalogues';
	}

	public static function urlCommunications(Structure $eStructure, string $view, ?string $subView = NULL): string {

		return match($view) {
			'website' => self::urlCommunicationsWebsite($eStructure),
			'mailing' => self::urlCommunicationsMailing($eStructure, $subView),
			default => self::urlCommunicationsWebsite($eStructure),
		};

	}

	public static function urlCommunicationsWebsite(Structure $eStructure): string {
		return '/website/manage?id='.$eStructure['id'];
	}

	public static function urlCommunicationsMailing(Structure $eStructure, ?string $view = NULL, ?int $season = NULL): string {

		$view ??= $eStructure->getView('viewMailingCategory');

		return match($view) {
			Member::CAMPAIGN => self::urlCommunicationsCampaign($eStructure),
			Member::CONTACT => self::urlCommunicationsContact($eStructure),
		};

	}

	public static function urlCommunicationsCampaign(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/campagnes';
	}

	public static function urlCommunicationsContact(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/contacts';
	}

	public static function urlAnalyzeReport(Structure $eStructure, ?int $season = NULL): string {
		return self::urlStructure($eStructure).'/analyses/rapports'.($season ? '/'.$season : '');
	}

	public static function urlIntelligenceVegetable(Structure $eStructure, string $view): string {

		return match($view) {
			'intelligence-crop-plan' => self::urlIntelligenceCropPlan($eStructure),
			'intelligence-variety' => self::urlIntelligenceVariety($eStructure)
		};

	}

	public static function urlIntelligenceCropPlan(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/bibliotheque';
	}

	public static function urlIntelligenceVariety(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/varietes';
	}

	public static function urlIntelligenceVarietyCriteria(Structure $eStructure): string {
		return self::urlIntelligenceVariety($eStructure).'/criteres';
	}

	public static function urlIntelligenceVarietyRating(Structure $eStructure): string {
		return self::urlIntelligenceVariety($eStructure).'/voter';
	}

	public static function urlAnalyzeProduction(Structure $eStructure, string $view = 'cultivation'): string {

		return match($view) {
			'working-time' => self::urlAnalyzeWorkingTime($eStructure),
			'cultivation' => self::urlAnalyzeCultivation($eStructure)
		};

	}

	public static function urlAnalyzeCommercialisation(Structure $eStructure, string $view = 'sales'): string {

		return match($view) {
			'report' => self::urlAnalyzeReport($eStructure),
			'sales' => self::urlAnalyzeSelling($eStructure),
			'quotation' => self::getObserverEntries('getCategoryUrls', $eStructure)['quotation']['view'],
		};

	}

	public static function urlAnalyzeAccounting(Structure $eStructure, string $view = \overview\DashboardAiLib::TAB_RESULT): string {
		return self::urlBase($eStructure).'/analyses/comptabilite/'.$view;
	}

	public static function urlAnalyzeSelling(Structure $eStructure, ?int $year = NULL, ?string $category = NULL): string {
		return self::urlStructure($eStructure).'/analyses/ventes'.($year ? '/'.$year : '').($category ? '/'.$category : '');
	}

	public static function urlAnalyzeCultivation(Structure $eStructure, ?int $season = NULL, ?string $category = NULL): string {
		return self::urlStructure($eStructure).'/analyses/cultures'.($season ? '/'.$season : '').($category ? '/'.$category : '');
	}

	public static function urlSettingsProduction(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/configuration/production';
	}

	public static function urlSettingsFeatures(Structure $eStructure): string {
		return '/structure/feature:manage?id='.$eStructure['id'];
	}

	/** Une AMAP règle ses distributions sur sa page Paramètres, avec le reste. */
	public static function urlSettingsCommercialisation(Structure $eStructure): string {
		return $eStructure->isAmap() ? self::urlAmapSettings($eStructure) : self::urlStructure($eStructure).'/configuration/commercialisation';
	}

	/**
	 * La page Paramètres d'une AMAP, qu'apporte son package : seul l'observateur en connaît l'adresse.
	 * Elle ne se demande que pour une AMAP, les appelants et la route posant `isAmap()`.
	 */
	public static function urlAmapSettings(Structure $eStructure): string {
		return self::getObserverEntries('getCategoryUrls', $eStructure)['feature']['settings-amap'];
	}

	public static function urlPaymentCard(Structure $eStructure): string {
		return self::urlStructure($eStructure).'/configuration/paiement-carte';
	}

	public static function urlPaymentCardSumup(Structure $eStructure): string {
		return self::urlPaymentCard($eStructure).'/sumup';
	}

	public static function urlPaymentCardSwitch(Structure $eStructure): string {
		return self::urlPaymentCard($eStructure).'/ouvretaferme';
	}

	public static function urlSettingsAccounting(Structure $eStructure, ?\financialYear\FinancialYear $eFinancialYear = NULL): string {
		return self::urlFinancialYear($eFinancialYear, $eStructure).'/configuration/accounting';
	}

	public static function urlSettingsInvoicing(Structure $eStructure): string {
		return  '/pdp/?structure='.$eStructure['id'];
	}

	public static function urlCash(\cash\Register $eRegister = new \cash\Register(), Structure $eStructure = new Structure()): string {
		return self::urlBase($eStructure).'/cahier-de-caisse'.($eRegister->notEmpty() ? '?register='.$eRegister['id'] : '');
	}

	public static function urlProofs(Structure $eStructure = new Structure()): string {
		return self::urlBase($eStructure).'/justificatifs';
	}

	public static function urlFinancialYear(?\financialYear\FinancialYear $eFinancialYear = NULL, Structure $eStructure = new Structure()): string {

		if($eStructure->empty()) {
			$eStructure = Structure::getConnected();
		}

		$eFinancialYear ??= $eStructure['eFinancialYear'] ?? NULL;

		if($eFinancialYear === NULL) {
			$financialYearViewed = $eStructure->getViewCached('viewAccountingYear');
		} else {
			$financialYearViewed = $eFinancialYear->empty() ? NULL : $eFinancialYear['id'];
		}

		$url =  self::urlBase($eStructure);

		if($financialYearViewed !== NULL) {
			$url .= '/exercice/'.$financialYearViewed;
		}

		return $url;

	}

	public static function urlAccountingArticles(Structure $eStructure = new Structure(), string $path = '', ?string $category = NULL): string {

		$query = [];

		if($eStructure->notEmpty()) {
			$query['structure'] = $eStructure['id'];
		}

		if($category !== NULL) {
			$query['category'] = $category;
		}

		return '/articles/'.$path.($query ? '?'.http_build_query($query) : '');

	}

	public static function urlConnected(string $url, Structure $eStructure = new Structure()): string {

		if($eStructure->empty()) {
			$eStructure = Structure::getConnected();
		}

		return $url.'?structure='.$eStructure['id'];

	}

	/**
	 * L'écran qui suit la création d'une ferme.
	 *
	 * Une carte-lien par fonctionnalité plutôt que sept colonnes de puces : ce qu'on
	 * demande ici est de choisir par où commencer, et une liste de tout ce que le
	 * logiciel sait faire est le meilleur moyen de ne rien choisir. Même vocabulaire
	 * que l'interstitiel d'arrivée par les relevés de prix — bandeau, pastille de domaine,
	 * chevron —, parce que c'est le même moment : la première minute dans le produit.
	 */
	public function getStarted(Structure $eStructure): string {

		$h = \main\DesignUi::getWelcome(
			s("Votre structure est créée sur {siteName} !"),
			'<p>'.s("Tout ce qui suit est maintenant possible. Choisissez par quoi vous voulez démarrer.").'</p>',
			\Asset::svg('main', 'friends/abeille.svg')
		);

		// Qui crée sa ferme parce qu'une AMAP l'invite à la livrer est venu pour cela : l'invitation
		// passe devant tout ce que le logiciel sait faire d'autre.
		$h .= implode('', self::getObserverEntries('getStartedInvitations', $eStructure));

		$h .= '<div class="util-cards util-cards-2">';

			foreach($this->getStartedFeatures($eStructure) as $feature) {

				// Une entrée peut ne pas s'adresser à cette ferme : `display` le dit, et son
				// absence vaut « toujours ». La condition vit avec l'entrée qu'elle gouverne
				// plutôt que dans cette boucle, où elle s'en éloignerait au premier ajout.
				if(($feature['display'] ?? TRUE) === FALSE) {
					continue;
				}

				$h .= \main\DesignUi::getDeckEntry($feature['url'], $feature['icon'], $feature['domain'], $feature['title'], $feature['text']);

			}

		$h .= '</div>';

		$h .= implode('', self::getObserverEntries('getStartedJoins', $eStructure));

		$h .= '<div class="util-buttons mt-2">';
			// Sans commercialisation/, absent de la version publique, les ventes n'ont pas d'écran
			$h .= '<a href="'.($eStructure->acceptCommercialisation() ? self::urlSellingSales($eStructure) : self::url($eStructure)).'" class="btn btn-lg btn-outline-primary">'.s("Passer cette étape").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qu'on propose, dans l'ordre où on le propose.
	 *
	 * Les relevés de prix ne sont offerts qu'aux productions qu'ils savent coter : les ouvrir à
	 * un éleveur l'enverrait sur un écran vide, la synthèse publique lui montre de
	 * quoi il s'agit sans rien lui demander.
	 *
	 * La boutique, les factures et la caisse ne s'offrent jamais sans commercialisation/, absent de la
	 * version publique, où leurs écrans n'existent pas.
	 *
	 * La facturation électronique et la comptabilité sortent de la liste sans accounting/, au lieu de
	 * prendre `display` : la liste est construite en entier avant d'être filtrée, et `urlOverview()`
	 * lit l'exercice de la comptabilité.
	 *
	 * Une entrée de `StructureSetting::START_STEPS` porte `button`, le bouton de la bande que l'accueil
	 * lui donne quand il la désigne comme premier pas, et `stepUrl` quand la bande mène ailleurs que
	 * la carte.
	 */
	public function getStartedFeatures(Structure $eStructure): array {

		return [
			[
				'name' => 'shop',
				'icon' => 'cart',
				'domain' => 'commercialisation',
				'title' => s("Vendez en ligne"),
				'text' => s("Créez une boutique, enregistrez votre gamme de produits, vos points de livraison et vos modes de règlement."),
				'url' => self::urlSellingShop($eStructure),
				'button' => s("Créer une boutique"),
				'display' => $eStructure->acceptCommercialisation(),
			],
			[
				'name' => 'invoice',
				'icon' => 'receipt',
				'domain' => 'commercialisation',
				'title' => s("Facturez vos clients"),
				'text' => s("Enregistrez vos commandes, éditez devis, bons de livraison et factures, envoyez-les par e-mail."),
				'url' => self::urlSellingCustomers($eStructure),
				// Désignée comme premier pas, l'entrée propose la vente, d'où part toute facture, à
				// l'adresse que la vente donne : la vente n'existe pas dans la version publique.
				'stepUrl' => $eStructure->acceptCommercialisation() ? self::getObserverEntries('getStartedStepUrls', $eStructure)['invoice'] : NULL,
				'button' => s("Créer une vente"),
				'display' => $eStructure->acceptCommercialisation(),
			],
			...(StructureSetting::hasAccountingApp() ? [
				[
					'name' => 'pdp',
					// Une icône et non le logo de la réforme : réduit à la taille d'une pastille,
					// il n'est plus lisible et ne se reconnaît pas.
					'icon' => 'file-earmark-text',
					'domain' => 'commercialisation',
					'title' => s("Passez à la facturation électronique"),
					'text' => s("Recevez les factures de vos fournisseurs, envoyez les vôtres, gérez votre e-reporting."),
					'url' => self::urlInvoicingOnboarding($eStructure),
				],
			] : []),
			[
				'name' => 'market',
				'icon' => 'cash-register',
				'domain' => 'commercialisation',
				'title' => s("Tenez votre caisse sur les marchés"),
				'text' => s("Enregistrez vos ventes au marché, éditez des tickets, sachez ce que vous avez vendu en fin de journée."),
				'url' => self::urlSellingMarket($eStructure),
				'button' => s("Voir mes marchés"),
				'display' => $eStructure->acceptCommercialisation(),
			],
			...(StructureSetting::hasAccountingApp() ? [
				[
					'name' => 'accounting',
					'icon' => 'piggy-bank',
					'domain' => 'accounting',
					'title' => s("Tenez votre comptabilité"),
					'text' => s("Import bancaire, rapprochement, TVA et bilan, jusqu'au compte de résultat."),
					// Sans condition sur la ferme : une ferme dont la comptabilité n'est pas ouverte est
					// redirigée par le produit vers « Découvrir la comptabilité », et l'URL tient
					// sans exercice comptable.
					'url' => self::urlOverview($eStructure),
				],
			] : []),
			[
				'name' => 'planning',
				'icon' => 'leaf',
				'domain' => 'production',
				'title' => s("Planifiez vos cultures"),
				'text' => s("Plan de culture ou d'assolement, planning de travail hebdomadaire et suivi du temps de travail."),
				'url' => \map\PlanUi::url($eStructure),
				// Désignée comme premier pas, l'entrée mène au plan de culture : une série se crée sans
				// parcelle, et le plan de la ferme se dessine ensuite.
				'stepUrl' => self::urlCultivationSeries($eStructure),
				'button' => s("Ouvrir le plan de culture"),
			],
			[
				'name' => 'quotation',
				'icon' => 'graph-up',
				'domain' => 'commercialisation',
				'title' => s("Relevés de prix"),
				'text' => s("Comparez vos prix à ceux des autres producteurs, par produit et par région, et voyez où vous vous situez."),
				'url' => $eStructure->acceptQuotation() ? self::getObserverEntries('getCategoryUrls', $eStructure)['quotation']['home'] : NULL,
				// Les relevés de prix ne cotent que du végétal : proposée à un éleveur, l'entrée
				// mènerait à un écran qui la refuse.
				'display' => $eStructure->acceptQuotation(),
			],
			[
				'name' => 'website',
				'icon' => 'megaphone',
				'domain' => 'settings',
				'title' => s("Créez votre site internet"),
				'text' => s("Autant de pages et de photos que vous voulez, avec votre nom de domaine si vous en avez un."),
				'url' => self::urlCommunicationsWebsite($eStructure),
				'button' => s("Créer mon site"),
			],
		];

	}

	public function create(Structure $eStructure): \Panel {

		return new \Panel(
			id: 'panel-structure-create',
			title: self::getInstance($eStructure)->getCreateTitle($eStructure),
			body: $this->getCreate($eStructure)
		);

	}

	public function getCreate(Structure $eStructure): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/structure:doCreate', ['id' => 'structure-create', 'autocomplete' => 'off']);

			$h .= self::getInstance($eStructure)->getCreateForm($form, $eStructure);

		$h .= $form->close();

		return $h;

	}

	public function createShop(Structure $eStructure): \Panel {

		$uiStructure = self::getInstance($eStructure);

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/structure/structure:doCreate', ['id' => 'structure-create', 'autocomplete' => 'off']);

			$h .= $uiStructure->getCreateForm($form, $eStructure);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-structure-create',
			title: $uiStructure->getCreateTitle($eStructure),
			body: $h
		);

	}

	public function updateLegal(Structure $eStructure): \Panel {

		return new \Panel(
			id: 'panel-structure-update-legal',
			title: s("Informations requises pour continuer"),
			body: $this->getLegalForm($eStructure)
		);

	}

	public function getLegalForm(Structure $eStructure, bool $onlyCountry = FALSE): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/structure:'.($onlyCountry ? 'doUpdateCountry' : 'doUpdateLegal'), ['autocomplete' => 'off', 'class' => 'structure-legal-form']);

			if($onlyCountry) {
				$h .= '<h3>'.s("Pour commencer").'</h3>';
			}

			$h .= '<div class="util-info">';
				$h .= $onlyCountry ?
					s("Nous avons besoin que vous confirmiez définitivement le pays de votre structure pour continuer.") :
					s("Nous avons besoin que vous fournissiez les informations légales de votre structure pour continuer.");
				$h .= '<br/>'.s("La conformité réglementaire de {siteName} n'est assurée que pour la FRANCE.");
			$h .= '</div>';

			$h .= $form->hidden('id', $eStructure['id']);
			$h .= $form->hidden('verifiedCountry', TRUE);
			$h .= $form->asteriskInfo();

			$legalCountryAttribute = fn(\util\FormUi $form, Structure $e) => [
				'group' => is_array(\user\Country::form()),
				'onchange' => 'Structure.changeLegalCountry(this);',
			];

			if($eStructure->isVerifiedCountry()) {

				$h .= $form->group(
					s("Pays du siège social"),
					'<b>'.\user\Country::ask($eStructure['legalCountry'])['name'].'</b>',
				);

			} else {

				\Asset::js('main', 'place.js');

				$h .= $form->dynamicGroup($eStructure, 'legalCountry', function($d) use ($legalCountryAttribute) {
					$d->attributes = $legalCountryAttribute;
				});
			}

			if($onlyCountry === FALSE) {

				if($eStructure->isFrance()) {
					$h .= $form->dynamicGroup($eStructure, 'siret*');
				}

				$eStructure['legalName'] ??= $eStructure['name'];

				$h .= $form->dynamicGroup($eStructure, 'legalName*');
				$h .= $form->dynamicGroup($eStructure, 'legalCategory*');
				$h .= $form->addressGroup(s("Adresse du siège social").\util\FormUi::asterisk(), 'legal', $eStructure, ['country' => FALSE]);

			}

			$confirm = $eStructure->isVerifiedCountry() ? [] : ['data-confirm' => s("Le choix du pays est définitif et vous ne pourrez plus le modifier pour {this}. Validez-vous votre choix ?", ['this' => self::getText($eStructure, 'this')])];

			$h .= $form->group(
				content: $form->submit(s("Valider"), $confirm)
			);

		$h .= $form->close();

		return $h;

	}

	public function getConsentDataForm(Structure $eStructure): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/structure:doUpdateConsentData', ['autocomplete' => 'off']);

			$h .= '<h3>'.s("Pour commencer").'</h3>';

			$h .= '<div class="util-block-info">';
				$h .= \Asset::icon('people-fill', ['class' => 'util-block-icon']);
				$h .= s("Cette page est basée sur l'échange de données entre les producteurs et les productrices qui utilisent {siteName}.<br/>Nous avons besoin que vous donniez votre accord à {siteName} pour partager les données anonymisées de votre ferme.");
			$h .= '</div>';

			$h .= $this->updateConsentData($eStructure);

			$h .= '<p>'.s("Vous pourrez changer d'avis à tout moment depuis les paramètres de la ferme.").'</p>';

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le paramétrage d'une structure, en deux onglets quand il y a deux sujets.
	 *
	 * ⚠️ Des panneaux et non des liens, contrairement au chapeau Maraîchage : les deux contenus
	 * sont déjà rendus par cette même méthode, et deux routes auraient fait payer deux fois une
	 * page qu'on construit une fois. `Lime.Tab.restore` garde l'onglet ouvert d'une visite à
	 * l'autre, ce qui est ici nécessaire : les deux formulaires postent en ajax et rechargent
	 * l'écran, et revenir au premier onglet après une sauvegarde faite sur le second ferait
	 * douter qu'elle ait eu lieu.
	 *
	 * ⚠️ Pas de rangée du tout quand le second onglet n'a rien à montrer : une ferme n'a ni
	 * partage ni attente, et un groupe d'un type qui ne propose aucun partage peut n'avoir que
	 * l'attente — voire rien. Un seul onglet n'est pas un onglet.
	 */
	public function update(Structure $eStructure): string {

		$update = $this->getUpdateTabs($eStructure);

		if($update['items'] === '') {
			return $update['panels'];
		}

		return \main\DesignUi::getPageTabs($update['items'], panels: $update['panels'], attributes: $update['attributes']);

	}

	/**
	 * Le paramétrage en trois morceaux : les attributs de son `.tabs`, les onglets et les panneaux,
	 * les onglets vides quand l'écran n'en a pas. Sous un groupe, les onglets sont dans la bande de
	 * l'écran et le gabarit pose le `.tabs` autour de la bande et du corps
	 * (`GroupTemplate::$mainTabsAttributes`) ; `update()` les assemble ailleurs.
	 *
	 * @return array{attributes: array, items: string, panels: string}
	 */
	public function getUpdateTabs(Structure $eStructure): array {

		$sharing = '';
		$features = '';
		$rules = '';
		$appearance = '';

		// Le partage est un réglage du groupe : il vit là où l'on règle le groupe, et non sur un
		// écran à part. C'est là que renvoie déjà le message affiché quand rien n'est partagé.
		if($eStructure->isCommunity()) {

			$uiCommunity = new StructureCommunityUi();

			$form = $uiCommunity->getShareForm($eStructure, $eStructure->community(), $eStructure['shareCounts']);

			// Pas de bloc blanc autour : le tableau de périmètres porte déjà son propre cadre, et
			// deux fonds emboîtés donnaient deux niveaux de profondeur là où il n'y a qu'un sujet.
			$sharing .= $form;

			// ⚠️ **Les règles vivent dans le profil et non sous le partage.** Elles l'ont suivi
			// tant qu'on les lisait comme sa contrepartie — ce qu'on demande, ce qu'on promet en
			// échange. Mais elles ne disent rien des données : elles disent ce que le groupe est
			// et ce qu'il attend de qui entre, comme son nom et sa présentation. Un producteur les
			// lit à l'entrée, avant d'avoir vu une seule case à cocher.
			//
			// Elles ne dépendent donc plus du partage : un groupe qui ne demande aucune donnée
			// peut avoir des règles, et n'en avait pas le droit tant qu'elles étaient accrochées
			// à un formulaire qui ne se rendait pas.
			if($eStructure->acceptRules()) {
				$rules = '<div class="util-block">'.$uiCommunity->getRulesForm($eStructure, $eStructure->community()).'</div>';
			}

			$features = $uiCommunity->getFeatures($eStructure);

			if($eStructure['customizeSettings'] !== NULL) {
				$appearance = \learning\CustomizeUi::getCard($eStructure->community(), $eStructure['customizeSettings'], $eStructure['eCourseCustomize']);
			}

		}

		$administrative = $this->getUpdateAdministrative($eStructure, $appearance);

		if($eStructure->acceptCommunitySharing() === FALSE) {

			return [
				'attributes' => [],
				'items' => '',
				'panels' => $administrative.$rules
			];

		}

		// ⚠️ Un lien qui mène ici pour un réglage précis porte `&tab=` : sans lui, `restore()` lit
		// le `localStorage` avant le défaut, et la destination dépend du dernier onglet que ce
		// lecteur-là avait ouvert. Un bouton qui n'a qu'un seul but n'arrive pas à deux endroits
		// selon l'historique du navigateur. Le cadriciel retire ensuite le paramètre de l'URL,
		// donc un favori ne fige aucun onglet. Modèle : `website/ui/Manage.u.php`.
		$tabsAllowed = ($rules === '') ?
			['structure-administrative', 'structure-sharing', 'structure-features'] :
			['structure-administrative', 'structure-sharing', 'structure-rules', 'structure-features'];

		$tab = get_exists('tab') ? '"'.GET('tab', $tabsAllowed, 'structure-administrative').'"' : 'null';

		$tabs = '<a class="tab-item selected" data-tab="structure-administrative" onclick="Lime.Tab.select(this)">'.s("Profil du groupe").'</a>';
		$tabs .= '<a class="tab-item" data-tab="structure-sharing" onclick="Lime.Tab.select(this)">'.s("Partage des données").'</a>';
		// ⚠️ Les règles ont leur onglet et ne sont plus sous le profil : ce sont deux textes
		// que le producteur lit à deux moments — le profil dit qui est le groupe, les
		// règles disent ce qu'il attend de qui entre. Rangées à la suite de l'identité,
		// elles se lisaient comme un champ de plus de la carte de visite.
		if($rules !== '') {
			$tabs .= '<a class="tab-item" data-tab="structure-rules" onclick="Lime.Tab.select(this)">'.s("Règles du groupe").'</a>';
		}
		$tabs .= '<a class="tab-item" data-tab="structure-features" onclick="Lime.Tab.select(this)">'.s("Fonctionnalités").'</a>';

		$panels = '<div class="tab-panel selected" data-tab="structure-administrative">'.$administrative.'</div>';
		$panels .= '<div class="tab-panel" data-tab="structure-sharing">'.$sharing.'</div>';
		if($rules !== '') {
			$panels .= '<div class="tab-panel" data-tab="structure-rules">'.$rules.'</div>';
		}
		$panels .= '<div class="tab-panel" data-tab="structure-features">'.$features.'</div>';

		return [
			'attributes' => [
				'id' => 'structure-update',
				'onrender' => 'Lime.Tab.restore(this, "structure-administrative", '.$tab.')'
			],
			'items' => $tabs,
			'panels' => $panels
		];

	}

	public static function getProducerTypeReadOnly(\util\FormUi $form, Structure $eStructure): string {

		if(
			$eStructure['creation'] !== Structure::DUPLICATION or
			$eStructure['producerType'] === Structure::REAL
		) {
			return '';
		}

		$value = '<b>'.self::p('producerType')->displayValues[$eStructure['producerType']].'</b>';

		if($eStructure['producerType'] === Structure::TEST) {
			$value .= \util\FormUi::info(s("Cette ferme a été dupliquée à partir d'une autre ferme et restera toujours une ferme de test."));
		}

		return $form->group(self::p('producerType')->label, $value);

	}

	public static function getProducerTypeDecks(bool $isFirst, ?string $producerTypeChosen, bool $canCreateAmap): string {

		// La ferme de test ne se propose qu'à qui en a déjà une : elle sert à essayer sans risque à
		// côté de la sienne, et elle n'a rien à dire de l'activité de celui qui arrive.
		$producerTypes = $isFirst ? [Structure::LEARNER, Structure::INSTALLATION] : [Structure::TEST, Structure::LEARNER, Structure::INSTALLATION];

		// L'AMAP ne se propose qu'à qui n'a encore aucune structure, parce qu'elle n'est pas une
		// catégorie de ferme : elle quitte ce formulaire pour le sien. Qui a déjà une structure la
		// crée depuis Mon compte.
		$hasAmap = ($isFirst and $canCreateAmap);

		$h = '<div class="util-cards util-cards-1 mb-2">';
			$h .= self::getProducerTypeDeck(Structure::REAL, 'util-deck-lg', $producerTypeChosen);
		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-'.(count($producerTypes) + ($hasAmap ? 1 : 0)).'">';

			if($hasAmap) {
				$h .= self::getAmapDeck();
			}

			foreach($producerTypes as $producerType) {
				$h .= self::getProducerTypeDeck($producerType, 'util-deck-top', $producerTypeChosen);
			}

		$h .= '</div>';

		return $h;

	}

	private static function getAmapDeck(): string {

		$h = '<a href="/demarrer/amap" class="util-deck util-deck-top">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-icon util-icon-round util-icon-amap">'.\Asset::icon('basket').'</div>';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.s("Une AMAP").'</div>';
					$h .= '<p>'.s("Pour organiser les distributions, les contrats et les adhésions de votre AMAP.").'</p>';
				$h .= '</div>';
				$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	private static function getProducerTypeDeck(string $producerType, string $class, ?string $producerTypeChosen): string {

		// Le deck porte ce que la catégorie change dans le formulaire suivant, rendu avant qu'elle
		// soit connue : le vocabulaire de ses libellés, et les champs qu'elle ne propose pas.
		[$icon, $domain, $wording, $fieldsHidden] = match($producerType) {
			Structure::REAL => ['house-door', 'production', 'structure', ''],
			Structure::TEST => ['tools', 'settings', 'structure', ''],
			Structure::LEARNER => ['book', 'accounting', 'farm', 'startedAt'],
			Structure::INSTALLATION => ['signpost-split', 'commercialisation', 'farm', ''],
		};

		// Une catégorie déjà connue se choisit au rendu, par le même geste que le clic : le
		// formulaire garde un seul chemin vers ses champs.
		$onrender = ($producerType === $producerTypeChosen) ? ' onrender="Structure.chooseProducerType(this)"' : '';

		$h = '<a class="util-deck '.$class.'" data-action="structure-producer-type" data-producer-type="'.$producerType.'" data-wording="'.$wording.'" data-fields-hidden="'.$fieldsHidden.'"'.$onrender.'>';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-icon util-icon-round util-icon-'.$domain.'">'.\Asset::icon($icon).'</div>';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.self::p('producerType')->values[$producerType].'</div>';
					$h .= '<p>'.self::getProducerTypeText($producerType).'</p>';
				$h .= '</div>';
				$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Ce que désigne la catégorie, en une phrase, sous son nom.
	 *
	 * Le **nom** de chaque catégorie vit dans le describer, point unique employé aussi par l'admin
	 * et par les écrans qui l'affichent sans la proposer. Cette phrase-ci ne sert qu'au formulaire
	 * qui fait choisir, et n'a donc rien à y faire.
	 */
	private static function getProducerTypeText(string $producerType): string {

		return match($producerType) {
			Structure::REAL => s("Je produis, je vends et je veux utiliser {siteName} pour mon activité."),
			Structure::TEST => s("Je veux découvrir ou essayer {siteName} avec une ferme de test."),
			Structure::INSTALLATION => s("Je prépare une installation qui n'a pas encore commencé."),
			Structure::LEARNER => s("J'utilise {siteName} dans le cadre d'une formation agricole."),
		};

	}

	/**
	 * La même catégorie, dite comme un état et non comme une intention.
	 *
	 * Les phrases ci-dessus servent les decks de l'inscription, où l'on choisit ce qu'on vient faire ;
	 * le bouton radio ne sert que sur une structure qui existe déjà, et l'équipe l'y lit autant que le
	 * propriétaire (planche du panneau, Q2 B).
	 */
	private static function getProducerTypeState(string $producerType): string {

		return match($producerType) {
			Structure::REAL => s("Ma ferme est en production."),
			Structure::TEST => s("Ma ferme sert à essayer le produit."),
			Structure::INSTALLATION => s("Ma ferme n'a pas encore commencé à produire."),
			Structure::LEARNER => s("Ma ferme sert à une formation agricole."),
		};

	}

	/**
	 * Les liens qui qualifient tout le paramétrage d'un groupe, dans la bande de l'écran. Une ferme
	 * trouve ses clés d'API dans le menu de ses réglages, qu'un groupe n'a pas : c'est son seul
	 * chemin vers elles.
	 */
	public function getUpdateTools(Structure $eStructure): string {

		$h = '';

		if(StructureSetting::hasAccountingApp()) {
			$h .= '<a href="'.StructureUi::urlConnected('/historique', $eStructure).'" class="btn btn-outline-secondary">'.\Asset::icon('clock-history').' '.s("Historique").'</a>';
		}

		if($eStructure->acceptApi()) {
			$h .= '<a href="'.StructureUi::urlStructureApi($eStructure).'" class="btn btn-outline-secondary">'.\Asset::icon('plug-fill').' '.s("Intégrations").'</a>';
		}

		return $h;

	}

	protected function getUpdateAdministrative(Structure $eStructure, string $appearance): string {

		$form = new \util\FormUi();

		$h = '';

		// Un groupe porte ces liens dans la bande de son écran (`getUpdateTools()`).
		if(
			$eStructure->isCommunity() === FALSE and
			StructureSetting::hasAccountingApp()
		) {
			$h .= '<div class="text-end mb-1"><a href="'.StructureUi::urlConnected('/historique', $eStructure).'">'.s("Historique").'</a></div>';
		}

		$h .= $form->openAjax('/structure/structure:doUpdate', ['id' => 'structure-update', 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $eStructure['id']);

			if($eStructure->acceptLogo()) {
				$h .= $form->group(
					(self::p('logo')->label)($eStructure),
					new \media\StructureLogoUi()->getCamera($eStructure, size: '10rem')
				);
			}

			// Le bandeau se règle à côté du logo, où se règle l'identité visible du groupe, et
			// n'existe que pour un groupe : les autres structures n'ont pas de bande à couvrir.
			if($eStructure->isCommunity() and $eStructure->acceptBanner()) {
				$h .= $form->group(
					(self::p('banner')->label)($eStructure),
					new \media\StructureBannerUi()->getCamera($eStructure, width: '32rem', height: 'auto')
				);
			}

			$h .= $form->dynamicGroup($eStructure, 'name');

			if($eStructure->acceptGroupQuotationSetting()) {
				$h .= $form->dynamicGroup($eStructure->community(), 'fqn');
			}

			$h .= $form->dynamicGroup($eStructure, 'legalEmail');

			if($eStructure->acceptUpdateProducerType()) {

				$h .= $form->dynamicGroup($eStructure, 'producerType*');

			} else {

				$h .= self::getProducerTypeReadOnly($form, $eStructure);

			}

			if(
				$eStructure->isLegal() and
				$eStructure->isVerifiedCountry()
			) {

				$h .= $form->group(
					s("Pays du siège social"),
					'<b>'.\user\Country::ask($eStructure['legalCountry'])['name'].'</b>'
				);

				if($eStructure->isLegalInfo()) {

					if($eStructure->isFrance()) {
						$h .= $form->dynamicGroup($eStructure, 'siret');
					}

					$h .= $form->dynamicGroup($eStructure, 'legalName');
					$h .= $form->addressGroup(s("Adresse du siège social"), 'legal', $eStructure, ['country' => FALSE]);
					$h .= $form->dynamicGroup($eStructure, 'legalCategory');

				}

			} else {
				$h .= $form->dynamicGroup($eStructure, 'legalCountry');
			}

			if($eStructure->acceptPresentation()) {
				$h .= $form->dynamicGroups($eStructure, ['description', 'startedAt', 'url']);
			}

			if($eStructure->acceptMarks()) {
				$h .= $this->getMarksFields($form, $eStructure);
			}

			if(
				$eStructure->isLegal() and
				$eStructure->acceptDocuments()
			) {
				$h .= $form->dynamicGroup($eStructure->conf(), 'organicCertifier');
			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		if($appearance !== '') {
			$h .= '<div class="mt-3">'.$appearance.'</div>';
		}

		if(
			$eStructure->isLegal() === FALSE and
			$eStructure->acceptLegal()
		) {

			$h .= '<h3 class="mt-3 mb-2">'.self::getText($eStructure, 'updateLegal').'</h3>';

			$h .= '<div class="util-buttons">';

				$h .= '<a data-ajax="/structure/structure:doEnableLegal" post-id="'.$eStructure['id'].'" data-confirm="'.s("Êtes-vous sûr de vouloir activer la structure juridique pour {value} sur {siteName} ?", encode($eStructure['name'])).'" class="util-button">';

					$h .= '<h4>';
						$h .= s("Activer la structure juridique");
					$h .= '</h4>';
					$h .= \Asset::icon('building');

				$h .= '</a>';

			$h .= '</div>';

		}

		if($eStructure->isProducer()) {

			$h .= '<h3 id="structure-sharing" class="mt-3 mb-2">'.s("Partage des données").'</h3>';
			$h .= $this->updateConsentData($eStructure);

		}

		// Une AMAP fermée reste ouverte à son équipe, qui sert ce qui court (`StructureLib::update()`) :
		// le profil dit qu'elle est fermée, et rien ne la ferme une seconde fois. Une AMAP ouverte se
		// ferme depuis Paramètres, en bas de page.
		if($eStructure->isAmap()) {

			if($eStructure->acceptClose() === FALSE) {
				$h .= '<h3 class="mt-3 mb-2">'.s("L'AMAP est fermée").'</h3>';
				$h .= '<p>'.s("Elle ne prend plus d'adhésion ni de souscription. Les souscriptions déjà signées sont servies jusqu'à la fin de leur saison.").'</p>';
			}

			return $h;

		}

		$h .= $this->getClose($eStructure);

		return $h;

	}

	/** Le geste qui ferme une structure, en bas de son paramétrage, sous son intertitre. */
	public function getClose(Structure $eStructure): string {

		$h = '<h3 class="mt-3 mb-2">'.s("Vous ne souhaitez plus utiliser {siteName} ?").'</h3>';
		$h .= $this->getCloseButtons($eStructure);

		return $h;

	}

	/**
	 * Les boutons seuls, pour la page Paramètres d'une AMAP qui en fait une section à son titre.
	 */
	public function getCloseButtons(Structure $eStructure): string {

		$h = '<div class="util-buttons">';

			// Un groupe a son propre panneau de rupture, qui chiffre ce que sa suppression coûte
			// aux fermes et aux membres. Une AMAP a le sien, qui dit ce qu'elle cesse de prendre et ce
			// qu'elle garde. Une ferme garde la confirmation du navigateur : elle ne détache personne,
			// elle n'efface qu'elle-même.
			if($eStructure->isCommunity()) {
				$close = 'href="/structure/community:breakClose?id='.$eStructure['id'].'"';
			} else if($eStructure->isAmap()) {
				$close = 'href="/structure/structure:breakClose?id='.$eStructure['id'].'"';
			} else {
				$close = 'data-ajax="/structure/structure:doClose" post-id="'.$eStructure['id'].'" data-confirm="'.s("Êtes-vous sûr de vouloir supprimer toutes les données de {value} ?", encode($eStructure['name'])).'"';
			}

			$h .= '<a '.$close.' class="util-button util-button-danger">';

				$h .= '<h4>';
					$h .= self::getText($eStructure, 'delete');
				$h .= '</h4>';
				$h .= \Asset::icon($eStructure->isAmap() ? 'door-closed' : 'trash');

			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	protected function updateConsentData(Structure $eStructure): string {

		$h = '<div class="mb-1">';
			$h .= \util\TextUi::switch([
				'id' => 'structure-consent-data',
				'data-ajax' => '/structure/structure:doUpdateConsentData',
				'post-id' => $eStructure['id'],
				'post-consent-data' => $eStructure['consentData'] ? FALSE : TRUE
			], $eStructure['consentData'] === TRUE);
			$h .= '  '.s("Contribuer aux fonctionnalités communautaires et participer à l'amélioration de Ouvretaferme");
		$h .= '</div>';

		if($eStructure->usesAccounting()) {
			$h .= '<p>'.s("Afin de vous proposer des fonctionnalités mieux adaptées à vos pratiques, nous aimerions utiliser certaines de vos données telles que celles liées à vos productions et vos ventes, ainsi que les comptes que vous choisissez pour classer vos factures en comptabilité. Nous n'utilisons que des données agrégées et anonymisées, qui préservent la confidentialité de votre structure.").'</p>';
		} else {
			$h .= '<p>'.s("Afin de vous proposer des fonctionnalités mieux adaptées à vos pratiques, nous aimerions utiliser certaines de vos données telles que celles liées à vos productions et vos ventes. Nous n'utilisons que des données agrégées et anonymisées, qui préservent la confidentialité de votre structure.").'</p>';
		}
		$h .= '<p>'.s("En partageant des données, vous nous aidez à :").'</p>';
		$h .= '<ul>';
			$h .= '<li>'.s("proposer des fonctionnalités communautaires sur {siteName} telles que des plans de culture personnalisés ou des relevés de prix,").'</li>';
			$h .= '<li>'.s("développer, tester et améliorer de nouvelles fonctionnalités").'</li>';
		$h .= '</ul>';

		return $h;

	}

	public function getMarksFields(\util\FormUi $form, \Element $e, string $suffix = ''): string {

		$content = '<div class="structure-marks">';
			$content .= '<div class="structure-marks-organic">';
				$content .= '<fieldset>';
					$content .= '<legend>'.s("Agriculture biologique").'</legend>';
					$content .= $form->dynamicField($e, 'organic'.$suffix);
				$content .= '</fieldset>';
				$content .= '<label>';
					$content .= '<span class="structure-marks-more btn btn-outline-primary">';
						$content .= \Asset::icon('plus-circle').' '.s("Plus de signes");
					$content .= '</span>';
					$content .= '<input type="radio" class="hide">';
				$content .= '</label>';
			$content .= '</div>';
			$content .= '<div class="structure-marks-other">';
				$content .= '<fieldset>';
					$content .= '<legend>'.s("Autres signes de qualité").'</legend>';
					$content .= $form->dynamicField($e, 'qualities'.$suffix);
				$content .= '</fieldset>';
			$content .= '</div>';
		$content .= '</div>';

		// Le statut bio d'une structure est obligatoire (Structure::properties(), étape required), et le
		// groupe porte son refus : c'est lui qui prend l'astérisque. Ceux d'un produit ou d'un article ne le sont pas.
		return $form->group(
			s("Signes de qualité").($e instanceof Structure ? \util\FormUi::asterisk() : ''),
			$content,
			['data-wrapper' => 'qualities organic']
		);

	}

	/**
	 * Les entrées qu'un package fermé apporte à un écran public, rangées par leur nom : l'écran décide de
	 * leur place, le package de leur contenu. Sans le package, personne ne répond : l'appelant se garde
	 * par `StructureSetting::hasCommercialisationApp()`, directement ou par un `accept*` qui le porte.
	 */
	public static function getObserverEntries(string $event, mixed ...$arguments): array {
		return array_merge(...self::notify($event, ...$arguments));
	}

	public function export(Structure $eStructure, int $year, int $yearFirst): string {

		$form = new \util\FormUi();

		$h = '<h3>'.s("Les données annuelles").'</h3>';

		$h .= $form->openAjax('/structure/structure:export', attributes: ['method' => 'get']);
			$h .= '<div class="util-block-search" style="display: flex; column-gap: 1rem">';
				$h .= $form->hidden('id', $eStructure['id']);
				$h .= $form->inputGroup(
					$form->addon(s("Année")).
					$form->rangeSelect('year', $eStructure->conf(\map\Configuration::class)['seasonLast'], $yearFirst, -1, $year, ['required' => TRUE])
				);
				$h .= $form->submit(s("Valider"));
			$h .= '</div>';
		$h .= $form->close();

		$h .= '<div class="util-cards util-cards-2 mb-2">';

			if($eStructure->acceptCultivation()) {

				$h .= \main\DesignUi::getDeckEntry('/series/csv:exportCultivations?id='.$eStructure['id'].'&year='.$year, 'journals', 'production', s("Exporter le plan de culture"), download: TRUE);
				$h .= \main\DesignUi::getDeckEntry('/series/csv:exportSoil?id='.$eStructure['id'].'&year='.$year, 'map', 'production', s("Exporter le plan d'assolement"), download: TRUE);
				$h .= \main\DesignUi::getDeckEntry('/map/csv:exportPlan?id='.$eStructure['id'].'&year='.$year, 'bounding-box', 'production', s("Exporter le plan de la ferme"), download: TRUE);
				$h .= \main\DesignUi::getDeckEntry('/series/csv:exportTimesheet?id='.$eStructure['id'].'&year='.$year, 'clock', 'production', s("Exporter le temps de travail"), download: TRUE);
				$h .= \main\DesignUi::getDeckEntry('/series/csv:exportTasks?id='.$eStructure['id'].'&year='.$year, 'calendar3', 'production', s("Exporter les interventions"), download: TRUE);
				$h .= \main\DesignUi::getDeckEntry('/series/csv:exportHarvests?id='.$eStructure['id'].'&year='.$year, 'basket2-fill', 'production', s("Exporter les récoltes"), download: TRUE);

			}

			$hasCommercialisation = StructureSetting::hasCommercialisationApp();

			if($hasCommercialisation) {

				$entries = self::getObserverEntries('getDataExportEntries', $eStructure, $year);

				$h .= $entries['sales'];
				$h .= $entries['items'];
				$h .= $entries['invoices'];

			}

			if(StructureSetting::hasAccountingApp()) {

				$entriesAccounting = self::getObserverEntries('getDataExportAccountingEntries', $eStructure, $year);

				$h .= $entriesAccounting['invoicings'];
				$h .= $entriesAccounting['cashes'];

			}

		$h .= '</div>';

		$h .= '<h3>'.s("Les données intégrales").'</h3>';

		$h .= '<div class="util-cards util-cards-2">';

			$h .= \main\DesignUi::getDeckEntry('/product/csv:exportProducts?id='.$eStructure['id'], 'box', 'commercialisation', s("Exporter les produits"), download: TRUE);

			if($hasCommercialisation) {
				$h .= $entries['customers'];
			}

		$h .= '</div>';

		return $h;

	}

	public function import(Structure $eStructure): string {

		$h = '';

		$h .= '<h3>'.s("Commercialisation").'</h3>';

		$h .= '<div class="util-cards util-cards-2 mb-2">';

			$h .= \main\DesignUi::getDeckEntry('/product/csv:importProducts?id='.$eStructure['id'], 'box', 'commercialisation', s("Importer des produits"));

			if(StructureSetting::hasCommercialisationApp()) {

				$entries = self::getObserverEntries('getDataImportEntries', $eStructure);

				$h .= $entries['customers'];
				$h .= $entries['prices'];

			}

		$h .= '</div>';

		if($eStructure->acceptCultivation()) {

			$h .= '<h3>'.s("Production").'</h3>';

			$h .= '<div class="util-cards util-cards-2 mb-2">';

				$h .= \main\DesignUi::getDeckEntry('/series/csv:importCultivations?id='.$eStructure['id'], 'journals', 'production', s("Importer un plan de culture"), s("Compatible Qrop / Brinjel"));

			$h .= '</div>';

		}

		if(StructureSetting::hasAccountingApp()) {

			$entries = self::getObserverEntries('getDataImportAccountingEntries', $eStructure);

			if($entries !== []) {

				$h .= '<h3>'.s("Comptabilité").'</h3>';

				$h .= '<div class="util-cards util-cards-2">';
					$h .= implode('', $entries);
				$h .= '</div>';

			}

		}

		return $h;

	}

	public static function getSelectedFinancialYear(Structure $eStructure, bool $withCloseDate = FALSE): string {

		$eFinancialYearSelected = $eStructure['eFinancialYear'];

		$h = '<div class="hide-lateral-up" style="margin-bottom: 0.25rem">';
			if($withCloseDate and $eFinancialYearSelected['status'] === \financialYear\FinancialYear::CLOSE) {
				$h .= s("Exercice {value}, clos le {date}", [
					'value' => $eFinancialYearSelected->getLabel(),
					'date' => \util\DateUi::numeric($eFinancialYearSelected['closeDate'], \util\DateUi::DATE),
				]);
			} else {
				$h .= s("Exercice {value}", $eFinancialYearSelected->getLabel());
			}
			if($eFinancialYearSelected['status'] === \financialYear\FinancialYear::CLOSE) {
				$h .= '  '.\Asset::icon('lock-fill');
			}
		$h .= '</div>';

		return $h;

	}

	public function getSeason(Structure $eStructure, \Closure $url, int $selectedSeason): string {

		$seasons = [];

		for($season = $eStructure->conf(\map\Configuration::class)['seasonLast']; $season >= $eStructure->conf(\map\Configuration::class)['seasonFirst']; $season--) {
			$seasons[] = [
				'url' => $url($season),
				'label' => s("Saison {year}", ['year' => $season]),
				'selected' => ($season === $selectedSeason)
			];
		}

		return new \main\DesignUi()->getSeason(s("Saison {year}", ['year' => $selectedSeason]), $seasons, $this->getSeasonsMore($eStructure));

	}

	protected function getSeasonsMore(Structure $eStructure): string {

		// ⚠️ Ajouter ou retirer une saison écrit la configuration, que
		// `Configuration::canRead()` réserve à `canManage()`. Sans cette garde, un simple
		// observateur, sur n'importe quelle ferme et pas seulement une ferme école, se voyait
		// proposer deux liens que l'action refuse. Défaut antérieur au chantier, révélé par le
		// premier lecteur qui n'est membre de rien.
		if(OTF_DEMO or $eStructure->canManage() === FALSE) {
			return '';
		}

		$newLast = $eStructure->conf(\map\Configuration::class)['seasonLast'] + 1;
		$newFirst = $eStructure->conf(\map\Configuration::class)['seasonFirst'] - 1;

		$h = '';

		if($newLast > \Clock::year() + 10) {
			$alert = s("Il n'est pas possible d'ajouter des saisons plus de dix ans en avance !");
			$h .= '<div class="dropdown-item structure-tab-disabled" data-alert="'.$alert.'">';
				$h .= s("Ajouter la saison {year}", ['year' => $newLast]);
			$h .= '</div> ';
		} else {
			$h .= '<a data-ajax="/map/configuration:doSeasonLast" post-id="'.$eStructure->conf(\map\Configuration::class)['id'].'" post-increment="1" class="dropdown-item">';
				$h .= s("Ajouter la saison {year}", ['year' => $newLast]);
			$h .= '</a> ';
		}

		$h .= '<a data-ajax="/map/configuration:doSeasonFirst" post-id="'.$eStructure->conf(\map\Configuration::class)['id'].'" post-increment="-1" class="dropdown-item">';
			$h .= s("Ajouter la saison {year}", ['year' => $newFirst]);
		$h .= '</a> ';

		return $h;

	}

	public function getSettingsCommercialisation(Structure $eStructure): string {

		// L'écran lit des entrées que seule l'application apporte.
		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return '';
		}

		$entries = self::getObserverEntries('getSettingsCommercialisationEntries', $eStructure);

		$h = '<div class="util-cards util-cards-2">';

			if($eStructure->canManage()) {

				$h .= $entries['general'];
				$h .= $entries['point'];

				$h .= \main\DesignUi::getDeckEntry('/structure/configuration:updateEmail?id='.$eStructure->conf()['id'], 'envelope', 'commercialisation', s("Les e-mails"), s("La personnalisation des messages envoyés aux clients et leur heure d'envoi"));

				if($eStructure->isLegal()) {

					if($eStructure->acceptUnits()) {
						$h .= \main\DesignUi::getDeckEntry('/product/unit:manage?structure='.$eStructure['id'], 'receipt', 'commercialisation', s("Les unités de vente"), s("Les unités dans lesquelles les produits sont vendus"));
					}

					if(
						$eStructure->isVerifiedCountry() and
						$eStructure->acceptDocuments()
					) {

						$h .= $entries['orderForm'];
						$h .= $entries['deliveryNote'];
						$h .= $entries['invoice'];

					}

					if($eStructure->acceptPaymentSettings()) {
						$h .= $entries['method'];
						$h .= \main\DesignUi::getDeckEntry(self::urlPaymentCard($eStructure), 'credit-card-2-front', 'commercialisation', s("Le paiement par carte"), s("Les connexions à Stripe et à SumUp, qui encaissent les paiements par carte"));
					}

					if($eStructure['stripeExpressEligible']) {
						$h .= $entries['reversal'];
					}

					if(
						$eStructure->isVerifiedCountry() and
						$eStructure->acceptWalletSettings()
					) {
						$h .= $entries['wallet'];
					}

				}

			}

			if($eStructure->acceptAccounting()) {
				$h .= $entries['preaccounting'];
			}

		$h .= '</div>';

		return $h;

	}

	public function getSettingsProduction(Structure $eStructure): string {

		$h = '';
		$h .= new SystemUi()->display($eStructure, $eStructure->system(), $eStructure['cProduction']);

		// L'interrupteur de la production est sur la page Fonctionnalités. Éteinte, la page ne garde que la fiche des
		// productions, où mène encore « Compléter ma fiche » (`ProfileAi.u.php`).
		if($eStructure->conf(Feature::class)['production']) {

			$h .= '<br/>';
			$h .= '<h2>'.s("Paramétrage").'</h2>';

			$h .= '<div class="util-cards util-cards-2">';

				$h .= \main\DesignUi::getDeckEntry('/map/configuration:updateCrop?id='.$eStructure->conf(\map\Configuration::class)['id'], 'gear-fill', 'production', s("Les cultures"), s("Les dimensions des planches, la durée des rotations et la saisie des horaires"));
				$h .= \main\DesignUi::getDeckEntry(\plant\PlantUi::urlManage($eStructure), 'flower3', 'production', s("Les espèces"), s("Le référentiel des espèces cultivées"));
				$h .= \main\DesignUi::getDeckEntry('/work/action:manage?structure='.$eStructure['id'], 'list-task', 'production', s("Les interventions"), s("Les actions réalisées sur les cultures"));
				$h .= \main\DesignUi::getDeckEntry('/work/tool:manage?structure='.$eStructure['id'], 'hammer', 'production', s("Le matériel"), s("Les outils et équipements mobilisés par les interventions"));
				$h .= \main\DesignUi::getDeckEntry('/work/tool:manage?structure='.$eStructure['id'].'&routineName=fertilizer', 'stars', 'production', s("Les intrants"), s("Les fertilisants et leur composition"));
				$h .= \main\DesignUi::getDeckEntry(\work\ToolUi::urlManage($eStructure, 'phyto'), 'capsule', 'production', s("Les produits de traitement"), s("Les produits phytosanitaires, leur dose et leur volume de bouillie par défaut"));
				$h .= \main\DesignUi::getDeckEntry('/work/supplier:manage?structure='.$eStructure['id'], 'buildings', 'production', s("Les fournisseurs de semences et plants"), s("Les fournisseurs auprès desquels vous achetez semences et plants"));

			$h .= '</div>';

		}

		return $h;

	}

	public function getPanel(Structure $eStructure): string {

		// Les portes de la structure, rassemblées avant d'être rendues : leur nombre décide de la
		// forme du bloc. Une porte unique n'est pas un bouton, c'est le bloc entier qui y mène.
		$doors = [];

		if($eStructure->assertProduction()) {
			$doors[] = ['url' => $eStructure->getProductionUrl(), 'class' => 'btn-production-light', 'content' => \Asset::icon('leaf').' '.s("Production")];
		}

		if($eStructure->isAmap() and $eStructure->canManage()) {
			$doors[] = ['url' => $eStructure->getAmapUrl(), 'class' => 'btn-amap-light', 'content' => \Asset::icon('people').' '.s("AMAP")];
		}
		// Le producteur d'une AMAP qu'il ne gère pas n'a rien à ouvrir ici : le tableau de bord est au
		// bureau. Sa porte est l'écran AMAP de sa propre ferme, dont l'adresse ne se déduit pas de
		// cette structure-ci — la page la pose quand elle existe, et le `??` dit une propriété
		// facultative, pas une sélection incomplète.
		else if($eStructure->isAmap() and ($eStructure['urlAmap'] ?? NULL) !== NULL) {
			$doors[] = ['url' => $eStructure['urlAmap'], 'class' => 'btn-amap-light', 'content' => \Asset::icon('people').' '.s("AMAP")];
		}

		if(
			$eStructure->isAmap() === FALSE and
			$eStructure->assertCommercialisation()
		) {
			$doors[] = ['url' => $eStructure->getCommercialisationUrl(), 'class' => 'btn-commercialisation-light', 'content' => \Asset::icon('basket3').' '.s("Vente")];
		}

		if($eStructure->assertAccounting()) {
			$doors[] = ['url' => $eStructure->getAccountingUrl(), 'class' => 'btn-accounting-light', 'content' => \Asset::icon('piggy-bank').' '.s("Gestion")];
		}

		// Une seconde bande plutôt qu'un encart d'annonce : ce qui se présentait comme une nouveauté
		// à lire est devenu une entrée à ouvrir, au même titre que Production, Vente ou Gestion.
		//
		// Une ferme déjà raccordée à la plateforme n'a plus rien à mettre en route : son entrée mène
		// aux factures d'achat, là où arrive ce que ses fournisseurs lui envoient.
		$others = [];

		if(
			$eStructure->acceptInvoicing() and
			$eStructure['type'] === Structure::PRODUCER and
			$eStructure->canManage()
		) {

			$url = $eStructure->conf(Feature::class)['hasPdp']
				? StructureUi::urlInvoicingBuy($eStructure)
				: StructureUi::urlInvoicingOnboarding($eStructure);

			$others[] = ['url' => $url, 'class' => 'btn-light', 'content' => \Asset::image('main', 'facturation-electronique-logo.png', ['style' => 'height: 1.2rem']).' '.s("Facturation électronique")];

		}

		$all = array_merge($doors, $others);

		// Où mène le bloc entier, quand il mène quelque part : un groupe à sa page, une AMAP à son
		// unique entrée. Le bloc est alors le lien, porte le chevron, et aucune bande ne s'écrit.
		//
		// ⚠️ Une ferme fait exception et garde ses boutons même quand les droits n'en laissent qu'un :
		// elle n'a pas de page à elle dont sa carte serait la porte, et le nombre d'entrées visibles
		// dépend du lecteur. Sa carte n'est donc jamais cliquable en entier.
		if($eStructure['type'] === Structure::COMMUNITY) {
			$single = StructureUi::url($eStructure);
		} else if($eStructure['type'] === Structure::PRODUCER) {
			$single = NULL;
		} else if(count($all) === 1) {
			$single = $all[0]['url'];
		} else {
			$single = NULL;
		}

		$tag = ($single === NULL) ? 'div' : 'a';

		$h = '<'.$tag.($single === NULL ? '' : ' href="'.$single.'"').' class="util-deck util-deck-lg">';

			$h .= '<div class="util-deck-head">';

				$h .= self::getLogo($eStructure, '4rem');

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">';
						$h .= encode($eStructure['name']);
						if(
							$eStructure['type'] === Structure::COMMUNITY and
							$eStructure['communityType'] !== NULL
						) {
							$h .= '<span class="badge badge-neutral">'.self::p('communityType')->values[$eStructure['communityType']].'</span>';
						}
					$h .= '</div>';

					if($eStructure['legalCity']) {
						$h .= '<p>'.\Asset::icon('geo-fill').' '.encode($eStructure['legalCity']).'</p>';
					}

				$h .= '</div>';

				if($single !== NULL) {
					$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
				}

			$h .= '</div>';

			if($single === NULL) {

				// Les portes de domaine tiennent chacune un tiers, et l'entrée secondaire demande toute la
				// largeur pour s'aligner sous leur bande : une porte seule n'en prend que la moitié, et
				// s'arrêterait au milieu d'une porte de domaine. Seule sur la carte, elle garde la moitié,
				// sans quoi elle deviendrait une barre qui écrase le deck.
				if($doors !== []) {
					$h .= self::getPanelDoors($doors, 'util-deck-band-doors-3');
				}

				if($others !== []) {
					$h .= self::getPanelDoors($others, 'util-deck-band-doors-1'.($doors !== [] ? ' util-deck-band-doors-full' : ''));
				}

			}

		$h .= '</'.$tag.'>';

		return $h;

	}

	protected static function getPanelDoors(array $doors, string $class): string {

		$h = '<div class="util-deck-band util-deck-band-doors '.$class.'">';

			foreach($doors as $door) {
				$h .= '<a href="'.$door['url'].'" class="btn '.$door['class'].'">'.$door['content'].'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'aperçu du bandeau d'un groupe, pour le contrôle de téléversement.
	 *
	 * Le ratio d'aperçu est celui de l'image stockée (4:1) et non celui de la bande (9:1) : c'est
	 * l'image qu'on choisit ici, pas le cadre qui la rognera.
	 */
	public static function getBanner(Structure $eStructure, ?string $width = NULL): string {

		$eStructure->expects(['id', 'banner']);

		$class = 'structure-banner-view media-rectangle-view';
		$style = '';

		if($eStructure['banner'] !== NULL) {
			$style .= 'background-image: url('.new \media\StructureBannerUi()->getUrlByElement($eStructure, 'l').');';
		}

		return '<div class="'.$class.'" style="width: '.($width ?? '100%').'; max-width: 100%; height: auto; aspect-ratio: 4; '.$style.'"></div>';

	}

	public static function getMenuUrl(Structure $eStructure, ?string $section): ?string {

		if($eStructure->isCommunity()) {

			$url = self::urlCommunity($eStructure);

		} else {

			$sectionMenu = $eStructure->getMenuSection($section);

			if($sectionMenu === NULL) {
				return NULL;
			}

			$url = $eStructure->getUrl($sectionMenu);

		}

		// La démonstration sert les pages de ses structures, les domaines des boutiques et des réseaux non.
		if(OTF_DEMO) {
			return $url;
		}

		return \main\SiteAiUi::url($url);

	}

	public static function getDropdownItem(Structure $eStructure, string $url, bool $selected): string {

		$h = '<a href="'.$url.'" data-ajax-navigation="never" class="dropdown-item dropdown-item-icon'.($selected ? ' selected' : '').'">';
			$h .= self::getLogo($eStructure, '1.75rem');
			$h .= '<span>'.encode($eStructure['name']).'</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * `$class` sert aux écrans qui cadrent autrement, et jamais à retoucher `media-rectangle-view` :
	 * son `background-size: contain` sert les logos de tout le produit, dont beaucoup portent un
	 * texte qui ne survit pas à un rognage.
	 */
	public static function getLogo(Structure $eStructure, string $size, string $class = ''): string {

		$eStructure->expects(['id', 'logo']);

		$ui = new \media\StructureLogoUi();

		$class = 'structure-logo-view media-rectangle-view '.$class.' ';
		$style = '';

		if($eStructure['logo'] !== NULL) {

			$format = $ui->convertToFormat($size);

			$style .= 'background-image: url('.$ui->getUrlByElement($eStructure, $format).');';

		}

		return '<div class="'.$class.'" style="'.$ui->getSquareCss($size).'; '.$style.'"></div>';

	}

	/**
	 * La pastille d'une structure sans logo : ses initiales, sur la couleur que la palette des
	 * vignettes d'utilisateur tire de son identifiant, pour qu'elle se reconnaisse d'un écran à l'autre.
	 */
	public static function getInitials(Structure $eStructure): string {

		$eStructure->expects(['id', 'name']);

		return '<div class="util-icon util-icon-round util-icon-sm util-icon-initials" style="--icon-background: '.\user\UserUi::getDefaultColor($eStructure['id']).'">'.encode($eStructure->getInitials()).'</div>';

	}

	public static function getEmailInfo(Structure $eStructure): string {
		return \util\FormUi::info(s("L'e-mail envoyé contiendra toujours le bandeau et la signature que vous avez définis sur la <link>page de configuration des e-mails</link>.", ['link' => '<a href="/structure/configuration:updateEmail?id='.$eStructure->conf()['id'].'" target="_blank">']));
	}

	public static function getNavigation(): string {

		$h = '<span class="h-menu">';
			$h .= \Asset::icon('list');
		$h .= '</span>';

		return $h;

	}

	public static function getAlleyWarning(): string {
		return \util\FormUi::info(\Asset::icon('exclamation-circle').' '.s("Les rendements et la fertilisation sont calculés en intégrant la largeur du passe-pied."));
	}

	public static function getRealServiceInfo(string $message, string $button = ''): string {

		$h = '<div class="util-block-info'.($button === '' ? ' mb-0' : '').'">';
			$h .= \Asset::icon('easel', ['class' => 'util-block-icon']);
			$h .= '<p>'.$message.'</p>';
			$h .= $button;
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Structure::model()->describer($property, [
			'name' => fn(Structure $e) => self::getText($e, 'propertyName'),
			'isLegal' => fn(Structure $e) => self::getText($e, 'propertyIsLegal'),
			'legalCountry' => s("Pays"),
			'legalName' => s("Raison sociale"),
			'legalEmail' => fn(Structure $e) => self::getText($e, 'propertyLegalEmail'),
			'legalCategory' => s("Forme juridique"),
			'siret' => s("Numéro d'immatriculation SIRET"),
			'legalPostcode' => s("Code postal"),
			'description' => fn(Structure $e) => self::getText($e, 'propertyPresentation'),
			'startedAt' => s("Année de création"),
			'url' => s("Site internet"),
			'logo' => fn(Structure $e) => self::getText($e, 'propertyLogo'),
			'banner' => fn(Structure $e) => self::getText($e, 'propertyBanner'),
			'profileVisibility' => s("Visibilité de votre ferme dans la communauté"),
			'organic' => s("Agriculture biologique"),
			'qualities' => s("Signes de qualité"),
			'communityType' => s("Type de groupe"),
			'producerType' => s("Catégorie de la ferme"),
		]);

		switch($property) {

			case 'profileVisibility' :

				$d->field = 'radio';
				$d->required = TRUE;

				$membres = '<h4>'.s("Les groupes dont vous êtes membre").'</h4>';
				$membres .= '<p>'.s("Votre fiche n'est lisible que des personnes avec qui vous partagez un groupe, et chaque groupe décide de ce qu'il en montre.").'</p>';

				$public = '<h4>'.s("Tout le monde").'</h4>';
				$public .= '<p>'.s("Votre nom, votre commune, votre logo et votre présentation deviennent lisibles sans compte. Vos données de production restent réservées à vos groupes : les rendre publiques n'est pas ce que ce réglage fait.").'</p>';

				$personne = '<h4>'.s("Personne").'</h4>';
				$personne .= '<p>'.s("Votre fiche n'est lisible par personne, pas même par les membres de vos groupes. Votre nom reste visible dans les listes où votre ferme apparaît, sans y mener.").'</p>';

				// Du plus ouvert au plus fermé : l'ordre du schéma range PRIVATE avant NONE parce
				// qu'il énumère, celui-ci se lit comme un curseur et c'est ce que la personne règle.
				$d->values = [
					Structure::PUBLIC => $public,
					Structure::MEMBERS => $membres,
					Structure::NONE => $personne
				];

				break;

			case 'producerType' :
				$d->required = TRUE;
				// Les options du bouton radio : une catégorie à afficher sans la proposer va dans
				// `displayValues`, l'étape `producerType.type` refuserait ce choix.
				$d->values = [
					Structure::REAL => s("Producteur installé"),
					Structure::TEST => s("Ferme de test"),
					Structure::LEARNER => s("Ferme d'étudiant"),
					Structure::INSTALLATION => s("Projet d'installation"),
				];

				$displayValues = $d->values + [
					Structure::PROJECT => s("Ferme projet d'un groupe"),
					Structure::SCHOOL => s("Ferme école"),
					Structure::TRAINING => s("Structure de formation"),
				];

				$d->displayValues = $displayValues;

				$d->displayIcons = [
					Structure::REAL => NULL,
					Structure::TEST => \Asset::icon('tools', ['title' => $displayValues[Structure::TEST]]),
					Structure::LEARNER => \Asset::icon('book', ['title' => $displayValues[Structure::LEARNER]]),
					Structure::INSTALLATION => \Asset::icon('signpost-split', ['title' => $displayValues[Structure::INSTALLATION]]),
					Structure::PROJECT => \Asset::icon('diagram-3', ['title' => $displayValues[Structure::PROJECT]]),
					Structure::SCHOOL => \Asset::icon('mortarboard', ['title' => $displayValues[Structure::SCHOOL]]),
					Structure::TRAINING => \Asset::icon('easel', ['title' => $displayValues[Structure::TRAINING]]),
				];

				// `createDynamicField()` exécute la closure liée au describer : `getProducerTypeState()`,
				// privée, n'y est plus accessible et se lit donc ici.
				$radios = [];

				foreach($d->values as $producerType => $label) {
					$radios[$producerType] = '<h4>'.$label.'</h4><p class="mb-0">'.self::getProducerTypeState($producerType).'</p>';
				}

				$d->field = fn(\util\FormUi $form, Structure $e) => $form->radios('producerType', $radios, $e['producerType'], ['required' => TRUE]);
				break;

			case 'communityType' :
				$d->values = [
					Structure::PRODUCER => s("Communauté de producteurs"),
					Structure::EDUCATION => s("Groupe de formation"),
					Structure::NETWORK => s("Réseau d'accompagnement"),
					Structure::ACCOUNTING => s("Structure comptable"),
				];
				break;

			case 'isLegal' :
				$d->field = 'radio';

				$yes = '<h5>'.s("Oui, le groupement est porté par une structure juridique").'</h5>';
				$yes .= '<ul class="mt-1">';
					$yes .= '<li>'.s("Votre groupement pourra éditer des factures").'</li>';
					$yes .= '<li>'.s("Votre groupement aura accès au module de gestion").'</li>';
					$yes .= '<li>'.s("Ce choix est irrévocable").'</li>';
				$yes .= '</ul>';

				$no = '<h5>'.s("Non, il s'agit d'un groupement informel").'</h5>';
				$no .= '<ul class="mt-1">';
					$no .= '<li>'.s("Votre groupement ne pourra pas éditer de factures").'</li>';
					$no .= '<li>'.s("Votre groupement n'aura pas accès au module de gestion").'</li>';
					$no .= '<li>'.s("Vous pourrez faire évoluer ce choix ultérieurement").'</li>';
				$no .= '</ul>';

				$d->values = [
					TRUE => $yes,
					FALSE => $no
				];
				$d->required = TRUE;

				$d->labelAfter = \util\FormUi::info(s("Indiquez s'il y a une entreprise ou une association derrière votre groupement."));
				break;

			case 'legalEmail' :
				$d->default = fn(Structure $e) => $e->exists() ? $e['legalEmail'] : \user\ConnectionLib::getOnline()['email'];
				$d->attributes = [
					'onfocus' => 'this.select()'
				];
				break;

			case 'legalCountry' :
				$d->values = fn(Structure $e) => \user\Country::form();
				$d->attributes = fn(\util\FormUi $form, Structure $e) => [
					'group' => is_array(\user\Country::form()),
				];
				$d->required = TRUE;
				break;

			case 'siret' :
				\main\PlaceUi::querySiret($d, 'legal');
				break;

			case 'organic' :
				$d->field = 'select';
				$d->values = self::getOrganic();
				break;

			case 'qualities' :
				$d->values = self::getQualitiesLabels();
				$d->field = fn(\util\FormUi $form, Structure $e) => $form->checkboxes('qualities[]', $d->values, $e['qualities'] ?? [], ['all' => FALSE, 'columns' => 3]);
				break;

			case 'legalCategory':
				$d->field = 'select';
				$d->values = self::getLegalCategories();
				break;

		}

		return $d;

	}

	public static function getLegalCategories(): array {

		$categories = [];

		foreach(StructureSetting::CATEGORIES_JURIDIQUES as $category) {

			$categories[$category] = match($category) {
				StructureSetting::CATEGORIE_JURIDIQUE_ENTREPRENEUR_INDIVIDUEL => s("Entreprise individuelle"),
				6598 => s("Exploitation agricole à responsabilité limitée (EARL)"),
				StructureSetting::CATEGORIE_GAEC => s("Groupement agricole d'exploitation en commun (GAEC)"),
				5410 => s("SARL"),
				5500 => s("Société Anonyme"),
				5710 => s("SAS"),
				6597 => s("Société civile d'exploitation agricole (SCEA)"),
				6317 => s("Société coopérative agricole (SCA)"),
				2200 => s("Société de fait"),
				2300 => s("Société en participation"),
				6318 => s("Union de sociétés coopératives agricoles"),
				StructureSetting::CATEGORIE_JURIDIQUE_AUTRE => s("Autre structure juridique"),
			};

		}

		return $categories;

	}

}

?>
