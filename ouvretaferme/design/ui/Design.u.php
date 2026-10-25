<?php
namespace design;

class DesignUi {

	use \Notifiable;

	/**
	 * La page se range en familles, chacune nommée par la question que pose celui qui dessine ou code
	 * un écran. Une section n'appartient qu'à une famille, et l'index par besoin, hors famille, ferme
	 * la page. Une famille s'ouvre sur son tableau « Lequel ? » quand elle a ses fiches, `which` une
	 * ligne par fiche. Une famille de tokens a son « Lequel ? » sans fiche : `headings` donne alors à
	 * chaque section le titre que lui aurait donné sa fiche.
	 */
	private function families(): array {

		$families = [
			'regles' => [
				'title' => s("Règles de l'interface"),
				'question' => s("Ce qui vaut pour tous les écrans"),
				'sections' => ['conventions'],
			],
			'fondations' => [
				'title' => s("Fondations"),
				'question' => s("Quelle valeur ?"),
				'which' => [
					[s("Titrer un écran, une section ou une sous-partie"), 'h1 · h2 · h3 · h4 · h5', 'titles'],
					[s("Choisir la taille d'un texte, jamais sous le plancher de 0,9 rem"), '1rem · 0.9rem', 'textSizes'],
					[s("Aligner des numéros ou des références en colonne"), 'mono', 'mono'],
					[s("Colorer selon la marque ou un état"), '--primary · --danger', 'colors'],
					[s("Adapter les composants du socle au thème d'un package"), '--callout-* · --marker-*', 'themeTokens'],
					[s("Arrondir un bloc, ou poser une ombre douce en surimpression"), '--radius · --gradient', 'radius'],
					[s("Poser une marge, un remplissage ou un écart"), 'var(--space-md)', 'spaces'],
					[s("Dire de quel univers vient un bloc ou une ligne"), 'util-domain', 'domains'],
					[s("Poser une icône en pastille, à l'encre d'un univers"), 'util-icon', 'iconDiscs'],
					[s("Poser le logo d'un service dans une ligne de texte, à sa hauteur"), 'util-logo', 'iconDiscs'],
					[s("Colorer une famille de critères de notation"), '--criterion-*', 'criteria'],
				],
				'headings' => [
					'titles' => s("Titres"),
					'textSizes' => s("Taille d'un texte"),
					'mono' => 'mono',
					'colors' => s("Couleurs"),
					'themeTokens' => s("Jetons de thème"),
					'radius' => s("Arrondi et ombre"),
					'spaces' => s("Espacements"),
					'domains' => 'util-domain',
					'iconDiscs' => 'util-icon',
					'criteria' => s("Critères de notation"),
				],
				'sections' => ['titles', 'textSizes', 'mono', 'colors', 'themeTokens', 'radius', 'spaces', 'domains', 'iconDiscs', 'criteria'],
			],
			'cadre' => [
				'title' => s("Mettre en page un écran"),
				'question' => s("Quel cadre ?"),
				'which' => [
					[s("Poser la tête d'un écran de structure, son titre, ce qui le situe et ses gestes"), 'util-page-header', 'pageHeader'],
					[s("Poser un contenu sur la feuille des écrans d'une structure, hors de leur gabarit"), 'util-paper', 'paper'],
					[s("Garder un résumé en vue à côté du contenu, comme un panier"), 'util-layout-aside', 'aside'],
					[s("Lire un document déposé à côté de ce qu'on en fait"), 'util-preview', 'previews'],
					[s("Réunir des colonnes titrées sous un titre, comme l'accueil d'une structure"), 'util-board', 'board'],
					[s("Séparer une bande du fond de page par un coup de pinceau"), 'DesignUi::getBrushSeparator() + util-brushed', 'brushed'],
					[s("Encadrer un contenu dans un bloc"), 'util-block', 'contentBlocks'],
					[s("Replier une section par son titre"), 'util-fold', 'collapsible'],
					[s("Poser un titre de section et son action à droite"), 'util-title · util-action', 'actionBars'],
					[s("Montrer une diapositive à la fois dans une place étroite"), 'util-carousel', 'carousel'],
				],
				'sections' => ['pageHeader', 'paper', 'aside', 'previews', 'board', 'brushed', 'contentBlocks', 'collapsible', 'actionBars', 'carousel', 'templates'],
			],
			'objet' => [
				'title' => s("Contenir un objet"),
				'question' => s("Quel bloc ?"),
				'which' => [
					[s("Montrer un objet parmi d'autres, avec son état et ses chiffres"), 'DesignUi::getDeckHead() + util-deck', 'decks'],
					[s("Montrer l'objet seul et tout ce qui s'y rattache"), 'util-record', 'records'],
					[s("Demander une action et porter le bouton qui la fait"), 'util-card-action', 'actionCards'],
					[s("Résumer un univers par son chiffre, une liste courte et ses liens"), 'util-tile', 'tiles'],
					[s("Poser une image et son texte"), 'util-vignette', 'vignettes'],
					[s("Aligner des libellés et leurs valeurs"), 'util-presentation', 'presentations'],
					[s("Encadrer un contenu qui n'a pas de forme à lui"), 'util-card', 'cards'],
				],
				'sections' => ['decks', 'records', 'actionCards', 'tiles', 'vignettes', 'presentations', 'presentationDetail', 'cards', 'cardsAccent'],
			],
			'etat' => [
				'title' => s("Dire un état"),
				'question' => s("Quelle marque ?"),
				'which' => [
					[s("Dire où en est une chose, d'un mot"), 'badge', 'marks'],
					[s("Compter, d'un nombre"), 'util-counter', 'counters'],
					[s("Signaler un fait en marge d'une ligne"), 'util-stamp', 'stamps'],
					[s("Accoler une marque et son détail"), 'badge-split', 'badges'],
					[s("Dire qu'une chose est allumée ou éteinte"), 'util-icon-active', 'statusDots'],
					[s("Dire combien une valeur est sûre"), 'util-confidence', 'reliability'],
					[s("Marquer une valeur reprise d'un autre élément"), 'util-follow', 'follows'],
					[s("Marquer une case que le logiciel ne calcule pas"), 'util-not-auto', 'notAuto'],
					[s("Dire d'une icône colorée si une ligne est conforme, à surveiller ou en défaut"), 'color-success + icône', 'status'],
				],
				'sections' => ['marks', 'counters', 'stamps', 'badges', 'statusDots', 'reliability', 'follows', 'notAuto', 'status'],
			],
			'vide' => [
				'title' => s("Le vide et l'invitation"),
				'question' => s("Que montrer quand il n'y a rien ?"),
				'which' => [
					[s("Dire qu'une liste, un panneau ou une recherche sont vides, ou y inviter au premier geste"), 'DesignUi::getBlockEmpty() + util-block-empty', 'cta'],
					[s("Constater un bloc ou une valeur vides"), 'util-empty', 'empty'],
					[s("Ouvrir un écran sur ce qui le gouverne, souvent une fonctionnalité à démarrer"), 'util-start', 'start'],
					[s("Accueillir sur un écran qu'on découvre"), 'DesignUi::getWelcome() + util-welcome', 'welcome'],
				],
				'sections' => ['cta', 'empty', 'start', 'welcome'],
			],
			'informer' => [
				'title' => s("Informer"),
				'question' => s("Comment dire sans interrompre ?"),
				'which' => [
					[s("Annoncer, confirmer, prévenir ou bloquer, dans un encadré"), 'util-block-info', 'tintedBlocks'],
					[s("Accompagner un champ ou une liste d'une note ou d'un message d'une ligne"), 'util-info', 'messages'],
					[s("Confirmer une action qui vient d'avoir lieu, sans prendre de place"), 'util-toast', 'flash'],
					[s("Détailler au survol ce qu'on pointe"), 'util-tip', 'tips'],
					[s("Ajouter un texte secondaire, au fil d'une ligne ou sous ce qu'il précise"), 'util-annotation', 'inline'],
					[s("Faire ressortir quelques mots dans un texte"), 'util-mark', 'highlight'],
					[s("Nommer une colonne ou un bloc sans lui donner de titre"), 'util-label', 'overline'],
					[s("Montrer une valeur telle qu'on la tape"), 'util-literal', 'typedValue'],
					[s("Marquer un objet de sa couleur, devant son nom"), 'util-dot', 'dots'],
					[s("Écrire un texte saisi sur plusieurs lignes, une valeur insécable ou une cellule sans objet"), 'TextUi::multiline()', 'texts'],
				],
				'sections' => ['tintedBlocks', 'messages', 'flash', 'tips', 'inline', 'highlight', 'overline', 'typedValue', 'dots', 'texts'],
			],
			'agir' => [
				'title' => s("Agir"),
				'question' => s("Quel bouton, quel geste ?"),
				'which' => [
					[s("Faire avancer la zone, envoyer un formulaire"), 'btn-primary', 'buttonsFull'],
					[s("Accompagner le plein d'un geste second"), 'btn-primary-light', 'buttonsLight'],
					[s("Toute autre action, Annuler comprise"), 'btn-outline-primary', 'buttonsOutline'],
					[s("Régler une ligne ou un écran, sur fond blanc"), 'DesignUi::getGear() + btn-color-primary', 'buttonsColor'],
					[s("Ouvrir un domaine depuis un écran qui n'appartient à aucun"), 'btn-production-light', 'buttonDomains'],
					[s("Proposer quelques grands choix qui structurent l'écran"), 'util-button', 'entries'],
					[s("Recevoir des fichiers glissés"), 'util-drop', 'drop'],
					[s("Ouvrir une liste de gestes depuis un bouton"), 'dropdown-list', 'dropdowns'],
					[s("Agir sur les lignes cochées d'une liste"), 'batch-group-wrapper', 'batch'],
					[s("Peser un geste qui a des conséquences"), 'util-weigh', 'weigh'],
					[s("Confirmer un geste qui part chez d'autres"), 'DesignUi::getConfirmPanel()', 'confirmPanel'],
					[s("Changer l'ordre en glissant"), 'util-drag-handle', 'drag'],
					[s("Cocher une chose faite d'un clic"), 'util-tick', 'ticks'],
					[s("Choisir un mode d'affichage et ses options"), 'util-soil-menu', 'displayMenu'],
					[s("Copier une adresse ou un code pour le coller ailleurs"), 'TextUi::copy()', 'copy'],
				],
				'sections' => ['buttonsUsage', 'buttonsFocus', 'sizes', 'buttonModifiers', 'buttonsFull', 'buttonsLight', 'buttonsOutline', 'buttonSelect', 'buttonsColor', 'buttonDomains', 'entries', 'drop', 'dropdowns', 'batch', 'weigh', 'confirmPanel', 'drag', 'ticks', 'displayMenu', 'copy'],
			],
			'saisir' => [
				'title' => s("Saisir"),
				'question' => s("Quel champ ?"),
				'which' => [
					[s("Répondre par oui ou par non dans un formulaire"), 'FormUi::yesNo()', 'fields'],
					[s("Dire à quoi sert un champ, ou comment le remplir"), 'FormUi::info()', 'fieldInfo'],
					[s("Fermer une option en disant pourquoi"), 'field-radio-group-reason', 'radios'],
					[s("Montrer les options d'un choix fermé en vignettes ou en couleurs"), 'util-choices', 'choices'],
					[s("Vider un champ d'un geste"), 'input-group-clear', 'clearable'],
					[s("Choisir dans une liste longue, ou créer"), "field = 'autocomplete'", 'autocomplete'],
					[s("Joindre un fichier d'un bouton, envoyé dès qu'il est choisi"), 'FormUi::fileButton()', 'fileButton'],
					[s("Ouvrir le détail d'une valeur lue en la touchant"), 'util-keypad-field', 'valueDetail'],
					[s("Cocher des dates, rangées par mois"), 'util-dates', 'dateChecks'],
					[s("Donner aux cases d'un formulaire le dessin des options de filtre"), 'util-filter-options-field', 'formOptions'],
					[s("Ranger des champs deux par ligne dans une bulle étroite"), 'util-fields', 'fieldsGrid'],
					[s("Encadrer des champs qui forment une notion dans un formulaire"), 'form-fieldset', 'fieldsets'],
					[s("Découper un formulaire long en rubriques"), 'FormUi::cards()', 'formChapters'],
					[s("Basculer un réglage binaire qui s'applique tout de suite"), 'TextUi::switch()', 'switches'],
					[s("Modifier une valeur sur place, sans formulaire"), 'util-quick', 'quick'],
					[s("Choisir sans quitter l'écran, au doigt"), 'util-sheet', 'sheet'],
					[s("Composer un objet en 3D"), 'util-composer', 'composer'],
				],
				'sections' => ['fields', 'fieldInfo', 'radios', 'choices', 'clearable', 'autocomplete', 'fileButton', 'valueDetail', 'dateChecks', 'formOptions', 'fieldsGrid', 'fieldsets', 'formChapters', 'switches', 'quick', 'sheet', 'composer'],
			],
			'chercher' => [
				'title' => s("Chercher et filtrer"),
				'question' => s("Comment réduire une liste ?"),
				'which' => [
					[s("Filtrer une liste par un formulaire de critères"), 'util-block-search', 'searches'],
					[s("Chercher et filtrer depuis un seul champ, chaque critère posé en jeton"), 'util-omnibox', 'omnibox'],
					[s("Filtrer une liste par une recherche suivie d'un jeton par critère, qui déplie ses valeurs"), 'DesignUi::getChipDropdown() + util-chips', 'chips'],
					[s("Filtrer par quelques options toujours visibles"), 'util-filters', 'filters'],
					[s("Nommer un interrupteur du bandeau des filtres, son nom basculant aussi le réglage"), 'a.util-filter-label', 'screenSettings'],
					[s("Dire ce qu'on regarde, et offrir d'en changer"), 'util-scope-line', 'scope'],
				],
				'sections' => ['searches', 'omnibox', 'chips', 'filters', 'screenSettings', 'scope'],
			],
			'lister' => [
				'title' => s("Lister et comparer"),
				'question' => s("Comment ranger des lignes ?"),
				'which' => [
					[s("Ranger des lignes sous des colonnes fixes"), 'table', 'tables'],
					[s("Ranger des lignes dont les colonnes se redistribuent sur petit écran"), 'util-table', 'grids'],
					[s("Lire une tête et ses lignes filles comme une seule bande"), 'tbody-group', 'groups'],
					[s("Dire par son fond qu'un rang est fait, à vérifier ou à traiter"), 'util-table-row-state', 'listStates'],
					[s("Colorer chaque cellule d'une matrice selon sa valeur"), 'util-heat', 'heat'],
					[s("Poser des plis côte à côte, un seul déplié"), 'util-bellows', 'bellows'],
					[s("Passer d'une page à l'autre d'une liste trop longue pour un écran"), 'TextUi::pagination()', 'pagination'],
					[s("Choisir une offre parmi quelques-unes et la mettre au panier"), 'util-seeds', 'seeds'],
					[s("Tenir un fil de messages datés"), 'util-comment', 'comments'],
					[s("Faire défiler en largeur un tableau trop large pour un petit écran"), 'util-overflow-sm', 'overflow'],
				],
				'sections' => ['tables', 'grids', 'groups', 'listStates', 'heat', 'bellows', 'pagination', 'seeds', 'comments', 'overflow'],
			],
			'naviguer' => [
				'title' => s("Naviguer"),
				'question' => s("Comment passer d'une vue à l'autre ?"),
				'which' => [
					[s("Ouvrir l'une après l'autre les vues d'un même objet"), 'DesignUi::getTabItem() + tabs', 'tabs'],
					[s("Poser la rangée dans le contenu d'une page plutôt que dans sa tête, simple, repliée en menu ou en tuiles"), 'tabs-h', 'tabsHorizontal'],
				],
				'sections' => ['tabs', 'tabsHorizontal'],
			],
			'chiffrer' => [
				'title' => s("Chiffrer"),
				'question' => s("Comment montrer un nombre ?"),
				'which' => [
					[s("Résumer un écran par quelques chiffres clés en tuiles"), 'DesignUi::getFigures() · DesignUi::getFigure() + util-figures', 'figures'],
					[s("Introduire un écran par des chiffres en ligne sous son titre"), 'util-figures-line', 'figuresLine'],
					[s("Faire reculer un chiffre passé derrière ses voisins"), 'util-figure-muted', 'figuresMuted'],
					[s("Marquer l'année affichée dans une rangée d'années"), 'util-figure-selected', 'figuresSelected'],
					[s("Parcourir une suite de chiffres de même nature"), 'util-figures-overflow', 'figuresScroll'],
					[s("Aligner quelques chiffres en bandeau, chacun ouvrant sa vue"), 'util-summarize', 'summarize'],
					[s("Dire une variation dans une ligne de tableau ou un prix"), 'util-variation', 'variation'],
					[s("Montrer d'un coup d'œil la part de chaque segment d'un tout"), 'util-ring', 'ring'],
					[s("Lire ou saisir une note sur cinq"), 'util-rating', 'ratings'],
					[s("Découper une piste en parts, une par état"), 'util-progress', 'progress'],
					[s("Placer des plages et un repère sur un axe gradué"), 'util-rail', 'rail'],
					[s("Graduer un axe de semaines ou de valeurs"), 'util-graduation', 'graduation'],
					[s("Donner la clé des couleurs d'une grille ou d'un graphique"), 'util-legend', 'legend'],
					[s("Comparer la forme de l'année de plusieurs lignes, mois par mois"), 'util-bars', 'bars'],
					[s("Colorer la France par département ou par région"), 'util-map', 'maps'],
					[s("Écrire un montant ou un pourcentage"), 'TextUi::money()', 'formats'],
				],
				'sections' => ['figures', 'figuresLine', 'figuresMuted', 'figuresSelected', 'figuresScroll', 'summarize', 'variation', 'ring', 'ratings', 'progress', 'rail', 'graduation', 'legend', 'bars', 'maps', 'formats'],
			],
			'temps' => [
				'title' => s("Situer dans le temps"),
				'question' => s("Comment montrer une date, une durée ?"),
				'which' => [
					[s("Dire mois par mois ce qui est traité, à traiter ou sans objet"), 'util-timeline', 'months'],
					[s("Placer des gestes datés et leurs durées sur un axe de jours"), 'util-timeline-event', 'timeline'],
					[s("Montrer où en est une campagne, d'un temps à l'autre"), 'util-campaign', 'campaign'],
					[s("Faire repérer une échéance avant de la lire"), 'util-calendar', 'calendars'],
					[s("Poser des dates retenues sur des pages de calendrier, mois par mois"), 'util-calendar-sheets', 'calendarSheets'],
					[s("Choisir un jour de la semaine et dire le temps de chacun"), 'util-days', 'days'],
				],
				'sections' => ['months', 'timeline', 'campaign', 'calendars', 'calendarSheets', 'days'],
			],
			'guider' => [
				'title' => s("Guider"),
				'question' => s("Comment mener d'une étape à l'autre ?"),
				'which' => [
					[s("Configurer une fonctionnalité vide, une question à la fois"), 'onboarding', 'onboarding'],
					[s("Faire suivre des étapes dans l'ordre, dans un contenu rédigé"), 'util-procedure', 'procedure'],
					[s("Faire vérifier des critères qui ne se suivent pas"), 'util-procedure-check', 'criteriaChecks'],
					[s("Mener les étapes d'une configuration, chacune avec son bloc"), 'util-procedure-icon', 'iconSteps'],
					[s("Aligner des dates à la suite sur un trait"), 'util-procedure-dots', 'chain'],
					[s("Montrer le trajet d'un flux, d'une étape à l'autre"), 'util-chain', 'route'],
					[s("Dire où en est le cycle d'un objet, étape par étape"), 'util-steps', 'steps'],
				],
				'sections' => ['onboarding', 'procedure', 'criteriaChecks', 'iconSteps', 'chain', 'route', 'steps'],
			],
			'animer' => [
				'title' => s("Animer un geste"),
				'question' => s("Quel mouvement ?"),
				'which' => [
					[s("Montrer qu'une écriture est passée, sur ce qu'elle a changé"), 'util-flash', 'feedback'],
					[s("Montrer qu'un bouton d'envoi attend la réponse du serveur"), 'util-pending', 'feedback'],
					[s("Montrer qu'un bloc attend son recalcul par le serveur"), 'util-waiting', 'feedback'],
					[s("Faire voir ce qu'une réponse retire"), 'util-vanish', 'vanish'],
				],
				'sections' => ['feedback', 'vanish', 'motion'],
			],
			'public' => [
				'title' => s("Pages publiques"),
				'question' => s("Comment parler à qui n'a pas de compte ?"),
				'which' => [
					[s("Ouvrir une page publique par une bande d'accueil"), 'util-page-hero', 'publicHero'],
					[s("Ouvrir un écran d'un groupe, d'un réseau ou d'une AMAP"), 'util-masthead', 'groupMasthead'],
					[s("Poser les sections d'une page publique, une sur deux sur fond gris"), 'DesignUi::getBand() + util-band', 'publicBands'],
					[s("Filtrer ou parcourir une liste depuis une colonne de côté"), 'util-filter-options-column', 'publicAnswer'],
				],
				'sections' => ['publicHero', 'groupMasthead', 'publicBands', 'publicAnswer'],
			],
		];

		return array_map(fn(array $family) => $family + ['which' => [], 'headings' => []], $families);

	}

	/**
	 * Une fiche ouvre la section qui montre sa classe, et dit dans le même ordre pour toutes à quoi elle
	 * sert, quand la prendre et à quel voisin la laisser. La clé est celle de la section.
	 */
	private function fiches(): array {

		return [
			'decks' => [
				'class' => 'util-deck',
				'role' => s("Un objet d'une liste, avec son identité, une bande par chose à dire et son étage déplié."),
				'when' => s("Une liste qui peut s'allonger, de séries, de livraisons ou d'aides, et le choix de ce que le formulaire suivant va créer."),
				'not' => [
					[s("l'objet seul, qu'on vient regarder en premier"), 'util-record'],
					[s("un choix d'entrée en petit nombre qui structure l'écran"), 'util-button'],
				],
				'variants' => s("le lien a.util-deck et le menu par data-dropdown, chacun seul ; le dépliable util-deck-open et les portes util-deck-band-doors, qui se cumulent ; util-deck-selected ; util-deck-accent-*"),
				'markup' => s("util-deck-head, qui porte l'icône, util-deck-body (util-deck-title puis sa phrase), util-deck-aside à droite et util-deck-arrow au bout, rendue par \\main\\DesignUi::getDeckHead() quand l'icône est ronde et la phrase un texte ; une util-deck-band par chose à dire, ses chiffres en util-deck-figures, chaque util-deck-figure en util-deck-key puis util-deck-value par \\main\\DesignUi::getDeckFigure() ; une util-deck-row par ligne d'un détail ; \\main\\DesignUi::getDeckDropdown() pour le menu ; \\main\\DesignUi::getDeckToggle() sur l'élément qui déplie, util-deck-slot passé pour un emplacement"),
			],
			'records' => [
				'class' => 'util-record',
				'role' => s("Un objet seul avec tout ce qui s'y rattache, sa tête, ses gestes, puis une information par rangée."),
				'when' => s("L'objet qu'on vient regarder en premier, seul sur sa page ou au-dessus d'une liste."),
				'not' => [
					[s("un objet parmi d'autres"), 'util-deck'],
					[s("des paires sans tête ni geste"), 'util-presentation'],
				],
				'variants' => s("util-record-branches · util-record-stub · util-record-tinted · util-record-item-alert · util-record-item-wide ; dans un panneau, util-panel-tinted"),
				'markup' => s("util-record-main, qui porte util-record-head (la marque, puis util-record-identity avec util-record-title et util-record-context) et util-record-actions ; puis util-record-list et \\main\\DesignUi::getRecordItem() pour chaque rangée"),
			],
			'actionCards' => [
				'class' => 'util-card-action',
				'role' => s("Une action que la page demande, et le bouton qui la fait."),
				'when' => s("Des factures en retard, des ventes à facturer, une carte par geste dans une grille util-cards-fit."),
				'not' => [
					[s("un chiffre sans action"), 'util-figure'],
				],
				'variants' => s("util-card-action-info · util-card-action-warning · util-card-action-danger · le tampon util-stamp"),
				'markup' => '\\main\\DesignUi::getActionCard()',
			],
			'tiles' => [
				'class' => 'util-tile',
				'role' => s("Un bloc d'accueil, avec son intitulé util-domain, son chiffre, une liste courte util-rows et ses liens."),
				'when' => s("Les colonnes de l'accueil d'une structure, dans une grille util-tiles."),
				'not' => [
					[s("une action que la page demande"), 'util-card-action'],
					[s("un objet d'une liste"), 'util-deck'],
				],
				'variants' => 'util-tile-wide · util-tile-link',
				'markup' => s("util-tiles, en une seule piste avec util-tiles-1, puis une util-tile par bloc, son intitulé teinté en util-tile-head ; dans un conteneur util-flat, les tuiles perdent fond, bord et ombre"),
			],
			'vignettes' => [
				'class' => 'util-vignette',
				'role' => s("Une image à gauche et son texte à droite."),
				'when' => s("Une structure ou un producteur avec son image, dans une liste comme dans une cellule de tableau."),
				'not' => [
					[s("un objet avec son état et ses gestes"), 'util-deck'],
				],
				'variants' => s("util-vignettes pour une suite · avec util-card quand le bloc est une carte"),
				'markup' => s("span.util-vignette, ou div.util-card.util-vignette"),
			],
			'presentations' => [
				'class' => 'util-presentation',
				'role' => s("Des paires libellé-valeur sur un dl, dont les valeurs partent toutes du même bord."),
				'when' => s("Les caractéristiques d'un objet, un total et ce qui reste à payer."),
				'not' => [
					[s("une information qui porte son propre geste"), 'util-record'],
				],
				'variants' => s("util-presentation-1|2|3 · util-presentation-max-content · util-presentation-auto, avec util-presentation-1 · dd.util-presentation-fill, avec util-presentation-2 ou -3, et sous 576 px sur toute util-presentation quand il porte un bouton · util-presentation-detail"),
				'markup' => s("dl, un dt par libellé, un dd par valeur ; le bouton d'un dd.util-presentation-fill en btn-sm btn-outline-primary ; la fiche d'un objet dans un div.util-block.stick-xs"),
			],
			'cards' => [
				'class' => 'util-card',
				'role' => s("Un bloc encadré, dans une grille util-cards d'une à quatre colonnes."),
				'when' => s("Un contenu qui n'a pas de bloc à lui, un texte, des boutons, un formulaire court."),
				'not' => [
					[s("un objet d'une liste avec son état"), 'util-deck'],
					[s("une action que la page demande"), 'util-card-action'],
				],
				'variants' => s("util-cards-1|2|3|4 · util-cards-start, sur la grille dont une carte se déplie, pour que ses voisines gardent leur hauteur · util-card-link · util-card-accent, le liseré, que util-card-accent-success, -warning, -danger, -info ou -todo colore"),
				'markup' => s("util-card-title pour le titre, util-card-buttons pour les gestes ; \\main\\DesignUi::getAccentCard() pour le liseré, son argument class pour une classe de plus sur la carte, margin pour la marge de sa grille, vide quand le conteneur donne la sienne"),
			],
			'marks' => [
				'class' => 'badge',
				'role' => s("Le mot qui dit où en est une chose, sans appeler d'action."),
				'when' => s("L'état d'une ligne de liste ou d'un objet, la nature d'un objet, une référence à lire caractère par caractère, un temps passé sur une intervention."),
				'not' => [
					[s("une action, qui reste un bouton"), 'btn'],
					[s("un nombre"), 'util-counter'],
					[s("un fait sur la ligne entière"), 'util-stamp'],
				],
				'variants' => s("badge-solid et badge-outline pour changer de poids, le dilué n'ayant pas de nom ; badge-danger, badge-confirmed et les teintes métier ; badge-dot, le chevron de dropdown-toggle ou le cadenas pour lire, ouvrir ou figer ; btn btn-badge ; badge-reference ; badge-time-spent ; badge-off ; badges et badges-stacked"),
				'markup' => s("span.badge, une classe de teinte, une classe de poids seulement pour en changer"),
			],
			'counters' => [
				'class' => 'util-counter',
				'role' => s("Le nombre qui compte, jamais un mot."),
				'when' => s("Le nombre de filtres posés, de relances, d'entretiens en retard, dans un bouton, un onglet ou une entrée de menu."),
				'not' => [
					[s("un mot qui qualifie"), 'badge'],
				],
				'variants' => s("util-counter-solid sur un fond qui bouge ; util-counter-danger quand ce qu'il compte presse, et pour rien d'autre"),
				'markup' => s("span.util-counter, absent quand il vaut zéro"),
			],
			'stamps' => [
				'class' => 'util-stamp',
				'role' => s("Un cachet posé en marge d'une ligne, qui signale un fait sur la ligne entière."),
				'when' => s("Une fonctionnalité nouvelle ou en bêta, au bout de son titre ; util-stamp-new pour ce qui vient d'apparaître."),
				'not' => [
					[s("un mot qui qualifie une partie de la ligne"), 'badge'],
				],
				'variants' => s("util-stamp-new ; util-stamp-friend, l'ami devant le texte ; util-title-stamp, le titre lui-même frappé comme un tampon"),
				'markup' => s("span.util-stamp, la teinte par --util-stamp"),
			],
			'badges' => [
				'class' => 'badge-split',
				'role' => s("Une marque pleine et son détail accolés, dans la teinte du conteneur."),
				'when' => s("Une marque qui a besoin d'un complément, une valeur et ce à quoi elle se rapporte."),
				'not' => [
					[s("une marque d'un seul mot"), 'badge'],
				],
				'variants' => s("la marque diluée, solid à FALSE, sur une ligne de liste"),
				'markup' => s("\\main\\DesignUi::getSplitBadge(), dans un conteneur color-*"),
			],
			'status' => [
				'class' => 'color-success + icône',
				'role' => s("L'état d'une ligne dit par une icône à la couleur de son état, seule ou devant un mot."),
				'when' => s("Une colonne de tableau qui dit d'une ligne qu'elle est conforme, à surveiller ou en défaut."),
				'not' => [
					[s("un état qui se nomme d'un mot sur fond clair"), 'badge'],
					[s("une chose allumée ou éteinte"), 'util-icon-active'],
				],
				'variants' => s("color-success avec check-lg · color-warning avec exclamation-triangle · color-danger avec exclamation-triangle ou x-lg"),
				'markup' => s("un span qui porte la classe de couleur, l'icône par \\Asset::icon() dedans, puis le mot s'il y en a un"),
			],
			'statusDots' => [
				'class' => 'util-icon-active',
				'role' => s("La pastille d'une chose allumée ou éteinte."),
				'when' => s("Une option active ou non, qui reste disponible dans les deux cas."),
				'not' => [
					[s("le domaine auquel une chose appartient"), 'util-icon'],
					[s("ce qui est indisponible"), 'util-icon-muted'],
				],
				'variants' => s("util-icon-inactive"),
				'markup' => s("div.util-icon.util-icon-round et son état, une icône dedans"),
			],
			'reliability' => [
				'class' => 'util-confidence',
				'role' => s("Trois barres qui disent combien une valeur est sûre."),
				'when' => s("Une valeur estimée ou calculée qu'on peut prendre plus ou moins au sérieux, à côté de la valeur ou du libellé qu'elle qualifie."),
				'not' => [
					[s("l'état d'une chose"), 'badge'],
				],
				'variants' => s("is-1, is-2 et is-3, les barres allumées en is-on ; util-confidence-levels pour la légende"),
				'markup' => s("span.util-confidence.is-1|2|3, trois span dont les allumés en is-on, le niveau nommé à côté ou par util-confidence-levels"),
			],
			'follows' => [
				'class' => 'util-follow',
				'role' => s("Le maillon d'une valeur qui vient d'un autre élément et se met à jour avec lui."),
				'when' => s("Une valeur d'une variante de produit reprise du produit principal."),
				'not' => [
					[s("une case que l'utilisateur remplit lui-même"), 'util-not-auto'],
				],
				'variants' => s("aucune"),
				'markup' => s("le maillon devant la valeur, sans texte, et la valeur garde son poids et son lien"),
			],
			'notAuto' => [
				'class' => 'util-not-auto',
				'role' => s("La pastille d'une case de formulaire officiel que le logiciel ne calcule pas."),
				'when' => s("Une case de la liasse fiscale à remplir à la main, une plus-value à classer sur une immobilisation."),
				'not' => [
					[s("une valeur reprise d'un autre élément"), 'util-follow'],
				],
				'variants' => s("aucune"),
				'markup' => s("sur le code de la case, la case restant vide"),
			],
			'cta' => [
				'class' => 'util-block-empty',
				'role' => s("Le bloc d'une zone entière qui n'a rien à montrer, son titre suivi s'il y a lieu de sa phrase et de son geste."),
				'when' => s("Une liste, un panneau ou une recherche sans résultat, sur un écran qui n'a rien d'autre à montrer."),
				'not' => [
					[s("un bloc qui a déjà son titre et rien dessous"), 'util-empty'],
				],
				'variants' => s("util-block-cta, le vert amande d'un geste à faire, quand util-block-empty ne dit que le vide"),
				'markup' => s("\\main\\DesignUi::getBlockEmpty(), qui rend le h3, puis le p et le geste en btn dans util-buttons, l'un et l'autre facultatifs, tous dans le bloc ; cta: TRUE pour util-block-cta"),
			],
			'empty' => [
				'class' => 'util-empty',
				'role' => s("Le constat d'un bloc ou d'une valeur vides."),
				'when' => s("Un bloc qui a déjà son titre et rien dessous, ou une valeur qui manque au fil d'un texte ou d'une cellule."),
				'not' => [
					[s("une liste, un panneau ou une recherche entièrement vides"), 'util-block-empty'],
				],
				'variants' => s("en bloc, avec sa silhouette ; sur un span, qui reste en ligne et perd dans une util-presentation le gras de la valeur"),
				'markup' => s("div.util-empty sous le titre du bloc, ou span.util-empty à la place de la valeur"),
			],
			'start' => [
				'class' => 'util-start',
				'role' => s("La bande qui ouvre un écran sur ce qui le gouverne, souvent une fonctionnalité à démarrer."),
				'when' => s("Une fonctionnalité à démarrer, avec ses gains quand elle n'a encore jamais été nourrie ; un état qui gouverne la page, une étape d'un parcours ou une annonce avec cachet."),
				'not' => [
					[s("un geste proposé au-dessus d'un écran déjà rempli"), 'util-block-info'],
					[s("une liste, un panneau ou une recherche vides"), 'util-block-empty'],
				],
				'variants' => s("util-start-upcoming, le cachet d'une fonctionnalité pas encore ouverte ; util-start-gains, les gains d'une fonctionnalité jamais nourrie"),
				'markup' => s("new \\main\\DesignUi()->getStart(), le geste dans buttons et l'illustration dans image"),
			],
			'welcome' => [
				'class' => 'util-welcome',
				'role' => s("Le bandeau qui accueille sur un écran qu'on découvre."),
				'when' => s("En tête d'un écran qu'on découvre, ou sous le bloc qu'il prolonge quand il invite à essayer une nouveauté."),
				'not' => [
					[s("l'écran encore vide d'une fonctionnalité à démarrer"), 'util-start'],
				],
				'variants' => s("aucune ; le titre suivi de util-stamp-new quand il invite à essayer une nouveauté"),
				'markup' => s("util-welcome-text, qui porte util-welcome-title, le corps en HTML rendu par l'appelant et les gestes dans util-welcome-buttons, puis util-welcome-mascot, rendus par \\main\\DesignUi::getWelcome()"),
			],
			'tintedBlocks' => [
				'class' => 'util-block-info',
				'role' => s("Le bloc qui annonce ou invite à agir, dans la teinte de l'univers."),
				'when' => s("Un fait à annoncer ou un geste proposé au-dessus d'un écran déjà rempli ; ses teintes pour un contrôle passé, une conséquence à peser avant de valider ou un échec qui bloque la suite."),
				'not' => [
					[s("une note d'une ligne sous un champ ou une section"), 'util-info'],
				],
				'variants' => s("util-block-success, util-block-warning, util-block-danger ; un titre h4 quand le message tient en plus d'une phrase ; puis, montrée plus bas, la forme à actions, que util-block-info-actions passe en ligne, sur util-block-info comme sur util-block-warning et util-block-danger"),
				'markup' => s("un p, précédé d'un h4 quand le message tient en plus d'une phrase, une action parfois ; un bouton sans variante prend la teinte du bloc, un bouton qui a la sienne la garde ; dans la forme à actions, util-block-info-title et util-block-info-help dans un util-block-info-text, puis les boutons et la croix util-block-info-close dans un util-block-info-actions ; util-block-icon en enfant direct ; puis, montrés plus bas, le bandeau util-block-sticked de Lime.Alert, sa croix util-block-close, et util-block-remove qui l'efface"),
			],
			'messages' => [
				'class' => 'util-info',
				'role' => s("La note qui accompagne un champ ou une section, sans encadré."),
				'when' => s("Une précision sous un champ ou une section ; ses sœurs à teinte pour une réussite, un avertissement ou un échec d'une ligne, dans le flux d'un formulaire ou d'une liste."),
				'not' => [
					[s("un message qui porte un titre"), 'util-block-info'],
				],
				'variants' => s("aucune ; ses sœurs util-success, util-warning et util-danger se posent à sa place, encadrées dans leur teinte avec leur icône"),
				'markup' => s("un texte nu ou des p, l'icône de la teinte venant de la feuille et jamais du balisage"),
			],
			'flash' => [
				'class' => 'util-toast',
				'role' => s("La pastille éphémère qui confirme une action qui vient d'avoir lieu."),
				'when' => s("Une action qui vient d'avoir lieu, à confirmer sans prendre de place dans la page, avec quelques secondes pour l'annuler quand elle se défait."),
				'not' => [
					[s("un résultat qui reste à l'écran"), 'util-block-success'],
				],
				'variants' => s("is-done, la coche de fin ; util-toast-timer, le temps qui reste pour annuler"),
				'markup' => s("posée par Lime.Alert.showToast() ou \$t->js()->toast(), jamais écrite à la main"),
			],
			'tips' => [
				'class' => 'util-tip',
				'role' => s("La bulle qui détaille au survol ce qu'on pointe."),
				'when' => s("Une série sur une planche ou une semaine de la grille de composition, dont le détail se lit au survol."),
				'not' => [
					[s("une précision qui se lit sans survoler"), 'util-annotation'],
				],
				'variants' => s("util-tip-above et util-tip-below, le côté de la flèche"),
				'markup' => s("en position fixe, placée en JS, la flèche posée sur la largeur par --tip-arrow ; util-tip-head en tête, util-tip-figures dessous, chaque valeur en util-tip-value sur son util-tip-label"),
			],
			'inline' => [
				'class' => 'util-annotation',
				'role' => s("Le texte secondaire, au fil d'une ligne ou sous ce qu'il précise."),
				'when' => s("Une précision courte à côté d'une valeur, d'un champ ou d'un titre."),
				'not' => [
					[s("une note à lire avant d'agir, en tête d'une section ou d'un formulaire"), 'util-info'],
				],
				'variants' => s("util-annotation-long, pour une précision de plusieurs lignes"),
				'markup' => s("un span au fil de la ligne, un div ou un p dessous, l'écart avec ce qui précède se posant sur le conteneur"),
			],
			'highlight' => [
				'class' => 'util-mark',
				'role' => s("Le surligneur de quelques mots dans un texte courant."),
				'when' => s("Quelques mots qui comptent, au milieu d'un paragraphe, plutôt qu'un paragraphe entier en gras."),
				'not' => [
					[s("une valeur à taper telle quelle"), 'util-literal'],
				],
				'variants' => s("sur un b, pour appuyer davantage"),
				'markup' => s("sur n'importe quel élément inline, qu'il suit aux retours à la ligne"),
			],
			'overline' => [
				'class' => 'util-label',
				'role' => s("Le sur-titre qui nomme une colonne ou un bloc sans lui donner de titre."),
				'when' => s("La tête d'une colonne ou d'un bloc, comme le sur-titre du bandeau de filtres."),
				'not' => [
					[s("un texte secondaire au fil d'une ligne"), 'util-annotation'],
				],
				'variants' => s("util-filter-label, qui ajoute sa marge dans le bandeau de filtres"),
				'markup' => s("un div en tête du bloc, en capitales grasses de 0,9 rem"),
			],
			'typedValue' => [
				'class' => 'util-literal',
				'role' => s("La valeur telle qu'on la tape ou qu'on la lit dans un fichier."),
				'when' => s("Une valeur à saisir au fil d'une phrase, ou l'exemple d'une colonne d'import."),
				'not' => [
					[s("un code qu'on relit caractère par caractère"), 'badge-reference'],
				],
				'variants' => s("aucune"),
				'markup' => s("un span, qui revient à la ligne au-delà de 15 rem"),
			],
			'dots' => [
				'class' => 'util-dot',
				'role' => s("Le rond de la couleur qu'un objet porte en base, devant son nom."),
				'when' => s("Un cahier de caisse, ou tout objet dont la couleur se choisit."),
				'not' => [
					[s("l'état d'une chose"), 'badge'],
				],
				'variants' => s("aucune"),
				'markup' => s("un span vide devant le nom, sa couleur en style en ligne par --util-dot"),
			],
			'texts' => [
				'class' => 'TextUi::multiline() · TextUi::nonBreaking() · TextUi::none()',
				'role' => s("Les écritures d'un texte que le code assemble, toujours les mêmes d'un écran à l'autre."),
				'when' => s("Un commentaire ou une note saisis sur plusieurs lignes, une quantité et son unité qui ne se séparent pas, une cellule dont la valeur n'a pas de sens sur sa ligne."),
				'not' => [
					[s("une valeur et son unité posées dans le markup"), 'util-unit'],
					[s("une valeur qui manque sur une ligne où elle a un sens"), 'util-empty'],
				],
				'variants' => s("aucune"),
				'markup' => s("multiline() échappe le texte puis garde ses retours à la ligne ; nonBreaking() rend chaque espace insécable ; none() écrit le tiret gris d'une cellule sans objet"),
			],
			'buttonsUsage' => [
				'class' => 'btn',
				'role' => s("Le socle commun de tous les boutons, avec ses tailles et ses modificateurs."),
				'when' => s("Tout bouton, avec son type btn-* et sa couleur."),
				'not' => [
					[s("une entrée qui ouvre un parcours"), 'util-button'],
				],
				'variants' => s("btn-xxs à btn-xl ; btn-group, btn-selected, btn-chosen, btn-readonly, btn-panel, btn-dashed"),
				'markup' => s("un a ou un button qui porte btn et un type btn-* ; une icône en btn-icon, son enfant, se pose seule au-dessus du libellé"),
			],
			'buttonsFull' => [
				'class' => 'btn-primary',
				'role' => s("Le plein, le bouton de l'action qui fait avancer la zone."),
				'when' => s("L'envoi d'un formulaire, le geste d'un état vide ou l'action qui fait avancer la zone ; une zone visible n'en a qu'un."),
				'not' => [
					[s("un geste qui accompagne le plein"), 'btn-primary-light'],
					[s("toute autre action, Annuler comprise"), 'btn-outline-primary'],
				],
				'variants' => s("btn-secondary, le plein de l'univers de l'écran, qui remplace le noir et ne s'y ajoute pas ; btn-success, btn-warning et btn-danger, les pleins des tons"),
				'markup' => s("un a ou un button qui porte btn et btn-primary"),
			],
			'buttonsLight' => [
				'class' => 'btn-primary-light',
				'role' => s("Le plein secondaire, une pastille pâle sans cerne, à l'encre foncée de sa couleur."),
				'when' => s("Le geste qui accompagne le plein sans lui disputer la place, comme Aperçu ou Rattacher."),
				'not' => [
					[s("l'action qui fait avancer la zone"), 'btn-primary'],
				],
				'variants' => s("btn-secondary-light, dans l'univers de l'écran ; btn-danger-light, pour retirer ou déconnecter parmi d'autres pastilles"),
				'markup' => s("un a ou un button qui porte btn et btn-primary-light"),
			],
			'buttonsOutline' => [
				'class' => 'btn-outline-primary',
				'role' => s("Le contour noir, qui cerne le bouton sur le fond de la surface."),
				'when' => s("Toute action qui ne fait pas avancer la zone, dont Annuler, celle à droite d'un titre de section et une suppression ordinaire, confirmée par data-confirm."),
				'not' => [
					[s("le réglage d'une ligne ou d'un écran"), 'btn-color-primary'],
				],
				'variants' => s("btn-outline-secondary, pour les outils d'une tête d'écran, les boutiques et les sites, et le ton info d'une carte à geste ; btn-chosen, la valeur retenue parmi des pastilles ; btn-outline-number, un numéro de compte ou de pièce qui mène à sa fiche, en chasse fixe, et btn-number, le même en aplat pour la pièce du jour, les deux en btn-sm dans une liste"),
				'markup' => s("un a ou un button qui porte btn et btn-outline-primary"),
			],
			'buttonsColor' => [
				'class' => 'btn-color-primary',
				'role' => s("Le blanc, qui pose l'encre et l'icône de sa couleur sur un fond blanc, dans le filet gris des cartes."),
				'when' => s("Le réglage d'une ligne ou d'un écran, une roue à la seule icône gear-fill, qui garde sa flèche quand elle ouvre un menu."),
				'not' => [
					[s("toute autre action"), 'btn-outline-primary'],
				],
				'variants' => s("btn-color-secondary, dans l'univers de l'écran"),
				'markup' => s("un a ou un button qui porte btn et btn-color-primary ; Lime.Dropdown ouvre son menu sur bg-primary ; la roue et son menu se rendent par \\main\\DesignUi::getGear(), titre du menu facultatif"),
			],
			'buttonDomains' => [
				'class' => 'btn-production-light',
				'role' => s("L'entrée de domaine, un plein secondaire à l'univers du domaine où elle mène."),
				'when' => s("Ouvrir un domaine depuis un écran qui n'appartient à aucun, comme la carte d'une structure dans la liste de l'utilisateur."),
				'not' => [
					[s("un geste second qui n'ouvre aucun domaine"), 'btn-primary-light'],
				],
				'variants' => s("btn-commercialisation-light, btn-accounting-light et btn-amap-light ; btn-light, l'entrée neutre qui ne relève d'aucun domaine"),
				'markup' => s("l'icône du domaine précède le libellé"),
			],
			'entries' => [
				'class' => 'util-button',
				'role' => s("Le grand bouton d'entrée, un choix qui ouvre un parcours."),
				'when' => s("Quelques choix qui structurent l'écran, comme créer sa structure ou rejoindre un groupe ; jamais une action qui envoie un formulaire."),
				'not' => [
					[s("une ligne d'une liste qui peut s'allonger"), 'util-deck'],
				],
				'variants' => s("util-button-danger, un geste qui détruit ; util-button-text, une précision sous le titre ; util-buttons-dark, l'ensemble sur la couleur principale ; util-button-seed, une graine à la place de l'icône ; util-buttons-center, des btn centrés à leur largeur"),
				'markup' => s("un div.util-buttons autour, les règles de util-button ne visant que cette balise ; chaque util-button tient dans un lien, avec un titre h4 et une icône à droite ; disabled éteint une entrée qui ne s'ouvre pas"),
			],
			'drop' => [
				'class' => 'util-drop',
				'role' => s("La zone de dépôt, qui reçoit des fichiers glissés et porte le bouton du sélecteur."),
				'when' => s("Un dépôt de fichiers, comme les justificatifs, l'import des relevés ou celui d'une vente."),
				'not' => [
					[s("un élément qu'on change de place en glissant"), 'util-drag-handle'],
				],
				'variants' => s("util-drop-over, pendant qu'un fichier la survole ; util-drop-head, util-drop-list et util-drop-foot, une fois des fichiers posés"),
				'markup' => s("rendue par main\\DropAiUi, le comportement commun dans MainDrop"),
			],
			'dropdowns' => [
				'class' => 'dropdown-list',
				'role' => s("Le menu déroulant, une liste de gestes ouverte depuis l'élément qui la porte."),
				'when' => s("Plusieurs gestes sur un objet, derrière un bouton qui garde sa flèche ; une entrée interdite y garde sa place, désactivée, avec sa raison."),
				'not' => [
					[s("des modes exclusifs avec leur aperçu"), 'util-soil-menu'],
				],
				'variants' => s("dropdown-list-light, le menu clair, avec dropdown-list-unstyled ; dropdown-item disabled ; dropdown-item-unread et dropdown-item-pending ; dropdown-item-group, qui déplie sur place le dropdown-item-group-items qui le suit"),
				'markup' => s("des dropdown-item, dropdown-divider, dropdown-subtitle et dropdown-block ; plein écran au téléphone"),
			],
			'batch' => [
				'class' => 'batch-group-wrapper',
				'role' => s("La barre d'actions des éléments cochés d'une liste."),
				'when' => s("Un geste à faire d'un coup sur plusieurs éléments cochés."),
				'not' => [
					[s("une chose faite à cocher seule"), 'util-tick'],
				],
				'variants' => s("batch-item-danger ; batch-item disabled"),
				'markup' => s("rendue par \\util\\BatchUi::group() ; une pilule au bord haut porte le titre et le nombre d'éléments cochés en util-counter, la croix annule la sélection"),
			],
			'weigh' => [
				'class' => 'util-weigh',
				'role' => s("Le bloc qui pèse un geste avant qu'on le fasse."),
				'when' => s("Un geste dont les conséquences doivent être lues avant de cliquer ; pour une suppression, quand quelqu'un d'autre que celui qui clique perd quelque chose."),
				'not' => [
					[s("un geste sans conséquence à lire"), 'data-confirm'],
					[s("une confirmation en panneau, dite en une phrase"), 'DesignUi::getConfirmPanel()'],
				],
				'variants' => s("sans modificateur, le registre courant ; util-weigh-warning, un geste qui se reprend ; util-weigh-danger, une perte ; util-weigh-alert, une conséquence à lire d'abord ; util-weigh-same, ce qui reste"),
				'markup' => s("rendu par main\\DesignUi::getWeigh() ; un titre qui nomme le geste, une phrase, les conséquences, ce qui reste, la pastille Annulable ou Définitif et le bouton, jamais de champ"),
			],
			'confirmPanel' => [
				'class' => 'DesignUi::getConfirmPanel()',
				'role' => s("Le panneau qui confirme un geste qui part chez d'autres sans rien détruire."),
				'when' => s("Un envoi ou une publication qui atteint d'autres personnes, sans que personne perde rien."),
				'not' => [
					[s("un geste dont les conséquences se lisent une à une"), 'util-weigh'],
					[s("un geste sans conséquence pour autrui"), 'data-confirm'],
				],
				'variants' => s("aucune, ni registre coloré ni inventaire"),
				'markup' => s("un texte qui chiffre ce que la page a sous la main, le bouton plein écrit par l'appelant, puis « Annuler »"),
			],
			'drag' => [
				'class' => 'util-drag-handle',
				'role' => s("La poignée qui saisit un élément pour le changer de place."),
				'when' => s("Une liste ou des réglages côte à côte dont on change l'ordre en glissant."),
				'not' => [
					[s("des fichiers glissés depuis l'ordinateur"), 'util-drop'],
				],
				'variants' => s("util-drag-chosen, l'élément saisi ; util-drag-ghost, l'emplacement où il atterrira ; util-drag-out, ce qu'il ne peut pas rejoindre ; sortable-fallback, la copie qui suit le pointeur"),
				'markup' => s("rendue par \\main\\DesignUi::getDragHandle() ; les états posés par Sortable, util-drag-out par le JS du package"),
			],
			'ticks' => [
				'class' => 'util-tick',
				'role' => s("La case qui coche d'un clic une chose à faire, sans formulaire."),
				'when' => s("Une ligne d'une liste de choses à faire, comme le pense-bête de la Semaine ou les lignes d'une description d'intervention."),
				'not' => [
					[s("des éléments cochés pour un geste commun"), 'batch-group-wrapper'],
				],
				'variants' => s("util-tick-line, qui ramène un libellé long sous lui-même"),
				'markup' => s("sur le lien qui porte le geste, autour d'Asset::icon('circle') ou Asset::icon('check-circle') ; sans le droit de cocher, l'icône reste nue"),
			],
			'displayMenu' => [
				'class' => 'util-soil-menu',
				'role' => s("Le menu de réglage d'affichage, des modes exclusifs et leurs options."),
				'when' => s("Un mode d'affichage à choisir parmi plusieurs, chacun avec son aperçu, puis ses options en interrupteur."),
				'not' => [
					[s("une liste de gestes"), 'dropdown-list'],
				],
				'variants' => s("aucune"),
				'markup' => s("dans un dropdown-list, une coche sur le mode actif, util-soil-preview pour l'aperçu, les options sous un séparateur"),
			],
			'copy' => [
				'class' => 'TextUi::copy()',
				'role' => s("Le bouton qui copie dans le presse-papiers le texte d'un élément de l'écran."),
				'when' => s("Une valeur que l'utilisateur va coller ailleurs, un lien de boutique, une adresse électronique, un code."),
				'not' => [
					[s("une valeur à lire ou à taper telle quelle"), 'util-literal'],
				],
				'variants' => s("\$class, btn-sm ou btn-xs pour une ligne serrée ; \$icon à FALSE, le libellé seul"),
				'markup' => s("rendu par util\\TextUi::copy() ; data-selector désigne l'élément dont le texte se copie, data-message dit à l'utilisateur que c'est fait"),
			],
			'fields' => [
				'class' => 'FormUi::yesNo()',
				'role' => s("Les deux réponses oui et non, côte à côte, d'un booléen de formulaire."),
				'when' => s("Une propriété bool dans un formulaire, qu'un describer obtient par le field yesNo."),
				'not' => [
					[s("une liste à choix multiples"), 'FormUi::checkboxes()'],
					[s("un réglage appliqué tout de suite, hors d'un formulaire"), 'TextUi::switch()'],
				],
				'variants' => s("field-toggle sur un champ qui n'a de sens que si la réponse est oui, field-toggle-wrapper sur son formulaire, qui ne porte qu'un oui / non"),
				'markup' => s("rendu par FormUi::yesNo() dans un FormUi::group() ; le champ masqué par field-toggle reste posté"),
			],
			'fieldInfo' => [
				'class' => 'FormUi::info()',
				'role' => s("Le texte qui accompagne un champ, sous son libellé ou sous le champ."),
				'when' => s("Sous le libellé, par labelAfter, ce qui situe le champ, à quoi il sert, où sa valeur se lira, ce qu'elle entraîne ; sous le champ, par after, la seule consigne de saisie, le format, l'unité, ce qu'un vide veut dire."),
				'not' => [
					[s("une note à lire avant d'agir, en tête d'une section ou d'un formulaire"), 'util-info'],
				],
				'variants' => s("aucune"),
				'markup' => s("FormUi::info() à la suite du libellé de FormUi::group(), ou à la suite du champ ; un geste sur la valeur, sous le champ, par FormUi::getFieldAction(), qui pose field-action"),
			],
			'radios' => [
				'class' => 'field-radio-group-reason',
				'role' => s("La ligne sous le libellé d'une option de FormUi::radios(), qui la précise ou dit pourquoi une règle la ferme."),
				'when' => s("Une option qu'une règle interdit, que l'utilisateur ne peut pas deviner ; elle reste dans la liste, désactivée."),
				'not' => [
					[s("une note qui vaut pour le champ entier"), 'FormUi::info()'],
				],
				'variants' => s("aucune"),
				'markup' => s("rendu par FormUi::radioOption(), la valeur de l'option, libellé puis raison, tous deux du HTML ; la raison sous le libellé de l'option désactivée ; seul le libellé s'atténue, jamais le bloc"),
			],
			'choices' => [
				'class' => 'util-choices',
				'role' => s("Les options d'un choix fermé en vignettes, chacune avec son nom dessous."),
				'when' => s("Un choix fermé dont une image distingue les options mieux qu'un nom."),
				'not' => [
					[s("un choix que leurs noms suffisent à distinguer"), 'FormUi::radios()'],
				],
				'variants' => s("util-choices-color, des pastilles de couleur cochées par un anneau ; util-choice-icon, une icône pour une échelle comme une fréquence ; util-choice-none, l'option vide"),
				'markup' => s("rendu par FormUi::choices() ou FormUi::colorChoices() dans un FormUi::group() ; chaque util-choice est un libellé qui porte le bouton radio, invisible, une vignette de 64px et son nom"),
			],
			'clearable' => [
				'class' => 'input-group-clear',
				'role' => s("La croix qui vide un champ d'un geste."),
				'when' => s("Un champ de recherche ou un filtre, en texte, nombre, e-mail, adresse web ou select, qui se vide d'un geste et remet ses filtres à jour."),
				'not' => [
					[s("un champ d'autocomplétion, qui porte déjà sa croix"), "field = 'autocomplete'"],
				],
				'variants' => s("aucune"),
				'markup' => s("l'attribut clearable de FormUi::text(), number(), email(), url() ou select() pose la croix à la suite du champ, avant un éventuel append, dans le même input-group ; elle ne se montre que si le champ porte une valeur ; refusé sur un select required ou multiple, qui n'a pas d'option vide où revenir"),
			],
			'autocomplete' => [
				'class' => "field = 'autocomplete'",
				'role' => s("Le champ qui cherche dans une liste longue à mesure qu'on tape."),
				'when' => s("Une liste longue, ou qui doit finir par une création, à la place d'un select."),
				'not' => [
					[s("une liste courte"), 'FormUi::select()'],
				],
				'variants' => s("multiple = TRUE pour plusieurs choix ; autocompleteHead, des boutons en tête qui règlent la recherche ; autocompleteWidth = 'group', la largeur de l'input-group ; une entrée 'type' => 'link' en dernier pour créer, 'type' => 'title' pour séparer deux groupes"),
				'markup' => s("un champ du describer en field = 'autocomplete', la route autocompleteUrl qui rend value, itemHtml et itemText ; le choix pose un champ caché au nom de la propriété"),
			],
			'valueDetail' => [
				'class' => 'util-keypad-field',
				'role' => s("La valeur lue, alignée à droite et suivie d'un chevron, qu'on touche pour ouvrir son détail."),
				'when' => s("Une valeur dont le détail s'ouvre dans un volet, comme le temps d'une équipe dans la feuille de récolte du planning."),
				'not' => [
					[s("une valeur qui s'ouvre en saisie sur place"), 'util-quick'],
				],
				'variants' => s("aucune"),
				'markup' => s("un a qui porte util-keypad-field, un span pour la valeur, puis le chevron ; la page fixe sa taille et la colonne de ses lignes, sur une surface blanche"),
			],
			'dateChecks' => [
				'class' => 'util-dates',
				'role' => s("Les dates à cocher, rangées par mois."),
				'when' => s("Cocher plusieurs dates d'un calendrier."),
				'not' => [
					[s("des options sans calendrier"), 'util-filter-options-field'],
				],
				'variants' => s("util-date verrouillée sur fond gris, cochée avec le cadenas quand la date existe déjà, décochée avec l'horloge quand sa prise des commandes serait fermée"),
				'markup' => s("un util-dates-month par mois, son util-label et une grille util-dates-grid de jetons util-date rendus par FormUi::checkbox()"),
			],
			'formOptions' => [
				'class' => 'util-filter-options-field',
				'role' => s("Les cases d'un formulaire au dessin des options de filtre, rail et pastille compris."),
				'when' => s("Des FormUi::checkboxes() ou FormUi::radios() qui prennent le dessin des util-filter-option."),
				'not' => [
					[s("des dates à cocher"), 'util-dates'],
				],
				'variants' => s("aucune"),
				'markup' => s("util-filter-options-field autour de FormUi::checkboxes() ou FormUi::radios() ; l'option cochée garde son poids et sa largeur"),
			],
			'fieldsGrid' => [
				'class' => 'util-fields',
				'role' => s("Les champs d'une bulle ou d'un panneau étroit, deux par ligne, le libellé au-dessus."),
				'when' => s("Une bulle ou un panneau étroit où un libellé long doit tenir sans rogner son champ, comme les planches d'un jardin qu'on dessine."),
				'not' => [
					[s("des champs qui forment une notion dans un formulaire large"), 'form-fieldset'],
				],
				'variants' => s("util-fields-wide, la ligne entière, pour une case à cocher, un message ou le bouton qui valide"),
				'markup' => s("chaque champ en groupe imbriqué (nested), qui le soustrait aux colonnes du formulaire autour"),
			],
			'fieldsets' => [
				'class' => 'form-fieldset',
				'role' => s("Le cadre d'un groupe de champs qui forment une notion dans un formulaire plus large."),
				'when' => s("Un vrai groupe de champs ; un champ seul n'a pas besoin d'un cadre, et deux cadres qui se suivent font un damier."),
				'not' => [
					[s("un formulaire long à découper en rubriques"), 'FormUi::cards()'],
				],
				'variants' => s("form-fieldset-notched, la légende à cheval sur la bordure, pour les champs communs en tête d'un formulaire"),
				'markup' => s("rendu par FormUi::fieldset(), un fieldset et sa legend ; la classe form-fieldset se passe en attribut, le cadre n'étant pas voulu partout"),
			],
			'fileButton' => [
				'class' => 'FormUi::fileButton()',
				'role' => s("Le bouton qui ouvre le choix d'un fichier, le champ de fichier caché dedans."),
				'when' => s("Un fichier qu'on joint d'un geste et qui part dès qu'il est choisi, une charte, une feuille signée."),
				'not' => [
					[s("un fichier envoyé avec le reste d'un formulaire"), 'FormUi::file()'],
				],
				'variants' => s("\$class, btn-outline-primary par défaut ; la classe passée en attribut s'ajoute à hide sur le champ"),
				'markup' => s("un label btn qui porte le libellé, icône comprise, et le champ de fichier caché ; l'envoi se branche sur le onchange du champ"),
			],
			'formChapters' => [
				'class' => 'FormUi::cards()',
				'role' => s("Les rubriques d'un formulaire long, chacune dans une carte."),
				'when' => s("Un formulaire qui compte assez de champs pour qu'on y cherche où l'on en est."),
				'not' => [
					[s("un petit groupe de champs qui forme une notion"), 'form-fieldset'],
				],
				'variants' => s("aucune"),
				'markup' => s("une util-card par rubrique dans une grille util-cards-1 ; chaque entrée porte title, content, et action quand le titre en porte une"),
			],
			'switches' => [
				'class' => 'TextUi::switch()',
				'role' => s("L'interrupteur d'un réglage binaire qui s'applique tout de suite, sans bouton d'enregistrement."),
				'when' => s("Un réglage binaire hors d'un formulaire."),
				'not' => [
					[s("un réglage qui n'a de sens qu'au sein d'un formulaire"), 'FormUi::yesNo()'],
				],
				'variants' => s("verrouillé, il garde sa forme, ses deux libellés et sa largeur, sans attributs d'action, et la raison du verrou s'écrit sous lui par FormUi::info(), jamais dans un title"),
				'markup' => s("rendu par util\\TextUi::switch() ; data-ajax porte l'adresse à appeler, post-* la valeur à envoyer"),
			],
			'quick' => [
				'class' => 'util-quick',
				'role' => s("La valeur soulignée en pointillé au survol, qu'un clic ouvre en saisie."),
				'when' => s("Une valeur d'un tableau qu'on modifie sans quitter la liste, quand l'élément passe assertUpdate()."),
				'not' => [
					[s("une valeur lue qui ouvre son détail dans un volet"), 'util-keypad-field'],
				],
				'variants' => s("util-quick-inline, le champ à la place de la valeur, le tableau restant visible ; util-inplace-field, une valeur écrite là où elle se lit, à la police de ce qui l'entoure ; util-quick sur un lien qui ouvre un tiroir ou un volet, le déclencheur, montré plus bas"),
				'markup' => s("posé par Data::quick() pour une saisie sur place ; à la main sur un a qui ouvre un tiroir ou un volet ; le formulaire est un util-quick-dropdown dont les champs s'empilent en util-quick-form, et util-quick-inline s'écrit par main\\QuickInlineAiUi"),
			],
			'sheet' => [
				'class' => 'util-sheet',
				'role' => s("La feuille qui monte du bas pour choisir sans quitter l'écran."),
				'when' => s("Un choix au doigt sans quitter l'écran ; à partir de 768px, elle se centre en modale."),
				'not' => [
					[s("une liste de gestes ouverte depuis un bouton"), 'dropdown-list'],
				],
				'variants' => s("util-sheet-inline, la feuille dans la page, sous ce qui l'ouvre, pour une page intégrée qui n'a pas d'écran à elle"),
				'markup' => s("util-sheet-scrim puis util-sheet-panel, qui porte util-sheet-grab la poignée, util-sheet-head le titre et la croix, util-sheet-body ce qui défile, util-sheet-foot les gestes ; util-sheet-scrim est le voile ; celui qui l'ouvre bloque le défilement de la page derrière elle"),
			],
			'composer' => [
				'class' => 'util-composer',
				'role' => s("L'éditeur d'un objet en 3D, l'aperçu à gauche et les choix à droite."),
				'when' => s("Composer un objet en 3D, comme l'éditeur d'avatar, l'aperçu restant en vue pendant que les choix défilent."),
				'not' => [
					[s("un choix fermé seul, en vignettes"), 'util-choices'],
				],
				'variants' => s("util-composer-tints, une rangée de pastilles et la pastille que l'éditeur ajoute après elle, qui passent ensemble à la ligne"),
				'markup' => s("util-composer-preview pour l'aperçu, collé sous --util-composer-top, la scène posée par scene\\SceneUi::getContainer() ; sous 768 px, l'aperçu passe au-dessus des choix"),
			],
			'searches' => [
				'class' => 'util-block-search',
				'role' => s("La carte qui porte le formulaire de recherche en tête d'une liste."),
				'when' => s("Une liste que plusieurs critères filtrent, par un formulaire qu'on envoie."),
				'not' => [
					[s("des critères cherchés depuis un seul champ"), 'util-omnibox'],
				],
				'variants' => s("util-search-1, util-search-2 et util-search-3, le nombre de colonnes à partir de 992 px, une seule en dessous, deux pour util-search-3 à partir de 576 px ; util-search-fill, avec util-search-2 ou util-search-3, un champ sur toute la ligne"),
				'markup' => s("util-block-search autour du formulaire, qu'il écarte de la liste ; dans util-search, un fieldset par champ, sa legend pour intitulé ; util-search-submit, rendu par FormUi::searchSubmit(), tient le bouton, la croix qui vide la recherche et le total, sur leur rangée, calés à droite"),
			],
			'omnibox' => [
				'class' => 'util-omnibox',
				'role' => s("Le champ unique qui cherche et filtre, chaque critère retenu posé en jeton."),
				'when' => s("Une liste que plusieurs familles de critères filtrent, cherchées depuis un seul champ."),
				'not' => [
					[s("un formulaire de critères qu'on envoie"), 'util-block-search'],
				],
				'variants' => s("util-omnibox-lg, un cran de hauteur pour un écran dont la recherche est l'objet ; util-omnibox-folded, util-omnibox-plain et util-omnibox-scroll, puis fullscreenBottom, name et chips, montrées plus bas ; singleByGroup, un critère par famille ; suggestionsIdle, une liste proposée au champ vide ; fullscreen: FALSE, la liste gardée sous le champ au doigt ; terms sur une suggestion, des mots cherchés sans être affichés ; util-brand-scope-light, posé avec util-brand-scope quand l'encre pleine de la marque tient moins de 4,5:1 sur le fond de la liste, passe ses textes atténués à l'encre pleine et assombrit sa ligne active : sous #4d7c0f, de 3,32 et 3,87 à 4,99 et 6,42"),
				'markup' => s("rendu par \\main\\OmniboxUi::get() ; util-omnibox-field porte form-control ; les suggestions dans util-omnibox-list, un util-omnibox-family par famille avec son util-omnibox-group en tête, et sa réserve en util-omnibox-rest sur sa dernière ligne"),
			],
			'chips' => [
				'class' => 'util-chips',
				'role' => s("La barre de jetons, la recherche en tête et un filtre par jeton."),
				'when' => s("Une liste filtrée par une recherche et des critères aux valeurs trop nombreuses pour rester visibles, chaque jeton dépliant les siennes."),
				'not' => [
					[s("quelques options toujours visibles"), 'util-filters'],
				],
				'variants' => s("util-chips-sticky, collée en haut au défilement ; is-active, un critère renseigné ; is-solid, celui qui restreint vraiment ce qu'on voit ; is-inverted, un jeton sur un fond sombre"),
				'markup' => s("util-chips, un util-omnibox en tête puis un util-chip par filtre ; \\main\\DesignUi::getChipDropdown() pour le jeton qui déplie ses valeurs, sa liste à la suite"),
			],
			'filters' => [
				'class' => 'util-filters',
				'role' => s("Le bandeau des filtres d'une liste, un groupe d'options par critère."),
				'when' => s("Quelques critères à peu de valeurs, toutes visibles d'un coup."),
				'not' => [
					[s("des critères aux valeurs trop nombreuses pour rester visibles"), 'util-chips'],
				],
				'variants' => s("is-selected sur l'option retenue"),
				'markup' => s("un util-filter par groupe, avec son util-filter-label et un util-filter-options qui contient les util-filter-option ; les options sont posées sur un rail clair dans la teinte de la section, l'option retenue est une pastille blanche détachée par son ombre ; le survol ne vaut qu'à la souris ; au-delà de deux valeurs, un jeton par \\main\\DesignUi::getChipDropdown() remplace util-filter-options sous le même util-filter-label"),
			],
			'screenSettings' => [
				'class' => 'a.util-filter-label',
				'role' => s("Le nom d'un interrupteur dans le bandeau des filtres, un lien qui bascule le réglage comme lui."),
				'when' => s("Un réglage binaire d'un écran, appliqué tout de suite, parmi ses réglages posés en util-filters."),
				'not' => [
					[s("un choix parmi quelques valeurs"), 'util-filters'],
					[s("un réglage binaire hors d'un bandeau"), 'TextUi::switch()'],
				],
				'variants' => s("aucune"),
				'markup' => s("un util-filter par réglage, des util-filter-option pour un choix, ou un TextUi::switch() seul sous un a.util-filter-label qui porte les mêmes attributs que lui, pour que son nom bascule aussi le réglage ; l'interrupteur s'aligne sur les options posées dans leur rail"),
			],
			'scope' => [
				'class' => 'util-scope-line',
				'role' => s("La ligne au-dessus du titre qui dit ce qu'on regarde, et le geste qui en change."),
				'when' => s("Un écran qui se lit à plusieurs portées, le tout ou l'une de ses parties."),
				'not' => [
					[s("un filtre de la liste affichée"), 'util-filters'],
				],
				'variants' => s("util-scope-line-title-sm, un surtitre à 0,9 rem au-dessus d'un nom ; util-scope-line-item-closed, les portées closes ; --scope-line-title-ink, l'encre sur un fond qui n'est pas celui de la page ; --scope-line-title-tracking, l'espacement des capitales"),
				'markup' => s("util-scope-line-title porte toujours un nom, y compris quand la portée est le tout ; le jeton garde le même mot quelle que soit la portée et ne prend jamais is-active ; dans la liste, un filet après le tout et un avant les portées closes, pas davantage"),
			],
			'tables' => [
				'class' => 'table',
				'role' => s("Le tableau du balisage, ses colonnes fixées par ses cellules."),
				'when' => s("Des lignes à lire sous des colonnes qui restent les mêmes à toutes les largeurs d'écran."),
				'not' => [
					[s("des colonnes qui se redistribuent sur petit écran"), 'util-table'],
				],
				'variants' => s("tr-hover, le survol de ligne ; tr-even, les lignes alternées ; tr-bordered, un filet par ligne ; sur une ligne, tr-bold, tr-light, tr-title, tr-sub, tr-upper, row-highlight et tr-disabled ; sur une colonne, td-min-content, text-end et t-highlight ; puis thead-sticky, td-sticky-first et util-overflow-frame, montrés plus bas"),
				'markup' => s("thead et tbody ; tr-sub sous un tr-title pour une ligne de tête et ses sous-lignes, le chevron de la tête en util-deck-chevron ; td-min-content et text-end se posent sur le th et sur chaque td de la colonne, t-highlight sur les td seuls"),
			],
			'grids' => [
				'class' => 'util-table',
				'role' => s("Le tableau en grille, ses colonnes déclarées en CSS plutôt que par le balisage."),
				'when' => s("Des lignes dont les colonnes se redistribuent sur petit écran, posées à même la feuille d'un écran, dans un panneau ou sur une page publique."),
				'not' => [
					[s("des colonnes fixes, qui ne bougent pas avec l'écran"), 'table'],
				],
				'variants' => s("util-table-even pour une grille qu'on lit, ou util-table-bordered pour une grille qu'on remplit, jamais les deux ; util-table-break, un filet au-dessus d'un groupe sans intertitre ; util-table-veil et util-table-groups, un groupe de colonnes voilé et nommé ; util-table-add et util-table-add-entry, la dernière ligne qui en ajoute une ; util-checkbox, une case que toute la cellule coche ; util-table-sticky et util-table-sticky-column, montrés avec table"),
				'markup' => s("les pistes en grid-template-columns, dans la feuille du package sous un sélecteur propre à l'écran ; util-table-row par rang, util-table-head pour les intitulés, util-table-title pour un intertitre, util-table-full pour une cellule de toute la ligne ; util-table-header pour un intitulé hors d'une util-table"),
			],
			'groups' => [
				'class' => 'tbody-group',
				'role' => s("La tête d'un groupe de lignes et ses lignes filles, lues comme une seule bande."),
				'when' => s("Un produit et ses variantes, ou tout objet suivi de ses lignes filles, dans un tableau."),
				'not' => [
					[s("une tête et ses rangs dans une util-table"), 'util-table-group'],
				],
				'variants' => s("tbody-group-fold, tbody-group-open et tbody-group-toggle, le groupe qu'on replie ; tr-future, une échéance pas encore échue ; util-tr-group-hover et util-tr-group-selected, un groupe mis en évidence ; montrés plus bas"),
				'markup' => s("tbody.tbody-group pour la tête, tbody.tbody-group-sub pour ses lignes filles, chacune un tr.tr-sub ; l'alternance de tbody-even compte les deux pour un"),
			],
			'listStates' => [
				'class' => 'util-table-row-state',
				'role' => s("Le fond d'un rang qui dit son état, fait, à vérifier ou à traiter."),
				'when' => s("Un rang d'une util-table qui est fait, a changé ou reste à traiter, parmi des rangs au repos."),
				'not' => [
					[s("une liste sans aucun élément"), 'util-block-empty'],
				],
				'variants' => s("util-deck-accent-success, un rang fait ; util-deck-accent-todo, un rang à traiter ou qui a changé ; util-deck-accent-warning et util-deck-accent-danger, réservés aux alertes"),
				'markup' => s("sur le rang lui-même, sans colonne ni bordure de plus, et jamais un fond posé par la feuille du package ; util-empty sur un span pour une valeur qui manque ; util-deck-row-state pour la même forme sur un util-deck"),
			],
			'heat' => [
				'class' => 'util-heat',
				'role' => s("La cellule d'une matrice dont le fond suit sa valeur."),
				'when' => s("Une matrice où l'on repère d'un coup d'œil les valeurs fortes, chaque cellule portant une seule variable."),
				'not' => [
					[s("des montants qui se comparent sur une même échelle"), 'util-share'],
				],
				'variants' => s("util-heat-strong, le texte en blanc au-delà de 0,95 ; util-heat-table, l'habillage du tableau ; util-column-hover, la colonne survolée, montrée plus bas"),
				'markup' => s("--heat de 0 à 1 sur chaque cellule, dans un table-unstyled, lui-même dans un util-overflow"),
			],
			'bellows' => [
				'class' => 'util-bellows',
				'role' => s("Des plis côte à côte, un seul ouvert, les autres réduits à une bande qui porte leur nom."),
				'when' => s("Des jours ou des étapes à parcourir côte à côte, dont un seul se lit en entier, sans que la largeur de l'ensemble bouge."),
				'not' => [
					[s("des vues qui ne se lisent jamais côte à côte"), 'tabs'],
				],
				'variants' => s("util-bellows-open et util-bellows-titled, le pli ouvert ; util-bellows-inactive, un pli qui reste en bande ; util-bellows-columns, les plis en colonnes d'une util-table, montrée plus bas"),
				'markup' => s("util-bellows-item par pli, sa util-bellows-band avec util-bellows-name et util-bellows-mark, puis son util-bellows-panel ouvert par util-bellows-title ; la bande du nom en tabindex=\"0\" ; le geste de MainBellows, chargé par \\Asset::js('main', 'bellowsAi.js')"),
			],
			'overflow' => [
				'class' => 'util-overflow-sm',
				'role' => s("Le cadre qui fait défiler un tableau en largeur sous un seuil d'écran, au lieu de l'écraser."),
				'when' => s("Un tableau dont les colonnes ne tiennent plus sous une largeur donnée, et qui se lit encore en les faisant défiler."),
				'not' => [
					[s("des colonnes qui peuvent se redistribuer sur petit écran"), 'util-table'],
					[s("un tableau dont l'intitulé doit rester en vue"), 'util-overflow-frame'],
				],
				'variants' => s("util-overflow, à toute largeur · util-overflow-xs, sous 450 px · util-overflow-sm, sous 576 px · util-overflow-md, sous 768 px · util-overflow-lg, sous 992 px · util-overflow-xl, sous 1200 px"),
				'markup' => s("un div autour du tableau, qui en est l'enfant direct ; sous le seuil, l'enfant garde la largeur du seuil et le cadre défile"),
			],
			'pagination' => [
				'class' => 'TextUi::pagination()',
				'role' => s("La navigation entre les pages d'une liste trop longue pour un écran."),
				'when' => s("Une liste découpée en pages par la requête de l'appelant."),
				'not' => [
					[s("une liste qu'on raccourcit en la filtrant"), 'util-block-search'],
				],
				'variants' => s("aucune"),
				'markup' => s("la page courante, comptée à partir de zéro, et le nombre d'éléments divisé par le nombre par page, arrondi au-dessus ; au plus dix numéros ; rien sur une seule page"),
			],
			'seeds' => [
				'class' => 'util-seeds',
				'role' => s("Quelques offres comparables alignées, une par sachet."),
				'when' => s("Choisir une offre parmi quelques-unes et la mettre au panier, comme sur la page d'abonnement."),
				'not' => [
					[s("ce qui gouverne la page au-dessus des offres, l'ouverture de la souscription ou un remerciement"), 'util-start'],
				],
				'variants' => s("util-seed-selected, un sachet au panier ; util-seed-ribbon, une échéance ; util-seed-logo, un logo à la place d'un ami ; util-seed-wide, le sachet qui réunit les autres ; util-seeds-plain, une longue liste sans illustration ; puis, montrés plus bas, util-table-seeds, util-celebrate et util-seed-torn, btn-offer-perched et la carte d'invitation"),
				'markup' => s("util-seed par offre, sa tête util-seed-head et util-seed-name, son corps util-seed-body ; le prix en util-seed-price et util-seed-per ; util-seed-features pour ce que l'offre ouvre ; util-seed-calendar pour les mois couverts ; util-seed-lead pour la phrase d'en-tête"),
			],
			'comments' => [
				'class' => 'util-comment',
				'role' => s("Le fil de messages aligné sur une grille, la date devant chaque message."),
				'when' => s("Les commentaires d'une intervention ou les annotations d'une opération bancaire, dans une liste ou une cellule de tableau."),
				'not' => [
					[s("un fil qui nomme l'auteur, où chacun n'a pas les mêmes gestes"), 'util-thread'],
				],
				'variants' => s("util-comment-editable, la colonne du crayon et de la corbeille ; util-comment-hover, les gestes au survol ; puis util-text-clamp, util-text-clamp-2 et text-break, montrés plus bas"),
				'markup' => s("le titre et le bouton d'ajout au-dessus du fil, dans un util-title, sauf dans une ligne de tableau, où l'ajout est une entrée de la roue de la ligne"),
			],
			'tabs' => [
				'class' => 'tabs',
				'role' => s("Une rangée d'onglets qui ouvre un panneau à la fois."),
				'when' => s("Des vues d'un même objet qu'on ouvre l'une après l'autre, une seule visible."),
				'not' => [
					[s("une bascule entre deux vues d'un même bloc"), 'tabs-sm'],
					[s("des vues qu'on garde côte à côte"), 'util-bellows'],
				],
				'variants' => s("selected, l'onglet choisi ; tab-item-end, l'onglet rejeté au bout de la rangée ; un util-counter dans l'onglet ; puis, montrés plus bas, tab-item-icon, tabs-lg, \\main\\DesignUi::getPageTabs() et fold, tab-item-detail, tab-item-vignette et tab-item-name, tabs-form et tabs-exclusive, tabs-sub, l'onglet-menu d'un collectif, tabs-v et tab-item-stat"),
				'markup' => s("un .tabs, sa rangée .tabs-item d'a.tab-item, chacun rendu par \\main\\DesignUi::getTabItem(), son libellé en HTML, puis un tab-panel par onglet dans le .tabs, où Lime.Tab le cherche"),
			],
			'tabsHorizontal' => [
				'class' => 'tabs-h',
				'role' => s("La rangée d'onglets posée dans le contenu d'une page, et non dans sa tête."),
				'when' => s("Toute rangée d'onglets du contenu d'une page, qu'elle reste simple, se replie en menu quand elle déborde ou se pose en tuiles."),
				'not' => [
					[s("les onglets de tête d'un écran, qui en ouvrent les vues"), '\\main\\DesignUi::getPageTabs()'],
				],
				'variants' => s("tabs-h-fold, la rangée repliée en menu quand elle déborde, aussi sous des sous-onglets ; tabs-tiles, des tuiles à icône, nom et seconde ligne ; montrées plus bas"),
				'markup' => s("un .tabs tabs-h, sans règle propre, qui dit que la rangée est dans le contenu ; sa rangée .tabs-item d'a.tab-item, selected sur l'onglet actif ; tabs-h-fold sur le conteneur, Lime.TabFold construit le menu et pose tabs-h-folded quand la rangée déborde, seule classe que la feuille lit"),
			],
			'figures' => [
				'class' => 'util-figures',
				'role' => s("Une rangée de chiffres en tuiles, la valeur en gros et son intitulé dessous."),
				'when' => s("En tête d'un écran que ses chiffres résument, chaque tuile pouvant mener à la liste qu'elle compte."),
				'not' => [
					[s("des chiffres qui introduisent l'écran sans le résumer"), 'util-figures-line'],
					[s("une variation dans un flux dense"), 'util-variation'],
				],
				'variants' => s("une tuile en balise a, qui mène à la liste qu'elle résume ; puis, montrés plus bas, util-figure-variation, util-figure-target, util-figures-2 et util-figures-3, util-figure-label-top"),
				'markup' => s("un .util-figures par \\main\\DesignUi::getFigures(), un util-figure par chiffre par \\main\\DesignUi::getFigure(), son util-figure-value puis son util-figure-label, en balise a quand une adresse est passée ; la famille en retrait que nomme FigureFamilySentinelAi ne reçoit aucun emploi neuf"),
			],
			'figuresLine' => [
				'class' => 'util-figures-line',
				'role' => s("Des chiffres en ligne sous un titre, sans fond ni cadre, séparés par un filet."),
				'when' => s("Une rangée qui introduit l'écran sans le résumer."),
				'not' => [
					[s("une page dont les chiffres sont le sujet"), 'util-figures'],
				],
				'variants' => s("un util-bars calé à droite de la valeur ; util-figure-variation, qui porte un écart par span"),
				'markup' => s("un .util-figures-line, un util-figures-line-item par chiffre, un lien le plus souvent, avec les enfants d'util-figure ; l'appelant pose la largeur du graphique"),
			],
			'figuresMuted' => [
				'class' => 'util-figure-muted',
				'role' => s("Une tuile en retrait, sans fond, d'un filet fin et à l'encre atténuée."),
				'when' => s("Un jour de vente passé, posé après les jours à venir pour compléter la rangée."),
				'not' => [
					[s("la tuile de l'année affichée"), 'util-figure-selected'],
				],
				'variants' => s("aucune"),
				'markup' => s("sur un util-figure en lien, qui prend le blanc au survol"),
			],
			'figuresSelected' => [
				'class' => 'util-figure-selected',
				'role' => s("La tuile de l'année affichée, à la teinte d'un util-choice coché."),
				'when' => s("Une rangée où chaque tuile ouvre la même analyse sur une autre année."),
				'not' => [
					[s("un jour passé qui recule derrière ses voisins"), 'util-figure-muted'],
				],
				'variants' => s("aucune"),
				'markup' => s("sur un util-figure en lien, que le survol ne change pas ; aucune tuile choisie tant qu'aucune année ne l'est, et pas de rangée pour une seule année"),
			],
			'figuresScroll' => [
				'class' => 'util-figures-overflow',
				'role' => s("Une suite de chiffres de même nature, chaque tuile à la largeur de son contenu."),
				'when' => s("Des chiffres qu'on parcourt plus qu'on ne compare, comme le chiffre d'affaires de chaque jour de vente."),
				'not' => [
					[s("quelques chiffres qui se comparent"), 'util-figures'],
				],
				'variants' => s("aucune"),
				'markup' => s("sur un .util-figures, jamais avec util-figures-2 ni util-figures-3, dans un util-figures-overflow-wrapper quand un hôte l'enveloppe dans le container sans changer sa largeur ; la rangée défile de côté sur un écran tactile, que data-touch désigne"),
			],
			'summarize' => [
				'class' => 'util-summarize',
				'role' => s("Un bandeau de chiffres habillé comme util-card, à la largeur de son contenu."),
				'when' => s("Quelques chiffres clés alignés sur une ligne, chacun pouvant ouvrir sa vue."),
				'not' => [
					[s("une rangée de tuiles qui se partagent la largeur"), 'util-figures'],
				],
				'variants' => s("li.selected, le chiffre de la vue ouverte ; util-summarize-muted, un détail sous le chiffre ; util-summarize-overflow, le bandeau qui défile au lieu de passer à la ligne ; util-summarize-unstyled, un lien sans habillage"),
				'markup' => s("un ul.util-summarize, un li par chiffre, son a ou son div, un h5 pour l'intitulé ; dans un panel-dialog, la bordure seule porte l'état"),
			],
			'variation' => [
				'class' => 'util-variation',
				'role' => s("Une pastille de variation, au fil d'un flux dense."),
				'when' => s("Une variation dans une ligne de tableau ou un prix."),
				'not' => [
					[s("une variation sous un chiffre clé"), 'util-figure-variation'],
				],
				'variants' => s("util-variation-up, util-variation-down, util-variation-neutral"),
				'markup' => s("un span.util-variation et son modificateur"),
			],
			'ring' => [
				'class' => 'util-ring',
				'role' => s("Un anneau qui montre la part de chaque segment dans un tout."),
				'when' => s("Une répartition à saisir d'un coup d'œil, avant qu'on lise les chiffres."),
				'not' => [
					[s("l'avancement d'un ensemble, de ce qui est fait à ce qui reste"), 'util-progress'],
				],
				'variants' => s("une taille passée en second argument du helper"),
				'markup' => s("\\main\\DesignUi::getRing() et ses couples [pourcentage, nom de token], toujours accompagné de badges scindés ou d'une légende chiffrée ; le trou prend le fond de la page, à redéfinir sur un aplat coloré"),
			],
			'ratings' => [
				'class' => 'util-rating',
				'role' => s("Une note sur cinq en étoiles, suivie de la moyenne et du nombre d'avis."),
				'when' => s("Une note sur une carte, ou en tête d'une liste d'avis."),
				'not' => [
					[s("dire combien une valeur est sûre"), 'util-confidence'],
				],
				'variants' => s("compact: TRUE au helper, une seule étoile sur une carte ; large: TRUE, qui pose util-rating-lg et la moyenne en grand ; puis, montrés plus bas, util-rating-input et util-rating-distribution"),
				'markup' => s("\\main\\DesignUi::getRating(), qui pose util-rating-stars, util-rating-off sur chaque étoile éteinte et util-rating-value ; les étoiles gardent la couleur du texte"),
			],
			'progress' => [
				'class' => 'util-progress',
				'role' => s("Une piste découpée en parts, une par état."),
				'when' => s("Une répartition qui se lit à sa longueur, entre conformes, points à vérifier et points bloquants."),
				'not' => [
					[s("une part dans un tout, lue à l'estime"), 'util-ring'],
				],
				'variants' => s("puis, montrés plus bas, util-progress-marked et son util-progress-marker, util-share pour des montants comparés"),
				'markup' => s("un .util-progress, un util-progress-part par part, sa largeur en style inline et sa couleur par une classe bg-*"),
			],
			'rail' => [
				'class' => 'util-rail',
				'role' => s("Un axe gradué qui porte une ou plusieurs plages et un repère."),
				'when' => s("Des plages et un repère à situer sur un axe, des semaines ou des prix."),
				'not' => [
					[s("les repères d'un axe, sans plage"), 'util-graduation'],
				],
				'variants' => s("util-rail-range-light, une plage d'appoint ; util-graduation-mark-key, le numéro du repère en gras ; puis, montrés plus bas, util-rail-trackless, un rail sans util-rail-track qui ne garde que sa graduation, et util-rail-flat, qui alignent une frise"),
				'markup' => s("un .util-rail, son util-rail-track, ses util-rail-range et son util-rail-marker, puis une util-graduation ; le placement en pourcentage de l'axe et la couleur en currentColor viennent de l'écran"),
			],
			'graduation' => [
				'class' => 'util-graduation',
				'role' => s("Les repères d'un axe, qui s'éclaircissent quand la place manque."),
				'when' => s("Un axe de semaines ou de valeurs à graduer."),
				'not' => [
					[s("un axe qui porte des plages et un repère"), 'util-rail'],
				],
				'variants' => s("util-graduation-sparse, le palier clairsemé tout de suite ; util-graduation-mark-decade, -five et -bound, les repères qui survivent"),
				'markup' => s("un .util-graduation, un util-graduation-mark par repère, dont \\series\\PlaceUi::getGraduationClass() donne les modificateurs ; le placement appartient à l'écran"),
			],
			'legend' => [
				'class' => 'util-legend',
				'role' => s("La clé des couleurs d'une grille ou d'un graphique."),
				'when' => s("Une grille ou un graphique dont les couleurs nomment des états."),
				'not' => [
					[s("la couleur qu'un objet porte, devant son nom"), 'util-dot'],
				],
				'variants' => s("une marque à la forme de ce qu'elle nomme, util-timeline-event util-timeline-event-period pour un ruban de période"),
				'markup' => s("un .util-legend, un util-legend-item par entrée, sa util-legend-mark puis son libellé ; la teinte reprise de la règle de la case nommée, et une entrée seulement pour un état présent à l'écran"),
			],
			'formats' => [
				'class' => 'TextUi::money()',
				'role' => s("L'écriture d'un montant et d'un pourcentage, toujours la même d'un écran à l'autre."),
				'when' => s("Tout montant et tout pourcentage affiché, dans un tableau, une phrase ou un chiffre clé."),
				'not' => [
					[s("un nombre sans unité monétaire"), 'TextUi::number()'],
				],
				'variants' => s("money(\$number, precision: 0), à l'euro · pc(\$number, \$precision)"),
				'markup' => s("money() écrit les décimales que le montant porte, de deux à quatre, l'espace des milliers et le symbole selon la langue, arrondit au commercial et ne signe jamais un zéro ; pc() écrit le séparateur décimal de la langue, ne coupe jamais la valeur de son %, et écrit « < » devant la plus petite valeur qu'il affiche pour une part qui s'arrondit à zéro, « < 1 % » à la précision 0"),
			],
			'bars' => [
				'class' => 'util-bars',
				'role' => s("Une quantité mois par mois en petites barres, chaque ligne à son échelle."),
				'when' => s("Comparer d'un coup d'œil la forme de l'année de plusieurs lignes."),
				'not' => [
					[s("la part de chaque état dans un tout"), 'util-progress'],
				],
				'variants' => s("util-bars-max, l'échelle en clair ; --bars, un autre nombre de colonnes ; puis, montrés plus bas, util-bars-light, util-bars-signed, util-bars-before, et UtilChart.createComparison(), un graphique en canvas sans util-bars, pour des modalités à une date, dans son cadre analyze-bar, à côté de son tableau analyze-chart-table"),
				'markup' => s("un .util-bars, un enfant par barre qui porte --bar et --short, puis util-bars-axis ; la couleur en currentColor, posée par l'écran"),
			],
			'maps' => [
				'class' => 'util-map',
				'role' => s("Une carte de France, une couleur par département ou par région."),
				'when' => s("Une valeur par région ou par département, avec son échelle."),
				'not' => [
					[s("une valeur à lire exactement, département par département"), 'table'],
				],
				'variants' => s("util-map-region, sans frontières de département ; une échelle à un sens ou à deux pôles ; puis, montrés plus bas, util-map-cropped et, placés ici en attendant leur famille, util-basemap, util-mapbar, util-maptool et les traits d'une carte Mapbox"),
				'markup' => s("\\main\\FranceUi::getMap(), qui pose util-map, util-map-labels, util-map-name, util-map-value, util-map-legend et util-map-scale ; l'écran fournit l'échelle et sa légende"),
			],
			'months' => [
				'class' => 'util-timeline',
				'role' => s("Une frise des douze mois, un état par mois."),
				'when' => s("Un suivi mois par mois, dont chaque mois ouvre sa vue."),
				'not' => [
					[s("une quantité mois par mois, dont on compare la forme de l'année d'une ligne à l'autre"), 'util-bars'],
				],
				'variants' => s("util-timeline-done, util-timeline-todo, util-timeline-upcoming pour un mois à venir qui porte déjà une valeur, util-timeline-none pour une case sans valeur ; util-timeline-selected cercle le mois qu'on regarde, par-dessus son état"),
				'markup' => s("un .util-timeline, une util-timeline-month par mois, en a, ou en span pour un mois sans valeur"),
			],
			'timeline' => [
				'class' => 'util-timeline-event',
				'role' => s("La pastille d'un geste daté."),
				'when' => s("Des gestes posés en pourcentage d'un axe de jours, sur la fenêtre de calendrier propre à l'écran."),
				'not' => [
					[s("des plages et un repère sur un axe gradué"), 'util-rail'],
				],
				'variants' => s("util-timeline-event-expected, vide et cernée ; util-timeline-event-period, en ruban de durée ; util-timeline-event-frame, le ruban de fond sur toute la durée de l'objet ; puis, montrée plus bas, util-timeline-striped"),
				'markup' => s("un i.util-timeline-event par geste ; la couleur et le placement viennent de l'écran"),
			],
			'campaign' => [
				'class' => 'util-campaign',
				'role' => s("Les temps d'une campagne, en étapes."),
				'when' => s("Une campagne dont une seule étape est en cours."),
				'not' => [
					[s("le cycle d'un objet, daté étape par étape"), 'util-steps'],
				],
				'variants' => s("is-done, is-active, la seule à porter un cadre, is-todo ; une étape en a, qui se soulève au survol ; puis, montrée plus bas, une étape seule dans une grille util-cards"),
				'markup' => s("un .util-campaign, une util-campaign-step par temps, son util-campaign-marker puis son util-campaign-content, qui porte util-campaign-date, util-campaign-label et util-campaign-description"),
			],
			'calendars' => [
				'class' => 'util-calendar',
				'role' => s("Une échéance montrée comme une page de calendrier."),
				'when' => s("Là où l'échéance est ce que l'œil cherche en premier."),
				'not' => [
					[s("une rangée qui sert à changer de jour et dit le temps de chacun"), 'util-days'],
				],
				'variants' => s("puis, montrés plus bas, \\util\\DateUi::calendarWeekday(), util-calendar-selected, util-calendar-lg, util-calendar-value, util-calendar-none et util-week-picker"),
				'markup' => s("\\util\\DateUi::calendar(), qui pose util-calendar-day et util-calendar-month ; la teinte vient de --accent, posée par ce qui la porte ; dans une rangée util-days, l'état d'un jour se pose sur son util-day, is-disabled compris"),
			],
			'calendarSheets' => [
				'class' => 'util-calendar-sheets',
				'role' => s("Des pages de calendrier, une par mois."),
				'when' => s("Des dates retenues qu'on lit mois par mois, un trou dans une colonne disant une date sautée."),
				'not' => [
					[s("les semaines d'un mois avec le temps de chaque jour"), 'util-days-month'],
				],
				'variants' => s("util-calendar-sheet-held, util-calendar-sheet-out ; puis, montrés plus bas, a.util-calendar-sheet-day, util-calendar-sheets-spaced et util-calendar-sheet-selected"),
				'markup' => s("un .util-calendar-sheets, une util-calendar-sheet par mois, son mois en util-label, util-calendar-sheet-weekday pour les initiales des jours, puis un util-calendar-sheet-day par jour, après une case vide par jour qui précède le 1er"),
			],
			'days' => [
				'class' => 'util-days',
				'role' => s("Les sept jours d'une semaine, chacun une cible."),
				'when' => s("Changer de jour en lisant le temps de chacun."),
				'not' => [
					[s("des quantités comparées, sans cellule cliquable ni date"), 'util-bars'],
				],
				'variants' => s("is-today, is-selected, is-future, is-over ; puis, montrés plus bas, is-absent, util-days-month avec is-disabled et is-warning, util-days-entry, util-day-absents et le jour en cellule de tableau"),
				'markup' => s("un .util-days, un util-day par jour, sa util-day-value, sa util-day-column où montent util-day-worked et util-day-done, puis sa pastille calendarWeekday()"),
			],
			'onboarding' => [
				'class' => 'onboarding',
				'role' => s("Un premier écran guidé, une question à la fois, à côté d'un aperçu qui se construit."),
				'when' => s("Une fonctionnalité vide que quelques questions suffisent à configurer."),
				'not' => [
					[s("les étapes d'une configuration déjà ouverte, chacune avec son bloc"), 'util-procedure-icon'],
				],
				'variants' => s("onboarding-progress-names ; puis, montrés plus bas, onboarding-panel-plain et onboarding-panel-progress-top"),
				'markup' => s("un .onboarding, son onboarding-header, un util-card onboarding-panel, ses onboarding-steps où l'étape courante porte onboarding-step is-current, la barre onboarding-stepper, puis onboarding-preview ; au-dessus du panneau, un onboarding-bound ; les champs propres à un choix dans son onboarding-tile-fields ; le pied en onboarding-footer, qui porte onboarding-footer-main et onboarding-footer-side, où vont « Passer cette étape » et le Retour d'un parcours sans noms d'étapes ; is-skipped sur le nom d'une étape passée, posé par MainOnboarding.goTo() ; la feuille main/onboarding.css se charge par \\Asset::css()"),
			],
			'procedure' => [
				'class' => 'util-procedure',
				'role' => s("Des étapes numérotées à suivre dans l'ordre, reliées par un trait."),
				'when' => s("Un mode d'emploi rédigé."),
				'not' => [
					[s("le cycle d'un objet, daté étape par étape"), 'util-steps'],
				],
				'variants' => s("aucune"),
				'markup' => s("un ol.util-procedure, un li.util-procedure-step par étape, sa pastille en première colonne et tout son contenu en seconde, le titre en util-procedure-title"),
			],
			'criteriaChecks' => [
				'class' => 'util-procedure-check',
				'role' => s("Des critères à vérifier, une coche à la place du numéro, sans trait."),
				'when' => s("Des critères qui ne se suivent pas."),
				'not' => [
					[s("des étapes à suivre dans l'ordre"), 'util-procedure'],
				],
				'variants' => s("aucune"),
				'markup' => s("sur un util-procedure ; la coche est une marque, pas une case, peinte de --marker-color, que le thème repeint comme montré plus bas"),
			],
			'iconSteps' => [
				'class' => 'util-procedure-icon',
				'role' => s("Des étapes à icône, chacune avec son bloc."),
				'when' => s("Les étapes d'une configuration, faites ou à faire."),
				'not' => [
					[s("une fonctionnalité vide, configurée une question à la fois"), 'onboarding'],
				],
				'variants' => s("aucune"),
				'markup' => s("sur un util-procedure, un util-icon util-icon-round en premier enfant de chaque étape, util-icon-active et l'icône check-lg pour une étape faite ; une étape qui porte un util-deck descend seule sa pastille de --marker-offset, le titre du deck en util-deck-title-heading"),
			],
			'chain' => [
				'class' => 'util-procedure-dots',
				'role' => s("Des jalons à la suite, un point sans numéro."),
				'when' => s("Des dates à la suite, qui n'ont pas de rang à compter."),
				'not' => [
					[s("des gestes placés sur un axe de jours"), 'util-timeline-event'],
				],
				'variants' => s("util-procedure-step-open"),
				'markup' => s("sur un util-procedure ; la teinte vient de --marker-background et --step-line, posées par l'écran"),
			],
			'route' => [
				'class' => 'util-chain',
				'role' => s("Un trajet de deux ou trois étapes, reliées par des flèches légendées."),
				'when' => s("Le chemin d'un flux, d'un compte à un autre."),
				'not' => [
					[s("des étapes que le lecteur suit dans l'ordre"), 'util-procedure'],
				],
				'variants' => s("aucune"),
				'markup' => s("un .util-chain, des util-chain-step avec leur icône et leur nom, séparées par des util-chain-arrow, avec leur icône et une légende facultative ; la chaîne se lit de haut en bas sous 576 px"),
			],
			'steps' => [
				'class' => 'util-steps',
				'role' => s("Le cycle d'un objet, en étapes datées."),
				'when' => s("Un objet qui passe par des étapes faites, en cours ou à venir."),
				'not' => [
					[s("les temps d'une campagne, dont une seule est en cours"), 'util-campaign'],
				],
				'variants' => s("aucune"),
				'markup' => s("new \\util\\StepsUi()->display(), qui pose sur chaque étape la classe done ou active reçue, et un point d'interrogation faute de date ; le rang dans la pastille s'écrit à la main, comme dans le tunnel du relevé de prix"),
			],
			'feedback' => [
				'class' => 'util-flash · util-pending · util-waiting',
				'role' => s("Le retour d'un geste, pendant et après son écriture."),
				'when' => s("Un envoi au serveur, le temps de sa réponse, puis sur ce qu'il a changé quand l'œil ne le trouverait pas seul."),
				'not' => [
					[s("une confirmation qui ne prend pas de place dans la page"), 'util-toast'],
				],
				'variants' => s("util-flash-success"),
				'markup' => s("util-flash se décide en PHP, dans l'action, par son argument highlight ; util-pending se pose seul sur le bouton d'envoi ; util-waiting sur un bloc qui porte data-waiting-block"),
			],
			'vanish' => [
				'class' => 'util-vanish',
				'role' => s("La copie d'un nœud retiré, qui s'efface à sa place."),
				'when' => s("Une réponse qui retire un nœud loin du geste qui l'a demandé, comme une liste qui se vide ailleurs dans la page."),
				'not' => [
					[s("ce qui reste et a changé"), 'util-flash'],
				],
				'variants' => s("aucune"),
				'markup' => s("qs(…)->remove(vanish: TRUE), qui ne se combine ni avec mode ni avec delay"),
			],
			'publicHero' => [
				'class' => 'util-page-hero',
				'role' => s("La bande d'accueil d'une page publique."),
				'when' => s("Une formation, une leçon, la FAQ ou les nouveautés du logiciel, et la saison en cours sur le tableau de bord d'une AMAP."),
				'not' => [
					[s("les autres écrans d'un groupe, d'un réseau ou d'une AMAP"), 'util-masthead'],
					[s("un écran de structure"), 'util-page-header'],
				],
				'variants' => s("aucune"),
				'markup' => s("\\main\\DesignUi::getPageHero(), qui pose seule le h1 ; util-page-hero-inner avec container, util-page-hero-top, util-page-hero-lead, la recherche util-omnibox-hero, les chiffres util-figures-line-card, la tuile util-icon-raised"),
			],
			'groupMasthead' => [
				'class' => 'util-masthead',
				'role' => s("La bande claire d'un écran de groupe."),
				'when' => s("La tête de tout écran rendu par le gabarit d'un groupe, d'un réseau ou d'une AMAP, qui la pose et la remplit lui-même."),
				'not' => [
					[s("une page publique, hors d'une structure"), 'util-page-hero'],
				],
				'variants' => s("util-masthead-network, util-masthead-amap"),
				'markup' => s("\\main\\DesignUi::getPageMasthead(), qui pose util-page-hero et util-masthead ensemble, util-masthead ramenant la bande de util-page-hero à la hauteur d'une tête d'écran, autour d'un \\main\\DesignUi::getPageHeader() ; util-masthead-head, util-masthead-thumb ou util-masthead-logo, util-masthead-actions, util-masthead-bar, util-masthead-figures, util-masthead-foot, util-masthead-tabs ; util-page-header-back-label en surtitre"),
			],
			'publicBands' => [
				'class' => 'util-band',
				'role' => s("Une section d'une page publique sur toute la largeur."),
				'when' => s("Chaque section sous la bande d'accueil d'une page de présentation, d'un tutoriel ou de la FAQ."),
				'not' => [
					[s("l'accueil, en tête de page"), 'util-page-hero'],
				],
				'variants' => s("util-band-alt ; puis, montrée plus bas, util-band-intro"),
				'markup' => s("\\main\\DesignUi::getBand() pour la section et son container, \\main\\DesignUi::getBandHead() pour util-band-head et util-band-lead ; util-band-body, util-band-mascot ; dans les bandes, util-layout-aside-start, util-tint-phase-1 à util-tint-phase-4, util-procedure-tint, util-deck-round, color-encre-accent, util-points, util-testimony, util-check-list, util-brushed"),
			],
			'publicAnswer' => [
				'class' => 'util-filter-options-column',
				'role' => s("Le rail d'util-filter-options en colonne de côté."),
				'when' => s("Le filtre d'une liste tenu en colonne de côté, comme la FAQ, ou le menu d'une page de lecture, comme une leçon ou la documentation."),
				'not' => [
					[s("le bandeau des filtres au-dessus d'une liste, un groupe d'options par critère"), 'util-filters'],
				],
				'variants' => s("util-filter-options-nav ; puis, montré plus bas, util-filter-options-menu"),
				'markup' => s("util-filter-option, son util-counter, is-selected amené dans la vue par Lime.Tab.reveal() ; util-filter-options-title, util-filter-options-title-subject, util-filter-options-title-group ; autour, util-deck-kicker, util-split, util-link-path, util-brand-scope, util-deck-title-heading, util-block-round, util-stack"),
			],
			'pageHeader' => [
				'class' => 'util-page-header',
				'role' => s("L'en-tête d'un écran de structure."),
				'when' => s("Chaque écran sous le gabarit d'une structure, de la liste à la fiche d'un objet, réglages compris."),
				'not' => [
					[s("une page publique"), 'util-page-hero'],
					[s("un écran de groupe, de réseau ou d'AMAP"), 'util-masthead'],
				],
				'variants' => s("util-page-header-identity, posé par l'argument logo"),
				'markup' => s("\\main\\DesignUi::getPageHeader(), qui émet le h1 dans util-page-band ; util-page-header-main, util-page-header-context, util-page-header-aside ; les gestes par tools, create et settings, dans cet ordre, la création par \\main\\DesignUi::getCreateButton(), un outil à libellé par \\main\\DesignUi::getGestureLabel(), la roue par \\main\\DesignUi::getGear() ; puis, montrés plus bas, les pages voisines par l'argument pages, et sous la tête les onglets tabs"),
			],
			'paper' => [
				'class' => 'util-paper',
				'role' => s("La feuille gris clair des écrans d'une structure."),
				'when' => s("Un contenu de structure posé hors de son gabarit, dont le main ne pose pas la feuille."),
				'not' => [
					[s("un contenu blanc posé sur la feuille"), 'util-block'],
				],
				'variants' => s("aucune"),
				'markup' => s("--domain donne la teinte ; util-start et util-table s'y posent sans classe à ajouter"),
			],
			'aside' => [
				'class' => 'util-layout-aside',
				'role' => s("Le contenu, et son résumé en vue à côté."),
				'when' => s("Un contenu et ce qui l'accompagne en vue à droite, comme le panier d'un abonnement ou d'un contrat d'AMAP, ou l'aperçu d'un justificatif à côté de sa fiche."),
				'not' => [
					[s("des colonnes égales sous un titre"), 'util-board'],
					[s("la colonne de côté à gauche, qui introduit ou filtre ce qu'elle précède"), 'util-layout-aside-start'],
				],
				'variants' => s("aucune"),
				'markup' => s("util-layout-aside-side ; le panier en util-basket-row, util-basket-icon, util-basket-head et util-basket-total, puis util-basket-bar, que l'appelant masque là où le panier reste en vue, par hide-lg-up ou hide-md-up ; util-basket-row et util-basket-total servent aussi hors panier, au récapitulatif d'un document"),
			],
			'previews' => [
				'class' => 'util-preview',
				'role' => s("L'aperçu d'un document déposé."),
				'when' => s("Un justificatif ou la pièce d'un document reçu, lu à côté de sa fiche, ou les pièces jointes d'un document en vignettes."),
				'not' => [
					[s("l'image d'un objet dans sa carte"), 'util-vignette'],
				],
				'variants' => s("util-preview-pdf, util-preview-photo, util-preview-erased"),
				'markup' => s("une div par document ; puis, montrés plus bas, util-previews et util-previews-large, util-previews-item, util-previews-remove, util-previews-selected et util-previews-add"),
			],
			'board' => [
				'class' => 'util-board',
				'role' => s("Des colonnes titrées sous un titre, séparées par un filet."),
				'when' => s("L'accueil d'une structure."),
				'not' => [
					[s("un contenu et son résumé"), 'util-layout-aside'],
				],
				'variants' => s("aucune"),
				'markup' => s("util-board-title, util-board-columns, util-board-column et ses util-board-column-title, util-board-column-body, util-board-column-foot ; util-rows et util-row dans le corps ; puis, montrée plus bas, util-board-columns-1 sur util-board-columns"),
			],
			'brushed' => [
				'class' => 'util-brushed',
				'role' => s("Une bande séparée du fond de page par un coup de pinceau."),
				'when' => s("Une bande qui tranche sur le fond de page, comme le démarrage d'une fonctionnalité, une rubrique du tableau de bord ou une section de page publique."),
				'not' => [
					[s("la section d'une page publique elle-même, que util-brushed habille"), 'util-band'],
				],
				'variants' => s("aucune"),
				'markup' => s("util-separator-top et util-separator-bottom en enfants directs, chacun par \\main\\DesignUi::getBrushSeparator(), ou le seul pinceau du haut quand l'élément suivant pose le sien sur son bord bas ; une section de page publique entière par \\main\\DesignUi::getBrushedBand() ; la couleur par --util-brushed-color"),
			],
			'contentBlocks' => [
				'class' => 'util-block',
				'role' => s("Un bloc qui encadre un contenu."),
				'when' => s("Un contenu à détacher de la page."),
				'not' => [
					[s("un message à teinte"), 'util-block-info'],
					[s("un bloc vide"), 'util-block-empty'],
					[s("un objet, et non un contenu"), 'util-card'],
					[s("un encart sur fond doux, sans contour, à l'intérieur d'un contenu"), 'util-block-inside'],
					[s("un chemin secondaire au pointillé à côté du chemin principal, montré plus bas"), 'util-block-optional'],
				],
				'variants' => s("util-block-dark avec son fond bg-*, util-block-round"),
				'markup' => s("un tableau posé dans le bloc reprend son retrait"),
			],
			'collapsible' => [
				'class' => 'util-fold',
				'role' => s("Une section repliée par son titre, sans cadre."),
				'when' => s("Une section qu'on ouvre quand on en a besoin."),
				'not' => [
					[s("replier l'intérieur d'une carte"), 'util-deck-fold'],
				],
				'variants' => s("aucune"),
				'markup' => s("util-fold-head, qui porte le h3, son util-counter et util-fold-chevron, puis util-fold-content ; util-fold-open, que l'écran bascule"),
			],
			'actionBars' => [
				'class' => 'util-title · util-action',
				'role' => s("Un titre de section et son action à droite."),
				'when' => s("Une section qui porte un geste sur ce qu'elle montre."),
				'not' => [
					[s("l'en-tête d'un écran"), 'util-page-header'],
				],
				'variants' => s("util-action-fold, montré plus bas"),
				'markup' => s("le titre puis le bouton ; dans le titre, un span.util-counter qui compte ce que la section montre ; flex-buttons pour plusieurs gestes ; puis, montré plus bas, util-subtitle"),
			],
			'carousel' => [
				'class' => 'util-carousel',
				'role' => s("Une diapositive à la fois, qu'on fait glisser."),
				'when' => s("Une suite de cartes qui ne tient pas sur une rangée, comme les notes du carnet rattachées à un écran de production."),
				'not' => [
					[s("une suite de chiffres de même nature"), 'util-figures-overflow'],
				],
				'variants' => s("util-carousel-cards, montré plus bas"),
				'markup' => s("util-carousel-track, util-carousel-slide, util-carousel-dots, démarré par MainCarousel.start()"),
			],
		];

	}

	/**
	 * La page a deux lecteurs et une seule source.
	 *
	 * Un humain vient y reconnaître un composant : il lui faut le titre, le rôle en une phrase et la
	 * démonstration. Un agent vient y chercher ce qu'il doit écrire : il lui faut toutes les
	 * annotations, qui portent les pièges, et le balisage exact sous chaque démonstration. Deux
	 * documents divergeraient — c'est ce qui est arrivé à `badge-off`, dont le commentaire
	 * décrivait un contour quand la règle faisait une opacité ; ici la vue agents est la même page,
	 * moins la coupe.
	 */
	public function all(bool $forAgents = FALSE): string {

		\Asset::js('design', 'designAi.js');

		$sections = $this->sections($forAgents);
		$families = $this->families();
		$fiches = $this->fiches();
		// Une fiche peut couvrir plusieurs classes, nommées dans son titre et séparées par « · ».
		$sectionsByClass = [];
		foreach($fiches as $key => $fiche) {
			foreach(explode(' · ', $fiche['class']) as $class) {
				$sectionsByClass[$class] = $key;
			}
		}
		$familyFirst = array_key_first($families);

		$h = '<div class="tabs tabs-v" id="design-summary">';

			$h .= '<div class="tabs-item tabs-item-sticky">';
				foreach($families as $key => $family) {
					$h .= '<a class="tab-item'.($key === $familyFirst ? ' selected' : '').'" data-family="'.$key.'" data-target="'.$key.'" onclick="DesignSummary.go(this)">'.$family['title'].'</a>';
				}
				$h .= '<a class="tab-item" data-family="index" data-target="index" onclick="DesignSummary.go(this)">'.$this->getFinderTitle().'</a>';
			$h .= '</div>';

			$h .= '<div class="tab-panel selected">';

				foreach($families as $key => $family) {

					$h .= '<div id="'.$key.'" class="design-family">';

						$h .= '<h2 class="design-family-title">'.$family['title'].'</h2>';
						$h .= '<p class="util-annotation">'.$family['question'].'</p>';

						if($family['which'] !== []) {
							$h .= $this->which($family['which']);
						}

						foreach($family['sections'] as $section) {
							$h .= $this->section($section, $sections[$section], $forAgents, $family['which'] !== [], $fiches[$section] ?? [], $sectionsByClass, $family['headings'][$section] ?? '');
						}

					$h .= '</div>';

					$h .= '<hr />';

				}

				// L'index suit les familles sans en être une, et prend leur forme pour que le sommaire le marque.
				$h .= '<div id="index" class="design-family">';
					$h .= '<h2 class="design-family-title">'.$this->getFinderTitle().'</h2>';
					$h .= $this->section('finder', $sections['finder'], $forAgents);
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	private function sections(bool $forAgents): array {

		return [
			'conventions' => $this->conventions(),
			'titles' => $this->titles(),
			'textSizes' => $this->textSizes(),
			'mono' => $this->mono(),
			'colors' => $this->colors(),
			'themeTokens' => new ThemeTokenAiUi()->tokens(),
			'radius' => $this->limeRadiusGradient(),
			'spaces' => $this->spaces(),
			'domains' => $this->domains(),
			'iconDiscs' => $this->iconDiscs(),
			'criteria' => $this->criterionMarks(),
			'templates' => $this->templates(),
			'pageHeader' => $this->pageHeader(),
			'paper' => $this->paper(),
			'aside' => $this->aside(),
			'previews' => $this->previews(),
			'board' => $this->board(),
			'brushed' => $this->brushed(),
			'contentBlocks' => $this->contentBlocks(),
			'collapsible' => $this->collapsible(),
			'actionBars' => $this->actionBars(),
			'carousel' => $this->carousel(),
			'cards' => $this->cards(),
			'cardsAccent' => $this->cardsAccent(),
			'decks' => $this->decks(),
			'records' => $this->records(),
			'actionCards' => $this->actionCards(),
			'tiles' => $this->tiles(),
			'vignettes' => $this->vignettes(),
			'presentations' => $this->presentations(),
			'presentationDetail' => $this->presentationDetail(),
			'marks' => $this->marks(),
			'counters' => $this->counters(),
			'stamps' => $this->stamps(),
			'dots' => $this->dots(),
			'texts' => $this->texts(),
			'badges' => $this->badges(),
			'statusDots' => $this->statusDots(),
			'status' => $this->status(),
			'reliability' => $this->reliability(),
			'follows' => $this->follows(),
			'notAuto' => $this->notAuto(),
			'empty' => $this->emptyScreens(),
			'cta' => $this->blocksCta(),
			'start' => $this->start(),
			'welcome' => $this->welcome(),
			'tintedBlocks' => $this->tintedBlocks(),
			'messages' => $this->messages(),
			'inline' => $this->inline(),
			'highlight' => $this->highlight(),
			'overline' => $this->overline(),
			'flash' => $this->flash(),
			'tips' => $this->tips(),
			'typedValue' => $this->typedValue(),
			'buttonsUsage' => $this->buttonsUsage($forAgents),
			'buttonsFull' => $this->buttonsFull(),
			'buttonsLight' => $this->buttonsLight(),
			'buttonDomains' => $this->buttonDomains(),
			'buttonsColor' => $this->buttonsColor(),
			'buttonsOutline' => $this->buttonsOutline(),
			'buttonsFocus' => $this->buttonsFocus(),
			'sizes' => $this->limeBtnSizes(),
			'buttonSelect' => $this->buttonSelect(),
			'buttonModifiers' => $this->buttonModifiers(),
			'entries' => $this->entries(),
			'drop' => $this->drop(),
			'dropdowns' => $this->dropdowns(),
			'batch' => $this->batch(),
			'weigh' => $this->weigh(),
			'confirmPanel' => $this->confirmPanel(),
			'drag' => $this->dragHandles(),
			'ticks' => $this->ticks(),
			'displayMenu' => $this->displayMenu(),
			'copy' => $this->copy(),
			'fieldsets' => $this->fieldsets(),
			'fileButton' => $this->fileButton(),
			'sheet' => $this->sheet(),
			'fieldsGrid' => $this->fieldsGrid(),
			'radios' => $this->radioGroups(),
			'choices' => $this->choices(),
			'fields' => $this->formFields(),
			'fieldInfo' => $this->fieldInfo(),
			'switches' => $this->switches(),
			'clearable' => $this->clearable(),
			'autocomplete' => $this->autocomplete(),
			'quick' => $this->quick(),
			'valueDetail' => $this->valueDetail(),
			'dateChecks' => $this->dateChecks(),
			'formOptions' => $this->formOptions(),
			'formChapters' => $this->formChapters(),
			'composer' => $this->composer(),
			'searches' => $this->searches(),
			'omnibox' => $this->omnibox(),
			'chips' => $this->chips(),
			'filters' => $this->filters(),
			'screenSettings' => $this->screenSettings(),
			'scope' => $this->scope(),
			'tables' => $this->limeTables(),
			'grids' => $this->limeGrids(),
			'groups' => $this->tableGroups(),
			'listStates' => $this->listStates(),
			'heat' => $this->heat(),
			'bellows' => $this->bellows(),
			'pagination' => $this->pagination(),
			'overflow' => $this->overflow(),
			'seeds' => $this->seeds(),
			'comments' => $this->comments(),
			'tabs' => $this->tabs(),
			'tabsHorizontal' => $this->tabsHorizontal(),
			'figures' => $this->figures(),
			'figuresLine' => $this->figuresLine(),
			'figuresMuted' => $this->figuresMuted(),
			'figuresSelected' => $this->figuresSelected(),
			'figuresScroll' => $this->figuresScroll(),
			'summarize' => $this->summarize(),
			'variation' => $this->variation(),
			'ring' => $this->ring(),
			'ratings' => $this->ratings(),
			'progress' => $this->progress(),
			'rail' => $this->rail(),
			'graduation' => $this->graduation(),
			'legend' => $this->legend(),
			'bars' => $this->bars(),
			'formats' => $this->formats(),
			'maps' => $this->maps(),
			'months' => $this->months(),
			'timeline' => $this->timeline(),
			'campaign' => $this->campaign(),
			'calendars' => $this->calendars(),
			'calendarSheets' => $this->calendarSheets(),
			'days' => $this->days(),
			'onboarding' => $this->onboarding(),
			'procedure' => $this->procedure(),
			'criteriaChecks' => $this->criteriaChecks(),
			'iconSteps' => $this->iconSteps(),
			'chain' => $this->chain(),
			'route' => $this->route(),
			'steps' => $this->steps(),
			'motion' => new JuicinessAiUi()->motion(),
			'feedback' => new JuicinessAiUi()->feedback(),
			'vanish' => new JuicinessAiUi()->vanish(),
			'publicHero' => $this->publicHero(),
			'groupMasthead' => $this->groupMasthead(),
			'publicBands' => $this->publicBands(),
			'publicAnswer' => $this->publicAnswer(),
			'finder' => $this->finder(),
		];

	}

	/**
	 * Deux colonnes tiennent à 360 px : le tableau se pose sans util-overflow-sm, qui lui donnerait une
	 * largeur minimale et le ferait défiler.
	 */
	private function which(array $which): string {

		$h = '<h3>'.s("Lequel ?").'</h3>';

		$h .= '<div class="util-table util-table-even design-which">';
			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Pour…").'</div>';
				$h .= '<div>'.s("Prendre").'</div>';
			$h .= '</div>';
			foreach($which as [$need, $class, $section]) {
				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$need.'</div>';
					$h .= '<div>'.self::getFicheLink($class, $section).'</div>';
				$h .= '</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Dans une famille rangée en fiches, la fiche remplace le titre de sa section, et ses rubriques
	 * les annotations de toutes les sections qu'elle couvre, variantes comprises : la vue de lecture
	 * n'en garde aucune, la vue agents les garde toutes. Une section sans fiche prend à sa place le
	 * titre que lui donne sa famille. Le balisage montré est celui de la démonstration seule, son
	 * premier titre compris.
	 */
	private function section(string $key, string $section, bool $forAgents, bool $filed = FALSE, array $fiche = [], array $sectionsByClass = [], string $heading = ''): string {

		$h = '<section id="design-'.$key.'">';
			if($filed) {
				if($fiche !== []) {
					$h .= $this->fiche($fiche, $sectionsByClass);
				} else if($heading !== '') {
					$h .= '<h3>'.$heading.'</h3>';
				}
				$h .= '<div class="design-demo">'.($forAgents ? $section : self::replaceAnnotations($section, fn() => '')).'</div>';
			} else {
				$h .= $forAgents ? $section : self::getShortened($section);
			}
			if($forAgents) {
				$h .= '<details class="design-source"><summary>'.s("Le balisage").'</summary><pre>'.encode(self::getMarkup($section, $filed === FALSE)).'</pre></details>';
			}
		$h .= '</section>';

		return $h;

	}

	private function fiche(array $fiche, array $sectionsByClass): string {

		$not = array_map(function(array $neighbour) use ($sectionsByClass) {
			[$text, $class] = $neighbour;
			return $text.' '.(array_key_exists($class, $sectionsByClass) ? self::getFicheLink($class, $sectionsByClass[$class]) : '<span class="util-literal">'.$class.'</span>');
		}, $fiche['not']);

		$h = '<h3>'.$fiche['class'].'</h3>';

		$h .= '<dl class="util-presentation util-presentation-1 mb-1">';
			$h .= '<dt>'.s("Rôle").'</dt><dd>'.$fiche['role'].'</dd>';
			$h .= '<dt>'.s("Quand").'</dt><dd>'.$fiche['when'].'</dd>';
			$h .= '<dt>'.s("Pas pour").'</dt><dd>'.implode(' ; ', $not).'</dd>';
			$h .= '<dt>'.s("Variantes").'</dt><dd>'.$fiche['variants'].'</dd>';
			$h .= '<dt>'.s("Balisage").'</dt><dd>'.$fiche['markup'].'</dd>';
		$h .= '</dl>';

		return $h;

	}

	/**
	 * Comme le sommaire, le lien fait défiler sans ancre, qui rechargerait la page.
	 */
	private static function getFicheLink(string $class, string $section): string {
		return '<a data-target="design-'.$section.'" onclick="DesignSummary.go(this)"><span class="util-literal">'.$class.'</span></a>';
	}

	/**
	 * La vue de lecture : le rôle, en une phrase, puis la démonstration. Les annotations suivantes
	 * disent les pièges, elles appartiennent à la vue agents.
	 */
	private static function getShortened(string $section): string {

		$first = TRUE;

		return self::replaceAnnotations($section, function(string $annotation) use (&$first) {

			if($first === FALSE) {
				return '';
			}

			$first = FALSE;

			return '<p class="util-annotation">'.self::getFirstSentence($annotation).'</p>';

		});

	}

	/**
	 * Récrit les annotations de la page, et elles seules. Un composant peut écrire lui-même une
	 * util-annotation, les formats sous l'appel d'util-drop par exemple : posée dans un élément util-*,
	 * elle appartient à la démonstration, qui doit rester celle que l'écran affiche.
	 */
	private static function replaceAnnotations(string $section, \Closure $replace): string {

		$components = [];

		return preg_replace_callback('#<div\b([^>]*)>|</div>|<p class="util-annotation(?: [^"]*)?"[^>]*>(.*?)</p>#s', function($matches) use (&$components, $replace) {

			if($matches[0] === '</div>') {
				array_pop($components);
				return $matches[0];
			}

			if(str_starts_with($matches[0], '<div')) {
				$components[] = (preg_match('#class="([^"]* )?util-#', $matches[1]) === 1);
				return $matches[0];
			}

			if(in_array(TRUE, $components, TRUE)) {
				return $matches[0];
			}

			return $replace($matches[2]);

		}, $section);

	}

	/**
	 * La première phrase d'un texte qui porte du balisage. Le point cherché est hors d'une balise, et
	 * la coupe est abandonnée si elle laisserait une balise ouverte : une annotation entière vaut
	 * mieux qu'un `<b>` qui déborde sur le reste de la page.
	 */
	private static function getFirstSentence(string $text): string {

		$inside = FALSE;

		for($position = 0; $position < mb_strlen($text) - 1; $position++) {

			$character = mb_substr($text, $position, 1);

			if($character === '<') {
				$inside = TRUE;
			} else if($character === '>') {
				$inside = FALSE;
			} else if($inside === FALSE and $character === '.' and mb_substr($text, $position + 1, 1) === ' ') {

				$sentence = mb_substr($text, 0, $position + 1);

				if(substr_count($sentence, '<') === substr_count($sentence, '</') * 2 + substr_count($sentence, '<br')) {
					return $sentence;
				}

				return $text;

			}

		}

		return $text;

	}

	/**
	 * Le balisage d'une section, sans ses annotations ni son titre : ce qu'un agent recopie.
	 */
	private static function getMarkup(string $section, bool $titled): string {

		$markup = self::replaceAnnotations($section, fn() => '');

		if($titled) {
			$markup = preg_replace('#<h[1-4][^>]*>.*?</h[1-4]>#s', '', $markup, 1);
		}

		return trim(preg_replace('#\n{2,}#', "\n", $markup));

	}

	public function domains(): string {

		$domains = [
			'production' => s("Production"),
			'commercialisation' => s("Vente"),
			'accounting' => s("Comptabilité"),
			'settings' => s("Réglages"),
			'amap' => s("AMAP"),
		];

		$h = '<p>'.s("util-domain dit de quel univers vient un bloc ou une ligne, en capitales grasses à l'encre de l'univers, posées au-dessus du nom.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-domain-production|commercialisation|accounting|settings|amap choisissent l'encre. AMAP prend l'encre du texte, son jeton étant trop proche du rouge d'une alerte. Sur un bloc util-block-dark, aucun jeton ne tient : la pastille passe au blanc.").'</p>';

		$h .= '<div class="design-pair mb-2">';
			foreach($domains as $domain => $label) {
				$h .= '<div class="util-block">';
					$h .= '<div class="util-domain util-domain-'.$domain.'">'.$label.'</div>';
					$h .= '<b>'.s("Contrôler les livraisons de la semaine").'</b>';
				$h .= '</div>';
			}
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Sur fond sombre").'</h4>';
		$h .= '<p class="util-annotation">'.s("Il n'y a pas de thème sombre. util-block-dark est un bloc isolé sur un écran clair, qui passe son texte en blanc sur un fond plein, et --color-encre avec : posé dans une util-band, ses titres gardent la typographie de la bande et prennent le blanc. Une surface claire posée dans le bloc redéclare --color-encre avec sa couleur de texte, sans quoi un titre de bande y sortirait blanc sur blanc. Son contenu ne porte que du blanc et la couleur de marque, jamais une teinte d'univers, aucun jeton d'univers ne tenant sur ce fond (contraste de 1,11 à 1,45 pour un plancher de 3). La pastille d'un univers y passe donc au blanc.").'</p>';
		$h .= '<div class="util-block util-block-dark bg-primary mb-2">';
			$h .= '<div class="util-domain util-domain-commercialisation">'.s("Vente").'</div>';
			$h .= '<b>'.s("Contrôler les livraisons de la semaine").'</b>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("En ligne").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-domain-inline réserve une colonne à la pastille, pour aligner les noms d'une liste de lignes. Sous 575 px la colonne se libère et la pastille prend sa largeur.").'</p>';
		$h .= '<div class="mb-2">';
			foreach($domains as $domain => $label) {
				$h .= '<div style="display: flex; gap: 0.75rem; align-items: baseline;">';
					$h .= '<div class="util-domain util-domain-inline util-domain-'.$domain.'">'.$label.'</div>';
					$h .= '<span>'.s("Contrôler les livraisons de la semaine").'</span>';
				$h .= '</div>';
			}
		$h .= '</div>';

		return $h;

	}

	public function paper(): string {

		$h = '<p class="util-annotation">'.s("Au bureau, les écrans d'une structure posent leur tête et leur contenu sur une feuille gris clair, à droite de la colonne de navigation, qui porte la teinte de leur univers et qu'un filet sépare de la feuille. La tête de l'écran n'a pas de fond à elle, le titre se pose à même la feuille. Les blocs et les lignes de tableau s'y lisent en blanc. Le gabarit structure fait de son main cette feuille ; util-paper la pose ailleurs, comme ici. La tablette et le téléphone n'ont pas de colonne, et leur contenu se pose sur la même feuille, sous la barre de la structure. Hors du gabarit, où rien ne fixe l'univers, --domain retombe sur --primary ; cette démonstration pose celui de la production.").'</p>';
		$h .= '<p class="util-annotation">'.s("Sur la feuille, la bande d'util-start est blanche, bordée d'un trait brossé de la couleur foncée de l'univers, et son texte s'écrit à l'encre. Elle est posée à la main : brossée sur ses quatre côtés, inclinée d'un demi-degré tandis que son texte reste droit, elle tient dans la colonne sans déborder, à toutes les largeurs. Sa pastille, ses liens et ses boutons prennent la couleur de l'univers, quelle que soit la classe de bouton posée : un bouton plein en porte le fond, les autres le contour. Ses boutons prennent la taille des gestes de la page. Avec ses gains (la fiche util-start, dans « Le vide et l'invitation »), les tuiles gardent leur fond clair et leur pastille prend la couleur de l'univers. Sur la feuille, les lignes blanches d'un tableau se posent à même la feuille. Dans un tableau à bandes, une ligne sur deux prend le fond de la feuille, et tr.tr-title perd son fond : le groupe se dit par l'écart au-dessus de lui et un trait de l'univers dessous, son titre restant à l'encre du texte pour ne pas se lire comme un lien.").'</p>';

		$h .= '<p class="util-annotation">'.s("Une util-table s'y pose de même, sans classe à ajouter : des rangs et des intertitres blancs, et dans une util-table-even un rang sur deux au fond de la feuille, l'intertitre y perdant le sien comme tr.tr-title. Un en-tête collant prend le fond de la feuille, sans arrondi, et une colonne fixe aussi dans l'en-tête. Les filets de util-table-bordered et de util-table-break ne changent pas, et un fond qu'un écran pose sur un rang, une cellule ou un en-tête reste le sien. La feuille est util-paper, ou le contenu d'un écran de structure à toutes les largeurs. Hors de la feuille, dans un panneau, sur une page publique ou d'administration, un tableau n'a pas plus de carte : ses rangs se posent sur le fond de ce qui le porte, et un en-tête collant prend ce fond, que la page, la feuille, le panneau, un bloc ou une carte donnent à --background-body. Il s'écrit partout en Open Sans, interligne 1,6, la police des pages de structure, sauf sur une page qu'un réseau ou un site habille, où il prend la police choisie. Une table-unstyled garde sa propre forme. Le second tableau défile dans un cadre, pour montrer l'en-tête collant, l'intertitre et les bandes.").'</p>';

		$h .= '<div class="design-paper-frame">';
			$h .= '<div class="util-paper">';

				$h .= \main\DesignUi::getPageHeader(
					s("Titre de l'écran"),
					context: s("Ce qui situe l'écran")
				);

				$h .= new \main\DesignUi()->getStart(
					\Asset::icon('star-fill'),
					s("Titre de la fonctionnalité"),
					'<p>'.s("Texte d'introduction, en une ou deux phrases, avec <link>un lien vers la suite</link>.", ['link' => '<a href="#">']).'</p>',
					buttons: '<div class="flex-buttons"><a href="#" class="btn btn-primary btn-xl">'.s("Commencer").'</a><a href="#" class="btn btn-outline-primary btn-xl">'.s("En savoir plus").'</a></div>'
				);

				$h .= $this->getStartStocks();

				$h .= '<table class="tr-even">';
					$h .= '<thead><tr><th>'.s("Produit").'</th><th>'.s("Unité").'</th><th class="text-end">'.s("Prix").'</th></tr></thead>';
					$h .= '<tbody>';
						foreach([s("Légumes") => [[s("Carotte botte"), s("botte"), '2,40 €'], [s("Chou vert"), s("pièce"), '3,10 €'], [s("Poireau"), s("kg"), '3,80 €']], s("Fruits") => [[s("Pomme"), s("kg"), '3,20 €'], [s("Poire"), s("kg"), '3,60 €']]] as $group => $rows) {
							$h .= '<tr class="tr-title"><td colspan="3">'.$group.'</td></tr>';
							foreach($rows as [$product, $unit, $price]) {
								$h .= '<tr><td>'.$product.'</td><td>'.$unit.'</td><td class="text-end">'.$price.'</td></tr>';
							}
						}
					$h .= '</tbody>';
				$h .= '</table>';

				$h .= '<div class="util-table util-table-bordered" style="grid-template-columns: fit-content(30%) minmax(0, 1fr) auto">';
					$h .= '<div class="util-table-head"><div></div><div>'.s("Intervention").'</div><div class="text-end">'.s("Temps passé").'</div></div>';
					foreach([[s("Accueil"), s("Visite du lycée agricole, puis rangement de la serre"), '1h30'], [s("Semis"), s("Épinard d'automne"), '0h45'], ['', s("Laitue"), '0h30'], ['', s("Oignon primeur"), '1h00'], [s("Récolte"), s("Carotte botte"), '2h00']] as $position => [$action, $name, $time]) {
						$h .= '<div class="util-table-row'.(($action !== '' and $position > 0) ? ' util-table-break' : '').'"><div class="color-muted">'.$action.'</div><div>'.$name.'</div><div class="text-end">'.$time.'</div></div>';
					}
				$h .= '</div>';

				$h .= '<div class="design-sticky-frame">';
					$h .= '<div class="util-table util-table-sticky" style="grid-template-columns: auto minmax(0, 1fr) auto">';
						$h .= '<div class="util-table-head"><div>'.s("Jour").'</div><div>'.s("Intervention").'</div><div class="text-end">'.s("Temps passé").'</div></div>';
						$h .= '<div class="util-table-title">'.s("Semaine 42").'</div>';
						foreach([[s("Lundi"), s("Semis des épinards"), '0h45'], [s("Lundi"), s("Arrosage de la serre"), '0h30'], [s("Mardi"), s("Plantation des laitues"), '2h15'], [s("Mardi"), s("Désherbage des carottes"), '1h30'], [s("Mercredi"), s("Récolte des poireaux"), '1h00'], [s("Mercredi"), s("Préparation des paniers"), '2h00'], [s("Jeudi"), s("Binage des oignons"), '1h15'], [s("Jeudi"), s("Paillage des courges"), '0h45'], [s("Vendredi"), s("Récolte des carottes"), '1h30'], [s("Vendredi"), s("Livraison des paniers"), '2h30']] as [$day, $name, $time]) {
							$h .= '<div class="util-table-row"><div class="color-muted">'.$day.'</div><div>'.$name.'</div><div class="text-end">'.$time.'</div></div>';
						}
					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La bande à trois gains, montrée deux fois : hors de la feuille sous la fiche util-start, et sur la
	 * feuille, où elle prend la couleur de l'univers.
	 */
	private function getStartStocks(): string {

		return new \main\DesignUi()->getStart(
			\Asset::icon('box-seam'),
			s("Le suivi des stocks"),
			'<p>'.s("Suivez un premier produit pour voir ce qu'il vous reste à vendre.").'</p>'.
			\main\DesignUi::getStartGains([
				['list-check', s("Le stock de chaque produit"), s("mis à jour à chaque vente et à chaque récolte")],
				['bell', s("Les produits bientôt épuisés"), s("sous le seuil que vous fixez")],
				['clock-history', s("L'historique des mouvements"), s("avec la date, la quantité et l'origine")],
			]),
			image: \Asset::image('main', 'friends/escargot.svg'),
			buttons: '<a href="#" class="btn btn-primary btn-xl">'.s("Suivre un produit").'</a>'
		);

	}

	/**
	 * Le titre de l'index, que portent son entrée du sommaire et sa tête.
	 */
	private function getFinderTitle(): string {
		return s("Trouver un composant");
	}

	/**
	 * L'entrée par le besoin. La page se lisait par composant : il fallait connaître le nom
	 * pour trouver la section, et deux sessions du même chantier ont réécrit un tableau en
	 * `<table>` + `<th>` faute d'avoir cherché « grille » (2026-09-10). Chaque ligne dit ce qu'on
	 * veut faire, la classe qui le fait, et mène à sa section. Les lignes se rangent sous la clé
	 * de la famille qui porte cette section, dans l'ordre du sommaire.
	 */
	public function finder(): string {

		$h = '<p class="util-annotation">'.s("Par ce qu'on veut obtenir, et non par le nom d'une classe. Une ligne qui manque est un composant à ajouter ici, pas à réécrire dans un package. Un besoin que l'index ne sert pas se déclare dans .claude/design/besoins.md, avec l'écran et la largeur, avant d'écrire une classe de package.").'</p>';

		$rows = [
			'fondations' => [
				[s("Adapter les composants du socle au thème d'un package, sans réécrire leurs règles"), '--callout-background · --marker-radius', 'themeTokens'],
				[s("Une marge, un remplissage, un écart entre deux blocs ou deux éléments d'une rangée"), 'var(--space-md)', 'spaces'],
				[s("Dire de quel univers vient un bloc ou une ligne"), 'util-domain · util-domain-inline', 'domains'],
				[s("Nommer une offre dans une phrase, avec son ami"), 'util-friend-inline', 'iconDiscs'],
				[s("Le logo d'une structure dans une pastille de taille fixe, et rien quand elle n'en a pas"), 'util-icon-logo', 'iconDiscs'],
				[s("Poser le logo d'un service dans une ligne de texte, à sa hauteur"), 'util-logo · util-logo-md · util-logo-lg', 'iconDiscs'],
			],
			'cadre' => [
				[s("Monter un écran entier, une liste, la fiche d'un objet enfant, une mise en route ou un formulaire, avec ses règles"), 'DesignUi::getPageHeader() + util-table · util-record · onboarding · FormUi', 'templates'],
				[s("Séparer une bande du fond de page par un coup de pinceau"), 'DesignUi::getBrushSeparator() + util-brushed + util-separator-top · util-separator-bottom', 'brushed'],
				[s("Poser un contenu sur la feuille des écrans d'une structure, hors de leur gabarit"), 'util-paper', 'paper'],
				[s("Ce que devient une util-table sur la feuille d'un écran, et hors d'elle"), 'util-table', 'paper'],
				[s("Un tableau de bord, des colonnes titrées côte à côte sous un titre, séparées par un filet"), 'util-board · util-board-columns · util-board-column', 'board'],
				[s("Des blocs titrés empilés dans une colonne, séparés par un filet horizontal"), 'util-board-columns + util-board-columns-1', 'board'],
				[s("Des rangs, un libellé à gauche et sa valeur à droite, sans le châssis d'un tableau"), 'util-rows · util-row', 'board'],
				[s("Un contenu et son résumé qui reste en vue à droite"), 'util-layout-aside', 'aside'],
				[s("Un panier, ses lignes et son total, avec sa barre en bas d'écran sur mobile"), 'util-basket-row · util-basket-bar', 'aside'],
				[s("Un récapitulatif hors panier, ses lignes, sa tête et son total"), 'util-basket-head · util-basket-row · util-basket-icon · util-basket-total', 'aside'],
				[s("L'aperçu d'un document déposé, PDF ou photo, à côté de ce qu'on en fait"), 'util-preview · util-preview-pdf · util-preview-photo · util-preview-erased', 'previews'],
				[s("Les pages d'un document en vignettes, à retirer une à une ou à ouvrir en grand"), 'util-previews · util-previews-large · util-previews-item · util-previews-remove · util-previews-selected · util-previews-add', 'previews'],
				[s("Une bande sans fond, qui porte l'en-tête d'un écran"), 'util-page-band', 'pageHeader'],
				[s("Revenir à la page parente, par un disque devant le titre"), 'util-page-header-back', 'pageHeader'],
				[s("Passer à une page voisine, par un disque à chevron après le titre"), 'DesignUi::getPages() · DesignUi::getPagesFromCategories() + util-page-header-title · util-page-header-pages', 'pageHeader'],
				[s("Un titre de section et son action à droite"), 'util-title · util-action', 'actionBars'],
				[s("Des boutons côte à côte, en tête d'écran ou sous un bloc, qui passent à la ligne quand la place manque"), 'flex-buttons', 'actionBars'],
				[s("Un titre de page long, avec ses gestes à droite de sa première ligne"), 'util-action-fold · util-action-fold-aside', 'actionBars'],
				[s("Un en-tête d'écran, avec son titre, ce qui le situe, ses gestes et son logo, à prendre plutôt que util-action-fold"), 'DesignUi::getPageHeader() + util-page-header', 'pageHeader'],
				[s("Un sous-titre plus discret qu'un titre de section"), 'util-subtitle', 'actionBars'],
				[s("Un encadré optionnel, à côté du chemin principal"), 'util-block-optional', 'contentBlocks'],
				[s("Une section qu'on replie par son titre"), 'util-fold · util-fold-head · util-fold-content', 'collapsible'],
				[s("Plusieurs chiffres dans une place étroite, un à la fois, qu'on fait glisser"), 'util-carousel · util-carousel-track · util-carousel-slide · util-carousel-dots', 'carousel'],
				[s("Des cartes en une seule rangée qu'on fait défiler, plusieurs à la fois au bureau"), 'util-carousel util-carousel-cards', 'carousel'],
			],
			'objet' => [
				[s("Des cartes en grille, une par élément"), 'util-cards + util-card', 'cards'],
				[s("Des cartes en grille dont le nombre par rang suit la place"), 'util-cards-fill', 'cards'],
				[s("Des cartes étroites en nombre, deux par rang au téléphone"), 'util-cards-small', 'cards'],
				[s("Une carte qui s'ouvre d'un clic n'importe où sur elle"), 'util-card + util-card-link', 'cards'],
				[s("Un pied de carte aligné au bas d'un rang de cartes"), 'util-card-foot', 'cards'],
				[s("Des cartes de hauteurs inégales rangées en colonnes, sans trou"), 'util-piles', 'cards'],
				[s("Des colonnes en piles qui s'élargissent au-delà d'un nombre, réglé par une media query"), 'util-piles + --util-piles-max', 'cards'],
				[s("Des blocs en tuiles, chacun son intitulé, son chiffre, sa liste et ses liens"), 'util-tiles · util-tiles-1 · util-tile · util-tile-wide', 'tiles'],
				[s("Une grille de tuiles qui sont des liens, une image au-dessus de son libellé"), 'util-tile-link · .selected', 'tiles'],
				[s("Une tuile qui dit son univers par sa tête"), 'util-tile-head + util-domain-*', 'tiles'],
				[s("Des cartes ou des notes rangées dans un bloc sans y faire une boîte dans la boîte"), 'util-flat', 'tiles'],
				[s("Des rangs dont le libellé se coupe d'une ellipse pour que la valeur reste sur sa ligne"), 'util-rows + util-rows-truncate', 'tiles'],
				[s("Une vignette et son texte, alignés"), 'util-vignette', 'vignettes'],
				[s("Une carte dont le liseré dit l'état"), 'DesignUi::getAccentCard() + util-card-accent-*', 'cardsAccent'],
				[s("Une étape qui reste à faire, sans alarmer"), 'util-card-accent-todo', 'cardsAccent'],
				[s("Une action que la page demande, son accroche et son bouton"), 'util-card-action · util-card-action-info|warning|danger', 'actionCards'],
				[s("Un contenu qui touche les bords d'une carte (carte interactive, image)"), 'util-card-bleed', 'cards'],
				[s("Un décompte posé dans le coin d'une carte"), 'util-card-count', 'cards'],
				[s("Un texte que l'utilisateur s'est écrit, un pense-bête, des notes ou un commentaire interne"), 'util-note + util-note-pin', 'cards'],
				[s("Un pense-bête qui tient la largeur de sa colonne, aligné sur les blocs voisins"), 'util-note + util-note-wide', 'cards'],
				[s("Des marques posées sur l'image d'une carte (type, auteur), sur un rang ou le nom entier"), 'util-card-badges · util-card-badges-wrap', 'cards'],
				[s("Le lien qui clôt un bloc, en texte suivi d'une flèche, et plusieurs dans un même pied"), 'util-more · util-mores', 'tiles'],
				[s("Le même lien en plus grand, lien principal d'une fiche qui mène à l'objet entier"), 'util-more-lg', 'tiles'],
				[s("Des libellés et leurs valeurs en regard, sur une, deux ou trois colonnes"), 'util-presentation · util-presentation-1|2|3', 'presentationDetail'],
				[s("Le complément d'une valeur, à sa suite, sans le gras de la valeur"), 'util-presentation-detail', 'presentationDetail'],
				[s("Une valeur qui prend le rang entier, des rangs serrés"), 'util-presentation-fill · util-presentation-max-content', 'presentations'],
				[s("Un détail qui se déplie"), 'DesignUi::getDeckToggle() + util-deck-fold', 'decks'],
				[s("Un objet d'une liste, son état et ses chiffres, en carte"), 'DesignUi::getDeckHead() + util-deck', 'decks'],
				[s("Un objet seul et tout ce qui s'y rattache, chaque information liée avec son geste"), 'util-record + util-record-branches', 'records'],
				[s("Un objet et ses gestes à gauche, ses informations liées à droite"), 'util-record + util-record-stub', 'records'],
				[s("La tête d'un objet sur le fond de son univers"), 'util-record-tinted', 'records'],
				[s("La tête d'un panneau qui ouvre la fiche d'un objet, teintée, sa croix au bout"), 'util-panel-tinted', 'records'],
				[s("Une information d'une fiche, son libellé, sa valeur et son geste"), 'DesignUi::getRecordItem() + util-record-item', 'records'],
				[s("Un objet qui a plusieurs destinations"), 'util-deck-band-doors', 'decks'],
				[s("Des chiffres d'un deck qui filtrent la liste"), 'util-deck-value', 'decks'],
				[s("Des portes aux libellés courts, sur la ligne du titre"), 'util-deck-doors', 'decks'],
				[s("Ce qui reste à faire sur un objet, à droite de son titre"), 'util-deck-aside', 'decks'],
				[s("Un réglage qui s'allume, l'interrupteur à droite"), 'util-deck-aside', 'decks'],
				[s("Les gestes sur un objet, dans une roue à droite"), 'util-deck-aside · dropdown-toggle', 'decks'],
				[s("La place du prochain objet au bout d'une suite de cartes, qui ouvre sa création"), 'btn-secondary-light + util-deck-add', 'decks'],
				[s("Une entrée de liste qui ouvre un choix au lieu d'une page"), 'DesignUi::getDeckDropdown()', 'decks'],
				[s("Les couleurs d'un thème, avant la flèche"), 'util-deck-colors', 'decks'],
				[s("Un objet au texte long, en grand"), 'util-deck-lg', 'decks'],
				[s("Des objets côte à côte dont les titres s'alignent en haut"), 'util-deck-top', 'decks'],
				[s("Ce qui décide d'un objet, en colonne de même largeur d'une carte à l'autre"), 'util-deck-aside-list + --deck-aside-width', 'decks'],
				[s("Une carte sélectionnée, le choix retenu parmi des decks qui filtrent une liste"), 'util-deck-selected', 'decks'],
				[s("Des objets à cocher pour une action groupée"), 'util-deck-aside · util-checkbox', 'decks'],
				[s("Des lignes à cocher dans un groupe"), 'util-deck-title · util-deck-row > util-checkbox', 'decks'],
				[s("Les parties d'un objet déplié, en onglets sous sa tête"), 'util-deck-tabs · util-deck-tabs-folder · util-deck-tab', 'decks'],
				[s("Un objet déplié à la place de sa ligne, dans son groupe"), 'util-deck-slot', 'decks'],
				[s("Un tableau qui touche les bords d'un étage"), 'util-deck-bleed', 'decks'],
				[s("Un dépliable dont la tête porte aussi des gestes"), 'a.util-deck-body · a.util-deck-chevron', 'decks'],
				[s("Une ligne d'un détail qui mène à un écran"), 'a.util-deck-row', 'decks'],
				[s("Une ligne d'un objet qui dit par son fond qu'elle est faite"), 'util-deck-row-state', 'decks'],
				[s("Une ligne ou un regroupement pas encore vu"), 'util-deck-row-unread · util-deck-unread · util-deck-row-read · util-deck-read', 'decks'],
				[s("Distinguer ce qui vient d'arriver de ce qui reste à ouvrir"), 'util-icon-dot · util-deck-row-pending · util-deck-pending', 'decks'],
				[s("Des noms à la suite, chacun derrière son image, qui passent à la ligne"), 'util-vignettes', 'vignettes'],
				[s("Une ligne qui court sur plusieurs lignes, rangée en haut"), 'util-deck-row-top', 'decks'],
				[s("Un fil de messages signés, avec les gestes de chacun"), 'util-thread · util-thread-side · util-deck-row-top', 'decks'],
				[s("Le chiffre en grand d'une tuile ou d'une bande, dans l'encre de son univers"), 'util-tile-figure', 'tiles'],
			],
			'etat' => [
				[s("Une marque d'état dans un tableau, sur fond clair"), 'badge + badge-danger', 'marks'],
				[s("Une marque pleine, sur un titre ou une carte"), 'badge + badge-solid', 'marks'],
				[s("Un état qui n'est pas encore atteint"), 'badge-outline · badge-off', 'marks'],
				[s("Un état qu'on peut changer"), 'btn btn-badge + dropdown-toggle', 'marks'],
				[s("Une marque qui est aussi un bouton"), 'btn + btn-badge', 'marks'],
				[s("Une référence, un code qu'on lit caractère par caractère"), 'badge-reference', 'marks'],
				[s("Un temps passé sur une intervention, à ne pas confondre avec un temps déclaré"), 'badge-time-spent', 'marks'],
				[s("Qualifier un objet d'un mot"), 'badge + badge-production', 'marks'],
				[s("Un compteur"), 'util-counter · util-counter-solid · util-counter-danger', 'counters'],
				[s("Une ligne conforme, à surveiller ou en défaut, dite par une icône colorée"), 'color-success · color-warning · color-danger + icône', 'status'],
				[s("Un cachet en marge d'une ligne, pour ce qui est nouveau"), 'util-stamp · util-stamp-new', 'stamps'],
				[s("Un titre de colonne frappé comme un tampon"), 'util-title-stamp', 'stamps'],
				[s("Une valeur qui vient d'un autre élément et le suit"), 'util-follow', 'follows'],
				[s("Une case d'un formulaire officiel qu'aucun calcul ne remplit, ou un montant que la règle ne calcule pas encore"), 'util-not-auto', 'notAuto'],
				[s("La fiabilité d'une valeur, et la légende de ses niveaux"), 'util-confidence · util-confidence-levels', 'reliability'],
			],
			'vide' => [
				[s("Accueillir sur un onglet ou un écran encore vide, de façon engageante"), 'DesignUi::getWelcome() + util-welcome', 'welcome'],
				[s("Ouvrir un écran sur ce qui le gouverne, souvent une fonctionnalité à démarrer"), 'util-start', 'start'],
				[s("L'écran vide d'une fonctionnalité jamais nourrie, avec ce qu'on y trouvera"), 'util-start + util-start-gains', 'start'],
				[s("Une liste vide qui appelle un geste (créer, importer)"), 'DesignUi::getBlockEmpty() + util-block-cta', 'cta'],
				[s("Une liste, un panneau ou une recherche entièrement vides, avec un titre et une phrase"), 'DesignUi::getBlockEmpty() + util-block-empty', 'cta'],
				[s("Un constat discret dans un bloc qui a déjà son titre, comme « Aucune récolte » dans une carte"), 'util-empty', 'empty'],
				[s("Une valeur absente, au milieu d'un texte ou d'une cellule"), 'util-empty', 'empty'],
			],
			'informer' => [
				[s("Un rond de la couleur d'un objet, devant son nom"), 'util-dot', 'dots'],
				[s("Un commentaire saisi sur plusieurs lignes, échappé, ses retours à la ligne gardés"), 'TextUi::multiline()', 'texts'],
				[s("Une quantité et son unité qui ne se séparent pas, assemblées par le code"), 'TextUi::nonBreaking()', 'texts'],
				[s("Une cellule dont la valeur n'a pas de sens sur sa ligne"), 'TextUi::none()', 'texts'],
				[s("Quelques mots à faire ressortir dans un texte"), 'util-mark', 'highlight'],
				[s("Une date ou un prix annulés, barrés, qui restent lisibles"), 'util-strikethrough', 'inline'],
				[s("Dire qu'une action a eu lieu, et laisser quelques secondes pour l'annuler"), 'util-toast', 'flash'],
				[s("Un fait que le produit garantit, dans un encadré"), 'util-block-info', 'tintedBlocks'],
				[s("Une note sous un champ ou une section"), 'util-info', 'messages'],
				[s("Une mention secondaire en petit, comme la source d'une carte ou un conseil en pied de formulaire"), 'util-helper', 'inline'],
				[s("Une précision de plusieurs lignes, comme la présentation d'une personne"), 'util-annotation-long', 'inline'],
				[s("Une icône dans un disque plein, devant un titre"), 'util-circle · util-circle-lg', 'inline'],
				[s("Une valeur et son unité qui ne se séparent pas au retour à la ligne"), 'util-unit', 'inline'],
				[s("Une réussite, un avertissement, un échec, en une ligne"), 'util-success · util-warning · util-danger', 'messages'],
				[s("Une réussite, un avertissement, un échec, avec un titre"), 'util-block-success · util-block-warning · util-block-danger', 'tintedBlocks'],
				[s("Un surtitre en petites capitales"), 'util-label', 'overline'],
				[s("Une valeur à taper ou à lire telle quelle, au fil d'une phrase ou dans une cellule"), 'util-literal', 'typedValue'],
				[s("Une bulle au survol"), 'util-tip', 'tips'],
				[s("Une bulle qui dit une période et ses valeurs côte à côte"), 'util-tip-head · util-tip-figures · util-tip-value · util-tip-label', 'tips'],
			],
			'agir' => [
				[s("Agir sur plusieurs lignes cochées"), 'batch', 'batch'],
				[s("Copier un lien, une adresse ou un code dans le presse-papiers"), 'TextUi::copy()', 'copy'],
				[s("Une valeur retenue parmi des pastilles, sans en faire un plein"), 'btn-outline-primary + btn-chosen', 'buttonsUsage'],
				[s("Cocher d'un clic une ligne d'une liste de choses à faire"), 'util-tick · util-tick-line', 'ticks'],
				[s("Dire ce qu'une action change, ce qui reste et si elle s'annule, avant de la faire"), 'DesignUi::getWeigh() + util-weigh', 'weigh'],
				[s("Le bouton d'un geste pesé, sans champ entre le bloc et lui"), 'DesignUi::getWeigh() + $action', 'weigh'],
				[s("Un formulaire dont le bouton doit être pesé"), 'DesignUi::getWeigh() + submit sous les champs', 'weigh'],
				[s("Attirer l'attention sur une conséquence d'une pesée qui s'écarte de ce qu'on attend"), "DesignUi::getWeigh() + 'alert' => TRUE (util-weigh-alert)", 'weigh'],
				[s("Une conséquence d'une pesée qui ne vaut que pour l'un des choix du formulaire"), "DesignUi::getWeigh() + 'attributes' de la conséquence", 'weigh'],
				[s("Supprimer quelque chose qui ne se récupère pas"), 'DesignUi::getDeletePanel() + util-weigh-danger', 'weigh'],
				[s("Confirmer un geste qui part chez d'autres, sans rien détruire"), 'DesignUi::getConfirmPanel()', 'confirmPanel'],
				[s("Un menu d'actions, avec une entrée non autorisée qui garde sa place"), 'dropdown', 'dropdowns'],
				[s("La raison d'une entrée de menu désactivée, sous son libellé"), 'util-item-label + util-item-more', 'dropdowns'],
				[s("Choisir un mode d'affichage parmi plusieurs, chacun avec son aperçu, et ses options dessous"), 'util-soil-menu · util-soil-mode · util-soil-switch', 'displayMenu'],
				[s("Un menu clair qui résume les lignes d'un écran, entrées non lues et regroupées"), 'dropdown-list-light · dropdown-item-unread · dropdown-item-pending · dropdown-item-group', 'dropdowns'],
				[s("Choisir la couleur et le type d'un bouton, du noir à l'univers de l'écran, du plein au contour"), 'btn-*', 'buttonsUsage'],
				[s("Choisir entre un bouton plein et un bouton en contour"), 'btn-primary · btn-outline-primary', 'buttonsUsage'],
				[s("Une action qui ne fait pas avancer la zone, Annuler compris, en contour"), 'btn-outline-primary', 'buttonsUsage'],
				[s("Un compteur sur un bouton, des boutons accolés, une icône au-dessus du libellé"), 'btn-status · btn-group · btn-icon', 'buttonModifiers'],
				[s("L'action qui fait avancer la zone, en noir ou dans l'univers de l'écran"), 'btn-primary · btn-secondary', 'buttonsUsage'],
				[s("Une action qui accompagne le plein, sans cerne, comme Aperçu ou Rattacher"), 'btn-primary-light · btn-secondary-light', 'buttonsUsage'],
				[s("Retirer ou déconnecter parmi d'autres pastilles"), 'btn-danger-light', 'buttonsUsage'],
				[s("Supprimer un élément sans perte pour autrui, sans rouge"), 'btn-outline-primary + data-confirm', 'buttonsUsage'],
				[s("Ouvrir un domaine depuis un écran qui n'appartient à aucun"), 'btn-production-light · btn-commercialisation-light · btn-accounting-light · btn-amap-light', 'buttonDomains'],
				[s("La roue des réglages d'une ligne ou d'un écran, un bouton blanc à filet"), 'btn-color-primary + dropdown-toggle', 'buttonsUsage'],
				[s("Un bouton qui ouvre un menu, avec sa flèche"), 'dropdown-toggle + data-dropdown', 'buttonsUsage'],
				[s("Les boutons d'une ligne de tableau, à la taille de leurs voisins"), 'btn-sm', 'buttonsUsage'],
				[s("Un bouton vert, orange ou rouge, au ton d'un bloc ou d'un panneau"), 'btn-success · btn-warning · btn-danger', 'buttonsUsage'],
				[s("Les boutons d'une tête d'écran, outils, création et roue"), 'DesignUi::getPageHeader() tools · create · settings', 'buttonsUsage'],
				[s("Un bouton sur une boutique ou un site public"), 'website-wrapper · body[data-template~="shop"]', 'buttonsUsage'],
				[s("Un bouton plus petit ou plus grand, à l'une des quatre hauteurs"), 'btn-xs · btn-sm · btn-lg', 'sizes'],
				[s("Un bouton qui n'a qu'une icône, carré"), 'btn-only-icon', 'sizes'],
				[s("Une icône seule à cliquer au doigt, qui fonce au survol, comme un chevron autour d'une pastille"), 'btn-only-icon + btn-bare', 'sizes'],
				[s("Un bouton désactivé, ou que le clavier atteint"), 'disabled · :focus-visible', 'buttonsFocus'],
				[s("Faire apparaître les cases d'une liste"), 'btn-outline-primary', 'buttonSelect'],
				[s("De grands choix d'entrée, illustrés"), 'util-buttons + util-button', 'entries'],
				[s("Déposer des fichiers en les glissant depuis l'ordinateur"), 'util-drop · util-drop-over', 'drop'],
				[s("Lister des fichiers déposés et conclure le dépôt"), 'util-drop-list · util-drop-foot', 'drop'],
				[s("Des boutons d'action centrés sous un titre, qui passent à la ligne"), 'util-buttons-center + btn', 'entries'],
				[s("Réordonner par glisser-déposer"), 'util-drag-handle', 'drag'],
				[s("Estomper pendant un glisser ce que l'élément saisi ne peut pas rejoindre"), 'util-drag-out', 'drag'],
				[s("Des réglages côte à côte, poignée, nom et interrupteur, qui passent à la ligne"), 'util-cards-fit + util-deck-sm · util-drag-handle · util-deck-aside', 'drag'],
				[s("L'appel principal d'une page publique et son second appel"), 'btn-terracota · btn-outline-terracota', 'buttonsUsage'],
			],
			'saisir' => [
				[s("Un champ de recherche ou un filtre qu'on vide d'un geste"), 'FormUi clearable · input-group-clear', 'clearable'],
				[s("Une valeur lue, alignée à droite, qu'on touche pour ouvrir son détail"), 'util-keypad-field', 'valueDetail'],
				[s("Un réglage binaire appliqué tout de suite"), 'TextUi::switch()', 'switches'],
				[s("Des champs qui forment une notion dans un formulaire"), 'FormUi::fieldset() + form-fieldset', 'fieldsets'],
				[s("Les champs communs d'un formulaire, réunis au-dessus du reste sous un titre posé sur le cadre"), 'form-fieldset + form-fieldset-notched', 'fieldsets'],
				[s("Des champs deux par ligne dans une bulle étroite"), 'util-fields · util-fields-wide', 'fieldsGrid'],
				[s("Un formulaire long, découpé en rubriques"), 'FormUi::cards() + util-cards + util-card', 'formChapters'],
				[s("Un choix fermé, avec la raison pour laquelle il l'est"), 'FormUi::radioOption() + field-radio-group-reason', 'radios'],
				[s("Un fichier à joindre d'un clic sur un bouton"), 'FormUi::fileButton()', 'fileButton'],
				[s("Un choix fermé qui se montre, en vignettes ou en pastilles de couleur"), 'FormUi::choices() · FormUi::colorChoices() + util-choices + util-choice', 'choices'],
				[s("Un choix fermé en échelle, comme une fréquence, en icônes"), 'FormUi::choices() + util-choice-icon', 'choices'],
				[s("Composer un objet en 3D, son aperçu en vue à gauche pendant que les choix défilent à droite"), 'util-composer + util-composer-preview · --util-composer-top', 'composer'],
				[s("Des pastilles de couleur et une pastille de plus sur une même rangée, qui passent ensemble à la ligne"), 'FormUi::colorChoices() + util-composer-tints', 'composer'],
				[s("Une question à laquelle on répond par oui ou par non, dans un formulaire"), 'FormUi::yesNo()', 'fields'],
				[s("Un champ qui ne paraît que si la réponse est oui"), 'field-toggle-wrapper + field-toggle', 'fields'],
				[s("Dire à quoi sert un champ ou ce que sa valeur entraîne, sous son libellé"), 'labelAfter + FormUi::info()', 'fieldInfo'],
				[s("Dire comment remplir un champ, sous le champ"), 'after + FormUi::info()', 'fieldInfo'],
				[s("Choisir un élément dans une liste longue, ou en créer un nouveau"), "field = 'autocomplete' + entrée 'type' => 'link'", 'autocomplete'],
				[s("Choisir plusieurs éléments dans une liste longue"), "field = 'autocomplete' + multiple = TRUE", 'autocomplete'],
				[s("Régler la recherche d'une liste longue sans la fermer, par des boutons en tête de liste"), 'autocompleteHead + autocomplete-head', 'autocomplete'],
				[s("Donner à la liste d'une autocomplétion la largeur de son groupe"), "autocompleteWidth = 'group'", 'autocomplete'],
				[s("Choisir sans quitter l'écran, au doigt"), 'util-sheet', 'sheet'],
				[s("Choisir sans quitter une page intégrée, qui n'a pas d'écran à elle"), 'util-sheet-inline', 'sheet'],
				[s("Cocher plusieurs dates d'un calendrier, rangées par mois"), 'util-dates + util-date', 'dateChecks'],
				[s("Donner aux cases d'un formulaire le dessin des options de filtre"), 'util-filter-options-field', 'formOptions'],
				[s("Une valeur qu'on modifie sur place, sans formulaire"), 'util-quick', 'quick'],
				[s("Une valeur qui devient champ dans sa cellule, le tableau restant visible"), 'util-quick-inline', 'quick'],
				[s("Écrire une valeur à sa place, avec la forme de ce qu'elle écrit"), 'util-inplace-field', 'quick'],
				[s("Des dates à cocher, groupées par mois"), 'util-dates + util-date', 'dateChecks'],
			],
			'chercher' => [
				[s("Filtrer une liste par un formulaire en tête"), 'util-block-search + util-search', 'searches'],
				[s("Filtrer une liste par une barre de jetons, la recherche en tête et un filtre par jeton"), 'DesignUi::getChipDropdown() + util-chips · util-chip · util-chips-sticky', 'chips'],
				[s("Chercher et filtrer depuis un seul champ, chaque critère retenu posé en jeton"), '\\main\\OmniboxUi::get() + util-omnibox', 'omnibox'],
				[s("Garder au doigt la liste d'une recherche sous son champ, sans plein écran"), '\\main\\OmniboxUi::get(fullscreen: FALSE)', 'omnibox'],
				[s("Ouvrir en plein écran un champ posé en bas, la liste au-dessus de lui"), '\\main\\OmniboxUi::get(fullscreenBottom: TRUE)', 'omnibox-bottom'],
				[s("Dire dans une liste de suggestions ce qui ne se choisit pas"), 'util-omnibox-notice', 'omnibox'],
				[s("Dire dans une liste de suggestions qu'il n'y a rien à proposer"), 'util-omnibox-notice-muted', 'omnibox'],
				[s("Choisir plusieurs éléments par une recherche, et les envoyer avec un formulaire"), '\\main\\OmniboxUi::get(name: …)', 'omnibox-name'],
				[s("Replier la recherche d'un en-tête en loupe, ses jetons sur une rangée sous le titre"), '\\main\\OmniboxUi::get(folded: TRUE)', 'omnibox-folded'],
				[s("Poser en puces les choix courts d'une famille de suggestions"), '\\main\\OmniboxUi::get(chips: …) · util-omnibox-family-chips', 'omnibox-chips'],
				[s("Garder un champ à jetons à sa largeur, les jetons entiers défilant sur une ligne"), '\\main\\OmniboxUi::get(scroll: TRUE) · util-omnibox-scroll', 'omnibox-scroll'],
				[s("Garder la croix d'effacement en vue au bout d'un champ dont les jetons défilent"), 'util-omnibox-track', 'omnibox-scroll'],
				[s("Régler l'affichage d'un écran par des réglages nommés, options et interrupteur"), 'util-filters + a.util-filter-label · TextUi::switch()', 'screenSettings'],
				[s("Dire ce qu'on regarde, et offrir d'en changer, au-dessus d'un titre"), 'util-scope-line', 'scope'],
				[s("Le même surtitre en petit, au-dessus d'un nom, à l'encre du fond où il se pose"), 'util-scope-line-title-sm + --scope-line-title-ink', 'scope'],
			],
			'lister' => [
				[s("Célébrer un moment qui n'arrive qu'une fois"), 'util-celebrate + util-seed-torn', 'seeds'],
				[s("Un tableau, une table, une grille dont les colonnes doivent se redistribuer sur petit écran"), 'util-table', 'grids'],
				[s("Un tableau classique, à colonnes fixes"), 'table', 'tables'],
				[s("L'intitulé d'une colonne hors d'un tableau"), 'util-table-header', 'grids'],
				[s("Un groupe de colonnes à distinguer de son voisin, de l'en-tête à la dernière ligne"), 'util-table-veil · util-table-groups', 'grids'],
				[s("Ajouter une ligne à une table depuis sa dernière ligne, et la saisir sur place"), 'util-table-add · util-table-add-entry', 'grids'],
				[s("Séparer des groupes de lignes sans intertitre, nommés par leur première ligne"), 'util-table-break', 'grids'],
				[s("Une tête et ses rangs dans une util-table, qui se lisent comme une seule bande"), 'util-table-group + util-table-group-sub', 'grids'],
				[s("Un rang fait, réglé, à vérifier ou à traiter, qui le dit par son fond"), 'util-table-row-state + util-deck-accent-*', 'listStates'],
				[s("Les états d'une liste, du rang au repos à la liste vide"), 'util-table-row-state · util-deck-row-state · util-block-empty · util-empty', 'listStates'],
				[s("Une tête et ses lignes filles, qui se lisent comme une seule bande"), 'tbody-group + tbody-group-sub', 'groups'],
				[s("Une tête et ses lignes filles qu'on replie, avec leur nombre"), 'tbody-group-fold · tbody-group-open · tbody-group-toggle', 'groups'],
				[s("Une échéance qui n'est pas encore échue, dans un tableau d'échéances"), 'tr-future', 'groups'],
				[s("Plusieurs lignes mises en évidence ensemble"), 'util-tr-group-hover · util-tr-group-selected', 'groups'],
				[s("Une colonne mise en évidence au survol, dans une matrice ou une grille"), 'util-column-hover', 'heat'],
				[s("Une matrice dont chaque cellule se colore selon sa valeur, pour repérer les fortes d'un coup d'oeil"), 'util-heat · util-heat-table · util-heat-strong', 'heat'],
				[s("Des jours ou des étapes côte à côte, un seul déplié, les autres réduits à une bande nommée"), 'util-bellows', 'bellows'],
				[s("Les colonnes d'un tableau dont une seule se déplie à la fois"), 'util-table + util-bellows-columns', 'bellows'],
				[s("Garder l'intitulé des colonnes en vue quand la liste défile"), 'thead-sticky · util-table-sticky', 'tables'],
				[s("Garder la première colonne en vue quand la table défile en largeur"), 'td-sticky-first · util-table-sticky-column', 'tables'],
				[s("Garder l'intitulé des colonnes en vue dans un tableau trop large pour l'écran"), 'util-overflow-frame + thead-sticky', 'tables'],
				[s("Faire défiler en largeur un tableau trop large, sous un seuil d'écran"), 'util-overflow-xs · util-overflow-sm · util-overflow-md · util-overflow-lg · util-overflow-xl', 'overflow'],
				[s("Une colonne serrée sur son contenu, une colonne de montants, une colonne mise en évidence"), 'td-min-content · text-end · t-highlight', 'tables'],
				[s("Paginer"), 'pagination', 'pagination'],
				[s("Une case à cocher dans une cellule, que toute la cellule coche"), 'util-checkbox · td-checkbox', 'grids'],
				[s("Choisir une offre parmi quelques-unes et la mettre au panier"), 'util-seeds + util-seed', 'seeds'],
				[s("Choisir parmi beaucoup d'offres sans illustration, alignées bande à bande"), 'util-seeds util-seeds-plain', 'seeds'],
				[s("Les mois qu'une période couvre, en entier ou en partie"), 'util-seed-calendar', 'seeds'],
				[s("Des groupes de lignes qui se déplient par un chevron"), 'util-seed-features · util-seed-features-toggle', 'seeds'],
				[s("Comparer quelques offres colonne par colonne"), 'util-table-seeds + util-seed-cap', 'seeds'],
				[s("Un geste qu'une offre ouvre, montré à qui ne l'a pas"), 'btn-offer-perched · util-stamp-friend · util-offer-preview · util-offer-footer', 'seeds'],
				[s("Une invitation à accepter, avec la case des conditions"), 'InviteUi::getCard()', 'seeds'],
				[s("Un fil compact, la date en tête de chaque message"), 'util-comment · util-comment-editable', 'comments'],
				[s("Des gestes de message qui ne paraissent qu'au survol"), 'util-comment-hover', 'comments'],
				[s("Un texte long coupé à trois lignes, tronqué avec un lien pour lire la suite"), 'util-text-clamp', 'comments'],
				[s("Un libellé venu d'ailleurs coupé à deux lignes, le texte entier en infobulle"), 'util-text-clamp-2', 'comments'],
				[s("Couper une adresse ou un mot sans espace pour qu'il tienne dans sa boîte"), 'text-break', 'comments'],
			],
			'naviguer' => [
				[s("Deux chemins exclusifs dans un formulaire"), 'tabs-form + tabs-exclusive', 'tabs'],
				[s("Les onglets de tête d'un écran, qui en ouvrent les vues"), '\\main\\DesignUi::getPageTabs() · tabs-page', 'tabs-page'],
				[s("Naviguer entre des vues d'un même objet"), 'DesignUi::getTabItem() + tabs', 'tabs'],
				[s("Des sous-onglets sous un onglet"), 'tabs-sub', 'tabs'],
				[s("Les pages d'un onglet en liste ouverte depuis lui, sans seconde rangée"), '\\structure\\StructureCommunityUi::getTabsRow() · tab-item data-dropdown data-dropdown-hover · tab-item-caret', 'tabs-community'],
				[s("Des onglets trop nombreux pour la largeur"), 'tabs-h-fold', 'tabsHorizontal'],
				[s("Des onglets repliés en menu quand ils dépassent la largeur"), '\\main\\DesignUi::getPageTabs(fold: TRUE)', 'tabs-page-fold'],
				[s("Le second libellé d'un onglet, sous son nom"), 'tab-item-detail', 'tabs-page-fold'],
				[s("La vignette d'un onglet, alignée même absente"), 'tab-item-vignette', 'tabs-page-fold'],
				[s("Le nom d'un onglet, seul à se couper quand l'onglet replié manque de place"), 'tab-item-name', 'tabs-page-fold'],
				[s("Des vues exclusives choisies sur leur description, en tuiles à icône, nom et seconde ligne"), 'tabs-tiles · tab-item-tile-icon', 'tabs-tiles'],
			],
			'chiffrer' => [
				[s("Les repères d'un axe de semaines ou de valeurs, qui s'éclaircissent quand la place manque"), 'util-graduation · util-graduation-mark', 'graduation'],
				[s("Comparer des quantités mois par mois, en petites barres"), 'util-bars · util-bars-light · util-bars-signed · util-bars-max · util-bars-axis', 'bars'],
				[s("Comparer chaque période à la même période de l'an dernier, en petites barres"), 'util-bars-before', 'bars'],
				[s("Comparer des modalités à une date, une barre chacune"), 'UtilChart.createComparison()', 'bars'],
				[s("Un tableau de chiffres et son graphique côte à côte"), 'analyze-chart-table · analyze-bar', 'bars'],
				[s("Écrire un montant ou un pourcentage"), 'TextUi::money() · TextUi::pc()', 'formats'],
				[s("Une étendue sur un axe gradué, avec ses plages et un repère"), 'util-rail', 'rail'],
				[s("Une frise de rails, une graduation en tête et un rail sans cadre par rang"), 'util-rail-trackless · util-rail-flat', 'rail'],
				[s("La clé des couleurs d'une grille ou d'un graphique"), 'util-legend · util-legend-item · util-legend-mark', 'legend'],
				[s("Une répartition en parts"), 'util-progress · util-ring', 'progress'],
				[s("Une répartition en parts, comparée à une valeur posée sur la barre"), 'util-progress-marked · util-progress-marker', 'progress'],
				[s("Des montants comparés sur une même échelle, chacun en parts, puis ce qui reste ou leur total"), 'util-share · util-share-row · util-share-total', 'progress'],
				[s("Une note sur cinq, lue ou saisie"), 'util-rating · util-rating-input', 'ratings'],
				[s("La répartition des avis par note, une ligne par note"), 'util-rating-distribution', 'ratings'],
				[s("Quelques chiffres clés en bandeau en tête d'écran, chacun pouvant ouvrir sa vue"), 'util-summarize', 'summarize'],
				[s("Un chiffre clé et sa variation"), 'util-figures · util-figure-variation · util-variation', 'figures'],
				[s("Un chiffre et l'objectif qu'il vise, avec le chemin fait vers lui"), 'util-figure-target · util-progress', 'figures'],
				[s("Des chiffres en ligne sous un titre, sans tuile, séparés par un filet"), 'util-figures-line · util-figures-line-item', 'figuresLine'],
				[s("Un chiffre dont l'intitulé se lit d'abord"), 'util-figure-label-top', 'figures'],
				[s("Une suite de chiffres qui défile au doigt, chaque tuile à la largeur de son contenu"), 'util-figures-overflow · util-figures-overflow-wrapper', 'figuresScroll'],
				[s("Un chiffre qui recule derrière ses voisins, un jour passé parmi les jours à venir"), 'util-figure-muted', 'figuresMuted'],
				[s("Marquer l'année affichée dans une rangée d'années qui ouvrent chacune la même analyse"), 'util-figure-selected', 'figuresSelected'],
				[s("Choisir l'indicateur que dessine le graphique sous une rangée de chiffres clés"), 'util-figure-selected', 'figuresSelected'],
				[s("Une valeur par région ou par département, sur la carte"), 'FranceUi::getMap()', 'maps'],
				[s("Une carte recadrée sur quelques départements"), 'util-map-cropped', 'maps'],
				[s("Basculer le fond d'une carte interactive"), 'util-basemap · util-basemap-image · util-basemap-name', 'maps'],
				[s("Les réglages d'une forme qu'on dessine sur une carte et ses issues, ou les gestes d'un contour désigné"), 'util-mapbar · util-mapbar-item · util-mapbar-primary · util-mapbar-labels · util-mapbar-name · util-mapbar-choice · util-mapbar-choice-icon · util-mapbar-choice-alone · util-mapbars · util-mapbars-start · util-mapbars-start-md', 'maps'],
				[s("Un point d'un dessin qu'on tire sur une carte, la croix qui le retire, et une longueur posée sur un côté"), 'util-maptool · util-maptool-unpoint · util-maptool-handle · util-maptool-say · util-maptool-say-detail · util-maptool-say-above · util-maptool-say-ok', 'maps'],
				[s("La forme désignée sur une carte, parmi d'autres contours"), '--map-selected · --map-selected-casing · --map-selected-ink', 'maps'],
				[s("Le contour d'un jardin sur une carte, de plein champ ou sous abri"), '--map-plot-line-open-field · --map-plot-line-greenhouse · --map-plot-casing', 'maps'],
			],
			'temps' => [
				[s("Un temps à faire avant les cartes d'une grille, à la place d'une carte"), 'util-cards + util-campaign-step', 'campaign'],
				[s("Un mois traité, à traiter, ou sans objet"), 'util-timeline', 'months'],
				[s("Une échéance qu'on repère avant de lire"), 'util-calendar', 'calendars'],
				[s("Des dates retenues sur un calendrier, mois par mois"), 'util-calendar-sheets · util-calendar-sheet · util-calendar-sheets-spaced', 'calendarSheets'],
				[s("Choisir un jour de la semaine avec son temps"), 'util-days · util-day', 'days'],
				[s("Choisir un jour de la semaine, ou aucun"), 'util-days + util-calendar-none', 'calendars'],
				[s("Choisir une semaine parmi quelques-unes, puis un jour de cette semaine"), 'util-week-picker · util-week-picker-week · util-week-picker-days', 'calendars'],
				[s("Griser un jour qui ne se choisit pas"), 'util-day is-disabled', 'days'],
				[s("Hachurer un jour d'absence"), 'util-day is-absent', 'days'],
				[s("Les semaines d'un mois, jour par jour"), 'util-days-month', 'days'],
				[s("Signaler un jour à vérifier"), 'util-day is-warning', 'days'],
				[s("Montrer qui est absent un jour, sous sa pastille"), 'util-day-absents · util-day-absents-more', 'days'],
				[s("Saisir la journée de chaque jour d'une semaine, de champ en champ, avec sa somme au titre"), 'util-days-entry · util-days-entry-total · util-days-entry-detail', 'days'],
				[s("La part d'une jauge qui reste à faire, hachurée"), 'util-progress-part-todo', 'days'],
			],
			'guider' => [
				[s("Guider en plusieurs étapes après la création"), 'onboarding', 'onboarding'],
				[s("Des étapes numérotées"), 'util-procedure · util-procedure-step', 'procedure'],
				[s("Des critères à vérifier, sans ordre"), 'util-procedure-check', 'criteriaChecks'],
				[s("Des étapes à icône, chacune avec son bloc"), 'util-procedure-icon + --marker-offset', 'iconSteps'],
				[s("Des dates à la suite sur un trait, la dernière à faire"), 'util-procedure-dots · util-procedure-step-open', 'chain'],
				[s("Un trajet en quelques étapes reliées par des flèches légendées"), 'util-chain · util-chain-step · util-chain-arrow', 'route'],
			],
			'animer' => [
				[s("La durée et la courbe d'un retour, d'une apparition, d'un déplacement"), 'var(--duration-sm) · var(--ease-out)', 'motion'],
				[s("Ce qui ne s'anime jamais, et le mouvement réduit"), 'prefers-reduced-motion', 'motion'],
				[s("Montrer qu'une écriture est passée, sur ce qu'elle a changé"), 'util-flash', 'feedback'],
				[s("Un bouton d'envoi qui attend la réponse du serveur"), 'util-pending', 'feedback'],
				[s("Un champ ou un bloc que le serveur recalcule, le temps de sa réponse"), 'data-waiting-block + util-waiting', 'feedback'],
				[s("Faire voir ce qu'une réponse retire"), 'util-vanish', 'vanish'],
			],
			'public' => [
				[s("Mettre des blocs les uns sous les autres, à un écart de l'échelle"), 'util-stack · util-stack-xs', 'publicAnswer'],
				[s("Une rangée de boutons centrée, sous un texte centré d'une page publique"), 'flex-buttons + flex-justify-center', 'publicBands'],
				[s("Un texte vert lisible sur une page publique, titre ou chiffre"), 'color-encre-accent', 'publicBands'],
				[s("Centrer le titre d'une bande et son chapô"), 'DesignUi::getBandHead() + util-band-head + util-band-lead', 'publicBands'],
				[s("Le corps d'une bande sur la largeur de lecture, centré sous sa tête"), 'util-band-body', 'publicBands'],
				[s("La bande qui suit le héros, remontée vers lui"), 'util-band-intro', 'publicBands'],
				[s("Des chiffres d'affiche sans tuile, au centre d'une bande"), 'util-figures-band', 'publicBands'],
				[s("Des rangs pastille, titre et texte, qui se lisent de haut en bas"), 'util-points + util-point · util-point-icon', 'publicBands'],
				[s("Une mascotte centrée au-dessus de ce qu'elle annonce"), 'util-band-mascot', 'publicBands'],
				[s("Une mascotte qui signe la fin d'un écran court"), 'util-signature-mascot', 'publicBands'],
				[s("La parole d'un utilisateur, sa photo, son nom, ce qu'il fait et ce qu'il dit"), 'util-testimony + util-testimony-photo · util-testimony-name · util-testimony-subtitle · util-testimony-text', 'publicBands'],
				[s("Quelques points courts dans le texte d'une carte, sous une coche"), 'util-check-list', 'publicBands'],
				[s("Ouvrir une page publique par une bande d'accueil, son titre, son chapô et ses chiffres"), 'util-page-hero · util-page-hero-inner · util-page-hero-top · util-page-hero-lead', 'publicHero'],
				[s("Une tuile d'icône blanche qui se détache d'un fond teinté"), 'util-icon-raised', 'publicHero'],
				[s("Des chiffres en ligne dans une carte blanche"), 'util-figures-line + util-figures-line-card', 'publicHero'],
				[s("La recherche qui fait le sujet d'une page publique, en pilule"), 'util-omnibox-hero', 'publicHero'],
				[s("Ouvrir un écran d'un groupe, d'un réseau ou d'une AMAP par une bande claire, sa vignette, son titre, ses gestes, ses chiffres, ses onglets et son filtre"), 'util-masthead · util-masthead-head · util-masthead-thumb · util-masthead-actions · util-masthead-bar · util-masthead-figures · util-masthead-foot · util-masthead-tabs · util-masthead-network · util-masthead-amap · util-masthead-logo', 'groupMasthead'],
				[s("Nommer le retour d'une tête, en surtitre au-dessus du titre"), 'util-page-header-back-label', 'groupMasthead'],
				[s("Des sections sur toute la largeur, une sur deux sur fond gris"), 'DesignUi::getBand() + util-band · util-band-alt', 'publicBands'],
				[s("Une section d'une page publique fermée au pinceau en haut et en bas"), 'DesignUi::getBrushedBand() + util-band + util-brushed', 'publicBands'],
				[s("Une colonne d'introduction ou de filtres à gauche, qui reste en vue"), 'util-layout-aside-start + --aside-width', 'publicBands'],
				[s("Teinter un bloc par la phase d'un parcours, ses marques, ses étapes et son filet compris"), 'util-tint-phase-1 · util-tint-phase-2 · util-tint-phase-3 · util-tint-phase-4 + badge-tint · util-procedure-tint · util-rule · util-rule-caption', 'publicBands'],
				[s("Des étapes numérotées à pastille plus grande, ou qui reprennent la suite d'une autre liste"), 'util-procedure + --marker-size · --marker-start', 'publicBands'],
				[s("Un bloc plus arrondi, avec ce qu'il contient en liste à icônes"), 'util-deck-round + util-deck-aside-list', 'publicBands'],
				[s("Une suite de lignes numérotées dans un bloc"), 'util-deck-row-numbered + util-deck-row-index', 'publicBands'],
				[s("Des filtres en colonne dans la colonne de côté, chacun avec son compte"), 'util-filter-options-column', 'publicAnswer'],
				[s("Une navigation de filtre à choix unique dans la colonne de côté, comme les entrées du menu"), 'util-filter-options-column + util-filter-options-nav', 'publicAnswer'],
				[s("Des groupes d'options sous un intitulé, dans une navigation de filtre"), 'util-filter-options-title', 'publicAnswer'],
				[s("Le menu d'une page de lecture, ou une navigation courte dont on compare les chiffres, qui reste en colonne à toutes les largeurs"), 'util-filter-options-menu', 'publicAnswer'],
				[s("Le sujet de la page, au-dessus de ses groupes, dans le menu d'une page de lecture"), 'util-filter-options-title-subject', 'publicAnswer'],
				[s("Un groupe d'entrées qu'on ne vient pas chercher, dans le menu d'une page de lecture"), 'util-filter-options-title-group', 'publicAnswer'],
				[s("Le corps des entrées d'une navigation de filtre, que ses intitulés suivent"), 'util-filter-options-nav + --filter-option-size', 'publicAnswer'],
				[s("Une marque et ce qu'elle situe, au-dessus du titre d'un bloc"), 'util-deck-kicker', 'publicAnswer'],
				[s("Deux colonnes inégales, le propos et ce qui l'accompagne"), 'util-split', 'publicAnswer'],
				[s("Le titre d'un bloc écrit comme les titres d'une page publique"), 'util-deck-title-heading', 'publicAnswer'],
				[s("Un lien vers un écran, avec son chemin dans le menu"), 'util-link-path', 'publicAnswer'],
				[s("Un bloc aux coins arrondis, plus aéré"), 'util-block-round', 'publicAnswer'],
				[s("Une partie de page à la couleur d'un réseau, ses filets, ses phases et sa bande d'accueil compris"), 'util-brand-scope + --color-brand', 'publicAnswer'],
			],
		];

		\Asset::css('design', 'designAi.css');

		$families = $this->families();

		$h .= '<div class="design-finder">';
			foreach($rows as $key => $lines) {
				$h .= '<div>';
					$h .= '<div class="util-label">'.$families[$key]['title'].'</div>';
					$h .= '<ul>';
					// Comme le sommaire et les liens d'une fiche, le lien fait défiler sans toucher à l'adresse (DesignSummary.go()).
					foreach($lines as [$need, $class, $anchor]) {
						$h .= '<li><a data-target="design-'.$anchor.'" onclick="DesignSummary.go(this)">'.$need.'</a> <code>'.$class.'</code></li>';
					}
					$h .= '</ul>';
				$h .= '</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Quatre écrans entiers, montés avec les composants de la page. Une session y trouvait chaque bloc
	 * et aucune forme d'écran : l'AMAP a refait lot par lot la liste, la fiche d'un objet enfant, la
	 * mise en route et le formulaire, et leurs règles ne s'apprenaient qu'en recette (retour de shisow
	 * du 2026-09-26, piste 1 de la problématique des retours d'interface). Un squelette dit
	 * l'assemblage, et ses annotations les règles que l'assemblage seul ne montre pas.
	 */
	public function templates(): string {

		$form = new \util\FormUi();

		$h = '<p>'.s("Quatre écrans montés avec les composants de cette page, à suivre avant d'en assembler un. Chaque bloc y est juste pris seul, et c'est l'assemblage qui se refusait en recette quand il était réinventé.").'</p>';

		$h .= '<h3 class="design-heading">'.s("Un écran de liste").'</h3>';
		$h .= '<p>'.s("La tête par \\main\\DesignUi::getPageHeader(), dont le titre est le nom de la rubrique, celui du menu qui y mène, et dont le nombre d'éléments va dans un util-counter, ou dans chaque onglet quand la liste en a. Le geste qui ajoute est le bouton plein à droite du titre, argument create, qui ouvre un panneau. Le réglage de l'affichage vit dans la roue, argument settings, jamais dans un formulaire posé sous la liste. Le filtre se pose en tête, dans util-block-search, et seulement quand la liste est assez longue pour qu'on y cherche. Les rangs sont une util-table quand ses colonnes doivent se redistribuer, des util-deck quand chaque élément porte un état et ses gestes. Le geste d'un rang est une roue au bout du rang, jamais un sélecteur dans une cellule. Une liste vide cède sa place à util-block-empty, et la pagination ferme la liste.").'</p>';

		$h .= '<div class="design-paper-frame">';
			$h .= '<div class="util-paper">';

				$h .= \main\DesignUi::getPageHeader(
					s("Clients").' <span class="util-counter">128</span>',
					create: '<a href="#design-templates" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Nouveau client").'</a>',
					settings: \main\DesignUi::getGear('<a href="#design-templates" class="dropdown-item">'.s("Colonnes affichées").'</a>')
				);

				$h .= '<div class="util-block-search">';
					$h .= $form->openAjax('#design-templates', ['method' => 'get', 'class' => 'util-search util-search-2']);
						$h .= '<fieldset><legend>'.s("Nom").'</legend>'.$form->text('design-template-name', '', ['placeholder' => s("Nom du client")]).'</fieldset>';
						$h .= '<fieldset><legend>'.s("Groupe").'</legend>'.$form->select('design-template-group', [s("Particuliers"), s("Professionnels")], NULL).'</fieldset>';
						$h .= '<div class="util-search-submit">'.$form->submit(s("Chercher")).'</div>';
					$h .= $form->close();
				$h .= '</div>';

				$h .= '<div class="util-table util-table-hover" style="grid-template-columns: minmax(0, 1fr) auto auto">';
					$h .= '<div class="util-table-head"><div>'.s("Client").'</div><div class="text-end">'.s("Ventes (€)").'</div><div></div></div>';
					foreach([[s("Claire Fontaine"), '1 240,50'], [s("Épicerie du Pont, Saint-Marcellin"), '3 812,00'], [s("Hugo Bernard"), '86,40']] as [$name, $amount]) {
						$h .= '<div class="util-table-row">';
							$h .= '<div><a href="#design-templates">'.$name.'</a></div>';
							$h .= '<div class="text-end">'.$amount.'</div>';
							$h .= '<div class="text-end">'.\main\DesignUi::getGear('<a href="#design-templates" class="dropdown-item">'.s("Modifier").'</a>', class: 'btn-sm').'</div>';
						$h .= '</div>';
					}
				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h3 class="design-heading">'.s("La fiche d'un objet enfant").'</h3>';
		$h .= '<p>'.s("Un objet qui n'existe que dans un autre, la distribution d'un contrat ou la variété d'une espèce, ramène toujours à son parent : getPageHeader() reçoit l'adresse du parent dans back, jamais NULL qui reviendrait à la page d'avant, d'où qu'on vienne, et la ligne de contexte nomme le parent. L'objet se présente en util-record, ses informations liées chacune avec son geste. Un geste dont la condition manque ne s'ouvre pas sur un refus. Il mène à ce qui manque, comme ici le lieu de la distribution. Le geste irréversible, supprimer, ne se pose pas dans la fiche. Il passe par la roue, puis par le panneau de \\main\\DesignUi::getDeletePanel(), qui pèse ce qui part et ce qui reste avant de demander la confirmation.").'</p>';

		$h .= '<div class="design-paper-frame">';
			$h .= '<div class="util-paper">';

				$h .= \main\DesignUi::getPageHeader(
					s("Distribution du mardi 13 octobre"),
					context: s("Contrat <link>Panier de légumes d'automne</link>", ['link' => '<a href="#design-templates">']),
					back: '#design-templates',
					settings: \main\DesignUi::getGear('<a href="#design-templates" class="dropdown-item">'.s("Modifier").'</a><a href="#design-templates" class="dropdown-item">'.s("Supprimer la distribution").'</a>')
				);

				$h .= '<article class="util-record util-record-branches">';
					$h .= '<div class="util-record-main">';
						$h .= '<div class="util-record-head">';
							$h .= \util\DateUi::calendar('2026-10-13');
							$h .= '<div class="util-record-identity">';
								$h .= '<h3 class="util-record-title">'.s("Mardi 13 octobre").' <span class="badge badge-info badge-dot">'.s("À préparer").'</span></h3>';
								$h .= '<div class="util-record-context">'.s("de 18:00 à 19:30").'</div>';
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<ul class="util-record-list">';
						$h .= \main\DesignUi::getRecordItem(s("Lieu"), '<span class="util-empty">'.s("Aucun lieu choisi").'</span>', '<a href="#design-templates" class="btn btn-sm btn-outline-primary">'.s("Choisir le lieu").'</a>', 'util-record-item-alert');
						$h .= \main\DesignUi::getRecordItem(s("Commandes"), s("Du mardi 6 octobre à 08:00 au lundi 12 octobre à 20:00"));
						$h .= \main\DesignUi::getRecordItem(s("Absences"), s("2 adhérents"));
					$h .= '</ul>';
				$h .= '</article>';

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h3 class="design-heading">'.s("Une mise en route").'</h3>';
		$h .= '<p>'.s("Le parcours qui configure une fonctionnalité se monte avec onboarding, présenté plus haut, et n'a pas de colonne à côté. Chez un producteur, il occupe l'écran à lui seul, sans tête d'écran ni onglets. Dans un réseau ou un groupe, il garde la bande et les onglets, et ce qu'il prépare se présente juste au-dessus avec util-start. Une étape pose une question, et son pied ne porte que le geste qui y répond, à droite, de taille normale. Passer l'étape va à gauche du pied, quitter le parcours entier dans son en-tête. Un parcours qui n'a rien à montrer à côté des questions prend onboarding-panel-plain. L'écran d'une fonctionnalité jamais nourrie qui n'appelle pas de parcours prend util-start avec ses gains, et non ce gabarit.").'</p>';

		\Asset::css('main', 'onboarding.css');

		$h .= '<div class="onboarding">';
			$h .= '<div class="onboarding-header">';
				$h .= '<p class="onboarding-header-text">'.s("Trois questions pour préparer vos premiers contrats.").'</p>';
				$h .= '<a href="#design-templates" class="onboarding-header-skip">'.s("Je préfère commencer sans").'</a>';
			$h .= '</div>';
			$h .= '<div class="util-card onboarding-panel onboarding-panel-plain">';
				$h .= '<div class="onboarding-steps">';
					$h .= '<div class="onboarding-progress">';
						$h .= '<div class="onboarding-stepper"><span class="is-current"></span><span></span><span></span></div>';
						$h .= '<div class="onboarding-progress-label">'.s("Étape 1 sur 3").'</div>';
					$h .= '</div>';
					$h .= '<section class="onboarding-step is-current">';
						$h .= '<h2>'.s("Où se font les distributions ?").'</h2>';
						$h .= $form->group(s("Lieu"), $form->text('design-template-place', '', ['placeholder' => s("Ex. : Salle des fêtes, place de la Mairie")]));
						$h .= '<div class="onboarding-footer">';
							$h .= '<div class="onboarding-footer-side"><a href="#design-templates" class="btn btn-outline-primary">'.s("Passer cette étape").'</a></div>';
							$h .= '<div class="onboarding-footer-main"><a href="#design-templates" class="btn btn-primary">'.s("Continuer").' '.\Asset::icon('arrow-right').'</a></div>';
						$h .= '</div>';
					$h .= '</section>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h3 class="design-heading">'.s("Un formulaire empilé").'</h3>';
		$h .= '<p>'.s("Dans un panneau étroit ou pour un champ long, le libellé passe au-dessus de son champ, sans form-columns. Un texte long tient toute la largeur. Des champs qui forment une notion vont dans FormUi::fieldset() avec form-fieldset, un booléen se rend en oui ou non par FormUi::yesNo(), la consigne de saisie s'écrit sous le champ et le cadre sous le libellé. Le bouton d'envoi est seul, plein, sous le dernier champ, par \$form->group(content: \$form->submit()). Un formulaire assez long pour qu'on s'y perde se découpe en rubriques par FormUi::cards(), et non en titres posés entre les champs.").'</p>';

		$h .= '<div class="util-block">';
			$h .= $form->openAjax('#design-templates');
				$h .= $form->group(s("Nom du contrat"), $form->text('design-template-contract', s("Panier de légumes d'automne")));
				$h .= $form->fieldset(s("Période"), $form->group(s("Première distribution"), $form->date('design-template-start', '2026-10-06')).$form->group(s("Dernière distribution"), $form->date('design-template-end', '2026-12-15')), ['class' => 'form-fieldset']);
				$h .= $form->group(s("Paiement en plusieurs fois"), $form->yesNo('design-template-installments', TRUE));
				$h .= $form->group(s("Présentation"), $form->textarea('design-template-description', s("Un panier de six à huit légumes de saison, récoltés la veille de la distribution.")));
				$h .= $form->group(content: $form->submit(s("Enregistrer")));
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les conventions de l'interface du skill `ui` qui portent sur un comportement ou sur une forme
	 * sans classe à elle. Chacune a son ancre `#convention-<nom>`, que la convention cite pour être
	 * tenue pour démontrée (`ConventionDemonstrationSentinelAi`), et se montre par ce qu'elle écarte
	 * à côté de ce qu'elle demande : sans le premier, le second ne se distingue de rien.
	 */
	public function conventions(): string {

		$form = new \util\FormUi();

		$pair = fn(string $avoid, string $do) => '<div class="design-pair">'.
				'<div><div class="util-label">'.s("À éviter").'</div>'.$avoid.'</div>'.
				'<div><div class="util-label">'.s("À faire").'</div>'.$do.'</div>'.
			'</div>';

		$h = '<h2>'.s("Conventions de l'interface").'</h2>';
		$h .= '<p class="util-annotation">'.s("Ce que l'utilisateur redresse à chaque fois qu'on l'oublie, montré par ce que la convention écarte à côté de ce qu'elle demande. Les conventions qui portent sur un composant sont montrées dans sa section.").'</p>';

		$h .= '<h3 class="mt-2">'.s("Une valeur figée est du texte").'</h3>';
		$h .= '<p class="util-annotation">'.s("Une valeur que personne ne peut changer sur cet écran se lit comme une valeur, pas comme un champ grisé dont on cherche comment le débloquer.").'</p>';
		$h .= '<div id="convention-valeur-figee">';
			$h .= $pair(
				'<div class="util-block">'.$form->group(s("Exercice comptable"), $form->text('design-convention-frozen', s("2026"), ['disabled' => TRUE])).'</div>',
				'<div class="util-block"><dl class="util-presentation util-presentation-1"><dt>'.s("Exercice comptable").'</dt><dd>'.s("2026").'</dd></dl></div>'
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Un réglage qu'on n'a pas le droit de changer reste un interrupteur, verrouillé").'</h3>';
		$h .= '<p class="util-annotation">'.s("TextUi::switch() avec disabled garde les deux libellés et la largeur, et la raison s'écrit sous lui par FormUi::info(). Ni texte, ni pastille, ni réglage masqué à qui voit l'écran.").'</p>';
		$h .= '<div id="convention-reglage-verrouille" class="util-block">';
			$h .= '<div class="design-convention-row design-convention-row-spread">';
				$h .= '<span>'.s("Boutique ouverte aux commandes").'</span>';
				$h .= \util\TextUi::switch(['disabled' => TRUE], TRUE, s("Ouverte"), s("Fermée"));
			$h .= '</div>';
			$h .= \util\FormUi::info(s("Seuls les administrateurs de la structure ouvrent et ferment la boutique."));
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Un état se marque par le fond").'</h3>';
		$h .= '<p class="util-annotation">'.s("La bordure qui apparaît sur le choix retenu élargit le jeton et pousse ses voisins. Le fond marque le même état sans rien déplacer.").'</p>';
		$h .= '<div id="convention-etat-fond">';
			$chips = fn(string $month) => '<div class="util-chips">'.
					'<a class="util-chip">'.s("Semaine").'</a>'.
					$month.
					'<a class="util-chip">'.s("Saison").'</a>'.
				'</div>';
			$h .= $pair(
				$chips('<a class="util-chip"><span class="design-convention-border">'.s("Mois").'</span></a>'),
				$chips('<a class="util-chip is-active">'.s("Mois").'</a>')
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Un geste qui insère ou ajoute est visible au repos").'</h3>';
		$h .= '<p class="util-annotation">'.s("Le « + » qui n'apparaît qu'au survol n'existe pas pour qui tient l'écran au doigt, ni pour qui ne passe pas la souris au bon endroit. Le survol peut l'accentuer, jamais le révéler.").'</p>';
		$h .= '<div id="convention-insertion-visible">';
			$rows = fn(string $class) => '<div class="util-block">'.
					'<div class="design-convention-row design-convention-row-spread"><b>'.s("Planche 1").'</b><a class="btn btn-sm btn-outline-primary '.$class.'">'.\Asset::icon('plus-circle').' '.s("Culture").'</a></div>'.
					'<div class="design-convention-row design-convention-row-spread"><b>'.s("Planche 2").'</b><a class="btn btn-sm btn-outline-primary '.$class.'">'.\Asset::icon('plus-circle').' '.s("Culture").'</a></div>'.
				'</div>';
			$h .= $pair($rows('design-convention-reveal'), $rows(''));
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Le gris atténué veut dire inactif").'</h3>';
		$h .= '<p class="util-annotation">'.s("Un lien atténué se lit comme éteint, et personne ne le clique. Le gris reste à ce qui ne réagit pas, comme une carte sans donnée.").'</p>';
		$h .= '<div id="convention-gris-inactif">';
			$h .= $pair(
				'<div class="util-block"><a href="#" class="color-muted">'.s("Voir les 12 ventes du jour").'</a></div>',
				'<div class="util-block"><a href="#">'.s("Voir les 12 ventes du jour").'</a><div class="color-muted">'.s("Aucune récolte cette semaine").'</div></div>'
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Une couleur dérivée se calcule depuis un token").'</h3>';
		$h .= '<p class="util-annotation">'.s("Une teinte plus claire ou plus foncée s'écrit en color-mix() à partir du token, jamais en hexadécimal recopié : elle suit le token quand il change, et le survol prend --primary.").'</p>';
		$h .= '<div id="convention-couleur-derivee" class="design-columns">';
			foreach([
				'design-convention-mix-100' => 'var(--primary)',
				'design-convention-mix-30' => 'color-mix(in srgb, var(--primary) 30%, white)',
				'design-convention-mix-10' => 'color-mix(in srgb, var(--primary) 10%, white)',
			] as $class => $formula) {
				$h .= '<div><div class="design-convention-swatch '.$class.'"></div><span class="mono">'.$formula.'</span></div>';
			}
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Pas de sélecteur de valeur dans une cellule de tableau").'</h3>';
		$h .= '<p class="util-annotation">'.s("Le geste d'une ligne est la roue de \\main\\DesignUi::getGear(), en btn-sm, au bout de la ligne, et les actions sur une sélection vont sous le tableau, pas dans une cellule qu'elles agrandiraient.").'</p>';
		$h .= '<div id="convention-tableau-sans-selecteur">';
			$gear = \main\DesignUi::getGear('<a class="dropdown-item">'.s("Modifier").'</a><a class="dropdown-item">'.s("Retirer").'</a>', class: 'btn-sm');
			$avoid = '<table><thead><tr><th>'.s("Produit").'</th><th>'.s("Statut").'</th></tr></thead><tbody>';
				foreach([s("Carotte botte"), s("Poireau")] as $position => $product) {
					$avoid .= '<tr><td>'.$product.'</td><td>'.$form->select('design-convention-status-'.$position, [s("En vente"), s("Retiré")], 0, ['required' => TRUE]).'</td></tr>';
				}
			$avoid .= '</tbody></table>';
			$do = '<table><thead><tr><th>'.s("Produit").'</th><th>'.s("Statut").'</th><th></th></tr></thead><tbody>';
				foreach([s("Carotte botte"), s("Poireau")] as $product) {
					$do .= '<tr><td>'.$product.'</td><td>'.s("En vente").'</td><td class="text-end">'.$gear.'</td></tr>';
				}
			$do .= '</tbody></table>';
			$do .= '<div class="flex-buttons"><a class="btn btn-outline-primary">'.s("Retirer la sélection de la vente").'</a></div>';
			$h .= $pair($avoid, $do);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Les boutons d'un panneau vont dans son corps, sous le dernier champ").'</h3>';
		$h .= '<p class="util-annotation">'.s("Un panneau court pose son bouton sous ses champs, par \$form->group(content: \$form->submit()). Le pied du panneau, séparé du formulaire par un vide, reste au panneau qu'on sait long.").'</p>';
		$h .= '<div id="convention-boutons-panneau" class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs">';
				$h .= $form->group(s("Nom"), $form->text('design-convention-panel-name', s("Marché du samedi")));
				$h .= $form->group(s("Lieu"), $form->text('design-convention-panel-place', s("Place du bourg")));
				$h .= $form->group(content: $form->submit(s("Enregistrer")));
			$h .= $form->close();
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Un titre ne se colle jamais au cadre qui le précède").'</h3>';
		$h .= '<p class="util-annotation">'.s("Les titres du dépôt n'ont pas de marge haute : un titre qui suit un bloc prend mt-2.").'</p>';
		$h .= '<div id="convention-titre-decolle">';
			$titled = fn(string $block, string $title) => '<div class="util-block '.$block.'">'.s("Livraisons de la semaine").'</div><h4 class="'.$title.'">'.s("Commandes en attente").'</h4><div class="util-block">'.s("3 commandes").'</div>';
			$h .= $pair($titled('mb-0', ''), $titled('', 'mt-2'));
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Aérer, sur tout l'écran").'</h3>';
		$h .= '<p class="util-annotation">'.s("Deux blocs voisins gardent l'écart du composant. Un écart se nomme par une classe du dépôt ou un pas de l'échelle, jamais par zéro ni par une marge inventée.").'</p>';
		$h .= '<div id="convention-aerer">';
			$blocks = fn(string $class) => '<div class="util-block '.$class.'">'.s("Récoltes").'</div><div class="util-block '.$class.'">'.s("Ventes").'</div><div class="flex-buttons"><a class="btn btn-outline-primary">'.s("Exporter").'</a></div>';
			$h .= $pair($blocks('mb-0'), $blocks(''));
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Aligner").'</h3>';
		$h .= '<p class="util-annotation">'.s("Un texte se centre verticalement sur ce qui l'accompagne dans sa rangée, l'icône, la vignette ou la case.").'</p>';
		$h .= '<div id="convention-aligner">';
			$line = fn(string $class) => '<div class="util-block"><div class="design-convention-row '.$class.'"><span class="util-circle util-circle-lg">'.\Asset::icon('basket3').'</span><span>'.s("Marché du samedi").'</span></div></div>';
			$h .= $pair($line('design-convention-row-top'), $line(''));
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Rien ne prend plus de place qu'il ne rapporte").'</h3>';
		$h .= '<p class="util-annotation">'.s("Le bandeau qui redit le nom de la structure et la coche qui confirme le geste qu'on vient de faire poussent la liste sous la ligne de flottaison sans rien apprendre.").'</p>';
		$h .= '<div id="convention-place">';
			$list = '<div class="util-block"><dl class="util-presentation util-presentation-1"><dt>'.s("Samedi").'</dt><dd>'.s("14 commandes").'</dd><dt>'.s("Mardi").'</dt><dd>'.s("9 commandes").'</dd></dl></div>';
			$h .= $pair(
				'<div class="util-block-info">'.s("Vous êtes sur l'espace du Verger des Combes.").'</div><div class="util-success">'.s("Le marché est bien enregistré.").'</div>'.$list,
				$list
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Le libellé et son champ sur une même ligne").'</h3>';
		$h .= '<p class="util-annotation">'.s("Quand la largeur le permet et que le formulaire a peu de champs, form-columns pose le libellé à gauche de son champ. Sous 576 px, le libellé repasse au-dessus.").'</p>';
		$h .= '<div id="convention-libelle-en-ligne" class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs">';
				$h .= $form->group(s("Prix de vente"), $form->inputGroup($form->number('design-convention-inline-price', '2.40').$form->addon('€')));
			$h .= $form->close();
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Un écran ne s'empile pas").'</h3>';
		$h .= '<p class="util-annotation">'.s("Trois lots ont chacun posé leur bloc à la suite du précédent, chacun juste pris seul. Redessinée, la même donnée entre dans une seule structure, rangée par rang.").'</p>';
		$h .= '<div id="convention-empilement">';
			$h .= $pair(
				'<div class="util-block-info">'.s("La boutique est ouverte jusqu'à jeudi midi.").'</div>'.
				'<div class="util-block">'.s("14 commandes reçues").'</div>'.
				'<div class="util-block-info">'.s("Retrait au marché du samedi.").'</div>',
				'<div class="util-block"><dl class="util-presentation util-presentation-1"><dt>'.s("Commandes").'</dt><dd>'.s("14 reçues, ouvertes jusqu'à jeudi midi").'</dd><dt>'.s("Retrait").'</dt><dd>'.s("Marché du samedi").'</dd></dl></div>'
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Le réglage d'un écran vit derrière une roue crantée à droite du titre").'</h3>';
		$h .= '<p class="util-annotation">'.s("La roue de \\main\\DesignUi::getPageHeader(), argument settings, plutôt qu'un formulaire de réglages empilé en bas de la page.").'</p>';
		$h .= '<div id="convention-roue-reglage">';
			$h .= \main\DesignUi::getPageHeader(
				s("Ventes"),
				settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Colonnes affichées").'</a><a href="#" class="dropdown-item">'.s("Tri par défaut").'</a>')
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Un logo réel ou rien").'</h3>';
		$h .= '<p class="util-annotation">'.s("Des initiales sur une pastille de couleur ressemblent à un logo que la structure n'a jamais choisi, et se confondent d'un écran à l'autre. Une structure qui a un logo le porte, une structure qui n'en a pas garde son nom seul. Le contre-exemple est util-icon-initials, que rend StructureUi::getInitials().").'</p>';
		$h .= '<div id="convention-logo-reel">';
			$vignettes = fn(string $missing) => '<div class="util-block"><div class="util-vignettes">'.
					'<span class="util-vignette"><div class="util-icon util-icon-round util-icon-sm util-icon-logo">'.self::getLogoDemo().'</div><span>'.s("Le Verger des Combes").'</span></span>'.
					'<span class="util-vignette">'.$missing.'<span>'.s("Le Fournil du chemin").'</span></span>'.
				'</div></div>';
			$h .= $pair($vignettes(self::getInitialsDemo(2, s("Le Fournil du chemin"))), $vignettes(''));
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Le gras marque un seul niveau").'</h3>';
		$h .= '<p class="util-annotation">'.s("L'espèce et pas la variété, le prix promotionnel et pas la ligne.").'</p>';
		$h .= '<div id="convention-gras">';
			$h .= $pair(
				'<div class="util-block"><b>'.s("Carotte").' '.s("Nantaise").'</b><br/><b>'.s("Tomate").' '.s("Cœur de bœuf").'</b></div>',
				'<div class="util-block"><b>'.s("Carotte").'</b> '.s("Nantaise").'<br/><b>'.s("Tomate").'</b> '.s("Cœur de bœuf").'</div>'
			);
		$h .= '</div>';

		$h .= '<h3 class="mt-2">'.s("Une cellule sans valeur").'</h3>';
		$h .= '<p class="util-annotation">'.s("Un tiret simple quand la notion ne s'applique pas à la ligne, une cellule vide quand elle s'appliquerait mais que personne ne l'a renseignée. Le poireau n'a pas de sous-groupe, le calibre de la carotte n'est pas saisi.").'</p>';
		$h .= '<div id="convention-cellule-vide">';
			$h .= '<table>';
				$h .= '<thead><tr><th>'.s("Produit").'</th><th>'.s("Sous-groupe").'</th><th>'.s("Calibre").'</th></tr></thead>';
				$h .= '<tbody>';
					$h .= '<tr><td>'.s("Carotte botte").'</td><td>'.s("Racines").'</td><td></td></tr>';
					$h .= '<tr><td>'.s("Poireau").'</td><td>-</td><td>'.s("Moyen").'</td></tr>';
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	public function criterionMarks(): string {

		$families = [
			'production' => s("Production"),
			'resistance' => s("Résistances"),
			'work' => s("Travail"),
			'quality' => s("Commercialisation"),
		];

		$states = [
			'' => s("Personne"),
			'is-community' => s("La communauté"),
			'is-mine' => s("La structure"),
			'is-mine is-community' => s("Les deux"),
		];

		\Asset::css('plant', 'criterion.css');

		$h = '<p>'.s("Quatre tokens --criterion-* donnent une couleur à chaque famille de critères.").'</p>';
		$h .= '<p class="util-annotation">'.s("Ce sont des alias du palier de marque, pas des couleurs nouvelles : --color-vert-emeraude, --color-reference, --soil et --color-lavande.").'</p>';

		$h .= '<div style="display: flex; flex-wrap: wrap; gap: 1.5rem;">';

			foreach($families as $family => $label) {
				$h .= '<div style="display: flex; align-items: center; gap: 0.5rem;">';
					$h .= '<div style="background-color: var(--criterion-'.$family.'); width: 1rem; height: 1rem; border-radius: 0.25rem;"></div>';
					$h .= '<div>'.$label.'<br /><span class="util-annotation">--criterion-'.$family.'</span></div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation" style="margin-top: 1.5rem;">'.s("\\plant\\PlantUi::getCriterionMark() rend deux motifs qui s'emboîtent, employés par la matrice des critères : le cercle pour la communauté, le carré pour la structure, et le carré dans le cercle quand les deux retiennent le critère. L'étoile est réservée à la notation des variétés, où elle veut dire autre chose.").'</p>';

		$h .= '<div id="plant-criterion" style="display: flex; flex-wrap: wrap; gap: 1.5rem;">';

			foreach($states as $state => $label) {
				$h .= '<div class="is-production '.$state.'" style="display: flex; align-items: center; gap: 0.5rem;">';
					$h .= \plant\PlantUi::getCriterionMark();
					$h .= $label;
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public function follows(): string {

		$h = '<p class="util-annotation">'.s("util-follow marque une valeur qui vient d'un autre élément et se met à jour avec lui : le maillon se pose devant la valeur, sans texte. La valeur n'est pas atténuée, elle se lit et se clique comme une valeur propre. Employé par les variantes de produits, où une valeur au maillon vient du produit principal.").'</p>';

		$h .= '<table>';
			$h .= '<thead><tr><th>'.s("Variante").'</th><th>'.s("Unité").'</th><th class="text-end">'.s("Prix").'</th></tr></thead>';
			$h .= '<tbody>';
				$h .= '<tr><td><span class="util-follow">'.\Asset::icon('link-45deg').s("Poulet prêt à cuire").'</span></td><td><span class="util-follow">'.\Asset::icon('link-45deg').s("pièce").'</span></td><td class="text-end">17,00 €</td></tr>';
				$h .= '<tr><td>'.s("Poulet extra gros").'</td><td><span class="util-follow">'.\Asset::icon('link-45deg').s("pièce").'</span></td><td class="text-end"><span class="util-follow">'.\Asset::icon('link-45deg').'13,00 €</span></td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		$h .= '<p class="util-annotation">'.s("Une valeur sans maillon est propre à l'élément. La légende de l'écran qui l'emploie explique le geste : modifier une valeur au maillon la détache.").'</p>';

		return $h;

	}

	public function notAuto(): string {

		$h = '<p class="util-annotation">'.s("util-not-auto marque le code d'une case d'un formulaire officiel que {siteName} ne calcule pas : aucune donnée ne la porte, c'est à l'utilisateur de la remplir. La case reste vide, la pastille prend la teinte de la comptabilité : c'est une chose à faire, pas une alerte. Employé par la liasse fiscale, et par la fiche d'immobilisation pour une plus-value « À classer », que la règle ne calcule pas tant qu'il manque une donnée à la fiche.").'</p>';

		$h .= '<table>';
			$h .= '<tbody>';
				$h .= '<tr><td>'.s("Autres emprunts et dettes assimilées").'</td><td>CS</td><td class="text-end">12 450</td></tr>';
				$h .= '<tr><td><i>'.s("dont emprunts fonciers").'</i></td><td><span class="util-not-auto">CO</span></td><td></td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function comments(): string {

		$messages = [
			['16/09', new \user\User(['id' => 1, 'vignette' => NULL, 'firstName' => s("Léa"), 'lastName' => s("Dubois")]), s("Paillage posé sur les planches 3 et 4, il en manque pour la 5."), TRUE],
			['17/09', new \user\User(['id' => 2, 'vignette' => NULL, 'firstName' => s("Claire"), 'lastName' => s("Martin")]), s("Deux rouleaux commandés, livraison jeudi."), FALSE],
			['18/09', new \user\User(['id' => 1, 'vignette' => NULL, 'firstName' => s("Léa"), 'lastName' => s("Dubois")]), s("Planche 5 terminée."), TRUE],
		];

		$h = '<p class="util-annotation">'.s("util-comment aligne un fil sur une grille, la date devant le message. util-comment-editable ajoute une troisième colonne, alignée à droite, qui porte le crayon et la corbeille des messages qu'on peut modifier. Employé par les commentaires d'une intervention, où l'auteur se reconnaît à sa vignette, et par les annotations d'une opération bancaire, sous son libellé dans la liste. Un fil qui nomme l'auteur et où chacun n'a pas les mêmes gestes prend util-thread dans un panneau ; dans une cellule de tableau, où sa vignette de 1,75 rem et sa colonne de droite pèseraient trop, il reste sur util-comment, le nom en gras après la vignette.").'</p>';

		$grid = function(string $class) use($messages): string {

			$h = '<div class="util-comment util-comment-editable'.$class.'">';

				foreach($messages as [$date, $eUser, $text, $editable]) {

					$h .= '<div>'.$date.'</div>';
					$h .= '<div>'.\user\UserUi::getVignette($eUser, '1.25rem').' '.$text.'</div>';
					$h .= '<div>';
						if($editable) {
							$h .= '<a class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('pencil-fill').'</a>&nbsp;&nbsp;';
							$h .= '<a class="btn btn-only-icon btn-sm btn-outline-danger">'.\Asset::icon('trash').'</a>';
						}
					$h .= '</div>';

				}

			$h .= '</div>';

			return $h;

		};

		$h .= $grid('');

		\Asset::js('main', 'commentHoverAi.js');

		$h .= '<h4 class="mt-2">'.s("Des gestes qui paraissent au survol").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-comment-hover ne montre les gestes d'un message qu'au survol de ce message, date et texte compris, sans leur retirer leur place : le texte ne se recoupe pas quand ils paraissent. Ils restent affichés tant que la souris ne passe pas à un autre message ou ne quitte pas la grille, pour qu'elle atteigne la corbeille en traversant l'écart de colonne (commentHoverAi.js). Le message survolé ressort sur une bande de fond, peinte sous la grille parce que ses trois cellules, alignées sur la ligne de base, n'ont pas la même hauteur. Un message sans geste ne ressort pas. Au doigt, les gestes sont toujours affichés et aucune bande n'est peinte. Réservé au crayon et à la corbeille : un geste d'ajout ne se cache jamais derrière un survol. Employé par les annotations d'une opération bancaire.").'</p>';

		$h .= $grid(' util-comment-hover');

		$h .= '<h4 class="mt-2">'.s("Un fil qu'on lit seulement, son dernier message coupé à trois lignes").'</h4>';
		$h .= '<p class="util-annotation">'.s("Sans util-comment-editable, la grille n'a que deux colonnes : un fil qu'on lit sans rien pouvoir y faire. Le titre et le bouton d'ajout vont au-dessus, dans un util-title, sauf quand le fil vit dans une ligne de tableau : l'ajout est alors une entrée de la roue de la ligne, et le champ s'ouvre en dernier, à la place que prendra le message.").'</p>';

		$h .= '<div class="util-comment">';
			$h .= '<div>'.s("16/09").'</div>';
			$h .= '<div>';
				$h .= '<div class="util-text-clamp">'.\user\UserUi::getVignette($messages[0][1], '1.25rem').' <b>'.s("Léa Dubois").'</b> '.s("Paillage posé sur les planches 3 et 4, il en manque pour la 5. Le fournisseur habituel est en rupture jusqu'à la fin du mois, j'ai donc demandé un devis à la coopérative, qui livre sous huit jours mais avec un rouleau plus étroit : il faudra deux passages par planche au lieu d'un. Si le devis convient, je le valide lundi et on pose la planche 5 la semaine suivante, avant les semis de mâche. Sinon on attend le retour du fournisseur habituel, et les semis glissent d'autant.").'</div>';
				$h .= '<a href="#design-comments">'.s("Lire la suite").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-text-clamp coupe un texte à trois lignes. La coupe ne se voit pas d'avance, un nombre de caractères ne dit pas combien de lignes un texte prendra à une largeur donnée : le lien qui ouvre le texte entier se montre en comparant la hauteur du texte à celle de la coupe, toutes les mesures d'abord, toutes les écritures ensuite. Employé par la dernière annotation d'une opération bancaire, sous son libellé, et par l'intitulé d'une leçon en tête du tableau de suivi d'une formation, dont le titre complet reste dans l'infobulle.").'</p>';

		$h .= '<h4 class="mt-2">'.s("Un libellé coupé à deux lignes").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-text-clamp-2, posé avec util-text-clamp, coupe à deux lignes : un libellé venu d'ailleurs, dans une rangée qui doit garder sa hauteur. Le texte entier se lit en dépliant le libellé, ou sur l'écran où il mène. Dans un conteneur flex, le parent demande min-width: 0, sans quoi la coupe ne joue pas. Employé par le libellé d'un mouvement Stripe à classer, dans la file « À traiter » du cahier de caisse.").'</p>';
		$h .= '<div class="util-text-clamp util-text-clamp-2" style="max-width: 22rem">'.s("Stripe Climate, contribution de 1 % de votre chiffre d'affaires à l'élimination du carbone pour la période du 1er au 31 octobre").'</div>';

		$h .= '<h4 class="mt-2">'.s("Un mot coupé pour tenir dans sa boîte").'</h4>';
		$h .= '<p class="util-annotation">'.s("text-break coupe un mot qui ne tient pas dans sa boîte, n'importe où s'il le faut : une adresse, un code, un mot saisi sans espace. Le texte qui a des espaces se coupe toujours entre ses mots.").'</p>';
		$h .= '<div class="util-block-inside text-break" style="max-width: 15rem">'.s("Rejoignez la visio sur https://visio.example.org/salle/formation-maraichage-sol-vivant-2027").'</div>';

		return $h;

	}

	public function dragHandles(): string {

		// La copie qui suit le pointeur est hors flux et n'existe que pendant le geste : la
		// planche la pose en marge négative pour la montrer au-dessus du tableau. -30px et
		// non une valeur en rem : la hauteur d'une ligne est menée par les 44 px de la
		// poignée, pas par la taille de police de la racine.
		$row = fn(string $label, string $reference, string $price, string $class = '') => '<tbody'.($class !== '' ? ' class="'.$class.'"' : '').'><tr>'.
			'<td class="td-min-content">'.\main\DesignUi::getDragHandle().'</td>'.
			'<td>'.$label.'</td>'.
			'<td>'.$reference.'</td>'.
			'<td class="text-end">'.$price.'</td>'.
		'</tr></tbody>';

		$h = '<p class="util-annotation">'.s("Quatre états posés par la bibliothèque Sortable, et un cinquième, util-drag-out, posé par le JS du package. util-drag-handle est la poignée, que rend \\main\\DesignUi::getDragHandle() : curseur grab, cible tactile de 44 px, défilement tactile neutralisé, sélection de texte coupée pendant le glisser. util-drag-chosen marque l'élément saisi, du toucher au relâchement. util-drag-ghost marque l'emplacement où l'élément atterrira : Sortable y déplace l'élément d'origine au fil du geste, c'est donc lui l'aperçu du résultat, et il se lit comme un emplacement vide, pas comme une ligne pâlie.").'</p>';
		$h .= '<p class="util-annotation">'.s("sortable-fallback est la copie qui suit le pointeur. Ce nom-là vient de la bibliothèque et non du design system : Sortable le pose lui-même sur la copie, sans réglage à passer, et le styler ici couvre tous les glissements du produit d'un coup. La copie sort du tableau, donc elle en perd le fond, les rayons et les largeurs de colonnes — visible ci-dessous, où ses colonnes ne tombent pas sur celles du tableau. Le fond et le cadre sont rendus par le design system ; les largeurs, elles, ne peuvent venir que du JS, qui les fige avant le clone.").'</p>';

		$h .= '<table class="tbody-even">';
			$h .= '<thead><tr><th class="td-min-content"></th><th>'.s("Variante").'</th><th>'.s("Référence").'</th><th class="text-end">'.s("Prix").'</th></tr></thead>';
			$h .= $row(s("Au repos"), 'PLT-001', '17,00 €');
			$h .= $row('util-drag-chosen', 'PLT-002', '21,00 €', 'util-drag-chosen');
			$h .= $row('util-drag-ghost', 'PLT-003', '9,50 €', 'util-drag-chosen util-drag-ghost');
			$h .= $row(s("Au repos"), 'PLT-004', '15,00 €');
		$h .= '</table>';

		$h .= '<table class="table-unstyled" style="position: relative; margin: -30px 0 2rem 1.5rem; width: calc(100% - 1.5rem);">';
			$h .= '<tbody class="sortable-fallback" style="display: block; opacity: 0.8;"><tr>';
				$h .= '<td class="td-min-content">'.\main\DesignUi::getDragHandle().'</td>';
				$h .= '<td>sortable-fallback</td>';
				$h .= '<td>PLT-003</td>';
				$h .= '<td class="text-end">9,50 €</td>';
			$h .= '</tr></tbody>';
		$h .= '</table>';

		$h .= '<p class="util-annotation">'.s("L'élément saisi porte util-drag-chosen et util-drag-ghost en même temps dès que le geste commence, comme la troisième ligne ici : la règle de l'emplacement passe après celle de la saisie et l'emporte. Sortable recopie util-drag-chosen sur la copie volante, qui n'en a pas l'usage, d'où le contour repris à zéro par sortable-fallback.").'</p>';

		$h .= '<p class="util-annotation">'.s("util-drag-out estompe ce que l'élément saisi ne peut pas rejoindre, une carte d'une autre colonne par exemple. Sortable n'a pas d'option pour cet état : le JS du package qui crée le Sortable pose la classe au départ du geste, dans onStart, et la retire dans onEnd. Des réglages côte à côte se rangent en util-deck util-deck-sm dans util-cards-fit, la poignée en tête, l'interrupteur dans util-deck-aside. util-deck-sm resserre la tête, dont la poignée tient la marge gauche, et util-cards-fit partage toute la ligne entre des cartes peu nombreuses, là où util-cards-fill garderait des pistes vides et laisserait le nom sans place.").'</p>';

		$h .= '<div class="util-cards util-cards-fit">';

			foreach([
				[s("Production"), TRUE, FALSE],
				[s("AMAP"), FALSE, FALSE],
				[s("Vente"), TRUE, TRUE],
				[s("Paramètres"), TRUE, TRUE],
			] as [$name, $on, $out]) {

				$h .= '<div class="util-deck util-deck-sm'.($out ? ' util-drag-out' : '').'">';
					$h .= '<div class="util-deck-head">';
						$h .= \main\DesignUi::getDragHandle();
						$h .= '<div class="util-deck-body"><div class="util-deck-title">'.$name.'</div></div>';
						$h .= '<div class="util-deck-aside">'.\util\TextUi::switch([], on: $on, textOn: s("Oui"), textOff: s("Non")).'</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function tips(): string {

		$h = '<p class="util-annotation">'.s("La coque seule est générique : le placement, la flèche, l'ombre et le repli. Elle se pose en position fixe et se place en JS, parce qu'elle doit suivre un élément qui défile et se retourner quand elle sortirait de la fenêtre. util-tip-above et util-tip-below disent de quel côté la flèche pointe, et la variable --tip-arrow où elle se pose sur la largeur.").'</p>';
		$h .= '<p class="util-annotation">'.s("Ce qu'elle porte l'est en partie. util-tip-head pose en tête ce qu'on survole et sa période, util-tip-figures range dessous des valeurs côte à côte, chacune en util-tip-value sur son util-tip-label, comme la bulle d'une barre des chiffres du menu Accueil. Le reste s'écrit dans la feuille de l'appelant, comme la bulle d'une série sur une planche, dans le plan d'assolement.").'</p>';

		$h .= '<div class="util-cards util-cards-2">';

			foreach(['util-tip-above', 'util-tip-below'] as $tipClass) {

				$h .= '<div>';
					$h .= '<h4>'.$tipClass.'</h4>';
					$h .= '<div class="util-tip util-tip-open '.$tipClass.'" style="position: relative; margin: 1.5rem 0;">';
						$h .= '<b>'.s("Haricot vert 13").'</b>';
						$h .= '<div>'.s("Semis").' · '.s("semaine {value}", 24).'</div>';
						$h .= '<div>'.s("Récolte prévue").' · '.p("{value} semaine", "{value} semaines", 3).'</div>';
					$h .= '</div>';
					$h .= '<div class="util-tip util-tip-open '.$tipClass.'" style="position: relative; margin: 1.5rem 0;">';
						$h .= '<div class="util-tip-head"><b>'.s("Semaine {value}", 41).'</b><span>'.s("du 5 au 11 oct.").'</span></div>';
						$h .= '<div class="util-tip-figures">';
							$h .= '<div><div class="util-tip-value">'.s("6 991 €").'</div><div class="util-tip-label">'.s("en {value}", 2026).'</div></div>';
							$h .= '<div><div class="util-tip-value">'.s("6 412 €").'</div><div class="util-tip-label">'.s("en {value}", 2025).'</div></div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function plants(string $file, array $plants): string {

		$path = \Asset::getPath('plant', $file, 'image');

		$h = '<p class="util-annotation">'.s("{value} images dans le fichier {file}.", ['value' => count($plants), 'file' => $file]).'</p>';

		$columns = 8;

		$h .= '<table class="tr-hover">';
			$h .= '<tbody>';
				foreach(array_chunk($plants, $columns) as $plantsLine) {
					$h .= '<tr>';
						foreach($plantsLine as $plant) {
							$h .= '<td style="text-align: center;">';
								$h .= '<svg width="5rem" height="5rem"><use xlink:href="'.$path.'#'.encode($plant).'"/></svg>';
								$h .= '<div class="mt-1">'.encode($plant).'</div>';
							$h .= '</td>';
						}
						$h .= str_repeat('<td></td>', $columns - count($plantsLine));
					$h .= '</tr>';
				}
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	// La liste affichée est celle du sélecteur et non celle du sprite : une catégorie peut
	// porter une icône Bootstrap, et un symbole peut dormir dans le fichier sans être
	// proposé nulle part. Les deux écarts sont montrés plutôt que masqués par une liste unique.
	public function categories(string $file, array $icons, array $symbols): string {

		$h = '<p class="util-annotation">'.s("{value} icônes proposées au choix d'une catégorie de produits, dont {sprite} tracées dans le fichier {file}.", ['value' => count($icons), 'sprite' => count(array_intersect($icons, $symbols)), 'file' => $file]).'</p>';

		$h .= $this->categoriesGrid($icons);

		// Le trait passe en blanc dans un menu déroulant. L'échantillon prend une icône sur
		// quatre pour attraper aussi les aplats (les yeux des animaux), qui suivent la
		// couleur du texte et non celle du trait.
		$h .= '<h3 class="mt-2">'.s("Sur fond de menu déroulant").'</h3>';

		$h .= '<div class="dropdown-list" style="position: static; display: block;">';
			foreach(array_values(array_filter($icons, fn($position) => $position % 4 === 0, ARRAY_FILTER_USE_KEY)) as $icon) {
				$h .= '<a href="#" class="dropdown-item">';
					$h .= \product\CategoryUi::getVignette(new \product\Category(['icon' => $icon]), '1.5rem').'  ';
					$h .= encode($icon);
				$h .= '</a>';
			}
		$h .= '</div>';

		$orphans = array_diff($symbols, $icons);

		if($orphans !== []) {

			$h .= '<h3 class="mt-2">'.s("Symboles non proposés").'</h3>';
			$h .= '<p class="util-annotation">'.s("Présents dans {file} mais absents de la liste de choix.", ['file' => $file]).'</p>';
			$h .= $this->categoriesGrid(array_values($orphans));

		}

		return $h;

	}

	private function categoriesGrid(array $icons): string {

		$columns = 8;

		$h = '<table class="tr-hover">';
			$h .= '<tbody>';
				foreach(array_chunk($icons, $columns) as $iconsLine) {
					$h .= '<tr>';
						foreach($iconsLine as $icon) {
							$h .= '<td style="text-align: center;">';
								$h .= \product\CategoryUi::getVignette(new \product\Category(['icon' => $icon]), '4rem');
								$h .= '<div class="mt-1">'.encode($icon).'</div>';
							$h .= '</td>';
						}
						$h .= str_repeat('<td></td>', $columns - count($iconsLine));
					$h .= '</tr>';
				}
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function friends(string $folder, array $friends): string {

		$h = '<p class="util-annotation">'.s("{value} images dans le dossier {folder}.", ['value' => count($friends), 'folder' => $folder]).'</p>';

		$columns = 6;

		$h .= '<table class="tr-hover">';
			$h .= '<tbody>';
				foreach(array_chunk($friends, $columns) as $friendsLine) {
					$h .= '<tr>';
						foreach($friendsLine as $friend) {
							$h .= '<td style="text-align: center;">';
								// Fichiers autonomes et non symboles d'un sprite : chacun a son propre
								// rapport d'aspect, d'où le object-fit qui les cadre sans les déformer.
								$h .= \Asset::image('main', $folder.'/'.$friend.'.svg', ['style' => 'width: 5rem; height: 5rem; object-fit: contain;']);
								$h .= '<div class="mt-1">'.encode($friend).'</div>';
							$h .= '</td>';
						}
						$h .= str_repeat('<td></td>', $columns - count($friendsLine));
					$h .= '</tr>';
				}
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function titles(): string {

		$h = '';

		foreach(['h1', 'h2', 'h3', 'h4', 'h5'] as $tag) {
			$h .= '<'.$tag.'>'.s("Titre de type {value}", $tag).'</'.$tag.'>';
		}

		return $h;
	}

	public function textSizes(): string {

		$h = '<div id="convention-taille-minimale" class="util-block">';

			foreach(['1rem' => 'design-type-base', '0.9rem' => 'design-type-floor'] as $size => $class) {
				$h .= '<div class="design-type-line"><span class="mono">'.$size.'</span><span class="'.$class.'">'.s("Récolte prévue en semaine 24").'</span></div>';
			}

			foreach(['0.8rem' => 'design-type-under', '0.7rem' => 'design-type-under-min'] as $size => $class) {
				$h .= '<div class="design-type-line design-type-line-under"><span class="mono">'.$size.'</span><span class="'.$class.'">'.s("Récolte prévue en semaine 24").'</span></div>';
			}

		$h .= '</div>';

		$h .= '<p>'.s("Le plancher est 0,9 rem, et il vaut pour tout texte, gras ou non.").'</p>';
		$h .= '<p class="util-annotation">'.s("Les deux dernières lignes sont sous le seuil : elles sont là pour qu'on voie ce que la règle écarte.").'</p>';

		$h .= '<p class="util-annotation">'.s("La première ligne est la règle : un texte, un lien, un libellé ou une ligne de liste s'écrit à 1 rem. La deuxième est l'exception, et une feuille qui l'emploie dit sur sa ligne ce qui la justifie.").'</p>';

		$h .= '<p class="util-annotation">'.s("Le socle les porte encore, et c'est une dette et non une dispense : {command} rend 141 tailles, dont 39 sous le seuil. Un écran neuf tient la règle ; une taille de component.css étant portée par tous les écrans à la fois, l'existant ne se redresse pas en passant.", [
			'command' => '<span class="mono">grep -oE \'font-size: *[0-9.]+rem\' component.css</span>',
		]).'</p>';

		return $h;
	}

	public function mono(): string {

		$h = '<p>'.s("Un numéro de compte {account} ou une référence se posent en {class}.", [
			'account' => '<span class="mono">60112</span>',
			'class' => '<span class="mono">mono</span>',
		]).'</p>';

		$h .= '<p>'.s("Même réglage que btn-number, dont c'est la version sans bouton : le même numéro se lit à l'identique qu'il porte une action ou non.").'</p>';
		$h .= '<p class="util-annotation">'.s("La chasse fixe est ce qui permet de comparer deux numéros l'un sous l'autre dans une colonne, ce qu'une police proportionnelle interdit.").'</p>';

		return $h;
	}

	// Modificateurs et sous-composants : ils ne s'affichent correctement que dans leur contexte,
	// ils sont donc écartés de la liste des variantes de couleur et présentés par buttonModifiers()
	const BTN_MODIFIERS = [
		'btn-group', 'btn-group-addon', 'btn-status', 'btn-status-fill',
		'btn-icon', 'btn-form', 'btn-panel', 'btn-readonly', 'btn-selected', 'btn-chosen', 'btn-dashed',
		'btn-xxs', 'btn-xs', 'btn-sm', 'btn-lg', 'btn-xl', 'btn-only-icon', 'btn-bare'
	];

	/**
	 * Les trois types de secondary dans chaque univers, le contour btn-outline-secondary n'ayant plus
	 * d'emploi que dans les outils d'une tête d'écran, les boutiques et les sites, dont le thème le
	 * redessine, et pour le ton info d'une carte à geste. Hors de la page d'une structure, secondary vaut la marque ; la page du design
	 * system n'en est pas une, chaque ligne pose donc l'univers qu'elle montre.
	 */
	private function getButtonUniverses(): string {

		$universes = [
			'' => s("Hors structure"),
			'production' => s("Production"),
			'commercialisation' => s("Vente"),
			'accounting' => s("Comptabilité"),
			'settings' => s("Réglages"),
			'amap' => s("AMAP"),
		];

		$h = '<div class="design-button-universes">';

			foreach($universes as $universe => $label) {

				$h .= '<div class="design-button-universe'.($universe === '' ? '' : ' design-button-universe-'.$universe).'">';
					$h .= '<span class="util-annotation">'.$label.'</span>';

					foreach([
						['btn-secondary', s("Créer")],
						['btn-secondary-light', s("Rattacher")],
						['btn-color-secondary', \Asset::icon('plus').' '.s("Ajouter")],
					] as [$class, $content]) {
						$h .= '<span class="design-button-named">';
							$h .= '<a href="#" class="btn '.$class.'">'.$content.'</a>';
							$h .= '<code>'.$class.'</code>';
						$h .= '</span>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un type de bouton dans ses couleurs, une ligne par classe : le nom, le bouton que le pointeur
	 * survole et que le clavier atteint sur place, puis le même désactivé.
	 *
	 * @param array $buttons Des paires [classe, libellé].
	 */
	private function getButtonTable(array $buttons): string {

		$h = '<div class="design-buttons">';

			$h .= '<span class="util-annotation">'.s("Classe").'</span>';
			$h .= '<span class="util-annotation">'.s("Au repos").'</span>';
			$h .= '<span class="util-annotation">'.s("Désactivé").'</span>';

			foreach($buttons as [$class, $label]) {
				$h .= '<code>'.$class.'</code>';
				$h .= '<span><a href="#" class="btn '.$class.'">'.$label.'</a></span>';
				$h .= '<span><a href="#" class="btn '.$class.' disabled">'.$label.'</a></span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les quatre types de bouton, la palette des classes btn-* pour les agents, puis quand prendre
	 * quel bouton, une règle par cas avec les boutons qu'elle demande, chacun nommé par sa classe.
	 * Les sections de type qui suivent disent le dessin et les états, pas l'emploi.
	 */
	public function buttonsUsage(bool $forAgents = FALSE): string {

		\Asset::css('main', 'font-ptserif.css');

		$menuItems = '<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>';
		$menu = '<div class="dropdown-list">'.$menuItems.'</div>';

		// La liste d'un menu suit son bouton, avant le nom de la classe : Lime.Dropdown la cherche
		// dans le frère qui suit le bouton.
		$named = fn(string $class, string $content, string $attributes = '', bool $withMenu = FALSE): string =>
			'<span class="design-button-named">'.
				'<a href="#" class="btn '.$class.'"'.$attributes.'>'.$content.'</a>'.
				($withMenu ? $menu : '').
				'<code>'.$class.'</code>'.
			'</span>';

		$gear = \main\DesignUi::getGear($menuItems);

		$rules = [
			[
				s("L'action qui fait avancer la zone, l'envoi d'un formulaire ou le geste d'un état vide, est le plein noir. Une zone visible n'en a qu'un."),
				'<div class="flex-buttons">'.$named('btn-primary', s("Enregistrer")).'</div>'
			],
			[
				s("Quand cette action porte l'univers de l'écran, comme les suggestions de rapprochement d'une facture ou la création dans la tête d'un écran, le plein prend sa couleur. Il remplace le noir, il ne s'y ajoute pas."),
				'<div class="flex-buttons design-button-universe-commercialisation">'.$named('btn-secondary', s("Voir les suggestions de rapprochement")).'</div>'
			],
			[
				s("Le plein secondaire est à réserver au geste qui accompagne le plein sans lui disputer la place, neutre ou dans l'univers de l'écran."),
				'<div class="flex-buttons design-button-universe-commercialisation">'.$named('btn-primary', s("Enregistrer")).$named('btn-primary-light', s("Aperçu")).$named('btn-secondary-light', s("Rattacher")).'</div>'
			],
			[
				s("Toute autre action prend le contour noir, dont Annuler et celle à droite d'un titre de section."),
				'<div class="flex-buttons">'.$named('btn-outline-primary', s("Importer")).$named('btn-outline-primary', s("Annuler")).'</div>'
			],
			[
				s("Le réglage d'une ligne ou d'un écran est une roue, le blanc avec la seule icône gear-fill. Comme tout bouton qui ouvre un menu, elle garde sa flèche."),
				'<div class="flex-buttons">'.
					$named('btn-color-primary dropdown-toggle', \Asset::icon('gear-fill'), ' data-dropdown="bottom-end"', TRUE).
					$named('btn-outline-primary dropdown-toggle', s("Exporter"), ' data-dropdown="bottom-start"', TRUE).
				'</div>'
			],
			[
				s("Dans une ligne de tableau, chaque bouton prend la taille de ses voisins, roue comprise."),
				'<div class="flex-buttons">'.
					$named('btn-sm btn-outline-primary', s("Choisir")).
					$named('btn-sm btn-color-primary dropdown-toggle', \Asset::icon('gear-fill'), ' data-dropdown="bottom-end"', TRUE).
				'</div>'
			],
			[
				s("Le vert, l'orange et le rouge reprennent le ton d'un bloc ou d'un panneau que l'écran montre, comme le bouton d'un util-block-warning ou la confirmation d'une perte sans retour par getDeletePanel(). Ils ne disent pas qu'un geste est important."),
				'<div class="flex-buttons">'.$named('btn-warning', s("Reconnecter")).$named('btn-danger', s("Supprimer définitivement")).'</div>'
			],
			[
				s("Une suppression ordinaire est un contour noir qui demande confirmation par data-confirm, et ni btn-outline-danger ni btn-danger ne s'y posent. Parmi les portes d'un deck, elle prend le plein secondaire rouge."),
				'<div class="flex-buttons">'.$named('btn-outline-primary', s("Supprimer")).$named('btn-danger-light', s("Déconnecter")).'</div>'
			],
			[
				s("Une valeur retenue parmi des pastilles en contour se marque sans devenir un plein, qui reste à l'action de la zone."),
				'<div class="flex-buttons">'.
					$named('btn-sm btn-outline-primary', s("8 h")).
					$named('btn-sm btn-outline-primary btn-chosen', s("9 h")).
					$named('btn-sm btn-outline-primary', s("10 h")).
				'</div>'
			],
			[
				s("Les boutons d'une tête d'écran se passent à getPageHeader(), les outils en btn-outline-secondary par tools, la création en btn-secondary par create et la roue en btn-color-primary par settings. La tête leur donne sa hauteur et sa forme de pastille, l'univers de l'écran leur couleur."),
				'<div class="design-button-universe-commercialisation">'.
					\main\DesignUi::getPageHeader(
						s("Ventes"),
						back: '',
						tools: '<a href="#" class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>',
						create: '<a href="#" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.s("Nouvelle vente").'</a>',
						settings: $gear
					).
				'</div>'
			],
			[
				s("Une boutique et un site public gardent les boutons de leur thème, avec sa police, des contours qui se remplissent au survol et le contour de l'univers, que les thèmes de boutique habillent. Le dessin du produit ne s'y pose pas, et le leur ne sort pas de leurs pages."),
				'<div class="flex-buttons website-wrapper design-button-custom-font">'.$named('btn-outline-primary', s("Voir les produits")).$named('btn-outline-secondary', s("Ajouter au panier")).'</div>'
			],
			[
				s("L'appel principal d'une page publique, comme l'inscription ou la démo, prend la terracotta, et son second appel le contour de la même couleur. Jamais dans un écran du produit, où le rouge dit une alerte."),
				'<div class="flex-buttons">'.$named('btn-terracota', s("Inscription")).$named('btn-outline-terracota', s("Essayer la démo")).'</div>'
			],
		];

		$h = '<p class="util-annotation">'.s("Un bouton a quatre types dans chaque couleur, le plein btn-primary, le plein secondaire btn-primary-light, le blanc btn-color-primary et le contour btn-outline-primary. primary est le noir, secondary l'univers de l'écran, ou la marque hors d'une structure, et success, warning et danger disent un état.").'</p>';

		if($forAgents) {

			$h .= '<h4 class="mt-2">'.s("Toutes les classes btn-*").'</h4>';
			$h .= '<p class="util-annotation">'.s("Extraites de la feuille de style, sans les modificateurs ni les tailles. Une classe qui n'est dans aucun des quatre types est une couleur d'objet du produit (btn-order, btn-game…), à ne pas poser hors de son écran.").'</p>';

			$h .= '<div class="design-button-palette">';
				foreach(array_diff($this->extractBtnClasses(), self::BTN_MODIFIERS) as $class) {
					$h .= '<a href="#" class="btn '.$class.'">'.$class.'</a>';
				}
			$h .= '</div>';

		}

		// En lecture, ce titre ouvre la démo de la fiche, les annotations étant retirées avant lui
		$h .= '<h4'.($forAgents ? ' class="mt-2"' : '').'>'.s("Quel bouton prendre").'</h4>';

		$h .= '<div class="design-button-rules">';

			foreach($rules as [$rule, $demo]) {
				$h .= '<div class="design-button-rule">';
					$h .= '<p>'.$rule.'</p>';
					$h .= $demo;
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public function buttonsFull(): string {

		$h = $this->getButtonUniverses();
		$h .= '<p class="util-annotation">'.s("btn-primary est le plein noir, btn-secondary le plein de l'univers de l'écran, et btn-success, btn-warning et btn-danger les pleins des tons.").'</p>';
		$h .= '<p class="util-annotation">'.s("Au survol, un voile noir fonce le fond de 20 %, de 32 % à l'actif, quelle que soit la couleur posée sur le bouton. Une boutique et un site public gardent le voile de 10 % de leurs thèmes.").'</p>';

		$h .= $this->getButtonTable([
			['btn-primary', s("Enregistrer")],
			['btn-secondary', s("Nouvelle vente")],
			['btn-success', s("Valider")],
			['btn-warning', s("Relancer")],
			['btn-danger', s("Supprimer")],
		]);

		return $h;

	}

	public function buttonsLight(): string {

		$h = '<p class="util-annotation">'.s("Le plein secondaire est une pastille pâle sans cerne, à l'encre foncée de sa couleur.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le fond mêle la couleur à la surface, à 8 % au repos, 14 % au survol et 20 % à l'actif, et l'encre tient 4,5:1 sur les trois. btn-primary-light part du gris de la page au repos.").'</p>';

		$h .= $this->getButtonTable([
			['btn-primary-light', s("Aperçu")],
			['btn-secondary-light', s("Rattacher")],
			['btn-success-light', s("Valider")],
			['btn-warning-light', s("Relancer")],
			['btn-danger-light', s("Déconnecter")],
		]);

		return $h;

	}

	public function buttonDomains(): string {

		$h = '<p class="util-annotation">'.s("btn-production-light, btn-commercialisation-light, btn-accounting-light et btn-amap-light ouvrent un domaine depuis un écran qui n'appartient à aucun : la carte d'une structure dans la liste de l'utilisateur et, pour la production seule, le partage d'une structure dans un groupe. Ce sont des pleins secondaires à l'univers du domaine où ils mènent, quel que soit celui de l'écran, et l'icône du domaine précède le libellé. btn-light est la variante neutre, pour une entrée qui ne relève d'aucun domaine.").'</p>';

		$h .= $this->getButtonTable([
			['btn-production-light', \Asset::icon('leaf').' '.s("Production")],
			['btn-commercialisation-light', \Asset::icon('basket3').' '.s("Vente")],
			['btn-accounting-light', \Asset::icon('piggy-bank').' '.s("Gestion")],
			['btn-amap-light', \Asset::icon('people').' '.s("AMAP")],
			['btn-light', \Asset::icon('graph-up').' '.s("Relevés des prix")],
		]);

		return $h;

	}

	public function buttonsColor(): string {

		$h = '<p class="util-annotation">'.s("Le blanc pose l'encre et l'icône de sa couleur sur un fond blanc, dans le filet gris des cartes.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le survol et l'actif prennent la couleur à 8 puis 14 %. Lime.Dropdown ouvre le menu d'un btn-color-primary sur bg-primary et celui d'un btn-color-secondary sur bg-secondary, comme pour un plein ou un contour de la même couleur.").'</p>';

		$h .= $this->getButtonTable([
			['btn-color-primary btn-only-icon', \Asset::icon('gear')],
			['btn-color-primary', \Asset::icon('pencil').' '.s("Modifier")],
			['btn-color-secondary', \Asset::icon('plus').' '.s("Ajouter")],
			['btn-color-success', \Asset::icon('check-lg').' '.s("Valider")],
			['btn-color-warning', \Asset::icon('bell').' '.s("Relancer")],
			['btn-color-danger', \Asset::icon('trash').' '.s("Supprimer")],
		]);

		$h .= '<h4 class="mt-2">'.s("La roue d'une ligne").'</h4>';
		$h .= '<p class="util-annotation">'.s("\\main\\DesignUi::getGear() rend la roue et le menu qu'elle ouvre, sa flèche comprise, à partir des entrées déjà rendues. Le titre du menu nomme l'objet réglé quand la ligne ne le dit pas assez près.").'</p>';
		$h .= '<div>'.\main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><div class="dropdown-divider"></div><a href="#" class="dropdown-item">'.s("Supprimer").'</a>', s("Justificatif")).'</div>';

		return $h;

	}

	public function buttonsOutline(): string {

		$h = '<p class="util-annotation">'.s("Le contour cerne le bouton de sa couleur sur le fond de la surface, et btn-outline-primary est le contour noir.").'</p>';
		$h .= '<p class="util-annotation">'.s("btn-outline-secondary dessine le contour de l'univers et ne sert plus que pour les outils d'une tête d'écran, dans les boutiques et les sites, dont le thème le redessine, et pour le ton info d'une carte à geste. Une autre action du produit prend btn-outline-primary, une roue btn-color-primary.").'</p>';
		$h .= '<p class="util-annotation">'.s("Au survol, le contour ne se remplit pas, sa couleur teinte le fond à 8 %, puis 14 % à l'actif, et son encre ne change pas. btn-outline-primary, plus présent, passe à 6 % puis 12 %, et son cerne gris prend l'encre. btn-outline-secondary se teinte de la couleur claire de l'univers. Le vert, l'orange et le rouge écrivent dans leur encre foncée.").'</p>';

		$h .= $this->getButtonTable([
			['btn-outline-primary', s("Importer")],
			['btn-outline-secondary', s("Importer")],
			['btn-outline-success', s("Valider")],
			['btn-outline-warning', s("Relancer")],
			['btn-outline-danger', s("Supprimer")],
		]);

		$h .= '<h4 class="mt-2">'.s("Un numéro qui mène à sa fiche").'</h4>';

		$h .= $this->getButtonTable([
			['btn-outline-number btn-sm', '411000'],
			['btn-number btn-sm', 'FA-2026-0142'],
		]);

		return $h;

	}

	public function buttonsFocus(): string {

		$h = '<h4 class="mt-2">'.s("Le focus du clavier").'</h4>';
		$h .= '<p class="util-annotation">'.s("Au clavier, le bouton atteint par la touche Tab se cerne d'un anneau de 3 px, décalé de 3 px, dans l'univers de l'écran mêlé à 70 % à la surface. L'anneau tient 3:1 sur le blanc et sur la feuille dans les cinq univers.").'</p>';
		$h .= '<p class="util-annotation">'.s("La classe disabled et l'attribut disabled posent le bouton à 45 % et le soustraient au pointeur. btn-readonly garde le dessin du bouton sans survol, pour une valeur qu'on montre à la place d'un geste.").'</p>';

		$h .= '<div class="design-button-palette">';
			$h .= '<span class="design-button-named"><a href="#" class="btn btn-primary design-button-focus">'.s("Enregistrer").'</a><code>:focus-visible</code></span>';
			$h .= '<a href="#" class="btn btn-primary-light">'.s("Aperçu").'</a>';
			$h .= '<a href="#" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
			$h .= '<a href="#" class="btn btn-outline-primary">'.s("Importer").'</a>';
			$h .= '<button type="button" class="btn btn-primary" disabled>'.s("Attribut disabled").'</button>';
			$h .= '<a href="#" class="btn btn-outline-primary btn-readonly">'.s("Lecture seule").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function buttonModifiers(): string {

		$h = '<h4 class="mt-2">'.s("Modificateurs et sous-composants").'</h4>';
		$h .= '<p class="util-annotation">'.s("Ces classes ne se suffisent pas à elles-mêmes : elles complètent un bouton ou se placent à l'intérieur.").'</p>';

		$h .= '<div style="display: flex; gap: 2rem; flex-wrap: wrap; align-items: flex-start; padding: 1.5rem 1rem 1rem; background: var(--background); border-radius: var(--radius);">';

			$example = function(string $label, string $content): string {
				return '<div><div class="util-annotation" style="margin-bottom: 0.5rem;">'.$label.'</div>'.$content.'</div>';
			};

			$h .= $example('btn-group · btn-group-addon',
				'<div class="btn-group">'.
					'<span class="btn btn-group-addon btn-form">'.s("Total").'</span>'.
					'<a href="#" class="btn btn-primary">42</a>'.
				'</div>'
			);

			$h .= $example('btn-status · btn-status-fill',
				'<a href="#" class="btn btn-outline-primary">'.s("Filtres").'<span class="btn-status">3</span></a> '.
				'<a href="#" class="btn btn-outline-primary">'.s("Panier").'<span class="btn-status-fill">7</span></a>'
			);

			$h .= $example('btn-icon',
				'<a href="#" class="btn btn-color-primary">'.
					\Asset::icon('gear-fill', ['class' => 'btn-icon']).
					s("Réglages").
				'</a>'
			);

			$h .= $example('btn-form',
				'<a href="#" class="btn btn-form">'.s("Aspect champ de formulaire").'</a>'
			);

			$h .= $example('btn-selected',
				'<a href="#" class="btn btn-form">'.s("Onglet").'</a> '.
				'<a href="#" class="btn btn-form btn-selected">'.s("Onglet actif").'</a>'
			);

			$h .= $example('btn-chosen',
				'<a href="#" class="btn btn-sm btn-outline-primary">'.s("8 h").'</a> '.
				'<a href="#" class="btn btn-sm btn-outline-primary btn-chosen">'.s("9 h").'</a> '.
				'<a href="#" class="btn btn-sm btn-outline-primary">'.s("10 h").'</a>'
			);

			$h .= $example('btn-readonly · disabled',
				'<a href="#" class="btn btn-outline-primary btn-readonly">'.s("Non cliquable").'</a> '.
				'<a href="#" class="btn btn-primary disabled">'.s("Désactivé").'</a>'
			);

			$h .= $example('btn-panel '.s("(rond sous 576px seulement)"),
				'<a href="#" class="btn btn-only-icon btn-primary btn-panel">'.\Asset::icon('plus-lg').'</a>'
			);

			$h .= $example('btn-dashed '.s("(emplacement à remplir)"),
				'<a href="#" class="btn btn-only-icon btn-dashed btn-xs">'.\Asset::icon('plus-lg').'</a> '.
				'<a href="#" class="btn btn-only-icon btn-dashed">'.\Asset::icon('plus-lg').'</a> '.
				'<a href="#" class="btn btn-dashed">'.s("Ajouter une ligne").'</a>'
			);

		$h .= '</div>';

		return $h;

	}

	public function buttonSelect(): string {

		$h = '<h4 class="mt-2">'.s("Faire apparaître les cases d'une liste").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le geste qui fait apparaître les cases d'une liste est un btn-outline-primary, comme toute action qui ne fait pas avancer la zone. Au repos, il porte l'icône check2-square suivie de « Sélectionner », et garde ce mot à toutes les largeurs, l'icône seule ne disant pas le geste. Seule la rangée d'outils du planning le réduit à l'icône sous 576 px, pour que l'interrupteur « Masquer ce qui est fait », ce bouton et « + Intervention » tiennent sur une même ligne au repos. Pendant la sélection, « Terminé » est précédé de la même icône, pour se lire comme le même bouton dans son autre état.").'</p>';

		$h .= '<div class="util-block" style="display: flex; gap: 2rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<div><div class="util-annotation" style="margin-bottom: 0.5rem;">'.s("Au repos").'</div><a href="#" class="btn btn-outline-primary">'.\Asset::icon('check2-square').' '.s("Sélectionner").'</a></div>';
			$h .= '<div><div class="util-annotation" style="margin-bottom: 0.5rem;">'.s("Pendant la sélection").'</div><a href="#" class="btn btn-outline-primary">'.\Asset::icon('check2-square').' '.s("Terminé").'</a></div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les grands boutons d'entrée : 78 emplois dans le produit, jamais présentés ici avant le
	 * 2026-09-11. Le balisage est celui de `base/structure/ui/Member.u.php` et de
	 * `Structure.u.php` (fermeture d'une structure).
	 */
	public function entries(): string {

		$h = '<p class="util-annotation">'.s("util-buttons aligne de grands choix, trois par ligne puis deux sous 992px et un sous 576px. Chaque util-button porte un titre en h4, une icône à droite, et tient dans un lien : c'est l'entrée d'un parcours (créer sa structure, rejoindre un groupe), jamais une action qui envoie un formulaire. util-button-danger dit un geste qui détruit, util-button-text ajoute une ligne de précision sous le titre, util-buttons-dark pose l'ensemble sur la couleur principale.").' '.s("La précision s'écrit en gris atténué (--muted) sur le bouton clair, en rouge sur util-button-danger, et en blanc à 70 % sur util-buttons-dark.").' '.s("Dans un util-block-cta ou un util-block-empty, util-buttons range les btn du geste, centrés ; hors d'un bloc, util-buttons-center fait de même.").'</p>';
		$h .= '<p class="util-annotation">'.s("Ne pas le prendre pour une entrée de liste : une liste de réglages, d'exports ou d'objets est une suite de util-deck, même quand chaque ligne est un lien comme ici. Ce qui sépare les deux n'est pas le geste mais la place : un util-button est un choix qui structure l'écran et qu'on compte sur les doigts d'une main, un util-deck est une ligne parmi d'autres dans une liste qui peut s'allonger.").'</p>';
		$h .= '<p class="util-annotation">'.s("La page « Mon compte » (ouvretaferme/main/view/account.v.php) porte les deux composants côte à côte, et c'est le meilleur exemple de la frontière : ses quatre réglages sont des util-deck, et les six « Créer une ferme », « Créer une AMAP », « Créer un groupe de formation » restent des util-button, parce qu'ils ouvrent un parcours au lieu d'allonger une liste. Le geste destructeur, lui, quitte la liste et garde util-button-danger.").'</p>';

		$h .= '<div class="util-buttons">';

			$h .= '<a href="#" class="util-button">';
				$h .= '<div><h4>'.s("Démarrer la création de ma structure").'</h4></div>';
				$h .= \Asset::icon('house-door-fill');
			$h .= '</a>';

			$h .= '<a href="#" class="util-button">';
				$h .= '<div><h4>'.s("Rejoindre un groupe").'</h4><div class="util-button-text">'.s("Avec le code d'invitation reçu par e-mail").'</div></div>';
				$h .= \Asset::icon('people');
			$h .= '</a>';

			$h .= '<a href="#" class="util-button util-button-danger">';
				$h .= '<div><h4>'.s("Supprimer la structure").'</h4></div>';
				$h .= \Asset::icon('trash');
			$h .= '</a>';

		$h .= '</div>';

		$h .= '<div class="util-buttons util-buttons-dark">';

			$h .= '<a href="#" class="util-button">';
				$h .= '<div><h4>'.s("util-buttons-dark").'</h4></div>';
				$h .= \Asset::icon('moon');
			$h .= '</a>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-button-seed remplace l'icône par une graine, quand les choix suivent un achat et prolongent l'illustration de la page. Premier emploi : les premiers pas après une souscription (subscription\\PageAiUi).").'</p>';

		$h .= '<div class="util-buttons">';

			$h .= '<a href="#" class="util-button">';
				$h .= '<div><h4>'.s("Relier votre banque").'</h4><div class="util-button-text">'.s("Les opérations arrivent seules, prêtes à rapprocher").'</div></div>';
				$h .= '<span class="util-button-seed">'.\Asset::svg('main', 'friends/petale-lavande.svg').'</span>';
			$h .= '</a>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-buttons-center range des btn à leur largeur propre, centrés sous un titre centré, et les passe à la ligne quand la place manque, avec l'écart de util-buttons. La grille, elle, laisserait deux dates dans les deux premières colonnes sur trois. Premier emploi : les dates du webinaire de la page des formations.").'</p>';

		$h .= '<div class="util-buttons util-buttons-center">';
			foreach([s("Mardi 3 novembre · 18h à 19h"), s("Jeudi 12 novembre · 12h à 13h")] as $date) {
				$h .= '<a href="#" class="btn btn-outline-terracota btn-lg">'.$date.'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	public function drop(): string {

		$h = '<p class="util-annotation">'.s("util-drop reçoit des fichiers glissés depuis l'ordinateur, et porte aussi le bouton qui ouvre le sélecteur, puisqu'on ne glisse pas toujours. util-drop-over la marque pendant qu'un fichier la survole. Une fois des fichiers posés, la zone garde son appel en tête (util-drop-head), les liste dessous (util-drop-list, une util-table : l'icône, util-drop-name, util-drop-state et la croix util-previews-remove sur chaque rangée) et finit par util-drop-foot, avec le compte de ce qui est reçu à gauche et le bouton qui termine à droite. Une rangée dit son état par data-state (waiting, pending ou done) et son refus par form-error ; chaque texte d'état nomme dans data-drop-state ceux où il paraît, et la zone n'en montre qu'un. Le compte prend ses textes dans getFoot(), un, aucun et plusieurs, et MainDrop.count() les récrit. La liste et le geste qui la conclut restent ainsi dans le même cadre. Le bouton reste inactif tant qu'un fichier part encore. Celui de l'appel devient « Ajouter d'autres fichiers » dès qu'un fichier est reçu. Au téléphone, deux util-button remplacent l'appel, et le cadre reste autour de la liste et de son pied. Au-dessous de 576 px, l'état passe sous le nom, qui garde toute la largeur ; un refus y passe à toutes les largeurs, puisqu'il peut citer un nom de fichier aussi long que celui de la ligne. Une erreur sur le lot se pose sur le form-error-wrapper enfant direct de la zone, qui entoure l'appel et la liste : le cadre passe au rouge et le message tombe au-dessus du pied. Un fichier refusé l'est sur sa ligne, sans toucher au cadre. Le balisage vient de main\\DropAiUi, le comportement commun de MainDrop. Premier emploi : le dépôt des justificatifs (proof\\ProofUi), puis le rattachement, l'import des relevés et l'import d'une vente.").'</p>';

		$icon = fn(string $icon, string $domain = 'accounting') => '<div class="util-icon util-icon-round util-icon-'.$domain.'">'.\Asset::icon($icon).'</div>';
		$states = '<span class="color-muted" data-drop-state="waiting">'.s("Non envoyé").'</span>'
			.'<span class="color-muted" data-drop-state="pending">'.s("Envoi en cours").'</span>'
			.'<span class="color-success" data-drop-state="done">'.\Asset::icon('check-lg').' '.s("Déposé").'</span>'
			.'<span class="color-danger" data-drop-state="refused">'.\Asset::icon('x-circle').' '.s("12,4 Mo, au-delà de 5 Mo").'</span>';

		// Un refus se montre comme Lime.Alert le pose, par form-error sur la ligne
		$row = fn(string $icon, string $name, string $state, bool $remove = FALSE) => '<div class="util-table-row'.($state === 'refused' ? ' form-error' : '').'" data-state="'.($state === 'refused' ? 'done' : $state).'"><div>'.$icon.'</div><div class="util-drop-name">'.$name.'</div><div class="util-drop-state">'.$states.'</div>'.($remove ? '<div><a class="util-previews-remove">'.\Asset::icon('x-lg').'</a></div>' : '').'</div>';

		$foot = fn(string $count, string $button, bool $sending = FALSE) => \main\DropAiUi::getFoot('<a class="btn btn-primary'.($sending ? ' disabled' : '').'">'.$button.'</a>', [], $count);

		$deposited = 'done';
		$pending = 'pending';
		$waiting = 'waiting';
		$refused = 'refused';

		$proofText = s("Glissez vos factures et vos tickets ici");
		$proofFormats = s("PDF, JPEG, PNG ou HEIC");
		$saleText = s("Glissez une facture, une commande ou les photos d'un ticket ici");
		$saleFormats = s("PDF, JPEG ou PNG");

		$variants = [
			s("Vide") => \main\DropAiUi::open('').'<div>'.\main\DropAiUi::getHead($proofText, $proofFormats, s("Choisir des fichiers"), '', TRUE).\main\DropAiUi::getList().'</div></div>',
			s("util-drop-over, un fichier la survole") => \main\DropAiUi::open('', 'util-drop-over').'<div>'.\main\DropAiUi::getHead($proofText, $proofFormats, s("Choisir des fichiers"), '', TRUE).\main\DropAiUi::getList().'</div></div>',
			s("Un envoi en cours, le bouton inactif") => \main\DropAiUi::open('')
				.'<div>'.\main\DropAiUi::getHead($proofText, $proofFormats, s("Choisir des fichiers"), '', FALSE).\main\DropAiUi::getList($row($icon('file-pdf'), 'facture-octobre.pdf', $pending)).'</div>'
				.$foot(s("Aucun justificatif déposé"), s("Terminé"), TRUE).'</div>',
			s("Reçus et refusés, un nom long et un refus long") => \main\DropAiUi::open('')
				.'<div>'.\main\DropAiUi::getHead($proofText, $proofFormats, s("Ajouter d'autres fichiers"), '', FALSE)
				.'<div class="util-block-danger"><p><b>'.s("1 fichier n'a pas pu être déposé.").'</b></p></div>'
				.\main\DropAiUi::getList($row($icon('file-pdf'), 'facture-octobre.pdf', $deposited).$row($icon('file-pdf'), 'Facture_FA2026-0918_COOPERATIVE-AGRICOLE-DU-VAL-D-ALLIER_SIEGE-SOCIAL_releve-mensuel-septembre.pdf', $refused)).'</div>'
				.$foot(s("1 justificatif déposé"), s("Terminé")).'</div>',
			s("Une erreur sur le lot") => \main\DropAiUi::open('', 'util-domain-commercialisation')
				.'<div class="form-error-wrapper">'.\main\DropAiUi::getHead($saleText, $saleFormats, s("Ajouter d'autres fichiers"), '', FALSE)
				.\main\DropAiUi::getList($row($icon('file-pdf', 'commercialisation'), 'commande-amap.pdf', $waiting, TRUE).$row($icon('image', 'commercialisation'), s("Photo {value}", 1), $waiting, TRUE))
				.'<p class="form-error-message">'.s("Déposez un seul PDF, ou les photos d'un même ticket.").'</p></div>'
				.$foot(s("2 fichiers"), s("Lire les fichiers")).'</div>',
			s("Sans appel, quand un fichier suffit (un PDF de vente)") => \main\DropAiUi::open('', 'util-domain-commercialisation')
				.'<div>'.\main\DropAiUi::getList($row($icon('file-pdf', 'commercialisation'), 'facture-restaurant-les-tilleuls-octobre.pdf', $waiting, TRUE)).'</div>'
				.$foot(s("1 fichier"), s("Lire le fichier")).'</div>',
			s("Un justificatif en cours, photographié page par page (util-title, util-previews)") => \main\DropAiUi::open('')
				.\main\DropAiUi::getList($row($icon('file-pdf'), 'facture-octobre.pdf', $deposited))
				.'<div class="util-title mt-1"><h3>'.s("Justificatif en cours").'</h3></div>'
				.'<div class="util-previews">'.implode('', array_map(fn(int $page) => '<div class="util-previews-item"><div class="util-preview util-preview-photo">'.\Asset::image('main', 'producers.webp').'</div><span>'.s("Page {value}", $page).'</span></div>', [1, 2, 3])).'</div>'
				.$foot(s("3 pages"), s("Terminer le justificatif")).'</div>',
			s("Sans pied, quand le panneau termine") => \main\DropAiUi::open('')
				.'<div>'.\main\DropAiUi::getHead(s("Glisser la facture ou le ticket de cette opération ici"), '', s("Choisir un fichier"), '', FALSE).\main\DropAiUi::getList($row($icon('image'), 'IMG_2110.jpg', $pending)).'</div></div>',
		];

		foreach($variants as $label => $demo) {
			$h .= '<p class="util-annotation mt-2 mb-1">'.$label.'</p>';
			$h .= $demo;
		}

		return $h;

	}

	public function colors(): string {

		$variables = $this->extractRootCssVariables();

		$h = '<p>'.s("Les couleurs que pose le :root de reboot.css, à écrire par leur variable, jamais par leur valeur.").'</p>';

		$h .= '<div class="design-columns">';
			foreach($variables as $variable => $value) {
				if(
					str_starts_with($variable, '--color') or
					str_ends_with($variable, '-on-light') or
					str_starts_with($value, '#')
				) {
					$h .= '<div>';
						$h .= '<div style="background-color: var('.$variable.'); height: 3rem; border-radius: var(--radius); border: 1px solid #0001; margin-bottom: 0.5rem;"></div>';
						$h .= '<div><strong>'.$variable.'</strong><br><span class="util-annotation">'.$value.'</span></div>';
					$h .= '</div>';
				}
			}
		$h .= '</div>';

		return $h;
	}

	public function limeRadiusGradient(): string {

		$variables = $this->extractRootCssVariables();

		$h = '<div class="design-pair design-pair-lg">';

			$h .= '<div>';
				$h .= '<h4><code>--radius</code> <span class="util-annotation">'.$variables['--radius'].'</span></h4>';
				$h .= '<div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">';
					$h .= '<div style="background: var(--secondary); color: white; padding: 0.75rem 1rem; border-radius: var(--radius);">var(--radius)</div>';
					$h .= '<div style="background: var(--secondary); color: white; padding: 0.75rem 1rem; border-radius: 0;">sans radius</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div>';
				$h .= '<h4><code>--gradient</code> <span class="util-annotation">'.$variables['--gradient'].'</span></h4>';
				$h .= '<p>'.s("Une ombre douce en surimpression.").'</p>';
				$h .= '<div style="height: 4rem; background: var(--gradient); border: 1px solid var(--border); border-radius: var(--radius);"></div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function spaces(): string {

		$variables = $this->extractRootCssVariables();

		$h = '<p>'.s("Une marge, un remplissage ou un écart de grille prend l'un de ces sept pas, par son token.").'</p>';
		$h .= '<p class="util-annotation">'.s("On écrit var(--space-md), jamais 1rem, et jamais une valeur entre deux pas comme 1.25rem ou 0.4rem. Un écart qui ne tombe sur aucun pas se ramène au plus proche. Les noms suivent ceux des largeurs d'écran, de xxs à xxl. Le test dev/test package=design file=SpaceScaleSentinelAi déclare, feuille par feuille, les valeurs en rem qui sont hors de l'échelle, en nombre qui ne peut que baisser. Une valeur déjà sur l'échelle et écrite en rem passe au token sur une ligne qu'on réécrit, sans remplacement en masse.").'</p>';

		$h .= '<div class="design-spaces">';
			foreach($variables as $variable => $value) {
				if(str_starts_with($variable, '--space-')) {
					$h .= '<code>'.$variable.'</code>';
					$h .= '<span class="util-annotation">'.$value.'</span>';
					$h .= '<div><div class="design-space-bar" style="width: var('.$variable.');"></div></div>';
				}
			}
		$h .= '</div>';

		return $h;

	}

	public function limeBtnSizes(): string {

		$sizes = [
			'btn-xxs' => s("1,75 rem"),
			'btn-xs' => s("1,75 rem"),
			'btn-sm' => s("2 rem"),
			'' => '--pill-height',
			'btn-lg' => s("2,75 rem"),
			'btn-xl' => s("2,75 rem"),
		];

		$h = '<h4 class="mt-2">'.s("Les tailles").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un bouton a quatre hauteurs, 1,75 rem en btn-xxs et btn-xs, 2 rem en btn-sm, celle d'une pastille par défaut et 2,75 rem en btn-lg et btn-xl.").'</p>';
		$h .= '<p class="util-annotation">'.s("Entre btn-xxs et btn-xs, et entre btn-lg et btn-xl, seule la taille du texte change. Un libellé prend 0,75em de marge sur les côtés, et un libellé sur deux lignes grandit le bouton au lieu d'être coupé. Un bouton qui n'a qu'une icône porte btn-only-icon, qui lui rend une marge égale sur les quatre côtés : il fait un carré de la hauteur du bouton. btn-badge, btn-number, btn-outline-number et les pastilles de document d'une vente (sale-document) gardent leurs marges propres (component.css).").'</p>';

		$h .= '<div class="design-buttons">';

			$h .= '<span class="util-annotation">'.s("Classe").'</span>';
			$h .= '<span class="util-annotation">'.s("Libellé").'</span>';
			$h .= '<span class="util-annotation">'.s("Icône seule").'</span>';

			foreach($sizes as $class => $height) {
				$h .= ($class === '' ? '<span>'.s("par défaut").'</span>' : '<code>'.$class.'</code>');
				$h .= '<span><a href="#" class="btn btn-primary '.$class.'">'.$height.'</a></span>';
				$h .= '<span><a href="#" class="btn btn-only-icon btn-color-primary '.$class.'">'.\Asset::icon('gear-fill').'</a></span>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("Un btn-only-icon sans couleur n'a déjà ni fond ni bordure, comme les croix de fermeture. Avec btn-bare, l'icône gris neutre passe au primaire au survol, sans voile, et la cible prend la taille d'un doigt. Ce sont les chevrons qui encadrent la pastille du jour ouvert dans les récoltes de la semaine, où l'icône prend 1,5 rem.").'</p>';
		$h .= '<div class="design-convention-row">';
			$h .= '<a href="#" class="btn btn-only-icon btn-bare">'.\Asset::icon('chevron-left').'</a>';
			$h .= \util\DateUi::calendarWeekday('2026-10-22', selected: TRUE, large: TRUE);
			$h .= '<a href="#" class="btn btn-only-icon btn-bare">'.\Asset::icon('chevron-right').'</a>';
			$h .= '<a class="btn btn-only-icon btn-bare disabled">'.\Asset::icon('chevron-right').'</a>';
		$h .= '</div>';

		return $h;

	}

	public function messages(): string {

		// Ces messages ont migré dans component.css, que l'extracteur ne lit pas : ils se
		// déclarent donc à la main. util-info porte la spirale des illustrations friends,
		// posée en masque, donc dans la teinte du module qui l'affiche.
		$h = '<div class="util-info">'.s("util-info accompagne un champ ou une section, c'est une note et pas un encadré. Son texte peut y être écrit nu...").'</div>';
		$h .= '<div class="util-info">';
			$h .= '<p>'.s("...ou découpé en paragraphes, la spirale marquant alors le bloc et non chaque ligne.").'</p>';
			$h .= '<p>'.s("Deux notes qui se suivent restent deux notes : c'est ce que la pastille ronde qu'elle remplace laissait lire comme une liste à puces.").'</p>';
		$h .= '</div>';
		$h .= '<h4 class="mt-2">util-success, util-warning, util-danger</h4>';
		$h .= '<p class="util-annotation">'.s("Un message d'une ligne se pose dans le flux d'un formulaire ou d'une liste, sur le fond clair de sa teinte et dans son encre foncée. L'icône de la teinte vient de la feuille, elle ne s'écrit pas dans le balisage. Aucun ne s'imprime.").'</p>';
		$h .= '<div class="util-success">'.s("util-success. Vos écritures de TVA sont cohérentes avec vos écritures de ventes.").'</div>';
		$h .= '<div class="util-warning">'.s("util-warning. Cette vente est déjà facturée, la modifier ne change pas la facture. <link>Voir la facture</link>", ['link' => '<a href="#">']).'</div>';
		$h .= '<div class="util-danger">'.s("util-danger. Vous êtes en retard. Enregistrez votre déclaration de TVA avant le 24 octobre, faute de quoi l'administration fiscale peut appliquer une majoration de 10 % sur le montant dû.").'</div>';

		return $h;

	}

	public function contentBlocks(): string {

		$h = '<div class="design-pair">';
		foreach(['util-block', 'util-block util-block-dark bg-primary', 'util-block util-block-round'] as $class) {
			$h .= '<div class="'.$class.'">'.$class.'</div>';
		}
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un tableau dans un bloc").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un tableau posé dans un util-block, directement ou dans un util-overflow, rend le retrait du bloc et le reprend dans ses cellules du bord. Le texte du tableau reste ainsi à un rem du contour sur les quatre côtés, aligné sur le titre, et ses rayures vont d'un bord à l'autre.").'</p>';
		$h .= '<div id="design-block-table" class="util-block">';
			$h .= '<h3>'.s("Planches de la série").'</h3>';
			$h .= '<div class="util-overflow-sm">';
				$h .= '<table class="tr-even">';
					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.s("Planche").'</th>';
							$h .= '<th class="text-end">'.s("Longueur").'</th>';
							$h .= '<th class="text-end">'.s("Surface").'</th>';
						$h .= '</tr>';
					$h .= '</thead>';
					$h .= '<tbody>';
						foreach([['e1', 25, 30], ['e2', 25, 30], ['e3', 20, 24]] as [$bed, $length, $area]) {
							$h .= '<tr>';
								$h .= '<td>'.$bed.'</td>';
								$h .= '<td class="text-end">'.s("{value} mL", $length).'</td>';
								$h .= '<td class="text-end">'.s("{value} m²", $area).'</td>';
							$h .= '</tr>';
						}
					$h .= '</tbody>';
				$h .= '</table>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un chemin secondaire").'</h4>';
		$h .= '<div class="util-block-optional">';
			$h .= '<h3>util-block-optional</h3>';
			$h .= '<p>'.s("Un chemin secondaire, à côté du chemin principal : le pointillé dit qu'on peut passer sans s'y arrêter. Employé pour créer une écriture à la main quand l'import bancaire ne l'a pas trouvée.").'</p>';
			$h .= '<a href="#" class="btn btn-outline-primary">'.s("Créer l'écriture").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function tintedBlocks(): string {

		$h = '<p class="util-annotation">'.s("Un bloc à teinte porte un texte, un titre h4 et une action parfois : util-block-info annonce ou invite à agir dans la teinte de l'univers, util-block-success dit qu'un contrôle est passé, util-block-warning montre une conséquence avant de valider, util-block-danger un échec qui bloque la suite. Un bouton sans variante prend la teinte du bloc, un bouton qui a la sienne la garde.").'</p>';

		$h .= '<div class="util-block-info">';
			$h .= '<h4>util-block-info</h4>';
			$h .= '<p>'.s("Les ventes non payées à régler par virement peuvent maintenant être facturées.").'</p>';
			$h .= '<a href="#" class="btn">'.s("Générer les factures").'</a>';
		$h .= '</div>';

		$h .= '<div class="util-block-success">';
			$h .= '<h4>util-block-success</h4>';
			$h .= '<p>'.s("Les écritures comptables ont bien été créées au moment de la comptabilisation.").'</p>';
		$h .= '</div>';

		$h .= '<div class="util-block-warning">';
			$h .= '<h4>util-block-warning</h4>';
			$h .= '<p>'.s("Le remplacement supprime les 3 immobilisations déjà importées.").'</p>';
			$h .= '<a href="#" class="btn btn-outline-primary">'.s("Remplacer quand même").'</a>';
		$h .= '</div>';

		$h .= '<div class="util-block-danger">';
			$h .= '<h4>util-block-danger</h4>';
			$h .= '<p>'.s("Votre fichier n'a pu être importé. Consultez la <link>documentation</link>.", ['link' => '<a href="#">']).'</p>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Sans titre").'</h4>';
		$h .= '<div class="util-block-warning">'.s("util-block-warning sans titre, pour une conséquence qui tient en une phrase.").'</div>';

		$h .= '<h4 class="mt-2">'.s("Avec des actions et une croix").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec des actions, util-block-info passe en ligne, le titre et son explication à gauche, les boutons poussés à droite, et sous 768 px les boutons descendent sous le texte. C'est la forme d'un message qui appelle une suite, comme l'arrivée sur un écran qu'un parcours vient de configurer ou une fiche fusionnée qui mène à celle conservée. La croix, facultative, le ferme. util-block-warning et util-block-danger prennent la même forme quand l'alerte appelle un geste.").'</p>';
		$h .= '<div class="util-block-info">';
			$h .= \Asset::icon('check-lg', ['class' => 'util-block-icon']);
			$h .= '<div class="util-block-info-text"><div class="util-block-info-title">'.s("Tout est prêt : 1 élément, 12 sous-éléments.").'</div><div class="util-block-info-help">'.s("Tout se modifie d'un clic ici. Et maintenant ?").'</div></div>';
			$h .= '<div class="util-block-info-actions"><a class="btn btn-secondary">'.s("Première action").'</a><a class="btn btn-outline-primary">'.s("Autre action").'</a><a class="util-block-info-close" title="'.s("Fermer").'">'.\Asset::icon('x-lg').'</a></div>';
		$h .= '</div>';

		$h .= '<div class="util-block-warning">';
			$h .= '<div class="util-block-info-text"><div class="util-block-info-title">'.s("3 immobilisations seront remplacées.").'</div><div class="util-block-info-help">'.s("Elles viennent de l'import précédent.").'</div></div>';
			$h .= '<div class="util-block-info-actions"><a class="btn btn-outline-primary">'.s("Remplacer quand même").'</a></div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Les sous-composants dans leur bloc").'</h4>';
		$h .= '<div class="design-pair">';

			// Chaque sous-composant dans le bloc où il sert vraiment, et non dans un div
			// bricolé : simulé en style inline, il fige un rendu que le composant a quitté.
			$h .= '<div class="util-block-info">';
				$h .= \Asset::icon('bell', ['class' => 'util-block-icon']);
				$h .= s("util-block-icon remplace l'icône de la teinte, posé en enfant direct du bloc.");
			$h .= '</div>';

			// util-block-remove : opacity 0, visible via outline
			$h .= '<div style="outline: 2px dashed var(--muted); border-radius: var(--radius); padding: 0.25rem;">';
				$h .= '<div class="util-block-info util-block-remove">util-block-remove (opacity: 0)</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Les bandeaux collés d'une alerte").'</h4>';
		// util-block-sticked : fixed contenu dans une boîte avec transform. Ce sont les deux
		// bandeaux de Lime.Alert, avec leur croix util-block-close, que seul un bandeau plein porte.
		foreach([
			'util-block-danger' => s("Le numéro de SIRET est invalide."),
			'util-block-success' => s("La vente a bien été enregistrée.")
		] as $class => $message) {
			$h .= '<div style="position: relative; height: 5rem; margin-bottom: 0.5rem; overflow: hidden; transform: translateZ(0); border: 1px solid var(--border); border-radius: var(--radius);">';
				$h .= '<div class="'.$class.' util-block-sticked">'.$message.'<a class="util-block-close">'.\Asset::icon('x-circle').'</a></div>';
			$h .= '</div>';
		}

		return $h;

	}

	public function flash(): string {

		$h = '<p class="util-annotation">'.s("util-toast confirme qu'une action a eu lieu, sans prendre de place dans la page, et laisse quelques secondes pour la défaire quand elle se défait. Elle est posée par <b>Lime.Alert.showToast()</b>, ou depuis une vue par <b>\$t->js()->toast()</b>, jamais écrite à la main : la pile util-toast-stack se crée au premier appel, fixée au coin bas droit, et occupe la largeur de l'écran sous 576 px.").'</p>';
		$h .= '<p class="util-annotation">'.s("Avec <b>from</b>, la pastille part de l'élément qui a déclenché l'action et se déplace jusqu'au coin. util-toast-timer est le temps qui reste pour annuler : il se vide en 6 s et s'arrête au survol comme au focus, et une pastille sans lien d'annulation n'en porte pas. À la fin, la pastille se referme sur une coche (<b>is-done</b>) puis s'éteint. Quand le système demande de réduire les animations, elle apparaît en fondu, sans déplacement ni filet, et reste le même temps. Le détail tient sur deux lignes au plus.").'</p>';
		$h .= '<p class="util-annotation">'.s("La teinte du filet et des icônes est le vert du thème mêlé de blanc à moitié : pur, il tombe à 2,6:1 sur le fond de la pastille, sous le 3:1 d'un élément graphique.").'</p>';

		$vignette = '<svg class="util-toast-icon"><use xlink:href="'.\plant\PlantUi::getVignetteHref('tomate').'"></use></svg>';

		$h .= '<div style="position: relative; height: 14rem; max-width: 30rem; overflow: hidden; transform: translateZ(0); border: 1px solid var(--border); border-radius: var(--radius);">';
			$h .= '<div class="util-toast-stack">';
				$h .= '<div class="util-toast is-done">'.\Asset::icon('check-lg', ['class' => 'util-toast-icon']).'</div>';
				$h .= '<div class="util-toast">';
					$h .= $vignette;
					$h .= '<span class="util-toast-text"><span>'.s("Récolte de 12 kg de tomate annulée. Le stock de Tomate ancienne en vrac n'a pas été modifié.").'</span></span>';
				$h .= '</div>';
				$h .= '<div class="util-toast">';
					$h .= $vignette;
					$h .= '<span class="util-toast-text"><span>'.s("12 kg de tomate ajoutés").'</span><small>'.s("Série Tomate ancienne 2 · s18 · Serre 4 planches 1, 2, 3 et 5 + Serre 7 planches 1, 2 et 3").'</small></span>';
					$h .= '<a href="#">'.s("Annuler").'</a>';
					$h .= '<span class="util-toast-timer" style="transform: scaleX(0.6);"></span>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function inline(): string {

		$h = '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<span class="util-annotation">util-annotation</span>';
			$h .= '<span class="util-helper">util-helper</span>';
			$h .= '<span class="util-empty">util-empty</span>';
			$h .= '<span class="util-unit">12 mL x 80 cm</span> <span class="util-annotation">util-unit</span>';
			$h .= '<span class="util-strikethrough">14,00 €</span> <span class="util-annotation">util-strikethrough</span>';
			$h .= '<span class="util-circle">★</span> <span class="util-annotation">util-circle</span>';
			$h .= '<span class="util-circle util-circle-lg">★</span> <span class="util-annotation">util-circle-lg</span>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">util-annotation-long</h4>';
		$h .= '<p class="util-annotation">'.s("util-annotation-long, posé avec util-annotation, garde sa taille et passe au gris des textes secondaires avec un interligne de 1,4 : util-annotation est taillé pour une ligne, et ses lignes se serrent dès la deuxième. L'écart avec ce qui précède se pose sur le conteneur, pas sur l'annotation.").'</p>';
		$h .= '<div class="util-block">';
			$h .= '<b>'.s("Claire Moreau").'</b>';
			$h .= '<p class="util-annotation util-annotation-long mb-0">'.s("Maraîchère en agriculture biologique depuis 2012 sur deux hectares, elle accompagne les installations en sol vivant et anime les journées techniques du groupement. Elle a conduit l'essai de couverts permanents suivi par la chambre d'agriculture pendant quatre saisons.").'</p>';
		$h .= '</div>';

		return $h;

	}

	public function emptyScreens(): string {

		$h = '<div class="util-block">';
			$h .= '<h4>'.s("Récoltes de la semaine").'</h4>';
			$h .= '<div class="util-empty" style="margin-bottom: 0;">'.s("Aucune récolte").'</div>';
		$h .= '</div>';
		$h .= '<div class="util-block">';
			$h .= '<dl class="util-presentation util-presentation-1">';
				$h .= '<dt>'.s("Tiers").'</dt>';
				$h .= '<dd>'.s("Emballages Ravel").'</dd>';
				$h .= '<dt>'.s("Date").'</dt>';
				$h .= '<dd><span class="util-empty">'.s("À compléter").'</span></dd>';
			$h .= '</dl>';
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Trois formes, selon ce qui est vide. Une zone entière, liste, panneau ou recherche sans résultat, prend util-block-empty, qui porte son propre titre. Un bloc qui a déjà son titre et rien dessous prend util-empty en bloc, avec sa silhouette. Une valeur qui manque au fil d'un texte ou d'une cellule prend util-empty sur un span, qui reste en ligne, et perd dans une util-presentation le gras de la valeur, pour ne pas se lire comme une valeur.").'</p>';

		return $h;

	}

	public function presentationDetail(): string {

		$h = '<h4 class="mt-2">'.s("Une valeur et son complément").'</h4>';
		$h .= '<div class="util-block">';
			$h .= '<dl class="util-presentation util-presentation-1">';
				$h .= '<dt>'.s("Capital emprunté").'</dt>';
				$h .= '<dd>'.s("21 000,00 €").'<span class="util-presentation-detail">'.s(", débloqué le 15/04/2022").'</span></dd>';
				$h .= '<dt>'.s("Taux").'</dt>';
				$h .= '<dd>'.s("0,90 %").'<span class="util-presentation-detail">'.s(", échéances constantes").'</span></dd>';
			$h .= '</dl>';
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Dans une util-presentation, la valeur est en gras. Ce qui la précise à sa suite, une date, un mode, un libellé de compte, se pose dans un span util-presentation-detail, qui garde le poids du texte. Employé par la fiche d'un emprunt.").'</p>';

		return $h;

	}

	public function highlight(): string {

		$h = '<div class="util-block">';
			$h .= '<p>'.s("util-mark se pose sur n'importe quel élément inline, au milieu d'un texte courant : <mark>quelques mots surlignés</mark> valent mieux qu'un paragraphe entier en gras, où plus rien n'est important. Il suit les retours à la ligne sans se casser.", ['mark' => '<span class="util-mark">']).'</p>';
			$h .= '<p style="margin-bottom: 0;">'.s("Cumulé avec du gras, pour <mark>appuyer davantage</mark>.", ['mark' => '<b class="util-mark">']).'</p>';
		$h .= '</div>';

		return $h;

	}

	public function collapsible(): string {

		$h = '<p class="util-annotation">'.s("util-fold replie une section par son titre, sans cadre : util-fold-head porte le h3, son util-counter et util-fold-chevron, et ouvre ou ferme util-fold-content, dont le contenu se pose sur le fond de la page. L'écran bascule util-fold-open sur util-fold. La tête peut aussi se poser dans le util-title de la section, à droite de son titre, quand c'est un chiffre qui déplie son détail (le total du temps de la page d'une intervention, TaskDetailUi::getTotal()). Pour replier l'intérieur d'une carte, c'est util-deck-fold.").'</p>';
		foreach([[s("En retard"), 5, TRUE], [s("Non planifié"), 9, FALSE]] as [$title, $count, $open]) {
			$h .= '<div class="util-fold'.($open ? ' util-fold-open' : '').'">';
				$h .= '<a class="util-fold-head" '.attr('onclick', 'this.closest(".util-fold").classList.toggle("util-fold-open")').'>';
					$h .= '<h3>'.$title.' <span class="util-counter">'.$count.'</span></h3>';
					$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
				$h .= '</a>';
				$h .= '<div class="util-fold-content">';
					$h .= '<div class="util-cards util-cards-2 util-cards-start mb-2">';
						$h .= '<div class="util-deck"><div class="util-deck-head"><div class="util-deck-body"><div class="util-deck-title">'.s("Une carte").'</div><p>'.s("posée sur le fond de la page").'</p></div></div></div>';
						$h .= '<div class="util-deck"><div class="util-deck-head"><div class="util-deck-body"><div class="util-deck-title">'.s("Une autre").'</div><p>'.s("sans cadre autour de la section").'</p></div></div></div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		}

		return $h;

	}

	public function actionBars(): string {

		$h = '<div class="util-title"><h2>'.s("Titre de section").'</h2><a href="#" class="btn btn-outline-primary">'.s("Action").'</a></div>';
		$h .= '<div class="util-action"><h3>util-action</h3><a href="#" class="btn btn-sm btn-outline-primary">'.s("Modifier").'</a></div>';
		$h .= '<div class="util-title"><h3>'.s("Contrats").' <span class="util-counter">14</span></h3><a href="#" class="btn btn-outline-primary">'.s("Nouveau contrat").'</a></div>';
		$h .= '<h4 class="mt-2">'.s("Un titre de page long et ses gestes").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-action-fold, posé avec util-action (en tête de la page d'une formation, par exemple), garde les gestes à droite de la première ligne d'un titre de page qui se replie. Le titre et sa ligne de contexte vont dans le premier enfant, les gestes dans util-action-fold-aside, une boite haute d'au moins une ligne du h1. Le premier enfant part de la moitié de la largeur de la rangée (flex-basis 50 %), les gestes de leur largeur propre : quand ils ne tiennent pas dans l'autre moitié, écart compris, ils passent sous le titre, à gauche, et le titre reprend toute la largeur. Ils vont aussi à la ligne entre eux plutôt que de sortir de l'écran. Sous 576 px, les gestes passent toujours sous le titre. Il ne vaut que pour le h1 d'une page dont le gabarit porte le mot base dans data-template (body[data-template~=&quot;base&quot;], template-base.css), qui déclare --h1-size. Un autre gabarit ou un autre titre le désalignerait.").'</p>';
		$h .= '<div class="util-action util-action-fold">';
			$h .= '<div>';
				$h .= '<h1>'.s("Un titre de page assez long pour se replier sur deux lignes").'</h1>';
				$h .= '<p class="mb-0"><span class="badge badge-draft">'.s("Brouillon").'</span> <span class="util-annotation">'.s("Ce qui situe l'objet").'</span></p>';
			$h .= '</div>';
			$h .= '<div class="flex-buttons util-action-fold-aside">';
				$h .= '<a href="#" class="btn btn-primary">'.s("Publier").'</a>';
				$h .= \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>');
			$h .= '</div>';
		$h .= '</div>';
		$h .= '<div class="util-action util-action-fold mt-2">';
			$h .= '<div>';
				$h .= '<h1>'.s("Un titre court").'</h1>';
			$h .= '</div>';
			$h .= '<div class="flex-buttons util-action-fold-aside">';
				$h .= '<a href="#" class="btn btn-primary">'.s("Personnaliser l'apparence de la page publique").'</a>';
				$h .= '<a href="#" class="btn btn-outline-primary">'.s("Gérer les inscriptions et les dates de session").'</a>';
			$h .= '</div>';
		$h .= '</div>';
		$h .= '<h4 class="mt-2">'.s("Un sous-titre de section").'</h4>';
		$h .= '<div class="util-subtitle">'.s("Sous-titre de section").'</div>';

		return $h;

	}

	public function pageHeader(): string {

		$h = '<p class="util-annotation">'.s("util-page-header est l'en-tête d'un écran, et rien d'autre. Il se pose par \\main\\DesignUi::getPageHeader(), qui émet le h1, l'enveloppe dans sa bande util-page-band et reçoit tout le reste déjà rendu. Le titre vit dans util-page-header-main, ce qui situe l'écran dans util-page-header-context, et les gestes dans util-page-header-aside. C'est la seule forme d'un en-tête d'écran de structure : plus de h1 écrit à la main, plus de util-action ni de util-title dans mainTitle. La bande n'a pas de fond, elle se pose sur celui de la tête, le même que celui du contenu, et l'écran ne lui pose ni couleur, ni taille, ni marge. Le titre ne porte que le nom de ce qu'on regarde et son compteur, jamais un menu, un bouton ou une date. Sur une page à onglets, ce qu'on regarde est la rubrique : le titre garde son nom, celui du menu, quel que soit l'onglet ouvert, et chaque compteur va dans son onglet, jamais dans le titre. La marque d'état passe par state, à côté du titre. La ligne de contexte, par context, en donne les bornes, dates ou rattachement. La saison passe par season, qui la pose dans util-page-header-season, après le titre et son disque des pages, en jeton util-chip rendu par new \\main\\DesignUi()->getSeason(), qui prend la forme des boutons de la tête et liste les saisons de la plus récente à la plus ancienne, les gestes qui en ajoutent sous un séparateur ; une saison seule, qui ne se choisit pas, s'écrit en texte à la couleur de l'univers. Un exercice comptable passe aussi par season, dans le même jeton, et l'argument title de getSeason() renomme alors la liste « Changer d'exercice ». Le territoire de l'outil des aides y passe de même, sous « Changer de territoire », et ses noms sont les saisons les plus longues, « Alpes-de-Haute-Provence » ou « Territoire de Belfort ». Quand le titre et la saison ne tiennent pas côte à côte, la saison passe sous le titre, qui garde sa ligne, et sous 400 px les gestes restent à côté du titre tant que la saison y tiendrait aussi. À côté d'un disque des pages, la saison reste sur la ligne du titre, qui s'enroule seul. Les chiffres clés de l'écran ne vont pas dans la bande : ils ouvrent la feuille, en tuiles util-figures. Les onglets ne vont jamais dans la bande, ils ouvrent le contenu juste dessous. La recherche de l'écran passe par search, dans util-page-header-search, décrite avec l'omnibox.").'</p>';
		$h .= '<p class="util-annotation">'.s("Les gestes arrivent par trois arguments que la méthode range toujours dans le même ordre, de gauche à droite. tools reçoit les outils en btn-outline-secondary, sans title, qu'un écran tactile n'affiche pas : l'icône seule quand elle se comprend sans texte, comme la loupe, les chevrons, le calendrier ou la croix, un libellé en hide-xs-down quand l'icône reste lisible seule sous 576 px, comme le PDF, et un libellé visible à toutes les largeurs dès que l'icône est ambiguë, comme l'aide. create reçoit la création en btn-secondary, settings la roue crantée en btn-color-primary, qui reste la dernière et porte, comme tout menu déroulant, sa flèche. Tous les boutons de la tête ont un format à part de ceux du site, et la couleur de leur classe, dessinée comme dans le reste de l'écran. L'icône seule fait un disque, le libellé allonge le même bouton en pastille. La loupe prend la teinte de son contour, et se remplit tant que la recherche est ouverte. Le menu des pages voisines, qui arrive par pages et est rendu par new \\main\\DesignUi()->getPages(), est un disque à chevron qui suit le titre et son compteur, avant la marque d'état, sans entrer dans le h1. En btn-outline-primary, il a la forme et la cible tactile du disque de retour, dont il partage les règles de format, et s'en distingue par son chevron. Il ouvre une liste claire dropdown-list-light qui nomme toujours la page où l'on est, en selected. Le titre et le disque partagent la rangée util-page-header-title, où le disque reste au bout de la dernière ligne du titre, et il se centre dans util-page-header-pages, haute d'une ligne du titre. Un nom long s'enroule à côté du disque, qui reste au bout de sa dernière ligne et ne tombe jamais seul dessous. Les libellés sont encodés, sauf si l'appelant passe html à TRUE et encode lui-même la part saisie. Le retour passe par back, qui ne reçoit que l'adresse de la page parente, et se rend en disque util-page-header-back devant le titre, dessiné comme le disque des pages et à la hauteur des boutons voisins, sans title ni libellé, la flèche se comprenant seule. Il reste visible à toutes les largeurs, et sous 576 px sa cible s'étend jusqu'à la taille d'un doigt sans que le disque grossisse. back à NULL rend un retour par l'historique, et la chaîne vide n'en rend aucun. Le logo, le contexte et les gestes n'ouvrent leur boîte que s'ils ont du contenu, et sous 400 px, sans logo, les gestes passent sur leur propre rang, à droite, plutôt que de laisser le titre s'enrouler sous eux. Sans recherche et à partir de trois gestes, ce régime vaut jusqu'à 767 px, les gestes restant à côté du titre tant qu'ils y tiennent. Avec une recherche, entre 400 et 575 px, la ligne de contexte passe sous eux à partir de trois gestes. La forme se contrôle par ouvretaferme/design/test/PageHeaderSentinelAi.t.php.").'</p>';
		$h .= \main\DesignUi::getPageHeader(
			s("Tomates anciennes greffées sous la grande serre, deuxième série de printemps").' <span class="util-counter">12</span>',
			context: s("Planche 4 de la serre 2, plantée le 14 avril"),
			state: '<span class="badge badge-draft">'.s("Brouillon").'</span>',
			back: '#',
			tools: '<a href="#" class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>',
			create: \main\DesignUi::getCreateButton('#', s("Nouvelle intervention")),
			pages: new \main\DesignUi()->getPages([
				['url' => '#', 'label' => s("Planches"), 'selected' => TRUE],
				['url' => '#', 'label' => s("Plan de culture"), 'selected' => FALSE]
			]),
			settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>'),
			season: new \main\DesignUi()->getSeason(s("Saison 2026"), [
				['url' => '#', 'label' => s("Saison 2026"), 'selected' => TRUE],
				['url' => '#', 'label' => s("Saison 2025"), 'selected' => FALSE]
			], '<a href="#" class="dropdown-item">'.s("Ajouter la saison 2027").'</a>')
		);
		$h .= '<h4 class="mt-2">'.s("Un titre court sur un téléphone").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un titre court tient sur une ligne, plus basse sur un téléphone que le jeton de saison ou le disque des pages : leur rangée prend alors la hauteur du bouton, et ni la ligne de contexte ni les gestes ne le touchent.").'</p>';
		$h .= \main\DesignUi::getPageHeader(
			s("Récoltes"),
			s("Du lundi 6 au dimanche 12 octobre 2026"),
			tools: '<a href="#" class="btn btn-outline-primary">'.\Asset::icon('check2-square').' '.s("Sélectionner").'</a>',
			settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a>'),
			season: new \main\DesignUi()->getSeason(s("Saison 2026"), [
				['url' => '#', 'label' => s("Saison 2026"), 'selected' => TRUE],
				['url' => '#', 'label' => s("Saison 2025"), 'selected' => FALSE]
			])
		);
		$h .= \main\DesignUi::getPageHeader(
			s("Analyses financières"),
			season: new \main\DesignUi()->getSeason(s("Exercice 2025 - 2026"), [
				['url' => '#', 'label' => s("Exercice 2025 - 2026"), 'selected' => TRUE],
				['url' => '#', 'label' => s("Exercice 2024 - 2025"), 'selected' => FALSE]
			], title: s("Changer d'exercice"))
		);
		$h .= \main\DesignUi::getPageHeader(
			s("Aides agricoles"),
			settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a>'),
			season: new \main\DesignUi()->getSeason(s("Alpes-de-Haute-Provence"), [
				['url' => '#', 'label' => s("Hautes-Alpes (05)"), 'selected' => FALSE],
				['url' => '#', 'label' => s("Toute la France"), 'selected' => FALSE],
				['url' => '#', 'label' => s("Alpes-de-Haute-Provence (04)"), 'selected' => TRUE]
			], title: s("Changer de territoire"))
		);
		$h .= '<h4 class="mt-2">'.s("Les pages voisines tirées des catégories").'</h4>';
		$h .= '<p class="util-annotation">'.s("Quand les pages voisines sont les catégories d'une même rubrique, la liste se construit par new \\main\\DesignUi()->getPagesFromCategories(), qui reçoit les catégories (clé, libellé), une closure qui rend l'adresse d'une clé et la clé choisie. Une catégorie NULL devient un séparateur, et html suit la même règle que pour getPages().").'</p>';
		$h .= \main\DesignUi::getPageHeader(
			s("Espèces cultivées"),
			pages: new \main\DesignUi()->getPagesFromCategories([
				'area' => s("Surfaces cultivées"),
				'plant' => s("Espèces cultivées"),
				NULL,
				'rotation' => s("Rotations")
			], fn(string $key) => '#'.$key, 'plant'),
			season: s("Saison 2026")
		);
		$h .= '<h4 class="mt-2">'.s("Avec un logo").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec un logo, la méthode ajoute util-page-header-identity et pose ce qu'elle a reçu dans util-page-header-logo, qui lui donne la colonne de gauche sans rien dire de sa taille : elle vient de l'appelant, par \\structure\\StructureUi::getLogo() ou par la caméra du logo d'une boutique, et vaut 3rem, celle de la fiche d'un membre, sauf la couverture d'une formation, plus large. Sous 576 px, un geste seul reste à droite du titre et la ligne de contexte reprend la largeur sous les deux ; sous 400 px, deux gestes ou plus passent au rang suivant, calés à droite. Sans ligne de contexte, le titre se cale en haut du logo et non en son milieu, le rang du contexte absorbant l'excédent de hauteur du logo. La bascule en rangée souple que la grille simple prend sous 400 px ne la touche pas, une aire qui enjambe deux rangs n'ayant pas d'équivalent en flex.").'</p>';
		$h .= \main\DesignUi::getPageHeader(
			s("Les Paniers du Coteau"),
			context: s("Tous les jeudis, de 18h à 20h"),
			logo: '<div style="width: 3rem; height: 3rem; border-radius: var(--radius); background: var(--background);"></div>',
			settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>')
		);
		$h .= \main\DesignUi::getPageHeader(
			s("Conduite d'une association de producteurs en circuit court"),
			context: s("Formation en ligne, 12 leçons"),
			logo: '<div style="width: 3rem; height: 3rem; border-radius: var(--radius); background: var(--background);"></div>',
			create: \main\DesignUi::getCreateButton('#', s("Nouvelle leçon")),
			settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>')
		);
		$h .= '<h4 class="mt-2">'.s("Un nom long, un logo et des pages voisines").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec un nom de boutique long, un logo et des pages voisines, le nom passe sous 576 px à la ligne à côté du disque, qui reste à droite de sa dernière ligne.").'</p>';
		$h .= \main\DesignUi::getPageHeader(
			s("Marché de producteurs du samedi au bourg"),
			logo: '<div style="width: 3rem; height: 3rem; border-radius: var(--radius); background: var(--background);"></div>',
			state: '<span class="btn btn-badge btn-sm btn-readonly badge badge-danger badge-dot">'.s("Fermée").'</span>',
			pages: new \main\DesignUi()->getPages([
				['title' => s("Les boutiques")],
				['url' => '#', 'label' => s("Marché de producteurs du samedi au bourg"), 'selected' => TRUE],
				['url' => '#', 'label' => s("Livraison à domicile"), 'selected' => FALSE]
			]),
			settings: \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>')
		);

		$h .= '<h4 class="mt-2">'.s("Une page à onglets").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une page à onglets garde en titre le nom de sa rubrique, et chaque onglet porte son compteur.").'</p>';
		$h .= '<div id="convention-titre-rubrique">';
			$h .= \main\DesignUi::getPageHeader(
				s("E-mailing"),
				create: \main\DesignUi::getCreateButton('#', s("Nouvelle campagne"))
			);
			$h .= '<div class="tabs">';
				$h .= '<div class="tabs-item">';
					$h .= '<a class="tab-item selected">'.s("Contacts").' <span class="util-counter util-counter-solid">573</span></a>';
					$h .= '<a class="tab-item">'.s("Campagnes").' <span class="util-counter util-counter-solid">12</span></a>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une icône par geste").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un même geste porte la même icône dans tout le produit. Un outil qui ouvre dans un autre onglet une page que voit le public, comme le formulaire d'adhésion d'une AMAP, porte box-arrow-up-right. link-45deg reste au lien entre deux données, comme util-follow ou l'ancre d'un titre de documentation. Un PDF porte file-pdf, la seule icône de PDF du produit, la famille file-earmark restant aux tables de types de fichier.").'</p>';
		$h .= '<div id="convention-icone-outil">';
			$h .= \main\DesignUi::getPageHeader(
				s("Demandes d'adhésion"),
				tools: '<a href="#" target="_blank" class="btn btn-outline-secondary">'.\Asset::icon('box-arrow-up-right').'<span> '.s("Le formulaire public").'</span></a>'.
					'<a href="#" class="btn btn-outline-secondary">'.\main\DesignUi::getGestureLabel('file-pdf', s("PDF")).'</a>'
			);
		$h .= '</div>';

		return $h;

	}

	public function start(): string {

		$h = '<p class="util-annotation">'.s("new \\main\\DesignUi()->getStart() rend util-start. L'argument buttons porte le geste qui la lance, l'argument image une illustration dans l'angle haut droit. La bande ne sert pas à tout ce qui se pose en tête d'un écran. Un geste proposé au-dessus d'un écran déjà rempli prend util-block-info, une liste vide util-block-empty, un geste qui vient d'aboutir util-block-success. Elle reste simple, comme ici, pour un état qui gouverne la page, une étape d'un parcours ou une annonce avec cachet, et porte ses gains pour une fonctionnalité jamais nourrie.").'</p>';
		$h .= '<p class="util-annotation">'.s("La bande prend le fond blanc menthe, bordé en haut et en bas d'un coup de pinceau. Enfant direct d'un container, elle va d'un bord à l'autre de l'écran, comme les bandes du tableau de bord. Ailleurs, dans un panneau, un formulaire ou un bloc, elle reste dans la colonne, comme ici. Sur les écrans d'une structure, elle tient dans la colonne, blanche, bordée sur ses quatre côtés d'un trait brossé de la couleur foncée de l'univers et inclinée d'un demi-degré. Elle prend la même forme sur les écrans d'un groupe, d'un réseau ou d'une AMAP, sous leur bande claire, bordée de la couleur de leur bandeau.").'</p>';

		$h .= new \main\DesignUi()->getStart(
			\Asset::icon('star-fill'),
			s("Titre de la fonctionnalité"),
			'<p>'.s("Texte d'introduction, en une ou deux phrases.").'</p>',
			buttons: '<a href="#" class="btn btn-primary">'.s("Commencer").'</a>'
		);

		$h .= '<h4 class="mt-2">'.s("Le cachet util-start-upcoming").'</h4>';
		$h .= '<p class="util-annotation">'.s("La même méthode annonce une fonctionnalité pas encore ouverte avec l'argument upcoming, qui reçoit tout le contenu du cachet, ici un <b>&lt;b&gt;</b> et un <b>&lt;small&gt;</b>. Le cachet se pose sans rien déplacer autour de lui et occupe l'angle de l'image, les deux ne cohabitent donc pas. Il peut aussi porter un avantage plutôt qu'une date : le titre lui réserve la largeur d'un cachet de deux mots, et sous 576px il descend en pied de carte.").'</p>';

		$h .= new \main\DesignUi()->getStart(
			\Asset::icon('star-fill'),
			s("Fonctionnalité pas encore ouverte"),
			'<p>'.s("Texte d'introduction, sans geste tant que la fonctionnalité n'est pas ouverte.").'</p>',
			upcoming: '<b>'.s("À venir").'</b><small>'.s("1er octobre").'</small>'
		);

		$h .= new \main\DesignUi()->getStart(
			\Asset::icon('star'),
			s("Merci pour votre soutien&nbsp;!"),
			'<p>'.s("Texte qui dit l'avantage et à quoi il s'applique.").'</p>',
			upcoming: '<b>'.s("-20 € HT").'</b><small>'.s("grâce à votre adhésion").'</small>'
		);

		$h .= '<h4 class="mt-2">'.s("Avec ses gains").'</h4>';
		$h .= '<p class="util-annotation">'.s("La bande d'une fonctionnalité que l'utilisateur n'a encore jamais nourrie porte ses gains, que \\main\\DesignUi::getStartGains() rend et qui se concatènent à la phrase dans l'argument content. Elle se réserve à l'écran dont la version remplie montrera au moins deux choses que l'écran vide ne laisse pas deviner, et qu'un geste à la main de l'utilisateur lance. Elle contient, dans cet ordre, le pictogramme et le titre, une seule phrase, le surtitre « Ce que vous y trouverez », deux ou trois gains, le bouton du premier geste, et une illustration dans l'angle. Jamais un seul gain, qui s'étirerait sur toute la bande, jamais quatre, qui passeraient sous la largeur lisible d'une tuile. Un écran qui n'a qu'une chose à promettre garde la bande simple. Le bouton s'écrit btn-xl, que la bande ramène à la taille des gestes de la page sur un écran de structure. Sous 767px l'illustration disparaît et les gains s'empilent sur une colonne. La bande s'allonge alors d'une tuile par gain, et la phrase reste courte.").'</p>';

		$h .= $this->getStartStocks();

		$h .= '<p class="util-annotation">'.s("Un gain s'écrit [pictogramme, titre, précision] et nomme ce que l'écran rempli affichera, lu dans son code avant d'être écrit. Son titre est un groupe nominal, sans verbe à l'impératif ni point. Sa précision le continue en minuscule et sans point final, et les deux se lisent comme une phrase coupée en deux lignes. Une condition se dit dans la précision. Aucune formule qui ne montre rien, comme « en un clic » ou « au même endroit ». Le pictogramme d'un gain montre la chose promise, au trait et sans variante -fill, différent de celui du titre et de celui des autres gains. Celui du titre est le pictogramme de la fonctionnalité, pas celui de sa section de menu. La pastille d'un gain prend la couleur de l'univers de l'écran, et --primary là où rien ne le fixe, comme ici.").'</p>';
		$h .= '<p class="util-annotation">'.s("L'illustration est un ami de main/static/image/friends, passé par \\Asset::image(), jamais une capture d'écran ni un logo, qui ne montrent plus rien à la taille de l'angle. Un ami par fonctionnalité, différent de ceux des écrans voisins du même menu, et le même pour deux écrans d'un même parcours. Un ami très pâle ne se lit pas sur la bande blanche des écrans d'une structure. Les éléments de décor du même dossier, feuilles, pétales, graines et spirale, ne servent pas d'illustration.").'</p>';
		$h .= '<p class="util-annotation">'.s("Entre les gains et le bouton, une seule ligne util-annotation est admise, pour une restriction, une mise en garde ou des gestes secondaires en liens. Deux boutons au plus, le premier plein et le second en contour, et les gestes suivants passent en liens dans la ligne. Une marche à suivre, un « À savoir » ou un second paragraphe sortent de la bande et se posent dessous, en util-procedure ou en util-block-info. La bande ne porte ni liste à puces, ce qui s'énumère devenant des gains, ni cachet upcoming avec une illustration, ni explication du fonctionnement de l'écran.").'</p>';

		$h .= new \main\DesignUi()->getStart(
			\Asset::icon('receipt'),
			s("Les factures"),
			'<p>'.s("Créez une première vente pour pouvoir la facturer.").'</p>'.
			\main\DesignUi::getStartGains([
				['file-earmark-text', s("Les factures de chaque client"), s("avec leur montant et leur date d'échéance")],
				['cash-coin', s("Les règlements reçus"), s("si votre banque est connectée")],
			]).
			'<div class="util-annotation">'.s("Vous n'avez pas encore de client ? <link>Importer un fichier de clients</link>", ['link' => '<a href="#">']).'</div>',
			image: \Asset::image('main', 'friends/poule.svg'),
			buttons: '<div class="flex-buttons"><a href="#" class="btn btn-primary btn-xl">'.s("Créer une vente").'</a><a href="#" class="btn btn-outline-primary btn-xl">'.s("Lire la documentation").'</a></div>'
		);

		return $h;

	}

	public function brushed(): string {

		$h = '<p class="util-annotation">'.s("util-brushed sépare une bande du fond de page par un coup de pinceau en haut et en bas. Elle habille util-start, la bande Vente ou Gestion du tableau de bord et les bandes des pages publiques. Elle porte deux enfants directs, util-separator-top et util-separator-bottom, qui contiennent chacun le SVG svg/brush-separator.svg. La couleur se pose sur la bande par --util-brushed-color, que le fond et le pinceau lisent tous les deux : blanc menthe pour util-start hors de la feuille, la couleur foncée de l'univers pour le bord d'util-start sur la feuille, vert menthe au tableau de bord, et la teinte qu'une page publique choisit pour sa bande, comme le vert amande. Sur la feuille, util-start ne garde que cette couleur, dessine elle-même son contour sur ses quatre côtés et le remplit de blanc. Sans elle, une util-band au pinceau prend le blanc menthe, et toute autre bande garde un fond transparent et un pinceau à la couleur du texte. La bande ne pose ni padding ni marge, et le pinceau en dépasse de 0,5 rem sur mobile et de 1 rem au bureau : l'appelant lui laisse au moins cette marge.").'</p>';
		$h .= '<p class="util-annotation">'.s("La bande ne garde que le pinceau du haut quand l'élément qui la suit pose le sien par-dessus son bord bas, comme le pied de page sous landing-cta. Partout ailleurs, sans pinceau du bas, elle se couperait net sur le fond de page.").'</p>';
		$h .= '<div class="util-brushed design-brushed-demo">';
			$h .= \main\DesignUi::getBrushSeparator('top');
			$h .= '<h4>'.s("Titre de la bande").'</h4>';
			$h .= '<p class="mb-0">'.s("Le contenu de la bande, posé sur son fond.").'</p>';
			$h .= \main\DesignUi::getBrushSeparator('bottom');
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le formulaire de recherche en tête d'une liste. Balisage de
	 * `commercialisation/selling/ui/Invoice.u.php` : 34 listes du produit en portent un.
	 */
	public function searches(): string {

		$h = '<p class="util-annotation">'.s("util-block-search enveloppe le formulaire dans une carte et l'écarte de la liste qu'il filtre. Dans cette carte, util-search range ses champs en grille, autant de colonnes que la largeur en loge, chacun dans un fieldset dont la legend est l'intitulé ; util-search-1, util-search-2 et util-search-3 les rangent en colonnes sous 992px, et util-search-fill donne à un champ toute la largeur qui reste. util-search-submit tient le bouton et la croix qui vide la recherche, sur une rangée à eux, calés à droite.").'</p>';

		$form = new \util\FormUi();

		$h .= '<div class="util-block-search">';
			$h .= $form->openAjax('#', ['method' => 'get', 'class' => 'util-search util-search-3']);
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Numéro").'</legend>';
					$h .= $form->text('name', '', ['placeholder' => s("Numéro")]);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Client").'</legend>';
					$h .= $form->text('customer', '', ['placeholder' => s("Nom du client")]);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("État").'</legend>';
					$h .= $form->select('status', [s("Toutes"), s("Envoyée"), s("Réglée")], NULL);
				$h .= '</fieldset>';
				$h .= $form->searchSubmit('#', TRUE, p("{value} facture", "{value} factures", 12));
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	public function clearable(): string {

		$h = '<p class="util-annotation">'.s("L'attribut clearable, accepté par FormUi::text(), number(), email(), url() et select(), pose une croix input-group-clear à la suite du champ, avant un éventuel append, dans le même input-group. Elle vide le champ et déclenche input puis change, comme une saisie : les filtres branchés sur ces événements se remettent à jour sans code de plus. Elle ne se montre que lorsque le champ porte une valeur.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un select effaçable revient à son option vide : clearable refuse donc un select required ou multiple, qui n'en a pas. Sur un champ texte sans placeholder, FormUi pose un placeholder d'une espace, sans lequel la croix ne saurait pas que le champ est vide.").'</p>';

		$form = new \util\FormUi();

		$h .= '<div class="util-search">';
			$h .= '<fieldset>';
				$h .= '<legend>'.s("Nom de produit").'</legend>';
				$h .= $form->text('name', s("Carotte"), ['placeholder' => s("Tapez..."), 'clearable' => TRUE]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= '<legend>'.s("Catégorie").'</legend>';
				$h .= $form->select('category', [1 => s("Légumes"), 2 => s("Fruits")], 1, ['placeholder' => s("Toutes"), 'clearable' => TRUE]);
			$h .= '</fieldset>';
			$h .= '<fieldset>';
				$h .= '<legend>'.s("Produits").'</legend>';
				$h .= $form->select('sold', [15 => s("Vendus ces 15 jours")], NULL, ['placeholder' => s("Tous"), 'clearable' => TRUE]);
			$h .= '</fieldset>';
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Les deux premiers champs portent une valeur et leur croix ; le troisième est vide et n'en montre pas.").'</p>';

		return $h;

	}

	/**
	 * Le champ d'autocomplétion du cadriciel (`FormUi::autocomplete()`, `form.js`), dont la dernière
	 * entrée crée l'élément qui manque. Le champ est celui que `FormUi` rend, sans son démarrage
	 * (`onrender`), qui chercherait sur une route que la page n'a pas ; la liste est le balisage que
	 * `AutocompleteField.source()` écrit, montrée ouverte.
	 */
	public function autocomplete(): string {

		$h = '<p class="util-annotation">'.s("Un champ field = 'autocomplete' remplace un select dès que la liste est longue ou qu'elle doit finir par une création. On tape, la route nommée par autocompleteUrl rend les résultats, et le choix pose un champ caché au nom de la propriété. autocompleteResults rend pour chaque élément value, itemHtml et itemText ; une entrée 'type' => 'link' se place en dernier pour créer ce qui manque, comme ProductUi::getAutocompleteCreate() ou PlantUi::getAutocompleteCreate(), et une entrée 'type' => 'title' sépare deux groupes.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le choix déclenche autocompleteSelect, qui ne remonte pas : il s'écoute par document.delegateEventListener, en capture. Son détail est le résultat entier, donc une clé de plus, un prix ou une unité, voyage jusqu'au JavaScript sans attribut data-. La croix vide le champ et poste un champ caché vide, qui délie. Un texte effacé au clavier ne poste rien, et chaque recherche retire le champ caché en partant : un formulaire où vider doit délier pose lui-même le champ caché vide sur autocompleteSource, comme toolbox/static/js/composition.js.").'</p>';

		$form = new \util\FormUi();

		[
			'query' => $query,
			'results' => $results,
		] = $form->autocomplete('designProduct', '#', [], NULL, [['value' => 1, 'itemText' => s("Carotte botte, 2,40 € / botte")]], FALSE, FALSE, [
			'placeholder' => s("Tapez un nom de produit"),
			'onrender' => NULL,
		]);

		$h .= '<div style="max-width: 21rem;">';
			$h .= $form->inputGroup($form->addon(\Asset::icon('box')).$query);
			$h .= $results;
			$h .= '<ul class="autocomplete-list" style="position: static; width: 100%;">';
				$h .= '<li data-n="0">'.s("Carotte botte, 2,40 € / botte").'</li>';
				$h .= '<li data-n="1" class="selected">'.s("Carotte nouvelle, 3,00 € / botte").'</li>';
				$h .= '<li class="autocomplete-link"><a>'.\Asset::icon('plus-circle').'<div>'.s("Ajouter un nouveau produit").'</div></a></li>';
			$h .= '</ul>';
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("La liste se pose sous le champ au focus, et passe en plein écran sur un écran tactile. L'entrée surlignée est celle que les flèches du clavier ont atteinte.").'</p>';

		$h .= '<h4 class="mt-2">'.s("Régler la recherche en tête de liste").'</h4>';
		$h .= '<p class="util-annotation">'.s("autocompleteHead pose une rangée de boutons en tête de la liste, qui règle la recherche sans la fermer. La recherche reçoit la valeur choisie sous le nom donné par name, les boutons suivent l'ordre de values, et selected donne la valeur choisie d'office. Un clic sur un autre bouton relance la recherche avec le texte tapé, le champ garde la valeur choisie d'une recherche à l'autre, et la rangée reste en haut quand la liste défile. Quand elle ne règle qu'une section de la liste, la réponse la place sous l'intitulé de cette section par un résultat de type head, et elle défile alors avec la section, comme dans « Espèce ou série » de « Nouvelle intervention » (planning/ui/TaskDay.u.php). FormUi::getAutocompleteHead() refuse une valeur choisie qui n'est pas dans la liste.").'</p>';

		[
			'query' => $query,
			'results' => $results,
		] = $form->autocomplete('designCultivation[]', '#', [], NULL, [], FALSE, FALSE, [
			'placeholder' => s("Espèce ou série"),
			'onrender' => NULL,
		] + $form->getAutocompleteHead([
			'name' => 'season',
			'label' => s("Saison"),
			'values' => [2025 => '2025', 2026 => '2026', 2027 => '2027'],
			'selected' => 2026,
		]));

		$h .= '<div style="max-width: 21rem;">';
			$h .= $form->inputGroup($form->addon(\Asset::icon('flower2')).$query);
			$h .= $results;
			$h .= '<ul class="autocomplete-list" style="position: static; width: 100%;">';
				$h .= '<li class="autocomplete-not-selectable"><div>'.s("Dans les séries").'</div></li>';
				$h .= '<li class="autocomplete-not-selectable autocomplete-head autocomplete-head-placed">';
					$h .= '<span>'.s("Saison").'</span>';
					$h .= '<button type="button" class="btn btn-sm btn-outline-white">2025</button>';
					$h .= '<button type="button" class="btn btn-sm btn-white">2026</button>';
					$h .= '<button type="button" class="btn btn-sm btn-outline-white">2027</button>';
				$h .= '</li>';
				$h .= '<li data-n="0" class="selected">'.s("Chou fleur · s36").'</li>';
				$h .= '<li data-n="1">'.s("Chou rave · s14").'</li>';
				$h .= '<li class="autocomplete-not-selectable disabled"><div>'.p("+ {value} autre", "+ {value} autres", 3).'</div></li>';
				$h .= '<li class="autocomplete-not-selectable"><div>'.s("Hors série").'</div></li>';
				$h .= '<li data-n="2">'.s("Chou de Bruxelles").'</li>';
			$h .= '</ul>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Plusieurs choix").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec multiple = TRUE dans le describer, le champ poste un tableau (nom suivi de []) : chaque choix s'ajoute en ligne sous le champ, avec son champ caché et sa corbeille, et le champ se vide pour en accueillir un autre. Un même élément ne s'ajoute pas deux fois, et rien n'est posté quand la liste est vide. autocompleteReorder ajoute les flèches qui ordonnent la liste. On le trouve sur les intrants d'une fertilisation (series/ui/Task.u.php) et sur les séries de « Nouvelle intervention » du planning, qui crée une intervention par série choisie (planning/ui/TaskCreate.u.php). autocompleteKeepQuery y garde le texte tapé après un choix, sélectionné, pour revenir sur les autres séries de la même recherche.").'</p>';
		$h .= '<p class="util-annotation">'.s("Avec autocompleteWidth = 'group', la liste prend la largeur de l'input-group qui contient le champ, addon et croix compris, et s'aligne sur son bord gauche, pour qu'un libellé long tienne sur une ligne. Sans input-group autour, ou en plein écran au doigt, elle garde la largeur du champ. Une autre valeur est refusée.").'</p>';

		[
			'query' => $query,
			'results' => $results,
		] = $form->autocomplete('designSeries[]', '#', [], NULL, [
			['value' => 1, 'itemHtml' => \plant\PlantUi::getVignette(new \plant\Plant(['fqn' => 'carotte']), '2.5rem').'<div><div>'.s("Carotte 1 · Saison 2027 · s41").'</div><div class="util-annotation">'.s("Serre 1").' '.\Asset::icon('chevron-right').' 1, 2, 3</div></div>'],
			['value' => 2, 'itemHtml' => \plant\PlantUi::getVignette(new \plant\Plant(['fqn' => 'poireau']), '2.5rem').'<div><div>'.s("Poireau d'automne conservation longue · Saison 2026 · s23").'</div><div class="util-annotation">'.s("Jardin du bas").' '.\Asset::icon('chevron-right').' 11e, 3s, 4s, 12e</div></div>'],
			['value' => 3, 'itemHtml' => \plant\PlantUi::getVignette(new \plant\Plant(['fqn' => 'poivron']), '2.5rem').'<div><div>'.s("Poivron · s16").'</div><div class="util-annotation">'.s("Serre 12").'</div></div>'],
		], FALSE, FALSE, [
			'placeholder' => s("Espèce ou série"),
			'onrender' => NULL,
		]);

		$h .= '<div style="max-width: 21rem;">';
			$h .= $form->inputGroup($form->addon(\Asset::icon('flower2')).$query);
			$h .= $results;
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une liste courte rangée par groupes, dans un select").'</h4>';
		$h .= '<p class="util-annotation">'.s("FormUi::select() reçoit alors une liste de groupes, chacun sous la forme ['label' => …, 'values' => [valeur => libellé]], et l'attribut 'group' => TRUE, qui pose un optgroup par groupe. Sans cet attribut, rien ne lève : le select rend une seule option vide au nom du premier groupe, et les valeurs disparaissent.").'</p>';

		$h .= '<div class="util-search">';
			$h .= '<fieldset>';
				$h .= '<legend>'.s("Culture").'</legend>';
				$h .= $form->select('designGroup', [
					['label' => s("Légumes racines"), 'values' => [1 => s("Carotte"), 2 => s("Radis")]],
					['label' => s("Légumes feuilles"), 'values' => [3 => s("Laitue"), 4 => s("Épinard")]],
				], 1, ['group' => TRUE]);
			$h .= '</fieldset>';
		$h .= '</div>';

		return $h;

	}

	public function board(): string {

		$h = '<p class="util-annotation">'.s("util-board réunit un titre util-board-title et une grille util-board-columns, qui loge autant de colonnes util-board-column que la place en offre à 16rem au moins. Une colonne porte son titre util-board-column-title, son contenu dans util-board-column-body et ses gestes dans util-board-column-foot. Un filet sépare deux voisines, vertical côte à côte et horizontal sous 768px, à la teinte de l'univers quand un util-domain-* englobe le tableau. Un tableau dont il ne reste aucune colonne se retire avec son titre. C'est la mise en page de l'accueil d'une structure.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-board-column-body est un conteneur : une grille util-cards-2 ou util-cards-3 qu'il range passe à une piste sous 40rem de colonne, et util-cards-3 à deux sous 60rem, quelle que soit la largeur de la fenêtre. Les rangs util-rows et util-row mettent le libellé à gauche et la valeur à droite, et un rang d'une seule cellule reste à gauche. Un rang qui nomme les colonnes des rangs suivants porte un util-table-header par cellule.").'</p>';

		$h .= '<div class="util-board util-domain-commercialisation">';

			$h .= '<div class="util-board-title">';
				$h .= '<span class="util-icon util-icon-sm util-icon-commercialisation">'.\Asset::icon('basket3').'</span>';
				$h .= s("Commercialisation");
			$h .= '</div>';

			$h .= '<div class="util-board-columns">';

				$h .= '<div class="util-board-column">';
					$h .= '<div class="util-title">';
						$h .= '<h2 class="util-board-column-title">'.s("Ventes à préparer").'</h2>';
						$h .= '<a href="#" class="btn btn-sm btn-commercialisation-light">'.\Asset::icon('sliders').' '.s("Choisir").'</a>';
					$h .= '</div>';
					$h .= '<div class="util-board-column-body">';
						$h .= '<div class="util-rows">';
							$h .= '<div class="util-row"><span>'.s("Marché de plein vent du samedi matin, place de la mairie").'</span><b>12</b></div>';
							$h .= '<div class="util-row"><span><span class="badge badge-neutral">'.s("Carte bancaire sur terminal de paiement").'</span></span><b>1 240,00 €</b></div>';
							$h .= '<div class="util-row color-muted">'.s("2 autres ventes").'</div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-board-column-foot">';
						$h .= '<a href="#" class="btn btn-sm btn-commercialisation-light">'.\Asset::icon('arrow-right-short').' '.s("Voir les ventes").'</a>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-board-column">';
					$h .= '<h2 class="util-board-column-title">'.s("Mise en route").'</h2>';
					$h .= '<div class="util-board-column-body">';
						$h .= '<div class="util-cards util-cards-2">';
							$h .= \main\DesignUi::getDeckEntry('#', 'person-gear', 'commercialisation', s("Administration"), s("Personne d'autre n'est encore invité à tenir la structure."));
							$h .= \main\DesignUi::getDeckEntry('#', 'shop', 'commercialisation', s("Boutique en ligne"), s("Aucune date de vente n'est encore ouverte."));
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-board-column">';
					$h .= '<h2 class="util-board-column-title">'.s("Livraisons à venir").'</h2>';
					$h .= '<div class="util-board-column-body">';
						$h .= '<div class="util-rows">';
							$h .= '<div class="util-label">'.s("Cette semaine").'</div>';
							$h .= '<div class="util-row"><span>'.s("AMAP du quartier des Chartreux").'</span><b>'.s("24 paniers").'</b></div>';
							$h .= '<div class="util-row"><span>'.s("Épicerie La Récolte").'</span><b>'.s("8 colis").'</b></div>';
							$h .= '<div class="util-label">'.s("La semaine prochaine").'</div>';
							$h .= '<div class="util-row"><span>'.s("Restaurant Le Potager").'</span><b>'.s("3 colis").'</b></div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-board-column-foot">';
						$h .= '<a href="#" class="btn btn-sm btn-commercialisation-light">'.\Asset::icon('arrow-right-short').' '.s("Voir les livraisons").'</a>';
					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Des blocs empilés dans une seule colonne").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-board-columns-1, posée avec util-board-columns, garde une seule piste à toute largeur : les colonnes s'empilent et le filet qui les sépare reste horizontal, celui du téléphone. Elle sert une page qui range ses blocs en piles, chaque pile dans sa moitié d'écran. Une classe util-domain-* posée sur une colonne teinte son titre sans teinter le filet, qui ne prend la couleur d'un univers que si la classe englobe la pile.").'</p>';

		$h .= '<div class="util-board">';

			$h .= '<div class="util-board-columns util-board-columns-1">';

				$h .= '<div class="util-board-column util-domain-commercialisation">';
					$h .= '<h2 class="util-board-column-title">'.s("Livraisons à venir").'</h2>';
					$h .= '<div class="util-board-column-body">';
						$h .= '<div class="util-rows">';
							$h .= '<div class="util-row"><span>'.s("AMAP du quartier des Chartreux").'</span><b>'.s("24 paniers").'</b></div>';
							$h .= '<div class="util-row"><span>'.s("Épicerie La Récolte").'</span><b>'.s("8 colis").'</b></div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-board-column-foot">';
						$h .= '<a href="#" class="btn btn-commercialisation-light">'.\Asset::icon('arrow-right-short').' '.s("Voir les livraisons").'</a>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-board-column util-domain-production">';
					$h .= '<div class="util-title">';
						$h .= '<h2 class="util-board-column-title">'.s("Interventions de la semaine").'</h2>';
						$h .= '<a href="#" class="btn btn-sm btn-production-light">'.\Asset::icon('sliders').' '.s("Choisir").'</a>';
					$h .= '</div>';
					$h .= '<div class="util-board-column-body">';
						$h .= '<div class="util-rows">';
							$h .= '<div class="util-row"><div class="util-table-header">'.s("Intervention").'</div><div class="util-table-header">'.s("Planches").'</div></div>';
							$h .= '<div class="util-row"><span>'.s("Plantation").'</span><b>4</b></div>';
							$h .= '<div class="util-row"><span>'.s("Semis direct").'</span><b>42</b></div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-board-column">';
					$h .= '<h2 class="util-board-column-title">'.s("Mise en route").'</h2>';
					$h .= '<div class="util-board-column-body">';
						$h .= '<div class="util-cards util-cards-2">';
							$h .= \main\DesignUi::getDeckEntry('#', 'person-gear', 'settings', s("Administration"), s("Personne d'autre n'est encore invité à tenir la structure."));
							$h .= \main\DesignUi::getDeckEntry('#', 'shop', 'settings', s("Boutique en ligne"), s("Aucune date de vente n'est encore ouverte."));
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les tuiles de l'accueil d'une structure. Les démonstrations portent les données qui ont fait
	 * déborder la maquette : un montant insécable de six chiffres et son unité, des libellés de rang
	 * qui passent à la ligne, deux liens dans un même pied.
	 */
	public function tiles(): string {

		$uiDashboard = new \dashboard\DashboardUi();

		$h = '<p class="util-annotation">'.s("util-tiles range des blocs en tuiles, autant par rang que la place en loge à 15rem, chacune à sa hauteur. Une util-tile porte toujours, dans cet ordre, son intitulé util-domain, son chiffre, une liste courte util-rows et ses liens. Elle se pose au filet sur la feuille, et util-tile-wide lui donne le rang entier, pour un bloc qui n'a pas de chiffre ou dont la liste a besoin de la largeur. util-tiles-1 les tient en une seule piste, quelle que soit la largeur, pour une colonne qui se lit de haut en bas. C'est la mise en page des colonnes de l'accueil d'une structure, au filet, chaque tuile d'univers sous sa tête util-tile-head.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-tile-figure enveloppe le chiffre util-figure-value et son intitulé util-figure-label, en 2,25rem à toute largeur dans l'encre de l'univers, pour qu'un montant tenu à son unité par util-unit tienne dans une tuile de 14rem. Une util-note posée dans util-tiles prend le rang entier, sans marge basse, la grille donnant déjà l'écart.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-more est le lien qui clôt un bloc, en texte et suivi d'une flèche, dans l'encre de l'univers ; util-mores range les liens d'un même pied et les passe à la ligne. Il remplace le bouton pâle de pied de colonne, qui en posait autant que de blocs au même poids. La flèche reste collée au dernier mot quand le libellé passe à la ligne.").'</p>';

		$h .= '<div class="util-tiles">';

			$h .= '<div class="util-tile util-tile-wide">';
				$h .= '<h3 class="util-domain">'.s("À traiter d'abord").'</h3>';
				$h .= '<div class="util-rows">';
					$h .= '<div class="util-row"><span>'.s("38 factures échues, dont la plus ancienne attend depuis 229 jours").'</span><b class="util-unit">20 697 <small>€ TTC</small></b></div>';
					$h .= '<div class="util-row"><span>'.s("3 commandes à préparer pour lundi 19 octobre").'</span><b class="util-unit">66 <small>€ HT</small></b></div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-note util-note-wide util-domain-production">';
				$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';
				$h .= '<h3 class="util-domain">'.s("Pense-bête").'</h3>';
				$h .= '<div class="util-rows">';
					$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('circle').'</a><div>'.s("Commander les plants de poireaux").'</div></div></div>';
					$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('circle').'</a><div>'.s("Rappeler le fournisseur de terreau").'</div></div></div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-tile util-domain-commercialisation">';
				$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon('commercialisation').' '.s("Ventes de la semaine").'</h3>';
				$h .= '<div class="util-tile-figure">';
					$h .= '<div class="util-figure-value"><span class="util-unit">45 108 <small>'.s("€ TTC").'</small></span></div>';
					$h .= '<div class="util-figure-label">'.s("encaissés depuis lundi").'</div>';
				$h .= '</div>';
				$h .= '<div class="util-rows">';
					$h .= '<div class="util-row"><span>'.s("Marché de plein vent du samedi matin, place de la mairie").'</span><b>'.s("12 ventes").'</b></div>';
					$h .= '<div class="util-row"><span>'.s("Boutique en ligne").'</span><b>'.s("8 ventes").'</b></div>';
				$h .= '</div>';
				$h .= '<div class="util-mores">';
					$h .= '<a href="#" class="util-more">'.s("Voir les ventes").'</a>';
					$h .= '<a href="#" class="util-more">'.s("Choisir les ventes affichées sur l'accueil").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-tile util-domain-production">';
				$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon('production').' '.s("Récoltes").'</h3>';
				$h .= '<div class="util-tile-figure">';
					$h .= '<div class="util-figure-value"><span class="util-unit">1 284 <small>'.s("kg").'</small></span></div>';
					$h .= '<div class="util-figure-label">'.s("récoltés cette semaine").'</div>';
				$h .= '</div>';
				$h .= '<div class="util-mores">';
					$h .= '<a href="#" class="util-more">'.s("Voir les récoltes").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-tile util-domain-commercialisation">';
				$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon('commercialisation').' '.s("Boutique en ligne").'</h3>';
				$h .= '<div class="util-tile-figure">';
					$h .= '<div class="util-figure-value">18</div>';
					$h .= '<div class="util-figure-label">'.s("commandes à livrer jeudi 22 octobre").'</div>';
				$h .= '</div>';
				$h .= '<div class="util-mores">';
					$h .= '<a href="#" class="util-more">'.s("Voir les commandes").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-tile util-domain-production">';
				$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon('production').' '.s("Semis à venir").'</h3>';
				$h .= '<div class="util-rows">';
					$h .= '<div class="util-row"><span>'.s("Mâche verte à cœur plein").'</span><b>'.s("mardi").'</b></div>';
					$h .= '<div class="util-row"><span>'.s("Épinard").'</span><b>'.s("jeudi").'</b></div>';
				$h .= '</div>';
				$h .= '<div class="util-mores">';
					$h .= '<a href="#" class="util-more">'.s("Voir le planning").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-tile-head se pose sur l'intitulé util-domain d'une tuile au filet de classe util-domain-*. Il en fait la tête de la tuile, à l'encre de l'univers mêlée à 12 % de blanc, l'intitulé gardant son encre. L'icône de l'univers, celle du menu, se pose devant l'intitulé et n'est pas facultative : les cinq teintes ne se distinguent pas entre elles. La tête prend le haut de la tuile en débordant de son retrait, et le suit sous 576px.").'</p>';

		$h .= '<div class="util-tiles mt-2">';

			foreach(['production', 'commercialisation', 'accounting', 'settings', 'amap'] as $domain) {

				$h .= '<div class="util-tile util-domain-'.$domain.'">';
					$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon($domain).' '.$uiDashboard->getSectionName($domain).'</h3>';
					$h .= '<div class="util-tile-figure">';
						$h .= '<div class="util-figure-value">18</div>';
						$h .= '<div class="util-figure-label">'.s("à traiter cette semaine").'</div>';
					$h .= '</div>';
					$h .= '<div class="util-mores">';
						$h .= '<a href="#" class="util-more">'.s("Voir le détail").'</a>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-rows-truncate, posée avec util-rows, tient chaque rang sur une ligne, même dans une tuile, où util-row passe sinon la valeur dessous. Le libellé se coupe d'une ellipse, et une util-annotation posée après la valeur prend toute la largeur du rang dessous ; un lien coupé ne s'étend pas au blanc qui le sépare de la valeur. C'est la liste des boutiques sur l'accueil d'une structure.").'</p>';

		// Une piste à la largeur d'une colonne de l'accueil : dans la grille pleine largeur de cette page,
		// une tuile tombe en dessous, et l'ellipse ne laisserait plus lire qu'un mot.
		$h .= '<div class="util-tiles util-tiles-1 mt-2" style="max-width: 21rem">';

			$h .= '<div class="util-tile util-domain-commercialisation">';
				$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon('commercialisation').' '.s("Boutiques en ligne").'</h3>';
				$h .= '<div class="util-rows util-rows-truncate">';
					$h .= '<div class="util-row"><div><b>'.s("Marché de producteurs du samedi matin, place de la mairie").'</b></div><div>'.s("3 commandes").'</div><div class="util-annotation">'.s("jeudi 22 octobre · les commandes ferment mercredi à 20:00").'</div></div>';
					$h .= '<div class="util-row"><div><b>'.s("Paniers de la semaine").'</b></div><div class="util-annotation">'.s("demain · les commandes sont fermées").'</div></div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-more-lg agrandit le lien à 1,25 rem quand il est le lien principal d'une fiche, posé sous sa tête et avant ses gestes, et qu'il mène à l'objet entier, comme « Voir la série » dans la fiche d'une série. Il garde la graisse, l'encre et la flèche de util-more.").'</p>';

		$h .= '<div class="util-mores">';
			$h .= '<a href="#" class="util-more util-more-lg">'.s("Voir la série").'</a>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-tile-figure vaut aussi hors d'une tuile, pour un chiffre en tête d'une bande ou d'une colonne, dans l'encre de l'univers qui l'englobe.").'</p>';

		$h .= '<div class="util-tile-figure util-domain-production mt-2">';
			$h .= '<div class="util-figure-value"><span class="util-unit">312 <small>'.s("kg").'</small></span></div>';
			$h .= '<div class="util-figure-label">'.s("de légumes à récolter pour la distribution de mardi").'</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-flat, posée sur le corps d'une tuile, retire leur cadre aux tuiles, cartes, decks, notes et chiffres clés qu'il range : ni fond, ni bord, ni ombre, ni retrait, ni punaise. La clé d'un rang qui porte une valeur passe au gris, et le filet des rangs reste. Ici une carte posée dans une tuile, qui y fait une boîte dans la boîte, à gauche sans util-flat et à droite avec.").'</p>';

		$h .= '<div class="util-tiles mt-2">';

			foreach([s("Sans util-flat") => '', s("Avec util-flat") => ' util-flat'] as $label => $flat) {

				$h .= '<div class="util-tile util-domain-production">';
					$h .= '<h3 class="util-domain util-tile-head">'.$uiDashboard->getSectionIcon('production').' '.$label.'</h3>';
					$h .= '<div class="util-board-column-body'.$flat.'">';
						$h .= '<div class="util-card">';
							$h .= '<div class="util-rows">';
								$h .= '<div class="util-row"><span>'.s("Tomate ancienne, serre 4").'</span><b>'.s("120 kg").'</b></div>';
								$h .= '<div class="util-row"><span>'.s("Courgette verte").'</span><b>'.s("64 kg").'</b></div>';
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-tile-link fait d'une util-tile un lien, une image au-dessus de son libellé, pour la grille d'entrée d'une page publique. .selected marque la tuile dont parle la page, et une voisine survolée lui rend son fond. L'image passe de 5 rem à 4 rem sous 576 px, puis à 3 rem sous 450 px, où le libellé descend à 0,9 rem. La tuile-lien garde son padding serré quand une tuile de contenu s'élargit sous 576 px, pour que trois tuiles par rangée tiennent leur libellé.").'</p>';
		$h .= '<div class="util-tiles">';
			foreach([
				['abeille', s("Production"), s("Planifier ses cultures"), TRUE],
				['cochon', s("Commercialisation"), s("Vendre au marché et en ligne"), FALSE],
				['escargot', s("Comptabilité"), s("Tenir ses comptes"), FALSE],
				['chien', s("AMAP"), s("Gérer les contrats"), FALSE],
				['coccinelle-rouge', s("Formations"), s("Se former"), FALSE],
				['chenille-rose', s("Réseaux"), s("Accompagnement interprofessionnel et départemental"), FALSE],
			] as [$svg, $title, $detail, $selected]) {
				$h .= '<a href="#" class="util-tile util-tile-link'.($selected ? ' selected' : '').'">'.\Asset::svg('main', 'friends/'.$svg.'.svg').$title.'<span>'.$detail.'</span></a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une valeur qu'on modifie sur place. Le balisage vient de `Data::quick()`
	 * (`framework/core/Data.php`), qui ne pose le lien que si l'élément accepte d'être modifié.
	 */
	public function quick(): string {

		$h = '<p class="util-annotation">'.s("util-quick souligne en pointillé, au survol seulement, une valeur qu'un clic ouvre en saisie : le tableau reste un tableau et la ligne ne change pas de hauteur. Pour une saisie sur place, on ne l'écrit pas à la main : Data::quick() pose la classe et les attributs quand l'élément passe assertUpdate(), et rend la valeur nue sinon. Le formulaire qui s'ouvre est un util-quick-dropdown posé par lime.js, dont les champs s'empilent en util-quick-form. Il couvre l'écran : quand la saisie doit laisser voir les cases voisines, c'est util-quick-inline, plus bas.").'</p>';

		$h .= '<table>';
			$h .= '<thead>';
				$h .= '<tr><th>'.s("Produit").'</th><th class="text-end">'.s("Quantité").'</th><th class="text-end">'.s("Prix").'</th></tr>';
			$h .= '</thead>';

			$h .= '<tbody>';
				$h .= '<tr><td>'.s("Carotte botte").'</td><td class="text-end"><a class="util-quick">12</a></td><td class="text-end"><a class="util-quick">2,40 €</a></td></tr>';
				$h .= '<tr><td>'.s("Chou vert pièce").'</td><td class="text-end">4</td><td class="text-end">3,10 €</td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';
		$h .= '<p class="util-annotation">'.s("La première ligne accepte la modification, la seconde non : rien ne les distingue au repos, c'est le survol qui dit ce qui s'ouvre.").'</p>';

		$h .= '<h4 class="mt-2">'.s("Le déclencheur d'un tiroir").'</h4>';
		$h .= '<p class="util-annotation">'.s("Quand la valeur s'édite dans un tiroir ou un volet plutôt que sur place, l'écran pose util-quick à la main sur le a qui l'ouvre : la valeur garde le même pointillé au survol, et le geste qui l'ouvre appartient à l'écran (la durée ou la récolte d'une intervention du planning).").'</p>';
		$h .= '<table>';
			$h .= '<tbody>';
				$h .= '<tr><td>'.s("Désherbage des carottes").'</td><td class="text-end"><a class="util-quick">1h30</a></td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';
		$h .= '<p class="util-annotation">'.s("Quand l'enregistrement coûte quelque chose, le describer de la propriété pose \$d->quickConfirm, un message ou une closure qui reçoit l'élément et rend NULL pour ne rien demander : FormUi::quick() le met en data-confirm sur le bouton Enregistrer. Le message est figé à l'ouverture et ne cite donc pas la valeur tapée. Ce qu'il doit savoir de l'élément est préparé par le callback de la page (ModulePage::quick()), jamais cherché dans le describer.").'</p>';

		$form = new \util\FormUi();

		// Un champ rendu deux fois porterait deux fois le même id.
		$field = fn(): string => $form->inputGroup($form->number('value', 1200).$form->addon('€'));

		$h .= '<h4 class="mt-2">'.s("Saisie dans la cellule").'</h4>';
		$h .= '<table>';
			$h .= '<thead>';
				$h .= '<tr><th>'.s("Case").'</th><th>'.s("Libellé").'</th><th class="text-end">'.s("Montant").'</th></tr>';
			$h .= '</thead>';

			$h .= '<tbody>';
				$h .= '<tr>';
					$h .= '<td>'.\main\QuickInlineAiUi::open('FN', 'design-quick-inline').'</td>';
					$h .= '<td>';
						$h .= s("Amortissements non déductibles");
						$h .= \main\QuickInlineAiUi::linked('design-quick-inline', '<a>'.s("Revenir au calcul").'</a>', 'div', 'util-info');
					$h .= '</td>';
					$h .= '<td class="text-end">'.\main\QuickInlineAiUi::get('design-quick-inline', \main\QuickInlineAiUi::open('1 200 €'), '#', [], $field()).'</td>';
				$h .= '</tr>';
			$h .= '</tbody>';
		$h .= '</table>';
		$h .= '<p class="util-annotation">'.s("util-quick-inline remplace la valeur par son champ, sur place : le tableau reste sous les yeux pendant la saisie. Ouverte, la boîte prend util-quick-inline-open et montre son util-quick-inline-field, un formulaire ajax du cadriciel : Entrée l'envoie, Échap et la perte du focus referment sans rien envoyer. La réponse remplace ce qui a changé, pas le tableau. On l'écrit par main\\QuickInlineAiUi : get() pour la boîte, open() pour ce qui l'ouvre, dans la boîte ou ailleurs dans la ligne (le code de la case), linked() pour ce qui ne se montre que pendant la saisie (un lien sous le libellé).").'</p>';

		// Un refus se montre ici tel que Lime.Alert le pose : form-error-wrapper sur le data-wrapper du champ, le message en dernier enfant
		$refused = fn(string $name, string $field, string $message): string => '<div data-wrapper="'.$name.'" class="form-error-wrapper">'.$field.'<p class="form-error-message">'.$message.'</p></div>';

		$h .= '<table>';
			$h .= '<thead>';
				$h .= '<tr><th>'.s("Libellé").'</th><th class="text-end">'.s("Montant").'</th></tr>';
			$h .= '</thead>';

			$h .= '<tbody>';
				$h .= '<tr>';
					$h .= '<td>'.s("Ventes").'</td>';
					$h .= '<td class="text-end">'.\main\QuickInlineAiUi::get('design-quick-inline-refused', \main\QuickInlineAiUi::open('50 637 €'), '#', [], $refused('value', $form->inputGroup($form->number('value', 99999999999).$form->addon('€')), s("Indiquez un montant en euros")), class: 'util-quick-inline-open').'</td>';
				$h .= '</tr>';
				$h .= '<tr>';
					$h .= '<td>'.s("Achats").'</td>';
					$h .= '<td class="text-end">'.\main\QuickInlineAiUi::get('design-quick-inline-refused-percent', \main\QuickInlineAiUi::open('14 640 €'), '#', [], $refused('variationPercent', $form->inputGroup($form->number('variationPercent', 10).$form->addon('%')), s("Indiquez une évolution en pourcentage")), class: 'util-quick-inline-open').'</td>';
				$h .= '</tr>';
			$h .= '</tbody>';
		$h .= '</table>';
		$h .= '<p class="util-annotation">'.s("Un refus s'écrit sous le champ quand le champ est enveloppé d'un data-wrapper qui porte le nom de la propriété refusée : la boîte reste ouverte, le focus revient au champ, et le bouton se cale en haut du champ au lieu de rester au milieu de l'ensemble. Sans data-wrapper, le refus part au bandeau global et la boîte reste centrée.").'</p>';

		$fieldWithNote = '<div class="util-quick-inline-fill-fields">';
			$fieldWithNote .= '<div>'.$form->inputGroup($form->number('amount', 116718.5, ['class' => 'text-end']).$form->addon('€')).'</div>';
			$fieldWithNote .= '<div class="util-quick-inline-fill-note">'.$form->textarea('comment', s("Deux serres de plus au printemps, et la vente directe du samedi matin qui reprend dès le mois d'avril"), ['rows' => 3]).'</div>';
		$fieldWithNote .= '</div>';

		$h .= '<div class="util-card">';
			$h .= '<div>'.s("Ventes").'</div>';
			$h .= \main\QuickInlineAiUi::get('design-quick-inline-note', \main\QuickInlineAiUi::open('116 718 €'), '#', [], $fieldWithNote, class: 'util-quick-inline-open util-quick-inline-fill');
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Un texte de plusieurs lignes, une note sous un montant par exemple, part avec le champ qu'il accompagne dans la même boîte : le bouton reste en face du premier champ, et c'est l'attribut rows du textarea qui en dit la hauteur. util-quick-inline-fill, posé par le paramètre class de get(), donne à la boîte la largeur de ce qui la contient et au champ toute celle qui reste à côté du bouton. Dans le champ, util-quick-inline-fill-fields range le montant et la note l'un sous l'autre, calés à droite, et util-quick-inline-fill-note donne à la note la largeur d'une phrase courte, ou toute la largeur sur un téléphone.").'</p>';

		$h .= '<table>';
			$h .= '<thead>';
				$h .= '<tr><th>'.s("Libellé").'</th><th class="text-end">'.s("Montant").'</th></tr>';
			$h .= '</thead>';

			$h .= '<tbody>';
				$h .= '<tr>';
					$h .= '<td>'.s("Dont à plus d'un an").'</td>';
					$h .= '<td class="text-end">'.\main\QuickInlineAiUi::get('design-quick-inline-wrap', \main\QuickInlineAiUi::open('116 718 €'), '#', [], $field(), class: 'util-quick-inline-wrap').'</td>';
				$h .= '</tr>';
			$h .= '</tbody>';
		$h .= '</table>';
		$h .= '<p class="util-annotation">'.s("Sous 450 px, une colonne de montants devient plus étroite que le champ et son bouton : util-quick-inline-wrap laisse alors le bouton passer sous le champ, calé à droite comme la valeur, au lieu de déborder sur la colonne voisine. Il se pose par le paramètre class de get().").'</p>';

		$h .= '<table>';
			$h .= '<thead>';
				$h .= '<tr><th>'.s("Pièce").'</th><th>'.s("Fichier").'</th></tr>';
			$h .= '</thead>';

			$h .= '<tbody>';
				$h .= '<tr>';
					$h .= '<td>'.s("Relevé de décembre").'</td>';
					$h .= '<td>'.\main\QuickInlineAiUi::get('design-quick-inline-file', '<a>'.\Asset::icon('download').' releve.pdf</a> · '.\main\QuickInlineAiUi::action(s("Remplacer")), '#', [], $form->file('content'), binary: TRUE).'</td>';
				$h .= '</tr>';
			$h .= '</tbody>';
		$h .= '</table>';
		$h .= '<p class="util-annotation">'.s("Un fichier se remplace de la même façon. get() avec binary: TRUE envoie le formulaire en multipart, et util-quick-inline-field rend visible le champ fichier que form.css masque partout ailleurs. Le geste « Remplacer » s'écrit par action(), qui a la couleur d'un lien, là où open() garde celle de la valeur. La boîte reste ouverte pendant que le sélecteur de fichiers du système a la main.").'</p>';

		$h .= '<div class="util-card">';
			$h .= \main\QuickInlineAiUi::get('design-quick-inline-blur', \main\QuickInlineAiUi::open(s("Commander les plants de poireaux")), '#', [], $form->text('text', s("Commander les plants de poireaux")), submitOnBlur: TRUE);
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Une ligne qu'on corrige en passant, dans une liste, s'enregistre aussi quand on clique ailleurs : get() avec submitOnBlur: TRUE. Si rien n'a changé, la boîte se referme sans rien envoyer, et Échap remet la valeur d'origine. Cette démonstration n'a pas d'action derrière elle : après une saisie, cliquer ailleurs ne peut rien enregistrer et la laisse ouverte, là où un écran réel reçoit une réponse qui remplace la ligne. Sans l'option, cliquer ailleurs referme toujours sans enregistrer.").'</p>';

		$h .= '<div class="util-card">';
			$h .= \main\QuickInlineAiUi::get('design-quick-inline-blur-refused', \main\QuickInlineAiUi::open(s("Commander les plants de poireaux")), '#', [], $refused('text', $form->text('text', ''), s("Écrivez le rappel")), submitOnBlur: TRUE, class: 'util-quick-inline-open');
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Refusée, une boîte qui enregistre à la sortie montre son refus de la même façon. Une seconde sortie sur la valeur refusée abandonne la saisie, comme Échap, au lieu de renvoyer le même refus.").'</p>';

		$h .= '<h4 class="mt-2">'.s("Champ écrit à sa place").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-inplace-field écrit une valeur là où elle se lit : le champ n'a ni cadre, ni fond, ni ombre, et prend la police et la couleur de ce qui l'entoure, un titre de carte ou un chiffre. Survolé, il se souligne en pointillé comme util-quick, et dans une enveloppe util-quick, c'est elle qui souligne. Vide, il dit ce qu'il attend en italique atténué. util-inplace-field-fit le règle à la largeur de ce qu'on y tape, pour un mot qui ne doit pas pousser ses voisins, comme le nom d'un onglet ; sous Firefox, sans field-sizing, l'écran tient l'attribut size à jour à la frappe. Il se pose sur un input ou, par la classe passée dans ses attributs, sur l'éditeur inline. Le nom se lit « champ sur place » : ce n'est pas la pièce d'un composant.").'</p>';
		$h .= '<div class="util-card">';
			$h .= '<div class="util-card-title">'.$form->text('inplaceTitle', s("Rendement des tomates"), ['class' => 'util-inplace-field']).'</div>';
			$h .= '<div class="util-figure-value">'.$form->text('inplaceFigure', '4,2', ['class' => 'util-inplace-field']).'</div>';
			$h .= $form->text('inplaceEmpty', NULL, ['class' => 'util-inplace-field', 'placeholder' => s("Ce que le chiffre mesure")]);
			$h .= '<span class="badge badge-neutral">'.$form->text('inplaceFit', s("Semis"), ['class' => 'util-inplace-field util-inplace-field-fit']).'</span>';
		$h .= '</div>';

		return $h;

	}

	public function records(): string {

		$h = '<p class="util-annotation">'.s("util-record présente un objet seul et tout ce qui s'y rattache : sa tête dans util-record-head (la marque, util-record-title avec son état, util-record-context), ses gestes dans util-record-actions, puis une information liée par util-record-item, son libellé, sa valeur et, s'il en a un, son geste dans util-record-action. Elle sert à l'objet qu'on vient regarder en premier, au-dessus d'une liste ou seul sur sa page ; un objet parmi d'autres reste un util-deck.").'</p>';
		$h .= '<p class="util-annotation">'.s("Deux déclinaisons sur le même balisage. util-record-branches pend chaque information à une ligne qui part du milieu de la marque et s'arrête au dernier nœud ; util-record-item-alert pose la rangée sur le fond et l'encre de util-deck-band-state, nœud en teinte d'avertissement, pour que la fiche d'un objet et sa carte disent l'alerte de la même couleur ; le talon ne la marque pas. Sous 768 px, chaque information passe sous son libellé et les gestes sous la liste, util-record-main s'effaçant de la grille pour le permettre. util-record-stub pose l'objet et ses gestes dans un talon de 22rem et les informations à droite, en colonnes d'au moins 13rem, derrière un pointillé ; util-record-item-wide y prend le rang entier. Sous 768 px, le talon passe au-dessus.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-record-tinted pose la tête sur le fond de l'univers : sur les branches, une bande derrière la tête et les gestes, les informations restant sur le blanc, et la ligne des branches part du bas de la bande ; sur le talon, le talon entier. La teinte vient de --object, qui part de --secondary, déjà redéfinie par chaque univers : la fiche n'a rien à dire de son domaine. Sans util-record-tinted, le talon reste neutre et les branches gardent la teinte sur leurs nœuds seulement.").'</p>';

		$main = function(string $mark, string $title, string $context, string $primary): string {
			return '<div class="util-record-main">'
				.'<div class="util-record-head">'
					.$mark
					.'<div class="util-record-identity">'
						.'<h3 class="util-record-title">'.$title.'</h3>'
						.'<div class="util-record-context">'.$context.'</div>'
					.'</div>'
				.'</div>'
				.'<div class="util-record-actions">'
					.'<a href="#design-records" class="btn btn-primary">'.$primary.'</a>'
					.\main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Modifier").'</a><a href="#" class="dropdown-item">'.s("Supprimer").'</a>')
				.'</div>'
			.'</div>';
		};

		$distribution = $main(
			\util\DateUi::calendar('2026-10-13'),
			s("Mardi 13 octobre").' <span class="badge badge-confirmed badge-dot">'.s("Commandes ouvertes").'</span>',
			s("Salle des fêtes, place de la Mairie, Saint-Marcellin"),
			\Asset::icon('file-earmark-text').' '.s("Feuille de distribution")
		);

		$distributionItems = \main\DesignUi::getRecordItem(s("Créneau"), s("de 18:00 à 19:30"))
			.\main\DesignUi::getRecordItem(s("Commandes"), s("Du mardi 6 octobre à 08:00 au lundi 12 octobre à 20:00"))
			.\main\DesignUi::getRecordItem(s("Permanences"), s("Personne pour le rangement"), '<a href="#design-records" class="btn btn-sm btn-outline-primary">'.s("Inscrire à une permanence").'</a>', 'util-record-item-alert')
			.\main\DesignUi::getRecordItem(s("Absences"), s("2 adhérents"));

		$h .= '<h4>'.s("Les branches, tête teintée").'</h4>';
		$h .= '<article class="util-record util-record-branches util-record-tinted mb-2">';
			$h .= $distribution;
			$h .= '<ul class="util-record-list">'.$distributionItems.'</ul>';
		$h .= '</article>';

		$h .= '<h4>'.s("Les branches").'</h4>';
		$h .= '<article class="util-record util-record-branches mb-2">';
			$h .= $distribution;
			$h .= '<ul class="util-record-list">'.$distributionItems.'</ul>';
		$h .= '</article>';

		$contract = $main(
			'<span class="util-icon util-icon-lg">'.\Asset::icon('basket').'</span>',
			s("Panier de légumes d'automne").' <span class="badge badge-confirmed badge-dot">'.s("Souscriptions ouvertes").'</span>',
			s("Les Jardins du Pré, panier de saison"),
			s("Inscrire un adhérent")
		);

		$contractItems = \main\DesignUi::getRecordItem(s("Période"), s("du 13 octobre au 10 novembre, 5 mardis"))
			.\main\DesignUi::getRecordItem(s("Formules"), s("Grand panier à 20,00 €, petit panier à 12,00 €"))
			.\main\DesignUi::getRecordItem(s("Adhérents"), s("5 inscrits"), '<a href="#design-records" class="btn btn-sm btn-outline-primary">'.s("Voir les adhérents").'</a>')
			.\main\DesignUi::getRecordItem(s("Règlement"), s("Chèque ou virement, en trois fois au plus"), class: 'util-record-item-wide');

		$h .= '<h4>'.s("Le talon, teinté").'</h4>';
		$h .= '<article class="util-record util-record-stub util-record-tinted mb-2">';
			$h .= $contract;
			$h .= '<ul class="util-record-list">'.$contractItems.'</ul>';
		$h .= '</article>';

		$h .= '<h4>'.s("Le talon").'</h4>';
		$h .= '<article class="util-record util-record-stub mb-2">';
			$h .= $contract;
			$h .= '<ul class="util-record-list">'.$contractItems.'</ul>';
		$h .= '</article>';

		$h .= '<h4>'.s("Dans un panneau").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-panel-tinted se pose sur le panneau qui s'ouvre sur la fiche d'un objet, par new \\Panel(attributes: ['class' => 'util-panel-tinted']), et la tête de la fiche passe dans header, util-record-head et util-record-actions sans util-record-main. La bande du panneau prend la teinte de la fiche et reste en vue au défilement, la croix du cadriciel au bout, derrière un filet ; le corps commence par la fiche sans sa tête, accrochée à la bande, ou par le formulaire qui l'enveloppe. Un util-record-stub y perd son talon et son pointillé, ses informations en colonnes sur toute la largeur. Sous 576 px, les gestes restent à droite du titre tant qu'ils y tiennent, et passent sous lui sinon, toujours à droite. Le panneau d'une note, \\note\\NoteUi::getPanel(), en est le modèle.").'</p>';

		// Les deux panneaux qui suivent n'ont pas la classe panel, que Lime.Panel prend pour un panneau ouvert
		// (Échap, purge()) et retirerait de la page, et recopient en tête les deux boutons que
		// Lime.Panel.removeButton() pose dans tout panneau (lime.js).
		$h .= '<div class="design-panel-frame">';
			$h .= '<div class="util-panel-tinted">';
				$h .= '<div class="panel-dialog">';
					$h .= '<div class="panel-header">';
						$h .= '<div class="panel-header-content">';
							$h .= '<div class="util-record-head">';
								$h .= '<span class="util-icon util-icon-lg">'.\Asset::icon('journal-text').'</span>';
								$h .= '<div class="util-record-identity">';
									$h .= '<h3 class="util-record-title">'.s("Plan de culture 2027").'</h3>';
									$h .= '<div class="util-record-context">'.s("écrite le 19 octobre 2026").'</div>';
								$h .= '</div>';
							$h .= '</div>';
							$h .= '<div class="util-record-actions">';
								$h .= '<a href="#design-records" class="btn btn-outline-primary">'.\Asset::icon('file-pdf').' '.s("PDF").'</a>';
								$h .= \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Supprimer").'</a>');
							$h .= '</div>';
						$h .= '</div>';
						$h .= '<a class="panel-close-desktop">'.\Asset::icon('x').'</a>';
						$h .= '<a class="panel-close-mobile">'.\Asset::icon('arrow-left-short').'</a>';
					$h .= '</div>';
					$h .= '<div class="panel-body">';
						$h .= '<article class="util-record util-record-branches">';
							$h .= '<ul class="util-record-list">';
								$h .= \main\DesignUi::getRecordItem(s("Rattacher à"), '<span class="util-empty">'.s("Aucun objet").'</span>', '<a href="#design-records" class="btn btn-sm btn-outline-primary">'.\Asset::icon('link-45deg').' '.s("Rattacher").'</a>');
								$h .= \main\DesignUi::getRecordItem(s("Qui peut la lire"), s("Toute l'équipe"));
							$h .= '</ul>';
						$h .= '</article>';
						$h .= '<p class="mt-1">'.s("Rotation des planches nord, à revoir avec la commande de semences.").'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="design-panel-frame mt-2">';
			$h .= '<div class="util-panel-tinted">';
				$h .= '<div class="panel-dialog">';
					$h .= '<div class="panel-header">';
						$h .= '<div class="panel-header-content">';
							$h .= '<div class="util-record-head">';
								$h .= '<span class="util-icon util-icon-lg">'.\Asset::icon('check2-square').'</span>';
								$h .= '<div class="util-record-identity">';
									$h .= '<h3 class="util-record-title">'.s("Désherbage").'</h3>';
									$h .= '<div class="util-record-context">'.s("Carotte de garde, série 2").'</div>';
								$h .= '</div>';
							$h .= '</div>';
							$h .= '<div class="util-record-actions">';
								$h .= \main\DesignUi::getGear('<a href="#" class="dropdown-item">'.s("Supprimer").'</a>');
							$h .= '</div>';
						$h .= '</div>';
						$h .= '<a class="panel-close-desktop">'.\Asset::icon('x').'</a>';
						$h .= '<a class="panel-close-mobile">'.\Asset::icon('arrow-left-short').'</a>';
					$h .= '</div>';
					$h .= '<div class="panel-body">';
						$h .= '<article class="util-record util-record-stub">';
							$h .= '<ul class="util-record-list">';
								$h .= \main\DesignUi::getRecordItem(s("Planifiée"), s("semaine 43"));
								$h .= \main\DesignUi::getRecordItem(s("Intrants"), '<span class="util-empty">'.s("Aucun").'</span>');
								$h .= \main\DesignUi::getRecordItem(s("Matériel"), s("Houe maraîchère"));
								$h .= \main\DesignUi::getRecordItem(s("Description"), s("Passer entre les rangs avant la pluie annoncée jeudi."), class: 'util-record-item-wide');
							$h .= '</ul>';
						$h .= '</article>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une vignette et son texte. Balisage de `ouvretaferme/analyze/ui/Report.u.php` et de
	 * `base/structure/ui/ProfileAi.u.php`, où la vignette s'ajoute à une util-card.
	 */
	public function vignettes(): string {

		$h = '<p class="util-annotation">'.s("util-vignette pose une image à gauche et le texte à droite, centrés l'un sur l'autre quand l'image est la plus haute, alignés sur le haut quand c'est le texte. Dans une cellule de tableau, ils se calent en haut, pour que le texte s'aligne sur celui des autres colonnes. Elle se cumule avec util-card quand le bloc est une carte.").'</p>';

		$h .= '<div class="util-cards util-cards-2">';
			$h .= '<div class="util-card util-vignette">';
				$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('house').'</div>';
				$h .= '<div>';
					$h .= '<div class="util-card-title">'.s("Ferme du Goupil").'</div>';
					$h .= '<div class="util-card-info">'.s("Maraîchage diversifié, 2,4 ha").'</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div class="util-card util-vignette">';
				$h .= '<div class="util-icon util-icon-round util-icon-commercialisation">'.\Asset::icon('basket3').'</div>';
				$h .= '<div>';
					$h .= '<div class="util-card-title">'.s("Un texte plus haut que l'image").'</div>';
					$h .= '<div class="util-card-info">'.s("L'image se cale alors sur le haut du texte, et non sur son milieu, pour rester à côté du titre.").'</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Vignettes à la suite").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-vignettes met des util-vignette à la suite, à la ligne quand la place manque : les producteurs qui livrent une distribution, par exemple. L'écart entre une image et son nom y est plus serré qu'entre deux vignettes, et les noms gardent une graisse normale, même dans une valeur de deck. Un intitulé peut précéder la première vignette sur la même ligne.").'</p>';

		$h .= '<div class="util-vignettes">';
			$h .= '<span class="util-annotation">'.s("en plus").'</span>';
			$h .= '<span class="util-vignette"><div class="util-icon util-icon-round util-icon-sm util-icon-logo">'.self::getLogoDemo().'</div><span>'.s("Le Verger des Combes").'</span></span>';
			$h .= '<span class="util-vignette"><span>'.s("Le Fournil du chemin").'</span></span>';
			$h .= '<span class="util-vignette"><span>'.s("Les Ruches de la côte").'</span></span>';
		$h .= '</div>';

		return $h;

	}

	/** Un logo large, rendu comme celui d'une structure, pour montrer qu'il tient entier dans sa pastille. */
	private static function getLogoDemo(): string {
		return '<div class="media-rectangle-view" style="background-image: url('.\Asset::getPath('main', 'logo.png', 'image').')"></div>';
	}

	/** L'identifiant choisit la couleur, d'où un identifiant par démonstration. */
	private static function getInitialsDemo(int $id, string $name): string {
		return \structure\StructureUi::getInitials(new \structure\Structure(['id' => $id, 'name' => $name]));
	}

	public function cards(): string {

		$h = '<p class="util-annotation">'.s("Une grille util-cards + le nombre de colonnes util-cards-1|2|3|4, et des util-card dedans. Les colonnes retombent à 2 sous 1200px puis à 1 sous 768px, sauf util-cards-4, qui attend 450px pour passer à une colonne.").'</p>';

		$h .= '<div class="util-cards util-cards-2">';

			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-title">util-card-title</div>';
				$h .= '<div class="util-card-info">util-card-info — texte secondaire</div>';
			$h .= '</div>';

			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-title">util-card-buttons</div>';
				$h .= '<div class="util-card-buttons">';
					$h .= '<a href="#" class="btn btn-primary">'.s("Valider").'</a>';
					$h .= '<a href="#" class="btn btn-outline-danger">'.s("Supprimer").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-card-link rend toute la carte cliquable quand elle mène à un seul endroit, la note ou la fiche qu'elle résume : le lien de son util-card-title s'étend sur toute la carte, qui se soulève au survol. Les liens, les boutons et les champs posés dans la carte gardent leur propre geste, comme le badge qui filtre la liste. La carte reste un div, et son titre porte le seul lien vers la destination. Modèle : la carte d'une note (NoteUi::getCard()).").'</p>';

		$h .= '<div class="util-cards util-cards-fill">';

			foreach([[s("Plan de culture 2027"), s("Tomates")], [s("Commande de semences"), s("Carottes")]] as [$title, $target]) {
				$h .= '<div class="util-card util-card-link">';
					$h .= '<div class="util-card-title"><a href="#">'.$title.'</a></div>';
					$h .= '<div class="util-card-info">'.s("18 oct.").'</div>';
					$h .= '<p class="util-text-clamp mt-1 mb-0">'.s("Revoir les rotations des planches du jardin du bas avant de commander.").'</p>';
					$h .= '<div class="mt-2"><a href="#" class="btn btn-badge btn-sm badge badge-outline badge-info">'.$target.'</a></div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation" style="margin-top: 1.5rem;">'.s("util-cards-fill remplace le nombre de colonnes quand il doit suivre la place réellement disponible et non la largeur de la fenêtre : autant de cartes par rang que la grille en loge à 14rem au moins, une seule colonne sous 450px. Une carte seule garde la largeur d'une carte au lieu de s'étirer sur la ligne.").'</p>';

		$h .= '<div class="util-cards util-cards-fill">';

			foreach([s("Mar. 15"), s("Jeu. 17"), s("Mar. 22"), s("Jeu. 24"), s("Mar. 29"), s("Jeu. 1er")] as $day) {
				$h .= '<div class="util-card">';
					$h .= '<div class="util-card-title">'.$day.'</div>';
					$h .= '<div class="util-card-info">util-card-info</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-cards-small range des cartes étroites qu'on parcourt en nombre, une vignette, deux lignes et un geste : autant par rang que la grille en loge à --cards-small-width au moins, donc deux au téléphone, là où util-cards-fill passe à une seule. Le geste propre à une carte reste dans son pied, et le bouton qui agit sur toutes se pose sous la grille. Premier emploi : la file des modèles 3D à examiner, dans l'administration.").'</p>';

		$h .= '<div class="util-cards util-cards-small">';

			foreach([
				[s("Manon Vasseur"), '20/10/2026 08:12'],
				[s("Jean-Baptiste Lefèvre-Duval"), '21/10/2026 17:45'],
				[s("Lucie Martin"), '22/10/2026 09:03'],
				[s("Thomas Roux"), '22/10/2026 14:30'],
				[s("Inès Garcia"), '23/10/2026 11:58'],
				[s("Hélène Moreau"), '24/10/2026 07:21'],
			] as [$name, $date]) {
				$h .= '<div class="util-card">';
					$h .= '<div class="util-preview util-preview-photo">'.\Asset::image('main', 'producers.webp').'</div>';
					$h .= '<div class="mt-1"><b>'.$name.'</b><div class="util-annotation">'.s("Importé le {value}", $date).'</div></div>';
					$h .= '<div class="util-card-foot"><a class="btn btn-outline-primary btn-sm">'.s("Retirer").'</a></div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<div class="mt-2"><a class="btn btn-primary">'.s("Tout valider").'</a></div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-card-foot se pose sur le dernier enfant d'une util-card, le pied qui porte ses boutons ou ses dates. Il descend au bas de la carte, et les pieds d'un rang s'alignent quelle que soit la longueur du texte au-dessus. La carte passe en colonne d'elle-même. Une carte qui espace déjà ses enfants par gap remet le padding-top du pied à 0 chez elle.").'</p>';

		$h .= '<div class="util-cards util-cards-3">';

			foreach([
				s("Semis sous abri."),
				s("Semis en pépinière, repiquage trois semaines plus tard, quand les plants ont leurs premières vraies feuilles."),
				s("Semis direct en planche, éclaircissage à dix centimètres, puis paillage dès que les plants dépassent la main. Arrosage léger et régulier jusqu'à la levée, puis selon la météo. La récolte commence huit semaines après le semis et s'étale sur un mois."),
			] as $text) {
				$h .= '<div class="util-card">';
					$h .= '<div class="util-card-title">'.s("Carotte").'</div>';
					$h .= '<p>'.$text.'</p>';
					$h .= '<div class="util-card-foot"><a class="btn btn-outline-primary btn-sm">'.s("Planifier").'</a></div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		\Asset::js('main', 'pilesAi.js');

		$h .= '<p class="util-annotation" style="margin-top: 1.5rem;">'.s("util-piles range des cartes de hauteurs inégales en colonnes, sans le trou qu'une grille laisse sous la plus courte d'un rang. MainPiles.stack(), posé en onrender, compte les colonnes que la place loge à 20rem (data-pile-width le change), et place chaque carte, dans l'ordre du DOM, sous la colonne la plus courte : chaque colonne est une util-piles-pile, que le script crée. Le rang d'origine est noté au premier empilement, donc un redimensionnement retrouve l'ordre. Sous la largeur d'une colonne, les cartes restent l'une sous l'autre dans leur ordre. Un écran qui masque des cartes appelle MainPiles.stack(board, true), qui recompose les colonnes même quand leur nombre ne change pas, et sans compter les cartes masquées. Le script se charge par \\Asset::js('main', 'pilesAi.js') chez chaque appelant.").'</p>';

		$h .= '<div class="util-piles" data-pile-width="14" onrender="MainPiles.stack(this)">';

			foreach([[s("Récolte"), 1], [s("Semis"), 4], [s("Plantation"), 2], [s("Désherbage"), 3], [s("Irrigation"), 1]] as [$title, $lines]) {
				$h .= '<div class="util-card">';
					$h .= '<div class="util-card-title">'.$title.' <span class="util-counter">'.$lines.'</span></div>';
					$h .= str_repeat('<div class="util-card-info">util-card-info</div>', $lines);
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("Une carte qui porte data-util-piles-uncounted se range à son rang comme les autres, mais sa hauteur ne compte pas dans celle de sa colonne : posée en tête, elle ne repousse pas la carte suivante dans la colonne voisine. C'est le pense-bête de la semaine du planning, au-dessus du premier bloc d'action.").'</p>';

		$h .= '<div class="util-piles" data-pile-width="14" onrender="MainPiles.stack(this)">';

			foreach([[s("Pense-bête"), 3, TRUE], [s("Récolte"), 2, FALSE], [s("Semis"), 1, FALSE], [s("Plantation"), 3, FALSE], [s("Désherbage"), 2, FALSE], [s("Irrigation"), 1, FALSE]] as [$title, $lines, $uncounted]) {
				$h .= '<div class="util-card"'.($uncounted ? ' data-util-piles-uncounted' : '').'>';
					$h .= '<div class="util-card-title">'.$title.' <span class="util-counter">'.$lines.'</span></div>';
					$h .= str_repeat('<div class="util-card-info">util-card-info</div>', $lines);
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("--util-piles-max, posée en CSS sur le util-piles, plafonne le nombre de colonnes : au-delà, les colonnes s'élargissent au lieu de se multiplier. Une media query la règle, ici deux colonnes au plus à partir de 992 px, et en dessous la largeur seule décide. C'est une entrée, que MainPiles.stack() lit avec ses mesures, alors que --util-piles est la sortie que le script écrit. Sans elle, ou à 0, le nombre ne suit que la largeur.").'</p>';

		$h .= '<div class="util-piles design-piles-max" data-pile-width="10" onrender="MainPiles.stack(this)">';

			foreach([[s("Récolte"), 2], [s("Semis"), 4], [s("Plantation"), 1], [s("Désherbage"), 3], [s("Irrigation"), 2], [s("Paillage"), 1]] as [$title, $lines]) {
				$h .= '<div class="util-card">';
					$h .= '<div class="util-card-title">'.$title.' <span class="util-counter">'.$lines.'</span></div>';
					$h .= str_repeat('<div class="util-card-info">util-card-info</div>', $lines);
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-note est une feuille punaisée, un peu de travers, pour un texte que l'utilisateur s'est écrit à lui-même : le pense-bête du planning, les notes d'une série ou d'un itinéraire, le commentaire interne d'une vente, d'une facture ou d'un catalogue. Un message du produit reste un util-block-info. La punaise, util-note-pin, est le premier enfant de la note et déborde au-dessus d'elle, dans l'écart avec le bloc précédent.").'</p>';

		$h .= '<div class="util-cards util-cards-2 mt-2">';

			$h .= '<div class="util-note">';
				$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';
				$h .= '<div class="util-title">';
					$h .= '<h3>'.s("Notes").'</h3>';
					$h .= '<a href="#" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('pencil-fill').'</a>';
				$h .= '</div>';
				$h .= s("Pucerons sur les premiers rangs, à surveiller après la pluie.");
			$h .= '</div>';

			$h .= '<div class="util-note">';
				$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';
				$h .= '<h4>'.s("Commentaire interne").'</h4>';
				$h .= s("Livrer par l'entrée de derrière.");
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-note-wide, posée avec util-note, donne à la note la largeur de son conteneur au lieu de la borner et de la centrer : elle tient alors les bords des blocs qui l'entourent dans une colonne, et penche moins. Elle porte une liste de lignes courtes, un pense-bête, pas un paragraphe, qui s'y lirait mal.").'</p>';

		$h .= '<div class="util-note util-note-wide mt-2">';
			$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';
			$h .= '<div class="util-rows">';
				$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('circle').'</a><div>'.s("Commander les plants de poireaux").'</div></div></div>';
				$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('circle').'</a><div>'.s("Rappeler le fournisseur de terreau").'</div></div></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation" style="margin-top: 1.5rem;">'.s("util-card-bleed se pose sur l'enfant d'une util-card ordinaire qui doit toucher ses bords, une carte interactive ou une image : il reprend le padding de la carte, et les coins de son arrondi quand il l'ouvre ou la ferme. Il est en position relative, et util-card-count s'y pose en haut à gauche pour une phrase de décompte (« 51 fermes sur 64 situées »), qui n'est ni un état (badge) ni un nombre seul (util-counter). En haut, parce que le bas à gauche d'une carte mapbox porte le logo que ses conditions d'usage imposent. La hauteur du contenu reste à la feuille du package.").'</p>';

		$h .= '<div class="util-card">';
			$h .= '<div class="util-card-bleed" style="height: 10rem; background-color: var(--background);">';
				$h .= '<div class="util-card-count font-sm">'.s("{located} fermes sur {total} situées", ['located' => 51, 'total' => 64]).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-card-badges pose des marques en haut à gauche d'une image, à la suite, par exemple le type d'une formation puis son auteur. Il se place dans un parent positionné, util-card-bleed ou l'image elle-même. Ses badges gardent leur fond plein et prennent une ombre, pour rester lisibles sur une photo. Les marques tiennent sur un rang. Quand la largeur manque, la dernière se coupe et finit par des points de suspension, la première garde sa largeur. La règle est écrite pour deux marques, un mot court puis un nom. Le nom coupé n'a pas d'info-bulle, il se lit en entier ailleurs sur l'écran ou sur la fiche de l'objet.").'</p>';

		$h .= '<div class="util-cards util-cards-3">';
			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-bleed bg-background" style="height: 8rem;">';
					$h .= '<div class="util-card-badges">';
						$h .= '<span class="badge badge-solid badge-production">'.s("Présentiel").'</span>';
						$h .= '<span class="badge badge-neutral">'.s("Groupement des agriculteurs biologiques de Bourgogne-Franche-Comté").'</span>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-bleed bg-muted" style="height: 8rem;">';
					$h .= '<div class="util-card-badges">';
						$h .= '<span class="badge badge-solid badge-info">'.s("En ligne").'</span>';
						$h .= '<span class="badge badge-neutral">'.s("Réseau des producteurs du Grand Est et des Ardennes").'</span>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-card-badges-wrap s'ajoute là où le nom doit se lire en entier, comme en tête d'une fiche. La marque qui ne tient pas à la suite passe alors au rang suivant, et son texte sur plusieurs lignes s'il dépasse encore la largeur de l'image. Un nom n'ayant pas de longueur bornée, les marques entrent alors dans le flux de leur parent, qui grandit avec elles et leur donne son remplissage.").'</p>';

		$h .= '<div class="util-cards util-cards-3">';
			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-badges util-card-badges-wrap">';
					$h .= '<span class="badge badge-solid badge-production">'.s("Présentiel").'</span>';
					$h .= '<span class="badge badge-neutral">'.s("Groupement des agriculteurs biologiques de Bourgogne-Franche-Comté").'</span>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function formChapters(): string {

		$h = '<p class="util-annotation">'.s("FormUi::cards() découpe un formulaire long en rubriques, chacune dans une util-card d'une grille util-cards-1. Chaque entrée porte title, content, et action quand le titre en porte une. La gouttière vient de la grille, deux cartes à la file n'ayant aucune marge entre elles, et le space-between de util-title aligne l'action à droite du titre.").'</p>';
		$h .= '<p class="util-annotation">'.s("À prendre quand un formulaire compte assez de champs pour qu'on y cherche où l'on en est. Un petit groupe de champs qui forme une notion à l'intérieur d'un formulaire garde form-fieldset, présenté plus bas.").'</p>';

		$formCards = new \util\FormUi();

		$h .= '<form class="form-columns form-columns-33 form-columns-xs">';

			$h .= $formCards->cards([
				['title' => s("L'engagement et les absences"), 'content' => $formCards->group(s("Distributions minimum"), $formCards->text('design-cards-minimum', '4')).$formCards->group(s("Délai d'annulation"), $formCards->text('design-cards-delay', '48'))],
				['title' => s("Les distributions"), 'content' => $formCards->group(s("Fin des commandes"), $formCards->text('design-cards-before', '2'))],
				['title' => s("Le document d'engagement"), 'action' => '<a href="#" class="btn btn-outline-primary">'.s("Prévisualiser").'</a>', 'content' => $formCards->group(s("Conditions particulières"), $formCards->textarea('design-cards-conditions', s("Les légumes non retirés le jour de la distribution restent acquis au producteur.")))],
			]);

		$h .= $formCards->close();

		return $h;

	}

	public function welcome(): string {

		$h = '<p class="util-annotation">'.s("util-welcome, avec util-welcome-text (util-welcome-title, le corps en HTML rendu par l'appelant, puis les gestes) et util-welcome-mascot. En tête d'un écran qu'on découvre, ou sous le bloc qu'il prolonge quand il invite à essayer une nouveauté, le titre suivi de util-stamp-new (le TPE SumUp Solo sous le compte SumUp, SumupUi::getReadersWelcome()). La mascotte disparaît sous 576px. Les gestes vont dans util-welcome-buttons : boutons à largeur naturelle, jamais étirés.").'</p>';

		$h .= \main\DesignUi::getWelcome(
			s("Votre structure est créée sur {siteName} !"),
			'<p>'.s("Le message d'accueil, en une phrase, et ce qu'on attend ensuite.").'</p>',
			\Asset::svg('main', 'friends/abeille.svg'),
			'<a href="#" class="btn btn-primary btn-lg">'.s("Le geste principal").'</a>'.
			'<a href="#" class="btn btn-outline-primary btn-lg">'.s("En savoir plus").'</a>'
		);

		return $h;

	}

	public function campaign(): string {

		$h = '<p class="util-annotation">'.s("util-campaign, avec une util-campaign-step par temps de la campagne : is-done pour ce qui est passé ou déjà ouvert, is-active pour l'étape en cours (la seule à porter un cadre), is-todo pour ce qui attend. Une étape qui mène quelque part s'écrit en a et se soulève au survol, comme un util-deck lien.").'</p>';

		$h .= '<div style="background-color: var(--color-bg-vert-bleu); padding: 1.5rem 1rem; border-radius: var(--radius)">';

			$h .= '<div class="util-campaign">';

				$campaign = [
					['state' => 'done', 'marker' => '1', 'date' => s("Jusqu'au 24 août"), 'label' => s("Première étape"), 'description' => s("Ce qui est passé garde sa pastille pleine, sans cadre.")],
					['state' => 'active', 'marker' => '2', 'date' => s("À partir du 25 août"), 'label' => s("Étape en cours"), 'description' => s("La seule à porter un cadre et un halo : c'est elle qu'on cherche."), 'href' => '#'],
					['state' => 'todo', 'marker' => \Asset::icon('lock-fill'), 'date' => s("À partir du 1er octobre"), 'label' => s("Étape à venir"), 'description' => s("Le blanc devient translucide et la pastille reste creuse.")],
				];

				foreach($campaign as $step) {

					$tag = isset($step['href']) ? 'a' : 'div';

					$h .= '<'.$tag.' class="util-campaign-step is-'.$step['state'].'"'.(isset($step['href']) ? ' href="'.$step['href'].'"' : '').'>';
						$h .= '<div class="util-campaign-marker">'.$step['marker'].'</div>';
						$h .= '<div class="util-campaign-content">';
							$h .= '<div class="util-campaign-date">'.$step['date'].'</div>';
							$h .= '<div class="util-campaign-label">'.$step['label'].'</div>';
							$h .= '<div class="util-campaign-description">'.$step['description'].'</div>';
						$h .= '</div>';
					$h .= '</'.$tag.'>';

				}

			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une étape seule, à la place d'une carte").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une étape seule peut aussi prendre la place d'une carte dans une grille util-cards, sans fond teinté : c'est un temps à faire avant les cartes qui suivent (première déclaration de TVA, période annuelle à confirmer).").'</p>';

		$h .= '<div class="util-cards util-cards-3 util-cards-start">';

			$h .= '<div class="util-campaign">';
				$h .= '<a class="util-campaign-step is-active" href="#">';
					$h .= '<div class="util-campaign-marker">'.\Asset::icon('receipt').'</div>';
					$h .= '<div class="util-campaign-content">';
						$h .= '<div class="util-campaign-date">'.s("Exercice 2025 - 2026").'</div>';
						$h .= '<div class="util-campaign-label">'.s("Étape dans une grille").'</div>';
						$h .= '<div class="util-campaign-description">'.s("Elle ouvre le geste qui manque aux cartes voisines.").'</div>';
					$h .= '</div>';
				$h .= '</a>';
			$h .= '</div>';

			$h .= '<div class="util-card"><div class="util-card-title">'.s("Carte voisine").'</div></div>';

		$h .= '</div>';

		return $h;

	}

	public function variation(): string {

		$h = '<p class="util-annotation">'.s("util-variation prend un modificateur util-variation-up, -down ou -neutral. À réserver aux variations qui se lisent dans un flux dense, une ligne de tableau ou un prix, là où util-figure-variation convient sous un chiffre clé.").'</p>';

		$h .= '<p class="mb-2" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">';

			$variations = [
				['', s("nouveau")],
				['', s("= stable")],
				['util-variation-up', '▲ 12 %'],
				['util-variation-down', '▼ 8 %'],
				['util-variation-neutral', '▲ 4 %'],
			];

			foreach($variations as [$modifier, $label]) {
				$h .= '<span class="util-variation '.$modifier.'">'.$label.'</span>';
			}

		$h .= '</p>';

		return $h;

	}

	public function reliability(): string {

		$h = '<p class="util-annotation">'.s("util-confidence et un niveau is-1|2|3, trois barres dont les allumées portent is-on : orange pour une valeur peu fiable, gris pour une valeur moyennement fiable, vert pour une valeur fiable. Le nom du niveau va en title, la marque suivant la valeur ou le libellé qu'elle qualifie. util-confidence-levels en est la légende, une util-confidence-level par niveau (marque, nom en gras, critère), dont le critère passe sous le nom à 575px. Repris de la mercuriale et du référentiel de fertilisation.").'</p>';

		$h .= '<div class="util-block-info mb-2">';
			$h .= '<h4>'.s("La fiabilité des valeurs").'</h4>';
			$h .= '<div class="util-confidence-levels">';

				$levels = [
					3 => [s("Assez fiable"), s("Plusieurs sources cohérentes entre elles")],
					2 => [s("Moyennement fiable"), s("Une seule source ou agrégé à partir de sources différentes qui ne se recouvrent pas")],
					1 => [s("Peu fiable"), s("Sources différentes et l'exporté dépasse le mobilisé")],
				];

				foreach($levels as $level => [$name, $criterion]) {

					$h .= '<div class="util-confidence-level">';
						$h .= '<span class="util-confidence is-'.$level.'" title="'.encode($name).'">';
							for($bar = 1; $bar <= 3; $bar++) {
								$h .= '<span'.(($bar <= $level) ? ' class="is-on"' : '').'></span>';
							}
						$h .= '</span>';
						$h .= '<b>'.$name.'</b>';
						$h .= '<span>'.$criterion.'</span>';
					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function overline(): string {

		$h = '<p class="util-annotation">'.s("util-label écrit en capitales grasses de 0,9 rem, en couleur neutre, pour nommer une colonne ou un bloc sans lui donner un titre. Le sur-titre du bandeau de filtres en est le premier usage, et util-filter-label s'y raccroche en ajoutant sa marge.").'</p>';

		$h .= '<div class="mb-2">';
			$h .= '<div class="util-label">'.s("L'équipe du groupe").'</div>';
			$h .= '<p class="mb-0">'.s("Ce que la colonne contient.").'</p>';
		$h .= '</div>';

		return $h;

	}

	public function filters(): string {

		$h = '<p class="util-annotation">'.s("util-filters, et un util-filter par groupe, chacun avec son util-filter-label et un util-filter-options qui contient les util-filter-option (+ is-selected). Les options sont posées sur un rail clair dans la teinte de la section, ajusté à leur largeur ; l'option retenue est une pastille blanche au texte de marque, détachée par son ombre. Le survol ne vaut qu'à la souris.").'</p>';

		$h .= '<div class="util-filters mb-2">';

			$filtres = [
				[s("Mode de production"), [[s("Tous"), FALSE], [s("Bio"), TRUE], [s("Conventionnel"), FALSE]]],
				[s("Référentiel"), [[s("Tous"), TRUE], [s("Légumes"), FALSE], [s("Fruits"), FALSE]]],
			];

			foreach($filtres as [$label, $options]) {

				$h .= '<div class="util-filter">';
					$h .= '<div class="util-filter-label">'.$label.'</div>';
					$h .= '<div class="util-filter-options">';

						foreach($options as [$option, $selected]) {
							$h .= '<a class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$option.'</a>';
						}

					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function screenSettings(): string {

		$h = '<p class="util-annotation">'.s("Le même bandeau pour des réglages qui s'appliquent tout de suite : un util-filter par réglage, des util-filter-option pour un choix parmi quelques valeurs, ou un TextUi::switch() seul sous son libellé pour un réglage binaire. Le libellé de l'interrupteur est alors un lien a.util-filter-label qui porte les mêmes attributs que lui, pour que son nom bascule aussi le réglage ; l'interrupteur s'aligne sur les options posées dans leur rail.").'</p>';

		$h .= '<div class="util-filters mb-2">';

			$h .= '<div class="util-filter">';
				$h .= '<div class="util-filter-label">'.s("Densité").'</div>';
				$h .= '<div class="util-filter-options">';
					$h .= '<a class="util-filter-option">'.s("Plus dense").'</a>';
					$h .= '<a class="util-filter-option is-selected">'.s("Normal").'</a>';
					$h .= '<a class="util-filter-option">'.s("Moins dense").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-filter">';
				$h .= '<a class="util-filter-label">'.s("Couleur des interventions").'</a>';
				$h .= \util\TextUi::switch([], on: TRUE);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function formOptions(): string {

		$h = '<p class="util-annotation">'.s("util-filter-options-field autour de FormUi::checkboxes() ou FormUi::radios() : les vraies cases du formulaire prennent le dessin des util-filter-option, rail et pastille compris. L'option cochée garde son poids, sa largeur ne bouge pas au choix.").'</p>';

		$form = new \util\FormUi();

		$h .= '<div class="util-filter-options-field mb-2">';
			$h .= $form->radios('designDay', [1 => s("Lun."), 2 => s("Mar."), 3 => s("Mer."), 4 => s("Jeu."), 5 => s("Ven.")], 2, ['required' => TRUE]);
		$h .= '</div>';

		return $h;

	}

	public function dateChecks(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("util-dates range un util-dates-month par mois, chacun avec son util-label et une grille util-dates-grid de jetons util-date rendus par FormUi::checkbox(). La date cochée prend le fond de marque. Deux raisons verrouillent une date, sur le même fond gris : elle existe déjà, cochée avec le cadenas ; ou sa prise des commandes serait déjà fermée, décochée avec l'horloge de la période de commande.").'</p>';

		$h .= '<div class="util-dates">';
			$h .= '<div class="util-dates-month">';
				$h .= '<div class="util-label mb-1">'.s("Janvier 2027").'</div>';
				$h .= '<div class="util-dates-grid">';

					$dates = [
						['2027-01-05', s("Mar. 5"), TRUE, NULL],
						['2027-01-08', s("Ven. 8"), FALSE, NULL],
						['2027-01-12', s("Mar. 12"), TRUE, 'lock-fill'],
						['2027-01-15', s("Ven. 15"), FALSE, 'clock'],
					];

					foreach($dates as [$date, $label, $checked, $icon]) {

						$h .= $form->checkbox('designDates[]', $date, [
							'checked' => $checked,
							'disabled' => ($icon !== NULL) ? TRUE : NULL,
							'callbackFieldAttributes' => ['class' => 'util-date'],
							'callbackLabel' => fn($input) => $input.'<b>'.$label.'</b>'.(($icon !== NULL) ? \Asset::icon($icon) : '')
						]);

					}

				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function omnibox(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("\\main\\OmniboxUi::get() rend util-omnibox, avec un util-omnibox-field qui porte la classe form-control du framework : le cadre, le rayon et le focus ne sont pas redessinés, une recherche se lit comme un champ de saisie.").'</p>';
		$h .= '<p class="util-annotation">'.s("Les suggestions vivent dans util-omnibox-list, découpée en un util-omnibox-family par famille, chacune avec son util-omnibox-group en tête.").'</p>';
		$h .= '<p class="util-annotation">'.s("Ce qu'une famille garde en réserve s'affiche en util-omnibox-rest, en dernière ligne de cette famille et non sur son en-tête : là, le nombre touchait la dernière entrée de la famille précédente et semblait la compter.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le modificateur util-omnibox-lg donne un cran de hauteur, comme form-control-lg sur un champ ordinaire : à réserver aux écrans dont la recherche est l'objet, le plan de culture par exemple, et non à un réglage parmi d'autres dans une barre de filtres.").'</p>';
		$h .= '<p class="util-annotation">'.s("Avec singleByGroup, un critère par famille : choisir une seconde espèce remplace la première. C'est le réglage du plan d'assolement, où deux espèces à la fois faussaient les compteurs et la sélection.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une suggestion se trouve aussi par terms, des mots que la recherche lit en plus du libellé sans jamais les afficher, comme une question de la FAQ qu'on trouve par sa réponse et ses symptômes. Dans la démonstration, « altise » propose le filet anti-insectes.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une suggestion porte en tête un util-omnibox-item-icon, rempli par icon, l'adresse d'un symbole du sprite, ou par vignette, du HTML rendu par le serveur pour ce qu'aucun sprite ne porte, l'avatar d'une personne ou les initiales d'une espèce propre à une structure. Dès qu'une entrée de la liste en porte un, toutes réservent la place. Le jeton posé par une suggestion garde son nom seul. Premier emploi de vignette, la recherche des Interventions du planning.").'</p>';
		$h .= '<p class="util-annotation">'.s("Au doigt, la recherche prend l'écran : la boîte part dans un util-omnibox-wrapper fixé en haut, un util-omnibox-placeholder marque l'endroit où elle reviendra, le body prend util-omnibox-fullscreen-open qui verrouille son défilement, et la liste déroule sous le champ au lieu de flotter. Échap ferme d'abord la liste et garde ce qui est tapé ou posé, puis le plein écran ; sur un champ vide, sans texte ni jeton, il referme tout d'un coup.").'</p>';
		$h .= '<p class="util-annotation">'.s("Avec suggestionsIdle, une omnibox distante propose sa propre liste tant que le champ est vide et sans jeton, au format de suggestions : le focus ne coûte aucune lecture au serveur, la première arrive avec la première frappe. Premier emploi, le champ de saisie du menu, qui propose vide les gestes du « + ».").'</p>';
		$h .= '<p class="util-annotation">'.s("Ces propriétés règlent la place de la boîte sans variante. --util-omnibox-max-width lève le plafond de 30rem d'une omnibox qui prend toute la largeur de ce qui la porte. --util-omnibox-list-top avec --util-omnibox-list-bottom ouvrent la liste au-dessus d'un champ posé en bas de l'écran. --util-omnibox-list-height remplace le plafond de la liste, 20rem, ou 50vh sous 768 px. --util-omnibox-list-left avec --util-omnibox-list-width élargissent vers la gauche la liste d'un champ plus étroit qu'elle, comme la recherche d'en-tête ou la case d'un jour de récolte du planning. Le champ du menu se sert des deux premières à la tablette et au téléphone. Il pose aussi --util-omnibox-list-height, d'après la place que l'écran laisse à sa liste, mesurée en JS. Le récapitulatif d'une vente la prend en entier, et au bureau ses autres listes la prennent aussi.").'</p>';

		$h .= '<div class="mb-2">';

			$h .= \main\OmniboxUi::get(
				// Neuf espèces pour que la famille dépasse le plafond de huit : la démo
				// doit montrer le util-omnibox-rest, qui ne se voit que tronqué.
				suggestions: [
					['group' => s("Espèces"), 'value' => 'carotte', 'label' => s("Carotte"), 'icon' => \plant\PlantUi::getVignetteHref('carotte')],
					['group' => s("Espèces"), 'value' => 'poireau', 'label' => s("Poireau"), 'icon' => \plant\PlantUi::getVignetteHref('poireau')],
					// Sans vignette, et dans les huit premières pour se rendre : le sprite n'en
					// a pas pour le radis, et la démo doit montrer la place réservée qui garde
					// les libellés alignés, plutôt qu'un symbole absent qui ne rendrait rien
					// sans rien signaler.
					['group' => s("Espèces"), 'value' => 'radis', 'label' => s("Radis")],
					['group' => s("Espèces"), 'value' => 'betterave', 'label' => s("Betterave"), 'icon' => \plant\PlantUi::getVignetteHref('betterave')],
					['group' => s("Espèces"), 'value' => 'blette', 'label' => s("Blette"), 'icon' => \plant\PlantUi::getVignetteHref('blette')],
					['group' => s("Espèces"), 'value' => 'courgette', 'label' => s("Courgette"), 'icon' => \plant\PlantUi::getVignetteHref('courgette')],
					['group' => s("Espèces"), 'value' => 'epinard', 'label' => s("Épinard"), 'icon' => \plant\PlantUi::getVignetteHref('epinard')],
					['group' => s("Espèces"), 'value' => 'fenouil', 'label' => s("Fenouil"), 'icon' => \plant\PlantUi::getVignetteHref('fenouil')],
					['group' => s("Espèces"), 'value' => 'haricot', 'label' => s("Haricot"), 'icon' => \plant\PlantUi::getVignetteHref('haricot')],
					['group' => s("Espèces"), 'value' => 'laitue', 'label' => s("Laitue"), 'icon' => \plant\PlantUi::getVignetteHref('laitue')],
					// Le detail lève l'ambiguïté d'un nom porté par deux espèces.
					['group' => s("Variétés"), 'value' => 'nantaise', 'label' => s("Nantaise"), 'detail' => s("Carotte")],
					['group' => s("Variétés"), 'value' => 'touchon', 'label' => s("Touchon"), 'detail' => s("Carotte")],
					// Taper « altise » le propose : terms est lu par la recherche, jamais affiché.
					['group' => s("Matériel"), 'value' => 'filet', 'label' => s("Filet anti-insectes"), 'terms' => s("voile altise mouche protection")],
					['group' => s("Personnes"), 'value' => 'cassandre', 'label' => s("Cassandre Chignec"), 'vignette' => \user\UserUi::getVignette(new \user\User(['id' => 3, 'firstName' => 'Cassandre', 'lastName' => 'Chignec', 'vignette' => NULL]), '1.25rem')],
					['group' => s("Espèces de la structure"), 'value' => 'kiwano', 'label' => s("Kiwano"), 'vignette' => \plant\PlantUi::getVignette(new \plant\Plant(['id' => 1, 'name' => s("Kiwano"), 'fqn' => NULL, 'vignette' => NULL]), '1.25rem')],
				],
				tokens: [
					['group' => s("Matériel"), 'value' => 'bache', 'label' => s("Bâche bio 140cm")],
				],
				placeholder: s("Espèce, variété, matériel..."),
				count: s("4 séries"),
				class: 'util-omnibox-lg'
			);

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("Avec fullscreen: FALSE, OmniboxUi::get() pose data-inline, et la liste reste sous le champ au doigt au lieu de passer en plein écran. Pour un champ qu'on remplit en place sans quitter ce qu'on lit autour, comme le champ de saisie et la case d'un jour de récolte.").'</p>';

		$h .= '<h4 id="design-omnibox-bottom" class="mt-2">'.s("Un champ posé en bas de l'écran").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec fullscreenBottom: TRUE, OmniboxUi::get() pose data-fullscreen=\"bottom\", pour un champ posé en bas de l'écran, celui du menu hors du bureau. Il part en plein écran dès que la nav passe en bas, sous 1100 px de large ou 600 px de haut, au doigt comme à la souris, et reste en place au-dessus. L'enveloppe prend util-omnibox-wrapper-bottom et se cale sur la zone visible, que le clavier du téléphone réduit : le champ reste juste au-dessus du clavier, la liste se pose au-dessus de lui, et une util-omnibox-back remplace la loupe pour en sortir. Sur les écrans qui portent ce champ, le toast passe en haut le temps du plein écran.").'</p>';

		$h .= '<div class="mb-2">';
			$h .= \main\OmniboxUi::get(
				suggestions: [
					['group' => s("Gestes"), 'value' => 'recolte', 'label' => s("Récolter")],
					['group' => s("Gestes"), 'value' => 'vente', 'label' => s("Vendre")],
					['group' => s("Gestes"), 'value' => 'temps', 'label' => s("Noter du temps de travail")],
				],
				placeholder: s("Saisir..."),
				fullscreenBottom: TRUE
			);
		$h .= '</div>';

		$h .= '<h4 id="design-omnibox-name" class="mt-2">'.s("Un champ de formulaire à jetons").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec name, OmniboxUi::get() devient un champ de formulaire, ici le rattachement d'une note. Chaque jeton poste sa valeur dans un champ caché name[], rendu par le serveur pour les jetons déjà posés et tenu à jour par le JS quand on en ajoute, en retire ou efface tout. Les champs cachés suivent la boîte sans y vivre. Au doigt, la boîte part dans util-omnibox-wrapper, hors du formulaire, et ils restent dans le formulaire à la suite de util-omnibox-placeholder. Un envoi pendant que la liste est ouverte garde ainsi les jetons.").'</p>';

		$h .= $form->open('design-omnibox-form', ['class' => 'mb-2']);
			$h .= $form->group(
				s("Rattacher à"),
				\main\OmniboxUi::get(
					suggestions: [
						['group' => s("Espèces"), 'value' => 'plant-blette', 'label' => s("Blette"), 'icon' => \plant\PlantUi::getVignetteHref('blette')],
						['group' => s("Espèces"), 'value' => 'plant-carotte', 'label' => s("Carotte"), 'icon' => \plant\PlantUi::getVignetteHref('carotte')],
						['group' => s("Espèces"), 'value' => 'plant-poireau', 'label' => s("Poireau"), 'icon' => \plant\PlantUi::getVignetteHref('poireau')],
						['group' => s("Interventions"), 'value' => 'action-desherbage', 'label' => s("Désherbage thermique")],
						['group' => s("Interventions"), 'value' => 'action-recolte', 'label' => s("Récolte")],
						['group' => s("Planches"), 'value' => 'bed-serre', 'label' => s("Grande serre du haut, planche 12")],
						['group' => s("Planches"), 'value' => 'bed-jardin', 'label' => s("Jardin du bas, planche 3")],
					],
					tokens: [
						['group' => s("Espèces"), 'value' => 'plant-blette', 'label' => s("Blette")],
						['group' => s("Planches"), 'value' => 'bed-serre', 'label' => s("Grande serre du haut, planche 12")],
					],
					placeholder: s("Espèce, intervention, planche..."),
					name: 'designTargets'
				)
			);
		$h .= $form->close();

		$h .= '<h4 class="mt-2">'.s("La recherche dans l'en-tête d'un écran").'</h4>';
		$h .= '<p class="util-annotation">'.s("La recherche d'un écran se pose dans son en-tête, à droite du titre et avant les gestes, par l'argument search de \\main\\DesignUi::getPageHeader(). Elle passe sous le titre en dessous de 768 px, dans le bandeau. Quand l'écran n'a rien à chercher, l'appelant passe la chaîne vide et l'en-tête reste celui d'un écran sans recherche.").'</p>';

		$h .= '<div class="mb-2">';
			$h .= \main\DesignUi::getPageHeader(
				s("Le grand livre"),
				context: s("Exercice 2026"),
				tools: '<a class="btn btn-outline-secondary">'.\Asset::icon('file-pdf').'<span class="hide-xs-down"> '.s("PDF").'</span></a>',
				create: '<a class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Enregistrer une écriture").'</span></a>',
				pages: new \main\DesignUi()->getPages([
					['url' => '#', 'label' => s("Le grand livre"), 'selected' => TRUE],
					['url' => '#', 'label' => s("Le grand livre des clients"), 'selected' => FALSE],
				]),
				search: \main\OmniboxUi::get(
					suggestions: [
						['group' => s("Comptes"), 'value' => '401', 'label' => s("401 Fournisseurs")],
						['group' => s("Comptes"), 'value' => '411', 'label' => s("411 Clients")],
					],
					placeholder: s("Compte, tiers, montant..."),
					count: s("128 écritures")
				)
			);
		$h .= '</div>';

		$months = array_map(fn(int $month) => \util\DateUi::getMonthName($month).' 2026', range(1, 12));
		$suggestionsMonths = array_map(fn(string $month) => ['group' => s("Période"), 'label' => $month, 'url' => '#design-omnibox-folded'], $months);

		$h .= '<h4 id="design-omnibox-folded" class="mt-2">'.s("La recherche repliée en loupe").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec folded, la recherche de l'en-tête se replie en util-omnibox-folded : une loupe util-omnibox-unfold tant que rien n'est cherché, qui déplie le champ et y met le curseur, et se replie quand on en sort sans rien poser. Les jetons quittent le champ pour util-omnibox-tokens, une rangée sous le titre suivie de util-omnibox-clear-all, où chacun se lit en entier. Au bureau, un critère posé garde le champ ouvert, décompte dedans ; sous 768 px, le champ reste une loupe et le décompte passe au bout de la rangée, en util-omnibox-tokens-count. La boîte et util-page-header-search passent en display: contents, et l'en-tête place util-omnibox-main dans l'aire search et la rangée dans l'aire filters. À réserver aux écrans où chercher est un geste parmi d'autres : les écrans de la comptabilité. La foire aux questions, dont la recherche est l'objet, garde son champ ouvert.").'</p>';

		$h .= '<div class="mb-2">';
			$h .= \main\DesignUi::getPageHeader(
				s("Le livre journal"),
				context: s("Exercice 2026"),
				create: '<a class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Enregistrer une écriture").'</span></a>',
				search: \main\OmniboxUi::get(
					suggestions: $suggestionsMonths,
					tokens: [
						['group' => s("Période"), 'value' => '2026-03', 'label' => s("mars 2026"), 'url' => '#design-omnibox-folded'],
					],
					placeholder: s("Compte, tiers, libellé, montant..."),
					count: s("192 écritures"),
					clear: '#design-omnibox-folded',
					folded: TRUE,
					chips: [s("Période")]
				)
			);
		$h .= '</div>';

		$h .= '<h4 id="design-omnibox-chips" class="mt-2">'.s("Des choix courts en puces").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec chips, les familles nommées se rendent en util-omnibox-family-chips : leurs choix courts, des mois, des moyens de paiement, débit et crédit, se suivent en puces sur une ou deux lignes au lieu d'une ligne chacun. Une telle famille montre jusqu'à vingt-quatre choix avant sa réserve, contre huit pour une famille en lignes, qui se montre entière quand on pose uncapped sur ses entrées, comme sur les articles du récapitulatif d'une vente. Les flèches les parcourent dans l'ordre de lecture.").'</p>';

		$h .= '<div class="util-omnibox-list is-open mb-2" style="position: static; max-width: 26rem;">';
			$h .= '<div class="util-omnibox-family-chips">';
				$h .= '<div class="util-omnibox-group">'.s("Période").'</div>';
				foreach($months as $month) {
					$h .= '<a class="util-omnibox-item">'.$month.'</a>';
				}
			$h .= '</div>';
			$h .= '<div class="util-omnibox-group">'.s("Compte").'</div>';
			$h .= '<a class="util-omnibox-item">'.s("401 Fournisseurs").'</a>';
			$h .= '<a class="util-omnibox-item">'.s("411 Clients").'</a>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une puce retenue dans la liste").'</h4>';
		$h .= '<p class="util-annotation">'.s("is-selected sur une puce marque la valeur retenue d'une famille qui reste dans la liste au lieu de partir en jeton : l'état d'une vente dans le récapitulatif du champ de saisie, que l'on change en touchant une autre puce, ou la variété et le calibre d'une récolte, où toucher de nouveau la puce retenue la décoche. La suggestion le demande par selected.").'</p>';

		$h .= '<div class="util-omnibox-list is-open mb-2" style="position: static; max-width: 26rem;">';
			$h .= '<div class="util-omnibox-family-chips">';
				$h .= '<div class="util-omnibox-group">'.s("État").'</div>';
				$h .= '<a class="util-omnibox-item">'.s("Brouillon").'</a>';
				$h .= '<a class="util-omnibox-item is-selected">'.s("Confirmé").'</a>';
				$h .= '<a class="util-omnibox-item is-cursor">'.s("Préparé").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une suggestion qui ne se choisit pas").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une suggestion qui porte notice devient un util-omnibox-notice, une ligne qui se lit sans se choisir. Ce n'est pas un lien, les flèches et Entrée la sautent, et le survol ne la marque pas. Premier emploi : le refus du champ de saisie. Son rouge est réservé au refus. Quand la liste n'a rien à proposer, notice vaut muted et la ligne devient util-omnibox-notice-muted, sans fond, à l'encre des en-têtes de famille.").'</p>';

		$h .= '<div class="util-omnibox-list is-open mb-2" style="position: static; max-width: 22rem;">';
			$h .= '<div class="util-omnibox-group">'.s("À corriger").'</div>';
			$h .= '<div class="util-omnibox-notice">'.s("Comptez en kilos, en pièces ou en bottes.").'</div>';
			$h .= '<div class="util-omnibox-group">'.s("Client").'</div>';
			$h .= '<div class="util-omnibox-notice util-omnibox-notice-muted">'.s("Vous n'avez encore aucun client.").'</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une suggestion sur deux lignes, un jeton avec son icône").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une suggestion qui porte sub prend une seconde ligne, où util-omnibox-item-body empile le libellé et util-omnibox-item-sub, au corps et à la couleur atténuée du détail, qui passe à la ligne au lieu d'être coupée. sub est du HTML rendu par le serveur, le libellé reste du texte. Le champ de saisie s'en sert le premier, pour écrire les lieux d'une série au format du planning.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un jeton posé par UtilOmnibox.addToken() avec une icône et une pastille porte util-omnibox-token-icon en tête, taillée comme util-omnibox-item-icon, et la pastille après le libellé. Le champ de saisie s'en sert le premier, pour la série choisie qui remplace le jeton de l'espèce.").'</p>';

		$vignette = '<svg class="util-omnibox-token-icon"><use xlink:href="'.\plant\PlantUi::getVignetteHref('radis-botte').'"></use></svg>';
		$start = '<span class="plant-start plant-start-background design-sample-plant">'.s("s{value}", 34).'</span>';
		$places = encode(s("Hangar")).' '.\Asset::icon('chevron-right').' 15o';

		$h .= '<div class="util-omnibox util-omnibox-lg is-filled mb-2" style="max-width: 22rem;">';
			$h .= '<div class="util-omnibox-field form-control">';
				$h .= \Asset::icon('search');
				$h .= '<a class="util-omnibox-token">'.$vignette.'<span class="util-omnibox-token-label">'.s("Radis d'automne").'</span>'.$start.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un jeton plus long que le champ").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un nom plus long que le champ se coupe dans sa pastille, en points de suspension sur util-omnibox-token-label. L'icône, la pastille et la croix gardent leur place, et la loupe reste sur la ligne du premier jeton. Le nom entier reste dans data-label, que lit la page qui filtre.").'</p>';

		$h .= '<div class="util-omnibox util-omnibox-lg is-filled mb-2" style="max-width: 16rem;">';
			$h .= '<div class="util-omnibox-field form-control">';
				$h .= \Asset::icon('search');
				$h .= '<a class="util-omnibox-token">'.$vignette.'<span class="util-omnibox-token-label">'.s("Radis rond rouge à bout blanc").'</span>'.$start.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="mb-2" style="max-width: 16rem;">';
			$h .= \main\OmniboxUi::get(
				tokens: [
					['group' => s("Interventions"), 'value' => 'desherbage', 'label' => s("Désherbage thermique des pommes de terre primeur")],
					['group' => s("Personnes"), 'value' => 'cassandre', 'label' => s("Cassandre")],
				]
			);
		$h .= '</div>';

		$h .= '<div class="util-omnibox-list is-open mb-2" style="position: static; max-width: 22rem;">';
			$h .= '<div class="util-omnibox-group">'.s("Quelle série de radis ?").'</div>';
			$h .= '<a class="util-omnibox-item is-cursor">';
				$h .= '<svg class="util-omnibox-item-icon"><use xlink:href="'.\plant\PlantUi::getVignetteHref('radis-botte').'"></use></svg>';
				$h .= '<span class="util-omnibox-item-body"><span>'.s("Radis d'automne").'</span><span class="util-omnibox-item-sub">'.s("s{value}", 34).' · '.$places.'</span></span>';
			$h .= '</a>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un champ sans loupe").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec util-omnibox-plain, posé par l'option searchIcon de OmniboxUi::get(), le champ n'a pas de loupe et ne se lit plus comme une recherche. Le jeton prend alors toute la ligne. À réserver à un champ où l'on tape une valeur, comme la case d'un jour de récolte du planning.").'</p>';

		$h .= '<div class="util-omnibox util-omnibox-plain is-filled mb-2">';
			$h .= '<div class="util-omnibox-field form-control">';
				$h .= '<a class="util-omnibox-token"><span class="util-omnibox-token-label">'.s("Kilogrammes").'</span>'.\Asset::icon('x-lg').'</a>';
				$h .= '<div class="util-omnibox-tail"><input type="text" class="util-omnibox-input" value="12"/></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 id="design-omnibox-scroll" class="mt-2">'.s("Un champ qui garde sa largeur").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec scroll, OmniboxUi::get() pose util-omnibox-scroll : le champ garde sa largeur et sa hauteur quel que soit le nombre de jetons. Les jetons restent entiers, et le texte tapé aussi, à leur droite et à sa largeur (field-sizing, et sous Firefox l'attribut size, posé au rendu et que UtilOmnibox.sizeInput() tient à jour quand le texte change) ; ce qui ne tient pas fait défiler leur piste, util-omnibox-track, sur une ligne et sans barre. La croix d'effacement reste hors de la piste, au bout du champ, visible quoi qu'il défile, et se montre dès qu'il y a du texte tapé, pas seulement des jetons. Pour un champ logé dans une colonne qu'il ne doit pas élargir, comme la case d'un jour de récolte du planning.").'</p>';

		$h .= '<div class="util-omnibox util-omnibox-plain util-omnibox-scroll is-filled mb-2" style="width: 12.5rem;">';
			$h .= '<div class="util-omnibox-field form-control">';
				// Le champ de saisie fait défiler la piste au bout à chaque lecture (`EntryField.load()`) : la
				// démonstration, sans lecture, s'y pose au rendu.
				$h .= '<div class="util-omnibox-track" onrender="this.scrollLeft = this.scrollWidth">';
					$h .= '<a class="util-omnibox-token"><span class="util-omnibox-token-label">'.s("Black Beauty").'</span>'.\Asset::icon('x-lg').'</a>';
					$h .= '<a class="util-omnibox-token"><span class="util-omnibox-token-label">'.s("Gros").'</span>'.\Asset::icon('x-lg').'</a>';
					$h .= '<div class="util-omnibox-tail"><input type="text" class="util-omnibox-input" value="12" size="3"/></div>';
				$h .= '</div>';
				$h .= '<a class="util-omnibox-clear">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function valueDetail(): string {

		$h = '<p class="util-annotation">'.s("util-keypad-field est une valeur lue, alignée à droite comme un nombre, suivie d'un chevron parce que la toucher en ouvre le détail. Une valeur trop longue se coupe d'une ellipse avant le chevron. La page fixe sa taille et la colonne de ses lignes. Son fond clair se pose sur une surface blanche, comme ici. La feuille de récolte du planning l'emploie la première pour le temps d'une équipe, que la démonstration reprend, et qui ouvre le volet des personnes.").'</p>';

		$h .= '<div class="util-block" style="display: grid; grid-template-columns: max-content minmax(0, 13rem); align-items: center; gap: var(--space-xxs) var(--space-sm); max-width: 22rem;">';
			$h .= '<span>'.s("Temps").'</span>';
			$h .= '<a class="util-keypad-field"><span>'.s("Maximilienne 1h30 · Bartholomé 0h45").'</span>'.\Asset::icon('chevron-right').'</a>';
		$h .= '</div>';

		return $h;

	}

	public function scope(): string {

		$h = '<p class="util-annotation">'.s("util-scope-line pose sur une ligne au-dessus du titre ce qu'on regarde, et le geste qui en change. Le surtitre (util-scope-line-title) porte toujours un nom, y compris quand la portée est le tout : seul, le jeton se lirait comme un fil d'Ariane et non comme une commande. Le jeton garde le même mot quelle que soit la portée, et ne prend jamais is-active, la valeur étant à sa gauche et non en lui.").'</p>';
		$h .= '<p class="util-annotation">'.s("Deux filets seulement dans la liste, et chacun sépare deux natures : le tout, qui n'est pas une entrée comme les autres, puis les portées closes (util-scope-line-item-closed). Un filet entre chaque entrée dirait qu'il n'y a aucun groupement.").'</p>';

		$h .= '<div class="util-scope-line mb-2">';
			$h .= '<div class="util-scope-line-title">'.s("Promotion 2026").'</div>';
			$h .= '<div class="util-scope-line-change">';
				$h .= '<div>';
					$h .= '<a class="util-chip" data-dropdown="bottom-start">'.s("Changer").'</a>';
					$h .= '<div class="dropdown-list">';
						$h .= '<a href="#" class="dropdown-item util-scope-line-item"><span>'.s("Toutes les promotions").'</span><span class="util-counter">48</span></a>';
						$h .= '<div class="dropdown-divider"></div>';
						$h .= '<a href="#" class="dropdown-item util-scope-line-item selected"><span>'.s("Promotion 2026").'</span><span class="util-counter">26</span></a>';
						$h .= '<a href="#" class="dropdown-item util-scope-line-item"><span>'.s("Promotion 2025").'</span><span class="util-counter">22</span></a>';
						$h .= '<div class="dropdown-divider"></div>';
						$h .= '<a href="#" class="dropdown-item util-scope-line-item util-scope-line-item-closed"><span>'.s("Promotion 2024").'</span><span class="util-counter">19</span></a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-scope-line-title-sm, posé avec util-scope-line-title, en fait un surtitre au-dessus d'un nom, à 0,9 rem, le plancher des textes. Sur un fond qui n'est pas celui de la page, l'écran déclare l'encre par --scope-line-title-ink sur le conteneur du surtitre, jamais plus haut : une barre de portée posée plus bas en hériterait.").' '.s("Un gabarit qui écrit ses capitales plus espacées déclare de même --scope-line-title-tracking, qui vaut 0,09 em par défaut.").'</p>';

		$h .= '<div class="util-block util-block-dark bg-primary mb-2" style="--scope-line-title-ink: white;">';
			$h .= '<div class="util-scope-line-title util-scope-line-title-sm">'.s("Réseau de formation").'</div>';
			$h .= '<h3 class="mb-0">'.s("Groupement des maraîchers du Val de Loire").'</h3>';
		$h .= '</div>';

		return $h;

	}

	public function copy(): string {

		$h = '<p class="util-annotation">'.s("\\util\\TextUi::copy() rend le bouton qui copie le texte de l'élément désigné, et non son balisage, puis le confirme par son message. Il se pose juste après la valeur qu'il copie.").'</p>';

		$h .= '<p>';
			$h .= '<span id="design-copy-value" class="util-literal">0012345678</span> ';
			$h .= \util\TextUi::copy('#design-copy-value', s("Copié !"), s("Copier"), 'btn btn-sm btn-outline-primary');
		$h .= '</p>';

		return $h;

	}

	public function displayMenu(): string {

		$h = '<p class="util-annotation">'.s("util-soil-menu liste d'un coup des modes exclusifs, avec une coche sur l'actif et un aperçu de ce que chacun rendra, puis les options en interrupteur sous un séparateur. À préférer au menu qui ne propose que l'action suivante : celui-ci se lit sans avoir à deviner l'état courant.").'</p>';
		$h .= '<p class="util-annotation">'.s("Deux détails du rendu : le libellé de la ligne d'interrupteur reprend le blanc des modes, la couleur de texte par défaut étant sombre et illisible sur ce fond, où elle se lit comme une ligne désactivée ; et la capsule masque son « Oui / Non », qui doublerait l'information et la largeur du jeton.").'</p>';

		$h .= '<div class="dropdown-list util-soil-menu mb-2" style="position: static; display: block;">';

			foreach([s("Couleurs claires") => TRUE, s("Couleur des espèces") => FALSE, s("Couleur des familles") => FALSE] as $label => $isCurrent) {

				$h .= '<a class="dropdown-item util-soil-mode'.($isCurrent ? ' selected' : '').'">';
					$h .= '<span class="util-soil-label">'.$label.'</span>';
					$h .= '<span class="util-soil-preview"><i style="background-color: #e08223; border-color: #e08223"></i><i style="background-color: #4e9a06; border-color: #4e9a06"></i><i style="background-color: #75507b; border-color: #75507b"></i></span>';
				$h .= '</a>';

			}

			$h .= '<div class="dropdown-divider"></div>';

			$h .= '<div class="util-soil-switch">';
				$h .= '<span>'.s("Semaines de semis, plantation et récolte").'</span>';
				$h .= \util\TextUi::switch(['class' => 'field-switch-sm'], on: TRUE, textOn: s("Oui"), textOff: s("Non"));
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function figures(): string {

		$h = '<p class="util-annotation">'.s("util-figures pose une rangée de chiffres en tête d'écran, la valeur en gros et son intitulé dessous. Les tuiles se partagent la largeur et passent à la ligne.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un chiffre qui résume une liste porte le lien vers elle : le même bloc en balise a, sans classe de plus. Le survol assombrit le fond, seul signe qu'une tuile pleine puisse donner.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un chiffre et son intitulé n'ont qu'une famille, util-figure. La famille en retrait qu'elle remplace ne reçoit aucun emploi neuf. Le test dev/test package=design file=FigureFamilySentinelAi la nomme et déclare les écrans qui la portent encore, en nombre qui ne peut que baisser, et chacun s'en défait sur une ligne qu'on réécrit.").'</p>';

		$h .= \main\DesignUi::getFigures([
			\main\DesignUi::getFigure('128', s("Séries")),
			\main\DesignUi::getFigure('12', s("Membres"), '#design-figures'),
		]);

		$h .= '<h4 class="mt-2">'.s("Un chiffre et sa variation").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-figure-variation pose une variation sous l'intitulé, en gris tant qu'elle ne dit ni mieux ni moins bien. util-figure-variation-up la passe au vert et util-figure-variation-down au rouge, à choisir selon le sens métier et non selon le signe, une baisse de charges étant une bonne nouvelle. Un chiffre sans point de comparaison ne porte pas de variation.").'</p>';

		$h .= '<div class="util-figures">';

			$figures = [
				['value' => '12 300 €', 'label' => s("Sans variation"), 'variation' => NULL, 'class' => 'util-figure-variation'],
				['value' => '48 100 €', 'label' => s("Variation favorable"), 'variation' => '+ 12 %', 'class' => 'util-figure-variation util-figure-variation-up'],
				['value' => '3 250 €', 'label' => s("Variation défavorable"), 'variation' => '− 8 %', 'class' => 'util-figure-variation util-figure-variation-down'],
				['value' => '910 €', 'label' => s("Variation neutre"), 'variation' => s("Non comparable"), 'class' => 'util-figure-variation'],
			];

			foreach($figures as $figure) {

				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-value">'.$figure['value'].'</div>';
					$h .= '<div class="util-figure-label">'.$figure['label'].'</div>';
					if($figure['variation'] !== NULL) {
						$h .= '<div class="'.$figure['class'].'">'.$figure['variation'].'</div>';
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un chiffre et l'objectif qu'il vise").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-figure-target pose sous l'intitulé la valeur visée, et une util-progress qui montre le chemin fait vers elle. La valeur visée s'écrit toujours : la barre seule ne dit pas ce qu'il reste. Un chiffre sans objectif saisi ne porte pas de cible.").'</p>';

		$h .= '<div class="util-figures util-figures-2">';

			foreach([
				['value' => '14 200 €', 'label' => s("Ventes de la saison"), 'target' => s("Objectif 20 000 €"), 'width' => 71],
				['value' => '46', 'label' => s("Paniers par semaine"), 'target' => s("Objectif 40 paniers"), 'width' => 100],
			] as $figure) {

				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-value">'.$figure['value'].'</div>';
					$h .= '<div class="util-figure-label">'.$figure['label'].'</div>';
					$h .= '<div class="util-figure-target">';
						$h .= $figure['target'];
						$h .= '<div class="util-progress"><div class="util-progress-part bg-success" style="width: '.$figure['width'].'%"></div></div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une tuile qui garde sa place").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-figures-2 et util-figures-3 fixent la place de chaque tuile, sur le modèle des colonnes de util-presentation : une tuile garde la même largeur qu'elle soit seule ou accompagnée, quand un chiffre n'apparaît que s'il vaut quelque chose. Une colonne sous 576 px, deux au-dessus, trois à partir de 992 px pour util-figures-3.").'</p>';

		$h .= '<div class="util-figures util-figures-2">';

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">13</div>';
				$h .= '<div class="util-figure-label">'.s("Traitements en {value}", 2021).'</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("L'intitulé au-dessus de la valeur").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-figure-label-top pose l'intitulé au-dessus de la valeur, en texte courant gras, quand l'œil doit savoir ce que compte chaque tuile avant de lire le chiffre : des tuiles voisines dont les valeurs se ressemblent, deux durées par exemple. L'intitulé s'écrit avant la valeur. Les tuiles d'une même rangée prennent toutes la même place d'intitulé.").'</p>';

		$h .= '<div class="util-figures">';

			foreach([s("Journée") => '7h30', s("Interventions") => '5h15'] as $label => $value) {
				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-label util-figure-label-top">'.$label.'</div>';
					$h .= '<div class="util-figure-value">'.$value.'</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un intitulé du dessus sur deux lignes").'</h4>';
		$h .= '<p class="util-annotation">'.s("L'intitulé du dessus tient sur une ligne : sur deux, il descend la valeur de sa tuile, qui quitte la ligne de ses voisines.").'</p>';

		$h .= '<div class="util-figures">';

			foreach([s("Journée") => '7h30', s("Interventions de la journée") => '5h15'] as $label => $value) {
				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-label util-figure-label-top">'.$label.'</div>';
					$h .= '<div class="util-figure-value">'.$value.'</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public function figuresScroll(): string {

		$h = '<p class="util-annotation">'.s("util-figures-overflow range une suite de chiffres de même nature, qu'on parcourt plus qu'on ne compare, comme le chiffre d'affaires de chaque jour de vente. Chaque tuile prend la largeur de son contenu, à toutes les largeurs. Sur un écran tactile, la rangée ne passe plus à la ligne et défile de côté, sans barre, chaque tuile s'arrêtant au bord gauche. Posée dans le container, elle défile aussi sous sa marge, jusqu'à son bord ; un hôte qui l'enveloppe sans changer sa largeur se déclare par util-figures-overflow-wrapper, comme ici. Le tactile se lit sur data-touch, que le framework pose sur le corps au chargement de la page : à la souris, les tuiles passent à la ligne. Ne se combine pas avec util-figures-2 ni util-figures-3, qui fixent la place de chaque tuile.").'</p>';

		$h .= '<div class="util-figures-overflow-wrapper">';

			$h .= '<div class="util-figures util-figures-overflow">';

				foreach([
					[s("Mar. 13 oct."), '72 €'],
					[s("Mer. 30 sept."), '12 345 €'],
					[s("Sam. 26 sept."), '?'],
					[s("Mar. 22 sept."), '522 €'],
					[s("Lun. 21 sept."), '689 €'],
					[s("Sam. 19 sept."), '273 €'],
					[s("Ven. 18 sept."), '2 064 €'],
					[s("Mer. 16 sept."), '1 108 €'],
				] as [$label, $value]) {
					$h .= '<a href="#design-figures" class="util-figure">';
						$h .= '<div class="util-figure-label util-figure-label-top">'.$label.'</div>';
						$h .= '<div class="util-figure-value">'.$value.'</div>';
					$h .= '</a>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function procedure(): string {

		$h = '<p class="util-annotation">'.s("util-procedure numérote des étapes à suivre dans l'ordre, dans un contenu rédigé. Chaque util-procedure-step porte sa pastille et un trait la relie à la suivante.").'</p>';
		$h .= '<p class="util-annotation">'.s("La pastille occupe la première colonne et tout le contenu de l'étape va dans la seconde, quel que soit le nombre d'éléments. Le titre de l'étape est un util-procedure-title, le texte qui le suit est libre.").'</p>';

		$h .= '<ol class="util-procedure">';

			foreach([
				[s("Gratter le sol"), s("Sur un mètre de rang, à la profondeur d'un outil.")],
				[s("Compter les levées"), s("Trois feuilles au moins sur chaque plant.")],
				[s("Noter le résultat"), s("Dans le cahier de culture, avec la date.")],
			] as [$title, $text]) {

				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.$title.'</h4>';
					$h .= '<p>'.$text.'</p>';
				$h .= '</li>';

			}

		$h .= '</ol>';

		return $h;

	}

	public function criteriaChecks(): string {

		$criteria = [
			[s("Le sol est couvert"), s("Pas de terre visible entre les rangs.")],
			[s("Le paillis ne touche pas les plants"), s("Deux doigts d'écart autour de chaque pied.")],
			[s("La couche fait cinq centimètres"), s("Mesurée en trois points de la planche, avec une règle plantée jusqu'au sol, sans tasser le paillis.")],
		];

		$list = function(array $items, string $style = ''): string {

			$h = '<ol class="util-procedure util-procedure-check"'.$style.'>';

				foreach($items as [$title, $text]) {

					$h .= '<li class="util-procedure-step">';
						$h .= '<h4 class="util-procedure-title">'.$title.'</h4>';
						$h .= '<p>'.$text.'</p>';
					$h .= '</li>';

				}

			$h .= '</ol>';

			return $h;

		};

		$h = '<p class="util-annotation">'.s("util-procedure-check, posé sur util-procedure, met une coche dans chaque pastille à la place du numéro et retire le trait, sur toutes les lignes : des critères à vérifier ne se suivent pas. Rien ne se coche, la coche est une marque et non une case.").'</p>';
		$h .= $list(array_slice($criteria, 0, 2));

		$h .= '<h4 class="mt-2">'.s("La coche dans la teinte du thème").'</h4>';
		$h .= '<p class="util-annotation">'.s("La coche prend la couleur du numéro, --marker-color, et suit donc le thème qui repeint la pastille, comme ci-dessous.").'</p>';
		$h .= $list($criteria, ' style="--marker-background: white; --marker-color: var(--primary-on-light, var(--primary)); --marker-ring: inset 0 0 0 2px var(--primary-on-light, var(--primary))"');

		return $h;

	}

	public function iconSteps(): string {

		$h = '<p class="util-annotation">'.s("util-procedure-icon, posé sur util-procedure, remplace le numéro par l'icône de l'étape : un util-icon util-icon-round en premier enfant de chaque util-procedure-step, coché par check-lg et util-icon-active quand l'étape est faite. La pastille a la taille d'un util-icon, pleine comme le numéro d'util-procedure, l'icône en blanc sur la teinte de sa classe d'univers, et le trait part sous elle.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une étape qui porte un util-deck descend sa pastille de --marker-offset, pour la poser face au titre du deck, et le trait s'allonge d'autant : il garde le même jour au-dessus de la pastille suivante que sous la sienne. Le titre du deck s'écrit en util-deck-title-heading. Le deck se déplie par sa tête : seule l'étape à faire maintenant est ouverte, les autres attendent un clic, et la coche dit seule qu'une étape est faite.").'</p>';

		$toggle = \main\DesignUi::getDeckToggle();

		$h .= '<ol class="util-procedure util-procedure-icon">';

			foreach([
				['geo-alt', s("Le lieu et le jour de retrait"), s("Où et quand les clients retirent leurs commandes."), TRUE, FALSE, [s("Lieu"), s("Marché couvert, le samedi"), s("Modifier")]],
				['basket', s("Les produits"), s("Ce que la boutique propose, avec leur prix et leur unité de vente."), FALSE, TRUE, [s("Produits"), s("Aucun produit"), s("Ajouter un produit")]],
				['people', s("Les clients qui commandent chaque semaine et ceux qui viennent une seule fois dans la saison"), s("Ceux qui reçoivent l'annonce de chaque ouverture."), FALSE, FALSE, [s("Clients"), s("Aucun client"), s("Inviter un client")]],
			] as [$icon, $title, $text, $done, $open, [$rowName, $rowState, $rowButton]]) {

				$h .= '<li class="util-procedure-step">';
					$h .= '<div class="util-icon util-icon-round '.($done ? 'util-icon-active' : 'util-icon-commercialisation').'">'.\Asset::icon($done ? 'check-lg' : $icon).'</div>';
					$h .= '<div class="util-deck'.($open ? ' util-deck-open' : '').'">';
						$h .= '<a class="util-deck-head" '.$toggle.'>';
							$h .= '<div class="util-deck-body">';
								$h .= '<div class="util-deck-title util-deck-title-heading">'.$title.'</div>';
								$h .= '<div class="util-annotation">'.$text.'</div>';
							$h .= '</div>';
							$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
						$h .= '</a>';
						$h .= '<div class="util-deck-fold">';
							$h .= '<div class="util-deck-row'.($done ? ' util-deck-row-state util-deck-accent-success' : '').' flex-justify-space-between flex-wrap">';
								$h .= '<span><span class="util-deck-row-name"><b>'.$rowName.'</b></span> <span>'.($done ? \Asset::icon('check-lg').' ' : '').$rowState.'</span></span>';
								$h .= '<span class="flex-buttons"><a class="btn btn-outline-primary btn-sm">'.$rowButton.'</a></span>';
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</li>';

			}

		$h .= '</ol>';

		return $h;

	}

	public function chain(): string {

		$h = '<p class="util-annotation">'.s("util-procedure-dots, posé sur util-procedure, réduit la pastille à un point sans numéro, au milieu de la première ligne de l'étape, et resserre les étapes sur leur ligne : des dates à la suite, qui n'ont pas de rang à compter. Sa teinte se règle par --marker-background et --step-line, comme util-procedure-tint. util-procedure-step-open évide le point de la dernière étape, celle qui reste à faire. Le point se centre sur une ligne de texte : une étape dont la première rangée est plus haute, un champ par exemple, règle --marker-drop.").'</p>';

		$h .= '<ol class="util-procedure util-procedure-dots">';
			foreach([['12/10/2026', s("7 bottes")], ['18/10/2026', s("8 bottes")]] as [$date, $quantity]) {
				$h .= '<li class="util-procedure-step"><div>'.$date.' · <b>'.$quantity.'</b></div></li>';
			}
			$h .= '<li class="util-procedure-step util-procedure-step-open"><div>'.s("Ajouter une récolte").'</div></li>';
		$h .= '</ol>';

		return $h;

	}

	public function route(): string {

		$h = '<p class="util-annotation">'.s("util-chain relie deux ou trois util-chain-step, chacune avec son icône et son nom, par des util-chain-arrow dont la légende est facultative. La légende passe à la ligne au-delà de 8 rem, et la chaîne se lit de haut en bas sous 576 px.").'</p>';

		$h .= '<div class="util-chain">';
			$h .= '<div class="util-chain-step">'.\Asset::icon('credit-card').'<span>'.s("Versement de la caisse").'</span></div>';
			$h .= '<div class="util-chain-arrow">'.\Asset::icon('arrow-right').'<span>'.s("compte 58").'</span></div>';
			$h .= '<div class="util-chain-step">'.\Asset::icon('wallet2').'<span>'.s("Compte non synchronisé").'</span></div>';
			$h .= '<div class="util-chain-arrow">'.\Asset::icon('arrow-right').'</div>';
			$h .= '<div class="util-chain-step">'.\Asset::icon('bank').'<span>'.s("Compte bancaire synchronisé").'</span></div>';
		$h .= '</div>';

		return $h;

	}

	public function typedValue(): string {

		$h = '<p class="util-annotation">'.s("util-literal montre une valeur telle qu'on la tape ou qu'on la lit dans un fichier, quelle que soit sa longueur, et revient à la ligne au-delà de 15 rem. badge-reference, lui, porte un code qu'on relit caractère par caractère, en chasse fixe et sur une seule ligne.").'</p>';

		$h .= '<ul>';
			$h .= '<li>'.s("{value} ou {value2} pour un amortissement linéaire", ['value' => '<span class="util-literal">L</span>', 'value2' => '<span class="util-literal">LIN</span>']).'</li>';
			$h .= '<li>'.s("Pour démarrer une liste à numéro, écrivez {value} sur une ligne vide", ['value' => '<span class="util-literal">'.s("1. [Votre texte]").'</span>']).'</li>';
		$h .= '</ul>';

		$h .= '<h4 class="mt-2">'.s("Dans un tableau").'</h4>';
		$h .= '<table class="tr-even">';
			$h .= '<thead><tr><th>'.s("Colonne").'</th><th>'.s("Exemple").'</th></tr></thead>';
			$h .= '<tbody>';
				foreach([[s("Mode de culture"), \series\Series::OPEN_FIELD], [s("Utilisation du sol"), \series\Series::BED], [s("Date de semis"), '2026-03-15']] as [$column, $example]) {
					$h .= '<tr><td>'.$column.'</td><td><span class="util-literal">'.$example.'</span></td></tr>';
				}
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function chips(): string {

		$h = '<p class="util-annotation">'.s("util-chips, avec un util-omnibox en tête et un util-chip par filtre. util-chips-sticky colle la barre en haut au défilement, ce qu'un bandeau de filtres ne peut pas se permettre. is-active pour un critère renseigné, is-solid pour celui qui restreint vraiment ce qu'on voit.").'</p>';

		$h .= '<div class="util-chips mb-2">';

			$h .= \main\OmniboxUi::get(
				suggestions: [
					['group' => s("Référentiel"), 'value' => 'legumes', 'label' => s("Légumes"), 'url' => '#'],
					['group' => s("Référentiel"), 'value' => 'plants', 'label' => s("Plants"), 'url' => '#'],
					['group' => s("Produits"), 'value' => 'carotte', 'label' => s("Carotte"), 'url' => '#'],
				],
				tokens: [
					['group' => s("Référentiel"), 'value' => 'fruits', 'label' => s("Fruits"), 'url' => '#'],
				],
				placeholder: s("Chercher un produit, une filière..."),
				count: s("12 résultats"),
				clear: '#'
			);

			$jetons = [
				[s("Mode de production"), FALSE, ''],
				[s("Bio"), TRUE, ''],
				[s("Vente directe"), TRUE, ''],
				[s("Planches libres"), FALSE, 'is-solid'],
			];

			foreach($jetons as [$label, $active, $state]) {
				$h .= \main\DesignUi::getChipDropdown($label, [['#design-chips', s("Première valeur"), TRUE], ['#design-chips', s("Deuxième valeur"), FALSE]], $active, $label, $state);
			}

			$form = new \util\FormUi();
			$h .= $form->select('designArea', [s("France"), s("Occitanie"), s("Provence-Alpes-Côte d'Azur")], 0, ['required' => TRUE, 'class' => 'util-chip is-active']);

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un jeton sur un fond sombre").'</h4>';
		$h .= '<p class="util-annotation">'.s("is-inverted pour un jeton posé sur un fond sombre, le bandeau d'un groupe ou la photo qui le couvre. Il reprend le contour et le blanc que le bandeau donne déjà à ses boutons : sans lui, le jeton blanc y devient la chose la plus claire de l'en-tête et vole l'œil aux onglets.").'</p>';

		$h .= '<div class="util-chips mb-2" style="background-color: #373737; padding: 0.75rem; border-radius: var(--radius);">';
			$h .= '<a class="util-chip is-inverted">'.s("Changer").'</a>';
			$h .= '<a class="util-chip is-inverted">'.s("Saison").' <b>2026</b></a>';
		$h .= '</div>';

		return $h;

	}

	public function months(): string {

		$h = '<p class="util-annotation">'.s("util-timeline, et une util-timeline-month par mois, dans l'état util-timeline-done|todo|upcoming|none (upcoming pour un mois à venir qui porte déjà une valeur, none pour une case sans valeur), plus util-timeline-selected pour cercler le mois qu'on regarde, par-dessus son état et non à sa place.").'</p>';

		$h .= '<div class="util-timeline mb-2">';

			$mois = [
				['janv', 'done', \Asset::icon('check-lg')], ['fév', 'done', \Asset::icon('check-lg')],
				['mars', 'done', \Asset::icon('check-lg')], ['avr', 'todo', '48'],
				['mai', 'todo', '54'], ['juin', 'todo', '223'],
				['juil', 'todo', '406'], ['août', 'todo selected', '29'],
				['sept', 'upcoming', '31'], ['oct', 'upcoming', '35'],
				['nov', 'none', '–'], ['déc', 'none', '–'],
			];

			foreach($mois as [$name, $state, $value]) {

				$class = 'util-timeline-month '.implode(' ', array_map(fn($one) => 'util-timeline-'.$one, explode(' ', $state)));
				$content = '<small>'.$name.'</small><b>'.$value.'</b>';

				$h .= str_contains($state, 'none') ?
					'<span class="'.$class.'">'.$content.'</span>' :
					'<a href="#" class="'.$class.'">'.$content.'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public function iconDiscs(): string {

		$h = '<p>'.s("util-icon pose une icône dans une pastille, déclinée par domaine avec util-icon-production, util-icon-commercialisation, util-icon-accounting, util-icon-settings, util-icon-amap et util-icon-muted, et par taille avec util-icon-sm et util-icon-lg.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-icon-brand porte le logo de Ouvretaferme, pour ce que l'équipe signe elle-même : le logo s'y dimensionne comme une icône. util-icon-friend porte l'ami d'une offre, sur la teinte du domaine qui l'accueille : il prend les trois quarts de la pastille. util-icon-round arrondit en cercle. util-icon-dot pose un point sur le coin haut gauche, pour ce qui vient d'arriver : il déborde du cadre et porte un anneau blanc qui le détache du fond coloré, donc l'icône ne va pas dans un conteneur qui rogne.").'</p>';

		$h .= '<div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">';

			foreach(['production', 'commercialisation', 'accounting', 'settings', 'amap', 'muted'] as $domain) {
				$h .= '<div class="util-icon util-icon-'.$domain.'">'.\Asset::icon('star-fill').'</div>';
			}

			$h .= '<div class="util-icon util-icon-sm util-icon-accounting">'.\Asset::icon('star-fill').'</div>';
			$h .= '<div class="util-icon util-icon-lg util-icon-accounting">'.\Asset::icon('star-fill').'</div>';
			$h .= '<div class="util-icon util-icon-round util-icon-accounting">'.\Asset::icon('star-fill').'</div>';
			$h .= '<div class="util-icon util-icon-accounting util-icon-dot">'.\Asset::icon('star-fill').'</div>';
			$h .= '<div class="util-icon util-icon-brand">'.\Asset::svg('main', 'svg/logo-otf.svg').'</div>';
			$h .= '<div class="util-icon util-icon-round util-icon-sm util-icon-logo">'.self::getLogoDemo().'</div>';
			if(\structure\StructureSetting::hasSubscriptionPackage()) {
				$h .= '<div class="util-icon util-icon-sm util-icon-settings util-icon-friend">'.\subscription\SubscriptionUi::getSoftwareFriend(\subscription\Subscription::MANAGEMENT).'</div>';
				$h .= '<div class="util-icon util-icon-settings util-icon-friend">'.\subscription\SubscriptionUi::getCommunityFriend().'</div>';
			}

		$h .= '</div>';

		if(\structure\StructureSetting::hasSubscriptionPackage()) {

			$h .= '<h4 class="mt-2">'.s("Ami dans une phrase").'</h4>';
			$h .= '<p class="util-annotation">'.s("util-friend-inline pose l'ami d'une offre devant son nom, au fil d'un texte : les deux restent sur la même ligne, et l'ami se centre sur la hauteur des lettres sans agrandir la ligne.").'</p>';
			$h .= '<p>'.s("3 % de chaque vente par carte, avec votre abonnement {offer}", ['offer' => '<span class="util-friend-inline">'.\Asset::svg('main', 'friends/luciole-classique.svg').s("Gestion").'</span>']).'</p>';

		}

		$h .= '<p class="util-annotation">'.s("util-icon-logo porte le logo d'une structure sur fond blanc, cerclé d'un filet, quelle que soit sa forme : un logo large y tient entier. Une structure sans logo n'a pas de pastille, et son nom se lit seul (#convention-logo-reel).").'</p>';

		$h .= '<h4 class="mt-2">'.s("Logo d'un service").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-logo pose le logo d'un service tiers dans le fil du texte, sans pastille, à la hauteur du texte, soit 1 rem, puis 1,5 rem avec util-logo-md et 3 rem avec util-logo-lg. Sa largeur suit son dessin. util-icon-logo reste réservé au logo d'une structure.").'</p>';
		$logo = fn(string $class) => \Asset::image('main', 'facturation-electronique-logo.png', ['class' => $class]);
		$h .= '<p>'.s("Reçue par la plateforme agréée").' '.$logo('util-logo').'</p>';
		$h .= '<h3>'.s("Facture FA-2026-103").' '.$logo('util-logo util-logo-md').'</h3>';
		$h .= '<div class="design-paper-frame">';
			$h .= '<div class="util-paper">';
				$h .= \main\DesignUi::getPageHeader(
					s("Facture de vente FA-2026-103"),
					logo: $logo('util-logo util-logo-lg')
				);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function status(): string {

		$h = '<p class="util-annotation">'.s("L'état d'une ligne se dit d'une icône à la couleur de son état, color-success, color-warning ou color-danger posée sur un span, sans classe de plus. Seule dans une colonne étroite, l'icône suffit ; devant un mot, elle le précède.").'</p>';

		$h .= '<table class="tr-even">';
			$h .= '<thead><tr><th>'.s("Contrôle").'</th><th>'.s("État").'</th></tr></thead>';
			$h .= '<tbody>';
				$h .= '<tr><td>'.s("Équilibre du journal").'</td><td><span class="color-success">'.\Asset::icon('check-lg').' '.s("Conforme").'</span></td></tr>';
				$h .= '<tr><td>'.s("Pièces sans justificatif").'</td><td><span class="color-warning">'.\Asset::icon('exclamation-triangle').' '.s("À surveiller").'</span></td></tr>';
				$h .= '<tr><td>'.s("Compte d'attente soldé").'</td><td><span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.s("En défaut").'</span></td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function statusDots(): string {

		$h = '<p class="util-annotation">'.s("util-icon-active et util-icon-inactive disent qu'une chose est allumée ou éteinte, là où les variantes de util-icon, dans les fondations, disent à quel domaine elle appartient. Elles empruntent la palette d'une section pour dire un état, ce que le dépôt fait déjà de --secondary-light. Ne pas leur préférer util-icon-muted : le gris dit « indisponible », alors que ce qui est éteint reste disponible, et ce qui ne l'est pas ne s'affiche pas du tout.").'</p>';

		$h .= '<div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">';

			foreach(['active' => s("Allumée"), 'inactive' => s("Éteinte")] as $state => $label) {
				$h .= '<div class="util-icon util-icon-round util-icon-'.$state.'">'.\Asset::icon('star-fill').'</div>';
				$h .= '<span class="color-muted font-sm">'.$label.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	public function cardsAccent(): string {

		$h = '<h4 style="margin-top: 1.5rem;">'.s("Cartes à liseré").'</h4>';
		$h .= '<p class="util-annotation">'.s("\\main\\DesignUi::getAccentCard() rend une util-card util-card-accent util-card-accent-*, dans sa propre grille util-cards : une alternative discrète aux blocs pleins util-block-success et consorts.").'</p>';

		foreach(['success', 'warning', 'danger', 'info', 'todo'] as $accent) {
			$h .= \main\DesignUi::getAccentCard($accent, NULL, 'util-card-accent-'.$accent, '<p>'.s("Le titre et le liseré prennent la couleur du modificateur.").'</p>');
		}

		$h .= '<p class="util-annotation">'.s("util-card-accent-todo marque une étape qui reste à faire. Il porte la teinte de la section, comme info, et non l'orange de warning : l'orange et le rouge sont réservés aux alertes, et une étape pas encore faite n'en est pas une.").'</p>';

		$h .= '<p class="util-annotation">'.s("Une carte à liseré porte un paragraphe quand elle n'a qu'une chose à dire, une liste dès qu'elle en a plusieurs : trois phrases empilées se lisent comme un seul texte suivi. Le marqueur prend la couleur du liseré.").'</p>';

		$h .= \main\DesignUi::getAccentCard('warning', NULL, s("Une seule alerte"), '<p>'.s("Elle reste un paragraphe : une liste d'un élément a une puce qui ne distingue rien.").'</p>');

		$h .= \main\DesignUi::getAccentCard('warning', NULL, s("Plusieurs alertes"),
			'<ul>'.
				'<li>'.s("La première, qui appelle un geste.").'</li>'.
				'<li>'.s("La deuxième, qui n'en appelle aucun.").'</li>'.
				'<li>'.s("La troisième, qui se lit d'un coup d'œil parce qu'elle est détachée.").'</li>'.
			'</ul>'
		);

		return $h;

	}

	public function tabsHorizontal(): string {

		$h = '<p class="util-annotation">'.s("tabs tabs-h pose une barre d'onglets qui reste dans la page, à distinguer des onglets de section du gabarit. L'onglet actif porte selected.").'</p>';

		$h .= '<div class="tabs tabs-h">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected">'.s("Synthèse").'</a>';
				$h .= '<a class="tab-item">'.s("Tableau des prix").'</a>';
				$h .= '<a class="tab-item">'.s("Méthode").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une rangée repliée en menu quand elle déborde").'</h4>';
		$h .= '<p class="util-annotation">'.s("tabs-h-fold replie une rangée tabs-h qui ne tient plus dans sa largeur en un seul onglet, tab-item-menu, qui porte l'onglet ouvert et ouvre la liste de tous les onglets. Le repli suit le débordement réel de la rangée, jamais un nombre d'onglets ni une largeur d'écran, et la rangée se déplie dès qu'elle tient de nouveau. Le compteur du menu additionne les util-counter-danger des onglets qu'il cache, pour que ce qui presse reste visible, et le libellé de l'onglet ouvert garde ses autres compteurs. L'onglet-menu prend les classes de l'onglet choisi, ce qui lui garde les couleurs que la page y pose. Le menu et la liste tiennent sur une ligne, une icône ou une vignette y prend son title pour nom, et ce que la page masque dans un onglet reste masqué dans la liste. Au-delà de dix onglets, la liste passe sur deux colonnes. Seule la rangée s'écrit : Lime.TabFold construit le menu avec les mêmes liens, et rejoue l'onclick de l'onglet choisi.").'</p>';

		$h .= '<div style="max-width: 20rem;">';
			$h .= '<div class="tabs tabs-h tabs-h-fold">';
				$h .= '<div class="tabs-item">';
					$h .= '<a class="tab-item selected">'.s("Tracteur").' <span class="util-counter util-counter-danger">1</span></a>';
					$h .= '<a class="tab-item">'.s("Utilitaire").'</a>';
					$h .= '<a class="tab-item">'.s("Broyeur à fléaux").' <span class="util-counter util-counter-danger">2</span></a>';
					$h .= '<a class="tab-item">'.s("Pulvérisateur").'</a>';
					$h .= '<a class="tab-item">'.s("Herse étrille").'</a>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Des sous-onglets repliés").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une rangée tabs-sub se replie comme une rangée de base, en ajoutant tabs-h et tabs-h-fold à son conteneur. L'onglet-menu y prend la hauteur du sous-onglet, son contenu centré, et son icône garde la taille de celle de l'onglet de base, à côté de la vignette et du prénom de l'onglet choisi.").'</p>';

		$h .= '<div style="max-width: 20rem;">';
			$h .= '<div class="tabs">';
				$h .= '<div class="tabs-item">';
					$h .= '<a class="tab-item">'.s("Récoltes").'</a>';
					$h .= '<a class="tab-item selected">'.s("Équipe").'</a>';
				$h .= '</div>';
				$h .= '<div class="tab-panel selected">';
					$h .= '<div class="tabs tabs-h tabs-h-fold">';
						$h .= '<div class="tabs-item tabs-sub">';
							$h .= '<a class="tab-item">'.s("Tous").'</a>';
							foreach([[s("Camille"), s("Roux")], [s("Bastien"), s("Morel")], [s("Jean-Baptiste"), s("Faure")], [s("Léonie"), s("Girard")]] as $id => [$firstName, $lastName]) {
								$h .= '<a class="tab-item'.($id === 2 ? ' selected' : '').'">'.\user\UserUi::getVignette(new \user\User(['id' => $id + 1, 'vignette' => NULL, 'firstName' => $firstName, 'lastName' => $lastName]), '1.5rem').' <span>'.$firstName.'</span></a>';
							}
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 id="design-tabs-tiles" class="mt-2">'.s("Des vues choisies sur leur description, en tuiles").'</h4>';
		$h .= '<p class="util-annotation">'.s("tabs-tiles fait de chaque onglet d'une rangée tabs-h une tuile, pour des vues exclusives qu'on choisit sur leur description : l'icône dans tab-item-tile-icon, le nom en tab-item-name et une ligne en tab-item-detail, qui tombe sur un téléphone. L'onglet choisi prend un fond teinté et sa vignette pleine, un onglet disabled reste gris. Une rangée porte quatre tuiles au plus, chacune de 18 rem au plus sans s'étirer, et la cinquième ouvre la seconde rangée. La rangée reste dans le contenu : elle ne passe pas par \\main\\DesignUi::getPageTabs() et ne se combine pas avec tabs-h-fold, qui replierait les tuiles en menu. Une grille de liens d'une page publique est util-tile-link.").'</p>';

		$tile = fn(string $icon, string $name, string $detail, string $state = '') => '<a class="tab-item'.($state === '' ? '' : ' '.$state).'">'.
			'<span class="tab-item-tile-icon">'.\Asset::icon($icon).'</span>'.
			'<span class="tab-item-name">'.$name.'</span>'.
			'<span class="tab-item-detail">'.$detail.'</span>'.
		'</a>';

		$h .= '<div class="tabs tabs-h tabs-tiles">';
			$h .= '<div class="tabs-item">';
				$h .= $tile('grid-3x3', s("Essai en parcelles"), s("Plusieurs modalités, répétées en blocs"), 'selected');
				$h .= $tile('eye', s("Suivi d'une culture"), s("Une planche observée dans la saison"));
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="tabs tabs-h tabs-tiles">';
			$h .= '<div class="tabs-item">';
				$h .= $tile('basket', s("Rendement"), s("Les récoltes pesées par modalité"), 'selected');
				$h .= $tile('stars', s("Qualité"), s("Le calibre et l'aspect notés à la récolte"));
				$h .= $tile('bug', s("Bioagresseurs"), s("Les attaques relevées à chaque passage"));
				$h .= $tile('clock', s("Temps de travail"), s("Les heures passées sur chaque bloc"));
				$h .= $tile('currency-euro', s("Coûts"), s("Les intrants achetés pour l'essai"), 'disabled');
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function graduation(): string {

		$h = '<p class="util-annotation">'.s("util-graduation porte un util-graduation-mark par repère, dont \\series\\PlaceUi::getGraduationClass() donne les modificateurs. Les multiples de dix prennent util-graduation-mark-decade, ceux de cinq util-graduation-mark-five, les deux extrémités util-graduation-mark-bound : ce sont les repères qui survivent quand la place manque, en dessous de 1200 px. Le composant ne porte que la densité, le placement appartient à l'écran.").'</p>';

		foreach([['', s("Au large, tous les repères"), NULL], [' util-graduation-sparse', s("Clairsemée, un sur cinq et les bornes"), s("util-graduation-sparse applique ce palier tout de suite, pour un axe qu'on sait toujours étroit.")]] as $index => [$modifier, $label, $annotation]) {

			$h .= '<h4'.($index === 0 ? '' : ' class="mt-2"').'>'.$label.'</h4>';
			if($annotation !== NULL) {
				$h .= '<p class="util-annotation">'.$annotation.'</p>';
			}

			$h .= '<div class="util-graduation'.$modifier.'" style="display: grid; grid-template-columns: repeat(30, minmax(0, 1fr)); margin-bottom: 1rem;">';

				foreach(range(1, 30) as $number) {
					$h .= '<div class="util-graduation-mark'.\series\PlaceUi::getGraduationClass('2026-W'.sprintf('%02d', $number), '2026-W01', '2026-W30').'" style="font-size: 0.7rem; text-align: center; color: var(--muted);">'.$number.'</div>';
				}

			$h .= '</div>';

		}

		return $h;

	}

	public function timeline(): string {

		$h = '<p class="util-annotation">'.s("util-timeline-event pose la pastille d'un geste daté. util-timeline-event-expected la vide et la cerne, util-timeline-event-period l'abaisse en ruban de durée, util-timeline-event-frame en fait le ruban de fond qui court sur toute la durée de l'objet. La couleur vient de l'écran, le placement aussi : les deux écrans qui l'emploient posent leurs pastilles en pourcentage d'un axe de jours, chacun sur la fenêtre de calendrier qui lui est propre.").'</p>';

		$h .= '<div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">';
			$h .= '<i class="util-timeline-event" style="background-color: #099A89; color: white;">'.s("P").'</i>';
			$h .= '<i class="util-timeline-event util-timeline-event-expected" style="color: #3076BD;">'.s("R").'</i>';
			$h .= '<i class="util-timeline-event util-timeline-event-period util-legend-mark" style="background-color: #3076BD;"></i>';
			$h .= '<i class="util-timeline-event util-timeline-event-frame" style="width: 8rem;"></i>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une ligne sur deux d'une frise").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-timeline-striped raye une frise un rang sur deux, comme tr-even sur un tableau. Les deux rangs sont peints : une rayure qui n'en peint qu'un disparaît sur un fond de page de la même teinte.").'</p>';

		$h .= '<div class="util-timeline-striped mb-2">';
			foreach([s("Carotte 1"), s("Carotte 2"), s("Carotte 3"), s("Carotte 4")] as $line) {
				$h .= '<div style="padding: 0.375rem 0.75rem;">'.$line.'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	public function steps(): string {

		$h = '<p class="util-annotation">'.s("new \\util\\StepsUi()->display() rend util-steps, un cycle en plusieurs étapes. Chacune porte sa classe done ou active, son libellé, un complément sous le libellé et une date, écrite dans la pastille, ou un point d'interrogation faute de date.").'</p>';
		$h .= '<p class="util-annotation">'.s("La méthode ne sait pas mettre le rang de l'étape dans la pastille : le tunnel du relevé de prix écrit ce balisage lui-même pour cela.").'</p>';

		$h .= new \util\StepsUi()->display([
			['class' => 'done', 'label' => s("Facture émise"), 'who' => s("Par vous"), 'date' => '2026-09-01'],
			['class' => 'active', 'label' => s("Facture reçue"), 'who' => s("Par le client"), 'date' => '2026-09-03'],
			['class' => '', 'label' => s("Facture payée"), 'who' => '', 'date' => NULL],
		]);

		return $h;

	}

	public function actionCards(): string {

		$h = '<p class="util-annotation">'.s("util-card-action porte une action que la page demande et le bouton qui la fait. Un chiffre sans action reste un util-figure, comme les volumes de vente en tête des ventes.").'</p>';
		$h .= '<p class="util-annotation">'.s("Elle se rend par \\main\\DesignUi::getActionCard(), une carte par geste, dans une grille util-cards-fit que l'appelant ouvre pour toute la rangée : deux cartes se partagent la ligne par moitiés, trois par tiers, et une carte seule garde la largeur d'un tiers. Elle s'ouvre sur une accroche util-card-title qui nomme ce qu'il y a à faire, précédée de son chiffre en grand, util-figure-value, ou du logo de ce que la carte propose. Une phrase dit ensuite de quoi il s'agit, et le pied util-card-buttons est un util-card-foot, qui descend au bas de la carte pour aligner les boutons d'une rangée.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le ton se pose par util-card-action-info, util-card-action-warning ou util-card-action-danger, et se lit sur l'encre du chiffre : l'accroche dit l'urgence en mots, la carte reste blanche. Une carte sans chiffre ne porte un ton que par le tampon. Sans ton, la carte est un travail courant. L'orange et le rouge restent aux alertes, une invitation ou une étape à venir prend info. Le bouton reste un contour, btn-outline-primary : la carte demande, elle n'est pas l'action qui fait avancer l'écran.").'</p>';

		$cases = [
			'ventes' => [NULL, '154', s("ventes à facturer"), s("Ces ventes de septembre 2026 ne sont pas payées et se règlent par virement bancaire."), s("Générer les factures")],
			'invitations' => ['info', '2', s("invitations en attente"), s("Les personnes invitées n'ont pas encore répondu."), s("Voir les invitations")],
			'stripe' => ['warning', '12', s("jours pour répondre à Stripe"), s("Fournissez avant le 26/10/2026 les informations que Stripe demande."), s("Compléter mes informations")],
			'retard' => ['danger', '14', s("factures en retard"), s("Elles restent impayées plus de 7 jours après leur échéance."), s("Voir les factures impayées")],
			'echues' => ['warning', '2', s("factures échues"), s("Échues depuis moins de 7 jours, elles ne sont pas encore à relancer."), s("Voir les factures échues")],
			'electronique' => [NULL, NULL, s("Facture électronique gratuite"), s("Envoyez et recevez vos factures électroniques depuis {siteName}."), s("Démarrer")],
		];

		$card = function(string $case, ?string $stamp = NULL) use ($cases): string {

			[$tone, $value, $title, $text, $action] = $cases[$case];

			$outline = ($stamp === NULL) ? 'btn-outline-primary' : match($tone) {
				NULL => 'btn-outline-primary',
				'info' => 'btn-outline-secondary',
				'warning' => 'btn-outline-warning',
				'danger' => 'btn-outline-danger',
			};

			$buttons = '<a href="#design-actionCards" class="btn btn-sm '.$outline.'">'.$action.'</a>';
			$image = ($value === NULL) ? \Asset::image('main', 'facturation-electronique-logo.png') : '';

			return \main\DesignUi::getActionCard($tone, $title, $text, $buttons, $value, $image, $stamp);

		};

		// --secondary vaut la couleur de la marque sur la page design system, et le ton info s'y confondrait avec la carte sans ton. Les grilles prennent la couleur de Commercialisation, celle du ton info dans le produit.

		$h .= '<div class="util-cards util-cards-fit mb-2 design-button-universe-commercialisation">';
			$h .= $card('retard');
			$h .= $card('ventes');
			$h .= $card('electronique');
		$h .= '</div>';

		$h .= '<h4>'.s("Les tons").'</h4>';

		$h .= '<div class="util-cards util-cards-fit mb-2 design-button-universe-commercialisation">';
			$h .= $card('ventes');
			$h .= $card('invitations');
			$h .= $card('stripe');
			$h .= $card('retard');
		$h .= '</div>';

		$h .= '<h4>'.s("Une carte seule").'</h4>';

		$h .= '<div class="util-cards util-cards-fit mb-2">';
			$h .= $card('electronique');
		$h .= '</div>';

		$h .= '<h4>'.s("Option du tampon court").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le tampon court pose un util-stamp d'un seul mot, « Retard », « Bientôt », « Nouveau », à cheval sur le bord du haut : un mot plus long descend en biais sur l'accroche d'une carte étroite. La grille qui en porte un descend toutes ses cartes d'autant, pour que les chiffres restent sur une ligne. Sous le tampon, le bouton prend le contour du ton, btn-outline-danger, btn-outline-warning ou btn-outline-secondary pour info, à l'encre du ton.").'</p>';

		$h .= '<div class="util-cards util-cards-fit design-button-universe-commercialisation">';
			$h .= $card('retard', s("Retard"));
			$h .= $card('echues', s("Bientôt"));
			$h .= $card('ventes');
			$h .= $card('electronique', s("Nouveau"));
		$h .= '</div>';

		return $h;

	}

	public function figuresMuted(): string {

		$h = '<p class="util-annotation">'.s("util-figure-muted fait reculer une tuile derrière ses voisines, sans fond, d'un filet fin et à l'encre atténuée. Elle marque un jour de vente passé, posé après les jours à venir pour compléter la rangée. La tuile reste un lien, et prend le blanc au survol.").'</p>';

		$h .= '<div class="util-figures-overflow-wrapper">';

			$h .= '<div class="util-figures util-figures-overflow">';

				foreach([
					[s("Jeu. 15 oct."), '1 240 €', FALSE],
					[s("Sam. 17 oct."), '?', FALSE],
					[s("Mar. 13 oct."), '72 €', TRUE],
					[s("Mar. 22 sept."), '522 €', TRUE],
				] as [$label, $value, $muted]) {
					$h .= '<a href="#design-figuresMuted" class="util-figure'.($muted ? ' util-figure-muted' : '').'">';
						$h .= '<div class="util-figure-label util-figure-label-top">'.$label.'</div>';
						$h .= '<div class="util-figure-value">'.$value.'</div>';
					$h .= '</a>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function carousel(): string {

		\Asset::js('main', 'carouselAi.js');

		$h = '<p class="util-annotation">'.s("util-carousel montre une diapositive à la fois dans une place étroite, comme les notes du carnet rattachées à un écran de production. La piste défile au doigt et au pavé, à accroche, sans script ; les points dessous la font défiler jusqu'à leur diapositive. Les diapositives prennent la hauteur de la plus haute. Une diapositive qui porte data-url et data-waiting-block n'est qu'une place, que MainCarousel (carouselAi.js) demande à son premier affichage et pâlit le temps de la réponse : la réponse la remplace, ou la retire avec son point par MainCarousel.remove(). La couleur des points est celle du texte, que l'appelant change.").'</p>';

		$h .= '<div class="util-carousel" style="max-width: 16rem" onrender="MainCarousel.start(this)">';

			$h .= '<div class="util-carousel-track">';

				foreach([
					['98', s("interventions faites la semaine 41")],
					['110', s("ventes livrées la semaine 41")],
					['6 991 €', s("livrés la semaine 41")],
				] as [$value, $label]) {
					$h .= '<div class="util-carousel-slide">';
						$h .= '<div class="util-figure-value">'.$value.'</div>';
						$h .= '<div class="util-figure-label">'.$label.'</div>';
					$h .= '</div>';
				}

			$h .= '</div>';

			$h .= '<div class="util-carousel-dots">';
				$h .= '<button type="button" class="selected"></button>';
				$h .= '<button type="button"></button>';
				$h .= '<button type="button"></button>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Plusieurs cartes à la fois").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-carousel-cards, posé à côté de util-carousel, montre en une seule rangée trois cartes au bureau, deux sur une tablette et une au téléphone, à l'écart et aux paliers d'util-cards. La carte est la diapositive elle-même (util-carousel-slide util-card) : elle prend la hauteur de la plus haute, et son util-card-foot se pose au bas. Les points marquent les cartes visibles et disparaissent quand toutes tiennent dans la rangée. C'est le bloc Notes des écrans de production, NoteUi::getLinked().").'</p>';

		$h .= '<div class="util-carousel util-carousel-cards" onrender="MainCarousel.start(this)">';

			$h .= '<div class="util-carousel-track">';

				foreach([
					[s("Mildiou en tunnel"), s("Premières taches sur les feuilles basses début juillet."), TRUE, FALSE],
					[s("Ficelle sous la première grappe, et jamais autour de la tige"), s("Une trentaine de pieds cassés l'an dernier."), FALSE, FALSE],
					[s("Goutte-à-goutte"), s("Deux goutteurs à changer avant la plantation."), FALSE, TRUE],
					[s("Greffe, bilan"), s("Vigueur meilleure en fin de saison."), FALSE, FALSE],
					[s("Variétés à garder"), s("Garder les plus précoces."), FALSE, FALSE],
					[s("Repiquage en godets"), s("Les petits godets séchaient trop vite."), FALSE, FALSE],
				] as [$title, $text, $withImage, $withFoot]) {

					$h .= '<div class="util-carousel-slide util-card">';
						if($withImage) {
							$h .= '<div class="util-card-bleed" style="height: 6rem; background: var(--background-even)"></div>';
						}
						$h .= '<div class="util-card-title">'.$title.'</div>';
						$h .= '<p class="mb-0">'.$text.'</p>';
						if($withFoot) {
							$h .= '<div class="util-card-foot"><span class="badge badge-off">'.s("Administrateurs").'</span></div>';
						}
					$h .= '</div>';

				}

			$h .= '</div>';

			$h .= '<div class="util-carousel-dots">';
				$h .= str_repeat('<button type="button"></button>', 6);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function figuresSelected(): string {

		$h = '<p class="util-annotation">'.s("util-figure-selected marque l'année affichée dans une rangée où chaque tuile ouvre la même analyse sur une autre année. Elle prend la teinte d'un util-choice coché et le chiffre passe à la couleur de l'univers. Un fond plein ferait passer la tuile devant le titre de l'écran. Le survol ne la change pas, puisqu'elle mène à l'écran déjà ouvert. Sur la fiche d'un produit, où l'on n'a encore choisi aucune année, toutes les tuiles sont des liens, et un panneau qui n'a qu'une année ne montre pas la rangée.").'</p>';
		$h .= '<p class="util-annotation">'.s("La même tuile choisit l'indicateur que dessine le graphique placé dessous, comme sur le Bilan d'une formation, où chaque chiffre clé rouvre l'écran sur sa série, troisième rangée ci-dessous.").'</p>';
		$h .= '<p class="util-annotation">'.s("Sur le gabarit commun, où l'univers est le bleu-vert foncé de la marque, la teinte se mêle au fond vert-bleu de la tuile au repos et non au blanc, qui la rendrait grise, et plus fort qu'ailleurs pour rester distincte d'une tuile survolée.").'</p>';

		foreach([
			[
				['2026', '126 460 €', s("8 % des ventes"), TRUE],
				['2025', '620 €', s("< 1 % des ventes"), FALSE],
				['2024', '2 221 €', s("1 % des ventes"), FALSE],
				['2023', '48 €', NULL, FALSE],
			],
			[
				['2026', \series\TaskUi::convertTime(184.38), NULL, FALSE],
				['2025', \series\TaskUi::convertTime(235.25), NULL, TRUE],
				['2024', \series\TaskUi::convertTime(195.65), NULL, FALSE],
				['2023', \series\TaskUi::convertTime(12.5), NULL, FALSE],
			],
			[
				[s("Inscriptions"), '12', NULL, FALSE],
				[s("Lecteurs actifs"), '16', NULL, TRUE],
				[s("Ont tout suivi"), '3', s("25 % à ce jour"), FALSE],
				[s("Note moyenne"), '<span class="util-unit">4,2 <small>'.\Asset::icon('star-fill').'</small></span>', s("5 avis"), FALSE],
			],
		] as $figures) {

			$h .= '<div class="util-figures-overflow-wrapper">';

				$h .= '<div class="util-figures util-figures-overflow">';

					foreach($figures as [$label, $value, $share, $selected]) {
						$h .= '<a href="#design-figuresSelected" class="util-figure'.($selected ? ' util-figure-selected' : '').'">';
							$h .= '<div class="util-figure-label util-figure-label-top">'.$label.'</div>';
							$h .= '<div class="util-figure-value">'.$value.'</div>';
							if($share !== NULL) {
								$h .= '<div class="util-figure-label">'.$share.'</div>';
							}
						$h .= '</a>';
					}

				$h .= '</div>';

			$h .= '</div>';

		}

		return $h;

	}

	public function figuresLine(): string {

		$h = '<p class="util-annotation">'.s("util-figures-line pose des chiffres en ligne sous un titre, sans le fond ni le cadre d'util-figure, chacun séparé du précédent par un filet. La rangée introduit l'écran sans le résumer : une page dont les chiffres sont le sujet garde util-figures.").'</p>';
		$h .= '<p class="util-annotation">'.s("Chaque util-figures-line-item, un lien le plus souvent, prend la largeur de son contenu. Ses enfants sont ceux d'util-figure : util-figure-value ramenée à 1,6 rem, util-figure-label, puis util-figure-variation, qui porte un écart par span, l'un sous l'autre au bureau et bout à bout sous 576 px. Un util-bars facultatif se cale à droite de la valeur, et l'intitulé passe alors à la ligne dans la largeur qu'ils font ensemble. L'appelant pose la largeur du graphique. Sous 576 px, chaque chiffre prend un rang et perd son filet.").'</p>';

		$figures = [
			['23', NULL, s("ventes livrées la semaine passée"), ['−12 %', FALSE], ['−44 %', FALSE], [[0.61, 0.8], [0.85, 0.98], [0.76, 0.83], [0.76, 0.66], [0.61, 0.68], [0.71, 0.68], [0.83, 0.71], [0.8, 0.73], [0.76, 0.85], [0.9, 0.83], [0.71, 0.98], [0.63, 1], [0.56, 1]]],
			['12 714', s("€ HT"), s("livrés la semaine passée"), ['−31 %', FALSE], ['−64 %', FALSE], [[0.7, 0.58], [0.65, 0.67], [0.64, 0.71], [0.77, 0.77], [0.86, 0.92], [1, 0.94], [0.99, 0.7], [0.88, 0.7], [0.81, 0.77], [0.76, 0.56], [0.67, 0.56], [0.37, 0.59], [0.26, 0.7]]],
			['25', NULL, s("commandes en ligne la semaine passée"), ['+9 %', TRUE], ['−17 %', FALSE], [[0.83, 1], [0.64, 0.83], [0.69, 0.58], [0.56, 0.58], [0.61, 0.64], [0.75, 0.69], [0.69, 0.69], [0.67, 0.83], [0.83, 0.81], [0.75, 0.97], [0.64, 0.97], [0.64, 0.94], [0.69, 0.83]]],
		];

		foreach([TRUE, FALSE] as $withBars) {

			$h .= '<div class="util-figures-line mb-2">';

				foreach($figures as [$value, $unit, $label, [$previous, $previousUp], [$lastYear, $lastYearUp], $columns]) {

					$h .= '<a href="#design-figuresLine" class="util-figures-line-item">';
						$h .= '<div class="util-figure-value">'.(($unit === NULL) ? $value : '<span class="util-unit">'.$value.' <small>'.$unit.'</small></span>').'</div>';
						if($withBars) {
							$h .= $this->getBarsBefore($columns, 'var(--color-brand)');
						}
						$h .= '<div class="util-figure-label">'.$label.'</div>';
						$h .= '<div class="util-figure-variation">';
							$h .= '<span>'.self::getVariationUp(s("{value} sur la précédente", ['value' => $previous]), $previousUp).',</span> ';
							$h .= '<span>'.self::getVariationUp(s("{value} sur {year}", ['value' => $lastYear, 'year' => 2025]), $lastYearUp).'</span>';
						$h .= '</div>';
					$h .= '</a>';

				}

			$h .= '</div>';

		}

		return $h;

	}

	private static function getVariationUp(string $text, bool $up): string {
		return $up ? '<span class="util-figure-variation-up">'.$text.'</span>' : $text;
	}

	// Treize semaines de la démonstration : une paire [--bar, --before] par colonne, NULL quand l'an dernier n'a rien à montrer.
	private function getBarsBefore(array $columns, ?string $color): string {

		$h = '<div class="util-bars util-bars-before" style="--bars: 13; width: 14rem; height: 2.6rem'.($color === NULL ? '' : '; color: '.$color).'">';
			foreach($columns as [$bar, $before]) {
				$h .= '<span style="--bar: '.$bar.($before === NULL ? '' : '; --before: '.$before).'"></span>';
			}
		$h .= '</div>';

		return $h;

	}

	public function calendars(): string {

		$h = '<p class="util-annotation">'.s("util-calendar montre une échéance comme une page de calendrier, le jour dans util-calendar-day et son mois dans util-calendar-month. Elle se pose par \\util\\DateUi::calendar(), et remplace la date en chiffres là où l'échéance est ce que l'oeil cherche en premier : dans une liste, un intitulé « Livraison » suivi de « 15/09/2026 » se lit après tous les autres chiffres, la pastille avant.").'</p>';
		$h .= '<p class="util-annotation">'.s("Elle prend la teinte de ce qui la porte, par la variable --accent d'un util-deck-accent-* ou d'une util-card-accent-*, et reste grise quand personne ne l'a posée.").'</p>';

		$h .= '<div style="display: flex; gap: 1rem;">';
			$h .= \util\DateUi::calendar('2026-09-15');
			$h .= '<span class="util-deck-accent-order">'.\util\DateUi::calendar('2026-10-02').'</span>';
			$h .= '<span class="util-deck-accent-success">'.\util\DateUi::calendar('2026-11-24').'</span>';
			$h .= '<span class="util-deck-accent-danger">'.\util\DateUi::calendar('2026-12-31').'</span>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Le nom du jour à la place du mois").'</h4>';
		$h .= '<p class="util-annotation">'.s("Dans une rangée de jours, \\util\\DateUi::calendarWeekday() met le nom court du jour à la place du mois. Le jour qu'on regarde porte util-calendar-selected, qui remplit la pastille de sa teinte.").'</p>';

		$h .= '<div style="display: flex; gap: 1rem;">';
			$h .= \util\DateUi::calendarWeekday('2026-09-28');
			$h .= \util\DateUi::calendarWeekday('2026-09-29', selected: TRUE);
			$h .= '<span class="util-deck-accent-success">'.\util\DateUi::calendarWeekday('2026-09-30').'</span>';
			$h .= '<span class="util-deck-accent-success">'.\util\DateUi::calendarWeekday('2026-10-01', selected: TRUE).'</span>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une pastille plus grande").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-calendar-lg grandit la pastille (calendarWeekday(), large: TRUE), et la rétrécit un peu sous 576 px pour que sept jours tiennent sur la largeur du téléphone. Une rangée qui sert à changer de jour et dit le temps de chacun prend util-days, plus bas, dont toute la colonne est la cible.").'</p>';

		$h .= '<div style="display: flex; gap: 0.75rem;">';
			$h .= \util\DateUi::calendarWeekday('2026-09-28', large: TRUE);
			$h .= \util\DateUi::calendarWeekday('2026-09-29', selected: TRUE, large: TRUE);
			$h .= \util\DateUi::calendarWeekday('2026-09-30', large: TRUE);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une valeur du jour sous la date").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une pastille peut porter une valeur du jour sur une troisième ligne, util-calendar-value, par calendarWeekday(), value: …. Un jour sans valeur n'en a pas, et dans une rangée util-days, toutes les pastilles prennent la hauteur de la plus haute. Modèle : la rangée des jours de la page d'une intervention du planning, avec le temps de chaque jour.").'</p>';

		$h .= '<div class="util-days">';
			foreach(['2026-09-28' => '2h30', '2026-09-29' => NULL, '2026-09-30' => '1h00'] as $date => $value) {
				$h .= '<div class="util-day">'.\util\DateUi::calendarWeekday($date, selected: ($date === '2026-09-30'), large: TRUE, value: $value).'</div>';
			}
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Aucun jour").'</h4>';
		$h .= '<p class="util-annotation">'.s("Quand le choix d'un jour admet aussi « aucun jour », la rangée commence par une pastille sans date, util-calendar-none, qui écrit son mot à la place du jour et du mois, un mot par ligne. Elle se choisit comme les autres. Le panneau « Nouvelle intervention » du planning la pose devant les sept jours de la semaine.").'</p>';

		$h .= '<div class="util-days">';
			$h .= '<div class="util-day is-selected"><div class="util-calendar util-calendar-selected util-calendar-none"><span class="util-calendar-month">'.s("Non défini").'</span></div></div>';
			foreach(['2026-09-28', '2026-09-29', '2026-09-30'] as $date) {
				$h .= '<div class="util-day">'.\util\DateUi::calendarWeekday($date).'</div>';
			}
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une semaine, puis son jour").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-week-picker propose quelques semaines en onglets, nommées d'après la semaine en cours et datées, puis un dernier onglet qui ouvre les autres. L'onglet choisi descend sur le tiroir util-week-picker-days, une rangée util-days des jours de sa semaine. Une semaine prise par le dernier onglet le renomme d'après elle. « Quand », dans « Nouvelle intervention » du planning, s'en sert.").'</p>';

		$h .= '<div class="util-week-picker">';
			$h .= '<div class="util-week-picker-weeks">';
				foreach(['2026-W43' => s("Cette semaine"), '2026-W44' => s("Semaine prochaine")] as $week => $name) {
					$h .= '<a class="util-week-picker-week'.($week === '2026-W43' ? ' selected' : '').'"><span class="util-week-picker-week-name">'.$name.'</span><span class="util-week-picker-week-dates">'.\util\DateUi::weekToDays($week, short: TRUE, withYear: FALSE).'</span></a>';
				}
				$h .= '<a class="util-week-picker-week util-week-picker-other">'.s("Autre…").'</a>';
			$h .= '</div>';
			$h .= '<div class="util-week-picker-days util-days">';
				$h .= '<div class="util-day is-selected"><div class="util-calendar util-calendar-selected util-calendar-none"><span class="util-calendar-month">'.s("Non défini").'</span></div></div>';
				foreach(['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'] as $date) {
					$h .= '<div class="util-day">'.\util\DateUi::calendarWeekday($date).'</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un jour qui ne se choisit pas").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un jour qui ne se choisit pas porte is-disabled, qui le grise et le rend insensible au clic. Dans « Nouvelle intervention », une intervention faite grise les jours à venir.").'</p>';

		$h .= '<div class="util-days">';
			foreach(['2026-09-28' => FALSE, '2026-09-29' => FALSE, '2026-09-30' => TRUE] as $date => $disabled) {
				$selected = ($date === '2026-09-29');
				$h .= '<div class="util-day'.($disabled ? ' is-disabled' : '').($selected ? ' is-selected' : '').'">'.\util\DateUi::calendarWeekday($date, selected: $selected).'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	public function calendarSheets(): string {

		$sheet = function(string $month, int $offset, int $days, array $held, array $out, bool $link = FALSE, ?int $selected = NULL): string {

			$h = '<div class="util-calendar-sheet">';
				$h .= '<div class="util-label">'.$month.'</div>';
				$h .= '<div class="util-calendar-sheet-grid">';
					foreach(['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $weekday) {
						$h .= '<span class="util-calendar-sheet-weekday">'.$weekday.'</span>';
					}
					$h .= str_repeat('<span></span>', $offset);
					for($day = 1; $day <= $days; $day++) {
						if(in_array($day, $out)) {
							$state = ' util-calendar-sheet-held util-calendar-sheet-out';
						} else if(in_array($day, $held)) {
							$state = ' util-calendar-sheet-held'.(($day === $selected) ? ' util-calendar-sheet-selected' : '');
						} else {
							$state = '';
						}
						if($link and $state !== '') {
							$h .= '<a href="#design-cards" class="util-calendar-sheet-day'.$state.'">'.$day.'</a>';
						} else {
							$h .= '<span class="util-calendar-sheet-day'.$state.'">'.$day.'</span>';
						}
					}
				$h .= '</div>';
			$h .= '</div>';

			return $h;

		};

		$h = '<p class="util-annotation">'.s("util-calendar-sheets range une page par mois, util-calendar-sheet, autant par rang que la largeur en tient et deux sur un téléphone. Chaque page porte son mois en util-label, puis la grille de la semaine commençant le lundi : les initiales des jours en util-calendar-sheet-weekday, une case vide par jour qui précède le 1er, puis chaque jour en util-calendar-sheet-day. Un mois sans date garde sa page, et une date sautée se voit comme un trou dans sa colonne.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un jour retenu porte util-calendar-sheet-held, plein dans la teinte de la rubrique (--secondary). util-calendar-sheet-out, qui s'y ajoute, le passe en contour : la date existe mais reste hors de ce qu'on est en train de choisir. La seconde marche de création d'un contrat d'AMAP le rend, et le rejoue quand la période change.").'</p>';

		$h .= '<div class="util-calendar-sheets">';
			$h .= $sheet(s("Novembre 2026"), 6, 30, [12, 19, 26], [5]);
			$h .= $sheet(s("Décembre 2026"), 1, 31, [3, 10, 17], []);
			$h .= $sheet(s("Août 2027"), 6, 31, [], []);
			$h .= $sheet(s("Septembre 2027"), 2, 30, [2], [9, 16, 23, 30]);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un calendrier seul sur la largeur, ses jours en lien et le jour ouvert").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-calendar-sheets-spaced aère un calendrier qui occupe seul la largeur de l'écran : les pages s'écartent de --space-xl et les rangs de --space-lg, au lieu de --space-md.").'</p>';

		$h .= '<p class="util-annotation">'.s("Un jour retenu peut être un lien, a.util-calendar-sheet-day, vers ce qu'il désigne : le survol épaissit son contour sans changer sa teinte. L'écran des distributions d'une AMAP ouvre ainsi le panneau de chaque date, et laisse la liste au téléphone, où un jour ne fait pas la largeur d'un doigt.").'</p>';

		$h .= '<p class="util-annotation">'.s("util-calendar-sheet-selected marque le jour dont la page montre la date, d'un anneau à l'encre détaché du rond : le jour garde la teinte de son état, et l'anneau tient sous le survol. L'onglet Distributions d'un contrat d'AMAP ouvre ainsi la fiche d'une date sous son calendrier, le 18 novembre ci-dessous.").'</p>';

		$h .= '<div class="util-calendar-sheets util-calendar-sheets-spaced">';
			$h .= $sheet(s("Novembre 2026"), 6, 30, [11, 18, 25], [4], TRUE, 18);
			$h .= $sheet(s("Décembre 2026"), 1, 31, [2, 9, 16, 30], [23], TRUE);
		$h .= '</div>';

		return $h;

	}

	public function days(): string {

		$day = function(string $date, string $value, ?string $column, array $states = [], bool $calendar = TRUE, string $after = ''): string {

			$link = ($calendar and in_array('is-future', $states) === FALSE);
			$tag = $link ? 'a' : 'div';

			$h = '<'.$tag.($link ? ' href="#design-cards"' : '').' class="util-day '.implode(' ', $states).'">';
				$h .= '<b class="util-day-value">'.$value.'</b>';
				if($column !== NULL) {
					$h .= '<span class="util-day-column">'.$column.'</span>';
				}
				if($calendar) {
					$h .= \util\DateUi::calendarWeekday($date, in_array('is-selected', $states));
				}
				$h .= $after;
			$h .= '</'.$tag.'>';

			return $h;

		};

		$worked = fn(int $worked, int $done) => '<span class="util-day-worked" style="height: '.$worked.'%"><span class="util-day-done" style="height: '.$done.'%"></span></span>';
		$done = fn(int $done) => '<span class="util-day-done" style="height: '.$done.'%"></span>';

		$h = '<p class="util-annotation">'.s("util-days range les sept jours d'une semaine, chacun un util-day : sa journée en util-day-value, puis util-day-column, dont util-day-worked monte à la hauteur de la journée sur une échelle commune et dont util-day-done remplit le bas de ce qui y a été fait, puis sa pastille calendarWeekday(). Un jour passé où il manque une journée prend pour valeur un « + », l'emplacement à remplir (btn-dashed), en span puisque le jour entier est le lien, et sa colonne ne porte que ce qui y a été fait ; s'il n'y a rien du tout, il n'a pas de colonne ; un jour à venir porte is-future, hachuré et sans valeur ; is-over passe ce qui a été fait en alerte quand il dépasse la journée.").'</p>';
		$h .= '<p class="util-annotation">'.s("is-today teinte la pastille d'aujourd'hui, is-selected remplit celle du jour qu'on regarde, toutes deux dans la teinte de la production, posée par le composant et non lue dans --secondary : une semaine de temps a la même apparence dans toutes les rubriques. Le jour entier est la cible. Sous 576 px, les sept jours se partagent la largeur du téléphone, un jour enveloppé par la saisie de sa journée qu'il ouvre (DurationUi::getTiled(), tile: …) comme un autre : l'enveloppe prend sa colonne. Le planning le rend par TimeWeekUi::getDay(), dans la tête du bandeau du temps, sur l'écran Temps de travail, où chaque jour du suivi RH seul ouvre ainsi sa journée, et dans le tableau de l'équipe. util-bars compare des quantités sans cellule cliquable ni date : il ne le remplace pas.").'</p>';

		$h .= '<div class="util-days">';
			$h .= $day('2026-09-28', '&nbsp;', NULL);
			$h .= $day('2026-09-29', '3h00', $worked(25, 100), ['is-over']);
			$h .= $day('2026-09-30', '<span class="btn btn-only-icon btn-dashed">'.\Asset::icon('plus-lg').'</span>', $done(15), ['is-selected']);
			$h .= $day('2026-10-01', '6h00', $worked(50, 40), ['is-today']);
			$h .= $day('2026-10-02', '&nbsp;', '', ['is-future']);
			$h .= $day('2026-10-03', '&nbsp;', '', ['is-future']);
			$h .= $day('2026-10-04', '&nbsp;', '', ['is-future']);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un jour d'absence").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un jour d'absence porte is-absent, et « Absent » pour valeur : sa colonne se hachure de rouge, à venir compris, et une journée saisie ce jour-là se peint par-dessus. Le planning le pose sur la semaine d'une personne. La hachure est le token --absent-hatch de reboot.css, aux intervalles transparents, que lit aussi toute autre marque d'absence, comme les absences posées sur la frise des périodes de l'onglet Personnes.").'</p>';

		$absent = s("Absent");

		$h .= '<div class="util-days">';
			$h .= $day('2026-09-28', '7h30', $worked(63, 60));
			$h .= $day('2026-09-29', $absent, $worked(25, 100), ['is-absent']);
			$h .= $day('2026-09-30', $absent, '', ['is-absent', 'is-selected']);
			$h .= $day('2026-10-01', '6h00', $worked(50, 40), ['is-today']);
			$h .= $day('2026-10-02', $absent, '', ['is-future', 'is-absent']);
			$h .= $day('2026-10-03', '&nbsp;', '', ['is-future']);
			$h .= $day('2026-10-04', '&nbsp;', '', ['is-future']);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Les semaines d'un mois").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-days-month empile les semaines d'un mois, sans colonne : sept colonnes égales pour qu'un jour s'aligne sur celui de la semaine d'avant, et la pastille en grand, qui porte « Férié » un jour férié. Les jours d'un autre mois portent is-disabled ; is-warning passe en alerte une journée que personne n'a saisie et qui reste à confirmer. Le récapitulatif de l'équipe le rend par hr\\RecapUi, où le jour entier ouvre la saisie de sa journée.").'</p>';

		$dayMonth = function(string $date, string $value, array $states = [], ?string $calendarValue = NULL): string {

			$tag = in_array('is-disabled', $states) ? 'div' : 'a';

			$h = '<'.$tag.($tag === 'a' ? ' href="#design-cards"' : '').' class="util-day '.implode(' ', $states).'">';
				$h .= '<b class="util-day-value">'.$value.'</b>';
				$h .= \util\DateUi::calendarWeekday($date, large: TRUE, value: $calendarValue);
			$h .= '</'.$tag.'>';

			return $h;

		};

		$h .= '<div class="util-days util-days-month">';
			$h .= $dayMonth('2026-04-27', '&nbsp;', ['is-disabled']);
			$h .= $dayMonth('2026-04-28', '&nbsp;', ['is-disabled']);
			$h .= $dayMonth('2026-04-29', '&nbsp;', ['is-disabled']);
			$h .= $dayMonth('2026-04-30', '&nbsp;', ['is-disabled']);
			$h .= $dayMonth('2026-05-01', '5h45', calendarValue: s("Férié"));
			$h .= $dayMonth('2026-05-02', '&nbsp;');
			$h .= $dayMonth('2026-05-03', '&nbsp;');
		$h .= '</div>';
		$h .= '<div class="util-days util-days-month mt-1">';
			$h .= $dayMonth('2026-05-04', '7h00', ['is-warning']);
			$h .= $dayMonth('2026-05-05', '7h00', ['is-warning']);
			$h .= $dayMonth('2026-05-06', '7h00');
			$h .= $dayMonth('2026-05-07', '<span class="color-muted">'.$absent.'</span>');
			$h .= $dayMonth('2026-05-08', '7h00', ['is-warning'], s("Férié"));
			$h .= $dayMonth('2026-05-09', '&nbsp;');
			$h .= $dayMonth('2026-05-10', '&nbsp;');
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("La saisie de la semaine").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-days-entry fait de la rangée la saisie de la semaine : la valeur de chaque jour prend la largeur du jour et porte la tuile du sélecteur de durée habituel (DurationUi::getTiled()), qui s'ouvre au clic et s'enregistre au choix de la durée. La pastille, en grand, reste au-dessus, et mène au jour quand il a une page. Un jour à saisir dit « à saisir » sur sa tuile, au ton courant : c'est un jour que compte util-days-entry-total, et une tuile vide qu'il ne compte pas, aujourd'hui, un jour férié ou hors contrat, garde son crayon seul. Un jour d'absence se hachure et se lit sans tuile, un jour à venir s'efface à demi, sans hachures, et se lit sans tuile. Un jour férié garde sa tuile et dit « Férié » dans sa pastille, que les autres pastilles suivent en hauteur.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-days-entry-total se pose dans le util-title, après le titre, et le suit : la durée saisie, puis util-days-entry-detail, qui réunit la jauge sur l'horaire visé, dont util-progress-part-todo hachure la part qui reste à saisir, et ce qui manque. Quand la ligne ne les tient plus, le détail passe à la ligne, et la valeur reste à côté du titre. Sous 768 px la pastille prend la taille d'une cible tactile, et sous 576 px les sept jours restent sur une ligne, la valeur plus petite, et « à saisir » laisse son crayon seul. Le composant ne se borne pas en largeur, l'écran qui l'emploie le fait : le planning le rend par TimeDayUi::getWeek(), sur le Temps de travail d'une personne, borné à 46rem.").'</p>';

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

		$field = fn(string $date, ?float $time, bool $missing = FALSE) => new \planning\DurationUi()->getTiled('design-entry-'.$date, $time, \planning\TimeDayUi::HOURS, \planning\TimeDayUi::getPanelDay($date), $missing ? s("à saisir") : '');

		$dayEntry = fn(string $date, string $value, array $states = [], ?string $calendarValue = NULL) => '<div class="util-day '.implode(' ', $states).'"><a href="#design-cards" class="util-days-entry-date">'.\util\DateUi::calendarWeekday($date, large: TRUE, value: $calendarValue).'</a><div class="util-day-value">'.$value.'</div></div>';

		$h .= '<div class="util-title">';
			$h .= '<h3>'.s("Temps de travail de la semaine").'</h3>';
			$h .= '<div class="util-days-entry-total">';
				$h .= '<b class="util-days-entry-value">13h30</b>';
				$h .= '<div class="util-days-entry-detail">';
					$h .= '<div class="util-progress">';
						$h .= '<div class="util-progress-part bg-done" style="width: 39%"></div>';
						$h .= '<div class="util-progress-part util-progress-part-todo" style="width: 20%"></div>';
					$h .= '</div>';
					$h .= '<span class="util-days-entry-note">'.s("sur {value}", '35h00').', '.p("{value} jour à saisir", "{value} jours à saisir", 1).'</span>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';
		$h .= '<div class="util-days util-days-entry">';
			$h .= $dayEntry('2026-11-09', $field('2026-11-09', 7.5));
			$h .= $dayEntry('2026-11-10', $field('2026-11-10', NULL, TRUE));
			$h .= $dayEntry('2026-11-11', $field('2026-11-11', NULL), calendarValue: s("Férié"));
			$h .= $dayEntry('2026-11-12', '<span class="color-muted">'.$absent.'</span>', ['is-absent']);
			$h .= $dayEntry('2026-11-13', $field('2026-11-13', 6.0), ['is-today']);
			$h .= $dayEntry('2026-11-14', '&nbsp;', ['is-future']);
			$h .= $dayEntry('2026-11-15', '&nbsp;', ['is-future']);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Les absents d'une équipe").'</h4>';
		$h .= '<p class="util-annotation">'.s("Pour une équipe, util-day-absents range sous la pastille la vignette de chaque absent du jour, cerclée de rouge. Au-delà de deux, la première reste et util-day-absents-more compte les autres dans la forme d'une vignette : aucun jour ne s'élargit. Un jour sans absent garde sa rangée vide, et les pastilles restent alignées. Le planning le rend par TimeWeekUi::getDayAbsents(), sur le Temps de travail de l'équipe.").'</p>';

		$vignette = fn(int $id, string $firstName, string $lastName) => \user\UserUi::getVignette(new \user\User(['id' => $id, 'vignette' => NULL, 'firstName' => $firstName, 'lastName' => $lastName]), '1.25rem');

		$lea = $vignette(1, s("Léa"), s("Dubois"));
		$claire = $vignette(2, s("Claire"), s("Martin"));
		$paul = $vignette(3, s("Paul"), s("Roux"));

		$absents = fn(string $content = '') => '<div class="util-day-absents">'.$content.'</div>';

		$h .= '<div class="util-days">';
			$h .= $day('2026-09-28', '21h30', $worked(60, 55), after: $absents($lea));
			$h .= $day('2026-09-29', '14h00', $worked(40, 45), after: $absents($lea.$claire));
			$h .= $day('2026-09-30', '&nbsp;', $done(15), ['is-selected'], after: $absents());
			$h .= $day('2026-10-01', '7h00', $worked(20, 15), ['is-today'], after: $absents($paul.'<span class="util-day-absents-more">'.s("+{value}", 2).'</span>'));
			$h .= $day('2026-10-02', '&nbsp;', '', ['is-future'], after: $absents($claire));
			$h .= $day('2026-10-03', '&nbsp;', '', ['is-future'], after: $absents());
			$h .= $day('2026-10-04', '&nbsp;', '', ['is-future'], after: $absents());
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un jour en cellule de tableau").'</h4>';
		$h .= '<p class="util-annotation">'.s("Sans pastille, un util-day sert de cellule de tableau, sous une tête de dates commune à toutes les lignes : le tableau de l'équipe du bandeau en est fait.").'</p>';

		$h .= '<div class="util-days">';
			$h .= $day('2026-09-28', '7h30', $worked(63, 60), calendar: FALSE);
			$h .= $day('2026-09-29', '3h00', $worked(25, 100), ['is-over'], calendar: FALSE);
			$h .= $day('2026-09-30', '&nbsp;', '', calendar: FALSE);
			$h .= $day('2026-10-01', '6h00', $worked(50, 40), calendar: FALSE);
		$h .= '</div>';

		return $h;

	}

	public function decks(): string {

		$h = '<p class="util-annotation">'.s("util-deck présente un objet d'une liste : son identité dans util-deck-head, puis autant de bandes qu'il a de choses à dire, puis son étage déplié. Il sert aussi à choisir ce que le formulaire suivant va créer, la catégorie d'une nouvelle structure par exemple : chaque choix est un deck lien. La teinte se pose par util-deck-accent-*, comme sur une util-card-accent, et sert la bande d'état et les pastilles de date qu'il contient.").'</p>';
		$h .= '<p class="util-annotation">'.s("Quatre natures, dont deux se cumulent. Le lien est une balise a qui porte util-deck-arrow, et rien dedans n'est cliquable, car un objet qui n'a qu'une destination n'a pas de bouton. Le menu est aussi une balise a, sans destination : elle porte data-dropdown, sa dropdown-list la suit, et son util-deck-arrow prend le triangle des menus déroulants au lieu du chevron d'une page, et DesignUi::getDeckDropdown() l'écrit. Le dépliable bascule util-deck-open par sa tête, qui porte util-deck-chevron. Les portes servent l'objet qui a plusieurs destinations, chacune un bouton dans util-deck-band-doors, et le bloc ne porte alors aucun chevron. util-deck-band-doors-1, -2 et -3 donnent aux boutons une largeur égale, pour qu'ils s'alignent d'une carte à l'autre ; sans eux, chaque bouton prend la largeur de son libellé. Une porte seule tient la moitié de la bande, à gauche, et plusieurs portes dans util-deck-band-doors-1 s'empilent sur toute la largeur. util-deck-band-doors-full rend la pleine largeur à la porte seule, sur la demande explicite d'un écran et jamais par défaut. Une porte est un bouton en teinte claire, btn-light, ou la teinte claire du domaine où elle mène (btn-production-light, btn-commercialisation-light), et jamais btn-outline-primary, que la règle du plein et du contour réserve aux actions hors d'un deck. Le geste qui retire ou déconnecte y prend btn-danger-light, la même teinte claire en rouge, et non un contour. Les portes et le dépliable se cumulent, le lien et le menu ne se cumulent avec rien.").'</p>';
		$h .= '<p class="util-annotation">'.s("Seule exception à un deck dont rien n'est cliquable, des chiffres qui filtrent la liste sous la rangée. Le bloc est alors un div et non une balise a, chaque util-deck-value porte son lien et un title qui dit ce qu'il compte, et le chiffre du filtre actif passe en gras. Une bande de portes ferait ici une carte plus haute que ses voisines pour trois destinations qui sont déjà les chiffres.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un deck peut aussi n'avoir aucune nature, et c'est courant. Celui dont le geste, un interrupteur ou une case à cocher, vit dans sa bande ou à droite de sa tête ne mène nulle part : il n'a ni chevron, ni bloc cliquable, ni bouton. Le rendre cliquable ferait deux gestes d'un seul clic, ce que l'exclusion ci-dessus interdit déjà entre le lien et le dépliable.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une pastille posée dans util-deck-title passe sous le titre quand la place manque, puis s'enroule si elle reste plus large que le deck : son libellé, dès qu'il porte des espaces, ne fixe plus la largeur du deck, ni celle de la grille qui le porte.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un util-deck de nature « lien » et un util-button sont tous deux des liens : ce n'est donc pas le geste qui les sépare, mais la place dans l'écran. Le relevé du dépôt le montre — la grande majorité des util-button posés hors de cette page sont des liens, et la frontière « lien contre action » viderait donc le composant au lieu de le distinguer. Un util-button est un choix d'entrée qui structure l'écran, en petit nombre ; un util-deck est une ligne d'une liste qui peut s'allonger.").'</p>';

		$h .= '<div class="util-cards util-cards-2 util-cards-start mb-2">';

			$h .= '<a href="#design-cards" class="util-deck util-deck-accent-order">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-commercialisation">'.\Asset::icon('shop').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Un lien").'<span class="badge badge-solid badge-accounting">'.s("Titres-restaurant et chèques vacances en ligne").'</span></div>';
						$h .= '<div class="util-deck-figures">';
							$h .= \util\DateUi::calendar('2026-09-15');
							$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Ventes").'</span><span class="util-deck-value">27</span></div>';
							$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Montant").'</span><span class="util-deck-value">446,65 €</span></div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck-band util-deck-band-state">'.s("util-deck-band-state, teintée par l'accent").'</div>';
			$h .= '</a>';

			$h .= '<div class="util-deck util-deck-accent-success">';
				$h .= \main\DesignUi::getDeckHead('house', 'production', s("Des portes"), s("Plusieurs destinations, donc aucun chevron"));
				$h .= '<div class="util-deck-band util-deck-band-state">'.s("Un état, puis les portes").'</div>';
				$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-3">';
					$h .= '<a href="#design-cards" class="btn btn-production-light">'.\Asset::icon('leaf').' '.s("Production").'</a>';
					$h .= '<a href="#design-cards" class="btn btn-commercialisation-light">'.\Asset::icon('basket3').' '.s("Vente").'</a>';
				$h .= '</div>';
				$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-2">';
					$h .= '<a href="#design-cards" class="btn btn-light">'.\Asset::icon('graph-up').' '.s("Une entrée secondaire").'</a>';
					$h .= '<a href="#design-cards" class="btn btn-danger-light">'.s("Déconnecter").'</a>';
				$h .= '</div>';
				$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-1">';
					$h .= '<a href="#design-cards" class="btn btn-light">'.\Asset::icon('arrow-right').' '.s("Une porte seule").'</a>';
				$h .= '</div>';
				$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-1 util-deck-band-doors-full">';
					$h .= '<a href="#design-cards" class="btn btn-light">'.\Asset::icon('arrows').' '.s("util-deck-band-doors-full, sur toute la largeur").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck util-deck-selected">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.\Asset::icon('check-circle-fill').s("Des chiffres qui filtrent").'</div>';
						$h .= '<div class="util-deck-figures">';
							$h .= \main\DesignUi::getDeckFigure(s("Structures"), '<a href="#design-cards">1 482</a>');
							$h .= \main\DesignUi::getDeckFigure(s("Abonnements"), '<a href="#design-cards" title="'.s("Virements attendus").'"><b>3</b></a> / <a href="#design-cards" title="'.s("Virements à vérifier").'">1</a>');
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck util-deck-2 util-deck-open">';
				$h .= '<a class="util-deck-head" '.\main\DesignUi::getDeckToggle().'>';
					$h .= '<div class="util-icon util-icon-round util-icon-accounting">'.\Asset::icon('journal-text').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Un dépliable").'</div>';
						$h .= '<p>'.s("4 éléments, résumés sous le titre").'</p>';
					$h .= '</div>';
					$h .= '<div class="util-deck-aside color-accounting">'.s("2 à traiter").'</div>';
					$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
				$h .= '</a>';
				$h .= '<div class="util-deck-fold">';
					$h .= '<a href="#design-cards" class="util-deck-row">';
						$h .= '<span class="util-deck-row-name">'.s("Une ligne qui ne mène qu'à un endroit").'</span>';
						$h .= '<span class="color-accounting">2</span>';
						$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
					$h .= '</a>';
					$h .= '<div class="util-deck-row">';
						$h .= '<a href="#design-cards" class="util-deck-row-name">util-deck-row-name</a>';
						$h .= '<a href="#design-cards" class="btn btn-outline-primary btn-sm">'.s("Action").'</a>';
					$h .= '</div>';
					$h .= '<div class="util-deck-row">';
						$h .= '<span class="util-deck-row-name">'.s("Un nom qui ne mène nulle part").'</span>';
						$h .= '<span class="color-success">'.\Asset::icon('check-lg').' '.s("Un état").'</span>';
					$h .= '</div>';
					$h .= '<div class="util-deck-content">';
						$h .= '<p class="mb-0">'.s("util-deck-content reçoit un tableau ou un formulaire, avec le retrait des lignes.").'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Aucune nature").'</div>';
						$h .= '<p>'.s("la décision se prend dans la bande, le bloc ne mène nulle part").'</p>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck-band">'.\util\TextUi::switch([], on: TRUE).'<span>'.s("Un réglage qui s'applique ici").'</span></div>';
			$h .= '</div>';

			$h .= \main\DesignUi::getDeckDropdown('calendar-week', 'commercialisation', s("Un menu"), '<a href="#design-cards" class="dropdown-item selected">'.s("Octobre 2026").'</a><a href="#design-cards" class="dropdown-item">'.s("Septembre 2026").'</a><a href="#design-cards" class="dropdown-item">'.s("Août 2026").'</a>', s("un choix parmi plusieurs, chacun un téléchargement"));

			$h .= '<div class="util-deck util-deck-1">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-commercialisation">'.\Asset::icon('shop').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("La garniture").'</div>';
						$h .= '<p>'.s("un logo au gabarit de 2,5 rem, ou une util-icon ronde quand il n'y en a pas").'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-1, util-deck-2 et util-deck-3 dessinent une, deux ou trois feuilles sous un bloc fermé : elles disent qu'il en cache.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le dépliable s'ouvre par sa seule tête, une balise a.util-deck-head qui bascule util-deck-open sur le bloc, ou qui mène à la page où il est ouvert quand le serveur pose util-deck-open. Une tête qui porte des gestes (un interrupteur, une roue) ne peut pas être un lien : elle reste une div.util-deck-head, et ses deux ouvreurs, le corps a.util-deck-body et le chevron a.util-deck-chevron, basculent chacun le bloc de part et d'autre de util-deck-aside, ou mènent tous deux à la page où il est ouvert. Son résumé est un paragraphe sous le titre ; ce qui appelle un geste sur l'objet (ce qui reste à traiter, une alerte) va à droite dans util-deck-aside, qui prend la largeur de son message sans dépasser 45 % de la tête. L'étage déplié aligne des util-deck-row : util-deck-row-name prend la place, ce qu'il porte se range à droite. Une ligne qui porte un bouton garde son nom pour lien et ne réagit pas au survol ; une ligne qui n'a qu'une destination est tout entière le lien, a.util-deck-row, avec util-deck-arrow et un fond au survol, ou avec util-deck-chevron quand elle mène à la page où elle est dépliée à sa place. Un tableau ou un formulaire y prend util-deck-content, qui peut suivre des lignes.").'</p>';

		$h .= '<div class="util-cards util-cards-1 mb-2">';

			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-inactive">'.\Asset::icon('list-check').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Des choses à faire").'</div>';
						$h .= '<p>'.s("une ligne faite parmi des lignes à faire").'</p>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck-fold">';
					$h .= '<div class="util-deck-row util-deck-row-state util-deck-accent-success">';
						$h .= '<a href="#design-cards" class="util-deck-row-name">'.s("Une chose faite").'</a>';
						$h .= '<span>'.\Asset::icon('check-lg').' '.s("Un état acquis").'</span>';
					$h .= '</div>';
					$h .= '<div class="util-deck-row">';
						$h .= '<a href="#design-cards" class="util-deck-row-name">'.s("Une chose à faire").'</a>';
						$h .= '<a href="#design-cards" class="btn btn-outline-primary btn-sm">'.s("Action").'</a>';
					$h .= '</div>';
					$h .= '<div class="util-deck-row">';
						$h .= '<a href="#design-cards" class="util-deck-row-name">'.s("Une autre chose à faire").'</a>';
						$h .= '<a href="#design-cards" class="btn btn-outline-primary btn-sm">'.s("Action").'</a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-row-state teinte le fond d'une ligne de l'étage déplié à la couleur de l'accent posé sur la ligne même, util-deck-accent-success pour une chose faite : l'état se lit sur la ligne, sans en ajouter une. Le texte d'état y garde la couleur du texte, le vert sur ce fond n'ayant pas assez de contraste.").'</p>';

		$h .= '<div class="util-cards util-cards-1 mb-2">';

			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Des éléments à voir").'</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck-fold">';
					$h .= '<a href="#design-cards" class="util-deck-row util-deck-row-unread">';
						$h .= '<span class="util-deck-row-name">'.s("Un élément pas encore vu").' <span class="util-annotation">'.s("son annotation").'</span></span>';
						$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
					$h .= '</a>';
					$h .= '<a href="#design-cards" class="util-deck-row util-deck-row-read util-deck-row-pending">';
						$h .= '<span class="util-deck-row-name">'.s("Un élément vu, pas encore ouvert").' <span class="util-annotation">'.s("son annotation").'</span></span>';
						$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
					$h .= '</a>';
					$h .= '<a href="#design-cards" class="util-deck-row util-deck-row-read">';
						$h .= '<span class="util-deck-row-name">'.s("Un élément déjà vu").' <span class="util-annotation">'.s("son annotation").'</span></span>';
						$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
					$h .= '</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck util-deck-unread">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Un regroupement pas encore vu").'</div>';
						$h .= '<p>'.s("son résumé").'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck util-deck-read util-deck-pending">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Un regroupement vu, pas encore ouvert").'</div>';
						$h .= '<p>'.s("son résumé").'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck util-deck-read">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Un regroupement déjà vu").'</div>';
						$h .= '<p>'.s("son résumé").'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-row-unread marque une ligne pas encore vue, util-deck-unread la tête d'un deck qui en regroupe : le gras et un fond neutre, et non la teinte d'accent de util-deck-row-state, puisque ne pas avoir vu n'est ni un état acquis ni une alerte. L'annotation y fonce pour garder son contraste. Dans une liste où le non-lu se marque, util-deck-row-read et util-deck-read ramènent les éléments déjà vus au poids normal : sans elles, le 500 de util-deck-row-name se distingue à peine du gras, et un titre de deck, gras d'office, ne se distingue pas du tout.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-deck-row-pending et util-deck-pending disent l'état intermédiaire d'une liste qui distingue « arrivé depuis la dernière visite » de « pas encore ouvert » : le gras seul, sans le fond, posé par-dessus util-deck-row-read ou util-deck-read qu'ils accompagnent. Trois états valent mieux qu'un fond qui reste allumé sur tout ce qui n'a pas été traité.").'</p>';
		$h .= '<div class="util-cards util-cards-1 mb-2">';
			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-fold">';
					$h .= '<div class="util-deck-row util-deck-row-unread util-deck-row-top">';
						$h .= \user\UserUi::getVignette(new \user\User(['id' => 3, 'vignette' => NULL, 'firstName' => s("Hugo"), 'lastName' => s("Bernard")]), '1.75rem');
						$h .= '<div class="util-deck-row-name">';
							$h .= '<b>'.s("Hugo Bernard").'</b>';
							$h .= '<p>'.s("Sur mon sachet de carottes, la date limite est dépassée de deux mois. Je peux quand même semer ?").'</p>';
							$h .= '<div class="util-annotation">'.s("Semer juste").' · <a href="#design-cards">'.s("1. Lire un sachet de graines").'</a></div>';
						$h .= '</div>';
						$h .= '<a href="#design-cards" class="btn btn-outline-primary" title="'.s("Marquer comme lu").'">'.\Asset::icon('check2').' <span class="hide-xs-down">'.s("Marquer comme lu").'</span></a>';
					$h .= '</div>';
					$h .= '<div class="util-deck-row util-deck-row-read util-deck-row-top">';
						$h .= \user\UserUi::getVignette(new \user\User(['id' => 4, 'vignette' => NULL, 'firstName' => s("Camille"), 'lastName' => s("Martin")]), '1.75rem');
						$h .= '<div class="util-deck-row-name">';
							$h .= '<b>'.s("Camille Martin").'</b>';
							$h .= '<p>'.s("Le tableau des densités m'a servi pour toute la planche de laitues, merci.").'</p>';
							$h .= '<div class="util-annotation">'.s("Semer juste").' · <a href="#design-cards">'.s("2. Choisir sa densité").'</a></div>';
						$h .= '</div>';
						$h .= '<span class="util-annotation">'.s("Lu par {value}", s("Julien Roux")).'</span>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-row-top range en haut une ligne qui court sur plusieurs lignes, un message sous le nom de son auteur : la vignette en tête et la colonne de droite restent sur la première ligne, et la vignette ne se resserre pas. Le texte sous le nom va dans un paragraphe de util-deck-row-name, au poids normal, y compris sur une ligne pas encore vue où le nom passe en gras. Employé par les messages de util-thread.").'</p>';

		$h .= '<div class="util-cards util-cards-1 util-thread mb-2">';
			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-fold">';
					$h .= '<div class="util-deck-row util-deck-row-top">';
						$h .= \user\UserUi::getVignette(new \user\User(['id' => 1, 'vignette' => NULL, 'firstName' => s("Léa"), 'lastName' => s("Dubois")]), '1.75rem');
						$h .= '<div class="util-deck-row-name">';
							$h .= '<b>'.s("Léa Dubois").'</b>';
							$h .= '<p>'.s("Cotisation de septembre, le prélèvement est passé deux fois.").'</p>';
							$h .= '<div class="util-annotation">'.s("Modifiée le {value}", s("24/09 à 09:40")).'</div>';
						$h .= '</div>';
						$h .= '<div class="util-thread-side">';
							$h .= '<span class="util-annotation">'.s("18/09").'</span>';
							$h .= '<div>';
								$h .= '<a class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('pencil-fill').'</a>&nbsp;&nbsp;';
								$h .= '<a class="btn btn-only-icon btn-sm btn-outline-danger">'.\Asset::icon('trash').'</a>';
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-row util-deck-row-top">';
						$h .= \user\UserUi::getVignette(new \user\User(['id' => 2, 'vignette' => NULL, 'firstName' => s("Claire"), 'lastName' => s("Martin")]), '1.75rem');
						$h .= '<div class="util-deck-row-name">';
							$h .= '<b>'.s("Claire Martin").'</b> <span class="badge badge-outline">'.s("Comptable").'</span>';
							$h .= '<div class="form-group mt-1" data-wrapper="text"><div><textarea class="form-control" rows="3">'.s("Le second prélèvement est remboursé le 30/09.").'</textarea></div></div>';
							$h .= '<div class="form-group"><div><a class="btn btn-sm btn-primary">'.s("Enregistrer").'</a> <a class="btn btn-sm btn-outline-primary">'.s("Annuler").'</a></div></div>';
						$h .= '</div>';
						$h .= '<div class="util-thread-side">';
							$h .= '<span class="util-annotation">'.s("19/09").'</span>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-thread est un fil de messages signés : util-deck-row-top pour chaque message, et util-thread-side pour sa colonne de droite, la date au-dessus des gestes. Les gestes sont la roue, ou le crayon et la corbeille quand il n'y en a que deux ; le crayon ouvre le message en modification sur place, le champ et ses deux boutons à la place du texte, et la corbeille demande confirmation. Sous 576px, la colonne passe sous le texte, alignée sur lui. Employé par le fil d'une leçon de formation et les commentaires des formations d'un groupe.").'</p>';
		$h .= '<p class="util-annotation">'.s("Seuls un util-deck lien et la tête d'un dépliable se soulèvent au survol : un deck à portes ou sans nature ne répond pas au clic et reste immobile.").'</p>';

		$h .= '<p class="util-annotation">'.s("Un interrupteur que le titre et le résumé suffisent à nommer se pose à droite de la tête, dans util-deck-aside : une bande par réglage allongerait une liste de fonctionnalités sans rien dire de plus. Il descend dans une bande quand il porte son propre libellé, une bande par interrupteur, pour plusieurs choix sur un même objet par exemple.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-deck-doors pose les portes à droite de la tête au lieu d'une bande, quand leurs libellés sont courts et tiennent à côté du titre : un objet par ligne, son geste au bout. Sous 576px elles passent sous le corps, en largeurs égales comme dans une bande. Un objet qui n'a qu'une destination y garde un bouton quand le verbe dit un geste que rien d'autre sur le deck ne dit.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-deck-colors aligne des util-deck-color entre le corps et la flèche, les couleurs d'un thème de boutique ou de site ; elles disparaissent sous 768px. util-deck-lg agrandit le titre, le résumé et la pastille, et cale la pastille en haut de la tête, pour une vitrine au texte long.").'</p>';

		$h .= '<div class="util-cards util-cards-1 mb-2">';

			$h .= '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-accounting">'.\Asset::icon('filetype-txt').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Des portes à droite").'</div>';
						$h .= '<p>'.s("util-deck-doors, sur la ligne du titre").'</p>';
					$h .= '</div>';
					$h .= '<div class="util-deck-doors">';
						$h .= '<a href="#design-cards" class="btn btn-sm btn-outline-primary">'.\Asset::icon('filetype-txt').' '.s("Format TXT").'</a>';
						$h .= '<a href="#design-cards" class="btn btn-sm btn-outline-primary">'.\Asset::icon('filetype-csv').' '.s("Format CSV").'</a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-2 mb-2">';

			$h .= '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-active">'.\Asset::icon('tag').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Un réglage à droite").'</div>';
						$h .= '<p>'.s("le titre et le résumé nomment ce que l'interrupteur allume").'</p>';
					$h .= '</div>';
					$h .= '<div class="util-deck-aside">'.\util\TextUi::switch([], on: TRUE, textOn: s("Oui"), textOff: s("Non")).'</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<a href="#design-cards" class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-commercialisation">'.\Asset::icon('palette-fill').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Les couleurs d'un thème").'</div>';
						$h .= '<p>'.s("util-deck-colors, avant la flèche").'</p>';
					$h .= '</div>';
					$h .= '<div class="util-deck-colors">';
						foreach(['#E1F7E8', '#1D4B53'] as $color) {
							$h .= '<span class="util-deck-color" style="background-color: '.$color.'"></span>';
						}
					$h .= '</div>';
					$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
				$h .= '</div>';
			$h .= '</a>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("La roue n'est pas une porte : un deck qui ne mène nulle part peut porter dans util-deck-aside une roue, un bouton dropdown-toggle qui ouvre les gestes sur l'objet, jamais avec util-deck-arrow, dont la flèche promettrait une tête cliquable. Un dépliable la pose entre ses deux ouvreurs, le corps et le chevron. Une entrée refusée y garde sa place comme dans tout menu déroulant. Sous 576px, la roue, l'interrupteur et les boutons d'un btn-group de util-deck-aside, comme l'interrupteur d'une util-deck-band, prennent une cible de 44px sans changer de dessin.").'</p>';

		$h .= '<div class="util-cards util-cards-2 mb-2">';

			$h .= '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('flower1').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Une roue à droite").'</div>';
						$h .= '<p>'.s("les gestes sur l'objet, dans un menu").'</p>';
					$h .= '</div>';
					$h .= '<div class="util-deck-aside">';
						$h .= \main\DesignUi::getGear(
							'<a href="#design-cards" class="dropdown-item">'.s("Dupliquer").'</a>'.
							'<div class="dropdown-divider"></div>'.
							'<a class="dropdown-item disabled"><div class="util-item-label">'.s("Retirer").'</div><div class="util-item-more">'.s("La raison, en une ligne").'</div></a>',
							s("Une roue à droite")
						);
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-add termine une suite de decks par la place du prochain : un btn-secondary-light à la largeur de la colonne et à la hauteur d'une tête de deck, qui ouvre la création. Il prend la teinte claire de l'univers et non le pointillé de btn-dashed, dont le gris atténué se lit comme éteint sur une grille de cartes pleines. Il se pose dans la grille comme une carte de plus, après la dernière. Modèle : la carte « Nouveau dossier » des justificatifs (ProofUi::getFolders()).").'</p>';

		$h .= '<div class="util-cards util-cards-2 mb-2">';

			$h .= '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-accounting">'.\Asset::icon('archive').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Achats").' <span class="util-counter">12</span></div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<a href="#design-cards" class="btn btn-secondary-light util-deck-add">'.\Asset::icon('plus-circle').' '.s("Nouveau dossier").'</a>';

		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-2 mb-2">';

			$h .= '<div class="util-deck util-deck-lg">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-accounting">'.\Asset::icon('bank').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("util-deck-lg").'</div>';
						$h .= '<p>'.s("Un texte de plusieurs phrases reste lisible : la taille monte d'un cran, et la pastille reste accrochée à la première ligne au lieu de flotter au milieu de la tête.").'</p>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck-band util-deck-band-doors">';
					$h .= '<a href="#design-cards" class="btn btn-outline-primary">'.s("Comment ça marche ?").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-deck util-deck-lg">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-commercialisation">'.\Asset::icon('basket').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Sans bande").'</div>';
						$h .= '<p>'.s("La pastille, le titre et le résumé montent ensemble, et une mise en avant se pose en gras sous le résumé.").'</p>';
						$h .= '<p><b>'.s("Une phrase mise en avant").'</b></p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-selected marque le deck retenu quand des decks liens filtrent la liste qu'ils surmontent : un voile sur la tête et le titre à la couleur du voile, précédé de \\Asset::icon('check-circle-fill'), jamais une bordure. Les autres prennent un voile plus léger au survol. Un deck est toujours retenu, celui par défaut quand l'adresse n'en nomme aucun.").'</p>';

		$h .= '<div class="util-cards util-cards-3 mb-2">';

			foreach([
				[s("À livrer"), '3', TRUE],
				[s("Toutes les ventes"), '42', FALSE],
				[s("7 derniers jours"), '9', FALSE],
			] as [$title, $value, $selected]) {
				$h .= '<a href="#design-cards" class="util-deck'.($selected ? ' util-deck-selected' : '').'">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.($selected ? \Asset::icon('check-circle-fill') : '').$title.'</div>';
							$h .= '<div class="util-deck-figures">'.\main\DesignUi::getDeckFigure(s("Ventes"), $value).'</div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</a>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-top cale le corps en haut de la tête et laisse la flèche ou la case au milieu : dans une rangée de decks qu'on lit d'un bloc à l'autre, un titre seul reste à la hauteur d'un titre suivi de chiffres, au lieu de descendre au milieu de sa tête étirée par la grille.").'</p>';

		$h .= '<div class="util-cards util-cards-3 mb-2">';

			foreach([
				[s("Mardi 15 septembre"), ''],
				[s("Mardi 22 septembre"), '<p>'.s("Point de retrait · Marché du samedi").'</p>'],
				[s("Mardi 29 septembre"), '<div class="util-deck-figures">'.\main\DesignUi::getDeckFigure(s("Ventes"), '12').\main\DesignUi::getDeckFigure(s("Montant"), '348,50 €').'</div>'],
			] as [$title, $content]) {
				$h .= '<a href="#design-cards" class="util-deck util-deck-top util-deck-accent-order">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$title.'</div>';
							$h .= $content;
						$h .= '</div>';
						$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-band util-deck-band-state">'.s("Commandes ouvertes").'</div>';
				$h .= '</a>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("Ce qui décide d'un objet se lit à droite dans util-deck-aside-list, avant le chevron. --deck-aside-width, posé sur la grille, donne à la liste la même largeur sur chaque carte, choisie pour la ligne la plus longue : les filets tombent l'un sous l'autre et les titres gardent la même largeur, quelle que soit la longueur de ce que chaque carte y écrit. Sous 768 px, la liste passe sous le titre et le chevron reste à côté de lui.").'</p>';

		$h .= '<div class="util-cards util-cards-1 mb-2" style="--deck-aside-width: 15rem">';

			foreach([
				[s("Région Bretagne"), s("Aide à l'investissement en circuits courts"), [['cash-coin', s("Jusqu'à 5 000 000 €"), TRUE], ['award', s("Subvention"), FALSE], ['calendar-event', s("Dépôt jusqu'au 31/10/2026"), FALSE]]],
				[s("État"), s("Crédit d'impôt"), [['award', s("Crédit d'impôt"), FALSE], ['calendar-event', s("Toute l'année"), FALSE]]],
			] as [$kicker, $title, $lines]) {
				$h .= '<a href="#design-cards" class="util-deck util-deck-top">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-kicker">'.$kicker.'</div>';
							$h .= '<div class="util-deck-title">'.$title.'</div>';
						$h .= '</div>';
						$h .= '<ul class="util-deck-aside-list">';
							foreach($lines as [$icon, $text, $total]) {
								$h .= '<li'.($total ? ' class="util-deck-aside-total"' : '').'>'.\Asset::icon($icon).' '.$text.'</li>';
							}
						$h .= '</ul>';
						$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
					$h .= '</div>';
				$h .= '</a>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("En sélection, des decks deviennent des blocs à cocher pour une barre d'actions groupées : la case prend la place de la flèche dans util-deck-aside, dans un label.util-checkbox dont la zone atteint var(--touch-target) sans agrandir la tête, et la case cochée pose util-deck-selected sur son deck. Au-dessus d'un groupe, la case de FormUi::checkbox() posée dans un util-label coche tout le groupe, sur la seule largeur de son libellé. Quand le groupe est lui-même un deck, cette case se pose dans util-deck-title, et chaque util-deck-row de son étage prend sa propre case en tête, dans un label.util-checkbox de la même zone.").'</p>';

		$h .= '<div class="util-deck util-deck-2 util-deck-open mb-2">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.new \util\FormUi()->checkbox(NULL, attributes: ['callbackLabel' => fn(string $input) => $input.' '.s("3 livraisons à venir")]).'</div>';
					$h .= '<p>'.s("Du mardi 22 septembre au mardi 6 octobre").'</p>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div class="util-deck-fold">';
				foreach([
					[s("Mardi 22 septembre"), TRUE],
					[s("Mardi 29 septembre"), FALSE],
				] as [$title, $selected]) {
					$h .= '<div class="util-deck-row">';
						$h .= '<label class="util-checkbox">'.new \util\FormUi()->inputCheckbox(NULL, attributes: ['checked' => $selected]).'</label>';
						$h .= '<span class="util-deck-row-name">'.$title.'</span>';
					$h .= '</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-label mb-1">'.new \util\FormUi()->checkbox(NULL, attributes: ['callbackLabel' => fn(string $input) => $input.' '.s("Octobre 2026")]).'</div>';
		$h .= '<div class="util-cards util-cards-3 mb-2">';

			foreach([
				[s("Vendredi 2 octobre"), TRUE],
				[s("Vendredi 9 octobre"), FALSE],
			] as [$title, $selected]) {
				$h .= '<div class="util-deck util-deck-accent-order'.($selected ? ' util-deck-selected' : '').'">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$title.'</div>';
						$h .= '</div>';
						$h .= '<div class="util-deck-aside"><label class="util-checkbox">'.new \util\FormUi()->inputCheckbox(NULL, attributes: ['checked' => $selected]).'</label></div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-band util-deck-band-state">'.s("Commandes ouvertes").'</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-tabs range sous la tête les parties d'un objet déplié, sur un fond neutre quelle que soit la teinte du bloc : util-deck-tabs-folder en fait des intercalaires, et l'onglet ouvert, blanc, se fond dans l'étage qu'il montre. Chaque partie est une a.util-deck-tab de 44px de haut, son nom dans util-deck-tab-name. Ce que la partie compte suit le nom dans un util-counter ; un small sous le nom ne porte que ce qui ne se compte pas, comme un état ou un montant. L'onglet ouvert porte is-selected, comme l'util-deck-fold qu'il montre : les étages qui suivent la barre sans is-selected sont masqués, et basculer d'un onglet à l'autre ne déplace rien au-dessus. Aucun onglet ne dit une étape en cours : ce sont des parties, pas un flux. Une partie qui n'a rien à montrer est une span.util-deck-tab vide, qui garde sa place sans rien dire pour aligner les blocs entre eux ; sous 768px, où cet alignement ne se lit plus, elle s'efface et les onglets présents se partagent la largeur. La barre n'existe que sur un bloc déplié : replié, il ne garde que sa tête.").'</p>';

		$panels = [
			'products' => [s("Produits"), 8, NULL, TRUE],
			'sales' => [s("Commandes"), 12, NULL, TRUE],
			'points' => [s("Modes de livraison"), NULL, s("choix désactivé"), TRUE],
			'summary' => [s("Bilan"), NULL, NULL, FALSE],
		];

		$select = 'const deck = this.closest(".util-deck"); deck.querySelectorAll(":scope > .util-deck-tabs > a, :scope > .util-deck-fold").forEach(node => node.classList.toggle("is-selected", node.dataset.panel === this.dataset.panel));';

		$h .= '<div class="util-deck util-deck-top util-deck-accent-order util-deck-open mb-2">';
			$h .= '<div class="util-deck-head">';
				$h .= '<a class="util-deck-body" '.\main\DesignUi::getDeckToggle().'>';
					$h .= '<div class="util-deck-title">'.s("Mardi 15 septembre").'</div>';
					$h .= '<p>'.s("Grille Particuliers").'</p>';
					$h .= '<div class="util-deck-figures">'.\main\DesignUi::getDeckFigure(s("Ventes"), '12').\main\DesignUi::getDeckFigure(s("Montant"), '348,50 €').'</div>';
				$h .= '</a>';
				$h .= '<div class="util-deck-aside">';
					$h .= '<a class="btn btn-outline-primary">'.\Asset::icon('link-45deg').'<span class="hide-xs-down"> '.s("Lien").'</span></a> ';
					$h .= \util\TextUi::switch([], on: TRUE).' ';
					$h .= \main\DesignUi::getGear('<a href="#design-cards" class="dropdown-item">'.s("Modifier").'</a>');
				$h .= '</div>';
				$h .= '<a class="util-deck-chevron" '.\main\DesignUi::getDeckToggle().'>'.\Asset::icon('chevron-down').'</a>';
			$h .= '</div>';
			$h .= '<div class="util-deck-tabs util-deck-tabs-folder">';
				foreach($panels as $panel => [$name, $counter, $detail, $available]) {
					if($available === FALSE) {
						$h .= '<span class="util-deck-tab"></span>';
						continue;
					}
					$h .= '<a data-panel="'.$panel.'" class="util-deck-tab'.($panel === 'products' ? ' is-selected' : '').'" '.attr('onclick', $select).'>';
						$h .= '<span class="util-deck-tab-name">'.$name;
							if($counter !== NULL) {
								$h .= '<span class="util-counter">'.$counter.'</span>';
							}
						$h .= '</span>';
						if($detail !== NULL) {
							$h .= '<small>'.$detail.'</small>';
						}
					$h .= '</a>';
				}
			$h .= '</div>';
			$h .= '<div data-panel="products" class="util-deck-fold is-selected">';
				$h .= '<div class="util-deck-content">';
					$h .= '<div class="util-overflow-xs util-deck-bleed">';
						$h .= '<table class="tr-even">';
							$h .= '<thead><tr><th>'.s("Produit").'</th><th class="text-end">'.s("Prix").'</th></tr></thead>';
							$h .= '<tbody>';
								$h .= '<tr><td>'.s("Carottes").'</td><td class="text-end">3,20 €</td></tr>';
								$h .= '<tr><td>'.s("Poireaux").'</td><td class="text-end">2,80 €</td></tr>';
							$h .= '</tbody>';
						$h .= '</table>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div data-panel="sales" class="util-deck-fold">';
				$h .= '<div class="util-deck-content">'.s("Ce que l'onglet Commandes montre").'</div>';
			$h .= '</div>';
			$h .= '<div data-panel="points" class="util-deck-fold">';
				$url = 'https://boutique.ouvretaferme.org/les-jardins-de-la-riviere-et-du-coteau/livraison-du-mardi-15-septembre';
				$h .= '<div class="util-deck-content">';
					$h .= '<dl class="util-presentation util-presentation-2">';
						$h .= '<dt>'.s("Lien").'</dt>';
						$h .= '<dd class="util-presentation-fill">';
							$h .= '<a id="design-deck-url" title="'.$url.'">'.$url.'</a>';
							$h .= ' '.\util\TextUi::copy('#design-deck-url', s("Copié !"), s("Copier"), 'btn btn-sm btn-outline-primary');
						$h .= '</dd>';
					$h .= '</dl>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-deck-bleed fait toucher à un tableau les deux bords de l'étage où il est posé, et le bas de l'étage quand il le termine : il remplace la marge du tableau. Dans une util-presentation, un lien long suivi de son bouton Copier se pose dans un dd.util-presentation-fill : sous 576px, la ligne passe sous son libellé, le lien se tronque en ellipse et porte l'adresse entière en title, Copier passe dessous s'il ne tient pas, avec une cible de 44px.").'</p>';

		$h .= '<p class="util-annotation">'.s("Un objet d'un groupe qui se déplie à la place de sa ligne devient une util-deck-slot : sa tête, sa barre et ses étages se posent à plat dans l'étage du groupe, sans bloc dans le bloc. Le slot prend la teinte de l'objet par util-deck-accent-*, et util-deck-open le déplie. Une tête d'une seule ligne, réduite à un util-deck-row-name, se centre.").'</p>';

		$h .= '<div class="util-deck util-deck-open mb-2">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.s("3 livraisons à venir").'</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div class="util-deck-fold">';
				$h .= '<a href="#design-cards" class="util-deck-row"><span class="util-deck-row-name">'.s("Mardi 22 septembre").'</span><span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span></a>';
				$h .= '<div class="util-deck-slot util-deck-accent-info util-deck-open">';
					$h .= '<div class="util-deck-head">';
						$h .= '<a class="util-deck-body" '.\main\DesignUi::getDeckToggle('util-deck-slot').'>';
							$h .= '<div class="util-deck-title">'.s("Mardi 29 septembre").'</div>';
							$h .= '<p>'.s("Grille Particuliers").'</p>';
						$h .= '</a>';
						$h .= '<a class="util-deck-chevron" '.\main\DesignUi::getDeckToggle('util-deck-slot').'>'.\Asset::icon('chevron-down').'</a>';
					$h .= '</div>';
					$h .= '<div class="util-deck-tabs util-deck-tabs-folder">';
						$h .= '<a class="util-deck-tab is-selected"><span class="util-deck-tab-name">'.s("Produits").'<span class="util-counter">5</span></span></a>';
						$h .= '<a class="util-deck-tab"><span class="util-deck-tab-name">'.s("Commandes").'<span class="util-counter">0</span></span></a>';
						$h .= '<span class="util-deck-tab"></span>';
					$h .= '</div>';
					$h .= '<div class="util-deck-fold is-selected">';
						$h .= '<div class="util-deck-content">'.s("Ce que l'onglet Produits montre").'</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<a href="#design-cards" class="util-deck-row"><span class="util-deck-row-name">'.s("Mardi 6 octobre").'</span><span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span></a>';
				$h .= '<div class="util-deck-slot">';
					$h .= '<a class="util-deck-head" '.\main\DesignUi::getDeckToggle('util-deck-slot').'>';
						$h .= '<div class="util-deck-body">';
							$h .= '<span class="util-deck-row-name">'.s("1 question hors du logiciel").'</span>';
						$h .= '</div>';
						$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
					$h .= '</a>';
					$h .= '<div class="util-deck-fold">';
						$h .= '<a href="#design-cards" class="util-deck-row"><span class="util-deck-row-name">'.s("Les tableaux des emprunts sont classés au dossier").'</span><span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span></a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-3 util-cards-start">';

			foreach([
				['util-deck-accent-success', s("Ce qui est fait")],
				['util-deck-accent-warning', s("Ce qui alerte")],
				['util-deck-accent-danger', s("Ce qui bloque")],
				['util-deck-accent-order', s("Ce qui se commande")],
				['util-deck-accent-info', s("Ce qui est à venir")],
				['util-deck-accent-todo', s("Ce qui reste à faire")],
			] as [$class, $label]) {

				$h .= '<div class="util-deck '.$class.'">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$label.'</div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-band util-deck-band-state">'.$class.'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function summarize(): string {

		$h = '<p class="util-annotation">'.s("ul.util-summarize aligne des chiffres sur une ligne, avec le même habillage que util-card. Contrairement à util-cards, la largeur suit le contenu, celle d'un bandeau et non d'une grille.").'</p>';

		$h .= '<ul class="util-summarize">';

			$h .= '<li>';
				$h .= '<a href="#">';
					$h .= '<h5>li > a</h5>';
					$h .= '78 286 €';
				$h .= '</a>';
			$h .= '</li>';

			$h .= '<li class="selected">';
				$h .= '<a href="#">';
					$h .= '<h5>li.selected > a</h5>';
					$h .= '160 880 €';
				$h .= '</a>';
			$h .= '</li>';

			$h .= '<li>';
				$h .= '<div>';
					$h .= '<h5>li > div</h5>';
					$h .= '116 146 €';
					$h .= '<div class="util-summarize-muted">util-summarize-muted</div>';
				$h .= '</div>';
			$h .= '</li>';

		$h .= '</ul>';

		$h .= '<p class="util-annotation">'.s("util-summarize-overflow sur le ul fait défiler le bandeau horizontalement au lieu de le passer à la ligne, util-summarize-unstyled sur un lien lui retire l'habillage. Dans un panel-dialog, l'ombre disparaît et seule la bordure porte l'état.").'</p>';

		return $h;

	}

	public function marks(): string {

		$h = '<p class="util-annotation">'.s("Une marque dit où en est une chose, un bouton appelle une action : un état ne se dessine jamais en bouton plein. Deux classes suffisent : le dessin (badge) et la teinte (badge-danger, badge-confirmed...). Le poids courant, dilué, s'obtient sans rien écrire ; il ne se nomme que pour en changer, par badge-solid ou badge-outline. Sans classe de teinte, la marque est grise et le contour suit la couleur du texte.").'</p>';

		$h .= '<h4>'.s("Les trois poids").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le poids courant n'a pas de nom : une marque sans classe de poids est diluée, parce qu'un aplat plein est trop lourd dans un tableau, où la marque est l'exception et non le sujet. badge-solid est réservé à ce qui doit crier. badge-outline dit ce qui n'est pas encore atteint, ou un classement plutôt qu'un état.").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<span class="badge badge-danger">badge</span>';
			$h .= '<span class="badge badge-solid badge-danger">badge-solid</span>';
			$h .= '<span class="badge badge-outline badge-danger">badge-outline</span>';
		$h .= '</div>';

		$h .= '<h4 style="margin-top: 1.5rem;">'.s("L'échelle des teintes").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une classe de teinte ne fait qu'une chose : poser le jeton. Le même nom vaut pour les trois poids, et les teintes métier remplacent les couleurs que les feuilles de package écrivaient chacune de leur côté.").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">';
			foreach(['neutral', 'info', 'primary', 'success', 'warning', 'danger'] as $tint) {
				$h .= '<span class="badge badge-'.$tint.'">badge-'.$tint.'</span>';
			}
		$h .= '</div>';
		$h .= '<div class="util-block mt-1" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">';
			foreach(['draft', 'confirmed', 'selling', 'prepared', 'delivered', 'basket', 'canceled', 'expired', 'accounting', 'production', 'greenhouse', 'harvest', 'pro', 'private'] as $tint) {
				$h .= '<span class="badge badge-'.$tint.'">badge-'.$tint.'</span>';
			}
		$h .= '</div>';

		$h .= '<h4 style="margin-top: 1.5rem;">'.s("Les trois rôles").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un état se lit, se change, ou ne se change plus. Le même dessin sert les trois, et c'est le signe qui change : badge-dot pour l'état qu'on lit, le chevron de dropdown-toggle pour celui qui ouvre un menu, le cadenas et la raison dans le title pour celui qui ne peut plus bouger. Le point et le chevron ne se cumulent pas, l'un dit « je suis un état », l'autre « je suis un état qui s'ouvre ».").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<span class="btn btn-badge btn-xs btn-readonly badge badge-confirmed badge-dot">'.s("Confirmé").'</span>';
			$h .= '<a data-dropdown="bottom-start" class="btn btn-badge btn-xs badge badge-confirmed dropdown-toggle">'.s("Confirmé").'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= '<a class="dropdown-item">'.s("Livré").'</a>';
				$h .= '<a class="dropdown-item">'.s("Annulé").'</a>';
			$h .= '</div>';
			$h .= '<span class="btn btn-badge btn-xs btn-readonly badge badge-confirmed" title="'.s("Il n'est pas possible de modifier une vente facturée.").'">'.s("Confirmé").'  '.\Asset::icon('lock-fill').'</span>';
		$h .= '</div>';

		$h .= '<h4 style="margin-top: 1.5rem;">'.s("Une marque qui est aussi un bouton").'</h4>';
		$h .= '<p class="util-annotation">'.s("btn btn-badge donne à une marque la géométrie et le comportement d'un bouton : les espacements, les tailles btn-xs à btn-xl, le survol, le curseur, btn-readonly pour ce qui ne se clique pas. La teinte et le poids restent ceux de la marque, et le poids dilué gagne un liseré, qui ne se pose que là : le fond seul ne dirait pas qu'on peut cliquer. C'est la forme d'un état qu'on ouvre, et celle d'une référence qu'on suit.").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			foreach(['btn-xs', 'btn-sm', '', 'btn-lg'] as $size) {
				$h .= '<a href="#design-marks" class="btn btn-badge '.$size.' badge badge-info">'.($size ?: 'btn-badge').'</a>';
			}
		$h .= '</div>';

		$h .= '<h4 style="margin-top: 1.5rem;">'.s("Une référence").'</h4>';
		$h .= '<p class="util-annotation">'.s("badge-reference est la marque d'un code : un numéro de pièce, une référence de rapprochement. Elle se lit caractère par caractère, donc en chasse fixe, et garde l'arrondi court de la marque même portée par un bouton, dont l'arrondi long ferait lire le code comme une action.").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<span class="badge badge-reference">FA-2026-0154</span>';
			$h .= '<a href="#design-marks" class="btn btn-badge btn-xs badge badge-reference">'.\Asset::icon('bank').' VIR-880214</a>';
			$h .= '<span class="badge badge-outline badge-reference">FA-2026-0155</span>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un temps passé sur une intervention").'</h4>';
		$h .= '<p class="util-annotation">'.s("Marque le temps passé sur une intervention, partout où il paraît dans le planning, pour qu'il ne se confonde jamais avec un temps déclaré, qui reste du texte. La marque porte son contour, en gras et d'une seule taille, y compris sur le fond d'une carte faite où un fond dilué de la même teinte disparaîtrait. Un temps non saisi reste un « ? » neutre, sans marque.").'</p>';
		$h .= '<div class="util-block flex-align-center flex-wrap">';
			$h .= '<span class="badge badge-time-spent">2h15</span>';
			$h .= '<span class="badge badge-time-spent">112h45</span>';
			$h .= '<span>'.s("<b>38h00</b> cette semaine, dont {value} sur les interventions", ['value' => '<span class="badge badge-time-spent">14h20</span>']).'</span>';
		$h .= '</div>';
		$h .= '<div class="util-block design-done-stage">';
			$h .= '<span>'.s("Sur une carte faite").'</span>';
			$h .= '<span class="badge badge-time-spent">7h30</span>';
			$h .= '<span>?</span>';
		$h .= '</div>';

		$h .= '<h4 style="margin-top: 1.5rem;">'.s("Diluer et aligner").'</h4>';
		$h .= '<p class="util-annotation">'.s("badge-off efface la marque à moitié, à teinte égale : ce que l'état n'a pas encore atteint face à ce qui vaut maintenant. badges aligne plusieurs marques, badges-stacked les empile en les calant à droite, sous un prix par exemple.").'</p>';
		$h .= '<div class="util-block">';
			$h .= '<div class="badges">';
				$h .= '<span class="badge badge-primary">'.s("Atteint").'</span>';
				$h .= '<span class="badge badge-primary badge-off">'.s("À venir").'</span>';
				$h .= '<span class="badge badge-success badge-off">'.s("À venir, autre teinte").'</span>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function counters(): string {

		$h = '<p class="util-annotation">'.s("util-counter compte, il ne qualifie pas : un nombre, jamais un mot. Le contour par défaut ; util-counter-solid quand le compteur est posé sur un fond qui bouge, un onglet choisi ou une entrée de menu survolée, où le contour seul disparaît ; util-counter-danger, un aplat rouge, quand ce qu'il compte presse, comme les entretiens en retard d'un matériel sur son onglet, et pour rien d'autre. Dans un bouton ou une action de la barre de navigation (nav-item), le compteur au contour dérive son contour et son fond de la couleur du texte, les deux aplats gardent leur teinte, et le bouton garde sa hauteur. Un compteur vide ne s'affiche pas.").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<span class="util-counter">42</span>';
			$h .= '<span class="util-counter util-counter-solid">7</span>';
			$h .= '<span class="util-counter util-counter-danger">2</span>';
			$h .= '<a href="#design-marks" class="btn btn-primary">'.s("Filtres").' <span class="util-counter">3</span></a>';
			$h .= '<a href="#design-marks" class="btn btn-outline-primary">'.s("Relances").' <span class="util-counter">12</span></a>';
		$h .= '</div>';
		$h .= '<div class="util-block design-dark-stage">';
			$h .= '<span>'.s("Sur une bande sombre").'</span>';
			$h .= '<span class="util-counter">42</span>';
			$h .= '<span class="util-counter util-counter-solid">7</span>';
			$h .= '<span class="util-counter util-counter-danger">2</span>';
		$h .= '</div>';
		$h .= '<p class="util-annotation">'.s("Les deux aplats portent un anneau, qui les détache du fond qui les porte sans rien demander à l'écran : il est invisible sur blanc et apparaît sur une bande sombre. Sans lui, l'aplat rouge rendait 2,63 de contraste sur une bande --primary, et le solide 1,0, sa teinte étant celle de la bande, donc le compteur y perdait toute forme et se lisait comme un nombre nu.").'</p>';

		return $h;

	}

	public function stamps(): string {

		$h = '<p class="util-annotation">'.s("util-stamp est un cachet posé en marge d'une ligne, légèrement incliné : il signale un fait sur la ligne entière, là où un badge qualifie un mot. Sa teinte vient de --util-stamp, la couleur de l'interface par défaut ; util-stamp-new la prend dans la commercialisation pour ce qui n'existait pas.").'</p>';
		$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
			$h .= '<span class="util-stamp">'.s("Signé").'</span>';
			$h .= '<span class="util-stamp util-stamp-new">'.s("Nouveau").'</span>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Le titre tamponné").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-title-stamp se pose sur un titre et le frappe comme un tampon, à bordure double. Là où util-stamp est une marque posée à côté d'une ligne, util-title-stamp est le titre lui-même, celui d'une colonne de choses à faire par exemple. Sa teinte vient de --util-title-stamp, la lavande par défaut, assombrie à l'emploi pour se lire sur blanc.").'</p>';
		$h .= '<div class="util-block">';
			$h .= '<h2 class="util-title-stamp">'.s("À faire").'</h2>';
		$h .= '</div>';

		return $h;

	}

	public function dots(): string {

		$h = '<p class="util-annotation">'.s("util-dot est le rond de la couleur qu'un objet porte en base, un cahier de caisse par exemple, posé devant son nom. Sa couleur arrive en style en ligne, par --util-dot. Il prend la taille du texte qu'il précède, et reste sur la première ligne quand le nom s'enroule, comme ici dans une tuile. Il accompagne toujours un nom et ne dit jamais un état, qui est une marque.").'</p>';
		$h .= '<div class="util-figures util-figures-3">';

			foreach([
				['#2E7D32', s("Espèces"), '830,65 €'],
				['#8E44AD', s("Titres-restaurant et chèques vacances encaissés au marché du samedi"), '1 240,00 €'],
				['#C0392B', s("Chèque"), '123,00 €'],
			] as [$color, $name, $value]) {

				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-label util-figure-label-top"><span class="util-dot" style="--util-dot: '.$color.'"></span>'.$name.'</div>';
					$h .= '<div class="util-figure-value">'.$value.'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function texts(): string {

		$h = '<p class="util-annotation">'.s("Un texte saisi par l'utilisateur s'écrit par \\util\\TextUi::multiline(), qui l'échappe avant de garder ses retours à la ligne. Une quantité assemblée par le code, son nombre et son unité, passe par \\util\\TextUi::nonBreaking() pour ne pas se couper en fin de ligne. Une cellule sans objet sur sa ligne s'écrit par \\util\\TextUi::none(), et une valeur qui manque là où elle a un sens par util-empty.").'</p>';

		$h .= '<table class="tr-even">';
			$h .= '<thead><tr><th>'.s("Appel").'</th><th>'.s("Rendu").'</th></tr></thead>';
			$h .= '<tbody>';
				$h .= '<tr><td><code>multiline()</code></td><td>'.\util\TextUi::multiline(s("Livrer par l'entrée de la cour.\nSonner deux fois.")).'</td></tr>';
				$h .= '<tr><td><code>nonBreaking()</code></td><td>'.\util\TextUi::nonBreaking(s("{value} kg", ['value' => \util\TextUi::number(12.5, 1)])).'</td></tr>';
				$h .= '<tr><td><code>none()</code></td><td>'.\util\TextUi::none().'</td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function badges(): string {

		$h = '<p class="util-annotation">'.s("\\main\\DesignUi::getSplitBadge() rend badge-split, qui accole une marque pleine et un badge-split-detail, dont la teinte est reprise de la classe color-* du conteneur.").'</p>';

		$h .= '<div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">';

			foreach(['production', 'commercialisation', 'accounting', 'settings'] as $color) {
				$h .= \main\DesignUi::getSplitBadge($color, s("Complément"), $color);
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation mt-1">'.s("La marque diluée (solid à FALSE) pèse moins que le texte qu'elle accompagne, sur une ligne de liste par exemple. Sa teinte doit exister en badge-*.").'</p>';

		$h .= '<div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">';

			foreach(['production', 'commercialisation', 'accounting'] as $color) {
				$h .= \main\DesignUi::getSplitBadge($color, s("Complément"), $color, solid: FALSE);
			}

		$h .= '</div>';

		return $h;

	}

	public function presentations(): string {

		$h = '<h4>'.s("Libellés et valeurs").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-presentation range des paires libellé-valeur dans une grille, sur un dl : le dt porte le libellé, le dd la valeur. Les libellés d'une même colonne s'alignent sur le plus long, donc les valeurs partent toutes du même bord, ce qu'une suite de paragraphes ne sait pas faire. Sous 576 px la grille revient à deux colonnes, libellé puis valeur sur le même rang.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le nombre de paires par rang se choisit au-dessus de 576 px : util-presentation-1 en met une, util-presentation-2 en met deux, util-presentation-3 en met deux puis trois à partir de 992 px. util-presentation-max-content serre les rangs et laisse les valeurs à leur largeur naturelle ; util-presentation-auto, avec -1, retient la valeur au lieu de l'étirer.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une valeur qui a besoin du rang entier se pose dans un dd.util-presentation-fill. Au-dessus de 576 px il prend le rang quoi qu'il porte ; en dessous, il ne passe sous son libellé que s'il porte un bouton, le cas pour lequel il a été écrit, une adresse longue suivie de son bouton Copier. Un dd.util-presentation-fill sans bouton reste donc dans la colonne des valeurs sur un téléphone, et un texte long y pousse la colonne des libellés : pour un texte, écrire une paire ordinaire.").'</p>';

		$h .= '<dl class="util-presentation util-presentation-2 mb-2">';
			$h .= '<dt>'.s("Structure").'</dt>';
			$h .= '<dd>'.s("Les Jardins du Pré").'</dd>';
			$h .= '<dt>'.s("Commune").'</dt>';
			$h .= '<dd>'.s("Saint-Marcellin").'</dd>';
			$h .= '<dt>'.s("Surface cultivée").'</dt>';
			$h .= '<dd>'.s("1,8 ha").'</dd>';
			$h .= '<dt>'.s("Première récolte").'</dt>';
			$h .= '<dd>'.s("12 avril 2026").'</dd>';
			$h .= '<dt>'.s("Lien public").'</dt>';
			$h .= '<dd class="util-presentation-fill"><a href="#">https://ouvretaferme.org/boutique/les-jardins-du-pre/commande-du-12-avril</a> <a href="#" class="btn btn-sm btn-outline-primary">'.\Asset::icon('clipboard').' '.s("Copier").'</a></dd>';
		$h .= '</dl>';

		$h .= '<dl class="util-presentation util-presentation-1 util-presentation-max-content">';
			$h .= '<dt>'.s("Total").'</dt>';
			$h .= '<dd>'.s("1 240,50 €").'</dd>';
			$h .= '<dt>'.s("Reste à payer").'</dt>';
			$h .= '<dd>'.s("310,00 €").'</dd>';
		$h .= '</dl>';

		$h .= '<h4 class="mt-2">'.s("La fiche d'un objet").'</h4>';
		$h .= '<p class="util-annotation">'.s("Les caractéristiques d'un objet, en tête de sa page, se posent dans un util-block stick-xs : le cadre blanc les détache du fond, et sous 576 px il perd ses bords latéraux pour occuper la largeur de l'écran.").'</p>';

		$h .= '<div class="util-block stick-xs">';
			$h .= '<dl class="util-presentation util-presentation-2">';
				$h .= '<dt>'.s("Immobilisation").'</dt>';
				$h .= '<dd>'.s("Serre tunnel 8 × 30 m").'</dd>';
				$h .= '<dt>'.s("Mise en service").'</dt>';
				$h .= '<dd>'.s("1er mars 2024").'</dd>';
				$h .= '<dt>'.s("Valeur").'</dt>';
				$h .= '<dd>'.s("12 400,00 €").'</dd>';
				$h .= '<dt>'.s("Durée").'</dt>';
				$h .= '<dd>'.s("10 ans").'</dd>';
			$h .= '</dl>';
		$h .= '</div>';

		return $h;

	}

	public function ring(): string {

		$h = '<p class="util-annotation">'.s("util-ring montre la part de chaque segment dans un tout, avant qu'on lise les chiffres. Le dégradé conique se compose dans \\main\\DesignUi::getRing(), qui reçoit des couples [pourcentage, nom de token] : un conic-gradient ne sait pas additionner ses propres segments, chaque part doit recevoir son angle de début et de fin.").'</p>';
		$h .= '<p class="util-annotation">'.s("Il ne donne jamais les valeurs : il s'accompagne de badges scindés ou d'une légende chiffrée. Seul, un anneau se lit à l'estime et se trompe sur les petites parts.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le trou central en fait un anneau et non un camembert. Il est peint avec la couleur de fond de la page : sur un aplat coloré, il faut la redéfinir.").'</p>';

		$h .= '<div style="display: flex; gap: 2rem; flex-wrap: wrap; align-items: center;">';

			$h .= \main\DesignUi::getRing([[60, 'production'], [25, 'commercialisation'], [15, 'accounting']]);
			$h .= \main\DesignUi::getRing([[100, 'production']], '4rem');
			$h .= \main\DesignUi::getRing([[50, 'settings'], [50, 'accounting']], '4rem');

		$h .= '</div>';

		return $h;

	}

	public function ratings(): string {

		$h = '<p class="util-annotation">'.s("util-rating lit une note sur cinq : les étoiles de util-rating-stars, éteintes en util-rating-off, et avec le nombre d'avis la moyenne qui les suit. Elle se compose dans \\main\\DesignUi::getRating(). Les étoiles gardent la couleur du texte : une mauvaise note n'est pas une alerte.").'</p>';
		$h .= '<p class="util-annotation">'.s("Sur une carte, une seule étoile suffit (compact: TRUE au helper). En tête d'une liste d'avis, util-rating-lg met la moyenne en grand dans util-rating-value, devant les étoiles.").'</p>';

		$h .= '<div class="flex-align-center flex-wrap mb-2">';
			$h .= \main\DesignUi::getRating(4.3, 12, compact: TRUE);
			$h .= \main\DesignUi::getRating(4.25, 4);
			$h .= \main\DesignUi::getRating(2.0);
			$h .= \main\DesignUi::getRating(3.6, large: TRUE);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un avis, des milliers d'avis, aucune étoile").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un seul avis se dit au singulier, un grand nombre se groupe par milliers, une note nulle éteint les cinq étoiles et une note parfaite les allume toutes.").'</p>';

		$h .= '<div class="flex-align-center flex-wrap mb-2">';
			$h .= \main\DesignUi::getRating(5.0, 1);
			$h .= \main\DesignUi::getRating(4.7, 1284);
			$h .= \main\DesignUi::getRating(0.0, 3);
			$h .= \main\DesignUi::getRating(4.7, 1284, compact: TRUE);
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Saisir une note").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-rating-input saisit la note par cinq radios, sans JavaScript, montées par \\main\\DesignUi::getRatingField() depuis le describer du champ. Le survol allume les étoiles jusqu'à celle qu'on pointe.").'</p>';

		$form = new \util\FormUi();

		$h .= \main\DesignUi::getRatingField($form, 'designRating', 4);

		$h .= '<h4 class="mt-2">'.s("La répartition des avis par note").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-rating-distribution répartit les avis par note, une ligne par note et trois enfants par ligne, qui se suivent directement : la note et son étoile, une barre util-progress, le nombre d'avis, aligné à droite.").'</p>';

		$h .= '<div class="util-rating-distribution">';
			foreach([5 => 8, 4 => 3, 3 => 1, 2 => 0, 1 => 0] as $rating => $count) {
				$h .= '<span class="util-annotation">'.$rating.' '.\Asset::icon('star-fill').'</span>';
				$h .= '<div class="util-progress">';
					$h .= '<div class="util-progress-part bg-secondary" style="width: '.round($count / 12 * 100, 1).'%"></div>';
				$h .= '</div>';
				$h .= '<span class="util-annotation">'.$count.'</span>';
			}
		$h .= '</div>';

		return $h;

	}

	public function blocksCta(): string {

		$h = '<p class="util-annotation">'.s("util-block-cta et util-block-empty partagent la même forme : le titre, la phrase et, s'il y en a un, le geste dans le même bloc. Un bouton posé sous un texte qui le motive, séparé de lui, laisse au lecteur le soin de relier deux choses que rien ne relie : c'est le défaut que ces composants existent pour éviter.").'</p>';
		$h .= '<p class="util-annotation">'.s("Ils se réservent aux écrans qui n'ont rien à montrer. Sur un écran plein, un aplat de cette taille prend la place du contenu et se lit comme une réclame.").'</p>';
		$h .= '<p class="util-annotation">'.s("La couleur distingue le message : le vert amande de util-block-cta annonce un geste à faire, le gris-bleu de util-block-empty dit seulement qu'il n'y a rien ici, même quand ce constat s'accompagne malgré tout d'un bouton.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le geste s'y pose en btn dans util-buttons, dont ces deux blocs centrent les boutons et les passent à la ligne quand la place manque. Les grands choix illustrés de util-button n'y entrent pas.").'</p>';
		$h .= '<p class="util-annotation">'.s("Pas de séparation au pinceau : elle ferme une bande pleine largeur contre le fond de page, et n'a rien à découper au milieu du flux.").'</p>';

		$h .= \main\DesignUi::getBlockEmpty(s("Aucun prix personnalisé pour ce produit"), s("Proposez un prix différent à un client ou à un groupe de clients pour ce produit."), '<a href="#" class="btn btn-primary">'.s("Ajouter un prix").'</a>');

		$h .= '<h4 class="mt-2">'.s("Sans phrase").'</h4>';
		$h .= '<p class="util-annotation">'.s("La phrase est facultative : sans elle, le composant écarte lui-même le geste du titre.").'</p>';

		$h .= \main\DesignUi::getBlockEmpty(s("Aucune vente"), buttons: '<a href="#" class="btn btn-primary">'.s("Créer une vente").'</a>');

		$h .= '<h4 class="mt-2">'.s("Sans geste").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le bouton aussi est facultatif : sans lui, la phrase termine le bloc et ne garde aucune marge en dessous.").'</p>';

		$h .= \main\DesignUi::getBlockEmpty(s("Aucune écriture sur cet exercice"), s("Les analyses se calculent à partir des écritures de l'exercice."));

		$h .= '<h4 class="mt-2">util-block-cta</h4>';
		$h .= \main\DesignUi::getBlockEmpty(
			s("Ce groupe n'a pas encore de structures"),
			s("Invitez les personnes qui le composeront : ce sont elles qui y apporteront leurs structures et leurs données."),
			'<a href="#" class="btn btn-primary">'.s("Inviter des membres").'</a><a href="#" class="btn btn-outline-primary">'.s("Partager une structure").'</a>',
			cta: TRUE
		);

		return $h;

	}

	public function weigh(): string {

		$h = '<p class="util-annotation">'.s("main\\DesignUi::getWeigh() rend util-weigh, qui pèse un geste avant qu'on le fasse. Il porte un titre qui nomme le geste, une phrase, ce qu'il change conséquence par conséquence, ce qui reste, s'il s'annule, et le bouton du geste quand rien ne s'intercale entre eux. Il ne porte jamais de champ.").'</p>';

		$h .= '<p class="util-annotation">'.s("Il se pose quand le geste a des conséquences qu'il faut avoir lues avant de cliquer. Sinon un data-confirm suffit. Pour une suppression, une seule question décide, celle du docblock de main\\DesignUi::getDeletePanel() : quelqu'un d'autre que celui qui clique perd-il quelque chose ? Si non, un data-confirm suffit, quelle que soit l'ampleur technique du geste ; si oui, getDeletePanel() rend le panneau au registre danger. Ce qui part et ce qui reste sont chiffrés partout où c'est possible, jamais approximés : on chiffre ce que la page a déjà sous la main, sinon on nomme au pluriel indéfini.").'</p>';

		$h .= '<p class="util-annotation">'.s("Trois registres. util-weigh, neutre, prend la couleur du domaine (--secondary) et explique un geste. util-weigh-warning porte un geste qui se reprend, util-weigh-danger un geste qui détruit une saisie ou coupe quelqu'un d'un accès. Le rouge garde son sens d'alerte et ne se pose que sur une perte.").'</p>';

		$h .= '<p class="util-annotation">'.s("Le titre est le geste suivi de son objet, avec le verbe du bouton : « Supprimer le groupement du Val » au-dessus d'un bouton « Supprimer le groupement ». Le lecteur sait ce qu'il pèse avant de lire la première ligne.").'</p>';

		$h .= '<p class="util-annotation">'.s("Une conséquence est une phrase entière, sujet puis verbe, et rien d'autre. Elle est au futur quand c'est le geste qui la produit : « Les 3 commandes passées seront figées », et non « sont figées », qui se lirait comme ce qui est déjà vrai. Jamais « sujet : ce qui lui arrive », jamais de ligne en petit qui dirait ce qui était vrai jusque-là.").'</p>';

		$h .= '<p class="util-annotation">'.s("Passent en gras le sujet de la phrase et les noms propres, et les nombres quand deux noms peuvent se confondre, comme les deux fiches d'une fusion de clients.").'</p>';

		$h .= '<p class="util-annotation">'.s("Une phrase de conséquence ou de reste nomme son sujet. Elle n'ouvre jamais sur un possessif ou un pronom dont le sujet n'est pas dans la même phrase : « Camille Martin garde ses structures et leurs données », jamais « Ses structures et leurs données restent les siennes ».").'</p>';

		$h .= '<p class="util-annotation">'.s("Une icône propre à chaque conséquence, un cadenas pour des commandes figées, un panier pour des produits, sur la teinte du registre. C'est elle qui porte la couleur et fait reconnaître la ligne avant de la lire, le texte reste en couleur courante.").'</p>';

		$h .= '<p class="util-annotation">'.s("Une conséquence qui demande d'être lue avant les autres, parce qu'elle s'écarte de ce que l'utilisateur attend, porte 'alert' => TRUE : sa ligne prend util-weigh-alert, et son carré passe à la teinte d'alerte, quel que soit le registre du bloc. Le texte ne change pas. Premier emploi : la TVA reprise du ticket de balance, qui diffère de celle que la vente calculerait.").'</p>';

		$h .= '<p class="util-annotation">'.s("Une conséquence qui ne vaut que pour l'un des choix du formulaire porte ses 'attributes', posés sur sa ligne, en général une classe hide et un data-* que le JS du formulaire lit pour la montrer ou la masquer au changement de choix, sans attendre le serveur. La ligne reste à sa place dans le bloc, on ne recompose pas la liste. Le premier emploi est la fiche des titres reçus du panneau de sortie d'une immobilisation, qui n'existe que pour un apport en société.").'</p>';

		$h .= '<p class="util-annotation">'.s("Ce qui reste, \$rest, dit ce que le geste ne change pas, et reste donc au présent : « La structure Ferme des Quatre Saisons ne change pas ». Ses lignes suivent les conséquences dans la même liste, en util-weigh-same : la coche check-lg sur le même carré, en fond --background et texte --text-intermediate sur les trois registres, rouge compris. Le carré neutre sépare ce qui ne bouge pas de ce que le geste produit, sans ajouter un second élément au composant.").'</p>';

		$h .= '<p class="util-annotation">'.s("\$reversible rend la pastille Annulable ou Définitif. Il vaut TRUE quand un autre geste du produit défait celui-ci, et ne dépend pas de ce que le geste écrit ou non en base. La pastille ne double pas le registre : rendre une boutique collective est un geste important qu'on annule, supprimer une photo est bénin et définitif. Elle se pose en haut à droite du bloc, face au titre, et face au disque sous 449 px. NULL ne rend aucune pastille, pour un geste qu'elle ne décrirait pas : un paiement ne se défait par aucun geste du produit, et ne détruit rien pour autant. Premier emploi : la souscription de la page d'abonnement.").'</p>';

		$h .= '<p class="util-annotation">'.s("Les champs qui décident du geste suivent le bloc dans le formulaire, en \$form->group() ou dynamicGroup() ordinaires, et le détail qui justifie la décision, comme le tableau des prix d'une famille de produits, passe aussi sous le bloc. Ce qui désigne l'objet du geste, comme la vente d'origine d'une copie, reste au-dessus.").'</p>';

		$h .= '<p class="util-annotation">'.s("\$action pose le bouton du geste dans le bloc, sous les lignes et dans la colonne du texte, avec ses voisins éventuels, comme Annuler ou un second geste. C'est sa place chaque fois que rien ne s'intercale entre le bloc et lui : un bouton posé sous le bloc, séparé de lui, laisse au lecteur le soin de relier deux choses que rien ne relie.").'</p>';
		$h .= '<p class="util-annotation">'.s("Quand des champs décident du geste, le bouton les suit, dans le corps du panneau, et ne remonte pas dans le bloc au-dessus d'eux. Il ne va au pied, dans le footer de \\Panel, que si le panneau est long d'habitude.").'</p>';

		$h .= '<p class="util-annotation">'.s("Aucun texte coloré sous 4,5:1. L'orange du produit n'y arrive pas sur blanc, donc l'icône et la pastille prennent --weigh-ink, la couleur du registre assombrie, et un fond plein sous du blanc prend --weigh-solid.").'</p>';

		$h .= '<p class="util-annotation">'.s("Pas de bloc quand il n'y a rien à peser : le bouton reste seul, sans bloc vide ni phrase de remplissage.").'</p>';

		$h .= '<div style="max-width: 34rem;">';

			$h .= \main\DesignUi::getWeigh(
				'info',
				'people-fill',
				s("Rendre la boutique Vente du vendredi collective"),
				s("Vos clients commandent à plusieurs producteurs en une fois."),
				[
					['icon' => 'lock', 'text' => s("<b>Les 3 commandes passées</b> seront figées, et vous les livrerez comme avant.")],
					['icon' => 'basket', 'text' => s("<b>La boutique</b> proposera vos produits et ceux des producteurs accueillis.")]
				],
				[s("Adresse, clients, dates et catalogue ne changent pas.")],
				TRUE
			);

			$h .= '<br/>';

			$h .= \main\DesignUi::getWeigh(
				'warning',
				'box-arrow-in-left',
				s("Reprendre la boutique Vente du vendredi"),
				s("Les producteurs accueillis quittent la boutique."),
				[
					['icon' => 'person-dash', 'text' => s("<b>Les 2 producteurs accueillis</b> seront retirés avec leurs produits.")],
					['icon' => 'truck', 'text' => s("<b>Les 3 livraisons à venir</b> seront vidées de leurs produits, et à regarnir.")]
				],
				[s("Vos produits, clients et dates ne changent pas.")],
				TRUE
			);

			$h .= '<br/>';

			$h .= \main\DesignUi::getWeigh(
				'danger',
				'trash',
				s("Supprimer le groupement du Val"),
				s("Le groupement disparaît pour ses 14 structures."),
				[
					['icon' => 'people', 'text' => s("<b>Les 14 structures membres</b> seront retirées, et le groupement quittera leur menu.")],
					['icon' => 'file-earmark-x', 'text' => s("<b>Les pages, promotions et documents</b> en ligne seront supprimés.")]
				],
				[s("Chaque structure garde ses données et son compte.")],
				FALSE,
				'<a class="btn btn-danger">'.s("Supprimer le groupement").'</a><a class="btn btn-outline-secondary">'.s("Annuler").'</a>'
			);

			$h .= '<br/>';

			$h .= \main\DesignUi::getWeigh(
				'info',
				'credit-card',
				s("Souscrire à l'offre Gestion"),
				s("L'abonnement est souscrit pour un an dès que le paiement est confirmé."),
				[
					['icon' => 'unlock', 'text' => s("Vous aurez accès aux fonctionnalités de <b>Gestion</b> du 15/11/2026 au 14/11/2027.")],
					['icon' => 'receipt', 'text' => s("Dès que votre paiement sera validé, vous recevrez une facture à l'adresse compta@example.org.")]
				],
				[],
				NULL,
				'<a class="btn btn-secondary">'.s("Payer 240 €").'</a>'
			);

			$h .= '<br/>';

			$h .= \main\DesignUi::getWeigh(
				'info',
				'lock',
				s("Importer la vente du 03/10/2026 à Marché Tence"),
				NULL,
				[
					['icon' => 'basket', 'text' => s("<b>La vente du 03/10/2026</b> à Marché Tence sera créée, livrée et payée. Elle ne pourra plus être modifiée ni supprimée.")],
					['icon' => 'exclamation-triangle', 'text' => s("<b>La TVA de la vente</b> sera celle imprimée sur le ticket, 82,40 €. Calculée à partir de vos taux, elle serait de 82,42 €."), 'alert' => TRUE]
				],
				[],
				FALSE
			);
			$h .= '<br/>';

			$h .= \main\DesignUi::getWeigh(
				'info',
				'box-arrow-right',
				s("Enregistrer la sortie de l'immobilisation Tracteur"),
				NULL,
				[
					['icon' => 'box-arrow-right', 'text' => s("<b>L'immobilisation Tracteur</b> sortira du bilan, et les écritures de cession seront enregistrées immédiatement.")],
					['icon' => 'box-arrow-in-right', 'text' => s("<b>Les titres reçus</b> entreront à l'actif sur une nouvelle fiche, non amortie, pour la valeur d'apport."), 'attributes' => ['data-dispose-reasons' => 'contribution']]
				],
				[],
				FALSE
			);
			$h .= '<br/>';

			$form = new \util\FormUi();

			$h .= \main\DesignUi::getWeigh(
				'info',
				'copy',
				s("Dupliquer le catalogue Marché du samedi"),
				NULL,
				[
					['icon' => 'journal-plus', 'text' => s("<b>Un nouveau catalogue</b> sera créé avec les 24 produits et leurs prix.")]
				],
				[s("Le catalogue Marché du samedi ne change pas.")],
				TRUE
			);

			$h .= $form->group(s("Nom de la copie"), $form->text('name', s("Marché du samedi (copie)")));
			$h .= '<a class="btn btn-primary">'.s("Dupliquer le catalogue").'</a>';

		$h .= '</div>';

		return $h;

	}

	public function seeds(): string {

		$h = '';

		if(\structure\StructureSetting::hasSubscriptionPackage()) {

			$membershipGroup = [s("Association {siteName}"), [s("Adhésion de 20 € comprise")], FALSE];

			$features = function(array $rows): string {

				$h = '<ul class="util-seed-features">';

					foreach($rows as $row) {

						if(is_string($row)) {
							$h .= '<li class="util-seed-features-row"><span class="util-seed-features-name">'.$row.'</span></li>';
							continue;
						}

						[$name, $items, $open] = $row;

						if(count($items) === 1) {
							$h .= '<li class="util-seed-features-row"><span class="util-seed-features-name">'.$name.'</span><span class="util-seed-features-single">'.$items[0].'</span></li>';
							continue;
						}

						$h .= '<li><details'.($open ? ' open' : '').'><summary class="util-seed-features-row"><span class="util-seed-features-name">'.$name.'</span><span class="util-seed-features-toggle">'.\Asset::icon('chevron-down').\Asset::icon('chevron-up').'</span></summary><ul>';
							foreach($items as $item) {
								$h .= '<li>'.\Asset::icon('check-lg').'<span>'.$item.'</span></li>';
							}
						$h .= '</ul></details></li>';

					}

				$h .= '</ul>';

				return $h;

			};

			$h .= '<p class="util-annotation">'.s("util-seeds aligne quelques offres comparables, une par util-seed : une tête illustrée (util-seed-head, util-seed-name), puis un corps (util-seed-body) qui porte le prix, la frise des mois, la liste de ce que l'offre ouvre et, en pied, le geste. La liste est util-seed-features : chaque ligne util-seed-features-row, haute d'une cible tactile, porte un nom (util-seed-features-name). Un groupe de plusieurs lignes est un details fermé par défaut, dont le summary se clique en entier et porte à droite util-seed-features-toggle, un chevron vers le bas fermé, vers le haut ouvert ; ouvert, le groupe prend un fond et ses lignes une coche verte. Un groupe d'une seule ligne ne se déplie pas : sa ligne s'écrit sous son nom, en util-seed-features-single. Premier emploi : la page d'abonnement. Pour une longue liste d'offres sans illustration, voir util-seeds-plain plus bas.").'</p>';
			$h .= '<p class="util-annotation">'.s("Le prix se lit en deux tailles, util-seed-price pour le montant et util-seed-per pour l'unité, suivis d'une util-annotation qui dit le montant réellement payé.").'</p>';
			$h .= '<p class="util-annotation">'.s("Un sachet au panier prend util-seed-selected, une pastille util-seed-sticker et, à la place du bouton, la bande util-seed-in-basket, de même hauteur : entrer dans le panier ne déplace rien. util-seed-ribbon pose une échéance en haut à gauche, et ses voisins gardent la même hauteur de tête.").'</p>';
			$h .= '<p class="util-annotation">'.s("Un sachet qui porte un logo plutôt qu'un ami et son nom le pose dans util-seed-logo : le logo prend la largeur de la tête jusqu'à 16rem, sans le carré réservé aux amis. Il est inséré en SVG dans la page, pour que ses textes prennent DM Sans, que le site charge déjà. À partir de 768px, les amis des sachets voisins grandissent de la hauteur du logo pour que les têtes s'alignent, et deux amis côte à côte se resserrent en largeur.").'</p>';
			$h .= '<p class="util-annotation">'.s("util-seed-wide est le sachet qui réunit les autres : il prend une rangée entière sous eux, ses amis séparés par util-seed-plus, et, sous le nom, util-seed-formula qui dit ce qu'il réunit. À partir de 768px, sa tête passe à gauche avec les amis côte à côte, et les sachets du dessus passent à trois colonnes pour que son corps s'aligne sur les deux dernières. Avec un ruban, sa tête se décale vers le bas pour le laisser libre. util-seed-saving, posée après l'unité du prix, dit ce que le sachet fait gagner.").'</p>';
			$h .= '<p class="util-annotation">'.s("util-seed-calendar est une frise de mois dont la couleur vient de --seed-color, posée sur la frise : util-seed-calendar-in pour un mois couvert, util-seed-calendar-part pour un mois couvert en partie, util-seed-calendar-now pour le mois en cours. Elle n'est pas util-timeline, qui suit l'année civile et dont les mois se cliquent.").'</p>';
			$h .= '<p class="util-annotation">'.s("util-seed-lead est la phrase d'en-tête posée au-dessus des sachets. L'échéance d'une offre, qui gouverne la page, s'annonce par util-start.").'</p>';

			$calendar = function(string $color, array $months): string {

				$h = '<div class="util-seed-calendar" style="--seed-color: var('.$color.')">';
					foreach($months as [$letter, $class]) {
						$h .= '<span class="'.$class.'">'.$letter.'</span>';
					}
				$h .= '</div>';

				return $h;

			};

			$h .= '<p class="util-seed-lead">'.s("Seuls les administrateurs de la structure peuvent modifier l'abonnement.").'</p>';

			$h .= '<div class="util-seeds">';

				$h .= '<div class="util-seed util-seed-selected">';
					$h .= '<span class="util-seed-sticker">'.\Asset::icon('basket3-fill').'</span>';
					$h .= '<span class="util-seed-ribbon">'.s("Jusqu'au 14/11/2027").'</span>';
					$h .= '<div class="util-seed-head">'.\subscription\SubscriptionUi::getSoftwareFriend(\subscription\Subscription::MANAGEMENT).'<div class="util-seed-name">'.s("Gestion").'</div></div>';
					$h .= '<div class="util-seed-body">';
						$h .= '<div class="util-seed-cost"><span class="util-seed-price">'.s("10 € HT").'</span> <span class="util-seed-per">'.s("/ mois").'</span><div class="util-annotation">'.s("(soit 120 € HT par an)").'</div></div>';
						$h .= $calendar('--settings', [['N', 'util-seed-calendar-part util-seed-calendar-now'], ['D', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['F', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['S', 'util-seed-calendar-in'], ['O', 'util-seed-calendar-in'], ['N', 'util-seed-calendar-part']]);
						$h .= $features([[s("Comptabilité"), [s("Écritures comptables"), s("Bilan et compte de résultat"), s("Gestion de la TVA (CA3, CA12...)")], TRUE], [s("Banque"), [s("Liaison avec votre compte bancaire"), s("Rapprochement automatique des transactions")], FALSE], [s("E-mails"), [s("Envoi d'e-mails (2 000 / semaine)")], FALSE], $membershipGroup]);
						$h .= '<div class="util-seed-in-basket"><span>'.\Asset::icon('basket3-fill').s("Dans le panier").'</span><a href="#" title="'.s("Retirer du panier").'">'.\Asset::icon('x-lg').'</a></div>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-seed">';
					$h .= '<div class="util-seed-head">'.\subscription\SubscriptionUi::getCommunityFriend().\subscription\SubscriptionUi::getCommunityLogo().'</div>';
					$h .= '<div class="util-seed-body">';
						$h .= '<div><span class="badge badge-success">'.s("En cours").'</span> <span class="util-annotation">'.s("jusqu'au 14/11/2027").'</span></div>';
						$h .= $calendar('--success', [['N', 'util-seed-calendar-in util-seed-calendar-now'], ['D', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['F', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['S', 'util-seed-calendar-in'], ['O', 'util-seed-calendar-in']]);
						$h .= $features([[s("Accompagnement personnalisé"), [s("Une réponse garantie à chaque question"), s("Un conseiller joignable")], FALSE], [s("Échanges sur les circuits courts"), [s("Adapter les boutiques en ligne à toutes les productions"), s("Échanger entre collectifs de producteurs")], FALSE]]);
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-seed">';
					$h .= '<div class="util-seed-head">'.\subscription\SubscriptionUi::getSeedFriends(\subscription\Subscription::EXPERT, FALSE).'<div class="util-seed-name">'.s("Pilotage").'</div></div>';
					$h .= '<div class="util-seed-body">';
						$h .= '<div class="util-seed-cost"><span class="util-seed-price">'.s("25 € HT").'</span> <span class="util-seed-per">'.s("/ mois").'</span><div class="util-annotation">'.s("(soit 300 € HT par an)").'</div></div>';
						$h .= $features([s("Tout Gestion"), [s("Tableau de bord"), [s("Tableau de bord personnalisable de votre structure"), s("Notifications en temps réel")], FALSE], [s("Personnalisation"), [s("Personnalisation de vos boutiques en ligne et site internet"), s("Visibilité sur la carte des producteurs")], FALSE], $membershipGroup]);
						$h .= '<a href="#" class="btn btn-outline-primary">'.\Asset::icon('arrow-left-right').' '.s("Changer pour Pilotage").'</a>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-seed util-seed-wide">';
					$h .= '<div class="util-seed-head">'.\subscription\SubscriptionUi::getSeedFriends(\subscription\Subscription::EXPERT, TRUE).'<div class="util-seed-name">'.s("Offre intégrale").'</div><div class="util-seed-formula">'.s("Pilotage + Communauté").'</div></div>';
					$h .= '<div class="util-seed-body">';
						$h .= '<div class="util-seed-cost"><span class="util-seed-price">'.s("30 € HT").'</span> <span class="util-seed-per">'.s("/ mois").'</span> <span class="util-seed-saving">'.s("-4 € HT / mois").'</span><div class="util-annotation">'.s("(soit 360 € HT par an)").'</div></div>';
						$h .= $features([s("Tout Pilotage"), s("Toute la Communauté"), $membershipGroup]);
						$h .= '<a href="#" class="btn btn-outline-primary">'.\Asset::icon('arrow-left-right').' '.s("Changer pour l'offre intégrale").'</a>';
					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-seeds mt-2">';

				$h .= '<div class="util-seed util-seed-wide util-seed-selected">';
					$h .= '<span class="util-seed-sticker">'.\Asset::icon('basket3-fill').'</span>';
					$h .= '<span class="util-seed-ribbon">'.s("Jusqu'au 14/11/2027").'</span>';
					$h .= '<div class="util-seed-head">'.\subscription\SubscriptionUi::getSeedFriends(\subscription\Subscription::EXPERT, TRUE).'<div class="util-seed-name">'.s("Offre intégrale").'</div><div class="util-seed-formula">'.s("Pilotage + Communauté").'</div></div>';
					$h .= '<div class="util-seed-body">';
						$h .= '<div class="util-seed-cost"><span class="util-seed-price">'.s("30 € HT").'</span> <span class="util-seed-per">'.s("/ mois").'</span> <span class="util-seed-saving">'.s("-4 € HT / mois").'</span><div class="util-annotation">'.s("(soit 360 € HT par an)").'</div></div>';
						$h .= $features([s("Tout Pilotage"), s("Toute la Communauté"), $membershipGroup]);
						$h .= '<div class="util-seed-in-basket"><span>'.\Asset::icon('basket3-fill').s("Dans le panier").'</span><a href="#" title="'.s("Retirer du panier").'">'.\Asset::icon('x-lg').'</a></div>';
					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<h4 class="mt-2">'.s("Beaucoup d'offres sans illustration").'</h4>';
			$h .= '<p class="util-annotation">'.s("util-seeds-plain, posé sur util-seeds, sert une longue liste d'offres qui n'ont ni ami ni logo, comme les contrats ouverts d'une AMAP : la tête perd la teinte des abonnements et le nom se resserre, et chaque sachet prend les rangées de la liste, tête, prix, frise, formules et pied, pour que ses voisins s'alignent bande à bande quelle que soit la longueur de leur nom. util-seed-features y porte les formules de l'offre et leur prix. Un sachet choisi se lit à son liseré util-seed-selected et à la bande util-seed-in-basket, sans pastille, qui recouvrirait un nom long. À côté d'un panier, en util-layout-aside, une frise de plus de sept mois passe sur deux rangs de sept. util-seed-wide ne s'y emploie pas. Premier emploi : les contrats ouverts de l'adhérent d'une AMAP.").'</p>';

			$plain = function(string $name, string $producer, string $price, string $per, ?string $annotation, array $months, array $formulas, string $foot, string $badge = '', bool $selected = FALSE) use ($calendar): string {

				$h = '<div class="util-seed'.($selected ? ' util-seed-selected' : '').'">';
					$h .= '<div class="util-seed-head"><div class="util-seed-name">'.$name.'</div><div class="util-seed-formula">'.$producer.'</div>'.$badge.'</div>';
					$h .= '<div class="util-seed-body">';
						$h .= '<div class="util-seed-cost"><span class="util-seed-price">'.$price.'</span> <span class="util-seed-per">'.$per.'</span>'.($annotation === NULL ? '' : '<div class="util-annotation">'.$annotation.'</div>').'</div>';
						$h .= $calendar('--amap', $months);
						$h .= '<ul class="util-seed-features">';
							foreach($formulas as [$formula, $value]) {
								$h .= '<li class="util-seed-features-row"><span class="util-seed-features-name">'.$formula.'</span><span>'.$value.'</span></li>';
							}
						$h .= '</ul>';
						$h .= $foot;
					$h .= '</div>';
				$h .= '</div>';

				return $h;

			};

			$quarter = [['O', 'util-seed-calendar-part util-seed-calendar-now'], ['N', 'util-seed-calendar-in'], ['D', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-part']];
			$year = [['O', 'util-seed-calendar-part util-seed-calendar-now'], ['N', 'util-seed-calendar-in'], ['D', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['F', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['S', 'util-seed-calendar-in'], ['O', 'util-seed-calendar-part']];
			$season = [['M', 'util-seed-calendar-part'], ['A', 'util-seed-calendar-in'], ['M', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['J', 'util-seed-calendar-in'], ['A', 'util-seed-calendar-in'], ['S', 'util-seed-calendar-in'], ['O', 'util-seed-calendar-in'], ['N', 'util-seed-calendar-in'], ['D', 'util-seed-calendar-part']];
			$add = '<a href="#" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter").'</a>';

			$h .= '<div class="util-layout-aside">';
				$h .= '<div class="util-seeds util-seeds-plain">';
					$h .= $plain(s("Panier légumes grand format, 1er trimestre"), s("Les Jardins du Marais"), s("dès 12,00 €"), s("la distribution"), NULL, $quarter, [[s("Grand panier"), s("18,00 €")], [s("Moyen panier"), s("15,00 €")], [s("Petit panier"), s("12,00 €")]], '<div class="util-seed-in-basket"><span>'.\Asset::icon('basket3-fill').' '.s("Choisi").'</span><a href="#">'.\Asset::icon('x-lg').' '.s("Retirer").'</a></div>', selected: TRUE);
					$h .= $plain(s("Œufs"), s("La Ferme des Coteaux"), s("dès 2,40 €"), s("la distribution"), NULL, $year, [[s("Boite de 6 œufs"), s("2,40 €")]], $add, '<span class="badge badge-info">'.s("2 places").'</span>');
					$h .= $plain(s("Volailles"), s("Élevage du Pré Vert"), s("À la commande"), '', s("date par date"), $season, [[s("Poulet entier"), s("12,50 € / kg")], [s("Œufs de cane"), s("2,75 € / pièce")]], $add);
					$h .= $plain(s("Panier légumes petit format, 1er trimestre"), s("Les Jardins du Marais"), s("dès 9,00 €"), s("la distribution"), NULL, $quarter, [[s("Petit panier"), s("9,00 €")]], $add);
					$h .= $plain(s("Fromages de chèvre, 1er trimestre"), s("La Chèvrerie du Haut"), s("dès 4,00 €"), s("la distribution"), NULL, $quarter, [[s("Deux fromages"), s("4,00 €")]], '<a href="#" class="btn btn-outline-primary">'.s("M'inscrire sur la liste d'attente").'</a>', '<span class="badge badge-neutral">'.s("Complet").'</span>');
				$h .= '</div>';
				$h .= '<div class="util-layout-aside-side"><div class="util-block">';
					$h .= '<div class="util-basket-row util-basket-head"><span class="util-basket-icon">'.\Asset::icon('basket3').'</span><h3>'.s("Contrats choisis").'</h3><span></span></div>';
					$h .= '<div class="util-basket-row"><span></span><b>'.s("Panier légumes grand format, 1er trimestre").'</b><span>'.s("12,00 €").'</span></div>';
					$h .= '<div class="util-basket-row util-basket-total"><span></span><span>'.s("Par distribution").'</span><span>'.s("12,00 €").'</span></div>';
				$h .= '</div></div>';
			$h .= '</div>';

			$h .= '<h4 class="mt-2">'.s("Comparer les offres colonne par colonne").'</h4>';
			$h .= '<p class="util-annotation">'.s("util-table-seeds fait d'une util-table le tableau qui compare quelques offres : chaque colonne d'offre devient un sachet, séparé de ses voisins par une gouttière, et les libellés restent à gauche sur le fond de la page. Le premier rang, util-table-seeds-top, porte un util-seed util-seed-cap par offre : la tête et le prix du sachet, ouvert vers le bas sur sa colonne. Un groupe est un rang util-table-seeds-group, avec des cellules vides pour ne pas couper les gouttières, et le dernier rang, util-table-seeds-end, ferme les sachets. Une valeur se lit en coche verte, en - pour ce que l'offre n'a pas, ou en texte. Avec util-table-sticky, l'en-tête reste en vue. Sous 992px, le libellé passe sur toute la largeur au-dessus de ses valeurs. Premier emploi : la page publique des offres.").'</p>';

			$h .= '<div class="util-table util-table-sticky util-table-seeds">';

				$h .= '<div class="util-table-seeds-top">';
					$h .= '<div></div>';
					foreach([
						[\subscription\SubscriptionUi::getEssentialFriend(), \subscription\SubscriptionUi::getEssentialName(), s("Gratuit"), ''],
						[\subscription\SubscriptionUi::getSeedFriends(\subscription\Subscription::MANAGEMENT, FALSE), s("Gestion"), s("10 € HT"), s("(soit 120 € HT par an)")],
						[\subscription\SubscriptionUi::getSeedFriends(\subscription\Subscription::EXPERT, FALSE), s("Pilotage"), s("25 € HT"), s("(soit 300 € HT par an)")],
					] as [$friends, $name, $price, $annual]) {
						$h .= '<div class="util-seed util-seed-cap">';
							$h .= '<div class="util-seed-head">'.$friends.'<div class="util-seed-name">'.$name.'</div></div>';
							$h .= '<div class="util-seed-body">';
								$h .= '<div class="util-seed-cost"><span class="util-seed-price">'.$price.'</span>';
								if($annual !== '') {
									$h .= ' <span class="util-seed-per">'.s("/ mois").'</span><div class="util-annotation">'.$annual.'</div>';
								}
								$h .= '</div>';
								$h .= '<a href="#" class="btn btn-outline-primary">'.s("S'inscrire").'</a>';
							$h .= '</div>';
						$h .= '</div>';
					}
				$h .= '</div>';

				$h .= '<div class="util-table-head"><div>'.s("Fonctionnalité").'</div><div class="text-center">'.\subscription\SubscriptionUi::getEssentialName().'</div><div class="text-center">'.s("Gestion").'</div><div class="text-center">'.s("Pilotage").'</div></div>';

				foreach([
					[s("Équipe"), [[s("Utilisateurs"), ['1', s("illimités"), s("illimités")]], [s("Suivi du temps de travail"), [TRUE, TRUE, TRUE]]]],
					[s("Banque"), [[s("Rapprochement automatique des transactions"), [FALSE, TRUE, TRUE]]]],
					[s("Association {siteName}"), [[s("Adhésion à l'association"), [FALSE, s("20 € comprise"), s("20 € comprise")]]]],
					[s("Tableau de bord"), [[s("Notifications en temps réel"), [FALSE, FALSE, TRUE]]]],
				] as [$group, $rows]) {
					$h .= '<div class="util-table-row util-table-seeds-group"><div>'.$group.'</div><div></div><div></div><div></div></div>';
					foreach($rows as [$label, $values]) {
						$h .= '<div class="util-table-row"><div>'.$label.'</div>';
						foreach($values as $value) {
							$h .= '<div class="text-center">'.match($value) {
								TRUE => \Asset::icon('check-lg'),
								FALSE => '-',
								default => $value,
							}.'</div>';
						}
						$h .= '</div>';
					}
				}

				$h .= '<div class="util-table-seeds-end"><div></div><div></div><div></div><div></div></div>';

			$h .= '</div>';

			$h .= '<h4 class="mt-2">'.s("Après l'achat, ou à l'entrée dans une structure").'</h4>';
			$h .= '<p class="util-annotation">'.s("util-celebrate s'ajoute à util-start pour dire qu'un achat est confirmé, ou qu'une invitation vient d'être acceptée : le bloc se centre, et des graines util-celebrate-bit sortent une fois du sachet, placées par top, left ou right, avec leur point de départ en --from-x et --from-y et leur inclinaison finale en --turn. DesignUi::getCelebrateBits() en pose de une à six. Il en reste deux sous 576px, et elles apparaissent en fondu, sans bouger, si le système demande moins d'animations. Il garde la carte arrondie au lieu de la bande brossée, son overflow: hidden, qui retient les graines, rognant le coup de pinceau.").'</p>';
			$h .= '<p class="util-annotation">'.s("util-seed-torn est la tête seule d'un util-seed, déchirée en haut : le sachet ouvert de l'offre achetée, ou de la structure qui vous accueille. util-celebrate-title porte la phrase qui le dit, util-celebrate-lines ce qui suit, une ligne par paragraphe.").'</p>';

			$h .= '<div class="util-start util-celebrate">';

				$h .= \main\DesignUi::getCelebrateBits(4);

				$h .= '<div class="util-seed util-seed-torn"><div class="util-seed-head">'.\subscription\SubscriptionUi::getSoftwareFriend(\subscription\Subscription::MANAGEMENT).'<div class="util-seed-name">'.s("Gestion").'</div></div></div>';
				$h .= '<div class="util-celebrate-title">'.s("Vous avez souscrit à l'offre Gestion").'</div>';
				$h .= '<p class="util-celebrate-lines">'.s("Votre offre est active du 15/11/2026 au 14/11/2027.").'</p>';
				$h .= '<a href="#" class="btn btn-outline-primary">'.\Asset::icon('download').' '.s("Télécharger la facture").'</a>';

			$h .= '</div>';

			$h .= '<h4 class="mt-2">'.s("Un geste réservé à une offre").'</h4>';
			$h .= '<p class="util-annotation">'.s("Un geste qu'une offre ouvre reste visible sans elle : btn-offer-perched pose l'ami de l'offre sur le coin de son bouton, SubscriptionUi::getFeatureFriend() le rend. Le bouton ouvre un panneau dont le titre se termine par util-stamp util-stamp-friend, le cachet qui porte l'ami devant « Disponible avec l'offre … », rendu par SubscriptionUi::getFeatureStamp(). Premier emploi : « Importer une vente » sur la page des ventes.").'</p>';
			$h .= '<div class="util-block" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">';
				$h .= '<a href="#" class="btn btn-outline-primary btn-offer-perched">'.\Asset::icon('file-earmark-plus').' '.s("Importer une vente").\subscription\SubscriptionUi::getFeatureFriend(\structure\Structure::FEATURE_BALANCE).'</a>';
				$h .= \subscription\SubscriptionUi::getFeatureStamp(\structure\Structure::FEATURE_BALANCE);
			$h .= '</div>';

			$h .= '<p class="util-annotation">'.s("Le panneau montre ce que le geste aurait fait des données de la structure : util-offer-preview place un avant et un après de part et d'autre d'une flèche util-offer-preview-arrow, chacun sous son util-offer-preview-label, et les empile sous 768px, la flèche tournée vers le bas. util-offer-receipt figure un ticket de papier pour ce qui a été saisi à la main, ses produits en capitales par util-offer-receipt-name.").'</p>';
			$h .= '<div class="util-offer-preview">';
				$h .= '<div>';
					$h .= '<div class="util-offer-preview-label">'.s("Le ticket de votre balance").'</div>';
					$h .= '<div class="util-offer-receipt">';
						$h .= '<div><b>'.s("Rapport Z").'</b></div>';
						$h .= '<hr/>';
						$h .= '<div><span><span class="util-offer-receipt-name">'.s("Tomate ronde").'</span> 12,34 kg</span><span>55,53</span></div>';
						$h .= '<div><span><span class="util-offer-receipt-name">'.s("Poivron").'</span> 5,52 kg</span><span>29,83</span></div>';
						$h .= '<hr/>';
						$h .= '<div><b>'.s("Total").'</b><b>85,36</b></div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-offer-preview-arrow">'.\Asset::icon('arrow-right').'</div>';
				$h .= '<div>';
					$h .= '<div class="util-offer-preview-label">'.s("Votre vente, après vérification").'</div>';
					$h .= '<table class="tr-even mb-0">';
						$h .= '<tbody>';
							$h .= '<tr><td>'.s("Tomates rondes / Paola").'</td><td class="text-end td-min-content">12,34 kg</td><td class="text-end td-min-content">55,53 €</td></tr>';
							$h .= '<tr><td>'.s("Poivrons").'</td><td class="text-end td-min-content">5,52 kg</td><td class="text-end td-min-content">29,83 €</td></tr>';
						$h .= '</tbody>';
					$h .= '</table>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<p class="util-annotation">'.s("À côté, le sachet de l'offre liste tout ce qu'elle ouvre, groupes ouverts : util-seed-features-open, une ligne par fonctionnalité, et util-seed-features-highlight sur celle du geste. PageAiUi::getFeatureLines() la rend. Sous 992px, le sachet quitte la colonne et util-offer-footer le couche dans le pied du panneau : l'ami et le nom (util-offer-footer-name), le prix (util-offer-footer-price), le geste. Un pied qui le porte se masque à partir de 992px.").'</p>';
			$h .= '<div class="util-seeds">';
				$h .= '<div class="util-seed">';
					$h .= '<div class="util-seed-head">'.\subscription\SubscriptionUi::getSoftwareFriend(\subscription\Subscription::MANAGEMENT).'<div class="util-seed-name">'.s("Gestion").'</div></div>';
					$h .= '<div class="util-seed-body">';
						$h .= new \subscription\PageAiUi()->getFeatureLines([
							['label' => s("Outils de vente"), 'list' => [
								s("Variantes de produits"),
								['label' => s("Import des ventes d'une balance ou d'un autre logiciel"), 'feature' => \structure\Structure::FEATURE_BALANCE],
								s("Cagnottes"),
							]],
						], \structure\Structure::FEATURE_BALANCE);
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div class="util-offer-footer mt-1">';
				$h .= '<div class="util-offer-footer-name">'.\subscription\SubscriptionUi::getSoftwareFriend(\subscription\Subscription::MANAGEMENT).'<div class="util-seed-name">'.s("Gestion").'</div></div>';
				$h .= '<div class="util-offer-footer-price"><div class="util-seed-cost"><span class="util-seed-price">'.s("10 € HT").'</span> <span class="util-seed-per">'.s("/ mois").'</span></div></div>';
				$h .= '<a href="#" class="btn btn-primary btn-lg">'.s("Choisir l'offre Gestion").'</a>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Une invitation à accepter").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avant l'acceptation, la même carte sans graines : le sachet porte la mascotte du type de la structure et son nom, le titre dit à quoi l'on est invité, et une ligne dit le rôle. La case des conditions et les boutons restent dans la carte, la case centrée comme le reste par .util-celebrate .field-checkbox, son message de refus par le text-align hérité de la carte ; seul un vrai formulaire, un choix entre plusieurs structures ou les champs d'un groupe, passe dessous. InviteUi::getCard() la rend.").'</p>';

		$form = new \util\FormUi();

		$h .= \structure\InviteUi::getCard(
			new \structure\Structure(['type' => \structure\Structure::PRODUCER]),
			s("Ferme des Exemples"),
			s("Vous êtes invité à rejoindre cette ferme"),
			[s("Vous y serez <b>{role}</b>.", ['role' => s("Comptable")])],
			\structure\InviteUi::getGesture($form, new \structure\Invite(['tosRequired' => TRUE]), s("Rejoindre maintenant"))
		);

		return $h;

	}

	public function aside(): string {

		$h = '<p class="util-annotation">'.s("util-layout-aside place le contenu à gauche et, dans util-layout-aside-side, un résumé de 23rem qui reste en vue au défilement. Sous 992px, le résumé passe sous le contenu. Premier emploi : le panier de la page d'abonnement.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le panier se compose de util-basket-row, une icône (util-basket-icon), un nom et un montant aligné à droite, la tête en util-basket-head et le total en util-basket-total. Sur mobile, util-basket-bar reprend le total et le geste en bas d'écran, collée au défilement, et le bouton du résumé se cache.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une case à cocher peut accompagner le geste dans la barre, comme l'acceptation des conditions de vente : ses liens restent blancs et soulignés sur le fond de la marque.").'</p>';

		$h .= '<div class="util-layout-aside">';

			$h .= '<div class="util-block">'.s("Le contenu de l'écran").'</div>';

			$h .= '<div class="util-layout-aside-side">';
				$h .= '<div class="util-block">';
					$h .= '<div class="util-basket-row util-basket-head"><span class="util-basket-icon">'.\Asset::icon('basket3').'</span><h3>'.s("Panier").'</h3><span></span></div>';
					if(\structure\StructureSetting::hasSubscriptionPackage()) {
						$h .= '<div class="util-basket-row"><span class="util-basket-icon">'.\subscription\SubscriptionUi::getSoftwareFriend(\subscription\Subscription::MANAGEMENT).'</span><b>'.s("Gestion").'</b><span>'.s("10 € HT / mois").'</span></div>';
						$h .= '<div class="util-basket-row"><span class="util-basket-icon">'.\subscription\SubscriptionUi::getCommunityFriend().'</span><b>'.s("Communauté").'</b><span>'.s("9 € HT / mois").'</span></div>';
					}
					$h .= '<div class="util-basket-row util-basket-total"><span></span><span>'.s("Total").'</span><span>'.s("19 € HT / mois").'</span></div>';
					$h .= '<a href="#" class="btn btn-primary btn-lg mt-1">'.s("Souscrire aux offres Gestion et Communauté pour 273,60 € TTC").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<div class="util-basket-bar">';
			$h .= '<div><b>'.s("Gestion et Communauté").'</b><br/>'.s("19 € HT / mois").'</div>';
			$h .= new \util\FormUi()->checkbox(NULL, 1, ['callbackLabel' => fn(string $input) => $input.'<span>'.s("J'ai lu et j'accepte les <link>conditions générales de vente</link>.", ['link' => '<a href="#">']).'</span>']);
			$h .= '<a href="#" class="btn btn-white btn-lg">'.s("Souscrire aux offres Gestion et Communauté pour 273,60 € TTC").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function previews(): string {

		$h = '<p class="util-annotation">'.s("util-preview montre un document déposé dans la colonne de côté, pour le lire pendant qu'on le rattache. Le PDF s'y affiche dans une iframe, au format d'une feuille (util-preview-pdf), la photo dans une image au format portrait (util-preview-photo). Un fichier effacé garde sa place et sa forme, avec util-empty qui le dit (util-preview-erased). Premier emploi : la fiche d'un justificatif (proof\\ProofUi).").'</p>';

		$h .= '<div class="design-columns">';
			$h .= '<div class="util-preview util-preview-photo">'.\Asset::image('main', 'producers.webp').'</div>';
			$h .= '<div class="util-preview util-preview-pdf util-preview-erased"><div class="util-empty"><p>'.s("Fichier effacé le 24/04/2026").'</p></div></div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Plusieurs pages d'un document").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-previews range plusieurs aperçus en vignettes. Chaque util-previews-item porte son aperçu, un libellé dessous et, quand la page peut se retirer, la croix util-previews-remove. Un fichier sans aperçu, un PDF, montre son icône dans le cadre. La page montrée en grand à côté est cerclée (util-previews-selected). La dernière vignette peut ajouter une page (util-previews-add) : un label qui porte le champ fichier, au bord pointillé. util-previews-large agrandit les vignettes quand elles sont le seul contenu du panneau, sans page en grand à côté. Premier emploi : les pages d'un justificatif, puis les photos d'un ticket de balance.").'</p>';

		$h .= '<div style="max-width: 24rem;">';
			$h .= '<div class="util-previews">';
				foreach([1, 2, 3] as $page) {
					$h .= '<div class="util-previews-item'.($page === 1 ? ' util-previews-selected' : '').'">';
						$h .= '<div class="util-preview util-preview-photo">'.\Asset::image('main', 'producers.webp').'</div>';
						$h .= '<span>'.s("Page {value}", $page).'</span>';
						$h .= '<a class="util-previews-remove">'.\Asset::icon('x-lg').'</a>';
					$h .= '</div>';
				}
				$h .= '<div class="util-previews-item">';
					$h .= '<div class="util-preview util-preview-photo">'.\Asset::icon('file-pdf').'</div>';
					$h .= '<span class="color-danger">'.\Asset::icon('x-circle').' '.s("Doublon").'</span>';
					$h .= '<a class="util-previews-remove">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';
				$h .= '<label class="util-previews-item util-previews-add">';
					$h .= '<div class="util-preview util-preview-photo">'.\Asset::icon('camera').'</div>';
					$h .= '<span>'.s("Ajouter une page").'</span>';
				$h .= '</label>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-previews util-previews-large mt-2">';
			foreach([1, 2] as $page) {
				$h .= '<div class="util-previews-item">';
					$h .= '<div class="util-preview util-preview-photo">'.\Asset::image('main', 'producers.webp').'</div>';
					$h .= '<span>'.s("Photo {value}", $page).'</span>';
				$h .= '</div>';
			}
		$h .= '</div>';

		return $h;

	}

	public function publicHero(): string {

		$h = '<p class="util-annotation">'.s("util-page-hero ouvre une page publique, hors d'une structure, comme les tutoriels ou la FAQ. Un écran de structure garde util-page-header. Deux exceptions : le tableau de bord d'une AMAP pendant une saison, dont la bande porte la saison et ses chiffres, et les écrans d'un groupe, que util-masthead ouvre (section suivante). DesignUi::getPageHero() la rend, et pose seule le h1. Dans util-page-hero-inner, posé avec container, la colonne de gauche porte util-page-hero-top (le retour util-page-header-back, une tuile d'icône et le surtitre en util-label), le titre, le chapô util-page-hero-lead et, au besoin, la recherche. La colonne de droite se cale en bas et porte les chiffres. Sous 768 px, elle passe sous la première.").'</p>';
		$h .= '<p class="util-annotation">'.s("La bande pose --heading-weight à 700, comme util-band, et ses titres le lisent, ceux d'un util-title compris. Hors d'elles, le lighter des titres d'écran ne change pas. Son fond est --color-bg-turquoise et son titre --color-encre, un cran sous la marque. Les deux disques sont décoratifs et tirés de la lavande et de la marque.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-icon-raised fait d'util-icon une tuile blanche, ombrée, à l'icône de la marque, qui se détache d'un fond teinté. util-figures-line-card met les chiffres d'util-figures-line dans une carte blanche à filet --border-brand ; une carte n'en portant que deux ou trois, ils restent côte à côte à toutes les largeurs. util-omnibox-hero donne à util-omnibox la forme d'une pilule à la largeur de sa colonne, et rien d'autre : la marge reste à l'appelant.").'</p>';

		$figures = fn(array $items) => '<div class="util-figures-line util-figures-line-card">'.implode('', array_map(fn(array $item) => '<div class="util-figures-line-item"><div class="util-figure-value">'.$item[0].'</div><div class="util-figure-label">'.$item[1].'</div></div>', $items)).'</div>';

		$hero = fn(string $icon, string $label, string $title, string $lead, string $search, string $figures): string => $this->getBandFrame(\main\DesignUi::getPageHero($title, $label, $icon, '#design-publicHero', $lead, $search, $figures));

		$h .= $hero(
			'play-btn',
			s("Ressources pédagogiques"),
			s("Tutoriels vidéo"),
			s("Quatre phases pour prendre le logiciel en main, de la création de votre espace à la commercialisation, chacune en modules courts."),
			'',
			$figures([['31', s("vidéos")], ['2 h 05', s("au total")], ['4', s("phases")]])
		);

		$search = '<div class="util-omnibox util-omnibox-hero mt-2">';
			$search .= '<div class="util-omnibox-field form-control">';
				$search .= \Asset::icon('search');
				$search .= '<span class="util-omnibox-tail"><input type="text" class="util-omnibox-input" autocomplete="off" placeholder="'.s("Ce que vous voyez à l'écran...").'"/></span>';
			$search .= '</div>';
		$search .= '</div>';

		$h .= $hero(
			'question-circle',
			s("Questions d'utilisation"),
			s("Que se passe-t-il à l'écran ?"),
			s("Décrivez ce que vous voyez avec vos mots, sans chercher le nom de la fonction. Chaque réponse dit pourquoi, et comment faire autrement."),
			$search,
			$figures([['23', s("questions")], ['7', s("écrans")]])
		);

		return $h;

	}

	/**
	 * Sur une page, une bande prend la largeur de l'écran et son container s'y ajuste. Posée ici dans
	 * le container de la page, elle déborderait de ses marges : la démonstration lui rend la largeur
	 * de sa colonne.
	 */
	private function getBandFrame(string $band): string {
		return '<div class="mb-2" style="--screen-width: 100%;">'.$band.'</div>';
	}

	public function groupMasthead(): string {

		$h = '<p class="util-annotation">'.s("util-masthead ouvre un écran d'un groupe, d'un réseau ou d'une AMAP, sous son bandeau. C'est un modificateur de util-page-hero, dont il garde le fond et le grand disque, ramené à la hauteur d'une tête d'écran. DesignUi::getPageMasthead() la rend autour d'un DesignUi::getPageHeader() déjà rendu, qui pose seul le h1 ; un écran de groupe la reçoit de son gabarit, qui la remplit de mainTitle, mainTabs, mainFilter et mainFigures.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-masthead-head aligne la vignette util-masthead-thumb (l'icône de l'onglet, à la couleur du groupe), la tête et la carte util-masthead-figures. Une tête qui a son logo, la couverture d'une formation, n'a pas de vignette ; le logo d'une structure s'y pose dans util-masthead-logo, qui lui donne la taille de la vignette. Le retour nommé par util-page-header-back-label devient un surtitre ; hors de la bande, le nom ne paraît pas. Les gestes de la tête sortent d'elle dans util-masthead-actions : les outils et la roue se rangent dans la barre util-masthead-bar, la création après elle. util-masthead-foot porte les onglets util-masthead-tabs, en .tab-item, puis un filtre à droite ; l'onglet ouvert prend le fond de la page. Le filtre est un util-filter à menu, ou un util-filter-options quand ses deux ou trois choix et leurs compteurs doivent rester visibles. util-masthead-network donne sa teinte à la bande d'un réseau (--network-background), util-masthead-amap à celle d'une AMAP (--amap-background). Sous 768 px, les gestes passent sous le titre et son contexte, et la vignette se cale en haut. Sous 576 px, la carte de chiffres passe sous la vignette.").'</p>';

		$back = '#design-groupMasthead';

		$h .= $this->getBandFrame(\main\DesignUi::getPageMasthead(
			\main\DesignUi::getPageHeader(
				s("Relevé d'automne").' <span class="util-counter">12 / 30</span>',
				context: s("Du 01/10 au 30/11 · Tout le réseau · toutes filières"),
				state: '<span class="badge badge-info">'.s("En cours jusqu'au 30/11").'</span>',
				back: $back,
				tools: '<a href="'.$back.'" class="btn btn-outline-secondary">'.\Asset::icon('check-lg').' '.s("Terminer la collecte").'</a>',
				create: '<a href="'.$back.'" class="btn btn-secondary">'.\Asset::icon('send').' '.s("Envoyer un e-mail").'</a>',
				settings: \main\DesignUi::getGear('<a href="'.$back.'" class="dropdown-item">'.s("Modifier la description").'</a>'),
				backLabel: s("Relevés de prix")
			),
			'clipboard-data',
			'<a href="'.$back.'" class="tab-item selected">'.s("Collecte").'</a><a href="'.$back.'" class="tab-item">'.s("Départements").'</a>'
		));

		$h .= $this->getBandFrame(\main\DesignUi::getPageMasthead(
			\main\DesignUi::getPageHeader(s("Tableau de bord")),
			'speedometer2',
			figures: '<div class="util-figures-line util-figures-line-card"><a href="'.$back.'" class="util-figures-line-item"><div class="util-figure-value">48</div><div class="util-figure-label">'.s("membres").'</div></a><a href="'.$back.'" class="util-figures-line-item"><div class="util-figure-value">31</div><div class="util-figure-label">'.s("structures").'</div></a></div>',
			class: 'util-masthead-network'
		));

		$h .= $this->getBandFrame(\main\DesignUi::getPageMasthead(
			\main\DesignUi::getPageHeader(s("Cotisations")),
			'people-fill',
			filter: '<div class="util-filter-options"><a href="'.$back.'" class="util-filter-option is-selected">'.s("À régler").' <span class="util-counter">4</span></a><a href="'.$back.'" class="util-filter-option">'.s("Toutes").' <span class="util-counter">37</span></a></div>',
			class: 'util-masthead-amap'
		));

		$h .= $this->getBandFrame(\main\DesignUi::getPageMasthead(
			\main\DesignUi::getPageHeader(
				s("La Chèvrerie du Lavoir"),
				context: s("Depuis 2012"),
				logo: '<div class="util-masthead-logo"><div style="width: 100%; height: 100%; background: var(--background);"></div></div>',
				back: $back,
				backLabel: s("Producteurs")
			),
			'producer-fill',
			class: 'util-masthead-amap'
		));

		return $h;

	}

	public function publicBands(): string {

		$h = '<p class="util-annotation">'.s("util-band pose une section d'une page publique sur toute la largeur, à bords droits, et util-band-alt la met sur fond gris, une bande sur deux, pour que la suite se lise sans filet. Comme util-page-hero, elle pose --heading-weight à 700 sur ses seuls h2 et h3.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-layout-aside-start est util-layout-aside à l'envers, la colonne de côté à gauche : elle introduit ce qu'elle précède, ou en filtre la liste. Sa largeur se règle par --aside-width, 23 rem par défaut. Elle reste en vue au défilement, sous la barre du site sur une page publique, et passe au-dessus du contenu sous 992 px.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-tint-phase-1 à util-tint-phase-4 posent la teinte d'une phase dans --tint, que lisent badge-tint, util-procedure-tint, util-rule, util-rule-caption, util-deck-aside-list et util-deck-row-index. --tint ne remplace ni --accent ni --badge-tint : util-deck remet --accent au gris et .badge son --badge-tint, chacun sur lui-même, donc aucun des deux ne passerait d'une bande à ses blocs. Aucun composant ne remet --tint. Les quatre jetons --color-phase-* portent un chiffre blanc à 4,5:1 au moins ; la troisième phase a son jeton et ne prend pas la terracotta, et aucun composant d'alerte ne les lit.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-procedure-tint peint les pastilles et le trait d'util-procedure à la teinte du bloc. --marker-size règle la pastille, 2 rem par défaut, et la colonne et le trait la suivent ; util-procedure-check ne le lit pas, sa coche étant placée pour la pastille de 2 rem. --marker-start donne le premier numéro, pour une suite qui reprend celle de son parent : ici, les modules 3 et 4.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-deck-round arrondit util-deck au rayon --radius-lg et le cerne du filet --border-brand. util-deck-aside-list range dans la tête ce que le bloc contient, en liste à icônes calée en haut et séparée du titre par un filet, le total en util-deck-aside-total, et --deck-aside-width lui donne la même largeur sur toutes les cartes d'une grille ; sous 768 px, elle passe sous le titre, et le chevron d'un deck lien reste à côté de lui. util-deck-row-numbered, sur util-deck-row, numérote une suite de lignes par util-deck-row-index, à chiffres égaux. Le numéro vient du code, une leçon reprenant celui de son module.").'</p>';

		$phases = [
			[1, s("Phase 1"), s("Les fondations"), s("Créez votre espace, repérez-vous dans le logiciel, puis dessinez vos parcelles et vos planches.")],
			[2, s("Phase 2"), s("La planification"), s("Passez d'une première série à une saison complète, du plan de culture à la commande de semences.")],
			[3, s("Phase 3"), s("Le suivi au quotidien"), s("Notez les interventions, le temps passé et les récoltes au fil de la saison.")],
			[4, s("Phase 4"), s("La commercialisation"), s("Vendez au marché, en boutique en ligne et aux professionnels, puis facturez.")],
		];

		$modules = [
			[s("Module 3 · Dessiner le plan des cultures"), s("Quelle surface cultivable, découpée comment ?"), s("19 min"), 1, [
				[s("Créer son premier jardin en cinq étapes"), s("5 min")],
				[s("Créer et modifier des parcelles"), s("3 min")],
				[s("Tracer une parcelle, un jardin et des planches"), s("4 min")],
			]],
			[s("Module 4 · Le plan de culture"), s("Comment prévoir et suivre ses cultures sur une saison ?"), s("26 min"), 5, [
				[s("Lire et manipuler le plan de culture"), s("7 min")],
				[s("Ajouter une série au plan de culture"), s("8 min")],
			]],
		];

		foreach($phases as [$position, $label, $name, $summary]) {

			$tint = match($position) {
				1 => 'util-tint-phase-1',
				2 => 'util-tint-phase-2',
				3 => 'util-tint-phase-3',
				4 => 'util-tint-phase-4',
			};

			$h .= '<div class="util-band'.(($position % 2 === 0) ? ' util-band-alt' : '').' '.$tint.'">';
				$h .= '<div class="util-layout-aside-start" style="--aside-width: 17rem;">';

					$h .= '<div class="util-layout-aside-side">';
						$h .= '<span class="badge badge-solid badge-tint">'.$label.'</span>';
						$h .= '<h2>'.$name.'</h2>';
						$h .= '<p>'.$summary.'</p>';
						$h .= '<span class="util-rule"></span>';
						$h .= '<p class="util-rule-caption">'.p("{value} module", "{value} modules", count($modules)).'</p>';
					$h .= '</div>';

					$h .= '<ol class="util-procedure util-procedure-tint" style="--marker-size: 2.75rem; --marker-counter: decimal-leading-zero; --marker-start: 3;">';

						foreach($modules as $index => [$title, $question, $duration, $questions, $lessons]) {

							$h .= '<li class="util-procedure-step">';
								$h .= '<div class="util-deck util-deck-round util-deck-top'.(($index === 0 and $position === 1) ? ' util-deck-open' : '').'">';
									$h .= '<div class="util-deck-head">';
										$h .= '<div class="util-deck-body">';
											$h .= '<h3>'.$title.'</h3>';
											$h .= '<p>'.$question.'</p>';
										$h .= '</div>';
										$h .= '<ul class="util-deck-aside-list">';
											$h .= '<li>'.\Asset::icon('play-btn').' '.p("{value} vidéo", "{value} vidéos", count($lessons)).'</li>';
											$h .= '<li><a href="#design-publicBands">'.\Asset::icon('chat-square-text').' '.p("{value} question de la FAQ", "{value} questions de la FAQ", $questions).'</a></li>';
											$h .= '<li class="util-deck-aside-total">'.\Asset::icon('clock').' '.$duration.'</li>';
										$h .= '</ul>';
									$h .= '</div>';
									$h .= '<div class="util-deck-fold">';
										foreach($lessons as $lesson => [$lessonTitle, $lessonDuration]) {
											$h .= '<div class="util-deck-row util-deck-row-numbered">';
												$h .= '<span class="util-deck-row-index">'.($index + 3).'.'.($lesson + 1).'</span>';
												$h .= '<span class="util-deck-row-name">'.$lessonTitle.'</span>';
												$h .= '<span class="color-muted">'.$lessonDuration.'</span>';
											$h .= '</div>';
										}
									$h .= '</div>';
								$h .= '</div>';
							$h .= '</li>';

						}

					$h .= '</ol>';

				$h .= '</div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Un titre en vert, des boutons centrés").'</h4>';
		$h .= '<p class="util-annotation">'.s("color-encre-accent écrit en vert un titre ou un chiffre d'une page publique. Elle lit --color-encre-accent, l'émeraude assombrie jusqu'à 4,6:1 sur blanc. flex-justify-center centre une rangée flex-buttons sous un texte centré.").'</p>';
		$h .= '<div class="util-band text-center">';
			$h .= '<h2 class="color-encre-accent">'.s("Une formation pour démarrer").'</h2>';
			$h .= '<p>'.s("Trois demi-journées pour prendre en main le logiciel.").'</p>';
			$h .= '<div class="flex-buttons flex-justify-center">';
				$h .= '<a href="#" class="btn btn-primary btn-lg">'.s("Voir les dates").'</a>';
				$h .= '<a href="#" class="btn btn-outline-primary btn-lg">'.s("Le programme").'</a>';
			$h .= '</div>';
		$h .= '</div>';
		return $h;

	}

	public function publicAnswer(): string {

		$h = '<p class="util-annotation">'.s("util-filter-options-column met le rail d'util-filter-options en colonne, dans la colonne de côté d'une liste : chaque util-filter-option prend la largeur et cale son util-counter à droite. Sous 992 px, la colonne passe au-dessus de la liste et le rail redevient une rangée, qui défile au doigt. L'option rendue avec is-selected y est amenée dans la vue, au chargement comme en ajax ; un écran qui change is-selected en JS l'amène lui-même par Lime.Tab.reveal([option]). Un choix d'un clic sans menu reste util-filter-option, util-chip portant le chevron d'un menu.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-deck-kicker précède le titre d'un bloc d'une rangée qui le situe, une marque et l'écran dont il parle, à la taille du texte. util-split pose le propos à gauche et ce qui l'accompagne à droite, en 1,4 et 1 ; une colonne seule prend la largeur, et les deux passent l'une sous l'autre sous 768 px. util-stack met des blocs les uns sous les autres à var(--space-md), util-stack-xs à var(--space-xs), sans toucher à leurs marges.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-link-path mène à un écran avec le chemin qui y conduit dans le menu, en small sous le libellé. Le fond doux qui accompagne la réponse est util-block-inside, et l'appel en fin de page util-block-dark : ni l'un ni l'autre ne demande de variante.").'</p>';

		$h .= '<div class="util-layout-aside-start" style="--aside-width: 15.5rem;">';

			$h .= '<div class="util-layout-aside-side">';
				$h .= '<div class="util-label">'.s("Écrans").'</div>';
				$h .= '<div class="util-filter-options util-filter-options-column">';
					foreach([
						[s("Tous les écrans"), 23, TRUE],
						[s("Plan de culture"), 6, FALSE],
						[s("Itinéraires techniques"), 4, FALSE],
						[s("Plan d'assolement"), 3, FALSE],
						[s("Ventes et commandes des clients professionnels"), 5, FALSE],
					] as [$label, $count, $selected]) {
						$h .= '<a href="#design-publicAnswer" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.' <span class="util-counter">'.$count.'</span></a>';
					}
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-stack util-stack-xs">';
				$h .= '<div class="util-deck util-deck-round util-deck-open">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-kicker"><span class="badge badge-dot badge-neutral">'.s("C'est voulu").'</span>'.s("Plan de culture").'</div>';
							$h .= '<div class="util-deck-title util-deck-title-heading">'.s("Pourquoi la surface et le rendement incluent-ils les passe-pieds ?").'</div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-fold">';
						$h .= '<div class="util-deck-row">';
							$h .= '<div class="util-deck-body util-split">';
								$h .= '<div>';
									$h .= '<p>'.s("La surface d'une série vaut sa longueur multipliée par la largeur travaillée et le passe-pied. Rendements, densités et fertilisation se calculent sur cette surface, comme dans les références techniques.").'</p>';
									$h .= '<a href="#design-publicAnswer" class="util-link-path">'.\Asset::icon('box-arrow-up-right').'<span>'.s("Voir dans la démo").'<small>'.s("Production, Plan de culture, Modifier la série").'</small></span></a>';
								$h .= '</div>';
								$h .= '<div class="util-block-inside">';
									$h .= '<div class="util-label">'.s("Ce que vous voyez").'</div>';
									$h .= '<ul class="mb-0">';
										$h .= '<li>'.s("Ma planche de 20 m sur 80 cm est comptée 24 m² au lieu de 16").'</li>';
										$h .= '<li>'.s("Mon rendement prévu est gonflé de 30 à 50 %").'</li>';
									$h .= '</ul>';
								$h .= '</div>';
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck util-deck-round">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-kicker"><span class="badge badge-dot badge-neutral">'.s("C'est voulu").'</span>'.s("Itinéraires techniques").'</div>';
							$h .= '<div class="util-deck-title util-deck-title-heading">'.s("Pourquoi mon itinéraire ne modifie-t-il pas les séries déjà créées ?").'</div>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une réponse à la couleur d'un réseau, son titre de bloc et l'appel de fin").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-brand-scope se pose sur l'élément qui redéfinit --color-brand, comme la page d'un réseau à sa couleur : --border-brand et --color-encre y sont recalculés, et le filet d'un util-deck-round suit la marque. util-page-hero et util-band le font déjà d'elles-mêmes. Une bande d'accueil posée dedans prend la couleur de la portée par --primary-on-light, que la page d'un réseau pose avec sa marque. --page-hero-background et --page-hero-ink se lisent aussi dans toute la portée, pour qu'un repère de la page prenne la teinte de la bande.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-deck-title-heading écrit le titre d'un bloc comme les titres des pages publiques : leur fonte, leur graisse et l'encre de la marque. Un état du deck garde la main sur lui.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-block-round arrondit et aère un util-block comme util-deck-round le fait d'un deck. L'appel en fin de page est un util-block util-block-dark util-block-round.").'</p>';

		$h .= '<div class="util-brand-scope design-columns" style="--color-brand: #8A3B12;">';

			$h .= '<div class="util-deck util-deck-round">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-kicker"><span class="badge badge-dot badge-neutral">'.s("Possible").'</span>'.s("Planning").'</div>';
						$h .= '<div class="util-deck-title util-deck-title-heading">'.s("Comment décaler toutes les interventions d'une série ?").'</div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-block util-block-dark util-block-round bg-primary">';
				$h .= '<h3>'.s("Une question sans réponse ?").'</h3>';
				$h .= '<p>'.s("Posez-la au réseau, qui la reprendra dans cette page si elle sert à d'autres.").'</p>';
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("La colonne en navigation").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-filter-options-nav, posé en plus d'util-filter-options-column, en fait une navigation : plus de rail, chaque option est une entrée de menu, le choix plein à la couleur de la marque comme dans le menu de l'application, les comptes en gris hors du choix. Sous util-brand-scope, la colonne prend la couleur du réseau, à droite.").'</p>';

		$h .= '<div class="design-columns mt-2">';

			foreach(['', '--color-brand: #8A3B12;'] as $brand) {

				$h .= '<div'.($brand ? ' class="util-brand-scope"' : '').' style="max-width: 15.5rem; '.$brand.'">';
					$h .= '<div class="util-label">'.s("Quel module ?").'</div>';
					$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav">';
						foreach([
							[s("Tous les modules"), 23, TRUE],
							[s("Le plan de culture"), 5, FALSE],
							[s("Itinéraires techniques"), 4, FALSE],
							[s("Planning et temps de travail"), 6, FALSE],
						] as [$label, $count, $selected]) {
							$h .= '<a href="#design-publicAnswer" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.' <span class="util-counter">'.$count.'</span></a>';
						}
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Les options en groupes").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-filter-options-title range les options en groupes sous un intitulé, à la forme d'util-filter-label. Une colonne groupée n'a pas de titre : l'intitulé du groupe en tient lieu, et les options sans groupe restent en tête. Sous 992 px, l'intitulé reste dans la rangée qui défile, séparé du groupe précédent par un filet.").'</p>';

		$h .= '<div class="design-columns mt-2">';

			foreach(['', '--color-brand: #8A3B12;'] as $brand) {

				$h .= '<div'.($brand ? ' class="util-brand-scope"' : '').' style="max-width: 15.5rem; '.$brand.'">';
					$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav">';
						foreach([
							[NULL, s("Tous les modules"), 23, FALSE],
							[NULL, s("Questions générales"), 2, FALSE],
							[s("Planification des cultures"), NULL, 0, FALSE],
							[NULL, s("Module 1 · Le plan de culture"), 5, FALSE],
							[NULL, s("Module 2 · Itinéraires techniques"), 4, TRUE],
							[s("Commercialisation"), NULL, 0, FALSE],
							[NULL, s("Module 1 · La boutique en ligne"), 8, FALSE],
							[NULL, s("Module 2 · La caisse"), 4, FALSE],
						] as [$title, $label, $count, $selected]) {
							if($title !== NULL) {
								$h .= '<div class="util-filter-options-title">'.$title.'</div>';
							} else {
								$h .= '<a href="#design-publicAnswer" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.' <span class="util-counter">'.$count.'</span></a>';
							}
						}
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Le menu gardé en colonne").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-filter-options-menu, posé en plus d'util-filter-options-column et d'util-filter-options-nav, garde la colonne sous 992 px. Il sert au menu d'une page de lecture, comme celui de la documentation ou d'une formation, quand la page le tient dans une colonne de côté puis le replie elle-même dans un panneau ouvert par un bouton : la colonne n'y a pas de place pour une rangée. Il sert aussi une navigation courte, à choix unique, dont chaque entrée porte un chiffre qu'on compare aux autres, comme les postes du paramétrage du prévisionnel financier : une rangée qui défile en cacherait la moitié.").'</p>';

		$h .= '<div class="mt-2" style="max-width: 15.5rem;">';
			$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav util-filter-options-menu">';
				foreach([
					[s("Prise en main"), NULL, FALSE],
					[NULL, s("Créer sa structure"), FALSE],
					[NULL, s("Inviter son équipe"), TRUE],
					[s("Commercialisation"), NULL, FALSE],
					[NULL, s("Ouvrir une boutique"), FALSE],
				] as [$title, $label, $selected]) {
					if($title !== NULL) {
						$h .= '<div class="util-filter-options-title">'.$title.'</div>';
					} else {
						$h .= '<a href="#design-publicAnswer" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.'</a>';
					}
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Les trois niveaux du menu d'une formation").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le menu d'une formation a trois niveaux. La section garde util-filter-options-title, la formation lue prend util-filter-options-title-subject et chaque module util-filter-options-title-group, posés en plus d'util-filter-options-title. La page règle le corps des entrées par --filter-option-size, que les intitulés suivent.").'</p>';

		$h .= '<div class="mt-2" style="max-width: 15.5rem;">';
			$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav util-filter-options-menu">';
				$h .= '<div class="util-filter-options-title">'.s("Formations").'</div>';
				$h .= '<div class="util-filter-options-title util-filter-options-title-subject">'.s("Vendre en ligne").'</div>';
				foreach([
					[s("Préparer sa boutique"), [[s("Créer la boutique"), FALSE], [s("Choisir les produits"), TRUE]]],
					[s("Ouvrir les ventes"), [[s("Fixer les dates"), FALSE]]],
				] as [$group, $lessons]) {
					$h .= '<div class="util-filter-options-title util-filter-options-title-group">'.$group.'</div>';
					foreach($lessons as [$label, $selected]) {
						$h .= '<a href="#design-publicAnswer" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.'</a>';
					}
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function confirmPanel(): string {

		$h = '<p class="util-annotation">'.s("main\\DesignUi::getConfirmPanel() rend un panneau qui confirme un geste qui part chez d'autres sans rien détruire : un envoi, une publication. Il dit ce que le geste va faire, chiffré, puis le geste et « Annuler ».").'</p>';

		$h .= '<p class="util-annotation">'.s("Trois confirmations, une question chacune. data-confirm ouvre le confirm() du navigateur et suffit à un geste sans conséquence pour autrui. getDeletePanel() pèse une perte sans retour, au registre danger de util-weigh. Ce panneau se place entre les deux : le geste atteint d'autres personnes, qui vont recevoir quelque chose, mais personne ne perd rien. Ni registre coloré ni inventaire, donc : le rouge mentirait, et une liste de pertes vide ferait douter.").'</p>';

		$h .= '<p class="util-annotation">'.s("Le texte chiffre ce que la page a déjà sous la main, comme le panneau de suppression : la date, le nombre de destinataires. Le bouton plein est écrit par l'appelant, qui connaît l'adresse et ses paramètres ; « Annuler » ferme le panneau sans rien faire partir. Premier emploi : l'envoi du relevé d'un groupe aux fermes.").'</p>';

		$panel = \main\DesignUi::getConfirmPanel(
			'panel-design-confirm',
			s("Envoyer le relevé aux fermes"),
			s("Relevé du 10/09/2026"),
			s("Le relevé du 10/09/2026 partira à 21 fermes."),
			'<a class="btn btn-primary">'.s("Envoyer le relevé").'</a>'
		);

		$h .= '<div style="max-width: 34rem;">'.$panel->body.'</div>';

		return $h;

	}

	public function ticks(): string {

		$h = '<p class="util-annotation">'.s("util-tick coche d'un clic une ligne d'une liste de choses à faire, sans formulaire. Un cercle vide reste à faire, un cercle coché est fait. Au survol, la case prend déjà la couleur de l'état que le clic donnera. Elle se pose sur le lien ou l'élément qui porte le geste, autour d'Asset::icon('circle') ou Asset::icon('check-circle'). Sans le droit de cocher, l'icône reste nue, sans util-tick. util-tick-line pose la case à 0.5rem de son libellé et ramène un libellé long sous lui-même, jamais sous la case. Modèles : les lignes O et X d'une description d'intervention (TaskUi::getDescription()), le pense-bête de la Semaine (ReminderUi::getReminder()) et celui de l'accueil.").'</p>';

		$h .= '<div class="util-rows">';
			$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('circle').'</a><div>'.s("Commander les plants de poireaux").'</div></div></div>';
			$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('check-circle').'</a><div>'.s("Passer chez le maraîcher voisin").'</div></div></div>';
			$h .= '<div class="util-row"><div class="util-tick-line"><a class="util-tick">'.\Asset::icon('circle').'</a><div>'.s("Rappeler le fournisseur de terreau pour décaler la livraison des sacs à la semaine prochaine, la serre n'étant pas encore vidée des tomates").'</div></div></div>';
			$h .= '<div class="util-row"><div class="util-tick-line">'.\Asset::icon('circle').'<div>'.s("Réparer la bâche de la serre, sans le droit de cocher").'</div></div></div>';
		$h .= '</div>';

		return $h;

	}

	public function switches(): string {

		$h = '<p class="util-annotation">'.s("util\\TextUi::switch() rend un réglage binaire qui s'applique immédiatement, sans bouton d'enregistrement : l'attribut data-ajax porte l'adresse à appeler, et post-* la valeur à envoyer. Pour un réglage qui n'a de sens qu'au sein d'un formulaire, c'est un oui / non qu'il faut, par FormUi::yesNo(), pas un interrupteur.").'</p>';
		$h .= '<p class="util-annotation">'.s("Verrouillé, il garde sa forme, ses deux libellés et sa largeur : l'état reste lisible, seul le geste disparaît. Ne jamais le remplacer par un texte, une pastille ou un interrupteur inerte, et ne jamais le masquer à qui voit l'écran. switch() retire alors les attributs d'action : le clic ne peut rien déclencher. La raison du verrou s'écrit sous lui par FormUi::info(), jamais dans un title, qu'aucun écran tactile n'affiche.").'</p>';

		$h .= '<div class="util-overflow-sm">';

			$h .= '<table class="tr-even">';

				$h .= '<thead><tr>';
					$h .= '<th></th>';
					$h .= '<th class="text-center">'.s("Oui").'</th>';
					$h .= '<th class="text-center">'.s("Oui, verrouillé").'</th>';
					$h .= '<th class="text-center">'.s("Non").'</th>';
					$h .= '<th class="text-center">'.s("Non, verrouillé").'</th>';
				$h .= '</tr></thead>';

				foreach([s("Taille normale") => '', s("Petite taille") => 'field-switch-sm'] as $label => $size) {

					$h .= '<tr>';
						$h .= '<td>'.$label.'</td>';

						foreach([[TRUE, FALSE], [TRUE, TRUE], [FALSE, FALSE], [FALSE, TRUE]] as [$on, $disabled]) {

							$h .= '<td class="text-center">';
								$h .= \util\TextUi::switch([
									'class' => $size,
									'disabled' => $disabled,
								], $on, s("En ligne"), s("Hors ligne"));
							$h .= '</td>';

						}

					$h .= '</tr>';

				}

			$h .= '</table>';

		$h .= '</div>';

		$h .= '<div id="convention-sans-title" class="util-block">';
			$h .= \util\TextUi::switch(['disabled' => TRUE], TRUE, s("En ligne"), s("Hors ligne"));
			$h .= \util\FormUi::info(s("Réservé aux administrateurs de la structure."));
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un groupe de champs. Le balisage est celui de `FormUi::fieldset()`, qui rend le fieldset, sa
	 * legend et le conteneur de ses champs.
	 */
	public function fieldsets(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("FormUi::fieldset() rend un fieldset et sa legend ; la classe form-fieldset l'encadre, pour dire que ces champs forment une notion à l'intérieur d'un formulaire plus large. À réserver à un vrai groupe : un champ seul n'a pas besoin d'un cadre, et deux cadres qui se suivent en font un damier. Pour découper un formulaire long en rubriques, prendre FormUi::cards(), présenté juste après.").'</p>';

		$h .= '<div class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs">';
				$h .= $form->group(s("Nom"), $form->text('design-fieldset-name', s("Parcelle du bas")));
				$h .= $form->fieldset(s("Dimensions de l'abri"), $form->group(s("Longueur"), $form->text('design-fieldset-length', '30')).$form->group(s("Largeur"), $form->text('design-fieldset-width', '8')), ['class' => 'form-fieldset']);
				$h .= $form->group(s("Surface"), $form->text('design-fieldset-area', '240'));
			$h .= $form->close();
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("Avec form-fieldset-notched, la légende reste à cheval sur la bordure : le cadre réunit les champs communs d'un formulaire, en tête, au-dessus de ce qu'ils gouvernent. Premier usage, le bloc « Informations générales » de la saisie d'une écriture.").'</p>';

		$h .= '<div class="util-block">';
			$h .= '<form>';
				$h .= $form->fieldset(s("Informations générales"), $form->group(s("Date de l'écriture"), $form->date('design-fieldset-notched-date', '2026-10-12')).$form->group(s("Pièce comptable"), $form->text('design-fieldset-notched-document', 'FA-2026-118')), ['class' => 'form-fieldset form-fieldset-notched']);
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	public function fileButton(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("\\util\\FormUi::fileButton() rend un bouton qui ouvre le choix d'un fichier : le champ de fichier est caché dans son label, et l'envoi se branche sur son onchange, dès que le fichier est choisi. Un fichier qui part avec le reste d'un formulaire garde FormUi::file().").'</p>';

		$h .= '<p>'.$form->fileButton('design-file-button', \Asset::icon('paperclip').' '.s("Joindre un PDF"), ['accept' => 'application/pdf']).'</p>';

		return $h;

	}

	/**
	 * Les champs d'une bulle, deux par ligne.
	 */
	public function fieldsGrid(): string {

		$form = new \util\FormUi(['columns' => 1]);

		$width = fn(string $name, ?int $value) => $form->inputGroup($form->number($name, $value).$form->addon(s("cm")));

		$h = '<p class="util-annotation">'.s("util-fields range les champs d'une bulle ou d'un panneau étroit deux par ligne, le libellé au-dessus, pour qu'un libellé long tienne sans rogner son champ. Chaque champ s'écrit en groupe imbriqué (nested), qui le soustrait aux colonnes du formulaire où la bulle se trouve. util-fields-wide prend la ligne entière, pour une case à cocher, un message ou le bouton qui valide. Premier emploi : les planches d'un jardin qu'on dessine.").'</p>';

		$h .= '<div class="util-block" style="max-width: 20rem">';
			$h .= '<div class="util-fields">';
				$h .= $form->group(s("Longueur"), $form->inputGroup($form->number('design-fields-length', 20).$form->addon(s("m"))), nested: TRUE);
				$h .= $form->group(s("Largeur"), $form->inputGroup($form->number('design-fields-width', 12).$form->addon(s("m"))), nested: TRUE);
				$h .= $form->group(s("Nombre"), $form->number('design-fields-number', 8), nested: TRUE);
				$h .= $form->group(s("Largeur travaillée"), $width('design-fields-bed', 110), nested: TRUE);
				$h .= $form->group(s("Passe-pied"), $width('design-fields-alley', 50), nested: TRUE);
				$h .= '<div class="util-fields-wide">'.$form->button(s("Valider")).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("La tête d'une bande, la bande qui suit le héros et ses chiffres").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-band-head centre le titre d'une bande et son chapô util-band-lead sur la largeur de lecture. util-band-intro est la bande qui suit le héros : elle remonte vers lui, son chapô grossit et s'écarte de ce qui suit. util-figures-band y aligne des chiffres d'affiche sans tuile, util-figure-value au-dessus de util-figure-label. Quatre chiffres passent deux par deux sous 450 px, trois restent sur une ligne.").'</p>';

		// Sans getBand() : son container prend la largeur de la fenêtre, et déborderait de la colonne de la fiche.
		$h .= '<div class="util-band util-band-intro">';
			$h .= \main\DesignUi::getBandHead(s("Ce que disent les structures"), s("Une enquête menée l'hiver dernier auprès des structures qui utilisent le logiciel."));
			$h .= '<div class="util-figures-band">';
				$h .= '<div><div class="util-figure-value">412</div><div class="util-figure-label">'.s("réponses").'</div></div>';
				$h .= '<div><div class="util-figure-value">87 %</div><div class="util-figure-label">'.s("le recommandent").'</div></div>';
				$h .= '<div><div class="util-figure-value">3 h</div><div class="util-figure-label">'.s("gagnées chaque semaine sur la planification et le suivi").'</div></div>';
				$h .= '<div><div class="util-figure-value">64</div><div class="util-figure-label">'.s("départements").'</div></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-band">';
			$h .= '<div class="util-figures-band">';
				$h .= '<div><div class="util-figure-value">2 h</div><div class="util-figure-label">'.s("de formation").'</div></div>';
				$h .= '<div><div class="util-figure-value">3</div><div class="util-figure-label">'.s("modules").'</div></div>';
				$h .= '<div><div class="util-figure-value">VIVEA</div><div class="util-figure-label">'.s("prise en charge possible").'</div></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Des points sur la largeur de lecture, sous une mascotte").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-points range des rangs util-point, une pastille util-point-icon suivie d'un titre et de son texte, séparés par un filet. Le composant ne fixe pas de largeur : util-band-body le pose sur la largeur de lecture, au centre de la bande, comme un formulaire ou une liste sous la tête d'une bande. util-band-mascot pose une mascotte centrée au-dessus de ce qu'elle annonce.").'</p>';
		$h .= '<div class="util-band util-band-alt">';
			$h .= '<div class="util-band-body">';
				$h .= '<ul class="util-points">';
					foreach([
						['person-check', s("Vous choisissez"), s("Rien n'est partagé sans votre accord.")],
						['key', s("Vous gardez la main"), s("Un groupe ne lit que ce que vous lui ouvrez, et il le lit avec votre nom. Vous retirez cet accès quand vous le souhaitez, depuis les réglages de votre structure, sans avoir à prévenir qui que ce soit.")],
						['eye', s("Vous voyez qui lit"), s("La liste des groupes qui lisent vos données reste visible.")],
					] as [$icon, $title, $text]) {
						$h .= '<li class="util-point">';
							$h .= '<div class="util-point-icon">'.\Asset::icon($icon).'</div>';
							$h .= '<div>';
								$h .= '<h3>'.$title.'</h3>';
								$h .= '<p>'.$text.'</p>';
							$h .= '</div>';
						$h .= '</li>';
					}
				$h .= '</ul>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-band">';
			$h .= '<div class="util-band-mascot">'.\Asset::svg('main', 'friends/poule.svg').'</div>';
			$h .= '<div class="util-band-head">';
				$h .= '<h2>'.s("Une question sur vos données ?").'</h2>';
				$h .= '<p class="util-band-lead">'.s("L'équipe répond à chaque message.").'</p>';
			$h .= '</div>';
			$h .= '<div class="flex-buttons flex-justify-center">';
				$h .= '<a href="#" class="btn btn-primary btn-lg">'.s("Nous écrire").'</a>';
				$h .= '<a href="#" class="btn btn-outline-primary btn-lg">'.s("Lire la charte des données").'</a>';
				$h .= '<a href="#" class="btn btn-outline-primary btn-lg">'.s("Consulter la FAQ").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$form = new \util\FormUi();

		$h .= '<h4 class="mt-2">'.s("La signature de la mascotte").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-signature-mascot signe la fin d'un écran court, un mot de passe à retrouver ou une page d'erreur : sous son dernier bloc, la mascotte se décale vers la droite. util-band-mascot la centre au-dessus de ce qu'elle annonce, util-welcome-mascot la pose à côté d'un texte d'accueil.").'</p>';
		$h .= '<div class="util-band">';
			$h .= $form->openAjax('#design-templates');
				$h .= $form->group(s("Votre adresse e-mail"), $form->email('design-signature-email'));
				$h .= $form->group(content: $form->submit(s("Recevoir les instructions par e-mail")));
			$h .= $form->close();
			$h .= '<div class="util-signature-mascot">'.\Asset::svg('main', 'friends/oiseau.svg').'</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un témoignage et une liste cochée").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-testimony donne la parole à un utilisateur : util-testimony-photo, util-testimony-name, util-testimony-subtitle pour ce qu'il fait, puis util-testimony-text. util-check-list énumère quelques points courts dans le texte d'une carte, sous une coche décorative. Un point peut porter du gras ou un lien, il coule comme un texte, sa seconde ligne sous son premier mot. Des critères à vérifier, en bloc, prennent util-procedure-check. --reading-width est la largeur d'un paragraphe à lire d'une traite, que le chapô d'util-page-hero ne dépasse pas.").'</p>';
		$h .= '<div class="util-band util-band-alt">';
			$h .= '<div class="util-layout-aside-start" style="--aside-width: 17rem;">';
				$h .= '<div class="util-layout-aside-side">';
					$h .= '<div class="util-testimony">';
						$h .= \Asset::image('main', 'profile/carotte.jpg', ['class' => 'util-testimony-photo']);
						$h .= '<div class="util-testimony-name">'.s("Claire Martin").'</div>';
						$h .= '<div class="util-testimony-subtitle">'.s("Maraîchage sur 2 hectares").'</div>';
						$h .= '<div class="util-testimony-text">'.s("Nous avons commencé par le plan de culture, un hiver, sans rien changer d'autre. Au printemps, les semis tombaient à la bonne semaine et les commandes de plants partaient d'elles-mêmes. Aujourd'hui toute l'équipe note ses interventions depuis le champ, et la saison se relit en fin d'année.").'</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div>';
					$h .= '<ul class="util-check-list" style="max-width: var(--reading-width);">';
						foreach([
							s("Le plan de culture et l'assolement"),
							s("Les commandes de semences et de plants"),
							s("Le suivi des interventions et du temps de travail, depuis le champ, sur téléphone, même sans réseau"),
							s("Les récoltes"),
							s("La vente au marché"),
							s("La boutique en ligne"),
							s("La facturation et <b>la facture électronique</b>"),
						] as $point) {
							$h .= '<li>'.$point.'</li>';
						}
					$h .= '</ul>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une bande sur un fond de pinceau").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-band posée avec util-brushed garde le fond et le pinceau d'util-brushed, au rythme d'util-band. La teinte se lit dans --util-brushed-color, que la bande ou sa page pose, et vaut le blanc menthe des pages publiques quand rien ne la pose, comme ici. Sur une page publique plus haute que l'écran, la dernière bande, dernier enfant de la page, descend jusqu'au pied, sans laisser voir le fond de la page entre elle et le pinceau du pied.").'</p>';
		$h .= '<div class="util-band util-brushed">';
			$h .= \main\DesignUi::getBrushSeparator('top');
			$h .= '<div class="util-band-head">';
				$h .= '<h2>'.s("Ce que les structures demandent").'</h2>';
				$h .= '<p class="util-band-lead">'.s("Les besoins revenus le plus souvent dans les réponses, du plus cité au moins cité.").'</p>';
			$h .= '</div>';
			$h .= \main\DesignUi::getBrushSeparator('bottom');
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un groupe de boutons radio dont une option est fermée, et le dit.
	 */
	public function radioGroups(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("Une option qu'une règle interdit reste dans la liste, désactivée, et porte sous son libellé la raison de son absence : field-radio-group-reason. Celui qui cherche l'option qu'il croyait avoir apprend pourquoi il ne l'a pas, au lieu de la chercher dans une liste où elle manque, comme le fait déjà une entrée de menu non autorisée qui garde sa place.").'</p>';
		$h .= '<p class="util-annotation">'.s("Seul le libellé s'atténue, jamais le bloc : la raison garderait le cumul et deviendrait le texte le moins lisible de l'écran. Même mise en œuvre que l'entrée de menu désactivée qui porte sa raison. Une option désactivée qui ne donne pas de raison n'est pas concernée et garde le rendu qu'elle a ailleurs.").'</p>';
		$h .= '<p class="util-annotation">'.s("À réserver à une règle que l'utilisateur ne peut pas deviner : une option absente parce qu'elle n'existe pas dans ce contexte n'a pas de raison à donner, elle s'enlève de la liste.").'</p>';

		$h .= '<div class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs">';
				$h .= $form->group(
					s("Régime de TVA"),
					$form->radios('design-radio-reason', [
						'a' => s("Le choix ouvert"),
						'b' => \util\FormUi::radioOption(s("Le choix fermé par une règle"), s("Réservé aux activités agricoles")),
						'c' => \util\FormUi::radioOption(s("Le choix fermé par une date"), s("Supprimé pour les exercices ouverts après le 31/12/2026")),
					], 'a', [
						'required' => TRUE,
						'callbackRadioAttributes' => fn($option, $key) => ($key === 'a') ? [] : ['disabled' => 'disabled'],
					])
				);
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un choix fermé dont les options se reconnaissent à l'œil : l'éditeur d'avatar (`user\UserAvatarUi`).
	 */
	public function choices(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("util-choices range les options d'un choix fermé quand une image les distingue mieux qu'un nom : chaque util-choice est un libellé qui porte le bouton radio, invisible, une vignette de 64px et son nom dessous. L'option cochée prend un fond, et à la souris l'option survolée un fond plus léger. Une option qu'un autre choix interdit se grise sans quitter sa place. util-choice-none tient la place de la vignette pour l'option vide. util-choices-color en fait des pastilles de couleur, cochées par un anneau, sous les vignettes de la même famille ou seules.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le choix s'écrit par FormUi::choices(), qui prend pour chaque valeur son nom et sa vignette, ou NULL pour l'option vide, et les pastilles par FormUi::colorChoices(), qui prend la liste des couleurs. Les deux se posent dans un FormUi::group().").'</p>';

		$h .= '<div class="util-block">';
			$h .= $form->choices('design-choice', [
				'' => ['label' => s("Aucun"), 'image' => NULL],
				'paille' => ['label' => s("Chapeau de paille"), 'image' => \Asset::image('user', 'avatar/couvre-chef-chapeau-paille.png')],
				'bob' => ['label' => s("Bob"), 'image' => \Asset::image('user', 'avatar/couvre-chef-bob.png')],
				'bonnet' => ['label' => s("Bonnet"), 'image' => \Asset::image('user', 'avatar/couvre-chef-bonnet.png'), 'attributes' => ['disabled' => 'disabled']],
			], 'paille');
			$h .= $form->colorChoices('design-choice-color', ['#1d4b53', '#2fbb75', '#8290fd', '#c4363c', '#f39c43'], '#2fbb75');
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("Quand les options forment une échelle, comme une fréquence, une icône dans util-choice-icon tient lieu de vignette, à la taille d'une vignette et sans cadre. Sa couleur peut porter le sens de l'option, ici le grisé des réponses qui disent qu'on n'utilise pas. Premier emploi : l'enquête des adhérents (structure\\SurveyUi).").'</p>';

		$h .= '<div class="util-block">';
			$h .= $form->choices('design-choice-icon', [
				'daily' => ['label' => s("Tous les jours"), 'image' => '<span class="util-choice-icon color-primary">'.\Asset::icon('reception-4').'</span>'],
				'weekly' => ['label' => s("Toutes les semaines"), 'image' => '<span class="util-choice-icon color-primary">'.\Asset::icon('reception-3').'</span>'],
				'occasional' => ['label' => s("De temps en temps"), 'image' => '<span class="util-choice-icon color-primary">'.\Asset::icon('reception-1').'</span>'],
				'never' => ['label' => s("Jamais"), 'image' => '<span class="util-choice-icon color-muted">'.\Asset::icon('x-circle').'</span>'],
			], 'weekly');
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'éditeur qui compose un objet en 3D : l'avatar (`user\UserAvatarUi`), puis les bâtiments du plan de la ferme.
	 * La scène ne se charge pas ici, une vignette tient sa place dans l'aperçu.
	 */
	public function composer(): string {

		$form = new \util\FormUi(['columns' => 1]);

		$headwears = [
			'' => ['label' => s("Aucun"), 'image' => NULL],
			'brousse' => ['label' => s("Chapeau de brousse"), 'image' => \Asset::image('user', 'avatar/couvre-chef-chapeau-brousse.png')],
			'paille' => ['label' => s("Chapeau de paille"), 'image' => \Asset::image('user', 'avatar/couvre-chef-chapeau-paille.png')],
			'hijab' => ['label' => s("Hijab de travail"), 'image' => \Asset::image('user', 'avatar/couvre-chef-hijab-travail.png')],
			'casquette' => ['label' => s("Casquette plate"), 'image' => \Asset::image('user', 'avatar/couvre-chef-casquette-plate.png')],
			'bob' => ['label' => s("Bob"), 'image' => \Asset::image('user', 'avatar/couvre-chef-bob.png')],
			'bonnet' => ['label' => s("Bonnet"), 'image' => \Asset::image('user', 'avatar/couvre-chef-bonnet.png')],
			'beret' => ['label' => s("Béret"), 'image' => \Asset::image('user', 'avatar/couvre-chef-beret.png')],
		];

		$tops = [
			'chemise' => ['label' => s("Chemise à carreaux"), 'image' => \Asset::image('user', 'avatar/haut-chemise-carreaux.png')],
			'polaire' => ['label' => s("Polaire zippée"), 'image' => \Asset::image('user', 'avatar/haut-polaire-zip.png')],
			'sweat' => ['label' => s("Sweat à capuche"), 'image' => \Asset::image('user', 'avatar/haut-sweat-capuche.png')],
			'pull' => ['label' => s("Pull"), 'image' => \Asset::image('user', 'avatar/haut-pull.png')],
			'polo' => ['label' => s("Polo"), 'image' => \Asset::image('user', 'avatar/haut-polo.png')],
		];

		// Assez de teintes pour que la rangée passe à la ligne à toute largeur, et que la pastille ajoutée la suive
		$tints = [
			'#1d4b53', '#2fbb75', '#8290fd', '#c4363c', '#f39c43', '#6b4423', '#d9c6a5', '#3a3a3a', '#f4e9d0',
			'#7a8b3c', '#4f6d8f', '#b5651d', '#e2b13c', '#8e4b6e', '#5c5c5c', '#a7c4a0', '#d97a6c', '#2b2b4f',
		];

		// La pastille que l'éditeur ajoute, ici une couleur libre à choisir, comme celle de l'avatar, qui se reconnait à son
		// cercle chromatique
		$added = fn(string $name) => '<div class="util-choices util-choices-color">'.
			'<label class="util-choice">'.
				$form->inputRadio($name, 'free').
				'<span style="display: grid; place-items: center; background: radial-gradient(circle, var(--background-surface) 0 52%, transparent 55%), conic-gradient(hsl(0 65% 55%), hsl(60 65% 50%), hsl(120 45% 45%), hsl(190 55% 45%), hsl(240 55% 60%), hsl(300 40% 50%), hsl(360 65% 55%));">'.\Asset::icon('plus-lg').'</span>'.
			'</label>'.
		'</div>';

		$h = '<p class="util-annotation">'.s("util-composer pose l'aperçu à gauche, en deux cinquièmes de la largeur, et les choix à droite. util-composer-preview reste en vue pendant que les choix défilent, à un écart sous --util-composer-top : dans un panneau, le script de l'éditeur y pose la hauteur de l'en-tête, collé lui aussi. La scène posée dans l'aperçu, par scene\\SceneUi::getContainer(), y prend toute la largeur sur 360 px de haut, et son canvas le curseur d'une main qui saisit. Sous 768 px, l'aperçu passe au-dessus des choix, sur 220 px, et défile avec eux : collé, il couvrirait les vignettes. Il y reste un repère, où l'éditeur pose dans un coin ce qu'il ajoute à la scène, comme la photo d'un bâtiment. Un aperçu caché par hide, sur l'onglet d'un éditeur qui n'a encore rien à montrer, laisse les choix sur toute la largeur. Premier emploi : l'éditeur d'avatar (user\\UserAvatarUi).").'</p>';
		$h .= '<p class="util-annotation">'.s("util-composer-tints met à plat les rangées de pastilles qu'il contient : FormUi::colorChoices() clôt la sienne, et une pastille que l'éditeur ajoute après elle, comme la couleur libre de l'avatar, suit la dernière teinte et passe à la ligne avec elle. Ses règles propres restent dans la feuille de l'éditeur.").'</p>';

		$h .= '<div class="util-block">';
			$h .= '<div class="util-composer">';

				$h .= '<div class="util-composer-preview">';
					$h .= \Asset::image('user', 'avatar/visage.png');
					$h .= '<a class="btn btn-outline-primary">'.\Asset::icon('shuffle').' '.s("Au hasard").'</a>';
				$h .= '</div>';

				$h .= '<div>';

					$tintsHeadwear = '<div class="util-composer-tints">';
						$tintsHeadwear .= $form->colorChoices('design-composer-headwear-tint', $tints, '#2fbb75');
						$tintsHeadwear .= $added('design-composer-headwear-tint');
					$tintsHeadwear .= '</div>';

					$h .= $form->group(s("Couvre-chef"), $form->choices('design-composer-headwear', $headwears, 'brousse').$tintsHeadwear);

					$tintsTop = '<div class="util-composer-tints">';
						$tintsTop .= $form->colorChoices('design-composer-top-tint', $tints, '#c4363c');
						$tintsTop .= $added('design-composer-top-tint');
					$tintsTop .= '</div>';

					$h .= $form->group(s("Haut"), $form->choices('design-composer-top', $tops, 'chemise').$tintsTop);

				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Deux conventions d'un champ de formulaire, le oui / non et le champ qu'un oui révèle. L'ancre
	 * est celle que nomment les conventions du skill `ui`.
	 */
	public function formFields(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("Un booléen de formulaire se rend en oui / non, jamais en case à cocher seule. FormUi::yesNo() pose les deux réponses côte à côte, et un describer l'obtient par le field yesNo, sans lequel dynamicField() rend une propriété bool en case à cocher. Une liste à choix multiples reste en FormUi::checkboxes(), et un réglage appliqué tout de suite, hors d'un formulaire, reste un interrupteur. Le test dev/test package=design file=BooleanFieldSentinelAi déclare les propriétés bool qu'un champ dynamique rend encore en case à cocher, en nombre qui ne peut que baisser.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un champ qui n'a de sens que si la réponse est oui porte field-toggle, et son formulaire field-toggle-wrapper. Le champ disparaît tant que le oui / non n'est pas sur oui, sans script, par une règle de component.css. Cette règle lit tous les oui / non du conteneur : avec deux oui / non dans le même field-toggle-wrapper, un seul non masque tous les field-toggle. Un conteneur ne porte donc qu'un oui / non. Le champ masqué reste posté, et c'est à l'action d'ignorer sa valeur quand la réponse est non.").'</p>';

		$h .= '<div id="convention-yes-no" class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs field-toggle-wrapper">';
				$h .= $form->group(s("Envoyer un rappel avant l'échéance"), $form->yesNo('design-field-reminder', 1));
				$h .= $form->group(s("Nombre de jours avant l'échéance"), $form->inputGroup($form->number('design-field-reminder-days', 7).$form->addon(s("jours"))), ['class' => 'field-toggle']);
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le partage entre ce qui se dit sous le libellé et ce qui se dit sous le champ. L'ancre est
	 * celle que nomment les conventions du skill `ui`.
	 */
	public function fieldInfo(): string {

		$form = new \util\FormUi();

		$h = '<p class="util-annotation">'.s("Sous le libellé, par labelAfter, ce qui situe le champ : à quoi il sert, où sa valeur se lira, ce qu'elle entraîne. Sous le champ, par after, seulement la consigne de saisie : le format, l'unité, ce qu'un vide veut dire. Les deux passent par FormUi::info(). Un texte qui se lit aussi bien avant de toucher au champ est un cadre, et monte sous le libellé.").'</p>';

		$h .= '<div id="convention-label-after" class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs">';
				$h .= $form->group(
					s("Délai de paiement").\util\FormUi::info(s("Il s'imprime sur chaque facture émise après ce changement.")),
					$form->inputGroup($form->number('design-field-delay', 30).$form->addon(s("jours"))).\util\FormUi::info(s("En jours, de 0 à 90. Laisser vide pour un paiement à réception."))
				);
			$h .= $form->close();
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un geste sous le champ").'</h4>';
		$h .= '<p class="util-annotation">'.s("\\util\\FormUi::getFieldAction() pose sous le champ un lien qui agit sur sa valeur, ouvrir une remise ou déplacer ce qui en dépend, en field-action. Ses gestes sont des boutons btn-xs btn-outline-primary, et deux gestes qui règlent la même valeur partagent le même field-action.").'</p>';

		$h .= '<div class="util-block">';
			$h .= '<form class="form-columns form-columns-33 form-columns-xs">';
				$h .= $form->group(
					s("Prix de vente"),
					$form->inputGroup($form->number('design-field-price', 12.5).$form->addon('€')).
					\util\FormUi::getFieldAction('<a class="btn btn-xs btn-outline-primary">'.\Asset::icon('tags').' '.s("Remiser").'</a> <a class="btn btn-xs btn-outline-primary">'.\Asset::icon('graph-down-arrow').' '.s("Remise sur volume").'</a>')
				);
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	/**
	 * La feuille, montrée ouverte dans un cadre : en vrai elle se pose en position fixe sur toute la
	 * fenêtre, ce qui couvrirait la page du design system.
	 */
	public function sheet(): string {

		$h = '<p class="util-annotation">'.s("util-sheet sert à choisir sans quitter l'écran : elle monte du bas au doigt, et se centre en modale à partir de 768px. Le fond du panneau est opaque et non hérité du contexte. Celui qui l'ouvre bloque le défilement de la page derrière elle, et la ferme au voile (util-sheet-scrim) comme à la croix.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-sheet-grab est la poignée qui dit qu'elle vient du bas, util-sheet-head son titre et sa croix, util-sheet-body la partie qui défile, util-sheet-foot les gestes. La croix fait une cible de --touch-target. Employée par la feuille des formats d'une famille de produits, dans la boutique. La croix seule ferme aussi les fenêtres de saisie d'une durée du planning.").'</p>';

		$h .= '<div class="design-sheet-frame">';
			$h .= '<div class="design-sheet-page">'.s("La page derrière la feuille.").'</div>';
			$h .= '<div class="util-sheet">';
				$h .= '<div class="util-sheet-scrim"></div>';
				$h .= '<div class="util-sheet-panel">';
					$h .= '<div class="util-sheet-grab"></div>';
					$h .= '<div class="util-sheet-head">';
						$h .= '<h4>'.s("Choisir un format").'</h4>';
						$h .= '<a class="util-sheet-close">'.\Asset::icon('x-lg').'</a>';
					$h .= '</div>';
					$h .= '<div class="util-sheet-body">';
						$h .= '<table class="tr-bordered">';
							$h .= '<tr><td>'.s("Poulet prêt à cuire").'</td><td class="text-end">17,00 €</td></tr>';
							$h .= '<tr><td>'.s("Poulet extra gros").'</td><td class="text-end">21,00 €</td></tr>';
							$h .= '<tr><td>'.s("Cuisses, le kg").'</td><td class="text-end">13,00 €</td></tr>';
						$h .= '</table>';
					$h .= '</div>';
					$h .= '<div class="util-sheet-foot">';
						$h .= '<span class="util-annotation">'.s("2 formats choisis").'</span>';
						$h .= '<a class="btn btn-primary">'.s("Terminé").'</a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-2">'.s("util-sheet-inline rend la feuille dans la page, sous ce qui l'ouvre, quand la page n'a pas d'écran à elle : une boutique intégrée dans une iframe qui prend la hauteur de son contenu. Ni voile, ni poignée, ni blocage du défilement ; le panneau prend un filet et se referme à la croix.").'</p>';

		$h .= '<div class="util-sheet util-sheet-inline">';
			$h .= '<div class="util-sheet-scrim"></div>';
			$h .= '<div class="util-sheet-panel">';
				$h .= '<div class="util-sheet-grab"></div>';
				$h .= '<div class="util-sheet-head">';
					$h .= '<h4>'.s("Toutes les dates de vente").'</h4>';
					$h .= '<a class="util-sheet-close">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';
				$h .= '<div class="util-sheet-body">';
					$h .= '<div class="util-rows">';
						$h .= '<div class="util-row"><span>'.s("Mardi 15 septembre").'</span><span>'.s("Jusqu'au lundi 14 septembre à 23:47").'</span></div>';
						$h .= '<div class="util-row"><span>'.s("Mardi 22 septembre").'</span><span>'.s("Ouvre le jeudi 17 septembre à 08:00").'</span></div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte de France, puis le fond d'une carte interactive. Les exemples de carte de France sont
	 * dessinés avec les régions et les écarts des relevés de prix, qui vivent dans le code fermé.
	 */
	public function maps(): string {

		$h = '<p class="util-annotation">'.s("\\main\\FranceUi::getMap() pose une couleur par département et un calque d'étiquettes sur le tracé partagé ; l'écran fournit l'échelle et sa légende. util-map-region efface les frontières de département quand la donnée est régionale, sinon la carte donne à lire un découpage que la couleur ne suit pas.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-map enveloppe le tracé, util-map-labels le calque des étiquettes, util-map-name le nom d'une région et util-map-value son chiffre, util-map-legend et util-map-scale l'échelle en pied de carte. On ne les écrit pas à la main : getMap() les pose.").'</p>';
		$h .= implode('', self::notify('getMaps'));

		$h .= '<h4 class="mt-2">'.s("Fond d'une carte interactive").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-basemap est la vignette qui bascule une carte Mapbox entre la photo satellite et le dessin : util-basemap-image montre l'autre fond, util-basemap-name le nomme. MapCartography la pose en bas à droite comme un contrôle Mapbox, avec l'option basemapSwitch, et en remplit l'aperçu : l'image statique de l'emprise pour le satellite, les formes de la carte sans leurs noms pour le dessin. Elle passe de 5 à 4,5 rem de large sous 768px.").'</p>';

		$drawing = '<svg viewBox="0 0 144 112" width="100%" height="100%" style="background: var(--background-surface)">';
			$drawing .= '<path d="M14,20L130,12L124,98L20,92Z" style="fill: var(--background); stroke: var(--text-intermediate)" stroke-width="0.6"/>';
			$drawing .= '<path d="M28,30L70,27L72,58L30,60Z" style="fill: var(--open-field-background); stroke: var(--open-field)" stroke-width="0.6"/>';
			$drawing .= '<path d="M80,26L116,24L118,52L82,54Z" style="fill: var(--greenhouse-background); stroke: var(--greenhouse)" stroke-width="0.6"/>';
			$drawing .= '<path d="M32,68L108,64L110,84L34,86Z" style="fill: var(--soil)"/>';
		$drawing .= '</svg>';

		$h .= '<div class="design-pair">';
			$h .= '<div>';
				$h .= '<span class="util-label">'.s("Sur le dessin").'</span>';
				$h .= '<button type="button" class="util-basemap">';
					$h .= '<span class="util-basemap-image" style="background-image: url('.\map\MapboxUi::getStaticSatelliteUrl(NULL, [3.0868, 45.6838], 144, 112).')"></span>';
					$h .= '<span class="util-basemap-name">'.s("Satellite").'</span>';
				$h .= '</button>';
			$h .= '</div>';
			$h .= '<div>';
				$h .= '<span class="util-label">'.s("Sur le satellite").'</span>';
				$h .= '<button type="button" class="util-basemap">';
					$h .= '<span class="util-basemap-image">'.$drawing.'</span>';
					$h .= '<span class="util-basemap-name">'.s("Dessin").'</span>';
				$h .= '</button>';
			$h .= '</div>';
		$h .= '</div>';
		$h .= '<p class="util-helper">© Mapbox © OpenStreetMap © Maxar</p>';

		$h .= '<h4 class="mt-2">'.s("Rangée de cases sur une carte").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-mapbar range sur une carte Mapbox les réglages d'une forme qu'on dessine, suivis de ses deux issues, ou les gestes d'un contour désigné. Chaque util-mapbar-item montre son icône au-dessus de son nom, .selected marque la forme qu'on trace et le mode en cours, et .disabled éteint un mode que la forme n'offre pas encore. util-mapbar-primary est l'issue qui enregistre, pleine. Sous 768 px, la rangée d'un tracé ne garde que ses icônes et ses issues passent en boutons sous la carte ; util-mapbar-labels garde les noms d'une rangée courte, et util-mapbar-name nomme au-dessus des gestes ce que la rangée désigne, séparé d'eux par un filet et coupé quand il dépasse leur largeur. La page la pose en haut à droite de la carte pendant un tracé, et au-dessus du contour d'un jardin désigné. util-mapbars pose deux rangées côte à côte, la seconde au bout de la première du côté où la page les ancre, pour qu'elle reste sous le doigt quand les cases de l'autre changent : la paire « Forme libre » et « Rectangle » d'une parcelle ou d'un jardin. Ancrées à gauche, util-mapbars-start met la seconde en tête, et util-mapbars-start-md fait de même sous 768 px seulement, pour des rangées que la page déplace alors vers le bord gauche de la carte. util-mapbars ne prend pas le clic, seules ses rangées le prennent : la carte et ses boutons restent sous la main entre elles et sous la boite qui les porte. Dès que la forme a des gestes à montrer, les deux rangées n'en font qu'une : util-mapbar-choice pose en tête la forme qu'on trace, séparée des gestes par un filet, et ouvre le menu des autres formes. Elle ne se grise pas, util-mapbar-choice-icon porte son icône et le chevron du menu, et util-mapbar-choice-alone retire le chevron et le clic quand le lien ne propose aucune autre forme.").'</p>';

		$item = fn(string $icon, string $label, string $class = '') => '<a class="util-mapbar-item'.$class.'">'.\Asset::icon($icon).'<span>'.$label.'</span></a>';

		foreach(['', ' design-maptool-stage-light'] as $class) {

			$h .= '<div class="design-maptool-stage'.$class.'">';
				$h .= '<div class="util-mapbar util-mapbar-labels">';
					$h .= '<b class="util-mapbar-name">'.s("Jardin des courges").'</b>';
					$h .= $item('pencil', s("Dessiner"));
					$h .= $item('layout-three-columns', s("Planches"));
					$h .= '<a class="util-mapbar-item color-primary">'.\Asset::icon('gear-fill').'</a>';
				$h .= '</div>';
				$h .= '<div class="util-mapbar">';
					$h .= $item('bounding-box', s("Modifier"), ' selected');
					$h .= $item('arrows-move', s("Déplacer"));
					$h .= $item('arrow-clockwise', s("Rotation"));
					$h .= $item('layout-three-columns', s("Planches"));
					$h .= $item('trash', s("Recommencer"));
					$h .= $item('check-lg', s("Enregistrer"), ' util-mapbar-primary');
					$h .= $item('x-lg', s("Annuler"));
				$h .= '</div>';
				$h .= '<div class="util-mapbar">';
					$h .= $item('bounding-box', s("Modifier"), ' selected');
					$h .= $item('arrows-move', s("Déplacer"));
					$h .= $item('arrow-clockwise', s("Rotation"));
					$h .= $item('check-lg', s("Créer le jardin"), ' util-mapbar-primary');
					$h .= $item('x-lg', s("Annuler"));
				$h .= '</div>';
				$h .= '<div class="util-mapbars">';
					$h .= '<div class="util-mapbar">';
						$h .= $item('bounding-box', s("Modifier"), ' selected');
						$h .= $item('arrows-move', s("Déplacer"), ' disabled');
						$h .= $item('arrow-clockwise', s("Rotation"), ' disabled');
					$h .= '</div>';
					$h .= '<div class="util-mapbar">';
						$h .= $item('pentagon', s("Forme libre"));
						$h .= $item('square', s("Rectangle"), ' selected');
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-mapbars util-mapbars-start">';
					$h .= '<div class="util-mapbar">';
						$h .= $item('bounding-box', s("Modifier"), ' selected');
						$h .= $item('arrows-move', s("Déplacer"));
						$h .= $item('arrow-clockwise', s("Rotation"));
						$h .= $item('trash', s("Recommencer"));
					$h .= '</div>';
					$h .= '<div class="util-mapbar">';
						$h .= $item('pentagon', s("Forme libre"), ' selected');
						$h .= $item('square', s("Rectangle"));
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-mapbar">';
					$h .= '<a class="util-mapbar-item util-mapbar-choice" data-dropdown="bottom-start">';
						$h .= '<div class="util-mapbar-choice-icon">'.\Asset::icon('cursor').\Asset::icon('chevron-down').'</div>';
						$h .= '<span>'.s("Parcelle PAC").'</span>';
					$h .= '</a>';
					$h .= '<div class="dropdown-list bg-primary">';
						$h .= '<a class="dropdown-item">'.\Asset::icon('pentagon').' '.s("Forme libre").'</a>';
						$h .= '<a class="dropdown-item">'.\Asset::icon('square').' '.s("Rectangle").'</a>';
					$h .= '</div>';
					$h .= $item('bounding-box', s("Modifier"), ' selected');
					$h .= $item('arrows-move', s("Déplacer"));
					$h .= $item('arrow-clockwise', s("Rotation"));
					$h .= $item('trash', s("Recommencer"));
				$h .= '</div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Outils posés sur une forme dessinée").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-maptool est une pastille de la taille d'un doigt (--touch-target), posée sur la carte par un marqueur. util-maptool-unpoint est la croix cernée de rouge qui retire le point choisi, posée en diagonale de lui. util-maptool-handle est la poignée d'un point, data-handle=\"middle\" celle d'un milieu de côté, qui ajoute un point quand on la tire, ou déplace le bord d'un rang de planches, et .selected le point choisi ou le bord tiré. util-maptool-say est la cote, une longueur ou le refus d'un geste, qui ne prend pas le doigt, util-maptool-say-detail son second mot, posé dessous, comme la culture d'une parcelle PAC sous sa surface, util-maptool-say-above la cote remontée au-dessus du point comme une infobulle, quand le pointeur la masquerait, et util-maptool-say-ok la cote d'une mesure conforme au plan, précédée d'une coche. Les couleurs sont les jetons --maptool-* de reboot.css, qui ne suivent pas le thème parce qu'elles se lisent sur une photo satellite comme sur le plan.").'</p>';
		$h .= '<p class="util-annotation">'.s("Ce dessin vient de l'éditeur de décor de la vue 3D et fait foi. La vue 3D en garde une copie sous #map-scene (map-scene-decor-tool, map-scene-decor-handle et map-scene-decor-say dans scene.css), tenue à la main tant qu'elle n'a pas pris ces classes : un changement fait ici se reporte là-bas.").'</p>';

		// Les deux fonds de la carte : la photo satellite, sombre, puis « Dessin », clair
		foreach(['', ' design-maptool-stage-light'] as $class) {

			$h .= '<div class="design-maptool-stage'.$class.'">';
				$h .= '<a class="util-maptool util-maptool-unpoint"><span>'.\Asset::icon('x-lg').'</span></a>';
				$h .= '<div class="util-maptool-handle"><span></span></div>';
				$h .= '<div class="util-maptool-handle selected"><span></span></div>';
				$h .= '<div class="util-maptool-handle" data-handle="middle"><span></span></div>';
				$h .= '<div class="util-maptool-say">'.s("{value} m", 128).'</div>';
				$h .= '<div class="util-maptool-say">'.s("{value} ha", '3,25').'<span class="util-maptool-say-detail">'.s("Prairie permanente").'</span></div>';
				$h .= '<div class="util-maptool-say util-maptool-say-ok">'.\Asset::icon('check-lg').' '.s("{value} cm", 60).'</div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Forme désignée sur une carte").'</h4>';
		$h .= '<p class="util-annotation">'.s("La parcelle ou le jardin désigné se dessine par-dessus les planches, dans les couches selected-casing et selected-line de MapCartography. Sur la photo satellite le trait est --map-selected, cerné de --map-selected-casing, et se détache des contours voisins par ce cerne et par son épaisseur. Sur le dessin le trait est --map-selected-ink, cerné de --background-surface. Le reste de la carte passe sous un voile de la couleur du cerne, dans la couche selected-veil. Le cyan --maptool-ring, qu'aucun jardin ni abri ne porte, reste à la forme qu'on dessine et aux planches cochées dans une liste, dans les couches drawing-line et bed-selected-line, cerné de --maptool-ink dans les couches drawing-casing et bed-selected-casing pour se lire sur les deux fonds.").'</p>';

		foreach(['', ' design-maptool-stage-light'] as $class) {

			$h .= '<div class="design-maptool-stage'.$class.'">';
				$h .= '<div class="design-map-shape"></div>';
				$h .= '<div class="design-map-shape selected"></div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Contour d'un jardin sur une carte").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un jardin n'a pas d'aplat, qui couvrirait ses planches. Il se lit à son contour, dans les couches plot-casing et plot-line de MapCartography. Sur la photo satellite le trait est --map-plot-line-open-field en plein champ et --map-plot-line-greenhouse sous abri, cerné de --map-plot-casing. Sur le dessin le trait est --open-field ou --greenhouse, sans cerne.").'</p>';

		foreach(['', ' design-maptool-stage-light'] as $class) {

			$h .= '<div class="design-maptool-stage'.$class.'">';
				$h .= '<div class="design-map-plot"></div>';
				$h .= '<div class="design-map-plot greenhouse"></div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Planches sur une carte").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une planche est un aplat bordé d'un filet, dans les couches bed-fill et bed-line de MapCartography, et son nom s'écrit en clair, cerné. Sur la photo satellite l'aplat est --open-field-background à 70 %, qui laisse voir le sol, le filet --map-bed-line et le cerne du nom --text. Sur le dessin l'aplat est --soil, le filet --map-bed-line-ink et le cerne du nom --soil. Sans le filet, deux planches qui se touchent se fondent en une tache.").'</p>';

		foreach(['', ' design-maptool-stage-light'] as $class) {

			$h .= '<div class="design-maptool-stage'.$class.'">';
				$h .= '<div class="design-map-beds">';
					foreach(['1', '2', '3', '4', '5'] as $name) {
						$h .= '<span class="design-map-bed">'.$name.'</span>';
					}
				$h .= '</div>';
			$h .= '</div>';

		}

		return $h;

	}

	public function progress(): string {

		$h = '<p class="util-annotation">'.s("util-progress est une piste, et util-progress-part autant de parts qu'il y a d'états à représenter, chacune avec sa largeur en style inline et sa couleur par une classe bg-*.").'</p>';

		$examples = [
			[s("Tout est conforme"), [['success', 100]], s("9 conformes sur 9")],
			[s("Un point bloque"), [['success', 71.4], ['warning', 14.3], ['danger', 14.3]], s("5 conformes sur 7")],
			[s("Rien n'est commencé"), [], s("0 conforme sur 4")],
		];

		$h .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1.5rem;">';

			foreach($examples as [$label, $parts, $count]) {

				$h .= '<div>';
					$h .= '<div style="display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 0.35rem;">';
						$h .= '<span>'.$label.'</span>';
						$h .= '<span class="util-annotation">'.$count.'</span>';
					$h .= '</div>';
					$h .= '<div class="util-progress">';
						foreach($parts as [$color, $width]) {
							$h .= '<div class="util-progress-part bg-'.$color.'" style="width: '.$width.'%"></div>';
						}
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une répartition comparée à un repère").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-progress-marked enveloppe la barre et un util-progress-marker, la valeur à laquelle se comparent les parts, placée en style inline en pourcentage de la piste comme une part. Le repère peut tomber au-delà des parts.").'</p>';

		$h .= '<div style="max-width: 20rem;">';
			$h .= '<div style="display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 0.35rem;">';
				$h .= '<span>'.s("Interventions").'</span>';
				$h .= '<span class="util-annotation">'.s("2h00 sur une journée de 4h00").'</span>';
			$h .= '</div>';
			$h .= '<div class="util-progress-marked">';
				$h .= '<div class="util-progress">';
					$h .= '<div class="util-progress-part bg-success" style="width: 8.33%"></div>';
					$h .= '<div class="util-progress-part bg-warning" style="width: 8.33%"></div>';
				$h .= '</div>';
				$h .= '<i class="util-progress-marker" style="left: 33.33%"></i>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Montants comparés").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-share range des montants qui se comparent, une util-share-row par montant. Chaque rangée porte util-share-label et son util-share-detail, util-share-value, puis une util-progress util-share-bar dont l'écran calcule les largeurs sur une échelle commune à toutes les rangées, et util-share-legend quand la barre a plusieurs parts.").'</p>';
		$h .= '<p class="util-annotation">'.s("La répartition se conclut par util-share-rest quand une phrase dit ce qui reste, comme dans le dossier de gestion, ou par util-share-total quand la dernière rangée est leur somme, comme dans le prévisionnel financier. Elle porte un util-share-label qui nomme le total et un util-share-value précédé du signe égal, sous un trait court posé sur le seul montant, comme une addition écrite à la main.").'</p>';
		$h .= '<p class="util-annotation">'.s("Quand util-share porte elle-même bg-background, en util-block, la piste vide des barres prend le fond d'une surface, sans quoi elle se perdrait dans le gris du bloc. Une carte util-share blanche posée dans une enveloppe grise garde la piste par défaut. C'est elle qui dit la part d'un petit montant face au plus grand, comme ce qui rentre face à ce qui sort dans une année en perte.").'</p>';

		$row = function(string $label, string $detail, string $value, array $parts): string {

			$h = '<div class="util-share-row">';
				$h .= '<div class="util-share-label">';
					$h .= $label;
					$h .= '<div class="util-share-detail">'.$detail.'</div>';
				$h .= '</div>';
				$h .= '<div class="util-share-value">'.$value.'</div>';
				$h .= '<div class="util-progress util-share-bar">';
					foreach($parts as [$color, $width]) {
						$h .= '<div class="util-progress-part bg-'.$color.'" style="width: '.$width.'%"></div>';
					}
				$h .= '</div>';
				if(count($parts) > 1) {
					$h .= '<div class="util-legend util-share-legend">';
						foreach($parts as [$color, , $legend]) {
							$h .= '<span class="util-legend-item"><i class="util-legend-mark bg-'.$color.'"></i>'.$legend.'</span>';
						}
					$h .= '</div>';
				}
			$h .= '</div>';

			return $h;

		};

		$h .= '<div class="util-card util-share">';
			$h .= $row(s("Ce que l'activité a dégagé"), s("l'EBE avant rémunération de l'exploitant"), \util\TextUi::money(40000, precision: 0), [['secondary', 100]]);
			$h .= $row(s("Ce qui en est sorti"), s("les annuités d'emprunt, puis ce que l'exploitant a reçu"), \util\TextUi::money(30000, precision: 0), [
				['primary', 30, s("Annuités d'emprunt {value}", \util\TextUi::money(12000, precision: 0))],
				['production', 45, s("Prélèvements de l'exploitant {value}", \util\TextUi::money(18000, precision: 0))],
			]);
			$h .= '<p class="util-share-rest">'.s("Il reste {value} pour investir ou faire face aux aléas.", \util\TextUi::money(10000, precision: 0)).'</p>';
		$h .= '</div>';

		$h .= '<div class="util-share util-block bg-background">';
			$h .= $row(s("Ce qui rentre"), s("{value} de ventes", \util\TextUi::money(21000, precision: 0)), '+'.\util\TextUi::money(21000, precision: 0), [['secondary', 50]]);
			$h .= $row(s("Ce qui sort"), s("vos charges, dont la rémunération et les cotisations de l'exploitant"), '-'.\util\TextUi::money(42000, precision: 0), [
				['primary', 35.7, s("Achats {value}", \util\TextUi::money(15000, precision: 0))],
				['production', 19.1, s("Charges externes {value}", \util\TextUi::money(8000, precision: 0))],
				['selling', 21.4, s("Salaires et charges sociales {value}", \util\TextUi::money(9000, precision: 0))],
				['commercialisation', 14.3, s("Rémunération et cotisations de l'exploitant {value}", \util\TextUi::money(6000, precision: 0))],
				['accounting', 7.1, s("Usure du matériel {value}", \util\TextUi::money(3000, precision: 0))],
				['lavande', 2.4, s("Reste des charges {value}", \util\TextUi::money(1000, precision: 0))],
			]);
			$h .= '<div class="util-share-total">';
				$h .= '<div class="util-share-label">'.s("Perte").'</div>';
				$h .= '<div class="util-share-value">= '.\util\TextUi::money(-21000, precision: 0).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function rail(): string {

		$h = '<p class="util-annotation">'.s("util-rail porte un axe (util-rail-track), une ou plusieurs plages (util-rail-range, util-rail-range-light pour une plage d'appoint), un repère (util-rail-marker) et la util-graduation dessous, une case par pas de l'axe. Le placement des plages et du repère appartient à l'écran, en pourcentage de l'axe, comme util-graduation. La couleur des plages vient de l'écran, en currentColor.").'</p>';
		$h .= '<p class="util-annotation">'.s("Sous un rail, la graduation ne s'éclaircit pas quand la place manque. L'écran n'y écrit que les numéros à lire, et util-graduation-mark-key met en gras celui du repère.").'</p>';

		$weeks = function(array $ranges): string {

			$h = '<div class="util-rail">';
				$h .= '<div class="util-rail-track">';
					foreach($ranges as [$first, $last]) {
						$h .= '<i class="util-rail-range" style="left: '.round(($first - 1) / 52 * 100, 3).'%; width: '.round(($last - $first + 1) / 52 * 100, 3).'%"></i>';
					}
				$h .= '</div>';
				$h .= '<div class="util-graduation">';
					for($week = 1; $week <= 52; $week++) {
						if($week % 10 === 0) {
							$h .= '<span class="util-graduation-mark util-graduation-mark-decade">'.$week.'</span>';
						} else if($week % 5 === 0) {
							$h .= '<span class="util-graduation-mark util-graduation-mark-five"></span>';
						} else {
							$h .= '<span class="util-graduation-mark"></span>';
						}
					}
				$h .= '</div>';
			$h .= '</div>';

			return $h;

		};

		$value = '<div class="util-rail">';
			$value .= '<div class="util-rail-track">';
				$value .= '<i class="util-rail-range util-rail-range-light" style="left: 41%; width: 18%"></i>';
				$value .= '<i class="util-rail-range" style="left: 36.5%; width: 27%"></i>';
				$value .= '<i class="util-rail-marker" style="left: 50%"></i>';
			$value .= '</div>';
			$value .= '<div class="util-graduation">';
				$value .= '<span class="util-graduation-mark util-graduation-mark-bound">14 €</span>';
				$value .= '<span class="util-graduation-mark">16 €</span>';
				$value .= '<span class="util-graduation-mark util-graduation-mark-key">18 €</span>';
				$value .= '<span class="util-graduation-mark">20 €</span>';
				$value .= '<span class="util-graduation-mark util-graduation-mark-bound">22 €</span>';
			$value .= '</div>';
		$value .= '</div>';

		$demos = [
			s("Une plage") => $weeks([[10, 45]]),
			s("Plusieurs plages") => $weeks([[10, 26], [33, 45]]),
			s("Une plage d'appoint et un repère") => $value,
		];

		foreach($demos as $label => $demo) {
			$h .= '<h4'.($label === array_key_first($demos) ? '' : ' class="mt-2"').'>'.$label.'</h4>';
			$h .= '<div style="color: var(--primary); max-width: 32rem; margin-bottom: 1.25rem">'.$demo.'</div>';
		}

		$years = [2022 => TRUE, 2023 => FALSE, 2024 => TRUE, 2025 => FALSE, 2026 => TRUE, 2027 => FALSE, 2028 => TRUE];
		$today = 64.3;

		$h .= '<h4 class="mt-2">'.s("Une frise de rails").'</h4>';
		$h .= '<p class="util-annotation">'.s("Une frise aligne un rail par rang sous une graduation commune. La graduation de tête se pose seule dans un util-rail-trackless, qui n'a pas d'axe dont l'écarter. Chaque rang prend un util-rail-flat, sans cadre ni fond, parce que la frise trace la grille de ses pas derrière les rails. La colonne qui nomme les rangs prend --timeline-name-column : un nom tient sur sa ligne, et un nom long passe à la ligne avant de prendre à l'axe la place de ses années. Employé par la liste des emprunts.").'</p>';
		$h .= '<div class="util-table design-frieze" style="--design-frieze-years: '.count($years).'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Emprunt").'</div>';
				$h .= '<div><div class="util-rail util-rail-trackless">';
					$h .= '<div class="util-graduation">';
						foreach($years as $year => $isShown) {
							$h .= '<span class="util-graduation-mark'.($year === 2026 ? ' util-graduation-mark-key' : '').'">'.($isShown ? $year : '').'</span>';
						}
					$h .= '</div>';
				$h .= '</div></div>';
			$h .= '</div>';

			foreach([
				[s("Prêt tracteur"), 0, 85.7, TRUE],
				[s("Prêt bancaire de la serre multichapelle et de son irrigation goutte à goutte"), 14.3, 85.7, TRUE],
				[s("Prêt d'installation envisagé"), 71.4, 28.6, FALSE],
			] as [$name, $left, $width, $isRunning]) {
				$h .= '<div class="util-table-row">';
					$h .= '<div><b>'.$name.'</b></div>';
					$h .= '<div><div class="util-rail util-rail-flat color-accounting">';
						$h .= '<div class="util-rail-track">';
							$h .= '<i class="util-rail-range util-rail-range-light" style="left: '.$left.'%; width: '.$width.'%"></i>';
							if($isRunning) {
								$h .= '<i class="util-rail-range" style="left: '.$left.'%; width: '.round($today - $left, 1).'%"></i>';
							}
							$h .= '<i class="util-rail-marker" style="left: '.$today.'%"></i>';
						$h .= '</div>';
					$h .= '</div></div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public function legend(): string {

		$h = '<p class="util-annotation">'.s("util-legend donne la clé des couleurs d'une grille ou d'un graphique, sur une ligne qui passe à la ligne quand la place manque. Chaque util-legend-item porte une util-legend-mark puis son libellé.").'</p>';
		$h .= '<p class="util-annotation">'.s("La teinte de chaque marque appartient à l'écran, qui la reprend de la règle de la case qu'elle nomme : une teinte copiée à la main dériverait de sa case au premier changement. Une marque peut aussi prendre la forme de ce qu'elle nomme, un ruban de période par exemple, en cumulant util-timeline-event util-timeline-event-period.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une légende n'entre qu'avec l'état qu'elle nomme. Une entrée qui ne correspond à rien à l'écran fait chercher ce qui n'y est pas.").'</p>';

		$h .= '<div class="util-legend mb-2">';
			foreach([[s("Servi"), 'var(--commercialisation)'], [s("En manque"), 'var(--warning)'], [s("Hors saison"), 'var(--muted-light)']] as [$label, $color]) {
				$h .= '<span class="util-legend-item"><i class="util-legend-mark" style="background-color: '.$color.'"></i>'.$label.'</span>';
			}
			$h .= '<span class="util-legend-item"><i class="util-legend-mark util-timeline-event util-timeline-event-period" style="background-color: #3076BD"></i>'.s("Récolte").'</span>';
		$h .= '</div>';

		return $h;

	}

	public function bellows(): string {

		\Asset::js('main', 'bellowsAi.js');

		$h = '<p class="util-annotation">'.s("util-bellows range des plis côte à côte, un seul ouvert : chaque pli fermé est une bande étroite qui porte son nom écrit de bas en haut, et un clic sur une bande y fait glisser le panneau pendant que l'autre se referme, sans que la largeur de l'ensemble bouge. Chaque util-bellows-item porte une util-bellows-band, avec util-bellows-name et, si le pli fermé porte quelque chose, une util-bellows-mark, puis un util-bellows-panel qui commence par util-bellows-title.").'</p>';
		$h .= '<p class="util-annotation">'.s("L'écran rend le pli ouvert avec util-bellows-open et util-bellows-titled, et le geste est celui de MainBellows (main/static/js/bellowsAi.js), délégué sur le document : rien à démarrer, une ligne remplacée par une réponse ciblée garde le geste. Le script se charge par \\Asset::js('main', 'bellowsAi.js') chez chaque appelant. util-bellows-inactive garde un pli en bande et ne l'ouvre pas, un jour à venir par exemple, et sa bande prend le fond que l'écran pose dans --util-bellows-inactive-background, comme la hachure des jours à venir des Récoltes. La bande qui porte le nom prend tabindex=\"0\" pour s'ouvrir au clavier, par Entrée ou Espace ; le focus passe alors au panneau, et la bande ouverte, masquée, sort de la tabulation.").'</p>';
		$h .= '<p class="util-annotation">'.s("Le nom passe de la bande au titre par util-bellows-titled, que MainBellows pose à chaque image du glissement : la course de la bande dépend de la largeur de l'écran, et aucun délai CSS ne tombe au moment où elle ne contient plus le nom.").'</p>';

		$days = [
			1 => [[s("Carotte"), s("12 kg")], [s("Radis"), s("40 bottes")]],
			2 => [],
			3 => [[s("Tomate"), s("8 kg")]],
			4 => [[s("Salade"), s("24 pièces")], [s("Carotte"), s("6 kg")]],
		];

		$h .= '<div class="util-bellows mb-2" style="height: 12rem">';

			foreach(range(1, 7) as $day) {

				$harvests = $days[$day] ?? [];
				$name = \util\DateUi::getDayName($day).' '.[28, 29, 30, 1, 2, 3, 4][$day - 1];

				if($day === 4) {
					$class = ' util-bellows-open util-bellows-titled';
				} else if($day > 4) {
					$class = ' util-bellows-inactive';
				} else {
					$class = '';
				}

				$h .= '<div class="util-bellows-item'.$class.'">';

					$h .= '<div class="util-bellows-band"'.($day > 4 ? '' : ' tabindex="0"').'>';
						if($harvests !== []) {
							$h .= '<span class="util-bellows-mark"></span>';
						}
						$h .= '<span class="util-bellows-name">'.$name.'</span>';
					$h .= '</div>';

					$h .= '<div class="util-bellows-panel">';
						if($day <= 4) {
							$h .= '<div class="util-bellows-title">'.$name.'</div>';
							foreach($harvests as [$plant, $quantity]) {
								$h .= '<div style="display: flex; justify-content: space-between; gap: 1rem"><span>'.$plant.'</span><b>'.$quantity.'</b></div>';
							}
							if($harvests === []) {
								$h .= '<div class="util-empty">'.s("Aucune récolte").'</div>';
							}
						}
					$h .= '</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Dans un tableau").'</h4>';
		$h .= '<p class="util-annotation">'.s("Dans une util-table, chaque pli est la cellule d'une même colonne, et la grille porte util-bellows-columns. La feuille de l'écran écrit la piste du n-ième pli var(--util-bellows-track-<n>), de 1 à 7, et l'écran pose --util-bellows-<n>: 1 sur la grille pour le pli ouvert, que MainBellows déplace ensuite. Toutes les cellules d'une colonne portent util-bellows-item ; seules celles de l'en-tête portent le nom, le titre, util-bellows-titled et le tabindex, celles des lignes une marque et leur contenu.").'</p>';
		$h .= '<p class="util-annotation">'.s("Une transition de grid-template-columns n'interpole que des longueurs, jamais une piste en fr ni un minmax() : les pistes des plis restent des longueurs, et ce sont les autres colonnes qui absorbent le reste. À l'ouverture d'un pli, l'événement bellowsOpen remonte depuis sa cellule d'en-tête, pour l'écran qui doit suivre le pli ouvert (un lien, un champ qu'il réémet).").'</p>';

		$rows = [
			s("Carotte") => [s("12 kg"), '', s("6 kg")],
			s("Tomate") => ['', s("8 kg"), ''],
		];

		$h .= '<div class="util-table util-table-even util-bellows-columns mb-2" style="grid-template-columns: minmax(8rem, 1fr) var(--util-bellows-track-1) var(--util-bellows-track-2) var(--util-bellows-track-3); --util-bellows-3: 1">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Série").'</div>';
				foreach(range(1, 3) as $day) {
					$name = \util\DateUi::getDayName($day).' '.[28, 29, 30, 1, 2, 3, 4][$day - 1];
					$h .= '<div class="util-bellows-item'.($day === 3 ? ' util-bellows-open util-bellows-titled' : '').'">';
						$h .= '<div class="util-bellows-band" tabindex="0"><span class="util-bellows-name">'.$name.'</span></div>';
						$h .= '<div class="util-bellows-panel text-end"><div class="util-bellows-title">'.$name.'</div></div>';
					$h .= '</div>';
				}
			$h .= '</div>';

			foreach($rows as $plant => $quantities) {
				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$plant.'</div>';
					foreach($quantities as $index => $quantity) {
						$h .= '<div class="util-bellows-item'.($index === 2 ? ' util-bellows-open' : '').'">';
							$h .= '<div class="util-bellows-band">'.($quantity === '' ? '' : '<span class="util-bellows-mark" title="'.$quantity.'"></span>').'</div>';
							$h .= '<div class="util-bellows-panel text-end">'.$quantity.'</div>';
						$h .= '</div>';
					}
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public function formats(): string {

		$h = '<p class="util-annotation">'.s("Un montant s'écrit par \\util\\TextUi::money() et un pourcentage par \\util\\TextUi::pc(), jamais à la main : la langue fixe l'espace des milliers et la place du symbole, et le même montant se lit pareil sur tous les écrans.").'</p>';

		$h .= '<table class="tr-even">';
			$h .= '<thead><tr><th>'.s("Appel").'</th><th class="text-end">'.s("Rendu").'</th></tr></thead>';
			$h .= '<tbody>';
				$h .= '<tr><td><code>money(1240.5)</code></td><td class="text-end">'.\util\TextUi::money(1240.5).'</td></tr>';
				$h .= '<tr><td><code>money(0.0125)</code></td><td class="text-end">'.\util\TextUi::money(0.0125).'</td></tr>';
				$h .= '<tr><td><code>money(1240.38, precision: 0)</code></td><td class="text-end">'.\util\TextUi::money(1240.38, precision: 0).'</td></tr>';
				$h .= '<tr><td><code>money(1240.5, precision: 0)</code></td><td class="text-end">'.\util\TextUi::money(1240.5, precision: 0).'</td></tr>';
				$h .= '<tr><td><code>pc(37.6)</code></td><td class="text-end">'.\util\TextUi::pc(37.6).'</td></tr>';
				$h .= '<tr><td><code>pc(12.34, 1)</code></td><td class="text-end">'.\util\TextUi::pc(12.34, 1).'</td></tr>';
				$h .= '<tr><td><code>pc(0.2)</code></td><td class="text-end">'.\util\TextUi::pc(0.2).'</td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function bars(): string {

		$h = '<p class="util-annotation">'.s("util-bars dessine une quantité mois par mois en petites barres, pour comparer d'un coup d'œil la forme de l'année de plusieurs lignes. util-bars-axis écrit dessous les initiales des mois, ou une seule fois en tête de colonne quand les barres sont dans un tableau.").'</p>';
		$h .= '<p class="util-annotation">'.s("Chaque barre est un enfant qui porte --bar, de 0 à 1, calculé par l'écran à l'échelle de sa ligne : le mois le plus chargé de la ligne va en haut, et deux lignes se comparent par leur forme, pas par leur hauteur. --short, de 0 à 1 aussi, peint en orange le haut de la barre, la part qui pose un problème. La couleur des barres vient de l'écran, en currentColor. Aucune valeur ne se lit au survol, qu'un écran tactile n'offre pas, et l'échelle s'écrit en clair par util-bars-max.").'</p>';
		$h .= '<p class="util-annotation">'.s("Douze colonnes par défaut. --bars en change le nombre, sur util-bars et util-bars-axis ensemble. Un trait fin sépare deux barres, pour que les mois se comptent sans l'axe.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-bars-max, posé juste au-dessus de util-bars, donne l'échelle du diagramme. Il écrit la valeur du mois le plus chargé, dans l'unité de la ligne, et tire un trait pointillé à la hauteur que cette valeur atteint. Sans lui, la hauteur d'une barre ne dit qu'une proportion.").'</p>';

		$axis = '<div class="util-bars-axis">';
			foreach(range(1, 12) as $month) {
				$axis .= '<span>'.mb_strtoupper(mb_substr(\util\DateUi::getMonthName($month), 0, 1)).'</span>';
			}
		$axis .= '</div>';

		$lines = [
			s("Carotte") => [s("337 kg"), [[0.4, 0], [0.5, 0], [0.3, 0], [0, 0], [0, 0], [0.6, 0], [0.8, 0], [1, 0], [0.9, 0.5], [0.7, 0], [0.6, 0], [0.5, 0]]],
			s("Tomate") => [s("120 kg"), [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0.2, 0], [0.7, 0], [1, 0.25], [0.8, 0], [0.3, 0], [0, 0], [0, 0]]],
		];

		foreach($lines as $name => [$max, $months]) {

			$h .= '<div style="display: grid; grid-template-columns: 6rem 10rem; gap: 1rem; align-items: end; margin-bottom: 0.75rem">';
				$h .= '<div>'.$name.'</div>';
				$h .= '<div>';
					$h .= '<div class="util-bars-max">'.$max.'</div>';
					$h .= '<div class="util-bars" style="color: var(--commercialisation)">';
						foreach($months as [$bar, $short]) {
							$h .= '<span style="--bar: '.$bar.($short > 0 ? '; --short: '.$short : '').'"></span>';
						}
					$h .= '</div>';
					$h .= $axis;
				$h .= '</div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Une seconde nature de la même quantité").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-bars-light, posé avec util-bars, garde à la part --short la teinte de la barre, éclaircie : elle ne signale plus un manque, elle distingue une seconde nature de la même quantité, comme les mois où une espèce ne se livre que conservée.").'</p>';

		$h .= '<div style="display: grid; grid-template-columns: 6rem 10rem; gap: 1rem; align-items: end; margin-bottom: 0.75rem">';
			$h .= '<div>'.s("Courge").'</div>';
			$h .= '<div>';
				$h .= '<div class="util-bars util-bars-light" style="color: var(--production)">';
					foreach([[1, 1], [1, 1], [0.5, 1], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0.5, 0], [1, 0], [1, 0.5], [1, 1]] as [$bar, $short]) {
						$h .= '<span style="--bar: '.$bar.($short > 0 ? '; --short: '.$short : '').'"></span>';
					}
				$h .= '</div>';
				$h .= $axis;
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une quantité qui change de signe").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-bars-signed dessine une quantité qui change de signe, un solde, de part et d'autre d'une ligne zéro tirée au milieu. Chaque mois porte --up ou --down, de 0 à 1, la part de la demi-hauteur au-dessus ou au-dessous. Une seule échelle vaut pour les deux sens, celle du mois le plus éloigné de zéro, écrite par util-bars-max. Au-dessus, la barre prend la couleur posée par l'écran ; au-dessous, l'orange d'alerte, parce qu'un solde qui passe sous zéro est ce qu'on vient y chercher. La clôture comptable l'emploie pour le solde le plus bas de chaque mois d'un compte courant d'associé.").'</p>';

		$h .= '<div style="display: grid; grid-template-columns: 6rem 10rem; gap: 1rem; align-items: end; margin-bottom: 0.75rem">';
			$h .= '<div>'.s("Compte courant").'</div>';
			$h .= '<div>';
				$h .= '<div class="util-bars-max">'.s("4 800 €").'</div>';
				$h .= '<div class="util-bars-signed" style="color: var(--accounting)">';
					foreach([[0.6, 0], [0.4, 0], [0, 0.3], [0, 1], [0, 0.8], [0.1, 0], [0.5, 0], [0.7, 0], [0.7, 0], [0, 0.1], [0.3, 0], [0.9, 0]] as [$up, $down]) {
						$h .= '<span style="--up: '.$up.'; --down: '.$down.'"></span>';
					}
				$h .= '</div>';
				$h .= $axis;
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Chaque période face à l'an dernier").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-bars-before, posé avec util-bars, compare chaque période à la même période de l'an dernier. Chaque colonne porte --bar et --before, tous deux en part du maximum des deux séries. L'an dernier se dessine en clair sur toute la colonne, la période en cours sur sa moitié centrale devant lui. Une colonne sans --before n'a pas de retrait, comme les premières semaines d'une structure qui ne vendait pas encore l'an dernier. Le graphique ne se légende pas : l'écart sur l'an dernier s'écrit à côté, en util-figure-variation.").'</p>';
		$h .= '<p class="util-annotation">'.s("La teinte du retrait est --bars-before-tint, une part de la couleur des barres. Dans un util-block-dark, les barres passent au blanc et le retrait avec elles.").'</p>';

		$weeks = [[0.61, 0.8], [0.85, 0.98], [0.76, 0.83], [0.76, 0.66], [0.61, 0.68], [0.71, 0.68], [0.83, 0.71], [0.8, 0.73], [0.76, 0.85], [0.9, 0.83], [0.71, 0.98], [0.63, 1], [0.56, 1]];
		$weeksRecent = [[0.61, NULL], [0.85, NULL], [0.76, NULL], [0.76, NULL], [0.61, NULL], [0.71, 0.68], [0.83, 0.71], [0.8, 0.73], [0.76, 0.85], [0.9, 0.83], [0.71, 0.98], [0.63, 1], [0.56, 1]];

		foreach([
			[s("Ventes livrées"), $weeks],
			[s("Depuis août 2025"), $weeksRecent],
		] as [$name, $columns]) {

			$h .= '<div style="display: grid; grid-template-columns: 6rem 14rem; gap: var(--space-md); align-items: end; margin-bottom: var(--space-sm)">';
				$h .= '<div>'.$name.'</div>';
				$h .= $this->getBarsBefore($columns, 'var(--color-brand)');
			$h .= '</div>';

		}

		$h .= '<div class="util-block util-block-dark bg-primary mt-1">';
			$h .= '<div style="display: grid; grid-template-columns: 6rem 14rem; gap: var(--space-md); align-items: end">';
				$h .= '<div>'.s("Ventes livrées").'</div>';
				$h .= $this->getBarsBefore($weeks, NULL);
			$h .= '</div>';
		$h .= '</div>';

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');
		\Asset::css('main', 'chart.css');
		\Asset::js('main', 'chart.js');

		$h .= '<h4 id="design-comparison" class="mt-2">'.s("Des modalités comparées à une date").'</h4>';
		$h .= '<p class="util-annotation">'.s("Pour comparer des modalités à une date, UtilChart.createComparison() trace une barre par modalité, d'une seule couleur, dans l'ordre que l'écran donne, sans légende, l'axe des valeurs partant de zéro. Quand la place d'une barre devient plus étroite que le plus long mot des libellés, le graphique se couche : les modalités se lisent de haut en bas, toutes nommées, et le cadre analyze-bar grandit d'une rangée par modalité. Debout, un libellé passe à la ligne entre deux mots quand il dépasse sa barre : une espace insécable garde une quantité avec son unité. Pour suivre des séries dans le temps, c'est UtilChart.createLines(), qui trace les huit premières et barre les suivantes dans la légende.").'</p>';

		$values = [2.4, 3.2, 3.7, 3.0, 2.7, 3.4, 3.2, 2.6, 2.9, 4.0];
		$labels = [s("Témoin sans apport"), s("Compost 20\u{00A0}t"), s("Compost 40\u{00A0}t"), s("Fumier 30\u{00A0}t"), s("Engrais vert seigle"), s("Engrais vert féverole"), s("Bouchons 1\u{00A0}t"), s("Paillage BRF"), s("Paillage foin"), s("Compost + paillage")];

		$h .= '<div class="analyze-bar">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createComparison(this, '.json_encode($values).', '.json_encode($labels).', "kg/m²")').'></canvas>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Le tableau à côté de son graphique").'</h4>';
		$h .= '<p class="util-annotation">'.s("analyze-chart-table met les chiffres et leur graphique côte à côte à partir de 768 px, le tableau analyze-values sur trois cinquièmes et le cadre analyze-bar sur deux, puis les empile en dessous, le tableau d'abord.").'</p>';

		$values = [3.2, 3.7, 3.0, 2.4];
		$labels = [s("Compost 20\u{00A0}t"), s("Compost 40\u{00A0}t"), s("Fumier 30\u{00A0}t"), s("Témoin sans apport")];

		$h .= '<div class="analyze-chart-table">';
			$h .= '<div class="util-overflow-xs">';
				$h .= '<table class="tr-even analyze-values">';
					$h .= '<thead><tr><th>'.s("Modalité").'</th><th class="text-end">'.s("Rendement").'</th></tr></thead>';
					$h .= '<tbody>';
						foreach($values as $position => $value) {
							$h .= '<tr><td>'.$labels[$position].'</td><td class="text-end">'.s("{value} kg/m²", ['value' => \util\TextUi::number($value, 1)]).'</td></tr>';
						}
					$h .= '</tbody>';
				$h .= '</table>';
			$h .= '</div>';
			$h .= '<div class="analyze-bar">';
				$h .= '<canvas '.attr('onrender', 'UtilChart.createComparison(this, '.json_encode($values).', '.json_encode($labels).', "kg/m²")').'></canvas>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function heat(): string {

		$h = '<p class="util-annotation">'.s("util-heat : le fond d'une cellule est proportionnel à une seule variable posée dessus, --heat de 0 à 1. util-heat-strong repasse le texte en blanc au-delà de 0.95 seulement : mesuré sur ce dégradé, le texte foncé contraste mieux que le blanc partout en dessous.").'</p>';
		$h .= '<p class="util-annotation">'.s("À poser sur un tableau table-unstyled : le fond par défaut des cellules (reboot.css:397) a une spécificité supérieure à une classe seule et gagnerait sans cela, et le zebra se disputerait de toute façon le fond avec le dégradé. util-heat-table rend alors au tableau le fond, le rayon, l'ombre et le filet de thead que table-unstyled lui retire (reboot.css:361), @media screen compris ; à l'impression la matrice perd son habillage comme tous les autres tableaux du produit. Dans un util-overflow, pour que la matrice défile à toutes les largeurs et pas seulement sous 450 px.").'</p>';

		$h .= '<div class="util-overflow-xs">';
			$h .= '<table class="table-unstyled util-heat-table">';
				$h .= '<thead><tr><th>'.s("Cohorte").'</th>';
				for($age = 0; $age <= 5; $age++) {
					$h .= '<th class="text-end">'.s("T+{age}", ['age' => $age]).'</th>';
				}
				$h .= '</tr></thead>';
				$h .= '<tbody>';

				foreach([[45, 28, 24, 22, 23, 22], [58, 31, 26, 25, 24, NULL], [38, 22, 19, 18, NULL, NULL]] as $index => $values) {

					$h .= '<tr><td><b>'.s("2026T{quarter}", ['quarter' => 3 - $index]).'</b></td>';

					foreach($values as $value) {

						if($value === NULL) {
							$h .= '<td></td>';
							continue;
						}

						$heat = round(min(1, $value / 55), 2);

						$h .= '<td class="text-end util-heat'.(($heat > 0.95) ? ' util-heat-strong' : '').'" style="--heat: '.$heat.'">'.$value.'</td>';

					}

					$h .= '</tr>';

				}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Colonne survolée").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-column-hover teinte toute une colonne pendant qu'on la survole, pour lire une semaine ou une période d'un bout à l'autre de la grille. La teinte est la marque à 6 %, le jeton --background-column-hover.").'</p>';
		$h .= '<p class="util-annotation">'.s("Elle se pose sur chaque cellule de la colonne, en pratique par JS : aucun sélecteur CSS ne relie les cellules d'une même colonne. Elle est rendue en background-image, par-dessus le fond que la cellule porte déjà : le dégradé d'util-heat reste lisible dessous, sans conflit de spécificité avec lui ni avec l'alternance tr-even.").'</p>';

		$h .= '<div class="util-overflow-xs">';
			$h .= '<table class="table-unstyled util-heat-table">';
				$h .= '<thead><tr><th>'.s("Espèce").'</th>';
				for($week = 20; $week <= 25; $week++) {
					$h .= '<th class="text-center'.(($week === 22) ? ' util-column-hover' : '').'">'.$week.'</th>';
				}
				$h .= '</tr></thead>';
				$h .= '<tbody>';

				foreach([s("Laitue") => [1, 0.3, 1, 0.3, 1, 0.3], s("Radis") => [0.2, 1, 0.2, 0.2, 1, 0.2], s("Courgette") => [0, 0, 0.25, 1, 0.25, 1]] as $plant => $heats) {

					$h .= '<tr><td>'.$plant.'</td>';

					foreach($heats as $index => $heat) {

						$h .= '<td class="util-heat'.(($index === 2) ? ' util-column-hover' : '').'" style="--heat: '.$heat.'"></td>';

					}

					$h .= '</tr>';

				}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	public function tabs(): string {

		$h = '<p class="util-annotation">'.s("\\main\\DesignUi::getTabItem() rend un onglet qui ouvre, par Lime.Tab, le tab-panel de même data-tab. Son libellé est du HTML, pour une icône ou un compteur.").'</p>';

		$h .= '<div class="tabs">';
			$h .= '<div class="tabs-item">';
				$h .= \main\DesignUi::getTabItem('design-tabs-day', s("Par jour"), TRUE);
				$h .= \main\DesignUi::getTabItem('design-tabs-hour', s("Par heure"));
			$h .= '</div>';
			$h .= '<div class="tab-panel selected" data-tab="design-tabs-day"><p>'.s("Le panneau des jours.").'</p></div>';
			$h .= '<div class="tab-panel" data-tab="design-tabs-hour"><p>'.s("Le panneau des heures.").'</p></div>';
		$h .= '</div>';

		$h .= '<div class="tabs">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected">'.s("Pages").' <span class="util-counter util-counter-solid">7</span></a>';
				$h .= '<a class="tab-item">'.s("Menu").' <span class="util-counter util-counter-solid">3</span></a>';
				$h .= '<a class="tab-item">'.s("Actualités").' <span class="util-counter util-counter-solid"></span></a>';
				$h .= '<a class="tab-item tab-item-end">'.s("Personnalisation").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une icône devant le libellé").'</h4>';
		$h .= '<p class="util-annotation">'.s("tab-item-icon se pose sur l'icône ou le logo qui précède le libellé d'un onglet, et l'en décolle : un onglet est en flex, l'espace du texte n'y compte pas.").'</p>';

		$h .= '<div class="tabs">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected">'.\Asset::icon('stripe', ['class' => 'tab-item-icon']).s("Stripe").'</a>';
				$h .= '<a class="tab-item">'.\Asset::icon('credit-card-2-front', ['class' => 'tab-item-icon']).s("Carte bancaire").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("La navigation principale d'un écran").'</h4>';
		$h .= '<p class="util-annotation">'.s("tabs-lg grossit les onglets : à réserver aux barres qui portent la navigation principale d'un écran, et non un sous-découpage à l'intérieur d'une page.").'</p>';

		$h .= '<div class="tabs tabs-lg">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item">'.s("Les prix du moment").'</a>';
				$h .= '<a class="tab-item selected">'.s("Saisir mes prix").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 id="design-tabs-page" class="mt-2">'.s("Les onglets de tête d'un écran").'</h4>';
		$h .= '<p class="util-annotation">'.s("\\main\\DesignUi::getPageTabs() rend en tabs-page les onglets de tête d'un écran, ceux qui en ouvrent les vues. L'onglet choisi prend l'encre de l'univers et un trait sur toute sa largeur, à toutes les largeurs d'écran. Sur le gabarit d'une structure, au bureau, les onglets se posent dans la tête de l'écran, sous le titre, sur un filet qui la traverse. Ils n'y montent que s'ils sont le premier contenu de l'écran. Après un encart, dans un panneau, à la tablette et au téléphone, ils restent dans le contenu, comme dans le dernier cadre. La rangée tabs-page-head porte à droite des onglets les outils de l'écran, qui restent dans le contenu et passent à la ligne quand la place manque. La rangée laisse un rem de plus que l'écart habituel avant le contenu qu'elle ouvre. Sans outil, comme dans le deuxième cadre, c'est le seul écart qu'elle garde sous la tête. Le même cadre passe fold au helper : des onglets qui ne tiennent pas dans la largeur se replient en un menu, au lieu de défiler de côté. Un libellé sur deux lignes tient dans la hauteur d'un onglet. Un onglet ouvre un menu par data-dropdown, et il est choisi quand l'écran affiché est une entrée de ce menu. Il ne se replie jamais : il reste à droite du repli, qui range les autres. Les panneaux et les sous-onglets tabs-sub de l'onglet choisi se passent au helper, qui les garde dans le .tabs, sous la rangée, où Lime.Tab les cherche.").'</p>';

		$pageTabs = fn(int $selected) => implode('', array_map(
			fn(int $position, string $label) => '<a class="tab-item'.($position === $selected ? ' selected' : '').'">'.$label.'</a>',
			[0, 1, 2],
			[s("Interventions"), s("Récoltes"), s("Équipe")]
		));

		$pageMenu = fn(bool $selected) => '<a data-dropdown="bottom-end" class="tab-item'.($selected ? ' selected' : '').'">'.\Asset::icon('three-dots-vertical').'</a>'.
			'<div class="dropdown-list">'.
				'<a class="dropdown-item'.($selected ? ' selected' : '').'">'.s("Répartir le travail sur la semaine").'</a>'.
				'<a class="dropdown-item">'.s("Personnaliser le planning").'</a>'.
			'</div>';

		$h .= '<div class="design-paper-frame design-tabs-page-frame mb-2">';
			$h .= '<div class="util-paper">';
				$h .= '<div class="structure-template-main-title">'.\main\DesignUi::getPageHeader(s("Plan de culture")).'</div>';
				$h .= '<div class="structure-template-main-container">';

					$h .= \main\DesignUi::getPageTabs(
						$pageTabs(2).$pageMenu(FALSE),
						tools: \main\OmniboxUi::get(placeholder: s("Espèce, variété, matériel...")),
						panels: '<div class="tab-panel selected">'.
							'<div class="tabs">'.
								'<div class="tabs-item tabs-sub">'.
									'<a class="tab-item selected">'.s("Tous").'</a>'.
									'<a class="tab-item">'.s("Camille").'</a>'.
									'<a class="tab-item">'.s("Jean-Baptiste").'</a>'.
								'</div>'.
							'</div>'.
						'</div>'
					);

					$h .= '<div class="util-block">'.s("Le contenu de l'écran").'</div>';

				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="design-paper-frame design-tabs-page-frame mb-2">';
			$h .= '<div class="util-paper">';
				$h .= '<div class="structure-template-main-title">'.\main\DesignUi::getPageHeader(s("Analyser les cultures")).'</div>';
				$h .= '<div class="structure-template-main-container">';

					$h .= \main\DesignUi::getPageTabs(
						'<a class="tab-item selected">'.s("Temps de travail à la ferme").'</a>'.
						'<a class="tab-item"><div>'.s("Temps de travail de l'équipe").'<span class="tab-item-detail">'.s("(par personne)").'</span></div></a>'.
						'<a class="tab-item">'.s("Saisonnalité du travail").'</a>'.
						'<a class="tab-item">'.s("Comparaison des périodes").'</a>'.
						$pageMenu(FALSE),
						fold: TRUE
					);

					$h .= '<div class="util-block">'.s("Le contenu de l'écran").'</div>';

				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="design-paper-frame">';
			$h .= '<div class="util-paper structure-template-main-container">';

				$h .= '<div class="util-block-info">'.s("Un encart posé avant les onglets").'</div>';

				$h .= \main\DesignUi::getPageTabs(
					$pageTabs(-1).$pageMenu(TRUE),
					tools: '<a class="btn btn-secondary dropdown-toggle">'.s("Parcelle Les Grandes Terres du Haut").'</a>'
				);

				$h .= '<div class="util-block">'.s("Le contenu de l'écran").'</div>';

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("Une rangée qui défile de côté ramène l'onglet choisi dans la vue à chaque rendu, par Lime.Tab.reveal(), sans faire défiler la page. Au téléphone, le dernier cadre s'ouvre ainsi sur son menu choisi, au bout de la rangée.").'</p>';

		$h .= '<h4 id="design-tabs-page-fold" class="mt-2">'.s("Des onglets nombreux, repliés en menu").'</h4>';
		$h .= '<p class="util-annotation">'.s("fold vaut pour toute rangée d'onglets qui peut dépasser la largeur d'un téléphone. Il s'impose à celle dont la liste vient de la base, un onglet par catalogue, par catégorie de produits, par matériel ou par journal, qui grandit sans borne. Quand les onglets ne tiennent plus dans la largeur, ils se replient en un seul onglet, l'onglet choisi précédé de l'icône de liste et suivi d'un chevron, qui ouvre le menu de tous les autres. Ils se déplient dès qu'ils tiennent de nouveau. L'onglet replié ne dépasse jamais la rangée, un nom trop long s'y coupe et l'icône et le chevron restent, avec le compteur quand le nom porte tab-item-name.").'</p>';

		$h .= '<p class="util-annotation">'.s("tab-item-detail porte le second libellé d'un onglet, sous son nom et d'une encre plus pâle, comme le type d'un catalogue. Le menu le reprend à la suite du nom, l'onglet replié ne garde que le nom et le compteur. tab-item-vignette est la place de la vignette d'un onglet, gardée vide par un onglet qui n'en a pas, pour que les noms s'alignent dans le menu. L'onglet replié n'en montre aucune, l'icône du menu tenant déjà cette place. tab-item-name enveloppe le nom, pour que l'onglet replié le coupe seul et garde le compteur.").'</p>';

		$catalogTabs = '';

		foreach([
			[s("Marché de Clermont-Ferrand le samedi"), 22, s("Particulier")],
			[s("Paniers"), 42, s("Particulier")],
			[s("Plants"), 39, s("Particulier")],
			[s("Vente directe"), 34, s("Particulier")],
		] as $position => [$name, $products, $type]) {
			$catalogTabs .= '<a class="tab-item'.($position === 0 ? ' selected' : '').'"><div><span class="tab-item-name">'.$name.'</span> <small class="util-counter util-counter-solid">'.$products.'</small><span class="tab-item-detail">'.$type.'</span></div></a>';
		}

		$categoryTabs = '';

		foreach([
			['legume', s("Légumes"), 128],
			['fruit', s("Fruits"), 14],
			['plant', s("Plants"), 53],
			[NULL, s("Non catégorisé"), 3],
		] as $position => [$icon, $name, $products]) {
			$vignette = ($icon === NULL) ? '' : \product\CategoryUi::getVignette(new \product\Category(['icon' => $icon]), '2rem');
			$categoryTabs .= '<a class="tab-item'.($position === 2 ? ' selected' : '').'"><span class="tab-item-vignette">'.$vignette.'</span><span class="tab-item-name">'.$name.'</span> <small class="util-counter util-counter-solid">'.$products.'</small></a>';
		}

		foreach([
			[s("Catalogues de produits"), $catalogTabs],
			[s("Produits"), $categoryTabs],
		] as [$title, $tabs]) {

			$h .= '<div class="design-paper-frame design-tabs-page-frame design-tabs-page-frame-narrow mb-2">';
				$h .= '<div class="util-paper">';
					$h .= '<div class="structure-template-main-title">'.\main\DesignUi::getPageHeader($title).'</div>';
					$h .= '<div class="structure-template-main-container">';

						$h .= \main\DesignUi::getPageTabs($tabs, fold: TRUE);

						$h .= '<div class="util-block">'.s("Le contenu de l'écran").'</div>';

					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		}

		$h .= '<h4 class="mt-2">'.s("Deux chemins exclusifs d'un formulaire").'</h4>';
		$h .= '<p class="util-annotation">'.s("tabs-form donne aux onglets la taille d'un formulaire, et tabs-exclusive sur le .tabs dit à Lime.Tab de désactiver le fieldset des panneaux non choisis : seuls les champs du chemin retenu sont postés. Employé pour ajouter une variante, où l'on crée un produit ou l'on en rattache un.").'</p>';

		$h .= '<div class="tabs tabs-exclusive">';
			$h .= '<div class="tabs-item tabs-form">';
				$h .= '<a class="tab-item selected">'.s("Nouveau produit").'</a>';
				$h .= '<a class="tab-item">'.s("Produit existant").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Des sous-onglets sous l'onglet choisi").'</h4>';
		$h .= '<p class="util-annotation">'.s("tabs-sub est un sous-onglet : une seconde rangée qui découpe l'onglet de base choisi au-dessus. Il ne s'emploie jamais seul, toujours dans le tab-panel d'une rangée d'onglets de base dont un onglet est actif : sans elle, rien ne dit de quoi il est la partie. L'état choisi y est un fond et non le trait, pour que les deux niveaux ne se lisent pas comme un seul. Une bascule entre deux vues d'un même bloc reste un tabs-sm, dans le titre.").'</p>';

		$h .= '<div class="tabs">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item">'.s("Répondants").'</a>';
				$h .= '<a class="tab-item selected">'.s("Relevés").'</a>';
				$h .= '<a class="tab-item">'.s("Cotations").'</a>';
			$h .= '</div>';
			$h .= '<div class="tab-panel selected">';
				$h .= '<div class="tabs">';
					$h .= '<div class="tabs-item tabs-sub">';
						$h .= '<a class="tab-item">'.s("Les prix du moment").'</a>';
						$h .= '<a class="tab-item selected">'.s("Régions").'</a>';
						$h .= '<a class="tab-item">'.s("Départements").'</a>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 id="design-tabs-community" class="mt-2">'.s("Les onglets du bandeau d'un collectif").'</h4>';
		$h .= '<p class="util-annotation">'.s("Dans le bandeau d'un collectif, l'onglet d'un menu de plusieurs pages en ouvre la liste par data-dropdown, au survol par data-dropdown-hover et à l'appui sur un écran tactile. Son chevron est celui de l'onglet-menu, tab-item-caret, et non dropdown-toggle, dont le ::after effacerait le soulignement de l'onglet choisi. L'onglet est choisi quand l'écran affiché est une de ses pages, que la liste nomme en selected. La liste est claire, en dropdown-list-unstyled dropdown-list-light, comme celle du sélecteur de structure, et le menu replié aussi. Un menu d'une seule page n'ouvre pas de liste, l'onglet y mène. Sous 768 px, le menu replié range ces pages sous le nom de leur onglet, en dropdown-subtitle comme les groupes du sélecteur de structure, et un filet, dropdown-divider, referme le groupe avant l'onglet seul qui le suit. StructureCommunityUi::getTabsRow() et getTabsMenu() les rendent sur la clé dropdown d'un onglet.").'</p>';

		\Asset::css('main', 'template-community.css');

		$communityTabs = [
			'distribution' => ['href' => '#', 'label' => s("Distributions"), 'end' => FALSE],
			'producer' => ['href' => '#', 'label' => s("Producteurs"), 'end' => FALSE, 'dropdown' => TRUE, 'pages' => [
				'producer' => ['href' => '#', 'label' => s("Liste des producteurs")],
				'wallet' => ['href' => '#', 'label' => s("Cagnottes")],
				'referent' => ['href' => '#', 'label' => s("Référents")]
			]],
			'settings' => ['href' => '#', 'label' => s("Paramètres"), 'end' => FALSE]
		];

		$h .= '<div class="community-header design-community-header" style="--community: var(--amap)">';
			$h .= '<div class="community-header-tabs">'.\structure\StructureCommunityUi::getTabsRow($communityTabs, 'producer', 'wallet').'</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un rail d'indicateurs").'</h4>';
		$h .= '<p class="util-annotation">'.s("tabs-v pose un rail dont chaque onglet est un tab-item-stat (libellé, contexte en small, valeur et util-variation), groupé par tabs-item-group, avec une bascule tabs-sm dans le titre de la carte. Sous 992px le rail redevient une rangée défilante et les titres de groupe disparaissent.").'</p>';

		$h .= '<div class="tabs tabs-v">';
			$h .= '<div class="tabs-item">';
				$h .= '<div class="tabs-item-group">'.s("Structures").'</div>';
				$h .= '<a class="tab-item tab-item-stat selected"><span class="tab-item-stat-label">'.s("Structures actives").'<small>'.s("juillet 2026").'</small></span><span class="tab-item-stat-value">1 069 <span class="util-variation util-variation-up">+152 %</span></span></a>';
				$h .= '<div class="tabs-item-group">'.s("Ventes").'</div>';
				$h .= '<a class="tab-item tab-item-stat"><span class="tab-item-stat-label">'.s("Volume d'affaires").'<small>'.s("juillet 2026").'</small></span><span class="tab-item-stat-value">2,91 M€ <span class="util-variation util-variation-down">-4 %</span></span></a>';
				$h .= '<span class="tab-item tab-item-stat tab-item-stat-disabled"><span class="tab-item-stat-label">'.s("À venir").'<small>'.s("arrive avec un prochain lot").'</small></span></span>';
			$h .= '</div>';
			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-title"><span>'.s("Structures actives").'</span><span class="tabs-item tabs-sm"><a class="tab-item selected">'.s("Mois").'</a><a class="tab-item">'.s("Semaine").'</a></span></div>';
				$h .= '<div class="util-card-info">'.s("au moins une écriture sur la période").'</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Un badge à la place du compteur").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un onglet peut porter un badge à la place du compteur, quand il qualifie au lieu de compter : la pastille de la carte bancaire marque le prestataire qui encaisse les paiements en ligne (CardAiUi::getTabs()). Il se décolle du libellé comme le compteur.").'</p>';
		$h .= '<div class="tabs tabs-h tabs-lg">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected">'.\Asset::icon('stripe', ['class' => 'tab-item-icon']).s("Stripe").'<span class="badge badge-commercialisation">'.\Asset::icon('credit-card').'</span></a>';
				$h .= '<a class="tab-item">'.\Asset::image('main', 'sumup-logo.png', ['class' => 'asset-icon tab-item-icon']).s("SumUp").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le menu déroulant et ses briques. La grille libellé / valeur est le
	 * util-presentation du produit, habillé pour le fond sombre : elle sert dès
	 * qu'un déroulant doit dire l'état d'un objet avant de proposer d'agir.
	 */
	public function dropdowns(): string {

		$h = '<p class="util-annotation">'.s("Un déroulant s'ouvre depuis un élément porteur de {value} et se ferme au clic extérieur ; sur mobile il passe en plein écran. Les briques ci-dessous sont montrées ouvertes. Une entrée qu'on n'a pas le droit de choisir garde sa place en dropdown-item disabled plutôt que de disparaître : util-item-label porte le libellé et prend seul l'atténuation, util-item-more porte la raison, qui reste lisible et passe à la ligne au-delà d'une largeur fixe : sans cette borne, une raison longue élargirait tout le menu. Le second menu, sans largeur imposée, le montre.", '<code>data-dropdown</code>').'</p>';

		$h .= '<div class="dropdown-list" style="position: static; display: block; width: 21rem;">';

			$h .= '<div class="dropdown-title">';
				$h .= s("Titre du déroulant");
				$h .= '<div class="font-sm">'.s("sous-titre, en {value}", '<code>font-sm</code>').'</div>';
			$h .= '</div>';

			$h .= '<dl class="util-presentation util-presentation-1 dropdown-presentation">';
				$h .= '<dt>'.s("Libellé").'</dt><dd>'.s("Valeur").'</dd>';
				$h .= '<dt>'.s("Autre libellé").'</dt><dd>'.s("Autre valeur").'</dd>';
			$h .= '</dl>';

			$h .= '<div class="dropdown-divider"></div>';

			$h .= '<a class="dropdown-item">'.s("Entrée simple").'</a>';
			$h .= '<a class="dropdown-item dropdown-item-icon">'.\Asset::icon('pencil').'<span>'.s("Entrée avec icône").'</span></a>';
			$h .= '<a class="dropdown-item selected">'.s("Entrée retenue").'</a>';
			$h .= '<a class="dropdown-item disabled">'.s("Entrée désactivée").'</a>';

			$h .= '<div class="dropdown-divider"></div>';

			$h .= '<div class="dropdown-subtitle">'.s("Sous-titre de section").'</div>';

			$h .= '<div class="dropdown-text dropdown-info">'.s("Un texte d'explication, qui n'est pas cliquable.").'</div>';

			$h .= '<div class="dropdown-divider"></div>';

			$h .= '<div class="dropdown-block">';
				$h .= s("Un bloc de contenu : formulaire, jauge, grille de valeurs. Même rythme horizontal que les entrées, mais ni survol ni clic.");
			$h .= '</div>';

		$h .= '</div>';

		$h .= '<div class="dropdown-list mt-1" style="position: static; display: inline-block;">';
			$h .= '<a class="dropdown-item disabled"><div class="util-item-label">'.s("Entrée désactivée avec sa raison").'</div><div class="util-item-more">'.s("La raison, en une ligne").'</div></a>';
			$h .= '<a class="dropdown-item disabled"><div class="util-item-label">'.s("Entrée désactivée avec une raison longue").'</div><div class="util-item-more">'.s("Une raison plus longue que la largeur fixe passe à la ligne au lieu d'élargir le menu tout entier").'</div></a>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Le menu clair, et une entrée qui déplie").'</h4>';
		$h .= '<p class="util-annotation">'.s("dropdown-list-light pose le menu sur un fond blanc, quand il résume les lignes d'un écran dont il reprend les teintes d'état : le fond sombre des menus les renverserait. dropdown-item-unread marque une entrée pas encore vue, par le gras et un fond neutre, jamais par la couleur d'accent. dropdown-item-pending garde le gras sans le fond, pour un menu qui distingue « arrivé depuis la dernière visite » de « pas encore ouvert ». dropdown-item-group est une entrée qui déplie sur place le bloc dropdown-item-group-items qui la suit : elle porte data-dropdown-keep, faute de quoi le clic fermerait le menu, bascule dropdown-item-group-open, et son dropdown-item-group-chevron se retourne. dropdown-list-light se pose avec dropdown-list-unstyled : sans lui, l'ombre sombre que lime.css donne à un menu ouvert sur un écran non tactile l'emporte sur la sienne, par une spécificité plus forte. Le séparateur dropdown-divider et le bloc dropdown-block y prennent les teintes du fond clair, l'intertitre dropdown-subtitle une encre atténuée, et l'entrée selected, celle où l'on est, un fond teinté, le gras et l'arrondi d'une pastille, que le survol ne lui retire pas. Le rayon vaut la moitié d'une entrée d'une ligne : sur plusieurs lignes, l'entrée devient un rectangle aux coins arrondis, qui ne rogne pas son contenu. La liste garde sa marge des deux bords de l'écran, et une liste plus large que l'écran s'y borne. C'est la seule marque de sélection des listes claires : pages voisines, sélecteur de structure, menus des onglets d'un collectif, notifications et menu de création du rail. Une liste claire ouverte par un bouton coloré, en btn-primary, btn-secondary, leurs contours ou btn-color, porte data-dropdown-background=\"no\" : sans lui, Lime.Dropdown lui pose le fond bg-primary ou bg-secondary de son bouton, qui l'emporte par un !important.").'</p>';

		$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light" style="position: static; display: block; width: 21rem;">';
			$h .= '<div class="dropdown-title">'.s("Titre du menu").'</div>';
			$h .= '<div class="dropdown-subtitle">'.s("Un intertitre").'</div>';
			$h .= '<a class="dropdown-item selected">'.s("L'entrée où l'on est").'</a>';
			$h .= '<a class="dropdown-item dropdown-item-unread">'.s("Une entrée pas encore vue").'</a>';
			$h .= '<a class="dropdown-item dropdown-item-pending">'.s("Une entrée vue, pas encore ouverte").'</a>';
			$h .= '<a class="dropdown-item">'.s("Une entrée déjà vue").'</a>';
			$h .= '<a class="dropdown-item dropdown-item-group dropdown-item-group-open dropdown-item-unread" data-dropdown-keep onclick="this.classList.toggle(\'dropdown-item-group-open\')">';
				$h .= s("Une entrée qui en regroupe trois").' ';
				$h .= '<span class="dropdown-item-group-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';
			$h .= '<div class="dropdown-item-group-items">';
				$h .= '<a class="dropdown-item dropdown-item-unread">'.s("Première").'</a>';
				$h .= '<a class="dropdown-item dropdown-item-unread">'.s("Deuxième").'</a>';
				$h .= '<a class="dropdown-item">'.s("Troisième").'</a>';
			$h .= '</div>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<div class="dropdown-block">'.s("Un bloc de contenu sur le fond clair").'</div>';
		$h .= '</div>';

		return $h;

	}

	public function batch(): string {

		$h = '<p class="util-annotation">'.s("\\util\\BatchUi::group() rend la barre. Une pilule chevauche le bord haut et porte le titre et le nombre d'éléments cochés (util-counter), la croix annule la sélection.").'</p>';

		$menu = '<a class="batch-item">'.\Asset::icon('pencil-fill').'<span>'.s("Modifier").'</span></a>';
		$menu .= '<a class="batch-item">'.\Asset::icon('rulers').'<span>'.s("Dimensions").'</span></a>';
		$menu .= '<a class="batch-item">'.\Asset::icon('calendar3').'<span>'.s("Saisons").'</span></a>';
		$menu .= '<a class="batch-item">'.\Asset::icon('arrow-left-right').'<span>'.s("Déplacer").'</span></a>';
		$menu .= '<a class="batch-item disabled">'.\Asset::icon('geo-fill').'<span>'.s("Dessiner").'</span></a>';

		$danger = '<a class="batch-item batch-item-danger">'.\Asset::icon('trash').'<span>'.s("Supprimer").'</span></a>';

		$batch = \util\BatchUi::group('design-batch-demo', $menu, $danger, title: s("Pour les planches sélectionnées"), hide: '', preview: TRUE);
		$h .= str_replace('class="batch-group-count util-counter"></span>', 'class="batch-group-count util-counter">3</span>', $batch);

		return $h;

	}

	public function tableGroups(): string {

		$h = '<p class="util-annotation">'.s("tbody.tbody-group porte la tête et tbody.tbody-group-sub ses lignes filles. Les deux se lisent comme une seule bande, sous un même fond et séparées par des filets pointillés, et l'alternance de tbody-even les compte pour un. Les lignes filles portent tr-sub pour le retrait, et gardent la taille et la couleur du texte de leur tête. Employé par la liste des produits, le catalogue, le stock et l'analyse des ventes, pour les variantes.").'</p>';

		$h .= '<table class="tbody-even">';
			$h .= '<thead><tr><th>'.s("Produit").'</th><th>'.s("Unité").'</th><th class="text-end">'.s("Prix").'</th></tr></thead>';
			$h .= '<tbody><tr><td>'.s("Carotte botte").'</td><td>'.s("botte").'</td><td class="text-end">2,40 €</td></tr></tbody>';
			$h .= '<tbody class="tbody-group"><tr class="tr-title"><td>'.s("Poulet").'</td><td></td><td></td></tr></tbody>';
			$h .= '<tbody class="tbody-group-sub">';
				$h .= '<tr class="tr-sub"><td>'.s("Poulet prêt à cuire").'</td><td>'.s("pièce").'</td><td class="text-end">17,00 €</td></tr>';
				$h .= '<tr class="tr-sub"><td>'.s("Poulet extra gros").'</td><td>'.s("pièce").'</td><td class="text-end">21,00 €</td></tr>';
			$h .= '</tbody>';
			$h .= '<tbody><tr><td>'.s("Chou vert").'</td><td>'.s("pièce").'</td><td class="text-end">3,10 €</td></tr></tbody>';
		$h .= '</table>';

		$h .= '<h4 class="mt-2">'.s("Groupe repliable").'</h4>';
		$h .= '<p class="util-annotation">'.s("La tête porte en plus tbody-group-fold, et tbody-group-open quand ses lignes filles se montrent. Son titre est un lien tbody-group-toggle qui bascule tbody-group-open, suivi d'un util-counter et du chevron de util-fold. Il s'ouvre par défaut sous le seuil des piles du résumé de caisse, RegisterLib::isSummaryStackOpen(). Quand ses lignes se cochent, une case dans la tête les coche toutes par CheckboxField.all() et saute celles qui sont désactivées. Employé par les dettes et créances de la clôture d'une comptabilité de trésorerie.").'</p>';

		$toggle = attr('onclick', 'this.closest("tbody").classList.toggle("tbody-group-open")');
		$form = new \util\FormUi();
		$checkAll = 'CheckboxField.all(this.closest("tbody").nextElementSibling, this.checked, "input")';

		$h .= '<table class="tbody-even">';
			$h .= '<thead><tr><th class="td-checkbox"></th><th>'.s("Pièce").'</th><th class="text-end">'.s("TTC").'</th></tr></thead>';
			$h .= '<tbody class="tbody-group tbody-group-fold tbody-group-open"><tr class="tr-title"><td class="td-checkbox"><label>'.$form->inputCheckbox(NULL, 1, ['checked' => TRUE, 'onchange' => $checkAll]).'</label></td><td><a class="tbody-group-toggle" '.$toggle.'>'.s("Ventes sans règlement enregistré").' <span class="util-counter">2</span><span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span></a></td><td class="text-end">330,00 €</td></tr></tbody>';
			$h .= '<tbody class="tbody-group-sub">';
				foreach([['F-2025-118', '210,00 €'], ['F-2025-121', '120,00 €']] as [$number, $amount]) {
					$h .= '<tr class="tr-sub"><td class="td-checkbox"><label>'.$form->inputCheckbox(NULL, 1, ['checked' => TRUE]).'</label></td><td>'.s("Facture {number}", ['number' => $number]).'</td><td class="text-end">'.$amount.'</td></tr>';
				}
			$h .= '</tbody>';
			$h .= '<tbody class="tbody-group tbody-group-fold"><tr class="tr-title"><td class="td-checkbox"><label>'.$form->inputCheckbox(NULL, 1, ['onchange' => $checkAll]).'</label></td><td><a class="tbody-group-toggle" '.$toggle.'>'.s("Ventes encaissées en banque après le 31/12/2025").' <span class="util-counter">6</span><span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span></a></td><td class="text-end">1 482,00 €</td></tr></tbody>';
			$h .= '<tbody class="tbody-group-sub">';
				foreach([['F-2025-104', '247,00 €'], ['F-2025-107', '312,00 €'], ['F-2025-109', '189,00 €'], ['F-2025-112', '260,00 €'], ['F-2025-114', '274,00 €'], ['F-2025-116', '200,00 €']] as [$number, $amount]) {
					$h .= '<tr class="tr-sub"><td class="td-checkbox"><label>'.$form->inputCheckbox(NULL, 1).'</label></td><td>'.s("Facture {number}", ['number' => $number]).'</td><td class="text-end">'.$amount.'</td></tr>';
				}
			$h .= '</tbody>';
		$h .= '</table>';

		$h .= '<h4 class="mt-2">'.s("Échéances à venir").'</h4>';
		$h .= '<p class="util-annotation">'.s("Dans un tableau d'échéances, tr-future se pose sur chaque ligne qui n'est pas encore échue : en gris et en italique, on la distingue de ce qui est passé. La classe s'ajoute à tr-sub, dont elle remplace la couleur. Employé par le tableau d'amortissement d'un emprunt.").'</p>';

		$h .= '<table class="tbody-even">';
			$h .= '<thead><tr><th>'.s("Date").'</th><th class="text-end">'.s("Échéance").'</th><th class="text-end">'.s("Capital restant dû").'</th></tr></thead>';
			$h .= '<tbody class="tbody-group tbody-group-fold tbody-group-open"><tr class="tr-title"><td><a class="tbody-group-toggle" '.$toggle.'>'.s("2026").' <span class="util-counter">3</span><span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span></a></td><td class="text-end">577,23 €</td><td class="text-end">12 183,70 €</td></tr></tbody>';
			$h .= '<tbody class="tbody-group-sub">';
				$h .= '<tr class="tr-sub"><td>15/10/2026</td><td class="text-end">192,41 €</td><td class="text-end">12 375,97 €</td></tr>';
				$h .= '<tr class="tr-sub tr-future"><td>15/11/2026</td><td class="text-end">192,41 €</td><td class="text-end">12 279,79 €</td></tr>';
				$h .= '<tr class="tr-sub tr-future"><td>15/12/2026</td><td class="text-end">192,41 €</td><td class="text-end">12 183,70 €</td></tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		$h .= '<h4 class="mt-2">'.s("Groupe de lignes mis en évidence").'</h4>';
		$h .= '<p class="util-annotation">'.s("Quand plusieurs lignes d'un tableau forment une seule unité, les écritures d'un même groupe comptable par exemple, un geste posé sur l'une les emporte toutes. Deux classes rendent cette étendue visible : util-tr-group-hover pour ce que le curseur désigne, util-tr-group-selected pour ce que l'utilisateur a retenu, avec un liseré qui donne le groupe à lire comme un bloc.").'</p>';
		$h .= '<p class="util-annotation">'.s("Elles se posent sur chaque ligne du groupe, en pratique par JS : aucun sélecteur CSS ne remonte d'une ligne à ses sœurs. Le fond va sur les cellules et non sur la ligne, sinon l'alternance de table.tr-even le recouvre une ligne sur deux.").'</p>';

		$groups = [
			['label' => s("Groupe au repos"), 'class' => '', 'rows' => [['60610000', s("Fournitures"), '120,00 €', ''], ['44566000', s("TVA déductible"), '24,00 €', ''], ['40100000', s("Fournisseur"), '', '144,00 €']]],
			['label' => s("Groupe survolé"), 'class' => 'util-tr-group-hover', 'rows' => [['60610000', s("Fournitures"), '80,00 €', ''], ['44566000', s("TVA déductible"), '16,00 €', ''], ['40100000', s("Fournisseur"), '', '96,00 €']]],
			['label' => s("Groupe sélectionné"), 'class' => 'util-tr-group-selected', 'rows' => [['60610000', s("Fournitures"), '50,00 €', ''], ['44566000', s("TVA déductible"), '10,00 €', ''], ['40100000', s("Fournisseur"), '', '60,00 €']]],
		];

		$h .= '<table class="tr-even">';
			$h .= '<thead><tr><th>'.s("Compte").'</th><th>'.s("Libellé").'</th><th class="text-end">'.s("Débit").'</th><th class="text-end">'.s("Crédit").'</th></tr></thead>';
			$h .= '<tbody>';
				foreach($groups as $group) {
					$h .= '<tr class="tr-title"><td colspan="4">'.$group['label'].'</td></tr>';
					foreach($group['rows'] as [$account, $label, $debit, $credit]) {
						$h .= '<tr'.($group['class'] ? ' class="'.$group['class'].'"' : '').'>';
							$h .= '<td>'.$account.'</td>';
							$h .= '<td>'.$label.'</td>';
							$h .= '<td class="text-end">'.$debit.'</td>';
							$h .= '<td class="text-end">'.$credit.'</td>';
						$h .= '</tr>';
					}
				}
			$h .= '</tbody>';
		$h .= '</table>';

		$h .= '<p class="util-annotation">'.s("Les deux classes valent aussi pour un util-table-row. Le fond et le liseré vont alors sur le rang et non sur ses cellules, qui gardent chacune leur alignement dans sa hauteur sans y laisser de trou. Modèle : TodoAiUi::getRow(), les lignes qui bloquent une clôture de caisse.").'</p>';

		$h .= '<div class="util-table util-table-even" style="grid-template-columns: auto 1fr auto">';
			$h .= '<div class="util-table-head"><div>'.s("Date").'</div><div>'.s("Transaction").'</div><div class="text-end">'.s("Montant").'</div></div>';
			foreach([['14/06/2025', 'util-tr-group-selected'], ['17/06/2025', 'util-tr-group-selected'], ['15/07/2025', '']] as [$date, $class]) {
				$h .= '<div class="util-table-row'.($class ? ' '.$class : '').'">';
					$h .= '<div>'.$date.'</div>';
					$h .= '<div>'.s("Paiement").'<br/><b>6,30 €</b></div>';
					$h .= '<div class="text-end">6,30 €</div>';
				$h .= '</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les états qu'une liste montre, réunis. Chaque composant de liste les dessine à sa façon, et une
	 * session qui ne trouve pas celui de son composant en écrit un dans sa feuille : la teinte d'un rang
	 * réglé sur l'échéancier d'une souscription l'a été ainsi, faute d'état de rang sur util-table.
	 */
	public function listStates(): string {

		$h = '<p class="util-annotation">'.s("Une liste a quatre états, et chacun a sa forme. Au repos, rien ne distingue le rang ou la ligne. Ce qui est fait, à vérifier ou à traiter se dit par le fond, dans la teinte d'un util-deck-accent-* posé sur le rang lui-même, sans colonne ni bordure de plus. Une valeur qui manque dans un rang prend util-empty sur un span. Une liste sans aucun élément cède toute sa place à util-block-empty, qui porte son titre et son geste, et le tableau ne s'affiche pas au-dessus, vide.").'</p>';
		$h .= '<p class="util-annotation">'.s("Sur une util-table, util-table-row-state teinte le rang, util-deck-accent-success pour un rang fait, util-deck-accent-todo pour un rang à traiter ou qui a changé. util-deck-accent-warning et util-deck-accent-danger restent aux alertes. La teinte couvre l'alternance de util-table-even et le blanc de la feuille d'un écran, cède au survol de util-table-hover, et le texte du rang garde sa couleur. Une feuille de package ne pose donc pas de fond sur un util-table-row. Sur un util-deck, la même forme s'écrit util-deck-row-state, et la ligne pas encore vue util-deck-row-unread, montrées avec les cartes.").'</p>';

		$h .= '<div class="design-pair">';

			$h .= '<div>';
				$h .= '<div class="util-label">'.s("Des rangs dans tous leurs états").'</div>';
				$h .= '<div class="util-table util-table-even" style="grid-template-columns: auto minmax(0, 1fr) auto">';
					$h .= '<div class="util-table-head"><div>'.s("Échéance").'</div><div>'.s("Adhérent").'</div><div class="text-end">'.s("Montant").'</div></div>';
					foreach([
						['01/10/2026', s("Claire Fontaine"), '42,00 €', s("Réglé"), ' util-table-row-state util-deck-accent-success'],
						['01/10/2026', s("Hugo Bernard"), '42,00 €', '', ''],
						['01/11/2026', s("Marion Leclerc et Antoine Leclerc"), '84,00 €', s("Chèque à encaisser"), ' util-table-row-state util-deck-accent-todo'],
						['01/11/2026', s("Paul Appert"), '', '', ''],
					] as [$date, $name, $amount, $state, $class]) {
						$h .= '<div class="util-table-row'.$class.'">';
							$h .= '<div>'.$date.'</div>';
							$h .= '<div>'.$name.($state === '' ? '' : '<div class="util-annotation">'.$state.'</div>').'</div>';
							$h .= '<div class="text-end">'.($amount === '' ? '<span class="util-empty">'.s("À fixer").'</span>' : $amount).'</div>';
						$h .= '</div>';
					}
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div>';
				$h .= '<div class="util-label">'.s("La même liste, vide").'</div>';
				$h .= '<div class="util-block-empty">';
					$h .= '<h3>'.s("Aucune échéance").'</h3>';
					$h .= '<p>'.s("Les échéances paraissent ici dès qu'un adhérent souscrit à un contrat payable en plusieurs fois.").'</p>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function onboarding(): string {

		\Asset::css('main', 'onboarding.css');

		$h = '<p class="util-annotation">'.s("Un premier écran guidé quand une fonctionnalité est vide : à gauche les questions, une étape à la fois (onboarding-step + is-current, la barre onboarding-stepper), à droite un aperçu qui se construit au fil des réponses (onboarding-preview), vide à l'ouverture et qui ne montre que ce qui a été choisi, jamais une valeur que personne n'a saisie et un conseil par étape (onboarding-tip, data-onboarding-tip + is-current), sa question en gras seule sur sa ligne et la réponse dessous. À une étape sans conseil, le cadre disparaît plutôt que de garder son icône seule ; un cadre qui ne porte aucun data-onboarding-tip reste toujours affiché. Une liste que l'utilisateur remplit ligne à ligne se range dans onboarding-rows, une onboarding-row par ligne, serrées entre elles, et le bouton d'ajout après la liste, hors d'elle, avec mt-1. Le libellé sous la barre est un texte libre, ou les noms des étapes alignés chacun sous son segment (onboarding-progress-names), et les noms remplacent alors « Étape N sur M ». Trois états, sur les segments comme sur les noms : une étape faite (is-done) a son segment en vert clair et son nom en vert, l'étape courante (is-current) a son segment plein et son nom en gras, une étape à venir reste grise. L'état vient de la classe et jamais de la balise, donc un nom qui recule par un lien se dessine comme un nom nu. Au-dessus de 575 px, chaque nom reste sous son segment, et se coupe plutôt que d'en sortir quand sa colonne est trop courte pour lui. Sous 575 px, deux noms seulement restent affichés, la courante et l'étape faite qui la précède, et cette dernière n'y est qu'à la condition de mener quelque part : une marche franchie vers laquelle aucune adresse ne ramène ne porte pas is-done, donc son nom disparaît avec les autres sur un téléphone. Ces deux noms prennent alors la place des noms masqués quand leur colonne ne les contient pas, jusqu'à se partager la rangée, et ne se coupent qu'au-delà. Celui qui sort de sa colonne entraîne son voisin avec lui. La barre a deux modes, et c'est le balisage qui choisit : un segment qui porte data-step reçoit ses états de MainOnboarding.goTo(), par position, un segment qui n'en porte pas garde le is-done que le serveur a rendu. La barre dit alors ce qui a été fait et non où l'on est, ce que demande un parcours dont on peut passer une étape. La démonstration ci-dessous est du second mode. **On recule par le nom d'une étape faite**, qui mène à la sienne : la barre nomme les étapes, elle doit donc y donner accès. Un bouton Retour se pose dans un parcours **sans** noms d'étapes, comme celui du plan de culture. Il ne s'ajoute aux noms que dans un parcours où la barre ne permet pas toujours de reculer, parce qu'une étape peut y être passée sans être ni faite ni sautée et ne laisse alors aucun nom précédent sous 575 px, comme l'import d'un FEC.").'</p>';

		$form = new \util\FormUi();

		$h .= '<div class="onboarding mb-0">';

			$h .= '<div class="onboarding-header">';
				$h .= '<p class="onboarding-header-text">'.s("Cette fonctionnalité n'est pas encore configurée : quelques questions suffisent, tout se retouche ensuite d'un clic.").'</p>';
				$h .= '<a class="onboarding-header-skip">'.s("Je préfère commencer sans").'</a>';
			$h .= '</div>';

			$h .= '<div class="util-card onboarding-panel">';

				$h .= '<div class="onboarding-steps">';

					$h .= '<div class="onboarding-progress">';
						// Quatre étapes et des libellés longs : à trois noms courts sur des colonnes larges,
						// ni la coupe sous 575 px ni la marche franchie sans retour n'apparaissaient,
						// et l'annotation promettait donc ce qu'aucune planche ne montrait.
						$h .= '<div class="onboarding-stepper"><span class="is-done"></span><span class="is-done"></span><span class="is-current"></span><span></span></div>';
						$h .= '<div class="onboarding-progress-label onboarding-progress-names"><a class="is-done">'.s("Le début").'</a><span>'.s("Une étape sans retour").'</span><span class="is-current">'.s("L'étape au nom si long qu'il se coupe").'</span><span>'.s("La fin").'</span></div>';
					$h .= '</div>';

					$h .= '<section class="onboarding-step is-current">';
						$h .= '<h2>'.s("Comment travaillez-vous ?").'</h2>';
						$h .= '<p class="onboarding-help">'.s("Ce choix décide seulement de ce que nous vous proposons de créer ensuite. Rien n'est figé.").'</p>';
						$h .= '<div class="onboarding-tiles">';
							$h .= $form->radios('design-onboarding-mode', ['a' => 'a', 'b' => 'b'], 'a', [
								'required' => TRUE,
								'callbackRadioContent' => fn(string $mode) => '<div class="onboarding-tile"><div class="onboarding-tile-text"><div class="onboarding-tile-title">'.($mode === 'a' ? s("Première façon de faire") : s("Seconde façon de faire")).'</div><div class="onboarding-tile-desc">'.s("Une ligne qui explique ce que ça change, dans les mots de l'utilisateur.").'</div></div></div>'
							]);
						$h .= '</div>';
						$h .= '<div>';
							$h .= '<div class="onboarding-rows">';
								foreach([s("Élément 1"), s("Élément 2")] as $position => $placeholder) {
									$h .= '<div class="util-card onboarding-row">';
										$h .= '<div class="onboarding-row-main">';
											$h .= '<div class="onboarding-field onboarding-field-name">'.$form->text('design-onboarding-name['.$position.']', '', ['placeholder' => s("Ex. : {value}", $placeholder)]).'</div>';
											$h .= '<a class="onboarding-row-remove" title="'.s("Retirer").'">'.\Asset::icon('x-lg').'</a>';
										$h .= '</div>';
									$h .= '</div>';
								}
							$h .= '</div>';
							$h .= '<a class="btn btn-outline-primary mt-1">'.\Asset::icon('plus-circle').' '.s("Ajouter un élément").'</a>';
						$h .= '</div>';
						$h .= '<p class="util-info mb-0">'.s("Une note discrète, quand un choix mérite un mot de plus.").'</p>';
						// Pas de Retour dans ce pied : la démonstration porte des noms d'étapes,
						// donc c'est par eux qu'on recule. La forme à bouton est montrée plus bas,
						// sous « Reculer par un bouton Retour, sans noms d'étapes ».
						$h .= '<div class="onboarding-footer">';
							$h .= '<div class="onboarding-footer-main"><a class="btn btn-primary">'.s("Continuer").' '.\Asset::icon('arrow-right').'</a></div>';
						$h .= '</div>';
					$h .= '</section>';

				$h .= '</div>';

				$h .= '<aside class="onboarding-preview">';
					$h .= '<div class="onboarding-preview-header"><span class="onboarding-preview-title">'.s("Aperçu").'</span><span class="onboarding-preview-summary">'.s("2 éléments").'</span></div>';
					$h .= '<div class="onboarding-preview-body">';
						$h .= '<div class="util-card"><div class="util-card-title">'.s("Élément 1").' <span class="badge badge-solid bg-primary">'.s("Type").'</span></div><p class="util-annotation" style="margin: 0">'.s("Le contenu de l'aperçu est propre à chaque fonctionnalité.").'</p></div>';
					$h .= '</div>';
					$h .= '<div class="util-card onboarding-tip">'.\Asset::icon('info-circle').'<div><span data-onboarding-tip="2" class="is-current"><b>'.s("Un conseil par étape.").'</b> '.s("Il rassure et explique le vocabulaire au moment où il sert.").'</span></div></div>';
				$h .= '</aside>';

			$h .= '</div>';

		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Sans aperçu").'</h4>';
		$h .= '<p class="util-annotation">'.s("Tous les parcours n'ont pas quelque chose à montrer à côté des questions. onboarding-panel-plain retire la colonne de droite au lieu de la laisser à moitié vide : le panneau passe à une seule colonne que les questions remplissent, et c'est le panneau qui se borne, à 60 rem, centré : au-delà, une ligne de formulaire devient trop longue pour se relire, et une colonne bornée dans un panneau libre laissait un bloc blanc d'autant plus vide que l'écran est large. Le conseil descend alors au bas des questions, où son margin-top: auto le pose. La hauteur minimale tombe à auto avec la colonne qu'elle tenait.").'</p>';
		$h .= '<div class="util-card onboarding-panel onboarding-panel-plain">';
			$h .= '<div class="onboarding-steps">';
				$h .= '<div class="onboarding-progress">';
					$h .= '<div class="onboarding-stepper"><span class="is-current"></span><span></span></div>';
					$h .= '<div class="onboarding-progress-label onboarding-progress-names"><span class="is-current">'.s("Première étape").'</span><span>'.s("Deuxième étape").'</span></div>';
				$h .= '</div>';
				$h .= '<section class="onboarding-step is-current">';
					$h .= '<h2>'.s("Une question qui ne construit rien de visible").'</h2>';
					$h .= '<p class="onboarding-help">'.s("Rien à montrer à droite, mais la colonne reste : elle porte le conseil de l'étape.").'</p>';
					$h .= '<div class="onboarding-footer"><div class="onboarding-footer-main"><a class="btn btn-primary">'.s("Continuer").'</a></div></div>';
				$h .= '</section>';
				$h .= '<div class="util-card onboarding-tip">'.\Asset::icon('info-circle').'<div><span data-onboarding-tip="1" class="is-current"><b>'.s("Le conseil descend avec les questions.").'</b> '.s("Sans colonne de droite, il se pose au bas de la seule qui reste.").'</span></div></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("La barre au sommet du panneau").'</h4>';
		$h .= '<p class="util-annotation">'.s("Avec un aperçu, la colonne des questions ne fait qu'un peu plus de la moitié du panneau, et une barre posée dedans y replie les noms de ses étapes. onboarding-panel-progress-top sort la barre de la colonne : elle devient un enfant direct du panneau, prend toute sa largeur, et le panneau se borne comme onboarding-panel-plain, pour qu'une étape avec aperçu et une étape sans montrent la même barre à la même largeur. La barre reprend alors le padding de la colonne dont elle sort, et la colonne descend son propre haut au gap qui la séparait de la première question. La classe se pose avec onboarding-panel, en plus de onboarding-panel-plain quand l'étape n'a pas d'aperçu. Premier emploi : les quatre marches du tunnel d'un contrat d'AMAP.").'</p>';
		$h .= '<div class="util-card onboarding-panel onboarding-panel-progress-top">';
			$h .= '<div class="onboarding-progress">';
				$h .= '<div class="onboarding-stepper"><span class="is-done"></span><span class="is-current"></span><span></span><span></span></div>';
				$h .= '<div class="onboarding-progress-label onboarding-progress-names"><a class="is-done">'.s("Le début").'</a><span class="is-current">'.s("L'étape au nom si long qu'il se coupe").'</span><span>'.s("Une autre étape").'</span><span>'.s("La fin").'</span></div>';
			$h .= '</div>';
			$h .= '<div class="onboarding-steps">';
				$h .= '<section class="onboarding-step is-current">';
					$h .= '<h2>'.s("Une question dont la réponse se voit à côté").'</h2>';
					$h .= '<div class="onboarding-footer"><div class="onboarding-footer-main"><a class="btn btn-primary">'.s("Continuer").'</a></div></div>';
				$h .= '</section>';
			$h .= '</div>';
			$h .= '<aside class="onboarding-preview">';
				$h .= '<div class="onboarding-preview-header">';
					$h .= '<span class="onboarding-preview-title">'.s("Aperçu").'</span>';
					$h .= '<span class="onboarding-preview-summary">'.s("2 lignes").'</span>';
				$h .= '</div>';
				$h .= '<div class="onboarding-preview-body">';
					$h .= '<div class="util-card"><div class="util-card-title">'.s("Ce qui est déjà répondu").'</div><p class="util-annotation mb-0">'.s("Et rien de ce que personne n'a saisi.").'</p></div>';
				$h .= '</div>';
				$h .= '<div class="util-card onboarding-tip">'.\Asset::icon('info-circle').'<div><span data-onboarding-tip="1" class="is-current">'.s("Avec un aperçu, le conseil descend sous lui.").'</span></div></div>';
			$h .= '</aside>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Au-dessus du panneau, et un pied sous le seul titre").'</h4>';
		$h .= '<p class="util-annotation">'.s("onboarding-bound donne à ce qui se pose au-dessus du panneau, le résultat d'un parcours déjà fait ou ce que les réponses vont écrire, la même borne que lui : toute la largeur jusqu'à 60 rem, centré. Sans elle, une carte posée au-dessus d'un panneau onboarding-panel-plain ou onboarding-panel-progress-top dépasse les bords du panneau sur un grand écran. Dans un panneau sans aperçu, un pied qui suit directement le titre de l'étape, sans question entre les deux, perd son filet.").'</p>';
		$h .= '<div class="onboarding-bound">';
			$h .= '<div class="util-card util-card-accent util-card-accent-success mb-2"><div class="util-card-title"><span>'.s("Ce que le parcours a fait").'</span></div><p class="util-annotation mb-0">'.s("Une carte bornée comme le panneau qu'elle surmonte.").'</p></div>';
		$h .= '</div>';
		$h .= '<div class="util-card onboarding-panel onboarding-panel-plain">';
			$h .= '<div class="onboarding-steps">';
				$h .= '<section class="onboarding-step is-current">';
					$h .= '<h2>'.s("La question suivante").'</h2>';
					$h .= '<div class="onboarding-footer"><div class="onboarding-footer-main"><a class="btn btn-primary">'.s("Continuer").'</a></div></div>';
				$h .= '</section>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une tuile qui porte ses champs").'</h4>';
		$h .= '<p class="util-annotation">'.s("Quand un choix demande des précisions que l'autre choix ne demande pas, les champs vivent dans la tuile qui les appelle, sous sa description, et non plus bas dans l'étape : ils tiennent au choix, pas à l'étape. onboarding-tile-fields ne s'affiche que dans la tuile cochée, et le bouton radio remonte alors en tête de la tuile au lieu de se centrer sur les champs. Les champs restent ceux du cadriciel (dynamicGroups), sans marge propre. Premier emploi : la date de livraison de la mise en route d'une boutique.").'</p>';
		$h .= '<div class="onboarding mb-0"><div class="onboarding-tiles">';
			$h .= $form->radios('design-onboarding-fields', ['a' => 'a', 'b' => 'b'], 'a', [
				'callbackRadioContent' => function(string $mode) use($form) {
					$h = '<div class="onboarding-tile"><div class="onboarding-tile-text">';
						$h .= '<div class="onboarding-tile-title">'.($mode === 'a' ? s("Un choix qui demande des précisions") : s("Un choix qui se suffit")).'</div>';
						$h .= '<div class="onboarding-tile-desc">'.s("Une ligne qui explique ce que ça change, dans les mots de l'utilisateur.").'</div>';
						if($mode === 'a') {
							$h .= '<div class="onboarding-tile-fields">';
								$h .= $form->group(s("Premier champ"), $form->date('design-onboarding-fields-date', ''));
								$h .= $form->group(s("Second champ"), $form->text('design-onboarding-fields-text', ''));
							$h .= '</div>';
						}
					$h .= '</div></div>';
					return $h;
				}
			]);
		$h .= '</div></div>';

		$h .= '<h4 class="mt-2">'.s("Reculer par un bouton Retour, sans noms d'étapes").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le parcours ci-dessus nomme ses étapes : les noms sont cliquables et il n'y a pas de bouton Retour. Celui ci-dessous ne les nomme pas, sa barre portant un libellé libre comme le parcours du plan de culture, et n'a donc aucun autre moyen de revenir : c'est là, et seulement là, que le bouton se pose. Les cumuler donnerait deux chemins pour le même geste, dont un qui ne dit pas où il mène.").'</p>';
		$h .= '<div class="util-card" style="padding: 1.5rem;">';
			$h .= '<div class="onboarding-progress" style="margin-bottom: 1.25rem;">';
				$h .= '<div class="onboarding-stepper"><span class="is-done"></span><span class="is-current"></span><span></span></div>';
				$h .= '<div class="onboarding-progress-label">'.s("Étape 2 sur 3").'</div>';
			$h .= '</div>';
			$h .= '<div class="onboarding-footer">';
				$h .= '<div class="onboarding-footer-side"><a class="btn btn-outline-primary">'.s("Retour").'</a></div>';
				$h .= '<div class="onboarding-footer-main"><a class="btn btn-primary">'.s("Continuer").'</a></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Passer une étape").'</h4>';
		$h .= '<p class="util-annotation">'.s("Le pied a deux emplacements. À droite, onboarding-footer-main porte le geste qui répond à l'étape et lui seul. À gauche, onboarding-footer-side porte ce qui en sort sans y répondre, « Passer cette étape » en btn btn-outline-primary, et le bouton Retour d'un parcours sans noms d'étapes. Le geste qui passe l'étape ne se pose jamais à côté du bouton principal ni en tuile dans la liste des choix, parce qu'il n'est pas une réponse à la question posée. Son libellé est « Passer cette étape » dans tous les parcours. Sous 575 px les boutons prennent toute la largeur, le principal au-dessus, celui qui passe l'étape dessous.").'</p>';
		$h .= '<div class="util-card" style="padding: 1.5rem;">';
			$h .= '<div class="onboarding-footer">';
				$h .= '<div class="onboarding-footer-side"><a class="btn btn-outline-primary">'.s("Passer cette étape").'</a></div>';
				$h .= '<div class="onboarding-footer-main"><a class="btn btn-primary">'.s("Continuer").'</a></div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Une étape passée reste éteinte").'</h4>';
		$h .= '<p class="util-annotation">'.s("Un segment clair (is-done) dit que l'étape a été enregistrée, et rien d'autre. Une étape quittée par « Passer cette étape » garde son segment et son nom gris, même quand l'étape courante est plus loin, et celui qui revient sur le parcours voit ce qui lui reste à remplir. Son nom porte is-skipped : il reste gris, et on y revient en le cliquant, comme par le nom d'une étape faite. C'est le balisage du segment qui choisit. Un segment qui porte data-onboarding-name ne reçoit de MainOnboarding.goTo() que is-current et garde le is-done que le serveur a rendu, ou que le parcours pose quand l'étape s'enregistre ; un segment sans attribut n'est pas touché ; un segment qui porte data-step s'allume par position, passé ou non. Un nom suit son segment. Dans la démonstration, la deuxième étape a été passée.").'</p>';
		$h .= '<div id="convention-step-done" class="util-block">';
			$h .= '<div class="onboarding-progress">';
				$h .= '<div class="onboarding-stepper"><span class="is-done"></span><span></span><span class="is-current"></span><span></span></div>';
				$h .= '<div class="onboarding-progress-label onboarding-progress-names"><a class="is-done">'.s("Le lieu").'</a><span class="is-skipped">'.s("Les horaires").'</span><span class="is-current">'.s("Les produits").'</span><span>'.s("L'ouverture").'</span></div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function limeGrids(): string {

		$makeGrid = function(string $gridClass, string $label, string $columns): string {

			$rows = [
				['Carotte botte', '2,40 €', '151'],
				['Carotte kg', '1,90 €', '134'],
				['Chou vert pièce', '3,10 €', '92'],
			];

			$h = '<div>';
				$h .= '<h4><code>'.$label.'</code></h4>';
				$h .= '<div class="util-table '.$gridClass.'" style="grid-template-columns: '.$columns.'">';

					$h .= '<div class="util-table-head">';
						$h .= '<div>Produit</div>';
						$h .= '<div class="text-end">Prix</div>';
						$h .= '<div class="text-end">Répondants</div>';
					$h .= '</div>';

					$h .= '<div class="util-table-title">Légumes</div>';

					foreach($rows as $row) {
						$h .= '<div class="util-table-row">';
							$h .= '<div>'.$row[0].'</div>';
							$h .= '<div class="text-end">'.$row[1].'</div>';
							$h .= '<div class="text-end">'.$row[2].'</div>';
						$h .= '</div>';
					}

				$h .= '</div>';
			$h .= '</div>';

			return $h;

		};

		$h = '<p class="util-annotation">'.s("util-table rend l'habillage d'un tableau (en-tête, rangs, alternance, survol), mais ses colonnes sont déclarées en CSS (grid-template-columns sur le conteneur) plutôt que par le balisage. Sa forme de base est celle de la feuille d'un écran, montrée ici : ni fond ni ombre, ses rangs blancs posés à même la feuille (Feuille d'écran, plus haut). Hors de la feuille, dans un panneau ou sur une page publique, elle garde cette forme : ses rangs se posent sur le fond de la page, sans carte ni ombre, comme les deux tableaux qui suivent le cadre.").'</p>';

		\Asset::css('design', 'designAi.css');

		$h .= '<div class="design-paper-frame">';
			$h .= '<div class="util-paper">';
				$h .= '<div class="design-grids">';
					$h .= $makeGrid('', 'util-table', 'minmax(0, 1fr) auto auto');
					$h .= $makeGrid('util-table-hover', 'util-table-hover (survol de ligne)', 'minmax(0, 1fr) auto auto');
					$h .= $makeGrid('util-table-even', 'util-table-even (lignes alternées)', 'minmax(0, 1fr) auto auto');
					$h .= $makeGrid('util-table-bordered', 'util-table-bordered (lignes séparées par un filet)', 'minmax(0, 1fr) auto auto');
					$h .= $makeGrid('util-table-even util-table-hover', 'util-table-even util-table-hover', 'minmax(0, 1fr) auto auto');
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="design-grids mt-2">';
			$h .= $makeGrid('', s("util-table hors de la feuille"), 'minmax(0, 1fr) auto auto');
			$h .= $makeGrid('util-table-even', s("util-table-even hors de la feuille"), 'minmax(0, 1fr) auto auto');
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-table-even et util-table-bordered sont les deux façons de séparer les lignes, et elles ne se cumulent pas : l'alternance pour une grille qu'on lit, le filet pour une grille qu'on remplit.").'</p>';

		$h .= '<p class="util-annotation">'.s("Quand un groupe de lignes n'a pas d'intertitre et que sa première ligne le nomme, util-table-break pose un filet plein au-dessus de cette ligne. Dans une util-table-bordered, le tireté de la ligne d'avant s'efface pour ne pas doubler le trait. La feuille « Temps de travail » du planning sépare ainsi deux interventions.").'</p>';

		$h .= '<div class="util-table util-table-bordered" style="grid-template-columns: minmax(8rem, max-content) minmax(0, 1fr) auto">';
			foreach([[s("Récolte"), s("Carotte"), '1h30'], ['', s("Poireau"), '0h45'], [s("Désherbage"), s("Carotte"), '2h00'], ['', s("Oignon"), '1h00']] as $position => [$action, $name, $time]) {
				$h .= '<div class="util-table-row'.(($action !== '' and $position > 0) ? ' util-table-break' : '').'"><div><b>'.$action.'</b></div><div>'.$name.'</div><div class="text-end">'.$time.'</div></div>';
			}
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-table-head porte les intitulés (même typographie qu'un th), util-table-title un intertitre sur toute la largeur (le pendant de tr.tr-title), et util-table-full une cellule qui prend toute la ligne (le pendant de colspan).").'</p>';

		$h .= '<div class="util-table util-table-even" style="grid-template-columns: minmax(0, 1fr) repeat(6, auto)">';

			$h .= '<div class="util-table-head util-table-groups">';
				$h .= '<div></div>';
				$h .= '<div style="grid-column: span 3">'.s("Mobilisé").'</div>';
				$h .= '<div class="util-table-veil" style="grid-column: span 3">'.s("Exporté").'</div>';
			$h .= '</div>';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Culture").'</div>';
				foreach(['', ' util-table-veil'] as $veil) {
					foreach(['N', 'P₂O₅', 'K₂O'] as $element) {
						$h .= '<div class="text-end'.$veil.'">'.$element.'</div>';
					}
				}
			$h .= '</div>';

			foreach([[s("Carotte"), [60, 40, 160], [30, 25, 138]], [s("Poireau"), [120, 50, 170], [70, 30, 110]]] as [$name, $mobilized, $exported]) {
				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$name.'</div>';
					foreach($mobilized as $value) {
						$h .= '<div class="text-end">'.$value.'</div>';
					}
					foreach($exported as $value) {
						$h .= '<div class="text-end util-table-veil">'.$value.'</div>';
					}
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-table-veil voile une cellule d'une util-table, et chaque cellule du groupe le porte, en-têtes compris : le groupe se lit d'un bloc de la tête à la dernière ligne, et se distingue de son voisin sans filet ni écart. Le voile est une image, comme t-highlight : il se pose par-dessus l'alternance et le survol. Sa teinte est --util-table-veil, que reprend un nom de groupe écrit hors des rangs. Au-dessus de l'en-tête, util-table-groups ajoute un util-table-head qui nomme chaque groupe, centré et collé à l'en-tête qu'il coiffe, le voile arrondi en haut. Modèles : FertilizationUi::getCropReference(), CompositionUi pour les quantités de l'année.").'</p>';

		$form = new \util\FormUi();

		$h .= '<div class="util-table util-table-bordered" style="grid-template-columns: var(--touch-target) minmax(0, 1fr) 4rem 4rem">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Mode de livraison").'</div>';

				foreach([2 => s("Mar."), 5 => s("Ven.")] as $day => $label) {
					$h .= '<div class="util-checkbox">';
						$h .= '<label title="'.encode(s("Tout cocher / Tout décocher")).'">';
							$h .= $label;
							$h .= $form->inputCheckbox(NULL, '1', ['onclick' => 'CheckboxField.all(this.firstParent(\'.util-table\'), this.checked, \'[name="designPoints['.$day.'][]"]\')']);
						$h .= '</label>';
					$h .= '</div>';
				}

			$h .= '</div>';

			foreach([1 => s("Marché du centre"), 2 => s("Retrait sur place")] as $point => $name) {

				$h .= '<div class="util-table-row">';
					$h .= '<div class="util-checkbox">';
						$h .= '<label title="'.encode(s("Tout cocher / Tout décocher")).'">';
							$h .= $form->inputCheckbox(NULL, '1', ['onclick' => 'CheckboxField.all(this.firstParent(\'.util-table-row\'), this.checked, \'[name^="designPoints["]\')']);
						$h .= '</label>';
					$h .= '</div>';
					$h .= '<div>'.$name.'</div>';

					foreach([2, 5] as $day) {
						$h .= '<div class="util-checkbox">';
							$h .= '<label>'.$form->inputCheckbox('designPoints['.$day.'][]', $point, ['checked' => ($point === 1)]).'</label>';
						$h .= '</div>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-checkbox centre une case dans sa cellule, et toute la cellule coche. Dans une util-table, la cellule perd son padding et la zone qui coche fait au moins --touch-target dans les deux sens ; la case de tête se pose sous son intitulé et appelle CheckboxField.all(), avec l'état partiel en indeterminate, posé en JS. Pour un table, c'est td-checkbox. Modèle : ProgressUi::getTrackLessonRow().").'</p>';

		$h .= '<div class="util-table util-table-bordered" style="grid-template-columns: auto minmax(0, 1fr) auto">';
			foreach([[s("Vincent Guth"), '2h00'], [s("Céline Gouband"), '0h45']] as [$name, $time]) {
				$h .= '<div class="util-table-row"><div>'.\Asset::icon('person-circle').'</div><div>'.$name.'</div><div class="text-end">'.$time.'</div></div>';
			}
			$h .= '<a class="util-table-row util-table-add"><div><span class="util-table-add-icon" style="width: calc(2rem - var(--space-xs));">'.\Asset::icon('person-plus').'</span><span>'.s("Ajouter une personne").'</span></div></a>';
		$h .= '</div>';

		$h .= '<div class="util-table util-table-bordered" style="grid-template-columns: minmax(0, 1fr) auto auto">';
			$h .= '<div class="util-table-row"><div>'.\util\DateUi::numeric('2026-09-28').'</div><div class="text-end">12 kg</div><div></div></div>';
			$h .= '<div class="util-table-row"><div>'.\util\DateUi::numeric('2026-09-25').'</div><div class="text-end">8,5 kg</div><div></div></div>';
			$h .= $form->open(NULL, ['class' => 'util-table-row util-table-add-entry']);
				$h .= '<div>'.$form->date('designHarvestDate', '2026-10-07').'</div>';
				$h .= '<div>'.$form->inputGroup($form->number('designHarvestQuantity', NULL, ['step' => 0.001]).$form->select('designHarvestUnit', ['kg' => s("kg"), 'bunch' => s("botte")], 'kg', ['required' => TRUE]), ['style' => 'width: 11rem;']).'</div>';
				$h .= '<div class="util-table-add-actions">'.$form->hidden('designHarvestTask', 1).$form->button(s("Ajouter"), ['class' => 'btn btn-primary']).'<a class="btn btn-outline-primary">'.s("Annuler").'</a></div>';
			$h .= $form->close();
		$h .= '</div>';

		$h .= '<div class="util-table util-table-bordered" style="grid-template-columns: minmax(0, 1fr) auto auto">';
			$h .= $form->open(NULL, ['class' => 'util-table-row util-table-add-entry form-error']);
				$h .= '<div>'.$form->date('designHarvestRefusedDate', '2026-10-07').'</div>';
				$h .= '<div class="form-error-wrapper">'.$form->inputGroup($form->number('designHarvestRefusedQuantity', -5000, ['step' => 0.001]).$form->select('designHarvestRefusedUnit', ['kg' => s("kg"), 'bunch' => s("botte")], 'kg', ['required' => TRUE]), ['style' => 'width: 11rem;']).'<p class="form-error-message" style="width: 11rem;">'.s("Indiquez une quantité récoltée positive ou nulle.").'</p></div>';
				$h .= '<div class="util-table-add-actions">'.$form->button(s("Ajouter"), ['class' => 'btn btn-primary']).'<a class="btn btn-outline-primary">'.s("Annuler").'</a></div>';
			$h .= $form->close();
		$h .= '</div>';

		$h .= '<p class="util-annotation">'.s("util-table-add fait de la dernière ligne d'une util-table le geste qui en ajoute une : un lien util-table-row, à la teinte et au survol de tout lien, dont l'unique cellule porte toute la largeur, son icône util-table-add-icon à la largeur de la colonne des premières cellules. Le geste ouvre à sa place une liste à choisir, ou une ligne de saisie util-table-add-entry, montrée ouverte ci-dessus, puis refusée : un formulaire util-table-row dont chaque champ tient la colonne de ce qu'il écrit, et dont la dernière cellule util-table-add-actions porte Ajouter, Annuler et les champs cachés, qui en tête du formulaire prendraient la place de la première cellule. La largeur du groupe de la quantité se pose à l'écran. Après un refus, le message s'écrit sous son champ, et les autres cellules restent alignées sur le premier rang. Sur un téléphone, le premier champ prend sa ligne. Modèle : TaskDetailUi::getHarvestEntry(), sur la page d'une intervention du planning.").'</p>';

		$h .= '<p class="util-annotation">'.s("Les pistes sont posées ici en style= pour tenir sur une page de démonstration. Dans le produit, elles vont dans la feuille du package, sous un sélecteur propre à l'écran : c'est la seule façon de les redistribuer sous une media query, et c'est ce qui distingue une util-table d'un table. Modèle : base/structure/static/css/shareAi.css.").'</p>';

		$h .= '<p class="util-annotation">'.s("Un intitulé de colonne qui vit hors d'une util-table, dans une grille écrite par le package, prend util-table-header : la même typographie qu'un th, sans en être un. Et c'est là que s'arrête cette typographie : petites capitales, graisse et couleur atténuée dessinent l'étiquette d'une colonne, jamais le sujet d'une ligne. Le nom d'un droit, d'un produit ou d'une ferme en tête de ligne est une cellule ordinaire.").'</p>';

		$h .= '<div class="design-grid-header">';
			$h .= '<div class="util-table-header">'.s("Semaine").'</div>';
			$h .= '<div class="util-table-header text-end">'.s("Semis").'</div>';
			$h .= '<div class="util-table-header text-end">'.s("Récolte").'</div>';
			$h .= '<div>'.s("Semaine 12").'</div><div class="text-end">3</div><div class="text-end">0</div>';
			$h .= '<div>'.s("Semaine 13").'</div><div class="text-end">1</div><div class="text-end">2</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'en-tête qui reste en vue. Le cadre borne la démonstration : sur un écran, `thead-sticky` colle
	 * sous la barre du haut, dont la hauteur est dans `--mainSticky`.
	 */
	public function stickyHead(): string {

		$h = '<h4 class="mt-2">'.s("En-tête qui reste en vue").'</h4>';
		$h .= '<p class="util-annotation">'.s("thead.thead-sticky garde les intitulés de colonnes en vue pendant qu'on lit une longue liste, calé sous la barre du haut par --mainSticky. Il se désactive tout seul quand le tableau défile en largeur dans un util-overflow ou un util-overflow-*, où il suivrait le défilement horizontal. util-table-sticky fait la même chose pour l'en-tête d'une util-table.").'</p>';

		$h .= '<div class="design-sticky-frame">';
			$h .= '<table class="tr-even">';
				$h .= '<thead class="thead-sticky"><tr><th>'.s("Compte").'</th><th>'.s("Libellé").'</th><th class="text-end">'.s("Solde").'</th></tr></thead>';
				$h .= '<tbody>';
				foreach([
					['401000', s("Fournisseurs"), '-1 240,00 €'],
					['411000', s("Clients"), '3 815,50 €'],
					['512000', s("Banque"), '12 402,18 €'],
					['530000', s("Caisse"), '215,00 €'],
					['601000', s("Achats de semences"), '2 980,40 €'],
					['606300', s("Petit équipement"), '412,90 €'],
					['615000', s("Entretien et réparations"), '1 105,00 €'],
					['701000', s("Ventes de produits finis"), '-28 740,00 €'],
				] as [$account, $label, $amount]) {
					$h .= '<tr><td>'.$account.'</td><td>'.$label.'</td><td class="text-end">'.$amount.'</td></tr>';
				}
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("En-tête d'une util-table qui reste en vue").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-table-sticky colle le util-table-head de la util-table sous la barre du haut, et lui donne le fond de la surface pour que les rangées passent dessous. Un tableau qui veut un autre fond le pose sur sa propre classe, comme util-table-seeds. La règle se cale toute seule sur le gabarit : la hauteur de la barre d'administration en gestion, celle de la barre publique sur une page de présentation.").'</p>';

		$h .= '<div class="design-sticky-frame">';
			$h .= '<div class="util-table util-table-even util-table-sticky" style="grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 8rem">';
				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Variété").'</div>';
					$h .= '<div>'.s("Espèce").'</div>';
					$h .= '<div class="text-end">'.s("Semis").'</div>';
				$h .= '</div>';
				foreach([
					[s("Touchon"), s("Carotte"), '12/03'],
					[s("Nantaise améliorée"), s("Carotte"), '28/03'],
					[s("Batavia Kamikaze"), s("Laitue"), '04/04'],
					[s("Reine de Mai"), s("Laitue"), '11/04'],
					[s("Monalisa"), s("Pomme de terre"), '18/04'],
					[s("Bleu de Solaize"), s("Poireau"), '25/04'],
					[s("Marmande"), s("Tomate"), '02/05'],
					[s("Cornue des Andes"), s("Tomate"), '09/05'],
					[s("Beurre de Rocquencourt"), s("Haricot"), '16/05'],
					[s("Noire de Crimée"), s("Tomate"), '23/05'],
				] as [$variety, $plant, $sowing]) {
					$h .= '<div class="util-table-row">';
						$h .= '<div>'.$variety.'</div>';
						$h .= '<div>'.$plant.'</div>';
						$h .= '<div class="text-end">'.$sowing.'</div>';
					$h .= '</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		\Asset::js('main', 'stickyColumnAi.js');

		$h .= '<h4 class="mt-2">'.s("Première colonne qui reste en vue").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-table-sticky-column garde la première colonne d'une util-table en vue quand la table défile en largeur dans son util-overflow, pour qu'on sache toujours de quelle ligne on lit les valeurs. La cellule fixe prend le fond de son rang, alternance comprise, et une ombre au bord dit que les colonnes passent dessous. Quand la table tient dans son enveloppe, rien ne passe dessous et l'ombre s'éteint, par MainStickyColumn (main/static/js/stickyColumnAi.js) qui pose alors is-fitted sur la table. Un écran qui emploie la classe charge ce script par \\Asset::js('main', 'stickyColumnAi.js'), et sans lui l'ombre reste, donc une colonne n'est jamais sans repère. Un écran qui fige une cellule de plus, la case qui passe sous le nom sur un téléphone par exemple, lui donne z-index: var(--table-sticky-column-layer), la couche que la table déclare à ses cellules fixes, et son ombre par box-shadow: var(--table-sticky-column-shadow), que is-fitted éteint avec la leur. Modèle : ProgressUi::getTrack(), le suivi des stagiaires d'une formation. td-sticky-first fait la même chose, posé sur la balise table, ombre éteinte par is-fitted comprise. Chaque cellule y porte déjà son fond, une table-unstyled doit donc en donner un à sa première colonne. Modèles : RoleUi, les rôles d'une structure, et les tableaux des statistiques.").'</p>';

		$weeks = [s("S12"), s("S13"), s("S14"), s("S15"), s("S16"), s("S17"), s("S18"), s("S19")];

		$h .= '<div class="util-overflow design-sticky-frame design-sticky-frame-column">';
			$h .= '<div class="util-table util-table-even util-table-sticky-column" style="grid-template-columns: minmax(10rem, 1fr) repeat('.count($weeks).', minmax(5rem, 1fr)); min-width: min-content">';
				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Culture").'</div>';
					foreach($weeks as $week) {
						$h .= '<div class="text-end">'.$week.'</div>';
					}
				$h .= '</div>';
				foreach([s("Carotte"), s("Laitue"), s("Poireau")] as $position => $crop) {
					$h .= '<div class="util-table-row">';
						$h .= '<div>'.$crop.'</div>';
						foreach($weeks as $index => $week) {
							$h .= '<div class="text-end">'.(($index + $position) % 3 === 0 ? '' : (($index + 2) * ($position + 1)).' kg').'</div>';
						}
					$h .= '</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-1">'.s("La même grille avec deux semaines au lieu de huit tient dans son enveloppe, et son bord n'a plus d'ombre.").'</p>';

		$weeksFitted = array_slice($weeks, 0, 2);

		$h .= '<div class="util-overflow design-sticky-frame design-sticky-frame-column">';
			$h .= '<div class="util-table util-table-even util-table-sticky-column" style="grid-template-columns: minmax(10rem, 1fr) repeat('.count($weeksFitted).', minmax(5rem, 1fr)); min-width: min-content">';
				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Culture").'</div>';
					foreach($weeksFitted as $week) {
						$h .= '<div class="text-end">'.$week.'</div>';
					}
				$h .= '</div>';
				foreach([s("Carotte"), s("Laitue"), s("Poireau")] as $position => $crop) {
					$h .= '<div class="util-table-row">';
						$h .= '<div>'.$crop.'</div>';
						foreach($weeksFitted as $index => $week) {
							$h .= '<div class="text-end">'.(($index + $position) % 3 === 0 ? '' : (($index + 2) * ($position + 1)).' kg').'</div>';
						}
					$h .= '</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<p class="util-annotation mt-1">'.s("La table peut être son propre cadre, et ranger ses lignes par groupes sans boîte, chacun ouvert par un intertitre. La première cellule de chaque ligne reste en vue. L'intertitre défile, mais sa case et son titre restent en vue là où ils sont au repos : la case au retrait de la colonne, le titre décalé de --table-title-sticky-left, l'écart que l'écran règle sur la case. La ligne d'ajout défile aussi, son lien reste en vue.").'</p>';

		$form = new \util\FormUi();

		$h .= '<div class="util-table util-table-sticky-column design-sticky-frame-column design-sticky-grouped" style="grid-template-columns: minmax(10rem, 1fr) repeat('.count($weeks).', minmax(5rem, 1fr))">';
			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Intervention").'</div>';
				foreach($weeks as $week) {
					$h .= '<div class="text-end">'.$week.'</div>';
				}
			$h .= '</div>';
			foreach([
				[s("Désherbage"), [s("Carotte"), s("Laitue")], 0],
				[s("Récolte"), [s("Poireau")], 3],
			] as [$action, $crops, $rest]) {
				$h .= '<div class="design-sticky-group">';
					$h .= '<div class="util-table-title">';
						$h .= '<label>'.$form->inputCheckbox(NULL).'</label>';
						$h .= '<h4>'.$action.'</h4>';
					$h .= '</div>';
					foreach($crops as $position => $crop) {
						$h .= '<div class="util-table-row">';
							$h .= '<div>'.$crop.'</div>';
							foreach($weeks as $index => $week) {
								$h .= '<div class="text-end">'.(($index + $position) % 3 === 0 ? '' : (($index + 1) * ($position + 1)).' h').'</div>';
							}
						$h .= '</div>';
					}
					if($rest > 0) {
						$h .= '<a class="util-table-row util-table-add"><div><span class="util-table-add-icon">'.\Asset::icon('chevron-down').'</span><span>'.p("Voir l'autre", "Voir les {value} autres", $rest).'</span></div></a>';
					}
				$h .= '</div>';
			}
		$h .= '</div>';

		$h .= '<div class="util-overflow design-sticky-frame design-sticky-frame-column mt-1">';
			$h .= '<table class="tr-even td-sticky-first" style="min-width: calc(10rem + 5rem * '.count($weeks).')">';
				$h .= '<thead><tr>';
					$h .= '<th>'.s("Culture").'</th>';
					foreach($weeks as $week) {
						$h .= '<th class="text-end">'.$week.'</th>';
					}
				$h .= '</tr></thead>';
				$h .= '<tbody>';
					foreach([s("Carotte"), s("Laitue"), s("Poireau")] as $position => $crop) {
						$h .= '<tr>';
							$h .= '<td>'.$crop.'</td>';
							foreach($weeks as $index => $week) {
								$h .= '<td class="text-end">'.(($index + $position) % 3 === 0 ? '' : (($index + 2) * ($position + 1)).' kg').'</td>';
							}
						$h .= '</tr>';
					}
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		$h .= '<h4 class="mt-2">'.s("Tableau large et long qui garde son en-tête").'</h4>';
		$h .= '<p class="util-annotation">'.s("util-overflow-frame fait défiler un tableau dans un cadre haut au plus comme l'écran, en largeur comme en hauteur : thead-sticky y reste en vue, parce que le cadre lui sert de conteneur défilant, et la barre de défilement horizontale est toujours visible au pied du cadre. Un util-overflow ne peut pas garder l'en-tête : un cadre qui défile en largeur défile aussi en hauteur pour le navigateur, et un en-tête collé à un cadre qui ne défile pas en hauteur ne colle plus à rien. Sous 576 px, le cadre perd sa hauteur et l'en-tête ne colle plus, un défilement dans le défilement piégeant le doigt. Avec td-sticky-first, une cellule fusionnée qui ouvre un rang d'en-tête défile avec les autres. Ici le cadre est borné à 14rem pour la démonstration. Modèle : AmortizationUi::getDepreciationTable(), le tableau des immobilisations.").'</p>';

		$h .= '<div class="util-overflow-frame design-sticky-frame design-sticky-frame-column">';
			$h .= '<table class="tr-even td-sticky-first" style="min-width: calc(15rem + 5rem * '.count($weeks).')">';
				$h .= '<thead class="thead-sticky">';
					$h .= '<tr>';
						$h .= '<th colspan="2" class="text-center">'.s("Culture").'</th>';
						$h .= '<th colspan="'.count($weeks).'" class="text-center">'.s("Récolte").'</th>';
					$h .= '</tr>';
					$h .= '<tr>';
						$h .= '<th>'.s("Espèce").'</th>';
						$h .= '<th>'.s("Planche").'</th>';
						foreach($weeks as $week) {
							$h .= '<th class="text-end">'.$week.'</th>';
						}
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';
					foreach([s("Carotte"), s("Laitue"), s("Poireau"), s("Betterave"), s("Chou-fleur"), s("Épinard"), s("Radis"), s("Courgette"), s("Oignon"), s("Fenouil")] as $position => $crop) {
						$h .= '<tr>';
							$h .= '<td>'.$crop.'</td>';
							$h .= '<td>'.s("Planche {value}", $position + 1).'</td>';
							foreach($weeks as $index => $week) {
								$h .= '<td class="text-end">'.(($index + $position) % 3 === 0 ? '' : (($index + 2) * ($position + 1)).' kg').'</td>';
							}
						$h .= '</tr>';
					}
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	public function limeTables(): string {

		$makeTable = function(string $tableClass, string $label, array $trClasses = []): string {
			$rows = [
				['Alice', 'Martin', '38', '2 400 €'],
				['Bob', 'Dupont', '25', '1 900 €'],
				['Claire', 'Bernard', '52', '3 100 €'],
			];
			$h = '<div>';
			$h .= '<h4><code>'.$label.'</code></h4>';
			$h .= '<table'.($tableClass ? ' class="'.$tableClass.'"' : '').'>';
				$h .= '<thead><tr><th>Prénom</th><th>Nom</th><th>Âge</th><th>Montant</th></tr></thead>';
				$h .= '<tbody>';
				foreach($rows as $i => $row) {
					$trClass = $trClasses[$i] ?? '';
					$h .= '<tr'.($trClass ? ' class="'.$trClass.'"' : '').'>';
					foreach($row as $cell) {
						$h .= '<td>'.$cell.'</td>';
					}
					$h .= '</tr>';
				}
				$h .= '</tbody>';
			$h .= '</table>';
			$h .= '</div>';
			return $h;
		};

		$h = '<div class="design-pair design-pair-lg">';
			$h .= $makeTable('', 'table, défaut');
			$h .= $makeTable('tr-hover', 'table.tr-hover, survol de ligne');
			$h .= $makeTable('tr-even', 'table.tr-even, lignes alternées');
			$h .= $makeTable('tr-bordered', 'table.tr-bordered, bordures de ligne');
			$h .= $makeTable('', 'tr.tr-bold · tr.tr-light · tr.tr-title', ['tr-bold', 'tr-light', 'tr-title']);
			$h .= $makeTable('', 'tr.tr-upper · tr.row-highlight · tr.tr-disabled', ['tr-upper', 'row-highlight', 'tr-disabled']);

			$h .= '<div>';
				$h .= '<h4><code>'.s("tr.tr-title + tr.tr-sub, une ligne de tête et ses sous-lignes").'</code></h4>';
				$h .= '<table class="tr-even">';
					$h .= '<thead><tr><th>'.s("Produit").'</th><th>'.s("Unité").'</th><th class="text-end">'.s("Prix").'</th></tr></thead>';
					$h .= '<tbody>';
						$h .= '<tr class="tr-title">';
							$h .= '<td><span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span> '.s("Poulet prêt à cuire").'</td>';
							$h .= '<td>'.s("kg, pièce").'</td>';
							$h .= '<td class="text-end">'.s("{from} à {to}", ['from' => \util\TextUi::money(13), 'to' => \util\TextUi::money(27.5)]).'</td>';
						$h .= '</tr>';
						$h .= '<tr class="tr-sub"><td>'.s("Poulet prêt à cuire").'</td><td>'.s("kg").'</td><td class="text-end">'.\util\TextUi::money(13).'</td></tr>';
						$h .= '<tr class="tr-sub"><td>'.s("Poulet prêt à cuire").' / '.s("1,2-1,4 kg").'</td><td>'.s("pièce").'</td><td class="text-end">'.\util\TextUi::money(17).'</td></tr>';
						$h .= '<tr class="tr-sub tr-disabled"><td>'.s("Poulet prêt à cuire").' / '.s("sans calibre").'</td><td>'.s("pièce").'</td><td class="text-end">'.\util\TextUi::money(23).'</td></tr>';
					$h .= '</tbody>';
				$h .= '</table>';
				$h .= '<p class="util-annotation">'.s("tr.tr-sub met une sous-ligne en retrait, en plus petit et en texte intermédiaire, et sort de l'alternance de table.tr-even comme tr.tr-title. Le chevron de la ligne de tête est util-deck-chevron, qui tourne de lui-même sous util-deck-open.").'</p>';
			$h .= '</div>';

			$h .= '<div>';
				$h .= '<h4><code>'.s("td-min-content · text-end · t-highlight").'</code></h4>';
				$h .= '<div class="util-overflow">';
					$h .= '<table class="tr-even">';
						$h .= '<thead><tr><th class="td-min-content">'.s("Compte").'</th><th>'.s("Libellé").'</th><th class="text-end">'.s("Solde").'</th></tr></thead>';
						$h .= '<tbody>';
							foreach([['601', s("Achats de semences"), 240], ['606', s("Fournitures"), 550.5], ['625', s("Transport"), 638.7]] as [$account, $label, $balance]) {
								$h .= '<tr><td class="td-min-content">'.$account.'</td><td>'.$label.'</td><td class="text-end t-highlight">'.\util\TextUi::money($balance).'</td></tr>';
							}
						$h .= '</tbody>';
					$h .= '</table>';
				$h .= '</div>';
				$h .= '<p class="util-annotation">'.s("td-min-content réduit une colonne à son contenu, sans retour à la ligne ; text-end aligne à droite un montant et son en-tête. t-highlight voile les cellules d'une colonne pour la distinguer de ses voisines, l'en-tête restant au fond du tableau : le voile se pose par-dessus le fond de la ligne, zébrage ou survol compris.").'</p>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= $this->stickyHead();

		return $h;

	}

	public function overflow(): string {

		$h = '<p class="util-annotation">'.s("util-overflow-* fait défiler un tableau en largeur sous son seuil, au lieu d'en écraser les colonnes : sous le seuil, l'enfant du cadre garde la largeur du seuil, et le cadre défile. Le suffixe se choisit d'après la largeur à laquelle le tableau cesse de se lire, util-overflow-sm le plus souvent.").'</p>';

		$h .= '<div class="util-overflow-sm">';
			$h .= '<table class="tr-even">';
				$h .= '<thead><tr>';
					$h .= '<th>'.s("Cagnotte").'</th>';
					foreach([s("Janvier"), s("Février"), s("Mars"), s("Avril"), s("Mai"), s("Juin")] as $month) {
						$h .= '<th class="text-end">'.$month.'</th>';
					}
				$h .= '</tr></thead>';
				$h .= '<tbody>';
					foreach([[s("Marché du samedi"), [1240, 1310, 1580, 1720, 2140, 2380]], [s("Paniers"), [860, 860, 910, 940, 1020, 1080]]] as [$name, $amounts]) {
						$h .= '<tr>';
							$h .= '<td>'.$name.'</td>';
							foreach($amounts as $amount) {
								$h .= '<td class="text-end">'.\util\TextUi::money($amount, precision: 0).'</td>';
							}
						$h .= '</tr>';
					}
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	public function pagination(): string {

		$h = '<p class="util-annotation">'.s("\\util\\TextUi::pagination() rend la navigation entre les pages d'une liste trop longue pour un écran ; le découpage reste à la requête de l'appelant. Elle reçoit la page courante, comptée à partir de zéro, et le nombre d'éléments divisé par le nombre par page, qu'elle arrondit au-dessus. Elle montre au plus dix numéros, à partir de cinq pages avant la page courante. Les deux flèches des bords mènent à la première et à la dernière page, et disparaissent quand on y est déjà ; le champ saute à une page précise. Sur une seule page, elle ne rend rien.").'</p>';

		$h .= \util\TextUi::pagination(7, 24);

		return $h;

	}

	private function extractBtnClasses(): array {

		$css = $this->getCssFile();

		preg_match_all('/\.(btn-[a-zA-Z0-9_-]+)/', $css, $matches);

		return array_unique($matches[1]);

	}

	private function extractRootCssVariables(): array {

		$css = $this->getCssFile();

		preg_match('/:root\s*\{([^}]+)}/', $css, $block);
		if(empty($block[1])) {
			return [];
		}

		preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $block[1], $matches, PREG_SET_ORDER);

		$vars = [];
		foreach($matches as $match) {
			$vars[$match[1]] = trim($match[2]);
		}
		return $vars;

	}

	private function getCssFile(): string {
		return
			file_get_contents(\Package::getPath('main').'/static/css/reboot.css').
			file_get_contents(\Package::getPath('main').'/static/css/component.css').
			file_get_contents(\Package::getPath('main').'/static/css/root.css');
	}
}
?>
