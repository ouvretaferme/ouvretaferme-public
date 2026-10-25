<?php
namespace block;

/**
 * Un usage de blocs et un type de bloc ne sont déclarés nulle part dans le moteur : ils vivent dans
 * les paires `BlockOwnerLib` + `BlockOwnerUi` et `BlockTypeLib` + `BlockTypeUi` du package que la
 * valeur stockée désigne par son premier segment (`BlockSetting::resolve()`). Cette sentinelle
 * vérifie que chaque paire est complète, que la déclaration ci-dessous dit encore ce que les
 * packages portent, qu'un usage vit dans la base commune, et que le moteur ne nomme ni usage ni
 * type d'un autre package.
 *
 * Rien ne rappellera ce contrat à qui ajoutera un usage dans six mois, depuis un package qui n'a
 * aucune raison d'ouvrir `base/block` : un `BlockOwnerLib` sans `BlockOwnerUi` ne lève qu'à
 * l'ouverture de l'éditeur, un type sans branche dans un `match` qu'au premier bloc rendu, et un
 * usage logé dans une base de structure ne lève jamais, il mêle des blocs de plusieurs structures.
 *
 * Aucune écriture en base, aucune base ouverte : la sentinelle tourne avec toutes les autres
 * (`dev/test package='*' file='*Sentinel*'`). Ce qui relit un objet en base (`getOwner()`) ou
 * écrit (`touch()`) se contrôle par sa présence, ce qui ne rend que des mots s'invoque.
 *
 * Lancement : dev/test package=block file=BlockTypeSentinel
 */
class BlockTypeSentinelTest extends \Test {

	/**
	 * Les usages connus au 2026-09-24, avec leur raison. Écrits en toutes lettres et non par la
	 * constante du package : une déclaration doit pouvoir devenir fausse et se faire nommer.
	 */
	const array OWNERS = [
		'learning-lesson' => "le contenu d'une leçon de formation, repris de `learningLesson.content` au lot B0 du chantier e-learning ; l'objet est la leçon, dans la base commune",
	];

	/**
	 * Les types connus au 2026-10-11, avec leur raison : ceux qu'un usage accepte au premier niveau,
	 * et ceux qu'un type accepte sous lui (`getChildren()`).
	 */
	const array TYPES = [
		'block-text' => "le texte, porté par le moteur parce que tous les usages en ont besoin, et parce que c'est lui qui reprend un contenu d'avant les blocs",
		'block-callout' => "l'encadré en trois couleurs (information, conseil, attention), lot B1",
		'block-steps' => "des étapes numérotées, parent de block-item, lot B1",
		'block-accordion' => "un accordéon, parent de block-item, lot B1",
		'block-tabs' => "des onglets, parent de block-item, lot B1",
		'block-figure' => "des chiffres clés, parent de quatre block-item au plus, chacun un chiffre et sa légende, lot B1",
		'block-quote' => "une citation et son auteur, lot B1",
		'block-table' => "un tableau de texte simple, sa grille dans settings, la première ligne pour en-tête, lot L7c",
		'block-video' => "une vidéo que le produit n'héberge pas, lue chez un hébergeur de la liste de l'éditeur de texte : son adresse dans url, sa légende dans title, pleine largeur ou petite, chargée au clic, lot C1 de blocs-etude-de-cas, ou à gauche ou à droite d'un texte écrit dans content, lot C9",
		'block-button' => "un lien vers une autre page, ouvert dans un autre onglet : son libellé dans title, son adresse en http ou https dans url, tous deux exigés, lot C3 de blocs-etude-de-cas",
		'block-section' => "un titre d'étape et son surtitre facultatif, qui ouvre une partie de l'objet jusqu'à la section suivante : son titre dans title, exigé, son surtitre dans settings, sans enfant, regroupé à la lecture par la liste, lot C5 de blocs-etude-de-cas",
		'block-answers' => "des réponses possibles en grille de deux, parent de block-answer, dont la meilleure se révèle au clic à la lecture, lot C7 de blocs-etude-de-cas",
		'block-tags' => "quelques mots qui situent le sujet, lus en pastilles grises dans l'ordre de la saisie : leurs mots dans settings, dix au plus de 40 caractères chacun, sans enfant ni variante, lot C8 de blocs-etude-de-cas",
		'block-item' => "l'enfant des étapes, de l'accordéon, des onglets et des chiffres clés, un titre et un texte, qui ne se pose jamais au premier niveau, lot B1",
		'block-answer' => "l'enfant des réponses, un titre exigé et un texte, ordinaire ou meilleure, une seule meilleure par fratrie, qui ne se pose jamais au premier niveau, lot C7 de blocs-etude-de-cas",
	];

