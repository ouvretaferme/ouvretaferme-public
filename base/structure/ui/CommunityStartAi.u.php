<?php
namespace structure;

/**
 * La mise en route d'un groupe : sur son accueil, une étape par chose qu'il a à décider.
 *
 * Planche validée le 11/09/2026, contrat dans `.claude/state/INRAE/parcours-formation.md`.
 * Le composant est `onboarding` (`main/onboarding.css`), celui qui mène déjà une ferme à travers son
 * entrée dans un groupe : rien de neuf en CSS.
 *
 * ⚠️ **Aucun état par étape n'est enregistré.** Chacune se lit sur ce qui existe déjà — la
 * présentation du groupe, ses sous-groupes, ses invitations — et le parcours s'ouvre sur la
 * première qui manque. Le seul état stocké est `Feature.hasStart`, qui dit si le parcours a
 * encore lieu d'être ; il appartient au chantier AMAP, qui l'a posé pour sa page de démarrage.
 *
 * ⚠️ **Une étape passée n'est pas une étape faite**, et rien ne mémorise qu'on l'a passée. Elle
 * est donc reproposée à la visite suivante : c'est vrai, elle n'est pas faite, et fermer le
 * parcours pour de bon reste à un clic. Le prix de ce choix est nul en base ; celui de l'inverse
 * aurait été une colonne par étape.
 */
class CommunityStartAiUi {

	/** Le parcours ouvert sur l'accueil, sans autre écran où revenir. */
	const ORIGIN_HOME = 'start';

	/**
	 * Les écrans d'où l'on part vers le parcours, nommés par `origin` dans l'adresse et les
	 * formulaires, pour que sa fin y ramène.
	 *
	 * ⚠️ Les noms sont ceux des onglets que posent les vues du groupe (`$t->tab`) : le rappel lit
	 * l'onglet de l'écran qui le porte, sans table d'appariement. Un écran sans onglet propre, ou
	 * dont l'onglet est l'accueil, mène au parcours sans origine, et sa fin revient à l'accueil.
	 */
	const ORIGINS = [self::ORIGIN_HOME, 'members', 'structures', 'schools', 'production', 'learning', 'quotation', 'settings'];

