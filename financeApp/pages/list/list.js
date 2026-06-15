const app = getApp()

Page({
  data: {
    month: new Date().toISOString().slice(0, 7),
    transactions: [],
    labels: {},
    businessTypes: [
      { code: '', label: '全部' },
      { code: 'sale', label: '销售' },
      { code: 'recycle', label: '回收' },
      { code: 'operating_expense', label: '支出' }
    ],
    businessIndex: 0
  },
  onShow() {
    this.ensureLogin(() => {
      this.syncLabels()
      this.loadTransactions()
    })
  },
  ensureLogin(done) {
    if (app.globalData.token) return done()
    app.login((error) => {
      if (error) return wx.showToast({ title: error, icon: 'none' })
      done()
    })
  },
  onMonthChange(event) {
    this.setData({ month: event.detail.value })
    this.loadTransactions()
  },
  syncLabels() {
    wx.setNavigationBarTitle({ title: app.t('nav.list', '流水') })
    this.setData({
      labels: {
        month: app.t('label.month', '月份'),
        type: app.t('label.business_type', '类型'),
        all: app.t('filter.all', '全部'),
        sale: app.t('business_type.sale', '销售'),
        recycle: app.t('business_type.recycle', '回收'),
        expense: app.t('business_type.operating_expense', '支出'),
        noData: app.t('state.no_transactions', '暂无流水')
      },
      businessTypes: [
        { code: '', label: app.t('filter.all', '全部') },
        { code: 'sale', label: app.t('business_type.sale', '销售') },
        { code: 'recycle', label: app.t('business_type.recycle', '回收') },
        { code: 'operating_expense', label: app.t('business_type.operating_expense', '支出') }
      ]
    })
  },
  onBusinessChange(event) {
    this.setData({ businessIndex: Number(event.detail.value) })
    this.loadTransactions()
  },
  loadTransactions() {
    const type = this.data.businessTypes[this.data.businessIndex].code
    const query = [`month=${this.data.month}`, 'per_page=50']
    if (type) query.push(`business_type=${type}`)
    app.request({
      url: `/app/transactions?${query.join('&')}`,
      success: (data) => this.setData({
        transactions: (data.data || []).map((item) => ({
          ...item,
          displayTitle: this.title(item),
          displayAccount: `${item.payment_account_label ? item.payment_account_label.label : ''}${item.online_method_label ? ' / ' + item.online_method_label.label : ''}`,
          displayMaterial: this.material(item),
          amountSign: item.business_type === 'sale' ? '+' : '-'
        }))
      }),
      fail: (message) => wx.showToast({ title: message, icon: 'none' })
    })
  },
  title(item) {
    const base = item.business_type_label ? item.business_type_label.label : item.business_type
    if (item.expense_category_label) return `${base} · ${item.expense_category_label.label}`
    if (item.product_type === 'pure_gold') return `${base} · ${app.t('product_type.pure_gold', '纯金')}`
    if (item.product_type === 'pure_silver') return `${base} · ${app.t('product_type.pure_silver', '纯银')}`
    if (item.product_type === 'gold_wrapped') return `${base} · ${app.t('product.gold_wrapped', '金包')}${item.wrap_material === 'copper' ? app.t('wrap_material.copper', '铜') : app.t('wrap_material.silver', '银')}`
    return base
  },
  material(item) {
    if (item.product_type === 'pure_gold') return `${app.t('product_type.pure_gold', '纯金')} ${item.pure_gold_weight}g · ${item.material_pieces}件`
    if (item.product_type === 'pure_silver') return `${app.t('wrap_material.silver', '银')} ${item.material_weight}g · ${item.material_pieces}件`
    if (item.product_type === 'gold_wrapped') return `${app.t('label.pure_gold_weight', '金')} ${item.wrapped_gold_weight}g / ${item.wrap_material === 'copper' ? app.t('wrap_material.copper', '铜') : app.t('wrap_material.silver', '银')} ${item.material_weight}g · ${item.material_pieces}件`
    return ''
  }
})
