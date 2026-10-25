<?php
namespace structure;

/**
 * Les blocs de la fiche publique d'une ferme, `/ferme/{id}`.
 *
 * Deux règles gouvernent tout le rendu, et elles viennent de la maquette :
 *
 * - **la granularité est la ligne.** Une ligne sans valeur ne s'affiche pas, un bloc dont toutes
 *   les lignes sont vides ne s'affiche pas. La production restreint les lignes possibles, le
 *   remplissage décide de ce qui reste. Chaque méthode rend donc `''` plutôt qu'un bloc vide ;
 * - **rien ne se calcule ici.** Tout arrive préparé par la page.
 */
class ProfileAiUi {

	/**
	 * Le zoom cadre le département, pas la ferme ni le pays.
	 *
	 * ⚠️ Le zoom seul ne dit rien : ce qu'on voit dépend de la largeur **et** du rapport de
	 * l'image. La scène fait 1 200 × 300, soit un rapport de 4 pour 1 — on ne cadre pas la France
	 * dans une bande pareille, elle y viendrait avec l'Italie et la Suisse. Mesuré : à 5, la bande
	 * allait de l'Atlantique à l'Autriche ; à 6, Milan et le Léman entraient dans le cadre. À 8,
	 * elle couvre le département et ses voisins — l'échelle que la pastille de lieu annonce, et
	 * celle que le centrage sur la commune promet.
	 */
	const MAP_ZOOM = 8;

	const MAP_WIDTH = 1200;
	const MAP_HEIGHT = 300;

	/**
	 * ⚠️ Pas de densité double : mesuré le 2026-09-05, la même image en `@2x` pèse 456 Ko contre
	 * 152. Elle passe sous un voile et porte du texte par-dessus, personne n'y cherche un détail —
	 * et c'est le fond d'une page que n'importe qui ouvre sans compte.
	 */
	const MAP_RETINA = FALSE;

	/** Le pas du fond dessiné qui remplace la carte, en pixels. Cf. `DesignUi::getNoiseContours()`. */
	const MAP_STEP = 12;

	/**
	 * La hauteur du relief dessiné derrière le bandeau, en unités du viewBox.
	 *
	 * Plus basse que la scène parce que la bande l'est : le SVG est en `slice`, un viewBox plus haut
	 * ne montrerait qu'une tranche du relief au lieu du relief entier. Le pas reste celui de la
	 * scène — à 6, le même motif pèse 74 Ko contre 37.
	 */
	const BANNER_HEIGHT = 220;

	/**
	 * L'opacité des courbes du bandeau, contre `.9` sur la scène.
	 *
	 * La scène porte ses courbes sous un voile et ne montre que des chiffres ; le bandeau porte le
	 * nom de la ferme à même le motif, et les traits pleins le hachaient (arbitrage,
	 * 12/09/2026).
	 */
	const BANNER_STROKE_OPACITY = '.45';

	/** La couleur du repère, sans le croisillon que l'API refuse. */
	const MAP_COLOR = '135266';

	public function __construct() {
		\Asset::css('structure', 'profileAi.css');
		\Asset::js('structure', 'profileAi.js');
	}

