<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Coin, CreditCard, Delete, Edit, Money, Plus, Refresh, User, Wallet } from '@element-plus/icons-vue'
import { api, formatMoney } from './api'

const token = ref(localStorage.getItem('admin_token') || '')
const adminUser = ref(JSON.parse(localStorage.getItem('admin_user') || 'null'))
const loginForm = reactive({ email: 'admin@finance.local', password: 'password' })
const loading = ref(false)
const activeMenu = ref('dashboard')
const month = ref(today().slice(0, 7))
const stats = ref(null)
const transactions = ref([])
const opening = ref({})
const accountDrawer = ref(false)
const accountDetail = ref({ entries: [] })
const accountRange = ref('month')
const selectedAccount = ref('cash')
const transactionDialog = ref(false)
const editingId = ref(null)
const i18n = ref({ translations: {}, languages: [], enums: {} })
const recyclePrice = reactive({ price_date: today(), reference_gold_price: 0, reference_silver_price: 0 })
const filters = reactive({ business_type: '', payment_account: '', product_type: '', date_from: '', date_to: '' })
const pagination = reactive({ page: 1, perPage: 50, total: 0 })
const users = ref([])
const userPagination = reactive({ page: 1, perPage: 50, total: 0 })
const permissionOptions = ref({})
const userDialog = ref(false)
const passwordDialog = ref(false)
const editingUserId = ref(null)
const resetUserId = ref(null)
const userForm = reactive(defaultUserForm())
const passwordForm = reactive({ password: '' })
const form = reactive(defaultForm())
const itemRows = ref([defaultItem()])

const isAuthed = computed(() => Boolean(token.value))
const hasPermission = (permission) => adminUser.value?.is_super_admin || adminUser.value?.permissions?.includes(permission)
const canUseTransactions = computed(() => hasPermission('transactions') || hasPermission('recycle_pure_gold') || hasPermission('recycle_gold_wrapped'))
const canEditTransactions = computed(() => Boolean(adminUser.value?.is_super_admin))
const visibleMenus = computed(() => [
  { key: 'dashboard', label: '首页', icon: Wallet },
  { key: 'transactions', label: '流水', icon: Money, visible: canUseTransactions.value },
  { key: 'opening', label: '期初', icon: Coin },
  { key: 'users', label: '用户管理', icon: User },
].filter((item) => item.visible ?? hasPermission(item.key)))
const t = (key, fallback = key) => i18n.value.translations?.[key] || fallback
const isRecycle = computed(() => form.business_type === 'recycle')
const isSale = computed(() => form.business_type === 'sale')
const isStockBusiness = computed(() => ['sale', 'recycle'].includes(form.business_type))
const isGoldWrapped = computed(() => form.product_type === 'gold_wrapped')
const recycleAmount = computed(() => itemRows.value.reduce((sum, item) => {
  if (form.product_type === 'pure_gold') return sum + number(item.pure_gold_weight) * number(item.gold_unit_price)
  if (form.product_type === 'gold_wrapped') {
    return sum + number(item.wrapped_gold_weight) * number(item.gold_unit_price) + number(item.material_weight) * number(item.silver_unit_price)
  }
  return sum + number(item.material_weight) * number(item.silver_unit_price)
}, 0))

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

