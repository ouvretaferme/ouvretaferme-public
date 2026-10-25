<?php
namespace structure;

/**
 * La ferme école : le miroir de production d'une ferme réelle, rejouable.
 *
 * ⚠️ **Rien ici n'est une liste écrite à la main.** Les modules à copier, les colonnes à décaler
 * et celles à laisser tranquilles se dérivent toutes du modèle. C'est une propriété de
 * construction et non un effort de vigilance : une colonne ajoutée demain dans un `.yml` est
 * prise en compte sans que personne y pense, et le contrôle d'intégrité parcourt exactement les
 * mêmes colonnes que la copie — sinon il partagerait son angle mort et ne contrôlerait rien.
 *
 * **Le décalage d'id.** Chaque ligne copiée prend `id + OFFSET`, ce qui permet de réécrire les
 * clés étrangères internes sans tenir une carte ligne à ligne. Ça n'est juste qu'à une condition,
 * et c'est tout le sujet : **une clé qui pointe hors de la copie ne doit pas être décalée.**
 *
 * Trois familles, distinguées par ce que le modèle sait déjà dire :
 *
 * - un module **sans colonne `structure`** (`plant\Criterion`, `Family`, `Tag`, `VarietyName`)
 *   est du référentiel partagé pur : jamais copié, ses ids jamais décalés ;
 * - un module à **`structure` nullable** (`plant\Plant`, `Size`, `Variety`, `work\Action`,
 *   `Category`) est mixte — certaines lignes sont à la ferme, d'autres au référentiel. Une clé
 *   qui le vise se décale **seulement si la ligne visée appartient à la ferme source**, ce que
 *   la jointure tranche ligne par ligne ;
 * - un module à **`structure` non nullable** appartient entièrement à la ferme : décalage sec.
 *
 * Une seule colonne mal classée ferait pointer la ferme école sur les données d'une autre ferme,
 * **sans la moindre erreur SQL**. D'où la dérivation, et d'où `check()`.
 *
 * **Deux appelants, une mécanique.** La ferme école (`configure/schoolAi.p.php`) et la duplication
 * d'une ferme par l'équipe d'un réseau (`DuplicateAiLib`) copient par les mêmes méthodes : elles
 * écartent les mêmes modules, vident les mêmes colonnes, décalent les mêmes ids rangés en json et
 * alignent la même configuration. Ce qui les distingue passe en argument : les packages copiés, les
 * saisons, les conditions qui bornent une table, le seuil d'anonymat des ventes. Une copie qui
 * garderait ses propres règles de décalage ou de clés aurait ses propres angles morts, et `check()`
 * ne les verrait pas.
 */
class SchoolAiLib {

	use \Notifiable;

	/**
	 * Les packages de la ferme école : ceux que le rôle `student` permet d'ouvrir.
	 *
	 * `RoleLib` ne donne au groupe `EDUCATION` qu'un rôle `student` porteur de `productionObserve` :
	 * copier la vente ou la comptabilité serait travailler sur des données que personne ne pourra
	 * jamais ouvrir, et ce sont elles qui portent le personnel.
	 *
	 * `aid` fait exception : le rôle `student` n'y donne rien, le catalogue des aides se lisant par
	 * toute personne connectée. Il figure ici parce que tout package de la production doit y figurer
	 * (`DuplicateSentinelAi`), et ses tables sont toutes hors copie (`MODULES_EXCLUDE`).
	 */
	const PACKAGES = ['map', 'plant', 'sequence', 'series', 'work', 'gallery', 'analyze', 'product', 'hr', 'toolbox', 'planning', 'aid'];

	/**
	 * On ne copie pas ce qu'on ne sait pas anonymiser, ni ce qui pointerait hors de la copie.
	 *
	 * Les photos portent le `hash` du fichier : les copier ferait pointer la ferme école sur les
	 * images réelles de sa source — ses bâtiments, ses parcelles, ses gens. Aucun brouillage de
	 * texte ne rattrape une photo. Une ferme école sans images reste entièrement utilisable pour
	 * enseigner des cultures ; avec celles de sa source, elle n'est anonyme sous aucun angle.
	 *
	 * ⚠️ La boutique du catalogue est exclue pour une **seconde** raison, et elle tient à la portée
	 * de la copie plutôt qu'à l'anonymat : `catalog\Offer` pointe `shop\Shop` et `shop\Date`,
	 * `product\Stock` pointe `selling\Sale`, et aucun de ces trois modules n'est copié. Le
	 * décalage d'id ne s'applique qu'à ce qui l'est (`getColumns()`), donc ces colonnes garderaient
	 * l'identifiant de la source : la ferme école porterait des offres rattachées à la boutique
	 * réelle. Ce qu'on copie du catalogue, ce sont les produits eux-mêmes, parce que les rapports
	 * et les ventes les nomment.
	 */
	const MODULES_EXCLUDE = [
		'gallery\Photo', 'plant\VarietyRating',
		'map\Decor', // le décor posé dans la 3D est propre à la structure, ses positions la situent
		'map\DecorLayer', // les calques rangent ce décor, qui ne se copie pas
		'map\DecorBuilding', // les bâtiments composés ne servent qu'à ce décor, et leur nom désigne ceux de la structure
		'map\DecorUpload', // les modèles 3D téléversés à la place de ces bâtiments, dont le hash ouvrirait le fichier de la source
		'product\Stock', 'product\StockBookmark',
		'product\Configuration', // les notes de stock sont du texte libre, et la ligne naît avec la structure
		// Le catalogue des aides est commun à toutes les structures : aucune ligne n'appartient à la source.
		'aid\Aid', 'aid\AidLink', 'aid\AidOrganization', 'aid\AidPiece', 'aid\AidTag', 'aid\Area', 'aid\Edition', 'aid\Expected', 'aid\Organization', 'aid\Piece', 'aid\Source', 'aid\Tag',
	];

	/**
	 * Ce qui se vide, et par quoi, dans la ferme école comme dans une ferme dupliquée.
	 *
	 * Une seule liste pour les deux copies, voulu le 15/09/2026 : une ferme dupliquée
	 * perd les mêmes textes que la ferme école.
	 *
	 * Chaque propriété dit la valeur vide que **sa** colonne accepte : un `json` et un enum non
	 * nullables refusent NULL.
	 *
	 * **Aucune image ni aucun média de la source**, règle du 15/09/2026. Une vignette porte
	 * le hash d'un fichier : sur une ferme école, elle montrerait les images de la source ; sur une
	 * ferme dupliquée, qui se modifie, `MediaLib::delete()` passe le média en `DELETED` par ce hash,
	 * et retirer la vignette de la copie effacerait l'image de la source. Une colonne `editor` se vide
	 * pour la même raison, en plus de son texte libre : c'est là qu'un média se range dès que la
	 * saisie accepte une figure, et la vider ne dépend pas de ce que la saisie accepte aujourd'hui.
	 *
	 * **Les espaces d'écriture libre**, où quelqu'un finit par nommer un client, un voisin ou un
	 * salarié. Le critère est la nature de la colonne, pas son volume : les libellés fonctionnels
	 * restent, un nom de planche est un repère que l'écran affiche.
	 *
	 * **L'appréciation de la ferme**, qui n'est pas une donnée de la plante : *ce qui exprime le
	 * jugement de la ferme ne se copie pas, ce qui décrit la plante se copie.* Un jeu de notes
	 * identifie son auteur mieux qu'un nom, précisément parce que personne ne pense à
	 * l'anonymiser — et ce qu'on vient étudier est un système de culture, pas les goûts de celui
	 * qui le conduit.
	 *
	 * ⚠️ Elle a **cinq porteurs** et non deux : la table `plant\VarietyRating`, exclue de la copie,
	 * `plant.criteria`, et trois colonnes de `plant\Variety` — `tags`, choisis en notant
	 * (`varietyRating.p.php:108` les écrit avec la note), `ratingOverall` et `ratingStatus`. Les
	 * nommer toutes est le seul moyen d'éviter que l'appréciation ne transparaisse par la porte
	 * d'à côté.
	 *
	 * `plant.tagsAccepted` n'y est pas, et c'est mesuré : `TagLib::getByPlant()` le lit sur la
	 * ligne du **référentiel partagé** (`whereStructure(NULL)`), jamais sur la copie d'une ferme.
	 * Il décrit l'espèce, pas ce qu'une ferme en pense.
	 */
	const PROPERTIES_BLANK = [
		'sequence\Sequence' => ['description' => 'NULL', 'comment' => 'NULL'],
		'sequence\Flow' => ['description' => 'NULL'],
		'plant\Plant' => ['criteria' => '"[]"', 'vignette' => 'NULL'],
		'plant\Size' => ['comment' => 'NULL'],
		'plant\Variety' => [
			'ratingComment' => 'NULL',
			'tags' => '"[]"',
			'ratingOverall' => 'NULL',
			'ratingStatus' => '"waiting"',
		],
		// ⚠️ Du texte libre écrit au fil du travail, et c'est ce qui le rend identifiant :
		// « Complément de 8 plaques de 150 », « Enlever filet climatique » nomment des gestes, des
		// fournisseurs, parfois des personnes. 9 470 des 22 138 interventions de la source en
		// portent. Ce qui s'étudie sur une ferme école est la structure du plan de culture, pas
		// les notes qu'une équipe se laisse.
		'series\Task' => ['description' => 'NULL'],
		// Le modèle des descriptions que la répétition écrira sur chaque intervention qu'elle crée.
		'series\Repeat' => ['description' => 'NULL'],
		'series\Series' => ['comment' => 'NULL'],
		// ⚠️ `origin` nomme la ferme, et pas de loin : sur les deux sources, les dix valeurs
		// distinctes sont des raisons sociales et des communes — « Le Biau Jardin (Gerzat, 63) »,
		// « Thierry Provençal (Le Broc, 63) », et jusqu'au nom de la source elle-même. C'est le
		// champ qui dit d'où vient un produit revendu ; sur une ferme école, il désigne le
		// voisinage réel. `stockLast` pointe `product\Stock`, qui n'est pas copié.
		'product\Product' => [
			'description' => 'NULL',
			'origin' => 'NULL',
			'vignette' => 'NULL',
			'stockLast' => 'NULL',
		],
		'work\Tool' => ['vignette' => 'NULL', 'comment' => 'NULL'],
		// La note d'un entretien fait est de l'écriture libre : « garage Dupont », une facture, un nom.
		// Sa photo montre un lieu, une plaque, une facture ; son média n'appartient pas à la copie.
		'toolbox\MaintenanceDone' => ['comment' => 'NULL', 'photo' => 'NULL'],
		// Une immatriculation désigne un véhicule réel, donc son propriétaire.
		'toolbox\Equipment' => ['registration' => 'NULL'],
		// Le décor adopté n'est pas copié (`map\Decor` est exclu) : le drapeau copié seul ouvrirait la copie sur un plan vide,
		// alors qu'elle doit montrer le décor par défaut comme toute ferme qui n'a rien posé.
		'map\Configuration' => ['decorAdopted' => '0'],
	];

