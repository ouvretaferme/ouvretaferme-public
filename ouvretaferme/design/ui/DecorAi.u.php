<?php
namespace design;

/**
 * L'onglet Tests : le décor des tests, une fiche par structure.
 *
 * Deux lecteurs, deux rendus d'une même source. En lecture, un humain qui recette cherche quel compte
 * tient quel rôle : les fiches sont repliées sur ce qu'elles portent et leurs rôles. Pour les agents,
 * un test à écrire demande la constante à citer, ce que la structure ne porte pas et quand la
 * prendre : tout est déplié.
 *
 * Ce qui se déduit du décor (nom, type, comptes et rôles) arrive de `DecorAiLib` par la page. Ce qui
 * ne s'en déduit pas (à quoi sert chaque structure, quand la prendre) est écrit ici, par code, et
 * `DecorSentinelAi` tient ces clés face à `FixtureAiLib::DECOR` dans les deux sens : une structure
 * ajoutée au décor sans sa fiche, ou une fiche dont la structure a disparu, le fait échouer.
 *
 * Aucune fiche ne dit comment se connecter : les comptes du décor n'ont aucun moyen de connexion,
 * en production comme en dev (`FixtureAiLib::USERS`), et la page dit le rôle qu'un test éprouve.
 */
class DecorAiUi {

	public function all(array $structures, array $users, bool $forAgents): string {

		$texts = $this->getTexts();

		$h = '<div class="design-decor">';

			$h .= '<p class="util-annotation mb-2">'.($forAgents ? $this->getLeadAgents() : $this->getLead($structures, $users)).'</p>';

			foreach($this->getFamilies() as $family) {

				$h .= '<section>';
					$h .= '<h2>'.$family['title'].'</h2>';
					$h .= '<div class="util-cards util-cards-2 util-cards-start mb-2">';

						foreach($family['codes'] as $code) {
							$h .= $this->getDeck($structures[$code], $texts[$code], $forAgents);
						}

					$h .= '</div>';
				$h .= '</section>';

			}

			$h .= '<hr />';
			$h .= $this->getUsers($users);

		$h .= '</div>';

		return $h;

	}

	private function getLead(array $structures, array $users): string {

		return s("Le décor compte {structures} structures et {users} comptes, en dev comme en production, pour que les tests aient de quoi lire. Le produit ne les montre nulle part. Chaque fiche dit quel rôle tient chaque compte, celui qu'un test éprouve.", [
			'structures' => count($structures),
			'users' => count($users),
		]);

	}

	private function getLeadAgents(): string {

		return s("Un test cite une structure par sa constante, {constant}, jamais par son identifiant. Le décor se lit, et ne s'écrit que dans la transaction du test, où il pose ce qu'il lit. Ce qui manque se déclare dans {decor} avec son fichier de migration, selon {readme}.", [
			'constant' => '<code>FixtureAiLib::F1</code>',
			'decor' => '<code>DECOR</code>',
			'readme' => '<code>base/structure/CLAUDE.md</code>',
		]);

	}

