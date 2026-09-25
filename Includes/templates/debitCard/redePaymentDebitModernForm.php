<?php
/**
 * Template MODERNO do checkout clássico (shortcode) do Rede Débito/Crédito.
 *
 * Espelha o layout moderno do Cielo (lkn-cielo-debit-payment-fields-modern-layout)
 * adaptado ao sistema de bandeiras do Rede:
 *   - Faixa de bandeiras (Visa, Mastercard, Amex, Elo) acima dos campos.
 *   - Nome do titular (100%).
 *   - Número do cartão + tipo do cartão (½ cada).
 *   - Validade + código de segurança (½ cada).
 *   - Parcelas e botão de finalizar.
 *
 * A label fica ACIMA do input (por isso o placeholder é personalizável aqui,
 * diferente dos Blocos, onde a label é flutuante dentro do input).
 *
 * Usa os MESMOS IDs/nomes do template padrão, para o processamento, o cartão
 * animado (jquery.card.js) e os scripts existentes continuarem funcionando.
 *
 * Recurso PRO (selecionado por get3dsTemplateStyle() === 'modern').
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */

if (! defined('ABSPATH')) {
    exit();
}
$integration_rede_for_woocommerce_option = get_option('woocommerce_rede_debit_settings');

// Labels/placeholders personalizáveis (seção "Fields" do admin, recurso PRO).
$lkn_fields_gtw = 'rede_debit';
$lkn_lbl = function ($field) use ($lkn_fields_gtw) {
    return \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getFieldLabel($lkn_fields_gtw, 'modern', $field, 'classic');
};
$lkn_ph = function ($field, $default) use ($lkn_fields_gtw) {
    $custom = \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getFieldOverride($lkn_fields_gtw, 'modern', $field, 'placeholder', $default, 'classic');
    return '' !== $custom ? $custom : $default;
};

// Opções do gateway: cartão animado e bandeiras (default ligados).
$lkn_show_card_animation = isset($show_card_animation) ? $show_card_animation : 'yes';
$lkn_show_card_brand_icons = isset($show_card_brand_icons) ? $show_card_brand_icons : 'yes';

