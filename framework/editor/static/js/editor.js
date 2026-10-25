document.addEventListener('navigation.sleep', () => {
	Editor.endAll();
});

class Editor {

	static labels = {};
	static conf = {};

	static sleeping = true;

	static instances = [];

	static wakeup() {

		if(this.sleeping === false) {
			return;
		}

		this.sleeping = false;

		this.selection();

	};

	// Remove all registered contenteditable
	static endAll() {

		// endInstance() retire l'entrée du tableau : on itère sur une copie
		[...this.instances].forEach(instanceId => this.endInstance(instanceId));

	};

	// Start handling a contenteditable div
	static startInstance(instanceId) {

		this.wakeup();

		const instance = qs(instanceId);

		if(instance.getAttribute('data-started') === '1') {
			return;
		}

		// Contenu servi par le serveur, avant toute réorganisation :
		// c'est la version de référence si l'utilisateur refuse son brouillon
		const htmlSaved = instance.innerHTML;

		this.instances.push(instanceId); // Save instanceId
		EditorMutation.observe(instanceId); // Save mutation observer

		instance.setAttribute('data-started', '1');

		EditorFormat.start(instanceId);

		EditorFigure.reorganizeInstance(instanceId);

		EditorDraft.start(instanceId, htmlSaved);

	};

	// End handling a contenteditable div
	static endInstance(instanceId) {

		const position = this.instances.indexOf(instanceId);

		if(position === -1) {
			return;
		}

		const instance = qs(instanceId);

		// Instance exists but is not started yet
		if(
			instance !== null &&
			instance.getAttribute('data-started') !== '1'
		) {
			return;
		}

		this.resetInstance(instanceId);

		// Le brouillon lui-même survit : quitter la page ne doit pas le perdre
		EditorDraft.end(instanceId);

		// Delete instanceId
		this.instances.splice(position, 1);

		// Disconnect and delete mutation observer
		EditorMutation.disconnect(instanceId);

		EditorFormat.end(instanceId);

		if(instance !== null) {
			instance.setAttribute('data-started', '0');
		}

	};

	// Reset all instances
	static resetAll() {

		[...this.instances].forEach(instanceId => this.resetInstance(instanceId));

	};

	// Reset instance
	static resetInstance(instanceId) {

		EditorFigure.deselect(instanceId);
		EditorFormat.reset(instanceId);

	};

	// Monitor selected text for active contenteditable divs
	static selection() {

		let lastRange = null;
		let lastInstanceId = null;

		let counter = 0; // +1 = +100ms

		setInterval(() => {

			const currentRange = EditorRange.get();

			Object.entries(this.instances).forEach(([key, instanceId]) => {

				const instance = document.getElementById(instanceId.substring(1));

				if(instance === null) {
					this.endInstance(instanceId);
					return;
				}

				// Element is focused
				if(
					(instance.matches(":focus") && qs(instanceId +' figure:not(:focus)')) ||
					qsa(instanceId +' [contenteditable="true"]:focus').length > 0 ||
					EditorRange.isIn(currentRange, instanceId)
				) {

					EditorRange.saveLast(currentRange);

					// No range
					if(currentRange === null) {

						if(lastRange !== null) {

							this.selectionCollapsed(instanceId);

							lastRange = null;
							lastInstanceId = null;

						}

					}
					// Simple cursor
					else if(currentRange.collapsed) {

						if(lastRange !== null) {

							this.selectionCollapsed(instanceId);

							lastRange = null;
							lastInstanceId = null;

						}

						const container = currentRange.startContainer.nodeType === Node.TEXT_NODE ? currentRange.startContainer.parentElement : currentRange.startContainer;
						const containerFigure = this.getFirstAncestor(container, 'FIGURE');

						// Firefox hack pour MAC
						// Il replace parfois le curseur au tout début de l'éditeur quand on fait une action sur une figure
						const isContainerEditor = (
							currentRange.startContainer === instance &&
							currentRange.startOffset === 0 &&
							currentRange.endContainer=== instance &&
							currentRange.endOffset === 0
						);
						if(
							isContainerEditor === false && (
								containerFigure === null ||
								containerFigure.getAttribute('data-interactive') === 'false'
							)
						) {

							EditorFigure.deselect(instanceId);

						}

						if(container.tagName === 'FIGCAPTION') {
							EditorFigure.selectCaption(instanceId);
						} else {
							EditorFigure.deselectCaption(instanceId);
						}

					}
					// Selection
					else {

						const container = currentRange.startContainer.nodeType === Node.TEXT_NODE ? currentRange.startContainer.parentElement : currentRange.startContainer;
						const containerFigure = this.getFirstAncestor(container, 'FIGURE');

						if(
							containerFigure === null ||
							containerFigure.getAttribute('data-interactive') === 'false'
						) {

							if(
								lastRange === null ||
								lastRange.startContainer !== currentRange.startContainer ||
								lastRange.startOffset !== currentRange.startOffset ||
								lastRange.endContainer !== currentRange.endContainer ||
								lastRange.endOffset !== currentRange.endOffset
							) {

								// User is manipulating the selection
								if(isMouseDown) {

								} else {

									this.selectionExpanded(instanceId, currentRange);

									lastRange = currentRange;
									lastInstanceId = instanceId;

								}

							}

							EditorFigure.deselect(instanceId);

						}

						if(container.tagName === 'FIGCAPTION') {
							EditorFigure.selectCaption(instanceId);
						} else {
							EditorFigure.deselectCaption(instanceId);
						}

					}

				} else {

					if(
						lastInstanceId === instanceId &&
						qs(lastInstanceId +'-box-selection').isVisible() &&
						qs(lastInstanceId +'-box-selection:hover') === null &&
						qs(lastInstanceId +'-box-selection input:focus') === null
					) {

						this.selectionCollapsed(lastInstanceId);

						lastRange = null;
						lastInstanceId = null;

					}

				}

				if(EditorRange.isIn(currentRange, instanceId)) {
					EditorFormat.updateLine(instanceId, currentRange);
				}

				qsa(instanceId +' figcaption', figcaptionSelector => {

					if(figcaptionSelector.matches(':focus')) {

						if(figcaptionSelector.innerHTML === '') {
							figcaptionSelector.innerHTML = '&nbsp;';
						}

					} else {

						if(figcaptionSelector.innerHTML === '&nbsp;') {
							figcaptionSelector.innerHTML = '';
						}

					}

				});

				// Fixed #1138 (workaround, we have to check regularly if it is fixed)
				if(browser.isSafari) {

					const instance = document.getElementById(instanceId.substring(1));

					for(let i = 0; i < instance.childNodes.length; i++) {

						const node = instance.childNodes[i];

						// Garbage collector for nasty browsers
						// Destroy empty lists and p
						if(
							(node.tagName === 'OL' || node.tagName === 'UL' || node.tagName === 'P' || node.tagName === 'H2' || node.tagName === 'H3' || node.tagName === 'H4') &&
							node.innerHTML === ''
						) {
							node.parentElement.removeChild(node);
							i--;
							continue;
						}

					}

				}

			});

			counter++;

		}, 100);

	};

	// returns first ancestor that matchs the given tag
	static getFirstAncestor(node, tag) {

		let nodeCheck = node;

		while(nodeCheck.parentElement) {

			if(this.isEditorNode(nodeCheck)) {
				return null;
			}

			if(nodeCheck.tagName === tag) {
				return nodeCheck;
			}

			nodeCheck = nodeCheck.parentElement;

		}

		return null;

	};

	// returns first ancestor that matchs the given tag
	static isBaseNode(node) {
		return ['P', 'UL', 'OL', 'H2', 'H3', 'H4', 'FIGURE'].includes(node.tagName);
	}

	// returns the first level block containing the node (direct child of the contenteditable)
	static getTopAncestor(node) {

		let nodeCheck = node;

		while(
			nodeCheck &&
			nodeCheck.parentElement &&
			this.isEditorNode(nodeCheck.parentElement) === false
		) {
			nodeCheck = nodeCheck.parentElement;
		}

		return nodeCheck;

	};

	// returns first ancestor that matchs the given tag
	static getBaseAncestor(node) {

		let nodeCheck = node;

		while(nodeCheck.parentElement) {

			if(this.isEditorNode(nodeCheck)) {
				return null;
			}

			if(this.isBaseNode(nodeCheck)) {
				return nodeCheck;
			}

			nodeCheck = nodeCheck.parentElement;

		}

		return null;

	};

	// returns contenteditable node
	static getEditorNode(node) {

		let nodeCheck = node;

		while(nodeCheck) {

			if(this.isEditorNode(nodeCheck)) {
				return nodeCheck;
			}

			nodeCheck = nodeCheck.parentElement;
		}

		return null;

	};

	static isEditorNode(node) {

		return (
			node &&
			node.nodeType === Node.ELEMENT_NODE &&
			node.hasAttribute('data-editor')
		);

	};

	static dispatchSlash(instance) {

		const slash = EditorRange.getSlash(instance);

		if(slash !== null) {
			instance.dispatchEvent(new CustomEvent('editorSlash', {
				bubbles: true,
				detail: {range: slash}
			}));
		}

	};

	static isNodeIn(node, tagCheck) {

		let nodeCheck = node;

		while(
			nodeCheck &&
			this.isEditorNode(nodeCheck) === false
		) {

			if(
				nodeCheck.nodeType === Node.ELEMENT_NODE &&
				nodeCheck.tagName === tagCheck
			) {
				return true;
			}

			nodeCheck = nodeCheck.parentElement;
		}

		return false;

	};

	// Display a formatting box for the selected text
	static selectionExpanded(instanceId, current) {

		EditorFormat.displaySelection(instanceId, current);

	};

	// No more selected text
	static selectionCollapsed(instanceId) {

		EditorFormat.hideSelection(instanceId);

	};

};

// Handle keyboards events, such as <hr>, lists...
document.delegateEventListener('keydown', '.editor', function(e) {

	const instanceId = '#'+ this.id;

	const selection = window.getSelection();

	// Backspace
	switch(e.which) {

		case 8 : // Backspace

			// Contenteditable is focused but has no selection
			if(selection.rangeCount === 0) {
				e.preventDefault(); // Prevent history back
				return;
			}

			if(EditorKeyboard.backspace(instanceId, e, selection)) {
				e.preventDefault();
			}

			break;

		case 46 : // Del

			if(selection.rangeCount === 0) {

				e.preventDefault();

				// Drop the selected figure
				const figureSelector = EditorFigure.getSelected(this);

				if(figureSelector !== null) {
					EditorFigure.remove(instanceId, figureSelector);
				}

				return;

			}

			if(EditorKeyboard.del(instanceId, e, selection)) {
				e.preventDefault();
			}

			break;

		case 13 : // Enter

			if(selection.rangeCount === 0) {

				// Add a new line before the selected figure
				const figureSelector = EditorFigure.getSelected(this);

				if(figureSelector !== null) {
					e.preventDefault();
					EditorFigure.insertLineBefore(instanceId, figureSelector);
				}

			}

			break;

		case 9 : // Tab

			// Dans une liste seulement : ailleurs, Tab doit garder son rôle
			// de navigation au clavier et sortir de l'éditeur
			if(EditorKeyboard.listLevel(selection, e.shiftKey ? 'outdent' : 'indent')) {
				e.preventDefault();
			}

			break;

		case 69 : // e
		case 66 : // b
		case 85 : // u
		case 73 : // i
		case 75 : // k

			if(selection.rangeCount > 0) {

				const range = selection.getRangeAt(0);

				EditorKeyboard.shortcuts(instanceId, range, e, String.fromCharCode(e.which).toLowerCase());

			}

			break;

	}


});

document.delegateEventListener('keypress', '.editor', function(e) {

	const instanceId = '#'+ this.id;

	const selection = window.getSelection();

	if(selection.rangeCount > 0) {

		const range = selection.getRangeAt(0);
		const character = String.fromCharCode(e.charCode);

		EditorKeyboard.shortcuts(instanceId, range, e, character);

	}

});

// Les claviers virtuels d'Android n'émettent pas de keypress et rendent 229 au keydown :
// seul input porte le caractère tapé
document.delegateEventListener('input', '.editor', function(e) {

	if(
		e.inputType === 'insertText' &&
		e.data === '/'
	) {
		Editor.dispatchSlash(this);
	}

});

// Gboard compose le mot en cours et y range le « / » (insertCompositionText) : le texte et le curseur
// ne sont définitifs qu'à compositionend
document.delegateEventListener('compositionend', '.editor', function(e) {

	if(e.data.endsWith('/')) {
		Editor.dispatchSlash(this);
	}

});

document.delegateEventListener('keyup', '.editor', function(e) {

	const instanceId = '#'+ this.id;
	const selection = window.getSelection();

	// No focus
	if(selection.rangeCount === 0) {
		return;
	}

	const range = selection.getRangeAt(0);

	EditorFormat.updateLine(instanceId, range);

});

class EditorKeyboard {

	static smileys = {
		':)' : '😀', ':-)' : '😀',
		':(' : '😦', ':-(' : '😦',
		';)' : '😉',
		';-)' : '😉',
		':\'(' : '😥',
		':p' : '😛',	':-p' : '😛',
		'(sleep)' : '😴',
		'(angry)' : '😡',
		'(a)' : '😇',
		'(yes)' : '👍',
		'(no)' : '👎',
		'(l)' : '💗',
		'(k)' : '😘'
	};

	static backspace(instanceId, e, selection) {

		const node = EditorRange.lineStart(selection);

		if(node === null) {
			return false;
		}

		// Within a figure, backspaces are not allowed at the beginning of a figcaption
		if(node.tagName === 'FIGCAPTION') {
			return true;
		}

		// In the editor
		const previousNode = EditorNode.siblingNoWhitespaces(node, 'previousSibling');

		if(previousNode === null) {
			return true;
		} else {

			switch(previousNode.tagName) {

				case 'FIGURE' :

					const topNode = Editor.getBaseAncestor(selection.getRangeAt(0).startContainer);

					if(EditorKeyboard.isEmptyLine(topNode)) {
						topNode.remove();
					}

					EditorFigure.select(instanceId, previousNode);

					return true;

			}

		}

		return false;


	};

	static del(instanceId, e, selection) {

		const node = EditorRange.lineEnd(selection);

		if(node === null) {
			return false;
		}

		// Within a figcaption
		if(node.tagName === 'FIGCAPTION') {
			return true;
		}

		// In the editor
		const nextNode = EditorNode.siblingNoWhitespaces(node, 'nextSibling');

		if(nextNode === null) {
			return true;
		} else {

			switch(nextNode.tagName) {

				case 'FIGURE' :

					if(EditorKeyboard.isEmptyLine(node)) {
						EditorRange.saveState();
						EditorNode.removeRecursive(node);
					}

					EditorFigure.select(instanceId, nextNode);

					return true;



			}

		}

		return false;


	};

	static shortcuts(instanceId, range, e, character) {

		const container = range.commonAncestorContainer;

		switch(character) {

			case 'b' :
			case 'i' :
			case 'u' :

				if(e.ctrlKey || e.metaKey) {

					const actions = {
						b: 'bold',
						i: 'italic',
						u: 'underline'
					};

					e.preventDefault();

					EditorFormat.action(actions[character], instanceId);


				}

				break;


			case 'k' :

				if(
					range.collapsed === false &&
					(e.ctrlKey || e.metaKey)
				) {

					e.preventDefault();

					EditorFormat.action('link-open', instanceId);

				}

				break;

			case ' ' : // space
			case String.fromCharCode(160) : // space

				const nodeUl = EditorKeyboard.checkLine(container, '-') || EditorKeyboard.checkLine(container, '*');

				if(nodeUl !== null) {
					e.preventDefault();
					return EditorKeyboard.list(nodeUl, 'ul', false);
				}


				const nodeLi = EditorKeyboard.checkLine(container, '#') || EditorKeyboard.checkLine(container, '1.');

				if(nodeLi !== null) {
					e.preventDefault();
					return EditorKeyboard.list(nodeLi, 'ol', false);
				}

				break;

			case '(' :
			case ')' :
			case 'p' :

				if(EditorKeyboard.smiley(container, e)) {
					return true;
				}

				break;
		}

	};

	static smiley(nodeExisting, e) {

		// Look for text pattern
		if(nodeExisting.nodeType !== Node.TEXT_NODE) {
			return false;
		}

		const range = window.getSelection().getRangeAt(0);

		if(
			range.startContainer === nodeExisting &&
			range.collapsed
		) {

			return EditorKeyboard._replaceSmileys(nodeExisting, e, range);

		}

		return false;

	};

	/**
	 * Changes the smileys into <img> tag
	 */
	static _replaceSmileys(node, e, range) {

		const message = node.nodeValue;
		const lastCharacter = String.fromCharCode(e.charCode);

		for(let shortcut in EditorKeyboard.smileys) {

			if(message.substring(range.startOffset - shortcut.length + 1, range.startOffset) + lastCharacter === shortcut) {

				e.preventDefault();

				const startMessage = message.substring(0, range.startOffset - shortcut.length + 1);
				const endMessage = message.substring(range.startOffset);

				const fragment = document.createDocumentFragment();

				if(startMessage) {
					fragment.appendChild(document.createTextNode(startMessage));
				}

				fragment.appendChild(document.createTextNode(EditorKeyboard.smileys[shortcut]));

				let nodeEnd;
				let offsetEnd;

				if(endMessage) {

					nodeEnd = document.createTextNode(endMessage);
					offsetEnd = 0;

					fragment.appendChild(nodeEnd);

				} else {

					nodeEnd = node.parentElement;
					offsetEnd = node.parentElement.childNodes.length + fragment.childNodes.length - 1;

					if(
						node.parentElement.lastChild.nodeType === Node.ELEMENT_NODE &&
						node.parentElement.lastChild.tagName === 'BR'
					) {
						offsetEnd--;
					}

				}

				node.parentElement.replaceChild(fragment, node);

				EditorRange.replace(nodeEnd, offsetEnd);

				return true;

			}


		}

		return false;

	};

