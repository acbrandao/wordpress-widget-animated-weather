<?php
/*
Plugin Name: Animated Weather Widget
Description: Simple aimated weather plugin using OpenWeatherMap API (requires API key) and meteocons with shortcode [weather] and settings        
Version: 1.25
Author: Tony Brandao (abrandao@abrandao.com)
Author URI: http://www.abrandao.com/author
Plugin URI: https://www.abrandao.com/2025/01/wordpress-animated-weather-widget/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

  
// Widget Class
class Animated_animwewi_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'animated_animwewi_widget',
            'Animated Weather Widget',
            array('description' => 'Displays weather widget using OpenWeatherMap API with customizable display options')
        );

        add_action('wp_enqueue_scripts', array($this, 'load_plugin_styles'));
        add_action('wp_enqueue_scripts', array($this, 'load_fontawesome'));
    }

    public function load_fontawesome() {
        wp_enqueue_style('fontawesome', plugins_url('assets/css/all.min.css', __FILE__));
        // wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    
    }

    public function load_plugin_styles() {
        wp_enqueue_style('weather-plugin-style', plugins_url('weather-plugin.css', __FILE__));
        
        // Get gradient colors from settings
        $gradient_start = get_option('animwewi_plugin_gradient_start', '#8db3c5');
        $gradient_end = get_option('animwewi_plugin_gradient_end', '#0090D2');
        
        $custom_css = "
            .weather-widget {
                background: linear-gradient(135deg, {$gradient_start}, {$gradient_end});
            }
        ";
        wp_add_inline_style('weather-plugin-style', $custom_css);
    }

    // Widget Frontend Display
    public function widget($args, $instance) {
    
        
        $api_key = get_option('animwewi_plugin_api_key');
        $location = !empty($instance['location']) ? $instance['location'] : get_option('animwewi_plugin_default_location', 'London,UK');
        $temp_unit = !empty($instance['temp_unit']) ? $instance['temp_unit'] : get_option('animwewi_plugin_temp_unit', 'F');
        $show_high_low = isset($instance['show_high_low']) ? $instance['show_high_low'] : get_option('animwewi_plugin_show_high_low', true);
        $show_wind = isset($instance['show_wind']) ? $instance['show_wind'] : get_option('animwewi_plugin_show_wind', true);
        $show_description = isset($instance['show_description']) ? $instance['show_description'] : get_option('animwewi_plugin_show_description', true);
        
        if (empty($api_key)) {
            echo esc_html('Please configure OpenWeatherMap API key in settings.');
            echo wp_kses_post($args['after_widget']);  ////escaped with wp_kses
            return;
        }

        $animwewi_data = $this->get_animwewi_data($api_key, $location);
        
        if (is_wp_error($animwewi_data)) {
            echo esc_html($animwewi_data->get_error_message() );
            echo wp_kses_post($args['after_widget']);  // escaped with wp_kses
            return;
        }

        ?>
        <div class="weather-widget">
            <div class="weather-location">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo  esc_html($animwewi_data['name']); ?>
                <?php if(!empty($animwewi_data['sys']['country'])): ?>
                    , <?php echo  esc_html($animwewi_data['sys']['country']); ?>
                <?php endif; ?>
                
                <div class="weather-note">
                    <sup>
                    <?php 
                    $timezone = $animwewi_data['timezone']; 
                    $timestamp = time() + $timezone; 
                    $localTime = gmdate('g:i a', $timestamp); 
                    echo  esc_html($localTime); 
                    ?>
                    </sup>
                </div>
            </div>
           
            <div class="weather-main">
                <div class="weather-icon">
                    <?php echo  $this->get_animwewi_icon($animwewi_data['weather'][0]['icon']) ; 
                    ?> 
                   
                </div>
                <div class="weather-temp">
                    <?php if($temp_unit == 'F'): ?>
                        <span class="temp-primary"><?php echo  esc_html(round($animwewi_data['main']['temp'] * 9/5 + 32) ); ?>°F</span>
                    <?php else: ?>
                        <span class="temp-primary"><?php echo  esc_html(round($animwewi_data['main']['temp'])); ?>°C</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="weather-details">
                <?php if($show_description): ?>
                    <div class="weather-item">
                        <i class="fas fa-umbrella"></i>
                        <?php echo  esc_html((ucfirst($animwewi_data['weather'][0]['description']))); ?>
                    </div>
                <?php endif; ?>

                <?php if($show_high_low): ?>
                    <div class="weather-item">
                        <i class="fas fa-temperature-high"></i>
                        High: <?php 
                        echo  esc_html($temp_unit) == 'F'
                            ? round($animwewi_data['main']['temp_max'] * 9/5 + 32) . '°F'
                            : round($animwewi_data['main']['temp_max']) . '°C';
                        ?>
                    </div>
                    <div class="weather-item">
                        <i class="fas fa-temperature-low"></i>
                        Low: <?php 
                        echo  esc_html($temp_unit) == 'F'
                            ? round($animwewi_data['main']['temp_min'] * 9/5 + 32) . '°F'
                            : round($animwewi_data['main']['temp_min']) . '°C';
                        ?>
                    </div>
                <?php endif; ?>

                <?php if($show_wind): ?>
                    <div class="weather-item">
                        <i class="fas fa-wind"></i>
                        Wind: <?php echo  esc_html(round($animwewi_data['wind']['speed'] * 2.237)); ?> mph
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        echo wp_kses_post($args['after_widget']);  //escaped with wp_kses
    }

    // Widget Backend Form
    public function form($instance) {
        $location = !empty($instance['location']) ? $instance['location'] : '';
        $temp_unit = !empty($instance['temp_unit']) ? $instance['temp_unit'] : 'F';
        $show_high_low = isset($instance['show_high_low']) ? $instance['show_high_low'] : true;
        $show_wind = isset($instance['show_wind']) ? $instance['show_wind'] : true;
        $show_description = isset($instance['show_description']) ? $instance['show_description'] : true;
        ?>
        <p>
            <label for="<?php echo  esc_attr($this->get_field_id('location')); ?>">Location:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('location')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('location')); ?>" type="text" 
                   value="<?php echo esc_attr($location); ?>" 
                   placeholder="City name,Country code (e.g., London,UK)">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('temp_unit')); ?>">Temperature Unit:</label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('temp_unit')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('temp_unit')); ?>">
                <option value="F" <?php selected($temp_unit, 'F'); ?>>Fahrenheit</option>
                <option value="C" <?php selected($temp_unit, 'C'); ?>>Celsius</option>
            </select>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_high_low')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_high_low')); ?>" 
                   <?php checked($show_high_low); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_high_low')); ?>">Show High/Low Temperatures</label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_wind')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_wind')); ?>" 
                   <?php checked($show_wind); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_wind')) ?>">Show Wind Speed</label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_description')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_description')); ?>" 
                   <?php checked($show_description); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_description')); ?>">Show Weather Description</label>
        </p>
        <?php
    }

    // Widget Update
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['location'] = (!empty($new_instance['location'])) ? wp_strip_all_tags($new_instance['location']) : '';
        $instance['temp_unit'] = (!empty($new_instance['temp_unit'])) ? wp_strip_all_tags($new_instance['temp_unit']) : 'F';
        $instance['show_high_low'] = isset($new_instance['show_high_low']) ? true : false;
        $instance['show_wind'] = isset($new_instance['show_wind']) ? true : false;
        $instance['show_description'] = isset($new_instance['show_description']) ? true : false;
        return $instance;
    }

    private function get_animwewi_data($api_key, $location) {
        $url = add_query_arg(
            array(
                'q' => urlencode($location),
                'appid' => $api_key,
                'units' => 'metric'
            ),
            'https://api.openweathermap.org/data/2.5/weather'
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return new WP_Error('animwewi_api_error', 'Failed to fetch weather data');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || isset($data['cod']) && $data['cod'] !== 200) {
            return new WP_Error('animwewi_api_error', 'Invalid response from weather API');
        }

        return $data;
    }

    private function get_animwewi_icon($icon_code) {

        $url_file_path=plugins_url('assets/icons/openweathermap', __FILE__);
        $icon_filename=$url_file_path."/".$icon_code.".svg";
        return "<embed src='$icon_filename' type='image/svg+xml' width='120px' height='*' alt='Weather Icon' />";


    }
}

// Register widget
function enroll_animwewi_widget() {
    register_widget('Animated_animwewi_Widget');
}
add_action('widgets_init', 'enroll_animwewi_widget');


// Add this code after the widget registration and before the admin menu functions

// Register Shortcode
function animwewi_widget_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'location' => get_option('animwewi_plugin_default_location', 'London,UK'),
        'temp_unit' => get_option('animwewi_plugin_temp_unit', 'F'),
        'show_high_low' => get_option('animwewi_plugin_show_high_low', true),
        'show_wind' => get_option('animwewi_plugin_show_wind', true),
        'show_description' => get_option('animwewi_plugin_show_description', true)
    ), $atts, 'weather');

    // Convert string boolean values to actual booleans
    $atts['show_high_low'] = filter_var($atts['show_high_low'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_wind'] = filter_var($atts['show_wind'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_description'] = filter_var($atts['show_description'], FILTER_VALIDATE_BOOLEAN);

    // Start output buffering
    ob_start();

    // Create instance of widget
    $widget = new Animated_animwewi_Widget();
    
    // Call widget() method with proper arguments
    $widget->widget(
        array(
            'before_widget' => '',
            'after_widget' => '',
            'before_title' => '',
            'after_title' => ''
        ),
        $atts
    );

    // Return the buffered content
    return ob_get_clean();
}
add_shortcode('weather', 'animwewi_widget_shortcode');


// Add Admin Menu
function animwewi_plugin_admin_menu() {
    add_options_page(
        'Weather Widget Settings',
        'Weather Widget',
        'manage_options',
        'weather-plugin-settings',
        'animwewi_plugin_settings_page'
    );
}
add_action('admin_menu', 'animwewi_plugin_admin_menu');

// WEather Shortcode documentation / help
function spawn_gen_animwewi_shortcode_docs() {
$animwewi_shortcode_docs = '<hr style="margin: 30px 0;">
<h2>Shortcode Usage</h2>
<p>You can display the weather widget anywhere in your content using the <code>[weather]</code> shortcode.</p>


<h3>Advanced Usage</h3>
<p>You can customize the widget display using these attributes:</p>
<ul style="list-style-type: disc; margin-left: 20px;">
    <li><code>location</code> - City and country code (e.g., "Paris,FR")</li>
    <li><code>temp_unit</code> - Temperature unit ("F" or "C")</li>
    <li><code>show_high_low</code> - Show high/low temperatures ("true" or "false")</li>
    <li><code>show_wind</code> - Show wind speed ("true" or "false")</li>
    <li><code>show_description</code> - Show weather description ("true" or "false")</li>
</ul>

<h3>Examples</h3>
<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">
// Basic example with location
[weather location="Tokyo,JP"]

// Full example with all options
[weather location="Paris,FR" temp_unit="C" show_high_low="true" show_wind="true" show_description="true"]

// Minimal display
[weather location="Rome,IT" show_high_low="false" show_wind="false" show_description="false"]
</pre>

<h3>Template Usage</h3>
<p>To use the shortcode in your template files, use this PHP code:</p>
<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">
do_shortcode([weather location="London,UK" temp_unit="C"])</pre>';


echo wp_kses_post($animwewi_shortcode_docs);  //

// Admin Settings Page
function animwewi_plugin_settings_page() {
   
     // Check if the user has the required permissions
     if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }

    if (isset($_POST['animwewi_plugin_api_key'])) {

        if (wp_verify_nonce(sanitize_text_field( wp_unslash($_POST['my_form_nonce'])), 'my_form_action')) {
     
       // Process the request 
        update_option('animwewi_plugin_api_key', sanitize_text_field($_POST['animwewi_plugin_api_key']));
        update_option('animwewi_plugin_default_location', sanitize_text_field($_POST['animwewi_plugin_default_location']));
        update_option('animwewi_plugin_temp_unit', sanitize_text_field($_POST['animwewi_plugin_temp_unit']));
        update_option('animwewi_plugin_show_high_low', isset($_POST['animwewi_plugin_show_high_low']));
        update_option('animwewi_plugin_show_wind', isset($_POST['animwewi_plugin_show_wind']));
        update_option('animwewi_plugin_show_description', isset($_POST['animwewi_plugin_show_description']));
        update_option('animwewi_plugin_gradient_start', sanitize_hex_color(wp_unslash($_POST['animwewi_plugin_gradient_start'])));
        update_option('animwewi_plugin_gradient_end', sanitize_hex_color(wp_unslash($_POST['animwewi_plugin_gradient_end'])));
        
        echo ('<div class="updated"><p>Settings saved!</p></div>');

          
        } else {
            add_settings_error('animwewi_messages', 'animwewi_message', 'Security check failed!', 'error');
            http_response_code(403); 
            echo "Unauthorized access." ;
            }
      
        }

    $api_key = get_option('animwewi_plugin_api_key');
    $default_location = get_option('animwewi_plugin_default_location', 'London,UK');
    $temp_unit = get_option('animwewi_plugin_temp_unit', 'F');
    $show_high_low = get_option('animwewi_plugin_show_high_low', true);
    $show_wind = get_option('animwewi_plugin_show_wind', true);
    $show_description = get_option('animwewi_plugin_show_description', true);
    $gradient_start = get_option('animwewi_plugin_gradient_start', '#8db3c5');
    $gradient_end = get_option('animwewi_plugin_gradient_end', '#0090D2');
    ?>
    <div class="wrap">
        <h2>Weather Widget Settings</h2>
        <form method="post" action="">
        <?php
            // Create and add nonce field
            wp_nonce_field('my_form_action', 'my_form_nonce');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="animwewi_plugin_api_key">OpenWeatherMap API Key:</label>
                    </th>
                    <td>
                        <input type="text" id="animwewi_plugin_api_key" name="animwewi_plugin_api_key" 
                               value="<?php echo ($api_key); ?>" class="regular-text">
                        <p class="description">
                            Get your API key from <a href="https://openweathermap.org/api" target="_blank">OpenWeatherMap</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="animwewi_plugin_default_location">Default Location:</label>
                    </th>
                    <td>
                        <input type="text" id="animwewi_plugin_default_location" name="animwewi_plugin_default_location" 
                               value="<?php echo esc_attr($default_location); ?>" class="regular-text">
                        <p class="description">Format: City,Country code (e.g., London,UK)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="animwewi_plugin_temp_unit">Default Temperature Unit:</label>
                    </th>
                    <td>
                        <select id="animwewi_plugin_temp_unit" name="animwewi_plugin_temp_unit">
                            <option value="F" <?php selected($temp_unit, 'F'); ?>>Fahrenheit</option>
                            <option value="C" <?php selected($temp_unit, 'C'); ?>>Celsius</option>
                        </select></td>
                </tr>
                <tr>
                    <th scope="row">Display Options:</th>
                    <td>
                        <label>
                            <input type="checkbox" name="animwewi_plugin_show_high_low" 
                                   <?php checked($show_high_low); ?>>
                            Show High/Low Temperatures
                        </label><br>
                        <label>
                            <input type="checkbox" name="animwewi_plugin_show_wind" 
                                   <?php checked($show_wind); ?>>
                            Show Wind Speed
                        </label><br>
                        <label>
                            <input type="checkbox" name="animwewi_plugin_show_description" 
                                   <?php checked($show_description); ?>>
                            Show Weather Description
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Widget Colors:</th>
                    <td>
                        <label for="animwewi_plugin_gradient_start">Gradient Start Color:</label>
                        <input type="color" id="animwewi_plugin_gradient_start" 
                               name="animwewi_plugin_gradient_start" 
                               value="<?php echo esc_attr($gradient_start); ?>"><br><br>
                        <label for="animwewi_plugin_gradient_end">Gradient End Color:</label>
                        <input type="color" id="animwewi_plugin_gradient_end" 
                               name="animwewi_plugin_gradient_end" 
                               value="<?php echo esc_attr($gradient_end); ?>">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
    </div>
    <hr>
    <?php
    
     spawn_gen_animwewi_shortcode_docs();  //echo '</div>';
}

/* Weather Widget Plugin Styles */
/* Weather Widget Plugin Styles */
function animwewi_plugin_styles() {
    return "
    .weather-widget {
        color: #fff;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        margin: 8px 0;
    }

    .weather-location {
        font-size: 1.8em;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .weather-main {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin: 10px 0;
        text-align: center;
    }

    .weather-icon {
        margin-bottom: -5px;
    }

    .weather-icon img {
        width: 120px;
        height: 120px;
        display: block;
        margin: 0 auto;
    }

    .weather-temp {
        text-align: center;
    }

    .temp-primary {
        font-size: 3.0em;
        font-weight: bold;
        line-height: 0;
    }

    .weather-details {
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        padding-top: 12px;
        margin-top: 12px;
    }

    .weather-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 1.1em;
    }

    .weather-note {
        font-size: 0.5em;
        opacity: 0.8;
        margin-left: auto;
    }

    .weather-item i {
        width: 24px;
        font-size: 1.2em;
        text-align: center;
        opacity: 0.9;
    }

    @media (max-width: 480px) {
        .weather-location {
            font-size: 1.4em;
        }
        
        .temp-primary {
            font-size: 2.4em;
        }
        
        .weather-icon img {
            width: 100px;
            height: 100px;
        }
        
        .weather-item {
            font-size: 0.95em;
        }
    }";
}

// Register the styles
function enroll_animwewi_plugin_styles() {
    wp_register_style('weather-plugin-style', false);
    wp_enqueue_style('weather-plugin-style');
    wp_add_inline_style('weather-plugin-style', animwewi_plugin_styles());
}
add_action('wp_enqueue_scripts', 'enroll_animwewi_plugin_styles');