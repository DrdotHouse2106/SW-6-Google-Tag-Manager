/*
 * Nova Tag Manager – Storefront-Einstiegspunkt
 * Das Plugin wird nur geladen, wenn der Tag Manager im Verkaufskanal aktiv ist.
 */
window.PluginManager.register(
    'NovaTagManager',
    () => import('./plugin/nova-tag-manager.plugin'),
    '#nova-gtm-config'
);
