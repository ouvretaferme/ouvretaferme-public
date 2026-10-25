<?php
namespace structure;

/**
 * L'acceptation des conditions générales de vente de Ouvretaferme par une structure, une ligne par version.
 *
 * Les CGV sont celles de `main/document/cgv.pdf`, les mêmes pour l'abonnement et pour le paiement par carte sur le
 * compte de reversement : le premier des deux gestes enregistre l'acceptation, l'autre ne la redemande pas. Elle est
 * cochée par un administrateur et engage la structure, y compris après son départ.
 *
 * Les versions et leur date d'entrée en vigueur sont dans `StructureSetting::$terms`, comme celles des CGU dans
 * `UserSetting::$tos`. La version acceptée est celle que sert `cgv.pdf`, la dernière déclarée. Ce qu'impose une
 * version nouvelle à une structure qui a accepté la précédente n'est pas décidé : `hasAccepted()` lit toutes les
 * versions (09/10/2026, « on avisera »).
 *
 * Les lignes sont une preuve : elles ne se modifient ni ne se suppriment.
 */
class TermsAiLib extends TermsCrud {

	public static function getLatestVersion(): int {

		return array_key_last(StructureSetting::$terms);

	}

	public static function hasAccepted(Structure $eStructure): bool {

		return Terms::model()
			->whereStructure($eStructure)
			->exists();

	}

	public static function getLast(Structure $eStructure): Terms {

		return Terms::model()
			->select(Terms::getSelection() + [
				'user' => ['firstName', 'lastName'],
			])
			->whereStructure($eStructure)
			->sort(['version' => SORT_DESC])
			->get();

	}

	/**
	 * Lit la case postée sous `terms`, refusée en `Terms::terms.check` si elle n'est pas cochée, puis enregistre
	 * l'acceptation de la dernière version. L'élément inséré ne porte que ses colonnes : la case n'en est pas une.
	 */
	public static function acceptFromInput(Structure $eStructure, array $input): void {

		$fw = new \FailWatch();

		new Terms()->build(['terms'], $input);

		$fw->validate();

		self::accept($eStructure);

	}

	public static function accept(Structure $eStructure): void {

		$eStructure->expects(['id']);

		$eTerms = new Terms([
			'structure' => $eStructure,
			'version' => self::getLatestVersion(),
		]);

		// Deux onglets ou un double clic acceptent la même version : le second n'a rien à ajouter
		try {
			self::create($eTerms);
		} catch(\DuplicateException) {
		}

	}

}
?>
