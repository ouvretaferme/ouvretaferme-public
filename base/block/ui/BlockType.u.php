<?php
namespace block;

/**
 * Les mots, l'icône et les deux rendus des types que le moteur porte lui-même. Chaque type se lit
 * dans un composant du design system : l'encadré dans `util-card-accent`, les étapes dans
 * `util-procedure`, l'accordéon dans `util-deck`, les onglets dans `tabs`, les chiffres clés dans
 * `util-figures`, les réponses dans `util-cards` et `util-card-accent`, la citation dans
 * `editor-quote`, le tableau dans `util-table`, la tête d'une section dans `util-label` et
 * `util-title`, les étiquettes dans `badges`. Il s'écrit dans le même balisage (`getEditable()`) :
 * ouvrir un bloc ne change pas sa forme, seulement ce qui s'y écrit. La vidéo n'a pas de composant
 * où se loger : son cadre est le sien, comme ses deux colonnes à côté d'un texte (`block-video`,
 * `read.css`).
 */
class BlockTypeUi {

	/**
	 * Un enfant se nomme d'après son parent (`parentType`) : l'étape, le volet, l'onglet ou le chiffre
	 * d'un bloc, dans la barre du bloc ouvert comme dans le « + » qui l'ajoute. `$variant` est celle du
	 * bloc ou de l'entrée du menu, qu'un type à forme fixée lit pour nommer chaque forme
	 * (`BlockTypeLib::isVariantFixed()`), et que les types du moteur ignorent.
	 */
	public function getName(string $type, ?string $parentType = NULL, ?string $variant = NULL): string {

		return match($type) {
			BlockTypeLib::TEXT => s("Texte"),
			BlockTypeLib::CALLOUT => s("Encadré"),
			BlockTypeLib::STEPS => s("Étapes"),
			BlockTypeLib::ACCORDION => s("Accordéon"),
			BlockTypeLib::TABS => s("Onglets"),
			BlockTypeLib::FIGURE => s("Chiffres clés"),
			BlockTypeLib::QUOTE => s("Citation"),
			BlockTypeLib::TABLE => s("Tableau"),
			BlockTypeLib::VIDEO => s("Vidéo"),
			BlockTypeLib::BUTTON => s("Bouton"),
			BlockTypeLib::SECTION => s("Section"),
			BlockTypeLib::ANSWERS => s("Réponses"),
			BlockTypeLib::TAGS => s("Étiquettes"),
			BlockTypeLib::ANSWER => s("Réponse"),
			BlockTypeLib::ITEM => match($parentType) {
				BlockTypeLib::STEPS, NULL => s("Étape"),
				BlockTypeLib::ACCORDION => s("Volet"),
				BlockTypeLib::TABS => s("Onglet"),
				BlockTypeLib::FIGURE => s("Chiffre"),
			},
		};

	}

	/**
	 * Ce qu'un type contient, en une ligne, sous son nom dans le choix du premier bloc
	 * (`BlockUi::getStart()`). Seuls les types de premier niveau en ont une : un enfant ne s'y propose
	 * pas. Le nombre de chiffres est celui de `BlockTypeLib::getChildrenMax()`, écrit ici en toutes
	 * lettres et tenu avec lui par `BlockTest::testStartDescriptions()`. Une forme fixée a une tuile par
	 * variante, que `$variant` désigne ; les types du moteur l'ignorent.
	 */
	public function getDescription(string $type, ?string $variant = NULL): string {

		return match($type) {
			BlockTypeLib::TEXT => s("Paragraphes, titres, listes et photos"),
			BlockTypeLib::CALLOUT => s("Une information, un conseil ou un point d'attention"),
			BlockTypeLib::STEPS => s("Une marche à suivre, numérotée"),
			BlockTypeLib::ACCORDION => s("Des volets qui se déplient un à un"),
			BlockTypeLib::TABS => s("Plusieurs textes à la même place, un seul affiché"),
			BlockTypeLib::FIGURE => s("Jusqu'à quatre chiffres et ce qu'ils mesurent"),
			BlockTypeLib::QUOTE => s("Une phrase et son auteur"),
			BlockTypeLib::TABLE => s("Des lignes et des colonnes"),
			BlockTypeLib::VIDEO => s("Une vidéo en ligne et sa légende"),
			BlockTypeLib::BUTTON => s("Un lien vers une autre page"),
			BlockTypeLib::SECTION => s("Un titre d'étape, sur un bandeau qui court jusqu'à la suivante"),
			BlockTypeLib::ANSWERS => s("Des réponses possibles, dont la meilleure"),
			BlockTypeLib::TAGS => s("Quelques mots qui situent le sujet"),
		};

	}

	public function getIcon(string $type): string {

		return match($type) {
			BlockTypeLib::TEXT => 'text-paragraph',
			BlockTypeLib::CALLOUT => 'info-square',
			BlockTypeLib::STEPS => 'list-ol',
			BlockTypeLib::ACCORDION => 'chevron-bar-expand',
			BlockTypeLib::TABS => 'folder2',
			BlockTypeLib::FIGURE => '123',
			BlockTypeLib::QUOTE => 'chat-quote',
			BlockTypeLib::TABLE => 'table',
			BlockTypeLib::VIDEO => 'play-btn',
			BlockTypeLib::BUTTON => 'box-arrow-up-right',
			BlockTypeLib::SECTION => 'card-heading',
			BlockTypeLib::ANSWERS => 'ui-radios-grid',
			BlockTypeLib::TAGS => 'tags',
			BlockTypeLib::ITEM, BlockTypeLib::ANSWER => 'dot',
		};

	}

	/**
	 * Le réglage d'un type à variantes, dans la barre du bloc ouvert (`BlockUi::getVariantMenu()`) : son
	 * icône et son nom, que le bouton garde en toutes lettres tant que la barre en a la place. Seuls
	 * les types qui déclarent des variantes (`BlockTypeLib::getVariants()`) ont une branche.
	 */
	public function getVariantLabel(string $type): string {

		return match($type) {
			BlockTypeLib::CALLOUT => \Asset::icon('palette').'<span class="hide-xs-down"> '.s("Couleur").'</span>',
			BlockTypeLib::VIDEO => \Asset::icon('aspect-ratio').'<span class="hide-xs-down"> '.s("Disposition").'</span>',
			BlockTypeLib::STEPS => \Asset::icon('list-check').'<span class="hide-xs-down"> '.s("Repère").'</span>',
			BlockTypeLib::ANSWER => \Asset::icon('award').'<span class="hide-xs-down"> '.s("Statut").'</span>',
		};

	}

	/**
	 * Ce qui précède le nom d'une variante dans ce menu : la pastille de sa couleur pour un encadré ou
	 * une réponse, rien pour une disposition de vidéo, que son nom dit en entier, l'icône de la liste
	 * pour un repère.
	 */
	public function getVariantMark(string $type, string $variant): string {

		return match($type) {
			BlockTypeLib::CALLOUT, BlockTypeLib::ANSWER => '<span class="block-variant '.encode($this->getVariantClass($type, $variant)).'">'.\Asset::icon('circle-fill').'</span> ',
			BlockTypeLib::VIDEO => '',
			BlockTypeLib::STEPS => \Asset::icon(match($variant) {
				BlockTypeLib::NUMBERS => 'list-ol',
				BlockTypeLib::CHECK => 'list-check',
			}).' ',
		};

	}

