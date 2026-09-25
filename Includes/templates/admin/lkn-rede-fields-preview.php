<?php
/**
 * Editor visual da seção "Fields" do gateway Rede Débito/Crédito.
 *
 * Renderiza o "resultado": o formulário de checkout em duas camadas
 * (Blocks/Gutenberg e Classic/shortcode) e três templates (standard/modern/
 * compact), usando as MESMAS classes/estrutura do checkout real.
 *
 * Regras de edição (lápis):
 *  - Label: editável em todos os templates, EXCETO o select "tipo de cartão"
 *    no template moderno (que não usa label).
 *  - Placeholder: existe quando a label fica ACIMA do input — em TODOS os
 *    templates do clássico/shortcode e, nos blocos/Gutenberg, apenas no compacto
 *    (nos blocos, standard e modern usam a label flutuante dentro do input).
 *
 * IMPORTANTE: o preview não pode conter <p> sem classe — o script de layout do
 * painel move o menu de abas para depois do último <p> sem classe.
 *
 * Espera $gateway_id (string) definida pelo gateway que inclui este arquivo.
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */

if (! defined('ABSPATH')) {
    exit();
}

$lkn_templates   = \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getCheckoutFieldTemplates();
$lkn_defs        = \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getCheckoutFieldDefinitions();
$lkn_text_fields = array('holder_name', 'card_number', 'expiry', 'cvc');
$lkn_asset       = plugin_dir_url(__FILE__) . '../../assets/cardTemplate/';

// Observação: as bandeiras dentro do CAMPO de número do layout compacto
// (.rede-compact-card-brands) existem apenas no checkout real — são dinâmicas
// (realçam a bandeira conforme o número digitado). No preview do admin elas não
// fazem sentido e por isso NÃO são renderizadas aqui.

$lkn_label_val = function ($mode, $tpl, $field) use ($gateway_id) {
    return \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getFieldLabel($gateway_id, $tpl, $field, $mode);
};
$lkn_ph_val = function ($mode, $tpl, $field) use ($gateway_id) {
    return \Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper::getFieldPlaceholder($gateway_id, $tpl, $field, $mode);
};

// Botão "lápis" (edição inline de label/placeholder).
$lkn_pencil = function ($mode, $tpl, $field, $kind, $extra = '') {
    return sprintf(
        '<button type="button" class="lkn-edit-btn %1$s" data-lkn-field="%2$s" data-lkn-kind="%3$s" data-lkn-template="%4$s" data-lkn-mode="%5$s" title="%6$s" aria-label="%6$s"><span class="lkn-edit-btn__icon" aria-hidden="true">&#9998;</span></button>',
        esc_attr($extra),
        esc_attr($field),
        esc_attr($kind),
        esc_attr($tpl),
        esc_attr($mode),
        esc_attr__('Edit', 'woo-rede')
    );
};

// Label (com lápis editável opcional e ícone opcional).
$lkn_edit_label = function ($mode, $tpl, $field, $class, $for, $editable = true, $icon = '') use ($lkn_label_val, $lkn_pencil) {
    return sprintf(
        '<label class="%1$s" for="%2$s"><span class="lkn-edit-text" data-lkn-field="%3$s" data-lkn-kind="label" data-lkn-template="%4$s" data-lkn-mode="%5$s">%6$s</span><span class="required">*</span>%7$s%8$s</label>',
        esc_attr($class),
        esc_attr($for),
        esc_attr($field),
        esc_attr($tpl),
        esc_attr($mode),
        esc_html($lkn_label_val($mode, $tpl, $field)),
        $editable ? $lkn_pencil($mode, $tpl, $field, 'label') : '',
        $icon
    );
};

// Botão de finalizar com o TEXTO editável (como as labels). O lápis fica FORA do
// <button> (não dá para aninhar <button> em <button>), posicionado sobre o botão.
$lkn_btn = function ($mode, $tpl, $class, $extra = '') use ($lkn_label_val, $lkn_pencil) {
    return '<span class="lkn-preview-btn"><button type="button" class="' . esc_attr($class) . '" disabled' . $extra . '>'
        . '<span class="lkn-edit-text" data-lkn-field="button" data-lkn-kind="label" data-lkn-template="' . esc_attr($tpl) . '" data-lkn-mode="' . esc_attr($mode) . '">' . esc_html($lkn_label_val($mode, $tpl, 'button')) . '</span>'
        . '</button>' . $lkn_pencil($mode, $tpl, 'button', 'label', 'lkn-edit-btn--over') . '</span>';
};

