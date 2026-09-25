<?php
if (! defined('ABSPATH')) {
    exit();
}

$integration_rede_for_woocommerce_theme = wp_get_theme();

// Obtém o nome do tema
$integration_rede_for_woocommerce_theme_name = $integration_rede_for_woocommerce_theme->get('Name');

$integration_rede_for_woocommerce_selectDivClass = 'select-input';
$integration_rede_for_woocommerce_selectDivStyle = '';
$integration_rede_for_woocommerce_selectSpanStyle = '';
$integration_rede_for_woocommerce_selectStyle = '';
$integration_rede_for_woocommerce_option = get_option('woocommerce_maxipago_credit_settings');


/* switch ($integration_rede_for_woocommerce_theme_name) {
    case 'Hello Elementor':
        $integration_rede_for_woocommerce_selectDivClass = "
            input-text
            jp-card-invalid
            wc-credit-card-form-card-number-select-input
        ";
        $integration_rede_for_woocommerce_selectDivStyle="
            padding: 0;
            font-size: 0.8rem;
        ";
    break;

    case 'OceanWP':
        $integration_rede_for_woocommerce_selectDivClass = "
            input-text
            jp-card-invalid
            wc-credit-card-form-card-number-select-input
        ";
        $integration_rede_for_woocommerce_selectSpanStyle = "
            height: 148%;
        ";
        $integration_rede_for_woocommerce_selectStyle = "
            font-size: 1.95rem
        ";
    break;
} */

