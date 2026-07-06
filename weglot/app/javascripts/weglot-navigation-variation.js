/* global wp */
(function () {
    if (!wp || !wp.blocks) {
        return;
    }

    const { registerBlockVariation } = wp.blocks;

    registerBlockVariation('core/navigation-link', {
        name: 'weglot-language-switcher',
        title: 'Weglot Widget menu',
        description: 'Weglot switcher menu',
        attributes: {
            label: 'Weglot',
            url: '#',
            className: 'weglot-navigation-item'
        },
        isActive: function (attrs) {
            return !!(attrs && attrs.className && attrs.className.indexOf('weglot-navigation-item') !== -1);
        }
    });
})();
