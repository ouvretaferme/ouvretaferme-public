<?php
namespace user;

class UserAvatarUploadUi {

	public function __construct() {

		\Asset::css('user', 'admin.css');
		\Asset::js('user', 'avatarUpload.js');

	}

	/**
	 * Le binaire du modèle se lit par cette route, après contrôle des droits (`avatarUpload.p.php`), jamais par
	 * l'adresse du stockage, qui le servirait à qui connait son hash. Une scène nomme la structure où elle le montre, qui
	 * ouvre la lecture aux autres membres (`UserAvatarUpload::canReadByStructure()`) ; l'éditeur de l'auteur n'en nomme pas.
	 */
	public static function url(UserAvatarUpload $e, \structure\Structure $eStructure = new \structure\Structure()): string {

		$url = '/user/avatar/modele/'.$e['id'];

		if($eStructure->notEmpty()) {
			$url .= '?structure='.$eStructure['id'];
		}

		return $url;

	}

	/**
	 * L'onglet de l'équipe : les modèles à examiner, déjà montrés dans les scènes, puis ceux qui ont été examinés, qu'une
	 * roue retire encore quand ils ont été gardés. Un retrait se fait sur la carte du modèle, et « Tout valider » garde
	 * ensuite le reste de la file affichée, dont chaque carte poste l'id : un envoi arrivé depuis attend d'avoir été vu.
	 *
	 * Le hash ne quitte jamais le Lib : il ouvre le fichier à qui le connaît, par l'adresse publique du stockage. La liste ne
	 * peut donc pas juger l'état d'un envoi, qui se lit sur ce hash (`acceptRead()`), et choisit ses gestes d'après le statut ;
	 * « Tout valider » porte l'état dans la condition de chaque écriture, le retrait rejuge `acceptWithdraw()` sous verrou.
	 */
	public function getAdmin(\Collection $cUserAvatarUploadWaiting, int $nUserAvatarUploadWaiting, \Collection $cUserAvatarUploadExamined): string {

		$h = '<div class="util-title">';
			$h .= '<h3>'.s("À examiner").self::getCounter($nUserAvatarUploadWaiting).'</h3>';
		$h .= '</div>';

		if($cUserAvatarUploadWaiting->empty()) {
			$h .= '<div class="util-block-empty mb-2"><p>'.s("Aucun modèle à examiner.").'</p></div>';
		} else {

			$form = new \util\FormUi();

			$h .= $form->openAjax('/user/admin/avatarUpload:doKeepAll', ['class' => 'mb-2']);

				$h .= '<div class="util-cards util-cards-small">';

					foreach($cUserAvatarUploadWaiting as $eUserAvatarUpload) {

						$h .= '<div class="util-card">';
							$h .= $form->hidden('ids[]', $eUserAvatarUpload);
							$h .= $this->getPreview($eUserAvatarUpload, 'list');
							$h .= '<div class="mt-1">'.$this->getAuthor($eUserAvatarUpload).'</div>';
							$h .= '<div class="util-card-foot">';
								$h .= '<a href="/user/admin/avatarUpload:withdraw?id='.$eUserAvatarUpload['id'].'" class="btn btn-outline-primary btn-sm">'.s("Retirer").'</a>';
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

		if($cUserAvatarUploadExamined->empty()) {
			$h .= '<div class="util-block-empty"><p>'.s("Aucun modèle examiné.").'</p></div>';
			return $h;
		}

		$h .= '<div class="util-table util-table-hover user-admin-uploads">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Modèle").'</div>';
				$h .= '<div>'.s("Auteur").'</div>';
				$h .= '<div class="hide-xs-down">'.s("État").'</div>';
			$h .= '</div>';

			foreach($cUserAvatarUploadExamined as $eUserAvatarUpload) {

				$state = '<div>'.$this->getState($eUserAvatarUpload).'</div>'.$this->getExaminedActions($eUserAvatarUpload);

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$this->getPreview($eUserAvatarUpload, 'list').'</div>';
					$h .= '<div>';
						$h .= $this->getAuthor($eUserAvatarUpload);
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
	 * Le nom mène à la fiche du compte et non à la recherche des utilisateurs (`/user/admin/?id=`), qui ne trouve que les
	 * comptes publics. La date d'import se lit dessous, au même format dans les deux tables.
	 */
	private function getAuthor(UserAvatarUpload $eUserAvatarUpload): string {

		$h = '<a href="/user/admin/:update?id='.$eUserAvatarUpload['user']['id'].'"><b>'.$eUserAvatarUpload['user']->getName().'</b></a>';
		$h .= '<div class="util-annotation">'.s("Importé le {value}", \util\DateUi::numeric($eUserAvatarUpload['createdAt'], \util\DateUi::DATE_HOUR_MINUTE)).'</div>';

		return $h;

	}

	/**
	 * L'aperçu tourne et enchaîne les poses du modèle (`UserAvatarUploadViewer`), lu par sa route et jamais par l'adresse du
	 * stockage. Un modèle retiré n'a plus de fichier : sa vignette reste vide, la marque « Retiré » dit pourquoi. `$place`
	 * distingue le conteneur de la liste de celui du panneau de retrait, ouvert par-dessus la liste du même modèle.
	 */
	private function getPreview(UserAvatarUpload $eUserAvatarUpload, string $place): string {

		if($eUserAvatarUpload['status'] === UserAvatarUpload::WITHDRAWN) {
			return '<div class="util-preview util-preview-photo util-preview-erased"></div>';
		}

		$h = '<div class="util-preview util-preview-photo" data-url="'.self::url($eUserAvatarUpload).'" data-index="'.encode(json_encode($eUserAvatarUpload['bakedIndex'], JSON_UNESCAPED_UNICODE)).'" onrender="UserAvatarUploadViewer.show(this)">';
			$h .= new \scene\SceneUi()->getContainer('user-avatar-upload-'.$place.'-'.$eUserAvatarUpload['id'], []);
			$h .= '<canvas></canvas>';
		$h .= '</div>';

		return $h;

	}

	private function getExaminedActions(UserAvatarUpload $eUserAvatarUpload): string {

		if($eUserAvatarUpload['status'] !== UserAvatarUpload::KEPT) {
			return '';
		}

		$h = '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<a href="/user/admin/avatarUpload:withdraw?id='.$eUserAvatarUpload['id'].'" class="dropdown-item">'.s("Retirer le modèle").'</a>';
		$h .= '</div>';

		return $h;

	}

	private function getState(UserAvatarUpload $eUserAvatarUpload): string {

		$h = match($eUserAvatarUpload['status']) {
			UserAvatarUpload::KEPT => '<span class="badge badge-success">'.s("Gardé").'</span>',
			UserAvatarUpload::WITHDRAWN => '<span class="badge badge-danger">'.s("Retiré").'</span>'
		};

		$h .= '<div class="util-annotation">'.s("Par {user} le {date}", [
			'user' => $eUserAvatarUpload['moderatedBy']->getName(),
			'date' => \util\DateUi::numeric($eUserAvatarUpload['moderatedAt'], \util\DateUi::DATE)
		]).'</div>';

		if($eUserAvatarUpload['status'] === UserAvatarUpload::WITHDRAWN) {
			$h .= '<div>'.self::p('reason')->values[$eUserAvatarUpload['reason']].'</div>';
		}

		return $h;

	}

	public function withdraw(UserAvatarUpload $eUserAvatarUpload): \Panel {

		$form = new \util\FormUi();

		$name = $eUserAvatarUpload['user']->getName();
		$hasEmail = ($eUserAvatarUpload['user']['email'] !== NULL);

		$h = $form->openAjax('/user/admin/avatarUpload:doWithdraw', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $eUserAvatarUpload);

			$h .= $form->group(s("Modèle"), $this->getPreview($eUserAvatarUpload, 'withdraw'));

			$h .= $form->dynamicGroup($eUserAvatarUpload, 'reason');

			$h .= $form->dynamicGroup($eUserAvatarUpload, 'reasonText', function(\PropertyDescriber $d) use($name, $hasEmail) {
				$d->labelAfter = \util\FormUi::info($hasEmail ? s("Envoyée à {value} dans l'e-mail.", $name) : s("{value} n'a plus d'adresse e-mail et ne la recevra pas.", $name));
			});

			$consequences = [
				['icon' => 'trash', 'text' => s("<b>Le modèle</b> sera effacé.")],
				['icon' => 'person', 'text' => s("<b>{value}</b> retrouvera son avatar composé dans toutes les scènes.", $name)]
			];

			if($hasEmail) {
				$consequences[] = ['icon' => 'envelope', 'text' => s("<b>{value}</b> recevra le motif par e-mail.", $name)];
			}

			$h .= \main\DesignUi::getWeigh('danger', 'person-x', NULL, NULL, $consequences, [s("{value} peut importer un autre modèle.", $name)], FALSE);

			$h .= $form->group(
				content: $form->submit(s("Retirer le modèle"), ['class' => 'btn btn-danger'])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-user-avatar-upload-withdraw',
			title: s("Retirer le modèle 3D"),
			subTitle: s("{user} · importé le {date}", [
				'user' => $name,
				'date' => \util\DateUi::numeric($eUserAvatarUpload['createdAt'], \util\DateUi::DATE)
			]),
			body: $h
		);

	}

	/**
	 * À l'auteur, quand l'équipe retire son modèle : le motif, la précision, et ce qui lui reste possible. L'invitation à
	 * répondre ne vient qu'avec une adresse de contestation, qui reçoit la réponse ; le pied ordinaire dit alors le contraire
	 * (« une adresse qui ne reçoit pas de réponse ») et n'est pas posé.
	 */
	public function getWithdrawnMail(UserAvatarUpload $eUserAvatarUpload, ?string $contest): array {

		$heroText = s("L'équipe de Ouvretaferme a retiré le modèle 3D que vous avez importé le {value} pour votre avatar. Le fichier est effacé et votre avatar composé s'affiche à sa place. Vous pouvez importer un autre modèle.", \util\DateUi::textual($eUserAvatarUpload['createdAt'], \util\DateUi::DATE));

		if($contest !== NULL) {
			$heroText .= ' '.s("Si vous pensez qu'il s'agit d'une erreur, répondez à cet e-mail.");
		}

		return \main\MailSoftwareAiUi::format(
			s("Votre modèle 3D a été retiré"),
			heroTitle: s("Votre modèle 3D a été retiré"),
			heroText: $heroText,
			facts: [s("Motif") => self::p('reason')->values[$eUserAvatarUpload['reason']]],
			quote: nl2br(encode($eUserAvatarUpload['reasonText'])),
			buttonLabel: s("Modifier mon avatar"),
			buttonUrl: \Lime::getUrl().'/user/avatar',
			footer: ($contest === NULL)
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = UserAvatarUpload::model()->describer($property, [
			'reason' => s("Motif"),
			'reasonText' => s("Précision"),
		]);

		switch($property) {

			case 'reason' :
				$d->field = 'radio';
				$d->values = [
					UserAvatarUpload::PERSON => s("Il représente une autre personne"),
					UserAvatarUpload::BRAND => s("Il reprend une marque ou un personnage soumis à des droits"),
					UserAvatarUpload::OFFENSIVE => s("Il est choquant ou haineux"),
					UserAvatarUpload::OTHER => s("Autre motif")
				];
				$d->attributes = [
					'required' => TRUE
				];
				break;

			case 'reasonText' :
				$d->field = 'textarea';
				$d->attributes = [
					'data-limit' => UserAvatarUpload::model()->getPropertyRange('reasonText')[1],
					'rows' => 4
				];
				break;

		}

		return $d;

	}

}
?>
