<?php
/**
 * Le bloc créé remplace le bloc à écrire du « + », lu, avec son propre « + », et sa ligne se range dans
 * le plan après celle du bloc qui le précède, ou, posé par le « + » de tête, avant celle du bloc qui le
 * suit : le reste de l'écran n'a pas bougé. Sans voisin, c'est le premier bloc d'un objet qui vient
 * d'être enregistré : la liste et le plan entiers prennent leur place, l'objet ayant pu recevoir
 * d'autres blocs depuis que l'écran l'a montré vide (par l'API), et le choix du premier bloc se masque
 * (`BlockUi::getStart()`).
 *
 * Un enfant ajouté rend son parent en entier, sans ligne au plan, qui ne liste que le premier niveau :
 * ses voisins en dépendent tous (les flèches de celui qui était le dernier, la tête d'un onglet, le
 * « + » d'un bloc de chiffres qui atteint son plafond).
 */
new JsonView('doCreate', function($data, AjaxTemplate $t) {

	if($data->eBlockParent->notEmpty()) {
		$t->qs('#block-'.$data->eBlockParent['id'])->outerHtml(new \block\BlockUi()->getItem($data->eBlockParent));
		return;
	}

	$eBlockAfter = $data->e['eBlockAfter'];
	$eBlockBefore = $data->e['eBlockBefore'];

	if(
		$eBlockAfter->empty() and
		$eBlockBefore->empty()
	) {
		$t->qs('#block-list')->innerHtml(new \block\BlockUi()->getList($data->cBlock));
		$t->qs('#block-plan')->outerHtml(new \block\BlockUi()->getPlan($data->cBlock, $data->e['owner'], $data->e['reference']));
		$t->qs('#block-start')->hide();
		return;
	}

	$planRow = new \block\BlockUi()->getPlanRow($data->cBlock[$data->e['id']]);

	$t->qs('#block-new')->outerHtml(new \block\BlockUi()->getItem($data->cBlock[$data->e['id']]));

	if($eBlockAfter->notEmpty()) {
		$t->qs('#block-plan-'.$eBlockAfter['id'])->insertAdjacentHtml('afterend', $planRow);
	} else {
		$t->qs('#block-plan-'.$eBlockBefore['id'])->insertAdjacentHtml('beforebegin', $planRow);
	}

	$t->qs('#block-plan .util-counter')->innerHtml((string)$data->cBlock->count());

});

/**
 * Enregistré sans être refermé, le bloc garde à l'écran le texte qu'on vient d'écrire : rien ne
 * change. Refermé, il revient lu, et une section rend aussi sa ligne du plan, la seule qui écrive un
 * titre (`BlockUi::getPlanRow()`) : celle des autres types ne nomme que le type, qui ne change pas.
 * Dans les deux cas, un frère qui a perdu la variante unique que le bloc vient de prendre revient lu
 * à sa place, éclairé pour qu'on voie la marque partir. Un refus revient par la chaîne d'erreur, sous
 * le champ, et le bloc reste ouvert.
 */
new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	if($data->close) {

		$t->qs('#block-'.$data->e['id'])->outerHtml(new \block\BlockUi()->getItem($data->eBlockClosed));

		// Le titre d'un onglet se lit aussi dans la tête de son parent. Les autres enfants n'en ont pas,
		// et une instruction sur un nœud absent ne fait rien.
		if($data->eBlockClosed['parentType'] !== NULL) {
			$t->qs('#block-tab-'.$data->e['id'])->innerHtml(encode($data->eBlockClosed['title']));
		} else if($data->eBlockClosed['type'] === \block\BlockTypeLib::SECTION) {
			$t->qs('#block-plan-'.$data->e['id'])->outerHtml(new \block\BlockUi()->getPlanRow($data->eBlockClosed));
		}

	}

	foreach($data->cBlockReset as $eBlockReset) {
		$t->qs('#block-'.$eBlockReset['id'])->outerHtml(new \block\BlockUi()->getItem($eBlockReset), highlight: TRUE);
	}

});

