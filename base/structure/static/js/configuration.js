class StructureConfiguration {

	static changeHasVat(target) {

		const hasVat = !!parseInt(target.value);

		const form = target.firstParent('form');

		form.qsa('[data-wrapper="vatFrequency"], [data-wrapper="vatChargeability"], [data-wrapper="vatNumber"], [data-wrapper="legalCategory"]', wrapper => hasVat ?
			wrapper.classList.remove('hide') :
			wrapper.classList.add('hide'));

		form.qsa('[data-wrapper="associates"]', wrapper => hasVat && parseInt(qs('[name="legalCategory"]').value) === 6533 ? // Pour les GAEC uniquement
			wrapper.classList.remove('hide') :
			wrapper.classList.add('hide'));

	}

	static toggleEffectiveAt(field, target, origin) {

		if(origin.length === 0) {
			return;
		}

		let value, valueOrigin;

		if(field === 'hasVat') {

			value = !!parseInt(target.value);
			valueOrigin = !!parseInt(origin);

		} else {

			value = qs('[name="vatChargeability"]:checked').value;
			valueOrigin = origin;

		}

		const form = target.firstParent('form');
		const fieldName = field + 'EffectiveAt';

		if(!form.qs('[data-wrapper="' + fieldName + '"]')) {
			return;
		}

		if(value === valueOrigin) {
			form.qs('[data-wrapper="' + fieldName + '"]').classList.add('hide');
		} else {
			form.qs('[data-wrapper="' + fieldName + '"]').classList.remove('hide');
		}
	}

	static weighEffectiveAt(field, target, structureId) {

		const form = target.firstParent('form');
		const value = form.qs('[name="'+ field +'"]:checked').value;
		const effectiveAt = form.qs('[name="'+ field +'EffectiveAt"]').value;

		// Une date tapée au clavier lance une pesée par frappe, et la plus ancienne peut répondre la dernière : seule
		// celle du dernier geste s'applique. Chaque pesée a son verrou, sinon la suivante serait refusée (`LOCK_FAILED`)
		// et la case resterait cachée sous un refus de « Valider ».
		const group = form.qs('[data-wrapper="'+ field +'HistoryConfirmed"]');
		const sequence = String(Number(group.dataset.sequence ?? 0) + 1);
		group.dataset.sequence = sequence;

		new Ajax.Query(form)
			.lockAs('weigh-'+ field +'-'+ sequence)
			.method('get')
			.url('/structure/configuration:vatWeigh?id='+ structureId +'&field='+ field +'&value='+ encodeURIComponent(value) +'&effectiveAt='+ encodeURIComponent(effectiveAt))
			.fetch()
			.then((json) => {

				if(group.dataset.sequence !== sequence) {
					return;
				}

				form.qs('#configuration-vat-weigh-'+ field).renderInner(json.weigh);
				group.classList.toggle('hide', json.weigh === '');

				// La date qui supprime ces choix se montre avec eux, même quand l'écran ne l'avait pas proposée
				if(json.weigh !== '') {
					form.qs('[data-wrapper="'+ field +'EffectiveAt"]').classList.remove('hide');
				}

			});

	}

	static changeVatChargeability() {

		const vatChargeability = qs('[name="vatChargeability"]:checked').value;

		if(vatChargeability === 'cash') {

			qs('[name="vatDebitsOption"][value="0"]').checked = true;
			qs('[data-wrapper="vatDebitsOption"]').hide();
			qs('[data-field="vatDebitsOption"]').setAttribute('disabled', 'disabled');

		} else {

			qs('[data-wrapper="vatDebitsOption"]').removeHide();
			qs('[data-field="vatDebitsOption"]').removeAttribute('disabled');

		}

	}
}
