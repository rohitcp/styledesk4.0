<?php

declare(strict_types=1);

/*
| 全应用通用的词。凡是出现在多个界面上的内容都放在这里，而不是放进某个界面
| 自己的文件，这样“保存”只翻译一次，也就不会出现两种写法。
*/

return [
    'retry' => '重试',
    'save' => '保存',
    'save_changes' => '保存更改',
    'done' => '完成',
    'cancel' => '取消',
    'edit' => '编辑',
    'delete' => '删除',
    'remove' => '移除',
    'add' => '添加',
    'custom_color' => '自定义颜色',
    'back' => '返回',
    'close' => '关闭',
    'search' => '搜索',
    'filter' => '筛选',
    'clear' => '清除',
    'clear_all' => '全部清除',
    'apply' => '应用',
    'show_more' => '显示更多',
    'show_less' => '收起',
    'view' => '查看',
    'yes' => '是',
    'no' => '否',
    'optional' => '（选填）',
    'not_set' => '未设置',
    'on' => '开',
    'off' => '关',
    'none' => '无',
    'active' => '启用',
    'inactive' => '停用',
    'saving' => '保存中…',
    'loading' => '加载中…',

    /**
     * 共用的日历选择器（x-date-field）。
     *
     * 这里不列月份和星期名称——Carbon 已经知道应用支持的每种语言的写法，
     * 手工维护十二个名字只会多出一处可能对不上的地方。
     */
    'choose_a_date' => '选择日期',
    'today' => '今天',
    'month' => '月',
    'year' => '年',
    'previous_month' => '上个月',
    'next_month' => '下个月',
    'language' => '语言',
    'coming_soon' => '即将推出',
    'setup_required' => '需要设置',
    'view_only' => '仅查看',

    /*
     * 共用的图片上传组件。目前用于员工头像，将来任何图片字段也会用到，
     * 因此它的文案放在这里而不是某个模块中。
     */
    'upload' => [
        'choose' => '选择图片',
        'too_large' => '该图片超过 2 MB。',
        'failed' => '该图片上传失败，请重试。',
    ],
    /** 某人是否同意被联系。由 <x-consent-status> 渲染。 */
    'consent' => [
        'opted_in' => '已同意',
        'opted_out' => '已拒绝',
    ],
    /** 从记录中移除任何内容之前询问。 */
    'confirm' => [
        'deactivate' => '停用',
        'activate' => '启用',
        'remove_tag_title' => '移除客户标签？',
        'remove_tag' => '从该客户移除“:label”？',
        'remove_behavioral_title' => '移除行为标签？',
        'remove_behavioral' => '从该客户移除“:label”？',
    ],

    /*
    | 表单填写过程中浏览器给出的实时校验提示。措辞与服务器拒绝同一个值时
    | 所说的一致，这样提交前后看到的说法完全相同。:field 是字段自己的标签。
    */
    'validation' => [
        'required' => '请填写:field。',
        'email' => '请输入有效的邮箱地址。',
        'url' => '请输入有效的网址，以 https:// 开头',
        'phone' => '请输入有效的电话号码。',
        'date' => '请输入有效的日期。',
        'numeric' => ':field必须是数字。',
        'integer' => ':field必须是整数。',
        'min' => ':field至少需要 :min 个字符。',
        'max' => ':field不能超过 :max 个字符。',
        'min_value' => ':field必须大于或等于 :min。',
        'max_value' => ':field必须小于或等于 :max。',
        'taken' => '该值已被使用。',
    ],
    'type_a_time' => '输入时间，例如 下午 2:30',

    'stale_assets' => '此页面已过期，部分功能将无法使用。',

    /*
    | 每个页面框架都会绘制的内容：应用栏上方的通知条、下方的页脚，
    | 以及页面资源过期时出现的提示条。
    */
    'reload' => '重新加载',
    'legal' => '法律信息',
    'terms' => '条款',
    'privacy' => '隐私',
    'support' => '支持',
    'all_rights_reserved' => '© :year StyleDesk。保留所有权利。',

    'banner' => [
        'watch_now' => '立即观看：StyleDesk 入门指南',
        /* 使用 trans_choice 而非 Str::plural()：后者只懂英文。中文没有
           复数变化，因此各分支写法相同，但整句仍由语言文件决定。 */
        'trial_remaining' => '{0} 你的免费试用今天到期|[1,*] 免费试用还剩 :count 天',
        'trial_ending' => '你的试用即将到期',
        'subscribe' => '立即订阅',
    ],

    'session' => [
        'expiring' => '你的会话即将过期',
        'expired' => '你的会话已过期',
    ],
];
