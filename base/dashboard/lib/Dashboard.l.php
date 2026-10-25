<?php
namespace dashboard;

/**
 * Le moteur de blocs : ce que l'accueil d'une structure affiche, et dans quel ordre.
 *
 * Il ne nomme aucun bloc. L'inventaire se dérive des packages et de rien d'autre : un package qui
 * apporte des blocs porte une paire `DashboardLib` + `DashboardUi`, et c'est son `getBlocks()` qui
 * fait foi. Aucune table du moteur ne double cette liste, sans quoi ajouter un bloc demanderait
 * d'ouvrir un fichier que son auteur n'a aucune raison de connaître.
 *
 * La même paire porte une seconde forme de déclaration, `getContributions()`, pour les pièces du poste
 * du haut, qui n'est pas une carte et qu'un bloc ne saurait pas dire. Les deux formes se résolvent par
 * la même convention de nom, se filtrent par le même `getEntry()` et se rendent par les mêmes trois
 * méthodes d'UI : ce qui change est l'endroit où la pièce se range, une section pour un bloc, un
 * emplacement de `DashboardSetting::SLOTS` pour une contribution.
 *
 * Le contrat de la paire est écrit dans `base/dashboard/CLAUDE.md` et tenu par
 * `test/DashboardBlockSentinel.t.php`.
 */
class DashboardLib {

	/** Le sens d'une comparaison, pour `compareEmptyLast()`, où un booléen ne dirait pas lequel est lequel. */
	private const int ASCENDING = 1;
	private const int DESCENDING = -1;

	/**
	 * La forme de la donnée cachée, à monter dès qu'un `load()` dont la déclaration porte un `cache` change
	 * ce qu'il rend.
	 *
	 * Une entrée posée avant le changement reste lisible jusqu'à son expiration, et l'UI qui attend la
	 * nouvelle forme lève sur l'ancienne : une heure d'accueil cassé pour qui l'ouvre, en dev comme au
	 * déploiement (cas vu le 2026-10-05, les barres passées d'une liste de couples à une liste de
	 * nombres). La version dans la clé fait de l'ancienne entrée une clé que plus personne ne lit, qui
	 * expire seule.
	 *
	 * 6 : le bilan lit seul ses semaines, et le chiffre de la semaine ne se cache plus (L62).
	 * 7 : chaque livraison des boutiques en ligne porte sa livraison et sa boutique, où sa ligne mène (L87).
	 */
	const int CACHE_VERSION = 7;

	/**
	 * Les deux inventaires, assemblés une fois par requête.
	 *
	 * Le balayage essaie de charger une classe par package et par passage, et un accueil le demandait
	 * quatre fois : `getContributions()` le refait pour écarter les noms déjà pris, les deux chargements
	 * le redemandent chacun, et la vue interroge `hasBlock()`.
	 *
	 * ⚠️ Ces deux tableaux ne dépendent d'aucune structure : ce sont des **déclarations**, écrites dans le
	 * code des packages, et rien n'y varie d'un tenant à l'autre. Une mémoïsation statique serait fautive
	 * s'ils portaient une donnée, et c'est pourquoi ils ne portent pas la visibilité, qui se décide
	 * structure par structure et reste hors du cache.
	 */
	private static ?array $blocks = NULL;
	private static ?array $contributions = NULL;

	/**
	 * Tous les blocs que le produit sait afficher, et ce que chacun suppose.
	 *
	 * Chaque déclaration dit de quelle `section` le bloc relève, dans quelle `column` de l'accueil il se
	 * range (`DashboardSetting::COLUMNS_BLOCK`, celle de ce qui se surveille par défaut), sous quelle `shape`
	 * (`DashboardSetting::SHAPES`, la tuile par défaut), quelle fonctionnalité l'ouvre
	 * (`feature`, une colonne de `structureFeature`) et quel droit le montre (`right`, une méthode
	 * `can*` de `structure\Structure`, ou une liste de méthodes qui doivent toutes répondre) : un bloc
	 * n'écrit aucun `if` de droit dans son code. Une liste sert au bloc qui résume un écran gardé par
	 * deux droits : sans elle, le second se lirait dans `load()`, après que la cellule différée a été
	 * posée, et son titre paraîtrait puis disparaîtrait à chaque accueil. `accept` pose à son tour
	 * des méthodes `accept*` de `structure\Structure`, qui disent ce que la structure a déjà fait : un
	 * bloc de production n'a rien à montrer à qui n'a planifié aucune série. Elle dit
	 * aussi s'il paraît sans que personne n'ait rien réglé (`default`), ce qu'il coûte (`cost`),
	 * combien de secondes son résultat se garde (`cache`), combien d'items il montre au plus
	 * (`max`), son rang dans sa section tant que le membre ne l'a pas rangé (`position`), et s'il porte à
	 * droite de son titre un « Choisir » qui règle ce qu'il montre (`choose`). Un bloc qui le déclare
	 * apporte `getChoice()` dans sa paire, que la route du panneau appelle (`loadChoice()`).
	 *
	 * Seule `section` est obligatoire, les autres clés portent un défaut (`getDefaults()`) : une clé
	 * ajoutée plus tard ne touchera que les déclarations qui la veulent.
	 *
	 * @return array [bloc => ['section' => …, 'column' => …, 'shape' => …, 'feature' => …, 'right' => string|string[], 'accept' => string[], 'default' => TRUE|FALSE|Closure, 'cost' => …, 'cache' => int, 'max' => ?int, 'position' => int, 'choose' => bool]]
	 */
	public static function getBlocks(): array {

		if(self::$blocks !== NULL) {
			return self::$blocks;
		}

		$blocks = [];

		// Le balayage plutôt qu'une liste déclarée dans `package.c.php` : cette liste-là est
		// régénérée, et un package qui apporterait un bloc sans la régénérer verrait son bloc disparaître
		// sans un mot. Forme de `NotificationLib::getTypes()` (`base/notification/lib/Notification.l.php:130`).
		foreach(\Package::getList() as $package => $app) {

			// Le package du moteur porte ce Lib-ci et n'apporte aucun bloc : sans cette sortie, le
			// balayage s'appellerait lui-même.
			if($package === __NAMESPACE__) {
				continue;
			}

			$class = '\\'.$package.'\\DashboardLib';

			if(class_exists($class) === FALSE) {
				continue;
			}

			foreach($class::getBlocks() as $block => $declaration) {

				// Une fusion silencieuse ferait disparaître le second bloc de l'écran, et son
				// package n'aurait aucun endroit où s'en apercevoir. Forme du refus de
				// `QueueLib::getConsumers()` (`framework/queue/lib/Queue.l.php:88-92`).
				if(array_key_exists($block, $blocks)) {
					throw new \Exception('Block '.$block.' is declared by two packages');
				}

				$declaration += self::getDefaults();

				// Le rangement lève ici plutôt que d'écarter le bloc plus loin : une section inconnue
				// n'est ouvrable par personne, et un bloc qui disparaît sans un mot est le mode de
				// panne que ce moteur doit rendre impossible.
				if(in_array($declaration['section'], DashboardSetting::SECTIONS, TRUE) === FALSE) {
					throw new \Exception('Block '.$block.' declares an unknown section '.$declaration['section']);
				}

				// Même refus pour la colonne, et pour la même raison : un bloc rangé dans une colonne que le
				// socle ne nomme pas ne paraîtrait nulle part. « Bilan » est nommée, mais réservée au bilan.
				if(in_array($declaration['column'], DashboardSetting::COLUMNS_BLOCK, TRUE) === FALSE) {
					throw new \Exception('Block '.$block.' declares a column no block may take: '.$declaration['column']);
				}

				// Et pour la forme : le socle ne sait dessiner que celles qu'il nomme.
				if(in_array($declaration['shape'], DashboardSetting::SHAPES, TRUE) === FALSE) {
					throw new \Exception('Block '.$block.' declares an unknown shape '.$declaration['shape']);
				}

				$blocks[$block] = $declaration;

			}

		}

		self::$blocks = $blocks;

		return $blocks;

	}

