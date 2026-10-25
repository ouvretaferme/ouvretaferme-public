<?php
namespace main;

class CsvUi {

	public function getImportButton(\Element $e, string $url, bool $ajax = FALSE): string {

		$form = new \util\FormUi();

		// Un formulaire ajax n'est envoyé par form.js que sur l'événement submit, que requestSubmit() lève et submit() non
		if($ajax) {
			$h = $form->openAjax($url, ['binary' => TRUE]);
			$submit = 'this.form.requestSubmit()';
		} else {
			$h = $form->openUrl($url, ['binary' => TRUE, 'method' => 'post']);
			$submit = 'this.form.submit()';
		}

			$h .= $form->hidden('id', $e);
			$h .= '<label class="btn btn-primary">';
				$h .= $form->file('csv', ['onchange' => $submit]);
				$h .= s("Importer un fichier CSV depuis mon ordinateur");
			$h .= '</label>';
		$h .= $form->close();

		return $h;

	}

	public static function getSyntaxInfo(): string {

		$h = '<ul>';
			$h .= '<li>'.s("La première ligne du fichier CSV correspond aux en-têtes qui doivent être recopiées sans modification").'</li>';
			$h .= '<li>'.s("Le séparateur des colonnes dans le fichier est la virgule (,)").'</li>';
			$h .= '<li>'.s("Le fichier doit être enregistré au format UTF-8 ou UTF-16").'</li>';
		$h .= '</ul>';

		return $h;

	}

	/**
	 * ⚠️ `$url` vide veut dire qu'il n'y a pas de fichier derrière ces conflits : la copie du plan
	 * d'une ferme emprunte la même analyse, mais on n'y corrige rien et il n'y a aucun format à
	 * respecter. Renvoyer vers la documentation du CSV depuis un écran sans CSV envoyait lire une
	 * page qui ne parle pas de ce qu'on regarde.
	 */
	public static function getGlobalErrors(int $count, string $url, bool $hasDoc = TRUE): string {

		$h = '<div class="util-block">';

			if($url !== '') {

				$h .= '<h4 class="color-danger">'.p("{value} problème a été trouvé dans le fichier CSV", "{value} problèmes ont été trouvés dans le fichier CSV", $count).'</h4>';

				$h .= '<p>'.s("Vous pouvez parcourir le tableau ci-dessous pour identifier ces problèmes et les corriger. Pour que {siteName} puisse importer vos données sans erreur, il est indispensable que le format CSV soit strictement respecté. Si vous n'êtes pas à l'aise avec cela, nous vous recommandons de ne pas utiliser cette fonctionnalité et de saisir vos données manuellement.").'</p>';

				if($hasDoc) {
					$h .= '<a href="'.$url.'" class="btn btn-primary">'.s("Voir la documentation du format").'</a>';
				}

			} else {

				$h .= '<h4 class="color-danger">'.p("{value} problème empêche cette copie", "{value} problèmes empêchent cette copie", $count).'</h4>';

				$h .= '<p>'.s("Le tableau ci-dessous les détaille. Ils tiennent à ce que la ferme d'arrivée ne connaît pas — une espèce, une unité de récolte, un outil — et se règlent dans ses réglages à elle.").'</p>';

			}

		$h .= '</div>';

		return $h;

	}

	public static function getDataList($data): string {

		$required = FALSE;

		$h = '<div class="util-overflow-lg">';
			$h .= '<table class="tr-bordered mb-1">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Type de donnée").'</th>';
						$h .= '<th>'.s("Nom de l'entête").'</th>';
						$h .= '<th>'.s("Description").'</th>';
						$h .= '<th>'.s("Exemple").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';
					foreach($data as $row) {

						if(is_string($row)) {
							$h .= '<tr class="tr-title">';
								$h .= '<td colspan="4">'.$row.'</td>';
							$h .= '</tr>';
						} else {

							[$title, $column, $description, $example] = $row;

							$required = ($required or str_contains($title, 'form-asterisk'));

							$h .= '<tr>';
								$h .= '<td>'.$title.'</td>';
								$h .= '<td><pre>'.$column.'</pre></td>';
								$h .= '<td style="max-width: 25rem">'.$description.'</td>';
								$h .= '<td>'.($example === NULL ? '-' : '<span class="util-literal">'.$example.'</span>').'</td>';
							$h .= '</tr>';

						}
					}
				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		if($required) {
			$h .= \util\FormUi::asteriskInfo(NULL);
		}

		return $h;

	}

}
?>
