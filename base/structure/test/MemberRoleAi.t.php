<?php
namespace structure;

/**
 * Ce que le panneau « Changer le rôle » annonce sous chaque rôle, dans `MemberUi::p('role')`.
 *
 * Défaut corrigé au lot AMAP C5, visible sur toutes les fermes : `accounting` est un enum
 * (`limited`, `full`, `denied`), et il passait par la branche qui lit les propriétés booléennes.
 * `denied` étant une chaîne non vide, tout rôle sans accès à la comptabilité affichait
 * « Gestion › Comptabilité » (24 841 rôles non administrateurs en dev le 2026-09-13).
 *
 * Les rôles sont construits en mémoire : le libellé ne lit que le rôle, et un rôle de production
 * dont on changerait les droits ferait varier le test avec la base.
 *
 * Lancement : dev/test package=structure file=MemberRoleAi
 */
class MemberRoleAiTest extends \Test {

	public function testAccountingIsAnnouncedOnlyWhenGranted(): void {

		$labels = $this->getLabels([
			1 => Role::DENIED,
			2 => Role::LIMITED,
			3 => Role::FULL,
		]);

		$this->assertFalse(str_contains($labels[1], s("Gestion")), 'un rôle sans accès à la comptabilité n\'annonce rien sous Gestion');
		$this->assertFalse(str_contains($labels[1], s("Comptabilité")), 'ni la comptabilité');

		$this->assertTrue(str_contains($labels[2], s("Comptabilité avec restrictions")), 'un accès restreint le dit');
		$this->assertTrue(str_contains($labels[3], s("Comptabilité intégrale")), 'un accès intégral le dit');

		// Le témoin : la ligne « Gestion » existe bien quand le droit est donné, sans quoi les deux
		// premières assertions passeraient sur un rendu vide.
		$this->assertTrue(str_contains($labels[3], s("Gestion")), 'la ligne Gestion se rend quand le droit est donné');

	}

	public function testAccountingAnalyzeStillAnnouncedAlone(): void {

		$labels = $this->getLabels([1 => Role::DENIED], ['accountingAnalyze' => TRUE]);

		$this->assertTrue(str_contains($labels[1], s("Analyse comptable")), 'l\'analyse comptable, booléenne, reste annoncée');
		$this->assertFalse(str_contains($labels[1], s("Comptabilité")), 'sans annoncer la comptabilité refusée');

	}

	/**
	 * Le lien « En savoir plus sur les rôles » mène à `/ferme/{id}/roles`, que la route garde par
	 * `acceptRoles()` (`role.p.php`) : il ne se rend que là où elle répond. Correction pour les
	 * groupes aussi, où il menait à une page refusée.
	 */
	public function testRolesLinkOnlyWhereTheRoutePasses(): void {

		foreach([
			\structure\Structure::PRODUCER => TRUE,
			\structure\Structure::AMAP => FALSE,
			\structure\Structure::COMMUNITY => FALSE,
		] as $type => $expected) {

			$eMember = new Member([
				'structure' => new Structure(['id' => 1, 'type' => $type]),
			]);

			$labelAfter = MemberUi::p('role')->labelAfter;

			$this->assertEquals($expected, str_contains($labelAfter($eMember), s("En savoir plus sur les rôles")), 'lien vers les rôles sur une structure '.$type);
			$this->assertEquals($expected, str_contains($labelAfter($eMember), s("Les rôles proposés sur cette page peuvent être personnalisés pour votre organisation.")), 'information sur les rôles personnalisables, structure '.$type);

		}

	}

	/**
	 * @param array<int, string> $accounting identifiant du rôle => valeur de `accounting`
	 * @return array<int, string> identifiant du rôle => libellé rendu
	 */
	private function getLabels(array $accounting, array $properties = []): array {

		$cRole = new \Collection();

		foreach($accounting as $id => $value) {

			$cRole[$id] = new Role($properties + [
				'id' => $id,
				'name' => 'Rôle '.$id,
				'fqn' => NULL,
				'admin' => FALSE,
				'visibility' => Role::PUBLIC,
				'planning' => Role::DENIED,
				'selling' => FALSE,
				'productionObserve' => FALSE,
				'productionAnalyze' => FALSE,
				'commercialisationAnalyze' => FALSE,
				'accounting' => $value,
				'accountingAnalyze' => FALSE,
			]);

		}

		$values = MemberUi::p('role')->values;

		return $values(new Member(['cRole' => $cRole, 'structure' => new Structure(['id' => 1, 'type' => Structure::PRODUCER])]));

	}

}
?>
