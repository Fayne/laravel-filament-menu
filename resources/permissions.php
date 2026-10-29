<?php

return [
    'resources' => ['Menu', 'MenuItem', 'MenuComponent', 'MenuComponentField', 'MenuWidget', 'MenuWidgetField'],
    'permissions' => [
        'editor' => ['tenant.viewSensitiveContentMenuItem'],
        'restricted' => ['tenant.executeContentHttpMenuItem'],
    ],
];