	const array METHODS_OWNER_LIB = ['getOwners', 'getTypes', 'getTypesByOwner', 'getOwner', 'canRead', 'canWrite', 'acceptWrite', 'getEditorOptions', 'touch'];
	const array METHODS_OWNER_UI = ['getPlanTitle'];
	const array METHODS_TYPE_LIB = ['getProperties', 'getPropertiesCreate', 'getPropertiesRequired', 'getChildren', 'getChildrenMax', 'getVariants', 'isVariantFixed', 'getVariantUnique', 'getDefaultSettings', 'getEditorOptions', 'castSettings', 'isEmpty'];
	const array METHODS_TYPE_UI = ['getName', 'getIcon', 'getDescription', 'render', 'getEditable'];

	/**
	 * Le menu de variante de la barre (`BlockUi::getVariantMenu()`), qu'une paire n'écrit que si un de ses
	 * types a des variantes qui se règlent là : une forme fixée n'en a pas, et une paire n'écrit pas de
	 * méthode que rien n'appelle.
	 */
	const array METHODS_TYPE_UI_VARIANT = ['getVariantLabel', 'getVariantMark', 'hasVariantDivider', 'getVariantClass'];

	/**
	 * Une paire se reconnaît dans une ligne du moteur à un nom de package littéral entre deux
	 * antislashs, suivi du nom d'une classe de paire, ou à un `use` qui l'importe.
	 */
	const string PATTERN_PAIR = '/(?:\\\\|use\s+)([a-z][a-zA-Z0-9]*)\\\\Block(?:Owner|Type)(?:Lib|Ui)\b/';

	/**
	 * Un package qui déclare des usages apporte les deux côtés de la paire, ou aucun ; de même pour
	 * un package qui porte des types.
	 */
	public function testEveryPackageCarriesBothSides(): void {

		$found = [];

		foreach(array_keys(\Package::getList()) as $package) {

			foreach(['BlockOwner', 'BlockType'] as $pair) {

				$lib = class_exists('\\'.$package.'\\'.$pair.'Lib');
				$ui = class_exists('\\'.$package.'\\'.$pair.'Ui');

				if($lib !== $ui) {
					$found[] = $package.' ('.$pair.($lib ? 'Lib sans '.$pair.'Ui' : 'Ui sans '.$pair.'Lib').')';
				}

			}

		}

		$this->assertEquals([], $found, "paires incomplètes : ".implode(', ', $found));

	}

	/**
	 * Les deux sens ne signalent pas la même chose : un usage apparu sans déclaration n'a pas été
	 * relu, une déclaration sans usage est devenue fausse.
	 */
	public function testOwnersDeclarationSaysWhatTheCodeCarries(): void {

		$found = array_keys(self::getOwnersByPackage());
		$declared = array_keys(self::OWNERS);

		sort($found);
		sort($declared);

		$this->assertEquals([], array_values(array_diff($found, $declared)), "usages apparus sans déclaration ici : ".implode(', ', array_diff($found, $declared)));
		$this->assertEquals([], array_values(array_diff($declared, $found)), "usages déclarés ici qu'aucun package ne porte plus : ".implode(', ', array_diff($declared, $found)));

	}

	public function testTypesDeclarationSaysWhatTheCodeCarries(): void {

		$found = self::getTypes();
		$declared = array_keys(self::TYPES);

		sort($declared);

		$this->assertEquals([], array_values(array_diff($found, $declared)), "types acceptés par un usage sans déclaration ici : ".implode(', ', array_diff($found, $declared)));
		$this->assertEquals([], array_values(array_diff($declared, $found)), "types déclarés ici qu'aucun usage n'accepte plus : ".implode(', ', array_diff($declared, $found)));

	}

	/**
	 * Un usage est déclaré par le package que son nom désigne : déclaré ailleurs, il serait résolu
	 * vers une paire qui ne le connaît pas.
	 */
	public function testEveryPackageDeclaresItsOwnOwners(): void {

		$found = [];

		foreach(self::getOwnersByPackage() as $owner => $package) {

			if(BlockSetting::getPackage($owner) !== $package) {
				$found[] = $owner.' (déclaré par '.$package.')';
			}

		}

		$this->assertEquals([], $found, "usages déclarés par un package qui n'est pas le leur : ".implode(', ', $found));

	}

