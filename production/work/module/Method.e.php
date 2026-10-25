<?php
namespace work;

class Method extends MethodElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function properties(\PropertyList $l): void {

		// Une méthode se range sous une intervention de la structure. Le chemin web la pose depuis le
		// lien qui ouvre le panneau, après l'avoir passée par `->validate()` (`page/method.p.php`) ;
		// l'API la reçoit dans le corps et la construit, donc sans ce contrôle.
		$l->property('action')
			->addAfterCheck('owned', function(Action $eAction): bool {

				$this->expects(['structure']);

				return (
					Action::model()
						->select(['structure'])
						->get($eAction) and
					$eAction['structure']->is($this['structure'])
				);

			});

	}

}
?>