?>
<fieldset
    id="maxipago-credit-payment-form"
    class="maxipago-payment-form">
    <div class="payment-method-description">
        <p><?php echo esc_html($integration_rede_for_woocommerce_option['description'] ?? __('Pay for your purchase with a credit card through ', 'woo-rede')); ?>
        </p>
        <img
            id="logo-maxipago"
            src="<?php echo esc_url(plugins_url('../../Public/images/maxipago.png', plugin_dir_path(__FILE__))); ?>"
            alt="Logo Maxipago">
    </div>
    <div class="maxipago-credit-fields-wrapper">
        <div
            id="maxipago-card-animation"
            class="card-wrapper card-animation"></div>
        <div class="wc-payment-maxipago-form-fields">

            <?php if (
                get_option('wcbcf_settings', array('person_type' => ''))['person_type'] === "0" ||
                ! is_plugin_active('woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php')
            ) {    ?>
                <div class="form-row form-row">
                    <label
                        class="labels-with-icons"
                        for="maxipagoCreditCardCpf">
                        <?php echo esc_attr('CPF'); ?><span
                            class="required">*</span>
                        <div class="icon-maxipago-input">
                            <svg
                                version="1.1"
                                id="Capa_1"
                                xmlns="http://www.w3.org/2000/svg"
                                xmlns:xlink="http://www.w3.org/1999/xlink"
                                x="0px"
                                y="4px"
                                width="24px"
                                height="16px"
                                viewBox="0 0 216 146"
                                enable-background="new 0 0 216 146"
                                xml:space="preserve">
                                <g>
                                    <path
                                        class="svg"
                                        d="M107.999,73c8.638,0,16.011-3.056,22.12-9.166c6.111-6.11,9.166-13.483,9.166-22.12c0-8.636-3.055-16.009-9.166-22.12c-6.11-6.11-13.484-9.165-22.12-9.165c-8.636,0-16.01,3.055-22.12,9.165c-6.111,6.111-9.166,13.484-9.166,22.12c0,8.637,3.055,16.01,9.166,22.12C91.99,69.944,99.363,73,107.999,73z"
                                        style="fill: rgb(21, 140, 186);"></path>
                                    <path
                                        class="svg"
                                        d="M165.07,106.037c-0.191-2.743-0.571-5.703-1.141-8.881c-0.57-3.178-1.291-6.124-2.16-8.84c-0.869-2.715-2.037-5.363-3.504-7.943c-1.466-2.58-3.15-4.78-5.052-6.6s-4.223-3.272-6.965-4.358c-2.744-1.086-5.772-1.63-9.085-1.63c-0.489,0-1.63,0.584-3.422,1.752s-3.815,2.472-6.069,3.911c-2.254,1.438-5.188,2.743-8.799,3.909c-3.612,1.168-7.237,1.752-10.877,1.752c-3.639,0-7.264-0.584-10.876-1.752c-3.611-1.166-6.545-2.471-8.799-3.909c-2.254-1.439-4.277-2.743-6.069-3.911c-1.793-1.168-2.933-1.752-3.422-1.752c-3.313,0-6.341,0.544-9.084,1.63s-5.065,2.539-6.966,4.358c-1.901,1.82-3.585,4.02-5.051,6.6s-2.634,5.229-3.503,7.943c-0.869,2.716-1.589,5.662-2.159,8.84c-0.571,3.178-0.951,6.137-1.141,8.881c-0.19,2.744-0.285,5.554-0.285,8.433c0,6.517,1.983,11.664,5.948,15.439c3.965,3.774,9.234,5.661,15.806,5.661h71.208c6.572,0,11.84-1.887,15.806-5.661c3.966-3.775,5.948-8.921,5.948-15.439C165.357,111.591,165.262,108.78,165.07,106.037z"
                                        style="fill: rgb(21, 140, 186);"></path>
                                </g>
                            </svg>
                        </div>
                    </label>
                    <input
                        id="maxipagoCreditCardCpf"
                        name="maxipago_credit_card_cpf"
                        class="input-text"
                        type="text"
                        placeholder="<?php echo esc_attr('123.456.789-12'); ?>"
                        maxlength="22"
                        autocomplete="off"
                        style="font-size: 1.5em; padding: 8px 45px;" />
                </div>
            <?php } ?>

            <div class="form-row form-row">
                <label
                    class="labels-with-icons"
                    for="maxipago-card-holder-name">
                    <?php esc_attr_e('Name on Card', 'woo-rede'); ?><span
                        class="required">*</span>
                    <div class="icon-maxipago-input">
                        <svg
                            version="1.1"
                            id="Capa_1"
                            xmlns="http://www.w3.org/2000/svg"
                            xmlns:xlink="http://www.w3.org/1999/xlink"
                            x="0px"
                            y="4px"
                            width="24px"
                            height="16px"
                            viewBox="0 0 216 146"
                            enable-background="new 0 0 216 146"
                            xml:space="preserve">
                            <g>
                                <path
                                    class="svg"
                                    d="M107.999,73c8.638,0,16.011-3.056,22.12-9.166c6.111-6.11,9.166-13.483,9.166-22.12c0-8.636-3.055-16.009-9.166-22.12c-6.11-6.11-13.484-9.165-22.12-9.165c-8.636,0-16.01,3.055-22.12,9.165c-6.111,6.111-9.166,13.484-9.166,22.12c0,8.637,3.055,16.01,9.166,22.12C91.99,69.944,99.363,73,107.999,73z"
                                    style="fill: rgb(21, 140, 186);"></path>
                                <path
                                    class="svg"
                                    d="M165.07,106.037c-0.191-2.743-0.571-5.703-1.141-8.881c-0.57-3.178-1.291-6.124-2.16-8.84c-0.869-2.715-2.037-5.363-3.504-7.943c-1.466-2.58-3.15-4.78-5.052-6.6s-4.223-3.272-6.965-4.358c-2.744-1.086-5.772-1.63-9.085-1.63c-0.489,0-1.63,0.584-3.422,1.752s-3.815,2.472-6.069,3.911c-2.254,1.438-5.188,2.743-8.799,3.909c-3.612,1.168-7.237,1.752-10.877,1.752c-3.639,0-7.264-0.584-10.876-1.752c-3.611-1.166-6.545-2.471-8.799-3.909c-2.254-1.439-4.277-2.743-6.069-3.911c-1.793-1.168-2.933-1.752-3.422-1.752c-3.313,0-6.341,0.544-9.084,1.63s-5.065,2.539-6.966,4.358c-1.901,1.82-3.585,4.02-5.051,6.6s-2.634,5.229-3.503,7.943c-0.869,2.716-1.589,5.662-2.159,8.84c-0.571,3.178-0.951,6.137-1.141,8.881c-0.19,2.744-0.285,5.554-0.285,8.433c0,6.517,1.983,11.664,5.948,15.439c3.965,3.774,9.234,5.661,15.806,5.661h71.208c6.572,0,11.84-1.887,15.806-5.661c3.966-3.775,5.948-8.921,5.948-15.439C165.357,111.591,165.262,108.78,165.07,106.037z"
                                    style="fill: rgb(21, 140, 186);"></path>
                            </g>
                        </svg>
                    </div>
                </label>
                <input
                    id="maxipago-card-holder-name"
                    name="maxipago_credit_holder_name"
                    class="input-text"
                    type="text"
                    placeholder="<?php echo esc_attr('John Doe'); ?>"
                    maxlength="30"
                    autocomplete="off"
                    style="font-size: 1.5em; padding: 8px 45px;" />
            </div>

            <div class="form-row form-row">
                <label
                    class="labels-with-icons"
                    for="maxipago-card-number">
                    <?php esc_attr_e('Card Number', 'woo-rede'); ?>
                    <span class="required">*</span>
                    <div class="icon-maxipago-input">
                        <svg
                            version="1.1"
                            id="Capa_1"
                            xmlns="http://www.w3.org/2000/svg"
                            xmlns:xlink="http://www.w3.org/1999/xlink"
                            x="0px"
                            y="3px"
                            width="24px"
                            height="17px"
                            viewBox="0 0 216 146"
                            enable-background="new 0 0 216 146"
                            xml:space="preserve">
                            <g>
                                <path
                                    class="svg"
                                    d="M182.385,14.258c-2.553-2.553-5.621-3.829-9.205-3.829H42.821c-3.585,0-6.653,1.276-9.207,3.829c-2.553,2.553-3.829,5.621-3.829,9.206v99.071c0,3.585,1.276,6.654,3.829,9.207c2.554,2.553,5.622,3.829,9.207,3.829H173.18c3.584,0,6.652-1.276,9.205-3.829s3.83-5.622,3.83-9.207V23.464C186.215,19.879,184.938,16.811,182.385,14.258z M175.785,122.536c0,0.707-0.258,1.317-0.773,1.834c-0.516,0.515-1.127,0.772-1.832,0.772H42.821c-0.706,0-1.317-0.258-1.833-0.773c-0.516-0.518-0.774-1.127-0.774-1.834V73h135.571V122.536z M175.785,41.713H40.214v-18.25c0-0.706,0.257-1.316,0.774-1.833c0.516-0.515,1.127-0.773,1.833-0.773H173.18c0.705,0,1.316,0.257,1.832,0.773c0.516,0.517,0.773,1.127,0.773,1.833V41.713z"
                                    style="fill: rgb(21, 140, 186);"></path>
                                <rect
                                    class="svg"
                                    x="50.643"
                                    y="104.285"
                                    width="20.857"
                                    height="10.429"
                                    style="fill: rgb(21, 140, 186);"></rect>
                                <rect
                                    class="svg"
                                    x="81.929"
                                    y="104.285"
                                    width="31.286"
                                    height="10.429"
                                    style="fill: rgb(21, 140, 186);"></rect>
                            </g>
                        </svg>
                    </div>
                </label>
                <input
                    id="maxipago-card-number"
                    name="maxipago_credit_number"
                    class="input-text jp-card-invalid wc-credit-card-form-card-number"
                    type="tel"
                    maxlength="22"
                    autocomplete="off"
                    placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;"
                    style="font-size: 1.5em; padding: 8px 45px;" />
                <input
                    id="maxipago-card-nonce"
                    name="maxipago_card_nonce"
                    type="hidden"
                    value="<?php echo esc_attr(wp_create_nonce('maxipagoCardNonce')) ?>">
            </div>

            <div class="form-row form-row">
                <label
                    class="labels-with-icons"
                    for="maxipago-card-expiry">
                    <?php esc_attr_e('Card Expiring Date', 'woo-rede'); ?><span
                        class="required">*</span>
                    <div class="icon-maxipago-input">
                        <svg
                            version="1.1"
                            id="Capa_1"
                            xmlns="http://www.w3.org/2000/svg"
                            xmlns:xlink="http://www.w3.org/1999/xlink"
                            x="0px"
                            y="4px"
                            width="24px"
                            height="16px"
                            viewBox="0 0 216 146"
                            enable-background="new 0 0 216 146"
                            xml:space="preserve">
                            <path
                                class="svg"
                                d="M172.691,23.953c-2.062-2.064-4.508-3.096-7.332-3.096h-10.428v-7.822c0-3.584-1.277-6.653-3.83-9.206c-2.554-2.553-5.621-3.83-9.207-3.83h-5.213c-3.586,0-6.654,1.277-9.207,3.83c-2.554,2.553-3.83,5.622-3.83,9.206v7.822H92.359v-7.822c0-3.584-1.277-6.653-3.83-9.206c-2.553-2.553-5.622-3.83-9.207-3.83h-5.214c-3.585,0-6.654,1.277-9.207,3.83c-2.553,2.553-3.83,5.622-3.83,9.206v7.822H50.643c-2.825,0-5.269,1.032-7.333,3.096s-3.096,4.509-3.096,7.333v104.287c0,2.823,1.032,5.267,3.096,7.332c2.064,2.064,4.508,3.096,7.333,3.096h114.714c2.824,0,5.27-1.032,7.332-3.096c2.064-2.064,3.096-4.509,3.096-7.332V31.286C175.785,28.461,174.754,26.017,172.691,23.953z M134.073,13.036c0-0.761,0.243-1.386,0.731-1.874c0.488-0.488,1.113-0.733,1.875-0.733h5.213c0.762,0,1.385,0.244,1.875,0.733c0.488,0.489,0.732,1.114,0.732,1.874V36.5c0,0.761-0.244,1.385-0.732,1.874c-0.49,0.488-1.113,0.733-1.875,0.733h-5.213c-0.762,0-1.387-0.244-1.875-0.733s-0.731-1.113-0.731-1.874V13.036z M71.501,13.036c0-0.761,0.244-1.386,0.733-1.874c0.489-0.488,1.113-0.733,1.874-0.733h5.214c0.761,0,1.386,0.244,1.874,0.733c0.488,0.489,0.733,1.114,0.733,1.874V36.5c0,0.761-0.244,1.386-0.733,1.874c-0.489,0.488-1.113,0.733-1.874,0.733h-5.214c-0.761,0-1.386-0.244-1.874-0.733c-0.488-0.489-0.733-1.113-0.733-1.874V13.036z M165.357,135.572H50.643V52.143h114.714V135.572z"
                                style="fill: rgb(21, 140, 186);"></path>
                        </svg>
                    </div>
                </label>
                <input
                    id="maxipago-card-expiry"
                    name="maxipago_credit_expiry"
                    class="input-text wc-credit-card-form-card-expiry"
                    type="tel"
                    autocomplete="off"
                    placeholder="<?php esc_attr_e('MM / YEAR', 'woo-rede'); ?>"
                    style="font-size: 1.5em; padding: 8px 30px 8px 35px;" />
            </div>

            <div class="form-row form-row">
                <label
                    class="labels-with-icons"
                    for="maxipago-card-cvc"><?php esc_attr_e('Security Code', 'woo-rede'); ?><span
                        class="required">*</span>
                    <div class="icon-maxipago-input">
                        <svg
                            version="1.1"
                            id="Capa_1"
                            xmlns="http://www.w3.org/2000/svg"
                            xmlns:xlink="http://www.w3.org/1999/xlink"
                            x="0px"
                            y="3px"
                            width="24px"
                            height="17px"
                            viewBox="0 0 216 146"
                            enable-background="new 0 0 216 146"
                            xml:space="preserve">
                            <path
                                class="svg"
                                d="M152.646,70.067c-1.521-1.521-3.367-2.281-5.541-2.281H144.5V52.142c0-9.994-3.585-18.575-10.754-25.745c-7.17-7.17-15.751-10.755-25.746-10.755s-18.577,3.585-25.746,10.755C75.084,33.567,71.5,42.148,71.5,52.142v15.644h-2.607c-2.172,0-4.019,0.76-5.54,2.281c-1.521,1.52-2.281,3.367-2.281,5.541v46.929c0,2.172,0.76,4.019,2.281,5.54c1.521,1.52,3.368,2.281,5.54,2.281h78.214c2.174,0,4.02-0.76,5.541-2.281c1.52-1.521,2.281-3.368,2.281-5.54V75.607C154.93,73.435,154.168,71.588,152.646,70.067z M128.857,67.786H87.143V52.142c0-5.757,2.037-10.673,6.111-14.746c4.074-4.074,8.989-6.11,14.747-6.11s10.673,2.036,14.746,6.11c4.073,4.073,6.11,8.989,6.11,14.746V67.786z"
                                style="fill: rgb(21, 140, 186);"></path>
                        </svg>
                    </div>
                </label>
                <input
                    id="maxipago-card-cvc"
                    name="maxipago_credit_cvc"
                    class="input-text wc-credit-card-form-card-cvc"
                    type="tel"
                    autocomplete="off"
                    placeholder="<?php esc_attr_e('CVC', 'woo-rede'); ?>"
                    style="font-size: 1.5em; padding: 8px 30px 8px 35px;" />
            </div>

            <div class="form-row form-row">
                <?php if (is_array($installments) && count($installments) > 1) { ?>

                    <label for="installments">
                        <?php esc_attr_e('Installments', 'woo-rede'); ?>
                        <span class="required">*</span>
                    </label>
                    <select
                        id="maxipago-card-installments"
                        name="maxipago_credit_installments"
                        class="input-text wc-credit-card-form-card-cvc lknIntegrationRedeForWoocommerceSelect"
                        style="<?php echo esc_attr($integration_rede_for_woocommerce_selectStyle) ?>"
                        autocomplete="off">
                        <?php
                        $integration_rede_for_woocommerce_default_installment = isset($installments_number) ? (int)$installments_number : 1;
                        foreach ($installments as $integration_rede_for_woocommerce_installment) {
                            $integration_rede_for_woocommerce_selected = ($integration_rede_for_woocommerce_installment['num'] == $integration_rede_for_woocommerce_default_installment) ? 'selected' : '';
                            printf('<option value="%d" %s>%s</option>', esc_attr($integration_rede_for_woocommerce_installment['num']), esc_attr($integration_rede_for_woocommerce_selected), esc_html($integration_rede_for_woocommerce_installment['label']));
                        }
                        ?>
                    </select>

                <?php } ?>

            </div>

            <div class="clear"></div>
        </div>
    </div>
</fieldset>