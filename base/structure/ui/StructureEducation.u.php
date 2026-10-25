<?php
namespace structure;

/**
 * Les mots d'un organisme de formation : une promotion, des étudiants, des fermes école.
 *
 * Elle porte ses mots, et l'écran des fermes école, que lui seul a — les autres types n'en
 * hébergent aucune. Tout le reste — l'accueil, le sélecteur, les onglets, les cartes — vit dans
 * `StructureCommunityUi`, qui les rend à l'identique pour les quatre types.
 */
class StructureEducationUi extends StructureCommunityUi {

	/**
	 * Le groupe de formation est le seul à redemander la catégorie : c'est le moment où une ferme
	 * de test devient une ferme d'étudiant, et l'étudiant n'y repense plus ensuite.
	 *
	 * Rien à proposer si aucune des fermes offertes n'accepte encore de changer — une ferme
	 * installée est figée partout (arbitrage du 2026-09-16).
	 */
	public static function getProducerTypeChoice(\util\FormUi $form, \Collection $cStructureProducer): string {

		$cStructureProducerOpen = $cStructureProducer->find(fn(Structure $eStructureProducer) => $eStructureProducer->acceptUpdateProducerType());

		if($cStructureProducerOpen->empty()) {
			return '';
		}

		// À plusieurs fermes, la question part vide et le JS la remplit sur la ferme cochée.
		if($cStructureProducer->count() === 1) {
			$eStructureProducer = $cStructureProducerOpen->first();
			$label = s("Catégorie de {name}", ['name' => encode($eStructureProducer['name'])]);
		} else {
			$eStructureProducer = new Structure(['producerType' => NULL]);
			$label = StructureUi::p('producerType')->label;
		}

		return $form->dynamicGroup($eStructureProducer, 'producerType*', function(\PropertyDescriber $d) use($label) {
			$d->label = $label;
		});

	}

	protected function getOnboardingFarmText(Structure $eStructure): string {
		return s("C'est avec elle que vous travaillerez dans le cadre de votre formation, pour apprendre à utiliser {siteName} et manipuler l'outil préféré des producteurs déjà installés.");
	}

	/**
	 * Un étudiant n'a pas à choisir sa catégorie : la ferme qu'il crée depuis sa formation est
	 * une ferme d'apprenant, et le formulaire s'ouvre directement sur ses champs.
	 */
	protected function getOnboardingFarmUrl(): string {
		return '/demarrer/ferme?producerType='.Structure::LEARNER;
	}

	/**
	 * Ce que le groupe cesse de lire, nommé dans la confirmation.
	 *
	 * Retirer une ferme est subi de son côté : la confirmation la nomme et dit ce qui s'arrête,
	 * là où le retrait qu'on décide soi-même se contente d'une confirmation ordinaire.
	 */
	/**
	 * Les fermes école, sur leur propre écran.
	 *
	 * L'encart passe devant la grille : ces fermes ne sont celles de personne, et une grille de
	 * cartes sans cette phrase se lirait comme la liste des fermes de l'organisme.
	 */
	public function getSchools(Structure $eStructure, \Collection $cStructureSchool): string {

		\Asset::css('structure', 'structure.css');

		if($cStructureSchool->empty()) {
			return self::getEmpty(
				s("Aucune ferme école"),
				s("Les fermes école sont des copies anonymisées de fermes réelles. Il n'y en a aucune pour le moment.")
			);
		}

		// ⚠️ `util-cards-1` : le deck prend toute la largeur, il ne se compare pas à son voisin.
		return self::getSchoolNotice()
			.'<div class="util-cards util-cards-1">'.self::getSchoolPanels($cStructureSchool).'</div>';

	}

	/**
	 * Les fermes école, qui ne sont la ferme de personne.
	 *
	 * Le sous-titre porte la période couverte et non « copie anonymisée » : l'encart de l'onglet
	 * vient de le dire, et deux fois la même phrase dans le même regard n'informe qu'une fois. Sur
	 * les autres cartes ce sous-titre nomme l'exploitant — une ferme école n'en a pas, il faut
	 * donc autre chose de vrai plutôt que rien, un sous-titre vide passant pour un oubli.
	 */
	protected static function getSchoolPanels(\Collection $cStructureSchool): string {

		$h = '';

		foreach($cStructureSchool as $eStructureSchool) {
			$h .= self::getSchoolPanel($eStructureSchool);
		}

		return $h;

	}

