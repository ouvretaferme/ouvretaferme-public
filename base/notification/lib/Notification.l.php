<?php
namespace notification;

/**
 * Les notifications d'un membre : leur émission, leur lecture et leur nettoyage.
 *
 * Une notification s'écrit dans la base de la structure qui la reçoit, une ligne par membre
 * concerné. Le compteur vit à côté de l'adhésion, en base commune
 * (`structure\Member.notificationsUnseen`), et c'est `structure\MemberLib` qui l'écrit : ce Lib ne
 * fait que lui dire quoi ajouter ou recompter.
 *
 * ⚠️ **Ce compteur compte les non `seenAt`, pas les non `readAt`** : la cloche dit « arrivé depuis
 * ta dernière visite », pas « reste à traiter » (direction C de la planche du 2026-09-21). Il
 * s'appelait `notificationsUnread` jusqu'au 2026-09-21 (`migration/2026-09-21-notifications-compteur-non-vues.md`).
 *
 * Une notification traverse donc trois états, et `seenAt` précède toujours `readAt` : jamais vue
 * (elle compte), vue à l'ouverture du centre ou de la cloche (`see()`, elle ne compte plus), puis
 * cliquée (`read()`). L'invariant {seenAt IS NULL} ⊆ {readAt IS NULL} est ce qui rend juste le choix
 * des utilisateurs à recaler dans `clean()`.
 *
 * ⚠️ Toute lecture ou écriture d'une notification passe par `on()`, qui ne bascule que ce package
 * sur la base visée. `StructureLib::connectDatabase()` rebrancherait aussi la comptabilité et la
 * structure connectée, que l'appelant lit peut-être encore : une commande passée sur une boutique
 * partagée, ou un script qui parcourt le parc.
 *
 * Les notifications sont segmentées par structure (décidé le 2026-09-20) : la cloche, le
 * centre et le compteur d'une structure ne lisent que sa base. Celles d'un groupe se lisent depuis
 * le groupe, jamais depuis les structures qui y sont rattachées.
 *
 * **Une source commune** s'ajoute aux notifications de la base de la structure : des lignes qui
 * vivent en base commune, qu'un package écrit et lit lui-même, et que le moteur fusionne à la
 * lecture sans connaître leur package (`getSources()`). Les notes de l'équipe Ouvretaferme en sont
 * une. Une source commune n'est pas un type : ni droit, ni fonctionnalité, ni réglage, ni file ; elle
 * n'entre pas dans `getTypes()`. Le moteur lui demande, par des méthodes au nom fixe de son
 * `NotificationLib` :
 * - `getCommon(Structure, User)` : ses lignes de moins de trente jours dans cette structure, en
 *   éléments `Notification`, `id` compris ;
 * - `getCommonById(Structure, User, int)` : l'une d'elles, pour le clic ;
 * - `countCommonUnseen(Structure, array)` : ses non vues par utilisateur, dans cette structure ;
 * - `seeCommon(Structure, User)`, `readCommon(Structure, User, Notification)` : marquent vues ou lues,
 *   et rendent les structures dont le compteur a pu bouger, une ligne vue dans une structure
 *   pouvant l'être aussi dans les autres ;
 * - `cleanCommon()` : efface ce qui a passé trente jours et rend, par structure, les utilisateurs
 *   dont le compteur a pu bouger.
 * Chaque ligne fusionnée porte `source`, le nom du package, que le clic renvoie ; celles de la base de
 * la structure portent `source` à NULL.
 */
class NotificationLib extends NotificationCrud {

	private static array $databaseByStructure = [];

	private static ?array $sources = NULL;

	/** Les sections que StructureTemplate sait poser, dans le vocabulaire de Structure::assertSection() */
	const SECTIONS = ['production', 'amap', 'commercialisation', 'accounting', 'settings'];

	/**
	 * La section sous laquelle le centre s'affiche : celle d'où l'on vient, que le lien porte
	 * (« c'est une page caméléon »). Une section absente, inconnue ou que l'utilisateur
	 * n'ouvre pas (lien ancien, accès direct, droits changés depuis) retombe sur la première section
	 * par défaut. assertSection() n'est appelée que sur une valeur de SECTIONS : son switch n'a pas de
	 * défaut et lèverait sur une autre chaîne.
	 *
	 * La retombée est getDefaultSection() et non getAccessSection(), qui rend 'dashboard' sous drapeau.
	 * Cette chaîne est hors de SECTIONS, donc assertSection() lèverait dessus et l'écran rendrait 500.
	 */
	public static function getSection(\structure\Structure $eStructure, ?string $section): ?string {

		if(
			in_array($section, self::SECTIONS, TRUE) and
			$eStructure->assertSection($section)
		) {
			return $section;
		}

		return $eStructure->getDefaultSection();

	}

	/** Le nom de la famille de tâches d'un type, tel que la crontab le nomme et que le runner le lit. */
	public static function getAction(string $type): string {
		return 'notification.'.$type;
	}

	/**
	 * Tous les types que le produit sait notifier, et ce que chacun suppose (son droit, sa
	 * fonctionnalité, sa famille).
	 *
	 * L'inventaire se dérive des packages et de rien d'autre : un package qui émet des notifications
	 * apporte une paire `NotificationLib` + `NotificationUi`, et c'est son `getTypes()` qui fait foi.
	 * Aucune table du moteur ne double cette liste, sans quoi ajouter un type demanderait d'ouvrir un
	 * fichier que son auteur n'a aucune raison de connaître. Le contrat de la paire est écrit dans
	 * `base/notification/CLAUDE.md` et tenu par `test/NotificationTypeSentinel.t.php`.
	 *
	 * Chaque type déclare aussi les valeurs que son réglage accepte (`values`, `OFF` toujours présent)
	 * et celle d'un membre qui n'a rien choisi (`default`). Le défaut n'est écrit nulle part ailleurs :
	 * le changer vaut pour tous ceux qui n'ont rien choisi, sans reprise. Il déclare enfin si un même
	 * objet ne se notifie qu'une fois (`once`), ce que `treat()` lit pour sa déduplication.
	 *
	 * Six clés sont facultatives, et leur défaut se pose une fois pour tous les packages
	 * (`getDefaults()`) :
	 * - `feature` (NULL) : la fonctionnalité de structure qui ouvre le type (`Structure::conf()`).
	 *   NULL ne pose aucune condition de fonctionnalité, et ne se déclare que sur un type obligatoire
	 *   (`NotificationTypeSentinel`) : l'échéance d'un abonnement concerne toute structure qui paie,
	 *   quelles que soient les fonctionnalités qu'elle a ouvertes ;
	 * - `mandatory` (FALSE) : le type ne se coupe pas. Son réglage montre « Toujours » et ne se
	 *   poste pas, et `getValue()` rend ON quelle que soit la valeur stockée. Rien d'autre ne
	 *   change : le droit passe avant, et un membre hors du droit ne le reçoit toujours pas ;
	 * - `rightValues` ([TRUE]) : les valeurs du droit qui ouvrent le type. Un droit booléen n'en a
	 *   qu'une ; un droit à plusieurs niveaux (`Role.accounting`) nomme ceux qu'il accepte. La règle
	 *   est lue par `hasRight()` et par elle seule ;
	 * - `structureTypes` (tous les types de `Structure.type`) : les structures qui peuvent recevoir le
	 *   type. Une ferme n'a pas de fermes rattachées, un groupe n'est retiré d'aucun groupe : sans
	 *   cette borne, leurs réglages proposeraient un interrupteur qui ne commande rien. Elle se lit
	 *   avec la fonctionnalité, par `isReceivable()` ;
	 * - `pilotage` (TRUE) : le type suppose la souscription de la structure à l'offre Pilotage
	 *   (`Structure::hasPilotage()`). FALSE ouvre le type aux structures sans l'offre, et
	 *   c'est la forme des notifications de l'équipe OTF (décidé le 22/09). La garde se
	 *   pose à l'émission, au traitement par `isReceivable()`, et à la lecture du centre et de la
	 *   cloche (`getByStructure()`) ;
	 * - `receivable` (NULL) : un callable `(Structure) => bool` que le package qui émet tient, pour
	 *   un type qui ne concerne que quelques structures sans qu'aucune fonctionnalité ne les désigne
	 *   (le compte de reversement, ouvert aux seules structures pour qui Ouvretaferme encaisse). Il
	 *   se lit par `isReceivable()` avec les autres bornes, et s'écrit en tableau `[classe, méthode]`
	 *   plutôt qu'en closure : deux lectures de `getTypes()` doivent rendre la même déclaration.
	 *
	 * @return array [type => ['right' => …, 'rightValues' => […], 'feature' => …, 'structureTypes' => […], 'family' => …, 'values' => […], 'default' => …, 'once' => bool, 'mandatory' => bool, 'pilotage' => bool]]
	 */
	public static function getTypes(): array {

		$types = [];

		foreach(\Package::getList() as $package => $app) {

			// Le package du moteur porte ce Lib-ci et n'émet aucun type : sans cette sortie, le
			// balayage s'appellerait lui-même.
			if($package === __NAMESPACE__) {
				continue;
			}

			$class = '\\'.$package.'\\NotificationLib';

			if(class_exists($class) === FALSE) {
				continue;
			}

			foreach($class::getTypes() as $type => $declaration) {
				$types[$type] = $declaration + self::getDefaults();
			}

		}

		return $types;

	}

