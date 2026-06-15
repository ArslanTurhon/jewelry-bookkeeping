const app = getApp()

Page({
  data: {
    user: {},
    language: 'zh-CN',
    labels: {},
    balances: {},
    balanceFields: [
      { key: 'cash', label: '期初现金', value: 0 },
      { key: 'online_bank', label: '期初银行', value: 0 },
      { key: 'online_wechat', label: '期初微信', value: 0 },
      { key: 'online_alipay', label: '期初支付宝', value: 0 },
      { key: 'sale_stock.pure_gold.pure_gold_weight', label: '销售库存-纯金g', value: 0 },
      { key: 'sale_stock.pure_gold.pieces', label: '销售库存-纯金件', value: 0 },
      { key: 'sale_stock.pure_silver.silver_weight', label: '销售库存-纯银g', value: 0 },
      { key: 'sale_stock.pure_silver.pieces', label: '销售库存-纯银件', value: 0 },
      { key: 'sale_stock.gold_wrapped_silver.wrapped_gold_weight', label: '销售库存-金包银金g', value: 0 },
      { key: 'sale_stock.gold_wrapped_silver.silver_weight', label: '销售库存-金包银银g', value: 0 },
      { key: 'sale_stock.gold_wrapped_silver.pieces', label: '销售库存-金包银件', value: 0 },
      { key: 'sale_stock.gold_wrapped_copper.wrapped_gold_weight', label: '销售库存-金包铜金g', value: 0 },
      { key: 'sale_stock.gold_wrapped_copper.copper_weight', label: '销售库存-金包铜铜g', value: 0 },
      { key: 'sale_stock.gold_wrapped_copper.pieces', label: '销售库存-金包铜件', value: 0 },
      { key: 'scrap_stock.pure_gold.pure_gold_weight', label: '旧料库-纯金g', value: 0 },
      { key: 'scrap_stock.pure_gold.pieces', label: '旧料库-纯金件', value: 0 },
      { key: 'scrap_stock.pure_silver.silver_weight', label: '旧料库-纯银g', value: 0 },
      { key: 'scrap_stock.pure_silver.pieces', label: '旧料库-纯银件', value: 0 },
      { key: 'scrap_stock.gold_wrapped_silver.wrapped_gold_weight', label: '旧料库-金包银金g', value: 0 },
      { key: 'scrap_stock.gold_wrapped_silver.silver_weight', label: '旧料库-金包银银g', value: 0 },
      { key: 'scrap_stock.gold_wrapped_silver.pieces', label: '旧料库-金包银件', value: 0 },
      { key: 'scrap_stock.gold_wrapped_copper.wrapped_gold_weight', label: '旧料库-金包铜金g', value: 0 },
      { key: 'scrap_stock.gold_wrapped_copper.copper_weight', label: '旧料库-金包铜铜g', value: 0 },
      { key: 'scrap_stock.gold_wrapped_copper.pieces', label: '旧料库-金包铜件', value: 0 }
    ],
    languages: [
      { code: 'zh-CN', name: '简体中文' }
    ]
  },
  onShow() {
    this.setData({
      user: app.globalData.user || wx.getStorageSync('user') || {},
      language: app.globalData.language || 'zh-CN'
    })
    this.ensureLogin(() => {
      this.syncLabels()
      this.loadOpening()
      this.loadI18n()
    })
  },
  ensureLogin(done) {
    if (app.globalData.token) return done()
    app.login((error) => {
      if (error) return wx.showToast({ title: error, icon: 'none' })
      done()
    })
  },
  loadOpening() {
    app.request({
      url: '/app/opening-balance',
      success: (balances) => this.setData({
        balances,
        balanceFields: this.data.balanceFields.map((field) => ({ ...field, value: balances[field.key] || 0 }))
      }),
      fail: (message) => wx.showToast({ title: message, icon: 'none' })
    })
  },
  loadI18n() {
    app.loadI18n((error, data) => {
      if (!error && data.languages) this.setData({ languages: data.languages })
      this.syncLabels()
    })
  },
  syncLabels() {
    const keyMap = {
      cash: 'label.cash',
      online_bank: 'online_method.bank',
      online_wechat: 'online_method.wechat',
      online_alipay: 'online_method.alipay',
      'sale_stock.pure_gold.pure_gold_weight': 'label.pure_gold_weight',
      'sale_stock.pure_gold.pieces': 'label.pieces',
      'sale_stock.pure_silver.silver_weight': 'label.silver_weight',
      'sale_stock.pure_silver.pieces': 'label.pieces',
      'sale_stock.gold_wrapped_silver.wrapped_gold_weight': 'label.wrapped_gold_weight',
      'sale_stock.gold_wrapped_silver.silver_weight': 'label.silver_weight',
      'sale_stock.gold_wrapped_silver.pieces': 'label.pieces',
      'sale_stock.gold_wrapped_copper.wrapped_gold_weight': 'label.wrapped_gold_weight',
      'sale_stock.gold_wrapped_copper.copper_weight': 'label.copper_weight',
      'sale_stock.gold_wrapped_copper.pieces': 'label.pieces',
      'scrap_stock.pure_gold.pure_gold_weight': 'label.pure_gold_weight',
      'scrap_stock.pure_gold.pieces': 'label.pieces',
      'scrap_stock.pure_silver.silver_weight': 'label.silver_weight',
      'scrap_stock.pure_silver.pieces': 'label.pieces',
      'scrap_stock.gold_wrapped_silver.wrapped_gold_weight': 'label.wrapped_gold_weight',
      'scrap_stock.gold_wrapped_silver.silver_weight': 'label.silver_weight',
      'scrap_stock.gold_wrapped_silver.pieces': 'label.pieces',
      'scrap_stock.gold_wrapped_copper.wrapped_gold_weight': 'label.wrapped_gold_weight',
      'scrap_stock.gold_wrapped_copper.copper_weight': 'label.copper_weight',
      'scrap_stock.gold_wrapped_copper.pieces': 'label.pieces'
    }
    wx.setNavigationBarTitle({ title: app.t('nav.me', '我的') })
    this.setData({
      labels: {
        user: app.t('user.wechat', '微信用户'),
        title: app.t('app.title', '金银首饰店账本'),
        language: app.t('label.language', '语言'),
        currentLanguage: app.t('label.current_language', '当前语言'),
        opening: app.t('nav.opening', '期初资金和库存'),
        save: app.t('action.save', '保存期初'),
        relogin: app.t('action.relogin', '重新登录'),
        saved: app.t('status.saved', '已保存'),
        switched: app.t('status.language_switched', '语言已切换'),
        logged: app.t('status.logged_in', '已登录')
      },
      balanceFields: this.data.balanceFields.map((field) => ({ ...field, label: app.t(keyMap[field.key] || field.key, field.label) }))
    })
  },
  onBalanceInput(event) {
    const key = event.currentTarget.dataset.key
    const balances = { ...this.data.balances, [key]: event.detail.value }
    this.setData({ balances })
  },
  saveOpening() {
    app.request({
      url: '/app/opening-balance',
      method: 'POST',
      data: this.data.balances,
      success: (balances) => {
        this.setData({
          balances,
          balanceFields: this.data.balanceFields.map((field) => ({ ...field, value: balances[field.key] || 0 }))
        })
        wx.showToast({ title: app.t('status.saved', '已保存') })
      },
      fail: (message) => wx.showToast({ title: message, icon: 'none' })
    })
  },
  onLanguageChange(event) {
    const lang = this.data.languages[event.detail.value]
    app.setLanguage(lang.code, () => {
      this.setData({ language: lang.code })
      this.syncLabels()
      wx.showToast({ title: app.t('status.language_switched', '语言已切换') })
    })
  },
  relogin() {
    app.login((error, data) => {
      if (error) return wx.showToast({ title: error, icon: 'none' })
      this.setData({ user: data.user })
      wx.showToast({ title: app.t('status.logged_in', '已登录') })
    })
  }
})
