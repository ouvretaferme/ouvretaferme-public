<?php
namespace structure;

/**
 * Les sous-groupes d'un groupe : promotions d'un organisme de formation, groupes d'un réseau,
 * antennes d'une structure comptable.
 *
 * Ils rangent, ils ne ferment rien. Une personne appartient à autant de sous-groupes qu'il le faut,
 * et son adhésion ne change pas de nature selon qu'elle en porte un ou aucun.
 *
 * La liste vit sur le sous-groupe et non l'inverse, pour deux raisons mesurées : on entre dans une
 * promotion avant d'avoir une ferme à partager, donc `Affiliation` ne peut pas la porter — 5 groupes
 * sur 8 en base portent déjà un membre sans aucun rattachement ; et une colonne posée sur `Member`
 * serait morte sur les 7 585 adhésions à des fermes. Le sens de la relation est celui de
 * `selling\Customer.groups`, retourné.
 *
 * Elle porte des ids d'utilisateur et non d'adhésion : c'est la personne que le sous-groupe range.
 * Son id traverse un départ suivi d'un retour, et c'est déjà par l'utilisateur que le groupe
 * désigne qui a apporté une ferme (`Affiliation.user`), ce qui évite une traduction à chaque
 * filtre.
 */
class SectionLib extends SectionCrud {

	// `status` n'est pas de la création : un sous-groupe naît ouvert, et le formulaire de création
	// ne pose qu'un nom. L'y laisser ferait échouer build() sur un champ jamais posté.
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	// `status` n'est pas du même geste que le nom : clore et rouvrir se font d'un clic sur la ligne
	// et passent par doUpdateStatus, comme structure\Member. Le laisser ici ferait échouer le
	// renommage sur un champ que son formulaire ne poste pas.
	public static function getPropertiesUpdate(?string $origin = NULL): \Closure|array {
		return ['name'];
	}

