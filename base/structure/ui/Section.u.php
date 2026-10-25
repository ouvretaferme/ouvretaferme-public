<?php
namespace structure;

/**
 * Les écrans des sous-groupes : la page qui tient la liste, et le panneau qui en compose un.
 *
 * ⚠️ Ni le fichier ni la classe ne portent le suffixe `Ai`, pour la raison même qui en exempte les
 * éléments : le nom est imposé par le cadriciel. `FormUi::dynamicField()` résout le describer en
 * `$e->getModule().'Ui'` (`framework/util/ui/Form.u.php:415`), donc un `SectionAiUi` ne serait
 * jamais trouvé et les champs se rendraient sans libellé ni contrôle, sans la moindre erreur.
 *
 * Rien n'est calculé ici : les effectifs et les listes arrivent posés sur les éléments par
 * `page/sectionAi.p.php`, sous `cUser` pour les personnes rangées dans le sous-groupe et
 * `cMemberStructure` pour les adhésions du groupe entier, dont la composition tire ses libellés.
 */
class SectionUi {

	public function __construct() {
		\Asset::css('structure', 'sectionAi.css');
	}

	/**
	 * La liste et la création, sur la page de gestion.
	 *
	 * ⚠️ Ce tableau et celui du tableau de bord portent les mêmes sous-groupes et ne doivent pas
	 * se ressembler : là-bas on lit et on compare, donc trois colonnes de chiffres et aucun geste ;
	 * ici on agit, donc les gestes et pas de colonne « fermes », qui ne sert aucune décision qu'on
	 * prend sur cet écran.
	 *
	 * ⚠️ La création vit sur la ligne de titre, comme partout ailleurs dans le produit. Elle ne
	 * reste ici que sur une liste vide, dans le bloc qui annonce l'absence : c'est alors le seul
	 * geste de l'écran, et l'envoyer chercher un bouton au-dessus du vide serait un détour.
	 */
	public function getManage(Structure $eStructure, \Collection $cSection, \Collection $cSectionClosed, Role $eRole = new Role()): string {

		if($cSection->notEmpty() or $cSectionClosed->notEmpty()) {
			return $this->getList($eStructure, $cSection, $cSectionClosed, $eRole);
		}

		$h = '<div class="util-block util-block-empty">';
			$h .= '<p>'.StructureUi::getText($eStructure, 'sectionEmpty').'</p>';

			// Une colonne : la création est une ligne, pas un formulaire à libellés. Le défaut du
			// cadriciel est `columns => 2`, qui pose form-columns et casserait la ligne.
			$form = new \util\FormUi(['columns' => 1]);

			$h .= $form->openAjax('/structure/sectionAi:doCreate');

				$h .= $form->hidden('structure', $eStructure);

				$h .= '<div class="section-create">';
					$h .= $form->dynamicField(
						new Section(),
						'name',
						fn(\PropertyDescriber $d) => $d->placeholder = StructureUi::getText($eStructure, 'sectionNew')
					);
					$h .= $form->submit(s("Créer"));
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le panneau de création, ouvert par le bouton de la ligne de titre.
	 */
	public function create(Structure $eStructure, Section $eSection): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/sectionAi:doCreate');

			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->dynamicGroups($eSection, ['name*']);

			$h .= $form->group(
				content: $form->submit(s("Créer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-section-create',
			title: StructureUi::getText($eStructure, 'sectionCreate'),
			body: $h
		);

	}

	/**
	 * Les clos ferment la liste, dans leur propre `tbody` : c'est ce qui pose le filet qui les
	 * sépare, sans une ligne de CSS. Ils restent atteignables, clore ne fermant rien.
	 */
	protected function getList(Structure $eStructure, \Collection $cSection, \Collection $cSectionClosed, Role $eRole): string {

		$h = '<div class="util-overflow">';
			$h .= '<table class="tr-even section-list">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Nom").'</th>';
						$h .= '<th class="text-center">'.s("Membres").'</th>';
						$h .= '<th class="td-min-content"></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				foreach([$cSection, $cSectionClosed] as $cSectionPart) {

					if($cSectionPart->empty()) {
						continue;
					}

					$h .= '<tbody>';

						foreach($cSectionPart as $eSection) {
							$h .= $this->getLine($eStructure, $eSection, $eRole);
						}

					$h .= '</tbody>';

				}

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	protected function getLine(Structure $eStructure, Section $eSection, Role $eRole): string {

		$members = $eSection['cUser']->count();

		$h = '<tr'.($eSection->isClosed() ? ' class="color-muted"' : '').'>';

			$h .= '<td class="section-line-name">';
				$h .= '<a href="/structure/sectionAi:update?id='.$eSection['id'].'">'.encode($eSection['name']).'</a>';
				if($eSection->isClosed()) {
					$h .= ' '.StructureCommunityUi::getSectionClosed();
				}
			$h .= '</td>';

			$h .= '<td class="text-center">';
				$h .= '<span class="util-counter">'.$members.'</span>';
			$h .= '</td>';

			$h .= '<td class="td-min-content">';
				$h .= '<div class="section-line-actions">';

					// ⚠️ Le mot du type et jamais « promotion » écrit en dur : ce fichier sert les
					// quatre types de groupes, et un réseau d'accompagnement se voyait proposer de
					// clôturer une promotion qu'il n'a pas.
					$the = StructureUi::getText($eStructure, 'sectionThe');

					// Inviter passe devant composer : une promotion se peuple d'abord de gens qui
					// n'ont pas encore de compte, et composer ne range que ceux qui sont déjà là.
					// L'invitation entre directement dans cette ligne-là, avec le rôle que le
					// sous-groupe borne.
					$role = $eRole->notEmpty() ? '&memberRole='.$eRole['id'] : '';

					$h .= '<a href="/structure/invite:createMember?structure='.$eStructure['id'].$role.$eSection->getInviteArgument().'" class="btn btn-outline-primary">'.StructureUi::getText($eStructure, 'membersInvite').'</a>';

					$h .= '<div>';
						$h .= '<a class="dropdown-toggle btn btn-color-primary" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
						$h .= '<div class="dropdown-list">';

							$h .= '<a href="/structure/sectionAi:compose?id='.$eSection['id'].'" class="dropdown-item">'.s("Composer {value}", $the).'</a>';

							$h .= '<a data-ajax="/structure/sectionAi:doUpdateStatus" post-id="'.$eSection['id'].'" post-status="'.($eSection->isClosed() ? Section::OPEN : Section::CLOSED).'" class="dropdown-item">';
								$h .= $eSection->isClosed() ? s("Réouvrir {value}", $the) : s("Clôturer {value}", $the);
							$h .= '</a>';

							$h .= '<div class="dropdown-divider"></div>';

							// La confirmation nomme l'effectif parce que c'est exactement ce qu'on
							// perd : les membres restent membres, mais plus rien ne dit où ils
							// étaient rangés, et aucune contrainte de la base ne l'aurait signalé.
							// Un sous-groupe vide ne perd personne, et l'annoncer serait faux.
							$confirm = ($members > 0)
								? StructureUi::getText($eStructure, 'sectionDeleteWarning', $members)
								: s("Voulez-vous vraiment supprimer {value} ?", StructureUi::getText($eStructure, 'sectionThis'));

							$h .= '<a data-ajax="/structure/sectionAi:doDelete" post-id="'.$eSection['id'].'" class="dropdown-item" data-confirm="'.$confirm.'">';
								$h .= s("Supprimer {value}", $the);
							$h .= '</a>';

						$h .= '</div>';
					$h .= '</div>';

				$h .= '</div>';
			$h .= '</td>';

		$h .= '</tr>';

		return $h;

	}

	public function update(Structure $eStructure, Section $eSection): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/sectionAi:doUpdate');

			$h .= $form->hidden('id', $eSection['id']);
			$h .= $form->dynamicGroups($eSection, ['name*']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-section-update',
			title: s("Renommer"),
			subTitle: StructureUi::getText($eStructure, 'section'),
			body: $h
		);

	}

	/**
	 * Composer se fait depuis le sous-groupe et jamais depuis la personne : y ranger dix-sept
	 * membres un par un demanderait dix-sept panneaux et dix-sept enregistrements, sans jamais
	 * montrer la promotion qu'on est en train de constituer.
	 *
	 * Le bouton vit dans le pied du panneau, qui est collant (`lime.css:181`), et il est relié au
	 * formulaire par l'attribut HTML `form` : le corps et le pied d'un `\Panel` sont deux nœuds
	 * distincts, aucune balise ne peut les envelopper tous les deux.
	 */
	public function compose(Structure $eStructure, Section $eSection): \Panel {

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->openAjax('/structure/sectionAi:doCompose', ['id' => 'section-compose']);

			$h .= $form->hidden('id', $eSection['id']);
			$h .= $form->dynamicField($eSection, 'users');

		$h .= $form->close();

		$footer = $form->submit(s("Enregistrer"), ['form' => 'section-compose']);

		return new \Panel(
			id: 'panel-section-compose',
			title: encode($eSection['name']),
			subTitle: StructureUi::getText($eStructure, 'section'),
			body: $h,
			footer: $footer
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Section::model()->describer($property, [
			'name' => s("Nom"),
			'users' => s("Membres"),
			'status' => s("Statut")
		]);

		switch($property) {

			case 'users' :

				$d->field = fn(\util\FormUi $form, Section $e) => $form->checkboxes(
					'users[]',
					// SectionUi:: et non self:: : Closure::call() rebinde la portée du callback
					// sur PropertyDescriber (Form.u.php:522), où self ne désigne plus cette classe.
					SectionUi::getUserValues($e['cMemberStructure']),
					$e['cUser'],
					['columns' => 1]
				);

				break;

			case 'status' :

				$d->values = [
					Section::OPEN => s("Ouvert"),
					Section::CLOSED => s("Clos")
				];

				break;

		}

		return $d;

	}

	/**
	 * Les cases de la composition, indexées par id d'utilisateur : c'est ce que la colonne range.
	 * Les libellés viennent des adhésions, qui seules portent le rôle dans ce groupe.
	 */
	public static function getUserValues(\Collection $cMember): array {

		$values = [];

		foreach($cMember as $eMember) {

			$h = '<div class="section-compose-member">';
				$h .= '<span>'.encode($eMember['user']['firstName'].' '.$eMember['user']['lastName']).'</span>';
				$h .= '<span class="color-muted">'.encode($eMember['role']['name']).'</span>';
			$h .= '</div>';

			$values[$eMember['user']['id']] = $h;

		}

		return $values;

	}

}
?>
