<?php

declare(strict_types=1);

/* 我的账户——登录者本人的设置。刻意与 settings 语言文件分开：那一份属于应用设置，
   配置的是这家门店，而不是这个人。 */

return [
    'title' => '我的账户',
    'intro' => '你自己的资料、偏好和安全设置。这里的任何改动都不会影响门店里的其他人。',

    'sections' => [
        'profile' => '我的资料',
        'preferences' => '我的偏好',
        'password' => '修改密码',
        'notifications' => '通知',
    ],

    'save' => '保存更改',
    'cancel' => '取消',
    'reset' => '恢复默认',
    'reset_confirm' => '把这些恢复为门店的默认值？',

    'profile' => [
        'title' => '我的资料',
        'intro' => '你在 StyleDesk 各处的显示方式，以及我们如何联系到你。',

        'photo_card' => '头像',
        'photo_hint' => 'JPG、PNG 或 WebP，不超过 5 MB。在你上传之前，使用你的姓名首字母。',
        'photo_upload' => '上传头像',
        'photo_replace' => '更换头像',
        'photo_remove' => '移除',
        'photo_saved' => '头像已更新。',
        'photo_removed' => '头像已移除。',
        'photo_too_large' => '这张图片超过 5 MB。',
        'photo_wrong_type' => '请选择 JPG、PNG 或 WebP 图片。',
        'photo_pending' => '预览——保存后才会生效。',

        'personal_card' => '个人信息',
        'first_name' => '名字',
        'last_name' => '姓氏',
        'display_name' => '显示名称',
        'display_name_hint' => '留空则使用你的姓名。',
        'display_name_placeholder' => '客户看到的你的名字',
        'job_title' => '职位',
        'job_title_placeholder' => '资深发型师',
        'phone' => '手机号码',
        'phone_hint' => '短信功能开启后，用于接收预约提醒。',
        'saved' => '资料已更新。',

        'email_card' => '电子邮箱',
        'email' => '电子邮箱',
        'email_locked_hint' => '你用来登录的地址。如果需要更改，请联系管理员。',
        'email_hint' => '你用这个地址登录。更改它需要你的密码，并从新地址确认。',
        'email_current_password' => '当前密码',
        'email_change_cta' => '更改',
        'email_new' => '新的电子邮箱',
        'email_change' => '更改电子邮箱',
        'email_subject' => '确认你新的 StyleDesk 电子邮箱',
        'email_pending_title' => '确认你的新电子邮箱',
        'email_pending_body' => '我们已经把链接发到 :email。在你确认新地址之前，当前地址仍然可用。',
        'email_pending' => '请到 :email 查收确认更改的链接。',
        'email_resend' => '重新发送链接',
        'email_resent' => '我们又发了一次确认链接。',
        'email_cancel' => '取消更改',
        'email_cancelled' => '邮箱更改已取消。',
        'email_changed' => '电子邮箱已更新。',
        'email_link_dead' => '这个确认链接已过期或已经用过了。请到"我的资料"重新申请一个。',
        'email_taken' => '这个电子邮箱已被使用。',
        'email_unchanged' => '这已经是你的电子邮箱了。',

        'staff_card' => '员工信息',
        'staff_intro' => '由管理员在员工管理中设定，这里仅供查看。',
        'role' => '角色',
        'no_role' => '未分配角色',
        'locations' => '分配的门店',
        'all_locations' => '全部门店',
        'no_location' => '未分配门店',
        'status' => '账号状态',
        'status_active' => '正常',
        'status_inactive' => '停用',
        'status_archived' => '已归档',
        'member_since' => '加入时间',
    ],

    'preferences' => [
        'title' => '我的偏好',
        'intro' => '这个应用对你来说是什么样子。在你在这里改动之前，每一项都跟随门店的默认值。',

        'language_card' => '语言',
        'language' => '主要语言',
        'language_hint' => '只改变你自己看到的界面。你的门店自己写下的内容——服务名称、客户备注——永远不会被翻译。',
        'language_default' => '使用门店的语言',

        'format_card' => '日期与时间',
        'date_format' => '日期格式',
        'time_format' => '时间格式',
        'timezone' => '时区',
        'timezone_hint' => '不设置则跟随门店的时区。',
        'first_day_of_week' => '一周从哪天开始',
        'use_business' => '使用门店的设置',

        'calendar_card' => '日历',
        'calendar_intro' => '日历页面对你来说以什么方式打开。',
        'calendar_view' => '默认视图',
        'calendar_views' => [
            'day' => '日',
            'week' => '周',
            'month' => '月',
        ],
        'show_weekends' => '显示周末',
        'show_cancelled' => '显示已取消的预约',
        'show_resource_color' => '显示资源颜色',
        'show_staff_color' => '显示员工颜色',

        'save' => '保存偏好',
        'saved' => '偏好已更新。',
        'reset_action' => '恢复默认',
        'reset_hint' => '清除你的个人选择，让每一项设置重新跟随门店。',
        'reset' => '偏好已恢复为门店的默认值。',
    ],

    'password' => [
        'title' => '修改密码',
        'intro' => '选一个你在别处没有用过的密码。',
        'card' => '你的密码',
        'hidden' => '你的密码已隐藏',
        'current' => '当前密码',
        'new' => '新密码',
        'confirm' => '确认新密码',
        'requirements' => '你的密码需要',
        'save' => '修改密码',
        'saved' => '密码已修改。',
        'current_wrong' => '这不是你当前的密码。',
        'same_as_current' => '请选一个与当前不同的密码。',
        'mismatch' => '两次输入的密码不一致。',
        'logout_others' => '退出所有其他设备的登录',
        'logout_others_hint' => '当前浏览器保持登录。你在其他任何地方的登录都需要用新密码重新进入。',
    ],

    'notifications' => [
        'title' => '通知',
        'intro' => '哪些消息会送到你这里，以及通过什么方式。安全提醒始终会发送。',
        'saved' => '通知偏好已更新。',
        'reset' => '通知偏好已恢复为默认值。',
        'save' => '保存通知偏好',
        'enable_all' => '全部开启',
        'disable_all' => '关闭可选通知',
        'bulk_hint' => '无论怎样，安全提醒都保持开启。',
        'always_on' => '始终开启',
        'coming_soon' => '即将推出',
        'not_supported' => '这条通知不支持该方式',

        'channels' => [
            'in_app' => '应用内',
            'email' => '邮件',
            'sms' => '短信',
            'push' => '推送',
        ],

        'groups' => [
            'appointments' => [
                'label' => '预约',
                'description' => '你日历上的预约发生了什么。',
            ],
            'clients' => [
                'label' => '客户',
                'description' => '你负责的客户有什么变动。',
            ],
            'team' => [
                'label' => '团队',
                'description' => '你的排班、你的角色，以及同事发给你的内容。',
            ],
            'resources' => [
                'label' => '资源',
                'description' => '你的工作所依赖的房间、椅位和设备。',
            ],
            'system' => [
                'label' => '安全与账号',
                'description' => '你如何得知自己的账号发生了什么。这些始终会发送。',
            ],
        ],

        'types' => [
            'booking.created' => '创建了新的预约',
            'booking.assigned' => '预约分配给了我',
            'booking.updated' => '预约有更新',
            'booking.rescheduled' => '预约改期了',
            'booking.cancelled' => '预约被取消',
            'booking.completed' => '预约已完成',
            'booking.no_show' => '预约被标记为爽约',
            'booking.reminder' => '预约提醒',

            'client.assigned' => '有新客户分配给我',
            'client.updated' => '客户资料有更新',
            'client.note_added' => '客户新增了备注',
            'client.file_uploaded' => '客户上传了文件',
            'client.mentioned' => '客户备注里提到了我',

            'team.invitation' => '员工邀请',
            'team.location_assigned' => '员工被分配到某家门店',
            'team.hours_changed' => '工作时间有变动',
            'team.schedule_changed' => '排班有变动',
            'team.mentioned' => '有人提到了我',
            'team.role_changed' => '角色或权限有变动',

            'resource.assigned' => '资源分配给了我',
            'resource.changed' => '资源有变动',
            'resource.unavailable' => '资源变为不可用',
            'resource.conflict' => '资源排期冲突',

            'security.alert' => '安全提醒',
            'security.password_changed' => '密码被修改',
            'security.email_changed' => '电子邮箱被更改',
            'security.new_login' => '检测到新的登录',
            'security.account_alert' => '账号提醒',
        ],
        /*
        | 每条一句，说的是消息什么时候到，而不是把标题换个说法重复一遍。正在决定要
        | 不要被打扰的人，需要的是触发条件，不是标题的同义词。
        */
        'types_hint' => [
            'booking.created' => '有人下了预约——线上、前台或电话都算。',
            'booking.assigned' => '某个预约挂到了你名下，或从同事那里转给了你。',
            'booking.updated' => '你某个预约的服务、价格或备注发生了变化。',
            'booking.rescheduled' => '你的某个预约换到了别的日期或时间。',
            'booking.cancelled' => '客户或同事取消了你的某个预约。',
            'booking.completed' => '你的某个预约已结账并标记为完成。',
            'booking.no_show' => '某位客户被记录为没有到店。',
            'booking.reminder' => '在你的某个预约即将开始前。',

            'client.assigned' => '某位客户被交给你负责。',
            'client.updated' => '有人修改了分配给你的某位客户的资料。',
            'client.note_added' => '你的某位客户被添加了备注。',
            'client.file_uploaded' => '你的某位客户被添加了照片或文档。',
            'client.mentioned' => '同事在某位客户的备注里写下了你的名字。',

            'team.invitation' => '有人被邀请加入门店，或接受了邀请。',
            'team.location_assigned' => '你被调到了另一家门店，或同事被调动了。',
            'team.hours_changed' => '你的常规工作时间被修改。',
            'team.schedule_changed' => '包含你班次的排班被发布或修改。',
            'team.mentioned' => '同事在 StyleDesk 的任何地方写下了你的名字。',
            'team.role_changed' => '你的角色变了，或你被允许做的事变了。',

            'resource.assigned' => '某个房间、椅位或设备挂到了你名下。',
            'resource.changed' => '你使用的某项资源被改名、移动，或修改了可用时间。',
            'resource.unavailable' => '你被安排使用的某项资源因维护或维修而关闭。',
            'resource.conflict' => '两个预约在同一时间需要同一项资源。',

            'security.alert' => '你的账号发生了我们认为你应该知道的事。',
            'security.password_changed' => '你的密码被修改，无论是你还是别人改的。',
            'security.email_changed' => '你用来登录的电子邮箱被更改。',
            'security.new_login' => '有人从我们没见过的设备或地点登录了你的账号。',
            'security.account_alert' => '你的账号被锁定、停用或以其他方式受限。',
        ],
    ],
];