	/**
	 * Les deux listes de la copie : celles de ce fichier, et celles que chaque package fermé déclare
	 * dans son `Structure.o.php`, absent de la version publique, qui ne peut pas nommer ses modules.
	 */
	public static function getModulesExclude(): array {
		return array_merge(self::MODULES_EXCLUDE, ...self::notify('getSchoolModulesExclude'));
	}

	public static function getPropertiesBlank(): array {
		return array_merge(self::PROPERTIES_BLANK, ...self::notify('getSchoolPropertiesBlank'));
	}

	/**
	 * ⚠️ **Les clés étrangères que le modèle ne déclare pas**, parce qu'elles vivent dans du JSON.
	 *
	 * C'est le seul endroit où la dérivation s'arrête et où une liste écrite à la main commence,
	 * et il faut le savoir : `getColumns()` et `check()` lisent tous deux le modèle, donc **ni la
	 * copie ni le contrôle** ne voient un tableau d'ids rangé dans une colonne `json`. Mesuré :
	 * sans cette liste, 593 références de planches de la ferme école pointaient encore les
	 * planches de sa source, sans la moindre erreur et sans que le contrôle bronche.
	 *
	 * Chaque paire est vérifiée deux fois — par le code qui lit la colonne, et par un taux de
	 * couverture de 100 % sur la ferme source. ⚠️ La couverture seule ne suffit pas : elle dit
	 * qu'une colonne porte des ids, pas de quelle table. `work\Action.categories` couvre 100 %
	 * de `plant\Plant` par pure coïncidence de plages d'ids. `series\Repeat.tools`, ajouté le
	 * 15/09/2026, n'a aucune valeur sur les sources 7, 368 et 439 : sa couverture se mesure au parc,
	 * 1 727 références, aucune hors des outils de la ferme de la répétition. `map\Configuration.rotationExclude`,
	 * ajouté le 22/10/2026, vise les espèces de la structure : 739 références au parc, aucune au référentiel.
	 *
	 * `plant\Plant.criteria` et `plant\Variety.tags` n'y sont pas, et c'est voulu : elles visent
	 * `plant\Criterion` et `plant\Tag`, du référentiel partagé qui n'est pas copié. Les décaler
	 * les ferait pointer dans le vide.
	 */
	const PROPERTIES_JSON = [
		'map\Configuration' => ['rotationExclude' => 'plant\Plant'],
		'sequence\Flow' => ['methods' => 'work\Method', 'tools' => 'work\Tool', 'products' => 'work\Tool'],
		'series\Repeat' => ['methods' => 'work\Method', 'tools' => 'work\Tool', 'products' => 'work\Tool'],
		'series\Task' => ['methods' => 'work\Method', 'tools' => 'work\Tool', 'products' => 'work\Tool'],
		'work\Action' => ['categories' => 'work\Category'],
	];

	/**
	 * Les tableaux d'ids d'utilisateurs, vidés — et eux le restent.
	 *
	 * ⚠️ Contrairement aux colonnes d'utilisateur, qui reçoivent le compte désigné : une liste
	 * d'affectations où le même compte figure quatre fois dirait qu'une intervention a été
	 * confiée quatre fois à la même personne. Un tableau vide dit « pas d'affectation », ce qui
	 * est vrai et se lit.
	 *
	 * Mesuré : les 517 valeurs de `plannedUsers` de la ferme 7 sont **toutes** des comptes réels,
	 * et `TaskLib` les compare bien à des `$eUser['id']` (`Task.l.php:382`, `418`, `486`). Une
	 * ferme censée effacer les gens de sa source nommait donc ses salariés sur chaque
	 * intervention copiée.
	 */
	const PROPERTIES_JSON_USER = [
		'series\Task' => ['plannedUsers'],
	];

	/**
	 * Les modules copiables : ceux de la production qui savent dire à quelle ferme ils sont.
	 *
	 * Un module sans colonne `structure` ne peut pas borner sa portée, donc il n'appartient à
	 * personne : c'est du référentiel partagé, et le copier reviendrait à dupliquer le catalogue
	 * d'espèces de tout le produit.
	 *
	 * Les exclusions **de module** de `DemoLib` s'appliquent par-dessus, et elles valent mieux que
	 * ce qu'on écrirait ici : elles écartent notamment `plant\CommunityCultivation` et
	 * `plant\CommunityVariety`, qui ne sont pas les données de la ferme mais sa **projection dans
	 * la bibliothèque commune**. Les copier ferait compter deux fois la même ferme dans les
	 * agrégats entre structures — sous une identité anonymisée, ce qui permettrait en plus de la
	 * reconnaître par recoupement. Elles se refabriquent par leur propre traitement, comme tout ce
	 * qui est dérivé.
	 *
	 * ⚠️ Ses exclusions **de paquetage**, en revanche, ne s'appliquent pas : `PACKAGES` ci-dessus
	 * est déjà une liste blanche, et deux listes qui répondent à la même question finissent par se
	 * contredire. C'est arrivé le 2026-09-05 sur `analyze` — la ferme de démonstration l'écarte,
	 * la ferme école en a besoin, et l'écran des rapports restait vide sans qu'aucune erreur ne le
	 * dise. La démo répond « que montre-t-on à un visiteur », l'école « qu'étudie-t-on ».
	 *
	 * `$packages` est la liste blanche de l'appelant : `PACKAGES` pour l'école, la famille choisie
	 * pour la duplication.
	 */
	public static function getModules(array $packages): array {

		$modulesExclude = DemoLib::getModulesExclude();
		$modulesExcludeSchool = self::getModulesExclude();
		$modules = [];

		foreach(DemoLib::getModules() as $module => $m) {

			if(
				in_array($m->getPackage(), $packages) and
				$m->hasProperty('structure') and
				in_array($m->getModule(), $modulesExclude) === FALSE and
				in_array($module, $modulesExcludeSchool) === FALSE
			) {
				$modules[$module] = $m;
			}

		}

		return $modules;

	}

	/**
	 * La table qualifiée par sa base.
	 *
	 * La connexion n'a pas de base par défaut : sans le préfixe, tout `INSERT` rend « No database
	 * selected ». `DemoLib::copy()` qualifie pour la même raison, et il le faut d'autant plus ici
	 * qu'une même requête peut joindre deux packages.
	 */
	public static function getTable(\ModuleModel $m): string {

		$pdo = $m->pdo();

		return $pdo->api->field($m->getDatabase()).'.'.$pdo->api->field($m->getTable());

	}

	/**
	 * Ce que devient chaque colonne dans la copie, dérivé du modèle.
	 *
	 * Rend, par propriété, l'expression SQL à écrire et la jointure qu'elle exige. La jointure
	 * n'apparaît que sur les cibles mixtes : ailleurs, l'expression se suffit.
	 */
	public static function getColumns(\ModuleModel $m, array $modules, int $structureSource, int $structureSchool, array $users, int $offset): array {

		$select = [];
		$joins = [];
		$index = 0;
		$propertiesBlank = self::getPropertiesBlank()[$m->getModule()] ?? [];

		foreach($m->getProperties() as $property) {

			$field = $m->pdo()->api->field($property);
			$target = $m->getPropertyToModule($property);

			if($property === 'id') {
				$select[$property] = 'm1.'.$field.' + '.$offset;
				continue;
			}

			if($property === 'structure') {
				$select[$property] = (string)$structureSchool;
				continue;
			}

			$blank = $propertiesBlank[$property] ?? NULL;

			if($blank !== NULL) {
				$select[$property] = $blank;
				continue;
			}

			if(in_array($property, self::PROPERTIES_JSON_USER[$m->getModule()] ?? [])) {

				// Un tableau de comptes se traduit valeur par valeur, comme un tableau d'ids se
				// décale : une intervention prévue pour trois personnes reste prévue pour leurs
				// trois fantômes. Vidé, il déliait le prévu du réalisé sur le même écran.
				$select[$property] = 'JSON_MERGE_PRESERVE("[]", COALESCE(('
					.'SELECT JSON_ARRAYAGG('.self::getUserCase('j.v', $users).')'
					.' FROM JSON_TABLE(m1.'.$field.', "$[*]" COLUMNS (v INT PATH "$")) j'
					.'), JSON_ARRAY()))';

				continue;

			}

			$jsonTarget = self::PROPERTIES_JSON[$m->getModule()][$property] ?? NULL;

			if($jsonTarget !== NULL) {

				// Un tableau d'ids se décale valeur par valeur, sans la jointure qui distingue ligne
				// à ligne ce qui appartient à la ferme. La cible n'a donc que des lignes de ferme, ou
				// le code interdit à une ferme d'y viser le référentiel : `work\Category` est mixte,
				// mais l'étape `known` de `Action::properties()` n'accepte que les catégories de la
				// structure de l'action, et `ActionLib::duplicateForStructure()` traduit les ids du
				// référentiel à la création d'une ferme (0 sur 192 936 références au parc le 15/09/2026).
				// `DuplicateSentinelAi` tient la liste de ces cibles mixtes.
				$select[$property] = 'JSON_MERGE_PRESERVE("[]", COALESCE(('
					.'SELECT JSON_ARRAYAGG(j.v + '.$offset.')'
					.' FROM JSON_TABLE(m1.'.$field.', "$[*]" COLUMNS (v INT PATH "$")) j'
					.'), JSON_ARRAY()))';

				continue;

			}

			if($target === 'user\User') {

				// Les comptes sont réels et ne se copient pas : une ligne copiée ne peut pas
				// garder l'auteur qu'elle avait, ce serait nommer quelqu'un de la ferme source
				// dans une ferme censée l'anonymiser. Chacun reçoit donc son **fantôme**, un par
				// personne, et non un compte unique pour tout le monde.
				//
				// ⚠️ Un compte unique n'est pas une anonymisation neutre, c'est une perte : il
				// fond les 25 personnes qui pointent sur la ferme source en une seule, et l'écran
				// du temps de travail en équipe n'a plus d'équipe à montrer. Ce que le maraîchage
				// s'enseigne ici, c'est justement la répartition du travail entre les personnes.
				//
				// ⚠️ Et non NULL sur les colonnes qui l'acceptent, comme c'était fait d'abord :
				// vider `Timesheet.user` a cassé `TimesheetLib::delegateByTask()`, qui indexe par
				// `$e['user']['id']` (`series/lib/Timesheet.l.php:163`). L'absence reste
				// l'absence : `IF(... IS NULL, NULL, ...)` garde la distinction entre « personne
				// ne l'a fait » et « quelqu'un l'a fait ».
				//
				// Sans `ELSE` : une valeur hors de la carte rendrait NULL, donc une erreur SQL sur
				// une colonne non nullable. La carte est bâtie sur ces colonnes-là, elle les
				// couvre toutes ; si elle cessait de le faire, mieux vaut l'apprendre bruyamment.
				$select[$property] = 'IF(m1.'.$field.' IS NULL, NULL, '.self::getUserCase('m1.'.$field, $users).')';
				continue;

			}

			if($target !== NULL and $target !== 'structure\Structure' and array_key_exists($target, $modules) === FALSE) {

				// ⚠️ **Une clé vers ce qu'une ferme porte, hors de la copie, arrête tout.** Recopiée
				// telle quelle, elle garderait l'id d'une ligne de la source : la copie pointerait les
				// données d'une autre ferme sans erreur SQL, et `check()`, qui dérive les mêmes
				// colonnes, ne relèverait rien. Nullable compris : une clé vers `plant\Variety` peut
				// viser une variété propre à la source. Le remède est de copier la cible, ou de vider
				// la colonne.
				if(new \ReflectionClass($target.'Model')->newInstance()->hasProperty('structure')) {
					throw new \Exception("Cle hors de la copie : ".$m->getModule().".".$property." vise ".$target.", qui n'est ni copie ni vide");
				}

			}

			if($target === NULL or $target === 'structure\Structure' or array_key_exists($target, $modules) === FALSE) {

				// Hors de la copie : référentiel partagé, ou valeur ordinaire. On ne touche pas.
				$select[$property] = 'm1.'.$field;
				continue;

			}

			$mModuleTarget = $modules[$target];

			if($mModuleTarget->isPropertyNull('structure') === FALSE) {

				// Toutes les lignes de la cible appartiennent à une ferme : celle-ci est
				// forcément la source, puisque la ligne qui pointe en vient.
				$select[$property] = 'IF(m1.'.$field.' IS NULL, NULL, m1.'.$field.' + '.$offset.')';
				continue;

			}

			// Cible mixte : la jointure dit, ligne par ligne, si la cible fait partie de la
			// copie. Sans elle, une culture rattachée à une espèce du référentiel partagé
			// pointerait après décalage sur une ligne qui ne lui appartient pas.
			$index++;
			$alias = 'j'.$index;

			$joins[] = 'LEFT JOIN '.self::getTable($mModuleTarget).' '.$alias
				.' ON '.$alias.'.id = m1.'.$field.' AND '.$alias.'.structure = '.$structureSource;

			$select[$property] = 'IF('.$alias.'.id IS NULL, m1.'.$field.', m1.'.$field.' + '.$offset.')';

		}

		return [$select, $joins];

	}

