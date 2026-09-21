<?php
/**
 * Template compacto do checkout clássico (shortcode) do Rede Débito/Crédito.
 *
 * Mesma estrutura de campos do template padrão (mesmos IDs/nomes, para o
 * processamento e os scripts existentes continuarem funcionando), porém num
 * layout compacto:
 *   Linha 1: Nome do titular | Tipo do cartão
 *   Linha 2: Número do cartão (com bandeiras) | Data de validade | Código
 *   Linha 3: Parcelas
 *
 * Recurso PRO (selecionado por get3dsTemplateStyle() === 'compact').
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */

if (! defined('ABSPATH')) {
    exit();
}
$integration_rede_for_woocommerce_option = get_option('woocommerce_rede_debit_settings');
?>
<fieldset id="rede-debit-payment-form" class="rede-payment-form rede-compact-classic">
    <div class="payment-method-description">
        <p><?php echo esc_html($integration_rede_for_woocommerce_option['description'] ?? __('Pay for your purchase with a debit card through', 'woo-rede')); ?></p>
    </div>

    <div class="rede-debit-fields-wrapper rede-compact-wrapper">
        <div id="rede-debit-card-animation" class="card-wrapper card-animation"></div>
        <div class="wc-payment-rede-form-fields rede-compact-fields">

            <?php
            // Tipos de cartão permitidos conforme a restrição configurada.
            if ($card_type_restriction === 'both') {
                $lkn_card_type_options = array(
                    'debit' => __('Debit Card', 'woo-rede'),
                    'credit' => __('Credit Card', 'woo-rede'),
                );
                $lkn_card_type_selected = ($card_type === 'credit') ? 'credit' : 'debit';
            } elseif ($card_type_restriction === 'credit_only') {
                $lkn_card_type_options = array('credit' => __('Credit Card', 'woo-rede'));
                $lkn_card_type_selected = 'credit';
            } else {
                $lkn_card_type_options = array('debit' => __('Debit Card', 'woo-rede'));
                $lkn_card_type_selected = 'debit';
            }

            // Esconde o seletor apenas quando restrito a um único tipo e a opção estiver habilitada.
            $lkn_hide_card_type_selector = ($card_type_restriction !== 'both' && (isset($hide_card_type_selector) ? $hide_card_type_selector : 'no') === 'yes');

            // Com um único tipo, o seletor é exibido porém "travado": fica cinza com cara de
            // disabled (SEM usar o atributo disabled, que faria o campo ser ignorado no envio).
            $lkn_lock_card_type_selector = ($card_type_restriction !== 'both' && !$lkn_hide_card_type_selector);

            $lkn_card_type_select_style = '';
            if ($lkn_lock_card_type_selector) {
                // O select do compacto recebe background branco !important no CSS; para o
                // estado "travado" (cinza) vencer, o inline precisa de !important (igual ao Cielo).
                $lkn_card_type_select_style .= ' background-color: #f0f0f1 !important; color: #767676 !important; pointer-events: none; cursor: not-allowed;';
            }
            ?>

            <div class="rede-compact-row rede-compact-row--top<?php echo $lkn_hide_card_type_selector ? ' rede-compact-row--name-only' : ''; ?>">
                <!-- Nome do titular -->
                <div class="rede-compact-field rede-compact-field--name">
                    <label for="rede-debit-card-holder-name"><?php esc_html_e('Name on Card', 'woo-rede'); ?><span class="required">*</span></label>
                    <div class="rede-compact-input-wrap">
                        <input id="rede-debit-card-holder-name"
                            name="rede_debit_holder_name"
                            class="input-text"
                            type="text"
                            autocomplete="off"
                            maxlength="30"
                            placeholder="Nome impresso no cartão"
                            required />
                    </div>
                </div>

                <!-- Tipo do cartão -->
                <div class="rede-compact-field rede-compact-field--type" id="rede-debit-card-type-wrapper"<?php echo $lkn_hide_card_type_selector ? ' style="display: none;"' : ''; ?>>
                    <label for="rede-debit-card-type"><?php esc_html_e('Card Type', 'woo-rede'); ?><span class="required">*</span></label>
                    <div class="rede-compact-input-wrap">
                        <select
                            id="rede-debit-card-type"
                            name="rede_debit_card_type"
                            class="input-select lknIntegrationRedeForWoocommerceSelect rede-compact-select"
                            style="<?php echo esc_attr($lkn_card_type_select_style); ?>"
                            autocomplete="off"
                            <?php if ($lkn_lock_card_type_selector) : ?>data-lkn-locked="true" aria-disabled="true" tabindex="-1"<?php endif; ?>>
                            <?php foreach ($lkn_card_type_options as $lkn_card_type_value => $lkn_card_type_label) : ?>
                                <option value="<?php echo esc_attr($lkn_card_type_value); ?>" <?php echo ($lkn_card_type_selected === $lkn_card_type_value) ? 'selected' : ''; ?>><?php echo esc_html($lkn_card_type_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rede-compact-row rede-compact-row--card">
                <!-- Número do cartão (com bandeiras) -->
                <div class="rede-compact-field rede-compact-field--number">
                    <label for="rede-debit-card-number"><?php esc_html_e('Card Number', 'woo-rede'); ?><span class="required">*</span></label>
                    <div class="rede-compact-input-wrap">
                        <input
                            id="rede-debit-card-number"
                            name="rede_debit_number"
                            class="input-text jp-card-invalid wc-debit-card-form-card-number"
                            type="tel"
                            maxlength="22"
                            autocomplete="off"
                            inputmode="numeric"
                            placeholder="0000 0000 0000 0000"
                            required />
                        <div class="rede-compact-card-brands" aria-hidden="true"></div>
                    </div>
                    <input
                        id="rede-debit-card-nonce"
                        name="rede_card_nonce"
                        type="hidden"
                        value="<?php echo esc_attr(wp_create_nonce('redeCardNonce')) ?>">
                </div>

                <!-- Data de validade (o ícone é injetado via JS) -->
                <div class="rede-compact-field rede-compact-field--exp" data-icon="calendar">
                    <label for="rede-debit-card-expiry"><?php esc_html_e('Card Expiring Date', 'woo-rede'); ?><span class="required">*</span></label>
                    <div class="rede-compact-input-wrap">
                        <input id="rede-debit-card-expiry"
                            name="rede_debit_expiry"
                            class="input-text wc-debit-card-form-card-expiry"
                            type="tel"
                            autocomplete="off"
                            inputmode="numeric"
                            placeholder="MM/AA"
                            required />
                    </div>
                </div>

                <!-- Código de segurança (o ícone é injetado via JS) -->
                <div class="rede-compact-field rede-compact-field--cvc" data-icon="key">
                    <label for="rede-debit-card-cvc"><?php esc_html_e('Security Code', 'woo-rede'); ?><span class="required">*</span></label>
                    <div class="rede-compact-input-wrap">
                        <input id="rede-debit-card-cvc"
                            name="rede_debit_cvc"
                            class="input-text wc-debit-card-form-card-cvc"
                            type="tel"
                            autocomplete="off"
                            inputmode="numeric"
                            maxlength="4"
                            placeholder="CVC"
                            required />
                    </div>
                </div>
            </div>

            <?php if (($card_type_restriction === 'credit_only' || $card_type_restriction === 'both') && is_array($installments) && count($installments) > 1) : ?>
            <div class="rede-compact-row rede-compact-row--installments" id="rede-debit-installments-wrapper" <?php echo ($card_type_restriction === 'both' && $card_type === 'debit') ? 'style="display: none;"' : ''; ?>>
                <div class="rede-compact-field rede-compact-field--installments">
                    <label for="rede-debit-card-installments"><?php esc_html_e('Installments', 'woo-rede'); ?><span class="required">*</span></label>
                    <div class="rede-compact-input-wrap">
                        <select
                            id="rede-debit-card-installments"
                            name="rede_debit_installments"
                            class="input-select lknIntegrationRedeForWoocommerceSelect rede-compact-select"
                            autocomplete="off">
                            <?php
                            $integration_rede_for_woocommerce_default_installment = isset($installments_number) ? (int)$installments_number : 1;
                            foreach ($installments as $integration_rede_for_woocommerce_installment) {
                                $integration_rede_for_woocommerce_selected = ($integration_rede_for_woocommerce_installment['num'] == $integration_rede_for_woocommerce_default_installment) ? 'selected' : '';
                                printf('<option value="%d" %s>%s</option>',
                                    esc_attr($integration_rede_for_woocommerce_installment['num']),
                                    esc_attr($integration_rede_for_woocommerce_selected),
                                    esc_html($integration_rede_for_woocommerce_installment['label'])
                                );
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="clear"></div>
        </div>
    </div>

    <?php if ($card_type_restriction === 'both') : ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleInstallments() {
                var cardType = $('#rede-debit-card-type').val();
                var installmentsWrapper = $('#rede-debit-installments-wrapper');

                if (cardType === 'credit') {
                    installmentsWrapper.show();
                } else {
                    installmentsWrapper.hide();
                    $('#rede-debit-card-installments').val('1');
                }
            }

            toggleInstallments();
            $('#rede-debit-card-type').on('change', toggleInstallments);
        });
    </script>
    <?php endif; ?>
</fieldset>