	public static function getByStructure(Structure $eStructure, bool $onlyOpen = FALSE): \Collection {

		return Section::model()
			->select(Section::getSelection())
			->whereStructure($eStructure)
			->where(fn() => 'status = '.Section::model()->format(Section::OPEN), if: $onlyOpen)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Les sous-groupes de ce groupe où cette personne est rangée.
	 *
	 * `JSON_CONTAINS` et non un `LIKE` : « 12 » se retrouve dans « 120 » et dans « 112 », et le
	 * filtre rendrait des sous-groupes auxquels elle n'appartient pas.
	 */
	public static function getByUser(\user\User $eUser, Structure $eStructure): \Collection {

		$eUser->expects(['id']);

		return Section::model()
			->select(Section::getSelection())
			->whereStructure($eStructure)
			->where('JSON_CONTAINS('.Section::model()->field('users').', \''.$eUser['id'].'\')')
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Ranger une personne dans les sous-groupes cochés, et l'ôter des autres.
	 *
	 * ⚠️ `$cSection` est la liste que **l'écran a proposée**, et elle ne se relit pas ici. Relue,
	 * elle rendait toujours tous les sous-groupes du groupe : sur un groupe dont la fonctionnalité
	 * est éteinte, le champ n'est pas rendu, `POST('sections')` vaut `[]`, et chaque enregistrement
	 * d'un rôle sortait la personne de tous ses sous-groupes. Un formulaire sans le champ n'est pas
	 * un formulaire où tout est décoché. Symétriquement, un POST forgé ne range plus personne quand
	 * rien n'a été proposé.
	 *
	 * ⚠️ Toujours par `associateUser()` / `dissociateUser()` : les ids vivent dans une colonne json,
	 * aucun outil dérivé du modèle ne les voit, et une écriture montée à la main écraserait la
	 * liste des autres.
	 */
	public static function updateUserSections(\structure\Member $eMember, \Collection $cSection, array $posted): void {

		$eMember->expects(['user', 'structure']);

		if($cSection->empty()) {
			return;
		}

		$posted = array_map('intval', $posted);

		foreach($cSection as $eSection) {

			if(in_array($eSection['id'], $posted, TRUE)) {
				self::associateUser($eSection, $eMember['user']);
			} else {
				self::dissociateUser($eMember['user'], $eMember['structure'], $eSection);
			}

		}

	}

	/**
	 * Les utilisateurs de chaque sous-groupe, en une seule lecture de l'équipe du groupe.
	 *
	 * Les ids vivent dans une colonne `json` : aucune jointure ne peut les agréger, et une requête
	 * par sous-groupe en ferait douze pour quatre ans de promotions. La collection reçue est
	 * indexée par id, l'intersection se fait donc en PHP sur des clés.
	 *
	 * Un utilisateur qui a quitté le groupe garde son id dans la liste — le retirer d'un groupe
	 * n'est pas le déranger de sa promotion — mais il ne compte plus, `$cUser` ne portant que les
	 * présents.
	 */
	public static function fillUsers(\Collection $cSection, \Collection $cUser): void {

		foreach($cSection as $eSection) {

			$users = array_flip($eSection['users']);

			$eSection['cUser'] = $cUser->find(fn(\user\User $eUserSection) => isset($users[$eUserSection['id']]));

		}

	}

	/**
	 * Le nombre de fermes que ces sous-groupes rassemblent, sans compter deux fois celle que deux
	 * d'entre eux se partagent.
	 *
	 * C'est ce que voit un membre borné par son rôle : l'union de ses sous-groupes, et non la
	 * somme de leurs lignes.
	 */
	public static function countStructures(Structure $eStructureCommunity, \Collection $cStructure, \Collection $cSection, array $sections, bool $bounded): int {

		$users = self::getUsers($cSection, $sections);

		return $cStructure
			->find(fn(Structure $eStructure) => self::keepsStructure($eStructureCommunity, $eStructure, $users, $bounded))
			->count();

	}

	public static function getUsers(\Collection $cSection, array $sections): array {

		$users = [];

		foreach($cSection as $eSection) {

			if(in_array($eSection['id'], $sections, TRUE)) {

				foreach($eSection['users'] as $user) {
					$users[$user] = TRUE;
				}

			}

		}

		return $users;

	}

	/**
	 * Une ferme reste sous le filtre quand l'un de ceux qui l'ont apportée y est rangé. Pour un
	 * lecteur borné, celle de l'équipe y reste aussi là où la portée des étudiants se réduit aux
	 * fermes de l'équipe : l'équipe n'est rangée dans aucun sous-groupe.
	 */
	public static function keepsStructure(Structure $eStructureCommunity, Structure $eStructure, array $users, bool $bounded): bool {

		if(
			$bounded and
			CommunityRightAiLib::restrictsReach($eStructureCommunity, 'shareProduction') and
			in_array(Community::STAFF, $eStructure['audiencesBringing'], TRUE)
		) {
			return TRUE;
		}

		return $eStructure['cUser']
			->find(fn(\user\User $eUser) => isset($users[$eUser['id']]))
			->notEmpty();

	}

	/**
	 * Pose sur chaque adhésion les sous-groupes qui la rangent, en une seule passe sur la liste.
	 *
	 * C'est la primitive dont vivent le sélecteur, ses effectifs et les badges de la carte du
	 * membre : les ids étant dans une colonne `json`, aucune jointure ne peut les rendre, et une
	 * requête par sous-groupe en ferait douze au bout de quatre ans de promotions.
	 */
	public static function fillSections(\Collection $ccMember, \Collection $cSection): void {

		$sections = [];

		foreach($cSection as $eSection) {
			foreach($eSection['users'] as $user) {
				$sections[$user][$eSection['id']] = $eSection;
			}
		}

		foreach($ccMember as $cMember) {
			foreach($cMember as $eMember) {
				$eMember['cSection'] = new \Collection($sections[$eMember['user']['id']] ?? []);
			}
		}

	}

	/**
	 * La portée du membre connecté sur les sous-groupes de ce groupe.
	 *
	 * `NULL` veut dire tout le groupe ; un tableau d'ids veut dire ces sous-groupes et rien
	 * d'autre. C'est la seule lecture de la portée, et tous les écrans la consomment en page.
	 *
	 * ⚠️ La borne est portée par le **rôle** et jamais par le type du groupe : un organisme de
	 * formation qui veut que ses étudiants voient tout n'a qu'à changer leur rôle, et un réseau
	 * qui veut borner les siens le peut aussi. Aucun écran de ce lot ne teste `communityType`.
	 *
	 * L'administrateur court-circuite la portée comme il court-circuite le reste : son rôle porte
	 * `ALL` par défaut, mais on ne dépend pas de ce défaut, qui se change à l'écran des rôles.
	 */
	public static function getScope(Structure $eStructure): ?array {

		$eMember = $eStructure->getMember();

		if(
			$eMember->empty() or
			$eMember->isAdmin() or
			$eMember['role']['sections'] === Role::ALL
		) {
			return NULL;
		}

		return self::getByUser($eMember['user'], $eStructure)->getIds();

	}

	/**
	 * Le sous-groupe demandé par l'URL.
	 *
	 * Un id inconnu rend un élément vide, donc « toutes », et sans message : ce n'est pas une
	 * erreur de l'utilisateur, c'est un lien qui a vieilli. Un sous-groupe clos reste sélectionnable
	 * — clore range, il ne ferme rien.
	 */
	public static function getSelected(\Collection $cSection, ?string $section): Section {

		if($section === NULL or ctype_digit($section) === FALSE) {
			return new Section();
		}

		return $cSection[(int)$section] ?? new Section();

	}

	public static function create(Section $e): void {

		$e->expects(['structure']);

		Section::model()->beginTransaction();

			try {
				parent::create($e);
			} catch(\DuplicateException) {

				Section::model()->rollBack();
				Section::fail('name.duplicate');

				return;

			}

			// Le compteur du groupe décide si l'écran des fermes existe : il se tient dans la même
			// transaction que la ligne qu'il compte, sinon une création interrompue laisserait un
			// onglet qui ment.
			CommunityLib::incrementSections($e['structure'], 1);

		Section::model()->commit();

	}

	public static function update(Section $e, array $properties): void {

		try {
			parent::update($e, $properties);
		} catch(\DuplicateException) {
			Section::fail('name.duplicate');
		}

	}

	/**
	 * L'invitation en attente perd sa destination et reste valable : elle fait entrer dans le
	 * groupe, le sous-groupe n'en était que le rangement. Le modèle ne le fera pas à notre place,
	 * le schéma ne portant aucune clé étrangère réelle.
	 */
	public static function delete(Section $e): void {

		$e->expects(['id', 'structure']);

		Section::model()->beginTransaction();

			Invite::model()
				->whereStructure($e['structure'])
				->whereMemberSection($e)
				->update(['memberSection' => NULL]);

			CommunityLib::incrementSections($e['structure'], -1);

			parent::delete($e);

		Section::model()->commit();

	}

	public static function associateUser(Section $eSection, \user\User $eUser): void {

		$eSection->expects(['id']);
		$eUser->expects(['id']);

		Section::model()
			->where('JSON_CONTAINS('.Section::model()->field('users').', \''.$eUser['id'].'\') = 0')
			->whereId($eSection)
			->update([
				'users' => new \Sql(Section::model()->pdo()->api->jsonAdd('users', $eUser['id']))
			]);

	}

	/**
	 * Sans sous-groupe nommé, la personne sort de tous ceux de ce groupe : c'est ce qu'appelle
	 * `MemberLib::delete()`, rien d'autre ne retirant un id rangé dans une colonne `json`.
	 *
	 * La structure est un argument et non une déduction : un id d'utilisateur ne dit pas de quel
	 * groupe on parle, et la même personne peut être rangée dans deux groupes à la fois.
	 *
	 * ⚠️ L'écriture passe un **tableau** de valeurs et non l'élément : `getUpdateCondition()`
	 * (`framework/core/Module.php`) ajoute `AND id = <id>` dès que l'argument est un Element
	 * porteur d'un id, ce qui réduirait silencieusement le retrait à un seul sous-groupe.
	 */
	public static function dissociateUser(\user\User $eUser, Structure $eStructure, Section $eSection = new Section()): void {

		$eUser->expects(['id']);

		Section::model()
			->where('JSON_CONTAINS('.Section::model()->field('users').', \''.$eUser['id'].'\')')
			->whereStructure($eStructure)
			->whereId($eSection, if: $eSection->notEmpty())
			->update([
				'users' => new \Sql(Section::model()->pdo()->api->jsonRemove('users', $eUser['id']))
			]);

	}

}
?>
