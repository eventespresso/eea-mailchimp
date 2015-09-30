<?php
/**
* This contains the logic for setting up the Custom MailChimp Settings Page.
*
**/

class Mailchimp_Admin_Page extends EE_Admin_Page {

	public function __construct( $routing = TRUE ) {
		parent::__construct( $routing );
	}

	protected function _init_page_props() {
		$this->page_slug = ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG;
		$this->page_label = EE_MAILCHIMP_LABEL;
	}

	protected function _ajax_hooks() {
		//todo: all hooks for ajax goes here.
	}

	protected function _define_page_props() {
		$this->_admin_base_url = EE_MAILCHIMP_ADMIN_URL;
		$this->_admin_page_title = $this->page_label;
		$this->_labels = array();
	}

	protected function _set_page_routes() {
		$this->_page_routes = array(
			'default' => array(
				'func' => '_mailchimp_api_settings'
			),
			'update_mailchimp'	=> array(
				'func' => '_update_mailchimp',
				'noheader' => TRUE
			)
		);
	}

	protected function _set_page_config() {
		$this->_page_config = array(
			'default' => array(
				'nav' => array(
					'label' => __('Main Settings', 'event_espresso'),
					'order' => 10
				),
				'metaboxes' => array( '_publish_post_box', '_espresso_news_post_box', '_espresso_links_post_box', '_espresso_sponsors_post_box' , '_mailchimp_meta_boxes' )
			)
		);
	}



	/**
	 * 	load_scripts_styles
	 */
	public function load_scripts_styles() {
		wp_enqueue_script('ee_admin_js');

	}



	/**
	 * 		declare price details page metaboxes
	 *		@access protected
	 *		@return void
	 */
	protected function _mailchimp_meta_boxes() {
		add_meta_box( 'mailchimp-instructions-mbox', __( 'MailChimp Instructions', 'event_espresso' ), array( $this, '_mailchimp_instructions_meta_box' ), $this->wp_page_slug, 'normal', 'high' );
		add_meta_box( 'mailchimp-details-mbox', __( 'API Settings', 'event_espresso' ), array( $this, '_mailchimp_api_settings_meta_box' ), $this->wp_page_slug, 'normal', 'high' );
	}



	/**
	 * 		_mailchimp_api_settings_meta_box
	 *		@access public
	 *		@return void
	 */
	public function _mailchimp_api_settings_meta_box() {
		echo EEH_Template::display_template( EE_MAILCHIMP_TEMPLATE_PATH . 'mailchimp_api_settings.template.php', $this->_template_args, TRUE );
	}



	/**
	 * 		_edit_price_details_meta_box
	 *		@access public
	 *		@return void
	 */
	public function _mailchimp_instructions_meta_box() {
		echo EEH_Template::display_template( EE_MAILCHIMP_TEMPLATE_PATH . 'mailchimp_instructions.template.php', $this->_template_args, TRUE );
	}



	/**
	 * 		_mailchimp_api_settings
	 *		@access protected
	 *		@return void
	 */
	protected function _mailchimp_api_settings() {

		$config = EE_Config::instance()->get_config( 'addons', 'Mailchimp', 'EE_Mailchimp_Config' );
		//d( $config );
		$this->_template_args['mailchimp_double_opt_check'] =  isset( $config->api_settings->skip_double_optin ) && $config->api_settings->skip_double_optin === FALSE ? 'checked="checked"' : '';
		
		$this->_template_args['submit_to_mc_end'] = $this->_template_args['submit_to_mc_complete'] = $this->_template_args['submit_to_mc_approved'] = '';
		switch ( $config->api_settings->submit_to_mc_when ) {
			case 'attendee-information-end':
				$this->_template_args['submit_to_mc_end'] = 'selected';
				break;
			case 'reg-step-completed':
				$this->_template_args['submit_to_mc_complete'] = 'selected';
				break;
			case 'reg-step-approved':
				$this->_template_args['submit_to_mc_approved'] = 'selected';
				break;
			default:
				$this->_template_args['submit_to_mc_approved'] = 'selected';
				break;
		}

		$this->_template_args['mailchimp_api_key'] = isset( $config->api_settings, $config->api_settings->api_key ) ? $config->api_settings->api_key : '';
		if ( isset( $this->_req_data['mcapi_error'] ) && ! empty( $this->_req_data['mcapi_error'] )) {
			$this->_template_args['mailchimp_key_error'] = '<span class="important-notice">' . $this->_req_data['mcapi_error'] . '</span>';
			$this->_template_args['mailchimp_api_key_class'] = 'error';
			$this->_template_args['mailchimp_api_key_img'] = '<span class="dashicons dashicons-no pink-icon ee-icon-size-24"></span>';
		} else if ( ! empty( $this->_template_args['mailchimp_api_key'] )) {
			$this->_template_args['mailchimp_key_error'] = NULL;
			$this->_template_args['mailchimp_api_key_class'] = '';
			$this->_template_args['mailchimp_api_key_img'] = '<span class="dashicons dashicons-yes green-icon ee-icon-size-24"></span>';
		} else {
			$this->_template_args['mailchimp_key_error'] = NULL;
			$this->_template_args['mailchimp_api_key_class'] = '';
			$this->_template_args['mailchimp_api_key_img'] = '';
		}

		$this->_set_publish_post_box_vars( 'id', 1,  FALSE, NULL, FALSE );
		$this->_set_add_edit_form_tags( 'update_mailchimp' );
		// the details template wrapper
		$this->display_admin_page_with_sidebar();
	}



