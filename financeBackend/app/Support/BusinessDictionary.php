<?php

namespace App\Support;

use App\Models\Language;
use App\Models\Translation;

class BusinessDictionary
{
    public const DEFAULT_LANGUAGE = 'zh-CN';

    public const ENUMS = [
        'business_type' => ['sale', 'recycle', 'operating_expense'],
        'payment_account' => ['cash', 'online'],
        'online_method' => ['bank', 'wechat', 'alipay'],
        'stock_bucket' => ['sale_stock', 'scrap_stock'],
        'product_type' => ['pure_gold', 'pure_silver', 'gold_wrapped'],
        'wrap_material' => ['silver', 'copper'],
        'expense_category' => ['rent', 'electricity', 'water', 'salary', 'supplies', 'other'],
    ];

    public static function defaults(): array
    {
        return [
            'app.title' => '金银首饰店账本',
            'nav.home' => '首页',
            'nav.entry' => '记账',
            'nav.list' => '流水',
            'nav.me' => '我的',
            'nav.opening' => '期初',
            'nav.dictionary' => '字典',
            'nav.back' => '返回概览',
            'action.save' => '保存',
            'action.refresh' => '刷新',
            'action.login' => '登录',
            'action.fill_demo' => '填入测试数据',
            'action.save_language' => '保存语言',
            'action.save_translation' => '保存翻译',
            'action.relogin' => '重新登录',
            'label.cash' => '现金',
            'label.online' => '线上',
            'label.total' => '合计',
            'label.sale_stock' => '销售库存',
            'label.scrap_stock' => '旧料库',
            'label.month' => '月份',
            'label.business_type' => '业务类型',
            'label.payment_account' => '支付方式',
            'label.online_method' => '线上方式',
            'label.product' => '商品',
            'label.language' => '语言',
            'label.account' => '账号',
            'label.password' => '密码',
            'label.expense_category' => '支出分类',
            'label.current_language' => '当前语言',
            'label.pure_gold_weight' => '纯金克重',
            'label.wrapped_gold_weight' => '金包金重',
            'label.silver_weight' => '银重',
            'label.copper_weight' => '铜重',
            'label.pieces' => '件数',
            'label.amount' => '金额',
            'label.date' => '日期',
            'label.remark' => '备注',
            'label.weight' => '重量',
            'summary.month_sales' => '本月销售',
            'summary.month_recycle' => '本月回收',
            'summary.month_expense' => '店铺支出',
            'summary.net_change' => '净变化',
            'filter.all' => '全部',
            'state.no_transactions' => '暂无流水',
            'state.logging_in' => '登录中...',
            'status.saved' => '已保存',
            'status.language_switched' => '语言已切换',
            'status.logged_in' => '已登录',
            'status.login_failed' => '登录失败',
            'status.save_failed' => '保存失败',
            'user.wechat' => '微信用户',
            'page.overview.title' => '经营概览',
            'page.transactions.title' => '业务流水',
            'page.opening.title' => '期初设置',
            'page.dictionary.title' => '多语言字典',
            'page.dictionary.help' => '左侧菜单会一直保留；如果要离开当前页面，可以点击左侧菜单、左上角“金银账本”，或右上角“返回概览”。',
            'page.dictionary.new_language' => '新增语言',
            'page.dictionary.translation' => '维护翻译',
            'page.dictionary.current' => '当前字典',
            'page.dictionary.language_code_placeholder' => '语言编码，如 ug-CN',
            'page.dictionary.language_name_placeholder' => '语言名称',
            'page.dictionary.translation_key_placeholder' => '字典 key',
            'page.dictionary.translation_value_placeholder' => '翻译内容',
            'language.zh-CN' => '简体中文',
            'language.ug-CN' => '中国新疆维吾尔语',
            'language.test-LANG' => '测试语言',
            'business_type.sale' => '销售',
            'business_type.recycle' => '回收',
            'business_type.operating_expense' => '店铺成本支出',
            'payment_account.cash' => '现金',
            'payment_account.online' => '线上',
            'online_method.bank' => '银行',
            'online_method.wechat' => '微信',
            'online_method.alipay' => '支付宝',
            'stock_bucket.sale_stock' => '销售库存',
            'stock_bucket.scrap_stock' => '旧料库',
            'product_type.pure_gold' => '纯金',
            'product_type.pure_silver' => '纯银',
            'product_type.gold_wrapped' => '金包',
            'wrap_material.silver' => '银',
            'wrap_material.copper' => '铜',
            'product.gold_wrapped_silver' => '金包银',
            'product.gold_wrapped_copper' => '金包铜',
            'expense_category.rent' => '房租',
            'expense_category.electricity' => '电费',
            'expense_category.water' => '水费',
            'expense_category.salary' => '工资',
            'expense_category.supplies' => '耗材',
            'expense_category.other' => '其他',
        ];
    }

    public function catalog(string $language = self::DEFAULT_LANGUAGE): array
    {
        $translations = $this->translations($language);

        return [
            'language' => $language,
            'languages' => Language::query()->where('enabled', true)->orderBy('sort_order')->get(),
            'translations' => $translations,
            'enums' => collect(self::ENUMS)->map(fn ($values, $group) => collect($values)
                ->map(fn ($value) => [
                    'code' => $value,
                    'label' => $translations["{$group}.{$value}"] ?? $translations[$value] ?? $value,
                ])
                ->values()
            ),
        ];
    }

    public function translations(string $language = self::DEFAULT_LANGUAGE): array
    {
        $fallback = Translation::query()
            ->where('language_code', self::DEFAULT_LANGUAGE)
            ->pluck('translation_value', 'translation_key')
            ->all();

        if ($language === self::DEFAULT_LANGUAGE) {
            return $fallback;
        }

        $current = Translation::query()
            ->where('language_code', $language)
            ->pluck('translation_value', 'translation_key')
            ->all();

        return array_replace($fallback, $current);
    }

    public function label(string $group, ?string $value, string $language = self::DEFAULT_LANGUAGE): ?array
    {
        if (! $value) {
            return null;
        }

        $translations = $this->translations($language);
        $key = "{$group}.{$value}";

        return [
            'code' => $value,
            'label' => $translations[$key] ?? $value,
        ];
    }
}
