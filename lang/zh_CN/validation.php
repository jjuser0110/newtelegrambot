<?php

/*
 * Simplified Chinese validation messages for the rules this app uses.
 * Rules not listed here fall back to English.
 */
return [
    'alpha_dash' => ':attribute 只能包含字母、数字、横线和下划线。',
    'array' => ':attribute 必须是数组。',
    'boolean' => ':attribute 必须是是或否。',
    'confirmed' => ':attribute 两次输入不一致。',
    'current_password' => '当前密码不正确。',
    'different' => ':attribute 必须和 :other 不同。',
    'date' => ':attribute 不是有效的日期。',
    'exists' => '选择的 :attribute 无效。',
    'image' => ':attribute 必须是图片。',
    'in' => '选择的 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'max' => [
        'array' => ':attribute 不能超过 :max 项。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'min' => [
        'array' => ':attribute 至少需要 :min 项。',
        'file' => ':attribute 至少需要 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少需要 :min 个字符。',
    ],
    'regex' => ':attribute 格式不正确。',
    'required' => '请填写 :attribute。',
    'required_without' => '没有 :values 时必须填写 :attribute。',
    'required_without_all' => ':values 都没有时必须填写 :attribute。',
    'string' => ':attribute 必须是文字。',
    'unique' => ':attribute 已被使用。',
    'uploaded' => ':attribute 上传失败。',

    'attributes' => [
        'batch_delay_minutes' => '批次间暂停（分钟）',
        'batch_size' => '每批数量',
        'bot_limit' => '机器人数量上限',
        'current_password' => '当前密码',
        'password_confirmation' => '确认新密码',
        'from_chat_id' => '来源 chat_id',
        'image' => '图片',
        'label' => '名称',
        'locale' => '语言',
        'message' => '消息',
        'message_id' => '消息 ID',
        'messages_per_second' => '每秒消息数',
        'name' => '名称',
        'password' => '密码',
        'saved_message_id' => '收藏消息',
        'scheduled_at' => '排程时间',
        'sort_order' => '顺序',
        'title' => '名称',
        'token' => 'Token',
        'trigger' => '触发词',
        'username' => '用户名',
    ],
];
