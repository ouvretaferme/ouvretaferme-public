<?php
namespace main;

class CryptLib {

	const METHOD = 'AES256';

	public static function encrypt(?string $data, string $secret, string $encode = 'base64_encode'): ?string {

		if($data === NULL) {
			return NULL;
		}

		$passphrase = MainSetting::$crypt[$secret] ?? throw new \Exception('Missing secret');

		$ivLength = openssl_cipher_iv_length(self::METHOD);
		$iv = openssl_random_pseudo_bytes($ivLength);

		return $encode($iv.openssl_encrypt($data, self::METHOD, $passphrase, iv: $iv));

	}

	public static function decrypt(?string $data, string $secret, string $decode = 'base64_decode'): ?string {

		if($data === NULL) {
			return NULL;
		}

		$passphrase = MainSetting::$crypt[$secret] ?? throw new \Exception('Missing secret');
		$decodedData = $decode($data);

		$ivLength = openssl_cipher_iv_length(self::METHOD);

		if(strlen($decodedData) < $ivLength) {
			return NULL;
		}

		$iv = substr($decodedData, 0, $ivLength);

		$decryptedData = openssl_decrypt(substr($decodedData, $ivLength), self::METHOD, $passphrase, iv: $iv);

		if($decryptedData === FALSE) {
			return NULL;
		} else {
			return $decryptedData;
		}

	}

	public static function sign(string $data, string $secret): string {

		return $data.':'.hash_hmac('sha256', $data, self::getSignatureKey($secret));

	}

	public static function verify(string $signed, string $secret): ?string {

		$position = strrpos($signed, ':');

		if($position === FALSE) {
			return NULL;
		}

		$data = substr($signed, 0, $position);
		$signature = substr($signed, $position + 1);

		if(hash_equals(hash_hmac('sha256', $data, self::getSignatureKey($secret)), $signature) === FALSE) {
			return NULL;
		}

		return $data;

	}

	/**
	 * Un condensé d'une valeur, pour la citer dans un jeton signé sans l'y écrire en clair.
	 *
	 * Le préfixe `digest` sépare les deux usages de la clé : sans lui, le condensé d'une valeur serait la
	 * signature de cette même valeur, donc un jeton signé tout prêt pour qui le lit.
	 */
	public static function digest(string $data, string $secret, int $length = 32): string {

		return substr(hash_hmac('sha256', 'digest:'.$data, self::getSignatureKey($secret)), 0, $length);

	}

	private static function getSignatureKey(string $secret): string {

		$passphrase = MainSetting::$crypt[$secret] ?? throw new \Exception('Missing secret');

		return hash_hmac('sha256', 'sign', $passphrase);

	}

}
?>