	/**
	 * 	_update_mailchimp
	 *  validates and saves the MailChimp API key to the config
	 */
	protected function _update_mailchimp() {
		$query_args = array( 'action' => 'default' );
		/** @type EE_Mailchimp_Config $config */
		$config = EE_Config::instance()->get_config( 'addons', 'Mailchimp', 'EE_Mailchimp_Config' );
		if ( isset( $_POST['mailchimp_api_key'] ) && ! empty( $_POST['mailchimp_api_key'] )) {
			$mailchimp_api_key = sanitize_text_field( $_POST['mailchimp_api_key'] );
			$mci_controller = new EE_MCI_Controller( $mailchimp_api_key );
			// Validate the MailChimp API Key
			$key_valid = $mci_controller->mci_get_api_key();
			if ( $key_valid ) {
				$key_valid = TRUE;
				$config->api_settings->mc_active = 1;
				$config->api_settings->api_key = $mailchimp_api_key;
				$config->api_settings->skip_double_optin = empty( $_POST['mailchimp_double_opt'] ) ? TRUE : FALSE;
				$config->api_settings->submit_to_mc_when = empty( $_POST['submit_to_mc_when'] ) ? 'reg-step-approved' : $_POST['submit_to_mc_when'];
				EE_Config::instance()->update_config( 'addons', 'Mailchimp', $config );
			} else {
				$key_valid = FALSE;
				$mcapi_error = $mci_controller->mci_get_response_error();
				$error_msg = isset( $mcapi_error['msg'] ) ? $mcapi_error['msg'] : __( 'Unknown MailChimp API Error.', 'event_espresso' );
				EE_Error::add_error( $error_msg, __FILE__, __FUNCTION__, __LINE__ );
				$query_args['mcapi_error'] = $error_msg;
				$config->api_settings->mc_active = FALSE;
				$config->api_settings->api_key = '';
				$config->api_settings->submit_to_mc_when = empty( $_POST['submit_to_mc_when'] ) ? 'reg-step-approved' : $_POST['submit_to_mc_when'];
				EE_Config::instance()->update_config( 'addons', 'Mailchimp', $config );
			}
		} else {
			$key_valid = FALSE;
			$error_msg = __( 'Please enter a MailChimp API key.', 'event_espresso' );
			EE_Error::add_error( $error_msg, __FILE__, __FUNCTION__, __LINE__ );
			$query_args['mcapi_error'] = $error_msg;
			$config->api_settings->mc_active = FALSE;
			$config->api_settings->api_key = '';
			$config->api_settings->submit_to_mc_when = empty( $_POST['submit_to_mc_when'] ) ? 'reg-step-approved' : $_POST['submit_to_mc_when'];
			EE_Config::instance()->update_config( 'addons', 'Mailchimp', $config );
		}
		if ( isset( $query_args['mcapi_error'] )) {
			$query_args['mcapi_error'] = urlencode( $query_args['mcapi_error'] );
		}
		$this->_redirect_after_action( $key_valid, 'Mailchimp API Key', 'updated', $query_args );
	}




	//none of the below group are currently used for this page
   protected function _add_screen_options() {}
   protected function _add_feature_pointers() {}
   public function admin_init() {}
   public function admin_notices() {}
   public function admin_footer_scripts() {}




}

