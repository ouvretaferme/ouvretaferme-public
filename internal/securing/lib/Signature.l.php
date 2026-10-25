<?php
namespace securing;

class SignatureLib {

	public static function sign(\structure\Structure $eStructure, string $source, int $reference, array $data): void {

		\structure\StructureLib::connectDatabase($eStructure);

		// Construit avant le verrou : une donnée qui ne se sérialise pas lève ici, sans rien tenir
		$e = new Signature([
			'source' => $source,
			'reference' => $reference,
			'data' => serialize($data),
		]);

		Signature::model()->beginTransaction();

		try {

			// Deux signatures simultanées ne chaînent pas sur le même maillon : la fin de la chaîne se verrouille. Elle
			// se lit dans l'ordre croissant de la clé primaire, à partir d'un maillon existant, parce que celui qui
			// attend bute alors sur un maillon sans rien tenir au-delà. Lue à rebours, elle verrouille d'abord la
			// fin de l'index, que deux transactions tiennent ensemble, et la première qui insère s'y interbloque.
			// La lecture traverse les maillons des deux sources : le verrou sérialise donc toutes les signatures
			// d'une même structure, caisse et vente confondues. Chaque structure ayant sa base, aucune n'attend
			// une autre.
			//
			// Le verrou dure jusqu'à la fin de la transaction la plus externe, pas jusqu'au commit() ci-dessous :
			// la pile de transactions est commune à toutes les bases d'un serveur, et une vente se signe depuis
			// SaleLib::recalculate(), dans la transaction de celui qui écrit la vente. Toutes les écritures de ventes
			// signées d'une structure se suivent donc une à une, de la signature à la fin de la transaction appelante.

			// Lecture sans verrou, qui ne donne que le point de départ : un maillon périmé convient, la lecture
			// verrouillante rend ceux qui le suivent. Une source vide part de 0 et parcourt la table une fois
			$signatureStart = Signature::model()
				->whereSource($source)
				->sort([
					'id' => SORT_DESC
				])
				->getValue('id') ?? 0;

			// Le maillon à chaîner ne se lit que par une lecture verrouillante, qui rend la dernière ligne validée :
			// la vue de la transaction appelante est antérieure à l'attente, et une lecture simple y rendrait le
			// maillon d'avant, sur lequel la chaîne fourcherait sans erreur
			$cSignatureLocked = Signature::model()
				->select(['reference', 'data', 'hmac'])
				->whereSource($source)
				->whereId('>=', $signatureStart)
				->sort([
					'id' => SORT_ASC
				])
				->option('for-update')
				->getCollection();

			$eSignatureLast = $cSignatureLocked->empty() ? new Signature() : $cSignatureLocked->last();

			// Pour la même raison, la dernière signature de la référence se lit d'abord parmi les maillons verrouillés :
			// celle qu'un autre a validée pendant l'attente s'y trouve, et la vue de l'appelant l'ignore
			$dataBefore = NULL;
			$found = FALSE;

			foreach($cSignatureLocked as $eSignatureLocked) {

				if($eSignatureLocked['reference'] === $reference) {
					$dataBefore = $eSignatureLocked['data'];
					$found = TRUE;
				}

			}

			if($found === FALSE) {

				$dataBefore = Signature::model()
					->whereSource($source)
					->whereReference($reference)
					->sort([
						'id' => SORT_DESC
					])
					->getValue('data');

			}

			if($dataBefore !== $e['data']) {

				$e['key'] = count(\main\MainSetting::$hmacKeys) - 1;

				$e['hmacChained'] = $eSignatureLast->empty() ? NULL : $eSignatureLast['hmac'];

				$e['hmac'] = self::getHmac($e);

				Signature::model()->insert($e);

				SignatureTailLib::synchronize($source, $e['hmac']);

			}

			Signature::model()->commit();

		} catch(\Throwable $exception) {

			// Un échec sous le verrou le rend : un processus qui signe pour plusieurs ventes à la suite tiendrait
			// sinon le dernier maillon jusqu'à sa fin. rollBack() vide la pile entière, transaction de l'appelant
			// comprise, qui n'a de toute façon plus rien à valider
			Signature::model()->rollBack();

			throw $exception;

		}

	}

	public static function getHmac(Signature $e) {

		$e->expects(['data', 'hmacChained', 'key']);

		return hash_hmac(
			'sha256',
			$e['hmacChained'].':'.$e['data'],
			\main\MainSetting::$hmacKeys[$e['key']]
		);

	}

}
