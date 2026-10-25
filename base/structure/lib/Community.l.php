<?php
namespace structure;

class CommunityLib extends CommunityCrud {

	public static function createForStructure(\structure\Structure $eStructure): void {

		if($eStructure->isCommunity() === FALSE) {
			return;
		}

		$e = new Community([
			'structure' => $eStructure,
		]);

		$e['fqn'] = self::getFreeFqn($eStructure);

		parent::create($e);

	}

	public static function update(Community $e, array $properties): void {

		Community::model()->beginTransaction();

		try {

			if(in_array('fqn', $properties)) {

				$e->expects(['structure', 'oldFqn']);

				if($e['fqn'] !== $e['oldFqn']) {

					$cCommunityRedirect = CommunityRedirect::model()
						->select(['id'])
						->whereFqn($e['fqn'])
						->whereStructure($e['structure'])
						->getCollection();

					foreach($cCommunityRedirect as $eCommunityRedirect) {
						CommunityRedirectLib::delete($eCommunityRedirect);
					}

					CommunityRedirectLib::create(new CommunityRedirect([
						'fqn' => $e['oldFqn'],
						'structure' => $e['structure'],
					]));

				}

			}

			parent::update($e, $properties);

			Community::model()->commit();

		} catch(\DuplicateException) {

			Community::model()->rollBack();

			Community::fail('fqn.duplicate');

		}

	}

	public static function getPropertiesAdministrative(\structure\Structure $eStructure): array {

		if($eStructure->acceptGroupQuotationSetting()) {
			return ['fqn'];
		} else {
			return [];
		}

	}

	public static function getFreeFqn(\structure\Structure $eStructure): string {

		$eStructure->expects(['id', 'name']);

		// toFqn() translittère après avoir abaissé la casse, et « ® » y devient « R ».
		$base = rtrim(substr(strtolower(toFqn($eStructure['name'])), 0, \Filter::MAX_FQN_SIZE), '-');

		if(
			$base !== '' and
			in_array($base, RESERVED_FQN) === FALSE and
			self::isFqnTaken($base, $eStructure) === FALSE
		) {
			return $base;
		}

		$position = 1;

		do {

			$suffix = '-'.$eStructure['id'].($position > 1 ? '-'.$position : '');

			if($base === '') {
				$fqn = 'groupe'.$suffix;
			} else {
				$fqn = rtrim(substr($base, 0, \Filter::MAX_FQN_SIZE - strlen($suffix)), '-').$suffix;
			}

			$position++;

		} while(self::isFqnTaken($fqn, $eStructure));

		return $fqn;

	}

	/**
	 * Une adresse est prise quand un autre groupe la porte, ou l'a portée : une redirection ne se reprend pas.
	 */
	public static function isFqnTaken(string $fqn, \structure\Structure $eStructure): bool {

		return (
			Community::model()
				->whereFqn($fqn)
				->whereStructure('!=', $eStructure)
				->exists() or
			CommunityRedirect::model()
				->whereFqn($fqn)
				->whereStructure('!=', $eStructure)
				->exists()
		);

	}

	/**
	 * Le groupe dont l'adresse actuelle ou ancienne vaut `$fqn`. L'appelant compare l'adresse du groupe à ce
	 * qu'il a lu pour savoir s'il doit rediriger.
	 */
	public static function getByAddress(string $fqn): Community {

		// Refusée avant la requête : une chaîne qui n'est pas une adresse ne peut pas être en base, et une
		// chaîne non ASCII comparée à la colonne ascii_bin lève.
		if(isFqn($fqn) === FALSE) {
			return new Community();
		}

		$eCommunity = Community::model()
			->select(Community::getSelection())
			->whereFqn($fqn)
			->get();

		if($eCommunity->notEmpty()) {
			return $eCommunity;
		}

		$eCommunityRedirect = CommunityRedirect::model()
			->select(['structure'])
			->whereFqn($fqn)
			->get();

		if($eCommunityRedirect->empty()) {
			return new Community();
		}

		return self::getByStructure($eCommunityRedirect['structure']);

	}

	/**
	 * Pose le `Community` de chaque groupe d'une collection, en une requête.
	 *
	 * `Structure::community()` interroge la base groupe par groupe et ne consulte son cache
	 * qu'après avoir constaté l'absence de la propriété : la poser ici la rend gratuite pour
	 * tous les appelants suivants.
	 */
	public static function prepareByStructures(\Collection $cStructure): \Collection {

		if($cStructure->empty()) {
			return $cStructure;
		}

		$cCommunity = Community::model()
			->select(Community::getSelection())
			->whereStructure('IN', $cStructure)
			->getCollection(index: 'structure');

		foreach($cStructure as $eStructure) {
			$eStructure['community'] = $cCommunity[$eStructure['id']] ?? new Community();
		}

		return $cStructure;

	}

	/**
	 * Le compteur de sous-groupes, tenu à la création et à la suppression.
	 *
	 * ⚠️ `sections + 1` en SQL et jamais une relecture suivie d'une écriture : l'opération est
	 * atomique sur une ligne qu'on ne détient pas, et deux créations simultanées perdraient sinon
	 * l'une des deux. Même forme que `product\ProductLib` pour son compteur de variantes.
	 */
	public static function incrementSections(\structure\Structure $eStructure, int $increment): void {

		Community::model()
			->whereStructure($eStructure)
			->update(['sections' => new \Sql('sections + '.$increment)]);

	}

	public static function getByStructure(\structure\Structure $eStructure): Community {

		return Community::model()
			->select(Community::getSelection())
			->whereStructure($eStructure)
			->get();

	}

}
