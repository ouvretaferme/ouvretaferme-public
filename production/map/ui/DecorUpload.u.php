<?php
namespace map;

/**
 * L'onglet « Importer un modèle 3D » de l'éditeur d'un bâtiment neuf (`DecorBuildingUi`) : le nom, le dépôt et l'aide,
 * ceux de l'avatar (`\scene\UploadUi`), puis le modèle vérifié, que « Valider » montre dans l'aperçu avant qu'on l'ajoute.
 */
class DecorUploadUi {

	// Le binaire se sert par sa route, après contrôle des droits, jamais par l'adresse du stockage
	public static function url(DecorUpload $e): string {
		return '/map/decorUpload:model?id='.$e['id'];
	}

	public function getTab(\util\FormUi $form, DecorBuilding $eDecorBuilding): string {

		$eDecorBuilding->expects(['uploadRules']);

		// Sur cet onglet, le nom n'a que le dépôt sous lui : libellé à gauche comme dans les autres formulaires, alors que la
		// composition empile ses choix sous leur libellé (`columns => 1`) pour garder leurs vignettes sur toute la largeur. Le
		// groupe vient donc d'un formulaire à deux colonnes, dans le gabarit que `FormUi::open()` pose sur un tel formulaire
		$h = '<div class="form-columns form-columns-33 form-columns-xs">';
			$h .= new \util\FormUi()->dynamicGroup($eDecorBuilding, 'name', function(\PropertyDescriber $d) {
				$d->attributes = ['placeholder' => s("Grange")];
			});
		$h .= '</div>';

		$h .= $this->getDrop($form, $eDecorBuilding);

		return $h;

	}

	protected function getDrop(\util\FormUi $form, DecorBuilding $eDecorBuilding): string {

		$rules = $eDecorBuilding['uploadRules'];

		$uploadUi = new \scene\UploadUi();

		return $uploadUi->getDrop(
			$form,
			'MapSceneBuilding',
			'/map/decorUpload:doCreate',
			'/map/decorUpload:doPreview',
			$rules,
			'map\\DecorUpload::file.conformity',
			FALSE,
			[
				'head' => s("Glissez votre modèle 3D ici"),
				'tooHeavy' => \user\AlertUi::getAvatarUploadWeight(\util\TextUi::size('{size}'), \util\TextUi::size($rules['maxBytes'])),
				'commitment' => s("Ce modèle ne montre aucun logo ni aucune personne réelle."),
				'add' => s("Ajouter ce bâtiment"),
				'adding' => s("Ajout..."),
			],
			$uploadUi->getSkill(
				'/map/decorUpload:skill?structure='.$eDecorBuilding['structure']['id'],
				s("Téléchargez le skill du décor."),
				s("Demandez-lui un bâtiment, d'après une photo ou une description.")
			),
			s("J'accepte que Ouvretaferme propose ce modèle aux autres utilisateurs, sans contrepartie.")
		);

	}


