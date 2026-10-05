<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Aviso + tela de atualização do plugin PRO.
 *
 * Padrão inspirado no sistema de migração do "woo-better-shipping-calculator-for-brazil"
 * e do "shipping-simulator-for-woocommerce":
 *
 *  - Uma tela cheia (página oculta) exibida uma única vez quando o PRO está
 *    instalado e desatualizado (redirecionamento único).
 *  - Uma camada final (notice-warning) exibida em todo o admin depois que a tela
 *    foi mostrada — "o aviso que reaparece".
 *  - Um botão com barrinha verde de progresso que atualiza o plugin PRO,
 *    reaproveitando o endpoint de update do PRO (zip + json).
 *
 * O aviso aparece quando o PRO está INSTALADO (ativo ou não), diferente do
 * aviso antigo que exigia o PRO ativo.
 */
final class LknIntegrationRedeForWoocommerceProUpdateNotice
{
    /** Slug da página oculta da tela de atualização. */
    private const SCREEN_SLUG = 'lkn-rede-pro-update';

    /**
     * Filtro compartilhado entre os plugins da família LKN que mapeia as telas
     * de onboarding/migração (telas cheias ocultas). Cada plugin registra o
     * próprio slug; qualquer um pula o redirect quando já está numa dessas
     * telas — evita loop de redirect entre dois plugins desse mesmo padrão.
     */
    private const ONBOARDING_SCREENS_FILTER = 'lkn_admin_onboarding_screens';

    /** Caminho relativo do arquivo principal do PRO. */
    private const PRO_BASENAME = 'rede-for-woocommerce-pro/rede-for-woocommerce-pro.php';

    /** Endpoint de update do PRO (json + zip). */
    private const API_URL = 'https://api.linknacional.com/v2/u/?slug=rede-for-woocommerce-pro';

    /** Checksum usado pelo plugin-update-checker no endpoint. */
    private const API_SALT = '4823a0e58074af39154f19e3de1f7443';

    /** Opção que marca a tela de atualização como já exibida. */
    private const OPTION_SHOWN = 'lkn_rede_pro_update_screen_shown';

    /** Opção que marca o aviso final como dispensado. */
    private const OPTION_DISMISSED = 'lkn_rede_pro_update_notice_dismissed';

    /** Ação AJAX de atualização do PRO (reaproveita a ação já existente). */
    private const AJAX_UPDATE = 'lkn_rede_force_update_pro';

    /** Ação do nonce de atualização. */
    private const NONCE_UPDATE = 'lkn_rede_force_update_pro';

    /** Ação AJAX para dispensar o aviso final. */
    private const AJAX_DISMISS = 'lkn_rede_dismiss_pro_update';

    /** Ação do nonce de dispensa. */
    private const NONCE_DISMISS = 'lkn_rede_dismiss_pro_update_nonce';

    /** Transient de sucesso: exibe o card "atualizado" após o reload. */
    private const SUCCESS_TRANSIENT = 'lkn_rede_pro_update_success';

    /** Transient de erro: exibe o card de erro após o reload. */
    private const ERROR_TRANSIENT = 'lkn_rede_pro_update_error';

    /** Cache da versão instalada do PRO (evita reler o arquivo várias vezes). */
    private $cached_pro_version = null;

    /**
     * Registra a página oculta da tela de atualização.
     */
    public function register_screen(): void
    {
        add_submenu_page(
            '',
            __('PRO plugin update', 'woo-rede'),
            __('PRO plugin update', 'woo-rede'),
            'update_plugins',
            self::SCREEN_SLUG,
            array($this, 'render_screen')
        );
    }

    /**
     * Registra o slug desta tela na lista compartilhada de telas de onboarding
     * da família LKN. Outros plugins leem essa lista para não redirecionar a
     * partir de uma tela de onboarding (evita loop de redirect).
     *
     * @param mixed $screens Slugs já registrados por outros plugins LKN.
     * @return array
     */
    public function register_onboarding_screen($screens): array
    {
        $screens = is_array($screens) ? $screens : array();
        $screens[] = self::SCREEN_SLUG;

        return array_values(array_unique($screens));
    }

