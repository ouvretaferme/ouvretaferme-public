<?php
namespace structure;

class ApiKeyLib {

	// Un scope :write embarque toujours le :read correspondant, on ne garde que l'accès le plus large par domaine
	public static function getAccesses(\api\AccessToken $eAccessToken): array {

		$eAccessToken->expects(['scopes']);

		$accesses = [];

		foreach($eAccessToken['scopes'] as $scope) {

			[$domain, $right] = explode(':', $scope);

			if($right === 'write' or isset($accesses[$domain]) === FALSE) {
				$accesses[$domain] = $right;
			}

		}

		return $accesses;

	}

	/**
	 * La structure que la personne relie à une application tierce, quand l'application ne l'a pas
	 * nommée : elle ne connaît pas les structures de qui se connecte chez elle. Seules s'offrent
	 * celles que la personne peut relier, et son choix rejoue la demande avec le bon id.
	 *
	 * Une seule structure se relie sans qu'on la propose. Aucune, quand la personne en administre
	 * qui n'ont pas l'offre qui ouvre l'API : la demande se rejoue sur la première, et la porte
	 * habituelle de la page d'autorisation l'envoie vers l'offre, qui explique ce qui manque.
	 */
	public static function resolveAuthorize(\stdClass $data): void {

		$cStructure = new \Collection();

		foreach(StructureLib::getOnline() as $cStructureByType) {
			$cStructure->mergeCollection($cStructureByType, reindex: FALSE);
		}

		$cStructureManaged = $cStructure->find(fn(Structure $eStructure) => $eStructure->canManage() and $eStructure->acceptApi());

		foreach($cStructureManaged as $eStructure) {
			$eStructure['urlRequested'] = '/api/oauth/authorize?'.http_build_query($_GET + ['structure' => $eStructure['id']]);
		}

		$cStructureAuthorize = $cStructureManaged->find(fn(Structure $eStructure) => $eStructure->acceptFeature(Structure::FEATURE_API));

		if($cStructureAuthorize->count() === 1) {
			throw new \RedirectAction($cStructureAuthorize->first()['urlRequested'], httpStatus: StructureSentinelAiLib::HTTP_STATUS);
		}

		if($cStructureAuthorize->empty() and $cStructureManaged->notEmpty()) {
			throw new \RedirectAction($cStructureManaged->first()['urlRequested'], httpStatus: StructureSentinelAiLib::HTTP_STATUS);
		}

		$data->cStructureAuthorize = $cStructureAuthorize;

		throw new \ViewAction($data, path: 'structure/apiKey:authorize');

	}

}
?>
