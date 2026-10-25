<?php
namespace design;

/**
 * L'onglet Dev : comment on travaille avec les agents sur ce dépôt.
 *
 * Il tient en une page parce qu'il n'est utile que s'il se lit d'un trait, avant d'ouvrir une
 * fenêtre. Ce qu'une session lit vraiment, c'est `.claude/skills/<nom>/SKILL.md` : cette page dit
 * quoi lancer, dans quel ordre et ce que ça engage, elle ne recopie aucun skill. Les règles de code
 * sont dans `CLAUDE.md`, celles de l'interface dans l'onglet Ui.
 *
 * Les cinq sections répondent chacune à une question qu'on se pose dans l'ordre : avec combien de
 * fenêtres, qui tient quoi, dans quel ordre, ce qui change à plusieurs, ce qui se lance à tout
 * moment.
 */
class DevAiUi {

	public function all(): string {

		$h = '';

		foreach([
			'modes' => $this->getModes(),
			'roles' => $this->getRoles(),
			'lot' => $this->getLot(),
			'ensemble' => $this->getEnsemble(),
			'commandes' => $this->getCommandes(),
		] as $key => $section) {

			$h .= '<section id="dev-'.$key.'">'.$section.'</section>';
			$h .= '<hr />';

		}

		return $h;

	}

	private function getModes(): string {

		$h = '<h2>'.s("1. Choisir un mode").'</h2>';
		$h .= '<p class="util-annotation">'.s("Une commande, tapée au début de chaque fenêtre. Le mode dit qui valide quoi. En cas de doute, prendre le plus léger : on monte d'un cran quand le chantier le demande, jamais l'inverse.").'</p>';

		// Le nombre de fenêtres est dans la phrase et non dans une colonne à lui : sous 768px les rangs
		// s'empilent et l'en-tête disparaît, où « Deux » seul ne dirait plus deux quoi.
		$h .= $this->getTable([s("Commande"), s("Quand")], [
			['/solo', s("Une fenêtre. Une correction, un petit lot : la session code, se contrôle elle-même, livre.")],
			['/tandem maquetteur · /tandem codeur', s("Deux fenêtres. Un écran à dessiner puis à coder. Vous parlez aux deux, il n'y a pas de chef de projet.")],
			['/equipe', s("Trois fenêtres et plus. Un chantier entier, du besoin à la prod. Un chef de projet tient les rôles et vous pose les arbitrages.")],
			['/virtual-vincent', s("Le même, quand vous n'êtes pas là : la session rend elle-même les arbitrages que vous rendriez, avance par tranche jusqu'à la porte que vous avez nommée, et ne vous présente que des choix fermés.")],
			['/virtual-emilie', s("Une fenêtre qui tient un chantier comme Émilie le tient, du signalement d'une structure jusqu'à la production, en éprouvant tout sur une structure réelle.")],
			['/bugfix', s("Une fenêtre. Un bug signalé, par une trace, une capture ou une phrase d'utilisatrice : la session remonte à la cause, corrige, et répare par script la donnée que le code a abîmée.")],
		]);

		return $h;

	}

	private function getRoles(): string {

		$h = '<h2>'.s("2. Les rôles").'</h2>';
		$h .= '<p class="util-annotation">'.s("Un rôle par fenêtre, tapé au début. En équipe et en tandem, l'architecte, le relecteur et l'UI se lancent en sous-agents par la session qui les appelle : il n'y a pas de fenêtre à leur ouvrir.").'</p>';

		$h .= $this->getTable([s("Commande"), s("Intervient"), s("Tient")], [
			['/chef-de-produit', s("Avant que ça existe"), s("Le besoin, la concurrence, la spécification et le registre des cas limites.")],
			['/maquetteur', s("Avant le code, puis à la livraison"), s("La maquette, sa validation, puis la recette de ce que le codeur livre contre elle.")],
			['/ui', s("Sur la maquette, puis sur le diff"), s("Le design system. Aucun écran ne part sans sa passe : en solo, la session la joue elle-même.")],
			['/architecte', s("Avant le code, puis sur le diff"), s("Le cadriciel et le logiciel : les couches, l'ORM, le multi-tenant.")],
			['/codeur', s("Après une maquette validée"), s("Le code, les tests des paquets touchés, et ce qu'il a vérifié ou n'a pas pu vérifier.")],
			['/relecteur', s("Sur le diff"), s("La relecture et l'épreuve en dev sur les cas limites, rendues en un verdict écrit.")],
			['/performance', s("Sur le diff"), s("Les requêtes, les doubles et le poids des pages témoins, sur une petite et une grosse structure, à la fin du lot, et chaque lundi un relevé des pages lourdes.")],
			['/releve-performance', s("De temps en temps"), s("Le relevé de la semaine, du journal des réponses aux pistes rattachées à leur appel, sans rien corriger.")],
			['/gardien', s("Après un incident, jamais sur un lot"), s("La façon de travailler des sessions : les règles partagées, les tests-sentinelles, l'outillage et le design system. Ne tient aucun chantier.")],
			['/captain-compta', s("Chaque semaine, et quand un texte de droit change"), s("Les textes de droit que le code cite, leur registre et leur veille. Porte chaque changement au chantier qui a écrit le code.")],
			['/auteur-formation', s("Avant d'écrire une formation, jusqu'à sa livraison"), s("Les objectifs d'une formation de l'e-learning, que seule cette fenêtre connaît, puis son contenu. Elle la fait relire par un formateur et suivre par des étudiants qui ignorent ces objectifs, et ne la livre qu'une fois ce qu'ils disent avoir appris rapproché de ce qu'elle visait.")],
			['/formateur', s("À la demande de l'auteur, dans sa propre fenêtre"), s("La relecture et la note d'une formation dont il ignore les objectifs, puis au moins cinq étudiants lancés en sous-agents, sur des modèles et des profils différents. Il leur pose les questions de vérification de l'auteur et rend une synthèse. Ne corrige rien.")],
			['/etudiant', s("En sous-agent, lancé par le formateur"), s("Une formation suivie dans Chrome comme une structure la suivrait, sans en connaître les objectifs, puis le formulaire de fin (ce qu'il a appris, satisfaction, clarté, points à améliorer) et les réponses aux questions de vérification.")],
		]);

		return $h;

	}

