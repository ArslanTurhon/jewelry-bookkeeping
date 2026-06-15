App({
  globalData: {
    apiBase: 'http://financebackend.test/api',
    token: '',
    user: null,
    language: 'zh-CN',
    i18n: {}
  },
  onLaunch() {
    this.globalData.token = wx.getStorageSync('token') || ''
    this.globalData.user = wx.getStorageSync('user') || null
    this.globalData.language = wx.getStorageSync('language') || 'zh-CN'
    this.loadI18n()
  },
  request(options) {
    const token = this.globalData.token || wx.getStorageSync('token')
    wx.request({
      url: `${this.globalData.apiBase}${options.url}`,
      method: options.method || 'GET',
      data: options.data || {},
      header: {
        Authorization: token ? `Bearer ${token}` : '',
        'Content-Type': 'application/json',
        'X-Language': this.globalData.language || 'zh-CN'
      },
      success: (res) => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          options.success && options.success(res.data)
        } else {
          const message = res.data && res.data.message ? res.data.message : '请求失败'
          options.fail && options.fail(message)
        }
      },
      fail: () => options.fail && options.fail('无法连接服务器')
    })
  },
  loadI18n(done) {
    this.request({
      url: `/app/i18n?lang=${this.globalData.language || 'zh-CN'}`,
      success: (data) => {
        this.globalData.i18n = data
        this.updateTabBar()
        done && done(null, data)
      },
      fail: (message) => done && done(message)
    })
  },
  t(key, fallback = key) {
    return this.globalData.i18n && this.globalData.i18n.translations && this.globalData.i18n.translations[key]
      ? this.globalData.i18n.translations[key]
      : fallback
  },
  updateTabBar() {
    const items = [
      ['pages/home/home', 'nav.home', '首页'],
      ['pages/entry/entry', 'nav.entry', '记账'],
      ['pages/list/list', 'nav.list', '流水'],
      ['pages/me/me', 'nav.me', '我的']
    ]
    items.forEach(([pagePath, key, fallback], index) => {
      wx.setTabBarItem({
        index,
        text: this.t(key, fallback)
      })
    })
    wx.getCurrentPages().forEach((page) => {
      const route = page.route || ''
      const current = items.find(([pagePath]) => pagePath === route)
      if (current) {
        wx.setNavigationBarTitle({ title: this.t(current[1], current[2]) })
      }
    })
  },
  setLanguage(language, done) {
    this.globalData.language = language
    wx.setStorageSync('language', language)
    this.loadI18n(done)
  },
  login(done) {
    wx.login({
      success: ({ code }) => {
        this.request({
          url: '/app/login',
          method: 'POST',
          data: { code, nickname: this.t('user.wechat', '微信用户') },
          success: (data) => {
            this.globalData.token = data.token
            this.globalData.user = data.user
            wx.setStorageSync('token', data.token)
            wx.setStorageSync('user', data.user)
            done && done(null, data)
          },
          fail: (message) => done && done(message)
        })
      },
      fail: () => done && done('微信登录失败')
    })
  }
})