	static list(nodeExisting, type, copy) {

		const nodeUl = document.createElement(type);
		const nodeLi = document.createElement('li');
		nodeUl.appendChild(nodeLi);

        if(copy) {
            Array.from(nodeExisting.childNodes).forEach(childNode => nodeLi.appendChild(childNode));
        } else {
            const nodeBr = document.createElement('br');
            nodeLi.appendChild(nodeBr);
        }

		nodeExisting.parentElement.replaceChild(nodeUl, nodeExisting);

        const lastChild = nodeLi.childNodes[nodeLi.childNodes.length - 1];

		EditorRange.replace(lastChild, (lastChild.nodeType === Node.ELEMENT_NODE) ?
            lastChild.childNodes.length :
            lastChild.length);

		return true;

	};

	// Ajoute ou retire un niveau d'imbrication aux <li> de la sélection
	// Renvoie false si la sélection n'est pas dans une liste, pour laisser
	// l'appelant retomber sur le comportement par défaut
	static listLevel(selection, direction) {

		const nodesItem = EditorKeyboard.getSelectedListItems(selection);

		if(nodesItem.length === 0) {
			return false;
		}

		EditorRange.keep(() => {

			if(direction === 'indent') {
				nodesItem.forEach(nodeItem => EditorKeyboard._listIndent(nodeItem));
			} else {
				// Du dernier au premier : sortir un item déplace ceux qui le suivent
				nodesItem.reverse().forEach(nodeItem => EditorKeyboard._listOutdent(nodeItem));
			}

		});

		// On est dans une liste : l'action est consommée même quand rien n'a bougé
		// (premier item d'une liste, ou profondeur maximale atteinte)
		return true;

	};

	// Change le type de la liste la plus proche du curseur, sans en créer une nouvelle
	static listType(nodeList, type) {

		if(nodeList.tagName === type.toUpperCase()) {
			return false;
		}

		EditorRange.keep(() => nodeList.renameNode(type));

		return true;

	};

	// Renvoie les <li> couverts par la sélection, en ordre document
	static getSelectedListItems(selection) {

		if(selection.rangeCount === 0) {
			return [];
		}

		const range = selection.getRangeAt(0);

		const nodeItem = EditorKeyboard._getItem(range.startContainer);

		if(nodeItem === null) {
			return [];
		}

		if(range.collapsed) {
			return [nodeItem];
		}

		const nodeEditor = Editor.getEditorNode(nodeItem);
		const nodesItem = [];

		nodeEditor.qsa('li', nodeCheck => {

			if(range.intersectsNode(nodeCheck)) {
				nodesItem[nodesItem.length] = nodeCheck;
			}

		});

		// Un item qui en contient un autre suit déjà son enfant : le garder
		// reviendrait à décaler deux fois la même branche
		return nodesItem.filter(nodeCheck => {
			return nodesItem.some(nodeOther => nodeOther !== nodeCheck && nodeCheck.contains(nodeOther)) === false;
		});

	};

	static _getItem(node) {
		return Editor.getFirstAncestor(node.nodeType === Node.TEXT_NODE ? node.parentElement : node, 'LI');
	};

	// Profondeur d'un <li> : 0 pour un item de premier niveau
	static getListDepth(nodeItem) {

		let depth = -1;
		let node = nodeItem.parentElement;

		while(
			node !== null &&
			Editor.isEditorNode(node) === false
		) {

			if(node.tagName === 'UL' || node.tagName === 'OL') {
				depth++;
			}

			node = node.parentElement;

		}

		return depth;

	};

	// Renvoie la sous-liste en fin d'item, ou la crée
	static _getSubList(nodeItem, type) {

		const nodeLast = nodeItem.lastElementChild;

		if(
			nodeLast !== null &&
			(nodeLast.tagName === 'UL' || nodeLast.tagName === 'OL')
		) {
			return nodeLast;
		}

		const nodeList = document.createElement(type);
		nodeItem.appendChild(nodeList);

		return nodeList;

	};

	static _listIndent(nodeItem) {

		const nodeList = nodeItem.parentElement;

		let nodePrevious = nodeItem.previousElementSibling;

		// Premier item de sa liste : s'il y a une liste juste au-dessus, on s'imbrique
		// sous son dernier item. Deux listes adjacentes se lisent comme une seule, et
		// la barre d'outils comme le raccourci « - » en créent une nouvelle à chaque fois
		if(nodePrevious === null) {
			nodePrevious = EditorKeyboard._getPreviousListItem(nodeList);
		}

		// Rien au-dessus où s'imbriquer
		if(
			nodePrevious === null ||
			nodePrevious.tagName !== 'LI'
		) {
			return false;
		}

		// La profondeur se mesure sur la destination, pas sur le point de départ
		if(EditorKeyboard.getListDepth(nodePrevious) + 1 >= Editor.conf.listDepthLimit) {
			return false;
		}

		// L'item rejoint la sous-liste de son prédécesseur, avec ce qu'il contient
		EditorKeyboard._getSubList(nodePrevious, nodeList.tagName).appendChild(nodeItem);

		// La liste qu'on vient de quitter peut être devenue vide
		if(nodeList.children.length === 0) {
			nodeList.remove();
		}

		return true;

	};

	// Dernier item de la liste qui précède immédiatement celle donnée, si elle est de même nature
	static _getPreviousListItem(nodeList) {

		const nodeListPrevious = nodeList.previousElementSibling;

		if(
			nodeListPrevious === null ||
			nodeListPrevious.tagName !== nodeList.tagName
		) {
			return null;
		}

		return nodeListPrevious.lastElementChild;

	};

	static _listOutdent(nodeItem) {

		const nodeList = nodeItem.parentElement;
		const nodeItemParent = nodeList.parentElement;

		// Liste de premier niveau : il n'y a pas de niveau au-dessus
		if(
			nodeItemParent === null ||
			nodeItemParent.tagName !== 'LI'
		) {
			return false;
		}

		// Les items qui suivent passeraient sous l'item sorti : on les lui rattache
		// pour qu'ils gardent leur niveau
		const nodesFollowing = [];
		let nodeFollowing = nodeItem.nextElementSibling;

		while(nodeFollowing !== null) {
			nodesFollowing[nodesFollowing.length] = nodeFollowing;
			nodeFollowing = nodeFollowing.nextElementSibling;
		}

		if(nodesFollowing.length > 0) {

			const nodeSubList = EditorKeyboard._getSubList(nodeItem, nodeList.tagName);
			nodesFollowing.forEach(node => nodeSubList.appendChild(node));

		}

		nodeItemParent.insertAdjacentElement('afterend', nodeItem);

		// La sous-liste vidée n'a plus lieu d'être
		if(nodeList.children.length === 0) {
			nodeList.remove();
		}

		return true;

	};

	// Checks if a node is inside an empty line
	static isEmptyLine(nodeCheck) {

		if(nodeCheck.nodeType === Node.TEXT_NODE) {
			return false;
		}

		if(Editor.isEditorNode(nodeCheck) && nodeCheck.innerHTML === '') {
			return true;
		}

		if(nodeCheck === null || nodeCheck.tagName !== 'P') {
			return false;
		}

		try {

			const run = function(node) {

				for(let i = 0; i < node.childNodes.length; i++) {

					const childNode = node.childNodes[i];

					if(
						childNode.nodeType === Node.TEXT_NODE ||
						childNode.tagName === 'IMG' || // <img>
						childNode.tagName === 'LI' // <li> in <ul>
					) {
						throw 'error';
					} else {
						run(childNode);
					}

				}

			};

			run(nodeCheck);

			return true;

		} catch(e) {
			return false;
		}

	};

	// Checks if a text node is alone on the previous line
	static getTextNodeFromPreviousLine(nodeCurrent) {

		if(EditorKeyboard.isEmptyLine(nodeCurrent) === false) {
			return null;
		}

		const nodeCurrentAncestor = Editor.getBaseAncestor(nodeCurrent);

		if(nodeCurrentAncestor.previousSibling === null) {
			return null;
		}

		const nodePrevious = nodeCurrentAncestor.previousSibling;

		try {

			let nodeText = null;

			nodePrevious.childNodes.forEach(nodeCheck => {

				if(
					nodeText === null &&
					nodeCheck.nodeType === Node.TEXT_NODE
				) {
					nodeText = nodeCheck;
					return;
				}

				if(
					nodeCheck.nodeType === Node.TEXT_NODE || // Omg a second text node
					nodeCheck.tagName === 'LI'
				) {
					throw 'error';
				}

			});

			return nodeText;

		} catch(e) {
			return null;
		}

	};

	// Checks if a node contains the given text and is alone on its line
	static checkLine(nodeCheck, text) {


		// Look for text pattern
		if(
			nodeCheck.nodeType !== Node.TEXT_NODE ||
			nodeCheck.nodeValue !== text
		) {
			return null;
		}

		// Nothing after the node
		if(
			nodeCheck.nextSibling !== null &&
			(!browser.isFirefox || nodeCheck.nextSibling.tagName !== 'BR') // Firefox hack
		) {
			return null;
		}

		// Check if we are at the very beginning of a line
		let node = nodeCheck;

		while(
			// Root node (contenteditable div)
			// Stop looping
			node &&
			Editor.isBaseNode(node) === false
		) {

			// Current node must be the first child of its parent node
			if(node.parentElement.firstChild !== node) {
				return null;
			}

			node = node.parentElement;

		}

		return node;

	};

}

// Display a formatting box for selected text
// Handle <b>, <u>, <i>...
document.delegateEventListener('click', '.editor-action, [data-action-run="1"]', function(e) {

	e.preventDefault();
	e.stopImmediatePropagation();

	const action = this.getAttribute('data-action');
	const instanceId = this.getAttribute('data-instance');

	EditorFormat.action(action, instanceId, this);

});

document.delegateEventListener('focusin', '.editor', function(e) {

	e.preventDefault();

	if(this.innerHTML === '') {

		const nodeP = EditorParagraph.getEmpty();

		this.appendChild(nodeP);
		EditorRange.replaceEnd(nodeP);

	}

});

document.delegateEventListener('focusout', '.editor', function(e) {

	e.preventDefault();

	if(
		this.getAttribute('data-figure') === '0' &&
		this.innerHTML.match(/^<p(?: [a-z\-]+="[a-zA-Z0-9]+")*>(?:<br>)*<\/p>$/) !== null
	) {
		this.innerHTML = '';
	}

});

// Prevent click on links in editor
document.delegateEventListener('click', '.editor p a', function(e) {

	e.preventDefault();

});

document.delegateEventListener('click', '.editor figure', function(e) {

	const instanceId = '#'+ Editor.getEditorNode(this).id;

	EditorFigure.select(instanceId, this);


});

// Display actions for medias
document.delegateEventListener('mouseenter', '.editor figure > div.editor-media', function(e) {

	e.preventDefault();

	if(EditorFormat.isMoving()) {
		return;
	}

	const instanceId = '#'+ Editor.getEditorNode(this).id;
	const figureId = '#' + this.parentElement.id;

	EditorFormat.displayMedia(instanceId, figureId, this);

});

document.delegateEventListener('mouseleave', '.editor figure > div.editor-media', function(e) {

	e.preventDefault();

	const instanceId = '#'+ Editor.getEditorNode(this).id;

	EditorFormat.hideMedia(instanceId);

});

// Allow to move medias
document.delegateEventListener('mouseenter', '.editor-action[data-action="media-move"]', function(e) {

	const instanceId = this.getAttribute('data-instance');

	EditorFormat.action('move-media-open', instanceId, this);

});

document.delegateEventListener('mouseleave', '.editor-action[data-action="media-move"]', function(e) {

	if(isMouseDown === false) {

		const instanceId = this.getAttribute('data-instance');

		EditorFormat.action('move-media-close', instanceId, this);


	}

});

// Allow to move figures
document.delegateEventListener('mouseenter', '.editor-action[data-action="figure-move"]', function(e) {

	const instanceId = this.getAttribute('data-instance');

	EditorFormat.action('move-figure-open', instanceId, this);

});

document.delegateEventListener('mouseleave', '.editor-action[data-action="figure-move"]', function(e) {

	if(isMouseDown === false) {

		const instanceId = this.getAttribute('data-instance');

		EditorFormat.action('move-figure-close', instanceId, this);


	}

});

// Handle image download
document.delegateEventListener('change', 'input.editor-upload', function(e) {

	e.preventDefault();
	e.stopPropagation();

	const instanceId = this.getAttribute('data-instance');
	const figureId = this.getAttribute('data-figure') || null;

	EditorImage.upload(instanceId, figureId, e, this.files);

	this.value = '';

});

document.delegateEventListener('paste', '.editor[data-figure="1"]', function(e) {

	// Word et Excel joignent une image du texte copié : seul un collage sans texte est une image
	if(e.clipboardData.types.includes('text/plain')) {
		return;
	}

	const files = Array.from(e.clipboardData.files).filter(file => file.type.startsWith('image/'));

	if(files.length === 0) {
		return;
	}

	e.preventDefault();

	const figureSelector = EditorFigure.getSelected(this);

	if(
		figureSelector !== null &&
		this.dataset.figureOnlyImage === '0'
	) {
		EditorImage.upload('#'+ this.id, '#'+ figureSelector.id, e, files);
		return;
	}

	// Une figure sélectionnée retire la sélection : la dernière ligne connue reste alors le point d'insertion
	const range = EditorRange.get();

	if(range !== null) {
		EditorRange.saveLast(range);
	}

	EditorImage.upload('#'+ this.id, null, e, files);

});


document.delegateEventListener('submit', '#editor-link-form', function(e) {

	e.preventDefault();

	const instanceId = this.getAttribute('data-instance');

	EditorFormat.action('link', instanceId, this);

});

// Placeholder for figcaption (workaround)
document.delegateEventListener('input', '.editor figcaption', function(e) {

	if(this.innerHTML === '') {
		this.innerHTML = '&nbsp;';
	}

	this.focus();

});

document.delegateEventListener('submit', '#editor-media-configure', function(e) {

	e.preventDefault();

	const url = qs('#editor-media-configure input[name="url"]').value;

	qs('div[data-url="' + url + '"]', media => {
		media.setAttribute('data-title', qs('#editor-media-configure input[name="title"]').value);
		media.setAttribute('data-link', qs('#editor-media-configure input[name="link"]').value);
	});

	qs('#panel-editor-media-configure', node => Lime.Panel.close(node));

});

document.delegateEventListener('click', '[data-action="media-download"]', function(e) {

	e.preventDefault();

	const url = this.getAttribute('data-url');
	EditorImage.download(url);

});

document.delegateEventListener('click', '[data-action="media-rotate-show"]', function(e) {

	e.preventDefault();

	const rotate = qs('#media-rotate-list');

	if(rotate.style.display === 'none') {
		rotate.style.display = 'inline-block';
	} else {
		rotate.style.display = 'none';
	}

});

document.delegateEventListener('click', '[data-action="media-rotate"]', function(e) {

	e.preventDefault();

	qs('#media-rotate-list').classList.add('processing');
	qsa('#media-rotate-list a', node => node.removeAttribute('data-action'));

	this.childNodes.filter('img').forEach(node => node.classList.add('animate'));

	const hash = this.getAttribute('data-xyz');
	const angle = this.getAttribute('data-angle');
	const mediaSelector = qs('.editor-media[data-xyz="' + hash + '"]');

	new EditorMedia().rotate(mediaSelector, null, hash, angle);

});

document.delegateEventListener('click', '[data-action="media-separate"]', function(e) {

	e.preventDefault();

	const nodeHost = EditorParagraph.getEmpty();

	const instanceId = this.getAttribute('data-instance');
	const mediaSelector = EditorFormat.currentMedia[instanceId];

	const figureSelector = mediaSelector.parentElement;

	figureSelector.parentElement.insertBefore(nodeHost, figureSelector);

	const newFigureId = EditorFigure.create(instanceId, {
		nodeHost: nodeHost,
		interactive: true
	});

	// create() renvoie un identifiant, addSelector() attend l'élément
	EditorFigure.addSelector(instanceId, qs(newFigureId), mediaSelector);

	EditorFormat.hideMedia(instanceId);

	figureSelector.removeAttribute('style');
	figureSelector.childNodes.filter('.editor-media').forEach(node => node.removeAttribute('style'));

	EditorFigure.reorganize(instanceId, figureSelector);

	qs('#panel-editor-media-configure', node => Lime.Panel.close(node));

});

// Videos
document.delegateEventListener('keyup', '#editor-video-value', function(e) {

	if(e.key === 'Escape') {

		const instanceId = qs('#panel-editor-video').getAttribute('data-instance');
		EditorFormat.action('video', instanceId, this);

	}

});

document.delegateEventListener('submit', '#editor-video-form', function(e) {

	e.preventDefault();

	const instanceId = qs('#panel-editor-video').getAttribute('data-instance');
	EditorFormat.action('video', instanceId, this);

});

document.delegateEventListener('submit', '#editor-grid-form', function(e) {

	e.preventDefault();

	const instanceId = qs('#panel-editor-grid').getAttribute('data-instance');
	EditorFormat.action('grid', instanceId, this);

});

// Quotes
document.delegateEventListener('click', '[data-action="quote-image"]', function(e) {

	e.preventDefault();

	EditorQuote.next(this);

});

class EditorFormat {

	static mediaMoving = false;

	// Bloc de premier niveau sur lequel la boîte de ligne est posée, par instance
	static currentLine = {};