	private function getLot(): string {

		$h = '<h2>'.s("3. Le chemin d'un lot").'</h2>';
		$h .= '<p class="util-annotation">'.s("Six étapes, dans cet ordre. Chacune a une sortie qu'on peut montrer, et c'est cette sortie qui prouve qu'elle a eu lieu : une étape dont on ne peut rien montrer n'est pas faite.").'</p>';

		$h .= $this->getTable([s("Étape"), s("Commande"), s("Ce qui en sort")], [
			[s("Concevoir"), '/chef-de-produit', s("Une spécification et des cas limites. C'est là qu'on tranche ce que le produit fait, pas pendant le codage.")],
			[s("Maquetter"), '/maquetteur · /ui', s("Une maquette que vous avez validée. Même en solo : une piste choisie n'est pas un écran validé.")],
			[s("Coder"), '/codeur', s("Le code, les tests rejoués paquet par paquet, et la passe UI avec le nombre d'écarts trouvés et corrigés.")],
			[s("Contrôler"), '/relecteur · /architecte', s("Un verdict écrit. Tous les tests-sentinelles tournent avant toute livraison.")],
			[s("Recetter"), '/recette', s("Une page où vous éprouvez le lot et répondez ligne à ligne. La livraison est la publication de cette page, pas le message qui l'annonce.")],
			[s("Déployer"), '/deploiement · /enprod', s("Une page qui dit quoi jouer, dans quel ordre, et ce qui bloque les écritures pendant ce temps.")],
		]);

		return $h;

	}

	private function getEnsemble(): string {

		$h = '<h2>'.s("4. À plusieurs agents sur le même dépôt").'</h2>';
		$h .= '<p class="util-annotation">'.s("Une vingtaine de fenêtres écrivent dans un seul répertoire de travail, et des humains tirent le même code ailleurs. Ces quatre règles valent pour tout le monde, agent ou pas.").'</p>';

		$h .= $this->getTable([s("Règle"), s("Pourquoi")], [
			[s("Une fenêtre ne touche que ses fichiers"), s("Jamais de commit, de push ni de stash de sa propre initiative, jamais d'écriture dans le travail d'une autre. L'index de git est partagé lui aussi : un fichier ajouté part dans le commit du voisin.")],
			[s("Un schéma ne part jamais seul"), s("Un .yml modifié, une reprise de données, un ALTER écrit à la main : chacun part avec son fichier daté dans migration/, dans le même commit. Sans lui, celui qui tire se retrouve avec une base en retard et des tests rouges qu'il ne peut rattacher à rien. Ce n'est pas qu'une consigne : /push refuse de pousser un schéma que ne couvre aucun fichier, et un test dit à celui qui tire quel fichier jouer.")],
			[s("Ce qui est en prod se dit à tout le monde"), s("/enprod dès que c'est joué : il consigne et prévient les autres fenêtres d'un geste. Sinon la suivante remet dans sa page de déploiement des commandes déjà jouées.")],
			[s("Un test rouge n'est pas forcément une régression"), s("Il peut venir d'un schéma tiré sans sa migration, ou d'une autre fenêtre qui écrivait sur la même base au même moment. Ça se prouve en retirant son propre diff, pas en lisant le message.")],
			[s("On ne tire jamais avec git pull"), s("git pull, git rebase, git stash et « Update Project » de PhpStorm supposent un répertoire de travail propre : ici ils mettent en réserve les 200 à 250 fichiers modifiés de toutes les fenêtres, et ne les rendent pas toujours en entier. Le 20 septembre au soir, « Update Project » en a pris 227 et en a rendu dix de moins, sans rien signaler. /pull aligne fichier par fichier et n'écrit rien tant qu'un conflit subsiste.")],
		]);

		return $h;

	}

