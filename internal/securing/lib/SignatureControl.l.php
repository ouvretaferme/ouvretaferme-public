<?php
namespace securing;

class SignatureControlLib {

	/** La clé d'une fin de chaîne qui ne tombe pas sur son repère : un id de maillon commence à 1. */
	const TAIL = 0;

	/**
	 * Contrôle une référence sur ses données d'aujourd'hui, avec la clé et le maillon précédent de sa dernière
	 * signature. Recalculé sur le `data` enregistré, le HMAC ne verrait pas une donnée changée depuis.
	 */
	public static function controlReference(\structure\Structure $eStructure, string $source, int $reference, array $data): string {

		\structure\StructureLib::connectDatabase($eStructure);

		$eSignature = Signature::model()
			->select(['key', 'hmac', 'hmacChained'])
			->whereSource($source)
			->whereReference($reference)
			->sort([
				'id' => SORT_DESC
			])
			->get();

		if($eSignature->empty()) {
			return Signature::UNSIGNED;
		}

		$eSignature['data'] = serialize($data);

		return (SignatureLib::getHmac($eSignature) === $eSignature['hmac']) ? Signature::INTACT : Signature::ALTERED;

	}

	/**
	 * Le contrôle d'une archive. Une archive dont le journal de création dit qu'elle a été signée et dont le maillon
	 * a disparu est altérée : sans ce journal, la suppression d'un maillon la confondrait avec une archive d'avant
	 * la signature des archives, et la ferait disparaître sans trace.
	 */
	public static function controlArchive(\structure\Structure $eStructure, string $source, string $event, int $reference, array $data): string {

		$integrity = self::controlReference($eStructure, $source, $reference, $data);

		if(
			$integrity === Signature::UNSIGNED and
			in_array($reference, self::getArchivesLoggedSigned($event))
		) {
			return Signature::ALTERED;
		}

		return $integrity;

	}

	/**
	 * Les archives d'une structure qui se restituent : celles qui ont un maillon, et celles dont le journal de
	 * création dit qu'elles ont été signées, qui se restituent altérées.
	 */
	public static function getArchivesSigned(\structure\Structure $eStructure, string $source, string $event): array {

		\structure\StructureLib::connectDatabase($eStructure);

		$references = Signature::model()
			->whereSource($source)
			->getColumn('reference');

		return array_values(array_unique(array_merge($references, self::getArchivesLoggedSigned($event))));

	}

	private static function getArchivesLoggedSigned(string $event): array {

		$cLog = Log::model()
			->select('data')
			->whereEvent($event)
			->getCollection();

		$references = [];

		foreach($cLog as $eLog) {

			// Le journal d'une archive d'avant la signature des archives ne porte pas signed
			if(($eLog['data']['signed'] ?? FALSE) === TRUE) {
				$references[] = (int)$eLog['data']['archive'];
			}

		}

		return $references;

	}

	/**
	 * Rend les maillons fautifs de la chaîne d'une source, par id : un HMAC qui ne se recalcule pas, ou un
	 * maillon qui ne chaîne pas sur le précédent. Recalculer chaque HMAC avec le `hmacChained` enregistré ne
	 * suffit pas : un maillon retiré ou inséré laisse chaque ligne cohérente avec elle-même, et seul le lien
	 * entre deux maillons voisins le trahit. Une fin de chaîne qui ne tombe pas sur le repère de la source se
	 * rend sous la clé TAIL, qu'aucun maillon ne peut avoir.
	 */
	public static function controlHmac(\structure\Structure $eStructure, string $source): array {

		\structure\StructureLib::connectDatabase($eStructure);

		$cSignature = Signature::model()
			->select('id', 'hmac', 'hmacChained', 'data', 'key')
			->whereSource($source)
			->recordset()
			->sort(['id' => SORT_ASC])
			->getCollection();

		$counter = 0;
		$hmacPrevious = NULL;
		$anomalies = [];

		foreach($cSignature as $eSignature) {

			echo "\r".(++$counter).' verified';

			$hmac = SignatureLib::getHmac($eSignature);
			$anomaly = NULL;

			if($hmac !== $eSignature['hmac']) {
				$anomaly = $hmac.' expected, '.$eSignature['hmac'].' found';
			} else if($eSignature['hmacChained'] !== $hmacPrevious) {
				$anomaly = 'chained on '.($eSignature['hmacChained'] ?? 'nothing').', previous is '.($hmacPrevious ?? 'nothing');
			}

			if($anomaly !== NULL) {
				$anomalies[$eSignature['id']] = $anomaly;
				echo "\r".'* Signature '.$eSignature['id'].': '.$anomaly."\n";
			}

			$hmacPrevious = $eSignature['hmac'];

		}

		// Un maillon retiré en fin de chaîne laisse une chaîne plus courte et cohérente : seul le repère, écrit hors
		// de la chaîne avec chaque maillon et signé avec la clé, dit où elle finissait
		$eSignatureTail = SignatureTailLib::getBySource($source);

		if($eSignatureTail->empty()) {
			$tailAnomaly = ($hmacPrevious === NULL) ? NULL : 'no tail';
		} else if($hmacPrevious === NULL) {
			$tailAnomaly = 'tail signs a link';
		} else {
			$tailAnomaly = (SignatureTailLib::getHmac($source, $hmacPrevious, $eSignatureTail['key']) === $eSignatureTail['hmac']) ? NULL : 'tail does not sign it';
		}

		if($tailAnomaly !== NULL) {
			$anomalies[self::TAIL] = 'chain ends on '.($hmacPrevious ?? 'nothing').', '.$tailAnomaly;
			echo "\r".'* Tail: '.$anomalies[self::TAIL]."\n";
		}

		echo "\n";

		return $anomalies;

	}

}
