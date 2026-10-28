<?php

namespace Fayne\Menu\Database\Seeders;

use Fayne\CMS\Support\ContentLocale;
use Fayne\Menu\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DemoMenuSeeder extends Seeder
{
    public function run(): void
    {
        $english = ContentLocale::normalize('en');
        $chinese = ContentLocale::normalize('zh_CN') ?? ContentLocale::normalize('zh-CN');
        if ($english === null || $chinese === null) {
            throw ValidationException::withMessages(['locale' => __('filament-menu::commands.bilingual_locales_required')]);
        }

        $model = config('filament-menu.models.menu');
        (new $model)->getConnection()->transaction(function () use ($model, $english, $chinese): void {
            foreach ($this->menus() as $slug => [$names, $items]) {
                if ($model::where('slug', $slug)->exists()) {
                    continue;
                }
                $group = (string) Str::uuid();
                foreach ([$english, $chinese] as $language => $locale) {
                    $menu = $model::create([
                        'slug' => $slug, 'name' => $names[$language], 'locale' => $locale,
                        'translation_group_id' => $group, 'deep' => 3, 'is_active' => false, 'changed' => true,
                    ]);
                    $this->createItems($menu, $items, $language);
                }
            }
        });
    }

    private function createItems(Menu $menu, array $items, int $language, int|string|null $parent = null, int $level = 1): void
    {
        $parentKey = config('filament-menu.foreign_keys.parent_id', 'parent_id');
        foreach ($items as $weight => $item) {
            $record = $menu->menuItems()->create([
                'name' => $item[$language], 'url' => $item[2], 'locale' => $menu->locale,
                $parentKey => $parent, 'level' => $level, 'weight' => $weight,
                'target' => '_self', 'is_active' => true, 'content' => [],
            ]);
            $this->createItems($menu, $item[3] ?? [], $language, $record->getKey(), $level + 1);
        }
    }

    private function menus(): array
    {
        return [
            'demo-main-navigation' => [['Demo store main navigation', '演示商城主导航'], [
                ['Home', '首页', '/'],
                ['Categories', '商品分类', '/categories', [
                    ['Fashion', '服饰鞋包', '/categories/fashion', [
                        ['Women', '女装', '/categories/fashion/women'],
                        ['Men', '男装', '/categories/fashion/men'],
                    ]],
                    ['Electronics', '数码电器', '/categories/electronics', [
                        ['Phones', '手机', '/categories/electronics/phones'],
                        ['Computers', '电脑', '/categories/electronics/computers'],
                    ]],
                    ['Home & living', '家居生活', '/categories/home', [
                        ['Kitchen', '厨房用品', '/categories/home/kitchen'],
                        ['Decor', '家居装饰', '/categories/home/decor'],
                    ]],
                ]],
                ['New arrivals', '新品上市', '/new-arrivals'],
                ['Deals', '优惠促销', '/deals'],
                ['Brands', '品牌馆', '/brands'],
                ['My account', '我的账户', '/account', [
                    ['My orders', '我的订单', '/account/orders'],
                    ['Wishlist', '我的收藏', '/account/wishlist'],
                ]],
                ['Cart', '购物车', '/cart'],
            ]],
            'demo-footer-navigation' => [['Demo store footer navigation', '演示商城页脚导航'], [
                ['Shopping guide', '购物指南', '/help/shopping', [
                    ['How to order', '购物流程', '/help/ordering'],
                    ['Payment methods', '支付方式', '/help/payment'],
                    ['Shipping & delivery', '配送说明', '/help/shipping'],
                ]],
                ['Customer service', '客户服务', '/help', [
                    ['Contact us', '联系我们', '/contact'],
                    ['Returns & refunds', '退换货政策', '/returns'],
                    ['FAQ', '常见问题', '/faq'],
                ]],
                ['About the store', '关于商城', '/about', [
                    ['Our story', '品牌故事', '/about/story'],
                    ['Privacy policy', '隐私政策', '/privacy'],
                    ['Terms of service', '服务条款', '/terms'],
                ]],
            ]],
        ];
    }
}
