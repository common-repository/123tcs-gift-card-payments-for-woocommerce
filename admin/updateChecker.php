<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../includes/settings.php';

class UpdateChecker
{
	public $plugin_slug;
	public $basename;
	public $version;
	public $cache_key;
	public $cache_allowed;

	public function __construct($slug, $basename, $version)
	{
		$this->plugin_slug = $slug;
		$this->basename = $basename;
		$this->version = $version;
		$this->cache_key = 'tcs_gcpfwc_custom_upd';
		$this->cache_allowed = false;

		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
		add_action( 'in_plugin_update_message-123tcs-gift-card-payments-for-woocommerce/tcs-gift-card-payments-for-woocommerce.php', array( $this, 'update_message' ), 10, 2 );
        add_filter( 'plugin_action_links_123tcs-gift-card-payments-for-woocommerce/tcs-gift-card-payments-for-woocommerce.php', array( $this, 'settings_link' ) );
	}

	public function request()
	{
		$remote = get_transient( $this->cache_key );

		if( false === $remote || ! $this->cache_allowed )
		{
		    $settings = new \TCS_GCP\Settings();
        
            // Get Merchant Id and Secret Key is for active mode
            $test_mode = $settings->is_testmode_active();
            $key = $test_mode ? $settings->get_test_merchant_id() : $settings->get_live_merchant_id();

			$remote = wp_remote_get( 
				add_query_arg( 
					array(
						'license_key' => urlencode( $key )
					), 
					'https://wpextensio.com/wp-content/uploads/123TCS-GCPFWC-updater/info.php'
				), 
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);

			if(
				is_wp_error( $remote )
				|| 200 !== wp_remote_retrieve_response_code( $remote )
				|| empty( wp_remote_retrieve_body( $remote ) )
			) 
			{
				return false;
			}

			set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );

		}

		$remote = json_decode( wp_remote_retrieve_body( $remote ) );

		return $remote;

	}


	function info( $res, $action, $args ) {

		// print_r( $action );
		// print_r( $args );

		// do nothing if you're not getting plugin information right now
		if( 'plugin_information' !== $action )
		{
			return false;
		}

		// do nothing if it is not our plugin
		if( $this->plugin_slug !== $args->slug )
		{
			return false;
		}

		// get updates
		$remote = $this->request();

		if( ! $remote )
		{
			return false;
		}

		$res = new \stdClass();
		

		$res->name = $remote->name;
		$res->slug = $remote->slug;
		$res->version = $remote->version;
		$res->tested = $remote->tested;
		$res->requires = $remote->requires;
		$res->author = $remote->author;
		$res->author_profile = $remote->author_profile;
		$res->download_link = $remote->download_url;
		$res->trunk = $remote->download_url;
		$res->requires_php = $remote->requires_php;
		$res->last_updated = $remote->last_updated;
		$object = $remote->contributors;
		$res->contributors = json_decode(json_encode($object), true);
		$res->sections = array(
			'description' => $remote->sections->description,
			'installation' => $remote->sections->installation,
			'changelog' => $remote->sections->changelog
		);
		
		if( ! empty( $remote->sections->screenshots ) )
		{
		    $res->sections[ 'screenshots' ] = $remote->sections->screenshots;
	    }

		if( ! empty( $remote->banners ) ) {
			$res->banners = array(
				'low' => $remote->banners->low,
				'high' => $remote->banners->high
			);
		}

		return $res;

	}

	public function update( $transient )
	{
		if ( empty($transient->checked ) )
		{
			return $transient;
		}

		$remote = $this->request();

		if(
			$remote
			&& version_compare( $this->version, $remote->version, '<' )
			&& version_compare( $remote->requires, get_bloginfo( 'version' ), '<' )
			&& version_compare( $remote->requires_php, PHP_VERSION, '<' )
		)
		{
			$res = new \stdClass();
			$res->slug = $this->plugin_slug;
			$res->plugin = $this->basename;
			$res->new_version = $remote->version;
			$res->tested = $remote->tested;
			$res->package = $remote->download_url;

			$transient->response[ $res->plugin ] = $res;
        }

		return $transient;
	}

	public function purge()
	{
		if (
			$this->cache_allowed
			&& 'update' === $options['action']
			&& 'plugin' === $options[ 'type' ]
		)
		{
			// just clean the cache when new plugin version is installed
			delete_transient( $this->cache_key );
		}

	}
	
	public function update_message( $plugin_info_array, $plugin_info_object )
	{
	    if( empty( $plugin_info_array[ 'package' ] ) )
	    {
	        _e(' Please <a href="/wp-admin/admin.php?page=tcs-gcp-settings-page">set your Merchant ID</a> to update.</a>', 'tcs-gift-card-payments-for-woocommerce');
	    }
    }
    
    public function settings_link( $links )
    {
	    // Build and escape the URL.
    	$url = esc_url( add_query_arg(
    		'page',
    		'tcs-gcp-settings-page',
    		get_admin_url() . 'admin.php'
    	) );
    	
    	// Create the link.
    	$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
    	
    	// Adds the link to the end of the array.
    	array_push(
    		$links,
    		$settings_link
    	);
    	
    	return $links;
    }
}
