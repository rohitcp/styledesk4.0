<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 原因代码
|--------------------------------------------------------------------------
|
| 某件事为什么会发生，从列表中选择而不是手工输入。列表本身放在
| config/reasons.php；这里只是它们叫什么。
|
*/

return [

    'title' => '原因',
    'intro' => '事情为什么会这样，用一份列表而不是一个自由填写的输入框——这样"我们为什么会流失预约"才是报表真正能回答的问题。',
    'back' => '全部原因列表',

    'types' => [
        'booking-cancellation' => ['label' => '取消预约', 'intro' => '预约为什么被取消。'],
        'booking-reschedule' => ['label' => '预约改期', 'intro' => '预约为什么改了时间。'],
        'no-show' => ['label' => '爽约', 'intro' => '客人为什么没有到店。'],
        'refund' => ['label' => '退款', 'intro' => '钱为什么退了出去。'],
        'payment-adjustment' => ['label' => '账单调整', 'intro' => '账单开出之后为什么又改了。'],
        'client-status-change' => ['label' => '客户状态变更', 'intro' => '客户为什么在正常、停用等状态之间变动。'],
        'staff-schedule-change' => ['label' => '员工排班变更', 'intro' => '排班为什么改了——班次、工时、休假和顶班。'],
        'booking-declined' => ['label' => '预约被拒', 'intro' => '预约申请为什么被拒绝。'],
        'service-cancellation' => ['label' => '停止提供服务', 'intro' => '某项服务为什么不再提供。'],
    ],

    'columns' => [
        'reason' => '原因',
        'source' => '来源',
        'details' => '追问原因',
        'status' => '状态',
        'action' => '操作',
    ],

    'system' => 'StyleDesk',
    'custom' => '你自己的',
    'renamed' => '已重命名',
    'active' => '开启',
    'inactive' => '关闭',
    'activate' => '开启',
    'deactivate' => '关闭',
    'edit' => '编辑',
    'delete' => '删除原因',
    'delete_confirm' => '删除“:name”？已经记在它名下的内容会保留原因；但今后不能再用它归档。',
    'system_undeletable' => '这一条由 StyleDesk 提供。可以重命名或关闭，但无法删除——归在它名下的记录仍然要说明原因。',

    'add' => '添加原因',
    'add_title' => '添加一条原因',
    'edit_title' => '编辑原因',
    'name' => '原因',
    'name_placeholder' => '客户改变了主意',
    'description' => '描述',
    'description_hint' => '选填。这一条是什么意思，写给下一个要选它的人看。',
    'requires_details' => '选择这一条时要求补充说明',
    'requires_details_hint' => '用于那些本身算不上答案的原因——"其他"就是最典型的一个。',
    'details_label' => '补充说明',

    'extra_title' => '同时会问',
    'extra_hint' => '这份列表在原因之外还会问第二个问题。它是记录 :label 的固定组成部分，无法配置。',

    'counts' => '共 :total 条，已开启 :active 条',
    'reorder_hint' => '拖动以重新排序',
    'save_order' => '保存排序',
    'order_changed' => '排序已更改，但尚未保存。',

    'added' => '原因已添加。',
    'updated' => '原因已更新。',
    'activated' => '原因已开启。',
    'deactivated' => '原因已关闭。已经记在它名下的内容不受影响。',
    'deleted' => '原因已删除。',
    'order_saved' => '排序已保存。',
    'none' => '这份列表里还没有原因。',
];
