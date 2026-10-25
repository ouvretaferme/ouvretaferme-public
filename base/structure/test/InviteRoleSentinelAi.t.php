<?php
namespace structure;

/**
 * Chaque type de structure a-t-il encore sa mascotte d'accueil, et l'invitation la rend-elle ?
 *
 * La mascotte d'un accueil d'invitation se lit dans la classe de type (`getWelcomeMascot()`, déclarée
 * par `StructureCommonUi`). Rien ne rappellera ce contrat à qui ajoutera un type de structure ou un
 * type de groupe, ou à qui écrira un accueil qui ne la lit plus : d'où cette sentinelle, qui vérifie
 * un **inventaire** et non un comportement.
 *
 * Elle compare dans les deux sens : une classe de type présente dans le package qu'aucun type de
 * structure n'atteint, et un type qui atteint une classe que le package ne porte pas. Pour chaque type
 * atteint, la mascotte répondue existe en image et la carte d'invitation la rend dans son sachet. Lecture seule : les structures sont construites en mémoire, rien n'est écrit.
 *
 * Les comportements du panneau et de la page d'acceptation d'une invitation de groupe sont dans `InviteRoleAi.t.php`.
 *
 * Lancement : dev/test package=structure file=InviteRoleSentinelAi
 */
class InviteRoleSentinelAiTest extends \Test {

	public function testEveryStructureTypeWelcomesWithItsMascot(): void {

		$classes = [];

		// Les classes se chargent à la demande : elles se relèvent par les fichiers du package, puis
		// l'héritage se lit par réflexion, `extends` compris.
		foreach(glob(\Package::getPath('structure').'/ui/*.u.php') as $file) {

			$class = 'structure\\'.basename($file, '.u.php').'Ui';

			if(class_exists($class) === FALSE) {
				continue;
			}

			$reflection = new \ReflectionClass($class);

			if($reflection->implementsInterface(StructureCommonUi::class) and $reflection->isInstantiable()) {
				$classes[] = '\\'.$class;
			}

		}

		$reached = [];

		foreach(Structure::model()->getPropertyEnum('type') as $type) {

			$communityTypes = ($type === Structure::COMMUNITY) ? Structure::model()->getPropertyEnum('communityType') : [NULL];

			foreach($communityTypes as $communityType) {

				$eStructure = new Structure(['id' => 1, 'type' => $type, 'communityType' => $communityType, 'name' => 'zzTest '.$type.' '.$communityType]);
				$class = StructureUi::getClass($eStructure);
				$reached[] = $class;

				$mascot = $class::getWelcomeMascot();
				$card = InviteUi::getCard($eStructure, 'sachet', 'titre', ['ligne']);

				$this->assertTrue(is_file(\Package::getPath('main').'/static/image/friends/'.$mascot.'.svg'), $class.' répond une mascotte qui existe : '.$mascot);
				$this->assertTrue(str_contains($card, '<div class="util-seed-head">'.\Asset::svg('main', 'friends/'.$mascot.'.svg').'<div class="util-seed-name">sachet</div>'), 'l\'invitation d\'une structure '.$class.' rend sa mascotte '.$mascot.' dans son sachet');

			}

		}

		$unreached = array_values(array_diff($classes, $reached));
		$this->assertEquals([], $unreached, 'classe de type du package qu\'aucun type de structure n\'atteint : '.implode(', ', $unreached));

		$outside = array_values(array_unique(array_diff($reached, $classes)));
		$this->assertEquals([], $outside, 'type de structure qui atteint une classe que le package ne porte pas : '.implode(', ', $outside));

	}

}
?>
