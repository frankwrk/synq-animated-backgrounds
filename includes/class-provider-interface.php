<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Interface for background animation providers.
 */
interface SYNQ_Bg_Provider_Interface
{

    /**
     * Unique provider type, e.g. "vanta_topology".
     */
    public function get_type(): string;

    /**
     * Human-readable label for the provider.
     */
    public function get_label(): string;

    /**
     * Register provider-specific controls inside the "Background Animation" section.
     *
     * @param \Elementor\Element_Base $element
     * @return void
     */
    public function register_controls($element): void;

    /**
     * Normalize Elementor settings into a generic config array.
     *
     * @param array $settings    Full element settings.
     * @param array $base_config Core config prepared by the plugin.
     * @return array             Merged config.
     */
    public function normalize_config(array $settings, array $base_config): array;

    /**
     * Enqueue any frontend scripts required by this provider.
     *
     * @return void
     */
    public function enqueue_scripts(): void;
}