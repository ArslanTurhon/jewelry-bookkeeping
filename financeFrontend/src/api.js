import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://192.168.22.229:8000/api',
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('admin_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  config.headers['X-Language'] = localStorage.getItem('language') || 'zh-CN'
  return config
})

export function formatMoney(value) {
  return Number(value || 0).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}
