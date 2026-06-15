const app = getApp()

function today() {
  return new Date().toISOString().slice(0, 10)
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
    material_pieces: '',
    expense_category: 'rent',
    transaction_date: today(),
    remark: ''
  }
}

Page({
  data: {
    form: defaultForm(),
    productLabel: '纯金',
    secondWeightLabel: '银重',
    labels: {},
    businessTypes: [
      { code: 'sale', label: '销售' },
      { code: 'recycle', label: '回收' },
      { code: 'operating_expense', label: '店铺支出' }
    ],
    productTypes: [
      { code: 'pure_gold', label: '纯金' },
      { code: 'pure_silver', label: '纯银' },
      { code: 'gold_wrapped_silver', label: '金包银' },
      { code: 'gold_wrapped_copper', label: '金包铜' }
    ],
    paymentAccounts: [
      { code: 'cash', label: '现金' },
      { code: 'online', label: '线上' }
    ],
    onlineMethods: [
      { code: 'bank', label: '银行' },
      { code: 'wechat', label: '微信' },
      { code: 'alipay', label: '支付宝' }
    ],
    expenseCategories: [
      { code: 'rent', label: '房租' },
      { code: 'electricity', label: '电费' },
      { code: 'water', label: '水费' },
      { code: 'salary', label: '工资' },
      { code: 'supplies', label: '耗材' },
      { code: 'other', label: '其他' }
    ]
  },
  onShow() {
    this.ensureLogin(() => this.syncLabels())
  },
  ensureLogin(done) {
    if (app.globalData.token) return done()
    app.login((error) => {
      if (error) return wx.showToast({ title: error, icon: 'none' })
      done()
    })
  },
  setBusiness(event) {
    this.setData({ 'form.business_type': event.currentTarget.dataset.code })
  },
  setPayment(event) {
    const code = event.currentTarget.dataset.code
    this.setData({
      'form.payment_account': code,
      'form.online_method': code === 'online' ? (this.data.form.online_method || 'bank') : ''
    })
  },
  syncLabels() {
    wx.setNavigationBarTitle({ title: app.t('nav.entry', '记账') })
    this.setData({
      labels: {
        businessType: app.t('label.business_type', '业务类型'),
        paymentAccount: app.t('label.payment_account', '支付方式'),
        onlineMethod: app.t('label.online_method', '线上方式'),
        amount: app.t('label.amount', '金额'),
        product: app.t('label.product', '商品'),
        pureGoldWeight: app.t('label.pure_gold_weight', '纯金克重'),
        silverWeight: app.t('label.silver_weight', '银重'),
        wrappedGoldWeight: app.t('label.wrapped_gold_weight', '金包类金重'),
        date: app.t('label.date', '日期'),
        remark: app.t('label.remark', '备注'),
        save: app.t('action.save', '保存'),
        all: app.t('filter.all', '全部'),
        expenseCategory: app.t('label.expense_category', '支出分类'),
        pieces: app.t('label.pieces', '件数')
      },
      businessTypes: [
        { code: 'sale', label: app.t('business_type.sale', '销售') },
        { code: 'recycle', label: app.t('business_type.recycle', '回收') },
        { code: 'operating_expense', label: app.t('business_type.operating_expense', '店铺支出') }
      ],
      productTypes: [
        { code: 'pure_gold', label: app.t('product_type.pure_gold', '纯金') },
        { code: 'pure_silver', label: app.t('product_type.pure_silver', '纯银') },
        { code: 'gold_wrapped_silver', label: app.t('product.gold_wrapped_silver', '金包银') },
        { code: 'gold_wrapped_copper', label: app.t('product.gold_wrapped_copper', '金包铜') }
      ],
      paymentAccounts: [
        { code: 'cash', label: app.t('payment_account.cash', '现金') },
        { code: 'online', label: app.t('payment_account.online', '线上') }
      ],
      onlineMethods: [
        { code: 'bank', label: app.t('online_method.bank', '银行') },
        { code: 'wechat', label: app.t('online_method.wechat', '微信') },
        { code: 'alipay', label: app.t('online_method.alipay', '支付宝') }
      ],
      expenseCategories: [
        { code: 'rent', label: app.t('expense_category.rent', '房租') },
        { code: 'electricity', label: app.t('expense_category.electricity', '电费') },
        { code: 'water', label: app.t('expense_category.water', '水费') },
        { code: 'salary', label: app.t('expense_category.salary', '工资') },
        { code: 'supplies', label: app.t('expense_category.supplies', '耗材') },
        { code: 'other', label: app.t('expense_category.other', '其他') }
      ],
      productLabel: this.data.form.product_type === 'pure_silver'
        ? app.t('product_type.pure_silver', '纯银')
        : this.data.form.product_type === 'gold_wrapped'
          ? this.data.form.wrap_material === 'copper'
            ? app.t('product.gold_wrapped_copper', '金包铜')
            : app.t('product.gold_wrapped_silver', '金包银')
          : app.t('product_type.pure_gold', '纯金'),
      secondWeightLabel: this.data.form.product_type === 'gold_wrapped' && this.data.form.wrap_material === 'copper'
        ? app.t('label.copper_weight', '铜重')
        : app.t('label.silver_weight', '银重')
    })
  },
  onProductChange(event) {
    const selected = this.data.productTypes[event.detail.value].code
    const updates = {}
    if (selected === 'gold_wrapped_silver') {
      updates['form.product_type'] = 'gold_wrapped'
      updates['form.wrap_material'] = 'silver'
      updates.productLabel = app.t('product.gold_wrapped_silver', '金包银')
      updates.secondWeightLabel = app.t('label.silver_weight', '银重')
    } else if (selected === 'gold_wrapped_copper') {
      updates['form.product_type'] = 'gold_wrapped'
      updates['form.wrap_material'] = 'copper'
      updates.productLabel = app.t('product.gold_wrapped_copper', '金包铜')
      updates.secondWeightLabel = app.t('label.copper_weight', '铜重')
    } else {
      updates['form.product_type'] = selected
      updates['form.wrap_material'] = ''
      updates.productLabel = selected === 'pure_silver' ? app.t('product_type.pure_silver', '纯银') : app.t('product_type.pure_gold', '纯金')
      updates.secondWeightLabel = app.t('label.silver_weight', '银重')
    }
    this.setData(updates)
  },
  onOnlineChange(event) {
    this.setData({ 'form.online_method': this.data.onlineMethods[event.detail.value].code })
  },
  onExpenseChange(event) {
    this.setData({ 'form.expense_category': this.data.expenseCategories[event.detail.value].code })
  },
  onDateChange(event) {
    this.setData({ 'form.transaction_date': event.detail.value })
  },
  onInput(event) {
    this.setData({ [`form.${event.currentTarget.dataset.field}`]: event.detail.value })
  },
  submit() {
    const payload = { ...this.data.form }
    if (payload.business_type !== 'operating_expense') {
      payload.expense_category = ''
    }
    if (payload.payment_account === 'cash') {
      payload.online_method = ''
    }
    app.request({
      url: '/app/transactions',
      method: 'POST',
      data: payload,
      success: () => {
        wx.showToast({ title: app.t('status.saved', '已保存') })
        this.setData({ form: defaultForm(), productLabel: app.t('product_type.pure_gold', '纯金'), secondWeightLabel: app.t('label.silver_weight', '银重') })
      },
      fail: (message) => wx.showToast({ title: message, icon: 'none' })
    })
  }
})