	static start(instanceId) {

		const instance = qs(instanceId);

		document.execCommand('enableObjectResizing', false, false);

		let html = '<div id="'+ instanceId.substring(1) +'-format">';

			html += '<input class="editor-upload" data-instance="'+ instanceId +'" type="file" multiple="multiple" accept="image/*">';

			html += '<div id="'+ instanceId.substring(1) +'-box-selection" class="editor-box-selection">'+
				'<div class="editor-box-selection-icons">'+
					'<button class="editor-action" data-action="bold" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('type-bold') +'</button>'+
					'<button class="editor-action" data-action="italic" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('type-italic') +'</button>'+
					'<button class="editor-action" data-action="underline" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('type-underline') +'</button>'+
					'<button class="editor-action" data-action="link-open" data-instance="'+ instanceId +'" title="'+ Editor.labels.link +'">'+ Lime.Asset.icon('link') +'</button>';

			html += '<span class="separator">&nbsp;</span>';
				html += '<button class="editor-action" data-action="size-open" data-instance="'+ instanceId +'" title="'+ Editor.labels.size +'"><span class="editor-size">Aa</span></button>';
				html += '<button class="editor-action" data-action="highlight" data-instance="'+ instanceId +'" title="'+ Editor.labels.highlight +'">'+ Lime.Asset.icon('highlighter') +'</button>';
				html += '<button class="editor-action" data-action="foreColor" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('palette-fill') +'</button>';
				html += '<input type="color" class="editor-box-selection-color" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" '+ 'oninput'.attr('EditorFormat.action("foreColorNew", "'+ instanceId +'", this)') +'/>';
			html += '<span class="separator">&nbsp;</span>';

			html += '<button class="editor-action" data-action="align-open" data-instance="'+ instanceId +'" title="'+ Editor.labels.align +'">'+ Lime.Asset.icon('text-left') +'</button>';
			html += '<span class="separator" data-action="align-separator">&nbsp;</span>';

			html += '<button class="editor-action" data-action="header-open" data-instance="'+ instanceId +'" title="'+ Editor.labels.header +'"><span class="editor-header">T</span></button>';


			html += '</div>'+
				'<div class="editor-box-selection-size">'+
					'<button class="editor-action" data-action="size-small" data-instance="'+ instanceId +'"><span>'+ Editor.labels.sizeSmall +'</span></button>'+
					'<button class="editor-action" data-action="size-normal" data-instance="'+ instanceId +'"><span>'+ Editor.labels.sizeNormal +'</span></button>'+
					'<button class="editor-action" data-action="size-large" data-instance="'+ instanceId +'"><span>'+ Editor.labels.sizeLarge +'</span></button>'+
					'<button class="editor-action" data-action="sub-close" data-instance="'+ instanceId +'" title="'+ Editor.labels.close +'">'+ Lime.Asset.icon('x-lg') +'</button>'+
				'</div>'+
				'<div class="editor-box-selection-header">'+
					'<button class="editor-action" data-action="header-h2" data-instance="'+ instanceId +'"><span>'+ Editor.labels.headerH2 +'</span></button>'+
					'<button class="editor-action" data-action="header-h3" data-instance="'+ instanceId +'"><span>'+ Editor.labels.headerH3 +'</span></button>'+
					'<button class="editor-action" data-action="header-h4" data-instance="'+ instanceId +'"><span>'+ Editor.labels.headerH4 +'</span></button>'+
					'<button class="editor-action" data-action="header-p" data-instance="'+ instanceId +'"><span>'+ Editor.labels.headerP +'</span></button>'+
					'<button class="editor-action" data-action="sub-close" data-instance="'+ instanceId +'" title="'+ Editor.labels.close +'">'+ Lime.Asset.icon('x-lg') +'</button>'+
				'</div>'+
				'<div class="editor-box-selection-align">'+
					'<button class="editor-action" data-action="align-left" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('text-left') +'</button>'+
					'<button class="editor-action" data-action="align-center" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('text-center') +'</button>'+
					'<button class="editor-action" data-action="align-right" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('text-right') +'</button>'+
					'<button class="editor-action" data-action="align-justify" data-instance="'+ instanceId +'">'+ Lime.Asset.icon('justify') +'</button>'+
					'<button class="editor-action" data-action="sub-close" data-instance="'+ instanceId +'" title="'+ Editor.labels.close +'">'+ Lime.Asset.icon('x-lg') +'</button>'+
				'</div>'+
				'<div class="editor-box-selection-link">'+
					'<form id="editor-link-form" data-instance="'+ instanceId +'">'+
						'<input type="text" placeholder="'+ Editor.labels.placeholderLink +'" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"/>'+
						'<button type="submit"></button>'+
						'<a class="editor-action" data-action="link-close" data-instance="'+ instanceId +'"></a>'+
					'</form>'+
				'</div>'+
			'</div>';

			if(instance.dataset.figure === '1') {

                const onlyImage = instance.dataset.figureOnlyImage;

				html += '<div id="'+ instanceId.substring(1) +'-box-line" class="editor-box-line" data-instance="'+ instanceId +'">';
					html += '<div class="editor-box-line-open">'+
						'<button class="editor-action" data-action="line-swap" data-instance="'+ instanceId +'" title="'+ Editor.labels.media +'">'+ Lime.Asset.icon('plus') +'</button>'+
						'<div class="editor-box-line-helper" style="visibility: hidden;">'+ instance.getAttribute('data-placeholder-empty') +'</div>'+
					'</div>';
					html += '<div class="editor-box-line-content">';
						html += '<button class="editor-action" data-action="list-ul" data-instance="'+ instanceId +'" title="'+ Editor.labels.listBullet +'">';
                            html += Lime.Asset.icon('list-ul');
                        html += '</button>';
                        html += '<button class="editor-action" data-action="list-ol" data-instance="'+ instanceId +'" title="'+ Editor.labels.listDigit +'">';
                            html += Lime.Asset.icon('list-ol');
                        html += '</button>';
                        // Affichés seulement quand le curseur est dans une liste, cf. updateLine()
                        // Noms Bootstrap trompeurs : text-indent-left porte le chevron vers la
                        // droite (décaler à droite), text-indent-right celui vers la gauche
                        html += '<button class="editor-action editor-action-list-level" data-action="list-indent" data-instance="'+ instanceId +'" title="'+ Editor.labels.listIndent +'">';
                            html += Lime.Asset.icon('text-indent-left');
                        html += '</button>';
                        html += '<button class="editor-action editor-action-list-level" data-action="list-outdent" data-instance="'+ instanceId +'" title="'+ Editor.labels.listOutdent +'">';
                            html += Lime.Asset.icon('text-indent-right');
                        html += '</button>';
                        html += '<button class="editor-action" data-action="line-image" data-instance="'+ instanceId +'" title="'+ Editor.labels.image +'">';
                            html += Lime.Asset.icon('image');
                        html += '</button>';

                        if(onlyImage === '0') {

                            html += '<button class="editor-action" data-action="show-editor-video" data-instance="'+ instanceId +'" title="'+ Editor.labels.video +'">'+ Lime.Asset.icon('camera-video-fill') +'</button>'+
                            '<button class="editor-action" data-action="show-editor-grid" data-instance="'+ instanceId +'" title="'+ Editor.labels.grid +'">'+ Lime.Asset.icon('grid-fill') +'</button>'+
                            '<button class="editor-action" data-action="quote" data-instance="'+ instanceId +'" title="'+ Editor.labels.quote +'">'+ Lime.Asset.icon('quote') +'</button>'+
                            '<button class="editor-action" data-action="hr" data-instance="'+ instanceId +'" title="'+ Editor.labels.separator +'">'+ Lime.Asset.icon('dash') +'</button>';

                        }

					html += '</div>';
				html += '</div>';

			}

		html += '</div>';

		document.body.insertAdjacentHTML('beforeend', html);

	};

	static end(instanceId) {

		qs(instanceId +'-format', node => node.remove());

		delete EditorFormat.currentLine[instanceId];

	};

	static reset(instanceId) {

		EditorFormat.hideMedia(instanceId);
		EditorFormat.hideSelection(instanceId);
		EditorFormat.hideLine(instanceId);

	};

	static currentMedia = {};

	static displayMedia(instanceId, figureId, mediaSelector) {

		const figureSelector = qs(figureId);
        const onlyImage = qs(instanceId).dataset.figureOnlyImage;

		EditorFormat.hideMedia(instanceId);

        if(onlyImage === '1') {
            return;
        }

		let html = '<div class="editor-box-media">';
			html += '<div class="editor-box-media-content">';

            if(
                mediaSelector.dataset.type === 'quote' ||
                mediaSelector.dataset.type === 'grid'
            ) {

                html += '<a class="editor-action editor-box-media-action" data-action="media-border" data-instance="'+ instanceId +'" data-figure="'+ figureId +'" title="'+ Editor.labels.border +'" tabindex="-1">'+ Lime.Asset.icon('border-style') +'</a>';

            }

			if(
				figureSelector.getAttribute('data-interactive') === 'true' &&
				(
					qsa(figureId+ ' > div[data-type]').length > 1 ||
					qsa(instanceId +' > figure[data-interactive="true"]').length > 1
				)
			) {
				html += '<a class="editor-action editor-box-media-action" data-action="media-move" data-instance="'+ instanceId +'" data-figure="'+ figureId +'" title="'+ Editor.labels.move +'" tabindex="-1">'+ Lime.Asset.icon('arrows-move') +'</a>';
			}

			if(
				figureSelector.getAttribute('data-interactive') === 'false' &&
				Editor.getBaseAncestor(figureSelector.parentElement) === null
			) {
				html += '<a class="editor-action editor-box-media-action" data-action="figure-move" data-instance="'+ instanceId +'" title="'+ Editor.labels.move +'" tabindex="-1">'+ Lime.Asset.icon('arrows-move') +'</a>';
			}


			if(mediaSelector.getAttribute('data-type') !== 'progress') {

				if(mediaSelector.getAttribute('data-xyz')) {

					let isCropable;

					if(
						(mediaSelector.getAttribute('data-xyz').slice(-1) === 'p' || mediaSelector.getAttribute('data-xyz').slice(-1) === 'j') &&
						mediaSelector.getAttribute('data-xyz-w') &&
						mediaSelector.getAttribute('data-xyz-h')
					) {

						const width = parseInt(mediaSelector.getAttribute('data-xyz-w'));
						const height = parseInt(mediaSelector.getAttribute('data-xyz-h'));

						const zoomFactor = Math.max(width, height) / Math.floor(2000 / ImageConf.imageCropRequiredFactor);

						isCropable = (zoomFactor > ImageConf.imageCropRequiredFactor);

					} else {
						isCropable = false;
					}

					if(isCropable) {
						html += '<a data-action="media-crop" class="editor-action editor-box-media-action" data-instance="'+ instanceId +'" data-type="editor" data-xyz="'+ mediaSelector.getAttribute('data-xyz') +'" title="'+ Editor.labels.crop +'"">'+ Lime.Asset.icon('crop') +'</a>';
					} else {
						html += '<a class="editor-action editor-box-media-action editor-box-media-action-disabled" title="'+ Editor.labels.cropResolution +'"">'+ Lime.Asset.icon('crop') +'</a>';
					}

				}

				if(mediaSelector.getAttribute('data-type') === 'image') {
					html += '<a data-action="media-configure" class="editor-action editor-box-media-action" data-instance="'+ instanceId +'" data-figure="'+ figureId +'" title="'+ Editor.labels.configure +'">'+ Lime.Asset.icon('gear-fill') +'</a>';
				}

			}

			html += '<a class="editor-action editor-box-media-action" data-action="media-remove" data-instance="'+ instanceId +'" data-figure="'+ figureId +'" title="'+ Editor.labels.delete +'" tabindex="-1">'+ Lime.Asset.icon('trash-fill') +'</a>';


			html += '</div>';
		html += '</div>';

		mediaSelector.insertAdjacentHTML('beforeend', html);

		EditorFormat.currentMedia[instanceId] = mediaSelector;

	};

	static hideMedia(instanceId) {

		qs(instanceId +' .editor-box-media', box => box.remove());

	};

	static updateLine(instanceId, range) {

		const instance = qs(instanceId);

		if(instance.getAttribute('data-figure') === '0') {
			return;
		}

		let selector = null;

		if(range !== null) {

			const rangeSelector = range.commonAncestorContainer;

			// Le <li> passe avant la liste : dans une liste, la ligne courante est l'item,
			// pas le <ul> entier — sans quoi la boîte se pose en tête de liste
			selector = Editor.getFirstAncestor(rangeSelector, 'LI') ||
				Editor.getFirstAncestor(rangeSelector, 'P') ||
                Editor.getFirstAncestor(rangeSelector, 'UL') ||
                Editor.getFirstAncestor(rangeSelector, 'OL') ||
                Editor.getFirstAncestor(rangeSelector, 'H2') ||
                Editor.getFirstAncestor(rangeSelector, 'H3') ||
                Editor.getFirstAncestor(rangeSelector, 'H4');

		}

		// Add selector if editor is empty (issue #2014)
		if(
			selector === null &&
			instance.childNodes.length === 1 &&
			EditorKeyboard.isEmptyLine(instance.firstChild)
		) {
			selector = instance.firstChild;
		}

		EditorFormat._toggleListLevel(instanceId, range);

		if(
			selector === null ||
			(selector.tagName === 'FIGURE' && selector.dataset.interactive === 'true') ||
			(
				EditorKeyboard.isEmptyLine(range.commonAncestorContainer) === false &&
				window.matchMedia('(max-width: 767px)').matches
			)
		) {
			EditorFormat.hideLine(instanceId);
		} else {
			EditorFormat.placeLine(instance, instanceId +'-box-line', selector);
		}


	};

	// Les actions de niveau n'ont de sens que dans une liste
	static _toggleListLevel(instanceId, range) {

		const nodeItem = (range === null) ? null : EditorKeyboard._getItem(range.commonAncestorContainer);

		qsa(instanceId +'-box-line .editor-action-list-level', node => {
			node.style.display = (nodeItem === null) ? 'none' : '';
		});

	};

	static hideLine(instanceId) {

		const boxSelector = qs(instanceId +'-box-line');

		if(boxSelector !== null) {
			boxSelector.style.display = 'none';
			EditorFormat.actionLineClose(instanceId);
		}

	};

	static placeLine(instance, boxId, selector) {

		const nodeComputedStyle = getComputedStyle(instance);
		const nodeMarginLeft = parseInt(nodeComputedStyle.getPropertyValue('padding-left'));

		const currentRectangle = qs(boxId).getBoundingClientRect();
		const rectangle = selector.getBoundingClientRect();

		// La boîte suit la ligne courante en hauteur, mais reste alignée sur le bloc de
		// premier niveau en largeur : sur un <li> imbriqué, suivre l'indentation la ferait
		// entrer dans le texte au lieu de rester dans la gouttière
		const nodeTop = Editor.getTopAncestor(selector);
		const rectangleGutter = (nodeTop === selector) ? rectangle : nodeTop.getBoundingClientRect();

		const currentTop = currentRectangle.top + window.scrollY;
		const currentLeft = currentRectangle.left + window.scrollX;

		const newTop = rectangle.top + window.scrollY - 8;

		let newLeft;

		if(window.matchMedia('(max-width: 699px)').matches) {
			newLeft = '';
		} else {
			newLeft = (rectangleGutter.left - nodeMarginLeft - 39);
		}

		const helperSelector = qs(boxId +' div.editor-box-line-helper');

		helperSelector.style.marginLeft = nodeMarginLeft +'px';

		// Silent helper if needed
		if(EditorKeyboard.isEmptyLine(selector)) {

			if(helperSelector.style.visibility === 'hidden') {

				if(EditorFigure.isRightOf(selector) || EditorFigure.isLeftOf(selector)) {
					helperSelector.innerHTML = '';
				} else if(instance.childNodes.length === 1) {
					helperSelector.innerHTML = instance.getAttribute('data-placeholder-empty');
				} else {
					helperSelector.innerHTML = instance.getAttribute('data-placeholder-focus');
				}

				helperSelector.style.visibility = 'visible';

			}

		} else {

			if(helperSelector.style.visibility === 'visible') {
				helperSelector.style.visibility = 'hidden';
			}

		}

		// No change in position
		if(
			Math.abs(currentTop - newTop) < 2 && // Allow 2px variation
			(newLeft === '' || Math.abs(currentLeft - newLeft) < 2)
		) {
			return;
		}

		const boxSelector = qs(boxId);

		boxSelector.style.display = 'flex';
		boxSelector.style.top = newTop +'px';
		boxSelector.style.left = newLeft +'px';

		const instanceId = '#'+ instance.id;

		const nodeTopPrevious = EditorFormat.currentLine[instanceId] ?? null;
		EditorFormat.currentLine[instanceId] = nodeTop;

		// Passer d'un item à l'autre d'une même liste ne fait que suivre le curseur :
		// l'utilisateur n'a pas changé de bloc, la barre reste ouverte
		if(
			nodeTop === nodeTopPrevious &&
			(nodeTop.tagName === 'UL' || nodeTop.tagName === 'OL')
		) {
			return;
		}

		EditorFormat.action('line-close', instanceId);

	};

	static displaySelection(instanceId, range) {

		const boxSelector = qs(instanceId + '-box-selection');

		const rectangle = range.getBoundingClientRect();

		const boxStyle = getComputedStyle(boxSelector);

		EditorFormat._restyleIcons(instanceId);

		boxSelector.style.display = 'flex';

		if(document.body.matches('[data-touch="no"]')) {

			boxSelector.style.top = (Math.floor(rectangle.top) + window.scrollY - parseInt(boxStyle.height) - 10) +'px';
			boxSelector.style.left = Math.max(10, (Math.floor(rectangle.left + rectangle.right - parseInt(boxStyle.width)) / 2)) +'px';

		}

	};

	static hideSelection(instanceId) {

		qs(instanceId +'-box-selection').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-color').value = '';

		EditorFormat._hideLink(instanceId);
		EditorFormat._hideSub(instanceId);

	};

	static actionExplorer(instanceId) {
		window.open(qs(instanceId).attr('data-figure-full-url'), '_blank').focus();
	}

	static actionFigureRemove(instanceId, selectedElement) {
		const figureRemove = qs(selectedElement.getAttribute('data-figure'));
		EditorFigure.remove(instanceId, figureRemove);
	}

