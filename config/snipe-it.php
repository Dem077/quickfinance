<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Snipe-IT API
    |--------------------------------------------------------------------------
    |
    | Set SNIPE_IT_URL to your Snipe-IT base URL (no trailing slash).
    | Create an API token in Snipe-IT: Account > API Keys.
    |
    */

    'url' => env('SNIPE_IT_URL'),

    'api_token' => env('SNIPE_IT_API_TOKEN'),

    'enabled' => env('SNIPE_IT_ENABLED', true),

    'default_status_id' => (int) env('SNIPE_IT_DEFAULT_STATUS_ID', 2),

    'default_category_id' => (int) env('SNIPE_IT_DEFAULT_CATEGORY_ID', 1),

    'default_manufacturer_id' => (int) env('SNIPE_IT_DEFAULT_MANUFACTURER_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Snipe-IT custom field DB column names (Settings > Custom Fields)
    |--------------------------------------------------------------------------
    */

    'custom_fields' => [
        'cao_asset_code' => env('SNIPE_IT_FIELD_CAO_ASSET_CODE', '_snipeit_cao_asset_code_2'),
        'finance_old_asset_tag' => env('SNIPE_IT_FIELD_FINANCE_OLD_ASSET_TAG', '_snipeit_finance_old_asset_tag_no_5'),
        'asset_class' => env('SNIPE_IT_FIELD_ASSET_CLASS', '_snipeit_asset_class_3'),
        'po_reference' => env('SNIPE_IT_FIELD_PO_REFERENCE', '_snipeit_po_number_reference_no_4'),
        'mac_address' => env('SNIPE_IT_FIELD_MAC_ADDRESS', '_snipeit_mac_address_1'),
    ],

];