	/**
	 * L'onglet de l'équipe : les modèles à examiner, déjà montrés sur le plan de leur structure, puis ceux qui ont été
	 * examinés. Un retrait ou une sélection se fait sur la carte du modèle, et « Tout valider » garde ensuite le reste de la
	 * file affichée, dont chaque carte poste l'id : un envoi arrivé depuis attend d'avoir été vu. Le filtre « Partage
	 * accepté » restreint les deux listes, et l'adresse le garde d'une page à l'autre.
	 *
	 * Les listes ne lisent pas le hash, qui ouvre le fichier à qui le connaît par l'adresse publique du stockage. Elles ne
	 * peuvent donc pas juger l'état d'un envoi, qui se lit sur ce hash (`acceptRead()`), et choisissent leurs gestes d'après
	 * le statut et les dates ; chaque action rejuge l'état sur l'élément complet, et la condition de chaque écriture le porte.
	 */
	public function getAdmin(bool $shared, \Collection $cDecorUploadWaiting, int $nDecorUploadWaiting, \Collection $cDecorUploadExamined): string {

		\Asset::css('map', 'decorUpload.css');
		\Asset::js('map', 'decorUpload.js');

		// L'interrupteur et son libellé basculent le filtre, et la liste repart de sa première page
		$url = '/map/admin/decorUpload'.($shared ? '' : '?shared=1');

		$h = '<div class="util-filters mb-2">';
			$h .= '<div class="util-filter">';
				$h .= '<a href="'.$url.'" class="util-filter-label">'.s("Partage accepté").'</a>';
				$h .= \util\TextUi::switch(['href' => $url], $shared);
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-title">';
			$h .= '<h3>'.s("À examiner").self::getCounter($nDecorUploadWaiting).'</h3>';
		$h .= '</div>';

		if($cDecorUploadWaiting->empty()) {
			$h .= '<div class="util-block-empty mb-2"><p>'.s("Aucun modèle à examiner.").'</p></div>';
		} else {

			$form = new \util\FormUi();

			$h .= $form->openAjax('/map/admin/decorUpload:doKeepAll', ['class' => 'mb-2']);

				$h .= '<div class="util-cards util-cards-small">';

					foreach($cDecorUploadWaiting as $eDecorUpload) {

						$h .= '<div class="util-card">';
							$h .= $form->hidden('ids[]', $eDecorUpload);
							$h .= $this->getPreview($eDecorUpload, 'list');
							$h .= '<div class="mt-1">'.$this->getAbout($eDecorUpload).'</div>';
							$h .= '<div class="util-card-foot">';
								// Ni la liste ni la carte ne lisent le hash : le geste suit l'accord et la date, l'action rejuge le reste
								if($eDecorUpload['sharedAt'] !== NULL and $eDecorUpload['chosenAt'] === NULL) {
									$h .= $this->getChoose($eDecorUpload, 'btn btn-outline-primary btn-sm');
								}
								$h .= '<a href="/map/admin/decorUpload:withdraw?id='.$eDecorUpload['id'].'" class="btn btn-outline-primary btn-sm">'.s("Retirer").'</a>';
							$h .= '</div>';
						$h .= '</div>';

					}

				$h .= '</div>';

				$h .= '<div class="mt-2">'.$form->submit(s("Tout valider")).'</div>';

			$h .= $form->close();

		}

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Examinés").'</h3>';
		$h .= '</div>';

		if($cDecorUploadExamined->empty()) {
			$h .= '<div class="util-block-empty"><p>'.s("Aucun modèle examiné.").'</p></div>';
			return $h;
		}

		$h .= '<div class="util-table util-table-hover map-admin-uploads">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Modèle").'</div>';
				$h .= '<div>'.s("Nom").'</div>';
				$h .= '<div class="hide-xs-down">'.s("État").'</div>';
			$h .= '</div>';

			foreach($cDecorUploadExamined as $eDecorUpload) {

				$state = '<div>'.$this->getState($eDecorUpload).'</div>'.$this->getExaminedActions($eDecorUpload);

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$this->getPreview($eDecorUpload, 'list').'</div>';
					// Pas util-action pour l'état et ses gestes : son retour à la ligne passerait l'engrenage sous l'état
					// dès que l'état est plus large que la cellule, quand ici c'est l'état qui se replie à côté de lui
					$h .= '<div>';
						$h .= $this->getAbout($eDecorUpload);
						if($eDecorUpload['sharedAt'] !== NULL) {
							$h .= '<div class="mt-1"><span class="badge badge-info">'.s("Partage accepté").'</span></div>';
						}
						$h .= '<div class="hide-sm-up mt-1 flex-justify-space-between">'.$state.'</div>';
					$h .= '</div>';
					$h .= '<div class="hide-xs-down flex-justify-space-between">'.$state.'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/** Un compteur à zéro s'afficherait : il n'est pas posé. */
	private static function getCounter(int $number): string {
		return ($number > 0) ? ' <span class="util-counter">'.$number.'</span>' : '';
	}

	/**
	 * Le nom du bâtiment ou de l'élément qui montre le modèle, déjà échappé.
	 */
	private static function getName(DecorUpload $eDecorUpload): string {

		$eDecor = $eDecorUpload['building']->notEmpty() ? $eDecorUpload['building'] : $eDecorUpload['object'];

		return encode($eDecor['name']);

	}

	/**
	 * Le nom, la structure, puis l'auteur, qui mène à la fiche du compte et non à la recherche des utilisateurs, et la date
	 * d'import, au même format dans les deux listes.
	 */
	private function getAbout(DecorUpload $eDecorUpload): string {

		$h = '<b>'.self::getName($eDecorUpload).'</b>';
		$h .= '<div class="util-annotation">'.encode($eDecorUpload['structure']['name']).'</div>';
		$h .= '<div class="mt-1"><a href="/user/admin/:update?id='.$eDecorUpload['createdBy']['id'].'">'.$eDecorUpload['createdBy']->getName().'</a></div>';
		$h .= '<div class="util-annotation">'.s("Importé le {value}", \util\DateUi::numeric($eDecorUpload['createdAt'], \util\DateUi::DATE_HOUR_MINUTE)).'</div>';

		return $h;

	}

	/**
	 * L'aperçu tourne sur lui-même (`MapDecorUploadViewer`), lu par sa route et jamais par l'adresse du stockage. Un modèle
	 * retiré n'a plus de fichier : sa vignette reste vide, la marque « Retiré » dit pourquoi. `$place` distingue le
	 * conteneur de la liste de celui du panneau de retrait, ouvert par-dessus la liste du même modèle.
	 */
	private function getPreview(DecorUpload $eDecorUpload, string $place): string {

		if($eDecorUpload['status'] === DecorUpload::WITHDRAWN) {
			return '<div class="util-preview map-admin-upload-preview util-preview-erased"></div>';
		}

		$h = '<div class="util-preview map-admin-upload-preview" data-url="'.self::url($eDecorUpload).'" data-index="'.encode(json_encode($eDecorUpload['bakedIndex'], JSON_UNESCAPED_UNICODE)).'" onrender="MapDecorUploadViewer.show(this)">';
			$h .= new \scene\SceneUi()->getContainer('map-decor-upload-'.$place.'-'.$eDecorUpload['id'], []);
			$h .= '<canvas></canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * « Sélectionner » envoie le remerciement : la confirmation le dit, ou dit qu'il ne partira pas faute d'adresse.
	 */
	private function getChoose(DecorUpload $eDecorUpload, string $class): string {

		$name = $eDecorUpload['createdBy']->getName();

		$confirm = ($eDecorUpload['createdBy']['email'] !== NULL) ?
			s("Sélectionner ce modèle et envoyer un remerciement à {value} ?", $name) :
			s("Sélectionner ce modèle ? {value} n'a plus d'adresse e-mail et ne recevra pas de remerciement.", $name);

		return '<a data-ajax="/map/admin/decorUpload:doChoose" post-id="'.$eDecorUpload['id'].'" class="'.$class.'" data-confirm="'.$confirm.'">'.s("Sélectionner").'</a>';

	}

	private function getExaminedActions(DecorUpload $eDecorUpload): string {

		if($eDecorUpload['status'] !== DecorUpload::KEPT) {
			return '';
		}

		$h = '';

		if($eDecorUpload['sharedAt'] !== NULL and $eDecorUpload['chosenAt'] === NULL) {
			$h .= $this->getChoose($eDecorUpload, 'dropdown-item');
		}

		$h .= '<a href="/map/admin/decorUpload:withdraw?id='.$eDecorUpload['id'].'" class="dropdown-item">'.s("Retirer le modèle").'</a>';

		return '<div>'.\main\DesignUi::getGear($h).'</div>';

	}

	/**
	 * Une seule marque : « Sélectionné » remplace « Gardé », qu'il vaut, puis qui et quand.
	 */
	private function getState(DecorUpload $eDecorUpload): string {

		if($eDecorUpload['chosenAt'] !== NULL and $eDecorUpload['status'] === DecorUpload::KEPT) {

			$h = '<span class="badge badge-success badge-solid">'.s("Sélectionné").'</span>';
			$h .= '<div class="util-annotation">'.s("Par {user} le {date}", [
				'user' => $eDecorUpload['chosenBy']->getName(),
				'date' => \util\DateUi::numeric($eDecorUpload['chosenAt'], \util\DateUi::DATE)
			]).'</div>';

			return $h;

		}

		$h = match($eDecorUpload['status']) {
			DecorUpload::KEPT => '<span class="badge badge-success">'.s("Gardé").'</span>',
			DecorUpload::WITHDRAWN => '<span class="badge badge-danger">'.s("Retiré").'</span>'
		};

		$h .= '<div class="util-annotation">'.s("Par {user} le {date}", [
			'user' => $eDecorUpload['moderatedBy']->getName(),
			'date' => \util\DateUi::numeric($eDecorUpload['moderatedAt'], \util\DateUi::DATE)
		]).'</div>';

		if($eDecorUpload['status'] === DecorUpload::WITHDRAWN) {
			$h .= '<div>'.self::p('reason')->values[$eDecorUpload['reason']].'</div>';
		}

		return $h;

	}

	public function withdraw(DecorUpload $eDecorUpload): \Panel {

		\Asset::css('map', 'decorUpload.css');
		\Asset::js('map', 'decorUpload.js');

		$form = new \util\FormUi();

		$author = $eDecorUpload['createdBy']->getName();
		$name = self::getName($eDecorUpload);
		$hasEmail = ($eDecorUpload['createdBy']['email'] !== NULL);

		$h = $form->openAjax('/map/admin/decorUpload:doWithdraw', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $eDecorUpload);

			$h .= $form->group(s("Modèle"), $this->getPreview($eDecorUpload, 'withdraw'));

			$h .= $form->dynamicGroup($eDecorUpload, 'reason');

			$h .= $form->dynamicGroup($eDecorUpload, 'reasonText', function(\PropertyDescriber $d) use($author, $hasEmail) {
				$d->labelAfter = \util\FormUi::info($hasEmail ? s("Envoyée à {value} dans l'e-mail.", $author) : s("{value} n'a plus d'adresse e-mail et ne la recevra pas.", $author));
			});

			$consequences = [
				['icon' => 'trash', 'text' => s("<b>Le modèle</b> sera effacé.")],
				$eDecorUpload['building']->notEmpty() ?
					['icon' => 'house', 'text' => s("<b>{value}</b> reprendra sa forme par défaut sur son plan.", $name)] :
					['icon' => 'box', 'text' => s("<b>{value}</b> deviendra une boite grise sur son plan.", $name)]
			];

			if($eDecorUpload['chosenAt'] !== NULL) {
				$consequences[] = ['icon' => 'x-lg', 'text' => s("<b>Le modèle</b> ne sera pas proposé aux autres utilisateurs.")];
			}

			if($hasEmail) {
				$consequences[] = ['icon' => 'envelope', 'text' => s("<b>{value}</b> recevra le motif par e-mail.", $author)];
			}

			$h .= \main\DesignUi::getWeigh('danger', 'house-x', NULL, NULL, $consequences, [s("{value} peut importer un autre modèle.", $author)], FALSE);

			$h .= $form->group(
				content: $form->submit(s("Retirer le modèle"), ['class' => 'btn btn-danger'])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-map-decor-upload-withdraw',
			title: s("Retirer le modèle 3D"),
			subTitle: s("{name} · {structure} · importé le {date}", [
				'name' => $name,
				'structure' => encode($eDecorUpload['structure']['name']),
				'date' => \util\DateUi::numeric($eDecorUpload['createdAt'], \util\DateUi::DATE)
			]),
			body: $h
		);

	}

	/**
	 * À l'auteur, quand l'équipe retire son modèle : le motif, la précision, et ce qui lui reste possible. L'invitation à
	 * répondre ne vient qu'avec une adresse de contestation, qui reçoit la réponse ; le pied ordinaire dit alors le contraire
	 * (« une adresse qui ne reçoit pas de réponse ») et n'est pas posé.
	 */
	public function getWithdrawnMail(DecorUpload $eDecorUpload, ?string $contest): array {

		$values = [
			'name' => self::getName($eDecorUpload),
			'date' => \util\DateUi::textual($eDecorUpload['createdAt'], \util\DateUi::DATE)
		];

		if($eDecorUpload['building']->notEmpty()) {
			$heroText = s("L'équipe de Ouvretaferme a retiré le modèle 3D « {name} » que vous avez importé le {date}. Le fichier est effacé et le bâtiment reprend sa forme par défaut. Vous pouvez importer un autre modèle.", $values);
		} else {
			$heroText = s("L'équipe de Ouvretaferme a retiré le modèle 3D « {name} » que vous avez importé le {date}. Le fichier est effacé et l'élément devient une boite grise sur votre plan. Vous pouvez importer un autre modèle.", $values);
		}

		if($contest !== NULL) {
			$heroText .= ' '.s("Si vous pensez qu'il s'agit d'une erreur, répondez à cet e-mail.");
		}

		return \main\MailSoftwareAiUi::format(
			s("Votre modèle 3D a été retiré"),
			heroTitle: s("Votre modèle 3D a été retiré"),
			heroText: $heroText,
			facts: [s("Motif") => self::p('reason')->values[$eDecorUpload['reason']]],
			quote: nl2br(encode($eDecorUpload['reasonText'])),
			footer: ($contest === NULL)
		);

	}

	/**
	 * À l'auteur, quand l'équipe sélectionne son modèle : un remerciement, sans bouton, il n'y a rien à faire après.
	 */
	public function getChosenMail(DecorUpload $eDecorUpload): array {

		return \main\MailSoftwareAiUi::format(
			s("Merci pour votre modèle 3D"),
			heroTitle: s("Merci pour votre modèle 3D"),
			heroText: s("L'équipe de Ouvretaferme a sélectionné le modèle 3D « {name} » que vous avez importé le {date}. Comme vous l'avez accepté, nous le proposerons aux autres utilisateurs pour décorer leur plan. Merci pour ce partage !", [
				'name' => self::getName($eDecorUpload),
				'date' => \util\DateUi::textual($eDecorUpload['createdAt'], \util\DateUi::DATE)
			])
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = DecorUpload::model()->describer($property, [
			'reason' => s("Motif"),
			'reasonText' => s("Précision"),
		]);

		switch($property) {

			case 'reason' :
				$d->field = 'radio';
				$d->values = [
					DecorUpload::PERSON => s("Il représente une personne réelle"),
					DecorUpload::BRAND => s("Il reprend une marque ou un personnage soumis à des droits"),
					DecorUpload::OFFENSIVE => s("Il est choquant ou haineux"),
					DecorUpload::OTHER => s("Autre motif")
				];
				$d->attributes = [
					'required' => TRUE
				];
				break;

			case 'reasonText' :
				$d->field = 'textarea';
				$d->attributes = [
					'data-limit' => DecorUpload::model()->getPropertyRange('reasonText')[1],
					'rows' => 4
				];
				break;

		}

		return $d;

	}

}
?>
