<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SYNQ_Bg_Provider_Vanta_Trunk implements SYNQ_Bg_Provider_Interface {

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
        return 'vanta_trunk';
    }

    public function get_label(): string {
        return __( 'Vanta – Trunk', 'synq-animated-backgrounds' );
    }

    public function register_controls( $element ): void {
        $type = $this->get_type();

        $element->add_control(
            "synq_bg_{$type}_color",
            [
                'label'     => __( 'Stroke Color', 'synq-animated-backgrounds' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#98465f',
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
                'default'   => '#222426',
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
            "synq_bg_{$type}_spacing",
            [
                'label'      => __( 'Spacing', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 0,
                        'max'  => 30,
                        'step' => 1,
                    ],
                ],
                'default'    => [
                    'size' => 0,
                ],
                'condition'  => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        $element->add_control(
            "synq_bg_{$type}_chaos",
            [
                'label'      => __( 'Chaos', 'synq-animated-backgrounds' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '' ],
                'range'      => [
                    '' => [
                        'min'  => 0,
                        'max'  => 2,
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
    }

    public function normalize_config( array $settings, array $base_config ): array {
        $type = $this->get_type();

        $color = isset( $settings[ "synq_bg_{$type}_color" ] )
            ? sanitize_hex_color( (string) $settings[ "synq_bg_{$type}_color" ] )
            : '';
        if ( empty( $color ) ) {
            $color = '#98465f';
        }

        $bg_color = isset( $settings[ "synq_bg_{$type}_bg_color" ] )
            ? sanitize_hex_color( (string) $settings[ "synq_bg_{$type}_bg_color" ] )
            : '';
        if ( empty( $bg_color ) ) {
            $bg_color = '#222426';
        }

        $scale   = $this->get_clamped_slider_value( $settings, "synq_bg_{$type}_scale", 1.0, 0.5, 2.0 );
        $spacing = $this->get_clamped_slider_value( $settings, "synq_bg_{$type}_spacing", 0.0, 0.0, 30.0 );
        $chaos   = $this->get_clamped_slider_value( $settings, "synq_bg_{$type}_chaos", 1.0, 0.0, 2.0 );

        $base_config['provider'] = [
            'color'   => $color,
            'bgColor' => $bg_color,
            'scale'   => $scale,
            'spacing' => $spacing,
            'chaos'   => $chaos,
        ];

        return $base_config;
    }

    public function enqueue_scripts(): void {
        $urls = [
            'p5'          => 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.1.9/p5.min.js',
            'vanta_trunk' => 'https://cdn.jsdelivr.net/npm/vanta@0.5.24/dist/vanta.trunk.min.js',
        ];

        /**
         * Filter CDN/local script URLs for the Vanta Trunk provider.
         *
         * @param array $urls {
         *     @type string $p5          URL for p5.js.
         *     @type string $vanta_trunk URL for vanta trunk build.
         * }
         */
        $urls = wp_parse_args(
            apply_filters( 'synq_ab_vanta_trunk_script_urls', $urls ),
            $urls
        );

        wp_register_script(
            'synq-ab-p5js',
            $urls['p5'],
            [],
            '1.1.9',
            true
        );

        wp_register_script(
            'synq-ab-vanta-trunk',
            $urls['vanta_trunk'],
            [ 'synq-ab-p5js' ],
            '0.5.24',
            true
        );

        wp_register_script(
            'synq-ab-provider-vanta-trunk',
            SYNQ_AB_PLUGIN_URL . 'assets/js/provider-vanta-trunk.js',
            [ 'synq-ab-core', 'synq-ab-vanta-trunk' ],
            SYNQ_AB_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script( 'synq-ab-p5js' );
        wp_enqueue_script( 'synq-ab-vanta-trunk' );
        wp_enqueue_script( 'synq-ab-provider-vanta-trunk' );
    }
}
