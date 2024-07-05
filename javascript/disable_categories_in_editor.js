(function () {
    wp.domReady(function () {
        wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-category');
    });
})();
