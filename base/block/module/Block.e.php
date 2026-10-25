<?php
namespace block;

/**
 * Un bloc d'un contenu composé : un texte, une photo, des étapes… Il appartient à un usage
 * (`owner`, par exemple les leçons d'une formation) et à un objet de cet usage (`reference`, l'id
 * de la leçon). Les droits sont ceux de l'objet : c'est le `BlockOwnerLib` de l'usage qui les dit, sur
 * l'objet que la page pose dans `eOwner`.
 */
class Block extends BlockElement {

	public function canRead(): bool {

		$this->expects(['owner', 'eOwner']);

		return (
			$this['eOwner']->notEmpty() and
			BlockSetting::resolve($this['owner'], 'BlockOwnerLib')::canRead($this['eOwner'])
		);

	}

	public function canWrite(): bool {

		$this->expects(['owner', 'eOwner']);

		return (
			$this['eOwner']->notEmpty() and
			BlockSetting::resolve($this['owner'], 'BlockOwnerLib')::canWrite($this['eOwner'])
		);

	}

	public function acceptWrite(): bool {

		$this->expects(['owner', 'eOwner']);

		return BlockSetting::resolve($this['owner'], 'BlockOwnerLib')::acceptWrite($this['eOwner']);

	}

	/**
	 * Seul un `block-item` porte une image (`media`) : une étape, un volet, un onglet ou un chiffre. Le
	 * texte porte les siennes dans ses figures (`getEditorOptions()`).
	 */
	public function acceptMedia(): bool {

		$this->expects(['type']);

		return ($this['type'] === BlockTypeLib::ITEM);

	}

	public function isRequired(string $property): bool {

		$this->expects(['type']);

		return in_array($property, BlockSetting::resolve($this['type'], 'BlockTypeLib')::getPropertiesRequired($this['type']), TRUE);

	}

	/**
	 * Le titre de ce bloc est le nom d'un onglet : celui d'un onglet, ou celui du premier onglet d'un
	 * bloc d'onglets qui naît avec lui.
	 */
	public function isTabName(): bool {

		$this->expects(['type', 'parent']);

		if($this['parent']->empty()) {
			return ($this['type'] === BlockTypeLib::TABS);
		}

		$this['parent']->expects(['type']);

		return ($this['parent']['type'] === BlockTypeLib::TABS);

	}

