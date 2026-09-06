/**
 * jQuery and Moment on `window`, before the date picker is loaded.
 *
 * bootstrap-daterangepicker is a UMD module. Under a bundler it takes the
 * CommonJS branch, and that branch reads `window.moment` if there is one and
 * falls back to `require('moment')` if there is not — WITHOUT unwrapping the
 * `default` an ES-module interop hands back. So the plugin received a module
 * namespace object where it wanted a function, and threw "e is not a
 * function" the moment it was constructed.
 *
 * The globals have to be set before the plugin's module is evaluated, and
 * `import` statements are hoisted above everything else in a file — so this
 * cannot live in the component that uses it. Importing this module first is
 * what orders the side effect correctly: ES modules evaluate their
 * dependencies in source order.
 *
 * Nothing else in StyleDesk uses jQuery or Moment. They are here for this one
 * plugin, and they travel in the chunk of whichever component imports it.
 */
import $ from 'jquery';
import moment from 'moment';

window.jQuery = $;
window.$ = $;
window.moment = moment;

export { $, moment };