	static actionLineSwap(instanceId) {
		if(qs(instanceId +'-box-line').classList.contains('selected')) {
			EditorFormat.action('line-close', instanceId);
		} else {
			EditorFormat.action('line-open', instanceId);
		}
	}

	static actionLineOpen(instanceId) {
		qs(instanceId +'-box-line').classList.add('selected');
	}

	static actionLineClose(instanceId) {
		qs(instanceId +'-box-line').classList.remove('selected');
	}

	static actionLinkOpen(instanceId, selectedElement) {

		if(EditorFormat._hasStyle('link-open')) {

			EditorRange.saveState();

			const nodes = EditorRange.browseTags('A');

			if(nodes.length === 1) {

				EditorFormat._displayLink(instanceId, nodes[0]);

			} else {

				if(confirm(Editor.labels.confirmRemoveLinks)) {

					for(let i = 0; i < nodes.length; i++) {
						EditorFormat._removeLink(nodes[i]);
					}

				}

				EditorRange.applyState();

				EditorFormat._restyleIcons(instanceId);

			}

		} else {
			EditorFormat._displayLink(instanceId, null);
		}

	}

	static actionLinkClose(instanceId) {

		EditorRange.restore();

		EditorFormat._closeLink(instanceId);

	}

	static actionLink(instanceId) {

		EditorRange.restore();

		EditorFormat._createLink(instanceId);

		EditorFormat._hideLink(instanceId);

	}

	static actionHeader(instanceId, tag) {

		EditorFormat._header(instanceId, tag.toUpperCase());
		EditorFormat._hideSub(instanceId);

	}

	static actionStyle(instanceId, action, value = null, css = false) {

		if(qs(instanceId +'-box-selection .editor-action[data-action="'+ action +'"]').classList.contains('disabled')) {
			return;
		}

		document.execCommand('styleWithCSS', false, css);
		document.execCommand(action, false, value);


	}

	static actionAlign(instanceId, action) {
		EditorFormat._align(instanceId, action);
	}

	static actionSizeOpen(instanceId) {

		if(qs(instanceId +'-box-selection .editor-action[data-action="size-open"]').classList.contains('disabled')) {
			return;
		}

		EditorFormat._displaySub(instanceId, 'size');

	}

	static actionSize(instanceId, size) {

		EditorFormat._wrapText(instanceId, 'data-size', (size === 'normal') ? null : size);
		EditorFormat._hideSub(instanceId);

	}

	static actionHighlight(instanceId) {

		EditorFormat._wrapText(instanceId, 'data-highlight', EditorFormat._hasStyle('highlight') ? null : 'true');

	}

	static _displaySub(instanceId, name) {

		qs(instanceId +'-box-selection .editor-box-selection-icons').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-'+ name).style.display = 'flex';

	}

