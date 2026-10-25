/**
 * Le dépôt d'un modèle 3D téléversé à la place d'une composition, partagé par l'éditeur de l'avatar (`UserAvatarEditor`)
 * et celui d'un bâtiment du décor (`MapSceneBuilding`), sur le markup de `\scene\UploadUi::getDrop()` : le choix du
 * fichier, le poids jugé avant tout envoi, l'attente, le refus, le débit, « Valider », le modèle vérifié, la case
 * d'engagement et l'envoi.
 *
 * Un script classique, posé par l'éditeur qui s'en sert (`Asset::js('scene', 'upload.js')`, `\scene\UploadUi`) : il lit et
 * écrit la page, et poste le fichier. L'éditeur garde l'aperçu et le pied du panneau : `refresh` est appelé chaque fois que
 * le fichier en attente change, y compris quand un nouveau fichier fait oublier le modèle vérifié (`isVerified()`).
 */
class SceneUploadDrop {

	constructor(node, form, refresh) {

		this.node = node;
		this.form = form;
		this.refresh = refresh;

		// Le fichier choisi qui attend de partir, ou null
		this.file = null;

		// Un envoi attend sa réponse (`send()`)
		this.sending = false;

		this.listeners = [];

		node.qsa('[data-scene-upload-input]', input => this.listeners.push(
			[input, 'change', () => {
				this.chooseFile(input.files[0] ?? null);
				// Le même fichier choisi de nouveau, corrigé entre-temps, doit encore déclencher `change`
				input.value = '';
			}]
		));

		this.listeners.push(
			[node, 'click', event => {
				if(event.target.closest('[data-scene-upload-remove]') !== null) {
					this.forgetFile();
				}
			}],
			// L'ajout du modèle vérifié attend l'engagement
			[node, 'change', event => {
				if(event.target.name === 'commitment') {
					this.refreshAdd();
				}
			}]
		);

		this.listeners.forEach(([target, type, listener]) => target.addEventListener(type, listener));

	}

	// Le fichier glissé sur l'appel, que l'éditeur fait suivre depuis le `ondrop` du dépôt
	drop(event, zone) {

		event.preventDefault();
		MainDrop.leave(zone);

		this.chooseFile(event.dataTransfer.files[0] ?? null);

	}

	// Un seul fichier à la fois : le suivant remplace celui qui attend, celui qui a été refusé ou le modèle vérifié. Le poids
	// se juge ici, avant tout envoi, sous la phrase que le serveur dit pour le même refus
	chooseFile(file) {

		// Un fichier glissé pendant un envoi ne remplace pas celui dont la réponse est attendue
		if(file === null || this.sending) {
			return;
		}

		if(this.isVerified()) {
			this.showVerified(false);
		}

		const row = this.node.qs('template[data-scene-upload-row]').content.firstElementChild.cloneNode(true);
		const size = row.qs('[data-scene-upload-size]');

		row.qs('[data-scene-upload-name]').textContent = file.name;
		size.textContent = this.getSize(size, file.size);

		this.node.qs('.util-drop-list').replaceChildren(row);

		Lime.Alert.hideErrors(this.form);

		if(file.size > parseInt(this.node.dataset.maxBytes)) {
			// Au dixième supérieur, comme le serveur : arrondi au plus près, un fichier à peine trop lourd pèserait le plafond
			const weight = (Math.ceil(file.size / 104857.6) / 10).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
			Lime.Alert.showErrors(this.form, [[this.node.dataset.fail, this.node.dataset.tooHeavy.replace('{size}', weight), 'file']]);
			this.refuseFile();
			return;
		}

		this.file = file;
		this.waitFile(true);

	}

	// Un fichier refusé reste listé avec son refus, sous son nom, et le dépôt revient pour le suivant
	refuseFile() {

		this.node.qs('.util-drop-list > .util-table-row')?.classList.add('form-error');
		this.node.qsa('[data-drop-choose]', label => label.textContent = this.node.dataset.chooseAgain);

		this.file = null;
		this.waitFile(false);

	}

	// Le fichier reste « Non envoyé », sans appel ni bouton. La retenue ne tient qu'à l'écran : la croix ou un nouveau
	// dépôt rouvrent l'envoi, que le serveur refusera de même tant que le débit est atteint ou l'import indisponible
	holdFile() {

		this.file = null;
		this.showFoot(false);

	}

	// La croix d'un fichier en attente, refusé ou vérifié : le dépôt revient vide, et l'éditeur à ce qu'il montrait avant
	forgetFile() {

		if(this.isVerified()) {
			this.showVerified(false);
		}

		this.node.qs('.util-drop-list').replaceChildren();
		this.node.qsa('[data-drop-choose]', label => label.textContent = label.dataset.dropChoose);

		Lime.Alert.hideErrors(this.form);

		this.file = null;
		this.waitFile(false);

	}

	// Un fichier qui attend retire l'appel, le grand bouton du téléphone et l'aide du skill, un seul fichier suffisant, et
	// montre « Valider »
	waitFile(waiting) {

		this.node.qs('.util-drop-head').classList.toggle('hide', waiting);
		this.node.qs('[data-scene-upload-phone]').classList.toggle('hide', waiting);
		this.node.qs('[data-scene-upload-skill]').classList.toggle('hide', waiting);

		this.showFoot(waiting);

	}

	showFoot(shown) {

		this.node.qs('.util-drop-foot').classList.toggle('hide', shown === false);

		this.refresh();

	}

