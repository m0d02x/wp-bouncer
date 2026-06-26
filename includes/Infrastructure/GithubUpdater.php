<?php

namespace Bouncer\WooCommerce\WhatsApp\Infrastructure;

/**
 * Self-contained GitHub release updater.
 *
 * Hooks into WordPress's native plugin update system so updates appear on the
 * Plugins screen the same way WP.org plugins do. Pulls release ZIPs from the
 * GitHub releases API and installs them via the standard WP upgrader.
 *
 * No external plugins required. Works for private repos if an access token is
 * configured via the `bouncer_github_token` filter or WP option.
 */
class GithubUpdater {

	/** @var string GitHub owner/repo, e.g. "m0d02x/wp-bouncer". */
	private string $repo;

	/** @var string Plugin slug: folder/main-file.php. */
	private string $plugin_slug;

	/** @var string Current installed version. */
	private string $version;

	/** @var string|null Cached release payload (raw API response body). */
	private $release_cache = null;

	/** @var array|null Parsed release data. */
	private $release_data_cache = null;

	public function __construct( string $repo, string $plugin_file, string $version ) {
		$this->repo        = $repo;
		$this->plugin_slug = plugin_basename( $plugin_file );
		$this->version     = $version;
	}

	/**
	 * Register WordPress hooks for the update lifecycle.
	 */
	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'rename_install_folder' ], 10, 3 );
	}

	/**
	 * Clear the cached release data so the next check hits GitHub fresh.
	 */
	public function clear_cache(): void {
		$cache_key = 'bouncer_github_release_' . md5( $this->repo );
		delete_transient( $cache_key );
		$this->release_data_cache = null;
	}

	/**
	 * Get the latest release info (public wrapper for admin UI display).
	 *
	 * @return array{version:string,download_url:string,info_url:string,published_at:string,body:string}|null
	 */
	public function get_release_info(): ?array {
		return $this->get_latest_release();
	}

	/**
	 * Check the latest GitHub release and inject an update object if a newer
	 * version is available. Runs on the WP update transient filter.
	 *
	 * @param mixed $transient
	 * @return mixed
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		// Only check once per transient refresh.
		if ( ! isset( $transient->checked[ $this->plugin_slug ] ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $release['version'];

		if ( ! $this->is_newer( $remote_version, $this->version ) ) {
			return $transient;
		}

		$obj                        = new \stdClass();
		$obj->slug                  = dirname( $this->plugin_slug );
		$obj->plugin                = $this->plugin_slug;
		$obj->new_version           = $remote_version;
		$obj->package               = $release['download_url'];
		$obj->url                   = $release['info_url'];
		$obj->requires_php          = WC_BOUNCER_WHATSAPP_MIN_PHP;
		$obj->tested                = get_bloginfo( 'version' );

		$transient->response[ $this->plugin_slug ] = $obj;

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" / "View version X details" popup.
	 *
	 * @param mixed  $result
	 * @param string $action
	 * @param object $args
	 * @return mixed
	 */
	public function plugin_info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$info = new \stdClass();
		$info->name               = 'WooCommerce Bouncer WhatsApp';
		$info->slug               = dirname( $this->plugin_slug );
		$info->version            = $release['version'];
		$info->author             = '<a href="https://bouncer.my">Bouncer</a>';
		$info->homepage           = $release['info_url'];
		$info->requires           = '6.0';
		$info->tested             = get_bloginfo( 'version' );
		$info->requires_php       = WC_BOUNCER_WHATSAPP_MIN_PHP;
		$info->download_link      = $release['download_url'];
		$info->last_updated       = $release['published_at'];
		$info->sections           = [
			'description'  => 'Automates WhatsApp notifications for WooCommerce orders and abandoned carts using the Bouncer API.',
			'release_notes' => $release['body'],
		];
		$info->banners['low']     = '';

		return $info;
	}

	/**
	 * Ensure the unpacked plugin folder matches the registered slug. GitHub
	 * release ZIPs often extract to a top-level folder that differs from the
	 * installed one — this prevents the plugin from being moved to a wrong
	 * path during upgrade.
	 *
	 * @param bool  $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public function rename_install_folder( bool $response, array $hook_extra, array $result ): array {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $result;
		}

		if ( empty( $result['destination'] ) || ! $wp_filesystem ) {
			return $result;
		}

		$installed_folder = trailingslashit( $result['local_destination'] ) . dirname( $this->plugin_slug );

		// Move the extracted folder to the expected location.
		$wp_filesystem->move( $result['destination'], $installed_folder );

		$result['destination'] = $installed_folder;

		return $result;
	}

	/**
	 * Fetch and parse the latest GitHub release. Cached for 12 hours via
	 * transients to avoid hammering the GitHub API on every admin page load.
	 *
	 * @return array{version:string,download_url:string,info_url:string,published_at:string,body:string}|null
	 */
	private function get_latest_release(): ?array {
		if ( null !== $this->release_data_cache ) {
			return $this->release_data_cache;
		}

		$cache_key = 'bouncer_github_release_' . md5( $this->repo );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			$this->release_data_cache = $cached;
			return $cached;
		}

		$api_url = sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo );
		$token   = $this->get_access_token();

		$headers = [ 'Accept' => 'application/vnd.github+json' ];
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get(
			$api_url,
			[
				'headers' => $headers,
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}

		$version  = ltrim( (string) $data['tag_name'], 'vV' );
		$asset    = $this->pick_asset( $data['assets'] ?? [] );
		$zip_url  = $asset ?: (string) ( $data['zipball_url'] ?? '' );

		if ( '' === $zip_url ) {
			return null;
		}

		$parsed = [
			'version'      => $version,
			'download_url' => $zip_url,
			'info_url'     => (string) ( $data['html_url'] ?? '' ),
			'published_at' => (string) ( $data['published_at'] ?? '' ),
			'body'         => (string) ( $data['body'] ?? '' ),
		];

		set_transient( $cache_key, $parsed, 12 * HOUR_IN_SECONDS );
		$this->release_data_cache = $parsed;

		return $parsed;
	}

	/**
	 * Pick the best asset URL from a release. Prefer a release-attached asset
	 * whose filename contains the plugin slug, then any .zip asset, then fall
	 * back to the auto-generated zipball URL.
	 *
	 * @param array<int,array{name:string,browser_download_url:string}> $assets
	 * @return string
	 */
	private function pick_asset( array $assets ): string {
		$slug_hint = strtolower( dirname( $this->plugin_slug ) );

		foreach ( $assets as $asset ) {
			$url = (string) ( $asset['browser_download_url'] ?? '' );
			$name = strtolower( (string) ( $asset['name'] ?? '' ) );

			if ( '' !== $url && '' !== $name && false !== strpos( $name, '.zip' ) && false !== strpos( $name, $slug_hint ) ) {
				return $url;
			}
		}

		foreach ( $assets as $asset ) {
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			$name = strtolower( (string) ( $asset['name'] ?? '' ) );

			if ( '' !== $url && '' !== $name && false !== strpos( $name, '.zip' ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Compare semver-like versions. Returns true if $remote is strictly newer
	 * than $installed.
	 */
	private function is_newer( string $remote, string $installed ): bool {
		$remote    = ltrim( $remote, 'vV' );
		$installed = ltrim( $installed, 'vV' );

		return version_compare( $remote, $installed, '>' );
	}

	/**
	 * Get optional GitHub personal access token for private repos or to bypass
	 * anonymous rate limits. Configurable via filter or option.
	 */
	private function get_access_token(): string {
		/**
		 * Filter the GitHub access token used for release API requests.
		 * Return an empty string for public repos (60 req/hr anonymous limit).
		 *
		 * @param string $token
		 */
		$token = (string) apply_filters( 'bouncer_github_token', '' );

		if ( '' === $token ) {
			$token = (string) get_option( 'bouncer_github_token', '' );
		}

		return $token;
	}
}