	/**
	 * `reference` n'est unique que dans une base. Un usage dont le package vit dans les bases de
	 * structure ferait désigner à un même bloc des objets de plusieurs structures (amendement A9 du
	 * lot B0).
	 */
	public function testOwnersLiveInCommonBase(): void {

		$packages = \structure\StructureSetting::getPackages();

		$this->assertTrue(in_array('journal', $packages, TRUE), "la liste des packages de structure ne porte plus `journal` : le contrôle ne sait plus lire ce qu'il compare");

		$found = [];

		foreach(array_keys(self::OWNERS) as $owner) {

			if(in_array(BlockSetting::getPackage($owner), $packages, TRUE)) {
				$found[] = $owner;
			}

		}

		$this->assertEquals([], $found, "usages dont le package vit dans les bases de structure : ".implode(', ', $found));

	}

	public function testOwnerPairsAreComplete(): void {

		foreach(self::getOwnersByPackage() as $owner => $package) {

			$classLib = BlockSetting::resolve($owner, 'BlockOwnerLib');
			$classUi = BlockSetting::resolve($owner, 'BlockOwnerUi');

			foreach(self::METHODS_OWNER_LIB as $method) {
				$this->assertTrue(method_exists($classLib, $method), $owner." : ".$classLib." ne porte pas ".$method."()");
			}

			foreach(self::METHODS_OWNER_UI as $method) {
				$this->assertTrue(method_exists($classUi, $method), $owner." : ".$classUi." ne porte pas ".$method."()");
			}

			$this->assertTrue($classLib::getTypes($owner) !== [], $owner." : getTypes() accepte au moins un type");

			$typesOffered = [];

			foreach(self::getOwnerSamples($owner) as $sample => $eOwner) {

				$types = $classLib::getTypesByOwner($owner, $eOwner);

				$this->assertEquals([], array_values(array_diff($classLib::getTypes($owner), $types)), $owner." : getTypesByOwner() retire, pour ".$sample.", des types que l'usage déclare (getTypes()), qu'elle ne peut que compléter");

				$typesOffered = array_merge($typesOffered, $types);

			}

			// Un objet sans bloc s'ouvre sur une tuile par entrée qu'il propose, une par variante d'une forme
			// fixée (`BlockLib::getChoices()`), et chaque tuile dit ce que son entrée contient : une entrée
			// sans ligne lèverait à l'ouverture de tout objet vide.
			foreach(BlockLib::getChoices(array_unique($typesOffered)) as $choice => ['type' => $type, 'variant' => $variant]) {

				try {
					$this->assertTrue(new (BlockSetting::resolve($type, 'BlockTypeUi'))()->getDescription($type, $variant) !== '', $owner." : getDescription() rend une ligne vide pour l'entrée ".$choice.", que la tuile du premier bloc affiche");
				} catch(\UnhandledMatchError) {
					$this->assertFail($owner." : getDescription() n'a pas de branche pour l'entrée ".$choice.", que la tuile du premier bloc affiche");
				}

			}

			// Un bloc de l'usage porte un type du menu ou un enfant d'un de ces types : chacun doit
			// trouver ses options, que le type donne pour l'usage, sinon le `match` qui les dit lève à
			// l'ouverture du bloc.
			$types = array_unique($typesOffered);

			foreach($types as $type) {
				$types = array_merge($types, BlockSetting::resolve($type, 'BlockTypeLib')::getChildren($type));
			}

			foreach(array_unique($types) as $type) {

				try {
					$this->assertTrue(is_array(BlockSetting::resolve($type, 'BlockTypeLib')::getEditorOptions($type, $owner)), $owner." : getEditorOptions() rend des options d'éditeur pour ".$type);
				} catch(\UnhandledMatchError) {
					$this->assertFail($owner." : getEditorOptions() n'a pas de branche pour le type ".$type);
				}

			}

			$this->assertTrue(new $classUi()->getPlanTitle($owner) !== '', $owner." : getPlanTitle() rend un titre");

		}

	}

