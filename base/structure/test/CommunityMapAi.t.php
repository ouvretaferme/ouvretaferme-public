<?php
namespace structure;

/**
 * La carte des producteurs d'un groupe et le lieu demandé à l'entrée (lot L11 du chantier
 * mes-producteurs).
 *
 * Deux contrats :
 * - à l'entrée dans un groupe, chaque ferme partageable arrive avec son `System`, et le formulaire ne
 *   demande le lieu que pour une ferme qui n'en a pas, rouvert par la ferme cochée quand il y en a
 *   plusieurs ;
 * - la carte rend un point par ferme située, dans l'ordre `[lng, lat]` que mapbox-gl attend, et compte
 *   les fermes qu'elle ne montre pas.
 *
 * Décor : le groupe silencieux G5, où le lecteur principal administre des fermes qu'il n'a pas encore
 * partagées (F6, F7, F9, F11, et les copies que DuplicateAi pose à son nom), et le groupe partageant
 * G4 pour la carte. Le lieu est posé en mémoire sur les fermes à partager, et en base sur celles de la
 * carte, dans la transaction de `CommunityRouteAiTest`.
 *
 * ⚠️ Le nombre de contrôles suit donc DuplicateAi : deux de plus par ferme à partager qu'il pose.
 *
 * Lancement : dev/test package=structure file=CommunityMapAi
 */
class CommunityMapAiTest extends CommunityRouteAiTest {

	const LNG_LAT = [4.8357, 45.764];