	/**
	 * Le menu pose un séparateur avant cette variante (`BlockUi::getVariantMenu()`) : les deux tailles
	 * d'une vidéo, seule, puis ses deux places à côté d'un texte, qui en ouvrent le champ.
	 */
	public function hasVariantDivider(string $type, string $variant): bool {

		return match($type) {
			BlockTypeLib::CALLOUT, BlockTypeLib::STEPS, BlockTypeLib::ANSWER => FALSE,
			BlockTypeLib::VIDEO => ($variant === BlockTypeLib::LEFT),
		};

	}

	/**
	 * La classe qu'une variante pose sur le bloc, en lecture comme en écriture, et que le menu de la
	 * barre échange sur le bloc à écrire (`data-block-variant-target`). Les étapes numérotées sont la
	 * liste du design system sans modificateur : leur classe est vide.
	 */
	public function getVariantClass(string $type, string $variant): string {

		return match($type) {
			BlockTypeLib::CALLOUT => 'util-card-accent-'.$variant,
			BlockTypeLib::VIDEO => 'block-video-'.$variant,
			BlockTypeLib::STEPS => match($variant) {
				BlockTypeLib::NUMBERS => '',
				BlockTypeLib::CHECK => 'util-procedure-check',
			},
			BlockTypeLib::ANSWER => match($variant) {
				BlockTypeLib::REGULAR => 'util-card-accent-info',
				BlockTypeLib::BEST => 'util-card-accent-success',
			},
		};

	}

	/**
	 * Le texte se lit comme tout contenu d'éditeur : c'est ce qui garde une leçon reprise identique
	 * à ce qu'elle était avant les blocs.
	 *
	 * Un bloc parent rend ses enfants dans sa forme. `$wrap` est ce que l'éditeur pose autour de
	 * chacun, son enveloppe et son formulaire (`BlockUi::getItem()`) : sans lui, c'est la lecture.
	 * `$insert` ajoute au bout des enfants le « + » qui en ajoute un (`getChildInsert()`), que
	 * l'éditeur seul demande. Un enfant rendu seul, dans l'éditeur, prend la forme que lui donne le
	 * type de son parent (`parentType`, posé par `BlockLib::fillEditor()`).
	 *
	 * Une section ne rend que sa tête : la lecture y pose les blocs qu'elle emporte
	 * (`BlockUi::render()`), et l'éditeur la montre seule, dans son enveloppe.
	 *
	 * Une réponse n'est rendue seule que dans l'éditeur, qui montre toujours la meilleure.
	 */
	public function render(Block $eBlock, array $attributes = [], ?\Closure $wrap = NULL, bool $insert = FALSE): string {

		return match($eBlock['type']) {
			BlockTypeLib::TEXT => new \editor\EditorUi()->value($eBlock['content'], attributes: $attributes),
			BlockTypeLib::CALLOUT => $this->renderCallout($eBlock, $attributes),
			BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS, BlockTypeLib::FIGURE => $this->renderComposite($eBlock, $wrap ?? fn(Block $eBlockChild) => $this->renderChild($eBlock['type'], $eBlockChild), $insert),
			BlockTypeLib::QUOTE => $this->renderQuote($eBlock, $attributes),
			BlockTypeLib::TABLE => $this->renderTable($eBlock),
			BlockTypeLib::VIDEO => $this->renderVideo($eBlock),
			BlockTypeLib::BUTTON => $this->renderButton($eBlock),
			BlockTypeLib::SECTION => $this->renderSectionHead($eBlock),
			BlockTypeLib::ANSWERS => $this->renderAnswers($eBlock, $wrap, $insert),
			BlockTypeLib::TAGS => $this->renderTags($eBlock),
			BlockTypeLib::ITEM => $this->renderChild($eBlock['parentType'], $eBlock, $attributes, read: FALSE),
			BlockTypeLib::ANSWER => $this->renderAnswer($eBlock, TRUE, $attributes),
		};

	}

