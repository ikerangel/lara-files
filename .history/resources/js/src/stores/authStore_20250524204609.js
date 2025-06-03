import { defineStore } from 'pinia';
import api from '../api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoading: false,
    error: null,
    isFetchingUser: false,   // ⬅️ our new flag
  }),

  getters: {
    isAuthenticated: state => !!state.user,
  },

  actions: {
    async login(credentials) {
      this.isLoading = true;
      this.error = null;
      try {
        await api.post('/login', credentials);
        await this.fetchUser();
      } catch (err) {
        this.handleError(err, 'Login failed');
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    async logout() {
      this.isLoading = true;
      try {
        await api.post('/logout');
      } catch (err) {
        this.handleError(err, 'Logout failed');
        throw err;
      } finally {
        this.clearAuth();
        this.isLoading = false;
      }
    },

    async fetchUser() {
      // If a fetch is already in-flight, do nothing
      if (this.isFetchingUser) return;

      this.isFetchingUser = true;
      this.isLoading = true;
      this.error = null;

      try {
        const { data } = await api.get('/api/user');
        this.user = data;
        console.log(data);
      } catch (err) {
        this.clearAuth();    // on 401 or other errors
        throw err;
      } finally {
        this.isFetchingUser = false;
        this.isLoading = false;
      }
    },

    clearAuth() {
      // resets state back to initial
      this.$reset();
    },

    handleError(err, fallback) {
      this.error =
        err.response?.data?.message ||
        err.message ||
        fallback;
    },
  },
});
