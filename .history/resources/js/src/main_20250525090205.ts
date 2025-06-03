// src/main.ts
import { createApp } from "vue";
import App from "./App.vue";
import { createPinia } from "pinia";
import i18n from "./i18n";
import { createHead } from "@vueuse/head";
import appSetting from "./app-setting";

// UI + CSS imports...
import "@/assets/css/app.css";
import { PerfectScrollbarPlugin } from "vue3-perfect-scrollbar";
import { vMaska } from "maska/vue";
import VueEasymde from "vue3-easymde";
import "easymde/dist/easymde.min.css";
import { TippyPlugin } from "tippy.vue";
import Popper from "vue3-popper";
import vue3JsonExcel from "vue3-json-excel";

// defer these until after we reload the store
import { useAuthStore } from "./stores/authStore";
import router from "./router";

async function bootstrap() {
  const app = createApp(App);

  // 1️⃣ Pinia first
  const pinia = createPinia();
  app.use(pinia);

  // 2️⃣ i18n/head/other plugins
  app.use(i18n);
  app.use(createHead());
  app.use(PerfectScrollbarPlugin);
  app.directive("maska", vMaska);
  app.use(VueEasymde);
  app.use(TippyPlugin);
  app.component("Popper", Popper);
  app.use(vue3JsonExcel);

  // 3️⃣ Set up cross-tab listener (so other tabs get notified)
  // Add eventListener to spread the logout across all the tabs.
  // This works along the authStore.logout() method
  window.addEventListener("storage", (event) => {
    if (event.key === "logout") {
      const auth = useAuthStore();
      auth.clearAuth();
      // Replace the URL and then reload so the sign-in view mounts cleanly
      router
        .replace({ name: "boxed-signin" })
        .catch(() => {/* ignore duplicate navigation */})
        .finally(() => {
          window.location.reload();
        });
    }
  });

  // 4️⃣ Check if we already logged out somewhere (important for duplicated tabs)
  const logoutFlag = localStorage.getItem("logout");
  const auth = useAuthStore();
  if (logoutFlag) {
    // Clear our in-memory store so we start off logged-out
    auth.clearAuth();
    // Remove the flag so a fresh login next time works normally
    localStorage.removeItem("logout");
  } else {
    // 5️⃣ If not already “logged out,” fetch the user normally
    await auth.fetchUser().catch(() => {
      /* not signed in, no biggie */
    });
  }

  // 6️⃣ Now install routing (guards will see hydrated store)
  app.use(router);

  // 7️⃣ Apply any settings, then wait for router to be ready
  appSetting.init();

  await router.isReady();

  // 8️⃣ Finally mount
  app.mount("#app");
}

bootstrap();
