<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Coin, CreditCard, Delete, Edit, Menu as MenuIcon, Money, Plus, Refresh, Tickets, User, Wallet } from '@element-plus/icons-vue'
import { LineChart } from 'echarts/charts'
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { init, use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { api, formatMoney } from './api'

use([LineChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const token = ref(localStorage.getItem('admin_token') || '')
const adminUser = ref(JSON.parse(localStorage.getItem('admin_user') || 'null'))
const loginForm = reactive({ account: '', password: '' })
const loading = ref(false)
const activeMenu = ref(adminUser.value?.is_super_admin ? 'dashboard' : 'reconciliation')
const mobileMenu = ref(false)
const month = ref(today().slice(0, 7))
const dashboardDate = ref(today())
const selectedEmployeeId = ref('')
const stats = ref(null)
const trendChartElement = ref(null)
let trendChart = null
const transactions = ref([])
const exchanges = ref([])
const exchangeDialog = ref(false)
const exchangeForm = reactive(defaultExchangeForm())
const scrapOutbounds = ref([])
const scrapOutboundDialog = ref(false)
const scrapOutboundForm = reactive(defaultScrapOutboundForm())
const opening = ref({})
const accountDrawer = ref(false)
const accountDetail = ref({ entries: [] })
const accountRange = ref('month')
const selectedAccount = ref('cash')
const transactionDialog = ref(false)
const editingId = ref(null)
const i18n = ref({ translations: {}, languages: [], enums: {} })
const recyclePrice = reactive({ price_date: today(), reference_gold_price: 0, reference_silver_price: 0 })
const filters = reactive({ business_type: '', payment_account: '', product_type: '', wrap_material: '', stock_bucket: '', status: 'active', date_from: '', date_to: '' })
const pagination = reactive({ page: 1, perPage: 50, total: 0 })
const users = ref([])
const stores = ref([])
const selectedStoreId = ref(localStorage.getItem('selected_store_id') || '')
const userPagination = reactive({ page: 1, perPage: 50, total: 0 })
const permissionOptions = ref({})
const userDialog = ref(false)
const userRole = ref('general')
const passwordDialog = ref(false)
const editingUserId = ref(null)
const resetUserId = ref(null)
const userForm = reactive(defaultUserForm())
const passwordForm = reactive({ password: '' })
const storeDialog = ref(false)
const editingStoreId = ref(null)
const storeForm = reactive({ name: '', enabled: true })
const profileDialog = ref(false)
const profileForm = reactive({ name: '', username: '' })
const ownPasswordForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const form = reactive(defaultForm())
const itemRows = ref([defaultItem()])
const changeReason = ref('')
const auditLogs = ref([])
const auditPagination = reactive({ page: 1, perPage: 50, total: 0 })
const auditFilters = reactive({ action: '', date_from: '', date_to: '' })
const auditDrawer = ref(false)
const auditDetail = ref(null)
const reconciliationToday = ref({ sections: [] })
const reconciliationReports = ref([])
const reconciliationHistory = ref([])
const reconciliationLoading = ref(false)
const reconciliationDate = ref('')
const reconciliationEmployeeId = ref('')
const reconciliationReminderShown = ref(false)
const reconciliationDraftTimers = new Map()
const rolePresets = {
  pure_gold: ['dashboard', 'recycle_pure_gold'],
  general: ['dashboard', 'transactions'],
  all_business: ['dashboard', 'transactions', 'recycle_pure_gold'],
}

const isAuthed = computed(() => Boolean(token.value))
const hasPermission = (permission) => adminUser.value?.is_super_admin || adminUser.value?.permissions?.includes(permission)
const canUseTransactions = computed(() => hasPermission('transactions') || hasPermission('recycle_pure_gold') || hasPermission('recycle_gold_wrapped'))
const hasPendingReconciliation = computed(() => reconciliationToday.value.sections?.some((section) => ['draft', 'returned'].includes(section.status)))
const canEditTransactions = computed(() => Boolean(adminUser.value?.is_super_admin))
const outboundAuthorized = computed({
  get: () => userForm.permissions.includes('scrap_outbound'),
  set: (enabled) => {
    userForm.permissions = enabled
      ? [...new Set([...userForm.permissions, 'scrap_outbound'])]
      : userForm.permissions.filter((permission) => permission !== 'scrap_outbound')
  },
})
const canWriteCurrentStore = computed(() => !adminUser.value?.is_super_admin || selectedStoreId.value !== 'all')
const dashboardEmployees = computed(() => users.value.filter((user) => {
  if (user.is_super_admin || !user.enabled) return false
  return selectedStoreId.value === 'all' || String(user.store_id) === String(selectedStoreId.value)
}))
const visibleMenus = computed(() => [
  { key: 'dashboard', label: '首页', icon: Wallet, visible: Boolean(adminUser.value?.is_super_admin) },
  { key: 'transactions', label: '流水', icon: Money, visible: canUseTransactions.value },
  { key: 'exchanges', label: '换现', icon: CreditCard, visible: hasPermission('transactions') },
  { key: 'scrap_outbounds', label: '旧料出库', icon: Coin, visible: hasPermission('scrap_outbound') },
  { key: 'reconciliation', label: adminUser.value?.is_super_admin ? '每日交账' : '今日交账', icon: Check, visible: canUseTransactions.value },
  { key: 'opening', label: '期初', icon: Coin },
  { key: 'users', label: '用户管理', icon: User },
  { key: 'stores', label: '店铺管理', icon: Coin, visible: Boolean(adminUser.value?.is_super_admin) },
  { key: 'audit', label: '操作记录', icon: Tickets, visible: Boolean(adminUser.value?.is_super_admin) },
].filter((item) => item.visible ?? hasPermission(item.key)))
const t = (key, fallback = key) => i18n.value.translations?.[key] || fallback
const isRecycle = computed(() => form.business_type === 'recycle')
const isSale = computed(() => form.business_type === 'sale')
const isStockBusiness = computed(() => ['sale', 'recycle'].includes(form.business_type))
const isGoldWrapped = computed(() => form.product_type === 'gold_wrapped')
const isMixedPayment = computed(() => form.payment_account === 'mixed')
const mixedTotal = computed(() => number(form.cash_amount) + number(form.online_amount))
const paymentOptions = computed(() => {
  const options = [
    { label: '现金', value: 'cash' },
    { label: '线上', value: 'online' },
    { label: '现金+线上', value: 'mixed' },
  ]
  if (['income', 'recycle'].includes(form.business_type)) {
    options.push({ label: '纯金回收资金', value: 'pure_gold_fund' })
  }
  return options
})
const stockOverview = computed(() => {
  const stock = stats.value?.stock || {}
  const entries = []
  for (const [bucket, prefix] of [['sale_stock', '销售货'], ['scrap_stock', '回收料']]) {
    const products = stock[bucket]?.products || {}
    entries.push(
      { label: `${prefix}纯金`, value: products.pure_gold?.pure_gold_weight, detail: `${products.pure_gold?.pieces || 0}件`, stock_bucket: bucket, product_type: 'pure_gold' },
      { label: `${prefix}纯银`, value: products.pure_silver?.silver_weight, detail: `${products.pure_silver?.pieces || 0}件`, stock_bucket: bucket, product_type: 'pure_silver' },
      { label: `${prefix}金包银`, value: products.gold_wrapped_silver?.wrapped_gold_weight, detail: `银${formatWeight(products.gold_wrapped_silver?.silver_weight)}g / ${products.gold_wrapped_silver?.pieces || 0}件`, stock_bucket: bucket, product_type: 'gold_wrapped', wrap_material: 'silver' },
      { label: `${prefix}金包铜`, value: products.gold_wrapped_copper?.wrapped_gold_weight, detail: `铜${formatWeight(products.gold_wrapped_copper?.copper_weight)}g / ${products.gold_wrapped_copper?.pieces || 0}件`, stock_bucket: bucket, product_type: 'gold_wrapped', wrap_material: 'copper' },
    )
  }
  return entries
})
const recycleAmount = computed(() => itemRows.value.reduce((sum, item) => {
  if (form.product_type === 'pure_gold') return sum + number(item.pure_gold_weight) * number(item.gold_unit_price)
  if (form.product_type === 'gold_wrapped') {
    return sum + number(item.wrapped_gold_weight) * number(item.gold_unit_price) + number(item.material_weight) * number(item.silver_unit_price)
  }
  return sum + number(item.material_weight) * number(item.silver_unit_price)
}, 0) * (number(form.recycle_price_rate || 100) / 100))

const openingGroups = computed(() => [
  {
    title: '资金账户',
    description: '把你现在手里、微信、支付宝、银行卡，以及专门用来回收纯金的钱填进来。',
    fields: [
      ['cash', '现金', '¥'],
      ['online_wechat', '微信', '¥'],
      ['online_alipay', '支付宝', '¥'],
      ['online_bank', '银行卡', '¥'],
      ['pure_gold_fund', '纯金回收资金', '¥'],
    ],
  },
  {
    title: '现有销售库存',
    description: '店里还没卖出去的货，按总克重填。件数不知道可以先填 0。',
    fields: [
      ['sale_stock.pure_gold.pure_gold_weight', '纯金克重', 'g'],
      ['sale_stock.pure_gold.pieces', '纯金件数', '件'],
      ['sale_stock.pure_silver.silver_weight', '纯银克重', 'g'],
      ['sale_stock.pure_silver.pieces', '纯银件数', '件'],
      ['sale_stock.gold_wrapped_silver.wrapped_gold_weight', '金包银-金重', 'g'],
      ['sale_stock.gold_wrapped_silver.silver_weight', '金包银-银重', 'g'],
      ['sale_stock.gold_wrapped_silver.pieces', '金包银件数', '件'],
    ],
  },
  {
    title: '已回收旧料',
    description: '已经收回来的旧料先按总克重填，不需要分每一件。',
    fields: [
      ['scrap_stock.pure_gold.pure_gold_weight', '已回收纯金克重', 'g'],
      ['scrap_stock.pure_gold.pieces', '纯金件数', '件'],
      ['scrap_stock.pure_silver.silver_weight', '已回收纯银克重', 'g'],
      ['scrap_stock.pure_silver.pieces', '纯银件数', '件'],
      ['scrap_stock.gold_wrapped_silver.wrapped_gold_weight', '已回收金包银-金重', 'g'],
      ['scrap_stock.gold_wrapped_silver.silver_weight', '已回收金包银-银重', 'g'],
      ['scrap_stock.gold_wrapped_silver.pieces', '金包银件数', '件'],
    ],
  },
])

function today() {
  const date = new Date()
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 10)
}

function number(value) {
  return Number(value || 0)
}

function formatWeight(value) {
  return number(value).toFixed(3)
}

function defaultForm() {
  return {
    business_type: 'sale',
    payment_account: 'cash',
    online_method: '',
    amount: '',
    cash_amount: '',
    online_amount: '',
    recycle_price_rate: 100,
    product_type: 'pure_gold',
    wrap_material: 'silver',
    pure_gold_weight: '',
    wrapped_gold_weight: '',
    material_weight: '',
    material_pieces: 1,
    expense_category: 'rent',
    transaction_date: today(),
    remark: '',
  }
}

function defaultItem() {
  return {
    pure_gold_weight: '',
    wrapped_gold_weight: '',
    material_weight: '',
    gold_unit_price: '',
    silver_unit_price: '',
  }
}

function defaultExchangeForm() {
  return {
    direction: 'cash_in',
    online_method: 'wechat',
    amount: 0,
    fee: 0,
    exchange_date: today(),
    remark: '',
  }
}

function defaultScrapOutboundForm() {
  return {
    product_type: 'pure_gold',
    wrap_material: 'silver',
    pure_gold_weight: 0,
    wrapped_gold_weight: 0,
    material_weight: 0,
    material_pieces: 0,
    gross_amount: 0,
    received_amount: 0,
    payment_account: 'pure_gold_fund',
    online_method: '',
    outbound_date: today(),
    remark: '',
    fees: [
      { category: 'processing', amount: 0, payment_account: 'deducted', online_method: '' },
      { category: 'refining', amount: 0, payment_account: 'deducted', online_method: '' },
      { category: 'transport', amount: 0, payment_account: 'deducted', online_method: '' },
      { category: 'other', amount: 0, payment_account: 'deducted', online_method: '' },
    ],
  }
}

function defaultUserForm() {
  return {
    name: '',
    username: '',
    email: '',
    password: '',
    enabled: true,
    store_id: '',
    permissions: ['dashboard', 'transactions'],
  }
}

async function login() {
  loading.value = true
  try {
    const { data } = await api.post('/admin/login', loginForm)
    token.value = data.token
    adminUser.value = data.admin
    activeMenu.value = data.admin.is_super_admin ? 'dashboard' : 'reconciliation'
    reconciliationReminderShown.value = false
    reconciliationToday.value = { sections: [] }
    reconciliationReports.value = []
    reconciliationHistory.value = []
    localStorage.setItem('admin_token', data.token)
    localStorage.setItem('admin_user', JSON.stringify(data.admin))
    await loadAll()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '登录失败')
  } finally {
    loading.value = false
  }
}

function logout() {
  api.post('/admin/logout').catch(() => {})
  localStorage.removeItem('admin_token')
  localStorage.removeItem('admin_user')
  token.value = ''
  adminUser.value = null
  activeMenu.value = 'dashboard'
  reconciliationReminderShown.value = false
  reconciliationToday.value = { sections: [] }
  reconciliationReports.value = []
  reconciliationHistory.value = []
}

async function loadAll() {
  if (adminUser.value?.is_super_admin) await loadStores()
  else {
    selectedStoreId.value = String(adminUser.value?.store_id || '')
    localStorage.setItem('selected_store_id', selectedStoreId.value)
  }
  const tasks = [loadI18n()]
  if (adminUser.value?.is_super_admin) tasks.push(loadStats())
  if (canUseTransactions.value) tasks.push(loadTransactions())
  if (hasPermission('transactions')) tasks.push(loadExchanges())
  if (hasPermission('scrap_outbound')) tasks.push(loadScrapOutbounds())
  if (hasPermission('opening')) tasks.push(loadOpening())
  if (hasPermission('recycle_price')) tasks.push(loadRecyclePrice(today()))
  if (hasPermission('users')) tasks.push(loadUsers(), loadPermissionOptions())
  await Promise.all(tasks)
  if (activeMenu.value === 'reconciliation') await loadReconciliations()
  if (!visibleMenus.value.some((item) => item.key === activeMenu.value)) {
    activeMenu.value = visibleMenus.value[0]?.key || ''
  }
}

async function loadStores() {
  const { data } = await api.get('/admin/stores')
  stores.value = data || []
  if (!selectedStoreId.value || (selectedStoreId.value !== 'all' && !stores.value.some((store) => String(store.id) === String(selectedStoreId.value)))) {
    selectedStoreId.value = String(stores.value.find((store) => store.enabled)?.id || '')
    if (selectedStoreId.value) localStorage.setItem('selected_store_id', selectedStoreId.value)
  }
}

async function changeStore(value) {
  selectedStoreId.value = String(value || '')
  localStorage.setItem('selected_store_id', selectedStoreId.value || 'all')
  selectedEmployeeId.value = ''
  await refresh()
  if (selectedStoreId.value !== 'all') {
    if (hasPermission('opening') && selectedStoreId.value) await loadOpening()
    if (hasPermission('recycle_price') && selectedStoreId.value) await loadRecyclePrice(today())
  }
}

function openCreateStore() {
  editingStoreId.value = null
  Object.assign(storeForm, { name: '', enabled: true })
  storeDialog.value = true
}

function openEditStore(store) {
  editingStoreId.value = store.id
  Object.assign(storeForm, { name: store.name, enabled: store.enabled })
  storeDialog.value = true
}

async function saveStore() {
  if (editingStoreId.value) await api.put(`/admin/stores/${editingStoreId.value}`, storeForm)
  else await api.post('/admin/stores', storeForm)
  storeDialog.value = false
  await loadStores()
  ElMessage.success('店铺已保存')
}

async function disableStore(store) {
  await ElMessageBox.confirm(`停用店铺 ${store.name}？历史账目会继续保留。`, '确认停用', { type: 'warning' })
  await api.delete(`/admin/stores/${store.id}`)
  await loadStores()
}

function openProfile() {
  Object.assign(profileForm, { name: adminUser.value?.name || '', username: adminUser.value?.username || '' })
  Object.assign(ownPasswordForm, { current_password: '', password: '', password_confirmation: '' })
  profileDialog.value = true
}

async function saveProfile() {
  const { data } = await api.put('/admin/me/profile', profileForm)
  adminUser.value = data
  localStorage.setItem('admin_user', JSON.stringify(data))
  ElMessage.success('账户资料已保存')
}

async function changeOwnPassword() {
  await api.put('/admin/me/password', ownPasswordForm)
  ElMessage.success('密码已修改，请重新登录')
  profileDialog.value = false
  logout()
}

async function loadI18n() {
  const { data } = await api.get('/admin/i18n')
  i18n.value = data
}

async function loadMe() {
  const { data } = await api.get('/admin/me')
  adminUser.value = data
  localStorage.setItem('admin_user', JSON.stringify(data))
}

async function loadStats() {
  const { data } = await api.get('/admin/stats/current', {
    params: { month: month.value, date: dashboardDate.value, admin_user_id: selectedEmployeeId.value || '' },
  })
  stats.value = data
  await nextTick()
  renderTrendChart()
}

function renderTrendChart() {
  if (!trendChartElement.value || !stats.value?.trend_7_days) return
  trendChart ||= init(trendChartElement.value)
  trendChart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: ['销售收入', '回收支出'], right: 8 },
    grid: { left: 18, right: 18, top: 48, bottom: 12, containLabel: true },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: stats.value.trend_7_days.map((item) => item.date.slice(5)),
    },
    yAxis: { type: 'value' },
    series: [
      {
        name: '销售收入',
        type: 'line',
        smooth: true,
        symbolSize: 7,
        lineStyle: { width: 3, color: '#1677ff' },
        itemStyle: { color: '#1677ff' },
        data: stats.value.trend_7_days.map((item) => item.sales),
      },
      {
        name: '回收支出',
        type: 'line',
        smooth: true,
        symbolSize: 7,
        lineStyle: { width: 3, color: '#c2413b' },
        itemStyle: { color: '#c2413b' },
        data: stats.value.trend_7_days.map((item) => item.recycle),
      },
    ],
  })
  trendChart.off('click')
  trendChart.on('click', (params) => {
    const date = stats.value?.trend_7_days?.[params.dataIndex]?.date
    const businessType = params.seriesName === '销售收入' ? 'sale' : 'recycle'
    if (date) openTransactionSource(businessType, { date })
  })
}

