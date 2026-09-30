<?php
if (!defined('ABSPATH')) {
    exit();
}
?>
<div id="lknIntegrationRedeForWoocommerceSettingsCardContainer">
    <div id="lknIntegrationRedeForWoocommerceSettingsCard" style="background-image: url('<?php echo esc_url($backgrounds['right']); ?>'), url('<?php echo esc_url($backgrounds['left']); ?>'); display:none;">
        <div id="lknIntegrationRedeForWoocommerceDivLogo">
            <div>
                <?php //phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage 
                ?>
                <img src=<?php echo esc_url($logo); ?> alt="Logo">
                <?php //phpcs:enable 
                ?>
            </div>
            <p><?php echo esc_attr($versions); ?></p>
        </div>
        <div id="lknIntegrationRedeForWoocommerDivContent">
            <div id="lknIntegrationRedeForWoocommerDivLinks">
                <div>
                    <a target="_blank" href=<?php echo esc_url('https://www.linknacional.com.br/wordpress/woocommerce/rede/?utm=plugin'); ?>>
                        <b>•</b><?php esc_attr_e('Documentation', 'woo-rede'); ?>
                    </a>
                    <a target="_blank" href=<?php echo esc_url('https://www.linknacional.com.br/wordpress/'); ?>>
                        <b>•</b><?php esc_attr_e('Hosting', 'woo-rede'); ?>
                    </a>
                </div>
                <div>
                    <a target="_blank" href=<?php echo esc_url('https://www.linknacional.com.br/wordpress/plugins/'); ?>>
                        <b>•</b><?php esc_attr_e('WP Plugin', 'woo-rede'); ?>
                    </a>
                    <a target="_blank" href=<?php echo esc_url('https://www.linknacional.com.br/wordpress/suporte/'); ?>>
                        <b>•</b><?php esc_attr_e('WP Support', 'woo-rede'); ?>
                    </a>
                </div>
            </div>
            <div class="lknIntegrationRedeForWoocommerceSupportLinks">
                <div id="lknIntegrationRedeForWoocommerStarsDiv">
                    <a target="_blank" href=<?php echo esc_url('https://wordpress.org/support/plugin/woo-rede/reviews/#new-post'); ?>>
                        <p><?php esc_attr_e('Rate Plugin', 'woo-rede'); ?></p>
                        <div class="lknIntegrationRedeForWoocommerceStars">
                            <span class="dashicons dashicons-star-filled lkn-stars"></span>
                            <span class="dashicons dashicons-star-filled lkn-stars"></span>
                            <span class="dashicons dashicons-star-filled lkn-stars"></span>
                            <span class="dashicons dashicons-star-filled lkn-stars"></span>
                            <span class="dashicons dashicons-star-filled lkn-stars"></span>
                        </div>
                    </a>
                </div>
                <div class="lknIntegrationRedeForWoocommerceContactLinks">
                    <a href=<?php echo esc_url('https://chat.whatsapp.com/C6S3my9Adr818hbeJphPBm'); ?> target="_blank">
                        <?php //phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage 
                        ?>
                        <img src="<?php echo esc_url($whatsapp); ?>" alt="Whatsapp Icon" class="lknIntegrationRedeForWoocommerceContactIcon">
                        <?php //phpcs:enable 
                        ?>
                    </a>
                    <a href=<?php echo esc_url('https://t.me/wpprobr'); ?> target="_blank">
                        <?php //phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage 
                        ?>
                        <img src="<?php echo esc_url($telegram); ?>" alt="Telegram Icon" class="lknIntegrationRedeForWoocommerceContactIcon">
                        <?php //phpcs:enable 
                        ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
