<?php
namespace securing;

class SignatureTailLib extends SignatureTailCrud {

	/**
	 * Aligne le repère d'une source sur le maillon qu'on vient de chaîner. Appelé par SignatureLib::sign() sous le
	 * verrou de la fin de chaîne et dans sa transaction : le repère et le maillon s'écrivent ensemble ou pas du tout.
	 */
	public static function synchronize(string $source, string $hmacLast): void {

		$key = count(\main\MainSetting::$hmacKeys) - 1;

		$eSignatureTail = new SignatureTail([
			'source' => $source,
			'key' => $key,
			'hmac' => self::getHmac($source, $hmacLast, $key),
		]);

		SignatureTail::model()
			->option('add-replace')
			->insert($eSignatureTail);

	}

	/**
	 * Le repère signe le dernier maillon avec la clé, comme un maillon signe le précédent : recopier le HMAC du
	 * nouveau dernier maillon après en avoir retiré un ne suffit pas à le réaligner, il faut la clé.
	 */
	public static function getHmac(string $source, string $hmacLast, int $key): string {

		return hash_hmac(
			'sha256',
			$source.':'.$hmacLast,
			\main\MainSetting::$hmacKeys[$key]
		);

	}

	public static function getBySource(string $source): SignatureTail {

		return SignatureTail::model()
			->select(SignatureTail::getSelection())
			->whereSource($source)
			->get();

	}

}
?>
