<?php

if (! defined('ABSPATH')) {
    exit;
}

class SYNQ_Bg_Provider_Vanta_Topology implements SYNQ_Bg_Provider_Interface
{

    public function get_type(): string
    {
        return 'vanta_topology';
    }

    public function get_label(): string
    {
        return __('Vanta – Topology', 'synq-animated-backgrounds');
    }

    public function register_controls($element): void
    {
        $type = $this->get_type();

        // Line color
        $element->add_control(
            "synq_bg_{$type}_line_color",
            [
                'label'     => __('Line Color', 'synq-animated-backgrounds'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#2a2a2a',
                'condition' => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        // Background color
        $element->add_control(
            "synq_bg_{$type}_bg_color",
            [
                'label'     => __('Background Color', 'synq-animated-backgrounds'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#000000',
                'condition' => [
                    'synq_bg_anim_enable' => 'yes',
                    'synq_bg_anim_type'   => $type,
                ],
            ]
        );

        // Scale
        $element->add_control(
            "synq_bg_{$type}_scale",
            [
                'label'      => __('Scale', 'synq-animated-backgrounds'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [''],
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

        // Points (density)
        $element->add_control(
            "synq_bg_{$type}_points",
            [
                'label'      => __('Points (Density)', 'synq-animated-backgrounds'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [''],
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

        // Spacing
        $element->add_control(
            "synq_bg_{$type}_spacing",
            [
                'label'      => __('Spacing', 'synq-animated-backgrounds'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [''],
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

    	$line_color = ! empty( $settings[ "synq_bg_{$type}_line_color" ] )
        	? $settings[ "synq_bg_{$type}_line_color" ]
        	: '#2a2a2a';

    	$bg_color = ! empty( $settings[ "synq_bg_{$type}_bg_color" ] )
        	? $settings[ "synq_bg_{$type}_bg_color" ]
        	: '#000000';

    	$scale = ! empty( $settings[ "synq_bg_{$type}_scale" ]['size'] )
        	? (float) $settings[ "synq_bg_{$type}_scale" ]['size']
        	: 1.0;

    	$points = ! empty( $settings[ "synq_bg_{$type}_points" ]['size'] )
        	? (float) $settings[ "synq_bg_{$type}_points" ]['size']
        	: 10.0;

    	$spacing = ! empty( $settings[ "synq_bg_{$type}_spacing" ]['size'] )
        	? (float) $settings[ "synq_bg_{$type}_spacing" ]['size']
        	: 15.0;

    	$base_config['provider'] = [
        	'lineColor' => $line_color,
        	'bgColor'   => $bg_color,
        	'scale'     => $scale,
        	'points'    => $points,
        	'spacing'   => $spacing,
    	];

    	return $base_config;
	}

    public function enqueue_scripts(): void
    {
        // External dependencies for Vanta Topology
        wp_enqueue_script(
            'synq-ab-threejs',
            'https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'synq-ab-p5js',
            'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.1.9/p5.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'synq-ab-vanta-topology',
            'https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.topology.min.js',
            ['synq-ab-threejs', 'synq-ab-p5js'],
            null,
            true
        );

        // Provider-side JS that plugs into the core registry
        wp_enqueue_script(
            'synq-ab-provider-vanta-topology',
            SYNQ_AB_PLUGIN_URL . 'assets/js/provider-vanta-topology.js',
            ['synq-ab-core', 'synq-ab-vanta-topology'],
            SYNQ_AB_PLUGIN_VERSION,
            true
        );
    }
}