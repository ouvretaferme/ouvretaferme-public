<?php
namespace structure;

/**
 * Une clé d'API vaut ce que vaut le droit de son porteur, et ne lui survit pas.
 *
 * Une clé personnelle s'obtient sur `Structure::canManage()`, vit un an et ne porte rien d'autre que
 * l'identité de qui l'a demandée. Deux mécanismes la bornent, et le test exerce les deux parce qu'ils
 * ne couvrent pas les mêmes cas.
 *
 * Le contrôle à l'usage (`ApiData::authorized()`, `ouvretaferme/Lime.s.php`) relit le droit à chaque
 * appel : il ferme immédiatement, y compris pour les clés émises avant lui, et rouvre de lui-même si
 * le porteur redevient administrateur. La révocation aux événements (`MemberLib`) éteint la clé pour
 * de bon, ce que le contrôle à l'usage ne fait pas : elle sort des écrans une clé qu'aucun autre
 * administrateur ne pourrait révoquer à la place de son porteur parti.
 *
 * Chaque révocation est accompagnée de deux témoins qui doivent rester vivants : la clé d'un autre
 * membre sur la même structure, et une clé du même porteur sur une autre référence. Sans eux, un
 * `UPDATE` sans borne rendrait le test vert.
 *
 * Lancement : dev/test package=structure file=ApiKeyRightAi
 */
class ApiKeyRightAiTest extends \dev\ApiTest {

	/** Référence témoin : jamais touchée par les gestes du test, sa clé doit survivre à toutes */
	const OTHER_REFERENCE = 999000001;

	protected function getTransactionModel(): \ModuleModel {
		return Member::model();
	}