async function loadTransactions() {
  const { data } = await api.get('/admin/transactions', {
    params: { ...filters, admin_user_id: selectedEmployeeId.value || '', month: month.value, page: pagination.page, per_page: pagination.perPage },
  })
  transactions.value = data.data || []
  pagination.total = data.total || 0
  pagination.page = data.current_page || pagination.page
}

async function loadExchanges() {
  const { data } = await api.get('/admin/exchanges', {
    params: {
      store_id: selectedStoreId.value === 'all' ? '' : selectedStoreId.value,
      date: dashboardDate.value,
      admin_user_id: selectedEmployeeId.value || '',
      per_page: 50,
    },
  })
  exchanges.value = data.data || []
}

async function openExchangeSource() {
  activeMenu.value = 'exchanges'
  await loadExchanges()
}

async function loadScrapOutbounds() {
  const { data } = await api.get('/admin/scrap-outbounds', {
    params: { store_id: selectedStoreId.value === 'all' ? '' : selectedStoreId.value, per_page: 50 },
  })
  scrapOutbounds.value = data.data || []
}

function openScrapOutbound() {
  Object.assign(scrapOutboundForm, defaultScrapOutboundForm())
  scrapOutboundDialog.value = true
}

function changeOutboundProduct(product) {
  scrapOutboundForm.payment_account = product === 'pure_gold' ? 'pure_gold_fund' : 'cash'
}

async function saveScrapOutbound() {
  try {
    await api.post('/admin/scrap-outbounds', {
      ...scrapOutboundForm,
      store_id: selectedStoreId.value === 'all' ? null : selectedStoreId.value,
      fees: scrapOutboundForm.fees.filter((fee) => Number(fee.amount) > 0),
    })
    scrapOutboundDialog.value = false
    await Promise.all([loadScrapOutbounds(), adminUser.value?.is_super_admin ? loadStats() : Promise.resolve()])
    ElMessage.success('旧料出库已保存')
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '旧料出库保存失败')
  }
}

function openExchange() {
  Object.assign(exchangeForm, defaultExchangeForm())
  exchangeDialog.value = true
}

async function saveExchange() {
  try {
    await api.post('/admin/exchanges', {
      ...exchangeForm,
      store_id: selectedStoreId.value === 'all' ? null : selectedStoreId.value,
    })
    exchangeDialog.value = false
    await Promise.all([loadExchanges(), adminUser.value?.is_super_admin ? loadStats() : Promise.resolve()])
    ElMessage.success('换现已保存')
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '换现保存失败')
  }
}

async function loadAuditLogs() {
  if (!adminUser.value?.is_super_admin) return
  const { data } = await api.get('/admin/audit-logs', {
    params: { ...auditFilters, page: auditPagination.page, per_page: auditPagination.perPage },
  })
  auditLogs.value = data.data || []
  auditPagination.total = data.total || 0
  auditPagination.page = data.current_page || auditPagination.page
}

async function loadOpening() {
  const { data } = await api.get('/admin/opening-balance')
  opening.value = data
}

async function loadRecyclePrice(date) {
  const { data } = await api.get('/admin/recycle-price', { params: { date } })
  Object.assign(recyclePrice, data)
}

