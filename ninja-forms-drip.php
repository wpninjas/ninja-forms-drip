<?php if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Plugin Name: Ninja Forms - Drip
 * Plugin URI:
 * Description:
 * Version: 3.0.0
 * Author:
 * Author URI:
 * Text Domain: ninja-forms-drip
 *
 * Copyright 2017 .
 */

if( version_compare( get_option( 'ninja_forms_version', '0.0.0' ), '3', '<' ) || get_option( 'ninja_forms_load_deprecated', FALSE ) ) {

    //include 'deprecated/ninja-forms-drip.php';

} else {

    /**
     * Class NF_Drip
     */
    final class NF_Drip
    {
        const VERSION = '0.0.1';
        const SLUG    = 'drip';
        const NAME    = 'Drip';
        const AUTHOR  = '';
        const PREFIX  = 'NF_Drip';

        /**
         * @var NF_Drip
         * @since 3.0
         */
        private static $instance;

        /**
         * Plugin Directory
         *
         * @since 3.0
         * @var string $dir
         */
        public static $dir = '';

        /**
         * Plugin URL
         *
         * @since 3.0
         * @var string $url
         */
        public static $url = '';

        /**
         * Main Plugin Instance
         *
         * Insures that only one instance of a plugin class exists in memory at any one
         * time. Also prevents needing to define globals all over the place.
         *
         * @since 3.0
         * @static
         * @static var array $instance
         * @return NF_Drip Highlander Instance
         */
        public static function instance()
        {
            if (!isset(self::$instance) && !(self::$instance instanceof NF_Drip)) {
                self::$instance = new NF_Drip();

                self::$dir = plugin_dir_path(__FILE__);

                self::$url = plugin_dir_url(__FILE__);

                /*
                 * Register our autoloader
                 */
                spl_autoload_register(array(self::$instance, 'autoloader'));
            }

            return self::$instance;
        }

        public function __construct()
        {
            /*
             * Required for all Extensions.
             */
            add_action( 'admin_init', array( $this, 'setup_license') );

            add_filter( 'ninja_forms_register_actions', array($this, 'register_actions'));

            add_filter( 'ninja_forms_plugin_settings', array( $this, 'plugin_settings' ), 10, 1 );
            add_filter( 'ninja_forms_plugin_settings_groups', array( $this, 'plugin_settings_groups' ), 10, 1 );

            add_action( 'init', array( $this, 'custom_rewrite_basic' ) );
            add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
            add_filter( 'parse_request', array( $this, 'parse_request' ), 100 );

            add_action( 'ninja_forms_save_setting_drip_secret',   array( $this, 'save_drip_secret' ), 10, 1 );

            add_action( 'admin_post_ninja_forms_drip_authorize', array( $this, 'authorize_drip' ) );
            add_action( 'admin_post_ninja_forms_drip_deauthorize', array( $this, 'deauthorize_drip' ) );

        }

        public static function has_token()
        {
          $token = Ninja_Forms()->get_setting('drip_token');
          return ! $token || empty( $token ) ? false : true;
        }

        public static function remote_post( $url, $body )
        {

          $patterns = array();
      		$patterns[0] = "/{:account_id}/";
      		$replacements = array();
      		$replacements[0] = self::get_drip_account();
      		$url = preg_replace( $patterns, $replacements, $url );

          return wp_remote_post( $url, array(
            'body' => json_encode( $body ),
            'headers' => array(
              'Content-Type' => 'application/json',
              'Authorization' => 'Bearer ' . Ninja_Forms()->get_setting('drip_token')
              )
            )
          );
        }

        public static function remote_get( $url )
        {
          return wp_remote_get( $url, array(
            'headers' => array(
              'Authorization' => 'Bearer ' . Ninja_Forms()->get_setting('drip_token')
              )
            )
          );
        }

        function custom_rewrite_basic()
        {
          add_rewrite_rule('^ninja-forms-drip-authorize/', 'index.php?ninja_forms_drip_authorize=true', 'top');
        }

        function parse_request()
        {
          global $wp_query;
          if( ! is_user_logged_in() )
          if( ! isset( $wp_query->query['ninja-forms-drip-authorize'] ) ) return;
          if( isset( $_GET['code'] ) ) do_action( 'admin_post_ninja_forms_drip_authorize' );
        }

        function add_query_vars($vars){
          $vars[] = "ninja-forms-drip-authorize";
          return $vars;
        }

        public static function get_drip_account( $email = false, $url = false )
        {

          $drip_account = Ninja_Forms()->get_setting('drip_account');
          $drip_account_json = Ninja_Forms()->get_setting('drip_account_json');

          if( empty( $drip_account ) ){
            $response = self::remote_get( 'http://api.getdrip.com/v2/accounts/' );

            if ( ! is_wp_error( $response ) ) {
              $accounts = json_decode( wp_remote_retrieve_body( $response ) );
              if( $accounts !== false && ! empty( $accounts ) && isset( $accounts->accounts )  && is_array( $accounts->accounts ) ){
                $drip_account = $accounts->accounts[0]->id;
                $drip_account_json = $accounts->accounts[0];
                Ninja_Forms()->update_setting('drip_account_json', $accounts->accounts[0]);
                Ninja_Forms()->update_setting('drip_account', $drip_account);
              }
            }
          }

          if( $email ){
            $drip_account = $drip_account_json->primary_email;
          }

          if( $url ){
            $drip_account = $drip_account_json->url;
          }

          return $drip_account;

        }

        public function deauthorize_drip()
        {
          Ninja_Forms()->update_setting( 'drip_token', '' );
          Ninja_Forms()->update_setting( 'drip_client_id', '' );
          Ninja_Forms()->update_setting( 'drip_secret', '' );

          wp_safe_redirect( add_query_arg( array(
            'page' => 'nf-settings'
          ), admin_url( '/admin.php' ) ) );
          exit;
        }

        public function authorize_drip(){

          if( ! isset( $_GET['code'] ) || empty( $_GET['code'] ) ){
            wp_safe_redirect( add_query_arg( array(
              'page' => 'nf-settings'
            ), admin_url( '/admin.php' ) ) );
            exit;

          }

          if( in_array( $_GET['code'], array( 'error', 'error_reason', 'error_description' ) ) ){
            wp_safe_redirect( add_query_arg( array(
              'page' => 'nf-settings'
            ), admin_url( '/admin.php' ) ) );
            exit;

          }

          $args = array(
            'client_id' => Ninja_Forms()->get_setting('drip_client_id'),
            'client_secret' => Ninja_Forms()->get_setting('drip_secret'),
            'grant_type' => 'authorization_code',
            'response_type' => 'token',
            'redirect_uri' => self::get_drip_redirect_url(),
            'code' => esc_attr( $_GET['code'] )
          );

          $response = wp_remote_post( add_query_arg( $args, 'https://www.getdrip.com/oauth/token' ) );

          $body = json_decode( wp_remote_retrieve_body( $response ) );

          if( isset( $body->access_token ) && ! empty( $body->access_token ) ){
            Ninja_Forms()->update_setting('drip_token', $body->access_token );
          }

          wp_safe_redirect( add_query_arg( array(
            'page' => 'nf-settings',
          ), admin_url( '/admin.php' ) ) );
          exit;

        }

        public static function get_drip_redirect_url()
        {
          return esc_url_raw( site_url('/ninja-forms-drip-authorize/' ) );
        }

        public function save_drip_secret( $drip_secret )
        {
            $token = Ninja_Forms()->get_setting('drip_token');

            if( empty( $token ) ){

              $client_id = Ninja_Forms()->get_setting('drip_client_id');

              if( count( $client_id ) === 64 && count( $drip_secret ) === 64 ){

                wp_redirect( add_query_arg( array(
                  'response_type' => 'code',
                  'client_id' => $client_id,
                  'redirect_uri' => urlencode( self::get_drip_redirect_url() )
                ), 'https://www.getdrip.com/oauth/authorize' ) );
              }

            }

        }


        public function plugin_settings_groups( $groups )
        {
            $groups[ 'drip' ] = array(
                'id' => 'drip',
                'label' => __( 'Drip API Login', 'ninja-forms-drip' ),
            );
            return $groups;
        }


        public function plugin_settings( $settings )
        {
          $settings[ 'drip' ] = array();

          if( $this->has_token() ){
            $settings[ 'drip' ]['drip_token'] = array(
                'id'    => 'drip_token',
                'type'  => 'html',
                'label'  => __( 'Drip API Status', 'ninja-forms-drip' ),
                'html' => sprintf( '<p>%s <strong>%s</strong><br> %s %s</p><br>', __( 'Connected as', 'ninja-forms-drip' ), self::get_drip_account( true ), __( 'for account', 'ninja-forms-drip' ), self::get_drip_account( false, true ) ). '<a href="' . add_query_arg( array( 'action' => 'ninja_forms_drip_deauthorize' ), admin_url( '/admin-post.php' ) ) . '" class="button">' . __( 'Disconnect Drip Account', 'ninja-forms-drip' ) . '</a>',
            );
          }

          else

          {
            $settings[ 'drip' ]['drip_redirect'] = array(
                'id'    => 'drip_redirect',
                'type'  => 'desc',
                'label'  => __( 'Drip Callback URL', 'ninja-forms-drip' ),
                'desc'  => self::get_drip_redirect_url(),
            );
            $settings[ 'drip' ]['drip_client_id'] = array(
                'id'    => 'drip_client_id',
                'type'  => 'textbox',
                'label'  => __( 'Drip CLIENT ID', 'ninja-forms-drip' )
            );
            $settings[ 'drip' ]['drip_secret'] = array(
                'id'    => 'drip_secret',
                'type'  => 'textbox',
                'label'  => __( 'Drip SECRET', 'ninja-forms-drip' )
            );
          }

          return $settings;
        }


        /**
         * Optional. If your extension processes or alters form submission data on a per form basis...
         */
        public function register_actions($actions)
        {
            if( $this->has_token() ){
              $actions[ 'drip' ] = new NF_Drip_Actions_DripPostSubscriber();
            }

            return $actions;
        }

        /*
         * Optional methods for convenience.
         */

        public function autoloader($class_name)
        {
            if (class_exists($class_name)) return;

            if ( false === strpos( $class_name, self::PREFIX ) ) return;

            $class_name = str_replace( self::PREFIX, '', $class_name );
            $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
            $class_file = str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';

            if (file_exists($classes_dir . $class_file)) {
                require_once $classes_dir . $class_file;
            }
        }

        /**
         * Template
         *
         * @param string $file_name
         * @param array $data
         */
        public static function template( $file_name = '', array $data = array() )
        {
            if( ! $file_name ) return;

            extract( $data );

            include self::$dir . 'includes/Templates/' . $file_name;
        }

        /**
         * Config
         *
         * @param $file_name
         * @return mixed
         */
        public static function config( $file_name )
        {
            return include self::$dir . 'includes/Config/' . $file_name . '.php';
        }

        /*
         * Required methods for all extension.
         */

        public function setup_license()
        {
        //  var_dump( get_option('ninja-drip-custom_fields') );
            if ( ! class_exists( 'NF_Extension_Updater' ) ) return;
            new NF_Extension_Updater( self::NAME, self::VERSION, self::AUTHOR, __FILE__, self::SLUG );
        }
    }

    /**
     * The main function responsible for returning The Highlander Plugin
     * Instance to functions everywhere.
     *
     * Use this function like you would a global variable, except without needing
     * to declare the global.
     *
     * @since 3.0
     * @return {class} Highlander Instance
     */
    function NF_Drip()
    {
        return NF_Drip::instance();
    }

    NF_Drip();
}