	/**
	 * La déclaration d'un seul type, défauts compris, lue dans le seul package qui l'émet.
	 *
	 * Elle sert les chemins qui ne portent qu'un type (l'émission sous le geste, le traitement d'une
	 * tâche) : balayer tous les packages pour en lire un coûterait au geste de l'utilisateur. Elle
	 * passe par `NotificationSetting::resolve()`, comme `describe()`, donc un test qui substitue le
	 * package du type substitue aussi sa déclaration. `getRecipients()` et `getValue()` lisent
	 * `getTypes()` : sous un double, ils voient la déclaration du vrai package.
	 */
	public static function getType(string $type): array {
		return NotificationSetting::resolve($type)::getTypes()[$type] + self::getDefaults();
	}

	/**
	 * Les packages qui portent une source commune, reconnus comme les types à leur `NotificationLib`,
	 * et à la présence de `getCommon()`. Mémoïsés sans égard à la structure connectée : c'est la liste
	 * des packages, pas une donnée d'une structure.
	 */
	public static function getSources(): array {

		if(self::$sources !== NULL) {
			return self::$sources;
		}

		self::$sources = [];

		foreach(\Package::getList() as $package => $app) {

			if($package === __NAMESPACE__) {
				continue;
			}

			if(method_exists('\\'.$package.'\\NotificationLib', 'getCommon')) {
				self::$sources[] = $package;
			}

		}

		return self::$sources;

	}

	private static function getSourceLib(string $source): string {
		return '\\'.$source.'\\NotificationLib';
	}

	private static function getDefaults(): array {

		return [
			'feature' => NULL,
			'mandatory' => FALSE,
			'rightValues' => [TRUE],
			'structureTypes' => \structure\Structure::model()->getPropertyEnum('type'),
			'pilotage' => TRUE,
			'communityTypes' => \structure\Structure::model()->getPropertyEnum('communityType'),
			'receivable' => NULL
		];

	}

	/**
	 * Borne une lecture aux types qu'une structure sans l'offre Pilotage voit encore : ceux qui
	 * déclarent `pilotage` à FALSE. Avec l'offre, rien n'est borné.
	 *
	 * ⚠️ Sans l'offre, la liste peut être vide (aucun type de l'équipe OTF déclaré), et un `IN` vide
	 * ne rend aucune ligne (`whereBuild()`) : c'est le résultat voulu, et non un `if:` oublié.
	 */
	private static function applyPilotage(NotificationModel $mNotification, \structure\Structure $eStructure): NotificationModel {

		if($eStructure->hasPilotage()) {
			return $mNotification;
		}

		$types = array_keys(array_filter(self::getTypes(), fn(array $declaration) => $declaration['pilotage'] === FALSE));

		return $mNotification->whereType('IN', $types);

	}

	/**
	 * Met en file la notification d'un geste. C'est tout ce que le geste paie.
	 *
	 * ⚠️ Le `try/catch` reste, et il est à sa place ici : l'émission tourne dans la transaction du
	 * geste (`shop\SaleLib::createForShop()` appelle l'observateur entre son `beginTransaction()` et
	 * son `commit()`) et `Package::notify()` n'attrape que `ObserverOutput`.
	 * Une notification perdue ne vaut pas une commande refusée. Il est étroit : il ne couvre que la
	 * lecture du drapeau et la mise en file, tout le reste se passe sous le cron, où un échec est un
	 * essai de plus.
	 *
	 * La tâche étant insérée dans la transaction du geste, une vente annulée emporte sa tâche, et le
	 * cron ne peut pas lire une ligne non validée.
	 *
	 * @param ?int $reference L'objet que la notification désigne (la vente) : il dédoublonne la
	 * notification au traitement, et la demande dans la file, deux gestes sur la même vente ne
	 * faisant qu'une tâche.
	 */
	public static function emit(\structure\Structure $eStructure, string $type, ?int $reference): void {

		try {

			if(NotificationSetting::isEnabled($eStructure) === FALSE) {
				return;
			}

			// Seule garde de type sous le geste : le reste de `isReceivable()` attend le traitement,
			// qui la refait, pour ne pas faire payer à l'utilisateur la lecture de ses fonctionnalités.
			if(
				self::getType($type)['pilotage'] !== FALSE and
				$eStructure->hasPilotage() === FALSE
			) {
				return;
			}

			// L'auteur voyage dans la charge de la tâche et non dans une colonne de la file :
			// `framework/queue` ne connaît aucun métier, et sous le cron `ConnectionLib::getOnline()`
			// ne rend plus personne. Une reprise réécrit la charge, donc c'est le dernier geste qui
			// compte, comme pour tout le reste de la demande.
			$eUserAuthor = \user\ConnectionLib::getOnline();

			\queue\QueueLib::push($eStructure['id'], self::getAction($type), $reference, [
				'author' => $eUserAuthor->empty() ? NULL : $eUserAuthor['id']
			]);

		} catch(\Throwable $e) {

			trigger_error('Notification '.$type.' non mise en file pour la structure #'.$eStructure['id'].' : '.$e->getMessage(), E_USER_WARNING);

		}

	}

