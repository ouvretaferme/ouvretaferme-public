<?php
namespace learning;

/**
 * Les formations vivent dans la base commune `ouvretaferme` : elles se lisent en dehors de toute
 * ferme, depuis le catalogue, et une même formation est suivie par des lecteurs de fermes
 * différentes.
 *
 * Deux contrats en découlent, qu'aucun code du package ne peut rappeler à qui ajoutera une colonne
 * dans un lot suivant :
 * - aucune propriété ne pointe vers un package d'une base `structure_<id>` (la liste de
 *   `StructureSetting::getPackages()`). Un id de ces tables n'est unique que dans sa structure : une
 *   ligne de formation qui le porterait désignerait une ligne différente dans chaque ferme, et la
 *   seule façon de la lire serait la jointure entre bases que le dépôt interdit ;
 * - aucun entier ne porte une référence en douce. Un `int32` nommé `thirdParty` ou `operationId`
 *   échappe au premier contrôle, qui ne voit que les délégués, et rouvre exactement le même trou.
 *
 * Les délégués qui sortent du package vers la base commune sont permis, mais déclarés un par un
 * avec leur raison : la déclaration dit ce que la formation emprunte ailleurs, et devient fausse
 * quand la propriété disparaît ou change de cible.
 *
 * Lancement : dev/test package=learning file=LearningBaseSentinel
 */
class LearningBaseSentinelTest extends \Test {

	/**
	 * Les délégués du package vers un autre package, tous de la base commune, et pourquoi.
	 */
	const DELEGATES = [
		'Announcement.user' => ['user\\User', 'le compte prévenu de l\'ouverture de la formation, clé de l\'annonce avec la formation : il ne l\'est qu\'une fois'],
		'Attachment.createdBy' => ['user\User', 'le compte qui a déposé le fichier, posé par le cadriciel'],
		'Comment.createdBy' => ['user\User', 'l\'auteur du message, posé par le cadriciel ; seul à le modifier'],
		'Comment.readBy' => ['user\User', 'le membre de l\'équipe qui a lu le commentaire le premier, écrit par le Lib seul'],
		'Course.community' => ['structure\Structure', 'le groupe auteur, un réseau ou un groupe de formation ; vide pour une formation de l\'équipe'],
		'Course.createdBy' => ['user\User', 'le compte qui a créé la formation, posé par le cadriciel'],
		'DateEnrollment.attendedBy' => ['user\User', 'le membre de l\'équipe qui a pointé la présence, écrit par le Lib seul ; il ne sort pas par l\'API'],
		'DateEnrollment.user' => ['user\User', 'la personne inscrite à la date, clé de la ligne avec la date ; vide pour une inscription par la page publique, jusqu\'à la connexion d\'un compte à la même adresse'],
		'DateEnrollment.structure' => ['structure\Structure', 'la ferme au nom de laquelle la personne s\'inscrit, vide à titre personnel ; elle part dans l\'export CSV du réseau'],
		'DateSheet.createdBy' => ['user\User', 'le compte qui a déposé la feuille signée, posé par le cadriciel'],
		'Enrollment.user' => ['user\User', 'le lecteur inscrit'],
		'Enrollment.structure' => ['structure\Structure', 'la ferme au nom de laquelle le lecteur suit la formation'],
		'Faq.community' => ['structure\Structure', 'le réseau ou le groupe de formation dont c\'est la FAQ, toujours renseigné'],
		'Progress.user' => ['user\User', 'le lecteur, clé de la ligne avec la leçon'],
		'Progress.checkedBy' => ['user\User', 'l\'encadrant qui valide une leçon à valider, écrit par le lot de l\'évaluation'],
		'Reading.user' => ['user\User', 'le stagiaire qui lit, clé de la séance avec la formation et la leçon'],
		'Response.user' => ['user\User', 'le lecteur qui a répondu, clé de la dernière tentative avec la question'],
		'Review.user' => ['user\User', 'l\'auteur de l\'avis quand il a un compte, clé de l\'avis avec la formation ; vide pour un avis donné par le lien d\'une inscription sans compte, jusqu\'à la connexion d\'un compte à la même adresse'],
		'Review.reportedBy' => ['user\User', 'le membre du réseau qui a signalé l\'avis, écrit par le Lib seul'],
		'Review.moderatedBy' => ['user\User', 'le membre de l\'équipe de Ouvretaferme qui a gardé, masqué ou réaffiché l\'avis, écrit par le Lib seul'],
		'Review.answeredBy' => ['user\User', 'le membre du réseau ou de l\'équipe qui a répondu à l\'avis, écrit par le Lib seul'],
		'Review.readBy' => ['user\User', 'le membre du réseau qui a lu l\'avis le premier dans la boite des avis, écrit par le Lib seul'],
		'Share.section' => ['structure\Section', 'la promotion à qui la formation est ouverte, dans la même base commune ; une promotion supprimée laisse un partage que les lectures écartent par jointure'],
		'Share.createdBy' => ['user\User', 'le compte qui a partagé la formation, posé par le cadriciel'],
		'Supervisor.community' => ['structure\Structure', 'le réseau ou le groupe de formation qui saisit l\'encadrant, une fois pour toutes ses formations ; vide pour un encadrant de l\'équipe, comme Course.community'],
	];

