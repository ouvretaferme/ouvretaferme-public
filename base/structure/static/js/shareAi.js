class StructureShareOnboarding extends MainOnboarding {

	// MainOnboarding fait avancer l'assistant sur Entrée dans un champ, sans soumettre le
	// formulaire : la saisie de l'étape serait perdue. Les deux avances voulues — passer l'étape,
	// et l'enregistrement réussi — appellent goTo() directement.
	static next() {
	}

}

document.delegateEventListener('click', '.community-share [data-action="community-share-skip"]', function() {
	StructureShareOnboarding.goTo(2);
});

document.delegateEventListener('click', '.community-share [data-action="production-toggle"]', function() {

	this.closest('.production-item').classList.toggle('production-item-collapsed');
	this.classList.toggle('selected');

});

// Le libellé replié est le métier, qui n'existe que dans le select : replier une ligne dont on
// vient de changer le métier laisserait l'ancien nom à l'écran.
document.delegateEventListener('change', '.community-share .production-item-fqn select', function() {

	const item = this.closest('.production-item');
	const label = item.qs('.production-item-toggle-label');

	if(label !== null) {
		label.innerHTML = this.value === '' ? label.dataset.empty : this.qs('option:checked').innerHTML;
	}

});