	static _hideSub(instanceId) {

		qs(instanceId +'-box-selection .editor-box-selection-size').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-header').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-align').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-icons').style.display = 'flex';

	}

	static _getSelectedTexts(range, instance) {

		const root = (range.commonAncestorContainer.nodeType === Node.TEXT_NODE) ? range.commonAncestorContainer.parentNode : range.commonAncestorContainer;

		const texts = [];

		if(range.commonAncestorContainer.nodeType === Node.TEXT_NODE) {
			texts.push(range.commonAncestorContainer);
		} else {

			const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);

			while(walker.nextNode()) {
				if(range.intersectsNode(walker.currentNode)) {
					texts.push(walker.currentNode);
				}
			}

		}

		return texts.filter(text => (
			instance.contains(text) &&
			text.parentElement.closest('figure') === null &&
			text.nodeValue.length > 0 &&
			(text !== range.startContainer || range.startOffset < text.nodeValue.length) &&
			(text !== range.endContainer || range.endOffset > 0)
		));

	}

	static _getTextValue(text, attribute, instance) {

		for(let node = text.parentElement; node !== null && node !== instance; node = node.parentElement) {
			if(node.tagName === 'SPAN' && node.hasAttribute(attribute)) {
				return node.getAttribute(attribute);
			}
		}

		return null;

	}

	static _getSelectionValue(attribute) {

		const range = EditorRange.get();

		if(range === null || range.collapsed) {
			return undefined;
		}

		const instance = Editor.getEditorNode(range.commonAncestorContainer);

		if(instance === null) {
			return undefined;
		}
		const values = [...new Set(EditorFormat._getSelectedTexts(range, instance).map(text => EditorFormat._getTextValue(text, attribute, instance)))];

		return (values.length === 1) ? values[0] : undefined;

	}

	// Une taille en em posée dans une autre se cumulerait : les spans porteurs qui débordent de la sélection
	// sont scindés à ses bornes, et ceux du dedans perdent l'attribut avant que la nouvelle valeur soit posée
	static _wrapText(instanceId, attribute, value) {

		const range = EditorRange.get();
		const instance = qs(instanceId);

		if(range === null || range.collapsed || instance.contains(range.commonAncestorContainer) === false) {
			return;
		}

		let startNode = range.startContainer;
		let endNode = range.endContainer;
		const startOffset = range.startOffset;
		const endOffset = range.endOffset;

		if(endNode.nodeType === Node.TEXT_NODE && endOffset < endNode.nodeValue.length) {
			endNode.splitText(endOffset);
		}

		if(startNode.nodeType === Node.TEXT_NODE && startOffset > 0 && startOffset < startNode.nodeValue.length) {

			const startNodeNew = startNode.splitText(startOffset);

			if(endNode === startNode) {
				endNode = startNodeNew;
			}

			startNode = startNodeNew;

		}

		const rangeSplit = document.createRange();

		if(startNode.nodeType === Node.TEXT_NODE) {
			rangeSplit.setStart(startNode, (startNode === range.startContainer) ? startOffset : 0);
		} else {
			rangeSplit.setStart(startNode, startOffset);
		}

		if(endNode.nodeType === Node.TEXT_NODE) {
			rangeSplit.setEnd(endNode, endNode.nodeValue.length);
		} else {
			rangeSplit.setEnd(endNode, endOffset);
		}

		const texts = EditorFormat._getSelectedTexts(rangeSplit, instance);

		if(texts.length === 0) {
			return;
		}

		const textFirst = texts[0];
		const textLast = texts[texts.length - 1];

		EditorFormat._splitAncestors(textFirst, attribute, instance, true);
		EditorFormat._splitAncestors(textLast, attribute, instance, false);

		// Le parent se lit avant l'écriture : un span vidé de ses attributs est déroulé et quitte l'arbre
		texts.forEach(text => {

			let node = text.parentElement;

			while(node !== null && node !== instance) {

				const parent = node.parentElement;

				if(node.tagName === 'SPAN' && node.hasAttribute(attribute)) {
					EditorFormat._setSpanValue(node, attribute, null);
				}

				node = parent;

			}

		});

		if(value !== null) {

			texts.forEach(text => {

				const parent = text.parentElement;

				if(parent.tagName === 'SPAN' && parent.childNodes.length === 1) {
					EditorFormat._setSpanValue(parent, attribute, value);
				} else {

					const span = document.createElement('span');
					text.before(span);
					span.append(text);

					EditorFormat._setSpanValue(span, attribute, value);

				}

			});

			texts.forEach(text => EditorFormat._mergeSpan(text.parentElement));

		}

		const selection = window.getSelection();
		const rangeNew = document.createRange();

		rangeNew.setStart(textFirst, 0);
		rangeNew.setEnd(textLast, textLast.nodeValue.length);

		selection.removeAllRanges();
		selection.addRange(rangeNew);

		EditorRange.saveLast(rangeNew);
		EditorFormat._restyleIcons(instanceId);

		// execCommand émet `input`, une écriture directe dans le DOM non : c'est le seul signe de changement que lisent les appelants
		instance.dispatchEvent(new InputEvent('input', {bubbles: true, inputType: 'formatSetInlineTextStyle'}));

	}

	static _splitAncestors(text, attribute, instance, before) {

		for(let node = text.parentElement; node !== null && node !== instance; node = node.parentElement) {

			if(node.tagName !== 'SPAN' || node.hasAttribute(attribute) === false) {
				continue;
			}

			const rangeOut = document.createRange();

			if(before) {
				rangeOut.setStart(node, 0);
				rangeOut.setEndBefore(text);
			} else {
				rangeOut.setStartAfter(text);
				rangeOut.setEnd(node, node.childNodes.length);
			}

			if(rangeOut.toString() === '') {
				continue;
			}

			const clone = node.cloneNode(false);
			clone.append(rangeOut.extractContents());

			if(before) {
				node.before(clone);
			} else {
				node.after(clone);
			}

		}

	}

	static _setSpanValue(span, attribute, value) {

		if(value === null) {
			span.removeAttribute(attribute);
		} else {
			span.setAttribute(attribute, value);
		}

		if(attribute === 'data-highlight') {

			if(value === null) {
				span.classList.remove('util-mark');
			} else {
				span.classList.add('util-mark');
			}

			if(span.classList.length === 0) {
				span.removeAttribute('class');
			}

		}

		if(span.attributes.length === 0) {
			span.replaceWith(...span.childNodes);
		}

	}

	static _mergeSpan(span) {

		if(span === null || span.tagName !== 'SPAN') {
			return;
		}

		const previous = span.previousSibling;

		if(
			previous !== null &&
			previous.nodeType === Node.ELEMENT_NODE &&
			previous.tagName === 'SPAN' &&
			previous.attributes.length === span.attributes.length &&
			[...span.attributes].every(attribute => previous.getAttribute(attribute.name) === attribute.value)
		) {
			previous.append(...span.childNodes);
			span.remove();
		}

	}

	static actionHr(instanceId) {

		EditorHr.create(instanceId);
		EditorFormat.action('line-close', instanceId);

	}

	static actionQuote(instanceId) {

		EditorQuote.create(instanceId);
		EditorFormat.action('line-close', instanceId);

	}

	static actionList(instanceId, type) {

		// Déjà dans une liste : on change le type de la liste la plus proche.
		// Sans ça, getCurrentLine() remonte au <ul>, ne le voit pas comme une ligne
		// vide, et ajoute une seconde liste juste après la première
		const nodeItem = (EditorRange.last === null) ? null : EditorKeyboard._getItem(EditorRange.last.commonAncestorContainer);

		if(nodeItem !== null) {

			EditorRange.restore();

			EditorKeyboard.listType(nodeItem.parentElement, type);

		} else {

			const nodeCurrent = EditorFormat.getCurrentLine(instanceId, {onEmpty: function(baseNode) {
				return baseNode.nodeName === 'P' ? 'keep' : 'create'
			}});

			EditorKeyboard.list(nodeCurrent, type, true);

		}

		EditorFormat.action('line-close', instanceId);

	}

	static actionListLevel(instanceId, direction) {

		// Le clic sur le bouton a fait perdre le focus au contenteditable
		EditorRange.restore();

		EditorKeyboard.listLevel(window.getSelection(), direction);

		EditorFormat.action('line-close', instanceId);

	}

	static actionLineImage(instanceId) {

		qs(instanceId +'-format .editor-upload', node => {
			node.removeAttribute('data-figure');
			node.click();
		});

	}

	static actionFigureImage(instanceId, selectedElement) {

		qs(instanceId +'-format .editor-upload', node => {
			node.setAttribute('data-figure', selectedElement.getAttribute('data-figure'));
			node.click();
		});

	}

	static actionMediaBorder(instanceId) {

		const mediaSelector = EditorFormat.currentMedia[instanceId];

        const borders = ['solid-grey', 'solid-primary', 'solid-text', 'dashed-grey', 'dashed-primary', 'dashed-text'];

        if(mediaSelector.dataset.border === undefined) {
            mediaSelector.dataset.border = borders[0];
        } else {

            const index = borders.indexOf(mediaSelector.dataset.border);

            if(index === borders.length - 1) {
                delete mediaSelector.dataset.border;
            } else {
                mediaSelector.dataset.border = borders[index + 1];
            }

        }

	}

	static actionMediaRemove(instanceId) {

		const mediaSelector = EditorFormat.currentMedia[instanceId];

		if(confirm(Editor.labels.confirmRemoveMedia)) {

			EditorFigure.removeNode(instanceId, mediaSelector);

		}

	}

	static actionMediaCrop(instanceId) {

		const mediaSelector = EditorFormat.currentMedia[instanceId];

		const hash = mediaSelector.getAttribute('data-xyz');

		new EditorMedia().getCropByHash(mediaSelector, hash);

	}

	static actionMediaConfigure(instanceId) {

		const mediaSelector = EditorFormat.currentMedia[instanceId];

		EditorFormat.configure(instanceId, mediaSelector);

	}

	static actionShowEditorGrid(instanceId, selectedElement) {

		EditorFormat.hideLine(instanceId);

		new Ajax.Query(selectedElement)
			.url('/editor/:configureGrid')
			.fetch()
			.then(() => {

				const grid = qs('#panel-editor-grid');

				grid.setAttribute('data-instance', instanceId);

			});

	}

	static actionGrid(instanceId) {

		const form = qs('#editor-grid-form');
		const columns = parseInt(form.qs('[name="columns"]').value);
		const rows = parseInt(form.qs('[name="rows"]').value);

		EditorGrid.create(instanceId, columns, rows);
		EditorFormat.action('line-close', instanceId);

		qs('#panel-editor-grid', node => Lime.Panel.close(node));

	}

	static actionShowEditorVideo(instanceId, selectedElement) {

		EditorFormat.hideLine(instanceId);

		new Ajax.Query(selectedElement)
			.url('/editor/:configureVideo')
			.fetch()
			.then(() => {

				const video = qs('#panel-editor-video');

				video.setAttribute('data-instance', instanceId);
				const figureId = selectedElement.getAttribute('data-figure');

				if(figureId) {
					video.setAttribute('data-figure', figureId);
				} else {
					video.removeAttribute('data-figure');
				}

			});

	}

	static actionVideo(instanceId) {

		const video = qs('#panel-editor-video');

		const figureId = video.dataset.figure;

		EditorVideo.convert(instanceId, figureId, qs('#editor-video-value').value);

		video.value = '';

		qs('#panel-editor-video', node => Lime.Panel.close(node));

		EditorFormat.action('line-close', instanceId);

	}

	static actionResize(instanceId, selectedElement) {

		const size = selectedElement.getAttribute('data-size');
		const figureSelector = qs(selectedElement.getAttribute('data-figure'));

		EditorFigure.resize(instanceId, figureSelector, size);

		EditorFigure.reorganize(instanceId, figureSelector);

	}

	static actionMoveMediaOpen(instanceId) {

		qsa(instanceId +' figure[data-interactive="true"]', figure => {

			new Sortable(figure, {
				handle: '.editor-box-media',
				group: 'figure',
				draggable: 'div.editor-media',
				chosenClass: 'editor-media-moving',
				onStart: function(e) {

					EditorFormat.hideMedia(instanceId);
					EditorFormat.startMoving(instanceId);

				},
				onChange: function(e) {

					// On laisse un placeholder si on vide la figure
					qsa(instanceId +' figure[data-interactive="true"]', figureCheck => {

						figureCheck.qsa('div.editor-media-placeholder', placeholder => placeholder.remove());

						if(figureCheck.qsa('div.editor-media').length === 0) {
							let clone = EditorFormat.currentMedia[instanceId].cloneNode(true);
							clone.childNodes.forEach(child => child.remove());
							clone.classList.add('editor-media-placeholder');
							figureCheck.insertAdjacentElement('afterbegin', clone);
						}
					});

					EditorFigure.reorganizeInstance(instanceId);

				},
				onEnd: function(e) {

					EditorFormat.action('move-figure-close', instanceId);
					EditorFigure.reorganizeInstance(instanceId);

					EditorFormat.stopMoving(instanceId);

				}
			});

		});

	}

	static actionMoveMediaClose(instanceId) {

		qsa(instanceId +' figure[data-interactive="true"]', figure => {
			Sortable.get(figure)?.destroy();
		});

	}

	static actionMoveFigureOpen(instanceId) {

		new Sortable(qs(instanceId), {
			fallbackOnBody: true,
			onChange: function(e) {
				EditorFigure.reorganizeInstance(instanceId);
			},
			onEnd: function(e) {

				EditorFormat.action('move-figure-close', instanceId);
				EditorFigure.reorganizeInstance(instanceId);

			}
		});

	}

	static actionMoveFigureClose(instanceId) {
		Sortable.get(qs(instanceId))?.destroy();
	}

	static action(action, instanceId, selectedElement) {

		switch(action) {

			case 'explorer' :
				return this.actionExplorer(instanceId);

			case 'figure-remove' :
				return this.actionFigureRemove(instanceId, selectedElement);

			case 'line-swap' :
				return this.actionLineSwap(instanceId);

			case 'line-open' :
				return this.actionLineOpen(instanceId);

			case 'line-close' :
				return this.actionLineClose(instanceId);

			case 'link-open' :
				return this.actionLinkOpen(instanceId, selectedElement);

			case 'link-close' :
				return this.actionLinkClose(instanceId);

			case 'link' :
				return this.actionLink(instanceId);

			case 'header-open' :
				return this._displaySub(instanceId, 'header');

			case 'header-h2' :
			case 'header-h3' :
			case 'header-h4' :
			case 'header-p' :
				return this.actionHeader(instanceId, action.substring(7));

			case 'foreColor' :
				return this.actionStyle(instanceId, 'foreColor', 'inherit', true);

			case 'foreColorNew' :
				return this.actionStyle(instanceId, 'foreColor', selectedElement.value, true);

			case 'bold' :
			case 'italic' :
			case 'underline' :
				return this.actionStyle(instanceId, action);

			case 'align-left' :
			case 'align-right' :
			case 'align-center' :
			case 'align-justify' :
				this.actionAlign(instanceId, action);
				return this._hideSub(instanceId);

			case 'align-open' :
				return this._displaySub(instanceId, 'align');

			case 'size-open' :
				return this.actionSizeOpen(instanceId);

			case 'size-small' :
			case 'size-normal' :
			case 'size-large' :
				return this.actionSize(instanceId, action.substring(5));

			case 'highlight' :
				return this.actionHighlight(instanceId);

			case 'sub-close' :
				return this._hideSub(instanceId);

			case 'hr' :
				return this.actionHr(instanceId);

			case 'list-ul' :
				return this.actionList(instanceId, 'ul');

			case 'list-ol' :
				return this.actionList(instanceId, 'ol');

			case 'list-indent' :
				return this.actionListLevel(instanceId, 'indent');

			case 'list-outdent' :
				return this.actionListLevel(instanceId, 'outdent');

			case 'quote' :
				return this.actionQuote(instanceId);

			case 'line-image' :
				return this.actionLineImage(instanceId);

			case 'figure-image' :
				return this.actionFigureImage(instanceId, selectedElement);

			case 'media-border' :
				return this.actionMediaBorder(instanceId);

			case 'media-remove' :
				return this.actionMediaRemove(instanceId);

			case 'media-crop' :
				return this.actionMediaCrop(instanceId);

			case 'media-configure':
				return this.actionMediaConfigure(instanceId);

			case 'show-editor-grid':
				return this.actionShowEditorGrid(instanceId, selectedElement);

			case 'grid' :
				return this.actionGrid(instanceId);

			case 'show-editor-video':
				return this.actionShowEditorVideo(instanceId, selectedElement);

			case 'video' :
				return this.actionVideo(instanceId);

			case 'resize' :
				return this.actionResize(instanceId, selectedElement);

			case 'move-media-open' :
				return this.actionMoveMediaOpen(instanceId);

			case 'move-media-close' :
				return this.actionMoveMediaClose(instanceId);

			case 'move-figure-open' :
				return this.actionMoveFigureOpen(instanceId);

			case 'move-figure-close' :
				return this.actionMoveFigureClose(instanceId);

		}

	};

	static isMoving() {
		return EditorFormat.mediaMoving;
	};

	static startMoving(instanceId) {

		EditorFormat.mediaMoving = true;

		qsa(instanceId +' figure[data-interactive="true"]', figure => {

			const figcaption = figure.qs('figcaption');

			if(figcaption === null) {
				return;
			}

			const paddingBottom = figcaption.offsetHeight + parseInt(getComputedStyle(figure).paddingBottom);

			figure.style.paddingBottom = paddingBottom +'px';
			figcaption.classList.add('figure-moving');

		});

	};

	static stopMoving(instanceId) {

		EditorFormat.mediaMoving = false;

		qsa(instanceId +' figure[data-interactive="true"]', figure => {

			const figcaption = figure.qs('figcaption');

			if(figcaption === null) {
				return;
			}

			figure.style.paddingBottom = '';
			figcaption.classList.remove('figure-moving');

		});

	};

	static configure(instanceId, mediaSelector) {

		new Ajax.Query(mediaSelector)
			.url('/editor/:configureMedia')
			.body({
				instanceId: instanceId,
				url: mediaSelector.getAttribute('data-url'),
				xyz: mediaSelector.getAttribute('data-xyz'),
				title: mediaSelector.getAttribute('data-title'),
				link: mediaSelector.getAttribute('data-link'),
				figureSize: mediaSelector.parentElement.querySelectorAll('div.editor-media[data-type]').length
			})
			.fetch();

	};

	static _align(instanceId, action) {

		// browseBaseTags() et non browseTags() : l'alignement porte sur les blocs,
		// et browse() ne remonte pas jusqu'au bloc quand la sélection dépasse un nœud texte
		const nodes = EditorRange.browseBaseTags(['P', 'H2', 'H3', 'H4']);

		if(nodes.length === 0) {
			return;
		}

		// Calc new alignment
		const align = action.substring(6);

		// Apply new alignment
		for(let i = 0; i < nodes.length; i++) {
			nodes[i].style.textAlign = align;
		}

		EditorFormat._restyleIcons(instanceId);

		return true;

	};

	static _header(instanceId, tag) {

		const nodes = EditorRange.browseBaseTags(['P', 'H2', 'H3', 'H4']);

		if(nodes.length === 0) {
			return;
		}

		const newNodes = nodes.map(node => (node.tagName === tag) ? node : node.renameNode(tag));

		const firstNode = newNodes[0];
		const lastNode = newNodes[newNodes.length - 1];

		const newRange = document.createRange();
		newRange.setStart(firstNode, 0);
		newRange.setEnd(lastNode, lastNode.childNodes.length);

		const selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange(newRange);

		EditorRange.saveLast(newRange);

		// Même raison que dans _wrapText() : renameNode() n'émet pas `input`
		qs(instanceId).dispatchEvent(new InputEvent('input', {bubbles: true, inputType: 'formatBlock'}));

		EditorFormat._restyleIcons(instanceId);

		return true;

	};

	static nodeLink = null;

	static _displayLink(instanceId, node) {

		EditorFormat.nodeLink = node;

		qs(instanceId +'-box-selection .editor-box-selection-icons').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-link').style.display = 'block';
		qs(instanceId +'-box-selection .editor-box-selection-link input').focus();

		if(node !== null) {

			qs(instanceId +'-box-selection [type="submit"]').innerHTML = Editor.labels.linkFilledSubmit;
			qs(instanceId +'-box-selection [data-action="link-close"]').innerHTML = Editor.labels.linkFilledClose;

			qs(instanceId +'-box-selection .editor-box-selection-link input').value = node.getAttribute('href');

		} else {

			qs(instanceId +'-box-selection [type="submit"]').innerHTML = Editor.labels.linkEmptySubmit;
			qs(instanceId +'-box-selection [data-action="link-close"]').innerHTML = Editor.labels.linkEmptyClose;

		}

	};

	static _closeLink(instanceId) {

		if(EditorFormat.nodeLink !== null) {
			if(confirm(Editor.labels.confirmRemoveLink)) {
				EditorFormat._removeLink(EditorFormat.nodeLink);
				EditorFormat._restyleIcons(instanceId);
			}
		}

		EditorFormat._hideLink(instanceId);

	};

	static _hideLink(instanceId) {

		qs(instanceId +'-box-selection .editor-box-selection-icons').style.display = 'flex';
		qs(instanceId +'-box-selection .editor-box-selection-link').style.display = 'none';
		qs(instanceId +'-box-selection .editor-box-selection-link input').value = '';

		EditorFormat.nodeLink = null;

	};

	static _createLink(instanceId) {

		let link = qs(instanceId + '-box-selection .editor-box-selection-link input').value;

		if(link.match(/^http[s]?\:\/\//) === null) {
			link = 'https://'+ link;
		}

		if(EditorFormat.nodeLink === null) {

			document.execCommand('styleWithCSS', false, false);
			document.execCommand("createLink", false, link);

		} else {
			EditorFormat.nodeLink.setAttribute('href', link);
		}

	};

	static _removeLink(node) {

		const newElements = document.createDocumentFragment();

		while(node.childNodes.length > 0) {
			newElements.appendChild(node.childNodes[0]);
		}


		const updateStartContainer = (node === EditorRange.startContainer);
		const updateEndContainer = (node === EditorRange.endContainer);

		node.parentElement.replaceChild(newElements, node);

		if(updateStartContainer) {
			EditorRange.newStartContainer = newElements[0];
		}

		if(updateEndContainer) {
			EditorRange.newEndContainer = newElements[0];
		}

	};

	static _hasStyle(command, node) {

		switch(command) {

			case 'link-open' :

				return (EditorRange.browseTags('A').length > 0);

			case 'foreColor' :
				return (EditorRange.browseTags('SPAN').reduce((value, item) => value + (item.hasAttribute('style') === false || item.getAttribute('style').includes('inherit')) ? 0 : 1, 0) > 0);

			case 'header-open' :

				const nodeHeader = EditorRange.browseBaseTags(['H2', 'H3', 'H4'])[0] ?? null;

				return (nodeHeader !== null);

			case 'header-h2' :
			case 'header-h3' :
			case 'header-h4' :
			case 'header-p' :

				const tag = command.substring(7).toUpperCase();
				const nodesHeader = EditorRange.browseBaseTags(['P', 'H2', 'H3', 'H4']);

				return (nodesHeader.length > 0 && nodesHeader.every(node => node.tagName === tag));

			case 'highlight' :
				return (EditorFormat._getSelectionValue('data-highlight') === 'true');

			case 'size-open' :

				const size = EditorFormat._getSelectionValue('data-size');

				return (size !== undefined && size !== null);

			case 'size-small' :
			case 'size-large' :
				return (EditorFormat._getSelectionValue('data-size') === command.substring(5));

			case 'size-normal' :
				return (EditorFormat._getSelectionValue('data-size') === null);

			case 'align-left' :
			case 'align-center' :
			case 'align-right' :
			case 'align-justify' :

				// Même parcours que _align() : l'état affiché doit refléter ce que l'action ferait
				const nodeAlign = EditorRange.browseBaseTags(['P', 'H2', 'H3', 'H4'])[0] ?? null;

				if(nodeAlign !== null) {

					const selectedAlignement = nodeAlign.style.textAlign;
					const commandAlignement = command.substring(6);

					if(selectedAlignement === 'left' || selectedAlignement === 'center' || selectedAlignement === 'right' || selectedAlignement === 'justify') {
						return (selectedAlignement === commandAlignement);
					} else {
						return ('left' === commandAlignement);
					}

				} else {
					return false;
				}

			default :
				return document.queryCommandState(command);


		}

	};

	static _restyleIcons(instanceId) {

		const nodes = EditorRange.browseBaseTags(['P', 'H2', 'H3', 'H4']);

		if(nodes.length === 0) {

			EditorFormat._iconHide(instanceId, 'align-open');
			EditorFormat._iconHide(instanceId, 'align-separator');
			EditorFormat._iconHide(instanceId, 'header-open');

		} else {

			const aligns = ['align-left', 'align-center', 'align-right', 'align-justify'];

			aligns.forEach(align => EditorFormat._iconStyle(instanceId, align));

			const alignCurrent = aligns.find(align => EditorFormat._hasStyle(align)) ?? 'align-left';
			const alignIcon = qs(instanceId +'-box-selection .editor-box-selection-align [data-action="'+ alignCurrent +'"]').innerHTML;

			EditorFormat._iconShow(instanceId, 'align-open');
			qs(instanceId +'-box-selection [data-action="align-open"]').innerHTML = alignIcon;

			EditorFormat._iconShow(instanceId, 'align-separator');

		}

		EditorFormat._iconStyle(instanceId, 'foreColor');
		EditorFormat._iconStyle(instanceId, 'highlight');

		EditorFormat._iconStyle(instanceId, 'size-small');
		EditorFormat._iconStyle(instanceId, 'size-normal');
		EditorFormat._iconStyle(instanceId, 'size-large');

		EditorFormat._iconStyle(instanceId, 'header-h2');
		EditorFormat._iconStyle(instanceId, 'header-h3');
		EditorFormat._iconStyle(instanceId, 'header-h4');
		EditorFormat._iconStyle(instanceId, 'header-p');

		if(nodes.length > 0 && EditorFormat._iconStyle(instanceId, 'header-open')) {
			EditorFormat._iconDisable(instanceId, 'link-open');
			EditorFormat._iconDisable(instanceId, 'size-open');
		} else {
			EditorFormat._iconStyle(instanceId, 'size-open');
		}

        EditorFormat._iconStyle(instanceId, 'bold');
        EditorFormat._iconStyle(instanceId, 'italic');
        EditorFormat._iconStyle(instanceId, 'underline');

	};

	static _iconHide(instanceId, command) {

		const selector = qs(instanceId + '-box-selection [data-action="' + command + '"]');
		selector.classList.add('hidden');

	};

	static _iconShow(instanceId, command) {

		const selector = qs(instanceId + '-box-selection [data-action="' + command + '"]');
		selector.classList.remove('hidden');

	};

	static _iconDisable(instanceId, command) {

		const selector = qs(instanceId + '-box-selection [data-action="' + command + '"]');

		selector.classList.remove('hidden');
		selector.classList.remove('selected');
		selector.classList.add('disabled');
		selector.childNodes.forEach(node => node.classList.remove('selected'));

	};

	static _iconStyle(instanceId, command) {

		const selector = qs(instanceId + '-box-selection [data-action="' + command + '"]');

		selector.classList.remove('hidden');
		selector.classList.remove('disabled');

		const result = EditorFormat._hasStyle(command);

		if(result) {

			switch(command) {

				case 'header-open' :
					selector.childNodes.forEach(node => node.classList.remove('selected'));
					selector.qs('span.editor-header').classList.add('selected');
					break;

                case 'foreColor' :

				    const colors = EditorRange.browseTags('SPAN').reduce((value, item) => {
                        if(
                            item.hasAttribute('style') &&
                            item.style.color != undefined
                        ) {
                            value[value.length] = item.style.color;
                        } else {
                            value[value.length] = null;
                        }
                        return value;
                    }, []);

                    const uniqueColors = colors.filter((value, index) => colors.indexOf(value) === index);

                    selector.classList.add('selected');

                    if(uniqueColors.length === 1) {
                        selector.nextSibling.value = EditorFormat.convertColor(uniqueColors[0]);
                    } else {
                        selector.nextSibling.value = '#000000';
                    }
					break;

				default :
					selector.classList.add('selected');
					break;

			}

			return true;

		} else {

			switch(command) {

                case 'foreColor' :
                    selector.nextSibling.value = '#000000';
					break;

			}

			selector.classList.remove('selected');
			selector.childNodes.forEach(node => node.classList.remove('selected'));
			return false;

		}

	};

    static convertColor(code) {
        return code.startsWith('#') ? code : ((color) => '#' + (1 << 24 | color[0] << 16 | color[1] << 8 | color[2]).toString(16).slice(1))(code.match(/\d+/g));
    }

	// Create a new and empty figure
	static newLine(instanceId, newNode, options) {

		const nodeCurrent = this.getCurrentLine(instanceId, options);

		if(nodeCurrent.parentElement !== null) {

            nodeCurrent.parentElement.replaceChild(newNode, nodeCurrent);

			// A figure can't be the last node of a contenteditable
			if(newNode.nextSibling === null) {

				const nodeP = EditorParagraph.getEmpty();

				newNode.insertAdjacentElement('afterend', nodeP);

			}

			EditorFigure.updateHack(newNode);

		}

	};

    static getCurrentLine(instanceId, options) {

		options = options || {};

        if(options.onEmpty === undefined) {
            options.onEmpty = () => 'new';
        }

		let nodeHost;

		if(options.nodeHost) {
			nodeHost = options.nodeHost;
		} else if(EditorRange.last && EditorRange.last.commonAncestorContainer) {
			nodeHost = EditorRange.last.commonAncestorContainer;
		} else {
			nodeHost = qs(instanceId).lastChild;
		}

		let nodeCurrent;

		if(Editor.isEditorNode(nodeHost)) {
            nodeCurrent = EditorParagraph.getEmpty();
			nodeHost.appendChild(nodeCurrent);
		} else {

			const baseNode = Editor.getBaseAncestor(nodeHost) || nodeHost;

			if(
                options.onEmpty(baseNode) === 'keep' ||
                EditorKeyboard.isEmptyLine(baseNode)
            ) {
                nodeCurrent = baseNode;
			} else {
                nodeCurrent = EditorParagraph.getEmpty();
				baseNode.insertAdjacentElement('afterend', nodeCurrent);
			}

		}

        return nodeCurrent;
        
    }

}

// Manipulates selected text
class EditorRange {

	// Last valid range pinged
	static last = null;

	// Ping update EditorRange.last variable
	static saveLast(range) {

		EditorRange.last = range;

		return EditorRange.last;

	};

	static get() {

		const currentSelection = window.getSelection();

		if(currentSelection.rangeCount === 0) {
			return null;
		}

		return currentSelection.getRangeAt(0);

	};

	// Chrome remplace l'espace tapée en fin de ligne par une espace insécable, que \s couvre
	static getSlash(instance) {

		const caret = EditorRange.get();

		if(
			caret === null ||
			caret.collapsed === false ||
			caret.startContainer.nodeType !== Node.TEXT_NODE ||
			caret.startOffset === 0 ||
			caret.startContainer.nodeValue[caret.startOffset - 1] !== '/' ||
			Editor.isNodeIn(caret.startContainer, 'FIGURE')
		) {
			return null;
		}

		let line = caret.startContainer.parentElement;

		while(
			line !== instance &&
			['P', 'LI', 'H2', 'H3', 'H4', 'BLOCKQUOTE'].includes(line.tagName) === false
		) {
			line = line.parentElement;
		}

		const before = document.createRange();
		before.setStart(line, 0);
		before.setEnd(caret.startContainer, caret.startOffset - 1);

		const text = before.toString();

		if(
			text !== '' &&
			/\s$/.test(text) === false
		) {
			return null;
		}

		const slash = document.createRange();
		slash.setStart(caret.startContainer, caret.startOffset - 1);
		slash.setEnd(caret.startContainer, caret.startOffset);

		return slash;

	};

	static split(instance, slash) {

		const after = document.createRange();
		after.setStart(slash.endContainer, slash.endOffset);
		after.setEnd(instance, instance.childNodes.length);

		const wrapper = document.createElement('div');
		wrapper.append(after.extractContents());

		slash.deleteContents();

		const isEmpty = node => (
			node.nodeType === Node.ELEMENT_NODE &&
			node.textContent.trim() === '' &&
			node.querySelector('img, iframe, hr, figure') === null &&
			['IMG', 'IFRAME', 'HR', 'FIGURE'].includes(node.tagName) === false
		);

		while(
			instance.lastChild !== null &&
			(isEmpty(instance.lastChild) || (instance.lastChild.nodeType === Node.TEXT_NODE && instance.lastChild.nodeValue.trim() === ''))
		) {
			instance.lastChild.remove();
		}

		while(
			wrapper.firstChild !== null &&
			(isEmpty(wrapper.firstChild) || (wrapper.firstChild.nodeType === Node.TEXT_NODE && wrapper.firstChild.nodeValue.trim() === ''))
		) {
			wrapper.firstChild.remove();
		}

		if(instance.childNodes.length === 0) {
			instance.append(EditorParagraph.getEmpty());
		}

		return wrapper.innerHTML;

	};