	/**
	 * Traite une tâche de la file : les destinataires, la déduplication, l'écriture et le compteur.
	 *
	 * Tourne sous le cron, hors de tout geste : ce qui lève ici remonte au runner, qui reporte la
	 * tâche et la retentera. Rien n'est donc attrapé, à l'inverse du chemin chaud.
	 *
	 * ⚠️ La file ne lit rien de ce que cette méthode rend : revenir est un succès, et la tâche
	 * s'efface (`QueueLib::run()`). Les sept sorties qui suivent disent donc deux choses
	 * différentes, et la distinction est tout le sujet. Une structure supprimée, sortie du drapeau,
	 * privée de la fonctionnalité, sans destinataire, une notification déjà reçue ou un objet
	 * disparu sont des « rien à faire » définitifs : on revient, la demande s'efface. Une base qui
	 * n'est pas encore à jour est un « pas encore » : on lève `TaskPostponed`, et la file la
	 * represente.
	 */
	public static function treat(\queue\Task $eTask): void {

		// La file ne porte qu'un id : `framework/queue` ne connaît pas `structure`, et c'est au
		// consommateur de relire la sienne.
		$eStructure = \structure\StructureLib::getById($eTask['reference']);

		if($eStructure->empty()) {
			return;
		}

		$type = substr($eTask['action'], strlen(self::getAction('')));
		$reference = $eTask['element'];
		$authorId = $eTask['payload']['author'];

		if(NotificationSetting::isEnabled($eStructure) === FALSE) {
			return;
		}

		// La fonctionnalité que le type suppose a été coupée depuis le geste : l'écran des réglages
		// ne montre plus ce type, donc le membre n'a plus aucun moyen de refuser ce qu'on lui
		// écrirait. `getSettings()` l'écarte de la même façon, et pour la même raison.
		$declaration = self::getType($type);

		if(self::isReceivable($eStructure, $declaration) === FALSE) {
			return;
		}

		if(self::hasTable($eStructure) === FALSE) {
			throw new \queue\TaskPostponed('la base de la structure #'.$eStructure['id'].' ne porte pas encore la table des notifications');
		}

		$cMember = self::getRecipients($eStructure, $type, $authorId === NULL ? new \user\User() : new \user\User(['id' => $authorId]));

		if($cMember->empty()) {
			return;
		}

		// Une notification par objet, par type et par destinataire : une commande réglée sur place
		// puis payée en ligne passe par les deux événements que la notification suit, et ne se reçoit
		// qu'une fois. Le retranchement se fait destinataire par destinataire, et non par un test
		// d'existence sur l'ensemble : un membre arrivé dans la structure entre les deux événements ne
		// porte aucune ligne, et une sortie globale le laisserait sans rien pour toujours, l'objet ne
		// repassant pas. Tout ce bloc suppose un objet : une demande sans référence ne peut ni se
		// reconnaître ni se recaler, donc un second passage lui réécrirait ses lignes et compterait
		// une seconde fois. Un type qui émettrait sans objet doit porter sa propre reconnaissance.
		//
		// Un type qui notifie chaque occurrence (`once` à FALSE, une commande modifiée) ne se
		// retranche que de ce que cette tâche-ci a déjà écrit : depuis sa création et non depuis
		// trente jours. La reprise d'une tâche interrompue garde ainsi son idempotence, puisqu'une
		// tâche ravivée garde sa date de création, et une tâche traitée s'efface, donc l'occurrence
		// suivante en crée une neuve.
		if($reference !== NULL) {

			// Au sens large pour la tâche : la ligne qu'elle a écrite peut porter la même seconde qu'elle.
			$since = $declaration['once'] ? ['>', self::getSince()] : ['>=', $eTask['createdAt']];

			$userIdsNotified = self::on($eStructure, fn() => Notification::model()
				->select(['id', 'user'])
				->whereUser('IN', $cMember->getColumnCollection('user'))
				->whereCreatedAt(...$since)
				->whereType($type)
				->whereReference($reference)
				->getCollection()
				->getColumnCollection('user')
				->getIds());

			if($userIdsNotified !== []) {

				// Le compteur de ceux qui ont déjà leur ligne est recalé avant de les écarter. La
				// notification s'écrit dans la base de la structure et le compteur dans la base
				// commune : deux bases, qu'aucune transaction ne couvre. Un runner qui meurt entre les
				// deux laisse une notification visible au centre et une cloche éteinte, et plus rien
				// ne la rattrape — la reprise les retranche justement parce qu'ils sont servis. Le
				// recalage ne coûte qu'à eux, c'est-à-dire au chemin rare : le cas courant garde son
				// écriture de masse, qui ne lit rien.
				\structure\MemberLib::synchronizeNotificationsUnseen($eStructure, self::countUnseen($eStructure, $userIdsNotified));

				$cMember = $cMember->find(fn(\structure\Member $eMember) => in_array($eMember['user']['id'], $userIdsNotified, TRUE) === FALSE);

				if($cMember->empty()) {
					return;
				}

			}

		}

		$classLib = NotificationSetting::resolve($type);

		$eNotification = $classLib::describe($type, $eStructure, $reference);

		// L'objet a disparu entre le geste et le traitement : la demande n'a plus d'objet, et ce
		// n'est pas un échec.
		if($eNotification->empty()) {
			return;
		}

		// Une valeur à choix ne se tranche qu'une fois l'objet relu (une commande de particulier ou de
		// professionnel) : c'est ici, et non dans `getRecipients()`, que le package répond. La
		// déduplication a déjà tourné sur l'ensemble, ce qui ne change rien : un membre écarté ici
		// n'a pas de ligne à recaler.
		$cMember = $cMember->find(fn(\structure\Member $eMember) => self::isWanted($classLib, self::getValue($eMember, $type), $eNotification));

		if($cMember->empty()) {
			return;
		}

		self::on($eStructure, function() use($cMember, $type, $reference, $eNotification) {

			foreach($cMember as $eMember) {

				self::create(new Notification([
					'user' => $eMember['user'],
					'type' => $type,
					'reference' => $reference,
					'group' => $eNotification['group'],
					'content' => $eNotification['content']
				]));

			}

		});

		\structure\MemberLib::addNotificationsUnseen($cMember);

	}

	/**
	 * L'instant au-delà duquel une notification n'existe plus pour personne. La liste, le compteur,
	 * la déduplication et le nettoyage se bornent tous là : s'ils divergeaient, la cloche compterait
	 * ce que la liste ne montre pas. La cloche lit toutefois le compteur stocké sur l'adhésion, qui
	 * n'est recalculé qu'à la lecture suivante ou au nettoyage de 2 h : une non lue qui passe les
	 * trente jours y reste comptée au plus 24 heures, écart accepté.
	 */
	private static function getSince(): \Sql {
		return new \Sql('NOW() - INTERVAL '.NotificationSetting::DAYS.' DAY');
	}