	public static function properties(\PropertyList $l): void {

		// Au premier niveau, un objet n'accepte que les types que son usage lui propose : une leçon n'a pas
		// l'écran du logiciel d'une page de site, et inversement, et un type qu'un autre package ajoute à
		// certains objets seulement (`getTypesByOwner()`) se juge sur l'objet posé par la page. Sous un
		// parent, c'est le parent qui dit ce qu'il porte (`BlockTypeLib::getChildren()`), jamais le menu de
		// l'usage : une étape n'est pas au premier niveau, un texte n'est pas parmi les étapes. Le parent
		// posé par la page porte son type.
		$l->property('type')
			->addAfterCheck('allowed', function(string $type): bool {

				$this->expects(['owner', 'parent']);

				if($this['parent']->empty()) {
					$this->expects(['eOwner']);
					return in_array($type, BlockSetting::resolve($this['owner'], 'BlockOwnerLib')::getTypesByOwner($this['owner'], $this['eOwner']), TRUE);
				}

				$this['parent']->expects(['type']);

				return in_array($type, BlockSetting::resolve($this['parent']['type'], 'BlockTypeLib')::getChildren($this['parent']['type']), TRUE);

			});

		// Un parent borné (`BlockTypeLib::getChildrenMax()`) refuse l'enfant de trop, même quand l'écran,
		// ouvert avant qu'un autre onglet n'en ajoute un, proposait encore son « + ».
		$l->property('type')
			->addAfterCheck('full', function(string $type): bool {

				$this->expects(['parent']);

				return (BlockLib::isFull($this['parent']) === FALSE);

			});

		// Une variante n'existe que pour un type qui en déclare, et un tel type en exige une : un encadré
		// sans couleur n'aurait pas de rendu. Le type est construit avant, en tête des listes de la page.
		$l->property('variant')
			->addAfterCheck('allowed', function(?string $variant): bool {

				$this->expects(['type']);

				$variants = BlockSetting::resolve($this['type'], 'BlockTypeLib')::getVariants($this['type']);

				if($variants === []) {
					return ($variant === NULL);
				} else {
					return in_array($variant, $variants, TRUE);
				}

			});

		// Un titre exigé (`getPropertiesRequired()`) a trois refus : celui du nom d'un onglet, qui se
		// dit avec les mots du champ « Nom de l'onglet », celui du libellé d'un bouton, et celui de tout
		// autre titre. Chacun laisse passer ce qui revient aux autres. Tous portent le nom de la
		// propriété, que l'éditeur pose en `data-wrapper` du conteneur du champ (`BlockTypeUi`).
		$l->property('title')
			->addAfterCheck('missing', function(?string $title): bool {

				return (
					$title !== NULL or
					$this->isRequired('title') === FALSE or
					$this->isTabName() or
					$this['type'] === BlockTypeLib::BUTTON
				);

			})
			->addAfterCheck('tabName', function(?string $title): bool {

				return (
					$title !== NULL or
					$this->isRequired('title') === FALSE or
					$this->isTabName() === FALSE
				);

			})
			->addAfterCheck('buttonLabel', fn(?string $title): bool => (
				$title !== NULL or
				$this['type'] !== BlockTypeLib::BUTTON
			));

		// Le texte passe par les options d'éditeur que son type donne pour son usage, seule source de ce
		// qu'il accepte : dans une leçon, une photo dans un bloc texte et aucune ailleurs. Le type est
		// construit avant, en tête des listes de la page. Refusé, il laisse le bloc sans type : le texte
		// n'a alors pas d'options à lire, et rien ne s'écrira.
		$l->property('content')
			->replaceCast(function(mixed &$value, \PropertyList $l): bool {

				$this->expects(['owner', 'type']);

				if($l->isInvalid('type')) {
					throw new \PropertySkip();
				}

				Block::model()->cast('content', $value);
				$value = new \editor\XmlLib()->fromHtml($value, BlockSetting::resolve($this['type'], 'BlockTypeLib')::getEditorOptions($this['type'], $this['owner']));

				// Le texte d'une vidéo décide de sa mise en page : à côté de lui, elle ne prend que la moitié
				// de la largeur (`BlockTypeUi::renderVideo()`). Des paragraphes, une liste ou un titre restés
				// vides, qu'une espace ou deux retours à la ligne laissent dans l'éditeur, ouvriraient une
				// colonne sans rien à lire. Ni des espaces, insécables comprises, ni des balises sans figure ne font un texte.
				if(
					$this['type'] === BlockTypeLib::VIDEO and
					$value !== NULL and
					preg_match('/\S/u', strip_tags($value, ['figure'])) === 0
				) {
					$value = NULL;
				}

				return TRUE;

			});

		// La vidéo et le bouton écrivent dans `url` (`BlockTypeLib::getProperties()`) une adresse gardée
		// telle que collée : la page où la vidéo se regarde, la page que le bouton ouvre. Le schéma n'en
		// juge que la forme, et le type `url` admet aussi `ftp` et `ftps` (`Filter.php`) ; `scheme` ne
		// garde que le web, la seule chose qu'un lien d'une leçon ouvre dans un onglet. `host` confronte
		// la vidéo aux hébergeurs de l'éditeur de texte, seule liste du produit. Rien de ce qui est posté
		// ne devient la source d'un cadre : l'adresse du lecteur se recalcule de cette liste à chaque
		// lecture (`BlockTypeUi::getVideoFrame()`). Chaque étape laisse passer ce que l'autre refuse.
		// Le type est construit avant, en tête des listes de la page.
		//
		// `storable` refuse ce que la colonne ne peut pas écrire et que la forme d'une adresse n'exclut
		// pas. Le type `url` ne porte pas de borne dans le schéma : la borne est celle dont le cadriciel
		// dimensionne sa colonne, `\Filter::MAX_TEXT16_SIZE` caractères en `utf8mb3`
		// (`framework/core/ModuleAdministration.php:343`). La longueur se compte en octets : un `text`
		// en contient 65 535, moins que cette borne en caractères de trois octets. Un caractère de
		// quatre octets, un emoji collé avec l'adresse, n'existe pas en `utf8mb3`.
		$l->property('url')
			->addAfterCheck('missing', function(?string $url): bool {

				return (
					$url !== NULL or
					$this->isRequired('url') === FALSE
				);

			})
			->addAfterCheck('storable', fn(?string $url): bool => (
				$url === NULL or (
					strlen($url) <= \Filter::MAX_TEXT16_SIZE and
					preg_match('/[\x{10000}-\x{10FFFF}]/u', $url) === 0
				)
			))
			->addAfterCheck('scheme', fn(?string $url): bool => (
				$url === NULL or
				preg_match('#^https?://#i', $url) === 1
			))
			->addAfterCheck('host', fn(?string $url): bool => (
				$url === NULL or
				$this['type'] !== BlockTypeLib::VIDEO or
				BlockSetting::getVideoSource($url) !== NULL
			))
			// Un objet porte une vidéo au plus : l'instant d'une étape (`settings.at`) s'y rapporte sans la
			// nommer, et deux vidéos le rendraient ambigu. Le refus se rattache à l'adresse, que toute vidéo
			// écrit, à la création comme à la modification, où la vidéo modifiée ne se compte pas. La copie
			// en refuse autant (`BlockLib::duplicate()`).
			->addAfterCheck('video', function(?string $url): bool {

				if(
					$url === NULL or
					$this['type'] !== BlockTypeLib::VIDEO
				) {
					return TRUE;
				}

				$this->expects(['owner', 'reference']);

				return (BlockLib::hasVideo($this['owner'], $this['reference'], $this) === FALSE);

			});

		// Un type qui écrit dans `settings` (`getProperties()`) y range sa propre forme, que seule sa paire
		// connaît : `format` la lui demande (`castSettings()`), et refuse en entier ce qui n'y est pas,
		// jamais réparé. Le moteur ne borne que ses types, chaque borne laissant passer les autres ; un
		// type d'un autre package borne le sien dans sa forme, et y nomme ses refus. Le type et la
		// variante sont construits avant, en tête des listes de la page. Refusé (`type.allowed`,
		// `type.full`, `variant.allowed`), l'un ou l'autre laisse la forme sans rien à quoi se rapporter,
		// et la chaîne s'arrête sans rien juger, comme celle de `content`.
		//
		// Une étape enregistrée à l'écran ne poste ni son instant ni ses repères, que seule l'API écrit :
		// son réglage non posté garde celui de la base, que le cast du cadriciel rendrait sinon `[]`, la
		// colonne json n'étant pas nullable (`Module::cast()`). Une étape neuve n'a rien à garder.
		$l->property('settings')
			->addBeforeCast('kept', function(mixed &$settings, \PropertyList $l): bool {

				$l->skipIfInvalid(['type']);

				if(
					$settings === NULL and
					$this['type'] === BlockTypeLib::ITEM and
					$this->exists()
				) {
					$settings = $this['settings'];
				}

				return TRUE;

			})
			->addAfterCast('format', function(mixed &$settings, \PropertyList $l, string $wrapper): bool {

				$l->skipIfInvalid(['type', 'variant']);

				return BlockSetting::resolve($this['type'], 'BlockTypeLib')::castSettings($this, $settings, $wrapper);

			})
			->addAfterCheck('rows', fn(array $settings): bool => (
				$this['type'] !== BlockTypeLib::TABLE or (
					count($settings['rows']) >= 1 and
					count($settings['rows']) <= BlockSetting::TABLE_ROWS_MAX
				)
			))
			// La chaîne s'arrête au premier refus : arrivée ici, la grille a au moins une ligne.
			->addAfterCheck('columns', fn(array $settings): bool => (
				$this['type'] !== BlockTypeLib::TABLE or (
					count($settings['rows'][0]) >= 1 and
					count($settings['rows'][0]) <= BlockSetting::TABLE_COLUMNS_MAX
				)
			))
			->addAfterCheck('cell', function(array $settings): bool {

				if($this['type'] !== BlockTypeLib::TABLE) {
					return TRUE;
				}

				foreach($settings['rows'] as $row) {

					foreach($row as $cell) {

						if(mb_strlen($cell) > BlockSetting::TABLE_CELL_LIMIT) {
							return FALSE;
						}

					}

				}

				return TRUE;

			})
			->addAfterCheck('kicker', fn(array $settings): bool => (
				$this['type'] !== BlockTypeLib::SECTION or
				$settings['kicker'] === NULL or
				mb_strlen($settings['kicker']) <= BlockSetting::SECTION_KICKER_LIMIT
			))
			// L'instant d'une étape, une fois lu en secondes (`BlockTypeLib::castItem()`), tombe dans la
			// vidéo : ni avant son début, ni au-delà de la borne.
			->addAfterCheck('at', fn(array $settings): bool => (
				$this['type'] !== BlockTypeLib::ITEM or
				array_key_exists('at', $settings) === FALSE or (
					$settings['at'] >= 0 and
					$settings['at'] <= BlockSetting::STEP_AT_MAX
				)
			))
			// Les repères d'une étape, une fois reconstruits (`BlockTypeLib::castItem()`) : pas plus que le
			// plafond, compté avant d'en lire un seul, puis chacun dans la capture. Les bornes s'écrivent
			// `>= 0` et `<= 100`, et non par leur contraire : une valeur qui n'est pas un nombre les échoue
			// toutes les deux.
			->addAfterCheck('marks', fn(array $settings): bool => (
				$this['type'] !== BlockTypeLib::ITEM or
				array_key_exists('marks', $settings) === FALSE or
				count($settings['marks']) <= BlockSetting::STEP_MARKS_MAX
			))
			->addAfterCheck('mark', function(array $settings): bool {

				if(
					$this['type'] !== BlockTypeLib::ITEM or
					array_key_exists('marks', $settings) === FALSE
				) {
					return TRUE;
				}

				$inside = fn(float $value): bool => ($value >= 0 and $value <= 100);

				foreach($settings['marks'] as $mark) {

					if(
						$inside($mark['x']) === FALSE or
						$inside($mark['y']) === FALSE
					) {
						return FALSE;
					}

					if(
						$mark['type'] === 'box' and (
							$mark['w'] <= 0 or
							$mark['h'] <= 0 or
							$inside(round($mark['x'] + $mark['w'], 2)) === FALSE or
							$inside(round($mark['y'] + $mark['h'], 2)) === FALSE
						)
					) {
						return FALSE;
					}

				}

				return TRUE;

			})
			// Les étiquettes se comptent une fois nettoyées (`BlockTypeLib::castTags()`) : un vide ou un doublon n'en est
			// pas une. Au-delà, elles sont refusées plutôt que tronquées, le POST pouvant porter ce que
			// l'éditeur ne laisse pas écrire.
			->addAfterCheck('full', fn(array $settings): bool => (
				$this['type'] !== BlockTypeLib::TAGS or
				count($settings['tags']) <= BlockSetting::TAGS_MAX
			))
			->addAfterCheck('tag', function(array $settings): bool {

				if($this['type'] !== BlockTypeLib::TAGS) {
					return TRUE;
				}

				foreach($settings['tags'] as $tag) {

					if(mb_strlen($tag) > BlockSetting::TAG_LIMIT) {
						return FALSE;
					}

				}

				return TRUE;

			});

	}

}
?>