	/**
	 * Chaque type accepté se résout vers une paire complète, dont chaque `match` a sa branche. Les
	 * propriétés qu'un type écrit sont des propriétés du bloc : une propriété inconnue ferait lever
	 * `build()` au premier enregistrement.
	 */
	public function testTypePairsAreComplete(): void {

		$properties = Block::model()->getProperties();
		$typesOffered = self::getTypesOffered();

		$typesChild = [];

		foreach($typesOffered as $type) {
			$typesChild = array_merge($typesChild, BlockSetting::resolve($type, 'BlockTypeLib')::getChildren($type));
		}

		foreach($typesOffered as $type) {

			$classLib = BlockSetting::resolve($type, 'BlockTypeLib');
			$classUi = BlockSetting::resolve($type, 'BlockTypeUi');

			if(class_exists($classLib) === FALSE or class_exists($classUi) === FALSE) {
				$this->assertFail($type." : la paire ".$classLib." / ".$classUi." n'existe pas");
				continue;
			}

			foreach(self::METHODS_TYPE_LIB as $method) {
				$this->assertTrue(method_exists($classLib, $method), $type." : ".$classLib." ne porte pas ".$method."()");
			}

			foreach(self::METHODS_TYPE_UI as $method) {
				$this->assertTrue(method_exists($classUi, $method), $type." : ".$classUi." ne porte pas ".$method."()");
			}

			foreach(['getProperties', 'getPropertiesCreate', 'getPropertiesRequired'] as $method) {

				try {
					$written = $classLib::$method($type);
					$this->assertEquals([], array_values(array_diff($written, $properties)), $type." : ".$method."() nomme des propriétés que le bloc n'a pas : ".implode(', ', array_diff($written, $properties)));
				} catch(\UnhandledMatchError) {
					$this->assertFail($type." n'a pas de branche dans ".$classLib."::".$method."()");
				}

			}

			foreach(['getChildren', 'getVariants'] as $method) {

				try {
					$this->assertTrue(is_array($classLib::$method($type)), $type." : ".$method."() rend une liste");
				} catch(\UnhandledMatchError) {
					$this->assertFail($type." n'a pas de branche dans ".$classLib."::".$method."()");
				}

			}

			// Les contrôles qui suivent lisent les variantes et la forme fixée : sans elles, ils n'ont rien
			// sur quoi se faire, et leur absence est nommée.
			try {
				$variants = $classLib::getVariants($type);
				$fixed = $classLib::isVariantFixed($type);
			} catch(\UnhandledMatchError) {
				$this->assertFail($type." n'a pas de branche dans ".$classLib."::getVariants() ou isVariantFixed()");
				continue;
			}

			// Une forme fixée se choisit au « + » (`BlockLib::getChoices()`) et ne se change plus : elle a des
			// variantes, qu'aucune fratrie ne se dispute, elle n'est jamais un enfant, que le « + » d'un
			// parent pose sans choix, elle s'écrit à la création avant le réglage, qui se lit selon elle, et
			// la mise à jour ne l'écrit pas. Une variante qui se règle dans la barre s'y écrit à chaque
			// enregistrement.
			try {

				$propertiesUpdate = $classLib::getProperties($type);
				$propertiesCreate = $classLib::getPropertiesCreate($type);

				if($fixed) {

					$this->assertTrue($variants !== [], $type." : isVariantFixed() fixe une forme sans variante (getVariants())");
					$this->assertTrue($classLib::getVariantUnique($type) === NULL, $type." : une forme fixée n'a pas de variante unique (getVariantUnique()), qu'aucun bloc ne peut prendre à ses frères");
					$this->assertFalse(in_array($type, $typesChild, TRUE), $type." : une forme fixée n'est jamais un enfant, que le « + » d'un parent pose sans menu");
					$this->assertFalse(in_array('variant', $propertiesUpdate, TRUE), $type." : une forme fixée ne se change pas, getProperties() ne porte pas variant");

					$positionVariant = array_search('variant', $propertiesCreate, TRUE);
					$positionSettings = array_search('settings', $propertiesCreate, TRUE);

					$this->assertTrue($positionVariant !== FALSE and ($positionSettings === FALSE or $positionVariant < $positionSettings), $type." : une forme fixée s'écrit à la création avant le réglage (variant avant settings dans getPropertiesCreate())");

				} else if($variants !== []) {
					$this->assertTrue(in_array('variant', $propertiesUpdate, TRUE), $type." : une variante qui n'est pas une forme fixée se règle dans la barre, getProperties() porte variant");
				}

			} catch(\UnhandledMatchError) {
				$this->assertFail($type." n'a pas de branche dans ".$classLib."::getProperties(), getPropertiesCreate() ou getVariantUnique()");
			}

			// Un type à variantes qui se règlent dans la barre a le menu de la barre.
			if($variants !== [] and $fixed === FALSE) {

				foreach(self::METHODS_TYPE_UI_VARIANT as $method) {
					$this->assertTrue(method_exists($classUi, $method), $type." : ".$classUi." ne porte pas ".$method."(), que le menu de variante de la barre appelle");
				}

			}

			// Un bloc neuf s'ouvre sur son réglage par défaut (`BlockLib::getNew()`), sous chaque variante où il
			// peut naître, que l'auteur peut enregistrer sans y toucher : la forme du type doit l'admettre. Un
			// refus que le type nomme lui-même compte comme un échec nommé, et non comme une erreur du test.
			// Une branche absente de getPropertiesCreate() est nommée plus haut. Un parent se juge sur les
			// enfants qu'il porte : l'élément en reçoit une liste vide, la colonne du réglage n'étant jamais
			// nulle.
			try {
				$writesSettings = in_array('settings', $classLib::getPropertiesCreate($type), TRUE);
			} catch(\UnhandledMatchError) {
				$writesSettings = FALSE;
			}

			foreach(($variants === []) ? [NULL] : $variants as $variant) {

				$name = $type.($variant === NULL ? '' : ' sous la variante '.$variant);

				try {
					$settings = $classLib::getDefaultSettings($type, $variant);
				} catch(\UnhandledMatchError) {
					$this->assertFail($name." n'a pas de branche dans ".$classLib."::getDefaultSettings()");
					continue;
				}

				if($writesSettings) {

					try {
						$this->assertTrue($classLib::castSettings(new Block(['type' => $type, 'variant' => $variant]), $settings, 'settings'), $name." : castSettings() refuse le réglage d'un bloc neuf (getDefaultSettings()), à admettre dans la forme ou à corriger dans getDefaultSettings()");
					} catch(\FailException $e) {
						$this->assertFail($name." : castSettings() refuse le réglage d'un bloc neuf (getDefaultSettings()) sous ".$e->getMessage().", à admettre dans la forme ou à corriger dans getDefaultSettings()");
					} catch(\UnhandledMatchError) {
						$this->assertFail($name." écrit dans settings et n'a pas de branche dans ".$classLib."::castSettings(), à y écrire avec sa forme");
					}

				}

				try {
					$classLib::isEmpty(new Block(['type' => $type, 'variant' => $variant, 'cBlock' => new \Collection(), 'settings' => $classLib::getDefaultSettings($type, $variant)] + array_fill_keys($properties, NULL)));
				} catch(\UnhandledMatchError) {
					$this->assertFail($name." n'a pas de branche dans ".$classLib."::isEmpty()");
				}

			}

			// Une forme fixée se nomme par variante, dans le menu, la barre et le plan.
			foreach($fixed ? $variants : [NULL] as $variant) {

				try {
					$this->assertTrue(new $classUi()->getName($type, NULL, $variant) !== '', $type.($variant === NULL ? '' : ' sous la variante '.$variant)." : getName() rend un texte");
				} catch(\UnhandledMatchError) {
					$this->assertFail($type.($variant === NULL ? '' : ' sous la variante '.$variant)." n'a pas de branche dans ".$classUi."::getName()");
				}

			}

			try {
				$this->assertTrue(new $classUi()->getIcon($type) !== '', $type." : getIcon() rend un texte");
			} catch(\UnhandledMatchError) {
				$this->assertFail($type." n'a pas de branche dans ".$classUi."::getIcon()");
			}

			// Un type à variantes se règle dans la barre du bloc ouvert : sans le nom de son réglage, sans
			// ce qui précède une de ses variantes, sans le séparateur qui la devance ou non, ou sans la
			// classe qu'elle pose, le `match` lèverait à l'ouverture du bloc, et sur la classe, dès sa
			// lecture.
			// La marque d'une variante peut être vide, une disposition de vidéo n'en a pas : elle n'est
			// appelée que pour sa branche. Le nom de la variante, lui, s'affiche toujours, et le menu le lit dans le
			// describer (`BlockUi::getVariantMenu()`). Une forme fixée n'a pas ce menu.
			$variantNames = BlockUi::p('variant')->values;

			foreach($fixed ? [] : $variants as $variant) {

				try {
					$this->assertTrue(new $classUi()->getVariantLabel($type) !== '', $type." : getVariantLabel() nomme le réglage");
					new $classUi()->getVariantMark($type, $variant);
					new $classUi()->hasVariantDivider($type, $variant);
					new $classUi()->getVariantClass($type, $variant);
				} catch(\UnhandledMatchError) {
					$this->assertFail($type." déclare des variantes et n'a pas de branche, pour la variante ".$variant.", dans ".$classUi."::getVariantLabel(), getVariantMark(), hasVariantDivider() ou getVariantClass()");
				}

				$this->assertTrue(($variantNames[$variant] ?? '') !== '', $type." : la variante ".$variant." n'a pas de nom dans le describer de BlockUi (p('variant')->values), le menu de la barre ne saurait pas l'écrire");

			}

			// La variante unique d'une fratrie est une des variantes du type : une autre valeur ne serait
			// jamais portée, et la remise des frères (`BlockLib::resetSiblingsVariant()`) ne jouerait pas.
			try {
				$variantUnique = $classLib::getVariantUnique($type);
				$this->assertTrue($variantUnique === NULL or in_array($variantUnique, $variants, TRUE), $type." : getVariantUnique() rend ".$variantUnique.", qui n'est pas une de ses variantes (getVariants())");
			} catch(\UnhandledMatchError) {
				$this->assertFail($type." n'a pas de branche dans ".$classLib."::getVariantUnique()");
			}

		}

	}

