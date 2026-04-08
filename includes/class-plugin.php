<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SYNQ_Animated_Backgrounds_Plugin {

    /**
     * @var SYNQ_Bg_Provider_Interface[]
     */
    protected $providers = [];

    protected $core_scripts_registered = false;
    protected $core_scripts_enqueued   = false;

    public function __construct() {
        $this->register_providers();

        // Elementor controls
        add_action(
            'elementor/element/container/section_layout/after_section_end',
            [ $this, 'register_container_controls' ],
            10,
            2
        );

        // Frontend: inject data attributes for containers
        add_action(
            'elementor/frontend/before_render',
            [ $this, 'on_element_before_render' ]
        );

        // Register core scripts / styles
        add_action(
            'wp_enqueue_scripts',
            [ $this, 'register_core_assets' ]
        );

        // Ensure assets are enqueued on Elementor frontend pages
        add_action(
            'elementor/frontend/after_enqueue_scripts',
            [ $this, 'force_enqueue_assets' ]
        );
    }

    /**
     * Register all background animation providers.
     */
    protected function register_providers(): void {
        $vanta_topology = new SYNQ_Bg_Provider_Vanta_Topology();

        $this->providers[ $vanta_topology->get_type() ] = $vanta_topology;
    }

    /**
     * Build options array for the "Animation Type" select.
     */
    protected function get_provider_options(): array {
        $options = [];

        foreach ( $this->providers as $type => $provider ) {
            $options[ $type ] = $provider->get_label();
        }

        return $options;
    }

    /**
     * Register core JS/CSS assets.
     */
    public function register_core_assets(): void {
        if ( $this->core_scripts_registered ) {
            return;
        }

        // Core JS
        wp_register_script(
            'synq-ab-core',
            SYNQ_AB_PLUGIN_URL . 'assets/js/core.js',
            [ 'elementor-frontend' ],
            SYNQ_AB_PLUGIN_VERSION,
            true
        );

        // Optional CSS stub
        wp_register_style(
            'synq-ab-frontend',
            SYNQ_AB_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SYNQ_AB_PLUGIN_VERSION
        );

        $this->core_scripts_registered = true;
    }

    /**
     * Enqueue core JS/CSS assets.
     */
    protected function enqueue_core_assets(): void {
        if ( $this->core_scripts_enqueued ) {
            return;
        }

        wp_enqueue_script( 'synq-ab-core' );
        wp_enqueue_style( 'synq-ab-frontend' );

        $this->core_scripts_enqueued = true;
    }

    /**
     * Ensure core and provider assets are enqueued on Elementor frontend pages.
     * This avoids race conditions with Elementor performance features / caching.
     */
    public function force_enqueue_assets(): void {
        // Make sure core assets are registered and enqueued.
        $this->register_core_assets();
        $this->enqueue_core_assets();

        // For v0.1, enqueue all providers on Elementor pages.
        foreach ( $this->providers as $provider ) {
            $provider->enqueue_scripts();
        }
    }

    /**
     * Add "Background Animation" controls to Elementor Container.
     */
    public function register_container_controls( $element, $args ): void {
        $element->start_controls_section(
            'synq_bg_anim_section',
            [
                'label' => __( 'Background Animation', 'synq-animated-backgrounds' ),
                'tab'   => \Elementor\Controls_Manager::TAB_LAYOUT,
            ]
        );

        $element->add_control(
            'synq_bg_anim_enable',
            [
                'label'        => __( 'Enable Background Animation', 'synq-animated-backgrounds' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'synq-animated-backgrounds' ),
                'label_off'    => __( 'No', 'synq-animated-backgrounds' ),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $element->add_control(
            'synq_bg_anim_type',
            [
                'label'     => __( 'Animation Type', 'synq-animated-backgrounds' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => $this->get_provider_options(),
                'default'   => '',
                'condition' => [
                    'synq_bg_anim_enable' => 'yes',
                ],
            ]
        );

        // Shared options
        $element->add_control(
            'synq_bg_anim_disable_mobile',
            [
                'label'        => __( 'Disable on Mobile', 'synq-animated-backgrounds' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'synq-animated-backgrounds' ),
                'label_off'    => __( 'No', 'synq-animated-backgrounds' ),
                'return_value' => 'yes',
                'default'      => '',
                'condition'    => [
                    'synq_bg_anim_enable' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'synq_bg_anim_intensity',
            [
                'label'      => __( 'Intensity (generic)', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 0,
                        'max'  => 1,
                        'step' => 0.1,
                    ],
                ],
                'default'    => [
                    'size' => 0.7,
                ],
                'condition'  => [
                    'synq_bg_anim_enable' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'synq_bg_anim_speed',
            [
                'label'      => __( 'Speed (generic)', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 0.5,
                        'max'  => 2,
                        'step' => 0.1,
                    ],
                ],
                'default'    => [
                    'size' => 1.0,
                ],
                'condition'  => [
                    'synq_bg_anim_enable' => 'yes',
                ],
            ]
        );

        // Provider-specific controls
        foreach ( $this->providers as $provider ) {
            $provider->register_controls( $element );
        }

        $element->end_controls_section();
    }

    /**
     * Inject data attributes for containers with animation enabled.
     *
     * @param \Elementor\Element_Base $element
     */
    public function on_element_before_render( $element ): void {
        // Only target Elementor Containers (for now).
        if ( 'container' !== $element->get_name() ) {
            return;
        }

        $settings = $element->get_settings_for_display();

        if ( empty( $settings['synq_bg_anim_enable'] ) || 'yes' !== $settings['synq_bg_anim_enable'] ) {
            return;
        }

        $type = $settings['synq_bg_anim_type'] ?? '';

        if ( ! $type || ! isset( $this->providers[ $type ] ) ) {
            return;
        }

        $provider = $this->providers[ $type ];

        $disable_mobile = ! empty( $settings['synq_bg_anim_disable_mobile'] ) && 'yes' === $settings['synq_bg_anim_disable_mobile'];

        $intensity = ! empty( $settings['synq_bg_anim_intensity']['size'] )
            ? (float) $settings['synq_bg_anim_intensity']['size']
            : 0.7;

        $speed = ! empty( $settings['synq_bg_anim_speed']['size'] )
            ? (float) $settings['synq_bg_anim_speed']['size']
            : 1.0;

        $base_config = [
            'type'          => $type,
            'disableMobile' => $disable_mobile,
            'intensity'     => $intensity,
            'speed'         => $speed,
        ];

        $config = $provider->normalize_config( $settings, $base_config );

        // Attach data attributes
        $element->add_render_attribute(
            '_wrapper',
            [
                'data-bg-anim'        => esc_attr( $type ),
                'data-bg-anim-config' => esc_attr( wp_json_encode( $config ) ),
            ]
        );

        // For stacking context / fallback, add a helper class.
        $element->add_render_attribute(
            '_wrapper',
            'class',
            'synq-bg-anim-wrapper'
        );
    }
}