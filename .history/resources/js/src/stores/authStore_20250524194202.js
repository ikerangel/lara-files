import { defineStore } from 'pinia';
import apiClient from '../api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async login(credentials) {
      this.isLoading = true;
      this.error = null;

      try {
        // Make login request
        await apiClient.post('/login', credentials);

        // Fetch authenticated user data
        await this.fetchUser();

      } catch (error) {
        this.handleError(error, 'Login failed');
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async logout() {
      this.isLoading = true;

      try {
        await apiClient.post('/logout');
        this.clearAuth();
      } catch (error) {
        this.handleError(error, 'Logout failed');
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async fetchUser() {
      this.isLoading = true;

      try {
        const { data } = await apiClient.get('api/user');
        this.user = data;
        console.log(this.user);
      } catch (error) {
        this.clearAuth(); // 401 fallback
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    clearAuth() {
      // Reset state
      this.$reset();
    },

    handleError(error, fallbackMessage) {
      this.error = error.response?.data?.message ||
                  error.message ||
                  fallbackMessage;
    },
  },
});

// Add response interceptor to handle 401 errors
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore(pinia);
      authStore.clearAuth();
    }
    return Promise.reject(error);
  }
);