    /**
     * A requisição atual já está numa tela de onboarding da família LKN —
     * própria ou de outro plugin? Enquanto estiver, ninguém redireciona.
     *
     * @return bool
     */
    private function is_on_lkn_onboarding_screen(): bool
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if ('' === $page) {
            return false;
        }

        $screens = apply_filters(self::ONBOARDING_SCREENS_FILTER, array());
        $screens = is_array($screens) ? $screens : array();
        $screens[] = self::SCREEN_SLUG;

        return in_array($page, $screens, true);
    }

    /**
     * Remove as notificações de terceiros na tela cheia de atualização, para que
     * os avisos de outros plugins não apareçam dentro do card principal.
     * Mesmo padrão do woo-better-shipping-calculator-for-brazil.
     */
    public function remove_admin_notices(): void
    {
        // Mantém qualquer tela de onboarding LKN limpa (nossa ou de outro plugin).
        if (! $this->is_on_lkn_onboarding_screen()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    /**
     * Redireciona uma única vez para a tela de atualização quando o PRO está
     * instalado e desatualizado.
     */
    public function maybe_redirect(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }

        // Não interrompe requisições POST (evita perder um save em andamento).
        $request_method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : 'GET';
        if ('POST' === $request_method) {
            return;
        }

        if ($this->is_plugin_update_page()) {
            return;
        }

        if (! current_user_can('update_plugins')) {
            return;
        }

        if (! $this->should_show()) {
            return;
        }

        if ('yes' === get_option(self::OPTION_SHOWN, 'no')) {
            return;
        }

        // Não redireciona se já estamos numa tela de onboarding LKN — a nossa
        // ou a de outro plugin. Sem isso, dois plugins que redirecionam para
        // suas telas cheias entram em loop (A→B→A→B…): o redirect ocorre antes
        // de qualquer tela renderizar e marcar a option "shown".
        if ($this->is_on_lkn_onboarding_screen()) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SCREEN_SLUG));
        exit;
    }

    /**
     * Trata o clique no ✕ da tela cheia: dispensa definitivamente o aviso
     * final do PRO (não volta a aparecer) e devolve o usuário ao painel.
     *
     * Roda no `admin_init` (antes de qualquer saída): o callback da página
     * admin só executa depois do `admin-header.php`, então um redirect ali
     * falharia com "headers already sent".
     */
    public function maybe_handle_dismiss(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }

        if (! isset($_GET['lkn-rede-pro-update-dismiss'])) {
            return;
        }

        if (! current_user_can('update_plugins')) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, self::NONCE_DISMISS)) {
            return;
        }

        // Marca como exibido E dispensado: o ✕ fecha a tela e o aviso final,
        // sem depender de a tela ter renderizado antes (ex.: URL re-disparada).
        update_option(self::OPTION_SHOWN, 'yes');
        update_option(self::OPTION_DISMISSED, 'yes');

        wp_safe_redirect(admin_url());
        exit;
    }

    /**
     * Renderiza a tela cheia de atualização do PRO.
     */
    public function render_screen(): void
    {
        if (! current_user_can('update_plugins')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'woo-rede'));
        }

        // Se o PRO já foi atualizado, não mostra mais a tela.
        if (! $this->should_show()) {
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        }

        // A tela carregou: marca como exibida para não abrir novamente.
        update_option(self::OPTION_SHOWN, 'yes');

        $dismiss_url = wp_nonce_url(
            admin_url('admin.php?page=' . self::SCREEN_SLUG . '&lkn-rede-pro-update-dismiss=1'),
            self::NONCE_DISMISS
        );

        $free_name = __('Integration Rede Itaú for WooCommerce', 'woo-rede');
        $pro_name = __('Integration Rede Itaú for WooCommerce PRO', 'woo-rede');
        $min_version = $this->min_pro_version();
        ?>
        <div class="wrap lkn-pro-update-screen">
            <div class="lkn-pro-update-screen__card">
                <a href="<?php echo esc_url($dismiss_url); ?>" class="lkn-pro-update-screen__close" aria-label="<?php esc_attr_e('Close and do not show again', 'woo-rede'); ?>">
                    <span aria-hidden="true">&times;</span>
                </a>

                <div class="lkn-pro-update-screen__badge" aria-hidden="true">&#9888;&#65039;</div>

                <h1 class="lkn-pro-update-screen__title">
                    <?php esc_html_e('Important PRO plugin update', 'woo-rede'); ?>
                </h1>

                <p class="lkn-pro-update-screen__lead">
                    <?php
                    echo sprintf(
                        /* translators: %1$s: FREE plugin name, %2$s: PRO plugin name, %3$s: minimum PRO version */
                        esc_html__('The plugin %1$s requires version %3$s or higher of %2$s. Your installed version is out of date.', 'woo-rede'),
                        '<strong>' . esc_html($free_name) . '</strong>',
                        '<strong>' . esc_html($pro_name) . '</strong>',
                        '<strong>' . esc_html($min_version) . '</strong>'
                    );
                    ?>
                </p>

                <div class="lkn-pro-update-screen__body">
                    <p>
                        <?php esc_html_e('Update the PRO plugin to avoid failures on the settings screen and at checkout. Your data will not be changed.', 'woo-rede'); ?>
                    </p>
                    <ul class="lkn-pro-update-screen__features">
                        <li>&#128274; <?php esc_html_e('More secure transactions', 'woo-rede'); ?></li>
                        <li>&#9889; <?php esc_html_e('Critical fixes and improvements', 'woo-rede'); ?></li>
                        <li>&#128179; <?php esc_html_e('New payment features', 'woo-rede'); ?></li>
                    </ul>
                </div>

                <div class="lkn-pro-update-screen__actions">
                    <a href="<?php echo esc_url(admin_url()); ?>" class="button button-secondary button-hero"><?php esc_html_e('Not now', 'woo-rede'); ?></a>
                    <button type="button" class="button button-primary button-hero lkn-pro-update-button">
                        <span class="lkn-pro-update-button__bar" aria-hidden="true"></span>
                        <span class="lkn-pro-update-button__text"><?php esc_html_e('Update PRO plugin', 'woo-rede'); ?></span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Camada final: notice-warning exibido em todo o admin após a tela ter sido
     * mostrada, até ser dispensado.
     */
    public function maybe_render_notice(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }

        // Não exibe o aviso sobre a própria tela de atualização.
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (self::SCREEN_SLUG === $page) {
            return;
        }

        if (! current_user_can('update_plugins')) {
            return;
        }

        if (! $this->should_show()) {
            return;
        }

        // Segunda camada: só depois que a tela cheia foi exibida.
        if ('yes' !== get_option(self::OPTION_SHOWN, 'no')) {
            return;
        }

        if ('yes' === get_option(self::OPTION_DISMISSED, 'no')) {
            return;
        }

        $nonce = wp_create_nonce(self::NONCE_DISMISS);
        $free_name = __('Integration Rede Itaú for WooCommerce', 'woo-rede');
        $pro_name = __('Integration Rede Itaú for WooCommerce PRO', 'woo-rede');
        ?>
        <div class="notice notice-warning is-dismissible lkn-pro-notice lkn-pro-notice--update"
            data-dismissible="lkn-rede-pro-update"
            data-action="<?php echo esc_attr(self::AJAX_DISMISS); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="lkn-pro-notice__icon">
                <img src="<?php echo esc_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Includes/assets/WordpressAssets/icon-256x256.gif'); ?>" alt="<?php echo esc_attr($free_name); ?>">
            </div>
            <div class="lkn-pro-notice__content">
                <p class="lkn-pro-notice__title">
                    <strong><?php echo esc_html($free_name); ?></strong>
                    <span class="lkn-pro-notice__badge"><?php esc_html_e('Update', 'woo-rede'); ?></span>
                </p>
                <p>
                    <?php
                    echo sprintf(
                        /* translators: %1$s: PRO plugin name, %2$s: minimum PRO version */
                        esc_html__('An important update for the %1$s plugin is available (version %2$s or higher). Update it to avoid failures in the configuration and at checkout.', 'woo-rede'),
                        '<strong>' . esc_html($pro_name) . '</strong>',
                        '<strong>' . esc_html($this->min_pro_version()) . '</strong>'
                    );
                    ?>
                </p>
                <button type="button" class="button button-primary lkn-pro-update-button">
                    <span class="lkn-pro-update-button__bar" aria-hidden="true"></span>
                    <span class="lkn-pro-update-button__text"><?php esc_html_e('Update PRO plugin', 'woo-rede'); ?></span>
                </button>
            </div>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'woo-rede'); ?></span></button>
        </div>
        <?php
    }

    /**
     * AJAX: atualiza o plugin PRO.
     *
     * 1) Tenta o updater nativo (funciona quando o PRO está ativo e publicou a
     *    atualização no transient).
     * 2) Fallback: busca o pacote no endpoint do PRO e instala/atualiza por cima
     *    (funciona mesmo com o PRO apenas instalado).
     */
    public function ajax_update_pro(): void
    {
        check_ajax_referer(self::NONCE_UPDATE, 'nonce');

        if (! current_user_can('update_plugins') && ! current_user_can('install_plugins')) {
            wp_send_json_error(array('message' => __('You do not have permission to update plugins.', 'woo-rede')));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);

        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }

        $result = $upgrader->upgrade(self::PRO_BASENAME);

        if (is_wp_error($result) || true !== $result) {
            $package = $this->get_pro_package_url();

            if ('' !== $package) {
                $result = $upgrader->install($package, array('overwrite_package' => true));
            }
        }

        if (is_wp_error($result)) {
            $message = $result->get_error_message();
            set_transient(self::ERROR_TRANSIENT, $message, 5 * MINUTE_IN_SECONDS);
            wp_send_json_error(array('message' => $message));
        }

        if (true !== $result) {
            $message = __('No update available at the moment. Update from the Plugins screen.', 'woo-rede');
            set_transient(self::ERROR_TRANSIENT, $message, 5 * MINUTE_IN_SECONDS);
            wp_send_json_error(array('message' => $message));
        }

        delete_site_transient('update_plugins');
        set_transient(self::SUCCESS_TRANSIENT, 'updated', 5 * MINUTE_IN_SECONDS);

        wp_send_json_success(array('message' => __('Updated successfully. Reloading…', 'woo-rede')));
    }

    /**
     * AJAX: dispensa o aviso final permanentemente.
     */
    public function ajax_dismiss(): void
    {
        check_ajax_referer(self::NONCE_DISMISS, 'nonce');

        if (! current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => __('Insufficient permission.', 'woo-rede')), 403);
        }

        update_option(self::OPTION_DISMISSED, 'yes');

        wp_send_json_success();
    }

    /**
     * Enfileira CSS/JS quando o aviso ou a tela serão exibidos.
     */
    public function enqueue_assets(): void
    {
        if (! is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $on_screen = (self::SCREEN_SLUG === $page);

        $success = get_transient(self::SUCCESS_TRANSIENT);
        $error   = get_transient(self::ERROR_TRANSIENT);

        if (! $on_screen && ! $this->notice_would_show() && false === $success && false === $error) {
            return;
        }

        wp_enqueue_style(
            'lkn-rede-pro-update',
            INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Admin/css/lkn-rede-pro-update.css',
            array(),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION
        );

        wp_enqueue_script(
            'lkn-rede-pro-update',
            INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Admin/js/lkn-rede-pro-update.js',
            array(),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
            true
        );

        // Consome os transients: o card de sucesso/erro aparece uma única vez.
        $show_on_load  = '';
        $error_message = '';
        if (false !== $error) {
            $show_on_load  = 'error';
            $error_message = (string) $error;
            delete_transient(self::ERROR_TRANSIENT);
        } elseif (false !== $success) {
            $show_on_load = 'success';
            delete_transient(self::SUCCESS_TRANSIENT);
        }

        wp_localize_script('lkn-rede-pro-update', 'LknProUpdate', $this->script_data($show_on_load, $error_message));
    }

    /**
     * Dados enviados ao JS.
     *
     * @return array
     */
    private function script_data(string $show_on_load = '', string $error_message = ''): array
    {
        $plugin_name = __('Integration Rede Itaú for WooCommerce', 'woo-rede');

        return array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_UPDATE,
            'nonce' => wp_create_nonce(self::NONCE_UPDATE),
            'plugin' => self::PRO_BASENAME,
            'redirectUrl' => admin_url('plugins.php'),
            'successText' => __('Updated!', 'woo-rede'),
            'iconUrl' => INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Includes/assets/WordpressAssets/icon-256x256.gif',
            'showOnLoad' => $show_on_load,
            'errorMessage' => $error_message,
            'success' => array(
                'title' => $plugin_name,
                'badge' => __('Success', 'woo-rede'),
                'close' => __('Close', 'woo-rede'),
                'message' => __('The PRO plugin was updated successfully.', 'woo-rede'),
            ),
            'error' => array(
                'title' => $plugin_name,
                'badge' => __('Error', 'woo-rede'),
                'close' => __('Close', 'woo-rede'),
            ),
        );
    }

    /**
     * Verifica se o aviso final seria exibido.
     */
    private function notice_would_show(): bool
    {
        if (! current_user_can('update_plugins')) {
            return false;
        }

        if (! $this->should_show()) {
            return false;
        }

        if ('yes' !== get_option(self::OPTION_SHOWN, 'no')) {
            return false;
        }

        return 'yes' !== get_option(self::OPTION_DISMISSED, 'no');
    }

    /**
     * Condição base: PRO instalado e desatualizado.
     */
    private function should_show(): bool
    {
        if ($this->is_plugin_update_page()) {
            return false;
        }

        if (! $this->pro_installed()) {
            return false;
        }

        return $this->pro_outdated();
    }

    /**
     * Caminho absoluto do arquivo principal do PRO.
     */
    private function pro_file(): string
    {
        return WP_PLUGIN_DIR . '/' . self::PRO_BASENAME;
    }

    /**
     * O PRO está instalado?
     */
    private function pro_installed(): bool
    {
        return file_exists($this->pro_file());
    }

    /**
     * Versão instalada do PRO.
     */
    private function pro_version(): string
    {
        if (null !== $this->cached_pro_version) {
            return $this->cached_pro_version;
        }

        if (! $this->pro_installed()) {
            return $this->cached_pro_version = '';
        }

        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_plugin_data($this->pro_file(), false, false);

        return $this->cached_pro_version = (isset($data['Version']) ? (string) $data['Version'] : '');
    }

    /**
     * O PRO está abaixo da versão mínima exigida pelo FREE?
     */
    private function pro_outdated(): bool
    {
        $version = $this->pro_version();

        if ('' === $version) {
            return false;
        }

        return version_compare($version, $this->min_pro_version(), '<');
    }

    /**
     * Versão mínima do PRO compatível com este FREE.
     */
    private function min_pro_version(): string
    {
        return defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_MIN_PRO_VERSION')
            ? INTEGRATION_REDE_FOR_WOOCOMMERCE_MIN_PRO_VERSION
            : '2.4.8';
    }

    /**
     * Busca a URL do pacote (.zip) do PRO no endpoint de update.
     */
    private function get_pro_package_url(): string
    {
        $url = add_query_arg(
            array(
                'installed_version' => $this->pro_version(),
                'php' => PHP_VERSION,
                'locale' => get_locale(),
                's' => self::API_SALT,
                'checking_for_updates' => '1',
            ),
            self::API_URL
        );

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body) || empty($body['download_url'])) {
            return '';
        }

        $package = esc_url_raw($body['download_url']);

        // Defesa: só aceita https e hosts válidos (evita instalar pacote arbitrário).
        if (0 !== strpos($package, 'https://')) {
            return '';
        }

        if (function_exists('wp_http_validate_url') && ! wp_http_validate_url($package)) {
            return '';
        }

        return $package;
    }

    /**
     * A página atual é de atualização/instalação de plugins?
     */
    private function is_plugin_update_page(): bool
    {
        $pagenow = isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '';

        return in_array($pagenow, array('update.php', 'update-core.php', 'update-core-network.php'), true);
    }
}
