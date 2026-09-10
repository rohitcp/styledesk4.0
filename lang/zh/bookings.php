<?php

declare(strict_types=1);

/*
| 预约。
|
| 措辞把两件容易混为一谈的东西分开：预约备注是关于这一次预约的，客户备注是关于
| 这个人的。前者当天读一次；后者只要对方还是客户，每个为他下单的人都会读到。
*/

return [

    'title' => '预约',
    'search_placeholder' => '搜索预约、客户、预约编号…',
    'intro' => '已接下的每一个预约，以及由谁来做。',
    'new' => '新建预约',
    'new_intro' => '找到客户，或直接接待到店客人。',
    'walk_in_intro' => '按前台当下问到的信息填写。',

    'add' => [
        'client' => '添加客户',
        'booking' => '添加预约',
        'walk_in' => '添加预约 · 到店客人',
        'leave' => '添加休假',
    ],

    'modes' => [
        'booking' => '预约',
        'walkin' => '到店客人',
    ],

    'any_staff' => '任意有空的人',
    'walk_in_guest' => '到店客人',

    'columns' => [
        'arrival' => '到店情况',
        'checkin' => '签到',
        'location' => '门店',
        'reference' => '预约编号',
        'booked_by' => '下单人',
        'client' => '客户',
        'date' => '日期',
        'time' => '时间',
        'services' => '服务',
        'staff' => '服务人员',
        'total' => '合计',
        'status' => '状态',
    ],

    'filters' => [
        'all_statuses' => '全部状态',
        'all_locations' => '全部门店',
        'all_services' => '全部服务',
        'all_payments' => '全部付款状态',
        'all_staff' => '全部团队成员',
        'date' => '日期',
        'reset' => '重置',
    ],

    'statuses' => [
        'pending' => ['label' => '待确认'],
        'draft' => ['label' => '草稿'],
        'confirmed' => ['label' => '已确认'],
        'arrived' => ['label' => '已签到'],
        'completed' => ['label' => '已完成'],
        'no-show' => ['label' => '爽约'],
        'declined' => ['label' => '已拒绝'],
        'cancelled' => ['label' => '已取消'],
    ],

    'sources' => [
        'front-desk' => '前台',
        'phone' => '电话',
        'online' => '线上',
        'walk-in' => '到店',
        'social' => '社交媒体',
        'referral' => '转介绍',
        'other' => '其他',
    ],

    /*
    | 预约页面要做的五个决定，按人们通常说出口的顺序排列，而不是按数据库想要的顺序。
    */
    'sections' => [
        'purchase' => '选择类型',
        'client' => '客户',
        'service' => '服务',
        'when' => '员工与时间',
        'details' => '预约详情',
        'payment' => '定金／付款',
        'comms' => '沟通',
        'summary' => '预约摘要',
    ],

    'purchase' => [
        'title' => '选择类型',
        'question' => '你想销售什么？',
        'services' => '服务',
        'services_hint' => '一次预约：服务、员工、时间和账单。',
        'membership' => '会员',
        'membership_hint' => '周期性会员或服务套餐。',
        'gift_card' => '礼品卡',
        'gift_card_hint' => '客户可稍后消费的储值。',
        'coming_soon' => '即将推出',
        'membership_off' => '请先在应用设置中启用会员。',
        'membership_empty' => '请先在「客户 → 会员」中发布一份会员方案。',
    ],

    'membership' => [
        'select' => '选择会员',
        'plans' => '会员方案',
        'packages' => '会员套餐',
        'none' => '目前没有已发布可销售的会员。',
        'none_hint' => '在「客户 → 会员」中发布一份会员方案后，会显示在这里。',
        'includes' => '包含',
        'select_this' => '选择此会员',
        'selected' => '已选择',
        'change' => '更换',
        'saving' => '客户可省 :amount',
        'trial' => ':days 天试用',
        'joining_fee' => '入会费 :amount',
        'setup_fee' => '开通费 :amount',
        'start' => '开始日期',
        'start_today' => '今天开始',
        'start_later' => '选择开始日期',
        'start_locked' => '会员在售出当天开始生效。',
        'scheduled_note' => '该会员在 :date 之前为「已排期」状态。',
        'purchase_summary' => '购买摘要',
        'billing' => '扣款周期',
        'next_billing' => '下次扣款',
        'one_off' => '一次性购买',
        'due_today' => '今日应付',
        'client_required' => '请先选择客户——会员必须归属于某位客户。',
        'plan_required' => '请选择一份会员。',
        'method_note' => '周期性会员需要一种可再次扣款的付款方式。',
        'complete' => '完成购买',
    ],

    'credits' => [
        'available' => '有可用的会员权益',
        'from' => '来自 :name',
        'remaining' => '可用 :count 次',
        'apply' => '使用会员次数',
        'apply_many' => '使用会员 · :count 个额度',
        'applied_many' => '会员 · 已使用 :count 个额度',
        'applied' => '已使用会员次数',
        'remove' => '移除',
        'line' => '会员次数抵扣',
        'covered' => '由会员承担',
        'used_line' => '已用额度',
        'benefits_section' => '会员权益',
        'col_service' => '服务',
        'col_included' => '包含',
        'col_used' => '已用',
        'col_remaining' => '剩余',
        'covered_by' => '由 :name 承担 · 剩余 :count',
        'used_up' => '额度已用完，可按原价预约。',
        'each' => '每次 :count 个额度',
        'renews' => ':date 更新',
    ],

    'cards' => [
        'recurring' => '周期性扣款',
        'recurring_hint' => '在取消之前将自动续费。',
        'recurring_locked' => '该会员以订阅方式销售，始终自动续费。',
        'renews' => '续费 :price',
        'one_off' => '每次续费在前台收取',
        'title' => '存档银行卡',
        'why' => '自动续费需要绑定银行卡。',
        'existing' => '使用已有银行卡',
        'add' => '+ 添加银行卡',
        'default' => '默认',
        'expires' => ':date 到期',
        'expired' => '已过期',
        'expiring' => '本月到期',
        'save' => '保存该银行卡',
        'save_required' => '自动续费需要绑定银行卡。',
        'none' => '该客户没有存档的银行卡。',
        'unavailable' => '暂时无法保存银行卡',
        'unavailable_hint' => '请在「应用设置 → 付款」中连接支付服务商，以便保存银行卡用于自动续费。',
        'client_first' => '请先选择客户再添加银行卡。',
        'required' => '请为续费选择一张银行卡，或关闭周期性扣款。',
        'adding' => '正在添加银行卡…',
        'failed' => '该银行卡保存失败。',
        'cancel' => '取消',
        'summary_recurring' => '周期性',
        'summary_payment_method' => '付款方式',
        'summary_next_billing' => '下次扣款',
        'yes' => '是',
        'no' => '否',
    ],

    'client' => [
        'search' => '按姓名、电话或邮箱搜索…',
        'search_label' => '按姓名、电话或邮箱搜索客户',
        'or' => '或',
        'add' => '添加客户',
        'guest' => '按匿名到店客人预约',
        'none' => '没有符合的客户。',
        'change' => '更换',
        'guest_name' => '姓名',
        'guest_phone' => '手机',
        'guest_email' => '邮箱',
        'guest_hint' => '到店客人不建客户记录就能下单。如果对方还会再来，请改为添加客户。',
        'guest_save' => '保存到店客人信息',
        'guest_checking' => '正在检查…',
        'guest_saved' => '已保存',
        'guest_known' => '这可能已经是一位客户了。',
        'guest_known_hint' => '改用他们的记录，预约就能接上他们的历史。如果是另一个人，就继续按到店客人处理。',
    ],

    'service' => [
        'search' => '搜索服务…',
        'category' => '服务分类',
        'all_categories' => '全部分类',
        'search_categories' => '搜索分类…',
        'chosen' => '已选',
        'none' => '没有符合的服务。',
        'empty' => '还没有服务。先添加一项，预约页面就会提供它。',
        'minutes' => ':count 分钟',
        'remove' => '移除 :name',

        /*
        | 整页的选择器。
        |
        | 有一百项服务的门店，没法在表单里的一个下拉框中挑选，所以挑选本身就是一个
        | 页面：一侧是分类，另一侧是服务，底部只有一个保存，而不是每选一项就提交一次。
        */
        'add' => '添加／指定服务',
        'change' => '添加或更改服务',
        'card_empty' => '还没有选择服务。',
        'select_title' => '选择服务',
        'close' => '返回预约',
        'categories' => '服务分类',
        'all_services' => '全部服务',
        'client_favorites' => '客户常做的服务',
        'selected' => '已选 :count 项',
        'selected_one' => '已选 1 项',
        'selected_none' => '未选择任何内容',
        'save_close' => '保存并关闭',
        'nothing_here' => '这个分类里没有内容。',
        'hours' => ':count 小时',
        'hours_minutes' => ':hours 小时 :minutes 分钟',
        'count' => ':count 项服务',
        'count_one' => '1 项服务',
        'needs' => '需要 :names',
    ],

    'when' => [
        'none_in_period' => '这个时段没有空档。',
        'change_location' => '更换门店',
        'search_locations' => '搜索门店…',
        'no_locations' => '没有符合该搜索的门店。',
        'closed_date' => '没有可预约的时间——这家门店当天不营业。',
        'too_long' => '这些服务放不进这家门店当天的营业时间里。',
        'nothing_free' => '这一天没有空档——时间已被占满，或者没有人当班。',
        'loading_times' => '正在查看有哪些空档…',
        'staff' => '团队成员',
        'any' => '任意有空的人',
        'date' => '日期',
        'today' => '今天',
        'tomorrow' => '明天',
        'next_3' => '未来 3 天',
        'next_7' => '未来 7 天',
        'custom' => '自定义日期',
        'done' => '完成',
        'previous_month' => '上个月',
        'next_month' => '下个月',
        'time' => '开始时间',
        'ends' => ':time 结束',
        'location' => '门店',
        'morning' => '上午',
        'afternoon' => '下午',
        'evening' => '晚上',
    ],

    'details' => [
        'source' => '预约来源',
        'note' => '预约备注',
        'note_hint' => '只针对这一次预约。它永远不会变成客户的长期备注。',
        'note_placeholder' => '客户想剪成同样的发型，但今天再短一点。',
        'client_note' => '客户备注',
        'client_note_aside' => '——保存在客户档案上',
        'client_note_hint' => '预约确认时保存到客户身上。之后为他下单的每个人都会看到。',
        'client_note_placeholder' => '头皮敏感——不要把热风直接对着发根。',
        'choose_source' => '选择一个来源',
        'search_sources' => '搜索来源…',
    ],

    'duplicate' => [
        'title' => '可能重复的预约',
        'title_exact' => '这看起来是同一个预约下了两次',
        'message' => ':client 在 :date 已经预约了 :service。',
        'message_overlap' => '这与该客户同一项服务的现有预约在时间上重叠。',
        'message_exact' => '这看起来与现有预约完全重复——同一客户、同一服务、同一门店、同一时间。',
        'existing' => '已有预约',
        'view' => '查看已有预约',
        'ask' => '客户确实可能在一天里想做同一项服务两次。如果情况就是这样，请继续。',
        'continue' => '仍然继续',
        'create_anyway' => '仍然创建',
        'cancel' => '取消这个预约',
        'discard_title' => '取消这个预约？',
        'discard_body' => '这会删掉正在录入的这个预约，以及它一路自动保存下来的那条预约意向。',
        'discard_keeps' => '这位客户已有的那个预约不受影响。',
        'keep' => '保留预约',
        'discard_confirm' => '取消并删除',
        'blocked' => '这位客户当天已经预约了其中一项服务。接单前请先查看预约页面上的提示。',
    ],

    'payment' => [
        'deposit_percent' => '定金',
        'deposit_now' => '现在应付定金',
        'deposit_now_percent' => '现在应付 :percent% 定金',
        'remaining' => '剩余应付',
        'type' => '付款方式',
        'none' => '现在不收款',
        'none_hint' => '预约过程中不收取任何款项。',
        'deposit' => '收取定金',
        'deposit_hint' => '现在先收取预约总额的一部分。',
        'full' => '全额付款',
        'full_hint' => '现在收取预约的全部金额。',
        'amount' => '定金金额',
        /* 前台说定金的两种方式：政策是用百分比写的，实际敲进去的是数字。 */
        'preset' => ':percent%',
        'preset_custom' => '自定义',
        'too_much' => '定金不能超过预约总额。',
        /* 服务本身强制要求的定金：这不是在问前台收多少，而是在告诉前台。 */
        'too_little' => '这些服务的定金至少要 :amount。',
        'deposit_required' => '需要定金',
        'deposit_required_error' => '这些服务需要定金，所以一分钱不收是无法完成预约的。',
        'deposit_required_hint' => '这些服务要求定金，因此必须收取才能完成这个预约。金额可以往上调，但不能取消。',
        'deposit_required_percent' => '预约总额的 :percent%',
        'balance' => '应付余额',
        'collecting' => '现在收取',
        'nothing_collected' => '这个预约不会收取任何款项。',
        'action' => '收款方式',
        'choose_action' => '选择如何收取',
        'search_actions' => '搜索…',
        'action_hint' => '只有在确实有款可收时才会问。',
        'actions' => [
            'collect-now' => '立即收款',
            'desk' => '在前台收取',
            'link' => '发送付款链接',
            'later' => '到店时再问',
            'waive' => '已免除',
        ],
        'action_hints' => [
            'collect-now' => '在完成预约之前，就在这里扣款。',
            'desk' => '由前台当面收取。在收到之前余额仍然挂账。',
            'link' => '客户自己找时间付。预约接下后通过邮件发送。',
            'later' => '客户到店时收取。余额仍然挂账。',
            'waive' => '有意不收取任何款项。会记在你的名下。',
        ],
        'waiver_reason' => '为什么免除',
        'waiver_placeholder' => '一位老客户，上次染色出了问题。',
        'waiver_hint' => '会连同你的名字和日期一起保存，以便日后可以交代这个决定。',
        'waiver_needed' => '请说明为什么免除这笔款项。',
        'no_waive_permission' => '你没有免除款项的权限。请找管理者。',
        'collect_now_hint' => '预约一接下，收款卡片就会打开。',
        'link_sent' => '付款链接已发送至 :to。',
        'link_status' => '付款链接',
        'link_statuses' => [
            'sent' => '已发送',
            'opened' => '已打开',
            'paid' => '已支付',
            'expired' => '已过期',
        ],
        'link_no_email' => '这位客户没有电子邮箱，链接没有地方可发。',
    ],

    'comms' => [
        'send' => '发送确认信息',
        'both' => '短信 + 邮件',
        'sms' => '短信',
        'email' => '邮件',
        'none' => '都不发',
        'to_sms' => '短信发至',
        'to_email' => '邮件发至',
        'missing' => '档案里没有号码也没有邮箱，因此无法发送。',
    ],

    'summary' => [
        'client' => '客户',
        'services' => '服务',
        'staff' => '服务人员',
        'when' => '时间',
        'duration' => '时长',
        'total' => '合计',
        'deposit' => '定金',
        'due' => '当天应付',
        'nothing' => '还没有选择任何内容。',
        'minutes' => '[1,*] :count 分钟',
        'reference' => '预约编号',
        'location' => '门店',
        'resource' => '资源',
        'starts' => '开始',
        'ends' => '预计结束',
        'subtotal' => '小计',
        'discount' => '折扣',
        'coupon' => '优惠券',
        'tip_paid' => '已付小费',
        'due_now' => '应付余额',
        'additional_tip' => '追加小费',
        'collect_today' => '今日收款',
        'tax' => '税费（:rate）',
        'tax_included' => '含税（:rate）',
        'paid' => '已付',
        'estimate' => '价格会在预约接下时确定。',
        'status' => '状态',
        'date' => '日期',
        'source' => '预约来源',
    ],

    /*
    | 选定客户后，在预约旁边展开的面板。
    |
    | 两类事实并排放在一起，措辞刻意区分："指名要求"是客户自己说的，"最常预约"是
    | 日程表看出来的。把两者混为一谈的面板，会让前台把软件的推测当成客户的原话转述
    | 回去。
    */
    'new_client' => [
        'title' => '添加客户',
        'intro' => '只填够接下这个预约的信息。其余的稍后可以在他们的档案里补。',
        'first_name' => '名字',
        'last_name' => '姓氏',
        'email' => '电子邮箱',
        'mobile' => '手机号码',
        'contact_hint' => '手机或邮箱——两者之一，确认信息才有地方可发。',
        'needs_contact' => '手机或邮箱——确认信息需要一个去处。',
        'duplicate' => '这可能已经是一位客户了。',
        'add' => '添加客户',
        'add_anyway' => '仍然添加',
        'full_form' => '完整客户表单',
    ],

    'context' => [
        'preferred' => '偏好员工',
        'also_seen' => '也找过',
        'last' => '上次预约',
        'again' => '再约一次相同内容',
        'recent' => '最近到访',
        'average' => '平均评分 :rating',
        'preferences' => '预约偏好',
        'none' => '还没有历史记录——这是他们的第一次预约。',
        'visits' => '{0} 没有过往到访|[1,*] 过往到访 :count 次',
        'from_diary' => '来自预约历史',
        'kinds' => [
            'asked_for' => '指名要求',
            'most_booked' => '最常预约',
            'also_seen' => '以前找过',
        ],
        'cadence' => '[1,*] 大约每 :count 周预约一次',
        'windows' => [
            'morning' => '偏好上午的预约',
            'afternoon' => '偏好下午的预约',
            'evening' => '偏好晚上的预约',
        ],
        'walk_in' => '到店客人',
        'walk_in_hint' => '除这次预约外不会留下任何记录。',
        'remove' => '把这位客户从预约中移除',
    ],

    'confirm' => '确认预约',
    'draft' => '存为草稿',
    'cancel' => '取消',

    /*
    | 还缺什么，一次只说一件。把所有没答的问题列成一张单子是一堵墙；
    | 只说下一件才是一条指令。
    */
    'lead' => [
        'created' => '已创建预约编号 :reference。目前无需任何操作。',
    ],

    /*
    | 预约在填写过程中自动保存自己。
    |
    | 说得很轻，并且挨着编号，因为这不是新闻：前台正在和人说话，一个每隔几秒就宣告
    | 自己的保存提示，会成为屏幕上最吵的东西。
    */
    'autosave' => [
        'reference' => '预约编号：:reference',
        'saving' => '正在保存…',
        'saved' => '已保存',
        'failed' => '未保存',
        'draft' => '草稿',
    ],

    'steps' => [
        'save' => '保存并继续',
        'edit' => '编辑',
    ],

    'blockers' => [
        'client' => '选择一位客户，或按到店客人处理。',
        'guest' => '给这位到店客人填个名字。',
        'service' => '至少选择一项服务。',
        'time' => '选择一个开始时间。',
        'ready' => '可以下单了。',
    ],

    'booked' => '已为 :name 确认预约。',
    'no_time_yet' => '尚未选时间',
    'saved_draft' => '预约已存为草稿。',

    'empty' => '没有符合这些筛选条件的预约。',
    'empty_hint' => '清除筛选条件即可看到全部预约。',
    'none_yet' => '还没有预约',
    'none_yet_hint' => '接下第一单，它就会出现在这里。',

    'showing' => '显示第 :from–:to 个预约，共 :total 个',
    'results' => [
        'zero' => '没有预约',
        'one' => '1 个预约',
        'many' => ':count 个预约',
        'clear' => '清除筛选',
    ],
    'actions_for' => '对 :name 的操作',

    /*
    | 预约上的钱。
    |
    | 门店收到的绝大多数款项都发生在别处——钱箱里、收银台旁的刷卡机上、某人的银行
    | App 里——所以措辞讲的是记下发生了什么，而不是向谁扣款。"标记为已付"是人说话
    | 的方式，这里就这么说。
    */
    /*
    | 预约自己的详情页。
    |
    | 阅读方式和客户档案一样：人在左边，事在中间，要对他们做什么在右边。
    */
    /*
    | 预约接下之后还能对它做什么。
    |
    | 四种操作，每一种都问同样两个问题：为什么，还有没有别的。原因本身属于这家门店
    | ——在应用设置 → 原因里设定——所以这里不点名任何一条。
    */
    /*
    | 前台实际工作的几个视图。
    |
    | 不是保存下来的搜索：每一个都是前台在早九晚六之间真的会问的问题，这也是页面
    | 打开时停在"今天"的原因。
    */
    'tabs' => [
        'today' => '今天',
        'next-3' => '未来 3 天',
        'month' => '本月',
        'check-in' => '待签到',
        'more' => '更多',
        'all' => '全部预约',
        'completed' => '已完成',
        'cancelled' => '已取消',
        'no-shows' => '爽约',
        'declined' => '已拒绝',

        'summary' => [
            'total' => '今天的预约',
            'checked_in' => '已签到',
            'pending' => '待签到',
            'completed' => '已完成',
            'no_show' => '爽约',
        ],

        /* 他们走到哪一步了，以及——在还没到之前——离约定时间差多少。"迟到 12 分钟"
           是前台会据此行动的信息；只给一个约定时间，就得有人看着钟自己算，一个上午
           算四十次。 */
        'arrival' => [
            'checked_in' => '已签到',
            'waiting' => '尚未到店',
            'not_due' => '今天没有安排',
            'done' => '已接待',
            'absent' => '没有到店',
            'due' => '现在该到了',
            'later' => '今天晚些时候',
            'early' => '早到 :count 分钟',
            'late' => '迟到 :count 分钟',
        ],

        'previous_month' => '上个月',
        'next_month' => '下个月',
        'empty' => [
            'today' => '今天没有预约。',
            'next-3' => '未来三天没有预约。',
            'check-in' => '没有人在等着签到。',
        ],
    ],

    'resources' => [
        'none_available' => '所选时间没有可用于这项服务的资源。',
        'auto' => '自动分配',
        'manual' => '手动选择',
        'change' => '更换资源',
        'choose' => '选择资源',
        'unavailable' => '不可用',
        'currently' => '当前分配',
        'updated' => '资源已更新：:name',
    ],

    'status' => [
        'check-in' => [
            'action' => '签到',
            'title' => '为客户签到',
            'intro' => '客户到了。他们的预约会转为已签到，并记录下时间。',
            'confirm' => '签到',
            'note' => '签到备注',
            'note_hint' => '选填。"早到了十分钟"，以及团队应该知道的任何其他信息。',
            'done_at' => '由 :name 于 :time 签到',
            'already' => '已签到',
        ],
        /* 没有原因列表，也几乎没有对话框：把一次服务做完是最平常的结果，
           在它前面放一个必填下拉框，每次都会被填成同一个答案。 */
        'complete' => [
            'action' => '完成',
            'title' => '完成服务',
            'intro' => '活儿做完了。这次预约会记为已交付；如果开启了评价邀请，还会询问客户体验如何。',
            'confirm' => '完成服务',
            'note' => '完成备注',
            'note_hint' => '选填。写给团队看的，不是给客户看的。',
        ],
        'no-show' => [
            'action' => '爽约',
            'title' => '把预约标记为爽约',
            'intro' => '预约仍然留在记录里；客户会被标记为没有到店。',
            'reason' => '原因',
            'confirm' => '保存',
        ],
        'cancelled' => [
            'action' => '取消预约',
            'title' => '取消预约',
            'intro' => '这次预约被取消，它占用的时段会被释放出来。',
            'reason' => '取消原因',
            /* 用"保留预约"而不是"取消"：在一个关于取消的对话框里，
               写着"取消"的按钮是唯一一个没人能两次读出同一个意思的。 */
            'dismiss' => '保留预约',
            'confirm' => '取消预约',
        ],
        'declined' => [
            'action' => '拒绝预约',
            'title' => '拒绝预约',
            'intro' => '这个请求被拒绝。什么都不会被预约，并且可以告诉客户原因。',
            'reason' => '拒绝原因',
            'confirm' => '拒绝预约',
        ],
        'reschedule' => [
            'action' => '为预约改期',
            'title' => '为预约改期',
            'intro' => '同一个预约，换一个时间。编号、客户和账单都保持不变。',
            'reason' => '改期原因',
            'confirm' => '保存改期',
            'current' => '当前',
            'new_date' => '新日期',
            'new_time' => '新时间',
            'staff' => '团队成员',
            'location' => '门店',
            'pick_date' => '选一个日期，看看有哪些空档。',
            'no_slots' => '那一天没有空档。',
            'loading' => '正在查看有哪些空档…',
        ],

        'choose_reason' => '选择一个原因',
        'note' => '备注',
        'note_hint' => '选填。写给团队看的，不是给客户看的。',
        'details' => '补充说明',
        'details_hint' => '这个原因必须填写。',
        'details_required' => '这个原因需要一段说明。',
        'reason_unavailable' => '该原因已不再可用。请另选一个。',
        'slot_taken' => '那个时间已被占用。请另选一个。',
        'dismiss' => '取消',

        'done' => [
            'check-in' => '客户已签到。',
            'complete' => '服务已完成。',
            'no-show' => '已标记为爽约。',
            'cancelled' => '预约已取消。',
            'declined' => '预约已拒绝。',
            'reschedule' => '预约已改期。',
        ],
    ],

    /*
    | 预约自己的历史：对它做过的每一件事，用这些原因在当天的措辞，
    | 而不是它们现在的措辞。
    */
    'activity' => [
        'title' => '预约动态',
        'none' => '这个预约还没有发生任何事。',
        'system' => 'StyleDesk',
        'by' => '由 :name',
        'reason' => '原因',
        'note' => '备注',
        'previous' => '原值',
        'new' => '新值',
        'events' => [
            'no-show' => '预约已标记为爽约',
            'cancelled' => '预约已取消',
            'declined' => '预约已拒绝',
            'confirmed' => '预约已改期',
            'pending' => '预约已改期',
            'arrived' => '客户已签到',
        ],
    ],

    'detail' => [
        'payment_status' => '付款',
        'book_again' => '再约一次',
        'cancel_booking' => '取消预约',
        'reschedule' => '改期',
        'soon_hint' => '尚未开发——取消和改期都会改动日程表。',
        'services' => '服务详情',
        'notes' => '预约备注',
        'no_notes' => '这次预约没有任何备注。',
        'payment_summary' => '付款摘要',
        'take_payment' => '收款',
        'paid_in_full' => '已全额付清',
        'transactions' => '收款流水',
        'no_transactions' => '这个预约还没有收到任何款项。',
        'recorded_by' => '由 :name 记录',
        'taken_by' => '由 :name 于 :when 收取',
        'someone' => '一位团队成员',
        'updated' => '最近更新于 :when',
        'walk_in' => '一位到店客人，没有建客户记录就下了单。',
        /* 明说出来，因为随着客户资料变化，档案和这个页面会不一致——而这正是它的意义。 */
        'snapshot' => '这是接下这个预约时（:when）的信息。',
    ],

    'pay' => [
        'coupon' => '优惠券代码',
        'coupon_placeholder' => '输入优惠券代码',
        'apply' => '应用',
        'remove_coupon' => '移除',
        'add_tip' => '添加小费',
        'no_tip' => '不给小费',
        'custom_tip' => '自定义',
        'discount' => '折扣',
        'tip' => '小费',
        'total_due' => '应付总额',
        'paying_by' => '付款方式',
        'paying_by_hint' => '有些服务用现金付的金额不同。总额会随之变化。',
        'title' => '付款',
        'due' => '应付金额',
        'collect_now' => '现在应收金额',
        'booking_total' => '预约总额',
        'remaining' => '剩余应付',
        'method' => '他们怎么付？',
        'change_method' => '更换',
        'back' => '返回预约摘要',
        'again' => '保存更改并继续',
        'skip' => '不收款直接确认',
        'skip_hint' => '预约照常成立，余额继续挂账。',
        'pay_amount' => '支付 :amount',
        'record' => '登记现金收款',
        'mark_paid' => '标记为已付',
        'marking' => '正在记录…',
        'amount' => '金额',
        'received' => '实收金额',
        'change' => '应找零',
        'reference' => '流水号',
        'reference_hint' => '对方那边显示的任何单号——选填。',
        'cardholder' => '持卡人姓名',
        'card_number' => '卡号',
        'expiry' => '有效期',
        'cvv' => 'CVV',
        'zip' => '账单邮编',
        'card_safe' => '卡片信息直接发给支付服务商。StyleDesk 从不保存它们。',
        'no_card_provider' => '没有连接任何刷卡服务商，因此 StyleDesk 无法自己收卡。请用刷卡机收，然后在下面登记。',
        'terminal' => '通过刷卡机收取',
        'not_ready' => '尚未设置。请在商户设置里添加账号。',
        'cash_only' => '这个预约按现金价计价，因此以现金结算。',
        'cash_only_row' => '不可用——这个预约按现金价计价。',
        'handle_hint' => '把这个念给对方，然后标记为已收款。',
        'short_cash' => '这少于要支付的金额。',
        'failed' => '这笔款项没有记录成功。没有扣任何钱——请重试。',
        'partial' => '已付 :paid／共 :total · 尚欠 :due',
    ],

    'methods' => [
        'card' => ['name' => '信用卡', 'hint' => '现在扣款，或用刷卡机收'],
        'cash' => ['name' => '现金', 'hint' => '在前台点清'],
        'paypal' => ['name' => 'PayPal', 'hint' => '转到门店的 PayPal'],
        'zelle' => ['name' => 'Zelle', 'hint' => '转到门店的 Zelle'],
        'cash-app' => ['name' => 'Cash App', 'hint' => '转到门店的 Cash App'],
        'venmo' => ['name' => 'Venmo', 'hint' => '转到门店的 Venmo'],
    ],

    'payment_statuses' => [
        'authorized' => ['label' => '已授权'],
        'cancelled' => ['label' => '已取消'],
        'disputed' => ['label' => '有争议'],
        'chargeback' => ['label' => '拒付'],
        'unpaid' => ['label' => '未付款'],
        'partial' => ['label' => '部分付款'],
        'paid' => ['label' => '已付款'],
        'pending' => ['label' => '待付款'],
        'failed' => ['label' => '失败'],
        'refunded' => ['label' => '已退款'],
        'partially-refunded' => ['label' => '部分退款'],
    ],

    'confirmation' => [
        'title' => '预约已确认',
        'made' => '这次预约已排进日程表。',
        'payment' => '付款',
        'view' => '查看预约',
        'another' => '再创建一个预约',
        'to_list' => '返回预约列表',
        'print' => '打印确认单',
        'receipt' => '下载收据',
        'receipt_title' => '收据',
        'send' => '发送确认信息',
        'sending' => '正在发送…',
        'sent' => '确认信息已发送至 :to。',
        'no_email' => '这位客户档案里没有电子邮箱。',
        'no_sms' => '短信功能尚未接入。请改用邮件发送，或先添加一个短信账号。',
        'link_failed' => '预约已成立，但付款链接没能发出邮件。请改为致电客户。',
        'due_notice' => '应付款项 :amount',
        'amount_due' => '应付金额',
    ],

    'email' => [
        'subject' => ':business —— 你在 :date 的预约',
        'headline' => '你的预约已确认',
        'intro' => '谢谢你，:name。以下是详细信息。',
        'footer_note' => '需要改期或取消？回复这封邮件或直接打电话给我们。',
        'link_subject' => ':business —— 支付你在 :date 的预约',
        'link_headline' => '如何支付你的预约',
        'link_intro' => '谢谢你，:name。以下是尚未结清的金额，以及结清的方式。',
        'link_amount' => '需支付金额',
        'link_cta' => '查看付款详情',
        'link_expiry' => '此链接在 :when 之前有效。',
    ],

    /*
    | 客户从付款链接进入的页面。
    |
    | 写给一个不是、也永远不会是 StyleDesk 用户的人看，所以它只说欠多少、往哪里付，
    | 完全不提这家门店内部是怎么运作的。
    */
    'pay_link' => [
        'title' => '支付你的预约',
        'amount' => '需支付金额',
        'balance' => '此预约的余额：:amount',
        'how' => '如何支付',
        'reference_hint' => '请注明 :reference，方便我们对上你的预约。',
        'no_handles' => '给我们打个电话，我们在电话里收。',
        'expired' => '这个付款链接已过期。',
        'expired_hint' => '联系我们，我们会重新发一个给你。',
        'settled' => '这个预约已全额付清。',
        'settled_hint' => '没有其他欠款了。谢谢你。',
        'footer' => '此链接只针对一次预约，不会让你登录任何账号。',
    ],

    'validation' => [
        'who' => '选择一位客户，或给到店客人填个名字。',
    ],
];
