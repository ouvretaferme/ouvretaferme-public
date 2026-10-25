<?php
new AdaptativeView('updateNote', function($data, PanelTemplate $t) {
	return new \product\ConfigurationUi()->updateStockNotes($data->e);
});

new AdaptativeView('add', function($data, PanelTemplate $t) {
	return new \product\StockUi()->add($data->eStructure);
});

new AdaptativeView('bookmarks', function($data, PanelTemplate $t) {
	return new \product\StockUi()->getBookmarks($data->e, $data->cBookmark);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \product\StockUi()->update($data->e, $data->cStock);
});

new AdaptativeView('increment', function($data, PanelTemplate $t) {
	return new \product\StockUi()->increment($data->e);
});

new AdaptativeView('decrement', function($data, PanelTemplate $t) {
	return new \product\StockUi()->decrement($data->e);
});
?>
