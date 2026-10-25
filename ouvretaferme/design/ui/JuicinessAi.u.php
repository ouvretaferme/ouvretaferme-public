<?php
namespace design;

/**
 * Les quatre sections de la famille « Animer un geste » de la page du design system. #design-motion
 * porte les tokens de durée et de courbe de juicinessAi.css, les conventions qui disent quand un
 * mouvement est dû, ce qui ne s'anime jamais, le mouvement réduit, la règle des célébrations, puis la
 * table geste, durée, courbe. #design-feedback porte le retour sur geste (util-flash, util-pending,
 * util-waiting), #design-vanish ce qu'une réponse retire (util-vanish), #design-logoWait l'attente
 * d'une vue lourde jusqu'à sa première image (util-logo-wait).
 *
 * Sortie de Design.u.php comme DevAiUi et DecorAiUi : Design.u.php appelle chaque section à sa place
 * dans la page.
 *
 * Les démonstrations se rejouent sans script. Le bouton « Rejouer » est le libellé d'une case cachée,
 * et chaque animation de démonstration a deux @keyframes identiques sous deux noms (designAi.css) :
 * cocher ou décocher la case change le nom de l'animation, ce qui la fait repartir du début. Une
 * classe retirée puis reposée demanderait un script, et la page n'en charge aucun.
 */
class JuicinessAiUi {

	public function motion(): string {

		$h = '<p>'.s("Un mouvement prend l'une de ces durées et l'une de ces courbes, par son token.").'</p>';
		$h .= '<p class="util-annotation">'.s("On écrit var(--duration-sm), jamais 0.2s. Ce qui apparait ou se déplace passe par transform et opacity. background-color, color, border-color et box-shadow sont admis pour un survol ou un état, en --duration-xs. Jamais de transition sur ce qui recalcule la mise en page, tailles et positions (width, height, top, left, inset), marges et espacements (margin, padding, gap), épaisseur de bordure, police et interligne, flex et grille, ni de transition: all.").'</p>';

		$h .= $this->getTokens();

		$h .= '<h3 class="mt-2">'.s("Un panneau ou un nœud ajouté parait sans attendre").'</h3>';
		$h .= '<p>'.s("Un panneau qui s'ouvre et un nœud qu'une réponse ajoute suivent une réponse du serveur déjà attendue : ils paraissent tout de suite, et aucun mouvement ne retarde leur lecture. Un ajout qu'il faut repérer de loin s'éclaire si l'action le demande, comme une écriture. Un menu déroulant, une bulle et tout ce qui s'ouvre à chaque geste gardent leur apparition d'aujourd'hui.").'</p>';

		$h .= $this->getConvention(
			'deplacement',
			s("Ce qui change de place se déplace"),
			'<p class="util-annotation">'.s("L'élément rejoint sa nouvelle place en --duration-md et --ease-in-out, au lieu d'y sauter.").'</p>',
			$this->getMove()
		);

		$h .= '<h3 class="mt-2">'.s("Ce qui ne s'anime jamais").'</h3>';
		$h .= '<ul>';
			foreach($this->getNever() as $never) {
				$h .= '<li>'.$never.'</li>';
			}
		$h .= '</ul>';

		$h .= '<h3 class="mt-2">'.s("Mouvement réduit").'</h3>';
		$h .= '<p>'.s("Quand le système demande moins d'animations, ce qui change de taille, de forme ou de position prend une durée infime et s'affiche tout de suite, boutique comprise. Les fondus d'opacité et de couleur restent, une apparition se fait alors sans glissement, et l'attente continue de tourner. La fin d'animation part toujours.").'</p>';
		$h .= '<p class="util-annotation">'.s("La règle s'écrit en 0.01ms, jamais en animation: none ni en durée nulle, sans quoi animationend ne part plus et ce qui attend la fin d'une animation pour retirer une classe ou un nœud reste posé. Chaque mouvement du socle déclare sa variante dans le bloc prefers-reduced-motion de juicinessAi.css, sans sélecteur sur *, pour que les animations d'un package gardent la leur. Un mouvement de package déclare la sienne dans sa feuille, sous la même règle.").'</p>';
		$h .= $this->getReduced();

		$h .= '<h3 class="mt-2">'.s("Célébrations").'</h3>';
		$h .= '<p>'.s("util-celebrate est réservé à un moment qui n'arrive qu'une fois dans la vie d'un compte ou d'une structure, aujourd'hui une souscription confirmée ou une invitation acceptée. Un geste qui se répète (cocher, enregistrer, vendre) ne se célèbre jamais.").' <a href="#design-seeds">'.s("Voir util-celebrate").'</a></p>';

		$h .= '<h3 class="mt-2">'.s("Quel geste prend quel mouvement").'</h3>';
		$h .= $this->getUsages();

		return $h;

	}