async function saveRecyclePrice() {
  const { data } = await api.post('/admin/recycle-price', recyclePrice)
  Object.assign(recyclePrice, data)
  ElMessage.success('参考价已保存')
}

async function saveOpening() {
  const { data } = await api.post('/admin/opening-balance', opening.value)
  opening.value = data
  await loadStats()
  ElMessage.success('期初数据已保存')
}

async function refresh() {
  pagination.page = 1
  const tasks = []
  if (adminUser.value?.is_super_admin) tasks.push(loadStats())
  if (canUseTransactions.value) tasks.push(loadTransactions())
  if (hasPermission('transactions')) tasks.push(loadExchanges())
  if (hasPermission('scrap_outbound')) tasks.push(loadScrapOutbounds())
  if (hasPermission('users')) tasks.push(loadUsers())
  if (adminUser.value?.is_super_admin && activeMenu.value === 'audit') tasks.push(loadAuditLogs())
  if (activeMenu.value === 'reconciliation') tasks.push(loadReconciliations())
  await Promise.all(tasks)
}

async function selectMenu(key) {
  activeMenu.value = key
  mobileMenu.value = false
  if (key === 'audit') await loadAuditLogs()
  if (key === 'exchanges') await loadExchanges()
  if (key === 'scrap_outbounds') await loadScrapOutbounds()
  if (key === 'reconciliation') await loadReconciliations()
}

const reconciliationLabels = {
  pure_gold_fund: '纯金回收资金',
  scrap_pure_gold_weight: '回收纯金克重',
  scrap_pure_gold_pieces: '回收纯金件数',
  cash: '现金',
  online_bank: '银行卡',
  online_wechat: '微信',
  online_alipay: '支付宝',
  sale_pure_gold_weight: '销售纯金克重',
  sale_pure_gold_pieces: '销售纯金件数',
  sale_pure_silver_weight: '销售纯银克重',
  sale_pure_silver_pieces: '销售纯银件数',
  sale_gold_wrapped_silver_gold_weight: '销售金包银金重',
  sale_gold_wrapped_silver_silver_weight: '销售金包银银重',
  sale_gold_wrapped_silver_pieces: '销售金包银件数',
  scrap_pure_silver_weight: '回收纯银克重',
  scrap_pure_silver_pieces: '回收纯银件数',
  scrap_gold_wrapped_silver_gold_weight: '回收金包银金重',
  scrap_gold_wrapped_silver_silver_weight: '回收金包银银重',
  scrap_gold_wrapped_silver_pieces: '回收金包银件数',
  recycle_amount: '回收总额',
  recycle_pure_gold_weight: '回收纯金克重',
  recycle_pure_gold_pieces: '回收纯金件数',
  sales_amount: '销售总额',
  sales_cash: '销售收现金',
  sales_wechat: '销售收微信',
  sales_alipay: '销售收支付宝',
  sales_bank: '销售收银行卡',
  sales_pure_gold_amount: '纯金销售金额',
  sales_pure_gold_weight: '卖出纯金克重',
  sales_pure_gold_pieces: '卖出纯金件数',
  sales_pure_silver_amount: '纯银销售金额',
  sales_pure_silver_weight: '卖出纯银克重',
  sales_pure_silver_pieces: '卖出纯银件数',
  sales_gold_wrapped_amount: '金包银销售金额',
  sales_gold_wrapped_gold_weight: '卖出金包银金重',
  sales_gold_wrapped_silver_weight: '卖出金包银银重',
  sales_gold_wrapped_pieces: '卖出金包银件数',
  recycle_cash: '回收付现金',
  recycle_wechat: '回收付微信',
  recycle_alipay: '回收付支付宝',
  recycle_bank: '回收付银行卡',
  recycle_pure_silver_amount: '纯银回收金额',
  recycle_pure_silver_weight: '回收纯银克重',
  recycle_pure_silver_pieces: '回收纯银件数',
  recycle_gold_wrapped_amount: '金包银回收金额',
  recycle_gold_wrapped_gold_weight: '回收金包银金重',
  recycle_gold_wrapped_silver_weight: '回收金包银银重',
  recycle_gold_wrapped_pieces: '回收金包银件数',
}

function reconciliationStatus(status) {
  return {
    pending: '未交账',
    partial: '部分完成',
    draft: '未提交',
    submitted: '等待确认',
    confirmed: '已确认',
    returned: '已退回',
  }[status] || status
}

function reconciliationStatusType(status) {
  return { confirmed: 'success', returned: 'danger', submitted: 'warning', partial: 'warning' }[status] || 'info'
}

function initializeReconciliationSection(section) {
  section.no_business = Boolean(section.no_business)
  section.business_summary ||= {}
  section.actual_snapshot ||= {}
  section.difference_reason ||= ''
  for (const field of section.business_summary_fields || []) {
    if (section.business_summary[field] === undefined) section.business_summary[field] = 0
  }
  for (const field of section.fields || []) {
    if (section.actual_snapshot[field] === undefined) section.actual_snapshot[field] = 0
  }
  return section
}

async function loadReconciliations() {
  reconciliationLoading.value = true
  try {
    if (adminUser.value?.is_super_admin) {
      const { data } = await api.get('/admin/reconciliations', {
        params: {
          store_id: selectedStoreId.value === 'all' ? '' : selectedStoreId.value,
          date: reconciliationDate.value,
          admin_user_id: reconciliationEmployeeId.value || '',
        },
      })
      reconciliationReports.value = data.data || []
    } else {
      const [{ data }, { data: history }] = await Promise.all([
        api.get('/admin/reconciliations/today'),
        api.get('/admin/reconciliations/mine'),
      ])
      reconciliationToday.value = {
        ...data,
        sections: (data.sections || []).map(initializeReconciliationSection),
      }
      reconciliationHistory.value = history.data || []
      if (hasPendingReconciliation.value && !reconciliationReminderShown.value) {
        reconciliationReminderShown.value = true
        ElMessageBox.alert('今天的交账还没有完成，请核对业务合计并完成盘点后提交。', '今日交账提醒', {
          confirmButtonText: '开始交账',
          type: 'warning',
        }).catch(() => {})
      }
    }
  } finally {
    reconciliationLoading.value = false
  }
}

function scheduleReconciliationDraft(section) {
  if (section.status !== 'draft') return
  clearTimeout(reconciliationDraftTimers.get(section.section_type))
  reconciliationDraftTimers.set(section.section_type, setTimeout(() => saveReconciliationDraft(section), 600))
}

async function saveReconciliationDraft(section) {
  try {
    const { data } = await api.put(`/admin/reconciliations/today/${section.section_type}/draft`, {
      no_business: section.no_business,
      business_summary: section.business_summary || [],
      actual_snapshot: section.actual_snapshot,
      difference_reason: section.difference_reason || null,
    })
    Object.assign(section, data, {
      fields: section.fields,
      business_summary_fields: section.business_summary_fields,
    })
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '草稿保存失败')
  }
}

function toggleNoBusiness(section, checked) {
  if (checked) {
    for (const field of section.business_summary_fields || []) section.business_summary[field] = 0
  }
  scheduleReconciliationDraft(section)
}

async function submitReconciliation(section) {
  try {
    const { data } = await api.post(`/admin/reconciliations/today/${section.section_type}/submit`, {
      no_business: section.no_business,
      business_summary: section.business_summary || [],
      actual_snapshot: section.actual_snapshot,
      difference_reason: section.difference_reason || null,
    })
    Object.assign(section, data)
    ElMessage.success('今日交账已提交')
    await loadReconciliations()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '提交失败')
  }
}

async function confirmReconciliation(section) {
  await api.post(`/admin/reconciliation-sections/${section.id}/confirm`)
  ElMessage.success('已确认')
  await loadReconciliations()
}

async function returnReconciliation(section) {
  const { value } = await ElMessageBox.prompt('请填写需要员工重新核对的原因。', '退回交账', {
    inputValidator: (text) => text?.trim().length >= 2 || '原因至少填写2个字符',
    confirmButtonText: '退回',
    cancelButtonText: '取消',
    type: 'warning',
  })
  await api.post(`/admin/reconciliation-sections/${section.id}/return`, { reason: value.trim() })
  ElMessage.success('已退回')
  await loadReconciliations()
}

async function changePage(page) {
  pagination.page = page
  await loadTransactions()
}

async function changePageSize(size) {
  pagination.perPage = size
  pagination.page = 1
  await loadTransactions()
}

async function applyFilters() {
  pagination.page = 1
  await loadTransactions()
}

function clearFilters() {
  Object.assign(filters, { business_type: '', payment_account: '', product_type: '', wrap_material: '', stock_bucket: '', status: 'active', date_from: '', date_to: '' })
  applyFilters()
}

async function openTransactionSource(businessType = '', options = {}) {
  if (options.date) month.value = String(options.date).slice(0, 7)
  Object.assign(filters, {
    business_type: businessType,
    payment_account: '',
    product_type: options.product_type || '',
    wrap_material: options.wrap_material || '',
    stock_bucket: options.stock_bucket || '',
    status: 'active',
    date_from: options.date || '',
    date_to: options.date || '',
  })
  pagination.page = 1
  activeMenu.value = 'transactions'
  await loadTransactions()
}

async function loadUsers() {
  const { data } = await api.get('/admin/users', { params: { page: userPagination.page, per_page: userPagination.perPage } })
  users.value = data.data || []
  userPagination.total = data.total || 0
  userPagination.page = data.current_page || userPagination.page
}

async function loadPermissionOptions() {
  const { data } = await api.get('/admin/users/permissions')
  permissionOptions.value = data
}

function openCreateUser() {
  Object.assign(userForm, defaultUserForm())
  userRole.value = 'general'
  editingUserId.value = null
  userDialog.value = true
}

function openEditUser(row) {
  Object.assign(userForm, {
    name: row.name,
    username: row.username,
    email: row.email,
    password: '',
    enabled: row.enabled,
    store_id: row.store_id,
    permissions: row.permissions || [],
  })
  userRole.value = detectUserRole(row.permissions || [])
  editingUserId.value = row.id
  userDialog.value = true
}

function detectUserRole(permissions) {
  if (permissions.includes('transactions') && permissions.includes('recycle_pure_gold')) return 'all_business'
  if (permissions.includes('recycle_pure_gold')) return 'pure_gold'
  return 'general'
}

function applyUserRole(role) {
  const keepOutbound = userForm.permissions.includes('scrap_outbound')
  userForm.permissions = [...rolePresets[role], ...(keepOutbound ? ['scrap_outbound'] : [])]
}