$lkn_select = function ($opts, $class = '') {
    return sprintf('<select class="%s" disabled aria-disabled="true" tabindex="-1" style="background-color:#f0f0f1 !important;color:#767676 !important;pointer-events:none;cursor:not-allowed;">%s</select>', esc_attr($class), $opts);
};

$lkn_installments_opts = '';
for ($i = 1; $i <= 12; $i++) {
    $lkn_installments_opts .= sprintf('<option value="%1$d">%1$dx</option>', $i);
}
$lkn_card_type_opts = '<option value="credit" selected>' . esc_html__('Credit Card', 'woo-rede') . '</option><option value="debit">' . esc_html__('Debit Card', 'woo-rede') . '</option>';
$lkn_description    = __('Pay for your purchase with a debit card through', 'woo-rede');

/**
 * Renderiza um preview.
 *
 * @param string $mode blocks|classic
 * @param string $tpl  standard|modern|compact
 */
$lkn_render = function ($mode, $tpl) use (
    $lkn_text_fields, $lkn_btn, $lkn_edit_label, $lkn_select, $lkn_pencil, $lkn_ph_val, $lkn_label_val,
    $lkn_installments_opts, $lkn_card_type_opts, $lkn_description, $lkn_asset
) {
    $blocks   = 'blocks' === $mode;
    // Placeholder: todos os templates do clássico; nos blocos só o compacto.
    $has_ph   = $blocks ? ('compact' === $tpl) : true;
    // Sem label de "tipo de cartão" apenas no modern dos Blocos (o shortcode
    // modern mostra a label, como no checkout real).
    $type_lbl = ! ($blocks && 'modern' === $tpl);
    $ct_label = $type_lbl ? $lkn_edit_label($mode, $tpl, 'card_type', '', 'lkn-preview-type') : '';
    $ins_label = $lkn_edit_label($mode, $tpl, 'installments', '', 'lkn-preview-installments');
    $img       = function ($name, $class, $alt = '') use ($lkn_asset) {
        return '<img src="' . esc_url($lkn_asset . $name) . '" alt="' . esc_attr($alt) . '" class="' . esc_attr($class) . '" aria-hidden="true" />';
    };

    // Atributos comuns a um input com placeholder personalizável (permite o lápis
    // atualizar o preview ao vivo).
    $ph_attrs = function ($field) use ($has_ph, $mode, $tpl, $lkn_ph_val) {
        if (! $has_ph) {
            return '';
        }
        return sprintf(
            ' placeholder="%s" data-lkn-ph-field="%s" data-lkn-mode="%s" data-lkn-template="%s"',
            esc_attr($lkn_ph_val($mode, $tpl, $field)),
            esc_attr($field),
            esc_attr($mode),
            esc_attr($tpl)
        );
    };

    // Campo no formato Blocks (label flutuante) — input + label (+ lápis do placeholder no compacto).
    $block_field = function ($field, $id) use ($has_ph, $mode, $tpl, $lkn_edit_label, $lkn_pencil, $ph_attrs) {
        return '<div class="wc-block-components-text-input">'
            . sprintf('<input type="text" id="%s" class="wc-block-components-text-input__input"%s readonly />', esc_attr($id), $ph_attrs($field))
            . $lkn_edit_label($mode, $tpl, $field, '', $id)
            . ($has_ph ? $lkn_pencil($mode, $tpl, $field, 'placeholder', 'lkn-edit-btn--ph') : '')
            . '</div>';
    };

    // Campo no formato classic.
    $classic_field = function ($field, $id, $style = '', $wrap_class = 'lkn-input-wrap', $extra = '') use ($has_ph, $mode, $tpl, $lkn_pencil, $ph_attrs) {
        $style_attr = '' !== $style ? sprintf(' style="%s"', esc_attr($style)) : '';
        return '<div class="' . esc_attr($wrap_class) . '">'
            . sprintf('<input type="text" id="%s" class="input-text"%s%s readonly />', esc_attr($id), $ph_attrs($field), $style_attr)
            . ($has_ph ? $lkn_pencil($mode, $tpl, $field, 'placeholder', 'lkn-edit-btn--ph') : '')
            . $extra
            . '</div>';
    };

    // Campo no formato Moderno (label acima do input) — input + lápis do placeholder.
    $modern_field = function ($field, $id) use ($has_ph, $mode, $tpl, $lkn_pencil, $ph_attrs) {
        return sprintf('<input type="text" id="%s" class="field-input"%s readonly />', esc_attr($id), $ph_attrs($field))
            . ($has_ph ? $lkn_pencil($mode, $tpl, $field, 'placeholder', 'lkn-edit-btn--ph') : '');
    };

    // Botão "Place order" (formato do checkout em Blocos), igual ao do Cielo.
    // O botão tem margin-top próprio, então não há espaçador antes dele.
    $order_button = '<div class="lkn-preview-order">'
        . $lkn_btn($mode, $tpl, 'wc-block-components-button wp-element-button contained')
        . '</div>';

    // ---------- Blocks ----------
    if ($blocks) {
        if ('modern' === $tpl) {
            echo '<div class="modern-template-container">';
            echo '<div class="modern-field-row-full">' . $block_field('holder_name', 'lkn-preview-holder') . '</div>';
            echo '<div class="modern-field-row-half"><div class="modern-field-with-icon">' . $block_field('card_number', 'lkn-preview-number') . $img('lock.svg', 'modern-field-icon') . '</div>';
            echo '<div class="modern-select-wrapper">' . $lkn_select($lkn_card_type_opts, 'modern-select') . '</div></div>';
            echo '<div class="modern-field-row-half"><div class="modern-field-with-icon">' . $block_field('expiry', 'lkn-preview-expiry') . $img('calendar.svg', 'modern-field-icon') . '</div>';
            echo '<div class="modern-field-with-icon">' . $block_field('cvc', 'lkn-preview-cvc') . $img('key.svg', 'modern-field-icon') . '</div></div>';
            echo '<div class="modern-field-row-full"><div class="modern-select-wrapper">' . $ins_label . $lkn_select($lkn_installments_opts, 'modern-select') . '</div></div>';
            echo '<div class="modern-field-row-full">' . $lkn_btn($mode, $tpl, 'modern-submit-button') . '</div>';
            echo '<div class="modern-gateway-description lkn-preview-description">' . esc_html($lkn_description) . '</div>';
            echo '</div>';
            return;
        }

        if ('compact' === $tpl) {
            echo '<div id="radio-control-wc-payment-method-options-rede_debit__content"><div class="rede-compact-container">';
            echo '<div class="rede-compact-row rede-compact-row--top">';
            echo '<div class="rede-compact-field rede-compact-field--name">' . $block_field('holder_name', 'lkn-preview-holder') . '</div>';
            echo '<div class="rede-compact-field rede-compact-field--type">' . $ct_label . '<select class="rede-compact-select" disabled aria-disabled="true" tabindex="-1" style="background-color:#f0f0f1 !important;color:#767676 !important;pointer-events:none;cursor:not-allowed;">' . $lkn_card_type_opts . '</select></div>';
            echo '</div>';
            echo '<div class="rede-compact-row rede-compact-row--card">';
            echo '<div class="rede-compact-field rede-compact-field--number"><div class="rede-compact-field-with-icon">' . $block_field('card_number', 'lkn-preview-number') . '</div></div>';
            echo '<div class="rede-compact-field rede-compact-field--exp"><div class="rede-compact-field-with-icon">' . $block_field('expiry', 'lkn-preview-expiry') . $img('calendar.svg', 'rede-compact-field-icon') . '</div></div>';
            echo '<div class="rede-compact-field rede-compact-field--cvc"><div class="rede-compact-field-with-icon">' . $block_field('cvc', 'lkn-preview-cvc') . $img('key.svg', 'rede-compact-field-icon') . '</div></div>';
            echo '</div>';
            echo '<div class="rede-compact-row rede-compact-row--installments"><div class="rede-compact-field rede-compact-field--installments">' . $ins_label . '<select class="rede-compact-select" disabled aria-disabled="true" tabindex="-1" style="background-color:#f0f0f1 !important;color:#767676 !important;pointer-events:none;cursor:not-allowed;">' . $lkn_installments_opts . '</select></div></div>';
            echo '<div class="rede-compact-row rede-compact-row--submit">' . $lkn_btn($mode, $tpl, 'rede-compact-submit-button') . '</div>';
            echo '<div class="rede-compact-description lkn-preview-description">' . esc_html($lkn_description) . '</div>';
            echo '</div></div>';
            return;
        }

        // Blocks básico (1 campo por linha) — mesma estrutura do checkout em blocos.
        echo '<div id="radio-control-wc-payment-method-options-rede_debit__content" class="wc-block-components-radio-control-accordion-content">';
        foreach ($lkn_text_fields as $field) {
            echo $block_field($field, 'lkn-preview-' . $field); // phpcs:ignore
        }
        echo '<div class="lknIntegrationRedeForWoocommerceSelectBlocks lknIntegrationRedeForWoocommerceSelect3dsInstallments">' . $lkn_edit_label($mode, $tpl, 'card_type', '', 'lkn-preview-type') . $lkn_select($lkn_card_type_opts, 'lknIntegrationRedeForWoocommerceSelect') . '</div>';
        echo '<div class="lknIntegrationRedeForWoocommerceSelectBlocks">' . $ins_label . $lkn_select($lkn_installments_opts, 'lknIntegrationRedeForWoocommerceSelect') . '</div>';
        echo $order_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="basic-gateway-description lkn-preview-description" style="text-align:center;">' . esc_html($lkn_description) . '</div>';
        echo '</div>';
        return;
    }

    // ---------- Classic / shortcode ----------
    if ('compact' === $tpl) {
        echo '<fieldset class="rede-payment-form rede-compact-classic">';
        echo '<div class="rede-debit-fields-wrapper rede-compact-wrapper"><div class="wc-payment-rede-form-fields rede-compact-fields">';
        echo '<div class="rede-compact-row rede-compact-row--top">';
        echo '<div class="rede-compact-field rede-compact-field--name">' . $lkn_edit_label($mode, $tpl, 'holder_name', '', 'lkn-preview-holder') . $classic_field('holder_name', 'lkn-preview-holder', '', 'rede-compact-input-wrap') . '</div>';
        echo '<div class="rede-compact-field rede-compact-field--type" id="rede-debit-card-type-wrapper">' . $lkn_edit_label($mode, $tpl, 'card_type', '', 'lkn-preview-type') . '<div class="rede-compact-input-wrap">' . $lkn_select($lkn_card_type_opts, 'input-select lknIntegrationRedeForWoocommerceSelect rede-compact-select') . '</div></div>';
        echo '</div>';
        echo '<div class="rede-compact-row rede-compact-row--card">';
        echo '<div class="rede-compact-field rede-compact-field--number">' . $lkn_edit_label($mode, $tpl, 'card_number', '', 'lkn-preview-number') . $classic_field('card_number', 'lkn-preview-number', '', 'rede-compact-input-wrap') . '</div>';
        echo '<div class="rede-compact-field rede-compact-field--exp">' . $lkn_edit_label($mode, $tpl, 'expiry', '', 'lkn-preview-expiry') . $classic_field('expiry', 'lkn-preview-expiry', '', 'rede-compact-input-wrap') . '</div>';
        echo '<div class="rede-compact-field rede-compact-field--cvc">' . $lkn_edit_label($mode, $tpl, 'cvc', '', 'lkn-preview-cvc') . $classic_field('cvc', 'lkn-preview-cvc', '', 'rede-compact-input-wrap') . '</div>';
        echo '</div>';
        echo '<div class="rede-compact-row rede-compact-row--installments" id="rede-debit-installments-wrapper"><div class="rede-compact-field rede-compact-field--installments">' . $ins_label . '<div class="rede-compact-input-wrap">' . $lkn_select($lkn_installments_opts, 'input-select lknIntegrationRedeForWoocommerceSelect rede-compact-select') . '</div></div></div>';
        echo '<div class="payment-submit-section">' . $lkn_btn($mode, $tpl, 'rede-compact-submit-button') . '</div>';
        echo '<div class="payment-method-description"><p class="lkn-preview-note lkn-preview-description">' . esc_html($lkn_description) . '</p></div>';
        echo '<div class="clear"></div></div></div></fieldset>';
        return;
    }

    // Classic Moderno (shortcode): campos em grid ½, label acima. (A faixa de
    // bandeiras existe só no checkout, não no preview.)
    if ('modern' === $tpl) {
        echo '<fieldset class="rede-payment-form rede-modern-classic">';
        echo '<div class="rede-modern-wrapper">';
        echo '<div class="rede-modern-form-fields">';
        echo '<div class="modern-field">' . $lkn_edit_label($mode, $tpl, 'holder_name', 'field-label', 'lkn-preview-holder') . '<div class="field-wrapper">' . $modern_field('holder_name', 'lkn-preview-holder') . '</div></div>';
        echo '<div class="field-group">';
        echo '<div class="modern-field field-half">' . $lkn_edit_label($mode, $tpl, 'card_number', 'field-label', 'lkn-preview-number') . '<div class="field-wrapper">' . $modern_field('card_number', 'lkn-preview-number') . '<div class="field-icon">' . $img('lock.svg', '') . '</div></div></div>';
        echo '<div class="modern-field field-half">' . $lkn_edit_label($mode, $tpl, 'card_type', 'field-label', 'lkn-preview-type') . '<div class="field-wrapper">' . $lkn_select($lkn_card_type_opts, 'field-select') . '</div></div>';
        echo '</div>';
        echo '<div class="field-group">';
        echo '<div class="modern-field field-half">' . $lkn_edit_label($mode, $tpl, 'expiry', 'field-label', 'lkn-preview-expiry') . '<div class="field-wrapper">' . $modern_field('expiry', 'lkn-preview-expiry') . '<div class="field-icon">' . $img('calendar.svg', '') . '</div></div></div>';
        echo '<div class="modern-field field-half">' . $lkn_edit_label($mode, $tpl, 'cvc', 'field-label', 'lkn-preview-cvc') . '<div class="field-wrapper">' . $modern_field('cvc', 'lkn-preview-cvc') . '<div class="field-icon">' . $img('key.svg', '') . '</div></div></div>';
        echo '</div>';
        echo '<div class="modern-field" id="rede-debit-installments-wrapper">' . $lkn_edit_label($mode, $tpl, 'installments', 'field-label', 'lkn-preview-installments') . '<div class="field-wrapper">' . $lkn_select($lkn_installments_opts, 'field-select') . '</div></div>';
        echo '<div class="payment-submit-section">' . $lkn_btn($mode, $tpl, 'modern-submit-button') . '</div>';
        echo '<div class="modern-gateway-description lkn-preview-description">' . esc_html($lkn_description) . '</div>';
        echo '</div></div></fieldset>';
        return;
    }

    // Classic padrão (1 campo por linha, sem ícones).
    echo '<fieldset class="rede-payment-form">';
    echo '<div class="rede-debit-fields-wrapper"><div class="wc-payment-rede-form-fields">';
    foreach ($lkn_text_fields as $field) {
        echo '<div class="form-row form-row lkn-preview-textfield">' . $lkn_edit_label($mode, $tpl, $field, '', 'lkn-preview-' . $field) . $classic_field($field, 'lkn-preview-' . $field, '') . '</div>'; // phpcs:ignore
    }
    echo '<div class="form-row form-row" id="rede-debit-card-type-wrapper">' . $ct_label . '<div class="lkn-input-wrap">' . $lkn_select($lkn_card_type_opts, 'input-select lknIntegrationRedeForWoocommerceSelect') . '</div></div>';
    echo '<div class="form-row form-row" id="rede-debit-installments-wrapper">' . $ins_label . '<div class="lkn-input-wrap">' . $lkn_select($lkn_installments_opts, 'input-select lknIntegrationRedeForWoocommerceSelect') . '</div></div>';
    echo $order_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<div class="payment-method-description"><p class="lkn-preview-note lkn-preview-description">' . esc_html($lkn_description) . '</p></div>';
    echo '<div class="clear"></div></div></div></fieldset>';
};
?>
<div class="lkn-fields-editor__stage">
    <?php foreach (array('blocks', 'classic') as $lkn_mode) : ?>
        <?php foreach ($lkn_templates as $lkn_tpl => $lkn_tpl_label) : ?>
            <div class="lkn-fields-preview lkn-fields-preview--<?php echo esc_attr($lkn_mode . '-' . $lkn_tpl); ?>"
                 data-mode="<?php echo esc_attr($lkn_mode); ?>"
                 data-template="<?php echo esc_attr($lkn_tpl); ?>"
                 hidden>
                <?php $lkn_render($lkn_mode, $lkn_tpl); ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