	/**
	 * La valeur du réglage de ce membre pour ce type : celle qu'il a choisie, sinon le défaut que le
	 * type déclare. L'adhésion ne porte que les valeurs qui diffèrent du défaut (`applySetting()`),
	 * l'absence de la clé est donc la réponse « rien choisi » et non une donnée manquante.
	 *
	 * Une valeur stockée que le type ne déclare plus retombe aussi sur le défaut : un package qui
	 * retire une valeur ne reprend pas les adhésions, et son `isWanted()`, sans branche pour elle,
	 * ferait échouer la tâche entière sous le cron, pour tous les membres de la structure.
	 *
	 * Un type obligatoire vaut ON quoi que porte l'adhésion : l'écran ne le laisse pas régler, mais une
	 * valeur posée avant qu'il le devienne, ou par un POST forgé, ne doit pas l'éteindre.
	 */
	public static function getValue(\structure\Member $eMember, string $type): string {

		$declaration = self::getTypes()[$type];

		if($declaration['mandatory']) {
			return NotificationSetting::ON;
		}

		if(
			array_key_exists($type, $eMember['notifications']) and
			in_array($eMember['notifications'][$type], $declaration['values'], TRUE)
		) {
			return $eMember['notifications'][$type];
		}

		return $declaration['default'];

	}

	/**
	 * Ce membre veut-il cette occurrence ? ON la veut toujours, et un type binaire n'a donc rien à
	 * répondre ; toute autre valeur est demandée au package, le seul à savoir ce qu'elle filtre.
	 * OFF n'arrive jamais ici, `getRecipients()` l'a déjà écarté.
	 */
	private static function isWanted(string $classLib, string $value, Notification $eNotification): bool {

		if($value === NotificationSetting::ON) {
			return TRUE;
		}

		return $classLib::isWanted($value, $eNotification);

	}

	/**
	 * Les adhésions actives de la structure qui tiennent le droit que le type exige, dont le réglage
	 * n'est pas OFF, et qui ne sont pas celle de l'auteur du geste.
	 *
	 * Le réglage voyage dans la ligne d'adhésion que cette requête lit déjà : le régler ne coûte donc
	 * aucune requête, ni ici ni sur les pages du produit, où la cloche lit le seul compteur.
	 *
	 * ⚠️ L'auteur est celui que la ligne de file a capté **au geste**, et il arrive donc en argument :
	 * le traitement tourne sous le cron, où `ConnectionLib::getOnline()` rend un utilisateur vide et
	 * n'écarterait plus personne, sans erreur ni trace. Un geste de la ligne de commande ou d'un cron
	 * n'a pas d'auteur non plus, et personne n'est alors écarté. Il est reconnu par son utilisateur et
	 * non par son adhésion, qui n'a de sens que dans une structure.
	 */
	private static function getRecipients(\structure\Structure $eStructure, string $type, \user\User $eUserAuthor = new \user\User()): \Collection {

		$declaration = self::getTypes()[$type];

		return \structure\Member::model()
			->select([
				'id', 'user', 'structure', 'notifications',
				'role' => fn($e) => \structure\RoleLib::ask($e['role'], $e['structure'])
			])
			->whereStructure($eStructure)
			->whereStatus(\structure\Member::IN)
			->whereStructureStatus(\structure\Member::ACTIVE)
			->getCollection()
			->find(fn(\structure\Member $eMember) => (
				$eMember['user']->is($eUserAuthor) === FALSE and
				self::hasRight($eMember, $declaration) and
				self::getValue($eMember, $type) !== NotificationSetting::OFF
			));

	}

