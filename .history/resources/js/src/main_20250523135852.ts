import { createApp } from "vue";
import App from "@/App.vue";

const app = createApp(App);

// pinia store
import { createPinia } from "pinia";
const pinia = createPinia();
app.use(pinia);

// Rehydrate user session on app load
import { useAuthStore } from "@/stores/authStore";
const auth = useAuthStore();
auth.fetchUser().catch(() => {
  // Optionally log or ignore
});

import router from "@/router";
app.use(router);

// main app css
import "@/assets/css/app.css";

// perfect scrollbar
import { PerfectScrollbarPlugin } from "vue3-perfect-scrollbar";
app.use(PerfectScrollbarPlugin);

//vue-meta
import { createHead } from "@vueuse/head";
const head = createHead();
app.use(head);

// set default settings
import appSetting from "@/app-setting";
appSetting.init();

//vue-i18n
import i18n from "@/i18n";
app.use(i18n);

// tippy tooltips
import { TippyPlugin } from "tippy.vue";
app.use(TippyPlugin);

//input mask
import { vMaska } from "maska/vue";
app.directive("maska", vMaska);

//markdown editor
import VueEasymde from "vue3-easymde";
import "easymde/dist/easymde.min.css";
app.use(VueEasymde);

// popper
import Popper from "vue3-popper";
app.component("Popper", Popper);

// json to excel
import vue3JsonExcel from "vue3-json-excel";
app.use(vue3JsonExcel);

app.mount("#app");
