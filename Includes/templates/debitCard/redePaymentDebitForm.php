<?php
if (! defined('ABSPATH')) {
    exit();
}
$integration_rede_for_woocommerce_option = get_option('woocommerce_rede_debit_settings');

// Labels personalizáveis (seção "Fields" do admin, recurso PRO).
// O shortcode clássico usa este markup para os layouts Padrão E Moderno, onde a
// label fica ACIMA do input — por isso o placeholder é personalizável aqui
// (diferente dos Blocos, onde a label é flutuante dentro do input).
$lkn_fields_gtw = 'rede_debit';
$lkn_fields_tpl = \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getActiveCheckoutTemplate($lkn_fields_gtw);
if ('modern' !== $lkn_fields_tpl) {
    $lkn_fields_tpl = 'standard';
}
$lkn_lbl = function ($field) use ($lkn_fields_gtw, $lkn_fields_tpl) {
    return \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getFieldLabel($lkn_fields_gtw, $lkn_fields_tpl, $field, 'classic');
};
$lkn_ph = function ($field, $default) use ($lkn_fields_gtw, $lkn_fields_tpl) {
    $custom = \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getFieldOverride($lkn_fields_gtw, $lkn_fields_tpl, $field, 'placeholder', $default, 'classic');
    return '' !== $custom ? $custom : $default;
};

// Cartão animado: controlado pela opção "Show animated card" (default ligado).
$lkn_show_card_animation = isset($show_card_animation) ? $show_card_animation : 'yes';
// Logo Rede: escondida só quando a opção PRO "Hide Rede logo" está ligada.
$lkn_hide_rede_logo = isset($hide_rede_logo) ? $hide_rede_logo : 'no';

// Bandeiras no topo do formulário (opção "Show card brand icons") — recurso
// separado, ativável em qualquer layout (igual ao Cielo). Faixa estática com
// todas as bandeiras. NÃO confundir com as bandeiras do CAMPO do compacto.
$lkn_show_card_brand_icons = isset($show_card_brand_icons) ? $show_card_brand_icons : 'yes';
// Recurso PRO: oculta o campo do titular; o nome é obtido do pedido.
$lkn_show_cardholder = isset($show_cardholder_name) ? $show_cardholder_name : 'no';
$lkn_brand_asset = plugin_dir_url(__FILE__) . '../../assets/cardTemplate/';
$lkn_top_brands = array(
    'visa'       => array('label' => __('Visa', 'woo-rede'), 'file' => 'visa-icon.svg'),
    'mastercard' => array('label' => __('Mastercard', 'woo-rede'), 'file' => 'mastercard-icon.svg'),
    'amex'       => array('label' => __('American Express', 'woo-rede'), 'file' => 'amex-icon.svg'),
    'elo'        => array('label' => __('Elo', 'woo-rede'), 'file' => 'elo-icon.svg'),
    'other_card' => array('label' => __('Other Card', 'woo-rede'), 'file' => 'other-card.svg'),
);

