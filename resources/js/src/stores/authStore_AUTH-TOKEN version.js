import { defineStore } from 'pinia';
import axios from 'axios';

// Centralized Axios instance
const apiClient = axios.create({
  baseURL: '/api', // Matches Laravel API routes
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }
});

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    isLoading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async login(credentials) {
      this.isLoading = true;
      this.error = null;

      try {
        // 1. Login request
        const { data } = await apiClient.post('/login', credentials);

        // 2. Store token
        this.token = data.token;
        localStorage.setItem('auth_token', data.token);

        // 3. Set default Axios auth header
        apiClient.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;

        // 4. Fetch user data
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
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

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