	/**
	 * Le deck large d'une ferme école : identité et chiffres en tête, ateliers, puis ses portes.
	 *
	 * ⚠️ Il prend toute la largeur, et c'est le nombre qui l'autorise : il y aura peu de fermes
	 * école, souvent une seule. À une, personne ne compare et le deck peut se déployer ; c'est ce
	 * qui écarte les formes serrées, qui ne servent qu'à choisir entre plusieurs.
	 *
	 * ⚠️ Aucun jeton « copie anonymisée » : l'encart au-dessus le dit pour toutes les fermes de
	 * l'écran, et le répéter sur chacune est la redondance qu'on a retirée des cartes de groupe.
	 */
	protected static function getSchoolPanel(Structure $eStructureSchool): string {

		$h = '<div class="util-deck">';

			$h .= '<div class="util-deck-head">';

				$h .= StructureUi::getLogo($eStructureSchool, '4rem');

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.encode($eStructureSchool['name']).'</div>';

					// ⚠️ Le département seul : le métier et la certification figurent dans les
					// ateliers, avec leurs surfaces. Les garder en sous-titre les disait deux fois.
					if($eStructureSchool['place'] !== NULL) {
						$h .= '<p>'.encode($eStructureSchool['place']).'</p>';
					}

					$h .= self::getSchoolFigures($eStructureSchool);

				$h .= '</div>';

			$h .= '</div>';

			$h .= self::getSchoolProductions($eStructureSchool);
			$h .= self::getSchoolActions($eStructureSchool);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les chiffres du deck.
	 *
	 * ⚠️ Une valeur absente ne laisse pas sa case vide, elle disparaît et les suivantes se rangent
	 * à gauche — même règle que la scène de la fiche, arbitrée le 2026-09-05. Beaucoup
	 * de sources ne renseignent ni altitude ni sol, et une valeur seule flottant en troisième
	 * colonne se lit comme un défaut d'affichage.
	 */
	protected static function getSchoolFigures(Structure $eStructureSchool): string {

		$figures = array_filter($eStructureSchool['figures'] ?? []);

		if($figures === []) {
			return '';
		}

		$h = '<div class="util-deck-figures">';

			foreach($figures as [$label, $value, $detail]) {

				if($detail !== '') {
					$value .= ' <span class="color-muted font-xs">'.$detail.'</span>';
				}

				$h .= \main\DesignUi::getDeckFigure($label, $value);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les trois portes d'une ferme école : sa fiche, ses cultures, ses ventes.
	 *
	 * ⚠️ Chacune est ouverte en lecture par `canSchool()` — le lecteur n'en est pas membre et n'y
	 * écrira rien. Une porte qui rendrait 403 est le défaut qu'on corrige ailleurs depuis deux
	 * jours ; celles-ci sont mesurées ouvertes.
	 *
	 * Les couleurs de domaine sont celles des portes de l'accueil (`StructureUi::getPanel()`).
	 */
	protected static function getSchoolActions(Structure $eStructureSchool): string {

		$urls = $eStructureSchool['urls'] ?? [];

		if($urls === []) {
			return '';
		}

		$h = '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-3">';

			foreach($urls as [$color, $label, $url]) {

				[$class, $icon] = match($color) {
					'color-brand' => ['btn-outline-primary', 'person-vcard'],
					'production' => ['btn-production-light', 'leaf'],
					'commercialisation' => ['btn-commercialisation-light', 'basket3'],
				};

				$h .= '<a href="'.$url.'" class="btn btn-sm '.$class.'">'.\Asset::icon($icon).' '.$label.'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les ateliers de culture de la ferme école, rendus par le code de la fiche.
	 *
	 * ⚠️ Le même bloc que la fiche, appelé et non recopié : deux rendus d'une même chose divergent
	 * au premier changement, et c'est celui qu'on recette le moins qui devient faux.
	 *
	 * Bornés au maraîchage et au légume de plein champ : c'est ce qu'on vient étudier sur une
	 * ferme école, et un atelier de transformation n'a ni surface ni mécanisation à montrer.
	 */
	protected static function getSchoolProductions(Structure $eStructureSchool): string {

		$cProduction = $eStructureSchool['cProduction'] ?? new \Collection();

		if($cProduction->empty()) {
			return '';
		}

		$ui = new ProfileAiUi();

		$h = '<div class="util-deck-content">';
			$h .= '<div class="profile-productions">';

				foreach($cProduction as $eProduction) {
					$h .= $ui->getProduction($eProduction);
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}


	/**
	 * Deux affirmations, pas trois. « Personne ne peut les modifier » disait pourquoi il n'y a pas
	 * de bouton, donc expliquait l'écran au lieu de le montrer : une fois dit qu'il s'agit de
	 * copies remises à jour, l'absence de modification suit toute seule. Et rien n'est promis sur
	 * la fréquence, qu'aucun traitement automatique ne tient.
	 *
	 * L'ampoule et non l'icône de l'onglet, que la vignette de la bande porte juste au-dessus.
	 */
	protected static function getSchoolNotice(): string {

		return new \main\DesignUi()->getStart(
			\Asset::icon('lightbulb'),
			s("Des fermes réelles pour apprendre et comprendre"),
			'<p>'.s("Ces fermes sont des copies anonymisées de fermes réelles, qui ont accepté de partager leurs données pour les mettre à disposition des étudiants.").'</p>'
		);

	}

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {
			'of' => s("du groupe"),
			'one' => s("un groupe de formation"),
			'the' => s("le groupe"),
			'this' => s("ce groupe"),
			'your' => s("votre groupe"),
			'my' => p("Mon groupe", "Mes groupes", $value),
			'propertyLogo' => s("Image du groupe"),
			'propertyBanner' => s("Bandeau du groupe"),
			'propertyName' => s("Nom du groupe"),
			'propertyIsLegal' => throw new \UnsupportedException(),
			'propertyLegalEmail' => s("Adresse e-mail du groupe"),
			'propertyPresentation' => s("Présentation du groupe"),
			'farms' => s("Fermes des étudiants"),
			'farmsMember' => s("Fermes du groupe"),
			'farmsTitle' => s("Les fermes des étudiants"),
			'farmsTitleScoped' => s("Les fermes du groupe"),
			'section' => p("Promotion", "Promotions", $value),
			'sectionsTitle' => s("Les promotions"),
			'sectionsFeature' => s("Ranger vos étudiants par promotion. Un étudiant ne voit que la sienne."),
			'sectionClosed' => p("{value} promotion close", "{value} promotions closes", $value),
			'staffTitle' => p("Formateur", "Formateurs", $value),
			'audienceStaff' => s("les formateurs"),
			'audienceMember' => s("les autres étudiants"),
			'audienceStaffAllow' => s("Permettre l'accès aux formateurs"),
			'audienceMemberAllow' => s("Permettre l'accès aux autres étudiants"),
			'audienceStaffGrant' => s("J'autorise l'accès aux formateurs"),
			'audienceMemberGrant' => s("J'autorise l'accès aux autres étudiants"),
			'shareImposedStaff' => s("Partagé avec les formateurs, sans condition. Les autres étudiants ne le voient pas."),
			'shareImposedAll' => s("Partagé avec les formateurs et les étudiants, sans condition."),
			'shareImposedRule' => s("Sans condition. Les fermes des étudiants sont partagées avec les formateurs, celles des formateurs avec tout le groupe."),
			'shareStaffImposed' => s("Accordé aux formateurs, sans condition."),
			'shareStaffImposedRule' => s("Sans condition. Les formateurs y ont droit sur toutes les fermes du groupe, les étudiants sur aucune."),
			'dashboardStaff' => s("Tableau de bord des formateurs"),
			'dashboardMember' => s("Tableau de bord des étudiants"),
			'sectionAll' => s("Tout le groupe"),
			'sectionManage' => s("Gérer les promotions"),
			'sectionNew' => s("Nom de la promotion"),
			'sectionCreate' => s("Nouvelle promotion"),
			'sectionThe' => s("la promotion"),
			'sectionThis' => s("cette promotion"),
			'sectionInvite' => s("Promotion dans laquelle l'invitation fait entrer"),
			'sectionStaff' => s("Ce rôle encadre tout le groupe et n'entre dans aucune promotion."),
			'inviteAdminEmail' => s("Lorsque vous créez un lien pour inviter un formateur, vous devez obligatoirement indiquer une adresse e-mail pour des raisons de sécurité."),
			'inviteAdminEmailRequired' => s("Veuillez réserver l'invitation d'un nouveau formateur à une adresse e-mail"),
			'inviteAdmin' => '<p>'.s("Un formateur administre le groupe comme vous. Il invite et retire des membres, change leur rôle, gère les promotions, retire des fermes du groupe, modifie son profil et son paramétrage, et peut supprimer le groupe. Il peut aussi vous retirer votre rôle de formateur ou vous retirer du groupe, et il faudra une nouvelle invitation pour y revenir.").'</p>'.'<p>'.s("Il ne prend en revanche aucune main sur les fermes. Le groupe ne fait que lire ce que chaque ferme lui partage, et seul un administrateur d'une ferme décide de ce qu'elle partage.").'</p>'.'<p><b>'.s("N'invitez comme formateurs que les personnes qui encadrent réellement la formation.").'</b></p>',
			'sectionStartTitle' => s("Créez votre première promotion"),
			'sectionEmpty' => s("Vous n'avez pas encore créé de promotion."),
			'sectionStartText' => s("Une promotion range vos étudiants par année. C'est elle qui décide de ce que chacun voit : un étudiant ne voit que la sienne. Vous inviterez vos étudiants juste après."),
			'sectionStartButton' => s("Créer une promotion"),
			'sectionCreated' => s("{value} créée", $value),
			'sectionOther' => s("Créer une autre promotion"),
			'sectionShareText' => s("Votre promotion verra vos données de production. Vous choisissez ce que vous ouvrez, et vous pouvez le refermer à tout moment."),
			'membersInvite' => s("Inviter des étudiants"),
			'membersInviteAll' => s("Inviter"),
			'membersInviteText' => s("Invitez vos étudiants à la rejoindre en leur envoyant un lien d'invitation."),
			'sectionDeleteWarning' => p("{value} personne ne sera plus rangée dans aucune promotion.", "{value} personnes ne seront plus rangées dans aucune promotion.", $value),
			'create' => s("Créer un groupe"),
			'createButton' => s("Créer le groupe"),
			'created' => s("Votre groupe est créé !"),
			'ready' => s("Votre groupe est prêt"),
			'delete' => s("Supprimer le groupe")
		};

	}

}
?>