	// Check if range belongs to an instance EditorRange.last variable
	static isIn(range, id) {

		if(range === null) {
			return false;
		}

		let container = range.commonAncestorContainer;

		while(container) {

			if('#'+ container.id === id) {
				return true;
			}

			container = container.parentElement;

		}

		return false;

	};

	// Exécute une transformation qui déplace des nœuds, puis repose le curseur
	// dessus. Déplacer un nœud commence par le détacher : sans cela le navigateur
	// replie la sélection sur l'ancien parent, et l'action suivante part à côté
	static keep(callback) {

		const selection = window.getSelection();

		if(selection.rangeCount === 0) {
			callback();
			return;
		}

		const range = selection.getRangeAt(0);

		const startContainer = range.startContainer;
		const startOffset = range.startOffset;
		const endContainer = range.endContainer;
		const endOffset = range.endOffset;

		callback();

		// Les nœuds de texte suivent l'élément déplacé, la position reste donc valide
		if(
			startContainer.isConnected &&
			endContainer.isConnected
		) {
			EditorRange.replace(startContainer, startOffset, endContainer, endOffset);
		}

	};

	// Restore previous range
	static restore() {

		if(EditorRange.last !== null) {

			const selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(EditorRange.last);

		}

	};

	// Replace current range at the end of the given node
	static replaceEnd(container) {

		const startContainer = container;
		let startOffset = container.childNodes.length;

		if(
			startContainer.childNodes.length > 0 &&
			startContainer.lastChild.nodeType === Node.ELEMENT_NODE &&
			startContainer.lastChild.tagName === 'BR'
		) {
			startOffset--;
		}

		return EditorRange.replace(startContainer, startOffset);

	};

	// Replace current range with new values
	static replace(startContainer, startOffset, endContainer, endOffset) {

		const newRange = document.createRange();

		if(
			typeof endContainer === 'undefined' || typeof endOffset === 'undefined' ||
			(startContainer === endContainer && startOffset === endOffset)
		) {

			newRange.setStart(startContainer, startOffset);
			newRange.collapse(true);

		} else {

			newRange.setStart(startContainer, startOffset);
			newRange.setEnd(endContainer, endOffset);

		}

		const selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange(newRange);

		EditorRange.last = newRange;

	};

	// Remove current range
	static remove() {

		const selection = window.getSelection();

		if(selection.rangeCount > 0) {
			selection.removeAllRanges();
		}

		EditorRange.last = null;

	};

	// Checks if a selection is a the beginning of a line
	// returns null if selection is not a the beginning
	// return the root node of the line otherwise
	static lineStart(selection) {

		if(selection.rangeCount === 0) {
			return null;
		}

		const range = selection.getRangeAt(0);

		// Range is not collapsed
		if(range.collapsed === false) {
			return null;
		}

		// Range does not start at offset 0
		if(range.startOffset > 0) {
			return null;
		}

		return EditorRange._lineCheck(range.startContainer, 'previousSibling');

	};

	// Checks if a selection is a the end of a contenteditable
	// returns null if selection is not a the end
	// return the root node of the line otherwise
	static lineEnd(selection) {

		if(selection.rangeCount === 0) {
			return null;
		}

		const range = selection.getRangeAt(0);

		// Range is not collapsed
		if(range.collapsed === false) {
			return null;
		}

		// If TEXT_NODE
		// Range does not start at the end of
		if(
			range.startContainer.nodeType === Node.TEXT_NODE &&
			range.startOffset !== range.startContainer.nodeValue.length
		) {
			return null;
		}

		return EditorRange._lineCheck(range.startContainer, 'nextSibling');

	};

	static _lineCheck(node, sibling) {

		if(Editor.isBaseNode(node)) {

			return node;

		} else {

			for(; ; ) {

				const nodeSibling = EditorNode.siblingNoWhitespaces(node, sibling);

				// Node has a sibling, so we are not at the beginning/end of the line
				if(nodeSibling !== null) {
					return null;
				}

				node = node.parentElement;

				if(node === null) {
					return null;
				}

				// Top of a text in figure

				// Figcaption
				if(node.tagName === 'FIGCAPTION') {
					return node;
				}
				// Editor and blockquote
				if(node.parentElement.getAttribute('contenteditable') === 'true') {
					return node;
				}

			}

			return null;

		}

	};

	// Renvoie le range courant, en ramenant sa fin sur le contenu réellement sélectionné
	// Un triple clic (ou un glisser jusqu'au début d'une ligne) termine la sélection au
	// tout début du bloc SUIVANT (endOffset === 0) : sans correction, ce bloc est traité
	// comme sélectionné alors que l'utilisateur n'en a rien pris
	static getCurrentNormalized() {

		const current = window.getSelection().getRangeAt(0).cloneRange();

		if(
			current.collapsed ||
			current.endOffset > 0
		) {
			return current;
		}

		// Remonter jusqu'au premier ancêtre qui a un frère précédent
		let node = current.endContainer;

		while(
			node !== null &&
			node.previousSibling === null
		) {
			node = node.parentElement;
		}

		if(node === null) {
			return current;
		}

		const nodePrevious = node.previousSibling;
		const offsetPrevious = (nodePrevious.nodeType === Node.TEXT_NODE) ? nodePrevious.nodeValue.length : nodePrevious.childNodes.length;

		// Ne reculer que si la sélection reste non vide
		const check = document.createRange();
		check.setStart(current.startContainer, current.startOffset);
		check.setEnd(nodePrevious, offsetPrevious);

		if(check.collapsed) {
			return current;
		}

		current.setEnd(nodePrevious, offsetPrevious);

		return current;

	};

	// Browse each node of the current range
	static browse(callback) {

		const current = EditorRange.getCurrentNormalized();

		if(
			current.collapsed ||
			current.startContainer === current.endContainer
		) {

			EditorRange._browseAncestors(current.startContainer, callback);

		} else {

			let node = current.startContainer;
			const nodeEnd = current.endContainer;

			// Browse node until we match the common ancestor container
			try {

				while(node !== null) {

					// Browse siblings
					EditorRange._browseSiblings(node, nodeEnd, callback);

					if(node.parentElement === current.commonAncestorContainer) {
						throw 'end';
					}

					callback(node.parentElement);

					node = node.parentElement.nextSibling;

				}

			} catch(e) {
				if(e !== 'end') {
					throw e;
				}
			}


			EditorRange._browseAncestors(current.commonAncestorContainer, callback);

		}

	};

	// Browse each base node of the current range
	static browseBase(callback) {

		const current = EditorRange.getCurrentNormalized();

		if(
			current.collapsed ||
			current.startContainer === current.endContainer
		) {

            const nodeBase = Editor.getBaseAncestor(current.startContainer);

            if(nodeBase !== null) {
                callback(nodeBase);
            }

		} else {

			let node = current.startContainer;
			const nodeEnd = current.endContainer;

            let nodeBase = Editor.getBaseAncestor(node);

            while(nodeBase !== null) {

                if(nodeBase.nodeType === Node.ELEMENT_NODE) {
                    callback(nodeBase);
                }

                if(nodeBase.contains(nodeEnd)) {
                    break;
                }

                nodeBase = nodeBase.nextSibling;


            }

		}

	};

	static browseTags(tags) {

        if(typeof tags === 'string') {
            tags = [tags];
        }

		const nodesFound = [];

		EditorRange.browse(function(node) {

			if(tags.includes(node.nodeName)) {
				nodesFound[nodesFound.length] = node;
			}

		});

		return nodesFound;

	};

	static browseBaseTags(tags) {

        if(typeof tags === 'string') {
            tags = [tags];
        }

		const nodesFound = [];

		EditorRange.browseBase(function(node) {

			if(tags.includes(node.nodeName)) {
				nodesFound[nodesFound.length] = node;
			}

		});

		return nodesFound;

	};

	static _browseSiblings(node, nodeEnd, callback) {

		let sibling = node;

		for(; ; ) {

			if(sibling.nodeType === Node.ELEMENT_NODE) {

				callback(sibling);

				if(sibling.childNodes.length > 0) {
					EditorRange._browseSiblings(sibling.childNodes[0], nodeEnd, callback);
				}


			}

			if(sibling === nodeEnd) {
				throw 'end';
			}

			if(sibling.nextSibling === null) {
				break;
			}

			sibling = sibling.nextSibling;

		}

	};

	static _browseAncestors(node, callback) {

		// Ignore text nodes
		if(node.nodeType === Node.TEXT_NODE) {
			node = node.parentElement;
		}

		while(
			// Root node (contenteditable div)
			// Stop looping
			node &&
			Editor.isEditorNode(node) === false
		) {

			callback(node);

			node = node.parentElement;

		}

	};

	static state = false;

	static startContainer = null;
	static newStartContainer = null;
	static startOffset = 0;

	static endContainer = null;
	static newEndContainer = null;
	static endOffset = 0;

	static saveState() {

		// Prepare a new range
		if(window.getSelection().rangeCount > 0) {

			const newRange = window.getSelection().getRangeAt(0).cloneRange();

			EditorRange.startContainer = newRange.startContainer;
			EditorRange.startOffset = newRange.startOffset;
			EditorRange.endContainer = newRange.endContainer;
			EditorRange.endOffset = newRange.endOffset;

		} else {

			EditorRange.startContainer = null;
			EditorRange.startOffset = 0;
			EditorRange.endContainer = null;
			EditorRange.endOffset = 0;

		}

		EditorRange.newStartContainer = null;
		EditorRange.newEndContainer = null;

		EditorRange.state = true;

	};

	static isStateSaved() {
		return EditorRange.state;
	};

	static applyState() {

		if(EditorRange.newStartContainer !== null || EditorRange.newEndContainer !== null) {

			const newStartContainer = EditorRange.newStartContainer ? EditorRange.newStartContainer : EditorRange.startContainer;
			const newEndContainer = EditorRange.newEndContainer ? EditorRange.newEndContainer : EditorRange.endContainer;

			// Refresh range
			if(newStartContainer !== null && newEndContainer !== null) {

				EditorRange.replace(
					newStartContainer, EditorRange.startOffset,
					newEndContainer, EditorRange.endOffset
				);

				EditorRange.newStartContainer = null;
				EditorRange.newEndContainer = null;

			}

		}

		EditorRange.state = false;

	};

};

// Tools for figure handling
class EditorFigure {

	static counter = 0;

	static select(instanceId, figureSelector) {

		const figureId = '#' + figureSelector.id;

		if(
			figureSelector.getAttribute('data-interactive') === 'false' || // Figure is not interactive
			figureSelector.qs('.editor-box-figure') !== null // Figure box already exists
		) {
			return;
		}

		EditorFigure.deselect(instanceId);

        const onlyImage = qs(instanceId).dataset.figureOnlyImage;
		const boxId = figureId + '-box-figure';

		if(qs(boxId) === null) {

			let html = '<div id="'+ boxId.substring(1) +'" class="editor-box-figure" data-whitespace="1">';
				html += '<div class="editor-box-figure-container">';
                    if(onlyImage === '0') {

                        html += '<button class="editor-action editor-action-plus" data-action="figure-image" data-instance="'+ instanceId +'" title="'+ Editor.labels.imageFigure +'">'+ Lime.Asset.icon('image') +''+ Lime.Asset.icon('plus-circle') +'<span class="editor-box-figure-label">'+ Editor.labels.imageLabel +'</span></button>';
                        html += '<span class="separator">&nbsp;</span>';
                        html += '<button class="editor-action editor-action-plus" data-action="show-editor-video" data-instance="'+ instanceId +'" title="'+ Editor.labels.videoFigure +'">'+ Lime.Asset.icon('camera-video-fill') +''+ Lime.Asset.icon('plus-circle') +'<span class="editor-box-figure-label">'+ Editor.labels.videoLabel +'</span></button>';
                        html += '<span class="separator">&nbsp;</span>';
                        html += '<div>';
                            html += '<button class="dropdown-toggle" data-dropdown="bottom-start" title="'+ Editor.labels.resize +'">'+ Lime.Asset.icon('grid-fill') +'<span class="editor-box-figure-label hide-md-down">'+ Editor.labels.resizeLabel +'</span></button>';
                            html += '<div class="dropdown-list editor-box-figure-list">';
                                html += '<button class="dropdown-item" data-action-run="1" data-action="resize" data-size="compressed" data-instance="'+ instanceId +'" data-figure="'+ figureId +'">'+ Editor.labels.resizeCompress +'</button>';
                                html += '<button class="dropdown-item" data-action-run="1" data-action="resize" data-size="left" data-instance="'+ instanceId +'" data-figure="'+ figureId +'">'+ Editor.labels.resizeLeft +'</button>';
                                html += '<button class="dropdown-item" data-action-run="1" data-action="resize" data-size="right" data-instance="'+ instanceId +'" data-figure="'+ figureId +'">'+ Editor.labels.resizeRight +'</button>';
                            html += '</div>';
                        html += '</div>';

                        html += '<span class="separator">&nbsp;</span>';

                    }
					html += '<a class="editor-action" data-action="figure-move" data-instance="'+ instanceId +'" title="'+ Editor.labels.moveFigure +'" tabindex="-1">'+ Lime.Asset.icon('arrows-move') +'</a>';
					html += '<span class="separator">&nbsp;</span>';
					html += '<button class="editor-action" data-action="figure-remove" data-instance="'+ instanceId +'" title="'+ Editor.labels.removeFigure +'">'+ Lime.Asset.icon('trash-fill') +'</button>';
				html += '</div>';
			html += '</div>';

			html += '<div class="editor-box-figcaption" data-whitespace="1">';
				html += '<span class="editor-action" title="'+ Editor.labels.captionLimit +'">?</span>/'+ Editor.conf.captionLimit;
			html += '</div>';

			figureSelector.insertAdjacentHTML('beforeend', html);

			EditorFigure.updateCaption(figureSelector);

		}

        if(onlyImage === '0') {

            qsa(boxId +' [data-action="resize"]', node => node.classList.remove('selected'));
            qs(boxId +' [data-action="resize"][data-size="'+ figureSelector.getAttribute('data-size') +'"]', node => node.classList.add('selected'));

            const medias = qsa(figureId + ' > div[data-type]').length;

            if(medias > 1) {
                qs(boxId +' [data-action="resize"][data-size="left"]').style.display = 'none';
                qs(boxId +' [data-action="resize"][data-size="right"]').style.display = 'none';
            } else {
                qs(boxId +' [data-action="resize"][data-size="left"]').style.display = 'block';
                qs(boxId +' [data-action="resize"][data-size="right"]').style.display = 'block';
            }

        }

		qsa(boxId +' .editor-action', action => action.setAttribute('data-figure', figureId));

		if(
			qs(figureId +' figcaption:focus') === null &&
			qs(figureId +' blockquote:focus') === null
		) {
			EditorRange.remove();
		}

		figureSelector.classList.add('selected');

	};

	static deselect(instanceId) {

		const instance = qs(instanceId);

		if(instance === null) {
			return;
		}

		const figureSelector = EditorFigure.getSelected(instance);

		if(figureSelector !== null) {

			figureSelector.classList.remove('selected');

			EditorFormat.action('move-media-close', instanceId);

		}

		qs(instanceId +' .editor-box-figure')?.remove();
		qs(instanceId +' .editor-box-figcaption')?.remove();

	};

	// Returns selected figure of an instance
	static getSelected(instanceSelector) {
		return instanceSelector.qs('figure.selected');
	};

	static selectCaption(instanceId) {

		qs(instanceId +' .editor-box-figcaption', figcaption => {

			if(figcaption.isHidden()) {
				figcaption.style.display = 'block';
			}

		});


	};

	static deselectCaption(instanceId) {

		qs(instanceId +' .editor-box-figcaption', figcaption => {

			if(figcaption.isVisible()) {
				figcaption.style.display = 'none';
			}

		});

	};

	static updateCaption(figureSelector) {

		let length = figureSelector.qs('figcaption').textContent.length;
		const maxLength = Editor.conf.captionLimit;

		figureSelector.qs('.editor-box-figcaption > span', (span) => {

			if(length > maxLength) {
				span.style.color = 'red';
				length = Lime.Asset.icon('exclamation-triangle-fill') +' '+ length;
			} else {
				span.style.color = '';
			}

			span.innerHTML = length;

		});

	};

	// Create a new and empty figure
	static create(instanceId, options) {

        options = options || {};

		const interactive = typeof options.interactive !== 'undefined' ? options.interactive : true;

		const nodeFigureId = 'editor-figure-' + EditorFigure.counter++;
		const nodeFigure = document.createElement('figure');
		nodeFigure.setAttribute('data-widget', 'figure');
		nodeFigure.setAttribute('data-caption', 'true');
		nodeFigure.setAttribute('data-interactive', interactive ? 'true' : 'false');
		nodeFigure.setAttribute('contenteditable', 'false');
		nodeFigure.setAttribute('id', nodeFigureId);

		if(interactive) {

			nodeFigure.setAttribute('data-size', 'compressed');

            const nodeFigcaption = document.createElement('figcaption');
            nodeFigcaption.setAttribute('contenteditable', 'true');
            nodeFigcaption.setAttribute('placeholder', Editor.labels.captionFigure);

            nodeFigure.appendChild(nodeFigcaption);

        }
        
        EditorFormat.newLine(instanceId, nodeFigure, options);

        return '#'+ nodeFigureId;

	};

	// Remove an existing figure
	static remove(instanceId, figureSelector, noConfirm) {

		if(
			noConfirm ||
			confirm(Editor.labels.confirmRemoveFigure)
		) {

			figureSelector.remove();

			EditorFigure.deselect(instanceId);

		}

	};

