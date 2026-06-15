<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, formatMoney } from './api'

const token = ref(localStorage.getItem('admin_token') || '')
const loginForm = ref({ email: 'admin@finance.local', password: 'password' })
const loginError = ref('')
const loading = ref(false)
const activeTab = ref('overview')
const month = ref(new Date().toISOString().slice(0, 7))
const language = ref(localStorage.getItem('language') || 'zh-CN')
const stats = ref(null)
const transactions = ref([])
const opening = ref({})
const openingMessage = ref('')
const i18n = ref({ translations: {}, languages: [], enums: {} })
const languages = ref([])
const translations = ref([])
const newLanguage = ref({ code: '', name: '', enabled: true, sort_order: 10 })
const translationForm = ref({ language_code: 'zh-CN', translation_key: '', translation_value: '' })
const filters = ref({ business_type: '', payment_account: '', product_type: '', expense_category: '' })

const isAuthed = computed(() => Boolean(token.value))
const t = (key, fallback = key) => i18n.value.translations?.[key] || fallback
const languageLabel = (item) => t(`language.${item.code}`, item.name)

const openingFields = computed(() => [
  ['cash', t('label.cash', '期初现金')], ['online_bank', '期初银行'], ['online_wechat', '期初微信'], ['online_alipay', '期初支付宝'],
  ['sale_stock.pure_gold.pure_gold_weight', `销售库存-${t('product_type.pure_gold', '纯金')}g`], ['sale_stock.pure_gold.pieces', `销售库存-${t('product_type.pure_gold', '纯金')}件`],
  ['sale_stock.pure_silver.silver_weight', `销售库存-${t('product_type.pure_silver', '纯银')}g`], ['sale_stock.pure_silver.pieces', `销售库存-${t('product_type.pure_silver', '纯银')}件`],
  ['sale_stock.gold_wrapped_silver.wrapped_gold_weight', `销售库存-${t('product.gold_wrapped_silver', '金包银')}金g`], ['sale_stock.gold_wrapped_silver.silver_weight', `销售库存-${t('product.gold_wrapped_silver', '金包银')}银g`], ['sale_stock.gold_wrapped_silver.pieces', `销售库存-${t('product.gold_wrapped_silver', '金包银')}件`],
  ['sale_stock.gold_wrapped_copper.wrapped_gold_weight', `销售库存-${t('product.gold_wrapped_copper', '金包铜')}金g`], ['sale_stock.gold_wrapped_copper.copper_weight', `销售库存-${t('product.gold_wrapped_copper', '金包铜')}铜g`], ['sale_stock.gold_wrapped_copper.pieces', `销售库存-${t('product.gold_wrapped_copper', '金包铜')}件`],
  ['scrap_stock.pure_gold.pure_gold_weight', `旧料库-${t('product_type.pure_gold', '纯金')}g`], ['scrap_stock.pure_gold.pieces', `旧料库-${t('product_type.pure_gold', '纯金')}件`],
  ['scrap_stock.pure_silver.silver_weight', `旧料库-${t('product_type.pure_silver', '纯银')}g`], ['scrap_stock.pure_silver.pieces', `旧料库-${t('product_type.pure_silver', '纯银')}件`],
  ['scrap_stock.gold_wrapped_silver.wrapped_gold_weight', `旧料库-${t('product.gold_wrapped_silver', '金包银')}金g`], ['scrap_stock.gold_wrapped_silver.silver_weight', `旧料库-${t('product.gold_wrapped_silver', '金包银')}银g`], ['scrap_stock.gold_wrapped_silver.pieces', `旧料库-${t('product.gold_wrapped_silver', '金包银')}件`],
  ['scrap_stock.gold_wrapped_copper.wrapped_gold_weight', `旧料库-${t('product.gold_wrapped_copper', '金包铜')}金g`], ['scrap_stock.gold_wrapped_copper.copper_weight', `旧料库-${t('product.gold_wrapped_copper', '金包铜')}铜g`], ['scrap_stock.gold_wrapped_copper.pieces', `旧料库-${t('product.gold_wrapped_copper', '金包铜')}件`],
])

async function login() {
  loginError.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/admin/login', loginForm.value)
    token.value = data.token
    localStorage.setItem('admin_token', data.token)
    await loadAll()
  } catch (error) {
    loginError.value = error.response?.data?.message || t('status.login_failed', '登录失败')
  } finally {
    loading.value = false
  }
}

