<?php
namespace user;

class UserAvatarUpload extends UserAvatarUploadElement {

	public function canRead(): bool {

		$this->expects(['user']);
		return $this['user']->is(ConnectionLib::getOnline());

	}

	/**
	 * Hors de son auteur, le modèle se lit dans une structure nommée par la requête, jamais devinée : la personne connectée
	 * en est membre, la scène et le drapeau y sont ouverts (`UserAvatarUploadLib::isOpenByStructure()`), et l'auteur y
	 * paraît (`UserAvatarUploadLib::getAuthorsByStructure()`). Les scènes qui posent l'adresse du modèle lisent les mêmes
	 * méthodes, et une adresse posée par la page ne se refuse donc pas à la lecture.
	 */
	public function canReadByStructure(\structure\Structure $eStructure): bool {

		$this->expects(['user']);

		return (
			$eStructure->notEmpty() and
			$eStructure->isMember() and
			UserAvatarUploadLib::isOpenByStructure($eStructure) and
			UserAvatarUploadLib::getAuthorsByStructure($eStructure, new \Collection([$this['user']['id'] => $this['user']]))->notEmpty()
		);

	}

	/**
	 * Le droit de l'équipe de Ouvretaferme, qui voit chaque modèle téléversé et le garde ou le retire.
	 */
	public function canModerate(): bool {
		return ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Un envoi se montre tant qu'il garde son fichier et qu'aucun admin ne l'a retiré.
	 */
	public function acceptRead(): bool {

		$this->expects(['status', 'hash']);

		return (
			in_array($this['status'], [UserAvatarUpload::NEW, UserAvatarUpload::KEPT], TRUE) and
			$this['hash'] !== NULL
		);

	}

	/**
	 * Un modèle se retire tant qu'il se montre, gardé compris. Un envoi remplacé ou abandonné par son auteur n'a plus de
	 * fichier ni d'image à retirer, et l'e-mail annoncerait le retrait d'un modèle que personne ne voit.
	 */
	public function acceptWithdraw(): bool {
		return $this->acceptRead();
	}

	public function assertWithdraw(): bool {
		return $this->acceptWithdraw() and $this->canModerate();
	}

	/**
	 * Un modèle se téléverse à la place d'un avatar composé, qui reste là pour y revenir et fait l'image de profil.
	 */
	public function acceptCreate(): bool {

		$this->expects(['user' => ['eUserAvatar']]);
		return $this['user']['eUserAvatar']->exists();

	}

	/**
	 * Le fichier et l'engagement sont deux propriétés hors schéma : seul le résultat de la conversion s'écrit. `file` est le
	 * chemin du fichier reçu, que la page ne pose que pour un envoi complet et passé par PHP (`is_uploaded_file()`).
	 *
	 * Le fichier se juge du moins cher au plus cher : sa forme et son poids lus par PHP, puis le débit, compté avant node et
	 * refus compris, puis la conversion par node (`\scene\UploadLib::run()`). Un refus de forme, de poids ou de conversion
	 * porte le même nom, `file.conformity`, et son code dit lequel : node juge aussi le poids et l'en-tête, PHP ne fait que
	 * lui épargner les fichiers qui ne passeraient pas.
	 */
	public static function properties(\PropertyList $l): void {

		$l->property('file')
			->replaceCheck(function(mixed $file): bool {

				if(is_string($file) === FALSE or is_file($file) === FALSE) {
					return FALSE;
				}

				if(filesize($file) > \scene\UploadLib::getRules('avatar')['maxBytes']) {
					throw self::getRefusal('weight', ['size' => filesize($file)]);
				}

				if(file_get_contents($file, length: 4) !== 'glTF') {
					throw self::getRefusal('format');
				}

				return TRUE;

			})
			// Le débit refuse l'envoi, pas le fichier : il tombe à côté du champ, sur `throttled`, pour que l'écran garde le
			// fichier tel quel au lieu de le dire refusé et d'inviter à un autre envoi qui échouerait de même
			->addAfterCheck('throttled', function(string $file): bool {

				$this->expects(['user']);

				if(ThrottleAiLib::acceptAvatarUpload($this['user']) === FALSE) {
					throw new \FailException('user\UserAvatarUpload::file.throttled', wrapper: 'throttled');
				}

				return TRUE;

			})
			->addAfterCheck('conformity', function(string $file): bool {

				// Un node qui ne démarre pas est un défaut du serveur : il part au journal d'erreurs, et l'import se dit indisponible à
				// côté du champ, sur `throttled` comme le débit, pour que l'écran garde le fichier au lieu de le dire refusé
				try {
					$result = \scene\UploadLib::run('avatar', $file);
				} catch(\scene\UploadUnavailableException $exception) {
					\dev\ErrorPhpLib::handleException($exception);
					throw new \FailException('user\UserAvatarUpload::file.unavailable', wrapper: 'throttled');
				}

				if(array_key_exists('refusal', $result)) {
					throw self::getRefusal($result['refusal'], $result['values']);
				}

				$this['rules'] = $result['rules'];
				$this['bakedIndex'] = $result['index'];
				$this['bakedBuffer'] = $result['buffer'];

				return TRUE;

			});

		$l->property('commitment')
			->replaceCheck(fn(mixed $commitment): bool => ($commitment === '1'));

		// Le motif et sa précision partent à l'auteur : un retrait se motive toujours, et les deux colonnes ne sont nullables
		// que pour les envois qu'aucun admin n'a retirés. `addAfterCheck` et non `replaceCheck` : le schéma reste devant pour
		// la liste des motifs et la longueur de la précision
		foreach(['reason', 'reasonText'] as $property) {
			$l->property($property)
				->addAfterCheck('empty', fn(?string $value): bool => $value !== NULL);
		}

	}

	/**
	 * Un refus de forme, de poids ou de conversion, quel que soit l'endroit qui le lève : la closure du message reçoit ces
	 * clés en paramètres nommés (`Fail.php:450`), qui sont donc les mêmes partout. Un poids cite son plafond, lu dans les
	 * règles et jamais recopié.
	 */
	public static function getRefusal(string $refusal, array $values = []): \FailException {

		$rules = \scene\UploadLib::getRules('avatar');

		if($refusal === 'weight') {
			$values['max'] = $rules['maxBytes'];
		}

		return new \FailException('user\UserAvatarUpload::file.conformity', [
			'refusal' => $refusal,
			'values' => $values
		]);

	}

}
?>
