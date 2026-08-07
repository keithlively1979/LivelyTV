<?php
/**
 * admin/includes/themes.php
 * Central definition of all available themes.
 * Each theme defines the four CSS custom property values that
 * drive colour throughout the admin panel.
 */

const THEMES = [
    'blue' => [
        'label'         => 'Blue',
        'primary'       => '#0000fe',
        'primary_dark'  => '#0000cc',
        'primary_light' => '#e6e6ff',
        'primary_muted' => '#4d4dff',
    ],
    'indigo' => [
        'label'         => 'Indigo',
        'primary'       => '#4f46e5',
        'primary_dark'  => '#3730a3',
        'primary_light' => '#ede9fe',
        'primary_muted' => '#6d64ee',
    ],
    'red' => [
        'label'         => 'Red',
        'primary'       => '#dc2626',
        'primary_dark'  => '#b91c1c',
        'primary_light' => '#fee2e2',
        'primary_muted' => '#ef4444',
    ],
    'green' => [
        'label'         => 'Green',
        'primary'       => '#16a34a',
        'primary_dark'  => '#15803d',
        'primary_light' => '#dcfce7',
        'primary_muted' => '#22c55e',
    ],
    'orange' => [
        'label'         => 'Orange',
        'primary'       => '#ea580c',
        'primary_dark'  => '#c2410c',
        'primary_light' => '#ffedd5',
        'primary_muted' => '#f97316',
    ],
    'mono' => [
        'label'         => 'Monochrome',
        'primary'       => '#374151',
        'primary_dark'  => '#1f2937',
        'primary_light' => '#f3f4f6',
        'primary_muted' => '#6b7280',
    ],
    'cyber' => [
        'label'         => 'Cyber',
        'primary'       => '#08e3f2',
        'primary_dark'  => '#06b6c4',
        'primary_light' => '#e0fffe',
        'primary_muted' => '#22d3ee',
    ],
];

/**
 * Return theme tokens for the current user, falling back to blue.
 */
function get_theme_tokens(string $theme_key): array
{
    return THEMES[$theme_key] ?? THEMES['blue'];
}
