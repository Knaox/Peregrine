import { injectStyles } from './src/styles';
import { PlayerCountCard } from './src/PlayerCountCard';
import { P } from './src/shared';

/**
 * Plugin bundle entrypoint. Injects the scoped keyframes, then registers the
 * connected-players card as a section on the server overview ("home"). The slot
 * is feature-detected so the plugin degrades gracefully on older shells.
 */
injectStyles();

if (typeof P.registerServerHomeSection === 'function') {
    P.registerServerHomeSection('player-counter', PlayerCountCard);
}
