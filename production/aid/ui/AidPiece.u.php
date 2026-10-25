<?php
namespace aid;

class AidPieceUi {

	// Une ligne porte une pièce courante ou une pièce libre, jamais les deux (AidPiece::properties()).
	public static function getName(AidPiece $eAidPiece): string {
		return $eAidPiece['piece']->notEmpty() ? $eAidPiece['piece']['name'] : $eAidPiece['name'];
	}

	// Tout ce que la ligne d'ajout pose se corrige ici, sauf la pièce courante : une autre pièce se retire
	// et s'ajoute depuis la table. Son nom est celui de la liste commune, seul celui d'une pièce libre
	// s'écrit.
	public function update(AidPiece $eAidPiece): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/aid/admin/aidPiece:doUpdate');

			$h .= $form->hidden('id', $eAidPiece);

			$h .= $form->dynamicGroups($eAidPiece, $eAidPiece['piece']->empty() ? ['name', 'moment', 'note', 'url'] : ['moment', 'note', 'url']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-aid-piece',
			title: s("Modifier la pièce"),
			subTitle: encode(self::getName($eAidPiece)),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = AidPiece::model()->describer($property, [
			'moment' => s("Moment"),
			'piece' => s("Pièce"),
			'name' => s("Nom de la pièce"),
			'note' => s("Condition"),
			'url' => s("Modèle à remplir"),
		]);

		switch($property) {

			case 'moment' :
				$d->field = 'select';
				$d->values = [
					AidPiece::APPLICATION => s("Demande d'aide"),
					AidPiece::PAYMENT => s("Demande de paiement"),
				];
				$d->placeholder = s("Moment");
				break;

			case 'note' :
				$d->placeholder = s("Condition, si elle ne vaut pas pour tous");
				break;

			case 'url' :
				$d->after = \util\FormUi::info(s("Le formulaire de l'organisme, quand la pièce en a un"));
				break;

		}

		return $d;

	}

}
?>