	// Resize an existing figure
	static resize(instanceId, figureSelector, size) {

		const figureId = '#'+ figureSelector.id;

		qsa('[data-figure="'+ figureId +'"][data-action="resize"]', node => node.classList.remove('selected'));
		qs('[data-figure="'+ figureId +'"][data-action="resize"][data-size="'+ size +'"]').classList.add('selected');

		figureSelector.setAttribute('data-size', size);

		figureSelector.qs('figcaption').style.display = (size === 'left' || size === 'right') ? 'none' : 'block';

		Lime.Dropdown.purge();

	};

	// Add a selector in a figure
	static addSelector(instanceId, figureSelector, mediaSelector) {

		const figcaptionSelector = figureSelector.qs('figcaption');

		if(figcaptionSelector !== null) {
			figcaptionSelector.insertAdjacentElement('beforebegin', mediaSelector);
		} else {
			figureSelector.insertAdjacentElement('beforeend', mediaSelector);
		}

		EditorFigure.updateHack(figureSelector);

		if(figcaptionSelector !== null) {
			figcaptionSelector.style.display = 'block';
		}

		// Reorganize all nodes
		EditorFigure.reorganize(instanceId, figureSelector);

		// Figure actions may have change
		EditorFigure.select(instanceId, figureSelector);

	};

	// Add a node in a figure
	static addNode(instanceId, figureId, nodeValue, type, defaultWidth, defaultHeight, attributes) {

		const node = document.createElement('div');
		node.setAttribute('class', 'editor-media');
		node.setAttribute('data-type', type);

		if(defaultWidth !== null) {
			node.setAttribute('data-w', defaultWidth);
		}

		if(defaultHeight !== null) {
			node.setAttribute('data-h', defaultHeight);
		}

		EditorFigure._applyAttributes(node, attributes);
		EditorFigure._applyValue(node, nodeValue);

		const figureSelector = qs(figureId);

		// If figure is displayed left, center it
		if(figureSelector.getAttribute('data-size') === 'left' || figureSelector.getAttribute('data-size') === 'right') {
			EditorFigure.resize(instanceId, figureSelector, 'compressed');
		}


		EditorFigure.addSelector(instanceId, figureSelector, node);

		return node;

	};

	// Replace an existing node in a figure
	static replaceNode(node, nodeValue, type, attributes) {

		node.setAttribute('data-type', type);

		EditorFigure._applyAttributes(node, attributes);
		EditorFigure._applyValue(node, nodeValue);

	};

	static _applyValue(node, nodeValue) {

		while(node.childNodes.length > 0) {
			node.removeChild(node.firstChild);
		}

		if(nodeValue !== null) {

			if(typeof nodeValue === 'string') {
				node.innerHTML = nodeValue;
			} else {
				node.appendChild(nodeValue);
			}
		}

	};

	static _applyAttributes(node, attributes) {

		if(typeof attributes !== 'undefined') {
			for(let name in attributes) {
				node.setAttribute('data-'+ name, attributes[name]);
			}
		}

	};

	// Hack to avoid selection issue in figures
	static updateHack(figureSelector) {

		figureSelector.childNodes.filter('span').forEach(node => node.remove());

		const nodeSpan = document.createElement('span');
		nodeSpan.setAttribute('contenteditable', 'false');

		figureSelector.appendChild(nodeSpan);

	};

	// Remove a node from a figure
	static removeNode(instanceId, nodeSelector) {

		const figureSelector = nodeSelector.parentElement;

		nodeSelector.remove();

		// Reorganize all nodes
		EditorFigure.reorganize(instanceId, figureSelector);

		// Figure actions may have change
		EditorFigure.select(instanceId, figureSelector);

	};

	// Reorganize nodes in all figures of an instance
	static reorganizeInstance(instanceId) {

		qsa(instanceId +' figure[data-interactive="true"]', figureSelector => {
			EditorFigure.reorganize(instanceId, figureSelector);
		});

	};

	// Reorganize nodes in a figure
	// Drop figure if it has no node any more
	static reorganize(instanceId, figureSelector) {

		const children = figureSelector.qsa('.editor-media').length;

		if(children === 0) {
			EditorFigure.remove(instanceId, figureSelector, true);
			return;
		}

		if(figureSelector.getAttribute('data-interactive') === 'true') {

			new GalleryEditor(figureSelector, {
				container: '.editor-media',
				onReorganized: () => {
					ReaditorFigure.reorganize(figureSelector);
				}
			}).listenResize();

		}

	};

	// Insert an empty line before a figure
	static insertLineBefore(instanceId, figureSelector) {

		const node = EditorParagraph.getEmpty();

		figureSelector.parentElement.insertBefore(node, figureSelector);

		EditorRange.replace(node, 0);

	};

	// Check if this TOP selector is right of a data-size="left" figure
	static isRightOf(selector) {
		return EditorFigure.isPositionOf(selector, 'left')
	};

	// Check if this TOP selector is right of a data-size="right" figure
	static isLeftOf(selector) {
		return EditorFigure.isPositionOf(selector, 'right')
	};

	// Check if this TOP selector is right of a data-size="left" figure
	static isPositionOf(selector, compare) {

		let figureSelector = null;
		let currentSibling = selector;

		while(currentSibling.previousSibling !== null) {

			currentSibling = currentSibling.previousSibling;

			if(
				currentSibling.tagName === 'FIGURE' &&
				currentSibling.getAttribute('data-size') === compare
			) {
				figureSelector = currentSibling;
				break;
			}

		}

		if(figureSelector === null) {
			return false;
		}

		const rectangle = selector.getBoundingClientRect();
		const figureRectangle = figureSelector.getBoundingClientRect();

		return (figureRectangle.bottom >= rectangle.top);

	};

};

// Handle hr in the editor
class EditorHr {

	static create(instanceId) {

		const figureId = EditorFigure.create(instanceId, {
			interactive: false
		});

		if(figureId === null) {
			return;
		}

		const nodeHr = document.createElement('div');
		nodeHr.setAttribute('class', 'editor-hr');
		nodeHr.innerHTML = '&#149; &#149; &#149;';

		EditorFigure.addNode(instanceId, figureId, nodeHr, 'hr', null, null, {});
	};

}

// Handle quotes in the editor
class EditorQuote {

	static create(instanceId) {

		const figureId = EditorFigure.create(instanceId, {
			interactive: false
		});

		if(figureId === null) {
			return;
		}

		const icons = Editor.labels['quoteIcons'];
		const firstIcon = Object.keys(icons)[0];

		const nodeQuote = document.createElement('div');
		nodeQuote.setAttribute('class', 'editor-quote');

		const nodeImage = document.createElement('div');
		nodeImage.setAttribute('class', 'editor-quote-image');
		nodeImage.setAttribute('data-action', 'quote-image');
		nodeImage.setAttribute('title', Editor.labels.quoteIcon);
		nodeImage.innerHTML = Lime.Asset.icon(icons[firstIcon]);

		nodeQuote.appendChild(nodeImage);

		const nodeBlockquote = document.createElement('blockquote');
		nodeBlockquote.setAttribute('contenteditable', 'true');
		nodeBlockquote.setAttribute('tabindex', '-1');

		const nodeP = EditorParagraph.getEmpty();
		nodeBlockquote.appendChild(nodeP);

		nodeQuote.appendChild(nodeBlockquote);

		EditorFigure.addNode(instanceId, figureId, nodeQuote, 'quote', null, null, {'about': firstIcon});
		EditorRange.replace(nodeP, 0);

	};

	static next(imageSelector) {

		const quoteSelector = imageSelector.parentElement.parentElement;
		const icons = Editor.labels['quoteIcons'];

		let newIcon;

		if(quoteSelector.dataset.about !== undefined) {

			const iconCurrent = quoteSelector.dataset.about;
			let iconFirst = null;
			let iconSelectNext = 'search';

			Object.entries(icons).forEach(([key, value]) => {

				if(iconFirst === null) {
					iconFirst = key;
				}

				if(iconSelectNext == 'pick') {
					newIcon = key;
					iconSelectNext = 'done';
				}

				if(key === iconCurrent) {
					iconSelectNext = 'pick';
				}

			});

			if(iconSelectNext === 'pick') {
				newIcon = iconFirst;
			}

		} else {
			newIcon = 'quote';
		}

		quoteSelector.setAttribute('data-about', newIcon);
		imageSelector.innerHTML = Lime.Asset.icon(icons[newIcon]);


	};

}

// Handle grids in the editor
class EditorGrid {

	static create(instanceId, columns, rows) {

		const figureId = EditorFigure.create(instanceId, {
			interactive: false
		});

		if(figureId === null) {
			return;
		}

		columns = Math.min(Math.max(1, columns), 4);
		rows = Math.min(Math.max(1, rows), 5);

		const cells = columns * rows;

		const nodeGrid = document.createElement('div');
		nodeGrid.setAttribute('class', 'editor-grid');
		nodeGrid.setAttribute('data-columns', columns);

		for(let i = 0; i < cells; i++) {
			nodeGrid.appendChild(this.newCell());
		}

		EditorFigure.addNode(instanceId, figureId, nodeGrid, 'grid');

	};

	static newCell() {

		const nodeCell = document.createElement('div');
		nodeCell.setAttribute('contenteditable', 'true');
		nodeCell.setAttribute('tabindex', '-1');

		const nodeP = EditorParagraph.getEmpty();
		nodeCell.appendChild(nodeP);

		return nodeCell;

	}

}

// Handle videos in the editor
class EditorVideo {

	static convert(instanceId, figureId, url) {

		const data = EditorVideo.match(url);

		if(data !== null) {

			if(typeof figureId === 'undefined') {
				figureId = EditorFigure.create(instanceId);
			}

			const nodeVideo = document.createElement('iframe');
			nodeVideo.setAttribute('frameborder', '0');
			nodeVideo.setAttribute('allowfullscreen', 'true');
			nodeVideo.setAttribute('src', data.url);

			EditorFigure.addNode(instanceId, figureId, nodeVideo, 'video', 854, 480, data);

		}

	};

	// La liste des hébergeurs vient du serveur (EditorSetting::VIDEO), qui recompose l'adresse de la même façon à l'enregistrement
	static match(url) {

		for(const source in Editor.conf.video) {

			const video = Editor.conf.video[source];
			const match = new RegExp(video.pattern).exec(url);

			if(match !== null) {
				return {
					source: source,
					url: video.embed.replace('{id}', match[1])
				};
			}

		}

		return null;

	};

};

// Handle paragraphes
class EditorParagraph {

	static counter = Date.now();

	// Creates and returns an empty line
	static getEmpty() {

		const node = document.createElement('p');
		node.setAttribute('data-location', EditorParagraph.tick());
		node.appendChild(document.createElement('br'));

		return node;

	};

	static tick() {
		EditorParagraph.counter++;
		return EditorParagraph.counter;
	};

	// Repair a paragraph node
	static repair(node) {

		// Add <br> if empty and missing
		if(node.childNodes.length === 0) {

			const nodeBr = document.createElement('br');
			node.appendChild(nodeBr);

		}

		// Add tick
		if(
			node.hasAttribute('data-location') === false &&
			(node.parentElement === null || node.parentElement.tagName !== 'BLOCKQUOTE')
		) {
			node.setAttribute('data-location', EditorParagraph.tick());
		}

	};

};

// Handle images in the editor
class EditorImage {

	static upload(instanceId, figureId, e, files) {

		const speed = qs(instanceId).getAttribute('data-speed');

		function onLoad(resolve, file, position, img) {

			EditorFormat.action('line-close', instanceId);

			// Get figure that will contain the image
			if(figureId === null) {
				figureId = EditorFigure.create(instanceId);
			}

			const previewUrl = ImageLoader.getPreview(file, img, 750, 80);

			let progressCss;
			if(previewUrl === null) {
				progressCss = 'background-color: #ccc';
			} else {
				progressCss = 'background-image: url(' + previewUrl + ')';
			}

			const progressId = 'progress-' + new Date().getTime();
			const progressHtml = ImageLoader.start(progressId, progressCss);

			const figureNode = EditorFigure.addNode(instanceId, figureId, progressHtml, 'progress', img.naturalWidth, img.naturalHeight);

			if(speed === 'slow') {

				const resizedUrl = ImageLoader.getPreview(file, img, 2000, 88);

				if(resizedUrl !== null) {
					file = ImageLoader.dataUrlToFile(resizedUrl);
				}

			}

			const data = new FormData();
			data.append('file', file);
			data.append('type', 'editor');

			const onFail = () => {

				ImageLoader.remove(progressId);
				EditorFigure.removeNode(instanceId, figureNode);

				resolve();

			};

			new (class extends Ajax.Query {

				done(data) {

					const media = data.media;

					const extension = media.hash.slice(-1);

					let url;
					if(extension === 'g') { // Original file for GIF
						url = media.urls.original;
					} else {
						url = media.urls.l;
					}

					const nodeAttributes = {
						'url': url,
						'xyz': media.hash,
						'xyz-w': media.width,
						'xyz-h': media.height,
						'xyz-version': media.version,
						'color': media.color,
					};

					// Apply attributes to temporary node
					figureNode.setAttribute('data-type', 'image');
					EditorFigure._applyAttributes(figureNode, nodeAttributes);

					ImageLoader.remove(progressId);
					resolve();

					// Load final image
					const nodeDisplay = new Image();
					nodeDisplay.src = url;
					nodeDisplay.onload = function() {

						const nodeImage = document.createElement('div');
						nodeImage.setAttribute('class', 'editor-image');
						nodeImage.appendChild(nodeDisplay);

						EditorFigure.replaceNode(figureNode, nodeImage, 'image', nodeAttributes);

					}

				};

				showErrors(data) {

					if(data.errors) {
						Lime.Alert.showStaticErrors(data.errors);
					} else {
						Lime.Alert.showStaticError(ImageMessage['internalUpload']);
					}

					onFail();

				};

			})()
				.url('/media/:put')
				.body(data)
				.xmlHttpRequest(xhr => {

					xhr.upload.addEventListener("progress", function(e) {

						if(e.lengthComputable) {

							const percentComplete = Math.floor((e.loaded / e.total) * 100);
							ImageLoader.update(progressId, percentComplete);

						}

					}, false);

				}).then(null, () => onFail());

		};

		function onError(resolve) {
			resolve();
		};

		let parallel;

		if(speed === 'fast') {
			parallel = 4;
		} else {
			parallel = 2;
		}

		ImageStorage.upload(files, 50, 'editor', onLoad, onError, parallel);

	};

	static download(url) {

		url = url.replace(/\/editor\/([0-9]{3,4})\//, '/editor/');

		if(url.indexOf('?') > -1) {
			url += '&';
		} else {
			url += '?';
		}
		window.open(url + 'download=1');

	};

};

// Sauvegarde périodique du contenu en cours de saisie, pour le retrouver
// après un rechargement ou une sortie de page accidentelle
class EditorDraft {

	static prefix = 'editor-draft:';
	static delay = 5000;

	// Par instance : {key, htmlSaved, htmlStart, dirty, disabled}
	static instances = {};

	static timer = null;

	static start(instanceId, htmlSaved) {

		const instance = qs(instanceId);

		// Pas d'identifiant stable, pas de brouillon
		const key = instance?.dataset.draft;

		if(!key) {
			return;
		}

		this.instances[instanceId] = {
			key: key,
			htmlSaved: htmlSaved,
			htmlStart: instance.innerHTML,
			dirty: false,
			disabled: false
		};

		this.restore(instanceId);

		if(this.timer === null) {
			this.timer = setInterval(() => this.saveAll(), this.delay);
		}

	};

	static end(instanceId) {

		delete this.instances[instanceId];

		if(
			this.timer !== null &&
			Object.keys(this.instances).length === 0
		) {
			clearInterval(this.timer);
			this.timer = null;
		}

	};

	// Signalé par l'observateur de mutations
	static touch(instanceId) {

		if(this.instances[instanceId] !== undefined) {
			this.instances[instanceId].dirty = true;
		}

	};

	static saveAll() {
		Object.keys(this.instances).forEach(instanceId => this.save(instanceId));
	};

	static save(instanceId) {

		const draft = this.instances[instanceId];

		if(
			draft === undefined ||
			draft.dirty === false ||
			draft.disabled
		) {
			return;
		}

		const instance = qs(instanceId);

		if(instance === null) {
			this.end(instanceId);
			return;
		}

		// Un envoi en cours laisse dans le DOM des aperçus en base64 et des nœuds
		// temporaires : on attend qu'il se termine, comme le fait la soumission
		if(instance.qsa('[data-type="progress"]').length > 0) {
			return;
		}

		draft.dirty = false;

		const html = instance.innerHTML;

		// Revenu au point de départ : le brouillon n'a plus lieu d'être
		if(html === draft.htmlStart) {
			this.forget(draft.key);
			return;
		}

		this.write(instanceId, draft, html);

	};

	static write(instanceId, draft, html) {

		const value = JSON.stringify({html: html, at: Date.now()});

		try {
			sessionStorage.setItem(this.prefix + draft.key, value);
			return true;
		} catch(e) {
		}

		// Stockage plein : on libère les brouillons les plus anciens et on retente
		if(this.evict(draft.key)) {

			try {
				sessionStorage.setItem(this.prefix + draft.key, value);
				return true;
			} catch(e) {
			}

		}

		// Toujours impossible : on cesse d'essayer pour cette instance plutôt que
		// de lever une exception toutes les 5 secondes
		draft.disabled = true;
		console.warn('EditorDraft : stockage saturé, brouillon désactivé pour '+ draft.key);

		return false;

	};

	// Supprime les brouillons des autres éditeurs, du plus ancien au plus récent
	static evict(keyKeep) {

		const entries = [];

		for(let i = 0; i < sessionStorage.length; i++) {

			const name = sessionStorage.key(i);

			if(
				name.startsWith(this.prefix) === false ||
				name === this.prefix + keyKeep
			) {
				continue;
			}

			let at = 0;

			try {
				at = JSON.parse(sessionStorage.getItem(name)).at || 0;
			} catch(e) {
			}

			entries[entries.length] = {name: name, at: at};

		}

		if(entries.length === 0) {
			return false;
		}

		entries.sort((a, b) => a.at - b.at).forEach(entry => sessionStorage.removeItem(entry.name));

		return true;

	};

