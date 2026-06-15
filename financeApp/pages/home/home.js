const app = getApp()

Page({
  data: {
    month: new Date().toISOString().slice(0, 7),
    stats: null,
    labels: {}
  },
  onShow() {
    this.ensureLogin(() => {
      this.syncLabels()
      this.loadStats()
    })
  },
  ensureLogin(done) {
    if (app.globalData.token) {
      done()
      return
    }
    app.login((error) => {
      if (error) {
        wx.showToast({ title: error, icon: 'none' })
        return
      }
      done()
    })
  },
  loadStats() {
    app.request({
      url: `/app/stats/current?month=${this.data.month}`,
      success: (stats) => this.setData({ stats }),
      fail: (message) => wx.showToast({ title: message, icon: 'none' })
    })
  },
  onMonthChange(event) {
    this.setData({ month: event.detail.value })
    this.loadStats()
  },
  syncLabels() {
    wx.setNavigationBarTitle({ title: app.t('nav.home', '经营看板') })
    this.setData({
      labels: {
        title: app.t('nav.home', '经营看板'),
        switchMonth: app.t('action.refresh', '切换月份'),
        total: app.t('label.total', '资金合计'),
        cash: app.t('label.cash', '现金'),
        online: app.t('label.online', '线上'),
        bank: app.t('online_method.bank', '银行'),
        wechat: app.t('online_method.wechat', '微信'),
        alipay: app.t('online_method.alipay', '支付宝'),
        monthSales: app.t('summary.month_sales', '本月销售'),
        monthRecycle: app.t('summary.month_recycle', '本月回收'),
        monthExpense: app.t('summary.month_expense', '店铺支出'),
        netChange: app.t('summary.net_change', '净变化'),
        saleStock: app.t('label.sale_stock', '销售库存'),
        scrapStock: app.t('label.scrap_stock', '旧料库'),
        pureGold: app.t('product_type.pure_gold', '纯金'),
        wrappedGold: app.t('product.gold_wrapped', '金包'),
        silver: app.t('wrap_material.silver', '银'),
        copper: app.t('wrap_material.copper', '铜')
      }
    })
  }
})
