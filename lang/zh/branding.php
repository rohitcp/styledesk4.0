<?php

declare(strict_types=1);

/*
| 品牌样式模块：表单和它的预览。
|
| 预览面板是应用、预约页面、邮件和收据的示意图——所以里面的文字是示例内容，翻译
| 它们的理由和翻译屏幕其余部分一样：一位中文读者在看自己的品牌样式会产生什么效果
| 时，应该看得懂。
*/

return [
    'title' => '品牌样式',
    'intro' => '你的标志、网站图标和颜色，会用在 StyleDesk、你的预约页面、确认和提醒邮件、收据和发票上。',
    'saved' => '品牌设置已更新。',
    'save_failed' => '无法保存你的品牌设置，请重试。',
    'reset_done' => '品牌样式已恢复为 StyleDesk 的默认值。',
    'correct_fields' => '请修正高亮显示的字段后重试。',

    'reset' => '恢复默认品牌样式',
    'remove_confirm' => '移除这张图片？保存时会将其删除。',
    'reset_confirm' => '把品牌样式恢复为 StyleDesk 的默认值？你的标志、网站图标和颜色都会被移除。',

    'logo' => [
        'title' => '门店标志',
        'hint' => 'PNG、JPG、SVG 或 WEBP，不超过 2 MB。400×120 像素左右效果不错。透明背景会原样保留。',
        'upload' => '上传标志',
        'replace' => '更换标志',
    ],

    'favicon' => [
        'title' => '网站图标／应用图标',
        'hint' => 'PNG、SVG 或 ICO，不超过 2 MB。请使用正方形图片——512×512 像素最理想。',
        'upload' => '上传图标',
        'replace' => '更换图标',
    ],

    'upload' => [
        'uploading' => '正在上传…',
        'removed' => '已移除。保存后生效。',
        'failed' => '这个文件上传失败。',
        'mimes' => '请使用 :formats 文件。',
        'too_large' => '文件不得超过 2 MB。',
    ],

    'colours' => [
        'title' => '品牌颜色',
        'hint' => '选一个颜色，或直接输入十六进制值。悬停时的深浅和按钮上文字的颜色，都会由此推算出来。',
        'primary' => '主色',
        'primary_hint' => '按钮、链接和应用栏。',
        'secondary' => '辅助色',
        'secondary_hint' => '起衬托作用的高亮。',
        'accent' => '强调色',
        'accent_hint' => '标记和小范围强调。',
        'picker_label' => ':name 颜色选择器',
        'invalid' => '请输入十六进制颜色，例如 #3d348b。',
        'contrast' => '主色上的白色文字：',
        'contrast_warning' => '你的应用栏、预约页面页头和邮件页头会在这个颜色上印白色文字，而在这个深浅下很难辨认。换一个更深的颜色通常就能解决。',
        'required_primary' => '请选择一个主色。',
        'required_secondary' => '请选择一个辅助色。',
        'required_accent' => '请选择一个强调色。',
    ],

    /*
     * WCAG 等级。保留标准写法："AA"是一个符合性等级的名称，不是一个英文单词，
     * 设计师在规范里查找它时，找的就是这两个字母。
     */
    'grades' => [
        'aaa' => 'AAA',
        'aa' => 'AA',
        'large' => '仅适用于大号文字',
        'fails' => '不通过',
    ],

    'preview' => [
        'title' => '预览',
        'hint' => '会随着你在上面调整颜色而更新。',
        'trial' => '免费试用还剩 0 天',
        'in_app' => '在 StyleDesk 中',
        'booking_page' => '你的预约页面',
        'emails' => '确认、提醒和邀请邮件',
        'receipts' => '收据和发票',

        'your_logo' => '你的标志',
        'book_appointment' => '预约',
        'confirmed' => '已确认',
        'new' => '新',
        'link_sentence' => '链接看起来像:link。',
        'this_one' => '这个',

        'book_online' => '随时在线预约',
        'select' => '选择',
        'continue' => '继续',
        'minutes' => ':count 分钟',

        'email_subject' => '你的预约已确认',
        'email_body' => '9 月 4 日星期四下午 2:00，由 Priya 在 :business 为你服务。',
        'view_appointment' => '查看预约',
        'email_footer' => '由 :business 通过 StyleDesk 发送',

        'receipt' => '收据',
        'tax' => '税费',
        'total' => '合计',

        'where_used' => '这些用在哪里',
        'where_used_body' => 'StyleDesk 应用、你的预约页面、预约确认、提醒、团队邀请、收据、发票、礼品卡和客户通知。',
        'representations' => '上面这些面板是示意图，不是模板本身。',
    ],
];
