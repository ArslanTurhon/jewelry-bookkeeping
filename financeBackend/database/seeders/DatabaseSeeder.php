<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Language;
use App\Models\OpeningBalance;
use App\Models\Store;
use App\Models\Translation;
use App\Support\BusinessDictionary;
use App\Support\FinanceStats;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $store = Store::query()->firstOrCreate(
            ['is_default' => true],
            ['name' => '总店', 'enabled' => true],
        );
        $legacyCategories = [
            ['name' => '销售', 'type' => 'income', 'color' => '#16a34a'],
            ['name' => '收入', 'type' => 'income', 'color' => '#2563eb'],
            ['name' => '回收', 'type' => 'expense', 'color' => '#f97316'],
            ['name' => '店铺成本', 'type' => 'expense', 'color' => '#64748b'],
        ];

        foreach ($legacyCategories as $category) {
            Category::query()->updateOrCreate(
                ['name' => $category['name'], 'type' => $category['type']],
                $category + ['is_system' => true],
            );
        }

        AdminUser::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@finance.local')],
            [
                'name' => '管理员',
                'username' => env('ADMIN_USERNAME', 'owner'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'is_super_admin' => true,
                'enabled' => true,
                'permissions' => array_keys(AdminUser::PERMISSIONS),
            ],
        );

        Language::query()->updateOrCreate(
            ['code' => BusinessDictionary::DEFAULT_LANGUAGE],
            ['name' => '简体中文', 'enabled' => true, 'sort_order' => 1],
        );

        Language::query()->updateOrCreate(
            ['code' => 'ug-CN'],
            ['name' => '中国新疆维吾尔语', 'enabled' => true, 'sort_order' => 2],
        );

        foreach (BusinessDictionary::defaults() as $key => $value) {
            Translation::query()->updateOrCreate(
                ['language_code' => BusinessDictionary::DEFAULT_LANGUAGE, 'translation_key' => $key],
                ['translation_value' => $value],
            );
        }

        $uyghurTranslations = [
            'app.title' => 'ئالتۇن-كۈمۈش زىبۇزىننەت دەپتىرى',
            'nav.home' => 'باش بەت',
            'nav.entry' => 'خاتىرىلەش',
            'nav.list' => 'ئاقىم',
            'nav.me' => 'مېنىڭ',
            'nav.opening' => 'دەسلەپكى سانلار',
            'nav.dictionary' => 'لۇغەت',
            'nav.back' => 'ئومۇمىي كۆرۈنۈشكە قايتىش',
            'action.save' => 'ساقلاش',
            'action.refresh' => 'يېڭىلاش',
            'action.login' => 'كىرىش',
            'action.fill_demo' => 'سىناق سانلىق مەلۇمات تولدۇرۇش',
            'action.save_language' => 'تىل ساقلاش',
            'action.save_translation' => 'تەرجىمە ساقلاش',
            'action.relogin' => 'قايتا كىرىش',
            'label.cash' => 'نەق پۇل',
            'label.online' => 'تور',
            'label.pure_gold_fund' => 'ساپ ئالتۇن قايتۇرۇش مەبلىغى',
            'label.total' => 'جەمئى',
            'label.sale_stock' => 'سېتىش ئامبىرى',
            'label.scrap_stock' => 'كونا ماتېرىيال ئامبىرى',
            'label.month' => 'ئاي',
            'label.business_type' => 'ئۆتكۈزۈش تۈرى',
            'label.payment_account' => 'تۆلەش ئۇسۇلى',
            'label.online_method' => 'تور ئۇسۇلى',
            'label.product' => 'مال',
            'label.language' => 'تىل',
            'label.account' => 'ھېساب',
            'label.password' => 'پارول',
            'label.expense_category' => 'خىراجەت تۈرى',
            'label.current_language' => 'نۆۋەتتىكى تىل',
            'label.pure_gold_weight' => 'ساپ ئالتۇن گرامى',
            'label.wrapped_gold_weight' => 'ئالتۇن قاپلانغان ئالتۇن گرامى',
            'label.silver_weight' => 'كۈمۈش ئېغىرلىقى',
            'label.copper_weight' => 'مىس ئېغىرلىقى',
            'label.pieces' => 'سانى',
            'label.amount' => 'سومما',
            'label.date' => 'چېسلا',
            'label.remark' => 'ئىزاھات',
            'label.weight' => 'ئېغىرلىق',
            'summary.month_sales' => 'بۇ ئاي سېتىش',
            'summary.month_recycle' => 'بۇ ئاي قايتۇرۇش',
            'summary.month_expense' => 'دۇكان خىراجىتى',
            'summary.net_change' => 'ساپ ئۆزگىرىش',
            'filter.all' => 'ھەممىسى',
            'state.no_transactions' => 'ئاقىم يوق',
            'state.logging_in' => 'كىرىۋاتىدۇ...',
            'status.saved' => 'ساقلاندى',
            'status.language_switched' => 'تىل ئالماشتۇرۇلدى',
            'status.logged_in' => 'كىردىڭىز',
            'status.login_failed' => 'كىرىش مەغلۇپ بولدى',
            'status.save_failed' => 'ساقلاش مەغلۇپ بولدى',
            'user.wechat' => 'ۋېيشىن ئىشلەتكۈچىسى',
            'page.overview.title' => 'ئىگىلىك كۆزنىكى',
            'page.transactions.title' => 'ئۆتكۈزۈش ئاقىمى',
            'page.opening.title' => 'دەسلەپكى سانلارنى تەڭشەش',
            'page.dictionary.title' => 'كۆپ تىللىق لۇغەت',
            'page.dictionary.help' => 'سول تەرەپتىكى تىزىملىك ھەمىشە ساقلىنىدۇ؛ بۇ بەتتىن چىقىش ئۈچۈن سول تىزىملىك، ئۈستىدىكى «ئالتۇن-كۈمۈش زىبۇزىننەت دەپتىرى» ياكى ئوڭ تەرەپتىكى «ئومۇمىي كۆرۈنۈشكە قايتىش» نى چېكىڭ.',
            'page.dictionary.new_language' => 'يېڭى تىل قوشۇش',
            'page.dictionary.translation' => 'تەرجىمە باشقۇرۇش',
            'page.dictionary.current' => 'نۆۋەتتىكى لۇغەت',
            'page.dictionary.language_code_placeholder' => 'تىل كودى، مەسىلەن ug-CN',
            'page.dictionary.language_name_placeholder' => 'تىل نامى',
            'page.dictionary.translation_key_placeholder' => 'لۇغەت key',
            'page.dictionary.translation_value_placeholder' => 'تەرجىمە مەزمۇنى',
            'language.zh-CN' => 'ئاددىيلاشتۇرۇلغان خەنزۇچە',
            'language.ug-CN' => 'جۇڭگو شىنجاڭ ئۇيغۇر تىلى',
            'language.test-LANG' => 'سىناق تىل',
            'business_type.sale' => 'سېتىش',
            'business_type.recycle' => 'قايتۇرۇپ سېتىۋېلىش',
            'business_type.income' => 'كىرىم',
            'business_type.operating_expense' => 'دۇكان خىراجىتى',
            'payment_account.cash' => 'نەق پۇل',
            'payment_account.online' => 'تور',
            'payment_account.pure_gold_fund' => 'ساپ ئالتۇن قايتۇرۇش مەبلىغى',
            'online_method.bank' => 'بانكا',
            'online_method.wechat' => 'ۋېيشىن',
            'online_method.alipay' => 'ئەلىپاي',
            'stock_bucket.sale_stock' => 'سېتىش ئامبىرى',
            'stock_bucket.scrap_stock' => 'كونا ماتېرىيال ئامبىرى',
            'product_type.pure_gold' => 'ساپ ئالتۇن',
            'product_type.pure_silver' => 'ساپ كۈمۈش',
            'product_type.gold_wrapped' => 'ئالتۇن قاپ',
            'wrap_material.silver' => 'كۈمۈش',
            'wrap_material.copper' => 'مىس',
            'product.gold_wrapped_silver' => 'ئالتۇن قاپ كۈمۈش',
            'product.gold_wrapped_copper' => 'ئالتۇن قاپ مىس',
            'expense_category.rent' => 'ئىجارە',
            'expense_category.electricity' => 'توك پۇلى',
            'expense_category.water' => 'سۇ پۇلى',
            'expense_category.salary' => 'ئىش ھەققى',
            'expense_category.supplies' => 'ئىستېمال بۇيۇملىرى',
            'expense_category.other' => 'باشقا',
        ];

        foreach ($uyghurTranslations as $key => $value) {
            Translation::query()->updateOrCreate(
                ['language_code' => 'ug-CN', 'translation_key' => $key],
                ['translation_value' => $value],
            );
        }

        foreach (FinanceStats::BALANCE_KEYS + FinanceStats::STOCK_KEYS as $key => $value) {
            OpeningBalance::query()->updateOrCreate(
                ['store_id' => $store->id, 'scope' => 'store', 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