	/**
	 * Les étapes et leur aperçu, c'est-à-dire le parcours entier.
	 *
	 * Le nom se lit à côté de `StructureCommunityUi::getShareSteps()`, l'autre parcours de ce
	 * package : les deux montent le même composant, l'un pour une ferme qui entre dans un groupe,
	 * l'autre pour un organisme de formation qui démarre.
	 *
	 * `$step` est l'étape à ouvrir : celle que l'adresse demande après un enregistrement, sinon la
	 * première qui manque. La page la calcule, l'UI ne fait que rendre.
	 */
	public static function getStartSteps(Structure $eStructure, \Collection $cSection, array $counts, Invite $eInviteLink, int $step, string $origin): string {

		\Asset::css('main', 'onboarding.css');
		\Asset::js('main', 'onboarding.js');
		\Asset::js('structure', 'communityStartAi.js');

		$done = self::getDone($eStructure, $cSection, $counts);

		// L'introduction précède le parcours au lieu d'y entrer : son cadre brossé prend la largeur
		// du conteneur, et le panneau des étapes reste borné dessous (`onboarding-panel-plain`).
		$h = self::getIntroduction($eStructure);

		$h .= '<div class="onboarding" data-step="'.$step.'" onrender="StructureCommunityStartAi.start(this)">';

			// Le lien qui quitte un parcours, à la forme de la création d'une formation : il dit où
			// mène la fin sans phrase de plus, et permet d'y retourner sans finir. Il se borne au
			// panneau qu'il surmonte (`onboarding-bound`), pas à l'introduction plus large au-dessus.
			if($origin !== self::ORIGIN_HOME) {
				$h .= '<div class="onboarding-header onboarding-bound">';
					$h .= '<a href="'.self::urlOrigin($eStructure, $origin).'" class="onboarding-header-skip">'.self::getOriginLabel($eStructure, $origin).'</a>';
				$h .= '</div>';
			}

			// ⚠️ `util-card` **et** `onboarding-panel` : le second ne pose que la grille et
			// `padding: 0`, en comptant sur la carte pour le fond, la bordure, le rayon et
			// l'ombre. Seul, le panneau laisse les étapes poser sur le fond de page.
			//
			// ⚠️ **`onboarding-panel-plain` : ce parcours n'a pas d'aperçu.** Il en a porté un, et
			// aucune étape n'avait de quoi le remplir : la carte du groupe montrait l'état d'avant
			// tant qu'on remplissait, la liste des promotions et le compte des invitations
			// tenaient sur une ligne. La colonne de droite est donc retirée, et les questions
			// remplissent un panneau borné à 60 rem et centré (`onboarding.css`).
			$h .= '<div class="util-card onboarding-panel onboarding-panel-plain">';

				$h .= '<div class="onboarding-steps">';

					$h .= self::getProgress($eStructure, $done);

					// ⚠️ **L'ordre et les numéros viennent de `getStepSlugs()`**, jamais d'une
					// suite écrite ici : le parcours n'a pas le même nombre d'étapes selon les
					// groupes, et une étape rendue sous un numéro que la barre ignore ne
					// s'afficherait jamais.
					foreach(self::getStepSlugs($eStructure) as $step => $slug) {

						$h .= match($slug) {

							// ⚠️ **Une étape, deux formes, et un seul parcours.** La promotion est
							// l'objet d'un organisme de formation — sans elle personne n'a où
							// entrer —, quand un réseau accueille ses fermes sans rien ranger et
							// n'a besoin que d'un usage. La question posée est la même : ce sans
							// quoi le groupe ne sert à rien.
							'description' => self::getDescription($eStructure, $step, $origin),
							'feature' => $eStructure->isEducation()
								? self::getSections($eStructure, $cSection, $step, $origin)
								: self::getFeatures($eStructure, $step, $origin),
							'share' => self::getShares($eStructure, $step, $origin),
							'invite' => self::getInvites($eStructure, $eInviteLink, $step, $origin),

						};

					}

					// Le conseil suit les questions au lieu de vivre dans une colonne à part :
					// `margin-top: auto` le pose au bas de la colonne, qui est une boîte flex. Un
					// seul conteneur et un `span` par étape, comme la page design system le
					// montre — c'est `.onboarding-tip [data-onboarding-tip].is-current` qui décide
					// lequel se voit, et le composant le bascule tout seul à chaque `goTo()`.
					$h .= '<div class="util-card onboarding-tip">'.\Asset::icon('info-circle').'<div>';

						foreach(self::getTips($eStructure) as $tipStep => $tip) {
							$h .= '<span data-onboarding-tip="'.$tipStep.'">'.$tip.'</span>';
						}

					$h .= '</div></div>';

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * « a », « a et b », « a, b et c ».
	 */
	protected static function joinNames(array $names): string {

		$last = array_pop($names);

		return ($names === []) ? $last : implode(', ', $names).' '.s("et").' '.$last;

	}

	/**
	 * Le bandeau d'arrivée, une fois et une seule.
	 *
	 * ⚠️ **Il ne se souvient de rien, et c'est voulu.** L'adresse qui suit la fermeture du parcours
	 * le demande ; la croix l'enlève de l'écran, et la visite suivante arrive sans le paramètre,
	 * donc sans bandeau. Une colonne « bandeau déjà vu » aurait été un second état à tenir pour un
	 * texte qui se lit une fois.
	 *
	 * ⚠️ **Les suites ne recouvrent pas le parcours.** Le bandeau a porté un bouton par
	 * fonctionnalité éteinte : c'était reposer à l'arrivée la question que l'étape 2 vient de
	 * poser, avec d'autres mots et sans dire ce que chacune fait. Ce qui reste est ce que le
	 * parcours n'a pas fait : l'équipe quand personne ne l'a encore rejointe, et en formation les
	 * étudiants, que le parcours n'invite pas.
	 *
	 * ⚠️ **Il paraît sur l'écran où la fin ramène**, que `$tab` nomme : « ici » ne désigne des
	 * membres que sur l'accueil et sur Membres, et l'onglet Formations d'un groupe qui n'en a
	 * aucune est lui-même la création de la première.
	 */
	public static function getWelcome(Structure $eStructure, \Collection $cSection, array $counts, string $tab): string {

		// ⚠️ Le bandeau paraît **à la place** du parcours, donc rien n'a chargé le fichier qui
		// porte sa croix : il le demande pour son compte, avec `onboarding.js`, dont sa classe hérite.
		\Asset::js('main', 'onboarding.js');
		\Asset::js('structure', 'communityStartAi.js');

		// ⚠️ L'adresse qui demande ce bandeau s'efface dès qu'il paraît : sans cela un rechargement
		// le referait voir, et un favori pris à ce moment-là le rallumerait à chaque visite — alors
		// que sa croix ne doit pas revenir. `removeArgument()` remplace l'entrée d'historique au
		// lieu d'en ajouter une, c'est le mécanisme que `Lime.Tab.restore()` emploie pour `tab`.
		$h = '<div class="util-block-info" data-onboarding-welcome onrender="history.removeArgument(\'demarrage\')">';

			$h .= \Asset::icon('check-lg', ['class' => 'util-block-icon']);

			$h .= '<div class="util-block-info-text">';

				$h .= '<div class="util-block-info-title">'.self::getWelcomeTitle($eStructure, $cSection).'</div>';

				$help = [];

				// ⚠️ `invitesSent` et non `invites` : le lien d'invitation est une invitation sans
				// adresse, que personne n'a reçue.
				if($counts['invitesSent'] > 0) {
					$help[] = p("{value} invitation est partie.", "{value} invitations sont parties.", $counts['invitesSent']);
				}

				if(in_array($tab, ['home', 'members'], TRUE)) {

					if($eStructure->isEducation() and $cSection->notEmpty()) {
						$help[] = s("Vos étudiants apparaîtront ici à mesure qu'ils rejoignent {name}.", ['name' => encode($cSection->first()['name'])]);
					} else if($eStructure->isEducation() === FALSE) {
						$help[] = s("Vos membres apparaîtront ici à mesure qu'ils rejoignent {the}.", ['the' => StructureUi::getText($eStructure, 'the')]);
					}

				}

				if($help !== []) {
					$h .= '<div class="util-block-info-help">'.implode(' ', $help).'</div>';
				}

			$h .= '</div>';

			$h .= '<div class="util-block-info-actions">';

				// Le seul bouton plein, tant que le groupe n'a aucune formation. Sa condition est la
				// fonctionnalité et non le type : un groupe de formation le reçoit aussi.
				if(
					$eStructure->acceptLearning() and
					$counts['courses'] === 0 and
					$tab !== 'learning'
				) {
					$h .= '<a href="'.StructureUi::urlCommunityLearning($eStructure).'" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Créer la première formation").'</a>';
				}

				// Ce que le groupe demande, quand il ne demande rien. C'est ce que disait l'alerte
				// de la page des fermes, mais ici et sous la forme qui convient : un geste proposé
				// à l'endroit d'où l'on agit, et non un état déclaré anormal au-dessus d'une liste
				// qui n'a rien à voir. Muet quand la question ne se pose pas : un groupe sans droit
				// au choix n'a rien à décider.
				if(self::acceptShares($eStructure) and self::getSharesAsked($eStructure) === []) {
					$h .= '<a href="'.StructureUi::urlStructureGeneral($eStructure).'&tab=structure-sharing" class="btn btn-outline-primary">'.s("Choisir ce que {the} demande", ['the' => StructureUi::getText($eStructure, 'the')]).'</a>';
				}

				// Le parcours d'un groupe de formation vient de proposer ses formateurs : il ne reste
				// que ses étudiants à faire entrer, et c'est toujours vrai à la sortie du parcours.
				if($eStructure->isEducation()) {
					$h .= '<a href="'.StructureUi::urlCommunitySections($eStructure).'" class="btn btn-outline-primary">'.s("Inviter vos étudiants").'</a>';
				} else if($counts['staff'] === 0 and array_key_exists('staff', $eStructure->getAudienceRoles())) {
					// Muet quand l'équipe est déjà là ou déjà invitée (`staff` compte les deux) :
					// le bandeau dit ce qui reste, pas ce qui existe. Et muet quand le type n'a pas
					// d'équipe à inviter, comme une communauté de producteurs.
					$h .= '<a href="'.StructureUi::urlMembers($eStructure).'" class="btn btn-outline-primary">'.s("Inviter l'équipe {of}", ['of' => StructureUi::getText($eStructure, 'of')]).'</a>';
				}

				$h .= '<a data-action="onboarding-welcome-close" title="'.s("Fermer").'" class="util-block-info-close">'.\Asset::icon('x-lg').'</a>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que le parcours a mis en place, nommé et non compté, dans les mots du type : une
	 * promotion pour un organisme, les fonctionnalités pour les autres. Rien de ce qui n'a pas été
	 * fait, ni les invitations, que l'aide dit en dessous.
	 */
	protected static function getWelcomeTitle(Structure $eStructure, \Collection $cSection): string {

		if($eStructure->isEducation()) {

			$names = match($cSection->count()) {
				0 => [],
				1 => [s("sa promotion {name}", ['name' => encode($cSection->first()['name'])])],
				default => [s("ses {value} promotions", $cSection->count())]
			};

		} else {
			$names = array_map(fn(string $feature) => self::getFeatureName($eStructure, $feature), self::getFeaturesOn($eStructure));
		}

		$ready = StructureUi::getText($eStructure, 'ready');

		if($names === []) {
			return s("{ready}.", ['ready' => $ready]);
		}

		return s("{ready}, avec {value}.", ['ready' => $ready, 'value' => self::joinNames($names)]);

	}

	/**
	 * Une fonctionnalité dite comme ce que le groupe a désormais.
	 *
	 * ⚠️ Un `match` sans `default` : une fonctionnalité ajoutée à `CommunityFeatureAiLib::FEATURES`
	 * sans nom ici fait tomber le bandeau au lieu d'en disparaître.
	 */
	protected static function getFeatureName(Structure $eStructure, string $feature): string {

		return match($feature) {
			// Le réseau compare les plans de culture de ses fermes, il n'en tient pas.
			'hasCommunityVegetable' => s("le maraîchage"),
			'hasQuotation' => s("ses relevés de prix"),
			'hasCommunitySections' => s("ses {sections}", ['sections' => mb_strtolower(StructureUi::getText($eStructure, 'section', 2))]),
			'hasCommunitySchools' => s("ses fermes école"),
			'hasCommunityLearning' => s("ses formations")
		};

	}

	/**
	 * Les étapes et leur état, dans l'ordre de la planche.
	 *
	 * Chacune se déduit de ce que le groupe porte, jamais d'une colonne qui dirait qu'on y est
	 * passé : une présentation effacée depuis le paramétrage rouvre l'étape, ce qui est exact.
	 */
	protected static function getDone(Structure $eStructure, \Collection $cSection, array $counts): array {

		$done = [

			'description' => ($eStructure['description'] !== NULL),

			// ⚠️ Zéro fonctionnalité allumée et l'étape passée sont la même chose pour le produit :
			// le parcours avance, et le groupe marche avec ses fermes et ses membres. C'est
			// pourquoi l'étape n'est « faite » qu'à partir d'une, et non dès qu'on l'a vue.
			'feature' => $eStructure->isEducation() ? $cSection->notEmpty() : (self::getFeaturesOn($eStructure) !== []),

			// Même règle pour le partage : un groupe peut exister pour rassembler sans rien lire,
			// et ne rien demander est une réponse. L'étape est faite dès qu'un droit est demandé,
			// à n'importe quel public. Un droit imposé compte comme demandé : elle est donc faite
			// d'office pour un groupe qui en porte un, et reste ouverte pour régler les autres.
			'share' => (self::getSharesAsked($eStructure) !== []),

			'invite' => ($counts['invites'] > 0 or $counts['members'] > 1)

		];

		return array_map(fn(string $slug) => $done[$slug], self::getStepSlugs($eStructure));

	}

	/** Les droits de la mise en route que ce groupe demande aujourd'hui, à au moins un public. */
	protected static function getSharesAsked(Structure $eStructure): array {

		return array_values(array_filter(
			self::getStartRights($eStructure),
			fn(string $right) => $eStructure->askedCommunityAudiences($right) !== []
		));

	}

	/**
	 * Les droits à public que la mise en route de ce groupe pose, dans l'ordre de la déclaration.
	 *
	 * ⚠️ **Une seule liste pour l'étape.** Elle décide si l'étape existe, si elle est faite, et ce
	 * que son tableau montre : un réseau qui ne voit l'étape que pour un droit ne doit pas s'y voir
	 * demander les autres.
	 */
	protected static function getStartRights(Structure $eStructure): array {

		return array_values(array_filter(
			$eStructure->getCommunityAudienceRights(),
			fn(string $right) => $eStructure->acceptCommunityRightStart($right)
		));

	}

	/**
	 * La première étape qui manque, ou la dernière quand tout est fait.
	 *
	 * Publique parce que la **page** s'en sert : c'est elle qui décide de l'étape ouverte, et elle
	 * la passe à `get()`. Deux calculs du même nombre auraient divergé.
	 */
	public static function getFirstMissing(Structure $eStructure, \Collection $cSection, array $counts): int {

		foreach(self::getDone($eStructure, $cSection, $counts) as $step => $done) {
			if($done === FALSE) {
				return $step;
			}
		}

		// Tout est fait : la dernière, dont le numéro dépend du groupe.
		return count(self::getStepSlugs($eStructure));

	}

	/**
	 * L'introduction du parcours, au composant des fonctionnalités qui démarrent (`getStart()`).
	 *
	 * La coche et non l'icône de l'accueil, que la vignette de la bande porte juste au-dessus.
	 *
	 * ⚠️ La poule et non l'abeille : celle-ci accueille déjà l'entrée d'une ferme, l'oiseau les
	 * fonctionnalités du groupe, et deux accueils du même produit ne partagent pas leur image.
	 */
	protected static function getIntroduction(Structure $eStructure): string {

		// ⚠️ « étudiants » ou « membres » selon le type, et pas un mot passe-partout pour les
		// trois : un organisme de formation accueille des étudiants, et le dire vaut mieux
		// qu'un terme juste partout et exact nulle part.
		//
		// ⚠️ **Le nombre d'étapes se compte, il ne s'écrit pas.** Le parcours en a trois
		// ou quatre selon ce que le groupe a à décider, et « Trois étapes » est resté
		// faux le temps d'une livraison sous une barre qui en montrait quatre. Une phrase
		// qui énonce un nombre que le code calcule ailleurs se périme au premier ajout.
		$steps = count(self::getStepSlugs($eStructure));

		$text = $eStructure->isEducation()
			? p("{value} étape pour qu'il soit prêt à accueillir vos étudiants, et tout se retouche ensuite depuis le paramétrage.", "{value} étapes pour qu'il soit prêt à accueillir vos étudiants, et tout se retouche ensuite depuis le paramétrage.", $steps)
			: p("{value} étape pour qu'il soit prêt à accueillir vos membres, et tout se retouche ensuite depuis le paramétrage.", "{value} étapes pour qu'il soit prêt à accueillir vos membres, et tout se retouche ensuite depuis le paramétrage.", $steps);

		return new \main\DesignUi()->getStart(
			\Asset::icon('check-lg'),
			StructureUi::getText($eStructure, 'created'),
			'<p>'.$text.'</p>',
			image: \Asset::image('main', 'friends/poule.svg')
		);

	}

	/**
	 * La barre, et ce qu'elle dit.
	 *
	 * ⚠️ **Ses segments ne portent pas `data-step`**, et c'est le sujet : `onboarding.js` ne
	 * recalcule la barre que pour les segments qui en portent un. Sans lui, elle garde ce que le
	 * serveur déclare fait — une étape passée reste vide alors qu'on l'a dépassée. La barre dit
	 * ce qui a été fait, pas où l'on est.
	 */
	protected static function getProgress(Structure $eStructure, array $done): string {

		$names = self::getNames($eStructure);

		$h = '<div class="onboarding-progress">';

			$h .= '<div class="onboarding-stepper">';
				foreach($done as $step => $isDone) {
					$h .= '<span'.($isDone ? ' class="is-done"' : '').' data-onboarding-name="'.$step.'"></span>';
				}
			$h .= '</div>';

			// ⚠️ `data-onboarding-name` est ce qui fait suivre l'étape à ces noms :
			// `MainOnboarding.goTo()` y bascule `is-current` et `is-done`, qui donnent le gras et la
			// couleur, et sous 575 px ne restent affichés que le nom qui porte `is-current` et celui
			// qui le précède. Sans l'attribut, un téléphone ne montre plus aucun nom, sans erreur
			// nulle part.
			$h .= '<div class="onboarding-progress-names">';
				foreach($names as $step => $name) {
					$h .= '<span data-onboarding-name="'.$step.'">'.$name.'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La suite des étapes de ce groupe, dans l'ordre, numérotées à partir de 1.
	 *
	 * ⚠️ **Une seule source pour les numéros.** Les noms, l'état de chacune, les conseils et les
	 * redirections des actions se dérivent tous d'ici : le parcours n'a pas le même nombre
	 * d'étapes selon les groupes, et trois listes indexées à la main auraient divergé à la
	 * première qui s'ajoute.
	 */
	protected static function getStepSlugs(Structure $eStructure): array {

		$slugs = $eStructure->acceptPresentation() ? ['description', 'feature'] : ['feature'];

		if(self::acceptShares($eStructure)) {
			$slugs[] = 'share';
		}

		$slugs[] = 'invite';

		return array_combine(range(1, count($slugs)), $slugs);

	}

	/**
	 * Ce groupe a-t-il quelque chose à décider sur ce qu'il demande aux fermes ?
	 *
	 * ⚠️ **La question porte sur les droits, pas sur le type.** Un droit imposé pose une question
	 * dont la réponse est écrite : l'étape n'existe que pour un groupe qui porte au moins un droit
	 * au choix. C'est le droit qui le dit et non le type, si bien qu'un type entièrement imposé
	 * s'exclut seul, et qu'un type qui gagne un droit au choix voit l'étape arriver sans qu'on y
	 * pense.
	 *
	 * Un réseau ne la voit que si une fonctionnalité que `startWith` nomme est allumée : c'est la
	 * déclaration des droits qui le dit.
	 */
	protected static function acceptShares(Structure $eStructure): bool {

		foreach(self::getStartRights($eStructure) as $right) {
			if($eStructure->imposesCommunityRight($right) === FALSE) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Le numéro d'une étape pour ce groupe, ou `0` si elle ne lui est pas proposée.
	 *
	 * Publique parce que les **actions** s'en servent : chacune redirige vers l'étape suivante, et
	 * ce numéro dépend du groupe. Une adresse `?demarrage=3` écrite en dur mènerait deux groupes
	 * qui n'ont pas les mêmes étapes à deux endroits différents.
	 */
	public static function getStepNumber(Structure $eStructure, string $slug): int {

		return (int)array_search($slug, self::getStepSlugs($eStructure), TRUE);

	}

	/**
	 * L'étape qui suit celle-ci, ou elle-même quand c'est la dernière.
	 *
	 * ⚠️ « Celle d'après » et non un nom écrit en dur : une action qui enregistre l'étape des
	 * fonctionnalités mène au partage pour un groupe qui a un droit au choix, et aux invitations
	 * pour un groupe qui n'en a pas. Nommer la destination obligerait à rouvrir ces actions
	 * le jour où la suite change.
	 */
	public static function getNextStepNumber(Structure $eStructure, string $slug): int {

		$slugs = self::getStepSlugs($eStructure);

		return min(self::getStepNumber($eStructure, $slug) + 1, count($slugs));

	}

	/**
	 * L'étape à ouvrir : celle que l'adresse demande si le parcours la porte, sinon la première
	 * qui manque.
	 *
	 * ⚠️ **Le numéro de l'adresse se relit contre la suite des étapes**, qui change avec ce que le
	 * groupe a allumé. Un favori `?demarrage=4` sur un parcours de trois étapes, ou le `0` que
	 * rend `getStepNumber()` pour une étape disparue entre l'affichage et l'envoi, ouvraient un
	 * numéro qu'aucune étape ne porte, donc un parcours sans question.
	 */
	public static function getOpenStep(Structure $eStructure, ?int $step, \Collection $cSection, array $counts): int {

		if(
			$step !== NULL and
			array_key_exists($step, self::getStepSlugs($eStructure))
		) {
			return $step;
		}

		return self::getFirstMissing($eStructure, $cSection, $counts);

	}

	/**
	 * L'adresse d'une étape, avec l'écran où la fin ramène.
	 *
	 * Publique parce que les **actions** s'en servent : l'origine se perdrait à la première étape
	 * enregistrée si chaque action recomposait l'adresse.
	 */
	public static function urlStep(Structure $eStructure, int $step, string $origin): string {

		$url = StructureUi::urlCommunity($eStructure).'?demarrage='.$step;

		if($origin !== self::ORIGIN_HOME) {
			$url .= '&origin='.$origin;
		}

		return $url;

	}

	/**
	 * L'écran où la fin du parcours ramène, avec son bandeau d'arrivée.
	 */
	public static function urlFinish(Structure $eStructure, string $origin): string {
		return \util\HttpUi::setArgument(self::urlOrigin($eStructure, $origin), 'demarrage', 'fini');
	}

	/**
	 * L'écran d'où l'on est parti vers le parcours, à la même adresse que son onglet.
	 */
	public static function urlOrigin(Structure $eStructure, string $origin): string {

		return match($origin) {
			self::ORIGIN_HOME => StructureUi::urlCommunity($eStructure),
			'members' => StructureUi::urlCommunityMembers($eStructure),
			'structures' => StructureUi::urlCommunityFarms($eStructure),
			'schools' => StructureUi::urlCommunitySchools($eStructure),
			// L'onglet mène l'équipe aux statistiques et les autres membres au comparatif : le
			// parcours ne s'ouvre qu'à qui gère le groupe, donc il ramène aux statistiques.
			'production' => StructureUi::urlCommunityVegetable($eStructure),
			'learning' => StructureUi::urlCommunityLearning($eStructure),
			'quotation' => StructureUi::getObserverEntries('getCategoryUrls', $eStructure)['quotation']['community'],
			'settings' => StructureUi::urlStructureGeneral($eStructure)
		};

	}

	/**
	 * ⚠️ L'onglet des fermes se nomme selon le type et le lecteur : son libellé se lit à sa source,
	 * sans quoi le lien de retour nommerait un écran que la rangée appelle autrement.
	 */
	protected static function getOriginLabel(Structure $eStructure, string $origin): string {

		return match($origin) {
			'members' => s("Revenir aux membres"),
			'structures' => s("Revenir aux {farms}", ['farms' => lcfirst(StructureCommunityUi::getFarmsLabel($eStructure))]),
			'schools' => s("Revenir aux fermes école"),
			'production' => s("Revenir au maraîchage"),
			'learning' => s("Revenir aux formations"),
			'quotation' => s("Revenir aux relevés de prix"),
			'settings' => s("Revenir au paramétrage")
		};

	}

	protected static function getNames(Structure $eStructure): array {

		$names = [
			'description' => s("Description"),
			'feature' => $eStructure->isEducation() ? s("Promotion") : s("Fonctionnalités"),
			'share' => s("Partage"),
			'invite' => s("Invitations")
		];

		return array_map(fn(string $slug) => $names[$slug], self::getStepSlugs($eStructure));

	}

	/**
	 * Les fonctionnalités que ce groupe a allumées, parmi celles que son type permet.
	 *
	 * ⚠️ Dérivée de la déclaration et jamais recopiée : `getCommunityFeatures()` croise
	 * `CommunityFeatureAiLib::FEATURES` avec le type, donc une quatrième fonctionnalité arrive
	 * dans cette étape sans qu'on y pense — et disparaît des types qui ne l'ont pas.
	 */
	protected static function getFeaturesOn(Structure $eStructure): array {

		return array_values(array_filter(
			$eStructure->getCommunityFeatures(),
			fn(string $feature) => $eStructure->conf(Feature::class)[$feature]
		));

	}

	/**
	 * Étape 1 : ce que les étudiants lisent avant d'entrer.
	 *
	 * ⚠️ L'image ne passe pas par ce formulaire : `StructureLogoUi::getCamera()` poste pour son
	 * compte, comme sur l'écran de paramétrage. « Continuer » n'enregistre donc que la
	 * présentation, et une image déposée est déjà enregistrée quand on y arrive.
	 */
	protected static function getDescription(Structure $eStructure, int $step, string $origin): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = '<section class="onboarding-step" data-step="'.$step.'">';

			$h .= '<h2>'.s("Présentez {your}", ['your' => StructureUi::getText($eStructure, 'your')]).'</h2>';
			$h .= '<p class="onboarding-help">'.($eStructure->isEducation()
				? s("C'est ce que vos étudiants voient en recevant l'invitation à le rejoindre.")
				: s("C'est ce que vos membres voient en recevant l'invitation à le rejoindre.")).'</p>';

			$h .= $form->openAjax('/structure/community:doStartDescription', ['id' => 'community-start-description']);

				$h .= $form->hidden('id', $eStructure);
				$h .= $form->hidden('origin', $origin);

				$h .= $form->group(
					(StructureUi::p('logo')->label)($eStructure),
					new \media\StructureLogoUi()->getCamera($eStructure, size: '7rem')
				);

				$h .= $form->dynamicGroup($eStructure, 'description');

				$h .= self::getFooter($form, s("Continuer"));

			$h .= $form->close();

		$h .= '</section>';

		return $h;

	}

	/**
	 * Étape 2 : une ligne par promotion, plusieurs d'un coup.
	 *
	 * ⚠️ Les promotions déjà créées ne se réécrivent pas ici : l'étape ajoute, elle ne modifie pas.
	 * Une promotion se renomme dans son écran, qui sait aussi la clore — deux gestes que ce
	 * parcours n'a pas à reproduire pour les avoir sous la main une fois.
	 *
	 * ⚠️ **La première promotion ne se passe pas** (planche validée le 26/09/2026) :
	 * sans elle, l'étape des invitations n'a nulle part où faire entrer un étudiant. Son champ est
	 * donc un groupe libellé et marqué obligatoire, sans croix, et le pied n'offre pas de passer.
	 * `doStartSections` tient la même règle côté serveur.
	 */
	protected static function getSections(Structure $eStructure, \Collection $cSection, int $step, string $origin): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = '<section class="onboarding-step" data-step="'.$step.'">';

			// Un groupe qui a déjà sa promotion revient ici pour en ajouter : rien ne l'y oblige
			// plus, et ce qui s'écrit n'est plus la première.
			$first = $cSection->empty();

			if($first) {
				$h .= '<h2>'.s("Créez votre première promotion").'</h2>';
				$h .= '<p class="onboarding-help">'.s("Il en faut une pour inviter vos étudiants.").'</p>';
			} else {
				$h .= '<h2>'.s("Ajoutez une promotion").'</h2>';
			}

			$h .= $form->openAjax('/structure/community:doStartSections', ['id' => 'community-start-sections']);

				$h .= $form->hidden('id', $eStructure);
				$h .= $form->hidden('origin', $origin);

				$h .= '<div data-onboarding-rows>';

					$h .= $form->group(
						$first ? s("Nom de la première promotion").\util\FormUi::asterisk() : s("Nom de la promotion"),
						$form->text('name[1]', NULL, ['placeholder' => s("Ex. : BPREA 2026")] + ($first ? ['required' => TRUE] : [])),
						['data-wrapper' => 'name[1]']
					);

				$h .= '</div>';

				// Le gabarit d'une ligne neuve, cloné par le JS : `__i__` y tient la place de
				// l'index, comme le fait déjà `MainOnboarding.addRow()`.
				$h .= '<template data-onboarding-template>'.self::getSectionRow($form, '__i__').'</template>';

				// `btn btn-outline-primary` et non le plein : le geste principal de l'étape est
				// « Continuer », en pied. La classe `btn-add` écrite ici n'existait dans aucune
				// feuille — le lien se rendait donc sans bouton, en texte souligné au survol.
				$h .= '<div class="form-group"><a data-action="onboarding-add-row" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une promotion").'</a></div>';

				$h .= self::getFooter($form, s("Continuer"), skip: $first === FALSE);

			$h .= $form->close();

		$h .= '</section>';

		return $h;

	}

	/**
	 * L'étape des fonctionnalités : les cartes de l'onglet, dans le parcours.
	 *
	 * ⚠️ **Les mêmes cartes et les mêmes libellés que l'onglet Fonctionnalités**, pris chez lui :
	 * deux tables divergeraient au premier mot changé, et un administrateur qui retrouve l'écran
	 * plus tard doit y reconnaître ce qu'il a vu ici.
	 *
	 * ⚠️ Pas de formulaire ni de bouton d'enregistrement : l'interrupteur s'applique aussitôt, et
	 * « Continuer » ne fait qu'avancer. C'est pourquoi cette étape seule n'a pas de `submit` — ce
	 * qu'elle règle est déjà enregistré quand on la quitte.
	 */
	protected static function getFeatures(Structure $eStructure, int $step, string $origin): string {

		$labels = StructureCommunityUi::getFeatureLabels($eStructure);

		$h = '<section class="onboarding-step" data-step="'.$step.'">';

			$h .= '<h2>'.s("Que fera {your} ?", ['your' => StructureUi::getText($eStructure, 'your')]).'</h2>';
			$h .= '<p class="onboarding-help">'.s("Allumez ce dont vous avez besoin. Tout se rallume et s'éteint ensuite sans rien perdre.").'</p>';

			foreach($eStructure->getCommunityFeatures() as $feature) {

				[$icon, $title, $text] = $labels[$feature];

				$active = $eStructure->conf(Feature::class)[$feature];

				$h .= '<div class="util-deck">';
					$h .= '<div class="util-deck-head">';

						$h .= '<div class="util-icon util-icon-round '.($active ? 'util-icon-active' : 'util-icon-inactive').'">'.\Asset::icon($icon).'</div>';

						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$title.'</div>';
							$h .= '<p>'.$text.'</p>';
						$h .= '</div>';

						// ⚠️ `origin` voyage jusqu'à la redirection : sans elle, l'écran rechargé
						// rouvrirait le parcours sur la première étape qui manque — c'est-à-dire la
						// troisième dès la première fonctionnalité allumée, alors qu'on en allume
						// souvent deux.
						$h .= '<div class="util-deck-aside">'.\util\TextUi::switch([
							'data-ajax' => '/structure/community:doUpdateFeature',
							'post-id' => $eStructure['id'],
							'post-feature' => $feature,
							'post-value' => $active ? 0 : 1,
							'post-origin' => $origin
						], $active, textOn: s("Oui"), textOff: s("Non")).'</div>';

					$h .= '</div>';
				$h .= '</div>';

			}

			$h .= self::getFooterLinks();

		$h .= '</section>';

		return $h;

	}

	/**
	 * Les deux publics d'une invitation, dans les mots du type.
	 *
	 * ⚠️ `member` et `staff` et non `student` et `teacher` : le choix porte sur **qui** l'on invite
	 * et le rôle s'en déduit côté serveur, où la liste des rôles du groupe fait foi. Un organisme
	 * de formation appelle ses membres des étudiants, un réseau des producteurs — c'est le même
	 * geste.
	 *
	 * Les publics et leurs rôles viennent de `Structure::getAudienceRoles()`, que l'écran et
	 * l'action lisent tous les deux : l'écran ne propose que ces publics-là, et le serveur n'accepte
	 * que ceux-là. Deux tables auraient laissé un POST forgé demander un rôle que l'écran ne montre pas.
	 *
	 * ⚠️ **`admin` seulement en formation.** Une communauté de producteurs n'a pas d'autre
	 * encadrement que ses administrateurs, et un administrateur donne un accès intégral au groupe :
	 * ce n'est pas ce qu'on distribue par un lien d'invitation en trois clics. Elle n'offre donc
	 * qu'un public, et l'écran cesse alors de poser une question à une seule réponse.
	 *
	 * Un groupe de formation n'a plus que deux statuts, administrateur et étudiant (décision de
	 * du 26/09/2026) : ses formateurs, ce sont ses administrateurs. Le parcours n'invite
	 * qu'eux (décision du même jour) : ses étudiants entrent depuis la page des membres, dans leur
	 * promotion. Le public `member` est donc absent en formation, et le lien avec lui, qui fait
	 * toujours entrer un `member`. Le conseiller est propre au réseau, seul type qui porte ce métier.
	 */
	protected static function getAudienceLabels(Structure $eStructure): array {

		$labels = $eStructure->isEducation()
			? ['staff' => s("Des formateurs")]
			: ['member' => s("Des producteurs"), 'staff' => s("L'équipe {of}", ['of' => StructureUi::getText($eStructure, 'of')])];

		return array_intersect_key($labels, $eStructure->getAudienceRoles());

	}

	/**
	 * Une promotion de plus, après la première.
	 *
	 * ⚠️ Un `form-group` et non `onboarding-row`, qui est la carte du composant : son retrait
	 * décalerait le champ sous celui de la première promotion, et le groupe garde le rythme du
	 * formulaire.
	 */
	protected static function getSectionRow(\util\FormUi $form, string $index): string {

		$h = '<div class="form-group" data-index="'.$index.'">';
			$h .= '<div class="onboarding-row-main">';
				$h .= '<div class="onboarding-field onboarding-field-name" data-wrapper="name['.$index.']">';
					$h .= $form->text('name['.$index.']', NULL, ['placeholder' => s("Ex. : BPREA 2027")]);
				$h .= '</div>';
				$h .= '<a data-action="onboarding-remove-row" class="onboarding-row-remove" title="'.s("Retirer cette promotion").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Étape du partage : ce que le groupe demande aux fermes qui le rejoindront.
	 *
	 * ⚠️ **Le tableau se réemploie, il ne se réécrit pas.** `getShareForm()` le rend déjà pour
	 * l'onglet « Partage des données », avec ses régimes, ses publics et son action. Une
	 * troisième écriture du même tableau — la carte d'une ferme en porte une lecture — aurait
	 * divergé au premier changement, et ce chantier en a déjà vu deux ce mois.
	 *
	 * ⚠️ **Le pied est celui des étapes qui n'enregistrent pas.** Les interrupteurs du tableau
	 * écrivent à l'instant où on les bascule : il n'y a rien à enregistrer en partant, et
	 * « Continuer » ne fait qu'avancer, comme à l'étape des fonctionnalités. « Passer cette
	 * étape » reste, et c'est lui qui dit que ne rien demander est une réponse.
	 *
	 * ⚠️ **Un seul titre.** La question de l'étape et son aide disent ce que le titre du tableau
	 * dit au paramétrage : il ne se rend donc pas ici (`title: FALSE`).
	 */
	protected static function getShares(Structure $eStructure, int $step, string $origin): string {

		$h = '<section class="onboarding-step" data-step="'.$step.'">';

			$h .= '<h2>'.s("Que demandez-vous aux fermes ?").'</h2>';
			$h .= '<p class="onboarding-help">'.s("Chaque ferme verra cette demande en rejoignant {the}, et restera libre de refuser.", ['the' => StructureUi::getText($eStructure, 'the')]).'</p>';

			// Le pied entre **dans** le formulaire : « Passer cette étape » et « Enregistrer » sont
			// les deux sorties de la même étape, elles tiennent la même ligne que sur les autres,
			// et le bouton doit rester dans le `<form>` qu'il soumet.
			$h .= new StructureCommunityUi()->getShareForm(
				$eStructure,
				$eStructure->community(),
				$eStructure['shareCounts'],
				columns: 1,
				skip: self::getFooterLinks(),
				origin: $origin,
				rights: self::getStartRights($eStructure),
				title: FALSE
			);

		$h .= '</section>';

		return $h;

	}

	/**
	 * L'étape des invitations : qui, et par quel moyen.
	 *
	 * ⚠️ **Un groupe de formation n'y invite que ses formateurs** (décidé le 26/09,
	 * « on invite uniquement des formateurs sur l'ob »). Ses étudiants entrent promotion par
	 * promotion, depuis la page des membres, une fois l'encadrement en place : le parcours ne
	 * pose donc ni la question du public, ni celle de la promotion, et ne montre aucun lien, qui
	 * fait toujours entrer un étudiant.
	 */
	protected static function getInvites(Structure $eStructure, Invite $eInviteLink, int $step, string $origin): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = '<section class="onboarding-step" data-step="'.$step.'">';

			if($eStructure->isEducation()) {
				$h .= '<h2>'.s("Invitez vos formateurs").'</h2>';
				$h .= '<p class="onboarding-help">'.s("Ils administrent le groupe avec vous. Vous inviterez ensuite vos étudiants, promotion par promotion, depuis la page des membres.").'</p>';
			} else {
				$h .= '<h2>'.s("Invitez vos premiers membres").'</h2>';
				$h .= '<p class="onboarding-help">'.s("Chacun rejoint {the} et y rattache la ferme qu'il veut partager.", ['the' => StructureUi::getText($eStructure, 'the')]).'</p>';
			}

			$h .= $form->openAjax('/structure/community:doStartInvites', ['id' => 'community-start-invites']);

				$h .= $form->hidden('id', $eStructure);
				$h .= $form->hidden('origin', $origin);

				$audiences = self::getAudienceLabels($eStructure);

				// ⚠️ Pas de question à une seule réponse : un groupe de producteurs n'a que ses
				// membres à inviter, un groupe de formation que ses formateurs, et des radios à une
				// option sont un contrôle qui ne contrôle rien. Le public voyage alors en champ
				// caché, que l'action relit comme le reste.
				$h .= (count($audiences) > 1)
					? $form->group(s("Qui invitez-vous ?"), $form->radios('audience', $audiences, 'member', ['columns' => 2, 'required' => TRUE]))
					: $form->hidden('audience', array_key_first($audiences));

				$h .= $form->group(
					s("Leurs adresses e-mail"),
					$form->textarea('emails', NULL, ['placeholder' => $eStructure->isEducation() ? s("claire.durand@exemple.fr") : s("lea.martin@exemple.fr, tom.bernard@exemple.fr"), 'rows' => 2])
				);

				if(array_key_exists('member', $eStructure->getAudienceRoles())) {
					$h .= self::getLink($eStructure, $eInviteLink, $origin);
				}

				// La dernière : passer ici ne mène à aucune étape suivante, le lien ferme donc
				// le parcours au lieu d'avancer.
				$h .= self::getFooter($form, s("Envoyer les invitations"), $eStructure, origin: $origin);

			$h .= $form->close();

		$h .= '</section>';

		return $h;

	}

	/**
	 * Un lien d'invitation, montré en entier et copiable d'un bouton.
	 *
	 * ⚠️ `input-group` + `form-control` + bouton est l'écriture du dépôt pour exactement ce cas
	 * (`selling/ui/Customer.u.php:543`, le lien qu'on donne à un client). Le `<pre>` cliquable
	 * qu'il y avait ici débordait de sa boîte — une adresse d'invitation fait une centaine de
	 * caractères sans espace — et surtout rien ne disait qu'il était cliquable : le geste se
	 * devinait, ce qui est le défaut que la carte d'un lien existe pour éviter.
	 *
	 * ⚠️ `doCopy()` ne lit pas l'élément cliqué mais celui que `data-selector` désigne
	 * (`lime.js:165`) : sans lui, la copie part sur `undefined`.
	 */
	protected static function getLinkBox(string $id, string $link): string {

		$h = '<div class="input-group onboarding-link">';

			$h .= '<div class="form-control" id="'.$id.'">'.encode($link).'</div>';

			$h .= '<a onclick="doCopy(this)" data-selector="#'.$id.'" data-message="'.s("Lien copié !").'" class="btn btn-primary">'.\Asset::icon('clipboard').' '.s("Copier").'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien d'invitation du groupe : celui qui existe, ou le geste qui le crée. Un seul, valable
	 * pour le groupe entier, et `memberSection` reste nul.
	 *
	 * ⚠️ **Rien ne se crée à l'affichage.** Une invitation posée au rendu serait un effet de bord
	 * à la lecture : chaque passage sur l'étape en fabriquerait une, et un groupe visité dix fois
	 * porterait dix liens. Le lien naît d'un clic, et il est réutilisé ensuite — c'est `$eInviteLink`,
	 * que la page a préparé.
	 */
	protected static function getLink(Structure $eStructure, Invite $eInviteLink, string $origin): string {

		$h = '<div class="form-group" data-onboarding-links>';

			$h .= '<p class="util-info">'.s("Ou partagez ce lien, valable pour {the} :", ['the' => StructureUi::getText($eStructure, 'this')]).'</p>';

			if($eInviteLink->notEmpty()) {

				$h .= self::getLinkBox('community-start-link', $eInviteLink->getLink());

			} else {

				$h .= '<a data-ajax="/structure/community:doStartLink" post-id="'.$eStructure['id'].'" post-origin="'.$origin.'" class="btn btn-outline-primary">'.s("Créer un lien").'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le pied d'une étape qui n'enregistre rien : ses trois gestes sont des liens.
	 *
	 * ⚠️ « Continuer » n'est pas un `submit` ici, et ce n'est pas une économie : l'étape des
	 * fonctionnalités s'enregistre à chaque interrupteur, il n'y a donc rien à envoyer en la
	 * quittant. Un bouton d'enregistrement y promettrait une écriture qui a déjà eu lieu.
	 */
	protected static function getFooterLinks(): string {

		// Pas de bouton « Retour » : on recule en cliquant le nom d'une étape dans la barre, qui
		// donne les trois destinations au lieu d'un seul cran sans nom.
		$h = '<div class="onboarding-footer">';

			$h .= '<div class="onboarding-footer-side">';
				$h .= '<a data-action="onboarding-skip" class="btn btn-outline-primary">'.s("Passer cette étape").'</a>';
			$h .= '</div>';

			$h .= '<div class="onboarding-footer-main">';
				$h .= '<a data-action="onboarding-next" class="btn btn-primary">'.s("Continuer").'</a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le pied d'une étape : passer, ou continuer.
	 *
	 * ⚠️ « Passer cette étape » n'enregistre rien et ne poste rien — c'est un geste du navigateur,
	 * et le serveur n'a pas à savoir qu'on l'a fait. **Sauf à la dernière**, où passer ne laisse
	 * plus rien à atteindre : le lien y ferme le parcours pour de bon, et il le fait lui-même au
	 * lieu de déclencher un bouton d'en-tête qui n'existe plus.
	 *
	 * ⚠️ L'origine voyage jusqu'à la redirection : la même action sert la page de démarrage d'une
	 * AMAP, qui revient sur son tableau de bord. Une adresse déduite du type de la structure
	 * enverrait un groupe sur `/ferme/{id}/tableau-de-bord`, qui n'existe pas.
	 *
	 * Il n'y a plus de « Retour » : on recule en cliquant le nom d'une étape, ce qui donne les
	 * trois destinations au lieu d'un cran sans nom. Le pied ne connaît donc plus son numéro, mais
	 * il sait s'il est le dernier.
	 */
	protected static function getFooter(\util\FormUi $form, string $submit, Structure $eStructure = new Structure(), bool $skip = TRUE, string $origin = self::ORIGIN_HOME): string {

		$h = '<div class="onboarding-footer mt-1">';

			if($skip) {

				$h .= '<div class="onboarding-footer-side">';
					$h .= $eStructure->empty()
						? '<a data-action="onboarding-skip" class="btn btn-outline-primary">'.s("Passer cette étape").'</a>'
						: '<a data-ajax="/structure/feature:doUpdateStart" post-id="'.$eStructure->conf(Feature::class)['id'].'" post-has-start="0" post-origin="'.$origin.'" class="btn btn-outline-primary">'.s("Passer cette étape").'</a>';
				$h .= '</div>';

			}

			$h .= '<div class="onboarding-footer-main">';
				$h .= $form->submit($submit, ['class' => 'btn btn-primary']);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un conseil par étape, et les deux premiers dépendent du type.
	 *
	 * ⚠️ Une bulle qui parle d'étudiants et de promotions au-dessus d'une liste de fonctionnalités
	 * décrit une étape qui n'existe pas sur cet écran. Le troisième conseil ne suppose aucun type
	 * et reste commun aux trois.
	 *
	 * ⚠️ Le second conseil d'un réseau ne dit **ni** le geste, que l'écran montre, **ni** la
	 * réversibilité, que l'aide de l'étape dit trois centimètres plus haut : il dit qu'on peut
	 * n'allumer aucune fonctionnalité, ce que rien d'autre n'énonce et que le contrat tranche —
	 * zéro allumée et l'étape passée sont la même chose. Deux textes pour une même règle, c'est
	 * un de trop, et c'est celui du flux qui reste.
	 */
	protected static function getTips(Structure $eStructure): array {

		$tips = $eStructure->isEducation()
			? [
				'feature' => '<b>'.s("La promotion range vos étudiants par année.").'</b> '.s("Les formateurs les retrouvent promotion par promotion, et un étudiant ne voit jamais la ferme d'un autre étudiant."),
			]
			: [
				'description' => '<b>'.s("Une image et deux phrases suffisent.").'</b> '.s("Celui qui reçoit l'invitation doit reconnaître {the} avant de le rejoindre.", ['the' => StructureUi::getText($eStructure, 'the')]),
				'feature' => '<b>'.s("Vous pouvez n'en allumer aucune.").'</b> '.s("{the} rassemble déjà ses membres, le reste s'ajoute quand le besoin vient.", ['the' => mb_ucfirst(StructureUi::getText($eStructure, 'the'))]),
			];

		// Le conseil de l'étape du partage dit la seule chose que le tableau ne montre pas : que
		// demander n'est pas obtenir. Chaque ferme reste libre de refuser, et l'écran des fermes
		// dit ensuite qui a accordé quoi.
		$tips['share'] = '<b>'.s("Demander n'est pas obtenir.").'</b> '.s("Chaque ferme choisit ce qu'elle vous ouvre en rejoignant {the}, et vous voyez sa réponse sur la page des fermes.", ['the' => StructureUi::getText($eStructure, 'the')]);

		$tips['invite'] = self::getInviteTip();

		return array_map(fn(string $slug) => $tips[$slug], self::getStepSlugs($eStructure));

	}

	protected static function getInviteTip(): string {

		return '<b>'.s("Vous pourrez inviter d'autres personnes à tout moment.").'</b> '.s("Cela se fait depuis la page des membres. Rien ne presse d'inviter tout le monde maintenant.");

	}

}
?>
