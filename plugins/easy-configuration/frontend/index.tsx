import AdminApp from './src/admin/AdminApp';
import { ConfigSection } from './src/server/ConfigSection';
import { P } from './src/shared';
import { injectStyles } from './src/styles';

/**
 * Plugin bundle entrypoint. Injects the scoped stylesheet once, then registers:
 *  - the admin template-manager SPA (rendered at /plugins/easy-configuration/*),
 *  - the player-facing "Game configuration" section on the server overview.
 */
injectStyles();
P.register('easy-configuration', AdminApp);
P.registerServerHomeSection('easy-config', ConfigSection);
