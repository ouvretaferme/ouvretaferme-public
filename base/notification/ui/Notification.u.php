<?php
namespace notification;

/**
 * La cloche de la barre, son menu et le centre des notifications d'une structure.
 *
 * Le centre et le menu montrent les mêmes lignes : une notification seule, ou un groupe (D7-B) dont la
 * tête est la plus récente et porte ses sœurs dans `cNotificationGroup`. Le menu est un résumé du
 * centre, dans la même lumière (M2) : la méta y est réduite à la structure et au rôle, l'heure devient
 * « hier » ou une date au-delà du jour.
 */
class NotificationUi {

	/** Arrivée depuis la dernière visite : point, fond et gras. */
	private const UNSEEN = 'unseen';

	/** Vue à une visite précédente, pas encore cliquée : le gras seul. */
	private const PENDING = 'pending';

	/** Cliquée : plate. */
	private const READ = 'read';

	public function __construct() {

		\Asset::css('notification', 'notification.css');
		\Asset::js('notification', 'notification.js');

	}

	/**
	 * Le centre, sous la section d'où l'on vient. Sans section, le centre prend la première section
	 * accessible (NotificationLib::getSection()).
	 */
	public static function url(\structure\Structure $eStructure, ?string $section = NULL): string {

		$url = self::getBase($eStructure).'/notifications';

		if($section === NULL) {
			return $url;
		}

		return \util\HttpUi::setArgument($url, 'section', $section);

	}

	/**
	 * Un groupe a ses propres adresses, et toute autre structure celles d'une ferme, qui s'affichent
	 * sous ses sections : la page refuse l'adresse qui n'est pas celle du type.
	 */
	private static function getBase(\structure\Structure $eStructure): string {

		$eStructure->expects(['type']);

		if($eStructure['type'] === \structure\Structure::COMMUNITY) {
			return '/groupe/'.$eStructure['id'];
		}

		return '/ferme/'.$eStructure['id'];

	}

	public static function urlMenu(\structure\Structure $eStructure): string {
		return '/notification/notification:menu?id='.$eStructure['id'];
	}

	/**
	 * Les réglages, atteints par la seule roue du menu de la cloche (D2-C). Ils s'affichent sous la
	 * même section que le centre, celle d'où l'on vient.
	 */
	public static function urlSettings(\structure\Structure $eStructure, ?string $section = NULL): string {

		$url = self::getBase($eStructure).'/notifications/reglages';

		if($section === NULL) {
			return $url;
		}

		return \util\HttpUi::setArgument($url, 'section', $section);

	}

