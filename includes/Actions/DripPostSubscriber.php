<?php if ( ! defined( 'ABSPATH' ) || ! class_exists( 'NF_Abstracts_Action' )) exit;

/**
 * Class NF_Drip_Actions_DripPostSubscriber
 */
final class NF_Drip_Actions_DripPostSubscriber extends NF_Abstracts_Action
{
    /**
     * @var string
     */
    protected $_name  = 'drip';

    /**
     * @var array
     */
    protected $_tags = array();

    /**
     * @var string
     */
    protected $_timing = 'late';

    /**
     * @var int
     */
    protected $_priority = '10';

    /**
     * Constructor
     */
    public function __construct()
{
    parent::__construct();

    $this->_nicename = __( 'POST Drip Subscriber', 'ninja-forms-drip' );

    /*
     * Settings
     */
    $this->_settings = array(
      'drip_subscriber' => array(
        'name' => 'drip_subscriber',
        'type' => 'textbox',
        'label' => __( 'Subscriber Email', 'ninja-forms-drip'),
        'width' => 'full',
        'group' => 'primary',
        'value' => '',
        'placeholder' => __( 'Choose Drip subscriber email', 'ninja-forms-drip' ),
        'help' => __( "Mandatory. The subscriber's email address.", 'ninja-forms-drip' ),
        'use_merge_tags' => true
      ),
      'drip_custom_fields' => array(
        'name' => 'drip_custom_fields',
        'type' => 'textarea',
        'label' => __( 'Custom Fields', 'ninja-forms-drip'),
        'width' => 'full',
        'group' => 'primary',
        'value' => '',
        'placeholder' => __( 'Choose custom fields.', 'ninja-forms-drip' ),
        'help' => __( 'Optional. An Object containing custom field data. E.g. { name: john doe }.', 'ninja-forms-drip' ),
        'use_merge_tags' => true
      ),
      'drip_tags' => array(
        'name' => 'drip_tags',
        'type' => 'textbox',
        'label' => __( 'Add Tags', 'ninja-forms-drip'),
        'width' => 'full',
        'group' => 'primary',
        'value' => '',
        'placeholder' => __( 'Comma separated list (ex: new, promotion ...)', 'ninja-forms-drip' ),
        'help' => __( 'Optional. A comma separated list containing one or more tags. E.g. (ex: new, promotion ...).', 'ninja-forms-drip' ),
        'use_merge_tags' => true
      ),
      'drip_remove_tags' => array(
        'name' => 'drip_tags_remove',
        'type' => 'textbox',
        'label' => __( 'Remove Tags', 'ninja-forms-drip'),
        'width' => 'full',
        'group' => 'primary',
        'value' => '',
        'placeholder' => __( 'Comma separated list (ex: new, promotion ...)', 'ninja-forms-drip' ),
        'help' => __( 'Optional. A comma separated list containing one or more tags. E.g. (ex: new, promotion ...).', 'ninja-forms-drip' ),
        'use_merge_tags' => true
      ),
      'drip_prospect' => array(
        'name' => 'drip_prospect',
        'type' => 'toggle',
        'label' => __( 'Prospect', 'ninja-forms-drip'),
        'width' => 'one-third',
        'group' => 'advanced',
        'value' => true,
        'help' => __( 'Optional. Specifiy whether we should attach a lead score to the subscriber (when lead scoring is enabled).', 'ninja-forms-drip' ),
        'use_merge_tags' => true
      ),
      'lifetime_value' => array(
        'name' => 'drip_lifetime_value',
        'type' => 'textbox',
        'label' => __( 'Lifetime Value', 'ninja-forms-drip'),
        'width' => 'one-third',
        'group' => 'advanced',
        'value' => '',
        'placeholder' => __( 'Enter value in cents', 'ninja-forms-drip' ),
        'help' => __( 'Optional. The lifetime value of the subscriber (in cents).', 'ninja-forms-drip' ),
        'use_merge_tags' => true
      ),
      'drip_base_lead_score' => array(
        'name' => 'drip_base_lead_score',
        'type' => 'textbox',
        'label' => __( 'Base Lead Score', 'ninja-forms-drip'),
        'width' => 'one-third',
        'group' => 'advanced',
        'value' => 30,
        'help' => __( 'Optional. An Integer specifying the starting value for lead score calculation for this subscriber. Defaults to 30.', 'ninja-forms-drip' ),
        'use_merge_tags' => true,
      ),
    );
}

    /*
    * PUBLIC METHODS
    */

    public function save( $action_settings )
    {

    }

    public function process( $action_settings, $form_id, $data )
    {

      if( ! is_email( $action_settings['drip_subscriber'] ) ) return $data;

      $tags = $remove_tags = $custom_fields = array();

      $user_tags = explode( ',', $action_settings['drip_tags'] );

      if( ! empty( $user_tags ) && is_array( $user_tags ) ){
        foreach( $user_tags as $key => $tag ){
          $tag = trim( $tag );
          if( ! empty( $tag ) )
          $tags[] = sanitize_text_field( $tag );
        }
      }

      $user_remove_tags = explode( ',', $action_settings['drip_remove_tags'] );

      if( ! empty( $user_remove_tags ) && is_array( $user_remove_tags ) ){
        foreach( $user_remove_tags as $key => $tag ){
          $tag = trim( $tag );
          if( ! empty( $tag ) )
          $remove_tags[] = sanitize_text_field($tag);
        }
      }

      $user_custom_fields = explode("\n", $action_settings['drip_custom_fields']);

      if( ! empty( $user_custom_fields ) && is_array( $user_custom_fields ) ){
        foreach( $user_custom_fields as $key => $pair ){
          $pair = trim( $pair );
          if( ! empty( $pair ) ){
            $field = explode( '=>', $pair );
            if( ! empty( $field ) && is_array( $field ) ){
              if( isset( $field[0] ) && isset( $field[1] ) && ! empty( $field[0] ) && ! empty( $field[1] ) ){
                $custom_fields[ str_replace( '-', '_', sanitize_key( trim($field[0]) ) ) ] = esc_html( trim( $field[1] ) );
              }
            }
          }
        }
      }

      $subscriber = array( 'email' => $action_settings['drip_subscriber'] );

      if( ! empty( $custom_fields ) ){
        $subscriber['custom_fields'] = $custom_fields;
      }

      if( ! empty( $tags ) ){
        $subscriber['tags'] = $tags;
      }

      if( ! empty( $remove_tags ) ){
        $subscriber['remove_tags'] = $remove_tags;
      }

      if( intval( $action_settings['drip_lifetime_value'] ) > 0 ){
        $subscriber['lifetime_value'] = intval( $action_settings['drip_lifetime_value'] );
      }

      if( ! wp_validate_boolean( $action_settings['drip_prospect'] ) ){
        $subscriber['prospect'] = wp_validate_boolean( $action_settings['drip_prospect'] );
      }

      if( intval( $action_settings['drip_base_lead_score'] ) !== 30 ){
        $subscriber['base_lead_score'] = intval( $action_settings['drip_base_lead_score'] );
      }

      $response  = NF_Drip()->remote_post(
        "http://api.getdrip.com/v2/{:account_id}/subscribers", array( 'subscribers' => array( $subscriber )
      ));

      if( ! is_wp_error( $response ) ){
        $response = wp_remote_retrieve_body( $response );
        $response = json_decode($response);
        $response = isset( $response->subscribers ) ? $response->subscribers[0] : false;
      }

      if( $response !== false ){
        do_action( 'ninja-forms-drip-response', $response, $action_settings, $form_id, $data );
      }

      return $data;
    }
}
