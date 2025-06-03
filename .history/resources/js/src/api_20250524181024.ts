import axios from 'axios';

const api = axios.create({
  //  baseURL: import.meta.env.VITE_API_URL || '/'
  baseURL: 'http://lara-vue.test', // Matches Laravel API routes
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
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized errors
      window.location.href = '/auth/boxed-signin';
    }
    return Promise.reject(error);
  }
);

export default api;
