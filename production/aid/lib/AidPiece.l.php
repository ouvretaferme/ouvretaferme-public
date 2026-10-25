<?php
namespace aid;

class AidPieceLib extends AidPieceCrud {

	// L'aide vient de la page (getCreateElement()), jamais du POST. `piece` précède `name`, dont l'étape
	// le lit (AidPiece::properties()).
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['moment', 'piece', 'name', 'note'];
	}

	// Une ligne posée garde sa pièce courante : en changer, c'est en retirer une et en ajouter une autre.
	// Le nom ne se poste que pour une pièce libre (AidPiece::properties()). `moment` précède `name`, dont
	// l'étape le lit.
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['moment', 'name', 'note', 'url'];
	}

	public static function getSelectionAdmin(): array {
		return ['id', 'aid', 'moment', 'piece' => ['name'], 'name', 'note', 'url'];
	}

	public static function existsByName(Aid $eAid, string $moment, string $name, AidPiece $eAidPieceExclude): bool {

		return AidPiece::model()
			->whereAid($eAid)
			->whereMoment($moment)
			->whereName($name)
			->whereId('!=', $eAidPieceExclude, if: $eAidPieceExclude->exists())
			->exists();

	}

}
?>