function defaultForm() {
  return {
    business_type: 'sale',
    payment_account: 'cash',
    online_method: '',
    amount: '',
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

function defaultUserForm() {
  return {
    name: '',
    email: '',
    password: '',
    enabled: true,
    permissions: ['dashboard', 'transactions'],
  }
}

async function login() {
  loading.value = true
  try {
    const { data } = await api.post('/admin/login', loginForm)
    token.value = data.token
    adminUser.value = data.admin
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
}

async function loadAll() {
  const tasks = [loadI18n()]
  if (hasPermission('dashboard')) tasks.push(loadStats())
  if (canUseTransactions.value) tasks.push(loadTransactions())
  if (hasPermission('opening')) tasks.push(loadOpening())
  if (hasPermission('recycle_price')) tasks.push(loadRecyclePrice(today()))
  if (hasPermission('users')) tasks.push(loadUsers(), loadPermissionOptions())
  await Promise.all(tasks)
  if (!visibleMenus.value.some((item) => item.key === activeMenu.value)) {
    activeMenu.value = visibleMenus.value[0]?.key || ''
  }
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
  const { data } = await api.get('/admin/stats/current', { params: { month: month.value } })
  stats.value = data
}

async function loadTransactions() {
  const { data } = await api.get('/admin/transactions', {
    params: { ...filters, month: month.value, page: pagination.page, per_page: pagination.perPage },
  })
  transactions.value = data.data || []
  pagination.total = data.total || 0
  pagination.page = data.current_page || pagination.page
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
  if (hasPermission('dashboard')) tasks.push(loadStats())
  if (canUseTransactions.value) tasks.push(loadTransactions())
  if (hasPermission('users')) tasks.push(loadUsers())
  await Promise.all(tasks)
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
  Object.assign(filters, { business_type: '', payment_account: '', product_type: '', date_from: '', date_to: '' })
  applyFilters()
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
  editingUserId.value = null
  userDialog.value = true
}

function openEditUser(row) {
  Object.assign(userForm, {
    name: row.name,
    email: row.email,
    password: '',
    enabled: row.enabled,
    permissions: row.permissions || [],
  })
  editingUserId.value = row.id
  userDialog.value = true
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

function openEdit(row) {
  Object.assign(form, defaultForm(), row, {
    transaction_date: String(row.transaction_date).slice(0, 10),
    amount: row.amount,
  })
  itemRows.value = row.item_weights?.length ? JSON.parse(JSON.stringify(row.item_weights)) : [defaultItem()]
  editingId.value = row.id
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
  if (payload.payment_account !== 'online') payload.online_method = ''
  if (!isStockBusiness.value) {
    payload.product_type = ''
    payload.wrap_material = ''
  }
  if (isRecycle.value) {
    payload.item_weights = itemRows.value
    payload.amount = recycleAmount.value
  } else {
    payload.item_weights = []
  }
  if (payload.business_type !== 'operating_expense') payload.expense_category = ''
  if (payload.product_type !== 'gold_wrapped') payload.wrap_material = ''
  return payload
}

async function saveTransaction() {
  try {
    const payload = buildPayload()
    if (editingId.value) {
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
  await ElMessageBox.confirm(`删除这笔 ${formatMoney(row.amount)} 元流水？`, '确认删除', { type: 'warning' })
  await api.delete(`/admin/transactions/${row.id}`)
  await refresh()
  ElMessage.success('已删除')
}

function productName(item) {
  if (item.product_type === 'pure_gold') return '纯金'
  if (item.product_type === 'pure_silver') return '纯银'
  if (item.product_type === 'gold_wrapped') return item.wrap_material === 'copper' ? '金包铜' : '金包银'
  return '-'
}

function accountName(item) {
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
</script>

<template>
  <el-config-provider>
    <main v-if="!isAuthed" class="login-page">
      <el-card class="login-card">
        <template #header>金银首饰店账本后台</template>
        <el-form :model="loginForm" label-position="top" @submit.prevent="login">
          <el-form-item label="账号"><el-input v-model="loginForm.email" /></el-form-item>
          <el-form-item label="密码"><el-input v-model="loginForm.password" type="password" show-password /></el-form-item>
          <el-button type="primary" :loading="loading" native-type="submit" class="full">登录</el-button>
        </el-form>
      </el-card>
    </main>

    <el-container v-else class="admin-shell">
      <el-aside width="220px">
        <div class="brand">金银账本</div>
        <el-menu v-model:default-active="activeMenu" background-color="#101827" text-color="#c8d3e6" active-text-color="#fff">
          <el-menu-item v-for="item in visibleMenus" :key="item.key" :index="item.key" @click="activeMenu = item.key">
            <el-icon><component :is="item.icon" /></el-icon>{{ item.label }}
          </el-menu-item>
        </el-menu>
        <div class="current-user">
          <strong>{{ adminUser?.name || '未命名用户' }}</strong>
          <span>{{ adminUser?.email }}</span>
          <el-tag size="small" :type="adminUser?.is_super_admin ? 'danger' : 'info'">
            {{ adminUser?.is_super_admin ? '超级管理员' : '普通用户' }}
          </el-tag>
        </div>
        <el-button plain class="logout" @click="logout">退出</el-button>
      </el-aside>

      <el-container>
        <el-header>
          <div>
            <h2>{{ visibleMenus.find((item) => item.key === activeMenu)?.label || '后台' }}</h2>
            <span>{{ month }}</span>
          </div>
          <div class="header-actions">
            <el-date-picker v-model="month" type="month" value-format="YYYY-MM" @change="refresh" />
            <el-button :icon="Refresh" @click="refresh">刷新</el-button>
          </div>
        </el-header>

        <el-main v-if="activeMenu === 'dashboard' && stats">
          <el-row :gutter="16">
            <el-col :span="6"><el-card shadow="hover" @click="openAccount('total')"><el-statistic title="资金合计" :value="stats.total" prefix="¥" /></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover" @click="openAccount('cash')"><el-statistic title="现金" :value="stats.cash" prefix="¥" /></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover" @click="openAccount('online')"><el-statistic title="线上" :value="stats.online.total" prefix="¥" /></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover" @click="openAccount('pure_gold_fund')"><el-statistic title="纯金回收资金" :value="stats.pure_gold_fund" prefix="¥" /></el-card></el-col>
          </el-row>

          <el-row :gutter="16" class="section">
            <el-col :span="6"><el-card><el-statistic title="本月销售" :value="stats.monthly.sales" prefix="¥" /></el-card></el-col>
            <el-col :span="6"><el-card><el-statistic title="本月收入" :value="stats.monthly.income" prefix="¥" /></el-card></el-col>
            <el-col :span="6"><el-card><el-statistic title="本月回收" :value="stats.monthly.recycle" prefix="¥" /></el-card></el-col>
            <el-col :span="6"><el-card><el-statistic title="本月支出" :value="stats.monthly.operating_expenses" prefix="¥" /></el-card></el-col>
          </el-row>

          <el-row :gutter="16" class="section">
            <el-col :span="12">
              <el-card>
                <template #header>旧料回收成本</template>
                <el-descriptions :column="1" border>
                  <el-descriptions-item label="纯金">{{ stats.recycle_cost.pure_gold.pure_gold_weight }}g / ¥{{ formatMoney(stats.recycle_cost.pure_gold.amount) }} / 均价 ¥{{ formatMoney(stats.recycle_cost.pure_gold.average_gold_price) }}</el-descriptions-item>
                  <el-descriptions-item label="金包银">金{{ stats.recycle_cost.gold_wrapped_silver.wrapped_gold_weight }}g / 银{{ stats.recycle_cost.gold_wrapped_silver.silver_weight }}g / ¥{{ formatMoney(stats.recycle_cost.gold_wrapped_silver.amount) }}</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
            <el-col :span="12">
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
                  <el-button v-if="hasPermission('transactions')" type="success" :icon="Plus" @click="openCreate('sale')">销售</el-button>
                  <el-button v-if="hasPermission('transactions')" type="primary" :icon="Plus" @click="openCreate('income')">收入</el-button>
                  <el-button v-if="hasPermission('transactions') || hasPermission('recycle_pure_gold')" type="warning" :icon="Plus" @click="openCreateRecycle('pure_gold')">纯金回收</el-button>
                  <el-button v-if="hasPermission('transactions') || hasPermission('recycle_gold_wrapped')" type="warning" :icon="Plus" @click="openCreateRecycle('gold_wrapped')">金包银回收</el-button>
                  <el-button v-if="hasPermission('transactions')" type="danger" :icon="Plus" @click="openCreate('operating_expense')">支出</el-button>
                </div>
              </div>
            </template>
            <el-form :inline="true">
              <el-form-item label="业务"><el-select v-model="filters.business_type" clearable @change="applyFilters"><el-option label="销售" value="sale" /><el-option label="收入" value="income" /><el-option label="回收" value="recycle" /><el-option label="支出" value="operating_expense" /></el-select></el-form-item>
              <el-form-item label="账户"><el-select v-model="filters.payment_account" clearable @change="applyFilters"><el-option label="现金" value="cash" /><el-option label="线上" value="online" /><el-option label="纯金回收资金" value="pure_gold_fund" /></el-select></el-form-item>
              <el-form-item label="开始日期"><el-date-picker v-model="filters.date_from" type="date" value-format="YYYY-MM-DD" @change="applyFilters" /></el-form-item>
              <el-form-item label="结束日期"><el-date-picker v-model="filters.date_to" type="date" value-format="YYYY-MM-DD" @change="applyFilters" /></el-form-item>
              <el-form-item>
                <el-button @click="applyFilters">筛选</el-button>
                <el-button @click="clearFilters">清空</el-button>
              </el-form-item>
            </el-form>
            <el-table :data="transactions" stripe border>
              <el-table-column prop="transaction_date" label="日期" width="120" />
              <el-table-column label="业务" width="110"><template #default="{ row }">{{ row.business_type_label?.label }}</template></el-table-column>
              <el-table-column label="商品/分类" width="130"><template #default="{ row }">{{ row.expense_category_label?.label || productName(row) }}</template></el-table-column>
              <el-table-column label="金额" width="130"><template #default="{ row }">¥{{ formatMoney(row.amount) }}</template></el-table-column>
              <el-table-column label="账户" width="160"><template #default="{ row }">{{ accountName(row) }}</template></el-table-column>
              <el-table-column label="重量"><template #default="{ row }">{{ weightText(row) }}</template></el-table-column>
              <el-table-column prop="remark" label="备注" />
              <el-table-column label="操作" width="130" fixed="right">
                <template #default="{ row }">
                  <el-button v-if="canEditTransactions" :icon="Edit" text @click="openEdit(row)" />
                  <el-button v-if="canEditTransactions" :icon="Delete" text type="danger" @click="deleteTransaction(row)" />
                  <span v-if="!canEditTransactions" class="muted">仅录入查询</span>
                </template>
              </el-table-column>
            </el-table>
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
              <el-col v-for="[key, label, unit] in group.fields" :key="key" :span="8">
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
            <el-table :data="users" stripe border>
              <el-table-column prop="name" label="姓名" width="140" />
              <el-table-column prop="email" label="账号邮箱" />
              <el-table-column label="角色" width="130">
                <template #default="{ row }">
                  <el-tag :type="row.is_super_admin ? 'danger' : 'info'">{{ row.is_super_admin ? '超级管理员' : '普通用户' }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.enabled ? 'success' : 'warning'">{{ row.enabled ? '启用' : '停用' }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="权限">
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
      </el-container>
    </el-container>

    <el-drawer v-model="accountDrawer" :title="accountTitle(selectedAccount)" size="50%">
      <el-radio-group v-model="accountRange" @change="loadAccountDetail">
        <el-radio-button label="month">当前月份</el-radio-button>
        <el-radio-button label="all">全部历史</el-radio-button>
      </el-radio-group>
      <el-table :data="accountDetail.entries" class="section" border>
        <el-table-column prop="transaction_date" label="日期" width="110" />
        <el-table-column prop="business_type" label="业务" width="120" />
        <el-table-column prop="remark" label="备注" />
        <el-table-column label="变化" width="120"><template #default="{ row }">¥{{ formatMoney(row.signed_amount) }}</template></el-table-column>
        <el-table-column label="余额" width="120"><template #default="{ row }">¥{{ formatMoney(row.balance_after) }}</template></el-table-column>
      </el-table>
    </el-drawer>

    <el-dialog v-model="transactionDialog" title="流水录入" width="760px">
      <el-form :model="form" label-width="110px">
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="业务类型">
              <el-select v-model="form.business_type">
                <el-option label="销售" value="sale" /><el-option label="收入" value="income" /><el-option label="回收" value="recycle" /><el-option label="支出" value="operating_expense" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="账户">
              <el-select v-model="form.payment_account">
                <el-option label="现金" value="cash" /><el-option label="线上" value="online" /><el-option label="纯金回收资金" value="pure_gold_fund" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8" v-if="form.payment_account === 'online'">
            <el-form-item label="线上方式">
              <el-select v-model="form.online_method"><el-option label="银行" value="bank" /><el-option label="微信" value="wechat" /><el-option label="支付宝" value="alipay" /></el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="日期"><el-date-picker v-model="form.transaction_date" type="date" value-format="YYYY-MM-DD" @change="loadRecyclePrice" /></el-form-item></el-col>
          <el-col :span="8" v-if="!isRecycle"><el-form-item label="金额"><el-input-number v-model="form.amount" :min="0" :precision="2" /></el-form-item></el-col>
          <el-col :span="8" v-if="form.business_type === 'operating_expense'"><el-form-item label="支出分类"><el-select v-model="form.expense_category"><el-option label="房租" value="rent" /><el-option label="电费" value="electricity" /><el-option label="水费" value="water" /><el-option label="工资" value="salary" /><el-option label="耗材" value="supplies" /><el-option label="其他" value="other" /></el-select></el-form-item></el-col>
        </el-row>

        <template v-if="isStockBusiness">
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="商品">
                <el-select v-model="form.product_type">
                  <el-option label="纯金" value="pure_gold" /><el-option v-if="isSale" label="纯银" value="pure_silver" /><el-option label="金包银" value="gold_wrapped" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <template v-if="!isRecycle">
            <el-row :gutter="12">
              <el-col :span="8" v-if="form.product_type === 'pure_gold'"><el-form-item label="纯金克重"><el-input-number v-model="form.pure_gold_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :span="8" v-if="form.product_type === 'pure_silver'"><el-form-item label="银重"><el-input-number v-model="form.material_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :span="8" v-if="isGoldWrapped"><el-form-item label="金重"><el-input-number v-model="form.wrapped_gold_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :span="8" v-if="isGoldWrapped"><el-form-item label="银重"><el-input-number v-model="form.material_weight" :min="0" :precision="3" /></el-form-item></el-col>
              <el-col :span="8"><el-form-item label="件数"><el-input-number v-model="form.material_pieces" :min="0" /></el-form-item></el-col>
            </el-row>
          </template>
          <template v-else>
            <el-alert title="参考价只作提示，实际每克回收价按每件手动填写。" type="info" :closable="false" />
            <div v-for="(item, index) in itemRows" :key="index" class="item-row">
              <el-input-number v-if="form.product_type === 'pure_gold'" v-model="item.pure_gold_weight" :min="0" :precision="3" placeholder="纯金g" />
              <el-input-number v-if="isGoldWrapped" v-model="item.wrapped_gold_weight" :min="0" :precision="3" placeholder="金g" />
              <el-input-number v-if="isGoldWrapped" v-model="item.material_weight" :min="0" :precision="3" placeholder="银g" />
              <el-input-number v-model="item.gold_unit_price" :min="0" :precision="2" :placeholder="`金价 参考${recyclePrice.reference_gold_price || 0}`" />
              <el-input-number v-if="isGoldWrapped" v-model="item.silver_unit_price" :min="0" :precision="2" :placeholder="`银价 参考${recyclePrice.reference_silver_price || 0}`" />
              <el-button :icon="Delete" @click="removeItem(index)" />
            </div>
            <el-button :icon="Plus" @click="addItem">添加一件</el-button>
            <el-statistic class="section" title="回收合计" :value="recycleAmount" prefix="¥" />
          </template>
        </template>

        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="transactionDialog = false">取消</el-button>
        <el-button type="primary" :icon="CreditCard" @click="saveTransaction">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="userDialog" :title="editingUserId ? '编辑用户' : '新增用户'" width="620px">
      <el-form :model="userForm" label-width="100px">
        <el-form-item label="姓名"><el-input v-model="userForm.name" /></el-form-item>
        <el-form-item label="邮箱"><el-input v-model="userForm.email" /></el-form-item>
        <el-form-item v-if="!editingUserId" label="初始密码"><el-input v-model="userForm.password" type="password" show-password /></el-form-item>
        <el-form-item label="启用"><el-switch v-model="userForm.enabled" /></el-form-item>
        <el-form-item label="模块权限">
          <el-checkbox-group v-model="userForm.permissions">
            <el-checkbox v-for="(label, key) in permissionOptions" :key="key" :label="key" :disabled="key === 'users'">{{ label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="userDialog = false">取消</el-button>
        <el-button type="primary" @click="saveUser">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="passwordDialog" title="重置密码" width="420px">
      <el-form :model="passwordForm" label-width="90px">
        <el-form-item label="新密码"><el-input v-model="passwordForm.password" type="password" show-password /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="passwordDialog = false">取消</el-button>
        <el-button type="primary" @click="resetPassword">保存</el-button>
      </template>
    </el-dialog>
  </el-config-provider>
</template>