	/**
	 * Le bandeau de ferme, le médaillon, puis les jetons et la présentation.
	 *
	 * ⚠️ `$container` sépare l'enveloppe du contenu, et ce n'est pas un réglage d'apparence :
	 * `.container` se dimensionne sur l'**écran** (`width: var(--screen-width)`, `reboot.css:1106`)
	 * et non sur son parent. Il n'est donc **pas imbricable** — posé dans un écran qui en a déjà
	 * un, il ignore la place que le menu latéral laisse et déborde. Mesuré : 31 px de défilement
	 * horizontal sur la page de réglage du profil, qui rend cet en-tête dans le container du
	 * `StructureTemplate`.
	 *
	 * La fiche publique passe TRUE : elle pose `mainContainer = FALSE` pour que la bande aille
	 * d'un bord à l'autre, et reprend son container ici. L'aperçu passe FALSE : il est déjà dans
	 * celui du gabarit.
	 */
	public function getIdentity(Structure $eStructure, \Collection $cUser = new \Collection(), bool $container = TRUE): string {

		$class = 'container profile-banner-inner';

		// Sans container, la bande n'a aucune gouttière : la feuille en pose une, et décale le corps
		// d'autant pour qu'il reste aligné sur le titre.
		$h = '<div class="profile-header'.($container ? '' : ' profile-header-plain').'">';

			$inner = '<div class="'.($container ? $class : 'profile-banner-inner').'">';

					$inner .= $this->getMedallion($eStructure);

					$inner .= '<div class="profile-banner-text">';

						// ⚠️ Le surtitre dit ce qu'est cette ferme, à l'endroit où le bandeau d'un
						// groupe dit son type. Il remplace le bloc d'avertissement qui suivait la
						// description : quatre mots dans la bande valent mieux que deux phrases
						// sous elle, et le lecteur sait avant de lire plutôt qu'après.
						if($eStructure->isSchool()) {
							$inner .= '<div class="profile-banner-type">'.s("Ferme école").'</div>';
						}

						$inner .= '<h1>'.encode($eStructure['name']).'</h1>';

						$details = $this->getIdentityDetails($eStructure, $cUser);

						if($details !== '') {
							$inner .= '<div class="profile-header-meta">'.$details.'</div>';
						}

					$inner .= '</div>';

				$inner .= '</div>';

			$h .= $this->getBanner($eStructure, $inner);

			$h .= '<div class="'.($container ? 'container' : '').'">';

				// Le corps s'aligne sur le titre et non sur le bord : le médaillon décale le nom
				// vers la droite, et une description repartant du bord gauche ouvrirait un second
				// axe de lecture sous le premier. Sans logo il n'y a pas de médaillon, donc rien
				// qui décale le titre, et le corps repart du bord.
				$h .= '<div class="profile-header-body'.($eStructure['logo'] === NULL ? '' : ' profile-header-body-offset').'">';

					if($eStructure['description'] !== NULL) {
						$h .= '<div class="profile-description">'.new \editor\EditorUi()->value($eStructure['description']).'</div>';
					}

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La ligne qui situe la structure sous son nom : la commune, l'ancienneté et qui en répond, jointes
	 * par « · », ou rien. Elle sert la bande de la fiche publique et la tête de page des écrans qui
	 * nomment une structure sans reprendre sa bande (la fiche d'un producteur de l'AMAP).
	 */
	public function getIdentityDetails(Structure $eStructure, \Collection $cUser = new \Collection()): string {

		$details = [];

		if($eStructure['legalCity'] !== NULL) {
			$details[] = \Asset::icon('geo-fill').' '.encode($eStructure['legalCity']);
		}

		if($eStructure['startedAt'] !== NULL) {
			$details[] = s("Depuis {value}", $eStructure['startedAt']);
		}

		// Qui répond de cette ferme : la première chose qu'on cherche en ouvrant sa fiche, et la seule
		// que ni le nom ni la commune ne disent. La portée est celle de la fiche, l'union des groupes du
		// lecteur, et non celle d'une carte.
		if($cUser->notEmpty()) {

			$names = [];

			foreach($cUser as $eUser) {
				$names[] = $eUser->getName();
			}

			$details[] = \Asset::icon('producer-fill').' '.implode(', ', $names);

		}

		return implode(' · ', $details);

	}

	/**
	 * Le bandeau, ou les courbes de niveau qui en tiennent lieu.
	 *
	 * ⚠️ Le second cas est le cas courant et non la dégradation : **5 917 fermes actives sur
	 * 5 922 n'ont pas d'image** (12/09/2026). Ce n'est donc pas un pis-aller à peine présentable,
	 * c'est ce que presque tout le monde verra — d'où le relief plutôt qu'un aplat, tiré de
	 * l'identifiant de la ferme : deux fermes voisines n'ont pas le même. Le motif est celui que
	 * la scène emploie déjà quand la carte manque (arbitrage, 12/09/2026).
	 *
	 * Le voile ne s'applique qu'à l'image, seul cas où le texte passe sur un fond qu'on ne
	 * choisit pas ; sur les courbes, qui sont claires, c'est le texte qui passe en sombre.
	 */
	private function getBanner(Structure $eStructure, string $inner): string {

		if($eStructure['banner'] === NULL) {
			return '<div class="profile-banner profile-banner-empty">'
				.'<div class="profile-banner-pattern">'.\main\DesignUi::getNoiseContours($eStructure['id'], self::MAP_WIDTH, self::BANNER_HEIGHT, self::MAP_STEP, background: NULL, strokeOpacity: self::BANNER_STROKE_OPACITY).'</div>'
				.$inner
				.'</div>';
		}

		$url = new \media\StructureBannerUi()->getUrlByElement($eStructure, 'l');

		return '<div class="profile-banner" style="background-image: url('.$url.');">'.$inner.'</div>';

	}

	/**
	 * Le logo chevauche la limite du bandeau.
	 *
	 * Sans logo, rien : une icône générique ne disait rien de la structure, et 90 % des fermes
	 * actives n'en ont pas (597 sur 5 922 le 12/09/2026) — c'est donc le cas courant qui portait
	 * une image inutile (arbitrage, 12/09/2026). Le titre reprend alors le bord, et le
	 * corps perd le décalage qui l'alignait sur lui.
	 */
	private function getMedallion(Structure $eStructure): string {

		if($eStructure['logo'] === NULL) {
			return '';
		}

		return '<div class="profile-medallion">'.StructureUi::getLogo($eStructure, '6.5rem').'</div>';

	}


	/**
	 * La scène : la carte porte les chiffres.
	 *
	 * Un seul objet au lieu de trois blocs côte à côte. Il n'y a plus de colonnes à équilibrer,
	 * donc plus de bas de cartes en escalier quand l'une porte une image et l'autre trois lignes —
	 * le défaut disparaît avec les cartes qui le causaient.
	 *
	 * ⚠️ **Quatre valeurs au plus, jamais cinq** : la grille est fixe et une cinquième case réduit
	 * les autres à l'illisible. Les valeurs manquantes ne laissent pas de trou, elles disparaissent
	 * et les autres se rangent à gauche — près de la moitié des fermes n'en renseignent qu'une ou
	 * deux, et une valeur seule flottant en troisième colonne se lit comme un défaut d'affichage.
	 */
	public function getScene(System $eSystem, \Collection $cProduction): string {

		if($eSystem->empty()) {
			return '';
		}

		$figures = array_filter(ProfileAiLib::getSystemFigures($eSystem, $cProduction));

		// La scène existe pour porter une carte et des chiffres. Sans les deux, il ne reste que
		// le fond dessiné : 22 rem de décoration qui n'apprend rien. Ce n'est pas un cas rare,
		// c'est le cas courant — 2 810 systèmes sur 5 928 au 07/09/2026 n'ont ni l'un ni l'autre.
		if($figures === [] and $eSystem['placeLngLat'] === NULL) {
			return '';
		}

		$h = '<div class="profile-scene">';

			$h .= $this->getSceneBackground($eSystem);
			$h .= $this->getScenePlace($eSystem);
			$h .= $this->getSceneFigures($figures);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les chiffres et le voile qui les porte, ou rien.
	 *
	 * ⚠️ Le voile ne se peint que s'il porte du texte : son dégradé va jusqu'à l'opacité
	 * pleine et son remplissage lui donne une hauteur propre, si bien que le rendre vide barre le
	 * bas de la carte d'un aplat sombre sans rien y écrire.
	 *
	 * ⚠️ Une case vide ne se rend pas non plus : elle laisserait un blanc dans la grille, et une
	 * ferme qui ne renseigne que son altitude verrait sa seule valeur flotter en deuxième
	 * colonne. Les valeurs présentes se rangent donc à gauche, en gardant la largeur d'un quart
	 * — le rythme tient, la colonne fixe non.
	 */
	private function getSceneFigures(array $figures): string {

		if($figures === []) {
			return '';
		}

		$h = '<div class="profile-scene-veil">';

			foreach($figures as [$label, $value, $detail, $isWord]) {

				$h .= '<div class="profile-scene-figure">';

					$h .= '<div class="profile-scene-key">'.$label.'</div>';
					$h .= '<div class="profile-scene-value'.($isWord ? ' profile-scene-value-word' : '').'">'.$value.'</div>';

					if($detail !== '') {
						$h .= '<div class="profile-scene-detail">'.$detail.'</div>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le fond de la scène : la carte, ou un dessin quand la ferme n'a pas de position.
	 *
	 * ⚠️ Une **image** et non la carte interactive du paquetage `map`. Mesuré le 2026-10-12 :
	 * celle-ci charge 2,52 Mo de bibliothèque et de feuilles, dont `mapbox-gl.js` 1,87 Mo et turf
	 * 564 Ko, avant la moindre tuile, sur une page que n'importe qui ouvre sans compte. L'image en pèse
	 * 152. Et une carte qu'on déplace invite à chercher la ferme, quand une image fixe dit
	 * « voici la région » : c'est ce que le centrage sur la commune promet, rien de plus.
	 */
	private function getSceneBackground(System $eSystem): string {

		if($eSystem['placeLngLat'] === NULL) {
			return '<div class="profile-scene-plain">'.\main\DesignUi::getNoiseContours($eSystem['structure']['id'], self::MAP_WIDTH, self::MAP_HEIGHT, self::MAP_STEP).'</div>';
		}

		$url = \map\MapboxUi::getStaticImageUrl($eSystem['placeLngLat'], self::MAP_ZOOM, self::MAP_WIDTH, self::MAP_HEIGHT, self::MAP_COLOR, self::MAP_RETINA);

		return '<img src="'.$url.'" class="profile-scene-map" alt="" loading="lazy">';

	}

	/**
	 * La pastille de lieu, absente quand il n'y a pas de lieu.
	 *
	 * ⚠️ `place` porte la commune d'une ferme réelle et le **nom du département** d'une ferme
	 * école, dont le point est le centre départemental. La pastille dit donc toujours ce que le
	 * point désigne, jamais plus précis que lui.
	 */
	private function getScenePlace(System $eSystem): string {

		if($eSystem['place'] === NULL) {
			return '';
		}

		return '<div class="profile-scene-place">'.encode($eSystem['place']).'</div>';

	}

	/**
	 * Un bloc par atelier, jamais de fusion, et un intitulé même quand il n'y en a qu'un.
	 *
	 * Les ateliers sont N lignes de `structureProduction` là où les cartes en sont une seule :
	 * les fusionner ferait passer douze ateliers pour une exploitation moyenne qui n'existe pas.
	 * Chacun porte ses propres surfaces, sa mécanisation, sa certification et son année — une
	 * ferme peut être en bio sur son maraîchage et en conversion sur son verger.
	 *
	 * ⚠️ `acceptOption()` (`module/Production.e.php:39`) dit quelles valeurs un métier accepte, et
	 * un atelier de transformation — boulangerie, conserverie, cosmétique — n'en accepte aucune :
	 * son bloc se réduit à sa ligne de titre, qui doit donc se tenir seule.
	 */
	public function getProductions(\Collection $cProduction): string {

		if($cProduction->empty()) {
			return '';
		}

		$h = '<section class="profile-block">';

			$h .= '<h2>'.p("Atelier", "Ateliers", $cProduction->count()).'</h2>';

			$h .= $this->getProductionList($cProduction);

		$h .= '</section>';

		return $h;

	}

	/**
	 * Des cartes, ou une ligne de jetons quand aucun atelier n'a plus que son intitulé.
	 *
	 * Une carte qui ne porte qu'un mot est une boîte vide, et 220 structures sur 2 504 n'ont que
	 * des ateliers de cette sorte — l'une en aligne douze, soit douze boîtes vides à la file.
	 *
	 * ⚠️ Le choix se fait sur le **bloc** et non sur chaque atelier : mélanger les deux formes
	 * ferait deux rythmes dans une même liste. Un atelier réduit à son intitulé au milieu
	 * d'ateliers remplis garde donc sa carte, ce sont ses voisines qui la tiennent.
	 */
	private function getProductionList(\Collection $cProduction): string {

		foreach($cProduction as $eProduction) {

			if($this->hasProductionDetails($eProduction)) {

				$h = '<div class="profile-productions">';

					foreach($cProduction as $eProductionCard) {
						$h .= $this->getProduction($eProductionCard);
					}

				$h .= '</div>';

				return $h;

			}

		}

		$h = '<div class="util-chips">';

			foreach($cProduction as $eProduction) {
				$h .= '<span class="badge badge-outline">'.encode(ProductionUi::get($eProduction['fqn'])).'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qu'un atelier porte en plus de son intitulé, et qui décide de la forme du bloc.
	 *
	 * ⚠️ Les trois mesures passent par `acceptOption()`, comme la carte : un atelier de
	 * transformation porte parfois une surface que son métier n'accepte pas et que la carte ne
	 * montre donc jamais. La compter ici rendrait une carte vide en croyant la remplir.
	 */
	private function hasProductionDetails(Production $eProduction): bool {

		if(
			$eProduction['organic'] !== Production::NO or
			$eProduction['startedAt'] !== NULL or
			$eProduction['description'] !== NULL
		) {
			return TRUE;
		}

		foreach(['area', 'areaGreenhouse', 'mechanisation'] as $property) {

			if($eProduction[$property] !== NULL and $eProduction->acceptOption($property)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	public function getProduction(Production $eProduction): string {

		$h = '<div class="profile-production">';

			$h .= '<div class="profile-production-head">';

				// Le métier se nomme même quand il n'y a qu'un atelier : sans lui, les surfaces
				// et la mécanisation se lisent comme des propriétés de la ferme entière, et sur
				// trois ateliers on ne saurait plus à qui appartient quoi.
				$h .= '<h3>'.encode(ProductionUi::get($eProduction['fqn'])).'</h3>';

				$h .= $this->getProductionLabel($eProduction);

				if($eProduction['startedAt'] !== NULL) {
					$h .= '<div class="profile-production-year">'.s("Depuis {value}", $eProduction['startedAt']).'</div>';
				}

			$h .= '</div>';

			$values = [];

			foreach(['area', 'areaGreenhouse', 'mechanisation'] as $property) {

				if($eProduction[$property] === NULL or $eProduction->acceptOption($property) === FALSE) {
					continue;
				}

				$values[ProductionUi::p($property)->label] = $this->getValue(ProductionUi::p($property), $eProduction[$property]);

			}

			if($values !== []) {

				$h .= '<div class="profile-production-values">';

					foreach($values as $label => $value) {
						$h .= '<div><span>'.$label.'</span><b>'.$value.'</b></div>';
					}

				$h .= '</div>';

			}

			if($eProduction['description'] !== NULL) {
				$h .= '<p class="profile-production-description">'.encode($eProduction['description']).'</p>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La certification de l'atelier, et non celle de la ferme.
	 *
	 * `structureProduction.organic` et `structure.organic` sont deux colonnes distinctes : la
	 * seconde est un jeton de l'en-tête, celle-ci qualifie ce seul métier.
	 */
	private function getProductionLabel(Production $eProduction): string {

		return match($eProduction['organic']) {
			Production::YES => '<span class="badge badge-outline color-production">'.s("Agriculture biologique").'</span>',
			Production::CONVERSION => '<span class="badge badge-outline profile-badge-conversion">'.s("En conversion").'</span>',
			Production::NO => ''
		};

	}

	/**
	 * Le voisinage, seul bloc de territoire qui apprenne quelque chose.
	 *
	 * Ce qui l'entourait est parti : la commune est déjà dans l'en-tête, et « 34 parcelles,
	 * 9 zones » sont deux compteurs qui n'apprennent rien — ni au visiteur, qui ne sait pas ce
	 * qu'ils valent, ni à l'exploitant, qui les connaît.
	 */
	public function getTerritory(\Collection $cStructureNeighbour): string {

		if($cStructureNeighbour->empty()) {
			return '';
		}

		$h = '<section class="profile-block">';

			$h .= '<h2>'.s("À proximité").'</h2>';
			$h .= $this->getNeighbours($cStructureNeighbour);

		$h .= '</section>';

		return $h;

	}

	/**
	 * Les fermes proches, nommées par leur commune et par une distance arrondie au kilomètre.
	 *
	 * ⚠️ Ce sont des géocodages à la commune et non des adresses : deux fermes du même village
	 * sont à zéro l'une de l'autre. Sous le kilomètre, la distance ne se donne donc pas — elle
	 * mesurerait l'écart entre deux mairies, pas entre deux exploitations.
	 */
	private function getNeighbours(\Collection $cStructureNeighbour): string {

		if($cStructureNeighbour->empty()) {
			return '';
		}

		$h = '<div class="util-cards util-cards-3">';

			foreach($cStructureNeighbour as $eStructureNeighbour) {

				$url = StructureUi::urlProfile($eStructureNeighbour);

				// Une ferme sans profil public se montre sans y mener : un deck sans nature, donc sans
				// chevron.
				$h .= ($url === '') ? '<div class="util-deck">' : '<a href="'.$url.'" class="util-deck">';

					$h .= '<div class="util-deck-head">';

						$h .= ($eStructureNeighbour['logo'] !== NULL)
							? StructureUi::getLogo($eStructureNeighbour, '2.5rem', 'util-icon-round')
							: '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('house').'</div>';

						$h .= '<div class="util-deck-body">';

							$h .= '<div class="util-deck-title">'.encode($eStructureNeighbour['name']).'</div>';

							$details = [];

							if($eStructureNeighbour['place'] !== NULL) {
								$details[] = encode($eStructureNeighbour['place']);
							}

							if($eStructureNeighbour['distance'] >= 1000) {
								$details[] = s("{value} km", round($eStructureNeighbour['distance'] / 1000));
							}

							if($details !== []) {
								$h .= '<p>'.\Asset::icon('geo-fill').' '.implode(' · ', $details).'</p>';
							}

						$h .= '</div>';

						if($url !== '') {
							$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
						}

					$h .= '</div>';

				$h .= ($url === '') ? '</div>' : '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un nom de ferme qui répond à « qui est-ce » sans quitter la page.
	 *
	 * ⚠️ **Elle ne se pose que sur un nom seul**, jamais sur une carte : une carte dit déjà le
	 * logo, la commune et les exploitants, et elle est le lien vers la fiche. Deux cibles dans une
	 * carte contrediraient « la carte est le lien » sans rien ajouter.
	 *
	 * ⚠️ **Et rien de ce qu'elle porte n'existe qu'en elle.** C'est ce qui rend acceptable qu'elle
	 * n'existe pas au doigt : `lime.js:2578` annule le clic dès `isTouch()`, quoi que dise
	 * `data-dropdown-hover`, donc un appui ouvrirait l'aperçu au lieu de la fiche — et le nom est
	 * le seul chemin vers elle. `profileAi.js` retire donc l'attribut au chargement sur tactile.
	 *
	 * La portée est celle de la page qui la porte : sur l'écran d'un groupe, ce que ce groupe lit
	 * de cette ferme. Passer d'un aperçu borné à une fiche qui montre l'union est le mouvement
	 * normal.
	 *
	 * Les ateliers ne se gardent pas ici : `ProfileAiLib::prepareRailProductions()` ne les charge
	 * que sur les fermes dont le rattachement ouvre la production, et laisse les autres avec une
	 * collection vide. Une seconde condition à cet endroit ferait deux réponses à une question.
	 */
	public function getStructureName(Structure $eStructure): string {

		$url = StructureUi::urlProfile($eStructure);

		if($url === '') {
			return '<div>'.encode($eStructure['name']).'</div>';
		}

		$h = '<div class="profile-name">';

			$h .= '<a href="'.$url.'" data-dropdown="bottom-start" data-dropdown-hover="true">'.encode($eStructure['name']).'</a>';

			// `dropdown-list-unstyled` et non `dropdown-list-minimalist`, qui portait les deux à la
			// fois : le dépouillement, et le plein écran. `lime.js:1587-1593` traite la seconde
			// comme `isTouch()` et bascule le panneau en plein écran quelle que soit la largeur,
			// avec un voile sombre et une couche d'historique — un survol qui assombrit la page
			// coûte plus cher que le clic qu'il devait épargner. Les deux classes retirent l'aplat
			// sombre de `lime.css:365`, une seule vient avec l'effet.
			$h .= '<div class="dropdown-list dropdown-list-unstyled profile-tooltip">';

				$h .= '<div class="util-vignette">';

					$h .= '<div>'.StructureUi::getLogo($eStructure, '2.5rem').'</div>';

					$h .= '<div>';

						$h .= '<div class="profile-tooltip-name">'.encode($eStructure['name']).'</div>';

						if($eStructure['legalCity'] !== NULL) {
							$h .= '<div class="color-muted font-xs">'.\Asset::icon('geo-fill').' '.encode($eStructure['legalCity']).'</div>';
						}

					$h .= '</div>';

				$h .= '</div>';

				if($eStructure['cProduction']->notEmpty()) {

					$h .= '<div class="util-chips mt-2">';

						foreach($eStructure['cProduction'] as $eProduction) {
							$h .= '<span class="badge badge-outline color-muted font-xs">'.encode(ProductionUi::get($eProduction['fqn'])).'</span>';
						}

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que lit quelqu'un à qui la ferme n'ouvre pas sa production.
	 *
	 * ⚠️ La mention de non-remplissage ne convient pas ici : elle décrit la ferme alors que c'est
	 * le **lecteur** qui est borné, et elle se trompe sur celles qui ont tout rempli — Gaec
	 * Yakaplanter porte une position, une surface et un climat, et sa fiche annonçait pourtant à
	 * un visiteur qu'elle n'avait rien renseigné.
	 */
	public function getHiddenNotice(): string {
		return '<div class="util-block-info">'.s("Cette structure ne partage pas ses données de production.").'</div>';
	}

	/**
	 * Ce que porte une fiche dont le système de production est vide.
	 *
	 * « Le bloc vide disparaît » est la bonne règle et laisse ici une page nue, qui se lit comme
	 * une panne et non comme un profil à remplir — et celui qui pourrait le remplir est justement
	 * celui qui la regarde. La mention se pose donc sur l'absence de la **scène** et non sur une
	 * page entièrement vide : une ferme qui a déclaré ses ateliers sans rien dire de son système
	 * a exactement le même trou, et c'est le seul écran d'où l'on peut le combler.
	 */
	public function getEmptyNotice(Structure $eStructure, bool $isMember): string {

		$h = '<div class="util-block-info">';

			$h .= s("Cette structure n'a pas encore renseigné son système de production.");

			if($isMember) {
				$h .= ' <a href="'.StructureUi::urlSettingsProduction($eStructure).'">'.s("Compléter ma fiche").'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que le propriétaire d'une fiche y règle, à l'endroit même de ce qu'il règle.
	 *
	 * Le réglage n'ouvre pas d'écran de préférences : on bascule à côté de ce qu'on masque, et
	 * l'écran de compte est administratif — l'e-mail, le SIRET, les adresses de facturation.
	 * Une visibilité de fiche y serait rangée loin de la fiche.
	 */



	/**
	 * La valeur telle que le describer la définit : son libellé d'enum, son unité.
	 *
	 * C'est là que vivent les deux, et les relire ici évite d'écrire « walking-tractor » ou une
	 * surface sans unité — deux façons de montrer la base plutôt que la donnée.
	 *
	 * ⚠️ La table des libellés ne s'interroge que pour une valeur d'enum, qui est une chaîne. Une
	 * surface décimale employée comme clé de tableau est convertie en entier par PHP, ce qui lève
	 * une notice et, en ajax, casse le JSON avant tout affichage. Le describer d'une mesure ne
	 * porte de toute façon aucun libellé : `values` peut aussi être une closure.
	 */
	private function getValue(\PropertyDescriber $d, mixed $value): string {

		$text = (is_string($value) and is_array($d->values)) ? ($d->values[$value] ?? $value) : $value;

		return ($d->append === NULL) ? (string)$text : $text.' '.$d->append;

	}

}
?>
