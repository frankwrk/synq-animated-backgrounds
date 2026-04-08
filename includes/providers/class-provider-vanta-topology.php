<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SYNQ_Bg_Provider_Vanta_Topology implements SYNQ_Bg_Provider_Interface {

    /**
     * Clamp a numeric slider value.
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

    public function get_type(): string {
        return 'vanta_topology';
    }

    public function get_label(): string {
        return __( 'Vanta – Topology', 'synq-animated-backgrounds' );
    }

    public function register_controls( $element ): void {
        $type = $this->get_type();

        $element->add_control(
            "synq_bg_{$type}_line_color",
            [
                'label'     => __( 'Line Color', 'synq-animated-backgrounds' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#2a2a2a',
                'condition' => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        $element->add_control(
            "synq_bg_{$type}_bg_color",
            [
                'label'     => __( 'Background Color', 'synq-animated-backgrounds' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#000000',
                'condition' => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        $element->add_control(
            "synq_bg_{$type}_scale",
            [
                'label'      => __( 'Scale', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 0.5,
                        'max'  => 2.0,
                        'step' => 0.1,
                    ],
                ],
                'default'    => [
                    'size' => 1.0,
                ],
                'condition'  => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        $element->add_control(
            "synq_bg_{$type}_points",
            [
                'label'      => __( 'Points (Density)', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 5,
                        'max'  => 20,
                        'step' => 1,
                    ],
                ],
                'default'    => [
                    'size' => 10,
                ],
                'condition'  => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        $element->add_control(
            "synq_bg_{$type}_spacing",
            [
                'label'      => __( 'Spacing', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 5,
                        'max'  => 50,
                        'step' => 1,
                    ],
                ],
                'default'    => [
                    'size' => 15,
                ],
                'condition'  => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );
    }

    public function normalize_config( array $settings, array $base_config ): array {
        $type = $this->get_type();

        $line_color = isset( $settings[ "synq_bg_{$type}_line_color" ] )
            ? sanitize_hex_color( (string) $settings[ "synq_bg_{$type}_line_color" ] )
            : '';
        if ( empty( $line_color ) ) {
            $line_color = '#2a2a2a';
        }

        $bg_color = isset( $settings[ "synq_bg_{$type}_bg_color" ] )
            ? sanitize_hex_color( (string) $settings[ "synq_bg_{$type}_bg_color" ] )
            : '';
        if ( empty( $bg_color ) ) {
            $bg_color = '#000000';
        }

        $scale   = $this->get_clamped_slider_value( $settings, "synq_bg_{$type}_scale", 1.0, 0.5, 2.0 );
        $points  = $this->get_clamped_slider_value( $settings, "synq_bg_{$type}_points", 10.0, 5.0, 20.0 );
        $spacing = $this->get_clamped_slider_value( $settings, "synq_bg_{$type}_spacing", 15.0, 5.0, 50.0 );

        $base_config['provider'] = [
            'lineColor' => $line_color,
            'bgColor'   => $bg_color,
            'scale'     => $scale,
            'points'    => $points,
            'spacing'   => $spacing,
        ];

        return $base_config;
    }

    public function enqueue_scripts(): void {
        $urls = [
            'three'          => 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js',
            'p5'             => 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.1.9/p5.min.js',
            'vanta_topology' => 'https://cdn.jsdelivr.net/npm/vanta@0.5.24/dist/vanta.topology.min.js',
        ];

        /**
         * Filter CDN/local script URLs for the Vanta Topology provider.
         *
         * @param array $urls {
         *     @type string $three          URL for three.js.
         *     @type string $p5             URL for p5.js.
         *     @type string $vanta_topology URL for vanta topology build.
         * }
         */
        $urls = wp_parse_args(
            apply_filters( 'synq_ab_vanta_topology_script_urls', $urls ),
            $urls
        );

        wp_register_script(
            'synq-ab-threejs',
            $urls['three'],
            [],
            'r121',
            true
        );

        wp_register_script(
            'synq-ab-p5js',
            $urls['p5'],
            [],
            '1.1.9',
            true
        );

        wp_register_script(
            'synq-ab-vanta-topology',
            $urls['vanta_topology'],
            [ 'synq-ab-threejs', 'synq-ab-p5js' ],
            '0.5.24',
            true
        );

        wp_register_script(
            'synq-ab-provider-vanta-topology',
            SYNQ_AB_PLUGIN_URL . 'assets/js/provider-vanta-topology.js',
            [ 'synq-ab-core', 'synq-ab-vanta-topology' ],
            SYNQ_AB_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script( 'synq-ab-threejs' );
        wp_enqueue_script( 'synq-ab-p5js' );
        wp_enqueue_script( 'synq-ab-vanta-topology' );
        wp_enqueue_script( 'synq-ab-provider-vanta-topology' );
    }
}