	/**
	 * La cloche, dans la barre d'une structure. Sans JavaScript, le lien mène au centre ; avec, il
	 * ouvre le menu une fois son contenu arrivé (`NotificationBell`).
	 */
	public function getBell(\structure\Structure $eStructure, int $unseen): string {

		$h = '<div id="'.self::getBellId($eStructure).'" class="notification-bell">';

			$h .= '<a href="'.self::url($eStructure).'" class="nav-item" data-ajax-navigation="never" data-notification-bell="'.self::urlMenu($eStructure).'" title="'.s("Notifications").'">';
				$h .= \Asset::icon('bell');
				$h .= $this->getCounter($unseen, 'util-counter-solid');
			$h .= '</a>';

			$h .= '<div id="notification-menu-'.$eStructure['id'].'" class="dropdown-list dropdown-list-unstyled dropdown-list-light notification-menu"></div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'identifiant de la cloche, que la vue vise pour éteindre sa pastille : une navigation ajax ne
	 * redessine pas la barre qui la porte.
	 */
	public static function getBellId(\structure\Structure $eStructure): string {
		return 'notification-bell-'.$eStructure['id'];
	}

	/**
	 * Un compteur vide à zéro : `util-counter:empty` le retire, et la cloche reste à sa place.
	 */
	private function getCounter(int $number, string $class = ''): string {
		return '<span class="util-counter '.$class.'">'.($number > 0 ? $number : '').'</span>';
	}

	public function getMenu(\structure\Structure $eStructure, ?string $section, \Collection $cNotificationHead, int $unseen, int $unread, bool $pilotage, bool $free): string {

		$h = '<div class="dropdown-title notification-menu-head">';
			$h .= '<a href="'.self::url($eStructure, $section).'" class="notification-menu-head-title">'.s("Notifications").'</a>';
			$h .= $this->getCounter($unseen, 'util-counter-solid');
			$h .= '<a href="'.self::urlSettings($eStructure, $section).'" class="notification-menu-settings" title="'.s("Gérer mes notifications").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '</div>';

		if($cNotificationHead->empty()) {
			$h .= '<div class="dropdown-text notification-menu-empty">'.s("Aucune notification").'</div>';
		}

		foreach($cNotificationHead as $eNotificationHead) {

			if($eNotificationHead['cNotificationGroup']->count() > 1) {
				$h .= $this->getMenuGroup($eNotificationHead);
			} else {
				$h .= $this->getMenuItem($eNotificationHead);
			}

		}

		$h .= '<div class="dropdown-divider"></div>';

		// Sans l'offre Pilotage, la promotion prend la place de « Voir toutes », et le bloc d'action
		// n'existe que s'il reste une non lue.
		//
		// Décidé le 22/09 sur la planche du lot 9.
		if($pilotage === FALSE) {

			if($unread > 0) {
				$h .= '<div class="dropdown-block util-action">';
					$h .= $this->getReadAll($eStructure);
				$h .= '</div>';
			}

			$h .= '<div class="dropdown-block">';
				$h .= '<div class="util-block-cta">';
					$h .= '<h3>'.s("Notifications de l'offre Pilotage").'</h3>';
					$h .= '<p>'.$this->getPromotionText($eStructure).'</p>';
					$h .= '<div class="util-buttons">'.$this->getPromotionButton().'</div>';
				$h .= '</div>';
			$h .= '</div>';

			return $h;

		}

		$h .= '<div class="dropdown-block util-action">';

			if($unread > 0) {
				$h .= $this->getReadAll($eStructure);
			}

			$h .= '<a href="'.self::url($eStructure, $section).'" class="btn btn-sm btn-primary notification-menu-all">'.s("Voir toutes").'</a>';

		$h .= '</div>';

		if($free) {
			$h .= '<div class="dropdown-block">';
				$h .= '<p class="util-annotation notification-menu-free">'.s("Les notifications sont proposées gratuitement jusqu'à fin octobre, puis seront disponibles dans une offre payante.").'</p>';
			$h .= '</div>';
		}

		return $h;

	}

	private function getReadAll(\structure\Structure $eStructure): string {
		return '<a data-ajax="/notification/notification:doReadAll" post-id="'.$eStructure['id'].'" class="notification-menu-read-all">'.\Asset::icon('check2-all').' '.s("Tout marquer comme lu").'</a>';
	}

	/**
	 * La promotion de l'offre Pilotage, dans le centre et dans les réglages. Décidée le
	 * 22/09.
	 */
	private function getPromotion(\structure\Structure $eStructure): string {

		return \main\DesignUi::getWelcome(
			s("Notifications de l'offre Pilotage"),
			'<p>'.$this->getPromotionText($eStructure).'</p>',
			\Asset::svg('main', 'friends/abeille.svg'),
			$this->getPromotionButton()
		);

	}

	private function getPromotionText(\structure\Structure $eStructure): string {

		return match($eStructure['type']) {
			\structure\Structure::COMMUNITY => s("Avec l'offre Pilotage, l'arrivée et le départ des membres de l'équipe s'affichent ici."),
			\structure\Structure::PRODUCER, \structure\Structure::SHOP, \structure\Structure::AMAP => s("Avec l'offre Pilotage, les commandes, la vie de vos groupes et les échéances de comptabilité s'affichent ici.")
		};

	}

	private function getPromotionButton(): string {
		return '<a href="'.\Lime::getUrl().'/presentation/offres" class="btn btn-primary">'.s("Découvrir l'offre Pilotage").'</a>';
	}

	private function getMenuItem(Notification $eNotification): string {

		if($eNotification['source'] !== NULL) {
			return $this->getMenuCommonItem($eNotification);
		}

		$state = self::getState($eNotification);

		$h = '<a '.$this->getRead($eNotification).' class="dropdown-item notification-menu-item '.self::getItemClass($state).'">';
			$h .= $this->getIcon($eNotification, $state);
			$h .= '<span class="notification-menu-text">';
				$h .= '<span class="notification-menu-title">'.$this->getText($eNotification).'</span>';

				$details = $this->implode($this->getDetails($eNotification));

				// Les détails nomment déjà ce qui distingue la ligne (la boutique d'une commande) : le nom
				// de groupe n'en est que le repli, sans quoi il se répéterait sous eux.
				if($details !== '') {
					$h .= '<span class="notification-menu-meta">'.$details.'</span>';
				} else {
					$h .= $this->getMenuMeta($eNotification);
				}

			$h .= '</span>';
			$h .= '<span class="notification-menu-when">'.$this->getWhen($eNotification['createdAt']).'</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * La ligne d'une source commune se lit dans la cloche comme dans le centre (`getCommonRow()`) :
	 * son texte entier sous le titre, puis son bouton.
	 */
	private function getMenuCommonItem(Notification $eNotification): string {

		$state = self::getState($eNotification);
		$sourceUi = $this->getSourceUi($eNotification['source']);

		$h = '<div class="dropdown-item notification-menu-item notification-menu-common '.self::getItemClass($state).'">';
			$h .= $this->getIcon($eNotification, $state);
			$h .= '<div class="notification-menu-text">';
				$h .= $this->getCommonContent($eNotification, $sourceUi, menu: TRUE, read: TRUE);
				$h .= '<span class="notification-menu-meta">'.$this->implode($this->getDetails($eNotification)).'</span>';
			$h .= '</div>';
			$h .= '<span class="notification-menu-when">'.$this->getWhen($eNotification['createdAt']).'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre, le texte et le bouton d'une ligne commune, dans la cloche comme dans le centre. Ni la
	 * ligne ni son titre ne sont des liens : le texte de l'éditeur porte les siens, et un lien dans un
	 * lien est défait par le navigateur. C'est donc le bouton qui lit la ligne, et une note sans
	 * bouton est lue dès qu'elle est vue, par sa source (`seeCommon()`).
	 *
	 * Dans la cloche, le corps porte `data-dropdown-keep` : sans lui, cliquer dans le texte pour le
	 * sélectionner fermerait le menu (`Lime.Dropdown.startCloseListener()`).
	 */
	private function getCommonContent(Notification $eNotification, object $sourceUi, bool $menu, bool $read): string {

		$button = $sourceUi->getCommonButton($eNotification);
		$attributes = $read ? $this->getRead($eNotification) : '';
		$titleClass = $menu ? 'notification-menu-title' : 'notification-text';

		$h = '<div class="'.$titleClass.'">'.$this->getText($eNotification).'</div>';
		$h .= '<div class="notification-common-body" '.($menu ? 'data-dropdown-keep' : '').'>'.$sourceUi->getCommonBody($eNotification).'</div>';

		if($button !== NULL) {
			$h .= '<a '.$attributes.' class="btn btn-sm btn-outline-primary notification-common-button">'.$button.'</a>';
		}

		return $h;

	}

	private function getMenuGroup(Notification $eNotificationHead): string {

		$state = self::getGroupState($eNotificationHead);

		$h = '<a class="dropdown-item dropdown-item-group notification-menu-item '.self::getItemClass($state).'" data-dropdown-keep '.attr('onclick', 'this.classList.toggle("dropdown-item-group-open")').'>';
			$h .= $this->getIcon($eNotificationHead, $state);
			$h .= '<span class="notification-menu-text">';
				$h .= '<span class="notification-menu-title">'.$this->getGroupText($eNotificationHead).'</span>';
				$h .= '<span class="notification-menu-summary">'.$this->implode([$this->getGroupSummary($eNotificationHead), self::getMoney($eNotificationHead['amountGroup'])]).'</span>';
				$h .= $this->getMenuMeta($eNotificationHead);
			$h .= '</span>';
			$h .= '<span class="notification-menu-aside">';
				$h .= '<span class="notification-menu-when">'.$this->getWhen($eNotificationHead['createdAt']).'</span>';
				$h .= '<span class="notification-menu-toggle">';
					$h .= '<span class="dropdown-item-group-chevron">'.\Asset::icon('chevron-down').'</span>';
				$h .= '</span>';
			$h .= '</span>';
		$h .= '</a>';

		$h .= '<div class="dropdown-item-group-items">';

			foreach($eNotificationHead['cNotificationGroup'] as $eNotification) {

				$h .= '<a '.$this->getRead($eNotification).' class="dropdown-item notification-menu-child '.self::getItemClass(self::getState($eNotification)).'">';
					$h .= '<span class="notification-menu-child-name">'.$this->getActor($eNotification).'</span>';
					$amount = $eNotification['amount'];

					if($amount !== NULL) {
						$h .= '<span class="notification-menu-child-amount">'.self::getMoney($amount).'</span>';
					}

					$h .= '<span class="notification-menu-when">'.$this->getTime($eNotification['createdAt']).'</span>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les réglages : un interrupteur par type, rangés par famille.
	 *
	 * @param array $settings [type => réglage], de `NotificationLib::getSettings()`, déjà rangés par
	 * famille : l'intertitre ne s'émet qu'au changement de famille.
	 */
	public function getSettings(\structure\Structure $eStructure, array $settings, bool $pilotage): string {

		// Sans l'offre, aucun type ne se règle : la promotion remplace le constat, qui serait faux.
		if($pilotage === FALSE) {
			return $this->getPromotion($eStructure);
		}

		if($settings === []) {

			$h = '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune notification à régler").'</h3>';
				$h .= '<p>'.s("Rien de ce que le site notifie aujourd'hui ne concerne cette structure.").'</p>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-table notification-settings mb-2">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Recevoir").'</div>';
			$h .= '</div>';

			$family = NULL;

			foreach($settings as $type => $setting) {

				if($setting['family'] !== $family) {
					$family = $setting['family'];
					$h .= '<div class="util-table-title">'.$this->getFamily($family).'</div>';
				}

				$h .= $this->getSetting($eStructure, $type, $setting);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne de réglage, que la réponse du geste remplace seule : le serveur décide de l'état, le
	 * client ne fait que le montrer.
	 *
	 * Les interrupteurs de l'écran partagent un `data-lock` : le verrou anti-double-envoi de
	 * `Ajax.Query` sérialise alors deux gestes rapprochés, qui liraient et réécriraient la même liste.
	 *
	 * L'interrupteur est le geste de toutes les lignes : allumer poste `scope`, que `getSettings()` pose
	 * sur la valeur qui suit OFF quand le type est éteint (la sentinelle exige OFF en tête), et qui
	 * vaut ON pour un type binaire. La portée d'un type à choix se règle sur la ligne de son nom, à sa
	 * suite (retours du 21/09, qui ont retiré la colonne « Ventes concernées » de la planche A2) :
	 * allumée, elle ouvre un menu des autres portées, éteinte, elle montre, figée, celle que
	 * l'interrupteur rallumera. Le menu ne propose jamais OFF, l'interrupteur s'en charge.
	 */
	public function getSetting(\structure\Structure $eStructure, string $type, array $setting): string {

		$locked = $setting['locked'];
		$on = ($setting['value'] !== NotificationSetting::OFF);

		$h = '<div id="'.self::getSettingId($type).'" class="util-table-row">';

			$h .= '<div>';
				$h .= '<div>';
					$h .= $this->getTypeName($type);
					if($setting['scopes'] !== []) {
						$h .= ' '.$this->getScope($eStructure, $type, $setting, $on);
					}
				$h .= '</div>';
				$h .= '<div class="color-muted font-sm">'.$this->getTypeDescription($type, $eStructure).'</div>';
			$h .= '</div>';

			$h .= '<div class="notification-settings-switch">';
				// Un type obligatoire se dit en toutes lettres : un interrupteur allumé et grisé se lirait
				// comme une panne (planche du lot 4, direction a). Le droit passe avant, un membre qui ne
				// le tient pas voit l'interrupteur verrouillé comme partout ailleurs.
				if($setting['mandatory'] and $locked === FALSE) {
					$h .= s("Toujours");
				} else {
					$h .= \util\TextUi::switch([
						'data-ajax' => '/notification/notification:doUpdateSetting',
						'data-lock' => 'notification-settings',
						'data-optimistic' => '',
						'post-id' => $eStructure['id'],
						'post-type' => $type,
						'post-value' => $on ? NotificationSetting::OFF : $setting['scope'],
						'disabled' => $locked,
						'title' => $locked ? $this->getLock($type, $setting['right']) : NULL
					], on: $on);
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	private function getScope(\structure\Structure $eStructure, string $type, array $setting, bool $on): string {

		$label = $this->getPackageUi($type)->getScope($setting['scope']);
		$badge = $this->getPackageUi($type)->getScopeClass($setting['scope']);

		if($on === FALSE) {
			return '<span class="btn btn-badge btn-sm btn-readonly badge badge-off '.$badge.'">'.$label.'</span>';
		}

		$h = '<a data-dropdown="bottom-start" class="btn btn-badge btn-sm badge '.$badge.' dropdown-toggle">'.$label.'</a>';

		$h .= '<div class="dropdown-list bg-primary">';

			foreach($setting['scopes'] as $scope) {

				if($scope === $setting['scope']) {
					continue;
				}

				$h .= '<a data-ajax="/notification/notification:doUpdateSetting" data-lock="notification-settings" post-id="'.$eStructure['id'].'" post-type="'.$type.'" post-value="'.$scope.'" class="dropdown-item">';
					$h .= \Asset::icon('arrow-right').'  <span class="badge '.$this->getPackageUi($type)->getScopeClass($scope).'">'.$this->getPackageUi($type)->getScope($scope).'</span>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public static function getSettingId(string $type): string {
		return 'notification-setting-'.$type;
	}

	private function getFamily(string $family): string {

		return match($family) {
			NotificationSetting::PRODUCTION => s("Production"),
			NotificationSetting::COMMERCIALISATION => s("Commercialisation"),
			NotificationSetting::ACCOUNTING => s("Comptabilité"),
			NotificationSetting::STRUCTURE => s("Équipe et groupes"),
			NotificationSetting::LEARNING => s("Formations"),
			NotificationSetting::SUBSCRIPTION => s("Abonnement")
		};

	}

	/**
	 * Le `NotificationUi` du package qui émet ce type, où vivent tous les mots qui parlent de l'objet
	 * notifié.
	 *
	 * Cet écran-ci tient la mise en page et rien d'autre : il ne nomme aucun type, et un package qui
	 * en ajoute un n'a aucune raison d'ouvrir ce fichier. Même convention de résolution que les
	 * `AlertUi` (`framework/core/Fail.php`), par le segment qui précède le premier tiret du type.
	 *
	 * Rendu `object` et non un type nommé : ces classes n'ont ni ancêtre ni interface commune, c'est
	 * leur nom que la convention résout. Leur inventaire de méthodes est tenu par
	 * `test/NotificationTypeSentinel.t.php`, pas par le typage.
	 */
	private function getPackageUi(string $type): object {

		$class = '\\'.NotificationSetting::getPackage($type).'\\NotificationUi';

		return new $class();

	}

	/**
	 * Le `NotificationUi` d'une source commune, résolu par le nom du package que porte la ligne. Il
	 * tient en plus ce que seule une source commune affiche (`getCommonBody()`, `getCommonButton()`),
	 * que `test/NotificationTypeSentinel.t.php` inventorie à part des méthodes de toute paire.
	 */
	private function getSourceUi(string $source): object {

		$class = '\\'.$source.'\\NotificationUi';

		return new $class();

	}

	private function getTypeName(string $type): string {
		return $this->getPackageUi($type)->getTypeName($type);
	}

	private function getTypeDescription(string $type, \structure\Structure $eStructure): string {
		return $this->getPackageUi($type)->getTypeDescription($type, $eStructure);
	}

	/**
	 * La raison du verrou, dans les mots de l'écran des rôles (`structure\RoleUi`) : un type se
	 * réserve par un droit ou, pour un groupe, par un rôle nommé (`fqn`, décidé le
	 * 22/09 pour les formations). Le package la donne d'après le droit et non d'après le type, deux
	 * types réservés au même droit se verrouillant pour la même raison.
	 */
	private function getLock(string $type, string $right): string {
		return $this->getPackageUi($type)->getLock($right);
	}

	public function getTitle(\structure\Structure $eStructure, int $unread): string {

		return \main\DesignUi::getPageHeader(
			s("Notifications").' '.$this->getCounter($unread),
			tools: ($unread > 0)
				? '<a data-ajax="/notification/notification:doReadAll" post-id="'.$eStructure['id'].'" class="btn btn-outline-secondary">'.\Asset::icon('check2-all').' <span class="hide-xs-down">'.s("Tout marquer comme lu").'</span></a>'
				: ''
		);

	}

	/**
	 * @param array $days Les lignes par jour, de `NotificationLib::getDays()`
	 */
	public function getCentre(\structure\Structure $eStructure, array $days, bool $pilotage): string {

		// Sans l'offre, le centre ne lit que les types ouverts à toutes les structures, et la
		// promotion se place sous eux. N'en ayant aucun à montrer, il ne montre qu'elle : le
		// constat « Aucune notification » annonce ce qui s'affichera, ce qui serait faux ici.
		if($pilotage === FALSE and $days === []) {
			return $this->getPromotion($eStructure);
		}

		if($days === []) {

			$h = '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune notification").'</h3>';
				$h .= '<p>'.match($eStructure['type']) {
					\structure\Structure::COMMUNITY => s("L'arrivée et le départ des fermes et des membres de l'équipe s'afficheront ici."),
					\structure\Structure::PRODUCER, \structure\Structure::SHOP, \structure\Structure::AMAP => s("Les commandes, la vie de vos groupes et les échéances de comptabilité s'afficheront ici.")
				}.'</p>';
			$h .= '</div>';

			return $h;

		}

		$h = '';

		foreach($days as $day => ['cNotification' => $cNotificationHead, 'unread' => $unread]) {

			$h .= '<div class="util-deck util-deck-open notification-day mb-2">';

				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= $this->getDay($day);
					$h .= '</div>';
					$h .= '<div class="util-deck-aside">'.$this->getCounter($unread).'</div>';
				$h .= '</div>';

				$h .= '<div class="util-deck-fold">';

					foreach($cNotificationHead as $eNotificationHead) {

						if($eNotificationHead['cNotificationGroup']->count() > 1) {
							$h .= $this->getGroup($eNotificationHead);
						} else {
							$h .= $this->getRow($eNotificationHead);
						}

					}

				$h .= '</div>';

			$h .= '</div>';

		}

		$h .= '<div class="util-info notification-end">'.s("Les notifications s'effacent au bout de {value} jours.", NotificationSetting::DAYS).'</div>';

		if($pilotage === FALSE) {
			$h .= $this->getPromotion($eStructure);
		}

		return $h;

	}

	private function getDay(string $day): string {

		$name = lcfirst(\util\DateUi::getDayName(date('N', strtotime($day)))).' '.\util\DateUi::textual($day, \util\DateUi::DAY_MONTH);

		if($day === \Clock::date()) {
			return '<div class="util-deck-title">'.s("Aujourd'hui").'</div><p>'.$name.'</p>';
		} else if($day === date('Y-m-d', \Clock::shift('yesterday'))) {
			return '<div class="util-deck-title">'.s("Hier").'</div><p>'.$name.'</p>';
		} else {
			return '<div class="util-deck-title">'.ucfirst($name).'</div>';
		}

	}

	private function getRow(Notification $eNotification): string {

		if($eNotification['source'] !== NULL) {
			return $this->getCommonRow($eNotification);
		}

		$state = self::getState($eNotification);

		$h = '<a '.$this->getRead($eNotification).' class="util-deck-row notification-row '.self::getRowClass($state).'">';
			$h .= $this->getIcon($eNotification, $state);
			$h .= '<span class="util-deck-row-name">';
				$h .= '<span class="notification-text">'.$this->getText($eNotification).'</span>';
				$details = $this->implode($this->getDetails($eNotification));

				if($details !== '') {
					$h .= '<span class="util-annotation notification-meta">'.$details.'</span>';
				}

			$h .= '</span>';
			$h .= '<span class="util-annotation notification-time">'.$this->getTime($eNotification['createdAt']).'</span>';
			$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * La ligne commune telle que ses destinataires la liront, pour l'aperçu de celui qui la rédige :
	 * le même rendu que le centre, sans lecture au clic, la ligne n'existant encore chez personne.
	 */
	public function getCommonPreview(Notification $eNotification): string {

		$h = '<div class="util-deck util-deck-open notification-common-preview">';
			$h .= '<div class="util-deck-fold">';
				$h .= $this->getCommonRow($eNotification, read: FALSE);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La ligne d'une source commune (`NotificationLib`, en tête) se lit dans sa ligne (D8-C) : son
	 * texte entier sous le titre, puis son bouton (`getCommonContent()`).
	 */
	private function getCommonRow(Notification $eNotification, bool $read = TRUE): string {

		$state = self::getState($eNotification);
		$sourceUi = $this->getSourceUi($eNotification['source']);

		$h = '<div class="util-deck-row notification-row notification-row-common '.self::getRowClass($state).'">';
			$h .= $this->getIcon($eNotification, $state);
			$h .= '<div class="util-deck-row-name">';
				$h .= $this->getCommonContent($eNotification, $sourceUi, menu: FALSE, read: $read);
				$h .= '<div class="util-annotation notification-meta">'.$this->implode($this->getDetails($eNotification)).'</div>';
			$h .= '</div>';
			$h .= '<span class="util-annotation notification-time">'.$this->getTime($eNotification['createdAt']).'</span>';
		$h .= '</div>';

		return $h;

	}

	private function getGroup(Notification $eNotificationHead): string {

		$state = self::getGroupState($eNotificationHead);
		$toggle = attr('onclick', 'this.closest(".util-deck-slot").classList.toggle("util-deck-open")');

		$h = '<div class="util-deck-slot notification-group '.self::getDeckClass($state).'">';

			// La tête ne porte aucun geste : elle est tout entière le lien qui ouvre le groupe, heure
			// comprise, et se compose comme une ligne (`getRow()`), le chevron à la place de la flèche.
			$h .= '<a class="util-deck-head" '.$toggle.'>';
				$h .= $this->getIcon($eNotificationHead, $state);
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title notification-text">'.$this->getGroupText($eNotificationHead).'</div>';
					$h .= '<p class="notification-group-summary">'.$this->getGroupSummary($eNotificationHead).'</p>';
					$details = $this->implode($this->getGroupDetails($eNotificationHead));

					if($details !== '') {
						$h .= '<p class="util-annotation notification-meta">'.$details.'</p>';
					}

				$h .= '</div>';
				$h .= '<span class="util-annotation notification-time">'.$this->getTime($eNotificationHead['createdAt']).'</span>';
				$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			$h .= '<div class="util-deck-fold">';

				foreach($eNotificationHead['cNotificationGroup'] as $eNotification) {

					$stateChild = self::getState($eNotification);

					$h .= '<a '.$this->getRead($eNotification).' class="util-deck-row notification-row '.self::getRowClass($stateChild).'">';
						$h .= $this->getIcon($eNotification, $stateChild);
						$h .= '<span class="util-deck-row-name">';
							$h .= '<span class="notification-text">'.$this->getText($eNotification).'</span>';
							$amount = $eNotification['amount'];

							if($amount !== NULL) {
								$h .= '<span class="util-annotation notification-meta">'.self::getMoney($amount).'</span>';
							}

						$h .= '</span>';
						$h .= '<span class="util-annotation notification-time">'.$this->getTime($eNotification['createdAt']).'</span>';
						$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
					$h .= '</a>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'état d'une ligne, dans le vocabulaire de la planche du 2026-09-21 (direction C) : arrivée
	 * depuis la dernière visite, vue mais pas encore cliquée, ou cliquée. `seenAt` précède toujours
	 * `readAt` (invariant de `NotificationLib`), donc deux comparaisons suffisent.
	 */
	private static function getState(Notification $eNotification): string {

		if($eNotification['seenAt'] === NULL) {
			return self::UNSEEN;
		}

		if($eNotification['readAt'] === NULL) {
			return self::PENDING;
		}

		return self::READ;

	}

	/**
	 * L'état d'une tête de groupe : elle est non vue dès qu'une des siennes l'est, vue mais pas
	 * cliquée quand elles sont toutes vues et qu'au moins une reste à lire, cliquée sinon. Les deux
	 * compteurs viennent de `NotificationLib::group()`, l'écran ne les calcule pas.
	 */
	private static function getGroupState(Notification $eNotificationHead): string {

		if($eNotificationHead['unseen'] > 0) {
			return self::UNSEEN;
		}

		if($eNotificationHead['unread'] > 0) {
			return self::PENDING;
		}

		return self::READ;

	}

	/** La classe d'une entrée de menu pour cet état. Une entrée cliquée n'en porte aucune. */
	private static function getItemClass(string $state): string {

		return match($state) {
			self::UNSEEN => 'dropdown-item-unread',
			self::PENDING => 'dropdown-item-pending',
			self::READ => ''
		};

	}

	/**
	 * La classe d'une ligne du centre pour cet état. Le gras sans fond s'ajoute à l'état lu du
	 * composant, qui le dépose : `util-deck-row-read` remet le poids normal, `-pending` le relève.
	 */
	private static function getRowClass(string $state): string {

		return match($state) {
			self::UNSEEN => 'util-deck-row-unread',
			self::PENDING => 'util-deck-row-read util-deck-row-pending',
			self::READ => 'util-deck-row-read'
		};

	}

	/** La classe d'une tête de groupe du centre pour cet état, sur le même partage que `getRowClass()`. */
	private static function getDeckClass(string $state): string {

		return match($state) {
			self::UNSEEN => 'util-deck-unread',
			self::PENDING => 'util-deck-read util-deck-pending',
			self::READ => 'util-deck-read'
		};

	}

	/**
	 * La source voyage avec l'id : une ligne d'une source commune et une notification de la base de la
	 * structure peuvent porter le même.
	 */
	private function getRead(Notification $eNotification): string {

		$h = 'data-ajax="/notification/notification:doRead" post-id="'.$eNotification['eStructure']['id'].'" post-notification="'.$eNotification['id'].'"';

		if($eNotification['source'] !== NULL) {
			$h .= ' post-source="'.$eNotification['source'].'"';
		}

		return $h;

	}

	private function getIcon(Notification $eNotification, string $state): string {

		[$classes, $svg] = $this->getPackageUi($eNotification['type'])->getIcon($eNotification);

		$dot = ($state === self::UNSEEN) ? ' util-icon-dot' : '';

		return '<span class="util-icon util-icon-sm '.$classes.$dot.'">'.$svg.'</span>';

	}

	/**
	 * La structure qui reçoit la notification, et le rôle qu'y tient le lecteur : une même personne
	 * reçoit dans un même centre ce que lui envoient sa structure et ses groupes.
	 */
	/**
	 * La méta d'une ligne du menu : ce que la ligne annonce quand la journée en porte plusieurs
	 * (`groupName`, posé par `NotificationLib::group()`). Le menu n'a pas la place de la méta du
	 * centre, mais sans ce nom deux boutiques qui livrent le même jour y donnent deux lignes au texte
	 * identique. Rien sinon : la cloche ne montre que la structure où elle est posée.
	 */
	private function getMenuMeta(Notification $eNotificationHead): string {

		if($eNotificationHead['groupName'] === NULL) {
			return '';
		}

		return '<span class="notification-menu-meta">'.encode($eNotificationHead['groupName']).'</span>';

	}

	private function getText(Notification $eNotification): string {
		return $this->getPackageUi($eNotification['type'])->getText($eNotification);
	}

	private function getGroupText(Notification $eNotificationHead): string {
		return $this->getPackageUi($eNotificationHead['type'])->getGroupText($eNotificationHead);
	}

	/**
	 * Les trois premiers noms du groupe, et le compte des autres.
	 */
	private function getGroupSummary(Notification $eNotificationHead): string {

		$names = [];

		foreach($eNotificationHead['cNotificationGroup'] as $eNotification) {
			$names[] = $this->getActor($eNotification);
		}

		// Un même acteur revient sur plusieurs lignes d'un groupe (deux commandes d'un client, deux
		// entretiens d'un matériel) : le résumé le nomme une fois, et « autres » compte des noms.
		$names = array_values(array_unique($names));

		$others = count($names) - 3;

		if($others > 0) {
			return p("{names} et {value} autre", "{names} et {value} autres", $others, ['names' => implode(', ', array_slice($names, 0, 3))]);
		} else {
			return implode(', ', $names);
		}

	}

	private function getActor(Notification $eNotification): string {
		return $this->getPackageUi($eNotification['type'])->getActor($eNotification);
	}

	/**
	 * Un montant, ou rien du tout quand il est inconnu : un groupe sans aucune ligne chiffrée n'a pas
	 * de total, et un zéro serait faux. Publique parce que les `NotificationUi` des packages
	 * l'emploient : la règle ne vaut la peine d'être écrite qu'une fois.
	 */
	public static function getMoney(?float $amount): ?string {

		if($amount === NULL) {
			return NULL;
		}

		return \util\TextUi::money($amount);

	}

	/**
	 * Joint les morceaux d'une méta par le point médian, en sautant ceux qui manquent (un montant
	 * inconnu).
	 */
	private function implode(array $parts): string {
		return implode(' · ', array_filter($parts, fn(?string $part) => $part !== NULL));
	}

	/**
	 * La méta d'une ligne du centre, que chaque package compose : la boutique et le montant d'une commande.
	 */
	private function getDetails(Notification $eNotification): array {
		return $this->getPackageUi($eNotification['type'])->getDetails($eNotification);
	}

	private function getGroupDetails(Notification $eNotificationHead): array {
		return $this->getPackageUi($eNotificationHead['type'])->getGroupDetails($eNotificationHead);
	}

	private function getTime(string $datetime): string {

		[$hours, $minutes] = explode(':', substr($datetime, 11, 5));

		return s("{hours}&nbsp;h&nbsp;{minutes}", ['hours' => $hours, 'minutes' => $minutes]);

	}

	/**
	 * L'heure dans le menu, qui n'a pas d'intertitre par jour : l'heure le jour même, « hier » la
	 * veille, la date au-delà.
	 */
	private function getWhen(string $datetime): string {

		$day = substr($datetime, 0, 10);

		if($day === \Clock::date()) {
			return $this->getTime($datetime);
		} else if($day === date('Y-m-d', \Clock::shift('yesterday'))) {
			return s("hier");
		} else {
			return \util\DateUi::numeric($day, \util\DateUi::DAY_MONTH);
		}

	}

}
?>
