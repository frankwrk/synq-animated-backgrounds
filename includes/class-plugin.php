<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SYNQ_Animated_Backgrounds_Plugin {

    /**
     * @var SYNQ_Bg_Provider_Interface[]
     */
    protected $providers = [];

    /**
     * Prevent duplicate registration.
     *
     * @var bool
     */
    protected $core_assets_registered = false;

    /**
     * Prevent duplicate enqueueing for core assets.
     *
     * @var bool
     */
    protected $core_assets_enqueued = false;

    /**
     * Track provider asset handles already enqueued.
     *
     * @var bool[]
     */
    protected $provider_assets_enqueued = [];

    public function __construct() {
        $this->register_providers();

        add_action(
            'elementor/element/container/section_layout/after_section_end',
            [ $this, 'register_container_controls' ],
            10,
            2
        );

        add_action(
            'elementor/frontend/before_render',
            [ $this, 'on_element_before_render' ]
        );

        add_action(
            'wp_enqueue_scripts',
            [ $this, 'register_core_assets' ],
            5
        );

        add_action(
            'wp_enqueue_scripts',
            [ $this, 'maybe_enqueue_assets_from_document' ],
            20
        );

        add_action(
            'elementor/preview/enqueue_scripts',
            [ $this, 'enqueue_assets_for_editor_preview' ]
        );

        add_action(
            'elementor/editor/after_enqueue_scripts',
            [ $this, 'enqueue_assets_for_editor_preview' ]
        );
    }

    /**
     * Register all background animation providers.
     */
    protected function register_providers(): void {
        $vanta_topology = new SYNQ_Bg_Provider_Vanta_Topology();
        $vanta_trunk    = new SYNQ_Bg_Provider_Vanta_Trunk();

        $this->providers[ $vanta_topology->get_type() ] = $vanta_topology;
        $this->providers[ $vanta_trunk->get_type() ]    = $vanta_trunk;
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
        if ( $this->core_assets_registered ) {
            return;
        }

        wp_register_script(
            'synq-ab-core',
            SYNQ_AB_PLUGIN_URL . 'assets/js/core.js',
            [ 'elementor-frontend' ],
            SYNQ_AB_PLUGIN_VERSION,
            true
        );

        wp_register_style(
            'synq-ab-frontend',
            SYNQ_AB_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SYNQ_AB_PLUGIN_VERSION
        );

        $this->core_assets_registered = true;
    }

    /**
     * Enqueue core JS/CSS assets.
     */
    protected function enqueue_core_assets(): void {
        if ( $this->core_assets_enqueued ) {
            return;
        }

        wp_enqueue_script( 'synq-ab-core' );
        wp_enqueue_style( 'synq-ab-frontend' );

        $this->core_assets_enqueued = true;
    }

    /**
     * Enqueue a single provider's frontend assets once.
     */
    protected function enqueue_provider_assets( string $type ): void {
        if ( ! isset( $this->providers[ $type ] ) ) {
            return;
        }

        if ( isset( $this->provider_assets_enqueued[ $type ] ) ) {
            return;
        }

        $this->providers[ $type ]->enqueue_scripts();
        $this->provider_assets_enqueued[ $type ] = true;
    }

    /**
     * Attempt to detect required providers from current post's Elementor data.
     */
    public function maybe_enqueue_assets_from_document(): void {
        if ( is_admin() ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( empty( $post_id ) ) {
            return;
        }

        $provider_types = $this->get_provider_types_from_post( (int) $post_id );
        if ( empty( $provider_types ) ) {
            return;
        }

        $this->register_core_assets();
        $this->enqueue_core_assets();

        foreach ( $provider_types as $provider_type ) {
            $this->enqueue_provider_assets( $provider_type );
        }
    }

    /**
     * In Elementor edit mode, preload core + all providers so live control
     * changes can preview instantly even before first save.
     */
    public function enqueue_assets_for_editor_preview(): void {
        $this->register_core_assets();
        $this->enqueue_core_assets();

        foreach ( array_keys( $this->providers ) as $provider_type ) {
            $this->enqueue_provider_assets( $provider_type );
        }
    }

    /**
     * Parse Elementor JSON and collect provider types referenced in this post.
     *
     * @return string[]
     */
    protected function get_provider_types_from_post( int $post_id ): array {
        $raw_data = get_post_meta( $post_id, '_elementor_data', true );
        if ( empty( $raw_data ) || ! is_string( $raw_data ) ) {
            return [];
        }

        $elementor_data = json_decode( $raw_data, true );
        if ( ! is_array( $elementor_data ) ) {
            return [];
        }

        $provider_types = [];
        $this->collect_provider_types_from_elements( $elementor_data, $provider_types );

        return array_values( array_unique( $provider_types ) );
    }

    /**
     * Recursively collect enabled provider types from element arrays.
     *
     * @param array   $elements       Elementor node array.
     * @param string[] $provider_types Output list of provider type keys.
     */
    protected function collect_provider_types_from_elements( array $elements, array &$provider_types ): void {
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $settings = isset( $element['settings'] ) && is_array( $element['settings'] )
                ? $element['settings']
                : [];

            $is_enabled = ! empty( $settings['synq_bg_anim_enable'] ) && 'yes' === $settings['synq_bg_anim_enable'];
            if ( $is_enabled ) {
                $type = isset( $settings['synq_bg_anim_type'] )
                    ? sanitize_key( (string) $settings['synq_bg_anim_type'] )
                    : '';

                if ( $type && isset( $this->providers[ $type ] ) ) {
                    $provider_types[] = $type;
                }
            }

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $this->collect_provider_types_from_elements( $element['elements'], $provider_types );
            }
        }
    }

    /**
     * Clamp a slider value from Elementor settings.
     */
    protected function get_clamped_slider_value( array $settings, string $key, float $default, float $min, float $max ): float {
        if ( empty( $settings[ $key ]['size'] ) || ! is_numeric( $settings[ $key ]['size'] ) ) {
            return $default;
        }

        $value = (float) $settings[ $key ]['size'];
        if ( ! is_finite( $value ) ) {
            return $default;
        }

        return max( $min, min( $max, $value ) );
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
        if ( 'container' !== $element->get_name() ) {
            return;
        }

        $settings = $element->get_settings_for_display();

        if ( empty( $settings['synq_bg_anim_enable'] ) || 'yes' !== $settings['synq_bg_anim_enable'] ) {
            return;
        }

        $type = isset( $settings['synq_bg_anim_type'] )
            ? sanitize_key( (string) $settings['synq_bg_anim_type'] )
            : '';

        if ( ! $type || ! isset( $this->providers[ $type ] ) ) {
            return;
        }

        // Fallback for theme-builder or dynamic document contexts where post-level
        // detection may not have happened before render.
        $this->register_core_assets();
        $this->enqueue_core_assets();
        $this->enqueue_provider_assets( $type );

        $provider       = $this->providers[ $type ];
        $disable_mobile = ! empty( $settings['synq_bg_anim_disable_mobile'] ) && 'yes' === $settings['synq_bg_anim_disable_mobile'];
        $intensity      = $this->get_clamped_slider_value( $settings, 'synq_bg_anim_intensity', 0.7, 0.0, 1.0 );
        $speed          = $this->get_clamped_slider_value( $settings, 'synq_bg_anim_speed', 1.0, 0.5, 2.0 );

        $base_config = [
            'type'          => $type,
            'disableMobile' => $disable_mobile,
            'intensity'     => $intensity,
            'speed'         => $speed,
        ];

        $config      = $provider->normalize_config( $settings, $base_config );
        $config_json = wp_json_encode( $config );

        if ( false === $config_json ) {
            return;
        }

        $element->add_render_attribute(
            '_wrapper',
            [
                'data-bg-anim'        => $type,
                'data-bg-anim-config' => $config_json,
            ]
        );

        $element->add_render_attribute(
            '_wrapper',
            'class',
            'synq-bg-anim-wrapper'
        );
    }
}
