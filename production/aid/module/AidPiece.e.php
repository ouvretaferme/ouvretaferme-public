<?php
namespace aid;

class AidPiece extends AidPieceElement {

	// Lu comme l'aide qu'il décrit, à qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	// Une pièce ne s'ajoute qu'à une aide qui a un dossier à déposer (Aid::acceptPieces()). La règle est
	// celle de l'élément créé, seule que la création interroge, par la page comme par l'API. Le retrait
	// reste ouvert, pour vider la liste d'une aide qui n'en a plus besoin.
	public function acceptCreate(): bool {

		$this->expects(['aid']);

		return $this['aid']->acceptPieces();

	}

	public static function properties(\PropertyList $l): void {

		// Une ligne nomme une pièce courante ou une pièce libre, jamais les deux ni aucune : la fiche n'a
		// sinon rien à dire, ou dit deux noms. L'étape lit `piece`, que la création construit avant `name`
		// (AidPieceLib::getPropertiesCreate()) et qu'une modification ne poste jamais : une ligne posée
		// garde sa pièce courante, et la lit sur l'élément. build() contrôle la forme de l'identifiant
		// d'une pièce courante, pas sa ligne : elle se relit ici.
		$l->property('name')
			->addAfterCheck('exclusive', function(?string $name, \PropertyList $l): bool {

				// Une pièce postée que build() a refusée a déjà son message (`AidPiece::piece.check`).
				$l->skipIfInvalid('piece');

				if($this['piece']->empty()) {
					return $name !== NULL;
				}

				return (
					$name === NULL and
					PieceLib::getById($this['piece']['id'], ['id'])->notEmpty()
				);

			})
			// Deux pièces libres du même nom au même moment font écrire deux fois la même ligne sur la fiche.
			// Le moment se construit avant le nom, à la création comme à la modification.
			->addAfterCheck('duplicate', function(?string $name, \PropertyList $l): bool {

				$l->skipIfInvalid('moment');

				if($name === NULL) {
					return TRUE;
				}

				return AidPieceLib::existsByName($this['aid'], $this['moment'], $name, $this) === FALSE;

			});

	}

}
?>
