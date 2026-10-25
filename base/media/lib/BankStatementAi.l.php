<?php
namespace media;

/**
 * Les relevés bancaires PDF déposés pour importer des opérations (`bank\Import`, source PDF, R-21).
 *
 * Le type n'écrit dans aucun champ d'élément (`field` à NULL) : la ligne `bankImport` vit dans la base
 * de sa structure et ne reçoit le hash qu'après l'envoi. Le relevé se télécharge par une route du
 * paquet `bank` qui vérifie les droits ; l'adresse publique du stockage le sert pourtant à qui connaît
 * son hash, d'où son retrait du disque dès la suppression de l'import (`MediaLib::deleteByHash()`), comme
 * `proof-file`.
 */
class BankStatementAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai donnerait
	 * `bank-statement-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'bank-statement';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Le PDF est écrit tel qu'il a été déposé : la branche image de `MediaLib::send()` ne le concerne pas.
	 */
	public function send(\Element $eElement, &$hash, string $binary, int|string|null $fileType = NULL): bool {

		$this->addMedia($eElement, $hash, $fileType);

		MediaSetting::$mediaDriver->sendBinary($binary, $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

		return TRUE;

	}

	/**
	 * Les hashes sont cités depuis les bases `structure_<id>`, que le nettoyage de nuit ne parcourt
	 * pas : sans cette surcharge, `MediaLib::clean()` remettrait le type entier en ACTIVE, `field` étant
	 * NULL, y compris les relevés que `MediaLib::deleteByHash()` vient de passer en DELETED.
	 */
	public function clean(): bool {

		Media::model()
			->whereType($this->type)
			->whereStatus(Media::SEARCHING)
			->update([
				'status' => Media::ACTIVE
			]);

		return TRUE;

	}

}
?>
