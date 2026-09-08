<?php

declare(strict_types=1);

/*
| 图标导航栏、抽屉菜单和账户菜单。
|
| 这是一层翻译，而不是重新命名：值若与英文含义不同，就等于借着新增语言的名义
| 改动了产品文案。
|
| 键名与 config/navigation.php 中的 `key` 一一对应——Nav::label() 解析
| navigation.<key>，因此两者不会各说各话。
*/

return [
    'bookings' => '预约',
    'menu_for' => ':name 菜单',
    'dashboard' => '仪表板',
    'calendar' => '日历',
    'clients' => '客户',
    'services' => '服务与资源',
    'staff' => '员工',
    'sales' => '销售',
    'marketing' => '营销',
    'reports' => '报表',
    'mentions' => '提及',
    'activity' => '动态',
    'design-system' => '设计系统',
    'team' => '团队',
    'app_settings' => '应用设置',
    'main_menu' => '主菜单',
    'search_placeholder' => '输入以搜索和查看最近项目…',
    'my_profile' => '我的资料',
    'sign_out' => '退出登录',
    'invite_team_members' => '邀请团队成员',
    'add' => '添加',

    'active_staff' => '{0} 没有在职员工|[1,*] :count 名在职员工',
    'coming_soon' => '即将推出',

    /*
    | 导航栏顶级项目下的下拉条目。
    |
    | 每个条目在 config/navigation.php 中都带有 `key`，因此 Nav::label() 能在此
    | 解析——包括那些页面尚未构建的条目。它们就在菜单里、读者看得见，所以菜单
    | 只翻译一半同样是缺陷，与链接是否有去处无关。
    */
    'all_bookings' => '全部预约',
    'booking_leads' => '预约意向',
    'add_booking' => '+ 添加预约',
    'add_walkin' => '+ 添加到店客户',
    'all_clients' => '全部客户',
    'add_client' => '添加客户',
    'coupons_offers' => '优惠券与促销',
    'all_services' => '全部服务',
    'add_service' => '添加服务',
    'all_resources' => '全部资源',
    'add_resource' => '添加资源',
    'resource_availability' => '资源可用情况',
    'resource_utilization' => '资源使用率',
    'all_staff' => '全部员工',
    'staff_schedule' => '员工排班',
    'shifts' => '班次',
    'staff_utilization' => '员工使用率',
    'add_staff' => '+ 添加员工',
    'email_marketing' => '邮件营销',
    'sms_marketing' => '短信营销',
    'social_marketing' => '社交媒体营销',
    'review_marketing' => '谷歌评价营销',
    'gift_cards' => '礼品卡',
    'loyalty' => '会员积分',
    'groups' => '分组',
    'forms_waivers' => '表单与同意书',
    'memberships_packages' => '会员与套餐',

    /* 打开的菜单中，每组条目上方的标题。 */
    'sections' => [
        'management' => '管理',
        'quick_actions' => '快捷操作',
        'services' => '服务',
        'resources' => '资源',
        'channels' => '渠道',
    ],

    /* 无障碍名称，当它与旁边的标签不同时使用。 */
    'aria' => [
        'services' => '服务与资源',
    ],

    /* 仅屏幕阅读器会读到的标题。 */
    'drawer_main' => '主导航',
    'rail_primary' => '主导航',
];
