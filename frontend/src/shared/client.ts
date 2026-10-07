import axios from 'axios'

const apiUrl = import.meta.env.VITE_API_URL

export const client = axios.create({
  baseURL: apiUrl || undefined,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})