	private function getInvite(): Invite {

		$eStructure = $this->getStructure($this->communitySilent);

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user, \user\User::getSelection()));

		$eInvite = new Invite([
			'id' => 1,
			'key' => 'zztestl11carte',
			'structure' => $eStructure,
			'type' => Invite::MEMBER,
			'memberRole' => RoleLib::getOne($eStructure, 'member'),
			'memberSection' => new Section(),
			'email' => NULL,
			'status' => Invite::PENDING,
			'expiresAt' => NULL,
			'limit' => NULL,
			'used' => 0,
		]);

		InviteLib::prepareShare($eInvite);

		return $eInvite;

	}

	private function getShare(Invite $eInvite): string {
		return new \ReflectionMethod(InviteUi::class, 'getShare')->invoke(new InviteUi(), new \util\FormUi(), $eInvite);
	}

	public function testShareableCarriesItsSystem(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->getInvite()['cStructureShareable'];

		$this->assertTrue($cStructure->notEmpty(), 'le témoin : le lecteur principal a des fermes à partager avec le groupe silencieux');

		foreach($cStructure as $eStructure) {
			$this->assertTrue($eStructure['eSystem']->notEmpty(), 'la ferme '.$eStructure['id'].' arrive avec son système');
			$this->assertEquals($eStructure['id'], $eStructure['eSystem']['structure']['id'], 'et c\'est le sien, dont l\'autocomplétion du lieu lit la structure');
		}

		\user\ConnectionLib::setOnline(new \user\User());

	}

	public function testPlaceAskedOnlyForUnplacedFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eInvite = $this->getInvite();
		$cStructure = $eInvite['cStructureShareable'];

		if($cStructure->count() < 2) {
			$this->assertFail('le décor doit offrir au moins deux fermes à partager, il en offre '.$cStructure->count());
			return;
		}

		$eStructureUnplaced = $cStructure->first();
		$eStructurePlaced = $cStructure->find(fn($eStructure) => $eStructure['id'] !== $eStructureUnplaced['id'], limit: 1);

		$eStructureUnplaced['eSystem']['place'] = NULL;
		$eStructureUnplaced['eSystem']['placeLngLat'] = NULL;
		$eStructurePlaced['eSystem']['place'] = 'Lyon';
		$eStructurePlaced['eSystem']['placeLngLat'] = self::LNG_LAT;

		// Une seule ferme, sans lieu : la question est posée.
		$eInvite['cStructureShareable'] = new \Collection([$eStructureUnplaced['id'] => $eStructureUnplaced]);
		InviteLib::fillPlaced($eInvite);
		$html = $this->getShare($eInvite);

		$this->assertTrue(str_contains($html, 'data-autocomplete-field="place"'), 'une ferme seule sans lieu : le champ du lieu est posé');

		// Une seule ferme, déjà située : rien n'est demandé.
		$eInvite['cStructureShareable'] = new \Collection([$eStructurePlaced['id'] => $eStructurePlaced]);
		InviteLib::fillPlaced($eInvite);
		$html = $this->getShare($eInvite);

		$this->assertFalse(str_contains($html, 'data-autocomplete-field="place"'), 'une ferme seule déjà située : le champ n\'est pas posé');

		// Deux fermes : seule la ferme sans lieu rouvre la question.
		$eInvite['cStructureShareable'] = new \Collection([
			$eStructureUnplaced['id'] => $eStructureUnplaced,
			$eStructurePlaced['id'] => $eStructurePlaced
		]);
		InviteLib::fillPlaced($eInvite);
		$html = $this->getShare($eInvite);

		$this->assertEquals(1, substr_count($html, 'data-place-missing'), 'deux fermes : un seul bouton porte la marque');
		$this->assertTrue((bool)preg_match('/<input[^>]*value="'.$eStructureUnplaced['id'].'"[^>]*data-place-missing|<input[^>]*data-place-missing[^>]*value="'.$eStructureUnplaced['id'].'"/', $html), 'et c\'est celui de la ferme sans lieu');

		// Deux fermes déjà situées : rien.
		$eStructureUnplaced['eSystem']['place'] = 'Lyon';
		$eStructureUnplaced['eSystem']['placeLngLat'] = self::LNG_LAT;
		InviteLib::fillPlaced($eInvite);
		$html = $this->getShare($eInvite);

		$this->assertFalse(str_contains($html, 'data-place-missing'), 'deux fermes situées : aucun bouton ne porte la marque');

		\user\ConnectionLib::setOnline(new \user\User());

	}


	/**
	 * Le lieu posté à l'entrée n'est retenu que pour une ferme sans lieu, et jamais sur un champ vide.
	 * Le cas « déjà située » poste un lieu, et non un champ vide : c'est le champ masqué que le
	 * formulaire envoie quand même.
	 */
	public function testJoinPlaceOnlyForUnplacedFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eInvite = $this->getInvite();
		$eStructure = $eInvite['cStructureShareable']->first();
		$input = ['place' => 'Lyon', 'placeLngLat' => json_encode(self::LNG_LAT)];

		$eStructure['eSystem']['place'] = NULL;
		$eStructure['eSystem']['placeLngLat'] = NULL;
		InviteLib::fillPlaced($eInvite);

		$eSystem = InviteLib::preparePlace($eStructure, $input);

		$this->assertTrue($eSystem->notEmpty(), 'une ferme sans lieu reçoit celui qu\'elle poste');
		$this->assertEquals('Lyon', $eSystem['place'], 'la commune');
		$this->assertEquals(self::LNG_LAT, $eSystem['placeLngLat'], 'et le point, dans l\'ordre [lng, lat]');

		$eStructure['eSystem']['place'] = NULL;
		$eStructure['eSystem']['placeLngLat'] = NULL;
		InviteLib::fillPlaced($eInvite);

		$this->assertTrue(InviteLib::preparePlace($eStructure, ['place' => '', 'placeLngLat' => ''])->empty(), 'un champ vide ne pose rien : le lieu reste facultatif');

		$eStructure['eSystem']['place'] = 'Grenoble';
		$eStructure['eSystem']['placeLngLat'] = [5.7245, 45.1885];
		InviteLib::fillPlaced($eInvite);

		$this->assertTrue(InviteLib::preparePlace($eStructure, $input)->empty(), 'une ferme déjà située garde son lieu, même quand le champ masqué en poste un');
		$this->assertEquals('Grenoble', $eStructure['eSystem']['place'], 'et il reste intact');

		\user\ConnectionLib::setOnline(new \user\User());

	}

	private function setPlace(Structure $eStructure, ?array $placeLngLat): void {

		System::model()
			->whereStructure($eStructure)
			->update([
				'place' => ($placeLngLat === NULL) ? NULL : 'Lyon',
				'placeLngLat' => $placeLngLat
			]);

	}

	/**
	 * La carte montre les fermes des membres que la liste montre, pas les fermes projet, et compte
	 * celles qu'elle ne situe pas. Une ferme est située, l'autre non, pour que les deux côtés du
	 * partage aient chacun un cas.
	 */
	public function testMapLocatesMemberFarms(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		// Un groupe naît avec son parcours de démarrage allumé (`FeatureLib::createForStructure()`), et le
		// parcours remplace la carte : éteint ici, dans la transaction, pour lire la carte, puis rallumé
		// plus bas pour vérifier qu'il la remplace
		FeatureLib::updateFeature($this->getStructure($this->communitySharing), 'hasStart', FALSE);
		$this->forgetStatics();

		$data = $this->run('fermes', $this->user);
		$cStructure = new \Collection();

		foreach([$data->cStructureMine, $data->cStructureMember] as $cStructureList) {
			foreach($cStructureList as $eStructure) {
				$cStructure[$eStructure['id']] = $eStructure;
			}
		}

		if($cStructure->count() < 2) {
			$this->assertFail('le décor doit offrir au moins deux fermes au groupe partageant, il en offre '.$cStructure->count());
			return;
		}

		$eStructureLocated = $cStructure->first();
		$eStructureUnlocated = $cStructure->find(fn($eStructure) => $eStructure['id'] !== $eStructureLocated['id'], limit: 1);

		$this->setPlace($eStructureLocated, self::LNG_LAT);
		$this->setPlace($eStructureUnlocated, NULL);

		$data = $this->run('fermes', $this->user);

		$this->assertTrue($data->mapApply, 'la liste des fermes d\'un groupe qui en héberge porte la carte');

		$ids = array_merge($data->cStructureMine->getIds(), $data->cStructureMember->getIds());

		$this->assertEquals(count($ids), $data->mapTotal, 'la carte compte les fermes que la liste montre');
		$this->assertEquals([], array_diff($data->cStructureLocated->getIds(), $ids), 'et ne situe aucune ferme hors de la liste');
		$this->assertEquals([], array_intersect($data->cStructureLocated->getIds(), $data->cStructureProject->getIds()), 'ni aucune ferme projet');

		$this->assertTrue($data->cStructureLocated->offsetExists($eStructureLocated['id']), 'la ferme qui a un lieu est située');
		$this->assertFalse($data->cStructureLocated->offsetExists($eStructureUnlocated['id']), 'celle qui n\'en a pas ne l\'est pas');

		foreach($data->cStructureLocated as $eStructure) {
			$this->assertTrue($eStructure['eSystem']['placeLngLat'] !== NULL, 'la ferme '.$eStructure['id'].' est située sur un point');
		}

		FeatureLib::updateFeature($this->getStructure($this->communitySharing), 'hasStart', TRUE);
		$this->forgetStatics();

		$dataHome = $this->run('', $this->user, ['demarrage' => '1']);

		$this->assertTrue($dataHome->start !== NULL, 'le témoin : sur l\'accueil, le parcours de démarrage est ouvert');

		$dataStart = $this->run('fermes', $this->user, ['demarrage' => '1']);

		$this->assertTrue($dataStart->start === NULL, 'le parcours de démarrage ne tient que l\'accueil');
		$this->assertEquals($data->cStructureLocated->getIds(), $dataStart->cStructureLocated->getIds(), 'et sa carte se lit comme hors mise en route');

		$html = new StructureCommunityUi()->getMap($data->cStructureLocated, $data->mapTotal);

		preg_match('/data-points="([^"]*)"/', $html, $match);
		$points = json_decode(html_entity_decode($match[1] ?? '[]'), TRUE);

		$this->assertEquals($data->cStructureLocated->count(), count($points), 'un point par ferme située');
		$this->assertTrue(in_array(self::LNG_LAT, array_column($points, 'lngLat'), TRUE), 'dans l\'ordre [lng, lat] que mapbox-gl attend');

		preg_match('/data-locale="([^"]*)"/', $html, $match);
		$locale = json_decode(html_entity_decode($match[1] ?? '{}'), TRUE);

		$this->assertEquals(['ScrollZoomBlocker.CtrlMessage', 'ScrollZoomBlocker.CmdMessage', 'TouchPanBlocker.Message', 'NavigationControl.ZoomIn', 'NavigationControl.ZoomOut'], array_keys($locale), 'les messages de cooperativeGestures et les infobulles du zoom, sous les clés que lit mapbox-gl');
	}

}