	private static function getDefaults(): array {

		return [
			'column' => DashboardSetting::FOLLOW,
			'shape' => DashboardSetting::TILE,
			'feature' => NULL,
			'right' => 'canRead',
			'accept' => [],
			'default' => TRUE,
			'cost' => DashboardSetting::LIGHT,
			'cache' => 0,
			'max' => NULL,
			'position' => 0,
			'choose' => FALSE
		];

	}

	/**
	 * Toutes les pièces du poste du haut que le produit sait afficher, et ce que chacune suppose.
	 *
	 * Même forme que `getBlocks()`, au balayage près : c'est le `getContributions()` de chaque paire qui
	 * fait foi, et le nom s'écrit de la même façon. Une contribution désigne un `slot` de
	 * `DashboardSetting::SLOTS` au lieu d'une section, porte un `position` qui range ses voisines du même
	 * emplacement, et n'a pas de `max`, ne rendant pas de liste. Celle du bilan déclare aussi la
	 * `section` dont sa bande prend la couleur.
	 *
	 * ⚠️ La méthode est **obligatoire** sur toute paire, et n'est pas gardée par `method_exists()` : un
	 * package qui n'apporte aucune contribution rend un tableau vide. Gardée, la sentinelle ne saurait
	 * plus dire si une paire est complète, et c'est le seul contrôle qui tienne ce contrat.
	 *
	 * @return array [contribution => ['slot' => …, 'section' => ?string, 'feature' => …, 'right' => string|string[], 'accept' => string[], 'default' => TRUE|FALSE|Closure, 'cost' => …, 'cache' => int, 'position' => int, 'shape' => 'tile', 'choose' => FALSE]]
	 */
	public static function getContributions(): array {

		if(self::$contributions !== NULL) {
			return self::$contributions;
		}

		$contributions = [];
		$blocks = self::getBlocks();

		foreach(\Package::getList() as $package => $app) {

			if($package === __NAMESPACE__) {
				continue;
			}

			$class = '\\'.$package.'\\DashboardLib';

			if(class_exists($class) === FALSE) {
				continue;
			}

			foreach($class::getContributions() as $contribution => $declaration) {

				if(array_key_exists($contribution, $contributions)) {
					throw new \Exception('Contribution '.$contribution.' is declared by two packages');
				}

				// Les deux formes partagent l'identifiant de nœud que le socle pose sur l'écran : le
				// même nom des deux côtés le poserait deux fois, et le second nœud serait celui que
				// `document.getElementById()` ne trouve jamais.
				if(array_key_exists($contribution, $blocks)) {
					throw new \Exception('Contribution '.$contribution.' is also declared as a block');
				}

				// Un défaut dit ce que le membre voit tant qu'il n'a rien réglé : sur un emplacement qu'il
				// ne règle pas, il n'aurait jamais de réglage pour le contredire, et une contribution
				// déclarée éteinte n'y paraîtrait chez personne.
				if(
					in_array($declaration['slot'], DashboardSetting::SLOTS_FIXED, TRUE)
					and array_key_exists('default', $declaration)
				) {
					throw new \Exception('Contribution '.$contribution.' declares a default in the fixed slot '.$declaration['slot']);
				}

				$declaration += self::getContributionDefaults();

				if(in_array($declaration['slot'], DashboardSetting::SLOTS, TRUE) === FALSE) {
					throw new \Exception('Contribution '.$contribution.' declares an unknown slot '.$declaration['slot']);
				}

				// Le bilan a sa bande, que le socle colore comme celle d'un univers : son occupant dit
				// laquelle, le socle ne sachant pas de quel univers parle un bilan.
				if(
					$declaration['slot'] === DashboardSetting::BILAN
					and in_array($declaration['section'], DashboardSetting::SECTIONS, TRUE) === FALSE
				) {
					throw new \Exception('Contribution '.$contribution.' in the slot '.$declaration['slot'].' declares no known section');
				}

				$contributions[$contribution] = $declaration;

			}

		}

		self::$contributions = $contributions;

		return $contributions;

	}

	/**
	 * `position` range les occupants d'un même emplacement, et deux packages peuvent porter le même
	 * nombre sans que personne n'ait à connaître l'autre : l'égalité est tranchée par l'ordre de
	 * déclaration, qui est celui de `\Package::getList()`.
	 */
	private static function getContributionDefaults(): array {

		return [
			'section' => NULL,
			'feature' => NULL,
			'right' => 'canRead',
			'accept' => [],
			'default' => TRUE,
			'cost' => DashboardSetting::LIGHT,
			'cache' => 0,
			'position' => 0,
			// Lus par `DashboardUi::getBlock()`, qui rend aussi la tuile du bilan. Un emplacement n'a pas de
			// panneau de choix ni de forme à lui, et la sentinelle refuse ces clés dans une déclaration de
			// contribution.
			'shape' => DashboardSetting::TILE,
			'choose' => FALSE
		];

	}

	/**
	 * Le jour et la semaine que la tête de l'accueil met en phrase. Les bornes se calculent ici : l'UI
	 * les met en phrase, elle ne les déduit pas.
	 */
	public static function getToday(): array {

		$week = \Clock::week();

		return [
			'date' => \Clock::date(),
			'week' => $week,
			'weekFrom' => week_date_starts($week),
			'weekTo' => week_date_ends($week)
		];

	}

