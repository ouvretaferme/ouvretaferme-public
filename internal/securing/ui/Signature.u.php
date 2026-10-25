<?php
namespace securing;

class SignatureUi {

	/**
	 * L'empreinte et la signature d'une archive, que les notices des archives de ventes et de caisse reprennent à l'identique.
	 */
	public function getNoticeIntegrity(string $file, string $sha256, string $integrity): string {

		$text = s("EMPREINTE")."\n\n";
		$text .= s("L'empreinte SHA-256 du fichier, calculée à sa création, est la suivante.")."\n";
		$text .= $sha256."\n\n";
		$text .= s("Elle se recalcule par la commande {macos} sur macOS et Linux, ou {windows} sous Windows. Une empreinte différente signifie que le fichier a été modifié.", [
			'macos' => 'shasum -a 256 '.$file,
			'windows' => 'certutil -hashfile '.$file.' SHA256',
		])."\n\n";

		$text .= s("SIGNATURE")."\n\n";
		$text .= s("À la création de l'archive, Ouvretaferme a signé sa période et son empreinte, en chaînant la signature à celle des archives précédentes. Seul Ouvretaferme, qui détient la clé de signature, peut la contrôler.")."\n\n";
		$text .= match($integrity) {
			\securing\Signature::INTACT => s("Ouvretaferme a contrôlé la signature au téléchargement de ce fichier, et l'archive n'a pas été modifiée depuis sa création."),
			\securing\Signature::ALTERED => s("Ouvretaferme a contrôlé la signature au téléchargement de ce fichier, et l'archive ne correspond plus à la signature posée à sa création. Ce constat est enregistré dans le journal de la structure."),
		}."\n";

		return $text;

	}

}
?>