?>
<fieldset id="rede-debit-payment-form" class="rede-payment-form">
    <div class="rede-debit-fields-wrapper">
        <?php if ('yes' === $lkn_show_card_brand_icons) : ?>
        <div class="rede-card-brands-container" id="rede-debit-card-brands">
            <div class="rede-card-brands">
                <?php foreach ($lkn_top_brands as $lkn_brand_key => $lkn_brand) : ?>
                    <img src="<?php echo esc_url($lkn_brand_asset . $lkn_brand['file']); ?>" alt="<?php echo esc_attr($lkn_brand['label'] . ' logo'); ?>" title="<?php echo esc_attr($lkn_brand['label']); ?>" data-brand="<?php echo esc_attr($lkn_brand_key); ?>" class="rede-card-brand-icon" />
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ('yes' === $lkn_show_card_animation) : ?>
        <div id="rede-debit-card-animation" class="card-wrapper card-animation"></div>
        <?php endif; ?>
        <div class="wc-payment-rede-form-fields">
            <?php if ('yes' === $lkn_show_cardholder) : ?>
            <!-- Recurso PRO: campo do titular oculto; o nome é obtido do pedido. -->
            <input type="hidden" id="rede-debit-card-holder-name" name="rede_debit_holder_name" value="" />
            <?php else : ?>
            <div class="form-row form-row">
                <label class="labels-with-icons" for="rede-debit-card-holder-name">
                    <?php echo esc_html($lkn_lbl('holder_name')); ?><span class="required">*</span>
                </label>
                <input id="rede-debit-card-holder-name"
                    name="rede_debit_holder_name" class="input-text"
                    type="text"
                    placeholder="<?php echo esc_attr($lkn_ph('holder_name', 'John Doe')); ?>"
                    maxlength="30" autocomplete="off"
                    style="font-size: 21px; padding: 8px 45px;" />
            </div>
            <?php endif; ?>

            <div class="form-row form-row">
                <label class="labels-with-icons" for="rede-debit-card-number">
                    <?php echo esc_html($lkn_lbl('card_number')); ?>
                    <span class="required">*</span>
                </label>
                <input
                    id="rede-debit-card-number"
                    name="rede_debit_number"
                    class="input-text jp-card-invalid wc-debit-card-form-card-number"
                    type="tel"
                    maxlength="22" autocomplete="off"
                    placeholder="<?php echo esc_attr($lkn_ph('card_number', '0000 0000 0000 0000')); ?>"
                    style="font-size: 21px; padding: 8px 45px;" />
                <input
                    id="rede-debit-card-nonce"
                    name="rede_card_nonce"
                    type="hidden"
                    value="<?php echo esc_attr(wp_create_nonce('redeCardNonce')) ?>">
            </div>

            <div class="form-row form-row">
                <label class="labels-with-icons" for="rede-debit-card-expiry">
                    <?php echo esc_html($lkn_lbl('expiry')); ?><span class="required">*</span>
                </label>
                <input id="rede-debit-card-expiry"
                    name="rede_debit_expiry"
                    class="input-text wc-debit-card-form-card-expiry"
                    type="tel"
                    autocomplete="off"
                    placeholder="<?php echo esc_attr($lkn_ph('expiry', 'MM/AA')); ?>"
                    style="font-size: 21px; padding: 8px 30px 8px 35px;" />
            </div>

            <div class="form-row form-row">
                <label class="labels-with-icons" for="rede-debit-card-cvc"><?php echo esc_html($lkn_lbl('cvc')); ?><span class="required">*</span>
                </label>
                <input id="rede-debit-card-cvc"
                    name="rede_debit_cvc"
                    class="input-text wc-debit-card-form-card-cvc"
                    type="tel"
                    autocomplete="off"
                    placeholder="<?php echo esc_attr($lkn_ph('cvc', 'CVC')); ?>"
                    style="font-size: 21px; padding: 8px 30px 8px 35px;" />
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

            // Com um único tipo, o seletor é exibido porém "travado": fica cinza com cara de
            // disabled (SEM usar o atributo disabled, que faria o campo ser ignorado no envio).
            $lkn_lock_card_type_selector = ($card_type_restriction !== 'both' && !$lkn_hide_card_type_selector);

            $lkn_card_type_select_style = 'font-size: 21px; padding: 10px; width: 100%;';
            if ($lkn_lock_card_type_selector) {
                $lkn_card_type_select_style .= ' background-color: #f0f0f1; color: #767676; pointer-events: none; cursor: not-allowed;';
            }
            ?>
            <div class="form-row form-row" id="rede-debit-card-type-wrapper"<?php echo $lkn_hide_card_type_selector ? ' style="display: none;"' : ''; ?>>
                <label for="rede-debit-card-type">
                    <?php echo esc_html($lkn_lbl('card_type')); ?>
                    <span class="required">*</span>
                </label>
                <select 
                    id="rede-debit-card-type"
                    name="rede_debit_card_type"
                    class="input-select lknIntegrationRedeForWoocommerceSelect"
                    style="<?php echo esc_attr($lkn_card_type_select_style); ?>"
                    autocomplete="off"
                    <?php if ($lkn_lock_card_type_selector) : ?>data-lkn-locked="true" aria-disabled="true" tabindex="-1"<?php endif; ?>>
                    <?php foreach ($lkn_card_type_options as $lkn_card_type_value => $lkn_card_type_label) : ?>
                        <option value="<?php echo esc_attr($lkn_card_type_value); ?>" <?php echo ($lkn_card_type_selected === $lkn_card_type_value) ? 'selected' : ''; ?>><?php echo esc_html($lkn_card_type_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (($card_type_restriction === 'credit_only' || $card_type_restriction === 'both') && is_array($installments) && count($installments) > 1) : ?>
            <div class="form-row form-row" id="rede-debit-installments-wrapper" <?php echo ($card_type_restriction === 'both' && $card_type === 'debit') ? 'style="display: none;"' : ''; ?>>
                <label for="rede-debit-card-installments">
                    <?php echo esc_html($lkn_lbl('installments')); ?>
                    <span class="required">*</span>
                </label>
                <select
                    id="rede-debit-card-installments"
                    name="rede_debit_installments"
                    class="input-select lknIntegrationRedeForWoocommerceSelect"
                    style="font-size: 21px; padding: 10px; width: 100%;"
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

            <?php
            // Botão de finalizar custom — recurso PRO (mesma regra dos layouts
            // moderno/compacto). Sem PRO, usa o botão nativo do WooCommerce.
            if (\Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::isProLicenseValid()) :
            ?>
            <div class="payment-submit-section">
                <button type="button" id="rede-debit-submit-btn" class="rede-basic-submit-button">
                    <?php echo esc_html($lkn_lbl('button')); ?>
                </button>
            </div>
            <?php endif; ?>

            <div class="clear"></div>
        </div>
    </div>

    <?php if ($card_type_restriction === 'both') : ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Função para controlar visibilidade das parcelas
            function toggleInstallments() {
                var cardType = $('#rede-debit-card-type').val();
                var installmentsWrapper = $('#rede-debit-installments-wrapper');
                
                if (cardType === 'credit') {
                    installmentsWrapper.show();
                } else {
                    installmentsWrapper.hide();
                    // Reset to 1 installment for debit
                    $('#rede-debit-card-installments').val('1');
                }
            }
            
            // Aplicar estado inicial
            toggleInstallments();
            
            // Event listener para mudanças
            $('#rede-debit-card-type').on('change', toggleInstallments);
        });
    </script>
    <?php endif; ?>

    <!-- Descrição do gateway (com logo) no rodapé, abaixo do botão de finalizar. -->
    <div class="payment-method-description">
        <p><?php echo esc_html($integration_rede_for_woocommerce_option['description'] ?? __('Pay for your purchase with a debit card through', 'woo-rede')); ?></p>
        <?php if ('yes' !== $lkn_hide_rede_logo) : ?>
        <svg id="logo-rede" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480.72 156.96">
            <defs>
                <style>
                    .cls-1 {
                        fill: #ff7800
                    }
                </style>
            </defs>
            <title>logo-rede</title>
            <path class="cls-1" d="M475.56 98.71h-106c-15.45 0-22-6-24.67-14.05h33.41c22.33 0 36.08-9.84 36.08-31.08S400.6 21.4 378.27 21.4h-10.62c-20 0-44.34 11.64-49.45 39.51h-29.89V0H263v60.91h-31.23c-29.94.15-46.61 15.31-48.79 37.8h-52.26c-15.45 0-22-6-24.67-14.05h33.41c22.33 0 36.08-9.84 36.08-31.08S161.8 21.4 139.47 21.4h-10.62c-20 0-44.34 11.64-49.45 39.51H57.47c-13.74 0-25.93 4.22-32.64 12.5V62.78H0v87.62c0 5 1.56 6.56 6.4 6.56h12.5c4.68 0 6.4-1.56 6.4-6.56v-34.51c0-26.08 16.4-31.24 33.27-31.24h21.06c5.26 25.88 26.93 38.26 52 38.26h54.48c6.26 15 21.21 22.8 45.17 22.8h14.52c23.74 0 43.73-16.87 43.73-41.7V84.65h28.87c5.26 25.88 26.93 38.26 52 38.26h105.16a5.23 5.23 0 0 0 5.15-5.31v-13.9a5.07 5.07 0 0 0-5.15-4.99zM127.91 45.14h12.34c5.62 0 9.53 2.34 9.53 8 0 5.31-3.9 7.81-9.53 7.81h-34.9c2.07-8.84 7.88-15.81 22.56-15.81zM263 104.8c0 9.84-7.49 16.87-17.18 16.87h-16.24c-13.12 0-21.71-5.15-21.71-18.12 0-12.65 8.59-18.9 21.71-18.9H263v20.15zm103.71-59.66H379c5.62 0 9.53 2.34 9.53 8 0 5.31-3.9 7.81-9.53 7.81h-34.9c2.12-8.84 7.9-15.81 22.61-15.81z"></path>
        </svg>
        <?php endif; ?>
    </div>

    <?php if ('yes' === $lkn_show_cardholder) : ?>
    <script type="text/javascript">
        // Recurso PRO: com o campo do titular oculto, espelha o nome de
        // faturamento (ou entrega) no campo virtual para a animação do cartão.
        jQuery(function ($) {
            function lknSyncHolderName() {
                var $first = $('#billing_first_name').length ? $('#billing_first_name') : $('#shipping_first_name');
                var $last = $('#billing_last_name').length ? $('#billing_last_name') : $('#shipping_last_name');
                var name = (($first.val() || '') + ' ' + ($last.val() || '')).trim();
                var $hidden = $('#rede-debit-card-holder-name');
                if ($hidden.length && $hidden.val() !== name) {
                    $hidden.val(name).trigger('input').trigger('change');
                }
            }
            $(document.body).on('input change blur', '#billing_first_name, #billing_last_name, #shipping_first_name, #shipping_last_name', lknSyncHolderName);
            $(document.body).on('updated_checkout', lknSyncHolderName);
            lknSyncHolderName();
            setTimeout(lknSyncHolderName, 300);
        });
    </script>
    <?php endif; ?>
</fieldset>