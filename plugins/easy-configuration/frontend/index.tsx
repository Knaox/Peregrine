import AdminApp from './src/admin/AdminApp';
import { P } from './src/shared';
import { injectStyles } from './src/styles';

/**
 * Plugin bundle entrypoint. Injects the scoped stylesheet once, then registers
 * the admin template-manager SPA (rendered at /plugins/easy-configuration/*).
 * The player-facing server config section is registered from P6 onwards.
 */
injectStyles();
P.register('easy-configuration', AdminApp);
