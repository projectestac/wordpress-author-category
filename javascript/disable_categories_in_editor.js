/**
 * Disables the category panel in the WordPress block editor.
 *
 * This script uses the @wordpress/data package to dispatch an action
 * that removes the category panel ('taxonomy-panel-category') from the editor sidebar.
 * It runs after the DOM is ready, thanks to @wordpress/dom-ready.
 */
(function () {
    wp.domReady(function () {
        wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-category');
    });
})();