	/**
	 * Le seuil d'anonymat de la ferme école : une ligne agrégée regroupe au moins trois clients
	 * distincts. Il se passe à `aggregateSelling()`, que la duplication appelle sans seuil.
	 *
	 * Le critère est le nombre de **clients** et non de ventes : trois ventes au même client ne
	 * regroupent personne.
	 */
	const CUSTOMERS_MIN = 3;

	/**
	 * Les ventes livrées de la source, agrégées dans la ferme école par semaine ISO, type et produit
	 * par le package des ventes (`selling\SchoolAiLib::aggregate()`), qui dit pourquoi l'agrégat est
	 * l'anonymisation elle-même. Absent de la version publique, rien n'est écrit.
	 */
	public static function aggregateSelling(int $structureSource, int $structureSchool, int $offset, ?string $until, int $customersMin): array {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return ['ventes' => 0, 'lignes' => 0];
		}

		return self::notify('aggregateSchoolSelling', $structureSource, $structureSchool, $offset, $until, $customersMin)[0];

	}

	/**
	 * La marge ajoutée au décalage, pour les ids que l'auto-incrément distribue pendant la copie.
	 *
	 * ⚠️ Sans elle, le premier id copié de la table dominante vaut exactement MAX + 1, la prochaine
	 * valeur de l'auto-incrément : une intervention créée par n'importe quelle ferme entre le calcul
	 * et l'`INSERT … SELECT` prend un id de la plage copiée, et la copie échoue en clé en double.
	 * `innodb_autoinc_lock_mode = 2` (mesuré en dev le 14/09/2026) ne réserve rien d'avance. La marge
	 * couvre largement les écritures de quelques secondes et reste négligeable devant l'étendue d'une
	 * source ancienne (4,5 M d'ids sur `seriesTask` pour la ferme 7).
	 */
	const OFFSET_MARGIN = 100000;

	/**
	 * Le décalage : ce qui place chaque id copié au-dessus du plus grand id de sa table.
	 *
	 * Un seul décalage pour toutes, et c'est ce qui rend la réécriture des clés possible sans
	 * carte : une clé de A vers B se décale du décalage de B, or il n'y en a qu'un. Chaque table
	 * demande MAX_t + 1 − MIN_source_t, puisque l'id copié le plus bas vaut MIN_source_t +
	 * décalage ; le décalage est le plus grand de ces besoins.
	 *
	 * ⚠️ **Pas MAX + 1 tout court**, qui a longtemps suffi à la ferme école : copier une ferme
	 * ancienne et active porte alors ses ids jusqu'à deux fois le plus grand de la table (la ferme 7
	 * va de 267 à 4 521 225 sur `seriesTask`, dont le MAX est 4 521 774), et chaque duplication
	 * doublerait le plafond. Ici, une copie coûte l'étendue de sa source.
	 */
	public static function getOffset(array $modules, int $structureSource): int {

		$offset = 0;
		$maxSource = [];

		foreach($modules as $module => $m) {

			// ⚠️ Toutes les tables n'ont pas d'`id` : `analyze\Product` est identifiée par le
			// couple (rapport, produit). Elle n'a donc aucun id à décaler — ses clés étrangères,
			// elles, le sont comme partout — et l'interroger sur un `MAX(id)` lève un
			// « Unknown column 'id' » qui arrête le script avant la moindre écriture.
			if($m->hasProperty('id') === FALSE) {
				continue;
			}

			$max = (int)$m->select(['max' => new \Sql('MAX(id)', 'int')])->get()['max'];

			$eElementSource = $m
				->select([
					'min' => new \Sql('MIN(id)', 'int'),
					'max' => new \Sql('MAX(id)', 'int')
				])
				->whereStructure($structureSource)
				->get();

			// Une table où la source n'a rien ne copie rien, donc ne demande rien.
			if($eElementSource['min'] === NULL) {
				continue;
			}

			$offset = max($offset, $max + 1 - $eElementSource['min']);
			$maxSource[$module] = $eElementSource['max'];

		}

		$offset += self::OFFSET_MARGIN;

		// L'id copié le plus haut de chaque table vaut son MAX source + décalage : au-delà de ce que
		// la colonne sait tenir, MySQL plafonnerait silencieusement en mode non strict et écraserait
		// des lignes.
		foreach($maxSource as $module => $max) {

			if($offset + $max >= 4294967295) {
				throw new \Exception("Decalage impossible : les ids de ".$module." depasseraient la capacite d'un int32 non signe (".$offset." + ".$max.")");
			}

		}

		return $offset;

	}

	/**
	 * Tout ce qu'une ferme école peut porter, y compris ce qu'on a cessé de copier.
	 *
	 * ⚠️ L'effacement balaie **plus large que la copie**, et ce n'est pas une précaution : le jour
	 * où un module sort du catalogue, ses lignes déjà copiées ne seraient plus jamais effacées ni
	 * remplacées. Mesuré — en retirant `gallery\Photo` de la copie, 1 286 photos de la ferme
	 * source sont restées dans la ferme école à travers tous les rejeux suivants, sans qu'aucun
	 * contrôle ne s'en aperçoive. Un catalogue qui se réduit doit emporter ce qu'il abandonne.
	 *
	 * Le balayage reste borné à la ferme école : il ne peut rien effacer d'autre.
	 *
	 * Sauf `product\Configuration` : sa ligne naît avec la structure et ne se copie pas, donc ni la
	 * copie ni les défauts ne la reposeraient, et la ferme perdrait ses réglages de stock à chaque
	 * rejeu.
	 */
	public static function getModulesClearable(): array {

		$modules = [];

		foreach(DemoLib::getModules() as $module => $m) {

			if(
				in_array($m->getPackage(), self::PACKAGES) and
				$m->hasProperty('structure') and
				$module !== 'product\Configuration'
			) {
				$modules[$module] = $m;
			}

		}

		return $modules;

	}

	/**
	 * Les comptes que les données de la ferme source désignent, du plus ancien au plus récent.
	 *
	 * Dérivé du modèle et non de la liste des membres, et l'écart est large : sur la ferme 7,
	 * **13 des 25 personnes qui ont pointé ne sont plus membres**. Une équipe reconstituée depuis
	 * `structureMember` perdrait la moitié de l'histoire du travail, qui est précisément ce qu'on
	 * vient étudier.
	 *
	 * L'ordre par identifiant est ce qui rend l'équipe stable d'un rejeu à l'autre : une personne
	 * garde sa position, et un compte nouveau prend la suivante sans renommer les autres.
	 */
	public static function getUsers(array $modules, int $structureSource): array {

		$users = [];

		foreach($modules as $m) {

			$pdo = $m->pdo();
			$columns = [];

			foreach($m->getProperties() as $property) {

				if($m->getPropertyToModule($property) === 'user\\User') {
					$columns[] = 'm1.'.$pdo->api->field($property);
				}

			}

			// Les tableaux d'ids rangés dans du json : le modèle ne les déclare pas, et sans eux
			// une personne qui n'apparaît que dans un `plannedUsers` n'aurait pas de fantôme.
			$json = self::PROPERTIES_JSON_USER[$m->getModule()] ?? [];

			if($columns === [] and $json === []) {
				continue;
			}

			$table = self::getTable($m);
			$where = ' WHERE m1.'.$pdo->api->field('structure').' = '.$structureSource;

			foreach($columns as $column) {

				foreach($pdo->select('SELECT DISTINCT '.$column.' AS u FROM '.$table.' m1'.$where.' AND '.$column.' IS NOT NULL') as $row) {
					$users[(int)$row['u']] = TRUE;
				}

			}

			foreach($json as $property) {

				$column = 'm1.'.$pdo->api->field($property);

				$sql = 'SELECT DISTINCT j.v AS u FROM '.$table.' m1'
					.' JOIN JSON_TABLE('.$column.', "$[*]" COLUMNS (v INT PATH "$")) j'
					.$where;

				foreach($pdo->select($sql) as $row) {
					$users[(int)$row['u']] = TRUE;
				}

			}

		}

		$users = array_keys($users);
		sort($users);

		return $users;

	}

	/**
	 * Le nom d'un fantôme, tiré de sa position et de rien d'autre.
	 *
	 * C'est ce nom qui sert de clé au réemploi : un fantôme n'a ni courriel ni identifiant stable
	 * — `doCreateGhost()` pose `email => NULL` —, donc c'est son identité qui doit être
	 * reproductible. Sans cela, chaque rejeu créerait une équipe neuve, l'empreinte dériverait, et
	 * la ferme école accumulerait des personnes.
	 *
	 * L'initiale change de lettre à chaque tour de la liste : les prénoms se répéteraient au-delà
	 * de 41 personnes, la paire tient jusqu'à 1 066.
	 */
	public static function getGhostName(int $position): array {

		$names = \structure\DemoLib::FIRST_NAMES;

		return [
			$names[$position % count($names)],
			substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', intdiv($position, count($names)) % 26, 1).'.'
		];

	}

	/**
	 * Une personne fictive par compte que la source désigne, réemployée d'un rejeu à l'autre.
	 *
	 * Le fantôme est la forme que le produit donne déjà à quelqu'un qui travaille sur une ferme
	 * sans y avoir de compte (`MemberLib::createGhostUser()`) : la ferme école n'invente donc rien,
	 * elle emploie ce que les fermes réelles emploient pour leurs saisonniers.
	 *
	 * ⚠️ Les fantômes ne sont pas effacés par `clear()` : `structure\Member` n'est pas dans
	 * `PACKAGES`, et c'est voulu. Les effacer puis les recréer changerait leurs identifiants à
	 * chaque rejeu, donc toutes les colonnes de comptes des 93 000 lignes copiées, donc l'empreinte
	 * qui garantit qu'un rejeu ne fait pas dériver la ferme école.
	 */
	public static function placeTeam(array $modules, int $structureSource, int $structureSchool): array {

		$users = self::getUsers($modules, $structureSource);

		$eStructureSchool = Structure::model()->select(['id', 'name'])->whereId($structureSchool)->get();
		$eRole = \structure\RoleLib::getOne($eStructureSchool, 'ghost');

		$ghosts = [];

		foreach(\structure\MemberLib::getGhostByStructure($eStructureSchool) as $eMember) {
			$ghosts[$eMember['user']['firstName'].' '.$eMember['user']['lastName']] = $eMember['user'];
		}

		$map = [];

		foreach($users as $position => $userSource) {

			[$firstName, $lastName] = self::getGhostName($position);

			$eUser = $ghosts[$firstName.' '.$lastName] ?? NULL;

			if($eUser === NULL) {

				$eUser = \user\UserLib::getNew()->merge([
					'invoiceCountry' => new \user\Country(),
					'email' => NULL,
					'type' => \user\User::PRIVATE,
					'visibility' => \user\User::PRIVATE,
					'roleProducer' => TRUE
				]);

				$eUser->build(['firstName', 'lastName'], ['firstName' => $firstName, 'lastName' => $lastName]);

				\structure\MemberLib::createGhostUser($eStructureSchool, $eUser);

			}

			$map[$userSource] = $eUser['id'];

		}

		return $map;

	}

	/**
	 * La traduction d'une colonne de compte, écrite une fois et posée partout.
	 *
	 * Un CASE et non une jointure : la carte tient dans quelques dizaines d'entrées, et une
	 * jointure de plus par colonne de compte multiplierait les alias sur des tables déjà jointes.
	 */
	private static function getUserCase(string $column, array $users): string {

		// Une carte vide dit qu'aucune ligne copiée ne désigne personne : `getUsers()` la dérive des
		// mêmes colonnes. Un CASE sans WHEN n'est pas du SQL, et une ferme dupliquée sans aucune
		// saisie en est le cas courant, pas une erreur.
		if($users === []) {
			return 'NULL';
		}

		$case = 'CASE '.$column;

		foreach($users as $userSource => $userSchool) {
			$case .= ' WHEN '.$userSource.' THEN '.$userSchool;
		}

		return $case.' END';

	}

	/** Efface ce que la ferme école porte, avant de le refaire. */
	public static function clear(int $structureSchool): int {

		$deleted = 0;

		foreach(self::getModulesClearable() as $m) {
			$deleted += $m->whereStructure($structureSchool)->delete();
		}

		// Les ventes ne sont pas copiées mais fabriquées : leur package les efface.
		$deleted += array_sum(self::notify('clearSchool', $structureSchool));

		return $deleted;

	}

	/**
	 * Recopie les modules de la ferme source dans la ferme cible.
	 *
	 * `$seasons` borne les tables à saison, `NULL` les copie toutes : la ferme école copie un
	 * intervalle, la duplication toute l'histoire de la ferme.
	 *
	 * `$conditions` borne un module au-delà de sa saison, par un fragment SQL qui commence par
	 * ` AND ` et lit la ligne sous l'alias `m1` : la duplication de la seule commercialisation n'y
	 * prend que les espèces que ses produits et ses rapports nomment.
	 */
	public static function copy(array $modules, int $structureSource, int $structureSchool, array $users, int $offset, ?array $seasons, array $conditions): int {

		$copied = 0;

		foreach($modules as $module => $m) {

			[$select, $joins] = self::getColumns($m, $modules, $structureSource, $structureSchool, $users, $offset);

			$pdo = $m->pdo();
			$table = self::getTable($m);

			$columns = [];

			foreach(array_keys($select) as $property) {
				$columns[] = $pdo->api->field($property);
			}

			$sql = 'INSERT INTO '.$table.' ('.implode(', ', $columns).')'
				.' SELECT '.implode(', ', $select)
				.' FROM '.$table.' m1 '.implode(' ', $joins)
				.' WHERE m1.'.$pdo->api->field('structure').' = '.$structureSource
				.self::getSeasonCondition($m, $seasons, $modules, $structureSource)
				.($conditions[$module] ?? '');

			$copied += $pdo->exec($sql);

		}

		return $copied;

	}

	/**
	 * La borne de saison, pour les tables qui en portent une.
	 *
	 * ⚠️ Le test est l'existence de la colonne et non une liste de tables écrite ici : une liste à
	 * la main oublierait la table qu'un futur `.yml` ajoute, et l'oubli ne se verrait pas — la
	 * copie rendrait simplement plus de lignes que l'intervalle, ce dont aucun contrôle ne
	 * s'émeut. `clear()` efface toute la structure, donc plus large que ce qu'on recopie : une
	 * borne resserrée ne laisse jamais de survivant du passage précédent.
	 */
	private static function getSeasonCondition(\ModuleModel $m, ?array $seasons, array $modules, int $structureSource): string {

		if($seasons === NULL) {
			return '';
		}

		if(in_array('season', $m->getProperties(), TRUE) === FALSE) {
			return self::getSeasonConditionInherited($m, $seasons, $modules, $structureSource);
		}

		[$seasonFirst, $seasonLast] = $seasons;

		$field = 'm1.'.$m->pdo()->api->field('season');

		// ⚠️ `IS NULL` explicitement : une saison nulle n'est pas une saison hors bornes, c'est
		// une ligne qui n'appartient à aucune saison — 9 196 des 21 688 interventions de la ferme
		// source sont dans ce cas. `BETWEEN` les écarte sans un mot, et le seul symptôme est une
		// clé étrangère rompue trois tables plus loin : les 27 381 pointages orphelins que le
		// contrôle d'intégrité a rendus au premier essai.
		return ' AND ('.$field.' IS NULL OR '.$field.' BETWEEN '.$seasonFirst.' AND '.$seasonLast.')';

	}

	/**
	 * La borne héritée : un module sans saison suit celle de ce qu'il référence.
	 *
	 * ⚠️ Sans elle, la borne coupe les séries de 2027 et **laisse leurs tranches** : mesuré le
	 * 2026-09-05 sur la seconde ferme école, 6 `series\Slice` pointaient sur des séries jamais
	 * copiées. Le contrôle d'intégrité l'a dit, rien d'autre ne l'aurait fait — la copie n'aurait
	 * levé aucune erreur, elle aurait juste porté des lignes orphelines.
	 *
	 * La liste ne s'écrit pas à la main : elle se dérive des clés étrangères **non nullables** qui
	 * visent un module portant une saison. Six modules sont dans ce cas aujourd'hui, et celui
	 * qu'un futur `.yml` ajoutera y entrera tout seul. Une clé nullable ne borne rien : sa ligne
	 * peut légitimement ne rattacher à rien.
	 */
	private static function getSeasonConditionInherited(\ModuleModel $m, ?array $seasons, array $modules, int $structureSource): string {

		$conditions = '';

		foreach($m->getProperties() as $property) {

			$target = $m->getPropertyToModule($property);

			if(
				$target === NULL or
				$m->isPropertyNull($property) or
				array_key_exists($target, $modules) === FALSE or
				in_array('season', $modules[$target]->getProperties(), TRUE) === FALSE
			) {
				continue;
			}

			$mModuleTarget = $modules[$target];
			$pdo = $mModuleTarget->pdo();

			$conditions .= ' AND m1.'.$m->pdo()->api->field($property).' IN ('
				.'SELECT '.$pdo->api->field('id').' FROM '.self::getTable($mModuleTarget)
				.' WHERE '.$pdo->api->field('structure').' = '.$structureSource
				.str_replace('m1.', '', self::getSeasonCondition($mModuleTarget, $seasons, $modules, $structureSource))
				.')';

		}

		return $conditions;

	}

	/**
	 * Le point conventionnel où atterrit toute ferme école, en (longitude, latitude).
	 *
	 * Des zéros et un rond : aucune ferme réelle ne se trouve à exactement 45,000000 N. C'est
	 * délibéré — une position **visiblement conventionnelle** vaut mieux qu'un décalage aléatoire,
	 * qui fabriquerait une position fausse mais crédible, et donc trompeuse pour qui la lit.
	 */
	const PLACE = [0.0, 45.0];

	/**
	 * Les colonnes de géométrie des modules copiés, chacune avec son type, qui dit comment la lire et l'écrire.
	 *
	 * ⚠️ **Le WKT range ses axes dans l'ordre inverse de `PLACE`** : celui que rend `ST_AsText` sur un
	 * `polygon` SRID 4326, mesuré sur la ferme 7, commence par `POLYGON((45.664014 3.113626`, donc
	 * (latitude, longitude), l'ordre interne du SRID, que suit aussi un `point` (`encodePoint()`,
	 * `MySQLApi.php`). `place()` calcule en (longitude, latitude), comme le GeoJSON que la carte poste :
	 * un couple lu sans être retourné déplacerait sa forme de 50 km.
	 *
	 * `place()` ne sait déplacer que ces deux types, et seule la ferme école l'appelle : la ferme
	 * dupliquée garde ses positions, la duplication étant consentie. Une colonne de géométrie absente
	 * de cette liste garderait dans la ferme école la position réelle de sa source :
	 * `DuplicateSentinelAi` exige que toute colonne `point` ou `polygon` d'un module copié y figure.
	 */
	const GEOMETRY = [
		'map\Draw' => ['corner' => 'point'],
		'map\Plot' => ['coordinates' => 'polygon'],
		'map\Zone' => ['coordinates' => 'polygon'],
	];

	/**
	 * Recentre la géométrie de la ferme école sur le point conventionnel, formes conservées.
	 *
	 * Le plan est le support pédagogique du maraîchage, et ce qu'un étudiant y lit ce sont des
	 * formes, des surfaces et des rotations — jamais une position absolue. On garde donc les
	 * positions relatives et on déplace l'ensemble d'un seul vecteur.
	 *
	 * Risque résiduel assumé : la forme d'un parcellaire reste comparable à une image satellite.
	 * L'exploiter demande un effort réel et ciblé, et on l'accepte plutôt que de retirer le
	 * support d'enseignement.
	 */
	public static function place(int $structureSchool): array {

		[$sumLng, $sumLat, $count] = [0.0, 0.0, 0];

		$shapes = [];

		foreach(self::GEOMETRY as $module => $properties) {

			$m = $module::model();
			$pdo = $m->pdo();

			foreach($properties as $property => $format) {

				$field = $pdo->api->field($property);

				foreach($pdo->select('SELECT id, ST_AsText('.$field.') AS value FROM '.self::getTable($m).' WHERE structure = '.$structureSchool.' AND '.$field.' IS NOT NULL') as $row) {

					// Tous les couples en (longitude, latitude), le WKT étant écrit dans l'ordre inverse.
					$couples = match($format) {
						'polygon' => array_map(fn(array $couple) => [$couple[1], $couple[0]], self::readWkt($row['value'])),
						'point' => [array_reverse(array_map('floatval', preg_split('/\s+/', trim($row['value'], 'POINT()'))))],
					};

					foreach($couples as [$lng, $lat]) {
						$sumLng += $lng;
						$sumLat += $lat;
						$count++;
					}

					$shapes[] = [$m, $field, $format, $row['id'], $couples];

				}

			}

		}

		if($count === 0) {
			return ['points' => 0];
		}

		$deltaLng = self::PLACE[0] - $sumLng / $count;
		$deltaLat = self::PLACE[1] - $sumLat / $count;

		foreach($shapes as [$m, $field, $format, $id, $couples]) {

			$pdo = $m->pdo();

			$moved = [];

			foreach($couples as [$lng, $lat]) {
				$moved[] = [$lng + $deltaLng, $lat + $deltaLat];
			}

			$value = match($format) {
				'polygon' => 'ST_GeomFromText('.$pdo->quote('POLYGON(('.implode(',', array_map(fn(array $couple) => $couple[1].' '.$couple[0], $moved)).'))').', 4326)',
				'point' => $pdo->api->encodePoint($moved[0]),
			};

			$pdo->exec('UPDATE '.self::getTable($m).' SET '.$field.' = '.$value.' WHERE id = '.$id);

		}

		return ['points' => $count, 'deltaLng' => $deltaLng, 'deltaLat' => $deltaLat];

	}

	/** Les couples d'un POLYGON WKT, dans l'ordre où ils y sont écrits. */
	private static function readWkt(string $wkt): array {

		preg_match('/\(\((.*)\)\)/', $wkt, $match);

		$couples = [];

		foreach(explode(',', $match[1] ?? '') as $couple) {

			[$a, $b] = array_map('floatval', preg_split('/\s+/', trim($couple)));

			$couples[] = [$a, $b];

		}

		return $couples;

	}

	/**
	 * Les fonctionnalités qu'une ferme école porte, et par omission celles qu'elle ne porte pas.
	 *
	 * ⚠️ La liste est **positive et exhaustive** : tout ce qui n'y figure pas est remis à zéro à
	 * chaque passage, colonne ajoutée demain comprise. C'est le bon sens de l'omission pour une
	 * ferme en lecture seule — une fonctionnalité qu'on n'a pas pensé à classer reste fermée au
	 * lieu de s'ouvrir sur des écrans que la copie ne remplit pas.
	 *
	 * Elles ne s'alignent pas sur la source, qui a tout activé : elles disent ce que la copie
	 * produit réellement. La production est copiée, les ventes sont agrégées ; il n'y a ni
	 * facture, ni boutique, ni stock, ni comptabilité, et les relevés de prix ne coteraient que
	 * des ventes de synthèse.
	 *
	 * Mesuré le 2026-09-05 : sans elles, `canAnalyzeCommercialisation()` rendait bien TRUE mais
	 * l'onglet Analyse n'avait aucune rubrique — les trois dépendent de `hasSales` — et
	 * s'affichait grisé, un droit ouvert sur une page vide.
	 */
	const FEATURE_ON = [
		'production',
		'hasCultivations',
		'hasSales',
	];

	/**
	 * Ce qui ne vient ni du défaut ni de la source, mais du réglage : les saisons.
	 *
	 * Troisième catégorie et non un rangement dans `CONFIGURATION_KEEP` : « gardé » veut dire
	 * qu'on n'y touche pas, et ces deux-là sont écrites à chaque passage. Les confondre ferait
	 * mentir la seule chose que ces listes servent à dire — qui décide de la valeur.
	 *
	 * L'intervalle vit dans `StructureSetting::SCHOOL_STRUCTURES` parce qu'il borne aussi la
	 * copie : le sélecteur de saison de l'écran doit proposer exactement les saisons copiées, et
	 * deux endroits qui décideraient de la même borne divergeraient au premier changement.
	 *
	 * Elles s'écrivent dans `map\Configuration`, que la copie emporte avec les autres réglages de
	 * culture de la source.
	 */
	const CONFIGURATION_DECLARED = [
		'seasonFirst', 'seasonLast',
	];

	/**
	 * Ce qui, dans la configuration, **décrit le système** de la ferme et s'aligne sur la source.
	 *
	 * Une ferme école est créée comme une structure neuve : sa configuration porte des valeurs par
	 * défaut qui décrivent **une autre ferme que sa source**. Les saisons ont mordu les premières
	 * parce qu'elles vident l'écran ; les autres mentent en silence, ce qui est pire — une source
	 * qui tourne en six ans affichée en quatre raconte une autre rotation, et rien ne le signale.
	 *
	 * Le partage : ce qui fait partie de ce qu'on vient étudier s'aligne ; ce qui relève de
	 * l'identité, des documents ou du paramétrage de service reste au défaut, parce que cela
	 * appartient à la ferme réelle et n'a rien à faire dans une copie anonymisée — un préfixe de
	 * facture ou un pied d'e-mail la nommerait.
	 *
	 * La configuration des ventes tient ses propres listes (`selling\ConfigurationLib::PROPERTIES_ALIGN`
	 * et `PROPERTIES_KEEP`), alignées par l'événement `alignConfiguration`.
	 */
	const CONFIGURATION_ALIGN = [
		'time', 'timeRange', 'timeReferenceMonth', 'timePeriodDay',
		'hasVat', 'vatChargeability', 'vatDebitsOption',
	];

	/**
	 * Ce qui reste au défaut : identité, documents, services.
	 *
	 * ⚠️ Cette liste n'existe pas pour être lue mais pour être **exhaustive** : `checkConfiguration()`
	 * refuse toute colonne qui ne figure ni ici ni dans `CONFIGURATION_ALIGN`. La classification ne
	 * se dérive pas du schéma — rien n'y distingue une rotation d'un préfixe de facture — alors
	 * c'est le contrôle qui tient la complétude à la place de la vigilance. Une colonne ajoutée
	 * dans six mois fait échouer le script tant que personne ne l'a rangée, au lieu de retomber
	 * silencieusement au défaut ou d'emporter une mention légale.
	 */
	const CONFIGURATION_KEEP = [
		'id', 'structure',
		'vatNumber', 'organicCertifier',
		'emailBanner', 'emailFooter',
	];

	/**
	 * Toute colonne de configuration est classée, sinon le script refuse de tourner.
	 *
	 * Rend la liste des colonnes orphelines, vide quand tout est rangé.
	 */
	public static function checkConfiguration(): array {

		$mConfiguration = \structure\Configuration::model();

		$known = array_merge(self::CONFIGURATION_ALIGN, self::CONFIGURATION_KEEP);
		$orphans = [];

		foreach($mConfiguration->pdo()->manager->getFields($mConfiguration->getDatabase(), $mConfiguration->getTable()) as $field) {

			if(in_array($field['name'], $known) === FALSE) {
				$orphans[] = $field['name'];
			}

		}

		return $orphans;

	}

	/**
	 * Aligne les saisons déclarées de la ferme école sur celles de sa source.
	 *
	 * ⚠️ Sans cela l'assolement s'ouvre sur une saison **vide** : la configuration d'une ferme
	 * école neuve porte l'année en cours et la suivante, tandis que ses séries copiées couvrent
	 * celles de la source — mesuré, 2026-2027 déclarées contre 2021-2027 réelles. Les onglets de
	 * saison masquaient donc cinq années de données et en proposaient une qui n'en avait aucune.
	 *
	 * C'est la configuration et non les séries qui fait foi pour le produit : elle borne les
	 * onglets, la saison par défaut et les exports.
	 */
	public static function placeConfiguration(int $structureSource, int $structureSchool, array $seasons): array {

		$orphans = self::checkConfiguration();

		if($orphans !== []) {
			throw new \Exception("Colonnes de configuration non classees (".implode(', ', $orphans)."), a ranger dans CONFIGURATION_ALIGN ou CONFIGURATION_KEEP");
		}

		$mConfiguration = \structure\Configuration::model();

		// ⚠️ Les saisons ne s'alignent plus sur la source : c'est l'intervalle déclaré qui décide
		// de ce qui est copié, et le sélecteur de saison de l'écran doit proposer exactement ce
		// qui existe. Alignées sur la source, elles offriraient des saisons vides à qui les choisit.
		\map\ConfigurationLib::updateProperties(new Structure(['id' => $structureSchool]), array_combine(self::CONFIGURATION_DECLARED, $seasons));

		$properties = self::CONFIGURATION_ALIGN;

		$eConfigurationSource = $mConfiguration->select(array_merge(['id'], $properties))->whereStructure($structureSource)->get();
		$eConfigurationSchool = $mConfiguration->select(array_merge(['id'], $properties))->whereStructure($structureSchool)->get();

		$changed = self::placeCountry($structureSource, $structureSchool);

		foreach($properties as $property) {

			if($eConfigurationSchool[$property] !== $eConfigurationSource[$property]) {
				$changed[$property] = $eConfigurationSource[$property];
			}

			$eConfigurationSchool[$property] = $eConfigurationSource[$property];

		}

		ConfigurationLib::updateProperties(new Structure(['id' => $structureSchool]), $eConfigurationSchool->extracts($properties));

		return array_merge($changed, self::alignConfiguration($structureSource, $structureSchool));

	}

	/**
	 * La configuration des ventes, alignée sur la source par son package, absent de la version
	 * publique. Rend les valeurs changées, comme `placeConfiguration()`. Partagée avec la duplication
	 * d'une ferme (`DuplicateAiLib::placeConfiguration()`).
	 */
	public static function alignConfiguration(int $structureSource, int $structure): array {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		$eStructureSource = new Structure(['id' => $structureSource]);
		$eStructure = new Structure(['id' => $structure]);

		return array_merge([], ...self::notify('alignConfiguration', $eStructureSource, $eStructure));

	}

	/**
	 * Le pays de la source : les taux de TVA se numérotent par pays (`ProductSetting::getVatRates()`),
	 * donc `defaultVat` et les taux des produits copiés ne désignent rien sans lui.
	 *
	 * ⚠️ À poser avant l'alignement de la configuration : `StructureLib::update()` coupe `hasVat`
	 * pour un pays sans TVA, et c'est la valeur de la source qui doit être écrite en dernier.
	 */
	public static function placeCountry(int $structureSource, int $structureSchool): array {

		$cStructure = Structure::model()
			->select(['id', 'legalCountry'])
			->whereId('IN', [$structureSource, $structureSchool])
			->getCollection(index: 'id');

		$eStructureSource = $cStructure[$structureSource];
		$eStructureSchool = $cStructure[$structureSchool];

		if($eStructureSchool['legalCountry']->is($eStructureSource['legalCountry'])) {
			return [];
		}

		$eStructureSchool['legalCountry'] = $eStructureSource['legalCountry'];

		StructureLib::update($eStructureSchool, ['legalCountry']);

		return ['legalCountry' => $eStructureSource['legalCountry']];

	}

	/**
	 * La configuration de la ferme école décrit-elle bien le système de sa source.
	 *
	 * ⚠️ Le contrôle porte sur les colonnes **dérivées de `CONFIGURATION_ALIGN`**, les mêmes que
	 * l'alignement : une liste écrite à part oublierait exactement ce que l'alignement oublie.
	 * Rend les écarts, vide quand les deux concordent.
	 */
	public static function checkConfigurationAligned(int $structureSource, int $structureSchool): array {

		$mConfiguration = \structure\Configuration::model();
		$properties = array_merge(['id'], self::CONFIGURATION_ALIGN);

		$eConfigurationSource = $mConfiguration->select($properties)->whereStructure($structureSource)->get();
		$eConfigurationSchool = $mConfiguration->select($properties)->whereStructure($structureSchool)->get();

		$errors = [];

		foreach(self::CONFIGURATION_ALIGN as $property) {

			if($eConfigurationSource[$property] !== $eConfigurationSchool[$property]) {
				$errors[] = $property.' : source '.var_export($eConfigurationSource[$property], TRUE).', ecole '.var_export($eConfigurationSchool[$property], TRUE);
			}

		}

		$cStructure = Structure::model()
			->select(['id', 'legalCountry'])
			->whereId('IN', [$structureSource, $structureSchool])
			->getCollection(index: 'id');

		$eCountrySource = $cStructure[$structureSource]['legalCountry'];
		$eCountrySchool = $cStructure[$structureSchool]['legalCountry'];

		if($eCountrySource->is($eCountrySchool) === FALSE) {
			$errors[] = 'legalCountry : source '.($eCountrySource->empty() ? 'aucun' : $eCountrySource['id']).', ecole '.($eCountrySchool->empty() ? 'aucun' : $eCountrySchool['id']);
		}

		if(StructureSetting::hasCommercialisationApp()) {

			$eStructureSource = $cStructure[$structureSource];
			$eStructureSchool = $cStructure[$structureSchool];

			$errors = array_merge($errors, ...self::notify('checkConfigurationAligned', $eStructureSource, $eStructureSchool));

		}

		return $errors;

	}

	/**
	 * Chaque département, avec sa préfecture en **(longitude, latitude)** — l'ordre de PHP.
	 *
	 * ⚠️ Cette table vit dans le script et non dans le modèle : c'est un besoin de l'anonymisation,
	 * pas une notion du produit, qui ignore complètement les départements — ni table, ni colonne,
	 * ni helper.
	 *
	 * La préfecture plutôt que le centre géométrique : le point posé ici est le seul repère que le
	 * plan donne, et une ville se reconnaît là où un champ au milieu du département ne dit rien.
	 * L'écart entre les deux est de 16 km en médiane, mais de 142 km en Guyane, dont le centre
	 * tombe en pleine forêt quand Cayenne est sur la côte.
	 *
	 * ⚠️ Vérifiée avant emploi contre une donnée indépendante : 17 préfectures hébergent une ferme
	 * de la base qui déclare la même commune, et l'écart avec la coordonnée écrite ici est de
	 * **0,4 km en médiane, 4,6 km au pire**. Une coordonnée posée en pleine mer ne lèverait aucune
	 * erreur, seule une mesure pouvait le dire.
	 */
	const DEPARTMENTS = [
		'01' => ["Ain", "Bourg-en-Bresse", 5.226, 46.205],
		'02' => ["Aisne", "Laon", 3.624, 49.564],
		'03' => ["Allier", "Moulins", 3.333, 46.565],
		'04' => ["Alpes-de-Haute-Provence", "Digne-les-Bains", 6.234, 44.092],
		'05' => ["Hautes-Alpes", "Gap", 6.079, 44.559],
		'06' => ["Alpes-Maritimes", "Nice", 7.262, 43.703],
		'07' => ["Ardèche", "Privas", 4.599, 44.735],
		'08' => ["Ardennes", "Charleville-Mézières", 4.723, 49.773],
		'09' => ["Ariège", "Foix", 1.605, 42.965],
		'10' => ["Aube", "Troyes", 4.075, 48.297],
		'11' => ["Aude", "Carcassonne", 2.353, 43.213],
		'12' => ["Aveyron", "Rodez", 2.575, 44.35],
		'13' => ["Bouches-du-Rhône", "Marseille", 5.37, 43.296],
		'14' => ["Calvados", "Caen", -0.37, 49.183],
		'15' => ["Cantal", "Aurillac", 2.446, 44.926],
		'16' => ["Charente", "Angoulême", 0.156, 45.649],
		'17' => ["Charente-Maritime", "La Rochelle", -1.152, 46.16],
		'18' => ["Cher", "Bourges", 2.396, 47.081],
		'19' => ["Corrèze", "Tulle", 1.771, 45.267],
		'21' => ["Côte-d'Or", "Dijon", 5.041, 47.322],
		'22' => ["Côtes-d'Armor", "Saint-Brieuc", -2.765, 48.514],
		'23' => ["Creuse", "Guéret", 1.87, 46.17],
		'24' => ["Dordogne", "Périgueux", 0.718, 45.184],
		'25' => ["Doubs", "Besançon", 6.024, 47.238],
		'26' => ["Drôme", "Valence", 4.892, 44.933],
		'27' => ["Eure", "Évreux", 1.151, 49.024],
		'28' => ["Eure-et-Loir", "Chartres", 1.489, 48.444],
		'29' => ["Finistère", "Quimper", -4.098, 47.996],
		'2A' => ["Corse-du-Sud", "Ajaccio", 8.738, 41.927],
		'2B' => ["Haute-Corse", "Bastia", 9.45, 42.701],
		'30' => ["Gard", "Nîmes", 4.36, 43.837],
		'31' => ["Haute-Garonne", "Toulouse", 1.444, 43.604],
		'32' => ["Gers", "Auch", 0.586, 43.646],
		'33' => ["Gironde", "Bordeaux", -0.578, 44.838],
		'34' => ["Hérault", "Montpellier", 3.877, 43.611],
		'35' => ["Ille-et-Vilaine", "Rennes", -1.68, 48.117],
		'36' => ["Indre", "Châteauroux", 1.694, 46.811],
		'37' => ["Indre-et-Loire", "Tours", 0.69, 47.394],
		'38' => ["Isère", "Grenoble", 5.735, 45.188],
		'39' => ["Jura", "Lons-le-Saunier", 5.554, 46.674],
		'40' => ["Landes", "Mont-de-Marsan", -0.499, 43.89],
		'41' => ["Loir-et-Cher", "Blois", 1.334, 47.586],
		'42' => ["Loire", "Saint-Étienne", 4.387, 45.439],
		'43' => ["Haute-Loire", "Le Puy-en-Velay", 3.884, 45.043],
		'44' => ["Loire-Atlantique", "Nantes", -1.553, 47.218],
		'45' => ["Loiret", "Orléans", 1.909, 47.902],
		'46' => ["Lot", "Cahors", 1.441, 44.448],
		'47' => ["Lot-et-Garonne", "Agen", 0.617, 44.203],
		'48' => ["Lozère", "Mende", 3.5, 44.518],
		'49' => ["Maine-et-Loire", "Angers", -0.563, 47.472],
		'50' => ["Manche", "Saint-Lô", -1.09, 49.115],
		'51' => ["Marne", "Châlons-en-Champagne", 4.365, 48.957],
		'52' => ["Haute-Marne", "Chaumont", 5.139, 48.111],
		'53' => ["Mayenne", "Laval", -0.77, 48.07],
		'54' => ["Meurthe-et-Moselle", "Nancy", 6.184, 48.692],
		'55' => ["Meuse", "Bar-le-Duc", 5.161, 48.771],
		'56' => ["Morbihan", "Vannes", -2.76, 47.658],
		'57' => ["Moselle", "Metz", 6.176, 49.12],
		'58' => ["Nièvre", "Nevers", 3.157, 46.99],
		'59' => ["Nord", "Lille", 3.058, 50.629],
		'60' => ["Oise", "Beauvais", 2.081, 49.43],
		'61' => ["Orne", "Alençon", 0.093, 48.432],
		'62' => ["Pas-de-Calais", "Arras", 2.78, 50.291],
		'63' => ["Puy-de-Dôme", "Clermont-Ferrand", 3.087, 45.777],
		'64' => ["Pyrénées-Atlantiques", "Pau", -0.37, 43.295],
		'65' => ["Hautes-Pyrénées", "Tarbes", 0.073, 43.233],
		'66' => ["Pyrénées-Orientales", "Perpignan", 2.895, 42.699],
		'67' => ["Bas-Rhin", "Strasbourg", 7.751, 48.573],
		'68' => ["Haut-Rhin", "Colmar", 7.358, 48.079],
		'69' => ["Rhône", "Lyon", 4.835, 45.764],
		'70' => ["Haute-Saône", "Vesoul", 6.156, 47.622],
		'71' => ["Saône-et-Loire", "Mâcon", 4.833, 46.307],
		'72' => ["Sarthe", "Le Mans", 0.199, 48.007],
		'73' => ["Savoie", "Chambéry", 5.918, 45.565],
		'74' => ["Haute-Savoie", "Annecy", 6.129, 45.899],
		'75' => ["Paris", "Paris", 2.349, 48.857],
		'76' => ["Seine-Maritime", "Rouen", 1.099, 49.443],
		'77' => ["Seine-et-Marne", "Melun", 2.658, 48.542],
		'78' => ["Yvelines", "Versailles", 2.13, 48.802],
		'79' => ["Deux-Sèvres", "Niort", -0.459, 46.324],
		'80' => ["Somme", "Amiens", 2.296, 49.895],
		'81' => ["Tarn", "Albi", 2.148, 43.928],
		'82' => ["Tarn-et-Garonne", "Montauban", 1.355, 44.018],
		'83' => ["Var", "Toulon", 5.93, 43.125],
		'84' => ["Vaucluse", "Avignon", 4.805, 43.95],
		'85' => ["Vendée", "La Roche-sur-Yon", -1.427, 46.67],
		'86' => ["Vienne", "Poitiers", 0.34, 46.581],
		'87' => ["Haute-Vienne", "Limoges", 1.262, 45.833],
		'88' => ["Vosges", "Épinal", 6.451, 48.174],
		'89' => ["Yonne", "Auxerre", 3.573, 47.798],
		'90' => ["Territoire de Belfort", "Belfort", 6.864, 47.638],
		'91' => ["Essonne", "Évry", 2.441, 48.629],
		'92' => ["Hauts-de-Seine", "Nanterre", 2.207, 48.892],
		'93' => ["Seine-Saint-Denis", "Bobigny", 2.44, 48.906],
		'94' => ["Val-de-Marne", "Créteil", 2.455, 48.79],
		'95' => ["Val-d'Oise", "Pontoise", 2.101, 49.05],
		'971' => ["Guadeloupe", "Basse-Terre", -61.726, 16.0],
		'972' => ["Martinique", "Fort-de-France", -61.07, 14.605],
		'973' => ["Guyane", "Cayenne", -52.334, 4.937],
		'974' => ["La Réunion", "Saint-Denis", 55.45, -20.882],
		'976' => ["Mayotte", "Mamoudzou", 45.228, -12.78],
	];

	const SYSTEM_KEEP = ['place', 'placeLngLat', 'placeCity', 'placeDepartment', 'placeEpci'];

	/**
	 * Jusqu'où vont les ventes d'une ferme école, et seulement si elles sont **coupées**.
	 *
	 * ⚠️ Une troncature muette est une donnée fausse : un lecteur qui ne voit rien après 2023 ne
	 * conclut pas « les données s'arrêtent là », il conclut « cette ferme n'a rien vendu en 2024 ».
	 * Une ferme école est lue par quelqu'un qui ne peut rien vérifier — ce qu'elle ne montre pas
	 * doit être annoncé.
	 *
	 * La borne n'est pas enregistrée : elle se passe à chaque lancement, parce qu'une ferme école
	 * n'a pas « une date d'arrêt des ventes » comme attribut, elle a une copie faite avec certains
	 * paramètres. La date se **dérive** donc de ce qui est là — mieux que la stocker sous tous les
	 * rapports : aucun schéma à toucher, et jamais faux puisque c'est ce que la ferme porte
	 * vraiment.
	 *
	 * Rend NULL quand rien n'est coupé, c'est-à-dire quand la ferme école va aussi loin que sa
	 * source : la mention n'apprendrait alors rien.
	 */
	public static function getSalesUntil(int $structureSchool, int $structureSource): ?string {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return NULL;
		}

		return self::notify('getSchoolSalesUntil', $structureSchool, $structureSource)[0];

	}

	/** Les saisons déclarées d'une structure, une fois la configuration alignée. */
	public static function getSeasons(int $structure): array {

		$settings = \structure\StructureSetting::SCHOOL_STRUCTURES[$structure] ?? NULL;

		return ($settings === NULL) ? [] : [$settings['seasonFirst'], $settings['seasonLast']];

	}

	/**
	 * Pose les fonctionnalités de la ferme école d'après ce que la copie contient.
	 *
	 * Écrit par le modèle comme le reste du script, donc sans passer par `FeatureLib` : c'est ce
	 * qui permet d'écrire dans une ferme dont `acceptWrite()` refuse l'écriture.
	 */
	public static function placeFeature(int $structureSchool): array {

		$mFeature = \structure\Feature::model();

		$properties = array_values(array_diff($mFeature->getProperties(), ['id', 'structure']));

		$eFeature = $mFeature->select(array_merge(['id'], $properties))->whereStructure($structureSchool)->get();

		$on = [];

		foreach($properties as $property) {

			$eFeature[$property] = in_array($property, self::FEATURE_ON, TRUE);

			if($eFeature[$property]) {
				$on[] = $property;
			}

		}

		$mFeature->select($properties)->update($eFeature);

		\structure\FeatureLib::forget(new \structure\Structure(['id' => $structureSchool]));

		return $on;

	}

	/**
	 * Le département d'un code postal, ou NULL si on ne peut pas le dire.
	 *
	 * ⚠️ Ce n'est pas `substr($postcode, 0, 2)`, et l'écart n'est pas théorique : la Corse porte
	 * `2A` et `2B` sur des codes en `20…`, l'outre-mer tient sur trois chiffres. Les deux premiers
	 * caractères rendraient « 20 » et « 97 », deux départements qui n'existent pas — un centre
	 * faux plutôt qu'un refus.
	 *
	 * ⚠️ Le partage corse suit la règle usuelle des plages postales (`200xx`/`201xx` en
	 * Corse-du-Sud, au-delà en Haute-Corse). Elle souffre quelques exceptions communales ; à
	 * l'échelle d'un centre départemental employé pour brouiller une position, l'écart est sans
	 * portée — les deux centres sont distants de 70 km sur une île qui en fait 180.
	 */
	public static function getDepartment(?string $postcode): ?string {

		$postcode = ($postcode === NULL) ? '' : trim($postcode);

		if(preg_match('/^\d{5}$/', $postcode) === 0) {
			return NULL;
		}

		$department = match(TRUE) {
			str_starts_with($postcode, '97') => substr($postcode, 0, 3),
			str_starts_with($postcode, '20') => ((int)substr($postcode, 0, 3) < 202) ? '2A' : '2B',
			default => substr($postcode, 0, 2)
		};

		return array_key_exists($department, self::DEPARTMENTS) ? $department : NULL;

	}

	/**
	 * Pose la ferme école au centre du département de sa source.
	 *
	 * ⚠️ Le libellé suit les coordonnées : `place` porte le **nom du département** et jamais une
	 * commune. Un point sans nom, ou pire un nom de commune qui n'est pas la sienne, mentirait
	 * davantage que l'absence — et c'est le libellé que la carte de la fiche affiche en tête.
	 *
	 * Le département est celui du lieu de production de la source quand il est connu, sinon celui
	 * de son siège.
	 *
	 * ⚠️ Sans l'un ni l'autre, on **refuse** plutôt que d'inventer un centre par défaut :
	 * la ferme école retombe alors sur le cas sans localisation, que la fiche traite déjà en
	 * retirant sa carte.
	 *
	 * ⚠️ Le point s'écrit en **(longitude, latitude)**, l'ordre de PHP :
	 * `MySQLApi::encodePoint()` (`framework/core/Database/drivers/MySQL/MySQLApi.php:46`) écrit
	 * `POINT($point[1] $point[0])` et inverse donc lui-même pour l'ordre interne du SRID 4326.
	 * Une table écrite dans le bon sens et posée dans le mauvais mettrait les fermes en pleine mer
	 * sans qu'aucune erreur ne se lève : la vérification est une distance connue, jamais une
	 * lecture de code.
	 *
	 * Effet de bord assumé et bon : la ferme école entre dans le voisinage des fermes de son
	 * département, à l'échelle du département — c'est-à-dire à l'échelle où elle est vraie.
	 */
	/**
	 * Pose le nom et le logo de la ferme école, déclarés dans le réglage.
	 *
	 * ⚠️ Ni l'un ni l'autre ne se copient de la source : ce serait rendre à une copie anonymisée
	 * l'identité même qu'on lui retire. Ils ne se laissent pas non plus au défaut — une ferme sans
	 * nom ni logo ne ressemble pas à une ferme, et l'écran qui la liste met alors un carré vide à
	 * côté de fermes qui en ont un.
	 *
	 * ⚠️ Le logo passe par `media\StructureLogoLib`, seul chemin qui écrive la colonne `logo` :
	 * elle porte une référence de média téléversé et non un chemin de fichier, si bien qu'y ranger
	 * `main/image/bubble/…` à la main ne montrerait rien.
	 *
	 * Il n'est téléversé **que s'il manque** : le remplacer à chaque passage réencoderait la même
	 * image tous les jours pour rien. Le nom, lui, est réécrit à chaque fois — il ne coûte rien et
	 * la ferme école n'a pas à garder un nom que quelqu'un lui aurait donné.
	 */
	/**
	 * Crée la ferme école si elle n'existe pas, à l'identifiant que le réglage lui donne.
	 *
	 * ⚠️ Tout ce que `StructureLib::create()` fait, **sauf deux choses**. Pas de `createOwner()` :
	 * une ferme école n'a ni membre ni propriétaire, c'est ce qui la définit. Et pas de
	 * `createStructure()`, qui duplique les actions et les espèces par défaut — la copie les
	 * apporte depuis la source et les effacerait juste après.
	 *
	 * ⚠️ L'identifiant est **forcé** : il est réservé dans `StructureSetting::SCHOOL_STRUCTURES`,
	 * et c'est lui qui ouvre l'accès partout. Le laisser à l'auto-incrément rendrait la ferme
	 * invisible de tous les écrans, sans la moindre erreur.
	 *
	 * Sans cette méthode, ajouter une ferme école demandait un rituel manuel — une ligne posée à
	 * la main puis six lignes satellites — que personne n'aurait refait à l'identique en
	 * production.
	 */
	public static function createSchool(int $structureSchool, array $settings): bool {

		$mStructure = Structure::model();

		if($mStructure->select(['id'])->whereId($structureSchool)->get()->notEmpty()) {
			return FALSE;
		}

		$eStructure = new Structure([
			'id' => $structureSchool,
			'name' => $settings['name'],
			'type' => Structure::PRODUCER,
			'producerType' => Structure::SCHOOL,
			'source' => new Structure(['id' => $settings['source']]),
			// Le pays de la source dès l'insertion : la configuration des ventes, posée par
			// `createStructure`, en tire le taux de TVA par défaut, et `placeCountry()` en dit la raison.
			'legalCountry' => Structure::model()->select(['legalCountry'])->whereId($settings['source'])->get()['legalCountry'],
			// ⚠️ `legalEmail` n'est pas nullable, et une ferme école n'a personne à qui écrire :
			// une adresse de domaine réservé plutôt qu'une adresse vide ou celle de la source, que
			// la copie existe justement pour taire. `example.org` ne reçoit rien, par convention.
			'legalEmail' => 'ferme-ecole@example.org',
			'status' => Structure::ACTIVE,
			'createdAt' => new \Sql('NOW()'),
		]);

		$mStructure->beginTransaction();

			$mStructure->insert($eStructure);

			ConfigurationLib::createForStructure($eStructure);
			\product\ConfigurationLib::createForStructure($eStructure);
			FeatureLib::createForStructure($eStructure);
			SystemLib::createForStructure($eStructure);
			RoleLib::createForStructure($eStructure);
			CommunityLib::createForStructure($eStructure);

			self::notify('createStructure', $eStructure);

		$mStructure->commit();

		return TRUE;

	}

	public static function placeIdentity(int $structureSchool, array $settings): array {

		$done = [];

		$mStructure = Structure::model();
		$eStructure = $mStructure->select(['id', 'name', 'logo'])->whereId($structureSchool)->get();

		if($eStructure['name'] !== $settings['name']) {

			$eStructure['name'] = $settings['name'];
			$mStructure->select(['name'])->update($eStructure);

			$done['nom'] = $settings['name'];

		}

		if($eStructure['logo'] === NULL) {

			$file = \Lime::getPath().'/'.$settings['logo'];

			if(is_file($file) === FALSE) {
				throw new \Exception("Logo introuvable : ".$file);
			}

			$hash = NULL;

			if(new \media\StructureLogoLib()->putFile($eStructure, $hash, $file, []) === FALSE) {
				throw new \Exception("Logo refuse par la bibliotheque de medias : ".$file);
			}

			$done['logo'] = $settings['logo'];

		}

		return $done;

	}

	public static function placeDepartment(int $structureSource, int $structureSchool): array {

		$eStructureSource = Structure::model()->select(['id', 'legalPostcode'])->whereId($structureSource)->get();
		$departmentPlace = \structure\System::model()->whereStructure($structureSource)->getValue('placeDepartment');

		// Le lieu de production situe la ferme mieux que son siège, comme pour toute structure : un
		// siège à Montpellier n'empêche pas les cultures d'être dans les Hautes-Alpes. Les départements
		// que `PlaceLib::extractCodes()` admet et ceux dont `DEPARTMENTS` connaît la préfecture sont deux
		// listes tenues séparément, et la lecture de la préfecture plus bas ne supporte pas un écart.
		$department = ($departmentPlace !== NULL and array_key_exists($departmentPlace, self::DEPARTMENTS)) ?
			$departmentPlace :
			self::getDepartment($eStructureSource['legalPostcode']);

		$mSystem = \structure\System::model();
		$eSystem = $mSystem->select(['id', 'place', 'placeLngLat'])->whereStructure($structureSchool)->get();

		// Ni commune ni intercommunalité, quel que soit le département : elles désigneraient la
		// préfecture, une ville où la ferme n'est pas.
		$eSystem['placeCity'] = NULL;
		$eSystem['placeEpci'] = NULL;

		if($department === NULL) {

			$eSystem['place'] = NULL;
			$eSystem['placeLngLat'] = NULL;
			$eSystem['placeDepartment'] = NULL;

			$mSystem->select(self::SYSTEM_KEEP)->update($eSystem);

			return [];

		}

		// Le département nomme, la préfecture situe : la pastille de la fiche dit « Vienne » quand
		// le plan est centré sur Poitiers. Nommer la ville placerait la ferme dans une commune où
		// elle n'est pas, ce que l'anonymisation par le flou évite justement.
		[$name, $prefecture, $longitude, $latitude] = self::DEPARTMENTS[$department];

		$eSystem['place'] = $name;
		$eSystem['placeLngLat'] = [$longitude, $latitude];
		$eSystem['placeDepartment'] = $department;

		$mSystem->select(self::SYSTEM_KEEP)->update($eSystem);

		return [$department, $name, $prefecture, $longitude, $latitude];

	}

	/**
	 * Ce que la fiche d'une ferme école montre : son système, jamais son identité.
	 *
	 * La fiche était blanche — ni atelier, ni surface, ni altitude — parce que le lot 20 ne copie
	 * que la production, et que les ateliers et le système vivent dans `base/structure`. Or c'est
	 * la première page qu'un formateur ouvre.
	 *
	 * **Ce qui manque à cette fiche n'est pas l'identité, c'est le système.** Un atelier
	 * « Maraîchage diversifié » ne nomme personne, une surface non plus, et c'est même ce qu'on
	 * vient étudier.
	 *
	 * ⚠️ `place` et `placeLngLat` sont exclus de la **copie**, et ce ne sont pas des oublis : la
	 * commune et les coordonnées de la source placent la ferme au mètre, ce que le recentrage du
	 * plan a justement défait. Ils sont ensuite **posés** par `placeDepartment()`, sur la
	 * préfecture du département de la source : on anonymise par le flou plutôt que par le vide, ce
	 * qui garde l'information agronomique — région, climat, contexte — que la suppression
	 * détruisait.
	 *
	 * Logo, bannière et adresse ne sont pas copiés non plus — même règle, appliquée là où la fiche
	 * irait les chercher.
	 */
	public static function placeProfile(int $structureSource, int $structureSchool, array $seasons): array {

		$done = [];

		// Les ateliers : la fiche les nomme, et c'est ce qui la rend lisible d'un coup d'œil.
		$mProduction = \structure\Production::model();

		$mProduction->whereStructure($structureSchool)->delete();

		$pdo = $mProduction->pdo();
		$fields = [];

		foreach($mProduction->getProperties() as $property) {
			$fields[$property] = ($property === 'structure') ? (string)$structureSchool : ('m1.'.$pdo->api->field($property));
		}

		unset($fields['id']);

		$pdo->exec(
			'INSERT INTO '.self::getTable($mProduction).' ('.implode(', ', array_map(fn($p) => $pdo->api->field($p), array_keys($fields))).')'
			.' SELECT '.implode(', ', $fields)
			.' FROM '.self::getTable($mProduction).' m1'
			.' WHERE m1.'.$pdo->api->field('structure').' = '.$structureSource
		);

		$done['ateliers'] = $mProduction->whereStructure($structureSchool)->count();

		// Le système : tout ce qui décrit la ferme, rien de ce qui la situe.
		$mSystem = \structure\System::model();

		$properties = array_values(array_diff($mSystem->getProperties(), ['id', 'structure'], self::SYSTEM_KEEP));

		$eSystemSource = $mSystem->select(array_merge(['id'], $properties))->whereStructure($structureSource)->get();
		$eSystemSchool = $mSystem->select(array_merge(['id'], $properties))->whereStructure($structureSchool)->get();

		foreach($properties as $property) {
			$eSystemSchool[$property] = $eSystemSource[$property];
		}

		$mSystem->select($properties)->update($eSystemSchool);

		$done['systeme'] = count($properties);

		// La description est **posée**, jamais copiée : celle de la source est du texte libre où
		// quelqu'un se raconte. Celle-ci dit ce que la page est, à l'endroit où l'on arrive sans
		// savoir ce qu'on regarde — la même chose que la carte du groupe, pour qui entre par ici.
		$mStructure = Structure::model();

		$eStructure = $mStructure->select(['id'])->whereId($structureSchool)->get();

		// ⚠️ Mise à NULL et non simplement plus écrite : le script détermine entièrement cette
		// ferme, donc il efface ce qu'il a posé hier. Le surtitre « Ferme école » du bandeau dit
		// désormais ce que la description disait, avant le nom au lieu d'après.
		$eStructure['description'] = NULL;

		// ⚠️ La visibilité est posée ici et non héritée du défaut du schéma, qui vaut NONE : une
		// ferme école existe pour être lue par les organismes de formation, et une fiche fermée
		// ferait tomber d'un coup le fait de la voir, d'observer sa production et de lire ses
		// ventes. Aucun geste humain ne peut l'ouvrir — elle n'a ni membre ni propriétaire.
		$eStructure['profileVisibility'] = Structure::MEMBERS;

		$mStructure->select(['description', 'profileVisibility'])->update($eStructure);

		$done['description'] = TRUE;
		$done['visibilite'] = Structure::MEMBERS;

		// ⚠️ Les Libs entretiennent des caches Redis que l'écriture directe ne touche pas, et qui
		// survivent 24 h. Mesuré : la fiche de la ferme école restait vide d'ateliers alors que la
		// table en portait un — le cache avait été peuplé avant la copie et personne ne l'invalide.
		// C'est le prix de la règle « un script de configure écrit par le modèle » : elle contourne
		// les Libs, donc aussi ce qu'ils tiennent à jour à côté de la base.
		\structure\ProductionLib::deleteCache($eStructure);
		\structure\SystemLib::deleteCache($eStructure);

		return $done;

	}

	/**
	 * L'empreinte du contenu d'une structure, pour prouver qu'un rejeu ne change rien.
	 *
	 * Toutes les colonnes de tous les modules copiés, ids compris : c'est le contrôle le plus
	 * exigeant, puisqu'il tombe aussi si le décalage se déplace d'un rejeu à l'autre. Il le peut
	 * parce que l'effacement précède la copie, donc le plus grand id retombe où il était.
	 *
	 * Une somme de CRC et non un `GROUP_CONCAT` : ce dernier tronque à `group_concat_max_len`
	 * — 1 024 octets par défaut — et rendrait une empreinte stable **et aveugle** au-delà.
	 */
	public static function getFingerprint(array $modules, int $structure): string {

		$parts = [];

		foreach($modules as $module => $m) {

			$pdo = $m->pdo();

			$fields = [];

			foreach($m->getProperties() as $property) {
				$fields[] = 'IFNULL('.$pdo->api->field($property).', "~")';
			}

			$sql = 'SELECT CONCAT(COUNT(*), ":", COALESCE(SUM(CRC32(CONCAT_WS("|", '.implode(', ', $fields).'))), 0))'
				.' FROM '.self::getTable($m)
				.' WHERE '.$pdo->api->field('structure').' = '.$structure;

			$parts[] = $module.'='.$pdo->selectUnique($sql);

		}

		return implode(' ', $parts);

	}

	/**
	 * Aucune ligne de la ferme école ne pointe hors d'elle-même, sauf le référentiel partagé.
	 *
	 * ⚠️ Il parcourt les colonnes **dérivées du modèle**, les mêmes que la copie. Une liste
	 * écrite à la main oublierait exactement les colonnes que la copie oublie, et un contrôle
	 * qui partage l'angle mort de ce qu'il contrôle ne contrôle rien.
	 *
	 * ⚠️ Rend les écarts **par colonne**, et non un verdict : ils se comparent à ceux de la ferme
	 * source, jamais à zéro. Une ferme réelle porte des liens déjà rompus — 15 colonnes sur la
	 * ferme 7, des lignes qui pointent vers des séries supprimées — et une copie fidèle les
	 * reproduit. Attendre zéro ferait échouer le script sur exactement le travail bien fait, et
	 * la seule façon de le faire passer serait de réparer la source, ce qui n'est pas son rôle.
	 * Ce qui se contrôle est donc l'égalité des deux relevés : la copie n'a rien ajouté.
	 */
	public static function check(array $modules, int $structure): array {

		$structureSchool = $structure;
		$errors = [];

		foreach($modules as $module => $m) {

			foreach($m->getProperties() as $property) {

				$target = $m->getPropertyToModule($property);

				if(
					$target === NULL or
					$target === 'structure\Structure' or
					$target === 'user\User' or
					array_key_exists($target, $modules) === FALSE
				) {
					continue;
				}

				$mModuleTarget = $modules[$target];

				$pdo = $m->pdo();
				$field = $pdo->api->field($property);

				// La cible légitime est soit dans la ferme école, soit dans le référentiel
				// partagé quand la cible en accepte — et rien d'autre.
				$condition = 'j.structure = '.$structureSchool;

				if($mModuleTarget->isPropertyNull('structure')) {
					$condition .= ' OR j.structure IS NULL';
				}

				$sql = 'SELECT COUNT(*) FROM '.self::getTable($m).' m1'
					.' LEFT JOIN '.self::getTable($mModuleTarget).' j ON j.id = m1.'.$field.' AND ('.$condition.')'
					.' WHERE m1.'.$pdo->api->field('structure').' = '.$structureSchool
					.' AND m1.'.$field.' IS NOT NULL'
					.' AND j.id IS NULL';

				$n = (int)$pdo->selectUnique($sql);

				if($n > 0) {
					$errors[$module.'.'.$property.' → '.$target] = $n;
				}

			}

		}

		return $errors;

	}

}
?>
