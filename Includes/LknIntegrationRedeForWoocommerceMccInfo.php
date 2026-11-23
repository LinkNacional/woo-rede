<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bloco informativo dos programas de monitoramento das bandeiras (MCC).
 *
 * Algumas atividades (identificadas pelo MCC) precisam de cadastro nos
 * programas de monitoramento da Visa, Mastercard e Amex. O cadastro é feito
 * pela Rede, no PV do estabelecimento — nada disso é configurável no plugin.
 * Sem o cadastro, a Rede nega a transação, e o lojista tende a culpar o
 * checkout. Por isso o bloco fica junto do campo PV, embaixo da descrição
 * "Credenciais da Rede.".
 *
 * O bloco não é um `type => 'title'` (esse fecha a </table>, viraria aba do
 * menu) nem uma linha nova do form-table (linha sem <label> quebra o layout em
 * Admin/js/lkn-integration-rede-for-woocommerce-settings-layout.js). Em vez
 * disso, o campo PV ganha o marcador `data-rede-mcc-info` e o layout JS clona
 * este bloco (impresso como <template>) para dentro do corpo do campo.
 */
final class LknIntegrationRedeForWoocommerceMccInfo
{
    /** id do <template> clonado pelo layout JS. */
    public const TEMPLATE_ID = 'lkn-rede-mcc-info';

    /** Marcador colocado no input do PV (ver field_custom_attributes()). */
    public const MARKER_ATTRIBUTE = 'data-rede-mcc-info';

    /**
     * Telas de gateway que usam o PV da Rede (e portanto mostram o bloco).
     *
     * `rede_pix` é do plugin PRO, mas a tela dele roda o layout JS deste plugin
     * (FREE), então o bloco aparece lá também.
     */
    private const GATEWAY_SECTIONS = array(
        'rede_credit',
        'rede_debit',
        'integration_rede_pix',
        'rede_google_pay',
        'rede_pix',
        'rede_apple_pay',
    );

    /**
     * Atributos a mesclar no `custom_attributes` do campo PV.
     *
     * @return array<string, string>
     */
    public static function field_custom_attributes(): array
    {
        return array(self::MARKER_ATTRIBUTE => '1');
    }

    /**
     * A requisição atual é a tela de configuração de um gateway que usa PV?
     */
    public static function is_gateway_screen(): bool
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        if ('wc-settings' !== $page || 'checkout' !== $tab) {
            return false;
        }

        /**
         * Telas de gateway que exibem o bloco de MCC.
         *
         * @param array $sections Sections (ids de gateway) que mostram o bloco.
         */
        $sections = apply_filters('integration_rede_for_woocommerce_mcc_info_sections', self::GATEWAY_SECTIONS);
        $sections = is_array($sections) ? $sections : self::GATEWAY_SECTIONS;

        return in_array($section, $sections, true);
    }

    /**
     * Imprime o bloco uma única vez por tela, dentro de um <template>.
     *
     * Roda no `admin_footer` porque o layout JS executa no `window.load`: o
     * template precisa existir antes disso. Um <template> não renderiza nada,
     * então não atrapalha quem já tem a opção configurada.
     */
    public function maybe_render_template(): void
    {
        if (! is_admin() || wp_doing_ajax() || ! self::is_gateway_screen()) {
            return;
        }

        printf(
            '<template id="%1$s">%2$s</template>',
            esc_attr(self::TEMPLATE_ID),
            self::render_block() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup montado e escapado internamente.
        );
    }

    /**
     * Markup do bloco (sem o <template>).
     */
    public static function render_block(): string
    {
        $rows = '';

        foreach (self::mccs() as $mcc) {
            $rows .= sprintf(
                '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td></tr>',
                esc_html($mcc[0]),
                esc_html($mcc[1]),
                esc_html($mcc[2])
            );
        }

        return sprintf(
            '<div class="lkn-rede-mcc">'
            . '<p class="lkn-rede-mcc__intro">%1$s</p>'
            . '<details class="lkn-rede-mcc__details">'
            . '<summary>%2$s</summary>'
            . '<table class="lkn-rede-mcc__table">'
            . '<thead><tr><th scope="col">%3$s</th><th scope="col">%4$s</th><th scope="col">%5$s</th></tr></thead>'
            . '<tbody>%6$s</tbody>'
            . '</table>'
            . '</details>'
            . '<p class="lkn-rede-mcc__footer">%7$s</p>'
            . '</div>',
            esc_html__('Some business activities, identified by the MCC code, must be registered with the card brand monitoring programs. Without this registration Rede may decline the transaction — that is not a failure of this plugin.', 'woo-rede'),
            esc_html__('Which MCCs require registration?', 'woo-rede'),
            esc_html__('MCC', 'woo-rede'),
            esc_html__('Activity', 'woo-rede'),
            esc_html__('Brands', 'woo-rede'),
            $rows,
            esc_html__('To register, contact Rede and request enrollment in the monitoring program of each brand.', 'woo-rede')
        );
    }

    /**
     * MCCs que exigem cadastro nos programas de monitoramento.
     *
     * As atividades voltam sem escape (render_block() escapa uma única vez).
     *
     * @return array<int, array{0: string, 1: string, 2: string}> MCC, atividade, bandeiras.
     */
    public static function mccs(): array
    {
        return array(
            array('5122', __('Pharmaceuticals and pharmacies — wholesale', 'woo-rede'), 'Visa, Mastercard, Amex'),
            array('5912', __('Pharmacies — retail', 'woo-rede'), 'Visa, Mastercard, Amex'),
            array('5993', __('Tobacco stores', 'woo-rede'), 'Visa, Mastercard, Amex'),
            array('9406', __('Government lotteries', 'woo-rede'), 'Mastercard, Amex'),
            array('7994 / 5816', __('Skill games', 'woo-rede'), 'Mastercard, Visa'),
            array('4816', __('Cyberlockers', 'woo-rede'), 'Visa, Mastercard'),
            array('6211', __('Financial trading platforms', 'woo-rede'), 'Visa, Mastercard'),
            array('6538', __('Person-to-person transactions — P2P', 'woo-rede'), 'Amex'),
        );
    }
}
