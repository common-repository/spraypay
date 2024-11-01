<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spray_Pay Class
 */
final class Spray_Pay {

	public $version = '1.0.8';

	public $rate = 0.03467;

	protected static $_instance = null;

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'spraypay' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'spraypay' ), '1.0.0' );
	}

	/**
	 * Main Spray_Pay Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Spray_Pay Constructor.
	 */
	public function __construct() {
		if ( ! class_exists( 'Mustache_Autoloader' ) ) {
			include_once( $this->plugin_path() . '/includes/vendors/Mustache/Autoloader.php' );
			Mustache_Autoloader::register();
		}
		include_once( $this->plugin_path() . '/includes/functions.php' );

		$this->init_hooks();
		$this->displaying_hooks();
	}

	/**
	 *  Init hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ), 0 );
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'wp_ajax_get_spray_pay_preview_html', array( $this, 'get_preview_html' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_admin' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts_frontend' ) );

		add_shortcode( 'spraypay', [ $this, 'shortcode' ] );
	}

	/**
	 *  Init displaying hooks
	 */
	private function displaying_hooks() {
		$spray_pay_settings = get_option( 'spray_pay_settings' );
		$hide_widget        = ! empty( $spray_pay_settings['hide_widget'] ) ? $spray_pay_settings['hide_widget'] : '';

		if ( ! is_array( $spray_pay_settings ) || empty( $spray_pay_settings['locations'] ) ) {
			return;
		}

		if ( in_array( 'single_product', $spray_pay_settings['locations'] ) ) {
			if ( 'yes' !== $hide_widget ) {
				if ( function_exists( 'in3_display_on_single_product' ) &&
                    has_action('woocommerce_single_product_summary', 'in3_display_on_single_product') ) {
					remove_action( 'woocommerce_single_product_summary', 'in3_display_on_single_product', 11 );
				}
				add_action( 'woocommerce_single_product_summary', 'spray_pay_display_on_single_product', 11 );
				if ( function_exists( 'in3_display_on_single_product' ) &&
                    has_action('woocommerce_single_product_summary', 'in3_display_on_single_product')) {
					add_action( 'woocommerce_single_product_summary', 'in3_display_on_single_product', 12 );
				}
			} else {
				add_action( 'wp_footer', 'spray_pay_output_widget_tmpl', 12 );
			}
		}

		if ( in_array( 'cart', $spray_pay_settings['locations'] ) ) {
			//add_action( 'woocommerce_cart_totals_after_order_total', 'spray_pay_display_in_cart', 11 );
		}

		if ( in_array( 'checkout', $spray_pay_settings['locations'] ) ) {
			//add_action( 'woocommerce_review_order_after_order_total', 'spray_pay_display_in_cart', 11 );
		}

		if ( in_array( 'shop', $spray_pay_settings['locations'] ) ) {
			// silence
		}
	}

	/**
	 * Conditionally loading styles/scripts in admin area
	 */
	function load_scripts_admin( $hook ) {
		if ( 'woocommerce_page_spray-pay-settings' === $hook ) {
			wp_enqueue_style(
				'spray-pay-styles',
				$this->plugin_url() . '/assets/css/admin-styles.css',
				array(),
				$this->version
			);

			wp_enqueue_script(
				'spray-pay-admin-scripts',
				$this->plugin_url() . '/assets/js/admin-scripts.js',
				array(),
				$this->version,
				true
			);

			$spray_payData = [
				'i18n'  => [
					'itemSelectText' => __( 'Klik op te selecteren', 'spraypay' ),
				],
				'rate'  => $this->rate,
				'nonce' => wp_create_nonce( 'spraypay_preview' )
			];

			wp_localize_script( 'spray-pay-admin-scripts', 'sprayPayData', $spray_payData );
		}
	}

	/**
	 * Conditionally loading for front end styles/scripts
	 */
	function load_scripts_frontend() {
		global $post;

		if ( is_singular( 'product' ) ) {
			$spray_pay_settings = get_option( 'spray_pay_settings' );

			wp_enqueue_style(
				'spray-pay-styles',
				$this->plugin_url() . '/assets/css/frontend.css',
				array(),
				$this->version
			);

			wp_add_inline_style( 'spray-pay-styles', "
@font-face {
  font-family: 'Bree Serif';
  font-style: normal;
  font-weight: 400;
  src: local(''),
       url('{$this->plugin_url()}/assets/fonts/bree-serif-v16-latin-ext_latin-regular.woff2') format('woff2'),
       url('{$this->plugin_url()}/assets/fonts/bree-serif-v16-latin-ext_latin-regular.woff') format('woff');
}
" );

			wp_enqueue_script(
				'spray-pay-scripts',
				$this->plugin_url() . '/assets/js/frontend.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			$spray_payData = [
				'minAmount'  => $spray_pay_settings['min_amount'],
				'maxAmount'  => $spray_pay_settings['max_amount'],
				'rate'       => $this->rate,
				'hideWidget' => ! empty( $spray_pay_settings['hide_widget'] ) ? $spray_pay_settings['hide_widget'] : ''
			];

			if ( function_exists( 'wc_get_price_decimals' ) ) {

				$spray_payData['currencySymbol'] = get_woocommerce_currency_symbol();
				$spray_payData['decimalPoint']   = wc_get_price_decimal_separator();
				$spray_payData['separator']      = wc_get_price_thousand_separator();
				$spray_payData['decimals']       = wc_get_price_decimals();
				$spray_payData['priceFormat']    = get_woocommerce_price_format();

			} else {

				$spray_payData['currencySymbol'] = '€';
				$spray_payData['decimalPoint']   = '.';
				$spray_payData['separator']      = '';
				$spray_payData['decimals']       = 2;
				$spray_payData['priceFormat']    = '%2$s%1$s ';

			}

			wp_localize_script( 'spray-pay-scripts', 'sprayPayData', $spray_payData );
		}
	}

	/**
	 * Add settings page menu item
	 */
	function settings_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'SprayPay Marketing Tool', 'spraypay' ),
			__( 'SprayPay Marketing Tool', 'spraypay' ),
			'manage_woocommerce',
			'spray-pay-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Add settings page
	 */
	function settings_page() {
		if ( isset( $_POST ) && isset( $_POST['option_page'] ) && $_POST['option_page'] === 'spray_pay_general_settings' ) {
			$this->save_settings();
		}

		?>
        <div class="wrap">
            <div
                    id="icon-themes"
                    class="icon32"></div>
            <h2><?php esc_html_e( 'SprayPay Marketing Tool instellingen', 'spraypay' ) ?></h2>

			<?php settings_errors(); ?>

            <p>
				<?php esc_html_e( 'SprayPay Marketing Plugin – Verhoog de omzet en het gemiddelde aankoopbedrag door het maandbedrag van SprayPay te tonen op de productpagina.', 'spraypay' ) ?>
            </p>

            <form
                    class="sprayPaySettingsForm"
                    method="POST"
                    action="admin.php?page=spray-pay-settings">
				<?php
				settings_fields( 'spray_pay_general_settings' );
				do_settings_sections( 'spray_pay_general_settings' );
				$this->admin_preview();
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Register plugin's settings on the settings page
	 */
	function register_settings() {
		add_settings_section(
			'spray_pay_widget_settings_section',
			__( 'De marketing tool instellingen pagina', 'spraypay' ),
			array( $this, 'spray_pay_widget_settings_section_desc' ),
			'spray_pay_general_settings'
		);

		$settings_cfg = $this->settings_config();

		foreach ( $settings_cfg as $s_id => $s_args ) {
			add_settings_field(
				$s_id,
				$s_args['label'],
				array( $this, 'spray_pay_render_setting_field' ),
				'spray_pay_general_settings',
				'spray_pay_widget_settings_section',
				$s_args
			);
			register_setting(
				'spray_pay_general_settings',
				$s_id,
				[
					'sanitize_callback' => array( $this, 'spray_pay_setting_validation' )
				]
			);
		}
	}

	/**
	 * Render a setting template based on given config
	 */
	function spray_pay_widget_settings_section_desc() {
	}

	/**
	 * Render a setting template based on given config
	 */
	function spray_pay_render_setting_field( $args ) {
		$saved_data = get_option( 'spray_pay_settings' );
		$saved_data = maybe_unserialize( $saved_data );
		$required   = '';

		if ( isset( $args['required'] ) && $args['required'] ) {
			$required = ' required';
		}

		$description = ( isset( $args['desc'] ) ) ? $args['desc'] : '';
		$kses_args   = array(
			'div'    => array(
				'class' => array(),
			),
			'span'   => array(
				'class' => array(),
			),
			'em'     => array(),
			'strong' => array(),
			'code'   => array(),
			'br'     => array()
		);

		switch ( $args['type'] ) {
			case 'input':
				$value = ! empty( $saved_data[ $args['id'] ] ) ? $saved_data[ $args['id'] ] : '';
				if ( $args['subtype'] === 'number' ) {
					$prependStart = ( isset( $args['prepend_value'] ) ) ? '<div class="input-prepend"> <span class="add-on">' . esc_html( $args['prepend_value'] ) . '</span>' : '';
					$prependEnd   = ( isset( $args['prepend_value'] ) ) ? '</div>' : '';
					$step         = ( isset( $args['step'] ) ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '';
					$min          = ( isset( $args['min'] ) ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '';
					$max          = ( isset( $args['max'] ) ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '';
					$placeholder  = ( isset( $args['placeholder'] ) ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';

					if ( isset( $args['disabled'] ) ) {
						echo wp_kses( $prependStart, $kses_args ) . '<input type="' . esc_attr( $args['subtype'] ) . '" id="' . esc_attr( $args['id'] ) . '_disabled"' . esc_attr( $step ) . '' . esc_attr( $max ) . '' . esc_attr( $min ) . esc_attr( $placeholder ) . ' name="' . esc_attr( $args['name'] ) . '_disabled" size="40" disabled value="' . esc_attr( $value ) . '" /><input type="hidden" id="' . esc_attr( $args['id'] ) . '" ' . esc_attr( $step ) . ' ' . esc_attr( $max ) . ' ' . esc_attr( $min ) . ' name="' . esc_attr( $args['name'] ) . '" size="40" value="' . esc_attr( $value ) . '" />' . wp_kses( $prependEnd, $kses_args );
					} else {
						echo wp_kses( $prependStart, $kses_args ) . '<input type="' . esc_attr( $args['subtype'] ) . '" id="' . esc_attr( $args['id'] ) . '"' . esc_attr( $required ) . esc_attr( $step ) . '' . esc_attr( $max ) . '' . esc_attr( $min ) . esc_attr( $placeholder ) . ' name="' . esc_attr( $args['name'] ) . '" size="40" value="' . esc_attr( $value ) . '" />' . wp_kses( $prependEnd, $kses_args );
					}
				} elseif ( $args['subtype'] === 'checkbox' ) {
					$checked = ( $value ) ? 'checked' : '';
					echo '<input type="' . esc_attr( $args['subtype'] ) . '" id="' . esc_attr( $args['id'] ) . '"' . esc_attr( $required ) . ' name="' . esc_attr( $args['name'] ) . '" size="40" value="1" ' . esc_attr( $checked ) . ' />';
				}

				if ( ! empty( $description ) ) {
					echo '<p class="description">' . wp_kses( $description, $kses_args ) . '</p>';
				}

				break;
			case 'select':
				$value    = ! empty( $saved_data[ $args['id'] ] ) ? $saved_data[ $args['id'] ] : '';
				$multiple = '';

				if ( $args['multiple'] ) {
					$value        = ! empty( $saved_data[ $args['id'] ] ) ? $saved_data[ $args['id'] ] : [];
					$multiple     = ' multiple';
					$args['name'] = $args['name'] . '[]';
				}

				echo '<select id="' . esc_attr( $args['id'] ) . '"' . esc_attr( $required ) . ' name="' . esc_attr( $args['name'] ) . '"' . esc_attr( $multiple ) . '>';
				if ( ! empty( $args['options_list'] ) ) {
					foreach ( $args['options_list'] as $v => $l ) {
						if ( $args['multiple'] ) {
							$checked = ( $value && in_array( $v, $value ) ) ? ' selected' : '';
						} else {
							$checked = ( $value && $value === $v ) ? ' selected' : '';
						}
						echo '<option value="' . esc_attr( $v ) . '"' . esc_attr( $checked ) . '>' . esc_html( $l ) . '</option>';
					}
				}
				echo '</select>';

				if ( ! empty( $description ) ) {
					echo '<p class="description">' . wp_kses( $description, $kses_args ) . '</p>';
				}

				break;
			case 'textarea':
				$placeholder = ( isset( $args['placeholder'] ) ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';
				$value       = ! empty( $saved_data[ $args['id'] ] ) ? $saved_data[ $args['id'] ] : '';
				echo '<textarea class="large-text" id="' . esc_attr( $args['id'] ) . '"' . esc_attr( $placeholder ) . esc_attr( $required ) . ' name="' . esc_attr( $args['name'] ) . '">';
				echo esc_attr( $value );
				echo '</textarea>';

				if ( ! empty( $description ) ) {

					echo '<p class="description">' . wp_kses( $description, $kses_args ) . '</p>';
				}

				break;
			case 'paragraph':
				echo '<p class="description">' . wp_kses( $description, $kses_args ) . '</p>';
				break;
			default:
				echo 'Error in settings config';
				break;
		}
	}

	/**
	 * Plugin's settings main config.
	 */
	function settings_config() {
		return [
			'locations'   => [
				'label'        => __( 'Activeer de marketing tool op:', 'spraypay' ),
				'type'         => 'select',
				'multiple'     => true,
				'id'           => 'locations',
				'name'         => 'locations',
				'required'     => false,
				'options_list' => [
					''               => __( 'Klik hier om locaties toe te voegen', 'spraypay' ),
					'single_product' => __( 'Product pagina', 'spraypay' )
				]
			],
			'appearance'  => [
				'label'        => __( 'Kies het uiterlijk van de marketingtool', 'spraypay' ),
				'type'         => 'select',
				'multiple'     => false,
				'id'           => 'appearance',
				'name'         => 'appearance',
				'required'     => false,
				'options_list' => [
					'branded' => __( 'Weergave: SprayPay logo en label', 'spraypay' ),
					'label'   => __( 'Alleen Label', 'spraypay' )
				]
			],
			'hide_widget' => [
				'label'        => __( 'Verberg de marketingtool', 'spraypay' ),
				'type'         => 'select',
				'multiple'     => false,
				'id'           => 'hide_widget',
				'name'         => 'hide_widget',
				'required'     => false,
				'options_list' => [
					'no'  => __( 'Nee', 'spraypay' ),
					'yes' => __( 'Ja', 'spraypay' )
				],
				'desc'         => __( 'Met deze optie kun je de standaardweergave van de marketingtool op productpagina\'s uitschakelen.<br>Met de volgende shortcode kun je de marketingtool zelf op iedere positie op een productpagina plaatsen: <code>[spraypay]</code>.', 'spraypay' )
			],
			'display_as'  => [
				'label'        => __( 'Weergeven als', 'spraypay' ),
				'type'         => 'select',
				'multiple'     => false,
				'id'           => 'display_as',
				'name'         => 'display_as',
				'required'     => false,
				'options_list' => $this->widget_display_options()
			],
			'min_amount'  => [
				'label'       => __( 'Minimale prijs', 'spraypay' ),
				'type'        => 'input',
				'subtype'     => 'number',
				'id'          => 'min_amount',
				'name'        => 'min_amount',
				'placeholder' => '250',
				'min'         => 250,
				'required'    => false,
				'desc'        => __( 'Het minimumbedrag waarmee de marketingtool zichtbaal zal zijn ', 'spraypay' )
			],
			'max_amount'  => [
				'label'       => __( 'Maximale prijs', 'spraypay' ),
				'type'        => 'input',
				'subtype'     => 'number',
				'id'          => 'max_amount',
				'name'        => 'max_amount',
				'placeholder' => '7500',
				'min'         => 251,
				'required'    => false,
				'desc'        => __( 'Het maximumbedrag waarmee de marketingtool zichtbaal zal zijn', 'spraypay' )
			]
		];
	}

	/**
	 * Plugin's settings form handler.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], "spray_pay_general_settings-options" ) ) {
			return;
		}

		$valid_setting_keys = array_keys( $this->settings_config() );
		$sanitized_data     = [];

		if ( $valid_setting_keys ) {
			foreach ( $valid_setting_keys as $k ) {
				$sanitized_data[ $k ] = $this->sanitize_field( $_POST[ $k ], $k );
			}
		}

		add_settings_error(
			'spray_pay_general_settings',
			1,
			__( 'Instellingen opgeslagen!', 'spraypay' ),
			'success'
		);

		update_option( 'spray_pay_settings', $sanitized_data );
	}

	/**
	 * Sanitize data
	 */
	public function sanitize_data( $data ) {
		$new = [];

		array_walk(
			$data,
			function ( $val, $key ) use ( &$new ) {
				if ( is_string( $val ) ) {
					if ( in_array( $key, [ 'min_amount', 'max_amount' ] ) ) {
						$new[ $key ] = absint( $val );
					} elseif ( 'tooltip_desc' === $key ) {
						$new[ $key ] = sanitize_textarea_field( $val );
					} else {
						$new[ $key ] = sanitize_text_field( $val );
					}
				} elseif ( is_array( $val ) ) {
					$new[ $key ] = array_map( 'sanitize_text_field', $val );
				}
			}
		);

		return $new;
	}

	/**
	 * Sanitize data
	 */
	public function sanitize_field( $val, $field_name ) {
		if ( is_string( $val ) ) {
			if ( in_array( $field_name, [ 'min_amount', 'max_amount' ] ) ) {
				return absint( $val );
			} elseif ( 'tooltip_desc' === $field_name ) {
				return sanitize_textarea_field( $val );
			} else {
				return sanitize_text_field( $val );
			}
		} elseif ( is_array( $val ) ) {
			return array_map( 'sanitize_text_field', $val );
		}
	}

	/**
	 * The list of all available displaying options. To be shown in setting's select field
	 */
	public function widget_display_options() {
		return [
			'opt1' => __( 'Of {{{bedrag}}} per maand (?)', 'spraypay' )
		];
	}

	/**
	 * The list of all available displaying options. To be used in Mustache tmpl engine
	 */
	public function widget_get_display_text_tmpl( $settings ) {
		$display_as = $settings['display_as'];
		$appearance = $settings['appearance'];
		$texts      = [
			'opt1' => 'branded' === $appearance
				? __( '<strong>{{{bedrag}}}</strong> per maand', 'spraypay' )
				: __( 'Of <strong>{{{bedrag}}}</strong> per maand', 'spraypay' )
		];

		return isset( $texts[ $display_as ] ) ? $texts[ $display_as ] : '';
	}

	/**
	 * Set default values for settings upon plugin activation
	 */
	public function set_defaults() {
		$existing = get_option( 'spray_pay_settings' );

		if ( ! $existing ) {
			$defaults = [
				'locations'   => [ 'single_product' ],
				'appearance'  => 'branded',
				'hide_widget' => 'no',
				'display_as'  => 'opt1',
				'min_amount'  => 250,
				'max_amount'  => 5000
			];

			update_option( 'spray_pay_settings', $defaults );
		}
	}

	/**
	 * Display preview of the widget in admin area
	 */
	function admin_preview() {
		$spray_pay_settings = get_option( 'spray_pay_settings' );
		$formula            = 570 * $this->rate;
		$bedrag_formatted   = spray_pay_formatted( $formula );

		$data = [
			'appearance'       => $spray_pay_settings['appearance'],
			'display_as_text'  => $this->widget_get_display_text_tmpl( $spray_pay_settings ),
			'bedrag_formatted' => spraypay_format_price( $bedrag_formatted ),
			'popup_content'    => '',
			'min_amount'       => $spray_pay_settings['min_amount'],
			'max_amount'       => $spray_pay_settings['max_amount'],
		];

		?>
        <button
                type="button"
                class="sprayPayPreviewBtn button action">
			<?php _e( 'Voorbeeld', 'spraypay' ) ?>
        </button>

        <div class="sprayPayPreviewContainer">
			<?php spray_pay_widget_tmpl( $data ) ?>
        </div>
		<?php
	}

	/**
	 * Get widget HTML
	 */
	function get_preview_html() {
		$_POST = json_decode( file_get_contents( 'php://input', true ), true );

		if ( ! wp_verify_nonce( $_POST['nonce'], "spraypay_preview" ) ) {
			return;
		}

		$valid_setting_keys = array_keys( $this->settings_config() );
		$sanitized_data     = [];

		if ( $valid_setting_keys ) {
			foreach ( $valid_setting_keys as $k ) {
				$sanitized_data[ $k ] = isset( $_POST[ $k ] ) ? $this->sanitize_field( $_POST[ $k ], $k ) : '';
			}
		}

		// sanitazing
		$formula          = 570 * $this->rate;
		$bedrag_formatted = spray_pay_formatted( $formula );

		$data = [
			'appearance'       => $sanitized_data['appearance'],
			'display_as_text'  => $this->widget_get_display_text_tmpl( $sanitized_data ),
			'bedrag_formatted' => spraypay_format_price( $bedrag_formatted ),
			'popup_content'    => '',
			'min_amount'       => '',
			'max_amount'       => ''
		];

		wp_send_json_success( spray_pay_widget_tmpl( $data, true ) );
	}

	/**
	 * shortcode()
	 */
	public function shortcode() {
		global $product;

		if ( ! is_singular( 'product' ) ) {
			return;
		}

		if ( ! $product ) {
			return;
		}

		if ( 'variable' !== $product->get_type() ) {
			$spray_pay_settings = get_option( 'spray_pay_settings' );
			$price              = $product->get_price();
			$should_display     = spray_pay_should_display( $price, $spray_pay_settings );

			if ( ! $should_display ) {
				return;
			}

			$bedrag_formatted = spray_pay_formatted( $price );

			$data = [
				'appearance'       => $spray_pay_settings['appearance'],
				'min_amount'       => $spray_pay_settings['min_amount'],
				'max_amount'       => $spray_pay_settings['max_amount'],
				'display_as_text'  => $this->widget_get_display_text_tmpl( $spray_pay_settings ),
				'bedrag_formatted' => spraypay_format_price( $bedrag_formatted ),
				'popup_content'    => ''
			];

			return spray_pay_widget_tmpl( $data, true );
		} else {
			return '<div class="sprayPayPlaceholder"></div>';
		}
	}

	/**
	 * load_plugin_textdomain()
	 */
	public function load_plugin_textdomain() {
		load_textdomain( 'spraypay', WP_LANG_DIR . '/spraypay/spraypay-' . get_locale() . '.mo' );
		load_plugin_textdomain( 'spraypay', false, plugin_basename( dirname( __FILE__ ) ) . "/languages" );
	}

	/**
	 * plugin_url()
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * plugin_path()
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get Ajax URL.
	 * @return string
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php', 'relative' );
	}

	/**
	 * on plugin activation
	 */
	public function activation() {
		$this->set_defaults();
	}

	/**
	 * on plugin activation
	 */
	public function deactivation() {
	}
}