	getSize(node, bytes) {

		if(bytes < 1024) {
			return node.dataset.byte.replace('{value}', bytes);
		} else if(bytes < 1048576) {
			return node.dataset.kilo.replace('{value}', Math.round(bytes / 1024));
		} else {
			return node.dataset.mega.replace('{value}', MainDrop.getSize(bytes));
		}

	}

	refreshAdd() {
		this.node.qs('[data-scene-upload-add]').disabled = (this.node.qs('input[name="commitment"]').checked === false);
	}

	/**
	 * Le modèle vérifié prend la place du dépôt et de l'aide, avec le nom et le poids du fichier ; ou le dépôt revient, le
	 * fichier toujours listé. La case d'engagement et, s'il y en a une, celle de l'accord de partage valent pour un fichier :
	 * elles se montrent décochées à chaque modèle vérifié, et l'ajout attend l'engagement.
	 */
	showVerified(verified) {

		const block = this.node.qs('[data-scene-upload-verified]');

		if(verified) {
			block.qs('[data-scene-upload-name]').textContent = this.node.qs('.util-drop-list [data-scene-upload-name]').textContent;
			block.qs('[data-scene-upload-size]').textContent = this.node.qs('.util-drop-list [data-scene-upload-size]').textContent;
			block.qsa('[data-scene-upload-checks] input', input => input.checked = false);
			this.refreshAdd();
		}

		block.classList.toggle('hide', verified === false);
		this.node.qs('[data-scene-upload-pick]').classList.toggle('hide', verified);

	}

	isVerified() {
		return (this.node.qs('[data-scene-upload-verified]').classList.contains('hide') === false);
	}

	// Un autre fichier : le sélecteur s'ouvre, et le modèle vérifié ne s'oublie qu'au fichier choisi (`chooseFile()`) ; un
	// sélecteur refermé sans rien choisir le laisse tel quel
	chooseAgain() {
		this.node.qs('.util-drop-head [data-scene-upload-input]').click();
	}

	/**
	 * Envoie le fichier en attente avec les cases cochées sous lui, et `values` en plus, à `url`, l'action qui le reçoit ou celle
	 * qui le vérifie (`data-scene-upload-preview`). Un refus du
	 * fichier, de forme, de poids ou de conversion, tombe sur le champ `file` : le fichier est alors refusé. Le débit ou
	 * l'import indisponible tombent sur `throttled` : le fichier n'est pas en cause, mais tout nouvel envoi échouerait de
	 * même, donc il n'y a plus rien à envoyer. `isDisposed` dit si l'éditeur a été libéré pendant l'envoi, auquel cas la
	 * réponse ne touche plus rien.
	 *
	 * `values` peut être une promesse, quand l'éditeur rend une image avant de poster : le bouton qui envoie dit déjà qu'il
	 * travaille et ne se reclique pas (`data-waiter`), du clic jusqu'à la réponse.
	 */
	send(values, isDisposed, url = this.node.dataset.sceneUpload) {

		if(this.file === null) {
			return;
		}

		const file = this.file;
		const button = this.node.qs((url === this.node.dataset.sceneUpload) ? '[data-scene-upload-add]' : '[data-scene-upload-validate]');

		// Le cadriciel ne lance le `data-waiter` que sur un `data-ajax` ou un formulaire : ce bouton, qui poste par un écouteur,
		// le lance lui-même
		const enableButton = startWaiter(button);
		this.lock(true);

		const enable = () => {
			enableButton();
			this.lock(false);
		};

		Promise.resolve(values)
			.then(values => {

				const body = new FormData();
				body.append('file', file, file.name);

				for(const [name, value] of Object.entries(values)) {
					body.append(name, value);
				}

				this.node.qsa('[data-scene-upload-checks] input:checked', input => body.append(input.name, input.value));

				return new Ajax.Query(this.form)
					.url(url)
					.body(body)
					.fetch();

			})
			.then(json => {

				// Une réponse qui recharge la page ne repeint le dépôt qu'une fois la page rechargée
				// (`Ajax.Instruction.renderRedirect()`) : le bouton reste inactif jusque-là
				if(json.__redirect === undefined) {
					enable();
				}

				if(isDisposed() || this.node.isConnected === false) {
					return;
				}

				if(this.node.qs('[data-wrapper="file"].form-error-wrapper') !== null) {
					// Un fichier refusé à l'ajout se montre de nouveau dans le dépôt, avec son refus
					if(this.isVerified()) {
						this.showVerified(false);
					}
					this.refuseFile();
				} else if(this.node.qs('[data-wrapper="throttled"].form-error-wrapper') !== null) {
					this.holdFile();
				}

			}, () => enable());

	}

	// Pendant un envoi, rien de ce qui change le fichier, les champs ou les cases ne se clique : la réponse porte sur le fichier parti
	// avec eux, et le retirer ferait arriver un modèle vérifié sur une ligne disparue. La ligne du fichier dit qu'il est parti
	lock(locked) {

		this.sending = locked;

		this.node.qsa('.util-drop-list > .util-table-row', row => row.dataset.state = locked ? 'pending' : 'waiting');
		this.node.qsa('[data-scene-upload-remove], [data-scene-upload-fields], [data-scene-upload-checks], [data-scene-upload-again]', node => node.classList.toggle('click-waiting', locked));

	}

	dispose() {

		this.listeners.forEach(([target, type, listener]) => target.removeEventListener(type, listener));
		this.listeners = [];

	}

}