function logout() {
  api.post('/admin/logout').catch(() => {})
  localStorage.removeItem('admin_token')
  token.value = ''
}

async function loadAll() {
  await Promise.all([loadI18n(), loadStats(), loadTransactions(), loadOpening()])
}

async function loadI18n() {
  const { data } = await api.get('/admin/i18n', { params: { lang: language.value } })
  i18n.value = data
  languages.value = data.languages || []
}

async function loadStats() {
  const { data } = await api.get('/admin/stats/current', { params: { month: month.value } })
  stats.value = data
}

async function loadTransactions() {
  const { data } = await api.get('/admin/transactions', {
    params: { ...filters.value, month: month.value, per_page: 100 },
  })
  transactions.value = data.data || []
}

async function loadOpening() {
  const { data } = await api.get('/admin/opening-balance')
  opening.value = data
}

async function saveOpening() {
  openingMessage.value = ''
  try {
    const { data } = await api.post('/admin/opening-balance', opening.value)
    opening.value = data
    openingMessage.value = t('status.saved', '期初数据已保存')
    await loadStats()
  } catch (error) {
    openingMessage.value = error.response?.data?.message || t('status.save_failed', '期初数据保存失败，请重新登录后再试')
  }
}

function fillOpeningDemo() {
  opening.value = {
    ...opening.value,
    cash: 1234.56,
    online_bank: 200,
    online_wechat: 300,
    online_alipay: 400,
    'sale_stock.pure_gold.pure_gold_weight': 12.345,
    'sale_stock.pure_gold.pieces': 2,
    'sale_stock.gold_wrapped_silver.wrapped_gold_weight': 3.21,
    'sale_stock.gold_wrapped_silver.silver_weight': 15.8,
    'sale_stock.gold_wrapped_silver.pieces': 4,
    'scrap_stock.pure_silver.silver_weight': 88.88,
    'scrap_stock.pure_silver.pieces': 6,
  }
  openingMessage.value = t('action.fill_demo', '已填入测试数据，点击保存期初写入数据库')
}

async function refresh() {
  await Promise.all([loadStats(), loadTransactions()])
}

async function changeLanguage() {
  localStorage.setItem('language', language.value)
  await loadI18n()
}

async function loadTranslations() {
  const { data } = await api.get('/admin/translations', { params: { language_code: translationForm.value.language_code } })
  translations.value = data
}

async function saveLanguage() {
  await api.post('/admin/languages', newLanguage.value)
  newLanguage.value = { code: '', name: '', enabled: true, sort_order: 10 }
  await loadI18n()
}

async function saveTranslation() {
  await api.post('/admin/translations', translationForm.value)
  translationForm.value.translation_key = ''
  translationForm.value.translation_value = ''
  await Promise.all([loadTranslations(), loadI18n()])
}

function goHome() {
  activeTab.value = 'overview'
}

async function openDictionary() {
  activeTab.value = 'dictionary'
  await loadTranslations()
}

function productName(item) {
  if (item.product_type === 'pure_gold') return t('product_type.pure_gold', '纯金')
  if (item.product_type === 'pure_silver') return t('product_type.pure_silver', '纯银')
  if (item.product_type === 'gold_wrapped') return item.wrap_material === 'copper' ? t('product.gold_wrapped_copper', '金包铜') : t('product.gold_wrapped_silver', '金包银')
  return '-'
}

onMounted(() => {
  if (isAuthed.value) {
    loadAll().catch(() => {
      localStorage.removeItem('admin_token')
      token.value = ''
    })
  } else if (new URLSearchParams(window.location.search).get('autologin') === '1') {
    login()
  }
})
</script>

