<?php
namespace structure;

class Role extends RoleElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function acceptCreate(): bool {
		return $this['structure']->acceptRoles();
	}

	public function acceptUpdate(): bool {

		$this->expects(['admin', 'visibility', 'fqn']);

		return (
			$this['admin'] === FALSE and
			$this['visibility'] === Role::PUBLIC and
			$this['fqn'] === NULL
		);

	}

	public function acceptDelete(): bool {
		return $this->acceptUpdate();
	}

	public function isGhost(): bool {
		return $this['fqn'] === 'ghost';
	}

	public function isProducer(): bool {

		$this->expects(['fqn']);

		return $this['fqn'] === 'producer';

	}

	/**
	 * Ce rôle encadre-t-il le groupe, au lieu d'y adhérer ?
	 *
	 * ⚠️ Le critère est le `fqn` et jamais la portée : `advisor` et `member` portent tous deux
	 * `sections = ALL` dans un réseau, et `admin` seul laisserait le conseiller dehors. Les rôles
	 * d'encadrement sont ceux que le produit crée pour encadrer, et `RoleLib::createForStructure()`
	 * est le seul endroit qui pose ces deux `fqn`.
	 *
	 * ⚠️ Un rôle qu'un groupe se donnera demain n'a pas de `fqn` et tombe donc du côté des
	 * adhérents. C'est le bon défaut : un rôle sur mesure nuance l'adhésion, il ne fabrique pas un
	 * corps enseignant.
	 */
	public function isStaff(): bool {

		$this->expects(['fqn']);

		return in_array($this['fqn'], StructureSetting::ROLE_FQNS, TRUE);

	}

}
?>