$lkn_asset = plugin_dir_url(__FILE__) . '../../assets/cardTemplate/';
$lkn_brands = array(
    'visa'       => array('label' => __('Visa', 'woo-rede'), 'file' => 'visa-icon.svg'),
    'mastercard' => array('label' => __('Mastercard', 'woo-rede'), 'file' => 'mastercard-icon.svg'),
    'amex'       => array('label' => __('American Express', 'woo-rede'), 'file' => 'amex-icon.svg'),
    'elo'        => array('label' => __('Elo', 'woo-rede'), 'file' => 'elo-icon.svg'),
    'other_card' => array('label' => __('Other Card', 'woo-rede'), 'file' => 'other-card.svg'),
);
?>
<fieldset id="rede-debit-payment-form" class="rede-payment-form rede-modern-classic">
    <div class="rede-modern-wrapper">
        <?php if ('yes' === $lkn_show_card_brand_icons) : ?>
        <!-- Faixa de bandeiras -->
        <div class="rede-card-brands-container" id="rede-debit-card-brands">
            <div class="rede-card-brands">
                <?php foreach ($lkn_brands as $lkn_brand_key => $lkn_brand) : ?>
                    <img
                        src="<?php echo esc_url($lkn_asset . $lkn_brand['file']); ?>"
                        alt="<?php echo esc_attr($lkn_brand['label'] . ' logo'); ?>"
                        title="<?php echo esc_attr($lkn_brand['label']); ?>"
                        data-brand="<?php echo esc_attr($lkn_brand_key); ?>"
                        class="rede-card-brand-icon" />
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="rede-modern-form-fields">
            <?php if ('yes' === $lkn_show_card_animation) : ?>
            <div id="rede-debit-card-animation" class="card-wrapper card-animation"></div>
            <?php endif; ?>

            <!-- Nome do titular (100%) -->
            <div class="modern-field">
                <label class="field-label" for="rede-debit-card-holder-name">
                    <?php echo esc_html($lkn_lbl('holder_name')); ?><span class="required">*</span>
                </label>
                <div class="field-wrapper">
                    <input id="rede-debit-card-holder-name"
                        name="rede_debit_holder_name"
                        class="field-input"
                        type="text"
                        maxlength="30"
                        autocomplete="cc-name"
                        placeholder="<?php echo esc_attr($lkn_ph('holder_name', 'John Doe')); ?>" />
                </div>
            </div>

            <!-- Número do cartão + tipo do cartão (½ cada) -->
            <div class="field-group">
                <div class="modern-field field-half">
                    <label class="field-label" for="rede-debit-card-number">
                        <?php echo esc_html($lkn_lbl('card_number')); ?><span class="required">*</span>
                    </label>
                    <div class="field-wrapper">
                        <input id="rede-debit-card-number"
                            name="rede_debit_number"
                            class="field-input wc-debit-card-form-card-number"
                            type="tel"
                            maxlength="22"
                            inputmode="numeric"
                            autocomplete="cc-number"
                            placeholder="<?php echo esc_attr($lkn_ph('card_number', '0000 0000 0000 0000')); ?>" />
                        <div class="field-icon"><img src="<?php echo esc_url($lkn_asset . 'lock.svg'); ?>" alt="" aria-hidden="true" /></div>
                        <input id="rede-debit-card-nonce" name="rede_card_nonce" type="hidden" value="<?php echo esc_attr(wp_create_nonce('redeCardNonce')); ?>">
                    </div>
                </div>

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

                // Com um único tipo, o seletor é exibido porém "travado" (cinza, sem usar o atributo disabled).
                $lkn_lock_card_type_selector = ($card_type_restriction !== 'both' && !$lkn_hide_card_type_selector);

                $lkn_card_type_select_style = '';
                if ($lkn_lock_card_type_selector) {
                    $lkn_card_type_select_style .= ' background-color: #f0f0f1 !important; color: #767676 !important; pointer-events: none; cursor: not-allowed;';
                }
                ?>
                <div class="modern-field field-half" id="rede-debit-card-type-wrapper"<?php echo $lkn_hide_card_type_selector ? ' style="display: none;"' : ''; ?>>
                    <label class="field-label" for="rede-debit-card-type">
                        <?php echo esc_html($lkn_lbl('card_type')); ?><span class="required">*</span>
                    </label>
                    <select
                        id="rede-debit-card-type"
                        name="rede_debit_card_type"
                        class="field-select lknIntegrationRedeForWoocommerceSelect"
                        style="<?php echo esc_attr($lkn_card_type_select_style); ?>"
                        autocomplete="off"
                        <?php if ($lkn_lock_card_type_selector) : ?>data-lkn-locked="true" aria-disabled="true" tabindex="-1"<?php endif; ?>>
                        <?php foreach ($lkn_card_type_options as $lkn_card_type_value => $lkn_card_type_label) : ?>
                            <option value="<?php echo esc_attr($lkn_card_type_value); ?>" <?php echo ($lkn_card_type_selected === $lkn_card_type_value) ? 'selected' : ''; ?>><?php echo esc_html($lkn_card_type_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Validade + código de segurança (½ cada) -->
            <div class="field-group">
                <div class="modern-field field-half">
                    <label class="field-label" for="rede-debit-card-expiry">
                        <?php echo esc_html($lkn_lbl('expiry')); ?><span class="required">*</span>
                    </label>
                    <div class="field-wrapper">
                        <input id="rede-debit-card-expiry"
                            name="rede_debit_expiry"
                            class="field-input wc-debit-card-form-card-expiry"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="cc-exp"
                            placeholder="<?php echo esc_attr($lkn_ph('expiry', 'MM/AA')); ?>" />
                        <div class="field-icon"><img src="<?php echo esc_url($lkn_asset . 'calendar.svg'); ?>" alt="" aria-hidden="true" /></div>
                    </div>
                </div>
                <div class="modern-field field-half">
                    <label class="field-label" for="rede-debit-card-cvc">
                        <?php echo esc_html($lkn_lbl('cvc')); ?><span class="required">*</span>
                    </label>
                    <div class="field-wrapper">
                        <input id="rede-debit-card-cvc"
                            name="rede_debit_cvc"
                            class="field-input wc-debit-card-form-card-cvc"
                            type="tel"
                            maxlength="4"
                            inputmode="numeric"
                            autocomplete="cc-csc"
                            placeholder="<?php echo esc_attr($lkn_ph('cvc', 'CVC')); ?>" />
                        <div class="field-icon"><img src="<?php echo esc_url($lkn_asset . 'key.svg'); ?>" alt="" aria-hidden="true" /></div>
                    </div>
                </div>
            </div>

            <?php if (($card_type_restriction === 'credit_only' || $card_type_restriction === 'both') && is_array($installments) && count($installments) > 1) : ?>
            <div class="modern-field" id="rede-debit-installments-wrapper" <?php echo ($card_type_restriction === 'both' && $card_type === 'debit') ? 'style="display: none;"' : ''; ?>>
                <label class="field-label" for="rede-debit-card-installments">
                    <?php echo esc_html($lkn_lbl('installments')); ?><span class="required">*</span>
                </label>
                <select
                    id="rede-debit-card-installments"
                    name="rede_debit_installments"
                    class="field-select lknIntegrationRedeForWoocommerceSelect"
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
            <?php endif; ?>

            <div class="payment-submit-section">
                <button type="button" id="rede-debit-submit-btn" class="modern-submit-button">
                    <?php echo esc_html($lkn_lbl('button')); ?>
                </button>
            </div>

            <div class="modern-gateway-description">
                <?php echo esc_html($integration_rede_for_woocommerce_option['description'] ?? __('Pay for your purchase with a debit card through', 'woo-rede')); ?>
            </div>
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
