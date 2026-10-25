<?php
namespace structure;

class Invite_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'general';

	public function __construct() {

		$this->list(
			'inviteList',
			[
				'?status' => Invite::getValidator('status')
			]
		);

		$this->create('invite');
		$this->read('invite/{id}');
		// Pas de mise à jour : une invitation se révoque et se recrée, elle ne se modifie pas.
		$this->delete('invite/{id}');

		$this->element(
			'structure\Invite',
			fn() => [
				'id', 'email', 'memberRole',
				'limit', 'used', 'lastUsedAt', 'expiresAt',
				'status', 'link'
			],
			[
				// Le lien à transmettre soi-même, l'API n'envoyant pas d'e-mail.
				'link' => fn(\ApiData $data, Invite $e) => $e->getLink()
			]
		);

	}

	public function getList(\ApiData $data): InviteModel {

		return $this->getNewModel($data)
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): InviteModel {

		// Les invitations de clients portent un selling\Customer et celles de producteurs un
		// shop\Shop, que l'API n'expose pas : elles relèvent de la commercialisation, pas de la
		// gestion de l'équipe.
		return new \structure\InviteModel()
			->whereStructure($data->eStructure)
			->whereType(Invite::MEMBER);

	}

	public function getNewElement(\ApiData $data): Invite {

		return new \structure\Invite([
			'structure' => $data->eStructure,
			'type' => Invite::MEMBER,
			// memberRole.check n'accepte que les rôles de cette liste. Le rôle admin en est écarté :
			// les droits d'administrateur ne se donnent pas depuis l'API.
			'cRole' => RoleLib::getByStructure($data->eStructure, withAdmin: FALSE),
			// Un jeton enverrait sinon des e-mails depuis notre domaine à n'importe quelle adresse : le
			// lien rendu par l'API se transmet par l'appelant.
			'sendMail' => FALSE
		]);

	}

}
?>