	/**
	 * Chaque refus qu'une propriété écrite par un type peut lever se dit à l'auteur. Sans message, le
	 * champ est marqué sans texte ; sans branche dans un `match` de `block\AlertUi`, le premier POST
	 * refusé lève dans le message au lieu de l'afficher. Les mots des refus de `AlertUi::ERRORS_BY_TYPE`
	 * dépendent du type, et un type d'un autre package les dit dans son propre `AlertUi`, sous la même
	 * clé. Les refus sont levés pour de vrai, par `build()` sur un bloc en mémoire et les valeurs de
	 * `getProbes()`, qui ne lit ni n'écrit la base : un refus que le type nomme lui-même est donc
	 * contrôlé aussi. Le réglage d'une forme fixée se lit selon sa variante : il est sondé sous chacune.
	 */
	public function testEveryRefusalSaysItself(): void {

		$found = [];
		$raised = [];
		$undelegated = [];

		foreach(self::getTypesWithParent() as [$type, $eBlockParent]) {

			$typeLib = BlockSetting::resolve($type, 'BlockTypeLib');
			$properties = $typeLib::getPropertiesCreate($type);
			$variants = $typeLib::isVariantFixed($type) ? $typeLib::getVariants($type) : [NULL];

			foreach(self::getProbes() as $property => $values) {

				if(in_array($property, $properties, TRUE) === FALSE) {
					continue;
				}

				foreach($values as $value) {

					foreach($variants as $variant) {

						$fw = new \FailWatch();

						new Block(['type' => $type, 'parent' => $eBlockParent, 'variant' => $variant])->build([$property], [$property => $value]);

						foreach($fw->get() as [$package, $failName, $arguments]) {

							$raised[] = $package.'\\'.$failName;

							// Un message du moteur qui se calcule sur le bloc dépend de son type : hors de la liste,
							// il ne serait pas cherché chez un type d'un autre package.
							if(
								$package === __NAMESPACE__ and
								in_array($failName, AlertUi::ERRORS_BY_TYPE, TRUE) === FALSE and
								AlertUi::getError($failName) instanceof \Closure
							) {
								$undelegated[] = $failName;
							}

							try {

								if($fw->getMessage($package, $failName, $arguments) === NULL) {
									$found[] = $type.' ('.$package.'\\'.$failName.', sans message)';
								}

							} catch(\UnhandledMatchError) {
								$found[] = $type.' ('.$package.'\\'.$failName.', sans branche)';
							}

						}

					}

				}

			}

		}

		$found = array_values(array_unique($found));
		sort($found);

		$this->assertEquals([], $found, "refus levés sans rien à dire à l'auteur, dont le message s'écrit sous cette clé dans l'AlertUi du package nommé : ".implode(', ', $found));

		$undelegated = array_values(array_unique($undelegated));
		sort($undelegated);

		$this->assertEquals([], $undelegated, "refus dont le message du moteur dépend du bloc sans figurer dans block\\AlertUi::ERRORS_BY_TYPE, à y ajouter : ".implode(', ', $undelegated));

		// Les valeurs sondent-elles encore chaque refus dont les mots dépendent du type ?
		$missed = array_values(array_diff(array_map(fn(string $failName) => __NAMESPACE__.'\\'.$failName, AlertUi::ERRORS_BY_TYPE), $raised));

		$this->assertEquals([], $missed, "refus qu'aucune valeur de getProbes() ne fait plus lever, dont le message n'est donc plus contrôlé : ajouter à getProbes() une valeur qui le lève, ou le retirer de ERRORS_BY_TYPE si aucune étape ne le lève plus : ".implode(', ', $missed));

	}

