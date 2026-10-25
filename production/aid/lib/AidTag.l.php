<?php
namespace aid;

class AidTagLib extends AidTagCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['tag'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return [];
	}

	/**
	 * Aligne les tags d'une aide sur ceux cochés : ceux décochés se retirent, ceux cochés en plus
	 * s'ajoutent, et ceux qui restent ne se touchent pas.
	 */
	public static function synchronize(Aid $eAid, \Collection $cTag): void {

		AidTag::model()->beginTransaction();

			$cAidTag = AidTag::model()
				->select(['id', 'tag'])
				->whereAid($eAid)
				->getCollection();

			$tagsKept = [];

			foreach($cAidTag as $eAidTag) {

				if($cTag->offsetExists($eAidTag['tag']['id'])) {
					$tagsKept[] = $eAidTag['tag']['id'];
				} else {
					self::delete($eAidTag);
				}

			}

			foreach($cTag as $eTag) {

				if(in_array($eTag['id'], $tagsKept) === FALSE) {
					self::create(new AidTag([
						'aid' => $eAid,
						'tag' => $eTag,
					]));
				}

			}

		AidTag::model()->commit();

	}

}
?>
