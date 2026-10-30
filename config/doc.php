<?php

/*
| Development Operations Center configuration.
| Checklist templates are generated per project on demand and can be edited afterwards.
| Items marked true are mandatory for delivery/closure.
*/

return [

    'currency' => env('DOC_CURRENCY', 'INR'),

    'reminders' => [
        'client_follow_up_days' => 3,
        'stale_task_days' => 5,
    ],

    'uploads' => [
        'disk' => 'local', // private disk: storage/app/private
        'max_kb' => 20480,
        'mimes' => 'pdf,png,jpg,jpeg,gif,webp,txt,csv,md,doc,docx,xls,xlsx,ppt,pptx,zip',
        // Client documents (Clients > Documents): same private disk and size limit, office/PDF/image/text/zip only.
        'client_document_mimes' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,webp,txt,zip',
    ],

    'checklists' => [
        'release' => [
            'Required features completed' => true,
            'Critical defects resolved' => true,
            'Major defects resolved or accepted' => true,
            'Client feedback resolved' => true,
            'Mobile responsiveness tested' => true,
            'Forms tested' => true,
            'SEO basics configured' => false,
            'Analytics configured' => false,
            'SSL verified' => true,
            'Backups configured' => true,
            'Production URL tested' => true,
            'Payment gateway tested (if applicable)' => false,
            'Email delivery tested' => false,
            'Final client approval received' => true,
        ],
        'handover' => [
            'Source code delivered' => true,
            'Hosting/admin credentials transferred securely (not stored here)' => true,
            'Documentation delivered' => true,
            'Admin panel instructions delivered' => false,
            'Domain/DNS notes delivered' => false,
            'Deployment notes delivered' => true,
            'Backup instructions delivered' => true,
            'Training completed' => false,
            'Warranty/support terms confirmed' => false,
            'Final invoice reference recorded' => false,
        ],
    ],
];