new JsonView('doIncrementPosition', function($data, AjaxTemplate $t) {

	if($data->eBlockParent->notEmpty()) {
		$t->qs('#block-'.$data->eBlockParent['id'])->outerHtml(new \block\BlockUi()->getItem($data->eBlockParent));
		return;
	}

	$eBlockNeighbour = $data->eBlockNeighbour;

	if($eBlockNeighbour->notEmpty()) {

		$t->qs('#block-'.$eBlockNeighbour['id'])->remove();

		$mode = match($data->increment) {
			-1 => 'afterend',
			1 => 'beforebegin',
		};

		$t->qs('#block-'.$data->e['id'])->insertAdjacentHtml($mode, new \block\BlockUi()->getItem($eBlockNeighbour));
		$t->qs('#block-plan')->outerHtml(new \block\BlockUi()->getPlan($data->cBlock, $data->e['owner'], $data->e['reference']));

	}

});

/**
 * La copie arrive fermée sous l'original, qui reste ouvert : on continue d'écrire là où l'on était.
 */
new JsonView('doDuplicate', function($data, AjaxTemplate $t) {

	if($data->eBlockParent->notEmpty()) {
		$t->qs('#block-'.$data->eBlockParent['id'])->outerHtml(new \block\BlockUi()->getItem($data->eBlockParent));
		return;
	}

	$t->qs('#block-'.$data->e['id'])->insertAdjacentHtml('afterend', new \block\BlockUi()->getItem($data->eBlockCopy));
	$t->qs('#block-plan')->outerHtml(new \block\BlockUi()->getPlan($data->cBlock, $data->e['owner'], $data->e['reference']));

});

/**
 * Le retrait du dernier bloc vide la liste et montre de nouveau le choix du premier bloc
 * (`BlockUi::getStart()`). Une étape retirée rend son parent, ou le retire avec elle quand elle était
 * la dernière.
 *
 * Le bloc retiré laisse voir son départ (`vanish`), sauf dans deux cas où la copie n'aurait pas de
 * place : l'enfant d'un parent rendu en entier, dont la copie se poserait dans le `.block-item`
 * positionné que l'`outerHtml` emporte, et le dernier bloc, qui quitte la liste avec le « + » de
 * tête : elle se vide d'un coup.
 */
new JsonView('doDelete', function($data, AjaxTemplate $t) {

	if($data->eBlockParent->notEmpty()) {
		$t->qs('#block-'.$data->eBlockParent['id'])->outerHtml(new \block\BlockUi()->getItem($data->eBlockParent));
		return;
	}

	if($data->cBlock->empty()) {
		$t->qs('#block-list')->innerHtml('');
		$t->qs('#block-start')->removeHide();
	} else if($data->e['parent']->empty()) {
		$t->qs('#block-'.$data->e['id'])->remove(vanish: TRUE);
	} else {
		$t->qs('#block-'.$data->e['parent']['id'])->remove(vanish: TRUE);
	}

	$t->qs('#block-plan')->outerHtml(new \block\BlockUi()->getPlan($data->cBlock, $data->e['owner'], $data->e['reference']));

});

/**
 * Le bloc coupé revient lu avec le texte d'avant, ou quitte la page s'il n'avait que le « / ». Le
 * texte d'après prend la place de l'aperçu que le script avait posé (`#block-split`), sous le bloc
 * choisi. Une instruction sur un nœud absent ne fait rien : sans texte d'après, le script n'a posé
 * aucun aperçu.
 */
new JsonView('doSplit', function($data, AjaxTemplate $t) {

	if($data->eBlockSplit->notEmpty()) {
		$t->qs('#block-'.$data->e['id'])->outerHtml(new \block\BlockUi()->getItem($data->eBlockSplit));
	} else {
		$t->qs('#block-'.$data->e['id'])->remove();
	}

	if($data->eBlockRest->notEmpty()) {
		$t->qs('#block-split')->outerHtml(new \block\BlockUi()->getItem($data->eBlockRest));
	} else {
		$t->qs('#block-split')->remove();
	}

	$t->qs('#block-plan')->outerHtml(new \block\BlockUi()->getPlan($data->cBlock, $data->e['owner'], $data->e['reference']));

});
?>
