import axios from 'axios';
import { useAuthStore } from './stores/authStore';

const api = axios.create({
  //  baseURL: import.meta.env.VITE_API_URL || '/'
  baseURL: 'http://lara-files.test', // Matches Laravel API routes
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  withXSRFToken: true,
});

// Add response interceptor for handling 401 errors
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore();

      // Only handle if not already logged out
      if (authStore.isAuthenticated) {
        await authStore.logout(); // Proper cleanup
        window.location.reload(); // Clear client state
      }
    }
    return Promise.reject(error);
  }
);

export default api;
