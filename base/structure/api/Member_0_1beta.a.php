<?php
namespace structure;

class Member_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'general';

	public function __construct() {

		$this->list('memberList');

		// Pas de création : un membre naît de l'acceptation d'une invitation (structure\Invite).
		$this->read('member/{id}');
		$this->update('member/{id}');
		$this->delete('member/{id}');

		$this->element(
			'structure\Member',
			fn() => [
				'id', 'user', 'role', 'status'
			],
			[
				'user' => fn(\ApiData $data, Member $e) => $data->exportElement($e['user'])
			]
		);

	}

	/**
	 * Deux catégories de membres se lisent mais ne s'écrivent pas depuis l'API. Le refus est posé ici
	 * plutôt que laissé aux erreurs métier de MemberLib : la raison est la même pour PUT et DELETE, et
	 * le framework n'offre aucun hook sur delete().
	 */
	public function load(\ApiData $data, array $routeInfo): void {

		parent::load($data, $routeInfo);

		if(in_array($data->routeInfo['requestMethod'], ['PUT', 'DELETE']) === FALSE) {
			return;
		}

		// Une personne de l'équipe à qui l'on a retiré tout rôle garde son adhésion, sans rôle : l'accès se redonne
		// par une invitation, qui la fait entrer de nouveau (`InviteLib::acceptMember()`). Un rôle écrit ici la
		// laisserait sortie.
		if($data->e->isRevoked()) {
			throw new \ApiException('A member without access can only be given access back by an invitation', 'no_access_not_writable', 412);
		}

		// Un jeton n'agit au nom d'aucun membre : il pourrait vider une structure de tous ses admins
		// ou s'en promouvoir un. L'attribution du rôle admin est bloquée séparément, par la liste
		// cRole posée dans getById().
		if($data->e['role']['admin']) {
			throw new \ApiException('An admin member can only be managed from the web interface', 'admin_not_writable', 412);
		}

		// Un utilisateur fantôme n'a pas de compte, il se convertit en membre réel par une invitation.
		// MemberLib::delete() refuse déjà de le supprimer, mais seulement tant qu'il porte le rôle
		// fantôme : sans ce blocage, un PUT lui attribue un rôle ordinaire (le web s'en protège par
		// canUpdateRole(), que l'API ne consulte pas) et le DELETE passe ensuite.
		if($data->e['role']->isGhost()) {
			throw new \ApiException('A ghost user can only be managed from the web interface', 'ghost_not_writable', 412);
		}

	}

	public function getById(\ApiData $data): Member {

		$eMember = parent::getById($data);


		if(in_array($data->routeInfo['requestMethod'], ['PUT', 'DELETE'])) {

			// Lue par le callback role.check : l'API ne propose pas le rôle admin.
			$eMember['cRole'] = RoleLib::getByStructure($data->eStructure, withAdmin: FALSE);

		}

		return $eMember;

	}

	/**
	 * Les membres que la page Équipe montre (`MemberLib::applyTeam()`), en liste comme par leur id : une adhésion
	 * qu'elle ne montre pas, sortie et sans rôle hors de l'équipe par exemple, ne se lit ni ne s'écrit par l'API.
	 */
	public function getNewModel(\ApiData $data): MemberModel {

		return MemberLib::applyTeam(
			new \structure\MemberModel()->whereStructure($data->eStructure),
			$data->eStructure
		);

	}

	public function getNewElement(\ApiData $data): Member {

		return new \structure\Member([
			'structure' => $data->eStructure
		]);

	}

}
?>