	private function getTokens(): string {

		$tokens = [
			'--duration-xs' => '0.15s',
			'--duration-sm' => '0.2s',
			'--duration-md' => '0.25s',
			'--duration-lg' => '0.5s',
			'--duration-xl' => '0.7s',
			'--duration-loop' => '1s',
			'--duration-step' => '0.1s',
			'--ease-out' => s("apparaitre, confirmer"),
			'--ease-in-out' => s("se déplacer"),
			'--ease-spring' => s("célébrer"),
			'--ease-linear' => s("tourner sans fin"),
		];

		$h = '<div class="design-motion-tokens">';
			foreach($tokens as $token => $use) {
				$h .= '<span><code>'.$token.'</code> <span class="util-annotation">'.$use.'</span></span>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une convention : sa règle, puis la même démonstration sans mouvement et avec la règle. Seule la
	 * colonne « avec la règle » porte design-motion-after, que les animations de designAi.css ciblent.
	 *
	 * La règle arrive avec son paragraphe, parce que c'est lui qui décide de la vue : un <p> simple reste
	 * dans la vue de lecture, un <p class="util-annotation"> n'est montré qu'aux agents.
	 */
	private function getConvention(string $anchor, string $title, string $rule, string $demo): string {

		$h = '<h3 class="mt-2">'.$title.'</h3>';
		$h .= $rule;

		$h .= '<div id="convention-mouvement-'.$anchor.'" class="util-block">';
			$h .= new \util\FormUi()->inputCheckbox(NULL, attributes: ['id' => 'design-motion-replay-'.$anchor, 'class' => 'design-motion-replay', 'hidden' => 'hidden']);
			$h .= '<div class="design-pair">';
				$h .= '<div>';
					$h .= '<h4>'.s("Sans mouvement").'</h4>';
					$h .= '<div class="design-motion-demo">'.$demo.'</div>';
				$h .= '</div>';
				$h .= '<div class="design-motion-after">';
					$h .= '<h4>'.s("Avec la règle").'</h4>';
					$h .= '<div class="design-motion-demo">'.$demo.'</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<label for="design-motion-replay-'.$anchor.'" class="btn btn-sm btn-outline-primary">'.s("Rejouer").'</label>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le déplacement, rejoué avec la règle du mouvement réduit simulée par une seconde case : les deux
	 * lignes rejoignent leur place en 0.01ms, comme sous prefers-reduced-motion.
	 */
	private function getReduced(): string {

		$form = new \util\FormUi();

		$h = '<div id="convention-mouvement-reduit" class="util-block">';
			$h .= $form->inputCheckbox(NULL, attributes: ['id' => 'design-motion-reduce', 'class' => 'design-motion-reduce', 'hidden' => 'hidden']);
			$h .= $form->inputCheckbox(NULL, attributes: ['id' => 'design-motion-replay-reduit', 'class' => 'design-motion-replay', 'hidden' => 'hidden']);
			$h .= '<div class="design-motion-tools">';
				$h .= '<label for="design-motion-reduce" class="btn btn-sm btn-outline-primary">'.s("Simuler le mouvement réduit").'</label>';
				$h .= '<label for="design-motion-replay-reduit" class="btn btn-sm btn-outline-primary">'.s("Rejouer").'</label>';
			$h .= '</div>';
			$h .= '<div class="design-motion-after">';
				$h .= '<div class="design-motion-demo">'.$this->getMove().'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le retour sur geste (L2) : util-flash, util-pending et util-waiting, montrés avec leurs vraies classes.
	 * La page ne charge aucun script : le voile joue au chargement puis se rejoue par la case cachée, dont
	 * designAi.css change le nom de l'animation ; le bouton en attente est figé dans son état passé
	 * --duration-sm ; le bloc en attente pulse sans fin, comme pendant une requête qui ne répond pas.
	 */
	public function feedback(): string {

		$form = new \util\FormUi();

		$h = '<p>'.s("Une écriture dont le résultat ne se voit pas de lui-même éclaire une fois ce qui a changé, à la place d'un message de succès qui ne dirait que ce que l'écran montre. Une case ou un interrupteur prend son état final au clic et ne s'éclaire pas. Un lien qui ouvre un panneau ou qui navigue n'affiche jamais de sablier.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-flash se décide en PHP, dans l'action : new ReloadAction(highlight: [sélecteurs]), new BackAction(highlight: [sélecteurs]) ou qs(…)->outerHtml(\$html, highlight: TRUE). MainJuiciness (juicinessAi.js) attend que plus aucune requête ne soit en vol et qu'aucun panneau ne soit en train de se fermer, pose la classe sur chaque nœud trouvé et la retire à animationend. Un sélecteur absent de l'écran n'éclaire rien, et aucun message ne le remplace. La teinte est --info, celle de la section. Un script de package qui déplace ou change un nœud sans requête l'éclaire par MainJuiciness.flash(node), au moment où le nœud est à sa place, sans écrire sa propre animation. Le voile occupe le ::after du nœud, qui doit être libre. util-flash-success, posé par MainJuiciness.flash(node, 'success'), éclaire en vert et deux fois plus longtemps le nœud qu'une écriture vient de régler, une ligne associée à son produit : l'état gagné se lit sur la ligne avant que le voile ne s'éteigne.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-pending se pose seul sur le bouton d'envoi d'une écriture, un [type=\"submit\"] de formulaire ou un .btn[data-ajax] en POST, le temps de la requête. Le bouton garde sa couleur et sa largeur ; au-delà de --duration-sm, le sablier remplace le libellé. Un bouton data-waiter garde son libellé d'attente. Un interrupteur data-optimistic prend son état au clic et revient à son état d'avant si la réponse échoue, le message passant par Lime.Alert.").'</p>';
		$h .= '<p class="util-annotation">'.s("util-waiting se pose sur un bloc que le serveur recalcule après un choix, un champ qui dépend d'un autre ou un total : le bloc porte data-waiting-block dans son markup, et le script qui le recalcule le nomme par new Ajax.Query().trigger(bloc). MainJuiciness (juicinessAi.js) pose la classe à ajaxBeforeFetch et la retire à l'image qui suit ajaxAfterFetch, réponse réussie ou échouée, quand plus aucune requête n'est en vol sur ce bloc : une requête qui prend le relais sur le même bloc le garde pâle sans faire repartir le pulse. Au-delà de --duration-sm, le bloc pâlit et pulse sans rien déplacer. Sous mouvement réduit, il reste pâle sans pulser. Un bouton garde util-pending.").'</p>';

		$h .= '<div id="convention-mouvement-retour" class="util-block">';
			$h .= $form->inputCheckbox(NULL, attributes: ['id' => 'design-motion-replay-retour', 'class' => 'design-motion-replay', 'hidden' => 'hidden']);
			$h .= '<div class="design-pair">';
				$h .= '<div>';
					$h .= '<h4>'.s("Le nœud nommé s'éclaire").'</h4>';
					$h .= '<div class="design-motion-demo">';
						$h .= '<div class="util-block util-flash">'.s("Planche 2 · Carotte Nantaise · Désherbage fait le 12/05").'</div>';
						$h .= '<div class="util-block util-flash util-flash-success">'.s("Chou Rave · 4,250 kg · Chou rave (kg)").'</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div>';
					$h .= '<h4>'.s("Le bouton attend la réponse").'</h4>';
					$h .= '<div class="design-motion-demo design-motion-buttons">';
						$h .= '<button type="button" class="btn btn-primary">'.s("Enregistrer").'</button>';
						$h .= '<button type="button" class="btn btn-primary util-pending"><span class="util-pending-label">'.s("Enregistrer").'</span><span class="util-pending-icon"><span class="util-animate-turn">'.\Asset::icon('hourglass-top').'</span></span></button>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div>';
					$h .= '<h4>'.s("Le bloc attend son recalcul").'</h4>';
					$h .= '<div class="design-motion-demo">';
						$h .= '<div class="util-block util-waiting">'.s("Bascule de TVA · 44588 vers 44571 · 500,00 €").'</div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<label for="design-motion-replay-retour" class="btn btn-sm btn-outline-primary">'.s("Rejouer").'</label>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le retrait (L3) : la copie montrée est la vraie util-vanish de juicinessAi.css, posée à la place du
	 * premier nœud, que la liste a déjà refermée. La page ne charge aucun script : la copie s'efface au
	 * chargement, puis se rejoue par la case cachée, dont designAi.css change le nom de l'animation.
	 */
	public function vanish(): string {

		$remaining = '<div class="util-block">'.s("Planche 2 · Carotte Nantaise").'</div>';
		$remaining .= '<div class="util-block">'.s("Planche 3 · Poireau d'hiver").'</div>';

		$h = '<p>'.s("Un nœud retiré part d'un coup, et ses voisins prennent sa place sans glisser. Quand ce qui part n'est pas sous les yeux, l'action peut demander que sa copie s'efface à sa place pendant que les voisins remontent dessous. Un script qui cherche ces nœuds par classe juste après la réponse écarte la copie par :not([inert], [inert] *), ou les cherche par identifiant.").'</p>';
		$h .= '<p class="util-annotation">'.s("La copie se décide en PHP, dans la vue : qs(…)->remove(vanish: TRUE), qui ne se combine ni avec mode ni avec delay. ajax.js émet ajaxVanish sur chacun de ces nœuds avant de rendre la première instruction de la réponse, puis les retire dans leur instruction : l'instruction suivante ne trouve que l'écran sans eux. MainJuiciness (juicinessAi.js) pose à la place du nœud, dans son parent, une copie en position absolue sans id, name, for ni data-ajax, rendue inert, avec util-vanish, et la retire à animationend. Si son voisin suivant est parti sans copie, elle va en dernier dans le parent, et si le parent est parti dans la même réponse, dans l'ancêtre positionné le plus proche. Une ligne ou une cellule de tableau part sans copie. Pendant 0,15 s, la copie compte dans :last-child, :nth-child et :empty de son parent, si bien que le :nth-child des voisins qui la suivent ne bascule qu'à la fin de l'effacement. Elle répond encore à ses classes et à ses data-*, sauf data-ajax.").'</p>';

		$h .= '<div id="convention-mouvement-retrait" class="util-block">';
			$h .= new \util\FormUi()->inputCheckbox(NULL, attributes: ['id' => 'design-motion-replay-retrait', 'class' => 'design-motion-replay', 'hidden' => 'hidden']);
			$h .= '<div class="design-pair">';
				$h .= '<div>';
					$h .= '<h4>'.s("Il part d'un coup").'</h4>';
					$h .= '<div class="design-motion-demo">';
						$h .= '<div class="design-motion-list">'.$remaining.'</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div>';
					$h .= '<h4>'.s("Sa copie s'efface à sa place").'</h4>';
					$h .= '<div class="design-motion-demo">';
						$h .= '<div class="design-motion-list">'.$remaining.'<div class="util-block util-vanish" inert>'.s("Planche 1 · Radis de 18 jours").'</div></div>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<label for="design-motion-replay-retrait" class="btn btn-sm btn-outline-primary">'.s("Rejouer").'</label>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'attente d'une vue lourde : le vrai util-logo-wait, qui bat sans fin puisque la page ne charge aucun
	 * script pour le retirer, sur le fond par défaut puis sur celui qu'un hôte choisit.
	 */
	public function logoWait(): string {

		$h = '<p>'.s("Une vue qui met plusieurs secondes à montrer sa première image, comme une scène en 3D, se couvre du logo de Ouvretaferme, dont les quatre feuilles battent l'une après l'autre jusqu'à cette image. Sous mouvement réduit, le logo reste plein, sans battre.").'</p>';
		$h .= '<p class="util-annotation">'.s("DesignUi::getLogoWait() rend le calque, à poser en enfant direct de la vue, qui doit être positionnée. Il la couvre entière, au-dessus de ses commandes, et reprend son arrondi. Le script de la vue le retire à sa première image, et aussi quand elle n'a pas pu s'ouvrir, pour que le message d'erreur ne se lise pas sous un logo qui bat encore. La vue choisit le fond du calque par --util-logo-wait-background, posée sur elle, de la couleur de sa première image : le ciel, pour le plan en 3D. Sans elle, le fond est celui d'une carte. L'étage du calque est 2, et une vue dont une commande monte plus haut le relève par --util-logo-wait-layer.").'</p>';

		$h .= '<div id="convention-mouvement-attente" class="util-block">';
			$h .= '<div class="design-pair">';
				$h .= '<div>';
					$h .= '<h4>'.s("Sur le fond par défaut").'</h4>';
					$h .= '<div class="design-motion-demo design-motion-wait">'.\main\DesignUi::getLogoWait().'</div>';
				$h .= '</div>';
				$h .= '<div>';
					$h .= '<h4>'.s("Sur le fond que la vue choisit").'</h4>';
					$h .= '<div class="design-motion-demo design-motion-wait design-motion-wait-sky">'.\main\DesignUi::getLogoWait().'</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	private function getMove(): string {

		$h = '<div class="design-motion-move">';
			$h .= '<div class="util-block design-motion-up">'.s("Planche 2 · Carotte Nantaise").'</div>';
			$h .= '<div class="util-block design-motion-down">'.s("Planche 1 · Radis de 18 jours").'</div>';
		$h .= '</div>';

		return $h;

	}

	private function getNever(): array {

		return [
			s("Le défilement de la page, qu'un geste ne déplace jamais."),
			s("Ce qui recalcule la mise en page (tailles, positions, marges, espacements, épaisseur de bordure, police, interligne, flex et grille), et transition: all."),
			s("Un écran ou un tableau entier remplacé par une réponse. Seul le nœud que l'instruction marque s'anime."),
			s("Une valeur qu'on est en train de saisir."),
			s("L'apparition d'un menu déroulant ou de ce qui s'ouvre à chaque geste, qui reste celle d'aujourd'hui."),
			s("Une boucle décorative. Seule l'attente tourne ou pulse (util-animate-turn, util-waiting), et elle s'arrête avec la requête."),
			s("Un clignotement. Un retour sur geste joue une fois, jamais en boucle."),
		];

	}

	private function getUsages(): string {

		$usages = [
			[s("Survol, appui, focus d'un contrôle"), '--duration-xs', '--ease-out'],
			[s("Un menu déroulant ou une bulle, qui s'ouvrent à chaque geste"), s("leur apparition actuelle"), s("inchangée")],
			[s("Un nœud qu'une réponse ajoute"), s("aucune, il parait tout de suite"), s("aucune")],
			[s("Un panneau ou un calque qui s'ouvre"), s("aucune, il parait tout de suite"), s("aucune")],
			[s("Un élément qui change de place"), '--duration-md', '--ease-in-out'],
			[s("Un nœud qui s'éclaire après une écriture, ou qu'un script de package vient de changer (util-flash)"), '--duration-lg', '--ease-out'],
			[s("Le fond qui s'assombrit derrière un panneau"), '--duration-xl', '--ease-out'],
			[s("Un élément qui arrive sur un grand écran et qu'il faut repérer (planning, assolement)"), '--duration-xl', '--ease-out'],
			[s("Un panneau qui se ferme"), s("celle d'aujourd'hui"), s("inchangée")],
			[s("Un nœud qu'une réponse retire"), s("aucune, ou --duration-xs pour la copie de remove(vanish: TRUE)"), '--ease-out'],
			[s("Une célébration (util-celebrate)"), s("0.9s, hors échelle"), '--ease-spring'],
			[s("Les éléments d'une cascade, qui partent l'un après l'autre"), s("un délai proportionnel à --duration-step"), s("celle du mouvement")],
			[s("L'attente qui tourne"), '--duration-loop', s("celle du composant")],
		];

		$h = '<table class="tr-even">';
			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Geste").'</th>';
					$h .= '<th>'.s("Durée").'</th>';
					$h .= '<th>'.s("Courbe").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';
			$h .= '<tbody>';
				foreach($usages as [$gesture, $duration, $curve]) {
					$h .= '<tr>';
						$h .= '<td>'.$gesture.'</td>';
						$h .= '<td>'.$duration.'</td>';
						$h .= '<td>'.$curve.'</td>';
					$h .= '</tr>';
				}
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

}
?>