	public function testUsageRequiresAdminRight(): void {

		$eMemberAdmin = MemberLib::getAdminsByStructure($this->eStructure)->first();

		// Le cas accepté d'abord : un 403 seul ne dirait pas si la route refuse ce qu'elle doit, ou tout
		$this->assertEquals(200, $this->callApiAsUser($eMemberAdmin['user'], 'GET', 'system')['status'], 'la clé d\'un administrateur passe');

		$eMemberOther = $this->createMember($this->getObserverRole());

		$response = $this->callApiAsUser($eMemberOther['user'], 'GET', 'system');

		$this->assertEquals(403, $response['status'], 'la clé d\'un membre qui n\'administre pas est refusée');
		$this->assertEquals('user_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

	public function testUsageRefusesOutsider(): void {

		$response = $this->callApiAsUser($this->getOutsider(), 'GET', 'system');

		$this->assertEquals(403, $response['status'], 'la clé de qui n\'est pas membre est refusée');
		$this->assertEquals('user_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * ApiData::authorized() lit acceptApi() à chaque appel, et non plus acceptApiAccess() seul : fermer
	 * IS_API_ACTIVE coupe donc aussi les clés déjà émises, hors des structures de la bêta. La constante
	 * vaut TRUE en dev, l'état fermé ne s'éprouve que par l'argument de acceptApi(), comme
	 * isScopeOpen() pour l'écriture (framework/dev/test/ApiWriteScope.t.php).
	 */
	public function testClosedApiRefusesOutsideBeta(): void {

		$this->assertEquals(FALSE, in_array($this->eStructure['id'], StructureSetting::API_STRUCTURE_BETA), 'le décor n\'est pas une structure de la bêta');

		$this->assertEquals(TRUE, $this->eStructure->acceptApi(TRUE), 'API ouverte');
		$this->assertEquals(FALSE, $this->eStructure->acceptApi(FALSE), 'API fermée, hors bêta');

		$eStructureBeta = clone $this->eStructure;
		$eStructureBeta['id'] = StructureSetting::API_STRUCTURE_BETA[0];

		$this->assertEquals(TRUE, $eStructureBeta->acceptApi(FALSE), 'API fermée, structure de la bêta');

		$eStructureAmap = clone $eStructureBeta;
		$eStructureAmap['type'] = Structure::AMAP;

		$this->assertEquals(FALSE, $eStructureAmap->acceptApi(TRUE), 'une AMAP reste refusée, API ouverte ou non');

	}

	/**
	 * La lecture est ouverte à tous, l'écriture seulement aux structures de la bêta tant que
	 * IS_API_WRITE_ACTIVE est fermée. acceptApiWrite() décide pour les trois portes : les scopes
	 * proposés à une clé, l'action qui la crée et le refus à l'appel, par acceptApiScope().
	 */
	public function testClosedWriteOpensOnlyToBeta(): void {

		$this->assertEquals(FALSE, $this->eStructure->acceptApiWrite(FALSE), 'écriture fermée, hors bêta');
		$this->assertEquals(TRUE, $this->eStructure->acceptApiWrite(TRUE), 'écriture ouverte, hors bêta');

		$eStructureBeta = clone $this->eStructure;
		$eStructureBeta['id'] = StructureSetting::API_STRUCTURE_BETA[0];

		$this->assertEquals(TRUE, $eStructureBeta->acceptApiWrite(FALSE), 'écriture fermée, structure de la bêta');

		$eStructureAmap = clone $eStructureBeta;
		$eStructureAmap['type'] = Structure::AMAP;

		$this->assertEquals(FALSE, $eStructureAmap->acceptApiWrite(TRUE), 'une AMAP reste refusée, écriture ouverte ou non');

	}

	/**
	 * Hors formations, acceptApiScope() répond comme acceptApiWrite() : la page des clés et le refus à
	 * l'appel (`ApiData::acceptScope()`) passent par lui, et ne doivent rien ouvrir de plus que la
	 * bêta de l'écriture. Les formations s'éprouvent dans `learning/test/ScopeApi.t.php`.
	 */
	public function testApiScopeFollowsWriteOutsideLearning(): void {

		foreach(['general', 'production', 'commercialisation', 'accounting'] as $domain) {

			$this->assertEquals(TRUE, $this->eStructure->acceptApiScope($domain.':read', FALSE), $domain.':read, écriture fermée');
			$this->assertEquals(FALSE, $this->eStructure->acceptApiScope($domain.':write', FALSE), $domain.':write, écriture fermée, hors bêta');
			$this->assertEquals(TRUE, $this->eStructure->acceptApiScope($domain.':write', TRUE), $domain.':write, écriture ouverte');

		}

		$eStructureBeta = clone $this->eStructure;
		$eStructureBeta['id'] = StructureSetting::API_STRUCTURE_BETA[0];

		$this->assertEquals(TRUE, $eStructureBeta->acceptApiScope('general:write', FALSE), 'general:write, écriture fermée, structure de la bêta');

	}

	/**
	 * La comptabilité ne s'ouvre sous une clé qu'à la structure qui l'a activée, comme ses écrans :
	 * ni la page des clés ne la propose, ni l'appel ne la sert à une structure qui ne l'a pas.
	 */
	public function testAccountingScopeRequiresAccounting(): void {

		$this->assertTrue($this->eStructure->hasAccounting(), 'le décor a la comptabilité');
		$this->assertTrue(in_array('accounting:read', $this->eStructure->getApiScopes()), 'la page des clés la propose');

		$this->eStructure->conf(Feature::class)['hasAccounting'] = FALSE;

		foreach([FALSE, TRUE] as $writeActive) {
			$this->assertEquals(FALSE, $this->eStructure->acceptApiScope('accounting:read', $writeActive), 'accounting:read, sans comptabilité');
			$this->assertEquals(FALSE, $this->eStructure->acceptApiScope('accounting:write', $writeActive), 'accounting:write, sans comptabilité');
		}

		$this->assertEquals(TRUE, $this->eStructure->acceptApiScope('production:read'), 'les autres domaines restent ouverts');
		$this->assertFalse(in_array('accounting:read', $this->eStructure->getApiScopes()), 'la page des clés ne la propose plus');

		FeatureLib::forget($this->eStructure);

	}

	public function testStatusOutRevokesKey(): void {

		$eMember = $this->createMember($this->getAdminRole());

		[$eAccessToken, $eAccessTokenOtherReference, $eAccessTokenOtherUser] = $this->createKeys($eMember);

		$this->assertEquals(200, $this->callApiAsUser($eMember['user'], 'GET', 'system')['status'], 'avant la sortie, la clé passe');

		$eMember['status'] = Member::OUT;
		MemberLib::update($eMember, ['status']);

		$this->assertTrue($this->isRevoked($eAccessToken), 'la sortie du collectif révoque la clé');
		$this->assertFalse($this->isRevoked($eAccessTokenOtherReference), 'la clé du même porteur sur une autre référence survit');
		$this->assertFalse($this->isRevoked($eAccessTokenOtherUser), 'la clé d\'un autre porteur sur la même structure survit');

		$response = $this->callApiAsUser($eMember['user'], 'GET', 'system');

		$this->assertEquals(403, $response['status'], 'et l\'appel est refusé, même avec une clé encore valide en apparence');

	}

	public function testDemotionRevokesKey(): void {

		$eMember = $this->createMember($this->getAdminRole());

		[$eAccessToken, $eAccessTokenOtherReference, $eAccessTokenOtherUser] = $this->createKeys($eMember);

		$eMember['role'] = $this->getObserverRole();
		MemberLib::update($eMember, ['role']);

		$this->assertTrue($this->isRevoked($eAccessToken), 'la perte du rôle d\'administrateur révoque la clé');
		$this->assertFalse($this->isRevoked($eAccessTokenOtherReference), 'la clé du même porteur sur une autre référence survit');
		$this->assertFalse($this->isRevoked($eAccessTokenOtherUser), 'la clé d\'un autre porteur sur la même structure survit');

	}

	public function testPromotionKeepsKey(): void {

		$eMember = $this->createMember($this->getAdminRole());

		[$eAccessToken] = $this->createKeys($eMember);

		// Témoin du contrôle lui-même : s'il révoquait à chaque écriture du rôle, les trois cas
		// ci-dessus seraient verts sans rien prouver
		$eMember['role'] = $this->getAdminRole();
		MemberLib::update($eMember, ['role']);

		$this->assertFalse($this->isRevoked($eAccessToken), 'un changement de rôle qui laisse l\'administration ne révoque rien');

	}

	public function testDeleteRevokesKey(): void {

		$eMember = $this->createMember($this->getAdminRole());

		[$eAccessToken, $eAccessTokenOtherReference, $eAccessTokenOtherUser] = $this->createKeys($eMember);

		MemberLib::deleteInternal($eMember);

		$this->assertTrue($this->isRevoked($eAccessToken), 'le retrait du membre révoque la clé');
		$this->assertFalse($this->isRevoked($eAccessTokenOtherReference), 'la clé du même porteur sur une autre référence survit');
		$this->assertFalse($this->isRevoked($eAccessTokenOtherUser), 'la clé d\'un autre porteur sur la même structure survit');

	}

	/**
	 * Un membre neuf plutôt qu'un membre réel de la structure de référence : `deleteInternal()` coupe
	 * aussi les présences, les absences et les sous-groupes, qui vivent dans la base de structure et
	 * ne sont donc pas couverts par la transaction ouverte sur la base commune.
	 */
	private function createMember(Role $eRole): Member {

		$eMember = new Member([
			'user' => $this->getOutsider(),
			'structure' => $this->eStructure,
			'role' => $eRole,
			'status' => Member::IN,
		]);

		MemberLib::create($eMember);

		return $eMember;

	}

	/**
	 * @return array{\api\AccessToken, \api\AccessToken, \api\AccessToken} la clé du membre, la sienne
	 *         sur une autre référence, celle d'un administrateur en exercice sur la même structure
	 */
	private function createKeys(Member $eMember): array {

		return [
			$this->createKey($eMember['user'], $this->eStructure['id']),
			$this->createKey($eMember['user'], self::OTHER_REFERENCE),
			$this->createKey(MemberLib::getAdminsByStructure($this->eStructure)->first()['user'], $this->eStructure['id']),
		];

	}

	/**
	 * La clé s'insère directement : `PersonalAccessTokenLib::issue()` signe un JWT avec la paire RSA,
	 * qui n'est pas versionnée, et rien de ce que le test mesure ne dépend de la signature.
	 */
	private function createKey(\user\User $eUser, int $reference): \api\AccessToken {

		$eAccessToken = new \api\AccessToken([
			'tokenId' => str_pad(bin2hex(random_bytes(16)), 128, '0'),
			'client' => \api\ClientLib::getPersonalClient(),
			'user' => $eUser,
			'reference' => $reference,
			'name' => 'zzTest clé',
			'scopes' => \api\ApiSetting::PERSONAL_SCOPE_NAMES,
			'expiryDateTime' => new \Sql('NOW() + INTERVAL 1 YEAR'),
			'revoked' => FALSE,
		]);

		\api\AccessToken::model()->insert($eAccessToken);

		return $eAccessToken;

	}

	private function isRevoked(\api\AccessToken $eAccessToken): bool {

		return \api\AccessToken::model()
			->select('revoked')
			->whereId($eAccessToken['id'])
			->get()['revoked'];

	}

	private function callApiAsUser(\user\User $eUser, string $method, string $route): array {

		$tokenInitial = \api\ApiSetting::$devAccessToken;

		\api\ApiSetting::$devAccessToken = fn() => new \api\AccessToken([
			'reference' => $this->eStructure['id'],
			'user' => $eUser,
		]);

		try {
			return $this->callApi($method, $route);
		} finally {
			\api\ApiSetting::$devAccessToken = $tokenInitial;
		}

	}

	private function getAdminRole(): Role {

		return Role::model()
			->select(['id', 'admin'])
			->whereStructure($this->eStructure)
			->whereAdmin(TRUE)
			->get();

	}

	private function getObserverRole(): Role {

		return Role::model()
			->select(['id', 'admin'])
			->whereStructure($this->eStructure)
			->whereAdmin(FALSE)
			->whereFqn(NULL)
			->get();

	}

	/**
	 * Un utilisateur qui n'est membre de la structure à aucun titre, pas même sorti.
	 */
	private function getOutsider(): \user\User {

		return \user\User::model()
			->join(Member::model(), 'm1.id = m2.user AND m2.structure = '.Member::model()->format($this->eStructure), 'LEFT')
			->select(['id'])
			->where('m2.id IS NULL')
			->sort(['m1.id' => SORT_ASC])
			->get();

	}

}
?>