	/**
	 * Replié en lecture, où la tête suffit à trouver une structure ; déplié pour les agents, qui lisent
	 * toutes les fiches d'un trait. La tête bascule le repli dans les deux vues.
	 */
	private function getDeck(array $structure, array $text, bool $forAgents): string {

		$rows = $forAgents
			? [
				s("Constante") => '<span class="badge badge-reference">FixtureAiLib::'.$structure['code'].'</span>',
				s("Porte") => $this->getList($text['porte']),
				s("Ne porte pas") => $this->getList($text['pas']),
				s("La prendre") => $this->getList($text['quand']),
				s("Rôles") => $this->getMembers($structure['members']),
			]
			: [
				s("Porte") => $this->getList($text['porte']),
				s("Rôles") => $this->getMembers($structure['members']),
			];

		$h = '<div class="util-deck '.($forAgents ? 'util-deck-open' : '').'">';

			$h .= '<a class="util-deck-head" '.attr('onclick', 'this.closest(".util-deck").classList.toggle("util-deck-open")').'>';
				$h .= $this->getIcon($structure);
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.$structure['code'].' · '.encode($structure['name']).'</div>';
					$h .= '<p>'.implode('<br>', $text['usage']).'</p>';
				$h .= '</div>';
				$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			// Un nom de ligne qui porte une liste est un bloc : une liste dans un `span` n'est pas du
			// HTML valide, et `util-deck-row-name` ne dépend pas de la balise.
			$h .= '<div class="util-deck-fold">';

				foreach($rows as $name => $value) {
					$h .= '<div class="util-deck-row">';
						$h .= '<div class="util-deck-row-name"><b>'.$name.'</b><br>'.$value.'</div>';
					$h .= '</div>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	private function getIcon(array $structure): string {

		[$domain, $icon] = $this->getIcons()[$structure['kind']];

		return '<div class="util-icon util-icon-round util-icon-'.$domain.'">'.\Asset::icon($icon).'</div>';

	}

	/**
	 * L'icône dit le type d'un coup d'œil, à la place d'un badge : le design system n'a de badge de
	 * domaine que pour la production. Une par type que le décor emploie (`kind` de `DecorAiLib`),
	 * domaine puis icône, et `DecorSentinelAi` tient la liste face au décor dans les deux sens.
	 */
	public function getIcons(): array {

		return [
			'producer-real' => ['production', 'house'],
			'producer-project' => ['production', 'flower1'],
			'shop' => ['commercialisation', 'shop'],
			'amap' => ['amap', 'basket'],
			'community-producer' => ['settings', 'people'],
			'community-network' => ['settings', 'diagram-3'],
			'community-education' => ['settings', 'mortarboard'],
		];

	}

	private function getList(array $items): string {

		$h = '<ul class="mb-0">';

			foreach($items as $item) {
				$h .= '<li>'.$item.'</li>';
			}

		$h .= '</ul>';

		return $h;

	}

	private function getMembers(array $members): string {

		if($members === []) {
			return '<span class="color-muted">'.s("Aucun membre").'</span>';
		}

		$roles = $this->getRoles();

		$h = '<ul class="mb-0">';

			foreach($members as $member) {
				$h .= '<li>';
					$h .= s("{firstName} {lastName}, {role}", [
						'firstName' => encode($member['firstName']),
						'lastName' => encode($member['lastName']),
						'role' => $roles[$member['role']],
					]);
					$h .= '<br><span class="color-muted">'.encode($member['email']).'</span>';
				$h .= '</li>';
			}

		$h .= '</ul>';

		return $h;

	}

	/**
	 * Les comptes, chacun avec ses rôles dans le décor. Les pistes sont celles des tableaux de l'onglet
	 * Dev (`design-dev-2`), qui se replient en une colonne sous 768 px.
	 */
	private function getUsers(array $users): string {

		$roles = $this->getRoles();

		$h = '<section>';

			$h .= '<h2>'.s("Comptes").'</h2>';

			$h .= '<div class="util-table util-table-even design-dev-2">';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Courriel").'</div>';
					$h .= '<div>'.s("Qui").'</div>';
				$h .= '</div>';

				foreach($users as $user) {

					$memberships = [];

					foreach($user['roles'] as $code => $role) {
						$memberships[] = $code.' · '.$roles[$role];
					}

					$h .= '<div class="util-table-row">';
						$h .= '<div><b>'.encode($user['email']).'</b></div>';
						$h .= '<div>';
							$h .= encode($user['firstName'].' '.$user['lastName']).'<br>';
							$h .= '<span class="color-muted">'.($memberships === [] ? s("Aucune structure") : implode(', ', $memberships)).'</span>';
						$h .= '</div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</section>';

		return $h;

	}

	/**
	 * Un libellé par clé de rôle que `DECOR` emploie, fantômes compris. `RoleUi::getFqn()` ne sert
	 * pas : il ne connaît pas les trois rôles sans `fqn` (`FixtureAiLib::ROLE_*`) et nomme les
	 * autres comme l'écran d'une équipe, pas comme une fiche du décor.
	 *
	 * Chaque libellé nomme la fonction et non la personne, pour se lire sans accord : `USERS` ne porte
	 * aucun genre, et ne reçoit rien qui ne serve qu'à cette page.
	 */
	public function getRoles(): array {

		return [
			\structure\FixtureAiLib::ROLE_ACCOUNTANT => s("comptabilité"),
			'admin' => s("administration"),
			'advisor' => s("conseil"),
			'ghost' => s("accès retiré"),
			'member' => s("adhésion"),
			\structure\FixtureAiLib::ROLE_OBSERVER => s("consultation"),
			\structure\FixtureAiLib::ROLE_PLANNING => s("planning"),
			'referent' => s("coordination"),
			'student' => s("formation"),
		];

	}

	/**
	 * Les familles, dans l'ordre de la page. Une structure appartient à une seule famille.
	 */
	public function getFamilies(): array {

		return [
			['title' => s("Comptabilité et TVA"), 'codes' => ['F1', 'F2']],
			['title' => s("Structure neuve"), 'codes' => ['F3']],
			['title' => s("Autres types de structure"), 'codes' => ['B1', 'A1', 'G2', 'G7']],
			['title' => s("Équipe"), 'codes' => ['F4', 'F5']],
			['title' => s("Groupes de F1 et F2"), 'codes' => ['G1', 'G3']],
			['title' => s("Décor des groupes"), 'codes' => ['G4', 'G5', 'G6', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'P1']],
		];

	}

	/**
	 * Ce que le décor ne dit pas de lui-même, par code : à quoi sert la structure (`usage`, sous le
	 * titre de la fiche), ce qu'elle porte, ce qu'elle ne porte pas, et quand un test la prend. Un fait
	 * par entrée : `usage` ligne à ligne sous le titre, les trois autres en puces dans l'étage déplié.
	 */
	public function getTexts(): array {

		return [
			'F1' => [
				'usage' => [s("Structure de référence"), s("Comptabilité complète"), s("TVA"), s("Quatre rôles d'équipe")],
				'porte' => [s("Assujettie à la TVA"), s("Engagement, réel simplifié"), s("Exercices 2024 et 2025 clos, 2026 ouvert, 2027 créé sans à-nouveau"), s("Comptes de TVA en attente 44588 et 44589"), s("Données légales avec SIRET"), s("Toutes les fonctionnalités sauf PDP et banque"), s("Partage consenti, production partagée avec G1 et G3")],
				'pas' => [s("Aucun client"), s("Aucune vente enregistrée"), s("Aucun partenaire PDP")],
				'quand' => [s("Par défaut"), s("Tout test comptable, par AccountingTest"), s("Les quatre régimes, par eachRegime()")],
			],
			'F2' => [
				'usage' => [s("Non assujettie"), s("Livre des recettes"), s("Un seul exercice"), s("Ne partage rien")],
				'porte' => [s("Micro-BA, livre des recettes"), s("Exercice 2026 ouvert, sans à-nouveau"), s("Ventes, factures, comptabilité"), s("Partage refusé"), s("Rattachée à G1 et G3 sans partager")],
				'pas' => [s("Aucun exercice clos"), s("Aucun à-nouveau"), s("Aucun client, aucune vente enregistrée")],
				'quand' => [s("Exercice unique au livre des recettes (CreateUntilDateAi)"), s("Non-assujettie (VatConfiguredAi)"), s("Refus du partage des données")],
			],
			'F3' => [
				'usage' => [s("Structure neuve"), s("Autre structure d'un cloisonnement")],
				'porte' => [s("Ce que la création sème"), s("TVA non répondue"), s("Pas de comptabilité")],
				'pas' => [s("Aucun membre"), s("Aucun compte")],
				'quand' => [s("Donnée qui ne doit pas passer d'une structure à l'autre"), s("Structure qui n'a rien répondu")],
			],
			'B1' => [
				'usage' => [s("Seule boutique collective du décor")],
				'porte' => [s("Assujettie à la TVA")],
				'pas' => [s("Aucun membre"), s("Aucun producteur")],
				'quand' => [s("Comportement propre à la boutique collective")],
			],
			'A1' => [
				'usage' => [s("Seule AMAP du décor"), s("Une référente")],
				'porte' => [s("Non assujettie"), s("Une référente"), s("Sa boutique, semée à la création")],
				'pas' => [s("Rien au-delà de la création")],
				'quand' => [s("Comportement propre à l'AMAP")],
			],
			'G2' => [
				'usage' => [s("Groupe d'enseignement"), s("Un enseignant"), s("Une élève")],
				'porte' => [s("Enseignant administrateur"), s("Une élève")],
				'pas' => [s("Aucune ferme rattachée")],
				'quand' => [s("Rôles d'enseignant et d'élève")],
			],
			'G7' => [
				'usage' => [s("Réseau des tutoriels"), s("Un administrateur")],
				'porte' => [s("Formations ouvertes"), s("Un administrateur, qui porte la clé d'API")],
				'pas' => [s("Aucune ferme rattachée"), s("Aucune formation hors d'un test")],
				'quand' => [s("Tutoriels lus sans compte (LearningTutorialFixtureTest)"), s("Écriture des tutoriels par l'API")],
			],
			'F4' => [
				'usage' => [s("Équipe avec un ancien membre"), s("Données légales saisies")],
				'porte' => [s("Données légales, sans SIRET"), s("Administrateur, membre au planning"), s("Ancien membre, accès retiré")],
				'pas' => [s("Pas de comptabilité"), s("TVA non répondue")],
				'quand' => [s("Membre sorti, accès retiré"), s("Données légales"), s("Abonnement")],
			],
			'F5' => [
				'usage' => [s("Comptabilité de trésorerie"), s("Un exercice clos et son à-nouveau"), s("Un compte dans deux structures, deux rôles")],
				'porte' => [s("Non assujettie à la TVA"), s("Trésorerie, réel simplifié"), s("Exercice 2025 clos sur six pièces, 2026 ouvert sur l'à-nouveau, 2027 créé sans à-nouveau"), s("Résultat 2025 de 3 000 € au compte 120"), s("Compte courant d'associé 45511 avec son tiers"), s("Données légales avec SIRET"), s("Une administratrice"), s("L'administrateur de F4 au planning")],
				'pas' => [s("Aucune affectation du résultat"), s("Aucun client, aucune vente"), s("Aucun compte bancaire, aucun cahier de caisse")],
				'quand' => [s("Bilan, liasse et dépôt d'un exercice clos"), s("Affectation du résultat"), s("Autoconsommation d'un associé"), s("Compte membre de deux structures"), s("Autre structure d'un test d'abonnement")],
			],
			'G1' => [
				'usage' => [s("Groupe de F1 et F2"), s("F1 partage"), s("F2 ne partage pas")],
				'porte' => [s("Demande à lire ses fermes"), s("F1 partage sa production, F2 non"), s("Administrateurs de F1 et F2 membres")],
				'pas' => [s("Aucun administrateur")],
				'quand' => [s("Partage entre un groupe et F1 ou F2")],
			],
			'G3' => [
				'usage' => [s("Réseau"), s("Une conseillère")],
				'porte' => [s("Demande à lire ses fermes"), s("Une conseillère"), s("Administrateurs de F1 et F2 membres")],
				'pas' => [s("Aucun administrateur")],
				'quand' => [s("Rôle de conseiller dans un réseau")],
			],
			'G4' => [
				'usage' => [s("Groupe qui partage"), s("F6, F7, F10, F11 partagent"), s("F9 ne partage pas")],
				'porte' => [s("Demande à lire ses fermes"), s("Deux administrateurs"), s("Propriétaire de P1")],
				'pas' => [s("Aucune production chez ses fermes")],
				'quand' => [s("communitySharing de CommunityFixtureAiTest")],
			],
			'G5' => [
				'usage' => [s("Groupe qui ne demande rien"), s("F8 rattachée")],
				'porte' => [s("Un administrateur"), s("Aucune demande de lecture")],
				'pas' => [s("Rien de partagé")],
				'quand' => [s("communitySilent de CommunityFixtureAiTest")],
			],
			'G6' => [
				'usage' => [s("Second groupe qui partage"), s("F6 seule")],
				'porte' => [s("Demande à lire ses fermes"), s("Deux administrateurs")],
				'pas' => [s("Aucune autre ferme que F6")],
				'quand' => [s("communitySharingBis de CommunityFixtureAiTest")],
			],
			'F6' => [
				'usage' => [s("Ferme lue par deux groupes")],
				'porte' => [s("Tallende, fiche visible des membres"), s("Partage avec G4 et G6")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme lue par deux groupes"), s("STRUCTURE_SHARED")],
			],
			'F7' => [
				'usage' => [s("Ferme d'un seul groupe"), s("À 53,6 km de F6")],
				'porte' => [s("Beaulieu, fiche visible des membres"), s("Partage avec G4 seul")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme lue par un seul groupe"), s("Distance entre deux fermes"), s("STRUCTURE_SHARING_ONLY")],
			],
			'F8' => [
				'usage' => [s("Ferme du groupe qui ne demande rien")],
				'porte' => [s("Saint-Amant-Tallende, fiche visible des membres"), s("Rattachée à G5")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme dont personne ne demande rien"), s("STRUCTURE_SILENT")],
			],
			'F9' => [
				'usage' => [s("Rattachée sans partager")],
				'porte' => [s("Rattachée à G4"), s("Production non partagée")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme rattachée qui ne partage pas"), s("structureSharedWithoutProduction")],
			],
			'F10' => [
				'usage' => [s("Ferme fermée"), s("Encore rattachée à G4")],
				'porte' => [s("Fermée après son rattachement à G4")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme fermée qu'un groupe ne lit plus"), s("structureClosed")],
			],
			'F11' => [
				'usage' => [s("Ferme d'un second associé")],
				'porte' => [s("Administrée par Pauline, rattachée par elle à G4"), s("Olivier aussi administrateur")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme rattachée par un autre compte"), s("structureOtherAssociate")],
			],
			'P1' => [
				'usage' => [s("Ferme projet d'un groupe")],
				'porte' => [s("Ferme projet de G4")],
				'pas' => [s("Aucune production")],
				'quand' => [s("Ferme projet possédée par un groupe"), s("structureProject")],
			],
		];

	}

}
?>
