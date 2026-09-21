<?php
/**
 * App-wide constants: built-in field/KPI definitions, operator lists,
 * fixed choice lists and badge colors. The single source of truth
 * seed_builtin_fields()/seed_builtin_kpis() (in helpers.php) read from.
 */

const BUILTIN_FIELDS = [
    'title'       => ['label' => 'Title',         'description' => 'Title',                             'type' => 'text',   'order' => 5],
    'release_no'  => ['label' => 'Spine',         'description' => 'Release / Spine #',                 'type' => 'number', 'order' => 10],
    'line_series' => ['label' => 'Line / Series', 'description' => 'Series / Line Category',            'type' => 'text',   'order' => 20],
    'legacy'      => ['label' => 'Legacy',        'description' => 'Legacy Status',                     'type' => 'yesno',  'order' => 30],
    'status'      => ['label' => 'Status',        'description' => 'Status (Owned / Wanted)',           'type' => 'yesno',  'order' => 40],
    'cib'         => ['label' => 'Packaging',     'description' => 'CIB (Complete In Box)',             'type' => 'yesno',  'order' => 50],
    'region'      => ['label' => 'Region',        'description' => 'Region / Edition (EUR, USA, JPN)',  'type' => 'select', 'order' => 60],
    'media_type'  => ['label' => 'Format',        'description' => 'Media Format (Physical / Digital)', 'type' => 'select', 'order' => 70],
    'notes'       => ['label' => 'Notes',         'description' => 'Notes Field',                       'type' => 'text',   'order' => 80],
];
const FIELD_TYPES = ['text' => 'Text', 'number' => 'Number', 'select' => 'Choice list', 'yesno' => 'Yes / No'];

// Built-in KPIs, expressed in the same generic count/percentage-with-conditions
// language a custom KPI uses (see kpi_compute()). Recreates exactly what the
// KPI bar showed before the "Reorder/Manage KPIs" feature existed, so nothing
// changes for anyone until they actually start editing their KPIs.
const BUILTIN_KPIS = [
    'catalog_total'  => ['label' => 'Catalog Total',        'color' => 'slate',   'calc_type' => 'count',      'n1_field' => '',         'n1_op' => '',      'n1_value' => '',          'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 10],
    'owned'          => ['label' => 'Owned',                 'color' => 'emerald', 'calc_type' => 'count',      'n1_field' => 'is_owned', 'n1_op' => 'true',  'n1_value' => '',          'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 20],
    'completion'     => ['label' => 'Completion',            'color' => 'indigo',  'calc_type' => 'percentage', 'n1_field' => 'is_owned', 'n1_op' => 'true',  'n1_value' => '',          'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 30],
    'legacy_total'   => ['label' => 'Legacy Total',          'color' => 'slate',   'calc_type' => 'count',      'n1_field' => 'is_legacy','n1_op' => 'true',  'n1_value' => '',          'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 40],
    'legacy_owned'   => ['label' => 'Legacy Owned',          'color' => 'emerald', 'calc_type' => 'count',      'n1_field' => 'is_legacy','n1_op' => 'true',  'n1_value' => '',          'n2_field' => 'is_owned', 'n2_op' => 'true',  'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 50],
    'legacy_missing' => ['label' => 'Legacy Missing',        'color' => 'rose',    'calc_type' => 'count',      'n1_field' => 'is_legacy','n1_op' => 'true',  'n1_value' => '',          'n2_field' => 'is_owned', 'n2_op' => 'false', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 60],
    'titles_total'   => ['label' => 'Titles in Collection',  'color' => 'indigo',  'calc_type' => 'count',      'n1_field' => '',         'n1_op' => '',      'n1_value' => '',          'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 70],
    'cib'            => ['label' => 'Complete In Box (CIB)', 'color' => 'emerald', 'calc_type' => 'count',      'n1_field' => 'is_cib',   'n1_op' => 'true',  'n1_value' => '',          'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 80],
    'physical'       => ['label' => 'Physical',              'color' => 'amber',   'calc_type' => 'count',      'n1_field' => 'media_type', 'n1_op' => 'eq',  'n1_value' => 'Physical',  'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 90],
    'digital'        => ['label' => 'Digital',               'color' => 'sky',     'calc_type' => 'count',      'n1_field' => 'media_type', 'n1_op' => 'eq',  'n1_value' => 'Digital',   'n2_field' => '', 'n2_op' => '', 'n2_value' => '', 'd1_field' => '', 'd1_op' => '', 'd1_value' => '', 'order' => 100],
];

// Operators available for a KPI condition, by the field's data type
const KPI_OPERATORS = [
    'yesno' => ['true' => 'is Yes', 'false' => 'is No'],
    'select' => ['eq' => 'is', 'neq' => 'is not'],
    'number' => ['eq' => '=', 'neq' => '≠', 'gt' => '>', 'lt' => '<', 'gte' => '≥', 'lte' => '≤'],
    'text' => ['contains' => 'contains', 'not_contains' => "doesn't contain", 'empty' => 'is empty', 'not_empty' => 'is not empty'],
];

// Fixed choices of the built-in choice-list fields (used by the quick-change button)
const BUILTIN_CHOICES = [
    'region'     => ['EUR (PAL)', 'USA (NTSC)', 'JPN (NTSC-J)', 'Region Free'],
    'media_type' => ['Physical', 'Digital'],
];

// Colors a choice can have (same badge design as EUR (PAL), Physical, OWNED, ...)
const CHOICE_COLORS = [
    'slate'   => 'Grey',
    'emerald' => 'Green',
    'sky'     => 'Blue',
    'indigo'  => 'Indigo',
    'purple'  => 'Purple',
    'pink'    => 'Pink',
    'rose'    => 'Red',
    'orange'  => 'Orange',
    'amber'   => 'Yellow',
    'teal'    => 'Teal',
];

