<?php
namespace map;

class DecorUpload extends DecorUploadElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
		];

	}

	/**
	 * Le modèle se montre à la place de la composition à tous ceux qui voient le plan de la structure, sous le droit de la
	 * page du plan (`production/map/page/scene.p.php`, `MapLib::load()`), et seuls ceux qui la gèrent l'envoient ou le
	 * retirent, comme le bâtiment qu'il remplace s'écrit (`DecorBuilding::canWrite()`).
	 */
	public function canRead(): bool {

		$this->expects(['structure']);

		return (
			$this['structure']->canRead() and
			$this['structure']->canProduction()
		);

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Un envoi se montre tant qu'il garde son fichier et qu'aucun admin ne l'a retiré.
	 */
	public function acceptRead(): bool {

		$this->expects(['status', 'hash']);

		return (
			in_array($this['status'], [DecorUpload::NEW, DecorUpload::KEPT], TRUE) and
			$this['hash'] !== NULL
		);

	}

	/**
	 * Le droit de l'équipe de Ouvretaferme, qui voit chaque modèle importé et le garde, le retire ou le sélectionne. Il ne
	 * passe pas par la structure : l'équipe n'en est pas membre.
	 */
	public function canModerate(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Un modèle se retire tant qu'il se montre, gardé ou sélectionné compris : une marque découverte après la sélection doit
	 * pouvoir sortir. Un envoi qui n'a plus de fichier n'a plus rien à retirer, et l'e-mail annoncerait le retrait d'un
	 * modèle que personne ne voit.
	 */
	public function acceptWithdraw(): bool {
		return $this->acceptRead();
	}

	public function assertWithdraw(): bool {
		return $this->canModerate() and $this->acceptWithdraw();
	}

	/**
	 * Un modèle se sélectionne une fois, si son auteur a accepté le partage et tant qu'il se montre. Entre deux clics ou deux
	 * admins, c'est la condition de l'écriture qui tranche, et avec elle le remerciement (`DecorUploadLib::choose()`).
	 */
	public function acceptChoose(): bool {

		$this->expects(['sharedAt', 'chosenAt']);

		return (
			$this['sharedAt'] !== NULL and
			$this['chosenAt'] === NULL and
			$this->acceptRead()
		);

	}

	public function assertChoose(): bool {
		return $this->canModerate() and $this->acceptChoose();
	}

	/**
	 * L'écriture ne suit pas la lecture (`acceptWrite()`, `framework/core/Data.php`) : un envoi retiré se modère encore, sur
	 * une ligne qui ne se montre plus.
	 */
	public function acceptWrite(): bool {
		return TRUE;
	}

	/**
	 * Le fichier et l'engagement sont deux propriétés hors schéma, comme pour l'avatar (`user\UserAvatarUpload`) : seul le
	 * résultat de la conversion s'écrit. `file` est le chemin du fichier reçu, que la page ne pose que pour un envoi complet
	 * et passé par PHP (`is_uploaded_file()`).
	 *
	 * Le fichier se juge du moins cher au plus cher : sa forme et son poids lus par PHP, puis le débit, compté avant node et
	 * refus compris, à l'aperçu comme à l'ajout (`decorUpload:doPreview`, `doCreate`, et leurs jumelles de `decorObject`), puis la conversion par node (`\scene\UploadLib::run()`) sous les règles de sa famille (`getFamily()`). Un refus de
	 * forme, de poids ou de conversion porte le même nom, `file.conformity`, et son code dit lequel.
	 */
	public static function properties(\PropertyList $l): void {

		$l->property('file')
			->replaceCheck(function(mixed $file): bool {

				if(is_string($file) === FALSE or is_file($file) === FALSE) {
					return FALSE;
				}

				if(filesize($file) > \scene\UploadLib::getRules(DecorUploadLib::PROFILE)['maxBytes']) {
					throw self::getRefusal('weight', ['size' => filesize($file)], $this->getFamily());
				}

				if(file_get_contents($file, length: 4) !== 'glTF') {
					throw self::getRefusal('format', [], $this->getFamily());
				}

				return TRUE;

			})
			// Le débit refuse l'envoi, pas le fichier : il tombe à côté du champ, sur `throttled`, pour que l'écran garde le
			// fichier tel quel au lieu de le dire refusé et d'inviter à un autre envoi qui échouerait de même. Il se compte
			// par utilisateur, sur la valeur que rend l'incrément, pour que deux envois simultanés ne passent pas sur la même place
			->addAfterCheck('throttled', function(string $file): bool {

				$count = \user\ThrottleAiLib::increment('decor-upload', (string)\user\ConnectionLib::getOnline()['id'], MapSetting::DECOR_UPLOAD_WINDOW);

				if($count > MapSetting::DECOR_UPLOAD_LIMIT) {
					throw new \FailException('map\DecorUpload::file.throttled', wrapper: 'throttled');
				}

				return TRUE;

			})
			->addAfterCheck('conformity', function(string $file): bool {

				// Un node qui ne démarre pas est un défaut du serveur : il part au journal d'erreurs, et l'import se dit indisponible à
				// côté du champ, sur `throttled` comme le débit, pour que l'écran garde le fichier au lieu de le dire refusé
				try {
					$result = \scene\UploadLib::run(DecorUploadLib::PROFILE, $file, $this->getFamily());
				} catch(\scene\UploadUnavailableException $exception) {
					\dev\ErrorPhpLib::handleException($exception);
					throw new \FailException('map\DecorUpload::file.unavailable', wrapper: 'throttled');
				}

				if(array_key_exists('refusal', $result)) {
					throw self::getRefusal($result['refusal'], $result['values'], $this->getFamily());
				}

				$this['rules'] = $result['rules'];
				$this['bakedIndex'] = $result['index'];
				$this['bakedBuffer'] = $result['buffer'];

				return TRUE;

			});

		$l->property('commitment')
			->replaceCheck(fn(mixed $commitment): bool => ($commitment === '1'));

		// L'accord de partage n'est la colonne d'aucune ligne : la case vaut une date, que le Lib pose à l'écriture
		// (`sharedAt`). Facultative, elle ne refuse rien : non cochée, elle n'est pas postée
		$l->property('shared')
			->replaceCast(function(mixed &$value): bool {
				$value = ($value === '1');
				return TRUE;
			})
			->replaceSet(function(bool $value): void {
				$this['shared'] = $value;
			});

		// Le motif et sa précision partent à l'auteur : un retrait se motive toujours, et les deux colonnes ne sont nullables
		// que pour les envois qu'aucun admin n'a retirés. `addAfterCheck` et non `replaceCheck` : le schéma reste devant pour
		// la liste des motifs et la longueur de la précision
		foreach(['reason', 'reasonText'] as $property) {
			$l->property($property)
				->addAfterCheck('empty', fn(?string $value): bool => $value !== NULL);
		}

	}

	/**
	 * La famille des règles de conversion (`base/scene/upload/decor.json`) : celle d'un bâtiment, ou la rangée de l'élément, dont
	 * les valeurs de l'enum sont les noms des familles.
	 */
	public function getFamily(): string {

		$this->expects(['building']);

		if($this['building']->notEmpty()) {
			return DecorUploadLib::FAMILY_BUILDING;
		}

		$this['object']->expects(['family']);

		return $this['object']['family'];

	}

	/**
	 * Un refus de forme, de poids ou de conversion, quel que soit l'endroit qui le lève : la closure du message reçoit ces
	 * clés en paramètres nommés (`Fail.php:450`), qui sont donc les mêmes partout. Un poids cite son plafond, lu dans les
	 * règles et jamais recopié. La famille dit ce que le fichier devait devenir, un bâtiment ou un élément d'une rangée.
	 */
	public static function getRefusal(string $refusal, array $values, string $family): \FailException {

		$rules = \scene\UploadLib::getRules(DecorUploadLib::PROFILE);

		if($refusal === 'weight') {
			$values['max'] = $rules['maxBytes'];
		}

		return new \FailException('map\DecorUpload::file.conformity', [
			'refusal' => $refusal,
			'values' => $values,
			'family' => $family
		]);

	}

}
?>
