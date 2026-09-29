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

    /** Cache da versão instalada do PRO (evita reler o arquivo várias vezes). */
    private $cached_pro_version = null;

    /**
     * Registra a página oculta da tela de atualização.
     */
    public function register_screen(): void
    {
        add_submenu_page(
            '',
            __('Atualização do plugin PRO', 'woo-rede'),
            __('Atualização do plugin PRO', 'woo-rede'),
            'update_plugins',
            self::SCREEN_SLUG,
            array($this, 'render_screen')
        );
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

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if (self::SCREEN_SLUG === $page) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SCREEN_SLUG));
        exit;
    }

    /**
     * Renderiza a tela cheia de atualização do PRO.
     */
    public function render_screen(): void
    {
        if (! current_user_can('update_plugins')) {
            wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'woo-rede'));
        }

        // Se o PRO já foi atualizado, não mostra mais a tela.
        if (! $this->should_show()) {
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        }

        // A tela carregou: marca como exibida para não abrir novamente.
        update_option(self::OPTION_SHOWN, 'yes');

        $free_name = __('Integration Rede Itaú para WooCommerce', 'woo-rede');
        $pro_name = __('Integração da Rede para WooCommerce Pro', 'woo-rede');
        $min_version = $this->min_pro_version();
        ?>
        <div class="wrap lkn-pro-update-screen">
            <div class="lkn-pro-update-screen__card">
                <a href="<?php echo esc_url(admin_url()); ?>" class="lkn-pro-update-screen__close" aria-label="<?php esc_attr_e('Fechar e não mostrar novamente', 'woo-rede'); ?>">
                    <span aria-hidden="true">&times;</span>
                </a>

                <div class="lkn-pro-update-screen__badge" aria-hidden="true">&#9888;&#65039;</div>

                <h1 class="lkn-pro-update-screen__title">
                    <?php esc_html_e('Atualização importante do plugin PRO', 'woo-rede'); ?>
                </h1>

                <p class="lkn-pro-update-screen__lead">
                    <?php
                    echo sprintf(
                        /* translators: %1$s: FREE plugin name, %2$s: PRO plugin name, %3$s: minimum PRO version */
                        esc_html__('O plugin %1$s exige a versão %3$s ou superior do %2$s. Sua versão instalada está desatualizada.', 'woo-rede'),
                        '<strong>' . esc_html($free_name) . '</strong>',
                        '<strong>' . esc_html($pro_name) . '</strong>',
                        '<strong>' . esc_html($min_version) . '</strong>'
                    );
                    ?>
                </p>

                <div class="lkn-pro-update-screen__body">
                    <p>
                        <?php esc_html_e('Atualize o plugin PRO para evitar falhas na tela de configurações e no checkout. Seus dados não serão alterados.', 'woo-rede'); ?>
                    </p>
                    <ul class="lkn-pro-update-screen__features">
                        <li>&#128274; <?php esc_html_e('Mais segurança nas transações', 'woo-rede'); ?></li>
                        <li>&#9889; <?php esc_html_e('Correções e melhorias críticas', 'woo-rede'); ?></li>
                        <li>&#128179; <?php esc_html_e('Novos recursos de pagamento', 'woo-rede'); ?></li>
                    </ul>
                </div>

                <div class="lkn-pro-update-screen__actions">
                    <a href="<?php echo esc_url(admin_url()); ?>" class="button button-secondary button-hero"><?php esc_html_e('Agora não', 'woo-rede'); ?></a>
                    <button type="button" class="button button-primary button-hero lkn-pro-update-button">
                        <span class="lkn-pro-update-button__bar" aria-hidden="true"></span>
                        <span class="lkn-pro-update-button__text"><?php esc_html_e('Atualizar plugin PRO', 'woo-rede'); ?></span>
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
        $free_name = __('Integration Rede Itaú para WooCommerce', 'woo-rede');
        $pro_name = __('Integração da Rede para WooCommerce Pro', 'woo-rede');
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
                    <span class="lkn-pro-notice__badge"><?php esc_html_e('Atualização', 'woo-rede'); ?></span>
                </p>
                <p>
                    <?php
                    echo sprintf(
                        /* translators: %1$s: PRO plugin name, %2$s: minimum PRO version */
                        esc_html__('Uma atualização importante do plugin %1$s está disponível (versão %2$s ou superior). Atualize para evitar falhas na configuração e no checkout.', 'woo-rede'),
                        '<strong>' . esc_html($pro_name) . '</strong>',
                        '<strong>' . esc_html($this->min_pro_version()) . '</strong>'
                    );
                    ?>
                </p>
                <button type="button" class="button button-primary lkn-pro-update-button">
                    <span class="lkn-pro-update-button__bar" aria-hidden="true"></span>
                    <span class="lkn-pro-update-button__text"><?php esc_html_e('Atualizar plugin PRO', 'woo-rede'); ?></span>
                </button>
            </div>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dispensar este aviso.', 'woo-rede'); ?></span></button>
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
            wp_send_json_error(array('message' => __('Você não tem permissão para atualizar plugins.', 'woo-rede')));
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
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if (true !== $result) {
            wp_send_json_error(array('message' => __('Nenhuma atualização disponível no momento. Atualize pela tela de Plugins.', 'woo-rede')));
        }

        delete_site_transient('update_plugins');

        wp_send_json_success(array('message' => __('Atualizado com sucesso. Recarregando…', 'woo-rede')));
    }

    /**
     * AJAX: dispensa o aviso final permanentemente.
     */
    public function ajax_dismiss(): void
    {
        check_ajax_referer(self::NONCE_DISMISS, 'nonce');

        if (! current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => __('Permissão insuficiente.', 'woo-rede')), 403);
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

        if (! $on_screen && ! $this->notice_would_show()) {
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

        wp_localize_script('lkn-rede-pro-update', 'LknProUpdate', $this->script_data());
    }

    /**
     * Dados enviados ao JS.
     *
     * @return array
     */
    private function script_data(): array
    {
        return array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_UPDATE,
            'nonce' => wp_create_nonce(self::NONCE_UPDATE),
            'plugin' => self::PRO_BASENAME,
            'redirectUrl' => admin_url('plugins.php'),
            'successText' => __('Atualizado!', 'woo-rede'),
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