async function saveUser() {
  try {
    const payload = { ...userForm }
    if (editingUserId.value) {
      delete payload.password
      await api.put(`/admin/users/${editingUserId.value}`, payload)
    } else {
      await api.post('/admin/users', payload)
    }
    userDialog.value = false
    await loadUsers()
    ElMessage.success('用户已保存')
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

function openResetPassword(row) {
  passwordForm.password = ''
  resetUserId.value = row.id
  passwordDialog.value = true
}

async function resetPassword() {
  try {
    await api.post(`/admin/users/${resetUserId.value}/reset-password`, passwordForm)
    passwordDialog.value = false
    ElMessage.success('密码已重置')
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '重置失败')
  }
}

async function disableUser(row) {
  await ElMessageBox.confirm(`停用用户 ${row.name}？`, '确认停用', { type: 'warning' })
  await api.delete(`/admin/users/${row.id}`)
  await loadUsers()
  ElMessage.success('用户已停用')
}

async function openAccount(account) {
  selectedAccount.value = account
  accountDrawer.value = true
  await loadAccountDetail()
}

async function loadAccountDetail() {
  const { data } = await api.get('/admin/account-details', {
    params: { account: selectedAccount.value, month: month.value, range: accountRange.value },
  })
  accountDetail.value = data
}

function accountTitle(account) {
  return {
    cash: '现金明细',
    online: '线上明细',
    online_wechat: '微信明细',
    online_alipay: '支付宝明细',
    online_bank: '银行卡明细',
    total: '合计明细',
    pure_gold_fund: '纯金回收资金明细',
  }[account] || '账户明细'
}

function resetForm(type = 'sale') {
  Object.assign(form, defaultForm(), { business_type: type })
  if (type === 'income') form.product_type = ''
  if (type === 'operating_expense') form.product_type = ''
  if (type === 'recycle') form.payment_account = 'pure_gold_fund'
  itemRows.value = [defaultItem()]
  editingId.value = null
}

function openCreate(type) {
  resetForm(type)
  transactionDialog.value = true
}

function openCreateRecycle(productType) {
  resetForm('recycle')
  form.product_type = productType
  form.wrap_material = productType === 'gold_wrapped' ? 'silver' : ''
  transactionDialog.value = true
}

function handleBusinessTypeChange(type) {
  if (!['income', 'recycle'].includes(type) && form.payment_account === 'pure_gold_fund') {
    form.payment_account = 'cash'
  }
  if (type === 'recycle') form.payment_account = 'pure_gold_fund'
}

function handlePaymentChange(account) {
  if (account === 'online' || account === 'mixed') {
    form.online_method = form.online_method || 'bank'
  } else {
    form.online_method = ''
  }
  if (account !== 'mixed') {
    form.cash_amount = ''
    form.online_amount = ''
  }
}

function openEdit(row) {
  Object.assign(form, defaultForm(), row, {
    transaction_date: String(row.transaction_date).slice(0, 10),
    amount: row.amount,
  })
  itemRows.value = row.item_weights?.length ? JSON.parse(JSON.stringify(row.item_weights)) : [defaultItem()]
  editingId.value = row.id
  changeReason.value = ''
  transactionDialog.value = true
}

function addItem() {
  itemRows.value.push(defaultItem())
}

function removeItem(index) {
  itemRows.value.splice(index, 1)
  if (!itemRows.value.length) itemRows.value.push(defaultItem())
}

function buildPayload() {
  const payload = { ...form }
  if (payload.payment_account !== 'online' && payload.payment_account !== 'mixed') payload.online_method = ''
  if (payload.payment_account === 'mixed') {
    payload.amount = mixedTotal.value
  } else {
    payload.cash_amount = ''
    payload.online_amount = ''
  }
  if (!isStockBusiness.value) {
    payload.product_type = ''
    payload.wrap_material = ''
  }
  if (isRecycle.value) {
    payload.item_weights = itemRows.value
    payload.amount = recycleAmount.value
  } else {
    payload.item_weights = []
    payload.recycle_price_rate = 100
  }
  if (payload.business_type !== 'operating_expense') payload.expense_category = ''
  if (payload.product_type !== 'gold_wrapped') payload.wrap_material = ''
  return payload
}

async function saveTransaction() {
  try {
    const payload = buildPayload()
    if (editingId.value) {
      payload.change_reason = changeReason.value
      await api.put(`/admin/transactions/${editingId.value}`, payload)
    } else {
      await api.post('/admin/transactions', payload)
    }
    transactionDialog.value = false
    await refresh()
    ElMessage.success('流水已保存')
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

async function deleteTransaction(row) {
  const { value } = await ElMessageBox.prompt(`作废这笔 ${formatMoney(row.amount)} 元流水，原记录会永久保留。`, '确认作废', {
    confirmButtonText: '作废',
    cancelButtonText: '取消',
    inputPlaceholder: '请输入作废原因',
    inputValidator: (text) => text?.trim().length >= 2 || '原因至少填写 2 个字符',
    type: 'warning',
  })
  await api.delete(`/admin/transactions/${row.id}`, { data: { reason: value.trim() } })
  await refresh()
  ElMessage.success('流水已作废')
}

function showAuditDetail(row) {
  auditDetail.value = row
  auditDrawer.value = true
}

function productName(item) {
  if (item.product_type === 'pure_gold') return '纯金'
  if (item.product_type === 'pure_silver') return '纯银'
  if (item.product_type === 'gold_wrapped') return item.wrap_material === 'copper' ? '金包铜' : '金包银'
  return '-'
}

function accountName(item) {
  if (item.payment_account === 'mixed') {
    const onlineLabel = item.online_method_label?.label || item.online_method || '线上'
    return `现金 ¥${formatMoney(item.cash_amount)} / ${onlineLabel} ¥${formatMoney(item.online_amount)}`
  }
  const base = item.payment_account_label?.label || item.payment_account
  return item.online_method_label ? `${base} / ${item.online_method_label.label}` : base
}

function weightText(item) {
  if (item.product_type === 'pure_gold') return `${item.pure_gold_weight}g / ${item.material_pieces}件`
  if (item.product_type === 'pure_silver') return `${item.material_weight}g / ${item.material_pieces}件`
  if (item.product_type === 'gold_wrapped') return `金${item.wrapped_gold_weight}g / 银${item.material_weight}g / ${item.material_pieces}件`
  return '-'
}

onMounted(() => {
  if (isAuthed.value) {
    loadMe().then(loadAll).catch(() => {
      localStorage.removeItem('admin_token')
      localStorage.removeItem('admin_user')
      token.value = ''
      adminUser.value = null
    })
  }
})

onBeforeUnmount(() => trendChart?.dispose())
</script>

<template>
  <el-config-provider>
    <main v-if="!isAuthed" class="login-page">
      <el-card class="login-card">
        <template #header>金银首饰店账本后台</template>
        <el-form :model="loginForm" label-position="top" @submit.prevent="login">
          <el-form-item label="登录账号"><el-input v-model="loginForm.account" /></el-form-item>
          <el-form-item label="密码"><el-input v-model="loginForm.password" type="password" show-password /></el-form-item>
          <el-button type="primary" :loading="loading" native-type="submit" class="full">登录</el-button>
        </el-form>
      </el-card>
    </main>

    <el-container v-else class="admin-shell">
      <el-aside width="220px" class="desktop-sidebar">
        <div class="brand">金银账本</div>
        <el-menu v-model:default-active="activeMenu" background-color="#101827" text-color="#c8d3e6" active-text-color="#fff">
          <el-menu-item v-for="item in visibleMenus" :key="item.key" :index="item.key" @click="selectMenu(item.key)">
            <el-icon><component :is="item.icon" /></el-icon>{{ item.label }}
          </el-menu-item>
        </el-menu>
        <div class="current-user">
          <strong>{{ adminUser?.name || '未命名用户' }}</strong>
          <span>{{ adminUser?.username }}</span>
          <el-tag size="small" :type="adminUser?.is_super_admin ? 'danger' : 'info'">
            {{ adminUser?.is_super_admin ? '老板' : '员工' }}
          </el-tag>
          <el-button text @click="openProfile">我的账户</el-button>
        </div>
        <el-button plain class="logout" @click="logout">退出</el-button>
      </el-aside>

      <el-container>
        <el-header>
          <div class="header-title">
            <el-button class="mobile-menu-button" :icon="MenuIcon" circle @click="mobileMenu = true" />
            <div>
              <h2>{{ visibleMenus.find((item) => item.key === activeMenu)?.label || '后台' }}</h2>
              <span>{{ month }}</span>
            </div>
          </div>
          <div class="header-actions">
            <el-select
              v-if="adminUser?.is_super_admin"
              v-model="selectedStoreId"
              placeholder="选择店铺"
              style="width: 150px"
              @change="changeStore"
            >
              <el-option label="全部店铺合计" value="all" />
              <el-option v-for="store in stores.filter((item) => item.enabled)" :key="store.id" :label="store.name" :value="String(store.id)" />
            </el-select>
            <el-tag v-else>{{ adminUser?.store?.name || '所属店铺' }}</el-tag>
            <el-date-picker
              v-if="adminUser?.is_super_admin && activeMenu === 'dashboard'"
              v-model="dashboardDate"
              type="date"
              value-format="YYYY-MM-DD"
              style="width: 145px"
              @change="loadStats"
            />
            <el-select
              v-if="adminUser?.is_super_admin && activeMenu === 'dashboard'"
              v-model="selectedEmployeeId"
              clearable
              placeholder="全部员工"
              style="width: 140px"
              @change="loadStats"
            >
              <el-option v-for="employee in dashboardEmployees" :key="employee.id" :label="employee.name" :value="employee.id" />
            </el-select>
            <el-date-picker v-model="month" type="month" value-format="YYYY-MM" @change="refresh" />
            <el-button :icon="Refresh" @click="refresh">刷新</el-button>
          </div>
        </el-header>

        <el-main v-if="activeMenu === 'dashboard' && stats">
          <section class="anomaly-band">
            <button type="button" class="anomaly-metric" @click="selectMenu('reconciliation')">
              <span>未交账</span><strong>{{ stats.anomalies.unsubmitted }}</strong>
            </button>
            <button type="button" class="anomaly-metric" @click="selectMenu('reconciliation')">
              <span>现金差额</span><strong>¥{{ formatMoney(stats.anomalies.cash_difference) }}</strong>
            </button>
            <button type="button" class="anomaly-metric" @click="selectMenu('reconciliation')">
              <span>金银差额</span><strong>{{ formatWeight(stats.anomalies.metal_difference) }}g</strong>
            </button>
            <button type="button" class="anomaly-metric" @click="selectMenu('reconciliation')">
              <span>被退回</span><strong>{{ stats.anomalies.returned }}</strong>
            </button>
          </section>

          <el-row :gutter="16" class="section">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" @click="openTransactionSource('sale', { date: dashboardDate })"><el-statistic title="当日销售" :value="stats.today.sales" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" @click="openTransactionSource('recycle', { date: dashboardDate })"><el-statistic title="当日回收" :value="stats.today.recycle" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" @click="openExchangeSource"><el-statistic title="当日换现" :value="stats.today.exchange" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" @click="openTransactionSource('operating_expense', { date: dashboardDate })"><el-statistic title="当日支出" :value="stats.today.operating_expenses" prefix="¥" /></el-card></el-col>
          </el-row>

          <el-row :gutter="16">
            <el-col :xs="12" :sm="8" :lg="4"><el-card shadow="hover" @click="openAccount('cash')"><el-statistic title="现金" :value="stats.cash" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="8" :lg="4"><el-card shadow="hover" @click="openAccount('online_wechat')"><el-statistic title="微信" :value="stats.online.wechat" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="8" :lg="4"><el-card shadow="hover" @click="openAccount('online_alipay')"><el-statistic title="支付宝" :value="stats.online.alipay" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="8" :lg="4"><el-card shadow="hover" @click="openAccount('online_bank')"><el-statistic title="银行卡" :value="stats.online.bank" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="8" :lg="4"><el-card shadow="hover" @click="openAccount('pure_gold_fund')"><el-statistic title="纯金专用资金" :value="stats.pure_gold_fund" prefix="¥" /></el-card></el-col>
            <el-col :xs="12" :sm="8" :lg="4"><el-card shadow="hover" @click="openAccount('total')"><el-statistic title="资金合计" :value="stats.total" prefix="¥" /></el-card></el-col>
          </el-row>

          <section class="trend-section section">
            <div class="section-heading">
              <div><h3>最近 7 天走势</h3><p>销售收入与回收支出</p></div>
            </div>
            <div ref="trendChartElement" class="trend-chart" />
          </section>

          <el-card class="section">
            <template #header>库存克重概览</template>
            <el-row :gutter="16">
              <el-col v-for="item in stockOverview" :key="item.label" :xs="24" :sm="12" :lg="6">
                <div class="stock-metric" role="button" tabindex="0" @click="openTransactionSource('', item)" @keyup.enter="openTransactionSource('', item)">
                  <span>{{ item.label }}</span>
                  <strong>{{ formatWeight(item.value) }}g</strong>
                  <small>{{ item.detail }}</small>
                </div>
              </el-col>
            </el-row>
          </el-card>

          <el-row :gutter="16" class="section">
            <el-col :xs="24" :lg="12">
              <el-card>
                <template #header>旧料回收成本</template>
                <el-descriptions :column="1" border>
                  <el-descriptions-item label="纯金">{{ stats.recycle_cost.pure_gold.pure_gold_weight }}g / ¥{{ formatMoney(stats.recycle_cost.pure_gold.amount) }} / 均价 ¥{{ formatMoney(stats.recycle_cost.pure_gold.average_gold_price) }}</el-descriptions-item>
                  <el-descriptions-item label="金包银">金{{ stats.recycle_cost.gold_wrapped_silver.wrapped_gold_weight }}g / 银{{ stats.recycle_cost.gold_wrapped_silver.silver_weight }}g / ¥{{ formatMoney(stats.recycle_cost.gold_wrapped_silver.amount) }}</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
            <el-col :xs="24" :lg="12">
              <el-card>
                <template #header>今日参考价</template>
                <el-form :inline="true" :model="recyclePrice">
                  <el-form-item label="日期"><el-date-picker v-model="recyclePrice.price_date" type="date" value-format="YYYY-MM-DD" @change="loadRecyclePrice" /></el-form-item>
                  <el-form-item label="金参考价"><el-input-number v-model="recyclePrice.reference_gold_price" :min="0" :precision="2" /></el-form-item>
                  <el-form-item label="银参考价"><el-input-number v-model="recyclePrice.reference_silver_price" :min="0" :precision="2" /></el-form-item>
                  <el-form-item><el-button type="primary" @click="saveRecyclePrice">保存</el-button></el-form-item>
                </el-form>
              </el-card>
            </el-col>
          </el-row>
        </el-main>

        <el-main v-if="activeMenu === 'transactions'">
          <el-card>
            <template #header>
              <div class="card-header">
                <span>流水管理</span>
                <div>
                  <el-button v-if="hasPermission('transactions')" :disabled="!canWriteCurrentStore" type="success" :icon="Plus" @click="openCreate('sale')">销售</el-button>
                  <el-button v-if="hasPermission('transactions')" :disabled="!canWriteCurrentStore" type="primary" :icon="Plus" @click="openCreate('income')">收入</el-button>
                  <el-button v-if="hasPermission('transactions') || hasPermission('recycle_pure_gold')" :disabled="!canWriteCurrentStore" type="warning" :icon="Plus" @click="openCreateRecycle('pure_gold')">纯金回收</el-button>
                  <el-button v-if="hasPermission('transactions') || hasPermission('recycle_gold_wrapped')" :disabled="!canWriteCurrentStore" type="warning" :icon="Plus" @click="openCreateRecycle('gold_wrapped')">金包银回收</el-button>
                  <el-button v-if="adminUser?.is_super_admin" :disabled="!canWriteCurrentStore" type="danger" :icon="Plus" @click="openCreate('operating_expense')">支出</el-button>
                </div>
              </div>
            </template>
            <el-form :inline="true">
              <el-form-item label="业务"><el-select v-model="filters.business_type" clearable @change="applyFilters"><el-option label="销售" value="sale" /><el-option label="收入" value="income" /><el-option label="回收" value="recycle" /><el-option v-if="adminUser?.is_super_admin" label="支出" value="operating_expense" /></el-select></el-form-item>
              <el-form-item v-if="adminUser?.is_super_admin" label="状态"><el-select v-model="filters.status" @change="applyFilters"><el-option label="有效" value="active" /><el-option label="已作废" value="voided" /><el-option label="全部" value="all" /></el-select></el-form-item>
              <el-form-item label="账户"><el-select v-model="filters.payment_account" clearable @change="applyFilters"><el-option label="现金" value="cash" /><el-option label="线上" value="online" /><el-option label="现金+线上" value="mixed" /><el-option label="纯金回收资金" value="pure_gold_fund" /></el-select></el-form-item>
              <el-form-item label="库存"><el-select v-model="filters.stock_bucket" clearable @change="applyFilters"><el-option label="销售货" value="sale_stock" /><el-option label="回收旧料" value="scrap_stock" /></el-select></el-form-item>
              <el-form-item label="开始日期"><el-date-picker v-model="filters.date_from" type="date" value-format="YYYY-MM-DD" @change="applyFilters" /></el-form-item>
              <el-form-item label="结束日期"><el-date-picker v-model="filters.date_to" type="date" value-format="YYYY-MM-DD" @change="applyFilters" /></el-form-item>
              <el-form-item>
                <el-button @click="applyFilters">筛选</el-button>
                <el-button @click="clearFilters">清空</el-button>
              </el-form-item>
            </el-form>
            <div class="table-scroll">
              <el-table :data="transactions" stripe border>
                <el-table-column prop="transaction_date" label="日期" width="120" />
                <el-table-column label="业务" width="110"><template #default="{ row }">{{ row.business_type_label?.label }}</template></el-table-column>
                <el-table-column label="商品/分类" width="130"><template #default="{ row }">{{ row.expense_category_label?.label || productName(row) }}</template></el-table-column>
                <el-table-column label="金额" width="130"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
                <el-table-column label="账户" width="160"><template #default="{ row }">{{ accountName(row) }}</template></el-table-column>
                <el-table-column label="重量" min-width="170"><template #default="{ row }">{{ weightText(row) }}</template></el-table-column>
                <el-table-column prop="remark" label="备注" min-width="160" />
                <el-table-column label="状态" width="110">
                  <template #default="{ row }"><el-tag :type="row.voided_at ? 'danger' : 'success'">{{ row.voided_at ? '已作废' : '有效' }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" width="130" fixed="right">
                  <template #default="{ row }">
                    <el-button v-if="canEditTransactions && !row.voided_at" :icon="Edit" text @click="openEdit(row)" />
                    <el-button v-if="canEditTransactions && !row.voided_at" :icon="Delete" text type="danger" title="作废" @click="deleteTransaction(row)" />
                    <span v-if="!canEditTransactions" class="muted">仅录入查询</span>
                  </template>
                </el-table-column>
              </el-table>
            </div>
            <el-pagination
              class="section pagination-bar"
              background
              layout="total, sizes, prev, pager, next, jumper"
              :current-page="pagination.page"
              :page-size="pagination.perPage"
              :page-sizes="[50, 100, 200]"
              :total="pagination.total"
              @current-change="changePage"
              @size-change="changePageSize"
            />
          </el-card>
        </el-main>

        <el-main v-if="activeMenu === 'exchanges'">
          <section class="tool-section">
            <div class="card-header">
              <div>
                <h3>现金与线上换现</h3>
                <p class="muted">每笔换现同时调整现金和指定线上账户。</p>
              </div>
              <el-button type="primary" :icon="Plus" :disabled="!canWriteCurrentStore" @click="openExchange">新增换现</el-button>
            </div>
            <div class="table-scroll section">
              <el-table :data="exchanges" stripe border>
                <el-table-column prop="exchange_date" label="日期" width="120" />
                <el-table-column label="方向" min-width="210">
                  <template #default="{ row }">{{ row.direction === 'cash_in' ? '收现金，转出线上' : '收线上，付出现金' }}</template>
                </el-table-column>
                <el-table-column label="线上账户" width="120">
                  <template #default="{ row }">{{ { bank: '银行卡', wechat: '微信', alipay: '支付宝' }[row.online_method] }}</template>
                </el-table-column>
                <el-table-column label="换现金额" width="130"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
                <el-table-column label="手续费" width="120"><template #default="{ row }">¥{{ formatMoney(row.fee) }}</template></el-table-column>
                <el-table-column prop="recorder.name" label="经手人" width="130" />
                <el-table-column prop="remark" label="备注" min-width="160" />
              </el-table>
            </div>
          </section>
        </el-main>

        <el-main v-if="activeMenu === 'scrap_outbounds'">
          <section class="tool-section">
            <div class="card-header">
              <div>
                <h3>旧料出库</h3>
                <p class="muted">出库后库存和实际到账同步更新。</p>
              </div>
              <el-button type="primary" :icon="Plus" :disabled="!canWriteCurrentStore" @click="openScrapOutbound">新增出库</el-button>
            </div>
            <div class="table-scroll section">
              <el-table :data="scrapOutbounds" stripe border>
                <el-table-column prop="outbound_date" label="日期" width="120" />
                <el-table-column label="旧料" width="130">
                  <template #default="{ row }">{{ row.product_type === 'pure_gold' ? '纯金' : row.product_type === 'pure_silver' ? '纯银' : row.wrap_material === 'copper' ? '金包铜' : '金包银' }}</template>
                </el-table-column>
                <el-table-column label="重量" min-width="180">
                  <template #default="{ row }">
                    {{ row.product_type === 'pure_gold' ? `${row.pure_gold_weight}g` : row.product_type === 'pure_silver' ? `${row.material_weight}g` : `金${row.wrapped_gold_weight}g / 材料${row.material_weight}g` }}
                  </template>
                </el-table-column>
                <el-table-column label="卖出总价" width="130"><template #default="{ row }">¥{{ formatMoney(row.gross_amount) }}</template></el-table-column>
                <el-table-column label="实际到账" width="130"><template #default="{ row }">¥{{ formatMoney(row.received_amount) }}</template></el-table-column>
                <el-table-column v-if="adminUser?.is_super_admin" label="原回收成本" width="140"><template #default="{ row }">¥{{ formatMoney(row.cost_amount) }}</template></el-table-column>
                <el-table-column v-if="adminUser?.is_super_admin" label="最终利润" width="130"><template #default="{ row }">¥{{ formatMoney(row.profit_amount) }}</template></el-table-column>
                <el-table-column prop="recorder.name" label="经手人" width="130" />
                <el-table-column prop="remark" label="备注" min-width="150" />
              </el-table>
            </div>
          </section>
        </el-main>

        <el-main v-if="activeMenu === 'reconciliation'" v-loading="reconciliationLoading">
          <template v-if="!adminUser?.is_super_admin">
            <el-alert
              v-if="hasPendingReconciliation"
              class="reconciliation-warning"
              title="今日交账尚未完成"
              type="error"
              :closable="false"
              show-icon
            />
            <el-alert
              title="请按实际数量填写。提交前系统不会显示账面数字；提交后如有差额，需要说明原因。"
              type="info"
              :closable="false"
            />
            <section v-for="section in reconciliationToday.sections" :key="section.section_type" class="reconciliation-section">
              <div class="reconciliation-heading">
                <div>
                  <h3>{{ section.section_type === 'pure_gold' ? '纯金回收交账' : '综合业务交账' }}</h3>
                  <p>{{ reconciliationToday.date }} · {{ section.version > 1 ? `第${section.version}次提交` : '今日首次提交' }}</p>
                </div>
                <el-tag :type="reconciliationStatusType(section.status)">{{ reconciliationStatus(section.status) }}</el-tag>
              </div>

              <el-form label-position="top" class="reconciliation-form">
                <el-checkbox
                  v-model="section.no_business"
                  :disabled="['submitted', 'confirmed'].includes(section.status)"
                  @change="toggleNoBusiness(section, $event)"
                >
                  今日无相关业务
                </el-checkbox>
                <h4 class="reconciliation-subtitle">今日业务合计</h4>
                <div class="reconciliation-grid">
                  <el-form-item v-for="field in section.business_summary_fields" :key="field" :label="reconciliationLabels[field]">
                    <el-input-number
                      v-model="section.business_summary[field]"
                      :disabled="section.no_business || ['submitted', 'confirmed'].includes(section.status)"
                      :precision="field.includes('pieces') ? 0 : field.includes('weight') ? 3 : 2"
                      :min="0"
                      @change="scheduleReconciliationDraft(section)"
                    />
                  </el-form-item>
                </div>
                <h4 class="reconciliation-subtitle">实际盘点</h4>
                <div class="reconciliation-grid">
                  <el-form-item v-for="field in section.fields" :key="field" :label="reconciliationLabels[field]">
                    <el-input-number
                      v-model="section.actual_snapshot[field]"
                      :disabled="['submitted', 'confirmed'].includes(section.status)"
                      :precision="field.includes('pieces') ? 0 : field.includes('weight') ? 3 : 2"
                      :min="0"
                      @change="scheduleReconciliationDraft(section)"
                    />
                  </el-form-item>
                </div>
                <el-form-item label="差额说明">
                  <el-input
                    v-model="section.difference_reason"
                    type="textarea"
                    :rows="2"
                    :disabled="['submitted', 'confirmed'].includes(section.status)"
                    placeholder="实盘与账面不一致时必须填写"
                    @input="scheduleReconciliationDraft(section)"
                  />
                </el-form-item>
              </el-form>

              <el-descriptions v-if="section.book_snapshot" :column="1" border class="section">
                <el-descriptions-item v-for="(_, field) in section.book_snapshot" :key="field" :label="reconciliationLabels[field]">
                  账面 {{ section.book_snapshot[field] }} / 实盘 {{ section.actual_snapshot[field] }} / 差额
                  <strong :class="{ 'difference-value': Number(section.differences?.[field]) !== 0 }">{{ section.differences?.[field] }}</strong>
                </el-descriptions-item>
              </el-descriptions>

              <el-alert v-if="section.status === 'returned'" :title="`退回原因：${section.return_reason}`" type="error" :closable="false" />
              <div class="reconciliation-actions">
                <el-button
                  type="primary"
                  size="large"
                  :disabled="['submitted', 'confirmed'].includes(section.status)"
                  @click="submitReconciliation(section)"
                >
                  {{ section.status === 'returned' ? '重新提交' : '提交今日交账' }}
                </el-button>
              </div>
            </section>
            <section class="reconciliation-section">
              <div class="reconciliation-heading">
                <div>
                  <h3>交账历史</h3>
                  <p>最近的提交、确认和退回结果</p>
                </div>
              </div>
              <div class="table-scroll">
                <el-table :data="reconciliationHistory" stripe>
                  <el-table-column type="expand">
                    <template #default="{ row }">
                      <div class="reconciliation-expanded">
                        <section v-for="section in row.sections" :key="section.id" class="review-section">
                          <div class="reconciliation-heading">
                            <div>
                              <h4>{{ section.section_type === 'pure_gold' ? '纯金回收' : '综合业务' }}</h4>
                              <p>提交人：{{ section.submitted_by?.name || '尚未提交' }} · 审核人：{{ section.reviewed_by?.name || '尚未审核' }}</p>
                            </div>
                            <el-tag :type="reconciliationStatusType(section.status)">{{ reconciliationStatus(section.status) }}</el-tag>
                          </div>
                          <el-descriptions v-if="Object.keys(section.business_summary || {}).length" title="业务合计" :column="2" border>
                            <el-descriptions-item v-for="(value, field) in section.business_summary" :key="field" :label="reconciliationLabels[field] || field">{{ value }}</el-descriptions-item>
                          </el-descriptions>
                          <el-descriptions v-if="Object.keys(section.actual_snapshot || {}).length" title="盘点结果" :column="1" border class="section">
                            <el-descriptions-item v-for="(value, field) in section.actual_snapshot" :key="field" :label="reconciliationLabels[field] || field">
                              <template v-if="section.book_snapshot">
                                账面 {{ section.book_snapshot[field] }} / 实盘 {{ value }} / 差额
                                <strong :class="{ 'difference-value': Number(section.differences?.[field]) !== 0 }">{{ section.differences?.[field] }}</strong>
                              </template>
                              <template v-else>实盘 {{ value }}</template>
                            </el-descriptions-item>
                          </el-descriptions>
                          <el-alert v-if="section.return_reason" :title="`退回原因：${section.return_reason}`" type="error" :closable="false" class="section" />
                        </section>
                      </div>
                    </template>
                  </el-table-column>
                  <el-table-column prop="date" label="日期" width="130" />
                  <el-table-column prop="store.name" label="店铺" min-width="150" />
                  <el-table-column label="状态" width="120">
                    <template #default="{ row }"><el-tag :type="reconciliationStatusType(row.status)">{{ reconciliationStatus(row.status) }}</el-tag></template>
                  </el-table-column>
                  <el-table-column label="交账部分" min-width="180">
                    <template #default="{ row }">{{ row.sections.map((item) => item.section_type === 'pure_gold' ? '纯金回收' : '综合业务').join('、') }}</template>
                  </el-table-column>
                </el-table>
              </div>
            </section>
          </template>

          <template v-else>
            <div class="reconciliation-owner-header">
              <div>
                <h3>每日交账</h3>
                <p>每家店每天一份总结果，纯金和综合业务可分别处理。</p>
              </div>
              <div class="reconciliation-owner-filters">
                <el-date-picker v-model="reconciliationDate" type="date" value-format="YYYY-MM-DD" clearable placeholder="全部日期" @change="loadReconciliations" />
                <el-select v-model="reconciliationEmployeeId" clearable placeholder="全部员工" @change="loadReconciliations">
                  <el-option v-for="employee in dashboardEmployees" :key="employee.id" :label="employee.name" :value="employee.id" />
                </el-select>
                <el-button :icon="Refresh" @click="loadReconciliations">刷新</el-button>
              </div>
            </div>
            <div class="table-scroll">
              <el-table :data="reconciliationReports" stripe border row-key="id">
                <el-table-column type="expand">
                  <template #default="{ row }">
                    <div class="reconciliation-expanded">
                      <section v-for="section in row.sections" :key="section.id" class="review-section">
                        <div class="reconciliation-heading">
                          <div>
                            <h4>{{ section.section_type === 'pure_gold' ? '纯金回收' : '综合业务' }}</h4>
                            <p>提交人：{{ section.submitted_by?.name || '尚未提交' }}</p>
                          </div>
                          <el-tag :type="reconciliationStatusType(section.status)">{{ reconciliationStatus(section.status) }}</el-tag>
                        </div>
                        <el-descriptions v-if="section.book_snapshot" :column="1" border>
                          <el-descriptions-item v-for="(_, field) in section.book_snapshot" :key="field" :label="reconciliationLabels[field]">
                            账面 {{ section.book_snapshot[field] }} / 实盘 {{ section.actual_snapshot[field] }} / 差额
                            <strong :class="{ 'difference-value': Number(section.differences?.[field]) !== 0 }">{{ section.differences?.[field] }}</strong>
                          </el-descriptions-item>
                          <el-descriptions-item label="员工说明">{{ section.difference_reason || '-' }}</el-descriptions-item>
                        </el-descriptions>
                        <div v-if="section.status === 'submitted'" class="reconciliation-actions">
                          <el-button type="success" :icon="Check" @click="confirmReconciliation(section)">确认</el-button>
                          <el-button type="danger" plain @click="returnReconciliation(section)">退回</el-button>
                        </div>
                      </section>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column prop="date" label="日期" width="130" />
                <el-table-column prop="store.name" label="店铺" min-width="160" />
                <el-table-column label="状态" width="130">
                  <template #default="{ row }"><el-tag :type="reconciliationStatusType(row.status)">{{ reconciliationStatus(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="完成情况" min-width="220">
                  <template #default="{ row }">{{ row.sections.length }} 个责任部分已有记录</template>
                </el-table-column>
              </el-table>
            </div>
          </template>
        </el-main>

        <el-main v-if="activeMenu === 'opening'">
          <el-alert title="期初数据只录一次，用来告诉系统现在账上和库存的起点。旧料先按总克重填，不需要按件拆。" type="info" :closable="false" />
          <el-card v-for="group in openingGroups" :key="group.title" class="section">
            <template #header>
              <div>
                <strong>{{ group.title }}</strong>
                <p class="opening-help">{{ group.description }}</p>
              </div>
            </template>
            <el-row :gutter="16">
              <el-col v-for="[key, label, unit] in group.fields" :key="key" :xs="24" :sm="12" :lg="8">
                <el-form-item :label="label">
                  <el-input-number v-model="opening[key]" :precision="unit === '件' ? 0 : 3" :min="-999999999" />
                  <span class="field-unit">{{ unit }}</span>
                </el-form-item>
              </el-col>
            </el-row>
          </el-card>
          <div class="save-bar">
            <el-button type="primary" size="large" @click="saveOpening">保存期初数据</el-button>
          </div>
        </el-main>

        <el-main v-if="activeMenu === 'users'">
          <el-card>
            <template #header>
              <div class="card-header">
                <span>用户管理</span>
                <el-button type="primary" :icon="Plus" @click="openCreateUser">新增用户</el-button>
              </div>
            </template>
            <div class="table-scroll">
              <el-table :data="users" stripe border>
                <el-table-column prop="name" label="姓名" width="140" />
                <el-table-column prop="username" label="登录账号" min-width="150" />
                <el-table-column prop="store.name" label="所属店铺" min-width="140" />
                <el-table-column label="角色" width="130">
                  <template #default="{ row }">
                    <el-tag :type="row.is_super_admin ? 'danger' : 'info'">{{ row.is_super_admin ? '老板' : '员工' }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                  <template #default="{ row }">
                    <el-tag :type="row.enabled ? 'success' : 'warning'">{{ row.enabled ? '启用' : '停用' }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="权限" min-width="220">
                  <template #default="{ row }">
                    <el-space wrap>
                      <el-tag v-for="permission in row.permissions" :key="permission">{{ permissionOptions[permission] || permission }}</el-tag>
                    </el-space>
                  </template>
                </el-table-column>
                <el-table-column prop="last_login_at" label="最后登录" width="180" />
                <el-table-column label="操作" width="220" fixed="right">
                  <template #default="{ row }">
                    <el-button :disabled="row.is_super_admin" :icon="Edit" text @click="openEditUser(row)" />
                    <el-button :disabled="row.is_super_admin" text @click="openResetPassword(row)">重置密码</el-button>
                    <el-button :disabled="row.is_super_admin || !row.enabled" :icon="Delete" text type="danger" @click="disableUser(row)" />
                  </template>
                </el-table-column>
              </el-table>
            </div>
            <el-pagination
              class="section pagination-bar"
              background
              layout="total, sizes, prev, pager, next, jumper"
              :current-page="userPagination.page"
              :page-size="userPagination.perPage"
              :page-sizes="[50, 100, 200]"
              :total="userPagination.total"
              @current-change="(page) => { userPagination.page = page; loadUsers() }"
              @size-change="(size) => { userPagination.perPage = size; userPagination.page = 1; loadUsers() }"
            />
          </el-card>
        </el-main>

        <el-main v-if="activeMenu === 'stores'">
          <el-card>
            <template #header>
              <div class="card-header">
                <span>店铺管理</span>
                <el-button type="primary" :icon="Plus" @click="openCreateStore">新增店铺</el-button>
              </div>
            </template>
            <el-table :data="stores" stripe border>
              <el-table-column prop="name" label="店铺名称" min-width="180" />
              <el-table-column label="状态" width="120">
                <template #default="{ row }">
                  <el-tag :type="row.enabled ? 'success' : 'warning'">{{ row.enabled ? '使用中' : '已停用' }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="180">
                <template #default="{ row }">
                  <el-button :icon="Edit" text @click="openEditStore(row)">修改</el-button>
                  <el-button v-if="row.enabled" type="danger" text @click="disableStore(row)">停用</el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </el-main>

        <el-main v-if="activeMenu === 'audit'">
          <el-card>
            <template #header><span>操作记录</span></template>
            <el-form :inline="true">
              <el-form-item label="操作"><el-select v-model="auditFilters.action" clearable @change="loadAuditLogs"><el-option label="流水修改" value="transaction.updated" /><el-option label="流水作废" value="transaction.voided" /><el-option label="店铺修改" value="store.updated" /><el-option label="员工修改" value="user.updated" /><el-option label="员工停用" value="user.disabled" /><el-option label="员工启用" value="user.enabled" /></el-select></el-form-item>
              <el-form-item label="开始日期"><el-date-picker v-model="auditFilters.date_from" type="date" value-format="YYYY-MM-DD" @change="loadAuditLogs" /></el-form-item>
              <el-form-item label="结束日期"><el-date-picker v-model="auditFilters.date_to" type="date" value-format="YYYY-MM-DD" @change="loadAuditLogs" /></el-form-item>
            </el-form>
            <div class="table-scroll">
              <el-table :data="auditLogs" stripe border>
                <el-table-column prop="created_at" label="时间" width="180" />
                <el-table-column prop="actor.name" label="操作人" width="140" />
                <el-table-column prop="store.name" label="店铺" width="140" />
                <el-table-column prop="action" label="操作类型" min-width="170" />
                <el-table-column prop="reason" label="原因" min-width="180" />
                <el-table-column label="详情" width="90"><template #default="{ row }"><el-button text @click="showAuditDetail(row)">查看</el-button></template></el-table-column>
              </el-table>
            </div>
            <el-pagination class="section pagination-bar" background layout="total, prev, pager, next" :current-page="auditPagination.page" :page-size="auditPagination.perPage" :total="auditPagination.total" @current-change="(page) => { auditPagination.page = page; loadAuditLogs() }" />
          </el-card>
        </el-main>
      </el-container>
    </el-container>

    <el-drawer v-model="mobileMenu" title="金银账本" direction="ltr" size="82%" class="mobile-drawer">
      <el-menu v-model:default-active="activeMenu" background-color="#101827" text-color="#c8d3e6" active-text-color="#fff">
        <el-menu-item v-for="item in visibleMenus" :key="item.key" :index="item.key" @click="selectMenu(item.key)">
          <el-icon><component :is="item.icon" /></el-icon>{{ item.label }}
        </el-menu-item>
      </el-menu>
      <div class="current-user">
        <strong>{{ adminUser?.name || '未命名用户' }}</strong>
        <span>{{ adminUser?.username }}</span>
        <el-tag size="small" :type="adminUser?.is_super_admin ? 'danger' : 'info'">
          {{ adminUser?.is_super_admin ? '老板' : '员工' }}
        </el-tag>
        <el-button text @click="openProfile">我的账户</el-button>
      </div>
      <el-button plain class="logout" @click="logout">退出</el-button>
    </el-drawer>

    <el-drawer v-model="accountDrawer" :title="accountTitle(selectedAccount)" size="min(92vw, 760px)">
      <el-radio-group v-model="accountRange" @change="loadAccountDetail">
        <el-radio-button label="month">当前月份</el-radio-button>
        <el-radio-button label="all">全部历史</el-radio-button>
      </el-radio-group>
      <div class="table-scroll section">
        <el-table :data="accountDetail.entries" border>
          <el-table-column prop="transaction_date" label="日期" width="110" />
          <el-table-column prop="business_type" label="业务" width="120" />
          <el-table-column prop="remark" label="备注" min-width="160" />
          <el-table-column label="变化" width="120"><template #default="{ row }">¥{{ formatMoney(row.signed_amount) }}</template></el-table-column>
          <el-table-column label="余额" width="120"><template #default="{ row }">¥{{ formatMoney(row.balance_after) }}</template></el-table-column>
        </el-table>
      </div>
    </el-drawer>

    <el-dialog v-model="exchangeDialog" title="新增换现" width="min(94vw, 560px)" class="responsive-dialog">
      <el-form :model="exchangeForm" label-width="110px" class="responsive-form">
        <el-form-item label="换现方向">
          <el-radio-group v-model="exchangeForm.direction">
            <el-radio-button value="cash_in">收现金，转线上</el-radio-button>
            <el-radio-button value="online_in">收线上，付现金</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="线上账户">
          <el-select v-model="exchangeForm.online_method">
            <el-option label="微信" value="wechat" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="银行卡" value="bank" />
          </el-select>
        </el-form-item>
        <el-form-item label="换现金额"><el-input-number v-model="exchangeForm.amount" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="手续费"><el-input-number v-model="exchangeForm.fee" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="日期"><el-date-picker v-model="exchangeForm.exchange_date" type="date" value-format="YYYY-MM-DD" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="exchangeForm.remark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="exchangeDialog = false">取消</el-button>
        <el-button type="primary" @click="saveExchange">保存换现</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="scrapOutboundDialog" title="新增旧料出库" width="min(96vw, 760px)" class="responsive-dialog">
      <el-form :model="scrapOutboundForm" label-width="120px" class="responsive-form">
        <el-row :gutter="12">
          <el-col :xs="24" :md="8">
            <el-form-item label="旧料种类">
              <el-select v-model="scrapOutboundForm.product_type" @change="changeOutboundProduct">
                <el-option label="纯金" value="pure_gold" />
                <el-option label="纯银" value="pure_silver" />
                <el-option label="金包银/铜" value="gold_wrapped" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col v-if="scrapOutboundForm.product_type === 'gold_wrapped'" :xs="24" :md="8">
            <el-form-item label="包裹材料">
              <el-select v-model="scrapOutboundForm.wrap_material"><el-option label="银" value="silver" /><el-option label="铜" value="copper" /></el-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :md="8"><el-form-item label="件数"><el-input-number v-model="scrapOutboundForm.material_pieces" :min="0" :precision="0" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col v-if="scrapOutboundForm.product_type === 'pure_gold'" :xs="24" :md="8"><el-form-item label="纯金克重"><el-input-number v-model="scrapOutboundForm.pure_gold_weight" :min="0" :precision="3" /></el-form-item></el-col>
          <el-col v-if="scrapOutboundForm.product_type === 'gold_wrapped'" :xs="24" :md="8"><el-form-item label="金重"><el-input-number v-model="scrapOutboundForm.wrapped_gold_weight" :min="0" :precision="3" /></el-form-item></el-col>
          <el-col v-if="scrapOutboundForm.product_type !== 'pure_gold'" :xs="24" :md="8"><el-form-item label="材料克重"><el-input-number v-model="scrapOutboundForm.material_weight" :min="0" :precision="3" /></el-form-item></el-col>
        </el-row>
        <el-divider content-position="left">卖出与到账</el-divider>
        <el-row :gutter="12">
          <el-col :xs="24" :md="8"><el-form-item label="卖出总价"><el-input-number v-model="scrapOutboundForm.gross_amount" :min="0" :precision="2" /></el-form-item></el-col>
          <el-col :xs="24" :md="8"><el-form-item label="实际到账"><el-input-number v-model="scrapOutboundForm.received_amount" :min="0" :precision="2" /></el-form-item></el-col>
          <el-col :xs="24" :md="8">
            <el-form-item label="到账账户">
              <el-select v-model="scrapOutboundForm.payment_account" :disabled="scrapOutboundForm.product_type === 'pure_gold'">
                <el-option label="现金" value="cash" /><el-option label="线上" value="online" /><el-option label="纯金专用资金" value="pure_gold_fund" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col v-if="scrapOutboundForm.payment_account === 'online'" :xs="24" :md="8">
            <el-form-item label="线上账户"><el-select v-model="scrapOutboundForm.online_method"><el-option label="微信" value="wechat" /><el-option label="支付宝" value="alipay" /><el-option label="银行卡" value="bank" /></el-select></el-form-item>
          </el-col>
        </el-row>
        <el-divider content-position="left">直接费用</el-divider>
        <div v-for="fee in scrapOutboundForm.fees" :key="fee.category" class="fee-row">
          <strong>{{ { processing: '加工费', refining: '提纯费', transport: '运输费', other: '其他费用' }[fee.category] }}</strong>
          <el-input-number v-model="fee.amount" :min="0" :precision="2" />
          <el-select v-model="fee.payment_account">
            <el-option label="买方扣除" value="deducted" /><el-option label="现金另付" value="cash" /><el-option label="线上另付" value="online" />
          </el-select>
          <el-select v-if="fee.payment_account === 'online'" v-model="fee.online_method"><el-option label="微信" value="wechat" /><el-option label="支付宝" value="alipay" /><el-option label="银行卡" value="bank" /></el-select>
        </div>
        <el-form-item label="出库日期"><el-date-picker v-model="scrapOutboundForm.outbound_date" type="date" value-format="YYYY-MM-DD" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="scrapOutboundForm.remark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="scrapOutboundDialog = false">取消</el-button>
        <el-button type="primary" @click="saveScrapOutbound">保存出库</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="transactionDialog" title="流水录入" width="min(94vw, 760px)" class="responsive-dialog">
      <el-form :model="form" label-width="110px" class="responsive-form">
        <el-row :gutter="12">
          <el-col :xs="24" :md="8">
            <el-form-item label="业务类型">
              <el-select v-model="form.business_type" @change="handleBusinessTypeChange">
                <el-option label="销售" value="sale" /><el-option label="收入" value="income" /><el-option label="回收" value="recycle" /><el-option v-if="adminUser?.is_super_admin" label="支出" value="operating_expense" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :md="8">
            <el-form-item label="账户">
              <el-select v-model="form.payment_account" @change="handlePaymentChange">
                <el-option v-for="option in paymentOptions" :key="option.value" :label="option.label" :value="option.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :md="8" v-if="form.payment_account === 'online' || form.payment_account === 'mixed'">
            <el-form-item label="线上方式">
              <el-select v-model="form.online_method"><el-option label="银行" value="bank" /><el-option label="微信" value="wechat" /><el-option label="支付宝" value="alipay" /></el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :xs="24" :md="8"><el-form-item label="日期"><el-date-picker v-model="form.transaction_date" type="date" value-format="YYYY-MM-DD" @change="loadRecyclePrice" /></el-form-item></el-col>
          <template v-if="isMixedPayment">
            <el-col :xs="24" :md="8"><el-form-item label="现金金额"><el-input-number v-model="form.cash_amount" :min="0" :precision="2" /></el-form-item></el-col>
            <el-col :xs="24" :md="8"><el-form-item label="线上金额"><el-input-number v-model="form.online_amount" :min="0" :precision="2" /></el-form-item></el-col>
            <el-col :xs="24" :md="8"><el-form-item label="合计金额"><el-input-number :model-value="mixedTotal" :min="0" :precision="2" disabled /></el-form-item></el-col>
          </template>
          <el-col :xs="24" :md="8" v-else-if="!isRecycle"><el-form-item label="金额"><el-input-number v-model="form.amount" :min="0" :precision="2" /></el-form-item></el-col>
          <el-col :xs="24" :md="8" v-if="form.business_type === 'operating_expense'"><el-form-item label="支出分类"><el-select v-model="form.expense_category"><el-option label="房租" value="rent" /><el-option label="电费" value="electricity" /><el-option label="水费" value="water" /><el-option label="工资" value="salary" /><el-option label="耗材" value="supplies" /><el-option label="其他" value="other" /></el-select></el-form-item></el-col>
        </el-row>

        <template v-if="isStockBusiness">
          <el-row :gutter="12">
            <el-col :xs="24" :md="8">
              <el-form-item label="商品">
                <el-select v-model="form.product_type">
                  <el-option label="纯金" value="pure_gold" /><el-option v-if="isSale" label="纯银" value="pure_silver" /><el-option label="金包银" value="gold_wrapped" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <template v-if="!isRecycle">
            <el-row :gutter="12">
              <el-col :xs="24" :md="8" v-if="form.product_type === 'pure_gold'"><el-form-item label="纯金克重"><el-input-number v-model="form.pure_gold_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :xs="24" :md="8" v-if="form.product_type === 'pure_silver'"><el-form-item label="银重"><el-input-number v-model="form.material_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :xs="24" :md="8" v-if="isGoldWrapped"><el-form-item label="金重"><el-input-number v-model="form.wrapped_gold_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :xs="24" :md="8" v-if="isGoldWrapped"><el-form-item label="银重"><el-input-number v-model="form.material_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :xs="24" :md="8"><el-form-item label="件数"><el-input-number v-model="form.material_pieces" :min="0" /></el-form-item></el-col>
            </el-row>
          </template>
          <template v-else>
            <el-alert class="recycle-tip" title="参考价仅作提示。成色不足时填写回收比例，例如 90 或 95，系统按单克价的对应百分比计算。" type="info" :closable="false" />
            <div v-for="(item, index) in itemRows" :key="index" class="item-row recycle-item-row">
              <div class="inline-field">
                <span>回收比例</span>
                <el-input-number v-model="form.recycle_price_rate" :min="0.01" :max="100" :precision="2" />
                <em>%</em>
              </div>
              <div v-if="form.product_type === 'pure_gold'" class="inline-field">
                <span>纯金克重</span>
                <el-input-number v-model="item.pure_gold_weight" :min="0" :precision="3" />
                <em>g</em>
              </div>
              <div v-if="isGoldWrapped" class="inline-field">
                <span>金重</span>
                <el-input-number v-model="item.wrapped_gold_weight" :min="0" :precision="3" />
                <em>g</em>
              </div>
              <div v-if="isGoldWrapped" class="inline-field">
                <span>银重</span>
                <el-input-number v-model="item.material_weight" :min="0" :precision="3" />
                <em>g</em>
              </div>
              <div class="inline-field">
                <span>金价</span>
                <el-input-number v-model="item.gold_unit_price" :min="0" :precision="2" />
                <em>参考 {{ formatMoney(recyclePrice.reference_gold_price || 0) }}</em>
              </div>
              <div v-if="isGoldWrapped" class="inline-field">
                <span>银价</span>
                <el-input-number v-model="item.silver_unit_price" :min="0" :precision="2" />
                <em>参考 {{ formatMoney(recyclePrice.reference_silver_price || 0) }}</em>
              </div>
              <el-button class="item-delete" :icon="Delete" @click="removeItem(index)" />
            </div>
            <el-button :icon="Plus" @click="addItem">添加一件</el-button>
            <el-statistic class="section" title="回收合计" :value="recycleAmount" prefix="¥" />
          </template>
        </template>

        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="3" /></el-form-item>
        <el-form-item v-if="editingId" label="修改原因"><el-input v-model="changeReason" type="textarea" :rows="2" placeholder="必填，至少 2 个字符" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="transactionDialog = false">取消</el-button>
        <el-button type="primary" :icon="CreditCard" @click="saveTransaction">保存</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="auditDrawer" title="操作详情" size="min(92vw, 720px)">
      <template v-if="auditDetail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="操作">{{ auditDetail.action }}</el-descriptions-item>
          <el-descriptions-item label="原因">{{ auditDetail.reason || '-' }}</el-descriptions-item>
          <el-descriptions-item label="修改前"><pre class="audit-json">{{ JSON.stringify(auditDetail.before_data, null, 2) }}</pre></el-descriptions-item>
          <el-descriptions-item label="修改后"><pre class="audit-json">{{ JSON.stringify(auditDetail.after_data, null, 2) }}</pre></el-descriptions-item>
        </el-descriptions>
      </template>
    </el-drawer>

    <el-dialog v-model="userDialog" :title="editingUserId ? '编辑用户' : '新增用户'" width="min(94vw, 620px)" class="responsive-dialog">
      <el-form :model="userForm" label-width="100px" class="responsive-form">
        <el-form-item label="姓名"><el-input v-model="userForm.name" /></el-form-item>
        <el-form-item label="登录账号"><el-input v-model="userForm.username" /></el-form-item>
        <el-form-item label="邮箱"><el-input v-model="userForm.email" /></el-form-item>
        <el-form-item label="所属店铺">
          <el-select v-model="userForm.store_id">
            <el-option v-for="store in stores.filter((item) => item.enabled)" :key="store.id" :label="store.name" :value="store.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="!editingUserId" label="初始密码"><el-input v-model="userForm.password" type="password" show-password /></el-form-item>
        <el-form-item label="启用"><el-switch v-model="userForm.enabled" /></el-form-item>
        <el-form-item label="员工职责">
          <el-radio-group v-model="userRole" @change="applyUserRole">
            <el-radio-button value="pure_gold">纯金回收</el-radio-button>
            <el-radio-button value="general">综合业务</el-radio-button>
            <el-radio-button value="all_business">全店业务</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="额外授权"><el-checkbox v-model="outboundAuthorized">允许旧料出库</el-checkbox></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="userDialog = false">取消</el-button>
        <el-button type="primary" @click="saveUser">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="storeDialog" :title="editingStoreId ? '修改店铺' : '新增店铺'" width="min(94vw, 460px)">
      <el-form :model="storeForm" label-width="90px">
        <el-form-item label="店铺名称"><el-input v-model="storeForm.name" /></el-form-item>
        <el-form-item v-if="editingStoreId" label="启用"><el-switch v-model="storeForm.enabled" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="storeDialog = false">取消</el-button>
        <el-button type="primary" @click="saveStore">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="profileDialog" title="我的账户" width="min(94vw, 520px)">
      <el-form label-width="100px">
        <el-divider content-position="left">账户资料</el-divider>
        <el-form-item label="姓名"><el-input v-model="profileForm.name" /></el-form-item>
        <el-form-item label="登录账号"><el-input v-model="profileForm.username" /></el-form-item>
        <el-form-item><el-button type="primary" @click="saveProfile">保存资料</el-button></el-form-item>
        <el-divider content-position="left">修改密码</el-divider>
        <el-form-item label="原密码"><el-input v-model="ownPasswordForm.current_password" type="password" show-password /></el-form-item>
        <el-form-item label="新密码"><el-input v-model="ownPasswordForm.password" type="password" show-password /></el-form-item>
        <el-form-item label="确认新密码"><el-input v-model="ownPasswordForm.password_confirmation" type="password" show-password /></el-form-item>
        <el-form-item><el-button type="warning" @click="changeOwnPassword">修改密码</el-button></el-form-item>
      </el-form>
    </el-dialog>

    <el-dialog v-model="passwordDialog" title="重置密码" width="min(94vw, 420px)" class="responsive-dialog">
      <el-form :model="passwordForm" label-width="90px" class="responsive-form">
        <el-form-item label="新密码"><el-input v-model="passwordForm.password" type="password" show-password /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="passwordDialog = false">取消</el-button>
        <el-button type="primary" @click="resetPassword">保存</el-button>
      </template>
    </el-dialog>
  </el-config-provider>
</template>