	/**
	 * L'équipe que la tête de l'accueil montre en 3D (A8 de 3d-avatars) : les membres au planning cette semaine, chacun
	 * avec la fiche de son personnage, et les absences de la semaine qui ne sont pas finies (planche v6).
	 *
	 * - `cUser` : `$eUser` en tête, puis les membres de la liste des absences, puis ceux qui ont un avatar, puis les
	 *   autres, `TEAM_MAX` au plus. La tête pose la personne connectée à sa droite, près de la roue, et ne garde que
	 *   ceux que sa largeur tient : une personne que la liste nomme passe ainsi avant les autres, et un personnage avant
	 *   un corps gris. Chacun porte `fiche`, `NULL` sans avatar, `me`, `away`, absent toute la journée aujourd'hui, que
	 *   la tête met au second plan, et `absent`, nommé dans la liste. La personne connectée absente toute la journée
	 *   passe au second plan comme les autres et quitte sa place sous la roue. `eUserAvatarUpload` porte le modèle
	 *   téléversé que la tête monte à la place de la fiche (`UserAvatarUploadLib::getByUsers()`), pour les
	 *   `TEAM_UPLOADS_MAX` premiers qui en ont un ; il est vide pour les autres, et la fiche reste le repli d'un modèle
	 *   que le navigateur ne lit pas.
	 * - `cUserAbsent` : les absences, dans l'ordre de leur premier jour restant, chacun avec `absenceWeek`,
	 *   `absenceDays` et `absenceHours` (`getAbsences()`).
	 * - `cUserAbsentListed` : celles que la liste nomme, les `TEAM_ABSENCES_MAX` premières, et `absentOthers`, le
	 *   nombre de celles qu'elle compte sans les nommer (arbitrage 26). `absentOthersLink` dit si ce compte mène à
	 *   l'onglet Absences du planning de la semaine : celui-ci refuse une semaine hors des saisons de la structure
	 *   (`production/planning/page/absence.p.php`), et le compte reste alors en texte.
	 * - `silhouette` : la fiche du corps commun dans sa tenue par défaut, que la tête peint en gris pour un membre sans
	 *   avatar (arbitrage 1 de V-3D, le 23/10).
	 * - `invite` : la personne connectée n'a pas d'avatar et peut en faire un (`$editor`, l'éditeur ouvert pour elle).
	 *   Elle n'est alors pas dans `cUser` : sa place porte « Faire mon avatar ».
	 *
	 * Les absences ne se lisent que sous `$absences`, le droit de voir celles des autres au planning
	 * (`Structure::canPlanningLimited()`) : l'accueil n'en dit pas plus que le planning. Sans lui, ni liste ni second plan.
	 *
	 * Au planning de la semaine (arbitrage 19), comme les personnes que propose le planning (`TimeTeamLib::getUsersForWeek()`) :
	 * une période qui touche la semaine, et la personne connectée, qui a toujours sa place. Sans le filtre du temps
	 * que chacun peut écrire, qui ne laisserait à un membre sans droit de gestion que lui-même. Les personnes sans
	 * compte sont écartées : elles n'auront jamais d'avatar, et la tête se remplirait de corps gris (arbitrage 2).
	 *
	 * L'objet en main ne vient pas de la fiche, que `UserAvatarLib::getFiche()` rend sans objet : c'est le moment de la
	 * journée qui le choisit dans la page, la tasse du matin, les mains vides le jour et le soir.
	 */
	public static function getTeam(\structure\Structure $eStructure, \user\User $eUser, bool $editor, bool $absences): array {

		$week = \Clock::week();

		$cPresence = \hr\PresenceLib::getBetween($eStructure, week_date_starts($week), week_date_ends($week));

		$cUserMember = \structure\MemberLib::getByStructure($eStructure, index: NULL)
			->find(fn(\structure\Member $eMember) => (
				$eMember['role']->isGhost() === FALSE and
				(
					$eMember['user']->is($eUser) or
					$cPresence->offsetExists($eMember['user']['id'])
				)
			))
			->getColumnCollection('user', 'user');

		$cUserAbsent = $absences ? self::getAbsences($eStructure, $cUserMember, $cPresence, $week) : new \Collection();

		$cUserAvatar = \user\UserAvatarLib::getByUsers($cUserMember);
		$cUserAvatarUpload = \user\UserAvatarUploadLib::getByUsers($eStructure, $cUserMember);

		// La personne connectée sans avatar n'a pas de corps gris : sa place porte l'invitation à en faire un
		$invite = ($editor and $cUserAvatar->offsetExists($eUser['id']) === FALSE);

		// Quatre rangs, dans l'ordre où la tête les pose : la personne connectée, celles que la liste nomme, les
		// personnages, les corps gris
		$ranks = [[], [], [], []];

		foreach($cUserMember as $userId => $eUserMember) {

			$hasAvatar = $cUserAvatar->offsetExists($userId);

			if($eUserMember->is($eUser)) {

				if($invite) {
					continue;
				}

				$rank = 0;

			} else if($cUserAbsent->offsetExists($userId)) {
				$rank = 1;
			} else {
				$rank = $hasAvatar ? 2 : 3;
			}

			$eUserMember['me'] = ($rank === 0);
			$eUserMember['fiche'] = $hasAvatar ? \user\UserAvatarLib::getFiche($cUserAvatar[$userId]) : NULL;
			$eUserMember['absent'] = $cUserAbsent->offsetExists($userId);
			$eUserMember['away'] = ($eUserMember['absent'] and $cUserAbsent[$userId]['absenceAway']);
			$ranks[$rank][$userId] = $eUserMember;

		}

		// Celles que la liste nomme, dans son ordre
		$listed = $ranks[1];
		$ranks[1] = [];

		foreach($cUserAbsent->getIds() as $userId) {
			if(array_key_exists($userId, $listed)) {
				$ranks[1][$userId] = $listed[$userId];
			}
		}

		$cUser = new \Collection(array_slice($ranks[0] + $ranks[1] + $ranks[2] + $ranks[3], 0, DashboardSetting::TEAM_MAX, preserve_keys: TRUE));

		// Le plafond suit l'ordre de la tête : les premiers montrés reçoivent leur modèle, les suivants gardent leur fiche
		$uploads = 0;

		foreach($cUser as $userId => $eUserMember) {

			if($cUserAvatarUpload->offsetExists($userId) and $uploads < DashboardSetting::TEAM_UPLOADS_MAX) {
				$eUserMember['eUserAvatarUpload'] = $cUserAvatarUpload[$userId];
				$uploads++;
			} else {
				$eUserMember['eUserAvatarUpload'] = new \user\UserAvatarUpload();
			}

		}

		return [
			'cUser' => $cUser,
			'cUserAbsent' => $cUserAbsent,
			'cUserAbsentListed' => $cUserAbsent->slice(0, min($cUserAbsent->count(), DashboardSetting::TEAM_ABSENCES_MAX), preserveKeys: TRUE),
			'absentOthers' => max(0, $cUserAbsent->count() - DashboardSetting::TEAM_ABSENCES_MAX),
			'absentOthersLink' => $eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($week)),
			'silhouette' => \user\UserAvatarLib::getFiche(new \user\UserAvatar([
				'source' => \user\UserAvatar::EDITOR,
				'composition' => \user\UserAvatarLib::getDefault()
			])),
			'invite' => $invite
		];

	}

	/**
	 * La liste des absences de la tête (planche v6, arbitrages 23 et 24) : les personnes de `$cUser` dont une absence
	 * validée tombe un jour de leur contrat de la semaine, à partir d'aujourd'hui. Une absence finie ne se dit plus. Le
	 * motif n'est pas lu (`AbsenceLib::fillUsers()`) : l'équipe voit qu'une personne est absente, jamais pourquoi.
	 *
	 * Chacune porte `absenceWeek`, absente toute la journée chaque jour de son contrat de la semaine, jours passés
	 * compris, `absenceDays`, ses jours restants, aujourd'hui compris, par date, avec la part du jour
	 * (`getAbsencePart()`), `absenceHours`, pour chaque jour en heures, ses plages (`getAbsenceHours()`), et
	 * `absenceAway`, absente toute la journée aujourd'hui.
	 *
	 * Les jours du contrat sont ceux du solde d'heures (`OvertimeLib::getContractDays()`), dans une période, fériés retirés.
	 *
	 * Une absence en heures reste dans la liste jusqu'au soir de son jour, même finie (arbitrage 25) : la liste dit les
	 * absences de la semaine qui restent, une plage passée comprise tant que son jour n'est pas fini.
	 */
	private static function getAbsences(\structure\Structure $eStructure, \Collection $cUser, \Collection $cPresence, string $week): \Collection {

		\hr\AbsenceLib::fillUsers($eStructure, $cUser, week_date_starts($week), week_date_ends($week));

		$cUserAbsence = $cUser->find(fn(\user\User $eUser) => $eUser['cAbsence']->notEmpty());

		if($cUserAbsence->empty()) {
			return new \Collection();
		}

		$cContract = new \Collection();

		foreach(\hr\ContractLib::getByUsers($eStructure, $cUserAbsence, week_date_starts($week), week_date_ends($week)) as $eContract) {
			$cContract[$eContract['presence']['id']] = $eContract;
		}

		$today = \Clock::date();
		$dates = week_dates($week);

		$cUserAbsent = new \Collection();

		foreach($cUserAbsence as $userId => $eUser) {

			// La personne connectée a sa place sans période : sans période, elle n'a pas de jour dont s'absenter
			if($cPresence->offsetExists($userId) === FALSE) {
				continue;
			}

			$worked = 0;
			$whole = 0;
			$days = [];
			$hours = [];

			foreach($dates as $date) {

				if(self::isContractDay($cPresence[$userId], $cContract, $date) === FALSE) {
					continue;
				}

				$part = self::getAbsencePart($eUser['cAbsence'], $date);

				$worked++;

				if($part === DashboardSetting::ABSENCE_DAY) {
					$whole++;
				}

				if($part !== NULL and $date >= $today) {

					$days[$date] = $part;

					if($part === DashboardSetting::ABSENCE_HOURS) {
						$hours[$date] = self::getAbsenceHours($eUser['cAbsence'], $date);
					}

				}

			}

			if($days === []) {
				continue;
			}

			$eUser['absenceWeek'] = ($whole === $worked);
			$eUser['absenceDays'] = $days;
			$eUser['absenceHours'] = $hours;
			$eUser['absenceAway'] = (array_key_first($days) === $today and $days[$today] === DashboardSetting::ABSENCE_DAY);

			$cUserAbsent[$userId] = $eUser;

		}

		// Celles qui manquent aujourd'hui d'abord, puis les autres dans l'ordre de leur premier jour ; à jour égal, le
		// prénom, dans lequel le planning range aussi son équipe
		return $cUserAbsent->sort(fn(\user\User $eUser1, \user\User $eUser2) => (
			[array_key_first($eUser1['absenceDays']), $eUser1['firstName'] ?? $eUser1['lastName']] <=>
			[array_key_first($eUser2['absenceDays']), $eUser2['firstName'] ?? $eUser2['lastName']]
		));

	}

	private static function isContractDay(\Collection $cPresence, \Collection $cContract, string $date): bool {

		if(\hr\HolidayLib::isHoliday($date)) {
			return FALSE;
		}

		$ePresence = \hr\Presence::isDatePresent($cPresence, $date);

		if($ePresence->empty()) {
			return FALSE;
		}

		$eContract = $cContract->offsetExists($ePresence['id']) ? $cContract[$ePresence['id']] : new \hr\Contract();

		return \hr\OvertimeLib::getContractDays($eContract)->isDayWorked($date);

	}

	/**
	 * La part du jour `$date` que couvrent les absences de `$cAbsence`, `NULL` pour un jour sans absence. Un matin et un
	 * après-midi posés par deux absences font la journée.
	 */
	private static function getAbsencePart(\Collection $cAbsence, string $date): ?string {

		$parts = [];

		foreach($cAbsence as $eAbsence) {

			if(
				$date < substr($eAbsence['from'], 0, 10) or
				$date > substr($eAbsence['to'], 0, 10)
			) {
				continue;
			}

			$part = $eAbsence->getPartOn($date);

			if($eAbsence['unit'] === \hr\Absence::HOUR) {
				$parts[] = DashboardSetting::ABSENCE_HOURS;
			} else if($part === NULL) {
				$parts[] = DashboardSetting::ABSENCE_DAY;
			} else if($part['to'] !== NULL and $part['from'] === NULL) {
				$parts[] = DashboardSetting::ABSENCE_MORNING;
			} else if($part['from'] !== NULL and $part['to'] === NULL) {
				$parts[] = DashboardSetting::ABSENCE_AFTERNOON;
			} else {
				$parts[] = DashboardSetting::ABSENCE_HOURS;
			}

		}

		$parts = array_unique($parts);

		if($parts === []) {
			return NULL;
		} else if(
			in_array(DashboardSetting::ABSENCE_DAY, $parts, TRUE) or
			(in_array(DashboardSetting::ABSENCE_MORNING, $parts, TRUE) and in_array(DashboardSetting::ABSENCE_AFTERNOON, $parts, TRUE))
		) {
			return DashboardSetting::ABSENCE_DAY;
		} else if(count($parts) === 1) {
			return $parts[array_key_first($parts)];
		} else {
			// Des heures et une demi-journée : le jour seul, comme pour des heures
			return DashboardSetting::ABSENCE_HOURS;
		}

	}

	/**
	 * Les plages des absences en heures du jour `$date`, dans l'ordre du jour, chacune `from` et `to` en `hh:mm`. Une
	 * demi-journée posée le même jour n'est pas une plage : la liste dit alors le jour seul, et ce tableau est vide.
	 */
	private static function getAbsenceHours(\Collection $cAbsence, string $date): array {

		$ranges = [];

		foreach($cAbsence as $eAbsence) {

			if(
				$date < substr($eAbsence['from'], 0, 10) or
				$date > substr($eAbsence['to'], 0, 10)
			) {
				continue;
			}

			if($eAbsence['unit'] !== \hr\Absence::HOUR) {
				return [];
			}

			$ranges[] = $eAbsence->getPartOn($date);

		}

		usort($ranges, fn(array $range1, array $range2) => $range1['from'] <=> $range2['from']);

		return $ranges;

	}

	/**
	 * L'écart de `$now` à `$before`, en pour cent arrondi à l'unité, ou `NULL` quand il n'y a rien à
	 * écrire : une période d'avant à zéro n'a pas d'écart qui ait un sens, et « 0 % » ne dit rien.
	 * L'UI n'en garde que le signe et les mots.
	 */
	public static function getVariation(int|float $now, int|float $before): ?int {

		if($before <= 0) {
			return NULL;
		}

		$percent = (int)round(($now - $before) * 100 / $before);

		return ($percent === 0) ? NULL : $percent;

	}

	/**
	 * Les blocs que cette structure affiche, dans l'ordre de l'écran, chacun avec sa déclaration,
	 * l'adresse de son « voir tout » et sa donnée.
	 *
	 * L'ordre est `(rang de section, position réglée, ordre de déclaration)`. La `position` est
	 * relative à sa section : l'utilisateur range les blocs d'un univers entre eux (arbitré
	 * le 2026-09-28). L'écran répartit ensuite cette liste entre ses colonnes sans la retrier
	 * (`DashboardUi::getColumns()`) : chaque colonne garde cet ordre, et aucune préférence n'a changé de
	 * sens avec elles (L45).
	 */
	public static function load(\structure\Structure $eStructure, bool $setting = FALSE): array {

		$preferences = PreferenceLib::getByMember($eStructure);

		$blocks = [];

		foreach(self::sortBySection(self::getBlocks(), $preferences) as $block => $declaration) {

			$entry = self::getEntry($eStructure, $block, $declaration, preferences: $preferences, setting: $setting);

			if($entry !== NULL) {
				$blocks[$block] = $entry;
			}

		}

		return $blocks;

	}

	/**
	 * Les pièces du poste que cette structure affiche, rangées par emplacement puis par `position`.
	 *
	 * Un emplacement sans occupant est **absent** et non une clé vide : le socle sait alors qu'il n'a
	 * rien à dessiner, et le poste entier ne se rend pas du tout quand le tableau est vide.
	 *
	 * `$slots` borne la lecture aux emplacements qu'un écran ou un geste rend, l'ordre restant celui de
	 * `DashboardSetting::SLOTS` : l'accueil et son réglage ne lisent que leur tête et leurs colonnes, le
	 * groupe Bureau du menu la météo (`loadHome()`).
	 *
	 * Le réglage lit les mêmes pièces que l'accueil : aucune ne porte d'interrupteur, et il montre
	 * l'accueil tel qu'il est.
	 *
	 * @return array [emplacement => [contribution => ['declaration' => …, 'url' => …, 'data' => …]]]
	 */
	public static function loadContributions(\structure\Structure $eStructure, array $slots = DashboardSetting::SLOTS): array {

		$preferences = PreferenceLib::getByMember($eStructure);

		$contributions = [];

		// Les nouvelles ne sont pas une pièce de l'accueil : elles s'ouvrent depuis le groupe Bureau, qui
		// les lit lui-même (`loadNews()`).
		foreach(array_intersect(DashboardSetting::SLOTS, $slots) as $slot) {

			if($slot === DashboardSetting::NEWS) {
				continue;
			}

			// Un emplacement qui ne se règle pas ne lit aucune préférence : une ligne écrite au nom de son
			// occupant quand il se réglait encore le cacherait sans interrupteur pour le rallumer, comme
			// les nouvelles au L46. Le chiffre de la semaine se réglait jusqu'au L62.
			$preferencesSlot = in_array($slot, DashboardSetting::SLOTS_FIXED, TRUE) ? [] : $preferences;

			foreach(self::getContributions() as $contribution => $declaration) {

				if($declaration['slot'] !== $slot) {
					continue;
				}

				// `deferred: FALSE` : une contribution est légère, et seule une pièce lourde a son emplacement
				// rempli après coup.
				$entry = self::getEntry($eStructure, $contribution, $declaration, preferences: $preferencesSlot, deferred: FALSE);

				if($entry === NULL) {
					continue;
				}

				if($slot === DashboardSetting::QUEUE) {

					// Les deux clés que le socle lit dans la donnée d'un package sont posées ici, une fois :
					// « absente » et « à NULL » rangent la ligne au même endroit, et la comparaison se lit
					// ensuite sans garde.
					$entry['data'] += ['dueAt' => NULL, 'waitingDays' => NULL];

				}

				$contributions[$slot][$contribution] = $entry;

			}

		}

		return self::sortBySlot($contributions);

	}

	/**
	 * L'entrée « Du nouveau » du groupe Bureau paraît-elle ? Elle paraît dès qu'un occupant de
	 * l'emplacement des nouvelles a quelque chose à dire, et sa page relit leur contenu à l'ouverture.
	 */
	public static function hasNews(\structure\Structure $eStructure): bool {
		return (self::loadNews($eStructure) !== []);
	}

	/**
	 * Les occupants de l'emplacement des nouvelles qui ont quelque chose à dire, tels que la page
	 * « Du nouveau » les rend, vide quand aucun n'en a.
	 *
	 * Aucune préférence n'est lue : les nouvelles ne se règlent pas (`DashboardSetting::SLOTS_FIXED`), et
	 * une ligne qu'un membre aurait écrite quand elles se réglaient sur l'accueil ne les cache plus
	 * (arbitré par V-Dashboard le 2026-10-16, L46).
	 *
	 * @return array [nom => ['declaration' => …, 'url' => …, 'data' => …]]
	 */
	public static function loadNews(\structure\Structure $eStructure): array {

		$news = [];

		foreach(self::getContributions() as $contribution => $declaration) {

			if($declaration['slot'] !== DashboardSetting::NEWS) {
				continue;
			}

			$entry = self::getEntry($eStructure, $contribution, $declaration, deferred: FALSE);

			if($entry !== NULL) {
				$news[$contribution] = $entry;
			}

		}

		return $news;

	}

	/**
	 * L'adresse de chaque occupant de l'emplacement des nouvelles, qu'il ait quelque chose à dire ou non :
	 * la page « Du nouveau » garde son chemin vers la source quand elle est vide. `getUrl()` reçoit alors
	 * une donnée `NULL`, ce que son contrat admet.
	 *
	 * @return array [nom => adresse]
	 */
	public static function loadNewsUrls(\structure\Structure $eStructure): array {

		$urls = [];

		foreach(self::getContributions() as $contribution => $declaration) {

			if(
				$declaration['slot'] !== DashboardSetting::NEWS or
				self::isAllowed($eStructure, $declaration) === FALSE
			) {
				continue;
			}

			$urls[$contribution] = DashboardSetting::resolve($contribution)::getUrl($contribution, $eStructure, NULL);

		}

		return $urls;

	}

	/**
	 * Ce que le « Choisir » d'un bloc donne à régler, ou `NULL` quand ce membre n'a rien à y régler :
	 * un nom qu'aucun bloc ne déclare, un bloc qui ne se choisit pas, un bloc que la structure ou le
	 * rôle n'offrent pas. Le package rend lui-même `NULL` quand il n'a pas de ligne où écrire le
	 * choix, un visiteur sans ligne de membre par exemple.
	 *
	 * Le filtre s'arrête à ce qui est offert (`isAllowed()`), comme le panneau de personnalisation : un
	 * bloc éteint par le membre n'a pas de bouton, mais rien n'interdit d'en régler le contenu.
	 */
	public static function loadChoice(\structure\Structure $eStructure, string $block): ?array {

		$blocks = self::getBlocks();

		if(
			array_key_exists($block, $blocks) === FALSE
			or $blocks[$block]['choose'] === FALSE
			or self::isAllowed($eStructure, $blocks[$block]) === FALSE
		) {
			return NULL;
		}

		$class = DashboardSetting::resolve($block);

		return $class::getChoice($block, $eStructure);

	}

	/**
	 * Les blocs d'un seul univers, pour un écran qui n'est pas l'accueil : le tableau de bord d'une AMAP
	 * (`amap/dashboard`), dont la barrière de `DashboardSetting::isEnabled()` refuse l'accueil.
	 *
	 * Mêmes déclarations et même `loadData()` que `load()`, donc même donnée et même cache. Trois écarts,
	 * qui tiennent tous à ce que les routes de l'accueil sont fermées à cette structure :
	 * - aucune préférence n'est lue : le panneau qui les règle est fermé, et un bloc masqué au L5
	 *   resterait masqué sans aucun moyen de le rallumer. Le défaut de la déclaration décide ;
	 * - rien n'est différé, la route d'un bloc lourd étant fermée elle aussi ;
	 * - `isEnabled()` n'est pas lu : c'est la page appelante qui porte le droit d'ouvrir l'écran.
	 */
	public static function loadSection(\structure\Structure $eStructure, string $section): array {

		$blocks = [];

		foreach(self::getBlocks() as $block => $declaration) {

			if($declaration['section'] === $section) {

				$entry = self::getEntry($eStructure, $block, $declaration, deferred: FALSE);

				if($entry !== NULL) {
					$blocks[$block] = $entry;
				}

			}

		}

		return $blocks;

	}

	/**
	 * Un seul bloc, pour la route qui remplit l'emplacement d'un bloc lourd après l'affichage de
	 * l'écran. Elle rend `NULL` pour un bloc que la structure ne voit pas comme pour un bloc qui n'a rien
	 * à dire : la route ne distingue pas ces deux-là, elle retire le nœud. Le nom qu'aucun package ne
	 * déclare se refuse avant, par `hasBlock()`, et rend 404.
	 */
	public static function loadBlock(\structure\Structure $eStructure, string $block, ?string $focus = NULL, bool $setting = FALSE): ?array {

		$blocks = self::getBlocks();

		if(array_key_exists($block, $blocks) === FALSE) {
			return NULL;
		}

		return self::getEntry($eStructure, $block, $blocks[$block], preferences: PreferenceLib::getByMember($eStructure), deferred: FALSE, focus: $focus, setting: $setting);

	}

	/**
	 * Un bloc de l'accueil en réglage, tel qu'un geste vient de le laisser : c'est le nœud que l'action
	 * renvoie, et lui seul. Un bloc lourd rallumé revient en emplacement, que sa route remplit comme au
	 * chargement. Seuls les blocs se règlent, le nom vient d'être reconnu parmi eux (`doUpdateHidden`).
	 */
	public static function loadSetting(\structure\Structure $eStructure, string $name): ?array {
		return self::getEntry($eStructure, $name, self::getBlocks()[$name], preferences: PreferenceLib::getByMember($eStructure), setting: TRUE);
	}

	/** Le nom est-il déclaré par un package ? La question ne regarde aucune structure. */
	public static function hasBlock(string $block): bool {
		return array_key_exists($block, self::getBlocks());
	}

	/**
	 * Ce que le groupe Bureau du menu porte sous ses entrées (L52) : la météo, seule depuis que le
	 * chiffre de la semaine est revenu sous la date (L62).
	 *
	 * @return array [emplacement => [contribution => ['declaration' => …, 'url' => …, 'data' => …]]]
	 */
	public static function loadHome(\structure\Structure $eStructure): array {
		return self::loadContributions($eStructure, slots: [DashboardSetting::WEATHER]);
	}

	/**
	 * Un univers est allumé dès qu'un de ses blocs l'est : il n'a pas de ligne à lui, son état se déduit
	 * de ses blocs (`PreferenceLib::updateSection()`). `$entries` porte les pièces d'un univers, telles
	 * que `loadChoices()` ou `load()` les rendent.
	 */
	public static function isSectionChosen(array $entries): bool {
		return in_array(TRUE, array_column($entries, 'chosen'), TRUE);
	}

	/**
	 * L'état de chaque univers de l'accueil en réglage, depuis les pièces de `load()`.
	 *
	 * @return bool[] [section => allumé]
	 */
	public static function getSectionsChosen(array $blocks): array {

		$sections = [];

		foreach($blocks as $block => $entry) {
			$sections[$entry['declaration']['section']][$block] = $entry;
		}

		return array_map(fn(array $entries) => self::isSectionChosen($entries), $sections);

	}

	/**
	 * Ce que l'écran de personnalisation a à montrer : tous les blocs offerts à ce membre dans cette
	 * structure, chacun avec l'état de son interrupteur, rangés comme l'accueil les range.
	 *
	 * Ce n'est pas `load()` : aucune donnée n'est lue, donc aucune requête de carte n'est payée pour un
	 * écran qui ne montre que des noms et des interrupteurs. Et le filtre s'arrête à ce qui est
	 * **offert** (`isAllowed()`) : un bloc éteint doit paraître, son interrupteur sur « Non » étant
	 * précisément ce que le membre vient rallumer. Les contributions n'y sont pas : aucune ne porte
	 * d'interrupteur depuis que le chiffre de la semaine ne se règle plus (L62).
	 *
	 * Les sections vides sont absentes, comme les emplacements vides de `loadContributions()` : l'écran
	 * sait alors qu'il n'a pas d'univers à dessiner, sans compter lui-même.
	 *
	 * @return array ['sections' => [section => [nom => ['declaration' => …, 'chosen' => bool]]]]
	 */
	public static function loadChoices(\structure\Structure $eStructure): array {

		$preferences = PreferenceLib::getByMember($eStructure);

		$sections = [];

		foreach(self::sortBySection(self::getBlocks(), $preferences) as $block => $declaration) {

			if(self::isAllowed($eStructure, $declaration) === FALSE) {
				continue;
			}

			$sections[$declaration['section']][$block] = [
				'declaration' => $declaration,
				'chosen' => self::isChosen($eStructure, $block, $declaration, $preferences)
			];

		}

		return [
			'sections' => $sections
		];

	}

	/**
	 * Une pièce, bloc ou contribution, telle que l'écran la reçoit, ou `NULL` si elle n'y paraît pas.
	 *
	 * Les deux formes passent ici : le filtre, l'appel au package, le refus du vide et le contrôle des
	 * scalaires ne dépendent pas de l'endroit où la pièce se range.
	 *
	 * `data` à `NULL` dit que le contenu n'est pas là : c'est le cas d'un bloc lourd, dont le socle
	 * rend un emplacement que sa propre route remplit. Une pièce légère dont le package rend `NULL` ou
	 * un tableau vide disparaît, « jamais de tuile à zéro » étant une règle de l'écran : le tableau
	 * vide est accepté ici pour que ce défaut-là reste sans conséquence.
	 */
	private static function getEntry(\structure\Structure $eStructure, string $name, array $declaration, array $preferences = [], bool $deferred = TRUE, ?string $focus = NULL, bool $setting = FALSE): ?array {

		if(self::isAllowed($eStructure, $declaration) === FALSE) {
			return NULL;
		}

		$chosen = self::isChosen($eStructure, $name, $declaration, $preferences);

		// En réglage, une pièce éteinte garde sa place, réduite à son titre, et sa donnée n'est pas lue :
		// elle ne paraîtra pas sur l'accueil, et le réglage ne paie pas ce qu'on a choisi de ne pas voir.
		if($chosen === FALSE) {
			return $setting ? self::getFrame($declaration, FALSE) : NULL;
		}

		$class = DashboardSetting::resolve($name);

		if($deferred and $declaration['cost'] === DashboardSetting::HEAVY) {

			$data = NULL;

		} else {

			$data = self::loadData($class, $name, $eStructure, $declaration['cache'], $focus);

			// Une pièce qui n'a rien à dire cette semaine disparaît de l'accueil. En réglage, elle reste
			// à son titre, sans quoi on ne pourrait pas l'éteindre avant qu'elle parle.
			if($data === NULL or $data === []) {
				return $setting ? self::getFrame($declaration, TRUE) : NULL;
			}

			if(LIME_ENV === 'dev') {
				self::assertScalars($name, $data);
			}

		}

		return [
			'declaration' => $declaration,
			'url' => $class::getUrl($name, $eStructure, $data),
			'data' => $data,
			'chosen' => TRUE,
			'frame' => FALSE
		];

	}

	/**
	 * Le cadre d'une pièce sans contenu, que seul le réglage montre : son titre, son interrupteur, et
	 * rien à quoi mènerait un pied. `frame` le distingue de l'emplacement d'un bloc lourd, qui n'a pas
	 * encore de donnée mais en aura une.
	 */
	private static function getFrame(array $declaration, bool $chosen): array {

		return [
			'declaration' => $declaration,
			'url' => NULL,
			'data' => NULL,
			'chosen' => $chosen,
			'frame' => TRUE
		];

	}

	/**
	 * La donnée d'une pièce, lue au cache quand sa déclaration en demande un.
	 *
	 * Le `NULL` de « rien à dire » est cachable comme une donnée : `Cache::query()` prend `FALSE` pour un
	 * échec de lecture, et une valeur nulle rendue telle quelle ne se distinguerait plus d'une clé
	 * jamais posée, si bien que la requête repartirait à chaque accueil pour rien. La donnée voyage donc
	 * dans une enveloppe.
	 *
	 * La clé vient de `getCacheKey()`, qui est le seul endroit du dépôt à en connaître le format.
	 */
	private static function loadData(string $class, string $name, \structure\Structure $eStructure, int $cache, ?string $focus): ?array {

		if($cache === 0) {
			return $class::load($name, $eStructure, $focus);
		}

		$key = self::getCacheKey($name, $eStructure, \user\ConnectionLib::getOnline(), $focus);

		return \Cache::redis()->query($key, fn() => ['data' => $class::load($name, $eStructure, $focus)], $cache)['data'];

	}

	/**
	 * La clé sous laquelle la donnée d'une pièce se garde, pour le lecteur comme pour qui l'oublie.
	 *
	 * Publique parce qu'un tiers doit pouvoir effacer ce qu'il vient de contredire : le cadriciel
	 * n'efface pas par motif, donc un script qui écrit sous le cache doit reconstruire chaque clé. Le
	 * format recopié à la main ailleurs cesserait d'oublier sans un mot le jour où il change ici, et un
	 * compte de zéro clé retirée se lit comme « rien à retirer » (cas vu le 2026-09-29 sur les cellules
	 * d'accueil, commit `0d3c535cb`).
	 *
	 * L'utilisateur se prend en argument et ne se lit pas dans la session : celui qui oublie une clé
	 * tourne en CLI, où il n'y a personne en ligne, et il connaît le sien.
	 *
	 * La clé porte l'utilisateur tant que `scope` n'existe pas : le jour où il arrivera, il élargira un
	 * partage, décision par décision, jamais l'inverse.
	 */
	public static function getCacheKey(string $name, \structure\Structure $eStructure, \user\User $eUser, ?string $focus = NULL): string {

		$key = 'dashboard-v'.self::CACHE_VERSION.'-'.$eStructure['id'].'-'.$name.'-'.$eUser['id'];

		// Une pièce qui se cache et qui se centre sur quelque chose garde une clé par centrage : la même
		// clé pour deux centrages servirait le premier lu au second demandé. Aucune pièce n'est
		// aujourd'hui les deux à la fois, et c'est le jour où l'une le sera que l'oubli se paierait.
		return ($focus === NULL) ? $key : $key.'-'.$focus;

	}

	/**
	 * Ce que cette structure et ce rôle offrent, sans regarder ce que le membre en a réglé.
	 *
	 * C'est la question que pose l'écran de personnalisation : il montre les interrupteurs de tout ce
	 * qui est offert, y compris ceux qui sont sur « Non ».
	 */
	private static function isAllowed(\structure\Structure $eStructure, array $declaration): bool {

		if(
			$declaration['feature'] !== NULL
			and (bool)$eStructure->conf(\structure\Feature::class)[$declaration['feature']] === FALSE
		) {
			return FALSE;
		}

		foreach((array)$declaration['right'] as $right) {

			if($eStructure->{$right}() === FALSE) {
				return FALSE;
			}

		}

		foreach($declaration['accept'] as $accept) {

			if($eStructure->{$accept}() === FALSE) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Ce que le membre a choisi d'en voir : sa ligne de préférence si elle existe, le défaut de la
	 * déclaration sinon.
	 *
	 * Une ligne absente n'est pas un trou à combler : c'est l'état d'une pièce que le membre n'a jamais
	 * touchée, et le défaut est alors la seule réponse juste. C'est ce qui fait qu'un bloc ajouté demain
	 * paraît chez tous ceux qui n'ont rien réglé, au lieu de rester éteint pour tout le parc.
	 */
	private static function isChosen(\structure\Structure $eStructure, string $name, array $declaration, array $preferences): bool {

		if(array_key_exists($name, $preferences)) {
			return ($preferences[$name]['hidden'] === FALSE);
		}

		$default = $declaration['default'];

		if($default instanceof \Closure) {
			return $default($eStructure);
		}

		return $default;

	}

	/**
	 * Les blocs rangés par `(rang de l'univers, position réglée, position déclarée, ordre de déclaration)`,
	 * le rang de l'univers étant celui que donne `sortSections()`.
	 *
	 * La `position` est relative à sa section : l'utilisateur range les blocs d'un univers entre eux et
	 * ne mêle pas deux univers (arbitré le 2026-09-28). La colonne d'un bloc n'entre pas dans
	 * ce rangement : l'écran la lit après lui, et deux blocs d'une même colonne s'y suivent dans l'ordre
	 * rendu ici. Un bloc jamais
	 * rangé porte `NULL` et passe donc en fin de **sa** section, ce qui met un bloc ajouté demain après
	 * ceux que le membre a déjà ordonnés plutôt qu'au milieu.
	 *
	 * ⚠️ `uksort` et non `uasort` : le rang réglé se lit par le **nom** de la pièce, que seul `uksort`
	 * passe au comparateur. Il est stable depuis PHP 8.0, donc deux blocs de rang égal, et notamment
	 * les deux `NULL` d'une section jamais rangée, gardent l'ordre où les packages les ont déclarés :
	 * c'est ce qui rend ce rangement identique au précédent tant que le membre n'a rien touché.
	 *
	 * La position déclarée départage avant l'ordre des packages, qui suit `\Package::getList()` et non
	 * la lecture de la bande : `planning` y passe avant `series`, et ses blocs précéderaient sinon la
	 * carte du planning qu'ils prolongent.
	 */
	private static function sortBySection(array $blocks, array $preferences): array {

		$sorted = [];

		foreach(self::sortSections($preferences) as $section) {

			$entries = [];

			foreach($blocks as $block => $declaration) {

				if($declaration['section'] === $section) {
					$entries[$block] = $declaration;
				}

			}

			uksort($entries, fn(string $first, string $second): int => self::compareEmptyLast(
				self::getPosition($preferences, $first),
				self::getPosition($preferences, $second),
				self::ASCENDING
			) ?: $entries[$first]['position'] <=> $entries[$second]['position']);

			$sorted += $entries;

		}

		return $sorted;

	}

	/**
	 * Les univers dans l'ordre que le membre leur a donné, ceux qu'il n'a jamais rangés après, dans
	 * l'ordre de `DashboardSetting::SECTIONS` (L39, arbitré par V-Dashboard le 2026-10-12).
	 *
	 * Le rang d'un univers est la `position` de la ligne qui porte son nom. `NULL` le range en fin, comme
	 * un bloc jamais rangé : un univers qu'un droit ou une fonctionnalité ouvre demain arrive après ceux
	 * que le membre a ordonnés, et ne s'intercale pas. `usort` est stable depuis PHP 8.0, donc deux
	 * univers sans rang gardent l'ordre des onglets, et un membre qui n'a rien rangé voit l'accueil
	 * d'avant le lot.
	 *
	 * Le rail et la barre du téléphone ne lisent pas ce rang : la navigation garde l'ordre des onglets,
	 * que la main retrouve au même endroit sur chaque écran (arbitré par V-Dashboard le 2026-10-12).
	 *
	 * @return string[]
	 */
	private static function sortSections(array $preferences): array {

		$sections = DashboardSetting::SECTIONS;

		usort($sections, fn(string $first, string $second): int => self::compareEmptyLast(
			self::getPosition($preferences, $first),
			self::getPosition($preferences, $second),
			self::ASCENDING
		));

		return $sections;

	}

	/**
	 * Les emplacements dans l'ordre de l'écran, chacun dans l'ordre de ses occupants.
	 *
	 * ⚠️ `uasort` est stable depuis PHP 8.0 : deux contributions que rien ne sépare gardent l'ordre où
	 * les packages les ont déclarées, donc celui de `\Package::getList()`. C'est ce qui rend l'ordre
	 * déterminé sans qu'un `position` ait à être unique, et sans qu'un package ait à connaître les
	 * autres.
	 *
	 * Aucun emplacement ne lit la préférence du membre pour se ranger : aucune poignée ne vient sur la
	 * tête ni sur la file, donc aucune `position` n'y est jamais écrite. Le rang est celui que le
	 * package a posé.
	 */
	private static function sortBySlot(array $contributions): array {

		$sorted = [];

		foreach(DashboardSetting::SLOTS as $slot) {

			if(array_key_exists($slot, $contributions) === FALSE) {
				continue;
			}

			$entries = $contributions[$slot];

			// La file se range par ce qu'elle attend, les autres emplacements par le rang que leur
			// package a posé : la file se lit par urgence.
			if($slot === DashboardSetting::QUEUE) {
				uasort($entries, fn(array $first, array $second): int => self::compareQueue($first, $second));
			} else {
				uasort($entries, fn(array $first, array $second): int => $first['declaration']['position'] <=> $second['declaration']['position']);
			}

			$sorted[$slot] = $entries;

		}

		return $sorted;

	}

	/**
	 * Deux lignes de la file : l'échéance d'abord, la plus proche en tête, puis l'ancienneté d'attente,
	 * la plus longue en tête, puis le rang que le package a posé. Une ligne sans échéance passe après
	 * toutes celles qui en portent une, et jamais l'univers ne range quoi que ce soit.
	 *
	 * `dueAt` et `waitingDays` sont les deux seules clés que le socle lise dans la donnée d'un package,
	 * et elles ne nomment personne : c'est le contrat de l'emplacement, écrit dans le `CLAUDE.md` du
	 * package.
	 */
	private static function compareQueue(array $first, array $second): int {

		$dueAt = self::compareEmptyLast($first['data']['dueAt'], $second['data']['dueAt'], self::ASCENDING);

		if($dueAt !== 0) {
			return $dueAt;
		}

		$waitingDays = self::compareEmptyLast($first['data']['waitingDays'], $second['data']['waitingDays'], self::DESCENDING);

		if($waitingDays !== 0) {
			return $waitingDays;
		}

		return $first['declaration']['position'] <=> $second['declaration']['position'];

	}

	/**
	 * Le rang qu'un membre a donné à une pièce, ou `NULL` quand il ne l'a jamais rangée.
	 *
	 * Une ligne absente de la carte n'est pas une donnée manquante : c'est l'état d'une pièce que
	 * personne n'a touchée, et `NULL` est la réponse juste, celle que `compareEmptyLast()` range en fin
	 * de section. La colonne elle-même est nullable pour dire exactement la même chose d'une pièce dont
	 * le membre a réglé l'interrupteur sans jamais toucher à l'ordre.
	 */
	private static function getPosition(array $preferences, string $name): ?int {

		if(array_key_exists($name, $preferences) === FALSE) {
			return NULL;
		}

		return $preferences[$name]['position'];

	}

	/**
	 * Deux valeurs dont l'absence range en dernier, quel que soit le sens de la comparaison : `NULL` ne
	 * vaut ni plus petit ni plus grand, il vaut « on ne sait pas », et ce qu'on ne sait pas ne passe
	 * devant rien.
	 */
	private static function compareEmptyLast(null|string|int $first, null|string|int $second, int $direction): int {

		if($first === NULL and $second === NULL) {
			return 0;
		}

		if($first === NULL) {
			return 1;
		}

		if($second === NULL) {
			return -1;
		}

		return $direction * ($first <=> $second);

	}

	/**
	 * Ce qu'une pièce rend part au cache et, un jour, à l'API : un objet y perdrait son sens à la
	 * sérialisation. Le contrôle ne tourne qu'en dev, où l'auteur de la pièce travaille ; en production
	 * il ne coûte rien, personne ne l'appelle.
	 */
	private static function assertScalars(string $name, array $data): void {

		foreach($data as $value) {

			if(is_object($value)) {
				throw new \Exception($name.' returns an object, when a block returns scalars and arrays only');
			}

			if(is_array($value)) {
				self::assertScalars($name, $value);
			}

		}

	}

}
?>
