import { defineStore } from 'pinia';
// import apiClient from '@/api';
// Centralized Axios instance
const apiClient = axios.create({
  baseURL: 'http://lara-vue.test', // Matches Laravel API routes
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  withXSRFToken: true,
});

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    error: null,
    isInitialized: false,
    isFetching: false,
    isLoading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async login(credentials) {
      this.isLoading = true;
      this.error = null;

      try {
        // Get CSRF cookie -> I don't need it as I go through Laravel
        // await apiClient.get('/sanctum/csrf-cookie');
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
      if (this.isFetching) return;
      this.isLoading = true;
      this.isFetching = true;

      try {
        const { data } = await apiClient.get('api/user');
        this.user = data;
        console.log(this.user);
      } catch (error) {
        this.clearAuth(); // 401 fallback
        throw error;
      } finally {
        this.isLoading = false;
        this.isFetching = false;
        this.isInitialized = true;
      }
    },

    async clearAuth() {
      await this.logout();
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