	/**
	 * La première ligne est l'en-tête. Les colonnes d'un tableau écrit ne se redistribuent pas, le
	 * produit ne sachant pas lesquelles vont ensemble : sous la largeur de ses colonnes (`read.css`), le
	 * tableau défile dans son cadre, la première colonne restant en vue. `MainStickyColumn` éteint le
	 * filet de cette colonne quand rien ne passe dessous.
	 */
	protected function renderTable(Block $eBlock): string {

		\Asset::js('main', 'stickyColumnAi.js');

		$rows = $eBlock['settings']['rows'];

		$h = '<div class="util-overflow">';
			$h .= '<div class="util-table util-table-even util-table-sticky-column block-table" style="--block-table-columns: '.count($rows[0]).'">';

				foreach($rows as $position => $row) {

					$h .= '<div class="'.($position === 0 ? 'util-table-head' : 'util-table-row').'">';

						foreach($row as $cell) {
							$h .= '<div>'.encode($cell).'</div>';
						}

					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La vidéo lue : son cadre, puis sa légende si elle en a une, dans une enveloppe qui porte la
	 * classe de sa disposition (`getVariantClass()`), que le menu de la barre échange à l'écriture.
	 *
	 * À gauche ou à droite d'un texte, la vidéo et son texte se partagent la largeur en deux colonnes,
	 * dans l'ordre où ils se lisent : à droite de son texte, la vidéo vient après lui, et c'est lui qui
	 * passe le premier sur un téléphone, où les colonnes s'empilent. Sans texte, elle se lit à la
	 * petite taille, plutôt qu'à côté d'une colonne vide. En pleine largeur ou petite, le texte gardé
	 * ne se lit pas : il attend que l'auteur revienne à une place à côté.
	 */
	protected function renderVideo(Block $eBlock): string {

		$variant = match($eBlock['variant']) {
			BlockTypeLib::FULL, BlockTypeLib::SMALL => $eBlock['variant'],
			BlockTypeLib::LEFT, BlockTypeLib::RIGHT => ($eBlock['content'] === NULL) ? BlockTypeLib::SMALL : $eBlock['variant'],
		};

		$figure = '<figure class="block-video">';

			$figure .= $this->getVideoFrame($eBlock);

			if($eBlock['title'] !== NULL) {
				$figure .= '<figcaption>'.encode($eBlock['title']).'</figcaption>';
			}

		$figure .= '</figure>';

		$h = '<div class="'.$this->getVariantClass($eBlock['type'], $variant).'">';

			$h .= match($variant) {
				BlockTypeLib::FULL, BlockTypeLib::SMALL => $figure,
				BlockTypeLib::LEFT => $figure.'<div class="block-video-text">'.new \editor\EditorUi()->value($eBlock['content']).'</div>',
				BlockTypeLib::RIGHT => '<div class="block-video-text">'.new \editor\EditorUi()->value($eBlock['content']).'</div>'.$figure,
			};

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bouton lu ouvre sa page dans un autre onglet, quelle qu'elle soit, pour que la leçon reste
	 * ouverte derrière, et l'icône le dit après le libellé. `nofollow` : l'adresse est écrite par un
	 * auteur, pas par le produit, comme celles des liens d'un texte (`FormatterUi`). L'enveloppe garde
	 * au bouton sa largeur, que la liste des blocs, une colonne flex, étirerait.
	 */
	protected function renderButton(Block $eBlock): string {

		$h = '<div class="block-button">';
			$h .= '<a href="'.encode($eBlock['url']).'" target="_blank" rel="nofollow" class="btn btn-outline-primary">'.encode($eBlock['title']).' '.\Asset::icon('box-arrow-up-right').'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La tête d'une section : son surtitre, s'il en a un, au-dessus de son titre, un `h2` comme celui
	 * d'une partie de document.
	 */
	protected function renderSectionHead(Block $eBlock): string {

		$h = '<div class="block-section-head">';

			if($eBlock['settings']['kicker'] !== NULL) {
				$h .= '<div class="util-label">'.encode($eBlock['settings']['kicker']).'</div>';
			}

			$h .= '<div class="util-title"><h2>'.encode($eBlock['title']).'</h2></div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les étiquettes lues, dans l'ordre où l'auteur les a écrites : c'est lui qui sait ce qui compte
	 * d'abord.
	 */
	protected function renderTags(Block $eBlock): string {

		$h = '<div class="badges block-tags">';

			foreach($eBlock['settings']['tags'] as $tag) {
				$h .= '<span class="badge badge-neutral">'.encode($tag).'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le cadre d'une vidéo ne demande rien à son hébergeur : ni lecteur, ni image. Le lecteur ne se
	 * charge qu'au clic sur « Lire la vidéo » (`BlockVideo`, `read.js`), qui pose alors dans le cadre
	 * l'adresse que porte `data-block-video`. Cette adresse est celle que la liste des hébergeurs
	 * compose depuis l'adresse enregistrée : l'hôte du lecteur est toujours celui de la liste.
	 *
	 * Le bouton est un lien vers ce même lecteur, sans démarrage (`BlockSetting::getVideo()`) : il se
	 * prend au clavier, et sans script il ouvre la vidéo chez l'hébergeur. L'adresse enregistrée ne
	 * sort pas dans la page : elle peut porter, après l'identifiant de la vidéo, de quoi désigner celui
	 * qui l'a collée. Le nom de l'hébergeur, lu dans la liste, dit où mène le clic.
	 *
	 * Une adresse que la liste ne reconnait plus (hébergeur retiré depuis l'enregistrement) n'a pas de
	 * lecteur à ouvrir. La lecture écarte alors le bloc (`BlockTypeLib::isEmpty()`), sans rien en dire à
	 * personne : ce cadre-là est le seul endroit où l'auteur l'apprend. Il y retrouve l'adresse qu'il
	 * avait collée, sans lien puisqu'elle ne mène à aucun lecteur, et ce qu'il en advient à la lecture,
	 * dans un élément à part pour que l'adresse se copie seule. La phrase tient à l'absence de lecteur
	 * et non à un hébergeur : elle vaut pour toute adresse que la liste cesse de lire.
	 *
	 * Le cadre porte aussi le gabarit du démarrage à un instant (`data-block-video-start`), que le
	 * clic sur l'instant d'une étape remplit (`getStepAt()`) : il reste en place une fois le lecteur
	 * posé, qui remplace le contenu du cadre et non le cadre.
	 */
	protected function getVideoFrame(Block $eBlock): string {

		$video = BlockSetting::getVideo($eBlock['url']);

		$start = ($video['source'] === NULL) ? '' : ' data-block-video-start="'.encode($video['playerStart']).'"';

		$h = '<div class="block-video-frame"'.$start.'>';

			if($video['source'] === NULL) {

				$h .= '<div class="block-video-poster">';
					$h .= '<div>';
						$h .= '<div class="color-muted">'.encode($eBlock['url']).'</div>';
						$h .= '<div class="util-annotation">'.s("Cette adresse n'est plus acceptée. La vidéo ne s'affiche plus à la lecture.").'</div>';
					$h .= '</div>';
				$h .= '</div>';

			} else {

				\Asset::js('block', 'read.js');

				$h .= '<a href="'.encode($video['player']).'" class="block-video-poster" data-block-video="'.encode($video['playerAutoplay']).'" referrerpolicy="strict-origin-when-cross-origin">';
					$h .= '<span class="btn btn-primary">'.\Asset::icon('play-fill').' '.s("Lire la vidéo").'</span>';
				$h .= '</a>';
				$h .= '<span class="block-video-host">'.encode($video['name']).'</span>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La vidéo seule, sans sa légende ni sa disposition, pour un écran qui la pose à part de la liste
	 * des blocs et la nomme lui-même, comme la carte en tête d'une leçon. L'instant d'une étape la
	 * trouve en remontant la page (`BlockVideo.jump()`).
	 */
	public function getVideoFigure(Block $eBlock): string {

		\Asset::css('block', 'read.css');

		return '<figure class="block-video">'.$this->getVideoFrame($eBlock).'</figure>';

	}

	protected function renderCallout(Block $eBlock, array $attributes): string {

		$h = '<div class="util-card util-card-accent '.$this->getVariantClass($eBlock['type'], $eBlock['variant']).'">';

			if($eBlock['title'] !== NULL) {
				$h .= '<div class="util-card-title">'.encode($eBlock['title']).'</div>';
			}

			$h .= new \editor\EditorUi()->value($eBlock['content'], attributes: $attributes);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les réponses, deux par rangée. Dans l'éditeur, la meilleure se voit et chaque réponse est un bloc
	 * à ouvrir. À la lecture, la meilleure se lit comme les autres jusqu'au clic sur « Voir la meilleure
	 * réponse » (`BlockAnswers`, `read.js`) : le stagiaire choisit avant de voir. Le bouton suit la grille
	 * dans une même enveloppe, la liste des blocs étant une colonne flex qui les espacerait d'un bloc.
	 */
	protected function renderAnswers(Block $eBlock, ?\Closure $wrap, bool $insert): string {

		if($wrap !== NULL) {
			return $this->renderComposite($eBlock, $wrap, $insert);
		}

		$hasBest = $eBlock['cBlock']->find(fn(Block $eBlockChild) => $eBlockChild['variant'] === BlockTypeLib::BEST)->notEmpty();

		$h = '<div>';

			$h .= $this->renderComposite($eBlock, fn(Block $eBlockChild) => $this->renderAnswer($eBlockChild, FALSE), FALSE);

			if($hasBest) {

				\Asset::js('block', 'read.js');

				$h .= '<div class="mt-1">';
					$h .= '<a class="btn btn-outline-primary" data-block-answers-reveal>'.\Asset::icon('award').' '.s("Voir la meilleure réponse").'</a>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une réponse, dans le liseré de sa variante, la meilleure portant sa pastille dans son titre. Tant
	 * qu'elle n'est pas révélée, la meilleure se rend ordinaire et garde sa forme révélée dans un
	 * `<template>`, que le script pose à sa place.
	 */
	protected function renderAnswer(Block $eBlock, bool $revealed, array $attributes = []): string {

		$hidden = (
			$eBlock['variant'] === BlockTypeLib::BEST and
			$revealed === FALSE
		);

		$variant = $hidden ? BlockTypeLib::REGULAR : $eBlock['variant'];

		$h = '<div class="util-card util-card-accent '.$this->getVariantClass($eBlock['type'], $variant).'"'.($hidden ? ' data-block-answer-best' : '').'>';

			$h .= '<div class="util-card-title">';
				$h .= encode($eBlock['title']);

				if($variant === BlockTypeLib::BEST) {
					$h .= ' <span class="badge badge-success">'.s("Meilleure réponse").'</span>';
				}

			$h .= '</div>';

			$h .= new \editor\EditorUi()->value($eBlock['content'], attributes: $attributes);

			if($hidden) {
				// Une forme entière et non la pastille en `hidden` : `.badge` déclare `display: inline-block`, qui bat l'attribut.
				$h .= '<template>'.$this->renderAnswer($eBlock, TRUE, $attributes).'</template>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La citation reprend celle de l'éditeur de texte (`editor-quote`), dont les règles ne s'appliquent
	 * que sous une `div.readitor` : c'est l'enveloppe du rendu de l'éditeur, que le bloc pose lui-même.
	 */
	protected function renderQuote(Block $eBlock, array $attributes): string {

		$h = '<div class="readitor">';
			$h .= '<div class="editor-quote">';
				$h .= '<div class="editor-quote-image">'.\Asset::icon('chat-quote').'</div>';
				$h .= '<blockquote>';
					$h .= new \editor\EditorUi()->value($eBlock['content'], attributes: $attributes);

					if($eBlock['title'] !== NULL) {
						$h .= '<p class="util-annotation">'.encode($eBlock['title']).'</p>';
					}

				$h .= '</blockquote>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les onglets se lisent en deux endroits, les têtes puis les panneaux : leurs enfants sont
	 * parcourus deux fois, l'une après l'autre, jamais l'une dans l'autre (une `Collection` n'a qu'un
	 * curseur). Le premier est choisi à l'ouverture. Les plis d'un accordéon sont enveloppés d'un
	 * `div` pour que la liste des blocs (`read.css`) retire la marge du dernier.
	 */
	protected function renderComposite(Block $eBlock, \Closure $wrap, bool $insert): string {

		$type = $eBlock['type'];
		$add = $insert ? $this->getChildInsert($eBlock) : '';

		if($type === BlockTypeLib::TABS) {

			$h = '<div class="tabs tabs-h">';

				$h .= '<div class="tabs-item">';

					$selected = TRUE;

					foreach($eBlock['cBlock'] as $eBlockChild) {
						$h .= '<a class="tab-item'.($selected ? ' selected' : '').'" id="block-tab-'.$eBlockChild['id'].'" data-tab="block-'.$eBlockChild['id'].'" onclick="Lime.Tab.select(this)">'.encode($eBlockChild['title']).'</a>';
						$selected = FALSE;
					}

					$h .= $add;

				$h .= '</div>';

				$selected = TRUE;

				foreach($eBlock['cBlock'] as $eBlockChild) {
					$h .= $this->getUnit($type, $eBlockChild, $wrap($eBlockChild), $selected);
					$selected = FALSE;
				}

			$h .= '</div>';

			return $h;

		}

		$h = '';

		foreach($eBlock['cBlock'] as $eBlockChild) {
			$h .= $this->getUnit($type, $eBlockChild, $wrap($eBlockChild));
		}

		return match($type) {
			BlockTypeLib::STEPS => $this->getStepsList($eBlock, $h).$add,
			BlockTypeLib::ACCORDION => '<div>'.$h.$add.'</div>',
			BlockTypeLib::FIGURE => '<div class="util-figures">'.$h.$add.'</div>',
			BlockTypeLib::ANSWERS => '<div class="util-cards util-cards-2 block-answers">'.$h.'</div>'.$add,
		};

	}

	/**
	 * La liste des étapes, numérotées ou cochées selon leur repère. Le bloc à écrire désigne sa liste
	 * au menu de la barre (`$target`), qui en échange la classe avant l'enregistrement.
	 */
	protected function getStepsList(Block $eBlock, string $items, bool $target = FALSE): string {

		$class = $this->getVariantClass($eBlock['type'], $eBlock['variant']);

		return '<ol class="util-procedure'.($class === '' ? '' : ' '.$class).'"'.($target ? ' data-block-variant-target' : '').'>'.$items.'</ol>';

	}

	/**
	 * La place d'un enfant dans son parent, autour de son rendu ou de son enveloppe d'éditeur : un
	 * élément de liste pour une étape, un panneau pour un onglet. Un pli d'accordéon, un chiffre et une
	 * réponse portent leur forme dans leur propre rendu. L'enfant à écrire du « + » (`BlockUi::getNew()`)
	 * porte `#block-new` sur sa place, que le script retire en entier s'il est refermé vide.
	 */
	public function getUnit(string $type, Block $eBlockChild, string $item, bool $selected = FALSE): string {

		$id = $eBlockChild->exists() ? '' : ' id="block-new"';

		return match($type) {
			BlockTypeLib::STEPS => '<li class="util-procedure-step"'.$id.'>'.$item.'</li>',
			BlockTypeLib::ACCORDION, BlockTypeLib::FIGURE, BlockTypeLib::ANSWERS => ($id === '') ? $item : '<div'.$id.'>'.$item.'</div>',
			BlockTypeLib::TABS => '<div class="tab-panel'.($selected ? ' selected' : '').'"'.$id.' data-tab="block-'.($eBlockChild->exists() ? $eBlockChild['id'] : 'new').'">'.$item.'</div>',
		};

	}

	/**
	 * Le « + » d'un parent ouvert, au bout de ses enfants, dans leur forme : une ligne sous la dernière
	 * étape, le dernier volet ou la grille des réponses, un onglet de plus, une tuile de plus. Celui des
	 * étapes suit la liste et non sa dernière ligne : dans la liste, il prendrait un numéro et le trait
	 * qui relie les étapes. Le script pose l'enfant à écrire après le dernier
	 * (`BlockEditor.getAnchor()`), `data-block-child` nommant le parent dont il prend la forme et
	 * `data-type` le type de ses enfants, posé sur le parent (`childTypes`, `BlockLib::fillEditor()`).
	 */
	protected function getChildInsert(Block $eBlock): string {

		$type = $eBlock['type'];
		$attributes = 'data-block-insert data-type="'.$eBlock['childTypes'][0].'" data-block-child="'.$type.'"';

		return match($type) {
			BlockTypeLib::STEPS => '<div class="block-insert-child"><a '.$attributes.' class="btn btn-only-icon btn-sm btn-outline-primary" title="'.s("Ajouter une étape").'">'.\Asset::icon('plus-lg').'</a></div>',
			BlockTypeLib::ANSWERS => '<div class="block-insert-child"><a '.$attributes.' class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('plus-lg').'</a></div>',
			BlockTypeLib::ACCORDION => '<div class="block-insert-child"><a '.$attributes.' class="btn btn-only-icon btn-sm btn-outline-primary" title="'.s("Ajouter un volet").'">'.\Asset::icon('plus-lg').'</a></div>',
			BlockTypeLib::TABS => '<a '.$attributes.' class="tab-item block-insert-tab" title="'.s("Ajouter un onglet").'">'.\Asset::icon('plus-lg').'</a>',
			BlockTypeLib::FIGURE => '<a '.$attributes.' class="btn btn-only-icon btn-dashed block-insert-figure" title="'.s("Ajouter un chiffre").'">'.\Asset::icon('plus-lg').'</a>',
		};

	}

	/**
	 * Un pli d'accordéon s'ouvre par sa tête (`util-deck-head`), comme tout dépliable du design
	 * system. Dans l'éditeur, le clic sur un bloc lu l'ouvre à l'écriture, où le pli montre tout. Un
	 * chiffre sans légende se montre : la légende n'est rendue que si elle est écrite. Une étape
	 * montre son instant dans la vidéo sous son titre (`getStepAt()`), et sa capture sous son texte
	 * (`getStepMedia()`). `$read` dit si c'est la lecture : l'éditeur rend l'enfant seul, hors de la
	 * liste des étapes.
	 */
	protected function renderChild(string $parentType, Block $eBlockChild, array $attributes = [], bool $read = TRUE): string {

		$content = new \editor\EditorUi()->value($eBlockChild['content'], attributes: $attributes);

		return match($parentType) {
			BlockTypeLib::STEPS => '<h4 class="util-procedure-title">'.encode($eBlockChild['title']).'</h4>'.$this->getStepAt($eBlockChild, $read).$content.$this->getStepMedia($eBlockChild, $read),
			BlockTypeLib::ACCORDION => '<div class="util-deck">'.
				'<a class="util-deck-head" '.attr('onclick', 'this.closest(".util-deck").classList.toggle("util-deck-open")').'>'.
					'<div class="util-deck-body"><div class="util-deck-title">'.encode($eBlockChild['title']).'</div></div>'.
					'<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>'.
				'</a>'.
				'<div class="util-deck-fold"><div class="util-deck-content">'.$content.'</div></div>'.
			'</div>',
			BlockTypeLib::TABS => $content,
			BlockTypeLib::FIGURE => '<div class="util-figure">'.
				'<div class="util-figure-value">'.encode($eBlockChild['title']).'</div>'.
				(($eBlockChild['content'] !== NULL) ? '<div class="util-figure-label">'.$content.'</div>' : '').
			'</div>',
		};

	}

	/**
	 * L'instant d'une étape dans la vidéo de son objet, qui la lance à ce moment-là (`BlockVideo.jump()`,
	 * `read.js`). Le script ne lit le bouton que sous cette parenté, que ce rendu est seul à écrire, et
	 * ne prend de lui que l'entier de `data-block-at`. Une étape sans instant n'a pas de bouton : la
	 * lecture retire aussi celui d'une étape dont l'objet n'a pas de vidéo (`BlockLib::getReadable()`).
	 * Comme la capture, l'instant n'arrive que par l'API : l'éditeur le montre sous le titre, en
	 * libellé, puisqu'un clic sur l'étape l'y ouvre à l'écriture et que l'étape n'y est pas sous la
	 * parenté que le script écoute. L'enregistrement d'une étape à l'écran le garde
	 * (`Block::settings.kept`). La clé `at` est absente d'une étape sans instant : c'est un réglage
	 * libre, pas une propriété que la sélection chargerait.
	 */
	protected function getStepAt(Block $eBlockChild, bool $read): string {

		if(array_key_exists('at', $eBlockChild['settings']) === FALSE) {
			return '';
		}

		$at = $eBlockChild['settings']['at'];

		if($read === FALSE) {
			return '<div class="block-step-at color-muted">'.\Asset::icon('play-fill').' '.$this->formatAt($at).'</div>';
		}

		\Asset::js('block', 'read.js');

		return '<div class="block-step-at">'.
			'<a class="btn btn-sm btn-outline-primary" data-block-at="'.encode($at).'">'.\Asset::icon('play-fill').' '.$this->formatAt($at).'</a>'.
		'</div>';

	}

	/**
	 * Un instant écrit comme sous une vidéo : `1:05`, et `1:02:05` passé une heure.
	 */
	public function formatAt(int $at): string {

		$hours = intdiv($at, 3600);
		$minutes = intdiv($at % 3600, 60);
		$seconds = $at % 60;

		if($hours > 0) {
			return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
		} else {
			return sprintf('%d:%02d', $minutes, $seconds);
		}

	}

	/**
	 * La capture d'une étape, sous son texte, à la taille de lecture du type (`block-item`,
	 * `Media.s.php`), et qui mène à l'image entière : une capture d'écran réduite ne se lit pas
	 * toujours. Elle n'arrive que par l'API (`block/{id}/media`) : l'éditeur la montre sous les champs
	 * sans proposer de la changer, et une étape qui naît du « + » n'en a donc pas.
	 *
	 * À la lecture, un bouton posé sur la capture dit qu'elle s'agrandit (`getStepZoom()`). Il n'est pas
	 * un second lien : c'est le lien de la capture qui l'ouvre. Dans l'éditeur, où le clic sur l'étape
	 * l'ouvre à l'écriture, il n'a pas sa place, comme le bouton de l'instant (`getStepAt()`).
	 */
	protected function getStepMedia(Block $eBlockChild, bool $read): string {

		if(
			$eBlockChild->exists() === FALSE or
			$eBlockChild['media'] === NULL
		) {
			return '';
		}

		$ui = new \media\BlockItemUi();

		return '<a href="'.encode($ui->getUrlByHash($eBlockChild['media'])).'" target="_blank" class="block-step-media">'.
			'<img src="'.encode($ui->getUrlByHash($eBlockChild['media'], 'm')).'" alt="'.encode($eBlockChild['title']).'" loading="lazy"/>'.
			$this->getStepMarks($eBlockChild).
			($read ? $this->getStepZoom($eBlockChild) : '').
		'</a>';

	}

	/**
	 * Le bouton d'agrandissement se pose dans le premier coin de la capture qu'aucun repère n'atteint :
	 * posé sur un repère, il cacherait ce que l'étape désigne. Le coin se mesure en pourcentage de
	 * l'image, comme les repères, sur la plus petite capture qu'un écran rende, dans la colonne d'un
	 * écran de 360 px : 299 px de large, et 150 px de haut pour une capture d'écran deux fois plus large
	 * que haute, l'allongement le plus fort qu'on prévoie (le média n'en borne aucun). Le bouton et son
	 * écart au bord, 53 px, y prennent 18 % de la largeur et 36 % de la hauteur, arrondis à 20 % et
	 * 38 %. Un repère compte avec ce qu'il peint au-delà de sa position (`read.css`) : 6 px de contour et
	 * de liseré autour d'un cadre, soit 2 % et 4 %, et un anneau de 16 px de rayon autour de son point,
	 * soit 6 % et 11 %. Quand chaque coin porte un repère, il n'y a pas de bouton : la capture s'ouvre
	 * toujours d'un clic. Comme pour `getStepMarks()`, la clé `marks` manque au réglage libre d'une
	 * étape sans repère.
	 */
	protected function getStepZoom(Block $eBlockChild): string {

		$zones = [];

		foreach($eBlockChild['settings']['marks'] ?? [] as $mark) {
			$zones[] = match($mark['type']) {
				'box' => [$mark['x'] - 2, $mark['y'] - 4, $mark['x'] + $mark['w'] + 2, $mark['y'] + $mark['h'] + 4],
				'ring' => [$mark['x'] - 6, $mark['y'] - 11, $mark['x'] + 6, $mark['y'] + 11],
			};
		}

		$corners = [
			'bottom-right' => [80, 62, 100, 100],
			'bottom-left' => [0, 62, 20, 100],
			'top-right' => [80, 0, 100, 38],
			'top-left' => [0, 0, 20, 38],
		];

		foreach($corners as $corner => [$left, $top, $right, $bottom]) {

			$reached = array_any($zones, fn(array $zone) => (
				$zone[0] < $right and $zone[2] > $left and
				$zone[1] < $bottom and $zone[3] > $top
			));

			if($reached === FALSE) {
				return '<span class="block-step-zoom block-step-zoom-'.$corner.'">'.\Asset::icon('arrows-angle-expand').'</span>';
			}

		}

		return '';

	}

	/**
	 * Les repères posés sur la capture d'une étape (`settings.marks`), en pourcentage de l'image : un
	 * cadre autour d'une zone, un anneau autour d'un point. Ils sont rendus partout où la capture l'est,
	 * dans l'éditeur aussi, seul endroit où l'auteur apprend qu'une étape en porte : ils n'arrivent que
	 * par l'API, comme la capture. La position s'écrit depuis les nombres que le réglage a reconstruits
	 * (`BlockTypeLib::castMark()`), jamais depuis ce qui a été posté, et la classe se choisit sur le
	 * type. La clé `marks` est absente d'une étape sans repère : c'est un réglage libre, pas une
	 * propriété que la sélection chargerait.
	 */
	protected function getStepMarks(Block $eBlockChild): string {

		if(array_key_exists('marks', $eBlockChild['settings']) === FALSE) {
			return '';
		}

		$h = '';

		foreach($eBlockChild['settings']['marks'] as $mark) {

			$h .= match($mark['type']) {
				'box' => '<span class="block-step-mark block-step-mark-box" style="'.sprintf('left: %.2F%%; top: %.2F%%; width: %.2F%%; height: %.2F%%', $mark['x'], $mark['y'], $mark['w'], $mark['h']).'"></span>',
				'ring' => '<span class="block-step-mark block-step-mark-ring" style="'.sprintf('left: %.2F%%; top: %.2F%%', $mark['x'], $mark['y']).'"></span>',
			};

		}

		return $h;

	}

	/**
	 * Le bloc ouvert, dans le balisage de sa lecture : le titre d'un encadré s'écrit dans
	 * `util-card-title`, la valeur d'un chiffre dans `util-figure-value`, l'auteur d'une citation dans
	 * son annotation. Les champs n'ont ni cadre ni fond (`util-inplace-field`) : on écrit sur
	 * ce qu'on lit.
	 *
	 * Le texte prend les options d'éditeur de l'usage que la page pose sur le bloc (`editorOptions`),
	 * les mêmes que celles qu'applique `Block::content` à l'écriture, et s'écrit à la place de son
	 * rendu (`inline`), sans le bandeau « Éditeur de texte ».
	 *
	 * `$draft` est la clé du brouillon de l'éditeur, que l'éditeur prend aussi pour son id
	 * (`editor-<clé>`) : c'est l'appelant qui sait sous quel nom le texte se retrouvera
	 * (`BlockUi`). Sans clé, l'éditeur prend un id fixe par type : un seul bloc à écrire du « + » est
	 * ouvert à la fois, et le script de son template cite cet id.
	 *
	 * Un parent à écrire montre le formulaire de sa première étape, qui naît avec lui
	 * (`BlockTypeLib::getPropertiesCreate()`), dans la forme du parent, et déjà le « + » de ses enfants
	 * au bout : le deuxième enfant s'ajoute sans enregistrer d'abord, le script faisant naître le
	 * parent avant de rejouer le geste sur son « + » rendu
	 * (`BlockEditor.insertInNew()`). La couleur d'un encadré, la taille d'une vidéo et le repère des
	 * étapes se choisissent dans la barre du bloc (`BlockUi::getVariantMenu()`), qui écrit le champ
	 * caché posé ici et échange la classe du bloc (`data-block-variant-target`), comme la meilleure
	 * réponse, sans sa pastille : le champ du titre garde la largeur de la carte. La première réponse
	 * naît ordinaire, avec son parent (`BlockLib::create()`), sans variante à choisir.
	 *
	 * Une section s'écrit dans sa tête, le surtitre dans `util-label`, le titre dans le `h2`. Les deux
	 * refus s'écrivent sous la tête, qui porte les deux noms en `data-wrapper` : dans le `h2`, ou à
	 * droite du titre dans la rangée de `util-title`, ils ne se liraient pas. Le titre d'une carte
	 * reçoit les siens pour la même raison dans un conteneur vide qui le suit, `util-card-title`
	 * étant une rangée : sans conteneur, le refus irait au bandeau, loin de la carte fautive.
	 */
	public function getEditable(Block $eBlock, \util\FormUi $form, ?string $draft): string {

		return match($eBlock['type']) {
			BlockTypeLib::TEXT => $this->getContentField($eBlock, $form, $draft),
			BlockTypeLib::CALLOUT, BlockTypeLib::ANSWER => '<div class="util-card util-card-accent '.$this->getVariantClass($eBlock['type'], $eBlock['variant']).'" data-block-variant-target>'.
				$form->hidden('variant', $eBlock['variant']).
				'<div class="util-card-title">'.$this->getTitleField($eBlock, $form).'</div>'.
				'<div data-wrapper="title"></div>'.
				$this->getContentField($eBlock, $form, $draft).
			'</div>',
			BlockTypeLib::QUOTE => '<div class="readitor">'.
				'<div class="editor-quote">'.
					'<div class="editor-quote-image">'.\Asset::icon('chat-quote').'</div>'.
					'<blockquote>'.
						$this->getContentField($eBlock, $form, $draft).
						'<p class="util-annotation">'.$this->getTitleField($eBlock, $form).'</p>'.
					'</blockquote>'.
				'</div>'.
			'</div>',
			BlockTypeLib::TABLE => $this->getTableEditable($eBlock, $form),
			BlockTypeLib::VIDEO => $this->getVideoEditable($eBlock, $form, $draft),
			BlockTypeLib::BUTTON => $this->getButtonEditable($eBlock, $form),
			BlockTypeLib::TAGS => $this->getTagsEditable($eBlock, $form),
			BlockTypeLib::SECTION => '<div class="block-section-head" data-wrapper="settings title">'.
				'<div class="util-label">'.$form->text('settings[kicker]', $eBlock['settings']['kicker'], ['class' => 'util-inplace-field', 'placeholder' => s("Surtitre (facultatif)")]).'</div>'.
				'<div class="util-title"><h2>'.$this->getTitleField($eBlock, $form).'</h2></div>'.
			'</div>',
			BlockTypeLib::ITEM => $this->getChildEditable($eBlock['parentType'], $eBlock, $form, $draft),
			BlockTypeLib::STEPS => $form->hidden('variant', $eBlock['variant']).
				$this->getStepsList($eBlock, '<li class="util-procedure-step">'.$this->getChildEditable($eBlock['type'], $eBlock, $form, $draft).'</li>', TRUE).
				$this->getChildInsert($eBlock),
			BlockTypeLib::ACCORDION => $this->getChildEditable($eBlock['type'], $eBlock, $form, $draft).$this->getChildInsert($eBlock),
			BlockTypeLib::FIGURE => '<div class="util-figures">'.$this->getChildEditable($eBlock['type'], $eBlock, $form, $draft).$this->getChildInsert($eBlock).'</div>',
			BlockTypeLib::ANSWERS => '<div class="util-cards util-cards-2 block-answers">'.
				'<div class="util-card util-card-accent '.$this->getVariantClass(BlockTypeLib::ANSWER, BlockTypeLib::REGULAR).'">'.
					'<div class="util-card-title">'.$this->getTitleField($eBlock, $form).'</div>'.
					'<div data-wrapper="title"></div>'.
					$this->getContentField($eBlock, $form, $draft).
				'</div>'.
			'</div>'.
			$this->getChildInsert($eBlock),
			BlockTypeLib::TABS => '<div class="tabs tabs-h">'.
				'<div class="tabs-item"><span class="tab-item selected">'.$this->getTitleField($eBlock, $form).'</span>'.$this->getChildInsert($eBlock).'</div>'.
				'<div class="tab-panel selected"><div data-wrapper="title"></div>'.$this->getContentField($eBlock, $form, $draft).'</div>'.
			'</div>',
		};

	}

	/**
	 * La vidéo ouverte : son cadre une fois qu'elle est enregistrée, puis l'adresse, puis la légende à
	 * la place où elle se lit, et son texte dans l'ordre de la lecture, avant la vidéo quand elle est à
	 * droite. Un bloc à écrire n'a pas de cadre, faute d'adresse : il n'y a pas d'aperçu avant
	 * l'enregistrement. L'adresse affichée est celle qui a été collée, jamais celle du lecteur. La
	 * disposition se choisit dans la barre du bloc (`BlockUi::getVariantMenu()`), qui écrit le champ
	 * caché posé ici et échange la classe de l'enveloppe (`data-block-variant-target`).
	 *
	 * Le champ du texte est rendu dans toutes les dispositions, et `block.css` le masque en pleine
	 * largeur et en petite taille : `build()` vide une propriété de la liste que le formulaire ne
	 * poste pas, et un champ retiré effacerait le texte au premier enregistrement d'une vidéo repassée
	 * à une taille.
	 */
	protected function getVideoEditable(Block $eBlock, \util\FormUi $form, ?string $draft): string {

		$h = '<div class="'.$this->getVariantClass($eBlock['type'], $eBlock['variant']).'" data-block-variant-target>';

			$figure = '<figure class="block-video">';

				$figure .= $form->hidden('variant', $eBlock['variant']);

				if($eBlock->exists()) {
					$figure .= $this->getVideoFrame($eBlock);
				}

				$figure .= '<div class="block-video-url" data-wrapper="url">';
					$figure .= $form->dynamicField($eBlock, 'url', function(\PropertyDescriber $d) {
						$d->placeholder = s("Adresse de la vidéo");
						$d->after = \util\FormUi::info(new \editor\EditorUi()->getVideoNames());
					});
				$figure .= '</div>';

				$figure .= '<figcaption>'.$this->getTitleField($eBlock, $form).'</figcaption>';

			$figure .= '</figure>';

			$text = '<div class="block-video-text">'.$this->getContentField($eBlock, $form, $draft).'</div>';

			$h .= match($eBlock['variant']) {
				BlockTypeLib::FULL, BlockTypeLib::SMALL, BlockTypeLib::LEFT => $figure.$text,
				BlockTypeLib::RIGHT => $text.$figure,
			};

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bouton ouvert : son libellé s'écrit dans le bouton, l'adresse dans un champ dessous. Le bouton
	 * est un `span`, il n'y a rien à suivre pendant qu'on écrit, et `btn-readonly` lui retire le fond
	 * que le survol donne à un bouton qui se clique.
	 */
	protected function getButtonEditable(Block $eBlock, \util\FormUi $form): string {

		$h = '<div class="block-button">';
			$h .= '<div data-wrapper="title">';
				$h .= '<span class="btn btn-outline-primary btn-readonly">'.$this->getTitleField($eBlock, $form).' '.\Asset::icon('box-arrow-up-right').'</span>';
			$h .= '</div>';
			$h .= '<div class="block-button-url" data-wrapper="url">';
				$h .= $form->dynamicField($eBlock, 'url', fn(\PropertyDescriber $d) => $d->placeholder = s("Adresse de la page"));
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le tableau ouvert garde la grille de sa lecture, chaque cellule écrite en place (`util-inplace-field`).
	 * Une colonne de roues le précède, une par ligne, et chaque intitulé porte la sienne au-dessus de
	 * lui : elles ouvrent l'insertion et la suppression d'une ligne ou d'une colonne
	 * (`BlockTable`, `block.js`). « + Ligne » et « + Colonne » ajoutent en bout, sous le tableau, où
	 * ils restent visibles à toute largeur. Les refus de la grille s'écrivent sous elle
	 * (`data-wrapper="settings"`).
	 *
	 * Les cellules se nomment `settings[rows][<ligne>][<colonne>]`, la forme de `Block::settings`, que
	 * le script renumérote à chaque ligne ou colonne ajoutée ou retirée. Une ligne ou une colonne
	 * ajoutée prend sa roue d'un `template` : une roue déjà ouverte porte l'identifiant que
	 * `Lime.Dropdown` lui a donné, qu'une copie dupliquerait. Sur un tableau neuf, l'invite
	 * de la première cellule dit qu'un tableur se colle.
	 */
	protected function getTableEditable(Block $eBlock, \util\FormUi $form): string {

		$rows = $eBlock['settings']['rows'];
		$isNew = $eBlock->exists() === FALSE;

		$h = '<div class="util-overflow">';
			$h .= '<div class="util-table util-table-even util-table-sticky-column block-table block-table-edit" style="--block-table-columns: '.count($rows[0]).'" data-block-table>';

				foreach($rows as $position => $row) {

					if($position === 0) {

						$h .= '<div class="util-table-head">';
							$h .= '<div></div>';

							foreach($row as $column => $cell) {
								$h .= '<div class="block-table-head">';
									$h .= $form->text('settings[rows][0]['.$column.']', $cell, ['class' => 'util-inplace-field', 'placeholder' => $isNew ? s("Intitulé") : NULL]);
									$h .= $this->getTableMenu('column');
								$h .= '</div>';
							}

						$h .= '</div>';

					} else {

						$h .= '<div class="util-table-row">';
							$h .= '<div>'.$this->getTableMenu('row').'</div>';

							foreach($row as $column => $cell) {
								$placeholder = ($isNew and $position === 1 and $column === 0) ? s("Écrire ou coller depuis un tableur") : NULL;
								$h .= '<div>'.$form->text('settings[rows]['.$position.']['.$column.']', $cell, ['class' => 'util-inplace-field', 'placeholder' => $placeholder]).'</div>';
							}

						$h .= '</div>';

					}

				}

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<template data-block-table-gear="row">'.$this->getTableMenu('row').'</template>';
		$h .= '<template data-block-table-gear="column">'.$this->getTableMenu('column').'</template>';

		$h .= '<div data-wrapper="settings"></div>';

		$h .= '<div class="block-insert-table">';
			$h .= '<a class="btn btn-sm btn-outline-primary" data-block-table-add="row">'.\Asset::icon('plus-lg').' '.s("Ligne").'</a>';
			$h .= '<a class="btn btn-sm btn-outline-primary" data-block-table-add="column">'.\Asset::icon('plus-lg').' '.s("Colonne").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les étiquettes ouvertes gardent la rangée de leur lecture, chaque mot écrit dans sa pastille
	 * (`util-inplace-field`), avec sa croix. « + Étiquette » ajoute une pastille en bout de rangée, Entrée une
	 * pastille après celle où l'on écrit (`BlockTags`, `block.js`). Au plafond
	 * (`BlockSetting::TAGS_MAX`), que le script lit sur la rangée, le « + » disparait et Entrée ne pose
	 * plus rien. Les refus s'écrivent sous la rangée (`data-wrapper="settings"`).
	 *
	 * La rangée n'est jamais sans pastille : des étiquettes enregistrées sans mot s'ouvrent, comme des
	 * neuves, sur une pastille vide, et retirer la dernière en rend une vide. La pastille ajoutée vient
	 * d'un `template`, dont les mots restent traduits ici.
	 */
	protected function getTagsEditable(Block $eBlock, \util\FormUi $form): string {

		$tags = $eBlock['settings']['tags'] ?: [''];

		$h = '<div class="badges block-tags block-tags-edit" data-block-tags data-block-tags-max="'.BlockSetting::TAGS_MAX.'">';

			foreach($tags as $tag) {
				$h .= $this->getTagEditable($form, $tag);
			}

			$h .= '<a class="btn btn-sm btn-outline-primary'.(count($tags) >= BlockSetting::TAGS_MAX ? ' hide' : '').'" data-block-tags-add>'.\Asset::icon('plus-lg').' '.s("Étiquette").'</a>';

		$h .= '</div>';

		$h .= '<template data-block-tags-new>'.$this->getTagEditable($form, '').'</template>';

		$h .= '<div data-wrapper="settings"></div>';

		return $h;

	}

	protected function getTagEditable(\util\FormUi $form, string $tag): string {

		$h = '<span class="badge badge-neutral">';
			$h .= $form->text('settings[tags][]', $tag, ['class' => 'util-inplace-field util-inplace-field-fit', 'placeholder' => s("Étiquette"), 'size' => $this->getFieldSize($tag)]);
			$h .= '<a class="block-tags-remove" data-block-tags-remove>'.\Asset::icon('x-lg').'</a>';
		$h .= '</span>';

		return $h;

	}

	/**
	 * La roue d'une ligne ou d'une colonne. `Lime.Dropdown` pose son menu dans `body` à l'ouverture :
	 * le script retient la roue ouverte (`BlockTable.trigger`) pour savoir sur quelle ligne ou quelle
	 * colonne agir.
	 */
	protected function getTableMenu(string $axis): string {

		[$title, $items] = match($axis) {
			'row' => [s("Ligne"), [
				'row-before' => s("Insérer une ligne au-dessus"),
				'row-after' => s("Insérer une ligne en dessous"),
				'row-delete' => s("Supprimer la ligne"),
			]],
			'column' => [s("Colonne"), [
				'column-before' => s("Insérer une colonne à gauche"),
				'column-after' => s("Insérer une colonne à droite"),
				'column-delete' => s("Supprimer la colonne"),
			]],
		};

		$h = '<a data-dropdown="bottom-start" data-block-table-menu="'.$axis.'" class="btn btn-sm btn-color-primary dropdown-toggle" title="'.$title.'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';

			foreach($items as $action => $label) {
				$h .= '<a class="dropdown-item" data-block-table-action="'.$action.'">'.$label.'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un enfant ouvert, dans la forme que lui donne son parent. Le nom d'un onglet se lit dans la tête
	 * de son parent, hors du panneau où vit le formulaire : il attend dans `data-block-head`, rattaché
	 * au formulaire par l'attribut `form`, et le script le pose dans la tête à l'ouverture
	 * (`BlockEditor.fillBand()`).
	 *
	 * Le refus d'un nom vide (`Block::title.tabName`) s'écrit dans le conteneur du champ, cherché par
	 * `Lime.Alert.showErrors()` dans le formulaire seul : `data-block-head`, qui reste en tête du
	 * panneau, en porte le `data-wrapper`, et `block.css` le montre alors. Le premier onglet d'un bloc
	 * qui naît a son champ dans la tête, dans le formulaire : un conteneur vide en tête du panneau
	 * (`getEditable()`) reçoit le refus à la même place. Le titre d'une étape, d'un volet ou d'un
	 * chiffre a de même un conteneur vide juste après lui, hors de la rangée du volet et de la grande
	 * taille du chiffre.
	 */
	protected function getChildEditable(string $parentType, Block $eBlock, \util\FormUi $form, ?string $draft): string {

		$content = $this->getContentField($eBlock, $form, $draft);

		if($parentType === BlockTypeLib::TABS) {
			return '<div data-block-head data-wrapper="title">'.$this->getTitleField($eBlock, $form, new BlockUi()->getFormId($eBlock)).'</div>'.$content;
		}

		$title = $this->getTitleField($eBlock, $form);
		$error = '<div data-wrapper="title"></div>';

		return match($parentType) {
			BlockTypeLib::STEPS => '<h4 class="util-procedure-title">'.$title.'</h4>'.$error.$this->getStepAt($eBlock, FALSE).$content.$this->getStepMedia($eBlock, FALSE),
			BlockTypeLib::ACCORDION => '<div class="util-deck util-deck-open">'.
				'<div class="util-deck-head">'.
					'<div class="util-deck-body"><div class="util-deck-title">'.$title.'</div>'.$error.'</div>'.
					'<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>'.
				'</div>'.
				'<div class="util-deck-fold"><div class="util-deck-content">'.$content.'</div></div>'.
			'</div>',
			BlockTypeLib::FIGURE => '<div class="util-figure">'.
				'<div class="util-figure-value">'.$title.'</div>'.
				$error.
				'<div class="util-figure-label">'.$content.'</div>'.
			'</div>',
		};

	}

	protected function getContentField(Block $eBlock, \util\FormUi $form, ?string $draft): string {

		$placeholder = match($eBlock['parentType'] ?? $eBlock['type']) {
			BlockTypeLib::TEXT => NULL,
			BlockTypeLib::QUOTE => s("La citation"),
			BlockTypeLib::FIGURE => s("Ce que le chiffre mesure"),
			BlockTypeLib::CALLOUT, BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS, BlockTypeLib::ANSWERS => s("Le texte"),
			BlockTypeLib::VIDEO => s("Le texte à côté de la vidéo"),
		};

		return $form->dynamicField($eBlock, 'content', function(\PropertyDescriber $d) use($eBlock, $draft, $placeholder) {

			$d->options = ['intro' => FALSE, 'inline' => TRUE, 'draft' => $draft] + $eBlock['editorOptions'];
			$d->placeholder = $placeholder;
			$d->attributes = ['class' => 'util-inplace-field'];

			if($draft === NULL) {
				$d->attributes['id'] = 'editor-block-new-'.$eBlock['type'];
			}

		});

	}

	/**
	 * Le titre s'écrit à la place où il se lit, et son texte d'attente dit ce qu'il devient : la
	 * valeur d'un chiffre, l'auteur d'une citation, le nom d'un onglet, la légende d'une vidéo, le
	 * libellé d'un bouton. Un enfant le prend du type de son parent : chaque parent a son enfant à
	 * écrire (`BlockLib::getNew()`).
	 *
	 * Le nom d'un onglet se tient dans la tête, le libellé d'un bouton dans le bouton, chacun à la
	 * largeur de ce qu'il porte : `field-sizing` là où le navigateur le connaît, l'attribut `size`
	 * ailleurs (Firefox), que le script tient à jour à la frappe (`BlockEditor.sizeField()`).
	 * `$formId` rattache le nom d'un onglet à son formulaire, qu'il quitte à l'ouverture.
	 */
	protected function getTitleField(Block $eBlock, \util\FormUi $form, ?string $formId = NULL): string {

		$type = $eBlock['parentType'] ?? $eBlock['type'];

		$placeholder = match($type) {
			BlockTypeLib::CALLOUT => s("Titre de l'encadré"),
			BlockTypeLib::STEPS => s("Titre de l'étape"),
			BlockTypeLib::ACCORDION => s("Titre du volet"),
			BlockTypeLib::TABS => s("Nom de l'onglet"),
			BlockTypeLib::FIGURE => s("Chiffre"),
			BlockTypeLib::QUOTE => s("Auteur de la citation"),
			BlockTypeLib::VIDEO => s("Légende de la vidéo"),
			BlockTypeLib::BUTTON => s("Libellé du bouton"),
			BlockTypeLib::SECTION => s("Titre de la section"),
			BlockTypeLib::ANSWERS => s("Titre de la réponse"),
		};

		$attributes = ['class' => 'util-inplace-field'];

		if(in_array($type, [BlockTypeLib::TABS, BlockTypeLib::BUTTON], TRUE)) {
			$attributes['class'] .= ' util-inplace-field-fit';
			$attributes['size'] = $this->getFieldSize($eBlock['title'] ?? '');
		}

		if($formId !== NULL) {
			$attributes['form'] = $formId;
		}

		return $form->dynamicField($eBlock, 'title', function(\PropertyDescriber $d) use($placeholder, $attributes) {
			$d->placeholder = $placeholder;
			$d->attributes = $attributes;
		});

	}

	/**
	 * La largeur d'un champ qui s'écrit à la largeur de ce qu'on tape, sous Firefox, où `field-sizing`
	 * n'existe pas : le nom d'un onglet, le libellé d'un bouton, une étiquette. Le script la tient à
	 * jour à la frappe (`BlockEditor.sizeField()`), avec le même plancher de sept caractères, pour que
	 * le champ reste une cible.
	 */
	protected function getFieldSize(string $value): int {
		return max(mb_strlen($value) + 1, 7);
	}

}
?>