	/**
	 * Ce membre tient-il le droit que ce type exige ? Un administrateur toujours (`Member::isRole()`),
	 * sinon l'une des valeurs que le type accepte pour ce droit.
	 *
	 * ⚠️ `isRole()` compare en `===` et vaut TRUE par défaut : sur un droit à niveaux comme
	 * `Role.accounting`, un appel sans valeur rendrait faux pour tout membre qui n'administre pas,
	 * sans erreur. C'est ce que `rightValues` répare, dans le même sens que
	 * `Structure::canObserveAccounting()`.
	 */
	private static function hasRight(\structure\Member $eMember, array $declaration): bool {

		foreach($declaration['rightValues'] as $value) {

			if($eMember->isRole($declaration['right'], $value)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Ce que la structure peut recevoir de ce type : son type de structure, la fonctionnalité, puis
	 * l'offre Pilotage quand le type la suppose, et pour un groupe son type de groupe. L'écran des
	 * réglages et le traitement de la file posent la même question, et doivent y répondre pareil :
	 * un type montré sans être traité ment au membre, un type traité sans être montré lui retire le
	 * moyen de le refuser.
	 *
	 * `communityTypes` (clé facultative, tous les types de `Structure.communityType` par défaut,
	 * `getDefaults()`) borne un type aux groupes qui le vivent : un groupe de producteurs n'a pas de
	 * formations, et sans cette borne ses réglages porteraient un intertitre « Formations » qui ne
	 * commande rien. Une structure qui n'est pas un groupe ne porte pas `communityType`, et la clé
	 * ne se lit donc jamais sur elle : c'est `structureTypes` qui la borne.
	 *
	 * Une `feature` à NULL ne pose aucune condition de fonctionnalité. La règle ne s'écrit qu'ici, pour
	 * que les réglages et le traitement, qui passent tous deux par cette méthode, en restent d'accord.
	 */
	private static function isReceivable(\structure\Structure $eStructure, array $declaration): bool {

		return (
			in_array($eStructure['type'], $declaration['structureTypes'], TRUE) and
			($declaration['feature'] === NULL or $eStructure->conf(\structure\Feature::class)[$declaration['feature']] !== FALSE) and
			($declaration['pilotage'] === FALSE or $eStructure->hasPilotage()) and
			($eStructure->isCommunity() === FALSE or in_array($eStructure['communityType'], $declaration['communityTypes'], TRUE)) and
			($declaration['receivable'] === NULL or call_user_func($declaration['receivable'], $eStructure))
		);

	}

	/**
	 * Ce que ce membre reçoit dans cette structure, type par type, dans l'ordre où l'écran les range.
	 *
	 * Un type dont la structure n'a pas la fonctionnalité est absent : il n'a rien à régler ici. Un
	 * type hors du droit du membre est verrouillé (`locked`) et vaut OFF, puisqu'il ne le reçoit pas :
	 * il reste visible pour que le membre sache ce que le produit notifie. Le masquage passe avant le
	 * verrou, un type sans objet pour la structure n'ayant rien à réserver à personne.
	 *
	 * L'entrée porte la valeur, les valeurs possibles, la famille et le droit : l'écran range par la
	 * famille, dit le verrou par le droit, et la présentation ne va pas chercher sa donnée.
	 *
	 * Un type à choix porte aussi ses portées (`scopes`, ses valeurs hors ON et OFF, vides pour un
	 * type binaire) et celle que l'écran montre (`scope`) : la valeur choisie, ou, le type éteint, celle
	 * que l'interrupteur rallumera. Le moteur ne garde qu'une valeur par type, et OFF n'en retient pas
	 * d'autre : rallumer pose donc toujours la première valeur qui suit OFF (planche A2 du lot 3a).
	 *
	 * ⚠️ Le tri par `FAMILIES` est ce qui tient les intertitres de l'écran, qui n'en émet un que
	 * lorsque la famille change : le balayage des packages ne groupe rien, et deux types d'une même
	 * famille rendus de part et d'autre d'un troisième donneraient deux intertitres identiques. Dans
	 * une famille, `FAMILIES` range les packages ; le tri est stable, et l'ordre du `getTypes()` d'un
	 * package décide donc entre ses propres types.
	 *
	 * Un type obligatoire (`mandatory`) porte sa valeur, ON, et l'écran la dit en toutes lettres à la
	 * place de l'interrupteur. Verrouillé, il reste « Réservé » comme les autres.
	 *
	 * @return array [type => ['value' => …, 'values' => […], 'scopes' => […], 'scope' => …, 'locked' => bool, 'mandatory' => bool, 'family' => …, 'right' => …]]
	 */
	public static function getSettings(\structure\Structure $eStructure, \structure\Member $eMember): array {

		$settings = [];

		foreach(self::getTypes() as $type => $declaration) {

			if(self::isReceivable($eStructure, $declaration) === FALSE) {
				continue;
			}

			$locked = (self::hasRight($eMember, $declaration) === FALSE);
			$value = $locked ? NotificationSetting::OFF : self::getValue($eMember, $type);

			$settings[$type] = [
				'value' => $value,
				'values' => $declaration['values'],
				'scopes' => array_values(array_diff($declaration['values'], [NotificationSetting::ON, NotificationSetting::OFF])),
				'scope' => ($value === NotificationSetting::OFF) ? $declaration['values'][1] : $value,
				'locked' => $locked,
				'mandatory' => $declaration['mandatory'],
				'family' => $declaration['family'],
				'right' => $declaration['right']
			];

		}

		$ranks = [];

		foreach(NotificationSetting::FAMILIES as $family => $packages) {
			foreach($packages as $package) {
				$ranks[$family.'/'.$package] = count($ranks);
			}
		}

		uksort($settings, fn(string $typeA, string $typeB) => $ranks[$settings[$typeA]['family'].'/'.NotificationSetting::getPackage($typeA)] <=> $ranks[$settings[$typeB]['family'].'/'.NotificationSetting::getPackage($typeB)]);

		return $settings;

	}

	/**
	 * Ce membre peut-il donner cette valeur à ce type sur cet écran ? Un type que l'écran n'offre pas,
	 * parce que la structure n'a pas la fonctionnalité ou parce que le membre n'a pas le droit, ne se
	 * règle pas, un type obligatoire non plus, et une valeur que le type ne déclare pas non plus : le
	 * POST a été forgé.
	 *
	 * ⚠️ La règle vit ici et non dans la page pour qu'un test l'atteigne sans jouer la route. Écrite
	 * là-bas, elle lisait la sortie de `getSettings()` sans qu'aucun test ne la touche, et le jour où
	 * cette sortie est passée de l'état seul à l'entrée complète, la comparaison est restée
	 * syntaxiquement valide et définitivement fausse.
	 */
	public static function isSettable(\structure\Structure $eStructure, \structure\Member $eMember, ?string $type, ?string $value): bool {

		$settings = self::getSettings($eStructure, $eMember);

		if($type === NULL or array_key_exists($type, $settings) === FALSE) {
			return FALSE;
		}

		return (
			$settings[$type]['locked'] === FALSE and
			$settings[$type]['mandatory'] === FALSE and
			in_array($value, $settings[$type]['values'], TRUE)
		);

	}

	/**
	 * Les réglages du membre, une fois ce geste appliqué. N'écrit rien : c'est `structure\MemberLib`
	 * qui tient l'adhésion.
	 *
	 * Une valeur égale au défaut retire la clé au lieu de l'écrire : le membre revient à « rien
	 * choisi », et suivra donc le défaut s'il change un jour.
	 */
	public static function applySetting(\structure\Member $eMember, string $type, string $value): array {

		$notifications = $eMember['notifications'];

		if($value === self::getTypes()[$type]['default']) {
			unset($notifications[$type]);
		} else {
			$notifications[$type] = $value;
		}

		return $notifications;

	}

	/**
	 * La base de cette structure porte-t-elle la table des notifications ?
	 *
	 * Deux questions, parce que la première ne suffit pas. `structure.version` dit que la structure a
	 * **passé** `do115`, qui crée la table, pas que sa base existe : `MigrateLib::live()` fait monter la
	 * version à chaque connexion, base présente ou non, chaque `doNN` sortant sur `hasDatabase()`. Une
	 * structure jamais installée atteint donc la borne sans base, et la lire lève `Unknown database`
	 * (#2 et #3 en production le 21/09, la structure 7 en dev le 20/09, 5 912 sur 6 185 actives en
	 * dev). La version écarte sans requête ce qui n'a pas encore la table ; la base se vérifie
	 * ensuite, une fois par structure et par processus.
	 *
	 * Mémoïsée par id de structure, jamais en une seule valeur : un même processus en parcourt
	 * plusieurs (`clean()`, les tests). Une base ne disparaît pas en cours de processus, et une base
	 * créée entre-temps n'est manquée que jusqu'au processus suivant.
	 *
	 * ⚠️ La borne est `NotificationSetting::VERSION_TABLE`, figée, et non `MigrateLib::VERSION`, qui
	 * monte : une garde « cette structure est-elle à jour » se périme au premier `doNN` venu, et
	 * répond alors non pour tout le parc, sur un sujet sans rapport avec les notifications.
	 *
	 * ⚠️ La même garde sert les deux versants, et ils n'en tirent pas la même conséquence. Écrire
	 * dans une table qui n'existe pas encore est une panne qui se répare : la structure rejouera
	 * `data/migrate` à sa prochaine connexion, donc l'émission **reporte** la demande au lieu de
	 * l'abandonner. Lire n'a rien à attendre : une base sans la table n'a aucune notification, et
	 * c'est une réponse complète, pas un report.
	 */
	private static function hasTable(\structure\Structure $eStructure): bool {

		if($eStructure['version'] < NotificationSetting::VERSION_TABLE) {
			return FALSE;
		}

		return self::$databaseByStructure[$eStructure['id']] ??= (
			\structure\Structure::model()->pdo()->select('SHOW DATABASES LIKE \''.\structure\StructureSetting::getDatabaseName($eStructure).'\'')->fetch() !== FALSE
		);

	}

	/**
	 * Exécute la closure avec des packages branchés sur la base de la structure, le package des
	 * notifications par défaut, puis rend le branchement d'avant. Publique pour les `describe()` des
	 * packages dont l'objet vit dans une base de structure (`invoicing\NotificationLib`) : la file les
	 * traite toutes dans un même processus, et `StructureLib::connectDatabase()` changerait aussi la
	 * structure connectée, que l'appelant lit peut-être encore.
	 *
	 * ⚠️ Le branchement d'avant se rend en entier, par `setPackages()`, et non package par package :
	 * un package que la configuration ne déclarait pas (sous le cron, `invoicing` n'est dans aucun
	 * `Lime.s.php`) n'aurait aucune valeur à reprendre, et resterait branché sur la base de la dernière
	 * structure traitée, donc lu depuis elle par la tâche suivante.
	 */
	public static function on(\structure\Structure $eStructure, \Closure $callback, array $packages = ['notification']): mixed {

		$packagesPrevious = \Database::getPackages();
		$base = \structure\StructureSetting::getDatabaseName($eStructure);

		foreach($packages as $package) {
			\Database::setPackage($package, $base);
		}

		\Database::addBase($base, 'ouvretaferme');
		\ModuleModel::resetDatabases();

		try {
			return $callback();
		} finally {
			\Database::setPackages($packagesPrevious);
			\ModuleModel::resetDatabases();
		}

	}

	/**
	 * Les notifications de moins de trente jours de cet utilisateur dans cette structure, de la plus
	 * récente à la plus ancienne. Chacune porte sa structure dans `eStructure`, que le clic renvoie
	 * pour retrouver la base.
	 *
	 * Sans limite : trente jours de commandes d'une structure tiennent en une page une fois groupés
	 * (106 commandes en une semaine, le cas le plus lourd mesuré en dev, font 14 lignes), et une limite
	 * posée avant le groupage couperait un groupe en rendant un total faux.
	 *
	 * Sans l'offre Pilotage, seuls restent les types qui ne la supposent pas (`applyPilotage()`).
	 */
	public static function getByStructure(\structure\Structure $eStructure, \user\User $eUser): \Collection {

		$cNotification = new \Collection();

		// Les sources communes d'abord : elles vivent en base commune, et une structure sans table de
		// notifications reçoit quand même les notes de l'équipe. Elles ne se bornent pas à l'offre
		// Pilotage, qu'aucune ne suppose.
		foreach(self::getSources() as $source) {

			foreach(self::getSourceLib($source)::getCommon($eStructure, $eUser) as $eNotification) {
				$eNotification['source'] = $source;
				$cNotification[] = $eNotification;
			}

		}

		if(self::hasTable($eStructure)) {

			$cNotificationStructure = self::on($eStructure, fn() => self::applyPilotage(Notification::model(), $eStructure)
				->select(Notification::getSelection())
				->whereUser($eUser)
				->whereCreatedAt('>', self::getSince())
				->getCollection());

			foreach($cNotificationStructure as $eNotification) {
				$eNotification['source'] = NULL;
				$cNotification[] = $eNotification;
			}

		}

		foreach($cNotification as $eNotification) {
			$eNotification['eStructure'] = $eStructure;
		}

		// Trié après la fusion, les deux origines se mêlant dans l'ordre de réception. L'id ne départage
		// que deux lignes d'une même origine.
		return $cNotification->sort(['createdAt' => SORT_DESC, 'id' => SORT_DESC]);

	}

	/**
	 * Regroupe les notifications que D7-B réunit en une ligne : même structure, même type, même jour
	 * et même `group` non nul.
	 *
	 * Chaque ligne rendue est la plus récente de son groupe, dans l'ordre reçu, et porte le groupe
	 * entier dans `cNotificationGroup` (elle-même comprise), ses non lues dans `unread`, ses non vues
	 * dans `unseen` et son total dans `amountGroup`. Une notification que rien ne rejoint est un
	 * groupe d'une seule.
	 *
	 * Deux compteurs parce que la tête porte trois états : elle est non vue dès qu'une fille l'est,
	 * vue mais pas cliquée quand elles sont toutes vues et qu'au moins une reste à lire, plate sinon.
	 * L'écran les lit, il ne les calcule pas.
	 *
	 * Elle porte aussi `groupName`, ce que `group` désigne pour le lecteur (`getGroupName()` du
	 * package), et seulement quand la journée en compte plusieurs pour ce type : deux commandes
	 * livrées le même jour par deux boutiques donnent deux lignes au texte identique, alors qu'une
	 * journée qui ne connaît qu'une boutique n'apprendrait rien à la nommer douze fois. Le moteur
	 * compte des valeurs distinctes, il ne sait pas ce qu'elles nomment.
	 *
	 * Un groupe se coupe en deux parts (décidé le 22/09, révisé en recette le même jour) :
	 * les non vues, et ce qui a été reçu plus tôt. Chaque part a sa tête, la plus récente de la part,
	 * donc chaque ligne reste à son heure. Les pages appellent `group()` avant `see()`, qui marque
	 * vues les non vues : la découpe montre donc ce qui arrive à cette ouverture, et au chargement
	 * suivant tout est vu, le groupe se réunit en une ligne. La tête de la part ancienne porte
	 * `earlier` quand la part des non vues du même groupe existe : c'est ce qui fait dire au package
	 * « reçues plus tôt ». Toutes les têtes portent la clé, seules comprises, pour que les packages la
	 * lisent sans garde.
	 *
	 * ⚠️ Deux clés et non une : la tête est elle-même un élément de son groupe, donc `amount` porte
	 * son propre montant et `amountGroup` ce que le groupe additionne. Le montant de chaque ligne est
	 * posé ici et non relu par l'affichage, cette boucle le lisant déjà pour sommer : l'écran ne
	 * manipule plus que des scalaires.
	 *
	 * Le total ne compte que les montants connus : `getAmount()` rend NULL quand l'objet annoncé n'a
	 * pas de prix, et un groupe qui n'en porte aucun garde `amountGroup` à NULL plutôt que d'afficher
	 * un zéro qui serait faux.
	 */
	public static function group(\Collection $cNotification): \Collection {

		$cNotificationHead = new \Collection();
		$namesByDay = [];

		// Les clés des deux parts de chaque groupe coupé, pour la seconde passe.
		$parts = [];

		foreach($cNotification as $eNotification) {

			$day = self::getDayKey($eNotification);
			$classLib = NotificationSetting::resolve($eNotification['type']);

			$groupName = $classLib::getGroupName($eNotification);
			$namesByDay[$day][$groupName] = TRUE;

			// La source dans la clé : une ligne d'une source commune et une notification de la base de la
			// structure peuvent porter le même id.
			if($eNotification['group'] === NULL) {
				$key = 'notification-'.$eNotification['eStructure']['id'].'-'.$eNotification['source'].'-'.$eNotification['id'];
			} else {
				$part = ($eNotification['seenAt'] === NULL) ? 'unseen' : 'earlier';
				$key = $day.'-'.$eNotification['group'].'-'.$part;
				$parts[$day.'-'.$eNotification['group']][$part] = $key;
			}

			if($cNotificationHead->offsetExists($key) === FALSE) {
				$eNotification['cNotificationGroup'] = new \Collection();
				$eNotification['unread'] = 0;
				$eNotification['unseen'] = 0;
				$eNotification['amountGroup'] = NULL;
				$eNotification['groupName'] = $groupName;
				$eNotification['earlier'] = FALSE;
				$cNotificationHead[$key] = $eNotification;
			}

			$eNotificationHead = $cNotificationHead[$key];

			$eNotificationHead['cNotificationGroup'][] = $eNotification;

			if($eNotification['readAt'] === NULL) {
				$eNotificationHead['unread']++;
			}

			if($eNotification['seenAt'] === NULL) {
				$eNotificationHead['unseen']++;
			}

			$amount = $classLib::getAmount($eNotification);

			$eNotification['amount'] = $amount;

			if($amount !== NULL) {
				$eNotificationHead['amountGroup'] = ($eNotificationHead['amountGroup'] === NULL) ? $amount : $eNotificationHead['amountGroup'] + $amount;
			}

		}

		foreach($cNotificationHead as $eNotificationHead) {

			if(count($namesByDay[self::getDayKey($eNotificationHead)]) < 2) {
				$eNotificationHead['groupName'] = NULL;
			}

		}

		foreach($parts as $keys) {

			if(count($keys) === 2) {
				$cNotificationHead[$keys['earlier']]['earlier'] = TRUE;
			}

		}

		return $cNotificationHead;

	}

	/**
	 * Ce qui réunit les lignes d'un même jour, groupe non compris : deux lignes qui partagent cette
	 * clé sans partager leur `group` racontent le même événement pour deux objets différents.
	 */
	private static function getDayKey(Notification $eNotification): string {

		return implode('-', [
			$eNotification['eStructure']['id'],
			$eNotification['type'],
			substr($eNotification['createdAt'], 0, 10)
		]);

	}

	/**
	 * Range les lignes par jour de réception, du plus récent au plus ancien.
	 *
	 * @return array [date => ['cNotification' => lignes du jour, 'unread' => non lues du jour, 'unseen' => non vues du jour]]
	 */
	public static function getDays(\Collection $cNotificationHead): array {

		$days = [];

		foreach($cNotificationHead as $eNotificationHead) {

			$day = substr($eNotificationHead['createdAt'], 0, 10);

			if(array_key_exists($day, $days) === FALSE) {
				$days[$day] = [
					'cNotification' => new \Collection(),
					'unread' => 0,
					'unseen' => 0
				];
			}

			$days[$day]['cNotification'][] = $eNotificationHead;
			$days[$day]['unread'] += $eNotificationHead['unread'];
			$days[$day]['unseen'] += $eNotificationHead['unseen'];

		}

		return $days;

	}

	/**
	 * Le nombre de notifications non vues de ces utilisateurs dans la structure, base de la structure
	 * et sources communes additionnées, zéro compris : c'est ce que le compteur de leur adhésion doit
	 * porter. Borné comme la liste (`getSince()`) : une notification passée au-delà de la durée de vie,
	 * que le nettoyage n'a pas encore effacée, ne se compte plus puisqu'elle ne se montre plus.
	 *
	 * @return array [id d'utilisateur => nombre]
	 */
	public static function countUnseen(\structure\Structure $eStructure, array $users): array {

		$unseen = array_fill_keys($users, 0);

		if(self::hasTable($eStructure)) {

			$cNotification = self::on($eStructure, fn() => Notification::model()
				->select([
					'user',
					'number' => new \Sql('COUNT(*)', 'int')
				])
				->whereUser('IN', $users)
				->whereSeenAt(NULL)
				->whereCreatedAt('>', self::getSince())
				->group('user')
				->getCollection());

			foreach($cNotification as $eNotification) {
				$unseen[$eNotification['user']['id']] += $eNotification['number'];
			}

		}

		foreach(self::getSources() as $source) {

			foreach(self::getSourceLib($source)::countCommonUnseen($eStructure, $users) as $user => $number) {
				$unseen[$user] += $number;
			}

		}

		return $unseen;

	}

	/**
	 * Recale le compteur de cet utilisateur dans chacune de ces structures. Les structures sont
	 * relues pour leur `version`, que `hasTable()` demande : une source commune ne rend que des ids.
	 */
	private static function synchronizeUnseen(array $structures, \user\User $eUser): void {

		$cStructure = \structure\Structure::model()
			->select(['id', 'version'])
			->whereId('IN', array_unique($structures))
			->getCollection();

		foreach($cStructure as $eStructure) {
			\structure\MemberLib::synchronizeNotificationsUnseen($eStructure, self::countUnseen($eStructure, [$eUser['id']]));
		}

	}

	/**
	 * Le nombre de notifications qui restent à lire parmi celles-ci.
	 *
	 * L'écran le distingue de ce que compte la cloche : « Tout marquer comme lu » s'offre tant qu'il
	 * reste quelque chose à traiter, alors que le compteur s'éteint à la première visite. Compté sur
	 * la liste déjà chargée, et non par une requête de plus : l'écran l'a sous la main.
	 */
	public static function countUnread(\Collection $cNotification): int {

		return $cNotification->find(fn(Notification $eNotification) => $eNotification['readAt'] === NULL)->count();

	}

	/**
	 * Marque comme lue une notification de l'utilisateur, puis recale son compteur.
	 *
	 * Elle la marque aussi comme vue, sans rien écraser : cliquer une ligne qu'aucune ouverture n'a
	 * encore montrée (un lien reçu, l'API) la fait passer les deux états d'un coup, et c'est le
	 * `COALESCE` qui tient l'invariant {seenAt IS NULL} ⊆ {readAt IS NULL} sur ce chemin.
	 */
	public static function read(\structure\Structure $eStructure, Notification $e): void {

		$e->expects(['id', 'user', 'source']);

		if($e['source'] !== NULL) {

			$structures = self::getSourceLib($e['source'])::readCommon($eStructure, $e['user'], $e);

			self::synchronizeUnseen([$eStructure['id'], ...$structures], $e['user']);

			return;

		}

		$e['readAt'] = new \Sql('NOW()');
		$e['seenAt'] = new \Sql('COALESCE(seenAt, NOW())');

		self::on($eStructure, fn() => Notification::model()
			->select(['readAt', 'seenAt'])
			->whereReadAt(NULL)
			->update($e));

		\structure\MemberLib::synchronizeNotificationsUnseen($eStructure, self::countUnseen($eStructure, [$e['user']['id']]));

	}

	/**
	 * Marque comme lues toutes les notifications de l'utilisateur dans cette structure, sources
	 * communes comprises, puis recale son compteur partout où une ligne a changé.
	 */
	public static function readAll(\structure\Structure $eStructure, \user\User $eUser): void {

		$structures = [$eStructure['id']];

		foreach(self::getSources() as $source) {
			$structures = array_merge($structures, self::getSourceLib($source)::readCommon($eStructure, $eUser, new Notification()));
		}

		if(self::hasTable($eStructure)) {
			self::readAllStructure($eStructure, $eUser);
		}

		self::synchronizeUnseen($structures, $eUser);

	}

	private static function readAllStructure(\structure\Structure $eStructure, \user\User $eUser): void {

		// Écriture de masse, et non une boucle sur chaque notification comme le dépôt le fait
		// d'ordinaire : une notification n'a aucune règle par élément à rejouer (ni `updatedAt`, ni
		// journal, ni table fille), et « Tout marquer comme lu » peut en toucher des centaines. Forme
		// retenue le 2026-09-19. Un tableau de valeurs, parce qu'un élément porteur d'un
		// id réduirait l'écriture à sa seule ligne (`Module::getUpdateCondition()`).
		self::on($eStructure, fn() => Notification::model()
			->whereUser($eUser)
			->whereReadAt(NULL)
			->update([
				'readAt' => new \Sql('NOW()'),
				'seenAt' => new \Sql('COALESCE(seenAt, NOW())')
			]));

	}

	/**
	 * Marque comme vues toutes les notifications de l'utilisateur dans cette structure, puis éteint son
	 * compteur.
	 *
	 * Appelée par le centre et par la cloche à chaque ouverture : ce qu'elles montrent est vu, et ce
	 * qu'elles ne montrent pas l'est aussi. Au-delà des six têtes de `NotificationSetting::MENU`, des
	 * lignes jamais affichées deviennent donc vues — c'est le sens de la cloche, qui dit « arrivé
	 * depuis ta dernière visite » : sans cela le compteur ne pourrait jamais tomber à zéro.
	 *
	 * ⚠️ Elle s'appelle **après** la lecture qui alimente l'écran, jamais avant : les lignes rendues
	 * portent alors encore l'état d'avant la visite, celui que l'écran marque d'un fond.
	 *
	 * Le compteur est recompté et non posé à zéro : une ligne d'une source commune vue ici l'est aussi
	 * dans les autres structures où l'utilisateur la reçoit, et leur compteur descend avec.
	 *
	 * Écriture de masse comme `readAll()`, et pour les mêmes raisons, tableau de valeurs compris.
	 *
	 * Ni l'une ni l'autre ne se bornent à l'offre Pilotage comme la lecture : une structure qui
	 * perd l'offre garde des lignes que le centre ne montre plus, et sa pastille resterait allumée
	 * sur elles jusqu'au nettoyage.
	 *
	 * @param int $unseen Ce que le compteur porte déjà, que l'appelant a sous la main : à zéro il n'y
	 *                    a rien à voir, et chaque ouverture paierait sinon une écriture dans la base
	 *                    de la structure et une sur `structureMember` pour aucune ligne changée.
	 * @return bool TRUE quand le compteur vient de s'éteindre, ce dont l'écran a besoin : une
	 *              navigation ajax ne redessine pas la barre qui porte la cloche.
	 */
	public static function see(\structure\Structure $eStructure, \user\User $eUser, int $unseen): bool {

		if($unseen === 0) {
			return FALSE;
		}

		$structures = [];

		foreach(self::getSources() as $source) {
			$structures = array_merge($structures, self::getSourceLib($source)::seeCommon($eStructure, $eUser));
		}

		$hasTable = self::hasTable($eStructure);

		if(
			$hasTable === FALSE and
			$structures === []
		) {
			return FALSE;
		}

		if($hasTable) {

			self::on($eStructure, fn() => Notification::model()
				->whereUser($eUser)
				->whereSeenAt(NULL)
				->update([
					'seenAt' => new \Sql('NOW()')
				]));

		}

		self::synchronizeUnseen([$eStructure['id'], ...$structures], $eUser);

		return TRUE;

	}

	/**
	 * Retrouve une notification de l'utilisateur dans la base de la structure, sous la même borne
	 * d'offre que `getByStructure()` : un id posté ne rouvre pas une ligne que le centre cache.
	 *
	 * Avec une source, la ligne est demandée à cette source commune : sans elle, l'id d'une ligne
	 * commune rouvrirait la notification de même id dans la base de la structure. Une source que le
	 * moteur ne connaît pas ne trouve rien.
	 */
	public static function getByUser(\structure\Structure $eStructure, \user\User $eUser, int $id, ?string $source = NULL): Notification {

		if($source !== NULL) {

			if(in_array($source, self::getSources(), TRUE) === FALSE) {
				return new Notification();
			}

			$eNotification = self::getSourceLib($source)::getCommonById($eStructure, $eUser, $id);

			if($eNotification->notEmpty()) {
				$eNotification['source'] = $source;
				$eNotification['eStructure'] = $eStructure;
			}

			return $eNotification;

		}

		if(self::hasTable($eStructure) === FALSE) {
			return new Notification();
		}

		$eNotification = self::on($eStructure, fn() => self::applyPilotage(Notification::model(), $eStructure)
			->select(Notification::getSelection())
			->whereId($id)
			->whereUser($eUser)
			->get());

		// Posée comme dans `getByStructure()` : un `getUrl()` de package peut mener dans la structure
		// qui reçoit la notification (le créneau d'une boutique partagée n'a pas la même adresse chez
		// chacun de ses producteurs), et la notification elle-même ne la porte pas.
		if($eNotification->notEmpty()) {
			$eNotification['source'] = NULL;
			$eNotification['eStructure'] = $eStructure;
		}

		return $eNotification;

	}

	/**
	 * Efface sur tout le parc les notifications de plus de trente jours, lues ou non, puis recale le
	 * compteur des membres qui en perdent une non lue.
	 *
	 * Tourne même quand les notifications sont fermées : sinon les lignes resteraient au-delà de
	 * trente jours le jour où le drapeau serait coupé.
	 */
	public static function clean(): void {

		$cStructure = \structure\Structure::model()
			->select(['id', 'version'])
			->getCollection(index: 'id');

		$failureByStructure = [];

		foreach($cStructure as $eStructure) {

			// Une structure jamais installée n'a rien à nettoyer : la garde l'écarte en silence, et
			// l'avertissement ne garde que les vraies pannes.
			if(self::hasTable($eStructure) === FALSE) {
				continue;
			}

			// Une base en échec ne doit pas priver les suivantes de leur nettoyage : le parc entier passe
			// dans la même nuit, et une base rétablie plus tard rattrape au passage suivant. Les échecs se
			// comptent et ne s'annoncent qu'une fois, en fin de passage : un avertissement par structure
			// noyait le signal sous des milliers de lignes, ce qui revient à ne plus en avoir.
			try {

				// Sur `readAt` et non sur `seenAt`, bien que le compteur compte les non vues : toute non vue
				// est non lue (invariant posé en tête de ce Lib), donc ce filtre retient déjà tous les
				// utilisateurs dont le compteur peut bouger, et quelques-uns pour rien.
				$cNotificationLost = self::on($eStructure, fn() => Notification::model()
					->select(['user'])
					->whereCreatedAt('<', self::getSince())
					->whereReadAt(NULL)
					->group('user')
					->getCollection());

				// Suppression de masse, et non une lecture suivie d'une boucle sur chaque élément comme le
				// dépôt le fait d'ordinaire : une purge par date n'a aucune règle par élément à rejouer, et
				// seul le compteur des adhésions, recalé juste après, dépend de ce qui est parti. Le parc
				// entier passe chaque nuit. Forme retenue le 2026-09-19.
				self::on($eStructure, fn() => Notification::model()
					->whereCreatedAt('<', self::getSince())
					->delete());

				if($cNotificationLost->notEmpty()) {

					$users = $cNotificationLost->getColumnCollection('user')->getIds();

					\structure\MemberLib::synchronizeNotificationsUnseen($eStructure, self::countUnseen($eStructure, $users));

				}

			} catch(\Throwable $e) {

				// La cause voyage avec l'identifiant : sans elle, l'avertissement nocturne rendrait un
				// numéro nu.
				$failureByStructure[$eStructure['id']] = $e->getMessage();

			}

		}

		// Les sources communes vivent en base commune : une passe chacune, pour tout le parc.
		foreach(self::getSources() as $source) {

			foreach(self::getSourceLib($source)::cleanCommon() as $structure => $users) {

				// Une structure supprimée depuis l'envoi n'a plus d'adhésion à recaler.
				if($cStructure->offsetExists($structure)) {
					\structure\MemberLib::synchronizeNotificationsUnseen($cStructure[$structure], self::countUnseen($cStructure[$structure], $users));
				}

			}

		}

		if($failureByStructure !== []) {

			// Les identifiants tiennent dans la ligne, les causes non : elles se répètent presque
			// toujours, et c'est la première qui dit de quoi il s'agit.
			trigger_error('Nettoyage des notifications en échec pour '.count($failureByStructure).' structure(s) sur '.$cStructure->count().', dont #'.implode(', #', array_slice(array_keys($failureByStructure), 0, 10)).'. Première cause : '.reset($failureByStructure), E_USER_WARNING);

		}

	}

}
?>