<template>
  <main v-if="!isAuthed" class="login-shell">
    <section class="login-panel">
      <h1>{{ t('app.title', '金银首饰店账本') }}</h1>
      <form @submit.prevent="login" class="login-form">
        <label>{{ t('label.account', '账号') }}<input v-model="loginForm.email" type="email" autocomplete="username" /></label>
        <label>{{ t('label.password', '密码') }}<input v-model="loginForm.password" type="password" autocomplete="current-password" /></label>
        <p v-if="loginError" class="error">{{ loginError }}</p>
        <button :disabled="loading">{{ loading ? t('state.logging_in', '登录中...') : t('action.login', '登录') }}</button>
      </form>
    </section>
  </main>

  <main v-else class="app-shell">
    <aside class="sidebar">
      <button class="brand" type="button" @click="goHome">
        <h1>{{ t('app.title', '金银账本') }}</h1>
        <p>{{ t('nav.home', '经营管理后台') }}</p>
      </button>
      <nav>
        <button :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">{{ t('nav.home', '概览') }}</button>
        <button :class="{ active: activeTab === 'transactions' }" @click="activeTab = 'transactions'">{{ t('nav.list', '流水') }}</button>
        <button :class="{ active: activeTab === 'opening' }" @click="activeTab = 'opening'">{{ t('nav.opening', '期初') }}</button>
        <button :class="{ active: activeTab === 'dictionary' }" @click="openDictionary">{{ t('nav.dictionary', '字典') }}</button>
      </nav>
      <button class="ghost" @click="logout">退出</button>
    </aside>

    <section class="workspace">
      <header class="toolbar">
        <div><h2>{{ activeTab === 'overview' ? t('page.overview.title', '经营概览') : activeTab === 'transactions' ? t('page.transactions.title', '业务流水') : activeTab === 'opening' ? t('page.opening.title', '期初设置') : t('page.dictionary.title', '多语言字典') }}</h2><p>{{ month }}</p></div>
        <div class="toolbar-actions">
          <button v-if="activeTab !== 'overview'" class="ghost" type="button" @click="goHome">{{ t('nav.back', '返回概览') }}</button>
          <select v-model="language" @change="changeLanguage"><option v-for="item in languages" :key="item.code" :value="item.code">{{ languageLabel(item) }}</option></select>
          <input v-model="month" type="month" @change="refresh" />
        </div>
      </header>

      <section v-if="activeTab === 'overview' && stats" class="overview">
        <div class="metric"><span>{{ t('label.total', '资金合计') }}</span><strong>¥{{ formatMoney(stats.total) }}</strong></div>
        <div class="metric income"><span>{{ t('label.cash', '现金') }}</span><strong>¥{{ formatMoney(stats.cash) }}</strong></div>
        <div class="metric"><span>{{ t('label.online', '线上') }}</span><strong>¥{{ formatMoney(stats.online.total) }}</strong></div>
        <div class="metric expense"><span>{{ t('summary.net_change', '本月净变化') }}</span><strong>¥{{ formatMoney(stats.monthly.net) }}</strong></div>
        <div class="panel wide">
          <h3>{{ t('label.sale_stock', '销售库存') }}</h3>
          <div class="inventory-grid">
            <b>{{ t('product_type.pure_gold', '纯金') }} {{ stats.stock.sale_stock.summary.pure_gold_weight }}g</b>
            <b>{{ t('product.gold_wrapped', '金包') }} {{ stats.stock.sale_stock.summary.wrapped_gold_weight }}g</b>
            <b>{{ t('wrap_material.silver', '银') }} {{ stats.stock.sale_stock.summary.silver_weight }}g</b>
            <b>{{ t('wrap_material.copper', '铜') }} {{ stats.stock.sale_stock.summary.copper_weight }}g</b>
          </div>
        </div>
        <div class="panel wide">
          <h3>{{ t('label.scrap_stock', '旧料库') }}</h3>
          <div class="inventory-grid">
            <b>{{ t('product_type.pure_gold', '纯金') }} {{ stats.stock.scrap_stock.summary.pure_gold_weight }}g</b>
            <b>{{ t('product.gold_wrapped', '金包') }} {{ stats.stock.scrap_stock.summary.wrapped_gold_weight }}g</b>
            <b>{{ t('wrap_material.silver', '银') }} {{ stats.stock.scrap_stock.summary.silver_weight }}g</b>
            <b>{{ t('wrap_material.copper', '铜') }} {{ stats.stock.scrap_stock.summary.copper_weight }}g</b>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'transactions'" class="panel">
        <div class="filters">
          <select v-model="filters.business_type" @change="loadTransactions"><option value="">{{ t('filter.all', '全部业务') }}</option><option value="sale">{{ t('business_type.sale', '销售') }}</option><option value="recycle">{{ t('business_type.recycle', '回收') }}</option><option value="operating_expense">{{ t('business_type.operating_expense', '店铺支出') }}</option></select>
          <select v-model="filters.payment_account" @change="loadTransactions"><option value="">{{ t('filter.all', '全部账户') }}</option><option value="cash">{{ t('payment_account.cash', '现金') }}</option><option value="online">{{ t('payment_account.online', '线上') }}</option></select>
          <select v-model="filters.product_type" @change="loadTransactions"><option value="">{{ t('filter.all', '全部商品') }}</option><option value="pure_gold">{{ t('product_type.pure_gold', '纯金') }}</option><option value="pure_silver">{{ t('product_type.pure_silver', '纯银') }}</option><option value="gold_wrapped">{{ t('product_type.gold_wrapped', '金包') }}</option></select>
          <button @click="loadTransactions">{{ t('action.refresh', '筛选') }}</button>
        </div>
        <table>
          <thead><tr><th>{{ t('label.date', '日期') }}</th><th>{{ t('label.business_type', '业务') }}</th><th>{{ t('label.product', '商品/分类') }}</th><th>{{ t('label.amount', '金额') }}</th><th>{{ t('label.account', '账户') }}</th><th>{{ t('label.weight', '重量') }}</th><th>{{ t('label.remark', '备注') }}</th></tr></thead>
          <tbody>
            <tr v-for="item in transactions" :key="item.id">
              <td>{{ item.transaction_date }}</td>
              <td>{{ item.business_type_label?.label }}</td>
              <td>{{ item.expense_category_label?.label || productName(item) }}</td>
              <td :class="item.business_type === 'sale' ? 'income' : 'expense'">¥{{ formatMoney(item.amount) }}</td>
              <td>{{ item.payment_account_label?.label }} {{ item.online_method_label?.label || '' }}</td>
              <td>{{ item.product_type === 'pure_gold' ? `${item.pure_gold_weight}g` : item.product_type === 'pure_silver' ? `${item.material_weight}g` : item.product_type === 'gold_wrapped' ? `金${item.wrapped_gold_weight}g / 料${item.material_weight}g` : '-' }}</td>
              <td>{{ item.remark || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="activeTab === 'opening'" class="panel">
        <div class="opening-grid">
          <label v-for="[key, label] in openingFields" :key="key">{{ label }}<input v-model="opening[key]" type="number" step="0.001" /></label>
        </div>
        <div class="form-actions">
          <button type="button" @click="saveOpening">{{ t('action.save', '保存期初') }}</button>
          <button type="button" class="ghost" @click="fillOpeningDemo">填入测试数据</button>
        </div>
        <p v-if="openingMessage" :class="openingMessage.includes('失败') ? 'error' : 'success'">{{ openingMessage }}</p>
      </section>

      <section v-if="activeTab === 'dictionary'" class="dictionary-grid">
        <div class="panel dictionary-intro">
        <h3>{{ t('page.dictionary.title', '字典管理') }}</h3>
          <p>{{ t('page.dictionary.help', '左侧菜单会一直保留；如果要离开当前页面，可以点击左侧菜单、左上角“金银账本”，或右上角“返回概览”。') }}</p>
        </div>
        <form class="panel" @submit.prevent="saveLanguage">
          <h3>{{ t('page.dictionary.new_language', '新增语言') }}</h3>
          <input v-model="newLanguage.code" :placeholder="t('page.dictionary.language_code_placeholder', '语言编码，如 ug-CN')" required />
          <input v-model="newLanguage.name" :placeholder="t('page.dictionary.language_name_placeholder', '语言名称')" required />
          <button>{{ t('action.save_language', '保存语言') }}</button>
        </form>
        <form class="panel" @submit.prevent="saveTranslation">
          <h3>{{ t('page.dictionary.translation', '维护翻译') }}</h3>
          <select v-model="translationForm.language_code" @change="loadTranslations"><option v-for="item in languages" :key="item.code" :value="item.code">{{ languageLabel(item) }}</option></select>
          <input v-model="translationForm.translation_key" :placeholder="t('page.dictionary.translation_key_placeholder', '字典 key')" required />
          <input v-model="translationForm.translation_value" :placeholder="t('page.dictionary.translation_value_placeholder', '翻译内容')" required />
          <button>{{ t('action.save_translation', '保存翻译') }}</button>
        </form>
        <div class="panel wide">
          <h3>{{ t('page.dictionary.current', '当前字典') }}</h3>
          <div v-for="item in translations" :key="item.id" class="translation-row"><code>{{ item.translation_key }}</code><span>{{ item.translation_value }}</span></div>
        </div>
      </section>
    </section>
  </main>
</template>