	static read(key) {

		try {

			const value = sessionStorage.getItem(this.prefix + key);

			return (value === null) ? null : JSON.parse(value);

		} catch(e) {
			return null;
		}

	};

	static forget(key) {

		try {
			sessionStorage.removeItem(this.prefix + key);
		} catch(e) {
		}

	};

	// Purge les brouillons des éditeurs d'un formulaire enregistré avec succès
	static flushForm(form) {

		form.qsa('.editor[data-draft]', instance => {

			EditorDraft.forget(instance.dataset.draft);

			const draft = EditorDraft.instances['#'+ instance.id];

			if(draft !== undefined) {

				draft.dirty = false;
				draft.htmlStart = instance.innerHTML;

			}

		});

	};

	static restore(instanceId) {

		const draft = this.instances[instanceId];
		const stored = this.read(draft.key);

		if(
			stored === null ||
			stored.html === undefined ||
			stored.html === draft.htmlStart
		) {
			return;
		}

		const instance = qs(instanceId);

		instance.innerHTML = stored.html;

		EditorFigure.reorganizeInstance(instanceId);

		this.showBar(instanceId);

	};

	static showBar(instanceId) {

		const instance = qs(instanceId);
		const wrapper = instance.parentElement;

		if(wrapper.qs('.editor-draft-bar') !== null) {
			return;
		}

		let html = '<div class="editor-draft-bar">';
			html += '<span>'+ Editor.labels.draftRestored +'</span>';
			html += '<a data-action="draft-cancel" data-instance="'+ instanceId +'" class="btn btn-xs btn-color-primary">'+ Editor.labels.draftCancel +'</a>';
		html += '</div>';

		instance.insertAdjacentHTML('beforebegin', html);

	};

	static hideBar(instanceId) {
		qs(instanceId).parentElement.qs('.editor-draft-bar', node => node.remove());
	};

	// Retour à la version servie par le serveur
	static cancel(instanceId) {

		const draft = this.instances[instanceId];

		if(draft === undefined) {
			return;
		}

		const instance = qs(instanceId);

		instance.innerHTML = draft.htmlSaved;

		EditorFigure.reorganizeInstance(instanceId);

		draft.htmlStart = instance.innerHTML;
		draft.dirty = false;

		this.forget(draft.key);
		this.hideBar(instanceId);

	};

};

document.delegateEventListener('click', '.editor-draft-bar [data-action="draft-cancel"]', function(e) {

	e.preventDefault();

	EditorDraft.cancel(this.getAttribute('data-instance'));

});

// Observe mutation in the contenteditable div
class EditorMutation {

	static observers = {};

	static observe(instanceId) {

		if(typeof EditorMutation.observers[instanceId] !== 'undefined') {
			return;
		}

		const config = {
			childList: true,
			characterData: true,
			subtree: true
		};

		let observer;

		observer = new MutationObserver(function(mutations) {

			// Get updated nodes
			const nodes = new Array;

			const instance = document.querySelector(instanceId);

			if(instance === null) {
				EditorMutation.disconnect(instanceId);
				return;
			}

			// Temporary shutdown the observer as we can change the dom
			observer.disconnect();

			EditorRange.saveState();

			mutations.forEach(function(mutation) {

				// Cleanup because browser can add/delete internal nodes
				for(let i = 0; i < mutation.addedNodes.length; i++) {
					nodes[nodes.length] = mutation.addedNodes[i];
				}

				for(let i = 0; i < mutation.removedNodes.length; i++) {
					const nodeKey = nodes.indexOf(mutation.removedNodes[i]);
					if(nodeKey !== -1) {
						delete nodes[nodeKey];
					}
				}

				const targetNode = mutation.target;

				if(
					nodes.indexOf(targetNode) === -1 &&
					Editor.isEditorNode(targetNode) === false
				) {
					nodes[nodes.length] = targetNode;
				}

			});

			nodes.forEach(function(node) {

				if(node.nodeType === Node.ELEMENT_NODE) {
					EditorMutation._nodeElement(instanceId, node);
				} else if(node.nodeType === Node.TEXT_NODE) {
					EditorMutation._nodeText(instanceId, node);
				}

			});

			EditorMutation._organizeInstance(instance);

			EditorRange.applyState();

			EditorDraft.touch(instanceId);

			// Wakeup observer
			observer.observe(target, config);

		});

		// select the target node
		const target = document.querySelector(instanceId);

		// pass in the target node, as well as the observer options
		observer.observe(target, config);

		EditorMutation.observers[instanceId] = observer;

	};

	static disconnect(instanceId) {

		if(typeof EditorMutation.observers[instanceId] === 'undefined') {
			return;
		}

		EditorMutation.observers[instanceId].disconnect();

		delete EditorMutation.observers[instanceId];

	};

	static _organizeInstance(instance) {

		let currentP = null;

		for(let i = 0; i < instance.childNodes.length; i++) {

			const node = instance.childNodes[i];

			// Garbage collector for nasty browsers
			// Destroy empty lists and p
			if(browser.isSafari === false) {

				if(
                    node.nodeType === Node.ELEMENT_NODE &&
                    ['P', 'UL', 'OL', 'H2', 'H3', 'H4'].includes(node.tagName) &&
					node.innerHTML === ''
				) {
					node.parentElement.removeChild(node);
					i--;
					continue;
				}

			}

			// Put orphan nodes in paragraph
			if(
				(
					node.nodeType === Node.TEXT_NODE && (
						(node.nodeValue.trim() === '' && currentP !== null) ||
						node.nodeValue.trim() !== ''
					)
				) ||
				(
                    node.nodeType === Node.ELEMENT_NODE &&
                    Editor.isBaseNode(node) === false
                )
			) {

				if(currentP === null) {

					currentP = document.createElement('P');

					instance.insertBefore(currentP, node);
					i++;

				}

				currentP.appendChild(node);
				i--;


			} else {
				currentP = null;
			}

		}

		// Check that last line if an empty <p>
		if(
			instance.childNodes.length > 0 &&
			instance.lastChild.tagName !== 'P'
		) {

			instance.appendChild(EditorParagraph.getEmpty());

		}
	};

	// Ensure HTML consistency
	static _nodeText(instanceId, node) {

		const parentElement = node.parentElement;

		if(parentElement === null) {
			return false;
		}

		if(
			parentElement.getAttribute('contenteditable') === 'true' &&
			parentElement.tagName !== 'FIGCAPTION'
		) {

			const nodeText = node.cloneNode(true);

			const nodeP = document.createElement('p');
			nodeP.setAttribute('data-location', EditorParagraph.tick());
			nodeP.appendChild(nodeText);

			if(EditorRange.startContainer === node) {
				EditorRange.newStartContainer = nodeText;
			}

			if(EditorRange.endContainer === node) {
				EditorRange.newEndContainer = nodeText;
			}

			node.parentElement.replaceChild(nodeP, node);


		} else {

			switch(parentElement.tagName) {

				case 'FIGCAPTION' :

					// Figcaption
					EditorFigure.updateCaption(parentElement.parentElement);

					break;

				default : // Nothing to do
					return false;

			}

		}

		return true;

	};

	static _nodeElement(instanceId, node) {

		// Parent node expected
		if(node.parentElement === null) {
			return false;
		}

		// Special case for data-widget node
		if(node.hasAttribute('data-widget')) {

			if(node.tagName === 'FIGURE') {
				EditorMutation._figureClean(node);
			}

			return false;

		}

		let nodeCheck = node;

		while(nodeCheck) {

			// Remove the node if it is within a <figcaption>
			if(
				node.tagName !== 'FIGCAPTION' &&
				nodeCheck.tagName === 'FIGCAPTION'
			) {
				return EditorNode.removeRecursive(node);
			}

			// Ok, this is contenteditable (ie: editor or blockquote)
			if(
				nodeCheck.getAttribute('contenteditable') === 'true' &&
				nodeCheck.tagName !== 'FIGCAPTION'
			) {
				break;
			}

			// Update figcaption if necessary
			if(
				nodeCheck.tagName === 'FIGCAPTION' &&
				nodeCheck.parentElement.tagName === 'FIGURE' &&
				nodeCheck.parentElement.hasAttribute('data-widget')
			) {
				EditorFigure.updateCaption(nodeCheck.parentElement);
			}

			// Do nothing if the node or a parent has data-widget attribute
			if(nodeCheck.hasAttribute('data-widget')) {
				return false;
			}

			nodeCheck = nodeCheck.parentElement;

		}

		return EditorMutation._nodeCleanRecursive(instanceId, node);

	};

	// Clean figure
	static _figureClean(node) {

		// Check for Copy/paste
		// Figures must have a unique ID

		const instance = Editor.getEditorNode(node);

		if(instance.qsa('[id="'+ node.id +'"]').length > 1) {
			node.setAttribute('id', 'editor-figure-'+ EditorFigure.counter++);
		}

	};

	// Clean a node and fix/remove it if not valid
	static _nodeCleanRecursive(instanceId, node) {

		// Handle child nodes
		for(let i = 0; i < node.childNodes.length; i++) {

			const childNode = node.childNodes[i];

			if(childNode.nodeType === Node.ELEMENT_NODE) {

				if(EditorMutation._nodeElement(instanceId, childNode) === 'removed') {
					i--;
				}

			}

		}

		const nodeTag = node.tagName;

		switch(nodeTag) {

			case 'STRONG' :
				return EditorMutation._nodeReplace(node, 'B');

			case 'EN' :
				return EditorMutation._nodeReplace(node, 'I');

			case 'STRIKE' :
				return EditorMutation._nodeReplace(node, 'S');

			// Clean attributes
			case 'B' :
			case 'I' :
			case 'S' :
			case 'U' :
			case 'H2' :
			case 'H3' :
			case 'H4' :
				return EditorMutation._nodeAttributes(node, [], 'style');

			case 'IMG' :
				return EditorNode.remove(node);

			case 'UL' :
			case 'OL' :
			case 'LI' :

				EditorNode.trim(node);
				return EditorMutation._nodeAttributes(node, [], [], true);

			case 'BR' :
				// Convert top <br> to <p>
				if(Editor.isEditorNode(node.parentElement)) {
					return EditorMutation._nodeReplace(node, 'P');
				}
				// Drop <br> that are not in an empty <p>
				else if(
					node.parentElement.tagName !== 'P' ||
					node.parentElement.childNodes.length !== 1
				) {
					//return EditorNode.remove(node);
					// Firefox est débile
					// Il rajoute un <br> en fin de paragraphe quand on tape "espace" alors que les autres navigateurs mettent un &nbsp;
					return EditorMutation._nodeAttributes(node);
				} else {
					return EditorMutation._nodeAttributes(node);
				}

			case 'DIV' :

				// <div> within grids are angels
				if(node.parentElement.parentElement.getAttribute('data-type') === 'grid') {
					return EditorMutation._nodeAttributes(node, ['contenteditable', 'tabindex']);
				}

				// Top <p/div/..> are angels
				if(
					Editor.isEditorNode(node.parentElement) ||
					node.parentNode.getAttribute('contenteditable') === 'true'
				) {
					EditorNode.trim(node);
					return EditorMutation._nodeReplace(node, 'P');
				}
				// Others <p/div/...> are evil
				else {
					return EditorNode.remove(node);
				}

			case 'P' :

				// Top <p/div/..> are angels
				if(
					Editor.isEditorNode(node.parentElement) ||
					node.parentNode.getAttribute('contenteditable') === 'true'
				) {

					EditorNode.trim(node);

					return EditorMutation._nodeAttributes(node);

				}
				// Others <p/div/...> are evil
				else {
					return EditorNode.remove(node);
				}

			case 'BLOCKQUOTE' :
				// <blockquote> within figures are angels
				if(node.parentElement.parentElement.getAttribute('data-type') === 'quote') {
					return EditorMutation._nodeAttributes(node, ['contenteditable', 'tabindex']);
				}
				// Others are evil
				else {
					return EditorNode.remove(node);
				}

			case 'A' :
				return EditorMutation._nodeAttributes(node, ['href'], [], true);

			case 'SPAN' :

				// util-mark n'est que le rendu du surlignage : collée seule, la classe montrerait un surlignage que le serveur retire
				if(node.getAttribute('data-highlight') === 'true') {
					node.setAttribute('class', 'util-mark');
				} else {
					node.removeAttribute('class');
				}

				return EditorMutation._nodeAttributes(node, [], ['style', 'data-size', 'data-highlight', 'class']);

			default :
				return EditorNode.remove(node);

		}

		return true;

	};

	// Clean node attributes
	static _nodeAttributes(node, requiredAttributes, authorizedAttributes, requiredChildrenNodes) {

		if(
			typeof requiredChildrenNodes !== 'undefined' && requiredChildrenNodes &&
			node.childNodes.length === 0
		) {
			return EditorNode.remove(node);
		}

		if(typeof requiredAttributes === 'undefined') {
			requiredAttributes = [];
		}

		if(typeof authorizedAttributes === 'undefined') {
			authorizedAttributes = [];
		}

		let requiredFound = 0;

		for(let i = 0; i < node.attributes.length; i++) {

			const attribute = node.attributes[i];

			const index = requiredAttributes.indexOf(attribute.name);
			const indexAuthorized = authorizedAttributes.indexOf(attribute.name);

			if(index > -1) {
				requiredFound++;
			} else if(indexAuthorized === -1) {
				node.removeAttribute(attribute.name);
				i--;
			}

		}

		if(requiredAttributes.length === requiredFound) {
			return 'updated';
		} else {
			return EditorNode.remove(node);
		}

	};

	// Replace a node by another one
	static _nodeReplace(node, tagTo, requiredAttributes) {

		// Check attributes
		if(EditorMutation._nodeAttributes(node, requiredAttributes) === 'removed') {
			return 'removed';
		}

		// Transform tag
		if(node.tagName !== tagTo) {

			const element = document.createElement(tagTo);

			// Copy attributes
			for(let i = 0; i < node.attributes.length; i++) {
				const attribute = node.attributes[i];
				element.setAttribute(attribute.name, attribute.value);
			}

			while(node.childNodes.length > 0) {

				const currentNode = node.childNodes[0];

				element.appendChild(currentNode);

				if(currentNode === EditorRange.startContainer) {
					EditorRange.newStartContainer = currentNode;
				}

				if(currentNode === EditorRange.endContainer) {
					EditorRange.newEndContainer = currentNode;
				}

			}

			const updateStartContainer = (node === EditorRange.startContainer);
			const updateEndContainer = (node === EditorRange.endContainer);

			node.parentElement.replaceChild(element, node);

			if(updateStartContainer) {
				EditorRange.newStartContainer = element;
			}

			if(updateEndContainer) {
				EditorRange.newEndContainer = element;
			}

			node = element;

		}

		// <p> element can't be empty
		if(node.tagName === 'P') {

			EditorParagraph.repair(node);

		}
		return 'updated';

	};

};

// Helper function to manipulate nodes
class EditorNode {

	// Get nextSibling or previousSibling and ignores empty text nodes
	static siblingNoWhitespaces(node, sibling) {

		let nodeSibling = node[sibling];

		for(; ; ) {

			if(nodeSibling === null) {
				return null;
			}

			// Ignore empty nodes
			if(
				nodeSibling.nodeType !== Node.TEXT_NODE ||
				nodeSibling.nodeValue.match(/^[\n\r\t]*$/) === null
			) {
				return nodeSibling;
			}

			nodeSibling = nodeSibling[sibling];

		}

	};

	// Trim whitespaces (\r, \t, \n) in a node
	static trim(node) {

		let childNode;

		function trimIt(node) {

			if(
				node.nodeType === Node.TEXT_NODE &&
				node.nodeValue.match(/^[\n\r\t]*$/)
			) {
				node.parentElement.removeChild(node);
				return true;
			} else {
				return false;
			}

		};

		// Left trim
		while(node.childNodes.length > 0) {

			childNode = node.childNodes[0];

			if(trimIt(childNode) === false) {
				break;
			}

		}

		// Right trim
		while(node.childNodes.length > 0) {

			childNode = node.childNodes[node.childNodes.length - 1];

			if(trimIt(childNode) === false) {
				break;
			}

		}

	};

	// Remove a node from the dom and attach its children to its parent node
	// Requires a EditorRange.saveState() call before
	static remove(node) {

		if(EditorRange.isStateSaved() === false) {
			throw 'Saved state required...';
		}

		const parent = node.parentElement;

		let firstChildHit = false;

		while(node.childNodes.length > 0) {

			if(firstChildHit === false) {

				if(node === EditorRange.startContainer) {
					EditorRange.newStartContainer = node.firstChild;
				}

				if(node === EditorRange.endContainer) {
					EditorRange.newEndContainer = node.firstChild;
				}

				firstChildHit = true;

			}

			if(Editor.isEditorNode(parent)) {

				const childContainer = document.createElement('p');
				childContainer.setAttribute('data-location', EditorParagraph.tick());
				childContainer.appendChild(node.firstChild);

				parent.insertBefore(childContainer, node);

			} else {
				parent.insertBefore(node.firstChild, node);
			}


		}

		parent.removeChild(node);

		return 'removed';

	};

	// Recursively remove a node from the dom and attach its children to its parent node
	static removeRecursive(node) {

		if(EditorRange.isStateSaved() === false) {
			throw 'Saved state required...';
		}

		// Handle child nodes
		for(let i = 0; i < node.childNodes.length; i++) {

			const childNode = node.childNodes[i];

			if(childNode.nodeType === Node.ELEMENT_NODE) {

				EditorNode.removeRecursive(childNode);
				i--;

			}

		}

		EditorNode.remove(node);

	};

};