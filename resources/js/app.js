import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import VueLazyLoad from 'vue3-lazyload';
import Toast, { POSITION } from "vue-toastification";
import "vue-toastification/dist/index.css";

const appName = import.meta.env.VITE_APP_NAME || 'District Smiles Dental Center';

createInertiaApp({
  title: (title) => `${title} | ${appName}`,
  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),

  setup({ el, App, props, plugin }) {

    const vueApp = createApp({ render: () => h(App, props) });

    vueApp.use(plugin);
    vueApp.use(ZiggyVue);
    vueApp.use(VueLazyLoad, {
      loading: '/images/loading.gif',
    });

    vueApp.use(Toast, {
      position: POSITION.TOP_RIGHT,
      timeout: 3000,
      closeOnClick: true,
      pauseOnHover: true,
      draggable: true,
      hideProgressBar: false,
      toastClassName: "custom-toast",
    });

    vueApp.mount(el);
  },

  progress: {
    color: '#4B5563',
  },
});