	private function getModules(): array {

		$modules = [];

		foreach(glob(__DIR__.'/../module/*.m.php') as $path) {

			$module = basename($path, '.m.php');
			$class = __NAMESPACE__.'\\'.$module.'Model';

			$modules[$module] = new $class();

		}

		return $modules;

	}

	/**
	 * Les noms qu'une référence prendrait : celui de chaque module du dépôt, et tout nom en `Id`.
	 */
	private function getReferenceNames(): array {

		$names = [];

		foreach(\Package::getList() as $package => $app) {
			foreach(glob(LIME_DIRECTORY.'/'.$app.'/'.$package.'/module/*.m.php') as $path) {
				$names[] = lcfirst(basename($path, '.m.php'));
			}
		}

		return array_unique($names);

	}

	private function isReferenceName(string $property, array $names): bool {

		return (
			in_array($property, $names, TRUE) or
			preg_match('/[a-z]Id$/', $property) === 1
		);

	}

	public function testNoDelegateToStructureBase(): void {

		$found = [];

		foreach($this->getModules() as $module => $m) {

			foreach($m->getProperties() as $property) {

				$target = $m->getPropertyToModule($property);

				if(
					$target !== NULL and
					in_array(strstr($target, '\\', TRUE), \structure\StructureSetting::getPackages(), TRUE)
				) {
					$found[] = $module.'.'.$property.' → '.$target;
				}

			}

		}

		$this->assertEquals([], $found, 'délégués vers une base structure_<id>, interdits : '.implode(', ', $found));

	}

	public function testNoIntegerReference(): void {

		$names = $this->getReferenceNames();

		$found = [];

		foreach($this->getModules() as $module => $m) {

			foreach($m->getProperties() as $property) {

				if(
					str_starts_with($m->getPropertyType($property), 'int') and
					$this->isReferenceName($property, $names)
				) {
					$found[] = $module.'.'.$property.' ('.$m->getPropertyType($property).')';
				}

			}

		}

		$this->assertEquals([], $found, 'entiers nommés comme une référence, à écrire en délégué de la base commune : '.implode(', ', $found));

	}

	/**
	 * Le contrôle des entiers reconnaît-il ce qu'il doit refuser ? Sans cette preuve, un motif trop
	 * étroit le laisserait vert pour toujours.
	 */
	public function testReferenceNameDetection(): void {

		$names = $this->getReferenceNames();

		$this->assertEquals(
			[TRUE, TRUE, TRUE, TRUE, FALSE, FALSE],
			array_map(fn(string $property) => $this->isReferenceName($property, $names), ['thirdParty', 'operation', 'structure', 'saleId', 'minutes', 'position'])
		);

	}

	public function testDelegateIsDeclared(): void {

		$found = [];

		foreach($this->getModules() as $module => $m) {

			foreach($m->getProperties() as $property) {

				$target = $m->getPropertyToModule($property);

				if(
					$target === NULL or
					str_starts_with($target, __NAMESPACE__.'\\')
				) {
					continue;
				}

				$key = $module.'.'.$property;

				if(
					isset(self::DELEGATES[$key]) === FALSE or
					self::DELEGATES[$key][0] !== $target
				) {
					$found[] = $key.' → '.$target;
				}

			}

		}

		$this->assertEquals([], $found, 'délégués hors du package sans déclaration dans DELEGATES : '.implode(', ', $found));

	}

	public function testDeclarationIsStillTrue(): void {

		$modules = $this->getModules();

		$stale = [];

		foreach(self::DELEGATES as $key => [$target]) {

			[$module, $property] = explode('.', $key);

			if(
				isset($modules[$module]) === FALSE or
				in_array($property, $modules[$module]->getProperties(), TRUE) === FALSE or
				$modules[$module]->getPropertyToModule($property) !== $target
			) {
				$stale[] = $key.' → '.$target;
			}

		}

		$this->assertEquals([], $stale, 'déclarations de DELEGATES devenues fausses : '.implode(', ', $stale));

	}

}
?>
