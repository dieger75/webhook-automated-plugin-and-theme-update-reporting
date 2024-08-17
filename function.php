// Guardar la versión antigua antes de la actualización
if (!function_exists('save_old_version')) {
    function save_old_version($bool, $hook_extra) {
        if (isset($hook_extra['plugin'])) {
            // Si se trata de un plugin
            $item_path = $hook_extra['plugin'];
            $item_full_path = WP_PLUGIN_DIR . '/' . $item_path;
        } elseif (isset($hook_extra['theme'])) {
            // Si se trata de un tema
            $item_path = $hook_extra['theme'];
            $item_full_path = get_theme_root() . '/' . $item_path;
        } else {
            return $bool;
        }

        // Obtenemos los datos del plugin o tema usando el archivo principal
        $item_info = isset($hook_extra['plugin']) ? get_plugin_data($item_full_path) : wp_get_theme($item_path);

        // Guardar la versión actual como versión antigua antes de la actualización
        if (isset($item_info['Version'])) {
            update_option($item_path . '_version_old', $item_info['Version']);
        }

        return $bool;
    }
    add_filter('upgrader_pre_install', 'save_old_version', 10, 2);
}

// Disparar el webhook después de la actualización
if (!function_exists('notify_update')) {
    function notify_update($upgrader_object, $options) {
        $data = array();

        if ($options['action'] == 'update') {
            if ($options['type'] == 'plugin' && !empty($options['plugins'])) {
                foreach ($options['plugins'] as $plugin_path) {
                    $plugin_full_path = WP_PLUGIN_DIR . '/' . $plugin_path;
                    $plugin_info = get_plugin_data($plugin_full_path);
                    $old_version = get_option($plugin_path . '_version_old', '');

                    $data[] = array(
                        'type' => 'plugin',
                        'name' => $plugin_info['Name'],
                        'old_version' => $old_version,
                        'new_version' => $plugin_info['Version'],
                        'path' => $plugin_path,
                    );

                    update_option($plugin_path . '_version_old', $plugin_info['Version']);
                }
            } elseif ($options['type'] == 'theme' && !empty($options['themes'])) {
                foreach ($options['themes'] as $theme_path) {
                    $theme_info = wp_get_theme($theme_path);
                    $old_version = get_option($theme_path . '_version_old', '');

                    $data[] = array(
                        'type' => 'theme',
                        'name' => $theme_info->get('Name'),
                        'old_version' => $old_version,
                        'new_version' => $theme_info->get('Version'),
                        'path' => $theme_path,
                    );

                    update_option($theme_path . '_version_old', $theme_info->get('Version'));
                }
            }

            if (!empty($data)) {
                $custom_data = array(
                    'items' => $data,
                    'updated_by' => wp_get_current_user()->user_login,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'month_number' => date('m')
                );

                $webhook_url = 'https://hook.eu2.make.com/te85whoxkrw3kh1eo3orw88ua1iw0amk';

                $args = array(
                    'body'        => json_encode($custom_data),
                    'headers'     => array('Content-Type' => 'application/json'),
                    'timeout'     => 15,
                    'blocking'    => true,
                    'data_format' => 'body',
                );

                $response = wp_remote_post($webhook_url, $args);

                if (is_wp_error($response)) {
                    error_log('Error enviando el webhook: ' . $response->get_error_message());
                } else {
                    error_log('Webhook enviado correctamente: ' . print_r($response, true));
                }
            }
        }
    }
    add_action('upgrader_process_complete', 'notify_update', 10, 2);
}
