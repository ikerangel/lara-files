import { createApp } from "vue";
import App from "./App.vue";

// Core dependencies
import { createPinia } from "pinia";
import router from "./router";
import i18n from "./i18n";

// Essential initialization
const app = createApp(App);
const pinia = createPinia();

// Core plugins
app.use(pinia);
app.use(router);
app.use(i18n);

// Auth system (early initialization)
import { useAuthStore } from "./stores/authStore";
const auth = useAuthStore();
auth.fetchUser().catch(() => {
  // Optionally log or ignore
});

// Main CSS (should come before component-specific CSS)
import "@/assets/css/app.css";

// Meta management
import { createHead } from "@vueuse/head";
app.use(createHead());

// Application settings
import appSetting from "./app-setting";
appSetting.init();

// UI Components & Plugins (ordered by importance/usage)
// -------------------------------
import { PerfectScrollbarPlugin } from "vue3-perfect-scrollbar";
app.use(PerfectScrollbarPlugin);

// Form components
import { vMaska } from "maska/vue";
app.directive("maska", vMaska);

import VueEasymde from "vue3-easymde";
import "easymde/dist/easymde.min.css";
app.use(VueEasymde);

// UI Utilities
import { TippyPlugin } from "tippy.vue";
app.use(TippyPlugin);

import Popper from "vue3-popper";
app.component("Popper", Popper);

// Data export
import vue3JsonExcel from "vue3-json-excel";
app.use(vue3JsonExcel);

app.mount("#app");