	/**
	 * Le moteur nomme-t-il un usage ou un type d'un autre package ? Un `match` sur un usage posé dans
	 * un fichier d'ici rouvrirait la table que la résolution par le nom a retirée. Tous les fichiers
	 * du répertoire sont lus, sauf `test/`, où les valeurs sont écrites en toutes lettres par
	 * construction. Les types du moteur lui-même (`block-…`) y ont leur place. Ceux qu'un observateur
	 * ajoute à un objet (`getTypesOffered()`) sont cherchés aussi : ils ne sont déclarés nulle part
	 * ici, et le moteur ne doit pas davantage les nommer.
	 */
	public function testEngineFilesNameNoForeignValue(): void {

		$values = array_merge(
			array_keys(self::getOwnersByPackage()),
			array_filter(self::getTypesOffered(), fn(string $type) => BlockSetting::getPackage($type) !== __NAMESPACE__)
		);

		$this->assertTrue($values !== [], "aucune valeur étrangère au moteur à chercher : le contrôle ne cherche plus rien");

		$found = [];

		foreach(self::getEngineLines() as $place => $line) {

			foreach($values as $value) {

				if(str_contains($line, $value)) {
					$found[] = $place.' ('.$value.')';
				}

			}

		}

		sort($found);

		$this->assertEquals([], $found, "fichiers du moteur qui nomment un usage ou un type d'un autre package : ".implode(', ', $found));

	}

