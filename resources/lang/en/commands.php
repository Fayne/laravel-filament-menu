<?php

return [
    'tenant_requires_demo' => 'The --tenant option requires --demo.',
    'install_core_first' => 'Run cms:install before installing this module.',
    'installed' => 'Menu installation is ready. No demo content was created.',
    'tenant_required' => 'Use --tenant with --demo in noninteractive tenant mode.',

    'select_tenant' => 'Select tenants for the demo menus',
    'tenant_not_found' => 'No matching active tenant was found. Create a tenant first or check --tenant.',
    'tenancy_disabled' => 'The --tenant option requires tenant mode to be enabled.',
    'bilingual_locales_required' => 'The target site or tenant must support en and zh_CN (or zh-CN) before creating bilingual demo menus.',
    'demo_ready' => 'Demo menus initialized and left inactive. Existing menus with the same demo slugs were preserved.',
    'multilingual_disabled' => 'Multilingual content is disabled: only the default language is shown until it is enabled in the site or tenant settings.',
];
