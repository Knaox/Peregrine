import { injectStyles } from './src/styles';
import { PmaButton } from './src/PmaButton';
import { P } from './src/shared';

/**
 * Plugin bundle entrypoint. Injects the scoped stylesheet, then registers the
 * phpMyAdmin button as a per-row action in the core "Databases" tab. The slot
 * is feature-detected so the plugin degrades gracefully on older shells that
 * predate `registerDatabaseRowAction`.
 */
injectStyles();

if (typeof P.registerDatabaseRowAction === 'function') {
    P.registerDatabaseRowAction('phpmyadmin', PmaButton);
}