	/**
	 * La forme probable n'est pas la valeur littérale mais la constante (`\learning\BlockOwnerLib::LESSON`),
	 * qui ne contient pas `learning-lesson` : c'est le nom de classe préfixé qui trahit.
	 */
	public function testEngineFilesNameNoPackagePair(): void {

		$temoins = [
			'\learning\BlockOwnerLib::LESSON' => 'learning',
			'use website\BlockTypeUi;' => 'website',
		];

		foreach($temoins as $temoin => $package) {
			$this->assertEquals($package, preg_match(self::PATTERN_PAIR, $temoin, $match) === 1 ? $match[1] : NULL, "le motif ne sait plus lire la forme : ".$temoin);
		}

		$found = [];

		foreach(self::getEngineLines() as $place => $line) {

			if(
				preg_match(self::PATTERN_PAIR, $line, $match) === 1 and
				$match[1] !== __NAMESPACE__
			) {
				$found[] = $place.' ('.$match[1].')';
			}

		}

		sort($found);

		$this->assertEquals([], $found, "fichiers du moteur qui nomment la paire d'un package : ".implode(', ', $found));

	}

	/**
	 * Les usages de tous les packages, indexés par valeur, avec le package qui les déclare.
	 */
	private static function getOwnersByPackage(): array {

		$owners = [];

		foreach(array_keys(\Package::getList()) as $package) {

			$class = '\\'.$package.'\\BlockOwnerLib';

			if(class_exists($class) === FALSE) {
				continue;
			}

			foreach($class::getOwners() as $owner) {
				$owners[$owner] = $package;
			}

		}

		ksort($owners);

		return $owners;

	}

	private static function getTypes(): array {

		$types = [];

		foreach(array_keys(self::getOwnersByPackage()) as $owner) {
			$types = array_merge($types, BlockSetting::resolve($owner, 'BlockOwnerLib')::getTypes($owner));
		}

		// L'arbre n'a qu'un niveau : les enfants des types de premier niveau suffisent.
		foreach(array_unique($types) as $type) {
			$types = array_merge($types, BlockSetting::resolve($type, 'BlockTypeLib')::getChildren($type));
		}

		$types = array_values(array_unique($types));
		sort($types);

		return $types;

	}

