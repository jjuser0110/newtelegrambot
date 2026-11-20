<?php

/*
 * Malay validation messages for the rules this app uses.
 * Rules not listed here fall back to English.
 */
return [
    'alpha_dash' => ':attribute hanya boleh mengandungi huruf, nombor, sengkang dan garis bawah.',
    'array' => ':attribute mestilah satu senarai.',
    'boolean' => ':attribute mestilah ya atau tidak.',
    'confirmed' => 'Pengesahan :attribute tidak sepadan.',
    'current_password' => 'Kata laluan semasa tidak betul.',
    'different' => ':attribute mesti berbeza daripada :other.',
    'date' => ':attribute bukan tarikh yang sah.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'image' => ':attribute mestilah gambar.',
    'in' => ':attribute yang dipilih tidak sah.',
    'integer' => ':attribute mestilah nombor bulat.',
    'max' => [
        'array' => ':attribute tidak boleh melebihi :max item.',
        'file' => ':attribute tidak boleh melebihi :max KB.',
        'numeric' => ':attribute tidak boleh melebihi :max.',
        'string' => ':attribute tidak boleh melebihi :max aksara.',
    ],
    'min' => [
        'array' => ':attribute mesti mempunyai sekurang-kurangnya :min item.',
        'file' => ':attribute mesti sekurang-kurangnya :min KB.',
        'numeric' => ':attribute mesti sekurang-kurangnya :min.',
        'string' => ':attribute mesti sekurang-kurangnya :min aksara.',
    ],
    'regex' => 'Format :attribute tidak sah.',
    'required' => 'Ruangan :attribute diperlukan.',
    'required_without' => 'Ruangan :attribute diperlukan apabila :values tiada.',
    'required_without_all' => 'Ruangan :attribute diperlukan apabila :values semuanya tiada.',
    'string' => ':attribute mestilah teks.',
    'unique' => ':attribute telah digunakan.',
    'uploaded' => ':attribute gagal dimuat naik.',

    'attributes' => [
        'batch_delay_minutes' => 'rehat antara kelompok (minit)',
        'batch_size' => 'saiz kelompok',
        'bot_limit' => 'had bot',
        'current_password' => 'kata laluan semasa',
        'password_confirmation' => 'pengesahan kata laluan',
        'from_chat_id' => 'chat_id sumber',
        'image' => 'gambar',
        'label' => 'label',
        'locale' => 'bahasa',
        'message' => 'mesej',
        'message_id' => 'ID mesej',
        'messages_per_second' => 'mesej sesaat',
        'name' => 'nama',
        'password' => 'kata laluan',
        'saved_message_id' => 'mesej simpanan',
        'scheduled_at' => 'masa jadual',
        'sort_order' => 'susunan',
        'title' => 'nama',
        'token' => 'token',
        'trigger' => 'pencetus',
        'username' => 'nama pengguna',
    ],
];
