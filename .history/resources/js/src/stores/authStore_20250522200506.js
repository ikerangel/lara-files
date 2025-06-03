import { defineStore } from 'pinia';
import axios from 'axios';

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
    // token: localStorage.getItem('auth_token') || null,
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
        // 1. First get CSRF cookie
        await apiClient.get('/csrf-cookie');

        // 2. Now make login request
        await apiClient.post('/login', credentials);

        // 3. Fetch authenticated user data
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
        const { data } = await apiClient.get('/user');
        this.user = data;
        console.log(this.user);
      } catch (error) {
        this.handleError(error, 'Failed to fetch user');
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    clearAuth() {
      // Reset state
      this.$reset();
      // Clear storage
      localStorage.removeItem('auth_token');
      // Remove Axios auth header
      delete apiClient.defaults.headers.common['Authorization'];
    },

    handleError(error, fallbackMessage) {
      this.error = error.response?.data?.message ||
                  error.message ||
                  fallbackMessage;
    },
  },
});

// Add request interceptor to auto-inject token


// Add response interceptor to handle 401 errors
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore();
      authStore.clearAuth();
    }
    return Promise.reject(error);
  }
);