	/**
	 * Les types que les objets de tous les usages proposent (`getTypesByOwner()` sur `getOwnerSamples()`),
	 * avec leurs enfants : ceux de `getTypes()` et ceux qu'un observateur ajoute à certains objets. Un
	 * type ajouté ainsi vient d'un autre package, qui le déclare chez lui : il n'entre pas dans `TYPES`,
	 * mais sa paire, sa tuile, ses options et ses refus se contrôlent ici comme ceux des autres.
	 */
	private static function getTypesOffered(): array {

		$types = [];

		foreach(array_keys(self::getOwnersByPackage()) as $owner) {

			foreach(self::getOwnerSamples($owner) as $eOwner) {
				$types = array_merge($types, BlockSetting::resolve($owner, 'BlockOwnerLib')::getTypesByOwner($owner, $eOwner));
			}

		}

		foreach(array_unique($types) as $type) {
			$types = array_merge($types, BlockSetting::resolve($type, 'BlockTypeLib')::getChildren($type));
		}

		$types = array_values(array_unique($types));
		sort($types);

		return $types;

	}

	/**
	 * Les objets d'un usage sur lesquels juger ce qu'il propose, construits en mémoire sans lire la
	 * base : un observateur peut ajouter un type à certains objets seulement. Pour une leçon, une de
	 * formation de l'équipe et une de formation de réseau, seule distinction que les types lisent
	 * (`Course::isTeam()`, sur `community` seul). Le groupe de réseau n'est jamais relu : son id ne sert
	 * qu'à le rendre non vide. Un usage sans branche ici lève dans chaque contrôle qui le parcourt, sous
	 * son nom.
	 */
	private static function getOwnerSamples(string $owner): array {

		return match($owner) {
			'learning-lesson' => [
				'une leçon de l\'équipe' => new \learning\Lesson(['course' => new \learning\Course(['community' => new \structure\Structure()])]),
				'une leçon de réseau' => new \learning\Lesson(['course' => new \learning\Course(['community' => new \structure\Structure(['id' => 1])])]),
			],
		};

	}

	/**
	 * Chaque type proposé, avec le parent sous lequel il se pose : vide pour un type de premier niveau.
	 * Un enfant se pose sous chacun de ses parents, dont le type change ses refus (le titre d'un onglet).
	 */
	private static function getTypesWithParent(): array {

		$typesWithParent = [];

		foreach(array_keys(self::getOwnersByPackage()) as $owner) {

			$types = [];

			foreach(self::getOwnerSamples($owner) as $eOwner) {
				$types = array_merge($types, BlockSetting::resolve($owner, 'BlockOwnerLib')::getTypesByOwner($owner, $eOwner));
			}

			foreach(array_unique($types) as $type) {

				$typesWithParent[$type] = [$type, new Block()];

				foreach(BlockSetting::resolve($type, 'BlockTypeLib')::getChildren($type) as $typeChild) {
					$typesWithParent[$typeChild.' '.$type] = [$typeChild, new Block(['type' => $type])];
				}

			}

		}

		return array_values($typesWithParent);

	}

	/**
	 * Les valeurs postées qui font lever les refus d'une propriété : l'absence, ce que le schéma
	 * refuse, ce qu'une étape du moteur refuse. Un octet qui n'est pas de l'UTF-8 passe les formes du
	 * tableau et de la section, et n'est refusé qu'à l'encodage par le schéma (`settings.check`). Une
	 * adresse plus longue que sa colonne garde la forme d'une adresse (`url.storable`).
	 */
	private static function getProbes(): array {

		return [
			'variant' => ['inconnue', str_repeat('a', 31)],
			'title' => [NULL, str_repeat('a', 251)],
			'url' => [NULL, 'texte', 'ftp://example.org/', 'https://example.org/', 'https://example.org/'.str_repeat('a', \Filter::MAX_TEXT16_SIZE)],
			'settings' => ['texte', ['rows' => [["\xC3\x28"]]], ['kicker' => "\xC3\x28"]],
		];

	}

	/**
	 * Toutes les lignes des fichiers du moteur, indexées `chemin:ligne`. Le répertoire se déduit de
	 * l'emplacement de ce fichier : un package déplacé ne doit pas rendre le contrôle vide.
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