	private function getCommandes(): string {

		$h = '<h2>'.s("5. À tout moment").'</h2>';
		$h .= '<p class="util-annotation">'.s("Ces commandes ne demandent ni de changer de mode ni d'ouvrir une fenêtre.").'</p>';

		$h .= $this->getTable([s("Commande"), s("Ce qu'elle fait")], [
			['/etat', s("Dit où en est chaque chantier, en lisant les fichiers d'état, sans rien demander aux autres fenêtres.")],
			['/etat-code', s("Fait l'état de ce qui n'est pas poussé dans le répertoire de travail. Il dit à quelle fenêtre appartient chaque fichier, ce qui n'appartient à personne et quoi en faire, et ce qui bloque un push. Il demande aux fenêtres ce qu'elles réclament, et repère ce qui défait un commit déjà poussé.")],
			['/recette', s("Publie la page de recette du développement en cours, ou de ce qu'on lui désigne.")],
			['/deploiement', s("Publie la page de mise en production du périmètre de la fenêtre. Avec global, elle interroge toutes les autres.")],
			['/pull', s("Met à jour le dépôt sans toucher au travail des autres fenêtres, puis joue en développement les migrations arrivées avec le code.")],
			['/push', s("Pousse le code de la fenêtre, refuse un schéma qui part sans sa migration, puis publie dans le salon Discord des migrations les commandes à jouer.")],
			['/emergency-push', s("Pousse tout de suite le correctif d'un défaut de production. Les contrôles rapides du push restent, la relecture et la passe complète des sentinelles se jouent juste après, sur le commit poussé.")],
			['/enprod', s("Consigne qu'un lot est parti et prévient toutes les fenêtres.")],
			['/nettoyage', s("Relève les fermes, groupes et AMAP de test qu'une recette a créés dans la base de développement, avec leurs données dans toutes les tables qui s'y rattachent et leur base de structure. Le produit ne sait pas supprimer une structure, donc elles s'accumulent : il y en avait 78 le 26 septembre, pour 314 782 lignes. Il écarte tout seul celles qu'un chantier ou le dépôt cite encore, il demande le reste aux fenêtres concernées, et la suppression part de votre mot, jamais de la sienne.")],
			['/hierarchise', s("Sur une capture où tout pèse pareil, classe chaque élément du plus au moins visible, puis retouche les poids ou refait le bloc entier quand la structure est en cause, avec les composants du design system.")],
			['/stats', s("Rend, semaine par semaine sur les trois derniers mois, le nombre de commits poussés et les lignes ajoutées et supprimées.")],
			['/demarrage', s("Pose sur le poste ce qui tourne sans fenêtre ouverte, la veille du poste et la lecture du commit en production, et dit ce qui manque. Se rejoue à tout moment sans rien doubler.")],
			['/super-reviewer', s("Audite tout le répertoire de travail non commité, et rend un rapport classé par gravité.")],
			['/reprends', s("Relance d'un geste toutes les fenêtres coupées par une panne de quota ou de modèle.")],
			['/restart', s("Après un changement de compte Claude, ferme toutes les fenêtres du poste, la sienne en dernier, et les fait repartir sur leur conversation sous le nouveau compte. Un onglet lancé par .claude/wakeup la reprend seul.")],
			['/send', s("Fait parvenir un message à un autre poste, depuis n'importe quelle fenêtre. Elle le confie au gardien de son poste, qui le porte au gardien d'en face, et celui-ci voit avec ses fenêtres ou avec la personne derrière son écran.")],
			['/contre-recette', s("Recette un écran ou un lot comme l'utilisateur final le vivra, dans Chrome, avant qu'il vous arrive : parcours depuis le menu, recul sur la page, dérive, problèmes déjà connus, cas dur, puis deux regards neufs par des agents qui ne connaissent pas le chantier. /virtual-vincent la joue seul avant chaque page de recette ; ailleurs, elle se lance sur votre demande.")],
			['/glacon', s("Éprouve à froid une fonctionnalité entière, comme ses utilisateurs la vivront. La fenêtre part d'une structure vide, joue chaque rôle dans Chrome du premier écran au dernier geste du cycle de vie, sans lire le code du chantier, et rend une page avec les bugs, les problèmes d'usage par rôle et des pistes.")],
			['/ecrire-skill', s("Écrit ou modifie un skill : la règle au niveau du principe, à un seul endroit, le récit dans cas-vus, les renvois vérifiés, puis les fenêtres qui tiennent le rôle prévenues. Un skill ne se modifie jamais autrement.")],
			['/3d', s("Modélise un objet 3D dans Blender, puis l'exporte pour la scène sous un budget de triangles. La fenêtre part d'une fiche de référence, essaie plusieurs partis pris l'un après l'autre, relit chaque version sur une planche de capture et la fait nommer par des juges à l'aveugle.")],
			['/3d-batiment', s("Fait le modèle 3D d'un bâtiment d'après une photo ou une description, sur le cadre commun à tous les bâtiments et le catalogue partagé des toits, murs, ouvertures et éléments, sous 1 500 triangles, puis le livre à la scène.")],
			['/3d-perso', s("Fait le personnage 3D d'une personne d'après sa photo, en un seul appel, sur le corps commun habillé et coiffé d'entrées du catalogue, sous 2 000 triangles. Le personnage est contrôlé avant d'être livré aux scènes.")],
			['/scene', s("Tient une scène 3D qui montre les données d'un utilisateur, comme un plan ou des flux joués dans le temps. Elle dit ce que la scène a le droit de montrer, depuis quelles vues et sur quelles structures on la juge, comment on mesure ses défauts et son budget, et comment on fait travailler ceux qui l'écrivent.")],
			['/security', s("Pose, au plan puis sur le diff, la question de l'exposition de chaque point d'entrée : qui l'appelle, quelle structure borne la donnée, quel état le ferme, et par quelle autre voie on l'atteint. Elle lit aussi ce que les sentinelles de sécurité déclarent ne pas voir. Elle rend une fiche de quinze lignes et ne corrige rien. Le gardien la lance une fois par semaine sur les scripts tiers.")],
			['/compta', s("Le socle métier de tout développement comptable, à charger dès qu'une fenêtre touche accounting/ ou Migrate.l.php : les quatre régimes, les bases multi-tenant, la façon de lancer et de lire les tests.")],
			['/api', s("Le socle de tout développement de l'API publique, à charger dès qu'une fenêtre touche un fichier d'api/ : ce qu'une route peut faire et ce qu'elle ne fait jamais, comment un package s'expose, la forme d'une ressource et de son corps de requête, les libellés de la référence, et comment éprouver le refus autant que le succès.")],
			['/fec-importeur', s("Reprend dans OTF la comptabilité qu'un cabinet tient ailleurs, depuis ses FEC annuels et son tableau d'amortissements.")],
			['/fec-verificateur', s("Contrôle qu'une comptabilité reprise correspond aux FEC d'origine et aux règles de OTF. Après une reprise, ou sur une structure dont on doute.")],
			['/diagnostic', s("Dit quel script lancer pour comprendre une panne sur une structure (banque, plateforme agréée, cahier de caisse, écritures dissociées), et le lance par un filtre qui ne laisse passer ni nom ni libellé.")],
			['/article', s("Intègre dans l'aide à la comptabilité un article rédigé hors du dépôt, en général un document déposé dans la Dropbox. Il vérifie chaque taux, date et numéro d'article sur Légifrance ou le BOFiP, et date la prochaine relecture.")],
			['/compte', s("Prépare un changement de compte Claude, et se tape avant le /login. Une fois le compte changé, les pages d'artefact publiées depuis l'ancien deviennent illisibles pour toutes les fenêtres à la fois, et plus rien ne dit lesquelles existaient : il en prend l'inventaire pendant qu'on peut encore le faire, demande à chaque fenêtre ce qu'elle tient, puis remet Chrome et republie ce qui est tombé.")],
		]);

		return $h;

	}

	/**
	 * Les cinq sections sont des listes à deux ou trois colonnes, et c'est la seule fabrique :
	 * ajouter une ligne ne demande jamais de choisir un balisage.
	 *
	 * Les pistes vivent dans `designAi.css`, sous `.design-dev-2` et `.design-dev-3`, parce que
	 * `util-table` déclare ses colonnes en CSS et que c'est la seule façon de les redistribuer sous
	 * une media query.
	 */
	private function getTable(array $headers, array $rows): string {

		$h = '<div class="util-table util-table-even design-dev-'.count($headers).'">';

			$h .= '<div class="util-table-head">';

				foreach($headers as $header) {
					$h .= '<div>'.$header.'</div>';
				}

			$h .= '</div>';

			foreach($rows as $row) {

				$h .= '<div class="util-table-row">';

					foreach($row as $index => $cell) {
						$h .= '<div>'.($index === 0 ? '<b>'.$cell.'</b>' : $cell).'</div>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

}
?>
