<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SYNQ_AB_GitHub_Updater {

    /**
     * @var string
     */
    protected $plugin_file;

    /**
     * @var string
     */
    protected $plugin_basename;

    /**
     * @var string
     */
    protected $plugin_slug;

    /**
     * @var string
     */
    protected $plugin_version;

    /**
     * @var string
     */
    protected $repository;

    /**
     * @var string
     */
    protected $github_token;

    /**
     * @var int
     */
    protected $cache_ttl;

    /**
     * @var string
     */
    protected $cache_key;

    /**
     * @var string
     */
    protected $api_endpoint;

    /**
     * @param string $plugin_file
     * @param string $plugin_version
     */
    public function __construct( $plugin_file, $plugin_version ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->plugin_slug     = dirname( $this->plugin_basename );
        $this->plugin_version  = (string) $plugin_version;
        $this->repository      = trim(
            (string) apply_filters(
                'synq_ab_github_repository',
                defined( 'SYNQ_AB_GITHUB_REPOSITORY' ) ? SYNQ_AB_GITHUB_REPOSITORY : ''
            )
        );
        $this->github_token    = trim( (string) apply_filters( 'synq_ab_github_token', '' ) );
        $this->cache_ttl       = max( 300, (int) apply_filters( 'synq_ab_github_cache_ttl', HOUR_IN_SECONDS ) );
        $this->cache_key       = 'synq_ab_github_release_' . md5( $this->repository );
        $this->api_endpoint    = 'https://api.github.com/repos/' . $this->repository . '/releases/latest';

        if ( ! $this->is_valid_repository() ) {
            return;
        }

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
        add_filter( 'plugins_api', [ $this, 'inject_plugin_information' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'normalize_source_directory' ], 10, 4 );
        add_filter( 'http_request_args', [ $this, 'inject_github_auth_headers' ], 10, 2 );
        add_action( 'upgrader_process_complete', [ $this, 'purge_cached_release' ], 10, 2 );
    }

    /**
     * @return bool
     */
    protected function is_valid_repository() {
        return 1 === preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $this->repository );
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    protected function ends_with( $haystack, $needle ) {
        if ( '' === $needle ) {
            return true;
        }

        $needle_length = strlen( $needle );
        if ( $needle_length > strlen( $haystack ) ) {
            return false;
        }

        return substr( $haystack, -$needle_length ) === $needle;
    }

    /**
     * @return array<string,string>
     */
    protected function get_github_headers() {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'SYNQ-Animated-Backgrounds/' . $this->plugin_version . '; ' . home_url( '/' ),
        ];

        if ( '' !== $this->github_token ) {
            $headers['Authorization'] = 'Bearer ' . $this->github_token;
        }

        return $headers;
    }

    /**
     * @param bool $force_refresh
     * @return array<string,string>|null
     */
    protected function get_latest_release( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( $this->cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            $this->api_endpoint,
            [
                'timeout' => 15,
                'headers' => $this->get_github_headers(),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $response_code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return null;
        }

        $payload = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $payload ) ) {
            return null;
        }

        $tag_name = isset( $payload['tag_name'] ) ? trim( (string) $payload['tag_name'] ) : '';
        if ( '' === $tag_name ) {
            return null;
        }

        $version = ltrim( $tag_name, "vV \t\n\r\0\x0B" );
        if ( '' === $version ) {
            return null;
        }

        $package = '';
        if ( ! empty( $payload['assets'] ) && is_array( $payload['assets'] ) ) {
            foreach ( $payload['assets'] as $asset ) {
                if ( ! is_array( $asset ) ) {
                    continue;
                }

                $asset_name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
                $asset_url  = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
                if ( '' === $asset_name || '' === $asset_url || ! $this->ends_with( $asset_name, '.zip' ) ) {
                    continue;
                }

                $package = $asset_url;
                if ( $asset_name === $this->plugin_slug . '.zip' ) {
                    break;
                }
            }
        }

        if ( '' === $package && ! empty( $payload['zipball_url'] ) ) {
            $package = (string) $payload['zipball_url'];
        }

        if ( '' === $package ) {
            return null;
        }

        $release = [
            'version'     => $version,
            'package'     => esc_url_raw( $package ),
            'html_url'    => ! empty( $payload['html_url'] ) ? esc_url_raw( (string) $payload['html_url'] ) : '',
            'changelog'   => ! empty( $payload['body'] ) ? (string) $payload['body'] : '',
            'published_at'=> ! empty( $payload['published_at'] ) ? (string) $payload['published_at'] : '',
        ];

        set_transient( $this->cache_key, $release, $this->cache_ttl );

        return $release;
    }

    /**
     * @param stdClass|mixed $transient
     * @return stdClass|mixed
     */
    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
            return $transient;
        }

        if ( ! isset( $transient->checked[ $this->plugin_basename ] ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! is_array( $release ) || empty( $release['version'] ) || empty( $release['package'] ) ) {
            return $transient;
        }

        if ( version_compare( $release['version'], $this->plugin_version, '<=' ) ) {
            return $transient;
        }

        $transient->response[ $this->plugin_basename ] = (object) [
            'slug'        => $this->plugin_slug,
            'plugin'      => $this->plugin_basename,
            'new_version' => $release['version'],
            'package'     => $release['package'],
            'url'         => ! empty( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . $this->repository,
            'requires'    => defined( 'SYNQ_AB_MIN_WP_VERSION' ) ? SYNQ_AB_MIN_WP_VERSION : '',
            'requires_php'=> defined( 'SYNQ_AB_MIN_PHP_VERSION' ) ? SYNQ_AB_MIN_PHP_VERSION : '',
            'tested'      => '6.9.4',
        ];

        return $transient;
    }

    /**
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object|array
     */
    public function inject_plugin_information( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) ) {
            return $result;
        }

        if ( $this->plugin_slug !== $args->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! is_array( $release ) || empty( $release['version'] ) ) {
            return $result;
        }

        $changelog = ! empty( $release['changelog'] )
            ? '<pre style="white-space:pre-wrap;">' . esc_html( $release['changelog'] ) . '</pre>'
            : esc_html__( 'No changelog notes were found in the latest GitHub release.', 'synq-animated-backgrounds' );

        return (object) [
            'name'          => 'SYNQ Animated Backgrounds for Elementor',
            'slug'          => $this->plugin_slug,
            'version'       => $release['version'],
            'author'        => '<a href="https://github.com/' . esc_attr( strtok( $this->repository, '/' ) ) . '">SYNQ Group</a>',
            'homepage'      => ! empty( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . $this->repository,
            'requires'      => defined( 'SYNQ_AB_MIN_WP_VERSION' ) ? SYNQ_AB_MIN_WP_VERSION : '',
            'requires_php'  => defined( 'SYNQ_AB_MIN_PHP_VERSION' ) ? SYNQ_AB_MIN_PHP_VERSION : '',
            'tested'        => '6.9.4',
            'download_link' => ! empty( $release['package'] ) ? $release['package'] : '',
            'sections'      => [
                'description' => esc_html__(
                    'Automatic updates are served from GitHub Releases for this plugin.',
                    'synq-animated-backgrounds'
                ),
                'changelog'   => $changelog,
            ],
        ];
    }

    /**
     * Ensure source ZIP extracts into the plugin directory name.
     *
     * @param string $source
     * @param string $remote_source
     * @param object $upgrader
     * @param array  $hook_extra
     * @return string|WP_Error
     */
    public function normalize_source_directory( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
            return $source;
        }

        if ( ! is_dir( $source ) ) {
            return $source;
        }

        $target = trailingslashit( $remote_source ) . basename( dirname( $this->plugin_basename ) );
        if ( untrailingslashit( $source ) === untrailingslashit( $target ) ) {
            return $source;
        }

        global $wp_filesystem;

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if ( ! $wp_filesystem ) {
            WP_Filesystem();
        }

        if ( $wp_filesystem ) {
            if ( $wp_filesystem->is_dir( $target ) ) {
                $wp_filesystem->delete( $target, true );
            }

            if ( $wp_filesystem->move( $source, $target ) ) {
                return $target;
            }
        }

        if ( @rename( $source, $target ) ) {
            return $target;
        }

        return new WP_Error(
            'synq_ab_github_update_directory_mismatch',
            esc_html__(
                'Plugin update was aborted because the downloaded package could not be mapped to the plugin directory.',
                'synq-animated-backgrounds'
            )
        );
    }

    /**
     * Add auth headers for private repository access when configured.
     *
     * @param array  $args
     * @param string $url
     * @return array
     */
    public function inject_github_auth_headers( $args, $url ) {
        if ( '' === $this->github_token ) {
            return $args;
        }

        $repo_api_prefix      = 'https://api.github.com/repos/' . $this->repository . '/';
        $repo_archive_prefix  = 'https://github.com/' . $this->repository . '/';
        $repo_codeload_prefix = 'https://codeload.github.com/' . $this->repository . '/';

        $matches_repo = 0 === strpos( $url, $repo_api_prefix )
            || 0 === strpos( $url, $repo_archive_prefix )
            || 0 === strpos( $url, $repo_codeload_prefix );

        if ( ! $matches_repo ) {
            return $args;
        }

        if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
            $args['headers'] = [];
        }

        $args['headers']['Authorization'] = 'Bearer ' . $this->github_token;
        $args['headers']['User-Agent']    = 'SYNQ-Animated-Backgrounds/' . $this->plugin_version . '; ' . home_url( '/' );

        return $args;
    }

    /**
     * @param object $upgrader
     * @param array  $options
     */
    public function purge_cached_release( $upgrader, $options ) {
        if ( empty( $options['action'] ) || empty( $options['type'] ) ) {
            return;
        }

        if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
            return;
        }

        if ( empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
            return;
        }

        if ( ! in_array( $this->plugin_basename, $options['plugins'], true ) ) {
            return;
        }

        delete_transient( $this->cache_key );
    }
}
